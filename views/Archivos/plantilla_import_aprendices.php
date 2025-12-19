<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Encabezados con marcación de obligatorios (*) y en el orden solicitado (A..AC)
$headers = [
  'nombres *',
  'apellidos *',
  'tipo_documento *',
  'numero_documento *',
  'genero *',
  'genero_otro',
  'fecha_nacimiento *',
  'rh *',
  'correo_electronico *',
  'correo_institucional',
  'telefono *',
  'municipio',
  'direccion',
  'barrio',
  'eps *',
  'eps_otro',
  'estrato *',
  'grado *',
  'grupo',
  'jornada *',
  'nombre_completo_acudiente *',
  'tipo_documento_acudiente *',
  'numero_documento_acudiente *',
  'telefono_acudiente *',
  'parentesco *',
  'parentesco_otro',
  'ocupacion *',
  'ocupacion_otro',
  'colegio *',
];

$sheet->fromArray($headers, null, 'A1');

// Rango de columnas (A..AC)
$lastCol = Coordinate::stringFromColumnIndex(count($headers));

// Estilos de cabecera: fondo #0B3A53 y texto blanco, centrado
$sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle("A1:{$lastCol}1")->getAlignment()
      ->setHorizontal(Alignment::HORIZONTAL_CENTER)
      ->setVertical(Alignment::VERTICAL_CENTER)
      ->setWrapText(true);
$sheet->getStyle("A1:{$lastCol}1")->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setRGB('0B3A53');

// Alineación y ajuste de texto para todo el rango A1:AB200
$sheet->getStyle("A1:{$lastCol}200")->getAlignment()
      ->setHorizontal(Alignment::HORIZONTAL_CENTER)
      ->setVertical(Alignment::VERTICAL_CENTER)
      ->setWrapText(true);

// Fila de ejemplo opcional (puede eliminarse por el usuario)
$sample = [
  'Juan',                // A nombres *
  'Pérez',               // B apellidos *
  'TI',                  // C tipo_documento *
  '1020304050',          // D numero_documento *
  'M',                   // E genero *
  '',                    // F genero_otro
  '12/05/2008',          // G fecha_nacimiento * (dd/mm/aaaa)
  'O+',                  // H rh *
  'juan.perez@example.com', // I correo_electronico *
  'j.perez@colegio.edu.co', // J correo_institucional
  '3001234567',          // K telefono *
  'Bogotá',              // L municipio
  'Calle 123 #45-67',    // M direccion
  'Chapinero',           // N barrio
  'EPS Sanitas',         // O eps *
  '',                    // P eps_otro
  '3',                   // Q estrato *
  '9',                   // R grado *
  'A',                   // S grupo
  'Mañana',              // T jornada *
  'María Pérez',         // U nombre_completo_acudiente *
  'CC',                  // V tipo_documento_acudiente *
  '52030405',            // W numero_documento_acudiente *
  '3007654321',          // X telefono_acudiente *
  'Madre',               // Y parentesco *
  '',                    // Z parentesco_otro
  'Empleado',            // AA ocupacion *
  '',                    // AB ocupacion_otro
  'Nombre del colegio',  // AC colegio * (nombre o código DANE)
];
$sheet->fromArray($sample, null, 'A2');

// Fuente para filas 2..200
$sheet->getStyle("A2:{$lastCol}200")->getFont()->setName('TH SarabunPSK')->setSize(16);

// Altura de filas 2..200 (30px ≈ 22.5pt)
for ($r = 2; $r <= 200; $r++) {
  $sheet->getRowDimension($r)->setRowHeight(22.5);
}

// Conversión aproximada de pixeles a unidades de columna de Excel
$pxToWidth = function($px) {
  // Fórmula aproximada: width ≈ (px - 5) / 7
  $w = ($px - 5) / 7; 
  return $w > 0 ? $w : 0;
};

// Anchos por grupo en píxeles
$setColsPx = function(array $letters, $px) use ($sheet, $pxToWidth) {
  $w = $pxToWidth($px);
  foreach ($letters as $col) {
    $sheet->getColumnDimension($col)->setWidth($w);
  }
};

// 185 px → L, M, N, U, AC
$setColsPx(['L','M','N','U','AC'], 185);
// 167 px → W
$setColsPx(['W'], 167);
// 155 px → I, J, V
$setColsPx(['I','J','V'], 155);
// 125 px → A, B, C, D, G, K, O, P, Q, X
$setColsPx(['A','B','C','D','G','K','O','P','Q','X'], 125);
// 95 px → E, F, R, S, T, Y, Z, AA, AB
$setColsPx(['E','F','R','S','T','Y','Z','AA','AB'], 95);
// 65 px → H
$setColsPx(['H'], 65);

if (ob_get_length()) ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="plantilla_import_estudiantes.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
