<style>
/* ==========================================================
   APERTURA DE CAJA
   Estilos limitados al modal #modalCajaChica
========================================================== */

#modalCajaChica {
  --caja-primary: #00a46a;
  --caja-primary-dark: #00754d;
  --caja-success: #00a46a;
  --caja-success-dark: #00754d;
  --caja-title: #18212f;
  --caja-text: #6f7888;
  --caja-border: #e4e8f1;
  --caja-soft: #f6f7fb;
}

#modalCajaChica .modal-dialog {
  width: calc(100% - 28px);
  max-width: 470px;
  margin-left: auto;
  margin-right: auto;
}

#modalCajaChica .modal-caja {
  overflow: hidden;
  border: 0;
  border-radius: 22px;
  background: #ffffff;
  box-shadow:
    0 28px 70px rgba(25, 35, 58, 0.23),
    0 8px 24px rgba(25, 35, 58, 0.10);
}

/* ================= ENCABEZADO ================= */

#modalCajaChica .caja-premium-header {
  position: relative;
  display: block;
  overflow: hidden;
  padding: 26px 28px;
  color: #ffffff;
  text-align: left;
  background:
    linear-gradient(
      135deg,
      #00603f 0%,
      #00a46a 52%,
      #31c18e 100%
    );
}

#modalCajaChica .caja-premium-header::before {
  content: "";
  position: absolute;
  top: -92px;
  right: -58px;
  width: 170px;
  height: 170px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.10);
}

#modalCajaChica .caja-premium-header::after {
  content: "";
  position: absolute;
  right: 55px;
  bottom: -74px;
  width: 110px;
  height: 110px;
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
  display: flex;
  flex: 0 0 56px;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  margin-right: 15px;
  border: 1px solid rgba(255, 255, 255, 0.22);
  border-radius: 17px;
  color: #ffffff;
  font-size: 22px;
  background: rgba(255, 255, 255, 0.16);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.18),
    0 10px 22px rgba(0, 96, 63, 0.16);
}

#modalCajaChica .caja-header-copy {
  min-width: 0;
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
  color: rgba(255, 255, 255, 0.80);
  font-size: 12px;
  line-height: 1.45;
}

/* ================= CUERPO ================= */

#modalCajaChica .modal-body {
  padding: 25px 28px 28px;
  text-align: left;
}

/* ================= CAMPOS ================= */

#modalCajaChica .caja-form-group {
  margin-bottom: 0;
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
  height: 52px;
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
  box-shadow: 0 0 0 4px rgba(0, 164, 106, 0.12);
}

#modalCajaChica #idcajaOperacion:disabled {
  cursor: wait;
  color: #8a93a3;
  background: #f6f7fa;
}

/* ================= CAJA ASIGNADA ================= */

#modalCajaChica .caja-asignada-card {
  position: relative;
  min-height: 67px;
  padding: 13px 48px 13px 15px;
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
  font-size: 20px;
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

/* ================= APERTURA ================= */

#modalCajaChica #bloqueAperturaCaja {
  margin-top: 19px;
}

#modalCajaChica .caja-monto-section {
  padding: 18px;
  border: 1px solid #e2e6ef;
  border-radius: 17px;
  background: #fafbfe;
}

#modalCajaChica .caja-monto-heading {
  margin-bottom: 11px;
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

#modalCajaChica .caja-monto-control {
  display: flex;
  align-items: stretch;
  min-height: 61px;
  overflow: hidden;
  border: 2px solid #e1e5ee;
  border-radius: 14px;
  background: #ffffff;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

#modalCajaChica .caja-monto-control:focus-within {
  border-color: var(--caja-primary);
  box-shadow: 0 0 0 5px rgba(0, 164, 106, 0.10);
}

#modalCajaChica .caja-moneda {
  display: flex;
  flex: 0 0 64px;
  align-items: center;
  justify-content: center;
  border-right: 1px solid #e2e5f4;
  color: var(--caja-primary-dark);
  font-size: 18px;
  font-weight: 900;
  background: #f1f3ff;
}

#modalCajaChica #montoApertura {
  height: 59px;
  padding: 7px 16px;
  border: 0;
  border-radius: 0;
  color: #18212f;
  font-size: 24px;
  font-weight: 800;
  text-align: right !important;
  background: #ffffff;
  box-shadow: none;
}

#modalCajaChica #montoApertura::placeholder {
  color: #c1c7d2;
  opacity: 1;
}

#modalCajaChica #montoApertura:focus {
  outline: none;
  box-shadow: none;
}

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
  padding: 12px 13px;
  border: 0;
  border-radius: 12px;
  font-size: 12px;
  line-height: 1.45;
}

#modalCajaChica #mensajePermisoCaja.alert-info {
  color: #31527a;
  background: #edf5ff;
}

#modalCajaChica #mensajePermisoCaja.alert-warning {
  color: #7a5909;
  background: #fff5d8;
}

#modalCajaChica #mensajePermisoCaja.alert-danger {
  color: #8c3038;
  background: #ffedef;
}

#modalCajaChica #mensajePermisoCaja.alert-success {
  color: #1f6d3d;
  background: #ebf8f0;
}

/* ================= BOTÓN ================= */

#modalCajaChica .caja-boton-contenedor {
  margin-top: 15px;
}

#modalCajaChica #btnAbrirCaja {
  position: relative;
  min-height: 55px;
  overflow: hidden;
  border: 0;
  border-radius: 14px;
  color: #ffffff;
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0.2px;
  background:
    linear-gradient(
      135deg,
      var(--caja-success) 0%,
      var(--caja-success-dark) 100%
    );
  box-shadow: 0 12px 23px rgba(11, 139, 65, 0.23);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    opacity 0.2s ease;
}

#modalCajaChica #btnAbrirCaja::before {
  content: "";
  position: absolute;
  top: -70px;
  right: -24px;
  width: 103px;
  height: 103px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.13);
}

#modalCajaChica #btnAbrirCaja:hover:not(:disabled) {
  color: #ffffff;
  transform: translateY(-1px);
  box-shadow: 0 15px 27px rgba(11, 139, 65, 0.28);
}

#modalCajaChica #btnAbrirCaja:disabled {
  cursor: not-allowed;
  opacity: 0.60;
  box-shadow: none;
}

/* ================= RESPONSIVE ================= */

@media (max-width: 575.98px) {
  #modalCajaChica .modal-dialog {
    width: calc(100% - 20px);
    margin-top: 10px;
    margin-bottom: 10px;
  }

  #modalCajaChica .modal-caja {
    border-radius: 19px;
  }

  #modalCajaChica .caja-premium-header {
    padding: 22px 20px;
  }

  #modalCajaChica .caja-header-icon {
    flex-basis: 50px;
    width: 50px;
    height: 50px;
    margin-right: 12px;
    border-radius: 15px;
    font-size: 20px;
  }

  #modalCajaChica .caja-premium-title {
    font-size: 18px;
  }

  #modalCajaChica .caja-premium-subtitle {
    font-size: 11px;
  }

  #modalCajaChica .modal-body {
    padding: 21px 18px 23px;
  }

  #modalCajaChica .caja-monto-section {
    padding: 15px;
  }

  #modalCajaChica .caja-moneda {
    flex-basis: 58px;
  }

  #modalCajaChica #montoApertura {
    padding-right: 12px;
    padding-left: 12px;
    font-size: 22px;
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

            <h4
              class="modal-title caja-premium-title"
              id="tituloModalCaja"
            >
              Apertura de caja
            </h4>

            <p class="caja-premium-subtitle">
              Selecciona la caja donde comenzarás a trabajar.
            </p>

          </div>

        </div>
      </div>

      <!-- ================= CUERPO ================= -->

      <div class="modal-body">

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
            Selecciona tu caja
          </label>

          <select
            id="idcajaOperacion"
            class="form-control form-control-lg"
          >
            <option value="">
              Cargando cajas...
            </option>
          </select>
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

        <!-- ================= MONTO Y BOTÓN ================= -->

        <div
          id="bloqueAperturaCaja"
          class="d-none"
        >

          <div class="caja-monto-section">

            <div class="caja-monto-heading">
              <strong>
                Efectivo inicial
              </strong>

              <small>
                Ingresa el dinero con el que inicia la caja.
              </small>
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
                disabled
              >

            </div>

          </div>

          <div class="caja-boton-contenedor">

            <button
              type="button"
              class="btn btn-success btn-lg btn-block"
              id="btnAbrirCaja"
              disabled
            >
              <i class="fas fa-lock-open mr-2"></i>
              Abrir caja
            </button>

          </div>

        </div>

        <!-- ================= MENSAJES ================= -->

        <div
          id="mensajePermisoCaja"
          class="alert d-none"
          role="alert"
        ></div>

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
    $rutaCajaChicaJs = __DIR__ . '/scripts/cajachica.js';
    $versionCajaChicaJs = is_file($rutaCajaChicaJs)
        ? filemtime($rutaCajaChicaJs)
        : time();

    echo '<script src="Views/modules/scripts/cajachica.js?v='
        . (int)$versionCajaChicaJs
        . '"></script>';
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
