<?php

declare(strict_types=1);

/*
 * TiquePOS inicia directamente en la aplicación.
 * La instalación web y el control remoto fueron retirados.
 * Cuando no se especifica una ruta, Views/Plantilla.php muestra el login.
 */
require_once __DIR__ . '/Libraries/MediaStorage.php';

tiquepos_media_migrate_legacy();

require_once __DIR__ . '/Controllers/Plantilla.php';

$plantilla = new Plantilla();
$plantilla->mostrarPlantilla();
