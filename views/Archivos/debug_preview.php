<?php
// Debug simple para ver exactamente qué datos llegan
require_once __DIR__ . '/../../models/Estudiante.php';
require_once __DIR__ . '/../../models/Ficha.php';

$colegioId = $_POST['colegio_id'] ?? null;
$fichas    = $_POST['fichas'] ?? [];

echo "<h5>DEBUG - Datos recibidos:</h5>";
echo "<p>Colegio ID: " . htmlspecialchars($colegioId) . "</p>";
echo "<p>Fichas: " . htmlspecialchars(json_encode($fichas)) . "</p>";

$estudianteModel = new Estudiante();
$fichaModel = new Ficha();
$rows = [];

if ($fichas && is_array($fichas)) {
    foreach($fichas as $fichaId) {
        $estudiantes = $estudianteModel->obtenerTodos($fichaId);
        foreach ($estudiantes as &$estudiante) {
            $ficha = $fichaModel->obtenerPorId($fichaId);
            $estudiante['numero_ficha'] = $ficha['numero'] ?? $fichaId;
        }
        $rows = array_merge($rows, $estudiantes);
    }
} else {
    $rows = $estudianteModel->obtenerPorColegio($colegioId);
    foreach ($rows as &$row) {
        if (isset($row['ficha_id'])) {
            $ficha = $fichaModel->obtenerPorId($row['ficha_id']);
            $row['numero_ficha'] = $ficha['numero'] ?? $row['ficha_id'];
        }
    }
}

echo "<h5>Datos procesados (" . count($rows) . " estudiantes):</h5>";
echo "<pre>" . htmlspecialchars(print_r($rows, true)) . "</pre>";

if (!$rows || count($rows) === 0): ?>
    <tr>
        <td colspan="7" class="text-center text-muted">⚠️ No hay estudiantes</td>
    </tr>
<?php else: ?>
    <?php foreach ($rows as $index => $e): 
        echo "<h6>Estudiante " . ($index + 1) . ":</h6>";
        echo "<pre>" . htmlspecialchars(print_r($e, true)) . "</pre>";
        
        $estado = $e['estado'] ?? 'Activo';
        $estadoClass = $estado === 'Deserto' ? 'estado-deserto' : 'estado-activo';
        
        $apellidos = isset($e['apellidos']) && !empty(trim($e['apellidos'])) ? trim($e['apellidos']) : '';
        $nombres = isset($e['nombres']) && !empty(trim($e['nombres'])) ? trim($e['nombres']) : '';
        $nombreCompleto = trim($apellidos . ' ' . $nombres);
        if (empty($nombreCompleto)) $nombreCompleto = 'Sin nombre';
        
        $tipoDoc = isset($e['tipo_documento']) && !empty($e['tipo_documento']) ? $e['tipo_documento'] : 'CC';
        $numDoc = isset($e['numero_documento']) && !empty($e['numero_documento']) ? $e['numero_documento'] : '';
        $documento = !empty($numDoc) ? $tipoDoc . ' ' . $numDoc : 'Sin documento';
        
        $numeroFicha = $e['numero_ficha'] ?? ($e['ficha'] ?? 'N/A');
        $jornada = isset($e['jornada']) && !empty(trim($e['jornada'])) ? trim($e['jornada']) : '';
        
        echo "<p><strong>Procesado:</strong></p>";
        echo "<p>1. Nombre: " . htmlspecialchars($nombreCompleto) . "</p>";
        echo "<p>2. Ficha: " . htmlspecialchars($numeroFicha) . "</p>";
        echo "<p>3. Documento: " . htmlspecialchars($documento) . "</p>";
        echo "<p>4. Lunes: (vacío)</p>";
        echo "<p>5. Martes: (vacío)</p>";
        echo "<p>6. Jornada: " . htmlspecialchars($jornada) . "</p>";
        echo "<p>7. Estado: " . htmlspecialchars($estado) . "</p>";
    ?>
    <tr>
        <td><?= htmlspecialchars($nombreCompleto) ?></td>
        <td><?= htmlspecialchars($numeroFicha) ?></td>
        <td><?= htmlspecialchars($documento) ?></td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td><?= htmlspecialchars($jornada) ?></td>
        <td class="<?= $estadoClass ?>"><?= htmlspecialchars($estado) ?></td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
