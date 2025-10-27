<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../models/Estudiante.php';
require_once __DIR__ . '/../../models/Colegio.php';
require_once __DIR__ . '/../../models/Profesor.php';
require_once __DIR__ . '/../../models/Ficha.php';
require_once __DIR__ . '/../../models/Asistencia.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\IOFactory;

$colegioId = $_GET['colegio_id'] ?? null;

$fichasRaw = $_GET['fichas'] ?? '';
$fichas = array_filter(array_map('intval', explode(',', $fichasRaw)));

if (!$colegioId) {
    die("❌ No se especificó colegio.");
}

$fichaModel      = new Ficha();
$colegioModel    = new Colegio();
$estudianteModel = new Estudiante();
$profesorModel   = new Profesor();

$colegio   = $colegioModel->obtenerPorId($colegioId);
$profesor  = $profesorModel->obtenerPorColegio($colegioId)[0]['nombre_completo'] ?? "No asignado";

// =======================
// 📌 Obtener estudiantes
// =======================
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

// Ordenar estudiantes
usort($estudiantes, function($a, $b) {
    $cmp = strcmp($a['ficha'] ?? '', $b['ficha'] ?? '');
    if ($cmp === 0) {
        // Ordenar por nombres primero, luego apellidos
        $na = ($a['nombres'] ?? '') . ' ' . ($a['apellidos'] ?? '');
        $nb = ($b['nombres'] ?? '') . ' ' . ($b['apellidos'] ?? '');
        return strcmp($na, $nb);
    }
    return $cmp;
});

// =======================
// 📌 Crear libro y hoja desde cero (sin plantilla)
// =======================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Reporte');

// Paleta de colores (ajustables)
$black = '000000';
$darkBlue = '0B3A53'; // encabezados
$zebra = 'F2F2F2';
$headerFontColor = 'FFFFFF';

// Pintar en blanco todo el lienzo alrededor para que lo no usado quede blanco
$canvas = 'A1:JP300';
$sheet->getStyle($canvas)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF');
$sheet->getStyle($canvas)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);

// Configurar anchos de columnas D..N en píxeles (conversión aproximada px -> unidades Excel)
// Excel usa ancho en caracteres (~7px por unidad) + margen. Aproximación estable usada en proyectos reales.
$px2w = function($px) { return max(0.5, ($px - 5) / 7); };
$sheet->getColumnDimension('D')->setWidth($px2w(65));
$sheet->getColumnDimension('E')->setWidth($px2w(125));
$sheet->getColumnDimension('F')->setWidth($px2w(185));
$sheet->getColumnDimension('G')->setWidth($px2w(95));
foreach (['H','I','J','K','L'] as $col) { $sheet->getColumnDimension($col)->setWidth($px2w(125)); }
$sheet->getColumnDimension('M')->setWidth($px2w(95));
$sheet->getColumnDimension('N')->setWidth($px2w(95));

// Marco D5:N11
$sheet->getStyle('D5:N11')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK)->getColor()->setARGB($black);

// Títulos centrados en G7..G9K
$sheet->mergeCells('G7:K7');
$sheet->mergeCells('G8:K8');
$sheet->mergeCells('G9:K9');
$sheet->setCellValue('G7', 'SENA');
$sheet->setCellValue('G8', 'SERVICIO NACIONAL DE APRENDIZAJE - SENA');
$sheet->setCellValue('G9', 'CONTROL DE ASISTENCIA - SISTEM SCHOLL');
$sheet->getStyle('G7')->getFont()->setName('Calibri')->setSize(36)->setBold(true);
$sheet->getStyle('G8')->getFont()->setName('Calibri')->setSize(12)->setBold(false);
$sheet->getStyle('G9')->getFont()->setName('Calibri')->setSize(12)->setBold(false);
$sheet->getStyle('G7:K9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
// Alturas de filas del encabezado
$sheet->getRowDimension(7)->setRowHeight(40);
$sheet->getRowDimension(8)->setRowHeight(30);
$sheet->getRowDimension(9)->setRowHeight(30);

// Info labels en fila 13 con cajas y bordes gruesos
$infoRowStart = 13;
// Fila 13: D vacío, E con etiqueta, F:G valor, H separación, I:J etiqueta fecha, K:M valor
$sheet->mergeCells('F'.$infoRowStart.':G'.$infoRowStart);
$sheet->mergeCells('I'.$infoRowStart.':J'.$infoRowStart);
$sheet->mergeCells('K'.$infoRowStart.':M'.$infoRowStart);

$sheet->setCellValue('D'.$infoRowStart, '');
$sheet->setCellValue('E'.$infoRowStart, 'INSTITUCIÓN:');
$sheet->setCellValue('F'.$infoRowStart, '');
$sheet->setCellValue('I'.$infoRowStart, 'FECHA DE REPORTE:');
$sheet->setCellValue('K'.$infoRowStart, '');

// Estilos de fila 13
$sheet->getRowDimension($infoRowStart)->setRowHeight(48);
$sheet->getStyle('D'.$infoRowStart.':M'.$infoRowStart)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle('E'.$infoRowStart)->getFont()->setBold(true);
$sheet->getStyle('I'.$infoRowStart)->getFont()->setBold(true);
// Bordes gruesos por caja: E, F:G, I:J, K:M
$sheet->getStyle('E'.$infoRowStart)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK)->getColor()->setARGB($black);
$sheet->getStyle('F'.$infoRowStart.':G'.$infoRowStart)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK)->getColor()->setARGB($black);
$sheet->getStyle('I'.$infoRowStart.':J'.$infoRowStart)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK)->getColor()->setARGB($black);
$sheet->getStyle('K'.$infoRowStart.':M'.$infoRowStart)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK)->getColor()->setARGB($black);

// Quitar borde interno entre las cajas (sin línea entre textos)
$sheet->getStyle('E'.$infoRowStart)->getBorders()->getRight()->setBorderStyle(Border::BORDER_NONE);
$sheet->getStyle('F'.$infoRowStart.':G'.$infoRowStart)->getBorders()->getLeft()->setBorderStyle(Border::BORDER_NONE);
$sheet->getStyle('I'.$infoRowStart.':J'.$infoRowStart)->getBorders()->getRight()->setBorderStyle(Border::BORDER_NONE);
$sheet->getStyle('K'.$infoRowStart.':M'.$infoRowStart)->getBorders()->getLeft()->setBorderStyle(Border::BORDER_NONE);

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
    $desde = $diasSemana ? $diasSemana[0] : ($weekStart ?: date('Y-m-d'));
    $hasta = $diasSemana ? end($diasSemana) : ($weekEnd ?: $desde);
} elseif ($weekStart) {
    $s = strtotime($weekStart);
    $e = $weekEnd ? strtotime($weekEnd) : $s;
    if ($e < $s) { $e = $s; }
    // incluir solo L-V dentro del rango
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
    // semana actual L-V
    $ts = time();
    $dow = (int)date('N', $ts);
    $monday = strtotime('-' . ($dow - 1) . ' days', $ts);
    if ($dow > 5) { $monday = strtotime('monday last week', $ts); }
    for ($i=0; $i<5; $i++) { $diasSemana[] = date('Y-m-d', strtotime("+$i day", $monday)); }
    $desde = $diasSemana[0];
    $hasta = $diasSemana[4];
}

// Utilidad para formatear "20-Lunes-2025"
$diasES = ['1'=>'Lunes','2'=>'Martes','3'=>'Miercoles','4'=>'Jueves','5'=>'Viernes','6'=>'Sabado','7'=>'Domingo'];
$fmtDia = function(string $ymd) use ($diasES) {
    $ts = strtotime($ymd);
    $d = date('j', $ts);
    $dow = $diasES[date('N', $ts)] ?? date('D', $ts);
    $y = date('Y', $ts);
    return "$d-$dow-$y";
};

// 🔹 Escribir datos en las celdas de información
$sheet->setCellValue('F'.$infoRowStart, ($colegio['nombre'] ?? ''));
// Obtener números de ficha en lugar de IDs
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
$sheet->setCellValue('K'.$infoRowStart, $fmtDia($diasSemana[0]) . ' al ' . $fmtDia(end($diasSemana)));

// Encabezado de tabla (D16:colDin)
$tableHeaderRow = 16;
$diasES = ['Mon'=>'LUNES','Tue'=>'MARTES','Wed'=>'MIERCOLES','Thu'=>'JUEVES','Fri'=>'VIERNES'];
$dayHeaders = [];
foreach ($diasSemana as $d) { $dayHeaders[] = $diasES[date('D', strtotime($d))] ?? strtoupper(date('D', strtotime($d))); }
$headers = array_merge(['NUMERO','DOCUMENTO','NOMBRE COMPLETO','#FICHA'], $dayHeaders, ['JORNADA','ESTADO']);
$sheet->fromArray($headers, null, 'D'.$tableHeaderRow);
$lastCol = chr(ord('D') + count($headers) - 1);
$sheet->getStyle('D'.$tableHeaderRow.':'.$lastCol.$tableHeaderRow)->applyFromArray([
  'font'=>['bold'=>true,'color'=>['argb'=>$headerFontColor]],
  'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>$darkBlue]],
  'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>$black]]]
]);
$sheet->getRowDimension($tableHeaderRow)->setRowHeight(30);

// 🔹 Inicio de tabla de datos desde fila 17
$tableStartRow = 17;

// 🔹 Llenar datos: una fila por FICHA si vienen fichas seleccionadas; si no, fallback por estudiante
$row = $tableStartRow;
$contador = 1;
$asisModel = new Asistencia();
$cacheAsistencias = [];
if (!empty($fichas)) {
    foreach ($fichas as $fichaIdSel) {
        $ficha = $fichaModel->obtenerPorId($fichaIdSel);
        $numeroFicha = $ficha['numero'] ?? ($ficha['nombre'] ?? $fichaIdSel);
        // Obtener estudiantes de la ficha y tomar el primero por apellidos,nombres
        $estudiantesFicha = $estudianteModel->obtenerTodos($fichaIdSel);
        usort($estudiantesFicha, function($a,$b){
            return strcmp(($a['apellidos']??'').($a['nombres']??''), ($b['apellidos']??'').($b['nombres']??''));
        });
        $rep = $estudiantesFicha[0] ?? [];
        $documento = '';
        if (!empty($rep)) {
            $documento = ($rep['tipo_documento'] ?? 'CC') . ' ' . ($rep['numero_documento'] ?? '');
        }
        $nombreCompletoRep = trim((string)(($rep['nombres'] ?? '') . ' ' . ($rep['apellidos'] ?? '')));

        // Número correlativo y columnas base
        $sheet->setCellValue("D$row", $contador);
        $sheet->setCellValue("E$row", $documento);
        $sheet->setCellValue("F$row", $nombreCompletoRep); // nombre del representante de la ficha
        $sheet->setCellValue("G$row", $numeroFicha);

        // Estados por día H..L tomando el representante; si no hay registro -> "No hubo clase"
        $cacheKey = $fichaIdSel . '|' . $diasSemana[0] . '|' . end($diasSemana);
        if (!isset($cacheAsistencias[$cacheKey])) {
            $regs = $asisModel->obtenerAsistenciasPorFichaRango($fichaIdSel, $diasSemana[0], end($diasSemana));
            $idx = [];
            foreach ($regs as $r) { $idx[$r['estudiante_id'].'|'.$r['fecha']] = $r['estado']; }
            $cacheAsistencias[$cacheKey] = $idx;
        }
        $idx = $cacheAsistencias[$cacheKey];
        $repId = $rep['id'] ?? 0;
        $dayCount = count($diasSemana);
        for ($i=0;$i<$dayCount;$i++) {
            $fechaD = $diasSemana[$i] ?? null;
            $estado = $fechaD ? ($idx[$repId.'|'.$fechaD] ?? 'No hubo clase') : 'No hubo clase';
            $col = chr(ord('H')+$i);
            $sheet->setCellValue($col.$row, $estado);
        }

        // Jornada de la ficha (o del representante) en Title Case
        $jornadaRaw = str_replace(['"', "'", '“', '”'], '', (string)($rep['jornada'] ?? ''));
        $jornada = mb_convert_case(mb_strtolower($jornadaRaw, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $jCol = chr(ord('H') + $dayCount);
        $eCol = chr(ord('H') + $dayCount + 1);
        $sheet->setCellValue($jCol.$row, $jornada);

        // Estado fijo para la fila por ficha
        $sheet->setCellValue($eCol.$row, 'ACTIVO');
        $sheet->getStyle($eCol.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('39A900');
        $sheet->getStyle($eCol.$row)->getFont()->getColor()->setARGB('FFFFFF');

        // Estilos de fila
        $lastColRow = chr(ord('H') + $dayCount + 1);
        $beforeLast = chr(ord('H') + $dayCount - 1);
        $sheet->getStyle("D$row:".$lastColRow.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("D$row:".$lastColRow.$row)->getFont()->setName('TH SarabunPSK')->setSize(16);
        if (($contador % 2) === 0) { $sheet->getStyle("D$row:".$beforeLast.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($zebra); }
        $sheet->getStyle("D$row:".$lastColRow.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);
        $sheet->getRowDimension($row)->setRowHeight(30);

        $row++; $contador++;
    }
} else {
    // Fallback: por estudiante (comportamiento previo)
    foreach ($estudiantes as $e) {
        $nombreCompleto = trim(($e['apellidos'] ?? '') . ' ' . ($e['nombres'] ?? ''));
        $documento = ($e['tipo_documento'] ?? 'CC') . ' ' . ($e['numero_documento'] ?? '');
        $numeroFicha = '';
        if (isset($e['ficha_id'])) { $ficha = $fichaModel->obtenerPorId($e['ficha_id']); $numeroFicha = $ficha['numero'] ?? ''; }
        elseif (isset($e['ficha'])) { $numeroFicha = $e['ficha']; }

        $sheet->setCellValue("D$row", $contador);
        $sheet->setCellValue("E$row", $documento);
        $sheet->setCellValue("F$row", $nombreCompleto);
        $sheet->setCellValue("G$row", $numeroFicha);

        $fichaIdRow = $e['ficha_id'] ?? null;
        if ($fichaIdRow) {
            $cacheKey = $fichaIdRow . '|' . $diasSemana[0] . '|' . end($diasSemana);
            if (!isset($cacheAsistencias[$cacheKey])) {
                $regs = $asisModel->obtenerAsistenciasPorFichaRango($fichaIdRow, $diasSemana[0], end($diasSemana));
                $idx = [];
                foreach ($regs as $r) { $idx[$r['estudiante_id'].'|'.$r['fecha']] = $r['estado']; }
                $cacheAsistencias[$cacheKey] = $idx;
            }
            $idx = $cacheAsistencias[$cacheKey];
            $dayCount = count($diasSemana);
            for ($i=0;$i<$dayCount;$i++) {
                $fechaD = $diasSemana[$i] ?? null;
                $estado = $fechaD ? ($idx[($e['id'] ?? 0).'|'.$fechaD] ?? 'No hubo clase') : 'No hubo clase';
                $col = chr(ord('H')+$i);
                $sheet->setCellValue($col.$row, $estado);
            }
        } else {
            $dayCount = count($diasSemana);
            for ($i=0;$i<$dayCount;$i++) { $sheet->setCellValue(chr(ord('H')+$i).$row, 'No hubo clase'); }
        }

        $jornadaRaw = str_replace(['"', "'", '“', '”'], '', (string)($e['jornada'] ?? ''));
        $jornada = mb_convert_case(mb_strtolower($jornadaRaw, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $jCol = chr(ord('H') + count($diasSemana));
        $eCol = chr(ord('H') + count($diasSemana) + 1);
        $sheet->setCellValue($jCol.$row, $jornada);

        $estadoAlumno = strtoupper(str_replace(['"', "'", '“', '”'], '', (string)($e['estado'] ?? 'ACTIVO')));
        $sheet->setCellValue($eCol.$row, $estadoAlumno);
        $estadoColor = ($estadoAlumno === 'ACTIVO') ? '39A900' : (($estadoAlumno === 'DESERTO' || $estadoAlumno === 'DESERCIÓN' || $estadoAlumno === 'DESERTADO') ? '00304D' : null);
        if ($estadoColor) { $sheet->getStyle($eCol.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($estadoColor); $sheet->getStyle($eCol.$row)->getFont()->getColor()->setARGB('FFFFFF'); }

        $lastColRow = chr(ord('H') + count($diasSemana) + 1);
        $beforeLast = chr(ord('H') + count($diasSemana) - 1);
        $sheet->getStyle("D$row:".$lastColRow.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("D$row:".$lastColRow.$row)->getFont()->setName('TH SarabunPSK')->setSize(16);
        if (($contador % 2) === 0 && count($diasSemana) > 0) { $sheet->getStyle("D$row:".$beforeLast.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($zebra); }
        $sheet->getStyle("D$row:".$lastColRow.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($black);
        $sheet->getRowDimension($row)->setRowHeight(30);

        $row++; $contador++;
    }
}

// No agregar filas vacías - tabla dinámica según estudiantes

// Limpiar todo lo que sigue (no hay plantilla)

// Anchos, bordes y estilos se toman de la plantilla

// Rellenar en blanco todo lo NO usado bajo la tabla para que no aparezcan franjas
$lastDataRow = max($tableStartRow, $row - 1);
$clearStart = $lastDataRow + 1;
$clearEnd = $clearStart + 300; // margen suficiente hacia abajo
$rangeClear = "D{$clearStart}:N{$clearEnd}";
$sheet->getStyle($rangeClear)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF');
$sheet->getStyle($rangeClear)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);

// También limpiar a la derecha de la tabla (columnas O..Z) desde el inicio de la tabla hasta más abajo
$rightRange = "O{$tableStartRow}:JP{$clearEnd}";
$sheet->getStyle($rightRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF');
$sheet->getStyle($rightRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);

// =======================
// 📌 Descargar archivo
// =======================
if (ob_get_length()) ob_end_clean();

$safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $colegio['nombre']);
$filename = "Reporte_Semanal_{$safeName}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
