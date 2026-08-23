<?php

declare(strict_types=1);

require_once __DIR__ . '/../Libraries/MediaStorage.php';

ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['nombre'])) {
    echo 'Debe ingresar al sistema correctamente';
    exit;
}

if ((int)($_SESSION['ventas'] ?? 0) !== 1) {
    echo 'No tiene permiso';
    exit;
}

$idventa = (int)($_GET['id'] ?? 0);

if ($idventa <= 0) {
    echo 'Venta inválida';
    exit;
}

require_once __DIR__ . '/../Models/Sell.php';
require_once __DIR__ . '/../Models/Company.php';
require_once __DIR__ . '/../Libraries/NumeroALetras.php';
require_once __DIR__ . '/../Libraries/fpdf182/fpdf.php';
require_once __DIR__ . '/../Libraries/phpqrcode/qrlib.php';

function ventaPdfTexto(string $texto): string
{
    $convertido = iconv(
        'UTF-8',
        'windows-1252//TRANSLIT//IGNORE',
        $texto
    );

    return $convertido !== false ? $convertido : $texto;
}

function ventaPdfCantidad(float $cantidad): string
{
    if (abs($cantidad - round($cantidad)) < 0.0001) {
        return number_format($cantidad, 0, '.', '');
    }

    return rtrim(
        rtrim(number_format($cantidad, 3, '.', ''), '0'),
        '.'
    );
}

function ventaPdfMonedaLetras(float $monto, string $moneda): string
{
    $entero = (int)floor($monto);
    $centimos = (int)round(($monto - $entero) * 100);

    if ($centimos >= 100) {
        $entero++;
        $centimos = 0;
    }

    $formatter = new NumeroALetras();
    $texto = strtoupper(trim((string)$formatter->toWords($entero)));

    return $texto
        . ' CON '
        . str_pad((string)$centimos, 2, '0', STR_PAD_LEFT)
        . '/100 '
        . strtoupper($moneda !== '' ? $moneda : 'SOLES');
}

function ventaPdfTipoComprobanteSunat(string $tipo): string
{
    $tipo = strtoupper(trim($tipo));

    if (str_contains($tipo, 'FACTURA')) {
        return '01';
    }

    if (str_contains($tipo, 'BOLETA')) {
        return '03';
    }

    return '00';
}

function ventaPdfTipoDocumentoSunat(string $tipo): string
{
    $tipo = strtoupper(trim($tipo));

    if ($tipo === 'DNI' || $tipo === '1') {
        return '1';
    }

    if ($tipo === 'RUC' || $tipo === '6') {
        return '6';
    }

    if ($tipo === 'CE' || $tipo === '4') {
        return '4';
    }

    return '0';
}


/**
 * Prepara un logo compatible con FPDF.
 *
 * @return array{ruta:string,temporal:?string}
 */
function ventaPdfPrepararLogo(array $empresa): array
{
    $directorio = tiquepos_media_dir('company') . DIRECTORY_SEPARATOR;
    $nombre = basename(
        trim((string)($empresa['logo'] ?? ''))
    );

    $ruta = $nombre !== ''
        ? $directorio . $nombre
        : '';

    $rutaDefault =
        $directorio . 'default_logo.png';

    if (
        $ruta === ''
        || !is_file($ruta)
    ) {
        $ruta = is_file($rutaDefault)
            ? $rutaDefault
            : '';
    }

    if ($ruta === '') {
        return [
            'ruta' => '',
            'temporal' => null
        ];
    }

    $extension = strtolower(
        pathinfo(
            $ruta,
            PATHINFO_EXTENSION
        )
    );

    if (
        in_array(
            $extension,
            ['png', 'jpg', 'jpeg'],
            true
        )
        && @getimagesize($ruta) !== false
    ) {
        return [
            'ruta' => $ruta,
            'temporal' => null
        ];
    }

    if (
        $extension === 'webp'
        && function_exists('imagecreatefromwebp')
        && function_exists('imagepng')
    ) {
        $imagen = @imagecreatefromwebp(
            $ruta
        );

        if ($imagen !== false) {
            $temporal =
                sys_get_temp_dir()
                . '/logo_venta_'
                . bin2hex(random_bytes(5))
                . '.png';

            imagealphablending(
                $imagen,
                false
            );

            imagesavealpha(
                $imagen,
                true
            );

            $convertido = imagepng(
                $imagen,
                $temporal,
                6
            );

            imagedestroy(
                $imagen
            );

            if (
                $convertido
                && is_file($temporal)
            ) {
                return [
                    'ruta' => $temporal,
                    'temporal' => $temporal
                ];
            }
        }
    }

    return [
        'ruta' =>
            is_file($rutaDefault)
                && @getimagesize($rutaDefault) !== false
                    ? $rutaDefault
                    : '',
        'temporal' => null
    ];
}

final class TiquePosVentaA4 extends FPDF
{
    public string $empresaCorta = '';
    public string $documentoCorto = '';

    public function Header(): void
    {
        if ($this->PageNo() <= 1) {
            return;
        }

        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(55, 61, 68);
        $this->SetXY(12, 10);
        $this->Cell(105, 5, ventaPdfTexto($this->empresaCorta), 0, 0, 'L');
        $this->SetFont('Helvetica', '', 7);
        $this->Cell(81, 5, ventaPdfTexto($this->documentoCorto . ' - continuación'), 0, 1, 'R');
        $this->SetDrawColor(205, 209, 214);
        $this->Line(12, 17, 198, 17);
        $this->SetY(21);
        $this->SetTextColor(0, 0, 0);
    }

    public function Footer(): void
    {
        // Sin pie de página por solicitud del usuario.
    }

    public function cantidadLineas(float $ancho, string $texto): int
    {
        $cw = &$this->CurrentFont['cw'];
        $wmax = ($ancho - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $texto = str_replace("\r", '', $texto);
        $nb = strlen($texto);

        if ($nb > 0 && $texto[$nb - 1] === "\n") {
            $nb--;
        }

        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;

        while ($i < $nb) {
            $c = $texto[$i];

            if ($c === "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }

            if ($c === ' ') {
                $sep = $i;
            }

            $l += $cw[$c] ?? 0;

            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }

                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }

        return $nl;
    }

    public function tituloSeccion(string $titulo): void
    {
        $this->SetFont('Helvetica', 'B', 7.4);
        $this->SetTextColor(70, 76, 83);
        $this->Cell(0, 4.5, ventaPdfTexto(strtoupper($titulo)), 0, 1, 'L');
        $this->SetDrawColor(210, 214, 218);
        $this->Line(12, $this->GetY(), 198, $this->GetY());
        $this->Ln(2.2);
        $this->SetTextColor(0, 0, 0);
    }

    public function dato(
        float $x,
        float $y,
        float $ancho,
        string $etiqueta,
        string $valor,
        float $altoValor = 4.2
    ): void {
        $this->SetXY($x, $y);
        $this->SetFont('Helvetica', 'B', 6.3);
        $this->SetTextColor(116, 121, 127);
        $this->Cell($ancho, 3.2, ventaPdfTexto(strtoupper($etiqueta)), 0, 1, 'L');
        $this->SetX($x);
        $this->SetFont('Helvetica', '', 7.7);
        $this->SetTextColor(36, 41, 46);
        $this->MultiCell(
            $ancho,
            $altoValor,
            ventaPdfTexto(trim($valor) !== '' ? $valor : '-'),
            0,
            'L'
        );
        $this->SetTextColor(0, 0, 0);
    }

    public function cabeceraTabla(): void
    {
        $anchos = [20, 92, 24, 22, 28];
        $titulos = ['CANT.', 'CÓDIGO / DESCRIPCIÓN', 'P. UNIT.', 'DESC.', 'IMPORTE'];

        $this->SetFillColor(242, 244, 246);
        $this->SetDrawColor(203, 207, 211);
        $this->SetTextColor(62, 68, 74);
        $this->SetFont('Helvetica', 'B', 6.8);

        foreach ($anchos as $indice => $ancho) {
            $alineacion = $indice === 1 ? 'L' : 'C';
            $this->Cell(
                $ancho,
                8,
                ventaPdfTexto($titulos[$indice]),
                1,
                $indice === count($anchos) - 1 ? 1 : 0,
                $alineacion,
                true
            );
        }

        $this->SetTextColor(0, 0, 0);
    }

    public function filaProducto(
        string $cantidad,
        string $codigo,
        string $descripcion,
        float $precio,
        float $descuento,
        float $importe,
        bool $alternar
    ): void {
        $anchos = [20, 92, 24, 22, 28];

        $this->SetFont('Helvetica', '', 7.1);
        $lineasDescripcion = max(
            1,
            $this->cantidadLineas(88, ventaPdfTexto($descripcion))
        );
        $alto = max(11.5, 5.4 + ($lineasDescripcion * 3.8));

        if ($this->GetY() + $alto > 262) {
            $this->AddPage();
            $this->cabeceraTabla();
        }

        $x = $this->GetX();
        $y = $this->GetY();

        if ($alternar) {
            $this->SetFillColor(250, 251, 252);
            $this->Rect($x, $y, array_sum($anchos), $alto, 'F');
        }

        $this->SetDrawColor(218, 221, 224);
        $this->Rect($x, $y, array_sum($anchos), $alto);

        $acumulado = 0.0;
        foreach (array_slice($anchos, 0, -1) as $ancho) {
            $acumulado += $ancho;
            $this->Line($x + $acumulado, $y, $x + $acumulado, $y + $alto);
        }

        $this->SetXY($x, $y);
        $this->SetFont('Helvetica', 'B', 6.8);
        $this->SetTextColor(55, 61, 68);
        $this->Cell($anchos[0], $alto, ventaPdfTexto($cantidad), 0, 0, 'C');

        $xDescripcion = $x + $anchos[0] + 2;
        $this->SetXY($xDescripcion, $y + 1.8);
        $this->SetFont('Helvetica', 'B', 6.5);
        $this->SetTextColor(103, 109, 115);
        $this->Cell($anchos[1] - 4, 3.5, ventaPdfTexto($codigo !== '' ? $codigo : 'SIN CÓDIGO'), 0, 1, 'L');
        $this->SetX($xDescripcion);
        $this->SetFont('Helvetica', '', 7.2);
        $this->SetTextColor(35, 40, 45);
        $this->MultiCell(
            $anchos[1] - 4,
            3.8,
            ventaPdfTexto($descripcion !== '' ? $descripcion : 'SIN DESCRIPCIÓN'),
            0,
            'L'
        );

        $xNumeros = $x + $anchos[0] + $anchos[1];
        $this->SetXY($xNumeros, $y);
        $this->SetFont('Helvetica', '', 7.2);
        $this->SetTextColor(35, 40, 45);
        $this->Cell($anchos[2], $alto, number_format($precio, 2), 0, 0, 'R');
        $this->Cell($anchos[3], $alto, number_format($descuento, 2), 0, 0, 'R');
        $this->SetFont('Helvetica', 'B', 7.2);
        $this->Cell($anchos[4] - 1.5, $alto, number_format($importe, 2), 0, 0, 'R');

        $this->SetTextColor(0, 0, 0);
        $this->SetY($y + $alto);
    }
}

$venta = new Sell();
$cabecera = $venta->ventacabecera($idventa);

if (!is_array($cabecera) || !isset($cabecera[0])) {
    echo 'No se encontró la venta';
    exit;
}

$reg = $cabecera[0];
$detalles = $venta->ventadetalles($idventa);
$pagos = $venta->obtenerPagosVenta($idventa);
$detalles = is_array($detalles) ? $detalles : [];
$pagos = is_array($pagos) ? $pagos : [];

$empresaListado = (new Company())->listar();
$empresa = is_array($empresaListado) && isset($empresaListado[0])
    ? $empresaListado[0]
    : [];

$nombreEmpresa = trim((string)($empresa['nombre'] ?? 'EMPRESA'));
$razonSocial = trim((string)($empresa['razon_social'] ?? $empresa['nombre_legal'] ?? ''));
$ruc = preg_replace('/\D/', '', (string)($empresa['documento'] ?? $empresa['ndocumento'] ?? ''));
$direccionEmpresa = trim((string)($empresa['direccion'] ?? ''));
$telefonoEmpresa = trim((string)($empresa['telefono'] ?? ''));
$ciudadEmpresa = trim((string)($empresa['ciudad'] ?? ''));
$emailEmpresa = trim((string)($empresa['email'] ?? ''));
$simbolo = trim((string)($empresa['simbolo'] ?? 'S/'));
$monedaNombre = trim((string)($empresa['moneda'] ?? 'SOLES'));
$porcentajeIgv = (float)($empresa['monto_impuesto'] ?? 18);
$nombreImpuesto = trim((string)($empresa['nombre_impuesto'] ?? 'IGV'));

$logoPreparado = ventaPdfPrepararLogo(
    $empresa
);

$logo = (string)(
    $logoPreparado['ruta']
    ?? ''
);

$logoTemporal = $logoPreparado['temporal']
    ?? null;

$tipoComprobanteOriginal = trim(
    (string)(
        $reg['tipo_comprobante']
        ?? 'COMPROBANTE ELECTRÓNICO'
    )
);

/*
|--------------------------------------------------------------------------
| TÍTULO TRIBUTARIO UNIFORME
|--------------------------------------------------------------------------
| strtoupper() no convierte correctamente caracteres acentuados UTF-8.
| Se usa mb_strtoupper() y luego se asigna el nombre tributario exacto.
*/
$tipoComprobante = mb_strtoupper(
    $tipoComprobanteOriginal,
    'UTF-8'
);

$tipoComprobanteNormalizado = strtr(
    $tipoComprobante,
    [
        'Á' => 'A',
        'É' => 'E',
        'Í' => 'I',
        'Ó' => 'O',
        'Ú' => 'U'
    ]
);

if (
    str_contains(
        $tipoComprobanteNormalizado,
        'BOLETA'
    )
) {
    $tipoComprobanteTitulo =
        'BOLETA DE VENTA ELECTRÓNICA';
} elseif (
    str_contains(
        $tipoComprobanteNormalizado,
        'FACTURA'
    )
) {
    $tipoComprobanteTitulo =
        'FACTURA ELECTRÓNICA';
} else {
    $tipoComprobanteTitulo =
        $tipoComprobante;
}
$serie = trim((string)($reg['serie_comprobante'] ?? ''));
$numero = trim((string)($reg['num_comprobante'] ?? ''));
$comprobante = $serie . '-' . $numero;
$fechaHora = (string)($reg['fecha_hora'] ?? $reg['fecha'] ?? date('Y-m-d H:i:s'));
$fechaEmision = date('d/m/Y H:i:s', strtotime($fechaHora));
$total = round((float)($reg['total_venta'] ?? 0), 2);
$descuentoTotal = round((float)($reg['descuento_total'] ?? 0), 2);
$subtotalAntesDescuento = round($total + $descuentoTotal, 2);
$opGravada = round((float)($reg['total_gravado'] ?? 0), 2);
$opExonerada = round((float)($reg['total_exonerado'] ?? 0), 2);
$opInafecta = round((float)($reg['total_inafecto'] ?? 0), 2);
$opExportacion = round((float)($reg['total_exportacion'] ?? 0), 2);
$igv = round((float)($reg['total_igv'] ?? 0), 2);

// Compatibilidad con ventas anteriores a la migración tributaria.
if (
    $total > 0
    && $opGravada <= 0
    && $opExonerada <= 0
    && $opInafecta <= 0
    && $opExportacion <= 0
) {
    $factorIgv = $porcentajeIgv > 0
        ? 1 + ($porcentajeIgv / 100)
        : 1;
    $opGravada = $factorIgv > 1
        ? round($total / $factorIgv, 2)
        : $total;
    $igv = round($total - $opGravada, 2);
}

$filasTributarias = [];
if ($opGravada > 0.009) {
    $filasTributarias[] = ['Operación gravada', $opGravada];
}
if ($opExonerada > 0.009) {
    $filasTributarias[] = ['Operación exonerada', $opExonerada];
}
if ($opInafecta > 0.009) {
    $filasTributarias[] = ['Operación inafecta', $opInafecta];
}
if ($opExportacion > 0.009) {
    $filasTributarias[] = ['Exportación', $opExportacion];
}
if ($igv > 0.009 || $opGravada > 0.009) {
    $filasTributarias[] = [
        $nombreImpuesto . ' ' . rtrim(rtrim(number_format($porcentajeIgv, 2, '.', ''), '0'), '.') . '%',
        $igv
    ];
}

$nombresPago = [];
foreach ($pagos as $pago) {
    $nombrePago = trim((string)(
        $pago['nombre']
        ?? $pago['nombre_forma_pago']
        ?? $pago['forma_pago']
        ?? ''
    ));

    if ($nombrePago !== '' && !in_array($nombrePago, $nombresPago, true)) {
        $nombresPago[] = $nombrePago;
    }
}

if (count($nombresPago) > 1) {
    $formaPago = 'Mixto';
} elseif (count($nombresPago) === 1) {
    $formaPago = $nombresPago[0];
} else {
    $formaPago = trim((string)(
        $reg['nombre_forma_pago']
        ?? $reg['forma_pago']
        ?? 'No especificado'
    ));
}

$condicionPago = trim((string)($reg['condicion_pago'] ?? $reg['tipo_pago'] ?? 'CONTADO'));
$usuario = trim((string)($reg['usuario'] ?? $_SESSION['nombre'] ?? ''));
$estadoDocumento = strtoupper(trim((string)($reg['estado_sunat'] ?? $reg['estado'] ?? 'ACEPTADO')));
$monedaCodigo = strtoupper(trim((string)($reg['moneda'] ?? 'PEN')));

$cantidadTotal = 0.0;
foreach ($detalles as $detalle) {
    $cantidadTotal += (float)($detalle['cantidad'] ?? 0);
}

$qrRuta = sys_get_temp_dir()
    . '/venta_a4_'
    . preg_replace('/[^A-Za-z0-9_-]/', '_', $comprobante)
    . '_'
    . bin2hex(random_bytes(4))
    . '.png';

$contenidoQr = implode('|', [
    $ruc,
    ventaPdfTipoComprobanteSunat($tipoComprobante),
    $serie,
    $numero,
    number_format($igv, 2, '.', ''),
    number_format($total, 2, '.', ''),
    date('Y-m-d', strtotime($fechaHora)),
    ventaPdfTipoDocumentoSunat((string)($reg['tipo_documento'] ?? '')),
    preg_replace('/\D/', '', (string)($reg['num_documento'] ?? '')),
]);

QRcode::png($contenidoQr, $qrRuta, QR_ECLEVEL_L, 4);

$pdf = new TiquePosVentaA4('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->empresaCorta = $nombreEmpresa;
$pdf->documentoCorto = $tipoComprobante . ' ' . $comprobante;
$pdf->SetMargins(12, 11, 12);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

// CABECERA
if ($logo !== '' && is_file($logo)) {
    $pdf->Image($logo, 12, 12, 28, 22);
}

$xEmpresa = ($logo !== '' && is_file($logo)) ? 45 : 12;
$wEmpresa = ($logo !== '' && is_file($logo)) ? 84 : 117;
$pdf->SetXY($xEmpresa, 12.5);
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->SetTextColor(37, 42, 47);
$pdf->Cell($wEmpresa, 6, ventaPdfTexto($nombreEmpresa), 0, 1, 'L');

if ($razonSocial !== '') {
    $pdf->SetX($xEmpresa);
    $pdf->SetFont('Helvetica', '', 8.2);
    $pdf->SetTextColor(82, 88, 94);
    $pdf->Cell($wEmpresa, 4.2, ventaPdfTexto($razonSocial), 0, 1, 'L');
}

$pdf->SetX($xEmpresa);
$pdf->SetFont('Helvetica', '', 7.4);
$pdf->SetTextColor(92, 98, 104);
$pdf->MultiCell(
    $wEmpresa,
    3.8,
    ventaPdfTexto($direccionEmpresa !== '' ? $direccionEmpresa : '-'),
    0,
    'L'
);

$contacto = [];
if ($telefonoEmpresa !== '') {
    $contacto[] = 'Tel. ' . $telefonoEmpresa;
}
if ($ciudadEmpresa !== '') {
    $contacto[] = $ciudadEmpresa;
}
if ($emailEmpresa !== '') {
    $contacto[] = $emailEmpresa;
}
if ($contacto) {
    $pdf->SetX($xEmpresa);
    $pdf->SetFont('Helvetica', '', 6.9);
    $pdf->MultiCell($wEmpresa, 3.5, ventaPdfTexto(implode('  |  ', $contacto)), 0, 'L');
}

$boxX = 138;
$boxY = 11;
$boxW = 60;
$boxH = 37;
$pdf->SetDrawColor(92, 98, 104);
$pdf->SetLineWidth(0.35);
$pdf->Rect($boxX, $boxY, $boxW, $boxH);
$pdf->SetXY($boxX, $boxY + 3);
$pdf->SetFont('Helvetica', 'B', 8.8);
$pdf->SetTextColor(58, 64, 70);
$pdf->Cell($boxW, 4.5, ventaPdfTexto('R.U.C. N° ' . $ruc), 0, 1, 'C');
$pdf->SetXY($boxX + 2, $boxY + 11);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->MultiCell($boxW - 4, 4.8, ventaPdfTexto($tipoComprobanteTitulo), 0, 'C');
$pdf->SetXY($boxX, $boxY + 26);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell($boxW, 5, ventaPdfTexto($comprobante), 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);

$pdf->SetDrawColor(217, 220, 223);
$pdf->Line(12, 52, 198, 52);
$pdf->SetY(58);

// TARJETAS DE INFORMACIÓN
$pdf->tituloSeccion('Datos del cliente y comprobante');
$yTarjetas = $pdf->GetY();
$altoTarjeta = 34;
$pdf->SetFillColor(249, 250, 251);
$pdf->SetDrawColor(222, 225, 228);
$pdf->Rect(12, $yTarjetas, 116, $altoTarjeta, 'DF');
$pdf->Rect(132, $yTarjetas, 66, $altoTarjeta, 'DF');

$pdf->dato(16, $yTarjetas + 4, 108, 'Nombre / Razón social', (string)($reg['cliente'] ?? 'CLIENTE VARIOS'));
$pdf->dato(
    16,
    $yTarjetas + 14,
    51,
    strtoupper((string)($reg['tipo_documento'] ?? 'Documento')),
    (string)($reg['num_documento'] ?? '-')
);
$pdf->dato(70, $yTarjetas + 14, 54, 'Dirección', (string)($reg['direccion'] ?? '-'));
$pdf->dato(136, $yTarjetas + 4, 58, 'Emisión', $fechaEmision);
$pdf->dato(136, $yTarjetas + 13, 27, 'Moneda', $monedaCodigo);
$pdf->dato(166, $yTarjetas + 13, 28, 'Condición', $condicionPago);
$pdf->dato(136, $yTarjetas + 22, 58, 'Forma de pago', $formaPago);

$pdf->SetY($yTarjetas + $altoTarjeta + 6);
$pdf->tituloSeccion('Detalle de productos');
$pdf->cabeceraTabla();

foreach ($detalles as $indice => $detalle) {
    $codigo = trim((string)($detalle['sku'] ?? $detalle['codigo'] ?? ''));
    $descripcion = trim((string)($detalle['articulo'] ?? ''));
    $unidad = trim((string)($detalle['unidad'] ?? $detalle['unidad_nombre'] ?? 'UNIDAD'));
    $cantidad = ventaPdfCantidad((float)($detalle['cantidad'] ?? 0)) . ' ' . strtoupper($unidad !== '' ? $unidad : 'UNIDAD');

    $pdf->filaProducto(
        $cantidad,
        $codigo,
        $descripcion,
        (float)($detalle['precio_unitario_con_impuesto'] ?? $detalle['precio_venta'] ?? 0),
        (float)($detalle['descuento'] ?? 0),
        (float)($detalle['subtotal'] ?? 0),
        $indice % 2 === 1
    );
}

if ($pdf->GetY() > 214) {
    $pdf->AddPage();
}

$pdf->Ln(6);
$yResumen = $pdf->GetY();
$altoResumen = max(48, 24 + count($filasTributarias) * 5 + ($descuentoTotal > 0 ? 10 : 0), count($pagos) > 1 ? 21 + count($pagos) * 5 : 42);

// RESUMEN IZQUIERDO
$pdf->SetFillColor(249, 250, 251);
$pdf->SetDrawColor(222, 225, 228);
$pdf->Rect(12, $yResumen, 108, $altoResumen, 'DF');
$pdf->SetXY(16, $yResumen + 4);
$pdf->SetFont('Helvetica', 'B', 7.1);
$pdf->SetTextColor(75, 81, 87);
$pdf->Cell(100, 4, ventaPdfTexto('RESUMEN DEL PAGO'), 0, 1, 'L');
$pdf->dato(16, $yResumen + 10, 48, 'Atendió', $usuario);
$pdf->dato(67, $yResumen + 10, 49, 'Cantidad de artículos', ventaPdfCantidad($cantidadTotal));
$pdf->dato(16, $yResumen + 21, 48, 'Forma de pago', $formaPago);
$pdf->dato(67, $yResumen + 21, 49, 'Condición', $condicionPago);

if (count($pagos) > 1) {
    $pdf->SetXY(16, $yResumen + 31);
    $pdf->SetFont('Helvetica', 'B', 6.2);
    $pdf->SetTextColor(116, 121, 127);
    $pdf->Cell(100, 3.5, ventaPdfTexto('DETALLE DEL PAGO MIXTO'), 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetTextColor(45, 50, 55);
    foreach ($pagos as $pago) {
        $nombre = trim((string)(
            $pago['nombre']
            ?? $pago['nombre_forma_pago']
            ?? $pago['forma_pago']
            ?? 'Pago'
        ));
        $pdf->SetX(16);
        $pdf->Cell(68, 4.5, ventaPdfTexto($nombre !== '' ? $nombre : 'Pago'), 0, 0, 'L');
        $pdf->Cell(32, 4.5, ventaPdfTexto($simbolo . ' ' . number_format((float)($pago['monto'] ?? 0), 2)), 0, 1, 'R');
    }
}

// TOTALES DERECHA
$xTot = 126;
$wTot = 72;
$pdf->Rect($xTot, $yResumen, $wTot, $altoResumen, 'D');
$pdf->SetXY($xTot + 4, $yResumen + 4);
$pdf->SetFont('Helvetica', 'B', 7.1);
$pdf->SetTextColor(75, 81, 87);
$pdf->Cell($wTot - 8, 4, ventaPdfTexto('TOTALES'), 0, 1, 'L');

$yLinea = $yResumen + 10;
$pdf->SetFont('Helvetica', '', 7.5);
$pdf->SetTextColor(55, 61, 67);

if ($descuentoTotal > 0) {
    $pdf->SetXY($xTot + 4, $yLinea);
    $pdf->Cell(40, 5, ventaPdfTexto('Subtotal'), 0, 0, 'L');
    $pdf->Cell(24, 5, number_format($subtotalAntesDescuento, 2), 0, 1, 'R');
    $yLinea += 5;
    $pdf->SetXY($xTot + 4, $yLinea);
    $pdf->Cell(40, 5, ventaPdfTexto('Descuento'), 0, 0, 'L');
    $pdf->Cell(24, 5, '- ' . number_format($descuentoTotal, 2), 0, 1, 'R');
    $yLinea += 5;
}

foreach ($filasTributarias as $filaTributaria) {
    $pdf->SetXY($xTot + 4, $yLinea);
    $pdf->Cell(40, 5, ventaPdfTexto((string)$filaTributaria[0]), 0, 0, 'L');
    $pdf->Cell(24, 5, number_format((float)$filaTributaria[1], 2), 0, 1, 'R');
    $yLinea += 5;
}

$yLinea += 1;
$pdf->SetDrawColor(190, 194, 198);
$pdf->Line($xTot + 4, $yLinea, $xTot + $wTot - 4, $yLinea);
$pdf->SetXY($xTot + 4, $yLinea + 2);
$pdf->SetFont('Helvetica', 'B', 9.2);
$pdf->SetTextColor(35, 40, 45);
$pdf->Cell(40, 7, ventaPdfTexto('TOTAL ' . $simbolo), 0, 0, 'L');
$pdf->Cell(24, 7, number_format($total, 2), 0, 1, 'R');

$pdf->SetTextColor(0, 0, 0);
$pdf->SetY($yResumen + $altoResumen + 5);

// IMPORTE EN LETRAS
$pdf->SetFillColor(249, 250, 251);
$pdf->SetDrawColor(222, 225, 228);
$pdf->SetFont('Helvetica', 'B', 6.4);
$pdf->SetTextColor(107, 112, 118);
$pdf->Cell(18, 8, ventaPdfTexto('SON:'), 1, 0, 'L', true);
$pdf->SetFont('Helvetica', '', 7.2);
$pdf->SetTextColor(43, 48, 53);
$pdf->MultiCell(
    168,
    4,
    ventaPdfTexto(ventaPdfMonedaLetras($total, $monedaNombre)),
    1,
    'L',
    true
);
$pdf->SetTextColor(0, 0, 0);

// QR Y TEXTO LEGAL
if ($pdf->GetY() + 38 > 276) {
    $pdf->AddPage();
}

$yQr = $pdf->GetY() + 6;
if (is_file($qrRuta)) {
    $pdf->Image($qrRuta, 12, $yQr, 27, 27);
}
$pdf->SetXY(44, $yQr + 1);
$pdf->SetFont('Helvetica', 'B', 7.3);
$pdf->SetTextColor(65, 71, 77);
$pdf->Cell(150, 4, ventaPdfTexto('Comprobante electrónico'), 0, 1, 'L');
$pdf->SetX(44);
$pdf->SetFont('Helvetica', '', 6.9);
$pdf->SetTextColor(100, 106, 112);
$pdf->MultiCell(
    150,
    3.8,
    ventaPdfTexto(
        'Representación impresa de la ' . $tipoComprobanteTitulo . '. '
        . 'Serie y número: ' . $comprobante . '. '
        . 'Consulte su validez en SUNAT o con su proveedor electrónico.'
    ),
    0,
    'L'
);

if (ob_get_length()) {
    ob_end_clean();
}

$nombreArchivo = preg_replace(
    '/[^A-Za-z0-9_.-]/',
    '_',
    $tipoComprobante . '_' . $serie . '_' . $numero . '.pdf'
);

$pdf->Output('I', $nombreArchivo);

if (is_file($qrRuta)) {
    @unlink($qrRuta);
}

if (
    is_string($logoTemporal)
    && $logoTemporal !== ''
    && is_file($logoTemporal)
) {
    @unlink($logoTemporal);
}
