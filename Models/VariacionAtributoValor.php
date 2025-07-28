<?php
require_once "../Config/Conexion.php";

class VariacionAtributoValor
{
    private $table = 'variacion_atributo_valor';
    private $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    // Insertar relación entre variación y valor
    public function insertar($idvariacion, $idvalor)
    {
        $sql = "INSERT INTO $this->table (idvariacion, idvalor) VALUES (?, ?)";
        return $this->conexion->setData($sql, array($idvariacion, $idvalor));
    }

    // Listar atributos de una variación
    public function listarPorVariacion($idvariacion)
    {
        $sql = "SELECT av.idvalor, v.valor, a.nombre AS atributo
                FROM variacion_atributo_valor av
                INNER JOIN atributo_valor v ON av.idvalor = v.idvalor
                INNER JOIN atributo a ON v.idatributo = a.idatributo
                WHERE av.idvariacion = ?";
        return $this->conexion->getData($sql, array($idvariacion));
    }

    // Eliminar todos los valores de una variación
    public function eliminarPorVariacion($idvariacion)
    {
        $sql = "DELETE FROM $this->table WHERE idvariacion = ?";
        return $this->conexion->setData($sql, array($idvariacion));
    }
}
