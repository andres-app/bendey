<?php
// comprimir_zip.php

$zip = new ZipArchive();
$zipFile = __DIR__ . '/zip/20609068800-01-F001-00000026.zip';
$xmlPath = __DIR__ . '/xml/20609068800-01-F001-00000026.xml';

if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
    $zip->addFile($xmlPath, "20609068800-01-F001-00000026.xml");
    $zip->close();
    echo "✅ ZIP creado.";
} else {
    echo "❌ Error al crear ZIP.";
}

