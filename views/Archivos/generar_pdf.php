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

// 🔹 Generar HTML con estilos modernos
$html = "
<style>
    body {
        font-family: 'Helvetica', 'Arial', sans-serif;
        font-size: 12px;
        color: #2c3e50;
    }
    h2 {
        text-align: center;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    .meta {
        margin-bottom: 15px;
        font-size: 12px;
    }
    .meta p {
        margin: 2px 0;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    thead th {
        background: #34495e;
        color: #fff;
        padding: 8px;
        text-align: center;
        font-size: 12px;
    }
    tbody td {
        padding: 6px 8px;
        border-bottom: 1px solid #ddd;
    }
    tbody tr:nth-child(even) {
        background: #f9f9f9;
    }
    tbody tr:hover {
        background: #f1f7fd;
    }
    td, th {
        font-size: 11px;
    }
    .estado {
        font-weight: bold;
        text-align: center;
    }
    .estado.Activo   { color: #27ae60; }  /* Verde */
    .estado.Deserto  { color: #c0392b; }  /* Rojo */
</style>

<h2>Asistencia - ".htmlspecialchars($colegio['nombre'])."</h2>
<div class='meta'>
    <p><strong>Facilitador:</strong> ".htmlspecialchars($profesor)."</p>
    <p><strong>Fecha:</strong> " . date('Y-m-d') . "</p>
</div>

<table>
<thead>
<tr>
<th>Ficha</th>
<th>Nombres y Apellidos</th>
<th>Tipo Documento</th>
<th>Número Documento</th>
<th>Jornada</th>
<th>Estado</th>
</tr>
</thead>
<tbody>";
foreach ($estudiantes as $e) {
    $nombreCompleto = $e['nombre_completo'] ?? trim(($e['nombres'] ?? '').' '.($e['apellidos'] ?? ''));
    $estado = $e['estado'] ?? 'Activo'; // Mantener tal cual viene de la BD (Activo/Deserto)
    $html .= "<tr>
        <td>".htmlspecialchars($e['ficha'] ?? '')."</td>
        <td>".htmlspecialchars($nombreCompleto)."</td>
        <td>".htmlspecialchars($e['tipo_documento'] ?? '')."</td>
        <td>".htmlspecialchars($e['numero_documento'] ?? '')."</td>
        <td>".htmlspecialchars($e['jornada'] ?? '')."</td>
        <td class='estado {$estado}'>".htmlspecialchars($estado)."</td>
    </tr>";
}
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
