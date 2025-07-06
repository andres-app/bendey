<?php
// Incluir la conexión de base de datos
require_once "Connect.php";
class Almacen
{
    private $tableName = 'almacen';
    private $conexion;

    // Implementamos nuestro constructor
    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    // Método para insertar registro
    public function insertar($nombre, $descripcion)
    {
        $sql = "INSERT INTO $this->tableName (nombre, descripcion, condicion) VALUES (?, ?, ?)";
        $arrData = array($nombre, $descripcion, 1);
        return $this->conexion->setData($sql, $arrData);
    }

    public function editar($idalmacen, $nombre, $descripcion)
    {
        $sql = "UPDATE $this->tableName SET nombre=?, descripcion=? WHERE idalmacen=?";
        $arrData = array($nombre, $descripcion, $idalmacen);
        return $this->conexion->setData($sql, $arrData);
    }

    public function desactivar($idalmacen)
    {
        $sql = "UPDATE $this->tableName SET condicion='0' WHERE idalmacen=?";
        $arrData = array($idalmacen);
        return $this->conexion->setData($sql, $arrData);
    }

    public function activar($idalmacen)
    {
        $sql = "UPDATE $this->tableName SET condicion='1' WHERE idalmacen=?";
        $arrData = array($idalmacen);
        return $this->conexion->setData($sql, $arrData);
    }

    // Método para mostrar registros
    public function mostrar(string $idalmacen)
    {
        $sql = "SELECT * FROM $this->tableName WHERE idalmacen=?";
        $arrData = array($idalmacen);
        return $this->conexion->getData($sql, $arrData);
    }

    // Listar registros
    public function listar()
    {
        $sql = "SELECT * FROM $this->tableName";
        return $this->conexion->getDataAll($sql);
    }

    // Listar y mostrar en Select
    public function select()
    {
        $sql = "SELECT * FROM $this->tableName WHERE condicion=1";
        return $this->conexion->getDataAll($sql);
    }
}
?>
