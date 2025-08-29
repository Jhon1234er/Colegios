<?php
require_once __DIR__ . '/../models/Ficha.php';
require_once __DIR__ . '/../config/db.php';

class FichaController {
    private $fichaModel;

    public function __construct() {
        $this->fichaModel = new Ficha();
    }

    // 📌 Listar fichas solo del profesor logueado
    public function index() {
        // Asegurar sesión
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

            if (!empty($nombre) && !empty($numero) && $cupo_total > 0) {
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

                        $ficha_id = $this->fichaModel->guardar($nombre, $numero, $cupo_total, $profesor_id);

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

    // 📌 Ver ficha por ID
    public function ver() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            header("Location: /?page=fichas&action=index");
            exit;
        }

        $ficha = $this->fichaModel->obtenerPorId($id);
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
}
