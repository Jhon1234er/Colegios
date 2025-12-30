<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

class AsistenteController {
    private $pdo;
    // Helper para responder JSON sin escapar caracteres Unicode
    private function respondJSON($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public function __construct() {
        if (function_exists('start_secure_session')) start_secure_session();
        require_login();
        require_role([1,4]);
        $this->pdo = Database::conectar();

        $action = $_GET['action'] ?? '';
        $page   = $_GET['page'] ?? '';
        if ($action === 'resumen_hoy' || $page === 'asistente_resumen') { $this->resumenHoy(); exit; }
        if ($action === 'colegios'    || $page === 'asistente_colegios') { $this->listarColegios(); exit; }
        if ($action === 'ausentes_hoy'|| $page === 'asistente_ausentes') { $this->ausentesHoy(); exit; }
        if ($action === 'reporte_excel'|| $page === 'asistente_reporte_excel') { $this->reporteExcel(); exit; }
        if ($action === 'historial_pendientes') { $this->historialPendientes(); exit; }
        if ($action === 'historial_procesados') { $this->historialProcesados(); exit; }
        if ($action === 'detalle_proceso') { $this->detalleProceso(); exit; }
        // por defecto muestra vista
        include __DIR__ . '/../views/Asistente/dashboard.php';
        exit;
    }

    private function resumenHoy() {
        header('Content-Type: application/json');
        try {
            $hoy = date('Y-m-d');
            // Total clases hoy
            $total = 0; $enCurso = 0; $proxima = null; $faltas = 0;
            try {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM horarios_fichas WHERE DATE(fecha_inicio)=?");
                $stmt->execute([$hoy]);
                $total = (int)$stmt->fetchColumn();
            } catch (\PDOException $e) { $total = 0; }

            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM horarios_fichas WHERE NOW() BETWEEN fecha_inicio AND fecha_fin AND (estado IS NULL OR LOWER(estado)<>'suspendido')");
                $enCurso = (int)$stmt->fetchColumn();
            } catch (\PDOException $e) { $enCurso = 0; }

            try {
                $stmt = $this->pdo->prepare("SELECT fecha_inicio FROM horarios_fichas WHERE fecha_inicio>NOW() ORDER BY fecha_inicio ASC LIMIT 1");
                $stmt->execute();
                $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
                if ($row && !empty($row['fecha_inicio'])) $proxima = $row;
            } catch (\PDOException $e) { $proxima = null; }

            // Contar faltas (ausentes) soportando esquemas con columna numérica o textual
            try {
                // Variante A: columna numérica estado_asistencia_id
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE DATE(fecha)=? AND LOWER(REPLACE(estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia')");
                $stmt->execute([$hoy]);
                $faltas = (int)$stmt->fetchColumn();
            } catch (\PDOException $eA) {
                try {
                    // Variante B: columna textual estado_asistencia
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE DATE(fecha)=? AND LOWER(estado_asistencia) IN ('no_asistio','ausente','falla')");
                    $stmt->execute([$hoy]);
                    $faltas = (int)$stmt->fetchColumn();
                } catch (\PDOException $eB) { $faltas = 0; }
            }

            $this->respondJSON([
                'success'=>true,
                'total_clases'=>$total,
                'en_curso'=>$enCurso,
                'faltas'=>$faltas,
                'proxima_clase'=>$proxima
            ]);
        } catch (\Throwable $e) {
            $this->respondJSON(['success'=>false,'error'=>$e->getMessage()],500);
        }
    }

    private function listarColegios() {
        header('Content-Type: application/json');
        try {
            // Intentar columnas completas; fallback a básicas
            try {
                $stmt = $this->pdo->query("SELECT id, nombre, tipo_institucion, departamento, municipio, codigo_dane FROM colegios ORDER BY nombre");
            } catch (\PDOException $e) {
                $stmt = $this->pdo->query("SELECT id, nombre, departamento, municipio FROM colegios ORDER BY nombre");
            }
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $this->respondJSON(['success'=>true,'data'=>$rows]);
        } catch (\Throwable $e) {
            $this->respondJSON(['success'=>false,'error'=>$e->getMessage()],500);
        }
    }

    private function ausentesHoy() {
        header('Content-Type: application/json');
        try {
            $hoy = date('Y-m-d');
            $withDebug = (isset($_GET['debug']) && (string)$_GET['debug'] === '1');
            $debug = [];
            $rows = [];
            // Consulta robusta: combina ambos esquemas (aprendices.id y usuarios.id) y soporta fecha_dia si existe
            try {
                $sql = "
                    SELECT DISTINCT est_id AS estudiante_id, ficha_id, nombres, apellidos, ficha_nombre FROM (
                        SELECT 
                            a.estudiante_id AS est_id,
                            COALESCE(a.ficha_id, ap.ficha_id) AS ficha_id,
                            u.nombres, u.apellidos,
                            COALESCE(fa.nombre, ff.nombre) AS ficha_nombre
                        FROM asistencias a
                        INNER JOIN aprendices ap ON ap.id = a.estudiante_id
                        INNER JOIN usuarios u ON u.id = ap.usuario_id
                        LEFT JOIN fichas fa ON fa.id = a.ficha_id
                        LEFT JOIN fichas ff ON ff.id = ap.ficha_id
                        WHERE (DATE(a.fecha) = ? OR a.fecha_dia = ?)
                          AND (LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia'))
                        UNION ALL
                        SELECT 
                            a.estudiante_id AS est_id,
                            COALESCE(a.ficha_id, ap2.ficha_id) AS ficha_id,
                            u2.nombres, u2.apellidos,
                            COALESCE(fb.nombre, fc.nombre) AS ficha_nombre
                        FROM asistencias a
                        INNER JOIN usuarios u2 ON u2.id = a.estudiante_id
                        LEFT JOIN aprendices ap2 ON ap2.usuario_id = u2.id
                        LEFT JOIN fichas fb ON fb.id = a.ficha_id
                        LEFT JOIN fichas fc ON fc.id = ap2.ficha_id
                        WHERE (DATE(a.fecha) = ? OR a.fecha_dia = ?)
                          AND (LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia'))
                    ) t
                    ORDER BY apellidos, nombres
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$hoy, $hoy, $hoy, $hoy]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e1) {
                // Fallback: sin usar fecha_dia para esquemas legacy
                try {
                    $sql = "
                        SELECT DISTINCT est_id AS estudiante_id, ficha_id, nombres, apellidos, ficha_nombre FROM (
                            SELECT 
                                a.estudiante_id AS est_id,
                                COALESCE(a.ficha_id, ap.ficha_id) AS ficha_id,
                                u.nombres, u.apellidos,
                                COALESCE(fa.nombre, ff.nombre) AS ficha_nombre
                            FROM asistencias a
                            INNER JOIN aprendices ap ON ap.id = a.estudiante_id
                            INNER JOIN usuarios u ON u.id = ap.usuario_id
                            LEFT JOIN fichas fa ON fa.id = a.ficha_id
                            LEFT JOIN fichas ff ON ff.id = ap.ficha_id
                            WHERE DATE(a.fecha) = ?
                              AND (LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia'))
                            UNION ALL
                            SELECT 
                                a.estudiante_id AS est_id,
                                COALESCE(a.ficha_id, ap2.ficha_id) AS ficha_id,
                                u2.nombres, u2.apellidos,
                                COALESCE(fb.nombre, fc.nombre) AS ficha_nombre
                            FROM asistencias a
                            INNER JOIN usuarios u2 ON u2.id = a.estudiante_id
                            LEFT JOIN aprendices ap2 ON ap2.usuario_id = u2.id
                            LEFT JOIN fichas fb ON fb.id = a.ficha_id
                            LEFT JOIN fichas fc ON fc.id = ap2.ficha_id
                            WHERE DATE(a.fecha) = ?
                              AND (LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia'))
                        ) t
                        ORDER BY apellidos, nombres
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$hoy, $hoy]);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $e2) { $rows = []; }
            }

            // Variante D: derivar ausentes por clases finalizadas hoy SIN registro de asistencia (o con ausencia)
            if (empty($rows)) {
                try {
                    $sql = "
                        SELECT DISTINCT u.id AS estudiante_id, f.id AS ficha_id, u.nombres, u.apellidos, f.nombre AS ficha_nombre
                        FROM horarios_fichas hf
                        INNER JOIN fichas f ON f.id = hf.ficha_id
                        INNER JOIN aprendices ap ON ap.ficha_id = f.id
                        INNER JOIN usuarios u ON u.id = ap.usuario_id
                        LEFT JOIN asistencias a
                          ON a.ficha_id = f.id
                         AND a.estudiante_id = u.id
                         AND DATE(a.fecha) = ?
                        WHERE DATE(hf.fecha_inicio) = ?
                          AND hf.fecha_fin < NOW()
                          AND (
                               a.id IS NULL
                               OR LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia')
                          )
                        ORDER BY u.apellidos, u.nombres
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$hoy, $hoy]);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $e4) {
                    try {
                        // Legacy: aprendices.usuario en vez de usuario_id
                        $sql = "
                            SELECT DISTINCT u.id AS estudiante_id, f.id AS ficha_id, u.nombres, u.apellidos, f.nombre AS ficha_nombre
                            FROM horarios_fichas hf
                            INNER JOIN fichas f ON f.id = hf.ficha_id
                            INNER JOIN aprendices ap ON ap.ficha = f.id
                            INNER JOIN usuarios u ON u.id = ap.usuario
                            LEFT JOIN asistencias a
                              ON a.ficha_id = f.id
                             AND a.estudiante_id = u.id
                             AND DATE(a.fecha) = ?
                            WHERE DATE(hf.fecha_inicio) = ?
                              AND hf.fecha_fin < NOW()
                              AND (
                                   a.id IS NULL
                                   OR a.estado_asistencia_id = 2
                                   OR LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia')
                              )
                            ORDER BY u.apellidos, u.nombres
                        ";
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute([$hoy, $hoy]);
                        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    } catch (\PDOException $e5) { /* mantener vacío */ }
                }
            }
            // Variante E: esquema sin tabla 'aprendices' (solo usuarios + asistencias)
            if (empty($rows)) {
                try {
                    $sql = "
                        SELECT DISTINCT a.estudiante_id AS estudiante_id,
                               COALESCE(a.ficha_id, 0) AS ficha_id,
                               u.nombres, u.apellidos,
                               f.nombre AS ficha_nombre
                        FROM asistencias a
                        JOIN usuarios u ON u.id = a.estudiante_id
                        LEFT JOIN fichas f ON f.id = a.ficha_id
                        WHERE (DATE(a.fecha) = ? OR a.fecha_dia = ?)
                          AND (LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia'))
                        ORDER BY u.apellidos, u.nombres
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$hoy, $hoy]);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $eE1) {
                    if ($eE1->getCode() !== '42S22') { /* otra falla SQL */ }
                    // Sin columna fecha_dia
                    try {
                        $sql = "
                            SELECT DISTINCT a.estudiante_id AS estudiante_id,
                                   COALESCE(a.ficha_id, 0) AS ficha_id,
                                   u.nombres, u.apellidos,
                                   f.nombre AS ficha_nombre
                            FROM asistencias a
                            JOIN usuarios u ON u.id = a.estudiante_id
                            LEFT JOIN fichas f ON f.id = a.ficha_id
                            WHERE DATE(a.fecha) = ?
                              AND (LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia'))
                            ORDER BY u.apellidos, u.nombres
                        ";
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute([$hoy]);
                        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    } catch (\PDOException $eE2) { /* mantener vacío */ }
                }
            }
            if ($withDebug) {
                // Histograma de estados para hoy (ayuda a ver variantes textuales o numéricas)
                try {
                    $st = $this->pdo->prepare("SELECT estado_asistencia_id AS id, LOWER(REPLACE(COALESCE(estado_asistencia,''),'ó','o')) AS txt, COUNT(*) AS c FROM asistencias WHERE DATE(fecha)=? GROUP BY id, txt ORDER BY c DESC");
                    $st->execute([$hoy]);
                    $debug['estado_hist'] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $eH) { $debug['estado_hist'] = []; }
                $this->respondJSON(['success'=>true,'data'=>$rows,'debug'=>$debug]);
                return;
            }
            $this->respondJSON(['success'=>true,'data'=>$rows]);
        } catch (\Throwable $e){ $this->respondJSON(['success'=>false,'error'=>$e->getMessage()],500); }
    }

    // ===== HISTORIAL: AUSENCIAS PENDIENTES DE PROCESO =====
    private function historialPendientes() {
        header('Content-Type: application/json');
        try {
            $colegio_id = isset($_GET['colegio_id']) ? (int)$_GET['colegio_id'] : 0;
            // Detectar columna de fecha "oficial" de la asistencia: priorizar fecha_dia si existe
            $asisFechaCol = 'fecha';
            try {
                $asisCols = $this->pdo->query("SHOW COLUMNS FROM asistencias")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                if (in_array('fecha_dia', $asisCols, true)) {
                    $asisFechaCol = 'fecha_dia';
                }
            } catch (\Throwable $_a) { /* noop */ }
            // Intentar con distintos nombres de tabla de seguimiento/proceso
            $bases = [
                // formato: [tabla, fk_nombre]
                ['seguimiento_ausencia', 'asistencia_id'],
                ['seguimiento_ausencias', 'asistencia_id'],
                ['procesos_ausencia', 'asistencia_id'],
                ['procesos_ausencias', 'asistencia_id'],
                ['procesos', 'asistencia_id'],
                ['seguimientos', 'asistencia_id'],
                ['seguimiento', 'asistencia_id']
            ];

            $rows = [];
            foreach ($bases as [$tabla, $fk]) {
                try {
                    // Variante 1: tabla de proceso con columna de estado (pendiente/en_proceso)
                    $sql = "
                        SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id,
                               DATE(a.$asisFechaCol) AS fecha,
                               u.nombres, u.apellidos, f.nombre AS ficha_nombre
                        FROM asistencias a
                        JOIN usuarios u ON u.id = a.estudiante_id
                        LEFT JOIN aprendices e ON e.usuario_id = u.id
                        JOIN fichas f ON a.ficha_id = f.id
                        JOIN `$tabla` sa ON sa.`$fk` = a.id
                        WHERE (LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia'))
                          AND (sa.estado IN ('pendiente','en_proceso','abierto'))
                          " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                        ORDER BY a.$asisFechaCol DESC
                        LIMIT 200
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($rows)) break; // éxito con estado
                    // Fallback legacy: aprendices.usuario
                    try {
                        $sql = "
                            SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                                   u.nombres, u.apellidos, f.nombre AS ficha_nombre
                            FROM asistencias a
                            JOIN usuarios u ON u.id = a.estudiante_id
                            LEFT JOIN aprendices e ON e.usuario = u.id
                            JOIN fichas f ON a.ficha_id = f.id
                            JOIN `$tabla` sa ON sa.`$fk` = a.id
                            WHERE (LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia'))
                              AND (sa.estado IN ('pendiente','en_proceso','abierto'))
                              " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                            ORDER BY a.fecha DESC
                            LIMIT 200
                        ";
                        $stmt = $this->pdo->prepare($sql);
                        if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        if (!empty($rows)) break;
                    } catch (Exception $_legacy) { /* noop */ }
                } catch (Exception $inner) { /* intentar variante 2 abajo */ }
                try {
                    // Variante 2: pendiente si NO existe registro en la tabla de proceso
                    $sql = "
                        SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                               u.nombres, u.apellidos, f.nombre AS ficha_nombre
                        FROM asistencias a
                        JOIN usuarios u ON u.id = a.estudiante_id
                        LEFT JOIN aprendices e ON e.usuario_id = u.id
                        JOIN fichas f ON a.ficha_id = f.id
                        LEFT JOIN `$tabla` sa ON sa.`$fk` = a.id
                        WHERE (LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia'))
                          AND (sa.`$fk` IS NULL OR sa.estado IN ('pendiente','en_proceso','abierto'))
                          " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                        ORDER BY a.fecha DESC
                        LIMIT 200
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($rows)) { break; }
                    // Fallback legacy: aprendices.usuario
                    try {
                        $sql = "
                            SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                                   u.nombres, u.apellidos, f.nombre AS ficha_nombre
                            FROM asistencias a
                            JOIN usuarios u ON u.id = a.estudiante_id
                            LEFT JOIN aprendices e ON e.usuario = u.id
                            JOIN fichas f ON a.ficha_id = f.id
                            LEFT JOIN `$tabla` sa ON sa.`$fk` = a.id
                            WHERE (LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia'))
                              AND (sa.`$fk` IS NULL OR sa.estado IN ('pendiente','en_proceso','abierto'))
                              " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                            ORDER BY a.fecha DESC
                            LIMIT 200
                        ";
                        $stmt = $this->pdo->prepare($sql);
                        if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        if (!empty($rows)) { break; }
                    } catch (Exception $_legacy2) { /* noop */ }
                } catch (Exception $inner2) {
                    // Variante 3: relación por (estudiante_id, fecha) cuando no existe FK a asistencia
                    try {
                        $sql = "
                            SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                                   u.nombres, u.apellidos, f.nombre AS ficha_nombre
                            FROM asistencias a
                            JOIN usuarios u ON u.id = a.estudiante_id
                            LEFT JOIN aprendices e ON e.usuario_id = u.id
                            JOIN fichas f ON a.ficha_id = f.id
                            LEFT JOIN `$tabla` sa ON sa.estudiante_id = e.id AND DATE(sa.fecha) = DATE(a.fecha)
                            WHERE (LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia'))
                              AND (sa.estudiante_id IS NULL OR sa.estado IN ('pendiente','en_proceso','abierto'))
                              " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                            ORDER BY a.fecha DESC
                            LIMIT 200
                        ";
                        $stmt = $this->pdo->prepare($sql);
                        if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        if (!empty($rows)) { break; }
                        // Fallback legacy para relación por estudiante/fecha
                        try {
                            $sql = "
                                SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                                       u.nombres, u.apellidos, f.nombre AS ficha_nombre
                                FROM asistencias a
                                JOIN usuarios u ON u.id = a.estudiante_id
                                LEFT JOIN aprendices e ON e.usuario = u.id
                                JOIN fichas f ON a.ficha_id = f.id
                                LEFT JOIN `$tabla` sa ON sa.estudiante_id = e.id AND DATE(sa.fecha) = DATE(a.fecha)
                                WHERE (LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia'))
                                  AND (sa.estudiante_id IS NULL OR sa.estado IN ('pendiente','en_proceso','abierto'))
                                  " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                                ORDER BY a.fecha DESC
                                LIMIT 200
                            ";
                            $stmt = $this->pdo->prepare($sql);
                            if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                            if (!empty($rows)) { break; }
                        } catch (Exception $_legacy3) { /* noop */ }
                    } catch (Exception $inner3) { /* swallow and follow with fallback */ }
                    continue;
                }
            }
            // Fallback: si no hay tablas de proceso o no devolvió nada, mostrar ausentes de hoy
            if ($rows === [] && $colegio_id >= 0) {
                $hoy = date('Y-m-d');
                $ayer = date('Y-m-d', strtotime('-1 day'));
                // Fallback A: explícitos ausentes (asistencias marcadas)
                try {
                    $sql = "
                        SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                               u.nombres, u.apellidos, f.nombre AS ficha_nombre
                        FROM asistencias a
                        JOIN usuarios u ON u.id = a.estudiante_id
                        LEFT JOIN aprendices e ON e.usuario_id = u.id
                        LEFT JOIN fichas f ON a.ficha_id = f.id
                        WHERE DATE(a.fecha) IN (?, ?)
                          AND (
                                a.estado_asistencia_id = 2
                             OR LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia')
                          )
                              " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                        ORDER BY a.fecha DESC
                        LIMIT 200
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $params = [$hoy, $ayer]; if ($colegio_id>0) $params[] = $colegio_id; 
                    $stmt->execute($params);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $_) { $rows = []; }

                // Fallback B: derivar pendientes por clase finalizada sin registro (o marcado ausente)
                if ($rows === []) {
                    try {
                        $sql = "
                            SELECT DISTINCT NULL AS asistencia_id, u.id AS estudiante_id, f.id AS ficha_id, DATE(hf.fecha_inicio) AS fecha,
                                   u.nombres, u.apellidos, f.nombre AS ficha_nombre
                            FROM horarios_fichas hf
                            INNER JOIN fichas f ON f.id = hf.ficha_id
                            INNER JOIN aprendices e ON e.ficha_id = f.id
                            INNER JOIN usuarios u ON u.id = e.usuario_id
                            LEFT JOIN asistencias a
                              ON a.ficha_id = f.id
                             AND a.estudiante_id = u.id
                             AND DATE(a.fecha) IN (?, ?)
                            WHERE DATE(hf.fecha_inicio) IN (?, ?)
                              AND hf.fecha_fin < NOW()
                              AND (
                                   a.id IS NULL
                                   OR a.estado_asistencia_id = 2
                                   OR LOWER(a.estado_asistencia) IN ('no_asistio','ausente','falla')
                              )
                              " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                            ORDER BY fecha DESC
                            LIMIT 200
                        ";
                        $stmt = $this->pdo->prepare($sql);
                        $params = [$hoy, $ayer, $hoy, $ayer]; if ($colegio_id>0) $params[] = $colegio_id; 
                        $stmt->execute($params);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (\Throwable $_2) { /* dejar vacío */ }
                }

                // Fallback C: si aún no hay filas, considerar relación con seguimiento_ausencias (cuando no poseen asistencia_id)
                if ($rows === []) {
                    try {
                        // Intentar unión por asistencia_id
                        // Condición de estado si existe la columna
                        $saCols = [];
                        try { $saCols = $this->pdo->query("SHOW COLUMNS FROM seguimiento_ausencias")->fetchAll(\PDO::FETCH_COLUMN) ?: []; } catch (\Throwable $__) { $saCols = []; }
                        $hasEstado = in_array('estado', $saCols, true);
                        $condPend  = $hasEstado ? " OR sa.estado IN ('pendiente','en_proceso','abierto')" : '';

                        $sql = "
                            SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                                   u.nombres, u.apellidos, f.nombre AS ficha_nombre
                            FROM asistencias a
                            JOIN usuarios u ON u.id = a.estudiante_id
                            LEFT JOIN fichas f ON a.ficha_id = f.id
                            LEFT JOIN seguimiento_ausencias sa ON sa.asistencia_id = a.id
                            WHERE DATE(a.fecha) IN (?, ?)
                              AND (
                                    a.estado_asistencia_id = 2
                                 OR LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia')
                              )
                              AND (sa.asistencia_id IS NULL" . $condPend . ")
                            ORDER BY a.fecha DESC
                            LIMIT 200
                        ";
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute([$hoy, $ayer]);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (\PDOException $eC) {
                        if ($eC->getCode() === '42S22') {
                            try {
                                // Sin columna asistencia_id: enlazar por (estudiante_id, fecha)
                                $cols = $this->pdo->query("SHOW COLUMNS FROM seguimiento_ausencias")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                                $cEst   = in_array('estudiante_id', $cols, true) ? 'estudiante_id'
                                 : (in_array('aprendiz_id', $cols, true) ? 'aprendiz_id'
                                 : (in_array('estudiante', $cols, true) ? 'estudiante' : null));
                                $cFecha = in_array('fecha', $cols, true) ? 'fecha' : (in_array('fecha_contacto', $cols, true) ? 'fecha_contacto' : null);
                                $hasEstado2 = in_array('estado', $cols, true);
                                if ($cEst && $cFecha) {
                                    $sql = "
                                        SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                                               u.nombres, u.apellidos, f.nombre AS ficha_nombre
                                        FROM asistencias a
                                        JOIN usuarios u ON u.id = a.estudiante_id
                                        LEFT JOIN fichas f ON a.ficha_id = f.id
                                        LEFT JOIN seguimiento_ausencias sa ON sa.$cEst = a.estudiante_id AND DATE(sa.$cFecha) = DATE(a.fecha)
                                        WHERE DATE(a.fecha) IN (?, ?)
                                          AND (
                                                a.estado_asistencia_id = 2
                                             OR LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('no_asistio','no asistio','ausente','falla','falta','inasistencia')
                                          )
                                          AND (sa.$cEst IS NULL" . ($hasEstado2 ? " OR sa.estado IN ('pendiente','en_proceso','abierto')" : '') . ")
                                        ORDER BY a.fecha DESC
                                        LIMIT 200
                                    ";
                                    $stmt = $this->pdo->prepare($sql);
                                    $stmt->execute([$hoy, $ayer]);
                                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                                }
                            } catch (\Throwable $_c2) { /* noop */ }
                        }
                    }
                }
            }

            // Fallback final: si seguimos sin filas, leer directamente seguimiento_ausencias HOY/AYER con estado pendiente/abierto/NULL
            if (empty($rows)) {
                try {
                    $hoy = date('Y-m-d');
                    $ayer = date('Y-m-d', strtotime('-1 day'));
                    $saCols = $this->pdo->query("SHOW COLUMNS FROM seguimiento_ausencias")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                    $cEst  = in_array('estudiante_id', $saCols, true) ? 'estudiante_id' : (in_array('aprendiz_id', $saCols, true) ? 'aprendiz_id' : (in_array('estudiante', $saCols, true) ? 'estudiante' : null));
                    $cFicha= in_array('ficha_id', $saCols, true) ? 'ficha_id' : (in_array('ficha', $saCols, true) ? 'ficha' : null);
                    $cFecha= in_array('fecha', $saCols, true) ? 'fecha' : (in_array('fecha_contacto', $saCols, true) ? 'fecha_contacto' : null);
                    $hasEstado = in_array('estado', $saCols, true);
                    if ($cEst && $cFicha && $cFecha) {
                        if ($cEst === 'aprendiz_id') {
                            $sql = "
                                SELECT NULL AS asistencia_id, u.id AS estudiante_id, sa.$cFicha AS ficha_id, DATE(sa.$cFecha) AS fecha,
                                       u.nombres, u.apellidos, f.nombre AS ficha_nombre
                                FROM seguimiento_ausencias sa
                                JOIN aprendices ap ON ap.id = sa.aprendiz_id
                                JOIN usuarios u ON u.id = ap.usuario_id
                                LEFT JOIN fichas f ON f.id = sa.$cFicha
                                WHERE DATE(sa.$cFecha) IN (?, ?)
                                  " . ($hasEstado ? "AND sa.estado IN ('pendiente','en_proceso','abierto')" : '') . "
                                  " . ($colegio_id>0 ? " AND ap.colegio_id = ?" : "") . "
                                ORDER BY sa.$cFecha DESC
                                LIMIT 200
                            ";
                            $stmt = $this->pdo->prepare($sql);
                            $params = [$hoy, $ayer]; if ($colegio_id>0) $params[] = $colegio_id; 
                            $stmt->execute($params);
                            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                        } else {
                            $sql = "
                                SELECT NULL AS asistencia_id, u.id AS estudiante_id, sa.$cFicha AS ficha_id, DATE(sa.$cFecha) AS fecha,
                                       u.nombres, u.apellidos, f.nombre AS ficha_nombre
                                FROM seguimiento_ausencias sa
                                JOIN usuarios u ON u.id = sa.$cEst
                                LEFT JOIN aprendices ap ON ap.usuario_id = u.id
                                LEFT JOIN fichas f ON f.id = sa.$cFicha
                                WHERE DATE(sa.$cFecha) IN (?, ?)
                                  " . ($hasEstado ? "AND sa.estado IN ('pendiente','en_proceso','abierto')" : '') . "
                                  " . ($colegio_id>0 ? " AND ap.colegio_id = ?" : "") . "
                                ORDER BY sa.$cFecha DESC
                                LIMIT 200
                            ";
                            $stmt = $this->pdo->prepare($sql);
                            $params = [$hoy, $ayer]; if ($colegio_id>0) $params[] = $colegio_id; 
                            $stmt->execute($params);
                            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                        }
                    }
                } catch (\Throwable $_pendFinal) { /* noop */ }
            }

            $this->respondJSON(['success'=>true,'data'=>$rows]);
        } catch (Exception $e) {
            // Si no existen tablas de seguimiento u otro detalle del esquema, no romper la vista
            $this->respondJSON(['success'=>true,'data'=>[]], 200);
        }
    }

    // ===== HISTORIAL: AUSENCIAS CON PROCESO REALIZADO =====
    private function historialProcesados() {
        header('Content-Type: application/json');
        try {
            $colegio_id = isset($_GET['colegio_id']) ? (int)$_GET['colegio_id'] : 0;
            // Detectar columna de fecha "oficial" de la asistencia: priorizar fecha_dia si existe
            $asisFechaCol = 'fecha';
            try {
                $asisCols = $this->pdo->query("SHOW COLUMNS FROM asistencias")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                if (in_array('fecha_dia', $asisCols, true)) {
                    $asisFechaCol = 'fecha_dia';
                }
            } catch (\Throwable $_a) { /* noop */ }

            $bases = [
                ['seguimiento_ausencia', 'asistencia_id', 'id AS proceso_id'],
                ['seguimiento_ausencias', 'asistencia_id', 'id AS proceso_id'],
                ['procesos_ausencia', 'asistencia_id', 'id AS proceso_id'],
                ['procesos_ausencias', 'asistencia_id', 'id AS proceso_id'],
                ['procesos', 'asistencia_id', 'id AS proceso_id'],
                ['seguimientos', 'asistencia_id', 'id AS proceso_id'],
                ['seguimiento', 'asistencia_id', 'id AS proceso_id']
            ];

            $rows = [];
            foreach ($bases as [$tabla, $fk, $selProc]) {
                try {
                    // Detectar columna de fecha en la tabla de seguimiento para poder mostrar fecha del proceso
                    // Prioridad: creado_en > fecha > fecha_contacto
                    $fechaProcExpr = 'NULL AS fecha_proceso';
                    try {
                        $segColsProc = $this->pdo->query("SHOW COLUMNS FROM `$tabla`")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                        $cFechaProc  = in_array('creado_en', $segColsProc, true) ? 'creado_en'
                                     : (in_array('fecha', $segColsProc, true) ? 'fecha'
                                     : (in_array('fecha_contacto', $segColsProc, true) ? 'fecha_contacto' : null));
                        if ($cFechaProc) {
                            $fechaProcExpr = "DATE(sa.$cFechaProc) AS fecha_proceso";
                        }
                    } catch (\Throwable $_) { /* noop */ }
                    // Variante 1: estado completado/finalizado
                    $sql = "
                        SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id,
                               DATE(a.$asisFechaCol) AS fecha,
                               DATE(a.$asisFechaCol) AS fecha_inasistencia,
                               u.nombres, u.apellidos,
                               COALESCE(f.numero, f.id) AS ficha_numero,
                               f.nombre AS ficha_nombre,
                               $fechaProcExpr,
                               sa.$selProc
                        FROM asistencias a
                        JOIN usuarios u ON u.id = a.estudiante_id
                        LEFT JOIN aprendices e ON e.usuario_id = u.id
                        JOIN fichas f ON a.ficha_id = f.id
                        JOIN `$tabla` sa ON sa.`$fk` = a.id
                        WHERE (
                               sa.estado IN ('completado','finalizado','cerrado','realizado','justificado')
                            OR a.estado_asistencia_id = 3
                            OR LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('justificado','justificada')
                        )
                          " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                        ORDER BY a.$asisFechaCol DESC
                        LIMIT 200
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($rows)) break;
                    
                    // Fallback legacy: aprendices.usuario
                    $sql = "
                        SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                               u.nombres, u.apellidos, f.nombre AS ficha_nombre,
                               sa.$selProc
                        FROM asistencias a
                        JOIN usuarios u ON u.id = a.estudiante_id
                        LEFT JOIN aprendices e ON e.usuario = u.id
                        JOIN fichas f ON a.ficha_id = f.id
                        JOIN `$tabla` sa ON sa.`$fk` = a.id
                        WHERE (
                               sa.estado IN ('completado','finalizado','cerrado','realizado','justificado')
                            OR a.estado_asistencia_id = 3
                            OR LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('justificado','justificada')
                        )
                          " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                        ORDER BY a.fecha DESC
                        LIMIT 200
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($rows)) break;

                    // Fallback: si no existe fk asistencia_id en la tabla de seguimiento
                    try {
                        // Detectar columna de estudiante en la tabla de seguimiento
                        $segCols = $this->pdo->query("SHOW COLUMNS FROM `$tabla`")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                        $segEst = in_array('estudiante_id', $segCols, true) ? 'estudiante_id'
                                 : (in_array('aprendiz_id', $segCols, true) ? 'aprendiz_id'
                                 : (in_array('estudiante', $segCols, true) ? 'estudiante' : null));
                        if ($segEst) {
                            if ($segEst === 'aprendiz_id') {
                                $sql = "
                                    SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id,
                                           DATE(a.$asisFechaCol) AS fecha,
                                           DATE(a.$asisFechaCol) AS fecha_inasistencia,
                                           u.nombres, u.apellidos,
                                           COALESCE(f.numero, f.id) AS ficha_numero,
                                           f.nombre AS ficha_nombre,
                                           $fechaProcExpr,
                                           sa.$selProc
                                    FROM asistencias a
                                    JOIN usuarios u ON u.id = a.estudiante_id
                                    LEFT JOIN aprendices e ON e.usuario_id = u.id
                                    JOIN fichas f ON a.ficha_id = f.id
                                    JOIN `$tabla` sa ON sa.aprendiz_id = e.id AND DATE(sa.fecha) = DATE(a.$asisFechaCol)
                                    WHERE (
                                           sa.estado IN ('completado','finalizado','cerrado','realizado','justificado')
                                        OR a.estado_asistencia_id = 3
                                        OR LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('justificado','justificada')
                                    )
                                      " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                                    ORDER BY a.$asisFechaCol DESC
                                    LIMIT 200
                                ";
                                $stmt = $this->pdo->prepare($sql);
                                if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                                if (!empty($rows)) break;
                            } else {
                                $sql = "
                                    SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                                           u.nombres, u.apellidos, f.nombre AS ficha_nombre,
                                           sa.$selProc
                                    FROM asistencias a
                                    JOIN usuarios u ON u.id = a.estudiante_id
                                    LEFT JOIN aprendices e ON e.usuario_id = u.id
                                    JOIN fichas f ON a.ficha_id = f.id
                                    JOIN `$tabla` sa ON sa.`$segEst` = u.id AND DATE(sa.fecha) = DATE(a.fecha)
                                    WHERE (
                                           sa.estado IN ('completado','finalizado','cerrado','realizado','justificado')
                                        OR a.estado_asistencia_id = 3
                                        OR LOWER(REPLACE(a.estado_asistencia,'ó','o')) IN ('justificado','justificada')
                                    )
                                      " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                                    ORDER BY a.fecha DESC
                                    LIMIT 200
                                ";
                                $stmt = $this->pdo->prepare($sql);
                                if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                                if (!empty($rows)) break;
                            }
                        }
                    } catch (\PDOException $_fk) { /* noop */ }
                } catch (Exception $e) {
                    // Si hay un error, continuar con la siguiente tabla
                    continue;
                }
            }

            // Fallback final: leer directamente de seguimiento_ausencias / seguimientos_ausencias
            if (empty($rows)) {
                try {
                    $cols = $this->pdo->query("SHOW COLUMNS FROM seguimiento_ausencias")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                    $cEst   = in_array('estudiante_id', $cols, true) ? 'estudiante_id' : (in_array('aprendiz_id', $cols, true) ? 'aprendiz_id' : (in_array('estudiante', $cols, true) ? 'estudiante' : null));
                    $cFicha = in_array('ficha_id', $cols, true) ? 'ficha_id' : (in_array('ficha', $cols, true) ? 'ficha' : null);
                    // Para la fecha del proceso, priorizar creado_en si existe
                    $cFecha = in_array('creado_en', $cols, true) ? 'creado_en'
                            : (in_array('fecha', $cols, true) ? 'fecha' : (in_array('fecha_contacto', $cols, true) ? 'fecha_contacto' : null));
                    $hasEstado = in_array('estado', $cols, true);
                    if ($cEst && $cFicha && $cFecha) {
                        $sql = "
                            SELECT NULL AS asistencia_id,
                                   sa.$cEst AS estudiante_id,
                                   sa.$cFicha AS ficha_id,
                                   DATE(sa.$cFecha) AS fecha,
                                   NULL AS fecha_inasistencia,
                                   DATE(sa.$cFecha) AS fecha_proceso,
                                   u.nombres, u.apellidos,
                                   COALESCE(f.numero, f.id) AS ficha_numero,
                                   f.nombre AS ficha_nombre,
                                   sa.id AS proceso_id
                            FROM seguimiento_ausencias sa
                            JOIN usuarios u ON u.id = sa.$cEst
                            LEFT JOIN fichas f ON f.id = sa.$cFicha
                            " . ($hasEstado ? "WHERE sa.estado IN ('completado','finalizado','cerrado','realizado','justificado')" : "") . "
                            ORDER BY sa.$cFecha DESC
                            LIMIT 200
                        ";
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute();
                        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    }
                } catch (\Throwable $_f) { /* noop */ }
            }

            $this->respondJSON(['success'=>true,'data'=>$rows]);
        } catch (Exception $e) {
            $this->respondJSON(['success'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // ===== DETALLE DE UN PROCESO DE SEGUIMIENTO =====
    private function detalleProceso() {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $proceso_id = isset($_GET['proceso_id']) ? (int)$_GET['proceso_id'] : 0;
            if ($proceso_id <= 0) {
                $this->respondJSON(['success'=>false,'error'=>'ID de proceso no válido'], 400);
                return;
            }

            // Detectar columnas reales de seguimiento_ausencias
            $cols = $this->pdo->query("SHOW COLUMNS FROM seguimiento_ausencias")->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            $cId    = in_array('id', $cols, true) ? 'id' : null;
            $cEst   = in_array('estudiante_id', $cols, true) ? 'estudiante_id'
                    : (in_array('aprendiz_id', $cols, true) ? 'aprendiz_id'
                    : (in_array('estudiante', $cols, true) ? 'estudiante' : null));
            $cFicha = in_array('ficha_id', $cols, true) ? 'ficha_id' : (in_array('ficha', $cols, true) ? 'ficha' : null);
            $cFecha = in_array('creado_en', $cols, true) ? 'creado_en'
                    : (in_array('fecha', $cols, true) ? 'fecha'
                    : (in_array('fecha_contacto', $cols, true) ? 'fecha_contacto' : null));

            if (!$cId || !$cEst || !$cFicha) {
                $this->respondJSON(['success'=>false,'error'=>'Tabla de seguimiento no está bien configurada'], 500);
                return;
            }

            $fechaExpr = $cFecha ? "DATE(sa.$cFecha) AS fecha_proceso" : "NULL AS fecha_proceso";

            $sql = "
                SELECT 
                    sa.$cId   AS id,
                    sa.$cEst  AS estudiante_id,
                    sa.$cFicha AS ficha_id,
                    $fechaExpr,
                    sa.via,
                    sa.contacto,
                    sa.telefono,
                    sa.motivo,
                    sa.observaciones,
                    u.nombres,
                    u.apellidos,
                    COALESCE(f.numero, f.id) AS ficha_numero,
                    f.nombre AS ficha_nombre
                FROM seguimiento_ausencias sa
                LEFT JOIN usuarios u ON u.id = sa.$cEst
                LEFT JOIN fichas f ON f.id = sa.$cFicha
                WHERE sa.$cId = ?
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$proceso_id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

            if (!$row) {
                $this->respondJSON(['success'=>false,'error'=>'Proceso no encontrado'], 404);
                return;
            }

            $this->respondJSON(['success'=>true,'data'=>$row]);
        } catch (\Throwable $e) {
            $this->respondJSON(['success'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    private function reporteExcel() {
        try {
            $colegio_id = isset($_GET['colegio_id']) ? (int)$_GET['colegio_id'] : 0;
            $desde = $_GET['desde'] ?? date('Y-m-01');
            $hasta = $_GET['hasta'] ?? date('Y-m-t');

            // Construcción de filas compatible con ambos esquemas
            $rows = [];
            $base = "SELECT DATE(a.fecha) AS fecha, COALESCE(f.numero,f.id) AS ficha_numero, f.nombre AS ficha_nombre, a.estado_asistencia AS estado
                     FROM asistencias a
                     JOIN %s e ON a.estudiante_id = e.id
                     JOIN usuarios u ON e.usuario_id = u.id
                     JOIN fichas f ON a.ficha_id = f.id
                     WHERE a.fecha BETWEEN ? AND ? %s
                     ORDER BY a.fecha ASC";
            $filter = ($colegio_id > 0) ? ' AND (e.colegio_id = ? OR e.colegio = ?)' : '';
            $params = ($colegio_id > 0) ? [$desde, $hasta, $colegio_id, $colegio_id] : [$desde, $hasta];
            try {
                $stmt = $this->pdo->prepare(sprintf($base, 'aprendices', $filter));
                $stmt->execute($params);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e1) {
                try {
                    $stmt = $this->pdo->prepare(sprintf($base, 'estudiantes', $filter));
                    $stmt->execute($params);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\PDOException $e2) {
                    // último recurso sin filtro de colegio
                    $stmt = $this->pdo->prepare(sprintf($base, 'aprendices', ''));
                    $stmt->execute([$desde, $hasta]);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                }
            }

            // Mapear estado numérico a texto
            foreach ($rows as &$r) {
                $map = [1=>'presente',2=>'ausente',3=>'tarde',4=>'justificado'];
                $r['estado'] = $map[(int)($r['estado'] ?? 0)] ?? (string)($r['estado'] ?? '');
            }
            unset($r);

            // Crear Excel con estilo colaborativo
            $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $ss->getActiveSheet();
            $sheet->setTitle('Asistencias');
            $sheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(12);
            $sheet->getDefaultStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFFF');
            $sheet->mergeCells('D5:N5'); $sheet->mergeCells('D6:N6'); $sheet->mergeCells('D7:N7');
            $sheet->setCellValue('D5', 'SENA');
            $sheet->setCellValue('D6', 'SERVICIO NACIONAL DE APRENDIZAJE - SENA');
            $sheet->setCellValue('D7', 'CONTROL DE ASISTENCIA - SISTEM SCHOLL');
            $sheet->getRowDimension(5)->setRowHeight(40);
            $sheet->getRowDimension(6)->setRowHeight(30);
            $sheet->getRowDimension(7)->setRowHeight(30);
            $sheet->getStyle('D5')->getFont()->setBold(true)->setSize(36);
            $sheet->getStyle('D6:D7')->getFont()->setSize(12);
            $sheet->getStyle('D5:N7')->getAlignment()->setHorizontal('center')->setVertical('center');
            $sheet->mergeCells('I9:J9'); $sheet->mergeCells('K9:M9');
            $sheet->setCellValue('I9', 'FECHA DE REPORTE:');
            $sheet->setCellValue('K9', (new \DateTime($desde))->format('d/m/Y').' - '.(new \DateTime($hasta))->format('d/m/Y'));
            $sheet->getStyle('I9:M9')->getFont()->setSize(12);
            $sheet->getStyle('I9:M9')->getAlignment()->setHorizontal('left');
            $headers = ['N°','Colegio','Titulo','Instructor','Ficha','Aula','Horario','Estado'];
            $startRow = 11; $startColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString('D');
            foreach ($headers as $i=>$h) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIndex + $i);
                $sheet->setCellValue($col.$startRow, strtoupper($h));
            }
            $headerRange = 'D'.$startRow.':'.'K'.$startRow;
            $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($headerRange)->getAlignment()->setHorizontal('center')->setVertical('center');
            $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B3A53');
            $row = $startRow + 1; $n = 1;
            $thinBorder = \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN;
            foreach ($rows as $data) {
                $vals = [
                    $n++, // N°
                    '',   // Colegio (no disponible en esta consulta)
                    'CLASE', // Titulo
                    '',   // Instructor
                    (string)($data['ficha_numero'] ?? ''),
                    '',   // Aula
                    (string)(($data['fecha'] ?? '').' 00:00 - '.($data['fecha'] ?? '').' 23:59'),
                    (string)($data['estado'] ?? '')
                ];
                foreach ($vals as $i=>$v) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIndex + $i);
                    $addr = $col.$row;
                    $sheet->setCellValueExplicit($addr, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $style = $sheet->getStyle($addr);
                    $style->getAlignment()->setHorizontal('center')->setVertical('center');
                    $style->getBorders()->getOutline()->setBorderStyle($thinBorder)->getColor()->setARGB('FF000000');
                }
                // Estado color
                $estadoCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIndex + 7).$row;
                $e = strtolower((string)($data['estado'] ?? ''));
                $color = 'FF111827'; if ($e==='en_curso') $color='FF22C55E'; elseif($e==='programado') $color='FF3B82F6'; elseif($e==='cancelado' || $e==='suspendido') $color='FFEF4444';
                $sheet->getStyle($estadoCol)->getFont()->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle($estadoCol)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($color);
                $row++;
            }
            $sheet->freezePane('D'.($startRow+1));
            foreach (range('D','K') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="reporte_asistencias.xlsx"');
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save('php://output');
            return;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error generando Excel: '.$e->getMessage();
        }
    }
}

// Inicializar si se accede directamente
if (basename($_SERVER['PHP_SELF']) === 'AsistenteController.php') {
    new AsistenteController();
}
