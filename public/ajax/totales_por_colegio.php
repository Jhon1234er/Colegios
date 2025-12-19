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

    // Contar cursos/materias asociados al colegio (colegio_curso)
    $stmt_materias = $pdo->prepare("SELECT COUNT(DISTINCT curso_id) AS total FROM colegio_curso WHERE colegio_id = ?");
    $stmt_materias->execute([$colegio_id]);
    $materias = (int)$stmt_materias->fetchColumn();

    // Contar facilitadores (profesores) por fichas del colegio (nuevo esquema usando ficha_colegio)
    try {
        $stmt_profesores = $pdo->prepare("SELECT COUNT(DISTINCT p.id) AS total
                                          FROM facilitadores p
                                          JOIN facilitador_ficha pf ON p.id = pf.facilitador_id
                                          JOIN ficha_colegio fc ON fc.ficha_id = pf.ficha_id
                                          WHERE fc.colegio_id = ?");
        $stmt_profesores->execute([$colegio_id]);
        $profesores = (int)$stmt_profesores->fetchColumn();
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S22' && $e->getCode() !== '42S02') throw $e;
        // Fallback legacy: profesor_ficha y fichas.colegio
        $stmt_profesores = $pdo->prepare("SELECT COUNT(DISTINCT p.id) AS total
                                          FROM facilitadores p
                                          JOIN profesor_ficha pf ON p.id = pf.profesor_id
                                          JOIN fichas f ON pf.ficha_id = f.id
                                          WHERE (f.colegio_id = ? OR f.colegio = ?)");
        $stmt_profesores->execute([$colegio_id, $colegio_id]);
        $profesores = (int)$stmt_profesores->fetchColumn();
    }

    // Contar fichas del colegio (nuevo esquema usando ficha_colegio)
    try {
        $stmt_fichas = $pdo->prepare("SELECT COUNT(DISTINCT f.id) AS total
                                      FROM ficha_colegio fc
                                      JOIN fichas f ON f.id = fc.ficha_id
                                      WHERE fc.colegio_id = ?");
        $stmt_fichas->execute([$colegio_id]);
        $fichas = (int)$stmt_fichas->fetchColumn();
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S22' && $e->getCode() !== '42S02') throw $e;
        // Fallback legacy: contar desde fichas directamente
        try {
            $stmt_fichas = $pdo->prepare("SELECT COUNT(DISTINCT f.id) AS total FROM fichas f WHERE f.colegio_id = ?");
            $stmt_fichas->execute([$colegio_id]);
            $fichas = (int)$stmt_fichas->fetchColumn();
        } catch (PDOException $e2) {
            if ($e2->getCode() !== '42S22') throw $e2;
            $stmt_fichas = $pdo->prepare("SELECT COUNT(DISTINCT f.id) AS total FROM fichas f WHERE f.colegio = ?");
            $stmt_fichas->execute([$colegio_id]);
            $fichas = (int)$stmt_fichas->fetchColumn();
        }
    }

    // Contar aprendices (estudiantes)
    $stmt_estudiantes = $pdo->prepare("SELECT COUNT(DISTINCT id) AS total FROM aprendices WHERE colegio_id = ?");
    $stmt_estudiantes->execute([$colegio_id]);
    $estudiantes = (int)$stmt_estudiantes->fetchColumn();

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
