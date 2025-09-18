<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Encabezados EXACTOS esperados por el importador (en minúsculas)
$headers = [
  'nombres',
  'apellidos',
  'tipo_documento',
  'numero_documento',
  'correo_electronico',
  'telefono',
  'fecha_nacimiento',
  'genero',
  'grado',
  'grupo',
  'jornada',
  'nombre_completo_acudiente',
  'tipo_documento_acudiente',
  'numero_documento_acudiente',
  'telefono_acudiente',
  'parentesco',
  'ocupacion',
];

$sheet->fromArray($headers, null, 'A1');

// Estilos simples para cabecera
$lastCol = chr(ord('A') + count($headers) - 1);
$sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
$sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3E8');

// Fila de ejemplo opcional (puede eliminarse por el usuario)
$sample = [
  'Juan',
  'Pérez',
  'TI',
  '1020304050',
  'juan.perez@example.com',
  '3001234567',
  '2008-05-12',
  'M',
  '9',
  'A',
  'Mañana',
  'María Pérez',
  'CC',
  '52030405',
  '3007654321',
  'Madre',
  'Empleado',
];
$sheet->fromArray($sample, null, 'A2');

// Anchos
for ($i = 0; $i < count($headers); $i++) {
  $col = chr(ord('A') + $i);
  $sheet->getColumnDimension($col)->setWidth(22);
}

if (ob_get_length()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="plantilla_import_estudiantes.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
