<?php
require_once __DIR__ . '/../../models/Asistencia.php';

header('Content-Type: application/json; charset=utf-8');

$colegio_id = isset($_GET['colegio_id']) ? intval($_GET['colegio_id']) : 0;
// Umbral de fallas configurable (por defecto 3)
$min_fallas = isset($_GET['min_fallas']) ? max(1, intval($_GET['min_fallas'])) : 3;

$asistenciaModel = new Asistencia();

try {
    if ($colegio_id <= 0) {
        // Estadísticas GLOBALes (todos los colegios)
        $fichas_data = $asistenciaModel->obtenerFallasGlobales();
        // Para globales no calculamos listado de alertas detalladas (puede ser muy grande)
        $alertas = [];
    } else {
        // Estadísticas por colegio específico (comportamiento existente)
        $fichas_data = $asistenciaModel->obtenerFallasPorFicha($colegio_id);
        $alertas = $asistenciaModel->obtenerEstudiantesConFallas($colegio_id, $min_fallas);
    }

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
