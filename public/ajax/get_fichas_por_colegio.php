<?php
require_once '../../config/db.php';

header('Content-Type: application/json');

try {
    $colegio_id = $_POST['colegio_id'] ?? $_GET['colegio_id'] ?? null;

    if (!$colegio_id) {
        echo json_encode([]);
        exit;
    }

    $pdo = Database::conectar();
    $stmt = $pdo->prepare("SELECT id, nombre FROM fichas WHERE colegio_id = ?");
    $stmt->execute([$colegio_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
