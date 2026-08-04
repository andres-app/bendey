<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Consult.php';

$consult = new Consult();
$op = $_GET['op'] ?? '';

header('Content-Type: application/json; charset=utf-8');

function responderDashboard(array $datos): void
{
    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function etiquetaMes(string $fecha): string
{
    $timestamp = strtotime($fecha);

    if ($timestamp === false) {
        return $fecha;
    }

    $meses = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre'
    ];

    $numeroMes = (int)date('n', $timestamp);

    return ($meses[$numeroMes] ?? '')
        . ' '
        . date('Y', $timestamp);
}

switch ($op) {
    case 'compras10dias':
        $compras = $consult->comprasultimos_10dias();

        $fechas = [];
        $totales = [];

        foreach ($compras as $registro) {
            $fechas[] = etiquetaMes(
                (string)($registro['fecha'] ?? '')
            );

            $totales[] = round(
                (float)($registro['total'] ?? 0),
                2
            );
        }

        responderDashboard([
            'fechas' => $fechas,
            'totales' => $totales
        ]);

    case 'ventas12meses':
        $ventas = $consult->ventasultimos_12meses();

        $fechas = [];
        $ventasBrutas = [];
        $notasCredito = [];
        $ventasNetas = [];

        foreach ($ventas as $registro) {
            $fechas[] = etiquetaMes(
                (string)($registro['fecha'] ?? '')
            );

            $ventasBrutas[] = round(
                (float)($registro['ventas_brutas'] ?? 0),
                2
            );

            $notasCredito[] = round(
                (float)($registro['notas_credito'] ?? 0),
                2
            );

            $ventasNetas[] = round(
                (float)($registro['total'] ?? 0),
                2
            );
        }

        responderDashboard([
            'fechas' => $fechas,
            'ventas_brutas' => $ventasBrutas,
            'notas_credito' => $notasCredito,
            'totales' => $ventasNetas
        ]);

    case 'cuadros1':
        $compraHoy = $consult->totalcomprahoy();
        $registroCompra = $compraHoy[0] ?? [];

        $ventaHoy = $consult->totalventahoy();
        $registroVenta = $ventaHoy[0] ?? [];

        $clientes = $consult->cantidadclientes();
        $registroClientes = $clientes[0] ?? [];

        $proveedores = $consult->cantidadproveedores();
        $registroProveedores = $proveedores[0] ?? [];

        $ventasBrutas = round(
            (float)($registroVenta['ventas_brutas'] ?? 0),
            2
        );

        $notasCredito = round(
            (float)($registroVenta['notas_credito'] ?? 0),
            2
        );

        $ventasNetas = round(
            (float)(
                $registroVenta['ventas_netas']
                ?? $registroVenta['total_venta']
                ?? 0
            ),
            2
        );

        responderDashboard([
            'totalcomprahoy' => round(
                (float)($registroCompra['total_compra'] ?? 0),
                2
            ),

            /*
             * Se conserva totalventahoy para mantener
             * compatibilidad con vistas antiguas.
             */
            'totalventahoy' => $ventasNetas,
            'totalventabruta' => $ventasBrutas,
            'totalnotascredito' => $notasCredito,
            'totalventaneta' => $ventasNetas,

            'cantidadclientes' =>
                (int)($registroClientes['totalc'] ?? 0),

            'cantidadproveedores' =>
                (int)($registroProveedores['totalp'] ?? 0)
        ]);

    case 'cuadros2':
        $articulos = $consult->cantidadarticulos();
        $registroArticulos = $articulos[0] ?? [];

        $stock = $consult->totalstock();
        $registroStock = $stock[0] ?? [];

        $categorias = $consult->cantidadcategorias();
        $registroCategorias = $categorias[0] ?? [];

        responderDashboard([
            'cantidadarticulos' =>
                (int)($registroArticulos['totalar'] ?? 0),

            'totalstock' =>
                (int)($registroStock['totalstock'] ?? 0),

            'cantidadcategorias' =>
                (int)($registroCategorias['totalca'] ?? 0)
        ]);

    case 'cateogriasMasVendidas':
        responderDashboard(
            $consult->cateogriasMasVendidas()
        );

    case 'stockCategoria':
        require_once __DIR__ . '/../Models/Category.php';

        $categoria = new Category();

        responderDashboard(
            $categoria->stockPorCategoria()
        );

    default:
        http_response_code(400);

        responderDashboard([
            'status' => false,
            'message' => 'Operación no válida.'
        ]);
}
