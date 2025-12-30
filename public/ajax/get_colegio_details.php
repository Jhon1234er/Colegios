<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Colegio.php';
require_once __DIR__ . '/../../models/Ficha.php';

try {
    $id = isset($_GET['id']) ? trim($_GET['id']) : '';
    if ($id === '') {
        echo json_encode(['success' => false, 'message' => 'ID de colegio requerido']);
        exit;
    }

    $model = new Colegio();
    $colegio = $model->obtenerPorId($id);

    if (!$colegio) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el colegio']);
        exit;
    }

    // Adjuntar fichas asociadas al colegio (si la estructura de BD lo permite)
    $fichaModel = new Ficha();
    try {
        $colegio['fichas'] = $fichaModel->obtenerPorColegio($id);
    } catch (Throwable $e) {
        // Si por algún motivo falla (por esquema antiguo, etc.), devolvemos lista vacía
        $colegio['fichas'] = [];
    }

    echo json_encode([
        'success' => true,
        'colegio' => $colegio,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos del colegio: ' . $e->getMessage(),
    ]);
}
