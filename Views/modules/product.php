<?php
ob_start();
session_start();

if (!isset($_SESSION['nombre'])) {
    header('Location: login');
    exit;
}

require 'header.php';
require 'sidebar.php';

if ((int)($_SESSION['almacen'] ?? 0) === 1) {
?>
<!-- Tailwind aislado para Productos. Preflight desactivado para no interferir con Bootstrap/Stisla. -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        prefix: 'tw-',
        corePlugins: { preflight: false },
        theme: {
            extend: {
                colors: {
                    tique: {
                        50: '#ecfdf6',
                        100: '#d7f7e9',
                        200: '#adebd2',
                        300: '#72d9b3',
                        400: '#31c18e',
                        500: '#00a46a',
                        600: '#008d5b',
                        700: '#00754d',
                        800: '#00603f',
                        900: '#004f35'
                    }
                },
                boxShadow: {
                    'product-command': '0 18px 48px rgba(15, 23, 42, .075)',
                    'product-filter': '0 10px 24px rgba(15, 23, 42, .08)'
                }
            }
        }
    };
</script>
<style>
    :root {
        --tp-product-green: #00a46a;
        --tp-product-green-dark: #00754d;
        --tp-product-ink: #17212b;
        --tp-product-muted: #75808e;
        --tp-product-line: #e7ebef;
        --tp-product-soft: #f6f8f9;
        --tp-product-shadow: 0 12px 34px rgba(15, 23, 42, .07);
    }

    .tp-products-page { color: var(--tp-product-ink); }

    .tp-products-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 16px;
        padding: 4px 1px;
    }

    .tp-products-hero h1 {
        margin: 0 0 4px;
        font-size: 1.45rem;
        font-weight: 760;
        letter-spacing: -.025em;
    }

    .tp-products-hero p {
        margin: 0;
        color: var(--tp-product-muted);
        font-size: .82rem;
    }

    .tp-products-actions { display: flex; align-items: center; gap: 9px; }

    .tp-btn-primary,
    .tp-btn-secondary {
        min-height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 15px;
        border-radius: 10px;
        font-size: .78rem;
        font-weight: 700;
    }

    .tp-btn-primary {
        border: 1px solid var(--tp-product-green);
        color: #fff;
        background: var(--tp-product-green);
        box-shadow: 0 8px 18px rgba(0, 155, 84, .18);
    }

    .tp-btn-primary:hover,
    .tp-btn-primary:focus { color: #fff; background: var(--tp-product-green-dark); }

    .tp-btn-secondary {
        border: 1px solid #dce2e7;
        color: #4d5965;
        background: #fff;
    }

    .tp-product-summary {
        display: flex;
        align-items: center;
        gap: 7px;
        flex-wrap: wrap;
        margin-bottom: 13px;
    }

    .tp-summary-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 33px;
        padding: 0 11px;
        border: 1px solid var(--tp-product-line);
        border-radius: 999px;
        color: #596572;
        background: #fff;
        font-size: .72rem;
        font-weight: 650;
    }

    .tp-summary-chip strong { color: #26313b; font-size: .78rem; }
    .tp-summary-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--tp-product-green); }
    .tp-summary-dot.warning { background: #e8a317; }
    .tp-summary-dot.danger { background: #de5261; }

    .tp-products-card {
        overflow: visible;
        border: 1px solid rgba(224, 229, 234, .95);
        border-radius: 18px;
        background: #fff;
        box-shadow: var(--tp-product-shadow);
    }

    .tp-products-toolbar {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) repeat(4, minmax(125px, auto)) auto;
        gap: 9px;
        align-items: center;
        padding: 14px;
        border-bottom: 1px solid var(--tp-product-line);
    }

    .tp-search-box { position: relative; }
    .tp-search-box i {
        position: absolute;
        top: 50%; left: 13px;
        transform: translateY(-50%);
        color: #9aa3ad;
        pointer-events: none;
    }

    .tp-search-box input,
    .tp-products-toolbar select {
        width: 100%; min-height: 39px;
        border: 1px solid #dfe4e9;
        border-radius: 10px;
        color: #34404b;
        background: #fff;
        box-shadow: none;
        font-size: .76rem;
    }

    .tp-search-box input { padding: 0 12px 0 38px; }
    .tp-products-toolbar select { padding: 0 28px 0 10px; }

    .tp-view-toggle {
        display: inline-flex;
        padding: 3px;
        border: 1px solid #dfe4e9;
        border-radius: 10px;
        background: #f7f8fa;
    }

    .tp-view-toggle button {
        width: 34px; height: 31px;
        border: 0;
        border-radius: 7px;
        color: #8a949e;
        background: transparent;
    }

    .tp-view-toggle button.active {
        color: var(--tp-product-green-dark);
        background: #fff;
        box-shadow: 0 2px 7px rgba(15,23,42,.08);
    }

    .tp-products-result-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 10px 15px;
        color: #7b8691;
        background: #fbfcfc;
        border-bottom: 1px solid #edf0f2;
        font-size: .71rem;
    }

    .tp-products-table-wrap { padding: 0 14px 13px; overflow: visible; }
    #tbllistado { margin: 0 !important; border-collapse: separate !important; border-spacing: 0 8px !important; }
    #tbllistado thead th {
        padding: 12px 11px;
        border: 0;
        color: #84909b;
        background: transparent;
        font-size: .65rem;
        font-weight: 760;
        letter-spacing: .045em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    #tbllistado tbody tr { box-shadow: 0 0 0 1px #edf0f2; }
    #tbllistado tbody tr:hover { box-shadow: 0 0 0 1px #d9e1e6, 0 7px 17px rgba(15,23,42,.05); }
    #tbllistado tbody tr.tp-product-inactive { opacity: .66; }
    #tbllistado tbody td {
        padding: 11px;
        border: 0;
        color: #46515d;
        background: #fff;
        font-size: .76rem;
        vertical-align: middle;
    }
    #tbllistado tbody td:first-child { border-radius: 11px 0 0 11px; }
    #tbllistado tbody td:last-child { border-radius: 0 11px 11px 0; }

    .tp-product-cell { display: flex; align-items: center; gap: 11px; min-width: 250px; }
    .tp-product-thumb {
        width: 48px; height: 48px; flex: 0 0 48px;
        overflow: hidden;
        border: 1px solid #e6eaee;
        border-radius: 11px;
        background: #f4f6f7;
    }
    .tp-product-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .tp-product-cell strong { display: block; color: #27323d; font-size: .82rem; line-height: 1.25; }
    .tp-product-cell small { display: block; margin-top: 3px; color: #8c96a0; font-size: .68rem; }
    .tp-product-cell .tp-variant-hint { color: #64748b; }

    .tp-category-cell strong { display: block; color: #3f4a55; font-size: .76rem; }
    .tp-category-cell small { display: block; margin-top: 2px; color: #919ba5; font-size: .66rem; }

    .tp-stock-wrap { min-width: 96px; }
    .tp-stock-wrap strong { display: block; color: #34404b; font-size: .82rem; }
    .tp-stock-state { display: inline-flex; align-items: center; gap: 5px; margin-top: 3px; color: #7f8994; font-size: .64rem; }
    .tp-stock-state::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: #3dac72; }
    .tp-stock-low::before { background: #e5a21a; }
    .tp-stock-out::before { background: #dc5663; }

    .tp-price-cell strong { display: block; color: #26313b; font-size: .87rem; }
    .tp-price-cell small { color: #919ba5; font-size: .64rem; }

    .afectacion-producto-pill,
    .tp-state-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 8px;
        border-radius: 999px;
        font-size: .65rem;
        font-weight: 730;
        white-space: nowrap;
    }
    .afectacion-10 { color:#176b3a; background:#edf9f1; border:1px solid #bce2c8; }
    .afectacion-20 { color:#8a5b16; background:#fff8e8; border:1px solid #eed7a3; }
    .afectacion-30 { color:#475467; background:#f2f4f7; border:1px solid #dfe3e8; }
    .afectacion-40 { color:#175cd3; background:#eff6ff; border:1px solid #bfd8ff; }
    .tp-state-active { color:#176b3a; background:#edf9f1; border:1px solid #c5e7cf; }
    .tp-state-inactive { color:#8b3440; background:#fff1f2; border:1px solid #f2c5ca; }

    .tp-row-actions { display: inline-flex; justify-content: flex-end; align-items: center; gap: 6px; min-width: 116px; }
    .tp-row-edit {
        min-height: 33px; padding: 0 11px;
        border: 1px solid #dce2e7; border-radius: 8px;
        color: #45515d; background: #fff;
        font-size: .69rem; font-weight: 700;
    }
    .tp-row-more {
        width: 34px; height: 33px;
        border: 1px solid #dce2e7; border-radius: 8px;
        color: #65717d; background: #fff;
    }
    .tp-row-actions .dropdown-menu { min-width: 188px; padding: 6px; border: 1px solid #e2e6ea; border-radius: 11px; box-shadow: 0 13px 30px rgba(15,23,42,.14); }
    .tp-row-actions .dropdown-item { padding: 8px 10px; border-radius: 7px; color: #4f5a65; font-size: .72rem; }
    .tp-row-actions .dropdown-item i { width: 19px; color: #87919b; }

    .tp-products-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        padding: 14px;
    }

    .tp-product-grid-card {
        position: relative;
        overflow: visible;
        border: 1px solid #e4e9ed;
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 7px 19px rgba(15,23,42,.045);
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .tp-product-grid-card:hover { transform: translateY(-2px); box-shadow: 0 13px 28px rgba(15,23,42,.09); }
    .tp-product-grid-card.is-inactive { opacity: .64; }
    .tp-grid-image { height: 148px; overflow: hidden; border-radius: 14px 14px 0 0; background: #f3f5f6; }
    .tp-grid-image img { width: 100%; height: 100%; object-fit: cover; }
    .tp-grid-body { padding: 12px; }
    .tp-grid-name { min-height: 38px; margin: 0; color: #26313b; font-size: .82rem; font-weight: 740; line-height: 1.35; }
    .tp-grid-sku { margin-top: 3px; color: #919ba5; font-size: .65rem; }
    .tp-grid-meta { display: flex; align-items: flex-end; justify-content: space-between; gap: 9px; margin-top: 13px; }
    .tp-grid-price { color: #222e38; font-size: 1rem; font-weight: 780; }
    .tp-grid-stock { color: #75808b; font-size: .67rem; text-align: right; }
    .tp-grid-footer { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 11px; padding-top: 10px; border-top: 1px solid #edf0f2; }
    .tp-grid-actions { display: flex; gap: 5px; }
    .tp-grid-actions button { height: 31px; border-radius: 8px; font-size: .68rem; }

    .tp-grid-more-wrap { display: flex; justify-content: center; padding: 0 14px 16px; }

    .tp-import-panel {
        display: none;
        position: relative;
        margin: 0 0 18px;
        border: 1px solid #e2e8f0;
        border-radius: 22px;
        background: #fff;
        box-shadow: 0 18px 50px rgba(15, 23, 42, .08);
        overflow: hidden;
    }
    .tp-import-panel.is-open { display: block; }
    .tp-import-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px 14px;
        border-bottom: 1px solid #edf1f4;
        background: linear-gradient(135deg, #ffffff 0%, #f1fcf7 100%);
    }
    .tp-import-title { display:flex; align-items:flex-start; gap:12px; min-width:0; }
    .tp-import-title-icon {
        display:grid; place-items:center; flex:0 0 42px; width:42px; height:42px;
        border-radius:14px; color:#008d5b; background:#e9fbf3; font-size:1rem;
    }
    .tp-import-panel h5 { margin: 1px 0 4px; color:#17212b; font-size:1rem; font-weight:760; letter-spacing:-.015em; }
    .tp-import-panel p { margin: 0; color:#728091; font-size:.73rem; line-height:1.45; }
    .tp-import-close {
        display:grid; place-items:center; width:36px; height:36px; border:0; border-radius:12px;
        color:#64748b; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.06); cursor:pointer;
    }
    .tp-import-close:hover { color:#00754d; background:#effaf5; transform:translateY(-1px); }
    .tp-import-close:active { transform:scale(.96); }
    .tp-import-close:focus, .tp-import-close:focus-visible {
        outline:none !important; color:#00754d; background:#effaf5;
        box-shadow:0 0 0 4px rgba(0,164,106,.11) !important;
    }
    .tp-import-toolbar {
        display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
        padding:12px 20px; border-bottom:1px solid #edf1f4; background:#fff;
    }
    .tp-import-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .tp-import-action {
        display:inline-flex; align-items:center; gap:8px; min-height:42px; padding:0 13px 0 8px;
        border:1px solid #e2e8f0; border-radius:13px; background:#fff; color:#475569;
        font-size:.72rem; font-weight:650; cursor:pointer; text-decoration:none !important;
        box-shadow:0 4px 12px rgba(15,23,42,.035);
        transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease, background-color .16s ease, color .16s ease;
        -webkit-tap-highlight-color:transparent;
    }
    .tp-import-action > i {
        display:inline-grid; place-items:center; width:28px; height:28px; flex:0 0 28px;
        border-radius:9px; color:#64748b; background:#f1f5f9; font-size:.72rem;
        transition:transform .16s ease, color .16s ease, background-color .16s ease;
    }
    .tp-import-action:hover {
        transform:translateY(-2px); border-color:#b8e3d1; color:#00754d; background:#fbfffd;
        box-shadow:0 9px 20px rgba(15,23,42,.065);
    }
    .tp-import-action:hover > i { transform:scale(1.05); color:#00754d; background:#e8f9f1; }
    .tp-import-action:active { transform:translateY(0) scale(.985); box-shadow:0 3px 9px rgba(15,23,42,.045); }
    .tp-import-action:focus,
    .tp-import-action:focus-visible {
        outline:none !important; border-color:#75d3ad !important;
        box-shadow:0 0 0 4px rgba(0,164,106,.11), 0 8px 18px rgba(15,23,42,.05) !important;
    }
    .tp-import-action.is-primary {
        border-color:#00a46a; background:#00a46a; color:#fff;
        box-shadow:0 9px 20px rgba(0,164,106,.18);
    }
    .tp-import-action.is-primary > i { color:#fff; background:rgba(255,255,255,.16); }
    .tp-import-action.is-primary:hover { border-color:#008d5b; background:#008d5b; color:#fff; box-shadow:0 12px 24px rgba(0,164,106,.23); }
    .tp-import-action.is-primary:hover > i { color:#fff; background:rgba(255,255,255,.2); }
    .tp-import-file { display:none; }
    .tp-import-kpis { display:flex; align-items:center; gap:7px; flex-wrap:wrap; }
    .tp-import-kpi {
        display:inline-flex; align-items:center; gap:6px; min-height:30px; padding:0 9px;
        border:1px solid #e4e9ed; border-radius:999px; background:#fff; color:#64748b; font-size:.67rem;
    }
    .tp-import-kpi strong { color:#17212b; font-size:.75rem; }
    .tp-import-kpi.is-valid { background:#effbf5; border-color:#d6f1e4; color:#00754d; }
    .tp-import-kpi.is-error { background:#fff5f5; border-color:#ffe0e0; color:#c23946; }
    .tp-sheet-help {
        display:flex; align-items:center; gap:8px; padding:9px 20px; background:#f8fafc;
        color:#64748b; font-size:.68rem; border-bottom:1px solid #edf1f4;
    }
    .tp-sheet-help i { color:#00a46a; }
    .tp-sheet-wrap { overflow:auto; max-height:54vh; background:#fff; }
    .tp-sheet { width:100%; min-width:1120px; border-collapse:separate; border-spacing:0; table-layout:fixed; }
    .tp-sheet th {
        position:sticky; top:0; z-index:6; height:42px; padding:0 9px; border-right:1px solid #e7ecef; border-bottom:1px solid #dfe6ea;
        background:#f7f9fa; color:#536174; font-size:.66rem; font-weight:750; text-align:left; letter-spacing:.015em;
    }
    .tp-sheet th:first-child { left:0; z-index:7; width:54px; text-align:center; }
    .tp-sheet td { height:43px; padding:0; border-right:1px solid #edf1f4; border-bottom:1px solid #edf1f4; background:#fff; vertical-align:middle; }
    .tp-sheet td:first-child { position:sticky; left:0; z-index:3; width:54px; background:#f9fbfb; }
    .tp-sheet-rownum { display:flex; align-items:center; justify-content:center; gap:5px; color:#94a3b8; font-size:.66rem; }
    .tp-sheet-rownum .tp-row-state { width:7px; height:7px; border-radius:999px; background:#cbd5e1; }
    .tp-sheet tr.is-valid .tp-row-state { background:#00a46a; box-shadow:0 0 0 3px rgba(0,164,106,.09); }
    .tp-sheet tr.has-error .tp-row-state { background:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.08); }
    .tp-sheet-cell, .tp-sheet-select {
        width:100%; height:42px; margin:0; padding:0 9px; border:0; outline:0; border-radius:0;
        background:transparent; color:#17212b; font-size:.72rem; box-shadow:none !important;
    }
    .tp-sheet-cell:focus, .tp-sheet-select:focus { background:#f3fcf8; box-shadow:inset 0 0 0 2px rgba(0,164,106,.42) !important; }
    .tp-sheet-cell.is-number { text-align:right; font-variant-numeric:tabular-nums; }
    .tp-sheet-select { appearance:auto; cursor:pointer; padding-right:4px; }
    .tp-sheet tr.has-error .tp-sheet-cell[data-invalid='1'], .tp-sheet tr.has-error .tp-sheet-select[data-invalid='1'] {
        background:#fff7f7; box-shadow:inset 0 -2px 0 #ef4444 !important;
    }
    .tp-sheet-row-actions { display:flex; align-items:center; justify-content:center; }
    .tp-sheet-remove { width:28px; height:28px; border:0; border-radius:9px; color:#94a3b8; background:transparent; cursor:pointer; }
    .tp-sheet-remove:hover { color:#dc3545; background:#fff1f2; }
    .tp-import-foot {
        display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
        padding:13px 20px 16px; border-top:1px solid #edf1f4; background:#fff;
    }
    .tp-import-status { color:#728091; font-size:.7rem; }
    .tp-import-status strong { color:#17212b; }
    .tp-import-submit {
        display:inline-flex; align-items:center; gap:8px; min-height:40px; padding:0 16px; border:0; border-radius:12px;
        color:#fff; background:#00a46a; font-size:.76rem; font-weight:720; cursor:pointer; box-shadow:0 10px 24px rgba(0,164,106,.18);
    }
    .tp-import-submit:disabled { cursor:not-allowed; opacity:.45; box-shadow:none; }
    .tp-import-submit:not(:disabled):hover { background:#008d5b; transform:translateY(-1px); }
    .tp-import-empty { padding:34px 20px; text-align:center; color:#94a3b8; font-size:.75rem; }
    .tp-import-empty i { display:block; margin-bottom:8px; color:#cbd5e1; font-size:1.6rem; }

    /* Focus visual TiquePOS: elimina el contorno negro nativo sin perder accesibilidad. */
    .tp-products-page button,
    .tp-products-page a,
    .tp-products-page label[for],
    .tp-products-page input,
    .tp-products-page select {
        -webkit-tap-highlight-color: transparent;
    }
    .tp-products-page button:focus,
    .tp-products-page button:focus-visible,
    .tp-products-page a:focus,
    .tp-products-page a:focus-visible,
    .tp-products-page label[for]:focus,
    .tp-products-page label[for]:focus-visible {
        outline: none !important;
    }
    .tp-import-submit:focus,
    .tp-import-submit:focus-visible {
        outline:none !important;
        box-shadow:0 0 0 4px rgba(0,164,106,.13), 0 10px 24px rgba(0,164,106,.18) !important;
    }
    .tp-sheet-remove:focus,
    .tp-sheet-remove:focus-visible {
        outline:none !important;
        color:#dc3545; background:#fff1f2;
        box-shadow:0 0 0 3px rgba(220,53,69,.09) !important;
    }
    .tp-products-page .btn:focus,
    .tp-products-page .btn.focus,
    .tp-products-page .btn:active:focus,
    .tp-products-page .btn.active:focus {
        outline:none !important;
        box-shadow:0 0 0 4px rgba(0,164,106,.10) !important;
    }
    @media (max-width: 767px) {
        .tp-import-head, .tp-import-toolbar, .tp-import-foot { padding-left:13px; padding-right:13px; }
        .tp-import-head { align-items:center; }
        .tp-import-title-icon { width:38px; height:38px; flex-basis:38px; }
        .tp-import-toolbar { align-items:flex-start; }
        .tp-import-actions, .tp-import-kpis { width:100%; }
        .tp-import-action { flex:1 1 auto; justify-content:center; }
        .tp-sheet-help { padding-left:13px; padding-right:13px; }
    }

    .tp-product-form-head {
        display: flex; align-items: center; justify-content: space-between; gap: 15px;
        margin-bottom: 14px;
    }
    .tp-product-form-head h2 { margin: 0 0 4px; font-size: 1.26rem; font-weight: 760; }
    .tp-product-form-head p { margin: 0; color: #7e8994; font-size: .76rem; }

    .tp-form-card {
        margin-bottom: 13px;
        border: 1px solid #e3e8ec;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 7px 20px rgba(15,23,42,.045);
    }
    .tp-form-card-header {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        min-height: 60px; padding: 13px 16px;
        border-bottom: 1px solid #edf0f2;
    }
    .tp-form-card-header h5 { margin: 0 0 2px; color: #34404b; font-size: .86rem; font-weight: 750; }
    .tp-form-card-header p { margin: 0; color: #929ba5; font-size: .68rem; }
    .tp-form-card-body { padding: 16px 16px 2px; }
    .tp-form-card label { color: #53606d; font-size: .71rem; font-weight: 680; }
    .tp-form-card .form-control,
    .tp-form-card .custom-file-label {
        min-height: 41px;
        border-color: #dfe4e9;
        border-radius: 9px;
        box-shadow: none;
        font-size: .78rem;
    }
    .tp-form-card .form-control:focus { border-color: #77b998; box-shadow: 0 0 0 3px rgba(0,155,84,.075); }
    .tp-field-help { display: block; margin-top: 5px; color: #929ba5; font-size: .65rem; line-height: 1.35; }

    .tp-image-uploader {
        height: 100%; min-height: 225px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 16px;
        border: 1px dashed #d7dde2;
        border-radius: 13px;
        background: #fafbfc;
        text-align: center;
    }
    .tp-image-preview { width: 115px; height: 115px; overflow: hidden; margin-bottom: 12px; border: 1px solid #e1e6ea; border-radius: 18px; background: #f1f3f5; }
    .tp-image-preview img { width: 100%; height: 100%; object-fit: cover; }
    .tp-image-uploader strong { font-size: .76rem; }
    .tp-image-uploader small { margin-top: 3px; color: #929ba5; font-size: .65rem; }

    .tp-collapse-trigger {
        display: inline-flex; align-items: center; gap: 7px;
        border: 0; color: #697580; background: transparent;
        font-size: .7rem; font-weight: 700;
    }
    .tp-collapse-trigger i { transition: transform .2s ease; }
    .tp-collapse-trigger[aria-expanded="true"] i { transform: rotate(180deg); }

    .producto-tributario-badge {
        display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px;
        border: 1px solid #c9e6d5; border-radius: 999px; color: #187044;
        background: #f0faf4; font-size: .67rem; font-weight: 730; white-space: nowrap;
    }

    .tp-switch-row { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 13px 14px; border: 1px solid #e6eaee; border-radius: 11px; background: #fafbfc; }
    .tp-switch-row strong { display: block; color: #3b4651; font-size: .76rem; }
    .tp-switch-row small { display: block; margin-top: 2px; color: #8c96a0; font-size: .65rem; }

    .tp-form-actions {
        position: sticky; bottom: 10px; z-index: 25;
        display: flex; justify-content: flex-end; gap: 8px;
        margin-top: 14px; padding: 11px;
        border: 1px solid rgba(220,225,230,.95);
        border-radius: 13px;
        background: rgba(255,255,255,.95);
        box-shadow: 0 10px 28px rgba(15,23,42,.1);
        backdrop-filter: blur(9px);
    }

    .tp-detail-overlay {
        position: fixed; inset: 0; z-index: 1048;
        visibility: hidden; opacity: 0;
        background: rgba(15,23,42,.36);
        transition: opacity .2s ease, visibility .2s ease;
    }
    .tp-detail-overlay.is-open { visibility: visible; opacity: 1; }
    .tp-detail-drawer {
        position: fixed; top: 0; right: 0; z-index: 1049;
        width: min(460px, 94vw); height: 100vh;
        display: flex; flex-direction: column;
        transform: translateX(105%);
        background: #fff;
        box-shadow: -18px 0 45px rgba(15,23,42,.18);
        transition: transform .24s ease;
    }
    .tp-detail-drawer.is-open { transform: translateX(0); }
    .tp-drawer-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px; border-bottom: 1px solid #e8ecef; }
    .tp-drawer-head h4 { margin: 0; font-size: .96rem; font-weight: 760; }
    .tp-drawer-close { width: 35px; height: 35px; border: 1px solid #e0e5e9; border-radius: 9px; color: #65717d; background: #fff; }
    .tp-drawer-body { flex: 1; overflow-y: auto; padding: 17px; }
    .tp-detail-cover { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
    .tp-detail-cover-image { width: 86px; height: 86px; flex: 0 0 86px; overflow: hidden; border: 1px solid #e2e7eb; border-radius: 16px; background: #f3f5f6; }
    .tp-detail-cover-image img { width: 100%; height: 100%; object-fit: cover; }
    .tp-detail-cover h3 { margin: 0 0 5px; color: #27323d; font-size: 1.02rem; font-weight: 760; }
    .tp-detail-cover p { margin: 0; color: #89939d; font-size: .7rem; }
    .tp-detail-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 9px; }
    .tp-detail-box { padding: 11px; border: 1px solid #e7ebee; border-radius: 11px; background: #fbfcfc; }
    .tp-detail-box span { display: block; color: #8c96a0; font-size: .62rem; font-weight: 700; text-transform: uppercase; }
    .tp-detail-box strong { display: block; margin-top: 4px; color: #35404b; font-size: .78rem; line-height: 1.35; }
    .tp-detail-section { margin-top: 17px; }
    .tp-detail-section h5 { margin: 0 0 9px; color: #4b5661; font-size: .72rem; font-weight: 760; text-transform: uppercase; }
    .tp-detail-variants { overflow: hidden; border: 1px solid #e4e8eb; border-radius: 11px; }
    .tp-detail-variant { display: grid; grid-template-columns: 1fr auto; gap: 10px; padding: 10px 11px; border-bottom: 1px solid #edf0f2; }
    .tp-detail-variant:last-child { border-bottom: 0; }
    .tp-detail-variant strong { display: block; color: #36414c; font-size: .73rem; }
    .tp-detail-variant small { color: #8d97a1; font-size: .64rem; }
    .tp-drawer-actions { display: flex; gap: 8px; padding: 13px 17px; border-top: 1px solid #e8ecef; }
    .tp-drawer-actions .btn { flex: 1; min-height: 40px; border-radius: 9px; font-size: .73rem; font-weight: 700; }

    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate { padding-top: 10px; color: #84909b; font-size: .68rem; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { min-width: 32px; border: 0 !important; border-radius: 8px !important; font-size: .68rem; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { color: #fff !important; background: var(--tp-product-green) !important; }
    .dt-buttons { display: none !important; }

    @media (max-width: 1199.98px) {
        .tp-products-toolbar { grid-template-columns: minmax(230px, 1fr) repeat(2, minmax(125px, auto)) auto; }
        .tp-products-toolbar .tp-filter-secondary { display: none; }
        .tp-products-grid { grid-template-columns: repeat(3, minmax(0,1fr)); }
    }

    @media (max-width: 991.98px) {
        .tp-products-toolbar { grid-template-columns: 1fr 1fr; }
        .tp-search-box { grid-column: 1 / -1; }
        .tp-view-toggle { justify-self: end; }
        .tp-products-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
    }

    @media (max-width: 767.98px) {
        .tp-products-hero { align-items: flex-start; flex-direction: column; }
        .tp-products-actions { width: 100%; }
        .tp-products-actions .tp-btn-primary { flex: 1; }
        .tp-product-summary { overflow-x: auto; flex-wrap: nowrap; padding-bottom: 2px; }
        .tp-summary-chip { flex: 0 0 auto; }
        .tp-products-toolbar { grid-template-columns: 1fr; }
        .tp-products-toolbar .tp-filter-secondary { display: block; }
        .tp-view-toggle { justify-self: start; }
        .tp-products-result-bar { align-items: flex-start; flex-direction: column; }
        .tp-products-grid { grid-template-columns: repeat(2, minmax(0,1fr)); gap: 9px; padding: 10px; }
        .tp-grid-image { height: 120px; }
        #tbllistado th:nth-child(2), #tbllistado td:nth-child(2),
        #tbllistado th:nth-child(5), #tbllistado td:nth-child(5),
        #tbllistado th:nth-child(6), #tbllistado td:nth-child(6) { display: none; }
        .tp-product-cell { min-width: 190px; }
        .tp-product-thumb { width: 42px; height: 42px; flex-basis: 42px; }
        .tp-detail-grid { grid-template-columns: 1fr; }
        .tp-form-actions { bottom: 5px; }
    }

    @media (max-width: 479.98px) {
        .tp-products-grid { grid-template-columns: 1fr; }
        .tp-grid-image { height: 170px; }
    }


    /* Panel superior dinámico de Productos */
    .tp-products-command {
        position: relative;
        overflow: visible;
        isolation: isolate;
        margin-bottom: 16px;
        border: 1px solid #e5eaf0;
        border-radius: 20px;
        background:
            radial-gradient(circle at 94% 8%, rgba(0, 164, 106, .09), transparent 28%),
            linear-gradient(135deg, #ffffff 0%, #fbfefd 58%, #f6fcf9 100%);
        box-shadow: 0 18px 48px rgba(15, 23, 42, .065);
    }

    .tp-products-command::after {
        content: '';
        position: absolute;
        width: 150px;
        height: 150px;
        right: 10px;
        bottom: 10px;
        border-radius: 999px;
        background: radial-gradient(circle, rgba(0, 164, 106, .045) 0%, rgba(0, 164, 106, 0) 72%);
        pointer-events: none;
        z-index: 0;
    }

    .tp-products-command .tp-products-hero {
        position: relative;
        z-index: 30;
        margin: 0;
        padding: 20px 20px 13px;
    }

    .tp-products-title-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .tp-products-title-icon {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #caeedf;
        border-radius: 13px;
        color: #00754d;
        background: #ecfdf6;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.7);
    }

    .tp-products-title-icon i { font-size: .95rem; }

    .tp-products-command .tp-products-hero h1 {
        margin-bottom: 3px;
        font-size: 1.38rem;
        font-weight: 760;
        color: #17212b;
    }

    .tp-products-command .tp-products-actions {
        position: relative;
        z-index: 40;
    }

    .tp-products-command .tp-products-actions .dropdown {
        position: relative;
        z-index: 50;
    }

    .tp-products-command .tp-products-actions .dropdown-menu {
        z-index: 1080 !important;
        min-width: 220px;
        margin-top: 8px;
        padding: 7px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 18px 42px rgba(15, 23, 42, .16);
    }

    .tp-products-command .tp-products-actions .dropdown-item {
        display: flex;
        align-items: center;
        min-height: 38px;
        padding: 8px 10px;
        border-radius: 8px;
        color: #475569;
        font-size: .76rem;
        transition: background-color .15s ease, color .15s ease;
    }

    .tp-products-command .tp-products-actions .dropdown-item:hover,
    .tp-products-command .tp-products-actions .dropdown-item:focus {
        color: #00754d;
        background: #effaf5;
    }

    .tp-products-command .tp-products-actions .dropdown-item i {
        width: 21px;
        margin-right: 7px !important;
        color: #64748b;
        text-align: center;
    }

    .tp-products-command .tp-products-actions .dropdown-item:hover i,
    .tp-products-command .tp-products-actions .dropdown-item:focus i {
        color: #00a46a;
    }

    .tp-products-command .tp-products-actions .dropdown-divider {
        margin: 6px 4px;
        border-top-color: #edf1f4;
    }

    .tp-products-command .tp-btn-primary,
    .tp-products-command .tp-btn-secondary {
        min-height: 42px;
        border-radius: 12px;
        font-weight: 650;
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease, background-color .16s ease;
    }

    .tp-products-command .tp-btn-primary {
        border-color: #00a46a;
        background: #00a46a;
        box-shadow: 0 10px 22px rgba(0, 164, 106, .20);
    }

    .tp-products-command .tp-btn-primary:hover {
        transform: translateY(-1px);
        background: #008d5b;
        border-color: #008d5b;
        box-shadow: 0 13px 26px rgba(0, 164, 106, .26);
    }

    .tp-products-command .tp-btn-secondary:hover {
        transform: translateY(-1px);
        border-color: #c9d2dc;
        background: #f8fafc;
        color: #334155;
    }

    .tp-products-command button:focus,
    .tp-products-command button:active,
    .tp-products-command button:focus-visible {
        outline: none !important;
        box-shadow: none;
    }

    .tp-products-command .tp-btn-primary:focus-visible {
        box-shadow: 0 0 0 4px rgba(0, 164, 106, .13) !important;
    }

    .tp-product-summary.tp-product-summary-dynamic {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin: 0;
        padding: 0 20px 20px;
    }

    .tp-quick-filter {
        position: relative;
        width: 100%;
        min-width: 0;
        min-height: 72px;
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 11px 12px;
        border: 1px solid #e4e9ee;
        border-radius: 15px;
        color: #475569;
        background: rgba(255,255,255,.92);
        text-align: left;
        cursor: pointer;
        box-shadow: 0 6px 18px rgba(15,23,42,.035);
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease, background-color .16s ease;
    }

    .tp-quick-filter:hover {
        transform: translateY(-2px);
        border-color: #cbd5df;
        background: #fff;
        box-shadow: 0 11px 24px rgba(15,23,42,.075);
    }

    .tp-quick-filter:focus-visible {
        border-color: #00a46a;
        box-shadow: 0 0 0 4px rgba(0,164,106,.10) !important;
    }

    .tp-quick-filter.is-active {
        border-color: rgba(0,164,106,.56);
        background: linear-gradient(135deg, #f0fcf6 0%, #ffffff 100%);
        box-shadow: 0 10px 26px rgba(0,164,106,.11), inset 0 0 0 1px rgba(0,164,106,.06);
    }

    .tp-quick-icon {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        color: #00754d;
        background: #eafaf3;
        font-size: .85rem;
        transition: transform .16s ease;
    }

    .tp-quick-filter:hover .tp-quick-icon { transform: scale(1.04); }
    .tp-quick-filter[data-product-filter="bajo"] .tp-quick-icon { color: #a16207; background: #fff8df; }
    .tp-quick-filter[data-product-filter="sin_stock"] .tp-quick-icon { color: #be3345; background: #fff0f2; }
    .tp-quick-filter[data-product-filter="variantes"] .tp-quick-icon { color: #5264b8; background: #f0f2ff; }

    .tp-quick-copy {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .tp-quick-label {
        overflow: hidden;
        color: #64748b;
        font-size: .66rem;
        font-weight: 650;
        line-height: 1.1;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .tp-quick-value {
        display: flex;
        align-items: baseline;
        gap: 5px;
        line-height: 1;
    }

    .tp-quick-value strong {
        color: #17212b;
        font-size: 1.02rem;
        font-weight: 780;
        letter-spacing: -.02em;
    }

    .tp-quick-value small {
        overflow: hidden;
        color: #8692a0;
        font-size: .63rem;
        font-weight: 520;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .tp-quick-check {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 18px;
        height: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        color: transparent;
        background: transparent;
        font-size: .52rem;
        transition: color .16s ease, background-color .16s ease;
    }

    .tp-quick-filter.is-active .tp-quick-check {
        color: #fff;
        background: #00a46a;
    }

    .tp-quick-filter.is-active .tp-quick-label { color: #00754d; }

    .tp-quick-filter.is-loading .tp-quick-value strong {
        opacity: .4;
    }

    .tp-quick-filter.pulse-count .tp-quick-value strong {
        animation: tpProductCountPulse .28s ease;
    }

    @keyframes tpProductCountPulse {
        0% { transform: scale(.92); opacity: .5; }
        65% { transform: scale(1.08); opacity: 1; }
        100% { transform: scale(1); }
    }

    @media (max-width: 991.98px) {
        .tp-product-summary.tp-product-summary-dynamic { grid-template-columns: repeat(2, minmax(0,1fr)); }
    }

    @media (max-width: 767.98px) {
        .tp-products-command .tp-products-hero { padding: 16px 14px 12px; }
        .tp-products-title-row { align-items: flex-start; }
        .tp-products-title-icon { width: 38px; height: 38px; flex-basis: 38px; border-radius: 11px; }
        .tp-products-command .tp-products-actions { width: 100%; }
        .tp-products-command .tp-products-actions .dropdown { flex: 1; }
        .tp-products-command .tp-products-actions .tp-btn-secondary { width: 100%; }
        .tp-product-summary.tp-product-summary-dynamic {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            overflow: visible;
            gap: 8px;
            padding: 0 14px 14px;
        }
        .tp-quick-filter { min-height: 68px; padding: 10px; }
        .tp-quick-value strong { font-size: .96rem; }
    }

    @media (max-width: 420px) {
        .tp-product-summary.tp-product-summary-dynamic { grid-template-columns: 1fr 1fr; }
        .tp-quick-icon { width: 34px; height: 34px; flex-basis: 34px; }
        .tp-quick-label { font-size: .61rem; }
        .tp-quick-value small { display: none; }
    }
</style>

<div class="main-content tp-products-page">
    <section class="section">
        <div class="section-body">
            <div class="tp-products-command tw-border tw-border-slate-200/80 tw-bg-white tw-shadow-product-command">
                <div class="tp-products-hero">
                    <div class="tp-products-title-row">
                        <span class="tp-products-title-icon" aria-hidden="true">
                            <i class="fas fa-boxes"></i>
                        </span>
                        <div>
                            <h1>Productos</h1>
                            <p>Administra precios, existencias, categorías y configuración tributaria.</p>
                        </div>
                    </div>

                    <div class="tp-products-actions">
                        <div class="dropdown">
                            <button class="tp-btn-secondary dropdown-toggle tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-shadow-sm hover:tw-bg-slate-50" type="button" data-toggle="dropdown">
                                <i class="fas fa-ellipsis-h"></i> Más acciones
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <button class="dropdown-item" type="button" onclick="togglePlantilla()">
                                    <i class="fas fa-file-import mr-2"></i> Importar productos
                                </button>
                                <button class="dropdown-item" type="button" onclick="exportarProductos('excel')">
                                    <i class="fas fa-file-excel mr-2"></i> Exportar Excel
                                </button>
                                <button class="dropdown-item" type="button" onclick="exportarProductos('pdf')">
                                    <i class="fas fa-file-pdf mr-2"></i> Exportar PDF
                                </button>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="Controllers/Product.php?op=descargarPlantillaExcel" download="plantilla_productos.xlsx">
                                    <i class="fas fa-file-excel mr-2"></i> Descargar plantilla Excel
                                </a>
                                <a class="dropdown-item" href="Controllers/Product.php?op=descargarPlantillaCsv" download="plantilla_productos.csv">
                                    <i class="fas fa-file-csv mr-2"></i> Descargar plantilla CSV
                                </a>
                            </div>
                        </div>

                        <button class="tp-btn-primary tw-bg-tique-500 tw-border-tique-500 tw-shadow-lg tw-shadow-tique-500/20 hover:tw-bg-tique-600" onclick="mostrarform(true)" id="btnagregar" type="button">
                            <i class="fas fa-plus"></i> Nuevo producto
                        </button>
                    </div>
                </div>

                <div class="tp-product-summary tp-product-summary-dynamic" id="resumenProductos" aria-label="Filtros rápidos de productos">
                    <button type="button" class="tp-quick-filter is-active tw-group" data-product-filter="todos" aria-pressed="true" title="Mostrar todos los productos">
                        <span class="tp-quick-icon"><i class="fas fa-boxes"></i></span>
                        <span class="tp-quick-copy">
                            <span class="tp-quick-label">Todos</span>
                            <span class="tp-quick-value"><strong id="kpiTotalProductos">0</strong><small>productos</small></span>
                        </span>
                        <span class="tp-quick-check"><i class="fas fa-check"></i></span>
                    </button>

                    <button type="button" class="tp-quick-filter tw-group" data-product-filter="bajo" aria-pressed="false" title="Filtrar productos con stock bajo">
                        <span class="tp-quick-icon"><i class="fas fa-exclamation-triangle"></i></span>
                        <span class="tp-quick-copy">
                            <span class="tp-quick-label">Stock bajo</span>
                            <span class="tp-quick-value"><strong id="kpiStockBajo">0</strong><small>productos</small></span>
                        </span>
                        <span class="tp-quick-check"><i class="fas fa-check"></i></span>
                    </button>

                    <button type="button" class="tp-quick-filter tw-group" data-product-filter="sin_stock" aria-pressed="false" title="Filtrar productos sin stock">
                        <span class="tp-quick-icon"><i class="fas fa-box-open"></i></span>
                        <span class="tp-quick-copy">
                            <span class="tp-quick-label">Sin stock</span>
                            <span class="tp-quick-value"><strong id="kpiSinStock">0</strong><small>productos</small></span>
                        </span>
                        <span class="tp-quick-check"><i class="fas fa-check"></i></span>
                    </button>

                    <button type="button" class="tp-quick-filter tw-group" data-product-filter="variantes" aria-pressed="false" title="Filtrar productos que usan variantes">
                        <span class="tp-quick-icon"><i class="fas fa-layer-group"></i></span>
                        <span class="tp-quick-copy">
                            <span class="tp-quick-label">Con variantes</span>
                            <span class="tp-quick-value"><strong id="kpiVariaciones">0</strong><small>productos</small></span>
                        </span>
                        <span class="tp-quick-check"><i class="fas fa-check"></i></span>
                    </button>
                </div>
            </div>

            <div id="plantillaSection" class="tp-import-panel tw-border tw-border-slate-200 tw-bg-white tw-shadow-xl" aria-hidden="true">
                <div class="tp-import-head tw-bg-gradient-to-r tw-from-white tw-to-tique-50">
                    <div class="tp-import-title">
                        <span class="tp-import-title-icon"><i class="fas fa-table"></i></span>
                        <div>
                            <h5>Importar Productos</h5>
                            <p>Registra productos en masa directamente desde esta hoja o carga un archivo Excel/CSV. Categorías, almacenes y unidades se muestran con nombres legibles.</p>
                        </div>
                    </div>
                    <button type="button" class="tp-import-close tw-transition-all tw-duration-200 focus:tw-outline-none" onclick="togglePlantilla(false)" title="Cerrar"><i class="fas fa-times"></i></button>
                </div>

                <div class="tp-import-toolbar tw-bg-white">
                    <div class="tp-import-actions">
                        <button type="button" class="tp-import-action is-primary tw-select-none tw-border-tique-500 tw-bg-tique-500 tw-text-white tw-shadow-md tw-transition-all tw-duration-200 hover:tw-bg-tique-600 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-500/10" id="btnAgregarFilaMasiva"><i class="fas fa-plus"></i> Agregar fila</button>
                        <label class="tp-import-action mb-0 tw-select-none tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-shadow-sm tw-transition-all tw-duration-200 hover:tw-border-tique-200 hover:tw-bg-tique-50 hover:tw-text-tique-700" for="archivo_productos"><i class="fas fa-file-upload"></i> Subir Excel/CSV</label>
                        <input class="tp-import-file" type="file" id="archivo_productos" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                        <a class="tp-import-action tw-select-none tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-shadow-sm tw-transition-all tw-duration-200 hover:tw-border-tique-200 hover:tw-bg-tique-50 hover:tw-text-tique-700 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-500/10" href="Controllers/Product.php?op=descargarPlantillaExcel" download="plantilla_productos.xlsx"><i class="fas fa-file-excel"></i> Plantilla Excel</a>
                        <a class="tp-import-action tw-select-none tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-shadow-sm tw-transition-all tw-duration-200 hover:tw-border-tique-200 hover:tw-bg-tique-50 hover:tw-text-tique-700 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-500/10" href="Controllers/Product.php?op=descargarPlantillaCsv" download="plantilla_productos.csv"><i class="fas fa-file-csv"></i> Plantilla CSV</a>
                        <button type="button" class="tp-import-action tw-select-none tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-shadow-sm tw-transition-all tw-duration-200 hover:tw-border-tique-200 hover:tw-bg-tique-50 hover:tw-text-tique-700 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-500/10" id="btnLimpiarMasivo"><i class="fas fa-eraser"></i> Limpiar</button>
                    </div>
                    <div class="tp-import-kpis">
                        <span class="tp-import-kpi"><strong id="masivoTotal">0</strong> filas</span>
                        <span class="tp-import-kpi is-valid"><strong id="masivoValidas">0</strong> válidas</span>
                        <span class="tp-import-kpi is-error"><strong id="masivoErrores">0</strong> con error</span>
                    </div>
                </div>

                <div class="tp-sheet-help">
                    <i class="fas fa-info-circle"></i>
                    <span>Puedes pegar varias columnas y filas de una sola vez. Categoría, subcategoría, almacén y unidad muestran <strong>ID + nombre</strong>, pero el sistema guarda únicamente el ID.</span>
                </div>

                <div class="tp-sheet-wrap" id="masivoSheetWrap">
                    <table class="tp-sheet" id="tablaMasivaProductos">
                        <colgroup>
                            <col style="width:54px"><col style="width:210px"><col style="width:130px"><col style="width:82px">
                            <col style="width:108px"><col style="width:108px"><col style="width:180px"><col style="width:190px">
                            <col style="width:170px"><col style="width:165px"><col style="width:52px">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>#</th><th>Producto *</th><th>SKU *</th><th>Stock</th><th>P. compra</th><th>P. venta *</th>
                                <th>Categoría *</th><th>Subcategoría</th><th>Almacén *</th><th>Unidad *</th><th></th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoMasivoProductos"></tbody>
                    </table>
                    <div class="tp-import-empty" id="masivoEmpty" style="display:none;"><i class="fas fa-border-all"></i>Agrega una fila o pega directamente desde Excel sobre la primera celda.</div>
                </div>

                <div class="tp-import-foot">
                    <div class="tp-import-status" id="masivoEstado">Carga los catálogos para comenzar.</div>
                    <button type="button" class="tp-import-submit tw-select-none tw-bg-tique-500 tw-text-white tw-shadow-lg tw-transition-all tw-duration-200 hover:tw-bg-tique-600 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-500/10" id="btnImportarMasivo" disabled><i class="fas fa-cloud-upload-alt"></i> Importar productos válidos</button>
                </div>
            </div>

            <div id="listadoregistros" class="tp-products-card">
                <div class="tp-products-toolbar">
                    <div class="tp-search-box">
                        <i class="fas fa-search"></i>
                        <input type="search" id="productoBuscar" placeholder="Buscar por nombre, SKU, categoría o almacén">
                    </div>
                    <select id="filtroCategoriaProducto"><option value="">Todas las categorías</option></select>
                    <select id="filtroStockProducto">
                        <option value="">Todo el stock</option>
                        <option value="normal">Stock normal</option>
                        <option value="bajo">Stock bajo</option>
                        <option value="sin_stock">Sin stock</option>
                    </select>
                    <select id="filtroTributoProducto" class="tp-filter-secondary">
                        <option value="">Toda tributación</option>
                        <option value="10">Gravado</option>
                        <option value="20">Exonerado</option>
                        <option value="30">Inafecto</option>
                        <option value="40">Exportación</option>
                    </select>
                    <select id="filtroEstadoProducto" class="tp-filter-secondary">
                        <option value="">Todos los estados</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </select>
                    <div class="tp-view-toggle" aria-label="Cambiar vista">
                        <button type="button" id="btnVistaTabla" title="Vista tabla"><i class="fas fa-list"></i></button>
                        <button type="button" id="btnVistaGrid" title="Vista cuadrícula"><i class="fas fa-th-large"></i></button>
                    </div>
                </div>

                <div class="tp-products-result-bar">
                    <span id="productosResultado">Cargando productos...</span>
                    <span>El costo, almacén y datos técnicos están disponibles en <strong>Ver detalle</strong>.</span>
                </div>

                <div id="vistaTablaProductos" class="tp-products-table-wrap">
                    <div class="table-responsive" style="overflow:visible;">
                        <table id="tbllistado" class="table" style="width:100%;">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th>Stock</th>
                                    <th>Precio de venta</th>
                                    <th>Tributación</th>
                                    <th>Estado</th>
                                    <th class="text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <div id="vistaGridProductos" style="display:none;">
                    <div id="productosGrid" class="tp-products-grid"></div>
                    <div class="tp-grid-more-wrap">
                        <button type="button" id="btnMostrarMasProductos" class="tp-btn-secondary" style="display:none;">
                            Mostrar más productos
                        </button>
                    </div>
                </div>
            </div>

            <div id="formularioregistros" style="display:none;">
                <div class="tp-product-form-head">
                    <div>
                        <button type="button" class="btn btn-link p-0 mb-2" onclick="cancelarform()">
                            <i class="fas fa-arrow-left mr-1"></i> Volver al listado
                        </button>
                        <h2 id="tituloFormularioProducto">Nuevo producto</h2>
                        <p id="subtituloFormularioProducto">Completa la información esencial. Las opciones avanzadas están organizadas por sección.</p>
                    </div>
                </div>

                <form name="formulario" id="formulario" method="POST" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="idarticulo" id="idarticulo">
                    <input type="hidden" name="imagenactual" id="imagenactual">

                    <div class="tp-form-card">
                        <div class="tp-form-card-header">
                            <div>
                                <h5>Información principal</h5>
                                <p>Datos que el usuario necesita para identificar y vender el producto.</p>
                            </div>
                            <span class="badge badge-light">Esencial</span>
                        </div>
                        <div class="tp-form-card-body">
                            <div class="row">
                                <div class="col-lg-3 col-md-4 mb-3">
                                    <div class="tp-image-uploader">
                                        <div class="tp-image-preview">
                                            <img src="storage/images/products/default.png" alt="Vista previa" id="imagenmuestra">
                                        </div>
                                        <strong>Imagen del producto</strong>
                                        <small>JPG o PNG. Se recomienda una imagen cuadrada.</small>
                                        <label class="btn btn-outline-secondary btn-sm mt-3 mb-0" for="imagen">
                                            <i class="fas fa-camera mr-1"></i> Seleccionar imagen
                                        </label>
                                        <input type="file" class="d-none" id="imagen" name="imagen" accept="image/jpeg,image/png">
                                    </div>
                                </div>

                                <div class="col-lg-9 col-md-8">
                                    <div class="row">
                                        <div class="form-group col-lg-8">
                                            <label for="nombre">Nombre del producto <span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="nombre" id="nombre" maxlength="100" placeholder="Ej.: Aretes mariposa dorados" required>
                                        </div>
                                        <div id="grupo_sku_principal" class="form-group col-lg-4">
                                            <label for="codigo">SKU</label>
                                            <input type="text" name="codigo" id="codigo" class="form-control" placeholder="Opcional">
                                        </div>
                                        <div id="grupo_precio_venta_principal" class="form-group col-lg-4">
                                            <label for="precio_venta">Precio de venta <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control" name="precio_venta" id="precio_venta" min="0.01" placeholder="0.00" required>
                                        </div>
                                        <div class="form-group col-lg-4">
                                            <label for="idcategoria">Categoría <span class="text-danger">*</span></label>
                                            <select class="form-control" name="idcategoria" id="idcategoria" required></select>
                                        </div>
                                        <div class="form-group col-lg-4">
                                            <label for="idsubcategoria">Subcategoría</label>
                                            <select class="form-control" name="idsubcategoria" id="idsubcategoria" disabled>
                                                <option value="">Seleccione subcategoría</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-12">
                                            <label for="descripcion">Descripción</label>
                                            <textarea class="form-control" name="descripcion" id="descripcion" rows="2" maxlength="500" placeholder="Información adicional opcional"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tp-form-card">
                        <div class="tp-form-card-header">
                            <div>
                                <h5>Inventario y costos</h5>
                                <p>La información de costo se mantiene fuera del listado principal.</p>
                            </div>
                            <button class="tp-collapse-trigger" type="button" data-toggle="collapse" data-target="#seccionInventarioProducto" aria-expanded="true">
                                Ver sección <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        <div class="collapse show" id="seccionInventarioProducto">
                            <div class="tp-form-card-body">
                                <div class="row">
                                    <div id="grupo_stock_principal" class="form-group col-lg-3 col-md-6">
                                        <label for="stock">Stock inicial</label>
                                        <input type="number" class="form-control" name="stock" id="stock" min="0" value="0">
                                    </div>
                                    <div id="grupo_precio_compra_principal" class="form-group col-lg-3 col-md-6">
                                        <label for="precio_compra">Costo unitario</label>
                                        <input type="number" step="0.01" class="form-control" name="precio_compra" id="precio_compra" min="0" placeholder="0.00">
                                        <span class="tp-field-help">Opcional. Se utiliza para márgenes y reportes internos.</span>
                                    </div>
                                    <div class="form-group col-lg-3 col-md-6">
                                        <label for="idalmacen">Almacén <span class="text-danger">*</span></label>
                                        <select class="form-control" name="idalmacen" id="idalmacen" required></select>
                                    </div>
                                    <div class="form-group col-lg-3 col-md-6">
                                        <label for="idmedida">Unidad de medida <span class="text-danger">*</span></label>
                                        <select class="form-control" name="idmedida" id="idmedida" required></select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tp-form-card">
                        <div class="tp-form-card-header">
                            <div>
                                <h5>Datos tributarios</h5>
                                <p>El producto hereda inicialmente la configuración general de la empresa.</p>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="producto-tributario-badge mr-2" id="estadoTributarioProducto">
                                    <i class="fas fa-percentage"></i> Gravado 18%
                                </span>
                                <button class="tp-collapse-trigger" type="button" data-toggle="collapse" data-target="#seccionTributariaProducto" aria-expanded="false">
                                    Configurar <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="collapse" id="seccionTributariaProducto">
                            <div class="tp-form-card-body">
                                <div class="row">
                                    <div class="form-group col-lg-4 col-md-6">
                                        <label for="codigo_afectacion_igv">Afectación al IGV</label>
                                        <select class="form-control" name="codigo_afectacion_igv" id="codigo_afectacion_igv" required>
                                            <option value="10">10 — Gravado: operación onerosa</option>
                                            <option value="20">20 — Exonerado: operación onerosa</option>
                                            <option value="30">30 — Inafecto: operación onerosa</option>
                                            <option value="40">40 — Exportación</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-lg-2 col-md-6">
                                        <label for="porcentaje_igv">Tasa IGV (%)</label>
                                        <input type="number" class="form-control" name="porcentaje_igv" id="porcentaje_igv" min="0" max="100" step="0.01" readonly required>
                                    </div>
                                    <div class="form-group col-lg-3 col-md-6">
                                        <label for="unidad_medida_sunat">Unidad SUNAT</label>
                                        <select class="form-control" name="unidad_medida_sunat" id="unidad_medida_sunat" required>
                                            <option value="NIU">NIU — Unidad</option>
                                            <option value="ZZ">ZZ — Servicio</option>
                                            <option value="KGM">KGM — Kilogramo</option>
                                            <option value="LTR">LTR — Litro</option>
                                            <option value="MTR">MTR — Metro</option>
                                            <option value="BX">BX — Caja</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-lg-3 col-md-6">
                                        <label for="codigo_producto_sunat">Código de producto SUNAT</label>
                                        <input type="text" class="form-control" name="codigo_producto_sunat" id="codigo_producto_sunat" maxlength="16" placeholder="Opcional">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tp-form-card">
                        <div class="tp-form-card-header">
                            <div>
                                <h5>Variaciones</h5>
                                <p>Actívalo únicamente para productos con talla, color, modelo u otras combinaciones.</p>
                            </div>
                            <button class="tp-collapse-trigger" type="button" data-toggle="collapse" data-target="#seccionVariacionesProducto" aria-expanded="false">
                                Configurar <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        <div class="collapse" id="seccionVariacionesProducto">
                            <div class="tp-form-card-body">
                                <div class="tp-switch-row mb-3">
                                    <div>
                                        <strong>Este producto tiene variantes</strong>
                                        <small>El stock y los precios se administrarán por combinación.</small>
                                    </div>
                                    <label class="switch mb-0">
                                        <input type="checkbox" id="activar_atributos" onchange="toggleAtributos()">
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                                <div id="atributos_section" style="display:none;">
                                    <div class="form-group">
                                        <label for="atributos_seleccionados">Atributos</label>
                                        <select id="atributos_seleccionados" class="form-control select2" multiple style="width:100%;"></select>
                                    </div>
                                    <div class="row" id="contenedor_atributos"></div>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="generarVariaciones()">
                                        <i class="fas fa-cogs mr-1"></i> Generar combinaciones
                                    </button>

                                    <div id="variaciones-container" class="mt-4" style="display:none;">
                                        <div class="table-responsive">
                                            <table id="tblvariaciones" class="table table-bordered table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Combinación</th>
                                                        <th>SKU</th>
                                                        <th>Stock</th>
                                                        <th>Costo</th>
                                                        <th>Precio de venta</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="variaciones-lista"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tp-form-actions">
                        <button class="btn btn-light" onclick="cancelarform()" type="button">Cancelar</button>
                        <button class="btn btn-success" type="submit" id="btnGuardar">
                            <i class="fas fa-save mr-1"></i> Guardar producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<div class="tp-detail-overlay" id="detalleProductoOverlay" onclick="cerrarDetalleProducto()"></div>
<aside class="tp-detail-drawer" id="detalleProductoDrawer" aria-hidden="true">
    <div class="tp-drawer-head">
        <h4>Detalle del producto</h4>
        <button type="button" class="tp-drawer-close" onclick="cerrarDetalleProducto()"><i class="fas fa-times"></i></button>
    </div>
    <div class="tp-drawer-body" id="detalleProductoContenido">
        <div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm mr-2"></span>Cargando producto...</div>
    </div>
    <div class="tp-drawer-actions" id="detalleProductoAcciones" style="display:none;">
        <button class="btn btn-outline-secondary" type="button" id="btnEstadoDesdeDetalle">Cambiar estado</button>
        <button class="btn btn-success" type="button" id="btnEditarDesdeDetalle"><i class="fas fa-pencil-alt mr-1"></i> Editar producto</button>
    </div>
</aside>
<?php
} else {
    require 'access.php';
}

require 'footer.php';
$rutaJs = __DIR__ . '/scripts/product.js';
$versionJs = is_file($rutaJs) ? filemtime($rutaJs) : time();
?>
<script src="Assets/js/JsBarcode.all.min.js"></script>
<script src="Assets/js/jquery.PrintArea.js"></script>
<script src="Views/modules/scripts/product.js?v=<?= (int)$versionJs ?>"></script>
<?php
ob_end_flush();
?>
