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
| Usa permiso EXISTENTE (ventas) para evitar "Sin acceso"
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
                                        <input type="datetime-local" class="form-control form-control-sm">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="text-muted mb-1">Fecha Fin</label>
                                        <input type="datetime-local" class="form-control form-control-sm">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="text-muted mb-1">Sucursal</label>
                                        <select class="form-control form-control-sm">
                                            <option>TODOS</option>
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="text-muted mb-1">Vendedor</label>
                                        <select class="form-control form-control-sm">
                                            <option>TODOS</option>
                                        </select>
                                    </div>
                                </div>


                                <!-- ================= BOTONES ================= -->
                                <div class="d-flex justify-content-end mb-3">
                                    <button class="btn btn-outline-success btn-sm mr-2">
                                        <i class="fa fa-file-excel"></i> Excel
                                    </button>
                                    <button class="btn btn-danger btn-sm">
                                        <i class="fa fa-file-pdf"></i> PDF
                                    </button>
                                </div>


                                <!-- ================= TABLA CENTRAL ================= -->
                                <table class="table table-bordered table-hover">
                                    <thead class="bg-primary text-white">
                                        <tr>
                                            <th class="text-white">Comprobante</th>
                                            <th class="text-white">Efectivo</th>
                                            <th class="text-white">Tarjeta</th>
                                            <th class="text-white">Transferencia</th>
                                            <th class="text-white">Yape/Plin</th>
                                            <th class="text-white">CxC</th>
                                            <th class="text-white">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <tr>
                                            <td><i class="fa fa-file-text"></i> Facturas</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                        </tr>

                                        <tr>
                                            <td>
                                                <i class="fa fa-money text-success"></i>
                                                Boletas
                                                <span class="badge badge-success ml-1">1</span>
                                            </td>
                                            <td class="text-right">S/ 50.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right font-weight-bold">S/ 50.00</td>
                                        </tr>

                                        <tr class="bg-light font-weight-bold">
                                            <td>SUB TOTALES</td>
                                            <td class="text-right">S/ 50.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right">S/ 0.00</td>
                                            <td class="text-right">S/ 50.00</td>
                                        </tr>

                                    </tbody>
                                </table>


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
                                                <div class="card-body">
                                                    S/ 50.00
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
                                                <div class="card-body">
                                                    S/ 50.00
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
ob_end_flush();
