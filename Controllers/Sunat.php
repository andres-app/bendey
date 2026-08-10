<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../Models/Sunat.php';
require_once __DIR__ . '/../Models/CreditNote.php';
require_once __DIR__ . '/../Models/ApiSunatStatus.php';
require_once __DIR__ . '/../Models/ApiSunatStorage.php';
require_once __DIR__ . '/../Models/ApiSunatEmission.php';
require_once __DIR__ . '/../Models/ApiSunatCreditNoteEmission.php';
require_once __DIR__ . '/../Models/ApiSunatCreditNoteStatus.php';

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
$creditNote = new CreditNote();
$op = trim((string)($_GET['op'] ?? ''));

function responderSunat(
    array $respuesta,
    int $codigo = 200
): void {
    http_response_code($codigo);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header('X-Content-Type-Options: nosniff');

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function escaparSunat(string $texto): string
{
    return htmlspecialchars(
        $texto,
        ENT_QUOTES,
        'UTF-8'
    );
}

function normalizarTipoRegistroSunat(
    mixed $valor
): string {
    $tipo = strtoupper(
        trim((string)$valor)
    );

    if (
        in_array(
            $tipo,
            [
                'NOTA',
                'NC',
                'NOTA_CREDITO',
                'NOTA-CREDITO'
            ],
            true
        )
    ) {
        return 'NOTA_CREDITO';
    }

    return 'VENTA';
}

function obtenerIdDocumentoSunat(): int
{
    return (int)(
        $_POST['id']
        ?? $_GET['id']
        ?? $_POST['iddocumento']
        ?? $_GET['iddocumento']
        ?? $_POST['idventa']
        ?? $_GET['idventa']
        ?? $_POST['idnota_credito']
        ?? $_GET['idnota_credito']
        ?? 0
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
        if (
            is_string($dato)
            || is_int($dato)
            || is_float($dato)
        ) {
            $texto = trim((string)$dato);

            if ($texto !== '') {
                $salida[] = $texto;
            }

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

    return implode(' | ', $partes);
}

function esRechazoDefinitivoSunat(
    string $estado,
    string $mensaje,
    array $faults = [],
    array $notes = []
): bool {
    $estado = strtoupper(trim($estado));

    if ($estado === 'RECHAZADO') {
        return true;
    }

    $texto = strtoupper(
        construirMensajeCompletoSunat(
            $mensaje,
            $faults,
            $notes
        )
    );

    $texto = strtr(
        $texto,
        [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U'
        ]
    );

    return str_contains($texto, '"CODE":"1033"')
        || str_contains($texto, '"CODE": "1033"')
        || str_contains($texto, 'CODE 1033')
        || str_contains($texto, 'CODIGO 1033')
        || str_contains($texto, 'NUMERACION REPETIDA')
        || (
            str_contains($texto, 'DOCUMENTO CON NUMERO')
            && str_contains($texto, 'YA EXISTE')
        );
}

function generarBadgeEstadoSunat(
    string $estadoSunat,
    string $mensaje = ''
): string {
    $estadoSunat = strtoupper(
        trim($estadoSunat)
    );

    $titulo = trim($mensaje) !== ''
        ? ' title="' . escaparSunat($mensaje) . '"'
        : '';

    switch ($estadoSunat) {
        case 'ACEPTADO':
            $clase = 'sunat-aceptado';
            $texto = 'Aceptado';
            break;

        case 'PENDIENTE':
        case 'EN_PROCESO':
            $clase = 'sunat-proceso';
            $texto = 'En proceso';
            break;

        case 'ENVIADO':
            $clase = 'sunat-enviado';
            $texto = 'Enviado';
            break;

        case 'RECHAZADO':
            $clase = 'sunat-rechazado';
            $texto = 'Rechazado';
            break;

        case 'EXCEPCION':
            $clase = 'sunat-rechazado';
            $texto = 'Excepción';
            break;

        case 'ERROR':
            $clase = 'sunat-error';
            $texto = 'Error';
            break;

        case 'RESUMEN_DIARIO':
            $clase = 'sunat-pendiente';
            $texto = 'Resumen diario';
            break;

        case 'NO_ENVIADO':
        default:
            $clase = 'sunat-pendiente';
            $texto = 'No enviado';
            $estadoSunat = 'NO_ENVIADO';
            break;
    }

    return '<span class="badge-sunat '
        . $clase
        . '" data-estado="'
        . escaparSunat($estadoSunat)
        . '"'
        . $titulo
        . '>'
        . escaparSunat($texto)
        . '</span>';
}

function generarAccionPrincipalSunat(
    string $tipoRegistro,
    int $idDocumento,
    string $estadoSunat,
    bool $tieneDocumentId,
    bool $rechazoDefinitivo
): string {
    $tipoRegistro = normalizarTipoRegistroSunat(
        $tipoRegistro
    );

    $estadoSunat = strtoupper(
        trim($estadoSunat)
    );

    if ($estadoSunat === 'RESUMEN_DIARIO') {
        return '
            <button
                type="button"
                class="btn btn-outline-secondary btn-sm sunat-action-btn"
                title="Pendiente de incluir en Resumen Diario"
                disabled>
                <i class="fas fa-layer-group mr-1"></i>
                Pendiente de resumen
            </button>
        ';
    }

    if ($estadoSunat === 'ACEPTADO') {
        $texto = 'Ver detalle';
        $titulo = 'Ver detalle SUNAT';
        $icono = 'fa-eye';
        $funcion = 'verDetalleSunat';
    } elseif (
        $rechazoDefinitivo
        || $estadoSunat === 'RECHAZADO'
    ) {
        $texto = 'Ver rechazo';
        $titulo = 'Ver el motivo del rechazo';
        $icono = 'fa-eye';
        $funcion = 'verDetalleSunat';
    } elseif (
        in_array(
            $estadoSunat,
            ['EXCEPCION', 'ERROR'],
            true
        )
    ) {
        $texto = 'Reintentar';
        $titulo = 'Reintentar después de un error técnico';
        $icono = 'fa-redo-alt';
        $funcion = 'enviarSunatManual';
    } elseif (
        !$tieneDocumentId
        || $estadoSunat === 'NO_ENVIADO'
    ) {
        $texto = 'Enviar';
        $titulo = 'Enviar documento a SUNAT';
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
            onclick="' . $funcion . '(\''
                . escaparSunat($tipoRegistro)
                . '\', '
                . $idDocumento
                . ')">

            <i class="fas ' . escaparSunat($icono) . ' mr-1"></i>
            ' . escaparSunat($texto) . '
        </button>
    ';
}

function construirCeldaDocumentoSunat(
    array $reg
): string {
    $comprobante = escaparSunat(
        (string)($reg['comprobante'] ?? '')
    );

    $tipoRegistro = normalizarTipoRegistroSunat(
        $reg['tipo_registro'] ?? 'VENTA'
    );

    $codigo = escaparSunat(
        (string)($reg['tipo_documento_sunat'] ?? '')
    );

    if ($tipoRegistro === 'NOTA_CREDITO') {
        $origen = escaparSunat(
            (string)($reg['comprobante_origen'] ?? '')
        );

        return '
            <div class="sunat-document-cell">
                <div class="sunat-document-main">
                    <span class="sunat-document-kind sunat-document-kind-note">
                        NC · ' . $codigo . '
                    </span>
                    <strong>' . $comprobante . '</strong>
                </div>
                <small>Modifica ' . ($origen !== '' ? $origen : '—') . '</small>
            </div>
        ';
    }

    $tipoTexto = (string)($reg['tipo_documento'] ?? 'Comprobante');
    $tipoCorto = str_contains(
        mb_strtoupper($tipoTexto, 'UTF-8'),
        'FACTURA'
    )
        ? 'Factura'
        : 'Boleta';

    return '
        <div class="sunat-document-cell">
            <div class="sunat-document-main">
                <span class="sunat-document-kind">
                    ' . escaparSunat($tipoCorto) . ' · ' . $codigo . '
                </span>
                <strong>' . $comprobante . '</strong>
            </div>
        </div>
    ';
}

try {
    switch ($op) {
        case 'listar':
            $registros = $sunat->listar();
            $data = [];

            foreach ($registros as $reg) {
                $tipoRegistro = normalizarTipoRegistroSunat(
                    $reg['tipo_registro'] ?? 'VENTA'
                );

                $idDocumento = (int)(
                    $reg['iddocumento']
                    ?? 0
                );

                if ($idDocumento <= 0) {
                    continue;
                }

                $documentId = trim(
                    (string)($reg['document_id'] ?? '')
                );

                $estadoSunat = strtoupper(
                    trim(
                        (string)(
                            $reg['estado_sunat']
                            ?? 'NO_ENVIADO'
                        )
                    )
                );

                if ($estadoSunat === '') {
                    $estadoSunat = 'NO_ENVIADO';
                }

                $faults = decodificarMensajesSunat(
                    $reg['faults'] ?? []
                );

                $notes = decodificarMensajesSunat(
                    $reg['notes'] ?? []
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

                $rechazoDefinitivo = esRechazoDefinitivoSunat(
                    $estadoSunat,
                    $mensajeTexto,
                    $faults,
                    $notes
                );

                /*
                 * El rechazo definitivo solo controla la acción disponible.
                 * El estado visible se conserva exactamente como está guardado
                 * en venta_sunat / nota_credito_sunat.
                 */
                $data[] = [
                    '0' => construirCeldaDocumentoSunat($reg),
                    '1' => escaparSunat(
                        (string)($reg['cliente'] ?? '')
                    ),
                    '2' => 'S/ ' . number_format(
                        (float)($reg['total'] ?? 0),
                        2
                    ),
                    '3' => generarBadgeEstadoSunat(
                        $estadoSunat,
                        $mensajeTexto
                    ),
                    '4' => escaparSunat(
                        (string)($reg['fecha'] ?? '')
                    ),
                    '5' => generarAccionPrincipalSunat(
                        $tipoRegistro,
                        $idDocumento,
                        $estadoSunat,
                        $documentId !== '',
                        $rechazoDefinitivo
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
            $tipoRegistro = normalizarTipoRegistroSunat(
                $_POST['tipo_registro']
                ?? $_GET['tipo_registro']
                ?? 'VENTA'
            );

            $idDocumento = obtenerIdDocumentoSunat();

            $detalle = $tipoRegistro === 'NOTA_CREDITO'
                ? $sunat->detalleNotaCredito($idDocumento)
                : $sunat->detalle($idDocumento);

            if (!$detalle) {
                responderSunat([
                    'status' => false,
                    'message' => 'No se encontró el documento electrónico.'
                ], 404);
            }

            $estadoDetalle = strtoupper(
                trim(
                    (string)(
                        $detalle['estado_sunat']
                        ?? 'NO_ENVIADO'
                    )
                )
            );

            $faultsDetalle = decodificarMensajesSunat(
                $detalle['faults'] ?? []
            );

            $notesDetalle = decodificarMensajesSunat(
                $detalle['notes'] ?? []
            );

            $mensajeDetalle = construirMensajeCompletoSunat(
                trim(
                    (string)(
                        $detalle['mensaje_sunat']
                        ?? ''
                    )
                ),
                $faultsDetalle,
                $notesDetalle
            );

            $rechazoDefinitivoDetalle = esRechazoDefinitivoSunat(
                $estadoDetalle,
                $mensajeDetalle,
                $faultsDetalle,
                $notesDetalle
            );

            /*
             * No se reescribe el estado por interpretación del mensaje.
             * El detalle y Listsales deben mostrar el mismo estado persistido.
             */
            $tieneXml = !empty($detalle['xml'])
                || !empty($detalle['xml_local']);

            $tieneCdr = !empty($detalle['cdr'])
                || !empty($detalle['cdr_local']);

            $parametrosBase = http_build_query([
                'op' => 'descargar',
                'tipo_registro' => $tipoRegistro,
                'id' => $idDocumento
            ]);

            responderSunat([
                'status' => true,
                'tipo_registro' => $tipoRegistro,
                'iddocumento' => $idDocumento,
                'tipo_documento' =>
                    $detalle['tipo_documento'] ?? '',
                'tipo_documento_sunat' =>
                    $detalle['tipo_documento_sunat'] ?? '',
                'comprobante' =>
                    $detalle['comprobante'] ?? '',
                'comprobante_origen' =>
                    $detalle['comprobante_origen'] ?? '',
                'cliente' =>
                    $detalle['cliente'] ?? '',
                'total' => number_format(
                    (float)($detalle['total'] ?? 0),
                    2
                ),
                'documentId' =>
                    $detalle['document_id'] ?? '',
                'estado' => $estadoDetalle,
                'mensaje' => $mensajeDetalle,
                'faults' => $faultsDetalle,
                'notes' => $notesDetalle,
                'rechazo_definitivo' =>
                    $rechazoDefinitivoDetalle,
                'puede_reintentar' =>
                    !$rechazoDefinitivoDetalle
                    && in_array(
                        $estadoDetalle,
                        ['EXCEPCION', 'ERROR'],
                        true
                    ),
                'tiene_xml' => $tieneXml,
                'tiene_cdr' => $tieneCdr,
                'xml_url' => $tieneXml
                    ? 'Controllers/Sunat.php?'
                        . $parametrosBase
                        . '&tipo=xml'
                    : '',
                'cdr_url' => $tieneCdr
                    ? 'Controllers/Sunat.php?'
                        . $parametrosBase
                        . '&tipo=cdr'
                    : ''
            ]);
            break;

        case 'consultar':
        case 'getStatus':
            $tipoRegistro = normalizarTipoRegistroSunat(
                $_POST['tipo_registro']
                ?? $_GET['tipo_registro']
                ?? 'VENTA'
            );

            $idDocumento = obtenerIdDocumentoSunat();

            if ($idDocumento <= 0) {
                responderSunat([
                    'status' => false,
                    'message' => 'El documento seleccionado no es válido.'
                ], 422);
            }

            if ($tipoRegistro === 'NOTA_CREDITO') {
                $servicio = new ApiSunatCreditNoteStatus();
                $resultado = $servicio->consultarYGuardar(
                    $idDocumento
                );
            } else {
                $servicio = new ApiSunatStatus();
                $resultado = $servicio->consultarYGuardar(
                    $idDocumento
                );
            }

            responderSunat([
                'status' => ($resultado['success'] ?? false) === true,
                'message' => $resultado['mensaje'] ?? '',
                'resultado' => $resultado
            ]);
            break;

        case 'enviarsunat':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderSunat([
                    'status' => false,
                    'message' => 'El envío requiere una petición POST.'
                ], 405);
            }

            $tipoRegistro = normalizarTipoRegistroSunat(
                $_POST['tipo_registro']
                ?? $_GET['tipo_registro']
                ?? 'VENTA'
            );

            $idDocumento = obtenerIdDocumentoSunat();

            if ($idDocumento <= 0) {
                responderSunat([
                    'status' => false,
                    'message' => 'El documento seleccionado no es válido.'
                ], 422);
            }

            if ($tipoRegistro === 'NOTA_CREDITO') {
                $emision = new ApiSunatCreditNoteEmission();
                $resultado = $emision->enviar($idDocumento);
            } else {
                $emision = new ApiSunatEmission();
                $resultado = $emision->enviarVenta($idDocumento);
            }

            responderSunat([
                'status' => ($resultado['success'] ?? false) === true,
                'message' => $resultado['mensaje'] ?? '',
                'resultado' => $resultado
            ]);
            break;

        case 'descargar':
            $tipoRegistro = normalizarTipoRegistroSunat(
                $_GET['tipo_registro']
                ?? 'VENTA'
            );

            $idDocumento = obtenerIdDocumentoSunat();

            $tipo = strtolower(
                trim((string)($_GET['tipo'] ?? ''))
            );

            if (
                $idDocumento <= 0
                || !in_array($tipo, ['xml', 'cdr'], true)
            ) {
                throw new RuntimeException(
                    'Solicitud de descarga inválida.'
                );
            }

            $archivo = $tipoRegistro === 'NOTA_CREDITO'
                ? $creditNote->obtenerArchivo(
                    $idDocumento,
                    $tipo
                )
                : $sunat->obtenerArchivo(
                    $idDocumento,
                    $tipo
                );

            if (!$archivo) {
                throw new RuntimeException(
                    'No se encontró el archivo solicitado.'
                );
            }

            $storage = new ApiSunatStorage();

            $rutaLocal = trim(
                (string)($archivo['ruta_local'] ?? '')
            );

            if (!$storage->existe($rutaLocal)) {
                $url = trim(
                    (string)($archivo['url'] ?? '')
                );

                if ($url === '') {
                    throw new RuntimeException(
                        'El documento todavía no tiene el archivo disponible.'
                    );
                }

                $rutaLocal = $storage->guardarDesdeUrl(
                    $url,
                    $tipo
                );

                if ($tipoRegistro === 'NOTA_CREDITO') {
                    $creditNote->actualizarRutaLocal(
                        $idDocumento,
                        $tipo,
                        $rutaLocal
                    );
                } else {
                    $sunat->actualizarRutaLocal(
                        $idDocumento,
                        $tipo,
                        $rutaLocal
                    );
                }
            }

            $rutaAbsoluta = $storage->rutaAbsoluta(
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

            header('Content-Type: application/zip');
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

        case 'contarPendientes':
            responderSunat([
                'status' => true,
                'cantidad' => $sunat->contarPendientes()
            ]);
            break;

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
                'message' => 'Operación no válida.'
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
