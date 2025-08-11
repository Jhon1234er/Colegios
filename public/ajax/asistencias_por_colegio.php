<?php
require_once __DIR__ . '/../../models/Asistencia.php';

// Forzar respuesta JSON y UTF-8
header('Content-Type: application/json; charset=utf-8');

// Sanitizar entrada
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
    // Obtener todas las fichas de ese colegio y el total de fallas por ficha
    $fichas_data = $asistenciaModel->obtenerFallasPorFicha($colegio_id);

    // Buscar estudiantes con >= 3 fallas
    $alertas = $asistenciaModel->obtenerEstudiantesConFallas($colegio_id, 3);

    echo json_encode([
        'fichas' => $fichas_data ?: [],
        'alertas' => $alertas ?: []
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    // Si ocurre un error, devolver JSON vacío y loguear
    error_log('Error en asistencias_por_colegio: ' . $e->getMessage());
    echo json_encode([
        'fichas' => [],
        'alertas' => []
    ]);
}
