<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
    header("location: login");
} else {
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
                        <div class="card-header">
                            <h4>Almacenes 
                                <button class="btn btn-success" onclick="mostrarform(true)" id="btnagregar">
                                    <i class="fa fa-plus-circle"></i> Agregar
                                </button>
                            </h4>
                        </div>

                        <!-- TABLA DE LISTADO DE REGISTROS -->
                        <div class="card-body">
                            <div class="table-responsive" id="listadoregistros">
                                <table id="tbllistado" class="table table-striped table-hover text-nowrap" style="width:100%;">
                                    <thead>
                                        <th>Código</th>
                                        <th>Nombre</th>
                                        <th>Dirección</th>
                                        <th>Estado</th>
                                        <th>Opciones</th>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                            <!-- FIN TABLA -->

                            <!-- FORMULARIO DE REGISTRO -->
                            <div id="formularioregistros">
                                <form name="formulario" id="formulario" method="POST">
                                    <div class="row">
                                        <div class="form-group col-lg-4 col-md-6 col-xs-12">
                                            <label for="codigo">Código</label>
                                            <input class="form-control" type="hidden" name="idalmacen" id="idalmacen">
                                            <input class="form-control" type="text" name="codigo" id="codigo" maxlength="50" placeholder="Código" required>
                                        </div>
                                        <div class="form-group col-lg-4 col-md-6 col-xs-12">
                                            <label for="nombre">Nombre</label>
                                            <input class="form-control" type="text" name="nombre" id="nombre" maxlength="100" placeholder="Nombre" required>
                                        </div>
                                        <div class="form-group col-lg-4 col-md-6 col-xs-12">
                                            <label for="direccion">Dirección</label>
                                            <input class="form-control" type="text" name="direccion" id="direccion" maxlength="200" placeholder="Dirección del almacén">
                                        </div>
                                        <div class="form-group col-lg-12 col-md-12 col-xs-12 text-center mt-3">
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
                            <!-- FIN FORMULARIO -->
                        </div>
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
}
ob_end_flush();
?>
