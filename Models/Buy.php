<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';

class Buy
{
    private string $tableName = 'ingreso';
    private string $tableNameDetalle = 'detalle_ingreso';
    private string $tableNameKardex = 'kardex';
    private Conexion $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
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

            $sqlIngreso = "INSERT INTO {$this->tableName}
                (idproveedor, idusuario, idsucursal, tipo_comprobante,
                 serie_comprobante, num_comprobante, fecha_hora, impuesto,
                 total_compra, tipo_compra, observacion, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aceptado')";

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

            $this->conexion->commit();
            $transaccionIniciada = false;

            return [
                'success' => true,
                'idingreso' => $idingreso,
                'tipo_compra' => $tipoCompra,
                'total_compra' => $totalCompra
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

    public function anular(int $idingreso): array
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
                        serie_comprobante, num_comprobante
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

            $detalles = $this->conexion->getDataAll(
                "SELECT iddetalle_ingreso, tipo_detalle, idarticulo,
                        cantidad, stock_venta, afecta_stock, estado
                 FROM {$this->tableNameDetalle}
                 WHERE idingreso = ?
                 FOR UPDATE",
                [$idingreso]
            );

            $cantidadesPorArticulo = [];

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

            $this->conexion->setData(
                "UPDATE {$this->tableName}
                 SET estado = 'Anulado'
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
                    i.tipo_compra,
                    i.estado
                FROM {$this->tableName} i
                INNER JOIN persona p
                    ON i.idproveedor = p.idpersona
                LEFT JOIN usuario u
                    ON i.idusuario = u.idusuario
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

        return $this->conexion->getDataAll($sql);
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
            )
        ];
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
            'idarticulo' => (int)($detalle['idarticulo'] ?? 0),
            'descripcion' => $this->limpiarTexto($detalle['descripcion'] ?? '', 250),
            'idcategoria_compra' => (int)($detalle['idcategoria_compra'] ?? 0),
            'idcategoria' => (int)($detalle['idcategoria'] ?? 0),
            'idsubcategoria' => (int)($detalle['idsubcategoria'] ?? 0),
            'idmedida' => (int)($detalle['idmedida'] ?? 0),
            'idalmacen' => (int)($detalle['idalmacen'] ?? 0),
            'codigo' => $this->limpiarCodigo($detalle['codigo'] ?? ''),
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
        $articulo = null;

        if ($detalle['origen'] === 'NUEVO') {
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

        $cantidad = (int)round((float)$detalle['cantidad']);
        $precioCompra = (float)$detalle['precio_compra'];
        $precioVenta = $detalle['precio_venta'];
        $idalmacen = (int)($articulo['idalmacen'] ?? 0);
        $idmedida = (int)($articulo['idmedida'] ?? 0);
        $descripcion = $detalle['descripcion'] !== ''
            ? $detalle['descripcion']
            : (string)$articulo['nombre'];

        $sqlDetalle = "INSERT INTO {$this->tableNameDetalle}
            (idingreso, tipo_detalle, idarticulo, descripcion,
             idcategoria_compra, idalmacen, idmedida, afecta_stock,
             cantidad, stock_venta, precio_compra, precio_venta,
             importe, estado, stock_estado)
            VALUES (?, 'INVENTARIO', ?, ?, NULL, ?, ?, 1,
                    ?, ?, ?, ?, ?, 1, 1)";

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
                (float)$detalle['importe']
            ]
        );

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
                $detalleDocumento,
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

        if ($idcategoria <= 0 || $idmedida <= 0 || $idalmacen <= 0) {
            throw new RuntimeException(
                'El producto nuevo requiere categoría, unidad y almacén.'
            );
        }

        $categoria = $this->conexion->getData(
            "SELECT idcategoria
             FROM categoria
             WHERE idcategoria = ?
               AND condicion = 1
             LIMIT 1",
            [$idcategoria]
        );

        if (!$categoria) {
            throw new RuntimeException('La categoría del producto nuevo no es válida.');
        }

        if ($idsubcategoria > 0) {
            $subcategoria = $this->conexion->getData(
                "SELECT idsubcategoria
                 FROM subcategoria
                 WHERE idsubcategoria = ?
                   AND idcategoria = ?
                   AND estado = 1
                 LIMIT 1",
                [$idsubcategoria, $idcategoria]
            );

            if (!$subcategoria) {
                throw new RuntimeException(
                    'La subcategoría no pertenece a la categoría seleccionada.'
                );
            }
        }

        $medida = $this->conexion->getData(
            "SELECT idmedida
             FROM medida
             WHERE idmedida = ?
               AND condicion = 1
             LIMIT 1",
            [$idmedida]
        );

        if (!$medida) {
            throw new RuntimeException('La unidad del producto nuevo no es válida.');
        }

        $almacen = $this->conexion->getData(
            "SELECT idalmacen
             FROM almacen
             WHERE idalmacen = ?
               AND estado = 1
             LIMIT 1",
            [$idalmacen]
        );

        if (!$almacen) {
            throw new RuntimeException('El almacén del producto nuevo no es válido.');
        }

        if ($codigo === '') {
            $codigo = $this->generarCodigoProducto();
        }

        $duplicadoCodigo = $this->conexion->getData(
            "SELECT idarticulo, nombre
             FROM articulo
             WHERE codigo = ?
             LIMIT 1
             FOR UPDATE",
            [$codigo]
        );

        if ($duplicadoCodigo) {
            throw new RuntimeException(
                'Ya existe un producto con el código ' . $codigo . '.'
            );
        }

        $sql = "INSERT INTO articulo
            (idcategoria, idsubcategoria, idmedida, codigo, nombre,
             stock, precio_compra, precio_venta, descripcion, imagen,
             condicion, idalmacen)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, 'default.png', 1, ?)";

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
                $idalmacen
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

    private function limpiarCodigo(mixed $valor): string
    {
        $codigo = strtoupper(trim((string)$valor));
        $codigo = preg_replace('/[^A-Z0-9._\-]/', '', $codigo) ?? '';

        return substr($codigo, 0, 50);
    }
}
