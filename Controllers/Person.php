<?php 
require_once "../Models/Person.php";

$person=new Person();

$idpersona=isset($_POST["idpersona"])? $_POST["idpersona"]:"";
$tipo_persona=isset($_POST["tipo_persona"])? $_POST["tipo_persona"]:"";
$nombre=isset($_POST["nombre"])? $_POST["nombre"]:"";
$tipo_documento=isset($_POST["tipo_documento"])? $_POST["tipo_documento"]:"";
$num_documento=isset($_POST["num_documento"])? $_POST["num_documento"]:"";
$direccion=isset($_POST["direccion"])? $_POST["direccion"]:"";
$telefono=isset($_POST["telefono"])? $_POST["telefono"]:"";
$email=isset($_POST["email"])? $_POST["email"]:"";

switch ($_GET["op"]) {
	case 'guardaryeditar':
	if (empty($idpersona)) {
		$rspta=$person->insertar($tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email);
		echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
	}else{
         $rspta=$person->editar($idpersona,$tipo_persona,$nombre,$tipo_documento,$num_documento,$direccion,$telefono,$email);
		echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
	}
		break;

	case 'eliminar':
		$rspta=$person->eliminar($idpersona);
		echo $rspta ? "Datos eliminados correctamente" : "No se pudo eliminar los datos";
		break;
	
	case 'mostrar':
		$rspta=$person->mostrar($idpersona);
		echo json_encode($rspta);
		break;

    case 'listarp':
		$rspta=$person->listarp();
		$data=Array();

		foreach($rspta as $reg){
		$data[]=array(
		"0"=>'<button class="btn btn-warning btn-sm" onclick="mostrar('.$reg['idpersona'].')"><i class="fas fa-pencil-alt"></i></button>'.' '.'<button class="btn btn-danger btn-sm" onclick="eliminar('.$reg['idpersona'].')"><i class="fas fa-trash-alt"></i></button>',
		"1"=>$reg['nombre'],
		"2"=>$reg['tipo_documento'],
		"3"=>$reg['num_documento'],
		"4"=>$reg['telefono'],
		"5"=>$reg['email']
			);
		}
		$results=array(
             "sEcho"=>1,//info para datatables
             "iTotalRecords"=>count($data),//enviamos el total de registros al datatable
             "iTotalDisplayRecords"=>count($data),//enviamos el total de registros a visualizar
             "aaData"=>$data); 
		echo json_encode($results);   
		break;

    case 'listarc':
		$rspta=$person->listarc();
		$data=Array();

		foreach($rspta as $reg){
		$data[]=array(
		"0"=>'<button class="btn btn-warning btn-sm" onclick="mostrar('.$reg['idpersona'].')"><i class="fas fa-pencil-alt"></i></button>'.' '.'<button class="btn btn-danger btn-sm" onclick="eliminar('.$reg['idpersona'].')"><i class="fas fa-trash-alt"></i></button>',
		"1"=>$reg['nombre'],
		"2"=>$reg['tipo_documento'],
		"3"=>$reg['num_documento'],
		"4"=>$reg['telefono'],
		"5"=>$reg['email']
			);
		}
		$results=array(
             "sEcho"=>1,//info para datatables
             "iTotalRecords"=>count($data),//enviamos el total de registros al datatable
             "iTotalDisplayRecords"=>count($data),//enviamos el total de registros a visualizar
             "aaData"=>$data); 
		echo json_encode($results);   
		break;

	case 'selectProveedor':
		$rspta=$person->selectp();
		echo '<option value="">Seleccione...</option>';
		foreach($rspta as $reg){
			echo '<option value="'. $reg['idpersona'].'">'.$reg['nombre'].'</option>';
		}
		break;

	case 'selectCliente':
		$rspta=$person->selectc();
		echo '<option value="">Seleccione...</option>';
		foreach($rspta as $reg){
			echo '<option value="'. $reg['idpersona'].'">'.$reg['nombre'].'</option>';
		}
		break;
}
