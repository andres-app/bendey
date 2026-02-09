<!-- ================= MODAL CAJA CHICA (DISEÑO PRO) ================= -->
<div class="modal fade" id="modalCajaChica"
     tabindex="-1"
     data-backdrop="static"
     data-keyboard="false">

  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-caja">

      <!-- HEADER -->
      <div class="modal-header border-0">
        <h5 class="modal-title font-weight-bold">CAJA CHICA</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>

      <!-- BODY -->
      <div class="modal-body">

        <!-- FECHAS -->
        <div class="row mb-3">
          <div class="col-md-6">
            <label>Apertura</label>
            <div class="input-group">
              <input type="text" class="form-control" value="24/04/2022 00:00:00" readonly>
              <div class="input-group-append">
                <span class="input-group-text text-success">
                  <i class="fas fa-calendar-plus"></i>
                </span>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <label>Cierre</label>
            <div class="input-group">
              <input type="text" class="form-control" value="24/04/2022 21:00:00" readonly>
              <div class="input-group-append">
                <span class="input-group-text text-success">
                  <i class="fas fa-calendar-check"></i>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- TARJETAS -->
        <div class="row text-center">

          <div class="col-md-6 mb-3">
            <div class="card card-box card-apertura">
              <small>Monto apertura</small>
              <h3>S/ 340.50</h3>
            </div>
          </div>

          <div class="col-md-6 mb-3">
            <div class="card card-box card-ingresos">
              <small>Total ingresos</small>
              <h3>S/ 133.50</h3>
            </div>
          </div>

          <div class="col-md-6 mb-3">
            <div class="card card-box card-egresos">
              <small>Total egresos</small>
              <h3>S/ 0.00</h3>
            </div>
          </div>

          <div class="col-md-6 mb-3">
            <div class="card card-box card-total">
              <small>Total en caja</small>
              <h3>S/ 474.00</h3>
            </div>
          </div>

        </div>

        <hr>

        <!-- BOTONES -->
        <div class="d-flex justify-content-between">
          <button class="btn btn-success btn-lg">
            Crear movimientos
          </button>

          <button class="btn btn-outline-success btn-lg">
            Ver movimientos
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
<script src="Assets/js/scripts.js"></script>
<script src="Assets/bundles/sweetalert/sweetalert.min.js"></script>

<!-- ================= APERTURA DE CAJA (LÓGICA FINAL) ================= -->
<script>
$(document).ready(function () {

    // 1. Verificar apertura apenas carga cualquier vista
    $.getJSON(
        'Controllers/Cajachica.php?op=verificar_apertura',
        function (resp) {

            if (resp.existe === false) {
                $('#modalCajaChica').modal('show');
            }

        }
    );

    // 2. Guardar apertura
    $('#btnAbrirCaja').on('click', function () {

        let monto = $('#montoApertura').val();

        if (!monto || parseFloat(monto) <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Monto inválido',
                text: 'Ingrese un monto de apertura válido'
            });
            return;
        }

        $.post(
            'Controllers/Cajachica.php?op=guardar_apertura',
            { monto },
            function (resp) {

                let r = JSON.parse(resp);

                if (r.status === 'ok') {
                    $('#modalCajaChica').modal('hide');

                    Swal.fire({
                        icon: 'success',
                        title: 'Caja abierta',
                        timer: 1200,
                        showConfirmButton: false
                    });
                }
            }
        );

    });

});
</script>

</body>
</html>
