<?php
//incluir la conexion de base de datos
require_once __DIR__ . '/../Config/Conexion.php';
class Product
{

	private $tableName = 'articulo';
	private $conexion;

	//implementamos nuestro constructor
	public function __construct()
	{
		$this->conexion = new Conexion();
	}

	//metodo insertar regiustro

	public function insertar(
		$idcategoria,
		$idsubcategoria,
		$idmedida,
		$idalmacen,
		$codigo,
		$nombre,
		$stock,
		$precio_compra,
		$precio_venta,
		$descripcion,
		$imagen,
		$codigo_afectacion_igv = '10',
		$porcentaje_igv = 18.00,
		$unidad_medida_sunat = 'NIU',
		$codigo_producto_sunat = null,
        $controla_lotes = false, $controla_vencimiento = false, $dias_alerta_vencimiento = 30,
        $numero_lote_inicial = null, $fecha_vencimiento_inicial = null
	)
	{
        $pdo = Conexion::conectar();
        $transaccionPropia = !$pdo->inTransaction();
		try {
            // Las fechas se modifican exclusivamente en Inventario > Lotes.
            // Para impedir cambios encubiertos desde Productos, un producto nuevo
            // con control de lotes se crea sin stock y se recibe desde Compras.
            if ($stock > 0 && ($controla_lotes || $controla_vencimiento)) {
                throw new RuntimeException('Crea el producto con stock inicial cero. Registra sus lotes al recibir mercadería en Compras. Las correcciones de vencimiento se realizan solo en Lotes y vencimientos.');
            }
            if ($numero_lote_inicial !== null || $fecha_vencimiento_inicial !== null) {
                throw new RuntimeException('Los números de lote y vencimientos no se editan desde Productos.');
            }
            if ($transaccionPropia) $pdo->beginTransaction();
			// Insertar el producto y obtener su ID
			$sql = "INSERT INTO $this->tableName 
			(idcategoria, idsubcategoria, idmedida, idalmacen, codigo, nombre, stock, precio_compra, precio_venta, descripcion, imagen, codigo_afectacion_igv, porcentaje_igv, unidad_medida_sunat, codigo_producto_sunat, condicion, controla_lotes, controla_vencimiento, dias_alerta_vencimiento)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)";
			$arrData = array(
				$idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo,
				$nombre, $stock, $precio_compra, $precio_venta, $descripcion, $imagen,
				$codigo_afectacion_igv, $porcentaje_igv, $unidad_medida_sunat,
                $codigo_producto_sunat, (int)$controla_lotes, (int)$controla_vencimiento, (int)$dias_alerta_vencimiento
			);
			$idarticulo = $this->conexion->setDataReturnId($sql, $arrData);
			// Si hay stock inicial, registrar en ingreso, detalle_ingreso y kardex.
			// El costo de compra puede ser 0.00.
			if ($stock > 0) {
				$idusuario = $_SESSION['idusuario'] ?? 1;
				$idproveedor = 1; // Proveedor genérico para stock inicial
				$num = str_pad(rand(1, 9999999), 7, '0', STR_PAD_LEFT);
				$total_compra = $precio_compra * $stock;

				// Insertar ingreso
				$sqlIngreso = "INSERT INTO ingreso 
				(idproveedor, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, impuesto, total_compra, estado) 
				VALUES (?, ?, 'Stock Inicial', 'INI', ?, NOW(), 0, ?, 'Aceptado')";
				$idIngreso = $this->conexion->setDataReturnId($sqlIngreso, [$idproveedor, $idusuario, $num, $total_compra]);

				// Insertar detalle_ingreso
                $sqlDetalle = "INSERT INTO detalle_ingreso
                    (idarticulo, idingreso, idalmacen, idmedida, cantidad, stock_venta, precio_compra, precio_venta, estado, stock_estado, numero_lote, fecha_vencimiento)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?)";
                $arrDetalle = [$idarticulo, $idIngreso, $idalmacen, $idmedida, $stock, $stock, $precio_compra, $precio_venta,
                    null, null];
				$this->conexion->setData($sqlDetalle, $arrDetalle);

				// ✅ Insertar en kardex
				$detalle = 'Stock Inicial INI-' . $num;
				$precioUnitario = $precio_compra;
				$total = $stock * $precioUnitario;

				$sqlKardex = "INSERT INTO kardex 
				(iddetalle, idarticulo, fecha, detalle,
				 cantidadi, costoui, totali,
				 cantidads, costous, totals,
				 cantidadex, costouex, totalex, tipo, estado) 
				VALUES (?, ?, NOW(), ?, ?, ?, ?, 0, 0, 0, ?, ?, ?, 'Ingreso', 'Activo')";
				$arrKardex = [
					$idIngreso,
					$idarticulo,
					$detalle,
					$stock,
					$precioUnitario,
					$total,
					$stock,
					$precioUnitario,
					$total
				];
				$this->conexion->setData($sqlKardex, $arrKardex);
			}

            if ($transaccionPropia) $pdo->commit();
            return $idarticulo;
        } catch (Throwable $e) {
            if ($transaccionPropia && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }



	public function editar(
		$idarticulo,
		$idcategoria,
		$idsubcategoria,
		$idmedida,
		$idalmacen,
		$codigo,
		$nombre,
		$stock,
		$precio_compra,
		$precio_venta,
		$descripcion,
		$imagen,
		$codigo_afectacion_igv = '10',
		$porcentaje_igv = 18.00,
		$unidad_medida_sunat = 'NIU',
		$codigo_producto_sunat = null
	)
	{
		$estadoLote = $this->conexion->getData(
            'SELECT stock, controla_lotes, controla_vencimiento FROM articulo WHERE idarticulo=?',
            [$idarticulo]
        );
        if ($estadoLote && ((int)$estadoLote['controla_lotes'] || (int)$estadoLote['controla_vencimiento'])
            && (int)$stock !== (int)$estadoLote['stock']) {
            throw new RuntimeException('El stock de un producto con lotes solo se modifica mediante compras, ventas o bajas trazables.');
        }
		$sql = "UPDATE $this->tableName 
        SET idcategoria=?, idsubcategoria=?, idmedida=?, idalmacen=?, codigo=?, nombre=?, stock=?, precio_compra=?, precio_venta=?, descripcion=?, imagen=?, codigo_afectacion_igv=?, porcentaje_igv=?, unidad_medida_sunat=?, codigo_producto_sunat=?
        WHERE idarticulo=?";
		$arrData = array(
			$idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo,
			$nombre, $stock, $precio_compra, $precio_venta, $descripcion, $imagen,
			$codigo_afectacion_igv, $porcentaje_igv, $unidad_medida_sunat,
			$codigo_producto_sunat, $idarticulo
		);
		return $this->conexion->setData($sql, $arrData);
	}




	/**
	 * Edita un producto variable y sus variantes activas en una sola transacción.
	 * Las variantes eliminadas desde la interfaz se desactivan para conservar
	 * la trazabilidad histórica de ventas.
	 */
	public function editarConVariaciones(array $producto, array $variaciones)
	{
		$transaccionIniciada = false;

		try {
			$idarticulo = (int)($producto['idarticulo'] ?? 0);
			if ($idarticulo <= 0) {
				throw new InvalidArgumentException('Producto no válido para edición.');
			}
			if (!$variaciones) {
				throw new InvalidArgumentException('El producto variable debe conservar al menos una variante.');
			}

			$this->conexion->beginTransaction();
			$transaccionIniciada = true;

			$actuales = $this->conexion->getDataAll(
				"SELECT idvariacion
				 FROM articulo_variacion
				 WHERE idarticulo = ? AND estado = 1",
				[$idarticulo]
			);
			$idsActuales = [];
			foreach (is_array($actuales) ? $actuales : [] as $actual) {
				$idActual = (int)($actual['idvariacion'] ?? 0);
				if ($idActual > 0) {
					$idsActuales[$idActual] = true;
				}
			}

            $controlLotes = $this->conexion->getData(
                'SELECT controla_lotes, controla_vencimiento FROM articulo WHERE idarticulo=? FOR UPDATE',
                [$idarticulo]
            );
            $rastreado = (int)($controlLotes['controla_lotes'] ?? 0) === 1
                || (int)($controlLotes['controla_vencimiento'] ?? 0) === 1;
            if ($rastreado) {
                $saldos = $this->conexion->getDataAll(
                    'SELECT idvariacion, stock FROM articulo_variacion WHERE idarticulo=? FOR UPDATE',
                    [$idarticulo]
                );
                $saldosMap = [];
                foreach ($saldos as $saldo) $saldosMap[(int)$saldo['idvariacion']] = (int)$saldo['stock'];
                foreach ($variaciones as $fila) {
                    $id = (int)($fila['idvariacion'] ?? 0);
                    $esperado = $id > 0 ? ($saldosMap[$id] ?? null) : 0;
                    if ($esperado === null || (int)($fila['stock'] ?? 0) !== $esperado) {
                        throw new RuntimeException('No se puede cambiar manualmente el stock de una variante con lotes. Registra la entrada por Compras.');
                    }
                }
                $idsEnFormulario = array_filter(array_map('intval', array_column($variaciones, 'idvariacion')));
                foreach ($saldosMap as $id => $disponible) {
                    if ($disponible > 0 && !in_array($id, $idsEnFormulario, true)) {
                        throw new RuntimeException('No se puede quitar una variante con stock identificado.');
                    }
                }
            }

			$idsRecibidos = [];
			$sqlActualizarVariacion = "UPDATE articulo_variacion
				SET combinacion = ?, sku = ?, stock = ?, precio_compra = ?, precio_venta = ?, precio_preferencial = ?, estado = 1
				WHERE idvariacion = ? AND idarticulo = ?";
			$sqlInsertarVariacion = "INSERT INTO articulo_variacion
                (idarticulo, combinacion, sku, stock, precio_compra, precio_venta, precio_preferencial, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";

			foreach ($variaciones as $variacion) {
				$idvariacion = (int)($variacion['idvariacion'] ?? 0);
				$combinacion = trim((string)($variacion['combinacion'] ?? ''));
				$sku = trim((string)($variacion['sku'] ?? ''));
				$stock = max(0, (int)($variacion['stock'] ?? 0));
				$precioCompra = max(0, (float)($variacion['precio_compra'] ?? 0));
				$precioVenta = max(0, (float)($variacion['precio_venta'] ?? 0));
                $precioPref = isset($variacion['precio_preferencial']) ? (float)$variacion['precio_preferencial'] : null;

				if ($idvariacion > 0) {
					$propietario = $this->conexion->getData(
						"SELECT idvariacion FROM articulo_variacion WHERE idvariacion = ? AND idarticulo = ? LIMIT 1",
						[$idvariacion, $idarticulo]
					);
					if (empty($propietario)) {
						throw new RuntimeException('Una variante no pertenece al producto que se está editando.');
					}

					$this->conexion->setData($sqlActualizarVariacion, [
						$combinacion,
						$sku,
						$stock,
						$precioCompra,
						$precioVenta,
                        $precioPref,
						$idvariacion,
						$idarticulo
					]);
					$idsRecibidos[$idvariacion] = true;
				} else {
					$idNuevo = (int)$this->conexion->setDataReturnId($sqlInsertarVariacion, [
						$idarticulo,
						$combinacion,
						$sku,
						$stock,
						$precioCompra,
						$precioVenta,
                        $precioPref
					]);
					if ($idNuevo <= 0) {
						throw new RuntimeException('No se pudo registrar una nueva variante.');
					}
					$idsRecibidos[$idNuevo] = true;
				}
			}

			$idsDesactivar = array_values(array_diff(array_keys($idsActuales), array_keys($idsRecibidos)));
			foreach ($idsDesactivar as $idDesactivar) {
				$this->conexion->setData(
					"UPDATE articulo_variacion SET estado = 0 WHERE idvariacion = ? AND idarticulo = ?",
					[(int)$idDesactivar, $idarticulo]
				);
			}

			$resumen = $this->conexion->getData(
				"SELECT
					COALESCE(SUM(stock), 0) AS stock_total,
					COALESCE(MIN(NULLIF(precio_compra, 0)), 0) AS precio_compra_min,
					COALESCE(MIN(NULLIF(precio_venta, 0)), 0) AS precio_venta_min,
					COUNT(*) AS cantidad_variaciones
				 FROM articulo_variacion
				 WHERE idarticulo = ? AND estado = 1",
				[$idarticulo]
			);

			if ((int)($resumen['cantidad_variaciones'] ?? 0) <= 0) {
				throw new RuntimeException('El producto variable debe conservar al menos una variante activa.');
			}

			$sqlProducto = "UPDATE {$this->tableName}
				SET idcategoria = ?,
					idsubcategoria = ?,
					idmedida = ?,
					idalmacen = ?,
					codigo = ?,
					nombre = ?,
					stock = ?,
					precio_compra = ?,
					precio_venta = ?,
					descripcion = ?,
					imagen = ?,
					codigo_afectacion_igv = ?,
					porcentaje_igv = ?,
					unidad_medida_sunat = ?,
					codigo_producto_sunat = ?
				WHERE idarticulo = ?";

			$this->conexion->setData($sqlProducto, [
				(int)($producto['idcategoria'] ?? 0),
				!empty($producto['idsubcategoria']) ? (int)$producto['idsubcategoria'] : null,
				(int)($producto['idmedida'] ?? 0),
				(int)($producto['idalmacen'] ?? 0),
				(isset($producto['codigo']) && trim((string)$producto['codigo']) !== '') ? trim((string)$producto['codigo']) : null,
				trim((string)($producto['nombre'] ?? '')),
				(int)($resumen['stock_total'] ?? 0),
				(float)($resumen['precio_compra_min'] ?? 0),
				(float)($resumen['precio_venta_min'] ?? 0),
				trim((string)($producto['descripcion'] ?? '')),
				trim((string)($producto['imagen'] ?? 'default.png')) ?: 'default.png',
				trim((string)($producto['codigo_afectacion_igv'] ?? '10')),
				max(0, (float)($producto['porcentaje_igv'] ?? 0)),
				strtoupper(trim((string)($producto['unidad_medida_sunat'] ?? 'NIU'))),
				isset($producto['codigo_producto_sunat']) && trim((string)$producto['codigo_producto_sunat']) !== ''
					? trim((string)$producto['codigo_producto_sunat'])
					: null,
				$idarticulo
			]);

			$this->conexion->commit();
			$transaccionIniciada = false;
			return true;
		} catch (Throwable $error) {
			if ($transaccionIniciada) {
				try {
					$this->conexion->rollBack();
				} catch (Throwable $rollbackError) {
					error_log('[ROLLBACK EDICION PRODUCTO VARIABLE] ' . $rollbackError->getMessage());
				}
			}

			error_log('[EDICION PRODUCTO VARIABLE] ' . $error->getMessage());
			throw $error;
		}
	}

	public function desactivar($idarticulo)
	{
		$sql = "UPDATE $this->tableName SET condicion='0' WHERE idarticulo=?";
		$arrData = array($idarticulo);
		return $this->conexion->setData($sql, $arrData);
	}

	public function activar($idarticulo)
	{
		$sql = "UPDATE $this->tableName SET condicion='1' WHERE idarticulo=?";
		$arrData = array($idarticulo);
		return $this->conexion->setData($sql, $arrData);
	}

    /**
     * Valida antes de editar: se permite convertir inventario existente solo
     * si todas las existencias están identificadas y coinciden con los saldos.
     * Nunca inventa lotes ni fuerza una conversión silenciosa.
     */
    public function validarConfiguracionLotes(int $idarticulo, bool $lotes, bool $vence, int $dias): void
    {
        if ($vence) $lotes = true;
        if ($dias < 1 || $dias > 3650) {
            throw new RuntimeException('Días de alerta inválidos (1-3650).');
        }
        $actual = $this->conexion->getData(
            'SELECT stock, controla_lotes, controla_vencimiento FROM articulo WHERE idarticulo=?',
            [$idarticulo]
        );
        if (!$actual) throw new RuntimeException('Producto inexistente.');
        $yaControla = (int)$actual['controla_lotes'] === 1 || (int)$actual['controla_vencimiento'] === 1;
        if ($lotes && !$yaControla) {
            $variantes = $this->conexion->getDataAll('SELECT idvariacion,stock FROM articulo_variacion WHERE idarticulo=? AND estado=1', [$idarticulo]);
            $capas = $this->conexion->getDataAll("SELECT idvariacion,numero_lote,fecha_vencimiento,stock_venta
                FROM detalle_ingreso WHERE idarticulo=? AND tipo_detalle='INVENTARIO' AND afecta_stock=1 AND estado=1 AND stock_venta>0", [$idarticulo]);
            $totalIdentificado = 0;
            $saldosPorVariacion = [];
            foreach ($capas as $capa) {
                if (trim((string)$capa['numero_lote']) === '' || ($vence && empty($capa['fecha_vencimiento']))) {
                    throw new RuntimeException('Existen ingresos con stock sin lote o vencimiento. Identifícalos en Inventario → Lotes → Por identificar antes de activar.');
                }
                $cantidad = (int)$capa['stock_venta'];
                $totalIdentificado += $cantidad;
                $idV = (int)($capa['idvariacion'] ?? 0);
                $saldosPorVariacion[$idV] = ($saldosPorVariacion[$idV] ?? 0) + $cantidad;
            }
            if ($variantes) {
                $totalVariantes = 0;
                foreach ($variantes as $v) {
                    $totalVariantes += (int)$v['stock'];
                    if (($saldosPorVariacion[(int)$v['idvariacion']] ?? 0) !== (int)$v['stock']) {
                        throw new RuntimeException('El saldo de una variante no coincide con sus lotes. Debes conciliarlo antes de activar.');
                    }
                }
                if (($saldosPorVariacion[0] ?? 0) !== 0 || $totalIdentificado !== $totalVariantes
                    || ((int)$actual['stock'] !== 0 && (int)$actual['stock'] !== $totalVariantes)) {
                    throw new RuntimeException('El saldo del producto y sus variantes no coincide con el inventario por lotes.');
                }
            } elseif ($totalIdentificado !== (int)$actual['stock']) {
                throw new RuntimeException('El stock físico no coincide con el total de lotes identificados. Debes conciliar las existencias antes de activar.');
            }
        }
        if ($vence && (int)$actual['controla_vencimiento'] !== 1) {
            $sinFecha = (int)$this->conexion->getValue(
                "SELECT COUNT(*) FROM detalle_ingreso WHERE idarticulo=? AND estado=1 AND afecta_stock=1 AND stock_venta>0 AND numero_lote IS NOT NULL AND numero_lote<>'' AND fecha_vencimiento IS NULL",
                [$idarticulo]
            );
            if ($sinFecha > 0) {
                throw new RuntimeException('No se puede activar vencimiento: hay lotes con saldo y sin fecha registrada. Concílialos primero.');
            }
        }
        if (!$lotes && $yaControla) {
            $saldo = (float)$this->conexion->getValue(
                "SELECT COALESCE(SUM(stock_venta),0) FROM detalle_ingreso WHERE idarticulo=? AND estado=1 AND numero_lote IS NOT NULL AND numero_lote<>''",
                [$idarticulo]
            );
            if ($saldo > 0) throw new RuntimeException('No se puede desactivar lotes con existencias identificadas.');
        }
    }

    public function guardarConfiguracionLotes(int $idarticulo, bool $lotes, bool $vence, int $dias): void
    {
        $this->validarConfiguracionLotes($idarticulo, $lotes, $vence, $dias);
        $this->conexion->setData(
            'UPDATE articulo SET controla_lotes=?, controla_vencimiento=?, dias_alerta_vencimiento=? WHERE idarticulo=?',
            [(int)($lotes || $vence), (int)$vence, $dias, $idarticulo]
        );
    }

    public function guardarPrecioPreferencial(int $idarticulo, ?float $precio): void
    {
        if ($precio !== null && ($precio <= 0 || !is_finite($precio))) throw new InvalidArgumentException('Precio preferencial inválido.');
        $this->conexion->setData('UPDATE articulo SET precio_preferencial=? WHERE idarticulo=?', [$precio, $idarticulo]);
    }

	//metodo para mostrar registros
	public function mostrar(string $idarticulo)
	{
		$sql = "SELECT
					a.*,
					c.nombre AS categoria,
					s.nombre AS subcategoria,
					m.nombre AS medida,
					al.nombre AS almacen,
					al.nombre AS almacen_nombre,
					COALESCE(v.cantidad_variaciones, 0) AS cantidad_variaciones,
					CASE
						WHEN COALESCE(v.cantidad_variaciones, 0) > 0
						THEN COALESCE(v.stock_variaciones, 0)
						ELSE a.stock
					END AS stock_total,
					CASE
						WHEN COALESCE(v.cantidad_variaciones, 0) > 0
						THEN COALESCE(v.precio_venta_min, a.precio_venta)
						ELSE a.precio_venta
					END AS precio_venta_min,
					CASE
						WHEN COALESCE(v.cantidad_variaciones, 0) > 0
						THEN COALESCE(v.precio_venta_max, a.precio_venta)
						ELSE a.precio_venta
					END AS precio_venta_max,
					CASE WHEN COALESCE(v.cantidad_variaciones, 0) > 0 THEN 1 ELSE 0 END AS tiene_variaciones
				FROM articulo a
				LEFT JOIN categoria c ON c.idcategoria = a.idcategoria
				LEFT JOIN subcategoria s ON s.idsubcategoria = a.idsubcategoria
				LEFT JOIN medida m ON m.idmedida = a.idmedida
				LEFT JOIN almacen al ON al.idalmacen = a.idalmacen
				LEFT JOIN (
					SELECT
						idarticulo,
						COUNT(*) AS cantidad_variaciones,
						SUM(stock) AS stock_variaciones,
						MIN(NULLIF(precio_venta, 0)) AS precio_venta_min,
						MAX(NULLIF(precio_venta, 0)) AS precio_venta_max
					FROM articulo_variacion
					WHERE estado = 1
					GROUP BY idarticulo
				) v ON v.idarticulo = a.idarticulo
				WHERE a.idarticulo = ?
				LIMIT 1";

		return $this->conexion->getData($sql, [$idarticulo]);
	}


    /**
     * Resumen de lotes de un producto para su ficha de lectura.
     * Una fecha corresponde a la combinación producto/variante/lote, nunca al artículo.
     * No permite modificaciones; mantiene existencias vencidas visibles para auditoría.
     */
    public function detalleLotesProducto(int $idarticulo): array
    {
        if ($idarticulo <= 0) throw new InvalidArgumentException('Producto inválido.');
        $config = $this->conexion->getData(
            'SELECT controla_lotes, controla_vencimiento, dias_alerta_vencimiento, stock FROM articulo WHERE idarticulo=? LIMIT 1',
            [$idarticulo]
        );
        if (!$config) throw new RuntimeException('El producto solicitado no existe.');
        $controlLotes = (int)$config['controla_lotes'] === 1 || (int)$config['controla_vencimiento'] === 1;
        $respuesta = [
            'controla_lotes' => $controlLotes,
            'controla_vencimiento' => (int)$config['controla_vencimiento'] === 1,
            'dias_alerta' => (int)$config['dias_alerta_vencimiento'],
            'stock_fisico' => (int)$config['stock'],
            'stock_vendible' => 0,
            'stock_vencido' => 0,
            'stock_sin_identificar' => 0,
            'stock_sin_fecha' => 0,
            'stock_registrado_lotes' => 0,
            'proximo_vencimiento' => null,
            'total_grupos' => 0,
            'limite_mostrado' => 60,
            'lotes' => []
        ];
        if (!$controlLotes) return $respuesta;
        $resumen = $this->conexion->getData(
            "SELECT
                COALESCE(SUM(di.stock_venta),0) AS stock_registrado_lotes,
                MIN(CASE WHEN di.fecha_vencimiento>=CURDATE() AND di.numero_lote IS NOT NULL AND TRIM(di.numero_lote)<>'' THEN di.fecha_vencimiento ELSE NULL END) AS proximo_vencimiento,
                COALESCE(SUM(CASE WHEN di.numero_lote IS NULL OR TRIM(di.numero_lote)='' THEN di.stock_venta ELSE 0 END),0) AS stock_sin_identificar,
                COALESCE(SUM(CASE WHEN di.numero_lote IS NOT NULL AND TRIM(di.numero_lote)<>'' AND di.fecha_vencimiento IS NULL THEN di.stock_venta ELSE 0 END),0) AS stock_sin_fecha,
                COALESCE(SUM(CASE WHEN di.fecha_vencimiento<CURDATE() THEN di.stock_venta ELSE 0 END),0) AS stock_vencido,
                COALESCE(SUM(CASE WHEN di.numero_lote IS NOT NULL AND TRIM(di.numero_lote)<>''
                  AND (?=0 OR (di.fecha_vencimiento IS NOT NULL AND di.fecha_vencimiento>=CURDATE()))
                  THEN di.stock_venta ELSE 0 END),0) AS stock_vendible
             FROM detalle_ingreso di
             WHERE di.idarticulo=? AND di.tipo_detalle='INVENTARIO' AND di.afecta_stock=1
               AND di.estado=1 AND di.stock_venta>0",
            [(int)$respuesta['controla_vencimiento'], $idarticulo]
        );
        $respuesta['proximo_vencimiento'] = $resumen['proximo_vencimiento'] ?? null;
        foreach (['stock_registrado_lotes','stock_sin_identificar','stock_sin_fecha','stock_vencido','stock_vendible'] as $key) {
            $respuesta[$key] = (int)($resumen[$key] ?? 0);
        }
        $sql = "SELECT di.idvariacion, COALESCE(av.combinacion,'Producto simple') AS presentacion,
                COALESCE(av.sku,'') AS sku_variacion,
                COALESCE(di.idalmacen,a.idalmacen) AS idalmacen,
                COALESCE(al.nombre,'Sin almacén') AS almacen,
                COALESCE(NULLIF(TRIM(di.numero_lote),''),'') AS numero_lote,
                di.fecha_vencimiento,
                SUM(di.stock_venta) AS cantidad,
                DATEDIFF(di.fecha_vencimiento,CURDATE()) AS dias_restantes
            FROM detalle_ingreso di
            JOIN articulo a ON a.idarticulo=di.idarticulo
            LEFT JOIN articulo_variacion av ON av.idvariacion=di.idvariacion
            LEFT JOIN almacen al ON al.idalmacen=COALESCE(di.idalmacen,a.idalmacen)
            WHERE di.idarticulo=? AND di.tipo_detalle='INVENTARIO'
              AND di.afecta_stock=1 AND di.estado=1 AND di.stock_venta>0
            GROUP BY di.idvariacion,av.combinacion,av.sku,
                COALESCE(di.idalmacen,a.idalmacen),al.nombre,
                COALESCE(NULLIF(TRIM(di.numero_lote),''),''),di.fecha_vencimiento
            ORDER BY CASE WHEN di.fecha_vencimiento IS NULL THEN 1 ELSE 0 END,
                di.fecha_vencimiento ASC,numero_lote ASC
            LIMIT 61";
        $grupos = $this->conexion->getDataAll($sql, [$idarticulo]);
        $grupos = is_array($grupos) ? $grupos : [];
        $respuesta['total_grupos'] = count($grupos);
        $respuesta['hay_mas'] = count($grupos)>60;
        $respuesta['lotes'] = array_slice($grupos,0,60);
        return $respuesta;
    }

	public function verificarCodigo(string $codigo)
	{
		$sql = "SELECT * FROM $this->tableName WHERE codigo=?";
		$arrData = array($codigo);
		return $this->conexion->getData($sql, $arrData);
	}

	//listar registros
	public function listar()
	{
		$sql = "
			SELECT 
				a.idarticulo,
				a.codigo,
				a.nombre,
				c.nombre AS categoria,
				s.nombre AS subcategoria,
				m.nombre AS medida,
				al.nombre AS almacen,
				a.stock,
				a.precio_compra,
				a.precio_venta,
				a.codigo_afectacion_igv,
				a.porcentaje_igv,
				a.unidad_medida_sunat,
				a.codigo_producto_sunat,
				a.descripcion,
				a.imagen,
				a.condicion
			FROM articulo a
			INNER JOIN categoria c ON a.idcategoria = c.idcategoria
			LEFT JOIN subcategoria s ON a.idsubcategoria = s.idsubcategoria
			LEFT JOIN medida m ON a.idmedida = m.idmedida
			LEFT JOIN almacen al ON a.idalmacen = al.idalmacen
	
			UNION
	
			SELECT 
				av.idarticulo_variacion AS idarticulo,
				av.sku AS codigo,
				CONCAT(a.nombre, ' - ', av.combinacion) AS nombre,
				c.nombre AS categoria,
				s.nombre AS subcategoria,
				m.nombre AS medida,
				al.nombre AS almacen,
				av.stock,
				av.precio_compra,
				av.precio_venta,
				a.codigo_afectacion_igv,
				a.porcentaje_igv,
				a.unidad_medida_sunat,
				a.codigo_producto_sunat,
				a.descripcion,
				a.imagen,
				a.condicion
			FROM articulo_variacion av
			INNER JOIN articulo a ON av.idarticulo = a.idarticulo
			INNER JOIN categoria c ON a.idcategoria = c.idcategoria
			LEFT JOIN subcategoria s ON a.idsubcategoria = s.idsubcategoria
			LEFT JOIN medida m ON a.idmedida = m.idmedida
			LEFT JOIN almacen al ON a.idalmacen = al.idalmacen
			WHERE av.estado = 1
		";

		return $this->conexion->getData($sql);
	}

	public function cantidadarticulos()
	{
		$sql = "SELECT COUNT(*) totalar FROM $this->tableName WHERE condicion=? AND stock>?";
		$arrData = array(1, 0);
		return $this->conexion->getData($sql, $arrData);
	}
	//listar y mostrar en Select
	public function select()
	{
		$sql = "SELECT * FROM $this->tableName WHERE condicion=1";
		return $this->conexion->getDataAll($sql);
	}

	public function listarCategoriasActivas()
	{
		$sql = "SELECT idcategoria, nombre FROM categoria WHERE condicion=1";
		return $this->conexion->getDataAll($sql);
	}

	public function listarActivosVentaPorCategoria($idcategoria)
    {
        // El catálogo anterior exponía el stock físico, incluso si el único
        // lote estaba vencido. El stock vendible es calculado por recepción.
        $rows = $this->conexion->getDataAll(
            "SELECT a.*,
              CASE WHEN a.controla_lotes=1 OR a.controla_vencimiento=1 THEN
                (SELECT COALESCE(SUM(di.stock_venta),0) FROM detalle_ingreso di
                 WHERE di.idarticulo=a.idarticulo AND di.estado=1 AND di.afecta_stock=1
                   AND di.tipo_detalle='INVENTARIO' AND di.stock_venta>0
                   AND di.numero_lote IS NOT NULL AND di.numero_lote<>''
                   AND (di.fecha_vencimiento IS NULL OR di.fecha_vencimiento>=CURDATE())
                   AND (a.controla_vencimiento=0 OR di.fecha_vencimiento IS NOT NULL))
               ELSE a.stock END AS stock_vendible
             FROM articulo a WHERE a.idcategoria=? AND a.condicion=1",
            [$idcategoria]
        );
        foreach ($rows as &$row) {
            $row['stock'] = (int)$row['stock_vendible'];
            unset($row['stock_vendible']);
        }
        unset($row);
        return $rows;
    }

	/**
	 * Catálogo optimizado para el POS independiente.
	 * Devuelve en una sola consulta los datos visuales, tributarios y de stock
	 * necesarios para renderizar el catálogo sin realizar una petición por categoría.
	 */
	public function listarCatalogoPos()
	{
		$sql = "SELECT
				a.idarticulo,
				a.idcategoria,
				a.idsubcategoria,
				a.idmedida,
				a.idalmacen,
				a.codigo,
				a.nombre,
				a.descripcion,
				a.imagen,
				CASE
					WHEN COALESCE(v.cantidad_variaciones, 0) > 0
					THEN COALESCE(v.precio_compra_min, a.precio_compra)
					ELSE a.precio_compra
				END AS precio_compra,
				CASE
					WHEN COALESCE(v.cantidad_variaciones, 0) > 0
					THEN COALESCE(v.precio_venta_min, a.precio_venta)
					ELSE a.precio_venta
				END AS precio_venta,
                a.precio_preferencial,
                a.controla_lotes, a.controla_vencimiento, a.dias_alerta_vencimiento,
                CASE
                    WHEN a.controla_lotes=1 OR a.controla_vencimiento=1
                    THEN (SELECT COALESCE(SUM(di.stock_venta),0)
                          FROM detalle_ingreso di WHERE di.idarticulo=a.idarticulo
                            AND di.tipo_detalle='INVENTARIO' AND di.afecta_stock=1
                            AND di.estado=1 AND di.stock_venta>0
                            AND di.numero_lote IS NOT NULL AND di.numero_lote<>''
                            AND (di.fecha_vencimiento IS NULL OR di.fecha_vencimiento>=CURDATE())
                            AND (a.controla_vencimiento=0 OR di.fecha_vencimiento IS NOT NULL))
                    WHEN COALESCE(v.cantidad_variaciones, 0)>0 THEN COALESCE(v.stock_variaciones,0)
                    ELSE COALESCE(a.stock,0)
                END AS stock,
				COALESCE(v.cantidad_variaciones, 0) AS cantidad_variaciones,
				CASE WHEN COALESCE(v.cantidad_variaciones, 0) > 0 THEN 1 ELSE 0 END AS tiene_variaciones,
				COALESCE(v.precio_venta_max, a.precio_venta) AS precio_venta_max,
				a.codigo_afectacion_igv,
				a.porcentaje_igv,
				a.unidad_medida_sunat,
				a.codigo_producto_sunat,
				c.nombre AS categoria,
				s.nombre AS subcategoria,
				m.nombre AS medida,
				al.nombre AS almacen,
				(
					SELECT di.iddetalle_ingreso
					FROM detalle_ingreso di
                    WHERE di.idarticulo = a.idarticulo
                      AND COALESCE(di.stock_venta, 0) > 0
                      AND ((a.controla_lotes=0 AND a.controla_vencimiento=0)
                           OR (di.numero_lote IS NOT NULL AND di.numero_lote<>''
                               AND (di.fecha_vencimiento IS NULL OR di.fecha_vencimiento>=CURDATE())
                               AND (a.controla_vencimiento=0 OR di.fecha_vencimiento IS NOT NULL)))
					ORDER BY
						CASE WHEN di.stock_estado = '1' THEN 0 ELSE 1 END,
						di.iddetalle_ingreso ASC
					LIMIT 1
				) AS idingreso
			FROM articulo a
			INNER JOIN categoria c ON c.idcategoria = a.idcategoria
			LEFT JOIN subcategoria s ON s.idsubcategoria = a.idsubcategoria
			LEFT JOIN medida m ON m.idmedida = a.idmedida
			LEFT JOIN almacen al ON al.idalmacen = a.idalmacen
			LEFT JOIN (
				SELECT
					idarticulo,
					COUNT(*) AS cantidad_variaciones,
					COALESCE(SUM(stock), 0) AS stock_variaciones,
					MIN(NULLIF(precio_compra, 0)) AS precio_compra_min,
					MIN(NULLIF(precio_venta, 0)) AS precio_venta_min,
					MAX(NULLIF(precio_venta, 0)) AS precio_venta_max
				FROM articulo_variacion
				WHERE estado = 1
				GROUP BY idarticulo
			) v ON v.idarticulo = a.idarticulo
			WHERE a.condicion = 1
			ORDER BY c.nombre ASC, a.nombre ASC";

		$productos = $this->conexion->getDataAll($sql);
		$productos = is_array($productos) ? $productos : [];

		$variaciones = $this->conexion->getDataAll(
			"SELECT
				av.idvariacion,
				av.idarticulo,
				av.combinacion,
				av.sku,
                CASE WHEN a.controla_lotes=1 OR a.controla_vencimiento=1
                     THEN (SELECT COALESCE(SUM(di.stock_venta),0) FROM detalle_ingreso di
                           WHERE di.idarticulo=av.idarticulo AND di.idvariacion=av.idvariacion
                           AND di.tipo_detalle='INVENTARIO' AND di.afecta_stock=1
                           AND di.estado=1 AND di.stock_venta>0
                           AND di.numero_lote IS NOT NULL AND di.numero_lote<>''
                           AND (di.fecha_vencimiento IS NULL OR di.fecha_vencimiento>=CURDATE())
                           AND (a.controla_vencimiento=0 OR di.fecha_vencimiento IS NOT NULL))
                     ELSE COALESCE(av.stock,0) END AS stock,
				COALESCE(av.precio_compra, 0) AS precio_compra,
				COALESCE(av.precio_venta, 0) AS precio_venta,
                av.precio_preferencial,
				COALESCE(NULLIF(av.imagen, ''), a.imagen) AS imagen
			 FROM articulo_variacion av
			 INNER JOIN articulo a ON a.idarticulo = av.idarticulo
			 WHERE av.estado = 1
			   AND a.condicion = 1
			 ORDER BY av.idarticulo ASC, av.combinacion ASC, av.idvariacion ASC"
		);

		$variacionesPorArticulo = [];
		foreach (is_array($variaciones) ? $variaciones : [] as $variacion) {
			$idArticulo = (int)($variacion['idarticulo'] ?? 0);
			if ($idArticulo <= 0) {
				continue;
			}
			$variacionesPorArticulo[$idArticulo][] = $variacion;
		}

		foreach ($productos as &$producto) {
			$idArticulo = (int)($producto['idarticulo'] ?? 0);
			$producto['variaciones'] = $variacionesPorArticulo[$idArticulo] ?? [];
		}
		unset($producto);

		return $productos;
	}

	public function cargarMasivoDesdeCSV($rutaArchivo)
	{
		$mensajes_exito = [];
		$mensajes_error = [];
		$fila = 1;

		if (($handle = fopen($rutaArchivo, "r")) !== FALSE) {
			$primeraLinea = fgets($handle);
			rewind($handle);
			$delimitadores = [',', ';', "\t"];
			$delimitador = ',';
			$mejorConteo = 0;
			foreach ($delimitadores as $candidato) {
				$conteo = count(str_getcsv((string)$primeraLinea, $candidato));
				if ($conteo > $mejorConteo) {
					$mejorConteo = $conteo;
					$delimitador = $candidato;
				}
			}

			while (($data = fgetcsv($handle, 0, $delimitador)) !== FALSE) {
				if ($fila === 1 && isset($data[0])) {
					$data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$data[0]);
				}
				if ($fila == 1) {
					$fila++;
					continue;
				}

				if (count($data) < 9) {
					$mensajes_error[] = "⚠️ Fila $fila: El archivo no tiene el número correcto de columnas (esperado: 9).";
					$fila++;
					continue;
				}

				list($nombre, $codigo, $stock, $precio_compra, $precio_venta, $idcategoria, $idsubcategoria, $idalmacen, $idmedida) = $data;

				if (empty($nombre) || empty($codigo)) {
					$mensajes_error[] = "⚠️ Fila $fila: El nombre o código está vacío. Producto no registrado.";
					$fila++;
					continue;
				}

				// Validar existencia de código duplicado
				$productoExistente = $this->verificarCodigo($codigo);
				if (!empty($productoExistente) && isset($productoExistente['codigo'])) {
					$mensajes_error[] = "🔁 Fila $fila: Ya existe un producto con el código '$codigo'. No se registró.";
					$fila++;
					continue;
				}

				// Validar claves foráneas
				$erroresFK = [];

				if (empty($this->conexion->getData("SELECT idcategoria FROM categoria WHERE idcategoria = ?", [$idcategoria]))) {
					$erroresFK[] = "Categoría (ID: $idcategoria)";
				}

				if (empty($this->conexion->getData("SELECT idsubcategoria FROM subcategoria WHERE idsubcategoria = ?", [$idsubcategoria]))) {
					$erroresFK[] = "Subcategoría (ID: $idsubcategoria)";
				}

				if (empty($this->conexion->getData("SELECT idmedida FROM medida WHERE idmedida = ?", [$idmedida]))) {
					$erroresFK[] = "Unidad de medida (ID: $idmedida)";
				}

				if (empty($this->conexion->getData("SELECT idalmacen FROM almacen WHERE idalmacen = ?", [$idalmacen]))) {
					$erroresFK[] = "Almacén (ID: $idalmacen)";
				}

				if (!empty($erroresFK)) {
					$mensajes_error[] = "❌ Fila $fila: No se registró el producto porque no se encontraron: " . implode(", ", $erroresFK) . ".";
					$fila++;
					continue;
				}

				// Valores por defecto
				$descripcion = "";
				$imagen = "default.png";

				$resultado = $this->insertar(
					$idcategoria,
					$idsubcategoria,
					$idmedida,
					$idalmacen,
					$codigo,
					$nombre,
					$stock,
					$precio_compra,
					$precio_venta,
					$descripcion,
					$imagen
				);

				if ($resultado) {
					$mensajes_exito[] = "✅ Fila $fila: Producto '$nombre' registrado correctamente.";
				} else {
					$mensajes_error[] = "⚠️ Fila $fila: Ocurrió un error al registrar el producto '$nombre'.";
				}

				$fila++;
			}

			fclose($handle);
		} else {
			$mensajes_error[] = "🚫 No se pudo abrir el archivo CSV.";
		}

		return [
			'exitosos' => $mensajes_exito,
			'errores' => $mensajes_error
		];
	}


	/**
	 * Inserción segura para importaciones masivas.
	 * A diferencia de insertar(), nunca imprime ni finaliza el proceso si una fila falla.
	 */
	public function insertarImportacionSegura(
		$idcategoria,
		$idsubcategoria,
		$idmedida,
		$idalmacen,
		$codigo,
		$nombre,
		$stock,
		$precio_compra,
		$precio_venta,
		$descripcion = '',
		$imagen = 'default.png',
		$codigo_afectacion_igv = '10',
		$porcentaje_igv = 18.00,
		$unidad_medida_sunat = 'NIU',
		$codigo_producto_sunat = null
	) {
		$transaccionIniciada = false;
		try {
			$this->conexion->beginTransaction();
			$transaccionIniciada = true;

			$sql = "INSERT INTO $this->tableName
			(idcategoria, idsubcategoria, idmedida, idalmacen, codigo, nombre, stock, precio_compra, precio_venta, descripcion, imagen, codigo_afectacion_igv, porcentaje_igv, unidad_medida_sunat, codigo_producto_sunat, condicion)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

			$idarticulo = (int)$this->conexion->setDataReturnId($sql, [
				$idcategoria,
				$idsubcategoria,
				$idmedida,
				$idalmacen,
				$codigo,
				$nombre,
				$stock,
				$precio_compra,
				$precio_venta,
				$descripcion,
				$imagen,
				$codigo_afectacion_igv,
				$porcentaje_igv,
				$unidad_medida_sunat,
				$codigo_producto_sunat
			]);

			if ($idarticulo <= 0) {
				throw new RuntimeException('No se generó el ID del producto.');
			}

			$stock = max(0, (int)$stock);
			$precio_compra = max(0, (float)$precio_compra);
			$precio_venta = max(0, (float)$precio_venta);

			if ($stock > 0 && $precio_venta > 0) {
				$idusuario = (int)($_SESSION['idusuario'] ?? 1);
				$idproveedor = 1;
				$num = str_pad((string)random_int(1, 9999999), 7, '0', STR_PAD_LEFT);
				$total_compra = $precio_compra * $stock;

				$sqlIngreso = "INSERT INTO ingreso
				(idproveedor, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, impuesto, total_compra, estado)
				VALUES (?, ?, 'Stock Inicial', 'INI', ?, NOW(), 0, ?, 'Aceptado')";
				$idIngreso = (int)$this->conexion->setDataReturnId($sqlIngreso, [$idproveedor, $idusuario, $num, $total_compra]);

				$sqlDetalle = "INSERT INTO detalle_ingreso
				(idarticulo, idingreso, cantidad, stock_venta, precio_compra, precio_venta, estado, stock_estado)
				VALUES (?, ?, ?, ?, ?, ?, 1, 1)";
				$this->conexion->setData($sqlDetalle, [$idarticulo, $idIngreso, $stock, $stock, $precio_compra, $precio_venta]);

				$detalle = 'Stock Inicial INI-' . $num;
				$total = $stock * $precio_compra;
				$sqlKardex = "INSERT INTO kardex
				(iddetalle, idarticulo, fecha, detalle,
				 cantidadi, costoui, totali,
				 cantidads, costous, totals,
				 cantidadex, costouex, totalex, tipo, estado)
				VALUES (?, ?, NOW(), ?, ?, ?, ?, 0, 0, 0, ?, ?, ?, 'Ingreso', 'Activo')";
				$this->conexion->setData($sqlKardex, [
					$idIngreso,
					$idarticulo,
					$detalle,
					$stock,
					$precio_compra,
					$total,
					$stock,
					$precio_compra,
					$total
				]);
			}

			$this->conexion->commit();
			$transaccionIniciada = false;
			return ['success' => true, 'idarticulo' => $idarticulo, 'error' => null];
		} catch (Throwable $error) {
			if ($transaccionIniciada) {
				try {
					$this->conexion->rollBack();
				} catch (Throwable $rollbackError) {
					error_log('[ROLLBACK IMPORTACION PRODUCTO] ' . $rollbackError->getMessage());
				}
			}
			error_log('[IMPORTACION PRODUCTO] ' . $error->getMessage());
			return ['success' => false, 'idarticulo' => 0, 'error' => $error->getMessage()];
		}
	}

	/**
	 * Verifica un código tanto en productos padre como en SKU de variaciones.
	 * Se usa en la importación masiva para evitar colisiones entre ambos tipos.
	 */
	public function verificarCodigoGlobal($codigo)
	{
		$codigo = trim((string)$codigo);
		if ($codigo === '') {
			return false;
		}

		$producto = $this->conexion->getData(
			"SELECT idarticulo, codigo FROM articulo WHERE codigo = ? LIMIT 1",
			[$codigo]
		);

		if (!empty($producto)) {
			return [
				'tipo' => 'producto',
				'id' => (int)($producto['idarticulo'] ?? 0),
				'codigo' => (string)($producto['codigo'] ?? $codigo)
			];
		}

		$variacion = $this->conexion->getData(
			"SELECT idvariacion, idarticulo, sku FROM articulo_variacion WHERE sku = ? LIMIT 1",
			[$codigo]
		);

		if (!empty($variacion)) {
			return [
				'tipo' => 'variacion',
				'id' => (int)($variacion['idvariacion'] ?? 0),
				'idarticulo' => (int)($variacion['idarticulo'] ?? 0),
				'codigo' => (string)($variacion['sku'] ?? $codigo)
			];
		}

		return false;
	}

	/**
	 * Crea un producto padre junto con todas sus variaciones en una sola
	 * transacción. Si una variación falla no queda un producto incompleto.
	 */
	public function insertarGrupoVariantesImportacionSegura(array $producto, array $variaciones)
	{
		$transaccionIniciada = false;

		try {
			if (!$variaciones) {
				throw new RuntimeException('El grupo no contiene variaciones para registrar.');
			}

			$this->conexion->beginTransaction();
			$transaccionIniciada = true;

			$sqlProducto = "INSERT INTO $this->tableName
				(idcategoria, idsubcategoria, idmedida, idalmacen, codigo, nombre, stock, precio_compra, precio_venta, descripcion, imagen, codigo_afectacion_igv, porcentaje_igv, unidad_medida_sunat, codigo_producto_sunat, condicion)
				VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

			$idarticulo = (int)$this->conexion->setDataReturnId($sqlProducto, [
				(int)($producto['idcategoria'] ?? 0),
				!empty($producto['idsubcategoria']) ? (int)$producto['idsubcategoria'] : null,
				(int)($producto['idmedida'] ?? 0),
				(int)($producto['idalmacen'] ?? 0),
				(isset($producto['codigo']) && trim((string)$producto['codigo']) !== '') ? trim((string)$producto['codigo']) : null,
				trim((string)($producto['nombre'] ?? '')),
				max(0, (float)($producto['precio_compra'] ?? 0)),
				max(0, (float)($producto['precio_venta'] ?? 0)),
				trim((string)($producto['descripcion'] ?? 'Importado desde carga masiva')),
				trim((string)($producto['imagen'] ?? 'default.png')) ?: 'default.png',
				trim((string)($producto['codigo_afectacion_igv'] ?? '10')),
				max(0, (float)($producto['porcentaje_igv'] ?? 0)),
				strtoupper(trim((string)($producto['unidad_medida_sunat'] ?? 'NIU'))),
				isset($producto['codigo_producto_sunat']) && trim((string)$producto['codigo_producto_sunat']) !== ''
					? trim((string)$producto['codigo_producto_sunat'])
					: null
			]);

			if ($idarticulo <= 0) {
				throw new RuntimeException('No se generó el ID del producto padre.');
			}

			$sqlVariacion = "INSERT INTO articulo_variacion
                (idarticulo, combinacion, sku, stock, precio_compra, precio_venta, precio_preferencial, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";

			$idsVariaciones = [];
			foreach ($variaciones as $variacion) {
				$sku = trim((string)($variacion['sku'] ?? ''));
				$combinacion = trim((string)($variacion['combinacion'] ?? ''));

				if ($sku === '' || $combinacion === '') {
					throw new RuntimeException('Una variación no tiene SKU o descripción de variante.');
				}

				$idvariacion = (int)$this->conexion->setDataReturnId($sqlVariacion, [
					$idarticulo,
					$combinacion,
					$sku,
					max(0, (int)($variacion['stock'] ?? 0)),
					max(0, (float)($variacion['precio_compra'] ?? 0)),
					max(0, (float)($variacion['precio_venta'] ?? 0)),
                    isset($variacion['precio_preferencial']) ? (float)$variacion['precio_preferencial'] : null
				]);

				if ($idvariacion <= 0) {
					throw new RuntimeException('No se pudo registrar la variación ' . $sku . '.');
				}

				$idsVariaciones[] = $idvariacion;
			}

			$this->conexion->commit();
			$transaccionIniciada = false;

			return [
				'success' => true,
				'idarticulo' => $idarticulo,
				'ids_variaciones' => $idsVariaciones,
				'error' => null
			];
		} catch (Throwable $error) {
			if ($transaccionIniciada) {
				try {
					$this->conexion->rollBack();
				} catch (Throwable $rollbackError) {
					error_log('[ROLLBACK IMPORTACION VARIANTES] ' . $rollbackError->getMessage());
				}
			}

			error_log('[IMPORTACION PRODUCTO VARIABLE] ' . $error->getMessage());

			return [
				'success' => false,
				'idarticulo' => 0,
				'ids_variaciones' => [],
				'error' => $error->getMessage()
			];
		}
	}

	public function insertarVariacion($idarticulo, $combinacion, $sku, $stock, $precio_compra, $precio_venta)
	{
		try {
			// Validaciones mínimas
			if (empty($sku)) {
				$sku = 'SKU-' . uniqid();
			}

			if ($stock < 0)
				$stock = 0;
			if ($precio_compra < 0)
				$precio_compra = 0;
			if ($precio_venta < 0)
				$precio_venta = 0;

			$sql = "INSERT INTO articulo_variacion 
			(idarticulo, combinacion, sku, stock, precio_compra, precio_venta, estado) 
			VALUES (?, ?, ?, ?, ?, ?, 1)";
			$arrData = [$idarticulo, $combinacion, $sku, $stock, $precio_compra, $precio_venta];

			return $this->conexion->setData($sql, $arrData);
		} catch (PDOException $e) {
			echo "❌ Error en insertarVariacion(): " . $e->getMessage();
			return false;
		}
	}

	public function listarVariacionesVenta()
	{
		$sql = "SELECT 
					av.idvariacion,
					av.idarticulo,
					av.sku AS codigo,
					CONCAT(a.nombre, ' - ', av.combinacion) AS nombre,
					av.stock,
					av.precio_compra,
					av.precio_venta,
					a.codigo_afectacion_igv,
					a.porcentaje_igv,
					a.unidad_medida_sunat,
					a.codigo_producto_sunat,
					a.descripcion,
					a.imagen,
					a.condicion,
					c.nombre AS categoria,
					s.nombre AS subcategoria,
					m.nombre AS medida,
					al.nombre AS almacen
				FROM articulo_variacion av
				INNER JOIN articulo a ON av.idarticulo = a.idarticulo
				INNER JOIN categoria c ON a.idcategoria = c.idcategoria
				LEFT JOIN subcategoria s ON a.idsubcategoria = s.idsubcategoria
				LEFT JOIN medida m ON a.idmedida = m.idmedida
				LEFT JOIN almacen al ON a.idalmacen = al.idalmacen
				WHERE av.estado = 1 AND av.stock > 0 AND a.condicion = 1";
		return $this->conexion->getDataAll($sql);
	}

	/**
	 * Listado administrativo compacto.
	 * Las variaciones se resumen en el producto padre para evitar filas duplicadas.
	 */
	public function listarGestionProductos()
	{
		$sql = "SELECT
					a.idarticulo,
					a.codigo,
					a.nombre,
					a.descripcion,
					a.imagen,
					a.precio_compra,
					a.precio_venta,
					a.codigo_afectacion_igv,
					a.porcentaje_igv,
					a.unidad_medida_sunat,
					a.codigo_producto_sunat,
					a.condicion,
					c.nombre AS categoria,
					s.nombre AS subcategoria,
					m.nombre AS medida,
					al.nombre AS almacen,
					COALESCE(v.cantidad_variaciones, 0) AS cantidad_variaciones,
					CASE WHEN COALESCE(v.cantidad_variaciones, 0) > 0 THEN 1 ELSE 0 END AS tiene_variaciones,
					CASE
						WHEN COALESCE(v.cantidad_variaciones, 0) > 0
						THEN COALESCE(v.stock_variaciones, 0)
						ELSE a.stock
					END AS stock,
					CASE
						WHEN COALESCE(v.cantidad_variaciones, 0) > 0
						THEN COALESCE(v.precio_venta_min, a.precio_venta)
						ELSE a.precio_venta
					END AS precio_venta_min,
					CASE
						WHEN COALESCE(v.cantidad_variaciones, 0) > 0
						THEN COALESCE(v.precio_venta_max, a.precio_venta)
						ELSE a.precio_venta
					END AS precio_venta_max
				FROM articulo a
				LEFT JOIN categoria c ON c.idcategoria = a.idcategoria
				LEFT JOIN subcategoria s ON s.idsubcategoria = a.idsubcategoria
				LEFT JOIN medida m ON m.idmedida = a.idmedida
				LEFT JOIN almacen al ON al.idalmacen = a.idalmacen
				LEFT JOIN (
					SELECT
						idarticulo,
						COUNT(*) AS cantidad_variaciones,
						SUM(stock) AS stock_variaciones,
						MIN(NULLIF(precio_venta, 0)) AS precio_venta_min,
						MAX(NULLIF(precio_venta, 0)) AS precio_venta_max
					FROM articulo_variacion
					WHERE estado = 1
					GROUP BY idarticulo
				) v ON v.idarticulo = a.idarticulo
				ORDER BY a.nombre ASC, a.idarticulo DESC";

		$resultado = $this->conexion->getDataAll($sql);
		return is_array($resultado) ? $resultado : [];
	}

	public function listarActivosVenta()
	{
		$sql = "SELECT 
					a.idarticulo,
					a.codigo,
					a.nombre,
					a.precio_compra,
					a.precio_venta,
					a.codigo_afectacion_igv,
					a.porcentaje_igv,
					a.unidad_medida_sunat,
					a.codigo_producto_sunat,
					                a.stock,
                a.controla_lotes,
                a.controla_vencimiento,
				a.imagen,
					a.condicion,
					c.nombre AS categoria,
					s.nombre AS subcategoria,
					m.nombre AS medida,
					al.nombre AS almacen,
					EXISTS (
						SELECT 1 FROM articulo_variacion av
						WHERE av.idarticulo = a.idarticulo AND av.estado = 1
					) AS tiene_variaciones
				FROM articulo a
				INNER JOIN categoria c ON a.idcategoria = c.idcategoria
				LEFT JOIN subcategoria s ON a.idsubcategoria = s.idsubcategoria
				LEFT JOIN medida m ON a.idmedida = m.idmedida
				LEFT JOIN almacen al ON a.idalmacen = al.idalmacen
				WHERE a.condicion = 1";

		$productos = $this->conexion->getDataAll($sql);

        $saldosControlados = $this->conexion->getDataAll(
            "SELECT di.idarticulo, SUM(di.stock_venta) AS saldo
             FROM detalle_ingreso di JOIN articulo a ON a.idarticulo=di.idarticulo
             WHERE (a.controla_lotes=1 OR a.controla_vencimiento=1)
               AND di.tipo_detalle='INVENTARIO' AND di.afecta_stock=1
               AND di.estado=1 AND di.stock_venta>0
               AND di.numero_lote IS NOT NULL AND di.numero_lote<>''
               AND (di.fecha_vencimiento IS NULL OR di.fecha_vencimiento>=CURDATE())
               AND (a.controla_vencimiento=0 OR di.fecha_vencimiento IS NOT NULL)
             GROUP BY di.idarticulo"
        );
        $saldosPorProducto = [];
        foreach ($saldosControlados as $s) $saldosPorProducto[(int)$s['idarticulo']] = (int)$s['saldo'];
		foreach ($productos as &$p) {
            if ((int)($p['controla_lotes'] ?? 0) === 1 || (int)($p['controla_vencimiento'] ?? 0) === 1) {
                $p['stock'] = $saldosPorProducto[(int)$p['idarticulo']] ?? 0;
                continue;
            }
			if (!empty($p['tiene_variaciones'])) {
				$id = $p['idarticulo'];
				$sqlSum = "SELECT SUM(stock) FROM articulo_variacion WHERE estado = 1 AND idarticulo = ?";
				$total = $this->conexion->getValue($sqlSum, [$id]);
				$p['stock'] = ($total !== null) ? (int) $total : 0;
			}
		}

		return $productos;
	}


	public function listarVariacionesPorArticulo($idarticulo)
	{
		$sql = "SELECT 
				av.idvariacion,
				av.idarticulo,
				av.combinacion,
				av.sku,
				av.stock,
				av.precio_compra,
				av.precio_venta,
                av.precio_preferencial
			FROM articulo_variacion av
			WHERE av.estado = 1 AND av.idarticulo = ?";
		return $this->conexion->getDataAll($sql, [$idarticulo]);
	}

	/**
	 * Catálogo listo para el generador de etiquetas.
	 * Los productos variables se exponen por variante y se oculta el padre para
	 * evitar imprimir un SKU que no corresponde a una combinación vendible.
	 */
	public function listarCatalogoEtiquetas()
	{
		return $this->buscarCatalogoEtiquetas('', 'todos', 80);
	}

	/**
	 * Búsqueda en vivo para el generador de etiquetas.
	 *
	 * Busca directamente en la base de datos para no depender de tener todo el
	 * catálogo precargado en el navegador. En productos variables permite buscar
	 * tanto por el SKU de la variante (articulo_variacion.sku) como por el SKU
	 * padre (articulo.codigo).
	 */
	public function buscarCatalogoEtiquetas($termino = '', $tipo = 'todos', $limite = 80)
	{
		$termino = trim((string)$termino);
		$tipo = trim((string)$tipo);
		$limite = max(1, min(150, (int)$limite));

		/*
		 * IMPORTANTE:
		 * No usar UNION entre articulo y articulo_variacion aquí. En instalaciones
		 * antiguas ambas tablas pueden tener collations distintas (por ejemplo
		 * utf8mb3_general_ci y utf8mb3_spanish_ci) y MariaDB devuelve un error
		 * "Illegal mix of collations". El módulo Productos ya usa
		 * listarGestionProductos(), por lo que partimos de esa consulta probada y
		 * armamos el catálogo de etiquetas en PHP.
		 */
		$productosBase = $this->listarGestionProductos();
		if (!is_array($productosBase)) {
			$productosBase = [];
		}

		$variaciones = $this->conexion->getDataAll(
			"SELECT
				idvariacion,
				idarticulo,
				sku,
				stock,
				precio_venta,
				combinacion
			 FROM articulo_variacion
			 WHERE estado = 1
			 ORDER BY idarticulo ASC, idvariacion ASC"
		);
		if (!is_array($variaciones)) {
			$variaciones = [];
		}

		$variacionesPorArticulo = [];
		foreach ($variaciones as $variacion) {
			$idArticuloVariacion = (int)($variacion['idarticulo'] ?? 0);
			if ($idArticuloVariacion <= 0) {
				continue;
			}
			if (!isset($variacionesPorArticulo[$idArticuloVariacion])) {
				$variacionesPorArticulo[$idArticuloVariacion] = [];
			}
			$variacionesPorArticulo[$idArticuloVariacion][] = $variacion;
		}

		$catalogo = [];

		foreach ($productosBase as $producto) {
			if ((int)($producto['condicion'] ?? 0) !== 1) {
				continue;
			}

			$idarticulo = (int)($producto['idarticulo'] ?? 0);
			if ($idarticulo <= 0) {
				continue;
			}

			$codigoPadre = trim((string)($producto['codigo'] ?? ''));
			$nombrePadre = trim((string)($producto['nombre'] ?? ''));
			$categoria = trim((string)($producto['categoria'] ?? ''));
			$almacen = trim((string)($producto['almacen'] ?? ''));
			$imagen = trim((string)($producto['imagen'] ?? ''));
			$variacionesArticulo = $variacionesPorArticulo[$idarticulo] ?? [];

			if (!empty($variacionesArticulo)) {
				if ($tipo === 'simple') {
					continue;
				}

				foreach ($variacionesArticulo as $variacion) {
					$sku = trim((string)($variacion['sku'] ?? ''));
					if ($sku === '') {
						continue;
					}

					$combinacion = trim((string)($variacion['combinacion'] ?? ''));
					$precioVariacion = (float)($variacion['precio_venta'] ?? 0);
					$precioPadre = (float)($producto['precio_venta_min'] ?? $producto['precio_venta'] ?? 0);

					$catalogo[] = [
						'tipo' => 'variacion',
						'idregistro' => (int)($variacion['idvariacion'] ?? 0),
						'idarticulo' => $idarticulo,
						'codigo' => $sku,
						'codigo_padre' => $codigoPadre,
						'nombre' => $nombrePadre . ($combinacion !== '' ? ' - ' . $combinacion : ''),
						'nombre_padre' => $nombrePadre,
						'variante' => $combinacion,
						'stock' => (int)($variacion['stock'] ?? 0),
						'precio_venta' => $precioVariacion > 0 ? $precioVariacion : $precioPadre,
						'categoria' => $categoria,
						'almacen' => $almacen,
						'imagen' => $imagen
					];
				}

				continue;
			}

			if ($tipo === 'variacion') {
				continue;
			}

			if ($codigoPadre === '') {
				continue;
			}

			$catalogo[] = [
				'tipo' => 'simple',
				'idregistro' => $idarticulo,
				'idarticulo' => $idarticulo,
				'codigo' => $codigoPadre,
				'codigo_padre' => $codigoPadre,
				'nombre' => $nombrePadre,
				'nombre_padre' => $nombrePadre,
				'variante' => '',
				'stock' => (int)($producto['stock'] ?? 0),
				'precio_venta' => (float)($producto['precio_venta_min'] ?? $producto['precio_venta'] ?? 0),
				'categoria' => $categoria,
				'almacen' => $almacen,
				'imagen' => $imagen
			];
		}

		if ($termino !== '') {
			$catalogo = array_values(array_filter($catalogo, function ($item) use ($termino) {
				$campos = [
					$item['codigo'] ?? '',
					$item['codigo_padre'] ?? '',
					$item['nombre'] ?? '',
					$item['nombre_padre'] ?? '',
					$item['variante'] ?? '',
					$item['categoria'] ?? '',
					$item['almacen'] ?? ''
				];

				foreach ($campos as $campo) {
					if (stripos((string)$campo, $termino) !== false) {
						return true;
					}
				}

				return false;
			}));
		}

		$puntaje = function ($item) use ($termino) {
			if ($termino === '') {
				return 10;
			}

			$codigo = (string)($item['codigo'] ?? '');
			$codigoPadreItem = (string)($item['codigo_padre'] ?? '');
			$nombre = (string)($item['nombre'] ?? '');

			if (strcasecmp($codigo, $termino) === 0) return 0;
			if (strcasecmp($codigoPadreItem, $termino) === 0) return 1;
			if (stripos($codigo, $termino) === 0) return 2;
			if (stripos($codigoPadreItem, $termino) === 0) return 3;
			if (stripos($nombre, $termino) === 0) return 4;
			return 10;
		};

		usort($catalogo, function ($a, $b) use ($puntaje) {
			$pa = $puntaje($a);
			$pb = $puntaje($b);
			if ($pa !== $pb) {
				return $pa < $pb ? -1 : 1;
			}

			$nombreA = (string)($a['nombre'] ?? '');
			$nombreB = (string)($b['nombre'] ?? '');
			$comparacionNombre = strcasecmp($nombreA, $nombreB);
			if ($comparacionNombre !== 0) {
				return $comparacionNombre;
			}

			return strcasecmp((string)($a['codigo'] ?? ''), (string)($b['codigo'] ?? ''));
		});

		return array_slice($catalogo, 0, $limite);
	}

	public function ejecutarSQL($sql, $params = [])
	{
		return $this->conexion->setData($sql, $params);
	}

	public function ejecutarSQLReturnId($sql, $params = [])
	{
		return $this->conexion->setDataReturnId($sql, $params);
	}
}
