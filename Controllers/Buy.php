<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Lima');

require_once __DIR__ . '/../Models/Buy.php';

$buy = new Buy();

function responderJson(
    bool $success,
    string $mensaje,
    array $extra = [],
    int $codigoHttp = 200
): void {
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'mensaje' => $mensaje
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function exigirSesionCompras(): void
{
    $idusuario = (int)($_SESSION['idusuario'] ?? 0);
    $permisoCompras = (int)($_SESSION['compras'] ?? 0);

    if ($idusuario <= 0 || $permisoCompras !== 1) {
        responderJson(
            false,
            'La sesión no es válida o no tiene permiso para registrar compras.',
            [],
            403
        );
    }
}

$op = (string)($_GET['op'] ?? '');

try {
    switch ($op) {
        case 'guardaryeditar':
            exigirSesionCompras();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, 'Método no permitido.', [], 405);
            }

            $idingreso = (int)($_POST['idingreso'] ?? 0);

            if ($idingreso > 0) {
                responderJson(
                    false,
                    'La edición de compras antiguas no está habilitada en esta etapa. Anule y registre nuevamente si corresponde.',
                    [],
                    409
                );
            }

            $detallesJson = (string)($_POST['detalles_json'] ?? '');
            $detalles = json_decode($detallesJson, true);

            if (!is_array($detalles)) {
                responderJson(
                    false,
                    'El detalle de la compra no tiene un formato válido.',
                    [],
                    422
                );
            }

            $cabecera = [
                'idproveedor' => (int)($_POST['idproveedor'] ?? 0),
                'idusuario' => (int)($_SESSION['idusuario'] ?? 0),
                'idsucursal' => (int)($_SESSION['idsucursal'] ?? 0),
                'tipo_comprobante' => (string)($_POST['tipo_comprobante'] ?? ''),
                'serie_comprobante' => (string)($_POST['serie_comprobante'] ?? ''),
                'num_comprobante' => (string)($_POST['num_comprobante'] ?? ''),
                'fecha_hora' => (string)($_POST['fecha_hora'] ?? ''),
                'impuesto' => (float)($_POST['impuesto'] ?? 0),
                'observacion' => (string)($_POST['observacion'] ?? '')
            ];

            $resultado = $buy->insertar($cabecera, $detalles);

            responderJson(
                true,
                'Compra registrada correctamente.',
                [
                    'idingreso' => (int)$resultado['idingreso'],
                    'tipo_compra' => (string)$resultado['tipo_compra'],
                    'total_compra' => (float)$resultado['total_compra']
                ]
            );
            break;

        case 'anular':
            exigirSesionCompras();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, 'Método no permitido.', [], 405);
            }

            $idingreso = (int)($_POST['idingreso'] ?? 0);
            $resultado = $buy->anular($idingreso);

            responderJson(
                true,
                (string)($resultado['mensaje'] ?? 'Compra anulada correctamente.')
            );
            break;

        case 'mostrar':
            exigirSesionCompras();

            $idingreso = (int)($_POST['idingreso'] ?? $_GET['idingreso'] ?? 0);
            $compra = $buy->mostrar($idingreso);

            if (!$compra) {
                responderJson(false, 'La compra no existe.', [], 404);
            }

            responderJson(true, 'Compra encontrada.', ['compra' => $compra]);
            break;

        case 'listarDetalle':
            exigirSesionCompras();

            $idingreso = (int)($_GET['id'] ?? $_POST['idingreso'] ?? 0);
            $detalles = $buy->listarDetalle($idingreso);

            responderJson(true, 'Detalle cargado.', ['detalles' => $detalles]);
            break;

        case 'listar':
            exigirSesionCompras();

            $registros = $buy->listar();
            $data = [];

            foreach ($registros as $reg) {
                $idingreso = (int)$reg['idingreso'];
                $estado = (string)$reg['estado'];
                $tipoCompra = (string)$reg['tipo_compra'];

                $badgeTipo = match ($tipoCompra) {
                    'MIXTA' => '<span class="badge badge-info">Mixta</span>',
                    'NO_INVENTARIO' => '<span class="badge badge-secondary">Gasto / servicio</span>',
                    default => '<span class="badge badge-primary">Inventario</span>'
                };

                $opciones = '<button type="button" class="btn btn-info btn-sm" '
                    . 'onclick="mostrarCompra(' . $idingreso . ')" title="Ver compra">'
                    . '<i class="fas fa-eye"></i></button>';

                if ($estado === 'Aceptado') {
                    $opciones .= ' <button type="button" class="btn btn-danger btn-sm" '
                        . 'onclick="anularCompra(' . $idingreso . ')" title="Anular compra">'
                        . '<i class="fas fa-times"></i></button>';
                }

                $documento = trim(
                    (string)$reg['serie_comprobante']
                    . '-'
                    . (string)$reg['num_comprobante'],
                    '-'
                );

                $data[] = [
                    '0' => $opciones,
                    '1' => htmlspecialchars((string)$reg['fecha'], ENT_QUOTES, 'UTF-8'),
                    '2' => htmlspecialchars((string)$reg['proveedor'], ENT_QUOTES, 'UTF-8'),
                    '3' => htmlspecialchars((string)$reg['usuario'], ENT_QUOTES, 'UTF-8'),
                    '4' => htmlspecialchars((string)$reg['tipo_comprobante'], ENT_QUOTES, 'UTF-8'),
                    '5' => htmlspecialchars($documento, ENT_QUOTES, 'UTF-8'),
                    '6' => $badgeTipo,
                    '7' => 'S/ ' . number_format((float)$reg['total_compra'], 2, '.', ','),
                    '8' => $estado === 'Aceptado'
                        ? '<span class="badge badge-success">Aceptado</span>'
                        : '<span class="badge badge-danger">Anulado</span>'
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(
                [
                    'sEcho' => 1,
                    'iTotalRecords' => count($data),
                    'iTotalDisplayRecords' => count($data),
                    'aaData' => $data
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            exit;

        case 'datosFormulario':
            exigirSesionCompras();

            responderJson(
                true,
                'Datos del formulario cargados.',
                ['datos' => $buy->datosFormulario()]
            );
            break;

        case 'listarArticulos':
        case 'productosCompra':
            exigirSesionCompras();

            responderJson(
                true,
                'Productos cargados.',
                ['productos' => $buy->listarProductosCompra()]
            );
            break;

        case 'selectProveedor':
            exigirSesionCompras();

            require_once __DIR__ . '/../Models/Person.php';
            $person = new Person();
            $proveedores = $person->listarp();

            header('Content-Type: text/html; charset=utf-8');
            echo '<option value="">Seleccione un proveedor...</option>';

            foreach ($proveedores as $reg) {
                echo '<option value="'
                    . (int)$reg['idpersona']
                    . '">'
                    . htmlspecialchars((string)$reg['nombre'], ENT_QUOTES, 'UTF-8')
                    . '</option>';
            }
            exit;

        default:
            responderJson(false, 'Operación no válida.', [], 404);
    }
} catch (Throwable $error) {
    error_log('[COMPRAS] ' . $error->getMessage());

    responderJson(
        false,
        $error->getMessage(),
        [],
        400
    );
}
