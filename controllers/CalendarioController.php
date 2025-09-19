<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

class CalendarioController {
    private $pdo;
    
    public function __construct() {
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
            $view = $_GET['view'] ?? 'dayGridMonth';
            $profesorFiltro = $_GET['profesor_id'] ?? null;
            $esAdmin = (int)($_SESSION['usuario']['rol_id'] ?? 0) === 1;
            
            error_log("Parámetros recibidos - Start: $start, End: $end, View: $view");
            
            // Si es Admin, mostrar todos los calendarios
            if ($esAdmin) {
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
                
                // Si hay un filtro de profesor, aplicarlo
                if ($profesorFiltro) {
                    $sql .= " AND hf.profesor_id = ?";
                    $params[] = $profesorFiltro;
                }
                
                // Filtrar por rango de fechas si se especifica
                if ($start && $end) { 
                    $sql .= " AND hf.fecha_inicio >= ? AND hf.fecha_fin <= ?"; 
                    $params[] = $start; 
                    $params[] = $end; 
                }
                
                $sql .= " ORDER BY hf.fecha_inicio, u.nombres, u.apellidos";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $eventos = [];
                foreach ($horarios as $horario) {
                    $ahora = new DateTime();
                    $fechaInicio = new DateTime($horario['fecha_inicio']);
                    $fechaFin = new DateTime($horario['fecha_fin']);
                    
                    // Solo marcar como pasado si la fecha de finalización es anterior a ahora
                    // y no está en estado 'cancelado' o 'finalizado'
                    $esPasado = $fechaFin < $ahora && 
                               $horario['estado'] !== 'cancelado' && 
                               $horario['estado'] !== 'finalizado';
                    
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
                echo json_encode($eventos);
                return;
            }

            // Verificar si el usuario es profesor
            if (!isset($_SESSION['usuario']['profesor_id'])) {
                // Si no es profesor, no debería llegar aquí porque ya manejamos el caso de admin
                http_response_code(403);
                echo json_encode(['error' => 'Acceso no autorizado']);
                return;
            } else {
                // Mostrar solo los horarios del profesor
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
                    JOIN usuarios u ON p.usuario_id = u.id";
                
                $params = [];
                // Agregar filtro de fechas si se proporcionan
                if ($start && $end) {
                    $sql .= " WHERE hf.fecha_inicio >= ? AND hf.fecha_fin <= ?";
                    $params = [$start, $end];
                }
                
                $sql .= " ORDER BY hf.fecha_inicio";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $eventos = [];
                foreach ($horarios as $horario) {
                    // Determinar si el evento ya pasó
                    $ahora = new DateTime();
                    $fechaEvento = new DateTime($horario['fecha_fin']);
                    $esPasado = $fechaEvento < $ahora;
                    
                    // Color y clase según si ya pasó
                    $color = $esPasado ? '#6c757d' : $horario['color_actual']; // Gris si ya pasó
                    $claseEstado = $esPasado ? 'evento-pasado' : 'evento-' . $horario['estado'];
                    
                    $fechaInicio = new DateTime($horario['fecha_inicio']);
                    $fechaFin = new DateTime($horario['fecha_fin']);
                    
                    // Reemplazar "Profesor" con el nombre real del profesor en el título
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
                
                header('Content-Type: application/json');
                echo json_encode($eventos);
                return;
            }
            
            // Si hay sesión, verificar rol de profesor
            $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
            
            if (!$profesor_id) {
                // Si no hay profesor_id, mostrar todos los horarios como modo público
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
                    ORDER BY hf.fecha_inicio
                ";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $eventos = [];
                foreach ($horarios as $horario) {
                    // Determinar si el evento ya pasó
                    $ahora = new DateTime();
                    $fechaEvento = new DateTime($horario['fecha_fin']);
                    $esPasado = $fechaEvento < $ahora;
                    
                    // Color y clase según si ya pasó
                    $color = $esPasado ? '#6c757d' : $horario['color_actual']; // Gris si ya pasó
                    $claseEstado = $esPasado ? 'evento-pasado' : 'evento-' . $horario['estado'];
                    
                    $fechaInicio = new DateTime($horario['fecha_inicio']);
                    $fechaFin = new DateTime($horario['fecha_fin']);
                    
                    // Usar las fechas reales almacenadas para que el bloque ocupe toda la duración
                    
                    // Reemplazar "Profesor" con el nombre real del profesor en el título
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
                
                header('Content-Type: application/json');
                echo json_encode($eventos);
                return;
            }
            
            $eventos = [];
            
            // 1. Obtener horarios propios y de fichas compartidas
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
                       SELECT pf.ficha_id 
                       FROM profesor_ficha pf 
                       WHERE pf.profesor_id = ?
                   )
                   OR hf.ficha_id IN (
                       SELECT fc.ficha_id 
                       FROM fichas_compartidas fc 
                       WHERE fc.profesor_compartido_id = ? 
                         AND fc.estado = 'aceptada'
                   ))";
            
            $params_propios = [$profesor_id, $profesor_id, $profesor_id, $profesor_id];
            
            // Agregar filtro de fechas si se proporcionan
            if ($start && $end) {
                $sql_propios .= " AND hf.fecha_inicio >= ? AND hf.fecha_fin <= ?";
                $params_propios[] = $start;
                $params_propios[] = $end;
            }
            
            $sql_propios .= " ORDER BY hf.fecha_inicio";
            
            $stmt = $this->pdo->prepare($sql_propios);
            $stmt->execute($params_propios);
            $horarios_propios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatear horarios propios
            foreach ($horarios_propios as $horario) {
                // Convertir fechas a objetos DateTime para formateo consistente
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
            
            // 2. Obtener horarios de calendarios sincronizados
            $sql_sincronizados = "
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
                JOIN calendario_sincronizacion cs ON (
                    (cs.profesor_propietario_id = ? AND cs.profesor_sincronizado_id = hf.profesor_id) OR
                    (cs.profesor_sincronizado_id = ? AND cs.profesor_propietario_id = hf.profesor_id)
                )
                WHERE cs.estado = 'aceptado'
                  AND hf.profesor_id != ?
                ORDER BY hf.fecha_inicio
            ";
            
            $stmt = $this->pdo->prepare($sql_sincronizados);
            $result = $stmt->execute([$profesor_id, $profesor_id, $profesor_id]);
            $horarios_sincronizados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatear horarios sincronizados
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
                    'editable' => $horario['permisos'] === 'lectura_escritura',
                    'extendedProps' => [
                        'horario_original_id' => $horario['id'],
                        'ficha_id' => $horario['ficha_id'],
                        'ficha_codigo' => $horario['ficha_codigo'],
                        'ficha_nombre' => $horario['ficha_nombre'],
                        'profesor_nombre' => $horario['profesor_nombre'],
                        'aula' => $horario['aula'],
                        'estado' => $horario['estado'],
                        'tipo' => 'sincronizado',
                        'permisos' => $horario['permisos'],
                        'asistencia_habilitada' => $horario['asistencia_habilitada']
                    ]
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode($eventos);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener horarios: ' . $e->getMessage()]);
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
            
            // Insertar en la base de datos solo con los campos que existen
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
    
    // Registrar cambios en historial
    private function registrarHistorial($horario_id, $profesor_id, $accion, $datos_anteriores, $datos_nuevos) {
        $sql = "
            INSERT INTO calendario_historial 
            (horario_id, profesor_id, accion, datos_anteriores, datos_nuevos) 
            VALUES (?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $horario_id,
            $profesor_id,
            $accion,
            $datos_anteriores ? json_encode($datos_anteriores) : null,
            $datos_nuevos ? json_encode($datos_nuevos) : null
        ]);
    }
    
    /**
     * Obtener fichas disponibles para el profesor
     */
    /**
     * Exporta un reporte de las clases a formato CSV
     */
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