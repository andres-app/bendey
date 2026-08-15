<?php

ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Lima');

$fechaMinimaCredito = (
    new DateTimeImmutable(
        'tomorrow',
        new DateTimeZone('America/Lima')
    )
)->format('Y-m-d');

if (!isset($_SESSION['nombre'])) {
    header('Location: login');
    exit;
}

require 'header.php';
require 'sidebar.php';

if ($_SESSION['ventas'] == 1) {
?>
    <!-- Tailwind aislado para esta vista. Preflight desactivado para no interferir con Bootstrap/Stisla. -->
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
                            50: '#f2fbf3',
                            100: '#e1f6e4',
                            500: '#52b848',
                            600: '#469f3e',
                            700: '#357f31'
                        }
                    },
                    boxShadow: {
                        'tique-float': '0 18px 45px rgba(15, 23, 42, .16)'
                    }
                }
            }
        };
    </script>

    <div class="main-content venta-pos-main-content">
        <section class="section venta-pos-section">
            <div class="section-body">

                <!-- =====================================================
                     SWITCH FIJO PARA MÓVIL Y TABLET
                     Permite alternar entre los datos de la venta y el pedido.
                ====================================================== -->
                <div
                    class="venta-mobile-switch-wrap"
                    id="ventaMobileSwitchWrap"
                    aria-label="Cambiar sección de la venta">

                    <div
                        class="venta-mobile-switch tw-bg-white tw-border tw-border-slate-200"
                        role="tablist"
                        aria-label="Secciones de nueva venta">

                        <span
                            class="venta-mobile-switch-slider"
                            aria-hidden="true">
                        </span>

                        <button
                            type="button"
                            class="venta-mobile-switch-btn active tw-transition-colors"
                            id="ventaSwitchDatos"
                            data-venta-panel="datos"
                            role="tab"
                            aria-selected="true"
                            aria-controls="ventaPanelDatos">
                            Datos
                        </button>

                        <button
                            type="button"
                            class="venta-mobile-switch-btn tw-transition-colors"
                            id="ventaSwitchProductos"
                            data-venta-panel="productos"
                            role="tab"
                            aria-selected="false"
                            aria-controls="ventaPanelProductos">
                            Productos
                        </button>

                    </div>
                </div>

                <!-- =====================================================
                     VENTAS EN COLA
                     Cada pestaña conserva un borrador independiente del POS.
                     No se registra en la BD hasta pulsar "Procesar venta".
                ====================================================== -->
                <div
                    class="venta-cola-bar"
                    id="ventaColaShell"
                    data-usuario="<?= (int)($_SESSION['idusuario'] ?? 0) ?>"
                    data-sucursal="<?= (int)($_SESSION['idsucursal_activa'] ?? 0) ?>"
                    data-caja="<?= (int)($_SESSION['idcaja_activa'] ?? 0) ?>"
                    aria-label="Ventas en cola">

                    <div
                        class="venta-cola-tabs"
                        id="ventaColaTabs"
                        role="tablist"
                        aria-label="Ventas abiertas">
                    </div>

                    <button
                        type="button"
                        class="venta-cola-nueva"
                        id="btnNuevaVentaCola"
                        title="Nueva venta"
                        aria-label="Abrir nueva venta">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    </button>
                </div>

                <div
                    class="venta-cola-menu"
                    id="ventaColaMenu"
                    role="menu"
                    aria-hidden="true">

                    <button
                        type="button"
                        class="venta-cola-menu-item"
                        data-venta-cola-accion="renombrar"
                        role="menuitem">
                        <i class="bi bi-pencil"></i>
                        Renombrar
                    </button>

                    <button
                        type="button"
                        class="venta-cola-menu-item"
                        data-venta-cola-accion="duplicar"
                        role="menuitem">
                        <i class="bi bi-copy"></i>
                        Duplicar pestaña
                    </button>

                    <div class="venta-cola-menu-separador"></div>

                    <button
                        type="button"
                        class="venta-cola-menu-item venta-cola-menu-item-peligro"
                        data-venta-cola-accion="cerrar"
                        role="menuitem">
                        <i class="bi bi-x-circle"></i>
                        Cerrar venta
                    </button>
                </div>

                <form id="formularioVenta" method="post" autocomplete="off">

                    <div class="row venta-pos-layout">

                        <!-- =====================================================
                             PANEL IZQUIERDO: FORMULARIO
                        ====================================================== -->
                        <div
                            class="col-lg-6 col-md-6 col-12 venta-panel-col venta-panel-col-formulario venta-panel-activo"
                            id="ventaPanelDatos"
                            role="tabpanel"
                            aria-labelledby="ventaSwitchDatos">

                            <div class="card venta-panel-card venta-panel-card-formulario">

                                <div class="card-header venta-panel-header venta-panel-header-ajustes">
                                    <h4>Nueva venta</h4>

                                    <div class="venta-ajustes-wrap">
                                        <button
                                            type="button"
                                            class="venta-ajustes-btn"
                                            id="btnAjustesVenta"
                                            aria-expanded="false"
                                            aria-controls="panelAjustesVenta"
                                            title="Configurar campos de Nueva Venta">
                                            <i class="bi bi-gear"></i>
                                            <span>Ajustes</span>
                                        </button>

                                        <div
                                            class="venta-ajustes-panel"
                                            id="panelAjustesVenta"
                                            aria-hidden="true">
                                            <div class="venta-ajustes-panel-cabecera">
                                                <div>
                                                    <strong>Campos de Nueva Venta</strong>
                                                    <small>Activa solo la información que necesitas.</small>
                                                </div>
                                                <button type="button" class="venta-ajustes-cerrar" id="btnCerrarAjustesVenta" aria-label="Cerrar ajustes">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>

                                            <div class="venta-ajustes-lista">
                                                <label class="venta-ajuste-item is-fixed">
                                                    <span>Tipo de comprobante</span>
                                                    <input type="checkbox" checked disabled>
                                                    <span class="venta-ajuste-switch"></span>
                                                </label>
                                                <label class="venta-ajuste-item is-fixed">
                                                    <span>Cliente</span>
                                                    <input type="checkbox" checked disabled>
                                                    <span class="venta-ajuste-switch"></span>
                                                </label>
                                                <label class="venta-ajuste-item"><span>Dirección</span><input type="checkbox" autocomplete="off" data-campo-switch="direccion"><span class="venta-ajuste-switch"></span></label>
                                                <label class="venta-ajuste-item"><span>Tipo de pago</span><input type="checkbox" autocomplete="off" data-campo-switch="tipo_pago"><span class="venta-ajuste-switch"></span></label>
                                                <label class="venta-ajuste-item"><span>Forma de pago</span><input type="checkbox" autocomplete="off" data-campo-switch="forma_pago"><span class="venta-ajuste-switch"></span></label>
                                                <label class="venta-ajuste-item"><span>Celular</span><input type="checkbox" autocomplete="off" data-campo-switch="celular"><span class="venta-ajuste-switch"></span></label>
                                                <label class="venta-ajuste-item"><span>Fecha de emisión</span><input type="checkbox" autocomplete="off" data-campo-switch="fecha_emision"><span class="venta-ajuste-switch"></span></label>
                                                <label class="venta-ajuste-item"><span>Tipo de operación SUNAT</span><input type="checkbox" autocomplete="off" data-campo-switch="tipo_operacion_sunat"><span class="venta-ajuste-switch"></span></label>
                                                <label class="venta-ajuste-item"><span>Descuentos</span><input type="checkbox" autocomplete="off" data-campo-switch="descuento"><span class="venta-ajuste-switch"></span></label>
                                                <label class="venta-ajuste-item"><span>Envío del comprobante</span><input type="checkbox" autocomplete="off" data-campo-switch="envio_comprobante"><span class="venta-ajuste-switch"></span></label>
                                            </div>

                                            <div class="venta-ajustes-autoguardado" aria-live="polite">
                                                <i class="bi bi-cloud-check"></i>
                                                <span id="estadoGuardadoAjustesVenta">Los cambios se guardan automáticamente</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="venta-form-shell">

                                    <div class="card-body px-0 pt-0 venta-form-scroll">

                                        <!-- =====================================
                                             CAMPOS CONFIGURABLES DE LA VENTA
                                        ====================================== -->
                                        <div class="venta-campos-grid" id="ventaCamposGrid">

                                            <div class="venta-campo venta-campo--medio" id="ventaCampoTipoComprobante" data-venta-campo="tipo_comprobante">
                                                <label for="tipo_comprobante">Tipo de comprobante</label>
                                                <select id="tipo_comprobante" name="tipo_comprobante" class="form-control form-select" required></select>
                                                <input type="hidden" id="serie_comprobante" name="serie_comprobante">
                                                <input type="hidden" id="num_comprobante" name="num_comprobante">
                                            </div>

                                            <div class="venta-campo venta-campo--medio" id="ventaCampoCliente" data-venta-campo="cliente">
                                                <label for="num_documento" class="mb-1">Cliente</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="num_documento" name="num_documento" maxlength="11" inputmode="numeric" autocomplete="off" placeholder="DNI o RUC">
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-outline-secondary px-3" id="btnConsultarCliente" onclick="consultarCliente()" title="Consultar DNI o RUC">
                                                            <i class="bi bi-arrow-repeat"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <small id="nombre_cliente" class="text-muted d-block mt-2">Déjelo vacío para usar CLIENTE VARIOS.</small>

                                                <input type="hidden" id="idcliente" name="idcliente" value="">
                                                <input type="hidden" id="cliente_generico" name="cliente_generico" value="0">
                                                <input type="hidden" id="tipo_documento" name="tipo_documento" value="">
                                                <input type="hidden" id="num_doc_real" name="num_doc_real" value="">
                                                <input type="hidden" id="nombre_cli" name="nombre_cli" value="">
                                                <input type="hidden" id="direccion" name="direccion" value="">
                                                <input type="hidden" id="email" name="email" value="">
                                            </div>

                                            <div class="venta-campo venta-campo--ancho" id="ventaCampoDireccion" data-venta-campo="direccion" hidden aria-hidden="true" style="display:none !important;">
                                                <label for="direccion_visible">Dirección</label>
                                                <input type="text" class="form-control" id="direccion_visible" autocomplete="off" maxlength="255" placeholder="Dirección del cliente">
                                                <small class="text-muted d-block mt-2">Se completa con la información obtenida del cliente o de la API DNI/RUC.</small>
                                            </div>

                                            <div class="venta-campo venta-campo--compacto" id="ventaCampoTipoPago" data-venta-campo="tipo_pago" hidden aria-hidden="true" style="display:none !important;">
                                                <label for="tipo_pago">Tipo de pago</label>
                                                <select class="form-control form-select" id="tipo_pago" name="idtipopago" required></select>
                                                <input type="hidden" id="condicion_pago" name="condicion_pago" value="">
                                            </div>

                                            <div class="venta-campo venta-campo--compacto" id="ventaCampoFormaPago" data-venta-campo="forma_pago" hidden aria-hidden="true" style="display:none !important;">
                                                <label for="forma_pago">Forma de pago</label>
                                                <select class="form-control form-select" id="forma_pago" name="idforma_pago" required></select>
                                            </div>

                                            <div class="venta-campo venta-campo--compacto" id="ventaCampoCelular" data-venta-campo="celular" hidden aria-hidden="true" style="display:none !important;">
                                                <label for="celular">Celular</label>
                                                <input type="text" class="form-control" id="celular" name="celular" maxlength="9" inputmode="numeric" autocomplete="off" placeholder="Ej.: 986634352">
                                            </div>

                                            <div class="venta-campo venta-campo--compacto" id="ventaCampoFechaEmision" data-venta-campo="fecha_emision" hidden aria-hidden="true" style="display:none !important;">
                                                <label for="fecha_emision">Fecha de emisión</label>
                                                <input type="date" class="form-control" id="fecha_emision" name="fecha_emision" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                                            </div>

                                            <div class="venta-campo venta-campo--ancho" id="ventaCampoTipoOperacionSunat" data-venta-campo="tipo_operacion_sunat" hidden aria-hidden="true" style="display:none !important;">
                                                <label for="tipo_operacion_sunat">Tipo de operación SUNAT</label>
                                                <select class="form-control form-select" id="tipo_operacion_sunat" name="tipo_operacion_sunat" required>
                                                    <option value="0101">0101 — Venta interna</option>
                                                </select>
                                                <small class="form-text text-muted" id="ayudaTipoOperacionSunat">Se utilizará la configuración tributaria de la empresa o sucursal.</small>
                                            </div>

                                            <div class="venta-campo venta-campo--medio" id="ventaCampoDescuentos" data-venta-campo="descuento" hidden aria-hidden="true" style="display:none !important;">
                                                <label class="d-block">Descuentos</label>
                                                <div class="venta-descuento-inline">
                                                    <label class="custom-switch mb-0">
                                                        <input type="checkbox" id="descuentoSwitch" class="custom-switch-input" checked>
                                                        <span class="custom-switch-indicator bg-success"></span>
                                                        <span class="custom-switch-description" id="labelDescuento">Descuento en %</span>
                                                    </label>
                                                    <input type="number" id="descuentoPorcentaje" class="form-control text-center" value="0" min="0" max="100" step="0.1" placeholder="%">
                                                </div>
                                            </div>

                                            <div class="venta-campo venta-campo--ancho" id="ventaCampoEnvioComprobante" data-venta-campo="envio_comprobante" hidden aria-hidden="true" style="display:none !important;">
                                                <label for="modo_envio">Envío del comprobante</label>
                                                <select class="form-control form-select" id="modo_envio" name="modo_envio" required>
                                                    <option value="inmediato">Enviar inmediatamente a SUNAT</option>
                                                    <option value="manual">Guardar y enviar manualmente después</option>
                                                    <option value="resumen_diario">Incluir en Resumen Diario de Boletas</option>
                                                </select>
                                                <small class="text-muted d-block mt-2" id="mensajeModoEnvio">La venta se registrará y luego será enviada automáticamente mediante APISUNAT.</small>
                                            </div>

                                        </div>

                                        <!-- DATOS OCULTOS NECESARIOS PARA BACKEND -->
                                        <input type="hidden" id="descuento_total" name="descuento_total" value="0.00">
                                        <input type="hidden" id="descuento_porcentaje" name="descuento_porcentaje" value="0.00">
                                        <input type="hidden" id="total_gravado" name="total_gravado" value="0.00">
                                        <input type="hidden" id="total_exonerado" name="total_exonerado" value="0.00">
                                        <input type="hidden" id="total_inafecto" name="total_inafecto" value="0.00">
                                        <input type="hidden" id="total_exportacion" name="total_exportacion" value="0.00">
                                        <input type="hidden" id="total_igv" name="total_igv" value="0.00">
                                        <input type="hidden" id="precios_incluyen_impuesto" name="precios_incluyen_impuesto" value="1">

                                        <!-- =====================================
                                             DATOS DE CRÉDITO
                                        ====================================== -->
                                        <div id="bloque_credito" class="row g-3 mb-4 venta-bloque-extra" style="display:none;">
                                            <div class="col-md-4">
                                                <label for="numero_cuotas" class="fw-bold">N.º de cuotas</label>
                                                <input type="number" min="1" class="form-control" id="numero_cuotas" name="numero_cuotas" placeholder="Ej.: 3">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="monto_cuota" class="fw-bold">Monto por cuota</label>
                                                <input type="text" class="form-control bg-light" id="monto_cuota" readonly placeholder="S/ 0.00">
                                                <input type="hidden" id="monto_cuota_real" name="monto_cuota" value="0.00">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="fecha_pago" class="fw-bold">Fecha del primer pago</label>
                                                <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" min="<?= htmlspecialchars($fechaMinimaCredito, ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                            <div class="col-12">
                                                <small class="text-muted">El importe se calculará según el total de la venta y el número de cuotas.</small>
                                            </div>
                                        </div>

                                        <!-- =====================================
                                             TOTAL RECIBIDO Y VUELTO
                                        ====================================== -->
                                        <div class="row g-4 mb-5 text-center venta-cobro-row">

                                            <div class="col-md-6">

                                                <label
                                                    for="total_recibido"
                                                    class="form-label text-muted fw-semibold mb-2">
                                                    Total recibido (S/)
                                                </label>

                                                <div class="d-flex justify-content-center">

                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        id="total_recibido"
                                                        name="total_recibido"
                                                        placeholder="0.00"
                                                        class="form-control total-display text-success">

                                                </div>

                                            </div>

                                            <div class="col-md-6">

                                                <label
                                                    for="vuelto"
                                                    class="form-label text-muted fw-semibold mb-2">
                                                    Vuelto (S/)
                                                </label>

                                                <div class="d-flex justify-content-center">

                                                    <input
                                                        type="text"
                                                        id="vuelto"
                                                        name="vuelto"
                                                        value="0.00"
                                                        readonly
                                                        class="form-control total-display total-disabled">

                                                </div>

                                            </div>

                                        </div>

                                        <!-- =====================================
                                             PAGO MIXTO
                                        ====================================== -->
                                        <div
                                            id="bloque_pago_mixto"
                                            class="mb-4"
                                            style="display:none;">

                                            <label class="form-label fw-bold">
                                                Detalle de pago mixto
                                            </label>

                                            <div id="pagosMixtosContainer"></div>

                                            <button
                                                type="button"
                                                class="btn btn-outline-success btn-sm mt-2"
                                                id="btnAgregarPagoMixto">

                                                <i class="bi bi-plus-circle"></i>
                                                Agregar método
                                            </button>

                                            <small class="text-muted d-block mt-2">
                                                El vuelto se calcula solamente con el importe pagado en efectivo.
                                            </small>

                                        </div>

                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- =====================================================
                             PANEL DERECHO: PEDIDO ACTUAL
                        ====================================================== -->
                        <div
                            class="col-lg-6 col-md-6 col-12 venta-panel-col venta-panel-col-pedido"
                            id="ventaPanelProductos"
                            role="tabpanel"
                            aria-labelledby="ventaSwitchProductos">

                            <div class="card venta-panel-card venta-panel-card-pedido">

                                <div class="card-header venta-panel-header venta-pedido-header">
                                    <div>
                                        <h4 class="mb-1">Pedido actual</h4>
                                        <small id="contadorProductosPedido" class="venta-pedido-contador">
                                            0 productos · 0 unidades
                                        </small>
                                    </div>

                                    <div class="venta-pedido-total-cabecera">
                                        <span>Total</span>
                                        <strong id="totalPedidoHeader">S/ 0.00</strong>
                                    </div>
                                </div>

                                <div class="card-body bg-white venta-pedido-body">

                                    <!-- =====================================
                                         BUSCADOR RÁPIDO DE PRODUCTOS
                                    ====================================== -->
                                    <div
                                        class="buscador-pedido-wrap mb-4"
                                        id="buscadorPedidoWrap">

                                        <div class="input-group buscador-pedido-input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-white border-right-0">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                            </div>

                                            <input
                                                type="search"
                                                class="form-control border-left-0 border-right-0"
                                                id="buscarProductoPedido"
                                                autocomplete="off"
                                                placeholder="Buscar por SKU o nombre del producto..."
                                                aria-label="Buscar producto por SKU o nombre"
                                                aria-controls="resultadosBusquedaPedido"
                                                aria-autocomplete="list">

                                            <div class="input-group-append">
                                                <button
                                                    type="button"
                                                    class="btn"
                                                    id="btnLimpiarBusquedaPedido"
                                                    title="Limpiar búsqueda"
                                                    aria-label="Limpiar búsqueda">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                        </div>

                                        <small
                                            class="buscador-pedido-ayuda"
                                            id="estadoBusquedaPedido"
                                            aria-live="polite">
                                            Escribe al menos 2 caracteres para buscar en tus productos existentes.
                                        </small>

                                        <div
                                            class="resultados-busqueda-pedido"
                                            id="resultadosBusquedaPedido"
                                            role="listbox"
                                            aria-label="Productos encontrados"
                                            style="display:none;">
                                        </div>

                                    </div>

                                    <div
                                        class="position-relative"
                                        id="contenedorPedido"
                                        style="min-height:100px;">

                                        <div id="detallesCards" class="venta-pedido-lista"></div>

                                        <div
                                            id="pedidoVacio"
                                            class="
                                                position-absolute
                                                top-0
                                                start-0
                                                w-100
                                                h-100
                                                d-flex
                                                flex-column
                                                justify-content-center
                                                align-items-center
                                                text-center
                                            "
                                            style="
                                                pointer-events:none;
                                                z-index:20;
                                            ">

                                            <i
                                                class="bi bi-upc-scan mb-3"
                                                style="
                                                    font-size:4rem;
                                                    color:#e0e0e0;
                                                ">
                                            </i>

                                            <div
                                                class="fw-semibold"
                                                style="
                                                    font-size:1.1rem;
                                                    color:#c0c0c0;
                                                ">
                                                Escanea los productos directamente
                                            </div>

                                            <div
                                                class="mt-1"
                                                style="
                                                    font-size:0.95rem;
                                                    color:#d0d0d0;
                                                ">
                                                o selecciónalos manualmente
                                            </div>

                                        </div>

                                    </div>

                                </div>

                                <div
                                    class="d-flex justify-content-end align-items-end p-4 venta-pedido-footer"
                                    style="pointer-events:none;">

                                    <div
                                        style="
                                            pointer-events:auto;
                                            display:flex;
                                            gap:24px;
                                        ">

                                        <button
                                            type="button"
                                            class="
                                                btn
                                                btn-success
                                                shadow
                                                d-flex
                                                align-items-center
                                                justify-content-center
                                            "
                                            style="
                                                width:72px;
                                                height:52px;
                                                border-radius:18px;
                                            "
                                            id="btnActivarEscaner"
                                            title="Activar lector de código de barras">

                                            <i
                                                class="bi bi-qr-code-scan"
                                                style="font-size:2rem;">
                                            </i>

                                        </button>

                                        <button
                                            type="button"
                                            class="
                                                btn
                                                btn-success
                                                shadow
                                                d-flex
                                                align-items-center
                                                justify-content-center
                                            "
                                            id="btnAbrirModal"
                                            style="
                                                width:72px;
                                                height:52px;
                                                border-radius:18px;
                                            "
                                            title="Agregar producto">

                                            <i
                                                class="bi bi-plus"
                                                style="font-size:2rem;">
                                            </i>

                                        </button>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- =====================================
                         ACCIÓN PRINCIPAL FIJA DEL POS
                         Se mantiene visible en Datos y Productos.
                    ====================================== -->
                    <div
                        class="card-footer venta-form-footer tw-bg-white tw-border tw-border-slate-200 tw-shadow-tique-float"
                        id="ventaProcesarFooter"
                        role="region"
                        aria-label="Total y procesamiento de venta">

                        <div class="venta-footer-total tw-min-w-0">
                            <span class="venta-footer-total-label tw-text-slate-500">Total</span>
                            <span id="totalGeneral" class="venta-footer-total-monto tw-text-slate-900">
                                S/0.00
                            </span>
                        </div>

                        <button
                            type="submit"
                            id="btnProcesarVenta"
                            class="btn venta-procesar-btn tw-bg-tique-500 hover:tw-bg-tique-600 focus:tw-ring-4 focus:tw-ring-green-100">
                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                            <span>Procesar venta</span>
                        </button>

                    </div>

                </form>

            </div>
        </section>
    </div>


    <input
        type="text"
        id="scannerInput"
        class="scanner-capture-input"
        inputmode="none"
        autocomplete="off"
        tabindex="-1"
        aria-hidden="true">


    <style>
        /* =========================================================
           AJUSTES Y GRID INTELIGENTE DE NUEVA VENTA
        ========================================================== */
        .venta-panel-header-ajustes {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .venta-ajustes-wrap { position: relative; margin-left: auto; }

        .venta-ajustes-btn {
            border: 1px solid #dfe7e1;
            background: #fff;
            color: #42524a;
            min-height: 36px;
            padding: 7px 12px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: .86rem;
            cursor: pointer;
        }

        .venta-ajustes-btn:hover { background: #f6faf7; border-color: #bcd5c2; color: #357f31; }

        .venta-ajustes-panel {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            z-index: 1500;
            width: min(390px, calc(100vw - 32px));
            max-height: min(660px, calc(100vh - 120px));
            overflow: hidden;
            display: none;
            flex-direction: column;
            background: #fff;
            border: 1px solid #dfe7e1;
            border-radius: 16px;
            box-shadow: 0 22px 55px rgba(15, 23, 42, .18);
        }

        .venta-ajustes-panel.is-open { display: flex; }
        .venta-ajustes-panel-cabecera { padding: 16px 17px 12px; border-bottom: 1px solid #edf1ee; display:flex; justify-content:space-between; gap:12px; }
        .venta-ajustes-panel-cabecera strong { display:block; color:#26332b; font-size:.96rem; }
        .venta-ajustes-panel-cabecera small { display:block; margin-top:3px; color:#7a8880; font-size:.78rem; }
        .venta-ajustes-cerrar { border:0; background:transparent; color:#718078; padding:4px; cursor:pointer; }
        .venta-ajustes-lista { overflow-y:auto; padding:8px 12px; }
        .venta-ajuste-item { min-height:42px; display:grid; grid-template-columns:minmax(0,1fr) 38px; align-items:center; gap:12px; padding:7px 6px; margin:0; color:#37463e; font-size:.84rem; cursor:pointer; position:relative; }
        .venta-ajuste-item input { position:absolute; opacity:0; pointer-events:none; }
        .venta-ajuste-switch { width:36px; height:20px; border-radius:999px; background:#d9e0dc; position:relative; transition:.18s ease; justify-self:end; }
        .venta-ajuste-switch::after { content:""; position:absolute; width:16px; height:16px; border-radius:50%; background:#fff; top:2px; left:2px; box-shadow:0 1px 3px rgba(0,0,0,.18); transition:.18s ease; }
        .venta-ajuste-item input:checked + .venta-ajuste-switch { background:#52b848; }
        .venta-ajuste-item input:checked + .venta-ajuste-switch::after { transform:translateX(16px); }
        .venta-ajuste-item.is-fixed { color:#7a8880; cursor:default; }
        .venta-ajuste-item.is-fixed::after { content:"Fijo"; position:absolute; right:48px; font-size:.68rem; color:#9aa69f; }
        .venta-ajustes-autoguardado {
            min-height: 42px;
            padding: 10px 16px 12px;
            border-top: 1px solid #edf1ee;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #7a8880;
            font-size: .76rem;
        }
        .venta-ajustes-autoguardado i { font-size: .92rem; }
        .venta-ajustes-autoguardado.is-saving { color: #526170; }
        .venta-ajustes-autoguardado.is-saved { color: #357f31; }
        .venta-ajustes-autoguardado.is-error { color: #b42318; }

        .venta-campos-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
            align-items: start;
        }

        .venta-campo { min-width:0; grid-column: span 4; }
        .venta-campo--compacto { grid-column: span 4; }
        .venta-campo--medio { grid-column: span 6; }
        .venta-campo--ancho { grid-column: span 8; }
        [data-venta-campo][hidden],
        .venta-campo.is-hidden { display:none !important; }
        .venta-descuento-inline { min-height:42px; display:flex; align-items:center; gap:18px; }
        .venta-descuento-inline #descuentoPorcentaje { width:104px; margin-left:auto; }
        .venta-bloque-extra { margin-top:4px; }

        @media (max-width: 991.98px) {
            .venta-campo,
            .venta-campo--compacto,
            .venta-campo--medio,
            .venta-campo--ancho { grid-column: span 6; }
            .venta-campo--ancho { grid-column: span 12; }
        }

        @media (max-width: 575.98px) {
            .venta-campos-grid { grid-template-columns: 1fr; gap:14px; }
            .venta-campo,
            .venta-campo--compacto,
            .venta-campo--medio,
            .venta-campo--ancho { grid-column: 1 / -1; }
            .venta-ajustes-btn span { display:none; }
            .venta-ajustes-btn { width:38px; justify-content:center; padding:7px; }
            .venta-ajustes-panel { right:-4px; }
            .venta-descuento-inline { align-items:flex-start; flex-direction:column; gap:10px; }
            .venta-descuento-inline #descuentoPorcentaje { width:100%; margin-left:0; }
        }

        /* =========================================================
           SWITCH FIJO DATOS / PRODUCTOS (MÓVIL Y TABLET)
        ========================================================== */
        .venta-mobile-switch-wrap {
            display: none;
        }

        /* =========================================================
           VENTAS EN COLA · PESTAÑAS DEL POS
        ========================================================== */
        .venta-cola-bar {
            position: relative;
            z-index: 70;
            width: 100%;
            min-width: 0;
            min-height: 46px;
            display: flex;
            align-items: stretch;
            gap: 8px;
            margin: 12px 0 10px;
            padding: 5px;
            border: 1px solid #dfe6e2;
            border-radius: 13px;
            background: #f5f8f6;
            box-shadow: 0 5px 15px rgba(15, 23, 42, .045);
        }

        .venta-cola-bar.is-locked {
            opacity: .72;
            pointer-events: none;
        }

        .venta-cola-tabs {
            min-width: 0;
            flex: 1 1 auto;
            display: flex;
            align-items: stretch;
            gap: 6px;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: thin;
            scrollbar-color: #c9d3cc transparent;
            overscroll-behavior-x: contain;
        }

        .venta-cola-tabs::-webkit-scrollbar {
            height: 4px;
        }

        .venta-cola-tabs::-webkit-scrollbar-thumb {
            border-radius: 99px;
            background: #c9d3cc;
        }

        .venta-cola-tab-item {
            min-width: 158px;
            max-width: 230px;
            flex: 0 0 auto;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 34px;
            align-items: stretch;
            border: 1px solid #dfe5e1;
            border-radius: 10px;
            overflow: hidden;
            background: #ffffff;
            transition:
                border-color .16s ease,
                background-color .16s ease,
                box-shadow .16s ease;
        }

        .venta-cola-tab-item:hover {
            border-color: #bcd3c3;
        }

        .venta-cola-tab-item.active {
            border-color: #6fbd7c;
            background: #f4fbf6;
            box-shadow: inset 0 -2px 0 #52b848;
        }

        .venta-cola-tab {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 7px 6px 10px;
            border: 0;
            color: #4f5d55;
            background: transparent;
            text-align: left;
            cursor: pointer;
        }

        .venta-cola-tab:hover,
        .venta-cola-tab:focus,
        .venta-cola-tab:active {
            border: 0 !important;
            outline: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        .venta-cola-tab-icono {
            width: 26px;
            height: 26px;
            flex: 0 0 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #478658;
            background: #edf6ef;
            font-size: .78rem;
        }

        .venta-cola-tab-item.active .venta-cola-tab-icono {
            color: #ffffff;
            background: #52b848;
        }

        .venta-cola-tab-contenido {
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1px;
        }

        .venta-cola-tab-nombre {
            overflow: hidden;
            color: #354239;
            font-size: .76rem;
            font-weight: 600;
            line-height: 1.15;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .venta-cola-tab-total {
            color: #89938d;
            font-size: .64rem;
            font-weight: 500;
            line-height: 1.1;
        }

        .venta-cola-tab-item.active .venta-cola-tab-nombre {
            color: #286f3c;
        }

        .venta-cola-tab-menu-btn {
            width: 34px;
            min-width: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 0;
            border-left: 1px solid #edf1ee;
            color: #87928b;
            background: transparent;
            font-size: 1rem;
            cursor: pointer;
        }

        .venta-cola-tab-menu-btn:hover,
        .venta-cola-tab-menu-btn:focus {
            color: #34443a;
            background: #eef4f0;
            outline: 0;
            box-shadow: none;
        }

        .venta-cola-nueva {
            width: 38px;
            min-width: 38px;
            flex: 0 0 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 1px solid #b9d7c1;
            border-radius: 10px;
            color: #277f42;
            background: #edf8f0;
            font-size: 1rem;
            cursor: pointer;
            transition: .16s ease;
        }

        .venta-cola-nueva:hover,
        .venta-cola-nueva:focus {
            color: #ffffff;
            border-color: #52b848;
            background: #52b848;
            outline: 0;
            box-shadow: none;
        }

        .venta-cola-menu {
            position: fixed;
            z-index: 100000;
            width: 190px;
            display: none;
            padding: 6px;
            border: 1px solid #dfe6e2;
            border-radius: 11px;
            background: #ffffff;
            box-shadow: 0 16px 38px rgba(15, 23, 42, .17);
        }

        .venta-cola-menu.show {
            display: block;
        }

        .venta-cola-menu-item {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 10px;
            border: 0;
            border-radius: 8px;
            color: #46534b;
            background: transparent;
            font-size: .78rem;
            font-weight: 400;
            text-align: left;
            cursor: pointer;
        }

        .venta-cola-menu-item:hover,
        .venta-cola-menu-item:focus {
            color: #26352d;
            background: #f2f6f3;
            outline: 0;
        }

        .venta-cola-menu-item i {
            width: 17px;
            color: #718078;
            text-align: center;
        }

        .venta-cola-menu-item-peligro,
        .venta-cola-menu-item-peligro i {
            color: #ba4b4b;
        }

        .venta-cola-menu-item-peligro:hover {
            color: #a83e3e;
            background: #fff4f4;
        }

        .venta-cola-menu-separador {
            height: 1px;
            margin: 4px 3px;
            background: #edf1ee;
        }

        @media (min-width: 1200px) {
            .venta-cola-bar {
                margin-top: 24px;
                margin-bottom: 10px;
            }

            .venta-pos-main-content .venta-pos-layout {
                margin-top: 0 !important;
            }

            .venta-panel-card-formulario {
                min-height: calc(100vh - 174px);
            }

            .venta-panel-card-pedido {
                height: calc(100vh - 174px);
                min-height: 470px;
            }
        }

        @media (max-width: 767.98px) {
            .venta-cola-bar {
                min-height: 43px;
                margin-top: 4px;
                margin-bottom: 8px;
                padding: 4px;
                border-radius: 10px;
            }

            .venta-cola-tab-item {
                min-width: 145px;
                max-width: 195px;
                grid-template-columns: minmax(0, 1fr) 32px;
                border-radius: 8px;
            }

            .venta-cola-tab {
                padding: 5px 6px 5px 8px;
                gap: 6px;
            }

            .venta-cola-tab-icono {
                width: 24px;
                height: 24px;
                flex-basis: 24px;
                border-radius: 7px;
                font-size: .7rem;
            }

            .venta-cola-tab-nombre {
                font-size: 11px;
            }

            .venta-cola-tab-total {
                font-size: 9px;
            }

            .venta-cola-tab-menu-btn {
                width: 32px;
                min-width: 32px;
            }

            .venta-cola-nueva {
                width: 34px;
                min-width: 34px;
                flex-basis: 34px;
                border-radius: 8px;
            }
        }

        /* =========================================================
           DISEÑO POS: UNA SOLA NAVEGACIÓN VERTICAL
           El formulario no genera barras internas. El pedido conserva
           desplazamiento con rueda/táctil, pero sin mostrar una barra gruesa.
        ========================================================== */
        .section-body {
            overflow-x: clip;
        }

        .venta-pos-layout {
            align-items: flex-start;
            margin-right: -12px;
            margin-left: -12px;
        }

        .venta-panel-col {
            padding-right: 12px;
            padding-left: 12px;
        }

        .venta-panel-card {
            margin-bottom: 0;
            border: 1px solid #e4e9e6;
            border-radius: 16px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 10px 26px rgba(15, 23, 42, .065);
        }

        .venta-panel-header {
            min-height: 58px;
            display: flex;
            align-items: center;
            padding: 12px 18px;
            border-bottom: 1px solid #e8ecea;
            background: #ffffff;
        }

        .venta-panel-header h4 {
            margin: 0;
            color: #26332c;
            font-size: 1rem;
            font-weight: 800;
        }

        .venta-pedido-header {
            justify-content: space-between;
            gap: 18px;
        }

        .venta-pedido-contador {
            display: block;
            margin-top: 3px;
            color: #7a8780;
            font-size: .72rem;
            font-weight: 600;
        }

        .venta-pedido-total-cabecera {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            line-height: 1.08;
            white-space: nowrap;
        }

        .venta-pedido-total-cabecera span {
            margin-bottom: 4px;
            color: #839087;
            font-size: .65rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .venta-pedido-total-cabecera strong {
            color: #26352d;
            font-size: 1.18rem;
            font-weight: 800;
        }

        .venta-panel-card-formulario {
            display: flex;
            flex-direction: column;
        }

        .venta-form-shell {
            min-height: 0;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            margin: 0;
            background: #ffffff;
        }

        .venta-form-scroll {
            flex: 1 1 auto;
            padding: 14px 18px 10px !important;
            overflow: visible;
        }

        .venta-form-scroll > .row,
        .venta-form-scroll > #bloque_credito {
            margin-right: -6px;
            margin-left: -6px;
        }

        .venta-form-scroll > .row > [class*="col-"],
        .venta-form-scroll > #bloque_credito > [class*="col-"] {
            padding-right: 6px;
            padding-left: 6px;
        }

        .venta-form-scroll > .row.mb-4,
        .venta-form-scroll > #bloque_credito.mb-4 {
            margin-bottom: 11px !important;
        }

        .venta-form-scroll > .row.mb-5 {
            margin-bottom: 12px !important;
        }

        .venta-form-scroll label,
        .venta-form-scroll .form-label {
            margin-bottom: 5px !important;
            color: #66736c;
            font-size: .75rem;
            font-weight: 600;
        }

        .venta-form-scroll .form-control,
        .venta-form-scroll .form-select,
        .venta-form-scroll .input-group-text,
        .venta-form-scroll .input-group .btn {
            min-height: 39px;
            border-color: #dce3df;
            border-radius: 9px;
            font-size: .82rem;
        }

        .venta-form-scroll .input-group-prepend .input-group-text,
        .venta-form-scroll .input-group-prepend .btn {
            border-radius: 9px 0 0 9px;
        }

        .venta-form-scroll .input-group-append .input-group-text,
        .venta-form-scroll .input-group-append .btn {
            border-radius: 0 9px 9px 0;
        }

        .venta-form-scroll .form-control:focus,
        .venta-form-scroll .form-select:focus {
            border-color: #7fc28d;
            box-shadow: 0 0 0 .16rem rgba(82, 184, 72, .11);
        }

        #nombre_cliente,
        #mensajeModoEnvio,
        #bloque_credito small,
        #bloque_pago_mixto small {
            line-height: 1.3;
        }

        #nombre_cliente {
            min-height: 15px;
            margin-top: 4px !important;
            font-size: .67rem;
        }

        .venta-descuento-row {
            margin: 0 0 10px !important;
        }

        .venta-descuento-row > .col-12 {
            padding: 0 !important;
        }

        .venta-descuento-row > .col-12 > .d-flex {
            min-height: 42px;
            padding: 6px 12px;
            border: 1px solid #e1e8e4;
            border-radius: 11px;
            background: #f8faf9;
        }

        .venta-descuento-row .custom-switch-description {
            color: #536159;
            font-size: .76rem;
            font-weight: 700;
        }

        #descuentoPorcentaje {
            width: 80px !important;
            min-height: 34px;
            height: 34px;
            margin-left: 16px !important;
            padding-top: 5px;
            padding-bottom: 5px;
            background: #ffffff;
            font-weight: 800;
        }

        .venta-cobro-row {
            margin: 0 0 11px !important;
            padding: 8px 5px 9px;
            border: 1px solid #e2e9e5;
            border-radius: 12px;
            background: #fbfcfb;
        }

        .venta-cobro-row > [class*="col-"] {
            padding-right: 6px !important;
            padding-left: 6px !important;
        }

        .venta-cobro-row label {
            margin-bottom: 4px !important;
            font-size: .68rem;
        }

        .venta-cobro-row .total-display {
            min-height: 38px;
            height: 38px;
            font-size: .92rem;
            font-weight: 800;
        }

        .venta-cobro-row .total-disabled {
            color: #65716b;
            background: #edf1ef;
        }

        #bloque_pago_mixto {
            margin-bottom: 11px !important;
            padding: 11px;
            border: 1px solid #e1e8e4;
            border-radius: 12px;
            background: #fafcfb;
        }

        .venta-fila-final {
            margin-bottom: 0 !important;
        }

        .venta-fila-final textarea {
            min-height: 66px;
            height: 66px;
            resize: vertical;
        }

        .venta-modo-envio select {
            padding-right: 32px;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        #mensajeModoEnvio {
            min-height: 28px;
            margin-top: 5px !important;
            color: #7a8780 !important;
            font-size: .66rem;
        }

        .venta-form-footer,
        .venta-pedido-footer {
            flex: 0 0 auto;
            border-top: 1px solid #e7ece9;
            background: #ffffff;
        }

        .venta-form-footer {
            min-height: 66px;
            display: grid;
            grid-template-columns: minmax(0, 40%) minmax(0, 60%);
            align-items: center;
            gap: 0;
            margin: 0;
            padding: 9px 18px;
            background: #ffffff !important;
            background-color: #ffffff !important;
            opacity: 1;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }

        .venta-footer-total {
            width: 100%;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            gap: 3px;
            padding-right: 14px;
        }

        .venta-footer-total-label {
            color: #78847e;
            font-size: .68rem;
            font-weight: 700;
            line-height: 1;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .venta-footer-total-monto {
            max-width: 100%;
            overflow: hidden;
            color: #26332c;
            font-size: 1.82rem;
            font-weight: 800;
            line-height: 1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #btnProcesarVenta.venta-procesar-btn {
            width: 100%;
            min-width: 0;
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 18px;
            border: 1px solid #4daf45 !important;
            border-radius: 10px;
            color: #ffffff !important;
            background: #52b848 !important;
            background-color: #52b848 !important;
            box-shadow: 0 7px 15px rgba(82, 184, 72, .16);
            font-size: .9rem;
            font-weight: 400;
            text-shadow: none;
            transition:
                background-color .18s ease,
                border-color .18s ease,
                box-shadow .18s ease,
                transform .18s ease;
        }

        #btnProcesarVenta.venta-procesar-btn:hover,
        #btnProcesarVenta.venta-procesar-btn:focus,
        #btnProcesarVenta.venta-procesar-btn:focus-visible {
            color: #ffffff !important;
            background: #469f3e !important;
            background-color: #469f3e !important;
            border-color: #469f3e !important;
            box-shadow: 0 0 0 .18rem rgba(82, 184, 72, .18);
            outline: 0;
        }

        #btnProcesarVenta.venta-procesar-btn:active,
        #btnProcesarVenta.venta-procesar-btn.active {
            color: #ffffff !important;
            background: #3f9138 !important;
            background-color: #3f9138 !important;
            border-color: #3f9138 !important;
            box-shadow: 0 3px 8px rgba(63, 145, 56, .22) !important;
            transform: translateY(1px);
        }

        #btnProcesarVenta.venta-procesar-btn:disabled,
        #btnProcesarVenta.venta-procesar-btn.disabled {
            color: #ffffff !important;
            background: #52b848 !important;
            background-color: #52b848 !important;
            border-color: #4daf45 !important;
            opacity: .72;
            cursor: not-allowed;
        }

        .venta-pedido-footer {
            min-height: 66px;
            padding: 9px 18px !important;
        }

        .venta-pedido-footer > div {
            gap: 12px !important;
        }

        .venta-pedido-footer .btn {
            width: 58px !important;
            height: 46px !important;
            border-radius: 13px !important;
            box-shadow: 0 7px 15px rgba(37, 125, 64, .15) !important;
        }

        .venta-pedido-footer .btn i {
            font-size: 1.45rem !important;
        }

        #detallesCards {
            padding: 2px 3px 8px 1px;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        #detallesCards::-webkit-scrollbar {
            width: 0;
            height: 0;
        }

        @media (min-width: 1200px) {
            /*
             * Escritorio: separación real y visible bajo el navbar.
             * El navbar de Stisla ocupa 70 px; después se dejan 24 px,
             * equivalentes al aire lateral usado entre los paneles.
             */
            .venta-pos-main-content {
                padding-top: 70px !important;
            }

            .venta-pos-main-content .venta-pos-layout {
                margin-top: 24px !important;
                min-height: calc(100vh - 118px);
            }

            .venta-panel-card-formulario {
                min-height: calc(100vh - 118px);
            }

            .venta-panel-col-pedido {
                position: sticky;
                top: 94px;
                align-self: flex-start;
            }

            .venta-panel-card-pedido {
                height: calc(100vh - 118px);
                min-height: 510px;
                display: flex;
                flex-direction: column;
            }

            .venta-pedido-body {
                flex: 1 1 auto;
                min-height: 0;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                padding: 14px 18px 8px;
            }

            .buscador-pedido-wrap {
                flex: 0 0 auto;
                margin-bottom: 10px !important;
            }

            #contenedorPedido {
                flex: 1 1 auto;
                min-height: 0 !important;
                overflow: hidden;
            }

            #detallesCards {
                height: 100%;
                overflow-x: hidden;
                overflow-y: auto;
                overscroll-behavior: contain;
            }

            #pedidoVacio {
                min-height: 100%;
            }
        }

        @media (min-width: 1200px) and (max-height: 760px) {
            .venta-panel-header {
                min-height: 54px;
                padding-top: 10px;
                padding-bottom: 10px;
            }

            .venta-form-scroll {
                padding: 10px 16px 7px !important;
            }

            .venta-form-scroll > .row.mb-4,
            .venta-form-scroll > #bloque_credito.mb-4 {
                margin-bottom: 8px !important;
            }

            .venta-descuento-row,
            .venta-cobro-row {
                margin-bottom: 8px !important;
            }

            .venta-fila-final textarea {
                min-height: 58px;
                height: 58px;
            }

            .venta-form-footer,
            .venta-pedido-footer {
                min-height: 60px;
            }

            .venta-footer-total-monto {
                font-size: 1.55rem;
            }

            .venta-procesar-btn {
                min-height: 42px;
            }
        }

        @media (max-width: 1199.98px) {
            body.venta-switch-responsive-activo {
                padding-bottom: calc(96px + env(safe-area-inset-bottom, 0px));
            }

            .venta-mobile-switch-wrap {
                position: fixed;
                left: 50%;
                bottom: calc(14px + env(safe-area-inset-bottom, 0px));
                z-index: 1030;
                display: block;
                width: min(360px, calc(100vw - 28px));
                transform: translateX(-50%);
                pointer-events: none;
            }

            .venta-mobile-switch {
                position: relative;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                padding: 4px;
                border: 1px solid #d9e4dc;
                border-radius: 999px;
                background: rgba(244, 248, 245, .97);
                box-shadow:
                    0 12px 30px rgba(15, 23, 42, .16),
                    inset 0 1px 0 rgba(255, 255, 255, .9);
                backdrop-filter: blur(14px);
                -webkit-backdrop-filter: blur(14px);
                overflow: hidden;
                pointer-events: auto;
            }

            .venta-mobile-switch-slider {
                position: absolute;
                top: 4px;
                bottom: 4px;
                left: 4px;
                z-index: 1;
                width: calc(50% - 4px);
                border-radius: 999px;
                background: #52b848;
                box-shadow:
                    0 6px 14px rgba(82, 184, 72, .24),
                    inset 0 1px 0 rgba(255, 255, 255, .22);
                transform: translateX(0);
                transition: transform .28s cubic-bezier(.22, .61, .36, 1);
                will-change: transform;
                pointer-events: none;
            }

            .venta-mobile-switch.is-productos .venta-mobile-switch-slider {
                transform: translateX(100%);
            }

            .venta-mobile-switch-btn {
                position: relative;
                z-index: 2;
                min-width: 0;
                min-height: 48px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 10px 15px;
                border: 0;
                border-radius: 999px;
                color: #617068;
                background: transparent;
                font-size: .92rem;
                font-weight: 400;
                line-height: 1;
                cursor: pointer;
                transition: color .22s ease;
                -webkit-tap-highlight-color: transparent;
            }

            .venta-mobile-switch-btn.active {
                color: #ffffff;
            }

            .venta-mobile-switch-btn:active {
                opacity: .88;
            }

            .venta-mobile-switch-btn:hover,
            .venta-mobile-switch-btn:focus,
            .venta-mobile-switch-btn:focus-visible,
            .venta-mobile-switch-btn:active {
                border: 0 !important;
                outline: 0 !important;
                box-shadow: none !important;
            }

            .venta-pos-layout {
                display: block;
                margin: 0;
            }

            .venta-panel-col {
                position: static;
                display: none;
                width: 100%;
                max-width: 100%;
                flex: 0 0 100%;
                padding: 0;
            }

            .venta-panel-col.venta-panel-activo {
                display: block;
                animation: ventaPanelEntrada .2s ease both;
            }

            .venta-panel-card {
                height: auto;
                min-height: 0;
                margin-bottom: 16px;
                overflow: visible;
            }

            .venta-form-scroll,
            #detallesCards {
                height: auto;
                max-height: none;
                overflow: visible;
            }

            .venta-form-footer {
                position: sticky;
                bottom: calc(82px + env(safe-area-inset-bottom, 0px));
                z-index: 25;
                border-radius: 0 0 16px 16px;
                box-shadow: 0 -8px 20px rgba(15, 23, 42, .06);
            }

            .venta-pedido-body {
                overflow: visible;
            }

            @keyframes ventaPanelEntrada {
                from {
                    opacity: 0;
                    transform: translateY(7px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        }

        @media (max-width: 767.98px) {
            .venta-form-scroll {
                padding: 14px 14px 10px !important;
            }
            .venta-form-footer {
                grid-template-columns: minmax(0, 40%) minmax(0, 60%);
                align-items: center;
                gap: 0;
                padding: 10px 14px;
                background: #ffffff !important;
            }

            .venta-footer-total {
                align-items: flex-start;
                justify-content: center;
                padding-right: 10px;
            }

            .venta-procesar-btn {
                width: 100%;
                min-width: 0;
            }
        }

        @media (max-width: 575.98px) {
            .venta-mobile-switch-wrap {
                width: calc(100vw - 22px);
                bottom: calc(10px + env(safe-area-inset-bottom, 0px));
            }

            .venta-mobile-switch-btn {
                min-height: 46px;
                padding-right: 11px;
                padding-left: 11px;
                font-size: .88rem;
                font-weight: 400;
            }

            .venta-pedido-header {
                align-items: flex-start;
            }

            .venta-pedido-total-cabecera strong {
                font-size: 1.05rem;
            }

            .venta-cobro-row > .col-md-6:first-child {
                margin-bottom: 8px;
            }
        }

        /* =========================================================
           AJUSTE COMPACTO PARA IPHONE 13 Y MÓVILES SIMILARES
           Mantiene 16px en campos para impedir el zoom automático de iOS,
           pero reduce alturas, espacios, cabeceras y elementos secundarios.
        ========================================================== */
        @media (max-width: 430px) {
            body.venta-switch-responsive-activo {
                padding-bottom: calc(78px + env(safe-area-inset-bottom, 0px));
            }

            .main-content .section {
                padding-right: 8px;
                padding-left: 8px;
            }

            .main-content .section .section-body {
                padding-right: 0;
                padding-left: 0;
            }

            .venta-mobile-switch-wrap {
                width: calc(100vw - 18px);
                bottom: calc(7px + env(safe-area-inset-bottom, 0px));
            }

            .venta-mobile-switch {
                padding: 3px;
                border-color: #dce6df;
                box-shadow: 0 8px 22px rgba(15, 23, 42, .14);
            }

            .venta-mobile-switch-slider {
                top: 3px;
                bottom: 3px;
                left: 3px;
                width: calc(50% - 3px);
                box-shadow: 0 4px 10px rgba(82, 184, 72, .2);
            }

            .venta-mobile-switch-btn {
                min-height: 40px;
                padding: 7px 9px;
                font-size: 13px;
                font-weight: 400;
            }

            .venta-panel-card {
                margin-bottom: 10px;
                border-radius: 12px;
                box-shadow: 0 5px 14px rgba(15, 23, 42, .055);
            }

            .venta-panel-header {
                min-height: 46px;
                padding: 8px 11px;
            }

            .venta-panel-header h4 {
                font-size: 14px;
                font-weight: 700;
            }

            .venta-pedido-contador {
                margin-top: 1px;
                font-size: 10px;
                font-weight: 500;
            }

            .venta-pedido-total-cabecera span {
                margin-bottom: 2px;
                font-size: 9px;
                font-weight: 600;
            }

            .venta-pedido-total-cabecera strong {
                font-size: 15px;
                font-weight: 700;
            }

            .venta-form-scroll {
                padding: 10px 10px 7px !important;
            }

            .venta-form-scroll > .row,
            .venta-form-scroll > #bloque_credito {
                margin-right: -4px;
                margin-left: -4px;
            }

            .venta-form-scroll > .row > [class*="col-"],
            .venta-form-scroll > #bloque_credito > [class*="col-"] {
                padding-right: 4px;
                padding-left: 4px;
            }

            .venta-form-scroll > .row.mb-4,
            .venta-form-scroll > #bloque_credito.mb-4,
            .venta-form-scroll > .row.mb-5 {
                margin-bottom: 8px !important;
            }

            .venta-form-scroll label,
            .venta-form-scroll .form-label {
                margin-bottom: 3px !important;
                font-size: 11px;
                font-weight: 500;
                line-height: 1.2;
            }

            /* 16px evita que Safari amplíe la pantalla al enfocar campos. */
            .venta-form-scroll input.form-control,
            .venta-form-scroll select.form-control,
            .venta-form-scroll select.form-select,
            .venta-form-scroll textarea.form-control {
                min-height: 36px;
                height: 36px;
                padding: 6px 9px;
                border-radius: 8px;
                font-size: 16px;
                line-height: 1.15;
            }

            .venta-form-scroll textarea.form-control {
                height: 52px;
                min-height: 52px;
                padding-top: 7px;
                padding-bottom: 7px;
            }

            .venta-form-scroll .input-group-text,
            .venta-form-scroll .input-group .btn {
                min-height: 36px;
                height: 36px;
                padding: 5px 10px;
                border-radius: 8px;
                font-size: 13px;
            }

            .venta-form-scroll .input-group-prepend .input-group-text,
            .venta-form-scroll .input-group-prepend .btn {
                border-radius: 8px 0 0 8px;
            }

            .venta-form-scroll .input-group-append .input-group-text,
            .venta-form-scroll .input-group-append .btn {
                border-radius: 0 8px 8px 0;
            }

            #nombre_cliente,
            #mensajeModoEnvio,
            #bloque_credito small,
            #bloque_pago_mixto small,
            .buscador-pedido-ayuda {
                font-size: 10px;
                line-height: 1.22;
            }

            #nombre_cliente {
                min-height: 12px;
                margin-top: 3px !important;
            }

            .venta-descuento-row {
                margin-bottom: 7px !important;
            }

            .venta-descuento-row > .col-12 > .d-flex {
                min-height: 36px;
                padding: 4px 8px;
                border-radius: 9px;
            }

            .venta-descuento-row .custom-switch-description {
                font-size: 11px;
                font-weight: 500;
            }

            #descuentoPorcentaje {
                width: 68px !important;
                min-height: 31px !important;
                height: 31px !important;
                margin-left: 10px !important;
                padding: 4px 6px !important;
                font-size: 16px !important;
                font-weight: 500;
            }

            .venta-cobro-row {
                margin-bottom: 7px !important;
                padding: 6px 4px;
                border-radius: 10px;
            }

            .venta-cobro-row > .col-md-6:first-child {
                margin-bottom: 5px;
            }

            .venta-cobro-row label {
                margin-bottom: 2px !important;
                font-size: 10px;
            }

            .venta-cobro-row .total-display {
                min-height: 34px !important;
                height: 34px !important;
                font-size: 16px !important;
                font-weight: 600;
            }

            #bloque_pago_mixto {
                margin-bottom: 8px !important;
                padding: 8px;
                border-radius: 10px;
            }
            #mensajeModoEnvio {
                min-height: 22px;
                margin-top: 3px !important;
            }

            .venta-form-footer {
                bottom: calc(66px + env(safe-area-inset-bottom, 0px));
                grid-template-columns: minmax(0, 40%) minmax(0, 60%);
                gap: 0;
                padding: 7px 10px;
                border-radius: 0 0 12px 12px;
                background: #ffffff !important;
                background-color: #ffffff !important;
            }

            .venta-footer-total-label {
                font-size: 9px;
                font-weight: 600;
            }

            .venta-footer-total-monto {
                font-size: 23px;
                font-weight: 700;
            }

            #btnProcesarVenta.venta-procesar-btn {
                min-height: 40px;
                padding: 7px 12px;
                border-radius: 9px;
                font-size: 13px;
                font-weight: 400;
            }

            .venta-pedido-body {
                padding: 10px 10px 5px;
            }

            .buscador-pedido-wrap {
                margin-bottom: 8px !important;
            }

            .buscador-pedido-input-group,
            .buscador-pedido-input-group .input-group-text,
            .buscador-pedido-input-group .form-control,
            .buscador-pedido-input-group .btn {
                min-height: 40px;
            }

            .buscador-pedido-input-group .form-control {
                font-size: 16px;
            }

            #btnLimpiarBusquedaPedido {
                min-width: 40px;
                padding: 0 10px;
                font-size: 21px;
            }

            #detallesCards .filas {
                margin-bottom: 8px !important;
                border-radius: 10px;
            }

            #detallesCards .filas .card-body {
                padding: 10px !important;
                font-size: 12px;
                line-height: 1.25;
            }

            #detallesCards .filas .fw-bold.fs-6 {
                margin-bottom: 3px !important;
                font-size: 13px !important;
            }

            #detallesCards .filas .small {
                font-size: 10px;
            }

            #detallesCards .filas .btn-sm {
                min-width: 32px;
                min-height: 30px;
                padding: 3px 7px !important;
                border-radius: 7px;
            }

            .venta-pedido-footer {
                min-height: 54px;
                padding: 7px 10px !important;
            }

            .venta-pedido-footer .btn {
                width: 48px !important;
                height: 40px !important;
                border-radius: 11px !important;
            }

            .venta-pedido-footer .btn i {
                font-size: 1.2rem !important;
            }

            #pedidoVacio i {
                margin-bottom: 8px !important;
                font-size: 2.8rem !important;
            }

            #pedidoVacio .fw-semibold {
                font-size: 13px !important;
            }

            #pedidoVacio .mt-1 {
                font-size: 11px !important;
            }
        }

        /* =========================================================
           BUSCADOR RÁPIDO DENTRO DE PEDIDO ACTUAL
        ========================================================== */
        .buscador-pedido-wrap {
            position: relative;
            z-index: 60;
        }

        .buscador-pedido-label {
            display: block;
            margin-bottom: 8px;
            color: #26352d;
            font-size: .9rem;
            font-weight: 800;
        }

        .buscador-pedido-input-group {
            border-radius: 13px;
            box-shadow: 0 5px 16px rgba(15, 23, 42, .055);
        }

        .buscador-pedido-input-group .input-group-text,
        .buscador-pedido-input-group .form-control,
        .buscador-pedido-input-group .btn {
            min-height: 48px;
            border-color: #d8e1dc;
        }

        .buscador-pedido-input-group .input-group-text {
            border-radius: 13px 0 0 13px;
            color: #52a763;
        }

        .buscador-pedido-input-group .form-control:focus {
            border-color: #70b985;
            box-shadow: none;
        }

        .buscador-pedido-input-group .btn {
            border-radius: 0 13px 13px 0;
        }

        #btnLimpiarBusquedaPedido {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            padding: 0 14px;
            color: #34443b !important;
            background: #eef3f0 !important;
            border-color: #d8e1dc !important;
            font-size: 1.55rem;
            font-weight: 700;
            line-height: 1;
            opacity: 1;
        }

        #btnLimpiarBusquedaPedido span {
            display: block;
            color: inherit !important;
            line-height: 1;
            transform: translateY(-1px);
        }

        #btnLimpiarBusquedaPedido:hover,
        #btnLimpiarBusquedaPedido:focus {
            color: #ffffff !important;
            background: #3f8f52 !important;
            border-color: #3f8f52 !important;
            box-shadow: 0 0 0 .18rem rgba(63, 143, 82, .16);
        }

        #btnLimpiarBusquedaPedido:active {
            color: #ffffff !important;
            background: #327442 !important;
            border-color: #327442 !important;
        }

        .buscador-pedido-ayuda {
            display: block;
            min-height: 19px;
            margin-top: 7px;
            color: #7c8b83;
            font-size: .78rem;
        }

        .resultados-busqueda-pedido {
            position: absolute;
            top: calc(100% - 18px);
            left: 0;
            right: 0;
            z-index: 100;
            max-height: 360px;
            overflow-y: auto;
            border: 1px solid #dfe7e2;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 18px 42px rgba(15, 23, 42, .16);
        }

        .resultado-producto-pedido {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 0;
            border-bottom: 1px solid #edf1ee;
            color: inherit;
            background: #ffffff;
            text-align: left;
            cursor: pointer;
            transition: background .15s ease;
        }

        .resultado-producto-pedido:last-child {
            border-bottom: 0;
        }

        .resultado-producto-pedido:hover,
        .resultado-producto-pedido:focus,
        .resultado-producto-pedido.active {
            outline: none;
            background: #f1faf4;
        }

        .resultado-producto-icono {
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            color: #2c914a;
            background: #eaf7ee;
            font-size: 1.15rem;
        }

        .resultado-producto-info {
            min-width: 0;
            flex: 1 1 auto;
        }

        .resultado-producto-nombre {
            overflow: hidden;
            color: #26352d;
            font-size: .9rem;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .resultado-producto-meta {
            overflow: hidden;
            margin-top: 3px;
            color: #7b8a82;
            font-size: .75rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .resultado-producto-precio {
            flex: 0 0 auto;
            color: #237b3e;
            font-size: .93rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .resultado-producto-agotado {
            color: #a64444;
            background: #fff7f7;
        }

        .resultado-producto-vacio {
            padding: 22px 16px;
            color: #7b8a82;
            font-size: .84rem;
            text-align: center;
        }

        @media (max-width: 575.98px) {
            .resultado-producto-icono {
                display: none;
            }

            .resultado-producto-pedido {
                gap: 8px;
                padding: 11px 12px;
            }

            .resultado-producto-precio {
                font-size: .84rem;
            }
        }

        /* =========================================================
           MODAL DE PRODUCTOS: ALTURA CONTROLADA Y DISEÑO COMPACTO
        ========================================================== */
        #modalProductos {
            padding-right: 0 !important;
        }

        #modalProductos .modal-productos-dialog {
            width: calc(100% - 12px);
            max-width: 1440px;
            margin: 6px auto;
        }

        #modalProductos .modal-productos-content {
            height: calc(100vh - 12px);
            height: calc(100dvh - 12px);
            max-height: calc(100vh - 12px);
            max-height: calc(100dvh - 12px);
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-radius: 20px;
            background: #f7f9f8;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
        }

        #modalProductos .modal-productos-header {
            flex: 0 0 auto;
            min-height: 72px;
            padding: 16px 22px;
            background: #ffffff;
            border-bottom: 1px solid #e7ece9;
        }

        #modalProductos .modal-productos-title {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #26352d;
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: .01em;
        }

        #modalProductos .modal-productos-title-icon {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            color: #278c46;
            background: #eaf7ee;
            font-size: 1.25rem;
        }

        #modalProductos .modal-productos-subtitle {
            margin-top: 2px;
            color: #7b8a82;
            font-size: .82rem;
            font-weight: 500;
        }

        #modalProductos .modal-productos-close {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 0;
            border-radius: 12px;
            color: #66756d;
            background: #f2f5f3;
            font-size: 1.5rem;
            opacity: 1;
        }

        #modalProductos .modal-productos-close:hover {
            color: #26352d;
            background: #e8eeea;
        }

        #modalProductos .modal-productos-tools {
            flex: 0 0 auto;
            background: #ffffff;
            border-bottom: 1px solid #e4eae6;
        }

        #modalProductos .modal-productos-categorias {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr) 38px;
            align-items: center;
            gap: 8px;
            padding: 13px 18px 10px;
        }

        #modalProductos .categoria-nav-btn {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 1px solid #dfe7e2;
            border-radius: 11px;
            color: #65756c;
            background: #f7f9f8;
        }

        #modalProductos .categoria-nav-btn:hover {
            color: #278c46;
            border-color: #bcd9c5;
            background: #edf8f0;
        }

        #modalProductos .categorias-viewport {
            min-width: 0;
            overflow: hidden;
        }

        #modalProductos #catList {
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            width: 100%;
            margin: 0;
            padding: 1px 0 7px;
            list-style: none;
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: thin;
            scrollbar-color: #cbd8d0 transparent;
        }

        #modalProductos #catList::-webkit-scrollbar {
            height: 5px;
        }

        #modalProductos #catList::-webkit-scrollbar-thumb {
            border-radius: 99px;
            background: #cbd8d0;
        }

        #modalProductos .categoria-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 38px;
            padding: 8px 15px;
            border: 1px solid #dfe7e2;
            border-radius: 999px;
            color: #526259;
            background: #f8faf9;
            font-size: .86rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
            text-decoration: none !important;
            transition: .16s ease;
        }

        #modalProductos .categoria-chip:hover {
            color: #257d40;
            border-color: #b9d9c3;
            background: #eff8f2;
        }

        #modalProductos .categoria-chip.active {
            color: #ffffff;
            border-color: #389c56;
            background: #389c56;
            box-shadow: 0 6px 14px rgba(56, 156, 86, .18);
        }

        #modalProductos .modal-productos-buscador {
            padding: 8px 22px 16px;
        }

        #modalProductos .buscador-productos-box {
            flex: 1 1 520px;
            max-width: 720px;
        }

        #modalProductos .buscador-productos-box .input-group-text,
        #modalProductos .buscador-productos-box .form-control,
        #modalProductos .buscador-productos-box .btn {
            min-height: 48px;
            border-color: #d8e1dc;
        }

        #modalProductos .buscador-productos-box .input-group-text {
            border-radius: 13px 0 0 13px;
        }

        #modalProductos .buscador-productos-box .form-control:focus {
            border-color: #70b985;
            box-shadow: none;
        }

        #modalProductos .buscador-productos-box .btn {
            border-radius: 0 13px 13px 0;
        }

        #modalProductos .busqueda-ayuda {
            display: flex;
            align-items: center;
            gap: 7px;
            min-height: 20px;
            margin-top: 7px;
            color: #7c8b83;
            font-size: .78rem;
        }

        #modalProductos .productos-modal-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 18px 20px 8px;
            background: #f7f9f8;
        }

        /*
         * GRID RESPONSIVE DE PRODUCTOS
         * No depende de las columnas de Bootstrap. De esta forma las tarjetas
         * mantienen el mismo diseño en escritorio, tablet y móvil.
         */
        #modalProductos #productosList {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
            width: 100%;
            margin: 0 !important;
        }

        #modalProductos #productosList > .col-12:not(.producto-item) {
            grid-column: 1 / -1;
            width: 100%;
            max-width: none;
            padding: 0;
        }

        #modalProductos .producto-item {
            width: 100% !important;
            max-width: none !important;
            min-width: 0;
            flex: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        #modalProductos .producto-card {
            border: 1px solid #e3e9e5 !important;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(15, 23, 42, .055) !important;
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        }

        #modalProductos .producto-card:hover,
        #modalProductos .producto-card:focus {
            transform: translateY(-2px);
            border-color: #aed4ba !important;
            box-shadow: 0 12px 24px rgba(15, 23, 42, .09) !important;
            outline: none;
        }

        #modalProductos .producto-card .card-body {
            padding: 16px;
        }

        #modalProductos .producto-imagen {
            width: 72px;
            height: 72px;
            flex: 0 0 72px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            overflow: hidden;
            background: #f0f4f1;
        }

        #modalProductos .producto-imagen img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        #modalProductos .producto-nombre {
            min-height: 42px;
            color: #25342c;
            font-size: .98rem;
            font-weight: 800;
            line-height: 1.35;
        }

        #modalProductos .producto-codigo {
            max-width: 100%;
            overflow: hidden;
            color: #77867e;
            font-size: .77rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #modalProductos .producto-precio {
            color: #237b3e;
            font-size: 1.08rem;
            font-weight: 800;
        }

        #modalProductos .producto-stock {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            color: #496158;
            background: #eef3f0;
            font-size: .73rem;
            font-weight: 700;
        }

        #modalProductos .modal-productos-footer {
            flex: 0 0 auto;
            min-height: 70px;
            padding: 11px 20px;
            border-top: 1px solid #e1e8e3;
            background: #ffffff;
        }

        #modalProductos .lector-status {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6b7b72;
            font-size: .82rem;
        }

        #modalProductos .lector-status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #52b848;
            box-shadow: 0 0 0 4px rgba(82, 184, 72, .13);
        }

        #modalProductos #formProductoRapido {
            max-height: min(54vh, 560px);
            overflow-y: auto;
            overscroll-behavior: contain;
            padding-right: 4px;
        }

        #modalProductos #formProductoRapido::-webkit-scrollbar,
        #modalProductos .productos-modal-body::-webkit-scrollbar {
            width: 8px;
        }

        #modalProductos #formProductoRapido::-webkit-scrollbar-thumb,
        #modalProductos .productos-modal-body::-webkit-scrollbar-thumb {
            border-radius: 99px;
            background: #cbd7d0;
        }

        .scanner-feedback {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 99999;
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 11px 15px;
            border: 1px solid #cce4d3;
            border-radius: 13px;
            color: #2d6540;
            background: #f0faf3;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .16);
            font-size: .84rem;
            font-weight: 700;
        }

        .scanner-feedback i {
            font-size: 1.05rem;
        }

        .scanner-capture-input {
            position: fixed !important;
            left: -9999px !important;
            top: -9999px !important;
            width: 1px !important;
            height: 1px !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        @media (max-width: 991.98px) {
            /*
             * En escritorio .buscador-productos-box usa flex-basis:520px como
             * ancho. Al pasar a flex-direction:column ese mismo valor se
             * convierte en ALTURA y empuja los productos fuera de la vista.
             */
            #modalProductos .buscador-productos-box {
                flex: 0 0 auto !important;
                width: 100%;
                max-width: none;
                min-height: 0;
            }

            #modalProductos #btnMostrarProductoRapido {
                flex: 0 0 auto;
                width: 100%;
            }

            #modalProductos #productosList {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }
        }

        @media (max-width: 575.98px) {
            #modalProductos #productosList {
                grid-template-columns: minmax(0, 1fr);
                gap: 10px;
            }

            #modalProductos .producto-card .card-body {
                padding: 13px;
            }

            #modalProductos .producto-imagen {
                width: 58px;
                height: 58px;
                flex-basis: 58px;
                border-radius: 11px;
            }

            #modalProductos .producto-nombre {
                min-height: 0;
                display: -webkit-box;
                overflow: hidden;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
                font-size: .92rem;
            }

            #modalProductos .producto-precio {
                font-size: 1rem;
            }

            #modalProductos .producto-stock {
                padding: 4px 8px;
                font-size: .69rem;
            }
        }

        @media (max-width: 767.98px) {
            #modalProductos .modal-productos-dialog {
                width: 100%;
                max-width: none;
                height: 100%;
                margin: 0;
            }

            #modalProductos .modal-productos-content {
                height: 100vh;
                height: 100dvh;
                max-height: 100vh;
                max-height: 100dvh;
                min-height: 0;
                border-radius: 0;
            }

            #modalProductos .modal-productos-tools {
                flex: 0 0 auto;
                min-height: 0;
            }

            #modalProductos .modal-productos-buscador > .d-flex {
                height: auto !important;
                min-height: 0;
            }

            #modalProductos .modal-productos-header {
                min-height: 64px;
                padding: 12px 15px;
            }

            #modalProductos .modal-productos-subtitle {
                display: none;
            }

            #modalProductos .modal-productos-categorias {
                grid-template-columns: 32px minmax(0, 1fr) 32px;
                gap: 5px;
                padding: 10px 10px 7px;
            }

            #modalProductos .categoria-nav-btn {
                width: 32px;
                height: 32px;
            }

            #modalProductos .modal-productos-buscador {
                padding: 7px 12px 12px;
            }

            #modalProductos .productos-modal-body {
                flex: 1 1 0;
                min-height: 0;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                padding: 12px 12px 4px;
            }

            #modalProductos .modal-productos-footer {
                padding: 10px 12px;
            }

            #modalProductos .lector-status {
                display: none;
            }
        }

        /* =========================================================
           POS Y MODAL APROVECHANDO EL ANCHO REAL DE MÓVIL/TABLET
           Reduce márgenes duplicados de Stisla + Bootstrap sin
           dejar los controles pegados completamente al borde.
        ========================================================== */
        @media (max-width: 1199.98px) {
            .venta-pos-main-content {
                padding-top: 62px !important;
                padding-right: 8px !important;
            }

            .venta-pos-main-content .venta-pos-section,
            .venta-pos-main-content .section-body {
                width: 100%;
                margin: 0 !important;
                padding-right: 0 !important;
                padding-left: 0 !important;
            }
        }

        /*
         * Entre 1025 y 1199 px el sidebar sigue siendo lateral, no flotante.
         * Se reserva su ancho tanto abierto como colapsado para impedir que
         * el menú cubra el POS al pulsar el botón de navegación.
         */
        @media (min-width: 1025px) and (max-width: 1199.98px) {
            body.sidebar-mini .venta-pos-main-content {
                padding-left: 78px !important;
            }

            body:not(.sidebar-mini) .venta-pos-main-content {
                padding-left: 258px !important;
            }
        }

        /* En móvil Stisla usa el menú como drawer y el contenido ocupa el ancho. */
        @media (max-width: 1024px) {
            .venta-pos-main-content {
                padding-left: 8px !important;
            }
        }

        @media (max-width: 767.98px) {
            .venta-pos-main-content {
                padding-top: 60px !important;
                padding-right: 4px !important;
                padding-left: 4px !important;
            }

            .venta-pos-main-content .venta-panel-card {
                border-radius: 10px;
            }

            /* Cabecera más baja y cercana a los bordes. */
            #modalProductos .modal-productos-header {
                min-height: 52px;
                padding: 7px 8px;
            }

            #modalProductos .modal-productos-title-icon {
                width: 34px;
                height: 34px;
                flex: 0 0 34px;
                border-radius: 10px;
                font-size: 1rem;
            }

            #modalProductos .modal-productos-header .ml-3 {
                margin-left: 8px !important;
            }

            #modalProductos .modal-productos-title {
                font-size: 1rem;
                line-height: 1.15;
            }

            #modalProductos .modal-productos-close {
                width: 34px;
                height: 34px;
                flex: 0 0 34px;
                border-radius: 9px;
                font-size: 1.25rem;
            }

            /* Categorías: menos altura y menos espacio lateral. */
            #modalProductos .modal-productos-categorias {
                grid-template-columns: 28px minmax(0, 1fr) 28px;
                gap: 3px;
                padding: 5px 4px 3px;
            }

            #modalProductos .categoria-nav-btn {
                width: 28px;
                height: 28px;
                border-radius: 8px;
                font-size: .72rem;
            }

            #modalProductos #catList {
                gap: 5px;
                padding: 0 0 3px;
                scrollbar-width: none;
            }

            #modalProductos #catList::-webkit-scrollbar {
                display: none;
            }

            #modalProductos .categoria-chip {
                min-height: 32px;
                gap: 0;
                padding: 6px 10px;
                font-size: .75rem;
                font-weight: 600;
            }

            #modalProductos .categoria-chip i {
                display: none;
            }

            /* Buscador y registro rápido en una misma fila. */
            #modalProductos .modal-productos-buscador {
                padding: 4px 6px 6px;
            }

            #modalProductos .modal-productos-buscador > .d-flex {
                min-width: 0;
                flex-direction: row !important;
                align-items: stretch !important;
                gap: 6px !important;
            }

            #modalProductos .buscador-productos-box {
                width: auto !important;
                min-width: 0;
                flex: 1 1 auto !important;
            }

            #modalProductos .buscador-productos-box .input-group-text,
            #modalProductos .buscador-productos-box .form-control,
            #modalProductos .buscador-productos-box .btn {
                min-height: 42px;
                height: 42px;
            }

            #modalProductos .buscador-productos-box .form-control {
                min-width: 0;
                padding-right: 7px;
                padding-left: 7px;
                font-size: 16px;
            }

            #modalProductos .buscador-productos-box .input-group-text {
                padding-right: 8px;
                padding-left: 9px;
                border-radius: 10px 0 0 10px;
            }

            #modalProductos .buscador-productos-box .btn {
                width: 40px;
                padding: 0;
                border-radius: 0 10px 10px 0;
            }

            #modalProductos .busqueda-ayuda {
                display: none !important;
            }

            #modalProductos #btnMostrarProductoRapido {
                width: 42px !important;
                min-width: 42px;
                max-width: 42px;
                min-height: 42px !important;
                padding: 0 !important;
                border-radius: 10px !important;
                overflow: hidden;
                font-size: 0;
                white-space: nowrap;
            }

            #modalProductos #btnMostrarProductoRapido i {
                margin: 0 !important;
                font-size: 1.05rem;
            }

            /* El área principal usa casi todo el ancho disponible. */
            #modalProductos .productos-modal-body {
                padding: 6px 5px 2px;
            }

            #modalProductos #productosList {
                gap: 6px;
            }

            #modalProductos .producto-card {
                border-radius: 11px;
            }

            #modalProductos .producto-card .card-body {
                padding: 9px;
            }

            #modalProductos .producto-imagen {
                width: 50px;
                height: 50px;
                flex-basis: 50px;
                border-radius: 9px;
            }

            #modalProductos .producto-nombre {
                font-size: .83rem;
                line-height: 1.25;
            }

            #modalProductos .producto-codigo {
                font-size: .67rem;
            }

            #modalProductos .producto-precio {
                font-size: .9rem;
            }

            #modalProductos .producto-stock {
                padding: 3px 7px;
                font-size: .64rem;
            }

            #modalProductos .modal-productos-footer {
                min-height: 48px;
                padding: 5px 6px;
            }

            #modalProductos #btnEscanearModalFooter {
                min-height: 38px !important;
                padding-right: 12px !important;
                padding-left: 12px !important;
                border-radius: 9px !important;
                font-size: .78rem;
            }

            #modalProductos #formProductoRapido {
                max-height: calc(100dvh - 108px);
            }
        }

        @media (max-width: 430px) {
            .venta-pos-main-content {
                padding-right: 2px !important;
                padding-left: 2px !important;
            }

            .venta-pos-main-content .venta-panel-card {
                border-radius: 8px;
            }

            #modalProductos .modal-productos-header {
                padding-right: 6px;
                padding-left: 6px;
            }

            #modalProductos .modal-productos-categorias {
                padding-right: 2px;
                padding-left: 2px;
            }

            #modalProductos .modal-productos-buscador {
                padding-right: 4px;
                padding-left: 4px;
            }

            #modalProductos .productos-modal-body {
                padding-right: 3px;
                padding-left: 3px;
            }
        }

        /* =========================================================
           MODAL POS SIN INTERFERENCIA DE LA BARRA SUPERIOR
           ========================================================= */
        body.pos-navbar-layout.modal-open {
            overflow: hidden !important;
        }

        @media (max-width: 767.98px) {
            #modalProductos {
                width: 100vw;
                height: 100dvh;
                overflow: hidden;
            }

            #modalProductos .modal-productos-content {
                width: 100%;
            }

            #modalProductos .modal-productos-footer {
                justify-content: flex-end !important;
                min-height: 44px;
            }

            #modalProductos #btnEscanearModalFooter {
                min-height: 36px !important;
            }
        }


        /* =========================================================
           TIPO DE OPERACIÓN SUNAT
           Vista compacta: la tributación detallada continúa calculándose
           internamente, pero no ocupa espacio en el formulario.
        ========================================================== */
        .venta-tipo-operacion-row {
            margin-bottom: 11px !important;
        }

        .venta-tipo-operacion-row > .col-12 {
            padding-right: 6px;
            padding-left: 6px;
        }

        #ayudaTipoOperacionSunat {
            display: block;
            min-height: 15px;
            margin-top: 4px;
            color: #7a8780 !important;
            font-size: .67rem;
            line-height: 1.3;
        }

        @media (max-width: 430px) {
            .venta-tipo-operacion-row {
                margin-bottom: 8px !important;
            }

            #ayudaTipoOperacionSunat {
                min-height: 12px;
                margin-top: 3px;
                font-size: 10px;
                line-height: 1.22;
            }
        }

        .venta-product-tax-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
            padding: 3px 7px;
            border: 1px solid #dfe5ea;
            border-radius: 999px;
            color: #657080;
            background: #f8fafb;
            font-size: .62rem;
            font-weight: 700;
        }

        .venta-product-tax-badge.tax-10 {
            border-color: #cde7d8;
            color: #237a4a;
            background: #f2faf5;
        }

        .venta-product-tax-badge.tax-20,
        .venta-product-tax-badge.tax-30,
        .venta-product-tax-badge.tax-40 {
            border-color: #d9def2;
            color: #5360a9;
            background: #f6f7fd;
        }

        @media (max-width: 991.98px) {
            .venta-tributaria-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .venta-tributaria-summary {
                grid-template-columns: 1fr 1fr;
            }

            .venta-tax-igv {
                grid-column: 1 / -1;
            }
        }

        /* =========================================================
           CAPA VISUAL TAILWIND + FOOTER FLOTANTE CENTRAL
           Mantiene compatibilidad con Bootstrap/Stisla y usa el
           verde corporativo de TiquePOS como color de acción.
        ========================================================== */
        :root {
            --tique-primary: #52b848;
            --tique-primary-hover: #469f3e;
            --tique-primary-dark: #357f31;
            --tique-border: #dbe5df;
            --tique-border-strong: #cbd9d0;
            --tique-surface: #ffffff;
            --tique-surface-soft: #f7faf8;
            --tique-text: #26332c;
            --tique-muted: #6f7d75;
        }

        .venta-pos-main-content {
            padding-bottom: 108px !important;
        }

        .venta-panel-card {
            border-color: var(--tique-border);
            box-shadow: 0 12px 32px rgba(15, 23, 42, .07);
        }

        .venta-form-scroll .form-control,
        .venta-form-scroll .form-select,
        #buscarProductoPedido,
        #modalProductos .form-control,
        #modalProductos .form-select {
            color: var(--tique-text);
            border-color: var(--tique-border);
            background-color: #ffffff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .025);
            transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
        }

        .venta-form-scroll .form-control:hover,
        .venta-form-scroll .form-select:hover,
        #buscarProductoPedido:hover,
        #modalProductos .form-control:hover,
        #modalProductos .form-select:hover {
            border-color: var(--tique-border-strong);
        }

        .venta-form-scroll .form-control:focus,
        .venta-form-scroll .form-select:focus,
        #buscarProductoPedido:focus,
        #modalProductos .form-control:focus,
        #modalProductos .form-select:focus {
            border-color: var(--tique-primary) !important;
            box-shadow: 0 0 0 3px rgba(82, 184, 72, .12) !important;
        }

        .venta-form-scroll .form-control[readonly],
        .venta-form-scroll .form-control:disabled,
        .venta-form-scroll .form-select:disabled {
            color: #66736c;
            background: #f2f5f3;
            cursor: not-allowed;
        }

        #btnConsultarCliente,
        #btnAgregarPagoMixto {
            color: #347b40 !important;
            border-color: #bcd8c3 !important;
            background: #f4fbf5 !important;
        }

        #btnConsultarCliente:hover,
        #btnConsultarCliente:focus,
        #btnAgregarPagoMixto:hover,
        #btnAgregarPagoMixto:focus {
            color: #ffffff !important;
            border-color: var(--tique-primary) !important;
            background: var(--tique-primary) !important;
            box-shadow: 0 0 0 3px rgba(82, 184, 72, .12) !important;
        }

        .venta-cola-nueva,
        #btnActivarEscaner,
        #btnAbrirModal,
        #modalProductos #btnMostrarProductoRapido,
        #modalProductos #btnEscanearModalFooter {
            border-color: var(--tique-primary) !important;
            background: var(--tique-primary) !important;
        }

        .venta-cola-nueva:hover,
        #btnActivarEscaner:hover,
        #btnAbrirModal:hover,
        #modalProductos #btnMostrarProductoRapido:hover,
        #modalProductos #btnEscanearModalFooter:hover {
            border-color: var(--tique-primary-hover) !important;
            background: var(--tique-primary-hover) !important;
        }

        /* Footer principal: fijo, flotante y centrado en la ventana. */
        #ventaProcesarFooter.venta-form-footer {
            position: fixed !important;
            left: 50% !important;
            right: auto !important;
            bottom: 18px !important;
            z-index: 1028 !important;
            width: min(680px, calc(100vw - 36px));
            min-height: 72px;
            grid-template-columns: minmax(0, 40%) minmax(0, 60%);
            padding: 10px 12px 10px 18px;
            border: 1px solid var(--tique-border) !important;
            border-radius: 18px !important;
            background: #ffffff !important;
            background-color: #ffffff !important;
            box-shadow: 0 18px 46px rgba(15, 23, 42, .18) !important;
            transform: translateX(-50%) !important;
            opacity: 1 !important;
            isolation: isolate;
        }

        #ventaProcesarFooter .venta-footer-total {
            padding-right: 16px;
        }

        #ventaProcesarFooter .venta-footer-total-label {
            color: #748078;
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .06em;
        }

        #ventaProcesarFooter .venta-footer-total-monto {
            color: #1f2c24;
            font-size: clamp(1.55rem, 2.2vw, 1.95rem);
            font-weight: 700;
        }

        #ventaProcesarFooter #btnProcesarVenta {
            min-height: 50px;
            border-radius: 13px;
            box-shadow: 0 8px 20px rgba(82, 184, 72, .24);
        }

        #ventaProcesarFooter #btnProcesarVenta:hover,
        #ventaProcesarFooter #btnProcesarVenta:focus {
            box-shadow: 0 10px 24px rgba(70, 159, 62, .27) !important;
            transform: translateY(-1px);
        }

        #ventaProcesarFooter #btnProcesarVenta:active {
            transform: translateY(0);
        }

        @media (max-width: 1199.98px) {
            body.venta-switch-responsive-activo {
                padding-bottom: calc(158px + env(safe-area-inset-bottom, 0px)) !important;
            }

            .venta-pos-main-content {
                padding-bottom: calc(164px + env(safe-area-inset-bottom, 0px)) !important;
            }

            #ventaProcesarFooter.venta-form-footer {
                bottom: calc(74px + env(safe-area-inset-bottom, 0px)) !important;
                width: min(620px, calc(100vw - 24px));
                min-height: 66px;
                padding: 8px 10px 8px 14px;
                border-radius: 16px !important;
            }

            .venta-mobile-switch-wrap {
                z-index: 1029;
            }
        }

        @media (max-width: 575.98px) {
            #ventaProcesarFooter.venta-form-footer {
                bottom: calc(62px + env(safe-area-inset-bottom, 0px)) !important;
                width: calc(100vw - 18px);
                min-height: 58px;
                grid-template-columns: minmax(0, 39%) minmax(0, 61%);
                padding: 6px 7px 6px 11px;
                border-radius: 14px !important;
                box-shadow: 0 12px 30px rgba(15, 23, 42, .16) !important;
            }

            #ventaProcesarFooter .venta-footer-total {
                padding-right: 8px;
            }

            #ventaProcesarFooter .venta-footer-total-label {
                font-size: 9px;
                font-weight: 500;
            }

            #ventaProcesarFooter .venta-footer-total-monto {
                font-size: 21px;
                font-weight: 700;
            }

            #ventaProcesarFooter #btnProcesarVenta {
                min-height: 44px;
                padding: 7px 10px;
                border-radius: 11px;
                font-size: 13px;
            }
        }

        @media (max-width: 430px) {
            body.venta-switch-responsive-activo {
                padding-bottom: calc(142px + env(safe-area-inset-bottom, 0px)) !important;
            }

            .venta-pos-main-content {
                padding-bottom: calc(148px + env(safe-area-inset-bottom, 0px)) !important;
            }

            #ventaProcesarFooter.venta-form-footer {
                bottom: calc(56px + env(safe-area-inset-bottom, 0px)) !important;
                width: calc(100vw - 14px);
                min-height: 56px;
                padding: 5px 6px 5px 10px;
                border-radius: 13px !important;
            }

            #ventaProcesarFooter #btnProcesarVenta {
                min-height: 42px;
                border-radius: 10px;
            }
        }
    </style>

    <!-- =========================================================
         MODAL DE PRODUCTOS
    ========================================================== -->
    <div
        class="modal fade"
        id="modalProductos"
        tabindex="-1"
        role="dialog"
        aria-labelledby="modalProductosLabel"
        aria-hidden="true">

        <div
            class="modal-dialog modal-xl modal-dialog-centered modal-productos-dialog"
            role="document">

            <div class="modal-content border-0 modal-productos-content">

                <div class="modal-header modal-productos-header align-items-center">
                    <div class="d-flex align-items-center">
                        <span class="modal-productos-title-icon">
                            <i class="bi bi-box-seam"></i>
                        </span>

                        <div class="ml-3">
                            <div class="modal-productos-title" id="modalProductosLabel">
                                Seleccionar productos
                            </div>
                            <div class="modal-productos-subtitle">
                                Busca por nombre o escanea el código desde cualquier categoría.
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="close modal-productos-close"
                        data-dismiss="modal"
                        aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-productos-tools">
                    <div class="modal-productos-categorias">
                        <button
                            type="button"
                            id="catPrev"
                            class="btn categoria-nav-btn"
                            aria-label="Categorías anteriores">
                            <i class="bi bi-chevron-left"></i>
                        </button>

                        <div class="categorias-viewport">
                            <ul class="nav flex-nowrap" id="catList"></ul>
                        </div>

                        <button
                            type="button"
                            id="catNext"
                            class="btn categoria-nav-btn"
                            aria-label="Categorías siguientes">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>

                    <div class="modal-productos-buscador">
                        <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-start" style="gap:12px;">
                            <div class="buscador-productos-box">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white border-right-0">
                                            <i class="bi bi-search text-secondary"></i>
                                        </span>
                                    </div>

                                    <input
                                        type="text"
                                        class="form-control border-left-0 border-right-0"
                                        id="buscarProducto"
                                        autocomplete="off"
                                        placeholder="Nombre, SKU o código de barras..."
                                        aria-label="Buscar producto">

                                    <div class="input-group-append">
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary bg-white"
                                            id="btnEscanearDesdeModal"
                                            title="Activar lector de código">
                                            <i class="bi bi-upc-scan"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="busqueda-ayuda" id="resultadoBusquedaProducto" aria-live="polite">
                                    <i class="bi bi-info-circle"></i>
                                    La búsqueda por código de barras es global. Presiona Enter para agregar el producto exacto.
                                </div>
                            </div>

                            <button
                                type="button"
                                class="btn btn-success d-flex align-items-center justify-content-center px-4"
                                id="btnMostrarProductoRapido"
                                style="min-height:48px; border-radius:12px; white-space:nowrap;">
                                <i class="bi bi-plus-circle mr-2"></i>
                                Registrar producto nuevo
                            </button>
                        </div>

                    <!-- Formulario rápido: está fuera del formulario de venta -->
                    <style>
                        #formProductoRapido .producto-rapido-panel {
                            background:#f8faf9;
                            border:1px solid #dce7e0;
                            border-radius:16px;
                            box-shadow:0 10px 28px rgba(16, 24, 40, .06);
                            overflow:hidden;
                        }

                        #formProductoRapido .producto-rapido-cabecera {
                            background:#ffffff;
                            border-bottom:1px solid #e7ece9;
                            padding:20px 22px;
                        }

                        #formProductoRapido .producto-rapido-contenido {
                            padding:20px 22px 22px;
                        }

                        #formProductoRapido .producto-rapido-seccion {
                            background:#ffffff;
                            border:1px solid #e6ece8;
                            border-radius:14px;
                            padding:18px;
                            margin-bottom:16px;
                        }

                        #formProductoRapido .producto-rapido-titulo-seccion {
                            display:flex;
                            align-items:center;
                            gap:9px;
                            color:#1f2937;
                            font-size:.95rem;
                            font-weight:700;
                            margin-bottom:15px;
                        }

                        #formProductoRapido .producto-rapido-numero {
                            width:25px;
                            height:25px;
                            border-radius:50%;
                            background:#e8f7ec;
                            color:#238a43;
                            display:inline-flex;
                            align-items:center;
                            justify-content:center;
                            font-size:.78rem;
                            font-weight:800;
                        }

                        #formProductoRapido .producto-rapido-label {
                            display:block;
                            color:#344054 !important;
                            font-size:.88rem;
                            font-weight:700;
                            line-height:1.25;
                            margin-bottom:7px;
                        }

                        #formProductoRapido .producto-rapido-ayuda {
                            display:block;
                            color:#7a8895 !important;
                            font-size:.76rem;
                            line-height:1.35;
                            margin-top:6px;
                        }

                        #formProductoRapido .form-control,
                        #formProductoRapido .input-group-text {
                            min-height:46px;
                            border-color:#d7e0db;
                        }

                        #formProductoRapido .form-control:focus {
                            border-color:#52b848;
                            box-shadow:0 0 0 .18rem rgba(82, 184, 72, .13);
                        }

                        #formProductoRapido .producto-rapido-aviso {
                            display:flex;
                            align-items:flex-start;
                            gap:10px;
                            background:#eef8f1;
                            border:1px solid #d8eddd;
                            color:#365b40;
                            border-radius:12px;
                            padding:12px 14px;
                            font-size:.83rem;
                            line-height:1.45;
                            margin-bottom:16px;
                        }

                        #formProductoRapido .producto-rapido-resultado {
                            background:#f6f8f7;
                            border:1px dashed #cad7cf;
                            border-radius:12px;
                            padding:12px 14px;
                            min-height:52px;
                        }

                        #formProductoRapido .producto-rapido-resultado strong {
                            color:#26332b;
                        }

                        #formProductoRapido .producto-rapido-obligatorio {
                            color:#d14343;
                        }

                        @media (max-width: 767.98px) {
                            #formProductoRapido .producto-rapido-cabecera,
                            #formProductoRapido .producto-rapido-contenido {
                                padding:16px;
                            }
                        }
                    </style>

                    <form
                        id="formProductoRapido"
                        class="mt-3"
                        autocomplete="off"
                        style="display:none;">

                        <div class="producto-rapido-panel">

                            <div class="producto-rapido-cabecera d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="d-flex align-items-center mb-1" style="gap:9px;">
                                        <span class="badge badge-success px-3 py-2">
                                            REGISTRO RÁPIDO
                                        </span>

                                        <span class="fw-bold text-dark" style="font-size:1.05rem;">
                                            Producto nuevo
                                        </span>
                                    </div>

                                    <div class="text-muted" style="font-size:.84rem;">
                                        Regístralo sin salir de la venta y agrégalo inmediatamente al pedido.
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-light btn-sm"
                                    id="btnCerrarProductoRapido"
                                    aria-label="Cerrar">

                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>

                            <div class="producto-rapido-contenido">

                                <div class="producto-rapido-aviso">
                                    <i class="bi bi-info-circle-fill mt-1"></i>
                                    <div>
                                        Los campos con <span class="producto-rapido-obligatorio">*</span> son obligatorios.
                                        La <strong>cantidad disponible</strong> es todo el stock que tienes ahora;
                                        al guardar se agregará solamente <strong>1 unidad</strong> a esta venta.
                                    </div>
                                </div>

                                <div class="producto-rapido-seccion">
                                    <div class="producto-rapido-titulo-seccion">
                                        <span class="producto-rapido-numero">1</span>
                                        Identifica el producto
                                    </div>

                                    <div class="row">
                                        <div class="col-lg-8 col-md-7 mb-3">
                                            <label for="rapido_nombre" class="producto-rapido-label">
                                                Nombre que verá en la venta
                                                <span class="producto-rapido-obligatorio">*</span>
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="rapido_nombre"
                                                name="nombre"
                                                maxlength="100"
                                                required
                                                placeholder="Ej.: Agua mineral 625 ml">

                                            <small class="producto-rapido-ayuda">
                                                Escribe un nombre fácil de reconocer en el buscador y en el comprobante.
                                            </small>
                                        </div>

                                        <div class="col-lg-4 col-md-5 mb-3">
                                            <label for="rapido_codigo" class="producto-rapido-label">
                                                Código de barras o SKU
                                                <span class="text-muted">(opcional)</span>
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="rapido_codigo"
                                                name="codigo"
                                                maxlength="50"
                                                placeholder="Ej.: AGUA-625">

                                            <small class="producto-rapido-ayuda">
                                                Déjalo vacío para generar un código automático.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="producto-rapido-seccion">
                                    <div class="producto-rapido-titulo-seccion">
                                        <span class="producto-rapido-numero">2</span>
                                        Indica dónde y cómo se controlará
                                    </div>

                                    <div class="row">
                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <label for="rapido_idcategoria" class="producto-rapido-label">
                                                Categoría <span class="producto-rapido-obligatorio">*</span>
                                            </label>

                                            <select
                                                class="form-control form-select"
                                                id="rapido_idcategoria"
                                                name="idcategoria"
                                                required>
                                                <option value="">Cargando...</option>
                                            </select>

                                            <small class="producto-rapido-ayuda">
                                                Grupo general, por ejemplo: Polos.
                                            </small>
                                        </div>

                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <label for="rapido_idsubcategoria" class="producto-rapido-label">
                                                Subcategoría
                                            </label>

                                            <select
                                                class="form-control form-select"
                                                id="rapido_idsubcategoria"
                                                name="idsubcategoria">
                                                <option value="">Selecciona primero la categoría</option>
                                            </select>

                                            <small class="producto-rapido-ayuda">
                                                Clasificación más específica, por ejemplo: Con dibujo.
                                            </small>
                                        </div>

                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <label for="rapido_idmedida" class="producto-rapido-label">
                                                Unidad de venta <span class="producto-rapido-obligatorio">*</span>
                                            </label>

                                            <select
                                                class="form-control form-select"
                                                id="rapido_idmedida"
                                                name="idmedida"
                                                required>
                                                <option value="">Cargando...</option>
                                            </select>

                                            <small class="producto-rapido-ayuda">
                                                Para productos individuales usa Unidad (NIU).
                                            </small>
                                        </div>

                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <label for="rapido_idalmacen" class="producto-rapido-label">
                                                Almacén <span class="producto-rapido-obligatorio">*</span>
                                            </label>

                                            <select
                                                class="form-control form-select"
                                                id="rapido_idalmacen"
                                                name="idalmacen"
                                                required>
                                                <option value="">Cargando...</option>
                                            </select>

                                            <small class="producto-rapido-ayuda">
                                                Lugar donde quedará registrado el stock.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="producto-rapido-seccion mb-0">
                                    <div class="producto-rapido-titulo-seccion">
                                        <span class="producto-rapido-numero">3</span>
                                        Registra el stock y los precios
                                    </div>

                                    <div class="row">
                                        <div class="col-lg-4 col-md-4 mb-3">
                                            <label for="rapido_stock" class="producto-rapido-label">
                                                ¿Cuántas unidades tienes ahora?
                                                <span class="producto-rapido-obligatorio">*</span>
                                            </label>

                                            <input
                                                type="number"
                                                class="form-control"
                                                id="rapido_stock"
                                                name="stock"
                                                min="1"
                                                max="999999"
                                                step="1"
                                                value="1"
                                                required
                                                placeholder="Ej.: 10">

                                            <small class="producto-rapido-ayuda">
                                                Esta cantidad será el stock inicial del inventario.
                                            </small>
                                        </div>

                                        <div class="col-lg-4 col-md-4 mb-3">
                                            <label for="rapido_precio_compra" class="producto-rapido-label">
                                                ¿Cuánto te costó cada unidad?
                                                <span class="text-muted">(opcional)</span>
                                            </label>

                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">S/</span>
                                                </div>

                                                <input
                                                    type="number"
                                                    class="form-control"
                                                    id="rapido_precio_compra"
                                                    name="precio_compra"
                                                    min="0"
                                                    max="99999999.99"
                                                    step="0.01"
                                                    placeholder="Ej.: 10.00">
                                            </div>

                                            <small class="producto-rapido-ayuda">
                                                Puedes dejarlo vacío. Se registrará como S/ 0.00.
                                            </small>
                                        </div>

                                        <div class="col-lg-4 col-md-4 mb-3">
                                            <label for="rapido_precio_venta" class="producto-rapido-label">
                                                ¿A cuánto lo venderás?
                                                <span class="producto-rapido-obligatorio">*</span>
                                            </label>

                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">S/</span>
                                                </div>

                                                <input
                                                    type="number"
                                                    class="form-control"
                                                    id="rapido_precio_venta"
                                                    name="precio_venta"
                                                    min="0.01"
                                                    max="99999999.99"
                                                    step="0.01"
                                                    required
                                                    placeholder="Ej.: 15.00">
                                            </div>

                                            <small class="producto-rapido-ayuda">
                                                Este es el precio que se cobrará al cliente.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-lg-6 mb-2 mb-lg-0">
                                            <div class="producto-rapido-resultado" id="rapido_resumen_destino">
                                                <span class="text-muted">Selecciona categoría, unidad y almacén.</span>
                                            </div>
                                        </div>

                                        <div class="col-lg-6">
                                            <div class="producto-rapido-resultado" id="rapido_ganancia">
                                                <span class="text-muted">Ingresa el costo y el precio de venta para ver la ganancia.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex flex-column flex-sm-row justify-content-end mt-4" style="gap:10px;">
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary px-4"
                                        id="btnCancelarProductoRapido">
                                        Cancelar
                                    </button>

                                    <button
                                        type="submit"
                                        class="btn btn-success px-4"
                                        id="btnGuardarProductoRapido">
                                        <i class="bi bi-lightning-charge-fill mr-2"></i>
                                        Guardar producto y agregar 1 al pedido
                                    </button>
                                </div>

                            </div>
                        </div>
                    </form>

                </div>

                </div>

                <div class="modal-body productos-modal-body">
                    <div class="row" id="productosList"></div>
                </div>

                <div class="modal-footer modal-productos-footer justify-content-between">
                    <div class="lector-status">
                        <span class="lector-status-dot"></span>
                        Lector disponible en toda la pantalla de venta
                    </div>

                    <button
                        type="button"
                        class="btn btn-success d-flex align-items-center justify-content-center px-4"
                        id="btnEscanearModalFooter"
                        style="min-height:46px; border-radius:12px;">
                        <i class="bi bi-upc-scan mr-2"></i>
                        Activar lector
                    </button>
                </div>

            </div>
        </div>
    </div>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<?php
} else {
    require 'access.php';
}

require 'footer.php';
?>

<script src="Views/modules/scripts/generaldata.js"></script>
<?php
$rutaNewsaleJs = __DIR__ . '/scripts/newsale3.js';

$versionNewsaleJs = file_exists($rutaNewsaleJs)
    ? filemtime($rutaNewsaleJs)
    : time();
?>

<script
    src="Views/modules/scripts/newsale3.js?v=<?= $versionNewsaleJs ?>">
</script>

<script>
/**
 * AJUSTES NUEVA VENTA v4
 * Motor autocontenido de visibilidad + persistencia.
 * Se mantiene aquí para que el estado ON/OFF no dependa de una copia
 * anterior de newsale3.js almacenada por el navegador o CDN.
 */
window.__VENTA_CAMPOS_INLINE_V4__ = true;

(function () {
    'use strict';

    const CAMPOS = Object.freeze({
        direccion: 'ventaCampoDireccion',
        tipo_pago: 'ventaCampoTipoPago',
        forma_pago: 'ventaCampoFormaPago',
        celular: 'ventaCampoCelular',
        fecha_emision: 'ventaCampoFechaEmision',
        tipo_operacion_sunat: 'ventaCampoTipoOperacionSunat',
        descuento: 'ventaCampoDescuentos',
        envio_comprobante: 'ventaCampoEnvioComprobante'
    });

    const PREDETERMINADOS = Object.freeze({
        tipo_comprobante: 1,
        cliente: 1,
        direccion: 0,
        tipo_pago: 1,
        forma_pago: 1,
        celular: 1,
        fecha_emision: 0,
        tipo_operacion_sunat: 1,
        descuento: 1,
        envio_comprobante: 1
    });

    let estadoActual = { ...PREDETERMINADOS };
    let temporizador = null;
    let secuenciaGuardado = 0;

    function aBit(valor) {
        return Number(valor) === 1 || valor === true ? 1 : 0;
    }

    function normalizar(configuracion) {
        const salida = { ...PREDETERMINADOS };
        const entrada = configuracion && typeof configuracion === 'object'
            ? configuracion
            : {};

        Object.keys(salida).forEach(function (clave) {
            if (clave === 'tipo_comprobante' || clave === 'cliente') {
                salida[clave] = 1;
                return;
            }

            if (Object.prototype.hasOwnProperty.call(entrada, clave)) {
                salida[clave] = aBit(entrada[clave]);
            }
        });

        return salida;
    }

    function mostrarCampo(clave, visible) {
        const id = CAMPOS[clave];
        if (!id) return;

        const campo = document.getElementById(id);
        if (!campo) return;

        if (visible) {
            campo.hidden = false;
            campo.setAttribute('aria-hidden', 'false');
            campo.classList.remove('is-hidden');
            campo.style.removeProperty('display');
        } else {
            campo.hidden = true;
            campo.setAttribute('aria-hidden', 'true');
            campo.classList.add('is-hidden');
            campo.style.setProperty('display', 'none', 'important');
        }
    }

    function sincronizarSwitchesDesdeEstado() {
        document.querySelectorAll('[data-campo-switch]').forEach(function (sw) {
            const clave = String(sw.getAttribute('data-campo-switch') || '');
            if (!clave) return;

            const activo = estadoActual[clave] === 1;

            /*
             * checked controla el estado actual. defaultChecked + atributo
             * checked evitan que la restauración automática del navegador
             * vuelva a imponer el HTML inicial después de F5.
             */
            sw.checked = activo;
            sw.defaultChecked = activo;

            if (activo) {
                sw.setAttribute('checked', 'checked');
            } else {
                sw.removeAttribute('checked');
            }
        });
    }

    function aplicar(configuracion) {
        estadoActual = normalizar(configuracion);

        Object.keys(CAMPOS).forEach(function (clave) {
            mostrarCampo(clave, estadoActual[clave] === 1);
        });

        sincronizarSwitchesDesdeEstado();
    }

    function leerSwitches() {
        const salida = { ...estadoActual };

        document.querySelectorAll('[data-campo-switch]').forEach(function (sw) {
            const clave = String(sw.getAttribute('data-campo-switch') || '');
            if (!Object.prototype.hasOwnProperty.call(PREDETERMINADOS, clave)) return;
            salida[clave] = sw.checked ? 1 : 0;
        });

        salida.tipo_comprobante = 1;
        salida.cliente = 1;
        return normalizar(salida);
    }

    function estadoTexto(texto, clase) {
        const contenedor = document.querySelector('.venta-ajustes-autoguardado');
        const etiqueta = document.getElementById('estadoGuardadoAjustesVenta');

        if (contenedor) {
            contenedor.classList.remove('is-saving', 'is-saved', 'is-error');
            if (clase) contenedor.classList.add('is-' + clase);
        }

        if (etiqueta) etiqueta.textContent = texto;
    }

    async function cargar() {
        estadoTexto('Cargando configuración...', 'saving');

        try {
            const respuesta = await fetch(
                'Controllers/Company.php?op=venta_campos_visibles&v=' + Date.now(),
                {
                    method: 'GET',
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                }
            );

            const datos = await respuesta.json();
            if (!respuesta.ok || !datos || datos.success !== true) {
                throw new Error(datos && datos.mensaje ? datos.mensaje : 'No se pudo cargar.');
            }

            aplicar(datos.configuracion || {});

            /*
             * Algunos navegadores restauran controles de formulario al final
             * del ciclo de carga. Reafirmamos el estado guardado sin volver a
             * consultar ni guardar nada.
             */
            window.setTimeout(sincronizarSwitchesDesdeEstado, 0);
            window.setTimeout(sincronizarSwitchesDesdeEstado, 250);
            window.setTimeout(sincronizarSwitchesDesdeEstado, 800);

            estadoTexto('Configuración guardada', 'saved');
        } catch (error) {
            aplicar(PREDETERMINADOS);
            estadoTexto('No se pudo cargar la configuración', 'error');
            console.error('[Nueva Venta · Ajustes v4]', error);
        }
    }

    async function guardar(configuracion, secuencia) {
        estadoTexto('Guardando...', 'saving');

        const cuerpo = new URLSearchParams();
        cuerpo.set('configuracion', JSON.stringify(configuracion));

        try {
            const respuesta = await fetch(
                'Controllers/Company.php?op=guardar_venta_campos_visibles&v=' + Date.now(),
                {
                    method: 'POST',
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: cuerpo.toString()
                }
            );

            const datos = await respuesta.json();
            if (!respuesta.ok || !datos || datos.success !== true) {
                throw new Error(datos && datos.mensaje ? datos.mensaje : 'No se pudo guardar.');
            }

            /* Ignorar respuestas antiguas si el usuario movió otro switch. */
            if (secuencia !== secuenciaGuardado) return;

            aplicar(datos.configuracion || configuracion);
            estadoTexto('Guardado automáticamente', 'saved');
        } catch (error) {
            if (secuencia !== secuenciaGuardado) return;
            estadoTexto('No se pudo guardar', 'error');
            console.error('[Nueva Venta · Ajustes v4]', error);
        }
    }

    function programarGuardado() {
        const configuracion = leerSwitches();

        /* ON/OFF manda inmediatamente en pantalla. */
        aplicar(configuracion);

        if (temporizador) window.clearTimeout(temporizador);
        const miSecuencia = ++secuenciaGuardado;

        temporizador = window.setTimeout(function () {
            temporizador = null;
            guardar(configuracion, miSecuencia);
        }, 140);
    }

    /*
     * El motor inline v4 asumía la visibilidad/autoguardado, pero al marcar
     * __VENTA_CAMPOS_INLINE_V4__ newsale3.js salía antes de registrar los
     * eventos del botón Ajustes. Por eso el botón quedaba sin manejador.
     * Esta interacción vive aquí junto al motor inline para tener una sola
     * fuente de verdad y evitar listeners duplicados.
     */
    function abrirPanelAjustes(abrir) {
        const panel = document.getElementById('panelAjustesVenta');
        const boton = document.getElementById('btnAjustesVenta');

        if (!panel || !boton) return;

        const debeAbrir = typeof abrir === 'boolean'
            ? abrir
            : !panel.classList.contains('is-open');

        /*
         * La BD / estadoActual es la única fuente de verdad. Al abrir el
         * panel se resincronizan siempre los switches. Esto corrige el caso
         * en que Chrome restaura los checkbox como OFF después de F5 aunque
         * los campos hayan sido cargados correctamente desde la BD.
         */
        if (debeAbrir) {
            sincronizarSwitchesDesdeEstado();
        }

        panel.classList.toggle('is-open', debeAbrir);
        panel.setAttribute('aria-hidden', debeAbrir ? 'false' : 'true');
        boton.setAttribute('aria-expanded', debeAbrir ? 'true' : 'false');
    }

    function inicializarInteraccionAjustes() {
        const boton = document.getElementById('btnAjustesVenta');
        const cerrar = document.getElementById('btnCerrarAjustesVenta');
        const panel = document.getElementById('panelAjustesVenta');

        if (!boton || !panel) return;

        /* Evita registrar el mismo listener más de una vez. */
        if (boton.dataset.ajustesVentaInicializado === '1') return;
        boton.dataset.ajustesVentaInicializado = '1';

        boton.addEventListener('click', function (evento) {
            evento.preventDefault();
            evento.stopPropagation();
            abrirPanelAjustes();
        });

        if (cerrar) {
            cerrar.addEventListener('click', function (evento) {
                evento.preventDefault();
                evento.stopPropagation();
                abrirPanelAjustes(false);
            });
        }

        document.addEventListener('click', function (evento) {
            if (!panel.classList.contains('is-open')) return;

            const objetivo = evento.target;
            if (!(objetivo instanceof Element)) return;

            if (!objetivo.closest('.venta-ajustes-wrap')) {
                abrirPanelAjustes(false);
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && panel.classList.contains('is-open')) {
                abrirPanelAjustes(false);
                boton.focus();
            }
        });
    }

    document.addEventListener('change', function (evento) {
        const objetivo = evento.target;
        if (!(objetivo instanceof HTMLInputElement)) return;
        if (!objetivo.matches('[data-campo-switch]')) return;
        programarGuardado();
    }, true);

    window.addEventListener('pageshow', function () {
        /*
         * También cubre restauraciones de página desde caché de navegación.
         * No escribe en BD; solo refleja estadoActual en los controles.
         */
        window.setTimeout(sincronizarSwitchesDesdeEstado, 0);
    });

    function iniciar() {
        /* El botón debe quedar operativo antes de esperar la respuesta de BD. */
        inicializarInteraccionAjustes();

        /* Evita que los opcionales aparezcan un instante antes de cargar BD. */
        Object.keys(CAMPOS).forEach(function (clave) {
            mostrarCampo(clave, false);
        });
        cargar();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar, { once: true });
    } else {
        iniciar();
    }

    window.VentaCamposAjustesV4 = { aplicar, cargar };
})();
</script>

<script>
    /*
    |--------------------------------------------------------------------------
    | Sincronizar tipo de pago con condición de pago
    |--------------------------------------------------------------------------
    | newsale3.js utiliza #condicion_pago para mostrar el bloque de crédito.
    | El selector real cargado desde la base de datos es #tipo_pago.
    */
    $(document).on('change', '#tipo_pago', function() {
        const condicion = $(this).find('option:selected').text().trim();

        $('#condicion_pago')
            .val(condicion)
            .trigger('change');
    });

    /*
    |--------------------------------------------------------------------------
    | Mantener monto numérico de la cuota
    |--------------------------------------------------------------------------
    */
    $(document).on('input change', '#numero_cuotas', function() {
        const cuotas = parseInt($(this).val(), 10);
        const textoTotal = $('#totalGeneral').text().replace(/[^\d.]/g, '');
        const total = parseFloat(textoTotal) || 0;

        if (!cuotas || cuotas < 1 || total <= 0) {
            $('#monto_cuota').val('');
            $('#monto_cuota_real').val('0.00');
            return;
        }

        const monto = total / cuotas;

        $('#monto_cuota').val('S/ ' + monto.toFixed(2));
        $('#monto_cuota_real').val(monto.toFixed(2));
    });

    /*
|--------------------------------------------------------------------------
| VALIDAR FECHA DE VENCIMIENTO DEL CRÉDITO
|--------------------------------------------------------------------------
| La fecha mínima permitida es mañana.
*/
    const fechaMinimaCredito = <?= json_encode(
                                    $fechaMinimaCredito,
                                    JSON_UNESCAPED_UNICODE |
                                        JSON_UNESCAPED_SLASHES
                                ) ?>;

    function validarFechaVencimientoCredito(mostrarMensaje = true) {
        const tipoPago = String(
                $('#tipo_pago option:selected').text() || ''
            )
            .trim()
            .toUpperCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');

        const esCredito = tipoPago.includes('CREDITO');

        const inputFecha = document.getElementById(
            'fecha_pago'
        );

        if (!inputFecha) {
            return true;
        }

        inputFecha.min = fechaMinimaCredito;
        inputFecha.setCustomValidity('');

        if (!esCredito) {
            return true;
        }

        const fechaSeleccionada = String(
            inputFecha.value || ''
        ).trim();

        if (fechaSeleccionada === '') {
            inputFecha.setCustomValidity(
                'Debe seleccionar la fecha de vencimiento de la primera cuota.'
            );

            if (mostrarMensaje) {
                inputFecha.reportValidity();
            }

            return false;
        }

        if (fechaSeleccionada < fechaMinimaCredito) {
            inputFecha.setCustomValidity(
                'La fecha de vencimiento debe ser posterior a la fecha de hoy.'
            );

            if (mostrarMensaje) {
                inputFecha.reportValidity();
            }

            return false;
        }

        inputFecha.setCustomValidity('');

        return true;
    }

    $(document).on(
        'change input',
        '#fecha_pago',
        function() {
            validarFechaVencimientoCredito(true);
        }
    );

    $(document).on(
        'change',
        '#tipo_pago',
        function() {
            validarFechaVencimientoCredito(false);
        }
    );
</script>

<?php
$rutaVentaColaJs = __DIR__ . '/scripts/venta_cola.js';

$versionVentaColaJs = is_file($rutaVentaColaJs)
    ? filemtime($rutaVentaColaJs)
    : time();
?>

<script
    src="Views/modules/scripts/venta_cola.js?v=<?= (int)$versionVentaColaJs ?>">
</script>

<?php
$rutaDuplicarVentaJs = __DIR__ . '/scripts/duplicar_venta.js';

$versionDuplicarVentaJs = is_file($rutaDuplicarVentaJs)
    ? filemtime($rutaDuplicarVentaJs)
    : time();
?>

<script
    src="Views/modules/scripts/duplicar_venta.js?v=<?= (int)$versionDuplicarVentaJs ?>">
</script>

<?php
ob_end_flush();
?>