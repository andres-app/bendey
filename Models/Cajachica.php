<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';

date_default_timezone_set('America/Lima');

class Cajachica
{
    private Conexion $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    /*
    |--------------------------------------------------------------------------
    | RESUMEN POR TIPO DE COMPROBANTE Y FORMA DE PAGO
    |--------------------------------------------------------------------------
    */
    public function resumen(
        string $fechaInicio,
        string $fechaFin,
        ?int $idusuario = null
    ): array {
        $sql = "
            SELECT
                v.tipo_comprobante,
                fp.nombre AS forma_pago,
                SUM(vp.monto) AS total

            FROM venta_pago vp

            INNER JOIN venta v
                ON v.idventa = vp.idventa

            INNER JOIN forma_pago fp
                ON fp.idforma_pago = vp.idforma_pago

            WHERE DATE(v.fecha_hora) BETWEEN ? AND ?
              AND v.estado = 'Aceptado'
        ";

        $parametros = [
            $fechaInicio,
            $fechaFin
        ];

        if ($idusuario !== null && $idusuario > 0) {
            $sql .= " AND v.idusuario = ?";
            $parametros[] = $idusuario;
        }

        $sql .= "
            GROUP BY
                v.tipo_comprobante,
                fp.nombre

            ORDER BY
                v.tipo_comprobante,
                fp.nombre
        ";

        $resultado = $this->conexion->getDataAll(
            $sql,
            $parametros
        );

        $resultado = is_array($resultado)
            ? $resultado
            : [];

        /*
         * Cuando exista movimiento_financiero,
         * agregar las cobranzas al resumen.
         */
        if ($this->tablaExiste('movimiento_financiero')) {
            $sqlCobranzas = "
                SELECT
                    'COBRANZAS' AS tipo_comprobante,
                    fp.nombre AS forma_pago,

                    SUM(
                        CASE
                            WHEN mf.tipo = 'INGRESO'
                            THEN mf.monto
                            ELSE -mf.monto
                        END
                    ) AS total

                FROM movimiento_financiero mf

                INNER JOIN forma_pago fp
                    ON fp.idforma_pago = mf.idforma_pago

                WHERE DATE(mf.fecha_hora) BETWEEN ? AND ?
                  AND mf.estado = 'ACTIVO'
                  AND mf.origen = 'COBRANZA'
            ";

            $parametrosCobranzas = [
                $fechaInicio,
                $fechaFin
            ];

            if ($idusuario !== null && $idusuario > 0) {
                $sqlCobranzas .= " AND mf.idusuario = ?";
                $parametrosCobranzas[] = $idusuario;
            }

            $sqlCobranzas .= "
                GROUP BY fp.nombre
                HAVING total <> 0
            ";

            $cobranzas = $this->conexion->getDataAll(
                $sqlCobranzas,
                $parametrosCobranzas
            );

            if (is_array($cobranzas)) {
                $resultado = array_merge(
                    $resultado,
                    $cobranzas
                );
            }
        }

        return $resultado;
    }

    /*
    |--------------------------------------------------------------------------
    | TOTALES GENERALES
    |--------------------------------------------------------------------------
    */
    public function totales(
        string $fechaInicio,
        string $fechaFin,
        ?int $idusuario = null
    ): array {
        $sql = "
            SELECT
                COALESCE(
                    SUM(vp.monto),
                    0
                ) AS ingresos_ventas,

                COALESCE(
                    SUM(
                        CASE
                            WHEN fp.es_efectivo = 1
                            THEN vp.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS efectivo_ventas,

                COALESCE(
                    SUM(
                        CASE
                            WHEN fp.es_efectivo = 0
                            THEN vp.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS no_efectivo_ventas

            FROM venta_pago vp

            INNER JOIN venta v
                ON v.idventa = vp.idventa

            INNER JOIN forma_pago fp
                ON fp.idforma_pago = vp.idforma_pago

            WHERE DATE(v.fecha_hora) BETWEEN ? AND ?
              AND v.estado = 'Aceptado'
        ";

        $parametros = [
            $fechaInicio,
            $fechaFin
        ];

        if ($idusuario !== null && $idusuario > 0) {
            $sql .= " AND v.idusuario = ?";
            $parametros[] = $idusuario;
        }

        $ventas = $this->conexion->getData(
            $sql,
            $parametros
        ) ?: [];

        $ingresosVentas = round(
            (float)($ventas['ingresos_ventas'] ?? 0),
            2
        );

        $efectivoVentas = round(
            (float)($ventas['efectivo_ventas'] ?? 0),
            2
        );

        $noEfectivoVentas = round(
            (float)($ventas['no_efectivo_ventas'] ?? 0),
            2
        );

        $ingresosMovimientos = 0.00;
        $efectivoMovimientos = 0.00;
        $egresos = 0.00;
        $egresosEfectivo = 0.00;

        if ($this->tablaExiste('movimiento_financiero')) {
            $sqlMovimientos = "
                SELECT
                    COALESCE(
                        SUM(
                            CASE
                                WHEN mf.tipo = 'INGRESO'
                                THEN mf.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS ingresos,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN mf.tipo = 'INGRESO'
                                 AND fp.es_efectivo = 1
                                THEN mf.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS ingresos_efectivo,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN mf.tipo = 'EGRESO'
                                THEN mf.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS egresos,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN mf.tipo = 'EGRESO'
                                 AND fp.es_efectivo = 1
                                THEN mf.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS egresos_efectivo

                FROM movimiento_financiero mf

                INNER JOIN forma_pago fp
                    ON fp.idforma_pago = mf.idforma_pago

                WHERE DATE(mf.fecha_hora) BETWEEN ? AND ?
                  AND mf.estado = 'ACTIVO'
                  AND mf.origen <> 'VENTA'
            ";

            $parametrosMovimientos = [
                $fechaInicio,
                $fechaFin
            ];

            if ($idusuario !== null && $idusuario > 0) {
                $sqlMovimientos .= " AND mf.idusuario = ?";
                $parametrosMovimientos[] = $idusuario;
            }

            $movimientos = $this->conexion->getData(
                $sqlMovimientos,
                $parametrosMovimientos
            ) ?: [];

            $ingresosMovimientos = round(
                (float)($movimientos['ingresos'] ?? 0),
                2
            );

            $efectivoMovimientos = round(
                (float)($movimientos['ingresos_efectivo'] ?? 0),
                2
            );

            $egresos = round(
                (float)($movimientos['egresos'] ?? 0),
                2
            );

            $egresosEfectivo = round(
                (float)($movimientos['egresos_efectivo'] ?? 0),
                2
            );
        }

        return [
            'ingresos' => round(
                $ingresosVentas + $ingresosMovimientos,
                2
            ),

            'efectivo' => round(
                $efectivoVentas + $efectivoMovimientos,
                2
            ),

            'no_efectivo' => round(
                $noEfectivoVentas
                + (
                    $ingresosMovimientos
                    - $efectivoMovimientos
                ),
                2
            ),

            'egresos' => $egresos,

            'egresos_efectivo' => $egresosEfectivo
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CAJA ABIERTA DEL USUARIO
    |--------------------------------------------------------------------------
    */
    public function obtenerCajaAbiertaUsuario(
        int $idusuario,
        bool $bloquear = false
    ): ?array {
        if ($idusuario <= 0) {
            return null;
        }

        $sql = "
            SELECT *
            FROM caja_apertura
            WHERE idusuario = ?
              AND estado = 'ABIERTA'
            ORDER BY idapertura DESC
            LIMIT 1
        ";

        if ($bloquear) {
            $sql .= " FOR UPDATE";
        }

        $resultado = $this->conexion->getData(
            $sql,
            [$idusuario]
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | CONTAR CAJAS ABIERTAS
    |--------------------------------------------------------------------------
    */
    public function contarCajasAbiertasUsuario(
        int $idusuario
    ): int {
        $resultado = $this->conexion->getData(
            "SELECT COUNT(*) AS cantidad
             FROM caja_apertura
             WHERE idusuario = ?
               AND estado = 'ABIERTA'",
            [$idusuario]
        );

        return (int)($resultado['cantidad'] ?? 0);
    }

    /*
    |--------------------------------------------------------------------------
    | REGISTRAR APERTURA
    |--------------------------------------------------------------------------
    */
    public function registrarApertura(
        float $monto,
        int $idusuario
    ): array {
        if ($idusuario <= 0) {
            return [
                'status' => 'error',
                'message' => 'El usuario de la sesión no es válido.'
            ];
        }

        if ($monto < 0) {
            return [
                'status' => 'error',
                'message' => 'El monto de apertura no puede ser negativo.'
            ];
        }

        $transaccionActiva = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionActiva = true;

            $abiertas = $this->conexion->getDataAll(
                "SELECT idapertura
                 FROM caja_apertura
                 WHERE idusuario = ?
                   AND estado = 'ABIERTA'
                 FOR UPDATE",
                [$idusuario]
            );

            if (is_array($abiertas) && count($abiertas) > 0) {
                throw new RuntimeException(
                    'Ya existe una caja abierta para este usuario.'
                );
            }

            $fecha = date('Y-m-d');
            $createdAt = date('Y-m-d H:i:s');

            $idapertura =
                $this->conexion->setDataReturnId(
                    "INSERT INTO caja_apertura (
                        fecha,
                        monto_apertura,
                        idusuario,
                        estado,
                        created_at
                    ) VALUES (?, ?, ?, 'ABIERTA', ?)",
                    [
                        $fecha,
                        round($monto, 2),
                        $idusuario,
                        $createdAt
                    ]
                );

            if (!$idapertura) {
                throw new RuntimeException(
                    'No se pudo registrar la apertura de caja.'
                );
            }

            $this->conexion->commit();
            $transaccionActiva = false;

            return [
                'status' => 'ok',
                'message' => 'Caja abierta correctamente.',
                'idapertura' => (int)$idapertura
            ];
        } catch (Throwable $e) {
            if ($transaccionActiva) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[CAJA ROLLBACK] '
                        . $rollbackError->getMessage()
                    );
                }
            }

            error_log(
                '[APERTURA CAJA] '
                . $e->getMessage()
            );

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | APERTURA MÁS RECIENTE DE HOY
    |--------------------------------------------------------------------------
    */
    public function obtenerAperturaHoyUsuario(
        int $idusuario
    ): ?array {
        $resultado = $this->conexion->getData(
            "SELECT *
             FROM caja_apertura
             WHERE fecha = ?
               AND idusuario = ?
             ORDER BY idapertura DESC
             LIMIT 1",
            [
                date('Y-m-d'),
                $idusuario
            ]
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | APERTURA POR FECHA
    |--------------------------------------------------------------------------
    */
    public function obtenerAperturaPorFecha(
        string $fecha,
        ?int $idusuario = null
    ): ?array {
        $sql = "
            SELECT
                idapertura,
                fecha,
                monto_apertura,
                idusuario,
                estado,
                created_at,
                fecha_cierre

            FROM caja_apertura

            WHERE fecha = ?
        ";

        $parametros = [$fecha];

        if ($idusuario !== null && $idusuario > 0) {
            $sql .= " AND idusuario = ?";
            $parametros[] = $idusuario;
        }

        $sql .= "
            ORDER BY idapertura DESC
            LIMIT 1
        ";

        $resultado = $this->conexion->getData(
            $sql,
            $parametros
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | TOTALES DE UNA APERTURA ESPECÍFICA
    |--------------------------------------------------------------------------
    */
    public function calcularTotalesApertura(
        int $idapertura
    ): array {
        $apertura = $this->conexion->getData(
            "SELECT *
             FROM caja_apertura
             WHERE idapertura = ?
             LIMIT 1",
            [$idapertura]
        );

        if (!$apertura) {
            throw new RuntimeException(
                'No se encontró la apertura de caja.'
            );
        }

        $ventas = $this->conexion->getData(
            "SELECT
                COALESCE(
                    SUM(
                        CASE
                            WHEN fp.es_efectivo = 1
                            THEN vp.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS ventas_efectivo

             FROM venta_pago vp

             INNER JOIN venta v
                ON v.idventa = vp.idventa

             INNER JOIN forma_pago fp
                ON fp.idforma_pago = vp.idforma_pago

             WHERE v.idusuario = ?
               AND v.estado = 'Aceptado'
               AND v.fecha_hora >= ?
               AND (
                    ? IS NULL
                    OR v.fecha_hora <= ?
               )",
            [
                $apertura['idusuario'],
                $apertura['created_at'],
                $apertura['fecha_cierre'],
                $apertura['fecha_cierre']
            ]
        ) ?: [];

        $ventasEfectivo = round(
            (float)($ventas['ventas_efectivo'] ?? 0),
            2
        );

        $otrosIngresosEfectivo = 0.00;
        $egresosEfectivo = 0.00;

        if ($this->tablaExiste('movimiento_financiero')) {
            $movimientos = $this->conexion->getData(
                "SELECT
                    COALESCE(
                        SUM(
                            CASE
                                WHEN mf.tipo = 'INGRESO'
                                 AND fp.es_efectivo = 1
                                THEN mf.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS ingresos_efectivo,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN mf.tipo = 'EGRESO'
                                 AND fp.es_efectivo = 1
                                THEN mf.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS egresos_efectivo

                 FROM movimiento_financiero mf

                 INNER JOIN forma_pago fp
                    ON fp.idforma_pago = mf.idforma_pago

                 WHERE mf.idapertura = ?
                   AND mf.estado = 'ACTIVO'
                   AND mf.origen <> 'VENTA'",
                [$idapertura]
            ) ?: [];

            $otrosIngresosEfectivo = round(
                (float)(
                    $movimientos['ingresos_efectivo']
                    ?? 0
                ),
                2
            );

            $egresosEfectivo = round(
                (float)(
                    $movimientos['egresos_efectivo']
                    ?? 0
                ),
                2
            );
        }

        $montoApertura = round(
            (float)$apertura['monto_apertura'],
            2
        );

        $totalSistema = round(
            $montoApertura
            + $ventasEfectivo
            + $otrosIngresosEfectivo
            - $egresosEfectivo,
            2
        );

        return [
            'idapertura' => (int)$idapertura,
            'monto_apertura' => $montoApertura,
            'ventas_efectivo' => $ventasEfectivo,
            'otros_ingresos_efectivo' =>
                $otrosIngresosEfectivo,
            'egresos_efectivo' => $egresosEfectivo,
            'total_sistema' => $totalSistema
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CERRAR CAJA
    |--------------------------------------------------------------------------
    */
    public function cerrarCaja(
        float $montoContado,
        int $idusuario
    ): array {
        if ($idusuario <= 0) {
            return [
                'status' => 'error',
                'message' => 'El usuario de la sesión no es válido.'
            ];
        }

        if ($montoContado < 0) {
            return [
                'status' => 'error',
                'message' => 'El monto contado no puede ser negativo.'
            ];
        }

        $transaccionActiva = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionActiva = true;

            $cajasAbiertas =
                $this->conexion->getDataAll(
                    "SELECT *
                     FROM caja_apertura
                     WHERE idusuario = ?
                       AND estado = 'ABIERTA'
                     ORDER BY idapertura DESC
                     FOR UPDATE",
                    [$idusuario]
                );

            if (
                !is_array($cajasAbiertas)
                || count($cajasAbiertas) === 0
            ) {
                throw new RuntimeException(
                    'No existe una caja abierta para este usuario.'
                );
            }

            if (count($cajasAbiertas) > 1) {
                throw new RuntimeException(
                    'Se encontraron varias cajas abiertas. Regularice la caja antes de continuar.'
                );
            }

            $apertura = $cajasAbiertas[0];
            $idapertura = (int)$apertura['idapertura'];

            $cierreExistente =
                $this->conexion->getData(
                    "SELECT idcierre
                     FROM caja_cierre
                     WHERE caja_apertura_id = ?
                     LIMIT 1",
                    [$idapertura]
                );

            if ($cierreExistente) {
                throw new RuntimeException(
                    'La apertura ya tiene un cierre registrado.'
                );
            }

            $totales = $this->calcularTotalesApertura(
                $idapertura
            );

            $totalSistema = round(
                (float)$totales['total_sistema'],
                2
            );

            $montoContado = round(
                $montoContado,
                2
            );

            $diferencia = round(
                $montoContado - $totalSistema,
                2
            );

            $fechaCierre = date('Y-m-d H:i:s');

            $idcierre =
                $this->conexion->setDataReturnId(
                    "INSERT INTO caja_cierre (
                        caja_apertura_id,
                        usuario_cierre,
                        fecha_cierre,
                        total_sistema,
                        monto_contado,
                        diferencia
                    ) VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $idapertura,
                        $idusuario,
                        $fechaCierre,
                        $totalSistema,
                        $montoContado,
                        $diferencia
                    ]
                );

            if (!$idcierre) {
                throw new RuntimeException(
                    'No se pudo registrar el cierre de caja.'
                );
            }

            $actualizado = $this->conexion->setData(
                "UPDATE caja_apertura
                 SET
                    estado = 'CERRADA',
                    fecha_cierre = ?
                 WHERE idapertura = ?
                   AND estado = 'ABIERTA'",
                [
                    $fechaCierre,
                    $idapertura
                ]
            );

            if (!$actualizado) {
                throw new RuntimeException(
                    'No se pudo actualizar el estado de la caja.'
                );
            }

            $this->conexion->commit();
            $transaccionActiva = false;

            return [
                'status' => 'ok',
                'message' => 'Caja cerrada correctamente.',
                'idcierre' => (int)$idcierre,
                'idapertura' => $idapertura,
                'total_sistema' => $totalSistema,
                'monto_contado' => $montoContado,
                'diferencia' => $diferencia
            ];
        } catch (Throwable $e) {
            if ($transaccionActiva) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[CIERRE CAJA ROLLBACK] '
                        . $rollbackError->getMessage()
                    );
                }
            }

            error_log(
                '[CIERRE CAJA] '
                . $e->getMessage()
            );

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | COMPROBAR EXISTENCIA DE TABLA
    |--------------------------------------------------------------------------
    */
    private function tablaExiste(
        string $tabla
    ): bool {
        $resultado = $this->conexion->getData(
            "SELECT COUNT(*) AS cantidad
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?",
            [$tabla]
        );

        return (int)($resultado['cantidad'] ?? 0) > 0;
    }
}