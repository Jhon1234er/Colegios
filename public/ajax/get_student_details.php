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

    $student = null;

    // Resolver si el ID recibido es de aprendices.id o usuarios.id (considerar variantes legacy)
    $aprendizId = null; $usuarioId = null;
    try {
        $chk = $pdo->prepare("SELECT id, usuario_id FROM aprendices WHERE id = ? LIMIT 1");
        $chk->execute([$studentId]);
        if ($row = $chk->fetch(PDO::FETCH_ASSOC)) { $aprendizId = (int)$row['id']; $usuarioId = isset($row['usuario_id']) ? (int)$row['usuario_id'] : null; }
    } catch (\Throwable $e) { /* noop */ }
    if ($usuarioId === null) {
        // intentar por usuario_id
        try {
            $chk2 = $pdo->prepare("SELECT id, usuario_id FROM aprendices WHERE usuario_id = ? LIMIT 1");
            $chk2->execute([$studentId]);
            if ($row2 = $chk2->fetch(PDO::FETCH_ASSOC)) { $aprendizId = (int)$row2['id']; $usuarioId = isset($row2['usuario_id']) ? (int)$row2['usuario_id'] : null; }
        } catch (\Throwable $e) { /* noop */ }
    }
    if ($usuarioId === null) {
        // intentar legacy: columna 'usuario'
        try {
            $chk3 = $pdo->prepare("SELECT id, usuario AS usuario_id FROM aprendices WHERE usuario = ? LIMIT 1");
            $chk3->execute([$studentId]);
            if ($row3 = $chk3->fetch(PDO::FETCH_ASSOC)) { $aprendizId = (int)$row3['id']; $usuarioId = isset($row3['usuario_id']) ? (int)$row3['usuario_id'] : null; }
        } catch (\Throwable $e) { /* noop */ }
    }
    if ($usuarioId === null) {
        // último recurso: buscar por número de documento del usuario
        try {
            $getUser = $pdo->prepare("SELECT id, numero_documento FROM usuarios WHERE id = ? LIMIT 1");
            $getUser->execute([$studentId]);
            if ($urow = $getUser->fetch(PDO::FETCH_ASSOC)) {
                $usuarioId = (int)$urow['id'];
                $doc = trim((string)($urow['numero_documento'] ?? ''));
                if ($doc !== '') {
                    // mapear al aprendiz por documento (vía join)
                    try {
                        $m = $pdo->prepare("SELECT a.id, a.usuario_id FROM aprendices a INNER JOIN usuarios u ON a.usuario_id = u.id WHERE u.numero_documento = ? LIMIT 1");
                        $m->execute([$doc]);
                        if ($mr = $m->fetch(PDO::FETCH_ASSOC)) { $aprendizId = (int)$mr['id']; }
                    } catch (\Throwable $e) { /* noop */ }
                }
            }
        } catch (\Throwable $e) { /* noop */ }
    }

    // Intento extra: siempre que tengamos algún identificador, obtener
    // una fila "cruda" de aprendices para completar grado/jornada/estado/ficha/colegio
    $extraAprendiz = null;
    try {
        if ($aprendizId !== null || $usuarioId !== null) {
            // En la mayoría de BD, familiares.aprendiz_id referencia a aprendices.usuario_id
            // pero aquí solo queremos los datos académicos básicos.
            $whereCol = $aprendizId !== null ? 'a.id' : 'a.usuario_id';
            $param   = $aprendizId !== null ? $aprendizId : $usuarioId;
            $sqlExtra = "SELECT a.grado, a.grupo, a.jornada, a.fecha_ingreso, a.estado,
                                 a.ficha_id,
                                 f.numero AS numero_ficha,
                                 c.nombre AS colegio_nombre,
                                 f.nombre AS ficha_nombre
                          FROM aprendices a
                          LEFT JOIN colegios c ON a.colegio_id = c.id
                          LEFT JOIN fichas f ON a.ficha_id = f.id
                          WHERE {$whereCol} = ? LIMIT 1";
            try {
                $stExtra = $pdo->prepare($sqlExtra);
                $stExtra->execute([$param]);
                $extraAprendiz = $stExtra->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (\Throwable $eE) {
                // Si falla por esquema viejo (sin colegio_id/ficha_id), intentamos variante legacy
                try {
                    $sqlExtraLegacy = "SELECT a.grado, a.grupo, a.jornada, a.fecha_ingreso, a.estado,
                                              a.ficha AS ficha_id,
                                              f.numero AS numero_ficha,
                                              c.nombre AS colegio_nombre,
                                              f.nombre AS ficha_nombre
                                       FROM aprendices a
                                       LEFT JOIN colegios c ON a.colegio_id = c.id
                                       LEFT JOIN fichas f ON f.id = a.ficha
                                       WHERE {$whereCol} = ? LIMIT 1";
                    $stExtra = $pdo->prepare($sqlExtraLegacy);
                    $stExtra->execute([$param]);
                    $extraAprendiz = $stExtra->fetch(PDO::FETCH_ASSOC) ?: null;
                } catch (\Throwable $eE2) { /* noop */ }
            }
        }
    } catch (\Throwable $eX) { /* noop */ }

    // Nuevo esquema (con jornada)
    $sqlNuevo = "
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
            a.grado,
            a.grupo,
            a.jornada,
            a.fecha_ingreso,
            a.estado,
            a.ficha_id,
            f.numero AS numero_ficha,
            c.nombre AS colegio_nombre,
            f.nombre AS ficha_nombre,
            a.nombre_completo_acudiente,
            a.tipo_documento_acudiente,
            a.numero_documento_acudiente,
            a.telefono_acudiente,
            a.parentesco,
            a.ocupacion
        FROM aprendices a
        INNER JOIN usuarios u ON a.usuario_id = u.id
        LEFT JOIN colegios c ON a.colegio_id = c.id
        LEFT JOIN fichas f ON a.ficha_id = f.id
        WHERE a.__WHERE__ = ?
    ";
    // Nuevo esquema (sin jornada)
    $sqlNuevoSinJornada = "
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
            a.grado,
            a.grupo,
            a.fecha_ingreso,
            a.estado,
            a.ficha_id,
            f.numero AS numero_ficha,
            c.nombre AS colegio_nombre,
            f.nombre AS ficha_nombre,
            a.nombre_completo_acudiente,
            a.tipo_documento_acudiente,
            a.numero_documento_acudiente,
            a.telefono_acudiente,
            a.parentesco,
            a.ocupacion
        FROM aprendices a
        INNER JOIN usuarios u ON a.usuario_id = u.id
        LEFT JOIN colegios c ON a.colegio_id = c.id
        LEFT JOIN fichas f ON a.ficha_id = f.id
        WHERE a.__WHERE__ = ?
    ";

    // Legacy (con jornada)
    $sqlLegacy = "
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
            a.grado,
            a.grupo,
            a.jornada,
            a.fecha_ingreso,
            a.estado,
            a.ficha AS ficha_id,
            f.numero AS numero_ficha,
            c.nombre AS colegio_nombre,
            f.nombre AS ficha_nombre,
            a.nombre_completo_acudiente,
            a.tipo_documento_acudiente,
            a.numero_documento_acudiente,
            a.telefono_acudiente,
            a.parentesco,
            a.ocupacion
        FROM aprendices a
        INNER JOIN usuarios u ON a.usuario_id = u.id
        LEFT JOIN colegios c ON a.colegio_id = c.id
        LEFT JOIN fichas f ON f.id = a.ficha
        WHERE a.__WHERE__ = ?
    ";
    // Legacy (sin jornada)
    $sqlLegacySinJornada = "
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
            a.grado,
            a.grupo,
            a.fecha_ingreso,
            a.estado,
            a.ficha AS ficha_id,
            f.numero AS numero_ficha,
            c.nombre AS colegio_nombre,
            f.nombre AS ficha_nombre,
            a.nombre_completo_acudiente,
            a.tipo_documento_acudiente,
            a.numero_documento_acudiente,
            a.telefono_acudiente,
            a.parentesco,
            a.ocupacion
        FROM aprendices a
        INNER JOIN usuarios u ON a.usuario_id = u.id
        LEFT JOIN colegios c ON a.colegio_id = c.id
        LEFT JOIN fichas f ON f.id = a.ficha
        WHERE a.__WHERE__ = ?
    ";

    // Intento 1: nuevo por a.id (si lo tenemos)
    try {
        if ($aprendizId !== null) {
            $st = $pdo->prepare(str_replace('__WHERE__', 'id', $sqlNuevo));
            $st->execute([$aprendizId]);
            $student = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (\Throwable $e) { /* noop */ }

    // Intento 1b: nuevo SIN jornada por a.id (si falló el anterior y lo tenemos)
    if (!$student) {
        try {
            if ($aprendizId !== null) {
                $st = $pdo->prepare(str_replace('__WHERE__', 'id', $sqlNuevoSinJornada));
                $st->execute([$aprendizId]);
                $student = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        } catch (\Throwable $e) { /* noop */ }
    }

    // Intento 2: nuevo por u.id (si lo tenemos)
    if (!$student) {
        try {
            if ($usuarioId !== null) {
                $st = $pdo->prepare(str_replace('__WHERE__', 'id', str_replace('WHERE a.__WHERE__ = ?', 'WHERE u.__WHERE__ = ?', $sqlNuevo)));
                $st->execute([$usuarioId]);
                $student = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        } catch (\Throwable $e) { /* noop */ }
    }

    // Intento 2b: nuevo SIN jornada por u.id
    if (!$student) {
        try {
            if ($usuarioId !== null) {
                $st = $pdo->prepare(str_replace('__WHERE__', 'id', str_replace('WHERE a.__WHERE__ = ?', 'WHERE u.__WHERE__ = ?', $sqlNuevoSinJornada)));
                $st->execute([$usuarioId]);
                $student = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        } catch (\Throwable $e) { /* noop */ }
    }

    // Intento 3: legacy por a.id (si lo tenemos)
    if (!$student) {
        try {
            if ($aprendizId !== null) {
                $st = $pdo->prepare(str_replace('__WHERE__', 'id', $sqlLegacy));
                $st->execute([$aprendizId]);
                $student = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        } catch (\Throwable $e) { /* noop */ }
    }

    // Intento 3b: legacy SIN jornada por a.id
    if (!$student) {
        try {
            if ($aprendizId !== null) {
                $st = $pdo->prepare(str_replace('__WHERE__', 'id', $sqlLegacySinJornada));
                $st->execute([$aprendizId]);
                $student = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        } catch (\Throwable $e) { /* noop */ }
    }

    // Intento 4: legacy por u.id (si lo tenemos)
    if (!$student) {
        try {
            if ($usuarioId !== null) {
                $st = $pdo->prepare(str_replace('__WHERE__', 'id', str_replace('WHERE a.__WHERE__ = ?', 'WHERE u.__WHERE__ = ?', $sqlLegacy)));
                $st->execute([$usuarioId]);
                $student = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        } catch (\Throwable $e) { /* noop */ }
    }

    // Intento 4b: legacy SIN jornada por u.id
    if (!$student) {
        try {
            if ($usuarioId !== null) {
                $st = $pdo->prepare(str_replace('__WHERE__', 'id', str_replace('WHERE a.__WHERE__ = ?', 'WHERE u.__WHERE__ = ?', $sqlLegacySinJornada)));
                $st->execute([$usuarioId]);
                $student = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        } catch (\Throwable $e) { /* noop */ }
    }

    if (!$student && $usuarioId !== null) {
        try {
            $st = $pdo->prepare("SELECT nombres, apellidos, CONCAT(nombres,' ',apellidos) AS nombre_completo, tipo_documento, numero_documento, correo_electronico AS email, telefono, fecha_nacimiento, genero FROM usuarios WHERE id = ?");
            $st->execute([$usuarioId]);
            $u = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($u) {
                $student = array_merge($u, [
                    'grado' => null,
                    'grupo' => null,
                    'jornada' => null,
                    'fecha_ingreso' => null,
                    'estado' => null,
                    'ficha_id' => null,
                    'numero_ficha' => null,
                    'colegio_nombre' => null,
                    'ficha_nombre' => null,
                    'nombre_completo_acudiente' => null,
                    'tipo_documento_acudiente' => null,
                    'numero_documento_acudiente' => null,
                    'telefono_acudiente' => null,
                    'parentesco' => null,
                    'ocupacion' => null,
                ]);
            }
        } catch (\Throwable $e) { /* noop */ }
    }

    // Si conseguimos datos extra del aprendiz desde la tabla aprendices,
    // combinarlos con lo que ya tengamos en $student (sea del join completo o del fallback).
    if ($student && $extraAprendiz) {
        $student = array_merge($student, array_filter($extraAprendiz, function($v){ return $v !== null; }));
    }

    // Completar datos del acudiente usando tabla familiares (es_acudiente = 1)
    if ($usuarioId !== null) {
        try {
            $sqlFam = "SELECT nombre_completo, tipo_documento, numero_documento, telefono,
                              COALESCE(parentesco, parentesco_otro) AS parentesco,
                              COALESCE(ocupacion, ocupacion_otro) AS ocupacion
                       FROM familiares
                       WHERE aprendiz_id = ? AND es_acudiente = 1
                       ORDER BY id DESC
                       LIMIT 1";
            $stFam = $pdo->prepare($sqlFam);
            $stFam->execute([$usuarioId]);
            if ($fam = $stFam->fetch(PDO::FETCH_ASSOC)) {
                $student['nombre_completo_acudiente']    = $fam['nombre_completo'] ?? null;
                $student['tipo_documento_acudiente']     = $fam['tipo_documento'] ?? null;
                $student['numero_documento_acudiente']   = $fam['numero_documento'] ?? null;
                $student['telefono_acudiente']           = $fam['telefono'] ?? null;
                $student['parentesco']                   = $fam['parentesco'] ?? null;
                $student['ocupacion']                    = $fam['ocupacion'] ?? null;
            }
        } catch (\Throwable $eFam) { /* noop */ }
    }

    echo json_encode(['success' => (bool)$student, 'student' => $student, 'message' => $student ? null : 'No se encontró el estudiante']);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos del estudiante: ' . $e->getMessage()
    ]);
}
?>
