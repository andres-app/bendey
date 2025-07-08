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
                                                    <select class="form-control form-select">
                                                        <option>Boleta de venta electrónica</option>
                                                        <!-- Puedes agregar más opciones si lo requieres -->
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Cliente</label>
                                                    <div class="input-group">
                                                        <input class="form-control" value="72050413 | Orlando Joaquin Ahumada Chávez">
                                                        <button class="btn btn-outline-secondary" type="button">
                                                            <i class="bi bi-arrow-repeat"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row g-3 mb-4">
                                                <div class="col-md-4">
                                                    <label>Celular</label>
                                                    <div class="form-group mb-0">
                                                        <input class="form-control" value="986634352">
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
                                                            <input type="radio" name="option" value="1" class="custom-switch-input" checked="">
                                                            <span class="custom-switch-indicator bg-success"></span>
                                                            <span class="custom-switch-description">Descuento en %</span>
                                                        </label>
                                                        <input type="number" class="form-control text-center" style="width:90px; margin-left:24px;" value="0.0" min="0" max="100" step="0.1">
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
                                                <textarea class="form-control" spellcheck="false" data-ms-editor="true"></textarea>
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label">Modo de envío</label>
                                                <select class="form-control form-select">
                                                    <option>Enviar a SUNAT ahora mismo!</option>
                                                </select>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="card-footer bg-white border-0 p-0">
                                        <hr class="my-0" style="border-top: 1.5px solid #ececec;">
                                        <div class="row align-items-center g-0 py-4 px-4">
                                            <div class="col-md-8 col-6 d-flex align-items-center">
                                                <span class="fw-normal text-dark fs-4" style="min-width:80px;">Total:</span>
                                                <span class="fw-bold text-dark ms-2" style="font-size:2.5rem; line-height:1;">S/133.50</span>
                                            </div>
                                            <div class="col-md-4 col-6 text-end">
                                                <button class="btn btn-success fw-semibold" style="font-size:1.45rem; padding: 0.7rem 3rem; border-radius:8px;">Procesar</button>
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
                                                    <div class="fw-bold fs-6 mb-1 text-dark">Polo crop Simpson | Amarillo | Standard</div>
                                                    <div class="text-muted small">Almacén: Huaquio 1</div>
                                                    <div class="text-muted small">SKU: PC208</div>
                                                    <div class="text-muted small">Precio Unitario: <span class="fw-semibold">S/25.00</span></div>
                                                    <div class="text-muted small">Cantidad: <span class="fw-semibold">2</span></div>
                                                    <div class="fw-bold mt-2 text-dark">Total: S/50.00</div>
                                                </div>
                                                <!-- Columna derecha: botones -->
                                                <div class="d-flex flex-column justify-content-between align-items-end ms-auto" style="min-width:48px;">
                                                    <div class="d-flex flex-column align-items-center gap-1">
                                                        <button class="btn btn-outline-success btn-sm px-2 py-1 mb-1"><i class="bi bi-plus"></i></button>
                                                        <button class="btn btn-outline-secondary btn-sm px-2 py-1"><i class="bi bi-dash"></i></button>
                                                    </div>
                                                    <button class="btn btn-outline-danger btn-sm px-2 py-1 mt-3" style="min-width:32px;"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Producto 2 -->
                                        <div class="card border-0 shadow-sm mb-3 bg-white">
                                            <div class="card-body d-flex justify-content-between align-items-start p-3">
                                                <!-- Info del producto -->
                                                <div>
                                                    <div class="fw-bold fs-6 mb-1 text-dark">Polo crop Simpson | Amarillo | Standard</div>
                                                    <div class="text-muted small">Almacén: Huaquio 1</div>
                                                    <div class="text-muted small">SKU: PC208</div>
                                                    <div class="text-muted small">Precio Unitario: <span class="fw-semibold">S/25.00</span></div>
                                                    <div class="text-muted small">Cantidad: <span class="fw-semibold">2</span></div>
                                                    <div class="fw-bold mt-2 text-dark">Total: S/50.00</div>
                                                </div>
                                                <!-- Columna derecha: botones -->
                                                <div class="d-flex flex-column justify-content-between align-items-end ms-auto" style="min-width:48px;">
                                                    <div class="d-flex flex-column align-items-center gap-1">
                                                        <button class="btn btn-outline-success btn-sm px-2 py-1 mb-1"><i class="bi bi-plus"></i></button>
                                                        <button class="btn btn-outline-secondary btn-sm px-2 py-1"><i class="bi bi-dash"></i></button>
                                                    </div>
                                                    <button class="btn btn-outline-danger btn-sm px-2 py-1 mt-3" style="min-width:32px;"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Producto 3 -->
                                        <div class="card border-0 shadow-sm mb-3 bg-white">
                                            <div class="card-body d-flex justify-content-between align-items-start p-3">
                                                <!-- Info del producto -->
                                                <div>
                                                    <div class="fw-bold fs-6 mb-1 text-dark">Polo crop Simpson | Amarillo | Standard</div>
                                                    <div class="text-muted small">Almacén: Huaquio 1</div>
                                                    <div class="text-muted small">SKU: PC208</div>
                                                    <div class="text-muted small">Precio Unitario: <span class="fw-semibold">S/25.00</span></div>
                                                    <div class="text-muted small">Cantidad: <span class="fw-semibold">2</span></div>
                                                    <div class="fw-bold mt-2 text-dark">Total: S/50.00</div>
                                                </div>
                                                <!-- Columna derecha: botones -->
                                                <div class="d-flex flex-column justify-content-between align-items-end ms-auto" style="min-width:48px;">
                                                    <div class="d-flex flex-column align-items-center gap-1">
                                                        <button class="btn btn-outline-success btn-sm px-2 py-1 mb-1"><i class="bi bi-plus"></i></button>
                                                        <button class="btn btn-outline-secondary btn-sm px-2 py-1"><i class="bi bi-dash"></i></button>
                                                    </div>
                                                    <button class="btn btn-outline-danger btn-sm px-2 py-1 mt-3" style="min-width:32px;"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Botones flotantes, siempre abajo a la derecha -->

                                    <div class="d-flex justify-content-end align-items-end" style="height: 100px; pointer-events: none;">
                                        <div style="pointer-events: auto; display: flex; gap: 24px;">
                                            <button class="btn btn-success shadow d-flex align-items-center justify-content-center"
                                                style="width:72px; height:52px; border-radius:18px;" title="Escanear">
                                                <i class="bi bi-qr-code-scan" style="font-size:2rem;"></i>
                                            </button>
                                            <button class="btn btn-success shadow d-flex align-items-center justify-content-center"
                                                style="width:72px; height:52px; border-radius:18px;" title="Agregar">
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
        <!-- Bootstrap Icons CDN -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<?php
    } else {
        require "access.php";
    }
    require "footer.php";
}
ob_end_flush();
?>