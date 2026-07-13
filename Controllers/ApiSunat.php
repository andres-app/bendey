<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/*
|--------------------------------------------------------------------------
| Dependencias
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/../Models/ApiSunat.php';
require_once __DIR__ . '/../Models/ApiSunatDocument.php';
require_once __DIR__ . '/../Models/ApiSunatEmission.php';
require_once __DIR__ . '/../Models/ApiSunatStatus.php';

/*
|--------------------------------------------------------------------------
| Respuesta JSON uniforme
|--------------------------------------------------------------------------
*/
function responderApiSunat(
    array $respuesta,
    int $codigoHttp = 200
): void {
    http_response_code($codigoHttp);

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRETTY_PRINT
        | JSON_PRESERVE_ZERO_FRACTION
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Proteger todas las operaciones
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['nombre'])
    || (int)($_SESSION['ventas'] ?? 0) !== 1
) {
    responderApiSunat([
        'success' => false,
        'mensaje' => 'Acceso no autorizado.'
    ], 403);
}

/*
|--------------------------------------------------------------------------
| Operación solicitada
|--------------------------------------------------------------------------
*/
$op = trim(
    (string)(
        $_GET['op']
        ?? $_POST['op']
        ?? ''
    )
);

try {
    switch ($op) {

        /*
        |--------------------------------------------------------------------------
        | VISTA PREVIA
        |--------------------------------------------------------------------------
        | Construye el JSON UBL, pero no envía nada.
        */
        case 'preview':

            $idventa = (int)(
                $_GET['idventa']
                ?? $_POST['idventa']
                ?? 0
            );

            if ($idventa <= 0) {
                responderApiSunat([
                    'success' => false,
                    'mensaje' =>
                        'El ID de venta no es válido.'
                ], 400);
            }

            $documento = new ApiSunatDocument();

            $resultado = $documento->construir(
                $idventa
            );

            responderApiSunat([
                'success' => true,
                'mensaje' =>
                    'JSON construido correctamente. No se realizó ningún envío.',
                'idventa' =>
                    $resultado['idventa'],
                'fileName' =>
                    $resultado['fileName'],
                'tipoSunat' =>
                    $resultado['tipoSunat'],
                'serie' =>
                    $resultado['serie'],
                'numero' =>
                    $resultado['numero'],
                'customerEmail' =>
                    $resultado['customerEmail'],
                'totales' =>
                    $resultado['totales'],
                'documentBody' =>
                    $resultado['documentBody']
            ]);

            break;

        /*
        |--------------------------------------------------------------------------
        | ÚLTIMO CORRELATIVO
        |--------------------------------------------------------------------------
        | Consulta el último número registrado en APISUNAT.
        | No emite ningún comprobante.
        */
        case 'lastDocument':

            $tipo = trim(
                (string)(
                    $_GET['tipo']
                    ?? $_POST['tipo']
                    ?? ''
                )
            );

            $serie = strtoupper(
                trim(
                    (string)(
                        $_GET['serie']
                        ?? $_POST['serie']
                        ?? ''
                    )
                )
            );

            if (!in_array(
                $tipo,
                ['01', '03'],
                true
            )) {
                responderApiSunat([
                    'success' => false,
                    'mensaje' =>
                        'El tipo debe ser 01 para factura o 03 para boleta.'
                ], 400);
            }

            if ($serie === '') {
                responderApiSunat([
                    'success' => false,
                    'mensaje' =>
                        'Debe indicar la serie.'
                ], 400);
            }

            $apiSunat = new ApiSunat();

            $resultado =
                $apiSunat->obtenerUltimoDocumento(
                    $tipo,
                    $serie
                );

            responderApiSunat(
                $resultado,
                ($resultado['success'] ?? false)
                    ? 200
                    : 400
            );

            break;

        /*
        |--------------------------------------------------------------------------
        | ENVIAR COMPROBANTE
        |--------------------------------------------------------------------------
        | Se utiliza para reintentos controlados.
        | La emisión normal ya se ejecuta desde Controllers/Sell.php.
        */
        case 'enviar':

            if (
                ($_SERVER['REQUEST_METHOD'] ?? '')
                !== 'POST'
            ) {
                responderApiSunat([
                    'success' => false,
                    'mensaje' =>
                        'La operación de envío requiere una petición POST.'
                ], 405);
            }

            $idventa = (int)(
                $_POST['idventa']
                ?? 0
            );

            if ($idventa <= 0) {
                responderApiSunat([
                    'success' => false,
                    'mensaje' =>
                        'El ID de venta no es válido.'
                ], 400);
            }

            $emision = new ApiSunatEmission();

            /*
             * ApiSunatEmission valida:
             * - correlativo;
             * - ambiente de producción;
             * - duplicados;
             * - documentId existente;
             * - factura o boleta válida.
             */
            $resultado = $emision->enviarVenta(
                $idventa
            );

            responderApiSunat(
                $resultado,
                ($resultado['success'] ?? false)
                    ? 200
                    : 400
            );

            break;

        /*
        |--------------------------------------------------------------------------
        | CONSULTAR ESTADO
        |--------------------------------------------------------------------------
        | Consulta APISUNAT y actualiza venta_sunat.
        |
        | Cuando el comprobante está ACEPTADO:
        | - guarda las URL originales;
        | - descarga XML al servidor;
        | - descarga CDR al servidor;
        | - registra xml_local y cdr_local.
        */
        case 'consultar':

            $idventa = (int)(
                $_GET['idventa']
                ?? $_POST['idventa']
                ?? 0
            );

            if ($idventa <= 0) {
                responderApiSunat([
                    'success' => false,
                    'mensaje' =>
                        'El ID de venta no es válido.'
                ], 400);
            }

            $estadoSunat = new ApiSunatStatus();

            $resultado =
                $estadoSunat->consultarYGuardar(
                    $idventa
                );

            responderApiSunat(
                $resultado,
                ($resultado['success'] ?? false)
                    ? 200
                    : 400
            );

            break;

        /*
        |--------------------------------------------------------------------------
        | OPERACIÓN INVÁLIDA
        |--------------------------------------------------------------------------
        */
        default:

            responderApiSunat([
                'success' => false,
                'mensaje' =>
                    'Operación no válida.',
                'operacion_recibida' =>
                    $op,
                'operaciones_disponibles' => [
                    'preview',
                    'lastDocument',
                    'enviar',
                    'consultar'
                ]
            ], 404);
    }
} catch (Throwable $e) {
    error_log(
        '[APISUNAT CONTROLLER] '
        . $e->getMessage()
        . ' | Archivo: '
        . $e->getFile()
        . ' | Línea: '
        . $e->getLine()
    );

    responderApiSunat([
        'success' => false,
        'mensaje' => $e->getMessage()
    ], 500);
}