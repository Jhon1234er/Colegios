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

    // ===== HISTORIAL: AUSENCIAS PENDIENTES DE PROCESO =====
    private function historialPendientes() {
        header('Content-Type: application/json');
        try {
            $colegio_id = isset($_GET['colegio_id']) ? (int)$_GET['colegio_id'] : 0;
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
                        SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                               CONCAT(u.nombres,' ',u.apellidos) AS nombres, f.nombre AS ficha_nombre
                        FROM asistencias a
                        JOIN estudiantes e ON a.estudiante_id = e.id
                        JOIN usuarios u ON e.usuario_id = u.id
                        JOIN fichas f ON a.ficha_id = f.id
                        JOIN `$tabla` sa ON sa.`$fk` = a.id
                        WHERE LOWER(a.estado) IN ('falla','fallo','ausente','no_asistio','no asistio','no asistió','inasistencia','noasistio')
                          AND (sa.estado IN ('pendiente','en_proceso','abierto'))
                          " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                        ORDER BY a.fecha DESC
                        LIMIT 200
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($rows)) break; // éxito con estado
                } catch (Exception $inner) { /* intentar variante 2 abajo */ }
                try {
                    // Variante 2: pendiente si NO existe registro en la tabla de proceso
                    $sql = "
                        SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                               CONCAT(u.nombres,' ',u.apellidos) AS nombres, f.nombre AS ficha_nombre
                        FROM asistencias a
                        JOIN estudiantes e ON a.estudiante_id = e.id
                        JOIN usuarios u ON e.usuario_id = u.id
                        JOIN fichas f ON a.ficha_id = f.id
                        LEFT JOIN `$tabla` sa ON sa.`$fk` = a.id
                        WHERE LOWER(a.estado) IN ('falla','fallo','ausente','no_asistio','no asistio','no asistió','inasistencia','noasistio')
                          AND sa.`$fk` IS NULL
                          " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                        ORDER BY a.fecha DESC
                        LIMIT 200
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    break; // éxito
                } catch (Exception $inner2) {
                    // Variante 3: relación por (estudiante_id, fecha) cuando no existe FK a asistencia
                    try {
                        $sql = "
                            SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                                   CONCAT(u.nombres,' ',u.apellidos) AS nombres, f.nombre AS ficha_nombre
                            FROM asistencias a
                            JOIN estudiantes e ON a.estudiante_id = e.id
                            JOIN usuarios u ON e.usuario_id = u.id
                            JOIN fichas f ON a.ficha_id = f.id
                            LEFT JOIN `$tabla` sa ON sa.estudiante_id = e.id AND DATE(sa.fecha) = DATE(a.fecha)
                            WHERE LOWER(a.estado) IN ('falla','fallo','ausente','no_asistio','no asistio','no asistió','inasistencia','noasistio')
                              AND sa.estudiante_id IS NULL
                              " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                            ORDER BY a.fecha DESC
                            LIMIT 200
                        ";
                        $stmt = $this->pdo->prepare($sql);
                        if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (Exception $inner3) { /* swallow and follow with fallback */ }
                    continue;
                }
            }
            // Fallback: si no hay tablas de proceso o no devolvió nada, mostrar ausentes de hoy
            if ($rows === [] && $colegio_id >= 0) {
                $hoy = date('Y-m-d');
                $sql = "
                    SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                           CONCAT(u.nombres,' ',u.apellidos) AS nombres, f.nombre AS ficha_nombre
                    FROM asistencias a
                    JOIN estudiantes e ON a.estudiante_id = e.id
                    JOIN usuarios u ON e.usuario_id = u.id
                    JOIN fichas f ON a.ficha_id = f.id
                    WHERE DATE(a.fecha)=? AND LOWER(a.estado) IN ('falla','fallo','ausente','no_asistio','no asistio','no asistió','inasistencia','noasistio')
                          " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                    ORDER BY a.fecha DESC
                    LIMIT 200
                ";
                $stmt = $this->pdo->prepare($sql);
                $params = [$hoy]; if ($colegio_id>0) $params[] = $colegio_id; 
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            $this->respondJSON(['success'=>true,'data'=>$rows]);
        } catch (Exception $e) {
            $this->respondJSON(['success'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // ===== HISTORIAL: AUSENCIAS CON PROCESO REALIZADO =====
    private function historialProcesados() {
        header('Content-Type: application/json');
        try {
            $colegio_id = isset($_GET['colegio_id']) ? (int)$_GET['colegio_id'] : 0;
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
                    // Variante 1: estado completado/finalizado
                    $sql = "
                        SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                               CONCAT(u.nombres,' ',u.apellidos) AS nombres, f.nombre AS ficha_nombre,
                               sa.$selProc
                        FROM asistencias a
                        JOIN estudiantes e ON a.estudiante_id = e.id
                        JOIN usuarios u ON e.usuario_id = u.id
                        JOIN fichas f ON a.ficha_id = f.id
                        JOIN `$tabla` sa ON sa.`$fk` = a.id
                        WHERE LOWER(a.estado) IN ('falla','fallo','ausente','no_asistio','no asistio','no asistió','inasistencia','noasistio')
                          AND (sa.estado IN ('completado','finalizado','cerrado'))
                          " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                        ORDER BY a.fecha DESC
                        LIMIT 200
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($rows)) break;
                } catch (Exception $inner) { /* intentar variante 2 */ }
                try {
                    // Variante 2: procesado si existe registro (sin columna estado)
                    $sql = "
                        SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                               CONCAT(u.nombres,' ',u.apellidos) AS nombres, f.nombre AS ficha_nombre,
                               sa.$selProc
                        FROM asistencias a
                        JOIN estudiantes e ON a.estudiante_id = e.id
                        JOIN usuarios u ON e.usuario_id = u.id
                        JOIN fichas f ON a.ficha_id = f.id
                        JOIN `$tabla` sa ON sa.`$fk` = a.id
                        WHERE LOWER(a.estado) IN ('falla','fallo','ausente','no_asistio','no asistio','no asistió','inasistencia','noasistio')
                          " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                        ORDER BY a.fecha DESC
                        LIMIT 200
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    if (!empty($rows)) break;
                } catch (Exception $inner2) {
                    // Variante 3: relación por (estudiante_id, fecha)
                    try {
                        $sql = "
                            SELECT a.id AS asistencia_id, a.estudiante_id, a.ficha_id, DATE(a.fecha) AS fecha,
                                   CONCAT(u.nombres,' ',u.apellidos) AS nombres, f.nombre AS ficha_nombre,
                                   sa.$selProc
                            FROM asistencias a
                            JOIN estudiantes e ON a.estudiante_id = e.id
                            JOIN usuarios u ON e.usuario_id = u.id
                            JOIN fichas f ON a.ficha_id = f.id
                            JOIN `$tabla` sa ON sa.estudiante_id = e.id AND DATE(sa.fecha) = DATE(a.fecha)
                            WHERE LOWER(a.estado) IN ('falla','fallo','ausente','no_asistio','no asistio','no asistió','inasistencia','noasistio')
                              " . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
                            ORDER BY a.fecha DESC
                            LIMIT 200
                        ";
                        $stmt = $this->pdo->prepare($sql);
                        if ($colegio_id>0) $stmt->execute([$colegio_id]); else $stmt->execute();
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (Exception $inner3) { /* swallow */ }
                    break;
                }
            }

            $this->respondJSON(['success'=>true,'data'=>$rows]);
        } catch (Exception $e) {
            $this->respondJSON(['success'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    private function estadisticasColegio() {
        header('Content-Type: application/json');
        try {
            $colegio_id = isset($_GET['colegio_id']) ? (int)$_GET['colegio_id'] : 0;
            if ($colegio_id <= 0) { throw new Exception('colegio_id requerido'); }

            $desde = $_GET['desde'] ?? null; // formato YYYY-MM-DD opcional
            $hasta = $_GET['hasta'] ?? null;

            // Info básica del colegio
            $stmt = $this->pdo->prepare("SELECT id, nombre, municipio, departamento FROM colegios WHERE id = ?");
            $stmt->execute([$colegio_id]);
            $colegio = $stmt->fetch(PDO::FETCH_ASSOC);

            // Contadores
            $totalProfes = (int)$this->pdo->query("SELECT COUNT(*) FROM profesores WHERE colegio_id = " . (int)$colegio_id)->fetchColumn();
            $totalEstu = (int)$this->pdo->query("SELECT COUNT(*) FROM estudiantes WHERE colegio_id = " . (int)$colegio_id)->fetchColumn();
            // Contar fichas a través de profesores
            $stmtFichas = $this->pdo->prepare("SELECT COUNT(DISTINCT f.id) FROM fichas f JOIN profesor_ficha pf ON f.id = pf.ficha_id JOIN profesores p ON pf.profesor_id = p.id WHERE p.colegio_id = ?");
            $stmtFichas->execute([$colegio_id]);
            $totalFichas = (int)$stmtFichas->fetchColumn();

            // Resumen asistencias en rango (o todo si no hay rango)
            $params = [$colegio_id];
            $rangoSql = '';
            if ($desde && $hasta) {
                $rangoSql = ' AND DATE(a.fecha) BETWEEN ? AND ? ';
                $params[] = $desde; $params[] = $hasta;
            }
            $sqlRes = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN a.estado = 'presente' THEN 1 ELSE 0 END) AS presentes,
                    SUM(CASE WHEN a.estado = 'ausente' THEN 1 ELSE 0 END) AS ausentes,
                    SUM(CASE WHEN a.estado = 'tarde' THEN 1 ELSE 0 END) AS tardanzas,
                    SUM(CASE WHEN a.estado = 'justificado' THEN 1 ELSE 0 END) AS justificados
                FROM asistencias a
                JOIN estudiantes e ON a.estudiante_id = e.id
                WHERE e.colegio_id = ? $rangoSql
            ";
            $stRes = $this->pdo->prepare($sqlRes);
            $stRes->execute($params);
            $resumen = $stRes->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'presentes'=>0,'ausentes'=>0,'tardanzas'=>0,'justificados'=>0];

            // Profesores del colegio
            $sqlProf = "
                SELECT p.id AS profesor_id, CONCAT(u.apellidos,' ',u.nombres) AS nombre, u.correo_electronico, u.telefono,
                       COALESCE(GROUP_CONCAT(DISTINCT m.nombre ORDER BY m.nombre SEPARATOR ', '), '') AS materias
                FROM profesores p
                JOIN usuarios u ON p.usuario_id = u.id
                LEFT JOIN materia_profesor mp ON p.id = mp.profesor_id
                LEFT JOIN materias m ON mp.materia_id = m.id
                WHERE p.colegio_id = ?
                GROUP BY p.id, u.apellidos, u.nombres, u.correo_electronico, u.telefono
                ORDER BY u.apellidos, u.nombres
            ";
            $stProf = $this->pdo->prepare($sqlProf);
            $stProf->execute([$colegio_id]);
            $profesores = $stProf->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Top aprendices con más ausencias (>=3)
            $sqlTop = "
                SELECT e.id AS estudiante_id, CONCAT(u.apellidos,' ',u.nombres) AS nombre, f.nombre AS ficha,
                       SUM(CASE WHEN a.estado='ausente' THEN 1 ELSE 0 END) AS total_fallas
                FROM estudiantes e
                JOIN usuarios u ON u.id = e.usuario_id
                LEFT JOIN fichas f ON e.ficha_id = f.id
                LEFT JOIN asistencias a ON a.estudiante_id = e.id
                WHERE e.colegio_id = ?
                GROUP BY e.id, u.apellidos, u.nombres, f.nombre
                HAVING total_fallas >= 3
                ORDER BY total_fallas DESC, nombre ASC
                LIMIT 50
            ";
            $stTop = $this->pdo->prepare($sqlTop);
            $stTop->execute([$colegio_id]);
            $aprendices_alerta = $stTop->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Fichas con total de faltas
            $sqlFichas = "
                SELECT f.id AS ficha_id, f.nombre AS ficha_nombre,
                       COUNT(CASE WHEN a.estado = 'ausente' THEN 1 END) AS total_fallas
                FROM fichas f
                JOIN profesor_ficha pf ON f.id = pf.ficha_id
                JOIN profesores p ON pf.profesor_id = p.id
                LEFT JOIN asistencias a ON a.ficha_id = f.id
                WHERE p.colegio_id = ?
                GROUP BY f.id, f.nombre
                ORDER BY total_fallas DESC, f.nombre ASC
            ";
            $stF = $this->pdo->prepare($sqlFichas);
            $stF->execute([$colegio_id]);
            $fichas_fallas = $stF->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $this->respondJSON([
                'success' => true,
                'colegio' => $colegio,
                'contadores' => [
                    'profesores' => $totalProfes,
                    'aprendices' => $totalEstu,
                    'fichas' => $totalFichas
                ],
                'resumen' => $resumen,
                'profesores' => $profesores,
                'aprendices_alerta' => $aprendices_alerta,
                'fichas_fallas' => $fichas_fallas
            ]);
        } catch (Exception $e) {
            $this->respondJSON(['success'=>false,'error'=>$e->getMessage()], 400);
        }
    }

    public function __construct() {
        start_secure_session();
        require_login();
        // rol 4 = asistente
        require_role([1,4]); // permitir también admin
        $this->pdo = Database::conectar();
        
        $action = $_GET['action'] ?? '';
        $page = $_GET['page'] ?? '';
        // Mapear también por nombre de page para compatibilidad con router existente
        if ($action === 'resumen_hoy' || $page === 'asistente_resumen') { $this->resumenHoy(); return; }
        if ($action === 'ausentes_hoy' || $page === 'asistente_ausentes') { $this->ausentesHoy(); return; }
        if ($action === 'colegios' || $page === 'asistente_colegios') { $this->listarColegios(); return; }
        if ($action === 'reporte_excel' || $page === 'asistente_reporte_excel') { $this->reporteExcel(); return; }
        if ($action === 'notificaciones' || $page === 'asistente_notificaciones') { $this->notificacionesRecientes(); return; }
        if (($action === 'notificar_falta' || $page === 'asistente_notificar') && $_SERVER['REQUEST_METHOD'] === 'POST') { $this->notificarFalta(); return; }
        if ($action === 'estadisticas_colegio' || $page === 'asistente_estadisticas') { $this->estadisticasColegio(); return; }
        // historial de ausencias (pendientes / procesados)
        if ($action === 'historial_pendientes' || $page === 'asistente_historial_pendientes') { $this->historialPendientes(); return; }
        if ($action === 'historial_procesados' || $page === 'asistente_historial_procesados') { $this->historialProcesados(); return; }
        // por defecto mostrar vista
        include __DIR__ . '/../views/Asistente/dashboard.php';
    }

    private function resumenHoy() {
        header('Content-Type: application/json');
        try {
            $hoy = date('Y-m-d');
            $colegio_id = isset($_GET['colegio_id']) ? (int)$_GET['colegio_id'] : 0;

            // total clases hoy
            $sqlClases = "SELECT COUNT(*)
                           FROM horarios_fichas hf
                           JOIN fichas f ON hf.ficha_id = f.id
                           " . ($colegio_id>0 ? "JOIN profesor_ficha pf ON f.id = pf.ficha_id
                           JOIN profesores p ON pf.profesor_id = p.id" : "") . "
                           WHERE DATE(hf.fecha_inicio)=?" . ($colegio_id>0 ? " AND p.colegio_id = ?" : "");
            $stmt = $this->pdo->prepare($sqlClases);
            $paramsClases = [$hoy]; if ($colegio_id>0) $paramsClases[] = $colegio_id;
            $stmt->execute($paramsClases);
            $totalClases = (int)$stmt->fetchColumn();

            // en curso (derivado por tiempo): ahora entre inicio y fin, excluye suspendidos
            $sqlEnCurso = "SELECT COUNT(*)
                           FROM horarios_fichas hf
                           JOIN fichas f ON hf.ficha_id = f.id
                           " . ($colegio_id>0 ? "JOIN profesor_ficha pf ON f.id = pf.ficha_id
                           JOIN profesores p ON pf.profesor_id = p.id" : "") . "
                           WHERE NOW() BETWEEN hf.fecha_inicio AND hf.fecha_fin
                             AND (hf.estado IS NULL OR LOWER(hf.estado) <> 'suspendido')" . ($colegio_id>0 ? " AND p.colegio_id = ?" : "");
            $stmt = $this->pdo->prepare($sqlEnCurso);
            if ($colegio_id>0) { $stmt->execute([$colegio_id]); $enCurso = (int)$stmt->fetchColumn(); }
            else { $stmt->execute(); $enCurso = (int)$stmt->fetchColumn(); }

            // faltas registradas hoy
            $sqlFaltas = "SELECT COUNT(*)
                           FROM asistencias a
                           JOIN estudiantes e ON a.estudiante_id = e.id
                           WHERE DATE(a.fecha)=? AND a.estado IN ('falla','no_asistio','ausente')" . ($colegio_id>0 ? " AND e.colegio_id = ?" : "");
            $stmt = $this->pdo->prepare($sqlFaltas);
            $paramsFaltas = [$hoy]; if ($colegio_id>0) $paramsFaltas[] = $colegio_id;
            $stmt->execute($paramsFaltas);
            $faltas = (int)$stmt->fetchColumn();

            // próxima clase
            $sqlProx = "SELECT hf.titulo, hf.fecha_inicio
                        FROM horarios_fichas hf
                        JOIN fichas f ON hf.ficha_id = f.id
                        " . ($colegio_id>0 ? "JOIN profesor_ficha pf ON f.id = pf.ficha_id
                        JOIN profesores p ON pf.profesor_id = p.id" : "") . "
                        WHERE hf.fecha_inicio>NOW()" . ($colegio_id>0 ? " AND p.colegio_id = ?" : "") . "
                        ORDER BY hf.fecha_inicio ASC LIMIT 1";
            $stmt = $this->pdo->prepare($sqlProx);
            if ($colegio_id>0) { $stmt->execute([$colegio_id]); $prox = $stmt->fetch(PDO::FETCH_ASSOC) ?: null; }
            else { $stmt->execute(); $prox = $stmt->fetch(PDO::FETCH_ASSOC) ?: null; }

            $this->respondJSON([
                'success' => true,
                'hoy' => $hoy,
                'total_clases' => $totalClases,
                'en_curso' => $enCurso,
                'faltas' => $faltas,
                'proxima_clase' => $prox
            ]);
        } catch (Exception $e) {
            $this->respondJSON(['success'=>false,'error'=>$e->getMessage()], 500);
        }
    }


    private function reporteExcel() {
        try {
            $colegio_id = isset($_GET['colegio_id']) ? (int)$_GET['colegio_id'] : 0;
            $desde = $_GET['desde'] ?? date('Y-m-01');
            $hasta = $_GET['hasta'] ?? date('Y-m-t');
            $estado = $_GET['estado'] ?? '';

            $params = [$desde, $hasta];
            $filtroColegio = '';
            if ($colegio_id > 0) { $filtroColegio = ' AND e.colegio_id = ? '; $params[] = $colegio_id; }
            $filtroEstado = '';
            if ($estado !== '') { $filtroEstado = ' AND a.estado = ? '; $params[] = $estado; }

            $sql = "
                SELECT 
                    DATE(a.fecha) as fecha,
                    CONCAT(u.nombres,' ',u.apellidos) as estudiante,
                    u.numero_documento,
                    f.numero as ficha_numero,
                    f.nombre as ficha_nombre,
                    a.estado
                FROM asistencias a
                JOIN estudiantes e ON a.estudiante_id = e.id
                JOIN usuarios u ON e.usuario_id = u.id
                JOIN fichas f ON a.ficha_id = f.id
                WHERE a.fecha BETWEEN ? AND ? $filtroColegio $filtroEstado
                ORDER BY a.fecha ASC, estudiante ASC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Asistencias');
            $headers = ['Fecha','Estudiante','Documento','Ficha','Nombre Ficha','Estado'];
            $sheet->fromArray($headers, null, 'A1');
            $sheet->fromArray(array_map(function($r){
                return [$r['fecha'],$r['estudiante'],$r['numero_documento'],$r['ficha_numero'],$r['ficha_nombre'],$r['estado']];
            }, $rows), null, 'A2');
            foreach (range('A','F') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="reporte_asistencias.xlsx"');
            $writer->save('php://output');
        } catch (Exception $e) {
            http_response_code(500);
            echo 'Error generando Excel: ' . $e->getMessage();
        }
    }

    private function ausentesHoy() {
        header('Content-Type: application/json');
        try {
            $hoy = date('Y-m-d');
            $colegio_id = isset($_GET['colegio_id']) ? (int)$_GET['colegio_id'] : 0;
            $sql = "
            SELECT a.id as asistencia_id, a.estudiante_id, a.ficha_id, u.nombres, u.apellidos, u.id as usuario_id,
                   f.nombre AS ficha_nombre
            FROM asistencias a
            JOIN estudiantes e ON a.estudiante_id = e.id
            JOIN usuarios u ON e.usuario_id = u.id
            JOIN fichas f ON a.ficha_id = f.id
            WHERE DATE(a.fecha) = ? AND a.estado IN ('falla','no_asistio','ausente')" . ($colegio_id>0 ? " AND e.colegio_id = ?" : "") . "
            ORDER BY u.nombres, u.apellidos
            ";
            $stmt = $this->pdo->prepare($sql);
            $params = [$hoy]; if ($colegio_id>0) $params[] = $colegio_id;
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->respondJSON(['success'=>true,'data'=>$rows]);
        } catch (Exception $e) {
            $this->respondJSON(['success'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    private function listarColegios() {
        header('Content-Type: application/json');
        try {
            $stmt = $this->pdo->query("SELECT id, nombre, tipo_institucion, departamento, municipio FROM colegios ORDER BY nombre");
            $colegios = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->respondJSON(['success'=>true,'data'=>$colegios]);
        } catch (Exception $e) {
            $this->respondJSON(['success'=>false,'error'=>$e->getMessage()], 500);
        }
    }


    private function notificarFalta() {
        header('Content-Type: application/json');
        try {
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) { $payload = $_POST; }
            $estudiante_id = (int)($payload['estudiante_id'] ?? 0);
            $ficha_id = (int)($payload['ficha_id'] ?? 0);
            $fecha = $payload['fecha'] ?? date('Y-m-d');
            if (!$estudiante_id || !$ficha_id) { throw new Exception('Datos incompletos'); }

            // Obtener usuario destino y datos
            $sql = "SELECT u.id as usuario_id, CONCAT(u.nombres,' ',u.apellidos) AS nombre
                    FROM estudiantes e JOIN usuarios u ON e.usuario_id=u.id
                    WHERE e.id=?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$estudiante_id]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$u) throw new Exception('Estudiante no encontrado');

            $usuarioDestino = (int)$u['usuario_id'];
            $titulo = 'Falta registrada';
            $mensaje = 'Se registró una inasistencia el ' . $fecha . ' en la ficha #' . $ficha_id;

            // Intentar inserción compatible con distintos esquemas
            try {
                // Esquema encabezado: (usuario_id, tipo_usuario, mensaje, fecha, estado)
                // Como destinatario es estudiante, marcamos tipo_usuario='estudiante'
                $stmtN = $this->pdo->prepare("INSERT INTO notificaciones (usuario_id, tipo_usuario, mensaje, fecha, estado) VALUES (?, 'estudiante', ?, NOW(), 'no_leida')");
                $stmtN->execute([$usuarioDestino, $titulo . ' - ' . $mensaje]);
            } catch (Exception $ex) {
                // Fallback esquema simple: (usuario_id, titulo, mensaje)
                try {
                    $stmtNF = $this->pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje) VALUES (?, ?, ?)");
                    $stmtNF->execute([$usuarioDestino, $titulo, $mensaje]);
                } catch (Exception $e2) {
                    throw $e2; // propagar si también falla el fallback
                }
            }

            $this->respondJSON(['success'=>true]);
        } catch (Exception $e) {
            $this->respondJSON(['success'=>false,'error'=>$e->getMessage()], 400);
        }
    }
}

// Inicializar si se accede directamente
if (basename($_SERVER['PHP_SELF']) === 'AsistenteController.php') {
    new AsistenteController();
}
