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


    .compra-export-toolbar {
        display: flex;
        min-width: 0;
        margin: 0;
    }

    .compra-export-toolbar .dt-buttons {
        width: 100%;
        display: flex;
        gap: 7px;
        float: none !important;
    }

    .compra-export-toolbar .dt-button,
    .compra-export-toolbar .btn {
        min-height: 34px;
        margin: 0 !important;
        padding: 6px 10px !important;
        display: inline-flex !important;
        flex: 1 1 0;
        align-items: center;
        justify-content: center;
        gap: 4px;
        border: 1px solid #dbe3de !important;
        border-radius: 9px !important;
        color: #536158 !important;
        background: #fff !important;
        box-shadow: none !important;
        font-size: .72rem !important;
        font-weight: 500 !important;
        white-space: nowrap;
    }

    .compra-export-toolbar .dt-button:hover,
    .compra-export-toolbar .btn:hover {
        border-color: #9ed3b1 !important;
        color: #00754d !important;
        background: #f4fbf5 !important;
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

        .compra-nueva-btn {
            width: 100%;
            margin-top: 12px;
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

        .compra-export-toolbar,
        .compra-export-toolbar .dt-buttons {
            width: 100%;
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

                        <button
                            type="button"
                            class="compra-nueva-btn tw-inline-flex tw-min-h-[42px] tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border-0 tw-bg-tique-500 tw-px-4 tw-py-2.5 tw-text-[13px] tw-font-medium tw-text-white tw-shadow-[0_8px_20px_rgba(0,164,106,.18)] tw-transition hover:tw-bg-tique-600 hover:tw-shadow-[0_10px_24px_rgba(0,164,106,.24)] focus:tw-outline-none"
                            onclick="mostrarform(true)"
                            id="btnagregar">
                            <i class="fas fa-plus"></i>
                            Nueva compra
                        </button>
                    </div>

                    <div class="tw-p-4 md:tw-p-6">
                        <div id="listadoregistros">
                            <div class="tw-mb-4 tw-grid tw-gap-3 sm:tw-grid-cols-2 xl:tw-grid-cols-4">
                                <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-4">
                                    <div class="tw-flex tw-items-center tw-gap-3">
                                        <span class="tw-flex tw-h-9 tw-w-9 tw-items-center tw-justify-center tw-rounded-xl tw-bg-white tw-text-tique-700 tw-shadow-sm">
                                            <i class="fas fa-boxes"></i>
                                        </span>
                                        <div>
                                            <div class="tw-text-xs tw-font-medium tw-text-slate-500">Mercadería</div>
                                            <div class="tw-mt-0.5 tw-text-[13px] tw-text-slate-700">Aumenta stock de productos existentes</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-4">
                                    <div class="tw-flex tw-items-center tw-gap-3">
                                        <span class="tw-flex tw-h-9 tw-w-9 tw-items-center tw-justify-center tw-rounded-xl tw-bg-white tw-text-tique-700 tw-shadow-sm">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </span>
                                        <div>
                                            <div class="tw-text-xs tw-font-medium tw-text-slate-500">Gastos y servicios</div>
                                            <div class="tw-mt-0.5 tw-text-[13px] tw-text-slate-700">Registra costos sin afectar inventario</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-4">
                                    <div class="tw-flex tw-items-center tw-gap-3">
                                        <span class="tw-flex tw-h-9 tw-w-9 tw-items-center tw-justify-center tw-rounded-xl tw-bg-white tw-text-tique-700 tw-shadow-sm">
                                            <i class="fas fa-filter"></i>
                                        </span>
                                        <div>
                                            <div class="tw-text-xs tw-font-medium tw-text-slate-500">Historial ordenado</div>
                                            <div class="tw-mt-0.5 tw-text-[13px] tw-text-slate-700">Filtra compras por fecha o proveedor</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-4">
                                    <div class="tw-flex tw-h-full tw-flex-col tw-justify-between tw-gap-3">
                                        <div class="tw-flex tw-items-center tw-gap-3">
                                            <span class="tw-flex tw-h-9 tw-w-9 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-xl tw-bg-white tw-text-tique-700 tw-shadow-sm">
                                                <i class="fas fa-file-export"></i>
                                            </span>
                                            <div class="tw-min-w-0">
                                                <div class="tw-text-xs tw-font-medium tw-text-slate-500">Exportar reporte</div>
                                                <div class="tw-mt-0.5 tw-text-[13px] tw-leading-5 tw-text-slate-700">Excel o PDF con los filtros aplicados</div>
                                            </div>
                                        </div>
                                        <div class="compra-export-toolbar" id="comprasExportToolbar" aria-label="Exportar reporte de compras"></div>
                                    </div>
                                </div>
                            </div>

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
                                        <select name="idproveedor" id="idproveedor" class="form-control" required>
                                            <option value="">Cargando proveedores...</option>
                                        </select>
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

<!-- PRODUCTO NUEVO -->
<div class="modal fade modal-compra" id="modalProductoNuevo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="formProductoNuevo" autocomplete="off">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Registrar producto nuevo</h5>
                        <small class="text-muted">
                            El producto se creará definitivamente cuando guardes la compra.
                        </small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="form-group col-lg-8 col-md-8">
                            <label for="nuevo_nombre">Nombre <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="nuevo_nombre"
                                maxlength="100"
                                required
                                placeholder="Ej.: Polo oversize rosado">
                        </div>

                        <div class="form-group col-lg-4 col-md-4">
                            <label for="nuevo_codigo">SKU o código</label>
                            <input
                                type="text"
                                class="form-control text-uppercase"
                                id="nuevo_codigo"
                                maxlength="50"
                                placeholder="Se genera si queda vacío">
                        </div>
                    </div>

                    <div class="coincidencias-producto" id="coincidenciasProductoNuevo"></div>

                    <div class="row mt-2">
                        <div class="form-group col-lg-3 col-md-6">
                            <label for="nuevo_idcategoria">Categoría <span class="text-danger">*</span></label>
                            <select class="form-control" id="nuevo_idcategoria" required></select>
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                            <label for="nuevo_idsubcategoria">Subcategoría</label>
                            <select class="form-control" id="nuevo_idsubcategoria"></select>
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                            <label for="nuevo_idmedida">Unidad <span class="text-danger">*</span></label>
                            <select class="form-control" id="nuevo_idmedida" required></select>
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                            <label for="nuevo_idalmacen">Almacén <span class="text-danger">*</span></label>
                            <select class="form-control" id="nuevo_idalmacen" required></select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-lg-4 col-md-4">
                            <label for="nuevo_cantidad">Cantidad comprada <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                class="form-control"
                                id="nuevo_cantidad"
                                min="1"
                                step="1"
                                value="1"
                                required>
                            <small class="text-muted">Esta cantidad será el stock que ingresa.</small>
                        </div>

                        <div class="form-group col-lg-4 col-md-4">
                            <label for="nuevo_precio_compra">Costo unitario <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">S/</span>
                                </div>
                                <input
                                    type="number"
                                    class="form-control"
                                    id="nuevo_precio_compra"
                                    min="0.01"
                                    step="0.01"
                                    required>
                            </div>
                        </div>

                        <div class="form-group col-lg-4 col-md-4">
                            <label for="nuevo_precio_venta">Precio de venta</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">S/</span>
                                </div>
                                <input
                                    type="number"
                                    class="form-control"
                                    id="nuevo_precio_venta"
                                    min="0"
                                    step="0.01"
                                    placeholder="Opcional">
                            </div>
                            <small class="text-muted">Podrás definirlo después si aún no lo conoces.</small>
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
