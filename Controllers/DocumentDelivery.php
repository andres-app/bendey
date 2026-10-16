<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../Libraries/DocumentDeliveryService.php';

function deliveryJson(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['nombre']) || (int)($_SESSION['idusuario'] ?? 0) <= 0) {
    deliveryJson(['success' => false, 'message' => 'La sesión no es válida.'], 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    deliveryJson(['success' => false, 'message' => 'La operación requiere POST.'], 405);
}

$csrf = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? ''));
$csrfSesion = trim((string)($_SESSION['document_delivery_csrf'] ?? ''));
if ($csrf === '' || $csrfSesion === '' || !hash_equals($csrfSesion, $csrf)) {
    deliveryJson(['success' => false, 'message' => 'La sesión de seguridad ha caducado. Actualice la página.'], 419);
}

$tipo = trim((string)($_POST['tipo'] ?? ''));
$id = (int)($_POST['id'] ?? 0);
$op = trim((string)($_GET['op'] ?? $_POST['op'] ?? ''));

$tipoPermiso = strtolower($tipo);
if (in_array($tipoPermiso, ['guia', 'guia_remision', 'guía'], true)) {
    if ((int)($_SESSION['guias_remision'] ?? 0) !== 1) {
        deliveryJson(['success' => false, 'message' => 'No tiene permiso para Guías de Remisión.'], 403);
    }
} elseif ((int)($_SESSION['ventas'] ?? 0) !== 1) {
    deliveryJson(['success' => false, 'message' => 'No tiene permiso para Ventas.'], 403);
}

if ($id <= 0) {
    deliveryJson(['success' => false, 'message' => 'El documento no es válido.'], 422);
}

try {
    $service = new DocumentDeliveryService();
    if (!$service->autorizarAccesoUsuario($tipo, $id, (int)$_SESSION['idusuario'])) {
        deliveryJson(['success' => false, 'message' => 'No tiene acceso al documento solicitado.'], 403);
    }

    if ($op === 'email') {
        $email = trim((string)($_POST['email'] ?? ''));
        $data = $service->email($tipo, $id, $email !== '' ? $email : null, false);
        deliveryJson(['success' => true, 'data' => $data, 'message' => (string)($data['mensaje'] ?? 'Correo enviado.')]);
    }

    if ($op === 'whatsapp') {
        $telefono = trim((string)($_POST['telefono'] ?? ''));
        $data = $service->whatsapp($tipo, $id, $telefono !== '' ? $telefono : null);
        deliveryJson(['success' => true, 'data' => $data]);
    }

    if ($op === 'info') {
        $doc = $service->obtenerDocumento($tipo, $id);
        deliveryJson([
            'success' => true,
            'data' => [
                'id' => (int)$doc['id'],
                'email' => (string)($doc['email'] ?? ''),
                'telefono' => (string)($doc['telefono'] ?? ''),
                'pdf_url' => $service->pdfUrl((string)$doc['tipo_interno'], (int)$doc['id']),
            ],
        ]);
    }

    deliveryJson(['success' => false, 'message' => 'Operación no válida.'], 404);
} catch (InvalidArgumentException $e) {
    deliveryJson(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('[DOCUMENT DELIVERY] ' . $e->getMessage());
    deliveryJson(['success' => false, 'message' => $e->getMessage()], 500);
}
