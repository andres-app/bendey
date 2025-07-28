<?php
require_once "../Config/Conexion.php";

class ArticuloVariacion
{
    private $table = 'articulo_variacion';
    private $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    // Insertar una nueva variación
    public function insertar($idarticulo, $sku, $stock, $precio_venta, $precio_compra, $imagen)
    {
        $sql = "INSERT INTO $this->table 
                (idarticulo, sku, stock, precio_venta, precio_compra, imagen) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $arrData = array($idarticulo, $sku, $stock, $precio_venta, $precio_compra, $imagen);
        return $this->conexion->setDataReturnId($sql, $arrData);
    }

    // Listar variaciones de un producto
    public function listarPorArticulo($idarticulo)
    {
        $sql = "SELECT * FROM $this->table WHERE idarticulo = ? AND estado = 1";
        return $this->conexion->getData($sql, array($idarticulo));
    }

    // Obtener una variación por ID
    public function obtener($idvariacion)
    {
        $sql = "SELECT * FROM $this->table WHERE idvariacion = ?";
        return $this->conexion->getDataSingle($sql, array($idvariacion));
    }

    // Eliminar (lógico) una variación
    public function desactivar($idvariacion)
    {
        $sql = "UPDATE $this->table SET estado = 0 WHERE idvariacion = ?";
        return $this->conexion->setData($sql, array($idvariacion));
    }
}
