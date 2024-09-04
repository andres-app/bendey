<?php
ob_start();
session_start();

if (!isset($_SESSION['nombre'])) {
    header("location: login");
    exit();
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
                                    <h4>Datos generales</h4>
                                </div>
                                <!-- TABLA DE LISTADO DE REGISTROS -->
                                <div class="card-body">
                                    <div class="table-responsive" id="listadoregistros">
                                        <table id="tbllistado" class="table table-striped table-hover text-nowrap"
                                            style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Opción</th>
                                                    <th>Logo</th>
                                                    <th>Nombre</th>
                                                    <th>Documento</th>
                                                    <th>Dirección</th>
                                                    <th>Teléfono</th>
                                                    <th>E-mail</th>
                                                    <th>País/Ciudad</th>
                                                    <th>Impuesto</th>
                                                    <th>Moneda</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                    <!-- TABLA DE LISTADO DE REGISTROS FIN -->

                                    <!-- FORMULARIO DE REGISTRO -->
                                    <div id="formularioregistros">
                                        <form action="" name="formulario" id="formulario" method="POST">
                                            <div class="row">
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="logo">Logo(*):</label>
                                                    <div class="d-flex align-items-center">
                                                        <!-- Contenedor de Input y Previsualización de Imagen -->
                                                        <div class="mr-3">
                                                            <input class="form-control-file" type="file" name="logo" id="logo"
                                                                style="max-width: 200px;">
                                                            <input type="hidden" name="logoactual" id="logoactual">
                                                        </div>
                                                        <!-- Contenedor de Imagen con Estilos CSS para mejor visualización -->
                                                        <div>
                                                            <img src="" alt="Logo Actual" class="img-thumbnail" width="150"
                                                                height="120" id="logomuestra">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="nombre">Nombre de la empresa(*):</label>
                                                    <input class="form-control" type="hidden" name="id_negocio" id="id_negocio">
                                                    <input class="form-control" type="text" name="nombre" id="nombre"
                                                        maxlength="100" placeholder="Nombre" required>
                                                </div>
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="ndocumento">Tipo de documento:(*):</label>
                                                    <input class="form-control" type="text" name="ndocumento" id="ndocumento"
                                                        value="RUC" placeholder="RUC" required disabled>
                                                </div>
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="documento">Numero de RUC(*):</label>
                                                    <input class="form-control" type="text" name="documento" id="documento"
                                                        maxlength="11" required>
                                                </div>
                                                <div class="form-group col-lg-16 col-md-6 col-xs-12">
                                                    <label for="direccion">Dirección(*):</label>
                                                    <input class="form-control" type="text" name="direccion" id="direccion"
                                                        maxlength="256" placeholder="Dirección" required>
                                                </div>
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="ciudad">Ciudad:</label>
                                                    <input class="form-control" type="text" name="ciudad" id="ciudad">
                                                </div>
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="pais">País:</label>
                                                    <input class="form-control" type="text" name="pais" id="pais">
                                                </div>
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="telefono">Teléfono(*):</label>
                                                    <input class="form-control" type="text" name="telefono" id="telefono"
                                                        required>
                                                </div>
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="email">E-mail:</label>
                                                    <input class="form-control" type="email" name="email" id="email">
                                                </div>
                                                <div class="form-group col-lg-12 col-md-12 col-xs-12">
                                                    <label>APIS REST</label>
                                                </div>
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="tokendniruc">Token RENIEC/SUNAT:</label>
                                                    <div class="input-group">
                                                        <input class="form-control" type="password" name="tokendniruc"
                                                            id="tokendniruc">
                                                        <div class="input-group-append">
                                                            <button class="btn btn-outline-secondary" type="button"
                                                                id="toggleTokenVisibility">
                                                                <i class="fa fa-eye" id="eyeIcon"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>


                                                <div class="form-group col-lg-12 col-md-12 col-xs-12">
                                                    <label>Datos Financieros</label>
                                                </div>
                                                <div class="form-group col-lg-3 col-md-3 col-xs-12">
                                                    <label for="nombre_impuesto">Nombre Imp:</label>
                                                    <input class="form-control" type="text" name="nombre_impuesto"
                                                        id="nombre_impuesto" placeholder="IVA - IGV">
                                                </div>
                                                <div class="form-group col-lg-3 col-md-3 col-xs-12">
                                                    <label for="monto_impuesto">Monto (%):</label>
                                                    <input class="form-control" type="text" name="monto_impuesto"
                                                        id="monto_impuesto">
                                                </div>
                                                <div class="form-group col-lg-3 col-md-3 col-xs-12">
                                                    <label for="moneda">Moneda:</label>
                                                    <input class="form-control" type="text" name="moneda" id="moneda"
                                                        placeholder="SOLES - Dolares">
                                                </div>
                                                <div class="form-group col-lg-3 col-md-3 col-xs-12">
                                                    <label for="simbolo">Símbolo:</label>
                                                    <input class="form-control" type="text" name="simbolo" id="simbolo"
                                                        placeholder="s/ - $">
                                                </div>
                                                <div class="form-group col-lg-12 col-md-12 col-sm-12 col-xs-12">
                                                    <button class="btn btn-primary" type="submit" id="btnGuardar"><i
                                                            class="fa fa-save"></i> Guardar</button>
                                                    <button class="btn btn-danger" onclick="cancelarform()" type="button"><i
                                                            class="fa fa-arrow-circle-left"></i> Cancelar</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    <!-- FORMULARIO DE REGISTRO FIN -->
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
    <script src="Views/modules/scripts/generalsetting.js"></script>
    <?php
}
ob_end_flush();
?>