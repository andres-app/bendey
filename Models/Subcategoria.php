<?php
require_once "../config/conexion.php";

class Subcategoria {
    public function listarPorCategoria($categoria_id) {
        $sql = "SELECT idsubcategoria, nombre FROM subcategoria WHERE idcategoria = '$categoria_id' AND estado = 1";
        return ejecutarConsulta($sql);
    }
}
?>
