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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $estudianteModel = new Estudiante();

            $datos = [
                'nombre' => $_POST['nombres'],
                'apellido' => $_POST['apellidos'],
                'tipo_documento' => $_POST['tipo_documento'],
                'numero_documento' => $_POST['numero_documento'],
                'correo_electronico' => $_POST['correo_electronico'],
                'telefono' => $_POST['telefono'],
                'genero' => $_POST['genero'],
                'contrasena' => $_POST['password'],
                'fecha_nacimiento' => $_POST['fecha_nacimiento'],
                'colegio_id' => $_POST['colegio_id'],
                'ficha_id' => $_POST['ficha_id'],
                'grado' => $_POST['grado'],
                'grupo' => $_POST['grupo'],
                'jornada' => $_POST['jornada'],
                'fecha_ingreso' => date('Y-m-d'),
                'nombre_completo_acudiente' => $_POST['nombre_completo_acudiente'],
                'tipo_documento_acudiente' => $_POST['tipo_documento_acudiente'],
                'numero_documento_acudiente' => $_POST['numero_documento_acudiente'],
                'telefono_acudiente' => $_POST['telefono_acudiente'],
                'parentesco' => $_POST['parentesco'],
                'ocupacion' => $_POST['ocupacion']
            ];

            $exito = $estudianteModel->guardar($datos);

            if ($exito) {
                header("Location: /?page=estudiantes&success=1");
                exit;
            } else {
                echo "❌ Error al registrar estudiante.";
            }
        }
    }

    public function index() {
        $estudianteModel = new Estudiante();
        $estudiantes = $estudianteModel->obtenerTodos();
        require_once __DIR__ . '/../views/Estudiante/lista.php';

    }
    
    public function contar() {
        $estudianteModel = new Estudiante();
        $totalEstudiante = $estudianteModel->contarEstudiantes();

        require 'views/dashboard.php'; 
    }

    public function obtenerEstudiantesConFichas() {
        $estudianteModel = new Estudiante();
        $estudiantes = $estudianteModel->obtenerTodos();
        $pdo = \Database::conectar();

        foreach ($estudiantes as &$estudiante) {
            $stmt = $pdo->prepare("SELECT nombre FROM fichas WHERE id = ?");
            $stmt->execute([$estudiante['ficha_id']]);
            $ficha = $stmt->fetchColumn();
            $estudiante['fichas'] = $ficha ? [$ficha] : [];
        }
        echo json_encode($estudiantes);
    }
    public function obtenerPorFicha($ficha_id) {
    $pdo = Database::conectar();
    $sql = "SELECT u.nombres, u.apellidos, e.grado, e.jornada,
                   e.nombre_completo_acudiente, e.telefono_acudiente, e.parentesco
            FROM estudiantes e
            INNER JOIN usuarios u ON u.id = e.usuario_id
            WHERE e.ficha_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ficha_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

}
