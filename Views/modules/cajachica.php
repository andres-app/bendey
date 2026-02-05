<?php
ob_start();
session_start();

if (!isset($_SESSION['nombre'])) {
    header("location: login");
    exit();
}

require "header.php";
require "sidebar.php";

/*
|--------------------------------------------------------------------------
| CAJA CHICA
| Usa permiso EXISTENTE (ventas)
|--------------------------------------------------------------------------
*/
if (!empty($_SESSION['ventas']) && $_SESSION['ventas'] == 1) {
?>

    <!-- ================= MAIN CONTENT ================= -->
    <div class="main-content">
        <section class="section">
            <div class="section-body">
                <div class="row">
                    <div class="col-12">

                        <div class="card">

                            <!-- CARD HEADER -->
                            <div class="card-header">
                                <h4>
                                    <i class="fa fa-calculator text-primary"></i>
                                    Liquidación de Caja
                                </h4>
                            </div>

                            <!-- CARD BODY -->
                            <div class="card-body">

                                <!-- ================= FILTROS ================= -->
                                <div class="row mb-4">

                                    <div class="col-md-3">
                                        <label class="text-muted mb-1">Fecha Inicio</label>
                                        <input type="date"
                                            id="fecha_inicio"
                                            class="form-control form-control-sm"
                                            value="<?= date('Y-m-d') ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="text-muted mb-1">Fecha Fin</label>
                                        <input type="date"
                                            id="fecha_fin"
                                            class="form-control form-control-sm"
                                            value="<?= date('Y-m-d') ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="text-muted mb-1">Sucursal</label>
                                        <select class="form-control form-control-sm" disabled>
                                            <option>TODOS</option>
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="text-muted mb-1">Vendedor</label>
                                        <select id="idusuario" class="form-control form-control-sm">
                                            <option value="">TODOS</option>
                                        </select>
                                    </div>

                                </div>

                                <!-- ================= BOTONES ================= -->
                                <div class="d-flex justify-content-end mb-3">
                                    <button class="btn btn-outline-success btn-sm mr-2" disabled>
                                        <i class="fa fa-file-excel"></i> Excel
                                    </button>
                                    <a
                                        href="Reports/caja_chica.php?fecha_inicio=<?= date('Y-m-d') ?>&fecha_fin=<?= date('Y-m-d') ?>"
                                        target="_blank"
                                        class="btn btn-danger btn-sm">
                                        <i class="fa fa-file-pdf"></i> PDF
                                    </a>

                                </div>

                                <!-- ================= TABLA CENTRAL ================= -->
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" id="tablaCaja">
                                        <thead class="bg-primary">
                                            <tr>
                                                <th class="bg-primary text-white">Comprobante</th>
                                                <th class="text-center text-white ">Efectivo</th>
                                                <th class="text-center text-white">Tarjeta</th>
                                                <th class="text-center text-white">Transferencia</th>
                                                <th class="text-center text-white">Yape / Plin</th>
                                                <th class="text-center text-white">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- JS INSERTA FILAS -->
                                        </tbody>
                                    </table>
                                </div>

                                <!-- ================= TOTALES INFERIORES ================= -->
                                <div class="row mt-4">

                                    <div class="col-md-4">
                                        <div class="card card-statistic-1">
                                            <div class="card-icon bg-success">
                                                <i class="fas fa-arrow-down"></i>
                                            </div>
                                            <div class="card-wrap">
                                                <div class="card-header">
                                                    <h4>Ingresos</h4>
                                                </div>
                                                <div id="totalIngresos" class="card-body">
                                                    S/ 0.00
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="card card-statistic-1">
                                            <div class="card-icon bg-danger">
                                                <i class="fas fa-arrow-up"></i>
                                            </div>
                                            <div class="card-wrap">
                                                <div class="card-header">
                                                    <h4>Egresos</h4>
                                                </div>
                                                <div class="card-body">
                                                    S/ 0.00
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="card card-statistic-1">
                                            <div class="card-icon bg-primary">
                                                <i class="fas fa-wallet"></i>
                                            </div>
                                            <div class="card-wrap">
                                                <div class="card-header">
                                                    <h4>Total en Caja</h4>
                                                </div>
                                                <div id="totalCaja" class="card-body">
                                                    S/ 0.00
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                            </div><!-- card-body -->
                        </div><!-- card -->

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

<!-- ================= JS ================= -->
<script src="Views/modules/scripts/cajachica.js"></script>

<?php
ob_end_flush();
?>