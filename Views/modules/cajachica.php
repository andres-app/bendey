<?php
// Views/modules/cajachica.php
ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['nombre'])) {
    header('Location: login');
    exit();
}

require 'header.php';
require 'sidebar.php';

/*
|--------------------------------------------------------------------------
| CAJA CHICA
| Usa permiso EXISTENTE (ventas)
|--------------------------------------------------------------------------
*/
if (!empty($_SESSION['ventas']) && (int)$_SESSION['ventas'] === 1) {
    $rolCajaSesion = strtoupper(
        trim((string)($_SESSION['rol_caja'] ?? ''))
    );

    $puedeMovimientoManualCaja =
        in_array(
            $rolCajaSesion,
            ['ADMINISTRADOR', 'CAJERO'],
            true
        )
        && (int)($_SESSION['puede_operar_caja'] ?? 0) === 1;

    $puedeAuditarCajas =
        (int)($_SESSION['settings'] ?? 0) === 1;
?>
    <!-- Tailwind aislado para Caja Chica. No altera Bootstrap/Stisla. -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    window.TIQUEPOS_CAJA_PUEDE_MOVIMIENTO_MANUAL =
        <?= $puedeMovimientoManualCaja ? 'true' : 'false' ?>;
</script>

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
                        'tique-soft': '0 16px 45px rgba(15, 23, 42, .08)',
                        'tique-card': '0 8px 28px rgba(15, 23, 42, .07)'
                    }
                }
            }
        };
    </script>

    <style>
        .caja-tw-view {
            --caja-brand: #00a46a;
            --caja-brand-dark: #00754d;
        }

        /* Selector de fecha visual: mismo patrón usado en newsale3.php. */
        .caja-fecha-trigger {
            width: 100%;
            min-height: 44px;
            padding: 0 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            color: #334155;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .025);
            text-align: left;
            transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
        }

        .caja-fecha-trigger:hover {
            border-color: #cbd5e1;
            background: #fff;
        }

        .caja-fecha-trigger:focus,
        .caja-fecha-trigger:focus-visible {
            outline: none !important;
            border-color: #00a46a !important;
            box-shadow: 0 0 0 4px rgba(0, 164, 106, .10) !important;
        }

        .caja-fecha-trigger-icon {
            width: 29px;
            height: 29px;
            flex: 0 0 29px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            color: #00754d;
            background: #ecfdf6;
            font-size: 13px;
        }

        .caja-fecha-trigger-texto {
            min-width: 0;
            flex: 1 1 auto;
            overflow: hidden;
            color: #334155;
            font-size: .875rem;
            font-weight: 500;
            line-height: 1.2;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .caja-fecha-trigger-chevron {
            flex: 0 0 auto;
            color: #94a3b8;
            font-size: 10px;
        }

        #modalFechaCaja .caja-fecha-modal-dialog {
            width: auto;
            max-width: 430px;
        }

        #modalFechaCaja .caja-fecha-modal-content {
            border: 0;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
        }

        #modalFechaCaja .caja-fecha-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        #modalFechaCaja .caja-fecha-modal-icon {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            color: #00754d;
            background: #ecfdf6;
            font-size: 15px;
        }

        #modalFechaCaja .caja-fecha-modal-close {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 12px;
            color: #64748b;
            background: #f8fafc;
            transition: background-color .15s ease, color .15s ease;
        }

        #modalFechaCaja .caja-fecha-modal-close:hover {
            color: #334155;
            background: #f1f5f9;
        }

        #modalFechaCaja button:focus,
        #modalFechaCaja button:active,
        #modalFechaCaja button:focus-visible {
            outline: none !important;
        }

        #modalFechaCaja button:focus-visible {
            box-shadow: 0 0 0 3px rgba(0, 164, 106, .14) !important;
        }

        .caja-calendario {
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
            user-select: none;
        }

        .caja-calendario-nav {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr) 38px;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .caja-calendario-nav-btn {
            width: 38px;
            height: 38px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            color: #526159;
            background: #fff;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }

        .caja-calendario-nav-btn:hover:not(:disabled) {
            color: #00754d;
            border-color: #adebd2;
            background: #ecfdf6;
        }

        .caja-calendario-nav-btn:disabled {
            opacity: .35;
            cursor: not-allowed;
        }

        .caja-calendario-mes {
            overflow: hidden;
            color: #26332c;
            font-size: .92rem;
            font-weight: 600;
            text-align: center;
            text-transform: capitalize;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .caja-calendario-semana,
        .caja-calendario-dias {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 5px;
        }

        .caja-calendario-semana {
            margin-bottom: 6px;
        }

        .caja-calendario-semana span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            color: #94a3b8;
            font-size: .66rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .caja-calendario-dia {
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

        .caja-calendario-dia:hover:not(:disabled):not(.is-empty) {
            color: #00754d;
            border-color: #d7f7e9;
            background: #ecfdf6;
        }

        .caja-calendario-dia.is-today:not(.is-selected) {
            color: #00754d;
            border-color: #adebd2;
            background: #f6fffb;
        }

        .caja-calendario-dia.is-selected {
            color: #fff;
            border-color: #00a46a;
            background: #00a46a;
            box-shadow: 0 6px 14px rgba(0, 164, 106, .22);
        }

        .caja-calendario-dia.is-disabled,
        .caja-calendario-dia:disabled {
            color: #cbd5e1;
            background: transparent;
            cursor: not-allowed;
        }

        .caja-calendario-dia.is-empty {
            pointer-events: none;
        }

        .caja-fecha-modal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .caja-fecha-hoy-btn,
        .caja-fecha-cerrar-btn {
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

        .caja-fecha-hoy-btn {
            color: #00754d;
            border: 1px solid #adebd2;
            background: #ecfdf6;
        }

        .caja-fecha-hoy-btn:hover {
            color: #fff;
            border-color: #00a46a;
            background: #00a46a;
        }

        .caja-fecha-cerrar-btn {
            color: #5d6962;
            border: 1px solid #e2e8f0;
            background: #fff;
        }

        .caja-fecha-cerrar-btn:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .caja-tw-view .caja-scrollbar::-webkit-scrollbar {
            width: 7px;
            height: 7px;
        }

        .caja-tw-view .caja-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 999px;
        }

        .caja-tw-view .caja-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 999px;
        }

        .caja-tw-view .caja-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .caja-tw-view .caja-loading-overlay {
            backdrop-filter: blur(2px);
        }

        .caja-tw-view .caja-summary-value {
            font-variant-numeric: tabular-nums;
        }

        .caja-tw-view .caja-range-btn.is-active {
            color: #ffffff !important;
            background: #00a46a !important;
            border-color: #00a46a !important;
            box-shadow: 0 7px 18px rgba(0, 164, 106, .22);
        }

        .caja-tw-view .caja-range-btn:not(.is-active):hover {
            color: #00754d !important;
            border-color: #adebd2 !important;
            background: #ecfdf6 !important;
        }

        .caja-tw-view .caja-status-dot {
            box-shadow: 0 0 0 5px rgba(255, 255, 255, .12);
        }

        .caja-tw-view .caja-money-positive {
            color: #0f172a;
        }

        .caja-tw-view .caja-money-negative {
            color: #e11d48;
        }

        .caja-tw-view .caja-money-brand {
            color: #00754d;
        }

        .swal2-popup.caja-swal-popup {
            width: min(620px, calc(100% - 24px)) !important;
            border-radius: 24px !important;
            padding: 0 !important;
            overflow: hidden !important;
        }

        .swal2-popup.caja-swal-popup .swal2-title {
            margin: 0 !important;
            padding: 22px 24px 8px !important;
            color: #0f172a !important;
            font-size: 22px !important;
            font-weight: 700 !important;
        }

        .swal2-popup.caja-swal-popup .swal2-html-container {
            margin: 0 !important;
            padding: 10px 24px 0 !important;
        }

        .swal2-popup.caja-swal-popup .swal2-actions {
            width: 100%;
            margin: 22px 0 0 !important;
            padding: 18px 24px 22px !important;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .swal2-popup.caja-swal-popup .swal2-confirm,
        .swal2-popup.caja-swal-popup .swal2-cancel {
            min-height: 44px;
            border-radius: 12px !important;
            padding: 10px 18px !important;
            font-weight: 600 !important;
            box-shadow: none !important;
        }

        .swal2-popup.caja-swal-popup .swal2-confirm {
            background: #00a46a !important;
        }

        .swal2-popup.caja-swal-popup .swal2-cancel {
            color: #475569 !important;
            background: #e2e8f0 !important;
        }

        @media (max-width: 767.98px) {
            .caja-tw-view .caja-desktop-table {
                display: none !important;
            }

            .caja-fecha-trigger-texto {
                font-size: 16px;
                font-weight: 400;
            }

            #modalFechaCaja .caja-fecha-modal-dialog {
                max-width: none;
                margin: 10px;
            }

            #modalFechaCaja .caja-fecha-modal-content {
                border-radius: 18px;
            }

            #modalFechaCaja .caja-fecha-modal-body {
                padding: 12px !important;
            }

            .caja-calendario {
                padding: 12px;
            }

            .caja-calendario-semana,
            .caja-calendario-dias {
                gap: 4px;
            }

            .caja-calendario-dia {
                min-height: 36px;
                border-radius: 10px;
                font-size: 13px;
            }
        }

        @media (min-width: 768px) {
            .caja-tw-view .caja-mobile-list {
                display: none !important;
            }
        }
    </style>

    <div class="main-content caja-tw-view">
        <section class="section">
            <div class="section-body">
                <div class="tw-mx-auto tw-max-w-[1600px] tw-space-y-5">

                    <!-- Encabezado -->
                    <div class="tw-relative tw-overflow-hidden tw-rounded-[26px] tw-bg-gradient-to-br tw-from-tique-700 tw-via-tique-600 tw-to-tique-500 tw-p-5 sm:tw-p-6 lg:tw-p-7 tw-shadow-tique-soft">
                        <div class="tw-pointer-events-none tw-absolute -tw-right-16 -tw-top-24 tw-h-64 tw-w-64 tw-rounded-full tw-bg-white/10"></div>
                        <div class="tw-pointer-events-none tw-absolute -tw-bottom-28 tw-left-1/3 tw-h-60 tw-w-60 tw-rounded-full tw-bg-white/5"></div>

                        <div class="tw-relative tw-flex tw-flex-col tw-gap-5 lg:tw-flex-row lg:tw-items-center lg:tw-justify-between">
                            <div class="tw-flex tw-items-start tw-gap-4">
                                <div class="tw-flex tw-h-12 tw-w-12 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-white/15 tw-text-white tw-shadow-inner">
                                    <i class="fas fa-cash-register tw-text-xl"></i>
                                </div>
                                <div>
                                    <div class="tw-mb-1 tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                        <h1 class="tw-m-0 tw-text-[24px] tw-font-semibold tw-leading-tight tw-text-white sm:tw-text-[28px]">
                                            Caja chica
                                        </h1>
                                        <span
                                            id="estadoCajaBadge"
                                            class="tw-inline-flex tw-items-center tw-gap-2 tw-rounded-full tw-border tw-border-white/20 tw-bg-white/15 tw-px-3 tw-py-1 tw-text-xs tw-font-medium tw-text-white tw-backdrop-blur-sm">
                                            <span id="estadoCajaDot" class="caja-status-dot tw-h-2 tw-w-2 tw-rounded-full tw-bg-emerald-300"></span>
                                            <span id="estadoCajaTexto">Caja abierta</span>
                                        </span>
                                    </div>
                                    <p class="tw-m-0 tw-max-w-2xl tw-text-sm tw-leading-6 tw-text-emerald-50/90">
                                        Controla ingresos, egresos, medios de pago y el efectivo esperado desde una sola vista.
                                    </p>
                                </div>
                            </div>

                            <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                <div class="tw-rounded-xl tw-border tw-border-white/15 tw-bg-white/10 tw-px-3 tw-py-2 tw-text-xs tw-text-emerald-50 tw-backdrop-blur-sm">
                                    <span class="tw-block tw-text-[10px] tw-uppercase tw-tracking-[.12em] tw-text-emerald-100/80">Periodo</span>
                                    <span id="periodoCajaLabel" class="tw-font-medium tw-text-white">Hoy</span>
                                </div>
                                <button
                                    type="button"
                                    id="btnActualizarCaja"
                                    class="tw-inline-flex tw-h-11 tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border tw-border-white/20 tw-bg-white tw-px-4 tw-text-sm tw-font-medium tw-text-tique-700 tw-shadow-sm tw-transition hover:tw-bg-emerald-50 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-white/20"
                                    onclick="cargarCaja({ forzar: true })">
                                    <i class="fas fa-sync-alt" id="iconActualizarCaja"></i>
                                    <span>Actualizar</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros y acciones -->
                    <div class="tw-rounded-[22px] tw-border tw-border-slate-200 tw-bg-white tw-p-4 sm:tw-p-5 tw-shadow-tique-card">
                        <div class="tw-flex tw-flex-col tw-gap-4 xl:tw-flex-row xl:tw-items-end xl:tw-justify-between">
                            <div class="tw-flex-1">
                                <div class="tw-mb-4 tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3">
                                    <div>
                                        <h2 class="tw-m-0 tw-text-[15px] tw-font-semibold tw-text-slate-800">Filtrar movimientos</h2>
                                        <p class="tw-m-0 tw-mt-1 tw-text-xs tw-text-slate-500">El resumen se actualiza automáticamente al cambiar los filtros.</p>
                                    </div>

                                    <div class="tw-inline-flex tw-flex-wrap tw-gap-1.5 tw-rounded-xl tw-bg-slate-100 tw-p-1.5" id="cajaRangosRapidos">
                                        <button type="button" class="caja-range-btn is-active tw-rounded-lg tw-border tw-border-transparent tw-bg-transparent tw-px-3 tw-py-1.5 tw-text-xs tw-font-medium tw-text-slate-600 tw-transition" data-range="today">Hoy</button>
                                        <button type="button" class="caja-range-btn tw-rounded-lg tw-border tw-border-transparent tw-bg-transparent tw-px-3 tw-py-1.5 tw-text-xs tw-font-medium tw-text-slate-600 tw-transition" data-range="7days">7 días</button>
                                        <button type="button" class="caja-range-btn tw-rounded-lg tw-border tw-border-transparent tw-bg-transparent tw-px-3 tw-py-1.5 tw-text-xs tw-font-medium tw-text-slate-600 tw-transition" data-range="month">Este mes</button>
                                    </div>
                                </div>

                                <div class="tw-grid tw-grid-cols-1 tw-gap-3 sm:tw-grid-cols-2 lg:tw-grid-cols-4">
                                    <div class="tw-block">
                                        <label for="btnFechaInicioCaja" class="tw-mb-1.5 tw-block tw-text-xs tw-font-medium tw-text-slate-600">Fecha inicio</label>
                                        <input
                                            type="hidden"
                                            id="fecha_inicio"
                                            value="<?= date('Y-m-d') ?>"
                                            data-max="<?= date('Y-m-d') ?>">
                                        <button
                                            type="button"
                                            id="btnFechaInicioCaja"
                                            class="caja-fecha-trigger"
                                            data-caja-fecha="fecha_inicio"
                                            data-caja-fecha-titulo="Fecha inicio"
                                            aria-haspopup="dialog"
                                            aria-controls="modalFechaCaja">
                                            <span class="caja-fecha-trigger-icon" aria-hidden="true"><i class="far fa-calendar-alt"></i></span>
                                            <span class="caja-fecha-trigger-texto" id="fechaInicioCajaTexto"></span>
                                            <i class="fas fa-chevron-down caja-fecha-trigger-chevron" aria-hidden="true"></i>
                                        </button>
                                    </div>

                                    <div class="tw-block">
                                        <label for="btnFechaFinCaja" class="tw-mb-1.5 tw-block tw-text-xs tw-font-medium tw-text-slate-600">Fecha fin</label>
                                        <input
                                            type="hidden"
                                            id="fecha_fin"
                                            value="<?= date('Y-m-d') ?>"
                                            data-max="<?= date('Y-m-d') ?>">
                                        <button
                                            type="button"
                                            id="btnFechaFinCaja"
                                            class="caja-fecha-trigger"
                                            data-caja-fecha="fecha_fin"
                                            data-caja-fecha-titulo="Fecha fin"
                                            aria-haspopup="dialog"
                                            aria-controls="modalFechaCaja">
                                            <span class="caja-fecha-trigger-icon" aria-hidden="true"><i class="far fa-calendar-check"></i></span>
                                            <span class="caja-fecha-trigger-texto" id="fechaFinCajaTexto"></span>
                                            <i class="fas fa-chevron-down caja-fecha-trigger-chevron" aria-hidden="true"></i>
                                        </button>
                                    </div>

                                    <label class="tw-block">
                                        <span class="tw-mb-1.5 tw-block tw-text-xs tw-font-medium tw-text-slate-600">Sucursal</span>
                                        <div class="tw-relative">
                                            <span class="tw-pointer-events-none tw-absolute tw-inset-y-0 tw-left-0 tw-flex tw-w-10 tw-items-center tw-justify-center tw-text-slate-400">
                                                <i class="fas fa-store"></i>
                                            </span>
                                            <select
                                                class="tw-h-11 tw-w-full tw-appearance-none tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-pl-10 tw-pr-9 tw-text-sm tw-text-slate-500 tw-outline-none"
                                                disabled>
                                                <option>TODOS</option>
                                            </select>
                                            <span class="tw-pointer-events-none tw-absolute tw-inset-y-0 tw-right-0 tw-flex tw-w-9 tw-items-center tw-justify-center tw-text-slate-400">
                                                <i class="fas fa-chevron-down tw-text-[10px]"></i>
                                            </span>
                                        </div>
                                    </label>

                                    <label class="tw-block">
                                        <span class="tw-mb-1.5 tw-block tw-text-xs tw-font-medium tw-text-slate-600">Vendedor</span>
                                        <div class="tw-relative">
                                            <span class="tw-pointer-events-none tw-absolute tw-inset-y-0 tw-left-0 tw-flex tw-w-10 tw-items-center tw-justify-center tw-text-slate-400">
                                                <i class="far fa-user"></i>
                                            </span>
                                            <select
                                                id="idusuario"
                                                class="tw-h-11 tw-w-full tw-appearance-none tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-pl-10 tw-pr-9 tw-text-sm tw-text-slate-700 tw-outline-none tw-transition focus:tw-border-tique-400 focus:tw-ring-4 focus:tw-ring-tique-100">
                                                <option value="">TODOS</option>
                                            </select>
                                            <span class="tw-pointer-events-none tw-absolute tw-inset-y-0 tw-right-0 tw-flex tw-w-9 tw-items-center tw-justify-center tw-text-slate-400">
                                                <i class="fas fa-chevron-down tw-text-[10px]"></i>
                                            </span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="tw-flex tw-flex-wrap tw-gap-2 xl:tw-justify-end">
                                <button
                                    type="button"
                                    class="tw-inline-flex tw-h-11 tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border tw-border-emerald-200 tw-bg-emerald-50 tw-px-4 tw-text-sm tw-font-medium tw-text-emerald-700 tw-transition hover:tw-bg-emerald-100 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-emerald-100"
                                    onclick="exportarExcel()">
                                    <i class="fas fa-file-excel"></i>
                                    Excel
                                </button>

                                <button
                                    type="button"
                                    class="tw-inline-flex tw-h-11 tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border tw-border-rose-200 tw-bg-rose-50 tw-px-4 tw-text-sm tw-font-medium tw-text-rose-700 tw-transition hover:tw-bg-rose-100 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-rose-100"
                                    onclick="exportarPDF()">
                                    <i class="fas fa-file-pdf"></i>
                                    PDF
                                </button>

                                <?php if ($puedeAuditarCajas) { ?>
                                <button
                                    type="button"
                                    id="btnAuditarCajas"
                                    class="tw-inline-flex tw-h-11 tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-px-4 tw-text-sm tw-font-medium tw-text-slate-700 tw-transition hover:tw-border-tique-200 hover:tw-bg-tique-50 hover:tw-text-tique-700 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-100"
                                    onclick="auditarMulticaja()">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Auditar cajas</span>
                                </button>
                                <?php } ?>

                                <?php if ($puedeMovimientoManualCaja) { ?>
                                <button
                                    type="button"
                                    id="btnMovimientoManualCaja"
                                    class="tw-inline-flex tw-h-11 tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border tw-border-tique-200 tw-bg-tique-50 tw-px-4 tw-text-sm tw-font-medium tw-text-tique-700 tw-transition hover:tw-bg-tique-100 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-tique-100 disabled:tw-cursor-not-allowed disabled:tw-opacity-50"
                                    onclick="abrirMovimientoManualCaja()">
                                    <i class="fas fa-exchange-alt"></i>
                                    <span>Movimiento</span>
                                </button>
                                <?php } ?>

                                <button
                                    type="button"
                                    id="btnCerrarCaja"
                                    class="tw-inline-flex tw-h-11 tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-bg-slate-900 tw-px-4 tw-text-sm tw-font-medium tw-text-white tw-shadow-sm tw-transition hover:tw-bg-slate-800 focus:tw-outline-none focus:tw-ring-4 focus:tw-ring-slate-200 disabled:tw-cursor-not-allowed disabled:tw-bg-slate-300 disabled:tw-shadow-none"
                                    onclick="cerrarCaja()">
                                    <i class="fas fa-lock"></i>
                                    <span>Cerrar caja</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen financiero -->
                    <div class="tw-grid tw-grid-cols-1 tw-gap-3 sm:tw-grid-cols-2 xl:tw-grid-cols-3 2xl:tw-grid-cols-6">
                        <div class="tw-rounded-[20px] tw-border tw-border-slate-200 tw-bg-white tw-p-4 tw-shadow-tique-card tw-transition hover:-tw-translate-y-0.5 hover:tw-shadow-tique-soft">
                            <div class="tw-flex tw-items-start tw-justify-between tw-gap-3">
                                <div>
                                    <p class="tw-m-0 tw-text-xs tw-font-medium tw-text-slate-500">Ventas cobradas</p>
                                    <p id="totalVentasBrutas" class="caja-summary-value tw-m-0 tw-mt-2 tw-text-[21px] tw-font-semibold tw-leading-none tw-text-slate-900">S/ 0.00</p>
                                </div>
                                <span class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-rounded-xl tw-bg-tique-50 tw-text-tique-600"><i class="fas fa-receipt"></i></span>
                            </div>
                        </div>

                        <div class="tw-rounded-[20px] tw-border tw-border-slate-200 tw-bg-white tw-p-4 tw-shadow-tique-card tw-transition hover:-tw-translate-y-0.5 hover:tw-shadow-tique-soft">
                            <div class="tw-flex tw-items-start tw-justify-between tw-gap-3">
                                <div>
                                    <p class="tw-m-0 tw-text-xs tw-font-medium tw-text-slate-500">Devoluciones N.C.</p>
                                    <p id="totalNotasCredito" class="caja-summary-value tw-m-0 tw-mt-2 tw-text-[21px] tw-font-semibold tw-leading-none tw-text-rose-600">- S/ 0.00</p>
                                </div>
                                <span class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-rounded-xl tw-bg-rose-50 tw-text-rose-600"><i class="fas fa-undo-alt"></i></span>
                            </div>
                        </div>

                        <div class="tw-rounded-[20px] tw-border tw-border-slate-200 tw-bg-white tw-p-4 tw-shadow-tique-card tw-transition hover:-tw-translate-y-0.5 hover:tw-shadow-tique-soft">
                            <div class="tw-flex tw-items-start tw-justify-between tw-gap-3">
                                <div>
                                    <p class="tw-m-0 tw-text-xs tw-font-medium tw-text-slate-500">Otros ingresos</p>
                                    <p id="totalOtrosIngresos" class="caja-summary-value tw-m-0 tw-mt-2 tw-text-[21px] tw-font-semibold tw-leading-none tw-text-slate-900">S/ 0.00</p>
                                </div>
                                <span class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-rounded-xl tw-bg-sky-50 tw-text-sky-600"><i class="fas fa-arrow-down"></i></span>
                            </div>
                        </div>

                        <div class="tw-rounded-[20px] tw-border tw-border-slate-200 tw-bg-white tw-p-4 tw-shadow-tique-card tw-transition hover:-tw-translate-y-0.5 hover:tw-shadow-tique-soft">
                            <div class="tw-flex tw-items-start tw-justify-between tw-gap-3">
                                <div>
                                    <p class="tw-m-0 tw-text-xs tw-font-medium tw-text-slate-500">Otros egresos</p>
                                    <p id="totalOtrosEgresos" class="caja-summary-value tw-m-0 tw-mt-2 tw-text-[21px] tw-font-semibold tw-leading-none tw-text-amber-600">- S/ 0.00</p>
                                </div>
                                <span class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-rounded-xl tw-bg-amber-50 tw-text-amber-600"><i class="fas fa-arrow-up"></i></span>
                            </div>
                        </div>

                        <div class="tw-rounded-[20px] tw-border tw-border-tique-100 tw-bg-gradient-to-br tw-from-white tw-to-tique-50 tw-p-4 tw-shadow-tique-card tw-transition hover:-tw-translate-y-0.5 hover:tw-shadow-tique-soft">
                            <div class="tw-flex tw-items-start tw-justify-between tw-gap-3">
                                <div>
                                    <p class="tw-m-0 tw-text-xs tw-font-medium tw-text-tique-700">Movimiento neto</p>
                                    <p id="totalResultadoNeto" class="caja-summary-value tw-m-0 tw-mt-2 tw-text-[21px] tw-font-semibold tw-leading-none tw-text-tique-700">S/ 0.00</p>
                                </div>
                                <span class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-rounded-xl tw-bg-white tw-text-tique-600 tw-shadow-sm"><i class="fas fa-chart-line"></i></span>
                            </div>
                        </div>

                        <div class="tw-rounded-[20px] tw-border tw-border-slate-800 tw-bg-slate-900 tw-p-4 tw-shadow-tique-card tw-transition hover:-tw-translate-y-0.5 hover:tw-shadow-tique-soft">
                            <div class="tw-flex tw-items-start tw-justify-between tw-gap-3">
                                <div>
                                    <p class="tw-m-0 tw-text-xs tw-font-medium tw-text-slate-300">Efectivo esperado</p>
                                    <p id="totalCaja" class="caja-summary-value tw-m-0 tw-mt-2 tw-text-[21px] tw-font-semibold tw-leading-none tw-text-white">S/ 0.00</p>
                                </div>
                                <span class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-rounded-xl tw-bg-white/10 tw-text-emerald-300"><i class="fas fa-wallet"></i></span>
                            </div>
                        </div>
                    </div>

                    <!-- Detalle -->
                    <div class="tw-relative tw-overflow-hidden tw-rounded-[22px] tw-border tw-border-slate-200 tw-bg-white tw-shadow-tique-card">
                        <div class="tw-flex tw-flex-col tw-gap-2 tw-border-b tw-border-slate-100 tw-p-4 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between sm:tw-px-5">
                            <div>
                                <div class="tw-flex tw-items-center tw-gap-2">
                                    <h2 class="tw-m-0 tw-text-[15px] tw-font-semibold tw-text-slate-800">Detalle por comprobante</h2>
                                    <span id="tablaCajaMeta" class="tw-inline-flex tw-items-center tw-rounded-full tw-bg-slate-100 tw-px-2.5 tw-py-1 tw-text-[11px] tw-font-medium tw-text-slate-600">0 movimientos</span>
                                </div>
                                <p class="tw-m-0 tw-mt-1 tw-text-xs tw-text-slate-500">Distribución de cobros según el medio de pago.</p>
                            </div>
                            <div class="tw-flex tw-items-center tw-gap-2 tw-text-[11px] tw-text-slate-400">
                                <i class="far fa-clock"></i>
                                <span id="ultimaActualizacionCaja">Sin actualizar</span>
                            </div>
                        </div>

                        <!-- Escritorio/tablet -->
                        <div class="caja-desktop-table caja-scrollbar tw-overflow-x-auto">
                            <table class="tw-m-0 tw-w-full tw-min-w-[860px] tw-border-collapse" id="tablaCaja">
                                <thead>
                                    <tr class="tw-bg-slate-50 tw-text-left tw-text-[11px] tw-uppercase tw-tracking-[.06em] tw-text-slate-500">
                                        <th class="tw-border-b tw-border-slate-200 tw-px-5 tw-py-3.5 tw-font-semibold">Comprobante</th>
                                        <th class="tw-border-b tw-border-slate-200 tw-px-4 tw-py-3.5 tw-text-right tw-font-semibold">Efectivo</th>
                                        <th class="tw-border-b tw-border-slate-200 tw-px-4 tw-py-3.5 tw-text-right tw-font-semibold">Tarjeta</th>
                                        <th class="tw-border-b tw-border-slate-200 tw-px-4 tw-py-3.5 tw-text-right tw-font-semibold">Transferencia</th>
                                        <th class="tw-border-b tw-border-slate-200 tw-px-4 tw-py-3.5 tw-text-right tw-font-semibold">Yape / Plin</th>
                                        <th class="tw-border-b tw-border-slate-200 tw-px-5 tw-py-3.5 tw-text-right tw-font-semibold">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="tw-divide-y tw-divide-slate-100">
                                    <tr>
                                        <td colspan="6" class="tw-px-5 tw-py-12 tw-text-center tw-text-sm tw-text-slate-400">Cargando movimientos...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Móvil -->
                        <div id="cajaMobileList" class="caja-mobile-list tw-space-y-3 tw-p-4">
                            <div class="tw-rounded-2xl tw-border tw-border-dashed tw-border-slate-200 tw-p-8 tw-text-center tw-text-sm tw-text-slate-400">Cargando movimientos...</div>
                        </div>

                        <div id="cajaLoading" class="caja-loading-overlay tw-pointer-events-none tw-absolute tw-inset-0 tw-z-20 tw-hidden tw-items-center tw-justify-center tw-bg-white/65">
                            <div class="tw-flex tw-items-center tw-gap-3 tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-px-4 tw-py-3 tw-shadow-lg">
                                <span class="tw-h-5 tw-w-5 tw-animate-spin tw-rounded-full tw-border-2 tw-border-slate-200 tw-border-t-tique-500"></span>
                                <span class="tw-text-sm tw-font-medium tw-text-slate-600">Actualizando caja...</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </div>

    <!-- Selector de fechas de Caja Chica: mismo patrón visual que newsale3.php -->
    <div
        class="modal fade"
        id="modalFechaCaja"
        tabindex="-1"
        role="dialog"
        aria-labelledby="modalFechaCajaTitulo"
        aria-hidden="true"
        data-backdrop="static"
        data-keyboard="true">

        <div class="modal-dialog modal-dialog-centered caja-fecha-modal-dialog" role="document">
            <div class="modal-content caja-fecha-modal-content tw-border-0 tw-rounded-2xl tw-shadow-2xl tw-overflow-hidden">
                <div class="modal-header caja-fecha-modal-header tw-border-b tw-border-slate-100 tw-bg-white tw-px-5 tw-py-4">
                    <div class="tw-flex tw-items-center tw-gap-3 tw-min-w-0">
                        <span class="caja-fecha-modal-icon" aria-hidden="true"><i class="far fa-calendar-alt"></i></span>
                        <div class="tw-min-w-0">
                            <h5 class="modal-title tw-m-0 tw-text-base tw-font-medium tw-text-slate-900" id="modalFechaCajaTitulo">Seleccionar fecha</h5>
                            <small class="tw-block tw-mt-1 tw-text-slate-500">Selecciona una fecha para filtrar los movimientos</small>
                        </div>
                    </div>
                    <button type="button" class="caja-fecha-modal-close" data-dismiss="modal" aria-label="Cerrar">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="modal-body caja-fecha-modal-body tw-bg-slate-50 tw-p-4 sm:tw-p-5">
                    <div class="caja-calendario">
                        <div class="caja-calendario-nav">
                            <button type="button" class="caja-calendario-nav-btn" id="btnFechaCajaAnterior" aria-label="Mes anterior">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                            </button>
                            <div class="caja-calendario-mes" id="fechaCajaMesTitulo"></div>
                            <button type="button" class="caja-calendario-nav-btn" id="btnFechaCajaSiguiente" aria-label="Mes siguiente">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div class="caja-calendario-semana" aria-hidden="true">
                            <span>Lu</span><span>Ma</span><span>Mi</span><span>Ju</span><span>Vi</span><span>Sá</span><span>Do</span>
                        </div>

                        <div class="caja-calendario-dias" id="fechaCajaDias" role="grid" aria-label="Calendario de filtro de caja"></div>
                    </div>

                    <div class="tw-mt-3 tw-rounded-xl tw-border tw-border-tique-100 tw-bg-tique-50 tw-px-3 tw-py-2.5">
                        <span class="tw-text-xs tw-text-slate-500">Fecha seleccionada</span>
                        <strong id="fechaCajaSeleccionResumen" class="tw-block tw-mt-0.5 tw-text-sm tw-font-medium tw-text-slate-800"></strong>
                    </div>
                </div>

                <div class="modal-footer caja-fecha-modal-footer tw-border-t tw-border-slate-100 tw-bg-white tw-px-4 tw-py-3 sm:tw-px-5">
                    <button type="button" id="btnFechaCajaHoy" class="caja-fecha-hoy-btn">
                        <i class="far fa-calendar-check" aria-hidden="true"></i>
                        Hoy
                    </button>
                    <button type="button" class="caja-fecha-cerrar-btn" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
<?php
} else {
    require 'access.php';
}

require 'footer.php';
ob_end_flush();
?>
