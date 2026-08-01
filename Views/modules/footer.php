<?php
date_default_timezone_set('America/Lima');

$fechaApertura = new DateTime(
    'now',
    new DateTimeZone('America/Lima')
);
?>

<style>
/* ==========================================================
   MODAL PREMIUM DE APERTURA DE CAJA
   Los estilos están limitados a #modalCajaChica
========================================================== */

#modalCajaChica {
  --caja-primary: #6777ef;
  --caja-primary-dark: #4f5dde;
  --caja-success: #16a34a;
  --caja-success-dark: #07833a;
  --caja-title: #18212f;
  --caja-text: #657083;
  --caja-border: #e5e9f2;
  --caja-background: #f7f8fc;
}

#modalCajaChica .modal-dialog {
  width: calc(100% - 28px);
  max-width: 500px;
  margin-left: auto;
  margin-right: auto;
}

#modalCajaChica .modal-caja {
  border: 0;
  border-radius: 24px;
  overflow: hidden;
  background: #ffffff;
  box-shadow:
    0 30px 80px rgba(25, 35, 58, 0.24),
    0 10px 30px rgba(25, 35, 58, 0.12);
}

/* ================= ENCABEZADO ================= */

#modalCajaChica .caja-premium-header {
  position: relative;
  display: block;
  padding: 29px 30px 27px;
  color: #ffffff;
  text-align: left;
  overflow: hidden;
  background:
    linear-gradient(
      135deg,
      #5364e8 0%,
      #6777ef 48%,
      #7459e9 100%
    );
}

#modalCajaChica .caja-premium-header::before {
  content: "";
  position: absolute;
  width: 190px;
  height: 190px;
  top: -112px;
  right: -64px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.10);
}

#modalCajaChica .caja-premium-header::after {
  content: "";
  position: absolute;
  width: 115px;
  height: 115px;
  right: 52px;
  bottom: -75px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.08);
}

#modalCajaChica .caja-header-content {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
}

#modalCajaChica .caja-header-icon {
  width: 62px;
  min-width: 62px;
  height: 62px;
  margin-right: 17px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 19px;
  font-size: 25px;
  color: #ffffff;
  background: rgba(255, 255, 255, 0.17);
  border: 1px solid rgba(255, 255, 255, 0.22);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.20),
    0 10px 25px rgba(26, 34, 100, 0.18);
  backdrop-filter: blur(5px);
}

#modalCajaChica .caja-header-copy {
  min-width: 0;
}

#modalCajaChica .caja-header-badge {
  display: inline-flex;
  align-items: center;
  margin-bottom: 7px;
  padding: 5px 10px;
  border-radius: 30px;
  font-size: 10px;
  line-height: 1;
  font-weight: 800;
  letter-spacing: 0.7px;
  text-transform: uppercase;
  color: #ffffff;
  background: rgba(255, 255, 255, 0.16);
}

#modalCajaChica .caja-header-badge::before {
  content: "";
  width: 7px;
  height: 7px;
  margin-right: 7px;
  border-radius: 50%;
  background: #8ff0ad;
  box-shadow: 0 0 0 4px rgba(143, 240, 173, 0.14);
}

#modalCajaChica .caja-premium-title {
  margin: 0;
  color: #ffffff;
  font-size: 21px;
  line-height: 1.2;
  font-weight: 800;
  letter-spacing: -0.2px;
}

#modalCajaChica .caja-premium-subtitle {
  margin: 6px 0 0;
  color: rgba(255, 255, 255, 0.78);
  font-size: 12px;
  line-height: 1.5;
}

/* ================= CUERPO ================= */

#modalCajaChica .modal-body {
  padding: 25px 28px 28px;
  text-align: left;
}

/* ================= FECHA ================= */

#modalCajaChica .caja-fecha-card {
  display: flex;
  align-items: center;
  margin-bottom: 20px;
  padding: 14px 15px;
  border: 1px solid var(--caja-border);
  border-radius: 15px;
  background: var(--caja-background);
}

#modalCajaChica .caja-fecha-icon {
  width: 43px;
  min-width: 43px;
  height: 43px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 13px;
  border-radius: 13px;
  color: var(--caja-primary);
  font-size: 17px;
  background: #ffffff;
  border: 1px solid #e7eaff;
  box-shadow: 0 5px 14px rgba(61, 72, 145, 0.08);
}

#modalCajaChica .caja-fecha-contenido {
  flex: 1;
  min-width: 0;
}

#modalCajaChica .caja-meta-label {
  display: block;
  margin-bottom: 2px;
  color: #9199a8;
  font-size: 10px;
  font-weight: 800;
  line-height: 1.2;
  letter-spacing: 0.55px;
  text-transform: uppercase;
}

#modalCajaChica .caja-fecha-principal {
  display: block;
  color: var(--caja-title);
  font-size: 14px;
  line-height: 1.4;
  font-weight: 800;
}

#modalCajaChica .caja-hora {
  display: inline-flex;
  align-items: center;
  margin-left: 7px;
  padding-left: 8px;
  color: var(--caja-text);
  font-size: 12px;
  font-weight: 600;
  border-left: 1px solid #dce0e9;
}

/* ================= CONTEXTO DE CAJA ================= */

#modalCajaChica #bloqueContextoCaja {
  margin-bottom: 18px;
  padding: 14px 15px;
  border: 1px solid #e5e9f4 !important;
  border-radius: 15px;
  color: var(--caja-title);
  background: #f9faff;
}

#modalCajaChica #bloqueContextoCaja .fa-cash-register {
  width: 41px;
  min-width: 41px;
  height: 41px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 0 !important;
  margin-right: 12px !important;
  border-radius: 12px;
  color: var(--caja-primary) !important;
  font-size: 17px !important;
  background: #edf0ff;
}

#modalCajaChica #tituloContextoCaja {
  color: var(--caja-title);
  font-size: 13px;
  font-weight: 800;
}

#modalCajaChica #descripcionContextoCaja {
  color: var(--caja-text) !important;
  line-height: 1.5;
}

/* ================= CAMPOS ================= */

#modalCajaChica .caja-form-group {
  margin-bottom: 18px;
}

#modalCajaChica .caja-label {
  display: block;
  margin-bottom: 8px;
  color: #30394a;
  font-size: 12px;
  font-weight: 800;
}

#modalCajaChica .caja-label i {
  width: 18px;
  color: var(--caja-primary);
}

#modalCajaChica #idcajaOperacion {
  height: 51px;
  padding: 0 15px;
  border: 1px solid var(--caja-border);
  border-radius: 13px;
  color: var(--caja-title);
  font-size: 13px;
  font-weight: 700;
  background-color: #ffffff;
  box-shadow: none;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

#modalCajaChica #idcajaOperacion:focus {
  border-color: var(--caja-primary);
  box-shadow: 0 0 0 4px rgba(103, 119, 239, 0.12);
}

#modalCajaChica #ayudaSeleccionCaja {
  margin-top: 7px;
  padding-left: 2px;
  color: #8992a2 !important;
  font-size: 11px;
  line-height: 1.5;
}

/* ================= CAJA AUTOMÁTICA ================= */

#modalCajaChica .caja-asignada-card {
  position: relative;
  min-height: 67px;
  padding: 13px 50px 13px 15px;
  border: 1px solid #dfe4f0;
  border-radius: 14px;
  background:
    linear-gradient(
      135deg,
      #f9faff 0%,
      #f4f6ff 100%
    );
}

#modalCajaChica .caja-asignada-card::after {
  content: "\f058";
  position: absolute;
  top: 50%;
  right: 16px;
  transform: translateY(-50%);
  color: #20b26b;
  font-family: "Font Awesome 5 Free";
  font-size: 21px;
  font-weight: 900;
}

#modalCajaChica #nombreCajaAutomatica {
  display: block;
  margin-bottom: 3px;
  color: var(--caja-title);
  font-size: 14px;
  font-weight: 800;
}

#modalCajaChica .caja-codigo {
  color: var(--caja-text);
  font-size: 11px;
  line-height: 1.4;
}

/* ================= MONTO ================= */

#modalCajaChica .caja-monto-section {
  margin-top: 22px;
  padding: 19px;
  border: 1px solid #e3e7f0;
  border-radius: 18px;
  background:
    linear-gradient(
      145deg,
      #ffffff 0%,
      #fafbfe 100%
    );
  box-shadow: 0 8px 24px rgba(34, 45, 76, 0.06);
}

#modalCajaChica .caja-monto-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 13px;
}

#modalCajaChica .caja-monto-heading strong {
  display: block;
  color: var(--caja-title);
  font-size: 13px;
  font-weight: 800;
}

#modalCajaChica .caja-monto-heading small {
  display: block;
  margin-top: 3px;
  color: #8c95a5;
  font-size: 11px;
}

#modalCajaChica .caja-monto-icon {
  width: 38px;
  min-width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-left: 12px;
  border-radius: 11px;
  color: #11924d;
  background: #eaf9f0;
}

#modalCajaChica .caja-monto-control {
  display: flex;
  align-items: stretch;
  min-height: 64px;
  overflow: hidden;
  border: 2px solid #e2e6ef;
  border-radius: 15px;
  background: #ffffff;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease;
}

#modalCajaChica .caja-monto-control:focus-within {
  border-color: var(--caja-primary);
  box-shadow: 0 0 0 5px rgba(103, 119, 239, 0.11);
  transform: translateY(-1px);
}

#modalCajaChica .caja-moneda {
  min-width: 67px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--caja-primary-dark);
  font-size: 19px;
  font-weight: 900;
  background: #f1f3ff;
  border-right: 1px solid #e2e5f4;
}

#modalCajaChica #montoApertura {
  height: 62px;
  padding: 7px 17px;
  border: 0;
  border-radius: 0;
  color: #18212f;
  font-size: 25px;
  font-weight: 800;
  text-align: right !important;
  background: #ffffff;
  box-shadow: none;
}

#modalCajaChica #montoApertura::placeholder {
  color: #c4cad4;
  opacity: 1;
}

#modalCajaChica #montoApertura:focus {
  box-shadow: none;
  outline: none;
}

/* Oculta controles laterales del input number */

#modalCajaChica #montoApertura::-webkit-outer-spin-button,
#modalCajaChica #montoApertura::-webkit-inner-spin-button {
  margin: 0;
  -webkit-appearance: none;
}

#modalCajaChica #montoApertura[type="number"] {
  -moz-appearance: textfield;
}

/* ================= MENSAJES ================= */

#modalCajaChica #mensajePermisoCaja {
  margin: 17px 0 0;
  padding: 13px 14px;
  border: 0;
  border-radius: 13px;
  color: #815e08;
  font-size: 12px;
  line-height: 1.5;
  background: #fff6dc;
}

#modalCajaChica #mensajePermisoCaja::before {
  content: "\f071";
  margin-right: 8px;
  font-family: "Font Awesome 5 Free";
  font-weight: 900;
}

/* ================= BOTÓN ================= */

#modalCajaChica .caja-boton-contenedor {
  margin-top: 21px;
}

#modalCajaChica #btnAbrirCaja {
  position: relative;
  min-height: 57px;
  border: 0;
  border-radius: 15px;
  overflow: hidden;
  color: #ffffff;
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0.3px;
  background:
    linear-gradient(
      135deg,
      var(--caja-success) 0%,
      var(--caja-success-dark) 100%
    );
  box-shadow: 0 12px 24px rgba(11, 139, 65, 0.25);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease;
}

#modalCajaChica #btnAbrirCaja::before {
  content: "";
  position: absolute;
  width: 105px;
  height: 105px;
  top: -70px;
  right: -25px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.13);
}

#modalCajaChica #btnAbrirCaja:hover:not(:disabled) {
  color: #ffffff;
  transform: translateY(-2px);
  box-shadow: 0 16px 29px rgba(11, 139, 65, 0.30);
}

#modalCajaChica #btnAbrirCaja:active:not(:disabled) {
  transform: translateY(0);
}

#modalCajaChica #btnAbrirCaja:focus {
  box-shadow:
    0 12px 24px rgba(11, 139, 65, 0.25),
    0 0 0 4px rgba(22, 163, 74, 0.16);
}

#modalCajaChica #btnAbrirCaja:disabled {
  cursor: not-allowed;
  opacity: 0.58;
  box-shadow: none;
}

#modalCajaChica .caja-seguridad {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 13px;
  color: #959dac;
  font-size: 10px;
  font-weight: 600;
  text-align: center;
}

#modalCajaChica .caja-seguridad i {
  margin-right: 6px;
  color: #7e899a;
}

/* ================= RESPONSIVE ================= */

@media (max-width: 575.98px) {
  #modalCajaChica .modal-dialog {
    width: calc(100% - 20px);
    margin-top: 10px;
    margin-bottom: 10px;
  }

  #modalCajaChica .modal-caja {
    border-radius: 20px;
  }

  #modalCajaChica .caja-premium-header {
    padding: 24px 21px 22px;
  }

  #modalCajaChica .caja-header-icon {
    width: 54px;
    min-width: 54px;
    height: 54px;
    margin-right: 13px;
    border-radius: 16px;
    font-size: 21px;
  }

  #modalCajaChica .caja-premium-title {
    font-size: 18px;
  }

  #modalCajaChica .caja-premium-subtitle {
    font-size: 11px;
  }

  #modalCajaChica .modal-body {
    padding: 20px 18px 22px;
  }

  #modalCajaChica .caja-fecha-card {
    padding: 12px;
  }

  #modalCajaChica .caja-hora {
    display: block;
    margin: 2px 0 0;
    padding: 0;
    border-left: 0;
  }

  #modalCajaChica .caja-monto-section {
    padding: 16px;
  }

  #modalCajaChica #montoApertura {
    padding-left: 12px;
    padding-right: 12px;
    font-size: 23px;
  }

  #modalCajaChica .caja-moneda {
    min-width: 59px;
  }
}
</style>

<!-- =========================================================
     MODAL APERTURA DE CAJA
========================================================== -->

<div
  class="modal fade"
  id="modalCajaChica"
  tabindex="-1"
  role="dialog"
  aria-labelledby="tituloModalCaja"
  aria-hidden="true"
  data-backdrop="static"
  data-keyboard="false"
>
  <div
    class="modal-dialog modal-dialog-centered"
    role="document"
  >
    <div class="modal-content modal-caja">

      <!-- ================= ENCABEZADO ================= -->

      <div class="modal-header caja-premium-header border-0">
        <div class="caja-header-content">

          <div class="caja-header-icon">
            <i class="fas fa-cash-register"></i>
          </div>

          <div class="caja-header-copy">

            <span class="caja-header-badge">
              Inicio de turno
            </span>

            <h4
              class="modal-title caja-premium-title"
              id="tituloModalCaja"
            >
              Apertura de caja
            </h4>

            <p class="caja-premium-subtitle">
              Seleccione su caja e indique el efectivo inicial.
            </p>

          </div>

        </div>
      </div>

      <!-- ================= CUERPO ================= -->

      <div class="modal-body">

        <!-- ================= FECHA ================= -->

        <div class="caja-fecha-card">

          <div class="caja-fecha-icon">
            <i class="far fa-calendar-alt"></i>
          </div>

          <div class="caja-fecha-contenido">

            <span class="caja-meta-label">
              Fecha de apertura
            </span>

            <span class="caja-fecha-principal">

              <?= htmlspecialchars(
                $fechaApertura->format('d/m/Y'),
                ENT_QUOTES,
                'UTF-8'
              ) ?>

              <span class="caja-hora">
                <i class="far fa-clock mr-1"></i>

                <?= htmlspecialchars(
                  $fechaApertura->format('H:i:s'),
                  ENT_QUOTES,
                  'UTF-8'
                ) ?>
              </span>

            </span>

          </div>

        </div>

        <!-- ================= CONTEXTO DE CAJA ================= -->

        <div
          id="bloqueContextoCaja"
          class="alert alert-light border text-left d-none"
          role="alert"
        >
          <div class="d-flex align-items-center">

            <i class="fas fa-cash-register"></i>

            <div>

              <strong id="tituloContextoCaja">
                Configuración de caja
              </strong>

              <div
                id="descripcionContextoCaja"
                class="small text-muted mt-1"
              >
                Verificando modalidad...
              </div>

            </div>

          </div>
        </div>

        <!-- ================= SELECTOR MULTICAJA ================= -->

        <div
          id="grupoSeleccionCaja"
          class="form-group caja-form-group text-left d-none"
        >
          <label
            for="idcajaOperacion"
            class="caja-label"
          >
            <i class="fas fa-store-alt"></i>
            Caja de trabajo
          </label>

          <select
            id="idcajaOperacion"
            class="form-control form-control-lg"
          >
            <option value="">
              Cargando cajas autorizadas...
            </option>
          </select>

          <small
            id="ayudaSeleccionCaja"
            class="form-text text-muted"
          >
            Todas las ventas y movimientos se registrarán en esta caja.
          </small>
        </div>

        <!-- ================= CAJA AUTOMÁTICA ================= -->

        <div
          id="grupoCajaAutomatica"
          class="form-group caja-form-group text-left d-none"
        >
          <label class="caja-label">
            <i class="fas fa-check-circle"></i>
            Caja asignada
          </label>

          <div class="caja-asignada-card">

            <strong id="nombreCajaAutomatica">
              —
            </strong>

            <div class="caja-codigo">
              Código:
              <span id="codigoCajaAutomatica">
                —
              </span>
            </div>

          </div>
        </div>

        <!-- ================= MONTO INICIAL ================= -->

        <div class="caja-monto-section">

          <div class="caja-monto-heading">

            <div>
              <strong>
                Efectivo inicial
              </strong>

              <small>
                Dinero disponible al comenzar el turno
              </small>
            </div>

            <div class="caja-monto-icon">
              <i class="fas fa-wallet"></i>
            </div>

          </div>

          <div class="caja-monto-control">

            <div class="caja-moneda">
              S/
            </div>

            <input
              type="number"
              step="0.01"
              min="0"
              inputmode="decimal"
              id="montoApertura"
              class="form-control"
              placeholder="0.00"
              autocomplete="off"
              aria-label="Monto inicial de apertura"
            >

          </div>

        </div>

        <!-- ================= MENSAJE DE PERMISOS ================= -->

        <div
          id="mensajePermisoCaja"
          class="alert alert-warning text-left d-none"
          role="alert"
        >
          No tiene permiso para abrir esta caja.
        </div>

        <!-- ================= BOTÓN ================= -->

        <div class="caja-boton-contenedor">

          <button
            type="button"
            class="btn btn-success btn-lg btn-block"
            id="btnAbrirCaja"
          >
            <i class="fas fa-lock-open mr-2"></i>
            Iniciar operaciones
          </button>

          <div class="caja-seguridad">
            <i class="fas fa-shield-alt"></i>
            La apertura quedará registrada con su usuario y hora.
          </div>

        </div>

      </div>

    </div>
  </div>
</div>

<!-- =========================================================
     FOOTER
========================================================== -->

<footer class="main-footer">

  <div class="footer-left">
    Todos los derechos reservados &copy; tiquepos.com
  </div>

  <div class="footer-right">
    v. 1.8
  </div>

</footer>

</div>
</div>

<!-- ================= JS BASE ================= -->

<script src="Assets/js/app.min.js"></script>

<!-- ================= DATATABLES ================= -->

<script src="Assets/bundles/datatables/datatables.min.js"></script>

<script
  src="Assets/bundles/datatables/DataTables-1.10.16/js/dataTables.bootstrap4.min.js"
></script>

<!-- ================= OTROS PLUGINS ================= -->

<script src="Assets/bundles/select2/dist/js/select2.full.min.js"></script>

<script src="Assets/bundles/sweetalert/sweetalert.min.js"></script>

<!-- ================= SCRIPTS STISLA ================= -->

<script src="Assets/js/scripts.js"></script>

<!-- ================= JS POR MÓDULO ================= -->

<?php
$url = $_GET['url'] ?? '';

if ($url === 'producto') {
    echo '<script src="Views/modules/scripts/product.js"></script>';
}

if ($url === 'cajachica') {
    echo '<script src="Views/modules/scripts/cajachica.js"></script>';
}
?>

<!-- ================= APERTURA GLOBAL ================= -->

<?php
$rutaAperturaJs = __DIR__ . '/scripts/apertura_caja.js';

$versionAperturaJs = file_exists($rutaAperturaJs)
    ? filemtime($rutaAperturaJs)
    : time();
?>

<script
  src="Views/modules/scripts/apertura_caja.js?v=<?= (int)$versionAperturaJs ?>"
></script>

</body>
</html>