<?php
require_once __DIR__ . '/../Config/Conexion.php';
require_once "../Models/Sunat.php";

$sunat = new Sunat();

switch ($_GET["op"]) {

    case 'listar':

        $rspta = $sunat->listar();
        $data = [];

        foreach ($rspta as $reg) {

            /* ===============================
               XML (MISMO ESTILO)
            =============================== */
            if (!empty($reg['xml'])) {
                $xml = '<a href="' . $reg['xml'] . '" target="_blank" class="badge-xml">XML</a>';
            } else {
                $xml = '<span class="badge-xml">—</span>';
            }

            /* ===============================
               CDR (MISMO ESTILO)
            =============================== */
            if (!empty($reg['cdr'])) {
                $cdr = '<a href="' . $reg['cdr'] . '" target="_blank" class="badge-cdr">CDR</a>';
            } else {
                $cdr = '<span class="badge-cdr">—</span>';
            }

            /* ===============================
               ESTADO SUNAT (CLASES ORIGINALES)
            =============================== */
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

                case 'PENDIENTE':
                default:
                    $estado = '<span class="badge-sunat sunat-pendiente">Pendiente</span>';
                    break;
            }

            /* ===============================
               MENSAJE SUNAT (NO CAMBIA ESTILO)
            =============================== */
            $mensaje = !empty($reg['mensaje_sunat'])
                ? '<small>' . $reg['mensaje_sunat'] . '</small>'
                : '<span class="text-muted">—</span>';

            /* ===============================
               DATA PARA DATATABLE
            =============================== */
            $data[] = [
                "0" => '<button class="btn btn-light btn-sm" onclick="verDetalle(' . $reg['idventa'] . ')">
                            <i class="fas fa-eye"></i>
                        </button>',
                "1" => $reg['comprobante'],
                "2" => $reg['cliente'],
                "3" => 'S/ ' . number_format($reg['total'], 2),
                "4" => $xml,
                "5" => $cdr,
                "6" => $estado,
                "7" => $mensaje,
                "8" => $reg['fecha']
            ];
        }

        echo json_encode([
            "draw" => 1,
            "recordsTotal" => count($data),
            "recordsFiltered" => count($data),
            "data" => $data
        ]);

        break;


    case 'generarxml':

        require_once "../Models/GenerarXML.php";

        $idventa = isset($_REQUEST['idventa']) ? (int) $_REQUEST['idventa'] : 0;

        if ($idventa <= 0) {
            echo json_encode([
                'status' => false,
                'message' => 'ID de venta inválido'
            ]);
            exit;
        }

        $xmlModel = new GenerarXML();
        $rutaXML = $xmlModel->generar($idventa);

        if ($rutaXML) {

            $conexion = new Conexion();

            $sql = "INSERT INTO venta_sunat (idventa, xml, estado_sunat)
                        VALUES (?, ?, 'GENERADO')
                        ON DUPLICATE KEY UPDATE 
                            xml = VALUES(xml),
                            estado_sunat = 'GENERADO'";

            $conexion->setData($sql, [$idventa, $rutaXML]);

            echo json_encode([
                'status' => true,
                'message' => 'XML generado correctamente'
            ]);
        } else {
            echo json_encode([
                'status' => false,
                'message' => 'No se pudo generar el XML'
            ]);
        }

        exit;

    case 'detalle':

        $idventa = isset($_POST['idventa']) ? (int) $_POST['idventa'] : 0;

        if ($idventa <= 0) {
            echo json_encode([
                'status' => false,
                'message' => 'ID de venta inválido'
            ]);
            exit;
        }

        $conexion = new Conexion();

        $sql = "SELECT 
                        CONCAT(v.tipo_comprobante,' ',v.serie_comprobante,'-',v.num_comprobante) AS comprobante,
                        p.nombre AS cliente,
                        v.total_venta AS total,
                        vs.xml,
                        vs.cdr,
                        vs.estado_sunat
                    FROM venta v
                    INNER JOIN persona p ON v.idcliente = p.idpersona
                    LEFT JOIN venta_sunat vs ON v.idventa = vs.idventa
                    WHERE v.idventa = ?
                    LIMIT 1";

        $r = $conexion->getData($sql, [$idventa]);

        // 🔴 CLAVE: si no es array asociativo, no existe
        if (!is_array($r) || !isset($r['comprobante'])) {
            echo json_encode([
                'status' => false,
                'message' => 'No se encontró información del comprobante'
            ]);
            exit;
        }

        echo json_encode([
            'status'      => true,
            'comprobante' => $r['comprobante'] ?? '',
            'cliente'     => $r['cliente'] ?? '',
            'total'       => number_format($r['total'] ?? 0, 2),
            'xml'         => $r['xml'] ?? '',
            'cdr'         => $r['cdr'] ?? '',
            'estado'      => $r['estado_sunat'] ?? 'PENDIENTE'
        ]);

        exit;

    case 'enviarsunat':

        require_once "../Models/EnviarSunat.php";

        $idventa = isset($_POST['idventa']) ? (int)$_POST['idventa'] : 0;

        if ($idventa <= 0) {
            echo json_encode([
                'status' => false,
                'message' => 'ID de venta inválido'
            ]);
            exit;
        }

        $envio = new EnviarSunat();
        $respuesta = $envio->enviar($idventa);

        if ($respuesta['status']) {

            $conexion = new Conexion();

            $sql = "UPDATE venta_sunat 
                        SET 
                            cdr = ?,
                            estado_sunat = ?,
                            mensaje_sunat = ?,
                            fecha_respuesta = NOW()
                        WHERE idventa = ?";

            $conexion->setData($sql, [
                $respuesta['cdr'],
                $respuesta['estado'],
                $respuesta['mensaje'],
                $idventa
            ]);

            echo json_encode([
                'status' => true,
                'message' => 'SUNAT respondió correctamente'
            ]);
        } else {

            echo json_encode([
                'status' => false,
                'message' => $respuesta['mensaje']
            ]);
        }

        exit;
}
