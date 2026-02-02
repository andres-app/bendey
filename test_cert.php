<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pfxPath = __DIR__ . '/certificado/cert.p12';

if (!file_exists($pfxPath)) {
    die('❌ No existe el archivo P12 en: ' . $pfxPath);
}

$pfx = file_get_contents($pfxPath);

$certs = [];
if (openssl_pkcs12_read($pfx, $certs, 'Felicity1')) {
    echo "✅ P12 leído correctamente<br>";
    echo "Cert: " . (isset($certs['cert']) ? 'OK' : 'NO') . "<br>";
    echo "Key: " . (isset($certs['pkey']) ? 'OK' : 'NO') . "<br>";
} else {
    echo "❌ ERROR al leer P12<br><br>";
    while ($e = openssl_error_string()) {
        echo $e . "<br>";
    }
}
