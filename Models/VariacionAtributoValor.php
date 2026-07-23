<?php
require_once __DIR__ . '/../Config/Conexion.php';

class VariacionAtributoValor
{
    private $table = 'variacion_atributo_valor';
    private $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    public function insertar($idvariacion, $idvalor)
    {
        if ($this->existeRelacion($idvariacion, $idvalor)) {
            return true;
        }

        $sql = "INSERT INTO {$this->table} (idvariacion, idvalor)
                VALUES (?, ?)";

        return $this->conexion->setData($sql, [$idvariacion, $idvalor]);
    }

    public function existeRelacion($idvariacion, $idvalor)
    {
        $sql = "SELECT idvariacion
                FROM {$this->table}
                WHERE idvariacion = ? AND idvalor = ?
                LIMIT 1";

        $registro = $this->conexion->getData($sql, [$idvariacion, $idvalor]);
        return !empty($registro);
    }

    public function listarPorVariacion($idvariacion)
    {
        $sql = "SELECT
                    vav.idvariacion,
                    av.idvalor,
                    av.valor,
                    av.estado AS estado_valor,
                    a.idatributo,
                    a.nombre AS atributo,
                    a.estado AS estado_atributo
                FROM {$this->table} vav
                INNER JOIN atributo_valor av ON av.idvalor = vav.idvalor
                INNER JOIN atributo a ON a.idatributo = av.idatributo
                WHERE vav.idvariacion = ?
                ORDER BY a.nombre ASC, av.valor ASC";

        return $this->conexion->getDataAll($sql, [$idvariacion]);
    }

    public function eliminarPorVariacion($idvariacion)
    {
        $sql = "DELETE FROM {$this->table} WHERE idvariacion = ?";
        return $this->conexion->setData($sql, [$idvariacion]);
    }

    public function eliminarRelacion($idvariacion, $idvalor)
    {
        $sql = "DELETE FROM {$this->table}
                WHERE idvariacion = ? AND idvalor = ?";

        return $this->conexion->setData($sql, [$idvariacion, $idvalor]);
    }
}
