<?php
require_once __DIR__ . '/../../models/Asistencia.php';

header('Content-Type: application/json; charset=utf-8');

$colegio_id = isset($_GET['colegio_id']) ? intval($_GET['colegio_id']) : 0;

file_put_contents(__DIR__.'/debug_test.txt', date('H:i:s') . " colegio_id recibido: $colegio_id\n", FILE_APPEND);

if ($colegio_id <= 0) {
    file_put_contents(__DIR__.'/debug_test.txt', date('H:i:s') . " colegio_id inválido\n", FILE_APPEND);
    echo json_encode([
        'fichas' => [],
        'alertas' => []
    ]);
    exit;
}

$asistenciaModel = new Asistencia();

try {
    $fichas_data = $asistenciaModel->obtenerFallasPorFicha($colegio_id);
    file_put_contents(__DIR__.'/debug_test.txt', date('H:i:s') . " fichas_data: " . print_r($fichas_data, true) . "\n", FILE_APPEND);

    $alertas = $asistenciaModel->obtenerEstudiantesConFallas($colegio_id, 3);
    file_put_contents(__DIR__.'/debug_test.txt', date('H:i:s') . " alertas: " . print_r($alertas, true) . "\n", FILE_APPEND);

    echo json_encode([
        'fichas' => $fichas_data ?: [],
        'alertas' => $alertas ?: []
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    file_put_contents(__DIR__.'/debug_test.txt', date('H:i:s') . " ERROR EXCEPCION: " . $e->getMessage() . "\n", FILE_APPEND);
    error_log('Error en asistencias_por_colegio: ' . $e->getMessage());
    echo json_encode([
        'fichas' => [],
        'alertas' => []
    ]);
}
