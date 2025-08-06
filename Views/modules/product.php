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
                                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                                    <div class="d-flex align-items-center flex-wrap">
                                        <h4 class="mb-2 mb-md-0 mr-3">
                                            Productos
                                            <button class="btn btn-success btn-sm ml-2" onclick="mostrarform(true)"
                                                id="btnagregar">
                                                <i class="fa fa-plus-circle"></i> Agregar
                                            </button>
                                        </h4>
                                    </div>

                                    <div class="d-flex align-items-center">
                                        <button class="btn btn-outline-secondary btn-sm ml-2" onclick="togglePlantilla()">
                                            <i class="fa fa-chevron-down"></i> Plantilla
                                        </button>
                                    </div>
                                </div>



                                <div class="card-body">
                                    <div class="container-fluid mb-3">
                                        <div id="plantillaSection" style="display: none; transition: all 0.4s ease;">
                                            <div class="row">
                                                <div class="col-12 d-flex justify-content-end">
                                                    <div class="btn-group mr-2">
                                                        <a href="Assets/plantillas/plantilla_productos.csv" download
                                                            class="btn btn-outline-primary btn-sm">
                                                            <i class="fa fa-download"></i> Descargar plantilla Excel
                                                        </a>
                                                    </div>
                                                    <form id="formSubidaMasiva" enctype="multipart/form-data"
                                                        class="form-inline">
                                                        <div class="form-group mb-0 mr-2">
                                                            <input type="file" class="form-control-file" id="archivo_productos"
                                                                name="archivo_productos" accept=".xlsx,.csv" required>
                                                        </div>
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fa fa-upload"></i> Cargar productos
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                                                    <th>Almacén</th>
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

                                                <div id="grupo_sku_principal" class="form-group col-md-4">
                                                    <label for="codigo">SKU</label>
                                                    <input type="text" name="codigo" id="codigo" class="form-control">
                                                </div>

                                                <div id="grupo_stock_principal" class="form-group col-md-4">
                                                    <label for="stock">Cantidad</label>
                                                    <input type="number" class="form-control" name="stock" id="stock" min="0">
                                                </div>

                                                <div id="grupo_precio_compra_principal" class="form-group col-md-4">
                                                    <label for="precio_compra">Precio de compra</label>
                                                    <input type="number" step="0.01" class="form-control" name="precio_compra"
                                                        id="precio_compra" min="0">
                                                    <small class="form-text text-muted">Opcional. Sin precio, no podrás
                                                        vender
                                                        el producto.</small>
                                                </div>

                                                <div id="grupo_precio_venta_principal" class="form-group col-md-4">
                                                    <label for="precio_venta">Precio de venta</label>
                                                    <input type="number" step="0.01" class="form-control" name="precio_venta"
                                                        id="precio_venta" min="0">
                                                    <small class="form-text text-muted">Opcional. Sin precio, no podrás
                                                        vender
                                                        el producto.</small>
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
                                                    <select class="form-control" name="idalmacen" id="idalmacen"
                                                        required></select>
                                                </div>

                                                <div class="form-group col-md-6">
                                                    <label>Unidad de Medida</label>
                                                    <select class="form-control" name="idmedida" id="idmedida"
                                                        required></select>
                                                </div>

                                                <div class="form-group col-lg-6">
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

                                                <div class="form-group col-md-12 mb-3">
                                                    <div id="activarAtributosContainer" class="d-flex align-items-center">
                                                        <label for="activar_atributos"
                                                            class="mb-0 mr-2 font-weight-normal">¿Activar atributos?</label>
                                                        <label class="switch mb-0">
                                                            <input type="checkbox" id="activar_atributos"
                                                                onchange="toggleAtributos()">
                                                            <span class="slider round"></span>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div id="atributos_section" class="col-12" style="display:none;">
                                                    <fieldset class="border p-3 mb-4 rounded">
                                                        <legend class="w-auto px-2">Atributos del Producto</legend>

                                                        <div class="form-group col-lg-12">
                                                            <label for="atributos_seleccionados">Selecciona los atributos
                                                                que
                                                                deseas usar:</label>
                                                            <select id="atributos_seleccionados" class="form-control select2"
                                                                multiple style="width: 100%;">
                                                                <!-- Opciones se cargarán dinámicamente -->
                                                            </select>
                                                        </div>

                                                        <div class="row" id="contenedor_atributos">
                                                            <!-- Aquí se insertarán los selects dinámicamente -->
                                                        </div>

                                                        <div class="text-center mt-3">
                                                            <button type="button" class="btn btn-info btn-sm"
                                                                onclick="generarVariaciones()">
                                                                <i class="fa fa-cogs"></i> Generar combinaciones
                                                            </button>
                                                        </div>

                                                        <div id="variaciones-container" class="mt-4" style="display: none;">
                                                            <h5>Combinaciones generadas:</h5>
                                                            <div class="table-responsive">
                                                                <table id="tblvariaciones"
                                                                    class="table table-bordered table-striped">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Combinación</th>
                                                                            <th>SKU</th>
                                                                            <th>Stock</th>
                                                                            <th>Precio Compra</th>
                                                                            <th>Precio Venta</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="variaciones-lista"></tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </fieldset>
                                                </div>

                                                <div class="form-group col-12 text-center mt-3">
                                                    <button class="btn btn-primary" type="submit" id="btnGuardar">
                                                        <i class="fa fa-save"></i> Guardar
                                                    </button>
                                                    <button class="btn btn-danger" onclick="cancelarform()" type="button">
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