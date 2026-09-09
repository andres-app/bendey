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

if ((int)($_SESSION['almacen'] ?? 0) === 1) {
?>
<!-- Tailwind aislado para Etiquetas. Preflight desactivado para convivir con Bootstrap/Stisla. -->
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
                    'tique-card': '0 14px 42px rgba(15, 23, 42, .08)',
                    'tique-soft': '0 8px 24px rgba(15, 23, 42, .06)'
                }
            }
        }
    };
</script>

<style>
    .lb-filter.active {
        border-color: #00a46a !important;
        background: #00a46a !important;
        color: #fff !important;
        box-shadow: 0 6px 16px rgba(0, 164, 106, .18);
    }
    .lb-column-btn.active {
        border-color: #00a46a !important;
        background: #ecfdf6 !important;
        color: #00754d !important;
        box-shadow: inset 0 0 0 1px rgba(0, 164, 106, .08), 0 6px 16px rgba(0, 164, 106, .08);
    }
    .lb-product {
        transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .lb-product:hover {
        border-color: #cbd5e1 !important;
        background: #f8fafc !important;
    }
    .lb-product.is-selected {
        border-color: #72d9b3 !important;
        background: #ecfdf6 !important;
        box-shadow: 0 8px 20px rgba(0, 164, 106, .08);
    }
    .lb-check { accent-color: #00a46a; }
    .lb-direct-status.ok { color: #047857 !important; background: #d1fae5 !important; }
    .lb-direct-status.err { color: #b91c1c !important; background: #fee2e2 !important; }
    .lb-list-scroll { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
    .lb-list-scroll::-webkit-scrollbar { width: 8px; }
    .lb-list-scroll::-webkit-scrollbar-track { background: transparent; }
    .lb-list-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }

    /* Vista previa: composición física de la etiqueta. */
    .lb-preview-stage {
        min-height: 360px;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        overflow: auto;
        background: repeating-linear-gradient(45deg, #f8fafc, #f8fafc 12px, #f1f5f9 12px, #f1f5f9 24px);
    }
    .lb-preview-sheet { display: grid; transform-origin: top center; }
    .lb-preview-label {
        box-sizing: border-box;
        display: grid;
        grid-template-rows: 48% 31% 17%;
        row-gap: 2%;
        overflow: hidden;
        min-width: 0;
        padding: .65mm .75mm .55mm;
        color: #050505;
        background: #fff;
        border-radius: 1.8mm;
        box-shadow: 0 3px 10px rgba(15, 23, 42, .16);
        font-family: Arial, Helvetica, sans-serif;
        line-height: 1;
    }
    .lb-preview-label.with-border { outline: 1px dashed #94a3b8; outline-offset: -1px; }
    .lb-label-heading {
        min-width: 0;
        min-height: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
    }
    .lb-label-business {
        overflow: hidden;
        flex: 0 0 auto;
        margin: 0 0 .18mm;
        font-size: var(--lb-business-size, 4.2pt);
        font-weight: 700;
        line-height: 1;
        text-align: left;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .lb-label-name-price {
        position: relative;
        min-width: 0;
        min-height: 0;
        flex: 1 1 auto;
        overflow: hidden;
    }
    .lb-label-name {
        display: -webkit-box;
        overflow: hidden;
        min-width: 0;
        min-height: 0;
        margin: 0;
        padding: 0 .1mm;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        font-size: var(--lb-name-size, 6pt);
        font-weight: 900;
        line-height: 1.05;
        letter-spacing: -.018em;
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
    }
    .lb-label-price-row {
        position: absolute;
        right: 0;
        bottom: 0;
        z-index: 3;
        display: flex;
        min-width: 0;
        max-width: 62%;
        align-items: flex-end;
        justify-content: flex-end;
        overflow: hidden;
        padding: 0 0 .04mm .45mm;
        background: #fff;
        white-space: nowrap;
    }
    .lb-label-currency {
        flex: 0 0 auto;
        margin: 0 .24mm .12em 0;
        font-size: var(--lb-currency-size, 7.2pt);
        font-weight: 500;
        line-height: .9;
    }
    .lb-label-price-value {
        flex: 0 1 auto;
        max-width: 100%;
        overflow: hidden;
        font-size: var(--lb-price-size, 11.8pt);
        font-weight: 950;
        line-height: .84;
        letter-spacing: -.035em;
        text-overflow: clip;
    }
    .lb-label-barcode {
        display: flex;
        min-width: 0;
        min-height: 0;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        padding: .04mm .15mm;
    }
    .lb-label-barcode svg {
        display: block;
        width: 90%;
        height: 52%;
        max-width: 90%;
        max-height: 52%;
        overflow: hidden;
        flex: 0 1 auto;
    }
    .lb-label-sku {
        display: flex;
        min-width: 0;
        min-height: 0;
        align-items: flex-start;
        justify-content: center;
        overflow: hidden;
        margin: 0;
        padding: .05mm .2mm 0;
        font-family: Arial, Helvetica, sans-serif;
        font-size: var(--lb-sku-size, 5.3pt);
        font-weight: 800;
        line-height: .95;
        letter-spacing: .015em;
        text-align: center;
        text-overflow: ellipsis;
        white-space: nowrap;
    }


    @media (max-width: 1100px) {
        .lb-preview-sticky { position: static !important; }
    }
</style>

<div class="main-content lb-page">
    <section class="section">
        <div class="tw-mx-auto tw-max-w-[1600px] tw-space-y-5">
            <!-- Encabezado -->
            <div class="tw-overflow-hidden tw-rounded-[22px] tw-border tw-border-slate-200/80 tw-bg-white tw-shadow-tique-card">
                <div class="tw-flex tw-flex-col tw-gap-4 tw-bg-gradient-to-r tw-from-white tw-via-white tw-to-tique-50/70 tw-p-5 md:tw-flex-row md:tw-items-center md:tw-justify-between md:tw-px-6">
                    <div class="tw-flex tw-items-start tw-gap-3">
                        <div class="tw-flex tw-h-12 tw-w-12 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-tique-50 tw-text-tique-700 tw-shadow-sm">
                            <i class="fas fa-barcode tw-text-lg"></i>
                        </div>
                        <div>
                            <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                <h1 class="tw-m-0 tw-text-[1.2rem] tw-font-semibold tw-tracking-tight tw-text-slate-900">Etiquetas y códigos de barras</h1>
                                <span class="tw-inline-flex tw-items-center tw-gap-1.5 tw-rounded-full tw-border tw-border-tique-100 tw-bg-tique-50 tw-px-2.5 tw-py-1 tw-text-[11px] tw-font-semibold tw-text-tique-700">
                                    <i class="fas fa-check-circle"></i> CODE128
                                </span>
                            </div>
                            <p class="tw-mb-0 tw-mt-1 tw-max-w-3xl tw-text-[13px] tw-leading-5 tw-text-slate-500">
                                Busca por nombre, SKU de variante o SKU padre, selecciona cantidades y envía las etiquetas directamente a tu impresora.
                            </p>
                        </div>
                    </div>
                    <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                        <span class="tw-inline-flex tw-items-center tw-gap-2 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-px-3 tw-py-2 tw-text-[12px] tw-font-medium tw-text-slate-600 tw-shadow-sm">
                            <i class="fas fa-ruler-combined tw-text-tique-600"></i> Medidas en mm
                        </span>
                        <span class="tw-inline-flex tw-items-center tw-gap-2 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-px-3 tw-py-2 tw-text-[12px] tw-font-medium tw-text-slate-600 tw-shadow-sm">
                            <i class="fas fa-print tw-text-tique-600"></i> Impresión directa
                        </span>
                    </div>
                </div>
            </div>

            <div class="tw-grid tw-grid-cols-1 tw-gap-5 xl:tw-grid-cols-[minmax(0,1.2fr)_minmax(360px,.8fr)]">
                <div class="tw-space-y-5">
                    <!-- Paso 1 -->
                    <div class="tw-overflow-hidden tw-rounded-[22px] tw-border tw-border-slate-200/80 tw-bg-white tw-shadow-tique-soft">
                        <div class="tw-flex tw-flex-col tw-gap-3 tw-border-b tw-border-slate-100 tw-p-5 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between md:tw-px-6">
                            <div class="tw-flex tw-items-center tw-gap-3">
                                <span class="tw-flex tw-h-9 tw-w-9 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-xl tw-bg-slate-900 tw-text-[12px] tw-font-bold tw-text-white">1</span>
                                <div>
                                    <h5 class="tw-m-0 tw-text-[15px] tw-font-semibold tw-text-slate-900">Productos a etiquetar</h5>
                                    <p class="tw-mb-0 tw-mt-0.5 tw-text-[12px] tw-text-slate-500">Las variantes usan su SKU propio. También puedes encontrarlas escribiendo el SKU padre.</p>
                                </div>
                            </div>
                            <button type="button" class="tw-inline-flex tw-items-center tw-gap-2 tw-self-start tw-rounded-xl tw-border-0 tw-bg-transparent tw-px-2 tw-py-1.5 tw-text-[12px] tw-font-semibold tw-text-tique-700 hover:tw-bg-tique-50" id="btnLimpiarSeleccion">
                                <i class="fas fa-broom"></i><span>Limpiar selección</span>
                            </button>
                        </div>

                        <div class="tw-p-5 md:tw-p-6">
                            <!-- Buscador: icono separado del texto -->
                            <div class="tw-flex tw-min-h-[48px] tw-items-stretch tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-shadow-sm focus-within:tw-border-tique-400 focus-within:tw-ring-4 focus-within:tw-ring-tique-50">
                                <span class="tw-flex tw-w-12 tw-shrink-0 tw-items-center tw-justify-center tw-border-r tw-border-slate-100 tw-bg-slate-50 tw-text-slate-400">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="search" class="tw-min-w-0 tw-flex-1 tw-border-0 tw-bg-transparent tw-px-4 tw-py-3 tw-text-[13px] tw-text-slate-800 tw-outline-none" id="buscarEtiquetaProducto" placeholder="Buscar producto, SKU, SKU padre, variante, categoría..." autocomplete="off">
                                <span class="tw-hidden tw-items-center tw-pr-3 sm:tw-flex">
                                    <span class="tw-rounded-lg tw-bg-slate-100 tw-px-2 tw-py-1 tw-font-mono tw-text-[10px] tw-font-semibold tw-text-slate-500">SKU</span>
                                </span>
                            </div>

                            <div class="tw-mt-3 tw-flex tw-flex-col tw-gap-2 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between">
                                <div class="tw-flex tw-flex-wrap tw-gap-2" id="filtrosEtiquetas">
                                    <button type="button" class="lb-filter active tw-min-h-[34px] tw-rounded-full tw-border tw-border-slate-200 tw-bg-white tw-px-3.5 tw-text-[12px] tw-font-semibold tw-text-slate-600 tw-transition hover:tw-border-slate-300 hover:tw-bg-slate-50" data-filter="todos">Todos</button>
                                    <button type="button" class="lb-filter tw-min-h-[34px] tw-rounded-full tw-border tw-border-slate-200 tw-bg-white tw-px-3.5 tw-text-[12px] tw-font-semibold tw-text-slate-600 tw-transition hover:tw-border-slate-300 hover:tw-bg-slate-50" data-filter="simple">Simples</button>
                                    <button type="button" class="lb-filter tw-min-h-[34px] tw-rounded-full tw-border tw-border-slate-200 tw-bg-white tw-px-3.5 tw-text-[12px] tw-font-semibold tw-text-slate-600 tw-transition hover:tw-border-slate-300 hover:tw-bg-slate-50" data-filter="variacion">Variantes</button>
                                    <button type="button" class="lb-filter tw-min-h-[34px] tw-rounded-full tw-border tw-border-slate-200 tw-bg-white tw-px-3.5 tw-text-[12px] tw-font-semibold tw-text-slate-600 tw-transition hover:tw-border-slate-300 hover:tw-bg-slate-50" data-filter="seleccionados">Seleccionados</button>
                                </div>
                                <span id="resultadosEtiquetasCount" class="tw-text-[11px] tw-font-medium tw-text-slate-400">Cargando catálogo...</span>
                            </div>

                            <div class="lb-list-scroll tw-mt-3 tw-max-h-[520px] tw-min-h-[190px] tw-overflow-y-auto tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/50 tw-p-2" id="listaProductosEtiquetas">
                                <div class="tw-flex tw-min-h-[170px] tw-items-center tw-justify-center tw-gap-3 tw-text-[12px] tw-text-slate-400">
                                    <span class="spinner-border spinner-border-sm"></span><span>Cargando productos...</span>
                                </div>
                            </div>

                            <div class="tw-mt-4 tw-grid tw-grid-cols-1 tw-gap-3 sm:tw-grid-cols-3">
                                <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-3.5">
                                    <div class="tw-text-[10px] tw-font-semibold tw-uppercase tw-tracking-wide tw-text-slate-400">Seleccionados</div>
                                    <div class="tw-mt-1 tw-flex tw-items-baseline tw-gap-1.5">
                                        <span id="cantidadProductosSeleccionados" class="tw-text-xl tw-font-bold tw-text-slate-900">0</span>
                                        <span class="tw-text-[11px] tw-text-slate-500">productos / variantes</span>
                                    </div>
                                </div>
                                <button type="button" class="tw-flex tw-min-h-[72px] tw-items-center tw-justify-between tw-gap-3 tw-rounded-2xl tw-border tw-border-tique-100 tw-bg-tique-50/70 tw-px-4 tw-py-3 tw-text-left tw-transition hover:tw-border-tique-200 hover:tw-bg-tique-50" id="btnCantidadStock" title="Usar el stock actual como cantidad de etiquetas">
                                    <span>
                                        <span class="tw-block tw-text-[10px] tw-font-semibold tw-uppercase tw-tracking-wide tw-text-tique-700">Cantidad rápida</span>
                                        <span class="tw-mt-1 tw-block tw-text-[12px] tw-font-semibold tw-text-slate-800">Etiquetas = stock</span>
                                    </span>
                                    <i class="fas fa-boxes tw-text-tique-600"></i>
                                </button>
                                <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-3.5 sm:tw-text-right">
                                    <div class="tw-text-[10px] tw-font-semibold tw-uppercase tw-tracking-wide tw-text-slate-400">Total a imprimir</div>
                                    <div id="cantidadEtiquetasSeleccionadas" class="tw-mt-1 tw-text-xl tw-font-bold tw-text-tique-700">0 etiquetas</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Paso 2 -->
                    <div class="tw-overflow-hidden tw-rounded-[22px] tw-border tw-border-slate-200/80 tw-bg-white tw-shadow-tique-soft">
                        <div class="tw-flex tw-flex-col tw-gap-3 tw-border-b tw-border-slate-100 tw-p-5 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between md:tw-px-6">
                            <div class="tw-flex tw-items-center tw-gap-3">
                                <span class="tw-flex tw-h-9 tw-w-9 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-xl tw-bg-slate-900 tw-text-[12px] tw-font-bold tw-text-white">2</span>
                                <div>
                                    <h5 class="tw-m-0 tw-text-[15px] tw-font-semibold tw-text-slate-900">Formato e impresión</h5>
                                    <p class="tw-mb-0 tw-mt-0.5 tw-text-[12px] tw-text-slate-500">Configura columnas, tamaño físico y contenido de la etiqueta.</p>
                                </div>
                            </div>
                            <button type="button" class="tw-inline-flex tw-items-center tw-gap-2 tw-self-start tw-rounded-xl tw-border-0 tw-bg-transparent tw-px-2 tw-py-1.5 tw-text-[12px] tw-font-semibold tw-text-tique-700 hover:tw-bg-tique-50" id="btnRestaurarFormato">
                                <i class="fas fa-undo-alt"></i><span>Restaurar</span>
                            </button>
                        </div>

                        <div class="tw-space-y-5 tw-p-5 md:tw-p-6">
                            <div>
                                <div class="tw-mb-2.5 tw-text-[11px] tw-font-semibold tw-uppercase tw-tracking-[.08em] tw-text-slate-500">Columnas por fila</div>
                                <div class="tw-grid tw-grid-cols-3 tw-gap-2" id="selectorColumnas">
                                    <button type="button" class="lb-column-btn active tw-min-h-[46px] tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-text-[12px] tw-font-semibold tw-text-slate-600 tw-transition hover:tw-bg-slate-50" data-cols="1"><span class="tw-font-bold">1</span> columna</button>
                                    <button type="button" class="lb-column-btn tw-min-h-[46px] tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-text-[12px] tw-font-semibold tw-text-slate-600 tw-transition hover:tw-bg-slate-50" data-cols="2"><span class="tw-font-bold">2</span> columnas</button>
                                    <button type="button" class="lb-column-btn tw-min-h-[46px] tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-text-[12px] tw-font-semibold tw-text-slate-600 tw-transition hover:tw-bg-slate-50" data-cols="3"><span class="tw-font-bold">3</span> columnas</button>
                                </div>
                            </div>

                            <div>
                                <div class="tw-mb-2.5 tw-text-[11px] tw-font-semibold tw-uppercase tw-tracking-[.08em] tw-text-slate-500">Tamaño y separación</div>
                                <div class="tw-grid tw-grid-cols-1 tw-gap-3 sm:tw-grid-cols-2">
                                    <?php
                                    $camposMedida = [
                                        ['labelWidth', 'Ancho de etiqueta', 15, 120, 30],
                                        ['labelHeight', 'Alto de etiqueta', 10, 150, 20],
                                        ['labelGapX', 'Separación horizontal', 0, 20, 2],
                                        ['labelGapY', 'Gap / avance vertical', 0, 20, 2],
                                    ];
                                    foreach ($camposMedida as [$idCampo, $etiqueta, $min, $max, $valor]) {
                                    ?>
                                    <label class="tw-block tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/60 tw-p-3.5" for="<?= htmlspecialchars($idCampo) ?>">
                                        <span class="tw-mb-2 tw-block tw-text-[11px] tw-font-medium tw-text-slate-600"><?= htmlspecialchars($etiqueta) ?></span>
                                        <span class="tw-flex tw-items-center tw-overflow-hidden tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white focus-within:tw-border-tique-400 focus-within:tw-ring-4 focus-within:tw-ring-tique-50">
                                            <input id="<?= htmlspecialchars($idCampo) ?>" type="number" class="tw-min-w-0 tw-flex-1 tw-border-0 tw-bg-transparent tw-px-3 tw-py-2.5 tw-text-[13px] tw-font-semibold tw-text-slate-800 tw-outline-none" min="<?= $min ?>" max="<?= $max ?>" step="0.5" value="<?= $valor ?>">
                                            <span class="tw-border-l tw-border-slate-100 tw-bg-slate-50 tw-px-3 tw-py-2.5 tw-text-[11px] tw-font-semibold tw-text-slate-400">mm</span>
                                        </span>
                                    </label>
                                    <?php } ?>
                                </div>
                            </div>

                            <div>
                                <div class="tw-mb-2.5 tw-text-[11px] tw-font-semibold tw-uppercase tw-tracking-[.08em] tw-text-slate-500">Contenido de la etiqueta</div>
                                <div class="tw-grid tw-grid-cols-1 tw-gap-2 sm:tw-grid-cols-2 lg:tw-grid-cols-3">
                                    <label class="tw-flex tw-cursor-pointer tw-items-center tw-gap-3 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-3 tw-text-[12px] tw-font-medium tw-text-slate-700 hover:tw-bg-slate-50"><input id="showBusiness" type="checkbox" class="lb-check tw-h-4 tw-w-4"> Nombre del negocio</label>
                                    <label class="tw-flex tw-cursor-pointer tw-items-center tw-gap-3 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-3 tw-text-[12px] tw-font-medium tw-text-slate-700 hover:tw-bg-slate-50"><input id="showName" type="checkbox" class="lb-check tw-h-4 tw-w-4" checked> Producto / variante</label>
                                    <label class="tw-flex tw-cursor-pointer tw-items-center tw-gap-3 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-3 tw-text-[12px] tw-font-medium tw-text-slate-700 hover:tw-bg-slate-50"><input id="showSku" type="checkbox" class="lb-check tw-h-4 tw-w-4" checked> SKU en texto</label>
                                    <label class="tw-flex tw-cursor-pointer tw-items-center tw-gap-3 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-3 tw-text-[12px] tw-font-medium tw-text-slate-700 hover:tw-bg-slate-50"><input id="showPrice" type="checkbox" class="lb-check tw-h-4 tw-w-4" checked> Precio de venta</label>
                                    <label class="tw-flex tw-cursor-pointer tw-items-center tw-gap-3 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-3 tw-text-[12px] tw-font-medium tw-text-slate-700 hover:tw-bg-slate-50"><input id="showBorder" type="checkbox" class="lb-check tw-h-4 tw-w-4"> Guía de borde</label>
                                    <label class="tw-flex tw-cursor-pointer tw-items-center tw-gap-3 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-3 tw-text-[12px] tw-font-medium tw-text-slate-700 hover:tw-bg-slate-50"><input id="compactMode" type="checkbox" class="lb-check tw-h-4 tw-w-4"> Modo compacto</label>
                                </div>
                            </div>

                            <div class="tw-grid tw-grid-cols-1 tw-gap-3 lg:tw-grid-cols-2">
                                <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50/60 tw-p-4">
                                    <div class="tw-mb-2 tw-flex tw-items-center tw-justify-between tw-gap-3">
                                        <div>
                                            <div class="tw-text-[11px] tw-font-semibold tw-uppercase tw-tracking-[.08em] tw-text-slate-500">Perfil</div>
                                            <div class="tw-mt-0.5 tw-text-[12px] tw-font-semibold tw-text-slate-800">Impresora y DPI</div>
                                        </div>
                                        <button type="button" class="tw-flex tw-h-9 tw-w-9 tw-items-center tw-justify-center tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-text-slate-500 hover:tw-border-slate-300 hover:tw-bg-slate-50" id="btnAdministrarImpresoras" title="Administrar perfiles"><i class="fas fa-cog"></i></button>
                                    </div>
                                    <select class="tw-w-full tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-px-3 tw-py-2.5 tw-text-[12px] tw-text-slate-700 tw-outline-none focus:tw-border-tique-400" id="printerProfile"></select>
                                    <div class="tw-mt-2 tw-text-[11px] tw-leading-5 tw-text-slate-500" id="printerProfileNote"></div>
                                </div>

                                <div class="tw-rounded-2xl tw-border tw-border-tique-100 tw-bg-tique-50/45 tw-p-4">
                                    <div class="tw-flex tw-items-start tw-justify-between tw-gap-3">
                                        <div>
                                            <div class="tw-text-[11px] tw-font-semibold tw-uppercase tw-tracking-[.08em] tw-text-tique-700">Método</div>
                                            <label class="tw-mb-0 tw-mt-1 tw-flex tw-cursor-pointer tw-items-center tw-gap-2 tw-text-[12px] tw-font-semibold tw-text-slate-800"><input type="radio" name="printMode" value="direct" class="lb-check" checked> Impresión directa</label>
                                        </div>
                                        <span class="lb-direct-status tw-inline-flex tw-items-center tw-gap-1.5 tw-rounded-full tw-bg-amber-100 tw-px-2.5 tw-py-1 tw-text-[10px] tw-font-semibold tw-text-amber-800" id="directPrintStatus"><i class="fas fa-circle tw-text-[7px]"></i><span>Sin conectar</span></span>
                                    </div>

                                    <div class="tw-mt-3 tw-space-y-2" id="directPrintBox">
                                        <div class="tw-flex tw-flex-col tw-gap-2 sm:tw-flex-row">
                                            <select class="tw-min-w-0 tw-flex-1 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-px-3 tw-py-2.5 tw-text-[12px] tw-text-slate-700 tw-outline-none focus:tw-border-tique-400" id="directPrinterName"><option value="">Detecta las impresoras instaladas...</option></select>
                                            <button type="button" class="tw-inline-flex tw-min-h-[40px] tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border tw-border-tique-200 tw-bg-white tw-px-3 tw-text-[12px] tw-font-semibold tw-text-tique-700 hover:tw-bg-tique-50" id="btnDetectarImpresoras"><i class="fas fa-plug"></i><span>Detectar</span></button>
                                        </div>
                                        <button type="button" class="tw-inline-flex tw-min-h-[38px] tw-w-full tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border-0 tw-bg-slate-900 tw-px-3 tw-text-[12px] tw-font-semibold tw-text-white hover:tw-bg-slate-800" id="btnDescargarConector"><i class="fas fa-download"></i><span>Descargar Conector TiquePOS</span></button>
                                        <div class="tw-text-[10px] tw-leading-4 tw-text-slate-500" id="connectorVersionInfo">TIQUEPOS S.A.C. · RUC 20609518597 · Windows 10/11</div>
                                        <div class="tw-text-[10px] tw-leading-4 tw-text-slate-500">El conector detecta las impresoras de Windows y envía TSPL directamente, sin abrir el diálogo de impresión.</div>
                                    </div>

                                    <div class="tw-mt-3 tw-border-t tw-border-tique-100 tw-pt-3">
                                        <label class="tw-mb-0 tw-flex tw-cursor-pointer tw-items-center tw-gap-2 tw-text-[11px] tw-font-medium tw-text-slate-500"><input type="radio" name="printMode" value="system" class="lb-check"> Usar diálogo del sistema <span class="tw-rounded-full tw-bg-white tw-px-2 tw-py-0.5 tw-text-[9px] tw-text-slate-400">Respaldo</span></label>
                                    </div>
                                </div>
                            </div>

                            <div class="tw-hidden tw-rounded-xl tw-border tw-border-amber-200 tw-bg-amber-50 tw-px-3.5 tw-py-3 tw-text-[11px] tw-leading-5 tw-text-amber-800" id="printerWidthAlert"></div>
                        </div>
                    </div>
                </div>

                <!-- Paso 3 -->
                <div class="lb-preview-sticky tw-sticky tw-top-[86px] tw-self-start">
                    <div class="tw-overflow-hidden tw-rounded-[22px] tw-border tw-border-slate-200/80 tw-bg-white tw-shadow-tique-card">
                        <div class="tw-flex tw-items-center tw-justify-between tw-gap-3 tw-border-b tw-border-slate-100 tw-p-5">
                            <div class="tw-flex tw-items-center tw-gap-3">
                                <span class="tw-flex tw-h-9 tw-w-9 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-xl tw-bg-slate-900 tw-text-[12px] tw-font-bold tw-text-white">3</span>
                                <div>
                                    <h5 class="tw-m-0 tw-text-[15px] tw-font-semibold tw-text-slate-900">Vista previa</h5>
                                    <p class="tw-mb-0 tw-mt-0.5 tw-text-[11px] tw-text-slate-500" id="previewSummary">Selecciona al menos un producto.</p>
                                </div>
                            </div>
                            <span class="tw-shrink-0 tw-rounded-full tw-border tw-border-slate-200 tw-bg-slate-50 tw-px-2.5 tw-py-1 tw-font-mono tw-text-[10px] tw-font-semibold tw-text-slate-600" id="previewSizeBadge">30 × 20 mm</span>
                        </div>

                        <div class="tw-p-4 sm:tw-p-5">
                            <div class="lb-preview-stage tw-rounded-2xl tw-border tw-border-slate-200 tw-p-5" id="previewStage">
                                <div class="tw-flex tw-min-h-[300px] tw-flex-col tw-items-center tw-justify-center tw-gap-3 tw-text-center">
                                    <span class="tw-flex tw-h-12 tw-w-12 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-slate-100 tw-text-slate-400"><i class="fas fa-barcode tw-text-lg"></i></span>
                                    <div>
                                        <div class="tw-text-[12px] tw-font-semibold tw-text-slate-600">Sin etiquetas seleccionadas</div>
                                        <div class="tw-mt-1 tw-text-[11px] tw-text-slate-400">La vista previa aparecerá aquí.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="tw-mt-4 tw-grid tw-grid-cols-1 tw-gap-2 sm:tw-grid-cols-2 xl:tw-grid-cols-1 2xl:tw-grid-cols-2">
                                <button type="button" class="tw-inline-flex tw-min-h-[44px] tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-px-4 tw-text-[12px] tw-font-semibold tw-text-slate-600 tw-transition hover:tw-border-slate-300 hover:tw-bg-slate-50" id="btnGuardarFormato"><i class="far fa-save"></i><span>Guardar formato</span></button>
                                <button type="button" class="tw-inline-flex tw-min-h-[44px] tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border-0 tw-bg-tique-500 tw-px-4 tw-text-[12px] tw-font-semibold tw-text-white tw-shadow-[0_8px_20px_rgba(0,164,106,.18)] tw-transition hover:tw-bg-tique-600" id="btnImprimirEtiquetas"><i class="fas fa-print"></i><span>Imprimir etiquetas</span></button>
                            </div>
                            <div class="tw-mt-3 tw-flex tw-items-start tw-gap-2 tw-rounded-xl tw-bg-slate-50 tw-px-3 tw-py-2.5 tw-text-[10px] tw-leading-4 tw-text-slate-500" id="printMethodNote">
                                <i class="fas fa-info-circle tw-mt-0.5 tw-text-tique-600"></i>
                                <span>En modo directo, TiquePOS envía la etiqueta a la impresora elegida sin abrir el cuadro de impresión. El modo del sistema queda disponible como respaldo.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Se mantiene Bootstrap para el modal porque Stisla ya lo gestiona en todo TiquePOS. -->
<div class="modal fade" id="modalImpresorasEtiquetas" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content tw-overflow-hidden tw-rounded-[20px] tw-border-0 tw-shadow-tique-card">
            <div class="modal-header tw-border-b tw-border-slate-100 tw-bg-white tw-p-5">
                <div class="tw-flex tw-items-start tw-gap-3">
                    <span class="tw-flex tw-h-10 tw-w-10 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-xl tw-bg-tique-50 tw-text-tique-700"><i class="fas fa-print"></i></span>
                    <div><h5 class="modal-title tw-m-0 tw-text-[15px] tw-font-semibold tw-text-slate-900">Perfiles de impresora</h5><small class="tw-mt-1 tw-block tw-text-[11px] tw-text-slate-500">Se guardan en este dispositivo porque las impresoras son locales.</small></div>
                </div>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body tw-bg-slate-50/50 tw-p-5">
                <div id="listaPerfilesImpresora" class="tw-mb-4"></div>
                <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-p-4">
                    <div class="tw-grid tw-grid-cols-1 tw-gap-3 sm:tw-grid-cols-2">
                        <label class="tw-block"><span class="tw-mb-1.5 tw-block tw-text-[11px] tw-font-medium tw-text-slate-600">Nombre del perfil</span><input class="form-control" id="printerName" placeholder="Ej. TSC mostrador"></label>
                        <label class="tw-block"><span class="tw-mb-1.5 tw-block tw-text-[11px] tw-font-medium tw-text-slate-600">DPI</span><input class="form-control" id="printerDpi" type="number" min="72" max="1200" value="203"></label>
                        <label class="tw-block"><span class="tw-mb-1.5 tw-block tw-text-[11px] tw-font-medium tw-text-slate-600">Modelo / driver</span><input class="form-control" id="printerModel" placeholder="Ej. TSC TE200"></label>
                        <label class="tw-block"><span class="tw-mb-1.5 tw-block tw-text-[11px] tw-font-medium tw-text-slate-600">Ancho imprimible máx.</span><div class="input-group"><input class="form-control" id="printerMaxWidth" type="number" min="20" max="500" step="0.5" value="108"><div class="input-group-append"><span class="input-group-text">mm</span></div></div></label>
                    </div>
                    <button type="button" class="tw-mt-4 tw-inline-flex tw-min-h-[40px] tw-items-center tw-justify-center tw-gap-2 tw-rounded-xl tw-border-0 tw-bg-tique-500 tw-px-4 tw-text-[12px] tw-font-semibold tw-text-white hover:tw-bg-tique-600" id="btnAgregarPerfilImpresora"><i class="fas fa-plus"></i><span>Registrar perfil</span></button>
                </div>
                <div class="tw-mt-4 tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-p-4">
                    <ul class="tw-m-0 tw-space-y-2 tw-pl-4 tw-text-[11px] tw-leading-5 tw-text-slate-500">
                        <li>El Conector de impresoras TiquePOS se instala una sola vez en cada PC Windows y se inicia automáticamente.</li>
                        <li>Solo escucha en <strong>127.0.0.1</strong> y queda vinculado al dominio de este TiquePOS.</li>
                        <li>La <strong>TSC TE200</strong> recibe TSPL directo a través del spooler de Windows.</li>
                        <li>Si el conector no está disponible, el diálogo del sistema continúa funcionando como respaldo.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
} else {
    require 'access.php';
}

require 'footer.php';
$rutaJs = __DIR__ . '/scripts/labels.js';
$versionJs = is_file($rutaJs) ? filemtime($rutaJs) : time();
?>
<script src="Assets/js/JsBarcode.all.min.js"></script>
<script src="Views/modules/scripts/labels.js?v=<?= (int)$versionJs ?>"></script>
<?php ob_end_flush(); ?>
