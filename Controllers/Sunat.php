<?php
// ===============================
// CONFIGURACIÓN DE ERRORES
// ===============================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===============================
// DEPENDENCIAS
// ===============================
require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/../Models/Sunat.php';

// ===============================
// INSTANCIA PRINCIPAL
// ===============================
$sunat = new Sunat();

// ===============================
// ROUTER
// ===============================
$op = $_GET['op'] ?? '';

switch ($op) {

    // ===============================
    // LISTAR COMPROBANTES SUNAT
    // ===============================
    case 'listar':

        $rspta = $sunat->listar();
        $data = [];

        foreach ($rspta as $reg) {

            // XML
            $xml = !empty($reg['xml'])
                ? '<a href="' . $reg['xml'] . '" target="_blank" class="badge-xml">XML</a>'
                : '<span class="badge-xml">—</span>';

            // CDR
            $cdr = !empty($reg['cdr'])
                ? '<a href="' . $reg['cdr'] . '" target="_blank" class="badge-cdr">CDR</a>'
                : '<span class="badge-cdr">—</span>';

            // Estado SUNAT
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

            // Mensaje SUNAT
            $mensaje = !empty($reg['mensaje_sunat'])
                ? '<small>' . $reg['mensaje_sunat'] . '</small>'
                : '<span class="text-muted">—</span>';

            // DataTable row
            $data[] = [
                "0" => '<button class="btn btn-light btn-sm" onclick="verDetalle(' . $reg['idventa'] . ')">
                            <i class="fas fa-eye"></i>
                        </button>',
                "1" => $reg['comprobante'],
                "2" => $reg['cliente'],
                "3" => 'S/ ' . number_format((float)$reg['total'], 2),
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
        exit;

    // ===============================
    // GENERAR XML
    // ===============================
    case 'generarxml':

        require_once __DIR__ . '/../Models/GenerarXML.php';

        $idventa = (int)($_REQUEST['idventa'] ?? 0);

        if ($idventa <= 0) {
            echo json_encode([
                'status' => false,
                'message' => 'ID de venta inválido'
            ]);
            exit;
        }

        $xmlModel = new GenerarXML();
        $rutaXML = $xmlModel->generar($idventa);

        if (!$rutaXML) {
            echo json_encode([
                'status' => false,
                'message' => 'No se pudo generar el XML'
            ]);
            exit;
        }

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
        exit;

    // ===============================
    // DETALLE COMPROBANTE
    // ===============================
    case 'detalle':

        $idventa = (int)($_POST['idventa'] ?? 0);

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

        if (!is_array($r)) {
            echo json_encode([
                'status' => false,
                'message' => 'No se encontró el comprobante'
            ]);
            exit;
        }

        echo json_encode([
            'status' => true,
            'comprobante' => $r['comprobante'] ?? '',
            'cliente' => $r['cliente'] ?? '',
            'total' => number_format((float)($r['total'] ?? 0), 2),
            'xml' => $r['xml'] ?? '',
            'cdr' => $r['cdr'] ?? '',
            'estado' => $r['estado_sunat'] ?? 'PENDIENTE'
        ]);
        exit;

    // ===============================
    // ENVIAR A SUNAT
    // ===============================
    case 'enviarsunat':

        require_once __DIR__ . '/../Models/EnviarSunat.php';

        $idventa = (int)($_POST['idventa'] ?? 0);

        if ($idventa <= 0) {
            echo json_encode([
                'status' => false,
                'message' => 'ID de venta inválido'
            ]);
            exit;
        }

        $envio = new EnviarSunat();
        $respuesta = $envio->enviar($idventa);

        if (!$respuesta['status']) {
            echo json_encode([
                'status' => false,
                'message' => $respuesta['mensaje']
            ]);
            exit;
        }

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
        exit;

    // ===============================
    // DEFAULT
    // ===============================
    default:
        echo json_encode([
            'status' => false,
            'message' => 'Operación no válida'
        ]);
        exit;
}
