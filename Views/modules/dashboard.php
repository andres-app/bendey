<?php

ob_start();
session_start();
 if(!isset($_SESSION['nombre'])){
header("location: login");
 }else{
     //echo $_SESSION['nombre'];
    require "header.php";
    require "sidebar.php";

    if($_SESSION['dashboard']==1){
    ?>
<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <!-- add content here -->
            <div class="row">

                <!--COMPRAS-->
                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                    <div class="card card-statistic-1">
                        <div class="card-icon l-bg-green">
                            <i class="fas fa-cart-plus"></i>
                        </div>
                        <div class="card-wrap">
                            <div class="padding-20">
                                <div class="text-right">
                                    <h3 class="font-light mb-0">
                                        <i class="ti-arrow-up text-success"></i><span id="tcomprahoy"></span>
                                    </h3>
                                    <span class="text-muted">Compras</span>
                                </div>
                            </div>
                        </div>
                        <a href="buy">
                            <div class="l-bg-green">
                                Compras
                                <i class="fas fa-arrow-circle-right"></i>
                            </div>
                        </a>
                    </div>
                </div>

                <!--VENTAS-->
                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                    <div class="card card-statistic-1">
                        <div class="card-icon l-bg-cyan">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="card-wrap">
                            <div class="padding-20">
                                <div class="text-right">
                                    <h3 class="font-light mb-0">
                                        <i class="ti-arrow-up text-success"></i><span id="tventahoy"></span>
                                    </h3>
                                    <span class="text-muted">Ventas</span>
                                </div>
                            </div>
                        </div>
                        <a href="listsales">
                            <div class="l-bg-cyan">
                                Ventas
                                <i class="fas fa-arrow-circle-right"></i>
                            </div>
                        </a>
                    </div>
                </div>

                <!--CLIENTES-->
                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                    <div class="card card-statistic-1">
                        <div class="card-icon l-bg-orange">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="card-wrap">
                            <div class="padding-20">
                                <div class="text-right">
                                    <h3 class="font-light mb-0">
                                        <i class="ti-arrow-up text-success"></i><span id="tclientes"></span>
                                    </h3>
                                    <span class="text-muted">Clientes</span>
                                </div>
                            </div>
                        </div>
                        <a href="customer">
                            <div class="l-bg-orange">
                                Clientes
                                <i class="fas fa-arrow-circle-right"></i>
                            </div>
                        </a>
                    </div>
                </div>
                <!--PROVEEDORES-->
                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                    <div class="small-box card card-statistic-1">
                        <div class="card-icon l-bg-red">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-wrap">
                            <div class="padding-20">
                                <div class="text-right">
                                    <h3 class="font-light mb-0">
                                        <i class="ti-arrow-up text-success"></i><span id="tproveedores"></span>
                                    </h3>
                                    <span class="text-muted">Proveedores</span>
                                </div>
                            </div>
                        </div>
                        <a href="supplier">
                            <div class="l-bg-red">
                                Proveedores
                                <i class="fas fa-arrow-circle-right"></i>
                            </div>
                        </a>
                    </div>
                </div>
                <!--CATEGORIAS-->
                <div class="col-xl-6 col-lg-12">
                    <div class="card l-bg-green">
                        <div class="card-statistic-3">
                            <div class="card-icon card-icon-large"><i class="fa fa-file-alt"></i></div>
                            <div class="card-content">
                                <h4 class="card-title"><span id="tcategorias"></span></h4>
                                <p class="mb-0 text-sm">
                                    <span class="text-nowrap">Categorias</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!--ALMACEN-->
                <div class="col-xl-6 col-lg-12">
                    <div class="card l-bg-orange">
                        <div class="card-statistic-3">
                            <div class="card-icon card-icon-large"><i class="fa fa-grip-horizontal"></i></div>
                            <div class="card-content">
                                <h4 class="card-title"><span id="tarticulos"></span></h4>

                                <p class="mb-0 text-sm">
                                    <span class="text-nowrap">Artículos</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!--BARRAS COMPRAS 10 ULTIMOS DIAS-->
                <div class="col-12 col-md-6 col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Compra de los ultimos meses</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="compra10dias"></canvas>
                        </div>
                    </div>
                </div>
                <!--BARRAS VENTAS 12 ULTIMOS MESES-->
                <div class="col-12 col-md-6 col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Venta en los ultimos 12 meses</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="venta12meses"></canvas>
                        </div>
                    </div>
                </div>
      <!--GRAFICA VENTAS-->
    <div class="col-lg-12 col-md-12 col-xs-12">

         <div class="card card-primary">
            <!--<div class="card-header">
              <h4>Categorías mas vendidas</h4>
            </div>-->
               <div class="card-body">
              <!--GRAFICA-->
                  <div id="cat_mas_vendidas" style="min-width: 310px; height: 400px; max-width: 600px; margin: 0 auto"></div>

                </div><!--fin box-body-->
          </div><!--fin box-->
      </div><!--col-sm-->
            </div>
        </div>
    </section>
</div>
<?php
    }else{
        require "access.php";
    } 
require "footer.php";
?>
<!-- JS Libraies -->
<script src="Assets/bundles/highcharts/highcharts.js"></script>

<script src="Assets/bundles/chartjs/chart.min.js"></script>

<script src="Views/modules/scripts/dashboard.js"></script>

<?php
 }
  ob_end_flush();
  ?>