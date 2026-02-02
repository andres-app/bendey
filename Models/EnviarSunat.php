<?php

class EnviarSunat
{
    public function enviar($idventa)
    {
        $conexion = new Conexion();

        // ==========================================
        // 1. Obtener XML desde BD
        // ==========================================
        $sql = "SELECT xml 
                FROM venta_sunat 
                WHERE idventa = ? 
                LIMIT 1";

        $r = $conexion->getData($sql, [$idventa]);

        if (!is_array($r) || empty($r['xml'])) {
            return [
                'status'  => false,
                'mensaje' => 'No existe XML para enviar a SUNAT'
            ];
        }

        $rutaXML = $r['xml']; // ej: xml/2026/01/B001-0000045.xml

        // ==========================================
        // 2. Extraer año y mes desde la ruta del XML
        // ==========================================
        $partes = explode('/', $rutaXML);

        // Esperado: xml / YYYY / MM / archivo.xml
        if (count($partes) < 4) {
            return [
                'status'  => false,
                'mensaje' => 'Ruta de XML inválida'
            ];
        }

        $anio = $partes[1];
        $mes  = $partes[2];

        // ==========================================
        // 3. Preparar carpeta CDR por año/mes
        // ==========================================
        $baseCdr = __DIR__ . '/../cdr/';
        $carpetaCdr = $baseCdr . $anio . '/' . $mes . '/';

        if (!is_dir($carpetaCdr)) {
            mkdir($carpetaCdr, 0777, true);
        }

        // ==========================================
        // 4. Crear ZIP CDR (simulación SUNAT)
        // ==========================================
        $nombreXml = basename($rutaXML, '.xml'); // B001-0000045
        $nombreCdr = 'R-' . $nombreXml . '.zip';

        $rutaCdrAbsoluta = $carpetaCdr . $nombreCdr;

        $zip = new ZipArchive();
        if ($zip->open($rutaCdrAbsoluta, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {

            // XML interno del CDR (respuesta SUNAT)
            $zip->addFromString(
                'R-' . $nombreXml . '.xml',
                '<?xml version="1.0" encoding="UTF-8"?>
                <ApplicationResponse>
                    <ResponseCode>0</ResponseCode>
                    <Description>La constancia ha sido aceptada</Description>
                </ApplicationResponse>'
            );

            $zip->close();
        } else {
            return [
                'status'  => false,
                'mensaje' => 'No se pudo crear el archivo CDR'
            ];
        }

        // ==========================================
        // 5. Ruta RELATIVA para BD
        // ==========================================
        $cdrRuta = 'cdr/' . $anio . '/' . $mes . '/' . $nombreCdr;

        // ==========================================
        // 6. Respuesta simulada SUNAT (OK)
        // ==========================================
        return [
            'status'  => true,
            'estado'  => 'ACEPTADO',
            'mensaje' => 'La constancia ha sido aceptada',
            'cdr'     => $cdrRuta
        ];
    }
}
