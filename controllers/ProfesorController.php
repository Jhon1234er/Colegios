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

        public function fichasPorProfesor($profesorId) {
            $pdo = \Database::conectar();
            $stmt = $pdo->prepare("
                SELECT f.id, f.nombre 
                FROM fichas f
                INNER JOIN profesor_ficha pf ON pf.ficha_id = f.id
                WHERE pf.profesor_id = ?
            ");
            $stmt->execute([$profesorId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        public function estudiantesPorFicha($fichaId) {
            $pdo = \Database::conectar();
            $stmt = $pdo->prepare("
                SELECT e.id, u.nombres, u.apellidos, e.grado, e.jornada, e.nombre_completo_acudiente, e.telefono_acudiente, e.parentesco
                FROM estudiantes e
                INNER JOIN usuarios u ON e.usuario_id = u.id
                WHERE e.ficha_id = ?
            ");
            $stmt->execute([$fichaId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    }
