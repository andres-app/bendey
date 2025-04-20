<?php
// enviar_sunat.php

// Datos del contribuyente de pruebas SUNAT (ambiente BETA)
$ruc = "20123456789";
$usuarioSol = "MODDATOS";
$claveSol = "moddatos";

// Archivo firmado y rutas
$nombreArchivo = "20123456789-01-F001-0000002";
$rutaZip = __DIR__ . "/zip/{$nombreArchivo}.zip";
$rutaXmlFirmado = __DIR__ . "/xml/factura_F001-0000002_firmado.xml";

// Crear ZIP con el XML firmado
$zip = new ZipArchive();
if ($zip->open($rutaZip, ZipArchive::CREATE) === true) {
    $zip->addFile($rutaXmlFirmado, "{$nombreArchivo}.xml");
    $zip->close();
} else {
    exit("No se pudo crear el archivo ZIP");
}

// Codificar el contenido del ZIP en base64
$contenidoZip = file_get_contents($rutaZip);
$contenidoZipBase64 = base64_encode($contenidoZip);

// Cargar el WSDL y preparar el cliente SOAP
$wsdl = "https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService?wsdl";
$cliente = new SoapClient($wsdl, [
    'cache_wsdl' => WSDL_CACHE_NONE,
    'trace' => true,
    'exceptions' => true,
    'soap_version' => SOAP_1_1
]);

// Cabecera WSSE (autenticación)
$namespace = 'http://schemas.xmlsoap.org/ws/2002/12/secext';
$header = new SoapHeader(
    $namespace,
    'Security',
    [
        'UsernameToken' => [
            'Username' => "$ruc$usuarioSol",
            'Password' => $claveSol
        ]
    ]
);
$cliente->__setSoapHeaders([$header]);

// Enviar la factura con sendBill
try {
    $params = [
        'fileName' => $nombreArchivo . '.zip',
        'contentFile' => $contenidoZipBase64
    ];

    $response = $cliente->__soapCall('sendBill', [$params]);

    // Guardar CDR (acuse SUNAT)
    $cdrZipB64 = $response->applicationResponse;
    file_put_contents("cdr/{$nombreArchivo}.zip", base64_decode($cdrZipB64));
    echo "Factura enviada correctamente. CDR guardado en /cdr/{$nombreArchivo}.zip\n";
} catch (SoapFault $e) {
    echo "Error al enviar: " . $e->faultstring;
    echo "\nRequest: \n" . $cliente->__getLastRequest();
    echo "\nResponse: \n" . $cliente->__getLastResponse();
}
?>
