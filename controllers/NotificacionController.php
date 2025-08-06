<?php
declare(strict_types=1); // ¡Debe ser la primera declaración después de <?php!
require_once __DIR__ . '/../config/db.php';

class NotificacionController {
    private $pdo; // Añade esta propiedad para la conexión PDO

    public function __construct() {
        $this->pdo = Database::conectar(); // Inicializa la conexión en el constructor
    }

    public function marcarLeida() {
        // Asegúrate de que la sesión ya esté iniciada en public/index.php
        // Esta comprobación es redundante si session_start() está al principio de index.php,
        // pero no causa daño.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header('Content-Type: application/json'); // ¡Esta línea es crucial y debe ser la primera salida!

        if (!isset($_SESSION['usuario'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'No autenticado']);
            exit;
        }

        $usuario_id = $_SESSION['usuario']['id'];
        $rol_id = $_SESSION['usuario']['rol_id'] ?? null;

        $roles = [
            1 => 'administrador',
            2 => 'profesor',
            3 => 'estudiante',
            4 => 'rector'
        ];

        $tipo_usuario = $roles[$rol_id] ?? null;

        if (!$tipo_usuario) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Tipo de usuario no válido']);
            exit;
        }

        try {
            // Usa la conexión PDO que se inicializó en el constructor
            $pdo = $this->pdo;

            // Si se envía un ID específico
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notificacion_id'])) {
                $id = intval($_POST['notificacion_id']);

                $stmt = $pdo->prepare("UPDATE notificaciones SET estado = 'leida' WHERE id = ? AND usuario_id = ? AND tipo_usuario = ?");
                $stmt->execute([$id, $usuario_id, $tipo_usuario]);

                // Consultar cuántas no leídas quedan
                $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND tipo_usuario = ? AND estado = 'no_leida'");
                $stmtCount->execute([$usuario_id, $tipo_usuario]);
                $restantes = (int) $stmtCount->fetchColumn();

                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Notificación marcada como leída',
                    'restantes' => $restantes
                ]);
                exit;
            } else {
                // No se envió ID: marcar todas como leídas (opcional)
                $stmt = $pdo->prepare("UPDATE notificaciones SET estado = 'leida' WHERE usuario_id = ? AND tipo_usuario = ?");
                $stmt->execute([$usuario_id, $tipo_usuario]);

                echo json_encode([
                    'success' => true,
                    'mensaje' => 'Todas las notificaciones marcadas como leídas'
                ]);
                exit;
            }
        } catch (PDOException $e) {
            error_log("Error al actualizar notificaciones: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
            exit;
        }
    }
}
