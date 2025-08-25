<?php
require_once __DIR__ . '/../models/Colegio.php';
require_once __DIR__ . '/../models/Profesor.php';

class ColegioController {
    private $colegioModel;
    private $profesorModel;

    public function __construct() {
        $this->colegioModel = new Colegio();
        $this->profesorModel = new Profesor();
    }

    public function index() {
        $colegios = $this->colegioModel->obtenerTodos();
        include __DIR__ . '/../views/Colegio/lista.php';
    }

    public function crear() {
        include __DIR__ . '/../views/Colegio/crear.php';
    }

    public function guardar() {
        start_secure_session();
        require_login(); require_role(1);
        csrf_validate();

        $datos = [
            'nombre'          => trim($_POST['nombre'] ?? ''),
            'codigo_dane'     => trim($_POST['codigo_dane'] ?? ''),
            'nit'             => trim($_POST['nit'] ?? ''),
            'tipo_institucion'=> $_POST['tipo_institucion'] ?? '',
            'direccion'       => trim($_POST['direccion'] ?? ''),
            'telefono'        => trim($_POST['telefono'] ?? ''),
            'correo'          => trim($_POST['correo'] ?? ''),
            'municipio'       => trim($_POST['municipio'] ?? ''),
            'departamento'    => trim($_POST['departamento'] ?? ''),
            'materias'        => $_POST['materias'] ?? [],
            'jornada'         => $_POST['jornada'] ?? [],
            'grados'          => $_POST['grados'] ?? [],
            'calendario'      => $_POST['calendario'] ?? []
        ];

        require_once __DIR__ . '/../models/Colegio.php';
        $colegioModel = new Colegio();

        if ($colegioModel->guardar($datos)) {
            header("Location: /?page=colegios&success=1");
            exit;
        }
        echo "❌ Error al guardar colegio.";
    }


    public function eliminar() {
        if (isset($_GET['id'])) {
            $this->colegioModel->eliminar($_GET['id']);
            header("Location: /?page=colegios");
            exit;
        }
    }

    public function infoProfesores() {
        header('Content-Type: application/json');

        $colegioId = $_POST['colegio_id'] ?? null;

        if ($colegioId) {
            $profesores = $this->profesorModel->obtenerPorColegio($colegioId);

            // Asegúrate que los alias coincidan con lo que usas en el JS:
            $respuesta = array_map(function ($p) {
                return [
                    'nombre_completo' => $p['nombre'],  // usa 'nombre' si así viene de la consulta
                    'materia' => $p['materia']
                ];
            }, $profesores);

            echo json_encode($respuesta);
        } else {
            echo json_encode([]);
        }
    }

}
