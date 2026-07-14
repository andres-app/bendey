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
                                                name="modo_envio"
                                                required>

                                                <option value="inmediato">
                                                    Registrar y enviar inmediatamente a SUNAT
                                                </option>

                                                <option value="manual">
                                                    Registrar ahora y enviar manualmente después
                                                </option>

                                            </select>

                                            <small
                                                class="text-muted d-block mt-2"
                                                id="mensajeModoEnvio">
                                                La venta se registrará y luego será enviada automáticamente mediante APISUNAT.
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

                <!-- Buscador y creación rápida -->
                <div class="px-4 py-3 bg-white">

                    <div
                        class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center"
                        style="gap:14px;">

                        <div
                            class="input-group flex-grow-1"
                            style="max-width:620px;">

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
                                style="font-size:1.1rem; min-height:48px;">

                            <div class="input-group-append">

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary bg-white"
                                    title="Escanear código">

                                    <i class="bi bi-upc-scan fs-4"></i>
                                </button>

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
                                                <span class="producto-rapido-obligatorio">*</span>
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
                                                    min="0.01"
                                                    max="99999999.99"
                                                    step="0.01"
                                                    required
                                                    placeholder="Ej.: 10.00">
                                            </div>

                                            <small class="producto-rapido-ayuda">
                                                Es el costo pagado al proveedor por una unidad.
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
ob_end_flush();
?>