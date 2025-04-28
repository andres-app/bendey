<?php
// Datos SUNAT
$ruc = "20609068800"; // Tu RUC
$usuarioSol = "OODICERA"; // Tu usuario SOL
$claveSol = "itylvelon"; // Tu clave SOL

// Archivo ZIP
$nombreZip = "$ruc-01-F001-00000025.zip";
$rutaZip = __DIR__ . "/zip/" . $nombreZip;

if (!file_exists($rutaZip)) {
    die("❌ No se encontró el ZIP: $rutaZip");
}

// WSDL y URL del servicio
$urlSunat = "https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService";
$wsdl = "https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService?wsdl";

// Codificar ZIP en Base64
$zipBase64 = base64_encode(file_get_contents($rutaZip));

// Armar XML SOAP
$soapRequest = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:ser="http://service.sunat.gob.pe">
  <soapenv:Header/>
  <soapenv:Body>
    <ser:sendBill>
      <fileName>{$nombreZip}</fileName>
      <contentFile>{$zipBase64}</contentFile>
    </ser:sendBill>
  </soapenv:Body>
</soapenv:Envelope>
XML;

// Iniciar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlSunat);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
curl_setopt($ch, CURLOPT_USERPWD, "$ruc$usuarioSol:$claveSol");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: text/xml; charset=utf-8",
    "SOAPAction: \"\"",
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $soapRequest);

// Ejecutar
$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "❌ Error CURL: " . curl_error($ch);
    exit;
}

// Cerrar cURL
curl_close($ch);

// Mostrar respuesta (para debug)
header('Content-Type: text/xml');
echo $response;
?>
