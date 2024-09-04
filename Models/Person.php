<?php
// Incluir la conexión a la base de datos
require_once "Connect.php";

class Person
{

    private $tableName = 'persona';
    private $conexion;
    private $apiKey = 'cGVydWRldnMucHJvZHVjdGlvbi5maXRjb2RlcnMuNjZkODY3NmU5ZmE0MTczZjYxMzIwMzU1'; // Reemplaza con tu clave de API DE PERUDEVS TOKENhttps://admin.perudevs.com/

    // Implementamos el constructor
    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    // Método para insertar registros
    public function insertar($tipo_persona, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email)
    {
        $sql = "INSERT INTO $this->tableName (tipo_persona, nombre, tipo_documento, num_documento, direccion, telefono, email) VALUES (?,?,?,?,?,?,?)";
        $arrData = array($tipo_persona, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email);
        return $this->conexion->setData($sql, $arrData);
    }

    // Método para editar registros
    public function editar($idpersona, $tipo_persona, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email)
    {
        $sql = "UPDATE $this->tableName SET tipo_persona=?, nombre=?, tipo_documento=?, num_documento=?, direccion=?, telefono=?, email=? WHERE idpersona=?";
        $arrData = array($tipo_persona, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email, $idpersona);
        return $this->conexion->setData($sql, $arrData);
    }

    // Método para eliminar registros
    public function eliminar($idpersona)
    {
        $sql = "DELETE FROM $this->tableName WHERE idpersona=?";
        $arrData = array($idpersona);
        return $this->conexion->setData($sql, $arrData);
    }

    // Método para mostrar registros
    public function mostrar(string $idpersona)
    {
        $sql = "SELECT * FROM $this->tableName WHERE idpersona=?";
        $arrData = array($idpersona);
        return $this->conexion->getData($sql, $arrData);
    }

    // Listar registros de proveedores
    public function listarp()
    {
        $sql = "SELECT * FROM $this->tableName WHERE tipo_persona='Proveedor'";
        return $this->conexion->getDataAll($sql);
    }

    // Listar registros de clientes
    public function listarc()
    {
        $sql = "SELECT * FROM $this->tableName WHERE tipo_persona='Cliente'";
        return $this->conexion->getDataAll($sql);
    }

    // Seleccionar proveedores para un select
    public function selectp()
    {
        $sql = "SELECT * FROM $this->tableName WHERE tipo_persona='Proveedor'";
        return $this->conexion->getDataAll($sql);
    }

    // Seleccionar clientes para un select
    public function selectc()
    {
        $sql = "SELECT * FROM $this->tableName WHERE tipo_persona='Cliente'";
        return $this->conexion->getDataAll($sql);
    }

    // Método para obtener información del cliente desde la API
    public function getCustomerInfo($document, $type)
    {
        $baseUrl = "https://api.perudevs.com/api/v1/";
        $url = ($type === "DNI") ? "{$baseUrl}dni/simple?document=$document&key=$this->apiKey" : "{$baseUrl}ruc?document=$document&key=$this->apiKey";
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    
        $response = curl_exec($ch);
    
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return json_encode(['estado' => false, 'mensaje' => 'Error en cURL: ' . $error_msg]);
        }
    
        curl_close($ch);
    
        $data = json_decode($response, true);
    
        if (!$data || !isset($data['estado'])) {
            return json_encode(['estado' => false, 'mensaje' => 'Respuesta inválida o error de API']);
        }
    
        if ($data['estado']) {
            // Ajuste para manejar la respuesta de DNI
            if ($type === "DNI") {
                $nombreCompleto = $data['resultado']['nombre_completo'];
                return json_encode(['estado' => true, 'resultado' => ['nombre' => $nombreCompleto, 'direccion' => '']]);
            } else {
                return json_encode(['estado' => true, 'resultado' => $data['resultado']]);
            }
        } else {
            return json_encode(['estado' => false, 'mensaje' => 'Documento no encontrado', 'detalle' => $data['mensaje'] ?? 'Sin detalles adicionales']);
        }
    }
    
}