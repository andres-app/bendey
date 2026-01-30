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
                            <h4>Datos generales de la empresa</h4>
                        </div>

                        <div class="card-body">

                            <!-- LISTADO (OCULTO PERO EXISTE PARA REUTILIZAR AJAX) -->
                            <div class="table-responsive d-none" id="listadoregistros">
                                <table id="tbllistado"></table>
                            </div>

                            <!-- FORMULARIO -->
                            <div id="formularioregistros">
                                <form name="formulario" id="formulario" method="POST">
                                    <div class="row">

                                        <input type="hidden" name="id_negocio" id="id_negocio">

                                        <div class="form-group col-lg-6">
                                            <label>Nombre de la empresa (*)</label>
                                            <input type="text" class="form-control" name="nombre" id="nombre" required>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label>Tipo documento</label>
                                            <input type="text" class="form-control" value="RUC" disabled>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label>Número de RUC (*)</label>
                                            <input type="text" class="form-control" name="documento" id="documento" maxlength="11" required>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label>Dirección (*)</label>
                                            <input type="text" class="form-control" name="direccion" id="direccion" required>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label>Ciudad</label>
                                            <input type="text" class="form-control" name="ciudad" id="ciudad">
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label>País</label>
                                            <input type="text" class="form-control" name="pais" id="pais">
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label>Teléfono (*)</label>
                                            <input type="text" class="form-control" name="telefono" id="telefono" required>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label>Email</label>
                                            <input type="email" class="form-control" name="email" id="email">
                                        </div>

                                        <div class="form-group col-lg-12">
                                            <label>Token RENIEC / SUNAT</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="tokendniruc" id="tokendniruc">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-secondary" id="toggleTokenVisibility">
                                                        <i class="fa fa-eye" id="eyeIcon"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group col-lg-3">
                                            <label>Nombre Impuesto</label>
                                            <input type="text" class="form-control" name="nombre_impuesto" id="nombre_impuesto">
                                        </div>

                                        <div class="form-group col-lg-3">
                                            <label>Monto (%)</label>
                                            <input type="text" class="form-control" name="monto_impuesto" id="monto_impuesto">
                                        </div>

                                        <div class="form-group col-lg-3">
                                            <label>Moneda</label>
                                            <input type="text" class="form-control" name="moneda" id="moneda">
                                        </div>

                                        <div class="form-group col-lg-3">
                                            <label>Símbolo</label>
                                            <input type="text" class="form-control" name="simbolo" id="simbolo">
                                        </div>

                                        <div class="form-group col-lg-12 text-right">
                                            <button type="submit" class="btn btn-primary" id="btnGuardar">
                                                <i class="fa fa-save"></i> Guardar configuración
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
<script src="Views/modules/scripts/generalsetting.js"></script>
<?php
}
ob_end_flush();
?>
