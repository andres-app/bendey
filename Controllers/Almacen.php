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
                $data[] = [
                    "0" => htmlspecialchars($reg['idalmacen']),
                    "1" => htmlspecialchars($reg['nombre']),
                    "2" => htmlspecialchars($reg['ubicacion']),
                    "3" => htmlspecialchars($reg['descripcion']),
                    "4" => ($reg['estado'] == 1 ? 'Activo' : 'Inactivo'),
                    "5" => '<button class="btn btn-warning btn-sm" onclick="editar('.$reg['idalmacen'].')">Editar</button>'
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
        

}
?>