<?php
require_once "../Models/Category.php";

$category = new Category();

$idcategoria = isset($_POST["idcategoria"]) ? $_POST["idcategoria"] : "";
$nombre = isset($_POST["nombre"]) ? $_POST["nombre"] : "";
$descripcion = isset($_POST["descripcion"]) ? $_POST["descripcion"] : "";

switch ($_GET["op"]) {

    case 'guardaryeditar':
        if (empty($idcategoria)) {
            $rspta = $category->insertar($nombre, $descripcion);
            echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
        } else {
            $rspta = $category->editar($idcategoria, $nombre, $descripcion);
            echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
        }
        break;

    case 'desactivar':
        $rspta = $category->desactivar($idcategoria);
        echo $rspta ? "Datos desactivados correctamente" : "No se pudo desactivar los datos";
        break;

    case 'activar':
        $rspta = $category->activar($idcategoria);
        echo $rspta ? "Datos activados correctamente" : "No se pudo activar los datos";
        break;

    case 'mostrar':
        $rspta = $category->mostrar($idcategoria);
        echo json_encode($rspta);
        break;

    // 🔽🔽🔽 AQUÍ ESTÁ LO QUE BUSCABAS 🔽🔽🔽
    case 'listar':
        $rspta = $category->listar();
        $data = array();

        foreach ($rspta as $reg) {

            $botonSub = 
                '<button class="btn btn-info btn-sm"
                    onclick="verSubcategorias('.$reg['idcategoria'].', `'.$reg['nombre'].'`)">
                    <i class="fa fa-list"></i>
                 </button> ';

            if ($reg['condicion']) {
                $botonesEstado =
                    '<button class="btn btn-warning btn-sm"
                        onclick="mostrar('.$reg['idcategoria'].')">
                        <i class="fas fa-pencil-alt"></i>
                     </button>
                     <button class="btn btn-danger btn-sm"
                        onclick="desactivar('.$reg['idcategoria'].')">
                        <i class="fas fa-times"></i>
                     </button>';
            } else {
                $botonesEstado =
                    '<button class="btn btn-warning btn-sm"
                        onclick="mostrar('.$reg['idcategoria'].')">
                        <i class="fas fa-pencil-alt"></i>
                     </button>
                     <button class="btn btn-primary btn-sm"
                        onclick="activar('.$reg['idcategoria'].')">
                        <i class="fas fa-check"></i>
                     </button>';
            }

            $data[] = array(
                "0" => $botonSub . $botonesEstado,
                "1" => $reg['nombre'],
                "2" => $reg['descripcion'],
                "3" => ($reg['condicion'])
                    ? '<div class="badge badge-success">Activado</div>'
                    : '<div class="badge badge-danger">Desactivado</div>'
            );
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );

        echo json_encode($results);
        break;

    case 'selectCategoria':
        $rspta = $category->select();
        echo '<option value="">Seleccione...</option>';
        foreach ($rspta as $reg) {
            echo '<option value="'.$reg['idcategoria'].'">'.$reg['nombre'].'</option>';
        }
        break;

    case 'listar_json':
        $rspta = $category->select();
        echo json_encode($rspta);
        break;
}
