<!-- ================= MODAL CAJA CHICA REDISEÑADO ================= -->
<div class="modal fade" id="modalCajaChica"
  tabindex="-1"
  data-backdrop="static"
  data-keyboard="false">

  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content modal-caja">

      <div class="modal-header border-0 text-center">
        <h4 class="modal-title w-100 font-weight-bold">
          APERTURA DE CAJA
        </h4>
      </div>

      <div class="modal-body text-center">

        <div class="mb-3">
          <small class="text-muted">Fecha de apertura</small>
          <div class="font-weight-bold">
            <?php echo date('d/m/Y H:i:s'); ?>
          </div>
        </div>

        <div class="form-group mt-4">
          <label class="font-weight-bold">Ingrese monto inicial</label>

          <div class="input-group input-group-lg mt-2">
            <div class="input-group-prepend">
              <span class="input-group-text font-weight-bold">S/</span>
            </div>

            <input
              type="number"
              step="0.01"
              id="montoApertura"
              class="form-control text-center font-weight-bold"
              placeholder="0.00">
          </div>
        </div>

        <div class="mt-4">
          <button type="button"
            class="btn btn-success btn-lg btn-block"
            id="btnAbrirCaja">
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

<!-- ================= DATATABLES (IMPORTANTE) ================= -->
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
<script src="Views/modules/scripts/apertura_caja.js"></script>

</body>
</html>
