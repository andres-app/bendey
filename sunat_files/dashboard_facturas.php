<?php
// dashboard_facturas.php

$directorioCDR = __DIR__ . "/cdr";
$archivos = glob($directorioCDR . "/*.zip");

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Dashboard de Facturas</title>";
echo "<style>
    body { font-family: sans-serif; padding: 20px; background: #f5f5f5; }
    table { width: 100%; border-collapse: collapse; background: white; }
    th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
    th { background: #f0f0f0; }
    .estado-aceptado { color: green; font-weight: bold; }
    .estado-error { color: red; font-weight: bold; }
    .estado-observado { color: orange; font-weight: bold; }
</style></head><body>";
echo "<h2>📊 Dashboard de Facturas Enviadas a SUNAT</h2>";
echo "<table><thead><tr><th>#</th><th>Comprobante</th><th>Estado</th><th>Descripción</th></tr></thead><tbody>";

$count = 1;
foreach ($archivos as $zipPath) {
    $nombreArchivo = basename($zipPath, ".zip");
    $zip = new ZipArchive();

    if ($zip->open($zipPath) === true) {
        $estado = "❌ No leído";
        $desc = "-";

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (pathinfo($entry, PATHINFO_EXTENSION) === 'xml') {
                $xmlContent = $zip->getFromName($entry);
                $xml = new SimpleXMLElement($xmlContent);

                $ns = $xml->getNamespaces(true);
                $xml->registerXPathNamespace('cbc', $ns['cbc'] ?? '');
                $responseCode = $xml->xpath('//cbc:ResponseCode');
                $description = $xml->xpath('//cbc:Description');

                if ($responseCode && $description) {
                    $codigo = (string) $responseCode[0];
                    $desc = (string) $description[0];

                    if ($codigo === "0") {
                        $estado = "<span class='estado-aceptado'>✅ Aceptado</span>";
                    } elseif ($codigo >= 2000 && $codigo <= 3999) {
                        $estado = "<span class='estado-observado'>⚠️ Observado ($codigo)</span>";
                    } else {
                        $estado = "<span class='estado-error'>❌ Error ($codigo)</span>";
                    }
                }
            }
        }
        $zip->close();
    } else {
        $estado = "❌ No se pudo abrir";
        $desc = "-";
    }

    echo "<tr><td>{$count}</td><td>{$nombreArchivo}</td><td>{$estado}</td><td>{$desc}</td></tr>";
    $count++;
}

echo "</tbody></table></body></html>";
?>
