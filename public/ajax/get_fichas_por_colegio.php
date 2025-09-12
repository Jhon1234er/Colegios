<?php
require_once '../../models/Ficha.php';

header('Content-Type: application/json');

try {
    $colegio_id = $_POST['colegio_id'] ?? $_GET['colegio_id'] ?? null;

    if (!$colegio_id) {
        echo json_encode([]);
        exit;
    }

    $fichaModel = new Ficha();
    $fichas = $fichaModel->obtenerPorColegio($colegio_id);
    
    echo json_encode($fichas);

} catch (Exception $e) {
    error_log("Error en get_fichas_por_colegio.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}
