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

        // Actualización normal del perfil
        $usuarioModel->actualizar($_SESSION['usuario']['id'], $_POST);
        header("Location: /?page=ver_perfil");
        exit;
    }
}
