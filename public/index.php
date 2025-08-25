<?php
require_once '../helpers/auth.php';
require_once '../controllers/AuthController.php';

start_secure_session();

$error = null;

// ====== LOGIN ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    csrf_validate();
    $ok = AuthController::login($_POST['correo'] ?? '', $_POST['password'] ?? '');
    if (!$ok) {
        $error = "Correo o contraseña incorrectos";
    } else {
        $rol = (int)$_SESSION['usuario']['rol_id'];
        if ($rol === 1) {
            header('Location: /?page=dashboard');
        } elseif ($rol === 2) {
            header('Location: /?page=dashboard_profesor');
        } else {
            header('Location: /');
        }
        exit;
    }
}

// ====== REGISTRO ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registro'])) {
    csrf_validate();
    AuthController::registrar($_POST);
    exit;
}

// ====== ENDPOINTS PÚBLICOS (AJAX lean) ======
$page = $_GET['page'] ?? null;

// Materias por colegio
if ($page === 'materias_por_colegio' && isset($_GET['colegio_id'])) {
    require_once '../models/ColegioMateria.php';
    $colegioMateriaModel = new ColegioMateria();
    header('Content-Type: application/json');
    echo json_encode($colegioMateriaModel->obtenerMateriasPorColegio($_GET['colegio_id']));
    exit;
}

// Profesores por colegio
if ($page === 'profesores_por_colegio' && isset($_GET['colegio_id'])) {
    require_once '../models/Profesor.php';
    $profesorModel = new Profesor();
    header('Content-Type: application/json');
    echo json_encode($profesorModel->obtenerPorColegio($_GET['colegio_id']));
    exit;
}

// Estudiantes por colegio
if ($page === 'estudiantes_por_colegio' && isset($_GET['colegio_id'])) {
    require_once '../models/Estudiante.php';
    $estudianteModel = new Estudiante();
    header('Content-Type: application/json');
    echo json_encode($estudianteModel->obtenerPorColegio($_GET['colegio_id']));
    exit;
}

// Fichas del profesor autenticado
if ($page === 'profesorficha') {
    require_login(); require_role(2);
    require_once '../controllers/ProfesorController.php';
    $controller = new ProfesorController();
    $controller->fichasPorProfesor($_SESSION['usuario']['profesor_id']);
    exit;
}

// Estudiantes por ficha
if ($page === 'estudiantesporficha') {
    require_login();
    require_once '../controllers/EstudianteController.php';
    $controller = new EstudianteController();
    $ficha_id = $_GET['ficha_id'] ?? null;
    header('Content-Type: application/json');
    echo $ficha_id ? $controller->obtenerPorFicha($ficha_id) : json_encode(['error' => 'Falta ficha_id']);
    exit;
}

// Marcar notificación como leída
if ($page === 'marcar_notificacion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_once '../controllers/NotificacionController.php';
    $controller = new NotificacionController();
    $controller->marcarLeida();
    exit;
}

// ====== RUTAS PROTEGIDAS (VISTAS) ======
if ($page === 'colegios') {
    require_login(); require_role(1);
    require_once '../controllers/ColegioController.php';
    $c = new ColegioController();
    $action = $_GET['action'] ?? 'index';
    if ($action === 'crear')          $c->crear();
    elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') { csrf_validate(); $c->guardar(); }
    elseif ($action === 'eliminar' && isset($_GET['id'])) $c->eliminar();
    else $c->index();
    exit;
}

if ($page === 'profesores') {
    require_login(); require_role(1);
    require_once '../controllers/ProfesorController.php';
    $c = new ProfesorController();
    $action = $_GET['action'] ?? 'index';
    if ($action === 'crear')          $c->crear();
    elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') { csrf_validate(); $c->guardar(); }
    else $c->index();
    exit;
}

if ($page === 'estudiantes') {
    require_login(); require_role([1]); // ajusta si estudiantes también pueden ver algo
    require_once '../controllers/EstudianteController.php';
    $c = new EstudianteController();
    $action = $_GET['action'] ?? 'index';
    if ($action === 'crear')          $c->crear();
    elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') { csrf_validate(); $c->guardar(); }
    else $c->index();
    exit;
}

// Perfil
if ($page === 'ver_perfil')   { require_login(); require_once '../controllers/PerfilController.php'; (new PerfilController())->ver(); exit; }
if ($page === 'editar_perfil'){ require_login(); require_once '../controllers/PerfilController.php'; $ctl=new PerfilController(); if ($_SERVER['REQUEST_METHOD']==='POST'){ csrf_validate(); $ctl->actualizar(); } else { $ctl->editar(); } exit; }

// ====== DASHBOARDS O BÚSQUEDA AJAX ======
if ($page === 'dashboard' && isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    require_login();
    require_role(1);

    $filtro = $_GET['filtro'] ?? 'colegio';
    $query = $_GET['q'] ?? '';
    $resultados = [];

    switch ($filtro) {
        case 'colegio':
            require_once '../models/Colegio.php';
            $resultados = (new Colegio())->buscarPorNombre($query);
            break;
        case 'profesor':
            require_once '../models/Profesor.php';
            $resultados = (new Profesor())->buscarPorNombre($query);
            break;
        case 'estudiante':
            require_once '../models/Estudiante.php';
            $resultados = (new Estudiante())->buscarPorNombre($query);
            break;
    }

    // Cargar una vista solo para los resultados
    include '../views/Archivos/resultados_busqueda.php';
    exit;
}

if (!empty($_SESSION['usuario'])) {
    if ((int)$_SESSION['usuario']['rol_id'] === 1) { include '../views/dashboard.php'; exit; }
    if ((int)$_SESSION['usuario']['rol_id'] === 2) { include '../views/Profesor/dashboard.php'; exit; }
}

// ====== REGISTRO (vista) ======
if (isset($_GET['registro']) && $_GET['registro'] === 'true') {
    include '../views/registro.php';
    exit;
}

// ====== LOGIN (por defecto) ======
// ====== LOGIN (por defecto) ======
require_once '../controllers/AuthController.php';
$c = new AuthController();
$c->loginForm();
exit;