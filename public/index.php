<?php
require_once '../controllers/AuthController.php';
session_start();

$error = null;

// --- LOGIN ---
if (isset($_POST['login'])) {
    $resultado = AuthController::login($_POST['correo'], $_POST['password']);
    if (!$resultado) {
        $error = "Correo o contraseña incorrectos";
    } else {
        $rol = $_SESSION['usuario']['rol_id'];
        if ($rol == 1) {
            header('Location: /?page=dashboard');
        } elseif ($rol == 2) {
            header('Location: /?page=dashboard_profesor');
        } elseif ($rol == 3) {
            header('Location: /?page=dashboard_estudiante');
        } else {
            header('Location: /');
        }
        exit;
    }
}

// --- REGISTRO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registro'])) {
    AuthController::registrar($_POST);
    exit;
}

// --- API/ENDPOINTS (antes de mostrar vistas) ---

// Materias por colegio
if (isset($_GET['page']) && $_GET['page'] === 'materias_por_colegio' && isset($_GET['colegio_id'])) {
    require_once '../models/ColegioMateria.php';
    $colegioMateriaModel = new ColegioMateria();
    $materias = $colegioMateriaModel->obtenerMateriasPorColegio($_GET['colegio_id']);
    header('Content-Type: application/json');
    echo json_encode($materias);
    exit;
}

// Profesores por colegio
if (isset($_GET['page']) && $_GET['page'] === 'profesores_por_colegio' && isset($_GET['colegio_id'])) {
    require_once '../models/Profesor.php';
    $profesorModel = new Profesor();
    $profesores = $profesorModel->obtenerPorColegio($_GET['colegio_id']);
    header('Content-Type: application/json');
    echo json_encode($profesores);
    exit;
}

// Estudiantes por colegio
if (isset($_GET['page']) && $_GET['page'] === 'estudiantes_por_colegio' && isset($_GET['colegio_id'])) {
    require_once '../models/Estudiante.php';
    $estudianteModel = new Estudiante();
    $estudiantes = $estudianteModel->obtenerPorColegio($_GET['colegio_id']);
    header('Content-Type: application/json');
    echo json_encode($estudiantes);
    exit;
}
if (isset($_GET['page']) && $_GET['page'] === 'profesorficha') {
    require_once '../controllers/ProfesorController.php';

    // ✅ Solo iniciar sesión si aún no se ha iniciado
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // ✅ Verificar que el profesor_id exista en sesión
    if (isset($_SESSION['usuario']['profesor_id'])) {
        $controller = new ProfesorController();
        $controller->fichasPorProfesor($_SESSION['usuario']['profesor_id']);
    } else {
        // 🔴 No hay profesor_id en sesión → devolver error JSON
        header('Content-Type: application/json');
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Sesión inválida o profesor no identificado.']);
    }

    exit;
}

if (isset($_GET['page']) && $_GET['page'] === 'estudiantesporficha') {
    require_once '../controllers/EstudianteController.php';
    $controller = new EstudianteController();
    $ficha_id = $_GET['ficha_id'] ?? null;

    if ($ficha_id) {
        $controller->obtenerPorFicha($ficha_id);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Falta ficha_id']);
    }

    exit;
}


// --- VISTAS PRINCIPALES ---

// Materias
if (isset($_GET['page']) && $_GET['page'] === 'materias') {
    require_once '../controllers/MateriaController.php';
    $controller = new MateriaController();
    $action = $_GET['action'] ?? 'index';
    if ($action === 'editar' && isset($_GET['id'])) {
        $controller->editar();
    } elseif ($action === 'actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->actualizar();
    } elseif ($action === 'desactivar' && isset($_GET['id'])) {
        $controller->desactivar();
    } elseif ($action === 'activar' && isset($_GET['id'])) {
        $controller->activar();
    } elseif ($action === 'guardar_ficha' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->guardarFicha();
    } else {
        $controller->index();
    }
    exit;
}

if (isset($_GET['page']) && $_GET['page'] === 'crear_materia') {
    include '../views/Materia/crear.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/materias/guardar') {
    require_once '../controllers/MateriaController.php';
    $controller = new MateriaController();
    $controller->guardar();
    exit;
}

// Colegios
if (isset($_GET['page']) && $_GET['page'] === 'colegios') {
    require_once '../controllers/ColegioController.php';
    $controller = new ColegioController();
    $action = $_GET['action'] ?? 'index';
    if ($action === 'crear') {
        $controller->crear();
    } elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->guardar();
    } elseif ($action === 'eliminar' && isset($_GET['id'])) {
        $controller->eliminar();
    } else {
        $controller->index();
    }
    exit;
}

// Profesores
if (isset($_GET['page']) && $_GET['page'] === 'profesores') {
    require_once '../controllers/ProfesorController.php';
    $controller = new ProfesorController();
    $action = $_GET['action'] ?? 'index';
    if ($action === 'crear') {
        $controller->crear();
    } elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->guardar();
    } else {
        $controller->index();
    }
    exit;
}

// Estudiantes
if (isset($_GET['page']) && $_GET['page'] === 'estudiantes') {
    require_once '../controllers/EstudianteController.php';
    $controller = new EstudianteController();
    $action = $_GET['action'] ?? 'index';
    if ($action === 'crear') {
        $controller->crear();
    } elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->guardar();
    } else {
        $controller->index();
    }
    exit;
}

// --- DASHBOARDS Y VISTAS DE USUARIO ---

// Dashboard general (admin)
if (isset($_SESSION['usuario']) && $_SESSION['usuario']['rol_id'] == 1) {
    include '../views/dashboard.php';
    exit;
}

// Dashboard profesor
if (isset($_SESSION['usuario']) && $_SESSION['usuario']['rol_id'] == 2) {
    include '../views/Profesor/dashboard.php';
    exit;
}

// Dashboard estudiante
if (isset($_SESSION['usuario']) && $_SESSION['usuario']['rol_id'] == 3) {
    include '../views/Estudiante/dashboard.php';
    exit;
}

// Registro
if (isset($_GET['registro']) && $_GET['registro'] === 'true') {
    include '../views/registro.php';
    exit;
}

// Login (por defecto)
include '../views/login.php';
exit;

