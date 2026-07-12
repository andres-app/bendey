<?php

require_once __DIR__ . '/../Models/Sell.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$sell = new Sell();
$op = $_GET['op'] ?? '';

$idventa = (int)($_POST['idventa'] ?? $_GET['idventa'] ?? 0);
$idusuario = (int)($_SESSION['idusuario'] ?? 0);

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

            // =================================================
            // 3. IMPUESTO CONFIGURADO
            // =================================================
            $datosNegocio = $conexionVenta->getData(
                "SELECT monto_impuesto
                 FROM datos_negocio
                 WHERE condicion = 1
                 ORDER BY id_negocio DESC
                 LIMIT 1"
            );

            $impuesto = 18.00;

            if (
                is_array($datosNegocio)
                && isset($datosNegocio['monto_impuesto'])
            ) {
                $impuestoConfigurado =
                    (float)$datosNegocio['monto_impuesto'];

                if ($impuestoConfigurado > 0) {
                    $impuesto = $impuestoConfigurado;
                }
            }

            // =================================================
            // 4. CLIENTE
            // =================================================
            $idcliente = (int)($_POST['idcliente'] ?? 0);

            $tipo_documento = strtoupper(
                trim((string)($_POST['tipo_documento'] ?? ''))
            );

            /*
             * num_doc_real conserva el DNI/RUC verdadero.
             * num_documento puede ser el campo visible.
             */
            $numeroDocumentoRecibido =
                $_POST['num_doc_real']
                ?? $_POST['num_documento']
                ?? '';

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
             * se valida que realmente exista.
             */
            if ($idcliente > 0) {
                $clienteExistente = $conexionVenta->getData(
                    "SELECT
                        idpersona,
                        nombre,
                        tipo_documento,
                        num_documento
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
            }

            /*
             * Si no hay idcliente, buscar o crear por documento.
             */
            if ($idcliente <= 0) {
                if ($num_documento === '') {
                    throw new Exception(
                        'Debe ingresar el documento del cliente.'
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

                $esFactura =
                    stripos($tipo_comprobante, 'factura') !== false;

                if (
                    $esFactura
                    && (
                        $tipo_documento !== 'RUC'
                        || strlen($num_documento) !== 11
                    )
                ) {
                    throw new Exception(
                        'Para emitir una factura debe seleccionar un cliente con RUC válido.'
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
                        $direccion,
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

            // =================================================
            // 6. CALCULAR SUBTOTAL
            // =================================================
            $subtotal = 0.00;

            for ($i = 0; $i < $cantidadProductos; $i++) {
                $idArticulo = (int)$idarticulos[$i];
                $cantidad = (float)$cantidades[$i];
                $precioVenta = (float)$preciosVenta[$i];

                if ($idArticulo <= 0) {
                    throw new Exception(
                        'Se encontró un producto inválido.'
                    );
                }

                if ($cantidad <= 0) {
                    throw new Exception(
                        'La cantidad de los productos debe ser mayor que cero.'
                    );
                }

                if ($precioVenta < 0) {
                    throw new Exception(
                        'El precio de venta no puede ser negativo.'
                    );
                }

                $subtotal += $cantidad * $precioVenta;
            }

            $subtotal = round($subtotal, 2);

            // =================================================
            // 7. DESCUENTO
            // =================================================
            $descuento_total = round(
                (float)($_POST['descuento_total'] ?? 0),
                2
            );

            $descuento_porcentaje = round(
                (float)($_POST['descuento_porcentaje'] ?? 0),
                2
            );

            if ($descuento_total < 0) {
                $descuento_total = 0;
            }

            if ($descuento_porcentaje < 0) {
                $descuento_porcentaje = 0;
            }

            if ($descuento_porcentaje > 100) {
                $descuento_porcentaje = 100;
            }

            /*
             * Compatibilidad:
             * si no llegó descuento_total pero sí porcentaje,
             * se calcula en el servidor.
             */
            if (
                $descuento_total <= 0
                && $descuento_porcentaje > 0
            ) {
                $descuento_total = round(
                    $subtotal * ($descuento_porcentaje / 100),
                    2
                );
            }

            if ($descuento_total > $subtotal) {
                $descuento_total = $subtotal;
            }

            /*
             * Si se aplicó descuento fijo en soles,
             * calcular el porcentaje equivalente para registro.
             */
            if (
                $descuento_total > 0
                && $subtotal > 0
                && $descuento_porcentaje <= 0
            ) {
                $descuento_porcentaje = round(
                    ($descuento_total / $subtotal) * 100,
                    2
                );
            }

            $total_venta = round(
                $subtotal - $descuento_total,
                2
            );

            if ($total_venta < 0) {
                $total_venta = 0;
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
                    es_efectivo
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
             * El formulario usa idtipopago, pero actualmente
             * el valor enviado es el nombre del tipo de pago.
             */
            $tipo_pago = trim(
                (string)($_POST['idtipopago'] ?? '')
            );

            if ($tipo_pago === '') {
                $tipo_pago = trim(
                    (string)$formaPago['nombre']
                );
            }

            $num_transac = trim(
                (string)($_POST['num_transac'] ?? '')
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
                $preciosVenta
            );

            if (!$idventa) {
                throw new Exception(
                    'Error al registrar la venta.'
                );
            }

            // =================================================
            // 11. REGISTRAR PAGOS
            // =================================================
            $pagosMixtos = $_POST['pagos'] ?? [];

            if (
                is_array($pagosMixtos)
                && count($pagosMixtos) > 0
            ) {
                $totalPagosRegistrados = 0.00;
                $pagosValidos = 0;

                foreach ($pagosMixtos as $pago) {
                    $metodo = trim(
                        (string)($pago['metodo'] ?? '')
                    );

                    $monto = round(
                        (float)($pago['monto'] ?? 0),
                        2
                    );

                    if ($metodo === '' || $monto <= 0) {
                        continue;
                    }

                    $fp = $conexionVenta->getData(
                        "SELECT idforma_pago
                         FROM forma_pago
                         WHERE nombre = ?
                           AND activo = 1
                           AND condicion = 1
                         LIMIT 1",
                        [$metodo]
                    );

                    if (!$fp) {
                        throw new Exception(
                            'Forma de pago inválida: ' . $metodo
                        );
                    }

                    $conexionVenta->setData(
                        "INSERT INTO venta_pago
                            (idventa, idforma_pago, monto)
                         VALUES (?, ?, ?)",
                        [
                            $idventa,
                            $fp['idforma_pago'],
                            $monto
                        ]
                    );

                    $totalPagosRegistrados += $monto;
                    $pagosValidos++;
                }

                if ($pagosValidos === 0) {
                    throw new Exception(
                        'Debe registrar al menos un método de pago válido.'
                    );
                }

                if (
                    round($totalPagosRegistrados, 2)
                    < round($total_venta, 2)
                ) {
                    throw new Exception(
                        'La suma de los pagos no cubre el total de la venta.'
                    );
                }
            } else {
                $conexionVenta->setData(
                    "INSERT INTO venta_pago
                        (idventa, idforma_pago, monto)
                     VALUES (?, ?, ?)",
                    [
                        $idventa,
                        $idforma_pago,
                        $total_venta
                    ]
                );
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

            $modoEnvio = strtolower(
                trim((string)($_POST['modo_envio'] ?? 'inmediato'))
            );

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

            $mensajeRespuesta = 'Venta registrada correctamente.';

            if (
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
    // ANULAR VENTA LOCAL
    // =========================================================
    case 'anular':

        $rspta = $sell->anular($idventa);

        echo $rspta
            ? 'Ingreso anulado correctamente'
            : 'No se pudo anular el ingreso';

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

                            ' .
                    (
                        $reg['estado'] === 'Aceptado'
                        ? '
                                <a
                                    class="dropdown-item text-danger"
                                    href="#"
                                    onclick="anular(' .
                        $id .
                        ')">
                                    <i class="fas fa-times"></i>
                                    Anular
                                </a>'
                        : ''
                    ) .
                    '
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

        $simbolo = 'S/';
        $nombreImpuesto = 'IGV';
        $tasaImpuesto = 18.00;

        if (!empty($negocio)) {
            $simbolo = $negocio[0]['simbolo'] ?? 'S/';
            $nombreImpuesto =
                $negocio[0]['nombre_impuesto'] ?? 'IGV';

            $tasaConfigurada =
                (float)($negocio[0]['monto_impuesto'] ?? 18);

            if ($tasaConfigurada > 0) {
                $tasaImpuesto = $tasaConfigurada;
            }
        }

        $id = (int)($_GET['id'] ?? 0);
        $detalles = $sell->listarDetalle($id);
        $totalVenta = 0.00;

        echo '
            <thead style="background-color:#A9D0F5">
                <th>Opciones</th>
                <th>Artículo</th>
                <th>Cantidad</th>
                <th>Precio Venta</th>
                <th>Descuento</th>
                <th>Total</th>
            </thead>
        ';

        foreach ($detalles as $reg) {
            $totalArticulo =
                ((float)$reg['precio_venta']
                    * (float)$reg['cantidad'])
                - (float)$reg['descuento'];

            $totalVenta += $totalArticulo;

            echo '
                <tr class="filas">
                    <td></td>
                    <td>' .
                htmlspecialchars(
                    $reg['nombre'],
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '</td>
                    <td>' .
                number_format(
                    (float)$reg['cantidad'],
                    2,
                    '.',
                    ''
                ) .
                '</td>
                    <td>' .
                number_format(
                    (float)$reg['precio_venta'],
                    2
                ) .
                '</td>
                    <td>' .
                number_format(
                    (float)$reg['descuento'],
                    2
                ) .
                '</td>
                    <td>' .
                number_format(
                    $totalArticulo,
                    2
                ) .
                '</td>
                </tr>
            ';
        }

        /*
         * Los precios incluyen IGV.
         * Base = Total / 1.18
         * IGV = Total - Base
         */
        $factor = 1 + ($tasaImpuesto / 100);

        $subtotalSinImpuesto = $factor > 0
            ? round($totalVenta / $factor, 2)
            : $totalVenta;

        $importeImpuesto = round(
            $totalVenta - $subtotalSinImpuesto,
            2
        );

        echo '
            <tfoot>
                <th>
                    <span>Subtotal</span><br>
                    <span id="valor_impuestoc">' .
            htmlspecialchars(
                $nombreImpuesto,
                ENT_QUOTES,
                'UTF-8'
            ) .
            ' ' .
            number_format(
                $tasaImpuesto,
                2
            ) .
            '%</span><br>
                    <span>TOTAL</span>
                </th>

                <th></th>
                <th></th>
                <th></th>
                <th></th>

                <th>
                    <span class="pull-right" id="total">' .
            $simbolo .
            ' ' .
            number_format(
                $subtotalSinImpuesto,
                2,
                '.',
                ''
            ) .
            '</span><br>

                    <span class="pull-right" id="most_imp">' .
            $simbolo .
            ' ' .
            number_format(
                $importeImpuesto,
                2,
                '.',
                ''
            ) .
            '</span><br>

                    <span class="pull-right" id="most_total">' .
            $simbolo .
            ' ' .
            number_format(
                $totalVenta,
                2,
                '.',
                ''
            ) .
            '</span>
                </th>
            </tfoot>
        ';

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
                            title="Imprimir Ticket"
                            onclick="window.open(\'' .
                    $baseUrl .
                    'Reports/80mm.php?id=' .
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

                            <a
                                class="dropdown-item"
                                href="https://wa.me/?text=' .
                    $whatsappTexto .
                    '"
                                target="_blank">
                                <i class="fab fa-whatsapp"></i>
                                WhatsApp
                            </a>

                            ' .
                    (
                        $reg['estado'] === 'Aceptado'
                        ? '
                                <a
                                    class="dropdown-item text-danger"
                                    href="#"
                                    onclick="anular(' .
                        $id .
                        ')">
                                    <i class="fas fa-times"></i>
                                    Anular
                                </a>'
                        : ''
                    ) .
                    '
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
                    ? '<div class="badge badge-cdr">Aceptado</div>'
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

        require_once __DIR__ . '/../Models/FormaPago.php';

        $formaPago = new FormaPago();
        $rspta = $formaPago->select();

        echo '<option value="">Seleccione...</option>';

        foreach ($rspta as $r) {
            echo '<option
                    value="' . (int)$r['idforma_pago'] . '"
                    data-nombre="' .
                htmlspecialchars(
                    $r['nombre'],
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '"
                    data-efectivo="' .
                (int)$r['es_efectivo'] .
                '">' .
                htmlspecialchars(
                    $r['nombre'],
                    ENT_QUOTES,
                    'UTF-8'
                ) .
                '</option>';
        }

        break;

    // =========================================================
    // BUSCAR PRODUCTO POR CÓDIGO
    // =========================================================
    case 'buscarProductoPorCodigo':

        $codigo = trim(
            (string)($_POST['codigo'] ?? '')
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
