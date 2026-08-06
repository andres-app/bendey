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

	public function cargarMasivoDesdeCSV($rutaArchivo)
	{
		$mensajes_exito = [];
		$mensajes_error = [];
		$fila = 1;

		if (($handle = fopen($rutaArchivo, "r")) !== FALSE) {
			while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
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
				if (!empty($productoExistente) && isset($productoExistente[0]['codigo'])) {
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

	public function ejecutarSQL($sql, $params = [])
	{
		return $this->conexion->setData($sql, $params);
	}

	public function ejecutarSQLReturnId($sql, $params = [])
	{
		return $this->conexion->setDataReturnId($sql, $params);
	}
}
