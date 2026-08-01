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

            /* =========================================================
               MODAL: VISTA DE VENTA PROFESIONAL
            ========================================================== */
            #getCodeModal .venta-modal-dialog {
                width: calc(100% - 30px);
                max-width: 1080px;
                margin: 24px auto;
            }

            #getCodeModal .venta-modal-content {
                overflow: hidden;
                border: 0;
                border-radius: 16px;
                background: #ffffff;
                box-shadow: 0 24px 70px rgba(15, 23, 42, .24);
            }

            #getCodeModal .venta-modal-header {
                min-height: 78px;
                padding: 18px 24px;
                border-bottom: 1px solid #e7e9ee;
                background: #ffffff;
            }

            #getCodeModal .venta-modal-heading {
                display: flex;
                align-items: center;
                gap: 13px;
                min-width: 0;
            }

            #getCodeModal .venta-modal-icon {
                width: 42px;
                height: 42px;
                flex: 0 0 42px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid #dfe3e8;
                border-radius: 11px;
                color: #4b5563;
                background: #f7f8fa;
                font-size: 1rem;
            }

            #getCodeModal .venta-modal-title {
                margin: 0;
                color: #1f2937;
                font-size: 1.08rem;
                font-weight: 700;
                line-height: 1.25;
            }

            #getCodeModal .venta-modal-subtitle {
                overflow: hidden;
                margin-top: 3px;
                color: #7b8491;
                font-size: .78rem;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            #getCodeModal .venta-modal-close {
                width: 38px;
                height: 38px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin: 0;
                padding: 0;
                border: 0;
                border-radius: 10px;
                color: #6b7280;
                background: #f3f4f6;
                font-size: 1.35rem;
                font-weight: 400;
                opacity: 1;
            }

            #getCodeModal .venta-modal-close:hover,
            #getCodeModal .venta-modal-close:focus {
                color: #1f2937;
                background: #e9ecef;
                outline: none;
            }

            #getCodeModal .venta-modal-body {
                max-height: calc(100vh - 182px);
                overflow-y: auto;
                overscroll-behavior: contain;
                padding: 22px 24px 8px;
                background: #f6f7f9;
            }

            #getCodeModal .venta-seccion {
                margin-bottom: 16px;
                padding: 18px;
                border: 1px solid #e3e6eb;
                border-radius: 13px;
                background: #ffffff;
            }

            #getCodeModal .venta-seccion-cabecera {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 15px;
                padding-bottom: 11px;
                border-bottom: 1px solid #edf0f3;
            }

            #getCodeModal .venta-seccion-titulo {
                display: flex;
                align-items: center;
                gap: 9px;
                margin: 0;
                color: #27313f;
                font-size: .84rem;
                font-weight: 750;
                letter-spacing: .025em;
                text-transform: uppercase;
            }

            #getCodeModal .venta-seccion-titulo i {
                width: 25px;
                height: 25px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 7px;
                color: #5c6674;
                background: #f0f2f5;
                font-size: .75rem;
            }

            #getCodeModal .venta-campo {
                margin-bottom: 15px;
            }

            #getCodeModal .venta-campo label {
                display: block;
                margin-bottom: 6px;
                color: #707987;
                font-size: .68rem;
                font-weight: 700;
                letter-spacing: .045em;
                text-transform: uppercase;
            }

            #getCodeModal .venta-campo .form-control,
            #getCodeModal .venta-campo .input-group-text {
                min-height: 42px;
                border-color: #dfe3e8;
                color: #374151;
                background: #f7f8fa;
                box-shadow: none;
                font-size: .84rem;
            }

            #getCodeModal .venta-campo .form-control[readonly] {
                cursor: default;
            }

            #getCodeModal .venta-campo .input-group-text {
                min-width: 42px;
                justify-content: center;
                color: #6b7280;
            }

            #getCodeModal .venta-total-resumen {
                min-height: 70px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 12px 16px;
                border: 1px solid #d9dee5;
                border-radius: 11px;
                background: #f8f9fb;
            }

            #getCodeModal .venta-total-resumen small {
                color: #7b8491;
                font-size: .66rem;
                font-weight: 700;
                letter-spacing: .05em;
                text-transform: uppercase;
            }

            #getCodeModal .venta-total-resumen strong {
                margin-top: 4px;
                color: #1f2937;
                font-size: 1.45rem;
                font-weight: 750;
                line-height: 1;
            }

            #getCodeModal .venta-descuento-resumen strong {
                color: #8a5b16;
            }

            #getCodeModal .venta-subseccion {
                margin-top: 6px;
                padding-top: 15px;
                border-top: 1px solid #edf0f3;
            }

            #getCodeModal .venta-subseccion-label {
                display: block;
                margin-bottom: 9px;
                color: #525d6b;
                font-size: .75rem;
                font-weight: 700;
            }

            #getCodeModal .venta-tabla-secundaria,
            #getCodeModal .venta-detalle-tabla {
                width: 100%;
                margin-bottom: 0;
                border: 1px solid #e2e6ea;
                border-radius: 10px;
                background: #ffffff;
                border-collapse: separate;
                border-spacing: 0;
                overflow: hidden;
            }

            #getCodeModal .venta-tabla-secundaria thead th,
            #getCodeModal .venta-detalle-tabla thead th {
                padding: 10px 12px;
                border-top: 0;
                border-bottom: 1px solid #dfe3e8;
                color: #626c79;
                background: #f1f3f5;
                font-size: .68rem;
                font-weight: 750;
                letter-spacing: .035em;
                text-transform: uppercase;
                vertical-align: middle;
                white-space: nowrap;
            }

            #getCodeModal .venta-tabla-secundaria tbody td,
            #getCodeModal .venta-tabla-secundaria tbody th,
            #getCodeModal .venta-detalle-tabla tbody td {
                padding: 11px 12px;
                border-top: 1px solid #edf0f3;
                color: #3e4652;
                background: #ffffff;
                font-size: .8rem;
                vertical-align: middle;
            }

            #getCodeModal .venta-tabla-secundaria tbody tr:first-child td,
            #getCodeModal .venta-tabla-secundaria tbody tr:first-child th,
            #getCodeModal .venta-detalle-tabla tbody tr:first-child td {
                border-top: 0;
            }

            #getCodeModal .venta-producto-nombre {
                color: #2d3745;
                font-weight: 600;
                line-height: 1.35;
            }

            #getCodeModal .venta-cantidad {
                color: #596372;
                font-weight: 600;
            }

            #getCodeModal .venta-importe {
                color: #27313f;
                font-weight: 700;
            }

            #getCodeModal .venta-detalle-tabla tfoot th {
                padding: 9px 12px;
                border-top: 1px solid #e4e7eb;
                color: #4b5563;
                background: #fafbfc;
                font-size: .78rem;
                vertical-align: middle;
            }

            #getCodeModal .venta-resumen-descuento th {
                color: #8a5b16;
            }

            #getCodeModal .venta-resumen-total th {
                padding-top: 12px;
                padding-bottom: 12px;
                color: #1f2937;
                background: #f1f3f5;
                font-size: .9rem;
                font-weight: 750;
            }

            #getCodeModal .venta-resumen-total small {
                display: block;
                margin-top: 3px;
                color: #7b8491;
                font-size: .66rem;
                font-weight: 500;
            }

            #getCodeModal .venta-detalle-vacio {
                padding: 28px 15px !important;
                color: #7b8491 !important;
                text-align: center;
            }

            #getCodeModal #resumenCuotasm {
                padding: 6px 9px;
                border: 1px solid #dfe3e8;
                border-radius: 999px;
                color: #596372;
                background: #f4f5f7;
                font-size: .68rem;
                font-weight: 650;
            }

            #getCodeModal .venta-modal-footer {
                padding: 13px 24px;
                border-top: 1px solid #e7e9ee;
                background: #ffffff;
            }

            #getCodeModal .venta-modal-footer .btn {
                min-width: 105px;
                border-color: #cfd4da;
                border-radius: 9px;
                color: #374151;
                background: #ffffff;
                box-shadow: none;
                font-size: .82rem;
                font-weight: 650;
            }

            #getCodeModal .venta-modal-footer .btn:hover,
            #getCodeModal .venta-modal-footer .btn:focus {
                border-color: #aeb5bd;
                color: #1f2937;
                background: #f3f4f6;
            }

            #getCodeModal .venta-estado-cuota {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 78px;
                padding: 5px 9px;
                border: 1px solid #dfe3e8;
                border-radius: 999px;
                color: #596372;
                background: #f5f6f8;
                font-size: .67rem;
                font-weight: 700;
                white-space: nowrap;
            }

            #getCodeModal .cuota-pagada {
                border-color: #cfe3d5;
                color: #2f6b40;
                background: #f2f8f4;
            }

            #getCodeModal .cuota-parcial {
                border-color: #eadcbf;
                color: #7c5a19;
                background: #fbf8f1;
            }

            #getCodeModal .cuota-vencida {
                border-color: #ecd0ce;
                color: #9b3028;
                background: #fff6f5;
            }

            #getCodeModal .cuota-anulada {
                color: #6b7280;
                background: #f1f2f4;
            }

            @media (max-width: 767.98px) {
                #getCodeModal .venta-modal-dialog {
                    width: 100%;
                    max-width: none;
                    height: 100%;
                    margin: 0;
                }

                #getCodeModal .venta-modal-content {
                    min-height: 100%;
                    border-radius: 0;
                }

                #getCodeModal .venta-modal-header {
                    padding: 14px 16px;
                }

                #getCodeModal .venta-modal-body {
                    max-height: calc(100vh - 145px);
                    padding: 14px 12px 4px;
                }

                #getCodeModal .venta-seccion {
                    padding: 14px;
                }

                #getCodeModal .venta-modal-footer {
                    padding: 11px 14px;
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



        <!--MODAL: VISTA DE VENTA-->
        <div
            class="modal fade"
            id="getCodeModal"
            tabindex="-1"
            role="dialog"
            aria-labelledby="formModal"
            aria-hidden="true">

            <div
                class="modal-dialog modal-dialog-centered venta-modal-dialog"
                role="document">

                <div class="modal-content venta-modal-content">

                    <div class="modal-header venta-modal-header">
                        <div class="venta-modal-heading">
                            <span class="venta-modal-icon" aria-hidden="true">
                                <i class="fas fa-receipt"></i>
                            </span>

                            <div style="min-width:0;">
                                <h5
                                    class="modal-title venta-modal-title"
                                    id="formModal">
                                    Vista de venta
                                </h5>

                                <div
                                    class="venta-modal-subtitle"
                                    id="modalComprobanteResumen">
                                    Información completa del comprobante
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="close venta-modal-close"
                            data-dismiss="modal"
                            aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body venta-modal-body">
                        <input
                            type="hidden"
                            name="idventam"
                            id="idventam">

                        <section class="venta-seccion">
                            <div class="venta-seccion-cabecera">
                                <h6 class="venta-seccion-titulo">
                                    <i class="fas fa-info"></i>
                                    Información general
                                </h6>
                            </div>

                            <div class="row">
                                <div class="col-lg-8 col-md-7 col-12 venta-campo">
                                    <label for="cliente">Cliente</label>
                                    <input
                                        class="form-control"
                                        type="text"
                                        name="cliente"
                                        id="cliente"
                                        maxlength="180"
                                        readonly>
                                </div>

                                <div class="col-lg-4 col-md-5 col-12 venta-campo">
                                    <label for="fecha_horam">Fecha de emisión</label>
                                    <div class="input-group">
                                        <input
                                            class="form-control"
                                            type="text"
                                            name="fecha_horam"
                                            id="fecha_horam"
                                            readonly>

                                        <div class="input-group-append">
                                            <span class="input-group-text">
                                                <i class="far fa-calendar-alt"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-6 col-12 venta-campo">
                                    <label for="tipo_comprobantem">Tipo de comprobante</label>
                                    <input
                                        class="form-control"
                                        type="text"
                                        name="tipo_comprobantem"
                                        id="tipo_comprobantem"
                                        readonly>
                                </div>

                                <div class="col-lg-2 col-md-3 col-6 venta-campo">
                                    <label for="serie_comprobantem">Serie</label>
                                    <input
                                        class="form-control"
                                        type="text"
                                        name="serie_comprobantem"
                                        id="serie_comprobantem"
                                        readonly>
                                </div>

                                <div class="col-lg-2 col-md-3 col-6 venta-campo">
                                    <label for="num_comprobantem">Número</label>
                                    <input
                                        class="form-control"
                                        type="text"
                                        name="num_comprobantem"
                                        id="num_comprobantem"
                                        readonly>
                                </div>

                                <div class="col-lg-4 col-md-6 col-12 venta-campo">
                                    <label for="impuestom">Impuesto aplicado</label>
                                    <div class="input-group">
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="impuestom"
                                            id="impuestom"
                                            readonly>

                                        <div class="input-group-append">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="venta-seccion">
                            <div class="venta-seccion-cabecera">
                                <h6 class="venta-seccion-titulo">
                                    <i class="fas fa-wallet"></i>
                                    Pago y resumen
                                </h6>
                            </div>

                            <div class="row align-items-stretch">
                                <div class="col-lg-4 col-md-6 col-12 venta-campo">
                                    <label for="tipo_pagom">Forma de pago</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="tipo_pagom"
                                        readonly>
                                </div>

                                <div class="col-lg-3 col-md-6 col-12 venta-campo">
                                    <label for="condicion_pagom">Condición</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="condicion_pagom"
                                        readonly>
                                </div>

                                <div
                                    class="col-lg-2 col-md-6 col-12 mb-3"
                                    id="descuentoResumenWrap"
                                    style="display:none;">
                                    <div class="venta-total-resumen venta-descuento-resumen h-100">
                                        <small>Descuento</small>
                                        <strong id="descuento_ventam">S/ 0.00</strong>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6 col-12 mb-3 ml-lg-auto">
                                    <div class="venta-total-resumen h-100">
                                        <small>Total de la venta</small>
                                        <strong id="total_ventam">S/ 0.00</strong>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="venta-subseccion"
                                id="bloquePagoMixto"
                                style="display:none;">

                                <span class="venta-subseccion-label">
                                    Detalle del pago
                                </span>

                                <div class="table-responsive">
                                    <table class="table table-sm venta-tabla-secundaria">
                                        <thead>
                                            <tr>
                                                <th>Forma de pago</th>
                                                <th class="text-right">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody id="detallePagom"></tbody>
                                    </table>
                                </div>
                            </div>

                            <div
                                class="venta-subseccion"
                                id="bloqueCuotas"
                                style="display:none;">

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="venta-subseccion-label mb-0">
                                        Cronograma de cuotas
                                    </span>

                                    <span id="resumenCuotasm"></span>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm venta-tabla-secundaria">
                                        <thead>
                                            <tr>
                                                <th>Cuota</th>
                                                <th class="text-right">Monto</th>
                                                <th>Vencimiento</th>
                                                <th class="text-right">Pagado</th>
                                                <th class="text-right">Saldo</th>
                                                <th class="text-center">Estado</th>
                                            </tr>
                                        </thead>

                                        <tbody id="detalleCuotasm"></tbody>

                                        <tfoot>
                                            <tr>
                                                <th colspan="4" class="text-right">
                                                    Total pendiente
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
                        </section>

                        <section class="venta-seccion">
                            <div class="venta-seccion-cabecera">
                                <h6 class="venta-seccion-titulo">
                                    <i class="fas fa-box-open"></i>
                                    Productos vendidos
                                </h6>
                            </div>

                            <div class="table-responsive">
                                <table
                                    id="detallesm"
                                    class="table venta-detalle-tabla">
                                    <tbody>
                                        <tr>
                                            <td class="venta-detalle-vacio">
                                                Cargando detalle...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>

                    <div class="modal-footer venta-modal-footer">
                        <button
                            class="btn btn-outline-secondary"
                            type="button"
                            data-dismiss="modal">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!--FIN MODAL: VISTA DE VENTA-->
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