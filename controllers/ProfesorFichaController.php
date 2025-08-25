<?php
require_once __DIR__ . '/../models/Profesor.php';


if (!isset($_SESSION['usuario']['profesor_id'])) {
    echo json_encode([]);
    exit;
}

$profesor_id = $_SESSION['usuario']['profesor_id'];

$profesorModel = new Profesor();
$fichas = $profesorModel->obtenerFichasPorProfesor($profesor_id);

header('Content-Type: application/json');
echo json_encode($fichas);
