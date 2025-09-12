<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../models/Estudiante.php';
require_once __DIR__ . '/../../models/Colegio.php';
require_once __DIR__ . '/../../models/Profesor.php';
require_once __DIR__ . '/../../models/Ficha.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

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
        return strcmp(
            ($a['nombres'] ?? '') . ' ' . ($a['apellidos'] ?? ''),
            ($b['nombres'] ?? '') . ' ' . ($b['apellidos'] ?? '')
        );
    }
    return $cmp;
});

// =======================
// 📌 Crear Excel
// =======================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// 🎨 Colores SENA
$senaGreen = '39B54A';
$senaOrange = 'FF8C00';
$headerBg = 'E8F5E8';
$borderColor = '000000';

// 🔹 Logo SENA (si existe el archivo)
$logoPath = __DIR__ . '/../../public/icons/sena-logo.png';
if (file_exists($logoPath)) {
    $drawing = new Drawing();
    $drawing->setName('Logo SENA');
    $drawing->setDescription('Logo SENA');
    $drawing->setPath($logoPath);
    $drawing->setHeight(80);
    $drawing->setCoordinates('A1');
    $drawing->setWorksheet($sheet);
}

// 🔹 Encabezado SENA
$sheet->setCellValue('A1', 'SENA');
$sheet->mergeCells('A1:G3');
$sheet->getStyle('A1')->getFont()->setSize(24)->setBold(true);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF');
$sheet->getStyle('A1')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);

// 🔹 Información del colegio
$sheet->setCellValue('A4', 'SERVICIO NACIONAL DE APRENDIZAJE - SENA');
$sheet->mergeCells('A4:G4');
$sheet->getStyle('A4')->getFont()->setSize(12)->setBold(true);
$sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A5', 'CONTROL DE ASISTENCIA');
$sheet->mergeCells('A5:G5');
$sheet->getStyle('A5')->getFont()->setSize(14)->setBold(true);
$sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// 🔹 Información de la ficha
$sheet->setCellValue('A7', 'INSTITUCIÓN:');
$sheet->setCellValue('B7', $colegio['nombre']);
$sheet->setCellValue('E7', 'FICHA:');
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
$sheet->setCellValue('F7', $fichaInfo);

$sheet->setCellValue('A8', 'INSTRUCTOR:');
$sheet->setCellValue('B8', $profesor);
$sheet->setCellValue('E8', 'FECHA:');
$sheet->setCellValue('F8', date('Y-m-d'));

// Estilos para información
$sheet->getStyle('A7:A8')->getFont()->setBold(true);
$sheet->getStyle('E7:E8')->getFont()->setBold(true);

// 🔹 Encabezados de tabla de asistencia
$headers = ['#', 'APELLIDOS Y NOMBRES', 'FICHA', 'DOCUMENTO', 'Lunes', 'Martes', 'JORNADA', 'ESTADO'];
$sheet->fromArray($headers, NULL, 'A10');

// Estilos encabezados
$sheet->getStyle('A10:H10')->applyFromArray([
    'font' => ['bold' => true, 'size' => 11],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => $headerBg]
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $borderColor]]
    ]
]);

// Ajustar altura de fila de encabezados
$sheet->getRowDimension(10)->setRowHeight(25);

// 🔹 Llenar datos de estudiantes
$row = 11;
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
        // Si ya viene el nombre/número de ficha
        $numeroFicha = $e['ficha'];
    }
    
    $sheet->setCellValue("A$row", $contador);
    $sheet->setCellValue("B$row", $nombreCompleto);
    $sheet->setCellValue("C$row", $numeroFicha);
    $sheet->setCellValue("D$row", $documento);
    
    // Celdas para marcar asistencia (Lunes y Martes en el medio)
    $sheet->setCellValue("E$row", 'No hubo clase'); // Lunes
    $sheet->setCellValue("F$row", 'No hubo clase'); // Martes
    
    // Jornada y Estado al final
    $sheet->setCellValue("G$row", $e['jornada'] ?? '');
    $sheet->setCellValue("H$row", $e['estado'] ?? 'Activo');

    // Estilos para las filas de datos
    $sheet->getStyle("A$row:H$row")->applyFromArray([
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $borderColor]]
        ],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ]);
    
    // Centrar el número, ficha y estado
    $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("C$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("H$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Ajustar altura de fila
    $sheet->getRowDimension($row)->setRowHeight(20);

    $row++;
    $contador++;
}

// No agregar filas vacías - tabla dinámica según estudiantes

// Ajustar ancho de columnas
$sheet->getColumnDimension('A')->setWidth(5);   // #
$sheet->getColumnDimension('B')->setWidth(35);  // Apellidos y Nombres
$sheet->getColumnDimension('C')->setWidth(10);  // Ficha
$sheet->getColumnDimension('D')->setWidth(20);  // Documento
$sheet->getColumnDimension('E')->setWidth(12);  // Lunes
$sheet->getColumnDimension('F')->setWidth(12);  // Martes
$sheet->getColumnDimension('G')->setWidth(12);  // Jornada
$sheet->getColumnDimension('H')->setWidth(10);  // Estado

// =======================
// 📌 Descargar archivo
// =======================
if (ob_get_length()) ob_end_clean();

$safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $colegio['nombre']);
$filename = "Asistencia_{$safeName}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
