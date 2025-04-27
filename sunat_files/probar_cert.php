<?php
// Ruta del certificado .p12
$pfx = __DIR__ . "/certificados/certificado.p12";

// Contraseña del certificado
$password = "A123456a"; // reemplaza por la clave real

echo "🔍 Probando lectura de: $pfx\n";

// Verifica que el archivo exista
if (!file_exists($pfx)) {
    die("❌ El archivo no existe: $pfx\n");
}

// Leer el archivo PFX
$pfxContent = file_get_contents($pfx);

if (openssl_pkcs12_read($pfxContent, $certs, $password)) {
    echo "✅ Certificado leído correctamente.\n";

    // Información opcional para confirmar lectura
    echo "🔐 Certificado:\n" . $certs['cert'] . "\n";
} else {
    echo "❌ No se pudo leer el archivo PFX. Verifica la clave o el archivo.\n";
}
?>
