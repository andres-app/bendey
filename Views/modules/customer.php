<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['nombre'])) { header('Location: login'); exit; }
require 'header.php';
require 'sidebar.php';
if ((int)($_SESSION['ventas'] ?? 0) !== 1) { require 'access.php'; }
else { ?>
<script src="https://cdn.tailwindcss.com"></script>
<style>
#tiq-clientes, #tiq-clientes * {box-sizing:border-box} #tiq-clientes .tiq-field{border:1px solid #dbe3ee;border-radius:10px;padding:10px 12px;width:100%;background:white;min-height:42px;outline:none}#tiq-clientes .tiq-field:focus{border-color:#059669;box-shadow:0 0 0 3px #d1fae5}#tiq-clientes .dataTables_wrapper .dt-buttons .dt-button{background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:5px 11px;font-size:12px;color:#334155}#tiq-clientes table.dataTable thead th{background:#f8fafc;color:#475569;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.035em;border-bottom:1px solid #e2e8f0}#tiq-clientes table.dataTable tbody td{vertical-align:middle;border-color:#f1f5f9;font-size:13px;padding:12px 10px}#tiq-clientes .dataTables_wrapper .dataTables_filter input{border:1px solid #cbd5e1;border-radius:9px;padding:7px 10px;margin-left:7px}#tiq-clientes button:focus{outline:none;box-shadow:0 0 0 3px #bbf7d0}#tiq-clientes .tiq-action{border:1px solid #e2e8f0;border-radius:8px;background:white;padding:7px 9px;margin-right:4px;color:#475569}#tiq-clientes .tiq-action:hover{background:#ecfdf5;color:#047857}#tiq-clientes .tiq-action-group{display:flex;align-items:center;gap:6px;white-space:nowrap}#tiq-clientes .tiq-action{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;padding:0;margin:0;flex:0 0 auto}#tiq-clientes .tiq-action-price{color:#047857;background:#f0fdf4;border-color:#bbf7d0}#tiq-clientes .tiq-field{appearance:auto!important;-webkit-appearance:menulist!important;line-height:1.4!important;color:#334155!important}#tiq-clientes select.tiq-field{padding-right:34px!important;background-color:#fff!important;color-scheme:light}#tiq-clientes select.tiq-field option{background:#fff!important;color:#1e293b!important;font-weight:400}#tiq-clientes .dt-buttons{display:flex!important;justify-content:flex-end;gap:6px;float:none!important;margin:0!important}#tiq-clientes .dt-buttons .dt-button{display:inline-flex!important;gap:5px;align-items:center;font-weight:600!important;box-shadow:none!important;margin:0!important;padding:7px 10px!important}#tiq-clientes .dt-buttons .buttons-excel{color:#047857!important;background:#f0fdf4!important;border-color:#bbf7d0!important}#tiq-clientes .dt-buttons .buttons-pdf{color:#475569!important;background:#f8fafc!important}#tiq-clientes .card{box-shadow:none;border:1px solid #e2e8f0;border-radius:16px}
#tiq-clientes .tiq-tab{border:0;background:transparent;color:#64748b;border-radius:9px;padding:9px 15px;font-size:13px;font-weight:650;cursor:pointer}#tiq-clientes .tiq-tab.active{background:#ecfdf5;color:#047857;box-shadow:0 1px 2px #cbd5e1}#tiq-clientes .tiq-field:focus{border-color:#059669;box-shadow:0 0 0 3px #d1fae5}#tiq-clientes .tiq-action:hover{background:#ecfdf5;color:#047857}
#tiq-clientes #tiq-clientes-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
#tiq-clientes .tiq-clientes-search{position:relative;display:flex;align-items:center;flex:1 1 280px;max-width:440px;min-width:0}
#tiq-clientes .tiq-clientes-search i{position:absolute;left:13px;color:#94a3b8;pointer-events:none;font-size:13px}
#tiq-clientes #tiq-clientes-buscar{display:block;width:100%;min-height:38px;padding:9px 12px 9px 36px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#334155;font-size:13px;outline:none;transition:border-color .15s ease,box-shadow .15s ease}
#tiq-clientes #tiq-clientes-buscar:focus{border-color:#059669;box-shadow:0 0 0 3px #d1fae5}
#tiq-clientes #tiq-clientes-buscar::placeholder{color:#94a3b8}
#tiq-clientes #tiq-export-toolbar{display:flex!important;justify-content:flex-end;align-items:center;gap:8px;min-height:0;flex-wrap:nowrap;overflow:visible;margin-left:auto}
@media(max-width:640px){#tiq-clientes .tiq-clientes-search{flex-basis:100%;max-width:none}#tiq-clientes #tiq-export-toolbar{margin-left:0;width:100%;justify-content:flex-end}}

#tiq-clientes #tiq-export-toolbar .dt-buttons{display:flex!important;align-items:center;gap:8px;flex-wrap:nowrap;margin:0!important;padding:0!important}
#tiq-clientes #tiq-export-toolbar .dt-button.tiq-export-btn{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-width:66px;height:34px!important;white-space:nowrap!important;padding:0 12px!important;border:1px solid #dbe3ee!important;border-radius:9px!important;font-size:12px!important;font-weight:650!important;line-height:1!important;box-shadow:none!important;margin:0!important;background:#fff!important;color:#334155!important}
#tiq-clientes #tiq-export-toolbar .dt-button.tiq-export-excel{background:#ecfdf5!important;color:#047857!important;border-color:#a7f3d0!important}
#tiq-clientes #tiq-export-toolbar .dt-button:hover{filter:brightness(.97)}
#tiq-clientes .tiq-action svg{display:block;width:17px;height:17px;flex-shrink:0}
#tiq-clientes .tiq-action-group{min-width:76px}
</style>
<style>
.tiq-modal-overlay[hidden]{display:none!important}.tiq-modal-overlay{position:fixed;inset:0;z-index:20000;background:rgba(15,23,42,0);opacity:0;visibility:hidden;transition:opacity .24s ease,background-color .24s ease,visibility .24s;display:flex;align-items:center;justify-content:center;padding:15px}.tiq-modal-window{width:min(1120px,100%);height:min(91vh,900px);background:#f8fafc;border-radius:16px;overflow:hidden;transform:translateY(14px) scale(.985);opacity:0;transition:transform .28s cubic-bezier(.2,.8,.2,1),opacity .24s ease;display:flex;flex-direction:column;box-shadow:0 25px 90px rgba(0,0,0,.3)}.tiq-modal-heading{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;border-bottom:1px solid #e2e8f0;background:#fff}.tiq-modal-window iframe{width:100%;height:100%;flex:1;border:0;background:#f8fafc}@media(max-width:640px){.tiq-modal-overlay{padding:5px}.tiq-modal-window{height:98vh}}

.tiq-modal-overlay.tiq-is-open{opacity:1;visibility:visible;background:rgba(15,23,42,.55)}
.tiq-modal-overlay.tiq-is-open .tiq-modal-window{opacity:1;transform:translateY(0) scale(1)}
.tiq-modal-overlay #tiq-modal-close{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;min-width:36px;padding:0;line-height:1;cursor:pointer;border:1px solid #e2e8f0;border-radius:10px;color:#475569;background:#fff;transition:background-color .18s,color .18s,transform .18s}
.tiq-modal-overlay #tiq-modal-close:hover{background:#f1f5f9;color:#0f172a;transform:scale(1.04)}
.tiq-modal-overlay #tiq-modal-close svg{width:19px;height:19px;display:block;stroke:currentColor}
@media(prefers-reduced-motion:reduce){.tiq-modal-overlay,.tiq-modal-window{transition:none!important}}
</style>
<div class="main-content" id="tiq-clientes"><section class="section"><div class="section-body">
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-1"><div><div class="text-xs font-semibold tracking-widest uppercase text-emerald-600">Gestión comercial</div><h1 class="text-2xl font-bold text-slate-900">Clientes</h1><p class="text-sm text-slate-500">Datos del cliente y acuerdos comerciales, organizados en secciones diferentes.</p></div><div class="flex items-center gap-2 flex-wrap"><a href="#precios" onclick="tiqClientesTab('precios');return false;" class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-100"><i class="fas fa-tags"></i> Precios por cliente</a><button type="button" onclick="mostrarform(true)" id="btnagregar" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"><i class="fas fa-plus"></i> Nuevo cliente</button></div></div>
<div class="flex gap-1.5 mb-2 mt-0 p-1 rounded-xl bg-white border border-slate-200 w-fit" role="tablist" aria-label="Secciones de clientes"><button class="tiq-tab active" id="tiq-tab-clientes" type="button" role="tab" aria-selected="true" onclick="tiqClientesTab('clientes')"><i class="fas fa-address-book mr-2"></i>Directorio</button><button class="tiq-tab" id="tiq-tab-precios" type="button" role="tab" aria-selected="false" onclick="tiqClientesTab('precios')"><i class="fas fa-tag mr-2"></i>Precios especiales</button></div><div id="tiq-clientes-panel"><div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5"><div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Clientes registrados</div><div id="tiq-clientes-total" class="text-2xl font-bold text-slate-900 mt-1">—</div></div><div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Clientes preferenciales</div><div id="tiq-clientes-preferenciales" class="text-2xl font-bold text-emerald-700 mt-1">—</div></div><div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Acuerdos comerciales</div><a class="inline-block text-sm text-emerald-700 font-semibold mt-2" href="#precios" onclick="tiqClientesTab('precios');return false;">Ver precios negociados <i class="fas fa-arrow-right"></i></a></div></div>
<div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm" id="listadoregistros"><div class="p-4 border-b border-slate-100"><h2 class="font-semibold text-slate-800">Directorio de clientes</h2><p class="text-xs text-slate-500">Edita la ficha o gestiona precios especiales de cada cliente.</p></div><div id="tiq-clientes-toolbar" class="border-b border-slate-100 px-4 py-3"><label class="tiq-clientes-search" for="tiq-clientes-buscar"><i class="fas fa-search" aria-hidden="true"></i><input id="tiq-clientes-buscar" type="search" aria-label="Buscar clientes" placeholder="Buscar por cliente, documento, teléfono..." autocomplete="off"></label><div id="tiq-export-toolbar" aria-label="Exportar directorio de clientes"></div></div><div class="px-4 pt-3 pb-4 overflow-x-auto"><table id="tbllistado" class="table table-hover text-nowrap" style="width:100%"><thead><tr><th>Acciones</th><th>Cliente</th><th>Documento</th><th>Número</th><th>Teléfono</th><th>Correo</th><th>Tipo</th></tr></thead><tbody></tbody></table></div></div>
<div id="formularioregistros" class="rounded-2xl border border-slate-200 bg-white shadow-sm" style="display:none"><div class="flex items-center justify-between p-5 border-b border-slate-100"><div><h2 id="tiq-form-title" class="text-lg font-semibold text-slate-900">Nuevo cliente</h2><p class="text-xs text-slate-500">Información comercial y de contacto</p></div><button type="button" class="text-slate-400 hover:text-slate-700" onclick="cancelarform()" aria-label="Cerrar"><i class="fas fa-times"></i></button></div><form id="formulario" name="formulario" method="POST" class="p-5"><input type="hidden" name="idpersona" id="idpersona"><input type="hidden" name="tipo_persona" id="tipo_persona" value="Cliente"><div class="grid grid-cols-1 md:grid-cols-3 gap-4">
<div><label class="text-xs font-semibold text-slate-600 block mb-1">Tipo de documento</label><select class="tiq-field" name="tipo_documento" id="tipo_documento" required><option value="DNI">DNI</option><option value="RUC">RUC</option></select></div>
<div><label class="text-xs font-semibold text-slate-600 block mb-1">Número de documento</label><div class="flex gap-2"><input class="tiq-field" type="text" name="num_documento" id="num_documento" maxlength="11" inputmode="numeric" placeholder="Número"><button type="button" class="rounded-lg bg-slate-100 px-3 text-slate-700 hover:bg-slate-200" title="Consultar documento" onclick="consultarCliente()"><i class="fas fa-search"></i></button></div></div>
<div><label class="text-xs font-semibold text-slate-600 block mb-1">Nombre o razón social *</label><input class="tiq-field" type="text" name="nombre" id="nombre" maxlength="100" placeholder="Nombre completo" required></div>
<div class="md:col-span-2"><label class="text-xs font-semibold text-slate-600 block mb-1">Dirección</label><input class="tiq-field" type="text" name="direccion" id="direccion" maxlength="70" placeholder="Dirección del cliente"></div>
<div><label class="text-xs font-semibold text-slate-600 block mb-1">Teléfono</label><input class="tiq-field" type="text" name="telefono" id="telefono" maxlength="20" placeholder="Celular o teléfono"></div>
<div><label class="text-xs font-semibold text-slate-600 block mb-1">Correo electrónico</label><input class="tiq-field" type="email" name="email" id="email" maxlength="50" placeholder="correo@ejemplo.com"></div>
<div class="md:col-span-2"><label class="text-xs font-semibold text-slate-600 block mb-1">Clasificación comercial</label><select class="tiq-field" name="es_preferencial" id="es_preferencial"><option value="0">Cliente regular</option><option value="1">Cliente frecuente / preferencial</option></select><p class="mt-1 text-xs text-slate-500">Los acuerdos individuales se administran en Precios por cliente.</p></div></div>
<div class="flex justify-end gap-2 mt-6 pt-4 border-t border-slate-100"><button type="button" onclick="cancelarform()" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancelar</button><button class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700" type="submit" id="btnGuardar"><i class="fas fa-save mr-1"></i> Guardar cliente</button></div></form></div>
</div><div id="tiq-precios-panel" hidden><iframe id="tiq-precios-frame" title="Precios especiales por cliente" loading="lazy" data-src="precios_clientes.php?integrado=1" scrolling="no" style="display:block;width:100%;height:600px;min-height:0;border:0;background:transparent;overflow:hidden"></iframe></div></div></section></div>
<!-- Modal del cliente: evita navegar a acuerdos de otros clientes -->
<div id="tiq-precio-modal" class="tiq-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="tiq-precio-modal-title" hidden>
  <div class="tiq-modal-window">
    <div class="tiq-modal-heading"><div><div class="text-xs font-semibold text-emerald-700 uppercase tracking-wide">Condiciones comerciales</div><h2 id="tiq-precio-modal-title" class="text-lg font-bold text-slate-900">Precios acordados</h2></div><button id="tiq-modal-close" type="button" class="tiq-action" aria-label="Cerrar precios" title="Cerrar"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
    <iframe id="tiq-modal-frame" title="Editar precios del cliente" referrerpolicy="same-origin"></iframe>
  </div>
</div>
<script>
function tiqClientesTab(tab){
 var price=tab==='precios', clients=document.getElementById('tiq-clientes-panel'), prices=document.getElementById('tiq-precios-panel');
 clients.hidden=price;prices.hidden=!price;clients.style.display=price?'none':'';prices.style.display=price?'':'none';
 ['clientes','precios'].forEach(function(k){var btn=document.getElementById('tiq-tab-'+k);if(btn){btn.classList.toggle('active',k===tab);btn.setAttribute('aria-selected',String(k===tab));}});
 var frame=document.getElementById('tiq-precios-frame');if(price&&frame&&!frame.getAttribute('src'))frame.setAttribute('src',frame.dataset.src);
 if(location.hash!=='#'+tab)history.replaceState(null,'','#'+tab);
}
// Altura basada en el contenido real, sin un scroll interno en la pestaña.
(function(){
  const frame = document.getElementById('tiq-precios-frame');
  if(!frame) return;
  let previous = 0;
  window.addEventListener('message', function(event){
    if(event.origin !== window.location.origin || event.source !== frame.contentWindow) return;
    const data = event.data;
    if(!data || data.type !== 'tiq-precios-content-height') return;
    const h = Math.ceil(Number(data.height));
    if(!Number.isFinite(h) || h < 200 || h > 30000) return;
    if(Math.abs(h-previous) <= 3) return;
    previous = h;
    frame.style.height = h + 'px';
  });
})();
if(location.hash==='#precios')tiqClientesTab('precios');
(function(){
  var m=document.getElementById('tiq-precio-modal');
  var f=document.getElementById('tiq-modal-frame');
  var closeButton=document.getElementById('tiq-modal-close');
  var lastFocus=null,closeTimer=null,openFrame=null;
  window.tiqAbrirPreciosCliente=function(id){
    id=Number(id); if(!Number.isInteger(id)||id<1)return;
    if(closeTimer){clearTimeout(closeTimer);closeTimer=null;}
    if(openFrame){cancelAnimationFrame(openFrame);openFrame=null;}
    lastFocus=document.activeElement;
    f.src='precios_clientes.php?integrado=1&modal=1&cliente='+encodeURIComponent(id);
    m.hidden=false; document.body.style.overflow='hidden';
    openFrame=requestAnimationFrame(function(){openFrame=requestAnimationFrame(function(){m.classList.add('tiq-is-open');closeButton.focus();openFrame=null;});});
  };
  window.tiqCerrarPreciosCliente=function(){
    if(m.hidden)return;
    if(openFrame){cancelAnimationFrame(openFrame);openFrame=null;}
    m.classList.remove('tiq-is-open');
    var finish=function(){m.hidden=true;f.removeAttribute('src');document.body.style.overflow='';if(lastFocus&&document.contains(lastFocus))lastFocus.focus();closeTimer=null;};
    if(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches){finish();return;}
    closeTimer=setTimeout(finish,290);
  };
  closeButton.addEventListener('click',window.tiqCerrarPreciosCliente);
  m.addEventListener('click',function(e){if(e.target===m)window.tiqCerrarPreciosCliente();});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!m.hidden)window.tiqCerrarPreciosCliente();});
})();
</script>
<?php } require 'footer.php'; ?>
<script src="Views/modules/scripts/customer.js?v=<?= (int)@filemtime(__DIR__.'/scripts/customer.js') ?>"></script>
<?php ob_end_flush(); ?>
