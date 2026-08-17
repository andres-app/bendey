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

if ((int)($_SESSION['compras'] ?? 0) === 1) {
?>
    <!-- Tailwind aislado para Proveedores. Preflight desactivado para no interferir con Bootstrap/Stisla. -->
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
        .supplier-tw-view {
            --supplier-brand: #00a46a;
            --supplier-brand-dark: #00754d;
            --supplier-text: #0f172a;
            --supplier-muted: #64748b;
            --supplier-border: #e2e8f0;
        }

        .supplier-tw-view button,
        .supplier-tw-view input,
        .supplier-tw-view select {
            font-weight: 400;
        }

        .supplier-tw-view button:focus,
        .supplier-tw-view button:active,
        .supplier-tw-view button:focus-visible,
        .supplier-tw-view .btn:focus,
        .supplier-tw-view .btn:active,
        .supplier-tw-view .btn:focus-visible {
            outline: none !important;
            box-shadow: none !important;
        }

        .supplier-field {
            width: 100%;
            min-height: 46px;
            border: 1px solid #dbe4df !important;
            border-radius: 13px !important;
            background: #fff !important;
            color: #334155 !important;
            font-size: .875rem !important;
            font-weight: 400 !important;
            padding: 0 14px !important;
            transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
        }

        .supplier-field-icon {
            padding-left: 44px !important;
        }

        .supplier-field::placeholder {
            color: #94a3b8;
            opacity: 1;
        }

        .supplier-field:focus {
            border-color: #00a46a !important;
            box-shadow: 0 0 0 3px rgba(0, 164, 106, .10) !important;
        }

        select.supplier-field {
            padding-right: 36px !important;
        }

        .supplier-label {
            display: block;
            margin-bottom: 7px;
            color: #475569;
            font-size: .78rem;
            font-weight: 600;
        }

        .supplier-required {
            color: #e11d48;
        }

        .supplier-table-shell {
            overflow: hidden;
            border: 1px solid #e5ebe7;
            border-radius: 16px;
            background: #fff;
        }

        .supplier-table-scroll {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        #tbllistado {
            margin: 0 !important;
            width: 100% !important;
        }

        #tbllistado thead th {
            padding: 13px 12px !important;
            border-top: 0 !important;
            border-bottom: 1px solid #e2e8f0 !important;
            color: #64748b !important;
            background: #f8fafc !important;
            font-size: .69rem !important;
            font-weight: 700 !important;
            letter-spacing: .035em;
            text-transform: uppercase;
            vertical-align: middle;
            white-space: nowrap;
        }

        #tbllistado tbody td {
            padding: 12px !important;
            border-top: 1px solid #f1f5f9 !important;
            color: #334155;
            font-size: .82rem;
            vertical-align: middle;
        }

        #tbllistado tbody tr {
            transition: background-color .15s ease;
        }

        #tbllistado tbody tr:hover {
            background: #f8fcfa !important;
        }

        .supplier-table-action {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 5px;
            border: 1px solid transparent;
            border-radius: 10px;
            transition: transform .15s ease, background-color .15s ease, border-color .15s ease;
        }

        .supplier-table-action:hover {
            transform: translateY(-1px);
        }

        .supplier-table-action-edit {
            color: #00754d;
            border-color: #ccefe0;
            background: #ecfdf6;
        }

        .supplier-table-action-edit:hover {
            color: #00603f;
            border-color: #adebd2;
            background: #d7f7e9;
        }

        .supplier-table-action-delete {
            color: #be123c;
            border-color: #ffe4e6;
            background: #fff1f2;
        }

        .supplier-table-action-delete:hover {
            color: #9f1239;
            border-color: #fecdd3;
            background: #ffe4e6;
        }

        #tbllistado_wrapper .dt-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        #tbllistado_wrapper .dt-button,
        #tbllistado_wrapper .buttons-excel,
        #tbllistado_wrapper .buttons-pdf {
            min-height: 36px !important;
            margin: 0 !important;
            padding: 7px 11px !important;
            border: 1px solid #dbe4df !important;
            border-radius: 10px !important;
            color: #475569 !important;
            background: #fff !important;
            box-shadow: none !important;
            font-size: .76rem !important;
            font-weight: 500 !important;
        }

        #tbllistado_wrapper .dt-button:hover {
            color: #00754d !important;
            border-color: #adebd2 !important;
            background: #ecfdf6 !important;
        }

        #tbllistado_wrapper .dataTables_filter {
            margin-bottom: 12px;
        }

        #tbllistado_wrapper .dataTables_filter label {
            color: #64748b;
            font-size: .78rem;
            font-weight: 500;
        }

        #tbllistado_wrapper .dataTables_filter input {
            min-height: 38px;
            margin-left: 8px;
            border: 1px solid #dbe4df;
            border-radius: 10px;
            outline: none;
            padding: 6px 11px;
            color: #334155;
            background: #fff;
        }

        #tbllistado_wrapper .dataTables_filter input:focus {
            border-color: #00a46a;
            box-shadow: 0 0 0 3px rgba(0, 164, 106, .10);
        }

        #tbllistado_wrapper .dataTables_info {
            padding-top: 14px;
            color: #64748b;
            font-size: .76rem;
        }

        #tbllistado_wrapper .dataTables_paginate {
            padding-top: 10px;
        }

        #tbllistado_wrapper .pagination .page-link {
            min-width: 34px;
            border-color: #e2e8f0;
            color: #475569;
            box-shadow: none !important;
        }

        #tbllistado_wrapper .pagination .page-item.active .page-link {
            border-color: #00a46a;
            background: #00a46a;
            color: #fff;
        }

        .supplier-dt-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
        }

        .supplier-dt-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        #tbllistado_wrapper .supplier-dt-actions .dt-buttons {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0;
        }

        #tbllistado_wrapper .supplier-dt-search {
            margin-left: auto;
            min-width: 320px;
            max-width: 430px;
            width: min(38vw, 430px);
        }

        #tbllistado_wrapper .supplier-dt-search .dataTables_filter {
            width: 100%;
            margin: 0;
        }

        #tbllistado_wrapper .supplier-dt-search .dataTables_filter label {
            position: relative;
            display: block;
            width: 100%;
            margin: 0;
            font-size: 0;
        }

        #tbllistado_wrapper .supplier-dt-search .dataTables_filter label::before {
            content: '\f002';
            position: absolute;
            top: 50%;
            left: 14px;
            z-index: 2;
            color: #008d5b;
            font-family: 'Font Awesome 5 Free';
            font-size: .82rem;
            font-weight: 900;
            transform: translateY(-50%);
            pointer-events: none;
        }

        #tbllistado_wrapper .supplier-dt-search .dataTables_filter input {
            width: 100% !important;
            min-height: 44px;
            height: 44px;
            margin: 0;
            padding: 8px 14px 8px 40px;
            border: 1px solid #dbe4df;
            border-radius: 12px;
            color: #334155;
            background: #fff;
            font-size: .84rem;
            font-weight: 400;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        #tbllistado_wrapper .supplier-dt-search .dataTables_filter input::placeholder {
            color: #94a3b8;
            opacity: 1;
        }

        #tbllistado_wrapper .supplier-dt-search .dataTables_filter input:focus {
            border-color: #00a46a;
            box-shadow: 0 0 0 3px rgba(0, 164, 106, .10);
        }

        .supplier-dt-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .supplier-document-cell {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
            min-width: 0;
        }

        .supplier-document-type {
            display: inline-flex;
            align-items: center;
            min-height: 25px;
            padding: 3px 8px;
            border: 1px solid #ccefe0;
            border-radius: 8px;
            color: #00754d;
            background: #ecfdf6;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .025em;
        }

        .supplier-document-separator {
            color: #cbd5e1;
            font-size: .75rem;
        }

        .supplier-document-number {
            color: #334155;
            font-size: .82rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .supplier-address-cell {
            display: inline-flex;
            max-width: 330px;
            align-items: flex-start;
            gap: 7px;
            color: #475569;
            line-height: 1.35;
            white-space: normal;
        }

        .supplier-address-cell i {
            margin-top: 2px;
            color: #94a3b8;
            font-size: .75rem;
        }

        .supplier-empty-value {
            color: #94a3b8;
        }

        .supplier-saving .supplier-save-label {
            opacity: .78;
        }

        @media (max-width: 767.98px) {
            .supplier-dt-toolbar,
            .supplier-dt-footer {
                align-items: stretch;
                flex-direction: column;
            }

            #tbllistado_wrapper .supplier-dt-actions,
            #tbllistado_wrapper .supplier-dt-actions .dt-buttons,
            #tbllistado_wrapper .supplier-dt-search,
            #tbllistado_wrapper .dataTables_filter,
            #tbllistado_wrapper .dataTables_filter label,
            #tbllistado_wrapper .dataTables_filter input {
                width: 100% !important;
                max-width: none;
                min-width: 0;
            }

            #tbllistado_wrapper .supplier-dt-actions .dt-button {
                flex: 1 1 0;
            }

            .supplier-address-cell {
                min-width: 220px;
            }
        }
    </style>

    <div class="main-content supplier-tw-view">
        <section class="section">
            <div class="section-body">
                <div class="tw-mx-auto tw-max-w-[1500px] tw-space-y-5">
                    <div class="tw-overflow-hidden tw-rounded-[22px] tw-border tw-border-slate-200/80 tw-bg-white tw-shadow-tique-card">
                        <div class="tw-flex tw-flex-col tw-gap-4 tw-border-b tw-border-slate-100 tw-bg-gradient-to-r tw-from-white tw-via-white tw-to-tique-50/70 tw-p-5 md:tw-flex-row md:tw-items-center md:tw-justify-between md:tw-px-6">
                            <div class="tw-flex tw-items-start tw-gap-3">
                                <div class="tw-flex tw-h-11 tw-w-11 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-tique-50 tw-text-tique-700">
                                    <i class="fas fa-truck-loading"></i>
                                </div>
                                <div>
                                    <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                        <h4 class="tw-m-0 tw-text-[1.05rem] tw-font-semibold tw-text-slate-900">Proveedores</h4>
                                        <span class="tw-rounded-full tw-border tw-border-tique-100 tw-bg-tique-50 tw-px-2.5 tw-py-1 tw-text-[11px] tw-font-medium tw-text-tique-700">Directorio comercial</span>
                                    </div>
                                    <p class="tw-mb-0 tw-mt-1 tw-text-[13px] tw-leading-5 tw-text-slate-500">
                                        Administra proveedores, documentos fiscales y datos de contacto desde una vista más rápida y ordenada.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                onclick="mostrarform(true)"
                                id="btnagregar"
                                class="tw-inline-flex tw-min-h-[42px] tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border-0 tw-bg-tique-500 tw-px-4 tw-py-2.5 tw-text-[13px] tw-font-medium tw-text-white tw-shadow-[0_8px_20px_rgba(0,164,106,.18)] tw-transition hover:tw-bg-tique-600 hover:tw-shadow-[0_10px_24px_rgba(0,164,106,.24)] focus:tw-outline-none">
                                <i class="fas fa-plus"></i>
                                Nuevo proveedor
                            </button>
                        </div>

                        <div class="tw-p-4 md:tw-p-6">
                            <div id="listadoregistros">
                                <div class="tw-mb-4 tw-grid tw-gap-3 md:tw-grid-cols-3">
                                    <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-4">
                                        <div class="tw-flex tw-items-center tw-gap-3">
                                            <span class="tw-flex tw-h-9 tw-w-9 tw-items-center tw-justify-center tw-rounded-xl tw-bg-white tw-text-tique-700 tw-shadow-sm">
                                                <i class="fas fa-address-book"></i>
                                            </span>
                                            <div>
                                                <div class="tw-text-xs tw-font-medium tw-text-slate-500">Información centralizada</div>
                                                <div class="tw-mt-0.5 tw-text-[13px] tw-text-slate-700">Contacto y documento en un solo registro</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-4">
                                        <div class="tw-flex tw-items-center tw-gap-3">
                                            <span class="tw-flex tw-h-9 tw-w-9 tw-items-center tw-justify-center tw-rounded-xl tw-bg-white tw-text-tique-700 tw-shadow-sm">
                                                <i class="fas fa-search"></i>
                                            </span>
                                            <div>
                                                <div class="tw-text-xs tw-font-medium tw-text-slate-500">Búsqueda inmediata</div>
                                                <div class="tw-mt-0.5 tw-text-[13px] tw-text-slate-700">Filtra por nombre, documento o contacto</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-4">
                                        <div class="tw-flex tw-items-center tw-gap-3">
                                            <span class="tw-flex tw-h-9 tw-w-9 tw-items-center tw-justify-center tw-rounded-xl tw-bg-white tw-text-tique-700 tw-shadow-sm">
                                                <i class="fas fa-file-export"></i>
                                            </span>
                                            <div>
                                                <div class="tw-text-xs tw-font-medium tw-text-slate-500">Reportes rápidos</div>
                                                <div class="tw-mt-0.5 tw-text-[13px] tw-text-slate-700">Exporta el directorio a Excel o PDF</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="supplier-table-shell">
                                    <div class="supplier-table-scroll tw-p-3 md:tw-p-4">
                                        <table id="tbllistado" class="table table-hover text-nowrap" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Acciones</th>
                                                    <th>Proveedor</th>
                                                    <th>Documento</th>
                                                    <th>Dirección</th>
                                                    <th>Teléfono</th>
                                                    <th>Email</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div id="formularioregistros" style="display:none;">
                                <form action="" name="formulario" id="formulario" method="POST" autocomplete="off" novalidate>
                                    <input type="hidden" name="idpersona" id="idpersona">
                                    <input type="hidden" name="tipo_persona" id="tipo_persona" value="Proveedor">

                                    <div class="tw-mb-5 tw-flex tw-flex-col tw-gap-3 tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-4 md:tw-flex-row md:tw-items-center md:tw-justify-between">
                                        <div>
                                            <div class="tw-flex tw-items-center tw-gap-2">
                                                <span class="tw-flex tw-h-9 tw-w-9 tw-items-center tw-justify-center tw-rounded-xl tw-bg-tique-50 tw-text-tique-700">
                                                    <i class="fas fa-building"></i>
                                                </span>
                                                <div>
                                                    <h5 id="supplierFormTitle" class="tw-m-0 tw-text-[15px] tw-font-semibold tw-text-slate-900">Nuevo proveedor</h5>
                                                    <p id="supplierFormSubtitle" class="tw-mb-0 tw-mt-0.5 tw-text-[12px] tw-text-slate-500">Completa la información comercial y de contacto.</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tw-flex tw-items-center tw-gap-2 tw-text-[11px] tw-text-slate-500">
                                            <i class="fas fa-asterisk tw-text-rose-500"></i>
                                            Los campos marcados son obligatorios
                                        </div>
                                    </div>

                                    <div class="tw-grid tw-grid-cols-1 tw-gap-x-4 tw-gap-y-5 md:tw-grid-cols-2">
                                        <div class="md:tw-col-span-2">
                                            <label for="nombre" class="supplier-label">Nombre o razón social <span class="supplier-required">*</span></label>
                                            <div class="tw-relative">
                                                <span class="tw-pointer-events-none tw-absolute tw-inset-y-0 tw-left-0 tw-flex tw-w-11 tw-items-center tw-justify-center tw-text-slate-400">
                                                    <i class="fas fa-building"></i>
                                                </span>
                                                <input class="form-control supplier-field supplier-field-icon" type="text" name="nombre" id="nombre" maxlength="100" placeholder="Ej.: Distribuidora Comercial S.A.C." required autocomplete="organization">
                                            </div>
                                        </div>

                                        <div>
                                            <label for="tipo_documento" class="supplier-label">Tipo de documento <span class="supplier-required">*</span></label>
                                            <select class="form-control supplier-field" name="tipo_documento" id="tipo_documento" required>
                                                <option value="DNI">DNI</option>
                                                <option value="RUC" selected>RUC</option>
                                                <option value="CEDULA">Cédula</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label for="num_documento" class="supplier-label">Número de documento</label>
                                            <input class="form-control supplier-field" type="text" name="num_documento" id="num_documento" maxlength="20" placeholder="Ingresa el número" inputmode="numeric" autocomplete="off">
                                            <small id="supplierDocumentHelp" class="tw-mt-1.5 tw-block tw-text-[11px] tw-text-slate-400">RUC: 11 dígitos.</small>
                                        </div>

                                        <div class="md:tw-col-span-2">
                                            <label for="direccion" class="supplier-label">Dirección</label>
                                            <input class="form-control supplier-field" type="text" name="direccion" id="direccion" maxlength="70" placeholder="Dirección fiscal o comercial" autocomplete="street-address">
                                        </div>

                                        <div>
                                            <label for="telefono" class="supplier-label">Teléfono</label>
                                            <input class="form-control supplier-field" type="tel" name="telefono" id="telefono" maxlength="20" placeholder="Ej.: 987 654 321" inputmode="tel" autocomplete="tel">
                                        </div>

                                        <div>
                                            <label for="email" class="supplier-label">Correo electrónico</label>
                                            <input class="form-control supplier-field" type="email" name="email" id="email" maxlength="50" placeholder="compras@proveedor.com" autocomplete="email">
                                        </div>
                                    </div>

                                    <div class="tw-mt-6 tw-flex tw-flex-col-reverse tw-gap-2 tw-border-t tw-border-slate-100 tw-pt-5 sm:tw-flex-row sm:tw-justify-end">
                                        <button
                                            class="tw-inline-flex tw-min-h-[42px] tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-px-4 tw-py-2.5 tw-text-[13px] tw-font-medium tw-text-slate-600 tw-transition hover:tw-border-slate-300 hover:tw-bg-slate-50 focus:tw-outline-none"
                                            onclick="cancelarform()"
                                            type="button">
                                            <i class="fas fa-arrow-left"></i>
                                            Cancelar
                                        </button>

                                        <button
                                            class="tw-inline-flex tw-min-h-[42px] tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border-0 tw-bg-tique-500 tw-px-5 tw-py-2.5 tw-text-[13px] tw-font-medium tw-text-white tw-shadow-[0_8px_20px_rgba(0,164,106,.18)] tw-transition hover:tw-bg-tique-600 disabled:tw-cursor-not-allowed disabled:tw-opacity-60 focus:tw-outline-none"
                                            type="submit"
                                            id="btnGuardar">
                                            <i class="fas fa-save"></i>
                                            <span class="supplier-save-label">Guardar proveedor</span>
                                        </button>
                                    </div>
                                </form>
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
<script src="Views/modules/scripts/supplier.js"></script>
<?php
ob_end_flush();
?>
