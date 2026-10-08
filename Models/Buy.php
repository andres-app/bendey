<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/CajaOperacionGuard.php';

class Buy
{
    private string $tableName = 'ingreso';
    private string $tableNameDetalle = 'detalle_ingreso';
    private string $tableNameKardex = 'kardex';
    private Conexion $conexion;
    private CajaOperacionGuard $cajaGuard;

    public function __construct()
    {
        $this->conexion = new Conexion();
        $this->cajaGuard = new CajaOperacionGuard($this->conexion);
    }

    /**
     * Registra una compra completa en una sola transacción.
     *
     * Los detalles pueden ser:
     * - INVENTARIO + EXISTENTE
     * - INVENTARIO + NUEVO
     * - NO_INVENTARIO + GASTO
     */
    public function insertar(array $cabecera, array $detalles): array
    {
        if (count($detalles) === 0) {
            throw new RuntimeException('Debe agregar al menos un detalle a la compra.');
        }

        $idproveedor = (int)($cabecera['idproveedor'] ?? 0);
        $idusuario = (int)($cabecera['idusuario'] ?? 0);
        $idsucursal = (int)($cabecera['idsucursal'] ?? 0);
        $tipoComprobante = $this->limpiarTexto($cabecera['tipo_comprobante'] ?? '', 20);
        $serieComprobante = $this->limpiarTexto($cabecera['serie_comprobante'] ?? '', 7);
        $numComprobante = $this->limpiarTexto($cabecera['num_comprobante'] ?? '', 10);
        $fechaHora = $this->normalizarFecha($cabecera['fecha_hora'] ?? '');
        $impuesto = round((float)($cabecera['impuesto'] ?? 0), 2);
        $observacion = $this->limpiarTexto($cabecera['observacion'] ?? '', 255);

        $idtipoPago = (int)($cabecera['idtipopago'] ?? 0);

        if ($idtipoPago <= 0) {
            throw new RuntimeException('Debe seleccionar una condición de pago válida.');
        }

        $tipoPago = $this->conexion->getData(
            "SELECT idtipopago, nombre, descripcion
             FROM tipo_pago
             WHERE idtipopago = ?
               AND estado = 1
             LIMIT 1",
            [$idtipoPago]
        );

        if (!$tipoPago) {
            throw new RuntimeException(
                'La condición de pago seleccionada ya no está disponible.'
            );
        }

        $condicionPago = $this->normalizarCondicionPago(
            (string)($tipoPago['nombre'] ?? '')
        );

        if (!in_array($condicionPago, ['CONTADO', 'CREDITO'], true)) {
            throw new RuntimeException(
                'El tipo de pago seleccionado debe corresponder a Contado o Crédito.'
            );
        }

        $idformaPago = (int)($cabecera['idforma_pago'] ?? 0);
        $numeroOperacion = $this->limpiarTexto(
            $cabecera['numero_operacion'] ?? '',
            80
        );

        if ($idproveedor <= 0) {
            throw new RuntimeException('Debe seleccionar un proveedor válido.');
        }

        if ($idusuario <= 0) {
            throw new RuntimeException('La sesión del usuario no es válida.');
        }

        if ($tipoComprobante === '') {
            throw new RuntimeException('Debe seleccionar el tipo de comprobante.');
        }

        if ($numComprobante === '') {
            throw new RuntimeException('Debe ingresar el número del comprobante.');
        }

        if ($impuesto < 0 || $impuesto > 99.99) {
            throw new RuntimeException('El porcentaje de impuesto no es válido.');
        }

        $transaccionIniciada = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionIniciada = true;

            $proveedor = $this->conexion->getData(
                "SELECT idpersona
                 FROM persona
                 WHERE idpersona = ?
                 LIMIT 1
                 FOR UPDATE",
                [$idproveedor]
            );

            if (!$proveedor) {
                throw new RuntimeException('El proveedor seleccionado no existe.');
            }

            $duplicado = $this->conexion->getData(
                "SELECT idingreso
                 FROM {$this->tableName}
                 WHERE idproveedor = ?
                   AND tipo_comprobante = ?
                   AND COALESCE(serie_comprobante, '') = ?
                   AND num_comprobante = ?
                   AND estado <> 'Anulado'
                 LIMIT 1
                 FOR UPDATE",
                [
                    $idproveedor,
                    $tipoComprobante,
                    $serieComprobante,
                    $numComprobante
                ]
            );

            if ($duplicado) {
                throw new RuntimeException(
                    'Ya existe una compra activa del mismo proveedor con ese comprobante.'
                );
            }

            $detallesNormalizados = [];
            $tieneInventario = false;
            $tieneNoInventario = false;
            $totalCompra = 0.0;

            foreach ($detalles as $indice => $detalle) {
                $normalizado = $this->normalizarDetalle($detalle, $indice + 1);
                $detallesNormalizados[] = $normalizado;
                $totalCompra += $normalizado['importe'];

                if ($normalizado['tipo_detalle'] === 'INVENTARIO') {
                    $tieneInventario = true;
                } else {
                    $tieneNoInventario = true;
                }
            }

            $totalCompra = round($totalCompra, 2);

            if ($totalCompra <= 0) {
                throw new RuntimeException('El total de la compra debe ser mayor que cero.');
            }

            if ($totalCompra > 999999999.99) {
                throw new RuntimeException('El total de la compra supera el límite permitido.');
            }

            $tipoCompra = $tieneInventario && $tieneNoInventario
                ? 'MIXTA'
                : ($tieneInventario ? 'INVENTARIO' : 'NO_INVENTARIO');

            $pago = null;

            if ($condicionPago === 'CONTADO') {
                $pago = $this->cajaGuard->prepararFormaPago(
                    $idformaPago,
                    $numeroOperacion,
                    [
                        'idusuario' => $idusuario,
                        'idsucursal' => (int)($cabecera['idsucursal'] ?? 0),
                        'idcaja' => (int)($cabecera['idcaja'] ?? 0),
                        'idapertura' => (int)($cabecera['idapertura'] ?? 0),
                        'modo_caja' => (string)($cabecera['modo_caja'] ?? 'LEGACY')
                    ]
                );
            }

            $idFormaPagoGuardar = $pago !== null
                ? (int)$pago['idforma_pago']
                : null;

            $idCuentaGuardar = $pago !== null
                ? (int)$pago['idcuenta_financiera']
                : null;

            $idAperturaGuardar = $pago !== null
                && (int)($pago['idapertura'] ?? 0) > 0
                ? (int)$pago['idapertura']
                : null;

            $numeroOperacionGuardar = $pago !== null
                ? $pago['numero_operacion']
                : null;

            $estadoPago = $condicionPago === 'CONTADO'
                ? 'PAGADO'
                : 'PENDIENTE';

            $sqlIngreso = "INSERT INTO {$this->tableName}
                (idproveedor, idusuario, idsucursal, tipo_comprobante,
                 serie_comprobante, num_comprobante, fecha_hora, impuesto,
                 total_compra, condicion_pago, idforma_pago,
                 idcuenta_financiera, idapertura, numero_operacion,
                 estado_pago, tipo_compra, observacion, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aceptado')";

            $idingreso = (int)$this->conexion->setDataReturnId(
                $sqlIngreso,
                [
                    $idproveedor,
                    $idusuario,
                    $idsucursal > 0 ? $idsucursal : null,
                    $tipoComprobante,
                    $serieComprobante !== '' ? $serieComprobante : null,
                    $numComprobante,
                    $fechaHora,
                    $impuesto,
                    $totalCompra,
                    $condicionPago,
                    $idFormaPagoGuardar,
                    $idCuentaGuardar,
                    $idAperturaGuardar,
                    $numeroOperacionGuardar,
                    $estadoPago,
                    $tipoCompra,
                    $observacion !== '' ? $observacion : null
                ]
            );

            if ($idingreso <= 0) {
                throw new RuntimeException('No se pudo crear la cabecera de la compra.');
            }

            $detalleDocumento = $this->limpiarTexto(
                trim($tipoComprobante . ' ' . $serieComprobante . '-' . $numComprobante),
                64
            );

            // Los grupos de variantes se crean completos dentro de la misma
            // transacción de la compra. Si algo falla, no queda un padre o una
            // variante registrada a medias.
            $detallesNormalizados = $this->prepararProductosVariablesCompra(
                $detallesNormalizados
            );

            foreach ($detallesNormalizados as $detalle) {
                if ($detalle['tipo_detalle'] === 'INVENTARIO') {
                    $this->registrarDetalleInventario(
                        $idingreso,
                        $fechaHora,
                        $detalleDocumento,
                        $detalle
                    );
                } else {
                    $this->registrarDetalleNoInventario($idingreso, $detalle);
                }
            }

            if ($condicionPago === 'CONTADO' && $pago !== null) {
                $concepto = 'Pago de compra #'
                    . $idingreso
                    . ' · '
                    . trim(
                        $tipoComprobante
                        . ' '
                        . $serieComprobante
                        . '-'
                        . $numComprobante,
                        ' -'
                    );

                $idMovimiento = (int)$this->conexion->setDataReturnId(
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
                        'COMPRA',
                        ?, ?, ?, ?, ?, ?, ?,
                        'ACTIVO'
                    )",
                    [
                        $idingreso,
                        (int)$pago['idforma_pago'],
                        (int)$pago['idcuenta_financiera'],
                        $idAperturaGuardar,
                        $totalCompra,
                        $concepto,
                        $idusuario
                    ]
                );

                if ($idMovimiento <= 0) {
                    throw new RuntimeException(
                        'No se pudo registrar el movimiento financiero de la compra.'
                    );
                }
            }

            $this->conexion->commit();
            $transaccionIniciada = false;

            return [
                'success' => true,
                'idingreso' => $idingreso,
                'tipo_compra' => $tipoCompra,
                'total_compra' => $totalCompra,
                'condicion_pago' => $condicionPago,
                'estado_pago' => $estadoPago,
                'forma_pago' => $pago !== null
                    ? (string)$pago['forma_pago']
                    : null
            ];
        } catch (Throwable $error) {
            if ($transaccionIniciada) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log('[COMPRA ROLLBACK] ' . $rollbackError->getMessage());
                }
            }

            throw $error;
        }
    }

    public function anular(int $idingreso, array $contextoSesion = []): array
    {
        if ($idingreso <= 0) {
            throw new RuntimeException('La compra indicada no es válida.');
        }

        $transaccionIniciada = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionIniciada = true;

            $compra = $this->conexion->getData(
                "SELECT idingreso, estado, tipo_comprobante,
                        serie_comprobante, num_comprobante,
                        condicion_pago, idapertura
                 FROM {$this->tableName}
                 WHERE idingreso = ?
                 LIMIT 1
                 FOR UPDATE",
                [$idingreso]
            );

            if (!$compra) {
                throw new RuntimeException('La compra no existe.');
            }

            if ((string)$compra['estado'] === 'Anulado') {
                throw new RuntimeException('La compra ya se encuentra anulada.');
            }

            $movimientoCompra = $this->conexion->getData(
                "SELECT
                    idmovimiento,
                    idapertura,
                    estado
                 FROM movimiento_financiero
                 WHERE origen = 'COMPRA'
                   AND idreferencia = ?
                   AND estado = 'ACTIVO'
                 ORDER BY idmovimiento DESC
                 LIMIT 1
                 FOR UPDATE",
                [$idingreso]
            );

            if (is_array($movimientoCompra)) {
                $idAperturaMovimiento = (int)(
                    $movimientoCompra['idapertura']
                    ?? 0
                );

                if ($idAperturaMovimiento > 0) {
                    $apertura = $this->conexion->getData(
                        "SELECT idapertura, estado
                         FROM caja_apertura
                         WHERE idapertura = ?
                         LIMIT 1
                         FOR UPDATE",
                        [$idAperturaMovimiento]
                    );

                    if (
                        !is_array($apertura)
                        || (string)$apertura['estado'] !== 'ABIERTA'
                    ) {
                        throw new RuntimeException(
                            'Esta compra fue pagada en una caja que ya fue cerrada. '
                            . 'Para conservar la trazabilidad no puede anularse retroactivamente; '
                            . 'registre una devolución o ajuste financiero.'
                        );
                    }

                    $idAperturaSesion = (int)(
                        $contextoSesion['idapertura']
                        ?? 0
                    );

                    if (
                        $idAperturaSesion <= 0
                        || $idAperturaSesion !== $idAperturaMovimiento
                    ) {
                        throw new RuntimeException(
                            'La compra fue pagada desde otra apertura de caja. '
                            . 'Debe operar sobre la misma caja antes de anularla.'
                        );
                    }
                }
            }

            $detalles = $this->conexion->getDataAll(
                "SELECT iddetalle_ingreso, tipo_detalle, idarticulo, descripcion,
                        cantidad, stock_venta, afecta_stock, estado
                 FROM {$this->tableNameDetalle}
                 WHERE idingreso = ?
                 FOR UPDATE",
                [$idingreso]
            );

            foreach ($detalles as $filaCancelacion) {
                $control = $this->conexion->getData(
                    "SELECT controla_lotes, controla_vencimiento FROM articulo WHERE idarticulo=?",
                    [(int)($filaCancelacion['idarticulo'] ?? 0)]
                );
                if ((int)($control['controla_lotes'] ?? 0) === 1 || (int)($control['controla_vencimiento'] ?? 0) === 1) {
                    throw new RuntimeException('No se permite anular directamente una compra con lotes hasta comprobar todas sus salidas y devoluciones.');
                }
            }
            $cantidadesPorArticulo = [];
            $cantidadesPorVariacion = [];

            foreach ($detalles as $detalle) {
                $esInventario =
                    (string)$detalle['tipo_detalle'] === 'INVENTARIO'
                    && (int)$detalle['afecta_stock'] === 1
                    && (int)$detalle['idarticulo'] > 0;

                if (!$esInventario) {
                    continue;
                }

        $cantidad = (int)round((float)$detalle['cantidad']);
                $stockVenta = (int)$detalle['stock_venta'];
                $idarticulo = (int)$detalle['idarticulo'];

                $descripcionDetalle = trim((string)($detalle['descripcion'] ?? ''));
                if (preg_match('/ · SKU:\s*([A-Z0-9._-]+)$/i', $descripcionDetalle, $skuMatch)) {
                    $skuVariacion = strtoupper(trim((string)$skuMatch[1]));
                    $variacion = $this->conexion->getData(
                        "SELECT idvariacion, stock, combinacion
                         FROM articulo_variacion
                         WHERE idarticulo = ?
                           AND sku = ?
                           AND estado = 1
                         LIMIT 1
                         FOR UPDATE",
                        [$idarticulo, $skuVariacion]
                    );

                    if ($variacion) {
                        if ((int)$variacion['stock'] < $cantidad) {
                            throw new RuntimeException(
                                'No se puede anular porque el stock actual de la variante "'
                                . (string)($variacion['combinacion'] ?? $skuVariacion)
                                . '" es menor que la cantidad ingresada.'
                            );
                        }

                        $idvariacion = (int)$variacion['idvariacion'];
                        $cantidadesPorVariacion[$idvariacion] =
                            ($cantidadesPorVariacion[$idvariacion] ?? 0) + $cantidad;
                    }
                }

                if ($stockVenta < $cantidad) {
                    $articulo = $this->conexion->getData(
                        "SELECT nombre
                         FROM articulo
                         WHERE idarticulo = ?
                         LIMIT 1
                         FOR UPDATE",
                        [$idarticulo]
                    );

                    throw new RuntimeException(
                        'No se puede anular porque parte del producto "'
                        . (string)($articulo['nombre'] ?? 'desconocido')
                        . '" ya fue vendido o consumido.'
                    );
                }

                $cantidadesPorArticulo[$idarticulo] =
                    ($cantidadesPorArticulo[$idarticulo] ?? 0) + $cantidad;
            }

            foreach ($cantidadesPorArticulo as $idarticulo => $cantidadTotal) {
                $articulo = $this->conexion->getData(
                    "SELECT idarticulo, nombre, stock
                     FROM articulo
                     WHERE idarticulo = ?
                     LIMIT 1
                     FOR UPDATE",
                    [(int)$idarticulo]
                );

                if (!$articulo) {
                    throw new RuntimeException(
                        'No se encontró uno de los productos de la compra.'
                    );
                }

                if ((int)$articulo['stock'] < (int)$cantidadTotal) {
                    throw new RuntimeException(
                        'No se puede anular porque el stock actual de "'
                        . (string)$articulo['nombre']
                        . '" es menor que la cantidad total ingresada.'
                    );
                }
            }

            foreach ($cantidadesPorVariacion as $idvariacion => $cantidadTotal) {
                $this->conexion->setData(
                    "UPDATE articulo_variacion
                     SET stock = stock - ?
                     WHERE idvariacion = ?",
                    [(int)$cantidadTotal, (int)$idvariacion]
                );
            }

            foreach ($cantidadesPorArticulo as $idarticulo => $cantidadTotal) {
                $this->conexion->setData(
                    "UPDATE articulo
                     SET stock = stock - ?
                     WHERE idarticulo = ?",
                    [(int)$cantidadTotal, (int)$idarticulo]
                );
            }

            foreach ($detalles as $detalle) {
                $this->conexion->setData(
                    "UPDATE {$this->tableNameDetalle}
                     SET estado = 0,
                         stock_estado = 0,
                         stock_venta = CASE
                             WHEN tipo_detalle = 'INVENTARIO' THEN 0
                             ELSE stock_venta
                         END
                     WHERE iddetalle_ingreso = ?",
                    [(int)$detalle['iddetalle_ingreso']]
                );
            }

            $this->conexion->setData(
                "UPDATE {$this->tableNameKardex}
                 SET estado = 'Anulado'
                 WHERE iddetalle = ?
                   AND tipo = 'Ingreso'",
                [$idingreso]
            );

            if (is_array($movimientoCompra)) {
                $this->conexion->setData(
                    "UPDATE movimiento_financiero
                     SET estado = 'ANULADO'
                     WHERE idmovimiento = ?",
                    [(int)$movimientoCompra['idmovimiento']]
                );
            }

            $this->conexion->setData(
                "UPDATE {$this->tableName}
                 SET estado = 'Anulado',
                     estado_pago = CASE
                        WHEN condicion_pago = 'CONTADO'
                            THEN 'ANULADO'
                        ELSE estado_pago
                     END
                 WHERE idingreso = ?",
                [$idingreso]
            );

            $this->conexion->commit();
            $transaccionIniciada = false;

            return [
                'success' => true,
                'mensaje' => 'Compra anulada correctamente.'
            ];
        } catch (Throwable $error) {
            if ($transaccionIniciada) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log('[ANULAR COMPRA ROLLBACK] ' . $rollbackError->getMessage());
                }
            }

            throw $error;
        }
    }

    public function mostrar(int $idingreso): array|false
    {
        $sql = "SELECT
                    i.idingreso,
                    DATE_FORMAT(i.fecha_hora, '%Y-%m-%d') AS fecha,
                    i.idproveedor,
                    p.nombre AS proveedor,
                    i.idusuario,
                    u.nombre AS usuario,
                    i.idsucursal,
                    s.nombre AS sucursal,
                    i.tipo_comprobante,
                    i.serie_comprobante,
                    i.num_comprobante,
                    i.total_compra,
                    i.impuesto,
                    i.condicion_pago,
                    i.estado_pago,
                    i.idforma_pago,
                    fp.nombre AS forma_pago,
                    i.numero_operacion,
                    i.idapertura,
                    i.tipo_compra,
                    i.observacion,
                    i.estado
                FROM {$this->tableName} i
                INNER JOIN persona p
                    ON i.idproveedor = p.idpersona
                LEFT JOIN usuario u
                    ON i.idusuario = u.idusuario
                LEFT JOIN sucursal s
                    ON i.idsucursal = s.idsucursal
                LEFT JOIN forma_pago fp
                    ON fp.idforma_pago = i.idforma_pago
                WHERE i.idingreso = ?";

        return $this->conexion->getData($sql, [$idingreso]);
    }

    public function listarDetalle(int $idingreso): array
    {
        $sql = "SELECT
                    di.iddetalle_ingreso,
                    di.idingreso,
                    di.tipo_detalle,
                    di.idarticulo,
                    COALESCE(a.nombre, di.descripcion) AS nombre,
                    di.descripcion,
                    cc.nombre AS categoria_compra,
                    al.nombre AS almacen,
                    m.nombre AS medida,
                    di.cantidad,
                    di.precio_compra,
                    di.precio_venta,
                    di.importe,
                    di.afecta_stock,
                    di.estado
                FROM {$this->tableNameDetalle} di
                LEFT JOIN articulo a
                    ON di.idarticulo = a.idarticulo
                LEFT JOIN categoria_compra cc
                    ON di.idcategoria_compra = cc.idcategoria_compra
                LEFT JOIN almacen al
                    ON di.idalmacen = al.idalmacen
                LEFT JOIN medida m
                    ON di.idmedida = m.idmedida
                WHERE di.idingreso = ?
                ORDER BY di.iddetalle_ingreso ASC";

        return $this->conexion->getDataAll($sql, [$idingreso]);
    }

    public function listar(): array
    {
        $sql = "SELECT
                    i.idingreso,
                    DATE_FORMAT(i.fecha_hora, '%Y-%m-%d') AS fecha,
                    p.nombre AS proveedor,
                    COALESCE(u.nombre, 'Sin usuario') AS usuario,
                    i.tipo_comprobante,
                    i.serie_comprobante,
                    i.num_comprobante,
                    i.total_compra,
                    i.condicion_pago,
                    i.estado_pago,
                    fp.nombre AS forma_pago,
                    i.tipo_compra,
                    i.estado
                FROM {$this->tableName} i
                INNER JOIN persona p
                    ON i.idproveedor = p.idpersona
                LEFT JOIN usuario u
                    ON i.idusuario = u.idusuario
                LEFT JOIN forma_pago fp
                    ON fp.idforma_pago = i.idforma_pago
                ORDER BY i.idingreso DESC";

        return $this->conexion->getDataAll($sql);
    }

    public function listarProductosCompra(): array
    {
        $sql = "SELECT
                    a.idarticulo,
                    a.codigo,
                    a.nombre,
                    a.stock,
                    a.precio_compra,
                    a.precio_venta,
                    a.imagen,
                    a.idcategoria,
                    a.idsubcategoria,
                    a.idmedida,
                    a.idalmacen,
                    a.controla_lotes,
                    a.controla_vencimiento,
                    a.dias_alerta_vencimiento,
                    c.nombre AS categoria,
                    sc.nombre AS subcategoria,
                    m.nombre AS medida,
                    m.codigo AS codigo_medida,
                    al.nombre AS almacen
                FROM articulo a
                INNER JOIN categoria c
                    ON a.idcategoria = c.idcategoria
                LEFT JOIN subcategoria sc
                    ON a.idsubcategoria = sc.idsubcategoria
                LEFT JOIN medida m
                    ON a.idmedida = m.idmedida
                LEFT JOIN almacen al
                    ON a.idalmacen = al.idalmacen
                WHERE a.condicion = 1
                ORDER BY a.nombre ASC";

        $productos = $this->conexion->getDataAll($sql);
        // Una compra puede contener varios lotes de una misma variante. La
        // presentación se identifica por idvariacion y no por el SKU padre.
        $variantes = $this->conexion->getDataAll(
            "SELECT av.idvariacion,av.idarticulo,av.sku,av.combinacion,av.stock,
                    av.precio_compra,av.precio_venta
             FROM articulo_variacion av JOIN articulo a ON a.idarticulo=av.idarticulo
             WHERE av.estado=1 AND a.condicion=1
             ORDER BY av.idarticulo,av.combinacion,av.idvariacion"
        );
        $porArticulo = [];
        foreach ($variantes as $variante) {
            $porArticulo[(int)$variante['idarticulo']][] = $variante;
        }
        foreach ($productos as &$producto) {
            $producto['variaciones'] = $porArticulo[(int)$producto['idarticulo']] ?? [];
        }
        unset($producto);
        return $productos;
    }

    public function datosFormulario(): array
    {
        return [
            'categorias' => $this->conexion->getDataAll(
                "SELECT idcategoria, nombre
                 FROM categoria
                 WHERE condicion = 1
                 ORDER BY nombre ASC"
            ),
            'subcategorias' => $this->conexion->getDataAll(
                "SELECT idsubcategoria, idcategoria, nombre
                 FROM subcategoria
                 WHERE estado = 1
                 ORDER BY nombre ASC"
            ),
            'medidas' => $this->conexion->getDataAll(
                "SELECT idmedida, codigo, nombre
                 FROM medida
                 WHERE condicion = 1
                 ORDER BY CASE WHEN UPPER(codigo) = 'NIU' THEN 0 ELSE 1 END,
                          nombre ASC"
            ),
            'almacenes' => $this->conexion->getDataAll(
                "SELECT idalmacen, idsucursal, nombre
                 FROM almacen
                 WHERE estado = 1
                 ORDER BY CASE WHEN UPPER(nombre) LIKE '%PRINCIPAL%' THEN 0 ELSE 1 END,
                          nombre ASC"
            ),
            'categorias_compra' => $this->conexion->getDataAll(
                "SELECT idcategoria_compra, nombre, descripcion
                 FROM categoria_compra
                 WHERE estado = 1
                 ORDER BY nombre ASC"
            ),
            'tipos_pago' => array_values(array_filter(array_map(
                function (array $tipoPago): array {
                    $tipoPago['condicion'] = $this->normalizarCondicionPago(
                        (string)($tipoPago['nombre'] ?? '')
                    );
                    return $tipoPago;
                },
                $this->conexion->getDataAll(
                    "SELECT idtipopago, nombre, descripcion
                     FROM tipo_pago
                     WHERE estado = 1
                     ORDER BY idtipopago ASC"
                )
            ), static function (array $tipoPago): bool {
                return in_array(
                    (string)($tipoPago['condicion'] ?? ''),
                    ['CONTADO', 'CREDITO'],
                    true
                );
            })),
            'formas_pago' => $this->conexion->getDataAll(
                "SELECT
                    fp.idforma_pago,
                    fp.nombre,
                    fp.es_efectivo,
                    fp.es_combinado,
                    fpd.requiere_caja_abierta,
                    fpd.requiere_operacion,
                    fpd.idcuenta_financiera
                 FROM forma_pago AS fp
                 LEFT JOIN forma_pago_destino AS fpd
                    ON fpd.idforma_pago = fp.idforma_pago
                 WHERE fp.activo = 1
                   AND fp.condicion = 1
                   AND fp.es_combinado = 0
                 ORDER BY
                    CASE WHEN fp.es_efectivo = 1 THEN 0 ELSE 1 END,
                    fp.nombre ASC"
            )
        ];
    }

    private function normalizarCondicionPago(string $valor): string
    {
        $normalizado = mb_strtoupper(trim($valor), 'UTF-8');
        $normalizado = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü'],
            ['A', 'E', 'I', 'O', 'U', 'U'],
            $normalizado
        );

        if (str_contains($normalizado, 'CREDITO')) {
            return 'CREDITO';
        }

        if (str_contains($normalizado, 'CONTADO')) {
            return 'CONTADO';
        }

        return '';
    }

    private function normalizarDetalle(array $detalle, int $numeroFila): array
    {
        $tipoDetalle = strtoupper(trim((string)($detalle['tipo_detalle'] ?? '')));
        $origen = strtoupper(trim((string)($detalle['origen'] ?? '')));
        $cantidad = round((float)($detalle['cantidad'] ?? 0), 3);
        $precioCompra = round((float)($detalle['precio_compra'] ?? 0), 2);
        $precioVentaRaw = $detalle['precio_venta'] ?? null;
        $precioVenta = $precioVentaRaw === '' || $precioVentaRaw === null
            ? null
            : round((float)$precioVentaRaw, 2);

        $tipoProductoCrudo = strtolower(trim((string)($detalle['producto_tipo'] ?? 'simple')));
        $productoTipo = in_array($tipoProductoCrudo, ['variante', 'variacion', 'variable'], true)
            ? 'variante'
            : 'simple';

        if (!in_array($tipoDetalle, ['INVENTARIO', 'NO_INVENTARIO'], true)) {
            throw new RuntimeException(
                "El tipo del detalle {$numeroFila} no es válido."
            );
        }

        if ($cantidad <= 0) {
            throw new RuntimeException(
                "La cantidad del detalle {$numeroFila} debe ser mayor que cero."
            );
        }

        if ($precioCompra < 0) {
            throw new RuntimeException(
                "El costo del detalle {$numeroFila} no puede ser negativo."
            );
        }

        if ($precioVenta !== null && $precioVenta < 0) {
            throw new RuntimeException(
                "El precio de venta del detalle {$numeroFila} no puede ser negativo."
            );
        }

        if ($tipoDetalle === 'INVENTARIO') {
            if (!in_array($origen, ['EXISTENTE', 'NUEVO'], true)) {
                throw new RuntimeException(
                    "El origen del producto en el detalle {$numeroFila} no es válido."
                );
            }

            if (abs($cantidad - round($cantidad)) > 0.0001) {
                throw new RuntimeException(
                    "Los productos inventariables deben ingresarse en cantidades enteras (detalle {$numeroFila})."
                );
            }
        } else {
            $origen = 'GASTO';
            $productoTipo = 'simple';
        }

        $codigo = $this->limpiarCodigo(
            $detalle['codigo'] ?? '',
            $productoTipo === 'variante' ? 100 : 50
        );
        $grupo = $this->limpiarCodigo($detalle['grupo'] ?? '', 50);
        $variante = $this->limpiarTexto($detalle['variante'] ?? '', 150);
        $codigoAfectacionIgv = preg_replace(
            '/[^0-9]/',
            '',
            (string)($detalle['codigo_afectacion_igv'] ?? '10')
        ) ?? '10';
        $codigoAfectacionIgv = substr($codigoAfectacionIgv, 0, 2);
        if ($codigoAfectacionIgv === '') {
            $codigoAfectacionIgv = '10';
        }

        if (
            $tipoDetalle === 'INVENTARIO'
            && $origen === 'NUEVO'
            && $productoTipo === 'variante'
        ) {
            if ($grupo === '') {
                throw new RuntimeException(
                    "El producto variable del detalle {$numeroFila} requiere Grupo / SKU padre."
                );
            }
            if ($codigo === '') {
                throw new RuntimeException(
                    "La variante del detalle {$numeroFila} requiere SKU."
                );
            }
            if ($variante === '') {
                throw new RuntimeException(
                    "La variante del detalle {$numeroFila} requiere descripción de variante."
                );
            }
            if ($grupo === $codigo) {
                throw new RuntimeException(
                    "El SKU padre y el SKU de la variante deben ser distintos (detalle {$numeroFila})."
                );
            }
        }

        $importe = round($cantidad * $precioCompra, 2);

        if ($importe <= 0) {
            throw new RuntimeException(
                "El importe del detalle {$numeroFila} debe ser mayor que cero."
            );
        }

        return [
            'tipo_detalle' => $tipoDetalle,
            'origen' => $origen,
            'producto_tipo' => $productoTipo,
            'grupo' => $grupo,
            'variante' => $variante,
            'codigo_afectacion_igv' => $codigoAfectacionIgv,
            'idarticulo' => (int)($detalle['idarticulo'] ?? 0),
            'idvariacion' => (int)($detalle['idvariacion'] ?? 0),
            'controla_lotes' => !empty($detalle['controla_lotes']) ? 1 : 0,
            'controla_vencimiento' => !empty($detalle['controla_vencimiento']) ? 1 : 0,
            'dias_alerta_vencimiento' => (int)($detalle['dias_alerta_vencimiento'] ?? 30),
            'numero_lote' => $this->limpiarTexto($detalle['numero_lote'] ?? '', 80),
            'fecha_vencimiento' => trim((string)($detalle['fecha_vencimiento'] ?? '')),
            'descripcion' => $this->limpiarTexto($detalle['descripcion'] ?? '', 250),
            'idcategoria_compra' => (int)($detalle['idcategoria_compra'] ?? 0),
            'idcategoria' => (int)($detalle['idcategoria'] ?? 0),
            'idsubcategoria' => (int)($detalle['idsubcategoria'] ?? 0),
            'idmedida' => (int)($detalle['idmedida'] ?? 0),
            'idalmacen' => (int)($detalle['idalmacen'] ?? 0),
            'codigo' => $codigo,
            'nombre' => $this->limpiarTexto($detalle['nombre'] ?? '', 100),
            'cantidad' => $cantidad,
            'precio_compra' => $precioCompra,
            'precio_venta' => $precioVenta,
            'importe' => $importe
        ];
    }

    private function registrarDetalleInventario(
        int $idingreso,
        string $fechaHora,
        string $detalleDocumento,
        array $detalle
    ): void {
        $idarticulo = 0;
        $idvariacion = 0;
        $articulo = null;
        $esVarianteNueva = $detalle['origen'] === 'NUEVO'
            && ($detalle['producto_tipo'] ?? 'simple') === 'variante';

        if ($esVarianteNueva) {
            $idarticulo = (int)($detalle['idarticulo'] ?? 0);
            $idvariacion = (int)($detalle['idvariacion'] ?? 0);

            if ($idarticulo <= 0 || $idvariacion <= 0) {
                throw new RuntimeException(
                    'No se pudo enlazar una de las variantes nuevas con su producto padre.'
                );
            }

            $articulo = $this->conexion->getData(
                "SELECT a.idarticulo, a.nombre, a.idalmacen, a.idmedida,
                        a.stock, a.precio_compra, a.precio_venta,
                        av.idvariacion, av.sku, av.combinacion,
                        av.stock AS stock_variacion
                 FROM articulo a
                 INNER JOIN articulo_variacion av
                    ON av.idarticulo = a.idarticulo
                 WHERE a.idarticulo = ?
                   AND av.idvariacion = ?
                   AND av.estado = 1
                 LIMIT 1
                 FOR UPDATE",
                [$idarticulo, $idvariacion]
            );
        } elseif ($detalle['origen'] === 'NUEVO') {
            $idarticulo = $this->crearProductoNuevo($detalle);

            $articulo = $this->conexion->getData(
                "SELECT idarticulo, nombre, idalmacen, idmedida, stock,
                        precio_compra, precio_venta
                 FROM articulo
                 WHERE idarticulo = ?
                 LIMIT 1
                 FOR UPDATE",
                [$idarticulo]
            );
        } else {
            $idarticulo = (int)$detalle['idarticulo'];

            if ($idarticulo <= 0) {
                throw new RuntimeException('Debe seleccionar un producto existente válido.');
            }

            $articulo = $this->conexion->getData(
                "SELECT idarticulo, nombre, idalmacen, idmedida, stock,
                        precio_compra, precio_venta, condicion
                 FROM articulo
                 WHERE idarticulo = ?
                 LIMIT 1
                 FOR UPDATE",
                [$idarticulo]
            );

            if (!$articulo || (int)$articulo['condicion'] !== 1) {
                throw new RuntimeException(
                    'Uno de los productos seleccionados no existe o está desactivado.'
                );
            }
        }

        if (!$articulo) {
            throw new RuntimeException('No se pudo recuperar el producto de la compra.');
        }
        if (!$esVarianteNueva && (int)($detalle['idvariacion'] ?? 0) > 0) {
            $idvariacion = (int)$detalle['idvariacion'];
            $existeVariante = $this->conexion->getData(
                'SELECT idvariacion FROM articulo_variacion WHERE idvariacion=? AND idarticulo=? AND estado=1 LIMIT 1 FOR UPDATE',
                [$idvariacion, $idarticulo]
            );
            if (!$existeVariante) {
                throw new RuntimeException('La variante no corresponde al producto seleccionado.');
            }
        }

        $configLotes = $this->conexion->getData(
            "SELECT controla_lotes, controla_vencimiento FROM articulo WHERE idarticulo=?",
            [$idarticulo]
        );
        $controlLotes = (int)($configLotes['controla_lotes'] ?? 0) === 1;
        $controlVence = (int)($configLotes['controla_vencimiento'] ?? 0) === 1;
        $numeroLote = trim((string)($detalle['numero_lote'] ?? ''));
        $fechaVence = trim((string)($detalle['fecha_vencimiento'] ?? ''));
        if (($controlLotes || $controlVence) && $numeroLote === '') {
            throw new RuntimeException('Este producto requiere un número de lote para la compra.');
        }
        if ($controlVence && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVence)) {
            throw new RuntimeException('Este producto requiere fecha de vencimiento válida (AAAA-MM-DD).');
        }
        if ($fechaVence !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVence)
            || date('Y-m-d', strtotime($fechaVence)) !== $fechaVence)) {
            throw new RuntimeException('La fecha de vencimiento del lote no es válida.');
        }
        // Un producto sin vencimiento habilitado no debe recibir una fecha oculta
        // enviada desde el cliente. La fecha siempre pertenece a cada lote.
        if (!$controlVence && $fechaVence !== '') {
            throw new RuntimeException('El producto no controla vencimientos. Activa «Lotes y fecha de vencimiento» antes de indicar una fecha.');
        }
        if (!$controlLotes && !$controlVence && $numeroLote !== '') {
            throw new RuntimeException('El producto no controla lotes. Activa el control antes de registrar un número de lote.');
        }
        if ($controlVence && $fechaVence < date('Y-m-d')) {
            throw new RuntimeException('La fecha indicada ya venció. Revisa el lote antes de ingresar esta mercadería.');
        }
        if ($numeroLote !== '') {
            // La fila de artículo se encuentra bloqueada FOR UPDATE en esta
            // transacción: se serializan recepciones simultáneas del mismo producto.
            $fechaLote = $fechaVence !== '' ? $fechaVence : null;
            $lotesIncompatibles = (int)$this->conexion->getValue(
                "SELECT COUNT(*) FROM detalle_ingreso
                 WHERE idarticulo=? AND idvariacion <=> ? AND numero_lote=?
                   AND tipo_detalle='INVENTARIO' AND afecta_stock=1 AND estado=1
                   AND NOT (fecha_vencimiento <=> ?)",
                [$idarticulo, $idvariacion > 0 ? $idvariacion : null, $numeroLote, $fechaLote]
            );
            if ($lotesIncompatibles > 0) {
                throw new RuntimeException('El lote «' . $numeroLote . '» ya tiene otra fecha de vencimiento para esta presentación. Verifica la etiqueta o utiliza otro número de lote.');
            }
        }
        $cantidad = (int)round((float)$detalle['cantidad']);
        $precioCompra = (float)$detalle['precio_compra'];
        $precioVenta = $detalle['precio_venta'];
        $idalmacen = (int)($articulo['idalmacen'] ?? 0);
        $idmedida = (int)($articulo['idmedida'] ?? 0);

        if ($esVarianteNueva) {
            $descripcion = trim(
                (string)$detalle['nombre']
                . ($detalle['variante'] !== '' ? ' - ' . $detalle['variante'] : '')
                . ' · SKU: ' . (string)$detalle['codigo']
            );
        } else {
            $descripcion = $detalle['descripcion'] !== ''
                ? $detalle['descripcion']
                : (string)$articulo['nombre'];
        }

        $sqlDetalle = "INSERT INTO {$this->tableNameDetalle}
            (idingreso, tipo_detalle, idarticulo, descripcion,
             idcategoria_compra, idalmacen, idmedida, afecta_stock,
             cantidad, stock_venta, precio_compra, precio_venta,
             importe, estado, stock_estado, idvariacion, numero_lote, fecha_vencimiento)
            VALUES (?, 'INVENTARIO', ?, ?, NULL, ?, ?, 1,
                    ?, ?, ?, ?, ?, 1, 1, ?, ?, ?)";

        $this->conexion->setData(
            $sqlDetalle,
            [
                $idingreso,
                $idarticulo,
                $descripcion,
                $idalmacen > 0 ? $idalmacen : null,
                $idmedida > 0 ? $idmedida : null,
                $cantidad,
                $cantidad,
                $precioCompra,
                $precioVenta,
                (float)$detalle['importe'],
                $idvariacion > 0 ? $idvariacion : null,
                $numeroLote !== '' ? $numeroLote : null,
                $fechaVence !== '' ? $fechaVence : null
            ]
        );

        if ($idvariacion > 0) {
            if ($precioVenta !== null && $precioVenta > 0) {
                $this->conexion->setData(
                    "UPDATE articulo_variacion
                     SET stock = COALESCE(stock, 0) + ?,
                         precio_compra = ?,
                         precio_venta = ?
                     WHERE idvariacion = ?
                       AND idarticulo = ?",
                    [$cantidad, $precioCompra, $precioVenta, $idvariacion, $idarticulo]
                );
            } else {
                $this->conexion->setData(
                    "UPDATE articulo_variacion
                     SET stock = COALESCE(stock, 0) + ?,
                         precio_compra = ?
                     WHERE idvariacion = ?
                       AND idarticulo = ?",
                    [$cantidad, $precioCompra, $idvariacion, $idarticulo]
                );
            }

            $resumenVariaciones = $this->conexion->getData(
                "SELECT COALESCE(SUM(stock), 0) AS stock_total,
                        COALESCE(MIN(precio_compra), 0) AS precio_compra_min,
                        COALESCE(MIN(precio_venta), 0) AS precio_venta_min
                 FROM articulo_variacion
                 WHERE idarticulo = ?
                   AND estado = 1",
                [$idarticulo]
            );

            $stockFinal = (int)($resumenVariaciones['stock_total'] ?? 0);
            $this->conexion->setData(
                "UPDATE articulo
                 SET stock = ?,
                     precio_compra = ?,
                     precio_venta = ?
                 WHERE idarticulo = ?",
                [
                    $stockFinal,
                    (float)($resumenVariaciones['precio_compra_min'] ?? $precioCompra),
                    (float)($resumenVariaciones['precio_venta_min'] ?? ($precioVenta ?? 0)),
                    $idarticulo
                ]
            );
        } else {
            if ($precioVenta !== null && $precioVenta > 0) {
                $this->conexion->setData(
                    "UPDATE articulo
                     SET stock = COALESCE(stock, 0) + ?,
                         precio_compra = ?,
                         precio_venta = ?
                     WHERE idarticulo = ?",
                    [$cantidad, $precioCompra, $precioVenta, $idarticulo]
                );
            } else {
                $this->conexion->setData(
                    "UPDATE articulo
                     SET stock = COALESCE(stock, 0) + ?,
                         precio_compra = ?
                     WHERE idarticulo = ?",
                    [$cantidad, $precioCompra, $idarticulo]
                );
            }

            $stockFinal = (int)$this->conexion->getValue(
                "SELECT stock
                 FROM articulo
                 WHERE idarticulo = ?",
                [$idarticulo]
            );
        }

        $totalIngreso = round($cantidad * $precioCompra, 2);
        $totalExistencia = round($stockFinal * $precioCompra, 2);

        $sqlKardex = "INSERT INTO {$this->tableNameKardex}
            (iddetalle, idarticulo, fecha, detalle,
             cantidadi, costoui, totali,
             cantidads, costous, totals,
             cantidadex, costouex, totalex,
             tipo, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?,
                    0, 0, 0,
                    ?, ?, ?, 'Ingreso', 'Activo')";

        $this->conexion->setData(
            $sqlKardex,
            [
                $idingreso,
                $idarticulo,
                substr($fechaHora, 0, 10),
                $esVarianteNueva ? $detalleDocumento . ' · ' . $descripcion : $detalleDocumento,
                $cantidad,
                $precioCompra,
                $totalIngreso,
                $stockFinal,
                $precioCompra,
                $totalExistencia
            ]
        );
    }

    private function registrarDetalleNoInventario(
        int $idingreso,
        array $detalle
    ): void {
        $descripcion = $detalle['descripcion'] !== ''
            ? $detalle['descripcion']
            : $detalle['nombre'];

        if ($descripcion === '') {
            throw new RuntimeException(
                'Debe ingresar la descripción del gasto o servicio.'
            );
        }

        $idcategoriaCompra = (int)$detalle['idcategoria_compra'];

        if ($idcategoriaCompra <= 0) {
            throw new RuntimeException(
                'Debe seleccionar una categoría para el gasto o servicio.'
            );
        }

        $categoriaCompra = $this->conexion->getData(
            "SELECT idcategoria_compra
             FROM categoria_compra
             WHERE idcategoria_compra = ?
               AND estado = 1
             LIMIT 1",
            [$idcategoriaCompra]
        );

        if (!$categoriaCompra) {
            throw new RuntimeException(
                'La categoría del gasto o servicio no existe o está desactivada.'
            );
        }

        $idmedida = (int)$detalle['idmedida'];

        if ($idmedida > 0) {
            $medida = $this->conexion->getData(
                "SELECT idmedida
                 FROM medida
                 WHERE idmedida = ?
                   AND condicion = 1
                 LIMIT 1",
                [$idmedida]
            );

            if (!$medida) {
                throw new RuntimeException(
                    'La unidad seleccionada para el gasto no es válida.'
                );
            }
        }

        $sqlDetalle = "INSERT INTO {$this->tableNameDetalle}
            (idingreso, tipo_detalle, idarticulo, descripcion,
             idcategoria_compra, idalmacen, idmedida, afecta_stock,
             cantidad, stock_venta, precio_compra, precio_venta,
             importe, estado, stock_estado)
            VALUES (?, 'NO_INVENTARIO', NULL, ?, ?, NULL, ?, 0,
                    ?, 0, ?, NULL, ?, 1, 0)";

        $this->conexion->setData(
            $sqlDetalle,
            [
                $idingreso,
                $descripcion,
                $idcategoriaCompra,
                $idmedida > 0 ? $idmedida : null,
                (float)$detalle['cantidad'],
                (float)$detalle['precio_compra'],
                (float)$detalle['importe']
            ]
        );
    }

    /**
     * Prepara todos los grupos variables antes de registrar el detalle de compra.
     * El producto padre y sus variantes se crean con stock 0; el stock se suma
     * luego desde cada detalle para que la compra y el kardex sean la fuente del ingreso.
     */
    private function prepararProductosVariablesCompra(array $detalles): array
    {
        $grupos = [];
        $codigosLote = [];

        foreach ($detalles as $indice => $detalle) {
            if (
                ($detalle['tipo_detalle'] ?? '') !== 'INVENTARIO'
                || ($detalle['origen'] ?? '') !== 'NUEVO'
            ) {
                continue;
            }

            $codigo = strtoupper(trim((string)($detalle['codigo'] ?? '')));
            if ($codigo !== '') {
                if (isset($codigosLote[$codigo])) {
                    throw new RuntimeException(
                        'El SKU ' . $codigo . ' está repetido entre los productos nuevos de la compra.'
                    );
                }
                $codigosLote[$codigo] = true;
            }

            if (($detalle['producto_tipo'] ?? 'simple') !== 'variante') {
                continue;
            }

            $grupo = strtoupper(trim((string)($detalle['grupo'] ?? '')));
            if ($grupo === '') {
                throw new RuntimeException('Un producto variable no tiene Grupo / SKU padre.');
            }
            $grupos[$grupo][] = $indice;
        }

        foreach ($grupos as $grupo => $indices) {
            if (isset($codigosLote[$grupo])) {
                throw new RuntimeException(
                    'El SKU padre ' . $grupo . ' coincide con el SKU de otro producto o variante de la compra.'
                );
            }

            if ($this->codigoProductoExisteGlobal($grupo)) {
                throw new RuntimeException(
                    'Ya existe un producto o variante con el SKU padre ' . $grupo . '.'
                );
            }

            $base = $detalles[$indices[0]];
            foreach ($indices as $indice) {
                $actual = $detalles[$indice];
                $consistente = (string)$actual['nombre'] === (string)$base['nombre']
                    && (int)$actual['idcategoria'] === (int)$base['idcategoria']
                    && (int)$actual['idsubcategoria'] === (int)$base['idsubcategoria']
                    && (int)$actual['idalmacen'] === (int)$base['idalmacen']
                    && (int)$actual['idmedida'] === (int)$base['idmedida']
                    && (string)$actual['codigo_afectacion_igv'] === (string)$base['codigo_afectacion_igv'];

                if (!$consistente) {
                    throw new RuntimeException(
                        'Todas las variantes del grupo ' . $grupo
                        . ' deben usar el mismo producto, categoría, subcategoría, almacén, unidad y afectación IGV.'
                    );
                }

                if ($this->codigoProductoExisteGlobal((string)$actual['codigo'])) {
                    throw new RuntimeException(
                        'Ya existe un producto o variante con el SKU ' . (string)$actual['codigo'] . '.'
                    );
                }
            }

            $resultado = $this->crearProductoVariableCompra(
                $base,
                array_map(
                    fn(int $indice): array => $detalles[$indice],
                    $indices
                )
            );

            foreach ($indices as $indice) {
                $sku = strtoupper(trim((string)$detalles[$indice]['codigo']));
                $detalles[$indice]['idarticulo'] = (int)$resultado['idarticulo'];
                $detalles[$indice]['idvariacion'] = (int)($resultado['variaciones'][$sku] ?? 0);

                if ($detalles[$indice]['idvariacion'] <= 0) {
                    throw new RuntimeException(
                        'No se pudo identificar la variante ' . $sku . ' del grupo ' . $grupo . '.'
                    );
                }
            }
        }

        return $detalles;
    }

    private function crearProductoVariableCompra(array $base, array $variantes): array
    {
        if (!$variantes) {
            throw new RuntimeException('El producto variable no contiene variantes.');
        }

        $idcategoria = (int)$base['idcategoria'];
        $idsubcategoria = (int)$base['idsubcategoria'];
        $idmedida = (int)$base['idmedida'];
        $idalmacen = (int)$base['idalmacen'];
        $nombre = $this->limpiarTexto($base['nombre'] ?? '', 100);
        $codigoPadre = $this->limpiarCodigo($base['grupo'] ?? '', 50);

        $this->validarCatalogosProductoNuevo(
            $idcategoria,
            $idsubcategoria,
            $idmedida,
            $idalmacen
        );

        if ($nombre === '' || $codigoPadre === '') {
            throw new RuntimeException('El producto variable requiere nombre y SKU padre.');
        }

        $tributacion = $this->obtenerTributacionProductoCompra(
            (string)($base['codigo_afectacion_igv'] ?? '10')
        );

        $preciosCompra = array_map(
            fn(array $item): float => (float)($item['precio_compra'] ?? 0),
            $variantes
        );
        $preciosVenta = array_map(
            fn(array $item): float => (float)($item['precio_venta'] ?? 0),
            $variantes
        );

        $idarticulo = (int)$this->conexion->setDataReturnId(
            "INSERT INTO articulo
                (idcategoria, idsubcategoria, idmedida, idalmacen, codigo, nombre,
                 stock, precio_compra, precio_venta, descripcion, imagen,
                 codigo_afectacion_igv, porcentaje_igv,
                 unidad_medida_sunat, codigo_producto_sunat, condicion)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'default.png', ?, ?, ?, NULL, 1)",
            [
                $idcategoria,
                $idsubcategoria > 0 ? $idsubcategoria : null,
                $idmedida,
                $idalmacen,
                $codigoPadre,
                $nombre,
                $preciosCompra ? min($preciosCompra) : 0,
                $preciosVenta ? min($preciosVenta) : 0,
                'Creado desde el módulo de Compras · Producto variable',
                $tributacion['codigo_afectacion_igv'],
                $tributacion['porcentaje_igv'],
                $tributacion['unidad_medida_sunat']
            ]
        );

        if ($idarticulo <= 0) {
            throw new RuntimeException(
                'No se pudo crear el producto variable ' . $codigoPadre . '.'
            );
        }

        $idsVariaciones = [];
        foreach ($variantes as $variante) {
            $sku = $this->limpiarCodigo($variante['codigo'] ?? '', 100);
            $combinacion = $this->limpiarTexto($variante['variante'] ?? '', 150);

            if ($sku === '' || $combinacion === '') {
                throw new RuntimeException(
                    'Una variante del grupo ' . $codigoPadre . ' no tiene SKU o descripción.'
                );
            }

            $idvariacion = (int)$this->conexion->setDataReturnId(
                "INSERT INTO articulo_variacion
                    (idarticulo, combinacion, sku, stock, precio_compra, precio_venta, estado)
                 VALUES (?, ?, ?, 0, ?, ?, 1)",
                [
                    $idarticulo,
                    $combinacion,
                    $sku,
                    (float)$variante['precio_compra'],
                    $variante['precio_venta']
                ]
            );

            if ($idvariacion <= 0) {
                throw new RuntimeException(
                    'No se pudo crear la variante ' . $sku . ' del grupo ' . $codigoPadre . '.'
                );
            }

            $idsVariaciones[$sku] = $idvariacion;
        }

        return [
            'idarticulo' => $idarticulo,
            'variaciones' => $idsVariaciones
        ];
    }

    private function validarCatalogosProductoNuevo(
        int $idcategoria,
        int $idsubcategoria,
        int $idmedida,
        int $idalmacen
    ): void {
        if ($idcategoria <= 0 || $idmedida <= 0 || $idalmacen <= 0) {
            throw new RuntimeException(
                'El producto nuevo requiere categoría, unidad y almacén.'
            );
        }

        $categoria = $this->conexion->getData(
            "SELECT idcategoria FROM categoria
             WHERE idcategoria = ? AND condicion = 1 LIMIT 1",
            [$idcategoria]
        );
        if (!$categoria) {
            throw new RuntimeException('La categoría del producto nuevo no es válida.');
        }

        if ($idsubcategoria > 0) {
            $subcategoria = $this->conexion->getData(
                "SELECT idsubcategoria FROM subcategoria
                 WHERE idsubcategoria = ? AND idcategoria = ? AND estado = 1 LIMIT 1",
                [$idsubcategoria, $idcategoria]
            );
            if (!$subcategoria) {
                throw new RuntimeException(
                    'La subcategoría no pertenece a la categoría seleccionada.'
                );
            }
        }

        $medida = $this->conexion->getData(
            "SELECT idmedida FROM medida
             WHERE idmedida = ? AND condicion = 1 LIMIT 1",
            [$idmedida]
        );
        if (!$medida) {
            throw new RuntimeException('La unidad del producto nuevo no es válida.');
        }

        $almacen = $this->conexion->getData(
            "SELECT idalmacen FROM almacen
             WHERE idalmacen = ? AND estado = 1 LIMIT 1",
            [$idalmacen]
        );
        if (!$almacen) {
            throw new RuntimeException('El almacén del producto nuevo no es válido.');
        }
    }

    private function codigoProductoExisteGlobal(string $codigo): bool
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return false;
        }

        $producto = $this->conexion->getData(
            "SELECT idarticulo FROM articulo WHERE codigo = ? LIMIT 1 FOR UPDATE",
            [$codigo]
        );
        if ($producto) {
            return true;
        }

        $variacion = $this->conexion->getData(
            "SELECT idvariacion FROM articulo_variacion WHERE sku = ? LIMIT 1 FOR UPDATE",
            [$codigo]
        );

        return !empty($variacion);
    }

    private function obtenerTributacionProductoCompra(string $codigoAfectacion): array
    {
        $codigoAfectacion = preg_replace('/[^0-9]/', '', $codigoAfectacion) ?? '';
        $codigoAfectacion = substr($codigoAfectacion, 0, 2);
        if ($codigoAfectacion === '') {
            $codigoAfectacion = '10';
        }

        $empresa = $this->conexion->getData(
            "SELECT codigo_afectacion_igv_predeterminado,
                    porcentaje_igv_predeterminado,
                    unidad_medida_sunat_predeterminada
             FROM datos_negocio
             WHERE condicion = 1
             ORDER BY id_negocio DESC
             LIMIT 1"
        );

        $afectacion = $this->conexion->getData(
            "SELECT codigo, porcentaje_predeterminado
             FROM sunat_catalogo_07_afectacion_igv
             WHERE codigo = ? AND activo = 1
             LIMIT 1",
            [$codigoAfectacion]
        );

        if (!$afectacion) {
            throw new RuntimeException(
                'La afectación IGV ' . $codigoAfectacion . ' no es válida.'
            );
        }

        $porcentaje = $codigoAfectacion === '10'
            ? round((float)($empresa['porcentaje_igv_predeterminado']
                ?? $afectacion['porcentaje_predeterminado']
                ?? 18), 2)
            : 0.00;

        $unidadSunat = strtoupper(trim((string)(
            $empresa['unidad_medida_sunat_predeterminada'] ?? 'NIU'
        )));
        if (!preg_match('/^[A-Z0-9]{2,3}$/', $unidadSunat)) {
            $unidadSunat = 'NIU';
        }

        return [
            'codigo_afectacion_igv' => $codigoAfectacion,
            'porcentaje_igv' => $porcentaje,
            'unidad_medida_sunat' => $unidadSunat
        ];
    }

    private function crearProductoNuevo(array $detalle): int
    {
        $idcategoria = (int)$detalle['idcategoria'];
        $idsubcategoria = (int)$detalle['idsubcategoria'];
        $idmedida = (int)$detalle['idmedida'];
        $idalmacen = (int)$detalle['idalmacen'];
        $nombre = $detalle['nombre'];
        $codigo = $detalle['codigo'];

        if ($nombre === '') {
            throw new RuntimeException('Debe ingresar el nombre del producto nuevo.');
        }

        $this->validarCatalogosProductoNuevo(
            $idcategoria,
            $idsubcategoria,
            $idmedida,
            $idalmacen
        );

        if ($codigo === '') {
            $codigo = $this->generarCodigoProducto();
        }

        if ($this->codigoProductoExisteGlobal($codigo)) {
            throw new RuntimeException(
                'Ya existe un producto o variante con el código ' . $codigo . '.'
            );
        }

        $controlVence = (int)($detalle['controla_vencimiento'] ?? 0) === 1;
        $controlLotes = $controlVence || (int)($detalle['controla_lotes'] ?? 0) === 1;
        $diasAlerta = (int)($detalle['dias_alerta_vencimiento'] ?? 30);
        if ($diasAlerta < 1 || $diasAlerta > 3650) throw new RuntimeException('Días de alerta inválidos.');
        if ($controlLotes && trim((string)($detalle['numero_lote'] ?? '')) === '') throw new RuntimeException('Debes identificar el lote del producto nuevo.');
        if ($controlVence && trim((string)($detalle['fecha_vencimiento'] ?? '')) === '') throw new RuntimeException('Debes ingresar vencimiento para el producto nuevo.');
        if ($controlVence) {
            $fecha = (string)($detalle['fecha_vencimiento'] ?? '');
            $fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha || $fecha < date('Y-m-d')) {
                throw new RuntimeException('La fecha de vencimiento no es válida o el lote ya venció.');
            }
        }


        $tributacion = $this->obtenerTributacionProductoCompra(
            (string)($detalle['codigo_afectacion_igv'] ?? '10')
        );

        $sql = "INSERT INTO articulo
            (idcategoria, idsubcategoria, idmedida, codigo, nombre,
             stock, precio_compra, precio_venta, descripcion, imagen,
             codigo_afectacion_igv, porcentaje_igv, unidad_medida_sunat,
             codigo_producto_sunat, condicion, idalmacen, controla_lotes, controla_vencimiento, dias_alerta_vencimiento)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, 'default.png', ?, ?, ?, NULL, 1, ?, ?, ?, ?)";

        $idarticulo = (int)$this->conexion->setDataReturnId(
            $sql,
            [
                $idcategoria,
                $idsubcategoria > 0 ? $idsubcategoria : null,
                $idmedida,
                $codigo,
                $nombre,
                (float)$detalle['precio_compra'],
                $detalle['precio_venta'],
                'Creado desde el módulo de Compras',
                $tributacion['codigo_afectacion_igv'],
                $tributacion['porcentaje_igv'],
                $tributacion['unidad_medida_sunat'],
                $idalmacen, (int)$controlLotes, (int)$controlVence, $diasAlerta
            ]
        );

        if ($idarticulo <= 0) {
            throw new RuntimeException('No se pudo crear el producto nuevo.');
        }

        return $idarticulo;
    }

    private function generarCodigoProducto(): string
    {
        for ($intento = 0; $intento < 15; $intento++) {
            $codigo = 'CMP-'
                . date('ymdHis')
                . '-'
                . random_int(10, 99);

            $existe = $this->conexion->getData(
                "SELECT idarticulo
                 FROM articulo
                 WHERE codigo = ?
                 LIMIT 1",
                [$codigo]
            );

            if (!$existe) {
                return $codigo;
            }
        }

        throw new RuntimeException('No se pudo generar un código único para el producto.');
    }

    private function normalizarFecha(mixed $fecha): string
    {
        $valor = trim((string)$fecha);

        if ($valor === '') {
            throw new RuntimeException('Debe ingresar la fecha de la compra.');
        }

        $formatos = ['!Y-m-d H:i:s', '!Y-m-d'];

        foreach ($formatos as $formato) {
            $fechaObj = DateTimeImmutable::createFromFormat($formato, $valor);
            $errores = DateTimeImmutable::getLastErrors();
            $sinErrores = $errores === false
                || (
                    (int)($errores['warning_count'] ?? 0) === 0
                    && (int)($errores['error_count'] ?? 0) === 0
                );

            if ($fechaObj instanceof DateTimeImmutable && $sinErrores) {
                return $fechaObj->format('Y-m-d H:i:s');
            }
        }

        throw new RuntimeException('La fecha de la compra no es válida.');
    }

    private function limpiarTexto(mixed $valor, int $maximo): string
    {
        $texto = preg_replace('/\s+/u', ' ', trim((string)$valor));
        $texto = $texto ?? '';

        if (function_exists('mb_substr')) {
            return mb_substr($texto, 0, $maximo, 'UTF-8');
        }

        return substr($texto, 0, $maximo);
    }

    private function limpiarCodigo(mixed $valor, int $maximo = 50): string
    {
        $codigo = strtoupper(trim((string)$valor));
        $codigo = preg_replace('/[^A-Z0-9._\-]/', '', $codigo) ?? '';

        return substr($codigo, 0, max(1, $maximo));
    }
}
