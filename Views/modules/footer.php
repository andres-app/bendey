<?php date_default_timezone_set('America/Lima'); ?>

<!-- ================= MODAL APERTURA DE CAJA ================= -->
<div
  class="modal fade"
  id="modalCajaChica"
  tabindex="-1"
  data-backdrop="static"
  data-keyboard="false"
>
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content modal-caja">

      <div class="modal-header border-0 text-center">
        <h4 class="modal-title w-100 font-weight-bold">
          APERTURA DE CAJA
        </h4>
      </div>

      <div class="modal-body text-center">

        <!-- ================= FECHA ================= -->
        <div class="mb-3">
          <small class="text-muted">
            Fecha de apertura
          </small>

          <div class="font-weight-bold">
            <?php
            $dt = new DateTime(
              'now',
              new DateTimeZone('America/Lima')
            );

            echo $dt->format('d/m/Y H:i:s');
            ?>
          </div>
        </div>

        <!-- ================= CONTEXTO DE CAJA ================= -->
        <div
          id="bloqueContextoCaja"
          class="alert alert-light border text-left d-none"
        >
          <div class="d-flex align-items-start">

            <i
              class="fas fa-cash-register text-primary mr-3 mt-1"
              style="font-size:1.4rem;"
            ></i>

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
          class="form-group text-left d-none"
        >
          <label
            for="idcajaOperacion"
            class="font-weight-bold"
          >
            Seleccione la caja que operará
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
            La apertura y el cierre se registrarán para la caja seleccionada.
          </small>
        </div>

        <!-- ================= CAJA AUTOMÁTICA ================= -->
        <div
          id="grupoCajaAutomatica"
          class="form-group text-left d-none"
        >
          <label class="font-weight-bold">
            Caja asignada
          </label>

          <div
            class="form-control form-control-lg bg-light"
            style="height:auto;"
          >
            <strong id="nombreCajaAutomatica">
              —
            </strong>

            <div class="small text-muted">
              Código:
              <span id="codigoCajaAutomatica">
                —
              </span>
            </div>
          </div>
        </div>

        <!-- ================= MONTO INICIAL ================= -->
        <div class="form-group mt-4">

          <label
            for="montoApertura"
            class="font-weight-bold"
          >
            Ingrese monto inicial
          </label>

          <div class="input-group input-group-lg mt-2">

            <div class="input-group-prepend">
              <span class="input-group-text font-weight-bold">
                S/
              </span>
            </div>

            <input
              type="number"
              step="0.01"
              min="0"
              id="montoApertura"
              class="form-control text-center font-weight-bold"
              placeholder="0.00"
              autocomplete="off"
            >

          </div>
        </div>

        <!-- ================= MENSAJE DE PERMISOS ================= -->
        <div
          id="mensajePermisoCaja"
          class="alert alert-warning text-left d-none"
        >
          No tiene permiso para abrir esta caja.
        </div>

        <!-- ================= BOTÓN ================= -->
        <div class="mt-4">

          <button
            type="button"
            class="btn btn-success btn-lg btn-block"
            id="btnAbrirCaja"
          >
            <i class="fas fa-lock-open mr-1"></i>
            INICIAR CAJA
          </button>

        </div>

      </div>

    </div>
  </div>
</div>
<!-- ================= FIN MODAL ================= -->


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

<script src="Assets/bundles/datatables/DataTables-1.10.16/js/dataTables.bootstrap4.min.js"></script>

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
$rutaAperturaJs =
  __DIR__ . '/scripts/apertura_caja.js';

$versionAperturaJs =
  file_exists($rutaAperturaJs)
    ? filemtime($rutaAperturaJs)
    : time();
?>

<script
  src="Views/modules/scripts/apertura_caja.js?v=<?= (int)$versionAperturaJs ?>"
></script>

</body>

</html>