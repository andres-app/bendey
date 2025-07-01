<?php
require_once "../models/Subcategoria.php";
$subcategoria = new Subcategoria();

switch ($_GET["op"]) {
    case 'selectSubcategoria':
        $categoria_id = $_REQUEST["categoria_id"];
        $rspta = $subcategoria->listarPorCategoria($categoria_id);
        echo '<option value="">Seleccione subcategoría</option>';
        foreach ($rspta as $reg) {
            echo '<option value="' . $reg['idsubcategoria'] . '">' . $reg['nombre'] . '</option>';
        }
        break;
}
?>
