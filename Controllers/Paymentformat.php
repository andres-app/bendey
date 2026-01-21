<?php
require_once "../Models/Paymentformat.php";

$paymentformat = new Paymentformat();

/* ===============================
   VARIABLES
=============================== */
$idforma_pago = isset($_POST["idforma_pago"]) ? $_POST["idforma_pago"] : "";
$nombre       = isset($_POST["nombre"]) ? $_POST["nombre"] : "";
$es_efectivo  = isset($_POST["es_efectivo"]) ? $_POST["es_efectivo"] : 0;
$condicion    = isset($_POST["condicion"]) ? $_POST["condicion"] : 1;

switch ($_GET["op"]) {

    /* ===============================
       GUARDAR / EDITAR
    =============================== */
    case 'guardaryeditar':
        if (empty($idforma_pago)) {
            $rspta = $paymentformat->insertar($nombre, $es_efectivo, $condicion);
            echo $rspta
                ? "Forma de pago registrada correctamente"
                : "No se pudo registrar la forma de pago";
        } else {
            $rspta = $paymentformat->editar($idforma_pago, $nombre, $es_efectivo, $condicion);
            echo $rspta
                ? "Forma de pago actualizada correctamente"
                : "No se pudo actualizar la forma de pago";
        }
        break;

    /* ===============================
       ACTIVAR / DESACTIVAR
    =============================== */
    case 'desactivar':
        $rspta = $paymentformat->desactivar($idforma_pago);
        echo $rspta ? "Forma de pago desactivada" : "No se pudo desactivar";
        break;

    case 'activar':
        $rspta = $paymentformat->activar($idforma_pago);
        echo $rspta ? "Forma de pago activada" : "No se pudo activar";
        break;

    /* ===============================
       MOSTRAR
    =============================== */
    case 'mostrar':
        $rspta = $paymentformat->mostrar($idforma_pago);
        echo json_encode($rspta);
        break;

    /* ===============================
       LISTAR (DATATABLE)
    =============================== */
    case 'listar':
        $rspta = $paymentformat->listar();
        $data = [];

        foreach ($rspta as $reg) {

            $botones = ($reg['activo'])
                ? '<button class="btn btn-warning btn-sm" onclick="mostrar(' . $reg['idforma_pago'] . ')">
                        <i class="fas fa-pencil-alt"></i>
                   </button>
                   <button class="btn btn-danger btn-sm" onclick="desactivar(' . $reg['idforma_pago'] . ')">
                        <i class="fas fa-times"></i>
                   </button>'
                : '<button class="btn btn-warning btn-sm" onclick="mostrar(' . $reg['idforma_pago'] . ')">
                        <i class="fas fa-pencil-alt"></i>
                   </button>
                   <button class="btn btn-primary btn-sm" onclick="activar(' . $reg['idforma_pago'] . ')">
                        <i class="fas fa-check"></i>
                   </button>';

            $data[] = [
                "0" => $botones,
                "1" => $reg['nombre'],
                "2" => ($reg['es_efectivo'] == 1)
                        ? '<span class="badge badge-success">Efectivo</span>'
                        : '<span class="badge badge-info">No efectivo</span>',
                "3" => ($reg['condicion'] == 1)
                        ? '<span class="badge badge-primary">Contado</span>'
                        : '<span class="badge badge-warning">Crédito</span>',
                "4" => ($reg['activo'])
                        ? '<span class="badge badge-success">Activo</span>'
                        : '<span class="badge badge-danger">Inactivo</span>'
            ];
        }

        echo json_encode([
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ]);
        break;

    /* ===============================
       SELECT PARA VENTAS
    =============================== */
    case 'selectFormaPago':
        $rspta = $paymentformat->select();

        echo '<option value="">Seleccione...</option>';
        foreach ($rspta as $reg) {
            echo '<option 
                    value="' . $reg['idforma_pago'] . '" 
                    data-efectivo="' . $reg['es_efectivo'] . '" 
                    data-condicion="' . $reg['condicion'] . '">
                    ' . $reg['nombre'] . '
                  </option>';
        }
        break;
}