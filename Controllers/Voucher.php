<?php 
require_once "../Models/Voucher.php";

$voucher=new Voucher();

$id_comp_pago=isset($_POST["id_comp_pago"])? $_POST["id_comp_pago"]:"";
$nombre=isset($_POST["nombre"])? $_POST["nombre"]:"";
$letra_serie=isset($_POST["letra_serie"])? $_POST["letra_serie"]:"";
$serie_comprobante=isset($_POST["serie_comprobante"])? $_POST["serie_comprobante"]:"";
$num_comprobante=isset($_POST["num_comprobante"])? $_POST["num_comprobante"]:"";

switch ($_GET["op"]) {
	case 'guardaryeditar':
	if (empty($id_comp_pago)) {
		$rspta=$voucher->insertar($nombre,$letra_serie,$serie_comprobante,$num_comprobante);
		echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
	}else{
         $rspta=$voucher->editar($id_comp_pago,$nombre,$letra_serie,$serie_comprobante,$num_comprobante);
		echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
	}
		break;
	
	case 'desactivar':
		$rspta=$voucher->desactivar($id_comp_pago);
		echo $rspta ? "Datos desactivados correctamente" : "No se pudo desactivar los datos";
		break;
	case 'activar':
		$rspta=$voucher->activar($id_comp_pago);
		echo $rspta ? "Datos activados correctamente" : "No se pudo activar los datos";
		break;
	
	case 'mostrar':
		$rspta=$voucher->mostrar($id_comp_pago);
		echo json_encode($rspta);
		break;

    case 'listar':
		$rspta=$voucher->listar();
		$data=Array();

        foreach($rspta as $reg){
        $data[]=array(
            "0"=>($reg['condicion'])?'<button class="btn btn-warning btn-sm" onclick="mostrar('.$reg['id_comp_pago'].')"><i class="fas fa-pencil-alt"></i></button>'.' '.'<button class="btn btn-danger btn-sm" onclick="desactivar('.$reg['id_comp_pago'].')"><i class="fas fa-times"></i></button>':'<button class="btn btn-warning btn-sm" onclick="mostrar('.$reg['id_comp_pago'].')"><i class="fas fa-pencil-alt"></i></button>'.' '.'<button class="btn btn-primary btn-sm" onclick="activar('.$reg['id_comp_pago'].')"><i class="fa fa-check"></i></button>',
            "1"=>$reg['nombre'],
            "2"=>$reg['letra_serie'].$reg['serie_comprobante'].'-'.$reg['num_comprobante'],
            "3"=>($reg['condicion'])?'<div class="badge badge-success">Activado</div>':'<div class="badge badge-danger">Desactivado</div>'
              );
		}
		$results=array(
             "sEcho"=>1,//info para datatables
             "iTotalRecords"=>count($data),//enviamos el total de registros al datatable
             "iTotalDisplayRecords"=>count($data),//enviamos el total de registros a visualizar
             "aaData"=>$data); 
		echo json_encode($results);   
		break;

	case 'selectComprobante':
		$rspta=$voucher->select();
		echo '<option value="">Seleccione...</option>';
		foreach($rspta as $reg){
			echo '<option value="'. $reg['id_comp_pago'].'">'.$reg['nombre'].'</option>';
		}
		break;
}
