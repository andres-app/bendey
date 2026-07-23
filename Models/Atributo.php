<?php
require_once __DIR__ . '/../Config/Conexion.php';

class Atributo
{
    private $tableName = 'atributo';
    private $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    public function insertar($nombre, $descripcion)
    {
        $sql = "INSERT INTO {$this->tableName} (nombre, descripcion, estado) VALUES (?, ?, 1)";
        return $this->conexion->setData($sql, [$nombre, $descripcion]);
    }

    public function editar($idatributo, $nombre, $descripcion)
    {
        $sql = "UPDATE {$this->tableName}
                SET nombre = ?, descripcion = ?
                WHERE idatributo = ?";

        return $this->conexion->setData($sql, [$nombre, $descripcion, $idatributo]);
    }

    public function desactivar($idatributo)
    {
        $sql = "UPDATE {$this->tableName} SET estado = 0 WHERE idatributo = ?";
        return $this->conexion->setData($sql, [$idatributo]);
    }

    public function activar($idatributo)
    {
        $sql = "UPDATE {$this->tableName} SET estado = 1 WHERE idatributo = ?";
        return $this->conexion->setData($sql, [$idatributo]);
    }

    public function mostrar($idatributo)
    {
        $sql = "SELECT idatributo, nombre, descripcion, estado
                FROM {$this->tableName}
                WHERE idatributo = ?
                LIMIT 1";

        return $this->conexion->getData($sql, [$idatributo]);
    }

    public function listar()
    {
        $sql = "SELECT idatributo, nombre, descripcion, estado
                FROM {$this->tableName}
                ORDER BY idatributo DESC";

        return $this->conexion->getDataAll($sql);
    }

    public function select()
    {
        $sql = "SELECT idatributo, nombre
                FROM {$this->tableName}
                WHERE estado = 1
                ORDER BY nombre ASC";

        return $this->conexion->getDataAll($sql);
    }

    public function existeNombre($nombre, $idatributoExcluir = 0)
    {
        $sql = "SELECT idatributo
                FROM {$this->tableName}
                WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?))
                  AND idatributo <> ?
                LIMIT 1";

        $registro = $this->conexion->getData($sql, [$nombre, $idatributoExcluir]);
        return !empty($registro);
    }

    /**
     * Formato para Select2 u otros selectores:
     * [{"id": 1, "text": "Rojo"}, ...]
     */
    public function listarValores($idatributo)
    {
        $sql = "SELECT av.idvalor AS id, av.valor AS text
                FROM atributo_valor av
                INNER JOIN {$this->tableName} a ON a.idatributo = av.idatributo
                WHERE av.idatributo = ?
                  AND av.estado = 1
                  AND a.estado = 1
                ORDER BY av.valor ASC";

        return $this->conexion->getDataAll($sql, [$idatributo]);
    }
}
