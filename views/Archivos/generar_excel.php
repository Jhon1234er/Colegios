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

// 🎨 Colores corporativos
$primaryColor   = '1F4E78'; // azul oscuro
$headerBgColor  = '2E75B6'; // azul intermedio
$activeColor    = '00B050'; // verde
$desertColor    = 'C00000'; // rojo

// 🔹 Título principal
$sheet->setCellValue('A1', "Reporte de Asistencia - " . $colegio['nombre']);
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true)->getColor()->setARGB('FFFFFF');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($primaryColor);

// 🔹 Subtítulos
$sheet->setCellValue('A2', "Facilitador: " . $profesor);
$sheet->setCellValue('C2', "Fecha: " . date('Y-m-d'));
$sheet->getStyle('A2:C2')->getFont()->setBold(true);

// 🔹 Encabezados de tabla
$headers = ['Ficha', 'Nombres y Apellidos', 'Tipo Documento', 'Número Documento', 'Fecha Asistencia', 'Jornada', 'Estado'];
$sheet->fromArray($headers, NULL, 'A4');

// Estilos encabezados
$sheet->getStyle('A4:G4')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => $headerBgColor]
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
    ]
]);

// Activar filtros
$sheet->setAutoFilter("A4:G4");

// 🔹 Llenar datos
$row = 5;
foreach ($estudiantes as $e) {
    $sheet->setCellValue("A$row", $e['ficha'] ?? 'N/A');
    $sheet->setCellValue("B$row", $e['nombre_completo'] ?? (($e['nombres'] ?? '') . ' ' . ($e['apellidos'] ?? '')));
    $sheet->setCellValue("C$row", $e['tipo_documento'] ?? '');
    $sheet->setCellValue("D$row", $e['numero_documento'] ?? '');
    $sheet->setCellValue("E$row", date('Y-m-d'));
    $sheet->setCellValue("F$row", $e['jornada'] ?? '');
    $sheet->setCellValue("G$row", $e['estado'] ?? 'Activo');

    // Estilos por estado
    $estado = strtolower($e['estado']);
    if ($estado === 'activo') {
        $sheet->getStyle("G$row")->getFont()->getColor()->setARGB($activeColor);
    } elseif ($estado === 'deserto') {
        $sheet->getStyle("G$row")->getFont()->getColor()->setARGB($desertColor);
    }

    // Bordes de la fila
    $sheet->getStyle("A$row:G$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);

    $row++;
}

// Ajustar ancho automático
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

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
