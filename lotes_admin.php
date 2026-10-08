<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if ((int)($_SESSION['idusuario'] ?? 0) <= 0 || (int)($_SESSION['almacen'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión con permisos de almacén.');
}
require_once __DIR__ . '/Config/Conexion.php';
$pdo = Conexion::conectar();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (empty($_SESSION['csrf_lotes_admin'])) $_SESSION['csrf_lotes_admin'] = bin2hex(random_bytes(24));
$aviso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf_lotes_admin'], (string)($_POST['csrf'] ?? ''))) throw new RuntimeException('La sesión ha expirado.');
        $id = filter_var($_POST['idarticulo'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) throw new RuntimeException('Producto inválido.');
        $lotes = isset($_POST['controla_lotes']) ? 1 : 0;
        $vence = isset($_POST['controla_vencimiento']) ? 1 : 0;
        if ($vence) $lotes = 1;
        $dias = filter_var($_POST['dias_alerta'] ?? 30, FILTER_VALIDATE_INT);
        if ($dias === false || $dias < 1 || $dias > 365) throw new RuntimeException('Días de alerta fuera de rango.');
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT idarticulo, nombre, stock, controla_lotes, controla_vencimiento FROM articulo WHERE idarticulo=? FOR UPDATE');
        $stmt->execute([$id]);
        $actual = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$actual) throw new RuntimeException('Producto no encontrado.');
        if ($lotes && !(int)$actual['controla_lotes']) {
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(stock),0) FROM articulo_variacion WHERE idarticulo=? AND estado=1');
            $stmt->execute([$id]);
            $saldoVariantes = (float)$stmt->fetchColumn();
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(stock_venta),0) FROM detalle_ingreso WHERE idarticulo=? AND tipo_detalle=\'INVENTARIO\' AND afecta_stock=1 AND estado=1');
            $stmt->execute([$id]);
            $saldoIngresos = (float)$stmt->fetchColumn();
            if ((float)$actual['stock'] > 0 || $saldoVariantes > 0 || $saldoIngresos > 0) {
                throw new RuntimeException('No se permite activar lotes sobre stock histórico sin identificar. Use un producto nuevo sin stock o realice una conciliación supervisada.');
            }
        }
        if (!$lotes && (int)$actual['controla_lotes']) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM detalle_ingreso WHERE idarticulo=? AND stock_venta>0 AND numero_lote IS NOT NULL');
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) throw new RuntimeException('No se permite desactivar el control con lotes todavía disponibles.');
        }
        $stmt = $pdo->prepare('UPDATE articulo SET controla_lotes=?, controla_vencimiento=?, dias_alerta_vencimiento=? WHERE idarticulo=?');
        $stmt->execute([$lotes, $vence, $dias, $id]);
        $pdo->commit();
        $aviso = 'Configuración guardada para ' . $actual['nombre'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $aviso = 'No se aplicaron cambios: ' . $e->getMessage();
    }
}
$productos = $pdo->query('SELECT a.idarticulo,a.nombre,a.stock,a.controla_lotes,a.controla_vencimiento,a.dias_alerta_vencimiento,(SELECT COUNT(*) FROM articulo_variacion av WHERE av.idarticulo=a.idarticulo AND av.estado=1) AS variantes FROM articulo a ORDER BY a.nombre')->fetchAll(PDO::FETCH_ASSOC);
if (($_GET['export'] ?? '') === 'csv') {
    $export = $pdo->query("SELECT a.nombre AS producto,COALESCE(av.combinacion,'Simple') AS presentacion,di.numero_lote,di.fecha_vencimiento,di.stock_venta,di.precio_compra FROM detalle_ingreso di JOIN articulo a ON a.idarticulo=di.idarticulo LEFT JOIN articulo_variacion av ON av.idvariacion=di.idvariacion WHERE di.numero_lote IS NOT NULL AND di.estado=1 ORDER BY a.nombre,di.fecha_vencimiento")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventario_lotes.csv"');
    echo "\xEF\xBB\xBF";
    $f=fopen('php://output','w');
    fputcsv($f,['Producto','Presentación','Lote','Vencimiento','Saldo','Costo']);
    foreach($export as $r){
        // Evita inyección de fórmulas al abrir CSV en Excel.
        foreach($r as &$v){if(is_string($v)&&preg_match('/^[\s]*[=+@-]/u',$v)) $v="'".$v;}
        unset($v);
        fputcsv($f,array_values($r));
    }
    fclose($f);
    exit;
}
$lotesRows = $pdo->query("SELECT a.nombre AS producto, COALESCE(av.combinacion,'Simple') AS presentacion, di.numero_lote, di.fecha_vencimiento, di.stock_venta, di.precio_compra, DATEDIFF(di.fecha_vencimiento,CURDATE()) AS dias_restantes, a.dias_alerta_vencimiento FROM detalle_ingreso di JOIN articulo a ON a.idarticulo=di.idarticulo LEFT JOIN articulo_variacion av ON av.idvariacion=di.idvariacion WHERE di.numero_lote IS NOT NULL AND di.stock_venta>0 AND di.estado=1 ORDER BY CASE WHEN di.fecha_vencimiento IS NULL THEN 1 ELSE 0 END, di.fecha_vencimiento ASC, a.nombre")->fetchAll(PDO::FETCH_ASSOC);
function esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lotes y vencimientos · TiquePOS</title><style>
:root{font-family:system-ui,-apple-system,sans-serif;color:#172238;background:#f5f7fb}body{margin:0}.wrap{max-width:1250px;margin:32px auto;padding:0 18px}h1{font-size:27px;margin:0 0 5px}p{color:#65738a}section{background:white;border-radius:14px;box-shadow:0 4px 24px #1526420e;margin:20px 0;padding:22px;overflow:auto}table{width:100%;border-collapse:collapse;min-width:850px}th,td{text-align:left;border-bottom:1px solid #edf0f5;padding:11px 9px;font-size:13px}th{color:#5f6d7f;font-size:12px;text-transform:uppercase}input[type=number]{width:70px;padding:7px;border:1px solid #cdd5e3;border-radius:6px}button{background:#2168df;border:0;color:white;border-radius:7px;padding:9px 12px;cursor:pointer}button:hover{background:#1352bf}.pill{display:inline-block;border-radius:12px;padding:3px 9px;font-size:12px;background:#eff4fc;color:#3060b8}.red{color:#b82424}.orange{color:#a85a08}.message{background:#fff8dd;border:1px solid #f2dfa1;padding:12px;border-radius:7px}.meta{display:flex;gap:12px;flex-wrap:wrap}.metric{background:#eaf1ff;border-radius:9px;padding:12px 18px}.metric b{display:block;font-size:23px}.small{font-size:12px;color:#728097}</style></head><body><main class="wrap"><h1>Lotes y vencimientos</h1><p>Control opcional por producto · FEFO automático en ventas · Inventario de productos naturales</p>
<?php if ($aviso !== ''): ?><div class="message"><?=esc($aviso)?></div><?php endif ?>
<section><h2>Configurar productos</h2><p class="small">La activación exige inventario sin existencias previas no identificadas. Los productos antiguos con stock deben conciliarse primero. Guardar no crea ni mueve existencias.</p><table><thead><tr><th>Producto</th><th>Stock</th><th>Variantes</th><th>Lotes</th><th>Vencimiento</th><th>Alerta (días)</th><th></th></tr></thead><tbody>
<?php foreach($productos as $r): ?><tr><td><?=esc($r['nombre'])?></td><td><?=esc($r['stock'])?></td><td><?=esc($r['variantes'])?></td><td colspan="4"><form method="post" style="display:flex;align-items:center;justify-content:space-between;gap:15px"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf_lotes_admin'])?>"><input type="hidden" name="idarticulo" value="<?=esc($r['idarticulo'])?>"><label><input type="checkbox" name="controla_lotes" <?=((int)$r['controla_lotes'])?'checked':''?>> Lotes</label><label><input type="checkbox" name="controla_vencimiento" <?=((int)$r['controla_vencimiento'])?'checked':''?>> Vence</label><input type="number" name="dias_alerta" min="1" max="365" value="<?=esc($r['dias_alerta_vencimiento'])?>"><button type="submit">Guardar</button></form></td></tr><?php endforeach ?></tbody></table></section>
<section><h2>Existencias identificadas por lote</h2><p><a href="?export=csv">Exportar CSV para Excel</a></p><div class="meta"><div class="metric"><b><?=count($lotesRows)?></b>Lotes con stock</div><div class="metric"><b><?=count(array_filter($lotesRows,fn($x)=>$x['dias_restantes']!==null&&(int)$x['dias_restantes']<0))?></b>Vencidos</div><div class="metric"><b><?=count(array_filter($lotesRows,fn($x)=>$x['dias_restantes']!==null&&(int)$x['dias_restantes']>=0&&(int)$x['dias_restantes']<=(int)$x['dias_alerta_vencimiento']))?></b>Próximos a vencer</div></div><table><thead><tr><th>Producto</th><th>Presentación</th><th>Lote</th><th>Vencimiento</th><th>Disponible</th><th>Costo</th><th>Estado</th></tr></thead><tbody><?php foreach($lotesRows as $r): $d=$r['dias_restantes']; $estado=$d===null?'Sin vencimiento':($d<0?'Vencido':($d<=$r['dias_alerta_vencimiento']?'Próximo a vencer':'Vigente')); ?><tr><td><?=esc($r['producto'])?></td><td><?=esc($r['presentacion'])?></td><td><?=esc($r['numero_lote'])?></td><td><?=esc($r['fecha_vencimiento']??'—')?></td><td><?=esc($r['stock_venta'])?></td><td>S/ <?=number_format((float)$r['precio_compra'],2)?></td><td class="<?=($d!==null&&$d<0)?'red':(($d!==null&&$d<=$r['dias_alerta_vencimiento'])?'orange':'')?>"><?=esc($estado)?></td></tr><?php endforeach ?></tbody></table></section>
<p class="small">Esta página solo está disponible con una sesión autorizada de almacén. No importa ni ajusta stock anterior automáticamente.</p></main></body></html>
