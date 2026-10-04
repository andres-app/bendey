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

if ((int)($_SESSION['compras'] ?? 0) === 1) {
?>

<!-- Tailwind aislado para Compras. Preflight desactivado para convivir con Bootstrap/Stisla. -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        prefix: 'tw-',
        corePlugins: {
            preflight: false
        },
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
                    'tique-card': '0 14px 42px rgba(15, 23, 42, .08)',
                    'tique-soft': '0 8px 24px rgba(15, 23, 42, .06)'
                }
            }
        }
    };
</script>

<style>
    .compra-page .card {
        border: 0;
        border-radius: 18px;
        box-shadow: 0 12px 35px rgba(15, 23, 42, .07);
    }

    .compra-page .card-header {
        border-bottom: 1px solid #edf1ef;
        background: #fff;
    }

    .compra-page .form-control {
        min-height: 44px;
        border-color: #dce4df;
        border-radius: 10px;
    }

    .compra-page textarea.form-control {
        min-height: 86px;
    }

    .compra-page .form-control:focus {
        border-color: #00a46a;
        box-shadow: 0 0 0 .18rem rgba(0, 164, 106, .12);
    }

    .compra-actions {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .compra-action-btn {
        min-height: 74px;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 12px;
        padding: 14px 16px;
        border: 1px solid #dfe8e2;
        border-radius: 14px;
        background: #fff;
        text-align: left;
        transition: .16s ease;
    }

    .compra-action-btn:hover,
    .compra-action-btn:focus {
        transform: translateY(-1px);
        border-color: #93cda3;
        background: #f3fbf5;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
        outline: none;
    }

    .compra-action-icon {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        color: #278c46;
        background: #eaf7ee;
        font-size: 1.1rem;
    }

    .compra-action-title {
        color: #243128;
        font-weight: 700;
    }

    .compra-action-help {
        margin-top: 2px;
        color: #77847c;
        font-size: .77rem;
        line-height: 1.25;
    }

    .detalle-compra-table thead th {
        border-top: 0;
        border-bottom: 1px solid #dfe7e2;
        color: #617067;
        background: #f7f9f8;
        font-size: .77rem;
        font-weight: 800;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .detalle-compra-table td {
        vertical-align: middle;
    }

    .detalle-compra-table .form-control {
        min-width: 100px;
        min-height: 39px;
        padding: 6px 9px;
    }

    .detalle-tipo {
        display: inline-flex;
        align-items: center;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .detalle-tipo-inventario {
        color: #1d7438;
        background: #eaf7ee;
    }

    .detalle-tipo-gasto {
        color: #5b6472;
        background: #eef1f4;
    }

    .compra-empty {
        padding: 42px 18px;
        color: #98a29c;
        text-align: center;
    }

    .compra-empty i {
        display: block;
        margin-bottom: 12px;
        color: #d3dcd6;
        font-size: 2.5rem;
    }

    .compra-total-box {
        border: 1px solid #dfe8e2;
        border-radius: 16px;
        background: #f8faf9;
        overflow: hidden;
    }

    .compra-total-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 15px;
        color: #5e6c64;
    }

    .compra-total-row + .compra-total-row {
        border-top: 1px solid #e5ebe7;
    }

    .compra-total-row.total-final {
        color: #1e2b23;
        background: #fff;
        font-size: 1.15rem;
        font-weight: 800;
    }

    .producto-compra-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border: 1px solid #e2e9e5;
        border-radius: 13px;
        background: #fff;
    }

    .producto-compra-item + .producto-compra-item {
        margin-top: 10px;
    }

    .producto-compra-item:hover {
        border-color: #a9d5b5;
        background: #f7fcf8;
    }

    .producto-compra-thumb {
        width: 54px;
        height: 54px;
        flex: 0 0 54px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 11px;
        background: #eef3f0;
    }

    .producto-compra-thumb img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .producto-compra-meta {
        min-width: 0;
        flex: 1 1 auto;
    }

    .producto-compra-nombre {
        color: #28352d;
        font-weight: 800;
    }

    .producto-compra-sub {
        margin-top: 3px;
        color: #7a8880;
        font-size: .76rem;
    }

    .modal-compra .modal-content {
        border: 0;
        border-radius: 18px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .24);
    }

    .modal-compra .modal-header {
        border-bottom: 1px solid #e7ece9;
    }

    .modal-compra .modal-footer {
        border-top: 1px solid #e7ece9;
    }

    .coincidencias-producto {
        display: none;
        margin-top: 10px;
        padding: 10px 12px;
        border: 1px solid #f0dca5;
        border-radius: 11px;
        color: #795c18;
        background: #fff9e8;
        font-size: .8rem;
    }


    /* =========================================================
       LISTADO PREMIUM DE COMPRAS
       ========================================================= */
    .compra-list-header {
        min-height: 86px;
        padding: 18px 22px;
        border-bottom: 1px solid #edf1ef;
    }

    .compra-list-header h4 {
        color: #243128;
        font-size: 1.08rem;
        font-weight: 700;
    }

    .compra-list-header small {
        display: block;
        margin-top: 4px;
        color: #7d8981 !important;
        font-size: .79rem;
        line-height: 1.4;
    }

    .compra-nueva-btn {
        min-height: 40px;
        padding: 8px 15px;
        border-radius: 10px;
        font-weight: 500;
        box-shadow: none !important;
    }

    .compra-list-toolbar {
        margin-bottom: 16px;
        padding: 14px;
        border: 1px solid #e4ebe6;
        border-radius: 14px;
        background: #f9fbfa;
    }

    .compra-filter-grid {
        display: grid;
        grid-template-columns:
            minmax(150px, .72fr)
            minmax(390px, 1.45fr)
            minmax(260px, 1fr)
            auto;
        gap: 12px;
        align-items: end;
    }

    .compra-filter-field {
        min-width: 0;
    }

    .compra-filter-field label {
        display: block;
        margin-bottom: 5px;
        color: #7a867f;
        font-size: .67rem;
        font-weight: 700;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .compra-filter-field .form-control {
        min-height: 44px;
        height: 44px;
        border-color: #dce4df;
        border-radius: 11px;
        background: #fff;
        color: #334155;
        font-size: .82rem;
        font-weight: 400;
        transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
    }

    .compra-filter-field .form-control:focus {
        border-color: #00a46a;
        box-shadow: 0 0 0 3px rgba(0, 164, 106, .09);
    }


    /*
     * Selector visual de fechas inspirado en Fecha de emisión de newsale3.php.
     * Los valores reales siguen en #compraFechaDesde y #compraFechaHasta.
     */
    .compra-date-trigger-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .compra-fecha-trigger {
        width: 100%;
        min-width: 0;
        min-height: 44px;
        padding: 6px 10px;
        display: flex;
        align-items: center;
        gap: 9px;
        border: 1px solid #dce4df;
        border-radius: 11px;
        color: #334155;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .025);
        text-align: left;
        transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
    }

    .compra-fecha-trigger:hover {
        border-color: #c9d7cf;
        background: #fff;
    }

    .compra-fecha-trigger:focus,
    .compra-fecha-trigger:focus-visible {
        outline: none !important;
        border-color: #00a46a !important;
        box-shadow: 0 0 0 3px rgba(0, 164, 106, .10) !important;
    }

    .compra-fecha-trigger-icon {
        width: 30px;
        height: 30px;
        flex: 0 0 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9px;
        color: #00754d;
        background: #ecfdf6;
        font-size: .79rem;
    }

    .compra-fecha-trigger-copy {
        min-width: 0;
        flex: 1 1 auto;
    }

    .compra-fecha-trigger-label {
        display: block;
        margin-bottom: 2px;
        color: #94a3b8;
        font-size: .57rem;
        font-weight: 700;
        letter-spacing: .045em;
        line-height: 1;
        text-transform: uppercase;
    }

    .compra-fecha-trigger-texto {
        display: block;
        min-width: 0;
        overflow: hidden;
        color: #36453d;
        font-size: .79rem;
        font-weight: 500;
        line-height: 1.2;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .compra-fecha-trigger-texto.is-empty {
        color: #94a3b8;
        font-weight: 400;
    }

    .compra-fecha-trigger-chevron {
        flex: 0 0 auto;
        color: #8a9890;
        font-size: .65rem;
    }

    #modalCompraFecha .compra-fecha-modal-dialog {
        width: auto;
        max-width: 430px;
    }

    #modalCompraFecha .modal-content {
        overflow: hidden;
        border: 0;
        border-radius: 20px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
    }

    #modalCompraFecha .modal-header {
        align-items: center;
        padding: 16px 18px;
        border-bottom: 1px solid #eef2f0;
        background: #fff;
    }

    .compra-fecha-modal-icon {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        color: #00754d;
        background: #ecfdf6;
        font-size: .92rem;
    }

    #modalCompraFecha .compra-fecha-modal-close {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        padding: 0;
        border: 0;
        border-radius: 11px;
        color: #64748b;
        background: #f8fafc;
    }

    #modalCompraFecha .compra-fecha-modal-close:hover {
        color: #334155;
        background: #f1f5f9;
    }

    #modalCompraFecha button:focus,
    #modalCompraFecha button:active,
    #modalCompraFecha button:focus-visible {
        outline: none !important;
    }

    #modalCompraFecha button:focus-visible {
        box-shadow: 0 0 0 3px rgba(0, 164, 106, .14) !important;
    }

    .compra-calendario {
        padding: 13px;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #fff;
        user-select: none;
    }

    .compra-calendario-nav {
        display: grid;
        grid-template-columns: 38px minmax(0, 1fr) 38px;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
    }

    .compra-calendario-nav-btn {
        width: 38px;
        height: 38px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e3ebe6;
        border-radius: 11px;
        color: #526159;
        background: #fff;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .compra-calendario-nav-btn:hover:not(:disabled) {
        color: #00754d;
        border-color: #cce4d1;
        background: #f4fbf5;
    }

    .compra-calendario-nav-btn:disabled {
        opacity: .35;
        cursor: not-allowed;
    }

    .compra-calendario-mes {
        overflow: hidden;
        color: #26332c;
        font-size: .92rem;
        font-weight: 600;
        text-align: center;
        text-transform: capitalize;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .compra-calendario-semana,
    .compra-calendario-dias {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 5px;
    }

    .compra-calendario-semana {
        margin-bottom: 6px;
    }

    .compra-calendario-semana span {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 24px;
        color: #91a097;
        font-size: .66rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .compra-calendario-dia {
        width: 100%;
        aspect-ratio: 1 / 1;
        min-width: 0;
        min-height: 34px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid transparent;
        border-radius: 11px;
        color: #435149;
        background: transparent;
        font-size: .78rem;
        font-weight: 500;
        line-height: 1;
        transition: background-color .14s ease, border-color .14s ease, color .14s ease;
    }

    .compra-calendario-dia:hover:not(:disabled):not(.is-empty) {
        color: #00754d;
        border-color: #d4ead8;
        background: #f2faf4;
    }

    .compra-calendario-dia.is-today:not(.is-selected) {
        color: #00754d;
        border-color: #bfe1c6;
        background: #f7fcf8;
    }

    .compra-calendario-dia.is-selected {
        color: #fff;
        border-color: #00a46a;
        background: #00a46a;
        box-shadow: 0 6px 14px rgba(0, 164, 106, .22);
    }

    .compra-calendario-dia.is-disabled,
    .compra-calendario-dia:disabled {
        color: #c7d0ca;
        background: transparent;
        cursor: not-allowed;
    }

    .compra-calendario-dia.is-empty {
        pointer-events: none;
    }

    .compra-fecha-seleccion-resumen {
        margin-top: 12px;
        padding: 10px 12px;
        border: 1px solid #d7f7e9;
        border-radius: 12px;
        background: #ecfdf6;
    }

    .compra-fecha-modal-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 12px 18px;
        border-top: 1px solid #eef2f0;
        background: #fff;
    }

    .compra-fecha-hoy-btn,
    .compra-fecha-cerrar-btn {
        min-height: 40px;
        padding: 0 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border-radius: 11px;
        font-size: .78rem;
        font-weight: 500;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .compra-fecha-hoy-btn {
        color: #00754d;
        border: 1px solid #bfe0c6;
        background: #f4fbf5;
    }

    .compra-fecha-hoy-btn:hover {
        color: #fff;
        border-color: #00a46a;
        background: #00a46a;
    }

    .compra-fecha-cerrar-btn {
        color: #5d6962;
        border: 1px solid #dfe6e2;
        background: #fff;
    }

    .compra-fecha-cerrar-btn:hover {
        border-color: #cfd9d3;
        background: #f7f9f8;
    }

    .compra-filter-search {
        display: flex;
        min-height: 44px;
        overflow: hidden;
        align-items: stretch;
        border: 1px solid #dce4df;
        border-radius: 12px;
        background: #fff;
        transition: border-color .16s ease, box-shadow .16s ease;
    }

    .compra-filter-search:hover {
        border-color: #c9d7cf;
    }

    .compra-filter-search:focus-within {
        border-color: #00a46a;
        box-shadow: 0 0 0 3px rgba(0, 164, 106, .09);
    }

    .compra-search-icon {
        display: flex;
        width: 42px;
        flex: 0 0 42px;
        align-items: center;
        justify-content: center;
        border-right: 1px solid #eef3f0;
        color: #008d5b;
        background: #f8fcfa;
        font-size: .82rem;
        pointer-events: none;
    }

    .compra-filter-search .form-control {
        min-width: 0;
        min-height: 42px;
        height: 42px;
        padding: 0 13px !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    .compra-filter-search .form-control::placeholder {
        color: #94a3b8;
        opacity: 1;
    }

    .compra-filter-reset {
        min-height: 40px;
        height: 40px;
        padding: 7px 12px;
        border-radius: 9px;
        font-size: .8rem;
        font-weight: 500;
        white-space: nowrap;
    }

    .compra-list-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }

    .compra-period-summary {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 30px;
        padding: 5px 10px;
        border: 1px solid #e1e8e3;
        border-radius: 999px;
        color: #607068;
        background: #fff;
        font-size: .75rem;
        line-height: 1.25;
    }

    .compra-period-summary i {
        color: #278c46;
    }

    .compra-result-count {
        color: #8a958e;
        font-size: .75rem;
        white-space: nowrap;
    }


    .compra-header-actions {
        flex: 0 0 auto;
    }

    .compra-export-dropdown {
        position: relative;
    }

    .compra-export-btn {
        border: 1px solid #dbe3de !important;
        color: #42534a !important;
        background: #fff !important;
        box-shadow: 0 5px 14px rgba(15, 23, 42, .04) !important;
        white-space: nowrap;
    }

    .compra-export-btn:hover,
    .compra-export-btn:focus,
    .compra-export-dropdown.show .compra-export-btn {
        border-color: #9ed3b1 !important;
        color: #00754d !important;
        background: #f4fbf7 !important;
    }

    .compra-export-caret {
        margin-left: 2px;
        font-size: .66rem;
        opacity: .72;
        transition: transform .16s ease;
    }

    .compra-export-dropdown.show .compra-export-caret {
        transform: rotate(180deg);
    }

    .compra-export-menu {
        min-width: 260px;
        margin-top: 8px;
        padding: 7px;
        border: 1px solid #e2e8e4;
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 16px 36px rgba(15, 23, 42, .12);
    }

    .compra-export-option {
        display: flex !important;
        align-items: center;
        gap: 10px;
        padding: 9px 10px !important;
        border: 0 !important;
        border-radius: 9px;
        color: #334155 !important;
        background: transparent !important;
        white-space: normal;
    }

    .compra-export-option + .compra-export-option {
        margin-top: 3px;
    }

    .compra-export-option:hover,
    .compra-export-option:focus {
        color: #1f3d31 !important;
        background: #f4f8f6 !important;
        outline: none;
    }

    .compra-export-option-icon {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9px;
        background: #f1f5f3;
        font-size: .95rem;
    }

    .compra-export-option-excel {
        color: #1f7a4d;
        background: #edf8f1;
    }

    .compra-export-option-pdf {
        color: #b23a3a;
        background: #fff1f1;
    }

    .compra-export-option strong,
    .compra-export-option small {
        display: block;
    }

    .compra-export-option strong {
        font-size: .8rem;
        font-weight: 700;
        line-height: 1.25;
    }

    .compra-export-option small {
        margin-top: 2px;
        color: #7b8780;
        font-size: .68rem;
        line-height: 1.25;
    }

    /* Los botones nativos de DataTables se mantienen como motor de exportación,
       pero no se muestran: la UI visible es el desplegable del encabezado. */
    .compra-page .dataTables_wrapper > .dt-buttons,
    .compra-page .compra-export-engine {
        display: none !important;
    }

    .compra-table-wrap {
        overflow: hidden;
        border: 1px solid #e5ebe7;
        border-radius: 13px;
        background: #fff;
    }

    .compra-table-scroll {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    #tbllistado {
        margin: 0 !important;
    }

    #tbllistado thead th {
        padding: 12px 10px;
        border-top: 0;
        border-bottom: 1px solid #dde5e0;
        color: #657269;
        background: #f7f9f8;
        font-size: .69rem;
        font-weight: 700;
        letter-spacing: .025em;
        text-transform: uppercase;
        vertical-align: middle;
        white-space: nowrap;
    }

    #tbllistado tbody td {
        padding: 11px 10px;
        border-top-color: #edf1ef;
        color: #3f4a43;
        font-size: .8rem;
        vertical-align: middle;
    }

    #tbllistado tbody tr:hover {
        background: #fbfcfb;
    }

    #tbllistado tbody td:nth-child(8) {
        color: #26332b;
        font-weight: 700;
    }

    #tbllistado .btn {
        border-radius: 8px;
        box-shadow: none !important;
        font-weight: 500;
    }

    #tbllistado_wrapper .dataTables_info {
        padding-top: 12px;
        color: #8a958e;
        font-size: .75rem;
    }

    #tbllistado_wrapper .dataTables_paginate {
        padding-top: 8px;
    }

    #tbllistado_wrapper .pagination .page-link {
        min-width: 33px;
        border-color: #e1e7e3;
        color: #606c64;
        font-size: .76rem;
        box-shadow: none;
    }

    #tbllistado_wrapper .pagination .page-item.active .page-link {
        border-color: #00a46a;
        color: #fff;
        background: #00a46a;
    }

    @media (max-width: 1199.98px) {
        .compra-filter-grid {
            grid-template-columns: minmax(150px, .75fr) minmax(360px, 1.5fr) minmax(230px, 1fr);
        }

        .compra-filter-reset {
            grid-column: 1 / -1;
            width: 100%;
        }
    }

    @media (max-width: 991.98px) {
        .compra-actions {
            grid-template-columns: 1fr;
        }
    }


    .compra-page button,
    .modal-compra button {
        font-weight: 500;
    }

    .compra-page button:focus,
    .compra-page button:active,
    .compra-page button:focus-visible,
    .modal-compra button:focus,
    .modal-compra button:active,
    .modal-compra button:focus-visible {
        outline: none !important;
        box-shadow: none !important;
    }

    .compra-field-label {
        display: block;
        margin-bottom: 7px;
        color: #475569;
        font-size: .78rem;
        font-weight: 600;
    }

    .compra-form-section .form-control {
        min-height: 46px;
        border: 1px solid #dbe4df;
        border-radius: 13px;
        background: #fff;
        color: #334155;
        font-size: .875rem;
        font-weight: 400;
        padding-left: 14px;
        padding-right: 14px;
    }

    .compra-form-section textarea.form-control {
        min-height: 88px;
        padding-top: 11px;
        padding-bottom: 11px;
        resize: vertical;
    }

    .compra-form-section .form-control::placeholder {
        color: #94a3b8;
        opacity: 1;
    }

    .compra-form-section .form-control:focus {
        border-color: #00a46a;
        box-shadow: 0 0 0 3px rgba(0, 164, 106, .10);
    }

    .compra-action-btn {
        position: relative;
        overflow: hidden;
        border-color: #e2e8f0;
        border-radius: 16px;
        background: #fff;
    }

    .compra-action-btn::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 4px;
        height: 100%;
        background: transparent;
        transition: background-color .15s ease;
    }

    .compra-action-btn:hover::after {
        background: #00a46a;
    }

    .compra-action-icon {
        color: #00754d;
        background: #ecfdf6;
    }

    .compra-primary-btn:disabled {
        box-shadow: none !important;
    }

    .compra-save-bar {
        backdrop-filter: blur(8px);
    }

    .modal-compra .modal-dialog {
        padding-left: 10px;
        padding-right: 10px;
    }

    .modal-compra .modal-content {
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, .9);
        border-radius: 20px;
    }

    .modal-compra .modal-header {
        padding: 18px 20px;
        background: linear-gradient(90deg, #ffffff 0%, #ffffff 68%, #ecfdf6 100%);
    }

    .modal-compra .modal-title {
        color: #0f172a;
        font-size: 1rem;
        font-weight: 600;
    }

    .modal-compra .modal-body {
        padding: 20px;
    }

    .modal-compra .modal-footer {
        padding: 14px 20px;
        background: #f8fafc;
    }

    .modal-compra .form-control {
        min-height: 44px;
        border-color: #dbe4df;
        border-radius: 12px;
        color: #334155;
        font-size: .86rem;
        font-weight: 400;
    }

    .modal-compra .form-control:focus {
        border-color: #00a46a;
        box-shadow: 0 0 0 3px rgba(0, 164, 106, .10);
    }

    .modal-compra .btn-success,
    .modal-compra .btn-primary {
        border-color: #00a46a !important;
        background: #00a46a !important;
    }

    .modal-compra .btn-success:hover,
    .modal-compra .btn-primary:hover {
        border-color: #008d5b !important;
        background: #008d5b !important;
    }

    @media (max-width: 767.98px) {
        .compra-page .card-body {
            padding-left: 14px;
            padding-right: 14px;
        }

        .compra-list-header {
            align-items: stretch !important;
            padding: 16px;
        }

        .compra-header-actions {
            width: 100%;
            margin-top: 4px;
        }

        .compra-nueva-btn,
        .compra-export-dropdown,
        .compra-export-btn {
            width: 100%;
        }

        .compra-filter-grid {
            grid-template-columns: 1fr;
        }

        .compra-filter-field-search,
        .compra-filter-field-dates {
            grid-column: auto;
        }


        .compra-date-trigger-grid {
            grid-template-columns: 1fr;
        }

        .compra-list-meta {
            align-items: flex-start;
            flex-direction: column;
        }

        #modalCompraFecha .compra-fecha-modal-dialog {
            max-width: calc(100% - 22px);
            margin: 11px auto;
        }

        .compra-calendario {
            padding: 10px;
        }

        .compra-calendario-semana,
        .compra-calendario-dias {
            gap: 3px;
        }

        .compra-calendario-dia {
            min-height: 36px;
            border-radius: 10px;
        }

        .compra-action-btn {
            min-height: 66px;
        }
    }


    /* Proveedor + mini Excel de productos nuevos */
    .compra-provider-select {
        min-width: 0;
        flex: 1 1 auto;
    }

    .compra-mode-tab.is-active {
        border-color: #a7e0c5 !important;
        background: #ecfdf5 !important;
        color: #00754d !important;
        box-shadow: inset 0 0 0 1px rgba(0, 164, 106, .06);
    }

    .compra-api-status.is-success {
        border-color: #bbf7d0;
        background: #f0fdf4;
        color: #166534;
    }

    .compra-api-status.is-error {
        border-color: #fecaca;
        background: #fef2f2;
        color: #991b1b;
    }


    /* Mini Excel: mismo diseño de Inventario > Productos > Importar productos */
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
    .tp-sheet { width:100%; min-width:2050px; border-collapse:separate; border-spacing:0; table-layout:fixed; }
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
    .tp-sheet-cell:disabled { background:#f8fafc; color:#a0aec0; cursor:not-allowed; }
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


</style>

<div class="main-content compra-page">
    <section class="section">
        <div class="section-body">
            <div class="tw-mx-auto tw-max-w-[1600px] tw-space-y-5">
                <div class="tw-overflow-hidden tw-rounded-[22px] tw-border tw-border-slate-200/80 tw-bg-white tw-shadow-tique-card">
                    <div class="tw-flex tw-flex-col tw-gap-4 tw-border-b tw-border-slate-100 tw-bg-gradient-to-r tw-from-white tw-via-white tw-to-tique-50/70 tw-p-5 md:tw-flex-row md:tw-items-center md:tw-justify-between md:tw-px-6">
                        <div class="tw-flex tw-items-start tw-gap-3">
                            <div class="tw-flex tw-h-11 tw-w-11 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-tique-50 tw-text-tique-700">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div>
                                <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                    <h4 class="tw-m-0 tw-text-[1.05rem] tw-font-semibold tw-text-slate-900">Compras</h4>
                                    <span class="tw-rounded-full tw-border tw-border-tique-100 tw-bg-tique-50 tw-px-2.5 tw-py-1 tw-text-[11px] tw-font-medium tw-text-tique-700">Inventario y gastos</span>
                                </div>
                                <p class="tw-mb-0 tw-mt-1 tw-text-[13px] tw-leading-5 tw-text-slate-500">
                                    Registra mercadería, servicios y comprobantes de proveedores desde un flujo más claro y rápido.
                                </p>
                            </div>
                        </div>

                        <div class="compra-header-actions tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                            <button
                                type="button"
                                class="compra-nueva-btn tw-inline-flex tw-min-h-[42px] tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border-0 tw-bg-tique-500 tw-px-4 tw-py-2.5 tw-text-[13px] tw-font-medium tw-text-white tw-shadow-[0_8px_20px_rgba(0,164,106,.18)] tw-transition hover:tw-bg-tique-600 hover:tw-shadow-[0_10px_24px_rgba(0,164,106,.24)] focus:tw-outline-none"
                                onclick="mostrarform(true)"
                                id="btnagregar">
                                <i class="fas fa-plus"></i>
                                Nueva compra
                            </button>

                            <div class="dropdown compra-export-dropdown" id="comprasExportDropdown">
                                <button
                                    type="button"
                                    class="compra-export-btn tw-inline-flex tw-min-h-[42px] tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-px-4 tw-py-2.5 tw-text-[13px] tw-font-medium tw-transition focus:tw-outline-none"
                                    id="btnExportarCompras"
                                    data-toggle="dropdown"
                                    aria-haspopup="true"
                                    aria-expanded="false">
                                    <i class="fas fa-file-export"></i>
                                    Exportar reporte
                                    <i class="fas fa-chevron-down compra-export-caret" aria-hidden="true"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-right compra-export-menu" aria-labelledby="btnExportarCompras">
                                    <button type="button" class="dropdown-item compra-export-option" data-formato="excel">
                                        <span class="compra-export-option-icon compra-export-option-excel"><i class="fas fa-file-excel"></i></span>
                                        <span>
                                            <strong>Excel</strong>
                                            <small>Exportar compras con los filtros aplicados</small>
                                        </span>
                                    </button>
                                    <button type="button" class="dropdown-item compra-export-option" data-formato="pdf">
                                        <span class="compra-export-option-icon compra-export-option-pdf"><i class="fas fa-file-pdf"></i></span>
                                        <span>
                                            <strong>PDF</strong>
                                            <small>Generar reporte PDF con los filtros aplicados</small>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tw-p-4 md:tw-p-6">
                        <div id="listadoregistros">
                            <div class="compra-list-toolbar" id="compraListToolbar">
                                <div class="compra-filter-grid">
                                    <div class="compra-filter-field">
                                        <label for="compraFiltroPeriodo">Periodo</label>
                                        <select class="form-control" id="compraFiltroPeriodo" aria-label="Filtrar compras por periodo">
                                            <option value="mes" selected>Este mes</option>
                                            <option value="hoy">Hoy</option>
                                            <option value="7dias">Últimos 7 días</option>
                                            <option value="todo">Todo el historial</option>
                                            <option value="personalizado">Personalizado</option>
                                        </select>
                                    </div>

                                    <div class="compra-filter-field compra-filter-field-dates">
                                        <label>Rango de fechas</label>
                                        <div class="compra-date-trigger-grid" role="group" aria-label="Rango de fechas de compras">
                                            <input type="hidden" id="compraFechaDesde" value="">
                                            <input type="hidden" id="compraFechaHasta" value="">

                                            <button type="button" class="compra-fecha-trigger" id="btnCompraFechaDesde" aria-haspopup="dialog" aria-controls="modalCompraFecha" aria-label="Seleccionar fecha desde">
                                                <span class="compra-fecha-trigger-icon" aria-hidden="true"><i class="far fa-calendar-alt"></i></span>
                                                <span class="compra-fecha-trigger-copy">
                                                    <span class="compra-fecha-trigger-label">Desde</span>
                                                    <span class="compra-fecha-trigger-texto" id="compraFechaDesdeTexto">Seleccionar fecha</span>
                                                </span>
                                                <i class="fas fa-chevron-down compra-fecha-trigger-chevron" aria-hidden="true"></i>
                                            </button>

                                            <button type="button" class="compra-fecha-trigger" id="btnCompraFechaHasta" aria-haspopup="dialog" aria-controls="modalCompraFecha" aria-label="Seleccionar fecha hasta">
                                                <span class="compra-fecha-trigger-icon" aria-hidden="true"><i class="far fa-calendar-check"></i></span>
                                                <span class="compra-fecha-trigger-copy">
                                                    <span class="compra-fecha-trigger-label">Hasta</span>
                                                    <span class="compra-fecha-trigger-texto" id="compraFechaHastaTexto">Seleccionar fecha</span>
                                                </span>
                                                <i class="fas fa-chevron-down compra-fecha-trigger-chevron" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="compra-filter-field compra-filter-field-search">
                                        <label for="compraBuscar">Buscar</label>
                                        <div class="compra-filter-search">
                                            <span class="compra-search-icon" aria-hidden="true"><i class="fas fa-search"></i></span>
                                            <input type="search" class="form-control" id="compraBuscar" autocomplete="off" placeholder="Proveedor, documento, número...">
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-outline-secondary compra-filter-reset" id="btnLimpiarFiltroCompras" title="Restablecer al mes actual">
                                        <i class="fas fa-undo-alt mr-1"></i>
                                        Restablecer
                                    </button>
                                </div>
                            </div>

                            <div class="compra-list-meta">
                                <div class="compra-period-summary" id="compraPeriodoResumen">
                                    <i class="far fa-calendar-alt" aria-hidden="true"></i>
                                    <span>Compras del mes actual</span>
                                </div>
                                <span class="compra-result-count" id="compraResultadoCount">0 registros</span>
                            </div>

                            <div class="compra-table-wrap">
                                <div class="compra-table-scroll">
                                    <table id="tbllistado" class="table table-hover text-nowrap" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>Acciones</th>
                                                <th>Fecha</th>
                                                <th>Proveedor</th>
                                                <th>Usuario</th>
                                                <th>Documento</th>
                                                <th>Número</th>
                                                <th>Tipo de compra</th>
                                                <th>Total</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div id="formularioregistros" style="display:none;">
                            <form name="formulario" id="formulario" method="POST" autocomplete="off">
                                <input type="hidden" name="idingreso" id="idingreso" value="">
                                <input type="hidden" name="detalles_json" id="detalles_json" value="[]">
                                <input type="hidden" name="total_compra" id="total_compra" value="0.00">

                                <div class="tw-mb-5 tw-flex tw-flex-col tw-gap-3 tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-4 md:tw-flex-row md:tw-items-center md:tw-justify-between">
                                    <div class="tw-flex tw-items-center tw-gap-3">
                                        <span class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-rounded-xl tw-bg-tique-50 tw-text-tique-700">
                                            <i class="fas fa-file-invoice"></i>
                                        </span>
                                        <div>
                                            <h5 class="tw-m-0 tw-text-[15px] tw-font-semibold tw-text-slate-900">Datos de la compra</h5>
                                            <p class="tw-mb-0 tw-mt-0.5 tw-text-[12px] tw-text-slate-500">Completa el comprobante y luego agrega los productos, gastos o servicios.</p>
                                        </div>
                                    </div>
                                    <div class="tw-flex tw-items-center tw-gap-2 tw-text-[11px] tw-text-slate-500">
                                        <i class="fas fa-asterisk tw-text-rose-500"></i>
                                        Los campos marcados son obligatorios
                                    </div>
                                </div>

                                <div class="compra-form-section tw-grid tw-grid-cols-1 tw-gap-x-4 tw-gap-y-5 md:tw-grid-cols-12">
                                    <div class="md:tw-col-span-6">
                                        <label class="compra-field-label" for="idproveedor">Proveedor <span class="text-danger">*</span></label>
                                        <div class="tw-flex tw-flex-col tw-gap-2 sm:tw-flex-row sm:tw-items-stretch">
                                            <select name="idproveedor" id="idproveedor" class="form-control compra-provider-select" required>
                                                <option value="">Cargando proveedores...</option>
                                            </select>
                                            <button
                                                type="button"
                                                id="btnNuevoProveedorCompra"
                                                class="tw-inline-flex tw-min-h-[44px] tw-shrink-0 tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border tw-border-tique-200 tw-bg-tique-50 tw-px-4 tw-text-[12px] tw-font-semibold tw-text-tique-700 tw-transition hover:tw-border-tique-300 hover:tw-bg-tique-100 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-500/10">
                                                <i class="fas fa-user-plus"></i>
                                                Nuevo / consultar
                                            </button>
                                        </div>
                                        <div class="tw-mt-1.5 tw-flex tw-items-center tw-gap-1.5 tw-text-[11px] tw-text-slate-500">
                                            <i class="fas fa-bolt tw-text-amber-500"></i>
                                            Puedes consultar DNI o RUC en PeruDev y registrar al proveedor sin salir de la compra.
                                        </div>
                                    </div>

                                    <div class="md:tw-col-span-3">
                                        <label class="compra-field-label" for="fecha_hora">Fecha <span class="text-danger">*</span></label>
                                        <input class="form-control" type="date" name="fecha_hora" id="fecha_hora" required>
                                    </div>

                                    <div class="md:tw-col-span-3">
                                        <label class="compra-field-label" for="impuesto">Impuesto</label>
                                        <select class="form-control" name="impuesto" id="impuesto">
                                            <option value="18">IGV 18% incluido</option>
                                            <option value="0">Sin IGV</option>
                                        </select>
                                    </div>

                                    <div class="md:tw-col-span-4">
                                        <label class="compra-field-label" for="tipo_comprobante">Tipo de comprobante <span class="text-danger">*</span></label>
                                        <select name="tipo_comprobante" id="tipo_comprobante" class="form-control" required>
                                            <option value="Factura">Factura</option>
                                            <option value="Boleta">Boleta</option>
                                            <option value="Ticket">Ticket</option>
                                            <option value="Recibo">Recibo</option>
                                            <option value="Otro">Otro</option>
                                        </select>
                                    </div>

                                    <div class="md:tw-col-span-4">
                                        <label class="compra-field-label" for="serie_comprobante">Serie</label>
                                        <input class="form-control text-uppercase" type="text" name="serie_comprobante" id="serie_comprobante" maxlength="7" placeholder="Ej.: F001">
                                    </div>

                                    <div class="md:tw-col-span-4">
                                        <label class="compra-field-label" for="num_comprobante">Número <span class="text-danger">*</span></label>
                                        <input class="form-control" type="text" name="num_comprobante" id="num_comprobante" maxlength="10" placeholder="Ej.: 00001234" required>
                                    </div>

                                    <div class="md:tw-col-span-12">
                                        <label class="compra-field-label" for="observacion">Observación</label>
                                        <textarea class="form-control" name="observacion" id="observacion" maxlength="255" rows="2" placeholder="Información adicional de la compra..."></textarea>
                                    </div>

                                    <div class="md:tw-col-span-4">
                                        <label class="compra-field-label" for="condicion_pago">
                                            Condición de pago <span class="text-danger">*</span>
                                        </label>
                                        <select
                                            name="condicion_pago"
                                            id="condicion_pago"
                                            class="form-control"
                                            required>
                                            <option value="CONTADO">Contado · se paga ahora</option>
                                            <option value="CREDITO">Crédito · queda pendiente</option>
                                        </select>
                                        <small class="form-text text-muted">
                                            Crédito registra la compra sin retirar dinero de ninguna caja.
                                        </small>
                                    </div>

                                    <div class="md:tw-col-span-4" id="grupoFormaPagoCompra">
                                        <label class="compra-field-label" for="idforma_pago">
                                            Forma de pago <span class="text-danger">*</span>
                                        </label>
                                        <select
                                            name="idforma_pago"
                                            id="idforma_pago"
                                            class="form-control">
                                            <option value="">Cargando formas de pago...</option>
                                        </select>
                                        <small
                                            id="ayudaFormaPagoCompra"
                                            class="form-text text-muted">
                                            Efectivo exige una caja seleccionada y una apertura activa.
                                        </small>
                                    </div>

                                    <div class="md:tw-col-span-4" id="grupoOperacionCompra">
                                        <label class="compra-field-label" for="numero_operacion">
                                            N.º de operación
                                        </label>
                                        <input
                                            class="form-control"
                                            type="text"
                                            name="numero_operacion"
                                            id="numero_operacion"
                                            maxlength="80"
                                            placeholder="Ej.: operación Yape, Plin o tarjeta">
                                        <small
                                            id="ayudaOperacionCompra"
                                            class="form-text text-muted">
                                            Se solicitará cuando la forma de pago lo requiera.
                                        </small>
                                    </div>

                                    <div class="md:tw-col-span-12">
                                        <div
                                            id="avisoTrazabilidadCompra"
                                            class="tw-flex tw-items-start tw-gap-2 tw-rounded-xl tw-border tw-border-emerald-100 tw-bg-emerald-50/70 tw-px-3 tw-py-2.5 tw-text-[12px] tw-text-emerald-800">
                                            <i class="fas fa-shield-alt tw-mt-0.5"></i>
                                            <span>
                                                Los pagos en efectivo quedan vinculados a la caja y apertura activa.
                                                Si la caja está cerrada, el servidor rechazará el pago.
                                            </span>
                                        </div>
                                    </div>

                                </div>

                                <div class="tw-my-6 tw-border-t tw-border-slate-100"></div>

                                <div class="tw-mb-3 tw-flex tw-flex-col tw-gap-1 sm:tw-flex-row sm:tw-items-end sm:tw-justify-between">
                                    <div>
                                        <h6 class="tw-m-0 tw-text-[14px] tw-font-semibold tw-text-slate-900">Detalles de la compra</h6>
                                        <p class="tw-mb-0 tw-mt-1 tw-text-[12px] tw-text-slate-500">Puedes combinar productos de inventario con gastos o servicios.</p>
                                    </div>
                                    <span class="tw-mt-2 tw-inline-flex tw-w-fit tw-items-center tw-gap-1.5 tw-rounded-full tw-bg-slate-100 tw-px-2.5 tw-py-1 tw-text-[11px] tw-text-slate-500 sm:tw-mt-0">
                                        <i class="fas fa-info-circle"></i>
                                        El stock se actualiza al guardar
                                    </span>
                                </div>

                                <div class="compra-actions">
                                    <button type="button" class="compra-action-btn" id="btnProductoExistente">
                                        <span class="compra-action-icon"><i class="fas fa-box"></i></span>
                                        <span>
                                            <span class="compra-action-title d-block">Producto existente</span>
                                            <span class="compra-action-help d-block">Compra mercadería registrada y aumenta su stock.</span>
                                        </span>
                                    </button>

                                    <button type="button" class="compra-action-btn" id="btnProductoNuevo">
                                        <span class="compra-action-icon"><i class="fas fa-box-open"></i></span>
                                        <span>
                                            <span class="compra-action-title d-block">Producto nuevo</span>
                                            <span class="compra-action-help d-block">Crea el producto al guardar la compra, sin duplicar stock.</span>
                                        </span>
                                    </button>

                                    <button type="button" class="compra-action-btn" id="btnGastoServicio">
                                        <span class="compra-action-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                                        <span>
                                            <span class="compra-action-title d-block">Gasto o servicio</span>
                                            <span class="compra-action-help d-block">Registra transporte, alquiler, publicidad u otros consumos.</span>
                                        </span>
                                    </button>
                                </div>

                                <div class="tw-mt-5 tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white">
                                    <div class="table-responsive tw-m-0">
                                        <table class="table detalle-compra-table tw-m-0" id="detalles">
                                            <thead>
                                                <tr>
                                                    <th>Tipo</th>
                                                    <th style="min-width:220px;">Descripción</th>
                                                    <th>Cantidad</th>
                                                    <th>Costo unitario</th>
                                                    <th>Precio venta</th>
                                                    <th>Importe</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody id="detallesCompraBody"></tbody>
                                        </table>

                                        <div class="compra-empty" id="detalleCompraVacio">
                                            <span class="tw-mx-auto tw-mb-3 tw-flex tw-h-12 tw-w-12 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-slate-100 tw-text-slate-400">
                                                <i class="fas fa-shopping-basket tw-m-0"></i>
                                            </span>
                                            <div class="tw-text-[13px] tw-font-medium tw-text-slate-700">Todavía no agregaste detalles</div>
                                            <small>Selecciona un producto existente, registra uno nuevo o agrega un gasto.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="compra-save-bar tw-mt-5 tw-flex tw-flex-col tw-gap-4 tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/80 tw-p-4 lg:tw-flex-row lg:tw-items-end lg:tw-justify-between">
                                    <div class="tw-flex tw-items-start tw-gap-3">
                                        <span class="tw-flex tw-h-10 tw-w-10 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-xl tw-bg-white tw-text-tique-700 tw-shadow-sm">
                                            <i class="fas fa-calculator"></i>
                                        </span>
                                        <div>
                                            <div class="tw-text-xs tw-font-medium tw-text-slate-500">Resumen de compra</div>
                                            <div class="tw-mt-0.5 tw-text-[12px] tw-text-slate-600">Los importes se recalculan automáticamente.</div>
                                        </div>
                                    </div>

                                    <div class="tw-flex tw-flex-col tw-gap-3 sm:tw-flex-row sm:tw-items-end">
                                        <div class="compra-total-box tw-min-w-[280px]">
                                            <div class="compra-total-row">
                                                <span>Subtotal</span>
                                                <strong id="total">S/ 0.00</strong>
                                            </div>
                                            <div class="compra-total-row">
                                                <span id="labelImpuestoTotal">IGV 18%</span>
                                                <strong id="most_imp">S/ 0.00</strong>
                                            </div>
                                            <div class="compra-total-row total-final">
                                                <span>Total compra</span>
                                                <strong id="most_total">S/ 0.00</strong>
                                            </div>
                                        </div>

                                        <div class="tw-flex tw-flex-col-reverse tw-gap-2 sm:tw-flex-row">
                                            <button class="compra-secondary-btn tw-inline-flex tw-min-h-[42px] tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-px-4 tw-py-2.5 tw-text-[13px] tw-font-medium tw-text-slate-600 tw-transition hover:tw-border-slate-300 hover:tw-bg-slate-50 focus:tw-outline-none" onclick="cancelarform()" type="button" id="btnCancelar">
                                                <i class="fas fa-arrow-left"></i>
                                                Cancelar
                                            </button>

                                            <button class="compra-primary-btn tw-inline-flex tw-min-h-[42px] tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border-0 tw-bg-tique-500 tw-px-5 tw-py-2.5 tw-text-[13px] tw-font-medium tw-text-white tw-shadow-[0_8px_20px_rgba(0,164,106,.18)] tw-transition hover:tw-bg-tique-600 disabled:tw-cursor-not-allowed disabled:tw-opacity-60 focus:tw-outline-none" type="submit" id="btnGuardar" disabled>
                                                <i class="fas fa-save"></i>
                                                Guardar compra
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- PROVEEDOR: CREAR / CONSULTAR PERUDEV -->
<div class="modal fade modal-compra" id="modalProveedorCompra" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content tw-overflow-hidden">
            <form id="formProveedorCompra" autocomplete="off">
                <div class="tw-flex tw-items-start tw-justify-between tw-border-b tw-border-slate-100 tw-bg-gradient-to-r tw-from-white tw-to-tique-50/60 tw-p-5">
                    <div class="tw-flex tw-items-start tw-gap-3">
                        <span class="tw-flex tw-h-11 tw-w-11 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-tique-50 tw-text-tique-700">
                            <i class="fas fa-building"></i>
                        </span>
                        <div>
                            <h5 class="tw-m-0 tw-text-[16px] tw-font-semibold tw-text-slate-900">Nuevo proveedor</h5>
                            <p class="tw-mb-0 tw-mt-1 tw-text-[12px] tw-text-slate-500">Consulta DNI o RUC en PeruDev y completa los datos antes de guardarlo.</p>
                        </div>
                    </div>
                    <button type="button" class="close tw-ml-3" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>

                <div class="modal-body tw-bg-slate-50/50 tw-p-5">
                    <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-p-4 tw-shadow-sm">
                        <div class="tw-grid tw-grid-cols-1 tw-gap-3 md:tw-grid-cols-12">
                            <div class="md:tw-col-span-3">
                                <label class="compra-field-label" for="proveedor_tipo_documento">Documento</label>
                                <select class="form-control" id="proveedor_tipo_documento">
                                    <option value="RUC">RUC</option>
                                    <option value="DNI">DNI</option>
                                </select>
                            </div>
                            <div class="md:tw-col-span-6">
                                <label class="compra-field-label" for="proveedor_num_documento">Número <span class="text-danger">*</span></label>
                                <input type="text" inputmode="numeric" class="form-control" id="proveedor_num_documento" maxlength="11" placeholder="Ingresa el RUC" required>
                            </div>
                            <div class="tw-flex tw-items-end md:tw-col-span-3">
                                <button type="button" id="btnConsultarProveedorApi" class="tw-inline-flex tw-min-h-[44px] tw-w-full tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border-0 tw-bg-slate-900 tw-px-4 tw-text-[12px] tw-font-semibold tw-text-white tw-transition hover:tw-bg-slate-800 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-slate-900/10">
                                    <i class="fas fa-search"></i>
                                    Consultar PeruDev
                                </button>
                            </div>
                        </div>

                        <div id="proveedorApiEstado" class="compra-api-status tw-mt-3 tw-hidden tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-px-3 tw-py-2.5 tw-text-[12px]"></div>
                    </div>

                    <div class="tw-mt-4 tw-grid tw-grid-cols-1 tw-gap-4 md:tw-grid-cols-12">
                        <div class="md:tw-col-span-12">
                            <label class="compra-field-label" for="proveedor_nombre">Nombre / razón social <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="proveedor_nombre" maxlength="160" required placeholder="Se completará con PeruDev o puedes escribirlo manualmente">
                        </div>
                        <div class="md:tw-col-span-12">
                            <label class="compra-field-label" for="proveedor_direccion">Dirección</label>
                            <input type="text" class="form-control" id="proveedor_direccion" maxlength="220" placeholder="Dirección fiscal o comercial">
                        </div>
                        <div class="md:tw-col-span-6">
                            <label class="compra-field-label" for="proveedor_telefono">Teléfono</label>
                            <input type="text" class="form-control" id="proveedor_telefono" maxlength="30" placeholder="Opcional">
                        </div>
                        <div class="md:tw-col-span-6">
                            <label class="compra-field-label" for="proveedor_email">Correo</label>
                            <input type="email" class="form-control" id="proveedor_email" maxlength="120" placeholder="Opcional">
                        </div>
                    </div>
                </div>

                <div class="modal-footer tw-flex tw-items-center tw-justify-end tw-gap-2 tw-border-t tw-border-slate-100 tw-bg-white tw-px-5 tw-py-4">
                    <button type="button" class="tw-inline-flex tw-min-h-[40px] tw-items-center tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-px-4 tw-text-[12px] tw-font-medium tw-text-slate-600 hover:tw-bg-slate-50" data-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btnGuardarProveedorCompra" class="tw-inline-flex tw-min-h-[40px] tw-items-center tw-gap-2 tw-rounded-xl tw-border-0 tw-bg-tique-500 tw-px-4 tw-text-[12px] tw-font-semibold tw-text-white tw-shadow-sm tw-transition hover:tw-bg-tique-600 disabled:tw-opacity-60">
                        <i class="fas fa-save"></i>
                        Guardar proveedor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PRODUCTO EXISTENTE -->
<div class="modal fade modal-compra" id="modalProductoExistente" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Seleccionar producto existente</h5>
                    <small class="text-muted">Busca por nombre, SKU o código de barras.</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                    </div>
                    <input
                        type="text"
                        class="form-control"
                        id="buscarProductoCompra"
                        autocomplete="off"
                        placeholder="Nombre o SKU...">
                </div>

                <div id="listaProductosCompra" style="max-height:480px; overflow-y:auto;"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- PRODUCTO NUEVO: UNO POR UNO / MINI EXCEL -->
<div class="modal fade modal-compra" id="modalProductoNuevo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content tw-overflow-hidden">
            <form id="formProductoNuevo" autocomplete="off">
                <div class="tw-flex tw-flex-col tw-gap-4 tw-border-b tw-border-slate-100 tw-bg-gradient-to-r tw-from-white tw-via-white tw-to-tique-50/50 tw-p-5 sm:tw-flex-row sm:tw-items-start sm:tw-justify-between">
                    <div class="tw-flex tw-items-start tw-gap-3">
                        <span class="tw-flex tw-h-11 tw-w-11 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-tique-50 tw-text-tique-700">
                            <i class="fas fa-box-open"></i>
                        </span>
                        <div>
                            <h5 class="tw-m-0 tw-text-[16px] tw-font-semibold tw-text-slate-900">Agregar productos nuevos</h5>
                            <p class="tw-mb-0 tw-mt-1 tw-text-[12px] tw-text-slate-500">Registra uno por uno o usa el mini Excel para preparar varios productos de la misma compra.</p>
                        </div>
                    </div>
                    <button type="button" class="close tw-self-start" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>

                <div class="tw-border-b tw-border-slate-100 tw-bg-white tw-px-5 tw-pt-4">
                    <div class="tw-inline-flex tw-rounded-xl tw-bg-slate-100 tw-p-1">
                        <button type="button" class="compra-mode-tab is-active tw-inline-flex tw-min-h-[38px] tw-items-center tw-gap-2 tw-rounded-lg tw-border tw-border-transparent tw-bg-transparent tw-px-4 tw-text-[12px] tw-font-semibold tw-text-slate-600 tw-transition" data-producto-modo="individual">
                            <i class="fas fa-plus-circle"></i> Uno por uno
                        </button>
                        <button type="button" class="compra-mode-tab tw-inline-flex tw-min-h-[38px] tw-items-center tw-gap-2 tw-rounded-lg tw-border tw-border-transparent tw-bg-transparent tw-px-4 tw-text-[12px] tw-font-semibold tw-text-slate-600 tw-transition" data-producto-modo="masivo">
                            <i class="fas fa-table"></i> Mini Excel masivo
                        </button>
                    </div>
                </div>

                <div class="modal-body tw-bg-slate-50/50 tw-p-5">
                    <div id="productoModoIndividual">
                        <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-p-4 tw-shadow-sm">
                            <div class="tw-grid tw-grid-cols-1 tw-gap-4 md:tw-grid-cols-12">
                                <div class="md:tw-col-span-8">
                                    <label class="compra-field-label" for="nuevo_nombre">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nuevo_nombre" maxlength="100" required placeholder="Ej.: Polo oversize rosado">
                                </div>
                                <div class="md:tw-col-span-4">
                                    <label class="compra-field-label" for="nuevo_codigo">SKU o código</label>
                                    <input type="text" class="form-control text-uppercase" id="nuevo_codigo" maxlength="50" placeholder="Se genera si queda vacío">
                                </div>
                            </div>

                            <div class="coincidencias-producto" id="coincidenciasProductoNuevo"></div>

                            <div class="tw-mt-4 tw-grid tw-grid-cols-1 tw-gap-4 sm:tw-grid-cols-2 lg:tw-grid-cols-4">
                                <div>
                                    <label class="compra-field-label" for="nuevo_idcategoria">Categoría <span class="text-danger">*</span></label>
                                    <select class="form-control" id="nuevo_idcategoria" required></select>
                                </div>
                                <div>
                                    <label class="compra-field-label" for="nuevo_idsubcategoria">Subcategoría</label>
                                    <select class="form-control" id="nuevo_idsubcategoria"></select>
                                </div>
                                <div>
                                    <label class="compra-field-label" for="nuevo_idmedida">Unidad <span class="text-danger">*</span></label>
                                    <select class="form-control" id="nuevo_idmedida" required></select>
                                </div>
                                <div>
                                    <label class="compra-field-label" for="nuevo_idalmacen">Almacén <span class="text-danger">*</span></label>
                                    <select class="form-control" id="nuevo_idalmacen" required></select>
                                </div>
                            </div>

                            <div class="tw-mt-4 tw-grid tw-grid-cols-1 tw-gap-4 md:tw-grid-cols-3">
                                <div>
                                    <label class="compra-field-label" for="nuevo_cantidad">Cantidad comprada <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="nuevo_cantidad" min="1" step="1" value="1" required>
                                    <small class="tw-mt-1 tw-block tw-text-[11px] tw-text-slate-500">Se sumará al stock al guardar la compra.</small>
                                </div>
                                <div>
                                    <label class="compra-field-label" for="nuevo_precio_compra">Costo unitario <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">S/</span></div>
                                        <input type="number" class="form-control" id="nuevo_precio_compra" min="0.01" step="0.01" required>
                                    </div>
                                </div>
                                <div>
                                    <label class="compra-field-label" for="nuevo_precio_venta">Precio de venta</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">S/</span></div>
                                        <input type="number" class="form-control" id="nuevo_precio_venta" min="0" step="0.01" placeholder="Opcional">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="productoModoMasivo" class="tw-hidden">
                        <div id="compraPlantillaSection" class="tp-import-panel is-open tw-border tw-border-slate-200 tw-bg-white tw-shadow-xl" aria-hidden="false">
                            <div class="tp-import-head tw-bg-gradient-to-r tw-from-white tw-to-tique-50">
                                <div class="tp-import-title">
                                    <span class="tp-import-title-icon"><i class="fas fa-table"></i></span>
                                    <div>
                                        <h5>Importar Productos</h5>
                                        <p>Registra productos simples o variables en masa desde esta hoja o carga un Excel/CSV. Las variantes se agrupan por SKU padre y la afectación IGV se obtiene del catálogo tributario existente.</p>
                                    </div>
                                </div>
                                <button type="button" class="tp-import-close tw-transition-all tw-duration-200 focus:tw-outline-none" id="btnCerrarCompraMasiva" title="Cerrar"><i class="fas fa-times"></i></button>
                            </div>

                            <div class="tp-import-toolbar tw-bg-white">
                                <div class="tp-import-actions">
                                    <button type="button" class="tp-import-action is-primary tw-select-none tw-border-tique-500 tw-bg-tique-500 tw-text-white tw-shadow-md tw-transition-all tw-duration-200 hover:tw-bg-tique-600 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-500/10" id="btnAgregarFilaCompraMasiva"><i class="fas fa-plus"></i> Agregar fila</button>
                                    <label class="tp-import-action mb-0 tw-select-none tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-shadow-sm tw-transition-all tw-duration-200 hover:tw-border-tique-200 hover:tw-bg-tique-50 hover:tw-text-tique-700" for="archivoProductosCompraMasivo"><i class="fas fa-file-upload"></i> Subir Excel/CSV</label>
                                    <input class="tp-import-file" type="file" id="archivoProductosCompraMasivo" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                    <a class="tp-import-action tw-select-none tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-shadow-sm tw-transition-all tw-duration-200 hover:tw-border-tique-200 hover:tw-bg-tique-50 hover:tw-text-tique-700 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-500/10" href="Controllers/Product.php?op=descargarPlantillaExcel" download="plantilla_productos.xlsx"><i class="fas fa-file-excel"></i> Plantilla Excel</a>
                                    <a class="tp-import-action tw-select-none tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-shadow-sm tw-transition-all tw-duration-200 hover:tw-border-tique-200 hover:tw-bg-tique-50 hover:tw-text-tique-700 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-500/10" href="Controllers/Product.php?op=descargarPlantillaCsv" download="plantilla_productos.csv"><i class="fas fa-file-csv"></i> Plantilla CSV</a>
                                    <button type="button" class="tp-import-action tw-select-none tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-shadow-sm tw-transition-all tw-duration-200 hover:tw-border-tique-200 hover:tw-bg-tique-50 hover:tw-text-tique-700 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-500/10" id="btnLimpiarCompraMasiva"><i class="fas fa-eraser"></i> Limpiar</button>
                                </div>
                                <div class="tp-import-kpis">
                                    <span class="tp-import-kpi"><strong id="compraMasivoTotal">0</strong> filas</span>
                                    <span class="tp-import-kpi is-valid"><strong id="compraMasivoValidas">0</strong> válidas</span>
                                    <span class="tp-import-kpi is-error"><strong id="compraMasivoErrores">0</strong> con error</span>
                                </div>
                            </div>

                            <div class="tp-sheet-help">
                                <i class="fas fa-info-circle"></i>
                                <span><strong>Simple:</strong> una fila crea un producto. <strong>Variante:</strong> varias filas con el mismo Grupo/SKU padre crean un solo producto con sus variantes. Categoría, almacén, unidad y afectación IGV se cargan desde la base de datos.</span>
                            </div>

                            <div class="tp-sheet-wrap" id="compraMasivoSheetWrap">
                                <table class="tp-sheet" id="tablaCompraMasivaProductos">
                                    <colgroup>
                                        <col style="width:54px"><col style="width:120px"><col style="width:145px"><col style="width:210px">
                                        <col style="width:145px"><col style="width:170px"><col style="width:82px"><col style="width:108px">
                                        <col style="width:108px"><col style="width:180px"><col style="width:190px"><col style="width:170px">
                                        <col style="width:165px"><col style="width:220px"><col style="width:52px">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>#</th><th>Tipo *</th><th>Grupo / SKU padre</th><th>Producto *</th><th>SKU *</th><th>Variante</th>
                                            <th>Stock</th><th>P. compra</th><th>P. venta *</th><th>Categoría *</th><th>Subcategoría</th>
                                            <th>Almacén *</th><th>Unidad *</th><th>Afectación IGV *</th><th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="compraMasivoBody"></tbody>
                                </table>
                                <div class="tp-import-empty" id="compraMasivoEmpty" style="display:none;"><i class="fas fa-border-all"></i>Agrega una fila o pega directamente desde Excel sobre la primera celda.</div>
                            </div>

                            <div class="tp-import-foot">
                                <div class="tp-import-status" id="compraMasivoEstado">Carga los catálogos para comenzar.</div>
                                <button type="button" class="tp-import-submit tw-select-none tw-bg-tique-500 tw-text-white tw-shadow-lg tw-transition-all tw-duration-200 hover:tw-bg-tique-600 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-500/10" id="btnAgregarProductosMasivos" disabled><i class="fas fa-cart-plus"></i> Agregar productos válidos</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer tw-flex tw-items-center tw-justify-end tw-gap-2 tw-border-t tw-border-slate-100 tw-bg-white tw-px-5 tw-py-4">
                    <button type="button" class="tw-inline-flex tw-min-h-[40px] tw-items-center tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-px-4 tw-text-[12px] tw-font-medium tw-text-slate-600 hover:tw-bg-slate-50" data-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btnAgregarProductoIndividual" class="tw-inline-flex tw-min-h-[40px] tw-items-center tw-gap-2 tw-rounded-xl tw-border-0 tw-bg-tique-500 tw-px-4 tw-text-[12px] tw-font-semibold tw-text-white tw-shadow-sm tw-transition hover:tw-bg-tique-600">
                        <i class="fas fa-plus"></i> Agregar a la compra
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- GASTO O SERVICIO -->
<div class="modal fade modal-compra" id="modalGastoServicio" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="formGastoServicio" autocomplete="off">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Agregar gasto o servicio</h5>
                        <small class="text-muted">No modificará el stock ni generará movimiento de kardex.</small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="form-group col-lg-8 col-md-8">
                            <label for="gasto_descripcion">Descripción <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="gasto_descripcion"
                                maxlength="250"
                                required
                                placeholder="Ej.: Servicio de transporte de mercadería">
                        </div>

                        <div class="form-group col-lg-4 col-md-4">
                            <label for="gasto_categoria">Categoría <span class="text-danger">*</span></label>
                            <select class="form-control" id="gasto_categoria" required></select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-lg-4 col-md-4">
                            <label for="gasto_idmedida">Unidad</label>
                            <select class="form-control" id="gasto_idmedida"></select>
                        </div>

                        <div class="form-group col-lg-4 col-md-4">
                            <label for="gasto_cantidad">Cantidad <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                class="form-control"
                                id="gasto_cantidad"
                                min="0.001"
                                step="0.001"
                                value="1"
                                required>
                        </div>

                        <div class="form-group col-lg-4 col-md-4">
                            <label for="gasto_precio">Costo unitario <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">S/</span>
                                </div>
                                <input
                                    type="number"
                                    class="form-control"
                                    id="gasto_precio"
                                    min="0.01"
                                    step="0.01"
                                    required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus mr-1"></i>
                        Agregar a la compra
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SELECTOR MODERNO DE FECHAS DEL HISTORIAL -->
<div class="modal fade" id="modalCompraFecha" tabindex="-1" role="dialog" aria-labelledby="compraFechaModalTitulo" aria-hidden="true" data-backdrop="static" data-keyboard="true">
    <div class="modal-dialog modal-dialog-centered compra-fecha-modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="tw-flex tw-min-w-0 tw-items-center tw-gap-3">
                    <span class="compra-fecha-modal-icon" aria-hidden="true"><i class="far fa-calendar-alt"></i></span>
                    <div class="tw-min-w-0">
                        <h5 class="modal-title tw-m-0 tw-text-[15px] tw-font-medium tw-text-slate-900" id="compraFechaModalTitulo">Seleccionar fecha</h5>
                        <small class="tw-mt-1 tw-block tw-text-[12px] tw-text-slate-500" id="compraFechaModalAyuda">Elige una fecha para filtrar el historial</small>
                    </div>
                </div>
                <button type="button" class="compra-fecha-modal-close" data-dismiss="modal" aria-label="Cerrar"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>

            <div class="modal-body tw-bg-slate-50 tw-p-4 sm:tw-p-5">
                <div class="compra-calendario">
                    <div class="compra-calendario-nav">
                        <button type="button" class="compra-calendario-nav-btn" id="btnCompraFechaAnterior" aria-label="Mes anterior"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                        <div class="compra-calendario-mes" id="compraFechaMesTitulo"></div>
                        <button type="button" class="compra-calendario-nav-btn" id="btnCompraFechaSiguiente" aria-label="Mes siguiente"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                    </div>

                    <div class="compra-calendario-semana" aria-hidden="true">
                        <span>Lu</span><span>Ma</span><span>Mi</span><span>Ju</span><span>Vi</span><span>Sá</span><span>Do</span>
                    </div>

                    <div class="compra-calendario-dias" id="compraFechaDias" role="grid" aria-label="Calendario para filtrar compras"></div>
                </div>

                <div class="compra-fecha-seleccion-resumen">
                    <span class="tw-text-[11px] tw-text-slate-500">Fecha seleccionada</span>
                    <strong id="compraFechaSeleccionResumen" class="tw-mt-0.5 tw-block tw-text-[13px] tw-font-medium tw-text-slate-800">-</strong>
                </div>
            </div>

            <div class="compra-fecha-modal-footer">
                <button type="button" id="btnCompraFechaHoy" class="compra-fecha-hoy-btn"><i class="far fa-calendar-check" aria-hidden="true"></i>Hoy</button>
                <button type="button" class="compra-fecha-cerrar-btn" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- VER COMPRA -->
<div class="modal fade modal-compra" id="getCodeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Detalle de la compra</h5>
                    <small class="text-muted" id="vistaCompraDocumento"></small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <small class="text-muted d-block">Proveedor</small>
                        <strong id="vistaCompraProveedor">-</strong>
                    </div>
                    <div class="col-md-3 mb-2">
                        <small class="text-muted d-block">Fecha</small>
                        <strong id="vistaCompraFecha">-</strong>
                    </div>
                    <div class="col-md-3 mb-2">
                        <small class="text-muted d-block">Tipo</small>
                        <strong id="vistaCompraTipo">-</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Costo</th>
                                <th>Importe</th>
                            </tr>
                        </thead>
                        <tbody id="detallesm"></tbody>
                    </table>
                </div>

                <div class="text-right mt-3">
                    <small class="text-muted d-block">Total</small>
                    <strong id="vistaCompraTotal" style="font-size:1.4rem;">S/ 0.00</strong>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php
} else {
    require 'access.php';
}

require 'footer.php';

$rutaBuyJs = __DIR__ . '/scripts/buy.js';
$versionBuyJs = file_exists($rutaBuyJs) ? filemtime($rutaBuyJs) : time();
?>

<script src="Views/modules/scripts/generaldata.js"></script>
<script src="Views/modules/scripts/buy.js?v=<?= (int)$versionBuyJs ?>"></script>

<?php
ob_end_flush();
?>
