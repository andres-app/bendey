<?php
//Views/modules/users.php
ob_start();
session_start();

if (!isset($_SESSION['nombre'])) {
    header('Location: login');
    exit;
}

require 'header.php';
require 'sidebar.php';

if ((int)($_SESSION['users'] ?? 0) !== 1) {
    require 'access.php';
    require 'footer.php';
    ob_end_flush();
    exit;
}
?>

<style>
  .users-shell {
    --users-primary: #3f51b5;
    --users-soft: #f4f6fb;
    --users-border: #e7eaf2;
    --users-text: #263043;
    --users-muted: #7b8498;
  }

  .users-shell .card {
    border: 0;
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(31, 45, 72, .07);
    overflow: hidden;
  }

  .users-shell .users-header {
    padding: 22px 24px;
    border-bottom: 1px solid var(--users-border);
    background: #fff;
  }

  .users-shell .users-header h4 {
    margin: 0;
    color: var(--users-text);
    font-weight: 700;
  }

  .users-shell .users-header p {
    margin: 4px 0 0;
    color: var(--users-muted);
    font-size: 13px;
  }

  .users-shell .btn-create {
    border-radius: 10px;
    padding: 10px 16px;
    font-weight: 600;
    box-shadow: 0 7px 18px rgba(63, 81, 181, .18);
  }

  .users-shell #tbllistado {
    min-width: 1120px;
  }

  .users-shell .table thead th {
    border-top: 0;
    border-bottom: 1px solid var(--users-border);
    background: #f8f9fc;
    color: #687187;
    font-size: 11px;
    letter-spacing: .04em;
    text-transform: uppercase;
    white-space: nowrap;
  }

  .users-shell .table td {
    vertical-align: middle;
    border-color: #f0f2f7;
  }

  .users-shell .form-section {
    border: 1px solid var(--users-border);
    border-radius: 16px;
    background: #fff;
    margin-bottom: 18px;
    overflow: hidden;
  }

  .users-shell .form-section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    background: #fafbfe;
    border-bottom: 1px solid var(--users-border);
  }

  .users-shell .section-icon {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    color: var(--users-primary);
    background: rgba(63, 81, 181, .10);
  }

  .users-shell .form-section-header h6 {
    margin: 0;
    color: var(--users-text);
    font-weight: 700;
  }

  .users-shell .form-section-header small {
    color: var(--users-muted);
  }

  .users-shell .form-section-body {
    padding: 18px;
  }

  .users-shell .form-control {
    min-height: 44px;
    border-radius: 10px;
    border-color: #dfe3ec;
  }

  .users-shell .form-control:focus {
    border-color: #9ea9df;
    box-shadow: 0 0 0 3px rgba(63, 81, 181, .10);
  }

  .users-shell .select2-container .select2-selection--multiple {
    min-height: 44px;
    border-radius: 10px;
    border-color: #dfe3ec;
  }

  .users-shell .select2-container--default.select2-container--focus
  .select2-selection--multiple {
    border-color: #9ea9df;
    box-shadow: 0 0 0 3px rgba(63, 81, 181, .10);
  }

  .users-shell .select2-container--default
  .select2-selection--multiple
  .select2-selection__choice {
    border: 0;
    border-radius: 7px;
    color: #35405a;
    background: #edf0fb;
  }

  .users-shell label {
    color: #4c5569;
    font-size: 13px;
    font-weight: 600;
  }

  .users-shell .assignment-summary {
    height: 100%;
    border-radius: 15px;
    padding: 18px;
    color: #fff;
    background: linear-gradient(145deg, #3949ab, #5567cb);
    box-shadow: 0 12px 24px rgba(57, 73, 171, .20);
  }

  .users-shell .assignment-summary .summary-label {
    color: rgba(255, 255, 255, .72);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
  }

  .users-shell .assignment-summary .summary-value {
    margin-bottom: 12px;
    font-size: 14px;
    font-weight: 700;
  }

  .users-shell .permission-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
    gap: 10px;
  }

  .users-shell .permission-item {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    margin: 0;
    padding: 10px 12px;
    border: 1px solid var(--users-border);
    border-radius: 10px;
    background: #fff;
    cursor: pointer;
    transition: .2s ease;
  }

  .users-shell .permission-item:hover {
    border-color: #b8c0e6;
    background: #fafbff;
  }

  .users-shell .permission-item input {
    display: none;
  }

  .users-shell .permission-check {
    width: 22px;
    height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 22px;
    border: 1px solid #ced4e3;
    border-radius: 7px;
    color: transparent;
    background: #fff;
  }

  .users-shell .permission-item input:checked + .permission-check {
    color: #fff;
    border-color: var(--users-primary);
    background: var(--users-primary);
  }

  .users-shell .access-switch {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    min-height: 68px;
    padding: 12px;
    margin-bottom: 10px;
    border: 1px solid var(--users-border);
    border-radius: 11px;
    background: #fff;
    cursor: pointer;
  }

  .users-shell .access-switch input {
    margin-top: 4px;
  }

  .users-shell .access-switch strong {
    display: block;
    color: var(--users-text);
    font-size: 13px;
  }

  .users-shell .access-switch small {
    display: block;
    color: var(--users-muted);
    font-weight: 400;
    line-height: 1.35;
  }

  .users-shell .photo-panel {
    display: flex;
    align-items: center;
    gap: 16px;
    min-height: 118px;
    padding: 14px;
    border: 1px dashed #cfd5e3;
    border-radius: 13px;
    background: #fafbfe;
  }

  .users-shell .photo-panel img {
    width: 86px;
    height: 86px;
    object-fit: cover;
    border-radius: 16px;
    border: 4px solid #fff;
    box-shadow: 0 6px 15px rgba(31, 45, 72, .12);
  }

  .users-shell .form-actions {
    position: sticky;
    bottom: 0;
    z-index: 5;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 15px 18px;
    margin: 0 -18px -18px;
    border-top: 1px solid var(--users-border);
    background: rgba(255, 255, 255, .96);
    backdrop-filter: blur(8px);
  }

  .users-shell .form-actions .btn {
    min-width: 125px;
    border-radius: 10px;
    font-weight: 600;
  }

  .users-shell .password-card {
    max-width: 520px;
    margin: 18px auto;
    padding: 22px;
    border: 1px solid var(--users-border);
    border-radius: 16px;
    background: #fff;
  }

  @media (max-width: 767.98px) {
    .users-shell .users-header {
      align-items: flex-start !important;
      gap: 14px;
    }

    .users-shell .users-header .btn-create {
      width: 100%;
    }

    .users-shell .form-actions {
      flex-direction: column-reverse;
    }

    .users-shell .form-actions .btn {
      width: 100%;
    }
  }
</style>

<div class="main-content users-shell">
  <section class="section">
    <div class="section-body">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="users-header d-flex align-items-center justify-content-between flex-wrap">
              <div>
                <h4><i class="fas fa-users-cog mr-2 text-primary"></i>Usuarios y accesos</h4>
                <p>Administra roles, permisos, sucursal, caja y almacén desde un solo lugar.</p>
              </div>

              <button
                type="button"
                class="btn btn-primary btn-create"
                id="btnagregar"
                onclick="nuevoUsuario()"
              >
                <i class="fas fa-user-plus mr-1"></i>
                Nuevo usuario
              </button>
            </div>

            <div class="card-body">
              <div class="table-responsive" id="listadoregistros">
                <table
                  id="tbllistado"
                  class="table table-hover"
                  style="width:100%;"
                >
                  <thead>
                    <tr>
                      <th>Acciones</th>
                      <th>Usuario</th>
                      <th>Documento</th>
                      <th>Contacto</th>
                      <th>Rol</th>
                      <th>Sucursal</th>
                      <th>Caja</th>
                      <th>Almacén</th>
                      <th>Estado</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div id="formularioregistros" style="display:none;">
                <form
                  action=""
                  name="formulario"
                  id="formulario"
                  method="POST"
                  enctype="multipart/form-data"
                  autocomplete="off"
                >
                  <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                      <h5 class="mb-1" id="tituloFormulario">Nuevo usuario</h5>
                      <small class="text-muted">
                        Los campos marcados con * son obligatorios.
                      </small>
                    </div>
                  </div>

                  <input type="hidden" name="idusuario" id="idusuario">

                  <div class="form-section">
                    <div class="form-section-header">
                      <span class="section-icon">
                        <i class="fas fa-id-card"></i>
                      </span>
                      <div>
                        <h6>Datos personales</h6>
                        <small>Información básica e identificación del usuario.</small>
                      </div>
                    </div>

                    <div class="form-section-body">
                      <div class="row">
                        <div class="form-group col-lg-6 col-md-6">
                          <label for="nombre">Nombre completo *</label>
                          <input
                            class="form-control"
                            type="text"
                            name="nombre"
                            id="nombre"
                            maxlength="100"
                            placeholder="Ej. Andrea Ramírez"
                            required
                          >
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                          <label for="tipo_documento">Tipo de documento *</label>
                          <select
                            name="tipo_documento"
                            id="tipo_documento"
                            class="form-control"
                            required
                          >
                            <option value="DNI">DNI</option>
                            <option value="RUC">RUC</option>
                            <option value="CEDULA">Cédula</option>
                            <option value="CE">Carné de extranjería</option>
                          </select>
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                          <label for="num_documento">Número de documento</label>
                          <input
                            type="text"
                            class="form-control"
                            name="num_documento"
                            id="num_documento"
                            maxlength="20"
                            placeholder="Documento"
                          >
                        </div>

                        <div class="form-group col-lg-6 col-md-6">
                          <label for="direccion">Dirección</label>
                          <input
                            class="form-control"
                            type="text"
                            name="direccion"
                            id="direccion"
                            maxlength="70"
                            placeholder="Dirección del usuario"
                          >
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                          <label for="telefono">Teléfono</label>
                          <input
                            class="form-control"
                            type="text"
                            name="telefono"
                            id="telefono"
                            maxlength="20"
                            placeholder="Número de teléfono"
                          >
                        </div>

                        <div class="form-group col-lg-3 col-md-6">
                          <label for="email">Correo electrónico</label>
                          <input
                            class="form-control"
                            type="email"
                            name="email"
                            id="email"
                            maxlength="70"
                            placeholder="usuario@empresa.com"
                          >
                        </div>

                        <div class="form-group col-lg-4 col-md-6">
                          <label for="cargo">Cargo</label>
                          <input
                            class="form-control"
                            type="text"
                            name="cargo"
                            id="cargo"
                            maxlength="20"
                            placeholder="Ej. Cajero"
                          >
                        </div>

                        <div class="form-group col-lg-8 col-md-6">
                          <label>Fotografía</label>
                          <div class="photo-panel">
                            <img
                              src="data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='180' viewBox='0 0 180 180'%3E%3Crect width='180' height='180' rx='28' fill='%23f1f3f8'/%3E%3Ccircle cx='90' cy='67' r='31' fill='%23aeb7c8'/%3E%3Cpath d='M35 157c5-34 27-52 55-52s50 18 55 52' fill='%23aeb7c8'/%3E%3C/svg%3E"
                              alt="Foto del usuario"
                              id="imagenmuestra"
                            >

                            <div class="flex-grow-1">
                              <input
                                class="form-control"
                                type="file"
                                name="imagen"
                                id="imagen"
                                accept=".jpg,.jpeg,.png,.webp"
                              >
                              <input
                                type="hidden"
                                name="imagenactual"
                                id="imagenactual"
                              >
                              <small class="text-muted d-block mt-2">
                                JPG, PNG o WEBP. Máximo 5 MB.
                              </small>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="form-section">
                    <div class="form-section-header">
                      <span class="section-icon">
                        <i class="fas fa-building"></i>
                      </span>
                      <div>
                        <h6>Asignación operativa</h6>
                        <small>Define dónde trabajará y qué función cumplirá.</small>
                      </div>
                    </div>

                    <div class="form-section-body">
                      <div class="row">
                        <div class="col-lg-8">
                          <div class="row">
                            <div class="form-group col-md-6">
                              <label for="rol">Rol operativo *</label>
                              <select
                                name="rol"
                                id="rol"
                                class="form-control"
                                required
                              >
                                <option value="ADMINISTRADOR">Administrador</option>
                                <option value="CAJERO">Cajero</option>
                                <option value="VENDEDOR" selected>Vendedor</option>
                              </select>
                              <small class="form-text text-muted">
                                Al cambiar el rol se aplican permisos sugeridos, que luego puedes ajustar.
                              </small>
                            </div>

                            <div class="form-group col-md-6">
                              <label for="idsucursal">Sucursal *</label>
                              <select
                                name="idsucursal"
                                id="idsucursal"
                                class="form-control"
                                required
                              >
                                <option value="">Selecciona una sucursal</option>
                              </select>
                            </div>

                            <div class="form-group col-md-6">
                              <label for="idcaja">Cajas asignadas</label>
                              <select
                                name="idcaja[]"
                                id="idcaja"
                                class="form-control"
                                multiple
                                required
                              ></select>
                              <small class="form-text text-muted">
                                Puedes asignar una o varias cajas físicas al mismo usuario.
                              </small>
                            </div>

                            <div class="form-group col-md-6">
                              <label for="idalmacen">Almacén asignado</label>
                              <select
                                name="idalmacen"
                                id="idalmacen"
                                class="form-control"
                              >
                                <option value="">Sin almacén asignado</option>
                              </select>
                            </div>
                          </div>
                        </div>

                        <div class="col-lg-4 mb-3">
                          <div class="assignment-summary">
                            <div class="mb-3">
                              <i class="fas fa-user-shield fa-lg"></i>
                            </div>

                            <div class="summary-label">Rol</div>
                            <div class="summary-value" id="resumenRol">VENDEDOR</div>

                            <div class="summary-label">Sucursal</div>
                            <div class="summary-value" id="resumenSucursal">Sin sucursal</div>

                            <div class="summary-label">Caja</div>
                            <div class="summary-value" id="resumenCaja">Sin caja</div>

                            <div class="summary-label">Almacén</div>
                            <div class="summary-value mb-0" id="resumenAlmacen">Sin almacén</div>
                          </div>
                        </div>
                      </div>

                      <hr>

                      <div class="row">
                        <div class="col-lg-6">
                          <h6 class="mb-3">Permisos en la sucursal</h6>

                          <div class="row">
                            <div class="col-md-6">
                              <label class="access-switch">
                                <input
                                  type="checkbox"
                                  name="puede_vender"
                                  id="puede_vender"
                                  value="1"
                                >
                                <span>
                                  <strong>Puede vender</strong>
                                  <small>Registra ventas desde el POS.</small>
                                </span>
                              </label>
                            </div>

                            <div class="col-md-6">
                              <label class="access-switch">
                                <input
                                  type="checkbox"
                                  name="puede_cobrar"
                                  id="puede_cobrar"
                                  value="1"
                                >
                                <span>
                                  <strong>Puede cobrar</strong>
                                  <small>Procesa pagos y cobranzas.</small>
                                </span>
                              </label>
                            </div>

                            <div class="col-md-6">
                              <label class="access-switch">
                                <input
                                  type="checkbox"
                                  name="puede_abrir_caja_sucursal"
                                  id="puede_abrir_caja_sucursal"
                                  value="1"
                                >
                                <span>
                                  <strong>Abrir caja</strong>
                                  <small>Autoriza aperturas en la sucursal.</small>
                                </span>
                              </label>
                            </div>

                            <div class="col-md-6">
                              <label class="access-switch">
                                <input
                                  type="checkbox"
                                  name="puede_cerrar_caja_sucursal"
                                  id="puede_cerrar_caja_sucursal"
                                  value="1"
                                >
                                <span>
                                  <strong>Cerrar caja</strong>
                                  <small>Autoriza cierres en la sucursal.</small>
                                </span>
                              </label>
                            </div>
                          </div>
                        </div>

                        <div class="col-lg-6">
                          <h6 class="mb-3">Permisos sobre la caja asignada</h6>

                          <div class="row">
                            <div class="col-md-4">
                              <label class="access-switch">
                                <input
                                  type="checkbox"
                                  name="puede_abrir"
                                  id="puede_abrir"
                                  value="1"
                                >
                                <span>
                                  <strong>Abrir</strong>
                                  <small>Inicia la jornada de caja.</small>
                                </span>
                              </label>
                            </div>

                            <div class="col-md-4">
                              <label class="access-switch">
                                <input
                                  type="checkbox"
                                  name="puede_cerrar"
                                  id="puede_cerrar"
                                  value="1"
                                >
                                <span>
                                  <strong>Cerrar</strong>
                                  <small>Finaliza y arquea la caja.</small>
                                </span>
                              </label>
                            </div>

                            <div class="col-md-4">
                              <label class="access-switch">
                                <input
                                  type="checkbox"
                                  name="puede_operar"
                                  id="puede_operar"
                                  value="1"
                                >
                                <span>
                                  <strong>Operar</strong>
                                  <small>Usa la caja para vender o cobrar.</small>
                                </span>
                              </label>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="form-section">
                    <div class="form-section-header">
                      <span class="section-icon">
                        <i class="fas fa-lock"></i>
                      </span>
                      <div>
                        <h6>Acceso y módulos</h6>
                        <small>Credenciales y permisos generales del sistema.</small>
                      </div>
                    </div>

                    <div class="form-section-body">
                      <div class="row">
                        <div class="form-group col-lg-6 col-md-6">
                          <label for="login">Usuario de acceso *</label>
                          <input
                            class="form-control"
                            type="text"
                            name="login"
                            id="login"
                            maxlength="20"
                            placeholder="Nombre de usuario"
                            required
                            autocomplete="off"
                          >
                        </div>

                        <div
                          class="form-group col-lg-6 col-md-6"
                          id="claves"
                        >
                          <label for="clave">Contraseña *</label>
                          <input
                            class="form-control"
                            type="password"
                            name="clave"
                            id="clave"
                            maxlength="64"
                            placeholder="Contraseña"
                            required
                            autocomplete="new-password"
                          >
                        </div>

                        <div class="form-group col-12">
                          <label class="mb-2">Permisos de módulos</label>
                          <div id="permisos" class="permission-grid"></div>
                        </div>
                      </div>

                      <div class="form-actions">
                        <button
                          class="btn btn-light"
                          onclick="cancelarform()"
                          type="button"
                        >
                          <i class="fas fa-arrow-left mr-1"></i>
                          Cancelar
                        </button>

                        <button
                          class="btn btn-primary"
                          type="submit"
                          id="btnGuardar"
                        >
                          <i class="fas fa-save mr-1"></i>
                          Guardar usuario
                        </button>
                      </div>
                    </div>
                  </div>
                </form>
              </div>

              <div id="formulario_clave" style="display:none;">
                <div class="password-card">
                  <div class="text-center mb-4">
                    <span class="section-icon mb-3">
                      <i class="fas fa-key"></i>
                    </span>
                    <h5>Nueva contraseña</h5>
                    <p class="text-muted mb-0">
                      Define una nueva clave de acceso para el usuario.
                    </p>
                  </div>

                  <form
                    action=""
                    name="formularioc"
                    id="formularioc"
                    method="POST"
                    autocomplete="off"
                  >
                    <input
                      type="hidden"
                      name="idusuarioc"
                      id="idusuarioc"
                    >

                    <div class="form-group">
                      <label for="clavec">Contraseña nueva</label>
                      <input
                        class="form-control"
                        type="password"
                        name="clavec"
                        id="clavec"
                        maxlength="64"
                        placeholder="Ingresa la nueva contraseña"
                        required
                        autocomplete="new-password"
                      >
                    </div>

                    <div class="d-flex justify-content-end flex-wrap" style="gap:10px;">
                      <button
                        class="btn btn-light"
                        onclick="cancelarform_clave()"
                        type="button"
                      >
                        Cancelar
                      </button>

                      <button
                        class="btn btn-primary"
                        type="submit"
                        id="btnGuardar_clave"
                      >
                        <i class="fas fa-save mr-1"></i>
                        Actualizar contraseña
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php require 'footer.php'; ?>

<?php
$versionUserJs = @filemtime(__DIR__ . '/scripts/user.js');
if (!$versionUserJs) {
    $versionUserJs = time();
}
?>
<script src="Views/modules/scripts/user.js?v=<?= (int)$versionUserJs ?>"></script>

<?php
ob_end_flush();
?>
