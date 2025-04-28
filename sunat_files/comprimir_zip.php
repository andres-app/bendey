<?php
// comprimir_zip.php

// Nombre del XML ya firmado
$xmlFirmado = '20609068800-01-F001-00000025.xml';

// Nombre del ZIP (sin el -SIGNED)
$zipNombre = '20609068800-01-F001-00000025.zip';

// Rutas (ajustar si tu carpeta es diferente)
$rutaXml = __DIR__ . '/xml/' . $xmlFirmado; // Ajusta si tu XML está en otra carpeta
$rutaZip = __DIR__ . '/zip/' . $zipNombre;  // Carpeta destino del ZIP

// Crear carpeta zip si no existe
if (!is_dir(dirname($rutaZip))) {
    mkdir(dirname($rutaZip), 0777, true);
}

// Crear ZIP
$zip = new ZipArchive();
if ($zip->open($rutaZip, ZipArchive::CREATE) === true) {
    // Agregar el XML firmado al ZIP (el nombre dentro del zip debe ser igual)
    $zip->addFile($rutaXml, $xmlFirmado);
    $zip->close();
    echo "✅ ZIP creado correctamente: $zipNombre";
} else {
    echo "❌ Error al crear ZIP.";
}
