<?php
require_once __DIR__ . '/../../models/Asistencia.php';

header('Content-Type: application/json; charset=utf-8');

$colegio_id = isset($_GET['colegio_id']) ? intval($_GET['colegio_id']) : 0;


if ($colegio_id <= 0) {
    echo json_encode([
        'fichas' => [],
        'alertas' => []
    ]);
    exit;
}

$asistenciaModel = new Asistencia();

try {
    $fichas_data = $asistenciaModel->obtenerFallasPorFicha($colegio_id);

    $alertas = $asistenciaModel->obtenerEstudiantesConFallas($colegio_id, 3);

    echo json_encode([
        'fichas' => $fichas_data ?: [],
        'alertas' => $alertas ?: []
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Error en asistencias_por_colegio: ' . $e->getMessage());
    echo json_encode([
        'fichas' => [],
        'alertas' => []
    ]);
}
