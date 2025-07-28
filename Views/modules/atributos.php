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
                                    <h4>Gestión de Atributos
                                        <button class="btn btn-success" onclick="mostrarform(true)" id="btnagregar">
                                            <i class="fa fa-plus-circle"></i> Agregar
                                        </button>
                                    </h4>
                                </div>

                                <!-- TABLA DE LISTADO DE ATRIBUTOS -->
                                <div class="card-body">
                                    <div class="table-responsive" id="listadoregistros">
                                        <table id="tbllistado" class="table table-striped table-hover text-nowrap"
                                            style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th style="display: none;">ID</th> <!-- Este debe ser el primero -->
                                                    <th>Nombre</th>
                                                    <th>Descripción</th>
                                                    <th>Estado</th>
                                                    <th>Valores</th>
                                                    <th>Opciones</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                    <!-- FIN TABLA -->

                                    <!-- FORMULARIO DE REGISTRO DE ATRIBUTOS -->
                                    <div id="formularioregistros" style="display: none;">
                                        <form name="formulario" id="formulario" method="POST">
                                            <div class="row">
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="nombre">Nombre del atributo</label>
                                                    <input type="hidden" name="idatributo" id="idatributo">
                                                    <input class="form-control" type="text" name="nombre" id="nombre"
                                                        maxlength="100" placeholder="Ej: Color, Talla" required>
                                                </div>
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="descripcion">Descripción</label>
                                                    <input class="form-control" type="text" name="descripcion" id="descripcion"
                                                        maxlength="255" placeholder="Descripción opcional del atributo">
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

        <!-- MODAL PARA VALORES DE ATRIBUTOS -->
        <div class="modal fade" id="modalValores" tabindex="-1" role="dialog" aria-labelledby="modalValoresLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Valores de Atributo: <span id="titulo-atributo"></span></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="formValor">
                            <input type="hidden" id="idvalor" name="idvalor">
                            <input type="hidden" id="idatributo_valor" name="idatributo">
                            <div class="form-group">
                                <label for="valor">Nuevo valor</label>
                                <input type="text" class="form-control" name="valor" id="valor" required>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar Valor</button>
                            </div>
                        </form>

                        <hr>
                        <table class="table table-sm table-striped" id="tblvalores">
                            <thead>
                                <tr>
                                    <th>Valor</th>
                                    <th>Estado</th>
                                    <th>Opciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- FIN MODAL -->

        <?php
    } else {
        require "access.php";
    }
    require "footer.php";
    ?>
    <script src="Views/modules/scripts/atributo.js"></script>
    <?php
}
ob_end_flush();
?>