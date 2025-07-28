<?php
require_once __DIR__ . '/../Config/Conexion.php';

class AtributoValor {
    private $table = "atributo_valor";
    private $conexion;

    public function __construct() {
        $this->conexion = new Conexion();
    }

    public function insertar($idatributo, $valor) {
        $sql = "INSERT INTO $this->table (idatributo, valor, estado) VALUES (?, ?, 1)";
        return $this->conexion->setData($sql, [$idatributo, $valor]);
    }

    public function editar($idvalor, $valor) {
        $sql = "UPDATE $this->table SET valor = ? WHERE idvalor = ?";
        return $this->conexion->setData($sql, [$valor, $idvalor]);
    }

    public function desactivar($idvalor) {
        $sql = "UPDATE $this->table SET estado = 0 WHERE idvalor = ?";
        return $this->conexion->setData($sql, [$idvalor]);
    }

    public function activar($idvalor) {
        $sql = "UPDATE $this->table SET estado = 1 WHERE idvalor = ?";
        return $this->conexion->setData($sql, [$idvalor]);
    }

    public function listarPorAtributo($idatributo) {
        $sql = "SELECT * FROM $this->table WHERE idatributo = ?";
        return $this->conexion->getDataAll($sql, [$idatributo]);
    }

    public function mostrar($idvalor) {
        $sql = "SELECT * FROM $this->table WHERE idvalor = ?";
        return $this->conexion->getData($sql, [$idvalor]);
    }
}
