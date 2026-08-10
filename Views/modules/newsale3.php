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
                        class="venta-mobile-switch"
                        role="tablist"
                        aria-label="Secciones de nueva venta">

                        <span
                            class="venta-mobile-switch-slider"
                            aria-hidden="true">
                        </span>

                        <button
                            type="button"
                            class="venta-mobile-switch-btn active"
                            id="ventaSwitchDatos"
                            data-venta-panel="datos"
                            role="tab"
                            aria-selected="true"
                            aria-controls="ventaPanelDatos">
                            Datos
                        </button>

                        <button
                            type="button"
                            class="venta-mobile-switch-btn"
                            id="ventaSwitchProductos"
                            data-venta-panel="productos"
                            role="tab"
                            aria-selected="false"
                            aria-controls="ventaPanelProductos">
                            Productos
                        </button>

                    </div>
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

                                <div class="card-header venta-panel-header">
                                    <h4>Nueva venta</h4>
                                </div>

                                <div class="venta-form-shell">

                                    <div class="card-body px-0 pt-0 venta-form-scroll">

                                        <!-- =====================================
                                             COMPROBANTE Y CLIENTE
                                        ====================================== -->
                                        <div class="row g-3 mb-4">

                                            <div class="col-md-6">
                                                <label for="tipo_comprobante">
                                                    Tipo de comprobante
                                                </label>

                                                <select
                                                    id="tipo_comprobante"
                                                    name="tipo_comprobante"
                                                    class="form-control form-select"
                                                    required>
                                                </select>

                                                <!-- Vista previa únicamente.
                                                     El correlativo definitivo se
                                                     asigna en el backend. -->
                                                <input
                                                    type="hidden"
                                                    id="serie_comprobante"
                                                    name="serie_comprobante">

                                                <input
                                                    type="hidden"
                                                    id="num_comprobante"
                                                    name="num_comprobante">
                                            </div>

                                            <div class="col-md-6">

                                                <label for="num_documento" class="mb-1">
                                                    Cliente
                                                </label>

                                                <div class="input-group">

                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        id="num_documento"
                                                        name="num_documento"
                                                        maxlength="11"
                                                        inputmode="numeric"
                                                        autocomplete="off"
                                                        placeholder="DNI o RUC">

                                                    <div class="input-group-append">
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-secondary px-3"
                                                            id="btnConsultarCliente"
                                                            onclick="consultarCliente()"
                                                            title="Consultar DNI o RUC">

                                                            <i class="bi bi-arrow-repeat"></i>
                                                        </button>
                                                    </div>

                                                </div>

                                                <small
                                                    id="nombre_cliente"
                                                    class="text-muted d-block mt-2">
                                                    Déjelo vacío para usar CLIENTE VARIOS.
                                                </small>

                                                <!-- =================================
                                                     DATOS REALES DEL CLIENTE
                                                ================================== -->
                                                <input
                                                    type="hidden"
                                                    id="idcliente"
                                                    name="idcliente"
                                                    value="">

                                                <input
                                                    type="hidden"
                                                    id="cliente_generico"
                                                    name="cliente_generico"
                                                    value="0">

                                                <input
                                                    type="hidden"
                                                    id="tipo_documento"
                                                    name="tipo_documento"
                                                    value="">

                                                <input
                                                    type="hidden"
                                                    id="num_doc_real"
                                                    name="num_doc_real"
                                                    value="">

                                                <input
                                                    type="hidden"
                                                    id="nombre_cli"
                                                    name="nombre_cli"
                                                    value="">

                                                <input
                                                    type="hidden"
                                                    id="direccion"
                                                    name="direccion"
                                                    value="">

                                                <input
                                                    type="hidden"
                                                    id="email"
                                                    name="email"
                                                    value="">

                                                <!-- =================================
                                                     DESCUENTOS PARA BACKEND
                                                ================================== -->
                                                <input
                                                    type="hidden"
                                                    id="descuento_total"
                                                    name="descuento_total"
                                                    value="0.00">

                                                <input
                                                    type="hidden"
                                                    id="descuento_porcentaje"
                                                    name="descuento_porcentaje"
                                                    value="0.00">


                                                <!-- RESUMEN TRIBUTARIO CALCULADO -->
                                                <input type="hidden" id="total_gravado" name="total_gravado" value="0.00">
                                                <input type="hidden" id="total_exonerado" name="total_exonerado" value="0.00">
                                                <input type="hidden" id="total_inafecto" name="total_inafecto" value="0.00">
                                                <input type="hidden" id="total_exportacion" name="total_exportacion" value="0.00">
                                                <input type="hidden" id="total_igv" name="total_igv" value="0.00">
                                                <input type="hidden" id="precios_incluyen_impuesto" name="precios_incluyen_impuesto" value="1">

                                            </div>

                                        </div>

                                        <!-- =====================================
                                             PAGOS
                                        ====================================== -->
                                        <div class="row g-3 mb-4">

                                            <div class="col-md-4">

                                                <label for="celular">
                                                    Celular
                                                </label>

                                                <div class="form-group mb-0">
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        id="celular"
                                                        name="celular"
                                                        maxlength="9"
                                                        inputmode="numeric"
                                                        autocomplete="off"
                                                        placeholder="Ej.: 986634352">
                                                </div>

                                            </div>

                                            <div class="col-md-4">

                                                <label for="tipo_pago">
                                                    Tipo de pago
                                                </label>

                                                <div class="form-group mb-0">

                                                    <select
                                                        class="form-control form-select"
                                                        id="tipo_pago"
                                                        name="idtipopago"
                                                        required>
                                                    </select>

                                                    <!-- Se sincroniza con el texto
                                                         seleccionado: Contado/Crédito -->
                                                    <input
                                                        type="hidden"
                                                        id="condicion_pago"
                                                        name="condicion_pago"
                                                        value="">

                                                </div>

                                            </div>

                                            <div class="col-md-4">

                                                <label for="forma_pago">
                                                    Forma de pago
                                                </label>

                                                <div class="form-group mb-0">
                                                    <select
                                                        class="form-control form-select"
                                                        id="forma_pago"
                                                        name="idforma_pago"
                                                        required>
                                                    </select>
                                                </div>

                                            </div>

                                        </div>

                                        <!-- =====================================
                                             DATOS DE CRÉDITO
                                        ====================================== -->
                                        <div
                                            id="bloque_credito"
                                            class="row g-3 mb-4"
                                            style="display:none;">

                                            <div class="col-md-4">

                                                <label
                                                    for="numero_cuotas"
                                                    class="fw-bold">
                                                    N.º de cuotas
                                                </label>

                                                <input
                                                    type="number"
                                                    min="1"
                                                    class="form-control"
                                                    id="numero_cuotas"
                                                    name="numero_cuotas"
                                                    placeholder="Ej.: 3">

                                            </div>

                                            <div class="col-md-4">

                                                <label
                                                    for="monto_cuota"
                                                    class="fw-bold">
                                                    Monto por cuota
                                                </label>

                                                <input
                                                    type="text"
                                                    class="form-control bg-light"
                                                    id="monto_cuota"
                                                    readonly
                                                    placeholder="S/ 0.00">

                                                <input
                                                    type="hidden"
                                                    id="monto_cuota_real"
                                                    name="monto_cuota"
                                                    value="0.00">

                                            </div>

                                            <div class="col-md-4">

                                                <label
                                                    for="fecha_pago"
                                                    class="fw-bold">
                                                    Fecha del primer pago
                                                </label>

                                                <input
                                                    type="date"
                                                    class="form-control"
                                                    id="fecha_pago"
                                                    name="fecha_pago"
                                                    min="<?= htmlspecialchars(
                                                                $fechaMinimaCredito,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>">

                                            </div>

                                            <div class="col-12">
                                                <small class="text-muted">
                                                    El importe se calculará según el total de la venta y el número de cuotas.
                                                </small>
                                            </div>

                                        </div>


                                        <!-- =====================================
                                             TIPO DE OPERACIÓN SUNAT
                                        ====================================== -->
                                        <div class="row g-3 mb-4 venta-tipo-operacion-row">
                                            <div class="col-12">
                                                <label for="tipo_operacion_sunat">
                                                    Tipo de operación SUNAT
                                                </label>

                                                <select
                                                    class="form-control form-select"
                                                    id="tipo_operacion_sunat"
                                                    name="tipo_operacion_sunat"
                                                    required>
                                                    <option value="0101">
                                                        0101 — Venta interna
                                                    </option>
                                                </select>

                                                <small
                                                    class="form-text text-muted"
                                                    id="ayudaTipoOperacionSunat">
                                                    Se utilizará la configuración tributaria de la empresa o sucursal.
                                                </small>
                                            </div>
                                        </div>

                                        <!-- =====================================
                                             DESCUENTO
                                        ====================================== -->
                                        <div class="row mb-4 venta-descuento-row">

                                            <div class="col-12 d-flex justify-content-center">

                                                <div class="d-flex align-items-center">

                                                    <label class="custom-switch mb-0">

                                                        <input
                                                            type="checkbox"
                                                            id="descuentoSwitch"
                                                            class="custom-switch-input"
                                                            checked>

                                                        <span
                                                            class="custom-switch-indicator bg-success">
                                                        </span>

                                                        <span
                                                            class="custom-switch-description"
                                                            id="labelDescuento">
                                                            Descuento en %
                                                        </span>

                                                    </label>

                                                    <!-- No lleva name para evitar
                                                         duplicidad con el campo
                                                         oculto descuento_porcentaje -->
                                                    <input
                                                        type="number"
                                                        id="descuentoPorcentaje"
                                                        class="form-control text-center"
                                                        style="width:90px; margin-left:24px;"
                                                        value="0"
                                                        min="0"
                                                        max="100"
                                                        step="0.1"
                                                        placeholder="%">

                                                </div>

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

                                        <!-- =====================================
                                             MODO DE ENVÍO
                                        ====================================== -->
                                        <div class="row g-3 venta-fila-final">
                                            <div class="col-12 venta-modo-envio">
                                                <label
                                                    for="modo_envio"
                                                    class="form-label">
                                                    Envío del comprobante
                                                </label>

                                                <select
                                                    class="form-control form-select"
                                                    id="modo_envio"
                                                    name="modo_envio"
                                                    required>

                                                    <option value="inmediato">
                                                        Enviar inmediatamente a SUNAT
                                                    </option>

                                                    <option value="manual">
                                                        Guardar y enviar manualmente después
                                                    </option>

                                                </select>

                                                <small
                                                    class="text-muted d-block mt-2"
                                                    id="mensajeModoEnvio">
                                                    La venta se registrará y luego será enviada automáticamente mediante APISUNAT.
                                                </small>
                                            </div>
                                        </div>

                                    </div>

                                    <!-- =====================================
                                         FOOTER DE LA VENTA
                                    ====================================== -->
                                    <div class="card-footer venta-form-footer">

                                        <div class="venta-footer-total">
                                            <span class="venta-footer-total-label">Total</span>
                                            <span id="totalGeneral" class="venta-footer-total-monto">
                                                S/0.00
                                            </span>
                                        </div>

                                        <button
                                            type="submit"
                                            id="btnProcesarVenta"
                                            class="btn venta-procesar-btn">
                                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                            <span>Procesar venta</span>
                                        </button>

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
           SWITCH FIJO DATOS / PRODUCTOS (MÓVIL Y TABLET)
        ========================================================== */
        .venta-mobile-switch-wrap {
            display: none;
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
| DESCRIPCIÓN DEL MODO DE ENVÍO
|--------------------------------------------------------------------------
*/
    $(document).on('change', '#modo_envio', function() {
        const modo = String(
            $(this).val() || 'inmediato'
        );

        if (modo === 'manual') {
            $('#mensajeModoEnvio').html(
                '<strong>Envío manual:</strong> ' +
                'la venta se registrará y reservará su correlativo, ' +
                'pero no será enviada a SUNAT. Podrá enviarla posteriormente ' +
                'desde Estado de Comprobantes SUNAT.'
            );

            return;
        }

        $('#mensajeModoEnvio').html(
            '<strong>Envío inmediato:</strong> ' +
            'la venta se registrará y será enviada automáticamente mediante APISUNAT.'
        );
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