<?php

require_once __DIR__ . '/../Config/Conexion.php';

class Voucher
{
    private $tableName = 'comp_pago';
    public $conexion;

    /**
     * Permite utilizar una conexión existente.
     *
     * Para registrar ventas debemos pasar la misma conexión
     * utilizada por Sell, de modo que FOR UPDATE forme parte
     * de la misma transacción.
     */
    public function __construct($conexion = null)
    {
        if ($conexion instanceof Conexion) {
            $this->conexion = $conexion;
        } else {
            $this->conexion = new Conexion();
        }
    }

    // =========================================
    // CRUD BÁSICO
    // =========================================

    public function insertar(
        $nombre,
        $letra_serie,
        $serie_comprobante,
        $num_comprobante
    ) {
        $sql = "INSERT INTO {$this->tableName}
                (
                    nombre,
                    letra_serie,
                    serie_comprobante,
                    num_comprobante,
                    condicion
                )
                VALUES (?, ?, ?, ?, ?)";

        return $this->conexion->setData($sql, [
            $nombre,
            $letra_serie,
            $serie_comprobante,
            $num_comprobante,
            1
        ]);
    }

    public function editar(
        $id_comp_pago,
        $nombre,
        $letra_serie,
        $serie_comprobante,
        $num_comprobante
    ) {
        $sql = "UPDATE {$this->tableName}
                SET
                    nombre = ?,
                    letra_serie = ?,
                    serie_comprobante = ?,
                    num_comprobante = ?
                WHERE id_comp_pago = ?";

        return $this->conexion->setData($sql, [
            $nombre,
            $letra_serie,
            $serie_comprobante,
            $num_comprobante,
            $id_comp_pago
        ]);
    }

    public function desactivar($id_comp_pago)
    {
        $sql = "UPDATE {$this->tableName}
                SET condicion = 0
                WHERE id_comp_pago = ?";

        return $this->conexion->setData($sql, [$id_comp_pago]);
    }

    public function activar($id_comp_pago)
    {
        $sql = "UPDATE {$this->tableName}
                SET condicion = 1
                WHERE id_comp_pago = ?";

        return $this->conexion->setData($sql, [$id_comp_pago]);
    }

    public function mostrar($id_comp_pago)
    {
        $sql = "SELECT *
                FROM {$this->tableName}
                WHERE id_comp_pago = ?
                LIMIT 1";

        return $this->conexion->getData($sql, [$id_comp_pago]);
    }

    public function listar()
    {
        $sql = "SELECT *
                FROM {$this->tableName}
                ORDER BY id_comp_pago";

        return $this->conexion->getDataAll($sql);
    }

    public function select()
    {
        $sql = "SELECT *
                FROM {$this->tableName}
                WHERE condicion = 1
                ORDER BY id_comp_pago";

        return $this->conexion->getDataAll($sql);
    }

    // =========================================
    // MÉTODOS ANTIGUOS
    // =========================================

    public function mostrar_serie($tipo_comprobante)
    {
        $sql = "SELECT
                    serie_comprobante,
                    num_comprobante,
                    letra_serie
                FROM {$this->tableName}
                WHERE nombre = ?
                  AND condicion = 1
                ORDER BY id_comp_pago
                LIMIT 1";

        $registro = $this->conexion->getData(
            $sql,
            [$tipo_comprobante]
        );

        return $registro ? [$registro] : [];
    }

    public function mostrar_numero($tipo_comprobante)
    {
        $sql = "SELECT num_comprobante
                FROM {$this->tableName}
                WHERE nombre = ?
                  AND condicion = 1
                ORDER BY id_comp_pago
                LIMIT 1";

        $registro = $this->conexion->getData(
            $sql,
            [$tipo_comprobante]
        );

        return $registro ? [$registro] : [];
    }

    // =========================================
    // CORRELATIVOS TRANSACCIONALES
    // =========================================

    /**
     * Obtiene y bloquea el correlativo.
     *
     * Este método debe ejecutarse después de beginTransaction()
     * y utilizando la misma conexión que registra la venta.
     */
    public function obtenerCorrelativoBloqueado($tipo_comprobante)
    {
        $sql = "SELECT
                    id_comp_pago,
                    nombre,
                    CONCAT(letra_serie, serie_comprobante) AS serie,
                    num_comprobante
                FROM {$this->tableName}
                WHERE nombre = ?
                  AND condicion = 1
                ORDER BY id_comp_pago
                LIMIT 1
                FOR UPDATE";

        return $this->conexion->getData(
            $sql,
            [$tipo_comprobante]
        );
    }

    /**
     * Actualiza el último correlativo utilizado.
     */
    public function actualizarCorrelativoPorId(
        $id_comp_pago,
        $numero
    ) {
        $sql = "UPDATE {$this->tableName}
                SET num_comprobante = ?
                WHERE id_comp_pago = ?
                  AND condicion = 1";

        return $this->conexion->setData($sql, [
            $numero,
            $id_comp_pago
        ]);
    }
}