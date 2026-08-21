<?php

declare(strict_types=1);

/*
 * Cloudflare Turnstile.
 * Site Key: pública.
 * Secret Key: solo servidor.
 * Los valores se escriben en Config/local.php durante /install/.
 */
$local = __DIR__ . '/local.php';
if (is_file($local)) {
    require_once $local;
}

$siteEnv = getenv('TURNSTILE_SITE_KEY');
$secretEnv = getenv('TURNSTILE_SECRET_KEY');

$siteKey = $siteEnv !== false && trim((string)$siteEnv) !== ''
    ? trim((string)$siteEnv)
    : (defined('TURNSTILE_SITE_KEY') ? trim((string)TURNSTILE_SITE_KEY) : '');

$secretKey = $secretEnv !== false && trim((string)$secretEnv) !== ''
    ? trim((string)$secretEnv)
    : (defined('TURNSTILE_SECRET_KEY') ? trim((string)TURNSTILE_SECRET_KEY) : '');

$allowed = [];
if (defined('TURNSTILE_ALLOWED_HOSTNAMES')) {
    $raw = TURNSTILE_ALLOWED_HOSTNAMES;
    if (is_array($raw)) {
        $allowed = $raw;
    } else {
        $allowed = preg_split('/\s*,\s*/', trim((string)$raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}

return [
    'site_key' => $siteKey,
    'secret_key' => $secretKey,
    'expected_action' => 'login',
    'allowed_hostnames' => array_values(array_unique(array_map(static fn($v) => strtolower(trim((string)$v)), $allowed))),
];
