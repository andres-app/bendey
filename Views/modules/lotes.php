<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['nombre'])) { header('Location: login'); exit; }
require_once __DIR__ . '/../../Controllers/Lotes.php';
try {
    $datosLotes = (new LotesController())->procesar();
} catch (Throwable $e) {
    error_log('[TIQUEPOS /lotes] ' . get_class($e) . ': ' . $e->getMessage());
    // No ocultar el módulo con un 500 genérico. La pantalla informa qué revisar.
    http_response_code(503);
    $datosLotes = null;
}
if ($datosLotes !== null) extract($datosLotes, EXTR_SKIP);
require 'header.php';
require 'sidebar.php';
if ($datosLotes === null): ?>
<div class="main-content"><section class="section"><div style="max-width:900px;margin:35px auto;padding:28px;border:1px solid #e2e8f0;border-radius:15px;background:#fff;font-family:system-ui,sans-serif">
<h2 style="font-size:24px">Lotes y vencimientos no se pudo cargar</h2>
<p>No se ha podido consultar el inventario. Comprueba que estén importadas las migraciones <code>actualizacion_lotes_20261008.sql</code> y <code>TiquePOS_Migracion_Fecha_Vencimiento_20261008.sql</code>, y que los archivos <code>Controllers/Lotes.php</code> y <code>Models/Lotes.php</code> estén actualizados.</p>
<p>El error técnico se ha registrado en el log PHP del servidor con el prefijo <code>[TIQUEPOS /lotes]</code>. No se modificó inventario.</p>
<a href="dashboard" style="color:#07835a">Regresar al panel</a></div></section></div>
<?php require 'footer.php'; ob_end_flush(); return; endif;
?>
<style>
.tp-lotes{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#182836;background:#f6f8fb}
.tp-lotes *{box-sizing:border-box}
.tp-lotes{margin:0}
.tp-lotes a{color:#087453;text-decoration:none}
.tp-lotes a:hover{text-decoration:underline}
.tp-lotes header{background:#fff;border-bottom:1px solid #e5ebf0;position:sticky;top:0;z-index:5}
.tp-lotes .top{max-width:1440px;margin:auto;display:flex;align-items:center;gap:16px;padding:16px 28px}
.tp-lotes .brand{font-size:20px;font-weight:800;letter-spacing:-.6px;color:#132b2b}
.tp-lotes .brand b{color:#00a46a}
.tp-lotes .back{margin-left:auto;font-size:13px;font-weight:700;color:#57697c}
.tp-lotes .wrap{max-width:1440px;margin:0 auto;padding:28px}
.tp-lotes h1{font-size:30px;letter-spacing:-1px;margin:0 0 5px;font-weight:780}
.tp-lotes h2{font-size:17px;margin:0;letter-spacing:-.3px}
.tp-lotes .muted{color:#667b8c}
.tp-lotes .sub{margin:0 0 23px;font-size:14px}
.tp-lotes .overview{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:20px 0 26px}
.tp-lotes .metric{background:white;border:1px solid #e7ecf0;padding:19px;border-radius:15px;box-shadow:0 5px 18px #192f4010}
.tp-lotes .metric small{font-size:12px;color:#76889a;font-weight:650;display:block}
.tp-lotes .metric b{display:block;margin:8px 0 4px;font-size:29px;letter-spacing:-1px;color:#193446}
.tp-lotes .metric .bad{color:#c93c45}
.tp-lotes .metric .warn{color:#ba7519}
.tp-lotes .panel{background:white;border:1px solid #e7ecf0;border-radius:16px;box-shadow:0 8px 26px #19304709;margin:18px 0;overflow:hidden}
.tp-lotes .panelhead{padding:20px 22px;border-bottom:1px solid #ecf0f3;display:flex;gap:14px;justify-content:space-between;align-items:center;flex-wrap:wrap}
.tp-lotes .panelbody{padding:21px 22px}
.tp-lotes .tabs{display:flex;flex-wrap:wrap;gap:8px;border-bottom:1px solid #e0e8ec;margin:0 0 0}
.tp-lotes .tabs a{padding:14px 20px;display:block;font-weight:700;font-size:13px;color:#708091;border-bottom:3px solid transparent}
.tp-lotes .tabs a.active{color:#009c6b;border-bottom-color:#00a46a}
.tp-lotes .filters{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
.tp-lotes .field{display:flex;flex-direction:column;gap:6px;min-width:135px;font-size:12px;font-weight:700;color:#677b8c}
.tp-lotes .field.search{flex:1;min-width:210px}
.tp-lotes input,.tp-lotes select,.tp-lotes textarea{font:inherit;font-size:13px;background:#fff;border:1px solid #dce4ec;border-radius:9px;padding:10px 11px;min-width:0;color:#213a4b}
.tp-lotes input:focus,.tp-lotes select:focus,.tp-lotes textarea:focus{outline:2px solid #b1e8d6;border-color:#00a46a}
.tp-lotes .btn{display:inline-flex;justify-content:center;align-items:center;gap:5px;border:none;background:#00a46a;border-radius:9px;color:white;padding:11px 15px;font-size:13px;font-weight:750;cursor:pointer;white-space:nowrap;text-decoration:none}
.tp-lotes .btn:hover{background:#008758;color:white;text-decoration:none}
.tp-lotes .btn.secondary{background:#f2f7f7;color:#087c5c}
.tp-lotes .btn.secondary:hover{background:#e5f3ee}
.tp-lotes .btn.danger{background:#fff0ee;color:#ae3845}
.tp-lotes .btn.danger:hover{background:#fbdcdd}
.tp-lotes .btn.sm{padding:8px 10px;font-size:12px}
.tp-lotes .scroll{overflow-x:auto}
.tp-lotes table{border-collapse:collapse;width:100%;min-width:950px}
.tp-lotes th{padding:13px 18px;text-align:left;font-size:10px;letter-spacing:.6px;text-transform:uppercase;color:#8594a4;font-weight:800;background:#fafbfc}
.tp-lotes td{padding:15px 18px;border-top:1px solid #f0f3f5;font-size:13px;vertical-align:middle}
.tp-lotes tbody tr:hover{background:#fcfefd}
.tp-lotes .secondary{font-size:12px;color:#718398;margin-top:5px}
.tp-lotes .code{font-family:ui-monospace,SFMono-Regular,monospace;font-weight:600;font-size:12px;color:#3f5568}
.tp-lotes .pill{display:inline-flex;align-items:center;border-radius:20px;padding:5px 10px;background:#eef6f4;color:#0b7956;font-size:11px;font-weight:750}
.tp-lotes .pill.expired{background:#ffeded;color:#c73b41}
.tp-lotes .pill.soon{background:#fff5e3;color:#9e5b04}
.tp-lotes .pill.undated{background:#edf1f6;color:#52657a}
.tp-lotes .empty{padding:50px 16px;text-align:center;color:#6b7d8d}
.tp-lotes .pager{display:flex;justify-content:space-between;align-items:center;padding:16px 21px;gap:12px;flex-wrap:wrap;border-top:1px solid #f1f3f6;font-size:12px;color:#7b8c9d}
.tp-lotes .pager a{font-weight:750;padding:8px 12px;border:1px solid #dbe6e6;border-radius:8px;color:#087c5c}
.tp-lotes .pager a.disabled{opacity:.4;pointer-events:none}
.tp-lotes .check{display:flex;gap:7px;align-items:center;white-space:nowrap}
.tp-lotes .check input{width:16px;height:16px;accent-color:#009f6a}
.tp-lotes .inlineform{display:flex;align-items:center;gap:13px;flex-wrap:wrap}
.tp-lotes .inlineform input[type=number]{width:82px}
.tp-lotes .flash{border-radius:11px;padding:15px 19px;margin-bottom:18px;font-size:13px;font-weight:650}
.tp-lotes .flash.ok{background:#ecfaf3;border:1px solid #b7e6d1;color:#07744e}
.tp-lotes .flash.error{background:#fff2f0;border:1px solid #f2b9b6;color:#aa3b35}
.tp-lotes .note{background:#f3faf7;border:1px solid #dbeee5;border-radius:10px;padding:13px 15px;color:#476b5f;font-size:12px;line-height:1.6;margin-bottom:17px}
.tp-lotes .over{color:#ae3845}
.tp-lotes dialog{border:1px solid #e1e7ec;border-radius:16px;padding:0;width:min(94vw,480px);box-shadow:0 20px 100px #0b273044}
.tp-lotes dialog::backdrop{background:#0b1c2ca0}
.tp-lotes .modalhead{padding:22px 24px 14px;font-size:20px;font-weight:760}
.tp-lotes .modalbody{padding:4px 24px 23px;display:grid;gap:13px}
.tp-lotes .modalfooter{padding:16px 24px;border-top:1px solid #e8ecee;display:flex;gap:10px;justify-content:flex-end}
.tp-lotes .status{display:flex;gap:12px;flex-wrap:wrap;font-size:12px}
.tp-lotes .status .dot{color:#0a9f6b}
@media (max-width:900px){.tp-lotes .overview{grid-template-columns:repeat(2,minmax(0,1fr))}
.tp-lotes .wrap{padding:18px 12px}
.tp-lotes .top{padding:14px 16px}
.tp-lotes .panelhead{padding:17px}
.tp-lotes .filters>*{flex:1}
.tp-lotes h1{font-size:25px}}
@media (max-width:500px){.tp-lotes .overview{gap:9px}
.tp-lotes .metric{padding:14px}
.tp-lotes .metric b{font-size:23px}
.tp-lotes .tabs a{padding:12px 13px}
.tp-lotes .inlineform{gap:9px}}
/* Mantener los estilos exclusivos del contenido: sin afectar header/sidebar. */
.tp-lotes { color:#182836; font-family:Inter,system-ui,-apple-system,'Segoe UI',sans-serif; background:#f6f8fb; border-radius:14px; }
.tp-lotes .wrap { max-width:1440px; width:100%; }
.tp-lotes .btn { box-shadow:none; }
.tp-lotes .modalbody { text-align:left; }
</style>
<div class="main-content">
  <section class="section">
    <div class="tp-lotes">

<main class="wrap"><div><h1>Lotes y vencimientos</h1><p class="sub muted">Control opcional, despacho FEFO y trazabilidad completa desde la compra hasta la venta.</p></div>
<?php if ($alerta): ?><div class="flash <?=lotes_h($alerta[0])?>" role="alert"><?=lotes_h($alerta[1])?></div><?php endif; ?>
<?php if (!empty($auditoriasPendientes)): ?>
<div class="flash error" role="alert"><strong>Migración SQL pendiente.</strong> Faltan las tablas de auditoría <?=lotes_h(implode(', ', $auditoriasPendientes))?>. Puedes consultar tus lotes, pero para registrar bajas o corregir fechas debes importar <code>00_MIGRACION_INSTALAR_PRIMERO.sql</code> del paquete. No se han modificado existencias.</div>
<?php endif; ?>
<div class="overview">
<div class="metric"><small>Lotes con existencias</small><b><?=number_format((int)$global['total'])?></b><span class="secondary">Identificados individualmente</span></div>
<div class="metric"><small>Lotes vencidos</small><b class="bad"><?=number_format((int)$global['vencidos'])?></b><span class="secondary">Bloqueados para ventas</span></div>
<div class="metric"><small>Próximos a vencer</small><b class="warn"><?=number_format((int)$global['proximos'])?></b><span class="secondary">Según alertas por producto</span></div>
<div class="metric"><small>Unidades vencidas</small><b><?=number_format((int)$global['unidades_vencidas'])?></b><span class="secondary">Siguen en existencia física</span></div>
</div>
<div class="panel"><nav class="tabs" aria-label="Secciones"><a class="<?= $tab==='lotes'?'active':'' ?>" href="<?=lotes_h(lotes_url(['tab'=>'lotes','p'=>1]))?>">Existencias por lote</a><a class="<?= $tab==='pendientes'?'active':'' ?>" href="<?=lotes_h(lotes_url(['tab'=>'pendientes','pi'=>1]))?>">Por identificar</a><a class="<?= $tab==='config'?'active':'' ?>" href="<?=lotes_h(lotes_url(['tab'=>'config','p'=>1,'pp'=>1]))?>">Configurar productos</a></nav>
<?php if ($tab === 'lotes'): ?>
<div class="panelhead"><div><h2>Inventario por lote</h2><p class="secondary" style="margin-bottom:0">Consulta y corrige la fecha de cada lote. Para existencias sin número de lote, usa «Por identificar».</p></div><a class="btn secondary" href="<?=lotes_h(lotes_url(['export'=>'csv']))?>">↓ Exportar CSV</a></div>
<div class="panelbody"><form class="filters" method="get" action="lotes"><input type="hidden" name="tab" value="lotes"><label class="field search">Buscar lote / producto / SKU<input type="search" name="q" maxlength="100" value="<?=lotes_h($busqueda)?>" placeholder="Ej.: magnesio, L-2026-001"></label><label class="field">Estado<select name="estado"><option value="todos">Todos</option><?php foreach(['vencidos'=>'Vencidos','proximos'=>'Próximos a vencer','vigentes'=>'Vigentes','sin_fecha'=>'Sin fecha'] as $k=>$n): ?><option value="<?=lotes_h($k)?>" <?=$estado===$k?'selected':''?>><?=lotes_h($n)?></option><?php endforeach ?></select></label><label class="field">Almacén<select name="almacen"><option value="0">Todos</option><?php foreach($almacenes as $al): ?><option value="<?=lotes_h($al['idalmacen'])?>" <?=$idAlmacen===(int)$al['idalmacen']?'selected':''?>><?=lotes_h($al['nombre'])?></option><?php endforeach ?></select></label><button class="btn" type="submit">Aplicar filtros</button><a class="btn secondary" href="lotes">Limpiar</a></form></div>
<div class="scroll"><table><thead><tr><th>Producto / presentación</th><th>Lote</th><th>Almacén</th><th>Vencimiento</th><th>Saldo físico</th><th>Costo unit.</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($rows as $r): $dias=$r['dias_restantes']; $est=$dias===null?'Sin vencimiento':((int)$dias<0?'Vencido':((int)$dias<=(int)$r['dias_alerta_vencimiento']?'Próximo a vencer':'Vigente')); $cl=$dias===null?'undated':((int)$dias<0?'expired':((int)$dias<=(int)$r['dias_alerta_vencimiento']?'soon':'')); ?>
<tr><td><b><?=lotes_h($r['producto'])?></b><div class="secondary"><?=lotes_h($r['presentacion'])?> · <?=lotes_h($r['codigo'] ?: 'Sin SKU')?></div></td><td><span class="code"><?=lotes_h($r['numero_lote'])?></span><div class="secondary">Ingreso #<?=lotes_h($r['iddetalle_ingreso'])?></div></td><td><?=lotes_h($r['almacen'] ?: '—')?></td><td><?=lotes_h($r['fecha_vencimiento'] ? date('d/m/Y',strtotime($r['fecha_vencimiento'])) : 'No aplica')?><div class="secondary"><?= $dias===null?'Sin fecha':($dias<0?'Hace '.abs((int)$dias).' días':((int)$dias===0?'Vence hoy':'En '.(int)$dias.' días'))?></div></td><td><b><?=number_format((float)$r['stock_venta'],0)?></b> und.</td><td>S/ <?=number_format((float)$r['precio_compra'],2)?></td><td><span class="pill <?=lotes_h($cl)?>"><?=lotes_h($est)?></span></td><td><button class="btn secondary sm btnIdentificar" type="button" data-id="<?=lotes_h($r['iddetalle_ingreso'])?>" data-nombre="<?=lotes_h($r['producto'])?>" data-lote="<?=lotes_h($r['numero_lote'])?>" data-fecha="<?=lotes_h($r['fecha_vencimiento'])?>" data-max="<?=lotes_h($r['stock_venta'])?>">Editar fecha</button> <button class="btn danger sm btnBaja" type="button" data-id="<?=lotes_h($r['iddetalle_ingreso'])?>" data-nombre="<?=lotes_h($r['producto'])?>" data-lote="<?=lotes_h($r['numero_lote'])?>" data-max="<?=lotes_h($r['stock_venta'])?>">Registrar baja</button></td></tr>
<?php endforeach ?><?php if (!$rows): ?><tr><td class="empty" colspan="8">No hay lotes que coincidan con los filtros.</td></tr><?php endif; ?>
</tbody></table></div><div class="pager"><span>Mostrando <?= $count ? $desde+1 : 0 ?>–<?=min($desde+$limite,$count)?> de <?=number_format($count)?> lotes</span><div class="status"><a class="<?=$pagina<=1?'disabled':''?>" href="<?=lotes_h(lotes_url(['p'=>$pagina-1]))?>">Anterior</a><span>Página <?=$pagina?> / <?=$paginas?></span><a class="<?=$pagina >= $paginas?'disabled':''?>" href="<?=lotes_h(lotes_url(['p'=>$pagina+1]))?>">Siguiente</a></div></div>
<?php elseif ($tab === 'pendientes'): ?>
<div class="panelhead"><div><h2>Existencias anteriores sin lote</h2><p class="secondary" style="margin:0">Asigna número de lote y vencimiento al saldo completo de cada ingreso existente, sin aumentar el stock.</p></div></div>
<div class="panelbody"><div class="note">Verifica físicamente la etiqueta del lote. Cada fila corresponde al <b>saldo restante de un ingreso</b>, no necesariamente a todo el producto. Si hay existencias en distintos lotes dentro de un mismo ingreso, necesitarás una conciliación más detallada: no atribuyas una fecha a unidades que no hayas comprobado. Al completar todos los ingresos podrás activar el control desde «Configurar productos».</div>
<form class="filters" method="get" action="lotes"><input type="hidden" name="tab" value="pendientes"><label class="field search">Buscar producto o SKU<input type="search" name="q" value="<?=lotes_h($busqueda)?>" placeholder="Producto, SKU o variante"></label><button class="btn">Buscar</button></form></div>
<div class="scroll"><table><thead><tr><th>Producto / presentación</th><th>Ingreso</th><th>Almacén</th><th>Saldo pendiente</th><th>Acción</th></tr></thead><tbody>
<?php foreach ($pendientes as $r): ?><tr><td><b><?=lotes_h($r['producto'])?></b><div class="secondary"><?=lotes_h($r['presentacion'])?> · <?=lotes_h($r['codigo'] ?: 'Sin SKU')?></div></td><td>#<?=lotes_h($r['idingreso'])?> · Detalle #<?=lotes_h($r['iddetalle_ingreso'])?></td><td><?=lotes_h($r['almacen'] ?: '—')?></td><td><b><?=lotes_h($r['stock_venta'])?></b> und.</td><td><button class="btn sm btnIdentificar" type="button" data-id="<?=lotes_h($r['iddetalle_ingreso'])?>" data-nombre="<?=lotes_h($r['producto'])?>" data-lote="" data-fecha="<?=lotes_h($r['fecha_vencimiento'])?>" data-max="<?=lotes_h($r['stock_venta'])?>">Asignar lote y fecha</button></td></tr><?php endforeach; ?>
<?php if (!$pendientes): ?><tr><td colspan="5" class="empty">No hay ingresos con saldo pendiente de identificación que coincidan con la búsqueda.</td></tr><?php endif ?></tbody></table></div>
<div class="pager"><span><?=number_format($n)?> ingreso(s) pendientes</span><div class="status"><a class="<?=$paginaPendientes<=1?'disabled':''?>" href="<?=lotes_h(lotes_url(['pi'=>$paginaPendientes-1]))?>">Anterior</a><span>Página <?=$paginaPendientes?> / <?=$paginasPendientes?></span><a class="<?=$paginaPendientes>=$paginasPendientes?'disabled':''?>" href="<?=lotes_h(lotes_url(['pi'=>$paginaPendientes+1]))?>">Siguiente</a></div></div>
<?php else: ?>
<div class="panelhead"><div><h2>Configurar el control por producto</h2><p class="secondary" style="margin-bottom:0">Aquí defines el modo de control, NO una fecha global. Cada ingreso y cada variante tienen sus propios lotes.</p></div></div>
<div class="panelbody"><div class="note">Para activar el control en un producto antiguo, primero debes identificar o conciliar las existencias anteriores. Identifica los saldos de ingreso desde «Por identificar» antes de activar el control. Solo se permite la activación cuando coinciden con el stock actual; no se inventan lotes. Desactivar exige agotar los lotes activos.</div><form class="filters" action="lotes" method="get"><input type="hidden" name="tab" value="config"><label class="field search">Buscar producto<input type="search" name="q" value="<?=lotes_h($textoProductos)?>" placeholder="Nombre o SKU"></label><button class="btn">Buscar</button></form></div>
<div class="scroll"><table><thead><tr><th>Producto</th><th>Stock total</th><th>Control de lotes / vencimiento</th></tr></thead><tbody>
<?php foreach($productos as $p): ?><tr><td><b><?=lotes_h($p['nombre'])?></b><div class="secondary"><?=lotes_h($p['codigo']?:'Sin SKU')?></div></td><td><b><?=lotes_h($p['stock'])?></b><div class="secondary">Variantes: <?=lotes_h($p['stock_variaciones'])?></div></td><td><form class="inlineform" method="post"><input type="hidden" name="csrf" value="<?=lotes_h($_SESSION['csrf_inventario_lotes'])?>"><input type="hidden" name="accion" value="config"><input type="hidden" name="idarticulo" value="<?=lotes_h($p['idarticulo'])?>"><label class="field">Modalidad<select name="modo_control" required><option value="ninguno" <?=(!(int)$p['controla_lotes'] && !(int)$p['controla_vencimiento'])?'selected':''?>>Sin control</option><option value="lotes" <?=((int)$p['controla_lotes'] && !(int)$p['controla_vencimiento'])?'selected':''?>>Solo lotes</option><option value="vencimiento" <?=((int)$p['controla_vencimiento'])?'selected':''?>>Lotes + vencimiento (FEFO)</option></select></label><label class="field">Avisar (días)<input name="dias_alerta" type="number" min="1" max="3650" value="<?=lotes_h($p['dias_alerta_vencimiento'])?>"></label><button class="btn sm">Guardar</button></form></td></tr><?php endforeach ?><?php if (!$productos): ?><tr><td class="empty" colspan="3">No se encontraron productos.</td></tr><?php endif ?></tbody></table></div><div class="pager"><span><?=number_format($totalProductos)?> productos</span><div class="status"><a class="<?=$paginaProductos<=1?'disabled':''?>" href="<?=lotes_h(lotes_url(['pp'=>$paginaProductos-1]))?>">Anterior</a><span>Página <?=$paginaProductos?> / <?=$totalPaginasProductos?></span><a class="<?=$paginaProductos>=$totalPaginasProductos?'disabled':''?>" href="<?=lotes_h(lotes_url(['pp'=>$paginaProductos+1]))?>">Siguiente</a></div></div>
<?php endif; ?></div>
<div class="panel"><div class="panelhead"><h2>Últimas bajas de inventario</h2><span class="secondary">Registro de auditoría · los movimientos no se eliminan</span></div><div class="scroll"><table><thead><tr><th>Fecha</th><th>Producto / lote</th><th>Motivo de baja</th><th>Cantidad</th><th>Stock final</th><th>Responsable</th></tr></thead><tbody><?php foreach($ajustes as $a): ?><tr><td><?=lotes_h(date('d/m/Y H:i',strtotime($a['created_at'])))?></td><td><?=lotes_h($a['producto'])?><div class="secondary"><?=lotes_h($a['numero_lote'])?> #<?=lotes_h($a['iddetalle_ingreso'])?></div></td><td><?=lotes_h(str_replace('_',' ',(string)$a['tipo']))?><div class="secondary"><?=lotes_h($a['motivo'])?></div></td><td class="over">−<?=lotes_h($a['cantidad'])?></td><td><?=lotes_h($a['stock_despues'])?></td><td><?=lotes_h($a['usuario'])?></td></tr><?php endforeach ?><?php if (!$ajustes): ?><tr><td class="empty" colspan="6">Todavía no hay bajas registradas.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="panel"><div class="panelhead"><h2>Historial de fechas y lotes</h2><span class="secondary">Cada modificación queda registrada con el responsable y motivo</span></div><div class="scroll"><table><thead><tr><th>Fecha del cambio</th><th>Producto / lote</th><th>Vencimiento anterior</th><th>Vencimiento nuevo</th><th>Responsable</th><th>Motivo</th></tr></thead><tbody>
<?php foreach($historialLotes as $h): ?><tr><td><?=lotes_h(date('d/m/Y H:i',strtotime($h['created_at'])))?></td><td><b><?=lotes_h($h['producto'])?></b><div class="secondary"><?=lotes_h($h['lote_anterior'] ?: 'Sin lote')?> → <?=lotes_h($h['lote_nuevo'])?> · Ingreso #<?=lotes_h($h['iddetalle_ingreso'])?></div></td><td><?=lotes_h($h['fecha_anterior'] ? date('d/m/Y',strtotime($h['fecha_anterior'])) : '—')?></td><td><?=lotes_h($h['fecha_nueva'] ? date('d/m/Y',strtotime($h['fecha_nueva'])) : 'Sin fecha')?></td><td><?=lotes_h($h['usuario'])?></td><td><?=lotes_h($h['motivo'])?></td></tr><?php endforeach ?>
<?php if (!$historialLotes): ?><tr><td colspan="6" class="empty">Todavía no se han corregido fechas ni identificado lotes anteriores.</td></tr><?php endif ?></tbody></table></div></div>
<p class="secondary" style="text-align:center;padding-bottom:22px">TiquePOS · Los productos vencidos quedan fuera del despacho FEFO; una baja física se registra aquí con responsable y sustento.</p>
</main>
<dialog id="modalIdentificar"><form method="post" id="formIdentificar"><div class="modalhead">Lote y fecha de vencimiento</div><div class="modalbody">
<p class="muted" style="font-size:13px;margin:0" id="tituloIdentificar"></p>
<div class="note" style="margin:0">Para un lote ya identificado, corregir el vencimiento actualiza <b>todas las recepciones del mismo lote y variante</b> de forma auditada. Al identificar un ingreso sin lote se registra solo su saldo. No se modifica el stock; si hay lotes mezclados, concílialos previamente.</div>
<input type="hidden" name="csrf" value="<?=lotes_h($_SESSION['csrf_inventario_lotes'])?>"><input type="hidden" name="nonce" value="<?=lotes_h($_SESSION['nonce_inventario_lotes'])?>"><input type="hidden" name="accion" value="identificar"><input type="hidden" name="iddetalle_ingreso" id="identificarId">
<label class="field">Número de lote <input type="text" name="numero_lote" id="identificarNumero" maxlength="80" required placeholder="Ej.: L-2027-001"></label>
<label class="field">Fecha de vencimiento <input type="date" name="fecha_vencimiento" id="identificarFecha"><small>Ejemplo: 28/09/2027. Obligatoria si el producto controla vencimientos; opcional si solo controla lotes.</small></label>
<label class="field">Motivo de registro o corrección<textarea rows="2" name="motivo" required minlength="8" maxlength="255" placeholder="Ej.: Identificación física de la etiqueta del lote"></textarea></label>
</div><div class="modalfooter"><button type="button" class="btn secondary" id="cerrarIdentificar">Cancelar</button><button type="submit" class="btn">Guardar datos del lote</button></div></form></dialog>
<dialog id="modalBaja"><form method="post" id="formBaja"><div class="modalhead">Registrar baja de lote</div><div class="modalbody"><p class="muted" style="font-size:13px;margin:0" id="tituloBaja"></p><p class="note" style="margin:0">Esta operación resta stock físico y genera un movimiento de auditoría. No puede deshacerse directamente.</p><input type="hidden" name="csrf" value="<?=lotes_h($_SESSION['csrf_inventario_lotes'])?>"><input type="hidden" name="nonce" value="<?=lotes_h($_SESSION['nonce_inventario_lotes'])?>"><input type="hidden" name="accion" value="baja"><input type="hidden" name="iddetalle_ingreso" id="bajaId"><label class="field">Motivo de baja<select name="tipo" required><option value="BAJA_VENCIMIENTO">Producto vencido</option><option value="BAJA_DANO">Producto dañado</option><option value="BAJA_OTRO">Otra causa documentada</option></select></label><label class="field">Cantidad a retirar<input type="number" name="cantidad" id="bajaCantidad" min="1" step="1" required></label><label class="field">Sustento / observación<textarea rows="3" name="motivo" required minlength="8" maxlength="255" placeholder="Indica por qué se retira del inventario"></textarea></label></div><div class="modalfooter"><button type="button" class="btn secondary" id="cerrarBaja">Cancelar</button><button type="submit" class="btn danger">Confirmar baja</button></div></form></dialog>
<script>(function(){
const ed=document.getElementById('modalIdentificar');
if(ed){document.querySelectorAll('.btnIdentificar').forEach(function(b){b.addEventListener('click',function(){
document.getElementById('identificarId').value=b.dataset.id;
document.getElementById('identificarNumero').value=b.dataset.lote || '';
document.getElementById('identificarFecha').value=b.dataset.fecha || '';
document.getElementById('tituloIdentificar').textContent=b.dataset.nombre+' · Ingreso #'+b.dataset.id+' · Saldo '+b.dataset.max+' unidades';
ed.showModal();
});});document.getElementById('cerrarIdentificar').addEventListener('click',function(){ed.close()});}
const dlg=document.getElementById('modalBaja');if(!dlg)return;document.querySelectorAll('.btnBaja').forEach(function(b){b.addEventListener('click',function(){document.getElementById('bajaId').value=b.dataset.id;document.getElementById('bajaCantidad').value='1';document.getElementById('bajaCantidad').max=b.dataset.max;document.getElementById('tituloBaja').textContent=b.dataset.nombre+' · Lote '+b.dataset.lote+' · Disponible '+b.dataset.max;dlg.showModal();});});document.getElementById('cerrarBaja').addEventListener('click',function(){dlg.close()});})();</script>

    </div>
  </section>
</div>
<?php require 'footer.php'; ob_end_flush(); ?>
