<?php

declare(strict_types=1);

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

require_once __DIR__ . '/../Models/Cajachica.php';
require_once __DIR__ . '/../Models/Company.php';

function hCajaExcel(mixed $valor): string
{
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function numeroCajaExcel(float $valor): string
{
    return number_format(
        $valor,
        2,
        '.',
        ''
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

header(
    'Content-Type: application/vnd.ms-excel; charset=UTF-8'
);

header(
    'Content-Disposition: attachment; filename=Liquidacion_Caja_'
    . $fechaInicio
    . '.xls'
);

header('Pragma: no-cache');
header('Expires: 0');

echo "<meta charset='UTF-8'>";

echo "<table border='1'>";

echo '<tr>';
echo '<th colspan="6" style="font-size:14px">';
echo hCajaExcel(
    $info['nombre'] ?? 'EMPRESA'
);
echo '</th>';
echo '</tr>';

echo '<tr>';
echo '<th colspan="6">LIQUIDACIÓN DE CAJA</th>';
echo '</tr>';

echo '<tr>';
echo '<th colspan="6">';
echo hCajaExcel(
    'Desde '
    . $fechaInicio
    . ' - Hasta '
    . $fechaFin
);
echo '</th>';
echo '</tr>';

if ($idapertura !== null) {
    echo '<tr>';
    echo '<th colspan="6">';
    echo hCajaExcel(
        'Apertura N.° '
        . $idapertura
    );
    echo '</th>';
    echo '</tr>';
}

echo '<tr>';
echo '<th>Comprobante</th>';
echo '<th>Efectivo</th>';
echo '<th>Tarjeta</th>';
echo '<th>Transferencia</th>';
echo '<th>Yape / Plin</th>';
echo '<th>Total</th>';
echo '</tr>';

echo '<tr>';
echo '<td><strong>APERTURA DE CAJA</strong></td>';
echo '<td>'
    . numeroCajaExcel($montoApertura)
    . '</td>';
echo '<td>0.00</td>';
echo '<td>0.00</td>';
echo '<td>0.00</td>';
echo '<td><strong>'
    . numeroCajaExcel($montoApertura)
    . '</strong></td>';
echo '</tr>';

foreach ($filas as $tipo => $fila) {
    $totalFila = round(
        array_sum($fila),
        2
    );

    $estilo = $totalFila < 0
        ? " style='color:#b91c1c;background:#fee2e2'"
        : '';

    echo '<tr' . $estilo . '>';
    echo '<td>' . hCajaExcel($tipo) . '</td>';
    echo '<td>'
        . numeroCajaExcel($fila['efectivo'])
        . '</td>';
    echo '<td>'
        . numeroCajaExcel($fila['tarjeta'])
        . '</td>';
    echo '<td>'
        . numeroCajaExcel($fila['transferencia'])
        . '</td>';
    echo '<td>'
        . numeroCajaExcel($fila['billeteras'])
        . '</td>';
    echo '<td><strong>'
        . numeroCajaExcel($totalFila)
        . '</strong></td>';
    echo '</tr>';
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

$resumen = [
    'VENTAS COBRADAS' => $ventasBrutas,
    'OTROS INGRESOS' => $otrosIngresos,
    'DEVOLUCIONES N.C.' => -$notasCredito,
    'OTROS EGRESOS' => -$otrosEgresos,
    'MOVIMIENTO NETO' => $resultadoNeto,
    'EFECTIVO ESPERADO' => $efectivoEsperado
];

foreach ($resumen as $concepto => $monto) {
    $estilo = $monto < 0
        ? " style='color:#b91c1c'"
        : '';

    echo '<tr' . $estilo . '>';
    echo '<td colspan="5"><strong>'
        . hCajaExcel($concepto)
        . '</strong></td>';
    echo '<td><strong>'
        . numeroCajaExcel($monto)
        . '</strong></td>';
    echo '</tr>';
}

echo '</table>';
