<?php
require_once "../Models/Consult.php";

$consult = new Consult();

switch ($_GET["op"]) {
    case 'compras10dias':
        //COMPRAS DE LOS ULTIMOS 10 DIAS
        $compras10 = $consult->comprasultimos_10dias();
        $fechas = array();
        $totales = array();

        $meses = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");

        foreach ($compras10 as $regfechac) {
            array_push($fechas, $meses[date("n", strtotime($regfechac["fecha"])) - 1]);
            array_push($totales, $regfechac['total']);
        }
        $respuesta = [
            "fechas" => $fechas,
            "totales" => $totales,
        ];
        echo json_encode($respuesta);

        break;

    case 'ventas12meses':
        //COMPRAS DE LOS ULTIMOS 10 DIAS
        $compras10 = $consult->ventasultimos_12meses();
        $fechas = array();
        $totales = array();

        $meses = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");

        foreach ($compras10 as $regfechac) {
            array_push($fechas, $meses[date("n", strtotime($regfechac["fecha"])) - 1]);
            array_push($totales, $regfechac['total']);
        }
        $respuesta = [
            "fechas" => $fechas,
            "totales" => $totales,
        ];
        echo json_encode($respuesta);

        break;

    case 'cuadros1':
        $rsptac = $consult->totalcomprahoy();
        $regc = $rsptac[0];
        $totalc = $regc['total_compra'];

        $rsptav = $consult->totalventahoy();
        $regv = $rsptav[0];
        $totalv = $regv['total_venta'];

        $rsptav = $consult->cantidadclientes();
        $regv = $rsptav[0];
        $totalclientes = $regv['totalc'];

        $rsptav = $consult->cantidadproveedores();
        $regv = $rsptav[0];
        $totalproveedores = $regv['totalp'];

        $data = [
            "totalcomprahoy" => $totalc,
            "totalventahoy" => $totalv,
            "cantidadclientes" => $totalclientes,
            "cantidadproveedores" => $totalproveedores,
        ];
        echo json_encode($data);
        break;

    case 'cuadros2':
        $rsptav = $consult->cantidadarticulos();
        $regv = $rsptav[0];
        $totalarticulos = $regv['totalar'];

        $rsptav = $consult->totalstock();
        $regv = $rsptav[0];
        $totalstock = $regv['totalstock'];
        $cap_almacen = 3000;

        $rsptav = $consult->cantidadcategorias();
        $regv = $rsptav[0];
        $totalcategorias = $regv['totalca'];

        $data = [
            "cantidadarticulos" => $totalarticulos,
            "totalstock" => $totalstock,
            "cantidadcategorias" => $totalcategorias,
        ];
        echo json_encode($data);
        break;

    case 'cateogriasMasVendidas':
        $cateogriasMasVendidas = $consult->cateogriasMasVendidas();

        echo json_encode($cateogriasMasVendidas);

        break;

    case 'stockCategoria':
        require_once "../Models/Category.php";
        $categoria = new Category();

        $rspta = $categoria->stockPorCategoria();

        echo json_encode($rspta);
        break;

}