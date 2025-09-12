<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

class SincronizacionController {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Database::conectar();
    }
    
    // Solicitar sincronización con otro profesor
    public function solicitarSincronizacion() {
        start_secure_session();
        require_role(2);
        $profesor_propietario_id = $_SESSION['usuario']['profesor_id'] ?? null;
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $profesor_sincronizado_id = $data['profesor_id'];
            $permisos = $data['permisos'] ?? 'solo_lectura';
            
            if ($profesor_propietario_id == $profesor_sincronizado_id) {
                throw new Exception("No puedes sincronizar tu calendario contigo mismo");
            }
            
            // Verificar si ya existe una solicitud
            $sql_verificar = "
                SELECT id, estado FROM calendario_sincronizacion 
                WHERE profesor_propietario_id = ? AND profesor_sincronizado_id = ?
            ";
            $stmt = $this->pdo->prepare($sql_verificar);
            $stmt->execute([$profesor_propietario_id, $profesor_sincronizado_id]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existente) {
                if ($existente['estado'] === 'pendiente') {
                    throw new Exception("Ya existe una solicitud pendiente");
                } elseif ($existente['estado'] === 'aceptado') {
                    throw new Exception("Ya tienes sincronización activa con este profesor");
                }
                
                // Si fue rechazada, actualizar
                $sql_actualizar = "
                    UPDATE calendario_sincronizacion 
                    SET estado = 'pendiente', permisos = ?, fecha_solicitud = NOW(), fecha_respuesta = NULL
                    WHERE id = ?
                ";
                $stmt = $this->pdo->prepare($sql_actualizar);
                $stmt->execute([$permisos, $existente['id']]);
                $solicitud_id = $existente['id'];
            } else {
                // Crear nueva solicitud
                $sql = "
                    INSERT INTO calendario_sincronizacion 
                    (profesor_propietario_id, profesor_sincronizado_id, permisos, estado) 
                    VALUES (?, ?, ?, 'pendiente')
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$profesor_propietario_id, $profesor_sincronizado_id, $permisos]);
                $solicitud_id = $this->pdo->lastInsertId();
            }
            
            // Crear notificación
            $this->crearNotificacionSincronizacion($solicitud_id, $profesor_propietario_id, $profesor_sincronizado_id, $permisos);
            
            echo json_encode(['success' => true, 'message' => 'Solicitud de sincronización enviada']);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    // Responder a solicitud de sincronización
    public function responderSolicitud() {
        start_secure_session();
        require_role(2);
        $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $solicitud_id = $data['solicitud_id'];
            $respuesta = $data['respuesta']; // 'aceptado' o 'rechazado'
            
            // Verificar que la solicitud existe y es para este profesor
            $sql_verificar = "
                SELECT cs.*, p.nombres as nombre_propietario 
                FROM calendario_sincronizacion cs
                JOIN profesores p ON cs.profesor_propietario_id = p.id
                WHERE cs.id = ? AND cs.profesor_sincronizado_id = ? AND cs.estado = 'pendiente'
            ";
            $stmt = $this->pdo->prepare($sql_verificar);
            $stmt->execute([$solicitud_id, $profesor_id]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$solicitud) {
                throw new Exception("Solicitud no encontrada o ya procesada");
            }
            
            // Actualizar estado de la solicitud
            $sql_actualizar = "
                UPDATE calendario_sincronizacion 
                SET estado = ?, fecha_respuesta = NOW() 
                WHERE id = ?
            ";
            $stmt = $this->pdo->prepare($sql_actualizar);
            $stmt->execute([$respuesta, $solicitud_id]);
            
            // Crear notificación de respuesta
            $this->crearNotificacionRespuesta($solicitud, $respuesta);
            
            // Marcar notificación original como leída
            $this->marcarNotificacionLeida($solicitud_id, 'solicitud_sincronizacion');
            
            $mensaje = $respuesta === 'aceptado' ? 
                'Sincronización aceptada correctamente' : 
                'Solicitud rechazada';
                
            echo json_encode(['success' => true, 'message' => $mensaje]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    // Obtener calendarios sincronizados
    public function obtenerSincronizados() {
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
                SELECT 
                    cs.id,
                    cs.permisos,
                    cs.fecha_solicitud,
                    p.id as profesor_id,
                    u.nombres as profesor_nombre,
                    u.correo_electronico as profesor_correo,
                    'propietario' as tipo
                FROM calendario_sincronizacion cs
                JOIN profesores p ON cs.profesor_sincronizado_id = p.id
                JOIN usuarios u ON p.usuario_id = u.id
                WHERE cs.profesor_propietario_id = ? AND cs.estado = 'aceptado'
                
                UNION ALL
                
                SELECT 
                    cs.id,
                    cs.permisos,
                    cs.fecha_solicitud,
                    p.id as profesor_id,
                    u.nombres as profesor_nombre,
                    u.correo_electronico as profesor_correo,
                    'sincronizado' as tipo
                FROM calendario_sincronizacion cs
                JOIN profesores p ON cs.profesor_propietario_id = p.id
                JOIN usuarios u ON p.usuario_id = u.id
                WHERE cs.profesor_sincronizado_id = ? AND cs.estado = 'aceptado'
                
                ORDER BY fecha_solicitud DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$profesor_id, $profesor_id]);
            $sincronizados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode($sincronizados);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener sincronizaciones: ' . $e->getMessage()]);
        }
    }
    
    // Obtener profesores disponibles para sincronizar
    public function obtenerProfesoresDisponibles() {
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
                SELECT p.id, u.nombres, u.correo_electronico as correo
                FROM profesores p
                JOIN usuarios u ON p.usuario_id = u.id
                WHERE p.id != ? 
                  AND p.id NOT IN (
                      SELECT profesor_sincronizado_id 
                      FROM calendario_sincronizacion 
                      WHERE profesor_propietario_id = ? 
                        AND estado IN ('pendiente', 'aceptado')
                  )
                ORDER BY u.nombres
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$profesor_id, $profesor_id]);
            $profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode($profesores);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener profesores: ' . $e->getMessage()]);
        }
    }
    
    // Eliminar sincronización
    public function eliminarSincronizacion() {
        start_secure_session();
        require_role(2);
        $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
        
        try {
            $sincronizacion_id = $_POST['sincronizacion_id'] ?? $_GET['sincronizacion_id'];
            
            // Verificar permisos
            $sql_verificar = "
                SELECT * FROM calendario_sincronizacion 
                WHERE id = ? AND (
                    profesor_propietario_id = ? OR profesor_sincronizado_id = ?
                ) AND estado = 'aceptado'
            ";
            $stmt = $this->pdo->prepare($sql_verificar);
            $stmt->execute([$sincronizacion_id, $profesor_id, $profesor_id]);
            $sincronizacion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sincronizacion) {
                throw new Exception("Sincronización no encontrada o sin permisos");
            }
            
            // Eliminar sincronización
            $sql_eliminar = "DELETE FROM calendario_sincronizacion WHERE id = ?";
            $stmt = $this->pdo->prepare($sql_eliminar);
            $stmt->execute([$sincronizacion_id]);
            
            echo json_encode(['success' => true, 'message' => 'Sincronización eliminada']);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    // Obtener horarios de calendarios sincronizados
    public function obtenerHorariosSincronizados() {
        start_secure_session();
        require_role(2);
        $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
        
        try {
            $sql = "
                SELECT 
                    hf.*,
                    f.numero as ficha_codigo,
                    f.nombre as ficha_nombre,
                    u.nombres as profesor_nombre,
                    cs.permisos,
                    CASE 
                        WHEN hf.estado = 'programado' AND NOW() < hf.fecha_inicio THEN hf.color
                        WHEN hf.estado = 'en_curso' THEN '#28a745'
                        WHEN hf.estado = 'finalizado' THEN '#6c757d'
                        WHEN hf.estado = 'cancelado' THEN '#dc3545'
                        WHEN hf.estado = 'programado' AND NOW() > hf.fecha_fin THEN '#ffc107'
                        ELSE hf.color
                    END as color_actual
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
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$profesor_id, $profesor_id, $profesor_id]);
            $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatear para FullCalendar
            $eventos = [];
            foreach ($horarios as $horario) {
                $eventos[] = [
                    'id' => 'sync_' . $horario['id'], // Prefijo para identificar eventos sincronizados
                    'title' => $horario['titulo'] . ' (' . $horario['profesor_nombre'] . ')',
                    'start' => $horario['fecha_inicio'],
                    'end' => $horario['fecha_fin'],
                    'backgroundColor' => $horario['color_actual'],
                    'borderColor' => $horario['color_actual'],
                    'className' => 'evento-sincronizado',
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
            echo json_encode(['error' => 'Error al obtener horarios sincronizados: ' . $e->getMessage()]);
        }
    }
    
    // Crear notificación de solicitud de sincronización
    private function crearNotificacionSincronizacion($solicitud_id, $propietario_id, $sincronizado_id, $permisos) {
        // Obtener nombre del profesor propietario
        $sql_profesor = "
            SELECT p.nombres, u.correo 
            FROM profesores p 
            JOIN usuarios u ON p.usuario_id = u.id 
            WHERE p.id = ?
        ";
        $stmt = $this->pdo->prepare($sql_profesor);
        $stmt->execute([$propietario_id]);
        $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $permisos_texto = $permisos === 'solo_lectura' ? 'solo lectura' : 'lectura y escritura';
        
        $mensaje = "El profesor {$profesor['nombres']} quiere sincronizar su calendario contigo con permisos de {$permisos_texto}";
        
        $botones = json_encode([
            [
                'texto' => 'Aceptar',
                'accion' => 'aceptar_sincronizacion',
                'clase' => 'btn-success',
                'datos' => ['solicitud_id' => $solicitud_id, 'respuesta' => 'aceptado']
            ],
            [
                'texto' => 'Rechazar',
                'accion' => 'rechazar_sincronizacion',
                'clase' => 'btn-danger',
                'datos' => ['solicitud_id' => $solicitud_id, 'respuesta' => 'rechazado']
            ]
        ]);
        
        $sql_notif = "
            INSERT INTO notificaciones 
            (usuario_id, mensaje, tipo, referencia_id, referencia_tipo, botones, estado) 
            VALUES (
                (SELECT usuario_id FROM profesores WHERE id = ?), 
                ?, 'solicitud_sincronizacion', ?, 'solicitud_sincronizacion', ?, 'no_leida'
            )
        ";
        
        $stmt = $this->pdo->prepare($sql_notif);
        $stmt->execute([$sincronizado_id, $mensaje, $solicitud_id, $botones]);
    }
    
    // Crear notificación de respuesta
    private function crearNotificacionRespuesta($solicitud, $respuesta) {
        $sql_profesor = "
            SELECT p.nombres 
            FROM profesores p 
            WHERE p.id = ?
        ";
        $stmt = $this->pdo->prepare($sql_profesor);
        $stmt->execute([$solicitud['profesor_sincronizado_id']]);
        $profesor_respuesta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($respuesta === 'aceptado') {
            $mensaje = "¡{$profesor_respuesta['nombres']} ha aceptado sincronizar calendarios contigo!";
            $tipo = 'sincronizacion_aceptada';
        } else {
            $mensaje = "{$profesor_respuesta['nombres']} ha rechazado la solicitud de sincronización";
            $tipo = 'sincronizacion_rechazada';
        }
        
        $sql_notif = "
            INSERT INTO notificaciones 
            (usuario_id, mensaje, tipo, referencia_id, referencia_tipo, estado) 
            VALUES (
                (SELECT usuario_id FROM profesores WHERE id = ?), 
                ?, ?, ?, 'respuesta_sincronizacion', 'no_leida'
            )
        ";
        
        $stmt = $this->pdo->prepare($sql_notif);
        $stmt->execute([$solicitud['profesor_propietario_id'], $mensaje, $tipo, $solicitud['id']]);
    }
    
    // Marcar notificación como leída
    private function marcarNotificacionLeida($referencia_id, $tipo) {
        $sql = "
            UPDATE notificaciones 
            SET estado = 'leida' 
            WHERE referencia_id = ? AND referencia_tipo = ?
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$referencia_id, $tipo]);
    }
}
?>
