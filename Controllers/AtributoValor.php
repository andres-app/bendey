<?php
require_once "../Models/AtributoValor.php";

$valor = new AtributoValor();

$idvalor = $_POST["idvalor"] ?? "";
$idatributo = $_POST["idatributo"] ?? "";
$valorTexto = $_POST["valor"] ?? "";

switch ($_GET["op"]) {
    case 'guardaryeditar':
        if (empty($idvalor)) {
            echo $valor->insertar($idatributo, $valorTexto) ? "Valor registrado" : "No se pudo registrar";
        } else {
            echo $valor->editar($idvalor, $valorTexto) ? "Valor actualizado" : "No se pudo actualizar";
        }
        break;

    case 'listar':
        $rspta = $valor->listarPorAtributo($_GET["idatributo"]);
        $data = [];

        foreach ($rspta as $reg) {
            $data[] = [
                $reg['valor'],
                $reg['estado'] ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Inactivo</span>',
                '<button class="btn btn-warning btn-sm" onclick="editarValor(' . $reg['idvalor'] . ')"><i class="fas fa-pencil-alt"></i></button>
                 <button class="btn btn-danger btn-sm" onclick="desactivarValor(' . $reg['idvalor'] . ')"><i class="fas fa-times"></i></button>'
            ];
        }

        echo json_encode([
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ]);
        break;


    case 'mostrar':
        echo json_encode($valor->mostrar($_POST["id"]));
        break;

    case 'desactivar':
        echo $valor->desactivar($_POST["id"]) ? "Desactivado" : "Error al desactivar";
        break;

    case 'activar':
        echo $valor->activar($_POST["id"]) ? "Activado" : "Error al activar";
        break;
}
