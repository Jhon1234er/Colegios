<?php
require_once __DIR__ . '/../models/Materia.php';

class MateriaController {
    private $materiaModel;

    public function __construct() {
        $this->materiaModel = new Materia();
    }

    public function index() {
        // Paginación estilo Gmail
        $perPage = 15;
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
        $linea = isset($_GET['linea']) ? trim($_GET['linea']) : '';
        $pageNum = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

        $totalMaterias = (int)$this->materiaModel->contarConFiltros($q, $estado, $linea);
        $totalPages = max(1, (int)ceil($totalMaterias / $perPage));
        if ($pageNum > $totalPages) { $pageNum = $totalPages; }
        $offset = ($pageNum - 1) * $perPage;

        $materias = $this->materiaModel->obtenerPaginadoConFiltros($q, $estado, $linea, $perPage, $offset);
        $lineas = $this->materiaModel->obtenerLineasDistinct();

        require __DIR__ . '/../views/Materia/index.php';
    }

    public function crear() {
        require __DIR__ . '/../views/Materia/crear.php';
    }

    public function guardar() {
        start_secure_session();
        require_login(); require_role(1);
        csrf_validate();
        
        // Detección de carga múltiple (arrays)
        if (isset($_POST['codigo']) && is_array($_POST['codigo'])) {
            $rows = [];
            $n = count($_POST['codigo']);
            for ($i = 0; $i < $n; $i++) {
                $codigo = trim($_POST['codigo'][$i] ?? '');
                $denom  = trim($_POST['denominacion'][$i] ?? '');
                $dur    = trim($_POST['duracion'][$i] ?? '');
                $ver    = $_POST['version'][$i] ?? '';
                $linea  = trim($_POST['linea_tecnoacademia'][$i] ?? '');
                if ($codigo === '' && $denom === '' && $dur === '' && $linea === '') continue;
                $rows[] = [
                    'codigo' => $codigo,
                    'denominacion' => $denom,
                    'duracion' => $dur,
                    'version' => $ver !== '' ? (int)$ver : null,
                    'linea_tecnoacademia' => $linea,
                    'nombre' => $denom,
                ];
            }
            if (!empty($rows)) {
                $insertados = $this->materiaModel->guardarMultiple($rows);
                header("Location: ?page=materias&success={$insertados}");
                exit;
            } else {
                header("Location: ?page=materias&action=crear&error=Sin+datos");
                exit;
            }
        }

        // Carga simple
        $data = [
            'codigo' => trim($_POST['codigo'] ?? ''),
            'denominacion' => trim($_POST['denominacion'] ?? ''),
            'duracion' => trim($_POST['duracion'] ?? ''),
            'version' => isset($_POST['version']) && $_POST['version'] !== '' ? (int)$_POST['version'] : null,
            'linea_tecnoacademia' => trim($_POST['linea_tecnoacademia'] ?? ''),
            'nombre' => trim($_POST['denominacion'] ?? ''),
        ];
        if ($data['nombre'] !== '') {
            if ($this->materiaModel->guardar($data)) {
                header("Location: ?page=materias&success=1");
                exit;
            }
        }
        echo "❌ Error al guardar materia.";
    }

    public function editar() {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $materia = $this->materiaModel->obtenerPorId($id);
            require __DIR__ . '/../views/Materia/editar.php';
        }
    }

    public function actualizar() {
        start_secure_session();
        require_login(); require_role(1);
        csrf_validate();

        $id = $_POST['id'] ?? null;
        $nombre = trim($_POST['nombre'] ?? '');

        if ($id) {
            $data = [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'denominacion' => trim($_POST['denominacion'] ?? $nombre),
                'duracion' => trim($_POST['duracion'] ?? ''),
                'version' => isset($_POST['version']) && $_POST['version'] !== '' ? (int)$_POST['version'] : null,
                'linea_tecnoacademia' => trim($_POST['linea_tecnoacademia'] ?? ''),
                'nombre' => trim($_POST['nombre'] ?? $nombre),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
            ];
            $this->materiaModel->actualizar($id, $data);
            header("Location: ?page=materias&updated=1");
            exit;
        } else {
            echo "❌ El nombre es obligatorio.";
        }
    }

    public function eliminar() {
        // En adelante, no usamos eliminar físico. Redirigimos a suspender.
        $this->suspender();
    }

    public function contar() {
        $totalMaterias = $this->materiaModel->contarMaterias();
        require 'views/dashboard.php'; 
    }

    public function suspender() {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $this->materiaModel->suspender((int)$id);
            header("Location: ?page=materias&status=suspended");
            exit;
        }
    }

    public function activar() {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $this->materiaModel->activar((int)$id);
            header("Location: ?page=materias&status=active");
            exit;
        }
    }
}
