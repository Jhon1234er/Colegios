<?php
require_once __DIR__ . '/../models/Estudiante.php';
require_once __DIR__ . '/../models/Colegio.php';
require_once __DIR__ . '/../models/Ficha.php';

class EstudianteController {

    /* 📌 Mostrar formulario de creación (panel interno) */
    public function crear() {
        $colegioModel = new Colegio();
        $colegios = $colegioModel->obtenerTodos();
        require __DIR__ . '/../views/Estudiante/crear.php';
    }

    /* 📌 Guardar estudiante (panel interno) */
    public function guardar() {
        start_secure_session();
        require_login();
        require_role([1, 2]);
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
            $fichaId = $datos['ficha_id'];
            header("Location: /?page=fichas&action=ver&id=" . urlencode($fichaId) . "&success=1");
            exit;
        }
        echo "❌ Error al registrar estudiante.";
    }

    /* 📌 Guardar estudiante desde formulario público (usando token de la ficha) */
    public function guardarPublico() {
        // Iniciar sesión para CSRF sin requerir login
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        csrf_validate();

        // ✅ Tomar token desde la URL
        $token = $_GET['token'] ?? null;
        if (!$token) {
            die("⚠️ Token no válido.");
        }

        // ✅ Buscar ficha asociada
        $fichaModel = new Ficha();
        $ficha = $fichaModel->buscarPorToken($token);

        if (!$ficha) {
            die("⚠️ Token inválido o vencido.");
        }

        // ✅ Siempre se fuerza ficha_id con la ficha encontrada
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
            'ficha_id'                    => $ficha['id'], // ✅ forzado desde el token
        ];

        $estudianteModel = new Estudiante();

        if ($estudianteModel->guardarPublico($datos)) {
            // Redirigir de vuelta al formulario con mensaje de éxito
            header("Location: /?page=registro_estudiante&token=" . urlencode($token) . "&success=1");
            exit;
        }

        echo "❌ Error al registrar estudiante desde formulario público.";
    }

    /* 📌 Listado de estudiantes */
    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $estudianteModel = new Estudiante();
        $rol_id = $_SESSION['usuario']['rol_id'] ?? null;

        if ($rol_id == 1) { 
            $estudiantes = $estudianteModel->obtenerTodos(); 
        } else {
            $ficha_id = $_GET['ficha_id'] ?? null;
            if (!$ficha_id) {
                die('❌ Ficha no especificada.');
            }
            $estudiantes = $estudianteModel->obtenerTodos($ficha_id);
        }

        require_once __DIR__ . '/../views/Estudiante/lista.php';
    }

    /* 📌 Contar estudiantes */
    public function contar() {
        $estudianteModel = new Estudiante();
        $totalEstudiante = $estudianteModel->contarEstudiantes();
        require 'views/dashboard.php'; 
    }

    /* 📌 API → Estudiantes por colegio */
    public function obtenerPorColegio($colegioId) {
        $estudianteModel = new Estudiante();
        $estudiantes = $estudianteModel->obtenerPorColegio($colegioId);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($estudiantes, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* 📌 API → Estudiantes por ficha */
    public function obtenerPorFicha($ficha_id) {
        $estudianteModel = new Estudiante();
        $estudiantes = $estudianteModel->obtenerTodos($ficha_id);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($estudiantes, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
