<?php
//incluir la conexion de base de datos
require_once "Connect.php";
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
	public function insertar($idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo, $nombre, $stock, $descripcion, $imagen)
	{
		$sql = "INSERT INTO $this->tableName (idcategoria, idsubcategoria, idmedida, idalmacen, codigo, nombre, stock, descripcion, imagen, condicion)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$arrData = array($idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo, $nombre, $stock, $descripcion, $imagen, 1);
		return $this->conexion->setData($sql, $arrData);
	}

	public function editar($idarticulo, $idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo, $nombre, $stock, $descripcion, $imagen)
	{
		$sql = "UPDATE $this->tableName 
				SET idcategoria=?, idsubcategoria=?, idmedida=?, idalmacen=?, codigo=?, nombre=?, stock=?, descripcion=?, imagen=? 
				WHERE idarticulo=?";
		$arrData = array($idcategoria, $idsubcategoria, $idmedida, $idalmacen, $codigo, $nombre, $stock, $descripcion, $imagen, $idarticulo);
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
    a.idalmacen,   -- ← aquí tienes el ID almacen
    al.nombre as almacen, -- ← nombre del almacen (si existe la tabla)
    a.codigo, 
    a.nombre, 
    a.stock, 
    a.descripcion, 
    a.imagen, 
    a.condicion,
    m.nombre as medida,
    (SELECT precio_compra FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 1) AS precio_compra,
    (SELECT precio_venta FROM detalle_ingreso WHERE idarticulo=a.idarticulo ORDER BY iddetalle_ingreso DESC LIMIT 1) AS precio_venta
FROM articulo a
INNER JOIN categoria c ON a.idcategoria=c.idcategoria
LEFT JOIN subcategoria s ON a.idsubcategoria=s.idsubcategoria
INNER JOIN medida m ON a.idmedida=m.idmedida
LEFT JOIN almacen al ON a.idalmacen = al.idalmacen -- ← agrega esto cuando tengas la tabla almacen
";
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
}
