<?php

ob_start();

function textoPdf58($texto)
{
    $texto = (string)$texto;
    $convertido = iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
    return $convertido !== false ? $convertido : $texto;
}

function cantidadPdf58($cantidad)
{
    $cantidad = (float)$cantidad;
    if (floor($cantidad) == $cantidad) {
        return number_format($cantidad, 0);
    }
    return rtrim(rtrim(number_format($cantidad, 2, '.', ''), '0'), '.');
}

function numeroALetras58($numero)
{
    require_once '../Libraries/NumeroALetras.php';
    $formatter = new NumeroALetras();
    return $formatter->toWords($numero);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['nombre'])) {
    echo 'Debe ingresar al sistema correctamente';
    exit;
}

if (!isset($_SESSION['ventas']) || (int)$_SESSION['ventas'] !== 1) {
    echo 'No tiene permiso';
    exit;
}

$idVenta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idVenta <= 0) {
    echo 'Venta inválida';
    exit;
}

require_once '../Models/Sell.php';
require_once '../Models/Company.php';
require_once '../Libraries/phpqrcode/qrlib.php';
require_once '../Libraries/fpdf182/fpdf.php';

$venta = new Sell();
$company = new Company();

$cabecera = $venta->ventacabecera($idVenta);
if (!is_array($cabecera) || empty($cabecera) || !isset($cabecera[0])) {
    echo 'No se encontró la venta';
    exit;
}
$reg = $cabecera[0];

$empresas = $company->listar();
if (!is_array($empresas) || empty($empresas) || !isset($empresas[0])) {
    echo 'No se encontraron los datos de la empresa';
    exit;
}
$empresaData = $empresas[0];

$empresa = trim((string)($empresaData['nombre'] ?? ''));
$documento = trim((string)($empresaData['documento'] ?? ''));
$direccionEmpresa = trim((string)($empresaData['direccion'] ?? ''));
$telefonoEmpresa = trim((string)($empresaData['telefono'] ?? ''));
$ciudad = trim((string)($empresaData['ciudad'] ?? ''));
$nombreImpuesto = trim((string)($empresaData['nombre_impuesto'] ?? 'IGV'));
$porcIgv = (float)($empresaData['monto_impuesto'] ?? $reg['impuesto'] ?? 18);

$monedaCodigo = strtoupper(trim((string)($reg['moneda_codigo'] ?? 'PEN')));
$simbolo = match ($monedaCodigo) {
    'USD' => '$',
    'EUR' => '€',
    default => 'S/'
};
$monedaNombre = match ($monedaCodigo) {
    'USD' => 'DÓLARES AMERICANOS',
    'EUR' => 'EUROS',
    default => 'SOLES'
};

$tipoCambioSunat = round((float)($reg['tipo_cambio_sunat'] ?? 1), 6);
$guiaRemision = trim((string)($reg['guia_remision'] ?? ''));
$tipoOperacionSunat = trim((string)($reg['tipo_operacion_sunat'] ?? '0101'));
$modoEnvioSunat = strtolower(trim((string)($reg['modo_envio_sunat'] ?? '')));
$direccionCliente = trim((string)($reg['direccion'] ?? ''));
$telefonoCliente = trim((string)($reg['telefono'] ?? ''));

$tipoComprobante = trim((string)($reg['tipo_comprobante'] ?? 'COMPROBANTE'));
$serie = trim((string)($reg['serie_comprobante'] ?? ''));
$numero = trim((string)($reg['num_comprobante'] ?? ''));
$cliente = trim((string)($reg['cliente'] ?? '')) ?: 'CLIENTE VARIOS';
$documentoCliente = trim((string)($reg['num_documento'] ?? ''));

$nombreArchivo = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $tipoComprobante . '_' . $serie . '_' . $numero . '_58mm.pdf');
$rutaQr = '../Assets/qr_58_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $serie . '_' . $numero) . '.png';
QRcode::png($numero, $rutaQr, QR_ECLEVEL_L, 3);

$pdf = new FPDF('P', 'mm', [58, 350]);
$pdf->SetMargins(2, 3, 2);
$pdf->SetAutoPageBreak(true, 5);
$pdf->AddPage();

$pdf->SetFont('Helvetica', 'B', 10);
$pdf->MultiCell(0, 4.5, textoPdf58($empresa), 0, 'C');
$pdf->SetFont('Helvetica', '', 7.5);
$pdf->MultiCell(0, 4, textoPdf58('RUC: ' . $documento), 0, 'C');
if ($direccionEmpresa !== '') {
    $pdf->MultiCell(0, 4, textoPdf58('Direc: ' . $direccionEmpresa), 0, 'C');
}
if ($telefonoEmpresa !== '') {
    $pdf->MultiCell(0, 4, textoPdf58('Telf: ' . $telefonoEmpresa), 0, 'C');
}
if ($ciudad !== '') {
    $pdf->MultiCell(0, 4, textoPdf58($ciudad), 0, 'C');
}

$pdf->Ln(1);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->MultiCell(0, 4, textoPdf58(mb_strtoupper($tipoComprobante, 'UTF-8')), 0, 'C');
$pdf->MultiCell(0, 4, textoPdf58($serie . ' - ' . $numero), 0, 'C');

$fechaVenta = $reg['fecha'] ?? date('Y-m-d');
$fechaFormateada = date('d/m/Y', strtotime((string)$fechaVenta));
$pdf->SetFont('Helvetica', '', 7.5);
$pdf->MultiCell(0, 4, textoPdf58('Fecha: ' . $fechaFormateada), 0, 'L');
$pdf->MultiCell(0, 4, textoPdf58('Cliente: ' . $cliente), 0, 'L');
if ($documentoCliente !== '' && $documentoCliente !== '99999999') {
    $pdf->MultiCell(0, 4, textoPdf58('Documento: ' . $documentoCliente), 0, 'L');
}
if ($direccionCliente !== '' && $direccionCliente !== '-') {
    $pdf->MultiCell(0, 4, textoPdf58('Dirección: ' . $direccionCliente), 0, 'L');
}
if ($telefonoCliente !== '') {
    $pdf->MultiCell(0, 4, textoPdf58('Celular: ' . $telefonoCliente), 0, 'L');
}
$pdf->MultiCell(0, 4, textoPdf58('Atendió: ' . (string)$_SESSION['nombre']), 0, 'L');

$pdf->Ln(1);
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(2);
$pdf->SetFont('Helvetica', 'B', 6.5);
$pdf->Cell(26, 4, textoPdf58('ARTÍCULO'), 0, 0, 'L');
$pdf->Cell(6, 4, 'UND', 0, 0, 'R');
$pdf->Cell(10, 4, 'PRECIO', 0, 0, 'R');
$pdf->Cell(12, 4, 'TOTAL', 0, 1, 'R');
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(2);

$detalles = $venta->ventadetalles($idVenta);
if (!is_array($detalles)) {
    $detalles = [];
}
$pdf->SetFont('Helvetica', '', 6.5);
$cantidadArticulos = 0;
foreach ($detalles as $detalle) {
    $sku = trim((string)($detalle['sku'] ?? ''));
    $articulo = trim((string)($detalle['articulo'] ?? ''));
    $nombreArticulo = trim(($sku !== '' ? $sku . ' - ' : '') . $articulo);
    if ($nombreArticulo === '') $nombreArticulo = 'SIN NOMBRE';

    $cantidad = (float)($detalle['cantidad'] ?? 0);
    $precio = (float)($detalle['precio_venta'] ?? 0);
    $subtotalLinea = (float)($detalle['subtotal'] ?? 0);

    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $pdf->SetXY($x, $y);
    $pdf->MultiCell(26, 3.5, textoPdf58($nombreArticulo), 0, 'L');
    $yNombre = $pdf->GetY();

    $pdf->SetXY($x + 26, $y);
    $pdf->Cell(6, 3.5, cantidadPdf58($cantidad), 0, 0, 'R');
    $pdf->Cell(10, 3.5, number_format($precio, 2), 0, 0, 'R');
    $pdf->Cell(12, 3.5, number_format($subtotalLinea, 2), 0, 0, 'R');

    $pdf->SetY(max($yNombre, $y + 3.5));
    $pdf->Ln(.7);
    $cantidadArticulos += $cantidad;
}

$total = round((float)($reg['total_venta'] ?? 0), 2);
$descuentoTotal = round((float)($reg['descuento_total'] ?? 0), 2);
$subtotal = round($total + $descuentoTotal, 2);
$opGravada = round((float)($reg['total_gravado'] ?? 0), 2);
$opExonerada = round((float)($reg['total_exonerado'] ?? 0), 2);
$opInafecta = round((float)($reg['total_inafecto'] ?? 0), 2);
$opExportacion = round((float)($reg['total_exportacion'] ?? 0), 2);
$igv = round((float)($reg['total_igv'] ?? 0), 2);

if ($total > 0 && $opGravada <= .009 && $opExonerada <= .009 && $opInafecta <= .009 && $opExportacion <= .009) {
    $factor = $porcIgv > 0 ? 1 + ($porcIgv / 100) : 1;
    $opGravada = $factor > 1 ? round($total / $factor, 2) : $total;
    $igv = round($total - $opGravada, 2);
}

$pdf->Ln(1);
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(1.5);
$pdf->SetFont('Helvetica', '', 7);

if ($descuentoTotal > .009) {
    $pdf->Cell(27, 4, textoPdf58('SUBTOTAL'), 0, 0, 'L');
    $pdf->Cell(27, 4, textoPdf58($simbolo . ' ' . number_format($subtotal, 2)), 0, 1, 'R');
    $pdf->Cell(27, 4, textoPdf58('DESCUENTO'), 0, 0, 'L');
    $pdf->Cell(27, 4, textoPdf58('- ' . $simbolo . ' ' . number_format($descuentoTotal, 2)), 0, 1, 'R');
}

$totalesTributarios = [
    'OP. GRAVADA' => $opGravada,
    'OP. EXONERADA' => $opExonerada,
    'OP. INAFECTA' => $opInafecta,
    'EXPORTACIÓN' => $opExportacion,
    $nombreImpuesto . ' ' . rtrim(rtrim(number_format($porcIgv, 2, '.', ''), '0'), '.') . '%' => $igv,
];
foreach ($totalesTributarios as $etiqueta => $importe) {
    if ($importe <= .009 && !str_starts_with($etiqueta, $nombreImpuesto)) continue;
    if (str_starts_with($etiqueta, $nombreImpuesto) && $importe <= .009 && $opGravada <= .009) continue;
    $pdf->Cell(27, 4, textoPdf58($etiqueta), 0, 0, 'L');
    $pdf->Cell(27, 4, textoPdf58($simbolo . ' ' . number_format($importe, 2)), 0, 1, 'R');
}
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(27, 5, 'TOTAL', 0, 0, 'L');
$pdf->Cell(27, 5, textoPdf58($simbolo . ' ' . number_format($total, 2)), 0, 1, 'R');

$pagos = $venta->obtenerPagosVenta($idVenta);
if (!is_array($pagos)) $pagos = [];
$nombresFormasPago = [];
foreach ($pagos as $pago) {
    $nombrePago = trim((string)($pago['nombre'] ?? $pago['nombre_forma_pago'] ?? $pago['forma_pago'] ?? ''));
    if ($nombrePago !== '' && !in_array($nombrePago, $nombresFormasPago, true)) {
        $nombresFormasPago[] = $nombrePago;
    }
}
if (count($nombresFormasPago) > 1) {
    $formaPagoTexto = 'Mixto';
} elseif (count($nombresFormasPago) === 1) {
    $formaPagoTexto = $nombresFormasPago[0];
} else {
    $respaldo = [1 => 'Efectivo', 2 => 'Yape | BCP', 3 => 'Plin', 4 => 'Tarjeta | Izipay', 5 => 'Izipay', 6 => 'Mixto'];
    $valor = trim((string)($reg['nombre_forma_pago'] ?? $reg['forma_pago'] ?? $reg['idforma_pago'] ?? ''));
    $formaPagoTexto = $valor !== '' && !ctype_digit($valor) ? $valor : ($respaldo[(int)$valor] ?? 'No especificado');
}

$tipoPagoTexto = match (trim((string)($reg['tipo_pago'] ?? ''))) {
    '1' => 'Contado',
    '4' => 'Crédito',
    default => trim((string)($reg['tipo_pago'] ?? ''))
};
if ($tipoPagoTexto === '') $tipoPagoTexto = 'Contado';

$pdf->Ln(1);
$pdf->SetFont('Helvetica', '', 7);
$pdf->MultiCell(0, 4, textoPdf58('Forma de pago: ' . $formaPagoTexto), 0, 'L');
$pdf->MultiCell(0, 4, textoPdf58('Tipo de pago: ' . $tipoPagoTexto), 0, 'L');
$pdf->MultiCell(0, 4, textoPdf58('Moneda: ' . $monedaCodigo . ' - ' . $monedaNombre), 0, 'L');
if ($monedaCodigo !== 'PEN') {
    $pdf->MultiCell(0, 4, textoPdf58('Tipo de cambio SUNAT: ' . number_format($tipoCambioSunat, 6, '.', '')), 0, 'L');
}
$pdf->MultiCell(0, 4, textoPdf58('Operación SUNAT: ' . $tipoOperacionSunat), 0, 'L');
if ($guiaRemision !== '') {
    $pdf->MultiCell(0, 4, textoPdf58('Guía de remisión: ' . $guiaRemision), 0, 'L');
}
if ($modoEnvioSunat !== '') {
    $modoTexto = match ($modoEnvioSunat) {
        'manual' => 'Envío manual',
        'resumen_diario' => 'Resumen Diario',
        default => 'Envío inmediato'
    };
    $pdf->MultiCell(0, 4, textoPdf58('Envío del comprobante: ' . $modoTexto), 0, 'L');
}

if (count($pagos) > 1) {
    $pdf->Ln(1);
    $pdf->Cell(0, 0, '', 'T');
    $pdf->Ln(1.5);
    $pdf->SetFont('Helvetica', 'B', 7);
    $pdf->Cell(0, 4, textoPdf58('Detalle del pago'), 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 7);
    foreach ($pagos as $pago) {
        $nombrePago = trim((string)($pago['nombre'] ?? $pago['nombre_forma_pago'] ?? $pago['forma_pago'] ?? 'Pago')) ?: 'Pago';
        $montoPago = (float)($pago['monto'] ?? 0);
        $pdf->Cell(30, 4, textoPdf58($nombrePago), 0, 0, 'L');
        $pdf->Cell(24, 4, textoPdf58($simbolo . ' ' . number_format($montoPago, 2)), 0, 1, 'R');
    }
}

$pdf->Ln(1.5);
$pdf->SetFont('Helvetica', '', 6.5);
$pdf->MultiCell(0, 3.5, textoPdf58('SON: ' . strtoupper(numeroALetras58($total)) . ' ' . $monedaNombre), 0, 'L');
$pdf->MultiCell(0, 3.5, textoPdf58('CANT. ARTÍCULOS: ' . cantidadPdf58($cantidadArticulos)), 0, 'L');

$qrSize = 24;
$xQr = (58 - $qrSize) / 2;
$pdf->Ln(1.5);
$yQr = $pdf->GetY();
if (file_exists($rutaQr)) {
    $pdf->Image($rutaQr, $xQr, $yQr, $qrSize, $qrSize);
}
$pdf->SetY($yQr + $qrSize + 1.5);
$pdf->SetFont('Helvetica', '', 6.5);
$pdf->MultiCell(0, 3, textoPdf58("Este comprobante es una representación impresa\ndel Comprobante Electrónico"), 0, 'C');
$pdf->Ln(.5);
$pdf->SetFont('Helvetica', 'B', 7);
$pdf->Cell(0, 3.5, textoPdf58('TIQUEPOS S.A.C'), 0, 1, 'C');
$pdf->SetFont('Helvetica', '', 6.5);
$pdf->Cell(0, 3.5, textoPdf58('www.tiquepos.com'), 0, 1, 'C');

if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output('I', $nombreArchivo);

if (file_exists($rutaQr)) {
    unlink($rutaQr);
}
