<?php
    require_once __DIR__ . '/../models/Profesor.php';
    require_once __DIR__ . '/../models/Colegio.php';

    class ProfesorController {
        private $profesorModel;
        private $colegioModel;

        public function __construct() {
            $this->profesorModel = new Profesor();
            $this->colegioModel = new Colegio();
        }

        public function index() {
            $profesores = $this->profesorModel->obtenerTodos();
            include __DIR__ . '/../views/Profesor/lista.php';
        }

        public function crear() {
            $colegios = $this->colegioModel->obtenerTodos();
            include __DIR__ . '/../views/Profesor/crear.php';
        }

        public function guardar() {
            $datos = $_POST;
            $datos['rol_id'] = 2; // Fijar rol como profesor

            try {
                $this->profesorModel->guardar($datos);
                header('Location: /?page=profesores');
            } catch (Exception $e) {
                echo "Error al guardar profesor: " . $e->getMessage();
            }
        }
        public function contar() {
            $profesorModel = new Profesor();
            $totalprofesores = $profesorModel->contarProfesores();

            require 'views/dashboard.php'; 
        }


        public function fichasPorProfesor($profesor_id) {
            $pdo = \Database::conectar();

            $stmt = $pdo->prepare("
                SELECT f.id, f.nombre
                FROM fichas f
                INNER JOIN profesor_ficha pf ON pf.ficha_id = f.id
                WHERE pf.profesor_id = ?
            ");
            $stmt->execute([$profesor_id]);
            $fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode($fichas);
        }
    }
