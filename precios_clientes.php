<?php
/** TiquePOS: precios comerciales por cliente. Compatible con esquema 2026-10-09. */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['nombre']) || (int)($_SESSION['ventas'] ?? 0) !== 1) { http_response_code(403); exit('Acceso no autorizado'); }
require_once __DIR__ . '/Config/Conexion.php';
$db = Conexion::conectar();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (empty($_SESSION['csrf_precios_cliente'])) $_SESSION['csrf_precios_cliente'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_precios_cliente'];
$h = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
$clienteSeleccionado = filter_var($_GET['cliente'] ?? $_POST['idcliente'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$cliente = $clienteSeleccionado;
$modal = isset($_REQUEST['modal']) && (string)$_REQUEST['modal'] === '1';
$integrado = isset($_REQUEST['integrado']) && (string)$_REQUEST['integrado'] === '1';
$mensaje = ''; $error = '';
if (isset($_SESSION['flash_precios'])) { $mensaje = (string)$_SESSION['flash_precios']; unset($_SESSION['flash_precios']); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) throw new RuntimeException('La sesión caducó. Actualiza la página.');
        $accion = (string)($_POST['accion'] ?? '');
        if ($modal && $clienteSeleccionado > 0 && $accion === 'desactivar') {
            $check=$db->prepare('SELECT idcliente FROM cliente_precio_especial WHERE idprecio=?');
            $check->execute([$_POST['idprecio'] ?? 0]);
            if ((int)$check->fetchColumn()!==$clienteSeleccionado) throw new RuntimeException('Este acuerdo no corresponde al cliente.');
        }
        if ($accion === 'desactivar') {
            $id = filter_var($_POST['idprecio'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) throw new RuntimeException('Acuerdo inválido.');
            $st = $db->prepare('UPDATE cliente_precio_especial SET activo=0 WHERE idprecio=? AND activo=1');
            $st->execute([$id]);
            if ($st->rowCount() !== 1) throw new RuntimeException('El acuerdo ya estaba inactivo o no existe.');
            $mensaje = 'Acuerdo desactivado correctamente.';
        } elseif ($accion === 'guardar') {
            $cliente = filter_var($_POST['idcliente'] ?? null, FILTER_VALIDATE_INT);
            if ($modal && $clienteSeleccionado > 0 && (int)$cliente !== $clienteSeleccionado) throw new RuntimeException('El cliente seleccionado no coincide.');
            $producto = filter_var($_POST['idarticulo'] ?? null, FILTER_VALIDATE_INT);
            $variante = filter_var($_POST['idvariacion'] ?? 0, FILTER_VALIDATE_INT);
            $edicion = filter_var($_POST['idprecio'] ?? 0, FILTER_VALIDATE_INT);
            $rawPrecio = trim((string)($_POST['precio'] ?? ''));
            $desde = trim((string)($_POST['fecha_inicio'] ?? ''));
            $hasta = trim((string)($_POST['fecha_fin'] ?? ''));
            if (!$cliente || !$producto || $variante === false || $edicion === false || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $rawPrecio) || (float)$rawPrecio <= 0) {
                throw new RuntimeException('Completa cliente, producto y un precio válido con hasta dos decimales.');
            }
            foreach ([$desde, $hasta] as $fecha) {
                if ($fecha !== '') {
                    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
                    if (!$date || $date->format('Y-m-d') !== $fecha) throw new RuntimeException('Las fechas no son válidas.');
                }
            }
            if ($desde !== '' && $hasta !== '' && $hasta < $desde) throw new RuntimeException('La fecha final no puede ser anterior a la inicial.');
            $st = $db->prepare("SELECT 1 FROM persona WHERE idpersona=? AND tipo_persona='Cliente'");
            $st->execute([$cliente]); if (!$st->fetchColumn()) throw new RuntimeException('El cliente no existe.');
            $st = $db->prepare('SELECT 1 FROM articulo WHERE idarticulo=? AND condicion=1');
            $st->execute([$producto]); if (!$st->fetchColumn()) throw new RuntimeException('El producto no existe o está inactivo.');
            $st = $db->prepare('SELECT COUNT(*) FROM articulo_variacion WHERE idarticulo=? AND estado=1');
            $st->execute([$producto]); $tieneVariantes = (int)$st->fetchColumn() > 0;
            if ($tieneVariantes && !$variante) throw new RuntimeException('Debes elegir una presentación para este producto.');
            if (!$tieneVariantes && $variante) throw new RuntimeException('Este producto no tiene variantes.');
            if ($variante) {
                $st = $db->prepare('SELECT 1 FROM articulo_variacion WHERE idvariacion=? AND idarticulo=? AND estado=1');
                $st->execute([$variante, $producto]); if (!$st->fetchColumn()) throw new RuntimeException('La presentación no pertenece al producto.');
            }
            $motivo = mb_substr(trim((string)($_POST['motivo'] ?? '')), 0, 200);
            $db->beginTransaction();
            if ($edicion > 0) {
                $st = $db->prepare('SELECT idprecio,activo FROM cliente_precio_especial WHERE idprecio=? FOR UPDATE');
                $st->execute([$edicion]); $actual = $st->fetch(PDO::FETCH_ASSOC);
                if (!$actual || (int)$actual['activo'] !== 1) throw new RuntimeException('El acuerdo que intentas editar ya no está activo. Recarga la página.');
                // La edición crea una nueva versión; se preserva la versión anterior en el historial.
                if ($modal && $clienteSeleccionado > 0) { $chk=$db->prepare('SELECT idcliente FROM cliente_precio_especial WHERE idprecio=?'); $chk->execute([$edicion]); if ((int)$chk->fetchColumn()!==$clienteSeleccionado) throw new RuntimeException('Acuerdo de otro cliente.'); }
                $st = $db->prepare('UPDATE cliente_precio_especial SET activo=0 WHERE idprecio=?');
                $st->execute([$edicion]);
            }
            // Evita dos tarifas activas para la misma combinación. Los acuerdos anteriores quedan visibles en historial.
            $st = $db->prepare('UPDATE cliente_precio_especial SET activo=0 WHERE idcliente=? AND idarticulo=? AND COALESCE(idvariacion,0)=? AND activo=1');
            $st->execute([$cliente, $producto, (int)$variante]);
            $st = $db->prepare('INSERT INTO cliente_precio_especial (idcliente,idarticulo,idvariacion,precio,fecha_inicio,fecha_fin,activo,motivo,creado_por) VALUES (?,?,?,?,?,?,1,?,?)');
            $st->execute([$cliente, $producto, $variante ?: null, $rawPrecio, $desde ?: null, $hasta ?: null, $motivo, (int)($_SESSION['idusuario'] ?? 0) ?: null]);
            $db->commit();
            $mensaje = $edicion ? 'Acuerdo actualizado. La versión anterior quedó guardada en el historial.' : 'Precio especial creado correctamente.';
        } else { throw new RuntimeException('Operación no reconocida.'); }
        $_SESSION['flash_precios'] = $mensaje;
        header('Location: precios_clientes.php' . ($integrado ? '?integrado=1' : '') . ($cliente ? ($integrado ? '&' : '?') . 'cliente=' . (int)$cliente : '') . ($modal ? '&modal=1' : ''), true, 303); exit;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e instanceof PDOException ? 'No se pudo guardar. Verifica la conexión y vuelve a intentarlo.' : $e->getMessage();
    }
}
try {
    $clientes = $db->query("SELECT idpersona,nombre,num_documento FROM persona WHERE tipo_persona='Cliente' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $productos = $db->query('SELECT idarticulo,nombre,precio_venta FROM articulo WHERE condicion=1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    $variantes = $db->query('SELECT idvariacion,idarticulo,combinacion,precio_venta FROM articulo_variacion WHERE estado=1 ORDER BY combinacion')->fetchAll(PDO::FETCH_ASSOC);
    $sqlAcuerdos = "SELECT cp.*,p.nombre AS cliente,a.nombre AS producto,av.combinacion AS variante,a.precio_venta AS precio_base,av.precio_venta AS precio_variante FROM cliente_precio_especial cp INNER JOIN persona p ON p.idpersona=cp.idcliente INNER JOIN articulo a ON a.idarticulo=cp.idarticulo LEFT JOIN articulo_variacion av ON av.idvariacion=cp.idvariacion " . ($modal && $clienteSeleccionado > 0 ? ' WHERE cp.idcliente=? ' : ' ') . " ORDER BY cp.activo DESC,cp.actualizado_en DESC,cp.idprecio DESC LIMIT 1000";
    $q=$db->prepare($sqlAcuerdos);$q->execute($modal && $clienteSeleccionado > 0 ? [$clienteSeleccionado] : []);$acuerdos=$q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { http_response_code(500); exit('No se pudo cargar el módulo. Verifica la tabla cliente_precio_especial.'); }
$hoy = date('Y-m-d'); $activos = 0; $programados = 0; $inactivos = 0; $clientesConPrecio = [];
foreach ($acuerdos as $a) {
    if ((int)$a['activo'] !== 1) { $inactivos++; continue; }
    if (($a['fecha_inicio'] && $a['fecha_inicio'] > $hoy) || ($a['fecha_fin'] && $a['fecha_fin'] < $hoy)) $programados++;
    else { $activos++; $clientesConPrecio[$a['idcliente']] = true; }
}
$input = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Precios por cliente | TiquePOS</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.hide-row{display:none!important}button:focus-visible,a:focus-visible{outline:2px solid #059669;outline-offset:2px}</style>
</head>
<body class="bg-[#f7faf9] text-slate-800" style="min-height:0;height:auto;overflow:hidden">
<main class="mx-auto max-w-[1440px] <?= $integrado ? 'px-1 py-2' : 'px-4 py-6 sm:px-6 lg:px-8' ?>">
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
 <div><a <?= $integrado ? 'style="display:none"' : '' ?> href="index.php" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-emerald-600">← Volver al sistema</a><div class="mt-3 flex items-center gap-3"><div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 5h18v14H3zM3 10h18M7 15h4"/></svg></div><div><h1 class="text-2xl font-bold tracking-tight sm:text-[28px]">Precios por cliente</h1><p class="text-sm text-slate-500">Acuerdos comerciales, presentaciones y vigencias</p></div></div></div>
 <button type="button" id="newBtn" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"><span class="text-lg leading-none">+</span> Nuevo acuerdo</button>
</div>
<?php if($mensaje): ?><div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status"><?= $h($mensaje) ?></div><?php endif; ?>
<?php if($error): ?><div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><?= $h($error) ?></div><?php endif; ?>
<div class="<?= $modal ? 'hidden' : 'mb-3 grid grid-cols-2 gap-2 lg:grid-cols-4' ?>">
<?php
$stats = [
  ['Acuerdos vigentes', $activos, 'text-emerald-700 bg-emerald-50', 'check'],
  ['Clientes con precio', count($clientesConPrecio), 'text-emerald-700 bg-emerald-50', 'users'],
  ['Fuera de vigencia', $programados, 'text-amber-700 bg-amber-50', 'clock'],
  ['Historial inactivo', $inactivos, 'text-slate-600 bg-slate-100', 'history'],
];
foreach ($stats as $stat): ?>
<div class="flex min-h-[66px] items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
  <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg <?= $stat[2] ?>" aria-hidden="true">
    <?php if ($stat[3] === 'check'): ?>
      <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/><circle cx="12" cy="12" r="10"/></svg>
    <?php elseif ($stat[3] === 'users'): ?>
      <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    <?php elseif ($stat[3] === 'clock'): ?>
      <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    <?php else: ?>
      <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg>
    <?php endif; ?>
  </span>
  <div class="min-w-0"><div class="text-xl font-bold leading-6 text-slate-900"><?= (int)$stat[1] ?></div><div class="text-[11px] leading-4 text-slate-500"><?= $h($stat[0]) ?></div></div>
</div>
<?php endforeach; ?>
</div>
<div class="grid items-start gap-5 xl:grid-cols-[minmax(335px,390px)_minmax(0,1fr)]">
<section id="editor" class="scroll-mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:p-6">
 <div class="mb-5 flex items-start justify-between gap-2"><div><h2 id="formTitle" class="text-lg font-bold">Nuevo acuerdo comercial</h2><p id="formSubtitle" class="mt-1 text-xs leading-5 text-slate-500">Solo define producto, presentación, precio y vigencia; DNI/RUC se gestiona en la ficha del cliente.</p></div><button type="button" id="cancelEdit" class="hidden rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Cancelar edición</button></div>
 <form id="priceForm" method="post" class="space-y-4">
  <input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="integrado" value="<?= $integrado ? '1' : '0' ?>"><input type="hidden" name="modal" value="<?= $modal ? '1' : '0' ?>"><input type="hidden" name="accion" value="guardar"><input type="hidden" name="idprecio" id="idprecio" value="0">
  <label class="block"><span class="mb-1.5 block text-xs font-semibold text-slate-700">Cliente <span class="text-rose-500">*</span></span><select id="cliente" name="idcliente" required class="<?= $input ?>" <?= $modal ? 'style="pointer-events:none;background:#f1f5f9" tabindex="-1"' : '' ?>><option value="">Selecciona un cliente</option><?php foreach ($clientes as $c): ?><option value="<?= $h($c['idpersona']) ?>"><?= $h($c['nombre']) ?></option><?php endforeach; ?></select></label>
  <label class="block"><span class="mb-1.5 block text-xs font-semibold text-slate-700">Producto <span class="text-rose-500">*</span></span><select id="producto" name="idarticulo" required class="<?= $input ?>"><option value="">Selecciona un producto</option><?php foreach ($productos as $p): ?><option value="<?= $h($p['idarticulo']) ?>" data-precio="<?= $h($p['precio_venta']) ?>"><?= $h($p['nombre']) ?></option><?php endforeach; ?></select></label>
  <label class="block"><span class="mb-1.5 block text-xs font-semibold text-slate-700">Presentación</span><select id="variante" name="idvariacion" class="<?= $input ?>"><option value="0">Producto sin variantes</option></select></label>
  <div class="grid grid-cols-[1fr_auto] items-end gap-3"><label class="block"><span class="mb-1.5 block text-xs font-semibold text-slate-700">Precio acordado (S/) <span class="text-rose-500">*</span></span><input id="precio" name="precio" required type="number" min="0.01" max="99999999" step="0.01" placeholder="0.00" class="<?= $input ?>"></label><div class="rounded-xl bg-slate-50 px-3 py-2.5 text-right"><div class="text-[10px] text-slate-500">Precio general</div><strong id="precioGeneral" class="whitespace-nowrap text-sm text-slate-800">—</strong></div></div>
  <div class="grid grid-cols-2 gap-3"><label class="block"><span class="mb-1.5 block text-xs font-semibold text-slate-700">Vigente desde</span><input id="desde" name="fecha_inicio" type="date" class="<?= $input ?>"></label><label class="block"><span class="mb-1.5 block text-xs font-semibold text-slate-700">Vigente hasta</span><input id="hasta" name="fecha_fin" type="date" class="<?= $input ?>"></label></div>
  <label class="block"><span class="mb-1.5 block text-xs font-semibold text-slate-700">Motivo / observación</span><textarea id="motivo" name="motivo" maxlength="200" rows="2" placeholder="Ej.: precio pactado para distribuidor" class="<?= $input ?> resize-y"></textarea></label>
  <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 p-3 text-xs leading-5 text-emerald-900">El POS aplicará este precio si el acuerdo está activo y dentro de su vigencia. Al editar, se conservará la versión anterior en el historial.</div>
  <button id="saveBtn" type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">Guardar acuerdo</button>
 </form>
</section>
<section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
 <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5"><div><h2 class="text-lg font-bold">Acuerdos e historial</h2><p class="text-xs text-slate-500">Puedes editar, buscar o desactivar acuerdos vigentes.</p></div><span class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-600"><?= count($acuerdos) ?> registros</span></div>
 <div class="flex flex-wrap gap-3 border-b border-slate-100 p-4"><input id="filtro" type="search" placeholder="Buscar cliente, producto o variante..." class="<?= $input ?> min-w-[200px] flex-1"><select id="estadoFiltro" class="<?= $input ?> sm:w-44"><option value="todos">Todos los estados</option><option value="vigente">Vigentes</option><option value="programado">Fuera de vigencia</option><option value="inactivo">Inactivos</option></select></div>
 <div class="overflow-x-auto"><table class="w-full min-w-[680px] text-left text-sm"><thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 font-semibold">Cliente / Producto</th><th class="px-4 py-3 font-semibold">Precio</th><th class="px-4 py-3 font-semibold">Vigencia</th><th class="px-4 py-3 font-semibold">Estado</th><th class="px-4 py-3 text-right font-semibold">Acciones</th></tr></thead><tbody id="rows" class="divide-y divide-slate-100">
<?php foreach ($acuerdos as $r):
 $activo=(int)$r['activo']===1;
 $vigente=$activo && (!$r['fecha_inicio'] || $r['fecha_inicio']<=$hoy) && (!$r['fecha_fin'] || $r['fecha_fin']>=$hoy);
 $estado=$vigente?'vigente':($activo?'programado':'inactivo');
 $estadoTexto=$vigente?'Vigente':($activo?'Fuera de vigencia':'Inactivo');
 $estilo=$vigente?'bg-emerald-50 text-emerald-700':($activo?'bg-amber-50 text-amber-700':'bg-slate-100 text-slate-500');
 $base=$r['idvariacion'] ? $r['precio_variante'] : $r['precio_base'];
 $dato=['idprecio'=>$r['idprecio'],'idcliente'=>$r['idcliente'],'idarticulo'=>$r['idarticulo'],'idvariacion'=>$r['idvariacion']?:0,'precio'=>$r['precio'],'fecha_inicio'=>$r['fecha_inicio'],'fecha_fin'=>$r['fecha_fin'],'motivo'=>$r['motivo']];
?><tr data-estado="<?= $estado ?>" class="transition hover:bg-slate-50/80">
<td class="px-4 py-3"><div class="font-semibold text-slate-900"><?= $h($r['cliente']) ?></div><div class="mt-0.5 max-w-[260px] truncate text-xs text-slate-500" title="<?= $h($r['producto']) ?>"><?= $h($r['producto']) ?><?= $r['variante']?' · '.$h($r['variante']):'' ?></div></td>
<td class="whitespace-nowrap px-4 py-3"><strong class="text-emerald-700">S/ <?= number_format((float)$r['precio'],2) ?></strong><div class="text-[11px] text-slate-400">General: S/ <?= number_format((float)$base,2) ?></div></td>
<td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500"><?= $h($r['fecha_inicio']?:'Sin inicio') ?><div><?= $h($r['fecha_fin']?:'Sin fin') ?></div></td>
<td class="px-4 py-3"><span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $estilo ?>"><?= $estadoTexto ?></span></td>
<td class="whitespace-nowrap px-4 py-3 text-right"><div class="flex items-center justify-end gap-1.5"><?php if ($activo): ?><button type="button" class="editBtn rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:border-emerald-200 hover:bg-emerald-50" data-acuerdo="<?= $h(json_encode($dato, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)) ?>">Editar</button><form method="post" onsubmit="return confirm('¿Desactivar este acuerdo?')"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="integrado" value="<?= $integrado ? '1' : '0' ?>"><input type="hidden" name="modal" value="<?= $modal ? '1' : '0' ?>"><input type="hidden" name="accion" value="desactivar"><input type="hidden" name="idprecio" value="<?= $h($r['idprecio']) ?>"><button type="submit" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Desactivar</button></form><?php else: ?><span class="text-xs text-slate-400">Histórico</span><?php endif; ?></div></td>
</tr><?php endforeach; ?></tbody></table></div>
<div id="emptyRows" class="hidden px-5 py-12 text-center text-sm text-slate-500">No hay acuerdos que coincidan con los filtros.</div>
<div class="border-t border-slate-100 px-4 py-3 text-xs text-slate-400">Se muestran hasta 1,000 registros, incluidos los acuerdos históricos.</div>
</section></div>
</main>
<script>
(() => {
 'use strict';
 const clienteInicial=<?= (int)$clienteSeleccionado ?>;
 const modalOnly=<?= $modal ? 'true' : 'false' ?>;
 const variantes=<?= json_encode($variantes,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?>;
 const $=id=>document.getElementById(id), form=$('priceForm'), producto=$('producto'), variante=$('variante');
 const money=value=>{const x=Number(value);return Number.isFinite(x)?'S/ '+x.toFixed(2):'—'};
 function setVariantes(selected){
   const items=variantes.filter(x=>String(x.idarticulo)===producto.value);
   variante.replaceChildren();
   let base=new Option(items.length?'Selecciona una presentación':'Producto sin variantes','0');
   if(items.length) base.disabled=true;
   variante.add(base);
   for(const x of items) variante.add(new Option((x.combinacion||'Variante')+' · '+money(x.precio_venta),String(x.idvariacion)));
   variante.value=selected && items.some(x=>String(x.idvariacion)===String(selected))?String(selected):(items.length?String(items[0].idvariacion):'0');
   updateGeneral();
 }
 function updateGeneral(){
   const selected=variantes.find(x=>String(x.idvariacion)===variante.value && String(x.idarticulo)===producto.value);
   $('precioGeneral').textContent=money(selected?selected.precio_venta:producto.selectedOptions[0]?.dataset.precio);
 }
 function resetForm(){form.reset();$('idprecio').value='0';$('formTitle').textContent='Nuevo acuerdo comercial';$('formSubtitle').textContent='Define un precio fijo para un cliente y una presentación.';$('saveBtn').textContent='Guardar acuerdo';$('cancelEdit').classList.add('hidden');setVariantes(0);}
 producto.addEventListener('change',()=>setVariantes(0));variante.addEventListener('change',updateGeneral);
 $('newBtn').addEventListener('click',()=>{resetForm();$('editor').scrollIntoView({behavior:'smooth',block:'start'});});
 $('cancelEdit').addEventListener('click',resetForm);
 document.querySelectorAll('.editBtn').forEach(button=>button.addEventListener('click',()=>{
   let x;try{x=JSON.parse(button.dataset.acuerdo);}catch{return;}
   resetForm();$('idprecio').value=x.idprecio;$('cliente').value=String(x.idcliente);producto.value=String(x.idarticulo);setVariantes(x.idvariacion);
   $('precio').value=x.precio;$('desde').value=x.fecha_inicio||'';$('hasta').value=x.fecha_fin||'';$('motivo').value=x.motivo||'';
   $('formTitle').textContent='Editar acuerdo comercial';$('formSubtitle').textContent='Al guardar se conservará la versión anterior en el historial.';
   $('saveBtn').textContent='Guardar cambios';$('cancelEdit').classList.remove('hidden');$('editor').scrollIntoView({behavior:'smooth',block:'start'});
 }));
 function filter(){const q=$('filtro').value.trim().toLocaleLowerCase(),status=$('estadoFiltro').value;let shown=0;
 document.querySelectorAll('#rows tr').forEach(row=>{const visible=(status==='todos'||row.dataset.estado===status)&&row.textContent.toLocaleLowerCase().includes(q);row.classList.toggle('hide-row',!visible);if(visible)shown++;});$('emptyRows').classList.toggle('hidden',shown>0);}
 $('filtro').addEventListener('input',filter);$('estadoFiltro').addEventListener('change',filter);
 setVariantes(0);
 if (clienteInicial > 0) { $('cliente').value=String(clienteInicial); $('cliente').dispatchEvent(new Event('change')); }
})();
</script>
<script>
(function(){
  if(window.parent===window || new URLSearchParams(location.search).has('modal')) return;
  const main = document.querySelector('main');
  if(!main) return;
  let previous = 0, scheduled = false;
  function report(){
    scheduled = false;
    // Se mide main (no html/body) para evitar el ciclo iframe -> viewport -> iframe.
    const height = Math.ceil(main.getBoundingClientRect().height + 18);
    if(Math.abs(height-previous) <= 3) return;
    previous = height;
    window.parent.postMessage({type:'tiq-precios-content-height',height:height},window.location.origin);
  }
  function schedule(){ if(!scheduled){ scheduled=true; requestAnimationFrame(report); } }
  window.addEventListener('load',schedule);
  if('ResizeObserver' in window) new ResizeObserver(schedule).observe(main);
  if('MutationObserver' in window) new MutationObserver(schedule).observe(main,{childList:true,subtree:true,attributes:true,attributeFilter:['class','style','hidden']});
  schedule();
})();
</script>
</body></html>
