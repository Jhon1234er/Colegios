<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Estudiante.php';

$ficha_id = $_GET['ficha_id'] ?? null;
if (!$ficha_id) {
    echo "<p>No se ha especificado una ficha para mostrar.</p>";
    return;
}

$pdo = Database::conectar();

// Obtener nombre de la ficha
$ficha_nombre = 'Desconocida';
$stmtFicha = $pdo->prepare("SELECT nombre FROM fichas WHERE id = ?");
$stmtFicha->execute([$ficha_id]);
$ficha = $stmtFicha->fetch(PDO::FETCH_ASSOC);
if ($ficha) {
    $ficha_nombre = $ficha['nombre'];
}

// Obtener estudiantes
$estudianteModel = new Estudiante();
$estudiantes = $estudianteModel->obtenerTodos($ficha_id);

// Obtener tareas
$profesor_id = $_SESSION['usuario']['profesor_id'] ?? null;
$tareasStmt = $pdo->prepare("SELECT * FROM tareas WHERE ficha_id = ? AND profesor_id = ?");
$tareasStmt->execute([$ficha_id, $profesor_id]);
$tareas = $tareasStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require __DIR__ . '/../Componentes/encabezado.php'; ?>

<style>
body{
  background-color: #f0f2f5;

}
.panel-contenido {
    max-width: 90%;
    margin: 15% auto 40px auto; /* ⬇️ 80px arriba, centrado */
    padding: 20px;
    background-color: #ffffff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    font-family: Arial, sans-serif;
}

.panel-contenido h2 {
    color: #2c3e50;
    text-align: center;
    margin-bottom: 25px;
}

.panel-contenido table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    font-size: 14px;
}

.panel-contenido th,
.panel-contenido td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: center;
}

.panel-contenido th {
    background-color: #6da5de;
    color: #333;
}

.panel-contenido tr:nth-child(even) {
    background-color: #fafafa;
}

.panel-contenido input[type="number"] {
    width: 60px;
    padding: 5px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 13px;
}

.panel-contenido .form-footer {
    text-align: center;
    margin-top: 20px;
}

.panel-contenido button[type="submit"] {
    background-color: #ff6b81; /* rosa suave */
    color: white;
    padding: 10px 30px;
    border: none;
    font-size: 15px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.panel-contenido button[type="submit"]:hover {
  background-color: #ff4757; /* tono más fuerte al pasar el mouse */
  box-shadow: 0 4px 12px rgba(255, 71, 87, 0.4);
}

.panel-contenido .no-tareas {
    color: #888;
    text-align: center;
    margin-top: 30px;
}
</style>

<div class="panel-contenido">
    <h2>Notas de la Ficha <strong><?= htmlspecialchars($ficha_nombre) ?></strong></h2>

    <?php if (empty($tareas)): ?>
        <p class="no-tareas">No hay tareas registradas aún.</p>
    <?php else: ?>
        <form action="/?page=guardar_notas" method="POST">
            <input type="hidden" name="ficha_id" value="<?= htmlspecialchars($ficha_id) ?>">

            <table>
                <thead>
                    <tr>
                        <th>Aprendiz</th>
                        <?php foreach ($tareas as $t): ?>
                            <th><?= htmlspecialchars($t['titulo']) ?></th>
                        <?php endforeach; ?>
                        <th>Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estudiantes as $est): ?>
                        <tr>
                            <td><?= htmlspecialchars($est['nombres'] . ' ' . $est['apellidos']) ?></td>
                            <?php
                            $total = 0;
                            $count = 0;
                            foreach ($tareas as $t):
                                $stmt = $pdo->prepare("SELECT nota FROM entregas WHERE estudiante_id = ? AND tarea_id = ?");
                                $stmt->execute([$est['id'], $t['id']]);
                                $nota = $stmt->fetchColumn();

                                $notaValue = $nota !== false ? $nota : '';
                                echo '<td>';
                                echo '<input type="number" step="0.1" min="0" max="5" name="notas[' . $est['id'] . '][' . $t['id'] . ']" value="' . $notaValue . '" />';
                                echo '</td>';

                                if ($nota !== false) {
                                    $total += $nota;
                                    $count++;
                                }
                            endforeach;

                            $promedio = $count > 0 ? round($total / $count, 2) : '-';
                            ?>
                            <td><strong><?= $promedio ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="form-footer">
                <button type="submit">Guardar notas</button>
            </div>
        </form>
    <?php endif; ?>
</div>
