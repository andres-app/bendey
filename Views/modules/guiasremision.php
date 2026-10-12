<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
date_default_timezone_set('America/Lima');
if (empty($_SESSION['nombre'])) { header('Location: login'); exit; }
require 'header.php';
require 'sidebar.php';
if ((int)($_SESSION['guias_remision'] ?? 0) !== 1) {
    require 'access.php';
    require 'footer.php';
    ob_end_flush(); exit;
}
if (empty($_SESSION['gre_csrf'])) $_SESSION['gre_csrf'] = bin2hex(random_bytes(32));
$tokenGre = $_SESSION['gre_csrf'];
$idventaGre = max(0, (int)($_GET['idventa']??0));
?>
<link rel="stylesheet" href="Assets/css/guias-remision.css?v=<?= (int)@filemtime(__DIR__.'/../../Assets/css/guias-remision.css') ?>">
<main class="main-content gre-page">
 <section class="section">
  <header class="gre-head">
   <div><span class="gre-eyebrow"><i class="fas fa-truck mr-1"></i> Gestión logística</span>
    <h1>Guías de remisión</h1><p>Registra los traslados, vincula ventas y conserva la trazabilidad de los productos.</p></div>
   <button type="button" class="btn gre-btn-primary" id="greNueva"><i class="fas fa-plus mr-2"></i>Nueva guía</button>
  </header>
  <div class="gre-stats">
   <div class="gre-stat"><span>Total de guías</span><strong id="greTotal">—</strong><i class="fas fa-layer-group"></i></div>
   <div class="gre-stat"><span>Borradores</span><strong id="greBorradores">—</strong><i class="far fa-file-alt"></i></div>
   <div class="gre-stat"><span>Preparadas</span><strong id="grePreparadas">—</strong><i class="fas fa-clipboard-check"></i></div>
   <div class="gre-stat"><span>Aceptadas SUNAT</span><strong id="greAceptadas">—</strong><i class="fas fa-check-circle"></i></div>
  </div>
  <div class="gre-panel">
   <div class="gre-toolbar">
    <div><h2>Documentos registrados</h2><p>Consulta y administra guías por sucursal.</p></div>
    <div class="gre-filters">
     <input id="greBuscar" class="form-control" placeholder="Buscar guía, destinatario o RUC/DNI" aria-label="Buscar guías">
     <select id="greEstadoFiltro" class="form-control" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option>BORRADOR</option><option>PREPARADA</option><option>PENDIENTE</option><option>ACEPTADA</option><option>RECHAZADA</option><option>EXCEPCION</option><option>ANULADA</option></select>
     <button class="btn btn-outline-secondary" id="greActualizar"><i class="fas fa-sync-alt"></i></button>
    </div>
   </div>
   <div class="table-responsive"><table class="table gre-table mb-0"><thead><tr><th>Documento</th><th>Fecha de traslado</th><th>Destinatario</th><th>Motivo</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead><tbody id="greFilas"><tr><td colspan="6" class="gre-empty">Cargando guías…</td></tr></tbody></table></div>
   <div class="gre-hint"><i class="fas fa-info-circle"></i> Las guías en estado borrador o preparada <b>no autorizan el traslado</b>. El envío electrónico requiere una integración GRE 2.0 validada.</div>
  </div>
 </section>
</main>
<div class="modal fade gre-modal" id="greModal" tabindex="-1" role="dialog" aria-labelledby="greModalTitulo" aria-hidden="true" data-backdrop="static">
 <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content">
  <div class="modal-header gre-modal-header"><div><span class="gre-eyebrow">GUÍA DE REMISIÓN REMITENTE · 09</span><h4 id="greModalTitulo">Nueva guía</h4><small id="greModalSubtitulo">Complete los datos del traslado.</small></div><button class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button></div>
  <div class="modal-body gre-form" id="greFormulario">
   <input type="hidden" id="greId">
   <div class="gre-form-grid">
    <div class="gre-fields">
     <div class="gre-block"><div class="gre-block-title"><span class="gre-step">1</span>Documento y origen</div>
      <div class="row">
       <div class="col-md-4 form-group"><label>Serie *</label><select id="greSerie" class="form-control" required></select></div>
       <div class="col-md-4 form-group"><label>Almacén de origen</label><select id="greAlmacen" class="form-control"></select></div>
       <div class="col-md-4 form-group"><label>Inicio del traslado *</label><input type="date" id="greFecha" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
       <div class="col-md-6 form-group"><label>Motivo SUNAT *</label><select id="greMotivo" class="form-control" required></select></div>
       <div class="col-md-6 form-group"><label>Modalidad *</label><select id="greModalidad" class="form-control"><option value="02">Transporte privado</option><option value="01">Transporte público</option></select></div>
      </div>
      <div id="greOrigenVenta" class="gre-origin" hidden></div>
     </div>
     <div class="gre-block"><div class="gre-block-title"><span class="gre-step">2</span>Destinatario y direcciones</div>
      <div class="row">
       <div class="col-md-12 form-group"><label>Cliente registrado</label><select id="greCliente" class="form-control"></select></div>
       <div class="col-md-3 form-group"><label>Tipo documento *</label><select id="greDocTipo" class="form-control"><option value="RUC">RUC</option><option value="DNI">DNI</option><option value="CE">Carnet de extranjería</option><option value="PASAPORTE">Pasaporte</option></select></div>
       <div class="col-md-3 form-group"><label>Número *</label><input id="greDocNumero" class="form-control" maxlength="20"></div>
       <div class="col-md-6 form-group"><label>Nombre / razón social *</label><input id="greDestinoNombre" class="form-control" maxlength="180"></div>
       <div class="col-md-8 form-group"><label>Dirección destinatario *</label><input id="greDestinoDireccion" class="form-control" maxlength="255"></div>
       <div class="col-md-4 form-group"><label>Correo (opcional)</label><input id="greEmail" type="email" class="form-control" maxlength="150"></div>
       <div class="col-md-3 form-group"><label>Ubigeo partida *</label><input id="grePartidaUbigeo" class="form-control" inputmode="numeric" maxlength="6" placeholder="150101"></div>
       <div class="col-md-9 form-group"><label>Dirección partida *</label><input id="grePartida" class="form-control" maxlength="255"></div>
       <div class="col-md-3 form-group"><label>Ubigeo llegada *</label><input id="greLlegadaUbigeo" class="form-control" inputmode="numeric" maxlength="6" placeholder="150101"></div>
       <div class="col-md-9 form-group"><label>Dirección llegada *</label><input id="greLlegada" class="form-control" maxlength="255"></div>
      </div>
     </div>
     <div class="gre-block"><div class="gre-block-title"><span class="gre-step">3</span>Transporte</div>
      <div class="row" id="greTransportePrivado">
       <div class="col-md-4 form-group"><label>Vehículo (placa) *</label><input list="grePlacas" id="grePlaca" class="form-control" maxlength="20"><datalist id="grePlacas"></datalist></div>
       <div class="col-md-3 form-group"><label>Doc. conductor *</label><select id="greCondTipo" class="form-control"><option value="DNI">DNI</option><option value="CE">CE</option></select></div>
       <div class="col-md-5 form-group"><label>Número documento *</label><input id="greCondNumero" class="form-control" maxlength="20"></div>
       <div class="col-md-7 form-group"><label>Nombre conductor *</label><input list="greConductores" id="greCondNombre" class="form-control" maxlength="180"><datalist id="greConductores"></datalist></div>
       <div class="col-md-5 form-group"><label>Licencia *</label><input id="greLicencia" class="form-control" maxlength="40"></div>
      </div>
      <div class="row" id="greTransportePublico" hidden>
       <div class="col-md-4 form-group"><label>RUC transportista *</label><input id="greTranspRuc" class="form-control" inputmode="numeric" maxlength="11"></div>
       <div class="col-md-8 form-group"><label>Razón social *</label><input list="greTransportistas" id="greTranspNombre" class="form-control" maxlength="180"><datalist id="greTransportistas"></datalist></div>
      </div>
     </div>
    </div>
    <div class="gre-fields">
     <div class="gre-block gre-product-block"><div class="gre-block-title"><span class="gre-step">4</span>Mercadería <span class="gre-count" id="greItemsCount">0 productos</span></div>
      <div class="gre-products-controls" id="greProductoControls"><select id="greProducto" class="form-control"><option value="">Seleccione un producto</option></select><button type="button" class="btn btn-outline-success" id="greAgregarProducto"><i class="fas fa-plus mr-1"></i> Agregar</button></div>
      <div id="greLineas" class="gre-lines"><div class="gre-lines-empty">Agregue los productos que serán trasladados.</div></div>
      <p class="gre-help">Las variantes y lotes se conservan en el detalle. Una guía de venta no vuelve a descontar stock.</p>
     </div>
     <div class="gre-block"><div class="gre-block-title"><span class="gre-step">5</span>Peso y observaciones</div>
      <div class="row"><div class="col-md-6 form-group"><label>Peso bruto (kg) *</label><input type="number" step="0.001" min="0.001" id="grePeso" class="form-control" placeholder="Ej.: 12.500"></div>
       <div class="col-md-6 form-group"><label>Número de bultos</label><input type="number" min="0" step="1" id="greBultos" class="form-control" placeholder="Opcional"></div>
       <div class="col-12 form-group"><label>Observación</label><textarea id="greObservacion" class="form-control" rows="3" maxlength="500"></textarea></div></div>
     </div>
    </div>
   </div>
  </div>
  <div class="modal-footer gre-modal-footer"><span class="gre-alert"><i class="fas fa-exclamation-triangle"></i> Documento local sin validez tributaria hasta aceptación SUNAT.</span><button class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button><button type="button" id="greGuardar" class="btn gre-btn-primary"><i class="fas fa-save mr-2"></i>Guardar borrador</button></div>
 </div></div>
</div>
<div class="modal fade gre-modal" id="greDetalleModal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header gre-modal-header"><div><span class="gre-eyebrow">Detalle del documento</span><h4 id="greDetalleTitulo">Guía</h4></div><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div><div class="modal-body" id="greDetalleCuerpo"></div><div class="modal-footer" id="greDetalleAcciones"></div></div></div></div>
<script>window.GRE_CONFIG = {csrf: <?= json_encode($tokenGre) ?>, ventaOrigen: <?= (int)$idventaGre ?>};</script>
<?php
require 'footer.php';
?>
<script src="Views/modules/scripts/guiasremision.js?v=<?= (int)@filemtime(__DIR__.'/scripts/guiasremision.js') ?>"></script>
<?php ob_end_flush(); ?>
