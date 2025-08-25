<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';

class TareaController // (podemos renombrarla luego a AsistenciaController)
{
    public static function guardarAsistencias()
    {
        start_secure_session();
        require_login(); require_role(2);
        csrf_validate();

        $pdo = Database::conectar();

        $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
        $ficha_id    = $_POST['ficha_id'] ?? null;
        $fecha       = date('Y-m-d');
        $hora        = date('H:i:s');
        $asistencias = $_POST['asistencias'] ?? [];

        if (!$profesor_id || !$ficha_id) {
            http_response_code(400);
            echo "❌ Falta información obligatoria (profesor_id o ficha_id).";
            exit;
        }

        // Evitar duplicado diario
        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*) FROM asistencias 
            WHERE profesor_id = ? AND ficha_id = ? AND fecha = ?
        ");
        $stmtCheck->execute([$profesor_id, $ficha_id, $fecha]);
        if ($stmtCheck->fetchColumn() > 0) {
            header("Location: /?page=dashboard_profesor&error=asistencia_ya_registrada");
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO asistencias (estudiante_id, profesor_id, ficha_id, fecha, hora, estado, observacion)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($asistencias as $a) {
            if (!isset($a['estudiante_id'], $a['estado'])) continue;
            $estado = in_array($a['estado'], ['presente','tarde','falla'], true) ? $a['estado'] : 'presente';
            $stmt->execute([
                (int)$a['estudiante_id'],
                (int)$profesor_id,
                (int)$ficha_id,
                $fecha,
                $hora,
                $estado,
                $a['observacion'] ?? null
            ]);
        }

        header("Location: /?page=dashboard_profesor&success=asistencia");
        exit;
    }
}
