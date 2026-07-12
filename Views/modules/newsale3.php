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

if ($_SESSION['ventas'] == 1) {
?>
    <div class="main-content">
        <section class="section">
            <div class="section-body">

                <form id="formularioVenta" method="post" autocomplete="off">

                    <div class="row">

                        <!-- =====================================================
                             PANEL IZQUIERDO: FORMULARIO
                        ====================================================== -->
                        <div class="col-lg-6 col-md-6 col-12">

                            <div class="card">

                                <div class="card-header">
                                    <h4>Nueva venta</h4>
                                </div>

                                <div class="card border-0 shadow-sm p-4">

                                    <div class="card-body px-0 pt-0">

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

                                                <label for="num_documento">
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
                                                        placeholder="DNI o RUC"
                                                        required>

                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary"
                                                        id="btnConsultarCliente"
                                                        onclick="consultarCliente()"
                                                        title="Consultar cliente">

                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>

                                                </div>

                                                <small
                                                    id="nombre_cliente"
                                                    class="text-muted d-block mt-2">
                                                    Ingrese un DNI de 8 dígitos o un RUC de 11 dígitos.
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
                                                    name="fecha_pago">

                                            </div>

                                            <div class="col-12">
                                                <small class="text-muted">
                                                    El importe se calculará según el total de la venta y el número de cuotas.
                                                </small>
                                            </div>

                                        </div>

                                        <!-- =====================================
                                             DESCUENTO
                                        ====================================== -->
                                        <div class="row mb-4">

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
                                        <div class="row g-4 mb-5 text-center">

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
                                             OBSERVACIÓN
                                        ====================================== -->
                                        <div class="mb-4">

                                            <label
                                                for="observacion"
                                                class="form-label">
                                                Observación
                                            </label>

                                            <textarea
                                                class="form-control"
                                                id="observacion"
                                                name="observacion"
                                                rows="3"
                                                spellcheck="false">
                                            </textarea>

                                        </div>

                                        <!-- =====================================
                                             MODO DE ENVÍO
                                        ====================================== -->
                                        <div class="mb-4">

                                            <label
                                                for="modo_envio"
                                                class="form-label">
                                                Modo de envío
                                            </label>

                                            <select
                                                class="form-control form-select"
                                                id="modo_envio"
                                                name="modo_envio">

                                                <option value="inmediato">
                                                    Enviar a SUNAT después de registrar
                                                </option>

                                            </select>

                                            <small class="text-muted d-block mt-2">
                                                La venta se registrará primero. El envío electrónico se realizará posteriormente mediante APISUNAT.
                                            </small>

                                        </div>

                                    </div>

                                    <!-- =====================================
                                         FOOTER DE LA VENTA
                                    ====================================== -->
                                    <div
                                        class="card-footer bg-white border-top px-4 py-3 position-sticky"
                                        style="bottom:0; z-index:30;">

                                        <div class="row align-items-center">

                                            <div class="col-12 col-md-8 mb-3 mb-md-0">

                                                <div
                                                    class="d-flex justify-content-md-start justify-content-center align-items-center h-100">

                                                    <span
                                                        style="font-size:1.3rem; color:#353535; font-weight:400;">
                                                        Total:&nbsp;
                                                    </span>

                                                    <span
                                                        id="totalGeneral"
                                                        style="font-size:2.4rem; color:#353535; font-weight:700;">
                                                        S/0.00
                                                    </span>

                                                </div>

                                            </div>

                                            <div class="col-12 col-md-4">

                                                <div
                                                    class="d-flex justify-content-md-end justify-content-center">

                                                    <button
                                                        type="submit"
                                                        id="btnProcesarVenta"
                                                        class="btn fw-normal"
                                                        style="
                                                            background:#52b848;
                                                            color:white;
                                                            min-width:190px;
                                                            height:60px;
                                                            font-size:1.2rem;
                                                        ">

                                                        Procesar

                                                    </button>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- =====================================================
                             PANEL DERECHO: PEDIDO ACTUAL
                        ====================================================== -->
                        <div class="col-lg-6 col-md-6 col-12">

                            <div class="card">

                                <div class="card-header">
                                    <h4>Pedido actual</h4>
                                </div>

                                <div class="card-body bg-white">

                                    <div
                                        class="position-relative"
                                        id="contenedorPedido"
                                        style="min-height:100px;">

                                        <div id="detallesCards"></div>

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
                                    class="d-flex justify-content-end align-items-end p-4"
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
                                            title="Escanear">

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
            class="modal-dialog modal-xl modal-dialog-centered"
            role="document">

            <div
                class="modal-content rounded-4 border-0"
                style="
                    min-height:85vh;
                    background:#fff;
                ">

                <div class="modal-header border-0 pb-0 align-items-center">

                    <span
                        class="fw-bold fs-3 ps-2"
                        id="modalProductosLabel"
                        style="color:#353535;">
                        PRODUCTOS
                    </span>

                    <button
                        type="button"
                        class="close fs-2"
                        data-dismiss="modal"
                        aria-label="Cerrar"
                        style="outline:none;">

                        <span aria-hidden="true">&times;</span>
                    </button>

                </div>

                <!-- Categorías -->
                <div
                    class="px-4 pt-3 pb-0 bg-white"
                    style="
                        border-bottom:1px solid #eee;
                        position:relative;
                    ">

                    <button
                        type="button"
                        id="catPrev"
                        class="btn btn-link p-0 m-0"
                        style="
                            position:absolute;
                            left:0;
                            top:60%;
                            transform:translateY(-50%);
                            z-index:2;
                        ">

                        <i
                            class="bi bi-chevron-left"
                            style="
                                font-size:1rem;
                                color:#aaa;
                            ">
                        </i>

                    </button>

                    <button
                        type="button"
                        id="catNext"
                        class="btn btn-link p-0 m-0"
                        style="
                            position:absolute;
                            right:0;
                            top:60%;
                            transform:translateY(-50%);
                            z-index:2;
                        ">

                        <i
                            class="bi bi-chevron-right"
                            style="
                                font-size:1rem;
                                color:#aaa;
                            ">
                        </i>

                    </button>

                    <nav class="mx-5">

                        <ul
                            class="nav flex-nowrap"
                            id="catList"
                            style="
                                white-space:nowrap;
                                overflow-x:auto;
                                scroll-behavior:smooth;
                            ">
                        </ul>

                    </nav>

                </div>

                <!-- Buscador -->
                <div class="px-4 py-3 bg-white">

                    <div
                        class="input-group"
                        style="max-width:540px;">

                        <div class="input-group-prepend">

                            <span
                                class="input-group-text bg-white border-end-0">

                                <i class="bi bi-search fs-4 text-secondary"></i>
                            </span>

                        </div>

                        <input
                            type="text"
                            class="form-control border-start-0"
                            id="buscarProducto"
                            autocomplete="off"
                            placeholder="Buscar por nombre o código..."
                            style="font-size:1.2rem;">

                        <div class="input-group-append">

                            <button
                                type="button"
                                class="btn btn-outline-secondary bg-green"
                                title="Escanear código">

                                <i class="bi bi-upc-scan fs-4"></i>
                            </button>

                        </div>

                    </div>

                </div>

                <!-- Productos -->
                <div class="modal-body pt-0">

                    <div
                        class="row px-3"
                        id="productosList">
                    </div>

                    <div
                        class="
                            modal-footer
                            border-0
                            bg-white
                            px-4
                            pb-4
                            pt-2
                            justify-content-end
                        ">

                        <button
                            type="button"
                            class="
                                btn
                                btn-success
                                btn-lg
                                d-flex
                                align-items-center
                                gap-2
                                px-4
                            "
                            style="min-width:300px;">

                            Escanear con la cámara

                            <i class="bi bi-upc-scan fs-4"></i>
                        </button>

                    </div>

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
<script src="Views/modules/scripts/newsale3.js"></script>

<script>
    /*
    |--------------------------------------------------------------------------
    | Sincronizar tipo de pago con condición de pago
    |--------------------------------------------------------------------------
    | newsale3.js utiliza #condicion_pago para mostrar el bloque de crédito.
    | El selector real cargado desde la base de datos es #tipo_pago.
    */
    $(document).on('change', '#tipo_pago', function () {
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
    $(document).on('input change', '#numero_cuotas', function () {
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
</script>

<?php
ob_end_flush();
?>