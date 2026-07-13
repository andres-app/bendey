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

if (!empty($_SESSION['ventas']) && (int)$_SESSION['ventas'] === 1) {
?>
<style>
    .cobranza-kpi {
        border: 1px solid #edf0f4;
        border-radius: 12px;
        padding: 14px;
        background: #fafbfc;
    }

    .cobranza-kpi small {
        color: #77808f;
        display: block;
        margin-bottom: 4px;
    }

    .cobranza-kpi strong {
        font-size: 1.15rem;
    }

    .cuota-row-pagada {
        opacity: .62;
        background: #f5f7f9;
    }

    .pago-linea {
        border: 1px solid #e8ebef;
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 10px;
        background: #fff;
    }
</style>

<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="card">
                <div class="card-header">
                    <h4>
                        <i class="fas fa-file-invoice-dollar text-success"></i>
                        Cuentas por cobrar
                    </h4>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table
                            id="tablaCobranzas"
                            class="table table-striped table-hover text-nowrap"
                            style="width:100%;">
                            <thead>
                                <tr>
                                    <th>Acción</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Factura</th>
                                    <th>Total</th>
                                    <th>Pagado</th>
                                    <th>Saldo</th>
                                    <th>Próx. vencimiento</th>
                                    <th>Estado</th>
                                    <th>SUNAT</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div
    class="modal fade"
    id="modalCobranza"
    tabindex="-1"
    role="dialog"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar cobranza</h5>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="cobranzaIdVenta">

                <div class="row mb-4">
                    <div class="col-md-4 mb-2">
                        <div class="cobranza-kpi">
                            <small>Cliente</small>
                            <strong id="cobranzaCliente">-</strong>
                        </div>
                    </div>

                    <div class="col-md-3 mb-2">
                        <div class="cobranza-kpi">
                            <small>Factura</small>
                            <strong id="cobranzaFactura">-</strong>
                        </div>
                    </div>

                    <div class="col-md-2 mb-2">
                        <div class="cobranza-kpi">
                            <small>Total</small>
                            <strong id="cobranzaTotal">S/ 0.00</strong>
                        </div>
                    </div>

                    <div class="col-md-3 mb-2">
                        <div class="cobranza-kpi">
                            <small>Estado SUNAT</small>
                            <strong id="cobranzaSunat">-</strong>
                        </div>
                    </div>
                </div>

                <h6>Aplicación a cuotas</h6>

                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Aplicar</th>
                                <th>Cuota</th>
                                <th>Vencimiento</th>
                                <th class="text-right">Monto</th>
                                <th class="text-right">Pagado</th>
                                <th class="text-right">Saldo</th>
                                <th style="width:170px;">Monto a aplicar</th>
                                <th>Estado</th>
                            </tr>
                        </thead>

                        <tbody id="tablaCuotasCobranza"></tbody>

                        <tfoot>
                            <tr>
                                <th colspan="6" class="text-right">Total aplicado:</th>
                                <th id="totalAplicadoCobranza">S/ 0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Formas de pago</h6>

                    <button
                        type="button"
                        class="btn btn-outline-success btn-sm"
                        id="btnAgregarPagoCobranza">
                        <i class="fas fa-plus-circle"></i>
                        Agregar forma
                    </button>
                </div>

                <div id="contenedorPagosCobranza"></div>

                <div class="row mt-3">
                    <div class="col-md-8">
                        <label for="observacionCobranza">Observación</label>

                        <textarea
                            id="observacionCobranza"
                            class="form-control"
                            rows="2"></textarea>
                    </div>

                    <div class="col-md-4">
                        <div class="cobranza-kpi mt-4">
                            <small>Total formas de pago</small>
                            <strong id="totalPagosCobranza">S/ 0.00</strong>
                        </div>
                    </div>
                </div>

                <hr>

                <h6>Historial de cobranzas</h6>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Código</th>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Formas de pago</th>
                                <th class="text-right">Monto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>

                        <tbody id="historialCobranza"></tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-dismiss="modal">
                    Cerrar
                </button>

                <button
                    type="button"
                    id="btnGuardarCobranza"
                    class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Registrar cobranza
                </button>
            </div>
        </div>
    </div>
</div>

<?php
} else {
    require 'access.php';
}

require 'footer.php';

$rutaJs = __DIR__ . '/scripts/cobranzas.js';
$versionJs = file_exists($rutaJs)
    ? filemtime($rutaJs)
    : time();
?>

<script
    src="Views/modules/scripts/cobranzas.js?v=<?= $versionJs ?>">
</script>

<?php
ob_end_flush();
?>
