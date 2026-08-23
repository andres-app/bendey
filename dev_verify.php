<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function dev_verify_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function dev_verify_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

function dev_verify_normalize(string $path): string
{
    $path = ltrim(str_replace('\\', '/', trim($path)), '/');
    $path = preg_replace('#/+#', '/', $path) ?? '';
    if ($path === '' || strpos($path, "\0") !== false || preg_match('#(^|/)\.\.(/|$)#', $path)) {
        return '';
    }
    return $path;
}

function dev_verify_protected(string $path): bool
{
    $p = dev_verify_normalize($path);
    if ($p === '') { return true; }
    if (preg_match('#(^|/)Config/local\.php$#i', $p)) { return true; }
    if (preg_match('#(^|/)\.env$#i', $p)) { return true; }
    if (preg_match('#(^|/)storage/#i', $p)) { return true; }
    if (preg_match('#(^|/)Assets/img/(company|products|users)/#i', $p)) { return true; }
    if (preg_match('#(^|/)Reports/error_log$#i', $p)) { return true; }
    return false;
}

function dev_verify_is_text_file(string $relative, string $contents): bool
{
    if ($contents === '' || strpos($contents, "\0") !== false) {
        return $contents === '';
    }
    $name = strtolower(basename($relative));
    if (in_array($name, ['version', '.htaccess', '.gitignore', 'composer.lock'], true)) {
        return true;
    }
    $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
    return in_array($ext, [
        'php','phtml','inc','js','mjs','cjs','css','scss','less','html','htm',
        'json','xml','sql','md','txt','csv','ini','conf','config','yaml','yml',
        'toml','lock','map','svg','webmanifest','sh','bat','cmd','ps1'
    ], true);
}

function dev_verify_hash_file_normalized(string $path, string $relative): string|false
{
    $contents = @file_get_contents($path);
    if (!is_string($contents)) {
        return false;
    }
    if (dev_verify_is_text_file($relative, $contents)) {
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
    }
    return hash('sha256', $contents);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    dev_verify_json(['success' => false, 'message' => 'Método no permitido.'], 405);
}

$local = __DIR__ . '/Config/local.php';
if (!is_file($local)) {
    dev_verify_json(['success' => false, 'message' => 'La instalación no tiene Config/local.php.'], 503);
}
require_once $local;

$host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
$host = preg_replace('/:\d+$/', '', $host) ?? $host;
$expectedHost = defined('APP_DOMAIN') ? strtolower(trim((string)APP_DOMAIN)) : '';
if ($expectedHost === '' || !hash_equals($expectedHost, $host)) {
    dev_verify_json(['success' => false, 'message' => 'Hostname DEVELOPMENT no autorizado.'], 403);
}

if (!defined('CONTROL_ENABLED') || !CONTROL_ENABLED || !defined('CONTROL_CLIENT_KEY') || !defined('CONTROL_CLIENT_SECRET')) {
    dev_verify_json(['success' => false, 'message' => 'TiquePOS Control no está habilitado en esta instalación.'], 503);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength <= 0 || $contentLength > 4_000_000) {
    dev_verify_json(['success' => false, 'message' => 'Solicitud de verificación demasiado grande o vacía.'], 413);
}
$raw = file_get_contents('php://input');
if (!is_string($raw) || $raw === '') {
    dev_verify_json(['success' => false, 'message' => 'Solicitud vacía.'], 400);
}

$clientKey = dev_verify_header('X-TiquePOS-Client');
$timestamp = dev_verify_header('X-TiquePOS-Timestamp');
$nonce = strtolower(dev_verify_header('X-TiquePOS-Nonce'));
$signature = strtolower(dev_verify_header('X-TiquePOS-Signature'));
if ($clientKey === '' || $timestamp === '' || $nonce === '' || $signature === '') {
    dev_verify_json(['success' => false, 'message' => 'Autenticación incompleta.'], 401);
}
if (!hash_equals((string)CONTROL_CLIENT_KEY, $clientKey)) {
    dev_verify_json(['success' => false, 'message' => 'Cliente no autorizado.'], 401);
}
if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > 300) {
    dev_verify_json(['success' => false, 'message' => 'Solicitud fuera de ventana de tiempo.'], 401);
}
if (!preg_match('/^[a-f0-9]{32}$/', $nonce) || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
    dev_verify_json(['success' => false, 'message' => 'Firma o nonce inválido.'], 401);
}
$path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/dev_verify.php'), PHP_URL_PATH) ?: '/dev_verify.php');
$canonical = "POST\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $raw);
$expectedSignature = hash_hmac('sha256', $canonical, (string)CONTROL_CLIENT_SECRET);
if (!hash_equals($expectedSignature, $signature)) {
    dev_verify_json(['success' => false, 'message' => 'Firma de solicitud inválida.'], 401);
}

$nonceDir = __DIR__ . '/storage/control/dev_verify_nonces';
if (!is_dir($nonceDir) && !mkdir($nonceDir, 0775, true) && !is_dir($nonceDir)) {
    dev_verify_json(['success' => false, 'message' => 'No se pudo preparar protección anti-replay.'], 500);
}
foreach (glob($nonceDir . '/*') ?: [] as $old) {
    if (is_file($old) && filemtime($old) !== false && filemtime($old) < time() - 600) { @unlink($old); }
}
$nonceFile = $nonceDir . '/' . hash('sha256', $nonce);
$nonceFp = @fopen($nonceFile, 'x');
if (!$nonceFp) {
    dev_verify_json(['success' => false, 'message' => 'Solicitud repetida.'], 409);
}
fwrite($nonceFp, (string)time());
fclose($nonceFp);

$data = json_decode($raw, true);
if (!is_array($data) || empty($data['files']) || !is_array($data['files'])) {
    dev_verify_json(['success' => false, 'message' => 'Manifiesto de GitHub inválido.'], 400);
}
$commit = strtolower(trim((string)($data['commit'] ?? '')));
if (!preg_match('/^[a-f0-9]{40}$/', $commit)) {
    dev_verify_json(['success' => false, 'message' => 'Commit inválido.'], 400);
}
if (count($data['files']) > 5000) {
    dev_verify_json(['success' => false, 'message' => 'Demasiados archivos para verificar.'], 400);
}

$root = realpath(__DIR__);
if ($root === false) {
    dev_verify_json(['success' => false, 'message' => 'No se pudo resolver la raíz de TiquePOS.'], 500);
}
$matched = 0;
$missing = [];
$different = [];
$invalid = [];
$missingCount = 0;
$differentCount = 0;
$invalidCount = 0;
$total = 0;
foreach ($data['files'] as $item) {
    if (!is_array($item)) { continue; }
    $relative = dev_verify_normalize((string)($item['path'] ?? ''));
    $expectedHash = strtolower(trim((string)($item['sha256'] ?? '')));
    if ($relative === '' || !preg_match('/^[a-f0-9]{64}$/', $expectedHash) || dev_verify_protected($relative)) {
        $invalidCount++;
        if (count($invalid) < 100) { $invalid[] = $relative !== '' ? $relative : '(ruta inválida)'; }
        continue;
    }
    $total++;
    $target = $root . '/' . $relative;
    if (!is_file($target)) {
        $missingCount++;
        if (count($missing) < 100) { $missing[] = $relative; }
        continue;
    }
    $actualHash = dev_verify_hash_file_normalized($target, $relative);
    if (!is_string($actualHash) || !hash_equals($expectedHash, strtolower($actualHash))) {
        $differentCount++;
        if (count($different) < 100) { $different[] = $relative; }
        continue;
    }
    $matched++;
}

$match = $total > 0 && $missingCount === 0 && $differentCount === 0 && $invalidCount === 0 && $matched === $total;
dev_verify_json([
    'success' => true,
    'match' => $match,
    'commit' => $commit,
    'domain' => defined('CONTROL_DOMAIN') ? (string)CONTROL_DOMAIN : (string)($_SERVER['HTTP_HOST'] ?? ''),
    'total' => $total,
    'matched' => $matched,
    'missing_count' => $missingCount,
    'different_count' => $differentCount,
    'invalid_count' => $invalidCount,
    'missing' => $missing,
    'different' => $different,
    'invalid' => $invalid,
    'checked_at' => date(DATE_ATOM),
]);
