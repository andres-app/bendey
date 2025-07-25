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
                                <div class="card-body">
                                    <div class="table-responsive" id="listadoregistros">
                                        <table id="tbllistado" class="table table-striped table-hover text-nowrap"
                                            style="width:100%;">
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

                                    <div id="formularioregistros" style="display: none;">
                                        <form action="" name="formulario" id="formulario" method="POST">
                                            <div class="row">
                                                <input type="hidden" name="idarticulo" id="idarticulo">

                                                <div class="form-group col-md-4">
                                                    <label for="nombre">Nombre del producto</label>
                                                    <input class="form-control" type="text" name="nombre" id="nombre"
                                                        maxlength="100" placeholder="Nombre" required>
                                                </div>

                                                <div class="form-group col-md-4">
                                                    <label for="codigo">SKU</label>
                                                    <input type="text" name="codigo" id="codigo" class="form-control">
                                                </div>

                                                <div class="form-group col-md-4">
                                                    <label for="stock">Cantidad</label>
                                                    <input type="number" class="form-control" name="stock" id="stock" min="0">
                                                </div>

                                                <div class="form-group col-md-4">
                                                    <label for="precio_compra">Precio de compra</label>
                                                    <input type="number" step="0.01" class="form-control" name="precio_compra" id="precio_compra" min="0">
                                                    <small class="form-text text-muted">Opcional. Sin precio, no podrás vender el producto.</small>
                                                </div>
                                                <div class="form-group col-md-4">
                                                    <label for="precio_venta">Precio de venta</label>
                                                    <input type="number" step="0.01" class="form-control" name="precio_venta" id="precio_venta" min="0">
                                                    <small class="form-text text-muted">Opcional. Sin precio, no podrás vender el producto.</small>
                                                </div>


                                                <div class="form-group col-md-4">
                                                    <label>Categoría</label>
                                                    <select class="form-control" name="idcategoria" id="idcategoria"
                                                        required></select>
                                                </div>
                                                <div class="form-group col-md-4">
                                                    <label>Subcategoría</label>
                                                    <select class="form-control" name="idsubcategoria"
                                                        id="idsubcategoria"></select>
                                                </div>
                                                <div class="form-group col-md-4">
                                                    <label>Almacén</label>
                                                    <select class="form-control" name="idalmacen" id="idalmacen" required>
                                                        <!-- Opciones se llenan por AJAX -->
                                                    </select>
                                                </div>

                                                <div class="form-group col-md-6">
                                                    <label>Unidad de Medida</label>
                                                    <select class="form-control" name="idmedida" id="idmedida"
                                                        required></select>
                                                </div>

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