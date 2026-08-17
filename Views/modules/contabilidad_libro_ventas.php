<?php
ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['nombre'])) {
    header('Location: login');
    exit;
}

require 'header.php';
require 'sidebar.php';
?>

<style>
    .conta-sales-page {
        --conta-brand: #00a46a;
        --conta-brand-dark: #008d5b;
        --conta-brand-soft: #ecfdf6;
        --conta-ink: #17211b;
        --conta-muted: #718078;
        --conta-line: #e6ece8;
        --conta-bg: #f6f8f7;
        --conta-card: #ffffff;
        --conta-blue: #5667d8;
        --conta-shadow: 0 16px 44px rgba(15, 23, 42, .07);
        --conta-shadow-soft: 0 8px 24px rgba(15, 23, 42, .055);
    }

    .conta-sales-page .section-body {
        display: grid;
        gap: 16px;
    }

    .conta-card {
        border: 1px solid var(--conta-line);
        border-radius: 20px;
        background: var(--conta-card);
        box-shadow: var(--conta-shadow-soft);
    }

    .conta-filter-card {
        position: relative;
        z-index: 20;
        padding: 20px;
    }

    .conta-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 17px;
    }

    .conta-heading-copy h4,
    .conta-table-title h4 {
        margin: 0;
        color: var(--conta-ink);
        font-size: 1.03rem;
        font-weight: 700;
        letter-spacing: -.015em;
    }

    .conta-heading-copy p,
    .conta-table-title p {
        margin: 5px 0 0;
        color: var(--conta-muted);
        font-size: .76rem;
        line-height: 1.55;
    }

    .conta-live-badge {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        flex: 0 0 auto;
        padding: 7px 10px;
        border: 1px solid #cceedd;
        border-radius: 999px;
        background: #f1fcf7;
        color: #08784f;
        font-size: .69rem;
        font-weight: 650;
    }

    .conta-live-badge::before {
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #22c55e;
        box-shadow: 0 0 0 4px rgba(34, 197, 94, .1);
    }

    .conta-filters {
        display: grid;
        grid-template-columns: minmax(260px, 1.05fr) minmax(180px, .9fr) minmax(180px, .9fr) minmax(190px, .95fr) auto;
        gap: 12px;
        align-items: end;
    }

    .conta-field {
        min-width: 0;
    }

    .conta-field label {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0 0 7px;
        color: #44534a;
        font-size: .7rem;
        font-weight: 650;
    }

    .conta-field label i {
        color: var(--conta-brand);
        font-size: .74rem;
    }

    .conta-control,
    .conta-date-trigger {
        width: 100%;
        height: 43px;
        border: 1px solid #dce4df;
        border-radius: 12px;
        background: #fff;
        color: #35423a;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .025);
        transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
    }

    .conta-control {
        padding: 0 35px 0 12px;
        font-size: .75rem;
        outline: 0;
    }

    .conta-control:focus,
    .conta-date-trigger:focus-visible {
        border-color: #79d7b5;
        box-shadow: 0 0 0 4px rgba(0, 164, 106, .08);
    }

    .conta-date-wrap {
        position: relative;
    }

    .conta-date-trigger {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 12px;
        text-align: left;
        cursor: pointer;
    }

    .conta-date-icon {
        width: 27px;
        height: 27px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        border-radius: 8px;
        background: var(--conta-brand-soft);
        color: var(--conta-brand);
        font-size: .76rem;
    }

    .conta-date-copy {
        min-width: 0;
        display: grid;
        gap: 1px;
    }

    .conta-date-copy strong {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #34413a;
        font-size: .75rem;
        font-weight: 600;
    }

    .conta-date-copy small {
        color: #9aa49e;
        font-size: .61rem;
    }

    .conta-date-trigger .fa-chevron-down {
        margin-left: auto;
        color: #9ba7a0;
        font-size: .65rem;
        transition: transform .18s ease;
    }

    .conta-date-trigger.is-open .fa-chevron-down {
        transform: rotate(180deg);
    }

    .conta-date-popover {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        width: min(410px, calc(100vw - 60px));
        padding: 13px;
        border: 1px solid #dfe6e2;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 22px 55px rgba(15, 23, 42, .16);
        z-index: 80;
    }

    .conta-date-popover[hidden] {
        display: none !important;
    }

    .conta-date-inputs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 9px;
    }

    .conta-date-inputs label {
        display: grid;
        gap: 5px;
        margin: 0;
        color: #67736c;
        font-size: .65rem;
        font-weight: 600;
    }

    .conta-date-inputs input {
        width: 100%;
        height: 40px;
        padding: 0 9px;
        border: 1px solid #dfe6e2;
        border-radius: 10px;
        outline: 0;
        color: #334139;
        background: #fbfcfb;
        font-size: .74rem;
    }

    .conta-date-inputs input:focus {
        border-color: #8bdabb;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(0, 164, 106, .07);
    }

    .conta-date-presets {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }

    .conta-date-presets button {
        border: 1px solid #e1e7e3;
        border-radius: 999px;
        padding: 6px 9px;
        background: #f8faf9;
        color: #657168;
        font-size: .64rem;
        cursor: pointer;
    }

    .conta-date-presets button:hover {
        border-color: #bce8d6;
        background: var(--conta-brand-soft);
        color: #08784f;
    }

    .conta-date-actions {
        display: flex;
        justify-content: flex-end;
        gap: 7px;
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid #edf1ee;
    }

    .conta-mini-btn {
        height: 34px;
        padding: 0 11px;
        border: 1px solid #dfe6e2;
        border-radius: 9px;
        background: #fff;
        color: #66736b;
        font-size: .68rem;
        font-weight: 600;
        cursor: pointer;
    }

    .conta-mini-btn.primary {
        border-color: var(--conta-brand);
        background: var(--conta-brand);
        color: #fff;
    }

    .conta-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        justify-content: flex-end;
    }

    .conta-btn {
        height: 43px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 14px;
        border: 0;
        border-radius: 12px;
        font-size: .72rem;
        font-weight: 700;
        white-space: nowrap;
        transition: transform .16s ease, box-shadow .16s ease, background .16s ease;
        cursor: pointer;
    }

    .conta-btn:hover {
        transform: translateY(-1px);
    }

    .conta-btn:focus-visible,
    .conta-menu-btn:focus-visible,
    .conta-icon-btn:focus-visible {
        outline: 3px solid rgba(0, 164, 106, .13);
        outline-offset: 2px;
    }

    .conta-btn.report {
        background: var(--conta-blue);
        color: #fff;
        box-shadow: 0 8px 18px rgba(86, 103, 216, .2);
    }

    .conta-btn.report:hover {
        background: #4b5bc7;
    }

    .conta-options-wrap {
        position: relative;
    }

    .conta-menu-btn {
        height: 43px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 0 13px;
        border: 0;
        border-radius: 12px;
        background: var(--conta-brand);
        color: #fff;
        font-size: .69rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 8px 18px rgba(0, 164, 106, .17);
    }

    .conta-options-menu {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: 235px;
        padding: 7px;
        border: 1px solid #e0e7e3;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .16);
        z-index: 70;
    }

    .conta-options-menu[hidden] {
        display: none !important;
    }

    .conta-options-menu button {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border: 0;
        border-radius: 10px;
        background: transparent;
        color: #47544c;
        text-align: left;
        font-size: .69rem;
        cursor: pointer;
    }

    .conta-options-menu button:hover {
        background: #f6faf8;
        color: #08784f;
    }

    .conta-options-menu .menu-icon {
        width: 29px;
        height: 29px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        border-radius: 8px;
        background: #f1f5f3;
        color: #68756d;
    }

    .conta-options-menu .menu-icon.excel {
        background: #ecfdf3;
        color: #168455;
    }

    .conta-options-menu .menu-icon.ejb {
        width: 36px;
        border-radius: 7px;
        background: #eef6ff;
        color: #0878c9;
        font-size: .58rem;
        font-weight: 900;
        letter-spacing: -.02em;
    }

    .conta-options-menu .menu-icon.siscont {
        background: #fff7e5;
        color: #d18a06;
    }

    .conta-options-menu .menu-copy {
        display: grid;
        gap: 2px;
    }

    .conta-options-menu .menu-copy strong {
        font-size: .69rem;
        font-weight: 650;
    }

    .conta-options-menu .menu-copy small {
        color: #96a19a;
        font-size: .59rem;
        font-weight: 400;
    }

    .conta-note {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-top: 15px;
        padding: 10px 12px;
        border: 1px solid #dfeee7;
        border-radius: 12px;
        background: #f7fcf9;
        color: #647169;
        font-size: .67rem;
        line-height: 1.5;
    }

    .conta-note i {
        margin-top: 2px;
        color: var(--conta-brand);
    }

    .conta-summary-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
    }

    .conta-stat {
        position: relative;
        min-width: 0;
        overflow: hidden;
        padding: 13px 14px;
        border: 1px solid var(--conta-line);
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 5px 18px rgba(15, 23, 42, .035);
    }

    .conta-stat::after {
        content: "";
        position: absolute;
        width: 54px;
        height: 54px;
        right: -18px;
        bottom: -24px;
        border-radius: 50%;
        background: rgba(0, 164, 106, .055);
    }

    .conta-stat span {
        display: block;
        color: #849088;
        font-size: .63rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .045em;
    }

    .conta-stat strong {
        display: block;
        margin-top: 6px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #1e2b23;
        font-size: 1rem;
        font-weight: 750;
        letter-spacing: -.02em;
    }

    .conta-stat small {
        display: block;
        margin-top: 2px;
        color: #9ca69f;
        font-size: .59rem;
    }

    .conta-stat.primary {
        border-color: #cdeede;
        background: linear-gradient(135deg, #fff 0%, #f3fcf8 100%);
    }

    .conta-stat.primary strong {
        color: #007c51;
    }

    .conta-table-card {
        position: relative;
        overflow: hidden;
    }

    .conta-table-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 17px 18px;
        border-bottom: 1px solid var(--conta-line);
    }

    .conta-table-tools {
        display: flex;
        align-items: center;
        gap: 7px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .conta-search {
        position: relative;
        width: 225px;
    }

    .conta-search i {
        position: absolute;
        top: 50%;
        left: 11px;
        transform: translateY(-50%);
        color: #9aa59e;
        font-size: .7rem;
        pointer-events: none;
    }

    .conta-search input {
        width: 100%;
        height: 37px;
        padding: 0 12px 0 31px;
        border: 1px solid #dfe6e2;
        border-radius: 10px;
        outline: 0;
        color: #425048;
        font-size: .68rem;
    }

    .conta-search input:focus {
        border-color: #88d8b9;
        box-shadow: 0 0 0 3px rgba(0, 164, 106, .07);
    }

    .conta-length {
        height: 37px;
        padding: 0 29px 0 10px;
        border: 1px solid #dfe6e2;
        border-radius: 10px;
        outline: 0;
        background: #fff;
        color: #526058;
        font-size: .67rem;
    }

    .conta-icon-btn {
        height: 37px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 0 11px;
        border: 0;
        border-radius: 10px;
        background: #eaf8f2;
        color: #08784f;
        font-size: .66rem;
        font-weight: 700;
        cursor: pointer;
    }

    .conta-icon-btn.excel {
        background: #22a15d;
        color: #fff;
    }

    .conta-icon-btn.refresh {
        background: #edf1ff;
        color: #5363cc;
    }

    .conta-table-shell {
        padding: 0 18px 15px;
    }

    .conta-table-scroll {
        overflow-x: auto;
        padding-top: 13px;
        scrollbar-color: #9ca3af #eef1ef;
        scrollbar-width: thin;
    }

    #tablaLibroVentas {
        width: 100% !important;
        min-width: 3300px;
        margin: 0 !important;
        border-collapse: separate !important;
        border-spacing: 0;
    }

    #tablaLibroVentas thead th {
        vertical-align: middle;
        border-color: #e2e8e4 !important;
        color: #4f5b54;
        background: #fafcfb;
        font-size: .59rem;
        font-weight: 700;
        line-height: 1.25;
        white-space: nowrap;
        text-transform: uppercase;
    }

    #tablaLibroVentas thead tr.conta-group-row th {
        height: 34px;
        background: #f5f8f6;
        color: #657169;
        font-size: .57rem;
        letter-spacing: .035em;
        text-align: center;
    }

    #tablaLibroVentas thead tr.conta-column-row th {
        height: 42px;
    }

    #tablaLibroVentas tbody td {
        vertical-align: middle;
        border-color: #edf1ee;
        color: #49564e;
        background: #fff;
        font-size: .64rem;
        line-height: 1.35;
        white-space: nowrap;
    }

    #tablaLibroVentas tbody tr:hover td {
        background: #f9fcfa;
    }

    #tablaLibroVentas tbody tr.conta-credit-note td {
        background: #fffdf7;
    }

    #tablaLibroVentas tbody tr.conta-credit-note:hover td {
        background: #fff9ea;
    }

    #tablaLibroVentas .conta-money-negative {
        color: #c2413b;
        font-weight: 650;
    }

    .conta-state-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 7px;
        border-radius: 999px;
        font-size: .57rem;
        font-weight: 750;
        letter-spacing: .02em;
    }

    .conta-state-pill::before {
        content: "";
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: currentColor;
    }

    .conta-state-pill.accepted {
        background: #eaf9f2;
        color: #08784f;
    }

    .conta-state-pill.pending {
        background: #fff7df;
        color: #a16207;
    }

    .conta-state-pill.rejected {
        background: #fff0ef;
        color: #c43f37;
    }

    .conta-state-pill.cancelled {
        background: #f0f2f4;
        color: #66717a;
    }

    .conta-state-pill.neutral {
        background: #eef2ff;
        color: #5565c9;
    }

    /* Paginación estándar del proyecto (DataTables + Bootstrap).
       Se reutiliza la misma estructura visual de los demás listados. */
    .conta-table-card .dataTables_wrapper .dataTables_info {
        padding-top: 14px;
        color: #64748b;
        font-size: .76rem;
    }

    .conta-table-card .dataTables_wrapper .dataTables_paginate {
        padding-top: 10px;
    }

    .conta-table-card .dataTables_wrapper .pagination {
        justify-content: flex-end;
        margin-bottom: 0;
    }

    .conta-table-card .dataTables_wrapper .pagination .page-link {
        min-width: 34px;
        min-height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-color: #e2e8f0;
        color: #475569;
        background: #fff;
        box-shadow: none !important;
        font-size: .76rem;
    }

    .conta-table-card .dataTables_wrapper .pagination .page-item.active .page-link {
        border-color: #00a46a;
        background: #00a46a;
        color: #fff;
    }

    .conta-table-card .dataTables_wrapper .pagination .page-item.disabled .page-link {
        color: #98a2b3;
        background: #fff;
        border-color: #eef2f6;
    }

    .conta-table-card .dataTables_wrapper .pagination .page-link:hover {
        border-color: #d5dde6;
        background: #f8fafc;
        color: #344054;
    }

    .conta-table-card .dataTables_wrapper .pagination .page-item.active .page-link:hover {
        border-color: #00a46a;
        background: #00a46a;
        color: #fff;
    }

    .conta-loading {
        position: absolute;
        inset: 0;
        z-index: 90;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, .73);
        backdrop-filter: blur(2px);
    }

    .conta-loading.is-visible {
        display: flex;
    }

    .conta-loading-box {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 11px 14px;
        border: 1px solid #e0e7e3;
        border-radius: 13px;
        background: #fff;
        color: #536058;
        box-shadow: 0 14px 38px rgba(15, 23, 42, .11);
        font-size: .7rem;
        font-weight: 650;
    }

    .conta-spinner {
        width: 19px;
        height: 19px;
        border: 2px solid #d5eee3;
        border-top-color: var(--conta-brand);
        border-radius: 50%;
        animation: contaSpin .75s linear infinite;
    }

    @keyframes contaSpin {
        to { transform: rotate(360deg); }
    }

    .conta-empty-hint {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: #859088;
        font-size: .64rem;
    }

    @media (max-width: 1280px) {
        .conta-filters {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .conta-actions {
            grid-column: 1 / -1;
            justify-content: flex-end;
        }
        .conta-summary-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .conta-filter-card,
        .conta-table-shell {
            padding-left: 12px;
            padding-right: 12px;
        }
        .conta-heading,
        .conta-table-head {
            align-items: stretch;
            flex-direction: column;
        }
        .conta-filters {
            grid-template-columns: 1fr;
        }
        .conta-actions {
            grid-column: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        .conta-btn,
        .conta-menu-btn {
            width: 100%;
        }
        .conta-summary-grid {
            grid-template-columns: 1fr 1fr;
        }
        .conta-table-tools {
            justify-content: stretch;
        }
        .conta-table-card .dataTables_wrapper .dataTables_info {
            text-align: left;
        }
        .conta-table-card .dataTables_wrapper .dataTables_paginate .pagination {
            justify-content: flex-start;
            margin-top: 6px;
        }
        .conta-search {
            width: 100%;
            flex: 1 1 100%;
        }
        .conta-length {
            flex: 1;
        }
        .conta-icon-btn {
            flex: 1;
        }
        .conta-date-popover {
            width: min(360px, calc(100vw - 48px));
        }
    }

    @media (max-width: 430px) {
        .conta-summary-grid {
            grid-template-columns: 1fr;
        }
        .conta-date-inputs {
            grid-template-columns: 1fr;
        }
        .conta-live-badge {
            align-self: flex-start;
        }
    }
</style>

<div class="main-content conta-sales-page">
    <section class="section">
        <div class="section-body">
            <div class="conta-card conta-filter-card">
                <div class="conta-heading">
                    <div class="conta-heading-copy">
                        <h4>Libro Electrónico de Ventas</h4>
                        <p>Consulta los comprobantes emitidos y genera el formato contable con la estructura de 37 campos.</p>
                    </div>
                    <span class="conta-live-badge">Datos del sistema</span>
                </div>

                <div class="conta-filters">
                    <div class="conta-field conta-date-wrap">
                        <label><i class="far fa-calendar-alt"></i> Fecha de emisión</label>
                        <button type="button" class="conta-date-trigger" id="contaDateTrigger" aria-expanded="false">
                            <span class="conta-date-icon"><i class="far fa-calendar"></i></span>
                            <span class="conta-date-copy">
                                <strong id="contaDateLabel">Seleccionando rango...</strong>
                                <small>Rango de comprobantes</small>
                            </span>
                            <i class="fas fa-chevron-down"></i>
                        </button>

                        <div class="conta-date-popover" id="contaDatePopover" hidden>
                            <div class="conta-date-inputs">
                                <label>
                                    Desde
                                    <input type="date" id="contaFechaInicio">
                                </label>
                                <label>
                                    Hasta
                                    <input type="date" id="contaFechaFin">
                                </label>
                            </div>
                            <div class="conta-date-presets">
                                <button type="button" data-conta-range="month">Este mes</button>
                                <button type="button" data-conta-range="prev-month">Mes anterior</button>
                                <button type="button" data-conta-range="30days">Últimos 30 días</button>
                                <button type="button" data-conta-range="year">Este año</button>
                            </div>
                            <div class="conta-date-actions">
                                <button type="button" class="conta-mini-btn" id="contaDateCancel">Cancelar</button>
                                <button type="button" class="conta-mini-btn primary" id="contaDateApply">Aplicar rango</button>
                            </div>
                        </div>
                    </div>

                    <div class="conta-field">
                        <label for="contaTipoDocumento"><i class="far fa-file-alt"></i> Tipo de comprobante</label>
                        <select id="contaTipoDocumento" class="conta-control">
                            <option value="TODOS">Todos</option>
                            <option value="01">Factura Electrónica</option>
                            <option value="03">Boleta Electrónica</option>
                            <option value="07">Nota de Crédito</option>
                        </select>
                    </div>

                    <div class="conta-field">
                        <label for="contaSucursal"><i class="fas fa-store-alt"></i> Sucursal</label>
                        <select id="contaSucursal" class="conta-control">
                            <option value="0">Todas</option>
                        </select>
                    </div>

                    <div class="conta-field">
                        <label for="contaRegimen"><i class="fas fa-landmark"></i> Régimen</label>
                        <select id="contaRegimen" class="conta-control">
                            <option value="M-RER">Régimen Especial</option>
                            <option value="M-RMT">Régimen MYPE Tributario</option>
                            <option value="M-RG">Régimen General</option>
                        </select>
                    </div>

                    <div class="conta-actions">
                        <button type="button" class="conta-btn report" id="btnContaGenerar">
                            <i class="fas fa-search"></i>
                            Generar reporte
                        </button>
                        <div class="conta-options-wrap">
                            <button type="button" class="conta-menu-btn" id="btnContaOpciones" aria-expanded="false">
                                Opciones
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="conta-options-menu" id="contaOpcionesMenu" hidden>
                                <button type="button" data-export="txt">
                                    <span class="menu-icon"><i class="fas fa-align-left"></i></span>
                                    <span class="menu-copy">
                                        <strong>Generar TXT SUNAT</strong>
                                        <small>Archivo mensual separado por pipes</small>
                                    </span>
                                </button>
                                <button type="button" data-export="sunat-xlsx">
                                    <span class="menu-icon excel"><i class="far fa-file-excel"></i></span>
                                    <span class="menu-copy">
                                        <strong>Formato SUNAT - Excel</strong>
                                        <small>37 columnas exactas del libro de ventas</small>
                                    </span>
                                </button>
                                <button type="button" data-export="ejb">
                                    <span class="menu-icon ejb">EJB</span>
                                    <span class="menu-copy">
                                        <strong>Formato EJB</strong>
                                        <small>22 columnas para EJB Contable</small>
                                    </span>
                                </button>
                                <button type="button" data-export="siscont">
                                    <span class="menu-icon siscont"><i class="fas fa-chart-pie"></i></span>
                                    <span class="menu-copy">
                                        <strong>Asiento Contable</strong>
                                        <small>42 columnas · estructura SISCONT</small>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="conta-note">
                    <i class="fas fa-info-circle"></i>
                    <span>Este libro consolida Facturas, Boletas y Notas de Crédito registradas en TiquePOS. Las Notas de Crédito se muestran con importes negativos y referencia al comprobante modificado.</span>
                </div>
            </div>

            <div class="conta-summary-grid" aria-label="Resumen del libro de ventas">
                <div class="conta-stat">
                    <span>Comprobantes</span>
                    <strong id="contaStatCount">0</strong>
                    <small id="contaStatObserved">Sin observaciones</small>
                </div>
                <div class="conta-stat">
                    <span>Operación gravada</span>
                    <strong id="contaStatGravada">S/. 0.00</strong>
                    <small>Base imponible registrada</small>
                </div>
                <div class="conta-stat">
                    <span>Exonerada / Inafecta</span>
                    <strong id="contaStatNoGravada">S/. 0.00</strong>
                    <small>Suma de operaciones no gravadas</small>
                </div>
                <div class="conta-stat">
                    <span>IGV</span>
                    <strong id="contaStatIgv">S/. 0.00</strong>
                    <small>Impuesto registrado</small>
                </div>
                <div class="conta-stat primary">
                    <span>Total registrado</span>
                    <strong id="contaStatTotal">S/. 0.00</strong>
                    <small>Importe neto del rango</small>
                </div>
            </div>

            <div class="conta-card conta-table-card">
                <div class="conta-table-head">
                    <div class="conta-table-title">
                        <h4>Formato de Libro Electrónico de Ventas</h4>
                        <p id="contaTableMeta">Genera un reporte para visualizar los comprobantes.</p>
                    </div>
                    <div class="conta-table-tools">
                        <div class="conta-search">
                            <i class="fas fa-search"></i>
                            <input type="search" id="contaSearch" placeholder="Buscar en el libro..." autocomplete="off">
                        </div>
                        <select id="contaLength" class="conta-length" aria-label="Registros por página">
                            <option value="10">10 filas</option>
                            <option value="25">25 filas</option>
                            <option value="50">50 filas</option>
                            <option value="100">100 filas</option>
                        </select>
                        <button type="button" class="conta-icon-btn excel" id="btnContaExcel" title="Exportar Formato SUNAT - Excel">
                            <i class="far fa-file-excel"></i>
                            Excel SUNAT
                        </button>
                        <button type="button" class="conta-icon-btn refresh" id="btnContaRefresh" title="Actualizar reporte">
                            <i class="fas fa-sync-alt"></i>
                            Actualizar
                        </button>
                    </div>
                </div>

                <div class="conta-table-shell">
                    <div class="conta-table-scroll">
                        <table id="tablaLibroVentas" class="table table-hover table-bordered">
                            <thead>
                                <tr class="conta-group-row">
                                    <th rowspan="2">Periodo</th>
                                    <th rowspan="2">Cod.Unic.</th>
                                    <th rowspan="2">Régimen</th>
                                    <th colspan="6">Datos del comprobante</th>
                                    <th colspan="3">Información cliente</th>
                                    <th colspan="13">Importes de la operación</th>
                                    <th rowspan="2">Moneda</th>
                                    <th rowspan="2">T.C.</th>
                                    <th colspan="4">Documento modificado</th>
                                    <th colspan="5">Otros</th>
                                    <th rowspan="2">Estado Comp.</th>
                                </tr>
                                <tr class="conta-column-row">
                                    <th>F.Emisión</th>
                                    <th>F.Vencimiento</th>
                                    <th>Tipo Doc</th>
                                    <th>Serie</th>
                                    <th>Número</th>
                                    <th>Num.Maq.Reg.</th>
                                    <th>T.Doc.</th>
                                    <th>Número</th>
                                    <th>Razón Social</th>
                                    <th>Op.Export.</th>
                                    <th>Op.Gravada</th>
                                    <th>Descuent.</th>
                                    <th>IGV</th>
                                    <th>Desc.IGV</th>
                                    <th>Op.Exonerada</th>
                                    <th>Op.Inafecta</th>
                                    <th>ISC</th>
                                    <th>Op.Arroz.P.</th>
                                    <th>Imp.Arroz.P.</th>
                                    <th>ICBPER</th>
                                    <th>Otro.Tributos.</th>
                                    <th>Total</th>
                                    <th>Fec.Comp.Modif.</th>
                                    <th>Tipo.Doc.Modif.</th>
                                    <th>Serie.Doc.Modif.</th>
                                    <th>Num.Doc.Modif.</th>
                                    <th>Id.Contr.</th>
                                    <th>Err.T.C.</th>
                                    <th>Comp.M.P</th>
                                    <th>Estado</th>
                                    <th>Camp.Lib.</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <div class="conta-loading" id="contaLoading" aria-hidden="true">
                    <div class="conta-loading-box">
                        <span class="conta-spinner"></span>
                        Procesando libro de ventas...
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
require 'footer.php';
$rutaJs = __DIR__ . '/scripts/contabilidad_libro_ventas.js';
$versionJs = is_file($rutaJs) ? filemtime($rutaJs) : time();
?>
<script src="Views/modules/scripts/contabilidad_libro_ventas.js?v=<?= (int)$versionJs ?>"></script>
<?php ob_end_flush(); ?>
