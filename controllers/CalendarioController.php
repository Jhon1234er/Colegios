<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

class CalendarioController {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Database::conectar();
    }
    
    // Obtener horarios para el calendario
    public function obtenerHorarios() {
        header('Content-Type: application/json');
        
        try {
            // Primero actualizar estados automáticamente
            $this->actualizarEstadosAutomaticos();
            
            // Obtener parámetros de vista del frontend
            $start = $_GET['start'] ?? null;
            $end = $_GET['end'] ?? null;
            $view = $_GET['view'] ?? 'dayGridMonth';
            
            error_log("Parámetros recibidos - Start: $start, End: $end, View: $view");
            
            // Verificar si hay sesión de usuario
            if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['profesor_id'])) {
                // Mostrar todos los horarios si no hay sesión (modo público)
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
                    
                    $eventos[] = [
                        'id' => $horario['id'],
                        'title' => $horario['titulo'],
                        'start' => date('c', strtotime($horario['fecha_inicio'])),
                        'end' => date('c', strtotime($horario['fecha_fin'])),
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
                    
                    $eventos[] = [
                        'id' => $horario['id'],
                        'title' => $horario['titulo'],
                        'start' => date('c', strtotime($horario['fecha_inicio'])),
                        'end' => date('c', strtotime($horario['fecha_fin'])),
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
                // Convertir fechas a formato ISO para FullCalendar
                $fechaInicio = date('c', strtotime($horario['fecha_inicio']));
                $fechaFin = date('c', strtotime($horario['fecha_fin']));
                
                $eventos[] = [
                    'id' => $horario['id'],
                    'title' => $horario['titulo'],
                    'start' => $fechaInicio,
                    'end' => $fechaFin,
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
            $stmt->execute([$profesor_id, $profesor_id, $profesor_id]);
            $horarios_sincronizados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatear horarios sincronizados
            foreach ($horarios_sincronizados as $horario) {
                $eventos[] = [
                    'id' => 'sync_' . $horario['id'],
                    'title' => $horario['titulo'] . ' (' . $horario['profesor_nombre'] . ')',
                    'start' => $horario['fecha_inicio'],
                    'end' => $horario['fecha_fin'],
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
        start_secure_session();
        require_role(2);
        $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            // Debug logs
            error_log("=== CREANDO HORARIO ===");
            error_log("Datos recibidos: " . json_encode($data));
            error_log("Profesor ID: " . $profesor_id);
            error_log("Color a guardar: " . ($data['color'] ?? '#007bff'));
            
            if (!$profesor_id) {
                throw new Exception("ID de profesor no encontrado en la sesión");
            }
            
            if (!$data) {
                throw new Exception("No se recibieron datos JSON válidos");
            }
            
            // Validar datos requeridos
            $campos_requeridos = ['ficha_id', 'titulo', 'fecha_inicio', 'fecha_fin', 'dia_semana', 'hora_inicio', 'hora_fin'];
            foreach ($campos_requeridos as $campo) {
                if (!isset($data[$campo]) || empty($data[$campo])) {
                    error_log("Campo faltante o vacío: $campo");
                    throw new Exception("Campo requerido faltante o vacío: $campo");
                }
            }
            
            // Verificar conflictos de horarios
            $conflictos = $this->verificarConflictos($data, $profesor_id);
            if (!empty($conflictos)) {
                http_response_code(409);
                echo json_encode(['error' => 'Conflicto de horarios detectado', 'conflictos' => $conflictos]);
                return;
            }
            
            $sql = "
                INSERT INTO horarios_fichas 
                (ficha_id, profesor_id, titulo, fecha_inicio, fecha_fin, dia_semana, 
                 hora_inicio, hora_fin, aula, color, creado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['ficha_id'],
                $profesor_id,
                $data['titulo'],
                $data['fecha_inicio'],
                $data['fecha_fin'],
                $data['dia_semana'],
                $data['hora_inicio'],
                $data['hora_fin'],
                $data['aula'] ?? null,
                $data['color'] ?? '#007bff',
                $profesor_id
            ]);
            
            $horario_id = $this->pdo->lastInsertId();
            
            // Registrar en historial
            $this->registrarHistorial($horario_id, $profesor_id, 'crear', null, $data);
            
            echo json_encode(['success' => true, 'horario_id' => $horario_id]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
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
                SET titulo = ?, fecha_inicio = ?, fecha_fin = ?, dia_semana = ?,
                    hora_inicio = ?, hora_fin = ?, aula = ?, color = ?
                WHERE id = ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['titulo'],
                $data['fecha_inicio'],
                $data['fecha_fin'],
                $data['dia_semana'],
                $data['hora_inicio'],
                $data['hora_fin'],
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
    
    // Obtener fichas disponibles para el profesor
    public function obtenerFichasDisponibles() {
        start_secure_session();
        require_role(2);
        $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
        
        if (!$profesor_id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de profesor no encontrado en la sesión']);
            return;
        }
        
        try {
            $sql = "
                SELECT DISTINCT f.id, f.numero as codigo, f.nombre, 'Programa General' as programa
                FROM fichas f
                WHERE f.id IN (
                    SELECT pf.ficha_id 
                    FROM profesor_ficha pf 
                    WHERE pf.profesor_id = ?
                )
                OR f.id IN (
                    SELECT fc.ficha_id 
                    FROM fichas_compartidas fc 
                    WHERE fc.profesor_compartido_id = ? 
                      AND fc.estado = 'aceptada'
                )
                ORDER BY f.numero
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$profesor_id, $profesor_id]);
            $fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode($fichas);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener fichas: ' . $e->getMessage()]);
        }
    }
    
    // Actualizar estados automáticamente según la hora actual
    private function actualizarEstadosAutomaticos() {
        try {
            $ahora = new DateTime();
            
            // Actualizar eventos que ya terminaron a 'finalizado'
            $sql_finalizar = "
                UPDATE horarios_fichas 
                SET estado = 'finalizado' 
                WHERE fecha_fin < ? 
                AND estado IN ('programado', 'en_curso')
            ";
            
            $stmt = $this->pdo->prepare($sql_finalizar);
            $finalizados = $stmt->execute([$ahora->format('Y-m-d H:i:s')]);
            $countFinalizado = $stmt->rowCount();
            
            // Actualizar eventos que están en curso
            $sql_en_curso = "
                UPDATE horarios_fichas 
                SET estado = 'en_curso' 
                WHERE fecha_inicio <= ? 
                AND fecha_fin > ? 
                AND estado = 'programado'
            ";
            
            $stmt = $this->pdo->prepare($sql_en_curso);
            $stmt->execute([
                $ahora->format('Y-m-d H:i:s'),
                $ahora->format('Y-m-d H:i:s')
            ]);
            $countEnCurso = $stmt->rowCount();
            
            // Log para debug
            if ($countFinalizado > 0 || $countEnCurso > 0) {
                error_log("Estados actualizados: {$countFinalizado} finalizados, {$countEnCurso} en curso - " . $ahora->format('Y-m-d H:i:s'));
            }
            
        } catch (Exception $e) {
            error_log("Error actualizando estados automáticos: " . $e->getMessage());
        }
    }
}
?>
