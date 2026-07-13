<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';

date_default_timezone_set('America/Lima');

class Cobranza
{
    private Conexion $conexion;

    public function __construct(?Conexion $conexion = null)
    {
        $this->conexion = $conexion instanceof Conexion
            ? $conexion
            : new Conexion();
    }

    public function listarCuentasPorCobrar(): array
    {
        $sql = "
            SELECT
                v.idventa,
                DATE_FORMAT(v.fecha_hora, '%d/%m/%Y %H:%i') AS fecha,
                p.nombre AS cliente,
                v.serie_comprobante,
                v.num_comprobante,
                ROUND(SUM(vc.monto), 2) AS total_credito,
                ROUND(SUM(vc.monto_pagado), 2) AS total_pagado,
                ROUND(SUM(GREATEST(vc.monto - vc.monto_pagado, 0)), 2) AS saldo,
                DATE_FORMAT(
                    MIN(CASE
                        WHEN vc.monto_pagado < vc.monto
                        THEN vc.fecha_vencimiento
                    END),
                    '%d/%m/%Y'
                ) AS proximo_vencimiento,
                CASE
                    WHEN SUM(GREATEST(vc.monto - vc.monto_pagado, 0)) <= 0.009
                    THEN 'PAGADO'
                    WHEN SUM(vc.monto_pagado) > 0
                    THEN 'PARCIAL'
                    WHEN MIN(CASE
                        WHEN vc.monto_pagado < vc.monto
                        THEN vc.fecha_vencimiento
                    END) < CURDATE()
                    THEN 'VENCIDO'
                    ELSE 'PENDIENTE'
                END AS estado_cobranza,
                UPPER(COALESCE(vs.estado_sunat, 'NO_ENVIADO')) AS estado_sunat
            FROM venta v
            INNER JOIN persona p
                ON p.idpersona = v.idcliente
            INNER JOIN venta_cuota vc
                ON vc.idventa = v.idventa
            LEFT JOIN venta_sunat vs
                ON vs.idventa = v.idventa
            WHERE v.estado = 'Aceptado'
            GROUP BY
                v.idventa,
                v.fecha_hora,
                p.nombre,
                v.serie_comprobante,
                v.num_comprobante,
                vs.estado_sunat
            ORDER BY
                CASE
                    WHEN SUM(GREATEST(vc.monto - vc.monto_pagado, 0)) > 0
                    THEN 0 ELSE 1
                END,
                MIN(CASE
                    WHEN vc.monto_pagado < vc.monto
                    THEN vc.fecha_vencimiento
                END) ASC,
                v.idventa DESC
        ";

        $resultado = $this->conexion->getDataAll($sql);
        return is_array($resultado) ? $resultado : [];
    }

    public function obtenerDetalleVenta(int $idventa): array
    {
        $venta = $this->conexion->getData(
            "SELECT
                v.idventa,
                v.idcliente,
                p.nombre AS cliente,
                p.tipo_documento,
                p.num_documento,
                v.tipo_comprobante,
                v.serie_comprobante,
                v.num_comprobante,
                DATE_FORMAT(v.fecha_hora, '%d/%m/%Y %H:%i:%s') AS fecha,
                v.total_venta,
                v.tipo_pago,
                v.estado,
                UPPER(COALESCE(vs.estado_sunat, 'NO_ENVIADO')) AS estado_sunat
             FROM venta v
             INNER JOIN persona p
                ON p.idpersona = v.idcliente
             LEFT JOIN venta_sunat vs
                ON vs.idventa = v.idventa
             WHERE v.idventa = ?
             LIMIT 1",
            [$idventa]
        );

        if (!$venta) {
            throw new RuntimeException('No se encontró la venta.');
        }

        $cuotas = $this->conexion->getDataAll(
            "SELECT
                vc.idventa_cuota,
                vc.numero_cuota,
                vc.codigo,
                vc.monto,
                vc.monto_pagado,
                ROUND(GREATEST(vc.monto - vc.monto_pagado, 0), 2) AS saldo,
                DATE_FORMAT(vc.fecha_vencimiento, '%d/%m/%Y') AS fecha_vencimiento,
                CASE
                    WHEN vc.monto_pagado >= vc.monto THEN 'PAGADO'
                    WHEN vc.monto_pagado > 0 THEN 'PARCIAL'
                    WHEN vc.fecha_vencimiento < CURDATE() THEN 'VENCIDO'
                    ELSE 'PENDIENTE'
                END AS estado
             FROM venta_cuota vc
             WHERE vc.idventa = ?
             ORDER BY vc.numero_cuota ASC",
            [$idventa]
        );

        $historial = $this->conexion->getDataAll(
            "SELECT
                c.idcobranza,
                c.codigo,
                DATE_FORMAT(c.fecha_hora, '%d/%m/%Y %H:%i') AS fecha_hora,
                c.monto_total,
                c.estado,
                u.nombre AS usuario,
                GROUP_CONCAT(
                    DISTINCT CONCAT(fp.nombre, ': S/ ', FORMAT(cp.monto, 2))
                    ORDER BY cp.idcobranza_pago
                    SEPARATOR ' | '
                ) AS formas_pago
             FROM cobranza c
             INNER JOIN usuario u
                ON u.idusuario = c.idusuario
             LEFT JOIN cobranza_pago cp
                ON cp.idcobranza = c.idcobranza
             LEFT JOIN forma_pago fp
                ON fp.idforma_pago = cp.idforma_pago
             WHERE c.idventa = ?
             GROUP BY
                c.idcobranza,
                c.codigo,
                c.fecha_hora,
                c.monto_total,
                c.estado,
                u.nombre
             ORDER BY c.idcobranza DESC",
            [$idventa]
        );

        $venta['cuotas'] = is_array($cuotas) ? $cuotas : [];
        $venta['historial'] = is_array($historial) ? $historial : [];

        return $venta;
    }

    public function obtenerFormasPagoCobranza(): array
    {
        $resultado = $this->conexion->getDataAll(
            "SELECT
                fp.idforma_pago,
                fp.nombre,
                fp.es_efectivo,
                fpd.idcuenta_financiera,
                cf.nombre AS cuenta_destino,
                cf.tipo AS tipo_cuenta,
                fpd.requiere_caja_abierta,
                fpd.requiere_operacion
             FROM forma_pago fp
             INNER JOIN forma_pago_destino fpd
                ON fpd.idforma_pago = fp.idforma_pago
             LEFT JOIN cuenta_financiera cf
                ON cf.idcuenta_financiera = fpd.idcuenta_financiera
             WHERE fp.activo = 1
               AND fp.condicion = 1
               AND fpd.permite_cobranza = 1
             ORDER BY fp.es_efectivo DESC, fp.nombre ASC"
        );

        return is_array($resultado) ? $resultado : [];
    }

    public function registrar(
        int $idventa,
        int $idusuario,
        array $aplicaciones,
        array $pagos,
        string $observacion = ''
    ): array {
        if ($idventa <= 0) {
            throw new InvalidArgumentException('La venta no es válida.');
        }

        if ($idusuario <= 0) {
            throw new InvalidArgumentException('El usuario de la sesión no es válido.');
        }

        if (empty($aplicaciones)) {
            throw new RuntimeException('Debe aplicar el pago al menos a una cuota.');
        }

        if (empty($pagos)) {
            throw new RuntimeException('Debe registrar al menos una forma de pago.');
        }

        $transaccionActiva = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionActiva = true;

            $venta = $this->conexion->getData(
                "SELECT
                    v.idventa,
                    v.idcliente,
                    v.tipo_comprobante,
                    v.serie_comprobante,
                    v.num_comprobante,
                    v.estado,
                    UPPER(COALESCE(vs.estado_sunat, 'NO_ENVIADO')) AS estado_sunat
                 FROM venta v
                 LEFT JOIN venta_sunat vs
                    ON vs.idventa = v.idventa
                 WHERE v.idventa = ?
                 LIMIT 1
                 FOR UPDATE",
                [$idventa]
            );

            if (!$venta) {
                throw new RuntimeException('No se encontró la venta.');
            }

            if (strtoupper(trim((string)$venta['estado'])) !== 'ACEPTADO') {
                throw new RuntimeException('La venta está anulada o no se encuentra activa.');
            }

            $esFactura = stripos((string)$venta['tipo_comprobante'], 'factura') !== false;

            if ($esFactura && strtoupper((string)$venta['estado_sunat']) !== 'ACEPTADO') {
                throw new RuntimeException(
                    'La factura debe estar aceptada por SUNAT antes de registrar una cobranza.'
                );
            }

            $idsAplicados = [];
            $cuotasBloqueadas = [];
            $totalAplicado = 0.00;

            foreach ($aplicaciones as $aplicacion) {
                $idCuota = (int)($aplicacion['idventa_cuota'] ?? 0);
                $montoAplicado = round((float)($aplicacion['monto'] ?? 0), 2);

                if ($idCuota <= 0 || $montoAplicado <= 0) {
                    throw new RuntimeException('Existe una aplicación de cuota inválida.');
                }

                if (isset($idsAplicados[$idCuota])) {
                    throw new RuntimeException('Una cuota fue incluida más de una vez.');
                }

                $idsAplicados[$idCuota] = true;

                $cuota = $this->conexion->getData(
                    "SELECT
                        idventa_cuota,
                        idventa,
                        numero_cuota,
                        codigo,
                        monto,
                        monto_pagado,
                        fecha_vencimiento,
                        estado
                     FROM venta_cuota
                     WHERE idventa_cuota = ?
                       AND idventa = ?
                     LIMIT 1
                     FOR UPDATE",
                    [$idCuota, $idventa]
                );

                if (!$cuota) {
                    throw new RuntimeException('No se encontró una de las cuotas seleccionadas.');
                }

                $saldo = round(
                    (float)$cuota['monto'] - (float)$cuota['monto_pagado'],
                    2
                );

                if ($saldo <= 0) {
                    throw new RuntimeException($cuota['codigo'] . ' ya se encuentra pagada.');
                }

                if ($montoAplicado - $saldo > 0.009) {
                    throw new RuntimeException(
                        'El monto aplicado a ' . $cuota['codigo']
                        . ' supera su saldo de S/ '
                        . number_format($saldo, 2, '.', '') . '.'
                    );
                }

                $cuotasBloqueadas[] = [
                    'cuota' => $cuota,
                    'monto_aplicado' => $montoAplicado
                ];

                $totalAplicado += $montoAplicado;
            }

            $totalAplicado = round($totalAplicado, 2);
            $formasUsadas = [];
            $pagosValidados = [];
            $totalPagos = 0.00;

            foreach ($pagos as $pago) {
                $idFormaPago = (int)($pago['idforma_pago'] ?? 0);
                $montoPago = round((float)($pago['monto'] ?? 0), 2);
                $numeroOperacion = trim((string)($pago['numero_operacion'] ?? ''));

                if ($idFormaPago <= 0 || $montoPago <= 0) {
                    throw new RuntimeException('Existe una forma de pago inválida.');
                }

                if (isset($formasUsadas[$idFormaPago])) {
                    throw new RuntimeException('No repita la misma forma de pago.');
                }

                $formasUsadas[$idFormaPago] = true;

                $forma = $this->conexion->getData(
                    "SELECT
                        fp.idforma_pago,
                        fp.nombre,
                        fp.es_efectivo,
                        fp.activo,
                        fp.condicion,
                        fpd.idcuenta_financiera,
                        fpd.requiere_caja_abierta,
                        fpd.requiere_operacion,
                        fpd.permite_cobranza
                     FROM forma_pago fp
                     INNER JOIN forma_pago_destino fpd
                        ON fpd.idforma_pago = fp.idforma_pago
                     WHERE fp.idforma_pago = ?
                     LIMIT 1
                     FOR UPDATE",
                    [$idFormaPago]
                );

                if (
                    !$forma
                    || (int)$forma['activo'] !== 1
                    || (int)$forma['condicion'] !== 1
                    || (int)$forma['permite_cobranza'] !== 1
                ) {
                    throw new RuntimeException('La forma de pago seleccionada no admite cobranzas.');
                }

                if ((int)$forma['requiere_operacion'] === 1 && $numeroOperacion === '') {
                    throw new RuntimeException(
                        'Debe ingresar el número de operación para ' . $forma['nombre'] . '.'
                    );
                }

                $idApertura = null;

                if ((int)$forma['requiere_caja_abierta'] === 1) {
                    $cajas = $this->conexion->getDataAll(
                        "SELECT idapertura
                         FROM caja_apertura
                         WHERE idusuario = ?
                           AND estado = 'ABIERTA'
                         ORDER BY idapertura DESC
                         FOR UPDATE",
                        [$idusuario]
                    );

                    $cantidadCajas = is_array($cajas) ? count($cajas) : 0;

                    if ($cantidadCajas === 0) {
                        throw new RuntimeException(
                            'Debe abrir caja antes de registrar una cobranza en efectivo.'
                        );
                    }

                    if ($cantidadCajas > 1) {
                        throw new RuntimeException(
                            'Se encontraron varias cajas abiertas para el usuario.'
                        );
                    }

                    $idApertura = (int)$cajas[0]['idapertura'];
                }

                if ((int)($forma['idcuenta_financiera'] ?? 0) <= 0) {
                    throw new RuntimeException(
                        'La forma de pago ' . $forma['nombre']
                        . ' no tiene una cuenta financiera configurada.'
                    );
                }

                $pagosValidados[] = [
                    'forma' => $forma,
                    'monto' => $montoPago,
                    'numero_operacion' => $numeroOperacion,
                    'idapertura' => $idApertura
                ];

                $totalPagos += $montoPago;
            }

            $totalPagos = round($totalPagos, 2);

            if (abs($totalAplicado - $totalPagos) > 0.01) {
                throw new RuntimeException(
                    'El total aplicado a cuotas debe coincidir con el total de las formas de pago.'
                );
            }

            $codigoTemporal = 'TMP-' . bin2hex(random_bytes(6));

            $idCobranza = $this->conexion->setDataReturnId(
                "INSERT INTO cobranza (
                    codigo,
                    idventa,
                    idcliente,
                    fecha_hora,
                    monto_total,
                    idusuario,
                    observacion,
                    estado
                ) VALUES (?, ?, ?, NOW(), ?, ?, ?, 'REGISTRADA')",
                [
                    $codigoTemporal,
                    $idventa,
                    $venta['idcliente'],
                    $totalAplicado,
                    $idusuario,
                    $observacion !== '' ? $observacion : null
                ]
            );

            if (!$idCobranza) {
                throw new RuntimeException('No se pudo registrar la cobranza.');
            }

            $codigoCobranza = 'COB-' . str_pad(
                (string)$idCobranza,
                8,
                '0',
                STR_PAD_LEFT
            );

            $this->conexion->setData(
                "UPDATE cobranza SET codigo = ? WHERE idcobranza = ?",
                [$codigoCobranza, $idCobranza]
            );

            foreach ($cuotasBloqueadas as $item) {
                $cuota = $item['cuota'];
                $montoAplicado = (float)$item['monto_aplicado'];

                $this->conexion->setData(
                    "INSERT INTO cobranza_detalle (
                        idcobranza,
                        idventa_cuota,
                        monto_aplicado
                    ) VALUES (?, ?, ?)",
                    [
                        $idCobranza,
                        $cuota['idventa_cuota'],
                        $montoAplicado
                    ]
                );

                $nuevoPagado = round(
                    (float)$cuota['monto_pagado'] + $montoAplicado,
                    2
                );

                $montoCuota = round((float)$cuota['monto'], 2);
                $nuevoEstado = $nuevoPagado >= $montoCuota - 0.009
                    ? 'PAGADO'
                    : 'PARCIAL';

                $fechaPago = $nuevoEstado === 'PAGADO'
                    ? date('Y-m-d H:i:s')
                    : null;

                $this->conexion->setData(
                    "UPDATE venta_cuota
                     SET monto_pagado = ?, estado = ?, fecha_pago = ?
                     WHERE idventa_cuota = ?",
                    [
                        $nuevoPagado,
                        $nuevoEstado,
                        $fechaPago,
                        $cuota['idventa_cuota']
                    ]
                );
            }

            foreach ($pagosValidados as $pagoValidado) {
                $forma = $pagoValidado['forma'];

                $idCobranzaPago = $this->conexion->setDataReturnId(
                    "INSERT INTO cobranza_pago (
                        idcobranza,
                        idforma_pago,
                        idcuenta_financiera,
                        idapertura,
                        monto,
                        numero_operacion
                    ) VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $idCobranza,
                        $forma['idforma_pago'],
                        $forma['idcuenta_financiera'],
                        $pagoValidado['idapertura'],
                        $pagoValidado['monto'],
                        $pagoValidado['numero_operacion'] !== ''
                            ? $pagoValidado['numero_operacion']
                            : null
                    ]
                );

                if (!$idCobranzaPago) {
                    throw new RuntimeException('No se pudo registrar el detalle del pago.');
                }

                $concepto = 'Cobranza ' . $codigoCobranza
                    . ' de ' . $venta['serie_comprobante']
                    . '-' . $venta['num_comprobante'];

                $this->conexion->setData(
                    "INSERT INTO movimiento_financiero (
                        fecha_hora,
                        tipo,
                        origen,
                        idreferencia,
                        idcobranza_pago,
                        idforma_pago,
                        idcuenta_financiera,
                        idapertura,
                        monto,
                        concepto,
                        idusuario,
                        estado
                    ) VALUES (
                        NOW(),
                        'INGRESO',
                        'COBRANZA',
                        ?, ?, ?, ?, ?, ?, ?, ?,
                        'ACTIVO'
                    )",
                    [
                        $idCobranza,
                        $idCobranzaPago,
                        $forma['idforma_pago'],
                        $forma['idcuenta_financiera'],
                        $pagoValidado['idapertura'],
                        $pagoValidado['monto'],
                        $concepto,
                        $idusuario
                    ]
                );
            }

            $this->conexion->commit();
            $transaccionActiva = false;

            return [
                'success' => true,
                'idcobranza' => (int)$idCobranza,
                'codigo' => $codigoCobranza,
                'monto_total' => $totalAplicado,
                'mensaje' => 'Cobranza registrada correctamente.'
            ];
        } catch (Throwable $e) {
            if ($transaccionActiva) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log('[COBRANZA ROLLBACK] ' . $rollbackError->getMessage());
                }
            }

            error_log('[COBRANZA] ' . $e->getMessage());
            throw $e;
        }
    }
}
