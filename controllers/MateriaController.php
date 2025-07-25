<?php
require_once __DIR__ . '/../models/Materia.php';

class MateriaController {
    private $materiaModel;

    public function __construct() {
        $this->materiaModel = new Materia();
    }

    // 📋 Mostrar todas las materias
    public function index() {
        $materias = $this->materiaModel->obtenerTodas();
        include __DIR__ . '/../views/Materia/lista.php';
    }

    // 📝 Mostrar formulario para crear
    public function crear() {
        include __DIR__ . '/../views/Materia/crear.php';
    }

    // 💾 Guardar nueva materia
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre'] ?? '');
            $codigo = trim($_POST['codigo'] ?? '');

            if (!empty($nombre) && !empty($codigo)) {
                $this->materiaModel->guardar($nombre, $codigo);
                header("Location: /?page=materias");
                exit;
            } else {
                $error = "Todos los campos son obligatorios.";
                include __DIR__ . '/../views/Materia/crear.php';
            }
        }
    }

    // ❌ Eliminar materia
    public function eliminar($id) {
        if ($id) {
            $this->materiaModel->eliminar($id);
            header("Location: /?page=materias");
            exit;
        }
    }

    // 🔄 Obtener materias por colegio (AJAX)
    public function obtenerPorColegioAjax() {
        if (isset($_GET['colegio_id'])) {
            $materias = $this->materiaModel->obtenerPorColegio($_GET['colegio_id']);
            header('Content-Type: application/json');
            echo json_encode($materias);
            exit;
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID de colegio no proporcionado']);
            exit;
        }
    }
    public function contar() {
            $materiaModel = new Materia();
            $totalMaterias = $materiaModel->contarMaterias();

            require 'views/dashboard.php'; 
        }
}
