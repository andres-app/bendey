<?php

ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['nombre'])) {
    header('Location: login');
    exit;
}

require 'header.php';
require 'sidebar.php';

$idventa = (int)($_GET['idventa'] ?? 0);

/*
|--------------------------------------------------------------------------
| RESPALDO PARA RUTAS AMIGABLES
|--------------------------------------------------------------------------
| Algunos RewriteRule cargan index.php?url=notacredito sin conservar la
| cadena original ?idventa=123. El navegador sí mantiene REQUEST_URI,
| por lo que recuperamos el parámetro desde allí.
*/
if ($idventa <= 0) {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $queryString = parse_url($requestUri, PHP_URL_QUERY);

    if (is_string($queryString) && $queryString !== '') {
        $parametrosRuta = [];
        parse_str($queryString, $parametrosRuta);

        $idventa = (int)(
            $parametrosRuta['idventa']
            ?? 0
        );
    }
}

if ((int)($_SESSION['ventas'] ?? 0) === 1) {
?>
    <div class="main-content">
        <section class="section">
            <div class="section-body">

                <div class="nc-page-header">
                    <div>
                        <a href="listsales" class="nc-back-link">
                            <i class="fas fa-arrow-left"></i>
                            Volver a ventas
                        </a>

                        <h1>Generar nota de crédito</h1>
                        <p>
                            Selecciona el motivo, los productos afectados y la forma de devolución.
                        </p>
                    </div>

                    <span class="nc-document-badge">
                        <i class="fas fa-file-invoice-dollar"></i>
                        Documento SUNAT 07
                    </span>
                </div>

                <div
                    class="alert alert-light border nc-info-alert"
                    id="ncEstadoCarga">
                    <span class="spinner-border spinner-border-sm mr-2"></span>
                    Cargando el comprobante original...
                </div>

                <form id="formNotaCredito" autocomplete="off" style="display:none;">
                    <input
                        type="hidden"
                        id="idventa"
                        name="idventa"
                        value="<?= $idventa ?>">

                    <div class="row">
                        <div class="col-xl-8 col-lg-7 col-12">

                            <div class="card nc-card">
                                <div class="card-header nc-card-header">
                                    <div>
                                        <h4>Comprobante original</h4>
                                        <small>
                                            La nota conservará al mismo cliente y la referencia tributaria.
                                        </small>
                                    </div>
                                    <span
                                        class="badge-sunat sunat-aceptado"
                                        id="ncEstadoSunatOriginal">
                                        Aceptado
                                    </span>
                                </div>

                                <div class="card-body">
                                    <div class="row nc-data-grid">
                                        <div class="col-md-4 col-6">
                                            <span>Comprobante</span>
                                            <strong id="ncComprobanteOriginal">—</strong>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <span>Fecha</span>
                                            <strong id="ncFechaOriginal">—</strong>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <span>Condición</span>
                                            <strong id="ncCondicionOriginal">—</strong>
                                        </div>
                                        <div class="col-md-8 col-12">
                                            <span>Cliente</span>
                                            <strong id="ncClienteOriginal">—</strong>
                                            <small id="ncDocumentoCliente"></small>
                                        </div>
                                        <div class="col-md-4 col-6">
                                            <span>Total original</span>
                                            <strong id="ncTotalOriginal">S/ 0.00</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card nc-card">
                                <div class="card-header nc-card-header">
                                    <div>
                                        <h4>Motivo de la nota</h4>
                                        <small>
                                            Los motivos disponibles dependen del tipo de comprobante.
                                        </small>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-5 col-12 form-group">
                                            <label for="codigo_motivo">
                                                Motivo SUNAT <span class="text-danger">*</span>
                                            </label>
                                            <select
                                                class="form-control"
                                                id="codigo_motivo"
                                                name="codigo_motivo"
                                                required>
                                                <option value="">Seleccione...</option>
                                            </select>
                                        </div>

                                        <div class="col-md-7 col-12 form-group">
                                            <label for="sustento">
                                                Sustento <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                class="form-control"
                                                id="sustento"
                                                name="sustento"
                                                maxlength="250"
                                                placeholder="Ej.: Devolución de productos"
                                                required>
                                        </div>

                                        <div class="col-12 form-group mb-0">
                                            <label for="observacion">Observación interna</label>
                                            <textarea
                                                class="form-control"
                                                id="observacion"
                                                name="observacion"
                                                rows="2"
                                                maxlength="500"
                                                placeholder="Opcional"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card nc-card">
                                <div class="card-header nc-card-header nc-products-header">
                                    <div>
                                        <h4>Productos afectados</h4>
                                        <small id="ncAyudaProductos">
                                            Selecciona los productos y cantidades que serán acreditados.
                                        </small>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        id="btnSeleccionarTodo">
                                        <i class="fas fa-check-double mr-1"></i>
                                        Seleccionar todo
                                    </button>
                                </div>

                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table nc-products-table mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="text-center" style="width:52px;">Incluir</th>
                                                    <th>Producto</th>
                                                    <th class="text-center">Vendido</th>
                                                    <th class="text-center">Disponible</th>
                                                    <th class="text-center" style="width:130px;">Cantidad NC</th>
                                                    <th class="text-right">Precio</th>
                                                    <th class="text-right">Importe</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ncDetalleProductos"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="card nc-card" id="ncCardDevolucion">
                                <div class="card-header nc-card-header">
                                    <div>
                                        <h4>Aplicación financiera</h4>
                                        <small id="ncAyudaFinanciera">
                                            Registra cómo se devolverá el importe al cliente.
                                        </small>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div
                                        class="nc-credit-summary"
                                        id="ncResumenCredito"
                                        style="display:none;">
                                        <div>
                                            <span>Se reducirá de cuotas pendientes</span>
                                            <strong id="ncMontoCuotas">S/ 0.00</strong>
                                        </div>
                                        <div>
                                            <span>Importe que debe devolverse</span>
                                            <strong id="ncMontoDevolverCredito">S/ 0.00</strong>
                                        </div>
                                    </div>

                                    <div id="ncPagosDevolucion">
                                        <div class="nc-payment-row" data-payment-row>
                                            <div class="row align-items-end">
                                                <div class="col-md-7 col-12 form-group mb-md-0">
                                                    <label>Forma de devolución</label>
                                                    <select
                                                        class="form-control nc-forma-pago"
                                                        aria-label="Forma de devolución">
                                                        <option value="">Seleccione...</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 col-9 form-group mb-md-0">
                                                    <label>Monto</label>
                                                    <input
                                                        type="number"
                                                        class="form-control nc-monto-pago"
                                                        min="0"
                                                        step="0.01"
                                                        value="0.00">
                                                </div>
                                                <div class="col-md-1 col-3 form-group mb-0 text-right">
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-danger nc-remove-payment"
                                                        title="Quitar forma">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm mt-3"
                                        id="btnAgregarFormaDevolucion">
                                        <i class="fas fa-plus mr-1"></i>
                                        Agregar otra forma
                                    </button>

                                    <div class="nc-payment-total mt-3">
                                        <span>Total asignado</span>
                                        <strong id="ncTotalPagos">S/ 0.00</strong>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="col-xl-4 col-lg-5 col-12">
                            <div class="card nc-card nc-summary-card">
                                <div class="card-header nc-card-header">
                                    <div>
                                        <h4>Resumen</h4>
                                        <small>Importes calculados automáticamente.</small>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="nc-summary-line">
                                        <span>Saldo acreditable</span>
                                        <strong id="ncSaldoAcreditable">S/ 0.00</strong>
                                    </div>
                                    <div class="nc-summary-line nc-tax-summary-line" id="ncFilaGravada">
                                        <span>Operación gravada</span>
                                        <strong id="ncTotalGravado">S/ 0.00</strong>
                                    </div>
                                    <div class="nc-summary-line nc-tax-summary-line" id="ncFilaExonerada" style="display:none;">
                                        <span>Operación exonerada</span>
                                        <strong id="ncTotalExonerado">S/ 0.00</strong>
                                    </div>
                                    <div class="nc-summary-line nc-tax-summary-line" id="ncFilaInafecta" style="display:none;">
                                        <span>Operación inafecta</span>
                                        <strong id="ncTotalInafecto">S/ 0.00</strong>
                                    </div>
                                    <div class="nc-summary-line nc-tax-summary-line" id="ncFilaExportacion" style="display:none;">
                                        <span>Exportación</span>
                                        <strong id="ncTotalExportacion">S/ 0.00</strong>
                                    </div>
                                    <div class="nc-summary-line nc-tax-summary-line" id="ncFilaIgv">
                                        <span>IGV</span>
                                        <strong id="ncIgv">S/ 0.00</strong>
                                    </div>
                                    <div class="nc-summary-line nc-summary-total">
                                        <span>Total nota</span>
                                        <strong id="ncTotalNota">S/ 0.00</strong>
                                    </div>

                                    <div class="form-group mt-4 mb-0">
                                        <label for="modo_envio">Envío a SUNAT</label>
                                        <select
                                            class="form-control"
                                            id="modo_envio"
                                            name="modo_envio">
                                            <option value="INMEDIATO">
                                                Enviar inmediatamente
                                            </option>
                                            <option value="MANUAL">
                                                Guardar para enviar después
                                            </option>
                                        </select>
                                        <small class="form-text text-muted">
                                            El stock, la caja y las cuotas se actualizarán únicamente cuando SUNAT acepte la nota.
                                        </small>
                                    </div>
                                </div>

                                <div class="card-footer nc-summary-footer">
                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-block"
                                        id="btnGuardarNota">
                                        <i class="fas fa-file-invoice-dollar mr-2"></i>
                                        Generar nota de crédito
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

            </div>
        </section>
    </div>

    <style>
        .nc-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 18px;
        }

        .nc-page-header h1 {
            margin: 7px 0 4px;
            color: #27313f;
            font-size: 1.55rem;
            font-weight: 800;
        }

        .nc-page-header p {
            margin: 0;
            color: #77808c;
            font-size: .86rem;
        }

        .nc-back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #657080;
            font-size: .78rem;
            font-weight: 700;
        }

        .nc-document-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 13px;
            border: 1px solid #d8dee6;
            border-radius: 10px;
            color: #4b5665;
            background: #fff;
            font-size: .78rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .nc-info-alert {
            border-radius: 12px;
            color: #596574;
            background: #fff;
        }

        .nc-card {
            overflow: hidden;
            border: 0;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
        }

        .nc-card-header {
            min-height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 15px 19px;
            border-bottom: 1px solid #edf0f3;
            background: #fff;
        }

        .nc-card-header h4 {
            margin: 0 0 3px;
            color: #27313f;
            font-size: .95rem;
            font-weight: 800;
        }

        .nc-card-header small {
            color: #828b97;
            font-size: .73rem;
        }

        .nc-data-grid > div {
            margin-bottom: 16px;
        }

        .nc-data-grid span,
        .nc-data-grid small {
            display: block;
            color: #8a939e;
            font-size: .67rem;
            font-weight: 700;
            letter-spacing: .035em;
            text-transform: uppercase;
        }

        .nc-data-grid strong {
            display: block;
            margin-top: 4px;
            color: #303a47;
            font-size: .9rem;
            font-weight: 750;
        }

        .nc-data-grid small {
            margin-top: 3px;
            font-weight: 600;
            letter-spacing: 0;
            text-transform: none;
        }

        .nc-products-table thead th {
            border-top: 0;
            border-bottom: 1px solid #e2e7ec;
            color: #657080;
            background: #f7f8fa;
            font-size: .67rem;
            font-weight: 800;
            letter-spacing: .035em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .nc-products-table tbody td {
            vertical-align: middle;
            border-top-color: #edf0f3;
            color: #414b58;
            font-size: .8rem;
        }

        .nc-product-name {
            color: #2e3845;
            font-weight: 700;
        }

        .nc-product-code {
            display: block;
            margin-top: 2px;
            color: #929aa5;
            font-size: .68rem;
        }

        .nc-quantity-input {
            min-width: 92px;
            text-align: center;
            font-weight: 750;
        }

        .nc-credit-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .nc-credit-summary > div {
            padding: 13px;
            border: 1px solid #e2e6eb;
            border-radius: 10px;
            background: #f8f9fb;
        }

        .nc-credit-summary span {
            display: block;
            color: #7b8591;
            font-size: .68rem;
            font-weight: 700;
        }

        .nc-credit-summary strong {
            display: block;
            margin-top: 5px;
            color: #303a47;
            font-size: 1rem;
        }

        .nc-payment-row {
            padding: 13px;
            border: 1px solid #e2e6eb;
            border-radius: 11px;
            background: #fafbfc;
        }

        .nc-payment-row + .nc-payment-row {
            margin-top: 10px;
        }

        .nc-remove-payment {
            min-width: 39px;
            height: 39px;
        }

        .nc-payment-total {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 12px;
            border-top: 1px solid #e5e8ec;
            color: #596472;
            font-size: .8rem;
            font-weight: 700;
        }

        .nc-payment-total strong {
            color: #293340;
            font-size: 1rem;
        }

        .nc-summary-card {
            position: sticky;
            top: 88px;
        }

        .nc-summary-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 11px 0;
            border-bottom: 1px solid #edf0f3;
            color: #67717e;
            font-size: .8rem;
        }

        .nc-summary-line strong {
            color: #303a47;
        }

        .nc-summary-total {
            margin-top: 6px;
            padding-top: 16px;
            border-bottom: 0;
            font-size: .95rem;
            font-weight: 800;
        }

        .nc-summary-total strong {
            font-size: 1.45rem;
        }

        .nc-summary-footer {
            padding: 14px 19px;
            border-top: 1px solid #edf0f3;
            background: #fff;
        }

        .nc-summary-footer .btn {
            min-height: 46px;
            border-radius: 10px;
            font-weight: 800;
        }



        .nc-tax-badge {
            display: inline-flex;
            align-items: center;
            margin-top: 5px;
            padding: 3px 7px;
            border: 1px solid #e1e5ea;
            border-radius: 999px;
            color: #657080;
            background: #f8f9fb;
            font-size: .62rem;
            font-weight: 750;
            line-height: 1;
            white-space: nowrap;
        }

        .nc-tax-badge.tax-10 {
            border-color: #cfe2ff;
            color: #315b94;
            background: #f0f6ff;
        }

        .nc-tax-badge.tax-20 {
            border-color: #cfe9d8;
            color: #317347;
            background: #f1faf4;
        }

        .nc-tax-badge.tax-30 {
            border-color: #ddd8f4;
            color: #62518c;
            background: #f6f4fc;
        }

        .nc-tax-badge.tax-40 {
            border-color: #f0d8b7;
            color: #8b5f1d;
            background: #fff8ee;
        }

        @media (max-width: 991.98px) {
            .nc-summary-card {
                position: static;
            }
        }

        @media (max-width: 767.98px) {
            .nc-page-header {
                flex-direction: column;
            }

            .nc-document-badge {
                align-self: flex-start;
            }

            .nc-products-header {
                align-items: stretch;
                flex-direction: column;
            }

            .nc-credit-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
<?php
} else {
    require 'access.php';
}

require 'footer.php';

$rutaJs = __DIR__ . '/scripts/notacredito.js';
$versionJs = is_file($rutaJs) ? filemtime($rutaJs) : time();
?>
<script src="Views/modules/scripts/notacredito.js?v=<?= (int)$versionJs ?>"></script>
<?php
ob_end_flush();
