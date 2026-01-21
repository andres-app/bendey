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

                                            <input type="hidden" name="idforma_pago" id="idforma_pago">

                                            <div class="row">

                                                <!-- Nombre -->
                                                <div class="form-group col-lg-6 col-md-6 col-sm-12">
                                                    <label>Nombre</label>
                                                    <input type="text" class="form-control" name="nombre" id="nombre" required>
                                                </div>

                                                <!-- Es efectivo -->
                                                <div class="form-group col-lg-3 col-md-3 col-sm-12">
                                                    <label>¿Es efectivo?</label>
                                                    <select class="form-control" name="es_efectivo" id="es_efectivo">
                                                        <option value="1">Sí</option>
                                                        <option value="0" selected>No</option>
                                                    </select>
                                                </div>

                                                <!-- Condición -->
                                                <div class="form-group col-lg-3 col-md-3 col-sm-12">
                                                    <label>Condición</label>
                                                    <select class="form-control" name="condicion" id="condicion">
                                                        <option value="1">Contado</option>
                                                        <option value="2">Crédito</option>
                                                    </select>
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
