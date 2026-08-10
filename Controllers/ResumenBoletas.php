<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Lima');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../Models/ResumenBoletas.php';

function responderResumenBoletas(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRESERVE_ZERO_FRACTION
    );
    exit;
}

if (
    !isset($_SESSION['nombre'])
    || (int)($_SESSION['ventas'] ?? 0) !== 1
) {
    responderResumenBoletas([
        'success' => false,
        'mensaje' => 'Acceso no autorizado.'
    ], 403);
}

$op = trim((string)($_GET['op'] ?? $_POST['op'] ?? ''));
$modelo = new ResumenBoletas();

try {
    switch ($op) {
        case 'pendientes':
            $fecha = trim((string)($_GET['fecha'] ?? date('Y-m-d')));
            $registros = $modelo->listarPendientes($fecha);

            $total = 0.0;
            foreach ($registros as $registro) {
                $total += (float)($registro['total_venta'] ?? 0);
            }

            responderResumenBoletas([
                'success' => true,
                'fecha' => $fecha,
                'cantidad' => count($registros),
                'total' => round($total, 2),
                'data' => $registros
            ]);
            break;

        case 'resumenes':
            $registros = $modelo->listarResumenes(150);

            responderResumenBoletas([
                'success' => true,
                'cantidad' => count($registros),
                'data' => $registros
            ]);
            break;

        case 'detalle':
            $idresumen = (int)($_GET['idresumen'] ?? $_POST['idresumen'] ?? 0);
            $detalle = $modelo->detalle($idresumen);

            if (!$detalle) {
                responderResumenBoletas([
                    'success' => false,
                    'mensaje' => 'No se encontró el resumen solicitado.'
                ], 404);
            }

            responderResumenBoletas([
                'success' => true,
                'data' => $detalle
            ]);
            break;

        case 'crear':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderResumenBoletas([
                    'success' => false,
                    'mensaje' => 'La creación del resumen requiere POST.'
                ], 405);
            }

            $fecha = trim((string)($_POST['fecha'] ?? ''));
            $ventas = $_POST['ventas'] ?? [];

            if (is_string($ventas)) {
                $decodificado = json_decode($ventas, true);
                $ventas = is_array($decodificado) ? $decodificado : [];
            }

            if (!is_array($ventas)) {
                $ventas = [];
            }

            $resultado = $modelo->crear(
                $fecha,
                $ventas,
                (int)($_SESSION['idusuario'] ?? 0)
            );

            responderResumenBoletas([
                'success' => true,
                'mensaje' => 'Resumen Diario preparado correctamente.',
                'data' => $resultado
            ]);
            break;

        case 'descartar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderResumenBoletas([
                    'success' => false,
                    'mensaje' => 'Esta operación requiere POST.'
                ], 405);
            }

            $idresumen = (int)($_POST['idresumen'] ?? 0);
            $eliminado = $modelo->descartar($idresumen);

            responderResumenBoletas([
                'success' => $eliminado,
                'mensaje' => $eliminado
                    ? 'Resumen descartado. Sus boletas vuelven a estar disponibles.'
                    : 'No se pudo descartar el resumen.'
            ], $eliminado ? 200 : 409);
            break;

        default:
            responderResumenBoletas([
                'success' => false,
                'mensaje' => 'Operación no válida.'
            ], 404);
    }
} catch (InvalidArgumentException $e) {
    responderResumenBoletas([
        'success' => false,
        'mensaje' => $e->getMessage()
    ], 422);
} catch (RuntimeException $e) {
    responderResumenBoletas([
        'success' => false,
        'mensaje' => $e->getMessage()
    ], 409);
} catch (Throwable $e) {
    error_log('[RESUMEN BOLETAS] ' . $e->getMessage());

    responderResumenBoletas([
        'success' => false,
        'mensaje' => 'No se pudo completar la operación del Resumen Diario.'
    ], 500);
}
