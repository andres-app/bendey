<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Lima');

require_once __DIR__ . '/../Models/CreditNote.php';
require_once __DIR__ . '/../Models/ApiSunatCreditNoteEmission.php';
require_once __DIR__ . '/../Models/ApiSunatCreditNoteStatus.php';
require_once __DIR__ . '/../Models/ApiSunatStorage.php';

if (
    !isset($_SESSION['nombre'])
    || (int)($_SESSION['ventas'] ?? 0) !== 1
) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => false,
        'message' => 'Acceso no autorizado.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$notas = new CreditNote();
$op = trim((string)($_GET['op'] ?? ''));

function responderNota(array $respuesta, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRESERVE_ZERO_FRACTION
    );
    exit;
}

function decodificarArregloJson(string $campo): array
{
    $texto = trim((string)($_POST[$campo] ?? ''));

    if ($texto === '') {
        return [];
    }

    $datos = json_decode($texto, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        throw new RuntimeException(
            'El contenido de ' . $campo . ' no es válido.'
        );
    }

    return $datos;
}

try {
    switch ($op) {
        case 'preparar':
            $idventa = (int)(
                $_GET['idventa']
                ?? $_POST['idventa']
                ?? 0
            );

            responderNota([
                'status' => true,
                'data' => $notas->preparar($idventa)
            ]);
            break;

        case 'guardar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderNota([
                    'status' => false,
                    'message' => 'El registro requiere una petición POST.'
                ], 405);
            }

            $datos = [
                'idventa' => (int)($_POST['idventa'] ?? 0),
                'codigo_motivo' => trim(
                    (string)($_POST['codigo_motivo'] ?? '')
                ),
                'sustento' => trim((string)($_POST['sustento'] ?? '')),
                'observacion' => trim(
                    (string)($_POST['observacion'] ?? '')
                ),
                'modo_envio' => trim(
                    (string)($_POST['modo_envio'] ?? 'INMEDIATO')
                ),
                'items' => decodificarArregloJson('items_json'),
                'pagos' => decodificarArregloJson('pagos_json')
            ];

            $resultado = $notas->registrar($datos, $_SESSION);
            $resultadoSunat = null;

            if (($resultado['modo_envio'] ?? '') === 'INMEDIATO') {
                try {
                    $emision = new ApiSunatCreditNoteEmission();
                    $resultadoSunat = $emision->enviar(
                        (int)$resultado['idnota_credito']
                    );
                } catch (Throwable $errorSunat) {
                    error_log(
                        '[NC ENVÍO AUTOMÁTICO] '
                        . $resultado['idnota_credito']
                        . ': ' . $errorSunat->getMessage()
                    );

                    $resultadoSunat = [
                        'success' => false,
                        'status' => 'ERROR',
                        'documentId' => null,
                        'mensaje' => $errorSunat->getMessage()
                    ];
                }
            }

            responderNota([
                'status' => true,
                'message' => $resultado['mensaje'],
                'resultado' => $resultado,
                'sunat' => $resultadoSunat
            ]);
            break;

        case 'procesar_devolucion':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderNota([
                    'status' => false,
                    'message' =>
                        'La operación requiere una petición POST.'
                ], 405);
            }

            $idnota = (int)(
                $_POST['idnota_credito']
                ?? 0
            );

            $resultado =
                $notas->procesarFinanzasPendientes(
                    $idnota,
                    $_SESSION
                );

            responderNota([
                'status' => true,
                'message' => $resultado['mensaje'],
                'resultado' => $resultado
            ]);
            break;

        case 'detalle':
            $idnota = (int)(
                $_GET['idnota_credito']
                ?? $_POST['idnota_credito']
                ?? 0
            );
            $detalle = $notas->obtenerNota($idnota);

            if (!$detalle) {
                responderNota([
                    'status' => false,
                    'message' => 'No se encontró la nota de crédito.'
                ], 404);
            }

            responderNota([
                'status' => true,
                'data' => $detalle
            ]);
            break;

        case 'enviar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderNota([
                    'status' => false,
                    'message' => 'El envío requiere una petición POST.'
                ], 405);
            }

            $idnota = (int)($_POST['idnota_credito'] ?? 0);
            $emision = new ApiSunatCreditNoteEmission();
            $resultado = $emision->enviar($idnota);

            responderNota([
                'status' => ($resultado['success'] ?? false) === true,
                'message' => $resultado['mensaje'] ?? '',
                'resultado' => $resultado
            ]);
            break;

        case 'consultar':
            $idnota = (int)(
                $_POST['idnota_credito']
                ?? $_GET['idnota_credito']
                ?? 0
            );
            $servicio = new ApiSunatCreditNoteStatus();
            $resultado = $servicio->consultarYGuardar($idnota);

            responderNota([
                'status' => ($resultado['success'] ?? false) === true,
                'message' => $resultado['mensaje'] ?? '',
                'resultado' => $resultado
            ]);
            break;

        case 'descargar':
            $idnota = (int)($_GET['idnota_credito'] ?? 0);
            $tipo = strtolower(trim((string)($_GET['tipo'] ?? '')));

            if (
                $idnota <= 0
                || !in_array($tipo, ['xml', 'cdr'], true)
            ) {
                throw new RuntimeException(
                    'Solicitud de descarga inválida.'
                );
            }

            $archivo = $notas->obtenerArchivo($idnota, $tipo);

            if (!$archivo) {
                throw new RuntimeException(
                    'No se encontró el archivo solicitado.'
                );
            }

            $storage = new ApiSunatStorage();
            $rutaLocal = trim((string)($archivo['ruta_local'] ?? ''));

            if (!$storage->existe($rutaLocal)) {
                $url = trim((string)($archivo['url'] ?? ''));

                if ($url === '') {
                    throw new RuntimeException(
                        'La nota todavía no tiene el archivo disponible.'
                    );
                }

                $rutaLocal = $storage->guardarDesdeUrl($url, $tipo);
                $notas->actualizarRutaLocal($idnota, $tipo, $rutaLocal);
            }

            $rutaAbsoluta = $storage->rutaAbsoluta($rutaLocal);

            if (!is_file($rutaAbsoluta)) {
                throw new RuntimeException(
                    'El archivo local no existe.'
                );
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: application/zip');
            header(
                'Content-Disposition: attachment; filename="'
                . basename($rutaAbsoluta) . '"'
            );
            header('Content-Length: ' . filesize($rutaAbsoluta));
            header('Cache-Control: private, no-store, max-age=0');
            readfile($rutaAbsoluta);
            exit;

        default:
            responderNota([
                'status' => false,
                'message' => 'Operación no válida.'
            ], 404);
    }
} catch (Throwable $e) {
    error_log('[CREDIT NOTE CONTROLLER] ' . $e->getMessage());

    responderNota([
        'status' => false,
        'message' => $e->getMessage()
    ], 500);
}
