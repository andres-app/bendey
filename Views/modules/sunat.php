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
                            <span class="sunat-kicker">
                                Centro de control electrónico
                            </span>

                            <h4 class="mb-1">
                                Bandeja SUNAT
                            </h4>

                            <p class="text-muted mb-0 sunat-subtitle">
                                Facturas, boletas y notas de crédito en una sola bandeja,
                                con el mismo estado que se muestra en Ventas.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm mt-3 mt-md-0"
                            id="btnActualizarSunat">

                            <i class="fas fa-sync-alt mr-1"></i>
                            Actualizar
                        </button>

                    </div>

                    <div class="card-body">

                        <div class="sunat-toolbar">
                            <div class="sunat-toolbar-copy">
                                <strong>Estado del documento</strong>
                                <small>
                                    Filtra rápidamente los documentos que requieren atención.
                                </small>
                            </div>

                            <div
                                class="sunat-status-filters"
                                id="sunatStatusFilters"
                                role="group"
                                aria-label="Filtrar documentos por estado SUNAT">

                                <button
                                    type="button"
                                    class="sunat-status-filter is-active"
                                    data-filtro="TODOS">
                                    Todos
                                </button>

                                <button
                                    type="button"
                                    class="sunat-status-filter"
                                    data-filtro="PENDIENTES">
                                    Pendientes
                                </button>

                                <button
                                    type="button"
                                    class="sunat-status-filter"
                                    data-filtro="PROCESO">
                                    En proceso
                                </button>

                                <button
                                    type="button"
                                    class="sunat-status-filter"
                                    data-filtro="RECHAZADOS">
                                    Rechazados
                                </button>

                                <button
                                    type="button"
                                    class="sunat-status-filter"
                                    data-filtro="ACEPTADOS">
                                    Aceptados
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive" id="sunatTableResponsive">

                            <table
                                id="tbllistado"
                                class="table table-hover sunat-table"
                                style="width:100%;">

                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Cliente</th>
                                        <th class="text-right">Total</th>
                                        <th class="text-center">Estado SUNAT</th>
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
            min-height: 88px;
            padding: 18px 22px;
            border-bottom: 1px solid #edf0f2;
        }

        .sunat-kicker {
            display: block;
            margin-bottom: 4px;
            color: #98a2b3;
            font-size: .64rem;
            font-weight: 750;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .sunat-subtitle {
            max-width: 720px;
            font-size: .84rem;
            line-height: 1.45;
        }

        .sunat-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 16px;
            padding: 14px 15px;
            border: 1px solid #e7eaee;
            border-radius: 12px;
            background: #fafbfc;
        }

        .sunat-toolbar-copy {
            min-width: 0;
        }

        .sunat-toolbar-copy strong {
            display: block;
            color: #344054;
            font-size: .8rem;
            font-weight: 700;
        }

        .sunat-toolbar-copy small {
            display: block;
            margin-top: 2px;
            color: #98a2b3;
            font-size: .68rem;
        }

        .sunat-status-filters {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 5px;
            padding: 4px;
            border: 1px solid #e4e7ec;
            border-radius: 10px;
            background: #f2f4f7;
        }

        .sunat-status-filter {
            min-height: 34px;
            padding: 0 11px;
            border: 0;
            border-radius: 7px;
            color: #667085;
            background: transparent;
            box-shadow: none;
            font-size: .72rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .sunat-status-filter:hover,
        .sunat-status-filter:focus {
            color: #344054;
            background: rgba(255, 255, 255, .7);
            outline: none;
        }

        .sunat-status-filter.is-active {
            color: #4f5fd1;
            background: #ffffff;
            box-shadow: 0 2px 7px rgba(15, 23, 42, .08);
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
            font-size: .72rem;
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

        .sunat-document-cell {
            min-width: 175px;
        }

        .sunat-document-main {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }

        .sunat-document-main strong {
            color: #344054;
            font-size: .82rem;
            font-weight: 750;
        }

        .sunat-document-kind {
            display: inline-flex;
            align-items: center;
            min-height: 20px;
            padding: 2px 7px;
            border: 1px solid #dfe4ea;
            border-radius: 999px;
            color: #667085;
            background: #f8fafc;
            font-size: .61rem;
            font-weight: 750;
            letter-spacing: .025em;
            text-transform: uppercase;
        }

        .sunat-document-kind-note {
            border-color: #f1d7b5;
            color: #8a5b16;
            background: #fffaf2;
        }

        .sunat-document-cell > small {
            display: block;
            margin-top: 5px;
            color: #98a2b3;
            font-size: .67rem;
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
            word-break: break-word;
        }

        .sunat-detail-files {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0 0 12px;
        }

        .sunat-detail-file {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 36px;
            padding: 0 12px;
            border: 1px solid #d8dde2;
            border-radius: 8px;
            color: #4f5964;
            background: #ffffff;
            font-size: .73rem;
            font-weight: 700;
            text-decoration: none !important;
        }

        .sunat-detail-file:hover {
            border-color: #b7c0c8;
            color: #25313b;
            background: #f5f7f8;
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

        @media (max-width: 991.98px) {
            .sunat-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .sunat-status-filters {
                width: 100%;
            }

            .sunat-status-filter {
                flex: 1 1 auto;
            }
        }

        @media (max-width: 767.98px) {
            .sunat-card .card-header {
                align-items: stretch !important;
            }

            .sunat-action-btn {
                width: 100%;
                min-width: 0;
            }

            .sunat-status-filters {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sunat-status-filter:first-child {
                grid-column: 1 / -1;
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

$rutaSunatJs = __DIR__ . '/scripts/sunat.js';
$versionSunatJs = is_file($rutaSunatJs)
    ? filemtime($rutaSunatJs)
    : time();
?>

<script
    src="Views/modules/scripts/sunat.js?v=<?= (int)$versionSunatJs ?>">
</script>

<?php
ob_end_flush();
