<?php

/**
 * Configuración de Cloudflare Turnstile.
 *
 * La site_key puede mostrarse en el navegador.
 * La secret_key debe permanecer únicamente en el servidor.
 *
 * También puedes definir las variables de entorno:
 * TURNSTILE_SITE_KEY y TURNSTILE_SECRET_KEY.
 */

$siteKeyEntorno = getenv('TURNSTILE_SITE_KEY');
$secretKeyEntorno = getenv('TURNSTILE_SECRET_KEY');

return array(
    'site_key' => $siteKeyEntorno !== false && trim($siteKeyEntorno) !== ''
        ? trim($siteKeyEntorno)
        : '0x4AAAAAAEDDWo04epRHtqZQ',

    'secret_key' => $secretKeyEntorno !== false && trim($secretKeyEntorno) !== ''
        ? trim($secretKeyEntorno)
        : '0x4AAAAAAEDDWmug1vU8vO3QYMBk6dGj12E',

    // Debe coincidir con la opción "action" usada en login.js.
    'expected_action' => 'login',

    /*
     * Recomendado en producción. Ejemplo:
     * 'allowed_hostnames' => array('tudominio.com', 'www.tudominio.com'),
     *
     * Déjalo vacío mientras pruebas en localhost o en varios dominios.
     */
    'allowed_hostnames' => array()
);
