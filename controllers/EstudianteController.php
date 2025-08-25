<?php
require_once __DIR__ . '/../models/Estudiante.php';
require_once __DIR__ . '/../models/Colegio.php';


class EstudianteController {
    public function crear() {
        $colegioModel = new Colegio();
        $colegios = $colegioModel->obtenerTodos();
        require __DIR__ . '/../views/Estudiante/crear.php';
    }

    public function guardar() {
        start_secure_session();
        require_login(); require_role(1);
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
            'ocupacion'                   => trim($_POST['ocupacion'] ?? '')
        ];

        require_once __DIR__ . '/../models/Estudiante.php';
        $estudianteModel = new Estudiante();

        if ($estudianteModel->guardar($datos)) {
            header("Location: /?page=estudiantes&success=1");
            exit;
        }
        echo "❌ Error al registrar estudiante.";
    }

    public function index() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

        $estudianteModel = new Estudiante();
        $rol_id = $_SESSION['usuario']['rol_id'] ?? null;

        if ($rol_id == 1) { // Administrador
            $estudiantes = $estudianteModel->obtenerTodos(); // sin ficha
        } else {
            $ficha_id = $_GET['ficha_id'] ?? null;
            if (!$ficha_id) {
                die('❌ Ficha no especificada.');
            }
            $estudiantes = $estudianteModel->obtenerTodos($ficha_id);
        }

        require_once __DIR__ . '/../views/Estudiante/lista.php';
    }

    
    public function contar() {
        $estudianteModel = new Estudiante();
        $totalEstudiante = $estudianteModel->contarEstudiantes();

        require 'views/dashboard.php'; 
    }
    public function obtenerPorColegio($colegioId) {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("
            SELECT 
                CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo,
                e.grado,
                e.jornada,
                e.nombre_completo_acudiente,
                e.telefono_acudiente,
                e.parentesco,
                f.nombre AS numero_ficha
            FROM estudiantes e
            JOIN usuarios u ON e.usuario_id = u.id
            LEFT JOIN fichas f ON e.ficha_id = f.id
            WHERE e.colegio_id = ?
        ");
        $stmt->execute([$colegioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorFicha($ficha_id) {
        $pdo = Database::conectar();
        $sql = "SELECT 
                    e.id AS id, -- esto es clave
                    u.nombres, 
                    u.apellidos, 
                    e.grado, 
                    e.jornada,
                    e.nombre_completo_acudiente, 
                    e.telefono_acudiente, 
                    e.parentesco
                FROM estudiantes e
                INNER JOIN usuarios u ON u.id = e.usuario_id
                WHERE e.ficha_id = ?";
                $stmt = $pdo->prepare($sql);
        $stmt->execute([$ficha_id]);
        $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($estudiantes);
    }

}
