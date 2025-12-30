<?php
// Asegurar salida JSON limpia
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ob_start();

set_error_handler(function($severity, $message, $file, $line){
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once __DIR__ . '/../../models/Ficha.php';

    $colegio_id = $_POST['colegio_id'] ?? $_GET['colegio_id'] ?? null;
    $colegio_id = is_numeric($colegio_id) ? (int)$colegio_id : 0;

    if ($colegio_id <= 0) {
        ob_end_clean();
        echo json_encode([]);
        exit;
    }

    $fichaModel = new Ficha();
    $fichas = $fichaModel->obtenerPorColegio($colegio_id);

    ob_end_clean();
    echo json_encode($fichas, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error en get_fichas_por_colegio.php: " . $e->getMessage());
    http_response_code(500);
    // Limpiar posible HTML previo
    if (ob_get_length() !== false) { ob_end_clean(); }
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
} finally {
    restore_error_handler();
}
