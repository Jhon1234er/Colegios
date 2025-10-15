<?php
require_once __DIR__ . '/../models/Usuario.php';

class PerfilController {
    public function ver() {
        if (!isset($_SESSION['usuario'])) {
            header("Location: /");
            exit;
        }

        $usuarioModel = new Usuario();
        $usuario = $usuarioModel->obtenerPorId($_SESSION['usuario']['id']);
        
        include __DIR__ . '/../views/Perfil/ver.php';
    }


    public function actualizar() {
        if (!isset($_SESSION['usuario'])) {
            header("Location: /");
            exit;
        }

        $usuarioModel = new Usuario();
        
        // Si es actualización de contacto (AJAX)
        if (isset($_POST['action']) && $_POST['action'] === 'update_contact') {
            $datos = [];
            if (isset($_POST['correo_electronico'])) {
                $datos['correo_electronico'] = $_POST['correo_electronico'];
            }
            if (isset($_POST['correo_institucional'])) {
                $datos['correo_institucional'] = $_POST['correo_institucional'];
            }
            if (isset($_POST['telefono'])) {
                $datos['telefono'] = $_POST['telefono'];
            }
            
            $resultado = $usuarioModel->actualizar($_SESSION['usuario']['id'], $datos);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => $resultado]);
            exit;
        }

        /**
         * FUNCIONALIDAD AGREGADA: Cambio de contraseña vía AJAX
         */
        if (isset($_POST['action']) && $_POST['action'] === 'cambiar_password') {
            $usuarioId = $_SESSION['usuario']['id'];
            $passwordActual = $_POST['password_actual'] ?? '';
            $passwordNueva = $_POST['password_nueva'] ?? '';
            
            // SEGURIDAD: Verificar contraseña actual antes de permitir el cambio
            $usuario = $usuarioModel->obtenerPorId($usuarioId);
            if (!password_verify($passwordActual, $usuario['password_hash'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
                exit;
            }
            
            // ACTUALIZACIÓN: Hashear nueva contraseña y guardar en BD
            $passwordHash = password_hash($passwordNueva, PASSWORD_DEFAULT);
            $resultado = $usuarioModel->actualizarPassword($usuarioId, $passwordHash);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => $resultado, 'message' => $resultado ? 'Contraseña actualizada correctamente' : 'Error al actualizar la contraseña']);
            exit;
        }

        // Actualización normal del perfil
        $usuarioModel->actualizar($_SESSION['usuario']['id'], $_POST);
        header("Location: /?page=ver_perfil");
        exit;
    }
}
