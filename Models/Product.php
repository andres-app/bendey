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

			// Si hay stock inicial, registrar en ingreso y detalle_ingreso
			if ($stock > 0 && $precio_compra > 0 && $precio_venta > 0) {
				$idusuario = $_SESSION['idusuario'] ?? 1;
				$idproveedor = 1; // ← Proveedor genérico para stock inicial
				$num = str_pad(rand(1, 9999999), 7, '0', STR_PAD_LEFT);

				$total_compra = $precio_compra * $stock;

				// Insertar ingreso con total_compra
				$sqlIngreso = "INSERT INTO ingreso 
					(idproveedor, idusuario, tipo_comprobante, serie_comprobante, num_comprobante, fecha_hora, impuesto, total_compra, estado) 
					VALUES (?, ?, 'Stock Inicial', 'INI', ?, NOW(), 0, ?, 'Aceptado')";
				$idIngreso = $this->conexion->setDataReturnId($sqlIngreso, [$idproveedor, $idusuario, $num, $total_compra]);

				$sqlDetalle = "INSERT INTO detalle_ingreso 
				(idarticulo, idingreso, cantidad, stock_venta, precio_compra, precio_venta, estado, stock_estado) 
				VALUES (?, ?, ?, ?, ?, ?, 1, 1)";
				$arrDetalle = [$idarticulo, $idIngreso, $stock, $stock, $precio_compra, $precio_venta];
				$this->conexion->setData($sqlDetalle, $arrDetalle);
			}

			return true;
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
		$sql = "SELECT 
    a.idarticulo, 
    a.idcategoria, 
    c.nombre as categoria,
    a.idsubcategoria, 
    s.nombre as subcategoria,
    a.idalmacen,   
    al.nombre as almacen, 
    a.codigo, 
    a.nombre, 
    a.stock, 
    a.precio_compra,   -- << agrega esto
    a.precio_venta,    -- << agrega esto
    a.descripcion, 
    a.imagen, 
    a.condicion,
    m.nombre as medida
FROM articulo a
INNER JOIN categoria c ON a.idcategoria=c.idcategoria
LEFT JOIN subcategoria s ON a.idsubcategoria=s.idsubcategoria
INNER JOIN medida m ON a.idmedida=m.idmedida
LEFT JOIN almacen al ON a.idalmacen = al.idalmacen";
		return $this->conexion->getDataAll($sql);
	}


	//listar registros activos
	public function listarActivos()
	{
		$sql = "SELECT a.idarticulo, a.idcategoria, c.nombre as categoria, a.codigo, a.nombre, a.stock, a.descripcion, a.imagen, a.condicion, m.nombre as medida FROM $this->tableName a INNER JOIN categoria c ON a.idcategoria=c.idcategoria INNER JOIN medida m ON a.idmedida=m.idmedida WHERE a.condicion='1'";
		return $this->conexion->getDataAll($sql);
	}

	//listar y mostrar en Select
	public function listarActivosVenta()
	{
		$sql = "SELECT 
			a.idarticulo, 
			a.idcategoria, 
			c.nombre as categoria, 
			a.codigo, 
			a.nombre, 
			a.stock, 
			(SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo AND stock_estado='1' ORDER BY iddetalle_ingreso DESC LIMIT 0,1) AS precio_venta, 
			(SELECT precio_compra FROM detalle_ingreso WHERE idarticulo=a.idarticulo AND stock_estado='1' ORDER BY iddetalle_ingreso ASC LIMIT 0,1) AS precio_compra, 
			(SELECT idingreso FROM detalle_ingreso WHERE idarticulo=a.idarticulo AND stock_estado='1' LIMIT 0,1) AS idingreso, 
			a.descripcion, 
			a.imagen, 
			a.condicion, 
			m.nombre as medida,
			a.idalmacen, 
			al.nombre as almacen -- ← agregamos el nombre del almacén
		FROM articulo a 
		INNER JOIN categoria c ON a.idcategoria=c.idcategoria 
		INNER JOIN medida m ON a.idmedida=m.idmedida 
		LEFT JOIN almacen al ON a.idalmacen = al.idalmacen
		WHERE a.condicion='1' AND a.stock > 0";
		return $this->conexion->getDataAll($sql);
	}

	/*public function listarActivosVenta(){
		$sql="SELECT m.idingreso,m.fecha_hora,a.idarticulo,a.codigo,a.nombre,a.stock,m.cantidad,m.precio_venta, m.precio_compra,a.descripcion,a.imagen,a.condicion FROM ( SELECT di.idarticulo, di.cantidad, di.precio_compra,di.precio_venta,i.idingreso,i.fecha_hora, di.stock_estado FROM ingreso i INNER JOIN detalle_ingreso di ON i.idingreso=di.idingreso) AS m INNER JOIN articulo a ON m.idarticulo = a.idarticulo WHERE a.stock>0 AND a.condicion='1' AND m.stock_estado='1' ORDER BY m.fecha_hora ASC LIMIT 0,1";
		return  $this->conexion->getDataAll($sql);
	}*/

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
}
