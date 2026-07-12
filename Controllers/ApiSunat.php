<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/../Models/ApiSunatDocument.php';
require_once __DIR__ . '/../Models/ApiSunat.php';

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
        | Vista previa
        |--------------------------------------------------------------------------
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
                    'mensaje' => 'El ID de venta no es válido.'
                ], 400);
            }

            $documento = new ApiSunatDocument();
            $resultado = $documento->construir($idventa);

            responderApiSunat([
                'success' => true,
                'mensaje' =>
                    'JSON construido correctamente. No se realizó ningún envío.',
                'idventa' => $resultado['idventa'],
                'fileName' => $resultado['fileName'],
                'tipoSunat' => $resultado['tipoSunat'],
                'serie' => $resultado['serie'],
                'numero' => $resultado['numero'],
                'customerEmail' => $resultado['customerEmail'],
                'totales' => $resultado['totales'],
                'documentBody' => $resultado['documentBody']
            ]);

            break;

        /*
        |--------------------------------------------------------------------------
        | Último correlativo
        |--------------------------------------------------------------------------
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

            if ($tipo === '') {
                responderApiSunat([
                    'success' => false,
                    'mensaje' =>
                        'Debe indicar el tipo de comprobante.'
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
        | Consultar resultado y guardar XML/CDR
        |--------------------------------------------------------------------------
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
                    'mensaje' => 'El ID de venta no es válido.'
                ], 400);
            }

            $pdo = Conexion::conectar();

            $pdo->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            $pdo->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

            $stmt = $pdo->prepare(
                "
                SELECT
                    idventa_sunat,
                    idventa,
                    document_id,
                    file_name,
                    estado_sunat,
                    xml,
                    cdr,
                    pdf
                FROM venta_sunat
                WHERE idventa = :idventa
                LIMIT 1
                "
            );

            $stmt->execute([
                ':idventa' => $idventa
            ]);

            $registro = $stmt->fetch();

            if (!$registro) {
                responderApiSunat([
                    'success' => false,
                    'mensaje' =>
                        'La venta no tiene un registro en venta_sunat.'
                ], 404);
            }

            $documentId = trim(
                (string)(
                    $registro['document_id']
                    ?? ''
                )
            );

            if ($documentId === '') {
                responderApiSunat([
                    'success' => false,
                    'mensaje' =>
                        'La venta todavía no tiene documentId.'
                ], 400);
            }

            $apiSunat = new ApiSunat();

            $consulta =
                $apiSunat->consultarDocumento(
                    $documentId
                );

            $estado = strtoupper(
                trim(
                    (string)(
                        $consulta['status']
                        ?? 'ERROR'
                    )
                )
            );

            if ($estado === '') {
                $estado = 'ERROR';
            }

            $xmlNuevo = trim(
                (string)(
                    $consulta['xml']
                    ?? ''
                )
            );

            $cdrNuevo = trim(
                (string)(
                    $consulta['cdr']
                    ?? ''
                )
            );

            /*
             * Si APISUNAT todavía no devuelve XML o CDR,
             * conservamos los valores existentes.
             */
            $xmlGuardar = $xmlNuevo !== ''
                ? $xmlNuevo
                : (
                    $registro['xml']
                    ?? null
                );

            $cdrGuardar = $cdrNuevo !== ''
                ? $cdrNuevo
                : (
                    $registro['cdr']
                    ?? null
                );

            $faults = is_array(
                $consulta['faults']
                ?? null
            )
                ? $consulta['faults']
                : [];

            $notes = is_array(
                $consulta['notes']
                ?? null
            )
                ? $consulta['notes']
                : [];

            $faultsJson = json_encode(
                $faults,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

            $notesJson = json_encode(
                $notes,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

            $responseJson = json_encode(
                $consulta['document']
                ?? $consulta,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
            );

            if ($faultsJson === false) {
                $faultsJson = '[]';
            }

            if ($notesJson === false) {
                $notesJson = '[]';
            }

            if ($responseJson === false) {
                $responseJson = '{}';
            }

            $estadosFinales = [
                'ACEPTADO',
                'RECHAZADO',
                'EXCEPCION'
            ];

            $fechaRespuesta = null;

            $responseTime = $consulta['responseTime']
                ?? null;

            if (
                is_numeric($responseTime)
                && (int)$responseTime > 0
            ) {
                $fechaRespuesta = date(
                    'Y-m-d H:i:s',
                    (int)$responseTime
                );
            } elseif (
                in_array(
                    $estado,
                    $estadosFinales,
                    true
                )
            ) {
                $fechaRespuesta = date(
                    'Y-m-d H:i:s'
                );
            }

            $mensaje = trim(
                (string)(
                    $consulta['message']
                    ?? ''
                )
            );

            if ($mensaje === '') {
                $mensaje = 'Estado consultado en APISUNAT.';
            }

            $actualizar = $pdo->prepare(
                "
                UPDATE venta_sunat
                SET
                    estado_sunat = :estado,
                    mensaje_sunat = :mensaje,
                    xml = :xml,
                    cdr = :cdr,
                    referencia = :referencia,
                    faults = :faults,
                    notes = :notes,
                    response_json = :response_json,
                    intentos_consulta =
                        intentos_consulta + 1,
                    fecha_ultima_consulta = NOW(),
                    fecha_respuesta = :fecha_respuesta
                WHERE idventa = :idventa
                "
            );

            $actualizar->execute([
                ':estado' => $estado,
                ':mensaje' => $mensaje,
                ':xml' => $xmlGuardar,
                ':cdr' => $cdrGuardar,
                ':referencia' => trim(
                    (string)(
                        $consulta['reference']
                        ?? ''
                    )
                ),
                ':faults' => $faultsJson,
                ':notes' => $notesJson,
                ':response_json' => $responseJson,
                ':fecha_respuesta' => $fechaRespuesta,
                ':idventa' => $idventa
            ]);

            responderApiSunat([
                'success' =>
                    ($consulta['success'] ?? false)
                    === true,
                'idventa' => $idventa,
                'documentId' => $documentId,
                'fileName' =>
                    $consulta['fileName']
                    ?? $registro['file_name'],
                'production' =>
                    $consulta['production']
                    ?? true,
                'status' => $estado,
                'mensaje' => $mensaje,
                'xml' => $xmlGuardar,
                'cdr' => $cdrGuardar,
                'faults' => $faults,
                'notes' => $notes,
                'fecha_respuesta' => $fechaRespuesta
            ]);

            break;

        default:

            responderApiSunat([
                'success' => false,
                'mensaje' => 'Operación no válida.',
                'operacion_recibida' => $op,
                'operaciones_disponibles' => [
                    'preview',
                    'lastDocument',
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