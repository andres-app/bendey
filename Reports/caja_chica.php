<?php

declare(strict_types=1);

ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['nombre'])) {
    echo 'Debe ingresar al sistema correctamente';
    exit;
}

if (
    !isset($_SESSION['ventas'])
    || (int)$_SESSION['ventas'] !== 1
) {
    echo 'No tiene permiso';
    exit;
}

require_once __DIR__ . '/../Libraries/fpdf182/fpdf.php';
require_once __DIR__ . '/../Models/Cajachica.php';
require_once __DIR__ . '/../Models/Company.php';

function textoCajaPdf(string $texto): string
{
    $convertido = iconv(
        'UTF-8',
        'windows-1252//TRANSLIT',
        $texto
    );

    return $convertido !== false
        ? $convertido
        : $texto;
}

function montoCajaPdf(float $monto): string
{
    $prefijo = $monto < 0
        ? '- S/ '
        : 'S/ ';

    return $prefijo
        . number_format(
            abs($monto),
            2,
            '.',
            ','
        );
}

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

$idusuario = isset($_GET['idusuario'])
    && (int)$_GET['idusuario'] > 0
    ? (int)$_GET['idusuario']
    : null;

$idapertura = isset($_GET['idapertura'])
    && (int)$_GET['idapertura'] > 0
    ? (int)$_GET['idapertura']
    : null;

$caja = new Cajachica();

$data = $caja->resumen(
    $fechaInicio,
    $fechaFin,
    $idusuario,
    $idapertura
);

$totales = $caja->totales(
    $fechaInicio,
    $fechaFin,
    $idusuario,
    $idapertura
);

$apertura = $idapertura !== null
    ? $caja->obtenerAperturaPorId(
        $idapertura
    )
    : $caja->obtenerAperturaPorFecha(
        $fechaInicio,
        $idusuario
    );

$montoApertura = round(
    (float)(
        $apertura['monto_apertura']
        ?? 0
    ),
    2
);

$empresa = new Company();
$info = $empresa->listar()[0] ?? [];

$filas = [];

foreach (is_array($data) ? $data : [] as $registro) {
    $tipo = trim(
        (string)(
            $registro['tipo_comprobante']
            ?? 'SIN COMPROBANTE'
        )
    );

    if (!isset($filas[$tipo])) {
        $filas[$tipo] = [
            'efectivo' => 0.00,
            'tarjeta' => 0.00,
            'transferencia' => 0.00,
            'billeteras' => 0.00,
            'otros' => 0.00
        ];
    }

    $forma = mb_strtolower(
        trim(
            (string)(
                $registro['forma_pago']
                ?? ''
            )
        ),
        'UTF-8'
    );

    $monto = round(
        (float)($registro['total'] ?? 0),
        2
    );

    if (str_contains($forma, 'efectivo')) {
        $filas[$tipo]['efectivo'] += $monto;
    } elseif (
        str_contains($forma, 'tarjeta')
        || str_contains($forma, 'izipay')
    ) {
        $filas[$tipo]['tarjeta'] += $monto;
    } elseif (str_contains($forma, 'transfer')) {
        $filas[$tipo]['transferencia'] += $monto;
    } elseif (
        str_contains($forma, 'yape')
        || str_contains($forma, 'plin')
    ) {
        $filas[$tipo]['billeteras'] += $monto;
    } else {
        $filas[$tipo]['otros'] += $monto;
    }
}

$pdf = new FPDF(
    'P',
    'mm',
    [80, 300]
);

$pdf->SetMargins(4, 4, 4);
$pdf->SetAutoPageBreak(true, 6);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 10);
$pdf->MultiCell(
    0,
    5,
    textoCajaPdf(
        mb_strtoupper(
            (string)($info['nombre'] ?? 'EMPRESA'),
            'UTF-8'
        )
    ),
    0,
    'C'
);

$ruc = trim(
    (string)(
        $info['documento']
        ?? $info['ruc']
        ?? '-'
    )
);

$pdf->SetFont('Arial', '', 8);
$pdf->Cell(
    0,
    4,
    textoCajaPdf('RUC: ' . $ruc),
    0,
    1,
    'C'
);

$pdf->MultiCell(
    0,
    4,
    textoCajaPdf(
        (string)($info['direccion'] ?? '-')
    ),
    0,
    'C'
);

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(
    0,
    5,
    textoCajaPdf('LIQUIDACIÓN DE CAJA'),
    0,
    1,
    'C'
);

$pdf->SetFont('Arial', '', 8);
$pdf->Cell(
    0,
    4,
    textoCajaPdf(
        'Desde: ' . $fechaInicio
    ),
    0,
    1,
    'C'
);

$pdf->Cell(
    0,
    4,
    textoCajaPdf(
        'Hasta: ' . $fechaFin
    ),
    0,
    1,
    'C'
);

if ($idapertura !== null) {
    $pdf->Cell(
        0,
        4,
        textoCajaPdf(
            'Apertura N.° '
            . $idapertura
        ),
        0,
        1,
        'C'
    );
}

$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(
    0,
    5,
    textoCajaPdf('RESUMEN DE MOVIMIENTOS'),
    0,
    1
);

foreach ($filas as $tipo => $fila) {
    $totalFila = round(
        array_sum($fila),
        2
    );

    $pdf->Ln(1);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->MultiCell(
        0,
        4,
        textoCajaPdf($tipo),
        0,
        'L'
    );

    $pdf->SetFont('Arial', '', 7);

    $conceptos = [
        'Efectivo' => $fila['efectivo'],
        'Tarjeta' => $fila['tarjeta'],
        'Transferencia' => $fila['transferencia'],
        'Yape / Plin' => $fila['billeteras'],
        'Otros' => $fila['otros']
    ];

    foreach ($conceptos as $concepto => $monto) {
        if (abs($monto) < 0.005) {
            continue;
        }

        $pdf->Cell(
            38,
            4,
            textoCajaPdf($concepto),
            0,
            0
        );

        $pdf->Cell(
            34,
            4,
            textoCajaPdf(
                montoCajaPdf($monto)
            ),
            0,
            1,
            'R'
        );
    }

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(
        38,
        5,
        textoCajaPdf('Total'),
        'T',
        0
    );

    $pdf->Cell(
        34,
        5,
        textoCajaPdf(
            montoCajaPdf($totalFila)
        ),
        'T',
        1,
        'R'
    );
}

$ventasBrutas = round(
    (float)($totales['ventas_brutas'] ?? 0),
    2
);

$notasCredito = round(
    (float)($totales['notas_credito'] ?? 0),
    2
);

$otrosIngresos = round(
    (float)($totales['otros_ingresos'] ?? 0),
    2
);

$otrosEgresos = round(
    (float)($totales['otros_egresos'] ?? 0),
    2
);

$resultadoNeto = round(
    (float)($totales['resultado_neto'] ?? 0),
    2
);

$efectivoEsperado = round(
    $montoApertura
    + (float)($totales['efectivo'] ?? 0)
    - (float)($totales['egresos_efectivo'] ?? 0),
    2
);

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(
    0,
    5,
    textoCajaPdf('TOTALES'),
    'T',
    1,
    'C'
);

$resumen = [
    'Apertura' => $montoApertura,
    'Ventas cobradas' => $ventasBrutas,
    'Otros ingresos' => $otrosIngresos,
    'Devoluciones N.C.' => -$notasCredito,
    'Otros egresos' => -$otrosEgresos,
    'Movimiento neto' => $resultadoNeto,
    'Efectivo esperado' => $efectivoEsperado
];

foreach ($resumen as $concepto => $monto) {
    $pdf->SetFont(
        'Arial',
        $concepto === 'Efectivo esperado'
            ? 'B'
            : '',
        $concepto === 'Efectivo esperado'
            ? 10
            : 8
    );

    $pdf->Cell(
        40,
        5,
        textoCajaPdf($concepto),
        0,
        0
    );

    $pdf->Cell(
        32,
        5,
        textoCajaPdf(
            montoCajaPdf($monto)
        ),
        0,
        1,
        'R'
    );
}

$pdf->Ln(3);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(
    0,
    4,
    textoCajaPdf('--- FIN DEL REPORTE ---'),
    0,
    1,
    'C'
);

if (ob_get_length()) {
    ob_end_clean();
}

$pdf->Output(
    'I',
    'Liquidacion_Caja_'
    . $fechaInicio
    . '.pdf'
);
