<?php
ob_start();
session_start();

if (!isset($_SESSION['nombre'])) {
    header('location: login');
    exit();
}

require 'header.php';
require 'sidebar.php';

if ($_SESSION['almacen'] == 1) {
?>
    <!-- Tailwind aislado para Categorías. Preflight desactivado para no interferir con Bootstrap/Stisla. -->
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
                        'tique-soft': '0 18px 45px rgba(15, 23, 42, .08)',
                        'tique-card': '0 8px 28px rgba(15, 23, 42, .07)'
                    }
                }
            }
        };
    </script>

    <style>
        .category-tw-view {
            --category-brand: #00a46a;
            --category-brand-dark: #00754d;
            --category-text: #0f172a;
            --category-muted: #64748b;
            --category-border: #e2e8f0;
        }

        .category-tw-view button,
        #modalSubcategorias button {
            font-weight: 500 !important;
        }

        .category-tw-view button:focus,
        .category-tw-view button:active,
        .category-tw-view button:focus-visible,
        #modalSubcategorias button:focus,
        #modalSubcategorias button:active,
        #modalSubcategorias button:focus-visible {
            outline: none !important;
            box-shadow: none !important;
        }

        .category-tw-view .category-input,
        #modalSubcategorias .category-input {
            width: 100%;
            min-height: 46px;
            border: 1px solid #e2e8f0;
            border-radius: 13px;
            background: #fff;
            color: #334155;
            font-size: .875rem;
            font-weight: 400;
            padding: 0 14px;
            transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
        }

        .category-tw-view .category-input::placeholder,
        #modalSubcategorias .category-input::placeholder {
            color: #94a3b8;
            opacity: 1;
        }

        .category-tw-view .category-input:hover,
        #modalSubcategorias .category-input:hover {
            border-color: #cbd5e1;
        }

        .category-tw-view .category-input:focus,
        #modalSubcategorias .category-input:focus {
            border-color: #00a46a !important;
            box-shadow: 0 0 0 4px rgba(0, 164, 106, .10) !important;
            outline: none !important;
        }

        .category-table-shell .dataTables_wrapper {
            width: 100%;
        }

        .category-table-shell .dt-buttons,
        .category-table-shell .dataTables_filter,
        .category-table-shell .dataTables_length {
            display: none !important;
        }

        .category-tw-view #categorySearch {
            padding-left: 42px !important;
            padding-right: 14px !important;
        }

        .category-table-shell .dataTables_info {
            color: #64748b;
            font-size: .82rem;
            padding-top: 18px !important;
        }

        .category-table-shell .dataTables_paginate {
            padding-top: 12px !important;
        }

        .category-table-shell .pagination {
            gap: 5px;
            margin: 0;
        }

        .category-table-shell .page-item .page-link {
            min-width: 36px;
            height: 36px;
            border: 1px solid #e2e8f0;
            border-radius: 10px !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            background: #fff;
            font-size: .82rem;
            font-weight: 500;
            box-shadow: none !important;
        }

        .category-table-shell .page-item.active .page-link {
            border-color: #00a46a !important;
            background: #00a46a !important;
            color: #fff !important;
        }

        .category-table-shell .page-item.disabled .page-link {
            color: #cbd5e1;
            background: #f8fafc;
        }

        .category-table-shell table.dataTable {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
        }

        .category-table-shell #tbllistado thead th {
            border-top: 0 !important;
            border-bottom: 1px solid #e2e8f0 !important;
            background: #f8fafc;
            color: #64748b;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .045em;
            text-transform: uppercase;
            padding: 13px 15px !important;
            white-space: nowrap;
        }

        .category-table-shell #tbllistado tbody td {
            border-top: 0 !important;
            border-bottom: 1px solid #f1f5f9 !important;
            color: #334155;
            font-size: .86rem;
            padding: 13px 15px !important;
            vertical-align: middle !important;
            background: #fff;
        }

        .category-table-shell #tbllistado tbody tr:last-child td {
            border-bottom: 0 !important;
        }

        .category-table-shell #tbllistado tbody tr:hover td {
            background: #fbfefc !important;
        }

        .category-name-cell {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 180px;
        }

        .category-name-icon {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            color: #00754d;
            background: #ecfdf6;
        }

        .category-name-text {
            color: #1e293b;
            font-weight: 500;
            line-height: 1.2;
        }

        .category-actions {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .category-icon-btn {
            width: 36px;
            height: 36px;
            padding: 0;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            color: #64748b;
            cursor: pointer;
            transition: transform .15s ease, border-color .15s ease, background-color .15s ease, color .15s ease;
        }

        .category-icon-btn:hover {
            transform: translateY(-1px);
        }

        .category-icon-btn--edit:hover {
            border-color: #fde68a;
            background: #fffbeb;
            color: #b45309;
        }

        .category-icon-btn--danger:hover {
            border-color: #fecaca;
            background: #fef2f2;
            color: #dc2626;
        }

        .category-icon-btn--activate:hover {
            border-color: #adebd2;
            background: #ecfdf6;
            color: #00754d;
        }

        .category-sub-btn {
            min-height: 36px;
            padding: 0 12px;
            border: 1px solid #d7f7e9;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            background: #ecfdf6;
            color: #00754d;
            font-size: .8rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform .15s ease, border-color .15s ease, background-color .15s ease;
        }

        .category-sub-btn:hover {
            transform: translateY(-1px);
            border-color: #adebd2;
            background: #d7f7e9;
            color: #00603f;
        }

        .category-status {
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: .76rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .category-status::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: currentColor;
        }

        .category-status--active {
            color: #047857;
            background: #ecfdf5;
        }

        .category-status--inactive {
            color: #b91c1c;
            background: #fef2f2;
        }

        #modalEditarCategoria .modal-dialog {
            max-width: 520px;
        }

        #modalEditarCategoria .modal-content {
            border: 0;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .20);
        }

        #modalEditarCategoria .modal-header {
            border-bottom: 1px solid #f1f5f9;
            padding: 18px 20px;
            align-items: center;
        }

        #modalEditarCategoria .modal-body {
            padding: 20px;
        }

        #modalEditarCategoria .modal-footer {
            padding: 0 20px 20px;
            border-top: 0;
        }

        /* Campo de edición moderno: label flotante + icono integrado */
        #modalEditarCategoria .category-edit-field {
            position: relative;
            width: 100%;
        }

        #modalEditarCategoria .category-edit-field__icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 2;
            width: 30px;
            height: 30px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ecfdf6;
            color: #008d5b;
            pointer-events: none;
            transition: background-color .18s ease, color .18s ease, transform .18s ease;
        }

        #modalEditarCategoria .category-edit-field__input {
            width: 100%;
            height: 60px;
            border: 1px solid #dbe4ea !important;
            border-radius: 16px !important;
            background: #fff !important;
            color: #0f172a !important;
            font-size: .96rem !important;
            font-weight: 400 !important;
            line-height: 1.2;
            padding: 22px 46px 8px 58px !important;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .025) !important;
            outline: none !important;
            transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease, transform .18s ease;
        }

        #modalEditarCategoria .category-edit-field__input:hover {
            border-color: #b9c7d2 !important;
        }

        #modalEditarCategoria .category-edit-field__input:focus {
            border-color: #00a46a !important;
            box-shadow: 0 0 0 4px rgba(0, 164, 106, .10) !important;
            background: #fff !important;
        }

        #modalEditarCategoria .category-edit-field__label {
            position: absolute;
            left: 58px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 2;
            margin: 0;
            color: #94a3b8;
            font-size: .86rem;
            font-weight: 400;
            line-height: 1;
            pointer-events: none;
            transform-origin: left center;
            transition: top .16s ease, transform .16s ease, font-size .16s ease, color .16s ease;
        }

        #modalEditarCategoria .category-edit-field__required {
            color: #f43f5e;
        }

        #modalEditarCategoria .category-edit-field__input:focus + .category-edit-field__label,
        #modalEditarCategoria .category-edit-field__input:not(:placeholder-shown) + .category-edit-field__label {
            top: 12px;
            transform: none;
            font-size: .66rem;
            font-weight: 500;
            color: #64748b;
        }

        #modalEditarCategoria .category-edit-field:focus-within .category-edit-field__label {
            color: #008d5b;
        }

        #modalEditarCategoria .category-edit-field:focus-within .category-edit-field__icon {
            background: #d7f7e9;
            color: #00754d;
            transform: translateY(-50%) scale(1.04);
        }

        #modalEditarCategoria .category-edit-field__hint {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 8px 2px 0;
            color: #94a3b8;
            font-size: .72rem;
            line-height: 1.35;
        }

        #modalEditarCategoria .category-edit-field__hint i {
            color: #00a46a;
            font-size: .65rem;
        }

        #modalEditarCategoria .category-modal-close {
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #64748b;
            font-size: 18px;
            line-height: 1;
        }

        #modalEditarCategoria .category-modal-close:hover {
            background: #f1f5f9;
            color: #334155;
        }

        #modalSubcategorias .modal-dialog {
            max-width: 760px;
        }

        #modalSubcategorias .modal-content {
            border: 0;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .20);
        }

        #modalSubcategorias .modal-header {
            border-bottom: 1px solid #f1f5f9;
            padding: 18px 20px;
            align-items: center;
        }

        #modalSubcategorias .modal-body {
            padding: 20px;
        }

        #modalSubcategorias .category-modal-close {
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #64748b;
            font-size: 18px;
            line-height: 1;
        }

        #modalSubcategorias .category-modal-close:hover {
            background: #f1f5f9;
            color: #334155;
        }

        #modalSubcategorias .category-sub-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        #modalSubcategorias .category-sub-table thead th {
            padding: 11px 13px;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #64748b;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        #modalSubcategorias .category-sub-table tbody td {
            padding: 12px 13px;
            border: 0;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #334155;
            font-size: .86rem;
        }

        #modalSubcategorias .category-sub-table tbody tr:last-child td {
            border-bottom: 0;
        }

        #modalSubcategorias .category-sub-action {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            color: #64748b;
            cursor: pointer;
        }

        #modalSubcategorias .category-sub-action.is-danger:hover {
            border-color: #fecaca;
            background: #fef2f2;
            color: #dc2626;
        }

        #modalSubcategorias .category-sub-action.is-success:hover {
            border-color: #adebd2;
            background: #ecfdf6;
            color: #00754d;
        }

        @media (max-width: 767.98px) {
            .category-table-shell .dataTables_info,
            .category-table-shell .dataTables_paginate {
                float: none !important;
                width: 100%;
                text-align: center !important;
            }

            .category-table-shell .pagination {
                justify-content: center;
                flex-wrap: wrap;
            }

            #modalSubcategorias .modal-dialog,
            #modalEditarCategoria .modal-dialog {
                margin: 12px;
            }

            #modalSubcategorias .modal-body,
            #modalSubcategorias .modal-header,
            #modalEditarCategoria .modal-body,
            #modalEditarCategoria .modal-header,
            #modalEditarCategoria .modal-footer {
                padding-left: 15px;
                padding-right: 15px;
            }
        }
    </style>

    <div class="main-content category-tw-view">
        <section class="section">
            <div class="section-body tw-max-w-[1500px] tw-mx-auto">
                <div class="tw-rounded-[22px] tw-border tw-border-slate-200 tw-bg-white tw-shadow-tique-soft tw-overflow-hidden">
                    <div class="tw-p-4 sm:tw-p-5 lg:tw-p-6 tw-border-b tw-border-slate-100">
                        <div class="tw-flex tw-flex-col lg:tw-flex-row lg:tw-items-center lg:tw-justify-between tw-gap-4">
                            <div class="tw-flex tw-items-start tw-gap-3">
                                <div class="tw-w-11 tw-h-11 tw-shrink-0 tw-rounded-2xl tw-bg-tique-50 tw-text-tique-700 tw-flex tw-items-center tw-justify-center">
                                    <i class="fas fa-tags"></i>
                                </div>
                                <div>
                                    <div class="tw-flex tw-items-center tw-gap-2 tw-flex-wrap">
                                        <h1 class="tw-m-0 tw-text-[1.25rem] sm:tw-text-[1.4rem] tw-font-semibold tw-text-slate-900">Categorías</h1>
                                        <span class="tw-inline-flex tw-items-center tw-rounded-full tw-bg-slate-100 tw-px-2.5 tw-py-1 tw-text-[.72rem] tw-font-medium tw-text-slate-500">Inventario</span>
                                    </div>
                                    <p class="tw-m-0 tw-mt-1 tw-text-[.84rem] tw-leading-5 tw-text-slate-500">Organiza tus productos y administra sus subcategorías desde un solo lugar.</p>
                                </div>
                            </div>

                            <button type="button" id="btnagregar" onclick="mostrarform(true)"
                                class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[42px] tw-px-4 tw-rounded-xl tw-border-0 tw-bg-tique-500 hover:tw-bg-tique-600 tw-text-white tw-text-sm tw-font-medium tw-shadow-[0_8px_20px_rgba(0,164,106,.18)] tw-transition-all hover:-tw-translate-y-0.5">
                                <i class="fas fa-plus tw-text-xs"></i>
                                Nueva categoría
                            </button>
                        </div>
                    </div>

                    <div id="listadoregistros" class="tw-p-4 sm:tw-p-5 lg:tw-p-6">
                        <div class="tw-grid tw-grid-cols-1 sm:tw-grid-cols-3 tw-gap-3 tw-mb-5">
                            <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/60 tw-p-4">
                                <div class="tw-flex tw-items-center tw-justify-between tw-gap-3">
                                    <div>
                                        <p class="tw-m-0 tw-text-[.75rem] tw-font-medium tw-text-slate-500">Total categorías</p>
                                        <p id="categoryStatTotal" class="tw-m-0 tw-mt-1 tw-text-2xl tw-font-semibold tw-text-slate-900">0</p>
                                    </div>
                                    <span class="tw-w-10 tw-h-10 tw-rounded-xl tw-bg-white tw-border tw-border-slate-200 tw-flex tw-items-center tw-justify-center tw-text-slate-500"><i class="fas fa-layer-group"></i></span>
                                </div>
                            </div>
                            <div class="tw-rounded-2xl tw-border tw-border-emerald-100 tw-bg-emerald-50/60 tw-p-4">
                                <div class="tw-flex tw-items-center tw-justify-between tw-gap-3">
                                    <div>
                                        <p class="tw-m-0 tw-text-[.75rem] tw-font-medium tw-text-emerald-700">Activas</p>
                                        <p id="categoryStatActive" class="tw-m-0 tw-mt-1 tw-text-2xl tw-font-semibold tw-text-emerald-800">0</p>
                                    </div>
                                    <span class="tw-w-10 tw-h-10 tw-rounded-xl tw-bg-white/80 tw-border tw-border-emerald-100 tw-flex tw-items-center tw-justify-center tw-text-emerald-600"><i class="fas fa-check"></i></span>
                                </div>
                            </div>
                            <div class="tw-rounded-2xl tw-border tw-border-rose-100 tw-bg-rose-50/60 tw-p-4">
                                <div class="tw-flex tw-items-center tw-justify-between tw-gap-3">
                                    <div>
                                        <p class="tw-m-0 tw-text-[.75rem] tw-font-medium tw-text-rose-700">Inactivas</p>
                                        <p id="categoryStatInactive" class="tw-m-0 tw-mt-1 tw-text-2xl tw-font-semibold tw-text-rose-800">0</p>
                                    </div>
                                    <span class="tw-w-10 tw-h-10 tw-rounded-xl tw-bg-white/80 tw-border tw-border-rose-100 tw-flex tw-items-center tw-justify-center tw-text-rose-500"><i class="fas fa-pause"></i></span>
                                </div>
                            </div>
                        </div>

                        <div class="tw-flex tw-flex-col md:tw-flex-row md:tw-items-center md:tw-justify-between tw-gap-3 tw-mb-4">
                            <div class="tw-relative tw-w-full md:tw-max-w-md">
                                <span class="tw-absolute tw-left-3.5 tw-top-1/2 -tw-translate-y-1/2 tw-text-slate-400 tw-pointer-events-none">
                                    <i class="fas fa-search tw-text-xs"></i>
                                </span>
                                <input type="search" id="categorySearch" class="category-input tw-pl-10" placeholder="Buscar categoría..." autocomplete="off">
                            </div>

                            <div class="tw-flex tw-items-center tw-gap-2 tw-flex-wrap">
                                <button type="button" id="btnExportExcel" class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[40px] tw-px-3.5 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white hover:tw-bg-slate-50 tw-text-slate-600 tw-text-[.82rem] tw-transition-colors">
                                    <i class="far fa-file-excel tw-text-tique-600"></i> Excel
                                </button>
                                <button type="button" id="btnExportPdf" class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[40px] tw-px-3.5 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white hover:tw-bg-slate-50 tw-text-slate-600 tw-text-[.82rem] tw-transition-colors">
                                    <i class="far fa-file-pdf tw-text-rose-500"></i> PDF
                                </button>
                            </div>
                        </div>

                        <div class="category-table-shell tw-rounded-2xl tw-border tw-border-slate-200 tw-overflow-hidden">
                            <div class="table-responsive tw-m-0">
                                <table id="tbllistado" class="table text-nowrap tw-m-0" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Categoría</th>
                                            <th>Subcategorías</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="formularioregistros" class="tw-p-4 sm:tw-p-5 lg:tw-p-6">
                        <div class="tw-max-w-3xl tw-mx-auto tw-rounded-[20px] tw-border tw-border-slate-200 tw-bg-slate-50/40 tw-p-4 sm:tw-p-5">
                            <div class="tw-flex tw-items-start tw-justify-between tw-gap-4 tw-mb-5">
                                <div>
                                    <div class="tw-flex tw-items-center tw-gap-2">
                                        <span class="tw-w-9 tw-h-9 tw-rounded-xl tw-bg-tique-50 tw-text-tique-700 tw-flex tw-items-center tw-justify-center"><i class="fas fa-tag tw-text-sm"></i></span>
                                        <div>
                                            <h2 id="categoryFormTitle" class="tw-m-0 tw-text-base tw-font-semibold tw-text-slate-900">Nueva categoría</h2>
                                            <p id="categoryFormSubtitle" class="tw-m-0 tw-mt-0.5 tw-text-[.8rem] tw-text-slate-500">Completa los datos para crear una categoría.</p>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" onclick="cancelarform()" aria-label="Cerrar formulario" class="tw-w-9 tw-h-9 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white hover:tw-bg-slate-50 tw-text-slate-500 tw-flex tw-items-center tw-justify-center tw-transition-colors">
                                    <i class="fas fa-times tw-text-xs"></i>
                                </button>
                            </div>

                            <form action="" name="formulario" id="formulario" method="POST">
                                <input type="hidden" name="idcategoria" id="idcategoria">

                                <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                                    <div>
                                        <label for="nombre" class="tw-block tw-mb-1.5 tw-text-[.78rem] tw-font-medium tw-text-slate-600">Nombre de la categoría <span class="tw-text-rose-500">*</span></label>
                                        <input class="category-input" type="text" name="nombre" id="nombre" maxlength="50" placeholder="Ej. Accesorios" required autocomplete="off">
                                    </div>

                                    <div>
                                        <label for="descripcion" class="tw-block tw-mb-1.5 tw-text-[.78rem] tw-font-medium tw-text-slate-600">Descripción / valores</label>
                                        <input class="category-input" type="text" name="descripcion" id="descripcion" maxlength="256" placeholder="Descripción opcional" autocomplete="off">
                                    </div>
                                </div>

                                <div class="tw-flex tw-flex-col-reverse sm:tw-flex-row sm:tw-justify-end tw-gap-2.5 tw-mt-6">
                                    <button type="button" onclick="cancelarform()" class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[42px] tw-px-4 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white hover:tw-bg-slate-50 tw-text-slate-600 tw-text-sm tw-transition-colors">
                                        <i class="fas fa-arrow-left tw-text-xs"></i> Cancelar
                                    </button>
                                    <button type="submit" id="btnGuardar" class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[42px] tw-px-4 tw-rounded-xl tw-border-0 tw-bg-tique-500 hover:tw-bg-tique-600 disabled:tw-opacity-60 disabled:tw-cursor-not-allowed tw-text-white tw-text-sm tw-shadow-[0_8px_20px_rgba(0,164,106,.18)] tw-transition-all">
                                        <i class="fas fa-save tw-text-xs"></i> Guardar categoría
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- MODAL EDITAR CATEGORÍA -->
    <div class="modal fade" id="modalEditarCategoria" tabindex="-1" role="dialog" aria-labelledby="modalEditarCategoriaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <span class="tw-w-10 tw-h-10 tw-rounded-xl tw-bg-amber-50 tw-text-amber-600 tw-flex tw-items-center tw-justify-center"><i class="fas fa-pencil-alt"></i></span>
                        <div>
                            <h5 class="tw-m-0 tw-text-base tw-font-semibold tw-text-slate-900" id="modalEditarCategoriaLabel">Editar categoría</h5>
                            <p class="tw-m-0 tw-mt-0.5 tw-text-[.8rem] tw-text-slate-500">Modifica únicamente el nombre de la categoría.</p>
                        </div>
                    </div>
                    <button type="button" class="category-modal-close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>

                <form id="formEditarCategoria" autocomplete="off">
                    <div class="modal-body">
                        <input type="hidden" id="edit_idcategoria" name="idcategoria">

                        <div class="category-edit-field">
                            <span class="category-edit-field__icon" aria-hidden="true">
                                <i class="fas fa-tag"></i>
                            </span>
                            <input
                                class="category-edit-field__input"
                                type="text"
                                id="edit_nombre"
                                name="nombre"
                                maxlength="50"
                                placeholder=" "
                                required
                                autocomplete="off">
                            <label for="edit_nombre" class="category-edit-field__label">
                                Nombre de la categoría <span class="category-edit-field__required">*</span>
                            </label>
                        </div>
                        <div class="category-edit-field__hint">
                            <i class="fas fa-info-circle"></i>
                            <span>Este cambio solo actualizará el nombre de la categoría.</span>
                        </div>
                    </div>
                    <div class="modal-footer tw-flex tw-items-center tw-justify-end tw-gap-2">
                        <button type="button" data-dismiss="modal" class="tw-inline-flex tw-items-center tw-justify-center tw-min-h-[42px] tw-px-4 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white hover:tw-bg-slate-50 tw-text-slate-600 tw-text-sm tw-transition-colors">Cancelar</button>
                        <button type="submit" id="btnGuardarEdicion" class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[42px] tw-px-4 tw-rounded-xl tw-border-0 tw-bg-tique-500 hover:tw-bg-tique-600 disabled:tw-opacity-60 disabled:tw-cursor-not-allowed tw-text-white tw-text-sm tw-transition-colors">
                            <i class="fas fa-save tw-text-xs"></i> Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- FIN MODAL EDITAR CATEGORÍA -->

    <!-- MODAL SUBCATEGORÍAS -->
    <div class="modal fade" id="modalSubcategorias" tabindex="-1" role="dialog" aria-labelledby="modalSubcategoriasLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="tw-flex tw-items-center tw-gap-3">
                        <span class="tw-w-10 tw-h-10 tw-rounded-xl tw-bg-tique-50 tw-text-tique-700 tw-flex tw-items-center tw-justify-center"><i class="fas fa-sitemap"></i></span>
                        <div>
                            <h5 class="tw-m-0 tw-text-base tw-font-semibold tw-text-slate-900" id="modalSubcategoriasLabel">Subcategorías</h5>
                            <p class="tw-m-0 tw-mt-0.5 tw-text-[.8rem] tw-text-slate-500">Categoría: <span id="categoriaNombre" class="tw-font-medium tw-text-slate-700"></span></p>
                        </div>
                    </div>
                    <button type="button" class="category-modal-close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="sub_idcategoria">

                    <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/60 tw-p-3.5 sm:tw-p-4 tw-mb-4">
                        <label for="sub_nombre" class="tw-block tw-mb-1.5 tw-text-[.78rem] tw-font-medium tw-text-slate-600">Nueva subcategoría</label>
                        <div class="tw-flex tw-flex-col sm:tw-flex-row tw-gap-2">
                            <input type="text" id="sub_nombre" class="category-input tw-flex-1" maxlength="80" placeholder="Ej. Collares" autocomplete="off">
                            <button type="button" onclick="guardarSubcategoria()" class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[46px] tw-px-4 tw-rounded-xl tw-border-0 tw-bg-tique-500 hover:tw-bg-tique-600 tw-text-white tw-text-sm tw-transition-colors">
                                <i class="fas fa-plus tw-text-xs"></i> Agregar
                            </button>
                        </div>
                    </div>

                    <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-overflow-hidden">
                        <div class="table-responsive tw-m-0">
                            <table class="category-sub-table">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                        <th class="tw-text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaSubcategorias"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- FIN MODAL SUBCATEGORÍAS -->

<?php
} else {
    require 'access.php';
}

require 'footer.php';
?>
<script src="Views/modules/scripts/category.js?v=<?php echo @filemtime(__DIR__ . '/scripts/category.js'); ?>"></script>
<?php
ob_end_flush();
?>
