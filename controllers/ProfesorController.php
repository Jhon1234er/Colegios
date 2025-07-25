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
    }
