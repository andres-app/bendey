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

if ((int)($_SESSION['ventas'] ?? 0) === 1) {
?>
    <div class="main-content">
        <section class="section">
            <div class="section-body">

                <div class="card sunat-card">

                    <div
                        class="card-header d-flex flex-column flex-md-row
                               justify-content-between align-items-md-center">

                        <div>
                            <h4 class="mb-1">
                                Estado de comprobantes SUNAT
                            </h4>

                            <p class="text-muted mb-0 sunat-subtitle">
                                Consulta, envía o reintenta cada comprobante
                                según su estado actual.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm mt-3 mt-md-0"
                            id="btnActualizarSunat">

                            <i class="fas fa-sync-alt mr-1"></i>
                            Actualizar tabla
                        </button>

                    </div>

                    <div class="card-body">

                        <div class="table-responsive" id="sunatTableResponsive">

                            <table
                                id="tbllistado"
                                class="table table-hover sunat-table"
                                style="width:100%;">

                                <thead>
                                    <tr>
                                        <th>Comprobante</th>
                                        <th>Cliente</th>
                                        <th class="text-right">Total</th>
                                        <th class="text-center">XML</th>
                                        <th class="text-center">CDR</th>
                                        <th class="text-center">Estado SUNAT</th>
                                        <th>Respuesta SUNAT</th>
                                        <th class="text-center">Fecha</th>
                                        <th class="text-right">Acciones</th>
                                    </tr>
                                </thead>

                                <tbody></tbody>

                            </table>

                        </div>

                    </div>
                </div>

            </div>
        </section>
    </div>

    <style>
        .sunat-card {
            border: 0;
            box-shadow: 0 8px 28px rgba(15, 23, 42, .06);
        }

        .sunat-card .card-header {
            min-height: 78px;
            padding-top: 18px;
            padding-bottom: 18px;
            border-bottom: 1px solid #edf0f2;
        }

        .sunat-subtitle {
            font-size: .84rem;
            line-height: 1.45;
        }

        .sunat-table {
            margin-bottom: 0;
        }

        .sunat-table thead th {
            padding-top: 13px;
            padding-bottom: 13px;
            border-top: 0;
            border-bottom: 1px solid #dfe4e8;
            color: #58616b;
            background: #f7f8f9;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
            vertical-align: middle;
            white-space: nowrap;
        }

        .sunat-table tbody td {
            padding-top: 12px;
            padding-bottom: 12px;
            border-top-color: #edf0f2;
            vertical-align: middle;
        }

        .sunat-table tbody tr:hover {
            background: #fafbfc;
        }

        .sunat-action-btn {
            min-width: 108px;
            border-color: #cfd5da;
            border-radius: 8px;
            color: #4d5761;
            background: #fff;
            font-weight: 600;
            white-space: nowrap;
        }

        .sunat-action-btn:hover,
        .sunat-action-btn:focus {
            color: #29323a;
            border-color: #aeb6bd;
            background: #f4f6f7;
            box-shadow: none;
        }

        .sunat-file-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 58px;
            padding: 5px 9px;
            border: 1px solid #d8dde2;
            border-radius: 7px;
            color: #55606a;
            background: #fff;
            font-size: .76rem;
            font-weight: 700;
            text-decoration: none !important;
        }

        .sunat-file-link:hover {
            color: #25313b;
            border-color: #b7c0c8;
            background: #f5f7f8;
        }

        .sunat-file-empty {
            color: #adb5bd;
        }

        .sunat-response-button {
            width: 100%;
            max-width: 360px;
            padding: 0;
            border: 0;
            color: inherit;
            background: transparent;
            text-align: left;
            cursor: pointer;
        }

        .sunat-response-button:hover .sunat-response-text,
        .sunat-response-button:focus .sunat-response-text {
            color: #26313b;
        }

        .sunat-response-button small {
            display: block;
            margin-top: 3px;
            color: #6777ef;
            font-size: .68rem;
            font-weight: 700;
        }

        .sunat-response-text {
            display: block;
            max-width: 340px;
            overflow: hidden;
            color: #66717b;
            font-size: .78rem;
            line-height: 1.35;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sunat-detail-box {
            max-height: 310px;
            overflow: auto;
            padding: 13px 14px;
            border: 1px solid #e2e6ea;
            border-radius: 10px;
            color: #3f4852;
            background: #f8f9fa;
            font-size: .78rem;
            line-height: 1.48;
            text-align: left;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .sunat-detail-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px;
            margin-bottom: 12px;
        }

        .sunat-detail-meta > div {
            padding: 9px 11px;
            border: 1px solid #e4e7eb;
            border-radius: 9px;
            background: #fff;
            text-align: left;
        }

        .sunat-detail-meta span {
            display: block;
            color: #8a939d;
            font-size: .66rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .sunat-detail-meta strong {
            display: block;
            margin-top: 3px;
            color: #303a44;
            font-size: .82rem;
        }

        #tbllistado td:last-child,
        #tbllistado th:last-child {
            text-align: right !important;
            white-space: nowrap;
        }

        #tbllistado_wrapper .dataTables_filter input {
            border: 1px solid #d9dee3;
            border-radius: 8px;
            box-shadow: none;
        }

        #tbllistado_wrapper .dataTables_length select {
            border: 1px solid #d9dee3;
            border-radius: 7px;
        }

        @media (max-width: 767.98px) {
            .sunat-card .card-header {
                align-items: stretch !important;
            }

            .sunat-action-btn {
                width: 100%;
                min-width: 0;
            }

            .sunat-response-button,
            .sunat-response-text {
                max-width: 220px;
            }

            .sunat-detail-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>

<?php
} else {
    require 'access.php';
}

require 'footer.php';

$rutaSunatJs =
    __DIR__ . '/scripts/sunat.js';

$versionSunatJs =
    is_file($rutaSunatJs)
        ? filemtime($rutaSunatJs)
        : time();
?>

<script
    src="Views/modules/scripts/sunat.js?v=<?= (int)$versionSunatJs ?>">
</script>

<?php
ob_end_flush();
