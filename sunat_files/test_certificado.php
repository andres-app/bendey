<?php
// test_certificado.php

$pfxPath = __DIR__ . '/certificados/certificado_felicitygirls.p12';
$pfxPassword = 'Felicity1'; // Reemplaza por tu clave real

echo "🔍 Probando lectura de: $pfxPath\n";

if (!file_exists($pfxPath)) {
    die("❌ No se encontró el archivo PFX.\n");
}

$pfxContent = file_get_contents($pfxPath);
if (!$pfxContent) {
    die("❌ No se pudo leer el archivo PFX.\n");
}

if (!openssl_pkcs12_read($pfxContent, $certs, $pfxPassword)) {
    die("❌ No se pudo procesar el PFX. La clave puede ser incorrecta o el archivo inválido.\n");
}

echo "✅ Certificado leído correctamente.\n";

// Mostrar sujeto (subject) del certificado
$certInfo = openssl_x509_parse($certs['cert']);
echo "📄 Sujeto del certificado: " . ($certInfo['subject']['CN'] ?? 'Sin CN') . "\n";
?>