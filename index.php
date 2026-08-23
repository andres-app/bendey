<?php

declare(strict_types=1);

if (!is_file(__DIR__ . '/Config/local.php') || !is_file(__DIR__ . '/storage/installed.lock')) {
    header('Location: /install/', true, 302);
    exit;
}

require_once __DIR__ . '/Libraries/MediaStorage.php';

tiquepos_media_migrate_legacy();

require_once 'Controllers/Plantilla.php';

$plantilla = new Plantilla();
$plantilla->mostrarPlantilla();
