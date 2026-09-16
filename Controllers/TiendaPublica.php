<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/TiendaWeb.php';
require_once __DIR__ . '/../Libraries/MediaStorage.php';

function responderPublico(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=30, stale-while-revalidate=60');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$op = trim((string)($_GET['op'] ?? 'productos'));
$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    responderPublico(['success' => false, 'mensaje' => 'Catálogo no válido.'], 422);
}

$model = new TiendaWeb(false);

try {
    switch ($op) {
        case 'productos':
            $pagina = $model->listarProductosPublicosPaginado(
                $slug,
                (int)($_GET['limit'] ?? 24),
                (int)($_GET['offset'] ?? 0),
                (string)($_GET['buscar'] ?? ''),
                (string)($_GET['categoria'] ?? ''),
                false
            );

            if (!is_array($pagina['config'] ?? null)) {
                responderPublico(['success' => false, 'mensaje' => 'Catálogo no disponible.'], 404);
            }

            $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/Controllers/TiendaPublica.php'));
            $base = rtrim(dirname(dirname($scriptName)), '/');
            if ($base === '/' || $base === '.') {
                $base = '';
            }

            foreach ($pagina['data'] as &$producto) {
                $raw = trim((string)($producto['imagen'] ?? ''));
                $url = $raw !== '' ? tiquepos_media_url('products', $raw) : '';
                $producto['imagen_url'] = $url !== '' ? $base . '/' . ltrim($url, '/') : '';
            }
            unset($producto);
            unset($pagina['config']);

            responderPublico(['success' => true] + $pagina);
            break;

        default:
            responderPublico(['success' => false, 'mensaje' => 'Operación no válida.'], 400);
    }
} catch (Throwable $e) {
    $mensaje = (defined('ENVIRONMENT') && ENVIRONMENT === 'development')
        ? $e->getMessage()
        : 'No se pudo cargar el catálogo.';
    responderPublico(['success' => false, 'mensaje' => $mensaje], 500);
}
