<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../config/db.php';

class SeguimientoController {
    public function __construct() {
        if (function_exists('start_secure_session')) {
            start_secure_session();
        }
        if (!isset($_SESSION['usuario'])) {
            http_response_code(401);
            echo 'No autorizado';
            exit;
        }
        $action = $_GET['action'] ?? 'iniciar';
        if ($action === 'iniciar') {
            $this->iniciar();
        } elseif ($action === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->guardar();
        } else {
            http_response_code(400);
            echo 'Acción no válida';
        }
        exit;
    }

    public function iniciar() {
        header('Content-Type: text/html; charset=utf-8');
        $pdo = Database::conectar();
        $ficha_id = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : 0;
        $estudiante_id = isset($_GET['estudiante_id']) ? (int)$_GET['estudiante_id'] : 0;
        $fecha = $_GET['fecha'] ?? '';
        // Fallback: si falta ficha_id pero tenemos estudiante, obtener su ficha actual
        if ($estudiante_id > 0 && $ficha_id <= 0) {
            try {
                $stmtFx = $pdo->prepare("SELECT ficha_id FROM estudiantes WHERE id = ? LIMIT 1");
                $stmtFx->execute([$estudiante_id]);
                $ficha_id = (int)($stmtFx->fetchColumn() ?: 0);
            } catch (Exception $e) { /* noop */ }
        }
        // Fallback: si falta fecha, tomar la última fecha con ausencia registrada
        if ($estudiante_id > 0 && $fecha === '') {
            try {
                $stFec = $pdo->prepare("SELECT DATE(a.fecha) FROM asistencias a WHERE a.estudiante_id = ? "
                    . ($ficha_id>0 ? " AND a.ficha_id = ?" : "")
                    . " AND LOWER(a.estado) IN ('falla','fallo','ausente','no_asistio','no asistio','no asistió','inasistencia','noasistio') ORDER BY a.fecha DESC LIMIT 1");
                $params = [$estudiante_id]; if ($ficha_id>0) $params[] = $ficha_id; 
                $stFec->execute($params);
                $fecha = $stFec->fetchColumn() ?: '';
            } catch (Exception $e) { /* noop */ }
        }
        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha)) { $fecha = date('Y-m-d'); }
        if ($ficha_id <= 0 || $estudiante_id <= 0) {
            http_response_code(400);
            echo 'Parámetros inválidos';
            return;
        }

        // Marcar notificación como leída (usa marker en el mensaje)
        try {
            $marker = sprintf('[seguimiento:estudiante_id=%d;ficha_id=%d]', $estudiante_id, $ficha_id);
            $stmtMk = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE usuario_id = ? AND leido = 0 AND mensaje LIKE ?");
            $stmtMk->execute([ (int)$_SESSION['usuario']['id'], '%'.$marker.'%' ]);
        } catch (Exception $e) { /* noop */ }

        $stmt = $pdo->prepare("SELECT e.id, u.nombres, u.apellidos, u.numero_documento, e.telefono_acudiente, e.nombre_completo_acudiente, e.ficha_id
                                FROM estudiantes e JOIN usuarios u ON u.id = e.usuario_id WHERE e.id = ? LIMIT 1");
        $stmt->execute([$estudiante_id]);
        $est = $stmt->fetch(PDO::FETCH_ASSOC);
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

        // Cargar historial (últimos 10)
        $hist = [];
        try {
            $q = $pdo->prepare("SELECT fecha, via, contacto, telefono, motivo, observaciones, creado_en FROM seguimiento_ausencias WHERE estudiante_id = ? AND ficha_id = ? ORDER BY creado_en DESC LIMIT 10");
            $q->execute([$estudiante_id, $ficha_id]);
            $hist = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) { $hist = []; }

        $data = [ 'est' => $est, 'ficha' => $ficha, 'fecha' => $fecha, 'hist' => $hist ];
        include __DIR__ . '/../views/Seguimiento/iniciar.php';
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

        // Insertar registro
        $ins = $pdo->prepare("INSERT INTO seguimiento_ausencias (estudiante_id, ficha_id, fecha, via, contacto, telefono, motivo, observaciones, creado_por) VALUES (?,?,?,?,?,?,?,?,?)");
        $ins->execute([$estudiante_id, $ficha_id, $fecha, $via, $contacto, $telefono, $motivo, $observaciones, $_SESSION['usuario']['id'] ?? null]);

        // Obtener datos para el mensaje
        $stmt = $pdo->prepare("SELECT e.id, u.nombres, u.apellidos, e.telefono_acudiente, e.nombre_completo_acudiente FROM estudiantes e JOIN usuarios u ON u.id = e.usuario_id WHERE e.id = ? LIMIT 1");
        $stmt->execute([$estudiante_id]);
        $est = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmtF = $pdo->prepare("SELECT id, nombre, numero FROM fichas WHERE id = ? LIMIT 1");
        $stmtF->execute([$ficha_id]);
        $ficha = $stmtF->fetch(PDO::FETCH_ASSOC);

        // Notificar a administradores (1) y asistentes (4)
        $dest = [];
        $q1 = $pdo->query("SELECT id FROM usuarios WHERE rol_id IN (1,4)");
        $dest = $q1->fetchAll(PDO::FETCH_COLUMN);

        $aprendiz = trim(($est['nombres'] ?? '') . ' ' . ($est['apellidos'] ?? ''));
        $titulo = 'Seguimiento de ausencia - ' . $aprendiz;
        $mensaje = "Se inició seguimiento por ausencia.\n" .
                   "Aprendiz: {$aprendiz}\n" .
                   "Ficha: {$ficha['numero']} ({$ficha['nombre']})\n" .
                   "Fecha: {$fecha}\n" .
                   "Vía: {$via}\n" .
                   "Contacto: {$contacto} - Tel: {$telefono}\n" .
                   "Motivo: {$motivo}\n" .
                   "Observaciones: {$observaciones}";
        $stmtN = $pdo->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje) VALUES (?, ?, ?)");
        foreach ($dest as $uId) {
            $stmtN->execute([(int)$uId, $titulo, $mensaje]);
        }
        // Redirigir al dashboard del asistente con mensaje de éxito
        header('Location: /?page=asistente&msg=seguimiento_ok');
        exit;
    }
}

// Bootstrap controller
new SeguimientoController();
