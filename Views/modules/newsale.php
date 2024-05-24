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
        <!-- Main Content -->
        <div class="main-content">
            <section class="section">
                <div class="section-body">
                    <div class="row">
                        <div class="col-lg-6 col-md-6 col-xs-12">
                            <div class="card card-success">
                                <div class="card-header">
                                    <h4>Ventas</h4>
                                </div>
                                <div class="card-body">
                                    <!--FORMULARIO PARA REGISTRO-->
                                    <div id="formularioregistros">
                                        <form action="" name="formulario" id="formulario" method="POST">
                                            <div class="row">
                                                <div class="form-group col-lg-8 col-md-8 col-xs-12">
                                                    <label for="">Cliente(*):</label>
                                                    <input class="form-control" type="hidden" name="idventa" id="idventa">
                                                    <div class="input-group">
                                                        <select name="idcliente" id="idcliente" class="form-control" required>
                                                        </select>
                                                        <div class="input-group-append">
                                                            <button class="btn btn-success" type="button"
                                                                onclick="agregarCliente()">
                                                                <i class="fa fa-plus-circle"></i> Agregar
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group col-lg-4 col-md-4 col-xs-6">
                                                    <label for="">Comprobante(*):</label>
                                                    <select onchange="ShowComprobante()" name="tipo_comprobante"
                                                        id="tipo_comprobante" class="form-control" required>
                                                    </select>
                                                </div>

                                                <div class="form-group col-lg-4 col-md-4 col-xs-6">
                                                    <label for="">Serie: </label>
                                                    <input class="form-control" type="text" name="serie_comprobante"
                                                        id="serie_comprobante" maxlength="7" readonly required>
                                                </div>

                                                <div class="form-group col-lg-4 col-md-4 col-xs-6">
                                                    <label for="">Número: </label>
                                                    <input class="form-control" type="text" name="num_comprobante"
                                                        id="num_comprobante" maxlength="10" readonly required>
                                                </div>

                                                <!-- Campo para tipo de pago -->
                                                <div class="form-group col-lg-4 col-md-4 col-sm-4 col-xs-12">
                                                    <label for="">Tipo de pago(*):</label>
                                                    <select onchange="ShowTipopago()" name="tipo_pago" id="tipo_pago"
                                                        class="form-control selectpicker" data-live-search="true" required>
                                                        <option value="contado">Contado</option>
                                                        <option value="mixto">Mixto</option>
                                                        <!-- Agregar más opciones si es necesario -->
                                                    </select>
                                                </div>

                                                <!-- Campos adicionales para pagos mixtos -->
                                                <div id="pago_mixto" style="display: none;">
                                                    <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                        <label for="">Monto en Efectivo:</label>
                                                        <input class="form-control" type="number" step="0.01"
                                                            name="monto_efectivo" id="monto_efectivo">
                                                    </div>
                                                    <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                        <label for="">Monto con Tarjeta:</label>
                                                        <input class="form-control" type="number" step="0.01"
                                                            name="monto_tarjeta" id="monto_tarjeta">
                                                    </div>
                                                </div>

                                                <!-- Campos adicionales para pago a crédito -->
                                                <div id="pago_credito" style="display: none;">
                                                    <div class="form-group col-lg-4 col-md-4 col-xs-6">
                                                        <label for="fecha_pago">Fecha de Pago:</label>
                                                        <input class="form-control" type="date" name="fecha_pago"
                                                            id="fecha_pago">
                                                    </div>
                                                    <div class="form-group col-lg-4 col-md-4 col-xs-6">
                                                        <label for="monto_deuda">Monto de Deuda:</label>
                                                        <input class="form-control" type="number" step="0.01" name="monto_deuda"
                                                            id="monto_deuda">
                                                    </div>
                                                   
                                                </div>

                                                <div id="t_pago" class="form-group col-lg-4 col-md-4 col-xs-12">
                                                    <label for="">N° Cuotas </label>
                                                    <input class="form-control" type="text" name="num_transac" id="num_transac"
                                                        maxlength="45">
                                                </div>

                                                <!-- Tabla de detalles -->
                                                <div class="form-group col-lg-12 col-md-12 col-xs-12">
                                                    <div class="table-responsive">
                                                        <table id="detalles"
                                                            class="table table-striped table-hover text-center">
                                                            <thead class="bg-aqua">
                                                                <tr>
                                                                    <th>Opción</th>
                                                                    <th class="col-xs-6">Articulo</th>
                                                                    <th class="col-xs-1">Cantidad</th>
                                                                    <th class="col-xs-1">Precio</th>
                                                                    <th class="col-xs-1">Descuento</th>
                                                                    <th class="col-xs-1">Subtotal</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <!-- Información de totales -->
                                                <div class="form-group col-12">
                                                    <div class="p-3 mb-2 bg-light border">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center bg-primary text-white p-2 mb-2 rounded">
                                                            <label class="mb-0">SubTotal</label>
                                                            <span id="total" class="badge badge-primary">0.00</span>
                                                        </div>
                                                        <div
                                                            class="d-flex justify-content-between align-items-center bg-primary text-white p-2 mb-2 rounded">
                                                            <label id="valor_impuesto" class="mb-0">IVG 18%</label>
                                                            <span id="most_imp" class="badge badge-warning">0.00</span>
                                                        </div>
                                                        <div
                                                            class="d-flex justify-content-between align-items-center bg-primary text-white p-2 mb-2 rounded">
                                                            <label class="mb-0">TOTAL</label>
                                                            <span id="most_total" class="badge badge-success">0.00</span>
                                                        </div>
                                                        <div class="bg-warning text-dark p-2 mb-2 rounded">
                        <label class="mb-0">Cant. pagado</label>
                        <input type="hidden" step="0.01" name="total_venta" id="total_venta">
                        <input class="form-control" onchange="modificarSubtotales()" type="number" step="0.01"
                            name="tpagado" id="tpagado">
                    </div>  
                                                        <div
                                                            class="d-flex justify-content-between align-items-center bg-warning text-dark p-2 mb-2 rounded">
                                                            <label class="mb-0">Cambio</label>
                                                            <span id="vuelto" class="badge bg-danger">0.00</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group col-lg-12 col-md-12 col-sm-12 col-xs-12">
                                                    <button class="btn btn-primary" type="submit" id="btnGuardar">
                                                        <i class="fa fa-save"></i> Guardar
                                                    </button>
                                                    <a href="listsales">
                                                        <button class="btn btn-danger" type="button" id="btnCancelar">
                                                            <i class="fa fa-arrow-circle-left"></i> Cancelar
                                                        </button>
                                                    </a>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    <!--FIN FORMULARIO PARA REGISTRO-->
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-12">
                            <div class="card card-success" id="formularioregistros">
                                <div class="card-header">
                                    <h4>Agrega un articulo</h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="tblarticulos"
                                            class="table table-striped table-bordered table-condensed table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Opción</th>
                                                    <th>Nombre</th>
                                                    <th>Código</th>
                                                    <th>Stock</th>
                                                    <th>Imagen</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!--modal para agregar nuevo cliente-->
            <div class="modal fade" id="Modalcliente" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
                aria-hidden="true">
                <div class="modal-dialog" style="width: 65% !important;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Nuevo cliente</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form id="formulariocliente" method="POST">
                                <div class="row">
                                    <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                        <label for="nombre">Nombre</label>
                                        <input class="form-control" type="hidden" name="idpersona" id="idpersona">
                                        <input class="form-control" type="hidden" name="tipo_persona" id="tipo_persona"
                                            value="Cliente">
                                        <input class="form-control" type="text" name="nombre" id="nombre" maxlength="100"
                                            placeholder="Nombre del cliente" required>
                                    </div>
                                    <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                        <label for="tipo_documento">Tipo Documento</label>
                                        <select class="form-control select-picker" name="tipo_documento" id="tipo_documento"
                                            required>
                                            <option value="DNI">DNI</option>
                                            <option value="RUC">RUC</option>
                                            <option value="CEDULA">CEDULA</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                        <label for="num_documento">Número Documento</label>
                                        <input class="form-control" type="text" name="num_documento" id="num_documento"
                                            maxlength="20" placeholder="Número de Documento">
                                    </div>
                                    <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                        <label for="direccion">Direccion</label>
                                        <input class="form-control" type="text" name="direccion" id="direccion" maxlength="70"
                                            placeholder="Direccion">
                                    </div>
                                    <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                        <label for="telefono">Telefono</label>
                                        <input class="form-control" type="text" name="telefono" id="telefono" maxlength="20"
                                            placeholder="Número de Telefono">
                                    </div>
                                    <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                        <label for="email">Email</label>
                                        <input class="form-control" type="email" name="email" id="email" maxlength="50"
                                            placeholder="Email">
                                    </div>
                                    <div class="form-group col-lg-12 col-md-12 col-sm-12 col-xs-12">
                                        <button class="btn btn-primary" type="submit" id="btnGuardarcliente">
                                            <i class="fa fa-save"></i> Guardar
                                        </button>
                                        <button class="btn btn-danger" type="button" data-dismiss="modal">
                                            <i class="fa fa-arrow-circle-left"></i> Cancelar
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer"></div>
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
    <script src="Views/modules/scripts/generaldata.js"></script>
    <script src="Views/modules/scripts/newsale.js"></script>
    <?php
}
ob_end_flush();
?>