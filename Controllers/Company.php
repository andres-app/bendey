<?php
require_once "../Models/Company.php";

$company = new Company();

// ==========================
// VARIABLES POST
// ==========================
$id_negocio          = isset($_POST["id_negocio"]) ? $_POST["id_negocio"] : "";
$nombre              = isset($_POST["nombre"]) ? $_POST["nombre"] : "";
$ndocumento          = isset($_POST["ndocumento"]) ? $_POST["ndocumento"] : "";
$documento           = isset($_POST["documento"]) ? $_POST["documento"] : "";
$direccion            = isset($_POST["direccion"]) ? $_POST["direccion"] : "";
$telefono             = isset($_POST["telefono"]) ? $_POST["telefono"] : "";
$email                = isset($_POST["email"]) ? $_POST["email"] : "";
$pais                 = isset($_POST["pais"]) ? $_POST["pais"] : "";
$ciudad               = isset($_POST["ciudad"]) ? $_POST["ciudad"] : "";
$nombre_impuesto      = isset($_POST["nombre_impuesto"]) ? $_POST["nombre_impuesto"] : "";
$monto_impuesto       = isset($_POST["monto_impuesto"]) ? $_POST["monto_impuesto"] : "";
$moneda               = isset($_POST["moneda"]) ? $_POST["moneda"] : "";
$simbolo              = isset($_POST["simbolo"]) ? $_POST["simbolo"] : "";
$token_reniec_sunat   = isset($_POST["tokendniruc"]) ? $_POST["tokendniruc"] : "";

// ==========================
// SWITCH
// ==========================
switch ($_GET["op"]) {

    // ======================
    // GUARDAR / EDITAR
    // ======================
    case 'guardaryeditar':

        $rspta = $company->editar(
            $id_negocio,
            $nombre,
            $ndocumento,
            $documento,
            $direccion,
            $telefono,
            $email,
            $pais,
            $ciudad,
            $nombre_impuesto,
            $monto_impuesto,
            $moneda,
            $simbolo,
            $token_reniec_sunat
        );

        echo $rspta
            ? "Datos actualizados correctamente"
            : "No se pudo actualizar los datos";
        break;

    // ======================
    // MOSTRAR
    // ======================
    case 'mostrar':
        $rspta = $company->mostrar($id_negocio);
        echo json_encode($rspta);
        break;

    // ======================
    // MOSTRAR IMPUESTO
    // ======================
    case 'mostrar_impuesto':
        $rspta = $company->mostrar_impuesto();
        $numeroimp = 0;

        foreach ($rspta as $reg) {
            $numeroimp = $reg['monto_impuesto'];
        }

        echo json_encode(floatval($numeroimp));
        break;

    // ======================
    // NOMBRE IMPUESTO
    // ======================
    case 'nombre_impuesto':
        $rspta = $company->nombre_impuesto();
        $nombreimp = "";

        foreach ($rspta as $reg) {
            $nombreimp = $reg['nombre_impuesto'];
        }

        echo json_encode($nombreimp);
        break;

    // ======================
    // SIMBOLO
    // ======================
    case 'mostrar_simbolo':
        $rspta = $company->mostrar_simbolo();
        $simbolo = "";

        foreach ($rspta as $reg) {
            $simbolo = $reg['simbolo'];
        }

        echo json_encode($simbolo);
        break;

    // ======================
    // DATOS FIJOS
    // ======================
    case 'mostrar_datos':
        $id_negocio = 2;
        $rspta = $company->mostrar($id_negocio);
        echo json_encode($rspta);
        break;

    // ======================
    // LISTAR (SIN LOGO)
    // ======================
    case 'listar':
        $rspta = $company->listar();
        $data = array();

        foreach ($rspta as $reg) {
            $data[] = array(
                "0" => '<button class="btn btn-warning btn-xs" onclick="mostrar(' . $reg['id_negocio'] . ')">
                            <i class="fas fa-edit"></i>
                        </button>',
                "1" => $reg['nombre'],
                "2" => $reg['ndocumento'] . $reg['documento'],
                "3" => $reg['direccion'],
                "4" => $reg['telefono'],
                "5" => $reg['email'],
                "6" => $reg['ciudad'] . ' - ' . $reg['pais'],
                "7" => $reg['nombre_impuesto'] . ' ' . $reg['monto_impuesto'] . ' %',
                "8" => $reg['simbolo'] . ' ' . $reg['moneda'],
                "9" => ($reg['condicion'])
                    ? '<span class="badge badge-success">Activo</span>'
                    : '<span class="badge badge-danger">Inactivo</span>'
            );
        }

        echo json_encode(array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        ));
        break;
}
