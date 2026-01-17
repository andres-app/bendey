<?php
ob_start();
session_start();

if (!isset($_SESSION['nombre'])) {
    header("location: login");
    exit;
}

require "header.php";
require "sidebar.php";

if ($_SESSION['almacen'] == 1) {
?>

<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-body">

            <div class="row">
                <div class="col-12">

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4>Almacenes</h4>
                            <button class="btn btn-success" onclick="mostrarform(true)" id="btnagregar">
                                <i class="fa fa-plus-circle"></i> Agregar
                            </button>
                        </div>

                        <div class="card-body">

                            <!-- LISTADO -->
                            <div class="table-responsive" id="listadoregistros">
                                <table id="tbllistado"
                                    class="table table-striped table-hover text-nowrap"
                                    style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Ubicación</th>
                                            <th>Descripción</th>
                                            <th>Estado</th>
                                            <th>Opciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <!-- FIN LISTADO -->

                            <!-- FORMULARIO -->
                            <div id="formularioregistros" style="display:none;">
                                <form id="formulario" method="POST">

                                    <input type="hidden" name="idalmacen" id="idalmacen">

                                    <div class="row">

                                        <div class="form-group col-lg-4 col-md-6 col-sm-12">
                                            <label>Nombre</label>
                                            <input type="text"
                                                class="form-control"
                                                name="nombre"
                                                id="nombre"
                                                maxlength="100"
                                                placeholder="Nombre del almacén"
                                                required>
                                        </div>

                                        <div class="form-group col-lg-4 col-md-6 col-sm-12">
                                            <label>Ubicación</label>
                                            <input type="text"
                                                class="form-control"
                                                name="ubicacion"
                                                id="ubicacion"
                                                maxlength="200"
                                                placeholder="Ubicación del almacén">
                                        </div>

                                        <div class="form-group col-lg-4 col-md-12 col-sm-12">
                                            <label>Descripción</label>
                                            <input type="text"
                                                class="form-control"
                                                name="descripcion"
                                                id="descripcion"
                                                maxlength="200"
                                                placeholder="Descripción del almacén">
                                        </div>

                                        <div class="form-group col-12 text-center mt-4">
                                            <button type="submit" class="btn btn-primary" id="btnGuardar">
                                                <i class="fa fa-save"></i> Guardar
                                            </button>

                                            <button type="button"
                                                class="btn btn-danger"
                                                onclick="cancelarform()">
                                                <i class="fa fa-arrow-circle-left"></i> Cancelar
                                            </button>
                                        </div>

                                    </div>
                                </form>
                            </div>
                            <!-- FIN FORMULARIO -->

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

<script src="Views/modules/scripts/almacen.js"></script>

<?php
ob_end_flush();
?>
