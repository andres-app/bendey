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

	public function insertar($idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo, $nombre, $stock, $precio_compra, $precio_venta, $descripcion, $imagen)
	{
		try {
			// Insertar el producto y obtener su ID
			$sql = "INSERT INTO $this->tableName 
			(idcategoria, idsubcategoria, idmedida, idalmacen, codigo, nombre, stock, precio_compra, precio_venta, descripcion, imagen, condicion)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
			$arrData = array($idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo, $nombre, $stock, $precio_compra, $precio_venta, $descripcion, $imagen);
			$idarticulo = $this->conexion->setDataReturnId($sql, $arrData);

			// Si hay stock inicial, registrar en ingreso, detalle_ingreso y kardex
			if ($stock > 0 && $precio_compra > 0 && $precio_venta > 0) {
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
				(iddetalle, idarticulo, fecha, detalle, cantidadi, costoui, totali, cantidadex, costouex, totalex, tipo, estado) 
				VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 'Ingreso', 'Activo')";
				$arrKardex = [$idIngreso, $idarticulo, $detalle, $stock, $precioUnitario, $total, $stock, $precioUnitario, $total];
				$this->conexion->setData($sqlKardex, $arrKardex);
			}

			return $idarticulo;
		} catch (PDOException $e) {
			echo "❌ Error en insertar(): " . $e->getMessage();
			exit;
		}
	}



	public function editar($idarticulo, $idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo, $nombre, $stock, $precio_compra, $precio_venta, $descripcion, $imagen)
	{
		$sql = "UPDATE $this->tableName 
        SET idcategoria=?, idsubcategoria=?, idmedida=?, idalmacen=?, codigo=?, nombre=?, stock=?, precio_compra=?, precio_venta=?, descripcion=?, imagen=?
        WHERE idarticulo=?";
		$arrData = array($idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo, $nombre, $stock, $precio_compra, $precio_venta, $descripcion, $imagen, $idarticulo);
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
		$sql = "SELECT * FROM $this->tableName WHERE idarticulo=?";
		$arrData = array($idarticulo);
		return $this->conexion->getData($sql, $arrData);
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
				a.descripcion,
				a.imagen,
				a.condicion,
				m.nombre AS medida,
				a.idalmacen,
				al.nombre AS almacen
			FROM articulo_variacion av
			INNER JOIN articulo a ON av.idarticulo = a.idarticulo
			INNER JOIN medida m ON a.idmedida = m.idmedida
			LEFT JOIN almacen al ON a.idalmacen = al.idalmacen
			WHERE av.estado = 1 AND av.stock > 0 AND a.condicion = 1";
		return $this->conexion->getDataAll($sql);
	}

	public function listarActivosVenta()
	{
		$sql = "SELECT 
					a.idarticulo,
					a.codigo,
					a.nombre,
					a.precio_compra,
					a.precio_venta,
					a.stock,
					a.imagen,
					a.condicion,
					c.nombre AS categoria,
					s.nombre AS subcategoria,
					m.nombre AS medida,
					al.nombre AS almacen        -- ✅ Aquí se agrega el almacén
				FROM articulo a
				INNER JOIN categoria c ON a.idcategoria = c.idcategoria
				LEFT JOIN subcategoria s ON a.idsubcategoria = s.idsubcategoria
				LEFT JOIN medida m ON a.idmedida = m.idmedida
				LEFT JOIN almacen al ON a.idalmacen = al.idalmacen   -- ✅ JOIN con almacén
				WHERE a.condicion = 1 AND a.stock > 0";
		return $this->conexion->getDataAll($sql);
	}
}
