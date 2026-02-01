<?php

class EnviarSunat
{
    public function enviar($idventa)
    {
        // 1. Obtener XML desde BD
        $conexion = new Conexion();

        $sql = "SELECT xml 
                FROM venta_sunat 
                WHERE idventa = ? 
                LIMIT 1";

        $r = $conexion->getData($sql, [$idventa]);

        if (!is_array($r) || empty($r['xml'])) {
            return [
                'status' => false,
                'mensaje' => 'No existe XML para enviar a SUNAT'
            ];
        }

        $rutaXML = $r['xml'];

        /* ==================================================
           2. AQUÍ VA TU ENVÍO REAL A SUNAT (SOAP)
           ==================================================
           - envías XML
           - SUNAT responde ZIP (CDR)
        */

        // ⚠️ SIMULACIÓN (para dejar armado el flujo)
        $cdrRuta = 'files/cdr/R-' . basename($rutaXML) . '.zip';

        // Simulación de respuesta SUNAT
        $codigoSunat = '0';
        $mensajeSunat = 'La constancia ha sido aceptada';

        if ($codigoSunat === '0') {
            return [
                'status'  => true,
                'estado'  => 'ACEPTADO',
                'mensaje' => $mensajeSunat,
                'cdr'     => $cdrRuta
            ];
        } else {
            return [
                'status'  => true,
                'estado'  => 'RECHAZADO',
                'mensaje' => $mensajeSunat,
                'cdr'     => ''
            ];
        }
    }
}
