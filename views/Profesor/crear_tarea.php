<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';

$pdo = Database::conectar();

// Obtener ID del profesor logueado
$profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
if (!$profesor_id) {
    die("❌ No se ha identificado al profesor.");
}

// Fichas del profesor
$stmt = $pdo->prepare("SELECT f.id, f.nombre FROM fichas f 
    INNER JOIN profesor_ficha pf ON pf.ficha_id = f.id 
    WHERE pf.profesor_id = ?");
$stmt->execute([$profesor_id]);
$fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Materias del profesor
$materiasStmt = $pdo->prepare("
    SELECT m.id, m.nombre 
    FROM materias m
    INNER JOIN materia_profesor mp ON mp.materia_id = m.id
    WHERE mp.profesor_id = ?
");
$materiasStmt->execute([$profesor_id]);
$materias = $materiasStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>

<link rel="stylesheet" href="/css/Profesor/crear_tarea.css">
<div class="crear-tarea">

    <h2>Crear Tarea</h2>

    <form method="POST" action="/?page=guardar_tarea">
        <label>Título:</label><br>
        <input type="text" name="titulo" required><br><br>

        <label>Descripción:</label><br>
        <textarea name="descripcion"></textarea><br><br>

        <label>Materia:</label><br>
        <select name="materia_id" required>
            <?php foreach ($materias as $m): ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Ficha:</label><br>
        <select name="ficha_id" required>
            <?php foreach ($fichas as $f): ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nombre']) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Fecha de entrega:</label><br>
        <input type="date" name="fecha_entrega"><br><br>

        <button type="submit">Guardar tarea</button>
    </form>
</div>
