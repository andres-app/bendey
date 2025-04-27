<?php
$pfxFile = __DIR__ . '/certificados/mi_certificado.pfx';
$pfxPassword = '123456';

if (!file_exists($pfxFile)) {
    die("❌ No se encontró el archivo.\n");
}

$pfxContent = file_get_contents($pfxFile);
if (!openssl_pkcs12_read($pfxContent, $certs, $pfxPassword)) {
    die("❌ No se pudo leer el PFX.\n");
}

echo "✅ PFX leído correctamente.\n";
echo "Contiene clave privada: " . (isset($certs['pkey']) ? "Sí" : "No") . "\n";
echo "Contiene certificado: " . (isset($certs['cert']) ? "Sí" : "No") . "\n";
