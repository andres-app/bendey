<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Consult.php';

$consult = new Consult();
$op = $_GET['op'] ?? '';

header('Content-Type: application/json; charset=utf-8');

function responderGraphics(array $datos): void
{
    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function etiquetaMesGrafico(string $fecha): string
{
    $timestamp = strtotime($fecha);

    if ($timestamp === false) {
        return $fecha;
    }

    $meses = [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic'
    ];

    return ($meses[(int)date('n', $timestamp)] ?? '')
        . ' '
        . date('Y', $timestamp);
}

function respuestaVentas(array $registros): array
{
    $fechas = [];
    $ventasBrutas = [];
    $notasCredito = [];
    $ventasNetas = [];

    foreach ($registros as $registro) {
        $fechas[] = etiquetaMesGrafico(
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

    return [
        'fechas' => $fechas,
        'ventas_brutas' => $ventasBrutas,
        'notas_credito' => $notasCredito,
        'totales' => $ventasNetas
    ];
}

switch ($op) {
    case 'compras_grafica':
        $registros = $consult->compras_grafica();

        $fechas = [];
        $totales = [];

        foreach ($registros as $registro) {
            $fechas[] = (string)($registro['fecha'] ?? '');
            $totales[] = round(
                (float)($registro['total'] ?? 0),
                2
            );
        }

        responderGraphics([
            'fechas' => $fechas,
            'totales' => $totales
        ]);

    case 'ventas_grafica':
        responderGraphics(
            respuestaVentas(
                $consult->ventas_grafica()
            )
        );

    case 'resumen_compras':
        $registros =
            $consult->comparsultimos_12meses_grafica();

        $fechas = [];
        $totales = [];

        foreach ($registros as $registro) {
            $fechas[] = (string)($registro['fecha'] ?? '');
            $totales[] = round(
                (float)($registro['total'] ?? 0),
                2
            );
        }

        responderGraphics([
            'fechas' => $fechas,
            'totales' => $totales
        ]);

    case 'resumen_ventas':
        responderGraphics(
            respuestaVentas(
                $consult->ventasultimos_12meses_grafica()
            )
        );

    default:
        http_response_code(400);

        responderGraphics([
            'status' => false,
            'message' => 'Operación no válida.'
        ]);
}
