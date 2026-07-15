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
        | OBTENER CONTEXTO
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

                    'nombre' => (string)(
                        $_SESSION['nombre']
                        ?? ''
                    ),

                    'login' => (string)(
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

                    'idcaja_preparada' =>
                        (int)(
                            $_SESSION[
                                'idcaja_preparada'
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

        /*
        |--------------------------------------------------------------------------
        | SELECCIONAR CAJA
        |--------------------------------------------------------------------------
        */
        case 'seleccionar':

            if (
                ($_SERVER['REQUEST_METHOD'] ?? '')
                !== 'POST'
            ) {
                responderContextoCaja([
                    'success' => false,
                    'mensaje' =>
                        'La selección requiere una petición POST.'
                ], 405);
            }

            if ($idsucursal <= 0) {
                responderContextoCaja([
                    'success' => false,
                    'mensaje' =>
                        'No existe una sucursal activa.'
                ], 422);
            }

            $idcaja = (int)(
                $_POST['idcaja']
                ?? 0
            );

            if ($idcaja <= 0) {
                responderContextoCaja([
                    'success' => false,
                    'mensaje' =>
                        'Seleccione una caja válida.'
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
                        'No existe configuración para la sucursal.'
                ], 404);
            }

            $modoReal = strtoupper(
                trim(
                    (string)(
                        $configuracion['modo']
                        ?? 'LEGACY'
                    )
                )
            );

            $modoObjetivo = strtoupper(
                trim(
                    (string)(
                        $configuracion[
                            'modo_objetivo'
                        ]
                        ?? ''
                    )
                )
            );

            $esMulticajaOperativo =
                $modoReal === 'MULTICAJA';

            $esPreparacionMulticaja =
                $modoReal === 'LEGACY'
                && $modoObjetivo === 'MULTICAJA';

            if (
                !$esMulticajaOperativo
                && !$esPreparacionMulticaja
            ) {
                responderContextoCaja([
                    'success' => false,
                    'mensaje' =>
                        'La selección manual de caja solo está disponible para Multicaja.'
                ], 422);
            }

            $cajaAutorizada =
                $configuracionCaja
                    ->obtenerCajaAutorizadaUsuario(
                        $idusuario,
                        $idsucursal,
                        $idcaja
                    );

            if (!$cajaAutorizada) {
                responderContextoCaja([
                    'success' => false,
                    'mensaje' =>
                        'No está autorizado para operar la caja seleccionada.'
                ], 403);
            }

            if ($esMulticajaOperativo) {
                $cajaAnterior = (int)(
                    $_SESSION[
                        'idcaja_activa'
                    ]
                    ?? 0
                );

                $_SESSION['idcaja_activa'] =
                    $idcaja;

                $_SESSION['idcaja_preparada'] =
                    0;

                /*
                 * Si cambia la caja, todavía no debe
                 * conservar una apertura de otra caja.
                 */
                if ($cajaAnterior !== $idcaja) {
                    $_SESSION['idapertura_activa'] =
                        0;
                }

                responderContextoCaja([
                    'success' => true,
                    'operativa' => true,
                    'mensaje' =>
                        'Caja seleccionada correctamente.',
                    'caja' =>
                        $cajaAutorizada,
                    'idcaja_activa' =>
                        $idcaja,
                    'idcaja_preparada' =>
                        0
                ]);
            }

            /*
             * En LEGACY solo se guarda para probar
             * la selección. No tiene efecto operativo.
             */
            $_SESSION['idcaja_preparada'] =
                $idcaja;

            responderContextoCaja([
                'success' => true,
                'operativa' => false,
                'mensaje' =>
                    'Caja seleccionada para preparación. El sistema continúa operando en modo LEGACY.',
                'caja' =>
                    $cajaAutorizada,
                'idcaja_activa' =>
                    (int)(
                        $_SESSION[
                            'idcaja_activa'
                        ]
                        ?? 0
                    ),
                'idcaja_preparada' =>
                    $idcaja
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
            'No se pudo procesar el contexto de caja.'
    ], 500);
}