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

function decodificarMensajesSunat(
    mixed $valor
): array {
    if (is_string($valor)) {
        $texto = trim($valor);

        if ($texto === '') {
            return [];
        }

        $decodificado = json_decode(
            $texto,
            true
        );

        if (
            json_last_error() === JSON_ERROR_NONE
            && is_array($decodificado)
        ) {
            $valor = $decodificado;
        } else {
            return [$texto];
        }
    }

    $salida = [];

    $recorrer = function (
        mixed $dato
    ) use (
        &$recorrer,
        &$salida
    ): void {
        if (is_string($dato)) {
            $texto = trim($dato);

            if ($texto !== '') {
                $salida[] = $texto;
            }

            return;
        }

        if (
            is_int($dato)
            || is_float($dato)
        ) {
            $salida[] = (string)$dato;
            return;
        }

        if (!is_array($dato)) {
            return;
        }

        foreach ($dato as $item) {
            $recorrer($item);
        }
    };

    $recorrer($valor);

    return array_values(
        array_unique(
            array_filter(
                array_map(
                    static fn(string $texto): string =>
                        preg_replace(
                            '/\s+/u',
                            ' ',
                            trim($texto)
                        ) ?? trim($texto),
                    $salida
                ),
                static fn(string $texto): bool =>
                    $texto !== ''
            )
        )
    );
}

function construirMensajeCompletoSunat(
    string $mensaje,
    array $faults,
    array $notes
): string {
    $partes = [];
    $mensaje = trim($mensaje);

    if ($mensaje !== '') {
        $partes[] = $mensaje;
    }

    foreach ($faults as $fault) {
        if (
            $fault !== ''
            && !str_contains(
                implode(' | ', $partes),
                $fault
            )
        ) {
            $partes[] = $fault;
        }
    }

    foreach ($notes as $note) {
        if (
            $note !== ''
            && !str_contains(
                implode(' | ', $partes),
                $note
            )
        ) {
            $partes[] = 'Nota: ' . $note;
        }
    }

    return implode(
        ' | ',
        $partes
    );
}

function resumirMensajeSunat(
    string $mensaje,
    int $longitud = 120
): string {
    $mensaje = trim(
        preg_replace(
            '/\s+/u',
            ' ',
            $mensaje
        ) ?? $mensaje
    );

    if (
        function_exists('mb_strlen')
        && mb_strlen($mensaje, 'UTF-8') > $longitud
    ) {
        return mb_substr(
            $mensaje,
            0,
            $longitud - 1,
            'UTF-8'
        ) . '…';
    }

    if (strlen($mensaje) > $longitud) {
        return substr(
            $mensaje,
            0,
            $longitud - 1
        ) . '…';
    }

    return $mensaje;
}


/*
|--------------------------------------------------------------------------
| ACCIÓN PRINCIPAL DEL COMPROBANTE
|--------------------------------------------------------------------------
| Se muestra una sola acción útil y neutral:
| - Enviar
| - Reintentar
| - Consultar estado
*/
function generarAccionPrincipalSunat(
    int $idventa,
    string $estadoSunat,
    bool $tieneDocumentId
): string {
    $estadosReintento = [
        'RECHAZADO',
        'EXCEPCION',
        'ERROR'
    ];

    if (
        in_array(
            $estadoSunat,
            $estadosReintento,
            true
        )
    ) {
        $texto = 'Reintentar';
        $titulo = 'Reintentar el mismo comprobante con su numeración original';
        $icono = 'fa-redo-alt';
        $funcion = 'enviarSunatManual';
    } elseif (
        !$tieneDocumentId
        || $estadoSunat === 'NO_ENVIADO'
    ) {
        $texto = 'Enviar';
        $titulo = 'Enviar comprobante a SUNAT';
        $icono = 'fa-paper-plane';
        $funcion = 'enviarSunatManual';
    } else {
        $texto = 'Consultar';
        $titulo = 'Consultar estado actual en SUNAT';
        $icono = 'fa-sync-alt';
        $funcion = 'consultarSunatManual';
    }

    return '
        <button
            type="button"
            class="btn btn-outline-secondary btn-sm sunat-action-btn"
            title="' . escaparSunat($titulo) . '"
            onclick="' . $funcion . '(' . $idventa . ')">

            <i class="fas ' . $icono . ' mr-1"></i>
            ' . escaparSunat($texto) . '
        </button>
    ';
}


try {
    switch ($op) {

        case 'listar':

            $registros = $sunat->listar();
            $data = [];

            foreach ($registros as $reg) {
                $idventa = (int)(
                    $reg['idventa']
                    ?? 0
                );

                $documentId = trim(
                    (string)(
                        $reg['document_id']
                        ?? ''
                    )
                );

                $tieneDocumentId =
                    $documentId !== '';

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
                            class="sunat-file-link"
                            title="Descargar XML">
                            <i class="far fa-file-code mr-1"></i>
                            XML
                       </a>'
                    : '<span class="sunat-file-empty">—</span>';

                $cdr = $tieneCdr
                    ? '<a
                            href="Controllers/Sunat.php?op=descargar&tipo=cdr&idventa='
                        . $idventa
                        . '"
                            class="sunat-file-link"
                            title="Descargar CDR">
                            <i class="far fa-file-archive mr-1"></i>
                            CDR
                       </a>'
                    : '<span class="sunat-file-empty">—</span>';

                $estadoSunat = strtoupper(
                    trim(
                        (string)(
                            $reg['estado_sunat']
                            ?? ''
                        )
                    )
                );

                if (
                    !$tieneDocumentId
                    && $estadoSunat === ''
                ) {
                    $estadoSunat = 'NO_ENVIADO';
                }

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
                        $estado =
                            '<span class="badge-sunat sunat-rechazado">Rechazado</span>';
                        break;

                    case 'EXCEPCION':
                        $estado =
                            '<span class="badge-sunat sunat-rechazado">Excepción</span>';
                        break;

                    case 'ERROR':
                        $estado =
                            '<span class="badge-sunat sunat-error">Error</span>';
                        break;

                    case 'NO_ENVIADO':
                        $estado =
                            '<span class="badge-sunat sunat-pendiente">No enviado</span>';
                        break;

                    default:
                        $estado =
                            '<span class="badge-sunat sunat-pendiente">Pendiente</span>';
                        break;
                }

                $faults = decodificarMensajesSunat(
                    $reg['faults']
                    ?? []
                );

                $notes = decodificarMensajesSunat(
                    $reg['notes']
                    ?? []
                );

                $mensajeTexto = construirMensajeCompletoSunat(
                    trim(
                        (string)(
                            $reg['mensaje_sunat']
                            ?? ''
                        )
                    ),
                    $faults,
                    $notes
                );

                if ($mensajeTexto !== '') {
                    $mensajeResumen =
                        resumirMensajeSunat(
                            $mensajeTexto
                        );

                    $mensaje = '
                        <button
                            type="button"
                            class="sunat-response-button"
                            onclick="verDetalleSunat(' . $idventa . ')"
                            title="Ver respuesta completa de APISUNAT">
                            <span class="sunat-response-text">'
                                . escaparSunat($mensajeResumen)
                                . '</span>
                            <small>Ver detalle</small>
                        </button>
                    ';
                } else {
                    $mensaje =
                        '<span class="text-muted">—</span>';
                }

                $accion =
                    generarAccionPrincipalSunat(
                        $idventa,
                        $estadoSunat,
                        $tieneDocumentId
                    );

                /*
                 * Acciones se devuelve en la última posición,
                 * para que aparezca al extremo derecho.
                 */
                $data[] = [
                    '0' => escaparSunat(
                        (string)(
                            $reg['comprobante']
                            ?? ''
                        )
                    ),
                    '1' => escaparSunat(
                        (string)(
                            $reg['cliente']
                            ?? ''
                        )
                    ),
                    '2' => 'S/ '
                        . number_format(
                            (float)(
                                $reg['total']
                                ?? 0
                            ),
                            2
                        ),
                    '3' => $xml,
                    '4' => $cdr,
                    '5' => $estado,
                    '6' => $mensaje,
                    '7' => escaparSunat(
                        (string)(
                            $reg['fecha']
                            ?? ''
                        )
                    ),
                    '8' => $accion
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

            $estadoDetalle = strtoupper(
                trim(
                    (string)(
                        $detalle['estado_sunat']
                        ?? 'PENDIENTE'
                    )
                )
            );

            $faultsDetalle =
                decodificarMensajesSunat(
                    $detalle['faults']
                    ?? []
                );

            $notesDetalle =
                decodificarMensajesSunat(
                    $detalle['notes']
                    ?? []
                );

            $mensajeDetalle =
                construirMensajeCompletoSunat(
                    trim(
                        (string)(
                            $detalle['mensaje_sunat']
                            ?? ''
                        )
                    ),
                    $faultsDetalle,
                    $notesDetalle
                );

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
                    $estadoDetalle,
                'mensaje' =>
                    $mensajeDetalle,
                'faults' =>
                    $faultsDetalle,
                'notes' =>
                    $notesDetalle,
                'puede_reintentar' =>
                    in_array(
                        $estadoDetalle,
                        [
                            'RECHAZADO',
                            'EXCEPCION',
                            'ERROR'
                        ],
                        true
                    ),
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
                'status' => ($resultado['success'] ?? false)
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
                'status' => ($resultado['success'] ?? false)
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
