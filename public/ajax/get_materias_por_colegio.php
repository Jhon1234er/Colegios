<?php
require_once '../../config/db.php';

header('Content-Type: application/json');

if (isset($_GET['colegio_id'])) {
    $pdo = Database::conectar();
    $stmt = $pdo->prepare("
        SELECT m.id, m.nombre
        FROM materias m
        INNER JOIN colegio_materia cm ON cm.materia_id = m.id
        WHERE cm.colegio_id = ?
    ");
    $stmt->execute([$_GET['colegio_id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

echo json_encode([]);
