<?php
// leer_cdr.php

$nombreArchivo = "20123456789-01-F001-0000002";
$rutaZipCdr = __DIR__ . "/cdr/{$nombreArchivo}.zip";

if (!file_exists($rutaZipCdr)) {
    die("❌ No se encontró el archivo CDR: $rutaZipCdr\n");
}

$zip = new ZipArchive();
if ($zip->open($rutaZipCdr) === true) {
    echo "✅ CDR abierto correctamente.\n";

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        echo "- Archivo encontrado: $entry\n";

        if (pathinfo($entry, PATHINFO_EXTENSION) === 'xml') {
            echo "\n📄 Mostrando respuesta SUNAT:\n";

            $cdrXml = $zip->getFromName($entry);
            $xml = new SimpleXMLElement($cdrXml);

            $ns = $xml->getNamespaces(true);
            $xml->registerXPathNamespace('cbc', $ns['cbc'] ?? '');
            $xml->registerXPathNamespace('cac', $ns['cac'] ?? '');

            $responseCode = $xml->xpath('//cbc:ResponseCode');
            $description = $xml->xpath('//cbc:Description');

            if ($responseCode && $description) {
                echo "📥 Código SUNAT: " . (string) $responseCode[0] . "\n";
                echo "📝 Descripción: " . (string) $description[0] . "\n";

                if ((string)$responseCode[0] === '0') {
                    echo "✅ SUNAT aceptó la factura.\n";
                } else {
                    echo "⚠️ SUNAT respondió con observación o rechazo.\n";
                }
            } else {
                echo "⚠️ No se encontró información en el CDR.\n";
            }
        }
    }
    $zip->close();
} else {
    echo "❌ No se pudo abrir el ZIP del CDR.\n";
}
?>
