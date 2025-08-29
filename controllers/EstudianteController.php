<?php
require_once __DIR__ . '/../models/Estudiante.php';
require_once __DIR__ . '/../models/Colegio.php';

class EstudianteController {

    /* 📌 Mostrar formulario de creación */
    public function crear() {
        $colegioModel = new Colegio();
        $colegios = $colegioModel->obtenerTodos();
        require __DIR__ . '/../views/Estudiante/crear.php';
    }

    /* 📌 Guardar estudiante nuevo */
    public function guardar() {
        start_secure_session();
        require_login();
        require_role(1);
        csrf_validate();

        $datos = [
            'nombres'                     => trim($_POST['nombres'] ?? ''),
            'apellidos'                   => trim($_POST['apellidos'] ?? ''),
            'tipo_documento'              => $_POST['tipo_documento'] ?? '',
            'numero_documento'            => trim($_POST['numero_documento'] ?? ''),
            'correo_electronico'          => trim($_POST['correo_electronico'] ?? ''),
            'telefono'                    => trim($_POST['telefono'] ?? ''),
            'fecha_nacimiento'            => $_POST['fecha_nacimiento'] ?? '',
            'genero'                      => $_POST['genero'] ?? '',
            'colegio_id'                  => $_POST['colegio_id'] ?? null,
            'grado'                       => $_POST['grado'] ?? '',
            'grupo'                       => trim($_POST['grupo'] ?? ''),
            'jornada'                     => $_POST['jornada'] ?? '',
            'fecha_ingreso'               => date('Y-m-d'),
            'nombre_completo_acudiente'   => trim($_POST['nombre_completo_acudiente'] ?? ''),
            'tipo_documento_acudiente'    => $_POST['tipo_documento_acudiente'] ?? '',
            'numero_documento_acudiente'  => trim($_POST['numero_documento_acudiente'] ?? ''),
            'telefono_acudiente'          => trim($_POST['telefono_acudiente'] ?? ''),
            'parentesco'                  => trim($_POST['parentesco'] ?? ''),
            'ocupacion'                   => trim($_POST['ocupacion'] ?? ''),
            'ficha_id'                    => $_POST['ficha_id'] ?? null,
        ];

        $estudianteModel = new Estudiante();

        if ($estudianteModel->guardar($datos)) {
            // ✅ Redirigir a la vista de la ficha en la que se registró el estudiante
            $fichaId = $datos['ficha_id'];
            header("Location: /?page=fichas&action=ver&id=" . urlencode($fichaId) . "&success=1");
            exit;
        }
        echo "❌ Error al registrar estudiante.";
    }

    /* 📌 Listado de estudiantes */
    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $estudianteModel = new Estudiante();
        $rol_id = $_SESSION['usuario']['rol_id'] ?? null;

        if ($rol_id == 1) { 
            // Administrador → todos los estudiantes
            $estudiantes = $estudianteModel->obtenerTodos(); 
        } else {
            // Otros roles → deben pasar ficha_id
            $ficha_id = $_GET['ficha_id'] ?? null;
            if (!$ficha_id) {
                die('❌ Ficha no especificada.');
            }
            $estudiantes = $estudianteModel->obtenerTodos($ficha_id);
        }

        require_once __DIR__ . '/../views/Estudiante/lista.php';
    }

    /* 📌 Contar estudiantes (para dashboard) */
    public function contar() {
        $estudianteModel = new Estudiante();
        $totalEstudiante = $estudianteModel->contarEstudiantes();
        require 'views/dashboard.php'; 
    }

    /* 📌 API → Estudiantes por colegio (JSON) */
    public function obtenerPorColegio($colegioId) {
        $estudianteModel = new Estudiante();
        $estudiantes = $estudianteModel->obtenerPorColegio($colegioId);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($estudiantes, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* 📌 API → Estudiantes por ficha (JSON) */
    public function obtenerPorFicha($ficha_id) {
        $estudianteModel = new Estudiante();
        // ✅ Se usa obtenerTodos pasándole ficha_id
        $estudiantes = $estudianteModel->obtenerTodos($ficha_id);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($estudiantes, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
