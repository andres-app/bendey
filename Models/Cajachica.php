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
        ?int $idusuario = null,
        ?int $idapertura = null
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

        if ($idapertura !== null && $idapertura > 0) {
            $sql .= " AND v.idapertura = ?";
            $parametros[] = $idapertura;
        } elseif ($idusuario !== null && $idusuario > 0) {
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

            if ($idapertura !== null && $idapertura > 0) {
                $sqlCobranzas .= " AND mf.idapertura = ?";
                $parametrosCobranzas[] = $idapertura;
            } elseif ($idusuario !== null && $idusuario > 0) {
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
        ?int $idusuario = null,
        ?int $idapertura = null
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

        if ($idapertura !== null && $idapertura > 0) {
            $sql .= " AND v.idapertura = ?";
            $parametros[] = $idapertura;
        } elseif ($idusuario !== null && $idusuario > 0) {
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

            if ($idapertura !== null && $idapertura > 0) {
                $sqlMovimientos .= " AND mf.idapertura = ?";
                $parametrosMovimientos[] = $idapertura;
            } elseif ($idusuario !== null && $idusuario > 0) {
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
| OBTENER APERTURA ACTIVA POR CAJA FÍSICA
|--------------------------------------------------------------------------
*/
    public function obtenerCajaAbiertaFisica(
        int $idcaja,
        bool $bloquear = false
    ): ?array {
        if ($idcaja <= 0) {
            return null;
        }

        $sql = "
        SELECT
            ca.*,
            cf.codigo AS codigo_caja,
            cf.nombre AS nombre_caja,
            s.codigo AS codigo_sucursal,
            s.nombre AS nombre_sucursal

        FROM caja_apertura AS ca

        INNER JOIN caja_fisica AS cf
            ON cf.idcaja = ca.idcaja

        INNER JOIN sucursal AS s
            ON s.idsucursal = ca.idsucursal

        WHERE ca.idcaja = ?
          AND ca.estado = 'ABIERTA'

        ORDER BY ca.idapertura DESC
        LIMIT 1
    ";

        if ($bloquear) {
            $sql .= " FOR UPDATE";
        }

        $resultado = $this->conexion->getData(
            $sql,
            [$idcaja]
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    /*
|--------------------------------------------------------------------------
| CONTAR APERTURAS ACTIVAS DE UNA CAJA FÍSICA
|--------------------------------------------------------------------------
*/
    public function contarCajasAbiertasFisica(
        int $idcaja
    ): int {
        if ($idcaja <= 0) {
            return 0;
        }

        $resultado = $this->conexion->getData(
            "SELECT
            COUNT(*) AS cantidad
         FROM caja_apertura
         WHERE idcaja = ?
           AND estado = 'ABIERTA'",
            [$idcaja]
        );

        return is_array($resultado)
            ? (int)($resultado['cantidad'] ?? 0)
            : 0;
    }

    /*
|--------------------------------------------------------------------------
| REGISTRAR APERTURA POR CAJA FÍSICA
|--------------------------------------------------------------------------
| Este método pertenece al modelo nuevo.
| No modifica ni reemplaza registrarApertura(), usado por LEGACY.
|--------------------------------------------------------------------------
*/
    public function registrarAperturaFisica(
        float $monto,
        int $idsucursal,
        int $idcaja,
        int $idusuarioApertura,
        int $idusuarioResponsable
    ): array {
        if ($idsucursal <= 0) {
            return [
                'status' => 'error',
                'message' =>
                'La sucursal de la apertura no es válida.'
            ];
        }

        if ($idcaja <= 0) {
            return [
                'status' => 'error',
                'message' =>
                'La caja física seleccionada no es válida.'
            ];
        }

        if ($idusuarioApertura <= 0) {
            return [
                'status' => 'error',
                'message' =>
                'El usuario que realiza la apertura no es válido.'
            ];
        }

        if ($idusuarioResponsable <= 0) {
            return [
                'status' => 'error',
                'message' =>
                'El responsable de caja no es válido.'
            ];
        }

        if ($monto < 0) {
            return [
                'status' => 'error',
                'message' =>
                'El monto de apertura no puede ser negativo.'
            ];
        }

        $transaccionActiva = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionActiva = true;

            /*
         * Bloqueamos la caja física para impedir
         * aperturas simultáneas desde dos peticiones.
         */
            $cajaFisica = $this->conexion->getData(
                "SELECT
                cf.idcaja,
                cf.idsucursal,
                cf.codigo,
                cf.nombre,
                cf.permite_efectivo,
                cf.activo,
                s.activo AS sucursal_activa

             FROM caja_fisica AS cf

             INNER JOIN sucursal AS s
                ON s.idsucursal = cf.idsucursal

             WHERE cf.idcaja = ?
               AND cf.idsucursal = ?
             LIMIT 1
             FOR UPDATE",
                [
                    $idcaja,
                    $idsucursal
                ]
            );

            if (!is_array($cajaFisica)) {
                throw new RuntimeException(
                    'La caja no pertenece a la sucursal seleccionada.'
                );
            }

            if (
                (int)($cajaFisica['activo'] ?? 0) !== 1
                || (int)($cajaFisica['sucursal_activa'] ?? 0) !== 1
            ) {
                throw new RuntimeException(
                    'La caja o la sucursal se encuentran inactivas.'
                );
            }

            if (
                (int)($cajaFisica['permite_efectivo'] ?? 0)
                !== 1
            ) {
                throw new RuntimeException(
                    'La caja seleccionada no admite operaciones en efectivo.'
                );
            }

            $aperturasActivas =
                $this->conexion->getDataAll(
                    "SELECT
                    idapertura
                 FROM caja_apertura
                 WHERE idcaja = ?
                   AND estado = 'ABIERTA'
                 FOR UPDATE",
                    [$idcaja]
                );

            if (
                is_array($aperturasActivas)
                && count($aperturasActivas) > 0
            ) {
                throw new RuntimeException(
                    'La caja física seleccionada ya tiene una apertura activa.'
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
                    idsucursal,
                    idcaja,
                    idusuario_apertura,
                    idusuario_responsable,
                    estado,
                    created_at
                ) VALUES (
                    ?,
                    ?,
                    NULL,
                    ?,
                    ?,
                    ?,
                    ?,
                    'ABIERTA',
                    ?
                )",
                    [
                        $fecha,
                        round($monto, 2),
                        $idsucursal,
                        $idcaja,
                        $idusuarioApertura,
                        $idusuarioResponsable,
                        $createdAt
                    ]
                );

            if (!$idapertura) {
                throw new RuntimeException(
                    'No se pudo registrar la apertura de la caja física.'
                );
            }

            $this->conexion->commit();
            $transaccionActiva = false;

            return [
                'status' => 'ok',
                'message' =>
                'Caja física abierta correctamente.',
                'idapertura' =>
                (int)$idapertura,
                'idsucursal' =>
                $idsucursal,
                'idcaja' =>
                $idcaja,
                'codigo_caja' =>
                (string)($cajaFisica['codigo'] ?? ''),
                'nombre_caja' =>
                (string)($cajaFisica['nombre'] ?? ''),
                'idusuario_apertura' =>
                $idusuarioApertura,
                'idusuario_responsable' =>
                $idusuarioResponsable
            ];
        } catch (Throwable $e) {
            if ($transaccionActiva) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[APERTURA FÍSICA ROLLBACK] '
                            . $rollbackError->getMessage()
                    );
                }
            }

            error_log(
                '[APERTURA CAJA FÍSICA] '
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
| TOTALES DE UNA APERTURA FÍSICA
|--------------------------------------------------------------------------
| Se utiliza únicamente para CAJA_UNICA y MULTICAJA.
| Las ventas y movimientos se calculan por idapertura.
|--------------------------------------------------------------------------
*/
    public function calcularTotalesAperturaFisica(
        int $idapertura
    ): array {
        if ($idapertura <= 0) {
            throw new RuntimeException(
                'La apertura seleccionada no es válida.'
            );
        }

        $apertura = $this->conexion->getData(
            "SELECT
            ca.idapertura,
            ca.fecha,
            ca.monto_apertura,
            ca.idsucursal,
            ca.idcaja,
            ca.idusuario_apertura,
            ca.idusuario_responsable,
            ca.estado,
            ca.created_at,
            ca.fecha_cierre,
            cf.codigo AS codigo_caja,
            cf.nombre AS nombre_caja

         FROM caja_apertura AS ca

         INNER JOIN caja_fisica AS cf
            ON cf.idcaja = ca.idcaja

         WHERE ca.idapertura = ?
         LIMIT 1",
            [$idapertura]
        );

        if (!is_array($apertura)) {
            throw new RuntimeException(
                'No se encontró la apertura de la caja física.'
            );
        }

        /*
    |--------------------------------------------------------------------------
    | VENTAS EN EFECTIVO VINCULADAS A LA APERTURA
    |--------------------------------------------------------------------------
    */
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

         FROM venta_pago AS vp

         INNER JOIN venta AS v
            ON v.idventa = vp.idventa

         INNER JOIN forma_pago AS fp
            ON fp.idforma_pago = vp.idforma_pago

         WHERE v.idapertura = ?
           AND v.estado = 'Aceptado'",
            [$idapertura]
        ) ?: [];

        $ventasEfectivo = round(
            (float)(
                $ventas['ventas_efectivo']
                ?? 0
            ),
            2
        );

        /*
    |--------------------------------------------------------------------------
    | OTROS INGRESOS Y EGRESOS EN EFECTIVO
    |--------------------------------------------------------------------------
    */
        $otrosIngresosEfectivo = 0.00;
        $egresosEfectivo = 0.00;

        if ($this->tablaExiste(
            'movimiento_financiero'
        )) {
            $movimientos =
                $this->conexion->getData(
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

                 FROM movimiento_financiero AS mf

                 INNER JOIN forma_pago AS fp
                    ON fp.idforma_pago =
                       mf.idforma_pago

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
            (float)(
                $apertura['monto_apertura']
                ?? 0
            ),
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
            'idapertura' =>
            (int)$apertura['idapertura'],

            'idsucursal' =>
            (int)$apertura['idsucursal'],

            'idcaja' =>
            (int)$apertura['idcaja'],

            'codigo_caja' =>
            (string)$apertura['codigo_caja'],

            'nombre_caja' =>
            (string)$apertura['nombre_caja'],

            'monto_apertura' =>
            $montoApertura,

            'ventas_efectivo' =>
            $ventasEfectivo,

            'otros_ingresos_efectivo' =>
            $otrosIngresosEfectivo,

            'egresos_efectivo' =>
            $egresosEfectivo,

            'total_sistema' =>
            $totalSistema
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
| CERRAR CAJA FÍSICA
|--------------------------------------------------------------------------
| Se utiliza únicamente para CAJA_UNICA y MULTICAJA.
| El cierre se determina por sucursal, caja física e idapertura.
|--------------------------------------------------------------------------
*/
    public function cerrarCajaFisica(
        float $montoContado,
        int $idsucursal,
        int $idcaja,
        int $idusuarioCierre
    ): array {
        if ($idsucursal <= 0) {
            return [
                'status' => 'error',
                'message' =>
                'La sucursal de la caja no es válida.'
            ];
        }

        if ($idcaja <= 0) {
            return [
                'status' => 'error',
                'message' =>
                'La caja física seleccionada no es válida.'
            ];
        }

        if ($idusuarioCierre <= 0) {
            return [
                'status' => 'error',
                'message' =>
                'El usuario que realiza el cierre no es válido.'
            ];
        }

        if ($montoContado < 0) {
            return [
                'status' => 'error',
                'message' =>
                'El monto contado no puede ser negativo.'
            ];
        }

        $transaccionActiva = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionActiva = true;

            /*
         * Bloqueamos la apertura activa de la caja.
         */
            $aperturas =
                $this->conexion->getDataAll(
                    "SELECT
                    ca.*,
                    cf.codigo AS codigo_caja,
                    cf.nombre AS nombre_caja

                 FROM caja_apertura AS ca

                 INNER JOIN caja_fisica AS cf
                    ON cf.idcaja = ca.idcaja

                 WHERE ca.idsucursal = ?
                   AND ca.idcaja = ?
                   AND ca.estado = 'ABIERTA'

                 ORDER BY ca.idapertura DESC
                 FOR UPDATE",
                    [
                        $idsucursal,
                        $idcaja
                    ]
                );

            if (
                !is_array($aperturas)
                || count($aperturas) === 0
            ) {
                throw new RuntimeException(
                    'No existe una apertura activa para la caja seleccionada.'
                );
            }

            if (count($aperturas) > 1) {
                throw new RuntimeException(
                    'Se encontraron varias aperturas activas para la misma caja física.'
                );
            }

            $apertura = $aperturas[0];

            $idapertura = (int)(
                $apertura['idapertura']
                ?? 0
            );

            if ($idapertura <= 0) {
                throw new RuntimeException(
                    'La apertura activa no es válida.'
                );
            }

            /*
         * Evitar registrar dos cierres para una apertura.
         */
            $cierreExistente =
                $this->conexion->getData(
                    "SELECT idcierre
                 FROM caja_cierre
                 WHERE caja_apertura_id = ?
                 LIMIT 1",
                    [$idapertura]
                );

            if (is_array($cierreExistente)) {
                throw new RuntimeException(
                    'La apertura ya tiene un cierre registrado.'
                );
            }

            /*
         * El arqueo físico se calcula exclusivamente
         * por idapertura.
         */
            $totales =
                $this->calcularTotalesAperturaFisica(
                    $idapertura
                );

            $totalSistema = round(
                (float)(
                    $totales['total_sistema']
                    ?? 0
                ),
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

            $fechaCierre =
                date('Y-m-d H:i:s');

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
                        $idusuarioCierre,
                        $fechaCierre,
                        $totalSistema,
                        $montoContado,
                        $diferencia
                    ]
                );

            if (!$idcierre) {
                throw new RuntimeException(
                    'No se pudo registrar el cierre de la caja física.'
                );
            }

            $actualizado =
                $this->conexion->setData(
                    "UPDATE caja_apertura
                 SET
                    estado = 'CERRADA',
                    fecha_cierre = ?
                 WHERE idapertura = ?
                   AND idsucursal = ?
                   AND idcaja = ?
                   AND estado = 'ABIERTA'",
                    [
                        $fechaCierre,
                        $idapertura,
                        $idsucursal,
                        $idcaja
                    ]
                );

            if (!$actualizado) {
                throw new RuntimeException(
                    'No se pudo actualizar el estado de la apertura.'
                );
            }

            $this->conexion->commit();
            $transaccionActiva = false;

            return [
                'status' =>
                'ok',

                'message' =>
                'Caja física cerrada correctamente.',

                'idcierre' =>
                (int)$idcierre,

                'idapertura' =>
                $idapertura,

                'idsucursal' =>
                $idsucursal,

                'idcaja' =>
                $idcaja,

                'codigo_caja' =>
                (string)(
                    $apertura['codigo_caja']
                    ?? ''
                ),

                'nombre_caja' =>
                (string)(
                    $apertura['nombre_caja']
                    ?? ''
                ),

                'usuario_cierre' =>
                $idusuarioCierre,

                'total_sistema' =>
                $totalSistema,

                'monto_contado' =>
                $montoContado,

                'diferencia' =>
                $diferencia,

                'fecha_cierre' =>
                $fechaCierre
            ];
        } catch (Throwable $e) {
            if ($transaccionActiva) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[CIERRE CAJA FÍSICA ROLLBACK] '
                            . $rollbackError->getMessage()
                    );
                }
            }

            error_log(
                '[CIERRE CAJA FÍSICA] '
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
