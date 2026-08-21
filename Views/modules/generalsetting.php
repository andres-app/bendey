<?php
// Views/modules/generalsetting.php

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

if ((int)($_SESSION['settings'] ?? 0) !== 1) {
    require 'access.php';
    require 'footer.php';
    ob_end_flush();
    exit;
}
?>


<style>
    .empresa-logo-panel {
        position: relative;
        overflow: hidden;
        border: 1px solid #e8edf3;
        border-radius: 20px;
        background:
            radial-gradient(circle at top right, rgba(0, 164, 106, .10), transparent 34%),
            linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        padding: 22px;
        box-shadow: 0 10px 28px rgba(31, 41, 55, .06);
    }

    .empresa-logo-panel::before {
        content: "";
        position: absolute;
        width: 170px;
        height: 170px;
        border-radius: 50%;
        background: rgba(0, 164, 106, .055);
        right: -72px;
        bottom: -92px;
        pointer-events: none;
    }

    .empresa-logo-preview-wrap {
        width: 170px;
        height: 132px;
        flex: 0 0 170px;
        border: 1px solid #dfe6ee;
        border-radius: 18px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        box-shadow: 0 8px 22px rgba(31, 41, 55, .08);
    }

    .empresa-logo-preview {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 12px;
        display: none;
    }

    .empresa-logo-vacio {
        text-align: center;
        color: #98a2b3;
    }

    .empresa-logo-vacio i {
        display: block;
        font-size: 2.4rem;
        margin-bottom: 8px;
        color: #c4ccd6;
    }

    .empresa-logo-dropzone {
        position: relative;
        border: 1.5px dashed #cbd5e1;
        border-radius: 16px;
        background: rgba(255, 255, 255, .78);
        padding: 18px;
        cursor: pointer;
        transition:
            border-color .18s ease,
            background-color .18s ease,
            transform .18s ease,
            box-shadow .18s ease;
    }

    .empresa-logo-dropzone:hover,
    .empresa-logo-dropzone.is-dragover {
        border-color: #00a46a;
        background: #ecfdf6;
        box-shadow: 0 0 0 4px rgba(0, 164, 106, .08);
        transform: translateY(-1px);
    }

    .empresa-logo-dropzone-icon {
        width: 42px;
        height: 42px;
        border-radius: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #d7f7e9;
        color: #00a46a;
        font-size: 1.15rem;
        flex: 0 0 42px;
    }

    .empresa-logo-status {
        min-height: 22px;
        font-size: .82rem;
    }

    .empresa-logo-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .empresa-logo-manage {
        display: flex;
        align-items: center;
        gap: 9px;
        margin-top: 15px;
    }

    .empresa-logo-manage .btn {
        min-height: 38px;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border-radius: 9px;
        font-weight: 500;
    }

    .empresa-logo-manage-chevron {
        margin-left: 3px;
        font-size: .72rem;
        transition: transform .18s ease;
    }

    #btnGestionarLogo[aria-expanded="true"] .empresa-logo-manage-chevron {
        transform: rotate(180deg);
    }

    .empresa-logo-editor {
        display: none;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #edf0f4;
    }

    .empresa-logo-help {
        border-left: 3px solid #00a46a;
        background: rgba(0, 164, 106, .055);
        border-radius: 0 12px 12px 0;
        padding: 10px 12px;
        color: #667085;
        font-size: .8rem;
        line-height: 1.45;
    }

    @media (max-width: 767.98px) {
        .empresa-logo-panel {
            padding: 17px;
        }

        .empresa-logo-layout {
            flex-direction: column;
            align-items: stretch !important;
        }

        .empresa-logo-preview-wrap {
            width: 100%;
            height: 150px;
            flex-basis: auto;
        }
    }

    .tributario-panel {
        position: relative;
        overflow: visible;
        border: 1px solid #e4e9ef;
        border-radius: 18px;
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfd 100%);
        box-shadow: 0 10px 28px rgba(15, 23, 42, .055);
    }

    .tributario-panel-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px 16px;
        border-bottom: 1px solid #edf0f3;
    }

    .tributario-panel-header h5 {
        margin: 0 0 4px;
        color: #263244;
        font-weight: 750;
    }

    .tributario-panel-header p {
        margin: 0;
        color: #7b8492;
        font-size: .8rem;
        line-height: 1.45;
    }

    .tributario-panel-badge {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 7px 11px;
        border: 1px solid #adebd2;
        border-radius: 999px;
        color: #00754d;
        background: #ecfdf6;
        font-size: .7rem;
        font-weight: 750;
        white-space: nowrap;
    }

    .tributario-panel-body {
        padding: 20px 22px 8px;
    }

    .tributario-field label {
        color: #475467;
        font-size: .76rem;
        font-weight: 700;
    }

    .tributario-field .form-control {
        min-height: 42px;
        border-color: #dfe4ea;
        border-radius: 9px;
        box-shadow: none;
    }

    .tributario-field .form-control:focus {
        border-color: #72d9b3;
        box-shadow: 0 0 0 3px rgba(0, 164, 106, .09);
    }

    .tributario-help {
        min-height: 34px;
        margin-top: 6px;
        color: #8b95a5;
        font-size: .7rem;
        line-height: 1.35;
    }

    .tributario-switch-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 13px 15px;
        border: 1px solid #e8ebef;
        border-radius: 11px;
        background: #ffffff;
    }

    .tributario-switch-row strong {
        display: block;
        margin-bottom: 3px;
        color: #344054;
        font-size: .77rem;
    }

    .tributario-switch-row small {
        display: block;
        color: #8b95a5;
        font-size: .68rem;
        line-height: 1.35;
    }

    .tributario-note {
        margin: 4px 22px 20px;
        padding: 12px 14px;
        border-left: 3px solid #00a46a;
        border-radius: 0 10px 10px 0;
        color: #667085;
        background: #f7f8ff;
        font-size: .72rem;
        line-height: 1.45;
    }

    @media (max-width: 767.98px) {
        .tributario-panel-header {
            flex-direction: column;
            padding: 17px;
        }
        .tributario-panel-body { padding: 17px 17px 6px; }
        .tributario-note { margin: 4px 17px 17px; }
    }


    /* ================================================================
       CONFIGURACIÓN · ACORDEÓN PRINCIPAL
       ================================================================ */
    .config-shell-card {
        border: 0;
        background: transparent;
        box-shadow: none;
    }

    .config-shell-card > .card-header {
        padding: 0 0 16px;
        border: 0;
        background: transparent;
    }

    .config-shell-card > .card-body {
        padding: 0;
    }

    .config-page-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 18px;
    }

    .config-page-heading h4 {
        margin: 0 0 4px;
        color: #253044;
        font-size: 1.18rem;
        font-weight: 700;
    }

    .config-page-heading p {
        margin: 0;
        color: #8a94a3;
        font-size: .78rem;
    }

    .config-accordion {
        display: flex;
        flex-direction: column;
        gap: 11px;
    }

    .config-accordion-item {
        overflow: hidden;
        border: 1px solid #e7ebf0;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 5px 18px rgba(15, 23, 42, .035);
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    .config-accordion-item.is-open {
        border-color: rgba(0, 164, 106, .34);
        box-shadow: 0 12px 28px rgba(0, 164, 106, .08);
    }

    .config-accordion-trigger {
        width: 100%;
        border: 0;
        outline: 0 !important;
        background: #fff;
        display: flex;
        align-items: center;
        gap: 13px;
        padding: 15px 17px;
        text-align: left;
        cursor: pointer;
        transition: background-color .16s ease;
    }

    .config-accordion-trigger:hover {
        background: #fbfcff;
    }

    .config-accordion-item.is-open > .config-accordion-trigger {
        background: linear-gradient(90deg, rgba(0,164,106,.055), rgba(255,255,255,0));
    }

    .config-accordion-index {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: #ecfdf6;
        color: #00754d;
        font-size: .76rem;
        font-weight: 700;
        letter-spacing: .02em;
    }

    .config-accordion-heading {
        min-width: 0;
        flex: 1 1 auto;
    }

    .config-accordion-title {
        display: block;
        margin: 0;
        color: #303a4d;
        font-size: .91rem;
        font-weight: 650;
        line-height: 1.25;
    }

    .config-accordion-subtitle {
        display: block;
        margin-top: 3px;
        color: #98a2b3;
        font-size: .72rem;
        line-height: 1.3;
    }

    .config-accordion-meta {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
    }

    .config-accordion-state {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 27px;
        padding: 5px 9px;
        border: 1px solid #e1e5eb;
        border-radius: 999px;
        background: #fafbfc;
        color: #667085;
        font-size: .67rem;
        font-weight: 650;
        white-space: nowrap;
    }

    .config-accordion-chevron {
        width: 28px;
        height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #9aa4b2;
        transition: transform .2s ease, color .2s ease;
    }

    .config-accordion-item.is-open > .config-accordion-trigger .config-accordion-chevron {
        transform: rotate(180deg);
        color: #00a46a;
    }

    .config-accordion-content {
        display: none;
        border-top: 1px solid #edf0f4;
        background: #fff;
    }


    .config-accordion-body {
        padding: 20px 20px 8px;
    }

    .config-subaccordion {
        display: flex;
        flex-direction: column;
        gap: 9px;
    }

    .config-subaccordion .config-accordion-item {
        border-radius: 13px;
        box-shadow: none;
        background: #fbfcfe;
    }

    .config-subaccordion .config-accordion-trigger {
        padding: 13px 14px;
        background: #fbfcfe;
    }

    .config-subaccordion .config-accordion-item.is-open > .config-accordion-trigger {
        background: #f7f8ff;
    }

    .config-subaccordion .config-accordion-index {
        width: 34px;
        height: 34px;
        flex-basis: 34px;
        border-radius: 10px;
        font-size: .7rem;
    }

    .config-subaccordion .config-accordion-body {
        padding: 18px 16px 4px;
    }

    .config-savebar {
        position: sticky;
        bottom: 12px;
        z-index: 40;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-top: 12px;
        padding: 10px 12px 10px 15px;
        border: 1px solid rgba(222, 226, 232, .95);
        border-radius: 14px;
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 12px 30px rgba(15, 23, 42, .10);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .config-savebar-text strong {
        display: block;
        color: #344054;
        font-size: .76rem;
        font-weight: 650;
    }

    .config-savebar-text span {
        display: block;
        margin-top: 1px;
        color: #98a2b3;
        font-size: .67rem;
    }

    .config-savebar .btn {
        min-width: 190px;
        border-radius: 9px;
        font-weight: 500;
    }

    .config-accordion .empresa-logo-panel {
        border-radius: 14px;
        box-shadow: none;
        padding: 18px;
    }

    .config-accordion .tributario-note {
        margin: 2px 0 12px;
    }

    .config-accordion .alert {
        border-radius: 11px;
        font-size: .75rem;
    }

    .config-caja-resumen {
        margin-bottom: 16px;
    }

    @media (max-width: 767.98px) {
        .config-page-heading {
            align-items: flex-start;
            flex-direction: column;
            gap: 4px;
        }

        .config-accordion-trigger {
            align-items: flex-start;
            padding: 14px;
        }

        .config-accordion-subtitle {
            display: none;
        }

        .config-accordion-meta {
            gap: 4px;
        }

        .config-accordion-state {
            display: none;
        }

        .config-accordion-body {
            padding: 16px 14px 5px;
        }

        .config-savebar {
            bottom: 8px;
            align-items: stretch;
            flex-direction: column;
            gap: 8px;
        }

        .config-savebar-text {
            display: none;
        }

        .config-savebar .btn {
            width: 100%;
            min-width: 0;
        }
    }


    /* ================================================================
       CONFIGURACIÓN · CAJAS FÍSICAS
       ================================================================ */
    .caja-gestion-panel {
        margin-top: 8px;
        padding: 18px;
        border: 1px solid #e6ebf0;
        border-radius: 16px;
        background: #fbfcfd;
    }

    .caja-gestion-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 13px;
    }

    .caja-gestion-header h6 {
        margin: 0 0 3px;
        color: #344054;
        font-size: .84rem;
        font-weight: 700;
    }

    .caja-gestion-header p {
        margin: 0;
        color: #98a2b3;
        font-size: .7rem;
    }

    .caja-gestion-list {
        display: grid;
        gap: 10px;
    }

    .caja-gestion-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 14px;
        border: 1px solid #e5e9ef;
        border-radius: 13px;
        background: #fff;
    }

    .caja-gestion-card-main {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        min-width: 0;
    }

    .caja-gestion-icon {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 11px;
        background: #ecfdf6;
        color: #00875a;
    }

    .caja-gestion-copy { min-width: 0; }

    .caja-gestion-title-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 7px;
    }

    .caja-gestion-title-row strong {
        color: #344054;
        font-size: .8rem;
    }

    .caja-gestion-code {
        color: #667085;
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .03em;
    }

    .caja-gestion-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-top: 6px;
    }

    .caja-gestion-badge {
        display: inline-flex;
        align-items: center;
        min-height: 22px;
        padding: 3px 7px;
        border-radius: 999px;
        font-size: .62rem;
        font-weight: 700;
    }

    .caja-gestion-badge.is-active { background:#ecfdf6; color:#00754d; }
    .caja-gestion-badge.is-inactive { background:#f2f4f7; color:#667085; }
    .caja-gestion-badge.is-open { background:#fff4e5; color:#b54708; }
    .caja-gestion-badge.is-closed { background:#f2f4f7; color:#667085; }
    .caja-gestion-badge.is-primary { background:#eef4ff; color:#3538cd; }

    .caja-gestion-copy p {
        margin: 7px 0 0;
        color: #7c8798;
        font-size: .68rem;
    }

    .caja-gestion-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 7px;
        color: #667085;
        font-size: .65rem;
    }

    .caja-gestion-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        justify-content: flex-end;
    }

    .caja-gestion-empty {
        display: flex;
        min-height: 130px;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 5px;
        color: #98a2b3;
        text-align: center;
    }

    .caja-gestion-empty i { font-size: 1.65rem; color:#cbd5e1; }
    .caja-gestion-empty strong { color:#667085; font-size:.78rem; }
    .caja-gestion-empty span { font-size:.68rem; }

    #modalCajaFisica .modal-content {
        border: 0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(15, 23, 42, .20);
    }

    #modalCajaFisica .modal-header {
        border-bottom: 1px solid #edf0f4;
        background: #fbfcfd;
    }

    @media (max-width: 767.98px) {
        .caja-gestion-header,
        .caja-gestion-card {
            align-items: stretch;
            flex-direction: column;
        }

        .caja-gestion-actions .btn {
            flex: 1 1 auto;
        }
    }

</style>

<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="row">
                <div class="col-12">
                    <div class="card config-shell-card">
                        <div class="card-header">
                            <div class="config-page-heading w-100">
                                <div>
                                    <h4>Configuración de la empresa</h4>
                                    <p>Abre solo la sección que necesitas editar.</p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive d-none" id="listadoregistros">
                                <table id="tbllistado"></table>
                            </div>

                            <div id="formularioregistros">
                                <div id="configAccordionPrincipal" class="config-accordion">

                                    <form
                                        name="formulario"
                                        id="formulario"
                                        method="POST"
                                        enctype="multipart/form-data"
                                        autocomplete="off"
                                        novalidate>

                                        <input type="hidden" name="id_negocio" id="id_negocio">
                                        <input type="hidden" name="ndocumento" id="ndocumento" value="RUC">
                                        <input type="hidden" name="eliminar_logo" id="eliminar_logo" value="0">

                                        <!-- 1. DATOS GENERALES -->
                                        <div
                                            class="config-accordion-item is-open"
                                            data-accordion-group="principal"
                                            data-config-section="general">
                                            <button
                                                type="button"
                                                class="config-accordion-trigger"
                                                data-config-accordion-trigger
                                                aria-expanded="true">
                                                <span class="config-accordion-index">01</span>
                                                <span class="config-accordion-heading">
                                                    <span class="config-accordion-title">Datos generales de la empresa</span>
                                                    <span class="config-accordion-subtitle">Logo, razón social, RUC y datos de contacto.</span>
                                                </span>
                                                <span class="config-accordion-meta">
                                                    <span class="config-accordion-state">Principal</span>
                                                    <span class="config-accordion-chevron"><i class="fas fa-chevron-down"></i></span>
                                                </span>
                                            </button>
                                            <div class="config-accordion-content" style="display:block;">
                                                <div class="config-accordion-body">
                                                    <div class="row">
                                        <div class="col-12 mb-4">
                                            <div class="empresa-logo-panel">

                                                <div class="d-flex empresa-logo-layout align-items-center" style="gap:22px;">

                                                    <div class="empresa-logo-preview-wrap">
                                                        <img
                                                            src=""
                                                            alt="Logo de la empresa"
                                                            id="logoEmpresaPreview"
                                                            class="empresa-logo-preview">

                                                        <div
                                                            id="logoEmpresaVacio"
                                                            class="empresa-logo-vacio">
                                                            <i class="far fa-image"></i>
                                                            <div class="font-weight-bold">
                                                                Sin logo
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="flex-grow-1 position-relative" style="z-index:1;">

                                                        <div class="d-flex flex-wrap align-items-start justify-content-between mb-3" style="gap:12px;">
                                                            <div>
                                                                <h5 class="mb-1">
                                                                    Logo de la empresa
                                                                </h5>

                                                                <p class="text-muted mb-0">
                                                                    Se utilizará automáticamente en facturas,
                                                                    boletas y notas de crédito.
                                                                </p>
                                                            </div>

                                                            <span
                                                                id="logoEmpresaBadge"
                                                                class="badge badge-light border px-3 py-2">
                                                                Sin cambios
                                                            </span>
                                                        </div>

                                                        <input
                                                            type="file"
                                                            name="logo"
                                                            id="logo"
                                                            class="d-none"
                                                            accept="image/png,image/jpeg,image/webp">

                                                        <div class="empresa-logo-manage">
                                                            <button
                                                                type="button"
                                                                class="btn btn-outline-primary btn-sm"
                                                                id="btnGestionarLogo"
                                                                aria-expanded="false"
                                                                aria-controls="logoEmpresaEditor">
                                                                <i class="far fa-image"></i>
                                                                <span id="textoBtnGestionarLogo">
                                                                    Administrar logo
                                                                </span>
                                                                <i class="fas fa-chevron-down empresa-logo-manage-chevron"></i>
                                                            </button>
                                                        </div>

                                                        <div
                                                            id="logoEmpresaEditor"
                                                            class="empresa-logo-editor"
                                                            aria-hidden="true">

                                                            <div
                                                                id="logoEmpresaDropzone"
                                                                class="empresa-logo-dropzone"
                                                                role="button"
                                                                tabindex="0"
                                                                aria-label="Seleccionar logo de la empresa">

                                                                <div class="d-flex align-items-center" style="gap:13px;">
                                                                    <span class="empresa-logo-dropzone-icon">
                                                                        <i class="fas fa-cloud-upload-alt"></i>
                                                                    </span>

                                                                    <div>
                                                                        <div class="font-weight-bold text-dark">
                                                                            Seleccionar otro logo
                                                                        </div>

                                                                        <div class="small text-muted">
                                                                            Arrastra una imagen aquí o haz clic para buscarla
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div
                                                                id="logoEmpresaEstado"
                                                                class="empresa-logo-status text-muted mt-2">
                                                                PNG o JPG. También acepta WEBP y lo convierte a PNG. Máximo 2 MB.
                                                            </div>

                                                            <div class="empresa-logo-actions mt-3">
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-outline-primary btn-sm"
                                                                    id="btnCambiarLogo">
                                                                    <i class="fas fa-pen mr-1"></i>
                                                                    Cambiar logo
                                                                </button>

                                                                <button
                                                                    type="button"
                                                                    class="btn btn-outline-danger btn-sm"
                                                                    id="btnQuitarLogo"
                                                                    disabled>
                                                                    <i class="far fa-trash-alt mr-1"></i>
                                                                    Quitar logo
                                                                </button>
                                                            </div>

                                                            <div class="empresa-logo-help mt-3">
                                                                Para una mejor impresión usa un archivo PNG
                                                                con fondo transparente, formato horizontal o cuadrado
                                                                y buena resolución.
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label for="nombre">
                                                Nombre de la empresa (*)
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="nombre"
                                                id="nombre"
                                                maxlength="80"
                                                required>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label>
                                                Tipo de documento
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                value="RUC"
                                                disabled>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label for="documento">
                                                Número de RUC (*)
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="documento"
                                                id="documento"
                                                maxlength="11"
                                                minlength="11"
                                                inputmode="numeric"
                                                pattern="[0-9]{11}"
                                                required>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label for="direccion">
                                                Dirección (*)
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="direccion"
                                                id="direccion"
                                                maxlength="100"
                                                required>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label for="ciudad">
                                                Ciudad
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="ciudad"
                                                id="ciudad"
                                                maxlength="50">
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label for="pais">
                                                País
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="pais"
                                                id="pais"
                                                maxlength="50">
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label for="telefono">
                                                Teléfono (*)
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="telefono"
                                                id="telefono"
                                                maxlength="20"
                                                required>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label for="email">
                                                Correo electrónico
                                            </label>

                                            <input
                                                type="email"
                                                class="form-control"
                                                name="email"
                                                id="email"
                                                maxlength="100">
                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 2. REGISTRO DE APIS -->
                                        <div
                                            class="config-accordion-item"
                                            data-accordion-group="principal"
                                            data-config-section="apis">
                                            <button
                                                type="button"
                                                class="config-accordion-trigger"
                                                data-config-accordion-trigger
                                                aria-expanded="false">
                                                <span class="config-accordion-index">02</span>
                                                <span class="config-accordion-heading">
                                                    <span class="config-accordion-title">Registro de APIs</span>
                                                    <span class="config-accordion-subtitle">Credenciales de consulta y facturación electrónica.</span>
                                                </span>
                                                <span class="config-accordion-meta">
                                                    <span class="config-accordion-state">2 servicios</span>
                                                    <span class="config-accordion-chevron"><i class="fas fa-chevron-down"></i></span>
                                                </span>
                                            </button>
                                            <div class="config-accordion-content">
                                                <div class="config-accordion-body">
                                                    <div id="configAccordionApis" class="config-subaccordion">

                                                        <div
                                                            class="config-accordion-item is-open"
                                                            data-accordion-group="apis"
                                                            data-config-section="dni-ruc">
                                                            <button
                                                                type="button"
                                                                class="config-accordion-trigger"
                                                                data-config-accordion-trigger
                                                                aria-expanded="true">
                                                                <span class="config-accordion-index">2.1</span>
                                                                <span class="config-accordion-heading">
                                                                    <span class="config-accordion-title">Consulta de DNI y RUC</span>
                                                                    <span class="config-accordion-subtitle">Token para búsqueda de personas y empresas.</span>
                                                                </span>
                                                                <span class="config-accordion-chevron"><i class="fas fa-chevron-down"></i></span>
                                                            </button>
                                                            <div class="config-accordion-content" style="display:block;">
                                                                <div class="config-accordion-body">
                                                                    <div class="row">
                                        <div class="form-group col-lg-12">
                                            <label for="tokendniruc">
                                                Token de consulta DNI/RUC
                                            </label>

                                            <div class="input-group">

                                                <input
                                                    type="password"
                                                    class="form-control"
                                                    name="tokendniruc"
                                                    id="tokendniruc"
                                                    autocomplete="new-password">

                                                <div class="input-group-append">

                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary"
                                                        id="toggleTokenVisibility"
                                                        aria-label="Mostrar u ocultar token">
                                                        <i
                                                            class="fa fa-eye"
                                                            id="eyeIcon"></i>
                                                    </button>

                                                </div>
                                            </div>
                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div
                                                            class="config-accordion-item"
                                                            data-accordion-group="apis"
                                                            data-config-section="apisunat">
                                                            <button
                                                                type="button"
                                                                class="config-accordion-trigger"
                                                                data-config-accordion-trigger
                                                                aria-expanded="false">
                                                                <span class="config-accordion-index">2.2</span>
                                                                <span class="config-accordion-heading">
                                                                    <span class="config-accordion-title">Facturación electrónica APISUNAT</span>
                                                                    <span class="config-accordion-subtitle">Persona ID, token y ambiente de emisión.</span>
                                                                </span>
                                                                <span class="config-accordion-chevron"><i class="fas fa-chevron-down"></i></span>
                                                            </button>
                                                            <div class="config-accordion-content">
                                                                <div class="config-accordion-body">
                                                                    <div class="row">
                                        <div class="form-group col-lg-6">
                                            <label for="apisunat_persona_id">
                                                Persona ID APISUNAT
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="apisunat_persona_id"
                                                id="apisunat_persona_id"
                                                maxlength="100"
                                                autocomplete="off"
                                                placeholder="Persona ID proporcionado por APISUNAT">

                                            <small class="form-text text-muted">
                                                Identificador de la empresa registrado
                                                en APISUNAT.
                                            </small>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label for="apisunat_persona_token">
                                                Persona Token APISUNAT
                                            </label>

                                            <div class="input-group">

                                                <input
                                                    type="password"
                                                    class="form-control"
                                                    name="apisunat_persona_token"
                                                    id="apisunat_persona_token"
                                                    autocomplete="new-password"
                                                    placeholder="Dejar vacío para conservar el token actual">

                                                <div class="input-group-append">

                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary"
                                                        id="toggleApiSunatToken"
                                                        aria-label="Mostrar u ocultar Persona Token">
                                                        <i
                                                            class="fa fa-eye"
                                                            id="apiSunatEyeIcon"></i>
                                                    </button>

                                                </div>
                                            </div>

                                            <small
                                                id="apisunatTokenEstado"
                                                class="form-text text-muted">
                                                Verificando configuración...
                                            </small>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label for="apisunat_production">
                                                Ambiente APISUNAT
                                            </label>

                                            <select
                                                class="form-control"
                                                name="apisunat_production"
                                                id="apisunat_production">
                                                <option value="1">
                                                    Producción
                                                </option>

                                                <option value="0">
                                                    Pruebas
                                                </option>
                                            </select>
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label>
                                                Estado de credenciales
                                            </label>

                                            <div
                                                class="form-control d-flex align-items-center"
                                                style="height:auto;min-height:42px;">
                                                <span
                                                    id="apisunatEstadoGeneral"
                                                    class="badge badge-secondary">
                                                    Verificando
                                                </span>
                                            </div>
                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 3. CONFIGURACIÓN TRIBUTARIA -->
                                        <div
                                            class="config-accordion-item"
                                            data-accordion-group="principal"
                                            data-config-section="tributaria">
                                            <button
                                                type="button"
                                                class="config-accordion-trigger"
                                                data-config-accordion-trigger
                                                aria-expanded="false">
                                                <span class="config-accordion-index">03</span>
                                                <span class="config-accordion-heading">
                                                    <span class="config-accordion-title">Configuración tributaria</span>
                                                    <span class="config-accordion-subtitle">IGV, afectación, tipo de operación y unidad SUNAT.</span>
                                                </span>
                                                <span class="config-accordion-meta">
                                                    <span class="config-accordion-state" id="estadoConfiguracionTributaria">
                                                        <i class="fas fa-shield-alt"></i>
                                                        Configuración general
                                                    </span>
                                                    <span class="config-accordion-chevron"><i class="fas fa-chevron-down"></i></span>
                                                </span>
                                            </button>
                                            <div class="config-accordion-content">
                                                <div class="config-accordion-body">
                                                    <div class="row">
                                                        <div class="form-group col-lg-6 tributario-field">
                                                            <label for="tipo_operacion_sunat_predeterminado">
                                                                Tipo de operación SUNAT
                                                            </label>

                                                            <select
                                                                class="form-control"
                                                                name="tipo_operacion_sunat_predeterminado"
                                                                id="tipo_operacion_sunat_predeterminado"
                                                                required>
                                                                <option value="0101">0101 — Venta interna</option>
                                                                <option value="0112">0112 — Venta interna que sustenta gastos deducibles</option>
                                                                <option value="0113">0113 — Venta interna NRUS</option>
                                                                <option value="0200">0200 — Exportación de bienes</option>
                                                                <option value="0201">0201 — Exportación de servicios realizados íntegramente en el país</option>
                                                                <option value="0202">0202 — Servicios de hospedaje a no domiciliados</option>
                                                                <option value="0203">0203 — Exportación de servicios: transporte de navieras</option>
                                                                <option value="0204">0204 — Servicios a naves y aeronaves de bandera extranjera</option>
                                                                <option value="0205">0205 — Servicios que conforman un paquete turístico</option>
                                                                <option value="0206">0206 — Servicios complementarios al transporte de carga</option>
                                                                <option value="0207">0207 — Suministro de energía a sujetos domiciliados en ZED</option>
                                                                <option value="0208">0208 — Servicios realizados parcialmente en el extranjero</option>
                                                                <option value="0301">0301 — Operaciones con carta de porte aéreo nacional</option>
                                                                <option value="0302">0302 — Transporte ferroviario de pasajeros</option>
                                                                <option value="0401">0401 — Venta a no domiciliados que no califica como exportación</option>
                                                                <option value="1001">1001 — Operación sujeta a detracción</option>
                                                                <option value="1002">1002 — Detracción: recursos hidrobiológicos</option>
                                                                <option value="1003">1003 — Detracción: transporte de pasajeros</option>
                                                                <option value="1004">1004 — Detracción: transporte de carga</option>
                                                                <option value="2001">2001 — Operación sujeta a percepción</option>
                                                            </select>

                                                            <div class="tributario-help">
                                                                Para ventas comunes se recomienda 0101. Las demás opciones deben utilizarse solo cuando correspondan tributariamente.
                                                            </div>
                                                        </div>

                                                        <div class="form-group col-lg-6 tributario-field">
                                                            <label for="codigo_afectacion_igv_predeterminado">
                                                                Afectación al IGV predeterminada
                                                            </label>

                                                            <select
                                                                class="form-control"
                                                                name="codigo_afectacion_igv_predeterminado"
                                                                id="codigo_afectacion_igv_predeterminado"
                                                                required>
                                                                <option value="10">10 — Gravado: operación onerosa</option>
                                                                <option value="20">20 — Exonerado: operación onerosa</option>
                                                                <option value="30">30 — Inafecto: operación onerosa</option>
                                                                <option value="40">40 — Exportación</option>
                                                            </select>

                                                            <div class="tributario-help">
                                                                Los productos existentes conservarán su clasificación; este valor se aplica a productos nuevos.
                                                            </div>
                                                        </div>

                                                        <div class="form-group col-lg-3 col-md-6 tributario-field">
                                                            <label for="nombre_impuesto">Nombre del impuesto</label>
                                                            <input type="text" class="form-control" name="nombre_impuesto" id="nombre_impuesto" maxlength="10" value="IGV">
                                                        </div>

                                                        <div class="form-group col-lg-3 col-md-6 tributario-field">
                                                            <label for="monto_impuesto">Tasa general IGV (%)</label>
                                                            <input type="number" class="form-control" name="monto_impuesto" id="monto_impuesto" min="0" max="100" step="0.01">
                                                        </div>

                                                        <div class="form-group col-lg-3 col-md-6 tributario-field">
                                                            <label for="porcentaje_igv_predeterminado">Tasa aplicada por defecto (%)</label>
                                                            <input type="number" class="form-control" name="porcentaje_igv_predeterminado" id="porcentaje_igv_predeterminado" min="0" max="100" step="0.01" readonly>
                                                        </div>

                                                        <div class="form-group col-lg-3 col-md-6 tributario-field">
                                                            <label for="unidad_medida_sunat_predeterminada">Unidad SUNAT predeterminada</label>
                                                            <select class="form-control" name="unidad_medida_sunat_predeterminada" id="unidad_medida_sunat_predeterminada" required>
                                                                <option value="NIU">NIU — Unidad</option>
                                                                <option value="ZZ">ZZ — Servicio</option>
                                                                <option value="KGM">KGM — Kilogramo</option>
                                                                <option value="LTR">LTR — Litro</option>
                                                                <option value="MTR">MTR — Metro</option>
                                                                <option value="BX">BX — Caja</option>
                                                            </select>
                                                        </div>

                                                        <div class="form-group col-lg-3 col-md-6 tributario-field">
                                                            <label for="moneda">Moneda</label>
                                                            <input type="text" class="form-control" name="moneda" id="moneda" maxlength="10">
                                                        </div>

                                                        <div class="form-group col-lg-3 col-md-6 tributario-field">
                                                            <label for="simbolo">Símbolo</label>
                                                            <input type="text" class="form-control" name="simbolo" id="simbolo" maxlength="10">
                                                        </div>

                                                        <div class="col-lg-6 mb-3">
                                                            <div class="tributario-switch-row h-100">
                                                                <div>
                                                                    <strong>Precios incluyen impuesto</strong>
                                                                    <small>El precio de venta registrado se interpreta como importe final.</small>
                                                                </div>
                                                                <label class="custom-switch mb-0">
                                                                    <input type="checkbox" class="custom-switch-input" name="precios_incluyen_impuesto" id="precios_incluyen_impuesto" value="1" checked>
                                                                    <span class="custom-switch-indicator"></span>
                                                                </label>
                                                            </div>
                                                        </div>

                                                        <div class="col-lg-6 mb-3">
                                                            <div class="tributario-switch-row h-100">
                                                                <div>
                                                                    <strong>Permitir cambio en Nueva venta</strong>
                                                                    <small>Habilita ajustes tributarios avanzados durante una operación.</small>
                                                                </div>
                                                                <label class="custom-switch mb-0">
                                                                    <input type="checkbox" class="custom-switch-input" name="permitir_cambio_afectacion_venta" id="permitir_cambio_afectacion_venta" value="1">
                                                                    <span class="custom-switch-indicator"></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <div class="tributario-note">
                                                    <i class="fas fa-info-circle mr-2"></i>
                                                    Una empresa o sucursal ubicada en la Amazonía no queda exonerada automáticamente. La clasificación debe configurarse de acuerdo con la situación tributaria validada por la empresa.
                                                </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 4. VALORES PREDETERMINADOS -->
                                        <div
                                            class="config-accordion-item"
                                            data-accordion-group="principal"
                                            data-config-section="predeterminados">
                                            <button
                                                type="button"
                                                class="config-accordion-trigger"
                                                data-config-accordion-trigger
                                                aria-expanded="false">
                                                <span class="config-accordion-index">04</span>
                                                <span class="config-accordion-heading">
                                                    <span class="config-accordion-title">Valores predeterminados</span>
                                                    <span class="config-accordion-subtitle">Comprobante, pago, forma de pago y envío SUNAT.</span>
                                                </span>
                                                <span class="config-accordion-meta">
                                                    <span class="config-accordion-state">Nueva venta</span>
                                                    <span class="config-accordion-chevron"><i class="fas fa-chevron-down"></i></span>
                                                </span>
                                            </button>
                                            <div class="config-accordion-content">
                                                <div class="config-accordion-body">
                                                    <div class="row">
                                        <div class="form-group col-lg-3 col-md-6">
                                            <label for="venta_tipo_comprobante_predeterminado">
                                                Tipo de comprobante
                                            </label>

                                            <select
                                                class="form-control"
                                                name="venta_tipo_comprobante_predeterminado"
                                                id="venta_tipo_comprobante_predeterminado">
                                                <option value="">Cargando comprobantes...</option>
                                            </select>

                                            <small class="form-text text-muted">
                                                Ejemplo: Boleta Electrónica o Factura Electrónica.
                                            </small>
                                        </div>

                                        <div class="form-group col-lg-3 col-md-6">
                                            <label for="venta_tipo_pago_predeterminado">
                                                Tipo de pago
                                            </label>

                                            <select
                                                class="form-control"
                                                name="venta_tipo_pago_predeterminado"
                                                id="venta_tipo_pago_predeterminado">
                                                <option value="">Cargando tipos de pago...</option>
                                            </select>

                                            <small class="form-text text-muted">
                                                Ejemplo: Contado o Crédito.
                                            </small>
                                        </div>

                                        <div class="form-group col-lg-3 col-md-6">
                                            <label for="venta_idforma_pago_predeterminada">
                                                Forma de pago
                                            </label>

                                            <select
                                                class="form-control"
                                                name="venta_idforma_pago_predeterminada"
                                                id="venta_idforma_pago_predeterminada">
                                                <option value="">Cargando formas de pago...</option>
                                            </select>

                                            <small class="form-text text-muted">
                                                Ejemplo: Efectivo, Yape, Plin o Tarjeta.
                                            </small>
                                        </div>

                                        <div class="form-group col-lg-3 col-md-6">
                                            <label for="venta_modo_envio_predeterminado">
                                                Modo de envío SUNAT
                                            </label>

                                            <select
                                                class="form-control"
                                                name="venta_modo_envio_predeterminado"
                                                id="venta_modo_envio_predeterminado">
                                                <option value="">
                                                    Usar el valor de la pantalla
                                                </option>
                                                <option value="inmediato">
                                                    Enviar inmediatamente
                                                </option>
                                                <option value="manual">
                                                    Enviar manualmente después
                                                </option>
                                                <option value="resumen_diario">
                                                    Incluir boletas en Resumen Diario
                                                </option>
                                            </select>

                                            <small class="form-text text-muted">
                                                El Resumen Diario aplica únicamente a Boleta Electrónica.
                                            </small>
                                        </div>

                                        <div class="col-12 mb-3">
                                            <div class="alert alert-light border mb-0">
                                                <i class="fas fa-info-circle mr-2 text-primary"></i>
                                                Al duplicar una venta, los datos del comprobante original
                                                tendrán prioridad sobre estos valores predeterminados.
                                            </div>
                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </form>

                                    <!-- 5. CONFIGURACIÓN DE CAJA -->
                                    <div
                                        class="config-accordion-item"
                                        data-accordion-group="principal"
                                        data-config-section="caja">
                                        <button
                                            type="button"
                                            class="config-accordion-trigger"
                                            data-config-accordion-trigger
                                            aria-expanded="false">
                                            <span class="config-accordion-index">05</span>
                                            <span class="config-accordion-heading">
                                                <span class="config-accordion-title">Configuración de caja</span>
                                                <span class="config-accordion-subtitle">Modalidad, caja principal y control de aperturas.</span>
                                            </span>
                                            <span class="config-accordion-meta">
                                                <span id="estadoConfiguracionCaja" class="badge badge-secondary">Verificando</span>
                                                <span class="config-accordion-chevron"><i class="fas fa-chevron-down"></i></span>
                                            </span>
                                        </button>
                                        <div class="config-accordion-content">
                                            <div class="config-accordion-body">
                            <div
                                id="alertaConfiguracionCaja"
                                class="alert alert-light border">
                                <div class="d-flex align-items-start">

                                    <i
                                        class="fas fa-cash-register mr-3 mt-1 text-primary"
                                        style="font-size:1.5rem;"></i>

                                    <div>
                                        <strong id="configuracionCajaTitulo">
                                            Cargando configuración...
                                        </strong>

                                        <p
                                            id="configuracionCajaMensaje"
                                            class="mb-0 mt-1 text-muted">
                                            Espere un momento.
                                        </p>
                                    </div>

                                </div>
                            </div>

                            <div class="row mt-4">

                                <div class="form-group col-lg-6">
                                    <label>
                                        Sucursal principal
                                    </label>

                                    <div class="form-control bg-light" style="height:auto;">
                                        <strong id="cajaSucursalNombre">
                                            —
                                        </strong>

                                        <div class="small text-muted">
                                            Código:
                                            <span id="cajaSucursalCodigo">
                                                —
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>
                                        Caja principal
                                    </label>

                                    <div class="form-control bg-light" style="height:auto;">
                                        <strong id="cajaPrincipalNombre">
                                            —
                                        </strong>

                                        <div class="small text-muted">
                                            Código:
                                            <span id="cajaPrincipalCodigo">
                                                —
                                            </span>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <form
                                id="formConfiguracionCaja"
                                autocomplete="off">

                                <input
                                    type="hidden"
                                    id="idsucursalCaja"
                                    name="idsucursal">

                                <div class="row">

                                    <div class="col-12 mb-3">
                                        <h5 class="mb-1">
                                            Modalidad de trabajo
                                        </h5>

                                        <p class="text-muted mb-0">
                                            Elige cómo se administrará el efectivo y las aperturas
                                            de caja en esta sucursal.
                                        </p>
                                    </div>

                                    <div class="col-lg-6 mb-3">

                                        <label
                                            for="modoCajaUnica"
                                            class="border rounded p-3 d-block h-100"
                                            style="cursor:pointer;">
                                            <div class="custom-control custom-radio">

                                                <input
                                                    type="radio"
                                                    class="custom-control-input"
                                                    name="modo_caja"
                                                    id="modoCajaUnica"
                                                    value="CAJA_UNICA">

                                                <span class="custom-control-label">
                                                    <strong>
                                                        Caja única
                                                    </strong>
                                                </span>

                                            </div>

                                            <p class="text-muted small mt-3 mb-0">
                                                Todos los usuarios autorizados trabajan sobre una
                                                misma apertura y una sola caja física.
                                            </p>
                                        </label>

                                    </div>

                                    <div class="col-lg-6 mb-3">

                                        <label
                                            for="modoMulticaja"
                                            class="border rounded p-3 d-block h-100"
                                            style="cursor:pointer;">
                                            <div class="custom-control custom-radio">

                                                <input
                                                    type="radio"
                                                    class="custom-control-input"
                                                    name="modo_caja"
                                                    id="modoMulticaja"
                                                    value="MULTICAJA">

                                                <span class="custom-control-label">
                                                    <strong>
                                                        Multicaja
                                                    </strong>
                                                </span>

                                            </div>

                                            <p class="text-muted small mt-3 mb-0">
                                                Cada caja física tiene su propia apertura, cierre
                                                y control de efectivo.
                                            </p>
                                        </label>

                                    </div>

                                    <div class="form-group col-lg-8">

                                        <label for="idcajaUnica">
                                            Caja predeterminada
                                        </label>

                                        <select
                                            class="form-control"
                                            id="idcajaUnica"
                                            name="idcaja_unica">
                                            <option value="">
                                                Cargando cajas...
                                            </option>
                                        </select>

                                        <small class="form-text text-muted">
                                            Esta es la caja utilizada en Caja única y se conserva como referencia cuando trabajas con Multicaja.
                                        </small>

                                    </div>

                                    <div class="form-group col-lg-4">

                                        <label>
                                            Cajas físicas activas
                                        </label>

                                        <div
                                            class="form-control bg-light d-flex align-items-center"
                                            style="height:42px;">
                                            <strong id="totalCajasActivas">
                                                0
                                            </strong>
                                        </div>

                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="caja-gestion-panel">
                                            <div class="caja-gestion-header">
                                                <div>
                                                    <h6>Cajas físicas</h6>
                                                    <p>Crea y administra las cajas disponibles en esta sucursal. En Multicaja se requieren al menos dos activas.</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-primary btn-sm"
                                                    id="btnNuevaCajaFisica">
                                                    <i class="fas fa-plus mr-1"></i>
                                                    Nueva caja
                                                </button>
                                            </div>

                                            <div
                                                id="listaCajasFisicas"
                                                class="caja-gestion-list">
                                                <div class="caja-gestion-empty">
                                                    <i class="fas fa-spinner fa-spin"></i>
                                                    <strong>Cargando cajas...</strong>
                                                </div>
                                            </div>

                                            <div class="mt-3 small text-muted">
                                                <i class="fas fa-user-shield mr-1"></i>
                                                Después de crear una caja, asigna los usuarios autorizados desde <strong>Usuarios</strong>.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group col-12 text-right">

                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                            id="btnGuardarConfiguracionCaja">
                                            <i class="fa fa-save"></i>
                                            Guardar modalidad
                                        </button>

                                        <small
                                            id="estadoCambioModalidadCaja"
                                            class="d-block text-muted mt-2">
                                            Verificando si existen cajas abiertas...
                                        </small>

                                    </div>

                                </div>

                            </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="configEmpresaSavebar" class="config-savebar">
                                        <div class="config-savebar-text">
                                            <strong>Configuración de empresa</strong>
                                            <span>Guarda los cambios realizados en Datos generales, APIs, Configuración tributaria y Valores predeterminados.</span>
                                        </div>
                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                            id="btnGuardar"
                                            form="formulario">
                                            <i class="fa fa-save mr-1"></i>
                                            Guardar configuración
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div
    class="modal fade"
    id="modalCajaFisica"
    tabindex="-1"
    role="dialog"
    aria-labelledby="tituloModalCajaFisica"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="formCajaFisica" autocomplete="off">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="tituloModalCajaFisica">Nueva caja física</h5>
                        <small class="text-muted" id="codigoCajaGestionPreview">El código se generará automáticamente</small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="idcajaGestion" value="">

                    <div class="form-group">
                        <label for="nombreCajaGestion">Nombre de la caja</label>
                        <input
                            type="text"
                            class="form-control"
                            id="nombreCajaGestion"
                            maxlength="100"
                            placeholder="Ej.: Caja recepción"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="descripcionCajaGestion">Descripción</label>
                        <textarea
                            class="form-control"
                            id="descripcionCajaGestion"
                            maxlength="255"
                            rows="3"
                            placeholder="Ej.: Caja ubicada en el primer piso"></textarea>
                    </div>

                    <div class="custom-control custom-switch">
                        <input
                            type="checkbox"
                            class="custom-control-input"
                            id="permiteEfectivoCajaGestion"
                            checked>
                        <label
                            class="custom-control-label"
                            for="permiteEfectivoCajaGestion">
                            Esta caja permite operaciones en efectivo
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarCajaFisica">
                        <i class="fas fa-save mr-1"></i>
                        Guardar caja
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require 'footer.php';

$rutaGeneralSettingJs =
    __DIR__ . '/scripts/generalsetting.js';

$versionGeneralSettingJs =
    is_file($rutaGeneralSettingJs)
    ? filemtime($rutaGeneralSettingJs)
    : time();
?>

<script
    src="Views/modules/scripts/generalsetting.js?v=<?= (int)$versionGeneralSettingJs ?>"></script>

<?php
$rutaLogoEmpresaJs =
    __DIR__ . '/scripts/generalsetting-logo.js';

$versionLogoEmpresaJs =
    is_file($rutaLogoEmpresaJs)
    ? filemtime($rutaLogoEmpresaJs)
    : time();
?>

<script
    src="Views/modules/scripts/generalsetting-logo.js?v=<?= (int)$versionLogoEmpresaJs ?>"></script>

<?php
ob_end_flush();
?>
