<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Colegio.php';
require_once __DIR__ . '/../../models/Ficha.php';
require_once __DIR__ . '/../../models/Materia.php';

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

    // Adjuntar fichas asociadas al colegio usando ficha_colegio
    $fichaModel = new Ficha();
    try {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("
            SELECT f.id, f.numero, f.nombre 
            FROM fichas f
            INNER JOIN ficha_colegio fc ON f.id = fc.ficha_id
            WHERE fc.colegio_id = ?
            ORDER BY f.numero
        ");
        $stmt->execute([$id]);
        $colegio['fichas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $colegio['fichas'] = [];
    }

    // Adjuntar cursos asociados al colegio usando colegio_curso
    try {
        $pdo = Database::conectar();
        $stmt = $pdo->prepare("
            SELECT c.id, c.codigo, c.nombre 
            FROM cursos c
            INNER JOIN colegio_curso cc ON c.id = cc.curso_id
            WHERE cc.colegio_id = ?
            ORDER BY c.nombre
        ");
        $stmt->execute([$id]);
        $colegio['cursos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $colegio['cursos'] = [];
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
