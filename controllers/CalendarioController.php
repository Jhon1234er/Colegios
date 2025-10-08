<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

class CalendarioController {
    private $pdo;
    
    public function __construct() {
        // Asegurar sesión iniciada para uso consistente de $_SESSION
        if (function_exists('start_secure_session')) {
            start_secure_session();
        }
        $this->pdo = Database::conectar();
        
        // Manejar acciones AJAX
        $action = $_GET['action'] ?? '';
        
        if ($action === 'obtenerFichas') {
            $this->obtenerFichas();
            exit;
        } elseif ($action === 'exportarReporte') {
            $this->exportarReporteCSV();
            exit;
        }
    }
    
    // Obtener horarios para el calendario
    public function obtenerHorarios() {
        header('Content-Type: application/json');
        
        try {
            // Verificar sesión primero
            if (!isset($_SESSION['usuario'])) {
                http_response_code(401); // No autorizado
                echo json_encode(['error' => 'No autorizado. Por favor inicie sesión.']);
                return;
            }
            
            // Obtener parámetros de vista del frontend
            $start = $_GET['start'] ?? null;
            $end = $_GET['end'] ?? null;
            // Normalizar a formato MySQL 'Y-m-d H:i:s'
            $start = $this->normalizarDateTime($start);
            $end   = $this->normalizarDateTime($end);
            $view = $_GET['view'] ?? 'dayGridMonth';
            $profesorFiltro = $_GET['profesor_id'] ?? null;
            $esAdmin = (int)($_SESSION['usuario']['rol_id'] ?? 0) === 1;
            
            error_log("Parámetros recibidos - Start: $start, End: $end, View: $view");
            
            // Delegar por rol y contexto
            if ($esAdmin) {
                echo json_encode($this->obtenerHorariosAdmin($start, $end, $profesorFiltro));
                return;
            }

            $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
            if (!$profesor_id) {
                echo json_encode($this->obtenerHorariosPublico());
                return;
            }

            echo json_encode($this->obtenerHorariosProfesor($start, $end, $profesor_id));
            return;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener horarios: ' . $e->getMessage()]);
        }
    }

    /**
     * Convierte una cadena ISO (posible con T/Z u offset) a 'Y-m-d H:i:s'.
     */
    private function normalizarDateTime($s) {
        if (!$s) return null;
        try {
            // Remover Z u offsets si existen para crear DateTime correctamente
            // Ejemplos: 2025-09-24T00:00:00Z, 2025-09-24T00:00:00-05:00
            // DateTime entiende ambos, solo formateamos a MySQL
            $dt = new DateTime($s);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // Fallback manual: reemplazar 'T' por espacio y recortar a 19 chars
            $s2 = str_replace('T', ' ', $s);
            $s2 = substr($s2, 0, 19);
            // Validar que DateTime lo acepte
            try { $dt2 = new DateTime($s2); return $dt2->format('Y-m-d H:i:s'); } catch(Exception $e2) { return null; }
        }
    }

    // ==== Helpers de obtención por rol ====
    private function obtenerHorariosAdmin(?string $start, ?string $end, $profesorFiltro = null): array {
        $sql = "
            SELECT 
                hf.*,
                f.numero as ficha_codigo,
                f.nombre as ficha_nombre,
                u.nombres as profesor_nombre,
                CONCAT(u.nombres, ' ', u.apellidos) as profesor_completo,
                hf.color as color_actual
            FROM horarios_fichas hf
            JOIN fichas f ON hf.ficha_id = f.id
            JOIN profesores p ON hf.profesor_id = p.id
            JOIN usuarios u ON p.usuario_id = u.id
            WHERE 1=1";

        $params = [];
        if ($profesorFiltro) { $sql .= " AND hf.profesor_id = ?"; $params[] = $profesorFiltro; }
        if ($start && $end) { $sql .= " AND hf.fecha_inicio >= ? AND hf.fecha_fin <= ?"; $params[] = $start; $params[] = $end; }
        $sql .= " ORDER BY hf.fecha_inicio, u.nombres, u.apellidos";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $eventos = [];
        foreach ($horarios as $horario) {
            $ahora = new DateTime();
            $fechaInicio = new DateTime($horario['fecha_inicio']);
            $fechaFin = new DateTime($horario['fecha_fin']);
            $esPasado = $fechaFin < $ahora && $horario['estado'] !== 'cancelado' && $horario['estado'] !== 'finalizado';
            $color = $esPasado ? '#6c757d' : $horario['color_actual'];
            $eventos[] = [
                'id' => $horario['id'],
                'title' => $horario['titulo'] . ' - ' . $horario['profesor_completo'],
                'start' => $fechaInicio->format('Y-m-d\TH:i:s'),
                'end' => $fechaFin->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'className' => 'evento-' . $horario['estado'],
                'extendedProps' => [
                    'ficha_id' => $horario['ficha_id'],
                    'ficha_codigo' => $horario['ficha_codigo'],
                    'ficha_nombre' => $horario['ficha_nombre'],
                    'profesor_nombre' => $horario['profesor_nombre'],
                    'profesor_id' => $horario['profesor_id'],
                    'aula' => $horario['aula'],
                    'estado' => $horario['estado'],
                    'tipo' => 'admin_vista',
                    'asistencia_habilitada' => $horario['asistencia_habilitada']
                ]
            ];
        }
        return $eventos;
    }

    private function obtenerHorariosPublico(): array {
        $sql = "
            SELECT 
                hf.*,
                f.numero as ficha_codigo,
                f.nombre as ficha_nombre,
                u.nombres as profesor_nombre,
                hf.color as color_actual
            FROM horarios_fichas hf
            JOIN fichas f ON hf.ficha_id = f.id
            JOIN profesores p ON hf.profesor_id = p.id
            JOIN usuarios u ON p.usuario_id = u.id
            ORDER BY hf.fecha_inicio";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $eventos = [];
        foreach ($horarios as $horario) {
            $ahora = new DateTime();
            $fechaEvento = new DateTime($horario['fecha_fin']);
            $esPasado = $fechaEvento < $ahora;
            $color = $esPasado ? '#6c757d' : $horario['color_actual'];
            $claseEstado = $esPasado ? 'evento-pasado' : 'evento-' . $horario['estado'];
            $fechaInicio = new DateTime($horario['fecha_inicio']);
            $fechaFin = new DateTime($horario['fecha_fin']);
            $tituloModificado = str_replace('Profesor', $horario['profesor_nombre'], $horario['titulo']);
            $eventos[] = [
                'id' => $horario['id'],
                'title' => $tituloModificado,
                'start' => $fechaInicio->format('Y-m-d\TH:i:s'),
                'end' => $fechaFin->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'className' => $claseEstado,
                'extendedProps' => [
                    'ficha_id' => $horario['ficha_id'],
                    'ficha_codigo' => $horario['ficha_codigo'],
                    'ficha_nombre' => $horario['ficha_nombre'],
                    'profesor_nombre' => $horario['profesor_nombre'],
                    'aula' => $horario['aula'],
                    'estado' => $horario['estado'],
                    'tipo' => 'publico',
                    'asistencia_habilitada' => $horario['asistencia_habilitada']
                ]
            ];
        }
        return $eventos;
    }

    private function obtenerHorariosProfesor(?string $start, ?string $end, int $profesor_id): array {
        $eventos = [];

        // 1) Propios y compartidos
        $sql_propios = "
            SELECT 
                hf.*,
                f.numero as ficha_codigo,
                f.nombre as ficha_nombre,
                u.nombres as profesor_nombre,
                hf.color as color_actual,
                CASE 
                    WHEN hf.creado_por = ? THEN 'propio'
                    ELSE 'compartido'
                END as tipo_horario
            FROM horarios_fichas hf
            JOIN fichas f ON hf.ficha_id = f.id
            JOIN profesores p ON hf.profesor_id = p.id
            JOIN usuarios u ON p.usuario_id = u.id
            WHERE (hf.profesor_id = ? 
               OR hf.ficha_id IN (
                   SELECT pf.ficha_id FROM profesor_ficha pf WHERE pf.profesor_id = ?
               )
               OR hf.ficha_id IN (
                   SELECT fc.ficha_id FROM fichas_compartidas fc 
                   WHERE fc.profesor_compartido_id = ? AND fc.estado = 'aceptada'
               ))";

        $params_propios = [$profesor_id, $profesor_id, $profesor_id, $profesor_id];
        if ($start && $end) { $sql_propios .= " AND hf.fecha_inicio >= ? AND hf.fecha_fin <= ?"; $params_propios[] = $start; $params_propios[] = $end; }
        $sql_propios .= " ORDER BY hf.fecha_inicio";
        $stmt = $this->pdo->prepare($sql_propios);
        $stmt->execute($params_propios);
        $horarios_propios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($horarios_propios as $horario) {
            $fechaInicio = new DateTime($horario['fecha_inicio']);
            $fechaFin = new DateTime($horario['fecha_fin']);
            $eventos[] = [
                'id' => $horario['id'],
                'title' => $horario['titulo'],
                'start' => $fechaInicio->format('Y-m-d\TH:i:s'),
                'end' => $fechaFin->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $horario['color_actual'],
                'borderColor' => $horario['color_actual'],
                'className' => 'evento-' . $horario['estado'],
                'extendedProps' => [
                    'ficha_id' => $horario['ficha_id'],
                    'ficha_codigo' => $horario['ficha_codigo'],
                    'ficha_nombre' => $horario['ficha_nombre'],
                    'profesor_nombre' => $horario['profesor_nombre'],
                    'aula' => $horario['aula'],
                    'estado' => $horario['estado'],
                    'tipo' => $horario['tipo_horario'],
                    'asistencia_habilitada' => $horario['asistencia_habilitada']
                ]
            ];
        }

        // 2) Sincronizados (opcional: si la tabla no existe, continuar sin error)
        try {
            $sql_sincronizados = "
                SELECT 
                    hf.*,
                    f.numero as ficha_codigo,
                    f.nombre as ficha_nombre,
                    u.nombres as profesor_nombre,
                    hf.color as color_actual,
                    cs.permisos as permisos
                FROM horarios_fichas hf
                JOIN fichas f ON hf.ficha_id = f.id
                JOIN profesores p ON hf.profesor_id = p.id
                JOIN usuarios u ON p.usuario_id = u.id
                JOIN calendario_sincronizacion cs ON (
                    (cs.profesor_propietario_id = ? AND cs.profesor_sincronizado_id = hf.profesor_id) OR
                    (cs.profesor_sincronizado_id = ? AND cs.profesor_propietario_id = hf.profesor_id)
                )
                WHERE cs.estado = 'aceptado'
                  AND hf.profesor_id != ?
                ORDER BY hf.fecha_inicio";

            $stmt = $this->pdo->prepare($sql_sincronizados);
            $stmt->execute([$profesor_id, $profesor_id, $profesor_id]);
            $horarios_sincronizados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($horarios_sincronizados as $horario) {
                $fechaInicio = new DateTime($horario['fecha_inicio']);
                $fechaFin = new DateTime($horario['fecha_fin']);
                $eventos[] = [
                    'id' => 'sync_' . $horario['id'],
                    'title' => $horario['titulo'] . ' (' . $horario['profesor_nombre'] . ')',
                    'start' => $fechaInicio->format('Y-m-d\TH:i:s'),
                    'end' => $fechaFin->format('Y-m-d\TH:i:s'),
                    'backgroundColor' => $horario['color_actual'],
                    'borderColor' => $horario['color_actual'],
                    'className' => 'evento-sincronizado evento-' . $horario['estado'],
                    'editable' => ($horario['permisos'] ?? '') === 'lectura_escritura',
                    'extendedProps' => [
                        'horario_original_id' => $horario['id'],
                        'ficha_id' => $horario['ficha_id'],
                        'ficha_codigo' => $horario['ficha_codigo'],
                        'ficha_nombre' => $horario['ficha_nombre'],
                        'profesor_nombre' => $horario['profesor_nombre'],
                        'aula' => $horario['aula'],
                        'estado' => $horario['estado'],
                        'tipo' => 'sincronizado',
                        'permisos' => $horario['permisos'] ?? '',
                        'asistencia_habilitada' => $horario['asistencia_habilitada']
                    ]
                ];
            }
        } catch (Exception $e) {
            // Silencioso: la tabla de sincronización puede no existir en algunas instalaciones
            // error_log('Sincronizados deshabilitado: ' . $e->getMessage());
        }

        return $eventos;
    }

    // Duplicar una semana completa al resto del mes para una ficha
    public function duplicarSemana() {
        start_secure_session();
        header('Content-Type: application/json');
        try {
            require_role([1,2]);
            $body = json_decode(file_get_contents('php://input'), true);
            $ficha_id = (int)($body['ficha_id'] ?? 0);
            $week_start = $body['week_start'] ?? null; // ISO YYYY-MM-DD (lunes)
            $months_ahead = (int)($body['months_ahead'] ?? 0); // 0: solo mes actual, 1: incluye próximo mes
            if (!$ficha_id || !$week_start || !strtotime($week_start)) {
                http_response_code(400);
                echo json_encode(['success'=>false,'error'=>'Parámetros inválidos']);
                return;
            }

            // Rango de la semana: lunes -> domingo
            $monday = new DateTime($week_start.' 00:00:00');
            $sunday = clone $monday; $sunday->modify('+6 days')->setTime(23,59,59);

            // Obtener eventos de esa semana para la ficha
            $sql = "SELECT * FROM horarios_fichas WHERE ficha_id = ? AND fecha_inicio BETWEEN ? AND ? ORDER BY fecha_inicio";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ficha_id, $monday->format('Y-m-d H:i:s'), $sunday->format('Y-m-d H:i:s')]);
            $baseEventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($baseEventos)) {
                echo json_encode(['success'=>false,'error'=>'No hay clases en la semana seleccionada']);
                return;
            }

            // Datos de ficha para validar
            $stmtF = $this->pdo->prepare("SELECT jornada, dias_semana FROM fichas WHERE id = ?");
            $stmtF->execute([$ficha_id]);
            $ficha = $stmtF->fetch(PDO::FETCH_ASSOC) ?: [];
            // Preparar días/jornada por día
            $configDias = [];
            if (!empty($ficha['dias_semana'])) {
                try { $configDias = json_decode($ficha['dias_semana'], true) ?: []; } catch(Exception $e) { $configDias = []; }
            }
            $diasPermitidos = [];
            $jornadaPorDia = [];
            if ($configDias) {
                if (array_keys($configDias) !== range(0, count($configDias) - 1)) {
                    $diasPermitidos = array_keys($configDias);
                    $jornadaPorDia = $configDias;
                } else {
                    $diasPermitidos = $configDias;
                }
            } else {
                $diasPermitidos = ['lunes','martes','miercoles','jueves','viernes'];
            }
            $jornadaGlobal = strtolower(trim((string)($ficha['jornada'] ?? '')));
            $rangos = [ 'mañana'=>['06:00:00','12:00:00'], 'manana'=>['06:00:00','12:00:00'], 'tarde'=>['12:00:00','18:00:00'], 'noche'=>['18:00:00','22:00:00'] ];

            // Final de alcance (fin de mes actual + months_ahead)
            $endOfMonth = new DateTime($monday->format('Y-m-01').' 00:00:00');
            $endOfMonth->modify('last day of this month')->setTime(23,59,59);
            if ($months_ahead > 0) {
                $endOfMonth->modify('+'.$months_ahead.' month');
                $endOfMonth->modify('last day of this month')->setTime(23,59,59);
            }

            $insertados = 0;

            foreach ($baseEventos as $ev) {
                $start = new DateTime($ev['fecha_inicio']);
                $end   = new DateTime($ev['fecha_fin']);
                // Iterar por semanas
                $cursorStart = clone $start; $cursorEnd = clone $end;
                while (true) {
                    $cursorStart->modify('+7 days');
                    $cursorEnd->modify('+7 days');
                    if ($cursorStart > $endOfMonth) break;

                    // Validar día permitido
                    $diaNum = (int)$cursorStart->format('N');
                    $mapDias = [1=>'lunes',2=>'martes',3=>'miercoles',4=>'jueves',5=>'viernes',6=>'sabado',7=>'domingo'];
                    $diaNombre = $mapDias[$diaNum] ?? '';
                    if (!in_array($diaNombre, $diasPermitidos, true)) continue;

                    // Validar jornada (por día si existe, si no global)
                    $jornadaDia = !empty($jornadaPorDia[$diaNombre]) ? strtolower(trim((string)$jornadaPorDia[$diaNombre])) : $jornadaGlobal;
                    if (!empty($jornadaDia) && isset($rangos[$jornadaDia])) {
                        [$iniJ,$finJ] = $rangos[$jornadaDia];
                        $horaIni = $cursorStart->format('H:i:s');
                        $horaFin = $cursorEnd->format('H:i:s');
                        if (!($horaIni >= $iniJ && $horaFin <= $finJ)) continue;
                    }

                    // Evitar duplicados: si ya hay un evento en esa ficha que se solape exactamente
                    $sqlChk = "SELECT COUNT(*) FROM horarios_fichas WHERE ficha_id = ? AND ((? BETWEEN fecha_inicio AND fecha_fin) OR (? BETWEEN fecha_inicio AND fecha_fin) OR (fecha_inicio = ?) )";
                    $stmtChk = $this->pdo->prepare($sqlChk);
                    $stmtChk->execute([$ficha_id, $cursorStart->format('Y-m-d H:i:s'), $cursorEnd->format('Y-m-d H:i:s'), $cursorStart->format('Y-m-d H:i:s')]);
                    if ((int)$stmtChk->fetchColumn() > 0) continue;

                    // Insertar
                    $stmtIns = $this->pdo->prepare("INSERT INTO horarios_fichas (profesor_id, ficha_id, titulo, fecha_inicio, fecha_fin, aula, color, estado, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $ok = $stmtIns->execute([
                        $ev['profesor_id'], $ficha_id, $ev['titulo'],
                        $cursorStart->format('Y-m-d H:i:s'), $cursorEnd->format('Y-m-d H:i:s'),
                        $ev['aula'], $ev['color'], 'programado', $ev['creado_por']
                    ]);
                    if ($ok) $insertados++;
                }
            }

            echo json_encode(['success'=>true,'insertados'=>$insertados]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }
    
    // Crear nuevo horario
    public function crearHorario() {
        try {
            start_secure_session();
            header('Content-Type: application/json');
            
            if (!isset($_SESSION['usuario'])) {
                http_response_code(401);
                echo json_encode(['error' => 'No autorizado']);
                return;
            }
            
            require_role(2);
            $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
            
            if (!$profesor_id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de profesor no encontrado']);
                return;
            }
            
            // Obtener datos del POST (FormData)
            $ficha_id = $_POST['ficha_id'] ?? null;
            $titulo = $_POST['titulo'] ?? null;
            $fecha_inicio = $_POST['fecha_inicio'] ?? null;
            $fecha_fin = $_POST['fecha_fin'] ?? null;
            $aula = $_POST['aula'] ?? null;
            $color = $_POST['color'] ?? '#007bff';
            $estado = $_POST['estado'] ?? 'programado';
            
            error_log("=== CREANDO HORARIO ===");
            error_log("Ficha ID: " . $ficha_id);
            error_log("Título: " . $titulo);
            error_log("Fecha inicio: " . $fecha_inicio);
            error_log("Fecha fin: " . $fecha_fin);
            error_log("Aula: " . $aula);
            
            if (!$ficha_id || !$titulo || !$fecha_inicio || !$fecha_fin || !$aula) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan datos requeridos']);
                return;
            }

            // ===== Validaciones de ficha: días permitidos y jornada =====
            $stmtFicha = $this->pdo->prepare("SELECT id, nombre, jornada, dias_semana FROM fichas WHERE id = ?");
            $stmtFicha->execute([$ficha_id]);
            $ficha = $stmtFicha->fetch(PDO::FETCH_ASSOC);
            if (!$ficha) {
                http_response_code(400);
                echo json_encode(['error' => 'Ficha no encontrada']);
                return;
            }

            // Días permitidos y jornada (soportar formato: ["lunes",...] o {"lunes":"mañana",...})
            $configDias = [];
            if (!empty($ficha['dias_semana'])) {
                try { $configDias = json_decode($ficha['dias_semana'], true) ?: []; } catch(Exception $e) { $configDias = []; }
            }
            $dtInicio = new DateTime($fecha_inicio);
            $dtFin    = new DateTime($fecha_fin);
            $ahora    = new DateTime();
            $diaNum = (int)$dtInicio->format('N'); // 1=lun..7=dom
            $mapDias = [1=>'lunes',2=>'martes',3=>'miercoles',4=>'jueves',5=>'viernes',6=>'sabado',7=>'domingo'];
            $diaNombre = $mapDias[$diaNum] ?? '';

            // Derivar lista de días permitidos y jornadaPorDia
            $diasPermitidos = [];
            $jornadaPorDia = [];
            if ($configDias) {
                if (array_keys($configDias) !== range(0, count($configDias) - 1)) {
                    // Asociativo: { dia: jornada }
                    $diasPermitidos = array_keys($configDias);
                    $jornadaPorDia = $configDias;
                } else {
                    // Indexado: [dias]; tomar jornada global de la columna ficha.jornada
                    $diasPermitidos = $configDias;
                }
            } else {
                $diasPermitidos = ['lunes','martes','miercoles','jueves','viernes'];
            }

            // Si la hora solicitada ya pasó (hoy o fecha anterior), mover al siguiente día permitido manteniendo la misma hora
            if ($dtInicio <= $ahora) {
                for ($i = 0; $i < 7; $i++) {
                    $dtInicio->modify('+1 day');
                    $dtFin->modify('+1 day');
                    $diaNumTmp = (int)$dtInicio->format('N');
                    $diaNombreTmp = $mapDias[$diaNumTmp] ?? '';
                    if (!in_array($diaNombreTmp, $diasPermitidos, true)) { continue; }
                    // Validar jornada del día (si existe) para la hora propuesta
                    $rangosTmp = [ 'mañana'=>['06:00:00','12:00:00'], 'manana'=>['06:00:00','12:00:00'], 'tarde'=>['12:00:00','18:00:00'], 'noche'=>['18:00:00','22:00:00'] ];
                    $jornadaDiaTmp = !empty($jornadaPorDia[$diaNombreTmp]) ? strtolower(trim((string)$jornadaPorDia[$diaNombreTmp])) : strtolower(trim((string)$ficha['jornada'] ?? ''));
                    if (!empty($jornadaDiaTmp) && isset($rangosTmp[$jornadaDiaTmp])) {
                        [$iniJ,$finJ] = $rangosTmp[$jornadaDiaTmp];
                        $horaIni = $dtInicio->format('H:i:s');
                        $horaFin = $dtFin->format('H:i:s');
                        if (!($horaIni >= $iniJ && $horaFin <= $finJ)) { continue; }
                    }
                    // Aceptado
                    $fecha_inicio = $dtInicio->format('Y-m-d H:i:s');
                    $fecha_fin    = $dtFin->format('Y-m-d H:i:s');
                    // Recalcular variables de día
                    $diaNombre = $diaNombreTmp;
                    break;
                }
            }

            if (!in_array($diaNombre, $diasPermitidos, true)) {
                http_response_code(400);
                echo json_encode(['error' => 'El día seleccionado no está permitido para esta ficha']);
                return;
            }

            // Ventana de jornada
            $rangos = [ 'mañana'=>['06:00:00','12:00:00'], 'manana'=>['06:00:00','12:00:00'], 'tarde'=>['12:00:00','18:00:00'], 'noche'=>['18:00:00','22:00:00'] ];
            $jornadaDia = null;
            if (!empty($jornadaPorDia[$diaNombre])) {
                $jornadaDia = strtolower(trim((string)$jornadaPorDia[$diaNombre]));
            } else {
                $jornadaDia = strtolower(trim((string)$ficha['jornada']));
            }
            if (!empty($jornadaDia) && isset($rangos[$jornadaDia])) {
                [$iniJ, $finJ] = $rangos[$jornadaDia];
                $horaIni = (new DateTime($fecha_inicio))->format('H:i:s');
                $horaFin = (new DateTime($fecha_fin))->format('H:i:s');
                if (!($horaIni >= $iniJ && $horaFin <= $finJ)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'El horario propuesto está fuera de la jornada configurada para el día']);
                    return;
                }
            }

            // Insertar en la base de datos
            $sql = "INSERT INTO horarios_fichas 
                    (profesor_id, ficha_id, titulo, fecha_inicio, fecha_fin, aula, color, estado, creado_por) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $profesor_id,
                $ficha_id,
                $titulo,
                $fecha_inicio,
                $fecha_fin,
                $aula,
                $color,
                $estado,
                $profesor_id
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Horario creado correctamente']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al crear el horario']);
            }
        } catch (Exception $e) {
            error_log('Error en crearHorario: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al crear el horario: ' . $e->getMessage()]);
        }
    }
    
    // Actualizar horario existente
    public function actualizarHorario() {
        start_secure_session();
        
        // Permitir actualización sin autenticación estricta
        $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $horario_id = $data['id'];
            
            // Verificar permisos solo si hay profesor_id
            if ($profesor_id && !$this->tienePermisosHorario($horario_id, $profesor_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para modificar este horario']);
                return;
            }
            
            // Obtener datos anteriores
            $stmt = $this->pdo->prepare("SELECT * FROM horarios_fichas WHERE id = ?");
            $stmt->execute([$horario_id]);
            $datos_anteriores = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar conflictos si cambió fecha/hora
            if ($data['fecha_inicio'] !== $datos_anteriores['fecha_inicio'] || 
                $data['fecha_fin'] !== $datos_anteriores['fecha_fin']) {
                $conflictos = $this->verificarConflictos($data, $profesor_id, $horario_id);
                if (!empty($conflictos)) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Conflicto de horarios detectado', 'conflictos' => $conflictos]);
                    return;
                }
            }
            
            $sql = "
                UPDATE horarios_fichas 
                SET titulo = ?, fecha_inicio = ?, fecha_fin = ?, 
                    aula = ?, color = ?
                WHERE id = ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['titulo'],
                $data['fecha_inicio'],
                $data['fecha_fin'],
                $data['aula'] ?? null,
                $data['color'] ?? '#007bff',
                $horario_id
            ]);
            
            // Registrar en historial
            $this->registrarHistorial($horario_id, $profesor_id, 'modificar', $datos_anteriores, $data);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    // Eliminar horario
    public function eliminarHorario() {
        start_secure_session();
        require_role(2);
        $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
        
        try {
            $horario_id = $_POST['horario_id'] ?? $_GET['horario_id'];
            
            if (!$this->tienePermisosHorario($horario_id, $profesor_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para eliminar este horario']);
                return;
            }
            
            // Obtener datos antes de eliminar
            $stmt = $this->pdo->prepare("SELECT * FROM horarios_fichas WHERE id = ?");
            $stmt->execute([$horario_id]);
            $datos_anteriores = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $this->pdo->prepare("DELETE FROM horarios_fichas WHERE id = ?");
            $stmt->execute([$horario_id]);
            
            // Registrar en historial
            $this->registrarHistorial($horario_id, $profesor_id, 'eliminar', $datos_anteriores, null);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    // Cambiar estado de horario
    public function cambiarEstado() {
        start_secure_session();
        require_role(2);
        $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $horario_id = $data['horario_id'];
            $nuevo_estado = $data['estado'];
            
            if (!$this->tienePermisosHorario($horario_id, $profesor_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para modificar este horario']);
                return;
            }
            
            $sql = "UPDATE horarios_fichas SET estado = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$nuevo_estado, $horario_id]);
            
            // Habilitar asistencia si la clase está en curso
            if ($nuevo_estado === 'en_curso') {
                $sql_asistencia = "UPDATE horarios_fichas SET asistencia_habilitada = TRUE WHERE id = ?";
                $stmt_asistencia = $this->pdo->prepare($sql_asistencia);
                $stmt_asistencia->execute([$horario_id]);
            }
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    // Verificar conflictos de horarios
    private function verificarConflictos($data, $profesor_id, $excluir_id = null) {
        $sql = "
            SELECT hf.*, f.numero as ficha_codigo
            FROM horarios_fichas hf
            JOIN fichas f ON hf.ficha_id = f.id
            WHERE hf.profesor_id = ? 
              AND hf.estado != 'cancelado'
              AND (
                  (? BETWEEN hf.fecha_inicio AND hf.fecha_fin) OR
                  (? BETWEEN hf.fecha_inicio AND hf.fecha_fin) OR
                  (hf.fecha_inicio BETWEEN ? AND ?) OR
                  (hf.fecha_fin BETWEEN ? AND ?)
              )
        ";
        
        $params = [
            $profesor_id,
            $data['fecha_inicio'], $data['fecha_fin'],
            $data['fecha_inicio'], $data['fecha_fin'],
            $data['fecha_inicio'], $data['fecha_fin']
        ];
        
        if ($excluir_id) {
            $sql .= " AND hf.id != ?";
            $params[] = $excluir_id;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Verificar permisos sobre un horario
    private function tienePermisosHorario($horario_id, $profesor_id) {
        $sql = "
            SELECT COUNT(*) as tiene_permisos
            FROM horarios_fichas hf
            WHERE hf.id = ? AND (
                hf.creado_por = ? OR
                hf.ficha_id IN (
                    SELECT fc.ficha_id 
                    FROM fichas_compartidas fc 
                    WHERE fc.profesor_compartido_id = ? 
                      AND fc.estado = 'aceptada'
                )
            )
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$horario_id, $profesor_id, $profesor_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado['tiene_permisos'] > 0;
    }
    
    public function exportarReporteCSV() {
        try {
            // Verificar sesión
            start_secure_session();
            
            if (!isset($_SESSION['usuario'])) {
                http_response_code(401);
                echo 'No autorizado';
                return;
            }
            
            // Obtener parámetros de filtro
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
            $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-t');
            $estado = $_GET['estado'] ?? null;
            
            // Manejar el ID del profesor según el rol del usuario
            $profesorId = null;
            if (isset($_SESSION['usuario'])) {
                if ($_SESSION['usuario']['rol_id'] == 1 && isset($_GET['profesor_id'])) {
                    // Admin puede ver cualquier profesor
                    $profesorId = $_GET['profesor_id'];
                } else if (isset($_SESSION['usuario']['profesor_id'])) {
                    // Profesores solo pueden verse a sí mismos
                    $profesorId = $_SESSION['usuario']['profesor_id'];
                }
            }
            
            // Registrar parámetros para depuración
            error_log('Exportar CSV - Parámetros: ' . print_r([
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'estado' => $estado,
                'profesor_id' => $profesorId,
                'usuario_rol' => $_SESSION['usuario']['rol_id'] ?? 'no-sesion',
                'usuario_profesor_id' => $_SESSION['usuario']['profesor_id'] ?? 'no-profesor'
            ], true));
            
            try {
                // Construir consulta base
                $sql = "
                    SELECT 
                        hf.id,
                        hf.titulo,
                        hf.fecha_inicio,
                        hf.fecha_fin,
                        hf.aula,
                        hf.estado,
                        f.numero as ficha_numero,
                        f.nombre as ficha_nombre,
                        CONCAT(u.nombres, ' ', u.apellidos) as profesor_nombre,
                        (SELECT COUNT(*) FROM asistencias a WHERE a.ficha_id = hf.ficha_id AND a.fecha BETWEEN hf.fecha_inicio AND hf.fecha_fin) as total_asistencias,
                        (SELECT COUNT(*) FROM asistencias a WHERE a.ficha_id = hf.ficha_id AND a.estado = 'presente' AND a.fecha BETWEEN hf.fecha_inicio AND hf.fecha_fin) as asistencias_confirmadas
                    FROM horarios_fichas hf
                    JOIN fichas f ON hf.ficha_id = f.id
                    JOIN profesores p ON hf.profesor_id = p.id
                    JOIN usuarios u ON p.usuario_id = u.id
                    WHERE hf.fecha_inicio BETWEEN ? AND ?
                ";
                
                $params = [$fechaInicio, $fechaFin];
                
                // Aplicar filtros adicionales
                if ($estado) {
                    $sql .= " AND hf.estado = ?";
                    $params[] = $estado;
                }
                
                if ($profesorId) {
                    $sql .= " AND hf.profesor_id = ?";
                    $params[] = $profesorId;
                }
                
                $sql .= " ORDER BY hf.fecha_inicio, u.apellidos, u.nombres";
                
                error_log('Ejecutando consulta SQL: ' . $sql);
                error_log('Parámetros: ' . print_r($params, true));
                
                $stmt = $this->pdo->prepare($sql);
                if (!$stmt) {
                    throw new Exception('Error al preparar la consulta: ' . print_r($this->pdo->errorInfo(), true));
                }
                
                $result = $stmt->execute($params);
                if (!$result) {
                    throw new Exception('Error al ejecutar la consulta: ' . print_r($stmt->errorInfo(), true));
                }
                
                $clases = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log('Se encontraron ' . count($clases) . ' clases para el reporte');
                
            } catch (Exception $e) {
                error_log('Error en la consulta SQL: ' . $e->getMessage());
                throw new Exception('Error al obtener los datos del reporte: ' . $e->getMessage());
            }
            
            // Configurar nombre del archivo con extensión .xls para forzar apertura en Excel
            $filename = 'reporte_clases_' . date('Y-m-d') . '.xls';
            
            // Crear un archivo temporal
            $tempFile = tempnam(sys_get_temp_dir(), 'xls_');
            $file = fopen($tempFile, 'w');
            
            // Escribir BOM para Excel
            fputs($file, "\xEF\xBB\xBF");
            
            // Escribir encabezados como HTML para forzar formato de tabla
            $html = "<table border='1'>\r\n";
            $html .= "<tr>\r\n";
            $html .= "<th>ID</th>\r\n";
            $html .= "<th>Título</th>\r\n";
            $html .= "<th>Fecha Inicio</th>\r\n";
            $html .= "<th>Fecha Fin</th>\r\n";
            $html .= "<th>Aula</th>\r\n";
            $html .= "<th>Estado</th>\r\n";
            $html .= "<th>Ficha</th>\r\n";
            $html .= "<th>Grupo</th>\r\n";
            $html .= "<th>Profesor</th>\r\n";
            $html .= "<th>Total Estudiantes</th>\r\n";
            $html .= "<th>Asistencias Confirmadas</th>\r\n";
            $html .= "<th>Porcentaje Asistencia</th>\r\n";
            $html .= "</tr>\r\n";
            
            // Escribir datos
            foreach ($clases as $clase) {
                $total = (int)$clase['total_asistencias'];
                $asistieron = (int)$clase['asistencias_confirmadas'];
                $porcentaje = $total > 0 ? round(($asistieron / $total) * 100, 2) : 0;
                
                $html .= "<tr>\r\n";
                $html .= "<td>" . htmlspecialchars($clase['id']) . "</td>\r\n";
                $html .= "<td>" . htmlspecialchars($clase['titulo']) . "</td>\r\n";
                $html .= "<td>" . htmlspecialchars($clase['fecha_inicio']) . "</td>\r\n";
                $html .= "<td>" . htmlspecialchars($clase['fecha_fin']) . "</td>\r\n";
                $html .= "<td>" . htmlspecialchars($clase['aula']) . "</td>\r\n";
                $html .= "<td>" . htmlspecialchars(ucfirst($clase['estado'])) . "</td>\r\n";
                $html .= "<td>" . htmlspecialchars($clase['ficha_numero']) . "</td>\r\n";
                $html .= "<td>" . htmlspecialchars($clase['ficha_nombre']) . "</td>\r\n";
                $html .= "<td>" . htmlspecialchars($clase['profesor_nombre']) . "</td>\r\n";
                $html .= "<td>" . $total . "</td>\r\n";
                $html .= "<td>" . $asistieron . "</td>\r\n";
                $html .= "<td>" . $porcentaje . '%' . "</td>\r\n";
                $html .= "</tr>\r\n";
            }
            
            $html .= "</table>";
            fwrite($file, $html);
            fclose($file);
            
            // Configurar cabeceras para descarga
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($tempFile));
            header('Cache-Control: max-age=0');
            
            // Enviar el archivo
            readfile($tempFile);
            
            // Eliminar el archivo temporal
            unlink($tempFile);
            exit;
            
        } catch (Exception $e) {
            error_log('Error al generar reporte CSV: ' . $e->getMessage());
            http_response_code(500);
            echo 'Error al generar el reporte';
        }
    }
    
    /**
     * Limpia el texto para CSV
     */
    private function limpiarTexto($texto) {
        // Eliminar saltos de línea y tabulaciones
        $texto = str_replace(["\r", "\n", "\t"], ' ', $texto);
        // Reemplazar comillas dobles por comillas simples
        $texto = str_replace('"', "'", $texto);
        // Eliminar espacios en blanco múltiples
        $texto = preg_replace('/\s+/', ' ', trim($texto));
        return $texto;
    }
    
    public function obtenerFichasDisponibles() {
        try {
            start_secure_session();
            
            if (!isset($_SESSION['usuario'])) {
                http_response_code(401);
                echo json_encode(['error' => 'No autorizado. Por favor inicie sesión.']);
                return;
            }
            // Permitir a roles 1 (Admin) y 2 (Profesor)
            $rol = (int)($_SESSION['usuario']['rol_id'] ?? 0);
            if (!in_array($rol, [1,2], true)) {
                http_response_code(403);
                echo json_encode(['error' => 'Acceso denegado']);
                return;
            }

            header('Content-Type: application/json');
            $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
            if ($rol === 1 && isset($_GET['profesor_id']) && $_GET['profesor_id'] !== '') {
                $profesor_id = $_GET['profesor_id'];
            }
            
            if (!$profesor_id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de profesor no encontrado en la sesión']);
                return;
            }
            // Traer fichas vinculadas al profesor (propias o asignadas)
            $sql = "
                SELECT DISTINCT f.id, f.numero AS codigo, f.nombre
                FROM fichas f
                INNER JOIN profesor_ficha pf ON f.id = pf.ficha_id
                WHERE pf.profesor_id = ?
                ORDER BY f.numero, f.nombre
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$profesor_id]);
            $fichas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            echo json_encode($fichas);
            
        } catch (Exception $e) {
            $errorMessage = 'Error en obtenerFichasDisponibles: ' . $e->getMessage();
            error_log($errorMessage);
            error_log('Trace: ' . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'error' => 'Error al cargar las fichas disponibles',
                'debug' => $errorMessage
            ]);
        }
    }
}