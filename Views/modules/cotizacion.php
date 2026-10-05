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

if ((int)($_SESSION['ventas'] ?? 0) === 1) {
?>
    <!-- Tailwind aislado para Cotizaciones. No altera Bootstrap/Stisla. -->
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
                        'tique-card': '0 14px 42px rgba(15, 23, 42, .07)',
                        'tique-float': '0 24px 70px rgba(15, 23, 42, .18)'
                    }
                }
            }
        };
    </script>

    <style>
        .cotizacion-page {
            --cq-green: #00a46a;
            --cq-green-dark: #008d5b;
            --cq-border: #e6ece9;
            --cq-muted: #64748b;
            --cq-ink: #0f172a;
        }

        .cotizacion-page .dataTables_wrapper {
            color: #475569;
            font-size: 12px;
        }

        .cotizacion-page .cq-dt-top,
        .cotizacion-page .cq-dt-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .cotizacion-page .cq-dt-top {
            padding: 14px 18px 12px;
            border-bottom: 1px solid #eef2f1;
        }

        .cotizacion-page .cq-dt-bottom {
            padding: 12px 18px 16px;
            border-top: 1px solid #eef2f1;
        }

        .cotizacion-page .cq-dt-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-wrap: wrap;
        }

        .cotizacion-page .dataTables_filter,
        .cotizacion-page .dataTables_length,
        .cotizacion-page .dt-buttons {
            margin: 0 !important;
        }

        .cotizacion-page .dataTables_filter label,
        .cotizacion-page .dataTables_length label {
            margin: 0;
            color: #64748b;
            font-size: 11px;
            font-weight: 600;
        }

        .cotizacion-page .dataTables_filter input {
            width: min(290px, 58vw) !important;
            height: 38px;
            margin-left: 8px !important;
            padding: 0 13px !important;
            border: 1px solid #dbe4e0 !important;
            border-radius: 11px !important;
            background: #fff !important;
            color: #0f172a !important;
            outline: none !important;
            box-shadow: none !important;
        }

        .cotizacion-page .dataTables_filter input:focus {
            border-color: #75d5b0 !important;
            box-shadow: 0 0 0 4px rgba(0, 164, 106, .08) !important;
        }

        .cotizacion-page .dataTables_length select {
            height: 36px;
            margin: 0 5px;
            border: 1px solid #dbe4e0;
            border-radius: 10px;
            background: #fff;
            color: #334155;
        }

        .cotizacion-page .dt-buttons .btn {
            height: 36px;
            padding: 0 12px;
            border: 1px solid #dbe4e0 !important;
            border-radius: 10px !important;
            background: #fff !important;
            color: #475569 !important;
            box-shadow: none !important;
            font-size: 11px;
            font-weight: 700;
        }

        .cotizacion-page .dt-buttons .btn:hover {
            border-color: #bcdacc !important;
            background: #f6fbf9 !important;
            color: #087a52 !important;
        }

        .cotizacion-page .table-responsive {
            overflow-x: auto;
        }

        .cotizacion-page table.dataTable {
            width: 100% !important;
            margin: 0 !important;
            border-collapse: collapse !important;
        }

        .cotizacion-page table.dataTable thead th {
            padding: 12px 14px !important;
            border-top: 0 !important;
            border-bottom: 1px solid #e8eeeb !important;
            background: #f8faf9;
            color: #64748b;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .055em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .cotizacion-page table.dataTable tbody td {
            padding: 13px 14px !important;
            border-top: 0 !important;
            border-bottom: 1px solid #f0f3f2 !important;
            color: #334155;
            vertical-align: middle;
            white-space: nowrap;
        }

        .cotizacion-page table.dataTable tbody tr:hover td {
            background: #fbfdfc;
        }

        .cotizacion-page .dataTables_info {
            padding: 0 !important;
            color: #94a3b8 !important;
            font-size: 11px;
        }

        .cotizacion-page .dataTables_paginate {
            padding: 0 !important;
        }

        .cotizacion-page .dataTables_paginate .paginate_button {
            min-width: 34px !important;
            height: 34px !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            padding: 0 9px !important;
            margin-left: 4px !important;
            border: 1px solid #e2e8e5 !important;
            border-radius: 9px !important;
            background: #fff !important;
            color: #64748b !important;
        }

        .cotizacion-page .dataTables_paginate .paginate_button.current,
        .cotizacion-page .dataTables_paginate .paginate_button.current:hover {
            border-color: var(--cq-green) !important;
            background: var(--cq-green) !important;
            color: #fff !important;
        }

        .cotizacion-page .dataTables_paginate .paginate_button:hover {
            border-color: #cbd8d2 !important;
            background: #f8faf9 !important;
            color: #0f172a !important;
        }

        .cq-number-cell {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 155px;
        }

        .cq-number-icon {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 10px;
            background: #ecfdf6;
            color: #008d5b;
        }

        .cq-number-icon svg {
            width: 17px;
            height: 17px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .cq-number-copy {
            display: grid;
            gap: 1px;
        }

        .cq-number-copy strong {
            color: #0f172a;
            font-size: 12px;
            font-weight: 800;
        }

        .cq-number-copy small {
            color: #94a3b8;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .cq-money {
            color: #0f172a;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .cq-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 26px;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
        }

        .cq-status::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: currentColor;
        }

        .cq-status--pending { background: #fff7e6; color: #b7791f; }
        .cq-status--done { background: #ecfdf6; color: #087a52; }
        .cq-status--cancelled { background: #fff1f2; color: #be123c; }
        .cq-status--other { background: #f1f5f9; color: #64748b; }

        .cq-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 6px;
        }

        .cq-action-btn {
            width: 34px;
            height: 34px;
            display: inline-grid;
            place-items: center;
            border: 1px solid #dfe7e3;
            border-radius: 10px;
            background: #fff;
            color: #64748b;
            cursor: pointer;
            transition: .15s ease;
        }

        .cq-action-btn:hover {
            transform: translateY(-1px);
            border-color: #b9d9ca;
            background: #f4fbf8;
            color: #087a52;
        }

        .cq-action-btn--execute {
            border-color: #bfe6d5;
            background: #ecfdf6;
            color: #008d5b;
        }

        .cq-action-btn svg {
            width: 15px;
            height: 15px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* Botones: foco limpio y consistente con la identidad TiquePOS. */
        .cotizacion-page button:focus,
        .cotizacion-page button:focus-visible,
        .cotizacion-page a:focus,
        .cotizacion-page a:focus-visible,
        #cotizacionPreviewModal button:focus,
        #cotizacionPreviewModal button:focus-visible,
        #cotizacionPreviewModal a:focus,
        #cotizacionPreviewModal a:focus-visible {
            outline: none !important;
        }

        .cq-filter-button:focus,
        .cq-filter-button:focus-visible,
        .cq-action-btn:focus,
        .cq-action-btn:focus-visible {
            border-color: #72d9b3 !important;
            box-shadow: 0 0 0 3px rgba(0, 164, 106, .10) !important;
        }

        .cq-filter-button.is-active,
        .cq-filter-button.is-active:focus,
        .cq-filter-button.is-active:focus-visible {
            border-color: #00a46a !important;
            background: #00a46a !important;
            color: #fff !important;
            box-shadow: 0 6px 18px rgba(0, 164, 106, .18) !important;
        }

        /* Refuerzo CSS para evitar que Bootstrap/Stisla apague el CTA principal. */
        .cq-new-quote-btn {
            border: 1px solid #00a46a !important;
            background: #00a46a !important;
            color: #fff !important;
            text-decoration: none !important;
            box-shadow: 0 10px 24px rgba(0, 164, 106, .20) !important;
        }

        .cq-new-quote-btn:hover,
        .cq-new-quote-btn:active,
        .cq-new-quote-btn:focus,
        .cq-new-quote-btn:focus-visible {
            border-color: #008d5b !important;
            background: #008d5b !important;
            color: #fff !important;
            text-decoration: none !important;
        }

        .cq-new-quote-btn:focus,
        .cq-new-quote-btn:focus-visible {
            box-shadow: 0 10px 24px rgba(0, 164, 106, .20), 0 0 0 3px rgba(0, 164, 106, .12) !important;
        }

        .cq-live-dot.is-loading {
            background: #f59e0b !important;
            box-shadow: 0 0 0 5px rgba(245, 158, 11, .12) !important;
        }

        body.cq-modal-open {
            overflow: hidden;
        }

        @media (max-width: 767px) {
            .cotizacion-page .cq-dt-top,
            .cotizacion-page .cq-dt-bottom {
                align-items: stretch;
            }

            .cotizacion-page .cq-dt-actions {
                width: 100%;
                margin-left: 0;
                justify-content: space-between;
            }

            .cotizacion-page .dataTables_filter,
            .cotizacion-page .dataTables_filter label,
            .cotizacion-page .dataTables_filter input {
                width: 100% !important;
                margin-left: 0 !important;
            }

            .cotizacion-page .dataTables_filter label {
                display: grid;
                gap: 6px;
            }
        }
    </style>

    <div class="main-content">
        <section class="section">
            <div class="section-body cotizacion-page tw-space-y-5">

                <div class="tw-overflow-hidden tw-rounded-3xl tw-border tw-border-slate-200/80 tw-bg-white tw-shadow-tique-card">
                    <div class="tw-relative tw-flex tw-flex-col tw-gap-5 tw-p-5 sm:tw-p-6 lg:tw-flex-row lg:tw-items-center lg:tw-justify-between">
                        <div class="tw-absolute tw-right-0 tw-top-0 tw-h-32 tw-w-32 tw-translate-x-10 tw--translate-y-10 tw-rounded-full tw-bg-tique-50"></div>

                        <div class="tw-relative tw-flex tw-items-start tw-gap-4">
                            <div class="tw-grid tw-h-12 tw-w-12 tw-flex-none tw-place-items-center tw-rounded-2xl tw-bg-tique-50 tw-text-tique-600">
                                <svg class="tw-h-6 tw-w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M6 3h9l3 3v15H6z"></path>
                                    <path d="M14 3v4h4M9 11h6M9 15h6"></path>
                                </svg>
                            </div>
                            <div>
                                <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                    <h1 class="tw-m-0 tw-text-xl tw-font-extrabold tw-tracking-tight tw-text-slate-900 sm:tw-text-2xl">Cotizaciones</h1>
                                    <span class="tw-inline-flex tw-items-center tw-gap-2 tw-rounded-full tw-bg-emerald-50 tw-px-3 tw-py-1 tw-text-[10px] tw-font-extrabold tw-uppercase tw-tracking-wide tw-text-emerald-700" title="Se actualiza automáticamente">
                                        <span class="cq-live-dot tw-h-2 tw-w-2 tw-rounded-full tw-bg-emerald-500 tw-shadow-[0_0_0_5px_rgba(16,185,129,.12)]" id="cotizacionLiveDot"></span>
                                        En vivo
                                    </span>
                                </div>
                                <p class="tw-mb-0 tw-mt-1 tw-max-w-2xl tw-text-xs tw-leading-5 tw-text-slate-500 sm:tw-text-sm">
                                    Crea propuestas, revisa su estado y conviértelas en ventas desde el POS cuando el cliente confirme.
                                </p>
                            </div>
                        </div>

                        <a
                            href="pos?comprobante=cotizacion"
                            id="btnagregar"
                            class="cq-new-quote-btn tw-relative tw-inline-flex tw-h-11 tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-bg-tique-500 tw-px-5 tw-text-xs tw-font-extrabold tw-text-white tw-transition hover:tw--translate-y-0.5">
                            <svg class="tw-h-4 tw-w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <path d="M12 5v14M5 12h14"></path>
                            </svg>
                            Nueva cotización
                        </a>
                    </div>
                </div>

                <div class="tw-grid tw-grid-cols-2 tw-gap-3 lg:tw-grid-cols-4">
                    <div class="tw-rounded-2xl tw-border tw-border-amber-100 tw-bg-gradient-to-br tw-from-amber-50 tw-to-white tw-p-4 tw-shadow-sm">
                        <div class="tw-flex tw-items-center tw-justify-between tw-gap-3">
                            <span class="tw-text-[10px] tw-font-extrabold tw-uppercase tw-tracking-[.12em] tw-text-amber-700">Pendientes</span>
                            <span class="tw-grid tw-h-8 tw-w-8 tw-place-items-center tw-rounded-xl tw-bg-amber-100 tw-text-amber-700">
                                <svg class="tw-h-4 tw-w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
                            </span>
                        </div>
                        <strong class="tw-mt-3 tw-block tw-text-2xl tw-font-black tw-text-slate-900" id="cotStatPendientes">0</strong>
                        <span class="tw-mt-1 tw-block tw-text-[10px] tw-text-slate-500">Esperando confirmación</span>
                    </div>

                    <div class="tw-rounded-2xl tw-border tw-border-emerald-100 tw-bg-gradient-to-br tw-from-emerald-50 tw-to-white tw-p-4 tw-shadow-sm">
                        <div class="tw-flex tw-items-center tw-justify-between tw-gap-3">
                            <span class="tw-text-[10px] tw-font-extrabold tw-uppercase tw-tracking-[.12em] tw-text-emerald-700">Ejecutadas</span>
                            <span class="tw-grid tw-h-8 tw-w-8 tw-place-items-center tw-rounded-xl tw-bg-emerald-100 tw-text-emerald-700">
                                <svg class="tw-h-4 tw-w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4L19 6"></path></svg>
                            </span>
                        </div>
                        <strong class="tw-mt-3 tw-block tw-text-2xl tw-font-black tw-text-slate-900" id="cotStatEjecutadas">0</strong>
                        <span class="tw-mt-1 tw-block tw-text-[10px] tw-text-slate-500">Convertidas en venta</span>
                    </div>

                    <div class="tw-rounded-2xl tw-border tw-border-rose-100 tw-bg-gradient-to-br tw-from-rose-50 tw-to-white tw-p-4 tw-shadow-sm">
                        <div class="tw-flex tw-items-center tw-justify-between tw-gap-3">
                            <span class="tw-text-[10px] tw-font-extrabold tw-uppercase tw-tracking-[.12em] tw-text-rose-700">Anuladas</span>
                            <span class="tw-grid tw-h-8 tw-w-8 tw-place-items-center tw-rounded-xl tw-bg-rose-100 tw-text-rose-700">
                                <svg class="tw-h-4 tw-w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="9"></circle><path d="M8 8l8 8M16 8l-8 8"></path></svg>
                            </span>
                        </div>
                        <strong class="tw-mt-3 tw-block tw-text-2xl tw-font-black tw-text-slate-900" id="cotStatAnuladas">0</strong>
                        <span class="tw-mt-1 tw-block tw-text-[10px] tw-text-slate-500">Fuera del flujo comercial</span>
                    </div>

                    <div class="tw-rounded-2xl tw-border tw-border-sky-100 tw-bg-gradient-to-br tw-from-sky-50 tw-to-white tw-p-4 tw-shadow-sm">
                        <div class="tw-flex tw-items-center tw-justify-between tw-gap-3">
                            <span class="tw-text-[10px] tw-font-extrabold tw-uppercase tw-tracking-[.12em] tw-text-sky-700">Monto pendiente</span>
                            <span class="tw-grid tw-h-8 tw-w-8 tw-place-items-center tw-rounded-xl tw-bg-sky-100 tw-text-sky-700">
                                <svg class="tw-h-4 tw-w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M4 6h16v12H4z"></path><path d="M8 12h8M12 9v6"></path></svg>
                            </span>
                        </div>
                        <strong class="tw-mt-3 tw-block tw-truncate tw-text-xl tw-font-black tw-text-slate-900 sm:tw-text-2xl" id="cotStatMonto">S/ 0.00</strong>
                        <span class="tw-mt-1 tw-block tw-text-[10px] tw-text-slate-500">Valor por concretar</span>
                    </div>
                </div>

                <div class="tw-overflow-hidden tw-rounded-3xl tw-border tw-border-slate-200/80 tw-bg-white tw-shadow-tique-card">
                    <div class="tw-flex tw-flex-col tw-gap-3 tw-border-b tw-border-slate-100 tw-px-4 tw-py-4 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between sm:tw-px-5">
                        <div>
                            <h2 class="tw-m-0 tw-text-sm tw-font-extrabold tw-text-slate-900">Historial de cotizaciones</h2>
                            <p class="tw-mb-0 tw-mt-1 tw-text-[10px] tw-text-slate-500">Los cambios aparecen sin recargar la página.</p>
                        </div>

                        <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2" id="cotizacionFiltros">
                            <button type="button" class="cq-filter-button is-active tw-h-8 tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-px-3 tw-text-[10px] tw-font-extrabold tw-text-slate-600 tw-transition" data-status-filter="">Todas</button>
                            <button type="button" class="cq-filter-button tw-h-8 tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-px-3 tw-text-[10px] tw-font-extrabold tw-text-slate-600 tw-transition" data-status-filter="Pendiente">Pendientes</button>
                            <button type="button" class="cq-filter-button tw-h-8 tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-px-3 tw-text-[10px] tw-font-extrabold tw-text-slate-600 tw-transition" data-status-filter="Ejecutada">Ejecutadas</button>
                            <button type="button" class="cq-filter-button tw-h-8 tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-px-3 tw-text-[10px] tw-font-extrabold tw-text-slate-600 tw-transition" data-status-filter="Anulada">Anuladas</button>
                        </div>
                    </div>

                    <div class="table-responsive" id="listadoregistros">
                        <table id="tbllistado" class="table tw-m-0 tw-w-full" style="width:100%;">
                            <thead>
                                <tr>
                                    <th>Cotización</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th class="text-right">Total</th>
                                    <th>Estado</th>
                                    <th class="text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Vista previa moderna de cotización -->
    <div id="cotizacionPreviewModal" class="tw-fixed tw-inset-0 tw-z-[1060] tw-hidden" role="dialog" aria-modal="true" aria-labelledby="cotizacionPreviewTitle">
        <div class="tw-absolute tw-inset-0 tw-bg-slate-950/45 tw-backdrop-blur-[2px]" data-cot-preview-close></div>

        <div class="tw-relative tw-mx-auto tw-flex tw-h-full tw-w-full tw-items-center tw-justify-center tw-p-2 sm:tw-p-5">
            <div class="tw-flex tw-h-[min(94vh,920px)] tw-w-full tw-max-w-6xl tw-flex-col tw-overflow-hidden tw-rounded-2xl tw-border tw-border-white/70 tw-bg-white tw-shadow-tique-float sm:tw-rounded-3xl">
                <div class="tw-flex tw-flex-none tw-items-center tw-justify-between tw-gap-3 tw-border-b tw-border-slate-100 tw-bg-white tw-px-4 tw-py-3 sm:tw-px-5">
                    <div class="tw-flex tw-min-w-0 tw-items-center tw-gap-3">
                        <span class="tw-grid tw-h-10 tw-w-10 tw-flex-none tw-place-items-center tw-rounded-xl tw-bg-tique-50 tw-text-tique-600">
                            <svg class="tw-h-5 tw-w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6z"></path><path d="M14 3v4h4M9 11h6M9 15h6"></path></svg>
                        </span>
                        <div class="tw-min-w-0">
                            <div class="tw-flex tw-items-center tw-gap-2">
                                <h2 id="cotizacionPreviewTitle" class="tw-m-0 tw-truncate tw-text-sm tw-font-extrabold tw-text-slate-900 sm:tw-text-base">Vista previa de cotización</h2>
                                <span id="previewHeaderStatus" class="cq-status cq-status--other">Cargando</span>
                            </div>
                            <p id="previewHeaderNumber" class="tw-mb-0 tw-mt-0.5 tw-text-[10px] tw-font-semibold tw-text-slate-500">—</p>
                        </div>
                    </div>

                    <div class="tw-flex tw-flex-none tw-items-center tw-gap-2">
                        <button type="button" id="btnPreviewEjecutar" class="tw-hidden tw-h-9 tw-items-center tw-gap-2 tw-rounded-xl tw-bg-tique-500 tw-px-3 tw-text-[10px] tw-font-extrabold tw-text-white tw-transition hover:tw-bg-tique-600">
                            <svg class="tw-h-4 tw-w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 5v14l11-7z"></path></svg>
                            <span class="tw-hidden sm:tw-inline">Ejecutar en POS</span>
                        </button>
                        <button type="button" id="btnPreviewImprimir" class="tw-grid tw-h-9 tw-w-9 tw-place-items-center tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-transition hover:tw-border-slate-300 hover:tw-bg-slate-50" title="Imprimir cotización">
                            <svg class="tw-h-4 tw-w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M6 9V3h12v6"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v7H6z"></path></svg>
                        </button>
                        <button type="button" class="tw-grid tw-h-9 tw-w-9 tw-place-items-center tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-transition hover:tw-border-rose-200 hover:tw-bg-rose-50 hover:tw-text-rose-600" data-cot-preview-close aria-label="Cerrar vista previa">
                            <svg class="tw-h-4 tw-w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="tw-relative tw-min-h-0 tw-flex-1 tw-overflow-y-auto tw-bg-slate-100/80 tw-p-3 sm:tw-p-5">
                    <div id="cotizacionPreviewLoading" class="tw-absolute tw-inset-0 tw-z-10 tw-grid tw-place-items-center tw-bg-slate-100/80">
                        <div class="tw-flex tw-items-center tw-gap-3 tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-px-4 tw-py-3 tw-shadow-lg">
                            <span class="tw-h-5 tw-w-5 tw-animate-spin tw-rounded-full tw-border-2 tw-border-slate-200 tw-border-t-tique-500"></span>
                            <span class="tw-text-xs tw-font-bold tw-text-slate-600">Preparando cotización…</span>
                        </div>
                    </div>

                    <article id="cotizacionPreviewDocument" class="tw-mx-auto tw-hidden tw-w-full tw-max-w-5xl tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-shadow-sm">
                        <div class="tw-border-b tw-border-slate-100 tw-p-5 sm:tw-p-7">
                            <div class="tw-flex tw-flex-col tw-gap-5 md:tw-flex-row md:tw-items-start md:tw-justify-between">
                                <div class="tw-max-w-xl">
                                    <div class="tw-flex tw-items-center tw-gap-3">
                                        <div id="previewEmpresaInitial" class="tw-grid tw-h-12 tw-w-12 tw-place-items-center tw-rounded-2xl tw-bg-slate-900 tw-text-base tw-font-black tw-text-white">T</div>
                                        <div>
                                            <h3 id="previewEmpresaNombre" class="tw-m-0 tw-text-lg tw-font-black tw-tracking-tight tw-text-slate-900">Empresa</h3>
                                            <p id="previewEmpresaDocumento" class="tw-mb-0 tw-mt-0.5 tw-text-[10px] tw-font-bold tw-uppercase tw-tracking-wide tw-text-slate-500"></p>
                                        </div>
                                    </div>
                                    <div class="tw-mt-3 tw-space-y-1 tw-text-[10px] tw-leading-4 tw-text-slate-500">
                                        <p id="previewEmpresaDireccion" class="tw-m-0"></p>
                                        <p id="previewEmpresaContacto" class="tw-m-0"></p>
                                    </div>
                                </div>

                                <div class="tw-rounded-2xl tw-border tw-border-tique-100 tw-bg-tique-50/70 tw-p-4 md:tw-min-w-[260px] md:tw-text-right">
                                    <span class="tw-text-[9px] tw-font-extrabold tw-uppercase tw-tracking-[.18em] tw-text-tique-700">Cotización</span>
                                    <strong id="previewDocumentoNumero" class="tw-mt-1 tw-block tw-text-xl tw-font-black tw-tracking-tight tw-text-slate-900">—</strong>
                                    <span id="previewDocumentoFecha" class="tw-mt-1 tw-block tw-text-[10px] tw-font-semibold tw-text-slate-500">—</span>
                                </div>
                            </div>
                        </div>

                        <div class="tw-grid tw-gap-4 tw-border-b tw-border-slate-100 tw-bg-slate-50/50 tw-p-5 sm:tw-grid-cols-3 sm:tw-p-7">
                            <div class="sm:tw-col-span-2">
                                <span class="tw-text-[9px] tw-font-extrabold tw-uppercase tw-tracking-[.14em] tw-text-slate-400">Cliente</span>
                                <strong id="previewClienteNombre" class="tw-mt-1 tw-block tw-text-sm tw-font-extrabold tw-text-slate-900">—</strong>
                                <div class="tw-mt-2 tw-grid tw-gap-1 tw-text-[10px] tw-leading-4 tw-text-slate-500">
                                    <span id="previewClienteDocumento"></span>
                                    <span id="previewClienteDireccion"></span>
                                    <span id="previewClienteContacto"></span>
                                </div>
                            </div>
                            <div>
                                <span class="tw-text-[9px] tw-font-extrabold tw-uppercase tw-tracking-[.14em] tw-text-slate-400">Atendido por</span>
                                <strong id="previewVendedor" class="tw-mt-1 tw-block tw-text-xs tw-font-bold tw-text-slate-700">—</strong>
                                <span id="previewEstadoTexto" class="tw-mt-2 tw-block tw-text-[10px] tw-text-slate-500"></span>
                                <span id="previewFormaPago" class="tw-mt-1 tw-block tw-text-[10px] tw-font-semibold tw-text-slate-600"></span>
                            </div>
                        </div>

                        <div class="tw-overflow-x-auto">
                            <table class="tw-w-full tw-min-w-[680px] tw-border-collapse">
                                <thead>
                                    <tr class="tw-border-b tw-border-slate-100 tw-bg-white">
                                        <th class="tw-px-5 tw-py-3 tw-text-left tw-text-[9px] tw-font-extrabold tw-uppercase tw-tracking-[.12em] tw-text-slate-400 sm:tw-px-7">Producto</th>
                                        <th class="tw-px-3 tw-py-3 tw-text-center tw-text-[9px] tw-font-extrabold tw-uppercase tw-tracking-[.12em] tw-text-slate-400">Cant.</th>
                                        <th class="tw-px-3 tw-py-3 tw-text-right tw-text-[9px] tw-font-extrabold tw-uppercase tw-tracking-[.12em] tw-text-slate-400">P. unitario</th>
                                        <th class="tw-px-3 tw-py-3 tw-text-right tw-text-[9px] tw-font-extrabold tw-uppercase tw-tracking-[.12em] tw-text-slate-400">Dscto.</th>
                                        <th class="tw-px-5 tw-py-3 tw-text-right tw-text-[9px] tw-font-extrabold tw-uppercase tw-tracking-[.12em] tw-text-slate-400 sm:tw-px-7">Importe</th>
                                    </tr>
                                </thead>
                                <tbody id="previewDetalleBody"></tbody>
                            </table>
                        </div>

                        <div class="tw-grid tw-gap-5 tw-border-t tw-border-slate-100 tw-p-5 sm:tw-grid-cols-[1fr_300px] sm:tw-p-7">
                            <div class="tw-self-end">
                                <div class="tw-inline-flex tw-items-center tw-gap-2 tw-rounded-xl tw-bg-slate-50 tw-px-3 tw-py-2 tw-text-[9px] tw-font-semibold tw-text-slate-500">
                                    <svg class="tw-h-3.5 tw-w-3.5 tw-text-tique-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v6M12 7h.01"></path></svg>
                                    Vista previa de la información registrada en la cotización.
                                </div>
                            </div>
                            <div id="previewResumen" class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-4"></div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </div>
<?php
} else {
    require 'access.php';
}

require 'footer.php';
?>
<script src="Views/modules/scripts/listcotizacion.js?v=<?= time() ?>"></script>
<?php
ob_end_flush();
?>
