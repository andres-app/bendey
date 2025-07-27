<?php
// Incluir la conexión de base de datos
require_once __DIR__ . '/../Config/Conexion.php';

class Almacen
{
    private $tableName = 'almacen';
    private $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    // Método para insertar un nuevo almacén
    public function insertar($nombre, $ubicacion, $descripcion)
    {
        $sql = "INSERT INTO $this->tableName (nombre, ubicacion, descripcion, estado) VALUES (?, ?, ?, ?)";
        $arrData = array($nombre, $ubicacion, $descripcion, 1);
        return $this->conexion->setData($sql, $arrData);
    }

    // Método para editar un almacén
    public function editar($idalmacen, $nombre, $ubicacion, $descripcion)
    {
        $sql = "UPDATE $this->tableName SET nombre = ?, ubicacion = ?, descripcion = ? WHERE idalmacen = ?";
        $arrData = array($nombre, $ubicacion, $descripcion, $idalmacen);
        return $this->conexion->setData($sql, $arrData);
    }

    // Método para desactivar un almacén
    public function desactivar($idalmacen)
    {
        $sql = "UPDATE $this->tableName SET estado = 0 WHERE idalmacen = ?";
        $arrData = array($idalmacen);
        return $this->conexion->setData($sql, $arrData);
    }

    // Método para activar un almacén
    public function activar($idalmacen)
    {
        $sql = "UPDATE $this->tableName SET estado = 1 WHERE idalmacen = ?";
        $arrData = array($idalmacen);
        return $this->conexion->setData($sql, $arrData);
    }

    // Método para mostrar un único registro
    public function mostrar(string $idalmacen)
    {
        $sql = "SELECT * FROM $this->tableName WHERE idalmacen = ?";
        $arrData = array($idalmacen);
        return $this->conexion->getData($sql, $arrData);
    }

    // Método para listar todos los almacenes
    public function listar()
    {
        $sql = "SELECT * FROM $this->tableName";
        return $this->conexion->getDataAll($sql);
    }

    // Método para listar almacenes activos en un <select>
    public function select()
    {
        $sql = "SELECT * FROM $this->tableName WHERE estado = 1";
        return $this->conexion->getDataAll($sql);
    }
}
?>
