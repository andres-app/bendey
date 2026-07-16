<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Cajachica.php';
require_once __DIR__ . '/../Models/ConfiguracionCaja.php';

date_default_timezone_set('America/Lima');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$caja = new Cajachica();
$configuracionCaja = new ConfiguracionCaja();
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

/*
|--------------------------------------------------------------------------
| DESTRUIR SESIÓN DESPUÉS DEL CIERRE
|--------------------------------------------------------------------------
*/
function destruirSesionDespuesDeCerrarCaja(): void
{
    $_SESSION = [];

    if (
        ini_get('session.use_cookies')
    ) {
        $parametrosCookie =
            session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parametrosCookie['path'],
            $parametrosCookie['domain'],
            $parametrosCookie['secure'],
            $parametrosCookie['httponly']
        );
    }

    if (
        session_status()
        === PHP_SESSION_ACTIVE
    ) {
        session_destroy();
    }
}

$idusuarioSesion = (int)(
    $_SESSION['idusuario']
    ?? 0
);

$idsucursalSesion = (int)(
    $_SESSION['idsucursal_activa']
    ?? 0
);

$modoCajaSesion = strtoupper(
    trim(
        (string)(
            $_SESSION['modo_caja']
            ?? 'LEGACY'
        )
    )
);

if (
    !in_array(
        $modoCajaSesion,
        [
            'LEGACY',
            'CAJA_UNICA',
            'MULTICAJA'
        ],
        true
    )
) {
    $modoCajaSesion = 'LEGACY';
}

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

            /*
            |--------------------------------------------------------------------------
            | LEGACY
            |--------------------------------------------------------------------------
            | Conserva el resumen por usuario.
            |--------------------------------------------------------------------------
            */
            if ($modoCajaSesion === 'LEGACY') {
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
                    'modo' => 'LEGACY',
                    'detalle' => $detalle,
                    'totales' => $totales,
                    'apertura' => $apertura,
                    'estado' => $estado
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | CAJA ÚNICA Y MULTICAJA
            |--------------------------------------------------------------------------
            */
            if ($idsucursalSesion <= 0) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'No existe una sucursal activa en la sesión.'
                ]);
            }

            $configuracion =
                $configuracionCaja->obtenerPorSucursal(
                    $idsucursalSesion
                );

            if (!$configuracion) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'No existe configuración para la sucursal activa.'
                ]);
            }

            $idcajaOperacion = 0;

            if ($modoCajaSesion === 'CAJA_UNICA') {
                $idcajaOperacion = (int)(
                    $configuracion['idcaja_unica']
                    ?? 0
                );

                if ($idcajaOperacion <= 0) {
                    responderCaja([
                        'status' => 'error',
                        'message' =>
                        'No existe una caja única configurada.'
                    ]);
                }

                $_SESSION['idcaja_activa'] =
                    $idcajaOperacion;
            }

            if ($modoCajaSesion === 'MULTICAJA') {
                $idcajaOperacion = (int)(
                    $_SESSION['idcaja_activa']
                    ?? 0
                );

                if ($idcajaOperacion <= 0) {
                    $_SESSION['idapertura_activa'] = 0;

                    responderCaja([
                        'status' => 'ok',
                        'modo' => 'MULTICAJA',
                        'detalle' => [],
                        'totales' => [
                            'ingresos' => 0,
                            'efectivo' => 0,
                            'no_efectivo' => 0,
                            'egresos' => 0,
                            'egresos_efectivo' => 0
                        ],
                        'apertura' => null,
                        'estado' => 'SIN_CAJA_SELECCIONADA',
                        'necesita_seleccion' => true
                    ]);
                }
            }

            $cajaAutorizada =
                $configuracionCaja
                ->obtenerCajaAutorizadaUsuario(
                    $idusuarioSesion,
                    $idsucursalSesion,
                    $idcajaOperacion
                );

            if (!$cajaAutorizada) {
                $_SESSION['idapertura_activa'] = 0;

                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'No está autorizado para operar la caja seleccionada.'
                ]);
            }

            $cantidad =
                $caja->contarCajasAbiertasFisica(
                    $idcajaOperacion
                );

            if ($cantidad > 1) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'Se encontraron varias aperturas activas para la misma caja física.'
                ]);
            }

            $apertura =
                $caja->obtenerCajaAbiertaFisica(
                    $idcajaOperacion
                );

            if (!$apertura) {
                $_SESSION['idapertura_activa'] = 0;

                responderCaja([
                    'status' => 'ok',
                    'modo' => $modoCajaSesion,
                    'caja' => $cajaAutorizada,
                    'detalle' => [],
                    'totales' => [
                        'ingresos' => 0,
                        'efectivo' => 0,
                        'no_efectivo' => 0,
                        'egresos' => 0,
                        'egresos_efectivo' => 0
                    ],
                    'apertura' => null,
                    'estado' => 'SIN_APERTURA',
                    'necesita_seleccion' => false
                ]);
            }

            $idapertura = (int)(
                $apertura['idapertura']
                ?? 0
            );

            if ($idapertura <= 0) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'La apertura física activa no es válida.'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | RESUMEN EXCLUSIVO DE LA APERTURA FÍSICA
            |--------------------------------------------------------------------------
            */
            $detalle = $caja->resumen(
                $fechaInicio,
                $fechaFin,
                null,
                $idapertura
            );

            $totales = $caja->totales(
                $fechaInicio,
                $fechaFin,
                null,
                $idapertura
            );

            $_SESSION['idcaja_activa'] =
                $idcajaOperacion;

            $_SESSION['idapertura_activa'] =
                $idapertura;

            responderCaja([
                'status' => 'ok',
                'modo' => $modoCajaSesion,
                'caja' => $cajaAutorizada,
                'detalle' => $detalle,
                'totales' => $totales,
                'apertura' => $apertura,
                'estado' => 'ABIERTA',
                'necesita_seleccion' => false
            ]);

            break;

        /*
        |--------------------------------------------------------------------------
        | VERIFICAR APERTURA
        |--------------------------------------------------------------------------
        */
        case 'verificar_apertura':

            /*
    |--------------------------------------------------------------------------
    | MODO LEGACY
    |--------------------------------------------------------------------------
    | Mantiene exactamente la búsqueda por usuario.
    |--------------------------------------------------------------------------
    */
            if ($modoCajaSesion === 'LEGACY') {
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
            }

            /*
    |--------------------------------------------------------------------------
    | MODOS NUEVOS
    |--------------------------------------------------------------------------
    */
            if ($idsucursalSesion <= 0) {
                responderCaja([
                    'status' => 'error',
                    'existe' => false,
                    'estado' => 'ERROR',
                    'message' =>
                    'No existe una sucursal activa en la sesión.'
                ]);
            }

            $configuracion =
                $configuracionCaja
                ->obtenerPorSucursal(
                    $idsucursalSesion
                );

            if (!$configuracion) {
                responderCaja([
                    'status' => 'error',
                    'existe' => false,
                    'estado' => 'ERROR',
                    'message' =>
                    'No existe configuración para la sucursal activa.'
                ]);
            }

            $idcajaOperacion = 0;

            /*
    |--------------------------------------------------------------------------
    | CAJA ÚNICA
    |--------------------------------------------------------------------------
    */
            if ($modoCajaSesion === 'CAJA_UNICA') {
                $idcajaOperacion = (int)(
                    $configuracion['idcaja_unica']
                    ?? 0
                );

                if ($idcajaOperacion <= 0) {
                    responderCaja([
                        'status' => 'error',
                        'existe' => false,
                        'estado' => 'ERROR',
                        'message' =>
                        'No existe una caja única configurada.'
                    ]);
                }

                $_SESSION['idcaja_activa'] =
                    $idcajaOperacion;
            }

            /*
    |--------------------------------------------------------------------------
    | MULTICAJA
    |--------------------------------------------------------------------------
    */
            if ($modoCajaSesion === 'MULTICAJA') {
                $idcajaOperacion = (int)(
                    $_SESSION['idcaja_activa']
                    ?? 0
                );

                if ($idcajaOperacion <= 0) {
                    $_SESSION['idapertura_activa'] = 0;

                    responderCaja([
                        'status' => 'ok',
                        'modo' => 'MULTICAJA',
                        'existe' => false,
                        'estado' => 'SIN_CAJA_SELECCIONADA',
                        'necesita_seleccion' => true,
                        'apertura' => null,
                        'message' =>
                        'Seleccione la caja que operará.'
                    ]);
                }
            }

            /*
    |--------------------------------------------------------------------------
    | VALIDAR AUTORIZACIÓN
    |--------------------------------------------------------------------------
    */
            $cajaAutorizada =
                $configuracionCaja
                ->obtenerCajaAutorizadaUsuario(
                    $idusuarioSesion,
                    $idsucursalSesion,
                    $idcajaOperacion
                );

            if (!$cajaAutorizada) {
                $_SESSION['idapertura_activa'] = 0;

                responderCaja([
                    'status' => 'error',
                    'existe' => false,
                    'estado' => 'NO_AUTORIZADO',
                    'message' =>
                    'No está autorizado para operar la caja seleccionada.'
                ]);
            }

            /*
    |--------------------------------------------------------------------------
    | BUSCAR APERTURA DE LA CAJA FÍSICA
    |--------------------------------------------------------------------------
    */
            $cantidad =
                $caja->contarCajasAbiertasFisica(
                    $idcajaOperacion
                );

            if ($cantidad > 1) {
                responderCaja([
                    'status' => 'error',
                    'existe' => true,
                    'estado' => 'ERROR',
                    'message' =>
                    'Se encontraron varias aperturas activas para la misma caja física.'
                ]);
            }

            $apertura =
                $caja->obtenerCajaAbiertaFisica(
                    $idcajaOperacion
                );

            $_SESSION['idcaja_activa'] =
                $idcajaOperacion;

            $_SESSION['idapertura_activa'] =
                $apertura
                ? (int)$apertura['idapertura']
                : 0;

            responderCaja([
                'status' => 'ok',
                'modo' => $modoCajaSesion,
                'existe' => $apertura !== null,
                'estado' => $apertura
                    ? 'ABIERTA'
                    : 'SIN_APERTURA',
                'necesita_seleccion' => false,
                'caja' => $cajaAutorizada,
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

            /*
    |--------------------------------------------------------------------------
    | MODO LEGACY
    |--------------------------------------------------------------------------
    | Conserva exactamente la apertura por usuario.
    |--------------------------------------------------------------------------
    */
            if ($modoCajaSesion === 'LEGACY') {
                $resultado =
                    $caja->registrarApertura(
                        $monto,
                        $idusuarioSesion
                    );

                responderCaja($resultado);
            }

            /*
    |--------------------------------------------------------------------------
    | VALIDAR SUCURSAL
    |--------------------------------------------------------------------------
    */
            if ($idsucursalSesion <= 0) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'No existe una sucursal activa en la sesión.'
                ]);
            }

            $configuracion =
                $configuracionCaja
                ->obtenerPorSucursal(
                    $idsucursalSesion
                );

            if (!$configuracion) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'No existe configuración para la sucursal activa.'
                ]);
            }

            $idcajaOperacion = 0;

            /*
    |--------------------------------------------------------------------------
    | CAJA ÚNICA
    |--------------------------------------------------------------------------
    */
            if ($modoCajaSesion === 'CAJA_UNICA') {
                $idcajaOperacion = (int)(
                    $configuracion['idcaja_unica']
                    ?? 0
                );

                if ($idcajaOperacion <= 0) {
                    responderCaja([
                        'status' => 'error',
                        'message' =>
                        'No existe una caja única configurada.'
                    ]);
                }

                $_SESSION['idcaja_activa'] =
                    $idcajaOperacion;
            }

            /*
    |--------------------------------------------------------------------------
    | MULTICAJA
    |--------------------------------------------------------------------------
    */
            if ($modoCajaSesion === 'MULTICAJA') {
                $idcajaOperacion = (int)(
                    $_SESSION['idcaja_activa']
                    ?? 0
                );

                if ($idcajaOperacion <= 0) {
                    responderCaja([
                        'status' => 'error',
                        'message' =>
                        'Seleccione primero la caja que operará.'
                    ]);
                }
            }

            /*
    |--------------------------------------------------------------------------
    | VALIDAR AUTORIZACIÓN PARA OPERAR
    |--------------------------------------------------------------------------
    */
            $cajaAutorizada =
                $configuracionCaja
                ->obtenerCajaAutorizadaUsuario(
                    $idusuarioSesion,
                    $idsucursalSesion,
                    $idcajaOperacion
                );

            if (!$cajaAutorizada) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'No está autorizado para operar la caja seleccionada.'
                ]);
            }

            /*
    |--------------------------------------------------------------------------
    | SI LA CAJA YA ESTÁ ABIERTA
    |--------------------------------------------------------------------------
    | Un usuario autorizado puede utilizar la apertura existente.
    |--------------------------------------------------------------------------
    */
            $aperturaExistente =
                $caja->obtenerCajaAbiertaFisica(
                    $idcajaOperacion
                );

            if ($aperturaExistente) {
                $_SESSION['idcaja_activa'] =
                    $idcajaOperacion;

                $_SESSION['idapertura_activa'] =
                    (int)$aperturaExistente['idapertura'];

                responderCaja([
                    'status' => 'ok',
                    'ya_estaba_abierta' => true,
                    'message' =>
                    'La caja ya se encuentra abierta y fue asignada a su sesión.',
                    'modo' =>
                    $modoCajaSesion,
                    'idcaja' =>
                    $idcajaOperacion,
                    'idapertura' =>
                    (int)$aperturaExistente['idapertura'],
                    'apertura' =>
                    $aperturaExistente
                ]);
            }

            /*
    |--------------------------------------------------------------------------
    | VALIDAR PERMISO PARA CREAR UNA APERTURA
    |--------------------------------------------------------------------------
    */
            $puedeAbrirCaja =
                (int)(
                    $cajaAutorizada['puede_abrir']
                    ?? 0
                ) === 1
                &&
                (int)(
                    $cajaAutorizada['puede_abrir_caja']
                    ?? 0
                ) === 1;

            if (!$puedeAbrirCaja) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'Puede operar esta caja cuando esté abierta, pero no tiene permiso para abrirla.'
                ]);
            }

            /*
    |--------------------------------------------------------------------------
    | REGISTRAR APERTURA FÍSICA
    |--------------------------------------------------------------------------
    */
            $resultado =
                $caja->registrarAperturaFisica(
                    $monto,
                    $idsucursalSesion,
                    $idcajaOperacion,
                    $idusuarioSesion,
                    $idusuarioSesion
                );

            if (
                ($resultado['status'] ?? '')
                === 'ok'
            ) {
                $_SESSION['idcaja_activa'] =
                    $idcajaOperacion;

                $_SESSION['idcaja_preparada'] =
                    0;

                $_SESSION['idapertura_activa'] =
                    (int)(
                        $resultado['idapertura']
                        ?? 0
                    );

                $resultado['modo'] =
                    $modoCajaSesion;
            }

            responderCaja($resultado);

            break;

        /*
        |--------------------------------------------------------------------------
        | DATOS PARA CIERRE
        |--------------------------------------------------------------------------
        */
        case 'datos_cierre':

            /*
    |--------------------------------------------------------------------------
    | MODO LEGACY
    |--------------------------------------------------------------------------
    | Conserva el cierre actual por usuario.
    |--------------------------------------------------------------------------
    */
            if ($modoCajaSesion === 'LEGACY') {
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
                    'modo' => 'LEGACY',
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
            }

            /*
    |--------------------------------------------------------------------------
    | VALIDAR SUCURSAL PARA MODOS NUEVOS
    |--------------------------------------------------------------------------
    */
            if ($idsucursalSesion <= 0) {
                responderCaja([
                    'status' => false,
                    'message' =>
                    'No existe una sucursal activa en la sesión.'
                ]);
            }

            $configuracion =
                $configuracionCaja
                ->obtenerPorSucursal(
                    $idsucursalSesion
                );

            if (!$configuracion) {
                responderCaja([
                    'status' => false,
                    'message' =>
                    'No existe configuración para la sucursal activa.'
                ]);
            }

            $idcajaOperacion = 0;

            /*
    |--------------------------------------------------------------------------
    | CAJA ÚNICA
    |--------------------------------------------------------------------------
    */
            if ($modoCajaSesion === 'CAJA_UNICA') {
                $idcajaOperacion = (int)(
                    $configuracion['idcaja_unica']
                    ?? 0
                );

                if ($idcajaOperacion <= 0) {
                    responderCaja([
                        'status' => false,
                        'message' =>
                        'No existe una caja única configurada.'
                    ]);
                }

                $_SESSION['idcaja_activa'] =
                    $idcajaOperacion;
            }

            /*
    |--------------------------------------------------------------------------
    | MULTICAJA
    |--------------------------------------------------------------------------
    */
            if ($modoCajaSesion === 'MULTICAJA') {
                $idcajaOperacion = (int)(
                    $_SESSION['idcaja_activa']
                    ?? 0
                );

                if ($idcajaOperacion <= 0) {
                    responderCaja([
                        'status' => false,
                        'message' =>
                        'Seleccione primero la caja que operará.'
                    ]);
                }
            }

            /*
    |--------------------------------------------------------------------------
    | VALIDAR AUTORIZACIÓN Y PERMISO DE CIERRE
    |--------------------------------------------------------------------------
    */
            $cajaAutorizada =
                $configuracionCaja
                ->obtenerCajaAutorizadaUsuario(
                    $idusuarioSesion,
                    $idsucursalSesion,
                    $idcajaOperacion
                );

            if (!$cajaAutorizada) {
                responderCaja([
                    'status' => false,
                    'message' =>
                    'No está autorizado para operar la caja seleccionada.'
                ]);
            }

            $puedeCerrar =
                (int)(
                    $cajaAutorizada['puede_cerrar']
                    ?? 0
                ) === 1
                &&
                (int)(
                    $cajaAutorizada['puede_cerrar_caja']
                    ?? 0
                ) === 1;

            if (!$puedeCerrar) {
                responderCaja([
                    'status' => false,
                    'message' =>
                    'Puede operar esta caja, pero no tiene permiso para cerrarla.'
                ]);
            }

            /*
    |--------------------------------------------------------------------------
    | BUSCAR APERTURA ACTIVA DE LA CAJA FÍSICA
    |--------------------------------------------------------------------------
    */
            $cantidad =
                $caja->contarCajasAbiertasFisica(
                    $idcajaOperacion
                );

            if ($cantidad === 0) {
                $_SESSION['idapertura_activa'] = 0;

                responderCaja([
                    'status' => false,
                    'message' =>
                    'No existe una apertura activa para la caja seleccionada.'
                ]);
            }

            if ($cantidad > 1) {
                responderCaja([
                    'status' => false,
                    'message' =>
                    'Se encontraron varias aperturas activas para la misma caja física.'
                ]);
            }

            $apertura =
                $caja->obtenerCajaAbiertaFisica(
                    $idcajaOperacion
                );

            if (!$apertura) {
                $_SESSION['idapertura_activa'] = 0;

                responderCaja([
                    'status' => false,
                    'message' =>
                    'No se encontró la apertura activa de la caja física.'
                ]);
            }

            $idapertura = (int)(
                $apertura['idapertura']
                ?? 0
            );

            if ($idapertura <= 0) {
                responderCaja([
                    'status' => false,
                    'message' =>
                    'La apertura activa no es válida.'
                ]);
            }

            /*
    |--------------------------------------------------------------------------
    | CALCULAR ARQUEO POR IDAPERTURA
    |--------------------------------------------------------------------------
    */
            $totales =
                $caja->calcularTotalesAperturaFisica(
                    $idapertura
                );

            $_SESSION['idcaja_activa'] =
                $idcajaOperacion;

            $_SESSION['idapertura_activa'] =
                $idapertura;

            responderCaja([
                'status' => true,
                'modo' => $modoCajaSesion,
                'caja' => $cajaAutorizada,
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

            /*
    |--------------------------------------------------------------------------
    | MONTO CONTADO
    |--------------------------------------------------------------------------
    | Se aceptan ambos nombres para mantener compatibilidad con el JS actual.
    |--------------------------------------------------------------------------
    */
            $montoContadoEntrada =
                $_POST['monto_contado']
                ?? $_POST['monto']
                ?? null;

            if (
                $montoContadoEntrada === null
                || $montoContadoEntrada === ''
                || !is_numeric($montoContadoEntrada)
            ) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'Ingrese un monto contado válido.'
                ]);
            }

            $montoContado = round(
                (float)$montoContadoEntrada,
                2
            );

            if ($montoContado < 0) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'El monto contado no puede ser negativo.'
                ]);
            }

            /*
    |--------------------------------------------------------------------------
    | MODO LEGACY
    |--------------------------------------------------------------------------
    | Mantiene exactamente el cierre actual por usuario.
    |--------------------------------------------------------------------------
    */
            if ($modoCajaSesion === 'LEGACY') {
                $resultado =
                    $caja->cerrarCaja(
                        $montoContado,
                        $idusuarioSesion
                    );

                if (
                    ($resultado['status'] ?? '')
                    === 'ok'
                ) {
                    $resultado['cerrar_sesion'] = true;
                    $resultado['redirect'] = 'login';

                    destruirSesionDespuesDeCerrarCaja();
                }

                responderCaja($resultado);
            }

            /*
    |--------------------------------------------------------------------------
    | VALIDAR SUCURSAL
    |--------------------------------------------------------------------------
    */
            if ($idsucursalSesion <= 0) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'No existe una sucursal activa en la sesión.'
                ]);
            }

            $configuracion =
                $configuracionCaja
                ->obtenerPorSucursal(
                    $idsucursalSesion
                );

            if (!$configuracion) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'No existe configuración para la sucursal activa.'
                ]);
            }

            $idcajaOperacion = 0;

            /*
    |--------------------------------------------------------------------------
    | CAJA ÚNICA
    |--------------------------------------------------------------------------
    */
            if ($modoCajaSesion === 'CAJA_UNICA') {
                $idcajaOperacion = (int)(
                    $configuracion['idcaja_unica']
                    ?? 0
                );

                if ($idcajaOperacion <= 0) {
                    responderCaja([
                        'status' => 'error',
                        'message' =>
                        'No existe una caja única configurada.'
                    ]);
                }

                $_SESSION['idcaja_activa'] =
                    $idcajaOperacion;
            }

            /*
    |--------------------------------------------------------------------------
    | MULTICAJA
    |--------------------------------------------------------------------------
    */
            if ($modoCajaSesion === 'MULTICAJA') {
                $idcajaOperacion = (int)(
                    $_SESSION['idcaja_activa']
                    ?? 0
                );

                if ($idcajaOperacion <= 0) {
                    responderCaja([
                        'status' => 'error',
                        'message' =>
                        'Seleccione primero la caja que operará.'
                    ]);
                }
            }

            /*
    |--------------------------------------------------------------------------
    | VALIDAR AUTORIZACIÓN
    |--------------------------------------------------------------------------
    */
            $cajaAutorizada =
                $configuracionCaja
                ->obtenerCajaAutorizadaUsuario(
                    $idusuarioSesion,
                    $idsucursalSesion,
                    $idcajaOperacion
                );

            if (!$cajaAutorizada) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'No está autorizado para operar la caja seleccionada.'
                ]);
            }

            /*
    |--------------------------------------------------------------------------
    | VALIDAR PERMISO DE CIERRE
    |--------------------------------------------------------------------------
    */
            $puedeCerrar =
                (int)(
                    $cajaAutorizada['puede_cerrar']
                    ?? 0
                ) === 1
                &&
                (int)(
                    $cajaAutorizada['puede_cerrar_caja']
                    ?? 0
                ) === 1;

            if (!$puedeCerrar) {
                responderCaja([
                    'status' => 'error',
                    'message' =>
                    'Puede operar esta caja, pero no tiene permiso para cerrarla.'
                ]);
            }

            /*
    |--------------------------------------------------------------------------
    | CERRAR APERTURA FÍSICA
    |--------------------------------------------------------------------------
    | El modelo bloquea y localiza la apertura activa por sucursal y caja.
    |--------------------------------------------------------------------------
    */
            $resultado =
                $caja->cerrarCajaFisica(
                    $montoContado,
                    $idsucursalSesion,
                    $idcajaOperacion,
                    $idusuarioSesion
                );

            if (
                ($resultado['status'] ?? '')
                === 'ok'
            ) {
                $resultado['modo'] =
                    $modoCajaSesion;

                $resultado['cerrar_sesion'] =
                    true;

                $resultado['redirect'] =
                    'login';

                /*
                     * Al cerrar la caja también termina
                     * la sesión operativa del usuario.
                     */
                destruirSesionDespuesDeCerrarCaja();
            }

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
