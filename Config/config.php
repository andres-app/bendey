<?php

declare(strict_types=1);

date_default_timezone_set('America/Lima');

$localConfig = __DIR__ . '/local.php';
$installLock = __DIR__ . '/../storage/installed.lock';

if (!is_file($localConfig) || !is_file($installLock)) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Location: /install/', true, 302);
        exit;
    }
    throw new RuntimeException('TiquePOS aún no está instalado. Ejecuta /install/ desde el navegador.');
}

require_once $localConfig;

if (!defined('TIQUEPOS_CONTROL_BYPASS')) {
    $maintenanceFlag = __DIR__ . '/../storage/control/maintenance.flag';
    if (is_file($maintenanceFlag)) {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $uri = strtolower((string)($_SERVER['REQUEST_URI'] ?? ''));
        http_response_code(503);
        header('Retry-After: 30');
        if (strpos($accept, 'application/json') !== false || strpos($uri, '/controllers/') !== false) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false,'message'=>'TiquePOS se está actualizando. Intenta nuevamente en unos segundos.'], JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Actualizando TiquePOS</title><body style="margin:0;background:#f4f7f6;font-family:system-ui,-apple-system,Segoe UI,Arial;display:grid;place-items:center;min-height:100vh;color:#173028"><main style="max-width:520px;background:white;border:1px solid #dce6e1;border-radius:18px;padding:28px;text-align:center"><div style="font-size:28px">Tique<span style="color:#00a46a">POS</span></div><h1 style="font-size:20px">Actualización en curso</h1><p style="color:#64736c;line-height:1.5">La aplicación volverá a estar disponible automáticamente al terminar el despliegue.</p></main></body></html>';
        }
        exit;
    }

    if (defined('CONTROL_ENABLED') && CONTROL_ENABLED && defined('CONTROL_ENFORCE_LICENSE') && CONTROL_ENFORCE_LICENSE) {
        require_once __DIR__ . '/../Control/LicenseGuard.php';
        TiquePOSLicenseGuard::enforce();
    }
}

$serverName = (string)($_SERVER['SERVER_NAME'] ?? '');
define('ENVIRONMENT', ($serverName === 'localhost' || $serverName === '127.0.0.1') ? 'development' : 'production');

if (!defined('PORT')) define('PORT', 3306);
if (!defined('CHARSET')) define('CHARSET', 'utf8mb4');
if (!defined('API_KEY')) define('API_KEY', '');
if (!defined('SYSTEMNAME')) define('SYSTEMNAME', 'TiquePOS');

try {
    $conn = new PDO(
        'mysql:host=' . HOST . ';port=' . PORT . ';dbname=' . DB_NAME . ';charset=' . CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $conn->exec("SET time_zone = '-05:00'");
} catch (PDOException $e) {
    if (ENVIRONMENT === 'development') {
        die('Error de conexión a la base de datos: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
    http_response_code(500);
    die('No se pudo conectar con la base de datos.');
}
