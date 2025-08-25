<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$colegio_id = filter_input(INPUT_GET, 'colegio_id', FILTER_VALIDATE_INT);

if (!$colegio_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de colegio no válido']);
    exit;
}

try {
    $pdo = Database::conectar();

    // Contar materias
    $stmt_materias = $pdo->prepare("SELECT COUNT(DISTINCT materia_id) AS total FROM colegio_materia WHERE colegio_id = ?");
    $stmt_materias->execute([$colegio_id]);
    $materias = $stmt_materias->fetchColumn();

    // Contar profesores
    $stmt_profesores = $pdo->prepare("SELECT COUNT(DISTINCT id) AS total FROM profesores WHERE colegio_id = ?");
    $stmt_profesores->execute([$colegio_id]);
    $profesores = $stmt_profesores->fetchColumn();

    // Contar fichas
    $stmt_fichas = $pdo->prepare("SELECT COUNT(DISTINCT f.id) AS total FROM fichas f JOIN colegio_materia cm ON f.materia_id = cm.materia_id WHERE cm.colegio_id = ?");
    $stmt_fichas->execute([$colegio_id]);
    $fichas = $stmt_fichas->fetchColumn();

    // Contar estudiantes
    $stmt_estudiantes = $pdo->prepare("SELECT COUNT(DISTINCT e.id) AS total FROM estudiantes e JOIN fichas f ON e.ficha_id = f.id JOIN colegio_materia cm ON f.materia_id = cm.materia_id WHERE cm.colegio_id = ?");
    $stmt_estudiantes->execute([$colegio_id]);
    $estudiantes = $stmt_estudiantes->fetchColumn();

    echo json_encode([
        'materias' => $materias,
        'profesores' => $profesores,
        'fichas' => $fichas,
        'estudiantes' => $estudiantes
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    // En un entorno de producción, registra el error en lugar de mostrarlo
    error_log($e->getMessage());
    echo json_encode(['error' => 'Error en el servidor al consultar la base de datos.']);
}
