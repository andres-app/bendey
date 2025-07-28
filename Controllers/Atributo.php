<?php
require_once "../Models/Atributo.php";

$atributo = new Atributo();

$idatributo = isset($_POST["idatributo"]) ? $_POST["idatributo"] : "";
$nombre = isset($_POST["nombre"]) ? $_POST["nombre"] : "";
$descripcion = isset($_POST["descripcion"]) ? $_POST["descripcion"] : "";

switch ($_GET["op"]) {
    case 'guardaryeditar':
        if (empty($idatributo)) {
            $rspta = $atributo->insertar($nombre, $descripcion);
            echo $rspta ? "✅ Atributo registrado correctamente" : "❌ No se pudo registrar";
        } else {
            $rspta = $atributo->editar($idatributo, $nombre, $descripcion);
            echo $rspta ? "✅ Atributo actualizado correctamente" : "❌ No se pudo actualizar";
        }
        break;

    case 'desactivar':
        $rspta = $atributo->desactivar($idatributo);
        echo $rspta ? "🔴 Atributo desactivado correctamente" : "❌ No se pudo desactivar";
        break;

    case 'activar':
        $rspta = $atributo->activar($idatributo);
        echo $rspta ? "🟢 Atributo activado correctamente" : "❌ No se pudo activar";
        break;

    case 'mostrar':
        $rspta = $atributo->mostrar($idatributo);
        echo json_encode($rspta);
        break;

    case 'listar':
        $rspta = $atributo->listar();
        $data = [];

        foreach ($rspta as $reg) {
            $data[] = [
                "0" => $reg['idatributo'], // columna oculta
                "1" => $reg['nombre'],
                "2" => $reg['descripcion'],
                "3" => ($reg['estado'])
                    ? '<span class="badge badge-success">Activo</span>'
                    : '<span class="badge badge-danger">Inactivo</span>',
                "4" => '<button class="btn btn-info btn-sm" onclick="gestionarValores(' . $reg['idatributo'] . ', \'' . htmlspecialchars($reg['nombre'], ENT_QUOTES) . '\')">
         <i class="fas fa-sliders-h"></i>
       </button>',

                "5" => ($reg['estado'])
                    ? '<button class="btn btn-warning btn-sm" onclick="mostrar(' . $reg['idatributo'] . ')"><i class="fas fa-pencil-alt"></i></button>
                           <button class="btn btn-danger btn-sm" onclick="desactivar(' . $reg['idatributo'] . ')"><i class="fas fa-times"></i></button>'
                    : '<button class="btn btn-warning btn-sm" onclick="mostrar(' . $reg['idatributo'] . ')"><i class="fas fa-pencil-alt"></i></button>
                           <button class="btn btn-primary btn-sm" onclick="activar(' . $reg['idatributo'] . ')"><i class="fas fa-check"></i></button>'
            ];
        }

        echo json_encode([
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ]);
        break;


    case 'select':
        $rspta = $atributo->select();
        echo '<option value="">Seleccione...</option>';
        foreach ($rspta as $reg) {
            echo '<option value="' . $reg['idatributo'] . '">' . $reg['nombre'] . '</option>';
        }
        break;
}
