<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
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
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="box-title">
                                        Cotizaciones
                                        <button class="btn btn-success" id="btnagregar">
                                            <i class="fa fa-plus-circle"></i> Agregar
                                        </button>
                                    </h4>
                                </div>

                                <div class="card-body">
                                    <div class="table-responsive" id="listadoregistros">
                                        <table id="tbllistado" class="table table-striped table-hover text-nowrap" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Opciones</th>
                                                    <th>Fecha</th>
                                                    <th>Cliente</th>
                                                    <th>Usuario</th>
                                                    <th>Documento</th>
                                                    <th>Número</th>
                                                    <th>Total</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- MODAL VER -->
        <div class="modal fade" id="getCodeModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="formModal">Vista de cotización</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="form-group col-lg-8 col-md-8 col-xs-12">
                                <label>Cliente(*):</label>
                                <input class="form-control" type="hidden" name="idventam" id="idventam">
                                <input class="form-control" type="text" name="cliente" id="cliente" readonly>
                            </div>

                            <div class="form-group col-lg-4 col-md-4 col-xs-6">
                                <label>Fecha:</label>
                                <div class="input-group">
                                    <input class="form-control pull-right" type="text" name="fecha_horam" id="fecha_horam" readonly>
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <i class="fas fa-calendar"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group col-lg-4 col-md-4 col-xs-6">
                                <label>Comprobante(*):</label>
                                <input class="form-control" type="text" name="tipo_comprobantem" id="tipo_comprobantem" readonly>
                            </div>

                            <div class="form-group col-lg-2 col-md-2 col-xs-6">
                                <label>Serie:</label>
                                <input class="form-control" type="text" name="serie_comprobantem" id="serie_comprobantem" readonly>
                            </div>

                            <div class="form-group col-lg-2 col-md-2 col-xs-6">
                                <label>Número:</label>
                                <input class="form-control" type="text" name="num_comprobantem" id="num_comprobantem" readonly>
                            </div>

                            <div class="form-group col-lg-4 col-md-4 col-xs-6">
                                <label>Impuesto:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="impuestom" id="impuestom" readonly>
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <i class="fas fa-percent"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group col-lg-12 col-md-12 col-xs-12">
                                <div class="row mt-2">
                                    <div class="form-group col-lg-4 col-md-4 col-xs-12">
                                        <label>Forma de pago:</label>
                                        <input type="text" class="form-control" id="tipo_pagom" readonly>
                                    </div>

                                    <div class="form-group col-lg-4 col-md-4 col-xs-12">
                                        <label>Condición:</label>
                                        <input type="text" class="form-control" id="condicion_pagom" readonly>
                                    </div>
                                </div>

                                <div class="row" id="bloquePagoMixto" style="display:none;">
                                    <div class="col-lg-12">
                                        <label>Detalle del pago:</label>
                                        <table class="table table-sm table-bordered">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Forma de pago</th>
                                                    <th class="text-right">Monto</th>
                                                </tr>
                                            </thead>
                                            <tbody id="detallePagom"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <table id="detallesm" class="table table-striped table-bordered table-condensed table-hover">
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-primary" type="button" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
<?php
    } else {
        require "access.php";
    }

    require "footer.php";
?>
    <script src="Views/modules/scripts/listcotizacion.js"></script>
<?php
}
ob_end_flush();
?>