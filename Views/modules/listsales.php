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

            .venta-ver-detalle-btn {
                width: 36px;
                height: 34px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                border: 1px solid #d9defa;
                border-radius: 8px;
                color: #5665d8;
                background: #f5f6ff;
                box-shadow: none !important;
                font-weight: 400;
            }

            .venta-ver-detalle-btn:hover,
            .venta-ver-detalle-btn:focus {
                border-color: #bfc7f5;
                color: #4050c7;
                background: #ecefff;
                outline: none;
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

            /* =========================================================
               PESTAÑAS DE DOCUMENTOS
            ========================================================== */
            .ventas-documentos-tabs {
                display: inline-flex;
                flex-wrap: wrap;
                gap: 7px;
                margin-bottom: 18px;
                padding: 5px;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                background: #f7f8fa;
            }

            .ventas-documentos-tabs .nav-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                min-height: 38px;
                padding: 8px 14px;
                border: 0;
                border-radius: 8px;
                color: #667085;
                background: transparent;
                font-size: .82rem;
                font-weight: 650;
            }

            .ventas-documentos-tabs .nav-link:hover {
                color: #344054;
                background: #ffffff;
            }

            .ventas-documentos-tabs .nav-link.active {
                color: #1f2937;
                background: #ffffff;
                box-shadow: 0 3px 10px rgba(15, 23, 42, .08);
            }

            .ventas-documentos-tabs .documento-contador {
                min-width: 23px;
                height: 21px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0 7px;
                border-radius: 999px;
                color: #667085;
                background: #eaecf0;
                font-size: .68rem;
                font-weight: 750;
            }

            .ventas-documentos-tabs .nav-link.active .documento-contador {
                color: #344054;
                background: #f2f4f7;
            }

            .venta-numero-documento,
            .nota-numero-celda,
            .nota-motivo-celda {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .venta-nota-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 3px 8px;
                border: 1px solid #f2d4a8;
                border-radius: 999px;
                color: #8a5b16;
                background: #fffaf0;
                font-size: .67rem;
                font-weight: 700;
                white-space: nowrap;
            }

            .nota-tipo-label {
                color: #667085;
                font-size: .63rem;
                font-weight: 750;
                letter-spacing: .045em;
            }

            .nota-origen-link {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                color: #475467;
                font-weight: 650;
                text-decoration: none;
                white-space: nowrap;
            }

            .nota-origen-link:hover {
                color: #1d2939;
                text-decoration: underline;
            }

            .nota-motivo-celda {
                max-width: 310px;
            }

            .nota-motivo-celda strong {
                color: #344054;
                font-size: .78rem;
                line-height: 1.25;
            }

            .nota-motivo-celda small {
                width: 100%;
                overflow: hidden;
                color: #7b8491;
                font-size: .7rem;
                line-height: 1.25;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .nota-total-negativo {
                color: #b42318;
                font-weight: 750;
                white-space: nowrap;
            }

            #tblNotasCredito tbody tr:hover {
                background: #fffafa;
            }

            @media (max-width: 767.98px) {
                .ventas-documentos-tabs {
                    width: 100%;
                }

                .ventas-documentos-tabs .nav-item {
                    flex: 1 1 0;
                }

                .ventas-documentos-tabs .nav-link {
                    width: 100%;
                    justify-content: center;
                }
            }


            /* =========================================================
               CABECERA Y FILTROS PREMIUM
            ========================================================== */
            .ventas-page-header {
                min-height: 92px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 20px;
                padding: 18px 22px !important;
                border-bottom: 1px solid #edf0f3;
            }

            .ventas-page-heading {
                min-width: 0;
            }

            .ventas-page-kicker {
                display: block;
                margin-bottom: 3px;
                color: #8b95a5;
                font-size: .66rem;
                font-weight: 750;
                letter-spacing: .075em;
                text-transform: uppercase;
            }

            .ventas-page-title {
                margin: 0;
                color: #202938;
                font-size: 1.18rem;
                font-weight: 750;
                line-height: 1.25;
            }

            .ventas-page-subtitle {
                margin: 4px 0 0;
                color: #7a8493;
                font-size: .79rem;
                line-height: 1.4;
            }

            .ventas-btn-agregar {
                min-width: 132px;
                height: 40px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 0 16px;
                border-radius: 9px;
                box-shadow: 0 5px 14px rgba(40, 167, 69, .18);
                font-size: .8rem;
                font-weight: 700;
                white-space: nowrap;
            }

            .ventas-control-panel {
                margin-bottom: 20px;
                border: 1px solid #e4e8ee;
                border-radius: 15px;
                background: #ffffff;
                box-shadow: 0 7px 22px rgba(15, 23, 42, .045);
            }

            .ventas-control-summary {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 18px;
                padding: 17px 18px;
                border-bottom: 1px solid #edf0f3;
                background: linear-gradient(180deg, #ffffff 0%, #fbfcfd 100%);
                border-radius: 15px 15px 0 0;
            }

            .ventas-summary-copy {
                min-width: 0;
            }

            .ventas-summary-eyebrow {
                display: block;
                margin-bottom: 3px;
                color: #98a2b3;
                font-size: .64rem;
                font-weight: 750;
                letter-spacing: .06em;
                text-transform: uppercase;
            }

            .ventas-summary-copy strong {
                display: block;
                color: #344054;
                font-size: .86rem;
                font-weight: 700;
            }

            .ventas-summary-cards {
                display: flex;
                align-items: center;
                gap: 9px;
            }

            .ventas-summary-card {
                min-width: 126px;
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 9px 12px;
                border: 1px solid #e4e7ec;
                border-radius: 11px;
                color: #667085;
                background: #ffffff;
                box-shadow: none;
                cursor: pointer;
                text-align: left;
                transition: .18s ease;
            }

            .ventas-summary-card:hover {
                border-color: #cfd5de;
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(15, 23, 42, .06);
            }

            .ventas-summary-card.is-active {
                border-color: #cdd4f8;
                color: #344054;
                background: #f7f8ff;
                box-shadow: 0 0 0 3px rgba(103, 119, 239, .07);
            }

            .ventas-summary-icon {
                width: 34px;
                height: 34px;
                flex: 0 0 34px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 9px;
                color: #667085;
                background: #f2f4f7;
                font-size: .8rem;
            }

            .ventas-summary-card.is-active .ventas-summary-icon {
                color: #5665d8;
                background: #e9ebff;
            }

            .ventas-summary-data {
                display: flex;
                flex-direction: column;
                line-height: 1.05;
            }

            .ventas-summary-data small {
                margin-bottom: 4px;
                color: #8b95a5;
                font-size: .64rem;
                font-weight: 650;
            }

            .ventas-summary-data strong {
                color: #344054;
                font-size: .95rem;
                font-weight: 750;
            }

            .ventas-filter-grid {
                display: grid;
                grid-template-columns:
                    minmax(240px, 1.35fr)
                    minmax(145px, .8fr)
                    minmax(138px, .72fr)
                    minmax(138px, .72fr)
                    minmax(170px, .9fr)
                    minmax(235px, 1.35fr)
                    auto;
                gap: 12px;
                align-items: end;
                padding: 17px 18px;
            }

            .ventas-filter-field label {
                display: block;
                margin-bottom: 6px;
                color: #667085;
                font-size: .66rem;
                font-weight: 750;
                letter-spacing: .035em;
                text-transform: uppercase;
            }

            .ventas-filter-field .form-control {
                height: 40px;
                border: 1px solid #dfe4ea;
                border-radius: 9px;
                color: #344054;
                background: #ffffff;
                box-shadow: none;
                font-size: .78rem;
            }

            .ventas-filter-field .form-control:focus {
                border-color: #9aa7f0;
                box-shadow: 0 0 0 3px rgba(103, 119, 239, .09);
            }

            .ventas-input-icon {
                position: relative;
            }

            .ventas-input-icon > i {
                position: absolute;
                z-index: 2;
                top: 50%;
                left: 12px;
                color: #98a2b3;
                font-size: .76rem;
                transform: translateY(-50%);
                pointer-events: none;
            }

            .ventas-input-icon .form-control {
                padding-left: 34px;
            }

            .ventas-segmented-control {
                height: 40px;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 4px;
                padding: 4px;
                border: 1px solid #dfe4ea;
                border-radius: 10px;
                background: #f6f7f9;
            }

            .ventas-segmented-option {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 7px;
                padding: 0 10px;
                border: 0;
                border-radius: 7px;
                color: #667085;
                background: transparent;
                box-shadow: none;
                font-size: .74rem;
                font-weight: 700;
                white-space: nowrap;
            }

            .ventas-segmented-option:hover {
                color: #344054;
                background: rgba(255, 255, 255, .65);
            }

            .ventas-segmented-option.active {
                color: #344054;
                background: #ffffff;
                box-shadow: 0 2px 7px rgba(15, 23, 42, .08);
            }

            .ventas-filter-actions {
                display: flex;
                align-items: flex-end;
            }

            .ventas-btn-limpiar {
                height: 40px;
                min-width: 92px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 7px;
                padding: 0 13px;
                border: 1px solid #dfe4ea;
                border-radius: 9px;
                color: #667085;
                background: #ffffff;
                font-size: .74rem;
                font-weight: 700;
            }

            .ventas-btn-limpiar:hover,
            .ventas-btn-limpiar:focus {
                border-color: #c7ced8;
                color: #344054;
                background: #f8fafc;
                box-shadow: none;
            }

            .ventas-export-row {
                min-height: 66px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 18px;
                padding: 12px 18px;
                border-top: 1px solid #edf0f3;
                background: #fbfcfd;
                border-radius: 0 0 15px 15px;
            }

            .ventas-export-copy {
                min-width: 0;
            }

            .ventas-export-copy span {
                display: block;
                color: #475467;
                font-size: .76rem;
                font-weight: 700;
            }

            .ventas-export-copy small {
                display: block;
                margin-top: 2px;
                color: #98a2b3;
                font-size: .68rem;
            }

            .ventas-exportadores {
                margin-left: auto;
            }

            .ventas-exportadores .dt-buttons {
                display: flex;
                align-items: center;
                gap: 9px;
                margin: 0;
            }

            .ventas-exportadores .dt-button,
            .ventas-exportadores .btn {
                min-width: 128px !important;
                height: 40px !important;
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
                gap: 7px;
                margin: 0 !important;
                padding: 0 17px !important;
                border-radius: 9px !important;
                box-shadow: none !important;
                font-size: .76rem !important;
                font-weight: 700 !important;
                line-height: 1 !important;
                white-space: nowrap;
            }

            .ventas-exportadores .buttons-excel {
                border: 1px solid #b7dfc3 !important;
                color: #237a3a !important;
                background: #f2fbf5 !important;
            }

            .ventas-exportadores .buttons-excel:hover,
            .ventas-exportadores .buttons-excel:focus {
                border-color: #8fcca1 !important;
                color: #17602c !important;
                background: #e9f8ee !important;
            }

            .ventas-exportadores .buttons-pdf {
                border: 1px solid #f0bfc5 !important;
                color: #b42335 !important;
                background: #fff5f6 !important;
            }

            .ventas-exportadores .buttons-pdf:hover,
            .ventas-exportadores .buttons-pdf:focus {
                border-color: #e39ca6 !important;
                color: #912018 !important;
                background: #ffecef !important;
            }

            .ventas-documento-panel {
                border: 1px solid #e5e9ef;
                border-radius: 13px;
                background: #ffffff;
                overflow: hidden;
            }

            .ventas-documento-panel .table-responsive {
                margin: 0;
            }

            #tbllistado_wrapper,
            #tblNotasCredito_wrapper {
                padding: 0;
            }

            #tbllistado_wrapper > .dt-buttons,
            #tblNotasCredito_wrapper > .dt-buttons {
                display: none;
            }

            #tbllistado_wrapper .dataTables_info,
            #tblNotasCredito_wrapper .dataTables_info {
                padding: 13px 16px;
                color: #8b95a5;
                font-size: .72rem;
            }

            #tbllistado_wrapper .dataTables_paginate,
            #tblNotasCredito_wrapper .dataTables_paginate {
                padding: 8px 14px 12px;
            }

            #tbllistado_wrapper .paginate_button,
            #tblNotasCredito_wrapper .paginate_button {
                min-width: 32px;
                height: 32px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin: 0 2px;
                padding: 0 !important;
                border: 1px solid transparent !important;
                border-radius: 8px !important;
                color: #667085 !important;
                background: transparent !important;
                box-shadow: none !important;
                font-size: .72rem;
            }

            #tbllistado_wrapper .paginate_button.current,
            #tblNotasCredito_wrapper .paginate_button.current {
                border-color: #d9defa !important;
                color: #4f5fd1 !important;
                background: #f2f3ff !important;
            }

            #tbllistado_wrapper .paginate_button:hover,
            #tblNotasCredito_wrapper .paginate_button:hover {
                border-color: #e4e7ec !important;
                color: #344054 !important;
                background: #f7f8fa !important;
            }

            @media (max-width: 1199.98px) {
                .ventas-filter-grid {
                    grid-template-columns:
                        repeat(3, minmax(0, 1fr));
                }

                .ventas-filter-documento,
                .ventas-filter-search {
                    grid-column: span 2;
                }
            }

            @media (max-width: 767.98px) {
                .ventas-page-header {
                    align-items: flex-start;
                    flex-direction: column;
                    padding: 16px !important;
                }

                .ventas-btn-agregar {
                    width: 100%;
                }

                .ventas-control-summary {
                    align-items: stretch;
                    flex-direction: column;
                }

                .ventas-summary-cards {
                    width: 100%;
                }

                .ventas-summary-card {
                    flex: 1 1 0;
                    min-width: 0;
                }

                .ventas-filter-grid {
                    grid-template-columns: 1fr;
                    padding: 15px;
                }

                .ventas-filter-documento,
                .ventas-filter-search {
                    grid-column: auto;
                }

                .ventas-export-row {
                    align-items: stretch;
                    flex-direction: column;
                }

                .ventas-exportadores {
                    width: 100%;
                    margin-left: 0;
                }

                .ventas-exportadores .dt-buttons {
                    width: 100%;
                }

                .ventas-exportadores .dt-button,
                .ventas-exportadores .btn {
                    flex: 1 1 0;
                    min-width: 0 !important;
                }
            }

            @media (max-width: 479.98px) {
                .ventas-summary-cards {
                    flex-direction: column;
                }

                .ventas-summary-card {
                    width: 100%;
                }

                .ventas-segmented-option {
                    font-size: .67rem;
                }
            }


            /* =========================================================
               SELECTOR BUSCABLE DE TIPO DE DOCUMENTO
            ========================================================== */
            .ventas-document-type-select {
                position: relative;
            }

            .ventas-document-type-trigger {
                width: 100%;
                height: 40px;
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 4px 11px 4px 7px;
                border: 1px solid #dfe4ea;
                border-radius: 10px;
                color: #344054;
                background: #ffffff;
                box-shadow: none;
                cursor: pointer;
                text-align: left;
                transition:
                    border-color .16s ease,
                    box-shadow .16s ease,
                    background-color .16s ease;
            }

            .ventas-document-type-trigger:hover {
                border-color: #c6ced8;
                background: #fcfcfd;
            }

            .ventas-document-type-select.is-open
            .ventas-document-type-trigger {
                border-color: #9aa7f0;
                box-shadow: 0 0 0 3px rgba(103, 119, 239, .09);
            }

            .ventas-document-type-trigger-icon {
                width: 29px;
                height: 29px;
                flex: 0 0 29px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                color: #5969d9;
                background: #eef0ff;
                font-size: .72rem;
            }

            .ventas-document-type-trigger-copy {
                min-width: 0;
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                line-height: 1.1;
            }

            .ventas-document-type-trigger-copy small {
                margin-bottom: 2px;
                color: #98a2b3;
                font-size: .56rem;
                font-weight: 650;
                letter-spacing: .035em;
                text-transform: uppercase;
            }

            .ventas-document-type-trigger-copy strong {
                overflow: hidden;
                color: #344054;
                font-size: .72rem;
                font-weight: 700;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .ventas-document-type-chevron {
                color: #98a2b3;
                font-size: .66rem;
                transition: transform .16s ease;
            }

            .ventas-document-type-select.is-open
            .ventas-document-type-chevron {
                transform: rotate(180deg);
            }

            .ventas-document-type-menu {
                position: absolute;
                z-index: 1080;
                top: calc(100% + 7px);
                left: 0;
                width: 360px;
                max-width: min(90vw, 360px);
                display: none;
                overflow: hidden;
                border: 1px solid #dfe4ea;
                border-radius: 13px;
                background: #ffffff;
                box-shadow: 0 18px 45px rgba(15, 23, 42, .16);
            }

            .ventas-document-type-select.is-open
            .ventas-document-type-menu {
                display: block;
                animation: ventasDocumentMenuIn .15s ease-out;
            }

            @keyframes ventasDocumentMenuIn {
                from {
                    opacity: 0;
                    transform: translateY(-4px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .ventas-document-type-search {
                position: relative;
                padding: 10px;
                border-bottom: 1px solid #edf0f3;
                background: #fbfcfd;
            }

            .ventas-document-type-search i {
                position: absolute;
                top: 50%;
                left: 22px;
                color: #98a2b3;
                font-size: .72rem;
                transform: translateY(-50%);
            }

            .ventas-document-type-search input {
                width: 100%;
                height: 37px;
                padding: 0 12px 0 34px;
                border: 1px solid #dfe4ea;
                border-radius: 8px;
                color: #344054;
                background: #ffffff;
                outline: none;
                font-size: .75rem;
            }

            .ventas-document-type-search input:focus {
                border-color: #9aa7f0;
                box-shadow: 0 0 0 3px rgba(103, 119, 239, .08);
            }

            .ventas-document-type-list {
                max-height: 340px;
                overflow-y: auto;
                overscroll-behavior: contain;
                padding: 6px;
            }

            .ventas-document-type-group {
                padding: 9px 9px 5px;
            }

            .ventas-document-type-group span {
                color: #98a2b3;
                font-size: .61rem;
                font-weight: 750;
                letter-spacing: .055em;
                text-transform: uppercase;
            }

            .ventas-document-type-option {
                width: 100%;
                min-height: 51px;
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 7px 9px;
                border: 0;
                border-radius: 9px;
                color: #475467;
                background: transparent;
                cursor: pointer;
                text-align: left;
                transition:
                    color .14s ease,
                    background-color .14s ease;
            }

            .ventas-document-type-option:hover,
            .ventas-document-type-option:focus {
                color: #1d2939;
                background: #f5f7fa;
                outline: none;
            }

            .ventas-document-type-option.active {
                color: #344054;
                background: #f0f2ff;
            }

            .ventas-document-option-icon {
                width: 33px;
                height: 33px;
                flex: 0 0 33px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 9px;
                color: #667085;
                background: #f2f4f7;
                font-size: .74rem;
            }

            .ventas-document-type-option.active
            .ventas-document-option-icon {
                color: #5969d9;
                background: #e5e8ff;
            }

            .ventas-document-option-copy {
                min-width: 0;
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                gap: 3px;
            }

            .ventas-document-option-copy strong {
                overflow: hidden;
                color: inherit;
                font-size: .76rem;
                font-weight: 700;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .ventas-document-option-copy small {
                overflow: hidden;
                color: #98a2b3;
                font-size: .65rem;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .ventas-document-option-code {
                min-width: 28px;
                height: 23px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0 6px;
                border: 1px solid #e4e7ec;
                border-radius: 999px;
                color: #667085;
                background: #ffffff;
                font-size: .61rem;
                font-weight: 750;
            }

            .ventas-document-type-option.active
            .ventas-document-option-code {
                border-color: #d7dbfb;
                color: #4f5fd1;
                background: #ffffff;
            }

            .ventas-document-type-empty {
                padding: 24px 15px;
                color: #98a2b3;
                font-size: .72rem;
                text-align: center;
            }

            @media (max-width: 767.98px) {
                .ventas-document-type-menu {
                    position: fixed;
                    top: auto;
                    right: 12px;
                    bottom: 12px;
                    left: 12px;
                    width: auto;
                    max-width: none;
                    border-radius: 16px;
                }

                .ventas-document-type-list {
                    max-height: 55vh;
                }
            }


            /* =========================================================
               AJUSTE FINAL DE LA BARRA DE FILTROS
            ========================================================== */
            .ventas-control-panel {
                overflow: visible;
            }

            .ventas-filter-grid {
                border-radius: 15px 15px 0 0;
            }

            .ventas-filter-field .form-control {
                padding-left: 12px !important;
                padding-right: 32px;
                line-height: 1.2;
            }

            .ventas-filter-field input.form-control {
                padding-right: 12px;
            }

            .ventas-input-icon {
                position: static;
            }

            .ventas-input-icon > i {
                display: none !important;
            }

            .ventas-input-icon .form-control {
                padding-left: 12px !important;
            }

            .ventas-document-type-trigger {
                padding-left: 7px;
            }

            .ventas-export-copy span {
                color: #344054;
                font-size: .78rem;
                font-weight: 750;
            }

            .ventas-export-copy small {
                color: #98a2b3;
            }

            @media (max-width: 1199.98px) {
                .ventas-filter-grid {
                    grid-template-columns:
                        repeat(3, minmax(0, 1fr));
                }
            }

            @media (max-width: 767.98px) {
                .ventas-filter-grid {
                    grid-template-columns: 1fr;
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
                                <div class="card-header ventas-page-header">
                                    <div class="ventas-page-heading">
                                        <span class="ventas-page-kicker">
                                            Gestión comercial
                                        </span>

                                        <h4 class="ventas-page-title">
                                            Ventas
                                        </h4>

                                        <p class="ventas-page-subtitle">
                                            Consulta ventas, notas de crédito y documentos SUNAT
                                            desde un solo lugar.
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn btn-success ventas-btn-agregar"
                                        id="btnagregar">
                                        <i class="fas fa-plus-circle"></i>
                                        <span>Nueva venta</span>
                                    </button>
                                </div>
                                <!--TABLA DE LISTADO DE REGISTROS-->
                                <div class="card-body">
                                                                        <div class="ventas-control-panel">

                                        <div class="ventas-filter-grid">

                                            <div class="ventas-filter-field ventas-filter-documento">
                                                <label>
                                                    Tipo de documento
                                                </label>

                                                <div
                                                    class="ventas-document-type-select"
                                                    id="ventasDocumentTypeSelect">

                                                    <button
                                                        type="button"
                                                        class="ventas-document-type-trigger"
                                                        id="filtroTipoDocumentoBtn"
                                                        aria-haspopup="listbox"
                                                        aria-expanded="false">

                                                        <span class="ventas-document-type-trigger-icon">
                                                            <i
                                                                id="filtroTipoDocumentoIcono"
                                                                class="fas fa-layer-group"></i>
                                                        </span>

                                                        <span class="ventas-document-type-trigger-copy">
                                                            <small>Seleccionado</small>

                                                            <strong id="filtroTipoDocumentoTexto">
                                                                Todos los documentos de venta
                                                            </strong>
                                                        </span>

                                                        <i class="fas fa-chevron-down ventas-document-type-chevron"></i>
                                                    </button>

                                                    <div
                                                        class="ventas-document-type-menu"
                                                        id="filtroTipoDocumentoMenu"
                                                        role="listbox"
                                                        aria-label="Tipos de documento">

                                                        <div class="ventas-document-type-search">
                                                            <i class="fas fa-search"></i>

                                                            <input
                                                                type="search"
                                                                id="buscarTipoDocumento"
                                                                placeholder="Buscar tipo de documento"
                                                                autocomplete="off">
                                                        </div>

                                                        <div class="ventas-document-type-list">

                                                            <div class="ventas-document-type-group">
                                                                <span>Comprobantes de venta</span>
                                                            </div>

                                                            <button
                                                                type="button"
                                                                class="ventas-document-type-option active"
                                                                data-tipo-comprobante="TODOS"
                                                                data-destino="ventas"
                                                                data-etiqueta="Todos los documentos de venta"
                                                                data-icono="fa-layer-group">

                                                                <span class="ventas-document-option-icon">
                                                                    <i class="fas fa-layer-group"></i>
                                                                </span>

                                                                <span class="ventas-document-option-copy">
                                                                    <strong>Todos los documentos</strong>
                                                                    <small>Facturas, boletas y documentos internos</small>
                                                                </span>
                                                            </button>

                                                            <button
                                                                type="button"
                                                                class="ventas-document-type-option"
                                                                data-tipo-comprobante="FACTURA"
                                                                data-destino="ventas"
                                                                data-etiqueta="Factura electrónica"
                                                                data-icono="fa-file-invoice">

                                                                <span class="ventas-document-option-icon">
                                                                    <i class="fas fa-file-invoice"></i>
                                                                </span>

                                                                <span class="ventas-document-option-copy">
                                                                    <strong>Factura electrónica</strong>
                                                                    <small>Documento SUNAT código 01</small>
                                                                </span>

                                                                <span class="ventas-document-option-code">01</span>
                                                            </button>

                                                            <button
                                                                type="button"
                                                                class="ventas-document-type-option"
                                                                data-tipo-comprobante="BOLETA"
                                                                data-destino="ventas"
                                                                data-etiqueta="Boleta de venta electrónica"
                                                                data-icono="fa-receipt">

                                                                <span class="ventas-document-option-icon">
                                                                    <i class="fas fa-receipt"></i>
                                                                </span>

                                                                <span class="ventas-document-option-copy">
                                                                    <strong>Boleta de venta electrónica</strong>
                                                                    <small>Documento SUNAT código 03</small>
                                                                </span>

                                                                <span class="ventas-document-option-code">03</span>
                                                            </button>

                                                            <button
                                                                type="button"
                                                                class="ventas-document-type-option"
                                                                data-tipo-comprobante="NOTA_VENTA"
                                                                data-destino="ventas"
                                                                data-etiqueta="Nota de venta"
                                                                data-icono="fa-file-alt">

                                                                <span class="ventas-document-option-icon">
                                                                    <i class="far fa-file-alt"></i>
                                                                </span>

                                                                <span class="ventas-document-option-copy">
                                                                    <strong>Nota de venta</strong>
                                                                    <small>Documento comercial interno</small>
                                                                </span>

                                                                <span class="ventas-document-option-code">NV</span>
                                                            </button>

                                                            <button
                                                                type="button"
                                                                class="ventas-document-type-option"
                                                                data-tipo-comprobante="RECIBO"
                                                                data-destino="ventas"
                                                                data-etiqueta="Recibo"
                                                                data-icono="fa-file-invoice-dollar">

                                                                <span class="ventas-document-option-icon">
                                                                    <i class="fas fa-file-invoice-dollar"></i>
                                                                </span>

                                                                <span class="ventas-document-option-copy">
                                                                    <strong>Recibo</strong>
                                                                    <small>Constancia interna de la operación</small>
                                                                </span>

                                                                <span class="ventas-document-option-code">R</span>
                                                            </button>

                                                            <button
                                                                type="button"
                                                                class="ventas-document-type-option"
                                                                data-tipo-comprobante="COTIZACION"
                                                                data-destino="ventas"
                                                                data-etiqueta="Cotización"
                                                                data-icono="fa-file-signature">

                                                                <span class="ventas-document-option-icon">
                                                                    <i class="fas fa-file-signature"></i>
                                                                </span>

                                                                <span class="ventas-document-option-copy">
                                                                    <strong>Cotización</strong>
                                                                    <small>Propuesta comercial registrada</small>
                                                                </span>

                                                                <span class="ventas-document-option-code">C</span>
                                                            </button>

                                                            <div class="ventas-document-type-group">
                                                                <span>Documentos de ajuste</span>
                                                            </div>

                                                            <button
                                                                type="button"
                                                                class="ventas-document-type-option"
                                                                data-tipo-comprobante="NOTA_CREDITO"
                                                                data-destino="notas"
                                                                data-etiqueta="Nota de crédito electrónica"
                                                                data-icono="fa-file-invoice-dollar">

                                                                <span class="ventas-document-option-icon">
                                                                    <i class="fas fa-file-invoice-dollar"></i>
                                                                </span>

                                                                <span class="ventas-document-option-copy">
                                                                    <strong>Nota de crédito electrónica</strong>
                                                                    <small>Documento SUNAT código 07</small>
                                                                </span>

                                                                <span class="ventas-document-option-code">07</span>
                                                            </button>

                                                            <div
                                                                class="ventas-document-type-empty"
                                                                id="sinTiposDocumento"
                                                                style="display:none;">
                                                                No se encontraron tipos de documento.
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="ventas-filter-field">
                                                <label for="filtroPeriodo">
                                                    Periodo
                                                </label>

                                                <select
                                                    class="form-control"
                                                    id="filtroPeriodo">
                                                        <option value="todos">
                                                            Todo el historial
                                                        </option>
                                                        <option value="hoy">
                                                            Hoy
                                                        </option>
                                                        <option value="7dias">
                                                            Últimos 7 días
                                                        </option>
                                                        <option value="mes">
                                                            Este mes
                                                        </option>
                                                        <option value="personalizado">
                                                            Personalizado
                                                        </option>
                                                    </select>
                                            </div>

                                            <div class="ventas-filter-field">
                                                <label for="filtroFechaDesde">
                                                    Desde
                                                </label>

                                                <input
                                                    type="date"
                                                    class="form-control"
                                                    id="filtroFechaDesde">
                                            </div>

                                            <div class="ventas-filter-field">
                                                <label for="filtroFechaHasta">
                                                    Hasta
                                                </label>

                                                <input
                                                    type="date"
                                                    class="form-control"
                                                    id="filtroFechaHasta">
                                            </div>

                                            <div class="ventas-filter-field">
                                                <label for="filtroEstadoSunat">
                                                    Estado SUNAT
                                                </label>

                                                <select
                                                    class="form-control"
                                                    id="filtroEstadoSunat">
                                                        <option value="">
                                                            Todos los estados
                                                        </option>
                                                        <option value="ACEPTADO">
                                                            Aceptado
                                                        </option>
                                                        <option value="PENDIENTE">
                                                            Pendiente
                                                        </option>
                                                        <option value="NO_ENVIADO">
                                                            No enviado
                                                        </option>
                                                        <option value="RECHAZADO">
                                                            Rechazado
                                                        </option>
                                                        <option value="ERROR">
                                                            Error
                                                        </option>
                                                        <option value="ANULADO">
                                                            Anulado
                                                        </option>
                                                    </select>
                                            </div>

                                            <div class="ventas-filter-field ventas-filter-search">
                                                <label for="filtroBusquedaDocumentos">
                                                    Buscar
                                                </label>

                                                <input
                                                    type="search"
                                                    class="form-control"
                                                    id="filtroBusquedaDocumentos"
                                                    placeholder="Cliente, comprobante, pago o usuario">
                                            </div>

                                            <div class="ventas-filter-actions">
                                                <button
                                                    type="button"
                                                    class="btn ventas-btn-limpiar"
                                                    id="btnLimpiarFiltros">
                                                    <i class="fas fa-undo-alt"></i>
                                                    Limpiar
                                                </button>
                                            </div>

                                        </div>

                                        <div class="ventas-export-row">

                                            <div class="ventas-export-copy">
                                                <span id="resumenFiltroActual">
                                                    Cargando resultados...
                                                </span>

                                                <small>
                                                    Exporta exactamente los registros visibles.
                                                </small>
                                            </div>

                                            <div
                                                id="exportadoresVentas"
                                                class="ventas-exportadores">
                                            </div>

                                            <div
                                                id="exportadoresNotas"
                                                class="ventas-exportadores d-none">
                                            </div>
                                        </div>

                                    </div>

                                    <div
                                        class="ventas-documento-panel"
                                        id="ventas-panel">

                                        <div
                                            class="table-responsive"
                                            id="listadoregistros">

                                            <table
                                                id="tbllistado"
                                                class="table table-striped table-hover text-nowrap"
                                                style="width:100%;">

                                                <thead>
                                                    <th>Fecha</th>
                                                    <th>Comprobante</th>
                                                    <th>Cliente</th>
                                                    <th>Usuario</th>
                                                    <th>Método de pago</th>
                                                    <th class="text-right">
                                                        Total venta
                                                    </th>
                                                    <th class="text-center">
                                                        Estado SUNAT
                                                    </th>
                                                    <th class="text-center">
                                                        Ver detalles
                                                    </th>
                                                    <th class="text-right">
                                                        Acciones
                                                    </th>
                                                </thead>

                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div
                                        class="ventas-documento-panel d-none"
                                        id="notas-credito-panel">

                                        <div class="table-responsive">

                                            <table
                                                id="tblNotasCredito"
                                                class="table table-striped table-hover text-nowrap"
                                                style="width:100%;">

                                                <thead>
                                                    <th>Fecha</th>
                                                    <th>Cliente</th>
                                                    <th>Usuario</th>
                                                    <th>Nota de crédito</th>
                                                    <th>Documento original</th>
                                                    <th>Motivo</th>
                                                    <th class="text-right">
                                                        Total acreditado
                                                    </th>
                                                    <th class="text-center">
                                                        Estado SUNAT
                                                    </th>
                                                    <th class="text-right">
                                                        Acciones
                                                    </th>
                                                </thead>

                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <!--TABLAS DE DOCUMENTOS FIN-->
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
                                <div class="col-lg-5 col-md-6 col-12 venta-campo">
                                    <label for="cliente">Cliente</label>
                                    <input
                                        class="form-control"
                                        type="text"
                                        name="cliente"
                                        id="cliente"
                                        maxlength="180"
                                        readonly>
                                </div>

                                <div class="col-lg-3 col-md-6 col-12 venta-campo">
                                    <label for="documento_clientem">DNI / RUC</label>
                                    <input
                                        class="form-control"
                                        type="text"
                                        id="documento_clientem"
                                        readonly>
                                </div>

                                <div class="col-lg-4 col-md-6 col-12 venta-campo">
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