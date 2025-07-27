<?php
// Incluir la conexión a la base de datos
require_once __DIR__ . '/../Config/Conexion.php';

class Person
{

    private $tableName = 'persona';
    private $conexion;
    private $apiKey;

    // Implementamos el constructor
    public function __construct()
    {
        $this->conexion = new Conexion();
        $this->apiKey = $this->obtenerApiKey();  // Obtener el token desde la base de datos
    }

    // Método para obtener el API Key desde la base de datos
    private function obtenerApiKey()
    {
        $sql = "SELECT token_reniec_sunat FROM datos_negocio LIMIT 1";
        $resultado = $this->conexion->getData($sql, []);
        // Quita espacios antes de devolver
        return isset($resultado['token_reniec_sunat']) ? trim($resultado['token_reniec_sunat']) : '';
    }

    // Método para insertar registros
    public function insertar($tipo_persona, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email)
    {
        $sql = "INSERT INTO persona (tipo_persona, nombre, tipo_documento, num_documento, direccion, telefono, email) VALUES (?,?,?,?,?,?,?)";
        $arrData = array($tipo_persona, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email);

        // Asegúrate de que estás obteniendo el ID del cliente recién insertado
        return $this->conexion->getReturnId($sql, $arrData);  // Devuelve el idpersona recién creado
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
        // Eliminar espacios en blanco
        $document = trim($document);

        // Validar tipo y formato del documento
        if (!$document || !in_array($type, ["DNI", "RUC"])) {
            return json_encode([
                'estado' => false,
                'mensaje' => 'Tipo de documento o número de documento inválido.'
            ]);
        }
        if (!ctype_digit($document)) {
            return json_encode([
                'estado' => false,
                'mensaje' => 'El número de documento debe ser numérico.'
            ]);
        }
        if ($type === "DNI" && strlen($document) != 8) {
            return json_encode([
                'estado' => false,
                'mensaje' => 'El DNI debe tener 8 dígitos.'
            ]);
        }
        if ($type === "RUC" && strlen($document) != 11) {
            return json_encode([
                'estado' => false,
                'mensaje' => 'El RUC debe tener 11 dígitos.'
            ]);
        }

        // Armamos la URL solo si los datos son válidos
        $baseUrl = "https://api.perudevs.com/api/v1/";
        $url = ($type === "DNI")
            ? "{$baseUrl}dni/simple?document=$document&key=$this->apiKey"
            : "{$baseUrl}ruc?document=$document&key=$this->apiKey";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return json_encode([
                'estado' => false,
                'mensaje' => 'Error en cURL: ' . $error_msg
            ]);
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if (!$data) {
            return json_encode([
                'estado' => false,
                'mensaje' => 'Sin respuesta de la API o respuesta no válida.',
                'raw' => $response
            ]);
        }

        if (!isset($data['estado'])) {
            return json_encode([
                'estado' => false,
                'mensaje' => 'Respuesta de API sin campo estado.',
                'api_response' => $data
            ]);
        }

        if ($data['estado']) {
            // Para DNI, la API devuelve 'nombre_completo'
            if ($type === "DNI") {
                $nombreCompleto = isset($data['resultado']['nombre_completo'])
                    ? $data['resultado']['nombre_completo']
                    : '';
                return json_encode([
                    'estado' => true,
                    'resultado' => [
                        'nombre' => $nombreCompleto,
                        'direccion' => ''
                    ]
                ]);
            } else {
                // Para RUC, la API devuelve datos de empresa
                return json_encode([
                    'estado' => true,
                    'resultado' => $data['resultado']
                ]);
            }
        } else {
            // Siempre retorna mensaje
            return json_encode([
                'estado' => false,
                'mensaje' => isset($data['mensaje']) ? $data['mensaje'] : 'Documento no encontrado o error desconocido en la API.',
                'detalle' => $data
            ]);
        }
    }




    public function mostrarPorDocumento($num_documento)
    {
        $sql = "SELECT * FROM persona WHERE num_documento = ?";
        $arrData = array($num_documento);
        return $this->conexion->getData($sql, $arrData); // Asume que este método devuelve los datos
    }


}