<?php
require_once "../Models/Cajachica.php";

if (strlen(session_id()) < 1) {
    session_start();
}

$caja = new Cajachica();

$op = $_GET['op'] ?? '';

switch ($op) {

    case 'resumen':

        $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
        $fecha_fin    = $_GET['fecha_fin'] ?? date('Y-m-d');
        $idusuario    = $_GET['idusuario'] ?? null;

        $detalle = $caja->resumen($fecha_inicio, $fecha_fin, $idusuario);
        $totales = $caja->totales($fecha_inicio, $fecha_fin, $idusuario);

        echo json_encode([
            'detalle' => $detalle,
            'totales' => $totales
        ]);
        break;

    case 'verificar_apertura':

        $apertura = $caja->existeAperturaHoy();

        if ($apertura) {

            $totales = $caja->totales(date('Y-m-d'), date('Y-m-d'));

            echo json_encode([
                'existe' => true,
                'apertura' => $apertura,
                'totales' => $totales
            ]);
        } else {

            echo json_encode([
                'existe' => false
            ]);
        }

        break;

        case 'guardar_apertura':

            $monto = $_POST['monto'] ?? 0;
            $idusuario = $_SESSION['idusuario'] ?? 0;
        
            $ok = $caja->registrarApertura($monto, $idusuario);
        
            echo json_encode([
                'status' => $ok ? 'ok' : 'error'
            ]);
        
            break;
        
}
