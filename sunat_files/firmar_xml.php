<?php
// firmar_xml.php

require_once __DIR__ . '/../Libraries/xmlseclibs/src/XMLSecurityDSig.php';
require_once __DIR__ . '/../Libraries/xmlseclibs/src/XMLSecurityKey.php';

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

// Ruta del certificado PFX
$pfxPath = __DIR__ . '/certificados/certificado_felicitygirls.p12';
$pfxPassword = 'Felicity1'; // <-- Reemplaza esto con la clave real

// Ruta del XML a firmar
$xmlPath = __DIR__ . '/xml/20123456789-01-F001-00000022.xml';
$xmlFirmadoPath = __DIR__ . '/xml/20123456789-01-F001-00000022_firmado.xml';

echo "🔍 Buscando certificado en: $pfxPath\n";
echo "🔍 Buscando XML en: $xmlPath\n";

if (!file_exists($pfxPath)) {
    die("❌ No se encontró el archivo del certificado PFX.");
}

if (!file_exists($xmlPath)) {
    die("❌ No se encontró el archivo XML.");
}

// Leer el contenido del certificado
$pfxContent = file_get_contents($pfxPath);
if (!$pfxContent) {
    die("❌ No se pudo leer el contenido del archivo PFX.");
}
if (!openssl_pkcs12_read($pfxContent, $certs, $pfxPassword)) {
    die("❌ No se pudo leer el archivo PFX. Verifica la clave.");
}

$privateKey = $certs['pkey'];
$publicCert = $certs['cert'];

// Cargar XML
$doc = new DOMDocument();
$doc->load($xmlPath);
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;

// Firmar el XML
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

// Guardar el archivo firmado
$doc->save($xmlFirmadoPath);
echo "✅ XML firmado correctamente: " . basename($xmlFirmadoPath) . "\n";
?>