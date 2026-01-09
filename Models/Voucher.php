<?php 
// incluir la conexion de base de datos
require_once __DIR__ . '/../Config/Conexion.php';

class Voucher {

    private $tableName = 'comp_pago';
    public $conexion;

    // constructor
    public function __construct() {
        $this->conexion = new Conexion();
    }

    // =========================================
    // CRUD BÁSICO (SE MANTIENE)
    // =========================================

    public function insertar($nombre, $letra_serie, $serie_comprobante, $num_comprobante) {
        $sql = "INSERT INTO $this->tableName 
                (nombre, letra_serie, serie_comprobante, num_comprobante, condicion) 
                VALUES (?,?,?,?,?)";
        $arrData = array($nombre, $letra_serie, $serie_comprobante, $num_comprobante, 1);
        return $this->conexion->setData($sql, $arrData);
    }

    public function editar($id_comp_pago, $nombre, $letra_serie, $serie_comprobante, $num_comprobante) {
        $sql = "UPDATE $this->tableName 
                SET nombre=?, letra_serie=?, serie_comprobante=?, num_comprobante=?  
                WHERE id_comp_pago=?";
        $arrData = array($nombre, $letra_serie, $serie_comprobante, $num_comprobante, $id_comp_pago);
        return $this->conexion->setData($sql, $arrData);
    }

    public function desactivar($id_comp_pago) {
        $sql = "UPDATE $this->tableName SET condicion='0' WHERE id_comp_pago=?";
        return $this->conexion->setData($sql, [$id_comp_pago]);
    }

    public function activar($id_comp_pago) {
        $sql = "UPDATE $this->tableName SET condicion='1' WHERE id_comp_pago=?";
        return $this->conexion->setData($sql, [$id_comp_pago]);
    }

    public function mostrar($id_comp_pago) {
        $sql = "SELECT * FROM $this->tableName WHERE id_comp_pago=?";
        return $this->conexion->getData($sql, [$id_comp_pago]); 
    }

    public function listar() {
        $sql = "SELECT * FROM $this->tableName";
        return $this->conexion->getDataAll($sql); 
    }

    public function select() {
        $sql = "SELECT * FROM $this->tableName WHERE condicion=1";
        return $this->conexion->getDataAll($sql); 
    }

    // =========================================
    // ❌ MÉTODOS ANTIGUOS (NO USAR PARA VENTAS)
    // =========================================
    // Se dejan solo por compatibilidad,
    // pero NO deben usarse para correlativos.

    public function mostrar_serie($tipo_comprobante) {
        $sql = "SELECT serie_comprobante, num_comprobante, letra_serie 
                FROM comp_pago 
                WHERE nombre=?";
        return $this->conexion->getDataAll($sql, [$tipo_comprobante]); 
    }

    public function mostrar_numero($tipo_comprobante) {
        $sql = "SELECT num_comprobante 
                FROM comp_pago 
                WHERE nombre=?";
        return $this->conexion->getDataAll($sql, [$tipo_comprobante]); 
    }

    // ==================================================
    // ✅ MÉTODOS PROFESIONALES (USAR EN VENTAS)
    // ==================================================

    /**
     * 🔐 Obtiene correlativo BLOQUEADO (FOR UPDATE)
     * Se usa DENTRO de una transacción
     */
	public function obtenerCorrelativoBloqueado($tipo_comprobante)
	{
		$sql = "SELECT 
					id_comp_pago,
					CONCAT(letra_serie, serie_comprobante) AS serie,
					num_comprobante
				FROM comp_pago
				WHERE nombre = ?
				AND condicion = 1
				FOR UPDATE";
	
		// getData() ya devuelve UNA fila
		return $this->conexion->getData($sql, [$tipo_comprobante]);
	}
	
	

    /**
     * 🔄 Actualiza el correlativo después de una venta exitosa
     */
    public function actualizarCorrelativoPorId($id_comp_pago, $numero) {
        $sql = "UPDATE comp_pago
                SET num_comprobante = ?
                WHERE id_comp_pago = ?";

        return $this->conexion->setData($sql, [$numero, $id_comp_pago]);
    }

}
