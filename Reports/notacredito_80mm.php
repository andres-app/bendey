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

require_once __DIR__ . '/../Models/CreditNote.php';
require_once __DIR__ . '/../Models/Company.php';
require_once __DIR__ . '/../Libraries/fpdf182/fpdf.php';
require_once __DIR__ . '/../Libraries/phpqrcode/qrlib.php';

function nc80Texto(string $texto): string
{
    $convertido = iconv(
        'UTF-8',
        'windows-1252//TRANSLIT',
        $texto
    );
    return $convertido !== false ? $convertido : $texto;
}

function nc80Cantidad(float $cantidad): string
{
    if (abs($cantidad - round($cantidad)) < 0.0001) {
        return number_format($cantidad, 0, '.', '');
    }
    return rtrim(rtrim(number_format($cantidad, 3, '.', ''), '0'), '.');
}

$notas = new CreditNote();
$completo = $notas->obtenerNota($idnota);

if (!$completo) {
    echo 'No se encontró la nota de crédito';
    exit;
}

$nota = $completo['cabecera'];
$detalles = $completo['detalles'];
$pagos = $completo['pagos'];
$empresaListado = (new Company())->listar();
$empresa = is_array($empresaListado) && isset($empresaListado[0])
    ? $empresaListado[0]
    : [];

$nombreEmpresa = trim((string)($empresa['nombre'] ?? ''));
$ruc = trim((string)($empresa['documento'] ?? $empresa['ndocumento'] ?? ''));
$direccion = trim((string)($empresa['direccion'] ?? ''));
$telefono = trim((string)($empresa['telefono'] ?? ''));
$simbolo = trim((string)($empresa['simbolo'] ?? 'S/'));
$serie = (string)$nota['serie_comprobante'];
$numero = (string)$nota['num_comprobante'];
$origen = (string)$nota['serie_documento_modificado']
    . '-'
    . (string)$nota['numero_documento_modificado'];

$alto = max(240, 155 + count($detalles) * 13 + count($pagos) * 7);
$pdf = new FPDF('P', 'mm', [80, $alto]);
$pdf->SetMargins(3, 4, 3);
$pdf->SetAutoPageBreak(true, 5);
$pdf->AddPage();

$pdf->SetFont('Helvetica', 'B', 11);
$pdf->MultiCell(0, 5, nc80Texto($nombreEmpresa), 0, 'C');
$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(0, 4, nc80Texto('RUC: ' . $ruc), 0, 1, 'C');
$pdf->MultiCell(0, 4, nc80Texto($direccion), 0, 'C');
$pdf->Cell(0, 4, nc80Texto('Telf: ' . $telefono), 0, 1, 'C');

$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(2);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->Cell(0, 5, nc80Texto('NOTA DE CRÉDITO ELECTRÓNICA'), 0, 1, 'C');
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Cell(0, 5, nc80Texto($serie . ' - ' . $numero), 0, 1, 'C');
$pdf->SetFont('Helvetica', '', 7);
$pdf->Cell(
    0,
    4,
    nc80Texto('SUNAT: ' . strtoupper((string)$nota['estado_sunat'])),
    0,
    1,
    'C'
);

$pdf->Ln(2);
$pdf->SetFont('Helvetica', '', 7.5);
$pdf->MultiCell(0, 4, nc80Texto('Cliente: ' . (string)$nota['cliente_nombre']), 0, 'L');
$pdf->MultiCell(
    0,
    4,
    nc80Texto(
        'Documento: '
        . (string)$nota['cliente_tipo_documento']
        . ' '
        . (string)$nota['cliente_num_documento']
    ),
    0,
    'L'
);
$pdf->Cell(
    0,
    4,
    nc80Texto('Fecha: ' . date('d/m/Y H:i', strtotime((string)$nota['fecha_hora']))),
    0,
    1
);
$pdf->Cell(0, 4, nc80Texto('Modifica: ' . $origen), 0, 1);
$pdf->MultiCell(
    0,
    4,
    nc80Texto(
        'Motivo: '
        . (string)$nota['codigo_motivo']
        . ' - '
        . (string)$nota['motivo_descripcion']
    ),
    0,
    'L'
);
$pdf->MultiCell(0, 4, nc80Texto('Sustento: ' . (string)$nota['sustento']), 0, 'L');

$pdf->Ln(1);
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(2);
$pdf->SetFont('Helvetica', 'B', 7);
$pdf->Cell(38, 4, nc80Texto('ARTÍCULO'), 0, 0);
$pdf->Cell(8, 4, 'CANT.', 0, 0, 'R');
$pdf->Cell(14, 4, 'PRECIO', 0, 0, 'R');
$pdf->Cell(14, 4, 'TOTAL', 0, 1, 'R');
$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(2);

$pdf->SetFont('Helvetica', '', 7);
$cantidadArticulos = 0.0;

foreach ($detalles as $detalle) {
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $nombre = trim(
        (string)$detalle['codigo_articulo']
        . ' - '
        . (string)$detalle['descripcion_articulo']
    );

    $pdf->MultiCell(38, 4, nc80Texto($nombre), 0, 'L');
    $yFinal = $pdf->GetY();
    $pdf->SetXY($x + 38, $y);
    $pdf->Cell(8, 4, nc80Cantidad((float)$detalle['cantidad_nota']), 0, 0, 'R');
    $pdf->Cell(14, 4, number_format((float)$detalle['precio_unitario_con_igv'], 2), 0, 0, 'R');
    $pdf->Cell(14, 4, number_format((float)$detalle['total_linea'], 2), 0, 0, 'R');
    $pdf->SetY(max($yFinal, $y + 4) + 1);
    $cantidadArticulos += (float)$detalle['cantidad_nota'];
}

$pdf->Cell(0, 0, '', 'T');
$pdf->Ln(2);
$pdf->SetFont('Helvetica', '', 8);
$totalesTributariosNc = [
    'OP. GRAVADA' => (float)($nota['total_gravado'] ?? 0),
    'OP. EXONERADA' => (float)($nota['total_exonerado'] ?? 0),
    'OP. INAFECTA' => (float)($nota['total_inafecto'] ?? 0),
    'EXPORTACIÓN' => (float)($nota['total_exportacion'] ?? 0),
    'IGV' => (float)($nota['igv'] ?? 0),
];
foreach ($totalesTributariosNc as $etiqueta => $importe) {
    if ($importe <= 0.009 && $etiqueta !== 'IGV') continue;
    if ($etiqueta === 'IGV' && $importe <= 0.009 && (float)($nota['total_gravado'] ?? 0) <= 0.009) continue;
    $pdf->Cell(40, 5, nc80Texto($etiqueta), 0, 0);
    $pdf->Cell(34, 5, nc80Texto($simbolo . ' ' . number_format($importe, 2)), 0, 1, 'R');
}
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->Cell(40, 6, nc80Texto('TOTAL NOTA'), 0, 0);
$pdf->Cell(34, 6, nc80Texto($simbolo . ' ' . number_format((float)$nota['total_nota'], 2)), 0, 1, 'R');

if (count($pagos) > 0) {
    $pdf->Ln(1);
    $pdf->SetFont('Helvetica', 'B', 7);
    $pdf->Cell(0, 4, nc80Texto('FORMA DE DEVOLUCIÓN'), 0, 1);
    $pdf->SetFont('Helvetica', '', 7);
    foreach ($pagos as $pago) {
        $pdf->Cell(45, 4, nc80Texto((string)$pago['forma_pago']), 0, 0);
        $pdf->Cell(29, 4, nc80Texto($simbolo . ' ' . number_format((float)$pago['monto'], 2)), 0, 1, 'R');
    }
}

$pdf->Ln(2);
$pdf->SetFont('Helvetica', '', 7);
$pdf->Cell(0, 4, nc80Texto('CANT. ARTÍCULOS: ' . nc80Cantidad($cantidadArticulos)), 0, 1);

$qrRuta = sys_get_temp_dir()
    . '/nc80_'
    . preg_replace('/[^A-Za-z0-9_-]/', '_', $serie . '_' . $numero)
    . '_' . bin2hex(random_bytes(4)) . '.png';
QRcode::png($serie . '-' . $numero . '|' . $origen, $qrRuta, QR_ECLEVEL_L, 3);

if (is_file($qrRuta)) {
    $yQr = $pdf->GetY() + 2;
    $pdf->Image($qrRuta, 25, $yQr, 30, 30);
    $pdf->SetY($yQr + 32);
}

$pdf->SetFont('Helvetica', '', 7);
$pdf->MultiCell(
    0,
    3.5,
    nc80Texto(
        "Esta es una representación impresa de la\nNota de Crédito Electrónica"
    ),
    0,
    'C'
);

if (ob_get_length()) {
    ob_end_clean();
}

$pdf->Output('I', 'Nota_Credito_' . $serie . '_' . $numero . '.pdf');

if (is_file($qrRuta)) {
    @unlink($qrRuta);
}
