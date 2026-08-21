<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/Voucher.php';
require_once __DIR__ . '/CajaOperacionGuard.php';

/**
 * Gestión local de notas de crédito.
 *
 * La nota se registra primero y sus efectos de stock, caja y cuotas se
 * aplican únicamente cuando APISUNAT/SUNAT la devuelve como ACEPTADA.
 */
class CreditNote
{
    private Conexion $conexion;
    private CajaOperacionGuard $cajaGuard;


    public function __construct(?Conexion $conexion = null)
    {
        $this->conexion = $conexion instanceof Conexion
            ? $conexion
            : new Conexion();

        $this->cajaGuard =
            new CajaOperacionGuard($this->conexion);
    }

    public function getConexion(): Conexion
    {
        return $this->conexion;
    }

    /**
     * Datos completos para construir la pantalla de emisión.
     */
    public function preparar(int $idventa): array
    {
        if ($idventa <= 0) {
            throw new InvalidArgumentException(
                'La venta seleccionada no es válida.'
            );
        }

        $venta = $this->obtenerVentaBase($idventa, false);
        $this->validarVentaElegible($venta);

        $detalles = $this->obtenerDetallesDisponibles($idventa, false);
        $motivos = $this->listarMotivos(
            (string)$venta['tipo_documento_origen']
        );

        $formasPago = $this->listarFormasPago();
        $pagosOriginales = $this->obtenerPagosOriginales($idventa);
        $resumenCredito = $this->obtenerResumenCredito($idventa);

        $totalAcreditado = round(
            (float)($venta['total_acreditado'] ?? 0),
            2
        );

        $saldoAcreditable = max(
            round(
                (float)$venta['total_venta'] - $totalAcreditado,
                2
            ),
            0.00
        );

        return [
            'venta' => [
                'idventa' => (int)$venta['idventa'],
                'fecha' => (string)$venta['fecha'],
                'fecha_hora' => (string)$venta['fecha_hora'],
                'tipo_comprobante' => (string)$venta['tipo_comprobante'],
                'tipo_documento_origen' =>
                    (string)$venta['tipo_documento_origen'],
                'serie_comprobante' => (string)$venta['serie_comprobante'],
                'num_comprobante' => (string)$venta['num_comprobante'],
                'comprobante' => (string)$venta['comprobante'],
                'total_venta' => round((float)$venta['total_venta'], 2),
                'descuento_total' => round(
                    (float)$venta['descuento_total'],
                    2
                ),
                'impuesto' => round((float)$venta['impuesto'], 2),
                'tipo_operacion_sunat' => (string)($venta['tipo_operacion_sunat'] ?? '0101'),
                'moneda_codigo' => (string)($venta['moneda_codigo'] ?? 'PEN'),
                'total_gravado' => round((float)($venta['total_gravado'] ?? 0), 2),
                'total_exonerado' => round((float)($venta['total_exonerado'] ?? 0), 2),
                'total_inafecto' => round((float)($venta['total_inafecto'] ?? 0), 2),
                'total_exportacion' => round((float)($venta['total_exportacion'] ?? 0), 2),
                'total_igv' => round((float)($venta['total_igv'] ?? 0), 2),
                'tipo_pago' => (string)$venta['tipo_pago'],
                'condicion_pago' => $this->normalizarCondicionPago(
                    (string)$venta['tipo_pago']
                ),
                'estado_sunat' => (string)$venta['estado_sunat'],
                'document_id' => (string)($venta['document_id'] ?? ''),
                'total_acreditado' => $totalAcreditado,
                'saldo_acreditable' => $saldoAcreditable
            ],
            'cliente' => [
                'idcliente' => (int)$venta['idcliente'],
                'tipo_documento' => (string)$venta['tipo_documento'],
                'num_documento' => (string)$venta['num_documento'],
                'nombre' => (string)$venta['cliente'],
                'direccion' => (string)($venta['direccion'] ?? ''),
                'email' => (string)($venta['email'] ?? '')
            ],
            'detalles' => $detalles,
            'motivos' => $motivos,
            'formas_pago' => $formasPago,
            'pagos_originales' => $pagosOriginales,
            'credito' => $resumenCredito
        ];
    }

    /**
     * Registra la nota y reserva su correlativo.
     * Los efectos contables se aplican después de la aceptación SUNAT.
     */
    public function registrar(array $datos, array $sesion): array
    {
        $idventa = (int)($datos['idventa'] ?? 0);
        $codigoMotivo = str_pad(
            preg_replace('/\D/', '', (string)($datos['codigo_motivo'] ?? '')),
            2,
            '0',
            STR_PAD_LEFT
        );
        $sustento = trim((string)($datos['sustento'] ?? ''));
        $modoEnvio = strtoupper(
            trim((string)($datos['modo_envio'] ?? 'INMEDIATO'))
        );
        $itemsEntrada = is_array($datos['items'] ?? null)
            ? $datos['items']
            : [];
        $pagosEntrada = is_array($datos['pagos'] ?? null)
            ? $datos['pagos']
            : [];

        $idusuario = (int)($sesion['idusuario'] ?? 0);

        if ($idventa <= 0) {
            throw new InvalidArgumentException(
                'No se pudo determinar la venta de origen.'
            );
        }

        if ($idusuario <= 0) {
            throw new RuntimeException(
                'La sesión del usuario no es válida.'
            );
        }

        if (!in_array($modoEnvio, ['INMEDIATO', 'MANUAL'], true)) {
            $modoEnvio = 'INMEDIATO';
        }

        if (mb_strlen($sustento, 'UTF-8') < 3) {
            throw new RuntimeException(
                'Ingrese un sustento de al menos 3 caracteres.'
            );
        }

        if (mb_strlen($sustento, 'UTF-8') > 250) {
            throw new RuntimeException(
                'El sustento no puede superar los 250 caracteres.'
            );
        }

        if (count($itemsEntrada) === 0) {
            throw new RuntimeException(
                'Seleccione al menos un producto para la nota de crédito.'
            );
        }

        $transaccionActiva = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionActiva = true;

            $venta = $this->obtenerVentaBase($idventa, true);
            $this->validarVentaElegible($venta);

            $motivo = $this->obtenerMotivo(
                $codigoMotivo,
                (string)$venta['tipo_documento_origen']
            );

            if (!$motivo) {
                throw new RuntimeException(
                    'El motivo de nota de crédito no está habilitado.'
                );
            }

            $detallesDisponibles = $this->obtenerDetallesDisponibles(
                $idventa,
                true
            );

            $seleccion = $this->normalizarSeleccionItems(
                $itemsEntrada,
                $detallesDisponibles
            );

            $esMotivoTotal = in_array(
                $codigoMotivo,
                ['01', '06'],
                true
            );

            if (
                $codigoMotivo === '01'
                && round((float)$venta['total_acreditado'], 2) > 0
            ) {
                throw new RuntimeException(
                    'La anulación de la operación solo puede emitirse cuando la venta todavía no tiene otras notas de crédito.'
                );
            }

            if ($esMotivoTotal) {
                $this->validarSeleccionTotal(
                    $seleccion,
                    $detallesDisponibles
                );
            }

            if (
                $codigoMotivo === '07'
                && (int)($motivo['permite_parcial'] ?? 0) !== 1
            ) {
                throw new RuntimeException(
                    'El motivo seleccionado no admite una devolución parcial.'
                );
            }

            $calculo = $this->calcularImportes(
                $venta,
                $seleccion
            );

            $saldoAcreditable = max(
                round(
                    (float)$venta['total_venta']
                    - (float)$venta['total_acreditado'],
                    2
                ),
                0.00
            );

            if ($saldoAcreditable <= 0) {
                throw new RuntimeException(
                    'La venta ya no tiene un saldo disponible para acreditar.'
                );
            }

            if ($calculo['total_nota'] > $saldoAcreditable + 0.01) {
                throw new RuntimeException(
                    'El importe de la nota supera el saldo disponible de S/ '
                    . number_format($saldoAcreditable, 2) . '.'
                );
            }

            if ($esMotivoTotal) {
                $calculo = $this->ajustarCalculoAlSaldo(
                    $calculo,
                    $saldoAcreditable
                );
            }

            $condicionPago = $this->normalizarCondicionPago(
                (string)$venta['tipo_pago']
            );

            $resumenCredito = $this->obtenerResumenCredito(
                $idventa,
                true
            );

            $montoAplicarCuotas = 0.00;
            $montoDevolver = $calculo['total_nota'];

            if ($condicionPago === 'CREDITO') {
                $montoAplicarCuotas = min(
                    $calculo['total_nota'],
                    round(
                        (float)$resumenCredito['saldo_pendiente'],
                        2
                    )
                );

                $montoDevolver = max(
                    round(
                        $calculo['total_nota'] - $montoAplicarCuotas,
                        2
                    ),
                    0.00
                );
            }

            $pagosNormalizados = $this->validarPagosDevolucion(
                $pagosEntrada,
                $montoDevolver,
                $sesion,
                $idusuario
            );

            $contextoCaja = $this->resolverContextoCaja(
                $sesion,
                $venta,
                $idusuario,
                $pagosNormalizados
            );

            $nombreCorrelativo =
                (string)$venta['tipo_documento_origen'] === '01'
                ? 'Nota de Crédito - Factura'
                : 'Nota de Crédito - Boleta';

            $voucher = new Voucher($this->conexion);
            $correlativo = $voucher->obtenerCorrelativoBloqueado(
                $nombreCorrelativo
            );

            if (!is_array($correlativo)) {
                throw new RuntimeException(
                    'No existe un correlativo activo para ' . $nombreCorrelativo . '.'
                );
            }

            $serie = strtoupper(trim((string)$correlativo['serie']));
            $numero = str_pad(
                (string)((int)$correlativo['num_comprobante'] + 1),
                8,
                '0',
                STR_PAD_LEFT
            );

            $tipoAfectacion = $esMotivoTotal
                ? 'TOTAL'
                : 'PARCIAL';

            $afectaStock = (int)($motivo['afecta_stock_default'] ?? 0) === 1;
            $fechaHora = date('Y-m-d H:i:s');

            $idnota = $this->conexion->setDataReturnId(
                "INSERT INTO nota_credito (
                    idventa,
                    idcliente,
                    idusuario,
                    idsucursal,
                    idcaja,
                    idapertura,
                    tipo_documento_modificado,
                    serie_documento_modificado,
                    numero_documento_modificado,
                    document_id_origen,
                    tipo_comprobante,
                    serie_comprobante,
                    num_comprobante,
                    codigo_motivo,
                    sustento,
                    tipo_afectacion,
                    tipo_operacion_sunat,
                    fecha_hora,
                    moneda,
                    impuesto,
                    valor_venta,
                    total_gravado,
                    total_exonerado,
                    total_inafecto,
                    total_exportacion,
                    descuento_total,
                    igv,
                    total_nota,
                    afecta_stock,
                    genera_devolucion_dinero,
                    afecta_cuentas_cobrar,
                    modo_envio,
                    estado,
                    cliente_tipo_documento,
                    cliente_num_documento,
                    cliente_nombre,
                    cliente_direccion,
                    cliente_email,
                    observacion
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    'Nota de Crédito Electrónica',
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    'REGISTRADA', ?, ?, ?, ?, ?, ?
                )",
                [
                    $idventa,
                    (int)$venta['idcliente'],
                    $idusuario,
                    $contextoCaja['idsucursal'],
                    $contextoCaja['idcaja'],
                    $contextoCaja['idapertura'],
                    (string)$venta['tipo_documento_origen'],
                    (string)$venta['serie_comprobante'],
                    (string)$venta['num_comprobante'],
                    (string)($venta['document_id'] ?? ''),
                    $serie,
                    $numero,
                    $codigoMotivo,
                    $sustento,
                    $tipoAfectacion,
                    (string)($venta['tipo_operacion_sunat'] ?? '0101'),
                    $fechaHora,
                    (string)($venta['moneda_codigo'] ?? 'PEN'),
                    round((float)($calculo['porcentaje_igv_referencial'] ?? $venta['impuesto'] ?? 0), 2),
                    $calculo['valor_venta'],
                    $calculo['total_gravado'],
                    $calculo['total_exonerado'],
                    $calculo['total_inafecto'],
                    $calculo['total_exportacion'],
                    $calculo['descuento_total'],
                    $calculo['igv'],
                    $calculo['total_nota'],
                    $afectaStock ? 1 : 0,
                    $montoDevolver > 0 ? 1 : 0,
                    $montoAplicarCuotas > 0 ? 1 : 0,
                    $modoEnvio,
                    (string)$venta['tipo_documento'],
                    (string)$venta['num_documento'],
                    (string)$venta['cliente'],
                    (string)($venta['direccion'] ?? ''),
                    (string)($venta['email'] ?? ''),
                    trim((string)($datos['observacion'] ?? ''))
                ]
            );

            if (!$idnota) {
                throw new RuntimeException(
                    'No se pudo registrar la nota de crédito.'
                );
            }

            foreach ($calculo['lineas'] as $linea) {
                $insertado = $this->conexion->setData(
                    "INSERT INTO nota_credito_detalle (
                        idnota_credito,
                        iddetalle_venta,
                        idarticulo,
                        codigo_articulo,
                        descripcion_articulo,
                        unidad_codigo,
                        codigo_afectacion_igv,
                        porcentaje_igv,
                        codigo_producto_sunat,
                        codigo_tributo,
                        nombre_tributo,
                        tipo_tributo,
                        cantidad_original,
                        cantidad_nota,
                        costo_unitario,
                        precio_unitario_con_igv,
                        valor_unitario_sin_igv,
                        descuento_linea,
                        valor_venta,
                        igv,
                        total_linea,
                        devuelve_stock
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $idnota,
                        $linea['iddetalle_venta'],
                        $linea['idarticulo'],
                        $linea['codigo_articulo'],
                        $linea['descripcion_articulo'],
                        $linea['unidad_codigo'],
                        $linea['codigo_afectacion_igv'],
                        $linea['porcentaje_igv'],
                        $linea['codigo_producto_sunat'],
                        $linea['codigo_tributo'],
                        $linea['nombre_tributo'],
                        $linea['tipo_tributo'],
                        $linea['cantidad_original'],
                        $linea['cantidad_nota'],
                        $linea['costo_unitario'],
                        $linea['precio_unitario_con_igv'],
                        $linea['valor_unitario_sin_igv'],
                        $linea['descuento_linea'],
                        $linea['valor_venta'],
                        $linea['igv'],
                        $linea['total_linea'],
                        $afectaStock ? 1 : 0
                    ]
                );

                if (!$insertado) {
                    throw new RuntimeException(
                        'No se pudo registrar el detalle de la nota de crédito.'
                    );
                }
            }

            foreach ($pagosNormalizados as $pago) {
                $insertado = $this->conexion->setData(
                    "INSERT INTO nota_credito_pago (
                        idnota_credito,
                        idforma_pago,
                        idcuenta_financiera,
                        idapertura,
                        numero_operacion,
                        monto
                    ) VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $idnota,
                        $pago['idforma_pago'],
                        $pago['idcuenta_financiera'],
                        $pago['idapertura'],
                        $pago['numero_operacion'],
                        $pago['monto']
                    ]
                );

                if (!$insertado) {
                    throw new RuntimeException(
                        'No se pudo registrar el detalle de la devolución.'
                    );
                }
            }

            $registroSunat = $this->conexion->setData(
                "INSERT INTO nota_credito_sunat (
                    idnota_credito,
                    tipo_documento_sunat,
                    estado_sunat,
                    mensaje_sunat
                ) VALUES (?, '07', 'NO_ENVIADO', 'Pendiente de envío a APISUNAT.')",
                [$idnota]
            );

            if (!$registroSunat) {
                throw new RuntimeException(
                    'No se pudo preparar el seguimiento SUNAT de la nota.'
                );
            }

            $actualizado = $voucher->actualizarCorrelativoPorId(
                (int)$correlativo['id_comp_pago'],
                $numero
            );

            if (!$actualizado) {
                throw new RuntimeException(
                    'No se pudo actualizar el correlativo de la nota de crédito.'
                );
            }

            $this->conexion->commit();
            $transaccionActiva = false;

            return [
                'success' => true,
                'idnota_credito' => (int)$idnota,
                'idventa' => $idventa,
                'comprobante' => $serie . '-' . $numero,
                'serie' => $serie,
                'numero' => $numero,
                'codigo_motivo' => $codigoMotivo,
                'total_nota' => $calculo['total_nota'],
                'monto_aplicar_cuotas' => $montoAplicarCuotas,
                'monto_devolver' => $montoDevolver,
                'modo_envio' => $modoEnvio,
                'mensaje' =>
                    'Nota de crédito registrada correctamente. '
                    . 'Los efectos de stock y caja se aplicarán cuando SUNAT la acepte.'
            ];
        } catch (Throwable $e) {
            if ($transaccionActiva) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[NC ROLLBACK] ' . $rollbackError->getMessage()
                    );
                }
            }

            error_log('[NOTA CREDITO] ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Aplica stock, caja y cuotas después de la aceptación SUNAT.
     */
    public function aplicarEfectos(int $idnotaCredito): array
    {
        if ($idnotaCredito <= 0) {
            throw new InvalidArgumentException(
                'La nota de crédito no es válida.'
            );
        }

        $transaccionActiva = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionActiva = true;

            $nota = $this->conexion->getData(
                "SELECT
                    nc.*,
                    ncs.estado_sunat
                 FROM nota_credito nc
                 INNER JOIN nota_credito_sunat ncs
                    ON ncs.idnota_credito = nc.idnota_credito
                 WHERE nc.idnota_credito = ?
                 LIMIT 1
                 FOR UPDATE",
                [$idnotaCredito]
            );

            if (!is_array($nota)) {
                throw new RuntimeException(
                    'No se encontró la nota de crédito.'
                );
            }

            if (
                strtoupper((string)$nota['estado_sunat']) !== 'ACEPTADO'
            ) {
                throw new RuntimeException(
                    'Los efectos se aplican únicamente cuando SUNAT acepta la nota.'
                );
            }

            if ((string)$nota['estado'] === 'ANULADA') {
                throw new RuntimeException(
                    'La nota de crédito se encuentra anulada.'
                );
            }

            if (
                (int)$nota['afecta_stock'] === 1
                && (int)$nota['stock_aplicado'] !== 1
            ) {
                $this->aplicarStock($nota);

                $this->conexion->setData(
                    "UPDATE nota_credito
                     SET
                        stock_aplicado = 1,
                        fecha_aplicacion_stock = NOW()
                     WHERE idnota_credito = ?",
                    [$idnotaCredito]
                );
            }

            if (
                (int)$nota['afecta_cuentas_cobrar'] === 1
                && (int)$nota['cuotas_aplicadas'] !== 1
            ) {
                $this->aplicarCuotas($nota);

                $this->conexion->setData(
                    "UPDATE nota_credito
                     SET
                        cuotas_aplicadas = 1,
                        fecha_aplicacion_cuotas = NOW()
                     WHERE idnota_credito = ?",
                    [$idnotaCredito]
                );
            }

            $resultadoFinanzas = [
                'aplicados' => 0,
                'pendientes' => 0,
                'total' => 0
            ];

            if (
                (int)$nota['genera_devolucion_dinero'] === 1
                && (int)$nota['finanzas_aplicadas'] !== 1
            ) {
                $resultadoFinanzas =
                    $this->aplicarFinanzas($nota);

                if (
                    (int)$resultadoFinanzas['pendientes'] === 0
                ) {
                    $this->conexion->setData(
                        "UPDATE nota_credito
                         SET
                            finanzas_aplicadas = 1,
                            fecha_aplicacion_finanzas = NOW()
                         WHERE idnota_credito = ?",
                        [$idnotaCredito]
                    );
                }
            }

            $this->conexion->commit();
            $transaccionActiva = false;

            return [
                'success' => true,
                'idnota_credito' => $idnotaCredito,
                'stock_aplicado' => (int)$nota['afecta_stock'] === 1,
                'cuotas_aplicadas' =>
                    (int)$nota['afecta_cuentas_cobrar'] === 1,
                'finanzas_aplicadas' =>
                    (int)$nota['genera_devolucion_dinero'] !== 1
                    || (int)$resultadoFinanzas['pendientes'] === 0,
                'finanzas_pendientes' =>
                    (int)$resultadoFinanzas['pendientes'],
                'mensaje' =>
                    (int)$resultadoFinanzas['pendientes'] > 0
                        ? 'Stock y cuotas aplicados. La devolución financiera quedó pendiente porque la apertura seleccionada ya no está disponible.'
                        : 'Los efectos de la nota fueron aplicados correctamente.'
            ];
        } catch (Throwable $e) {
            if ($transaccionActiva) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[NC EFECTOS ROLLBACK] '
                        . $rollbackError->getMessage()
                    );
                }
            }

            error_log('[NC EFECTOS] ' . $e->getMessage());
            throw $e;
        }
    }

    public function obtenerNota(int $idnotaCredito): ?array
    {
        if ($idnotaCredito <= 0) {
            return null;
        }

        $cabecera = $this->conexion->getData(
            "SELECT
                nc.*,
                ncm.descripcion AS motivo_descripcion,
                ncs.document_id,
                ncs.file_name,
                ncs.xml,
                ncs.xml_local,
                ncs.cdr,
                ncs.cdr_local,
                ncs.estado_sunat,
                ncs.mensaje_sunat,
                ncs.faults,
                ncs.notes,
                ncs.fecha_envio,
                ncs.fecha_respuesta,
                v.tipo_pago AS tipo_pago_original,
                CASE
                    WHEN UPPER(TRIM(CAST(v.tipo_pago AS CHAR))) IN ('1', 'CONTADO')
                        THEN 'CONTADO'
                    WHEN UPPER(TRIM(CAST(v.tipo_pago AS CHAR))) IN ('4', 'CREDITO', 'CRÉDITO')
                        THEN 'CRÉDITO'
                    ELSE UPPER(TRIM(CAST(v.tipo_pago AS CHAR)))
                END AS condicion_pago_original,
                v.total_venta AS total_venta_original,
                v.fecha_hora AS fecha_documento_original,
                u.nombre AS usuario_emisor,
                s.nombre AS sucursal_nombre,
                cf.codigo AS caja_codigo,
                cf.nombre AS caja_nombre
             FROM nota_credito nc
             INNER JOIN nota_credito_motivo ncm
                ON ncm.codigo = nc.codigo_motivo
             INNER JOIN nota_credito_sunat ncs
                ON ncs.idnota_credito = nc.idnota_credito
             INNER JOIN venta v
                ON v.idventa = nc.idventa
             LEFT JOIN usuario u
                ON u.idusuario = nc.idusuario
             LEFT JOIN sucursal s
                ON s.idsucursal = nc.idsucursal
             LEFT JOIN caja_fisica cf
                ON cf.idcaja = nc.idcaja
             WHERE nc.idnota_credito = ?
             LIMIT 1",
            [$idnotaCredito]
        );

        if (!is_array($cabecera)) {
            return null;
        }

        $detalles = $this->conexion->getDataAll(
            "SELECT *
             FROM nota_credito_detalle
             WHERE idnota_credito = ?
             ORDER BY iddetalle_nota_credito ASC",
            [$idnotaCredito]
        );

        $pagos = $this->conexion->getDataAll(
            "SELECT
                ncp.*,
                fp.nombre AS forma_pago,
                fp.es_efectivo,
                COALESCE(fpd.requiere_caja_abierta, 0)
                    AS requiere_caja_abierta,
                COALESCE(fpd.requiere_operacion, 0)
                    AS requiere_operacion
             FROM nota_credito_pago ncp
             INNER JOIN forma_pago fp
                ON fp.idforma_pago = ncp.idforma_pago
             LEFT JOIN forma_pago_destino fpd
                ON fpd.idforma_pago = fp.idforma_pago
             WHERE ncp.idnota_credito = ?
             ORDER BY ncp.idnota_credito_pago ASC",
            [$idnotaCredito]
        );

        $ajustesCuotas = $this->conexion->getDataAll(
            "SELECT
                ncca.*,
                vc.codigo,
                vc.numero_cuota,
                vc.fecha_vencimiento
             FROM nota_credito_cuota_ajuste ncca
             INNER JOIN venta_cuota vc
                ON vc.idventa_cuota = ncca.idventa_cuota
             WHERE ncca.idnota_credito = ?
             ORDER BY vc.numero_cuota ASC",
            [$idnotaCredito]
        );

        return [
            'cabecera' => $cabecera,
            'detalles' => is_array($detalles) ? $detalles : [],
            'pagos' => is_array($pagos) ? $pagos : [],
            'ajustes_cuotas' => is_array($ajustesCuotas)
                ? $ajustesCuotas
                : []
        ];
    }


    /**
     * Lista las notas de crédito para integrarlas en la pantalla
     * general de ventas.
     */
    public function listarParaVentas(): array
    {
        $sql = "
            SELECT
                nc.idnota_credito,
                nc.idventa,

                DATE_FORMAT(
                    nc.fecha_hora,
                    '%d/%m/%Y %H:%i'
                ) AS fecha,

                COALESCE(
                    nc.cliente_nombre,
                    'SIN CLIENTE'
                ) AS cliente,

                COALESCE(
                    u.nombre,
                    'SIN USUARIO'
                ) AS usuario,

                nc.serie_comprobante,
                nc.num_comprobante,

                nc.serie_documento_modificado,
                nc.numero_documento_modificado,

                nc.codigo_motivo,

                COALESCE(
                    ncm.descripcion,
                    nc.sustento,
                    'SIN MOTIVO'
                ) AS motivo,

                nc.sustento,
                nc.total_nota,
                nc.genera_devolucion_dinero,
                nc.finanzas_aplicadas,
                nc.estado AS estado_local,

                CASE
                    WHEN ncs.idnota_credito IS NULL
                    THEN 'NO_ENVIADO'

                    WHEN TRIM(
                        COALESCE(
                            ncs.estado_sunat,
                            ''
                        )
                    ) <> ''
                    THEN UPPER(
                        TRIM(
                            ncs.estado_sunat
                        )
                    )

                    WHEN COALESCE(
                        ncs.document_id,
                        ''
                    ) = ''
                    THEN 'NO_ENVIADO'

                    ELSE 'PENDIENTE'
                END AS estado_sunat,

                COALESCE(
                    ncs.mensaje_sunat,
                    ''
                ) AS mensaje_sunat,

                COALESCE(
                    ncs.document_id,
                    ''
                ) AS document_id

            FROM nota_credito AS nc

            LEFT JOIN usuario AS u
                ON u.idusuario = nc.idusuario

            LEFT JOIN nota_credito_motivo AS ncm
                ON ncm.codigo = nc.codigo_motivo

            LEFT JOIN nota_credito_sunat AS ncs
                ON ncs.idnota_credito =
                   nc.idnota_credito

            ORDER BY
                nc.fecha_hora DESC,
                nc.idnota_credito DESC
        ";

        $resultado = $this->conexion->getDataAll(
            $sql
        );

        return is_array($resultado)
            ? $resultado
            : [];
    }

    public function obtenerArchivo(
        int $idnotaCredito,
        string $tipo
    ): ?array {
        $tipo = strtolower(trim($tipo));

        if (
            $idnotaCredito <= 0
            || !in_array($tipo, ['xml', 'cdr'], true)
        ) {
            return null;
        }

        $columnaUrl = $tipo;
        $columnaLocal = $tipo . '_local';

        $resultado = $this->conexion->getData(
            "SELECT
                idnota_credito,
                document_id,
                file_name,
                {$columnaUrl} AS url,
                {$columnaLocal} AS ruta_local,
                estado_sunat
             FROM nota_credito_sunat
             WHERE idnota_credito = ?
             LIMIT 1",
            [$idnotaCredito]
        );

        return is_array($resultado) ? $resultado : null;
    }

    public function actualizarRutaLocal(
        int $idnotaCredito,
        string $tipo,
        string $ruta
    ): bool {
        $tipo = strtolower(trim($tipo));
        $ruta = trim($ruta);

        if (
            $idnotaCredito <= 0
            || !in_array($tipo, ['xml', 'cdr'], true)
            || $ruta === ''
        ) {
            return false;
        }

        $columna = $tipo . '_local';

        return (bool)$this->conexion->setData(
            "UPDATE nota_credito_sunat
             SET
                {$columna} = ?,
                fecha_descarga_archivos = NOW()
             WHERE idnota_credito = ?",
            [$ruta, $idnotaCredito]
        );
    }

    public function listarMotivos(string $tipoDocumentoOrigen): array
    {
        $columna = $tipoDocumentoOrigen === '01'
            ? 'permite_factura'
            : 'permite_boleta';

        $resultado = $this->conexion->getDataAll(
            "SELECT
                codigo,
                descripcion,
                afecta_stock_default,
                permite_parcial
             FROM nota_credito_motivo
             WHERE activo = 1
               AND {$columna} = 1
             ORDER BY codigo ASC"
        );

        return is_array($resultado) ? $resultado : [];
    }

    public function listarFormasPago(): array
    {
        $resultado = $this->conexion->getDataAll(
            "SELECT
                fp.idforma_pago,
                fp.nombre,
                fp.es_efectivo,
                COALESCE(fpd.requiere_caja_abierta, 0)
                    AS requiere_caja_abierta,
                COALESCE(fpd.requiere_operacion, 0)
                    AS requiere_operacion,
                fpd.idcuenta_financiera
             FROM forma_pago AS fp
             LEFT JOIN forma_pago_destino AS fpd
                ON fpd.idforma_pago = fp.idforma_pago
             WHERE fp.activo = 1
               AND fp.condicion = 1
               AND fp.es_combinado = 0
             ORDER BY fp.idforma_pago ASC"
        );

        return is_array($resultado) ? $resultado : [];
    }

    private function obtenerVentaBase(
        int $idventa,
        bool $bloquear
    ): array {
        $sql = "SELECT
                    v.idventa,
                    v.idcliente,
                    v.idusuario,
                    v.idsucursal,
                    v.idcaja,
                    v.idapertura,
                    v.tipo_comprobante,
                    v.serie_comprobante,
                    v.num_comprobante,
                    v.fecha_hora,
                    DATE_FORMAT(v.fecha_hora, '%d/%m/%Y %H:%i') AS fecha,
                    CONCAT(v.serie_comprobante, '-', v.num_comprobante) AS comprobante,
                    v.impuesto,
                    v.tipo_operacion_sunat,
                    v.moneda_codigo,
                    v.total_gravado,
                    v.total_exonerado,
                    v.total_inafecto,
                    v.total_exportacion,
                    v.total_igv,
                    v.precios_incluyen_impuesto,
                    v.descuento_total,
                    v.total_venta,
                    v.tipo_pago,
                    v.estado,

                    CASE
                        WHEN v.tipo_comprobante = 'Factura Electrónica'
                        THEN '01'
                        WHEN v.tipo_comprobante = 'Boleta Electrónica'
                        THEN '03'
                        ELSE ''
                    END AS tipo_documento_origen,

                    p.tipo_documento,
                    p.num_documento,
                    p.nombre AS cliente,
                    p.direccion,
                    p.email,

                    vs.document_id,
                    UPPER(COALESCE(vs.estado_sunat, '')) AS estado_sunat,
                    vs.mensaje_sunat,

                    COALESCE((
                        SELECT SUM(nc.total_nota)
                        FROM nota_credito nc
                        WHERE nc.idventa = v.idventa
                          AND nc.estado <> 'ANULADA'
                    ), 0) AS total_acreditado

                FROM venta v
                INNER JOIN persona p
                    ON p.idpersona = v.idcliente
                LEFT JOIN venta_sunat vs
                    ON vs.idventa = v.idventa
                WHERE v.idventa = ?
                LIMIT 1";

        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }

        $resultado = $this->conexion->getData(
            $sql,
            [$idventa]
        );

        if (!is_array($resultado)) {
            throw new RuntimeException(
                'No se encontró la venta seleccionada.'
            );
        }

        return $resultado;
    }

    private function validarVentaElegible(array $venta): void
    {
        if (
            !in_array(
                (string)$venta['tipo_documento_origen'],
                ['01', '03'],
                true
            )
        ) {
            throw new RuntimeException(
                'Solo se puede emitir una nota de crédito para una factura o boleta electrónica.'
            );
        }

        if ((string)$venta['estado'] !== 'Aceptado') {
            throw new RuntimeException(
                'La venta original no se encuentra activa.'
            );
        }

        if (strtoupper((string)$venta['estado_sunat']) !== 'ACEPTADO') {
            throw new RuntimeException(
                'El comprobante original debe estar aceptado por SUNAT antes de generar una nota de crédito.'
            );
        }

        if (trim((string)($venta['document_id'] ?? '')) === '') {
            throw new RuntimeException(
                'El comprobante original no tiene documentId de APISUNAT.'
            );
        }
    }

    private function obtenerDetallesDisponibles(
        int $idventa,
        bool $bloquear
    ): array {
        $sql = "SELECT
                    dv.iddetalle_venta,
                    dv.idarticulo,
                    dv.cantidad AS cantidad_original,
                    dv.precio_compra,
                    dv.precio_venta,
                    dv.descuento,
                    dv.codigo_afectacion_igv,
                    dv.porcentaje_igv,
                    dv.unidad_medida_sunat,
                    dv.codigo_producto_sunat,
                    dv.codigo_tributo,
                    dv.nombre_tributo,
                    dv.tipo_tributo,
                    dv.valor_unitario_sin_igv,
                    dv.base_imponible,
                    dv.monto_igv,
                    dv.total_linea,
                    a.codigo AS codigo_articulo,
                    a.nombre AS descripcion_articulo,
                    COALESCE(
                        NULLIF(UPPER(TRIM(dv.unidad_medida_sunat)), ''),
                        NULLIF(UPPER(TRIM(m.codigo)), ''),
                        'NIU'
                    ) AS unidad_codigo,
                    COALESCE((
                        SELECT SUM(ncd.cantidad_nota)
                        FROM nota_credito_detalle ncd
                        INNER JOIN nota_credito nc
                            ON nc.idnota_credito = ncd.idnota_credito
                        WHERE ncd.iddetalle_venta = dv.iddetalle_venta
                          AND nc.estado <> 'ANULADA'
                    ), 0) AS cantidad_acreditada
                FROM detalle_venta dv
                INNER JOIN articulo a
                    ON a.idarticulo = dv.idarticulo
                LEFT JOIN medida m
                    ON m.idmedida = a.idmedida
                WHERE dv.idventa = ?
                  AND dv.estado = 1
                ORDER BY dv.iddetalle_venta ASC";

        if ($bloquear) {
            $sql .= ' FOR UPDATE';
        }

        $resultado = $this->conexion->getDataAll(
            $sql,
            [$idventa]
        );

        $salida = [];

        foreach (is_array($resultado) ? $resultado : [] as $fila) {
            $cantidadOriginal = round(
                (float)$fila['cantidad_original'],
                3
            );
            $cantidadAcreditada = round(
                (float)$fila['cantidad_acreditada'],
                3
            );
            $cantidadDisponible = max(
                round($cantidadOriginal - $cantidadAcreditada, 3),
                0.000
            );

            $fila['cantidad_original'] = $cantidadOriginal;
            $fila['cantidad_acreditada'] = $cantidadAcreditada;
            $fila['cantidad_disponible'] = $cantidadDisponible;
            $fila['precio_compra'] = round((float)$fila['precio_compra'], 2);
            $fila['precio_venta'] = round((float)$fila['precio_venta'], 2);
            $fila['descuento'] = round((float)$fila['descuento'], 2);
            $fila['porcentaje_igv'] = round((float)($fila['porcentaje_igv'] ?? 0), 2);
            $fila['base_imponible'] = round((float)($fila['base_imponible'] ?? 0), 2);
            $fila['monto_igv'] = round((float)($fila['monto_igv'] ?? 0), 2);
            $fila['total_linea'] = round((float)($fila['total_linea'] ?? 0), 2);

            $salida[] = $fila;
        }

        return $salida;
    }

    private function obtenerMotivo(
        string $codigo,
        string $tipoDocumentoOrigen
    ): ?array {
        $columna = $tipoDocumentoOrigen === '01'
            ? 'permite_factura'
            : 'permite_boleta';

        $resultado = $this->conexion->getData(
            "SELECT *
             FROM nota_credito_motivo
             WHERE codigo = ?
               AND activo = 1
               AND {$columna} = 1
             LIMIT 1",
            [$codigo]
        );

        return is_array($resultado) ? $resultado : null;
    }

    private function normalizarSeleccionItems(
        array $itemsEntrada,
        array $detallesDisponibles
    ): array {
        $mapa = [];

        foreach ($detallesDisponibles as $detalle) {
            $mapa[(int)$detalle['iddetalle_venta']] = $detalle;
        }

        $seleccion = [];
        $vistos = [];

        foreach ($itemsEntrada as $item) {
            if (!is_array($item)) {
                continue;
            }

            $iddetalle = (int)($item['iddetalle_venta'] ?? 0);
            $cantidad = round((float)($item['cantidad'] ?? 0), 3);

            if ($iddetalle <= 0 || $cantidad <= 0) {
                continue;
            }

            if (isset($vistos[$iddetalle])) {
                throw new RuntimeException(
                    'Un producto fue enviado más de una vez.'
                );
            }

            if (!isset($mapa[$iddetalle])) {
                throw new RuntimeException(
                    'Uno de los productos no pertenece a la venta original.'
                );
            }

            $detalle = $mapa[$iddetalle];
            $disponible = round(
                (float)$detalle['cantidad_disponible'],
                3
            );

            if ($cantidad > $disponible + 0.0001) {
                throw new RuntimeException(
                    'La cantidad seleccionada para '
                    . $detalle['descripcion_articulo']
                    . ' supera el saldo disponible ('
                    . $this->formatearCantidad($disponible)
                    . ').'
                );
            }

            $detalle['cantidad_nota'] = $cantidad;
            $seleccion[] = $detalle;
            $vistos[$iddetalle] = true;
        }

        if (count($seleccion) === 0) {
            throw new RuntimeException(
                'Seleccione al menos una cantidad válida.'
            );
        }

        return $seleccion;
    }

    private function validarSeleccionTotal(
        array $seleccion,
        array $detallesDisponibles
    ): void {
        $mapaSeleccion = [];

        foreach ($seleccion as $item) {
            $mapaSeleccion[(int)$item['iddetalle_venta']] =
                round((float)$item['cantidad_nota'], 3);
        }

        foreach ($detallesDisponibles as $detalle) {
            $disponible = round(
                (float)$detalle['cantidad_disponible'],
                3
            );

            if ($disponible <= 0) {
                continue;
            }

            $seleccionada = $mapaSeleccion[
                (int)$detalle['iddetalle_venta']
            ] ?? 0.000;

            if (abs($seleccionada - $disponible) > 0.0001) {
                throw new RuntimeException(
                    'El motivo seleccionado requiere incluir todas las cantidades disponibles.'
                );
            }
        }
    }

    private function calcularImportes(
        array $venta,
        array $seleccion
    ): array {
        $lineas = [];
        $valorVenta = 0.00;
        $igvTotal = 0.00;
        $totalNota = 0.00;
        $descuentoNota = 0.00;
        $totalGravado = 0.00;
        $totalExonerado = 0.00;
        $totalInafecto = 0.00;
        $totalExportacion = 0.00;
        $porcentajeReferencial = 0.00;

        foreach ($seleccion as $detalle) {
            $cantidad = round((float)$detalle['cantidad_nota'], 3);
            $cantidadOriginal = max(
                round((float)$detalle['cantidad_original'], 3),
                0.001
            );
            $proporcion = min($cantidad / $cantidadOriginal, 1.00);

            $afectacion = trim((string)($detalle['codigo_afectacion_igv'] ?? '10'));
            if (!in_array($afectacion, ['10','20','30','40'], true)) {
                $afectacion = '10';
            }
            $porcentaje = $afectacion === '10'
                ? round((float)($detalle['porcentaje_igv'] ?? $venta['impuesto'] ?? 18), 2)
                : 0.00;
            $porcentajeReferencial = max($porcentajeReferencial, $porcentaje);

            $totalOriginalLinea = round((float)($detalle['total_linea'] ?? 0), 2);
            $baseOriginalLinea = round((float)($detalle['base_imponible'] ?? 0), 2);
            $igvOriginalLinea = round((float)($detalle['monto_igv'] ?? 0), 2);

            // Compatibilidad con ventas históricas migradas o incompletas.
            if ($totalOriginalLinea <= 0) {
                $totalOriginalLinea = max(
                    round(
                        (float)$detalle['cantidad_original']
                        * (float)$detalle['precio_venta']
                        - (float)$detalle['descuento'],
                        2
                    ),
                    0.00
                );
            }
            if ($baseOriginalLinea <= 0 && $totalOriginalLinea > 0) {
                if ($afectacion === '10' && $porcentaje > 0) {
                    $baseOriginalLinea = round(
                        $totalOriginalLinea / (1 + ($porcentaje / 100)),
                        2
                    );
                    $igvOriginalLinea = round(
                        $totalOriginalLinea - $baseOriginalLinea,
                        2
                    );
                } else {
                    $baseOriginalLinea = $totalOriginalLinea;
                    $igvOriginalLinea = 0.00;
                }
            }

            $totalLinea = round($totalOriginalLinea * $proporcion, 2);
            $baseLinea = round($baseOriginalLinea * $proporcion, 2);
            $igvLinea = round($igvOriginalLinea * $proporcion, 2);

            // Cuadrar el total por redondeo proporcional.
            if (abs(($baseLinea + $igvLinea) - $totalLinea) > 0.01) {
                if ($afectacion === '10') {
                    $igvLinea = round($totalLinea - $baseLinea, 2);
                } else {
                    $baseLinea = $totalLinea;
                    $igvLinea = 0.00;
                }
            }

            $descuentoLinea = round(
                (float)($detalle['descuento'] ?? 0) * $proporcion,
                2
            );
            $precioConImpuesto = $cantidad > 0
                ? round($totalLinea / $cantidad, 6)
                : 0.00;
            $valorUnitario = $cantidad > 0
                ? round($baseLinea / $cantidad, 6)
                : 0.00;

            $tributo = $this->datosTributoPorAfectacion(
                $afectacion,
                $detalle
            );

            $lineas[] = [
                'iddetalle_venta' => (int)$detalle['iddetalle_venta'],
                'idarticulo' => (int)$detalle['idarticulo'],
                'codigo_articulo' => trim((string)($detalle['codigo_articulo'] ?? '')),
                'descripcion_articulo' => trim((string)$detalle['descripcion_articulo']),
                'unidad_codigo' => $this->normalizarUnidad(
                    (string)($detalle['unidad_codigo'] ?? 'NIU')
                ),
                'codigo_afectacion_igv' => $afectacion,
                'porcentaje_igv' => $porcentaje,
                'codigo_producto_sunat' => trim((string)($detalle['codigo_producto_sunat'] ?? '')),
                'codigo_tributo' => $tributo['codigo_tributo'],
                'nombre_tributo' => $tributo['nombre_tributo'],
                'tipo_tributo' => $tributo['tipo_tributo'],
                'cantidad_original' => round((float)$detalle['cantidad_original'], 3),
                'cantidad_nota' => $cantidad,
                'costo_unitario' => round((float)$detalle['precio_compra'], 6),
                'precio_unitario_con_igv' => $precioConImpuesto,
                'valor_unitario_sin_igv' => $valorUnitario,
                'descuento_linea' => $descuentoLinea,
                'valor_venta' => $baseLinea,
                'igv' => $igvLinea,
                'total_linea' => $totalLinea
            ];

            if ($afectacion === '10') {
                $totalGravado += $baseLinea;
            } elseif ($afectacion === '20') {
                $totalExonerado += $baseLinea;
            } elseif ($afectacion === '30') {
                $totalInafecto += $baseLinea;
            } elseif ($afectacion === '40') {
                $totalExportacion += $baseLinea;
            }

            $totalNota += $totalLinea;
            $valorVenta += $baseLinea;
            $igvTotal += $igvLinea;
            $descuentoNota += $descuentoLinea;
        }

        return [
            'lineas' => $lineas,
            'valor_venta' => round($valorVenta, 2),
            'total_gravado' => round($totalGravado, 2),
            'total_exonerado' => round($totalExonerado, 2),
            'total_inafecto' => round($totalInafecto, 2),
            'total_exportacion' => round($totalExportacion, 2),
            'descuento_total' => round($descuentoNota, 2),
            'igv' => round($igvTotal, 2),
            'total_nota' => round($totalNota, 2),
            'porcentaje_igv_referencial' => round($porcentajeReferencial, 2)
        ];
    }

    private function ajustarCalculoAlSaldo(
        array $calculo,
        float $saldoAcreditable
    ): array {
        $diferencia = round(
            $saldoAcreditable - (float)$calculo['total_nota'],
            2
        );

        if (abs($diferencia) > 0.05) {
            throw new RuntimeException(
                'No fue posible cuadrar la nota total con el saldo del comprobante.'
            );
        }

        if (abs($diferencia) >= 0.01 && count($calculo['lineas']) > 0) {
            $indice = count($calculo['lineas']) - 1;
            $linea = $calculo['lineas'][$indice];
            $linea['total_linea'] = round(
                (float)$linea['total_linea'] + $diferencia,
                2
            );

            $afectacion = (string)($linea['codigo_afectacion_igv'] ?? '10');
            $porcentaje = (float)($linea['porcentaje_igv'] ?? 0);

            if ($afectacion === '10' && $porcentaje > 0) {
                $factor = 1 + ($porcentaje / 100);
                $linea['valor_venta'] = round(
                    $linea['total_linea'] / $factor,
                    2
                );
                $linea['igv'] = round(
                    $linea['total_linea'] - $linea['valor_venta'],
                    2
                );
            } else {
                $linea['valor_venta'] = $linea['total_linea'];
                $linea['igv'] = 0.00;
            }

            $linea['precio_unitario_con_igv'] = round(
                $linea['total_linea'] / $linea['cantidad_nota'],
                6
            );
            $linea['valor_unitario_sin_igv'] = round(
                $linea['valor_venta'] / $linea['cantidad_nota'],
                6
            );
            $calculo['lineas'][$indice] = $linea;
        }

        $calculo['total_nota'] = round($saldoAcreditable, 2);
        $calculo['valor_venta'] = 0.00;
        $calculo['igv'] = 0.00;
        $calculo['total_gravado'] = 0.00;
        $calculo['total_exonerado'] = 0.00;
        $calculo['total_inafecto'] = 0.00;
        $calculo['total_exportacion'] = 0.00;

        foreach ($calculo['lineas'] as $linea) {
            $base = (float)$linea['valor_venta'];
            $calculo['valor_venta'] += $base;
            $calculo['igv'] += (float)$linea['igv'];

            switch ((string)$linea['codigo_afectacion_igv']) {
                case '20':
                    $calculo['total_exonerado'] += $base;
                    break;
                case '30':
                    $calculo['total_inafecto'] += $base;
                    break;
                case '40':
                    $calculo['total_exportacion'] += $base;
                    break;
                case '10':
                default:
                    $calculo['total_gravado'] += $base;
                    break;
            }
        }

        foreach ([
            'valor_venta',
            'igv',
            'total_gravado',
            'total_exonerado',
            'total_inafecto',
            'total_exportacion'
        ] as $clave) {
            $calculo[$clave] = round((float)$calculo[$clave], 2);
        }

        return $calculo;
    }

    private function obtenerPagosOriginales(int $idventa): array
    {
        $resultado = $this->conexion->getDataAll(
            "SELECT
                vp.idforma_pago,
                fp.nombre,
                fp.es_efectivo,
                SUM(vp.monto) AS monto
             FROM venta_pago vp
             INNER JOIN forma_pago fp
                ON fp.idforma_pago = vp.idforma_pago
             WHERE vp.idventa = ?
             GROUP BY vp.idforma_pago, fp.nombre, fp.es_efectivo
             ORDER BY vp.idforma_pago ASC",
            [$idventa]
        );

        return is_array($resultado) ? $resultado : [];
    }

    private function obtenerResumenCredito(
        int $idventa,
        bool $bloquear = false
    ): array {
        if ($bloquear) {
            $this->conexion->getDataAll(
                "SELECT idventa_cuota
                 FROM venta_cuota
                 WHERE idventa = ?
                 ORDER BY idventa_cuota ASC
                 FOR UPDATE",
                [$idventa]
            );
        }

        $resultado = $this->conexion->getData(
            "SELECT
                COALESCE(SUM(monto), 0) AS total_programado,
                COALESCE(SUM(monto_pagado), 0) AS total_pagado,
                COALESCE(SUM(GREATEST(monto - monto_pagado, 0)), 0)
                    AS saldo_pendiente,
                COUNT(*) AS cantidad_cuotas
             FROM venta_cuota
             WHERE idventa = ?",
            [$idventa]
        ) ?: [];

        return [
            'total_programado' => round(
                (float)($resultado['total_programado'] ?? 0),
                2
            ),
            'total_pagado' => round(
                (float)($resultado['total_pagado'] ?? 0),
                2
            ),
            'saldo_pendiente' => round(
                (float)($resultado['saldo_pendiente'] ?? 0),
                2
            ),
            'cantidad_cuotas' => (int)($resultado['cantidad_cuotas'] ?? 0)
        ];
    }

    private function validarPagosDevolucion(
        array $pagosEntrada,
        float $montoDevolver,
        array $sesion,
        int $idusuario
    ): array {
        $montoDevolver = round($montoDevolver, 2);

        if ($montoDevolver <= 0) {
            return [];
        }

        $normalizados = [];
        $formasUsadas = [];
        $suma = 0.00;

        foreach ($pagosEntrada as $pago) {
            if (!is_array($pago)) {
                continue;
            }

            $idforma = (int)($pago['idforma_pago'] ?? 0);
            $monto = round((float)($pago['monto'] ?? 0), 2);
            $numeroOperacion = trim(
                (string)($pago['numero_operacion'] ?? '')
            );

            if ($idforma <= 0 || $monto <= 0) {
                continue;
            }

            if (isset($formasUsadas[$idforma])) {
                throw new RuntimeException(
                    'No repita una misma forma de devolución. '
                    . 'Use una sola fila por cada medio de pago.'
                );
            }

            $preparado =
                $this->cajaGuard->prepararFormaPago(
                    $idforma,
                    $numeroOperacion,
                    [
                        'idusuario' => $idusuario,
                        'idsucursal' => (int)(
                            $sesion['idsucursal_activa']
                            ?? 0
                        ),
                        'idcaja' => (int)(
                            $sesion['idcaja_activa']
                            ?? 0
                        ),
                        'idapertura' => (int)(
                            $sesion['idapertura_activa']
                            ?? 0
                        ),
                        'modo_caja' => (string)(
                            $sesion['modo_caja']
                            ?? 'LEGACY'
                        )
                    ]
                );

            if (
                (int)$preparado['requiere_caja_abierta'] === 1
                && (int)($sesion['puede_cobrar'] ?? 0) !== 1
            ) {
                throw new RuntimeException(
                    'No tiene permiso para realizar devoluciones en efectivo.'
                );
            }

            $normalizados[] = [
                'idforma_pago' => $idforma,
                'nombre' => (string)$preparado['forma_pago'],
                'es_efectivo' =>
                    (int)$preparado['es_efectivo'],
                'requiere_caja_abierta' =>
                    (int)$preparado['requiere_caja_abierta'],
                'requiere_operacion' =>
                    (int)$preparado['requiere_operacion'],
                'idcuenta_financiera' =>
                    (int)$preparado['idcuenta_financiera'],
                'idapertura' =>
                    (int)($preparado['idapertura'] ?? 0) > 0
                        ? (int)$preparado['idapertura']
                        : null,
                'idcaja' =>
                    (int)($preparado['idcaja'] ?? 0) > 0
                        ? (int)$preparado['idcaja']
                        : null,
                'idsucursal' =>
                    (int)($preparado['idsucursal'] ?? 0) > 0
                        ? (int)$preparado['idsucursal']
                        : null,
                'numero_operacion' =>
                    $preparado['numero_operacion'],
                'monto' => $monto
            ];

            $formasUsadas[$idforma] = true;
            $suma += $monto;
        }

        if (count($normalizados) === 0) {
            throw new RuntimeException(
                'Seleccione cómo se devolverá el importe de S/ '
                . number_format($montoDevolver, 2) . '.'
            );
        }

        $suma = round($suma, 2);

        if (abs($suma - $montoDevolver) > 0.01) {
            throw new RuntimeException(
                'Las formas de devolución suman S/ '
                . number_format($suma, 2)
                . ', pero deben sumar S/ '
                . number_format($montoDevolver, 2) . '.'
            );
        }

        return $normalizados;
    }

    private function resolverContextoCaja(
        array $sesion,
        array $venta,
        int $idusuario,
        array $pagos
    ): array {
        $idsucursal = (int)(
            $sesion['idsucursal_activa']
            ?? $venta['idsucursal']
            ?? 0
        );

        /*
         * La nota queda vinculada a una apertura únicamente cuando
         * realmente existe una devolución que afectará efectivo.
         * Medios bancarios/Yape/Plin no deben aparentar salida física
         * de una caja.
         */
        foreach ($pagos as $pago) {
            if (
                (int)($pago['requiere_caja_abierta'] ?? 0) === 1
            ) {
                return [
                    'idsucursal' =>
                        (int)($pago['idsucursal'] ?? 0) > 0
                            ? (int)$pago['idsucursal']
                            : ($idsucursal > 0 ? $idsucursal : null),
                    'idcaja' =>
                        (int)($pago['idcaja'] ?? 0) > 0
                            ? (int)$pago['idcaja']
                            : null,
                    'idapertura' =>
                        (int)($pago['idapertura'] ?? 0) > 0
                            ? (int)$pago['idapertura']
                            : null
                ];
            }
        }

        return [
            'idsucursal' =>
                $idsucursal > 0
                    ? $idsucursal
                    : null,
            'idcaja' => null,
            'idapertura' => null
        ];
    }

    private function aplicarStock(array $nota): void
    {
        $detalles = $this->conexion->getDataAll(
            "SELECT *
             FROM nota_credito_detalle
             WHERE idnota_credito = ?
               AND devuelve_stock = 1
             ORDER BY iddetalle_nota_credito ASC
             FOR UPDATE",
            [(int)$nota['idnota_credito']]
        );

        foreach (is_array($detalles) ? $detalles : [] as $detalle) {
            $idarticulo = (int)$detalle['idarticulo'];
            $cantidad = round((float)$detalle['cantidad_nota'], 3);
            $costo = round((float)$detalle['costo_unitario'], 6);

            if ($cantidad <= 0) {
                continue;
            }

            $articulo = $this->conexion->getData(
                "SELECT idarticulo, stock
                 FROM articulo
                 WHERE idarticulo = ?
                 LIMIT 1
                 FOR UPDATE",
                [$idarticulo]
            );

            if (!is_array($articulo)) {
                throw new RuntimeException(
                    'No se encontró un producto de la nota de crédito.'
                );
            }

            $lotes = $this->conexion->getDataAll(
                "SELECT
                    iddetalle_ingreso,
                    cantidad,
                    precio_compra,
                    stock_venta
                 FROM detalle_ingreso
                 WHERE idarticulo = ?
                   AND afecta_stock = 1
                 ORDER BY
                    CASE
                        WHEN ABS(precio_compra - ?) <= 0.01 THEN 0
                        ELSE 1
                    END,
                    iddetalle_ingreso ASC
                 FOR UPDATE",
                [$idarticulo, $costo]
            );

            if (!is_array($lotes) || count($lotes) === 0) {
                throw new RuntimeException(
                    'No existe un lote de ingreso donde devolver el producto '
                    . $detalle['descripcion_articulo'] . '.'
                );
            }

            $cantidadRestante = $cantidad;

            foreach ($lotes as $lote) {
                if ($cantidadRestante <= 0.0001) {
                    break;
                }

                $cantidadLote = round((float)$lote['cantidad'], 3);
                $stockLote = round((float)$lote['stock_venta'], 3);
                $capacidad = max(
                    round($cantidadLote - $stockLote, 3),
                    0.000
                );

                if ($capacidad <= 0) {
                    continue;
                }

                $devolverLote = min($cantidadRestante, $capacidad);

                $this->conexion->setData(
                    "UPDATE detalle_ingreso
                     SET
                        stock_venta = stock_venta + ?,
                        stock_estado = 1
                     WHERE iddetalle_ingreso = ?",
                    [
                        $devolverLote,
                        (int)$lote['iddetalle_ingreso']
                    ]
                );

                $cantidadRestante = round(
                    $cantidadRestante - $devolverLote,
                    3
                );
            }

            if ($cantidadRestante > 0.0001) {
                throw new RuntimeException(
                    'No existe capacidad suficiente en los lotes de ingreso para devolver '
                    . $detalle['descripcion_articulo'] . '.'
                );
            }

            $this->conexion->setData(
                "UPDATE articulo
                 SET stock = COALESCE(stock, 0) + ?
                 WHERE idarticulo = ?",
                [$cantidad, $idarticulo]
            );

            $stockActual = round(
                (float)$articulo['stock'] + $cantidad,
                3
            );
            $totalIngreso = round($cantidad * $costo, 2);
            $totalExistencia = round($stockActual * $costo, 2);
            $detalleKardex = 'Nota de crédito '
                . $nota['serie_comprobante']
                . '-'
                . $nota['num_comprobante'];

            $this->conexion->setData(
                "INSERT INTO kardex (
                    iddetalle,
                    idarticulo,
                    fecha,
                    detalle,
                    cantidadi,
                    costoui,
                    totali,
                    cantidads,
                    costous,
                    totals,
                    cantidadex,
                    costouex,
                    totalex,
                    tipo,
                    estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?, 'Ingreso', 'Activo')",
                [
                    (int)$nota['idnota_credito'],
                    $idarticulo,
                    date('Y-m-d'),
                    mb_substr($detalleKardex, 0, 64, 'UTF-8'),
                    $cantidad,
                    $costo,
                    $totalIngreso,
                    $stockActual,
                    $costo,
                    $totalExistencia
                ]
            );
        }
    }

    private function aplicarCuotas(array $nota): void
    {
        $pagos = $this->conexion->getData(
            "SELECT COALESCE(SUM(monto), 0) AS total
             FROM nota_credito_pago
             WHERE idnota_credito = ?",
            [(int)$nota['idnota_credito']]
        ) ?: [];

        $montoDevolver = round((float)($pagos['total'] ?? 0), 2);
        $montoReducir = max(
            round((float)$nota['total_nota'] - $montoDevolver, 2),
            0.00
        );

        if ($montoReducir <= 0) {
            return;
        }

        $cuotas = $this->conexion->getDataAll(
            "SELECT *
             FROM venta_cuota
             WHERE idventa = ?
             ORDER BY numero_cuota DESC
             FOR UPDATE",
            [(int)$nota['idventa']]
        );

        $restante = $montoReducir;

        foreach (is_array($cuotas) ? $cuotas : [] as $cuota) {
            if ($restante <= 0.001) {
                break;
            }

            $montoAntes = round((float)$cuota['monto'], 2);
            $pagadoAntes = round((float)$cuota['monto_pagado'], 2);
            $reducible = max(round($montoAntes - $pagadoAntes, 2), 0.00);

            if ($reducible <= 0) {
                continue;
            }

            $reduccion = min($restante, $reducible);
            $montoDespues = round($montoAntes - $reduccion, 2);
            $pagadoDespues = min($pagadoAntes, $montoDespues);

            if ($montoDespues <= 0 && $pagadoDespues <= 0) {
                $estadoDespues = 'ANULADO';
            } elseif ($pagadoDespues >= $montoDespues - 0.01) {
                $estadoDespues = 'PAGADO';
            } elseif ($pagadoDespues > 0) {
                $estadoDespues = 'PARCIAL';
            } else {
                $estadoDespues = 'PENDIENTE';
            }

            $this->conexion->setData(
                "INSERT INTO nota_credito_cuota_ajuste (
                    idnota_credito,
                    idventa_cuota,
                    monto_antes,
                    monto_pagado_antes,
                    monto_reducido,
                    monto_despues,
                    monto_pagado_despues,
                    estado_antes,
                    estado_despues
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    (int)$nota['idnota_credito'],
                    (int)$cuota['idventa_cuota'],
                    $montoAntes,
                    $pagadoAntes,
                    $reduccion,
                    $montoDespues,
                    $pagadoDespues,
                    (string)$cuota['estado'],
                    $estadoDespues
                ]
            );

            $this->conexion->setData(
                "UPDATE venta_cuota
                 SET
                    monto = ?,
                    monto_pagado = ?,
                    estado = ?
                 WHERE idventa_cuota = ?",
                [
                    $montoDespues,
                    $pagadoDespues,
                    $estadoDespues,
                    (int)$cuota['idventa_cuota']
                ]
            );

            $restante = round($restante - $reduccion, 2);
        }

        if ($restante > 0.01) {
            throw new RuntimeException(
                'No se pudo aplicar completamente la reducción a las cuotas.'
            );
        }
    }

    private function aplicarFinanzas(array $nota): array
    {
        $pagos = $this->conexion->getDataAll(
            "SELECT
                ncp.*,
                fp.nombre AS forma_pago,
                fp.es_efectivo,
                COALESCE(fpd.requiere_caja_abierta, 0)
                    AS requiere_caja_abierta,
                COALESCE(fpd.requiere_operacion, 0)
                    AS requiere_operacion
             FROM nota_credito_pago ncp
             INNER JOIN forma_pago fp
                ON fp.idforma_pago = ncp.idforma_pago
             LEFT JOIN forma_pago_destino fpd
                ON fpd.idforma_pago = fp.idforma_pago
             WHERE ncp.idnota_credito = ?
             ORDER BY ncp.idnota_credito_pago ASC
             FOR UPDATE",
            [(int)$nota['idnota_credito']]
        );

        $aplicados = 0;
        $pendientes = 0;

        foreach (is_array($pagos) ? $pagos : [] as $pago) {
            if ((int)($pago['idmovimiento'] ?? 0) > 0) {
                $aplicados++;
                continue;
            }

            $requiereCaja =
                (int)($pago['requiere_caja_abierta'] ?? 0) === 1;

            $idaperturaPago =
                (int)($pago['idapertura'] ?? 0);

            if ($requiereCaja) {
                /*
                 * Nunca insertamos un egreso dentro de una apertura ya
                 * cerrada. Si SUNAT aceptó después del cierre, la
                 * devolución queda pendiente para una apertura actual.
                 */
                if ($idaperturaPago <= 0) {
                    $pendientes++;
                    continue;
                }

                $apertura = $this->conexion->getData(
                    "SELECT idapertura, estado
                     FROM caja_apertura
                     WHERE idapertura = ?
                     LIMIT 1
                     FOR UPDATE",
                    [$idaperturaPago]
                );

                if (
                    !is_array($apertura)
                    || strtoupper(
                        (string)($apertura['estado'] ?? '')
                    ) !== 'ABIERTA'
                ) {
                    $pendientes++;
                    continue;
                }
            }

            $this->crearMovimientoDevolucion(
                $nota,
                $pago,
                (int)($pago['idcuenta_financiera'] ?? 0),
                $requiereCaja && $idaperturaPago > 0
                    ? $idaperturaPago
                    : null,
                (int)$nota['idusuario']
            );

            $aplicados++;
        }

        return [
            'aplicados' => $aplicados,
            'pendientes' => $pendientes,
            'total' => count(is_array($pagos) ? $pagos : [])
        ];
    }

    public function procesarFinanzasPendientes(
        int $idnotaCredito,
        array $sesion
    ): array {
        if ($idnotaCredito <= 0) {
            throw new InvalidArgumentException(
                'La nota de crédito no es válida.'
            );
        }

        $idusuario = (int)($sesion['idusuario'] ?? 0);

        if ($idusuario <= 0) {
            throw new RuntimeException(
                'La sesión del usuario no es válida.'
            );
        }

        if ((int)($sesion['puede_cobrar'] ?? 0) !== 1) {
            throw new RuntimeException(
                'No tiene permiso para procesar devoluciones financieras.'
            );
        }

        $this->conexion->beginTransaction();

        try {
            $nota = $this->conexion->getData(
                "SELECT
                    nc.*,
                    ncs.estado_sunat
                 FROM nota_credito nc
                 INNER JOIN nota_credito_sunat ncs
                    ON ncs.idnota_credito = nc.idnota_credito
                 WHERE nc.idnota_credito = ?
                 LIMIT 1
                 FOR UPDATE",
                [$idnotaCredito]
            );

            if (!is_array($nota)) {
                throw new RuntimeException(
                    'No se encontró la nota de crédito.'
                );
            }

            if (
                strtoupper((string)$nota['estado_sunat'])
                !== 'ACEPTADO'
            ) {
                throw new RuntimeException(
                    'La devolución financiera solo puede procesarse '
                    . 'cuando SUNAT haya aceptado la nota.'
                );
            }

            if ((string)$nota['estado'] === 'ANULADA') {
                throw new RuntimeException(
                    'La nota de crédito se encuentra anulada.'
                );
            }

            if (
                (int)$nota['genera_devolucion_dinero'] !== 1
            ) {
                $this->conexion->commit();

                return [
                    'success' => true,
                    'pendientes' => 0,
                    'mensaje' =>
                        'La nota no requiere una devolución de dinero.'
                ];
            }

            $pagos = $this->conexion->getDataAll(
                "SELECT
                    ncp.*,
                    fp.nombre AS forma_pago,
                    COALESCE(fpd.requiere_caja_abierta, 0)
                        AS requiere_caja_abierta
                 FROM nota_credito_pago ncp
                 INNER JOIN forma_pago fp
                    ON fp.idforma_pago = ncp.idforma_pago
                 LEFT JOIN forma_pago_destino fpd
                    ON fpd.idforma_pago = fp.idforma_pago
                 WHERE ncp.idnota_credito = ?
                 ORDER BY ncp.idnota_credito_pago ASC
                 FOR UPDATE",
                [$idnotaCredito]
            );

            $procesados = 0;
            $ultimaCaja = null;

            foreach (is_array($pagos) ? $pagos : [] as $pago) {
                if ((int)($pago['idmovimiento'] ?? 0) > 0) {
                    continue;
                }

                $preparado =
                    $this->cajaGuard->prepararFormaPago(
                        (int)$pago['idforma_pago'],
                        (string)($pago['numero_operacion'] ?? ''),
                        [
                            'idusuario' => $idusuario,
                            'idsucursal' => (int)(
                                $sesion['idsucursal_activa']
                                ?? 0
                            ),
                            'idcaja' => (int)(
                                $sesion['idcaja_activa']
                                ?? 0
                            ),
                            'idapertura' => (int)(
                                $sesion['idapertura_activa']
                                ?? 0
                            ),
                            'modo_caja' => (string)(
                                $sesion['modo_caja']
                                ?? 'LEGACY'
                            )
                        ]
                    );

                $idaperturaActual =
                    (int)($preparado['idapertura'] ?? 0) > 0
                        ? (int)$preparado['idapertura']
                        : null;

                $idcuentaActual =
                    (int)$preparado['idcuenta_financiera'];

                $this->crearMovimientoDevolucion(
                    $nota,
                    $pago,
                    $idcuentaActual,
                    $idaperturaActual,
                    $idusuario
                );

                $this->conexion->setData(
                    "UPDATE nota_credito_pago
                     SET
                        idcuenta_financiera = ?,
                        idapertura = ?,
                        numero_operacion = ?
                     WHERE idnota_credito_pago = ?",
                    [
                        $idcuentaActual,
                        $idaperturaActual,
                        $preparado['numero_operacion'],
                        (int)$pago['idnota_credito_pago']
                    ]
                );

                if ($idaperturaActual !== null) {
                    $ultimaCaja = [
                        'idsucursal' =>
                            (int)($preparado['idsucursal'] ?? 0),
                        'idcaja' =>
                            (int)($preparado['idcaja'] ?? 0),
                        'idapertura' => $idaperturaActual
                    ];
                }

                $procesados++;
            }

            $pendientes = (int)$this->conexion->getValue(
                "SELECT COUNT(*)
                 FROM nota_credito_pago
                 WHERE idnota_credito = ?
                   AND idmovimiento IS NULL",
                [$idnotaCredito]
            );

            if ($pendientes === 0) {
                $this->conexion->setData(
                    "UPDATE nota_credito
                     SET
                        finanzas_aplicadas = 1,
                        fecha_aplicacion_finanzas =
                            COALESCE(fecha_aplicacion_finanzas, NOW())
                     WHERE idnota_credito = ?",
                    [$idnotaCredito]
                );
            }

            if (is_array($ultimaCaja)) {
                $this->conexion->setData(
                    "UPDATE nota_credito
                     SET
                        idsucursal = ?,
                        idcaja = ?,
                        idapertura = ?
                     WHERE idnota_credito = ?",
                    [
                        $ultimaCaja['idsucursal'] > 0
                            ? $ultimaCaja['idsucursal']
                            : null,
                        $ultimaCaja['idcaja'] > 0
                            ? $ultimaCaja['idcaja']
                            : null,
                        $ultimaCaja['idapertura'],
                        $idnotaCredito
                    ]
                );
            }

            $this->conexion->commit();

            return [
                'success' => true,
                'procesados' => $procesados,
                'pendientes' => $pendientes,
                'mensaje' =>
                    $pendientes === 0
                        ? 'Devolución financiera procesada correctamente.'
                        : 'Todavía existen devoluciones pendientes.'
            ];
        } catch (Throwable $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    private function crearMovimientoDevolucion(
        array $nota,
        array $pago,
        int $idcuentaFinanciera,
        ?int $idapertura,
        int $idusuario
    ): int {
        if ($idcuentaFinanciera <= 0) {
            throw new RuntimeException(
                'La forma de devolución no tiene una cuenta financiera válida.'
            );
        }

        $concepto = 'Devolución por nota de crédito '
            . $nota['serie_comprobante']
            . '-'
            . $nota['num_comprobante']
            . ' / '
            . $nota['serie_documento_modificado']
            . '-'
            . $nota['numero_documento_modificado'];

        $idmovimiento =
            (int)$this->conexion->setDataReturnId(
                "INSERT INTO movimiento_financiero (
                    fecha_hora,
                    tipo,
                    origen,
                    idreferencia,
                    idforma_pago,
                    idcuenta_financiera,
                    idapertura,
                    monto,
                    concepto,
                    idusuario,
                    estado
                ) VALUES (
                    NOW(),
                    'EGRESO',
                    'NOTA_CREDITO',
                    ?, ?, ?, ?, ?, ?, ?, 'ACTIVO'
                )",
                [
                    (int)$nota['idnota_credito'],
                    (int)$pago['idforma_pago'],
                    $idcuentaFinanciera,
                    $idapertura,
                    round((float)$pago['monto'], 2),
                    mb_substr(
                        $concepto,
                        0,
                        255,
                        'UTF-8'
                    ),
                    $idusuario
                ]
            );

        if ($idmovimiento <= 0) {
            throw new RuntimeException(
                'No se pudo registrar el egreso de la devolución.'
            );
        }

        $this->conexion->setData(
            "UPDATE nota_credito_pago
             SET idmovimiento = ?
             WHERE idnota_credito_pago = ?",
            [
                $idmovimiento,
                (int)$pago['idnota_credito_pago']
            ]
        );

        return $idmovimiento;
    }

    private function normalizarCondicionPago(string $valor): string
    {
        $texto = $this->normalizarTexto($valor);

        if ($texto === '4' || str_contains($texto, 'CREDITO')) {
            return 'CREDITO';
        }

        return 'CONTADO';
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = trim($texto);
        $ascii = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $texto
        );

        return strtoupper($ascii !== false ? $ascii : $texto);
    }


    private function datosTributoPorAfectacion(
        string $afectacion,
        array $origen = []
    ): array {
        $mapa = [
            '10' => ['1000', 'IGV', 'VAT'],
            '20' => ['9997', 'EXO', 'VAT'],
            '30' => ['9998', 'INA', 'FRE'],
            '40' => ['9995', 'EXP', 'FRE']
        ];
        $base = $mapa[$afectacion] ?? $mapa['10'];

        return [
            'codigo_tributo' => trim((string)($origen['codigo_tributo'] ?? $base[0])) ?: $base[0],
            'nombre_tributo' => trim((string)($origen['nombre_tributo'] ?? $base[1])) ?: $base[1],
            'tipo_tributo' => trim((string)($origen['tipo_tributo'] ?? $base[2])) ?: $base[2]
        ];
    }

    private function normalizarUnidad(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));

        return preg_match('/^[A-Z0-9]{2,3}$/', $codigo)
            ? $codigo
            : 'NIU';
    }

    private function formatearCantidad(float $cantidad): string
    {
        if (abs($cantidad - round($cantidad)) < 0.0001) {
            return number_format($cantidad, 0, '.', '');
        }

        return rtrim(
            rtrim(number_format($cantidad, 3, '.', ''), '0'),
            '.'
        );
    }
}
