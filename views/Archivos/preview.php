<?php
require_once __DIR__ . '/../../models/Estudiante.php';

$colegioId = $_POST['colegio_id'] ?? null;
$fichas    = $_POST['fichas'] ?? [];

$estudianteModel = new Estudiante();
$rows = [];

if ($fichas && is_array($fichas)) {
    foreach($fichas as $fichaId) {
        $rows = array_merge($rows, $estudianteModel->obtenerTodos($fichaId));
    }
} else {
    $rows = $estudianteModel->obtenerPorColegio($colegioId);
}

if (!$rows || count($rows) === 0): ?>
    <tr>
        <td colspan="5" class="text-center text-muted">⚠️ No hay estudiantes</td>
    </tr>
<?php else: ?>
    <?php foreach ($rows as $e): 
        $estado = $e['estado'] ?? 'Activo';
        $estadoClass = $estado === 'Deserto' ? 'estado-deserto' : 'estado-activo';
    ?>
    <tr>
        <td contenteditable="true" class="editable"><?= htmlspecialchars(($e['nombres'] ?? '') . ' ' . ($e['apellidos'] ?? '')) ?></td>
        <td contenteditable="true" class="editable"><?= htmlspecialchars(($e['tipo_documento'] ?? '') . ' - ' . ($e['numero_documento'] ?? '')) ?></td>
        <td contenteditable="true" class="editable"><?= htmlspecialchars($e['ficha'] ?? 'N/A') ?></td>
        <td contenteditable="true" class="editable"><?= htmlspecialchars($e['jornada'] ?? '') ?></td>
        <td contenteditable="true" class="editable <?= $estadoClass ?>"><?= htmlspecialchars($estado) ?></td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
