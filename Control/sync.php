<?php

declare(strict_types=1);

define('TIQUEPOS_CONTROL_BYPASS', true);
require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/Agent.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $token = (string)($_GET['token'] ?? '');
    if (!defined('CONTROL_CRON_TOKEN') || CONTROL_CRON_TOKEN === '' || !hash_equals((string)CONTROL_CRON_TOKEN, $token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success'=>false,'message'=>'Token de cron inválido.'),JSON_UNESCAPED_UNICODE);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');
try {
    $agent = new TiquePOSControlAgent();
    echo json_encode($agent->sync(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array('success'=>false,'message'=>$e->getMessage()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
