<?php 
//incluir la conexion de base de datos
require_once "Connect.php";
class Voucher{

    private $tableName='comp_pago';
    private $conexion;

	//implementamos nuestro constructor
	public function __construct(){
		$this->conexion = new Conexion();
	}

	//metodo insertar regiustro
    public function insertar($nombre,$letra_serie,$serie_comprobante,$num_comprobante){
        $sql="INSERT INTO $this->tableName (nombre,letra_serie,serie_comprobante,num_comprobante,condicion) VALUES (?,?,?,?,?)";
        $arrData = array($nombre,$letra_serie,$serie_comprobante,$num_comprobante,1);
        return $this->conexion->setData($sql,$arrData);
    }

    public function editar($id_comp_pago,$nombre,$letra_serie,$serie_comprobante,$num_comprobante){
        $sql="UPDATE $this->tableName SET nombre=?,letra_serie=?, serie_comprobante=?, num_comprobante=?  WHERE id_comp_pago=?";
        $arrData = array($nombre,$letra_serie,$serie_comprobante,$num_comprobante,$id_comp_pago);
        return $this->conexion->setData($sql,$arrData);
    }

	public function desactivar($id_comp_pago){
		$sql="UPDATE $this->tableName SET condicion='0' WHERE id_comp_pago=?";
		$arrData = array($id_comp_pago);
		return $this->conexion->setData($sql,$arrData);
	}
    
	public function activar($id_comp_pago){
		$sql="UPDATE $this->tableName SET condicion='1' WHERE id_comp_pago=?";
		$arrData = array($id_comp_pago);
		return $this->conexion->setData($sql,$arrData);
	}

	//metodo para mostrar registros
	public function mostrar(string $id_comp_pago){
		$sql="SELECT * FROM $this->tableName WHERE id_comp_pago=?";
		$arrData = array($id_comp_pago);
		return  $this->conexion->getData($sql,$arrData); 
	}

	//listar registros
	public function listar(){
		$sql="SELECT * FROM $this->tableName";
		return  $this->conexion->getDataAll($sql); 
	}
    //listar y mostrar en selct
    public function select(){
        $sql="SELECT * FROM $this->tableName WHERE condicion=1";
        return  $this->conexion->getDataAll($sql); 
    }

    public function mostrar_serie($tipo_comprobante){
        $sql="SELECT serie_comprobante, num_comprobante, letra_serie FROM comp_pago WHERE nombre='$tipo_comprobante'";
		return  $this->conexion->getDataAll($sql); 
    }
    
    public function mostrar_numero($tipo_comprobante){
        $sql="SELECT num_comprobante FROM comp_pago WHERE nombre='$tipo_comprobante'";
		return  $this->conexion->getDataAll($sql); 
    }

}

