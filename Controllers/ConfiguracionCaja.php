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

            $cajasGestion =
                $configuracionCaja
                    ->listarCajasGestion(
                        $idsucursal
                    );

            responderConfiguracionCajaJson([
                'success' => true,
                'configuracion' => $configuracion,
                'cajas' => $cajas,
                'cajas_gestion' => $cajasGestion,
                'total_cajas' => count($cajas),
                'aperturas_abiertas' =>
                    $configuracionCaja
                        ->contarAperturasAbiertasSucursal(
                            $idsucursal
                        )
            ]);

            break;

        /*
        |--------------------------------------------------------------------------
        | CAMBIAR MODALIDAD DE CAJA
        |--------------------------------------------------------------------------
        | Actualiza la modalidad operativa únicamente si no existen
        | aperturas activas en la sucursal.
        |--------------------------------------------------------------------------
        */
        case 'guardar_preferencia':

            if (
                ($_SERVER['REQUEST_METHOD'] ?? '')
                !== 'POST'
            ) {
                responderConfiguracionCajaJson([
                    'success' => false,
                    'mensaje' =>
                        'La operación requiere una petición POST.'
                ], 405);
            }

            $idsucursal = (int)(
                $_POST['idsucursal']
                ?? 0
            );

            $modoObjetivo = strtoupper(
                trim(
                    (string)(
                        $_POST['modo_objetivo']
                        ?? ''
                    )
                )
            );

            $idcajaUnica = (int)(
                $_POST['idcaja_unica']
                ?? 0
            );

            if ($idsucursal <= 0) {
                throw new RuntimeException(
                    'La sucursal seleccionada no es válida.'
                );
            }

            if (
                !in_array(
                    $modoObjetivo,
                    [
                        'CAJA_UNICA',
                        'MULTICAJA'
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Seleccione Caja única o Multicaja.'
                );
            }

            if (
                $modoObjetivo === 'CAJA_UNICA'
                && $idcajaUnica <= 0
            ) {
                throw new RuntimeException(
                    'Seleccione una caja principal válida.'
                );
            }

            $resultado =
                $configuracionCaja
                    ->cambiarModalidad(
                        $idsucursal,
                        $modoObjetivo,
                        $idcajaUnica
                    );

            if (!$resultado) {
                throw new RuntimeException(
                    'No se pudo actualizar la modalidad de caja.'
                );
            }

            $configuracionActualizada =
                $configuracionCaja
                    ->obtenerPorSucursal(
                        $idsucursal
                    );

            if (!is_array($configuracionActualizada)) {
                throw new RuntimeException(
                    'La modalidad fue guardada, pero no se pudo recargar su configuración.'
                );
            }

            /*
             * La sesión que realiza el cambio debe abandonar cualquier
             * contexto de caja anterior inmediatamente.
             */
            $_SESSION['idsucursal_activa'] = $idsucursal;
            $_SESSION['modo_caja'] = $modoObjetivo;
            $_SESSION['modo_caja_objetivo'] = $modoObjetivo;
            $_SESSION['idapertura_activa'] = 0;

            if ($modoObjetivo === 'CAJA_UNICA') {
                $idcajaAplicada = (int)(
                    $configuracionActualizada['idcaja_unica']
                    ?? $idcajaUnica
                );

                $_SESSION['idcaja_activa'] = $idcajaAplicada;
                $_SESSION['idcaja_preparada'] = $idcajaAplicada;
            } else {
                $_SESSION['idcaja_activa'] = 0;
                $_SESSION['idcaja_preparada'] = 0;
            }

            responderConfiguracionCajaJson([
                'success' => true,
                'mensaje' =>
                    $modoObjetivo === 'CAJA_UNICA'
                        ? 'Caja única activada correctamente.'
                        : 'Multicaja activado correctamente.',
                'configuracion' =>
                    $configuracionActualizada,
                'aperturas_abiertas' => 0,
                'requiere_recarga' => true,
                'aviso_sesiones' =>
                    'Los demás usuarios que ya estaban conectados deben volver a iniciar sesión antes de operar.'
            ]);

            break;

        /*
        |--------------------------------------------------------------------------
        | CREAR / EDITAR CAJA FÍSICA
        |--------------------------------------------------------------------------
        */
        case 'guardar_caja':

            if (
                ($_SERVER['REQUEST_METHOD'] ?? '')
                !== 'POST'
            ) {
                responderConfiguracionCajaJson([
                    'success' => false,
                    'mensaje' =>
                        'La operación requiere una petición POST.'
                ], 405);
            }

            $idsucursal = (int)(
                $_POST['idsucursal']
                ?? 0
            );

            $idcaja = (int)(
                $_POST['idcaja']
                ?? 0
            );

            $nombre = trim(
                (string)(
                    $_POST['nombre']
                    ?? ''
                )
            );

            $descripcion = trim(
                (string)(
                    $_POST['descripcion']
                    ?? ''
                )
            );

            $permiteEfectivo =
                (int)(
                    $_POST['permite_efectivo']
                    ?? 0
                ) === 1;

            if ($idcaja > 0) {
                $resultado =
                    $configuracionCaja
                        ->editarCaja(
                            $idsucursal,
                            $idcaja,
                            $nombre,
                            $descripcion,
                            $permiteEfectivo
                        );

                responderConfiguracionCajaJson([
                    'success' => (bool)$resultado,
                    'mensaje' =>
                        'Caja actualizada correctamente.'
                ]);
            }

            $cajaCreada =
                $configuracionCaja
                    ->crearCaja(
                        $idsucursal,
                        $nombre,
                        $descripcion,
                        $permiteEfectivo
                    );

            responderConfiguracionCajaJson([
                'success' => true,
                'mensaje' =>
                    'Caja física creada correctamente.',
                'caja' => $cajaCreada,
                'aviso' =>
                    'La nueva caja debe asignarse a los usuarios que podrán operarla desde el módulo Usuarios.'
            ]);

            break;

        /*
        |--------------------------------------------------------------------------
        | ACTIVAR / DESACTIVAR CAJA FÍSICA
        |--------------------------------------------------------------------------
        */
        case 'cambiar_estado_caja':

            if (
                ($_SERVER['REQUEST_METHOD'] ?? '')
                !== 'POST'
            ) {
                responderConfiguracionCajaJson([
                    'success' => false,
                    'mensaje' =>
                        'La operación requiere una petición POST.'
                ], 405);
            }

            $idsucursal = (int)(
                $_POST['idsucursal']
                ?? 0
            );

            $idcaja = (int)(
                $_POST['idcaja']
                ?? 0
            );

            $activar =
                (int)(
                    $_POST['activar']
                    ?? 0
                ) === 1;

            $resultado =
                $configuracionCaja
                    ->cambiarEstadoCaja(
                        $idsucursal,
                        $idcaja,
                        $activar
                    );

            responderConfiguracionCajaJson([
                'success' => (bool)$resultado,
                'mensaje' =>
                    $activar
                        ? 'Caja activada correctamente.'
                        : 'Caja desactivada correctamente.'
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
} catch (RuntimeException $e) {
    responderConfiguracionCajaJson([
        'success' => false,
        'mensaje' => $e->getMessage()
    ], 422);
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
        'mensaje' =>
            'Ocurrió un error interno al procesar la configuración de caja.'
    ], 500);
}