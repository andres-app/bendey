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
// MODELOSS
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
$porcIgv   = (float)$empresaData['monto_impuesto'];
$simbolo   = $empresaData['simbolo'];
$moneda    = $empresaData['moneda'];

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
$pdf->Cell(0, 5, "Direc: " . utf8_decode($direccion), 0, 1, 'C');
$pdf->Cell(0, 5, "Telf: " . $telefono, 0, 1, 'C');
$pdf->Cell(0, 5, utf8_decode($ciudad), 0, 1, 'C');

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

// Línea 1: tipo de comprobante
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->Cell(
  0,
  5,
  utf8_decode(mb_strtoupper($reg['tipo_comprobante'], 'UTF-8')),
  0,
  1,
  'C'
);

// Línea 2: serie y número
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(
  0,
  5,
  utf8_decode($reg['serie_comprobante'] . ' - ' . $reg['num_comprobante']),
  0,
  1,
  'C'
);

// ===============================
// CLIENTE / ATENDIÓ
// ===============================
$pdf->Ln(2);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(0, 5, utf8_decode("Cliente: " . ($reg['cliente'] ?? '')), 0, 1);
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
$pdf->Cell(38, 4, utf8_decode('ARTÍCULO'));
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

  $nombreArticulo = $d['articulo'];
  if (!empty($d['sku'])) {
    $nombreArticulo .= ' (' . $d['sku'] . ')';
  }

$nombreArticulo = '';

if (!empty($d['sku'])) {
    $nombreArticulo .= $d['sku'] . ' - ';
}

$nombreArticulo .= $d['articulo'];


  $pdf->MultiCell(38, 4, utf8_decode($nombreArticulo));
  $pdf->SetXY(40, $y);
  $pdf->Cell(8, 4, $d['cantidad'], 0, 0, 'R');
  $pdf->Cell(14, 4, number_format($d['precio_venta'], 2), 0, 0, 'R');
  $pdf->Cell(16, 4, number_format($d['subtotal'], 2), 0, 1, 'R');

  $cantidad += (float)$d['cantidad'];
}

// ===============================
// TOTALES (MOSTRANDO DESCUENTO REAL)
// ===============================
$total = (float) ($reg['total_venta'] ?? 0);
$descuento_total = (float) ($reg['descuento_total'] ?? 0);
$descuento_porcentaje = (float) ($reg['descuento_porcentaje'] ?? 0);

// Subtotal antes del descuento
$subtotal = round($total + $descuento_total, 2);

// IGV calculado sobre el subtotal neto (total final)
$igv = round($total * $porcIgv / 100, 2);

$pdf->Ln(1);
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(2);

// SUBTOTAL
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(40, 5, utf8_decode('SUBTOTAL'));
$pdf->Cell(36, 5, $simbolo . ' ' . number_format($subtotal, 2), 0, 1, 'R');

// DESCUENTO (SI EXISTE)
if ($descuento_total > 0) {
    $pdf->Cell(
        40,
        5,
        utf8_decode(
            'DESCUENTO ' .
            rtrim(rtrim(number_format($descuento_porcentaje, 2), '0'), '.') . '%'
        )
    );
    $pdf->Cell(
        36,
        5,
        '- ' . $simbolo . ' ' . number_format($descuento_total, 2),
        0,
        1,
        'R'
    );
}

// IGV
$pdf->Cell(40, 5, utf8_decode($impuesto . ' ' . $porcIgv . '%'));
$pdf->Cell(36, 5, $simbolo . ' ' . number_format($igv, 2), 0, 1, 'R');

// TOTAL
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(40, 6, utf8_decode('TOTAL'));
$pdf->Cell(36, 6, $simbolo . ' ' . number_format($total, 2), 0, 1, 'R');


// ===============================
// FORMA DE PAGO (TEXTO)
// ===============================
$pdf->Ln(1);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(0, 5, utf8_decode('Forma de pago: ' . ($reg['tipo_pago'] ?? '')), 0, 1);

// ===============================
// CONDICIÓN DE PAGO (si existe)
// ===============================
$condicion = $reg['condicion_pago'] ?? 'CONTADO';
$pdf->Cell(0, 5, utf8_decode('Condición: ' . ucfirst(strtolower($condicion))), 0, 1);

// ===============================
// DETALLE FORMA DE PAGO (SOLO MIXTO)
// ===============================
$pagos = $venta->obtenerPagosVenta($_GET["id"]);

if (is_array($pagos) && count($pagos) > 1) {

  $pdf->Ln(1);
  $pdf->Cell(0, 0, '', 'T');
  $pdf->Ln(2);

  $pdf->SetFont('Helvetica', 'B', 8);
  $pdf->Cell(0, 5, utf8_decode('Detalle del pago'), 0, 1, 'C');

  $pdf->Ln(1);
  $pdf->SetFont('Helvetica', '', 8);

  foreach ($pagos as $p) {
    $pdf->Cell(40, 5, utf8_decode($p['nombre']));
    $pdf->Cell(36, 5, $simbolo . ' ' . number_format((float)$p['monto'], 2), 0, 1, 'R');
  }
}

// ===============================
// PIE
// ===============================
$pdf->Ln(2);
$pdf->SetFont('Helvetica', '', 7);
$pdf->MultiCell(0, 4, utf8_decode('SON: ' . strtoupper(convertirNumeroALetras($total)) . ' ' . $moneda));

$pdf->Cell(0, 5, utf8_decode('CANT. ARTÍCULOS: ' . $cantidad), 0, 1);

// ===============================
// QR CENTRADO REAL
// ===============================
$qrSize = 30;
$x = (80 - $qrSize) / 2;
$pdf->Ln(2);
$pdf->Image($filename, $x, $pdf->GetY(), $qrSize);

// ===============================
// TEXTO LEGAL DEBAJO DEL QR
// ===============================
$pdf->Ln($qrSize + 2);
$pdf->SetFont('Helvetica', '', 8);

$pdf->MultiCell(
  0,
  3,
  utf8_decode(
    "Este comprobante es una representación impresa\n" .
    "del Comprobante Electrónico"
  ),
  0,
  'C'
);

$pdf->Ln(1);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(0, 4, utf8_decode('TIQUEPOS S.A.C'), 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(0, 4, utf8_decode('www.tiquepos.com'), 0, 1, 'C');

// ===============================
// SALIDA (EVITAR ERROR OUTPUT)
// ===============================
// Si hubo warnings/echo antes, FPDF revienta. Esto lo evita:
if (ob_get_length()) {
  ob_end_clean();
}

$pdf->Output(
  $reg['tipo_comprobante'] . '_' . $reg['serie_comprobante'] . '_' . $reg['num_comprobante'] . '.pdf',
  'I'
);

unlink($filename);
