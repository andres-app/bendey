<?php

ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Lima');

if (!isset($_SESSION['nombre'])) {
    header('Location: login');
    exit;
}

require 'header.php';
require 'sidebar.php';

if ((int)($_SESSION['ventas'] ?? 0) !== 1) {
    require 'access.php';
    require 'footer.php';
    ob_end_flush();
    exit;
}

$fechaHoy = date('Y-m-d');
?>

<style>
    .rb-page { padding-top: 18px; }
    .rb-hero {
        display:flex; align-items:flex-start; justify-content:space-between; gap:18px;
        margin-bottom:16px; padding:20px 22px; border:1px solid #e5ebe7; border-radius:18px;
        background:linear-gradient(135deg,#fff 0%,#f8fbf9 100%); box-shadow:0 8px 24px rgba(15,23,42,.05);
    }
    .rb-hero h4 { margin:0 0 5px; color:#26352d; font-size:1.15rem; font-weight:700; }
    .rb-hero p { margin:0; color:#7d8982; font-size:.78rem; }
    .rb-hero-icon {
        width:46px;height:46px;flex:0 0 46px;display:inline-flex;align-items:center;justify-content:center;
        border-radius:14px;color:#2f8d4d;background:#eaf7ee;font-size:1.05rem;
    }
    .rb-metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
    .rb-metric { padding:15px 16px;border:1px solid #e6ebe8;border-radius:14px;background:#fff; }
    .rb-metric small { display:block;color:#8a958f;font-size:.68rem;margin-bottom:5px; }
    .rb-metric strong { color:#2c3931;font-size:1.18rem;font-weight:700; }
    .rb-shell { border:1px solid #e4eae6;border-radius:18px;background:#fff;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.045); }
    .rb-tabs { display:flex;gap:4px;padding:8px;border-bottom:1px solid #e8edea;background:#fafcfb; }
    .rb-tab { border:0;border-radius:10px;padding:10px 14px;color:#66746c;background:transparent;font-size:.78rem;font-weight:500;cursor:pointer; }
    .rb-tab.active { color:#276d3d;background:#eaf6ed; }
    .rb-panel { display:none; }
    .rb-panel.active { display:block; }
    .rb-toolbar { display:flex;align-items:end;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid #edf1ee; }
    .rb-filter { width:min(240px,100%); }
    .rb-filter label { display:block;margin-bottom:5px;color:#66746c;font-size:.7rem;font-weight:600; }
    .rb-filter .form-control { min-height:39px;border-radius:9px;border-color:#dce4df; }
    .rb-actions { display:flex;align-items:center;gap:8px;flex-wrap:wrap; }
    .rb-btn { min-height:39px;border-radius:9px;font-weight:400; }
    .rb-table-wrap { overflow-x:auto; }
    .rb-table { width:100%;margin:0; }
    .rb-table th { border-top:0;background:#fbfcfb;color:#7c8981;font-size:.67rem;font-weight:650;letter-spacing:.02em;text-transform:uppercase;white-space:nowrap; }
    .rb-table td { vertical-align:middle;color:#45524a;font-size:.76rem;border-color:#edf1ee; }
    .rb-doc strong { display:block;color:#2c3931;font-size:.79rem; }
    .rb-doc small { color:#98a29c;font-size:.66rem; }
    .rb-state { display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:.65rem;font-weight:650;white-space:nowrap; }
    .rb-state-pending { color:#805c17;background:#fff5d9; }
    .rb-state-ok { color:#25643a;background:#eaf6ed; }
    .rb-state-error { color:#9a3737;background:#fff0f0; }
    .rb-state-process { color:#375d91;background:#edf4ff; }
    .rb-footer {
        display:flex;align-items:center;justify-content:space-between;gap:14px;padding:13px 18px;border-top:1px solid #e8edea;background:#fbfcfb;
    }
    .rb-footer-summary { color:#738078;font-size:.72rem; }
    .rb-footer-summary strong { color:#2d3a32; }
    .rb-empty { padding:46px 20px;text-align:center;color:#87938c; }
    .rb-empty i { display:block;margin-bottom:10px;color:#c5cec8;font-size:2rem; }
    .rb-note { margin:14px 18px 18px;padding:11px 13px;border-left:3px solid #6f8bd8;border-radius:0 10px 10px 0;background:#f7f9ff;color:#667085;font-size:.71rem;line-height:1.45; }
    .rb-modal-total { font-size:1rem;font-weight:700;color:#2c3931; }
    @media (max-width:767.98px) {
        .rb-page { padding-top:8px; }
        .rb-hero { padding:16px; }
        .rb-hero-icon { display:none; }
        .rb-metrics { grid-template-columns:1fr; gap:8px; }
        .rb-toolbar { align-items:stretch;flex-direction:column; }
        .rb-filter { width:100%; }
        .rb-actions { width:100%; }
        .rb-actions .btn { flex:1 1 auto; }
        .rb-footer { align-items:stretch;flex-direction:column; }
        .rb-footer .btn { width:100%; }
    }
</style>

<div class="main-content rb-page">
    <section class="section">
        <div class="section-body">
            <div class="rb-hero">
                <div>
                    <h4>Resumen Diario de Boletas</h4>
                    <p>Agrupa las boletas configuradas para Resumen Diario y conserva la trazabilidad de cada RC.</p>
                </div>
                <span class="rb-hero-icon"><i class="fas fa-file-invoice"></i></span>
            </div>

            <div class="rb-metrics">
                <div class="rb-metric"><small>Boletas pendientes</small><strong id="rbMetricPendientes">0</strong></div>
                <div class="rb-metric"><small>Total pendiente</small><strong id="rbMetricTotal">S/ 0.00</strong></div>
                <div class="rb-metric"><small>Resúmenes registrados</small><strong id="rbMetricResumenes">0</strong></div>
            </div>

            <div class="rb-shell">
                <div class="rb-tabs" role="tablist">
                    <button type="button" class="rb-tab active" data-rb-tab="pendientes">Boletas pendientes</button>
                    <button type="button" class="rb-tab" data-rb-tab="resumenes">Resúmenes</button>
                </div>

                <div class="rb-panel active" data-rb-panel="pendientes">
                    <div class="rb-toolbar">
                        <div class="rb-filter">
                            <label for="rbFecha">Fecha de las boletas</label>
                            <input type="date" class="form-control" id="rbFecha" value="<?= htmlspecialchars($fechaHoy, ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars($fechaHoy, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="rb-actions">
                            <button type="button" class="btn btn-outline-secondary rb-btn" id="rbActualizarPendientes"><i class="fas fa-sync-alt mr-1"></i> Actualizar</button>
                        </div>
                    </div>

                    <div class="rb-table-wrap">
                        <table class="table rb-table" id="rbTablaPendientes">
                            <thead>
                                <tr>
                                    <th style="width:42px"><input type="checkbox" id="rbSeleccionarTodos" aria-label="Seleccionar todas"></th>
                                    <th>Comprobante</th>
                                    <th>Cliente</th>
                                    <th>Fecha</th>
                                    <th class="text-right">Total</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="rbPendientesBody"></tbody>
                        </table>
                    </div>

                    <div id="rbPendientesVacio" class="rb-empty" style="display:none;">
                        <i class="far fa-check-circle"></i>
                        No hay boletas pendientes de Resumen Diario para esta fecha.
                    </div>

                    <div class="rb-footer">
                        <div class="rb-footer-summary"><strong id="rbSeleccionadas">0</strong> seleccionada(s) · <strong id="rbTotalSeleccionado">S/ 0.00</strong></div>
                        <button type="button" class="btn btn-success rb-btn" id="rbCrearResumen" disabled><i class="fas fa-layer-group mr-1"></i> Crear Resumen Diario</button>
                    </div>
                </div>

                <div class="rb-panel" data-rb-panel="resumenes">
                    <div class="rb-toolbar">
                        <div>
                            <strong style="font-size:.82rem;color:#344239;">Historial de RC</strong>
                            <div style="font-size:.68rem;color:#919b95;margin-top:2px;">Incluye preparados, enviados y estados posteriores.</div>
                        </div>
                        <div class="rb-actions">
                            <button type="button" class="btn btn-outline-secondary rb-btn" id="rbActualizarResumenes"><i class="fas fa-sync-alt mr-1"></i> Actualizar</button>
                        </div>
                    </div>

                    <div class="rb-table-wrap">
                        <table class="table rb-table">
                            <thead>
                                <tr>
                                    <th>Resumen</th>
                                    <th>Fecha documentos</th>
                                    <th class="text-center">Boletas</th>
                                    <th class="text-right">Total</th>
                                    <th>Ticket</th>
                                    <th>Estado</th>
                                    <th class="text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="rbResumenesBody"></tbody>
                        </table>
                    </div>
                    <div id="rbResumenesVacio" class="rb-empty" style="display:none;">
                        <i class="far fa-folder-open"></i>
                        Todavía no se ha creado ningún Resumen Diario.
                    </div>

                    <div class="rb-note">
                        <i class="fas fa-info-circle mr-1"></i>
                        Esta fase prepara y administra el RC en TiquePOS. El envío real a APISUNAT se habilitará cuando se confirme el endpoint específico de Resumen Diario; no se reutiliza ni se inventa la ruta de envío individual de comprobantes.
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="rbDetalleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="rbDetalleTitulo">Detalle del resumen</h5>
                    <small class="text-muted" id="rbDetalleSubtitulo"></small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table rb-table mb-0">
                        <thead><tr><th>Boleta</th><th>Cliente</th><th>Fecha</th><th class="text-right">Total</th></tr></thead>
                        <tbody id="rbDetalleBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="text-muted small" id="rbDetalleCantidad"></span>
                <span class="rb-modal-total" id="rbDetalleTotal">S/ 0.00</span>
            </div>
        </div>
    </div>
</div>

<?php
require 'footer.php';
$rutaJs = __DIR__ . '/scripts/resumenboletas.js';
$versionJs = is_file($rutaJs) ? filemtime($rutaJs) : time();
?>
<script src="Views/modules/scripts/resumenboletas.js?v=<?= (int)$versionJs ?>"></script>
<?php ob_end_flush(); ?>
