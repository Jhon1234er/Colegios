<?php
require_once __DIR__ . '/../models/Ficha.php';
require_once __DIR__ . '/../models/Estudiante.php';
require_once __DIR__ . '/../config/db.php';

class FichaController {
    private $fichaModel;

    public function __construct() {
        $this->fichaModel = new Ficha();
    }

    // 📌 Listar fichas solo del profesor logueado
    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $usuario_id = $_SESSION['usuario']['id'] ?? null;

        if (!$usuario_id) {
            die("⚠️ Error: No hay sesión de usuario activa.");
        }

        // Buscar profesor_id
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $profesor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profesor) {
            die("⚠️ Error: No existe un profesor vinculado a este usuario.");
        }

        $profesor_id = $profesor['id'];

        // 🔹 Obtener solo las fichas de este profesor
        $fichas = $this->fichaModel->obtenerTodasPorProfesor($profesor_id);

        require __DIR__ . '/../views/Ficha/crear_ficha.php';
    }

    // 📌 Mostrar formulario para crear ficha
    public function crear() {
        require __DIR__ . '/../views/Ficha/crear_ficha.php';
    }

    // 📌 Guardar nueva ficha
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre'] ?? '');
            $numero = trim($_POST['numero'] ?? '');
            $cupo_total = (int) ($_POST['cupo_total'] ?? 0);
            $dias_semana = $_POST['dias_semana'] ?? [];

            if (!empty($nombre) && !empty($numero) && $cupo_total > 0 && !empty($dias_semana)) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                $usuario_id = $_SESSION['usuario']['id'] ?? null;

                if ($usuario_id) {
                    $pdo = Database::conectar();
                    $stmt = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
                    $stmt->execute([$usuario_id]);
                    $profesor = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($profesor) {
                        $profesor_id = $profesor['id'];

                        $ficha_id = $this->fichaModel->guardar($nombre, $numero, $cupo_total, $profesor_id, $dias_semana);

                        if ($ficha_id) {
                            header("Location: /?page=fichas&action=index");
                            exit;
                        } else {
                            die("⚠️ Error: no se pudo guardar la ficha en la BD.");
                        }
                    } else {
                        die("⚠️ Error: No existe un profesor vinculado a este usuario.");
                    }
                } else {
                    die("⚠️ Error: No hay sesión de usuario activa.");
                }
            } else {
                die("⚠️ Error: Debes completar todos los campos.");
            }
        }

        header("Location: /?page=fichas&action=crear");
        exit;
    }

    // 📌 Ver ficha por ID con estudiantes
    public function ver() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            header("Location: /?page=fichas&action=index");
            exit;
        }

        $ficha = $this->fichaModel->obtenerPorId($id);

        if (!$ficha) {
            die("❌ Ficha no encontrada.");
        }

        // 🔹 Traer estudiantes de esta ficha
        $estudianteModel = new Estudiante();
        $estudiantes = $estudianteModel->obtenerTodos($ficha['id']);

        require __DIR__ . '/../views/Ficha/ver.php';
    }

    // 📌 Suspender (eliminar lógicamente) ficha
    public function eliminar() {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $this->fichaModel->actualizarEstado($id, 'cerrada');
        }
        header("Location: /?page=fichas&action=index");
        exit;
    }

    // 📌 Compartir ficha con otros profesores
    public function compartirFicha() {
        header('Content-Type: application/json');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $fichaId = $input['ficha_id'] ?? null;
        $profesores = $input['profesores'] ?? [];
        
        if (!$fichaId || empty($profesores)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $usuarioActual = $_SESSION['usuario']['id'] ?? null;
        if (!$usuarioActual) {
            echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
            return;
        }

        try {
            $pdo = Database::conectar();
            $pdo->beginTransaction();

            // Obtener profesor_id del usuario actual
            $stmt = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
            $stmt->execute([$usuarioActual]);
            $profesorLider = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profesorLider) {
                throw new Exception('Profesor líder no encontrado');
            }

            $profesorLiderId = $profesorLider['id'];

            // Obtener información de la ficha
            $stmt = $pdo->prepare("SELECT numero, nombre FROM fichas WHERE id = ?");
            $stmt->execute([$fichaId]);
            $ficha = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ficha) {
                throw new Exception('Ficha no encontrada');
            }

            // Obtener nombre del profesor líder
            $stmt = $pdo->prepare("SELECT nombres, apellidos FROM usuarios WHERE id = ?");
            $stmt->execute([$usuarioActual]);
            $usuarioLider = $stmt->fetch(PDO::FETCH_ASSOC);

            $nombreLider = $usuarioLider['nombres'] . ' ' . $usuarioLider['apellidos'];

            // Crear solicitudes pendientes y notificaciones
            foreach ($profesores as $profesorId) {
                // Verificar si ya existe una relación (pendiente o aceptada)
                $stmt = $pdo->prepare("
                    SELECT estado FROM fichas_compartidas 
                    WHERE ficha_id = ? AND profesor_lider_id = ? AND profesor_compartido_id = ? 
                    AND estado IN ('pendiente', 'aceptada')
                ");
                $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                $relacionExistente = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($relacionExistente) {
                    continue; // Saltar este profesor si ya tiene la ficha o hay solicitud pendiente
                }
                
                // Insertar solicitud pendiente en fichas_compartidas
                $stmt = $pdo->prepare("
                    INSERT INTO fichas_compartidas 
                    (ficha_id, profesor_lider_id, profesor_compartido_id, estado) 
                    VALUES (?, ?, ?, 'pendiente')
                ");
                $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                $solicitudId = $pdo->lastInsertId();

                // Obtener usuario_id del profesor receptor
                $stmt = $pdo->prepare("SELECT usuario_id FROM profesores WHERE id = ?");
                $stmt->execute([$profesorId]);
                $profesorReceptor = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($profesorReceptor && $solicitudId) {
                    // Crear notificación con botones de acción
                    $titulo = "Solicitud para compartir ficha: {$ficha['numero']}";
                    $mensaje = "El facilitador {$nombreLider} quiere compartir la ficha {$ficha['numero']} ({$ficha['nombre']}) contigo.";
                    
                    $datosAccion = json_encode([
                        'solicitud_id' => $solicitudId,
                        'ficha_id' => $fichaId,
                        'profesor_solicitante_id' => $profesorLiderId
                    ]);
                    
                    $botonesAccion = json_encode([
                        'aceptar' => 'Aceptar',
                        'rechazar' => 'Rechazar'
                    ]);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO notificaciones 
                        (usuario_id, tipo_usuario, tipo, titulo, mensaje, accion, datos_accion, botones_accion) 
                        VALUES (?, 'profesor', ?, ?, ?, 'compartir_ficha', ?, ?)
                    ");
                    $stmt->execute([
                        $profesorReceptor['usuario_id'],
                        'solicitud_compartir',
                        $titulo,
                        $mensaje,
                        $datosAccion,
                        $botonesAccion
                    ]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Solicitudes de compartir enviadas exitosamente']);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Verificar estado de compartir ficha para cada profesor
    public function verificarEstadoCompartir() {
        header('Content-Type: application/json');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $fichaId = $input['ficha_id'] ?? null;
        $profesores = $input['profesores'] ?? [];
        
        if (!$fichaId || empty($profesores)) {
            echo json_encode([]);
            return;
        }

        $usuarioActual = $_SESSION['usuario']['id'] ?? null;
        if (!$usuarioActual) {
            echo json_encode([]);
            return;
        }

        try {
            $pdo = Database::conectar();
            
            // Obtener profesor_id del usuario actual
            $stmt = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
            $stmt->execute([$usuarioActual]);
            $profesorLider = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profesorLider) {
                echo json_encode([]);
                return;
            }

            $profesorLiderId = $profesorLider['id'];
            $estados = [];

            // Verificar estado para cada profesor
            foreach ($profesores as $profesorId) {
                $stmt = $pdo->prepare("
                    SELECT estado FROM fichas_compartidas 
                    WHERE ficha_id = ? AND profesor_lider_id = ? AND profesor_compartido_id = ? 
                    AND estado IN ('pendiente', 'aceptada')
                ");
                $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                $relacion = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $estados[$profesorId] = $relacion ? $relacion['estado'] : 'disponible';
            }

            echo json_encode($estados);

        } catch (Exception $e) {
            echo json_encode([]);
        }
    }

    // Responder a solicitud de compartir ficha
    public function responder_solicitud() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $solicitudId = $input['solicitud_id'] ?? null;
        $respuesta = $input['respuesta'] ?? null; // 'aceptar' o 'rechazar'

        if (!$solicitudId || !in_array($respuesta, ['aceptar', 'rechazar'])) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            return;
        }

        try {
            $pdo = Database::conectar();
            $pdo->beginTransaction();

            // Obtener datos de la solicitud
            $stmt = $pdo->prepare("
                SELECT s.*, f.numero, f.nombre as ficha_nombre,
                       u1.nombres as solicitante_nombres, u1.apellidos as solicitante_apellidos,
                       u2.nombres as receptor_nombres, u2.apellidos as receptor_apellidos,
                       p1.usuario_id as solicitante_usuario_id
                FROM fichas_compartidas s
                JOIN fichas f ON s.ficha_id = f.id
                JOIN profesores p1 ON s.profesor_lider_id = p1.id
                JOIN profesores p2 ON s.profesor_compartido_id = p2.id
                JOIN usuarios u1 ON p1.usuario_id = u1.id
                JOIN usuarios u2 ON p2.usuario_id = u2.id
                WHERE s.id = ? AND s.estado = 'pendiente'
            ");
            $stmt->execute([$solicitudId]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                throw new Exception('Solicitud no encontrada o ya procesada');
            }

            if ($respuesta === 'aceptar') {
                // Actualizar estado de solicitud
                $stmt = $pdo->prepare("
                    UPDATE fichas_compartidas 
                    SET estado = 'aceptada', fecha_respuesta = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$solicitudId]);

                // Notificar al solicitante sobre la aceptación
                $titulo = "Ficha aceptada: {$solicitud['numero']}";
                $mensaje = "El facilitador {$solicitud['receptor_nombres']} {$solicitud['receptor_apellidos']} ha aceptado compartir la ficha {$solicitud['numero']} ({$solicitud['ficha_nombre']}).";
                
            } else {
                // Actualizar estado de solicitud
                $stmt = $pdo->prepare("
                    UPDATE fichas_compartidas 
                    SET estado = 'rechazada', fecha_respuesta = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$solicitudId]);

                // Notificar al solicitante sobre el rechazo
                $titulo = "Ficha rechazada: {$solicitud['numero']}";
                $mensaje = "El facilitador {$solicitud['receptor_nombres']} {$solicitud['receptor_apellidos']} ha rechazado compartir la ficha {$solicitud['numero']} ({$solicitud['ficha_nombre']}).";
            }

            // Marcar la notificación original como leída (procesada)
            $stmt = $pdo->prepare("
                UPDATE notificaciones 
                SET estado = 'leida' 
                WHERE accion = 'compartir_ficha' 
                AND JSON_EXTRACT(datos_accion, '$.solicitud_id') = ?
            ");
            $stmt->execute([$solicitudId]);

            // Crear notificación para el solicitante
            $stmt = $pdo->prepare("
                INSERT INTO notificaciones 
                (usuario_id, tipo_usuario, tipo, titulo, mensaje) 
                VALUES (?, 'profesor', ?, ?, ?)
            ");
            $stmt->execute([
                $solicitud['solicitante_usuario_id'],
                'respuesta_compartir',
                $titulo,
                $mensaje
            ]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Respuesta procesada exitosamente']);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
