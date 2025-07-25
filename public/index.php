<?php
require_once '../controllers/AuthController.php';
session_start();

$error = null;

// Login
if (isset($_POST['login'])) {
    $resultado = AuthController::login($_POST['correo'], $_POST['password']);
    if (!$resultado) {
        $error = "Correo o contraseña incorrectos";
    } else {
        header('Location: /');
        exit;
    }
}

// Registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registro'])) {
    AuthController::registrar($_POST);
    exit;
}

// Materias
if (isset($_GET['page']) && $_GET['page'] === 'materias') {
    include '../views/Materia/lista.php';
    exit;
}

if (isset($_GET['page']) && $_GET['page'] === 'crear_materia') {
    include '../views/Materia/crear.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/materias/guardar') {
    require_once '../controllers/MateriaController.php';
    $controller = new MateriaController();
    $controller->guardar(); // ✅
    exit;
}

//Colegios
if (isset($_GET['page']) && $_GET['page'] === 'colegios') {
    require_once '../controllers/ColegioController.php';
    $controller = new ColegioController();

    $action = $_GET['action'] ?? 'index'; // si no hay action, va a index()

    if ($action === 'crear') {
        $controller->crear();
        exit;
    } elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->guardar();
        exit;
    } elseif ($action === 'eliminar' && isset($_GET['id'])) {
        $controller->eliminar();
        exit;
    } else {
        $controller->index();
        exit;
    }
}

//Profesores
if (isset($_GET['page']) && $_GET['page'] === 'profesores') {
    require_once '../controllers/ProfesorController.php';
    $controller = new ProfesorController();

    $action = $_GET['action'] ?? 'index';

    if ($action === 'crear') {
        $controller->crear();
        exit;
    } elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->guardar();
        exit;
    } else {
        $controller->index();
        exit;
    }
}
// API para obtener materias por colegio
if (isset($_GET['page']) && $_GET['page'] === 'materias_por_colegio' && isset($_GET['colegio_id'])) {
    require_once '../models/ColegioMateria.php'; // ✅ SOLUCIONADO
    $colegioMateriaModel = new ColegioMateria();
    $materias = $colegioMateriaModel->obtenerMateriasPorColegio($_GET['colegio_id']);
    header('Content-Type: application/json');
    echo json_encode($materias);
    exit;
}
if (isset($_GET['page']) && $_GET['page'] === 'estudiantes') {
    require_once '../controllers/EstudianteController.php';
    $controller = new EstudianteController();

    $action = $_GET['action'] ?? 'index';

    if ($action === 'crear') {
        $controller->crear();
        exit;
    } elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->guardar();
        exit;
    } else {
        $controller->index();
        exit;
    }
}

// API para obtener profesores por colegio
if (isset($_GET['page']) && $_GET['page'] === 'profesores_por_colegio' && isset($_GET['colegio_id'])) {
    require_once '../models/Profesor.php';
    $profesorModel = new Profesor();
    $profesores = $profesorModel->obtenerPorColegio($_GET['colegio_id']);
    header('Content-Type: application/json');
    echo json_encode($profesores);
    exit;
}

    if (isset($_GET['page']) && $_GET['page'] === 'estudiantes_por_colegio' && isset($_GET['colegio_id'])) {
        require_once '../models/Estudiante.php';
        $estudianteModel = new Estudiante();
        $estudiantes = $estudianteModel->obtenerPorColegio($_GET['colegio_id']);
        header('Content-Type: application/json');
        echo json_encode($estudiantes);
        exit;
    }

// ✅ Si ya está autenticado, mostrar dashboard
if (isset($_SESSION['usuario'])) {
    include '../views/dashboard.php';
    exit;
}

// 🧾 Mostrar registro si se pidió
if (isset($_GET['registro']) && $_GET['registro'] === 'true') {
    include '../views/registro.php';
    exit;
}

// 🔒 Si no hay sesión, mostrar login
include '../views/login.php';
