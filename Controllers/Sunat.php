<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../Models/Sunat.php';
require_once __DIR__ . '/../Models/ApiSunatStatus.php';
require_once __DIR__ . '/../Models/ApiSunatStorage.php';
require_once __DIR__ . '/../Models/ApiSunatEmission.php';

if (
    !isset($_SESSION['nombre'])
    || (int)($_SESSION['ventas'] ?? 0) !== 1
) {
    http_response_code(403);

    echo json_encode([
        'status' => false,
        'message' => 'Acceso no autorizado.'
    ]);

    exit;
}

$sunat = new Sunat();
$op = trim(
    (string)($_GET['op'] ?? '')
);

function responderSunat(
    array $respuesta,
    int $codigo = 200
): void {
    http_response_code($codigo);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function escaparSunat(
    string $texto
): string {
    return htmlspecialchars(
        $texto,
        ENT_QUOTES,
        'UTF-8'
    );
}

try {
    switch ($op) {

        case 'listar':

            $registros = $sunat->listar();
            $data = [];

            foreach ($registros as $reg) {
                $idventa = (int)$reg['idventa'];

                $tieneXml =
                    !empty($reg['xml'])
                    || !empty($reg['xml_local']);

                $tieneCdr =
                    !empty($reg['cdr'])
                    || !empty($reg['cdr_local']);

                $xml = $tieneXml
                    ? '<a
                            href="Controllers/Sunat.php?op=descargar&tipo=xml&idventa='
                        . $idventa
                        . '"
                            target="_blank"
                            class="badge-xml">
                            XML
                       </a>'
                    : '<span class="badge-xml">—</span>';

                $cdr = $tieneCdr
                    ? '<a
                            href="Controllers/Sunat.php?op=descargar&tipo=cdr&idventa='
                        . $idventa
                        . '"
                            target="_blank"
                            class="badge-cdr">
                            CDR
                       </a>'
                    : '<span class="badge-cdr">—</span>';

                $estadoSunat = strtoupper(
                    trim(
                        (string)(
                            $reg['estado_sunat']
                            ?? 'PENDIENTE'
                        )
                    )
                );

                switch ($estadoSunat) {
                    case 'ACEPTADO':
                        $estado =
                            '<span class="badge-sunat sunat-aceptado">Aceptado</span>';
                        break;

                    case 'EN_PROCESO':
                    case 'PENDIENTE':
                        $estado =
                            '<span class="badge-sunat sunat-proceso">En proceso</span>';
                        break;

                    case 'ENVIADO':
                        $estado =
                            '<span class="badge-sunat sunat-enviado">Enviado</span>';
                        break;

                    case 'RECHAZADO':
                    case 'EXCEPCION':
                        $estado =
                            '<span class="badge-sunat sunat-rechazado">'
                            . escaparSunat($estadoSunat)
                            . '</span>';
                        break;

                    case 'ERROR':
                        $estado =
                            '<span class="badge-sunat sunat-error">Error</span>';
                        break;

                    default:
                        $estado =
                            '<span class="badge-sunat sunat-pendiente">Pendiente</span>';
                }

                $mensaje = !empty(
                    $reg['mensaje_sunat']
                )
                    ? '<small>'
                        . escaparSunat(
                            (string)$reg['mensaje_sunat']
                        )
                        . '</small>'
                    : '<span class="text-muted">—</span>';

                $data[] = [
                    '0' =>
                        '<button
                            class="btn btn-light btn-sm"
                            onclick="verDetalle('
                        . $idventa
                        . ')">
                            <i class="fas fa-eye"></i>
                         </button>',
                    '1' => escaparSunat(
                        (string)$reg['comprobante']
                    ),
                    '2' => escaparSunat(
                        (string)$reg['cliente']
                    ),
                    '3' => 'S/ '
                        . number_format(
                            (float)$reg['total'],
                            2
                        ),
                    '4' => $xml,
                    '5' => $cdr,
                    '6' => $estado,
                    '7' => $mensaje,
                    '8' => escaparSunat(
                        (string)$reg['fecha']
                    )
                ];
            }

            responderSunat([
                'draw' => 1,
                'recordsTotal' => count($data),
                'recordsFiltered' => count($data),
                'data' => $data
            ]);

            break;

        case 'detalle':

            $idventa = (int)(
                $_POST['idventa']
                ?? $_GET['idventa']
                ?? 0
            );

            $detalle = $sunat->detalle(
                $idventa
            );

            if (!$detalle) {
                responderSunat([
                    'status' => false,
                    'message' =>
                        'No se encontró el comprobante.'
                ], 404);
            }

            responderSunat([
                'status' => true,
                'idventa' => $idventa,
                'comprobante' =>
                    $detalle['comprobante'],
                'cliente' =>
                    $detalle['cliente'],
                'total' => number_format(
                    (float)$detalle['total'],
                    2
                ),
                'documentId' =>
                    $detalle['document_id'],
                'estado' =>
                    $detalle['estado_sunat']
                    ?? 'PENDIENTE',
                'mensaje' =>
                    $detalle['mensaje_sunat']
                    ?? '',
                'xml' =>
                    $detalle['xml_local']
                    ?? $detalle['xml']
                    ?? '',
                'cdr' =>
                    $detalle['cdr_local']
                    ?? $detalle['cdr']
                    ?? ''
            ]);

            break;

        case 'consultar':
        case 'getStatus':

            $idventa = (int)(
                $_POST['idventa']
                ?? $_GET['idventa']
                ?? 0
            );

            $servicio = new ApiSunatStatus();

            $resultado =
                $servicio->consultarYGuardar(
                    $idventa
                );

            responderSunat([
                'status' =>
                    ($resultado['success'] ?? false)
                    === true,
                'message' =>
                    $resultado['mensaje']
                    ?? '',
                'resultado' =>
                    $resultado
            ]);

            break;

        case 'enviarsunat':

            if (
                $_SERVER['REQUEST_METHOD']
                !== 'POST'
            ) {
                responderSunat([
                    'status' => false,
                    'message' =>
                        'El envío requiere una petición POST.'
                ], 405);
            }

            $idventa = (int)(
                $_POST['idventa']
                ?? 0
            );

            $emision = new ApiSunatEmission();

            $resultado =
                $emision->enviarVenta(
                    $idventa
                );

            responderSunat([
                'status' =>
                    ($resultado['success'] ?? false)
                    === true,
                'message' =>
                    $resultado['mensaje']
                    ?? '',
                'resultado' =>
                    $resultado
            ]);

            break;

        case 'descargar':

            $idventa = (int)(
                $_GET['idventa']
                ?? 0
            );

            $tipo = strtolower(
                trim(
                    (string)(
                        $_GET['tipo']
                        ?? ''
                    )
                )
            );

            if (
                $idventa <= 0
                || !in_array(
                    $tipo,
                    ['xml', 'cdr'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Solicitud de descarga inválida.'
                );
            }

            $archivo = $sunat->obtenerArchivo(
                $idventa,
                $tipo
            );

            if (!$archivo) {
                throw new RuntimeException(
                    'No se encontró el archivo solicitado.'
                );
            }

            $storage = new ApiSunatStorage();

            $rutaLocal = trim(
                (string)(
                    $archivo['ruta_local']
                    ?? ''
                )
            );

            if (
                !$storage->existe($rutaLocal)
            ) {
                $url = trim(
                    (string)(
                        $archivo['url']
                        ?? ''
                    )
                );

                if ($url === '') {
                    throw new RuntimeException(
                        'El comprobante todavía no tiene archivo disponible.'
                    );
                }

                $rutaLocal =
                    $storage->guardarDesdeUrl(
                        $url,
                        $tipo
                    );

                $sunat->actualizarRutaLocal(
                    $idventa,
                    $tipo,
                    $rutaLocal
                );
            }

            $rutaAbsoluta =
                $storage->rutaAbsoluta(
                    $rutaLocal
                );

            if (!is_file($rutaAbsoluta)) {
                throw new RuntimeException(
                    'El archivo local no existe.'
                );
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header(
                'Content-Type: application/zip'
            );

            header(
                'Content-Disposition: attachment; filename="'
                . basename($rutaAbsoluta)
                . '"'
            );

            header(
                'Content-Length: '
                . filesize($rutaAbsoluta)
            );

            header(
                'Cache-Control: private, no-store, max-age=0'
            );

            readfile($rutaAbsoluta);
            exit;

        case 'generarxml':

            responderSunat([
                'status' => false,
                'message' =>
                    'El XML ahora es generado y firmado por APISUNAT.'
            ], 410);

            break;

        default:

            responderSunat([
                'status' => false,
                'message' =>
                    'Operación no válida.'
            ], 404);
    }
} catch (Throwable $e) {
    error_log(
        '[SUNAT CONTROLLER] '
        . $e->getMessage()
    );

    responderSunat([
        'status' => false,
        'message' => $e->getMessage()
    ], 500);
}