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
                                id="listadoregistros">
                                <table id="tbllistado"></table>
                            </div>

                            <div id="formularioregistros">

                                <form
                                    name="formulario"
                                    id="formulario"
                                    method="POST"
                                    autocomplete="off">
                                    <div class="row">

                                        <input
                                            type="hidden"
                                            name="id_negocio"
                                            id="id_negocio">

                                        <input
                                            type="hidden"
                                            name="ndocumento"
                                            id="ndocumento"
                                            value="RUC">

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
                                                maxlength="10">
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
                                                step="0.01">
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
                                                maxlength="10">
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
                                                maxlength="10">
                                        </div>

                                        <div class="form-group col-lg-12 text-right">

                                            <button
                                                type="submit"
                                                class="btn btn-primary"
                                                id="btnGuardar">
                                                <i class="fa fa-save"></i>
                                                Guardar configuración
                                            </button>

                                        </div>

                                    </div>
                                </form>

                            </div>

                        </div>
                    </div>

                    <!-- =========================
     CONFIGURACIÓN DE CAJA
========================== -->

                    <div class="card mt-4">

                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0">
                                    Configuración de caja
                                </h4>
                            </div>

                            <span
                                id="estadoConfiguracionCaja"
                                class="badge badge-secondary">
                                Verificando
                            </span>
                        </div>

                        <div class="card-body">

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
                                            Esta será la caja utilizada cuando se active Caja única.
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

                                    <div class="form-group col-12 text-right">

                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                            id="btnGuardarConfiguracionCaja">
                                            <i class="fa fa-save"></i>
                                            Guardar modalidad
                                        </button>

                                        <small class="d-block text-muted mt-2">
                                            La activación estará disponible después de adaptar
                                            aperturas, ventas, cobranzas y cierres.
                                        </small>

                                    </div>

                                </div>

                            </form>

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
    src="Views/modules/scripts/generalsetting.js?v=<?= (int)$versionGeneralSettingJs ?>"></script>

<?php
ob_end_flush();
?>