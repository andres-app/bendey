<?php 
//incluir la conexion de base de datos
require_once __DIR__ . '/../Config/Conexion.php';
class Company
{
    private $tableName = 'datos_negocio';
    private $conexion;

    // Implementamos nuestro constructor
    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    // Método para editar los registros
    public function editar($id_negocio, $nombre, $ndocumento, $documento, $direccion, $telefono, $email, $logo, $pais, $ciudad, $nombre_impuesto, $monto_impuesto, $moneda, $simbolo, $token_reniec_sunat) {
        $sql = "UPDATE $this->tableName SET nombre=?, ndocumento=?, documento=?, direccion=?, telefono=?, email=?, logo=?, pais=?, ciudad=?, nombre_impuesto=?, monto_impuesto=?, moneda=?, simbolo=?, token_reniec_sunat=? WHERE id_negocio=?";
        $arrData = array($nombre, $ndocumento, $documento, $direccion, $telefono, $email, $logo, $pais, $ciudad, $nombre_impuesto, $monto_impuesto, $moneda, $simbolo, $token_reniec_sunat, $id_negocio);
        return $this->conexion->setData($sql, $arrData);
    }

    // Método para mostrar registros
    public function mostrar(string $id_negocio)
    {
        $sql = "SELECT * FROM $this->tableName WHERE id_negocio=?";
        $arrData = array($id_negocio);
        return $this->conexion->getData($sql, $arrData);
    }

    public function mostrar_impuesto()
    {
        $sql = "SELECT monto_impuesto FROM datos_negocio";
        return $this->conexion->getDataAll($sql);
    }

    public function mostrar_simbolo()
    {
        $sql = "SELECT simbolo FROM datos_negocio";
        return $this->conexion->getDataAll($sql);
    }

    public function nombre_impuesto()
    {
        $sql = "SELECT nombre_impuesto FROM datos_negocio";
        return $this->conexion->getDataAll($sql);
    }

    // Método para listar registros
    public function listar()
    {
        $sql = "SELECT * FROM $this->tableName";
        return $this->conexion->getDataAll($sql);
    }

    // Método para obtener el token actual
    public function obtenerToken()
    {
        $sql = "SELECT token_reniec_sunat FROM $this->tableName WHERE id_negocio = 1"; // Suponiendo que siempre se usará el id_negocio = 1
        $resultado = $this->conexion->getData($sql, []);
        return $resultado['token_reniec_sunat'] ?? ''; // Retorna el token o una cadena vacía si no se encuentra
    }

    // Método para actualizar el token
    public function actualizarToken($nuevoToken)
    {
        $sql = "UPDATE $this->tableName SET token_reniec_sunat = ? WHERE id_negocio = 1"; // Suponiendo que siempre se usará el id_negocio = 1
        return $this->conexion->setData($sql, [$nuevoToken]);
    }
}
