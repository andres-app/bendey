<?php
require __DIR__ . '/../vendor/autoload.php';

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

// Datos
$pfxPath = __DIR__ . '/../sunat_files/certificados/certi.pfx';
$pfxPassword = 'Dev2804751';

$xmlPath = __DIR__ . '/../sunat_files/xml/20609068800-01-F001-00000025.xml';
$signedXmlPath = __DIR__ . '/../sunat_files/xml/20609068800-01-F001-00000025.xml';

if (!file_exists($pfxPath)) {
    die('❌ Archivo PFX no encontrado.');
}

$pfx = file_get_contents($pfxPath);
if (!openssl_pkcs12_read($pfx, $certs, $pfxPassword)) {
    die('❌ No se pudo abrir el certificado PFX. Verifica la clave.');
}

$privateKey = $certs['pkey'];
$publicCert = $certs['cert'];

$doc = new DOMDocument();
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;
$doc->load($xmlPath);

// Asegurar que el nodo raíz tiene ID
$root = $doc->documentElement;
if (!$root->hasAttribute('ID')) {
    $root->setAttribute('ID', "F001-00000025");
}

$objDSig = new XMLSecurityDSig();
$objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

// Firmar específicamente el ID
$objDSig->addReference(
    $doc,
    XMLSecurityDSig::SHA1,
    ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
    ['uri' => '#F001-00000025']
);

$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type'=>'private']);
$objKey->loadKey($privateKey, false);

// Firmar
$objDSig->sign($objKey);
$objDSig->add509Cert($publicCert);
$objDSig->appendSignature($doc->documentElement);

// Guardar
$doc->save($signedXmlPath);

echo "✅ XML firmado correctamente: " . basename($signedXmlPath);
?>
