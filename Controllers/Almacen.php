<?php
require_once "../Models/Almacen.php";
$almacen = new Almacen();

switch ($_GET["op"]) {

    case 'selectAlmacen':
        $rspta = $almacen->listar();
        echo '<option value="">Seleccione un almacén</option>';
        foreach ($rspta as $reg) {
            echo '<option value="' . $reg['idalmacen'] . '">' . htmlspecialchars($reg['nombre']) . '</option>';
        }
        break;

    case 'listar':
        $rspta = $almacen->listar();
        $data = [];

        foreach ($rspta as $reg) {
            $estado = ($reg['estado'] == 1) ? 'Activo' : 'Inactivo';

            $botones = '';
            $botones .= '<button class="btn btn-warning btn-sm" onclick="mostrar(' . $reg['idalmacen'] . ')"><i class="fa fa-pencil"></i></button> ';
            if ($reg['estado']) {
                $botones .= '<button class="btn btn-danger btn-sm" onclick="desactivar(' . $reg['idalmacen'] . ')"><i class="fa fa-close"></i></button>';
            } else {
                $botones .= '<button class="btn btn-success btn-sm" onclick="activar(' . $reg['idalmacen'] . ')"><i class="fa fa-check"></i></button>';
            }

            $data[] = [
                "0" => htmlspecialchars($reg['idalmacen']),
                "1" => htmlspecialchars($reg['nombre']),
                "2" => htmlspecialchars($reg['ubicacion']),
                "3" => htmlspecialchars($reg['descripcion']),
                "4" => $estado,
                "5" => $botones
            ];
        }

        $results = [
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ];

        echo json_encode($results);
        break;

    case 'mostrar':
        $rspta = $almacen->mostrar($_POST["idalmacen"]);
        echo json_encode($rspta[0]);
        break;

    case 'desactivar':
        echo $almacen->desactivar($_POST["idalmacen"]) ? "Almacén desactivado" : "No se pudo desactivar";
        break;

    case 'activar':
        echo $almacen->activar($_POST["idalmacen"]) ? "Almacén activado" : "No se pudo activar";
        break;

    case 'guardaryeditar':
        if (empty($_POST["idalmacen"])) {
            echo $almacen->insertar($_POST["nombre"], $_POST["ubicacion"], $_POST["descripcion"]);
        } else {
            echo $almacen->editar($_POST["idalmacen"], $_POST["nombre"], $_POST["ubicacion"], $_POST["descripcion"]);
        }
        break;
}
?>
