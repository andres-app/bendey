<?php
require_once "../Models/Subcategoria.php";

$subcategoria = new Subcategoria();

switch ($_GET["op"]) {

    // =========================
    // LISTAR (JSON) - MODAL
    // =========================
    case 'listar':
        $idcategoria = $_GET["idcategoria"];
        echo json_encode(
            $subcategoria->listarPorCategoria($idcategoria)
        );
        break;

    // =========================
    // INSERTAR DESDE MODAL
    // =========================
    case 'guardar':

        $idcategoria = $_POST["idcategoria"] ?? null;
        $nombre = $_POST["nombre"] ?? null;

        if (empty($idcategoria) || empty($nombre)) {
            echo "Datos incompletos";
            break;
        }

        $rspta = $subcategoria->insertar($idcategoria, $nombre);
        echo $rspta ? "OK" : "ERROR";
        break;

    // =========================
    // ACTIVAR / DESACTIVAR
    // =========================
    case 'activar':
        echo $subcategoria->activar($_POST["idsubcategoria"]);
        break;

    case 'desactivar':
        echo $subcategoria->desactivar($_POST["idsubcategoria"]);
        break;

    // =========================
    // SELECT PARA PRODUCTOS
    // =========================
    case 'selectSubcategoria':

        $categoria_id = $_POST["categoria_id"] ?? $_GET["categoria_id"] ?? null;

        echo '<option value="">Seleccione subcategoría</option>';

        if (!$categoria_id) break;

        $rspta = $subcategoria->listarPorCategoria($categoria_id);

        foreach ($rspta as $reg) {
            echo '<option value="'.$reg['idsubcategoria'].'">'.$reg['nombre'].'</option>';
        }
        break;
}
