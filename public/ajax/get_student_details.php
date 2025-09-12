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

    // Obtener datos completos del estudiante y acudiente
    $stmt = $pdo->prepare("
        SELECT 
            u.nombres,
            u.apellidos,
            CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo,
            u.tipo_documento,
            u.numero_documento,
            u.correo_electronico AS email,
            u.telefono,
            u.fecha_nacimiento,
            u.genero,
            e.grado,
            e.grupo,
            e.jornada,
            e.fecha_ingreso,
            e.estado,
            c.nombre AS colegio_nombre,
            f.nombre AS ficha_nombre,
            e.nombre_completo_acudiente,
            e.tipo_documento_acudiente,
            e.numero_documento_acudiente,
            e.telefono_acudiente,
            e.parentesco,
            e.ocupacion
        FROM estudiantes e
        INNER JOIN usuarios u ON e.usuario_id = u.id
        LEFT JOIN colegios c ON e.colegio_id = c.id
        LEFT JOIN fichas f ON e.ficha_id = f.id
        WHERE e.id = ?
    ");
    
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        echo json_encode([
            'success' => true,
            'student' => $student
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró el estudiante'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos del estudiante: ' . $e->getMessage()
    ]);
}
?>
