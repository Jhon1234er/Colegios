<?php
require_once __DIR__ . '/../models/Profesor.php';

class ProfesorController {
    private $profesorModel;

    public function __construct() {
        $this->profesorModel = new Profesor();
    }

    public function index() {
        $profesores = $this->profesorModel->obtenerTodos();
        include __DIR__ . '/../views/Profesor/lista.php';
    }

    public function crear() {
        include __DIR__ . '/../views/Profesor/crear.php';
    }

    public function guardar() {
        start_secure_session();
        require_login(); require_role(1);
        csrf_validate();

        $datos = [
            'nombres'             => trim($_POST['nombres'] ?? ''),
            'apellidos'           => trim($_POST['apellidos'] ?? ''),
            'tipo_documento'      => $_POST['tipo_documento'] ?? '',
            'numero_documento'    => trim($_POST['numero_documento'] ?? ''),
            'correo_electronico'  => trim($_POST['correo_electronico'] ?? ''),
            'telefono'            => trim($_POST['telefono'] ?? ''),
            'fecha_nacimiento'    => $_POST['fecha_nacimiento'] ?? '',
            'genero'              => $_POST['genero'] ?? '',
            'password'            => $_POST['password'] ?? '',
            'titulo_academico'    => trim($_POST['titulo_academico'] ?? ''),
            'especialidad'        => trim($_POST['especialidad'] ?? ''),
            'rh'                  => trim($_POST['rh'] ?? ''),
            'correo_institucional'=> trim($_POST['correo_institucional'] ?? ''),
            'tip_contrato'        => $_POST['tip_contrato'] ?? '',
            'rol_id'              => 2
        ];

        try {
            $this->profesorModel->guardar($datos);
            header('Location: /?page=profesores&success=1');
            exit;
        } catch (Exception $e) {
            echo "❌ Error al guardar profesor: " . $e->getMessage();
        }
    }

    public function contar() {
        $totalprofesores = $this->profesorModel->contarProfesores();
        require 'views/dashboard.php'; 
    }
}
