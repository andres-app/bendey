<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__
    . '/../Models/ConfiguracionCaja.php';

date_default_timezone_set(
    'America/Lima'
);

$configuracionCaja =
    new ConfiguracionCaja();

$op = trim(
    (string)($_GET['op'] ?? '')
);

function responderContextoCaja(
    array $respuesta,
    int $codigoHttp = 200
): void {
    http_response_code($codigoHttp);

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

$idusuario = (int)(
    $_SESSION['idusuario']
    ?? 0
);

$idsucursal = (int)(
    $_SESSION['idsucursal_activa']
    ?? 0
);

if ($idusuario <= 0) {
    responderContextoCaja([
        'success' => false,
        'mensaje' =>
            'La sesión del usuario no es válida.'
    ], 403);
}

try {
    switch ($op) {

        /*
        |--------------------------------------------------------------------------
        | OBTENER CONTEXTO OPERATIVO
        |--------------------------------------------------------------------------
        */
        case 'obtener':

            if ($idsucursal <= 0) {
                responderContextoCaja([
                    'success' => false,
                    'mensaje' =>
                        'No existe una sucursal activa en la sesión.'
                ], 422);
            }

            $configuracion =
                $configuracionCaja
                    ->obtenerPorSucursal(
                        $idsucursal
                    );

            if (!$configuracion) {
                responderContextoCaja([
                    'success' => false,
                    'mensaje' =>
                        'No existe configuración para la sucursal activa.'
                ], 404);
            }

            $cajas =
                $configuracionCaja
                    ->listarCajasAutorizadasUsuario(
                        $idusuario,
                        $idsucursal
                    );

            responderContextoCaja([
                'success' => true,

                'usuario' => [
                    'idusuario' => $idusuario,
                    'nombre' =>
                        (string)(
                            $_SESSION['nombre']
                            ?? ''
                        ),
                    'login' =>
                        (string)(
                            $_SESSION['login']
                            ?? ''
                        )
                ],

                'contexto' => [
                    'idsucursal' =>
                        $idsucursal,

                    'codigo_sucursal' =>
                        (string)(
                            $configuracion[
                                'codigo_sucursal'
                            ]
                            ?? ''
                        ),

                    'modo' =>
                        (string)(
                            $configuracion['modo']
                            ?? 'LEGACY'
                        ),

                    'modo_objetivo' =>
                        (string)(
                            $configuracion[
                                'modo_objetivo'
                            ]
                            ?? ''
                        ),

                    'idcaja_unica' =>
                        (int)(
                            $configuracion[
                                'idcaja_unica'
                            ]
                            ?? 0
                        ),

                    'idcaja_activa' =>
                        (int)(
                            $_SESSION[
                                'idcaja_activa'
                            ]
                            ?? 0
                        ),

                    'idapertura_activa' =>
                        (int)(
                            $_SESSION[
                                'idapertura_activa'
                            ]
                            ?? 0
                        )
                ],

                'total_cajas' =>
                    count($cajas),

                'cajas' =>
                    $cajas
            ]);

            break;

        default:

            responderContextoCaja([
                'success' => false,
                'mensaje' =>
                    'Operación no válida.'
            ], 404);
    }
} catch (Throwable $e) {
    error_log(
        '[CONTEXTO CAJA] '
        . $e->getMessage()
        . ' | Archivo: '
        . $e->getFile()
        . ' | Línea: '
        . $e->getLine()
    );

    responderContextoCaja([
        'success' => false,
        'mensaje' =>
            'No se pudo obtener el contexto de caja.'
    ], 500);
}