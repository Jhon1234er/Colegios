<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../config/db.php';

class AuthController {

    // 👉 Muestra formulario de login y genera token CSRF
    public function loginForm() {
        start_secure_session();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        include __DIR__ . '/../views/login.php';
    }

    // 👉 Procesa login
    public static function login($correo, $password) {
        start_secure_session();
        csrf_validate(); // 🔒 valida token enviado

        // Throttling básico por sesión
        $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
        $_SESSION['login_blocked_until'] = $_SESSION['login_blocked_until'] ?? 0;

        if (time() < $_SESSION['login_blocked_until']) {
            return false;
        }

        $usuarioModel = new Usuario();
        $usuario = $usuarioModel->buscarPorCorreo($correo);

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            session_regenerate_id(true);

            $pdo = Database::conectar();

            $_SESSION['usuario'] = [
                'id'        => (int)$usuario['id'],
                'rol_id'    => (int)$usuario['rol_id'],
                'nombres'   => $usuario['nombres'],
                'apellidos' => $usuario['apellidos'],
            ];

            // Si es profesor, anexar IDs
            if ((int)$usuario['rol_id'] === 2) {
                $stmt = $pdo->prepare("SELECT id, tip_contrato FROM profesores WHERE usuario_id = ?");
                $stmt->execute([$usuario['id']]);
                $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($profesor) {
                    $_SESSION['usuario']['profesor_id']  = (int)$profesor['id'];
                    $_SESSION['usuario']['tip_contrato'] = $profesor['tip_contrato'];
                }
            }

            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_blocked_until'] = 0;
            return true;
        }

        // Fallo: sumar intento
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_blocked_until'] = time() + 15 * 60; // 15 min
        }
        return false;
    }

    // 👉 Registro
    public static function registrar($data) {
        start_secure_session();
        csrf_validate();

        if (empty($data['tipo_documento']) || empty($data['genero']) || empty($data['fecha_nacimiento'])) {
            return "Por favor selecciona un tipo de documento, género y fecha de nacimiento válidos.";
        }

        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);

        $usuarioModel = new Usuario();
        $pdo = Database::conectar();

        try {
            $pdo->beginTransaction();

            $usuario_id = $usuarioModel->registrar($data, $pdo);
            if (!$usuario_id) {
                throw new Exception("Error al insertar el usuario.");
            }

            if ((int)$data['rol_id'] === 1) {
                $stmtAdmin = $pdo->prepare("INSERT INTO administradores (usuario_id, fecha_designacion) VALUES (?, CURDATE())");
                $stmtAdmin->execute([$usuario_id]);
            }

            $pdo->commit();
            header('Location: /?page=login');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return "Error de registro: " . $e->getMessage();
        }
    }

    public function index() {
        $usuarioModel = new Usuario();
        $totalUsuarios = $usuarioModel->contarUsuarios();
        require 'views/dashboard.php';
    }
}
