<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Facilitador.php';

try {
    $id = isset($_GET['id']) ? trim($_GET['id']) : '';
    if ($id === '') {
        echo json_encode(['success' => false, 'message' => 'ID de profesor requerido']);
        exit;
    }

    $pdo = Database::conectar();

    // Datos principales del facilitador (profesor) – sin depender de f.colegio
    $stmt = $pdo->prepare("SELECT 
            f.id,
            u.nombres,
            u.apellidos,
            u.tipo_documento,
            u.numero_documento,
            u.correo_electronico,
            u.telefono,
            f.titulo_academico,
            f.especialidad,
            f.fecha_ingreso,
            f.tipo_contrato
        FROM facilitadores f
        INNER JOIN usuarios u ON f.usuario = u.id
        WHERE f.id = ?");
    $stmt->execute([$id]);
    $profesor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profesor) {
        echo json_encode(['success' => false, 'message' => 'No se encontró el profesor']);
        exit;
    }

    // Resolver colegio_nombre desde las fichas asignadas
    $colegioNombre = null;
    try {
        $q = $pdo->prepare("SELECT DISTINCT c.nombre
            FROM facilitador_ficha ff
            INNER JOIN fichas fi ON fi.id = ff.ficha_id
            LEFT JOIN colegios c ON fi.colegio_id = c.id
            WHERE ff.facilitador_id = ?
            LIMIT 1");
        $q->execute([$id]);
        $colegioNombre = $q->fetchColumn();
        if (!$colegioNombre) {
            // Fallback legacy: fichas.colegio
            $q2 = $pdo->prepare("SELECT DISTINCT c.nombre
                FROM facilitador_ficha ff
                INNER JOIN fichas fi ON fi.id = ff.ficha_id
                LEFT JOIN colegios c ON fi.colegio = c.id
                WHERE ff.facilitador_id = ?
                LIMIT 1");
            $q2->execute([$id]);
            $colegioNombre = $q2->fetchColumn();
        }
    } catch (Throwable $e) {
        $colegioNombre = null;
    }
    $profesor['colegio_nombre'] = $colegioNombre;

    // Fichas asignadas (propias + compartidas)
    $profModel = new Facilitador();
    $fichasPropias = $profModel->obtenerFichasPorFacilitador($id);
    foreach ($fichasPropias as &$f) {
        if (!isset($f['tipo'])) {
            $f['tipo'] = 'propia';
        }
    }
    unset($f);

    // Fichas compartidas con este facilitador (best-effort según esquema actual)
    $fichasCompartidas = [];
    try {
        $stmtC = $pdo->prepare("\n            SELECT DISTINCT f.id, f.nombre, f.dias_semana, COALESCE(f.numero, f.id) AS numero\n            FROM fichas f\n            INNER JOIN fichas_compartidas fc ON f.id = fc.ficha\n            WHERE fc.facilitador_compartido = ? AND fc.estado = 'Aceptada'\n            ORDER BY f.nombre\n        ");
        $stmtC->execute([$id]);
        $fichasCompartidas = $stmtC->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\PDOException $eC1) {
        if ($eC1->getCode() !== '42S22') { throw $eC1; }
        try {
            $stmtC = $pdo->prepare("\n                SELECT DISTINCT f.id, f.nombre, f.dias_semana, COALESCE(f.numero, f.id) AS numero\n                FROM fichas f\n                INNER JOIN fichas_compartidas fc ON f.id = fc.ficha_id\n                WHERE fc.facilitador_compartido = ? AND fc.estado = 'Aceptada'\n                ORDER BY f.nombre\n            ");
            $stmtC->execute([$id]);
            $fichasCompartidas = $stmtC->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $eC2) {
            if ($eC2->getCode() !== '42S22') { throw $eC2; }
        }
    }

    foreach ($fichasCompartidas as &$fc) {
        $fc['tipo'] = 'compartida';
    }
    unset($fc);

    // Combinar fichas propias y compartidas sin duplicados
    $mapFichas = [];
    foreach ($fichasPropias as $ficha) {
        $key = (int)($ficha['id'] ?? 0);
        $mapFichas[$key] = $ficha;
    }
    foreach ($fichasCompartidas as $ficha) {
        $key = (int)($ficha['id'] ?? 0);
        $existente = $mapFichas[$key] ?? [];
        $mapFichas[$key] = array_merge($existente, $ficha);
    }
    $fichas = array_values($mapFichas);

    // Materias asignadas (no se usa actualmente en el panel, se deja vacío)
    $materias = [];

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
        'success'           => true,
        'profesor'          => $profesor,
        'fichas'            => $fichas,
        'fichas_compartidas'=> $fichasCompartidas,
        'materias'          => $materias,
        'clases'            => $clases,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos del profesor: ' . $e->getMessage()
    ]);
}
