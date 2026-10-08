<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Lotes.php';

/** Flujo HTTP del módulo de inventario. No emite HTML (salvo CSV). */
final class LotesController
{
    private Lotes $model;

    public function __construct()
    {
        $this->model = new Lotes();
    }

    public function procesar(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if ((int)($_SESSION['idusuario'] ?? 0) <= 0 || (int)($_SESSION['almacen'] ?? 0) !== 1) {
            http_response_code(403);
            exit('Acceso restringido al personal autorizado de inventario.');
        }
        if (empty($_SESSION['csrf_inventario_lotes'])) $_SESSION['csrf_inventario_lotes'] = bin2hex(random_bytes(24));
        if (empty($_SESSION['nonce_inventario_lotes'])) $_SESSION['nonce_inventario_lotes'] = bin2hex(random_bytes(16));
        $alerta = $_SESSION['flash_lotes'] ?? null;
        unset($_SESSION['flash_lotes']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $faltantes = $this->model->auditoriasPendientes();
                if ($faltantes) {
                    throw new RuntimeException('Faltan las tablas de auditoría de lotes. Instala la migración SQL del paquete antes de modificar el inventario.');
                }
                if (!hash_equals((string)$_SESSION['csrf_inventario_lotes'], (string)($_POST['csrf'] ?? ''))) {
                    throw new RuntimeException('Sesión expirada. Actualiza la pantalla.');
                }
                $accion = (string)($_POST['accion'] ?? '');
                if ($accion === 'identificar') {
                    $nonce = (string)($_POST['nonce'] ?? '');
                    if (!preg_match('/^[a-f0-9]{32}$/D', $nonce) || !hash_equals((string)$_SESSION['nonce_inventario_lotes'], $nonce)) {
                        throw new RuntimeException('Formulario vencido o ya enviado. Actualiza la pantalla.');
                    }
                    $id = filter_var($_POST['iddetalle_ingreso'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id) throw new RuntimeException('Ingreso inválido.');
                    $mensaje = $this->model->guardarIdentificacion((int)$id,
                        trim((string)($_POST['numero_lote'] ?? '')),
                        trim((string)($_POST['fecha_vencimiento'] ?? '')),
                        trim((string)($_POST['motivo'] ?? '')),
                        (int)$_SESSION['idusuario'], $nonce);
                    $_SESSION['nonce_inventario_lotes'] = bin2hex(random_bytes(16));
                } elseif ($accion === 'config') {
                    $id = filter_var($_POST['idarticulo'] ?? null, FILTER_VALIDATE_INT);
                    $dias = filter_var($_POST['dias_alerta'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id || $dias === false) throw new RuntimeException('Datos inválidos para configurar el producto.');
                    $modo = (string)($_POST['modo_control'] ?? '');
                    if (!in_array($modo, ['ninguno', 'lotes', 'vencimiento'], true)) {
                        throw new RuntimeException('Selecciona una modalidad válida para el producto.');
                    }
                    $mensaje = $this->model->configurar((int)$id, $modo !== 'ninguno', $modo === 'vencimiento', (int)$dias);
                } elseif ($accion === 'baja') {
                    $nonce = (string)($_POST['nonce'] ?? '');
                    if (!preg_match('/^[a-f0-9]{32}$/D', $nonce) || !hash_equals((string)$_SESSION['nonce_inventario_lotes'], $nonce)) {
                        throw new RuntimeException('Operación duplicada o formulario vencido. Actualiza el inventario.');
                    }
                    $id = filter_var($_POST['iddetalle_ingreso'] ?? null, FILTER_VALIDATE_INT);
                    $cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT);
                    if (!$id || !$cantidad) throw new RuntimeException('Especifica el lote y la cantidad entera a dar de baja.');
                    $mensaje = $this->model->darDeBaja((int)$id, (int)$cantidad, (string)($_POST['tipo'] ?? ''),
                        trim((string)($_POST['motivo'] ?? '')), (int)$_SESSION['idusuario'], $nonce);
                    $_SESSION['nonce_inventario_lotes'] = bin2hex(random_bytes(16));
                } else {
                    throw new RuntimeException('Acción desconocida.');
                }
                $_SESSION['flash_lotes'] = ['ok', $mensaje];
            } catch (Throwable $e) {
                error_log('[TIQUEPOS LOTES] ' . $e->getMessage());
                $_SESSION['flash_lotes'] = ['error', $e->getMessage()];
            }
            header('Location: ' . lotes_url(), true, 303);
            exit;
        }
        $busqueda = $this->texto($_GET['q'] ?? '', 100);
        $estado = (string)($_GET['estado'] ?? 'todos');
        if (!in_array($estado, ['todos', 'vencidos', 'proximos', 'vigentes', 'sin_fecha'], true)) $estado = 'todos';
        $idAlmacen = filter_var($_GET['almacen'] ?? 0, FILTER_VALIDATE_INT);
        $idAlmacen = $idAlmacen && $idAlmacen > 0 ? (int)$idAlmacen : 0;
        $tab = in_array((string)($_GET['tab'] ?? ''), ['config','pendientes'], true) ? (string)$_GET['tab'] : 'lotes';
        $pagina = max(1, min(999999, (int)($_GET['p'] ?? 1)));
        if (($_GET['export'] ?? '') === 'csv') {
            $this->csv($busqueda, $estado, $idAlmacen);
        }
        $lotes = $this->model->obtenerLotes($busqueda, $estado, $idAlmacen, $pagina);
        $textoProductos = $tab === 'config' ? $busqueda : $this->texto($_GET['qp'] ?? '', 100);
        $productoPagina = max(1, (int)($_GET['pp'] ?? 1));
        $productos = $this->model->productos($textoProductos, $productoPagina);
        $revision = $this->model->porIdentificar($busqueda, max(1,(int)($_GET['pi'] ?? 1)));
        return array_merge($lotes, $productos, $revision, [
            'alerta' => $alerta,
            'busqueda' => $busqueda,
            'estado' => $estado,
            'idAlmacen' => $idAlmacen,
            'tab' => $tab,
            'global' => $this->model->resumen(),
            'almacenes' => $this->model->almacenes(),
            'textoProductos' => $textoProductos,
            'auditoriasPendientes' => $this->model->auditoriasPendientes(),
            'ajustes' => $this->model->ajustesRecientes(),
            'historialLotes' => $this->model->identificacionesRecientes()
        ]);
    }

    private function texto($value, int $max): string
    {
        $value = trim((string)$value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }

    private function csv(string $busqueda, string $estado, int $almacen): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tiquepos_lotes_' . date('Ymd') . '.csv"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID ingreso', 'SKU', 'Producto', 'Presentación', 'Almacén', 'N.º lote', 'Vencimiento', 'Días restantes', 'Stock', 'Costo unitario']);
        foreach ($this->model->exportarLotes($busqueda, $estado, $almacen) as $r) {
            $data = [$r['iddetalle_ingreso'], $r['codigo'], $r['producto'], $r['presentacion'], $r['almacen'], $r['numero_lote'], $r['fecha_vencimiento'], $r['dias_restantes'], $r['stock_venta'], $r['precio_compra']];
            foreach ($data as &$v) if (is_string($v) && preg_match('/^\s*[=+@\-]/u', $v)) $v = "'" . $v;
            unset($v);
            fputcsv($out, $data);
        }
        fclose($out);
        exit;
    }
}

/** URL con parámetros para la navegación del módulo y para el retorno POST. */
function lotes_url(array $extra = []): string
{
    $params = $_GET;
    unset($params['export'], $params['url']);
    $params = array_merge($params, $extra);
    return 'lotes' . ($params ? '?' . http_build_query($params) : '');
}

function lotes_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
