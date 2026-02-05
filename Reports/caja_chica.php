<?php
include('../Libraries/fpdf182/fpdf.php');
require_once '../Models/Cajachica.php';
require_once '../Models/Company.php';

function t($txt) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $txt);
}

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$fecha_fin    = $_GET['fecha_fin'] ?? date('Y-m-d');

$caja = new Cajachica();
$data = $caja->resumen($fecha_inicio, $fecha_fin);
$totales = $caja->totales($fecha_inicio, $fecha_fin);

// Empresa
$empresa = new Company();
$info = $empresa->listar()[0] ?? [];

$pdf = new FPDF('P', 'mm', [80, 200]);
$pdf->AddPage();
$pdf->SetMargins(4, 4, 4);
$pdf->SetAutoPageBreak(true, 5);

/* ======================
   ENCABEZADO
====================== */
$pdf->SetFont('Arial', 'B', 10);
$pdf->MultiCell(0, 5, t(strtoupper($info['nombre'] ?? 'EMPRESA')), 0, 'C');

$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(0, 4, t('RUC: ' . ($info['ruc'] ?? '-')), 0, 'C');
$pdf->MultiCell(0, 4, t($info['direccion'] ?? '-'), 0, 'C');

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 5, t('LIQUIDACIÓN DE CAJA'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 4, t("Desde: $fecha_inicio"), 0, 1, 'C');
$pdf->Cell(0, 4, t("Hasta: $fecha_fin"), 0, 1, 'C');

$pdf->Ln(3);

/* ======================
   AGRUPAR DATOS
====================== */
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

/* ======================
   DETALLE (FORMATO TABLA)
====================== */
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 5, t('RESUMEN'), 0, 1, 'L');
$pdf->Ln(1);

$total_general = 0;

foreach ($filas as $tc => $f) {

    $total = $f['efectivo'] + $f['tarjeta'] + $f['transferencia'] + $f['yape'] + $f['plin'];
    $total_general += $total;

    // ---- TÍTULO COMPROBANTE ----
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(0, 5, t($tc), 0, 1);

    // Línea
    $pdf->Cell(0, 1, t(str_repeat('-', 32)), 0, 1);

    // ---- CABECERA TABLA ----
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(40, 5, t('Concepto'), 0, 0);
    $pdf->Cell(32, 5, t('Monto'), 0, 1, 'R');

    $pdf->Cell(0, 1, t(str_repeat('-', 32)), 0, 1);

    $pdf->SetFont('Arial', '', 8);

    // ---- FILAS ----
    if ($f['efectivo'] > 0) {
        $pdf->Cell(40, 5, t('Efectivo'), 0, 0);
        $pdf->Cell(32, 5, 'S/ ' . number_format($f['efectivo'], 2), 0, 1, 'R');
    }

    if ($f['tarjeta'] > 0) {
        $pdf->Cell(40, 5, t('Tarjeta'), 0, 0);
        $pdf->Cell(32, 5, 'S/ ' . number_format($f['tarjeta'], 2), 0, 1, 'R');
    }

    if ($f['transferencia'] > 0) {
        $pdf->Cell(40, 5, t('Transferencia'), 0, 0);
        $pdf->Cell(32, 5, 'S/ ' . number_format($f['transferencia'], 2), 0, 1, 'R');
    }

    if (($f['yape'] + $f['plin']) > 0) {
        $pdf->Cell(40, 5, t('Yape / Plin'), 0, 0);
        $pdf->Cell(32, 5, 'S/ ' . number_format($f['yape'] + $f['plin'], 2), 0, 1, 'R');
    }

    // Línea
    $pdf->Cell(0, 1, t(str_repeat('-', 32)), 0, 1);

    // ---- TOTAL ----
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(40, 6, t('TOTAL'), 0, 0);
    $pdf->Cell(32, 6, 'S/ ' . number_format($total, 2), 0, 1, 'R');

    $pdf->Ln(2);
}


/* ======================
   TOTALES
====================== */
$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 5, t('------------------------------'), 0, 1, 'C');

$pdf->Cell(0, 5, t('INGRESOS: S/ ' . number_format($total_general, 2)), 0, 1);
$pdf->Cell(0, 5, t('EGRESOS: S/ 0.00'), 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, t('TOTAL CAJA: S/ ' . number_format($total_general, 2)), 0, 1);

$pdf->Ln(3);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 4, t('--- FIN DEL REPORTE ---'), 0, 1, 'C');

$pdf->Output('I', 'Liquidacion_Caja_80mm.pdf');
