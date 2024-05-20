<?php 
require_once "../Models/Medida.php";

$medida=new Medida();

$idmedida=isset($_POST["idmedida"])? $_POST["idmedida"]:"";
$codigo=isset($_POST["codigo"])? $_POST["codigo"]:"";
$nombre=isset($_POST["nombre"])? $_POST["nombre"]:"";

switch ($_GET["op"]) {
	case 'guardaryeditar':
	if (empty($idmedida)) {
		$rspta=$medida->insertar($codigo,$nombre);
		echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
	}else{
         $rspta=$medida->editar($idmedida,$codigo,$nombre);
		echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
	}
		break;
	

	case 'desactivar':
		$rspta=$medida->desactivar($idmedida);
		echo $rspta ? "Datos desactivados correctamente" : "No se pudo desactivar los datos";
		break;
	case 'activar':
		$rspta=$medida->activar($idmedida);
		echo $rspta ? "Datos activados correctamente" : "No se pudo activar los datos";
		break;
	
	case 'mostrar':
		$rspta=$medida->mostrar($idmedida);
		echo json_encode($rspta);
		break;

    case 'listar':
		$rspta=$medida->listar();
		$data=Array();


            foreach($rspta as $reg){
			$data[]=array(
            "0"=>$reg['codigo'],
            "1"=>$reg['nombre'],
            "2"=>($reg['condicion'])?'<div class="badge badge-success">Activado</div>':'<div class="badge badge-danger">Desactivado</div>',
			"3"=>($reg['condicion'])?'<button class="btn btn-warning btn-sm" onclick="mostrar('.$reg['idmedida'].')"><i class="fas fa-pencil-alt"></i></button>'.' '.'<button class="btn btn-danger btn-sm" onclick="desactivar('.$reg['idmedida'].')"><i class="fas fa-times"></i></button>':'<button class="btn btn-warning btn-sm" onclick="mostrar('.$reg['idmedida'].')"><i class="fas fa-pencil-alt"></i></button>'.' '.'<button class="btn btn-primary btn-sm" onclick="activar('.$reg['idmedida'].')"><i class="fas fa-check"></i></button>'
              );
		}
		$results=array(
             "sEcho"=>1,//info para datatables
             "iTotalRecords"=>count($data),//enviamos el total de registros al datatable
             "iTotalDisplayRecords"=>count($data),//enviamos el total de registros a visualizar
             "aaData"=>$data); 
		echo json_encode($results);   
		break;

	case 'selectMedida':
		$rspta=$medida->select();
		echo '<option value="">Seleccione...</option>';
		foreach($rspta as $reg){
		//while ($reg=$rspta->fetch_object()) {
			echo '<option value="'. $reg['idmedida'].'">'.$reg['nombre'].'</option>';
		}
		break;
}
