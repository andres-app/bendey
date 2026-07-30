<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
    header("location: login");
} else {
    //echo $_SESSION['nombre'];
    require "header.php";
    require "sidebar.php";

    if ($_SESSION['ventas'] == 1) {
?>

        <style>
            /* =========================================================
               LISTADO DE VENTAS: ACCIONES SOBRIAS Y ORDENADAS
            ========================================================== */
            #tbllistado thead th {
                padding-top: 13px;
                padding-bottom: 13px;
                border-bottom: 1px solid #e5e7eb;
                color: #4b5563;
                font-size: .76rem;
                font-weight: 700;
                letter-spacing: .035em;
                text-transform: uppercase;
                vertical-align: middle;
                white-space: nowrap;
            }

            #tbllistado tbody td {
                color: #374151;
                vertical-align: middle;
            }

            #tbllistado tbody tr:hover {
                background: #f8fafc;
            }

            .venta-acciones {
                display: inline-block;
            }

            .venta-acciones-boton {
                min-width: 100px;
                padding: 6px 11px;
                border-color: #d1d5db;
                border-radius: 8px;
                color: #374151;
                background: #ffffff;
                box-shadow: none !important;
                font-size: .78rem;
                font-weight: 600;
            }

            .venta-acciones-boton:hover,
            .venta-acciones-boton:focus,
            .venta-acciones.show .venta-acciones-boton {
                border-color: #9ca3af;
                color: #111827;
                background: #f3f4f6;
            }

            .venta-acciones-menu {
                min-width: 230px;
                padding: 7px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                box-shadow: 0 14px 35px rgba(15, 23, 42, .13);
            }

            .venta-acciones-menu .dropdown-header {
                padding: 7px 10px 5px;
                color: #9ca3af;
                font-size: .67rem;
                font-weight: 700;
                letter-spacing: .06em;
                text-transform: uppercase;
            }

            .venta-acciones-menu .dropdown-item {
                display: flex;
                align-items: center;
                gap: 10px;
                min-height: 38px;
                padding: 8px 10px;
                border: 0;
                border-radius: 7px;
                color: #374151;
                background: transparent;
                font-size: .84rem;
                text-align: left;
            }

            .venta-acciones-menu .dropdown-item:hover,
            .venta-acciones-menu .dropdown-item:focus {
                color: #111827;
                background: #f3f4f6;
            }

            .venta-acciones-menu .dropdown-item i {
                width: 17px;
                color: #6b7280;
                text-align: center;
            }

            .venta-acciones-menu .dropdown-divider {
                margin: 6px 3px;
                border-top-color: #edf0f3;
            }

            .venta-acciones-menu .venta-accion-peligro,
            .venta-acciones-menu .venta-accion-peligro i {
                color: #b42318;
            }

            .venta-acciones-menu .venta-accion-peligro:hover,
            .venta-acciones-menu .venta-accion-peligro:focus {
                color: #912018;
                background: #fff1f0;
            }

            #tbllistado_wrapper .dt-buttons .btn {
                margin-right: 6px;
                border-color: #d1d5db;
                border-radius: 8px;
                color: #374151;
                background: #ffffff;
                box-shadow: none;
                font-size: .8rem;
                font-weight: 600;
            }

            #tbllistado_wrapper .dt-buttons .btn:hover,
            #tbllistado_wrapper .dt-buttons .btn:focus {
                border-color: #9ca3af;
                color: #111827;
                background: #f3f4f6;
            }

            @media (max-width: 767.98px) {
                .venta-acciones-boton {
                    min-width: 42px;
                }

                .venta-acciones-boton .texto-accion {
                    display: none;
                }
            }
        </style>

        <!-- Main Content -->
        <div class="main-content">
            <section class="section">
                <div class="section-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="box-title">Ventas <button class="btn btn-success" id="btnagregar"><i
                                                class="fa fa-plus-circle"></i> Agregar</button></h4>

                                </div>
                                <!--TABLA DE LISTADO DE REGISTROS-->
                                <div class="card-body">
                                    <div class="table-responsive" id="listadoregistros">
                                        <table id="tbllistado" class="table table-striped table-hover text-nowrap"
                                            style="width:100%;">
                                            <thead>
                                                <th>Fecha</th>
                                                <th>Cliente</th>
                                                <th>Usuario</th>
                                                <th>Documento</th>
                                                <th>Número</th>
                                                <th class="text-right">Total venta</th>
                                                <th class="text-center">Estado SUNAT</th>
                                                <th class="text-right">Acciones</th>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                    <!--TABLA DE LISTADO DE REGISTROS FIN-->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>



        <!--MODALES-->
        <!--MODAL PARA VER EL INGRESO-->

        <div class="modal fade" id="getCodeModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="formModal">Vista de venta</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="form-group col-lg-8 col-md-8 col-xs-12">
                                <label for="">Cliente(*):</label>
                                <input class="form-control" type="hidden" name="idventam" id="idventam">
                                <input class="form-control"type="text"name="cliente"id="cliente"maxlength="180"readonly>
                            </div>
                            <div class="form-group col-lg-4 col-md-4 col-xs-6">
                                <label>Fecha: </label>
                                <div class="input-group">
                                    <input class="form-control pull-right" type="text" name="fecha_horam" id="fecha_horam"
                                        readonly>
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <i class="fas fa-calendar"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-lg-4 col-md-4 col-xs-6">
                                <label for="">Comprobante(*):</label>
                                <input class="form-control" type="text" name="tipo_comprobantem" id="tipo_comprobantem"
                                    maxlength="7" readonly>
                            </div>
                            <div class="form-group col-lg-2 col-md-2 col-xs-6">
                                <label for="">Serie: </label>
                                <input class="form-control" type="text" name="serie_comprobantem" id="serie_comprobantem"
                                    maxlength="7" readonly>
                            </div>
                            <div class="form-group col-lg-2 col-md-2 col-xs-6">
                                <label for="">Número: </label>
                                <input class="form-control" type="text" name="num_comprobantem" id="num_comprobantem"
                                    maxlength="10" readonly>
                            </div>
                            <div class="form-group col-lg-4 col-md-4 col-xs-6">
                                <label>Impuesto: </label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="impuestom" id="impuestom" readonly>
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <i class="fas fa-percent"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group col-lg-12 col-md-12 col-xs-12">
                                <!-- FORMA Y CONDICIÓN DE PAGO -->
                                <div class="row mt-2">
                                    <div class="form-group col-lg-4 col-md-4 col-xs-12">
                                        <label>Forma de pago:</label>
                                        <input type="text" class="form-control" id="tipo_pagom" readonly>
                                    </div>

                                    <div class="form-group col-lg-4 col-md-4 col-xs-12">
                                        <label>Condición:</label>
                                        <input type="text" class="form-control" id="condicion_pagom" readonly>
                                    </div>
                                </div>

                                <!-- DETALLE PAGO MIXTO -->
                                <div class="row" id="bloquePagoMixto" style="display:none;">
                                    <div class="col-lg-12">
                                        <label>Detalle del pago:</label>
                                        <table class="table table-sm table-bordered">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Forma de pago</th>
                                                    <th class="text-right">Monto</th>
                                                </tr>
                                            </thead>
                                            <tbody id="detallePagom"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- ==========================================
     CRONOGRAMA DE CUOTAS
=========================================== -->
                                <div
                                    class="row"
                                    id="bloqueCuotas"
                                    style="display:none;">

                                    <div class="col-lg-12">

                                        <div
                                            class="d-flex justify-content-between align-items-center mb-2">

                                            <label class="mb-0">
                                                Cronograma de cuotas:
                                            </label>

                                            <span
                                                id="resumenCuotasm"
                                                class="badge badge-info">
                                            </span>

                                        </div>

                                        <div class="table-responsive">

                                            <table
                                                class="table table-sm table-bordered table-hover">

                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Cuota</th>
                                                        <th class="text-right">Monto</th>
                                                        <th>Vencimiento</th>
                                                        <th class="text-right">Pagado</th>
                                                        <th class="text-right">Saldo</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>

                                                <tbody id="detalleCuotasm">
                                                </tbody>

                                                <tfoot>
                                                    <tr>
                                                        <th colspan="4" class="text-right">
                                                            Total pendiente:
                                                        </th>

                                                        <th
                                                            id="totalPendienteCuotasm"
                                                            class="text-right">
                                                            S/ 0.00
                                                        </th>

                                                        <th></th>
                                                    </tr>
                                                </tfoot>

                                            </table>

                                        </div>

                                    </div>

                                </div>

                                <table id="detallesm" class="table table-striped table-bordered table-condensed table-hover">
                                    <tbody>

                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="form-group col-lg-12 col-md-12 col-xs-12">

                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" type="button" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
        <!--FIN MODAL PARA VER EL INGRESO-->
        <!--FIN MODALES-->
    <?php
    } else {
        require "access.php";
    }
    require "footer.php";
    ?>
    <?php
    $rutaListsalesJs = __DIR__ . '/scripts/listsales.js';
    $versionListsalesJs = file_exists($rutaListsalesJs)
        ? filemtime($rutaListsalesJs)
        : time();
    ?>

    <script src="Views/modules/scripts/listsales.js?v=<?= $versionListsalesJs ?>"></script>

    <?php
    $rutaDuplicarVentaListadoJs = __DIR__ . '/scripts/listsales_duplicar.js';
    $versionDuplicarVentaListadoJs = file_exists($rutaDuplicarVentaListadoJs)
        ? filemtime($rutaDuplicarVentaListadoJs)
        : time();
    ?>

    <script
        src="Views/modules/scripts/listsales_duplicar.js?v=<?= (int)$versionDuplicarVentaListadoJs ?>">
    </script>
<?php
}
ob_end_flush();
?>