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
            <!-- RESUMEN DE VENTAS -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-secondary">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="card-wrap">
                            <div class="card-header">
                                <h4>Ventas brutas</h4>
                            </div>
                            <div class="card-body" id="graficaVentasBrutas">
                                S/ 0.00
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-danger">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="card-wrap">
                            <div class="card-header">
                                <h4>Notas de crédito</h4>
                            </div>
                            <div class="card-body text-danger" id="graficaNotasCredito">
                                - S/ 0.00
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-success">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="card-wrap">
                            <div class="card-header">
                                <h4>Ventas netas</h4>
                            </div>
                            <div class="card-body" id="graficaVentasNetas">
                                S/ 0.00
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">

                <!--GRAFICO DE COMPRAS-->
                <div class="col-12 col-md-6 col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Grafico de compras</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="compras_grafica"></canvas>
                        </div>
                    </div>
                </div>

                <!--GRAFICO DE VENTAS-->
                <div class="col-12 col-md-6 col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Ventas brutas, notas de crédito y ventas netas</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="ventas_grafica"></canvas>
                        </div>
                    </div>
                </div>

                <!--RESUMEN DE COMPRAS DEL AÑO-->
                <div class="col-12 col-md-6 col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Resumen de compras del año <?php echo date("Y"); ?></h4>
                        </div>
                        <div class="card-body">
                            <canvas id="resumen_compras"></canvas>
                        </div>
                    </div>
                </div>
                <!--RESUMEN DE VENTAS DEL AÑO-->
                <div class="col-12 col-md-6 col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Ventas netas por mes <?php echo date("Y"); ?></h4>
                        </div>
                        <div class="card-body">
                            <canvas id="resumen_ventas"></canvas>
                        </div>
                    </div>
                </div>
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
<script src="Assets/bundles/chartjs/chart.min.js"></script>
<?php
$rutaGraphicsJs = __DIR__ . '/scripts/graphics.js';
$versionGraphicsJs = file_exists($rutaGraphicsJs)
    ? filemtime($rutaGraphicsJs)
    : time();
?>
<script src="Views/modules/scripts/graphics.js?v=<?= $versionGraphicsJs ?>"></script>



<?php
 }
  ob_end_flush();
  ?>