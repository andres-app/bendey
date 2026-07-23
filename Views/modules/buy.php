<?php

ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Lima');

if (!isset($_SESSION['nombre'])) {
    header('Location: login');
    exit;
}

require 'header.php';
require 'sidebar.php';

if ((int)($_SESSION['compras'] ?? 0) === 1) {
?>

<style>
    .compra-page .card {
        border: 0;
        border-radius: 18px;
        box-shadow: 0 12px 35px rgba(15, 23, 42, .07);
    }

    .compra-page .card-header {
        border-bottom: 1px solid #edf1ef;
        background: #fff;
    }

    .compra-page .form-control {
        min-height: 44px;
        border-color: #dce4df;
        border-radius: 10px;
    }

    .compra-page textarea.form-control {
        min-height: 86px;
    }

    .compra-page .form-control:focus {
        border-color: #52b848;
        box-shadow: 0 0 0 .18rem rgba(82, 184, 72, .12);
    }

    .compra-actions {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .compra-action-btn {
        min-height: 74px;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 12px;
        padding: 14px 16px;
        border: 1px solid #dfe8e2;
        border-radius: 14px;
        background: #fff;
        text-align: left;
        transition: .16s ease;
    }

    .compra-action-btn:hover,
    .compra-action-btn:focus {
        transform: translateY(-1px);
        border-color: #93cda3;
        background: #f3fbf5;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
        outline: none;
    }

    .compra-action-icon {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        color: #278c46;
        background: #eaf7ee;
        font-size: 1.1rem;
    }

    .compra-action-title {
        color: #243128;
        font-weight: 700;
    }

    .compra-action-help {
        margin-top: 2px;
        color: #77847c;
        font-size: .77rem;
        line-height: 1.25;
    }

    .detalle-compra-table thead th {
        border-top: 0;
        border-bottom: 1px solid #dfe7e2;
        color: #617067;
        background: #f7f9f8;
        font-size: .77rem;
        font-weight: 800;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .detalle-compra-table td {
        vertical-align: middle;
    }

    .detalle-compra-table .form-control {
        min-width: 100px;
        min-height: 39px;
        padding: 6px 9px;
    }

    .detalle-tipo {
        display: inline-flex;
        align-items: center;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .detalle-tipo-inventario {
        color: #1d7438;
        background: #eaf7ee;
    }

    .detalle-tipo-gasto {
        color: #5b6472;
        background: #eef1f4;
    }

    .compra-empty {
        padding: 42px 18px;
        color: #98a29c;
        text-align: center;
    }

    .compra-empty i {
        display: block;
        margin-bottom: 12px;
        color: #d3dcd6;
        font-size: 2.5rem;
    }

    .compra-total-box {
        border: 1px solid #dfe8e2;
        border-radius: 16px;
        background: #f8faf9;
        overflow: hidden;
    }

    .compra-total-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 15px;
        color: #5e6c64;
    }

    .compra-total-row + .compra-total-row {
        border-top: 1px solid #e5ebe7;
    }

    .compra-total-row.total-final {
        color: #1e2b23;
        background: #fff;
        font-size: 1.15rem;
        font-weight: 800;
    }

    .producto-compra-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border: 1px solid #e2e9e5;
        border-radius: 13px;
        background: #fff;
    }

    .producto-compra-item + .producto-compra-item {
        margin-top: 10px;
    }

    .producto-compra-item:hover {
        border-color: #a9d5b5;
        background: #f7fcf8;
    }

    .producto-compra-thumb {
        width: 54px;
        height: 54px;
        flex: 0 0 54px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 11px;
        background: #eef3f0;
    }

    .producto-compra-thumb img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .producto-compra-meta {
        min-width: 0;
        flex: 1 1 auto;
    }

    .producto-compra-nombre {
        color: #28352d;
        font-weight: 800;
    }

    .producto-compra-sub {
        margin-top: 3px;
        color: #7a8880;
        font-size: .76rem;
    }

    .modal-compra .modal-content {
        border: 0;
        border-radius: 18px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .24);
    }

    .modal-compra .modal-header {
        border-bottom: 1px solid #e7ece9;
    }

    .modal-compra .modal-footer {
        border-top: 1px solid #e7ece9;
    }

    .coincidencias-producto {
        display: none;
        margin-top: 10px;
        padding: 10px 12px;
        border: 1px solid #f0dca5;
        border-radius: 11px;
        color: #795c18;
        background: #fff9e8;
        font-size: .8rem;
    }

    @media (max-width: 991.98px) {
        .compra-actions {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .compra-page .card-body {
            padding-left: 14px;
            padding-right: 14px;
        }

        .compra-action-btn {
            min-height: 66px;
        }
    }
</style>

<div class="main-content compra-page">
    <section class="section">
        <div class="section-body">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0">Compras</h4>
                                <small class="text-muted">
                                    Registra mercadería, productos nuevos, gastos y servicios.
                                </small>
                            </div>

                            <button
                                type="button"
                                class="btn btn-success"
                                onclick="mostrarform(true)"
                                id="btnagregar">
                                <i class="fas fa-plus-circle mr-1"></i>
                                Nueva compra
                            </button>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive" id="listadoregistros">
                                <table
                                    id="tbllistado"
                                    class="table table-striped table-hover text-nowrap"
                                    style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Opciones</th>
                                            <th>Fecha</th>
                                            <th>Proveedor</th>
                                            <th>Usuario</th>
                                            <th>Documento</th>
                                            <th>Número</th>
                                            <th>Tipo de compra</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <div id="formularioregistros" style="display:none;">
                                <form
                                    name="formulario"
                                    id="formulario"
                                    method="POST"
                                    autocomplete="off">

                                    <input type="hidden" name="idingreso" id="idingreso" value="">
                                    <input type="hidden" name="detalles_json" id="detalles_json" value="[]">
                                    <input type="hidden" name="total_compra" id="total_compra" value="0.00">

                                    <div class="row">
                                        <div class="form-group col-lg-6 col-md-6">
                                            <label for="idproveedor">Proveedor <span class="text-danger">*</span></label>
                                            <select
                                                name="idproveedor"
                                                id="idproveedor"
                                                class="form-control"
                                                required>
                                                <option value="">Cargando proveedores...</option>
                                            </select>
                                        </div>

                                        <div class="form-group col-lg-3 col-md-3">
                                            <label for="fecha_hora">Fecha <span class="text-danger">*</span></label>
                                            <input
                                                class="form-control"
                                                type="date"
                                                name="fecha_hora"
                                                id="fecha_hora"
                                                required>
                                        </div>

                                        <div class="form-group col-lg-3 col-md-3">
                                            <label for="impuesto">Impuesto</label>
                                            <select
                                                class="form-control"
                                                name="impuesto"
                                                id="impuesto">
                                                <option value="18">IGV 18% incluido</option>
                                                <option value="0">Sin IGV</option>
                                            </select>
                                        </div>

                                        <div class="form-group col-lg-4 col-md-4">
                                            <label for="tipo_comprobante">Tipo de comprobante <span class="text-danger">*</span></label>
                                            <select
                                                name="tipo_comprobante"
                                                id="tipo_comprobante"
                                                class="form-control"
                                                required>
                                                <option value="Factura">Factura</option>
                                                <option value="Boleta">Boleta</option>
                                                <option value="Ticket">Ticket</option>
                                                <option value="Recibo">Recibo</option>
                                                <option value="Otro">Otro</option>
                                            </select>
                                        </div>

                                        <div class="form-group col-lg-4 col-md-4">
                                            <label for="serie_comprobante">Serie</label>
                                            <input
                                                class="form-control text-uppercase"
                                                type="text"
                                                name="serie_comprobante"
                                                id="serie_comprobante"
                                                maxlength="7"
                                                placeholder="Ej.: F001">
                                        </div>

                                        <div class="form-group col-lg-4 col-md-4">
                                            <label for="num_comprobante">Número <span class="text-danger">*</span></label>
                                            <input
                                                class="form-control"
                                                type="text"
                                                name="num_comprobante"
                                                id="num_comprobante"
                                                maxlength="10"
                                                placeholder="Ej.: 00001234"
                                                required>
                                        </div>

                                        <div class="form-group col-12">
                                            <label for="observacion">Observación</label>
                                            <textarea
                                                class="form-control"
                                                name="observacion"
                                                id="observacion"
                                                maxlength="255"
                                                rows="2"
                                                placeholder="Información adicional de la compra..."></textarea>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-end mb-2">
                                            <div>
                                                <h6 class="mb-1">Detalles de la compra</h6>
                                                <small class="text-muted">
                                                    Puedes combinar productos de inventario con gastos o servicios.
                                                </small>
                                            </div>
                                        </div>

                                        <div class="compra-actions">
                                            <button
                                                type="button"
                                                class="compra-action-btn"
                                                id="btnProductoExistente">
                                                <span class="compra-action-icon">
                                                    <i class="fas fa-box"></i>
                                                </span>
                                                <span>
                                                    <span class="compra-action-title d-block">Producto existente</span>
                                                    <span class="compra-action-help d-block">
                                                        Compra mercadería registrada y aumenta su stock.
                                                    </span>
                                                </span>
                                            </button>

                                            <button
                                                type="button"
                                                class="compra-action-btn"
                                                id="btnProductoNuevo">
                                                <span class="compra-action-icon">
                                                    <i class="fas fa-box-open"></i>
                                                </span>
                                                <span>
                                                    <span class="compra-action-title d-block">Producto nuevo</span>
                                                    <span class="compra-action-help d-block">
                                                        Crea el producto al guardar la compra, sin duplicar stock.
                                                    </span>
                                                </span>
                                            </button>

                                            <button
                                                type="button"
                                                class="compra-action-btn"
                                                id="btnGastoServicio">
                                                <span class="compra-action-icon">
                                                    <i class="fas fa-file-invoice-dollar"></i>
                                                </span>
                                                <span>
                                                    <span class="compra-action-title d-block">Gasto o servicio</span>
                                                    <span class="compra-action-help d-block">
                                                        Registra transporte, alquiler, publicidad u otros consumos.
                                                    </span>
                                                </span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="table-responsive mt-4">
                                        <table class="table detalle-compra-table" id="detalles">
                                            <thead>
                                                <tr>
                                                    <th>Tipo</th>
                                                    <th style="min-width:220px;">Descripción</th>
                                                    <th>Cantidad</th>
                                                    <th>Costo unitario</th>
                                                    <th>Precio venta</th>
                                                    <th>Importe</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody id="detallesCompraBody"></tbody>
                                        </table>

                                        <div class="compra-empty" id="detalleCompraVacio">
                                            <i class="fas fa-shopping-basket"></i>
                                            <div class="font-weight-bold">Todavía no agregaste detalles</div>
                                            <small>
                                                Selecciona un producto existente, registra uno nuevo o agrega un gasto.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="row justify-content-end mt-3">
                                        <div class="col-lg-5 col-md-6">
                                            <div class="compra-total-box">
                                                <div class="compra-total-row">
                                                    <span>Subtotal</span>
                                                    <strong id="total">S/ 0.00</strong>
                                                </div>
                                                <div class="compra-total-row">
                                                    <span id="labelImpuestoTotal">IGV 18%</span>
                                                    <strong id="most_imp">S/ 0.00</strong>
                                                </div>
                                                <div class="compra-total-row total-final">
                                                    <span>Total compra</span>
                                                    <strong id="most_total">S/ 0.00</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-column flex-sm-row justify-content-end mt-4" style="gap:10px;">
                                        <button
                                            class="btn btn-outline-secondary px-4"
                                            onclick="cancelarform()"
                                            type="button"
                                            id="btnCancelar">
                                            <i class="fas fa-arrow-left mr-1"></i>
                                            Cancelar
                                        </button>

                                        <button
                                            class="btn btn-success px-4"
                                            type="submit"
                                            id="btnGuardar"
                                            disabled>
                                            <i class="fas fa-save mr-1"></i>
                                            Guardar compra
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- PRODUCTO EXISTENTE -->
<div class="modal fade modal-compra" id="modalProductoExistente" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Seleccionar producto existente</h5>
                    <small class="text-muted">Busca por nombre, SKU o código de barras.</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                    </div>
                    <input
                        type="text"
                        class="form-control"
                        id="buscarProductoCompra"
                        autocomplete="off"
                        placeholder="Nombre o SKU...">
                </div>

                <div id="listaProductosCompra" style="max-height:480px; overflow-y:auto;"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- PRODUCTO NUEVO -->
<div class="modal fade modal-compra" id="modalProductoNuevo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="formProductoNuevo" autocomplete="off">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Registrar producto nuevo</h5>
                        <small class="text-muted">
                            El producto se creará definitivamente cuando guardes la compra.
                        </small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="form-group col-lg-8 col-md-8">
                            <label for="nuevo_nombre">Nombre <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="nuevo_nombre"
                                maxlength="100"
                                required
                                placeholder="Ej.: Polo oversize rosado">
                        </div>

                        <div class="form-group col-lg-4 col-md-4">
                            <label for="nuevo_codigo">SKU o código</label>
                            <input
                                type="text"
                                class="form-control text-uppercase"
                                id="nuevo_codigo"
                                maxlength="50"
                                placeholder="Se genera si queda vacío">
                        </div>
                    </div>

                    <div class="coincidencias-producto" id="coincidenciasProductoNuevo"></div>

                    <div class="row mt-2">
                        <div class="form-group col-lg-3 col-md-6">
                            <label for="nuevo_idcategoria">Categoría <span class="text-danger">*</span></label>
                            <select class="form-control" id="nuevo_idcategoria" required></select>
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                            <label for="nuevo_idsubcategoria">Subcategoría</label>
                            <select class="form-control" id="nuevo_idsubcategoria"></select>
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                            <label for="nuevo_idmedida">Unidad <span class="text-danger">*</span></label>
                            <select class="form-control" id="nuevo_idmedida" required></select>
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                            <label for="nuevo_idalmacen">Almacén <span class="text-danger">*</span></label>
                            <select class="form-control" id="nuevo_idalmacen" required></select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-lg-4 col-md-4">
                            <label for="nuevo_cantidad">Cantidad comprada <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                class="form-control"
                                id="nuevo_cantidad"
                                min="1"
                                step="1"
                                value="1"
                                required>
                            <small class="text-muted">Esta cantidad será el stock que ingresa.</small>
                        </div>

                        <div class="form-group col-lg-4 col-md-4">
                            <label for="nuevo_precio_compra">Costo unitario <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">S/</span>
                                </div>
                                <input
                                    type="number"
                                    class="form-control"
                                    id="nuevo_precio_compra"
                                    min="0.01"
                                    step="0.01"
                                    required>
                            </div>
                        </div>

                        <div class="form-group col-lg-4 col-md-4">
                            <label for="nuevo_precio_venta">Precio de venta</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">S/</span>
                                </div>
                                <input
                                    type="number"
                                    class="form-control"
                                    id="nuevo_precio_venta"
                                    min="0"
                                    step="0.01"
                                    placeholder="Opcional">
                            </div>
                            <small class="text-muted">Podrás definirlo después si aún no lo conoces.</small>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus mr-1"></i>
                        Agregar a la compra
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- GASTO O SERVICIO -->
<div class="modal fade modal-compra" id="modalGastoServicio" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="formGastoServicio" autocomplete="off">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Agregar gasto o servicio</h5>
                        <small class="text-muted">No modificará el stock ni generará movimiento de kardex.</small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="form-group col-lg-8 col-md-8">
                            <label for="gasto_descripcion">Descripción <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="gasto_descripcion"
                                maxlength="250"
                                required
                                placeholder="Ej.: Servicio de transporte de mercadería">
                        </div>

                        <div class="form-group col-lg-4 col-md-4">
                            <label for="gasto_categoria">Categoría <span class="text-danger">*</span></label>
                            <select class="form-control" id="gasto_categoria" required></select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-lg-4 col-md-4">
                            <label for="gasto_idmedida">Unidad</label>
                            <select class="form-control" id="gasto_idmedida"></select>
                        </div>

                        <div class="form-group col-lg-4 col-md-4">
                            <label for="gasto_cantidad">Cantidad <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                class="form-control"
                                id="gasto_cantidad"
                                min="0.001"
                                step="0.001"
                                value="1"
                                required>
                        </div>

                        <div class="form-group col-lg-4 col-md-4">
                            <label for="gasto_precio">Costo unitario <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">S/</span>
                                </div>
                                <input
                                    type="number"
                                    class="form-control"
                                    id="gasto_precio"
                                    min="0.01"
                                    step="0.01"
                                    required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus mr-1"></i>
                        Agregar a la compra
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- VER COMPRA -->
<div class="modal fade modal-compra" id="getCodeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Detalle de la compra</h5>
                    <small class="text-muted" id="vistaCompraDocumento"></small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <small class="text-muted d-block">Proveedor</small>
                        <strong id="vistaCompraProveedor">-</strong>
                    </div>
                    <div class="col-md-3 mb-2">
                        <small class="text-muted d-block">Fecha</small>
                        <strong id="vistaCompraFecha">-</strong>
                    </div>
                    <div class="col-md-3 mb-2">
                        <small class="text-muted d-block">Tipo</small>
                        <strong id="vistaCompraTipo">-</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Costo</th>
                                <th>Importe</th>
                            </tr>
                        </thead>
                        <tbody id="detallesm"></tbody>
                    </table>
                </div>

                <div class="text-right mt-3">
                    <small class="text-muted d-block">Total</small>
                    <strong id="vistaCompraTotal" style="font-size:1.4rem;">S/ 0.00</strong>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php
} else {
    require 'access.php';
}

require 'footer.php';

$rutaBuyJs = __DIR__ . '/scripts/buy.js';
$versionBuyJs = file_exists($rutaBuyJs) ? filemtime($rutaBuyJs) : time();
?>

<script src="Views/modules/scripts/generaldata.js"></script>
<script src="Views/modules/scripts/buy.js?v=<?= (int)$versionBuyJs ?>"></script>

<?php
ob_end_flush();
?>
