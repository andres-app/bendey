<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../Models/ConfiguracionCaja.php';

$configuracionCaja = new ConfiguracionCaja();

$op = trim(
    (string)($_GET['op'] ?? '')
);

/*
|--------------------------------------------------------------------------
| RESPUESTA JSON
|--------------------------------------------------------------------------
*/
function responderConfiguracionCajaJson(
    mixed $respuesta,
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

/*
|--------------------------------------------------------------------------
| VALIDAR SESIÓN
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['nombre'])) {
    responderConfiguracionCajaJson([
        'success' => false,
        'mensaje' => 'Acceso no autorizado.'
    ], 403);
}

/*
|--------------------------------------------------------------------------
| VALIDAR PERMISO DE CONFIGURACIÓN
|--------------------------------------------------------------------------
*/
if (
    (int)($_SESSION['settings'] ?? 0)
    !== 1
) {
    responderConfiguracionCajaJson([
        'success' => false,
        'mensaje' =>
            'No tiene permiso para acceder a la configuración de caja.'
    ], 403);
}

try {
    switch ($op) {

        /*
        |--------------------------------------------------------------------------
        | OBTENER CONFIGURACIÓN ACTUAL
        |--------------------------------------------------------------------------
        */
        case 'obtener':

            $configuracion =
                $configuracionCaja
                    ->obtenerSucursalPrincipal();

            if (!$configuracion) {
                responderConfiguracionCajaJson([
                    'success' => false,
                    'mensaje' =>
                        'No se encontró una sucursal principal activa.'
                ], 404);
            }

            $idsucursal = (int)(
                $configuracion['idsucursal']
                ?? 0
            );

            if ($idsucursal <= 0) {
                responderConfiguracionCajaJson([
                    'success' => false,
                    'mensaje' =>
                        'La sucursal principal no es válida.'
                ], 500);
            }

            $cajas =
                $configuracionCaja
                    ->listarCajasActivas(
                        $idsucursal
                    );

            responderConfiguracionCajaJson([
                'success' => true,
                'configuracion' => $configuracion,
                'cajas' => $cajas,
                'total_cajas' => count($cajas)
            ]);

            break;

        /*
        |--------------------------------------------------------------------------
        | OPERACIÓN INVÁLIDA
        |--------------------------------------------------------------------------
        */
        default:

            responderConfiguracionCajaJson([
                'success' => false,
                'mensaje' =>
                    'Operación no válida.'
            ], 404);
    }
} catch (Throwable $e) {
    error_log(
        '[CONFIGURACION CAJA CONTROLLER] '
        . $e->getMessage()
        . ' | Archivo: '
        . $e->getFile()
        . ' | Línea: '
        . $e->getLine()
    );

    responderConfiguracionCajaJson([
        'success' => false,
        'mensaje' => $e->getMessage()
    ], 500);
}