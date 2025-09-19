<?php
require_once __DIR__ . '/../models/Profesor.php';

class ProfesorController {
    private $profesorModel;

    public function __construct() {
        $this->profesorModel = new Profesor();
    }

    /* 📌 Listado de profesores */
    public function index() {
        $profesores = $this->profesorModel->obtenerTodos();
        include __DIR__ . '/../views/Profesor/lista.php';
    }

    /* 📌 Formulario crear profesor */
    public function crear() {
        include __DIR__ . '/../views/Profesor/crear.php';
    }

    /* 📌 Guardar profesor nuevo */
    public function guardar() {
        start_secure_session();
        require_login();
        require_role(1);
        csrf_validate();

        $datos = [
            'nombres'              => trim($_POST['nombres'] ?? ''),
            'apellidos'            => trim($_POST['apellidos'] ?? ''),
            'tipo_documento'       => $_POST['tipo_documento'] ?? '',
            'numero_documento'     => trim($_POST['numero_documento'] ?? ''),
            'correo_electronico'   => trim($_POST['correo_electronico'] ?? ''),
            'telefono'             => trim($_POST['telefono'] ?? ''),
            'fecha_nacimiento'     => $_POST['fecha_nacimiento'] ?? '',
            'genero'               => $_POST['genero'] ?? '',
            'password'             => $_POST['password'] ?? '',
            'titulo_academico'     => trim($_POST['titulo_academico'] ?? ''),
            'especialidad'         => trim($_POST['especialidad'] ?? ''),
            'rh'                   => trim($_POST['rh'] ?? ''),
            'correo_institucional' => trim($_POST['correo_institucional'] ?? ''),
            'tip_contrato'         => $_POST['tip_contrato'] ?? '',
            'rol_id'               => 2
        ];

        try {
            $this->profesorModel->guardar($datos);
            header('Location: /?page=profesores&success=1');
            exit;
        } catch (Exception $e) {
            echo "❌ Error al guardar profesor: " . $e->getMessage();
        }
    }

    /* 📌 Contar profesores (para dashboard) */
    public function contar() {
        $totalprofesores = $this->profesorModel->contarProfesores();
        require 'views/dashboard.php'; 
    }

    public function obtenerFichasPorProfesor() {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $usuario_id = $_SESSION['usuario']['id'] ?? null;
            if (!$usuario_id) {
                throw new Exception('No se pudo identificar el usuario');
            }

            // Obtener profesor_id del usuario actual
            require_once __DIR__ . '/../config/db.php';
            $pdo = Database::conectar();
            $stmt = $pdo->prepare("SELECT id FROM profesores WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            $profesor = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profesor) {
                throw new Exception('No se encontró el perfil de profesor');
            }

            $profesor_id = $profesor['id'];

            // Obtener fichas propias
            $fichasPropias = $this->profesorModel->obtenerFichasPorProfesor($profesor_id);

            // Obtener fichas compartidas (solo las aceptadas)
            $stmt = $pdo->prepare("
                SELECT DISTINCT f.id, f.numero AS numero_ficha, f.nombre, 'compartida' as tipo
                FROM fichas f
                INNER JOIN fichas_compartidas fc ON f.id = fc.ficha_id
                WHERE fc.profesor_compartido_id = ? AND fc.estado = 'aceptada'
                ORDER BY f.numero
            ");
            $stmt->execute([$profesor_id]);
            $fichasCompartidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Marcar fichas propias
            foreach ($fichasPropias as &$ficha) {
                $ficha['tipo'] = 'propia';
            }

            // Combinar ambas listas
            $todasLasFichas = array_merge($fichasPropias, $fichasCompartidas);

            // Asegurar que todos los elementos tengan la misma estructura
            foreach ($todasLasFichas as &$ficha) {
                // Asegurar que existan los campos requeridos
                $ficha['id'] = $ficha['id'] ?? 0;
                $ficha['numero_ficha'] = $ficha['numero_ficha'] ?? '';
                $ficha['nombre'] = $ficha['nombre'] ?? 'Sin nombre';
                $ficha['tipo'] = $ficha['tipo'] ?? 'desconocido';
                $ficha['dias_semana'] = $ficha['dias_semana'] ?? ['lunes', 'miércoles', 'viernes'];
                $ficha['hora_inicio'] = $ficha['hora_inicio'] ?? '07:00:00';
                $ficha['hora_fin'] = $ficha['hora_fin'] ?? '17:00:00';
            }

            // Enviar respuesta JSON
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($todasLasFichas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
            
        } catch (Exception $e) {
            // En caso de error, devolver un array vacío con código de error 500
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true,
                'message' => 'Error al cargar las fichas: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * Método para manejar la ruta profesorficha
     */
    public function profesorficha() {
        $this->obtenerFichasPorProfesor();
    }
}
