<?php
// ===============================
// CONVERTIR NÚMERO A LETRAS
// ===============================
function convertirNumeroALetras($numero)
{
  require_once "../Libraries/NumeroALetras.php";
  $formatter = new NumeroALetras();
  return $formatter->toWords($numero);
}

ob_start();
if (strlen(session_id()) < 1) session_start();

if (!isset($_SESSION['nombre'])) {
  echo "Debe ingresar al sistema correctamente";
  exit;
}

if ($_SESSION['ventas'] != 1) {
  echo "No tiene permiso";
  exit;
}

// ===============================
// MODELOS
// ===============================
require_once "../Models/Sell.php";
require_once "../Models/Company.php";

$venta = new Sell();
$reg = $venta->ventacabecera($_GET["id"])[0];

$empresaData = (new Company())->listar()[0];

// ===============================
// DATOS EMPRESA
// ===============================
$empresa   = $empresaData['nombre'];
$documento = $empresaData['documento'];
$direccion = $empresaData['direccion'];
$telefono  = $empresaData['telefono'];
$ciudad    = $empresaData['ciudad'];
$impuesto  = $empresaData['nombre_impuesto'];
$porcIgv   = $empresaData['monto_impuesto'];
$simbolo   = $empresaData['simbolo'];

// ===============================
// QR
// ===============================
include('../Libraries/phpqrcode/qrlib.php');
$filename = '../Assets/qr_' . $reg['num_comprobante'] . '.png';
QRcode::png($reg['num_comprobante'], $filename, QR_ECLEVEL_L, 3);

// ===============================
// PDF 80MM REAL
// ===============================
include('../Libraries/fpdf182/fpdf.php');
$pdf = new FPDF('P', 'mm', [80, 350]);
$pdf->SetMargins(2, 4, 2);
$pdf->AddPage();

// ===============================
// CABECERA CENTRADA
// ===============================
$pdf->SetFont('Helvetica', 'B', 12);
$pdf->Cell(0, 5, utf8_decode($empresa), 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 9);
$pdf->Cell(0, 5, "RUC: " . $documento, 0, 1, 'C');
$pdf->Cell(0, 5, "Direc: " . $direccion, 0, 1, 'C');
$pdf->Cell(0, 5, "Telf: " . $telefono, 0, 1, 'C');
$pdf->Cell(0, 5, $ciudad, 0, 1, 'C');

// ===============================
// FECHA Y USUARIO
// ===============================
$pdf->Ln(2);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(0, 5, "Fecha: " . date("d/m/Y", strtotime($reg['fecha'])), 0, 1, 'C');

// ===============================
// COMPROBANTE
// ===============================
$pdf->Ln(2);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(
  0,
  5,
  utf8_decode(
    mb_strtoupper(
      $reg['tipo_comprobante'],
      'UTF-8'
    ) . " N° " . $reg['serie_comprobante'] . " - " . $reg['num_comprobante']
  ),
  0,
  1,
  'C'
);


// ===============================
// CLIENTE / ATENDIÓ
// ===============================
$pdf->Ln(2);
$pdf->SetFont('Helvetica', '', 8);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(0, 5, utf8_decode("Cliente: " . ($cab['cliente'] ?? '')), 0, 1);
$pdf->Cell(0, 5, utf8_decode("Atendió: " . ($_SESSION['nombre'] ?? '')), 0, 1);



// ===============================
// LINEA
// ===============================
$pdf->Ln(1);
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(2);

// ===============================
// COLUMNAS
// ===============================
$pdf->SetFont('Helvetica', 'B', 7);
$pdf->Cell(38, 4, 'ARTICULO');
$pdf->Cell(8, 4, 'UND', 0, 0, 'R');
$pdf->Cell(14, 4, 'PRECIO', 0, 0, 'R');
$pdf->Cell(16, 4, 'TOTAL', 0, 1, 'R');
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(2);

// ===============================
// DETALLE
// ===============================
$pdf->SetFont('Helvetica', '', 7);
$cantidad = 0;

foreach ($venta->ventadetalles($_GET["id"]) as $d) {
  $y = $pdf->GetY();
  $pdf->MultiCell(38, 4, utf8_decode($d['articulo']));
  $pdf->SetXY(40, $y);
  $pdf->Cell(8, 4, $d['cantidad'], 0, 0, 'R');
  $pdf->Cell(14, 4, number_format($d['precio_venta'], 2), 0, 0, 'R');
  $pdf->Cell(16, 4, number_format($d['subtotal'], 2), 0, 1, 'R');
  $cantidad += $d['cantidad'];
}

// ===============================
// TOTALES
// ===============================
$total = $reg['total_venta'];
$igv = round($total * $porcIgv / 100, 2);
$subtotal = round($total - $igv, 2);

$pdf->Ln(1);
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(2);

$pdf->Cell(40, 5, 'SUBTOTAL');
$pdf->Cell(36, 5, $simbolo . ' ' . number_format($subtotal, 2), 0, 1, 'R');

$pdf->Cell(40, 5, $impuesto . ' ' . $porcIgv . '%');
$pdf->Cell(36, 5, $simbolo . ' ' . number_format($igv, 2), 0, 1, 'R');

$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(40, 6, 'TOTAL');
$pdf->Cell(36, 6, $simbolo . ' ' . number_format($total, 2), 0, 1, 'R');

// ===============================
// PIE
// ===============================
$pdf->Ln(2);
$pdf->SetFont('Helvetica', '', 7);
$pdf->MultiCell(0, 4, 'SON: ' . strtoupper(convertirNumeroALetras($total)) . ' ' . $simbolo);

$pdf->Cell(0, 5, 'CANT. ARTICULOS: ' . $cantidad, 0, 1);

$pdf->Ln(3);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(0, 6, utf8_decode('¡GRACIAS POR SU COMPRA!'), 0, 1, 'C');

// ===============================
// QR CENTRADO REAL
// ===============================
$qrSize = 30;
$x = (80 - $qrSize) / 2;
$pdf->Ln(2);
$pdf->Image($filename, $x, $pdf->GetY(), $qrSize);

// ===============================
// SALIDA
// ===============================
$pdf->Output(
  $reg['tipo_comprobante'] . '_' . $reg['serie_comprobante'] . '_' . $reg['num_comprobante'] . '.pdf',
  'I'
);

unlink($filename);
ob_end_flush();
