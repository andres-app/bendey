<?php

ob_start();
session_start();
 if(!isset($_SESSION['nombre'])){
header("location: login");
 }else{
     //echo $_SESSION['nombre'];
    require "header.php";
    require "sidebar.php";

    if($_SESSION['almacen']==1){
    ?>
<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Categorias <button class="btn btn-success" onclick="mostrarform(true)"
                                    id="btnagregar"><i class="fa fa-plus-circle"></i> Agregar</button></h4>
                        </div>
                        <!--TABLA DE LISTADO DE REGISTROS-->
                        <div class="card-body">
                            <div class="table-responsive" id="listadoregistros">
                                <table id="tbllistado" class="table table-striped table-hover text-nowrap"
                                    style="width:100%;">
                                    <thead>
                                        <th>Opciones</th>
                                        <th>Nombre</th>
                                        <th>Descripcion</th>
                                        <th>Estado</th>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                            <!--TABLA DE LISTADO DE REGISTROS FIN-->

                            <!--FORMULARIO PARA DE REGISTRO-->
                            <div id="formularioregistros">
                                <form action="" name="formulario" id="formulario" method="POST">
                                    <div class="row">
                                        <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                            <label for="">Nombre</label>
                                            <input class="form-control" type="hidden" name="idcategoria"
                                                id="idcategoria">
                                            <input class="form-control" type="text" name="nombre" id="nombre"
                                                maxlength="50" placeholder="Nombre" required>
                                        </div>
                                        <div class="form-group col-lg-6 col-md-6 col-xs-12">
                                            <label for="">Descripcion</label>
                                            <input class="form-control" type="text" name="descripcion" id="descripcion"
                                                maxlength="256" placeholder="Descripcion">
                                        </div>
                                        <div class="form-group col-lg-12 col-md-12 col-sm-12 col-xs-12">
                                            <button class="btn btn-primary" type="submit" id="btnGuardar"><i
                                                    class="fa fa-save"></i> Guardar</button>

                                            <button class="btn btn-danger" onclick="cancelarform()" type="button"><i
                                                    class="fa fa-arrow-circle-left"></i>
                                                Cancelar</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <!--FORMULARIO PARA DE REGISTRO FIN-->
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div>
</section>
</div>

<!-- MODAL SUBCATEGORÍAS -->
<div class="modal fade" id="modalSubcategorias" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">
          Subcategorías de <b><span id="categoriaNombre"></span></b>
        </h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>

      <div class="modal-body">

        <!-- ID CATEGORÍA -->
        <input type="hidden" id="sub_idcategoria">

        <!-- FORM NUEVA SUBCATEGORÍA -->
        <div class="input-group mb-3">
          <input type="text" id="sub_nombre" class="form-control"
                 placeholder="Nueva subcategoría">
          <div class="input-group-append">
            <button class="btn btn-primary" onclick="guardarSubcategoria()">
              Agregar
            </button>
          </div>
        </div>

        <!-- TABLA SUBCATEGORÍAS -->
        <div class="table-responsive">
          <table class="table table-bordered table-sm">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Estado</th>
                <th>Opciones</th>
              </tr>
            </thead>
            <tbody id="tablaSubcategorias">
              <!-- AJAX -->
            </tbody>
          </table>
        </div>

      </div>

    </div>
  </div>
</div>
<!-- FIN MODAL SUBCATEGORÍAS -->

<?php
    }else{
        require "access.php";
    }    
require "footer.php";
?>
<script src="Views/modules/scripts/category.js"></script>
<?php
 }
  ob_end_flush();
  ?>