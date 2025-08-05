<?php
require_once '../config/db.php';

class TareaController
{
    public static function guardarAsistencias()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $pdo = Database::conectar();

        $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
        $ficha_id = $_POST['ficha_id'] ?? null;
        $fecha = date('Y-m-d');
        $asistencias = $_POST['asistencias'] ?? [];

        if (!$profesor_id || !$ficha_id) {
            die("❌ Falta información obligatoria (profesor_id o ficha_id).");
        }

        // Verificar si ya hay asistencia hoy para esa ficha
        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*) FROM asistencias 
            WHERE profesor_id = ? AND ficha_id = ? AND fecha = ?
        ");
        $stmtCheck->execute([$profesor_id, $ficha_id, $fecha]);
        $existe = $stmtCheck->fetchColumn();

        if ($existe > 0) {
            header("Location: /?page=dashboard_profesor&error=asistencia_ya_registrada");
            exit;
        }

        // Obtener nombre del profesor
        $stmtProfe = $pdo->prepare("
            SELECT u.nombres, u.apellidos 
            FROM profesores p
            JOIN usuarios u ON u.id = p.usuario_id
            WHERE p.id = ?
        ");
        $stmtProfe->execute([$profesor_id]);
        $profesor = $stmtProfe->fetch(PDO::FETCH_ASSOC);
        $nombre_profesor = $profesor ? $profesor['nombres'] . ' ' . $profesor['apellidos'] : "Desconocido";

        // Obtener número de ficha
        $stmtFicha = $pdo->prepare("SELECT nombre FROM fichas WHERE id = ?");
        $stmtFicha->execute([$ficha_id]);
        $numero_ficha = $stmtFicha->fetchColumn() ?? "Desconocida";

        // Notificación a todos los admins
        $adminQuery = $pdo->query("SELECT id FROM usuarios WHERE rol_id = 1");
        $admins = $adminQuery->fetchAll(PDO::FETCH_ASSOC);

        foreach ($admins as $admin) {
            $stmtNoti = $pdo->prepare("
                INSERT INTO notificaciones (tipo_usuario, usuario_id, mensaje, estado, fecha)
                VALUES (?, ?, ?, 'no_leida', NOW())
            ");
            $mensaje = "Asistencia registrada para la ficha $numero_ficha por el profesor $nombre_profesor";
            $stmtNoti->execute([
                'administrador',
                $admin['id'],
                $mensaje
            ]);
        }

        // Guardar asistencias
        foreach ($asistencias as $a) {
            if (!isset($a['estudiante_id']) || !is_numeric($a['estudiante_id'])) continue;

            $stmt = $pdo->prepare("
                INSERT INTO asistencias (estudiante_id, profesor_id, ficha_id, fecha, estado, observacion)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE estado = VALUES(estado), observacion = VALUES(observacion)
            ");

            $stmt->execute([
                (int)$a['estudiante_id'],
                (int)$profesor_id,
                (int)$ficha_id,
                $fecha,
                $a['estado'],
                $a['observacion'] ?? null
            ]);
        }

        // Redirigir al dashboard
        header("Location: /?page=dashboard_profesor&success=asistencia");
        exit;
    }

    public static function guardarTarea()
    {
        require_once '../config/db.php';
        session_start();

        $titulo = $_POST['titulo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $materia_id = $_POST['materia_id'] ?? null;
        $ficha_id = $_POST['ficha_id'] ?? null;
        $fecha_entrega = $_POST['fecha_entrega'] ?? null;
        $profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;

        if (!$titulo || !$materia_id || !$ficha_id || !$profesor_id) {
            die('❌ Faltan datos obligatorios para guardar la tarea.');
        }

        $pdo = Database::conectar();
        $stmt = $pdo->prepare("INSERT INTO tareas (titulo, descripcion, materia_id, profesor_id, ficha_id, fecha_entrega) 
                            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$titulo, $descripcion, $materia_id, $profesor_id, $ficha_id, $fecha_entrega]);

        header("Location: /?page=dashboard_profesor&success=tarea_guardada");
        exit;
    }

    public static function guardarNotas()
    {
        require_once '../config/db.php';
        if (session_status() === PHP_SESSION_NONE) session_start();

        $notas = $_POST['notas'] ?? [];

        if (empty($notas)) {
            die('❌ Faltan datos obligatorios para guardar notas.');
        }

        $pdo = Database::conectar();

        foreach ($notas as $estudiante_id => $tareas) {
            foreach ($tareas as $tarea_id => $nota) {
                if ($nota === '') continue; // Evitar guardar campos vacíos

                $stmt = $pdo->prepare("INSERT INTO entregas (estudiante_id, tarea_id, nota) 
                                    VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE nota = VALUES(nota)");
                $stmt->execute([$estudiante_id, $tarea_id, $nota]);
            }
        }

        header("Location: /?page=ver_notas&ficha_id=" . ($_POST['ficha_id'] ?? ''));
        exit;
    }

}
