<?php
// enviar_sunat.php

// Datos del contribuyente de pruebas SUNAT (ambiente BETA)
$ruc = "20123456789";
$usuarioSol = "MODDATOS";
$claveSol = "moddatos";

// Nombre de archivo base
$nombreArchivo = "20123456789-01-F001-0000002";
$rutaXmlFirmado = __DIR__ . "/xml/{$nombreArchivo}.xml";
$rutaZip = __DIR__ . "/zip/{$nombreArchivo}.zip";

// Verifica que el XML firmado exista
if (!file_exists($rutaXmlFirmado)) {
    die("❌ El archivo XML firmado no existe: $rutaXmlFirmado");
}

// Crear ZIP con el XML firmado
$zip = new ZipArchive();
if ($zip->open($rutaZip, ZipArchive::CREATE) === true) {
    $zip->addFile($rutaXmlFirmado, "{$nombreArchivo}.xml");
    $zip->close();
} else {
    exit("❌ No se pudo crear el archivo ZIP");
}

// Codificar el ZIP en base64
$contenidoZipBase64 = base64_encode(file_get_contents($rutaZip));

// Cliente SOAP SUNAT (beta)
$wsdl = "https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService?wsdl";
$cliente = new SoapClient($wsdl, [
    'cache_wsdl' => WSDL_CACHE_NONE,
    'trace' => true,
    'exceptions' => true,
    'soap_version' => SOAP_1_1
]);

// Cabecera de seguridad WSSE
$namespace = 'http://schemas.xmlsoap.org/ws/2002/12/secext';
$header = new SoapHeader($namespace, 'Security', [
    'UsernameToken' => [
        'Username' => "$ruc$usuarioSol",
        'Password' => $claveSol
    ]
]);
$cliente->__setSoapHeaders([$header]);

// Enviar a SUNAT
try {
    $params = [
        'fileName' => "{$nombreArchivo}.zip",
        'contentFile' => $contenidoZipBase64
    ];

    $response = $cliente->__soapCall('sendBill', [$params]);

    // Guardar CDR
    file_put_contents(__DIR__ . "/cdr/{$nombreArchivo}.zip", base64_decode($response->applicationResponse));
    echo "✅ Factura enviada correctamente. CDR guardado en /cdr/{$nombreArchivo}.zip\n";
} catch (SoapFault $e) {
    echo "❌ Error al enviar: " . $e->faultstring . "\n";
    echo "\nRequest:\n" . $cliente->__getLastRequest();
    echo "\nResponse:\n" . $cliente->__getLastResponse();
    
}
?>
