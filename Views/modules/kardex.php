<?php
ob_start();
session_start();

if (!isset($_SESSION['nombre'])) {
    header('location: login');
} else {
    require 'header.php';
    require 'sidebar.php';

    if (!empty($_SESSION['almacen']) && $_SESSION['almacen'] == 1) {
        $hoy = date('Y-m-d');
        $inicioMes = date('Y-m-01');
?>
<style>
    .inventory-report { --inv-green: #00ad74; --inv-green-dark: #008f60; --inv-blue: #5268f2; --inv-red: #ef5b65; --inv-orange: #f5a742; --inv-ink: #182033; --inv-muted: #7d879b; --inv-border: #e8edf4; }
    .inventory-report .inv-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:14px; margin-bottom:18px; }
    .inventory-report .inv-title h4 { margin:0 0 4px; color:var(--inv-ink); font-weight:800; font-size:1.42rem; }
    .inventory-report .inv-title p { margin:0; color:var(--inv-muted); }
    .inventory-report .inv-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:6px; margin-left:auto; }
    .inventory-report .inv-actions .btn { border-radius:8px; min-height:32px; padding:5px 9px; font-size:.74rem; line-height:1.1; font-weight:800; background:#fff; box-shadow:0 3px 10px rgba(25,34,54,.04); }
    .inventory-report .inv-actions .btn i { font-size:.76rem; }
    .inventory-report .inv-actions .btn:disabled { opacity:.65; cursor:wait; }
    .inventory-report .filter-card,
    .inventory-report .content-card,
    .inventory-report .chart-card,
    .inventory-report .alert-card { border:1px solid var(--inv-border); box-shadow:0 7px 24px rgba(25,34,54,.045); border-radius:14px; }
    .inventory-report .filter-card .card-body { padding:17px 18px 7px; }
    .inventory-report .filter-card label { font-weight:700; font-size:.76rem; color:#626d82; margin-bottom:6px; }
    .inventory-report .filter-card .form-control { min-height:42px; border-radius:9px; border-color:#e1e7f0; }
    .inventory-report .filter-actions { display:flex; align-items:center; gap:8px; padding-top:25px; }
    .inventory-report .filter-actions .btn { min-height:42px; border-radius:9px; font-weight:700; }
    .inventory-report .metric-card { position:relative; overflow:hidden; border:1px solid var(--inv-border); border-radius:14px; padding:16px 17px; height:100%; background:#fff; box-shadow:0 6px 18px rgba(25,34,54,.035); }
    .inventory-report .metric-card:before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; background:#dce3ed; }
    .inventory-report .metric-card.metric-green:before { background:var(--inv-green); }
    .inventory-report .metric-card.metric-blue:before { background:var(--inv-blue); }
    .inventory-report .metric-card.metric-orange:before { background:var(--inv-orange); }
    .inventory-report .metric-card.metric-red:before { background:var(--inv-red); }
    .inventory-report .metric-top { display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .inventory-report .metric-icon { width:36px; height:36px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:#f4f7fa; color:#718096; }
    .inventory-report .metric-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.035em; color:var(--inv-muted); font-weight:800; margin-bottom:5px; }
    .inventory-report .metric-value { font-size:1.35rem; line-height:1.18; color:var(--inv-ink); font-weight:800; font-variant-numeric:tabular-nums; }
    .inventory-report .metric-note { font-size:.75rem; color:#97a0b0; margin-top:5px; min-height:18px; }
    .inventory-report .nav-pills { gap:5px; }
    .inventory-report .nav-pills .nav-link { border-radius:9px; font-weight:700; color:#596174; padding:9px 14px; }
    .inventory-report .nav-pills .nav-link.active { background:var(--inv-green); color:#fff; box-shadow:0 4px 12px rgba(0,173,116,.18); }
    .inventory-report .summary-heading { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:8px; margin-bottom:14px; }
    .inventory-report .summary-heading h6 { margin:0; color:var(--inv-ink); font-weight:800; }
    .inventory-report .period-badge { background:#f3f6f9; color:#687388; border-radius:999px; padding:6px 10px; font-size:.75rem; font-weight:700; }
    .inventory-report .chart-card { background:#fff; padding:16px 17px; height:100%; }
    .inventory-report .chart-title { font-size:.92rem; font-weight:800; color:var(--inv-ink); margin-bottom:2px; }
    .inventory-report .chart-subtitle { font-size:.74rem; color:#929bad; margin-bottom:13px; }
    .inventory-report .chart-wrap { position:relative; height:265px; }
    .inventory-report .chart-wrap.chart-short { height:235px; }
    .inventory-report .alert-card { background:#fff; padding:16px 17px; height:100%; }
    .inventory-report .alert-list { display:grid; gap:9px; }
    .inventory-report .alert-item { display:flex; align-items:center; justify-content:space-between; gap:12px; border:1px solid #edf1f5; border-radius:11px; padding:11px 12px; background:#fafbfd; }
    .inventory-report .alert-item .left { display:flex; align-items:center; gap:10px; min-width:0; }
    .inventory-report .alert-dot { width:9px; height:9px; border-radius:50%; flex:0 0 auto; background:#a8b0bf; }
    .inventory-report .alert-dot.red { background:var(--inv-red); }
    .inventory-report .alert-dot.orange { background:var(--inv-orange); }
    .inventory-report .alert-dot.green { background:var(--inv-green); }
    .inventory-report .alert-dot.blue { background:var(--inv-blue); }
    .inventory-report .alert-label { font-size:.8rem; color:#596478; font-weight:700; }
    .inventory-report .alert-value { font-size:.9rem; color:var(--inv-ink); font-weight:800; white-space:nowrap; }
    .inventory-report .top-products { margin:0; }
    .inventory-report .top-products td { padding:.68rem .55rem; border-top:1px solid #edf1f5; vertical-align:middle; }
    .inventory-report .top-rank { width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#f2f5f8; color:#657086; font-size:.75rem; font-weight:800; }
    .inventory-report .product-name { font-weight:700; color:#1c263a; }
    .inventory-report .product-meta { color:#8a93a5; font-size:.74rem; margin-top:2px; }
    .inventory-report .money { font-variant-numeric:tabular-nums; font-weight:700; }
    .inventory-report .qty { font-variant-numeric:tabular-nums; }
    .inventory-report .table thead th { background:#f7f8fb; border-bottom:1px solid #e9edf4; color:#555f73; font-size:.72rem; text-transform:uppercase; letter-spacing:.025em; vertical-align:middle; white-space:nowrap; }
    .inventory-report .table td { vertical-align:middle; }
    .inventory-report .table tfoot th { background:#fafbfd; font-weight:800; }
    .inventory-report .reconcile-ok,
    .inventory-report .reconcile-alert { display:inline-flex; align-items:center; gap:5px; border-radius:999px; padding:5px 8px; font-size:.7rem; font-weight:800; white-space:nowrap; }
    .inventory-report .reconcile-ok { background:#ecf9f1; color:#16804b; }
    .inventory-report .reconcile-alert { background:#fff1ed; color:#c24b2f; }
    .inventory-report .kardex-summary { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:10px; margin:13px 0 15px; }
    .inventory-report .kardex-summary > div { border:1px solid var(--inv-border); border-radius:11px; padding:11px 12px; background:#fff; min-width:0; }
    .inventory-report .kardex-summary span { display:block; color:#8992a4; font-size:.67rem; font-weight:800; text-transform:uppercase; }
    .inventory-report .kardex-summary strong { display:block; color:#1d273b; font-size:.98rem; margin-top:4px; word-break:break-word; }
    .inventory-report .kardex-chart-card { border:1px solid var(--inv-border); border-radius:12px; padding:14px; margin-bottom:14px; background:#fff; }
    .inventory-report .kardex-chart-wrap { position:relative; height:220px; }
    .inventory-report .content-card .card-body { padding:17px; }
    .inventory-report .btn-icon-text i { margin-right:5px; }
    .inventory-report .table-responsive { min-height:140px; }
    .inventory-report .dt-buttons { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
    .inventory-report .dt-buttons .btn { margin:0 !important; border-radius:8px !important; padding:7px 11px !important; min-width:auto !important; height:auto !important; line-height:1.2 !important; white-space:nowrap !important; }
    .inventory-report .dataTables_filter input { border:1px solid #e1e7f0 !important; border-radius:8px !important; min-height:34px; }
    .inventory-report .empty-chart { display:flex; height:100%; align-items:center; justify-content:center; color:#9aa3b2; font-size:.82rem; text-align:center; padding:20px; }
    @media (max-width:1199px) { .inventory-report .kardex-summary { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @media (max-width:767px) {
        .inventory-report .inv-toolbar { align-items:flex-start; }
        .inventory-report .inv-actions { width:auto; margin-left:auto; }
        .inventory-report .inv-actions .btn { flex:0 0 auto; }
        .inventory-report .filter-actions { padding-top:0; }
        .inventory-report .chart-wrap { height:240px; }
        .inventory-report .kardex-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width:575px) {
        .inventory-report .metric-value { font-size:1.18rem; }
        .inventory-report .kardex-summary { grid-template-columns:1fr; }
        .inventory-report .nav-pills .nav-item { flex:1 1 48%; }
        .inventory-report .nav-pills .nav-link { text-align:center; padding:9px 8px; }
    }
</style>

<div class="main-content inventory-report" data-report-user="<?= htmlspecialchars((string)($_SESSION['nombre'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8') ?>">
    <section class="section">
        <div class="section-body">
            <div class="inv-toolbar">
                <div class="inv-title">
                    <h4><i class="fas fa-chart-pie mr-2"></i>Inventario valorizado</h4>
                    <p>Resumen ejecutivo, control de stock y Kardex valorizado.</p>
                </div>
                <div class="inv-actions" aria-label="Acciones del reporte">
                    <button type="button" class="btn btn-outline-success" id="btnExportExcel" title="Exportar reporte completo a Excel"><i class="fas fa-file-excel mr-1"></i><span>Excel</span></button>
                    <button type="button" class="btn btn-outline-danger" id="btnExportPdf" title="Exportar reporte completo a PDF"><i class="fas fa-file-pdf mr-1"></i><span>PDF</span></button>
                    <button type="button" class="btn btn-outline-primary" id="btnPrintInventario" title="Imprimir reporte completo"><i class="fas fa-print mr-1"></i><span>Imprimir</span></button>
                </div>
            </div>

            <div class="card filter-card mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-xl-2 col-md-4 col-sm-6">
                            <label for="fecha_inicio">Desde</label>
                            <input type="date" class="form-control" id="fecha_inicio" value="<?= htmlspecialchars($inicioMes) ?>">
                        </div>
                        <div class="form-group col-xl-2 col-md-4 col-sm-6">
                            <label for="fecha_fin">Hasta</label>
                            <input type="date" class="form-control" id="fecha_fin" value="<?= htmlspecialchars($hoy) ?>">
                        </div>
                        <div class="form-group col-xl-2 col-md-4 col-sm-6">
                            <label for="idalmacen">Almacén</label>
                            <select class="form-control" id="idalmacen"><option value="0">Todos</option></select>
                        </div>
                        <div class="form-group col-xl-2 col-md-4 col-sm-6">
                            <label for="idcategoria">Categoría</label>
                            <select class="form-control" id="idcategoria"><option value="0">Todas</option></select>
                        </div>
                        <div class="form-group col-xl-2 col-md-4 col-sm-6">
                            <label for="idsubcategoria">Subcategoría</label>
                            <select class="form-control" id="idsubcategoria"><option value="0">Todas</option></select>
                        </div>
                        <div class="form-group col-xl-2 col-md-4 col-sm-6">
                            <label for="buscarInventario">Producto / SKU</label>
                            <input type="text" class="form-control" id="buscarInventario" placeholder="Buscar...">
                        </div>
                    </div>
                    <div class="filter-actions justify-content-end pb-2">
                        <button type="button" class="btn btn-light" id="btnLimpiarFiltros"><i class="fas fa-undo mr-1"></i> Limpiar</button>
                        <button type="button" class="btn btn-success" id="btnAplicarFiltros"><i class="fas fa-filter mr-1"></i> Aplicar filtros</button>
                    </div>
                </div>
            </div>

            <div class="row mb-1">
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="metric-card metric-green">
                        <div class="metric-top"><div><div class="metric-label">Valor inventario</div><div class="metric-value" id="metricValorActual">S/ 0.00</div></div><span class="metric-icon"><i class="fas fa-coins"></i></span></div>
                        <div class="metric-note">Saldo valorizado actual</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="metric-card metric-blue">
                        <div class="metric-top"><div><div class="metric-label">Saldo actual</div><div class="metric-value" id="metricSaldoActual">0 und.</div></div><span class="metric-icon"><i class="fas fa-cubes"></i></span></div>
                        <div class="metric-note">Existencia física registrada</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="metric-card metric-green">
                        <div class="metric-top"><div><div class="metric-label">Entradas</div><div class="metric-value" id="metricEntradas">0 und.</div></div><span class="metric-icon"><i class="fas fa-arrow-down"></i></span></div>
                        <div class="metric-note" id="metricValorEntradas">S/ 0.00 valorizado</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="metric-card metric-orange">
                        <div class="metric-top"><div><div class="metric-label">Salidas</div><div class="metric-value" id="metricSalidas">0 und.</div></div><span class="metric-icon"><i class="fas fa-arrow-up"></i></span></div>
                        <div class="metric-note" id="metricValorSalidas">S/ 0.00 valorizado</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="metric-card metric-red">
                        <div class="metric-top"><div><div class="metric-label">Sin stock</div><div class="metric-value" id="metricSinStock">0</div></div><span class="metric-icon"><i class="fas fa-box-open"></i></span></div>
                        <div class="metric-note">Productos con saldo ≤ 0</div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="metric-card metric-green">
                        <div class="metric-top"><div><div class="metric-label">Conciliación FIFO</div><div class="metric-value" id="metricConciliacion">100%</div></div><span class="metric-icon"><i class="fas fa-check-double"></i></span></div>
                        <div class="metric-note" id="metricDiferencias">0 diferencias</div>
                    </div>
                </div>
            </div>

            <div class="card content-card">
                <div class="card-body">
                    <ul class="nav nav-pills mb-4" id="inventoryTabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tabResumen" role="tab"><i class="fas fa-chart-line mr-1"></i> Resumen</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabProductos" role="tab"><i class="fas fa-box mr-1"></i> Productos</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabCategorias" role="tab"><i class="fas fa-layer-group mr-1"></i> Categorías / Subcategorías</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabKardex" role="tab"><i class="fas fa-exchange-alt mr-1"></i> Kardex valorizado</a></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tabResumen" role="tabpanel">
                            <div class="summary-heading">
                                <h6>Panorama del inventario</h6>
                                <span class="period-badge" id="periodoResumen">Período actual</span>
                            </div>

                            <div class="row">
                                <div class="col-xl-6 mb-3">
                                    <div class="chart-card">
                                        <div class="chart-title">Valor del inventario por categoría</div>
                                        <div class="chart-subtitle">Distribución del saldo valorizado actual.</div>
                                        <div class="chart-wrap"><canvas id="chartCategorias"></canvas><div class="empty-chart d-none" id="emptyChartCategorias">No hay datos valorizados para graficar.</div></div>
                                    </div>
                                </div>
                                <div class="col-xl-6 mb-3">
                                    <div class="chart-card">
                                        <div class="chart-title">Entradas vs. salidas</div>
                                        <div class="chart-subtitle">Movimiento diario de unidades dentro del período seleccionado.</div>
                                        <div class="chart-wrap"><canvas id="chartMovimientos"></canvas><div class="empty-chart d-none" id="emptyChartMovimientos">No hay movimientos en el período.</div></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-4 mb-3">
                                    <div class="chart-card">
                                        <div class="chart-title">Participación por subcategoría</div>
                                        <div class="chart-subtitle">Composición del valor actual del inventario.</div>
                                        <div class="chart-wrap chart-short"><canvas id="chartSubcategorias"></canvas><div class="empty-chart d-none" id="emptyChartSubcategorias">No hay subcategorías valorizadas.</div></div>
                                    </div>
                                </div>
                                <div class="col-xl-5 mb-3">
                                    <div class="chart-card">
                                        <div class="chart-title">Top 10 productos por valor</div>
                                        <div class="chart-subtitle">Productos que concentran mayor saldo valorizado.</div>
                                        <div class="table-responsive" style="min-height:0;">
                                            <table class="table top-products" id="tablaTopProductos">
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 mb-3">
                                    <div class="alert-card">
                                        <div class="chart-title">Alertas de inventario</div>
                                        <div class="chart-subtitle">Situaciones que requieren revisión.</div>
                                        <div class="alert-list">
                                            <div class="alert-item"><div class="left"><span class="alert-dot red"></span><span class="alert-label">Sin stock</span></div><span class="alert-value" id="alertSinStock">0</span></div>
                                            <div class="alert-item"><div class="left"><span class="alert-dot orange"></span><span class="alert-label">Stock negativo</span></div><span class="alert-value" id="alertNegativos">0</span></div>
                                            <div class="alert-item"><div class="left"><span class="alert-dot blue"></span><span class="alert-label">Diferencias FIFO</span></div><span class="alert-value" id="alertDiferencias">0</span></div>
                                            <div class="alert-item"><div class="left"><span class="alert-dot green"></span><span class="alert-label">Sin movimiento</span></div><span class="alert-value" id="alertSinMovimiento">0</span></div>
                                        </div>
                                        <small class="d-block text-muted mt-3">“Sin movimiento” considera productos sin entradas ni salidas en el rango consultado.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tabProductos" role="tabpanel">
                            <div class="table-responsive">
                                <table id="tablaInventario" class="table table-hover table-striped" style="width:100%">
                                    <thead><tr><th>Código / Producto</th><th>Categoría</th><th>Subcategoría</th><th>Almacén</th><th>Saldo inicial</th><th>Entradas</th><th>Salidas</th><th>Saldo actual</th><th>Costo actual</th><th>Saldo valorizado</th><th>Conciliación</th><th>Acción</th></tr></thead>
                                    <tbody></tbody>
                                    <tfoot><tr><th colspan="4">TOTAL</th><th id="footSaldoInicial">0</th><th id="footEntradas">0</th><th id="footSalidas">0</th><th id="footSaldoActual">0</th><th></th><th id="footValorizado">S/ 0.00</th><th colspan="2"></th></tr></tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tabCategorias" role="tabpanel">
                            <div class="table-responsive">
                                <table id="tablaCategorias" class="table table-hover" style="width:100%">
                                    <thead><tr><th>Categoría</th><th>Subcategoría</th><th>Productos</th><th>Saldo inicial</th><th>Entradas</th><th>Salidas</th><th>Saldo actual</th><th>Saldo valorizado</th><th>Diferencias</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tabKardex" role="tabpanel">
                            <div class="row align-items-end mb-1">
                                <div class="form-group col-lg-8 col-md-7">
                                    <label for="idarticuloKardex">Producto</label>
                                    <select class="form-control" id="idarticuloKardex"><option value="">Seleccione un producto</option></select>
                                </div>
                                <div class="form-group col-lg-4 col-md-5">
                                    <button type="button" class="btn btn-success btn-block btn-icon-text" id="btnVerKardex"><i class="fas fa-search"></i> Ver Kardex</button>
                                </div>
                            </div>
                            <div id="kardexCabecera"></div>
                            <div class="kardex-chart-card d-none" id="kardexChartCard">
                                <div class="chart-title">Evolución del saldo del producto</div>
                                <div class="chart-subtitle">Entradas, salidas y saldo acumulado por movimiento.</div>
                                <div class="kardex-chart-wrap"><canvas id="chartKardex"></canvas></div>
                            </div>
                            <div class="table-responsive">
                                <table id="tablaKardex" class="table table-hover table-striped" style="width:100%">
                                    <thead><tr><th rowspan="2">Fecha</th><th rowspan="2">Documento / Detalle</th><th colspan="3" class="text-center">Entrada</th><th colspan="3" class="text-center">Salida</th><th colspan="3" class="text-center">Saldo</th></tr><tr><th>Cantidad</th><th>Costo U.</th><th>Total</th><th>Cantidad</th><th>Costo U.</th><th>Total</th><th>Cantidad</th><th>Costo U.</th><th>Total</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php
    } else {
        require 'access.php';
    }

    require 'footer.php';
?>
<script src="Assets/bundles/chartjs/chart.min.js"></script>
<script src="Views/modules/scripts/kardex.js?v=20261007-export-premium"></script>
<?php
}
ob_end_flush();
?>
