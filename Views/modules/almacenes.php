<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['nombre'])) { header('Location: login'); exit; }
require 'header.php';
require 'sidebar.php';

if ((int)($_SESSION['almacen'] ?? 0) === 1) {
?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            prefix: 'tw-', corePlugins: { preflight: false },
            theme: { extend: {
                colors: { tique: { 50:'#ecfdf6',100:'#d7f7e9',200:'#adebd2',300:'#72d9b3',400:'#31c18e',500:'#00a46a',600:'#008d5b',700:'#00754d',800:'#00603f',900:'#004f35' } },
                boxShadow: { 'tique-soft':'0 18px 45px rgba(15,23,42,.08)' }
            }}
        };
    </script>
    <style>
        .warehouse-tw-view button, #modalEditarAlmacen button { font-weight:500 !important; }
        .warehouse-tw-view button:focus,.warehouse-tw-view button:active,.warehouse-tw-view button:focus-visible,
        #modalEditarAlmacen button:focus,#modalEditarAlmacen button:active,#modalEditarAlmacen button:focus-visible { outline:none !important; box-shadow:none !important; }
        .warehouse-input {
            width:100%; min-height:46px; border:1px solid #e2e8f0 !important; border-radius:13px !important; background:#fff !important;
            color:#334155 !important; font-size:.875rem !important; font-weight:400 !important; padding:0 14px !important; box-shadow:none !important;
            transition:border-color .15s ease, box-shadow .15s ease;
        }
        .warehouse-input::placeholder { color:#94a3b8; opacity:1; }
        .warehouse-input:hover { border-color:#cbd5e1 !important; }
        .warehouse-input:focus { border-color:#00a46a !important; box-shadow:0 0 0 4px rgba(0,164,106,.10) !important; outline:none !important; }
        .warehouse-tw-view #warehouseSearch { padding-left:42px !important; padding-right:14px !important; }

        .warehouse-table-shell .dt-buttons,.warehouse-table-shell .dataTables_filter,.warehouse-table-shell .dataTables_length { display:none !important; }
        .warehouse-table-shell .dataTables_info { color:#64748b; font-size:.82rem; padding-top:18px !important; }
        .warehouse-table-shell .dataTables_paginate { padding-top:12px !important; }
        .warehouse-table-shell .pagination { gap:5px; margin:0; }
        .warehouse-table-shell .page-item .page-link {
            min-width:36px;height:36px;border:1px solid #e2e8f0;border-radius:10px !important;display:inline-flex;align-items:center;justify-content:center;
            color:#475569;background:#fff;font-size:.82rem;font-weight:500;box-shadow:none !important;
        }
        .warehouse-table-shell .page-item.active .page-link { border-color:#00a46a !important;background:#00a46a !important;color:#fff !important; }
        .warehouse-table-shell .page-item.disabled .page-link { color:#cbd5e1;background:#f8fafc; }
        .warehouse-table-shell table.dataTable { margin-top:0 !important;margin-bottom:0 !important;border-collapse:separate !important;border-spacing:0 !important; }
        .warehouse-table-shell #tbllistado thead th {
            border-top:0 !important;border-bottom:1px solid #e2e8f0 !important;background:#f8fafc;color:#64748b;font-size:.72rem;font-weight:600;
            letter-spacing:.045em;text-transform:uppercase;padding:13px 15px !important;white-space:nowrap;
        }
        .warehouse-table-shell #tbllistado tbody td {
            border-top:0 !important;border-bottom:1px solid #f1f5f9 !important;color:#334155;font-size:.86rem;padding:13px 15px !important;
            vertical-align:middle !important;background:#fff;
        }
        .warehouse-table-shell #tbllistado tbody tr:last-child td { border-bottom:0 !important; }
        .warehouse-table-shell #tbllistado tbody tr:hover td { background:#fbfefc !important; }
        .warehouse-name-cell { display:flex;align-items:center;gap:10px;min-width:170px; }
        .warehouse-name-icon { width:36px;height:36px;flex:0 0 36px;display:inline-flex;align-items:center;justify-content:center;border-radius:11px;color:#00754d;background:#ecfdf6; }
        .warehouse-name-text { color:#1e293b;font-weight:500;line-height:1.2; }
        .warehouse-secondary { color:#64748b;font-size:.82rem;line-height:1.35; }
        .warehouse-secondary.is-empty { color:#94a3b8;font-style:italic; }
        .warehouse-status { min-height:28px;padding:0 10px;border-radius:999px;display:inline-flex;align-items:center;gap:7px;font-size:.76rem;font-weight:500;white-space:nowrap; }
        .warehouse-status::before { content:'';width:7px;height:7px;border-radius:999px;background:currentColor; }
        .warehouse-status--active { color:#047857;background:#ecfdf5; }
        .warehouse-status--inactive { color:#b91c1c;background:#fef2f2; }
        .warehouse-actions { display:inline-flex;align-items:center;gap:7px; }
        .warehouse-icon-btn { width:36px;height:36px;padding:0;border:1px solid #e2e8f0;border-radius:11px;display:inline-flex;align-items:center;justify-content:center;background:#fff;color:#64748b;cursor:pointer;transition:.15s ease; }
        .warehouse-icon-btn:hover { transform:translateY(-1px); }
        .warehouse-icon-btn--edit:hover { border-color:#fde68a;background:#fffbeb;color:#b45309; }
        .warehouse-icon-btn--danger:hover { border-color:#fecaca;background:#fef2f2;color:#dc2626; }
        .warehouse-icon-btn--activate:hover { border-color:#adebd2;background:#ecfdf6;color:#00754d; }

        #modalEditarAlmacen .modal-dialog { max-width:590px; }
        #modalEditarAlmacen .modal-content { border:0;border-radius:20px;overflow:hidden;box-shadow:0 24px 70px rgba(15,23,42,.20); }
        #modalEditarAlmacen .modal-header { border-bottom:1px solid #f1f5f9;padding:18px 20px;align-items:center; }
        #modalEditarAlmacen .modal-body { padding:20px; }
        #modalEditarAlmacen .modal-footer { padding:0 20px 20px;border-top:0; }
        .warehouse-modal-close { width:36px;height:36px;border:0;border-radius:11px;display:inline-flex;align-items:center;justify-content:center;background:#f8fafc;color:#64748b;font-size:18px;line-height:1; }
        .warehouse-modal-close:hover { background:#f1f5f9;color:#334155; }
        .warehouse-floating-field { position:relative;width:100%; }
        .warehouse-floating-field__icon { position:absolute;left:16px;top:50%;transform:translateY(-50%);z-index:2;width:30px;height:30px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:#ecfdf6;color:#008d5b;pointer-events:none;transition:.18s ease; }
        .warehouse-floating-field__input { width:100%;height:60px;border:1px solid #dbe4ea !important;border-radius:16px !important;background:#fff !important;color:#0f172a !important;font-size:.94rem !important;font-weight:400 !important;padding:22px 46px 8px 58px !important;box-shadow:0 2px 8px rgba(15,23,42,.025) !important;outline:none !important;transition:.18s ease; }
        .warehouse-floating-field__input:hover { border-color:#b9c7d2 !important; }
        .warehouse-floating-field__input:focus { border-color:#00a46a !important;box-shadow:0 0 0 4px rgba(0,164,106,.10) !important; }
        .warehouse-floating-field__label { position:absolute;left:58px;top:50%;transform:translateY(-50%);z-index:2;margin:0;color:#94a3b8;font-size:.86rem;font-weight:400;line-height:1;pointer-events:none;transition:.16s ease; }
        .warehouse-floating-field__input:focus + .warehouse-floating-field__label,.warehouse-floating-field__input:not(:placeholder-shown) + .warehouse-floating-field__label { top:12px;transform:none;font-size:.66rem;font-weight:500;color:#64748b; }
        .warehouse-floating-field:focus-within .warehouse-floating-field__label { color:#008d5b; }
        .warehouse-floating-field:focus-within .warehouse-floating-field__icon { background:#d7f7e9;color:#00754d;transform:translateY(-50%) scale(1.04); }
        @media(max-width:767.98px){
            .warehouse-table-shell .dataTables_info,.warehouse-table-shell .dataTables_paginate{float:none !important;width:100%;text-align:center !important;}
            .warehouse-table-shell .pagination{justify-content:center;flex-wrap:wrap;}
            #modalEditarAlmacen .modal-dialog{margin:12px;}
            #modalEditarAlmacen .modal-body,#modalEditarAlmacen .modal-header,#modalEditarAlmacen .modal-footer{padding-left:15px;padding-right:15px;}
        }
    </style>

    <div class="main-content warehouse-tw-view">
        <section class="section">
            <div class="section-body tw-max-w-[1500px] tw-mx-auto">
                <div class="tw-rounded-[22px] tw-border tw-border-slate-200 tw-bg-white tw-shadow-tique-soft tw-overflow-hidden">
                    <div class="tw-p-4 sm:tw-p-5 lg:tw-p-6 tw-border-b tw-border-slate-100">
                        <div class="tw-flex tw-flex-col lg:tw-flex-row lg:tw-items-center lg:tw-justify-between tw-gap-4">
                            <div class="tw-flex tw-items-start tw-gap-3">
                                <div class="tw-w-11 tw-h-11 tw-shrink-0 tw-rounded-2xl tw-bg-tique-50 tw-text-tique-700 tw-flex tw-items-center tw-justify-center"><i class="fas fa-warehouse"></i></div>
                                <div>
                                    <div class="tw-flex tw-items-center tw-gap-2 tw-flex-wrap">
                                        <h1 class="tw-m-0 tw-text-[1.25rem] sm:tw-text-[1.4rem] tw-font-semibold tw-text-slate-900">Almacenes</h1>
                                        <span class="tw-inline-flex tw-items-center tw-rounded-full tw-bg-slate-100 tw-px-2.5 tw-py-1 tw-text-[.72rem] tw-font-medium tw-text-slate-500">Inventario</span>
                                    </div>
                                    <p class="tw-m-0 tw-mt-1 tw-text-[.84rem] tw-leading-5 tw-text-slate-500">Administra tus puntos de almacenamiento, ubicaciones y disponibilidad.</p>
                                </div>
                            </div>
                            <button type="button" id="btnagregar" onclick="mostrarform(true)" class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[42px] tw-px-4 tw-rounded-xl tw-border-0 tw-bg-tique-500 hover:tw-bg-tique-600 tw-text-white tw-text-sm tw-font-medium tw-shadow-[0_8px_20px_rgba(0,164,106,.18)] tw-transition-all hover:-tw-translate-y-0.5"><i class="fas fa-plus tw-text-xs"></i> Nuevo almacén</button>
                        </div>
                    </div>

                    <div id="listadoregistros" class="tw-p-4 sm:tw-p-5 lg:tw-p-6">
                        <div class="tw-grid tw-grid-cols-1 sm:tw-grid-cols-3 tw-gap-3 tw-mb-5">
                            <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/60 tw-p-4"><div class="tw-flex tw-items-center tw-justify-between tw-gap-3"><div><p class="tw-m-0 tw-text-[.75rem] tw-font-medium tw-text-slate-500">Total almacenes</p><p id="warehouseStatTotal" class="tw-m-0 tw-mt-1 tw-text-2xl tw-font-semibold tw-text-slate-900">0</p></div><span class="tw-w-10 tw-h-10 tw-rounded-xl tw-bg-white tw-border tw-border-slate-200 tw-flex tw-items-center tw-justify-center tw-text-slate-500"><i class="fas fa-layer-group"></i></span></div></div>
                            <div class="tw-rounded-2xl tw-border tw-border-emerald-100 tw-bg-emerald-50/60 tw-p-4"><div class="tw-flex tw-items-center tw-justify-between tw-gap-3"><div><p class="tw-m-0 tw-text-[.75rem] tw-font-medium tw-text-emerald-700">Activos</p><p id="warehouseStatActive" class="tw-m-0 tw-mt-1 tw-text-2xl tw-font-semibold tw-text-emerald-800">0</p></div><span class="tw-w-10 tw-h-10 tw-rounded-xl tw-bg-white/80 tw-border tw-border-emerald-100 tw-flex tw-items-center tw-justify-center tw-text-emerald-600"><i class="fas fa-check"></i></span></div></div>
                            <div class="tw-rounded-2xl tw-border tw-border-rose-100 tw-bg-rose-50/60 tw-p-4"><div class="tw-flex tw-items-center tw-justify-between tw-gap-3"><div><p class="tw-m-0 tw-text-[.75rem] tw-font-medium tw-text-rose-700">Inactivos</p><p id="warehouseStatInactive" class="tw-m-0 tw-mt-1 tw-text-2xl tw-font-semibold tw-text-rose-800">0</p></div><span class="tw-w-10 tw-h-10 tw-rounded-xl tw-bg-white/80 tw-border tw-border-rose-100 tw-flex tw-items-center tw-justify-center tw-text-rose-500"><i class="fas fa-pause"></i></span></div></div>
                        </div>

                        <div class="tw-flex tw-flex-col md:tw-flex-row md:tw-items-center md:tw-justify-between tw-gap-3 tw-mb-4">
                            <div class="tw-relative tw-w-full md:tw-max-w-md">
                                <span class="tw-absolute tw-left-3.5 tw-top-1/2 -tw-translate-y-1/2 tw-text-slate-400 tw-pointer-events-none"><i class="fas fa-search tw-text-xs"></i></span>
                                <input type="search" id="warehouseSearch" class="warehouse-input" placeholder="Buscar almacén..." autocomplete="off">
                            </div>
                            <div class="tw-flex tw-items-center tw-gap-2 tw-flex-wrap">
                                <button type="button" id="btnWarehouseExportExcel" class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[40px] tw-px-3.5 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white hover:tw-bg-slate-50 tw-text-slate-600 tw-text-[.82rem] tw-transition-colors"><i class="far fa-file-excel tw-text-tique-600"></i> Excel</button>
                                <button type="button" id="btnWarehouseExportPdf" class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[40px] tw-px-3.5 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white hover:tw-bg-slate-50 tw-text-slate-600 tw-text-[.82rem] tw-transition-colors"><i class="far fa-file-pdf tw-text-rose-500"></i> PDF</button>
                            </div>
                        </div>

                        <div class="warehouse-table-shell tw-rounded-2xl tw-border tw-border-slate-200 tw-overflow-hidden">
                            <div class="table-responsive tw-m-0">
                                <table id="tbllistado" class="table text-nowrap tw-m-0" style="width:100%;">
                                    <thead><tr><th>ID</th><th>Almacén</th><th>Ubicación</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="formularioregistros" class="tw-p-4 sm:tw-p-5 lg:tw-p-6" style="display:none;">
                        <div class="tw-max-w-4xl tw-mx-auto tw-rounded-[20px] tw-border tw-border-slate-200 tw-bg-slate-50/40 tw-p-4 sm:tw-p-5">
                            <div class="tw-flex tw-items-start tw-justify-between tw-gap-4 tw-mb-5">
                                <div class="tw-flex tw-items-center tw-gap-2"><span class="tw-w-9 tw-h-9 tw-rounded-xl tw-bg-tique-50 tw-text-tique-700 tw-flex tw-items-center tw-justify-center"><i class="fas fa-warehouse tw-text-sm"></i></span><div><h2 class="tw-m-0 tw-text-base tw-font-semibold tw-text-slate-900">Nuevo almacén</h2><p class="tw-m-0 tw-mt-0.5 tw-text-[.8rem] tw-text-slate-500">Registra una nueva ubicación para organizar tu inventario.</p></div></div>
                                <button type="button" onclick="cancelarform()" aria-label="Cerrar formulario" class="tw-w-9 tw-h-9 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white hover:tw-bg-slate-50 tw-text-slate-500 tw-flex tw-items-center tw-justify-center"><i class="fas fa-times tw-text-xs"></i></button>
                            </div>
                            <form id="formulario" method="POST" autocomplete="off">
                                <input type="hidden" name="idalmacen" id="idalmacen">
                                <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                                    <div><label for="nombre" class="tw-block tw-mb-1.5 tw-text-[.78rem] tw-font-medium tw-text-slate-600">Nombre del almacén <span class="tw-text-rose-500">*</span></label><input type="text" class="warehouse-input" name="nombre" id="nombre" maxlength="100" placeholder="Ej. Almacén principal" required></div>
                                    <div><label for="ubicacion" class="tw-block tw-mb-1.5 tw-text-[.78rem] tw-font-medium tw-text-slate-600">Ubicación</label><input type="text" class="warehouse-input" name="ubicacion" id="ubicacion" maxlength="200" placeholder="Ej. Av. Principal 123"></div>
                                    <div class="md:tw-col-span-2"><label for="descripcion" class="tw-block tw-mb-1.5 tw-text-[.78rem] tw-font-medium tw-text-slate-600">Descripción</label><input type="text" class="warehouse-input" name="descripcion" id="descripcion" maxlength="200" placeholder="Descripción opcional"></div>
                                </div>
                                <div class="tw-flex tw-flex-col-reverse sm:tw-flex-row sm:tw-justify-end tw-gap-2.5 tw-mt-6">
                                    <button type="button" onclick="cancelarform()" class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[42px] tw-px-4 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white hover:tw-bg-slate-50 tw-text-slate-600 tw-text-sm"><i class="fas fa-arrow-left tw-text-xs"></i> Cancelar</button>
                                    <button type="submit" id="btnGuardar" class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[42px] tw-px-4 tw-rounded-xl tw-border-0 tw-bg-tique-500 hover:tw-bg-tique-600 tw-text-white tw-text-sm tw-shadow-[0_8px_20px_rgba(0,164,106,.18)]"><i class="fas fa-save tw-text-xs"></i> Guardar almacén</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="modalEditarAlmacen" tabindex="-1" role="dialog" aria-labelledby="modalEditarAlmacenLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="tw-flex tw-items-center tw-gap-3"><span class="tw-w-10 tw-h-10 tw-rounded-xl tw-bg-amber-50 tw-text-amber-600 tw-flex tw-items-center tw-justify-center"><i class="fas fa-pencil-alt"></i></span><div><h5 class="tw-m-0 tw-text-base tw-font-semibold tw-text-slate-900" id="modalEditarAlmacenLabel">Editar almacén</h5><p class="tw-m-0 tw-mt-0.5 tw-text-[.8rem] tw-text-slate-500">Modifica únicamente los datos del almacén seleccionado.</p></div></div>
                    <button type="button" class="warehouse-modal-close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <form id="formEditarAlmacen" autocomplete="off">
                    <div class="modal-body">
                        <input type="hidden" id="edit_idalmacen" name="idalmacen">
                        <div class="tw-space-y-3">
                            <div class="warehouse-floating-field"><span class="warehouse-floating-field__icon"><i class="fas fa-warehouse"></i></span><input class="warehouse-floating-field__input" type="text" id="edit_nombre" name="nombre" maxlength="100" placeholder=" " required><label class="warehouse-floating-field__label" for="edit_nombre">Nombre del almacén <span class="tw-text-rose-500">*</span></label></div>
                            <div class="warehouse-floating-field"><span class="warehouse-floating-field__icon"><i class="fas fa-map-marker-alt"></i></span><input class="warehouse-floating-field__input" type="text" id="edit_ubicacion" name="ubicacion" maxlength="200" placeholder=" "><label class="warehouse-floating-field__label" for="edit_ubicacion">Ubicación</label></div>
                            <div class="warehouse-floating-field"><span class="warehouse-floating-field__icon"><i class="fas fa-align-left"></i></span><input class="warehouse-floating-field__input" type="text" id="edit_descripcion" name="descripcion" maxlength="200" placeholder=" "><label class="warehouse-floating-field__label" for="edit_descripcion">Descripción</label></div>
                        </div>
                    </div>
                    <div class="modal-footer tw-flex tw-gap-2 tw-justify-end">
                        <button type="button" data-dismiss="modal" class="tw-inline-flex tw-items-center tw-justify-center tw-min-h-[42px] tw-px-4 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white hover:tw-bg-slate-50 tw-text-slate-600 tw-text-sm">Cancelar</button>
                        <button type="submit" id="btnGuardarEdicionAlmacen" class="tw-inline-flex tw-items-center tw-justify-center tw-gap-2 tw-min-h-[42px] tw-px-4 tw-rounded-xl tw-border-0 tw-bg-tique-500 hover:tw-bg-tique-600 tw-text-white tw-text-sm"><i class="fas fa-save tw-text-xs"></i> Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
} else { require 'access.php'; }
require 'footer.php';
$almacenJs = __DIR__ . '/scripts/almacen.js';
$almacenJsVersion = file_exists($almacenJs) ? filemtime($almacenJs) : time();
?>
<script src="Views/modules/scripts/almacen.js?v=<?php echo $almacenJsVersion; ?>"></script>
<?php ob_end_flush(); ?>
