<?php

declare(strict_types=1);

ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION['nombre'])
    || (int)($_SESSION['ventas'] ?? 0) !== 1
) {
    echo 'No tiene permiso';
    exit;
}

$idnota = (int)($_GET['id'] ?? 0);

if ($idnota <= 0) {
    echo 'Nota de crédito inválida';
    exit;
}

require_once __DIR__ . '/../Models/CreditNote.php';
require_once __DIR__ . '/../Models/Company.php';
require_once __DIR__ . '/../Libraries/fpdf182/fpdf.php';
require_once __DIR__ . '/../Libraries/phpqrcode/qrlib.php';

function ncPdfTexto(string $texto): string
{
    $convertido = iconv(
        'UTF-8',
        'windows-1252//TRANSLIT',
        $texto
    );

    return $convertido !== false ? $convertido : $texto;
}

function ncPdfCantidad(float $cantidad): string
{
    if (abs($cantidad - round($cantidad)) < 0.0001) {
        return number_format($cantidad, 0, '.', '');
    }

    return rtrim(
        rtrim(number_format($cantidad, 3, '.', ''), '0'),
        '.'
    );
}

$notas = new CreditNote();
$notaCompleta = $notas->obtenerNota($idnota);

if (!$notaCompleta) {
    echo 'No se encontró la nota de crédito';
    exit;
}

$nota = $notaCompleta['cabecera'];
$detalles = $notaCompleta['detalles'];
$pagos = $notaCompleta['pagos'];

$empresaListado = (new Company())->listar();
$empresa = is_array($empresaListado) && isset($empresaListado[0])
    ? $empresaListado[0]
    : [];

$nombreEmpresa = trim((string)($empresa['nombre'] ?? ''));
$ruc = trim((string)($empresa['documento'] ?? $empresa['ndocumento'] ?? ''));
$direccion = trim((string)($empresa['direccion'] ?? ''));
$telefono = trim((string)($empresa['telefono'] ?? ''));
$emailEmpresa = trim((string)($empresa['email'] ?? ''));
$simbolo = trim((string)($empresa['simbolo'] ?? 'S/'));

$logo = __DIR__ . '/../Assets/img/company/' . trim((string)($empresa['logo'] ?? ''));
if (!is_file($logo)) {
    $logo = __DIR__ . '/../Assets/img/company/default_logo.png';
}

$serie = (string)$nota['serie_comprobante'];
$numero = (string)$nota['num_comprobante'];
$comprobante = $serie . '-' . $numero;
$origen = (string)$nota['serie_documento_modificado']
    . '-'
    . (string)$nota['numero_documento_modificado'];

$qrRuta = sys_get_temp_dir()
    . '/nc_'
    . preg_replace('/[^A-Za-z0-9_-]/', '_', $comprobante)
    . '_' . bin2hex(random_bytes(4)) . '.png';

$contenidoQr = implode('|', [
    preg_replace('/\D/', '', $ruc),
    '07',
    $serie,
    $numero,
    number_format((float)$nota['igv'], 2, '.', ''),
    number_format((float)$nota['total_nota'], 2, '.', ''),
    date('Y-m-d', strtotime((string)$nota['fecha_hora'])),
    (string)$nota['cliente_tipo_documento'],
    (string)$nota['cliente_num_documento']
]);

QRcode::png($contenidoQr, $qrRuta, QR_ECLEVEL_L, 3);

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

if (is_file($logo)) {
    $pdf->Image($logo, 12, 12, 27);
}

$pdf->SetXY(43, 13);
$pdf->SetFont('Helvetica', 'B', 12);
$pdf->Cell(92, 6, ncPdfTexto($nombreEmpresa), 0, 1);
$pdf->SetX(43);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(92, 4, ncPdfTexto('RUC: ' . $ruc), 0, 1);
$pdf->SetX(43);
$pdf->MultiCell(92, 4, ncPdfTexto($direccion), 0, 'L');
$pdf->SetX(43);
$pdf->Cell(92, 4, ncPdfTexto('Teléfono: ' . $telefono), 0, 1);
if ($emailEmpresa !== '') {
    $pdf->SetX(43);
    $pdf->Cell(92, 4, ncPdfTexto('Email: ' . $emailEmpresa), 0, 1);
}

$boxX = 139;
$boxY = 12;
$boxW = 59;
$boxH = 31;
$pdf->Rect($boxX, $boxY, $boxW, $boxH);
$pdf->SetXY($boxX, $boxY + 3);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->MultiCell($boxW, 5, ncPdfTexto('NOTA DE CRÉDITO\nELECTRÓNICA'), 0, 'C');
$pdf->SetXY($boxX, $boxY + 17);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell($boxW, 6, ncPdfTexto($comprobante), 0, 1, 'C');
$pdf->SetXY($boxX, $boxY + 24);
$pdf->SetFont('Helvetica', '', 7);
$pdf->Cell(
    $boxW,
    4,
    ncPdfTexto('SUNAT: ' . strtoupper((string)$nota['estado_sunat'])),
    0,
    1,
    'C'
);

$pdf->SetY(50);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(0, 5, ncPdfTexto('DATOS DEL CLIENTE'), 0, 1);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(25, 5, ncPdfTexto('Cliente:'), 0, 0);
$pdf->MultiCell(0, 5, ncPdfTexto((string)$nota['cliente_nombre']), 0, 'L');
$pdf->Cell(25, 5, ncPdfTexto('Documento:'), 0, 0);
$pdf->Cell(
    0,
    5,
    ncPdfTexto(
        (string)$nota['cliente_tipo_documento']
        . ': '
        . (string)$nota['cliente_num_documento']
    ),
    0,
    1
);
$pdf->Cell(25, 5, ncPdfTexto('Dirección:'), 0, 0);
$pdf->MultiCell(
    0,
    5,
    ncPdfTexto((string)($nota['cliente_direccion'] ?? '-')),
    0,
    'L'
);

$pdf->Ln(2);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(0, 5, ncPdfTexto('INFORMACIÓN DE LA NOTA'), 0, 1);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(40, 5, ncPdfTexto('Fecha de emisión:'), 0, 0);
$pdf->Cell(
    50,
    5,
    ncPdfTexto(date('d/m/Y H:i', strtotime((string)$nota['fecha_hora']))),
    0,
    0
);
$pdf->Cell(42, 5, ncPdfTexto('Documento modificado:'), 0, 0);
$pdf->Cell(0, 5, ncPdfTexto($origen), 0, 1);
$pdf->Cell(40, 5, ncPdfTexto('Código de motivo:'), 0, 0);
$pdf->Cell(
    0,
    5,
    ncPdfTexto(
        (string)$nota['codigo_motivo']
        . ' - '
        . (string)$nota['motivo_descripcion']
    ),
    0,
    1
);
$pdf->Cell(40, 5, ncPdfTexto('Sustento:'), 0, 0);
$pdf->MultiCell(0, 5, ncPdfTexto((string)$nota['sustento']), 0, 'L');

$pdf->Ln(3);

$col = [23, 85, 16, 28, 28];
$pdf->SetFont('Helvetica', 'B', 7);
$pdf->SetFillColor(242, 244, 247);
$pdf->Cell($col[0], 7, ncPdfTexto('CÓDIGO'), 1, 0, 'C', true);
$pdf->Cell($col[1], 7, ncPdfTexto('DESCRIPCIÓN'), 1, 0, 'C', true);
$pdf->Cell($col[2], 7, ncPdfTexto('CANT.'), 1, 0, 'C', true);
$pdf->Cell($col[3], 7, ncPdfTexto('P. UNIT.'), 1, 0, 'C', true);
$pdf->Cell($col[4], 7, ncPdfTexto('IMPORTE'), 1, 1, 'C', true);

$pdf->SetFont('Helvetica', '', 7);

foreach ($detalles as $detalle) {
    $descripcion = (string)$detalle['descripcion_articulo'];
    $lineasDescripcion = max(1, (int)ceil(mb_strlen($descripcion, 'UTF-8') / 48));
    $alto = max(6, $lineasDescripcion * 4);
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    if ($y + $alto > 270) {
        $pdf->AddPage();
        $y = $pdf->GetY();
    }

    $pdf->SetXY($x, $y);
    $pdf->Cell($col[0], $alto, ncPdfTexto((string)$detalle['codigo_articulo']), 1, 0);
    $pdf->SetXY($x + $col[0], $y);
    $pdf->MultiCell($col[1], 4, ncPdfTexto($descripcion), 1, 'L');
    $pdf->SetXY($x + $col[0] + $col[1], $y);
    $pdf->Cell($col[2], $alto, ncPdfCantidad((float)$detalle['cantidad_nota']), 1, 0, 'R');
    $pdf->Cell(
        $col[3],
        $alto,
        number_format((float)$detalle['precio_unitario_con_igv'], 2),
        1,
        0,
        'R'
    );
    $pdf->Cell(
        $col[4],
        $alto,
        number_format((float)$detalle['total_linea'], 2),
        1,
        0,
        'R'
    );
    $pdf->SetY($y + $alto);
}

$pdf->Ln(4);
$totX = 132;
$pdf->SetX($totX);
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(35, 6, ncPdfTexto('VALOR DE VENTA'), 0, 0, 'R');
$pdf->Cell(31, 6, ncPdfTexto($simbolo . ' ' . number_format((float)$nota['valor_venta'], 2)), 0, 1, 'R');
$pdf->SetX($totX);
$pdf->Cell(35, 6, ncPdfTexto('IGV 18%'), 0, 0, 'R');
$pdf->Cell(31, 6, ncPdfTexto($simbolo . ' ' . number_format((float)$nota['igv'], 2)), 0, 1, 'R');
$pdf->SetX($totX);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Cell(35, 7, ncPdfTexto('TOTAL NOTA'), 0, 0, 'R');
$pdf->Cell(31, 7, ncPdfTexto($simbolo . ' ' . number_format((float)$nota['total_nota'], 2)), 0, 1, 'R');

if (count($pagos) > 0) {
    $pdf->Ln(2);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->Cell(0, 5, ncPdfTexto('FORMA DE DEVOLUCIÓN'), 0, 1);
    $pdf->SetFont('Helvetica', '', 8);

    foreach ($pagos as $pago) {
        $pdf->Cell(65, 5, ncPdfTexto((string)$pago['forma_pago']), 0, 0);
        $pdf->Cell(
            35,
            5,
            ncPdfTexto($simbolo . ' ' . number_format((float)$pago['monto'], 2)),
            0,
            1,
            'R'
        );
    }
}

if (is_file($qrRuta)) {
    $yQr = max($pdf->GetY() + 4, 230);
    if ($yQr > 250) {
        $pdf->AddPage();
        $yQr = 20;
    }
    $pdf->Image($qrRuta, 12, $yQr, 28, 28);
    $pdf->SetXY(44, $yQr + 3);
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->MultiCell(
        150,
        4,
        ncPdfTexto(
            "Esta es una representación impresa de la Nota de Crédito Electrónica.\n"
            . 'Documento modificado: ' . $origen . '.\n'
            . 'Estado SUNAT: ' . strtoupper((string)$nota['estado_sunat']) . '.'
        ),
        0,
        'L'
    );
}

if (ob_get_length()) {
    ob_end_clean();
}

$pdf->Output(
    'I',
    'Nota_Credito_' . $serie . '_' . $numero . '.pdf'
);

if (is_file($qrRuta)) {
    @unlink($qrRuta);
}
