<?php
// Incluir la conexión de base de datos
require_once __DIR__ . '/../Config/Conexion.php';

class Atributo
{
    private $tableName = 'atributo';
    private $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    // Insertar nuevo atributo
    public function insertar($nombre, $descripcion)
    {
        $sql = "INSERT INTO $this->tableName (nombre, descripcion, estado) VALUES (?, ?, ?)";
        $arrData = array($nombre, $descripcion, 1);
        return $this->conexion->setData($sql, $arrData);
    }

    // Editar atributo existente
    public function editar($idatributo, $nombre, $descripcion)
    {
        $sql = "UPDATE $this->tableName SET nombre = ?, descripcion = ? WHERE idatributo = ?";
        $arrData = array($nombre, $descripcion, $idatributo);
        return $this->conexion->setData($sql, $arrData);
    }

    // Desactivar atributo
    public function desactivar($idatributo)
    {
        $sql = "UPDATE $this->tableName SET estado = 0 WHERE idatributo = ?";
        $arrData = array($idatributo);
        return $this->conexion->setData($sql, $arrData);
    }

    // Activar atributo
    public function activar($idatributo)
    {
        $sql = "UPDATE $this->tableName SET estado = 1 WHERE idatributo = ?";
        $arrData = array($idatributo);
        return $this->conexion->setData($sql, $arrData);
    }

    // Mostrar un solo atributo por ID
    public function mostrar($idatributo)
    {
        $sql = "SELECT * FROM $this->tableName WHERE idatributo = ?";
        $arrData = array($idatributo);
        return $this->conexion->getData($sql, $arrData);
    }

    // Listar todos los atributos
    public function listar()
    {
        $sql = "SELECT * FROM $this->tableName";
        return $this->conexion->getDataAll($sql);
    }

    // Listar para select (solo activos)
    public function select()
    {
        $sql = "SELECT * FROM $this->tableName WHERE estado = 1";
        return $this->conexion->getDataAll($sql);
    }
}
