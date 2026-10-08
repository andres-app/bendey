<?php

declare(strict_types=1);

/*
 * Cloudflare Turnstile de TiquePOS.
 * Configuración independiente de Config/local.php.
 */
$siteKey = '0x4AAAAAAEV9HsSzNpX_a0nz';
$secretKey = '0x4AAAAAAEV9HsoN24IGBG4jNFCvzsHR3PU';

return [
    'site_key' => $siteKey,
    'secret_key' => $secretKey,
    'expected_action' => 'login',
    'allowed_hostnames' => [
        'app.tiquepos.com',
    ],
];
