<?php 
//incluir la conexion de base de datos
require_once "Connect.php";
class Person{

    private $tableName='persona';
    private $conexion;

	//implementamos nuestro constructor
	public function __construct(){
		$this->conexion = new Conexion();
	}

	//metodo insertar regiustro
    public function insertar($tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email){
        $sql="INSERT INTO $this->tableName (tipo_persona,nombre,tipo_documento,num_documento,direccion,telefono,email) VALUES (?,?,?,?,?,?,?)";
        $arrData = array($tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email);
        return $this->conexion->setData($sql,$arrData);
    }

    public function editar($idpersona,$tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email){
	$sql="UPDATE $this->tableName SET tipo_persona=?, nombre=?, tipo_documento=?, num_documento=?, direccion=?, telefono=?, email=? WHERE idpersona=?";
        $arrData = array($tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email,$idpersona);
        return $this->conexion->setData($sql,$arrData);
    }
    
	//funcion para eliminar datos
	public function eliminar($idpersona){
		$sql="DELETE FROM $this->tableName WHERE idpersona=?";
		$arrData = array($idpersona);
		return $this->conexion->setData($sql,$arrData);
	}

	//metodo para mostrar registros
	public function mostrar(string $idpersona){
		$sql="SELECT * FROM $this->tableName WHERE idpersona=?";
		$arrData = array($idpersona);
		return  $this->conexion->getData($sql,$arrData); 
	}

	//listar registros
	public function listarp(){
		$sql="SELECT * FROM $this->tableName WHERE tipo_persona='Proveedor'";
		return  $this->conexion->getDataAll($sql); 
	}
	public function listarc(){
		$sql="SELECT * FROM $this->tableName WHERE tipo_persona='Cliente'";
		return  $this->conexion->getDataAll($sql); 
	}

    //listar y mostrar en selct
    public function selectp(){
        $sql="SELECT * FROM $this->tableName WHERE tipo_persona='Proveedor'";
        return  $this->conexion->getDataAll($sql); 
    }

    //listar y mostrar en selct
    public function selectc(){
        $sql="SELECT * FROM $this->tableName WHERE tipo_persona='Cliente'";
        return  $this->conexion->getDataAll($sql); 
    }

}

