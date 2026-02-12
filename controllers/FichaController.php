<?php
require_once __DIR__ . '/../config/db.php';

class FichaController {
    private $fichaModel;
    
    public function __construct() {
        require_once __DIR__ . '/../models/Ficha.php';
        $this->fichaModel = new Ficha();
    }
    
    public function index() {
        // Redirigir según el rol: profesores a Mis Fichas, admins al dashboard
        $rol_id = $_SESSION['usuario']['rol_id'] ?? 1;
        if ($rol_id === 2) {
            header("Location: /?page=dashboard_profesor");
        } else {
            header("Location: /?page=dashboard");
        }
        exit;
    }
    
    public function crear() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar();
            // Redirigir a Mis Fichas (dashboard_profesor) para profesores, o al listado para admins
            $rol_id = $_SESSION['usuario']['rol_id'] ?? 1;
            if ($rol_id === 2) {
                header("Location: /?page=dashboard_profesor");
            } else {
                header("Location: /?page=fichas&action=index");
            }
            exit;
        }
        require __DIR__ . '/../views/Ficha/crear_ficha.php';
    }
    
    public function guardar() {
        $data = $_POST;
        
        // Validaciones básicas
        if (empty($data['nombre']) || empty($data['numero']) || empty($data['cupo_total'])) {
            throw new Exception('Faltan datos obligatorios');
        }
        
        // Si hay ID, es una actualización, si no, es una creación
        if (!empty($data['id'])) {
            $this->fichaModel->actualizar($data['id'], $data);
        } else {
            // Obtener profesor_id de la sesión
            $usuario_id = $_SESSION['usuario']['id'] ?? null;
            $profesor_id = null;
            
            if ($usuario_id) {
                try {
                    require_once __DIR__ . '/../config/db.php';
                    $pdo = Database::conectar();
                    $stmt = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ? LIMIT 1");
                    $stmt->execute([$usuario_id]);
                    $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
                    $profesor_id = $profesor['id'] ?? null;
                } catch (Exception $e) {
                    // Si no hay profesor_id, continuar con null
                }
            }
            
            // Llamar al método guardar original con parámetros separados
            $this->fichaModel->guardar(
                $data['nombre'],
                $data['numero'],
                (int)$data['cupo_total'],
                $profesor_id,
                [], // días_semana - vacío por ahora
                $data['jornada'] ?? null
            );
        }
    }
    
    public function editar() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            header("Location: /?page=fichas&action=index");
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->fichaModel->actualizar($id, $_POST);
            // Redirigir a Mis Fichas (dashboard_profesor) para profesores, o al listado para admins
            $rol_id = $_SESSION['usuario']['rol_id'] ?? 1;
            if ($rol_id === 2) {
                header("Location: /?page=dashboard_profesor");
            } else {
                header("Location: /?page=fichas&action=index");
            }
            exit;
        }
        
        $ficha = $this->fichaModel->obtenerPorId($id);
        require __DIR__ . '/../views/Ficha/editar.php';
    }
    
    public function ver() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            header("Location: /?page=fichas&action=index");
            exit;
        }
        
        $ficha = $this->fichaModel->obtenerPorId($id);
        if (!$ficha) {
            header("Location: /?page=fichas&action=index");
            exit;
        }
        
        // Cargar aprendices de esta ficha
        require_once __DIR__ . '/../models/Aprendiz.php';
        $aprendizModel = new Aprendiz();
        $estudiantes = $aprendizModel->obtenerTodos($ficha['id']);

        $todasFichas = [];
        try {
            if (session_status() === PHP_SESSION_NONE) { start_secure_session(); }
            $rol = (int)($_SESSION['usuario']['rol_id'] ?? 0);
            if ($rol === 1) {
                $todasFichas = $this->fichaModel->obtenerTodas();
            }
        } catch (Throwable $e) {
            $todasFichas = [];
        }

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
            $stmt = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ? LIMIT 1");
            $stmt->execute([$usuarioActual]);
            $profesorLider = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profesorLider) {
                throw new Exception('Profesor líder no encontrado');
            }

            $profesorLiderId = $profesorLider['id'];

            // Obtener información de la ficha
            $stmt = $pdo->prepare("SELECT nombre FROM fichas WHERE id = ?");
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
                // Verificar si ya existe una relación
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM fichas_compartidas 
                    WHERE ficha_id = ? AND profesor_lider_id = ? AND profesor_compartido_id = ? 
                    AND estado_id IN (1,2)
                ");
                $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                
                if ($stmt->fetchColumn() > 0) {
                    continue; // Saltar este profesor si ya tiene la ficha
                }
                
                // Insertar solicitud pendiente
                $stmt = $pdo->prepare("
                    INSERT INTO fichas_compartidas 
                    (ficha_id, profesor_lider_id, profesor_compartido_id, estado_id) 
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
                
                $solicitudId = $pdo->lastInsertId();

                // Obtener usuario_id del profesor compartido
                $stmt = $pdo->prepare("SELECT usuario_id FROM facilitadores WHERE id = ?");
                $stmt->execute([$profesorId]);
                $usuarioCompartidoId = $stmt->fetchColumn();

                if ($usuarioCompartidoId > 0) {
                    // Crear notificación
                    $titulo = "Solicitud para compartir ficha";
                    $mensaje = "El facilitador {$nombreLider} quiere compartir la ficha {$ficha['nombre']} contigo.";
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, estado_id, creado_en) VALUES (?, ?, ?, 1, NOW())");
                        $stmt->execute([$usuarioCompartidoId, $titulo, $mensaje]);
                    } catch (\Throwable $ignored) {
                        // No interrumpir si falla la notificación
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Solicitudes de compartir enviadas exitosamente']);
            return;

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            return;
        }
    }

    public function verificarEstadoCompartir() {
        header('Content-Type: application/json; charset=utf-8');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $fichaId = $input['ficha_id'] ?? null;
        $profesores = $input['profesores'] ?? [];

        if (!$fichaId || empty($profesores) || !is_array($profesores)) {
            echo json_encode(new stdClass());
            return;
        }

        $usuarioActual = $_SESSION['usuario']['id'] ?? null;
        if (!$usuarioActual) {
            echo json_encode(new stdClass());
            return;
        }

        try {
            $pdo = Database::conectar();

            $stmt = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ? LIMIT 1");
            $stmt->execute([$usuarioActual]);
            $profesorLider = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profesorLider || empty($profesorLider['id'])) {
                echo json_encode(new stdClass());
                return;
            }

            $profesorLiderId = (int)$profesorLider['id'];
            $estados = [];

            foreach ($profesores as $profesorId) {
                $pid = (int)$profesorId;
                if ($pid <= 0) continue;

                $estado = 'disponible';
                $st = $pdo->prepare("SELECT estado_id FROM fichas_compartidas WHERE ficha_id = ? AND profesor_lider_id = ? AND profesor_compartido_id = ? AND estado_id IN (1,2) LIMIT 1");
                $st->execute([(int)$fichaId, $profesorLiderId, $pid]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['estado_id'])) {
                    $estado = ((int)$row['estado_id'] === 2) ? 'aceptada' : 'pendiente';
                }
                $estados[$pid] = $estado;
            }

            echo json_encode($estados);
            return;
        } catch (Throwable $e) {
            echo json_encode(new stdClass());
            return;
        }
    }
}
