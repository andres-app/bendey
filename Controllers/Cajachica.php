<?php
require_once "../Models/Cajachica.php";

if (strlen(session_id()) < 1) {
    session_start();
}

$caja = new Cajachica();

$op = $_GET['op'] ?? '';

switch ($op) {

    case 'resumen':

        $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d');
        $fecha_fin    = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
        $idusuario    = isset($_GET['idusuario']) ? $_GET['idusuario'] : null;
    
        $detalle = $caja->resumen($fecha_inicio, $fecha_fin, $idusuario);
        $totales = $caja->totales($fecha_inicio, $fecha_fin, $idusuario);
    
        // Apertura por la fecha del filtro
        $apertura = $caja->obtenerAperturaPorFecha($fecha_inicio);
    
        // Estado del día de HOY (si hay registro, sea ABIERTA o CERRADA)
        $aperturaHoy = $caja->obtenerAperturaHoy();
    
        $estadoHoy = 'SIN_APERTURA';
        if (is_array($aperturaHoy) && isset($aperturaHoy['estado'])) {
            $estadoHoy = $aperturaHoy['estado']; // ABIERTA | CERRADA
        }
    
        echo json_encode(array(
            'detalle'  => $detalle,
            'totales'  => $totales,
            'apertura' => $apertura,
            'estado'   => $estadoHoy
        ));
    
        break;
    

    case 'verificar_apertura':

        $apertura = $caja->obtenerAperturaHoy();

        if ($apertura) {

            echo json_encode([
                'existe' => $apertura ? true : false,
                'estado' => $apertura['estado'] ?? 'SIN_APERTURA'
            ]);
            
        } else {

            echo json_encode([
                'existe' => false,
                'estado' => 'CERRADA'
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

    case 'cerrar_caja':

        $montoContado = $_POST['monto_contado'] ?? 0;
        $idusuario = $_SESSION['idusuario'] ?? 0;

        // 🔒 Verificar si realmente hay caja ABIERTA
        $apertura = $caja->obtenerCajaAbiertaHoy();

        if (!$apertura) {
            echo json_encode([
                'status' => 'error',
                'message' => 'La caja ya está cerrada'
            ]);
            break;
        }

        $resultado = $caja->cerrarCaja($montoContado, $idusuario);

        echo json_encode($resultado);

        break;



    case 'datos_cierre':

        $apertura = $caja->obtenerCajaAbiertaHoy();

        if (!$apertura) {
            echo json_encode([
                'status' => false,
                'message' => 'La caja ya está cerrada'
            ]);
            break;
        }

        $totales = $caja->totales(date('Y-m-d'), date('Y-m-d'));

        $totalSistema = $apertura['monto_apertura'] + ($totales['ingresos'] ?? 0);

        echo json_encode([
            'status' => true,
            'total_sistema' => $totalSistema
        ]);
}
