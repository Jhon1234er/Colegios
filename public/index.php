<?php
// Mostrar todos los errores en pantalla (solo para desarrollo)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
require_once '../helpers/auth.php';
require_once '../controllers/AuthController.php';

// Autoload de Composer (PhpSpreadsheet, Dompdf, etc.)
// Cargar autoload solo si existe, para evitar error fatal cuando no está instalado
$__autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($__autoload)) {
    require_once $__autoload;
} else {
    // Registrar aviso en el log; algunas funciones (PDF/Excel) no estarán disponibles
    error_log('Aviso: vendor/autoload.php no encontrado. Ejecuta "composer install" en la raíz del proyecto.');
}
start_secure_session();

$page = $_GET['page'] ?? null; // ensure $page is defined before any use

$error = null;

// ====== MENU ITEMS ======
$menuItems = [
    [
        'title' => 'Inicio',
        'icon' => 'fa-home',
        'url' => '?page=dashboard',
        'roles' => [1, 2, 3] // Admin, facilitador, aprendiz 
    ],
    [
        'title' => 'Reportes',
        'icon' => 'fa-chart-bar',
        'url' => '?page=reportes',
        'roles' => [1, 2, 3] // Admin, facilitador, aprendiz
    ],
    [
        'title' => 'Asistente',
        'icon' => 'fa-user-shield',
        'url' => '?page=asistente',
        'roles' => [1, 4] // Admin y Asistente
    ],
];

// ====== LOGIN ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // CSRF se valida dentro de AuthController::login
    $ok = AuthController::login($_POST['correo'] ?? '', $_POST['password'] ?? '');
    if (!$ok) {
        // PRG: redirigir para evitar reenvío del formulario y mostrar flash de error
        header('Location: ?page=login');
        exit;
    } else {
        $rol = (int)$_SESSION['usuario']['rol_id'];
        if ($rol === 1) {
            header('Location: ?page=dashboard');
        } elseif ($rol === 2) {
            header('Location: ?page=dashboard_profesor');
        } elseif ($rol === 4) {
            header('Location: ?page=asistente');
        } else {
            header('Location: ?');
        }
        exit;
    }
}

// ====== REGISTRO PÚBLICO DE APRENDIZ PENDIENTE (sin ficha) ======
if ($page === 'registro_aprendiz_pendiente') {
    require_once '../controllers/AprendizController.php';
    $c = new AprendizController();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $c->guardarPendientePublico();
        exit;
    } else {
        $c->formularioPendientePublico();
        exit;
    }
}

// ====== Endpoints de asistencias para el tablero del facilitador ======
if ($page === 'obtener_asistencias') {
    if (!ob_get_level()) { ob_start(); }
    ini_set('display_errors', '0');
    try {
        require_login(); 
        require_role(2);
        require_once '../controllers/AsistenciaController.php';
        require_once '../models/Asistencia.php';
        
        // Llamar directamente al método sin pasar por el constructor
        $asistenciaModel = new Asistencia();
        
        // Validar parámetros
        if (!isset($_GET['ficha_id']) || !is_numeric($_GET['ficha_id'])) {
            throw new Exception('ID de ficha no válido');
        }
        $ficha_id = (int)$_GET['ficha_id'];
        $fecha_inicio = $_GET['fecha_inicio'] ?? null;
        $fecha_fin = $_GET['fecha_fin'] ?? null;
        if (!$fecha_inicio || !$fecha_fin || !strtotime($fecha_inicio) || !strtotime($fecha_fin)) {
            throw new Exception('Rango de fechas no válido');
        }
        
        // Obtener asistencias
        $asistencias = $asistenciaModel->obtenerAsistenciasPorFichaRango($ficha_id, $fecha_inicio, $fecha_fin);
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $asistencias]);
        exit;
        
    } catch (Throwable $e) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        echo json_encode([
            'success' => false, 
            'data' => [],
            'message' => $e->getMessage(),
            'debug' => [
                'GET' => $_GET,
                'SESSION' => isset($_SESSION['usuario']) ? 'logged_in' : 'not_logged'
            ]
        ]);
        exit;
    }
}
if ($page === 'clase_en_curso') {
    require_login(); require_role(2);
    require_once '../controllers/AsistenciaController.php';
    (new AsistenciaController())->claseEnCurso();
    exit;
}
if ($page === 'clase_proxima_hoy') {
    require_login(); require_role(2);
    require_once '../controllers/AsistenciaController.php';
    (new AsistenciaController())->proximaHoy();
    exit;
}
if ($page === 'guardar_asistencia' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login(); require_role(2);
    require_once '../controllers/AsistenciaController.php';
    (new AsistenciaController())->registrarLote();
    exit;
}
if ($page === 'asistencia_actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login(); require_role(2);
    require_once '../controllers/AsistenciaController.php';
    (new AsistenciaController())->actualizar();
    exit;
}
if ($page === 'asistencia_notificar_ausentes_dia') {
    require_login(); require_role(2);
    require_once '../controllers/AsistenciaController.php';
    (new AsistenciaController())->notificarAusentesDia();
    exit;
}
if ($page === 'iniciar_clase' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login(); require_role(2);
    require_once '../controllers/AsistenciaController.php';
    (new AsistenciaController())->iniciarClase();
    exit;
}
// Cambio de contraseña inicial (forzado)
if ($page === 'cambiar_password_inicial_guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../controllers/AuthController.php';
    (new AuthController())->cambiarPasswordInicialGuardar();
    exit;
}

// ====== ASISTENTE (ROL 4) ======
if ($page === 'asistente') {
    require_login();
    require_role([1,4]);
    require_once '../controllers/AsistenteController.php';
    new AsistenteController();
    exit;
}

if ($page === 'asistente_resumen') {
    require_login();
    require_role([1,4]);
    require_once '../controllers/AsistenteController.php';
    $_GET['action'] = 'resumen_hoy';
    new AsistenteController();
    exit;
}

if ($page === 'asistente_ausentes') {
    require_login();
    require_role([1,4]);
    require_once '../controllers/AsistenteController.php';
    $_GET['action'] = 'ausentes_hoy';
    new AsistenteController();
    exit;
}

if ($page === 'asistente_notificar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role([1,4]);
    require_once '../controllers/AsistenteController.php';
    $_GET['action'] = 'notificar_falta';
    new AsistenteController();
    exit;
}

if ($page === 'asistente_reporte_excel') {
    require_login();
    require_role([1,4]);
    require_once '../controllers/AsistenteController.php';
    $_GET['action'] = 'reporte_excel';
    new AsistenteController();
    exit;
}

// Reporte PDF (Asistente)
if ($page === 'asistente_reporte_pdf') {
    require_login();
    require_role([1,4]);
    require_once '../controllers/AsistenteController.php';
    $_GET['action'] = 'reporte_pdf';
    new AsistenteController();
    exit;
}

if ($page === 'asistente_colegios') {
    require_login();
    require_role([1,4]);
    require_once '../controllers/AsistenteController.php';
    $_GET['action'] = 'colegios';
    new AsistenteController();
    exit;
}


// Gestión de materias (cursos)
if ($page === 'cursos') {
    require_login(); require_role(1);
    require_once '../controllers/AreaDeConocimientoController.php';
    $c = new AreaDeConocimientoController();
    $action = $_GET['action'] ?? 'index';
    if ($action === 'crear') {
        $c->crear();
    } elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_validate();
        $c->guardar();
    } elseif ($action === 'editar') {
        $c->editar();
    } elseif ($action === 'actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_validate();
        $c->actualizar();
    } elseif ($action === 'suspender') {
        $c->suspender();
    } elseif ($action === 'activar') {
        $c->activar();
    } elseif ($action === 'eliminar') {
        $c->eliminar();
    } else {
        $c->index();
    }
    exit;
}

// ====== REGISTRO USUARIO GENERAL ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registro'])) {
    csrf_validate();
    $result = AuthController::registrar($_POST);
    if (is_string($result) && strpos($result, 'Error') !== false) {
        echo '<div style="color:red; font-weight:bold; margin:40px auto; max-width:600px; text-align:center;">'.$result.'</div>';
        echo '<a href="javascript:history.back()" style="display:block;text-align:center;margin-top:20px;">Volver</a>';
        exit;
    }
    exit;
}

// ====== API ENDPOINTS ======
if ($page === 'api') {
    require_once '../controllers/ApiController.php';
    $api = new ApiController();
    exit;
}

// ====== ENDPOINTS DE ASISTENCIAS (para tablero profesor) ======
// Obtener estudiantes para asistencia (usado por calendario_nuevo.js)
if ($page === 'asistencia_obtener_estudiantes') {
    require_login();
    require_role([1, 2]);
    require_once '../controllers/AsistenciaController.php';
    $_GET['action'] = 'obtener_estudiantes';
    new AsistenciaController();
    exit;
}

// Guardar asistencias del día en lote para una ficha
if ($page === 'guardar_asistencia' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role([1, 2]);
    require_once '../controllers/AsistenciaController.php';
    $_GET['action'] = 'registrar_lote';
    new AsistenciaController();
    exit;
}

// Próxima clase de HOY para una ficha (dashboard profesor)
if ($page === 'clase_proxima_hoy') {
    require_login();
    require_role([1, 2]);
    require_once '../controllers/AsistenciaController.php';
    $_GET['action'] = 'proxima_hoy';
    new AsistenciaController();
    exit;
}

// Alias para guardar asistencias (endpoint usado por JS)
if ($page === 'asistencia_registrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role([1, 2]);
    require_once '../controllers/AsistenciaController.php';
    $_GET['action'] = 'registrar_lote';
    new AsistenciaController();
    exit;
}

// Actualizar una asistencia específica (id, estado)
if ($page === 'asistencia_actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role([1, 2]);
    require_once '../controllers/AsistenciaController.php';
    $_GET['action'] = 'actualizar';
    new AsistenciaController();
    exit;
}

// Notificar ausentes del día (solo cuando la clase ya terminó)
if ($page === 'asistencia_notificar_ausentes_dia' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role([1, 2]);
    require_once '../controllers/AsistenciaController.php';
    (new AsistenciaController())->notificarAusentesDia();
    exit;
}

// Actualizar una asistencia (justificación desde modal calendario, modo solo lectura)
if ($page === 'asistencia_actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role([1, 2]);
    require_once '../controllers/AsistenciaController.php';
    $_GET['action'] = 'actualizar';
    new AsistenciaController();
    exit;
}

// Iniciar clase (usado por el contador del tablero)
if ($page === 'iniciar_clase' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role([1, 2]);
    require_once '../controllers/AsistenciaController.php';
    $_GET['action'] = 'iniciar_clase';
    new AsistenciaController();
    exit;
}

// Iniciar seguimiento por ausencia (vista para asistentes/admin)
if ($page === 'seguimiento_ausencia_iniciar') {
    require_login();
    require_role([1, 4, 1]); // admin(1) y asistente(4), profesor opcional si se desea permitir
    require_once '../controllers/SeguimientoController.php';
    $_GET['action'] = 'iniciar';
    new SeguimientoController();
    exit;
}

// Guardar seguimiento por ausencia
if ($page === 'seguimiento_ausencia_guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role([1, 4]);
    require_once '../controllers/SeguimientoController.php';
    $_GET['action'] = 'guardar';
    new SeguimientoController();
    exit;
}
// Verificar si hay clase en curso para la ficha (habilita registro de asistencia)
if ($page === 'clase_en_curso') {
    require_login();
    require_role([1, 2]);
    require_once '../controllers/AsistenciaController.php';
    $_GET['action'] = 'clase_en_curso';
    new AsistenciaController();
    exit;
}

// (Vista Asistencias removida: se usa la tabla semanal del dashboard de ficha)

// ====== REPORTES ======
if ($page === 'reportes') {
    require_login();
    require_role([1, 2, 3]); // Admin, Profesor, Coordinador
    require_once '../controllers/ReporteController.php';
    $reporteController = new ReporteController();
    $reporteController->index();
    exit;
}

// ====== ENDPOINTS PÚBLICOS (AJAX lean) ======

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
        $gradosVal = $colegio['grados'] ?? [];
        if (!is_array($gradosVal)) {
            $gradosVal = array_filter(array_map('trim', explode(',', (string)$gradosVal)));
        }
        $jornadasVal = $colegio['jornada'] ?? [];
        if (!is_array($jornadasVal)) {
            $jornadasVal = array_filter(array_map('trim', explode(',', (string)$jornadasVal)));
        }
        echo json_encode([
            'grados'   => array_values($gradosVal),
            'jornadas' => array_values($jornadasVal)
        ]);
    } else {
        echo json_encode(['grados' => [], 'jornadas' => []]);
    }
    exit;
}

// Profesores por colegio
if ($page === 'profesores_por_colegio' && isset($_GET['colegio_id'])) {
    require_once '../models/Facilitador.php';
    $profesorModel = new Facilitador();
    header('Content-Type: application/json');
    echo json_encode($profesorModel->obtenerPorColegio($_GET['colegio_id']));
    exit;
}

// Estudiantes por colegio
if ($page === 'estudiantes_por_colegio' && isset($_GET['colegio_id'])) {
    require_once '../models/Aprendiz.php';
    $estudianteModel = new Aprendiz();
    header('Content-Type: application/json');
    echo json_encode($estudianteModel->obtenerPorColegio($_GET['colegio_id']));
    exit;
}

// Fichas por colegio
if ($page === 'fichas_por_colegio' && isset($_GET['colegio_id'])) {
    require_once '../models/Ficha.php';
    $fichaModel = new Ficha();
    header('Content-Type: application/json');
    echo json_encode($fichaModel->obtenerPorColegio($_GET['colegio_id']));
    exit;
}

// Fichas del facilitador autenticado
if ($page === 'facilitadorficha') {
    require_login();
    require_role(2);
    require_once '../controllers/FacilitadorController.php';
    $controller = new FacilitadorController();
    $controller->obtenerFichasPorFacilitador();
    exit;
}

// Estudiantes por ficha (JSON)
if ($page === 'estudiantesporficha') {
    require_login();
    require_role(2);
    header('Content-Type: application/json; charset=utf-8');
    $fichaId = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : 0;
    if ($fichaId <= 0) { echo json_encode([]); exit; }
    require_once '../models/Aprendiz.php';
    $apr = new Aprendiz();
    try {
        $lista = $apr->obtenerTodos($fichaId);
        echo json_encode(is_array($lista) ? $lista : []);
    } catch (Throwable $e) {
        echo json_encode([]);
    }
    exit;
}

// Obtener todos los profesores (para compartir fichas)
if ($page === 'obtener_profesores') {
    if (!ob_get_level()) { ob_start(); }
    ini_set('display_errors', '0');
    try {
        require_login();
        require_role(2);

        require_once '../config/db.php';
        $pdo = Database::conectar();

        $profesorActual = $_SESSION['usuario']['id'];
        $stmt = $pdo->prepare("SELECT f.id, u.nombres, u.apellidos, f.tipo_contrato
                               FROM facilitadores f
                               INNER JOIN usuarios u ON f.usuario_id = u.id
                               WHERE u.id != ?");
        $stmt->execute([$profesorActual]);
        $profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($profesores ?: []);
        exit;
    } catch (Throwable $e) {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Compartir ficha con otros profesores
if ($page === 'compartir_ficha' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ob_get_level()) { ob_start(); }
    ini_set('display_errors', '0');
    require_login();
    require_role(2);

    // Llamar directamente al método sin instanciar el controlador
    $input = json_decode(file_get_contents('php://input'), true);
    $fichaId = $input['ficha_id'] ?? null;
    $profesores = $input['profesores'] ?? [];

    if (!$fichaId || empty($profesores)) {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    $usuarioActual = $_SESSION['usuario']['id'] ?? null;
    if (!$usuarioActual) {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }

    try {
        require_once '../config/db.php';
        $pdo = Database::conectar();
        $pdo->beginTransaction();

        // Obtener profesor_id del usuario actual
        $stmt = $pdo->prepare("SELECT id FROM facilitadores WHERE usuario_id = ? LIMIT 1");
        $stmt->execute([$usuarioActual]);
        $profesorLider = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profesorLider) {
            throw new Exception('Profesor líder no encontrado');
        }

        $profesorLiderId = $profesorLider['id'];

        // Insertar solicitudes
        foreach ($profesores as $profesorId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM fichas_compartidas 
                                  WHERE ficha_id = ? AND profesor_lider_id = ? AND profesor_compartido_id = ? AND estado_id IN (1,2)");
            $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);

            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO fichas_compartidas 
                                      (ficha_id, profesor_lider_id, profesor_compartido_id, estado_id) 
                                      VALUES (?, ?, ?, 1)");
                $stmt->execute([$fichaId, $profesorLiderId, $profesorId]);
            }
        }

        $pdo->commit();
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Ficha compartida exitosamente']);
        exit;

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Verificar estado de compartir ficha
if ($page === 'verificar_estado_compartir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ob_get_level()) { ob_start(); }
    ini_set('display_errors', '0');
    try {
        require_login();
        require_role(2);
        require_once '../controllers/FichaController.php';
        $controller = new FichaController();
        $controller->verificarEstadoCompartir();
        exit;
    } catch (Throwable $e) {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
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

// Aprendices por ficha
if ($page === 'aprendicesporficha') {
    require_login();
    require_once '../controllers/AprendizController.php';
    $controller = new AprendizController();
    $ficha_id = $_GET['ficha_id'] ?? null;
    header('Content-Type: application/json');
    echo $ficha_id ? $controller->obtenerPorFicha($ficha_id) : json_encode(['error' => 'Falta ficha_id']);
    exit;
}

// ====== ENDPOINTS DE CALENDARIO ======
// Obtener horarios para calendario
if ($page === 'calendario_obtener') {
    start_secure_session();
    require_login();
    require_role([1, 2]); // Permite administradores (1) y profesores (2)
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->obtenerHorarios();
    exit;
}

// Obtener fichas disponibles
if ($page === 'calendario_obtener_fichas') {
    start_secure_session();
    require_login();
    require_role([1, 2]); // Permite administradores (1) y profesores (2)
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->obtenerFichasDisponibles();
    exit;
}

// Crear nuevo horario
if ($page === 'calendario_crear') {
    start_secure_session();
    require_login();
    require_role([1, 2]); // Permite administradores (1) y profesores (2)
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->crearHorario();
    exit;
}

// Actualizar horario existente - Calendario Principal
if ($page === 'calendario_actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session();
    require_login();
    require_role([1, 2]); // Permite administradores (1) y profesores (2)
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->actualizarHorario();
    exit;
}

// Actualizar horario existente - Calendario Colaborativo
if ($page === 'calcolab_actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session();
    require_login();
    require_role([1, 2, 4]); // Permite administradores (1), profesores (2) y asistentes (4)
    require_once '../controllers/CalendarioColaborativoController.php';
    $controller = new CalendarioColaborativoController();
    $controller->actualizarHorario();
    exit;
}

// Eliminar horario
if ($page === 'calendario_eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session();
    require_login();
    require_role([1, 2]); // Permite administradores (1) y profesores (2)
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->eliminarHorario();
    exit;
}

// Cambiar estado de horario
if ($page === 'calendario_estado' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session();
    require_login();
    require_role([1, 2]); // Permite administradores (1) y profesores (2)
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->cambiarEstado();
    exit;
}

// Exportar reporte de clases a CSV
if ($page === 'calendario_exportar' && isset($_GET['action']) && $_GET['action'] === 'exportarReporte') {
    start_secure_session();
    require_login();
    require_role([1, 2]); // Permite administradores (1) y profesores (2)
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->exportarReporteCSV();
    exit;
}

// Duplicar semana de calendario
if ($page === 'calendario_duplicar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session();
    require_login();
    require_role([1, 2]);
    require_once '../controllers/CalendarioController.php';
    $controller = new CalendarioController();
    $controller->duplicarSemana();
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

// Obtener contador de notificaciones no leídas (AJAX)
if ($page === 'notificaciones_contador') {
    require_login();
    header('Content-Type: application/json');
    
    try {
        require_once '../config/db.php';
        $pdo = Database::conectar();
        
        $usuario_id = $_SESSION['usuario']['id'] ?? null;
        if (!$usuario_id) {
            echo json_encode(['count' => 0]);
            exit;
        }
        
        // Contar no leídas soportando estado_id (1=no leída) y legacy
        try {
            $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND estado_id = 1");
            $stmtTotal->execute([$usuario_id]);
        } catch (PDOException $eEstadoId) {
            if ($eEstadoId->getCode() !== '42S22') { throw $eEstadoId; }
            try {
                $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND (leido = 0 OR leido IS NULL)");
                $stmtTotal->execute([$usuario_id]);
            } catch (PDOException $eCount) {
                if ($eCount->getCode() !== '42S22') { throw $eCount; }
                try {
                    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND (estado = 'no_leida' OR estado IS NULL)");
                    $stmtTotal->execute([$usuario_id]);
                } catch (PDOException $eCount2) {
                    if ($eCount2->getCode() !== '42S22') { throw $eCount2; }
                    try {
                        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario = ? AND (leido = 0 OR leido IS NULL)");
                        $stmtTotal->execute([$usuario_id]);
                    } catch (PDOException $eCount3) {
                        if ($eCount3->getCode() !== '42S22') { throw $eCount3; }
                        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario = ? AND (estado = 'no_leida' OR estado IS NULL)");
                        $stmtTotal->execute([$usuario_id]);
                    }
                }
            }
        }
        $totalNoLeidas = (int)$stmtTotal->fetchColumn();
        
        echo json_encode(['count' => $totalNoLeidas]);
        
    } catch (Exception $e) {
        error_log("Error al obtener contador de notificaciones: " . $e->getMessage());
        echo json_encode(['count' => 0]);
    }
    exit;
}

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

// ===== PLANTILLA IMPORT ESTUDIANTES =====
if ($page === 'plantilla_import_estudiantes') {
    require __DIR__ . '/../views/Archivos/plantilla_import_aprendices.php';
    exit;
}

// ====== REGISTRO (vista pública con token) ======
if ($page === 'registro_estudiante') {
    require_once '../controllers/AprendizController.php';
    $c = new AprendizController();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $c->guardarPublico();
        exit;
    } else {
        $c->formularioPublico();
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

// ====== REGISTRO PÚBLICO DE PROFESOR ======
if ($page === 'registro_profesor') {
    // Vista pública del nuevo formulario de profesor
    include '../views/registro.php';
    exit;
}

if ($page === 'registro_profesor_guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Guardado público (sin requerir login admin)
    require_once '../controllers/FacilitadorController.php';
    $c = new FacilitadorController();
    $c->guardarPublico();
    exit;
}


// ====== RUTAS PROTEGIDAS (VISTAS) ======

// Administradores
if ($page === 'administradores') {
    require_login(); require_role(1);
    $action = $_GET['action'] ?? 'index';
    if ($action === 'crear') {
        include '../views/Administrador/crear.php';
        exit;
    }
}

if ($page === 'asistentes') {
    require_login(); require_role(1);
    $action = $_GET['action'] ?? 'index';
    if ($action === 'crear') {
        include '../views/Asistente/crear_asistente.php';
        exit;
    }
    // Aquí podrías agregar más acciones para asistentes si lo necesitas
}
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

// Facilitadores/Instructores (nuevo esquema)
if ($page === 'facilitadores') {
    require_login(); require_role(1);
    require_once '../controllers/FacilitadorController.php';
    $c = new FacilitadorController();
    $action = $_GET['action'] ?? 'index';
    if     ($action === 'crear')      { $c->crear(); }
    elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') { csrf_validate(); $c->guardar(); }
    else { $c->index(); }
    exit;
}

// Pendientes de aprobación (facilitadores registrados públicamente)
if ($page === 'facilitadores_pendientes') {
    require_login(); require_role([1,4]);
    require_once '../controllers/FacilitadorController.php';
    (new FacilitadorController())->pendientes();
    exit;
}

// Aprobar registro público de facilitador
if ($page === 'facilitador_aprobar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login(); require_role([1,4]);
    require_once '../controllers/FacilitadorController.php';
    (new FacilitadorController())->aprobarPublico();
    exit;
}

// Rechazar registro público de facilitador
if ($page === 'facilitador_rechazar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login(); require_role([1,4]);
    require_once '../controllers/FacilitadorController.php';
    (new FacilitadorController())->rechazarPublico();
    exit;
}

if ($page === 'facilitadores') {
    // Compatibilidad: redirigir a FacilitadorController (renombrado)
    require_login(); require_role(1);
    require_once '../controllers/FacilitadorController.php';
    $c = new FacilitadorController();
    $action = $_GET['action'] ?? 'index';
    if     ($action === 'crear')      { $c->crear(); }
    elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') { csrf_validate(); $c->guardar(); }
    else { $c->index(); }
    exit;
}

if ($page === 'aprendices') {
    require_login(); 
    require_role([1,2]);
    require_once '../controllers/AprendizController.php';
    $c = new AprendizController();
    $action = $_GET['action'] ?? 'index';
    if ($action === 'crear') {
        $c->crear();
    } elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_validate();
        $c->guardar();
    } elseif ($action === 'mover_ficha' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_validate();
        $c->moverFicha();
    } elseif ($action === 'editar' && isset($_GET['id'])) {
        $c->editar();
    } elseif ($action === 'actualizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_validate();
        $c->actualizar();
    } elseif ($action === 'eliminar' && isset($_GET['id'])) {
        $c->eliminar();
    } elseif ($action === 'activar' && isset($_GET['id'])) {
        $c->activar();
    } elseif ($action === 'importar') {
        $c->importar();
    } elseif ($action === 'importar_pendientes') {
        $c->importarPendientes();
    } elseif ($action === 'pendientes_ficha') {
        $c->pendientesFicha();
    } elseif ($action === 'pendientes_generales') {
        $c->pendientesGenerales();
    } elseif ($action === 'asignar_pendiente') {
        $c->asignarPendiente();
    } elseif ($action === 'importar_excel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $c->importarExcel();
    } else {
        $c->index();
    }
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
        // Redirigir a Mis Fichas (dashboard_profesor) para profesores, o al listado para admins
        $rol_id = $_SESSION['usuario']['rol_id'] ?? 1;
        if ($rol_id === 2) {
            header("Location: /?page=dashboard_profesor");
        } else {
            header("Location: /?page=fichas&action=index");
        }
        exit;
    } elseif ($action === 'editar' && isset($_GET['id'])) {
        $c->editar();
    } elseif ($action === 'ver' && isset($_GET['id'])) {
        $c->ver();
    } elseif ($action === 'eliminar' && isset($_GET['id'])) {
        $c->eliminar();
    } else {
        // Redirigir según el rol: profesores a Mis Fichas, admins al dashboard
        $rol_id = $_SESSION['usuario']['rol_id'] ?? 1;
        if ($rol_id === 2) {
            header("Location: /?page=dashboard_profesor");
        } else {
            header("Location: /?page=dashboard");
        }
        exit;
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
            require_once '../models/Facilitador.php';
            $resultados = (new Facilitador())->buscarPorNombre($query);
            break;
        case 'estudiante':
            require_once '../models/Aprendiz.php';
            $resultados = (new Aprendiz())->buscarPorNombre($query);
            break;
    }

    include '../views/Componentes/resultados_busqueda.php';
    exit;
}

// Endpoint ligero: info de colegio (grados y jornadas) para registro público
if ($page === 'info_colegio') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $id = isset($_GET['colegio_id']) ? (int)$_GET['colegio_id'] : 0;
        if ($id <= 0) { echo json_encode(['grados' => [], 'jornadas' => []]); exit; }
        require_once '../models/Colegio.php';
        $m = new Colegio();
        $col = $m->obtenerPorId($id);
        $grados = isset($col['grados']) && is_array($col['grados']) ? $col['grados'] : [];
        $jornadas = isset($col['jornada']) && is_array($col['jornada']) ? $col['jornada'] : [];
        echo json_encode(['grados' => $grados, 'jornadas' => $jornadas], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['grados' => [], 'jornadas' => [], 'error' => $e->getMessage()]);
    }
    exit;
}

// ====== ENDPOINT PREVIEW ======
if ($page === 'preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role([1, 2]);
    include '../views/Archivos/preview.php';
    exit;
}

// ====== ENDPOINT PREVIEW V2 (para modales de reportes) ======
if ($page === 'preview_v2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role([1, 2, 4]); // Permitir a Admin, Profesor y Asistente
    include '../views/Archivos/preview.php';
    exit;
}

// ====== CALENDARIO ======
if ($page === 'calendario') {
    require_login();
    require_role([1, 2]); // Permite tanto a administradores (1) como a profesores (2)
    include '../views/Calendario/calendario.php';
    exit;
}

// ====== CALENDARIO COLABORATIVO (Asistente + Admin + Profesores) ======
if ($page === 'calendario_colaborativo') {
    require_login();
    require_role([1, 2, 4]); // Admin, Profesores y Asistente
    include '../views/Calendario/colaborativo.php';
    exit;
}

// Endpoints JSON del Calendario Colaborativo
if ($page === 'calcolab_instructores') {
    require_login();
    require_role([1, 2, 4]);
    require_once '../controllers/CalendarioColaborativoController.php';
    (new CalendarioColaborativoController())->instructores();
    exit;
}

// Calcolab: crear clase (POST)
if ($page === 'calcolab_crear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role([1, 2, 4]); // Admin, Profesores y Asistente
    require_once '../controllers/CalendarioColaborativoController.php';
    (new CalendarioColaborativoController())->crear();
    exit;
}

if ($page === 'calcolab_eventos') {
    require_login();
    require_role([1, 2, 4]); // Admin, Profesores y Asistente
    require_once '../controllers/CalendarioColaborativoController.php';
    (new CalendarioColaborativoController())->eventos();
    exit;
}

// Calcolab: exportar a Excel (XLSX)
if ($page === 'calcolab_export') {
    require_login();
    require_role([1, 2, 4]); // Admin, Profesores y Asistente
    require_once '../controllers/CalendarioColaborativoController.php';
    (new CalendarioColaborativoController())->exportarExcel();
    exit;
}

// Calcolab: disponibilidad por ficha (usar en modal estándar). Solo requiere sesión activa.
if ($page === 'calcolab_disponibilidad_ficha') {
    require_login();
    require_once '../controllers/CalendarioColaborativoController.php';
    (new CalendarioColaborativoController())->disponibilidadFicha();
    exit;
}

// Calcolab: fichas por instructor
if ($page === 'calcolab_fichas_por_instructor') {
    require_login();
    require_role([1, 4]);
    require_once '../controllers/CalendarioColaborativoController.php';
    (new CalendarioColaborativoController())->fichasPorInstructor();
    exit;
}

// API: Asistencias por fecha (para pestaña en modal de detalles)
if ($page === 'asistencias_por_fecha') {
    require_login();
    require_role([1, 2, 4]);
    header('Content-Type: application/json; charset=utf-8');
    try {
        $fecha = isset($_GET['fecha']) ? trim((string)$_GET['fecha']) : '';
        if ($fecha === '') { echo json_encode([]); exit; }
        $fichaIdParam = isset($_GET['ficha_id']) ? trim((string)$_GET['ficha_id']) : '';
        $fichaParam = isset($_GET['ficha']) ? trim((string)$_GET['ficha']) : '';
        // Resolver ficha_id desde 'ficha_id' directo o desde código/nombre
        require_once '../config/db.php';
        $pdo = Database::conectar();
        $ficha_id = 0;
        if ($fichaIdParam !== '' && ctype_digit($fichaIdParam)) {
            $ficha_id = (int)$fichaIdParam;
        } elseif ($fichaParam !== '') {
            if (ctype_digit($fichaParam)) { $ficha_id = (int)$fichaParam; }
            else {
                $st = $pdo->prepare('SELECT id FROM fichas WHERE numero = ? OR nombre = ? LIMIT 1');
                $st->execute([$fichaParam, $fichaParam]);
                $row = $st->fetch(PDO::FETCH_ASSOC); if ($row) $ficha_id = (int)$row['id'];
            }
        }
        // Si no hay ficha id, retornar vacío
        if ($ficha_id <= 0) { echo json_encode([]); exit; }
        require_once '../models/Asistencia.php';
        $m = new Asistencia();
        $rows = $m->obtenerEstudiantesFichaFecha($ficha_id, $fecha);
        echo json_encode($rows ?: []);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Calcolab: instructores por ficha
if ($page === 'calcolab_instructores_por_ficha') {
    require_login();
    require_role([1, 4]);
    require_once '../controllers/CalendarioColaborativoController.php';
    (new CalendarioColaborativoController())->instructoresPorFicha();
    exit;
}

// ====== DASHBOARDS SEGÚN ROL ======
if (!empty($_SESSION['usuario'])) {
    $rol_id = (int)$_SESSION['usuario']['rol_id'];
    if ($rol_id === 1) { include '../views/Administrador/dashboard.php'; exit; }
    if ($rol_id === 2) { include '../views/Facilitador/dashboard.php'; exit; }
    if ($rol_id === 4) { include '../views/Asistente/dashboard.php'; exit; }
}

// ====== RECUPERAR CONTRASEÑA ======
if ($page === 'forgot_password') {
    require_once '../controllers/AuthController.php';
    (new AuthController())->forgotForm();
    exit;
}
if ($page === 'forgot_password_send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../controllers/AuthController.php';
    (new AuthController())->forgotSend();
    exit;
}
// Verificación de código de 6 dígitos (paso intermedio)
if ($page === 'verify_code') {
    require_once '../controllers/AuthController.php';
    (new AuthController())->verifyCodeForm();
    exit;
}
if ($page === 'verify_reset_code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../views/Auth/verify_reset_code.php';
    exit;
}
if ($page === 'resend_code') {
    require_once __DIR__ . '/../views/Auth/resend_code.php';
    exit;
}
if ($page === 'reset_password_code') {
    start_secure_session();
    if (!isset($_SESSION['pwd_change_allowed'])) {
        header('Location: /?page=forgot_password');
        exit;
    }
    require __DIR__ . '/../views/Auth/reset_password_code.php';
    exit;
}
if ($page === 'reset_password_code_submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../views/Auth/cambiar_password_inicial.php';
    exit;
}
if ($page === 'reset_password') {
    require_once '../controllers/AuthController.php';
    (new AuthController())->resetForm();
    exit;
}
if ($page === 'reset_password_submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../controllers/AuthController.php';
    (new AuthController())->resetSubmit();
    exit;
}
// ====== EMAILJS: VISTA DE PRUEBA Y ENVÍO ======
if ($page === 'emailjs') {
    require_once '../views/Auth/Emailjs.php';
    exit;
}


// ====== LOGIN (por defecto) ======
$c = new AuthController();
$c->loginForm();
exit;