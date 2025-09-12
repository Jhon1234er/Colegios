<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID de estudiante requerido']);
        exit;
    }

    $studentId = $_GET['id'];
    $pdo = Database::conectar();

    // Obtener datos del acudiente del estudiante
    $stmt = $pdo->prepare("
        SELECT 
            e.nombre_completo_acudiente,
            e.tipo_documento_acudiente,
            e.numero_documento_acudiente,
            e.telefono_acudiente,
            e.parentesco,
            e.ocupacion
        FROM estudiantes e
        WHERE e.id = ?
    ");
    
    $stmt->execute([$studentId]);
    $guardian = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($guardian) {
        echo json_encode([
            'success' => true,
            'guardian' => $guardian
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró información del acudiente'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos del acudiente: ' . $e->getMessage()
    ]);
}
?>
