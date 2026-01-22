<?php
// incluir la conexión a la base de datos
require_once __DIR__ . '/../Config/Conexion.php';

class Company
{
    private $tableName = 'datos_negocio';
    private $conexion;

    // Constructor
    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    // ============================
    // EDITAR DATOS DEL NEGOCIO
    // ============================
    public function editar(
        $id_negocio,
        $nombre,
        $ndocumento,
        $documento,
        $direccion,
        $telefono,
        $email,
        $pais,
        $ciudad,
        $nombre_impuesto,
        $monto_impuesto,
        $moneda,
        $simbolo,
        $token_reniec_sunat
    ) {
        $sql = "UPDATE {$this->tableName} 
                SET nombre = ?, 
                    ndocumento = ?, 
                    documento = ?, 
                    direccion = ?, 
                    telefono = ?, 
                    email = ?, 
                    pais = ?, 
                    ciudad = ?, 
                    nombre_impuesto = ?, 
                    monto_impuesto = ?, 
                    moneda = ?, 
                    simbolo = ?, 
                    token_reniec_sunat = ?
                WHERE id_negocio = ?";

        $arrData = [
            $nombre,
            $ndocumento,
            $documento,
            $direccion,
            $telefono,
            $email,
            $pais,
            $ciudad,
            $nombre_impuesto,
            $monto_impuesto,
            $moneda,
            $simbolo,
            $token_reniec_sunat,
            $id_negocio
        ];

        return $this->conexion->setData($sql, $arrData);
    }

    // ============================
    // MOSTRAR UN REGISTRO
    // ============================
    public function mostrar($id_negocio)
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE id_negocio = ?";
        return $this->conexion->getData($sql, [$id_negocio]);
    }

    // ============================
    // IMPUESTO
    // ============================
    public function mostrar_impuesto()
    {
        $sql = "SELECT monto_impuesto FROM {$this->tableName}";
        return $this->conexion->getDataAll($sql);
    }

    public function nombre_impuesto()
    {
        $sql = "SELECT nombre_impuesto FROM {$this->tableName}";
        return $this->conexion->getDataAll($sql);
    }

    // ============================
    // SIMBOLO MONEDA
    // ============================
    public function mostrar_simbolo()
    {
        $sql = "SELECT simbolo FROM {$this->tableName}";
        return $this->conexion->getDataAll($sql);
    }

    // ============================
    // LISTAR
    // ============================
    public function listar()
    {
        $sql = "SELECT * FROM {$this->tableName}";
        return $this->conexion->getDataAll($sql);
    }

    // ============================
    // TOKEN RENIEC / SUNAT
    // ============================
    public function obtenerToken()
    {
        $sql = "SELECT token_reniec_sunat 
                FROM {$this->tableName} 
                WHERE id_negocio = 1";

        $resultado = $this->conexion->getData($sql, []);
        return $resultado['token_reniec_sunat'] ?? '';
    }

    public function actualizarToken($nuevoToken)
    {
        $sql = "UPDATE {$this->tableName} 
                SET token_reniec_sunat = ? 
                WHERE id_negocio = 1";

        return $this->conexion->setData($sql, [$nuevoToken]);
    }
}
