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
                                    <!-- FORMULARIO PARA REGISTRO -->
                                    <div id="formularioregistros">
                                        <form action="" name="formulario" id="formulario" method="POST">
                                            <div class="row">
                                                <!-- Cliente -->
                                                <div class="form-group col-2">
                                                    <label for="">Tipo Documento</label>
                                                    <select class="form-control" name="tipo_documento" id="tipo_documento" required>
                                                        <option value="DNI">DNI</option>
                                                        <option value="RUC">RUC</option>
                                                    </select>
                                                </div>
                                                <div class="form-group col-3">
                                                    <label for="">Número Documento</label>
                                                    <div class="input-group">
                                                        <input class="form-control" type="text" name="num_documento" id="num_documento" maxlength="20" placeholder="N° Documento">
                                                        <button type="button" class="btn btn-primary" onclick="consultarCliente()"><i class="fa fa-search"></i></button>
                                                    </div>
                                                </div>
                                                <div class="form-group col-7">
                                                    <label for="">Nombre</label>
                                                    <input class="form-control" type="hidden" name="idpersona" id="idpersona">
                                                    <input class="form-control" type="hidden" name="tipo_persona" id="tipo_persona" value="Cliente">
                                                    <input class="form-control" type="text" name="nombre" id="nombre" maxlength="100" placeholder="Nombre del cliente" required>
                                                </div>
                                                <div class="form-group col-12">
                                                    <label for="">Dirección</label>
                                                    <input class="form-control" type="text" name="direccion" id="direccion" maxlength="70" placeholder="Dirección">
                                                </div>
                                                <!-- Comprobante -->
                                                <div class="form-group col-4">
                                                    <label for="">Comprobante(*):</label>
                                                    <select onchange="ShowComprobante()" name="tipo_comprobante" id="tipo_comprobante" class="form-control" required>
                                                    </select>
                                                </div>
                                                <!-- Serie y Número -->
                                                <div class="form-group col-4">
                                                    <label for="">Serie:</label>
                                                    <input class="form-control" type="text" name="serie_comprobante" id="serie_comprobante" maxlength="7" readonly required>
                                                </div>
                                                <div class="form-group col-4">
                                                    <label for="">Número:</label>
                                                    <input class="form-control" type="text" name="num_comprobante" id="num_comprobante" maxlength="10" readonly required>
                                                </div>
                                                <!-- Tipo de pago -->
                                                <div class="form-group col-4">
                                                    <label for="">Tipo de pago(*):</label>
                                                    <select onchange="ShowTipopago()" name="tipo_pago" id="tipo_pago" class="form-control selectpicker" data-live-search="true" required>
                                                        <option value="contado">Contado</option>
                                                        <option value="mixto">Mixto</option>
                                                        <option value="credito">Crédito</option>
                                                    </select>
                                                </div>
                                                <!-- Campos adicionales para pagos mixtos -->
                                                <div id="pago_mixto" class="col-12 mt-3" style="display: none;">
                                                    <div class="row">
                                                        <div class="form-group col-6">
                                                            <label for="">Monto en Efectivo:</label>
                                                            <input class="form-control" type="number" step="0.01" name="monto_efectivo" id="monto_efectivo">
                                                        </div>
                                                        <div class="form-group col-6">
                                                            <label for="">Monto con Tarjeta:</label>
                                                            <input class="form-control" type="number" step="0.01" name="monto_tarjeta" id="monto_tarjeta">
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Campos adicionales para pago a crédito -->
                                                <div id="pago_credito" class="col-12 mt-3" style="display: none;">
                                                    <!-- Contenedor para cuotas adicionales -->
                                                    <div id="cuotas_adicionales"></div>
                                                    <!-- Botón para agregar más cuotas -->
                                                    <button type="button" class="btn btn-success mt-3" id="agregar_cuota">
                                                        <i class="fa fa-plus-circle"></i> Agregar Cuota
                                                    </button>
                                                </div>
                                                <!-- Tabla de detalles -->
                                                <div class="form-group col-12 mt-3">
                                                    <div class="table-responsive">
                                                        <table id="detalles" class="table table-striped table-hover text-center">
                                                            <thead class="bg-aqua">
                                                                <tr>
                                                                    <th>Opción</th>
                                                                    <th>Artículo</th>
                                                                    <th>Cantidad</th>
                                                                    <th>Precio</th>
                                                                    <th>Descuento</th>
                                                                    <th>Subtotal</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <!-- Información de totales -->
                                                <div class="form-group col-12 mt-3">
                                                    <div class="p-3 mb-2 bg-light border">
                                                        <div class="d-flex justify-content-between align-items-center bg-primary text-white p-2 mb-2 rounded">
                                                            <label class="mb-0">SubTotal</label>
                                                            <span id="total" class="badge badge-primary">0.00</span>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center bg-primary text-white p-2 mb-2 rounded">
                                                            <label id="valor_impuesto" class="mb-0">IGV 18%</label>
                                                            <span id="most_imp" class="badge badge-warning">0.00</span>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center bg-primary text-white p-2 mb-2 rounded">
                                                            <label class="mb-0">TOTAL</label>
                                                            <span id="most_total" class="badge badge-success">0.00</span>
                                                        </div>
                                                        <div class="bg-warning text-dark p-2 mb-2 rounded">
                                                            <label class="mb-0">Cant. pagado</label>
                                                            <input type="hidden" step="0.01" name="total_venta" id="total_venta">
                                                            <input class="form-control" onchange="modificarSubtotales()" type="number" step="0.01" name="tpagado" id="tpagado">
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center bg-warning text-dark p-2 mb-2 rounded">
                                                            <label class="mb-0">Cambio</label>
                                                            <span id="vuelto" class="badge bg-danger">0.00</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Botones de acción -->
                                                <div class="form-group col-12 mt-3">
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
                        <!-- Tabla de artículos -->
                        <div class="col-lg-6 col-md-6 col-12">
                            <div class="card card-success" id="formularioregistros">
                                <div class="card-header">
                                    <h4>Agrega un artículo</h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="tblarticulos" class="table table-striped table-bordered table-condensed table-hover">
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
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.getElementById('agregar_cuota').addEventListener('click', function () {
                    const contenedorCuotas = document.getElementById('cuotas_adicionales');

                    const nuevaCuota = document.createElement('div');
                    nuevaCuota.classList.add('row', 'mt-2', 'p-3', 'border', 'rounded', 'bg-light');

                    nuevaCuota.innerHTML = `
                    <div class="form-group col-lg-6 col-md-6 col-sm-6 col-xs-12">
                        <label for="fecha_pago">Fecha de Pago:</label>
                        <input class="form-control" type="date" name="fecha_pago[]" id="fecha_pago">
                    </div>
                    <div class="form-group col-lg-6 col-md-6 col-sm-6 col-xs-12">
                        <label for="monto_deuda">Monto de Deuda:</label>
                        <input class="form-control" type="number" step="0.01" name="monto_deuda[]" id="monto_deuda">
                    </div>
                `;

                    contenedorCuotas.appendChild(nuevaCuota);
                });
            });
        </script>

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
