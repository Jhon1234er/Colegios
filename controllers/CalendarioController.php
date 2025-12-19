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

    /**
     * Ejecuta una consulta que puede referenciar p.usuario_id en el JOIN con usuarios.
     * Si el esquema no tiene esa columna (42S22), reintenta reemplazando por p.usuario.
     */
    private function queryWithFacilitadorUserJoin(string $sql, array $params): array {
        try {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                // Si no hay filas, intentar variantes comunes aunque no haya error de esquema
                if (!$rows) {
                    // Variante A: hf.profesor_id
                    $sqlA = str_replace('hf.facilitador_id', 'hf.profesor_id', $sql);
                    $sqlA = str_replace('p.id = hf.facilitador_id', 'p.id = hf.profesor_id', $sqlA);
                    try {
                        $stA = $this->pdo->prepare($sqlA);
                        $stA->execute($params);
                        $rowsA = $stA->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        if ($rowsA) { return $rowsA; }
                    } catch (\PDOException $eA) { /* ignorar, seguimos */ }
                    // Variante B: p.usuario (legacy)
                    $sqlB = str_replace('p.usuario_id', 'p.usuario', $sqlA);
                    try {
                        $stB = $this->pdo->prepare($sqlB);
                        $stB->execute($params);
                        $rowsB = $stB->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        if ($rowsB) { return $rowsB; }
                    } catch (\PDOException $eB) { /* ignorar */ }
                }
            } catch (\PDOException $e) {
                if ($e->getCode() !== '42S22') { throw $e; }
                // Fallback 1: unirse por ficha->colegio_id
                try {
                    $sql2 = str_replace('LEFT JOIN colegios c ON c.id = f.colegio_id', 'LEFT JOIN colegios c ON c.id = f.colegio_id', $sql);
                    $stmt = $this->pdo->prepare($sql2);
                    $stmt->execute($params);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $e2) {
                    if ($e2->getCode() !== '42S22') { throw $e2; }
                    // Fallback 2: unirse por ficha->colegio (legacy)
                    try {
                        $sql3 = str_replace('LEFT JOIN colegios c ON c.id = p.colegio_id', 'LEFT JOIN colegios c ON c.id = f.colegio', $sql);
                        $stmt = $this->pdo->prepare($sql3);
                        $stmt->execute($params);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (\PDOException $e3) {
                        if ($e3->getCode() !== '42S22') { throw $e3; }
                        // Fallback 3: quitar join a colegios y seleccionar NULLS
                        $sql4 = str_replace([
                            'LEFT JOIN colegios c ON c.id = p.colegio',
                            'c.id AS colegio_id,',
                            'c.nombre AS colegio_nombre'
                        ], [
                            '',
                            'NULL AS colegio_id,',
                            "'' AS colegio_nombre"
                        ], $sql);
                        $stmt = $this->pdo->prepare($sql4);
                        $stmt->execute($params);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    }
                }
            }
            return $rows;
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S22') { throw $e; }
            // 1) Fallback de columna: algunos esquemas usan hf.profesor_id en vez de hf.facilitador_id
            $sqlHF = str_replace([
                'COALESCE(hf.facilitador_id)',
                'hf.facilitador_id',
                'p.id = hf.facilitador_id'
            ], [
                'COALESCE(hf.profesor_id)',
                'hf.profesor_id',
                'p.id = hf.profesor_id'
            ], $sql);
            try {
                $stmtHF = $this->pdo->prepare($sqlHF);
                $stmtHF->execute($params);
                $rowsHF = $stmtHF->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if ($rowsHF) { return $rowsHF; }
            } catch (\PDOException $eHF) {
                if ($eHF->getCode() !== '42S22') { throw $eHF; }
            }

            // 2) Reintentar manteniendo estructura pero forzando selects/joins seguros
            $sqlFac = str_replace([
                'COALESCE(f.numero, f.id)'
            ], [
                'f.id'
            ], $sqlHF);
            try {
                $stmtFac = $this->pdo->prepare($sqlFac);
                $stmtFac->execute($params);
                return $stmtFac->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $eFac) {
                if ($eFac->getCode() !== '42S22') { throw $eFac; }
                // 3) Legacy: usuarios sin columna usuario_id en facilitadores
                $sqlLegacy = str_replace('p.usuario_id', 'p.usuario', $sqlFac);
                try {
                    $stmt2 = $this->pdo->prepare($sqlLegacy);
                    $stmt2->execute($params);
                    return $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $eFinal) {
                    // Último recurso: devolver vacío para no romper UI
                    return [];
                }
            }
        }
    }

    /**
     * Resuelve el facilitador_id del usuario en sesión.
     */
    private function resolverProfesorIdDesdeSesion(): ?int {
        try {
            $usuarioId = $_SESSION['usuario']['id'] ?? null;
            $facilitadorId = $_SESSION['usuario']['facilitador_id'] ?? null;
            // 1) Si viene en sesión, verificar que exista en facilitadores
            if ($facilitadorId) {
                try {
                    $stV = $this->pdo->prepare("SELECT id FROM facilitadores WHERE id = ? LIMIT 1");
                    $stV->execute([(int)$facilitadorId]);
                    $rowV = $stV->fetch(PDO::FETCH_ASSOC);
                    if ($rowV && isset($rowV['id'])) { return (int)$rowV['id']; }
                } catch (\PDOException $eV) { /* ignorar, intentaremos por usuario */ }
            }
            if (!$usuarioId) { return null; }
            // 2) Intentar por usuario_id (esquema actual)
            try {
                $st = $this->pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ? LIMIT 1");
                $st->execute([$usuarioId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['id'])) {
                    $_SESSION['usuario']['facilitador_id'] = (int)$row['id'];
                    return (int)$row['id'];
                }
            } catch (\PDOException $e) {
                if ($e->getCode() !== '42S22') { throw $e; }
            }
            // 3) Intentar por usuario (legacy)
            try {
                $st2 = $this->pdo->prepare("SELECT id FROM facilitadores WHERE usuario = ? LIMIT 1");
                $st2->execute([$usuarioId]);
                $row2 = $st2->fetch(PDO::FETCH_ASSOC);
                if ($row2 && isset($row2['id'])) {
                    $_SESSION['usuario']['facilitador_id'] = (int)$row2['id'];
                    return (int)$row2['id'];
                }
            } catch (\PDOException $e) {
                if ($e->getCode() !== '42S22') { throw $e; }
            }
            return null;
        } catch (\Throwable $t) { return null; }
    }

    /**
     * Registra cambios en historial_horarios (si la tabla existe). Silencioso ante errores.
     */
    private function registrarHistorial($horario_id, $usuario_id, $accion, $antes, $despues) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO historial_horarios (horario_id, usuario_id, accion, antes, despues, creado_en) VALUES (?, ?, ?, ?, ?, NOW())");
            $a = is_array($antes) ? json_encode($antes, JSON_UNESCAPED_UNICODE) : (is_string($antes) ? $antes : null);
            $d = is_array($despues) ? json_encode($despues, JSON_UNESCAPED_UNICODE) : (is_string($despues) ? $despues : null);
            $stmt->execute([$horario_id, $usuario_id, $accion, $a, $d]);
        } catch (Throwable $e) {
            // silencioso si la tabla no existe o hay cualquier fallo
        }
    }
    
    // Obtener horarios para el calendario
    public function obtenerHorarios() {
        // Evitar que warnings/notices rompan el JSON
        @ini_set('display_errors', '0');
        // Iniciar un buffer propio para capturar cualquier salida durante el handler
        if (function_exists('ob_start')) { @ob_start(); }
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
            // Aceptar alias legacy 'profesor_id'
            $profesorFiltro = $_GET['facilitador_id'] ?? ($_GET['profesor_id'] ?? null);
            $estadoFiltro = isset($_GET['estado']) && $_GET['estado'] !== '' ? $_GET['estado'] : null; // puede venir "programado", "en_curso", etc.
            $fichaFiltro  = isset($_GET['ficha']) && $_GET['ficha'] !== '' ? $_GET['ficha'] : null;   // acepta id o número (codigo)
            $esAdmin = (int)($_SESSION['usuario']['rol_id'] ?? 0) === 1;
            
            error_log("Parámetros recibidos - Start: $start, End: $end, View: $view");
            
            // Delegar por rol y contexto
            if ($esAdmin) {
                $evs = $this->obtenerHorariosAdmin($start, $end, $profesorFiltro, $estadoFiltro, $fichaFiltro);
                if (empty($evs) && $profesorFiltro && ctype_digit((string)$profesorFiltro)) {
                    // Fallback: usar el mismo método que ve el profesor para este facilitador
                    try { $evs = $this->obtenerHorariosProfesor($start, $end, (int)$profesorFiltro, $estadoFiltro, $fichaFiltro); } catch (\Throwable $e) { /* noop */ }
                }

                // Modo debug opcional para admins: devolver contexto + muestra
                if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
                    $out = [
                        'rol' => 'admin',
                        'start' => $start,
                        'end' => $end,
                        'profesorFiltro' => $profesorFiltro,
                        'estadoFiltro' => $estadoFiltro,
                        'fichaFiltro' => $fichaFiltro,
                        'total_eventos' => is_array($evs) ? count($evs) : 0,
                        'eventos_muestra' => is_array($evs) ? array_slice($evs, 0, 20) : [],
                    ];
                    echo json_encode($out, JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode($evs);
                }
                return;
            }

            // Para instructores: si llega facilitador_id por GET (desde la vista), úsalo directamente
            $facilitador_id = null;
            if ($profesorFiltro && ctype_digit((string)$profesorFiltro)) {
                $facilitador_id = (int)$profesorFiltro;
            } else {
                // Resolver coherente del facilitador para este usuario
                $facilitador_id = $this->resolverProfesorIdDesdeSesion();
            }
            if (!$facilitador_id) {
                // Sin facilitador resuelto: modo público (no lanzar 500)
                $evs = $this->obtenerHorariosPublico($start, $end, $estadoFiltro, $fichaFiltro);
                if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
                    $out = [
                        'rol' => 'publico',
                        'start' => $start,
                        'end' => $end,
                        'estadoFiltro' => $estadoFiltro,
                        'fichaFiltro' => $fichaFiltro,
                        'total_eventos' => is_array($evs) ? count($evs) : 0,
                        'eventos_muestra' => is_array($evs) ? array_slice($evs, 0, 20) : [],
                    ];
                    echo json_encode($out, JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode($evs);
                }
                return;
            }

            $evs = $this->obtenerHorariosProfesor($start, $end, $facilitador_id, $estadoFiltro, $fichaFiltro);
            if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
                $out = [
                    'rol' => 'profesor',
                    'start' => $start,
                    'end' => $end,
                    'facilitador_id' => $facilitador_id,
                    'estadoFiltro' => $estadoFiltro,
                    'fichaFiltro' => $fichaFiltro,
                    'total_eventos' => is_array($evs) ? count($evs) : 0,
                    'eventos_muestra' => is_array($evs) ? array_slice($evs, 0, 20) : [],
                ];
                echo json_encode($out, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode($evs);
            }
            return;
            
        } catch (Exception $e) {
            // No romper la UI: devolver lista vacía (HTTP 200) y loguear
            error_log('Calendario obtenerHorarios error: ' . $e->getMessage());
            if (function_exists('ob_get_length') && ob_get_length() !== false) { @ob_clean(); }
            echo json_encode([]);
        } finally {
            // Enviar el buffer con el JSON generado (si existe)
            if (function_exists('ob_get_length') && ob_get_length() !== false) { @ob_end_flush(); }
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

    private function safeDT($s): ?DateTime {
        try { if (!$s || $s === '0000-00-00 00:00:00') return null; return new DateTime($s); } catch (Throwable $e) { return null; }
    }

    // ==== Helpers de obtención por rol ====
    private function obtenerHorariosAdmin(?string $start, ?string $end, $profesorFiltro = null, $estadoFiltro = null, $fichaFiltro = null): array {
        $sql = "
            SELECT 
                hf.*,
                COALESCE(f.numero, f.id) as ficha_codigo,
                f.nombre as ficha_nombre,
                u.nombres as profesor_nombre,
                CONCAT(u.nombres, ' ', u.apellidos) as profesor_completo,
                p.id AS facilitador_id,
                hf.color as color_actual,
                CASE 
                    WHEN NOW() BETWEEN hf.fecha_inicio AND hf.fecha_fin THEN 'en_curso'
                    WHEN NOW() < hf.fecha_inicio THEN 'programado'
                    ELSE 'finalizado'
                END AS estado
            FROM horarios_fichas hf
            JOIN fichas f ON hf.ficha_id = f.id
            LEFT JOIN facilitadores p ON p.id = hf.facilitador_id
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE 1=1";

        $params = [];
        if ($profesorFiltro) {
            // Filtro mínimo robusto (evita tablas opcionales): por facilitador o creador
            $sql .= " AND (
                hf.facilitador_id = ?
                OR hf.creado_por IN (
                    SELECT COALESCE(p.usuario_id, p.usuario) FROM facilitadores p WHERE p.id = ?
                )
            )";
            array_push($params, $profesorFiltro, $profesorFiltro);
        }
        // Incluir eventos que se solapan con el rango solicitado
        if ($start && $end) { $sql .= " AND hf.fecha_inicio < ? AND hf.fecha_fin > ?"; $params[] = $end; $params[] = $start; }
        // EstadoFiltro omitido: estado se calcula dinámicamente por fecha_inicio/fin
        // Ficha: aceptar id numérico o nombre
        if ($fichaFiltro) {
            $isNum = ctype_digit((string)$fichaFiltro);
            if ($isNum) { $sql .= " AND hf.ficha_id = ?"; $params[] = (int)$fichaFiltro; }
            else { $sql .= " AND f.nombre = ?"; $params[] = $fichaFiltro; }
        }
        $sql .= " ORDER BY hf.fecha_inicio, u.nombres, u.apellidos";

        $horarios = $this->queryWithFacilitadorUserJoin($sql, $params);

        $eventos = [];
        foreach ($horarios as $horario) {
            $ahora = new DateTime();
            $fi = $this->safeDT($horario['fecha_inicio']);
            $ff = $this->safeDT($horario['fecha_fin']);
            if (!$fi || !$ff) { continue; }
            $fechaInicio = $fi;
            $fechaFin = $ff;
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
                    'facilitador_id' => $horario['facilitador_id'],
                    'aula' => $horario['aula'],
                    'estado' => $horario['estado'],
                    'tipo' => 'admin_vista',
                    'asistencia_habilitada' => $horario['asistencia_habilitada']
                ]
            ];
        }

        // Fallback FINAL para admins: si no hay eventos, consulta simple por facilitador_id
        // para asegurar que se muestren al menos las clases directas del instructor.
        if (empty($eventos)) {
            $sqlS = "
                SELECT 
                    hf.*,
                    COALESCE(f.numero, f.id) AS ficha_codigo,
                    f.nombre AS ficha_nombre,
                    CONCAT(u.nombres, ' ', u.apellidos) AS profesor_completo,
                    u.nombres AS profesor_nombre,
                    hf.color AS color_actual,
                    hf.facilitador_id AS facilitador_id,
                    CASE 
                        WHEN NOW() BETWEEN hf.fecha_inicio AND hf.fecha_fin THEN 'en_curso'
                        WHEN NOW() < hf.fecha_inicio THEN 'programado'
                        ELSE 'finalizado'
                    END AS estado
                FROM horarios_fichas hf
                JOIN fichas f ON hf.ficha_id = f.id
                LEFT JOIN facilitadores p ON p.id = hf.facilitador_id
                LEFT JOIN usuarios u ON p.usuario_id = u.id
                WHERE 1=1";
            $paramsS = [];
            if ($profesorFiltro) {
                $sqlS .= " AND hf.facilitador_id = ?";
                $paramsS[] = $profesorFiltro;
            }
            if ($start && $end) {
                $sqlS .= " AND hf.fecha_inicio < ? AND hf.fecha_fin > ?";
                $paramsS[] = $end; $paramsS[] = $start;
            }
            if ($fichaFiltro) {
                $isNum = ctype_digit((string)$fichaFiltro);
                if ($isNum) { $sqlS .= " AND hf.ficha_id = ?"; $paramsS[] = (int)$fichaFiltro; }
                else { $sqlS .= " AND f.nombre = ?"; $paramsS[] = $fichaFiltro; }
            }
            $sqlS .= " ORDER BY hf.fecha_inicio";

            $horariosS = $this->queryWithFacilitadorUserJoin($sqlS, $paramsS);
            foreach ($horariosS as $horario) {
                $ahora = new DateTime();
                $fi = $this->safeDT($horario['fecha_inicio']);
                $ff = $this->safeDT($horario['fecha_fin']);
                if (!$fi || !$ff) { continue; }
                $fechaInicio = $fi;
                $fechaFin = $ff;
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
                        'facilitador_id' => $horario['facilitador_id'],
                        'aula' => $horario['aula'],
                        'estado' => $horario['estado'],
                        'tipo' => 'admin_vista',
                        'asistencia_habilitada' => $horario['asistencia_habilitada']
                    ]
                ];
            }
        }

        return $eventos;
    }

    private function obtenerHorariosPublico(?string $start, ?string $end, $estadoFiltro = null, $fichaFiltro = null): array {
        $sql = "
            SELECT 
                hf.*,
                COALESCE(f.numero, f.id) as ficha_codigo,
                f.nombre as ficha_nombre,
                u.nombres as profesor_nombre,
                hf.color as color_actual,
                CASE 
                    WHEN NOW() BETWEEN hf.fecha_inicio AND hf.fecha_fin THEN 'en_curso'
                    WHEN NOW() < hf.fecha_inicio THEN 'programado'
                    ELSE 'finalizado'
                END AS estado
            FROM horarios_fichas hf
            JOIN fichas f ON hf.ficha_id = f.id
            LEFT JOIN facilitadores p ON p.id = hf.facilitador_id
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE 1=1";
        $params = [];
        if ($start && $end) { $sql .= " AND hf.fecha_inicio < ? AND hf.fecha_fin > ?"; $params[] = $end; $params[] = $start; }
        if ($fichaFiltro) {
            $isNum = ctype_digit((string)$fichaFiltro);
            if ($isNum) { $sql .= " AND hf.ficha_id = ?"; $params[] = (int)$fichaFiltro; }
            else { $sql .= " AND f.nombre = ?"; $params[] = $fichaFiltro; }
        }
        $sql .= " ORDER BY hf.fecha_inicio";

        $horarios = $this->queryWithFacilitadorUserJoin($sql, $params);

        $eventos = [];
        foreach ($horarios as $horario) {
            $ahora = new DateTime();
            $fi = $this->safeDT($horario['fecha_inicio']);
            $ff = $this->safeDT($horario['fecha_fin']);
            if (!$fi || !$ff) { continue; }
            $fechaEvento = $ff;
            $esPasado = $fechaEvento < $ahora;
            $color = $esPasado ? '#6c757d' : $horario['color_actual'];
            $claseEstado = $esPasado ? 'evento-pasado' : 'evento-' . ($horario['estado'] ?? 'programado');
            $fechaInicio = $fi;
            $fechaFin = $ff;
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

    private function obtenerHorariosProfesor(?string $start, ?string $end, int $facilitador_id, $estadoFiltro = null, $fichaFiltro = null): array {
        $eventos = [];

        // 1) Propios y compartidos
        $usuario_id_sesion = (int)($_SESSION['usuario']['id'] ?? 0);
        // Resolver usuario dueño de este facilitador (para filtrar creado_por correctamente)
        $usuario_facilitador = 0;
        try {
            $stUx = $this->pdo->prepare("SELECT usuario_id FROM facilitadores WHERE id = ?");
            $stUx->execute([$facilitador_id]);
            $usuario_facilitador = (int)($stUx->fetchColumn() ?: 0);
        } catch (\PDOException $eUx) {
            if ($eUx->getCode() === '42S22') {
                try {
                    $stUx2 = $this->pdo->prepare("SELECT usuario FROM facilitadores WHERE id = ?");
                    $stUx2->execute([$facilitador_id]);
                    $usuario_facilitador = (int)($stUx2->fetchColumn() ?: 0);
                } catch (\Throwable $_) { /* noop */ }
            } else { throw $eUx; }
        }
        $sql_propios = "
            SELECT 
                hf.*,
                COALESCE(f.numero, f.id) as ficha_codigo,
                f.nombre as ficha_nombre,
                u.nombres as profesor_nombre,
                hf.color as color_actual,
                CASE 
                    WHEN NOW() BETWEEN hf.fecha_inicio AND hf.fecha_fin THEN 'en_curso'
                    WHEN NOW() < hf.fecha_inicio THEN 'programado'
                    ELSE 'finalizado'
                END AS estado,
                CASE 
                    WHEN hf.creado_por = ? THEN 'propio'
                    ELSE 'compartido'
                END as tipo_horario
            FROM horarios_fichas hf
            JOIN fichas f ON hf.ficha_id = f.id
            LEFT JOIN facilitadores p ON hf.facilitador_id = p.id
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE (hf.facilitador_id = ? 
               OR hf.ficha_id IN (
                   SELECT ff.ficha_id FROM facilitador_ficha ff WHERE ff.facilitador_id = ?
               )
                OR hf.ficha_id IN (
                    SELECT fc.ficha_id FROM fichas_compartidas fc 
                    WHERE fc.profesor_compartido_id = ? AND (fc.estado_id = 2 OR UPPER(fc.estado) = 'ACEPTADA')
                )
                OR hf.creado_por IN (?, ?)
            )";

        // Placeholders en orden:
        // 1) CASE hf.creado_por = ?  -> usuario en sesión
        // 2) hf.facilitador_id = ?   -> facilitador actual
        // 3) ff.facilitador_id = ?   -> facilitador actual
        // 4) fc.profesor_compartido_id = ? -> usuario en sesión
        // 5-6) hf.creado_por IN (?,?) -> usuario en sesión y usuario del facilitador
        $params_propios = [
            $usuario_id_sesion,
            $facilitador_id,
            $facilitador_id,
            $usuario_id_sesion,
            $usuario_id_sesion,
            $usuario_facilitador ?: $usuario_id_sesion
        ];
        if ($start && $end) { $sql_propios .= " AND hf.fecha_inicio < ? AND hf.fecha_fin > ?"; $params_propios[] = $end; $params_propios[] = $start; }
        // EstadoFiltro omitido: se calcula dinámicamente
        if ($fichaFiltro) {
            $isNum = ctype_digit((string)$fichaFiltro);
            if ($isNum) { $sql_propios .= " AND hf.ficha_id = ?"; $params_propios[] = (int)$fichaFiltro; }
            else { $sql_propios .= " AND f.nombre = ?"; $params_propios[] = $fichaFiltro; }
        }
        $sql_propios .= " ORDER BY hf.fecha_inicio";
        try {
            $horarios_propios = $this->queryWithFacilitadorUserJoin($sql_propios, $params_propios);
        } catch (\PDOException $eMain) {
            // Si fallan tablas puente/compartidas inexistentes, degradar a consulta básica de propios
            if (!in_array($eMain->getCode(), ['42S02','42S22'], true)) { throw $eMain; }
            // Intento de fallback específico: cambiar fc.ficha_id -> fc.ficha y estado_id->texto
            try {
                $sql_propios_b = str_replace('SELECT fc.ficha_id FROM fichas_compartidas fc', 'SELECT fc.ficha FROM fichas_compartidas fc', $sql_propios);
                $sql_propios_b = str_replace('fc.estado_id = 1', "(fc.estado_id = 2 OR UPPER(fc.estado) = 'ACEPTADA')", $sql_propios_b);
                $horarios_propios = $this->queryWithFacilitadorUserJoin($sql_propios_b, $params_propios);
            } catch (\PDOException $eMain2) {
                if (!in_array($eMain2->getCode(), ['42S02','42S22'], true)) { throw $eMain2; }
                // Fallback final: consulta básica sin compartidos
                $sql_basic = "
                SELECT 
                    hf.*,
                    COALESCE(f.numero, f.id) as ficha_codigo,
                    f.nombre as ficha_nombre,
                    u.nombres as profesor_nombre,
                    hf.color as color_actual,
                    CASE 
                        WHEN NOW() BETWEEN hf.fecha_inicio AND hf.fecha_fin THEN 'en_curso'
                        WHEN NOW() < hf.fecha_inicio THEN 'programado'
                        ELSE 'finalizado'
                    END AS estado,
                    CASE 
                        WHEN hf.creado_por = ? THEN 'propio'
                        ELSE 'compartido'
                    END as tipo_horario
                FROM horarios_fichas hf
                JOIN fichas f ON hf.ficha_id = f.id
                LEFT JOIN facilitadores p ON hf.facilitador_id = p.id
                LEFT JOIN usuarios u ON p.usuario_id = u.id
                WHERE (hf.facilitador_id = ? OR hf.creado_por IN (?,?))
                ";
                // Orden de placeholders: (CASE creado_por=?), (hf.facilitador_id=?), (hf.creado_por IN (?,?))
                $params_basic = [
                    $usuario_id_sesion,
                    $facilitador_id,
                    $usuario_id_sesion,
                    $usuario_facilitador ?: $usuario_id_sesion
                ];
                if ($start && $end) { $sql_basic .= " AND hf.fecha_inicio < ? AND hf.fecha_fin > ?"; $params_basic[] = $end; $params_basic[] = $start; }
                if ($fichaFiltro) {
                    $isNum = ctype_digit((string)$fichaFiltro);
                    if ($isNum) { $sql_basic .= " AND hf.ficha_id = ?"; $params_basic[] = (int)$fichaFiltro; }
                    else { $sql_basic .= " AND f.nombre = ?"; $params_basic[] = $fichaFiltro; }
                }
                $sql_basic .= " ORDER BY hf.fecha_inicio";
                $horarios_propios = $this->queryWithFacilitadorUserJoin($sql_basic, $params_basic);
            }
        }

        // (Fallback adicional): si no se obtuvieron eventos directos, intentar por relación de ficha
        if (empty($horarios_propios)) {
            try {
                $sql_pf = "
                    SELECT 
                        hf.*,
                        COALESCE(f.numero, f.id) as ficha_codigo,
                        f.nombre as ficha_nombre,
                        u.nombres as profesor_nombre,
                        hf.color as color_actual,
                        CASE 
                            WHEN NOW() BETWEEN hf.fecha_inicio AND hf.fecha_fin THEN 'en_curso'
                            WHEN NOW() < hf.fecha_inicio THEN 'programado'
                            ELSE 'finalizado'
                        END AS estado,
                        'propio' as tipo_horario
                    FROM horarios_fichas hf
                    JOIN fichas f ON hf.ficha_id = f.id
                    LEFT JOIN facilitador_ficha pf ON pf.ficha_id = f.id
                    LEFT JOIN facilitadores p ON p.id = pf.facilitador_id
                    LEFT JOIN usuarios u ON u.id = p.usuario
                    WHERE pf.facilitador_id = ?
                ";
                $params_pf = [$facilitador_id];
                if ($start && $end) { $sql_pf .= " AND hf.fecha_inicio < ? AND hf.fecha_fin > ?"; $params_pf[] = $end; $params_pf[] = $start; }
                if ($fichaFiltro) {
                    $isNum = ctype_digit((string)$fichaFiltro);
                    if ($isNum) { $sql_pf .= " AND hf.ficha_id = ?"; $params_pf[] = (int)$fichaFiltro; }
                    else { $sql_pf .= " AND f.nombre = ?"; $params_pf[] = $fichaFiltro; }
                }
                $sql_pf .= " ORDER BY hf.fecha_inicio";
                $horarios_propios = $this->queryWithFacilitadorUserJoin($sql_pf, $params_pf);
            } catch (\Throwable $ePF) {
                // Intentar con tabla legacy profesor_ficha
                try {
                    $sql_pf2 = str_replace('facilitador_ficha pf', 'profesor_ficha pf', str_replace('pf.facilitador_id', 'pf.profesor_id', $sql_pf));
                    $params_pf2 = $params_pf;
                    $horarios_propios = $this->queryWithFacilitadorUserJoin($sql_pf2, $params_pf2);
                } catch (\Throwable $ePF2) { /* mantener vacío */ }
            }
        }

        foreach ($horarios_propios as $horario) {
            $fi = $this->safeDT($horario['fecha_inicio']);
            $ff = $this->safeDT($horario['fecha_fin']);
            if (!$fi || !$ff) { continue; }
            $fechaInicio = $fi;
            $fechaFin = $ff;
            $eventos[] = [
                'id' => $horario['id'],
                'title' => $horario['titulo'],
                'start' => $fechaInicio->format('Y-m-d\TH:i:s'),
                'end' => $fechaFin->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $horario['color_actual'],
                'borderColor' => $horario['color_actual'],
                'className' => 'evento-' . ($horario['estado'] ?? 'programado'),
                'extendedProps' => [
                    'ficha_id' => $horario['ficha_id'],
                    'ficha_codigo' => $horario['ficha_codigo'],
                    'ficha_nombre' => $horario['ficha_nombre'],
                    'profesor_nombre' => $horario['profesor_nombre'],
                    'aula' => $horario['aula'],
                    'estado' => ($horario['estado'] ?? 'programado'),
                    'tipo' => $horario['tipo_horario'],
                    'asistencia_habilitada' => $horario['asistencia_habilitada']
                ]
            ];
        }

        // Fallback FINAL: si sigue sin eventos, hacer una consulta mínima DIRECTA sobre horarios_fichas
        // sin tablas puente ni sincronización, solo por facilitador_id/profesor_id.
        if (empty($eventos)) {
            $rowsSimple = [];
            try {
                $sql_simple = "
                    SELECT 
                        hf.id,
                        COALESCE(hf.titulo,'Clase') AS titulo,
                        hf.fecha_inicio,
                        hf.fecha_fin,
                        hf.aula,
                        COALESCE(hf.color, '#3b82f6') AS color_actual,
                        hf.ficha_id,
                        COALESCE(f.numero, hf.ficha_id) AS ficha_codigo,
                        f.nombre AS ficha_nombre
                    FROM horarios_fichas hf
                    LEFT JOIN fichas f ON hf.ficha_id = f.id
                    WHERE hf.facilitador_id = ?
                ";
                $params_simple = [$facilitador_id];
                if ($start && $end) {
                    $sql_simple .= " AND hf.fecha_inicio < ? AND hf.fecha_fin > ?";
                    $params_simple[] = $end; $params_simple[] = $start;
                }
                if ($fichaFiltro) {
                    $isNum = ctype_digit((string)$fichaFiltro);
                    if ($isNum) { $sql_simple .= " AND hf.ficha_id = ?"; $params_simple[] = (int)$fichaFiltro; }
                    else {
                        // Sin JOIN a fichas: si el filtro no es numérico, no podemos aplicarlo de forma segura aquí
                    }
                }
                $sql_simple .= " ORDER BY hf.fecha_inicio";
                $stmtS = $this->pdo->prepare($sql_simple);
                $stmtS->execute($params_simple);
                $rowsSimple = $stmtS->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Exception $eS) {
                // Si falla por columna desconocida (facilitador_id), intentar con profesor_id
                if ($eS->getCode() === '42S22' || stripos($eS->getMessage(), 'Unknown column') !== false) {
                    try {
                        $sql_simple2 = str_replace('hf.facilitador_id', 'hf.profesor_id', $sql_simple);
                        $stmtS2 = $this->pdo->prepare($sql_simple2);
                        $stmtS2->execute($params_simple);
                        $rowsSimple = $stmtS2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (Exception $eS2) {
                        $rowsSimple = [];
                    }
                } else {
                    $rowsSimple = [];
                }
            }

            foreach ($rowsSimple as $row) {
                $fi = $this->safeDT($row['fecha_inicio'] ?? null);
                $ff = $this->safeDT($row['fecha_fin'] ?? null);
                if (!$fi || !$ff) { continue; }
                $fechaInicio = $fi;
                $fechaFin = $ff;
                // Calcular estado dinámicamente según la fecha si no existe columna
                $ahora = new DateTime();
                $estado = 'programado';
                if ($ahora > $fechaFin) $estado = 'finalizado';
                elseif ($ahora >= $fechaInicio && $ahora <= $fechaFin) $estado = 'en_curso';

                $color = $row['color_actual'] ?? '#3b82f6';
                $profNombre = '';
                if (!empty($_SESSION['usuario']['nombres']) || !empty($_SESSION['usuario']['apellidos'])) {
                    $pn = trim((string)($_SESSION['usuario']['nombres'] ?? ''));
                    $pa = trim((string)($_SESSION['usuario']['apellidos'] ?? ''));
                    $profNombre = trim($pn . ' ' . $pa);
                }
                $fichaId = $row['ficha_id'] ?? null;
                $fichaCodigo = $row['ficha_codigo'] ?? $fichaId;
                $fichaNombre = $row['ficha_nombre'] ?? '';
                $eventos[] = [
                    'id' => $row['id'],
                    'title' => $row['titulo'],
                    'start' => $fechaInicio->format('Y-m-d\\TH:i:s'),
                    'end' => $fechaFin->format('Y-m-d\\TH:i:s'),
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'className' => 'evento-' . $estado,
                    'extendedProps' => [
                        'ficha_id' => $fichaId,
                        'ficha_codigo' => $fichaCodigo,
                        'ficha_nombre' => $fichaNombre,
                        'profesor_nombre' => $profNombre,
                        'aula' => $row['aula'] ?? '',
                        'estado' => $estado,
                        'tipo' => 'propio_simple',
                        'asistencia_habilitada' => null,
                    ]
                ];
            }
        }

        // 2) Sincronizados (opcional: si la tabla no existe, continuar sin error)
        try {
            $sql_sincronizados = "
                SELECT 
                    hf.*,
                    COALESCE(f.numero, f.id) as ficha_codigo,
                    f.nombre as ficha_nombre,
                    u.nombres as profesor_nombre,
                    hf.color as color_actual,
                    cs.permisos as permisos
                FROM horarios_fichas hf
                JOIN fichas f ON hf.ficha_id = f.id
                JOIN facilitadores p ON hf.facilitador_id = p.id
                JOIN usuarios u ON p.usuario_id = u.id
                JOIN calendario_sincronizacion cs ON (
                    (cs.profesor_propietario_id = ? AND cs.profesor_sincronizado_id = hf.facilitador_id) OR
                    (cs.profesor_sincronizado_id = ? AND cs.profesor_propietario_id = hf.facilitador_id)
                )
                WHERE cs.estado = 'aceptado'
                  AND hf.facilitador_id != ?
                ORDER BY hf.fecha_inicio";

            $stmt = $this->pdo->prepare($sql_sincronizados);
            $stmt->execute([$facilitador_id, $facilitador_id, $facilitador_id]);
            $horarios_sincronizados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($horarios_sincronizados as $horario) {
                $fi = $this->safeDT($horario['fecha_inicio']);
                $ff = $this->safeDT($horario['fecha_fin']);
                if (!$fi || !$ff) { continue; }
                $fechaInicio = $fi;
                $fechaFin = $ff;
                $eventos[] = [
                    'id' => 'sync_' . $horario['id'],
                    'title' => $horario['titulo'] . ' (' . $horario['profesor_nombre'] . ')',
                    'start' => $fechaInicio->format('Y-m-d\TH:i:s'),
                    'end' => $fechaFin->format('Y-m-d\TH:i:s'),
                    'backgroundColor' => $horario['color_actual'],
                    'borderColor' => $horario['color_actual'],
                    'className' => 'evento-sincronizado evento-' . ($horario['estado'] ?? 'programado'),
                    'editable' => ($horario['permisos'] ?? '') === 'lectura_escritura',
                    'extendedProps' => [
                        'horario_original_id' => $horario['id'],
                        'ficha_id' => $horario['ficha_id'],
                        'ficha_codigo' => $horario['ficha_codigo'],
                        'ficha_nombre' => $horario['ficha_nombre'],
                        'profesor_nombre' => $horario['profesor_nombre'],
                        'aula' => $horario['aula'],
                        'estado' => ($horario['estado'] ?? 'programado'),
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

    // Duplicar las clases seleccionadas de la semana actual a la siguiente semana
    public function duplicarSemana() {
        start_secure_session();
        header('Content-Type: application/json');
        try {
            require_role([1,2]);

            $rawBody = file_get_contents('php://input') ?: '';
            $body = json_decode($rawBody, true);
            if (!is_array($body)) {
                throw new Exception('Petición inválida');
            }

            $eventIds = $body['event_ids'] ?? null;
            if (!is_array($eventIds) || empty($eventIds)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No se seleccionaron clases para duplicar']);
                return;
            }

            // Normalizar y desduplicar IDs
            $ids = [];
            foreach ($eventIds as $v) {
                $id = (int)$v;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
            if (empty($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'IDs de clases no válidos']);
                return;
            }

            $usuario = $_SESSION['usuario'] ?? [];
            $facilitadorSesion = (int)($usuario['facilitador_id'] ?? 0);
            $rolSesion = (int)($usuario['rol_id'] ?? 0);

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_values($ids);

            $sqlBase = "SELECT * FROM horarios_fichas WHERE id IN ($placeholders)";
            // Para instructores (rol 2) limitar a sus propias clases por seguridad
            if ($facilitadorSesion && $rolSesion === 2) {
                $sqlBase .= " AND facilitador_id = ?";
                $params[] = $facilitadorSesion;
            }

            $stmt = $this->pdo->prepare($sqlBase);
            $stmt->execute($params);
            $baseEventos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (empty($baseEventos)) {
                echo json_encode(['success' => false, 'error' => 'No se encontraron las clases seleccionadas']);
                return;
            }

            // Calcular semana actual (lunes a lunes) según fecha del servidor
            $weekStart = new DateTime('today');
            $weekStart->setTime(0, 0, 0);
            $dow = (int)$weekStart->format('N'); // 1=lunes..7=domingo
            if ($dow > 1) {
                $weekStart->modify('-' . ($dow - 1) . ' days');
            }
            $weekEnd = clone $weekStart;
            $weekEnd->modify('+7 days');

            $insertados = 0;

            foreach ($baseEventos as $ev) {
                if (empty($ev['fecha_inicio']) || empty($ev['fecha_fin'])) {
                    continue;
                }

                $start = new DateTime($ev['fecha_inicio']);
                $end   = new DateTime($ev['fecha_fin']);

                // Seguridad extra: solo duplicar si la clase pertenece a la semana actual
                if ($start < $weekStart || $start >= $weekEnd) {
                    continue;
                }

                $newStart = clone $start;
                $newEnd   = clone $end;
                $newStart->modify('+7 days');
                $newEnd->modify('+7 days');

                $fichaId = (int)($ev['ficha_id'] ?? 0);
                $facId   = (int)($ev['facilitador_id'] ?? 0);
                if ($fichaId <= 0 || $facId <= 0) {
                    continue;
                }

                // Evitar duplicados exactos en misma ficha/facilitador/fecha
                $sqlChk = "SELECT COUNT(*) FROM horarios_fichas 
                           WHERE ficha_id = ? 
                             AND facilitador_id = ?
                             AND fecha_inicio = ? 
                             AND fecha_fin = ?";
                $stmtChk = $this->pdo->prepare($sqlChk);
                $stmtChk->execute([
                    $fichaId,
                    $facId,
                    $newStart->format('Y-m-d H:i:s'),
                    $newEnd->format('Y-m-d H:i:s'),
                ]);
                if ((int)$stmtChk->fetchColumn() > 0) {
                    continue;
                }

                $stmtIns = $this->pdo->prepare(
                    "INSERT INTO horarios_fichas (facilitador_id, ficha_id, titulo, fecha_inicio, fecha_fin, aula, color, creado_por)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $ok = $stmtIns->execute([
                    $facId,
                    $fichaId,
                    $ev['titulo'] ?? 'Clase',
                    $newStart->format('Y-m-d H:i:s'),
                    $newEnd->format('Y-m-d H:i:s'),
                    $ev['aula'] ?? null,
                    $ev['color'] ?? '#3b82f6',
                    $ev['creado_por'] ?? ($usuario['id'] ?? null),
                ]);
                if ($ok) {
                    $insertados++;
                }
            }

            echo json_encode(['success' => true, 'insertados' => $insertados]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
            
            require_role([1,2]);
            $facilitador_id = $_POST['facilitador_id'] ?? ($_SESSION['usuario']['facilitador_id'] ?? null);
            
            $__rolActual = $_SESSION['usuario']['rol_id'] ?? null;
            if (!$facilitador_id && (int)$__rolActual === 2) {
                http_response_code(400);
                echo json_encode(['error' => 'ID de facilitador no encontrado']);
                return;
            }
            
            // Obtener datos del POST (FormData)
            $ficha_id = $_POST['ficha_id'] ?? null;
            $titulo = $_POST['titulo'] ?? null;
            $fecha_inicio = $_POST['fecha_inicio'] ?? null;
            $fecha_fin = $_POST['fecha_fin'] ?? null;
            $aula = $_POST['aula'] ?? null;
            $color = $_POST['color'] ?? '#007bff';
            // estado visual se calcula dinámicamente según fecha
            if (!$facilitador_id && $ficha_id) {
                try {
                    $stFF = $this->pdo->prepare("SELECT facilitador_id FROM facilitador_ficha WHERE ficha_id = ? LIMIT 1");
                    $stFF->execute([$ficha_id]);
                    $facilitador_id = (int)($stFF->fetchColumn() ?: 0);
                } catch (\PDOException $eFF) {
                    if ($eFF->getCode() !== '42S02' && $eFF->getCode() !== '42S22') { throw $eFF; }
                }
                if (!$facilitador_id) {
                    try {
                        $stF = $this->pdo->prepare("SELECT facilitador_id FROM fichas WHERE id = ?");
                        $stF->execute([$ficha_id]);
                        $facilitador_id = (int)($stF->fetchColumn() ?: 0);
                    } catch (\PDOException $eFc) {
                        if ($eFc->getCode() !== '42S22') { throw $eFc; }
                    }
                }
                if (!$facilitador_id) {
                    try {
                        $stFC = $this->pdo->prepare("SELECT facilitador_compartido FROM fichas_compartidas WHERE ficha_id = ? AND (estado = 'Aceptada' OR estado_id = 2) ORDER BY creado_en DESC LIMIT 1");
                        $stFC->execute([$ficha_id]);
                        $facilitador_id = (int)($stFC->fetchColumn() ?: 0);
                    } catch (\PDOException $eFC1) {
                        if ($eFC1->getCode() !== '42S22') { throw $eFC1; }
                        try {
                            $stFC = $this->pdo->prepare("SELECT facilitador_compartido FROM fichas_compartidas WHERE ficha = ? AND (estado = 'Aceptada' OR estado_id = 2) ORDER BY creado_en DESC LIMIT 1");
                            $stFC->execute([$ficha_id]);
                            $facilitador_id = (int)($stFC->fetchColumn() ?: 0);
                        } catch (\PDOException $eFC2) { if ($eFC2->getCode() !== '42S22') { throw $eFC2; } }
                    }
                    if (!$facilitador_id) {
                        try {
                            $stFCu = $this->pdo->prepare("SELECT profesor_compartido_id FROM fichas_compartidas WHERE ficha_id = ? AND (estado = 'Aceptada' OR estado_id = 2) ORDER BY creado_en DESC LIMIT 1");
                            $stFCu->execute([$ficha_id]);
                            $uid = (int)($stFCu->fetchColumn() ?: 0);
                            if ($uid) {
                                try {
                                    $stMap = $this->pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ?");
                                    $stMap->execute([$uid]);
                                    $facilitador_id = (int)($stMap->fetchColumn() ?: 0);
                                } catch (\PDOException $eMp) {
                                    if ($eMp->getCode() !== '42S22') { throw $eMp; }
                                    $stMap = $this->pdo->prepare("SELECT id FROM facilitadores WHERE usuario = ?");
                                    $stMap->execute([$uid]);
                                    $facilitador_id = (int)($stMap->fetchColumn() ?: 0);
                                }
                            }
                        } catch (\PDOException $eFC3) { if ($eFC3->getCode() !== '42S22') { throw $eFC3; } }
                    }
                }
            }
            
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
            if (!$facilitador_id) {
                http_response_code(400);
                echo json_encode(['error' => 'No se pudo determinar el facilitador para el bloque']);
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

            // Resolver creado_por como usuarios.id (no id de facilitadores) para cumplir FK fk_hf_usuario
            $creadoPorUserId = null;
            try {
                $stU1 = $this->pdo->prepare("SELECT usuario_id FROM facilitadores WHERE id = ?");
                $stU1->execute([(int)$facilitador_id]);
                $uid = (int)($stU1->fetchColumn() ?: 0);
                if ($uid > 0) { $creadoPorUserId = $uid; }
            } catch (\PDOException $eU) {
                if ($eU->getCode() === '42S22') {
                    try {
                        $stU2 = $this->pdo->prepare("SELECT usuario FROM facilitadores WHERE id = ?");
                        $stU2->execute([(int)$facilitador_id]);
                        $uid2 = (int)($stU2->fetchColumn() ?: 0);
                        if ($uid2 > 0) { $creadoPorUserId = $uid2; }
                    } catch (\Throwable $_) { /* noop */ }
                } else { throw $eU; }
            }
            if (!$creadoPorUserId && !empty($_SESSION['usuario']['id'])) {
                $creadoPorUserId = (int)$_SESSION['usuario']['id'];
            }

            // Insertar en la base de datos (creado_por puede ser NULL si no se resuelve)
            $sql = "INSERT INTO horarios_fichas 
                    (facilitador_id, ficha_id, titulo, fecha_inicio, fecha_fin, aula, color, creado_por) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $facilitador_id,
                $ficha_id,
                $titulo,
                $fecha_inicio,
                $fecha_fin,
                $aula,
                $color,
                $creadoPorUserId
            ]);
            
            if ($result) {
                $usuarioDestinoId = 0;
                try {
                    $stU = $this->pdo->prepare("SELECT usuario_id FROM facilitadores WHERE id = ?");
                    $stU->execute([$facilitador_id]);
                    $usuarioDestinoId = (int)($stU->fetchColumn() ?: 0);
                } catch (\PDOException $eU1) {
                    if ($eU1->getCode() !== '42S22') { throw $eU1; }
                    $stU = $this->pdo->prepare("SELECT usuario FROM facilitadores WHERE id = ?");
                    $stU->execute([$facilitador_id]);
                    $usuarioDestinoId = (int)($stU->fetchColumn() ?: 0);
                }
                if ($usuarioDestinoId > 0) {
                    $tituloNoti = 'Nuevo bloque programado';
                    $nombreFicha = (string)($ficha['nombre'] ?? '');
                    // Formato compacto: si es el mismo día => "d-m-Y de hh:mm a hh:mm"
                    // en caso contrario => "del d-m-Y HH:mm al d-m-Y HH:mm"
                    try {
                        $dtI = new \DateTime($fecha_inicio);
                        $dtF = new \DateTime($fecha_fin);
                        if ($dtI && $dtF && $dtI->format('Y-m-d') === $dtF->format('Y-m-d')) {
                            $rango = $dtI->format('d-m-Y') . ' de ' . $dtI->format('h:i') . ' a ' . $dtF->format('h:i');
                        } else {
                            $rango = 'del ' . $dtI->format('d-m-Y H:i') . ' al ' . $dtF->format('d-m-Y H:i');
                        }
                    } catch (\Throwable $t) {
                        $rango = 'de ' . $fecha_inicio . ' a ' . $fecha_fin;
                    }
                    $msg = 'Se programó un nuevo bloque para la ficha ' . $nombreFicha . ' ' . $rango . ' en ' . $aula . '.';
                    try {
                        try {
                            $stN = $this->pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                            $stN->execute([$usuarioDestinoId, $tituloNoti, $msg]);
                        } catch (\PDOException $eN1) {
                            if ($eN1->getCode() !== '42S22') { throw $eN1; }
                            try {
                                $stN = $this->pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, leido, creado_en) VALUES (?, ?, ?, 0, NOW())");
                                $stN->execute([$usuarioDestinoId, $tituloNoti, $msg]);
                            } catch (\PDOException $eN2) {
                                if ($eN2->getCode() !== '42S22') { throw $eN2; }
                                try {
                                    $stN = $this->pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, estado, creado_en) VALUES (?, ?, ?, 'no_leida', NOW())");
                                    $stN->execute([$usuarioDestinoId, $tituloNoti, $msg]);
                                } catch (\PDOException $eN3) {
                                    if ($eN3->getCode() !== '42S22') { throw $eN3; }
                                    $stN = $this->pdo->prepare("INSERT INTO notificaciones (usuario, titulo, mensaje, estado, creado_en) VALUES (?, ?, ?, 'no_leida', NOW())");
                                    $stN->execute([$usuarioDestinoId, $tituloNoti, $msg]);
                                }
                            }
                        }
                    } catch (\Throwable $ign) {}
                }
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
        $facilitador_id = $_SESSION['usuario']['facilitador_id'] ?? null;
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $horario_id = $data['id'];
            
            // Verificar permisos solo si hay facilitador_id
            if ($facilitador_id && !$this->tienePermisosHorario($horario_id, $facilitador_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permisos para modificar este horario']);
                return;
            }
            
            // Obtener datos anteriores
            $stmt = $this->pdo->prepare("SELECT * FROM horarios_fichas WHERE id = ?");
            $stmt->execute([$horario_id]);
            $datos_anteriores = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar conflictos si cambió fecha/hora y tenemos facilitador_id para contrastar
            if (($data['fecha_inicio'] !== $datos_anteriores['fecha_inicio'] || 
                 $data['fecha_fin'] !== $datos_anteriores['fecha_fin']) && $facilitador_id) {
                $conflictos = $this->verificarConflictos($data, $facilitador_id, $horario_id);
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
            $this->registrarHistorial($horario_id, $facilitador_id, 'modificar', $datos_anteriores, $data);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'No se pudo actualizar el horario', 'detalle' => $e->getMessage()]);
        }
    }
    
    // Eliminar horario
    public function eliminarHorario() {
        start_secure_session();
        require_role(2);
        $facilitador_id = $_SESSION['usuario']['facilitador_id'] ?? null;
        
        try {
            $horario_id = $_POST['horario_id'] ?? $_GET['horario_id'];
            
            if (!$this->tienePermisosHorario($horario_id, $facilitador_id)) {
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
            $this->registrarHistorial($horario_id, $facilitador_id, 'eliminar', $datos_anteriores, null);
            
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    // Cambiar estado de horario (responde JSON limpio y mapea al ENUM de BD)
    public function cambiarEstado() {
        start_secure_session();
        require_role(2);
        header('Content-Type: application/json; charset=utf-8');
        try {
            $facilitador_id = $_SESSION['usuario']['facilitador_id'] ?? null;
            $data = json_decode(file_get_contents('php://input'), true);
            $horario_id = (int)($data['horario_id'] ?? 0);
            $nuevo_estado = (string)($data['estado'] ?? '');

            if (!$horario_id) { http_response_code(400); echo json_encode(['error'=>'horario_id requerido']); return; }
            if (!$this->tienePermisosHorario($horario_id, $facilitador_id)) {
                http_response_code(403); echo json_encode(['error' => 'Sin permisos para modificar este horario']); return;
            }

            // Mapear a ENUM de BD: 'Programado','En curso','Finalizado','Suspendido'
            $map = [
                'programado' => 'Programado', 'Programado' => 'Programado',
                'en_curso'   => 'En curso',   'En curso'   => 'En curso',
                'finalizado' => 'Finalizado', 'Finalizado' => 'Finalizado',
                'suspendido' => 'Suspendido', 'Suspendido' => 'Suspendido',
                'cancelado'  => 'Suspendido', 'Cancelado'  => 'Suspendido',
            ];
            if (isset($map[$nuevo_estado])) $nuevo_estado = $map[$nuevo_estado];

            $stmt = $this->pdo->prepare("UPDATE horarios_fichas SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $horario_id]);

            // Ejemplo: habilitar asistencia si entra en curso
            if ($nuevo_estado === 'En curso') {
                $this->pdo->prepare("UPDATE horarios_fichas SET asistencia_habilitada = TRUE WHERE id = ?")
                          ->execute([$horario_id]);
            }

            echo json_encode(['success' => true, 'estado' => $nuevo_estado], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    // Verificar conflictos de horarios
    private function verificarConflictos($data, $facilitador_id, $excluir_id = null) {
        $sql = "
            SELECT hf.*, COALESCE(f.numero, f.id) as ficha_codigo
            FROM horarios_fichas hf
            JOIN fichas f ON hf.ficha_id = f.id
            WHERE hf.facilitador_id = ? 
              AND (
                  (? BETWEEN hf.fecha_inicio AND hf.fecha_fin) OR
                  (? BETWEEN hf.fecha_inicio AND hf.fecha_fin) OR
                  (hf.fecha_inicio BETWEEN ? AND ?) OR
                  (hf.fecha_fin BETWEEN ? AND ?)
              )
        ";
        
        $params = [
            $facilitador_id,
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
    private function tienePermisosHorario($horario_id, $facilitador_id) {
        // Intento 1: esquema nuevo (ficha_id + estado_id)
        $sql1 = "
            SELECT COUNT(*) as tiene_permisos
            FROM horarios_fichas hf
            WHERE hf.id = ? AND (
                hf.creado_por = ? OR
                hf.ficha_id IN (
                    SELECT fc.ficha_id 
                    FROM fichas_compartidas fc 
                    WHERE fc.profesor_compartido_id = ? 
                      AND fc.estado_id = 2
                )
            )
        ";
        try {
            $stmt = $this->pdo->prepare($sql1);
            $stmt->execute([$horario_id, $facilitador_id, $facilitador_id]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['tiene_permisos' => 0];
            return ((int)$resultado['tiene_permisos']) > 0;
        } catch (\PDOException $e1) {
            if ($e1->getCode() !== '42S22') { throw $e1; }
        }

        // Intento 2: columna 'ficha' en lugar de 'ficha_id' (manteniendo estado_id)
        $sql2 = "
            SELECT COUNT(*) as tiene_permisos
            FROM horarios_fichas hf
            WHERE hf.id = ? AND (
                hf.creado_por = ? OR
                hf.ficha_id IN (
                    SELECT fc.ficha 
                    FROM fichas_compartidas fc 
                    WHERE fc.profesor_compartido_id = ? 
                      AND fc.estado_id = 2
                )
            )
        ";
        try {
            $stmt = $this->pdo->prepare($sql2);
            $stmt->execute([$horario_id, $facilitador_id, $facilitador_id]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['tiene_permisos' => 0];
            return ((int)$resultado['tiene_permisos']) > 0;
        } catch (\PDOException $e2) {
            if ($e2->getCode() !== '42S22') { throw $e2; }
        }

        // Intento 3 (legacy total): 'ficha' + estado textual (case-insensitive)
        $sql3 = "
            SELECT COUNT(*) as tiene_permisos
            FROM horarios_fichas hf
            WHERE hf.id = ? AND (
                hf.creado_por = ? OR
                hf.ficha_id IN (
                    SELECT fc.ficha 
                    FROM fichas_compartidas fc 
                    WHERE fc.profesor_compartido_id = ? 
                      AND UPPER(fc.estado) = 'ACEPTADA'
                )
            )
        ";
        $stmt = $this->pdo->prepare($sql3);
        $stmt->execute([$horario_id, $facilitador_id, $facilitador_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['tiene_permisos' => 0];
        return ((int)$resultado['tiene_permisos']) > 0;
    }
    
    public function exportarReporteCSV() {
        try {
            start_secure_session();
            if (!isset($_SESSION['usuario'])) { http_response_code(401); echo 'No autorizado'; return; }

            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
            $fechaFin    = $_GET['fecha_fin'] ?? date('Y-m-t');
            // Rango completo del día e inclusión por solape
            $iniDT = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fechaInicio) ? ($fechaInicio . ' 00:00:00') : $fechaInicio;
            $finDT = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fechaFin)    ? ($fechaFin    . ' 23:59:59') : $fechaFin;
            $facilitadorId  = null;
            if (($_SESSION['usuario']['rol_id'] ?? 0) == 1) {
                if (isset($_GET['facilitador_id']) && $_GET['facilitador_id'] !== '') {
                    $facilitadorId = $_GET['facilitador_id'];
                } elseif (isset($_GET['profesor_id']) && $_GET['profesor_id'] !== '') { // alias legacy
                    $facilitadorId = $_GET['profesor_id'];
                }
            } else if (isset($_SESSION['usuario']['facilitador_id'])) {
                $facilitadorId = $_SESSION['usuario']['facilitador_id'];
            }

            $debugContext = [
                'GET' => $_GET,
                'rol_id' => $_SESSION['usuario']['rol_id'] ?? null,
                'facilitadorId' => $facilitadorId,
                'fechaInicio' => $fechaInicio,
                'fechaFin' => $fechaFin,
            ];
            if (function_exists('error_log')) {
                @error_log('CSV DEBUG contexto: ' . json_encode($debugContext));
            }

            // Consulta datos con fallbacks de esquema: primero 'aprendices', si falla intentamos 'estudiantes'.
            // Usar condición de solape: inicio < fin_rango Y fin > inicio_rango
            $params = [$finDT, $iniDT];
            $filterSuffix = '';
            if ($facilitadorId) { $filterSuffix = ' AND hf.facilitador_id = ?'; $params[] = $facilitadorId; }
            $orderSuffix = ' ORDER BY hf.fecha_inicio';

            $sqlApr = "
                SELECT 
                    hf.id,
                    COALESCE(hf.titulo,'Clase') AS titulo,
                    hf.fecha_inicio,
                    hf.fecha_fin,
                    hf.aula,
                    COALESCE(hf.estado,'programado') AS estado,
                    hf.ficha_id,
                    COALESCE(f.numero, f.id) AS ficha_numero,
                    f.nombre AS ficha_nombre,
                    CONCAT(u.nombres,' ',u.apellidos) AS profesor_nombre,
                    COALESCE(
                      c.nombre,
                      (SELECT c2.nombre FROM colegios c2 INNER JOIN aprendices ap2 ON ap2.colegio_id=c2.id WHERE ap2.ficha_id=f.id LIMIT 1),
                      f.colegio_nombre
                    ) AS colegio_nombre
                FROM horarios_fichas hf
                JOIN fichas f ON hf.ficha_id = f.id
                LEFT JOIN facilitadores p ON p.id = hf.facilitador_id
                LEFT JOIN usuarios u ON p.usuario_id = u.id
                LEFT JOIN colegios c ON c.id = f.colegio_id
                WHERE (hf.fecha_inicio < ? AND hf.fecha_fin > ?)" 
                . $filterSuffix 
                . $orderSuffix;

            try {
                $rows = $this->queryWithFacilitadorUserJoin($sqlApr, $params);
            } catch (\PDOException $eQ1) {
                if ($eQ1->getCode() !== '42S02' && $eQ1->getCode() !== '42S22') { throw $eQ1; }
                // Fallback: esquema con tabla 'estudiantes'
                $sqlEst = "
                    SELECT 
                        hf.id,
                        COALESCE(hf.titulo,'Clase') AS titulo,
                        hf.fecha_inicio,
                        hf.fecha_fin,
                        hf.aula,
                        COALESCE(hf.estado,'programado') AS estado,
                        hf.ficha_id,
                        COALESCE(f.numero, f.id) AS ficha_numero,
                        f.nombre AS ficha_nombre,
                        CONCAT(u.nombres,' ',u.apellidos) AS profesor_nombre,
                        COALESCE(
                          c.nombre,
                          (SELECT c2.nombre FROM colegios c2 INNER JOIN estudiantes e2 ON e2.colegio_id=c2.id WHERE e2.ficha_id=f.id LIMIT 1),
                          f.colegio_nombre
                        ) AS colegio_nombre
                    FROM horarios_fichas hf
                    JOIN fichas f ON hf.ficha_id = f.id
                    LEFT JOIN facilitadores p ON p.id = hf.facilitador_id
                    LEFT JOIN usuarios u ON p.usuario_id = u.id
                    LEFT JOIN colegios c ON c.id = f.colegio_id
                    WHERE (hf.fecha_inicio < ? AND hf.fecha_fin > ?)" 
                    . $filterSuffix 
                    . $orderSuffix;
                try {
                    $rows = $this->queryWithFacilitadorUserJoin($sqlEst, $params);
                } catch (\PDOException $eQ2) {
                    if ($eQ2->getCode() !== '42S02' && $eQ2->getCode() !== '42S22') { throw $eQ2; }
                    // Fallback final: no subconsultas a estudiantes/aprendices; sin JOIN a colegios
                    $sqlSafe = "
                        SELECT 
                            hf.id,
                            COALESCE(hf.titulo,'Clase') AS titulo,
                            hf.fecha_inicio,
                            hf.fecha_fin,
                            hf.aula,
                            COALESCE(hf.estado,'programado') AS estado,
                            hf.ficha_id,
                            COALESCE(f.numero, f.id) AS ficha_numero,
                            f.nombre AS ficha_nombre,
                            CONCAT(u.nombres,' ',u.apellidos) AS profesor_nombre,
                            '' AS colegio_nombre
                        FROM horarios_fichas hf
                        JOIN fichas f ON hf.ficha_id = f.id
                        LEFT JOIN facilitadores p ON p.id = hf.facilitador_id
                        LEFT JOIN usuarios u ON p.usuario_id = u.id
                        WHERE (hf.fecha_inicio < ? AND hf.fecha_fin > ?)" 
                        . $filterSuffix 
                        . $orderSuffix;
                    $rows = $this->queryWithFacilitadorUserJoin($sqlSafe, $params);
                }
            }

            if (function_exists('error_log')) {
                $countApr = isset($rows) && is_array($rows) ? count($rows) : 0;
                @error_log('CSV DEBUG filas tras consultas principales: ' . $countApr);
            }

            // Si seguimos sin filas pero tenemos un facilitador definido,
            // intentar reutilizar la misma lógica que usa el calendario del profesor
            if (empty($rows) && $facilitadorId) {
                try {
                    $eventos = $this->obtenerHorariosProfesor($iniDT, $finDT, (int)$facilitadorId);
                    foreach ($eventos as $ev) {
                        $fi = !empty($ev['start']) ? new DateTime($ev['start']) : null;
                        $ff = !empty($ev['end']) ? new DateTime($ev['end']) : null;
                        if (!$fi || !$ff) { continue; }
                        $rows[] = [
                            'colegio_nombre'  => $ev['extendedProps']['colegio_nombre'] ?? '',
                            'titulo'          => $ev['title'] ?? 'Clase',
                            'fecha_inicio'    => $fi->format('Y-m-d H:i:s'),
                            'fecha_fin'       => $ff->format('Y-m-d H:i:s'),
                            'aula'            => $ev['extendedProps']['aula'] ?? '',
                            'estado'          => $ev['extendedProps']['estado'] ?? 'programado',
                            'ficha_numero'    => $ev['extendedProps']['ficha_codigo'] ?? '',
                            'profesor_nombre' => $ev['extendedProps']['profesor_nombre'] ?? '',
                        ];
                    }
                } catch (\Throwable $eF) {
                    // Mantener $rows vacío si algo falla para no romper la descarga
                }
            }

            // Fallback adicional: si aún no hay filas, intentar por columna profesor_id directamente
            if (empty($rows) && $facilitadorId) {
                try {
                    $sqlProf = "
                        SELECT 
                            hf.id,
                            COALESCE(hf.titulo,'Clase') AS titulo,
                            hf.fecha_inicio,
                            hf.fecha_fin,
                            hf.aula,
                            COALESCE(hf.estado,'programado') AS estado,
                            hf.ficha_id,
                            COALESCE(f.numero, f.id) AS ficha_numero,
                            f.nombre AS ficha_nombre,
                            CONCAT(u.nombres,' ',u.apellidos) AS profesor_nombre,
                            '' AS colegio_nombre
                        FROM horarios_fichas hf
                        JOIN fichas f ON hf.ficha_id = f.id
                        LEFT JOIN facilitadores p ON p.id = hf.profesor_id
                        LEFT JOIN usuarios u ON p.usuario_id = u.id
                        WHERE hf.profesor_id = ?
                          AND (hf.fecha_inicio < ? AND hf.fecha_fin > ?)
                        ORDER BY hf.fecha_inicio
                    ";
                    $paramsProf = [(int)$facilitadorId, $finDT, $iniDT];
                    $stmtProf = $this->pdo->prepare($sqlProf);
                    $stmtProf->execute($paramsProf);
                    $rows = $stmtProf->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $eQProf) {
                    // Si falla (por ausencia de columna profesor_id, por ejemplo), dejamos $rows como está
                }
            }

            // Último recurso: si sigue vacío pero el facilitador tiene clases en el calendario,
            // reutilizar la lógica de obtenerHorariosProfesor sin restricción de fechas.
            if (empty($rows) && $facilitadorId) {
                try {
                    $eventos = $this->obtenerHorariosProfesor(null, null, (int)$facilitadorId);
                    foreach ($eventos as $ev) {
                        $fi = !empty($ev['start']) ? new DateTime($ev['start']) : null;
                        $ff = !empty($ev['end']) ? new DateTime($ev['end']) : null;
                        if (!$fi || !$ff) { continue; }
                        $rows[] = [
                            'colegio_nombre'  => $ev['extendedProps']['colegio_nombre'] ?? '',
                            'titulo'          => $ev['title'] ?? 'Clase',
                            'fecha_inicio'    => $fi->format('Y-m-d H:i:s'),
                            'fecha_fin'       => $ff->format('Y-m-d H:i:s'),
                            'aula'            => $ev['extendedProps']['aula'] ?? '',
                            'estado'          => $ev['extendedProps']['estado'] ?? 'programado',
                            'ficha_numero'    => $ev['extendedProps']['ficha_codigo'] ?? '',
                            'profesor_nombre' => $ev['extendedProps']['profesor_nombre'] ?? '',
                        ];
                    }
                } catch (\Throwable $eF2) {
                }
            }

            if (function_exists('error_log')) {
                $totalFinal = isset($rows) && is_array($rows) ? count($rows) : 0;
                @error_log('CSV DEBUG filas finales para Excel: ' . $totalFinal);
            }

            // Modo debug opcional: si se pasa ?debug=1, devolver JSON en vez de Excel
            if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
                header('Content-Type: application/json; charset=utf-8');

                $debugRows = is_array($rows) ? $rows : [];
                $debug = [
                    'contexto' => $debugContext,
                    'total_filas' => count($debugRows),
                    'muestra' => array_slice($debugRows, 0, 10),
                ];

                // Añadir información de la lógica de calendario del profesor para comparar
                if ($facilitadorId) {
                    try {
                        $evMes = $this->obtenerHorariosProfesor($iniDT, $finDT, (int)$facilitadorId);
                        $evTodos = $this->obtenerHorariosProfesor(null, null, (int)$facilitadorId);
                        $debug['eventos_mes_count'] = is_array($evMes) ? count($evMes) : 0;
                        $debug['eventos_todos_count'] = is_array($evTodos) ? count($evTodos) : 0;
                        $debug['eventos_mes_sample'] = is_array($evMes) ? array_slice($evMes, 0, 5) : [];
                    } catch (\Throwable $eDbg) {
                        $debug['eventos_error'] = $eDbg->getMessage();
                    }
                }

                echo json_encode($debug, JSON_UNESCAPED_UNICODE);
                return;
            }

            // Solo XLSX (PhpSpreadsheet)
            if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
                http_response_code(500);
                echo 'PhpSpreadsheet no está instalado. Instala con: composer require phpoffice/phpspreadsheet';
                return;
            }
            $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $ss->getActiveSheet();
            $sheet->setTitle('Clases');
            $headers = ['A1' => 'Colegio', 'B1' => 'Titulo', 'C1' => 'Instructor', 'D1' => 'Ficha', 'E1' => 'Aula', 'F1' => 'Horario', 'G1' => 'Estado'];
            foreach ($headers as $cell => $text) { $sheet->setCellValue($cell, $text); }
            $sheet->getStyle('A1:G1')->getFont()->setBold(true);
            $r = 2; foreach ($rows as $row) {
                $inicio = (new DateTime($row['fecha_inicio']))->format('Y-m-d H:i');
                $fin    = (new DateTime($row['fecha_fin']))->format('Y-m-d H:i');
                $hor    = $inicio.' - '.$fin;
                $sheet->setCellValue('A'.$r, (string)($row['colegio_nombre'] ?? ''));
                $sheet->setCellValue('B'.$r, 'Clase');
                $sheet->setCellValue('C'.$r, (string)($row['profesor_nombre'] ?? ''));
                $sheet->setCellValue('D'.$r, (string)($row['ficha_numero'] ?? ''));
                $sheet->setCellValue('E'.$r, (string)($row['aula'] ?? ''));
                $sheet->setCellValue('F'.$r, $hor);
                $sheet->setCellValue('G'.$r, (string)($row['estado'] ?? 'programado'));
                $r++;
            }
            foreach (range('A','G') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="reporte_clases.xlsx"');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
            $writer->save('php://output');
            return;
        } catch (Throwable $e) {
            http_response_code(500);
            echo 'Error al exportar: ' . $e->getMessage();
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
            // Evitar que warnings/notices rompan el JSON
            @ini_set('display_errors', '0');
            if (function_exists('ob_start')) { @ob_start(); }
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
            $facilitador_id = $_SESSION['usuario']['facilitador_id'] ?? null;
            if ($rol === 1 && isset($_GET['facilitador_id']) && $_GET['facilitador_id'] !== '') {
                $facilitador_id = $_GET['facilitador_id'];
            }
            
            if (!$facilitador_id) {
                $facilitador_id = $this->resolverProfesorIdDesdeSesion();
            }
            if (!$facilitador_id) {
                // Si es ADMIN y no se especificó instructor, devolver fichas del sistema
                if ($rol === 1) {
                    $todas = [];
                    try {
                        $sqlA = "
                            SELECT DISTINCT f.id, COALESCE(f.numero, f.id) AS codigo, f.nombre
                            FROM fichas f
                            WHERE 
                                EXISTS (SELECT 1 FROM horarios_fichas hf WHERE hf.ficha_id = f.id)
                             OR EXISTS (SELECT 1 FROM facilitador_ficha ff WHERE ff.ficha_id = f.id)
                             OR COALESCE(f.facilitador_id, 0) <> 0
                        ";
                        $stA = $this->pdo->prepare($sqlA);
                        $stA->execute();
                        $todas = $stA->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (\PDOException $eA) {
                        // Fallbacks de esquema
                        try {
                            $sqlB = "
                                SELECT DISTINCT f.id, COALESCE(f.numero, f.id) AS codigo, f.nombre
                                FROM fichas f
                                WHERE 
                                    EXISTS (SELECT 1 FROM horarios_fichas hf WHERE hf.ficha_id = f.id)
                                 OR COALESCE(f.facilitador, 0) <> 0
                            ";
                            $stB = $this->pdo->prepare($sqlB);
                            $stB->execute();
                            $todas = $stB->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        } catch (\PDOException $eB) { $todas = []; }
                    }
                    if (function_exists('ob_get_length') && ob_get_length() !== false) { @ob_clean(); }
                    echo json_encode($todas);
                    return;
                }
                // Para roles no-admin: devolver vacío sin error
                echo json_encode([]);
                return;
            }
            // Traer fichas vinculadas al facilitador (propias o asignadas)
            // Resolver usuario_id del facilitador para esquemas que guardan compartidos por usuario
            $fichas = [];
            $usuarioFacId = 0;
            try {
                $stUF = $this->pdo->prepare("SELECT usuario_id FROM facilitadores WHERE id = ?");
                $stUF->execute([(int)$facilitador_id]);
                $usuarioFacId = (int)($stUF->fetchColumn() ?: 0);
            } catch (\PDOException $eUF) {
                if ($eUF->getCode() !== '42S22') { /* noop */ }
                try {
                    $stUF2 = $this->pdo->prepare("SELECT usuario FROM facilitadores WHERE id = ?");
                    $stUF2->execute([(int)$facilitador_id]);
                    $usuarioFacId = (int)($stUF2->fetchColumn() ?: 0);
                } catch (\Throwable $_) { /* noop */ }
            }
            // Intento 1: UNION de relación puente, clases existentes y fichas compartidas aceptadas (esquema actual)
            try {
                $sql1 = "
                    SELECT DISTINCT f.id, COALESCE(f.numero, f.id) AS codigo, f.nombre
                    FROM fichas f
                    INNER JOIN (
                        SELECT ficha_id FROM facilitador_ficha WHERE facilitador_id = ?
                        UNION
                        SELECT ficha_id FROM horarios_fichas WHERE facilitador_id = ?
                        UNION
                        SELECT ficha_id FROM fichas_compartidas WHERE profesor_compartido_id = ? AND (estado = 'Aceptada' OR estado_id = 2)
                    ) x ON x.ficha_id = f.id
                    ORDER BY COALESCE(f.numero, f.id), f.nombre
                ";
                $st1 = $this->pdo->prepare($sql1);
                try {
                    $st1->execute([$facilitador_id, $facilitador_id, $usuarioFacId]);
                } catch (\PDOException $e1a) {
                    if ($e1a->getCode() !== '42S22') { throw $e1a; }
                    // Fallback A: usar 'ficha' en lugar de 'ficha_id'
                    try {
                        $sql1b = str_replace('SELECT ficha_id FROM fichas_compartidas', 'SELECT ficha FROM fichas_compartidas', $sql1);
                        $st1 = $this->pdo->prepare($sql1b);
                        $st1->execute([$facilitador_id, $facilitador_id, $usuarioFacId]);
                    } catch (\PDOException $e1b) {
                        if ($e1b->getCode() !== '42S22') { throw $e1b; }
                        // Fallback B: usar 'facilitador_compartido' (usa facilitador_id)
                        try {
                            $sql1c = str_replace('profesor_compartido_id', 'facilitador_compartido', $sql1);
                            $st1 = $this->pdo->prepare($sql1c);
                            $st1->execute([$facilitador_id, $facilitador_id, $facilitador_id]);
                        } catch (\PDOException $e1c) {
                            if ($e1c->getCode() !== '42S22') { throw $e1c; }
                            // Fallback C: usar 'profesor_compartido' (usa usuarios.id)
                            try {
                                $sql1c2 = str_replace('profesor_compartido_id', 'profesor_compartido', $sql1);
                                $st1 = $this->pdo->prepare($sql1c2);
                                $st1->execute([$facilitador_id, $facilitador_id, $usuarioFacId]);
                            } catch (\PDOException $e1c2) {
                                if ($e1c2->getCode() !== '42S22') { throw $e1c2; }
                                // Fallback D: combinar 'ficha' + 'facilitador_compartido'
                                try {
                                    $sql1d = str_replace(
                                        ['SELECT ficha_id FROM fichas_compartidas','profesor_compartido_id'],
                                        ['SELECT ficha FROM fichas_compartidas','facilitador_compartido'],
                                        $sql1
                                    );
                                    $st1 = $this->pdo->prepare($sql1d);
                                    $st1->execute([$facilitador_id, $facilitador_id, $facilitador_id]);
                                } catch (\PDOException $e1d) {
                                    if ($e1d->getCode() !== '42S22') { throw $e1d; }
                                    // Fallback E: combinar 'ficha' + 'profesor_compartido'
                                    try {
                                        $sql1d2 = str_replace(
                                            ['SELECT ficha_id FROM fichas_compartidas','profesor_compartido_id'],
                                            ['SELECT ficha FROM fichas_compartidas','profesor_compartido'],
                                            $sql1
                                        );
                                        $st1 = $this->pdo->prepare($sql1d2);
                                        $st1->execute([$facilitador_id, $facilitador_id, $usuarioFacId]);
                                    } catch (\PDOException $e1d2) {
                                        if ($e1d2->getCode() !== '42S22') { throw $e1d2; }
                                        // Fallback F: si falla columna 'estado', usar solo estado_id = 2
                                        $sql1e = str_replace("(estado = 'Aceptada' OR estado_id = 2)", 'estado_id = 2', $sql1d2);
                                        $st1 = $this->pdo->prepare($sql1e);
                                        $st1->execute([$facilitador_id, $facilitador_id, $usuarioFacId]);
                                    }
                                }
                            }
                        }
                    }
                }
                $fichas = $st1->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e1) {
                if (!in_array($e1->getCode(), ['42S02','42S22'], true)) { throw $e1; }
            }

            // Intento 2: columna directa en fichas + clases existentes + compartidas (si Intento 1 quedó vacío o no aplica)
            if (empty($fichas)) {
                try {
                    $sql2 = "
                        SELECT DISTINCT f.id, COALESCE(f.numero, f.id) AS codigo, f.nombre
                        FROM fichas f
                        WHERE f.facilitador_id = ?
                           OR EXISTS (SELECT 1 FROM horarios_fichas hf WHERE hf.ficha_id = f.id AND hf.facilitador_id = ?)
                           OR EXISTS (SELECT 1 FROM fichas_compartidas fc WHERE fc.ficha_id = f.id AND fc.profesor_compartido_id = ? AND (fc.estado = 'Aceptada' OR fc.estado_id = 2))
                        ORDER BY COALESCE(f.numero, f.id), f.nombre
                    ";
                    $st2 = $this->pdo->prepare($sql2);
                    try {
                        $st2->execute([$facilitador_id, $facilitador_id, $usuarioFacId]);
                    } catch (\PDOException $e2a) {
                        if ($e2a->getCode() !== '42S22') { throw $e2a; }
                        // Fallback A: usar fc.ficha en EXISTS
                        try {
                            $sql2b = str_replace('fc.ficha_id = f.id', 'fc.ficha = f.id', $sql2);
                            $st2 = $this->pdo->prepare($sql2b);
                            $st2->execute([$facilitador_id, $facilitador_id, $usuarioFacId]);
                        } catch (\PDOException $e2b) {
                            if ($e2b->getCode() !== '42S22') { throw $e2b; }
                            // Fallback B: usar facilitador_compartido (usa facilitador_id)
                            try {
                                $sql2c = str_replace('fc.profesor_compartido_id = ?', 'fc.facilitador_compartido = ?', $sql2);
                                $st2 = $this->pdo->prepare($sql2c);
                                $st2->execute([$facilitador_id, $facilitador_id, $facilitador_id]);
                            } catch (\PDOException $e2c) {
                                if ($e2c->getCode() !== '42S22') { throw $e2c; }
                                // Fallback C: usar profesor_compartido (usuarios.id)
                                try {
                                    $sql2c2 = str_replace('fc.profesor_compartido_id = ?', 'fc.profesor_compartido = ?', $sql2);
                                    $st2 = $this->pdo->prepare($sql2c2);
                                    $st2->execute([$facilitador_id, $facilitador_id, $usuarioFacId]);
                                } catch (\PDOException $e2c2) {
                                    if ($e2c2->getCode() !== '42S22') { throw $e2c2; }
                                    // Fallback D: si falta columna 'estado', usar solo estado_id = 2
                                    $sql2d = str_replace("(fc.estado = 'Aceptada' OR fc.estado_id = 2)", 'fc.estado_id = 2', $sql2c2);
                                    $st2 = $this->pdo->prepare($sql2d);
                                    $st2->execute([$facilitador_id, $facilitador_id, $usuarioFacId]);
                                }
                            }
                        }
                    }
                    $fichas = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $e2) {
                    if (!in_array($e2->getCode(), ['42S02','42S22'], true)) { throw $e2; }
                }
            }

            // Intento 3 (legacy): profesor_ficha + horarios_fichas.facilitador_id
            if (empty($fichas)) {
                try {
                    $sql3 = "
                        SELECT DISTINCT f.id, COALESCE(f.numero, f.id) AS codigo, f.nombre
                        FROM fichas f
                        INNER JOIN (
                            SELECT ficha_id FROM profesor_ficha WHERE facilitador_id = ?
                            UNION
                            SELECT ficha_id FROM horarios_fichas WHERE facilitador_id = ?
                        ) y ON y.ficha_id = f.id
                        ORDER BY COALESCE(f.numero, f.id), f.nombre
                    ";
                    $st3 = $this->pdo->prepare($sql3);
                    $st3->execute([$facilitador_id, $facilitador_id]);
                    $fichas = $st3->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $e3) {
                    if (!in_array($e3->getCode(), ['42S02','42S22'], true)) { throw $e3; }
                }
            }

            // Intento 3b (legacy puro): solo horarios_fichas.facilitador_id cuando la tabla profesor_ficha no existe
            if (empty($fichas)) {
                try {
                    $sql3b = "
                        SELECT DISTINCT f.id, COALESCE(f.numero, f.id) AS codigo, f.nombre
                        FROM fichas f
                        INNER JOIN (
                            SELECT ficha_id FROM horarios_fichas WHERE facilitador_id = ?
                        ) z ON z.ficha_id = f.id
                        ORDER BY COALESCE(f.numero, f.id), f.nombre
                    ";
                    $st3b = $this->pdo->prepare($sql3b);
                    $st3b->execute([$facilitador_id]);
                    $fichas = $st3b->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $e3b) {
                    if (!in_array($e3b->getCode(), ['42S02','42S22'], true)) { throw $e3b; }
                }
            }

            // Limpiar cualquier salida previa y responder JSON limpio
            if (function_exists('ob_get_length') && ob_get_length() !== false) { @ob_clean(); }
            echo json_encode($fichas);
            
        } catch (Exception $e) {
            $errorMessage = 'Error en obtenerFichasDisponibles: ' . $e->getMessage();
            error_log($errorMessage);
            error_log('Trace: ' . $e->getTraceAsString());
            http_response_code(500);
            if (function_exists('ob_get_length') && ob_get_length() !== false) { @ob_clean(); }
            echo json_encode([
                'error' => 'Error al cargar las fichas disponibles',
                'debug' => $errorMessage
            ]);
        } finally {
            if (function_exists('ob_get_length') && ob_get_length() !== false) { @ob_end_flush(); }
        }
    }
}
