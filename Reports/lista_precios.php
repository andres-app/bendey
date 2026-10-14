<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['idusuario']) && empty($_SESSION['nombre'])) { http_response_code(401); exit('Sesión expirada'); }
require_once __DIR__ . '/../Config/Conexion.php';
try {
    $pdo=Conexion::conectar();
    $sql="SELECT a.codigo,a.nombre producto,a.precio_compra costo,a.precio_venta precio_normal, l.idvariacion,l.nombre lista,l.precio,ROUND(l.precio-a.precio_compra,2) utilidad,ROUND(l.precio-a.precio_venta,2) diferencia FROM producto_lista_precio l JOIN articulo a ON a.idarticulo=l.idarticulo ORDER BY a.nombre,l.idvariacion,l.nombre";
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) { http_response_code(500); exit('No se pudo cargar el reporte. Comprueba la migración de listas de precios.'); }
if (($_GET['formato']??'')==='csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lista_precios.csv"');
    $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
    fputcsv($out,['Código','Producto','Costo','Venta normal','ID variación','Lista','Precio','Utilidad estimada','Diferencia'], ';');
    foreach($rows as $r) fputcsv($out,array_values($r),';');
    fclose($out);exit;
}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reporte de listas de precios</title><style>body{margin:0;background:#f1f5f9;color:#0f172a;font:14px system-ui,Arial}.wrap{max-width:1200px;margin:32px auto;background:white;padding:30px;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 10px 35px #0f172a0a}h1{margin:0 0 6px;font-size:24px}.muted{color:#64748b}.actions{display:flex;gap:9px;flex-wrap:wrap;margin:23px 0}.btn{border-radius:9px;background:#047857;color:white;padding:10px 15px;text-decoration:none;border:0;cursor:pointer;font-weight:650}table{width:100%;border-collapse:collapse}th{text-align:left;background:#f8fafc}th,td{padding:12px 10px;border-bottom:1px solid #e2e8f0}td.n{text-align:right;font-variant-numeric:tabular-nums}thead{position:sticky;top:0}@media(max-width:700px){.wrap{margin:0;padding:15px;border-radius:0}.overflow{overflow:auto}table{min-width:880px}}@media print{body{background:white}.wrap{box-shadow:none;border:0;margin:0;max-width:none}.actions{display:none}thead{position:static}}</style></head><body><div class="wrap"><h1>Reporte de listas de precios</h1><p class="muted">TiquePOS · Precios adicionales por producto y variación · <?= h(date('d/m/Y H:i')) ?></p><div class="actions"><a class="btn" href="?formato=csv">Exportar CSV (Excel)</a><button class="btn" onclick="print()">Imprimir / Guardar PDF</button></div><div class="overflow"><table><thead><tr><th>Código</th><th>Producto</th><th>Costo</th><th>Venta normal</th><th>Variación</th><th>Lista</th><th>Precio</th><th>Utilidad estimada</th><th>Diferencia</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=h($r['codigo'])?></td><td><?=h($r['producto'])?></td><td class="n"><?=number_format((float)$r['costo'],2)?></td><td class="n"><?=number_format((float)$r['precio_normal'],2)?></td><td><?= $r['idvariacion'] ? h($r['idvariacion']) : 'Base' ?></td><td><?=h($r['lista'])?></td><td class="n"><?=number_format((float)$r['precio'],2)?></td><td class="n"><?=number_format((float)$r['utilidad'],2)?></td><td class="n"><?=number_format((float)$r['diferencia'],2)?></td></tr><?php endforeach; ?></tbody></table></div><p class="muted"><?=count($rows)?> precios registrados. La utilidad estimada es precio menos costo registrado, no utilidad neta.</p></div></body></html>
