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

// ====== REGISTRO USUARIO GENERAL ======
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

// 📌 NUEVO: Grados y jornadas por colegio
if ($page === 'info_colegio' && isset($_GET['colegio_id'])) {
    require_once '../models/Colegio.php';
    $colegioModel = new Colegio();
    $colegio = $colegioModel->obtenerPorId($_GET['colegio_id']);

    header('Content-Type: application/json');
    if ($colegio) {
        echo json_encode([
            'grados'   => array_map('trim', explode(',', $colegio['grados'] ?? '')),
            'jornadas' => array_map('trim', explode(',', $colegio['jornada'] ?? ''))
        ]);
    } else {
        echo json_encode(['grados' => [], 'jornadas' => []]);
    }
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
    require_login();
    require_role(2);
    require_once '../controllers/ProfesorController.php';
    $controller = new ProfesorController();
    $controller->obtenerFichasPorProfesor(); 
    exit;
}

// Obtener todos los profesores (para compartir fichas)
if ($page === 'obtener_profesores') {
    require_login();
    require_role(2);
    require_once '../models/Profesor.php';
    $profesorModel = new Profesor();
    $profesorActual = $_SESSION['usuario']['id'];
    header('Content-Type: application/json');
    echo json_encode($profesorModel->obtenerTodosExcepto($profesorActual));
    exit;
}

// Compartir ficha con otros profesores
if ($page === 'compartir_ficha' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role(2);
    require_once '../controllers/FichaController.php';
    $controller = new FichaController();
    $controller->compartirFicha();
    exit;
}

// Verificar estado de compartir ficha
if ($page === 'verificar_estado_compartir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role(2);
    require_once '../controllers/FichaController.php';
    $controller = new FichaController();
    $controller->verificarEstadoCompartir();
    exit;
}

// Responder a solicitud de compartir ficha
if ($page === 'responder_solicitud' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role(2);
    require_once '../controllers/FichaController.php';
    $controller = new FichaController();
    $controller->responder_solicitud();
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

// ====== ENDPOINTS DE CALENDARIO ======
// Obtener horarios para calendario
if ($page === 'calendario_obtener') {
    start_secure_session();
    require_role(2);
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->obtenerHorarios();
    exit;
}

// Obtener fichas disponibles
if ($page === 'calendario_obtener_fichas') {
    start_secure_session();
    require_role(2);
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->obtenerFichasDisponibles();
    exit;
}

// Crear nuevo horario
if ($page === 'calendario_crear') {
    start_secure_session();
    require_role(2);
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->crearHorario();
    exit;
}

// Actualizar horario existente
if ($page === 'calendario_actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->actualizarHorario();
    exit;
}

// Eliminar horario
if ($page === 'calendario_eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session();
    require_role(2);
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->eliminarHorario();
    exit;
}

// Cambiar estado de horario
if ($page === 'calendario_estado' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session();
    require_role(2);
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->cambiarEstado();
    exit;
}

// ====== ENDPOINTS DE SINCRONIZACIÓN ======
// Solicitar sincronización de calendario
if ($page === 'sincronizar_calendario' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session();
    require_role(2);
    require_once '../controllers/SincronizacionController.php';
    $controller = new SincronizacionController();
    $controller->solicitarSincronizacion();
    exit;
}

// Responder solicitud de sincronización
if ($page === 'responder_sincronizacion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session();
    require_role(2);
    require_once '../controllers/SincronizacionController.php';
    $controller = new SincronizacionController();
    $controller->responderSolicitud();
    exit;
}

// Obtener calendarios sincronizados
if ($page === 'calendarios_sincronizados' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    start_secure_session();
    require_role(2);
    require_once '../controllers/SincronizacionController.php';
    $controller = new SincronizacionController();
    $controller->obtenerSincronizados();
    exit;
}

// Obtener profesores disponibles para sincronizar
if ($page === 'profesores_sincronizar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    start_secure_session();
    require_role(2);
    require_once '../controllers/SincronizacionController.php';
    $controller = new SincronizacionController();
    $controller->obtenerProfesoresDisponibles();
    exit;
}

// Eliminar sincronización
if ($page === 'eliminar_sincronizacion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session();
    require_role(2);
    require_once '../controllers/SincronizacionController.php';
    $controller = new SincronizacionController();
    $controller->eliminarSincronizacion();
    exit;
}

// Obtener horarios de calendarios sincronizados
if ($page === 'horarios_sincronizados' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    start_secure_session();
    require_role(2);
    require_once '../controllers/SincronizacionController.php';
    $controller = new SincronizacionController();
    $controller->obtenerHorariosSincronizados();
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

// Guardar asistencia - Funcionalidad removida
// if ($page === 'guardar_asistencia' && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once '../controllers/TareaController.php';
//     TareaController::guardarAsistencias();
//     exit;
// }

// Vista previa de estudiantes por colegio/ficha
if ($page === 'preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/db.php';

    $colegioId = $_POST['colegio_id'] ?? null;
    $fichas    = $_POST['fichas'] ?? [];

    if (!$colegioId) { exit; }

    $pdo = Database::conectar();

    if (!empty($fichas)) {
        $in  = str_repeat('?,', count($fichas) - 1) . '?';
        $sql = "SELECT CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo,
                       u.numero_documento,
                       f.nombre AS ficha,
                       e.jornada,
                       e.estado
                FROM estudiantes e
                INNER JOIN usuarios u ON e.usuario_id = u.id
                INNER JOIN fichas f ON e.ficha_id = f.id
                WHERE e.colegio_id = ? AND e.ficha_id IN ($in)
                ORDER BY f.nombre, nombre_completo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$colegioId], $fichas));
    } else {
        $sql = "SELECT CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo,
                       u.numero_documento,
                       f.nombre AS ficha,
                       e.jornada,
                       e.estado
                FROM estudiantes e
                INNER JOIN usuarios u ON e.usuario_id = u.id
                INNER JOIN fichas f ON e.ficha_id = f.id
                WHERE e.colegio_id = ?
                ORDER BY f.nombre, nombre_completo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$colegioId]);
    }

    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($estudiantes)) {
        echo "<tr><td colspan='5'>⚠️ No hay estudiantes en este colegio/fichas</td></tr>";
    } else {
        foreach ($estudiantes as $e): ?>
            <tr>
              <td><?= htmlspecialchars($e['nombre_completo']) ?></td>
              <td><?= htmlspecialchars($e['numero_documento']) ?></td>
              <td><?= htmlspecialchars($e['ficha']) ?></td>
              <td><?= htmlspecialchars($e['jornada']) ?></td>
              <td><?= htmlspecialchars($e['estado']) ?></td>
            </tr>
        <?php endforeach;
    }

    exit;
}

// ===== GENERAR EXCEL/PDF ======
if ($page === 'generar_excel') {
    require __DIR__ . '/../views/Archivos/generar_excel.php';
    exit;
}

if ($page === 'generar_pdf') {
    require __DIR__ . '/../views/Archivos/generar_pdf.php';
    exit;
}

// ====== REGISTRO (vista pública con token) ======
if ($page === 'registro_estudiante') {
    require_once '../controllers/EstudianteController.php';
    $c = new EstudianteController();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $c->guardarPublico($_POST);
        exit;
    } else {
        include '../views/Estudiante/registro.php';
        exit;
    }
}

// ====== REGISTRO GENERAL ======
if ($page === 'registro') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_validate();
        AuthController::registrar($_POST);
        exit;
    } else {
        include '../views/registro.php';
        exit;
    }
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
    require_login(); 
    require_role([1,2]);
    require_once '../controllers/EstudianteController.php';
    $c = new EstudianteController();
    $action = $_GET['action'] ?? 'index';
    if ($action === 'crear')          $c->crear();
    elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') { csrf_validate(); $c->guardar(); }
    else $c->index();
    exit;
}

if ($page === 'crear_materia') {
    require_login(); 
    require_role(1);
    require_once '../controllers/MateriaController.php';
    $c = new MateriaController();
    $c->crear();
    exit;
}

if ($page === 'fichas') {
    require_login(); 
    require_role([1, 2]); 
    require_once '../controllers/FichaController.php';
    $c = new FichaController();
    $action = $_GET['action'] ?? 'index';

    if ($action === 'crear') {
        $c->crear();
    } elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_validate();
        $c->guardar();
    } elseif ($action === 'ver' && isset($_GET['id'])) {
        $c->ver();
    } elseif ($action === 'eliminar' && isset($_GET['id'])) {
        $c->eliminar();
    } else {
        $c->index();
    }
    exit;
}

// Perfil
if ($page === 'ver_perfil')   { require_login(); require_once '../controllers/PerfilController.php'; (new PerfilController())->ver(); exit; }
if ($page === 'actualizar_perfil'){ require_login(); require_once '../controllers/PerfilController.php'; csrf_validate(); (new PerfilController())->actualizar(); exit; }

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

    include '../views/Archivos/resultados_busqueda.php';
    exit;
}

// ====== ENDPOINT PREVIEW ======
if ($page === 'preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role([1, 2]);
    include '../views/Archivos/preview.php';
    exit;
}

// ====== CALENDARIO ======
if ($page === 'calendario') {
    require_login();
    require_role(2);
    include '../views/Calendario/calendario.php';
    exit;
}

// ====== DASHBOARDS SEGÚN ROL ======
if (!empty($_SESSION['usuario'])) {
    if ((int)$_SESSION['usuario']['rol_id'] === 1) { include '../views/dashboard.php'; exit; }
    if ((int)$_SESSION['usuario']['rol_id'] === 2) { include '../views/Profesor/dashboard.php'; exit; }
}

// ====== LOGIN (por defecto) ======
$c = new AuthController();
$c->loginForm();
exit;
