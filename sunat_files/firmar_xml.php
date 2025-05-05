<?php
require __DIR__ . '/../vendor/autoload.php';

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

// === Configuración ===
$pfxPath = __DIR__ . '/../sunat_files/certificados/certi.pfx';
$pfxPassword = 'Dev2804751';

$serie = 'F001';
$numero = '00000026'; // 👈 usa el número correcto dinámicamente si deseas
$idComprobante = "$serie-$numero";
$xmlPath = __DIR__ . "/../sunat_files/xml/20609068800-01-$idComprobante.xml";
$signedXmlPath = $xmlPath; // mismo archivo

// === Cargar PFX ===
if (!file_exists($pfxPath)) {
    die('❌ Archivo PFX no encontrado.');
}
$pfx = file_get_contents($pfxPath);
if (!openssl_pkcs12_read($pfx, $certs, $pfxPassword)) {
    die('❌ No se pudo abrir el certificado PFX. Verifica la clave.');
}
$privateKey = $certs['pkey'];
$publicCert = $certs['cert'];

// === Cargar XML ===
$doc = new DOMDocument();
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;
$doc->load($xmlPath);

// === Asegurar que el nodo raíz tenga atributo ID ===
$root = $doc->documentElement;
$root->setAttribute("ID", $idComprobante);

// === Firmar ===
$objDSig = new XMLSecurityDSig();
$objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
$objDSig->addReference(
    $root,
    XMLSecurityDSig::SHA1,
    ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
    ['uri' => "#$idComprobante"] // referenciar por ID
);

$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
$objKey->loadKey($privateKey, false);

$objDSig->sign($objKey);
$objDSig->add509Cert($publicCert);
$objDSig->appendSignature($root);

// === Guardar firmado ===
$doc->save($signedXmlPath);
echo "✅ XML firmado correctamente: $idComprobante";
