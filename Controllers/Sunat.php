<?php
require_once __DIR__ . '/../Config/Conexion.php';
require_once "../Models/Sunat.php";

$sunat = new Sunat();

switch ($_GET["op"]) {
    case 'listar':
        $rspta = $sunat->listar();
        $data = [];
        foreach ($rspta as $reg) {
            $data[] = [
                "0" => '<button class="btn btn-info btn-sm" onclick="verDetalle(' . $reg['idventa'] . ')">Ver</button>',
                "1" => $reg['comprobante'],
                "2" => $reg['cliente'],
                "3" => 'S/ ' . number_format($reg['total'], 2),
                "4" => isset($reg['xml']) ? $reg['xml'] : '',
                "5" => isset($reg['estado_sunat']) ? $reg['estado_sunat'] : '',
                "6" => $reg['fecha']
            ];
        }
        echo json_encode(["data" => $data]);
        break;
}
?>