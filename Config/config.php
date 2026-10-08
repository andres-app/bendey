<?php

declare(strict_types=1);

date_default_timezone_set('America/Lima');

/*
 * TiquePOS standalone.
 * Sin instalador web, sin Config/local.php y sin control/licenciamiento remoto.
 * La conexión corresponde a la instalación existente de app.tiquepos.com.
 */
if (!defined('HOST')) define('HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'u274409976_tpsunat');
if (!defined('DB_PASS')) define('DB_PASS', 'Dev2804751$$$');
if (!defined('DB_NAME')) define('DB_NAME', 'u274409976_tpsunat');
if (!defined('PORT')) define('PORT', 3306);
if (!defined('CHARSET')) define('CHARSET', 'utf8mb4');
if (!defined('API_KEY')) define('API_KEY', '');
if (!defined('APP_DOMAIN')) define('APP_DOMAIN', 'app.tiquepos.com');
if (!defined('SYSTEMNAME')) define('SYSTEMNAME', 'TiquePOS');

$serverName = strtolower((string)($_SERVER['SERVER_NAME'] ?? ''));
if (!defined('ENVIRONMENT')) {
    define(
        'ENVIRONMENT',
        ($serverName === 'localhost' || $serverName === '127.0.0.1')
            ? 'development'
            : 'production'
    );
}

try {
    $conn = new PDO(
        'mysql:host=' . HOST
        . ';port=' . PORT
        . ';dbname=' . DB_NAME
        . ';charset=' . CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $conn->exec("SET time_zone = '-05:00'");
} catch (PDOException $e) {
    error_log('[TIQUEPOS][DB] ' . $e->getMessage());

    if (ENVIRONMENT === 'development') {
        die(
            'Error de conexión a la base de datos: '
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        );
    }

    http_response_code(500);
    die('No se pudo conectar con la base de datos.');
}
