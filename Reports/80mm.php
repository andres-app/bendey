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

// Evita errores de FPDF si se genera alguna salida previa
ob_start();

// ===============================
// SESIÓN
// ===============================
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['nombre'])) {
    echo "Debe ingresar al sistema correctamente";
    exit;
}

if (!isset($_SESSION['ventas']) || (int) $_SESSION['ventas'] !== 1) {
    echo "No tiene permiso";
    exit;
}

// ===============================
// VALIDAR ID DE VENTA
// ===============================
$idVenta = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($idVenta <= 0) {
    echo "Venta inválida";
    exit;
}

// ===============================
// MODELOS
// ===============================
require_once "../Models/Sell.php";
require_once "../Models/Company.php";

$venta = new Sell();

$cabeceraVenta = $venta->ventacabecera($idVenta);

if (
    !is_array($cabeceraVenta) ||
    empty($cabeceraVenta) ||
    !isset($cabeceraVenta[0])
) {
    echo "No se encontró la venta";
    exit;
}

$reg = $cabeceraVenta[0];

$company = new Company();
$listadoEmpresa = $company->listar();

if (
    !is_array($listadoEmpresa) ||
    empty($listadoEmpresa) ||
    !isset($listadoEmpresa[0])
) {
    echo "No se encontraron los datos de la empresa";
    exit;
}

$empresaData = $listadoEmpresa[0];

// ===============================
// DATOS DE LA EMPRESA
// ===============================
$empresa = $empresaData['nombre'] ?? '';
$documento = $empresaData['documento'] ?? '';
$direccion = $empresaData['direccion'] ?? '';
$telefono = $empresaData['telefono'] ?? '';
$ciudad = $empresaData['ciudad'] ?? '';

$impuesto = $empresaData['nombre_impuesto'] ?? 'IGV';
$porcIgv = (float) ($empresaData['monto_impuesto'] ?? 0);

$simbolo = $empresaData['simbolo'] ?? 'S/.';
$moneda = $empresaData['moneda'] ?? 'SOLES';

// ===============================
// DATOS DEL COMPROBANTE
// ===============================
$tipoComprobante = $reg['tipo_comprobante'] ?? 'COMPROBANTE';
$serieComprobante = $reg['serie_comprobante'] ?? '';
$numeroComprobante = $reg['num_comprobante'] ?? '';

$nombreArchivoComprobante =
    $tipoComprobante . '_' .
    $serieComprobante . '_' .
    $numeroComprobante . '.pdf';

// ===============================
// GENERAR QR
// ===============================
include_once '../Libraries/phpqrcode/qrlib.php';

$nombreQrSeguro = preg_replace(
    '/[^A-Za-z0-9_\-]/',
    '_',
    $numeroComprobante
);

$filename = '../Assets/qr_' . $nombreQrSeguro . '.png';

QRcode::png(
    $numeroComprobante,
    $filename,
    QR_ECLEVEL_L,
    3
);

// ===============================
// CREAR PDF DE 80 MM
// ===============================
include_once '../Libraries/fpdf182/fpdf.php';

$pdf = new FPDF(
    'P',
    'mm',
    [80, 350]
);

$pdf->SetMargins(2, 4, 2);
$pdf->SetAutoPageBreak(true, 4);
$pdf->AddPage();

// ===============================
// CABECERA CENTRADA
// ===============================
$pdf->SetFont('Helvetica', 'B', 12);

$pdf->Cell(
    0,
    5,
    utf8_decode($empresa),
    0,
    1,
    'C'
);

$pdf->SetFont('Helvetica', '', 9);

$pdf->Cell(
    0,
    5,
    utf8_decode('RUC: ' . $documento),
    0,
    1,
    'C'
);

$pdf->Cell(
    0,
    5,
    utf8_decode('Direc: ' . $direccion),
    0,
    1,
    'C'
);

$pdf->Cell(
    0,
    5,
    utf8_decode('Telf: ' . $telefono),
    0,
    1,
    'C'
);

$pdf->MultiCell(
    0,
    5,
    utf8_decode($ciudad),
    0,
    'C'
);

// ===============================
// FECHA
// ===============================
$pdf->Ln(2);

$pdf->SetFont('Helvetica', '', 8);

$fechaVenta = $reg['fecha'] ?? date('Y-m-d');

$pdf->Cell(
    0,
    5,
    'Fecha: ' . date('d/m/Y', strtotime($fechaVenta)),
    0,
    1,
    'C'
);

// ===============================
// COMPROBANTE
// ===============================
$pdf->Ln(2);

$pdf->SetFont('Helvetica', 'B', 9);

$pdf->Cell(
    0,
    5,
    utf8_decode(
        mb_strtoupper(
            $tipoComprobante,
            'UTF-8'
        )
    ),
    0,
    1,
    'C'
);

$pdf->SetFont('Helvetica', 'B', 8);

$pdf->Cell(
    0,
    5,
    utf8_decode(
        $serieComprobante .
        ' - ' .
        $numeroComprobante
    ),
    0,
    1,
    'C'
);

// ===============================
// CLIENTE Y USUARIO
// ===============================
$pdf->Ln(2);

$pdf->SetFont('Helvetica', '', 8);

$cliente = trim((string) ($reg['cliente'] ?? ''));

if ($cliente === '') {
    $cliente = 'CLIENTE VARIOS';
}

$pdf->MultiCell(
    0,
    5,
    utf8_decode('Cliente: ' . $cliente),
    0,
    'L'
);

$pdf->MultiCell(
    0,
    5,
    utf8_decode(
        'Atendió: ' .
        ($_SESSION['nombre'] ?? '')
    ),
    0,
    'L'
);

// ===============================
// LÍNEA SUPERIOR
// ===============================
$pdf->Ln(1);

$pdf->Cell(
    0,
    0,
    '',
    'T'
);

$pdf->Ln(2);

// ===============================
// CABECERA DE COLUMNAS
// ===============================
$pdf->SetFont('Helvetica', 'B', 7);

// Ancho total disponible:
// 80 mm - 2 mm izquierda - 2 mm derecha = 76 mm
$pdf->Cell(
    38,
    4,
    utf8_decode('ARTÍCULO'),
    0,
    0,
    'L'
);

$pdf->Cell(
    8,
    4,
    'UND',
    0,
    0,
    'R'
);

$pdf->Cell(
    14,
    4,
    'PRECIO',
    0,
    0,
    'R'
);

$pdf->Cell(
    16,
    4,
    'TOTAL',
    0,
    1,
    'R'
);

$pdf->Cell(
    0,
    0,
    '',
    'T'
);

$pdf->Ln(2);

// ===============================
// DETALLE DE PRODUCTOS
// ===============================
$pdf->SetFont('Helvetica', '', 7);

$cantidad = 0;

$detallesVenta = $venta->ventadetalles($idVenta);

if (!is_array($detallesVenta)) {
    $detallesVenta = [];
}

foreach ($detallesVenta as $d) {

    // Construir nombre del producto
    $partesNombre = [];

    $sku = trim((string) ($d['sku'] ?? ''));
    $articulo = trim((string) ($d['articulo'] ?? ''));

    if ($sku !== '') {
        $partesNombre[] = $sku;
    }

    if ($articulo !== '') {
        $partesNombre[] = $articulo;
    }

    $nombreArticulo = implode(
        ' - ',
        $partesNombre
    );

    if ($nombreArticulo === '') {
        $nombreArticulo = 'SIN NOMBRE';
    }

    $cantidadProducto = (float) ($d['cantidad'] ?? 0);
    $precioVenta = (float) ($d['precio_venta'] ?? 0);
    $subtotalProducto = (float) ($d['subtotal'] ?? 0);

    /*
     * Guardamos la posición inicial de toda la fila.
     */
    $xInicio = $pdf->GetX();
    $yInicio = $pdf->GetY();

    /*
     * El nombre ocupa 38 mm.
     * MultiCell permite que baje a dos o más líneas.
     */
    $pdf->SetXY(
        $xInicio,
        $yInicio
    );

    $pdf->MultiCell(
        38,
        4,
        utf8_decode($nombreArticulo),
        0,
        'L'
    );

    /*
     * Guardamos la posición vertical alcanzada por
     * el nombre del artículo.
     */
    $yFinalNombre = $pdf->GetY();

    /*
     * Colocamos cantidad, precio y total en la primera
     * línea de la fila.
     */
    $pdf->SetXY(
        $xInicio + 38,
        $yInicio
    );

    $cantidadMostrada = (
        floor($cantidadProducto) == $cantidadProducto
    )
        ? number_format($cantidadProducto, 0)
        : number_format($cantidadProducto, 2);

    $pdf->Cell(
        8,
        4,
        $cantidadMostrada,
        0,
        0,
        'R'
    );

    $pdf->Cell(
        14,
        4,
        number_format($precioVenta, 2),
        0,
        0,
        'R'
    );

    $pdf->Cell(
        16,
        4,
        number_format($subtotalProducto, 2),
        0,
        0,
        'R'
    );

    /*
     * La fila debe terminar después de la última línea
     * ocupada por el nombre.
     */
    $alturaMinimaFila = 4;

    $yFinalFila = max(
        $yFinalNombre,
        $yInicio + $alturaMinimaFila
    );

    /*
     * Movemos el cursor debajo de toda la fila.
     * Esto evita que el siguiente producto o los totales
     * se superpongan con nombres largos.
     */
    $pdf->SetY($yFinalFila);
    $pdf->Ln(0.8);

    $cantidad += $cantidadProducto;
}

// ===============================
// CALCULAR TOTALES
// ===============================
$total = (float) ($reg['total_venta'] ?? 0);

$descuentoTotal = (float) (
    $reg['descuento_total'] ?? 0
);

$descuentoPorcentaje = (float) (
    $reg['descuento_porcentaje'] ?? 0
);

// Subtotal antes de aplicar el descuento
$subtotal = round(
    $total + $descuentoTotal,
    2
);

// IGV calculado según la configuración del sistema
$igv = round(
    $total * $porcIgv / 100,
    2
);

// ===============================
// LÍNEA ANTES DE TOTALES
// ===============================
$pdf->Ln(1);

$pdf->Cell(
    0,
    0,
    '',
    'T'
);

$pdf->Ln(2);

// ===============================
// SUBTOTAL
// ===============================
$pdf->SetFont('Helvetica', '', 8);

$pdf->Cell(
    40,
    5,
    utf8_decode('SUBTOTAL'),
    0,
    0,
    'L'
);

$pdf->Cell(
    36,
    5,
    $simbolo . ' ' . number_format($subtotal, 2),
    0,
    1,
    'R'
);

// ===============================
// DESCUENTO
// ===============================
if ($descuentoTotal > 0) {

    $porcentajeFormateado = rtrim(
        rtrim(
            number_format(
                $descuentoPorcentaje,
                2
            ),
            '0'
        ),
        '.'
    );

    $pdf->Cell(
        40,
        5,
        utf8_decode(
            'DESCUENTO ' .
            $porcentajeFormateado .
            '%'
        ),
        0,
        0,
        'L'
    );

    $pdf->Cell(
        36,
        5,
        '- ' .
        $simbolo .
        ' ' .
        number_format($descuentoTotal, 2),
        0,
        1,
        'R'
    );
}

// ===============================
// IGV
// ===============================
$porcentajeIgvFormateado = rtrim(
    rtrim(
        number_format($porcIgv, 2),
        '0'
    ),
    '.'
);

$pdf->Cell(
    40,
    5,
    utf8_decode(
        $impuesto .
        ' ' .
        $porcentajeIgvFormateado .
        '%'
    ),
    0,
    0,
    'L'
);

$pdf->Cell(
    36,
    5,
    $simbolo . ' ' . number_format($igv, 2),
    0,
    1,
    'R'
);

// ===============================
// TOTAL
// ===============================
$pdf->SetFont('Helvetica', 'B', 8);

$pdf->Cell(
    40,
    6,
    utf8_decode('TOTAL'),
    0,
    0,
    'L'
);

$pdf->Cell(
    36,
    6,
    $simbolo . ' ' . number_format($total, 2),
    0,
    1,
    'R'
);

// ===============================
// FORMA DE PAGO
// ===============================
$pdf->Ln(1);

$pdf->SetFont('Helvetica', '', 8);

$tipoPago = $reg['tipo_pago'] ?? '';

$pdf->MultiCell(
    0,
    5,
    utf8_decode(
        'Forma de pago: ' .
        $tipoPago
    ),
    0,
    'L'
);

// ===============================
// CONDICIÓN DE PAGO
// ===============================
$condicion = trim(
    (string) (
        $reg['condicion_pago'] ??
        'CONTADO'
    )
);

if ($condicion === '') {
    $condicion = 'CONTADO';
}

$pdf->MultiCell(
    0,
    5,
    utf8_decode(
        'Condición: ' .
        ucfirst(
            strtolower($condicion)
        )
    ),
    0,
    'L'
);

// ===============================
// DETALLE DE PAGOS MIXTOS
// ===============================
$pagos = $venta->obtenerPagosVenta($idVenta);

if (
    is_array($pagos) &&
    count($pagos) > 1
) {
    $pdf->Ln(1);

    $pdf->Cell(
        0,
        0,
        '',
        'T'
    );

    $pdf->Ln(2);

    $pdf->SetFont('Helvetica', 'B', 8);

    $pdf->Cell(
        0,
        5,
        utf8_decode('Detalle del pago'),
        0,
        1,
        'C'
    );

    $pdf->Ln(1);

    $pdf->SetFont('Helvetica', '', 8);

    foreach ($pagos as $p) {

        $nombreFormaPago = $p['nombre'] ?? 'Pago';
        $montoPago = (float) ($p['monto'] ?? 0);

        $pdf->Cell(
            40,
            5,
            utf8_decode($nombreFormaPago),
            0,
            0,
            'L'
        );

        $pdf->Cell(
            36,
            5,
            $simbolo . ' ' . number_format($montoPago, 2),
            0,
            1,
            'R'
        );
    }
}

// ===============================
// MONTO EN LETRAS
// ===============================
$pdf->Ln(2);

$pdf->SetFont('Helvetica', '', 7);

$montoLetras = strtoupper(
    convertirNumeroALetras($total)
);

$pdf->MultiCell(
    0,
    4,
    utf8_decode(
        'SON: ' .
        $montoLetras .
        ' ' .
        $moneda
    ),
    0,
    'L'
);

// ===============================
// CANTIDAD DE ARTÍCULOS
// ===============================
$cantidadMostrada = (
    floor($cantidad) == $cantidad
)
    ? number_format($cantidad, 0)
    : number_format($cantidad, 2);

$pdf->Cell(
    0,
    5,
    utf8_decode(
        'CANT. ARTÍCULOS: ' .
        $cantidadMostrada
    ),
    0,
    1,
    'L'
);

// ===============================
// QR CENTRADO
// ===============================
$qrSize = 30;
$xQr = (80 - $qrSize) / 2;

$pdf->Ln(2);

$yQr = $pdf->GetY();

if (file_exists($filename)) {
    $pdf->Image(
        $filename,
        $xQr,
        $yQr,
        $qrSize,
        $qrSize
    );
}

// ===============================
// TEXTO LEGAL
// ===============================
$pdf->SetY(
    $yQr + $qrSize + 2
);

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

$pdf->Cell(
    0,
    4,
    utf8_decode('TIQUEPOS S.A.C'),
    0,
    1,
    'C'
);

$pdf->SetFont('Helvetica', '', 8);

$pdf->Cell(
    0,
    4,
    utf8_decode('www.tiquepos.com'),
    0,
    1,
    'C'
);

// ===============================
// LIMPIAR SALIDA PREVIA
// ===============================
if (ob_get_length()) {
    ob_end_clean();
}

// ===============================
// MOSTRAR PDF
// ===============================
$pdf->Output(
    $nombreArchivoComprobante,
    'I'
);

// ===============================
// ELIMINAR QR TEMPORAL
// ===============================
if (file_exists($filename)) {
    unlink($filename);
}