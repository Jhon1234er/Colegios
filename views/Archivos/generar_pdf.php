<?php
ob_start();
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../models/Aprendiz.php';
require_once __DIR__ . '/../../models/Colegio.php';
require_once __DIR__ . '/../../models/Facilitador.php';
require_once __DIR__ . '/../../models/Ficha.php';
require_once __DIR__ . '/../../models/Asistencia.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$colegioId = $_GET['colegio_id'] ?? null;
$fichas    = isset($_GET['fichas']) ? explode(',', $_GET['fichas']) : [];

if (!$colegioId) die("❌ No se especificó colegio.");

$colegioModel    = new Colegio();
$estudianteModel = new Aprendiz();
$profesorModel   = new Facilitador();
$fichaModel      = new Ficha();

$colegio   = $colegioModel->obtenerPorId($colegioId);
// Fallback para nombre del colegio por si cambia el nombre del campo
$colegioNombre = trim((string)($colegio['nombre'] ?? $colegio['colegio'] ?? $colegio['institucion'] ?? $colegio['razon_social'] ?? ''));
if ($colegioNombre === '') { $colegioNombre = 'No especificado'; }
$profList  = $profesorModel->obtenerPorColegio($colegioId);
$profesor  = isset($profList[0]) ? trim(($profList[0]['apellidos'] ?? '').' '.($profList[0]['nombres'] ?? '')) : "No asignado";

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
    // Agregar aprendices de todas las fichas del colegio
    $fichasColegio = $fichaModel->obtenerPorColegio($colegioId);
    foreach ($fichasColegio as $f) {
        $tmp = $estudianteModel->obtenerTodos($f['id']);
        foreach ($tmp as &$row) {
            $row['ficha']  = $f['nombre'] ?? ("Ficha " . $f['id']);
            $row['estado'] = $row['estado'] ?? 'Activo';
        }
        $estudiantes = array_merge($estudiantes, $tmp);
    }
}

// 🔹 Determinar rango de fechas (Lunes–Viernes) con soporte a 'days' o week_start/week_end
$daysParam = $_GET['days'] ?? [];
$weekStart = $_GET['week_start'] ?? ($_GET['desde'] ?? '');
$weekEnd   = $_GET['week_end']   ?? ($_GET['hasta'] ?? '');
$diasSemana = [];
if (!empty($daysParam)) {
    if (is_string($daysParam)) { $daysParam = array_filter(array_map('trim', explode(',', $daysParam))); }
    if (is_array($daysParam)) {
        foreach ($daysParam as $d) {
            $ts = strtotime($d);
            if ($ts) {
                $dow = (int)date('N', $ts);
                if ($dow >= 1 && $dow <= 5) { $diasSemana[] = date('Y-m-d', $ts); }
            }
        }
        $diasSemana = array_values(array_unique($diasSemana));
        sort($diasSemana);
    }
    if ($diasSemana) { $desde = $diasSemana[0]; $hasta = end($diasSemana); }
    else { $desde = $weekStart ?: date('Y-m-d'); $hasta = $weekEnd ?: $desde; }
} elseif ($weekStart) {
    $s = strtotime($weekStart);
    $e = $weekEnd ? strtotime($weekEnd) : $s;
    if ($e < $s) { $e = $s; }
    $cursor = $s; $safe = 0;
    while ($cursor <= $e && $safe < 14) {
        $dow = (int)date('N', $cursor);
        if ($dow >= 1 && $dow <= 5) { $diasSemana[] = date('Y-m-d', $cursor); }
        $cursor = strtotime('+1 day', $cursor);
        $safe++;
    }
    $desde = $diasSemana ? $diasSemana[0] : date('Y-m-d', $s);
    $hasta = $diasSemana ? end($diasSemana) : date('Y-m-d', $e);
} else {
    $ts = time();
    $dow = (int)date('N', $ts);
    $monday = strtotime('-' . ($dow - 1) . ' days', $ts);
    if ($dow > 5) { $monday = strtotime('monday last week', $ts); }
    for ($i=0; $i<5; $i++) { $diasSemana[] = date('Y-m-d', strtotime("+$i day", $monday)); }
    $desde = $diasSemana[0];
    $hasta = $diasSemana[4];
}
// Utilidad formateo "20-Lunes-2025"
$diasES = ['1'=>'Lunes','2'=>'Martes','3'=>'Miercoles','4'=>'Jueves','5'=>'Viernes','6'=>'Sabado','7'=>'Domingo'];
$fmtDia = function(string $ymd) use ($diasES) {
    $ts = strtotime($ymd);
    return date('j', $ts) . '-' . ($diasES[date('N',$ts)] ?? date('D',$ts)) . '-' . date('Y', $ts);
};
// Rango de texto
$rangoTexto = $fmtDia($desde) . ' al ' . $fmtDia($hasta);

// 🔹 Generar HTML con mismo diseño del Excel
$html = "
<style>
  @page { margin: 20px 20px 20px 20px; }
  body { font-family: 'Calibri', 'Arial', sans-serif; font-size: 14px; color: #000; }
  .wrap { }
  .header-box { border: 3px solid #000; width: 80%; margin: 10px auto 12px auto; padding: 10px 8px; text-align: center; }
  .sena-title { font-size: 36px; font-weight: bold; margin: 0; }
  .sena-sub { font-size: 12px; margin: 2px 0; }
  .info-line { width: 95%; margin: 8px auto 16px auto; text-align: center; font-family: 'Calibri', 'Arial', sans-serif; font-size: 12px; font-weight: 400; }
  .no-right { border-right: none; }
  .no-left { border-left: none; }
  table { width: 90%; margin: 0 auto; border-collapse: collapse; font-family: 'DejaVu Sans', 'TH SarabunPSK', 'Arial', sans-serif; font-size: 12px; }
  thead th { background: #0B3A53; color: #fff; border: 1px solid #000; padding: 6px 4px; text-align: center; }
  tbody td { border: 1px solid #000; padding: 6px 4px; text-align: center; }
  tbody tr:nth-child(even) td:not(.estado) { background: #F2F2F2; }
  td.estado { color: #fff; font-weight: bold; }
</style>

<div class='wrap'>
  <div class='header-box'>
    <div class='sena-title'>SENA</div>
    <div class='sena-sub'>SERVICIO NACIONAL DE APRENDIZAJE - SENA</div>
    <div class='sena-sub'>CONTROL DE ASISTENCIA - SISTEM SCHOLL</div>
  </div>

  <div class='info-line'>INSTITUCIÓN: ".htmlspecialchars($colegioNombre)."  •  FECHA DE REPORTE: ".htmlspecialchars($rangoTexto)."</div>

  <table>
    <thead>
      <tr>
        <th>NUMERO</th>
        <th>DOCUMENTO</th>
        <th>NOMBRE COMPLETO</th>
        <th>#FICHA</th>
        
        ";
// Encabezados dinámicos según días seleccionados
$diasESCab = ['Mon'=>'LUNES','Tue'=>'MARTES','Wed'=>'MIERCOLES','Thu'=>'JUEVES','Fri'=>'VIERNES'];
foreach ($diasSemana as $d) { $html .= "<th>".($diasESCab[date('D', strtotime($d))] ?? strtoupper(date('D', strtotime($d))))."</th>"; }
$html .= "
        <th>JORNADA</th>
        <th>ESTADO</th>
      </tr>
    </thead>
    <tbody>";
// Datos: una fila por FICHA (igual al Excel)
$asisModel = new Asistencia();
$contador = 1;
if (!empty($fichas)) {
    foreach ($fichas as $fichaIdSel) {
        $ficha = $fichaModel->obtenerPorId($fichaIdSel);
        $numeroFicha = $ficha['numero'] ?? ($ficha['nombre'] ?? $fichaIdSel);
        $estudiantesFicha = $estudianteModel->obtenerTodos($fichaIdSel);
        usort($estudiantesFicha, function($a,$b){ return strcmp(($a['apellidos']??'').($a['nombres']??''), ($b['apellidos']??'').($b['nombres']??'')); });
        $rep = $estudiantesFicha[0] ?? [];
        $documento = !empty($rep) ? (($rep['tipo_documento'] ?? 'CC') . ' ' . ($rep['numero_documento'] ?? '')) : '';
        $nombreCompleto = trim((string)(($rep['nombres'] ?? '') . ' ' . ($rep['apellidos'] ?? '')));

        // Asistencias del representante
        $regs = $asisModel->obtenerAsistenciasPorFichaRango((int)$fichaIdSel, $desde, $hasta);
        $idx = [];
        foreach ($regs as $r) { $idx[$r['estudiante_id'].'|'.$r['fecha']] = $r['estado']; }
        $repId = $rep['id'] ?? 0;
        $dias = [];
        foreach ($diasSemana as $fechaD) { $dias[] = $fechaD ? ($idx[$repId.'|'.$fechaD] ?? 'No hubo clase') : 'No hubo clase'; }

        // Jornada Title Case
        $jr = str_replace(['"', "'", '“', '”'], '', (string)($rep['jornada'] ?? ''));
        $jornada = mb_convert_case(mb_strtolower($jr, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $estado = 'ACTIVO';
        $estadoBg = '#39A900';

        $html .= "<tr>
          <td>".$contador."</td>
          <td>".htmlspecialchars($documento)."</td>
          <td>".htmlspecialchars($nombreCompleto)."</td>
          <td>".htmlspecialchars($numeroFicha)."</td>";
        foreach ($dias as $val) { $html .= "<td>".htmlspecialchars($val)."</td>"; }
        $html .= "<td>".htmlspecialchars($jornada)."</td>
          <td class='estado' style='background: $estadoBg;'>".htmlspecialchars($estado)."</td>
        </tr>";
        $contador++;
    }
} else {
    // Fallback: por estudiante si no hay fichas
    foreach ($estudiantes as $e) {
        $documento = ($e['tipo_documento'] ?? 'CC') . ' ' . ($e['numero_documento'] ?? '');
        $ficha = '';
        if (isset($e['ficha_id'])) { $ff = $fichaModel->obtenerPorId($e['ficha_id']); $ficha = $ff['numero'] ?? ''; }
        elseif (isset($e['ficha'])) { $ficha = $e['ficha']; }
        $jornada = mb_convert_case(mb_strtolower((string)($e['jornada'] ?? ''), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $estado = strtoupper((string)($e['estado'] ?? 'ACTIVO'));
        $estadoBg = ($estado==='ACTIVO') ? '#39A900' : '#00304D';
        $html .= "<tr>
          <td>".$contador."</td>
          <td>".htmlspecialchars($documento)."</td>
          <td>".htmlspecialchars(trim(($e['nombres']??'').' '.($e['apellidos']??'')))."</td>
          <td>".htmlspecialchars($ficha)."</td>
          <td>No hubo clase</td>
          <td>No hubo clase</td>
          <td>No hubo clase</td>
          <td>No hubo clase</td>
          <td>No hubo clase</td>
          <td>".htmlspecialchars($jornada)."</td>
          <td class='estado' style='background: $estadoBg;'>".htmlspecialchars($estado)."</td>
        </tr>";
        $contador++;
    }
}

// No agregar filas vacías - tabla dinámica según estudiantes
$html .= "</tbody></table>
</div>";

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
