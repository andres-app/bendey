<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
    header("location: login");
} else {
    require "header.php";
    require "sidebar.php";

    if ($_SESSION['ventas'] == 1) {
?>
        <div class="main-content">
            <section class="section">
                <div class="section-body">
                    <div class="row">
                        <!-- Panel Izquierdo: Formulario Venta -->
                        <div class="col-lg-6 col-md-6 col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4>Nueva venta</h4>
                                </div>

                                <div class="card border-0 shadow-sm p-4">

                                    <!-- ✅ FORM ABRE AQUÍ Y NO SE CIERRA HASTA DESPUÉS DEL FOOTER -->
                                    <form id="formularioVenta">
                                        <div class="card-body px-0 pt-0">

                                            <div class="row g-3 mb-4">
                                                <div class="col-md-6">
                                                    <label>Tipo de Comprobante</label>
                                                    <select id="tipo_comprobante" class="form-control form-select"
                                                        name="tipo_comprobante"></select>
                                                </div>

                                                <div class="col-md-6">
                                                    <label for="num_documento">Cliente</label>
                                                    <div class="input-group">
                                                        <input class="form-control" type="text" name="num_documento"
                                                            id="num_documento" maxlength="20"
                                                            placeholder="N° Documento">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            onclick="consultarCliente()">
                                                            <i class="bi bi-arrow-repeat"></i>
                                                        </button>
                                                    </div>

                                                    <!-- AGREGADO: input oculto para el valor real -->
                                                    <input type="hidden" id="num_doc_real" name="num_doc_real">

                                                    <!-- Nombre de cliente autollenado -->
                                                    <small id="nombre_cliente" class="text-muted d-block mt-2"></small>
                                                </div>
                                            </div>

                                            <div class="row g-3 mb-4">
                                                <div class="col-md-4">
                                                    <label>Celular</label>
                                                    <div class="form-group mb-0">
                                                        <input class="form-control" placeholder="986634352">
                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <label>Condición de pago</label>
                                                    <div class="form-group mb-0">
                                                        <select class="form-control form-select">
                                                            <option>Contado</option>
                                                            <option>Crédito</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <label>Forma de pago</label>
                                                    <div class="form-group mb-0">
                                                        <select class="form-control form-select">
                                                            <option>Efectivo</option>
                                                            <option>Yape/Plin</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row mb-4">
                                                <div class="col-12 d-flex justify-content-center">
                                                    <div class="d-flex align-items-center">
                                                        <label class="custom-switch">
                                                            <input type="radio" name="option" value="1"
                                                                class="custom-switch-input" checked="">
                                                            <span class="custom-switch-indicator bg-success"></span>
                                                            <span class="custom-switch-description">Descuento en %</span>
                                                        </label>
                                                        <input type="number" class="form-control text-center"
                                                            style="width:90px; margin-left:24px;" value="0.0" min="0" max="100"
                                                            step="0.1">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row g-3 mb-4">
                                                <div class="col-md-6">
                                                    <label class="form-label">Total recibido soles</label>
                                                    <input class="form-control text-success fw-bold fs-5" value="S/150.00">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Vuelto soles</label>
                                                    <input class="form-control text-secondary fw-bold fs-5" value="S/16.50">
                                                </div>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label">Observación</label>
                                                <textarea class="form-control" spellcheck="false"
                                                    data-ms-editor="true"></textarea>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label">Modo de envío</label>
                                                <select class="form-control form-select">
                                                    <option>Enviar a SUNAT ahora mismo!</option>
                                                </select>
                                            </div>

                                        </div>

                                        <!-- ✅ ESTE FOOTER AHORA ESTÁ DENTRO DEL FORM -->
                                        <div class="card-footer bg-white border-0 px-4 pb-4 pt-2">
                                            <div class="row align-items-center">

                                                <!-- Total -->
                                                <div class="col-12 col-md-8 mb-3 mb-md-0">
                                                    <div class="d-flex justify-content-md-start justify-content-center align-items-center h-100">
                                                        <span style="font-size:1.3rem; color:#353535; font-weight:400;">Total:&nbsp;</span>
                                                        <span id="totalGeneral" style="font-size:2.4rem; color:#353535; font-weight:700;">
                                                            S/0.00
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Botón -->
                                                <div class="col-12 col-md-4">
                                                    <div class="d-flex justify-content-md-end justify-content-center">
                                                        <button
                                                            type="submit"
                                                            class="btn fw-normal"
                                                            style="background:#52b848; color:white; min-width:190px; height:60px; font-size:1.2rem;">
                                                            Procesar
                                                        </button>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                    </form>
                                    <!-- ✅ FORM CIERRA AQUÍ -->

                                </div>
                            </div>
                        </div>

                        <!-- Panel Derecho: Carrito/Pedido Actual -->
                        <div class="col-lg-6 col-md-6 col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4>Pedido actual</h4>
                                </div>

                                <div class="card-body bg-white">

                                    <!-- CONTENEDOR DEL PEDIDO -->
                                    <div class="card-body position-relative" id="detallesCards">

                                        <!-- EMPTY STATE -->
                                        <div id="pedidoVacio"
                                            class="position-absolute top-0 start-0 w-100 h-100
                                            d-flex flex-column justify-content-center align-items-center
                                            text-center text-muted bg-white">
                                            <i class="bi bi-cart-plus mb-3" style="font-size:3rem;"></i>
                                            <p class="mb-1 fw-semibold">No hay productos en el pedido</p>
                                            <small>
                                                Selecciona el botón <b>+</b> para agregar productos<br>
                                                o escanéalos con la cámara
                                            </small>
                                        </div>

                                    </div>

                                    <!-- BOTONES FLOTANTES -->
                                    <div class="d-flex justify-content-end align-items-end mt-3"
                                        style="pointer-events:none;">
                                        <div style="pointer-events:auto; display:flex; gap:24px;">
                                            <button
                                                class="btn btn-success shadow d-flex align-items-center justify-content-center"
                                                style="width:72px; height:52px; border-radius:18px;"
                                                title="Escanear"
                                                type="button">
                                                <i class="bi bi-qr-code-scan" style="font-size:2rem;"></i>
                                            </button>

                                            <button
                                                class="btn btn-success shadow d-flex align-items-center justify-content-center"
                                                style="width:72px; height:52px; border-radius:18px;"
                                                title="Agregar"
                                                id="btnAbrirModal"
                                                type="button">
                                                <i class="bi bi-plus" style="font-size:2rem;"></i>
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div><!-- /.row -->
                </div><!-- /.section-body -->
            </section>
        </div><!-- /.main-content -->

        <!-- Modal Productos -->
        <div class="modal fade" id="modalProductos" tabindex="-1" role="dialog" aria-labelledby="modalProductosLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
                <div class="modal-content rounded-4 border-0" style="min-height: 85vh; background: #fff;">

                    <!-- Header -->
                    <div class="modal-header border-0 pb-0 align-items-center" style="padding-bottom:0;">
                        <span class="fw-bold fs-3 ps-2" style="color:#353535;">PRODUCTOS</span>
                        <button type="button" class="close fs-2" data-dismiss="modal" aria-label="Cerrar" style="outline:none;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="px-4 pt-3 pb-0 bg-white" style="border-bottom:1px solid #eee; position:relative;">
                        <button id="catPrev" class="btn btn-link p-0 m-0" type="button"
                            style="position:absolute; left:0; top:60%; transform:translateY(-50%); z-index:2;">
                            <i class="bi bi-chevron-left" style="font-size:1rem; color:#aaa;"></i>
                        </button>

                        <button id="catNext" class="btn btn-link p-0 m-0" type="button"
                            style="position:absolute; right:0; top:60%; transform:translateY(-50%); z-index:2;">
                            <i class="bi bi-chevron-right" style="font-size:1rem; color:#aaa;"></i>
                        </button>

                        <nav class="mx-5">
                            <ul class="nav flex-nowrap" id="catList" style="white-space:nowrap; overflow-x:auto; scroll-behavior:smooth;">
                            </ul>
                        </nav>
                    </div>

                    <!-- Buscador -->
                    <div class="px-4 py-3 bg-white">
                        <div class="input-group" style="max-width: 540px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search fs-4 text-secondary"></i>
                                </span>
                            </div>
                            <input type="text" class="form-control border-start-0" placeholder="PC208" style="font-size:1.2rem;">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary bg-green" type="button" title="Escanear código">
                                    <i class="bi bi-upc-scan fs-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Productos -->
                    <div class="modal-body pt-0">
                        <div class="row px-3" id="productosList"></div>

                        <div class="modal-footer border-0 bg-white px-4 pb-4 pt-2 justify-content-end" style="border-top:none;">
                            <button class="btn btn-success btn-lg d-flex align-items-center gap-2 px-4"
                                style="min-width:300px;" type="button">
                                Escanear con la cámara <i class="bi bi-upc-scan fs-4"></i>
                            </button>
                        </div>
                    </div>

                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div><!-- /.modal -->

        <!-- Bootstrap Icons CDN -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<?php
    } else {
        require "access.php";
    }
    require "footer.php";
?>
    <script src="Views/modules/scripts/generaldata.js"></script>
    <script src="Views/modules/scripts/newsale3.js"></script>
<?php
}
ob_end_flush();
?>
