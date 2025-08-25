<?php
require_once __DIR__ . '/../models/Materia.php';

class MateriaController {
    private $materiaModel;

    public function __construct() {
        $this->materiaModel = new Materia();
    }

    public function index() {
        $materias = $this->materiaModel->obtenerTodas();
        require __DIR__ . '/../views/Materia/index.php';
    }

    public function crear() {
        require __DIR__ . '/../views/Materia/crear.php';
    }

    public function guardar() {
        start_secure_session();
        require_login(); require_role(1);
        csrf_validate();

        $nombre = trim($_POST['nombre'] ?? '');

        if ($nombre) {
            if ($this->materiaModel->guardar(['nombre' => $nombre])) {
                header("Location: /?page=materias&success=1");
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

        if ($id && $nombre) {
            $this->materiaModel->actualizarNombre($id, $nombre);
            header("Location: /?page=materias&updated=1");
            exit;
        } else {
            echo "❌ El nombre es obligatorio.";
        }
    }

    public function eliminar() {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $this->materiaModel->eliminar($id);
            header("Location: /?page=materias&deleted=1");
            exit;
        }
    }

    public function contar() {
        $totalMaterias = $this->materiaModel->contarMaterias();
        require 'views/dashboard.php'; 
    }
}
