<?php

ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
    header("location: login");
} else {
    //echo $_SESSION['nombre'];
    require "header.php";
    require "sidebar.php";

    if ($_SESSION['almacen'] == 1) {
?>

        <div class="main-content">
            <section class="section">
                <div class="section-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="card flex">
                                <div class="card-header">
                                    <h4>Productos | <button class="btn btn-success" onclick="mostrarform(true)"
                                            id="btnagregar"><i class="fa fa-plus-circle"></i> Agregar</button></h4>
                                </div>
                                <!--TABLA DE LISTADO DE REGISTROS-->
                                <div class="card-body">
                                    <div class="table-responsive" id="listadoregistros">
                                        <table id="tbllistado" class="table table-striped table-hover text-nowrap" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Codigo</th>
                                                    <th>Nombre</th>
                                                    <th>Categoria</th>
                                                    <th>Subcategoría</th>
                                                    <th>Und.Medida</th>
                                                    <th>Stock</th>
                                                    <th>Imagen</th>
                                                    <th>P. Compra</th>
                                                    <th>P. Venta</th>
                                                    <th>Estado</th>
                                                    <th>Opciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                    <!--TABLA DE LISTADO DE REGISTROS FIN-->

                                    <!-- FORMULARIO DE REGISTRO -->
                                    <div id="formularioregistros" style="display: none;">
                                        <form action="" name="formulario" id="formulario" method="POST">
                                            <div class="row">
                                                <!-- Datos principales -->
                                                <input type="hidden" name="idarticulo" id="idarticulo">
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="nombre">Nombre(*):</label>
                                                    <input class="form-control" type="text" name="nombre" id="nombre"
                                                        maxlength="100" placeholder="Nombre" required>
                                                </div>
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="idcategoria">Categoría(*):</label>
                                                    <select name="idcategoria" id="idcategoria" class="form-control" required>
                                                        <option value="">Seleccione categoría</option>
                                                        <!-- se llenará dinámicamente -->
                                                    </select>
                                                </div>

                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="idsubcategoria">Subcategoría</label>
                                                    <select name="idsubcategoria" id="idsubcategoria" class="form-control">
                                                        <option value="">Seleccione subcategoría</option>
                                                        <!-- se llenará dinámicamente -->
                                                    </select>
                                                </div>

                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="idalmacen">Almacén(*):</label>
                                                    <select name="idalmacen" id="idalmacen" class="form-control" required>
                                                        <option value="">Seleccione un almacén</option>
                                                        <option value="1">Almacén Principal</option>
                                                        <option value="2">Almacén Secundario</option>
                                                        <option value="3">Almacén Lima</option>
                                                        <option value="4">Almacén Arequipa</option>
                                                    </select>
                                                </div>
                                                <!-- Código y Unidad de medida -->
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="codigo">Código:</label>
                                                    <input class="form-control" type="text" name="codigo" id="codigo"
                                                        placeholder="Código del producto" required>
                                                </div>
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="idmedida">Unidad de Medida:</label>
                                                    <select name="idmedida" id="idmedida" class="form-control"
                                                        required></select>
                                                </div>

                                                <!-- Imagen -->
                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="imagen">Imagen:</label>
                                                    <div class="custom-file">
                                                        <input type="file" class="custom-file-input" id="imagen" name="imagen">
                                                        <label class="custom-file-label" for="imagen">Selecciona una
                                                            imagen</label>
                                                    </div>
                                                    <input type="hidden" name="imagenactual" id="imagenactual">
                                                    <div class="mt-2">
                                                        <img src="" alt="" id="imagenmuestra"
                                                            style="max-width: 150px; max-height: 120px;">
                                                    </div>
                                                </div>

                                                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                    <label for="activar_atributos">¿Activar atributos?</label><br>
                                                    <label class="switch">
                                                        <input type="checkbox" id="activar_atributos"
                                                            onchange="toggleAtributos()">
                                                        <span class="slider round"></span>
                                                    </label>
                                                </div>


                                                <!-- Sección de atributos (oculta por defecto) -->
                                                <div id="atributos_section" class="col-12" style="display:none;">
                                                    <div class="row">
                                                        <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                            <label for="color">Color:</label>
                                                            <select class="form-control" name="color" id="color">
                                                                <option value="">Seleccionar</option>
                                                                <option value="Rojo">Rojo</option>
                                                                <option value="Negro">Negro</option>
                                                                <option value="Blanco">Blanco</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                                            <label for="talla">Talla:</label>
                                                            <select class="form-control" name="talla" id="talla">
                                                                <option value="">Seleccionar</option>
                                                                <option value="S">S</option>
                                                                <option value="M">M</option>
                                                                <option value="L">L</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Botones de acción -->
                                                <div
                                                    class="form-group col-lg-12 col-md-12 col-sm-12 col-xs-12 text-center mt-3">
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
        </div>
        </section>
        </div>
    <?php
    } else {
        require "access.php";
    }
    require "footer.php";
    ?>
    <script src="Assets/js/JsBarcode.all.min.js"></script>
    <script src="Assets/js/jquery.PrintArea.js"></script>
    <script src="Views/modules/scripts/product.js"></script>
<?php
}
ob_end_flush();
?>