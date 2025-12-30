<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Ficha.php';

class SeguimientoController {
    private $usuarioModel;
    private $fichaModel;

    public function __construct() { 
        if (!isset($_SESSION['usuario'])) {
            http_response_code(401);
            echo json_encode(['error' => 'No autorizado']);
            exit();
        }

        $this->usuarioModel = new Usuario();
        $this->fichaModel   = new Ficha();

        $action = $_GET['action'] ?? 'iniciar';

        if ($action === 'iniciar') {
            $this->iniciar();
        } elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar();
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
        }
    }

    public function iniciar() {
        $ficha_id = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : 0;
        $estudiante_id = isset($_GET['estudiante_id']) ? (int)$_GET['estudiante_id'] : 0;
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha)) {
            $fecha = date('Y-m-d');
        }

        if ($ficha_id <= 0 || $estudiante_id <= 0) {
            http_response_code(400);
            echo 'Parámetros inválidos';
            return;
        }

        // Conexión a BD para operaciones directas
        $pdo = Database::conectar();

        // Marcar notificación como leída (usa marker en el mensaje)
        try {
            $marker = sprintf('[seguimiento:estudiante_id=%d;ficha_id=%d]', $estudiante_id, $ficha_id);
            $stmtMk = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE usuario_id = ? AND leido = 0 AND mensaje LIKE ?");
            $stmtMk->execute([ (int)$_SESSION['usuario']['id'], '%'.$marker.'%' ]);
        } catch (Exception $e) { /* noop */ }

        // Obtener información del estudiante de la tabla aprendices
        $est = null;
        try {
            // Consulta optimizada basada en la estructura real de la tabla
            $sql = "SELECT 
                        u.id,
                        u.nombres, 
                        u.apellidos, 
                        u.numero_documento,
                        e.telefono_acudiente, 
                        e.nombre_completo_acudiente, 
                        e.ficha_id,
                        e.usuario_id,
                        e.colegio_id,
                        e.grado,
                        e.grupo,
                        e.jornada,
                        e.estado
                    FROM usuarios u
                    JOIN aprendices e ON e.usuario_id = u.id
                    WHERE e.usuario_id = ?
                    LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$estudiante_id]);
            $est = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$est) {
                // Si no se encuentra, intentar con el ID de usuario en lugar del ID de aprendiz
                $sql = "SELECT 
                            u.id,
                            u.nombres, 
                            u.apellidos, 
                            u.numero_documento,
                            e.telefono_acudiente, 
                            e.nombre_completo_acudiente, 
                            e.ficha_id,
                            e.usuario_id,
                            e.colegio_id,
                            e.grado,
                            e.grupo,
                            e.jornada,
                            e.estado
                        FROM usuarios u
                        JOIN aprendices e ON e.usuario_id = u.id
                        WHERE u.id = ?
                        LIMIT 1";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$estudiante_id]);
                $est = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$est) {
                    throw new Exception("No se encontró el estudiante con ID: " . $estudiante_id);
                }
            }
            
        } catch (Exception $e) {
            throw new Exception("Error al obtener información del estudiante: " . $e->getMessage());
        }
        $stmtF = $pdo->prepare("SELECT id, nombre, numero FROM fichas WHERE id = ? LIMIT 1");
        $stmtF->execute([$ficha_id]);
        $ficha = $stmtF->fetch(PDO::FETCH_ASSOC);
        
        // Asegurar tabla de historial
        $pdo->exec("CREATE TABLE IF NOT EXISTS seguimiento_ausencias (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            estudiante_id INT UNSIGNED NOT NULL,
            ficha_id INT UNSIGNED NOT NULL,
            fecha DATE NOT NULL,
            via VARCHAR(40) NULL,
            contacto VARCHAR(60) NULL,
            telefono VARCHAR(40) NULL,
            motivo VARCHAR(120) NULL,
            observaciones TEXT NULL,
            creado_por INT UNSIGNED NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY k_sa_est_ficha (estudiante_id, ficha_id, fecha)
        ) ENGINE=InnoDB");

        // Cargar historial (últimos 10) tolerante a nombres de columnas
        $hist = [];
        try {
            $cols = [];
            try { $cols = $pdo->query("SHOW COLUMNS FROM seguimiento_ausencias")->fetchAll(PDO::FETCH_COLUMN) ?: []; } catch (Throwable $_) { $cols = []; }
            $cEst  = in_array('estudiante_id', $cols, true) ? 'estudiante_id' : (in_array('aprendiz_id', $cols, true) ? 'aprendiz_id' : (in_array('estudiante', $cols, true) ? 'estudiante' : null));
            $cFicha= in_array('ficha_id', $cols, true) ? 'ficha_id' : (in_array('ficha', $cols, true) ? 'ficha' : null);
            if ($cEst && $cFicha) {
                $sqlH = "SELECT fecha, via, contacto, telefono, motivo, observaciones, creado_en FROM seguimiento_ausencias WHERE $cEst = ? AND $cFicha = ? ORDER BY creado_en DESC LIMIT 10";
                $q = $pdo->prepare($sqlH);
                $q->execute([$estudiante_id, $ficha_id]);
                $hist = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $hist = [];
            }
        } catch (Exception $e) { $hist = []; }

        $data = [ 'est' => $est, 'ficha' => $ficha, 'fecha' => $fecha, 'hist' => $hist ];
        include __DIR__ . '/../views/Asistente/iniciar.php';
    }   

    public function guardar() {
        header('Content-Type: text/html; charset=utf-8');
        $pdo = Database::conectar();
        // Tomar primero de POST; si faltan, intentar leer de GET (fallback desde botón/URL)
        $ficha_id = isset($_POST['ficha_id']) ? (int)$_POST['ficha_id'] : 0;
        if ($ficha_id <= 0) { $ficha_id = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : 0; }
        $estudiante_id = isset($_POST['estudiante_id']) ? (int)$_POST['estudiante_id'] : 0;
        if ($estudiante_id <= 0) { $estudiante_id = isset($_GET['estudiante_id']) ? (int)$_GET['estudiante_id'] : 0; }
        // Fecha de inasistencia: si no llega, tomar hoy; normalizar a YYYY-MM-DD
        $fecha = $_POST['fecha'] ?? ($_GET['fecha'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { $fecha = date('Y-m-d'); }
        $via = $_POST['via'] ?? 'Llamada';
        $contacto = $_POST['contacto'] ?? 'Acudiente';
        $telefono = $_POST['telefono'] ?? '';
        $motivo = $_POST['motivo'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';

        if ($ficha_id <= 0 || $estudiante_id <= 0) {
            http_response_code(400);
            echo '<div style="max-width:680px;margin:24px auto;font-family:system-ui;">'
               . '<h3>Parámetros inválidos</h3>'
               . '<p>Faltan identificadores de ficha o aprendiz. Intenta abrir el seguimiento desde el listado (botón Iniciar) para que se pasen correctamente.</p>'
               . '<p><a href="/?page=asistente" style="color:#0d6efd;">Volver al asistente</a></p>'
               . '</div>';
            return;
        }

        // Asegurar tabla de historial
        $pdo->exec("CREATE TABLE IF NOT EXISTS seguimiento_ausencias (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            estudiante_id INT UNSIGNED NOT NULL,
            ficha_id INT UNSIGNED NOT NULL,
            fecha DATE NOT NULL,
            via VARCHAR(40) NULL,
            contacto VARCHAR(60) NULL,
            telefono VARCHAR(40) NULL,
            motivo VARCHAR(120) NULL,
            observaciones TEXT NULL,
            creado_por INT UNSIGNED NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY k_sa_est_ficha (estudiante_id, ficha_id, fecha)
        ) ENGINE=InnoDB");

        // Intentar obtener la asistencia del día para vincular el proceso (soportando esquemas distintos)
        $asistenciaId = null;
        $aprendizId = null;
        try {
            $stApr = $pdo->prepare("SELECT id FROM aprendices WHERE usuario_id = ? LIMIT 1");
            $stApr->execute([$estudiante_id]);
            $aprendizId = ($stApr->fetchColumn() ?: null);
        } catch (Throwable $_apr) { $aprendizId = null; }
        try {
            // Detectar columnas de asistencias
            $colsA = $pdo->query("SHOW COLUMNS FROM asistencias")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $hasFecha = in_array('fecha', $colsA, true);
            $hasFechaDia = in_array('fecha_dia', $colsA, true);
            $hasFicha = in_array('ficha_id', $colsA, true);
            $cFicha = $hasFicha ? 'ficha_id = ? AND ' : '';

            $idList = [$estudiante_id];
            if ($aprendizId) { $idList[] = (int)$aprendizId; }
            $placeIds = implode(',', array_fill(0, count($idList), '?'));

            // Try con fecha
            if ($hasFecha) {
                $sql1 = "SELECT id FROM asistencias WHERE $cFicha estudiante_id IN ($placeIds) AND DATE(fecha) = ? ORDER BY fecha DESC LIMIT 1";
                $st1 = $pdo->prepare($sql1);
                $params1 = $hasFicha ? array_merge([$ficha_id], $idList, [$fecha]) : array_merge($idList, [$fecha]);
                $st1->execute($params1);
                $aid = $st1->fetchColumn();
                if ($aid) { $asistenciaId = (int)$aid; }
            }
            // Try con fecha_dia
            if ($asistenciaId === null && $hasFechaDia) {
                $sql2 = "SELECT id FROM asistencias WHERE $cFicha estudiante_id IN ($placeIds) AND fecha_dia = ? ORDER BY id DESC LIMIT 1";
                $st2 = $pdo->prepare($sql2);
                $params2 = $hasFicha ? array_merge([$ficha_id], $idList, [$fecha]) : array_merge($idList, [$fecha]);
                $st2->execute($params2);
                $aid2 = $st2->fetchColumn();
                if ($aid2) { $asistenciaId = (int)$aid2; }
            }
        } catch (Throwable $_a) { $asistenciaId = $asistenciaId ?? null; }

        // Si hay asistencia del día, marcarla como 'justificado' para que no aparezca en 'Ausentes de Hoy'
        if ($asistenciaId !== null) {
            try {
                $up = $pdo->prepare("UPDATE asistencias SET estado_asistencia = 'justificado' WHERE id = ?");
                $up->execute([$asistenciaId]);
                // Intentar también actualizar el id de estado si existe
                try {
                    $colsA = $pdo->query("SHOW COLUMNS FROM asistencias")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    if (in_array('estado_asistencia_id', $colsA, true)) {
                        $up2 = $pdo->prepare("UPDATE asistencias SET estado_asistencia_id = 3 WHERE id = ?");
                        $up2->execute([$asistenciaId]);
                    }
                    if (in_array('excusa_aprobada', $colsA, true)) {
                        $up3 = $pdo->prepare("UPDATE asistencias SET excusa_aprobada = 1 WHERE id = ?");
                        $up3->execute([$asistenciaId]);
                    }
                } catch (Throwable $_idUp) { /* noop */ }
            } catch (Exception $_up) { /* noop */ }
        }
        // Si no existe, crear una asistencia 'justificado' para evitar que aparezca en derivaciones por HF
        if ($asistenciaId === null) {
            try {
                $colsA = $pdo->query("SHOW COLUMNS FROM asistencias")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $namesA = [];
                $valsA  = [];
                if (in_array('estudiante_id', $colsA, true)) { $namesA[] = 'estudiante_id'; $valsA[] = $estudiante_id; }
                if (in_array('ficha_id', $colsA, true))      { $namesA[] = 'ficha_id';      $valsA[] = $ficha_id; }
                if (in_array('fecha', $colsA, true))         { $namesA[] = 'fecha';         $valsA[] = $fecha . ' 00:00:00'; }
                if (in_array('fecha_dia', $colsA, true))     { $namesA[] = 'fecha_dia';     $valsA[] = $fecha; }
                if (in_array('estado_asistencia', $colsA, true)) { $namesA[] = 'estado_asistencia'; $valsA[] = 'justificado'; }
                if (in_array('estado_asistencia_id', $colsA, true)) { $namesA[] = 'estado_asistencia_id'; $valsA[] = 3; }
                if (in_array('excusa_aprobada', $colsA, true)) { $namesA[] = 'excusa_aprobada'; $valsA[] = 1; }
                if (!empty($namesA)) {
                    $phA = rtrim(str_repeat('?,', count($namesA)), ',');
                    $sqlA = 'INSERT INTO asistencias (' . implode(',', $namesA) . ') VALUES (' . $phA . ')';
                    $insA = $pdo->prepare($sqlA);
                    $insA->execute($valsA);
                    $asistenciaId = (int)$pdo->lastInsertId();
                }
            } catch (Throwable $_makeA) { /* noop */ }
        }

        // Asegurar columnas opcionales (asistencia_id, estado) si no existen
        $cols = [];
        try { $cols = $pdo->query("SHOW COLUMNS FROM seguimiento_ausencias")->fetchAll(PDO::FETCH_COLUMN) ?: []; } catch (Throwable $_) { $cols = []; }
        if (!in_array('asistencia_id', $cols, true)) {
            try { $pdo->exec("ALTER TABLE seguimiento_ausencias ADD COLUMN asistencia_id BIGINT NULL"); } catch (Throwable $_alt1) { /* noop */ }
        }
        if (!in_array('estado', $cols, true)) {
            try { $pdo->exec("ALTER TABLE seguimiento_ausencias ADD COLUMN estado VARCHAR(20) NULL"); } catch (Throwable $_alt2) { /* noop */ }
        }
        try { $cols = $pdo->query("SHOW COLUMNS FROM seguimiento_ausencias")->fetchAll(PDO::FETCH_COLUMN) ?: []; } catch (Throwable $_) { $cols = []; }

        // Insertar registro detectando columnas reales
        $cEst   = in_array('estudiante_id', $cols, true) ? 'estudiante_id' : (in_array('aprendiz_id', $cols, true) ? 'aprendiz_id' : (in_array('estudiante', $cols, true) ? 'estudiante' : null));
        $cFicha = in_array('ficha_id', $cols, true) ? 'ficha_id' : (in_array('ficha', $cols, true) ? 'ficha' : null);
        $cFecha = in_array('fecha', $cols, true) ? 'fecha' : (in_array('fecha_contacto', $cols, true) ? 'fecha_contacto' : null);
        if (!$cEst || !$cFicha || !$cFecha) {
            http_response_code(500);
            echo '<div style="max-width:680px;margin:24px auto;font-family:system-ui;">'
               . '<h3>Error de configuración</h3>'
               . '<p>La tabla seguimiento_ausencias no contiene las columnas requeridas.</p>'
               . '</div>';
            return;
        }
        $names = [$cEst, $cFicha, $cFecha];
        $vals  = [$estudiante_id, $ficha_id, $fecha];
        if (in_array('asistencia_id', $cols, true) && $asistenciaId !== null) { $names[] = 'asistencia_id'; $vals[] = $asistenciaId; }
        foreach (['via'=> $via, 'contacto'=> $contacto, 'telefono'=> $telefono, 'motivo'=> $motivo, 'observaciones'=> $observaciones, 'creado_por'=> ($_SESSION['usuario']['id'] ?? null)] as $k=>$v) {
            if (in_array($k, $cols, true)) { $names[] = $k; $vals[] = $v; }
        }
        if (in_array('estado', $cols, true)) { $names[] = 'estado'; $vals[] = 'justificado'; }
        $ph = rtrim(str_repeat('?,', count($names)), ',');
        $sqlI = 'INSERT INTO seguimiento_ausencias (' . implode(',', $names) . ') VALUES (' . $ph . ')';
        $ins = $pdo->prepare($sqlI);
        $ins->execute($vals);

        // Obtener datos para el mensaje
        $stmt = $pdo->prepare("SELECT e.usuario_id as id, u.nombres, u.apellidos, e.telefono_acudiente, e.nombre_completo_acudiente 
                              FROM aprendices e 
                              JOIN usuarios u ON u.id = e.usuario_id 
                              WHERE e.usuario_id = ?    
                              LIMIT 1");
        $stmt->execute([$estudiante_id]);
        $est = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$est) {
            // Si no se encuentra, intentar con el ID de usuario
            $stmt = $pdo->prepare("SELECT e.usuario_id as id, u.nombres, u.apellidos, e.telefono_acudiente, e.nombre_completo_acudiente 
                                  FROM aprendices e 
                                  JOIN usuarios u ON u.id = e.usuario_id 
                                  WHERE u.id = ? 
                                  LIMIT 1");
            $stmt->execute([$estudiante_id]);
            $est = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $stmtF = $pdo->prepare("SELECT id, nombre, numero FROM fichas WHERE id = ? LIMIT 1");
        $stmtF->execute([$ficha_id]);
        $ficha = $stmtF->fetch(PDO::FETCH_ASSOC);

        // Notificar a administradores (1) y asistentes (4)
        $dest = [];
        $q1 = $pdo->query("SELECT id FROM usuarios WHERE rol_id IN (1,4)");
        $dest = $q1->fetchAll(PDO::FETCH_COLUMN);

        $aprendiz = trim(($est['nombres'] ?? '') . ' ' . ($est['apellidos'] ?? ''));
        $titulo = 'Seguimiento de ausencia - ' . $aprendiz;
        // Notificación corta y genérica para el panel: sin motivo ni observaciones largas
        $mensaje = 'Se realizó proceso a Aprendiz';
        $stmtN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje) VALUES (?, ?, ?)");
        foreach ($dest as $uId) {
            $stmtN->execute([(int)$uId, $titulo, $mensaje]);
        }
        // Redirigir al dashboard del asistente con mensaje de éxito
        header('Location: /?page=asistente&msg=seguimiento_ok');
        exit;
    }
}
