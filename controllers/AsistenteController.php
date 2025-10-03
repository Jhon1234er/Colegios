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
        start_secure_session();
        require_login();
        // rol 4 = asistente
        require_role([1,4]); // permitir también admin
        $this->pdo = Database::conectar();

        $action = $_GET['action'] ?? '';
        if ($action === 'resumen_hoy') {
            $this->resumenHoy();
            return;
        }
        if ($action === 'ausentes_hoy') {
            $this->ausentesHoy();
            return;
        }
        if ($action === 'colegios') {
            $this->listarColegios();
            return;
        }
        if ($action === 'reporte_csv') {
            $this->reporteCSV();
            return;
        }
        if ($action === 'reporte_excel') {
            $this->reporteExcel();
            return;
        }
        if ($action === 'notificaciones') {
            $this->notificacionesRecientes();
            return;
        }
        if ($action === 'notificar_falta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->notificarFalta();
            return;
        }
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
                           WHERE DATE(hf.fecha_inicio)=?" . ($colegio_id>0 ? " AND f.colegio_id = ?" : "");
            $stmt = $this->pdo->prepare($sqlClases);
            $paramsClases = [$hoy]; if ($colegio_id>0) $paramsClases[] = $colegio_id;
            $stmt->execute($paramsClases);
            $totalClases = (int)$stmt->fetchColumn();

            // en curso
            $sqlEnCurso = "SELECT COUNT(*)
                           FROM horarios_fichas hf
                           JOIN fichas f ON hf.ficha_id = f.id
                           WHERE hf.estado='en_curso' AND NOW() BETWEEN hf.fecha_inicio AND hf.fecha_fin" . ($colegio_id>0 ? " AND f.colegio_id = ?" : "");
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
                        WHERE hf.fecha_inicio>NOW()" . ($colegio_id>0 ? " AND f.colegio_id = ?" : "") . "
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
            $stmt = $this->pdo->query("SELECT id, nombre FROM colegios ORDER BY nombre");
            $colegios = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->respondJSON(['success'=>true,'data'=>$colegios]);
        } catch (Exception $e) {
            $this->respondJSON(['success'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    private function reporteCSV() {
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
                WHERE a.fecha BETWEEN ? AND ? $filtroColegio
                ORDER BY a.fecha ASC, estudiante ASC
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="reporte_asistencias.csv"');
            echo "\xEF\xBB\xBF"; // BOM UTF-8
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Fecha','Estudiante','Documento','Ficha','Nombre Ficha','Estado']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['fecha'],
                    $r['estudiante'],
                    $r['numero_documento'],
                    $r['ficha_numero'],
                    $r['ficha_nombre'],
                    $r['estado']
                ]);
            }
            fclose($out);
        } catch (Exception $e) {
            http_response_code(500);
            echo 'Error generando CSV: ' . $e->getMessage();
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
