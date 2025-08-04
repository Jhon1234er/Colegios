<?php
require_once __DIR__ . '/../models/Estudiante.php';

$ficha_id = $_GET['ficha_id'] ?? null;
if (!$ficha_id) {
    echo json_encode([]);
    exit;
}

$estudianteModel = new Estudiante();
$estudiantes = $estudianteModel->obtenerPorFicha($ficha_id);

header('Content-Type: application/json');
echo json_encode($estudiantes);
