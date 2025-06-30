<?php
require __DIR__ . '/../vendor/autoload.php';
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

// Rutas
$xmlPath = __DIR__ . '/../sunat_files/xml/20609068800-01-F001-00000026.xml';
$pfxPath = __DIR__ . '/../sunat_files/certificados/certi.pfx';
$pfxPassword = 'Dev2804751';

if (!file_exists($pfxPath) || !file_exists($xmlPath)) {
    die("❌ Certificado o XML no encontrado.");
}

// Leer certificado
$pfx = file_get_contents($pfxPath);
if (!openssl_pkcs12_read($pfx, $certs, $pfxPassword)) {
    die("❌ No se pudo leer el PFX.");
}

$doc = new DOMDocument();
$doc->preserveWhiteSpace = false;
$doc->formatOutput = true;
$doc->load($xmlPath);

// Obtener nodo raíz y colocar atributo ID si no existe
$root = $doc->documentElement;
$idFactura = $doc->getElementsByTagName('ID')->item(0)->nodeValue; // F001-00000026
$root->setAttribute("ID", $idFactura);

// Preparar firma
$objDSig = new XMLSecurityDSig();
$objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

// Agregar referencia CON ID explícito
$objDSig->addReference(
    $root,
    XMLSecurityDSig::SHA1,
    ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
    ['uri' => '#' . $idFactura]
);

// Clave privada
$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type'=>'private']);
$objKey->loadKey($certs['pkey'], false);

// Firmar y adjuntar
$objDSig->sign($objKey);
$objDSig->add509Cert($certs['cert']);
$objDSig->appendSignature($root);

// Guardar XML firmado
$doc->save($xmlPath);
echo "✅ XML firmado correctamente: $idFactura";