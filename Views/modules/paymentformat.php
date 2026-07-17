<?php
ob_start();
session_start();

if (!isset($_SESSION['nombre'])) {
    header("location: login");
} else {

    require "header.php";
    require "sidebar.php";

    if ($_SESSION['settings'] == 1) {
?>
        <!-- Main Content -->
        <div class="main-content">
            <section class="section">
                <div class="section-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4>
                                        Formas de pago
                                        <button class="btn btn-success" onclick="mostrarform(true)" id="btnagregar">
                                            <i class="fa fa-plus-circle"></i> Agregar
                                        </button>
                                    </h4>
                                </div>

                                <!-- LISTADO -->
                                <div class="card-body">
                                    <div class="table-responsive" id="listadoregistros">
                                        <table id="tbllistado" class="table table-striped table-hover text-nowrap" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Opciones</th>
                                                    <th>Nombre</th>
                                                    <th>Tipo</th>
                                                    <th>Condición</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>

                                    <!-- FORMULARIO -->
                                    <div id="formularioregistros" style="display:none;">
                                        <form id="formulario" method="POST">

                                            <input
                                                type="hidden"
                                                name="idforma_pago"
                                                id="idforma_pago">

                                            <div class="alert alert-info mb-4" role="alert">
                                                <div class="d-flex align-items-start">
                                                    <i
                                                        class="fas fa-info-circle mr-3 mt-1"
                                                        style="font-size: 1.25rem;"></i>

                                                    <div>
                                                        <div class="font-weight-bold mb-2">
                                                            ¿Cómo configurar una forma de pago?
                                                        </div>

                                                        <div class="small">
                                                            <strong>Nombre:</strong>
                                                            escribe el nombre que verá el usuario, por ejemplo:
                                                            Efectivo, Yape, Plin, Tarjeta o Transferencia.
                                                        </div>

                                                        <div class="small mt-1">
                                                            <strong>¿Es efectivo?:</strong>
                                                            selecciona <strong>Sí</strong> únicamente cuando se trate
                                                            de dinero físico. Para Yape, Plin, tarjetas o transferencias,
                                                            selecciona <strong>No</strong>.
                                                        </div>

                                                        <div class="small mt-1">
                                                            <strong>Condición:</strong>
                                                            selecciona <strong>Contado</strong> cuando el pago se realiza
                                                            en el momento de la venta. Usa <strong>Crédito</strong>
                                                            solamente cuando el importe quedará pendiente.
                                                        </div>

                                                        <div class="small mt-2">
                                                            <strong>Ejemplos:</strong>
                                                            Efectivo = Sí / Contado ·
                                                            Yape = No / Contado ·
                                                            Tarjeta = No / Contado.
                                                        </div>

                                                        <div class="small mt-2 text-primary">
                                                            <strong>Pago mixto:</strong>
                                                            no representa dinero por sí mismo. Permite combinar dos o
                                                            más formas activas, por ejemplo Efectivo + Yape.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">

                                                <!-- Nombre -->
                                                <div class="form-group col-lg-6 col-md-6 col-sm-12">
                                                    <label for="nombre">
                                                        Nombre de la forma de pago
                                                    </label>

                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        name="nombre"
                                                        id="nombre"
                                                        maxlength="100"
                                                        placeholder="Ejemplo: Yape, Plin o Tarjeta"
                                                        autocomplete="off"
                                                        required>

                                                    <small class="form-text text-muted">
                                                        Este nombre aparecerá en la pantalla de ventas.
                                                    </small>
                                                </div>

                                                <!-- Es efectivo -->
                                                <div class="form-group col-lg-3 col-md-3 col-sm-12">
                                                    <label for="es_efectivo">
                                                        ¿Es dinero en efectivo?
                                                    </label>

                                                    <select
                                                        class="form-control"
                                                        name="es_efectivo"
                                                        id="es_efectivo"
                                                        required>
                                                        <option value="1">Sí, dinero físico</option>
                                                        <option value="0" selected>No, pago electrónico</option>
                                                    </select>

                                                    <small class="form-text text-muted">
                                                        Influye en el cálculo del vuelto y el arqueo de caja.
                                                    </small>
                                                </div>

                                                <!-- Condición -->
                                                <div class="form-group col-lg-3 col-md-3 col-sm-12">
                                                    <label for="condicion">
                                                        Momento del pago
                                                    </label>

                                                    <select
                                                        class="form-control"
                                                        name="condicion"
                                                        id="condicion"
                                                        required>
                                                        <option value="1">Contado — pago inmediato</option>
                                                        <option value="2">Crédito — pago posterior</option>
                                                    </select>

                                                    <small class="form-text text-muted">
                                                        Para Efectivo, Yape, Plin y Tarjeta selecciona Contado.
                                                    </small>
                                                </div>

                                                <!-- BOTONES -->
                                                <div class="form-group col-12">
                                                    <button class="btn btn-primary" type="submit" id="btnGuardar">
                                                        <i class="fa fa-save"></i> Guardar
                                                    </button>
                                                    <button class="btn btn-danger" type="button" onclick="cancelarform()">
                                                        <i class="fa fa-arrow-circle-left"></i> Cancelar
                                                    </button>
                                                </div>

                                            </div>
                                        </form>
                                    </div>
                                    <!-- FORMULARIO FIN -->

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

    <?php
    } else {
        require "access.php";
    }

    require "footer.php";
    ?>
    <script src="Views/modules/scripts/paymentformat.js"></script>
<?php
}
ob_end_flush();
?>