<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Cajachica.php';

date_default_timezone_set('America/Lima');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$caja = new Cajachica();
$op = $_GET['op'] ?? '';

function responderCaja(array $respuesta): void
{
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

$idusuarioSesion = (int)(
    $_SESSION['idusuario']
    ?? 0
);

if ($idusuarioSesion <= 0) {
    responderCaja([
        'status' => 'error',
        'message' => 'La sesión del usuario no es válida.'
    ]);
}

try {
    switch ($op) {
        /*
        |--------------------------------------------------------------------------
        | RESUMEN
        |--------------------------------------------------------------------------
        */
        case 'resumen':

            $fechaInicio = trim(
                (string)(
                    $_GET['fecha_inicio']
                    ?? date('Y-m-d')
                )
            );

            $fechaFin = trim(
                (string)(
                    $_GET['fecha_fin']
                    ?? date('Y-m-d')
                )
            );

            $idusuarioFiltro = isset($_GET['idusuario'])
                && (int)$_GET['idusuario'] > 0
                    ? (int)$_GET['idusuario']
                    : null;

            $detalle = $caja->resumen(
                $fechaInicio,
                $fechaFin,
                $idusuarioFiltro
            );

            $totales = $caja->totales(
                $fechaInicio,
                $fechaFin,
                $idusuarioFiltro
            );

            $usuarioApertura =
                $idusuarioFiltro
                ?? $idusuarioSesion;

            $apertura =
                $caja->obtenerAperturaPorFecha(
                    $fechaInicio,
                    $usuarioApertura
                );

            $aperturaHoy =
                $caja->obtenerAperturaHoyUsuario(
                    $idusuarioSesion
                );

            $cajaAbierta =
                $caja->obtenerCajaAbiertaUsuario(
                    $idusuarioSesion
                );

            $estado = 'SIN_APERTURA';

            if ($cajaAbierta) {
                $estado = 'ABIERTA';
            } elseif ($aperturaHoy) {
                $estado = strtoupper(
                    (string)$aperturaHoy['estado']
                );
            }

            responderCaja([
                'status' => 'ok',
                'detalle' => $detalle,
                'totales' => $totales,
                'apertura' => $apertura,
                'estado' => $estado
            ]);

            break;

        /*
        |--------------------------------------------------------------------------
        | VERIFICAR APERTURA
        |--------------------------------------------------------------------------
        */
        case 'verificar_apertura':

            $cantidad =
                $caja->contarCajasAbiertasUsuario(
                    $idusuarioSesion
                );

            if ($cantidad > 1) {
                responderCaja([
                    'status' => 'error',
                    'existe' => true,
                    'estado' => 'ERROR',
                    'message' =>
                        'Se encontraron varias cajas abiertas para el usuario.'
                ]);
            }

            $apertura =
                $caja->obtenerCajaAbiertaUsuario(
                    $idusuarioSesion
                );

            responderCaja([
                'status' => 'ok',
                'existe' => $apertura !== null,
                'estado' => $apertura
                    ? 'ABIERTA'
                    : 'SIN_APERTURA',
                'apertura' => $apertura
            ]);

            break;

        /*
        |--------------------------------------------------------------------------
        | GUARDAR APERTURA
        |--------------------------------------------------------------------------
        */
        case 'guardar_apertura':

            $monto = round(
                (float)(
                    $_POST['monto']
                    ?? 0
                ),
                2
            );

            $resultado =
                $caja->registrarApertura(
                    $monto,
                    $idusuarioSesion
                );

            responderCaja($resultado);

            break;

        /*
        |--------------------------------------------------------------------------
        | DATOS PARA CIERRE
        |--------------------------------------------------------------------------
        */
        case 'datos_cierre':

            $cantidad =
                $caja->contarCajasAbiertasUsuario(
                    $idusuarioSesion
                );

            if ($cantidad === 0) {
                responderCaja([
                    'status' => false,
                    'message' =>
                        'No existe una caja abierta.'
                ]);
            }

            if ($cantidad > 1) {
                responderCaja([
                    'status' => false,
                    'message' =>
                        'Se encontraron varias cajas abiertas.'
                ]);
            }

            $apertura =
                $caja->obtenerCajaAbiertaUsuario(
                    $idusuarioSesion
                );

            if (!$apertura) {
                responderCaja([
                    'status' => false,
                    'message' =>
                        'No existe una caja abierta.'
                ]);
            }

            $totales =
                $caja->calcularTotalesApertura(
                    (int)$apertura['idapertura']
                );

            responderCaja([
                'status' => true,
                'apertura' => $apertura,
                'monto_apertura' =>
                    $totales['monto_apertura'],
                'ventas_efectivo' =>
                    $totales['ventas_efectivo'],
                'otros_ingresos_efectivo' =>
                    $totales['otros_ingresos_efectivo'],
                'egresos_efectivo' =>
                    $totales['egresos_efectivo'],
                'total_sistema' =>
                    $totales['total_sistema']
            ]);

            break;

        /*
        |--------------------------------------------------------------------------
        | CERRAR CAJA
        |--------------------------------------------------------------------------
        */
        case 'cerrar_caja':

            $montoContado = round(
                (float)(
                    $_POST['monto_contado']
                    ?? 0
                ),
                2
            );

            $resultado =
                $caja->cerrarCaja(
                    $montoContado,
                    $idusuarioSesion
                );

            responderCaja($resultado);

            break;

        default:

            responderCaja([
                'status' => 'error',
                'message' => 'Operación no válida.'
            ]);
    }
} catch (Throwable $e) {
    error_log(
        '[CONTROLADOR CAJA] '
        . $e->getMessage()
    );

    responderCaja([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}