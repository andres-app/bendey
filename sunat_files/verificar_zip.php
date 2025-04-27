<?php
// verificar_zip.php

$nombreArchivo = "20123456789-01-F001-0000002";
$rutaZip = __DIR__ . "/zip/{$nombreArchivo}.zip";

echo "📦 Verificando ZIP: $rutaZip\n";

if (!file_exists($rutaZip)) {
    die("❌ El archivo ZIP no existe.\n");
}

$zip = new ZipArchive();
if ($zip->open($rutaZip) === true) {
    echo "✅ ZIP abierto correctamente.\n";
    echo "🗂 Contenido del ZIP:\n";

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        echo "- Archivo encontrado: $entry\n";

        // Mostrar contenido si es XML
        if (pathinfo($entry, PATHINFO_EXTENSION) === 'xml') {
            echo "\n📄 Mostrando contenido XML:\n";
            $xmlContent = $zip->getFromName($entry);
            echo substr($xmlContent, 0, 1000); // Mostrar solo los primeros 1000 caracteres
        }
    }
    $zip->close();
} else {
    echo "❌ No se pudo abrir el archivo ZIP.\n";
}
?>
