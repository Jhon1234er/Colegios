<?php
require_once __DIR__ . '/../models/AreaDeConocimiento.php';

class AreaDeConocimientoController {
    private $areaDeConocimientoModel;

    public function __construct() {
        $this->areaDeConocimientoModel = new AreaDeConocimiento();
    }

    public function index() {
        // Paginación estilo Gmail
        $perPage = 15;
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
        $linea = isset($_GET['linea']) ? trim($_GET['linea']) : '';
        $pageNum = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

        $totalMaterias = (int)$this->areaDeConocimientoModel->contarConFiltros($q, $estado, $linea);
        $totalPages = max(1, (int)ceil($totalMaterias / $perPage));
        if ($pageNum > $totalPages) { $pageNum = $totalPages; }
        $offset = ($pageNum - 1) * $perPage;

        $materias = $this->areaDeConocimientoModel->obtenerPaginadoConFiltros($q, $estado, $linea, $perPage, $offset);
        $lineas = $this->areaDeConocimientoModel->obtenerLineasDistinct();

        require __DIR__ . '/../views/AreaDeConocimiento/tabla.php';
    }

    public function crear() {
        require __DIR__ . '/../views/AreaDeConocimiento/crear.php';
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
                $insertados = $this->areaDeConocimientoModel->guardarMultiple($rows);
                header("Location: ?page=cursos&success={$insertados}");
                exit;
            } else {
                header("Location: ?page=cursos&action=crear&error=Sin+datos");
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
            if ($this->areaDeConocimientoModel->guardar($data)) {
                header("Location: ?page=cursos&success=1");
                exit;
            }
        }
        echo "❌ Error al guardar materia.";
    }

    public function editar() {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $materia = $this->areaDeConocimientoModel->obtenerPorId($id);
            require __DIR__ . '/../views/AreaDeConocimiento/editar.php';
        }
    }

    public function actualizar() {
        start_secure_session();
        require_login(); require_role(1);
        csrf_validate();

        $id = $_POST['id'] ?? null;
        $nombre = trim($_POST['nombre'] ?? '');

        if ($id) {
            $data = $this->areaDeConocimientoModel->actualizar($id, [
                'codigo' => trim($_POST['codigo'] ?? ''),
                'denominacion' => trim($_POST['denominacion'] ?? $nombre),
                'duracion' => trim($_POST['duracion'] ?? ''),
                'version' => isset($_POST['version']) && $_POST['version'] !== '' ? (int)$_POST['version'] : null,
                'linea_tecnoacademia' => trim($_POST['linea_tecnoacademia'] ?? ''),
                'nombre' => trim($_POST['nombre'] ?? $nombre),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
            ]);
            header("Location: ?page=cursos&updated=1");
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
        $totalMaterias = $this->areaDeConocimientoModel->contarMaterias();
        require 'views/dashboard.php'; 
    }

    public function suspender() {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $this->areaDeConocimientoModel->suspender((int)$id);
            header("Location: ?page=cursos&status=suspended");
            exit;
        }
    }

    public function activar() {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $this->areaDeConocimientoModel->activar((int)$id);
            header("Location: ?page=cursos&status=active");
            exit;
        }
    }
}
