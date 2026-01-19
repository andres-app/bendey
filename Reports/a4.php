<?php
ob_start();
if (strlen(session_id()) < 1) session_start();

if (!isset($_SESSION['nombre'])) { echo "Debe ingresar al sistema correctamente"; exit; }
if ($_SESSION['ventas'] != 1) { echo "No tiene permiso"; exit; }

// ===============================
// HELPERS
// ===============================
function tx($s) {
  $s = (string)$s;
  // Convierte UTF-8 a Windows-1252 para FPDF sin romper tildes/ñ
  return iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
}

// ===============================
// MODELOS
// ===============================
require_once "../Models/Sell.php";
require_once "../Models/Company.php";
require_once "../Libraries/NumeroALetras.php";
require_once "../Libraries/phpqrcode/qrlib.php";
require_once "../Libraries/fpdf182/fpdf.php";

$venta = new Sell();
$reg = $venta->ventacabecera($_GET["id"])[0];
$detalles = $venta->ventadetalles($_GET["id"]);
$pagos = $venta->obtenerPagosVenta($_GET["id"]);

$empresaData = (new Company())->listar()[0];

// ===============================
// DATOS EMPRESA
// ===============================
$empresa   = $empresaData['nombre'];
$ruc       = $empresaData['documento'];
$direccion = $empresaData['direccion'];
$telefono  = $empresaData['telefono'];
$email     = $empresaData['email'] ?? '';
$ciudad    = $empresaData['ciudad'] ?? '';
$simbolo   = $empresaData['simbolo'];
$moneda    = $empresaData['moneda'];
$impuesto  = $empresaData['nombre_impuesto'];
$porcIgv   = (float)$empresaData['monto_impuesto'];

$logo = "../Assets/img/company/" . ($empresaData['logo'] ?? '');
$logoDefault = "../Assets/img/company/default_logo.png";
if (!file_exists($logo)) $logo = $logoDefault;

// ===============================
// DATOS VENTA
// ===============================
$tipoComp  = strtoupper($reg['tipo_comprobante']);
$serieNum  = $reg['serie_comprobante'] . " - " . $reg['num_comprobante'];
$fechaDoc  = date("Y-m-d", strtotime($reg['fecha']));

$cliente   = $reg['cliente'] ?? '--';
$dirCli    = $reg['direccion'] ?? '--';
$docCli    = ($reg['tipo_documento'] ?? '') . ": " . ($reg['num_documento'] ?? '--');

$tipoPago  = $reg['tipo_pago'] ?? '--';
$condicion = $reg['condicion_pago'] ?? 'CONTADO';

// ===============================
// TOTALES (MISMA LÓGICA QUE 80MM)
// ===============================
$total = (float)$reg['total_venta'];
$igv = round($total * $porcIgv / 100, 2);
$subtotal = round($total - $igv, 2);

// ===============================
// LETRAS
// ===============================
$formatter = new NumeroALetras();
$enLetras = strtoupper($formatter->toWords($total)) . " " . $moneda;

// ===============================
// QR
// ===============================
$qrFile = "../Assets/qr_" . $reg['num_comprobante'] . ".png";
QRcode::png($reg['num_comprobante'], $qrFile, QR_ECLEVEL_L, 4);

// ===============================
// PDF A4
// ===============================
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

$M = 10;                 // margen
$W = 210; $H = 297;      // A4
$lineH = 5;

// ===============================
// CABECERA: LOGO + EMPRESA
// ===============================
$pdf->SetXY($M, 10);
if (file_exists($logo)) {
  $pdf->Image($logo, $M, 10, 28); // ancho 28mm
}

$pdf->SetXY($M + 32, 12);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell(110, 5, tx($empresa), 0, 1);

$pdf->SetX($M + 32);
$pdf->SetFont('Helvetica', '', 9);
$pdf->Cell(110, 4, tx("RUC: $ruc"), 0, 1);
$pdf->SetX($M + 32);
$pdf->Cell(110, 4, tx("Dirección: $direccion"), 0, 1);
$pdf->SetX($M + 32);
$pdf->Cell(110, 4, tx("Teléfono: $telefono"), 0, 1);
$pdf->SetX($M + 32);
$pdf->Cell(110, 4, tx("Email: $email"), 0, 1);

// ===============================
// CAJA DERECHA: COMPROBANTE + FECHA
// ===============================
$boxX = 140; $boxY = 10; $boxW = 60; $boxH = 22;
$pdf->Rect($boxX, $boxY, $boxW, $boxH);
$pdf->SetXY($boxX, $boxY);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell($boxW, 7, tx("$tipoComp ELECTRÓNICA N°: " . $reg['serie_comprobante'] . $reg['num_comprobante']), 0, 1, 'C');

$pdf->Line($boxX, $boxY + 7, $boxX + $boxW, $boxY + 7);

$pdf->SetFont('Helvetica', 'B', 8);
$pdf->SetXY($boxX, $boxY + 8);
$pdf->Cell($boxW, 6, tx("FECHA"), 0, 1, 'C');

$pdf->Line($boxX, $boxY + 14, $boxX + $boxW, $boxY + 14);

$pdf->SetFont('Helvetica', '', 9);
$pdf->SetXY($boxX, $boxY + 15);
$pdf->Cell($boxW, 7, tx($fechaDoc), 0, 1, 'C');

// ===============================
// BLOQUE CLIENTE
// ===============================
$cliY = 38;
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetXY($M, $cliY);
$pdf->Cell(0, 5, tx("CLIENTE"), 0, 1);

$pdf->SetFont('Helvetica', '', 9);
$pdf->SetX($M);
$pdf->Cell(0, 4, tx($cliente), 0, 1);

$pdf->SetX($M);
$pdf->Cell(0, 4, tx("Dirección: $dirCli"), 0, 1);

$pdf->SetX($M);
$pdf->Cell(0, 4, tx($docCli), 0, 1);

// ===============================
// FORMA / CONDICIÓN (FUERA DE LA TABLA)
// ===============================
$pdf->Ln(2);
$pdf->SetX($M);
$pdf->Cell(0, 4, tx("Forma de pago: $tipoPago"), 0, 1);
$pdf->SetX($M);
$pdf->Cell(0, 4, tx("Condición: " . ucfirst(strtolower($condicion))), 0, 1);

// ===============================
// TABLA DETALLE (CON LÍNEAS)
// ===============================
$tableX = $M;
$tableY = 72;
$tableW = 190;
$tableH = 165;          // alto máximo de tabla en una hoja
$pdf->Rect($tableX, $tableY, $tableW, $tableH);

// Columnas: ajustadas a tu captura
$colCodigo = 28;
$colDesc   = 92;
$colCant   = 14;
$colPU     = 24;
$colImp    = 32;

// Líneas verticales
$pdf->Line($tableX + $colCodigo, $tableY, $tableX + $colCodigo, $tableY + $tableH);
$pdf->Line($tableX + $colCodigo + $colDesc, $tableY, $tableX + $colCodigo + $colDesc, $tableY + $tableH);
$pdf->Line($tableX + $colCodigo + $colDesc + $colCant, $tableY, $tableX + $colCodigo + $colDesc + $colCant, $tableY + $tableH);
$pdf->Line($tableX + $colCodigo + $colDesc + $colCant + $colPU, $tableY, $tableX + $colCodigo + $colDesc + $colCant + $colPU, $tableY + $tableH);

// Header tabla
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->SetXY($tableX, $tableY);
$pdf->Cell($colCodigo, 7, tx("CODIGO"), 0, 0, 'C');
$pdf->Cell($colDesc,   7, tx("DESCRIPCION"), 0, 0, 'C');
$pdf->Cell($colCant,   7, tx("CANT"), 0, 0, 'C');
$pdf->Cell($colPU,     7, tx("P.U."), 0, 0, 'C');
$pdf->Cell($colImp,    7, tx("IMPORTE"), 0, 1, 'C');

$pdf->Line($tableX, $tableY + 7, $tableX + $tableW, $tableY + 7);

// Filas
$pdf->SetFont('Helvetica', '', 8);
$y = $tableY + 10;
$yMax = $tableY + $tableH - 12;

$cantArticulos = 0;

foreach ($detalles as $d) {
  // altura estimada por descripción
  $desc = (string)$d['articulo'];
  $lines = max(1, ceil(mb_strlen($desc, 'UTF-8') / 38)); // ajuste simple
  $rowH = 5 * $lines;

  if ($y + $rowH > $yMax) break; // una sola hoja

  $cantArticulos += (float)$d['cantidad'];

  // CODIGO
  $pdf->SetXY($tableX + 1, $y);
  $pdf->MultiCell($colCodigo - 2, 5, tx($d['codigo'] ?? ''), 0, 'L');

  // DESCRIPCION
  $pdf->SetXY($tableX + $colCodigo + 1, $y);
  $pdf->MultiCell($colDesc - 2, 5, tx($desc), 0, 'L');

  // CANT
  $pdf->SetXY($tableX + $colCodigo + $colDesc, $y);
  $pdf->Cell($colCant - 2, 5, tx($d['cantidad']), 0, 0, 'R');

  // P.U.
  $pdf->SetXY($tableX + $colCodigo + $colDesc + $colCant, $y);
  $pdf->Cell($colPU - 2, 5, number_format((float)$d['precio_venta'], 2), 0, 0, 'R');

  // IMPORTE
  $pdf->SetXY($tableX + $colCodigo + $colDesc + $colCant + $colPU, $y);
  $pdf->Cell($colImp - 2, 5, number_format((float)$d['subtotal'], 2), 0, 0, 'R');

  $y += $rowH;
}

// ===============================
// TEXTO SON + CANTIDAD (BAJO TABLA, IZQ)
// ===============================
$belowY = $tableY + $tableH + 4;

$pdf->SetFont('Helvetica', '', 8);
$pdf->SetXY($M, $belowY);
$pdf->MultiCell(120, 4, tx("SON: $enLetras"));

$pdf->SetXY($M, $belowY + 10);
$pdf->Cell(120, 4, tx("Cantidad de artículos: " . (int)$cantArticulos), 0, 1);

// ===============================
// CAJA TOTALES (BAJO TABLA, DERECHA)
// ===============================
$totX = 135; $totY = 245; $totW = 65; $totH = 24;
$pdf->Rect($totX, $totY, $totW, $totH);

$pdf->SetFont('Helvetica', 'B', 8);
$pdf->SetXY($totX + 2, $totY + 4);
$pdf->Cell(35, 4, tx("SUBTOTAL:"), 0, 0, 'R');
$pdf->Cell(26, 4, $simbolo . " " . number_format($subtotal, 2), 0, 1, 'R');

$pdf->SetXY($totX + 2, $totY + 10);
$pdf->Cell(35, 4, tx("$impuesto $porcIgv%:"), 0, 0, 'R');
$pdf->Cell(26, 4, $simbolo . " " . number_format($igv, 2), 0, 1, 'R');

$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetXY($totX + 2, $totY + 16);
$pdf->Cell(35, 5, tx("TOTAL:"), 0, 0, 'R');
$pdf->Cell(26, 5, $simbolo . " " . number_format($total, 2), 0, 1, 'R');

// ===============================
// DETALLE PAGO MIXTO (SI APLICA) - ABAJO IZQ
// ===============================
if (count($pagos) > 1) {
  $pdf->SetFont('Helvetica', 'B', 8);
  $pdf->SetXY($M, 240);
  $pdf->Cell(0, 4, tx("Detalle del pago"), 0, 1);

  $pdf->SetFont('Helvetica', '', 8);
  $yy = 244;
  foreach ($pagos as $p) {
    if ($yy > 268) break;
    $pdf->SetXY($M, $yy);
    $pdf->Cell(70, 4, tx($p['nombre']), 0, 0);
    $pdf->Cell(30, 4, $simbolo . " " . number_format((float)$p['monto'], 2), 0, 1, 'R');
    $yy += 4;
  }
}

// ===============================
// QR + TEXTO LEGAL (PIE)
// ===============================
$pdf->Image($qrFile, $M, 262, 26);

$pdf->SetFont('Helvetica', '', 7);
$pdf->SetXY($M, 290);
$pdf->Cell(0, 4, tx("Este comprobante es una representación impresa del Comprobante Electrónico"), 0, 1);

$pdf->Output($tipoComp . '_' . $reg['serie_comprobante'] . '_' . $reg['num_comprobante'] . '.pdf', 'I');

if (file_exists($qrFile)) unlink($qrFile);
ob_end_flush();
