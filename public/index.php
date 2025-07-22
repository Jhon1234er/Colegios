<?php
require_once '../controllers/AuthController.php';

$error = null;

// 🔐 Login
if (isset($_POST['login'])) {
    $resultado = AuthController::login($_POST['correo'], $_POST['password']);
    if (!$resultado) {
        $error = "Correo o contraseña incorrectos";
    }
}

// 📝 Registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registro'])) {
    AuthController::registrar($_POST);
    exit;
}

// 📄 Cargar vista según URL
if (isset($_GET['registro']) && $_GET['registro'] === 'true') {
    include '../views/registro.php';
} else {
    include '../views/login.php';
}
