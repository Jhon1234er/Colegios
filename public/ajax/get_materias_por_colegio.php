<?php
require_once '../../config/db.php';

if (isset($_GET['colegio_id'])) {
    $pdo = Database::conectar();
    $stmt = $pdo->prepare("
        SELECT m.id, m.nombre 
        FROM colegio_materia cm
        JOIN materias m ON cm.materia_id = m.id
        WHERE cm.colegio_id = ?
    ");
    $stmt->execute([$_GET['colegio_id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
