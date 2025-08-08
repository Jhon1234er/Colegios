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

    public function editar() {
        if (!isset($_SESSION['usuario'])) {
            header("Location: /");
            exit;
        }

        $usuarioModel = new Usuario();
        $usuario = $usuarioModel->obtenerPorId($_SESSION['usuario']['id']);
        include __DIR__ . '/../views/Perfil/editar.php';
    }

    public function actualizar() {
        if (!isset($_SESSION['usuario'])) {
            header("Location: /");
            exit;
        }

        $usuarioModel = new Usuario();
        $usuarioModel->actualizar($_SESSION['usuario']['id'], $_POST);

        header("Location: /?page=ver_perfil");
        exit;
    }
}
