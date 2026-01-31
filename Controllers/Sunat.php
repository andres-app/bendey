<?php
require_once __DIR__ . '/../Config/Conexion.php';
require_once "../Models/Sunat.php";

$sunat = new Sunat();

switch ($_GET["op"]) {

    case 'listar':

        $rspta = $sunat->listar();
        $data = [];

        foreach ($rspta as $reg) {

            // ===============================
            // XML (sutil)
            // ===============================
            if (!empty($reg['xml'])) {
                $xml = '<a href="'.$reg['xml'].'" target="_blank" class="badge-xml">XML</a>';
            } else {
                $xml = '<span class="badge-xml">—</span>';
            }

            // ===============================
            // ESTADO SUNAT (sutil)
            // ===============================
            switch ($reg['estado_sunat']) {

                case 'ACEPTADO':
                    $estado = '<span class="badge-sunat sunat-aceptado">Aceptado</span>';
                    break;

                case 'ENVIADO':
                    $estado = '<span class="badge-sunat sunat-enviado">Enviado</span>';
                    break;

                case 'RECHAZADO':
                    $estado = '<span class="badge-sunat sunat-rechazado">Rechazado</span>';
                    break;

                case 'ERROR':
                    $estado = '<span class="badge-sunat sunat-error">Error</span>';
                    break;

                default:
                    $estado = '<span class="badge-sunat sunat-pendiente">Pendiente</span>';
                    break;
            }

            // ===============================
            // DATA PARA DATATABLE
            // ===============================
            $data[] = [
                "0" => '<button class="btn btn-light btn-sm" onclick="verDetalle('.$reg['idventa'].')">
                            <i class="fas fa-eye"></i>
                        </button>',
                "1" => $reg['comprobante'],
                "2" => $reg['cliente'],
                "3" => 'S/ ' . number_format($reg['total'], 2),
                "4" => $xml,
                "5" => $estado,
                "6" => $reg['fecha']
            ];
        }

        echo json_encode([
            "draw" => 1,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
            "data" => $data
        ]);
        break;
}
?>
