<?php
require_once '../Models/Cajachica.php';
require_once '../Models/Company.php';

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$fecha_fin    = $_GET['fecha_fin'] ?? date('Y-m-d');
$idusuario    = $_GET['idusuario'] ?? null;

$caja = new Cajachica();
$data = $caja->resumen($fecha_inicio, $fecha_fin, $idusuario);
$totales = $caja->totales($fecha_inicio, $fecha_fin, $idusuario);

// Empresa
$empresa = new Company();
$info = $empresa->listar()[0] ?? [];

// Agrupar igual que en pantalla
$filas = [];

foreach ($data as $r) {
    $tc = $r['tipo_comprobante'];

    if (!isset($filas[$tc])) {
        $filas[$tc] = [
            'efectivo' => 0,
            'tarjeta' => 0,
            'transferencia' => 0,
            'yape' => 0,
            'plin' => 0
        ];
    }

    $forma = strtolower($r['forma_pago']);
    $monto = (float)$r['total'];

    if (strpos($forma, 'efectivo') !== false) {
        $filas[$tc]['efectivo'] += $monto;
    } elseif (strpos($forma, 'tarjeta') !== false) {
        $filas[$tc]['tarjeta'] += $monto;
    } elseif (strpos($forma, 'transfer') !== false) {
        $filas[$tc]['transferencia'] += $monto;
    } elseif (strpos($forma, 'yape') !== false) {
        $filas[$tc]['yape'] += $monto;
    } elseif (strpos($forma, 'plin') !== false) {
        $filas[$tc]['plin'] += $monto;
    }
}

// HEADERS EXCEL
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=Liquidacion_Caja_$fecha_inicio.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<meta charset='UTF-8'>";

// ================= TABLA =================
echo "<table border='1'>";

echo "<tr>
        <th colspan='7' style='font-size:14px'>{$info['nombre']}</th>
      </tr>";
echo "<tr>
        <th colspan='7'>LIQUIDACIÓN DE CAJA</th>
      </tr>";
echo "<tr>
        <th colspan='7'>Desde $fecha_inicio - Hasta $fecha_fin</th>
      </tr>";

echo "<tr>
        <th>Comprobante</th>
        <th>Efectivo</th>
        <th>Tarjeta</th>
        <th>Transferencia</th>
        <th>Yape / Plin</th>
        <th>Total</th>
      </tr>";

$total_general = 0;

foreach ($filas as $tc => $f) {

    $total = $f['efectivo'] + $f['tarjeta'] + $f['transferencia'] + $f['yape'] + $f['plin'];
    $total_general += $total;

    echo "<tr>
            <td>$tc</td>
            <td>{$f['efectivo']}</td>
            <td>{$f['tarjeta']}</td>
            <td>{$f['transferencia']}</td>
            <td>" . ($f['yape'] + $f['plin']) . "</td>
            <td><strong>$total</strong></td>
          </tr>";
}

echo "<tr>
        <td colspan='5'><strong>INGRESOS</strong></td>
        <td><strong>$total_general</strong></td>
      </tr>";

echo "<tr>
        <td colspan='5'><strong>EGRESOS</strong></td>
        <td>0.00</td>
      </tr>";

echo "<tr>
        <td colspan='5'><strong>TOTAL EN CAJA</strong></td>
        <td><strong>$total_general</strong></td>
      </tr>";

echo "</table>";
