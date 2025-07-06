<?php
require_once "../Models/Almacen.php";
$almacen = new Almacen();

switch ($_GET["op"]) {
    case 'selectAlmacen':
        $rspta = $almacen->listar();
        echo '<option value="">Seleccione un almacén</option>';
        foreach($rspta as $reg){
            echo '<option value="'. $reg['idalmacen'].'">'.htmlspecialchars($reg['nombre']).'</option>';
        }
        break;
}
?>