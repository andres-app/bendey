<?php
//Controllers/Sell.php
require_once __DIR__ . '/../Models/Sell.php';
require_once __DIR__ . '/../Models/ConfiguracionCaja.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$sell = new Sell();
$configuracionCaja = new ConfiguracionCaja();

$op = $_GET['op'] ?? '';

$idventa = (int)($_POST['idventa'] ?? $_GET['idventa'] ?? 0);
$idusuario = (int)($_SESSION['idusuario'] ?? 0);

$modoCajaSesion = strtoupper(
    trim(
        (string)(
            $_SESSION['modo_caja']
            ?? 'LEGACY'
        )
    )
);

if (
    !in_array(
        $modoCajaSesion,
        [
            'LEGACY',
            'CAJA_UNICA',
            'MULTICAJA'
        ],
        true
    )
) {
    $modoCajaSesion = 'LEGACY';
}

/**
 * Respuesta JSON uniforme.
 */
function responderJson($data): void
{
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
    );

    exit;
}

/**
 * Genera la URL pública base del sistema.
 */
function obtenerBaseUrl(): string
{
    $https = !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';

    $protocol = $https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    $projectRoot = rtrim(
        dirname(dirname($_SERVER['PHP_SELF'] ?? '')),
        '/\\'
    ) . '/';

    return $protocol . $host . $projectRoot;
}

/**
 * Genera el estado visual real del comprobante ante SUNAT.
 * No utiliza las clases badge de Bootstrap/Stisla.
 */
function generarEstadoSunatVenta(
    array $registro
): string {
    $estado = strtoupper(
        trim(
            (string)(
                $registro['estado_sunat']
                ?? 'NO_ENVIADO'
            )
        )
    );

    $mensaje = trim(
        (string)(
            $registro['mensaje_sunat']
            ?? ''
        )
    );

    $titulo = '';

    if ($mensaje !== '') {
        $titulo = ' title="' .
            htmlspecialchars(
                $mensaje,
                ENT_QUOTES,
                'UTF-8'
            ) .
            '"';
    }

    switch ($estado) {
        case 'ACEPTADO':
            $clase = 'sunat-aceptado';
            $texto = 'Aceptado';
            break;

        case 'PENDIENTE':
        case 'EN_PROCESO':
            $clase = 'sunat-proceso';
            $texto = 'En proceso';
            break;

        case 'ENVIADO':
            $clase = 'sunat-enviado';
            $texto = 'Enviado';
            break;

        case 'RECHAZADO':
            $clase = 'sunat-rechazado';
            $texto = 'Rechazado';
            break;

        case 'EXCEPCION':
            $clase = 'sunat-rechazado';
            $texto = 'Excepción';
            break;

        case 'ERROR':
            $clase = 'sunat-error';
            $texto = 'Error';
            break;

        case 'ANULADO':
            $clase = 'sunat-anulado';
            $texto = 'Anulado';
            break;

        case 'NO_APLICA':
            $clase = 'sunat-no-aplica';
            $texto = 'No aplica';
            break;

        case 'NO_ENVIADO':
        default:
            $clase = 'sunat-pendiente';
            $texto = 'No enviado';
            break;
    }

    return '<span class="badge-sunat '
        . $clase
        . '"'
        . $titulo
        . '>'
        . $texto
        . '</span>';
}


/**
 * Estado SUNAT en texto plano para exportaciones.
 */
function obtenerEstadoSunatTexto(string $estado): string
{
    $estado = strtoupper(trim($estado));

    return match ($estado) {
        'ACEPTADO' => 'Aceptado',
        'PENDIENTE', 'EN_PROCESO' => 'En proceso',
        'ENVIADO' => 'Enviado',
        'RECHAZADO' => 'Rechazado',
        'EXCEPCION' => 'Excepción',
        'ERROR' => 'Error',
        'ANULADO' => 'Anulado',
        'NO_APLICA' => 'No aplica',
        'NO_ENVIADO' => 'No enviado',
        default => $estado !== '' ? $estado : 'No enviado',
    };
}

switch ($op) {

    // =========================================================
    // GUARDAR VENTA
    // =========================================================
    case 'guardaryeditar':

        require_once __DIR__ . '/../Models/Person.php';
        require_once __DIR__ . '/../Models/Voucher.php';
        require_once __DIR__ . '/../Models/ApiSunatEmission.php';

        if ($idusuario <= 0) {
            responderJson([
                'success' => false,
                'mensaje' => 'La sesión del usuario no es válida.'
            ]);
        }

        $idsucursalVenta = null;
        $idcajaVenta = null;
        $idaperturaVenta = null;

        $conexionVenta = $sell->getConexion();

        // Ambos modelos usan la misma conexión y transacción.
        $person = new Person($conexionVenta);
        $voucher = new Voucher($conexionVenta);

        $transaccionActiva = false;

        try {
            // =================================================
            // 1. INICIAR TRANSACCIÓN
            // =================================================
            $conexionVenta->beginTransaction();
            $transaccionActiva = true;

            // =================================================
            // 2. TIPO DE COMPROBANTE
            // =================================================
            $tipo_comprobante = trim(
                (string)($_POST['tipo_comprobante'] ?? '')
            );

            if ($tipo_comprobante === '') {
                throw new Exception(
                    'Debe seleccionar un tipo de comprobante.'
                );
            }

            /*
|--------------------------------------------------------------------------
| CONTEXTO DE SUCURSAL, CAJA Y APERTURA
|--------------------------------------------------------------------------
| LEGACY conserva los campos en NULL.
| CAJA_UNICA y MULTICAJA requieren una apertura física activa.
|--------------------------------------------------------------------------
*/
            $esCotizacion =
                stripos(
                    $tipo_comprobante,
                    'cotizacion'
                ) !== false;

            if ($modoCajaSesion !== 'LEGACY') {
                $idsucursalSesion = (int)(
                    $_SESSION['idsucursal_activa']
                    ?? 0
                );

                if ($idsucursalSesion <= 0) {
                    throw new Exception(
                        'No existe una sucursal activa en la sesión.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | VALIDAR PERMISO DEL USUARIO EN LA SUCURSAL
                |--------------------------------------------------------------------------
                */
                $permisoSucursal =
                    $configuracionCaja->obtenerPermisoSucursalUsuario(
                        $idusuario,
                        $idsucursalSesion
                    );

                if (!is_array($permisoSucursal)) {
                    throw new Exception(
                        'El usuario no está autorizado para operar en la sucursal activa.'
                    );
                }

                if (
                    (int)(
                        $permisoSucursal['puede_vender']
                        ?? 0
                    ) !== 1
                ) {
                    throw new Exception(
                        'El usuario no tiene permiso para registrar ventas en esta sucursal.'
                    );
                }

                /*
                 * Las cotizaciones pertenecen a la sucursal,
                 * pero no generan ingreso en caja.
                 */
                $idsucursalVenta = $idsucursalSesion;

                if (!$esCotizacion) {
                    $idcajaSesion = (int)(
                        $_SESSION['idcaja_activa']
                        ?? 0
                    );

                    $idaperturaSesion = (int)(
                        $_SESSION['idapertura_activa']
                        ?? 0
                    );

                    if ($idcajaSesion <= 0) {
                        throw new Exception(
                            'No existe una caja activa en la sesión.'
                        );
                    }

                    if ($idaperturaSesion <= 0) {
                        throw new Exception(
                            'Debe abrir la caja antes de registrar una venta.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | VALIDAR CAJA AUTORIZADA PARA EL USUARIO
                    |--------------------------------------------------------------------------
                    */
                    $autorizacionCaja =
                        $configuracionCaja->obtenerCajaAutorizadaUsuario(
                            $idusuario,
                            $idsucursalSesion,
                            $idcajaSesion
                        );

                    if (!is_array($autorizacionCaja)) {
                        throw new Exception(
                            'El usuario no está autorizado para operar la caja seleccionada.'
                        );
                    }

                    if (
                        (int)(
                            $autorizacionCaja['puede_operar']
                            ?? 0
                        ) !== 1
                    ) {
                        throw new Exception(
                            'El usuario no tiene permiso para operar esta caja.'
                        );
                    }

                    /*
                     * Verificar que la apertura continúa activa
                     * y corresponde a la sucursal y caja de la sesión.
                     */
                    $aperturaVenta =
                        $conexionVenta->getData(
                            "SELECT
                    idapertura,
                    idsucursal,
                    idcaja,
                    estado

                 FROM caja_apertura

                 WHERE idapertura = ?
                   AND idsucursal = ?
                   AND idcaja = ?
                   AND estado = 'ABIERTA'

                 LIMIT 1
                 FOR UPDATE",
                            [
                                $idaperturaSesion,
                                $idsucursalSesion,
                                $idcajaSesion
                            ]
                        );

                    if (!is_array($aperturaVenta)) {
                        $_SESSION['idapertura_activa'] = 0;

                        throw new Exception(
                            'La apertura de caja ya no se encuentra activa.'
                        );
                    }

                    $idcajaVenta =
                        (int)$aperturaVenta['idcaja'];

                    $idaperturaVenta =
                        (int)$aperturaVenta['idapertura'];
                }
            }

            // =================================================
            // 3. CONFIGURACIÓN TRIBUTARIA EFECTIVA
            // =================================================
            $configuracionTributariaVenta =
                $sell->obtenerConfiguracionTributariaEfectiva(
                    $idsucursalVenta
                );

            $impuesto = round(
                (float)(
                    $configuracionTributariaVenta['porcentaje_igv']
                    ?? 18.00
                ),
                2
            );

            // =================================================
            // 3.1 DATOS ADICIONALES DEL COMPROBANTE
            // =================================================
            $fechaEmision = trim(
                (string)($_POST['fecha_emision'] ?? date('Y-m-d'))
            );

            $fechaEmisionObj = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $fechaEmision,
                new DateTimeZone('America/Lima')
            );

            if (!$fechaEmisionObj || $fechaEmisionObj->format('Y-m-d') !== $fechaEmision) {
                throw new Exception('La fecha de emisión no es válida.');
            }

            $hoyLima = new DateTimeImmutable(
                'today',
                new DateTimeZone('America/Lima')
            );

            if ($fechaEmisionObj > $hoyLima) {
                throw new Exception('La fecha de emisión no puede ser futura.');
            }

            $guiaRemision = mb_substr(
                trim((string)($_POST['guia_remision'] ?? '')),
                0,
                50,
                'UTF-8'
            );

            $monedaCodigo = strtoupper(
                trim(
                    (string)(
                        $_POST['moneda_codigo']
                        ?? $configuracionTributariaVenta['moneda_codigo']
                        ?? 'PEN'
                    )
                )
            );

            if (!preg_match('/^[A-Z]{3}$/', $monedaCodigo)) {
                throw new Exception('El tipo de moneda no es válido.');
            }

            $tipoCambioSunat = round(
                (float)($_POST['tipo_cambio_sunat'] ?? 1),
                6
            );

            if ($monedaCodigo === 'PEN') {
                $tipoCambioSunat = 1.000000;
            } elseif ($tipoCambioSunat <= 0) {
                throw new Exception(
                    'Ingrese un tipo de cambio SUNAT válido para una venta en moneda extranjera.'
                );
            }

            // =================================================
            // 4. CLIENTE
            // =================================================
            $idcliente = (int)($_POST['idcliente'] ?? 0);

            $esFactura =
                stripos($tipo_comprobante, 'factura') !== false;

            $esBoleta =
                stripos($tipo_comprobante, 'boleta') !== false;

            $modoEnvio = strtolower(
                trim((string)($_POST['modo_envio'] ?? 'inmediato'))
            );

            if (!in_array(
                $modoEnvio,
                ['inmediato', 'manual', 'resumen_diario'],
                true
            )) {
                $modoEnvio = 'inmediato';
            }

            if ($modoEnvio === 'resumen_diario' && !$esBoleta) {
                throw new Exception(
                    'El Resumen Diario solo está disponible para Boleta Electrónica.'
                );
            }

            $clienteGenericoSolicitado = in_array(
                strtolower(
                    trim(
                        (string)(
                            $_POST['cliente_generico']
                            ?? '0'
                        )
                    )
                ),
                ['1', 'true', 'si', 'sí', 'on'],
                true
            );

            $tipo_documento = strtoupper(
                trim((string)($_POST['tipo_documento'] ?? ''))
            );

            /*
             * num_doc_real conserva el DNI/RUC verdadero.
             * Si está vacío, se toma el campo visible.
             */
            $numDocReal = trim(
                (string)($_POST['num_doc_real'] ?? '')
            );

            $numeroDocumentoRecibido =
                $numDocReal !== ''
                ? $numDocReal
                : ($_POST['num_documento'] ?? '');

            $num_documento = preg_replace(
                '/[^0-9A-Za-z\-]/',
                '',
                trim((string)$numeroDocumentoRecibido)
            );

            $nombre_cli = trim(
                (string)($_POST['nombre_cli'] ?? '')
            );

            $direccion = trim(
                (string)($_POST['direccion'] ?? '')
            );

            $telefono = trim(
                (string)($_POST['celular'] ?? '')
            );

            $email = trim(
                (string)($_POST['email'] ?? '')
            );

            /*
             * Si el documento queda vacío y no es factura,
             * la venta se registra automáticamente como:
             * DNI 99999999 / CLIENTE VARIOS.
             */
            if (
                !$esFactura
                && (
                    $clienteGenericoSolicitado
                    || $num_documento === ''
                )
            ) {
                $clienteGenericoSolicitado = true;
                $tipo_documento = 'DNI';
                $num_documento = '99999999';
                $nombre_cli = 'CLIENTE VARIOS';
                $direccion = '-';
                $email = '';
            }

            // Inferir DNI o RUC cuando el formulario no lo envía.
            if ($tipo_documento === '') {
                if (preg_match('/^\d{8}$/', $num_documento)) {
                    $tipo_documento = 'DNI';
                } elseif (preg_match('/^\d{11}$/', $num_documento)) {
                    $tipo_documento = 'RUC';
                }
            }

            /*
             * Si el formulario envió un idcliente,
             * se valida y se toman sus datos reales de la base.
             */
            if ($idcliente > 0) {
                $clienteExistente = $conexionVenta->getData(
                    "SELECT
                        idpersona,
                        nombre,
                        tipo_documento,
                        num_documento,
                        direccion,
                        telefono,
                        email
                     FROM persona
                     WHERE idpersona = ?
                     LIMIT 1",
                    [$idcliente]
                );

                if (!$clienteExistente) {
                    throw new Exception(
                        'El cliente seleccionado no existe.'
                    );
                }

                $tipo_documento = strtoupper(
                    trim(
                        (string)(
                            $clienteExistente['tipo_documento']
                            ?? ''
                        )
                    )
                );

                $num_documento = trim(
                    (string)(
                        $clienteExistente['num_documento']
                        ?? ''
                    )
                );

                $nombre_cli = trim(
                    (string)(
                        $clienteExistente['nombre']
                        ?? ''
                    )
                );

                $direccion = trim(
                    (string)(
                        $clienteExistente['direccion']
                        ?? $direccion
                    )
                );

                $telefono = trim(
                    (string)(
                        $clienteExistente['telefono']
                        ?? $telefono
                    )
                );

                $email = trim(
                    (string)(
                        $clienteExistente['email']
                        ?? $email
                    )
                );

                $clienteGenericoSolicitado =
                    $num_documento === '99999999';
            }

            /*
             * Factura: siempre debe tener un RUC real.
             */
            if (
                $esFactura
                && (
                    $tipo_documento !== 'RUC'
                    || !preg_match('/^\d{11}$/', $num_documento)
                    || $num_documento === '99999999'
                )
            ) {
                throw new Exception(
                    'Para emitir una factura debe seleccionar un cliente con RUC válido.'
                );
            }

            /*
             * Si no hay idcliente, buscar o crear por documento.
             */
            if ($idcliente <= 0) {
                if ($num_documento === '') {
                    throw new Exception(
                        'No se pudo determinar el cliente de la venta.'
                    );
                }

                if (
                    $tipo_documento === 'DNI'
                    && !preg_match('/^\d{8}$/', $num_documento)
                ) {
                    throw new Exception(
                        'El DNI debe tener exactamente 8 dígitos.'
                    );
                }

                if (
                    $tipo_documento === 'RUC'
                    && !preg_match('/^\d{11}$/', $num_documento)
                ) {
                    throw new Exception(
                        'El RUC debe tener exactamente 11 dígitos.'
                    );
                }

                if (
                    !$clienteGenericoSolicitado
                    && !in_array(
                        $tipo_documento,
                        ['DNI', 'RUC'],
                        true
                    )
                ) {
                    throw new Exception(
                        'Ingrese un DNI de 8 dígitos o un RUC de 11 dígitos.'
                    );
                }

                $cliente = $person->mostrarPorDocumento(
                    $num_documento
                );

                if ($cliente) {
                    $idcliente = (int)$cliente['idpersona'];
                } else {
                    if ($nombre_cli === '') {
                        throw new Exception(
                            'No se pudo determinar el nombre del cliente.'
                        );
                    }

                    $idcliente = (int)$person->insertar(
                        'Cliente',
                        $nombre_cli,
                        $tipo_documento,
                        $num_documento,
                        $direccion !== '' ? $direccion : '-',
                        $telefono,
                        $email
                    );
                }
            }

            if ($idcliente <= 0) {
                throw new Exception(
                    'No se pudo determinar el cliente de la venta.'
                );
            }

            // =================================================
            // 5. VALIDAR PRODUCTOS
            // =================================================
            $idarticulos = $_POST['idarticulo'] ?? [];
            $idingresos = $_POST['idingreso'] ?? [];
            $cantidades = $_POST['cantidad'] ?? [];
            $preciosCompra = $_POST['precio_compra'] ?? [];
            $preciosVenta = $_POST['precio_venta'] ?? [];
            $descuentosItems = $_POST['descuento'] ?? [];

            if (
                !is_array($idarticulos)
                || count($idarticulos) === 0
            ) {
                throw new Exception(
                    'Debe agregar al menos un producto antes de procesar la venta.'
                );
            }

            $cantidadProductos = count($idarticulos);

            if (
                count($cantidades) !== $cantidadProductos
                || count($preciosVenta) !== $cantidadProductos
                || count($preciosCompra) !== $cantidadProductos
            ) {
                throw new Exception(
                    'Los datos del detalle de la venta están incompletos.'
                );
            }

            if (
                !is_array($descuentosItems)
                || count($descuentosItems) !== $cantidadProductos
            ) {
                $descuentosItems = array_fill(
                    0,
                    $cantidadProductos,
                    0.00
                );
            }

            // =================================================
            // 6. PREVISUALIZAR TOTAL TRIBUTARIO SIN DESCUENTO GLOBAL
            // =================================================
            $tipoOperacionSolicitada = trim(
                (string)(
                    $_POST['tipo_operacion_sunat']
                    ?? ''
                )
            );

            $tributacionPrevia = $sell->calcularTributacionVenta(
                $idarticulos,
                $cantidades,
                $preciosVenta,
                $descuentosItems,
                0.00,
                $idsucursalVenta,
                $tipoOperacionSolicitada
            );

            $subtotal = round(
                (float)(
                    $tributacionPrevia['subtotal_documento']
                    ?? 0
                ),
                2
            );

            if ($subtotal <= 0) {
                throw new Exception(
                    'El total de los productos debe ser mayor que cero.'
                );
            }

            // =================================================
            // 7. DESCUENTO Y CÁLCULO TRIBUTARIO DEFINITIVO
            // =================================================
            $descuento_total = round(
                (float)($_POST['descuento_total'] ?? 0),
                2
            );

            $descuento_porcentaje = round(
                (float)($_POST['descuento_porcentaje'] ?? 0),
                2
            );

            $descuento_total = max($descuento_total, 0.00);
            $descuento_porcentaje = min(
                max($descuento_porcentaje, 0.00),
                100.00
            );

            if (
                $descuento_total <= 0
                && $descuento_porcentaje > 0
            ) {
                $descuento_total = round(
                    $subtotal * ($descuento_porcentaje / 100),
                    2
                );
            }

            $descuento_total = min(
                $descuento_total,
                $subtotal
            );

            if (
                $descuento_total > 0
                && $descuento_porcentaje <= 0
            ) {
                $descuento_porcentaje = round(
                    ($descuento_total / $subtotal) * 100,
                    2
                );
            }

            $tributacion = $sell->calcularTributacionVenta(
                $idarticulos,
                $cantidades,
                $preciosVenta,
                $descuentosItems,
                $descuento_total,
                $idsucursalVenta,
                $tipoOperacionSolicitada
            );

            $tributacion['moneda_codigo'] = $monedaCodigo;

            $total_venta = round(
                (float)($tributacion['total_venta'] ?? 0),
                2
            );

            $impuesto = round(
                (float)(
                    $tributacion['porcentaje_igv_predeterminado']
                    ?? $impuesto
                ),
                2
            );

            if ($total_venta <= 0) {
                throw new Exception(
                    'El total final de la venta debe ser mayor que cero.'
                );
            }

            /*
             * En una boleta, la identificación del adquirente
             * es obligatoria cuando el total supera S/ 700.
             */
            if (
                $esBoleta
                && $total_venta > 700
            ) {
                $documentoValidoBoleta =
                    (
                        $tipo_documento === 'DNI'
                        && preg_match('/^\d{8}$/', $num_documento)
                        && $num_documento !== '99999999'
                    )
                    || (
                        $tipo_documento === 'RUC'
                        && preg_match('/^\d{11}$/', $num_documento)
                    );

                if (
                    $clienteGenericoSolicitado
                    || !$documentoValidoBoleta
                ) {
                    throw new Exception(
                        'Las boletas mayores a S/ 700 deben incluir los nombres y un documento de identidad válido del cliente.'
                    );
                }
            }

            // =================================================
            // 8. FORMA Y TIPO DE PAGO
            // =================================================
            $idforma_pago = (int)(
                $_POST['idforma_pago'] ?? 0
            );

            if ($idforma_pago <= 0) {
                throw new Exception(
                    'Debe seleccionar una forma de pago.'
                );
            }

            $formaPago = $conexionVenta->getData(
                "SELECT
                    idforma_pago,
                    nombre,
                    es_efectivo,
                    es_combinado
                FROM forma_pago
                WHERE idforma_pago = ?
                AND activo = 1
                AND condicion = 1
                LIMIT 1",
                [$idforma_pago]
            );

            if (!$formaPago) {
                throw new Exception(
                    'La forma de pago seleccionada no es válida.'
                );
            }

            /*
|--------------------------------------------------------------------------
| TIPO DE PAGO: CONTADO O CRÉDITO
|--------------------------------------------------------------------------
| El formulario puede enviar:
| - Contado
| - Crédito
| - 1
| - 4
*/
            $tipo_pago = trim(
                (string)(
                    $_POST['idtipopago']
                    ?? ''
                )
            );

            if ($tipo_pago === '') {
                throw new Exception(
                    'Debe seleccionar el tipo de pago.'
                );
            }

            $tipoPagoNormalizado = mb_strtoupper(
                $tipo_pago,
                'UTF-8'
            );

            $tipoPagoNormalizado = str_replace(
                [
                    'Á',
                    'É',
                    'Í',
                    'Ó',
                    'Ú'
                ],
                [
                    'A',
                    'E',
                    'I',
                    'O',
                    'U'
                ],
                $tipoPagoNormalizado
            );

            $esCredito = (
                $tipoPagoNormalizado === '4'
                || str_contains(
                    $tipoPagoNormalizado,
                    'CREDITO'
                )
            );

            $esContado = (
                $tipoPagoNormalizado === '1'
                || str_contains(
                    $tipoPagoNormalizado,
                    'CONTADO'
                )
            );

            if (!$esCredito && !$esContado) {
                throw new Exception(
                    'El tipo de pago debe ser Contado o Crédito.'
                );
            }

            /*
|--------------------------------------------------------------------------
| DATOS DE LAS CUOTAS
|--------------------------------------------------------------------------
*/
            $numeroCuotas = 0;
            $fechaPrimeraCuotaTexto = '';
            $fechaPrimeraCuota = null;

            if ($esCredito) {
                $esFacturaCredito =
                    stripos(
                        $tipo_comprobante,
                        'factura'
                    ) !== false;

                if (!$esFacturaCredito) {
                    throw new Exception(
                        'Por ahora el pago al crédito está habilitado únicamente para facturas electrónicas.'
                    );
                }

                $numeroCuotas = (int)(
                    $_POST['numero_cuotas']
                    ?? 0
                );

                $fechaPrimeraCuotaTexto = trim(
                    (string)(
                        $_POST['fecha_pago']
                        ?? ''
                    )
                );

                if (
                    $numeroCuotas < 1
                    || $numeroCuotas > 36
                ) {
                    throw new Exception(
                        'El número de cuotas debe estar entre 1 y 36.'
                    );
                }

                if (
                    !preg_match(
                        '/^\d{4}-\d{2}-\d{2}$/',
                        $fechaPrimeraCuotaTexto
                    )
                ) {
                    throw new Exception(
                        'Debe ingresar la fecha de vencimiento de la primera cuota.'
                    );
                }

                $zonaHoraria = new DateTimeZone(
                    'America/Lima'
                );

                try {
                    $fechaPrimeraCuota =
                        new DateTimeImmutable(
                            $fechaPrimeraCuotaTexto,
                            $zonaHoraria
                        );
                } catch (Throwable $errorFecha) {
                    throw new Exception(
                        'La fecha de la primera cuota no es válida.'
                    );
                }

                if (
                    $fechaPrimeraCuota->format('Y-m-d')
                    !== $fechaPrimeraCuotaTexto
                ) {
                    throw new Exception(
                        'La fecha de la primera cuota no es válida.'
                    );
                }

                /*
|--------------------------------------------------------------------------
| VALIDAR VENCIMIENTO POSTERIOR A HOY
|--------------------------------------------------------------------------
| SUNAT no admite como vencimiento una fecha anterior
| ni igual a la fecha de emisión.
*/
                $fechaActual = new DateTimeImmutable(
                    'today',
                    $zonaHoraria
                );

                $fechaMinimaPermitida = $fechaActual->modify(
                    '+1 day'
                );

                if (
                    $fechaPrimeraCuota
                    < $fechaMinimaPermitida
                ) {
                    throw new Exception(
                        'La fecha de vencimiento de la primera cuota debe ser posterior a la fecha de hoy.'
                    );
                }
            }

            $num_transac = trim(
                (string)(
                    $_POST['num_transac']
                    ?? ''
                )
            );

            // =================================================
            // 9. OBTENER CORRELATIVO BLOQUEADO
            // =================================================
            $corr = $voucher->obtenerCorrelativoBloqueado(
                $tipo_comprobante
            );

            if (!$corr) {
                throw new Exception(
                    'No existe un correlativo activo para el comprobante seleccionado.'
                );
            }

            $serie_comprobante = trim(
                (string)$corr['serie']
            );

            $numeroActual = (int)$corr['num_comprobante'];
            $numeroSiguiente = $numeroActual + 1;

            $num_comprobante = str_pad(
                (string)$numeroSiguiente,
                8,
                '0',
                STR_PAD_LEFT
            );

            error_log(
                '[VENTA] '
                    . $tipo_comprobante
                    . ' '
                    . $serie_comprobante
                    . '-'
                    . $num_comprobante
            );

            // =================================================
            // 10. INSERTAR VENTA Y DETALLES
            // =================================================
            $datosVentaExtra = [
                'fecha_emision' => $fechaEmision,
                'guia_remision' => $guiaRemision,
                'moneda_codigo' => $monedaCodigo,
                'tipo_cambio_sunat' => $tipoCambioSunat,
                'direccion_cliente' => $direccion,
                'celular_cliente' => $telefono,
                'modo_envio_sunat' => $modoEnvio
            ];

            $idventa = $sell->insertar(
                $idcliente,
                $idusuario,
                $tipo_comprobante,
                $serie_comprobante,
                $num_comprobante,
                $impuesto,
                $total_venta,
                $descuento_total,
                $descuento_porcentaje,
                $tipo_pago,
                $num_transac,
                $idforma_pago,
                $idingresos,
                $idarticulos,
                $cantidades,
                $preciosCompra,
                $preciosVenta,
                $idsucursalVenta,
                $idcajaVenta,
                $idaperturaVenta,
                $tributacion,
                $datosVentaExtra
            );
            if (!$idventa) {
                throw new Exception(
                    'Error al registrar la venta.'
                );
            }

            // =================================================
            // 11. CRONOGRAMA DE CRÉDITO / PAGOS AL CONTADO
            // =================================================
            if ($esCredito) {
                /*
                |--------------------------------------------------------------
                | VENTA AL CRÉDITO
                |--------------------------------------------------------------
                | Una factura al crédito no representa dinero recibido en caja
                | al momento de emitirla. Por eso:
                | 1) se genera el cronograma en venta_cuota;
                | 2) NO se inserta venta_pago hasta que exista una cobranza real.
                |
                | Las cuotas se reparten en centavos para que la suma sea
                | exactamente igual al total del comprobante.
                */
                if (!$fechaPrimeraCuota instanceof DateTimeImmutable) {
                    throw new Exception(
                        'No se pudo determinar la fecha de la primera cuota.'
                    );
                }

                $totalCentavos = (int)round(
                    ((float)$total_venta) * 100
                );

                if ($totalCentavos <= 0) {
                    throw new Exception(
                        'El total de una venta al crédito debe ser mayor que cero.'
                    );
                }

                $centavosBase = intdiv(
                    $totalCentavos,
                    $numeroCuotas
                );

                if ($centavosBase <= 0) {
                    throw new Exception(
                        'El número de cuotas es demasiado alto para el total de la venta.'
                    );
                }

                $centavosAcumulados = 0;
                $diaObjetivo = (int)$fechaPrimeraCuota->format('d');
                $mesBase = new DateTimeImmutable(
                    $fechaPrimeraCuota->format('Y-m-01'),
                    new DateTimeZone('America/Lima')
                );

                for ($numeroCuota = 1; $numeroCuota <= $numeroCuotas; $numeroCuota++) {
                    $esUltima = $numeroCuota === $numeroCuotas;

                    $centavosCuota = $esUltima
                        ? ($totalCentavos - $centavosAcumulados)
                        : $centavosBase;

                    $centavosAcumulados += $centavosCuota;

                    $mesCuota = $mesBase->modify(
                        '+' . ($numeroCuota - 1) . ' month'
                    );

                    $ultimoDiaMes = (int)$mesCuota->format('t');
                    $diaCuota = min(
                        $diaObjetivo,
                        $ultimoDiaMes
                    );

                    $fechaVencimientoCuota = $mesCuota->setDate(
                        (int)$mesCuota->format('Y'),
                        (int)$mesCuota->format('m'),
                        $diaCuota
                    )->format('Y-m-d');

                    $codigoCuota = 'Cuota' . str_pad(
                        (string)$numeroCuota,
                        3,
                        '0',
                        STR_PAD_LEFT
                    );

                    $montoCuota = $centavosCuota / 100;

                    $cuotaRegistrada = $conexionVenta->setData(
                        "INSERT INTO venta_cuota
                        (
                            idventa,
                            numero_cuota,
                            codigo,
                            monto,
                            fecha_vencimiento,
                            monto_pagado,
                            fecha_pago,
                            estado
                        )
                        VALUES (?, ?, ?, ?, ?, 0.00, NULL, 'PENDIENTE')",
                        [
                            $idventa,
                            $numeroCuota,
                            $codigoCuota,
                            $montoCuota,
                            $fechaVencimientoCuota
                        ]
                    );

                    if (!$cuotaRegistrada) {
                        throw new Exception(
                            'No se pudo registrar el cronograma de cuotas de la venta.'
                        );
                    }
                }
            } else {
                /*
                |--------------------------------------------------------------
                | VENTA AL CONTADO
                |--------------------------------------------------------------
                */
                $pagosRecibidos = $_POST['pagos'] ?? [];

                $esPagoCombinado =
                    (int)($formaPago['es_combinado'] ?? 0) === 1;

                if ($esPagoCombinado) {

                    if (
                        !is_array($pagosRecibidos)
                        || count($pagosRecibidos) === 0
                    ) {
                        throw new Exception(
                            'Debe registrar las formas de pago utilizadas.'
                        );
                    }

                    $pagosAgrupados = [];

                    foreach ($pagosRecibidos as $pago) {

                        $idFormaPagoDetalle = filter_var(
                            $pago['idforma_pago'] ?? null,
                            FILTER_VALIDATE_INT
                        );

                        $montoTexto = str_replace(
                            ',',
                            '.',
                            trim((string)($pago['monto'] ?? '0'))
                        );

                        $monto = round(
                            (float)$montoTexto,
                            2
                        );

                        if (
                            (
                                $idFormaPagoDetalle === false
                                || $idFormaPagoDetalle <= 0
                            )
                            && $monto <= 0
                        ) {
                            continue;
                        }

                        if (
                            $idFormaPagoDetalle === false
                            || $idFormaPagoDetalle <= 0
                        ) {
                            throw new Exception(
                                'Debe seleccionar una forma de pago válida.'
                            );
                        }

                        if ($monto <= 0) {
                            throw new Exception(
                                'El monto de cada forma de pago debe ser mayor que cero.'
                            );
                        }

                        $formaPagoDetalle = $conexionVenta->getData(
                            "SELECT
                                idforma_pago,
                                nombre,
                                es_efectivo
                             FROM forma_pago
                             WHERE idforma_pago = ?
                               AND activo = 1
                               AND condicion = 1
                               AND es_combinado = 0
                             LIMIT 1",
                            [$idFormaPagoDetalle]
                        );

                        if (!$formaPagoDetalle) {
                            throw new Exception(
                                'Una de las formas de pago seleccionadas no está disponible.'
                            );
                        }

                        if (!isset($pagosAgrupados[$idFormaPagoDetalle])) {
                            $pagosAgrupados[$idFormaPagoDetalle] = [
                                'idforma_pago' => $idFormaPagoDetalle,
                                'nombre' => $formaPagoDetalle['nombre'],
                                'monto' => 0.00
                            ];
                        }

                        $pagosAgrupados[$idFormaPagoDetalle]['monto'] =
                            round(
                                $pagosAgrupados[$idFormaPagoDetalle]['monto']
                                    + $monto,
                                2
                            );
                    }

                    if (count($pagosAgrupados) < 2) {
                        throw new Exception(
                            'El pago mixto requiere al menos dos formas de pago diferentes.'
                        );
                    }

                    $totalPagado = 0.00;

                    foreach ($pagosAgrupados as $pagoAgrupado) {
                        $totalPagado += $pagoAgrupado['monto'];
                    }

                    $totalPagado = round(
                        $totalPagado,
                        2
                    );

                    if (
                        abs($totalPagado - $total_venta) > 0.01
                    ) {
                        throw new Exception(
                            'La suma de los pagos debe ser igual al total de la venta. '
                                . 'Total de venta: S/ '
                                . number_format($total_venta, 2)
                                . '. Total ingresado: S/ '
                                . number_format($totalPagado, 2)
                                . '.'
                        );
                    }

                    foreach ($pagosAgrupados as $pagoAgrupado) {

                        $registrado = $conexionVenta->setData(
                            "INSERT INTO venta_pago
                            (
                                idventa,
                                idforma_pago,
                                monto
                            )
                            VALUES (?, ?, ?)",
                            [
                                $idventa,
                                $pagoAgrupado['idforma_pago'],
                                $pagoAgrupado['monto']
                            ]
                        );

                        if (!$registrado) {
                            throw new Exception(
                                'No se pudo registrar el detalle de los pagos.'
                            );
                        }
                    }
                } else {

                    $registrado = $conexionVenta->setData(
                        "INSERT INTO venta_pago
                        (
                            idventa,
                            idforma_pago,
                            monto
                        )
                        VALUES (?, ?, ?)",
                        [
                            $idventa,
                            $idforma_pago,
                            $total_venta
                        ]
                    );

                    if (!$registrado) {
                        throw new Exception(
                            'No se pudo registrar el pago de la venta.'
                        );
                    }
                }
            }

            // =================================================
            // 12. ACTUALIZAR CORRELATIVO
            // =================================================
            $actualizado =
                $voucher->actualizarCorrelativoPorId(
                    $corr['id_comp_pago'],
                    $num_comprobante
                );

            if (!$actualizado) {
                throw new Exception(
                    'No se pudo actualizar el correlativo del comprobante.'
                );
            }

            // =================================================
            // 13. CONFIRMAR TRANSACCIÓN LOCAL
            // =================================================
            $conexionVenta->commit();
            $transaccionActiva = false;

            /*
                |--------------------------------------------------------------------------
                | 14. ENVÍO AUTOMÁTICO A APISUNAT
                |--------------------------------------------------------------------------
                | El envío se realiza después del COMMIT.
                | Si APISUNAT falla, la venta sigue registrada y no debe duplicarse.
                */

            $tipoNormalizado = mb_strtolower(
                trim($tipo_comprobante),
                'UTF-8'
            );

            $esFacturaElectronica =
                str_contains($tipoNormalizado, 'factura');

            $esBoletaElectronica =
                str_contains($tipoNormalizado, 'boleta');

            $esComprobanteElectronico =
                $esFacturaElectronica
                || $esBoletaElectronica;

            $resultadoSunat = [
                'aplica' => $esComprobanteElectronico,
                'intentado' => false,
                'success' => null,
                'status' => $esComprobanteElectronico
                    ? 'NO_ENVIADO'
                    : 'NO_APLICA',
                'documentId' => null,
                'mensaje' => $esComprobanteElectronico
                    ? 'El comprobante todavía no fue enviado.'
                    : 'Este documento es interno y no se envía a SUNAT.'
            ];

            /*
                |--------------------------------------------------------------------------
                | COMPROBANTE PARA ENVÍO MANUAL
                |--------------------------------------------------------------------------
                | No se llama a APISUNAT.
                | La venta queda registrada localmente con su correlativo.
                */
            if (
                $esComprobanteElectronico
                && $modoEnvio === 'manual'
            ) {
                $resultadoSunat = [
                    'aplica' => true,
                    'intentado' => false,
                    'success' => null,
                    'status' => 'NO_ENVIADO',
                    'documentId' => null,
                    'fileName' => null,
                    'production' => true,
                    'mensaje' =>
                    'Comprobante registrado para envío manual posterior.'
                ];
            }

            if (
                $esBoletaElectronica
                && $modoEnvio === 'resumen_diario'
            ) {
                $resultadoSunat = [
                    'aplica' => true,
                    'intentado' => false,
                    'success' => null,
                    'status' => 'NO_ENVIADO',
                    'documentId' => null,
                    'fileName' => null,
                    'production' => true,
                    'mensaje' =>
                    'Boleta registrada para inclusión en el Resumen Diario.'
                ];
            }

            if (
                $esComprobanteElectronico
                && $modoEnvio === 'inmediato'
            ) {
                $resultadoSunat['intentado'] = true;

                try {
                    $emisionSunat = new ApiSunatEmission();

                    $respuestaEmision =
                        $emisionSunat->enviarVenta(
                            (int)$idventa
                        );

                    $resultadoSunat = [
                        'aplica' => true,
                        'intentado' => true,
                        'success' => ($respuestaEmision['success'] ?? false)
                            === true,
                        'status' => strtoupper(
                            trim(
                                (string)(
                                    $respuestaEmision['status']
                                    ?? 'ERROR'
                                )
                            )
                        ),
                        'documentId' =>
                        $respuestaEmision['documentId']
                            ?? null,
                        'fileName' =>
                        $respuestaEmision['fileName']
                            ?? null,
                        'production' =>
                        $respuestaEmision['production']
                            ?? true,
                        'mensaje' =>
                        $respuestaEmision['mensaje']
                            ?? 'APISUNAT no devolvió un mensaje.'
                    ];
                } catch (Throwable $errorSunat) {
                    /*
         * No devolvemos success=false para la venta,
         * porque la venta local sí quedó registrada.
         */
                    error_log(
                        '[APISUNAT ENVÍO AUTOMÁTICO] Venta '
                            . $idventa
                            . ': '
                            . $errorSunat->getMessage()
                    );

                    $resultadoSunat = [
                        'aplica' => true,
                        'intentado' => true,
                        'success' => false,
                        'status' => 'ERROR',
                        'documentId' => null,
                        'mensaje' => $errorSunat->getMessage()
                    ];
                }
            }

            $mensajeRespuesta =
                'Venta registrada correctamente.';

            if (
                $esBoletaElectronica
                && $modoEnvio === 'resumen_diario'
            ) {
                $mensajeRespuesta =
                    'Venta registrada. La boleta quedó pendiente para el Resumen Diario.';
            } elseif (
                $esComprobanteElectronico
                && $modoEnvio === 'manual'
            ) {
                $mensajeRespuesta =
                    'Venta registrada. El comprobante quedó pendiente de envío manual.';
            } elseif (
                $esComprobanteElectronico
                && ($resultadoSunat['success'] ?? false) === true
            ) {
                $mensajeRespuesta =
                    'Venta registrada y enviada a APISUNAT.';
            } elseif (
                $esComprobanteElectronico
                && ($resultadoSunat['intentado'] ?? false) === true
            ) {
                $mensajeRespuesta =
                    'La venta fue registrada, pero no pudo enviarse a APISUNAT.';
            }

            responderJson([
                'success' => true,
                'idventa' => (int)$idventa,
                'tipo_comprobante' => $tipo_comprobante,
                'serie_comprobante' => $serie_comprobante,
                'num_comprobante' => $num_comprobante,
                'comprobante' =>
                $serie_comprobante
                    . '-'
                    . $num_comprobante,
                'total_venta' => $total_venta,
                'celular' => $telefono,
                'moneda_codigo' => $monedaCodigo,
                'tipo_cambio_sunat' => $tipoCambioSunat,
                'modo_envio' => $modoEnvio,
                'mensaje' => $mensajeRespuesta,
                'sunat' => $resultadoSunat
            ]);
        } catch (Throwable $e) {
            if ($transaccionActiva) {
                try {
                    $conexionVenta->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[VENTA ROLLBACK] '
                            . $rollbackError->getMessage()
                    );
                }
            }

            error_log(
                '[VENTA ERROR] ' . $e->getMessage()
            );

            responderJson([
                'success' => false,
                'mensaje' => $e->getMessage()
            ]);
        }

        break;

    // =========================================================
    // ANULACIÓN DE VENTAS DESHABILITADA
    // =========================================================
    case 'anular':

        http_response_code(403);

        responderJson([
            'success' => false,
            'mensaje' => 'La anulación de ventas no está permitida.'
        ]);

        break;

    // =========================================================
    // MOSTRAR VENTA
    // =========================================================
    case 'mostrar':

        responderJson(
            $sell->mostrar($idventa) ?: []
        );

        break;

    // =========================================================
    // PAGOS DE LA VENTA
    // =========================================================
    case 'pagos':

        $id = (int)($_GET['idventa'] ?? 0);

        responderJson(
            $sell->obtenerPagosVenta($id)
        );

        break;

    // =========================================================
    // CUOTAS DE VENTA AL CRÉDITO
    // =========================================================
    case 'cuotas':

        $id = (int)(
            $_GET['idventa']
            ?? $_POST['idventa']
            ?? 0
        );

        if ($id <= 0) {
            responderJson([]);
        }

        responderJson(
            $sell->obtenerCuotasVenta($id)
        );

        break;
    // =========================================================
    // LISTAR COTIZACIONES
    // =========================================================
    case 'listarCotizaciones':

        $rspta = $sell->listarCotizaciones();
        $data = [];
        $baseUrl = obtenerBaseUrl();

        foreach ($rspta as $reg) {
            $id = (int)$reg['idventa'];

            $data[] = [
                '0' => '
                    <div class="btn-group">
                        <button
                            class="btn btn-info btn-sm"
                            title="Ver"
                            onclick="mostrar(' . $id . ')">
                            <i class="fas fa-eye"></i>
                        </button>

                        <button
                            class="btn btn-success btn-sm"
                            title="Imprimir"
                            onclick="window.open(\'' .
                    $baseUrl .
                    'Reports/a4.php?id=' .
                    $id .
                    '\', \'_blank\')">
                            <i class="fas fa-print"></i>
                        </button>

                        <button
                            type="button"
                            class="btn btn-secondary btn-sm dropdown-toggle"
                            data-toggle="dropdown"
                            title="Más">
                            <span>...</span>
                        </button>

                        <div class="dropdown-menu">
                            <a
                                class="dropdown-item"
                                href="' .
                    $baseUrl .
                    'Reports/a4.php?id=' .
                    $id .
                    '"
                                target="_blank">
                                <i class="far fa-file-pdf"></i>
                                Imprimir A4
                            </a>

                        </div>
                    </div>
                ',
                '1' => $reg['fecha'],
                '2' => $reg['cliente'],
                '3' => $reg['usuario'],
                '4' => $reg['tipo_comprobante'],
                '5' =>
                $reg['serie_comprobante']
                    . '-'
                    . $reg['num_comprobante'],
                '6' => number_format(
                    (float)$reg['total_venta'],
                    2,
                    '.',
                    ''
                ),
                '7' =>
                $reg['estado'] === 'Aceptado'
                    ? '<div class="badge badge-success">Aceptado</div>'
                    : '<div class="badge badge-danger">Anulado</div>'
            ];
        }

        responderJson([
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ]);

        break;

    // =========================================================
    // VISTA PREVIA DE SERIE Y NÚMERO
    // No reserva el correlativo. El número definitivo se asigna
    // durante guardaryeditar con FOR UPDATE.
    // =========================================================
    case 'mostrar_serie_numero':

        $tipoComprobante = trim(
            (string)(
                $_POST['tipo_comprobante']
                ?? $_GET['tipo_comprobante']
                ?? ''
            )
        );

        if ($tipoComprobante === '') {
            responderJson([
                'serie' => '',
                'numero' => ''
            ]);
        }

        $registro = $sell->getConexion()->getData(
            "SELECT
                CONCAT(letra_serie, serie_comprobante) AS serie,
                num_comprobante
             FROM comp_pago
             WHERE nombre = ?
               AND condicion = 1
             ORDER BY id_comp_pago
             LIMIT 1",
            [$tipoComprobante]
        );

        if (!$registro) {
            responderJson([
                'serie' => '',
                'numero' => ''
            ]);
        }

        responderJson([
            'serie' => $registro['serie'],
            'numero' => str_pad(
                (string)(
                    (int)$registro['num_comprobante'] + 1
                ),
                8,
                '0',
                STR_PAD_LEFT
            )
        ]);

        break;

    // =========================================================
    // MÉTODOS ANTIGUOS DE SERIE Y NÚMERO
    // =========================================================
    case 'mostrar_numero':

        require_once __DIR__ . '/../Models/Voucher.php';

        $tipoComprobante = trim(
            (string)($_REQUEST['tipo_comprobante'] ?? '')
        );

        if ($tipoComprobante === '') {
            responderJson('00000001');
        }

        $comprobantes = new Voucher();

        $registro = $comprobantes->mostrar_numero(
            $tipoComprobante
        );

        $numeroActual = isset($registro[0]['num_comprobante'])
            ? (int)$registro[0]['num_comprobante']
            : 0;

        $nuevoNumero = $numeroActual >= 99999999
            ? '00000001'
            : $numeroActual + 1;

        responderJson($nuevoNumero);

        break;

    case 'mostrar_serie':

        require_once __DIR__ . '/../Models/Voucher.php';

        $tipoComprobante = trim(
            (string)($_REQUEST['tipo_comprobante'] ?? '')
        );

        $comprobantes = new Voucher();

        $registro = $comprobantes->mostrar_serie(
            $tipoComprobante
        );

        if (empty($registro)) {
            responderJson([
                'letra' => '',
                'serie' => ''
            ]);
        }

        $fila = $registro[0];

        $serieActual = (int)$fila['serie_comprobante'];
        $numeroActual = (int)$fila['num_comprobante'];

        if ($numeroActual >= 99999999) {
            $serieActual++;
        }

        responderJson([
            'letra' => $fila['letra_serie'],
            'serie' => $serieActual
        ]);

        break;

    // =========================================================
    // DETALLE DE VENTA EN HTML
    // =========================================================
    case 'listarDetalle':

        require_once __DIR__ . '/../Models/Company.php';

        $company = new Company();
        $negocio = $company->listar();
        $simbolo = trim((string)($negocio[0]['simbolo'] ?? 'S/'));
        $nombreImpuesto = trim((string)($negocio[0]['nombre_impuesto'] ?? 'IGV'));
        $id = (int)($_GET['id'] ?? 0);
        $detalles = $sell->listarDetalle($id);

        $simboloHtml = htmlspecialchars(
            $simbolo !== '' ? $simbolo : 'S/',
            ENT_QUOTES,
            'UTF-8'
        );

        echo '
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>SKU</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-right">Precio unitario</th>
                    <th class="text-right">Descuento</th>
                    <th class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
        ';

        if (!is_array($detalles) || count($detalles) === 0) {
            echo '
                <tr>
                    <td colspan="6" class="venta-detalle-vacio">
                        No se encontraron productos para esta venta.
                    </td>
                </tr>
                </tbody>
            ';
            break;
        }

        $subtotalProductos = 0.00;

        foreach ($detalles as $reg) {
            $cantidad = (float)($reg['cantidad'] ?? 0);
            $precioUnitario = (float)(
                $reg['precio_unitario_con_impuesto']
                ?? $reg['precio_venta']
                ?? 0
            );
            $descuentoLinea = (float)($reg['descuento'] ?? 0);
            $importeLinea = round((float)($reg['subtotal'] ?? 0), 2);
            $subtotalProductos += $importeLinea;

            $cantidadTexto = abs($cantidad - round($cantidad)) < 0.00001
                ? number_format($cantidad, 0, '.', '')
                : rtrim(rtrim(number_format($cantidad, 3, '.', ''), '0'), '.');

            $afectacion = trim((string)($reg['afectacion_descripcion'] ?? ''));
            $tasa = (float)($reg['porcentaje_igv'] ?? 0);
            if ((string)($reg['codigo_afectacion_igv'] ?? '10') === '10') {
                $afectacion = 'Gravado ' . rtrim(rtrim(number_format($tasa, 2, '.', ''), '0'), '.') . '%';
            }

            echo '
                <tr>
                    <td>
                        <div class="venta-producto-nombre">' .
                            htmlspecialchars((string)($reg['nombre'] ?? 'Producto'), ENT_QUOTES, 'UTF-8') .
                        '</div>
                        <small class="text-muted">' .
                            htmlspecialchars($afectacion, ENT_QUOTES, 'UTF-8') .
                        '</small>
                    </td>
                    <td>' .
                        htmlspecialchars(
                            (string)($reg['sku'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ) .
                    '</td>
                    <td class="text-center venta-cantidad">' . $cantidadTexto . '</td>
                    <td class="text-right">' . $simboloHtml . ' ' . number_format($precioUnitario, 2, '.', ',') . '</td>
                    <td class="text-right">' . $simboloHtml . ' ' . number_format($descuentoLinea, 2, '.', ',') . '</td>
                    <td class="text-right venta-importe">' . $simboloHtml . ' ' . number_format($importeLinea, 2, '.', ',') . '</td>
                </tr>
            ';
        }

        $cabeceraDetalle = $detalles[0];
        $totalVenta = round((float)($cabeceraDetalle['total_venta'] ?? $subtotalProductos), 2);
        $descuentoTotal = round((float)($cabeceraDetalle['descuento_total'] ?? 0), 2);
        $totalGravado = round((float)($cabeceraDetalle['total_gravado'] ?? 0), 2);
        $totalExonerado = round((float)($cabeceraDetalle['total_exonerado'] ?? 0), 2);
        $totalInafecto = round((float)($cabeceraDetalle['total_inafecto'] ?? 0), 2);
        $totalExportacion = round((float)($cabeceraDetalle['total_exportacion'] ?? 0), 2);
        $totalIgv = round((float)($cabeceraDetalle['total_igv'] ?? 0), 2);

        echo '</tbody><tfoot>';

        if ($descuentoTotal > 0.009) {
            echo '
                <tr class="venta-resumen-fila venta-resumen-descuento">
                    <th colspan="5" class="text-right">Descuento aplicado</th>
                    <th class="text-right">− ' . $simboloHtml . ' ' . number_format($descuentoTotal, 2, '.', ',') . '</th>
                </tr>
            ';
        }

        $filasTributarias = [
            'Operación gravada' => $totalGravado,
            'Operación exonerada' => $totalExonerado,
            'Operación inafecta' => $totalInafecto,
            'Exportación' => $totalExportacion,
            $nombreImpuesto => $totalIgv
        ];

        foreach ($filasTributarias as $etiqueta => $importe) {
            if ($importe <= 0.009 && $etiqueta !== $nombreImpuesto) {
                continue;
            }
            if ($etiqueta === $nombreImpuesto && $importe <= 0.009 && $totalGravado <= 0.009) {
                continue;
            }

            echo '
                <tr class="venta-resumen-fila">
                    <th colspan="5" class="text-right">' .
                        htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') .
                    '</th>
                    <th class="text-right">' . $simboloHtml . ' ' . number_format($importe, 2, '.', ',') . '</th>
                </tr>
            ';
        }

        echo '
                <tr class="venta-resumen-total">
                    <th colspan="5" class="text-right">
                        <span>Total de la venta</span>
                        <small>Resumen tributario guardado con el comprobante</small>
                    </th>
                    <th class="text-right">' . $simboloHtml . ' ' . number_format($totalVenta, 2, '.', ',') . '</th>
                </tr>
            </tfoot>
        ';

        break;

    // =========================================================
    // PREPARAR DUPLICACIÓN COMO NUEVA VENTA
    // =========================================================
    case 'duplicar':

        if (
            !isset($_SESSION['nombre'])
            || (int)($_SESSION['ventas'] ?? 0) !== 1
        ) {
            responderJson([
                'success' => false,
                'mensaje' => 'Acceso no autorizado.'
            ]);
        }

        $idOrigen = (int)(
            $_GET['idventa']
            ?? $_POST['idventa']
            ?? 0
        );

        if ($idOrigen <= 0) {
            responderJson([
                'success' => false,
                'mensaje' => 'El comprobante de origen no es válido.'
            ]);
        }

        $plantilla = $sell->obtenerDatosDuplicacion(
            $idOrigen
        );

        if (!is_array($plantilla)) {
            responderJson([
                'success' => false,
                'mensaje' => 'No se encontró el comprobante que desea duplicar.'
            ]);
        }

        $cabecera = is_array(
            $plantilla['cabecera'] ?? null
        )
            ? $plantilla['cabecera']
            : [];

        $detallesOriginales = is_array(
            $plantilla['detalles'] ?? null
        )
            ? $plantilla['detalles']
            : [];

        $tipoComprobanteOrigen = trim(
            (string)(
                $cabecera['tipo_comprobante']
                ?? ''
            )
        );

        $tipoNormalizadoOrigen = mb_strtoupper(
            $tipoComprobanteOrigen,
            'UTF-8'
        );

        if (
            !str_contains($tipoNormalizadoOrigen, 'FACTURA')
            && !str_contains($tipoNormalizadoOrigen, 'BOLETA')
        ) {
            responderJson([
                'success' => false,
                'mensaje' => 'Solo se pueden duplicar facturas o boletas.'
            ]);
        }

        $productos = [];
        $advertencias = [];

        foreach ($detallesOriginales as $detalle) {
            $idArticulo = (int)(
                $detalle['idarticulo']
                ?? 0
            );

            $cantidadOriginal = max(
                0,
                (int)round(
                    (float)(
                        $detalle['cantidad']
                        ?? 0
                    )
                )
            );

            $stockDisponible = max(
                0,
                (int)floor(
                    (float)(
                        $detalle['stock_disponible']
                        ?? 0
                    )
                )
            );

            $articuloActivo = (int)(
                $detalle['articulo_activo']
                ?? 0
            ) === 1;

            $cantidadCargar = min(
                $cantidadOriginal,
                $stockDisponible
            );

            $nombreArticulo = trim(
                (string)(
                    $detalle['articulo']
                    ?? 'Producto'
                )
            );

            $puedeCargar = (
                $idArticulo > 0
                && $articuloActivo
                && $stockDisponible > 0
                && $cantidadCargar > 0
            );

            if (!$articuloActivo) {
                $advertencias[] =
                    $nombreArticulo
                    . ': el producto está inactivo y no fue agregado.';
            } elseif ($stockDisponible <= 0) {
                $advertencias[] =
                    $nombreArticulo
                    . ': no tiene stock disponible y no fue agregado.';
            } elseif ($cantidadCargar < $cantidadOriginal) {
                $advertencias[] =
                    $nombreArticulo
                    . ': la venta original tenía '
                    . $cantidadOriginal
                    . ', pero se cargaron '
                    . $cantidadCargar
                    . ' por el stock disponible actual.';
            }

            $productos[] = [
                'idingreso' => (int)(
                    $detalle['idingreso']
                    ?? 0
                ),
                'idarticulo' => $idArticulo,
                'codigo' => trim(
                    (string)(
                        $detalle['codigo']
                        ?? ''
                    )
                ),
                'articulo' => $nombreArticulo,
                'precio_compra' => round(
                    (float)(
                        $detalle['precio_compra_actual']
                        ?? $detalle['precio_compra_original']
                        ?? 0
                    ),
                    2
                ),
                'precio_venta' => round(
                    (float)(
                        $detalle['precio_venta']
                        ?? 0
                    ),
                    2
                ),
                'cantidad_original' => $cantidadOriginal,
                'cantidad_cargar' => $cantidadCargar,
                'stock' => $stockDisponible,
                'puede_cargar' => $puedeCargar
            ];
        }

        $idFormaPagoOrigen = (int)(
            $cabecera['idforma_pago']
            ?? 0
        );

        if ($idFormaPagoOrigen > 0) {
            $formaPagoOrigen = $sell->getConexion()->getData(
                "SELECT
                    nombre,
                    es_combinado
                 FROM forma_pago
                 WHERE idforma_pago = ?
                 LIMIT 1",
                [$idFormaPagoOrigen]
            );

            if (
                is_array($formaPagoOrigen)
                && (int)(
                    $formaPagoOrigen['es_combinado']
                    ?? 0
                ) === 1
            ) {
                $advertencias[] =
                    'La venta original usó pago mixto. Debe volver a distribuir los importes antes de procesar.';
            }
        }

        responderJson([
            'success' => true,
            'mensaje' =>
                'El comprobante fue cargado como plantilla editable.',
            'origen' => [
                'idventa' => (int)(
                    $cabecera['idventa']
                    ?? 0
                ),
                'comprobante' => trim(
                    (string)(
                        $cabecera['serie_comprobante']
                        ?? ''
                    )
                )
                    . '-'
                    . trim(
                        (string)(
                            $cabecera['num_comprobante']
                            ?? ''
                        )
                    )
            ],
            'venta' => [
                'tipo_comprobante' => $tipoComprobanteOrigen,
                'tipo_pago' => trim(
                    (string)(
                        $cabecera['tipo_pago']
                        ?? ''
                    )
                ),
                'idforma_pago' => $idFormaPagoOrigen,
                'descuento_total' => round(
                    (float)(
                        $cabecera['descuento_total']
                        ?? 0
                    ),
                    2
                ),
                'descuento_porcentaje' => round(
                    (float)(
                        $cabecera['descuento_porcentaje']
                        ?? 0
                    ),
                    2
                ),
                'numero_cuotas' => (int)(
                    $cabecera['numero_cuotas']
                    ?? 0
                )
            ],
            'cliente' => [
                'idcliente' => (int)(
                    $cabecera['idcliente']
                    ?? 0
                ),
                'tipo_documento' => trim(
                    (string)(
                        $cabecera['tipo_documento']
                        ?? ''
                    )
                ),
                'num_documento' => trim(
                    (string)(
                        $cabecera['num_documento']
                        ?? ''
                    )
                ),
                'nombre' => trim(
                    (string)(
                        $cabecera['cliente']
                        ?? ''
                    )
                ),
                'direccion' => trim(
                    (string)(
                        $cabecera['direccion']
                        ?? ''
                    )
                ),
                'telefono' => trim(
                    (string)(
                        $cabecera['telefono']
                        ?? ''
                    )
                ),
                'email' => trim(
                    (string)(
                        $cabecera['email']
                        ?? ''
                    )
                )
            ],
            'productos' => $productos,
            'advertencias' => $advertencias
        ]);

        break;

    // =========================================================
    // DETALLE PARA EDICIÓN
    // =========================================================
    case 'listarDetalle_editar':

        $id = (int)($_GET['id'] ?? 0);
        $rspta = $sell->listarDetalle($id);
        $data = [];

        foreach ($rspta as $reg) {
            $data[] = [
                'Idingreso' => $reg['idarticulo'],
                'Idarticulo' => $reg['idarticulo'],
                'Articulo' => $reg['nombre'],
                'Pcompra' => $reg['precio_compra'],
                'Pventa' => $reg['precio_venta'],
                'Cantidad' => $reg['cantidad'],
                'Stock' => $reg['stock']
            ];
        }

        responderJson($data);

        break;

    // =========================================================
    // LISTAR VENTAS
    // =========================================================
    case 'listar':

        $rspta = $sell->listar();
        $data = [];
        $baseUrl = obtenerBaseUrl();

        foreach ($rspta as $reg) {
            $id = (int)$reg['idventa'];

            $whatsappTexto = urlencode(
                'Detalle de la venta: '
                    . $id
                    . ' - Ver PDF: '
                    . $baseUrl
                    . 'Reports/a4.php?id='
                    . $id
            );

            $tipoComprobanteVenta = trim(
                (string)($reg['tipo_comprobante'] ?? '')
            );

            $estadoVenta = trim(
                (string)($reg['estado'] ?? '')
            );

            $estadoSunatVenta = strtoupper(
                trim((string)($reg['estado_sunat'] ?? ''))
            );

            $totalVenta = round(
                (float)($reg['total_venta'] ?? 0),
                2
            );

            $totalNotas = round(
                (float)($reg['total_notas_credito'] ?? 0),
                2
            );

            $saldoNota = max(
                round($totalVenta - $totalNotas, 2),
                0.00
            );

            $esComprobanteElectronico = in_array(
                $tipoComprobanteVenta,
                ['Factura Electrónica', 'Boleta Electrónica'],
                true
            );

            $puedeGenerarNota =
                $esComprobanteElectronico
                && $estadoVenta === 'Aceptado'
                && $estadoSunatVenta === 'ACEPTADO'
                && $saldoNota > 0.009;

            if ($puedeGenerarNota) {
                $accionNotaCredito = '
                    <button
                        type="button"
                        class="dropdown-item"
                        onclick="generarNotaCredito(' . $id . ')">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Generar nota de crédito</span>
                    </button>
                ';
            } else {
                if (!$esComprobanteElectronico) {
                    $motivoNotaBloqueada =
                        'Disponible solo para facturas y boletas electrónicas.';
                } elseif ($estadoVenta !== 'Aceptado') {
                    $motivoNotaBloqueada =
                        'La venta original no se encuentra activa.';
                } elseif ($estadoSunatVenta !== 'ACEPTADO') {
                    $motivoNotaBloqueada =
                        'El comprobante debe estar aceptado por SUNAT.';
                } else {
                    $motivoNotaBloqueada =
                        'La venta ya no tiene saldo disponible para acreditar.';
                }

                $accionNotaCredito = '
                    <button
                        type="button"
                        class="dropdown-item text-muted"
                        title="' . htmlspecialchars(
                            $motivoNotaBloqueada,
                            ENT_QUOTES,
                            'UTF-8'
                        ) . '"
                        disabled>
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Generar nota de crédito</span>
                    </button>
                ';
            }

            $serieNumero = trim(
                (string)($reg['serie_comprobante'] ?? '')
            )
                . '-'
                . trim(
                    (string)($reg['num_comprobante'] ?? '')
                );

            $comprobanteHtml =
                '<div class="venta-numero-documento">'
                . '<strong>'
                . htmlspecialchars(
                    $tipoComprobanteVenta
                    . ' / '
                    . $serieNumero,
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</strong>';

            $cantidadNotas = (int)(
                $reg['cantidad_notas_credito']
                ?? 0
            );

            if ($cantidadNotas > 0 && $totalNotas > 0) {
                $textoCantidad =
                    $cantidadNotas === 1
                        ? '1 nota de crédito'
                        : $cantidadNotas . ' notas de crédito';

                $comprobanteHtml .= '
                    <span
                        class="venta-nota-badge"
                        title="' . htmlspecialchars(
                            $textoCantidad,
                            ENT_QUOTES,
                            'UTF-8'
                        ) . '">
                        <i class="fas fa-file-invoice-dollar"></i>
                        ' . $cantidadNotas . ' N.C. · S/ '
                        . number_format(
                            $totalNotas,
                            2,
                            '.',
                            ''
                        ) . '
                    </span>
                ';
            }

            $comprobanteHtml .= '</div>';

            $documentoCliente = trim(
                (string)($reg['num_documento'] ?? '')
            );

            $nombreCliente = trim(
                (string)($reg['cliente'] ?? 'SIN CLIENTE')
            );

            $clienteTexto =
                $documentoCliente !== ''
                    ? $documentoCliente . ' / ' . $nombreCliente
                    : $nombreCliente;

            $metodoPago = trim(
                (string)($reg['metodo_pago'] ?? '')
            );

            if ($metodoPago === '') {
                $metodoPago = 'No especificado';
            }

            $data[] = [
                '0' =>
                    '<span class="venta-id-export d-none" data-idventa="'
                    . $id
                    . '"></span>'
                    . htmlspecialchars(
                        (string)($reg['fecha'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ),

                '1' => $comprobanteHtml,

                '2' => htmlspecialchars(
                    $clienteTexto,
                    ENT_QUOTES,
                    'UTF-8'
                ),

                '3' => htmlspecialchars(
                    (string)($reg['usuario'] ?? 'SIN USUARIO'),
                    ENT_QUOTES,
                    'UTF-8'
                ),

                '4' => htmlspecialchars(
                    $metodoPago,
                    ENT_QUOTES,
                    'UTF-8'
                ),

                '5' => 'S/ ' . number_format(
                    $totalVenta,
                    2,
                    '.',
                    ''
                ),

                '6' => generarEstadoSunatVenta(
                    $reg
                ),

                '7' => '
                    <button
                        type="button"
                        class="btn btn-sm venta-ver-detalle-btn"
                        onclick="mostrar(' . $id . ')"
                        title="Ver detalles"
                        aria-label="Ver detalles de la venta">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                ',

                '8' => '
                    <div class="dropdown venta-acciones">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary dropdown-toggle venta-acciones-boton"
                            data-toggle="dropdown"
                            aria-haspopup="true"
                            aria-expanded="false"
                            title="Abrir acciones de la venta">

                            <i class="fas fa-ellipsis-h mr-1"></i>
                            <span class="texto-accion">Acciones</span>
                        </button>

                        <div class="dropdown-menu dropdown-menu-right venta-acciones-menu">
                            <h6 class="dropdown-header">Venta</h6>

                            <button
                                type="button"
                                class="dropdown-item"
                                onclick="duplicarVenta(' . $id . ')">
                                <i class="far fa-copy"></i>
                                <span>Duplicar venta</span>
                            </button>

                            ' . $accionNotaCredito . '

                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header">Comprobante</h6>

                            <a
                                class="dropdown-item"
                                href="' . $baseUrl . 'Reports/80mm.php?id=' . $id . '"
                                target="_blank"
                                rel="noopener">
                                <i class="fas fa-receipt"></i>
                                <span>Imprimir ticket</span>
                            </a>

                            <a
                                class="dropdown-item"
                                href="' . $baseUrl . 'Reports/a4.php?id=' . $id . '"
                                target="_blank"
                                rel="noopener">
                                <i class="far fa-file-pdf"></i>
                                <span>Imprimir A4</span>
                            </a>

                            <a
                                class="dropdown-item"
                                href="https://wa.me/?text=' . $whatsappTexto . '"
                                target="_blank"
                                rel="noopener">
                                <i class="far fa-comment-dots"></i>
                                <span>Compartir por WhatsApp</span>
                            </a>
                        </div>
                    </div>
                '
            ];
        }

        responderJson([
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ]);

        break;

    // =========================================================
    // REPORTE DETALLADO DE VENTAS
    // =========================================================
    case 'reporteVentas':

        $idsRecibidos = $_POST['ids'] ?? [];

        if (!is_array($idsRecibidos)) {
            $idsRecibidos = [];
        }

        $idsVenta = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $idsRecibidos),
                    static fn(int $id): bool => $id > 0
                )
            )
        );

        if (count($idsVenta) === 0) {
            responderJson([]);
        }

        $registrosReporte =
            $sell->listarReporteVentasDetallado(
                $idsVenta
            );

        $reporte = [];

        foreach ($registrosReporte as $reg) {
            $tipoComprobante = trim(
                (string)($reg['tipo_comprobante'] ?? '')
            );

            $serieNumero = trim(
                (string)($reg['serie_comprobante'] ?? '')
            )
                . '-'
                . trim(
                    (string)($reg['num_comprobante'] ?? '')
                );

            $documentoCliente = trim(
                (string)($reg['num_documento'] ?? '')
            );

            $nombreCliente = trim(
                (string)($reg['cliente'] ?? 'SIN CLIENTE')
            );

            $clienteTexto =
                $documentoCliente !== ''
                    ? $documentoCliente . ' / ' . $nombreCliente
                    : $nombreCliente;

            $cantidad = (float)($reg['cantidad'] ?? 0);

            $cantidadTexto =
                abs($cantidad - round($cantidad)) < 0.00001
                    ? number_format($cantidad, 0, '.', '')
                    : rtrim(
                        rtrim(
                            number_format(
                                $cantidad,
                                3,
                                '.',
                                ''
                            ),
                            '0'
                        ),
                        '.'
                    );

            $reporte[] = [
                'fecha' => (string)($reg['fecha'] ?? ''),

                'comprobante' =>
                    $tipoComprobante
                    . ' / '
                    . $serieNumero,

                'cliente' => $clienteTexto,

                'sku' => trim(
                    (string)($reg['sku'] ?? '')
                ),

                'producto' => trim(
                    (string)($reg['producto'] ?? 'Producto')
                ),

                'cantidad' => $cantidadTexto,

                'precio' => 'S/ ' . number_format(
                    (float)($reg['precio'] ?? 0),
                    2,
                    '.',
                    ''
                ),

                'metodo_pago' => trim(
                    (string)(
                        $reg['metodo_pago']
                        ?? 'No especificado'
                    )
                ),

                'total_venta' => 'S/ ' . number_format(
                    (float)($reg['total_venta'] ?? 0),
                    2,
                    '.',
                    ''
                ),

                'estado_sunat' => obtenerEstadoSunatTexto(
                    (string)($reg['estado_sunat'] ?? '')
                )
            ];
        }

        responderJson($reporte);

        break;

    // =========================================================
    // LISTAR NOTAS DE CRÉDITO EN VENTAS
    // =========================================================
    case 'listarnotascredito':

        require_once __DIR__ . '/../Models/CreditNote.php';

        $creditNote = new CreditNote();
        $notas = $creditNote->listarParaVentas();
        $data = [];
        $baseUrl = obtenerBaseUrl();

        foreach ($notas as $nota) {
            $idNota = (int)(
                $nota['idnota_credito']
                ?? 0
            );

            $idVentaOrigen = (int)(
                $nota['idventa']
                ?? 0
            );

            if ($idNota <= 0) {
                continue;
            }

            $serieNumero =
                trim(
                    (string)(
                        $nota['serie_comprobante']
                        ?? ''
                    )
                )
                . '-'
                . trim(
                    (string)(
                        $nota['num_comprobante']
                        ?? ''
                    )
                );

            $documentoOrigen =
                trim(
                    (string)(
                        $nota['serie_documento_modificado']
                        ?? ''
                    )
                )
                . '-'
                . trim(
                    (string)(
                        $nota['numero_documento_modificado']
                        ?? ''
                    )
                );

            $estadoLocal = strtoupper(
                trim(
                    (string)(
                        $nota['estado_local']
                        ?? 'REGISTRADA'
                    )
                )
            );

            $estadoSunat = strtoupper(
                trim(
                    (string)(
                        $nota['estado_sunat']
                        ?? 'NO_ENVIADO'
                    )
                )
            );

            if ($estadoLocal === 'ANULADA') {
                $estadoSunat = 'ANULADO';
            }

            $notaEstadoVisual = $nota;
            $notaEstadoVisual['estado_sunat'] =
                $estadoSunat;

            $sustento = trim(
                (string)(
                    $nota['sustento']
                    ?? ''
                )
            );

            $motivo = trim(
                (string)(
                    $nota['motivo']
                    ?? ''
                )
            );

            $codigoMotivo = str_pad(
                preg_replace(
                    '/\D/',
                    '',
                    (string)(
                        $nota['codigo_motivo']
                        ?? ''
                    )
                ),
                2,
                '0',
                STR_PAD_LEFT
            );

            $motivoHtml = '
                <div class="nota-motivo-celda">
                    <strong>'
                        . htmlspecialchars(
                            $codigoMotivo
                            . ' - '
                            . $motivo,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . '</strong>';

            if ($sustento !== '') {
                $motivoHtml .= '
                    <small title="'
                        . htmlspecialchars(
                            $sustento,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . '">'
                        . htmlspecialchars(
                            $sustento,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . '</small>';
            }

            $motivoHtml .= '</div>';

            $whatsappTexto = urlencode(
                'Nota de crédito '
                . $serieNumero
                . ' - Ver PDF: '
                . $baseUrl
                . 'Reports/notacredito_a4.php?id='
                . $idNota
            );

            $data[] = [
                '0' => htmlspecialchars(
                    (string)(
                        $nota['fecha']
                        ?? ''
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ),

                '1' => htmlspecialchars(
                    (string)(
                        $nota['cliente']
                        ?? 'SIN CLIENTE'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ),

                '2' => htmlspecialchars(
                    (string)(
                        $nota['usuario']
                        ?? 'SIN USUARIO'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ),

                '3' => '
                    <div class="nota-numero-celda">
                        <span class="nota-tipo-label">
                            NOTA DE CRÉDITO
                        </span>
                        <strong>'
                            . htmlspecialchars(
                                $serieNumero,
                                ENT_QUOTES,
                                'UTF-8'
                            )
                            . '</strong>
                    </div>
                ',

                '4' => '
                    <a
                        class="nota-origen-link"
                        href="'
                        . $baseUrl
                        . 'Reports/a4.php?id='
                        . $idVentaOrigen
                        . '"
                        target="_blank"
                        rel="noopener">
                        <i class="far fa-file-alt"></i>
                        '
                        . htmlspecialchars(
                            $documentoOrigen,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . '
                    </a>
                ',

                '5' => $motivoHtml,

                '6' => '
                    <span class="nota-total-negativo">
                        - S/ '
                        . number_format(
                            (float)(
                                $nota['total_nota']
                                ?? 0
                            ),
                            2,
                            '.',
                            ''
                        )
                        . '
                    </span>
                ',

                '7' => generarEstadoSunatVenta(
                    $notaEstadoVisual
                ),

                '8' => '
                    <div class="dropdown venta-acciones">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary dropdown-toggle venta-acciones-boton"
                            data-toggle="dropdown"
                            aria-haspopup="true"
                            aria-expanded="false"
                            title="Abrir acciones de la nota de crédito">

                            <i class="fas fa-ellipsis-h mr-1"></i>
                            <span class="texto-accion">
                                Acciones
                            </span>
                        </button>

                        <div
                            class="dropdown-menu dropdown-menu-right venta-acciones-menu">

                            <h6 class="dropdown-header">
                                Nota de crédito
                            </h6>

                            <a
                                class="dropdown-item"
                                href="'
                                . $baseUrl
                                . 'Reports/notacredito_a4.php?id='
                                . $idNota
                                . '"
                                target="_blank"
                                rel="noopener">
                                <i class="far fa-file-pdf"></i>
                                <span>Imprimir A4</span>
                            </a>

                            <a
                                class="dropdown-item"
                                href="'
                                . $baseUrl
                                . 'Reports/notacredito_80mm.php?id='
                                . $idNota
                                . '"
                                target="_blank"
                                rel="noopener">
                                <i class="fas fa-receipt"></i>
                                <span>Imprimir ticket</span>
                            </a>

                            <a
                                class="dropdown-item"
                                href="https://wa.me/?text='
                                . $whatsappTexto
                                . '"
                                target="_blank"
                                rel="noopener">
                                <i class="far fa-comment-dots"></i>
                                <span>Compartir por WhatsApp</span>
                            </a>

                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header">
                                Comprobante original
                            </h6>

                            <a
                                class="dropdown-item"
                                href="'
                                . $baseUrl
                                . 'Reports/a4.php?id='
                                . $idVentaOrigen
                                . '"
                                target="_blank"
                                rel="noopener">
                                <i class="far fa-file-alt"></i>
                                <span>Ver venta original</span>
                            </a>

                            <a
                                class="dropdown-item"
                                href="sunat">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Abrir módulo SUNAT</span>
                            </a>

                        </div>
                    </div>
                '
            ];
        }

        responderJson([
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ]);

        break;

    // =========================================================
    // SELECT CLIENTES
    // =========================================================
    case 'selectCliente':

        require_once __DIR__ . '/../Models/Person.php';

        $persona = new Person();
        $rspta = $persona->listarc();

        echo '<option value="">Seleccione...</option>';

        foreach ($rspta as $reg) {
            echo '<option value="' .
                (int)$reg['idpersona'] .
                '">' .
                htmlspecialchars(
                    $reg['nombre'],
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '</option>';
        }

        break;

    // =========================================================
    // CANTIDAD DE ARTÍCULOS
    // =========================================================
    case 'cantidad_articulos':

        require_once __DIR__ . '/../Models/Product.php';

        $articulo = new Product();

        responderJson(
            $articulo->cantidadarticulos()
        );

        break;

    // =========================================================
    // LISTAR ARTÍCULOS
    // =========================================================
    case 'listarArticulos':

        require_once __DIR__ . '/../Models/Product.php';

        $articulo = new Product();
        $rspta = $articulo->listarActivosVenta();
        $data = [];
        $operacion = 1;

        foreach ($rspta as $reg) {
            $idingreso = (int)($reg['idingreso'] ?? 0);
            $idarticulo = (int)($reg['idarticulo'] ?? 0);
            $precioCompra = (float)($reg['precio_compra'] ?? 0);
            $precioVenta = (float)($reg['precio_venta'] ?? 0);
            $stock = (int)($reg['stock'] ?? 0);

            $codigoJs = json_encode(
                (string)($reg['codigo'] ?? ''),
                JSON_UNESCAPED_UNICODE
            );

            $nombreJs = json_encode(
                (string)($reg['nombre'] ?? 'Sin nombre'),
                JSON_UNESCAPED_UNICODE
            );

            if ($stock <= 10) {
                $btnStock = '<button class="btn btn-danger btn-sm">'
                    . $stock
                    . '</button>';
            } elseif ($stock < 30) {
                $btnStock = '<button class="btn btn-warning btn-sm">'
                    . $stock
                    . '</button>';
            } else {
                $btnStock = '<button class="btn btn-success btn-sm">'
                    . $stock
                    . '</button>';
            }

            $data[] = [
                '0' => '
                    <button
                        class="btn btn-success btn-sm"
                        onclick=\'agregarDetalle(
                            ' . $idingreso . ',
                            ' . $idarticulo . ',
                            ' . $codigoJs . ',
                            ' . $nombreJs . ',
                            ' . $precioCompra . ',
                            ' . $precioVenta . ',
                            ' . $stock . ',
                            ' . $operacion . '
                        )\'>
                        <span class="fa fa-plus"></span>
                        Añadir
                    </button>
                ',
                '1' =>
                htmlspecialchars(
                    $reg['nombre'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                    '<br><span style="font-size:0.95em;color:#888;">(' .
                    htmlspecialchars(
                        $reg['almacen'] ?? 'Sin almacén',
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                    ')</span>',
                '2' => htmlspecialchars(
                    $reg['codigo'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ),
                '3' => $btnStock,
                '4' => "<img src='Assets/img/products/" .
                    rawurlencode($reg['imagen'] ?? '') .
                    "' height='40' width='40' alt='Producto'>"
            ];
        }

        responderJson([
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ]);

        break;

    // =========================================================
    // SELECT COMPROBANTES
    // =========================================================
    case 'selectComprobante':

        require_once __DIR__ . '/../Models/Voucher.php';

        $comprobantes = new Voucher();
        $rspta = $comprobantes->select();

        echo '<option value="">Seleccione...</option>';

        foreach ($rspta as $reg) {
            $nombre = htmlspecialchars(
                $reg['nombre'],
                ENT_QUOTES,
                'UTF-8'
            );

            echo '<option value="' .
                $nombre .
                '">' .
                $nombre .
                '</option>';
        }

        break;

    // =========================================================
    // SELECT TIPO DE PAGO
    // =========================================================
    case 'selectTipopago':

        require_once __DIR__ . '/../Models/Paymentstype.php';

        $tipopago = new Paymentstype();
        $rspta = $tipopago->select();

        echo '<option value="">Seleccione...</option>';

        foreach ($rspta as $reg) {
            $nombre = htmlspecialchars(
                $reg['nombre'],
                ENT_QUOTES,
                'UTF-8'
            );

            echo '<option value="' .
                $nombre .
                '">' .
                $nombre .
                '</option>';
        }

        break;

    // =========================================================
    // CONFIGURACIÓN TRIBUTARIA DE LA VENTA
    // =========================================================
    case 'configuracionTributariaVenta':

        try {
            $idsucursalTributaria = (int)(
                $_SESSION['idsucursal_activa']
                ?? 0
            );

            responderJson([
                'success' => true,
                'configuracion' =>
                    $sell->obtenerConfiguracionTributariaEfectiva(
                        $idsucursalTributaria > 0
                            ? $idsucursalTributaria
                            : null
                    ),
                'tipos_operacion' =>
                    $sell->listarTiposOperacionSunat()
            ]);
        } catch (Throwable $errorTributario) {
            responderJson([
                'success' => false,
                'mensaje' => $errorTributario->getMessage()
            ]);
        }

        break;

    // =========================================================
    // BOOTSTRAP DEL POS INDEPENDIENTE
    // =========================================================
    case 'bootstrapPos':

        if ($idusuario <= 0) {
            http_response_code(401);
            responderJson([
                'success' => false,
                'mensaje' => 'La sesión ha expirado.'
            ]);
        }

        try {
            require_once __DIR__ . '/../Models/Product.php';
            require_once __DIR__ . '/../Models/Voucher.php';
            require_once __DIR__ . '/../Models/Company.php';

            $product = new Product();
            $voucherPos = new Voucher();
            $companyPos = new Company();

            $formasPago = $sell->getConexion()->getDataAll(
                "SELECT
                    idforma_pago,
                    nombre,
                    es_efectivo,
                    es_combinado,
                    condicion
                 FROM forma_pago
                 WHERE activo = 1
                   AND condicion = 1
                 ORDER BY
                    es_combinado ASC,
                    es_efectivo DESC,
                    nombre ASC"
            );

            $empresa = $companyPos->mostrarActivoSeguro();
            $tributaria = $sell->obtenerConfiguracionTributariaEfectiva(
                (int)($_SESSION['idsucursal_activa'] ?? 0) ?: null
            );

            $comprobantes = [];
            foreach ($voucherPos->select() as $comprobante) {
                $comprobantes[] = [
                    'id' => (int)($comprobante['id_comp_pago'] ?? 0),
                    'nombre' => (string)($comprobante['nombre'] ?? ''),
                    'letra_serie' => (string)($comprobante['letra_serie'] ?? ''),
                    'serie_comprobante' => (string)($comprobante['serie_comprobante'] ?? ''),
                    'num_comprobante' => (string)($comprobante['num_comprobante'] ?? '')
                ];
            }

            $empresaPublica = [
                'nombre' => (string)($empresa['nombre'] ?? 'TiquePOS'),
                'documento' => (string)($empresa['documento'] ?? ''),
                'direccion' => (string)($empresa['direccion'] ?? ''),
                'simbolo' => (string)($empresa['simbolo'] ?? 'S/.'),
                'moneda' => (string)($empresa['moneda'] ?? 'SOLES'),
                'logo' => (string)($empresa['logo'] ?? ''),
                'venta_tipo_comprobante_predeterminado' =>
                    (string)($empresa['venta_tipo_comprobante_predeterminado'] ?? ''),
                'venta_tipo_pago_predeterminado' =>
                    (string)($empresa['venta_tipo_pago_predeterminado'] ?? 'Contado'),
                'venta_idforma_pago_predeterminada' =>
                    (int)($empresa['venta_idforma_pago_predeterminada'] ?? 0),
                'venta_modo_envio_predeterminado' =>
                    (string)($empresa['venta_modo_envio_predeterminado'] ?? 'inmediato')
            ];

            responderJson([
                'success' => true,
                'empresa' => $empresaPublica,
                'usuario' => [
                    'id' => $idusuario,
                    'nombre' => (string)($_SESSION['nombre'] ?? 'Usuario'),
                    'cargo' => (string)($_SESSION['cargo'] ?? '')
                ],
                'caja' => [
                    'modo' => $modoCajaSesion,
                    'idsucursal' => (int)($_SESSION['idsucursal_activa'] ?? 0),
                    'idcaja' => (int)($_SESSION['idcaja_activa'] ?? 0),
                    'idapertura' => (int)($_SESSION['idapertura_activa'] ?? 0)
                ],
                'categorias' => $product->listarCategoriasActivas(),
                'productos' => $product->listarCatalogoPos(),
                'comprobantes' => $comprobantes,
                'formas_pago' => is_array($formasPago) ? $formasPago : [],
                'tributaria' => $tributaria,
                'tipos_operacion' => $sell->listarTiposOperacionSunat()
            ]);
        } catch (Throwable $errorPos) {
            responderJson([
                'success' => false,
                'mensaje' => $errorPos->getMessage()
            ]);
        }

        break;

    // =========================================================
    // BUSCAR CLIENTES DESDE EL POS
    // =========================================================
    case 'buscarClientesPos':

        if ($idusuario <= 0) {
            http_response_code(401);
            responderJson([
                'success' => false,
                'mensaje' => 'La sesión ha expirado.',
                'clientes' => []
            ]);
        }

        $termino = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));

        if (mb_strlen($termino, 'UTF-8') < 2) {
            responderJson([
                'success' => true,
                'clientes' => []
            ]);
        }

        $terminoBusqueda = '%' . $termino . '%';

        $clientes = $sell->getConexion()->getDataAll(
            "SELECT
                idpersona,
                nombre,
                tipo_documento,
                num_documento,
                direccion,
                telefono,
                email
             FROM persona
             WHERE tipo_persona = 'Cliente'
               AND (
                    nombre LIKE ?
                    OR num_documento LIKE ?
                    OR telefono LIKE ?
               )
             ORDER BY
                CASE
                    WHEN num_documento = ? THEN 0
                    WHEN nombre = ? THEN 1
                    ELSE 2
                END,
                nombre ASC
             LIMIT 15",
            [
                $terminoBusqueda,
                $terminoBusqueda,
                $terminoBusqueda,
                $termino,
                $termino
            ]
        );

        responderJson([
            'success' => true,
            'clientes' => is_array($clientes) ? $clientes : []
        ]);

        break;

    // =========================================================
    // CATEGORÍAS
    // =========================================================
    case 'listarCategorias':

        require_once __DIR__ . '/../Models/Product.php';

        $product = new Product();

        responderJson(
            $product->listarCategoriasActivas()
        );

        break;

    // =========================================================
    // ARTÍCULOS POR CATEGORÍA
    // =========================================================
    case 'listarArticulosPorCategoria':

        require_once __DIR__ . '/../Models/Product.php';

        $idcategoria = (int)($_GET['idcategoria'] ?? 0);

        $product = new Product();

        responderJson(
            $product->listarActivosVentaPorCategoria(
                $idcategoria
            )
        );

        break;

    // =========================================================
    // ARTÍCULOS PARA MODAL
    // =========================================================
    case 'listarArticulosModal':

        require_once __DIR__ . '/../Models/Product.php';

        $product = new Product();

        responderJson(
            $product->listarActivosVenta()
        );

        break;

    // =========================================================
    // FORMAS DE PAGO
    // =========================================================
    case 'selectFormaPago':

        $rspta = $sell->getConexion()->getDataAll(
            "SELECT
            idforma_pago,
            nombre,
            es_efectivo,
            es_combinado,
            condicion
         FROM forma_pago
         WHERE activo = 1
           AND condicion = 1
         ORDER BY
            es_combinado ASC,
            es_efectivo DESC,
            nombre ASC"
        );

        echo '<option value="">Seleccione...</option>';

        foreach ($rspta as $r) {
            $idformaPago = (int)(
                $r['idforma_pago']
                ?? 0
            );

            $nombre = htmlspecialchars(
                (string)($r['nombre'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            );

            $esEfectivo = (int)(
                $r['es_efectivo']
                ?? 0
            );

            $esCombinado = (int)(
                $r['es_combinado']
                ?? 0
            );

            echo '<option
                value="' . $idformaPago . '"
                data-nombre="' . $nombre . '"
                data-efectivo="' . $esEfectivo . '"
                data-combinado="' . $esCombinado . '">
                ' . $nombre . '
              </option>';
        }

        break;

    // =========================================================
    // BUSCAR PRODUCTO POR CÓDIGO
    // =========================================================
    case 'buscarProductoPorCodigo':

        $codigo = preg_replace(
            '/[\x00-\x1F\x7F]/u',
            '',
            trim(
                (string)($_POST['codigo'] ?? '')
            )
        );

        if ($codigo === '') {
            responderJson([]);
        }

        $producto = $sell->buscarProductoPorCodigo(
            $codigo
        );

        responderJson(
            $producto ?: []
        );

        break;

    // =========================================================
    // OPERACIÓN INVÁLIDA
    // =========================================================
    default:

        responderJson([
            'success' => false,
            'mensaje' => 'Operación no válida.'
        ]);
}
