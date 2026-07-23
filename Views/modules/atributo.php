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

if ((int)($_SESSION['almacen'] ?? 0) === 1) {
?>
    <div class="main-content">
        <section class="section">
            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>
                                    Gestión de Atributos
                                    <button class="btn btn-success" type="button" onclick="mostrarform(true)" id="btnagregar">
                                        <i class="fa fa-plus-circle"></i> Agregar
                                    </button>
                                </h4>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive" id="listadoregistros">
                                    <table id="tbllistado" class="table table-striped table-hover text-nowrap" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nombre</th>
                                                <th>Descripción</th>
                                                <th>Estado</th>
                                                <th>Valores</th>
                                                <th>Opciones</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>

                                <div id="formularioregistros" style="display:none;">
                                    <form name="formulario" id="formulario" method="POST" autocomplete="off">
                                        <div class="row">
                                            <div class="form-group col-lg-6 col-md-6 col-12">
                                                <label for="nombre">Nombre del atributo</label>
                                                <input type="hidden" name="idatributo" id="idatributo">
                                                <input
                                                    class="form-control"
                                                    type="text"
                                                    name="nombre"
                                                    id="nombre"
                                                    maxlength="100"
                                                    placeholder="Ej.: Color, Talla"
                                                    required
                                                >
                                            </div>

                                            <div class="form-group col-lg-6 col-md-6 col-12">
                                                <label for="descripcion">Descripción</label>
                                                <input
                                                    class="form-control"
                                                    type="text"
                                                    name="descripcion"
                                                    id="descripcion"
                                                    maxlength="255"
                                                    placeholder="Descripción opcional del atributo"
                                                >
                                            </div>

                                            <div class="form-group col-lg-12 col-md-12 col-12 text-center mt-3">
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="modalValores" tabindex="-1" role="dialog" aria-labelledby="modalValoresLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalValoresLabel">
                        Valores de Atributo: <span id="titulo-atributo"></span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <form id="formValor" autocomplete="off">
                        <input type="hidden" id="idvalor" name="idvalor">
                        <input type="hidden" id="idatributo_valor" name="idatributo">

                        <div class="form-group">
                            <label for="valor" id="labelValor">Nuevo valor</label>
                            <input type="text" class="form-control" name="valor" id="valor" maxlength="100" required>
                        </div>

                        <div class="text-right">
                            <button type="button" class="btn btn-secondary" id="btnCancelarValor" style="display:none;">
                                <i class="fa fa-times"></i> Cancelar edición
                            </button>
                            <button type="submit" class="btn btn-primary" id="btnGuardarValor">
                                <i class="fa fa-save"></i> Guardar valor
                            </button>
                        </div>
                    </form>

                    <hr>

                    <div class="table-responsive">
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
    </div>
<?php
} else {
    require 'access.php';
}

require 'footer.php';
?>
<script src="Views/modules/scripts/atributo.js"></script>
<?php
ob_end_flush();
?>
