<?php
require_once __DIR__ . '/../Config/Conexion.php';

class FormaPago {

    private $tableName = 'forma_pago';
    private $conexion;

    public function __construct() {
        $this->conexion = new Conexion();
    }

    public function insertar($nombre, $es_efectivo) {
        $sql = "INSERT INTO $this->tableName (nombre, es_efectivo, condicion) VALUES (?,?,?)";
        $arrData = array($nombre, $es_efectivo, 1);
        return $this->conexion->setData($sql, $arrData);
    }

    public function editar($idforma_pago, $nombre, $es_efectivo) {
        $sql = "UPDATE $this->tableName SET nombre=?, es_efectivo=? WHERE idforma_pago=?";
        $arrData = array($nombre, $es_efectivo, $idforma_pago);
        return $this->conexion->setData($sql, $arrData);
    }

    public function desactivar($idforma_pago) {
        $sql = "UPDATE $this->tableName SET condicion='0' WHERE idforma_pago=?";
        $arrData = array($idforma_pago);
        return $this->conexion->setData($sql, $arrData);
    }

    public function activar($idforma_pago) {
        $sql = "UPDATE $this->tableName SET condicion='1' WHERE idforma_pago=?";
        $arrData = array($idforma_pago);
        return $this->conexion->setData($sql, $arrData);
    }

    public function mostrar($idforma_pago) {
        $sql = "SELECT * FROM $this->tableName WHERE idforma_pago=?";
        $arrData = array($idforma_pago);
        return $this->conexion->getData($sql, $arrData);
    }

    public function listar() {
        $sql = "SELECT * FROM $this->tableName ORDER BY idforma_pago ASC";
        return $this->conexion->getDataAll($sql);
    }

    // ✅ para llenar el SELECT (solo activos)
    public function select() {
        $sql = "SELECT idforma_pago, nombre, es_efectivo
                FROM $this->tableName
                WHERE activo = 1";
        return $this->conexion->getDataAll($sql);
    }
}
