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

if ((int)($_SESSION['almacen'] ?? 0) === 1) {
?>
    <link rel="stylesheet" href="Assets/css/atributo.css?v=3.0.1">

    <div class="main-content atributo-page">
        <section class="section">
            <div class="section-header atributo-section-header">
                <div>
                    <span class="atributo-eyebrow">Catálogo de productos</span>
                    <h1>Gestión de atributos</h1>
                    <p>Organiza características como talla, color, material y sus valores disponibles.</p>
                </div>

                <button class="btn btn-primary atributo-btn-primary" type="button" onclick="mostrarform(true)" id="btnagregar">
                    <i class="fas fa-plus"></i>
                    <span>Nuevo atributo</span>
                </button>
            </div>

            <div class="section-body">
                <div id="listadoregistros">
                    <div class="row atributo-summary-row">
                        <div class="col-xl-4 col-md-4 col-sm-12">
                            <div class="atributo-summary-card">
                                <div class="atributo-summary-icon atributo-summary-icon--total">
                                    <i class="fas fa-tags"></i>
                                </div>
                                <div>
                                    <span>Total de atributos</span>
                                    <strong id="kpiTotal">0</strong>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-md-4 col-sm-6">
                            <div class="atributo-summary-card">
                                <div class="atributo-summary-icon atributo-summary-icon--active">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <span>Activos</span>
                                    <strong id="kpiActivos">0</strong>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-md-4 col-sm-6">
                            <div class="atributo-summary-card">
                                <div class="atributo-summary-icon atributo-summary-icon--inactive">
                                    <i class="fas fa-pause-circle"></i>
                                </div>
                                <div>
                                    <span>Inactivos</span>
                                    <strong id="kpiInactivos">0</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card atributo-card">
                        <div class="card-body">
                            <div class="atributo-toolbar">
                                <div class="atributo-search-wrap">
                                    <i class="fas fa-search"></i>
                                    <input
                                        type="search"
                                        class="form-control"
                                        id="buscarAtributo"
                                        placeholder="Buscar por nombre o descripción..."
                                        aria-label="Buscar atributos"
                                    >
                                    <button type="button" class="atributo-clear-search" id="limpiarBusquedaAtributo" title="Limpiar búsqueda" aria-label="Limpiar búsqueda">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>

                                <div class="atributo-toolbar-actions">
                                    <div class="atributo-segmented" role="group" aria-label="Filtrar atributos por estado">
                                        <button type="button" class="is-active" data-estado="todos">Todos</button>
                                        <button type="button" data-estado="activo">Activos</button>
                                        <button type="button" data-estado="inactivo">Inactivos</button>
                                    </div>

                                    <div class="dropdown">
                                        <button class="btn atributo-btn-secondary dropdown-toggle" type="button" id="btnExportar" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-download"></i>
                                            <span>Exportar</span>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="btnExportar">
                                            <button type="button" class="dropdown-item" id="exportarExcel">
                                                <i class="far fa-file-excel mr-2"></i> Excel
                                            </button>
                                            <button type="button" class="dropdown-item" id="exportarPdf">
                                                <i class="far fa-file-pdf mr-2"></i> PDF
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="atributo-table-help">
                                <span><i class="fas fa-info-circle"></i> Administra primero el atributo y luego agrega sus valores.</span>
                                <span id="resultadoAtributos">0 resultados</span>
                            </div>

                            <div class="table-responsive atributo-table-wrap">
                                <table id="tbllistado" class="table atributo-table" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Descripción</th>
                                            <th>Estado</th>
                                            <th>Valores</th>
                                            <th>Opciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="formularioregistros" style="display:none;">
                    <div class="card atributo-card atributo-form-card">
                        <div class="card-header atributo-form-header">
                            <div class="atributo-form-heading">
                                <button type="button" class="atributo-back-button" onclick="cancelarform()" aria-label="Volver al listado" title="Volver al listado">
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                                <div>
                                    <span class="atributo-eyebrow" id="formEyebrow">Nuevo registro</span>
                                    <h4 id="tituloFormulario">Crear atributo</h4>
                                    <p id="descripcionFormulario">Define una característica que podrá utilizarse en las variaciones de tus productos.</p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <form name="formulario" id="formulario" method="POST" autocomplete="off" novalidate>
                                <input type="hidden" name="idatributo" id="idatributo">

                                <div class="row">
                                    <div class="form-group col-lg-6 col-md-6 col-12">
                                        <label for="nombre">Nombre del atributo <span class="text-danger">*</span></label>
                                        <div class="atributo-input-icon">
                                            <i class="fas fa-tag"></i>
                                            <input
                                                class="form-control"
                                                type="text"
                                                name="nombre"
                                                id="nombre"
                                                maxlength="100"
                                                placeholder="Ej.: Color, Talla o Material"
                                                required
                                            >
                                        </div>
                                        <small class="form-text text-muted">Usa un nombre breve y fácil de reconocer.</small>
                                        <div class="invalid-feedback">Ingresa el nombre del atributo.</div>
                                    </div>

                                    <div class="form-group col-lg-6 col-md-6 col-12">
                                        <label for="descripcion">Descripción <span class="atributo-optional">Opcional</span></label>
                                        <div class="atributo-input-icon">
                                            <i class="fas fa-align-left"></i>
                                            <input
                                                class="form-control"
                                                type="text"
                                                name="descripcion"
                                                id="descripcion"
                                                maxlength="255"
                                                placeholder="Ej.: Tallas disponibles para prendas"
                                            >
                                        </div>
                                        <div class="atributo-counter"><span id="contadorDescripcion">0</span>/255</div>
                                    </div>
                                </div>

                                <div class="atributo-form-tip">
                                    <i class="fas fa-lightbulb"></i>
                                    <div>
                                        <strong>Ejemplo:</strong>
                                        crea el atributo <b>Talla</b> y luego agrega valores como XS, S, M, L y XL.
                                    </div>
                                </div>

                                <div class="atributo-form-actions">
                                    <button class="btn atributo-btn-secondary" type="button" onclick="cancelarform()">
                                        Cancelar
                                    </button>
                                    <button class="btn btn-primary atributo-btn-primary" type="submit" id="btnGuardar">
                                        <i class="fas fa-save"></i>
                                        <span id="textoBtnGuardar">Guardar atributo</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade atributo-modal" id="modalValores" tabindex="-1" role="dialog" aria-labelledby="modalValoresLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header atributo-modal-header">
                    <div class="atributo-modal-title-wrap">
                        <div class="atributo-modal-icon"><i class="fas fa-layer-group"></i></div>
                        <div>
                            <span class="atributo-eyebrow">Valores del atributo</span>
                            <h5 class="modal-title" id="modalValoresLabel"><span id="titulo-atributo"></span></h5>
                            <p>Agrega las opciones que estarán disponibles para este atributo.</p>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="atributo-value-editor" id="editorValor">
                        <form id="formValor" autocomplete="off" novalidate>
                            <input type="hidden" id="idvalor" name="idvalor">
                            <input type="hidden" id="idatributo_valor" name="idatributo">

                            <div class="atributo-value-editor-header">
                                <div>
                                    <span class="atributo-value-mode" id="modoValor">Nuevo valor</span>
                                    <label for="valor" id="labelValor">Escribe el valor que deseas agregar</label>
                                </div>
                                <button type="button" class="atributo-link-button" id="btnCancelarValor" style="display:none;">
                                    <i class="fas fa-times"></i> Cancelar edición
                                </button>
                            </div>

                            <div class="atributo-value-input-row">
                                <div class="atributo-input-icon flex-grow-1">
                                    <i class="fas fa-font"></i>
                                    <input
                                        type="text"
                                        class="form-control"
                                        name="valor"
                                        id="valor"
                                        maxlength="100"
                                        placeholder="Ej.: Rojo, XL, Algodón"
                                        required
                                    >
                                    <div class="invalid-feedback">Ingresa un valor.</div>
                                </div>
                                <button type="submit" class="btn btn-primary atributo-btn-primary" id="btnGuardarValor">
                                    <i class="fas fa-plus"></i>
                                    <span>Agregar valor</span>
                                </button>
                            </div>
                            <small class="atributo-keyboard-hint"><kbd>Enter</kbd> para guardar</small>
                        </form>
                    </div>

                    <div class="atributo-values-toolbar">
                        <div class="atributo-search-wrap atributo-search-wrap--small">
                            <i class="fas fa-search"></i>
                            <input type="search" class="form-control" id="buscarValor" placeholder="Buscar valor..." aria-label="Buscar valores">
                            <button type="button" class="atributo-clear-search" id="limpiarBusquedaValor" title="Limpiar búsqueda" aria-label="Limpiar búsqueda">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="atributo-value-stats">
                            <span><b id="totalValores">0</b> total</span>
                            <span class="is-active"><b id="valoresActivos">0</b> activos</span>
                            <span class="is-inactive"><b id="valoresInactivos">0</b> inactivos</span>
                        </div>
                    </div>

                    <div class="table-responsive atributo-values-table-wrap">
                        <table class="table atributo-table atributo-values-table" id="tblvalores">
                            <thead>
                                <tr>
                                    <th>Valor</th>
                                    <th>Estado</th>
                                    <th class="text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="3" class="atributo-empty-cell">Selecciona un atributo para ver sus valores.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
} else {
    require 'access.php';
}

require 'footer.php';
?>
<script src="Views/modules/scripts/atributo.js?v=3.0.0"></script>
<?php
ob_end_flush();
?>
