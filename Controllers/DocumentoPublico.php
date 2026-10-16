<?php

declare(strict_types=1);

require_once __DIR__ . '/../Libraries/DocumentDeliveryService.php';

$tipo = trim((string)($_GET['tipo'] ?? ''));
$id = (int)($_GET['id'] ?? 0);
$expira = (int)($_GET['exp'] ?? 0);
$firma = trim((string)($_GET['sig'] ?? ''));

if (!DocumentDeliveryService::validarFirma($tipo, $id, $expira, $firma)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'El enlace del documento no es válido o ha vencido.';
    exit;
}

try {
    $service = new DocumentDeliveryService();
    $doc = $service->obtenerDocumento($tipo, $id);
    $pdfOficial = $service->obtenerPdfOficial($tipo, $id);

    // Si el proveedor electrónico ya dispone de PDF HTTPS, evitar regenerarlo.
    if ($pdfOficial !== '') {
        header('Location: ' . $pdfOficial, true, 302);
        exit;
    }

    $teniaSesion = session_status() === PHP_SESSION_ACTIVE || !empty($_COOKIE[session_name()]);
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Un visitante externo no necesita recibir una cookie de sesión solo por abrir el PDF.
        if (!$teniaSesion) {
            @ini_set('session.use_cookies', '0');
        }
        session_start();
    }
    $sesionOriginal = $_SESSION;

    // Los reportes existentes consultan permisos desde $_SESSION. Se inyecta un
    // contexto temporal y se restaura/destruye al terminar, incluso si el PDF
    // finaliza con exit(), para que un enlace firmado nunca otorgue una sesión.
    register_shutdown_function(static function () use ($teniaSesion, $sesionOriginal): void {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $_SESSION = $sesionOriginal;
            if ($teniaSesion) {
                @session_write_close();
            } else {
                $_SESSION = [];
                @session_destroy();
            }
        } catch (Throwable $e) {
            error_log('[DOCUMENTO PUBLICO SESION] ' . $e->getMessage());
        }
    });

    // Sesión efímera únicamente para reutilizar los reportes PDF existentes.
    $_SESSION['idusuario'] = (int)($doc['idusuario'] ?? 0);
    $_SESSION['nombre'] = 'Documento público';
    $_SESSION['ventas'] = 1;
    $_SESSION['guias_remision'] = 1;

    $_GET['id'] = $id;
    $_GET['idventa'] = $id;
    $_GET['idguia'] = $id;
    $_GET['idnota_credito'] = $id;

    header('Cache-Control: private, max-age=300');
    header('X-Robots-Tag: noindex, nofollow, noarchive');

    $normal = strtolower($tipo);
    if (in_array($normal, ['guia', 'guia_remision', 'guía'], true)) {
        require __DIR__ . '/../Reports/guiaremision_a4.php';
        exit;
    }
    if (in_array($normal, ['nota', 'nota_credito'], true)) {
        require __DIR__ . '/../Reports/notacredito_a4.php';
        exit;
    }

    require __DIR__ . '/../Reports/a4.php';
} catch (Throwable $e) {
    error_log('[DOCUMENTO PUBLICO] ' . $e->getMessage());
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo obtener el documento solicitado.';
}
