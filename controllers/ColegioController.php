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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre'] ?? '');
            $codigo_dane = trim($_POST['codigo_dane'] ?? '');
            $nit = trim($_POST['nit'] ?? '');
            $tipo = trim($_POST['tipo_institucion'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $municipio = trim($_POST['municipio'] ?? '');
            $departamento = trim($_POST['departamento'] ?? '');
            $materias = $_POST['materias'] ?? [];
            $jornada = $_POST['jornada'] ?? [];
            $grados = $_POST['grados'] ?? [];
            $calendario = $_POST['calendario'] ?? [];

            if (
                !empty($nombre) && !empty($codigo_dane) && !empty($nit) &&
                !empty($tipo) && !empty($direccion) && !empty($telefono) &&
                !empty($correo) && !empty($municipio) && !empty($departamento)
            ) {
                $this->colegioModel->guardar([
                    'nombre' => $nombre,
                    'codigo_dane' => $codigo_dane,
                    'nit' => $nit,
                    'tipo_institucion' => $tipo,
                    'direccion' => $direccion,
                    'telefono' => $telefono,
                    'correo' => $correo,
                    'municipio' => $municipio,
                    'departamento' => $departamento,
                    'jornada' => $jornada,
                    'grados' => $grados,
                    'calendario' => $calendario
                ], $materias);

                header("Location: /?page=colegios");
                exit;
            } else {
                $error = "Todos los campos son obligatorios.";
                include __DIR__ . '/../views/Colegio/crear.php';
            }
        }
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
