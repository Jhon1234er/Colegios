<?php
// Script de prueba para verificar datos médicos
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = Database::conectar();
    
    // 1. Verificar aprendiz y su usuario_id
    $sqlAprendiz = "SELECT a.id as aprendiz_id, a.usuario_id, u.nombres, u.apellidos 
                    FROM aprendices a 
                    INNER JOIN usuarios u ON a.usuario_id = u.id 
                    WHERE a.id = 1";
    $stmtAprendiz = $pdo->prepare($sqlAprendiz);
    $stmtAprendiz->execute();
    $aprendiz = $stmtAprendiz->fetch(PDO::FETCH_ASSOC);
    
    echo "=== APRENDIZ ===\n";
    echo json_encode($aprendiz, JSON_PRETTY_PRINT) . "\n\n";
    
    // 2. Verificar datos médicos para ese usuario_id
    if ($aprendiz) {
        $sqlMedical = "SELECT * FROM informacion_medica WHERE usuario_id = ?";
        $stmtMedical = $pdo->prepare($sqlMedical);
        $stmtMedical->execute([$aprendiz['usuario_id']]);
        $medical = $stmtMedical->fetch(PDO::FETCH_ASSOC);
        
        echo "=== DATOS MÉDICOS ===\n";
        echo json_encode($medical, JSON_PRETTY_PRINT) . "\n\n";
        
        // 3. Verificar con JOIN completo
        $sqlJoin = "SELECT u.nombres, u.apellidos, 
                           im.padece_enfermedad, im.enfermedad_detalle, 
                           im.alergias, im.alergias_detalle,
                           im.medicamentos_permanentes, im.medicamentos_detalle,
                           im.discapacidad, im.discapacidad_detalle
                    FROM aprendices a
                    INNER JOIN usuarios u ON a.usuario_id = u.id
                    LEFT JOIN informacion_medica im ON u.id = im.usuario_id
                    WHERE a.id = 1";
        $stmtJoin = $pdo->prepare($sqlJoin);
        $stmtJoin->execute();
        $joinResult = $stmtJoin->fetch(PDO::FETCH_ASSOC);
        
        echo "=== RESULTADO JOIN COMPLETO ===\n";
        echo json_encode($joinResult, JSON_PRETTY_PRINT) . "\n\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
