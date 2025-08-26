<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../models/Estudiante.php';
require_once __DIR__ . '/../../models/Colegio.php';
require_once __DIR__ . '/../../models/Profesor.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$colegioId = $_GET['colegio_id'] ?? null;
$fichas    = isset($_GET['fichas']) ? explode(',', $_GET['fichas']) : [];

if (!$colegioId) {
    die("❌ No se especificó colegio.");
}

$colegioModel   = new Colegio();
$estudianteModel = new Estudiante();
$profesorModel   = new Profesor();

// 📌 Datos principales
$colegio   = $colegioModel->obtenerPorId($colegioId);
$profesor  = $profesorModel->obtenerPorColegio($colegioId)[0]['nombre_completo'] ?? "No asignado";

// Obtener estudiantes
$estudiantes = [];
if (!empty($fichas)) {
    foreach ($fichas as $fichaId) {
        $estudiantes = array_merge($estudiantes, $estudianteModel->obtenerTodos($fichaId));
    }
} else {
    $estudiantes = $estudianteModel->obtenerPorColegio($colegioId);
}

// 🔹 Ordenar por ficha y luego por nombre
usort($estudiantes, function($a, $b) {
    $cmp = strcmp($a['ficha'] ?? '', $b['ficha'] ?? '');
    if ($cmp === 0) {
        return strcmp(($a['nombres'] ?? '') . ' ' . ($a['apellidos'] ?? ''), ($b['nombres'] ?? '') . ' ' . ($b['apellidos'] ?? ''));
    }
    return $cmp;
});

// Crear Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// 🔹 Título
$sheet->setCellValue('A1', "Asistencia - " . $colegio['nombre']);
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// 🔹 Subtítulos
$sheet->setCellValue('A2', "Facilitador: " . $profesor);
$sheet->setCellValue('C2', "Fecha: " . date('Y-m-d'));

// 🔹 Encabezados de tabla
$headers = ['Ficha', 'Nombres y Apellidos', 'Tipo Documento', 'Número Documento', 'Fecha Asistencia', 'Jornada', 'Estado'];
$sheet->fromArray($headers, NULL, 'A4');

// Estilo para encabezados
$sheet->getStyle('A4:G4')->getFont()->setBold(true);
$sheet->getStyle('A4:G4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A4:G4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// 🔹 Activar filtros en la tabla
$sheet->setAutoFilter("A4:G4");

// 🔹 Llenar datos
$row = 5;
foreach ($estudiantes as $e) {
    $sheet->setCellValue("A$row", $e['ficha'] ?? 'N/A');
    $sheet->setCellValue("B$row", $e['nombre_completo'] ?? ($e['nombres'].' '.$e['apellidos']));
    $sheet->setCellValue("C$row", $e['tipo_documento'] ?? '');
    $sheet->setCellValue("D$row", $e['numero_documento'] ?? '');
    $sheet->setCellValue("E$row", date('Y-m-d')); // aquí podrías traer la asistencia real
    $sheet->setCellValue("F$row", $e['jornada'] ?? '');
    $sheet->setCellValue("G$row", $e['estado'] ?? 'Activo');
    $row++;
}

// Ajustar ancho automático
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Descargar archivo
$filename = "Asistencia_{$colegio['nombre']}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
