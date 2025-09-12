<?php
ob_start();
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../models/Estudiante.php';
require_once __DIR__ . '/../../models/Colegio.php';
require_once __DIR__ . '/../../models/Profesor.php';
require_once __DIR__ . '/../../models/Ficha.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$colegioId = $_GET['colegio_id'] ?? null;
$fichas    = isset($_GET['fichas']) ? explode(',', $_GET['fichas']) : [];

if (!$colegioId) die("❌ No se especificó colegio.");

$colegioModel    = new Colegio();
$estudianteModel = new Estudiante();
$profesorModel   = new Profesor();
$fichaModel      = new Ficha();

$colegio   = $colegioModel->obtenerPorId($colegioId);
$profesor  = $profesorModel->obtenerPorColegio($colegioId)[0]['nombre_completo'] ?? "No asignado";

// 🔹 Obtener estudiantes con nombre de ficha
$estudiantes = [];
if (!empty($fichas)) {
    foreach ($fichas as $fichaId) {
        $tmp = $estudianteModel->obtenerTodos($fichaId);
        $ficha = $fichaModel->obtenerPorId($fichaId);
        $nombreFicha = $ficha['nombre'] ?? ("Ficha " . $fichaId);
        foreach ($tmp as &$row) {
            $row['ficha']  = $nombreFicha;
            $row['estado'] = $row['estado'] ?? 'Activo';
        }
        $estudiantes = array_merge($estudiantes, $tmp);
    }
} else {
    $estudiantes = $estudianteModel->obtenerPorColegio($colegioId);
    foreach ($estudiantes as &$row) {
        if (isset($row['ficha_id'])) {
            $ficha = $fichaModel->obtenerPorId($row['ficha_id']);
            $row['ficha'] = $ficha['nombre'] ?? ("Ficha " . $row['ficha_id']);
        }
        $row['estado'] = $row['estado'] ?? 'Activo';
    }
}

// 🔹 Generar HTML con formato SENA
$fichaModel = new Ficha();
$fichaNumeros = [];
if (!empty($fichas)) {
    foreach ($fichas as $fichaId) {
        $ficha = $fichaModel->obtenerPorId($fichaId);
        $fichaNumeros[] = $ficha['numero'] ?? $fichaId;
    }
    $fichaInfo = implode(', ', $fichaNumeros);
} else {
    $fichaInfo = 'Todas';
}

$html = "
<style>
    body {
        font-family: 'Arial', sans-serif;
        font-size: 11px;
        color: #000;
        margin: 20px;
    }
    .header {
        text-align: center;
        border: 2px solid #000;
        padding: 15px;
        margin-bottom: 20px;
    }
    .sena-title {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 10px;
    }
    .sena-subtitle {
        font-size: 12px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .control-title {
        font-size: 14px;
        font-weight: bold;
    }
    .info-section {
        margin: 15px 0;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        margin: 5px 0;
    }
    .info-label {
        font-weight: bold;
        display: inline-block;
        width: 100px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    th, td {
        border: 1px solid #000;
        padding: 8px 4px;
        text-align: center;
        font-size: 10px;
    }
    th {
        background-color: #f0f0f0;
        font-weight: bold;
    }
    .num-col { width: 5%; }
    .name-col { width: 30%; text-align: left; }
    .ficha-col { width: 8%; }
    .doc-col { width: 15%; }
    .day-col { width: 12%; }
    .jornada-col { width: 10%; }
    .estado-col { width: 8%; }
</style>

<div class='header'>
    <div class='sena-title'>SENA</div>
    <div class='sena-subtitle'>SERVICIO NACIONAL DE APRENDIZAJE - SENA</div>
    <div class='control-title'>CONTROL DE ASISTENCIA</div>
</div>

<div class='info-section'>
    <div class='info-row'>
        <span><span class='info-label'>INSTITUCIÓN:</span> ".htmlspecialchars($colegio['nombre'])."></span>
        <span><span class='info-label'>FICHA:</span> ".$fichaInfo."</span>
    </div>
    <div class='info-row'>
        <span><span class='info-label'>INSTRUCTOR:</span> ".htmlspecialchars($profesor)."></span>
        <span><span class='info-label'>FECHA:</span> " . date('Y-m-d') . "</span>
    </div>
</div>

<table>
<thead>
<tr>
<th class='num-col'>#</th>
<th class='name-col'>APELLIDOS Y NOMBRES</th>
<th class='ficha-col'>FICHA</th>
<th class='doc-col'>DOCUMENTO</th>
<th class='day-col'>Lunes</th>
<th class='day-col'>Martes</th>
<th class='jornada-col'>JORNADA</th>
<th class='estado-col'>ESTADO</th>
</tr>
</thead>
<tbody>";
$contador = 1;
foreach ($estudiantes as $e) {
    $nombreCompleto = trim(($e['apellidos'] ?? '') . ' ' . ($e['nombres'] ?? ''));
    $documento = ($e['tipo_documento'] ?? 'CC') . ' ' . ($e['numero_documento'] ?? '');
    
    // Obtener número de ficha
    $numeroFicha = '';
    if (isset($e['ficha_id'])) {
        $ficha = $fichaModel->obtenerPorId($e['ficha_id']);
        $numeroFicha = $ficha['numero'] ?? '';
    } elseif (isset($e['ficha'])) {
        $numeroFicha = $e['ficha'];
    }
    
    $html .= "<tr>
        <td class='num-col'>".$contador."</td>
        <td class='name-col'>".htmlspecialchars($nombreCompleto)."</td>
        <td class='ficha-col'>".htmlspecialchars($numeroFicha)."</td>
        <td class='doc-col'>".htmlspecialchars($documento)."</td>
        <td class='day-col'>No hubo clase</td>
        <td class='day-col'>No hubo clase</td>
        <td class='jornada-col'>".htmlspecialchars($e['jornada'] ?? '')."</td>
        <td class='estado-col'>".htmlspecialchars($e['estado'] ?? 'Activo')."</td>
    </tr>";
    $contador++;
}

// No agregar filas vacías - tabla dinámica según estudiantes
$html .= "</tbody></table>";

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

ob_end_clean();

$filename = "Asistencia_" . preg_replace('/[^A-Za-z0-9_-]/', '_', $colegio['nombre']) . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);
exit;
