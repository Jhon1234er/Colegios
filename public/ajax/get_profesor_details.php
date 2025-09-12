<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Profesor.php';

try {
    $id = isset($_GET['id']) ? trim($_GET['id']) : '';
    if ($id === '') {
        echo json_encode(['success' => false, 'message' => 'ID de profesor requerido']);
        exit;
    }

    $pdo = Database::conectar();

    // Datos principales del profesor
    $stmt = $pdo->prepare("SELECT 
            p.id,
            u.nombres,
            u.apellidos,
            u.tipo_documento,
            u.numero_documento,
            u.correo_electronico,
            u.telefono,
            p.titulo_academico,
            p.especialidad,
            p.fecha_ingreso,
            p.rh,
            p.correo_institucional,
            p.tip_contrato,
            c.nombre AS colegio_nombre
        FROM profesores p
        INNER JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN colegios c ON p.colegio_id = c.id
        WHERE p.id = ?");
    $stmt->execute([$id]);
    $profesor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profesor) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el profesor']);
        exit;
    }

    // Fichas asignadas
    $profModel = new Profesor();
    $fichas = $profModel->obtenerFichasPorProfesor($id);

    // Materias asignadas
    $materias = $profModel->obtenerMateriasPorProfesor($id);

    // Próximas clases de la semana (best-effort: si la tabla no existe, devolver vacía)
    $clases = [];
    try {
        // Semana actual
        $hoy = new DateTime('now');
        $inicioSemana = clone $hoy; $inicioSemana->modify('monday this week')->setTime(0,0,0);
        $finSemana = clone $inicioSemana; $finSemana->modify('+6 days')->setTime(23,59,59);

        // Intentar adivinar estructura de horarios: horarios_fichas con profesor_id
        $sql = "SELECT id, titulo, fecha_inicio, fecha_fin, aula, color, estado
                FROM horarios_fichas
                WHERE profesor_id = ? AND fecha_inicio BETWEEN ? AND ?
                ORDER BY fecha_inicio ASC
                LIMIT 50";
        $st2 = $pdo->prepare($sql);
        $st2->execute([$id, $inicioSemana->format('Y-m-d H:i:s'), $finSemana->format('Y-m-d H:i:s')]);
        $clases = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // fallback silencioso
        $clases = [];
    }

    echo json_encode([
        'success'  => true,
        'profesor' => $profesor,
        'fichas'   => $fichas,
        'materias' => $materias,
        'clases'   => $clases,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos del profesor: ' . $e->getMessage()
    ]);
}
