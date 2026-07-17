<?php
require_once __DIR__ . '/../Config/Conexion.php';

class Paymentformat
{
    private $tableName = 'forma_pago';
    private $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    /* ===============================
       INSERTAR
    =============================== */
    public function insertar($nombre, $es_efectivo, $condicion)
    {
        $sql = "INSERT INTO {$this->tableName}
                (nombre, es_efectivo, activo, condicion)
                VALUES (?, ?, 1, ?)";
        $arrData = [$nombre, $es_efectivo, $condicion];
        return $this->conexion->setData($sql, $arrData);
    }

    /* ===============================
       EDITAR
    =============================== */
    public function editar($idforma_pago, $nombre, $es_efectivo, $condicion)
    {
        $sql = "UPDATE {$this->tableName}
                SET nombre = ?, es_efectivo = ?, condicion = ?
                WHERE idforma_pago = ?";
        $arrData = [$nombre, $es_efectivo, $condicion, $idforma_pago];
        return $this->conexion->setData($sql, $arrData);
    }

    /* ===============================
       ACTIVAR / DESACTIVAR
    =============================== */
    public function activar($idforma_pago)
    {
        $sql = "UPDATE {$this->tableName} SET activo = 1 WHERE idforma_pago = ?";
        return $this->conexion->setData($sql, [$idforma_pago]);
    }

    public function desactivar($idforma_pago)
    {
        $sql = "UPDATE {$this->tableName} SET activo = 0 WHERE idforma_pago = ?";
        return $this->conexion->setData($sql, [$idforma_pago]);
    }

    /* ===============================
       MOSTRAR
    =============================== */
    public function mostrar($idforma_pago)
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE idforma_pago = ?";
        return $this->conexion->getData($sql, [$idforma_pago]);
    }

    /* ===============================
       LISTAR
    =============================== */
    public function listar()
    {
        $sql = "SELECT * FROM {$this->tableName}";
        return $this->conexion->getDataAll($sql);
    }

    /* ===============================
       SELECT PARA COMBOS
    =============================== */
    public function select()
    {
        $sql = "SELECT
                idforma_pago,
                nombre,
                es_efectivo,
                es_combinado,
                condicion
            FROM {$this->tableName}
            WHERE activo = 1
            ORDER BY es_combinado ASC,
                     es_efectivo DESC,
                     nombre ASC";

        return $this->conexion->getDataAll($sql);
    }

    /* ===============================
    FORMAS DISPONIBLES PARA PAGO MIXTO
    =============================== */
    public function selectParaPagoMixto()
    {
        $sql = "SELECT
                idforma_pago,
                nombre,
                es_efectivo,
                condicion
            FROM {$this->tableName}
            WHERE activo = 1
              AND condicion = 1
              AND es_combinado = 0
            ORDER BY es_efectivo DESC,
                     nombre ASC";

        return $this->conexion->getDataAll($sql);
    }
}
