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

                                    <div class="card-body px-0 pt-0">
                                        <form>
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
                                        </form>
                                    </div>
                                    <div class="card-footer bg-white border-0 px-4 pb-4 pt-2">
                                        <div class="row align-items-center">
                                            <!-- Total -->
                                            <div class="col-12 col-md-8 mb-3 mb-md-0">
                                                <div class="d-flex justify-content-md-start justify-content-center align-items-center h-100">
                                                    <span style="font-size:1.3rem; color:#353535; font-weight:400;">Total:&nbsp;</span>
                                                    <span style="font-size:2.4rem; color:#353535; font-weight:700;">S/133.50</span>
                                                </div>
                                            </div>
                                            <!-- Botón -->
                                            <div class="col-12 col-md-4">
                                                <div class="d-flex justify-content-md-end justify-content-center">
                                                    <button class="btn fw-normal"
                                                        style="background:#52b848; color:white; min-width:190px; height:60px; font-size:1.2rem; border-radius:10px;">
                                                        Procesar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                                    <div class="mb-4" style="max-height:430px;overflow-y:auto;">
                                        <!-- Producto 1 -->
                                        <div class="card border-0 shadow-sm mb-3 bg-white">
                                            <div class="card-body d-flex justify-content-between align-items-start p-3">
                                                <!-- Info del producto -->
                                                <div>
                                                    <div class="fw-bold fs-6 mb-1 text-dark">Polo crop Simpson | Amarillo |
                                                        Standard</div>
                                                    <div class="text-muted small">Almacén: Huaquio 1</div>
                                                    <div class="text-muted small">SKU: PC208</div>
                                                    <div class="text-muted small">Precio Unitario: <span
                                                            class="fw-semibold">S/25.00</span></div>
                                                    <div class="text-muted small">Cantidad: <span class="fw-semibold">2</span>
                                                    </div>
                                                    <div class="fw-bold mt-2 text-dark">Total: S/50.00</div>
                                                </div>
                                                <!-- Columna derecha: botones -->
                                                <div class="d-flex flex-column justify-content-between align-items-end ms-auto"
                                                    style="min-width:48px;">
                                                    <div class="d-flex flex-column align-items-center gap-1">
                                                        <button class="btn btn-outline-success btn-sm px-2 py-1 mb-1"><i
                                                                class="bi bi-plus"></i></button>
                                                        <button class="btn btn-outline-secondary btn-sm px-2 py-1"><i
                                                                class="bi bi-dash"></i></button>
                                                    </div>
                                                    <button class="btn btn-outline-danger btn-sm px-2 py-1 mt-3"
                                                        style="min-width:32px;"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Producto 2 -->
                                        <div class="card border-0 shadow-sm mb-3 bg-white">
                                            <div class="card-body d-flex justify-content-between align-items-start p-3">
                                                <!-- Info del producto -->
                                                <div>
                                                    <div class="fw-bold fs-6 mb-1 text-dark">Polo crop Simpson | Amarillo |
                                                        Standard</div>
                                                    <div class="text-muted small">Almacén: Huaquio 1</div>
                                                    <div class="text-muted small">SKU: PC208</div>
                                                    <div class="text-muted small">Precio Unitario: <span
                                                            class="fw-semibold">S/25.00</span></div>
                                                    <div class="text-muted small">Cantidad: <span class="fw-semibold">2</span>
                                                    </div>
                                                    <div class="fw-bold mt-2 text-dark">Total: S/50.00</div>
                                                </div>
                                                <!-- Columna derecha: botones -->
                                                <div class="d-flex flex-column justify-content-between align-items-end ms-auto"
                                                    style="min-width:48px;">
                                                    <div class="d-flex flex-column align-items-center gap-1">
                                                        <button class="btn btn-outline-success btn-sm px-2 py-1 mb-1"><i
                                                                class="bi bi-plus"></i></button>
                                                        <button class="btn btn-outline-secondary btn-sm px-2 py-1"><i
                                                                class="bi bi-dash"></i></button>
                                                    </div>
                                                    <button class="btn btn-outline-danger btn-sm px-2 py-1 mt-3"
                                                        style="min-width:32px;"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Producto 3 -->
                                        <div class="card border-0 shadow-sm mb-3 bg-white">
                                            <div class="card-body d-flex justify-content-between align-items-start p-3">
                                                <!-- Info del producto -->
                                                <div>
                                                    <div class="fw-bold fs-6 mb-1 text-dark">Polo crop Simpson | Amarillo |
                                                        Standard</div>
                                                    <div class="text-muted small">Almacén: Huaquio 1</div>
                                                    <div class="text-muted small">SKU: PC208</div>
                                                    <div class="text-muted small">Precio Unitario: <span
                                                            class="fw-semibold">S/25.00</span></div>
                                                    <div class="text-muted small">Cantidad: <span class="fw-semibold">2</span>
                                                    </div>
                                                    <div class="fw-bold mt-2 text-dark">Total: S/50.00</div>
                                                </div>
                                                <!-- Columna derecha: botones -->
                                                <div class="d-flex flex-column justify-content-between align-items-end ms-auto"
                                                    style="min-width:48px;">
                                                    <div class="d-flex flex-column align-items-center gap-1">
                                                        <button class="btn btn-outline-success btn-sm px-2 py-1 mb-1"><i
                                                                class="bi bi-plus"></i></button>
                                                        <button class="btn btn-outline-secondary btn-sm px-2 py-1"><i
                                                                class="bi bi-dash"></i></button>
                                                    </div>
                                                    <button class="btn btn-outline-danger btn-sm px-2 py-1 mt-3"
                                                        style="min-width:32px;"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Botones flotantes, siempre abajo a la derecha -->

                                    <div class="d-flex justify-content-end align-items-end"
                                        style="height: 100px; pointer-events: none;">
                                        <div style="pointer-events: auto; display: flex; gap: 24px;">
                                            <button
                                                class="btn btn-success shadow d-flex align-items-center justify-content-center"
                                                style="width:72px; height:52px; border-radius:18px;" title="Escanear">
                                                <i class="bi bi-qr-code-scan" style="font-size:2rem;"></i>
                                            </button>
                                            <button
                                                class="btn btn-success shadow d-flex align-items-center justify-content-center"
                                                style="width:72px; height:52px; border-radius:18px;" title="Agregar"
                                                id="btnAbrirModal">
                                                <i class="bi bi-plus" style="font-size:2rem;"></i>
                                            </button>
                                        </div>
                                    </div>


                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

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
                        <!-- Flecha Izquierda -->
                        <button id="catPrev" class="btn btn-link p-0 m-0" style="position:absolute; left:0; top:60%; transform:translateY(-50%); z-index:2;">
                            <i class="bi bi-chevron-left" style="font-size:1rem; color:#aaa;"></i>
                        </button>
                        <!-- Flecha Derecha -->
                        <button id="catNext" class="btn btn-link p-0 m-0" style="position:absolute; right:0; top:60%; transform:translateY(-50%); z-index:2;">
                            <i class="bi bi-chevron-right" style="font-size:1rem; color:#aaa;"></i>
                        </button>

                        <nav class="mx-5"> <!-- Espacio para que no tapen las flechas -->
                            <ul class="nav flex-nowrap" id="catList" style="white-space:nowrap; overflow-x:auto; scroll-behavior:smooth;">
                            </ul>
                        </nav>
                    </div>

                    <!-- Buscador -->
                    <div class="px-4 py-3 bg-white">
                        <div class="input-group" style="max-width: 540px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search fs-4 text-secondary"></i></span>
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
                        <div class="row px-3" id="productosList">
                    </div>
                    <!-- Footer SIEMPRE al pie del modal, derecha -->
                    <div class="modal-footer border-0 bg-white px-4 pb-4 pt-2 justify-content-end" style="border-top:none;">
                        <button class="btn btn-success btn-lg d-flex align-items-center gap-2 px-4"
                            style="min-width:300px;">
                            Escanear con la cámara <i class="bi bi-upc-scan fs-4"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

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