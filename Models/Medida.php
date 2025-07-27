<?php 
//incluir la conexion de base de datos
require_once __DIR__ . '/../Config/Conexion.php';
class Medida{

    private $tableName='medida';
    private $conexion;

	//implementamos nuestro constructor
	public function __construct(){
		$this->conexion = new Conexion();
	}

	//metodo insertar regiustro
    public function insertar($codigo,$nombre){
        $sql="INSERT INTO $this->tableName (codigo,nombre,condicion) VALUES (?,?,?)";
        $arrData = array($codigo,$nombre,1);
        return $this->conexion->setData($sql,$arrData);
    }

    public function editar($idmedida,$codigo,$nombre){
        $sql="UPDATE $this->tableName SET codigo=?,nombre=? WHERE idmedida=?";
        $arrData = array($codigo,$nombre,$idmedida);
        return $this->conexion->setData($sql,$arrData);
    }

	public function desactivar($idmedida){
		$sql="UPDATE $this->tableName SET condicion='0' WHERE idmedida=?";
		$arrData = array($idmedida);
		return $this->conexion->setData($sql,$arrData);
	}
    
	public function activar($idmedida){
		$sql="UPDATE $this->tableName SET condicion='1' WHERE idmedida=?";
		$arrData = array($idmedida);
		return $this->conexion->setData($sql,$arrData);
	}

	//metodo para mostrar registros
	public function mostrar(string $idmedida){
		$sql="SELECT * FROM $this->tableName WHERE idmedida=?";
		$arrData = array($idmedida);
		return  $this->conexion->getData($sql,$arrData); 
	}

	//listar registros
	public function listar(){
		$sql="SELECT * FROM $this->tableName";
		return  $this->conexion->getDataAll($sql); 
	}
    //listar y mostrar en Select
    public function select(){
        $sql="SELECT * FROM $this->tableName WHERE condicion=1";
        return  $this->conexion->getDataAll($sql); 
    }


}

