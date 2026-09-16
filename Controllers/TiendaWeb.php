<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../Models/TiendaWeb.php';

function tiendaResponder(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['nombre'])) {
    tiendaResponder(['success' => false, 'mensaje' => 'Acceso no autorizado.'], 403);
}

$puedeConfigurar = (int)($_SESSION['settings'] ?? 0) === 1;
$puedeInventario = (int)($_SESSION['almacen'] ?? 0) === 1;
if (!$puedeConfigurar && !$puedeInventario) {
    tiendaResponder(['success' => false, 'mensaje' => 'No tiene permisos para administrar la página web.'], 403);
}

$op = trim((string)($_GET['op'] ?? ''));
$model = new TiendaWeb();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $csrfSesion = (string)($_SESSION['tienda_web_csrf'] ?? '');
        $csrf = (string)($_POST['csrf'] ?? '');
        if ($csrfSesion === '' || $csrf === '' || !hash_equals($csrfSesion, $csrf)) {
            tiendaResponder(['success' => false, 'mensaje' => 'La sesión del formulario venció. Recargue la página.'], 419);
        }
    }

    switch ($op) {
        case 'guardar_configuracion':
            if (!$puedeConfigurar) {
                tiendaResponder(['success' => false, 'mensaje' => 'No tiene permiso para modificar la configuración.'], 403);
            }
            $config = $model->guardarConfiguracion($_POST);
            tiendaResponder(['success' => true, 'mensaje' => 'Página web actualizada.', 'config' => $config]);
            break;

        case 'guardar_producto':
            if (!$puedeInventario && !$puedeConfigurar) {
                tiendaResponder(['success' => false, 'mensaje' => 'No tiene permiso para modificar productos.'], 403);
            }
            $model->guardarEstadoProducto(
                (int)($_POST['idarticulo'] ?? 0),
                (int)($_POST['publicado'] ?? 0) === 1,
                (int)($_POST['destacado'] ?? 0) === 1
            );
            tiendaResponder(['success' => true]);
            break;

        case 'publicar_todos':
            $model->establecerPublicacionTodos((int)($_POST['publicado'] ?? 0) === 1);
            tiendaResponder(['success' => true]);
            break;

        case 'listar_productos':
            tiendaResponder(['success' => true, 'data' => $model->listarProductosAdmin()]);
            break;

        default:
            tiendaResponder(['success' => false, 'mensaje' => 'Operación no válida.'], 400);
    }
} catch (InvalidArgumentException $e) {
    tiendaResponder(['success' => false, 'mensaje' => $e->getMessage()], 422);
} catch (Throwable $e) {
    $mensaje = (defined('ENVIRONMENT') && ENVIRONMENT === 'development')
        ? $e->getMessage()
        : 'No se pudo completar la operación.';
    tiendaResponder(['success' => false, 'mensaje' => $mensaje], 500);
}
