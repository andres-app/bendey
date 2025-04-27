<?php
// verificar_envio.php

$nombreArchivo = "20123456789-01-F001-0000002";
$rutaXml = __DIR__ . "/xml/{$nombreArchivo}.xml";
$rutaZip = __DIR__ . "/zip/{$nombreArchivo}.zip";

echo "🔍 Verificando XML y ZIP para SUNAT\n";
echo "-----------------------------------\n";

// 1. Verificar archivo XML
if (!file_exists($rutaXml)) {
    die("❌ El XML no existe: $rutaXml\n");
}
echo "✅ XML encontrado: $rutaXml\n";

$xmlContent = file_get_contents($rutaXml);

// 2. Validar contenido clave del XML
if (str_contains($xmlContent, '<Invoice') && str_contains($xmlContent, '<ds:Signature')) {
    echo "✅ El XML contiene <Invoice> y <ds:Signature>\n";
} else {
    echo "❌ El XML no contiene <Invoice> y/o <ds:Signature>\n";
}

// 3. Verificar archivo ZIP
if (!file_exists($rutaZip)) {
    die("❌ El ZIP no existe: $rutaZip\n");
}
echo "✅ ZIP encontrado: $rutaZip\n";

$zip = new ZipArchive();
if ($zip->open($rutaZip) === true) {
    $encontrado = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        echo "- Contenido ZIP: $entry\n";
        if ($entry === "{$nombreArchivo}.xml") {
            $zipXmlContent = $zip->getFromName($entry);
            if (str_contains($zipXmlContent, '<Invoice') && str_contains($zipXmlContent, '<ds:Signature')) {
                echo "✅ El XML dentro del ZIP tiene estructura válida y está firmado\n";
            } else {
                echo "❌ El XML dentro del ZIP no tiene <Invoice> o <ds:Signature>\n";
            }
            $encontrado = true;
        }
    }
    $zip->close();

    if (!$encontrado) {
        echo "❌ No se encontró el archivo {$nombreArchivo}.xml dentro del ZIP\n";
    }
} else {
    echo "❌ No se pudo abrir el archivo ZIP\n";
}
?>
