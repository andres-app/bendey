<?php
// firmar_xml.php

// Ruta al archivo XML generado
$xmlPath = __DIR__ . '/xml/20123456789-01-F001-0000002.xml';

// Ruta del certificado PFX y su clave
$pfxFile = __DIR__ . '/certificados/mi_certificado.p12';
$pfxPassword = '7260apisun'; // Cambia esto por tu clave real

// Cargar el PFX
if (!file_exists($pfxFile)) {
    die("No se encontró el archivo PFX");
}

$pfxContent = file_get_contents($pfxFile);
if (!openssl_pkcs12_read($pfxContent, $certs, $pfxPassword)) {
    die("No se pudo leer el archivo PFX. Verifica la clave.");
}

$privateKey = $certs['pkey'];
$publicCert = $certs['cert'];

// Cargar el XML
$doc = new DOMDocument();
$doc->load($xmlPath);
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;

// Crear firma digital usando XMLSecLibs
require_once __DIR__ . '/../Libraries/xmlseclibs/src/XMLSecurityDSig.php';
require_once __DIR__ . '/../Libraries/xmlseclibs/src/XMLSecurityKey.php';

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

$objDSig = new XMLSecurityDSig();
$objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
$objDSig->addReference(
    $doc,
    XMLSecurityDSig::SHA1,
    ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
    ['force_uri' => true]
);

$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
$objKey->loadKey($privateKey, false);

$objDSig->sign($objKey);
$objDSig->add509Cert($publicCert);
$objDSig->appendSignature($doc->documentElement);

// Guardar el XML firmado (sobrescribe el mismo archivo)
$doc->save($xmlPath);
echo "✅ XML firmado correctamente: 20123456789-01-F001-0000002.xml\n";
?>
