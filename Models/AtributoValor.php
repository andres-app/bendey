<?php
require_once __DIR__ . '/../Config/Conexion.php';

class AtributoValor
{
    private $table = 'atributo_valor';
    private $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    public function insertar($idatributo, $valor)
    {
        $sql = "INSERT INTO {$this->table} (idatributo, valor, estado)
                VALUES (?, ?, 1)";

        return $this->conexion->setData($sql, [$idatributo, $valor]);
    }

    public function editar($idvalor, $valor)
    {
        $sql = "UPDATE {$this->table}
                SET valor = ?
                WHERE idvalor = ?";

        return $this->conexion->setData($sql, [$valor, $idvalor]);
    }

    public function desactivar($idvalor)
    {
        $sql = "UPDATE {$this->table} SET estado = 0 WHERE idvalor = ?";
        return $this->conexion->setData($sql, [$idvalor]);
    }

    public function activar($idvalor)
    {
        $sql = "UPDATE {$this->table} SET estado = 1 WHERE idvalor = ?";
        return $this->conexion->setData($sql, [$idvalor]);
    }

    public function listarPorAtributo($idatributo)
    {
        $sql = "SELECT idvalor, idatributo, valor, estado
                FROM {$this->table}
                WHERE idatributo = ?
                ORDER BY valor ASC, idvalor ASC";

        return $this->conexion->getDataAll($sql, [$idatributo]);
    }

    public function listarActivosPorAtributo($idatributo)
    {
        $sql = "SELECT av.idvalor, av.idatributo, av.valor
                FROM {$this->table} av
                INNER JOIN atributo a ON a.idatributo = av.idatributo
                WHERE av.idatributo = ?
                  AND av.estado = 1
                  AND a.estado = 1
                ORDER BY av.valor ASC";

        return $this->conexion->getDataAll($sql, [$idatributo]);
    }

    public function mostrar($idvalor)
    {
        $sql = "SELECT idvalor, idatributo, valor, estado
                FROM {$this->table}
                WHERE idvalor = ?
                LIMIT 1";

        return $this->conexion->getData($sql, [$idvalor]);
    }

    public function atributoExiste($idatributo)
    {
        $sql = "SELECT idatributo
                FROM atributo
                WHERE idatributo = ?
                LIMIT 1";

        $registro = $this->conexion->getData($sql, [$idatributo]);
        return !empty($registro);
    }

    public function existeValor($idatributo, $valor, $idvalorExcluir = 0)
    {
        $sql = "SELECT idvalor
                FROM {$this->table}
                WHERE idatributo = ?
                  AND LOWER(TRIM(valor)) = LOWER(TRIM(?))
                  AND idvalor <> ?
                LIMIT 1";

        $registro = $this->conexion->getData(
            $sql,
            [$idatributo, $valor, $idvalorExcluir]
        );

        return !empty($registro);
    }
}
