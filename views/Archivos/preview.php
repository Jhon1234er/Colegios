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

foreach ($rows as $e) {
    echo "<tr>
        <td>{$e['nombres']} {$e['apellidos']}</td>
        <td>{$e['tipo_documento']} - {$e['numero_documento']}</td>
        <td>{$e['ficha']}</td>
        <td>{$e['jornada']}</td>
        <td>".($e['estado'] ?? 'Activo')."</td>
    </tr>";
}
