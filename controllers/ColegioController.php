<?php
require_once __DIR__ . '/../models/Colegio.php';
require_once __DIR__ . '/../models/Facilitador.php';

class ColegioController {
    private $colegioModel;
    private $facilitadorModel;

    public function __construct() {
        $this->colegioModel = new Colegio();
        $this->facilitadorModel = new Facilitador();
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

        try {
            $ok = $colegioModel->guardar($datos, $datos['materias'] ?? []);
        } catch (\PDOException $e) {
            // Error específico de base de datos (incluye chk_tel_colegio)
            $msg = 'No se pudo registrar el colegio. ';
            $raw = $e->getMessage();
            if (strpos($raw, 'chk_tel_colegio') !== false) {
                $msg .= 'El teléfono recibido desde la API no cumple el formato que impone la base de datos (chk_tel_colegio).';
            } else {
                $msg .= 'Ocurrió un error en la base de datos al guardar los datos.';
            }
            header('Location: /?page=colegios&action=crear&error=' . urlencode($msg));
            exit;
        } catch (\Throwable $e) {
            $msg = 'No se pudo registrar el colegio. Inténtalo nuevamente.';
            header('Location: /?page=colegios&action=crear&error=' . urlencode($msg));
            exit;
        }

        if ($ok) {
            header("Location: /?page=dashboard&success=1");
            exit;
        }

        $msg = 'No se pudo registrar el colegio. Verifica los datos e inténtalo de nuevo.';
        header('Location: /?page=colegios&action=crear&error=' . urlencode($msg));
        exit;
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
            $profes = $this->facilitadorModel->obtenerPorColegio($colegioId);

            // Mapear a estructura esperada por el frontend
            $respuesta = array_map(function ($p) {
                $nombre = trim(($p['apellidos'] ?? '') . ' ' . ($p['nombres'] ?? ''));
                if ($nombre === '') { $nombre = ($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? ''); }
                return [
                    'nombre_completo' => trim($nombre),
                    'materia' => $p['tipo_contrato'] ?? ''
                ];
            }, $profes);

            echo json_encode($respuesta);
        } else {
            echo json_encode([]);
        }
    }

}
