<?php
require_once __DIR__ . '/../Config/Conexion.php';

class Subcategoria {

    private $conexion;

    public function __construct() {
        $this->conexion = new Conexion();
    }

    public function insertar($idcategoria, $nombre) {
        $sql = "INSERT INTO subcategoria (idcategoria, nombre, estado)
                VALUES (?, ?, 1)";
        return $this->conexion->setData($sql, [$idcategoria, $nombre]);
    }

    public function listarPorCategoria($idcategoria) {
        $sql = "SELECT * FROM subcategoria
                WHERE idcategoria = ?";
        return $this->conexion->getDataAll($sql, [$idcategoria]);
    }

    public function activar($idsubcategoria) {
        $sql = "UPDATE subcategoria SET estado = 1
                WHERE idsubcategoria = ?";
        return $this->conexion->setData($sql, [$idsubcategoria]);
    }

    public function desactivar($idsubcategoria) {
        $sql = "UPDATE subcategoria SET estado = 0
                WHERE idsubcategoria = ?";
        return $this->conexion->setData($sql, [$idsubcategoria]);
    }
}
