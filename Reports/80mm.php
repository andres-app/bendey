<?php

// ======================================================
// CONVERTIR TEXTO UTF-8 PARA LAS FUENTES BÁSICAS DE FPDF
// ======================================================
function textoPdf($texto)
{
    $texto = (string) $texto;

    $convertido = iconv(
        'UTF-8',
        'windows-1252//TRANSLIT',
        $texto
    );

    return $convertido !== false
        ? $convertido
        : $texto;
}

// ===============================
// CONVERTIR NÚMERO A LETRAS
// ===============================
function convertirNumeroALetras($numero)
{
    require_once "../Libraries/NumeroALetras.php";

    $formatter = new NumeroALetras();

    return $formatter->toWords($numero);
}

// ===============================
// FORMATEAR CANTIDAD
// ===============================
function formatearCantidad($cantidad)
{
    $cantidad = (float) $cantidad;

    if (floor($cantidad) == $cantidad) {
        return number_format($cantidad, 0);
    }

    return rtrim(
        rtrim(
            number_format($cantidad, 2, '.', ''),
            '0'
        ),
        '.'
    );
}

// Evitar que warnings o espacios previos dañen el PDF
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

if (
    !isset($_SESSION['ventas']) ||
    (int) $_SESSION['ventas'] !== 1
) {
    echo "No tiene permiso";
    exit;
}

// ===============================
// VALIDAR ID DE VENTA
// ===============================
$idVenta = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

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
$company = new Company();

// ===============================
// OBTENER CABECERA DE VENTA
// ===============================
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

// ===============================
// OBTENER DATOS DE EMPRESA
// ===============================
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
$empresa = trim(
    (string) ($empresaData['nombre'] ?? '')
);

$documento = trim(
    (string) ($empresaData['documento'] ?? '')
);

$direccion = trim(
    (string) ($empresaData['direccion'] ?? '')
);

$telefono = trim(
    (string) ($empresaData['telefono'] ?? '')
);

$ciudad = trim(
    (string) ($empresaData['ciudad'] ?? '')
);

$impuesto = trim(
    (string) ($empresaData['nombre_impuesto'] ?? 'IGV')
);

$porcIgv = (float) (
    $empresaData['monto_impuesto'] ?? 0
);

$simbolo = trim(
    (string) ($empresaData['simbolo'] ?? 'S/.')
);

$moneda = trim(
    (string) ($empresaData['moneda'] ?? 'SOLES')
);

// ===============================
// DATOS DEL COMPROBANTE
// ===============================
$tipoComprobante = trim(
    (string) (
        $reg['tipo_comprobante'] ??
        'COMPROBANTE'
    )
);

$serieComprobante = trim(
    (string) (
        $reg['serie_comprobante'] ??
        ''
    )
);

$numeroComprobante = trim(
    (string) (
        $reg['num_comprobante'] ??
        ''
    )
);

// ===============================
// NOMBRE DEL ARCHIVO PDF
// ===============================
$nombreArchivoPdf = preg_replace(
    '/[^A-Za-z0-9_\-.]/',
    '_',
    $tipoComprobante .
    '_' .
    $serieComprobante .
    '_' .
    $numeroComprobante .
    '.pdf'
);

// ===============================
// GENERAR QR
// ===============================
require_once '../Libraries/phpqrcode/qrlib.php';

$nombreQrSeguro = preg_replace(
    '/[^A-Za-z0-9_\-]/',
    '_',
    $serieComprobante .
    '_' .
    $numeroComprobante
);

$rutaQr = '../Assets/qr_' .
    $nombreQrSeguro .
    '.png';

$contenidoQr = $numeroComprobante;

/*
 * Actualmente se conserva el mismo contenido del QR
 * utilizado por el sistema: número de comprobante.
 */
QRcode::png(
    $contenidoQr,
    $rutaQr,
    QR_ECLEVEL_L,
    3
);

// ===============================
// CREAR PDF DE 80 MM
// ===============================
require_once '../Libraries/fpdf182/fpdf.php';

$pdf = new FPDF(
    'P',
    'mm',
    [80, 350]
);

$pdf->SetMargins(2, 4, 2);
$pdf->SetAutoPageBreak(true, 5);
$pdf->AddPage();

// ===============================
// CABECERA DE EMPRESA
// ===============================
$pdf->SetFont(
    'Helvetica',
    'B',
    12
);

$pdf->MultiCell(
    0,
    5,
    textoPdf($empresa),
    0,
    'C'
);

$pdf->SetFont(
    'Helvetica',
    '',
    9
);

$pdf->Cell(
    0,
    5,
    textoPdf('RUC: ' . $documento),
    0,
    1,
    'C'
);

$pdf->MultiCell(
    0,
    5,
    textoPdf('Direc: ' . $direccion),
    0,
    'C'
);

$pdf->Cell(
    0,
    5,
    textoPdf('Telf: ' . $telefono),
    0,
    1,
    'C'
);

$pdf->MultiCell(
    0,
    5,
    textoPdf($ciudad),
    0,
    'C'
);

// ===============================
// FECHA
// ===============================
$pdf->Ln(2);

$pdf->SetFont(
    'Helvetica',
    '',
    8
);

$fechaVenta = $reg['fecha'] ?? date('Y-m-d');

$fechaFormateada = date(
    'd/m/Y',
    strtotime($fechaVenta)
);

$pdf->Cell(
    0,
    5,
    textoPdf(
        'Fecha: ' .
        $fechaFormateada
    ),
    0,
    1,
    'C'
);

// ===============================
// TIPO DE COMPROBANTE
// ===============================
$pdf->Ln(2);

$pdf->SetFont(
    'Helvetica',
    'B',
    9
);

$pdf->Cell(
    0,
    5,
    textoPdf(
        mb_strtoupper(
            $tipoComprobante,
            'UTF-8'
        )
    ),
    0,
    1,
    'C'
);

// ===============================
// SERIE Y NÚMERO
// ===============================
$pdf->SetFont(
    'Helvetica',
    'B',
    8
);

$pdf->Cell(
    0,
    5,
    textoPdf(
        $serieComprobante .
        ' - ' .
        $numeroComprobante
    ),
    0,
    1,
    'C'
);

// ===============================
// CLIENTE
// ===============================
$pdf->Ln(2);

$pdf->SetFont(
    'Helvetica',
    '',
    8
);

$cliente = trim(
    (string) (
        $reg['cliente'] ??
        ''
    )
);

if ($cliente === '') {
    $cliente = 'CLIENTE VARIOS';
}

$pdf->MultiCell(
    0,
    5,
    textoPdf(
        'Cliente: ' .
        $cliente
    ),
    0,
    'L'
);

// ===============================
// USUARIO QUE ATENDIÓ
// ===============================
$usuarioAtendio = trim(
    (string) (
        $_SESSION['nombre'] ??
        ''
    )
);

$pdf->MultiCell(
    0,
    5,
    textoPdf(
        'Atendió: ' .
        $usuarioAtendio
    ),
    0,
    'L'
);

// ===============================
// LÍNEA SUPERIOR DEL DETALLE
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
$pdf->SetFont(
    'Helvetica',
    'B',
    7
);

/*
 * Ancho disponible:
 * 80 mm - 2 mm margen izquierdo - 2 mm margen derecho
 * Total: 76 mm.
 */
$pdf->Cell(
    38,
    4,
    textoPdf('ARTÍCULO'),
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
$pdf->SetFont(
    'Helvetica',
    '',
    7
);

$cantidadTotalArticulos = 0;

$detallesVenta = $venta->ventadetalles($idVenta);

if (!is_array($detallesVenta)) {
    $detallesVenta = [];
}

foreach ($detallesVenta as $detalle) {

    // ===============================
    // CONSTRUIR NOMBRE DEL ARTÍCULO
    // ===============================
    $sku = trim(
        (string) (
            $detalle['sku'] ??
            ''
        )
    );

    $articulo = trim(
        (string) (
            $detalle['articulo'] ??
            ''
        )
    );

    $partesNombre = [];

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

    // ===============================
    // DATOS NUMÉRICOS DEL PRODUCTO
    // ===============================
    $cantidadProducto = (float) (
        $detalle['cantidad'] ??
        0
    );

    $precioVenta = (float) (
        $detalle['precio_venta'] ??
        0
    );

    $subtotalProducto = (float) (
        $detalle['subtotal'] ??
        0
    );

    // ===============================
    // POSICIÓN INICIAL DE LA FILA
    // ===============================
    $xInicio = $pdf->GetX();
    $yInicio = $pdf->GetY();

    /*
     * El nombre del artículo puede ocupar varias líneas.
     * MultiCell calcula automáticamente su altura.
     */
    $pdf->SetXY(
        $xInicio,
        $yInicio
    );

    $pdf->MultiCell(
        38,
        4,
        textoPdf($nombreArticulo),
        0,
        'L'
    );

    /*
     * Se obtiene la posición final del nombre para evitar
     * que el siguiente producto o el subtotal se superpongan.
     */
    $yFinalNombre = $pdf->GetY();

    // ===============================
    // CANTIDAD, PRECIO Y TOTAL
    // ===============================
    $pdf->SetXY(
        $xInicio + 38,
        $yInicio
    );

    $pdf->Cell(
        8,
        4,
        formatearCantidad($cantidadProducto),
        0,
        0,
        'R'
    );

    $pdf->Cell(
        14,
        4,
        number_format(
            $precioVenta,
            2
        ),
        0,
        0,
        'R'
    );

    $pdf->Cell(
        16,
        4,
        number_format(
            $subtotalProducto,
            2
        ),
        0,
        0,
        'R'
    );

    // ===============================
    // FINAL REAL DE LA FILA
    // ===============================
    $alturaMinimaFila = 4;

    $yFinalFila = max(
        $yFinalNombre,
        $yInicio + $alturaMinimaFila
    );

    $pdf->SetY($yFinalFila);
    $pdf->Ln(0.8);

    $cantidadTotalArticulos += $cantidadProducto;
}

// ===============================
// TOTALES DE LA VENTA
// ===============================
$total = (float) (
    $reg['total_venta'] ??
    0
);

$descuentoTotal = (float) (
    $reg['descuento_total'] ??
    0
);

$descuentoPorcentaje = (float) (
    $reg['descuento_porcentaje'] ??
    0
);

// Subtotal antes del descuento
$subtotal = round(
    $total + $descuentoTotal,
    2
);

// Conserva el cálculo usado actualmente por el sistema
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
$pdf->SetFont(
    'Helvetica',
    '',
    8
);

$pdf->Cell(
    40,
    5,
    textoPdf('SUBTOTAL'),
    0,
    0,
    'L'
);

$pdf->Cell(
    36,
    5,
    textoPdf(
        $simbolo .
        ' ' .
        number_format(
            $subtotal,
            2
        )
    ),
    0,
    1,
    'R'
);

// ===============================
// DESCUENTO
// ===============================
if ($descuentoTotal > 0) {

    $porcentajeDescuentoTexto = rtrim(
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
        textoPdf(
            'DESCUENTO ' .
            $porcentajeDescuentoTexto .
            '%'
        ),
        0,
        0,
        'L'
    );

    $pdf->Cell(
        36,
        5,
        textoPdf(
            '- ' .
            $simbolo .
            ' ' .
            number_format(
                $descuentoTotal,
                2
            )
        ),
        0,
        1,
        'R'
    );
}

// ===============================
// IGV
// ===============================
$porcentajeIgvTexto = rtrim(
    rtrim(
        number_format(
            $porcIgv,
            2
        ),
        '0'
    ),
    '.'
);

$pdf->Cell(
    40,
    5,
    textoPdf(
        $impuesto .
        ' ' .
        $porcentajeIgvTexto .
        '%'
    ),
    0,
    0,
    'L'
);

$pdf->Cell(
    36,
    5,
    textoPdf(
        $simbolo .
        ' ' .
        number_format(
            $igv,
            2
        )
    ),
    0,
    1,
    'R'
);

// ===============================
// TOTAL
// ===============================
$pdf->SetFont(
    'Helvetica',
    'B',
    8
);

$pdf->Cell(
    40,
    6,
    textoPdf('TOTAL'),
    0,
    0,
    'L'
);

$pdf->Cell(
    36,
    6,
    textoPdf(
        $simbolo .
        ' ' .
        number_format(
            $total,
            2
        )
    ),
    0,
    1,
    'R'
);

// ======================================================
// OBTENER LOS PAGOS REGISTRADOS PARA LA VENTA
// ======================================================
$pagos = $venta->obtenerPagosVenta($idVenta);

if (!is_array($pagos)) {
    $pagos = [];
}

// ===============================
// EXTRAER NOMBRES DE PAGO
// ===============================
$nombresFormasPago = [];

foreach ($pagos as $pago) {

    /*
     * Admite distintas denominaciones de columna
     * para evitar que aparezca solamente el ID.
     */
    $nombrePago = trim(
        (string) (
            $pago['nombre'] ??
            $pago['nombre_forma_pago'] ??
            $pago['forma_pago'] ??
            ''
        )
    );

    if (
        $nombrePago !== '' &&
        !in_array(
            $nombrePago,
            $nombresFormasPago,
            true
        )
    ) {
        $nombresFormasPago[] = $nombrePago;
    }
}

// ===============================
// DETERMINAR TEXTO DE FORMA DE PAGO
// ===============================
if (count($nombresFormasPago) > 1) {

    $formaPagoTexto = 'Mixto';

} elseif (count($nombresFormasPago) === 1) {

    $formaPagoTexto = $nombresFormasPago[0];

} else {

    /*
     * Respaldo para ventas antiguas donde todavía no
     * exista información en el detalle de pagos.
     */
    $valorFormaPagoCabecera = trim(
        (string) (
            $reg['nombre_forma_pago'] ??
            $reg['forma_pago'] ??
            $reg['tipo_pago'] ??
            ''
        )
    );

    /*
     * Si la cabecera ya contiene un texto, se muestra.
     */
    if (
        $valorFormaPagoCabecera !== '' &&
        !ctype_digit($valorFormaPagoCabecera)
    ) {
        $formaPagoTexto = $valorFormaPagoCabecera;

    } else {

        /*
         * Respaldo según los IDs actuales de forma_pago.
         * La información principal sigue obteniéndose de
         * obtenerPagosVenta().
         */
        $formasPagoRespaldo = [
            1 => 'Efectivo',
            2 => 'Yape | BCP',
            3 => 'Plin',
            4 => 'Tarjeta | Izipay',
            5 => 'Izipay',
            6 => 'Mixto'
        ];

        $idFormaPagoCabecera = (int) $valorFormaPagoCabecera;

        $formaPagoTexto =
            $formasPagoRespaldo[$idFormaPagoCabecera] ??
            'No especificado';
    }
}

// ===============================
// MOSTRAR FORMA DE PAGO
// ===============================
$pdf->Ln(1);

$pdf->SetFont(
    'Helvetica',
    '',
    8
);

$pdf->MultiCell(
    0,
    5,
    textoPdf(
        'Forma de pago: ' .
        $formaPagoTexto
    ),
    0,
    'L'
);

// ===============================
// CONDICIÓN DE PAGO
// ===============================
$condicionPago = trim(
    (string) (
        $reg['condicion_pago'] ??
        'CONTADO'
    )
);

if ($condicionPago === '') {
    $condicionPago = 'CONTADO';
}

$pdf->MultiCell(
    0,
    5,
    textoPdf(
        'Condición: ' .
        ucfirst(
            strtolower($condicionPago)
        )
    ),
    0,
    'L'
);

// ===============================
// DETALLE DEL PAGO MIXTO
// ===============================
if (count($pagos) > 1) {

    $pdf->Ln(1);

    $pdf->Cell(
        0,
        0,
        '',
        'T'
    );

    $pdf->Ln(2);

    $pdf->SetFont(
        'Helvetica',
        'B',
        8
    );

    $pdf->Cell(
        0,
        5,
        textoPdf('Detalle del pago'),
        0,
        1,
        'C'
    );

    $pdf->Ln(1);

    $pdf->SetFont(
        'Helvetica',
        '',
        8
    );

    foreach ($pagos as $pago) {

        $nombrePago = trim(
            (string) (
                $pago['nombre'] ??
                $pago['nombre_forma_pago'] ??
                $pago['forma_pago'] ??
                'Pago'
            )
        );

        if ($nombrePago === '') {
            $nombrePago = 'Pago';
        }

        $montoPago = (float) (
            $pago['monto'] ??
            0
        );

        $pdf->Cell(
            40,
            5,
            textoPdf($nombrePago),
            0,
            0,
            'L'
        );

        $pdf->Cell(
            36,
            5,
            textoPdf(
                $simbolo .
                ' ' .
                number_format(
                    $montoPago,
                    2
                )
            ),
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

$pdf->SetFont(
    'Helvetica',
    '',
    7
);

$montoEnLetras = strtoupper(
    convertirNumeroALetras($total)
);

$pdf->MultiCell(
    0,
    4,
    textoPdf(
        'SON: ' .
        $montoEnLetras .
        ' ' .
        $moneda
    ),
    0,
    'L'
);

// ===============================
// CANTIDAD TOTAL DE ARTÍCULOS
// ===============================
$pdf->Cell(
    0,
    5,
    textoPdf(
        'CANT. ARTÍCULOS: ' .
        formatearCantidad(
            $cantidadTotalArticulos
        )
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

if (file_exists($rutaQr)) {
    $pdf->Image(
        $rutaQr,
        $xQr,
        $yQr,
        $qrSize,
        $qrSize
    );
}

// ===============================
// TEXTO LEGAL DEBAJO DEL QR
// ===============================
$pdf->SetY(
    $yQr + $qrSize + 2
);

$pdf->SetFont(
    'Helvetica',
    '',
    8
);

$pdf->MultiCell(
    0,
    3,
    textoPdf(
        "Este comprobante es una representación impresa\n" .
        "del Comprobante Electrónico"
    ),
    0,
    'C'
);

$pdf->Ln(1);

$pdf->SetFont(
    'Helvetica',
    'B',
    8
);

$pdf->Cell(
    0,
    4,
    textoPdf('TIQUEPOS S.A.C'),
    0,
    1,
    'C'
);

$pdf->SetFont(
    'Helvetica',
    '',
    8
);

$pdf->Cell(
    0,
    4,
    textoPdf('www.tiquepos.com'),
    0,
    1,
    'C'
);

// ===============================
// LIMPIAR CUALQUIER SALIDA PREVIA
// ===============================
if (ob_get_length()) {
    ob_end_clean();
}

// ===============================
// MOSTRAR PDF EN EL NAVEGADOR
// ===============================
$pdf->Output(
    'I',
    $nombreArchivoPdf
);

// ===============================
// ELIMINAR QR TEMPORAL
// ===============================
if (file_exists($rutaQr)) {
    unlink($rutaQr);
}