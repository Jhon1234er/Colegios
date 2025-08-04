<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../config/db.php';

class AuthController {
    public static function login($correo, $password) {
        require_once __DIR__ . '/../models/Usuario.php';
        $usuarioModel = new Usuario();
        $usuario = $usuarioModel->buscarPorCorreo($correo);

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            // Conexión a base de datos
            $pdo = Database::conectar();

            // Guarda datos básicos del usuario
            $_SESSION['usuario'] = [
                'id' => $usuario['id'],
                'rol_id' => $usuario['rol_id'],
                'nombres' => $usuario['nombres'],
                'apellidos' => $usuario['apellidos']
            ];

            // Si es profesor, buscar su ID en la tabla profesores
            if ($usuario['rol_id'] == 2) {
                $stmt = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
                $stmt->execute([$usuario['id']]);
                $profesor = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($profesor) {
                    $_SESSION['usuario']['profesor_id'] = $profesor['id'];
                }
            }

            return true;
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
    
    public function index() {
        $usuarioModel = new Usuario();
        $totalUsuarios = $usuarioModel->contarUsuarios();

        require 'views/dashboard.php'; 
    }
    
}


