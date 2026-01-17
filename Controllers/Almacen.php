<?php
require_once "../Models/Almacen.php";

$almacen = new Almacen();

$idalmacen   = isset($_POST["idalmacen"]) ? $_POST["idalmacen"] : "";
$nombre      = isset($_POST["nombre"]) ? $_POST["nombre"] : "";
$ubicacion   = isset($_POST["ubicacion"]) ? $_POST["ubicacion"] : "";
$descripcion = isset($_POST["descripcion"]) ? $_POST["descripcion"] : "";

switch ($_GET["op"]) {

    // ==============================
    // GUARDAR / EDITAR
    // ==============================
    case 'guardaryeditar':
        if (empty($idalmacen)) {
            $rspta = $almacen->insertar($nombre, $ubicacion, $descripcion);
            echo $rspta
                ? "✅ Almacén registrado correctamente"
                : "❌ No se pudo registrar el almacén";
        } else {
            $rspta = $almacen->editar($idalmacen, $nombre, $ubicacion, $descripcion);
            echo $rspta
                ? "✅ Almacén actualizado correctamente"
                : "❌ No se pudo actualizar el almacén";
        }
        break;

    // ==============================
    // DESACTIVAR
    // ==============================
    case 'desactivar':
        $rspta = $almacen->desactivar($idalmacen);
        echo $rspta
            ? "🔴 Almacén desactivado correctamente"
            : "❌ No se pudo desactivar";
        break;

    // ==============================
    // ACTIVAR
    // ==============================
    case 'activar':
        $rspta = $almacen->activar($idalmacen);
        echo $rspta
            ? "🟢 Almacén activado correctamente"
            : "❌ No se pudo activar";
        break;

    // ==============================
    // MOSTRAR (EDITAR)
    // ==============================
    case 'mostrar':
        $rspta = $almacen->mostrar($idalmacen);
        echo json_encode($rspta);
        break;

    // ==============================
    // LISTAR (DATATABLE)
    // ==============================
    case 'listar':
        $rspta = $almacen->listar();
        $data = [];

        foreach ($rspta as $reg) {
            $data[] = [
                "0" => $reg['idalmacen'],
                "1" => $reg['nombre'],
                "2" => $reg['ubicacion'],
                "3" => $reg['descripcion'],
                "4" => ($reg['estado'])
                    ? '<span class="badge badge-success">Activo</span>'
                    : '<span class="badge badge-danger">Inactivo</span>',

                // 👉 MISMA FORMA QUE ATRIBUTOS
                "5" => ($reg['estado'])
                    ? '<button class="btn btn-warning btn-sm" onclick="mostrar(' . $reg['idalmacen'] . ')">
                            <i class="fas fa-pencil-alt"></i>
                       </button>
                       <button class="btn btn-danger btn-sm" onclick="desactivar(' . $reg['idalmacen'] . ')">
                            <i class="fas fa-times"></i>
                       </button>'
                    : '<button class="btn btn-warning btn-sm" onclick="mostrar(' . $reg['idalmacen'] . ')">
                            <i class="fas fa-pencil-alt"></i>
                       </button>
                       <button class="btn btn-primary btn-sm" onclick="activar(' . $reg['idalmacen'] . ')">
                            <i class="fas fa-check"></i>
                       </button>'
            ];
        }

        echo json_encode([
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ]);
        break;

    // ==============================
    // SELECT (COMBOS)
    // ==============================
    case 'selectAlmacen':
        $rspta = $almacen->listar();
        echo '<option value="">Seleccione un almacén</option>';
        foreach ($rspta as $reg) {
            echo '<option value="' . $reg['idalmacen'] . '">' .
                htmlspecialchars($reg['nombre']) .
                '</option>';
        }
        break;
}
