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
		$codigo_producto_sunat = null
	)
	{
		try {
			// Insertar el producto y obtener su ID
			$sql = "INSERT INTO $this->tableName 
			(idcategoria, idsubcategoria, idmedida, idalmacen, codigo, nombre, stock, precio_compra, precio_venta, descripcion, imagen, codigo_afectacion_igv, porcentaje_igv, unidad_medida_sunat, codigo_producto_sunat, condicion)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
			$arrData = array(
				$idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo,
				$nombre, $stock, $precio_compra, $precio_venta, $descripcion, $imagen,
				$codigo_afectacion_igv, $porcentaje_igv, $unidad_medida_sunat,
				$codigo_producto_sunat
			);
			$idarticulo = $this->conexion->setDataReturnId($sql, $arrData);
			// Si hay stock inicial, registrar en ingreso, detalle_ingreso y kardex.
			// El costo de compra puede ser 0.00.
			if ($stock > 0 && $precio_venta > 0) {
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
				(idarticulo, idingreso, cantidad, stock_venta, precio_compra, precio_venta, estado, stock_estado) 
				VALUES (?, ?, ?, ?, ?, ?, 1, 1)";
				$arrDetalle = [$idarticulo, $idIngreso, $stock, $stock, $precio_compra, $precio_venta];
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

			return $idarticulo;
		} catch (PDOException $e) {
			echo "❌ Error en insertar(): " . $e->getMessage();
			exit;
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
		$sql = "SELECT * FROM articulo WHERE idcategoria=? AND condicion=1";
		return $this->conexion->getDataAll($sql, [$idcategoria]);
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
				a.precio_compra,
				a.precio_venta,
				a.stock,
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
			WHERE a.condicion = 1
			ORDER BY c.nombre ASC, a.nombre ASC";

		$resultado = $this->conexion->getDataAll($sql);
		return is_array($resultado) ? $resultado : [];
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
				trim((string)($producto['codigo'] ?? '')),
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
				(idarticulo, combinacion, sku, stock, precio_compra, precio_venta, estado)
				VALUES (?, ?, ?, ?, ?, ?, 1)";

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
					max(0, (float)($variacion['precio_venta'] ?? 0))
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

		foreach ($productos as &$p) {
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
				av.precio_venta
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
