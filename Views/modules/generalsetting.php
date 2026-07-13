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

if ((int)($_SESSION['settings'] ?? 0) !== 1) {
    require 'access.php';
    require 'footer.php';
    ob_end_flush();
    exit;
}
?>

<div class="main-content">
    <section class="section">

        <div class="section-body">
            <div class="row">
                <div class="col-12">

                    <div class="card">

                        <div class="card-header">
                            <h4>
                                Datos generales de la empresa
                            </h4>
                        </div>

                        <div class="card-body">

                            <div
                                class="table-responsive d-none"
                                id="listadoregistros"
                            >
                                <table id="tbllistado"></table>
                            </div>

                            <div id="formularioregistros">

                                <form
                                    name="formulario"
                                    id="formulario"
                                    method="POST"
                                    autocomplete="off"
                                >
                                    <div class="row">

                                        <input
                                            type="hidden"
                                            name="id_negocio"
                                            id="id_negocio"
                                        >

                                        <input
                                            type="hidden"
                                            name="ndocumento"
                                            id="ndocumento"
                                            value="RUC"
                                        >

                                        <!-- =========================
                                             DATOS DE LA EMPRESA
                                        ========================== -->

                                        <div class="col-12 mb-3">
                                            <h5 class="mb-1">
                                                Información empresarial
                                            </h5>

                                            <p class="text-muted mb-0">
                                                Información utilizada en los comprobantes
                                                emitidos por el sistema.
                                            </p>
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
                                                required
                                            >
                                        </div>

                                        <div class="form-group col-lg-6">
                                            <label>
                                                Tipo de documento
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                value="RUC"
                                                disabled
                                            >
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
                                                required
                                            >
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
                                                required
                                            >
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
                                                maxlength="50"
                                            >
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
                                                maxlength="50"
                                            >
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
                                                required
                                            >
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
                                                maxlength="100"
                                            >
                                        </div>

                                        <!-- =========================
                                             CONSULTA DNI/RUC
                                        ========================== -->

                                        <div class="col-12 mt-3 mb-3">
                                            <hr>

                                            <h5 class="mb-1">
                                                Consulta de DNI y RUC
                                            </h5>

                                            <p class="text-muted mb-0">
                                                Este token se utiliza únicamente para
                                                consultar datos de personas y empresas.
                                                No es el token de facturación electrónica.
                                            </p>
                                        </div>

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
                                                    autocomplete="new-password"
                                                >

                                                <div class="input-group-append">

                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary"
                                                        id="toggleTokenVisibility"
                                                        aria-label="Mostrar u ocultar token"
                                                    >
                                                        <i
                                                            class="fa fa-eye"
                                                            id="eyeIcon"
                                                        ></i>
                                                    </button>

                                                </div>
                                            </div>
                                        </div>

                                        <!-- =========================
                                             APISUNAT
                                        ========================== -->

                                        <div class="col-12 mt-3 mb-3">
                                            <hr>

                                            <h5 class="mb-1">
                                                Facturación electrónica APISUNAT
                                            </h5>

                                            <p class="text-muted mb-0">
                                                Credenciales utilizadas para emitir
                                                facturas y boletas electrónicas.
                                            </p>
                                        </div>

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
                                                placeholder="Persona ID proporcionado por APISUNAT"
                                            >

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
                                                    placeholder="Dejar vacío para conservar el token actual"
                                                >

                                                <div class="input-group-append">

                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary"
                                                        id="toggleApiSunatToken"
                                                        aria-label="Mostrar u ocultar Persona Token"
                                                    >
                                                        <i
                                                            class="fa fa-eye"
                                                            id="apiSunatEyeIcon"
                                                        ></i>
                                                    </button>

                                                </div>
                                            </div>

                                            <small
                                                id="apisunatTokenEstado"
                                                class="form-text text-muted"
                                            >
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
                                                id="apisunat_production"
                                            >
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
                                                style="height:auto;min-height:42px;"
                                            >
                                                <span
                                                    id="apisunatEstadoGeneral"
                                                    class="badge badge-secondary"
                                                >
                                                    Verificando
                                                </span>
                                            </div>
                                        </div>

                                        <!-- =========================
                                             IMPUESTOS Y MONEDA
                                        ========================== -->

                                        <div class="col-12 mt-3 mb-3">
                                            <hr>

                                            <h5 class="mb-1">
                                                Impuestos y moneda
                                            </h5>
                                        </div>

                                        <div class="form-group col-lg-3">
                                            <label for="nombre_impuesto">
                                                Nombre del impuesto
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="nombre_impuesto"
                                                id="nombre_impuesto"
                                                maxlength="10"
                                            >
                                        </div>

                                        <div class="form-group col-lg-3">
                                            <label for="monto_impuesto">
                                                Monto (%)
                                            </label>

                                            <input
                                                type="number"
                                                class="form-control"
                                                name="monto_impuesto"
                                                id="monto_impuesto"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                            >
                                        </div>

                                        <div class="form-group col-lg-3">
                                            <label for="moneda">
                                                Moneda
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="moneda"
                                                id="moneda"
                                                maxlength="10"
                                            >
                                        </div>

                                        <div class="form-group col-lg-3">
                                            <label for="simbolo">
                                                Símbolo
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                name="simbolo"
                                                id="simbolo"
                                                maxlength="10"
                                            >
                                        </div>

                                        <div class="form-group col-lg-12 text-right">

                                            <button
                                                type="submit"
                                                class="btn btn-primary"
                                                id="btnGuardar"
                                            >
                                                <i class="fa fa-save"></i>
                                                Guardar configuración
                                            </button>

                                        </div>

                                    </div>
                                </form>

                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>

    </section>
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
    src="Views/modules/scripts/generalsetting.js?v=<?= (int)$versionGeneralSettingJs ?>"
></script>

<?php
ob_end_flush();
?>