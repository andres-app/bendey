<?php
require_once __DIR__ . '/../Config/Conexion.php'; // asegúrate que esta ruta es correcta

class Subcategoria {
    private $conexion;

    public function __construct() {
        $this->conexion = new Conexion();
    }

    public function listarPorCategoria($categoria_id) {
        $sql = "SELECT idsubcategoria, nombre FROM subcategoria WHERE idcategoria = ? AND estado = 1";
        return $this->conexion->getDataAll($sql, [$categoria_id]);
    }
}
?>
