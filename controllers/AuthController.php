<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../config/db.php';

class AuthController {
    public static function login($correo, $password) {
        $usuarioModel = new Usuario();
        $usuario = $usuarioModel->obtenerPorCorreo($correo);

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $_SESSION['usuario'] = $usuario;
            header('Location: /COLEGIOS/views/dashboard.php');
            exit;
        }

        return false;
    }

    public static function registrar($data) {
        if (empty($data['tipo_documento']) || empty($data['genero']) || empty($data['fecha_nacimiento'])) {
            return "Por favor selecciona un tipo de documento, género y fecha de nacimiento válidos.";
        }

        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);

        $usuarioModel = new Usuario();

        try {
            $pdo = Database::conectar();
            $pdo->beginTransaction();

            $usuario_id = $usuarioModel->registrar($data, $pdo);
            if (!$usuario_id) {
                throw new Exception("Error al insertar el usuario.");
            }

            if ($data['rol_id'] == 1) {
                $stmtAdmin = $pdo->prepare("INSERT INTO administradores (usuario_id, fecha_designacion) VALUES (?, CURDATE())");
                $stmtAdmin->execute([$usuario_id]);
            }

            $pdo->commit();
            header('Location: /COLEGIOS/views/login.php');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return "Error de registro: " . $e->getMessage();
        }
    }
}
