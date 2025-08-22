<?php
// Mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../vendor/autoload.php';

// ✅ Usar clase sin namespace
$pdf = new \FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(70, 200, 'Hola desde FPDF con Composer!');

// 👇 Mostrar en navegador pero con nombre definido
$pdf->Output('I', 'reporte.pdf');
exit;
