<?php

ob_start();
session_start();

if (!isset($_SESSION['nombre'])) {
  header("location: login");
} else {

  require "header.php";
  require "sidebar.php";

  if ($_SESSION['almacen'] == 1) {
?>
    <!-- Main Content -->
    <div class="main-content">
      <section class="section">
        <div class="section-body">
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h4>Consulta de Ventas por Fecha</h4>
                </div>

                <div class="card-body">
                  <div class="table-responsive" id="listadoregistros">

                    <div class="row">
                      <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                        <label>Fecha Inicio</label>
                        <input
                          type="date"
                          class="form-control"
                          name="fecha_inicio"
                          id="fecha_inicio"
                          value="<?php echo date("Y-m-d"); ?>">
                      </div>

                      <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                        <label>Fecha Fin</label>
                        <input
                          type="date"
                          class="form-control"
                          name="fecha_fin"
                          id="fecha_fin"
                          value="<?php echo date("Y-m-d"); ?>">
                      </div>

                      <!-- Rangos rápidos + botón -->
                      <div class="form-group col-lg-6 col-md-6 col-sm-12 col-xs-12">
                        <label>Rangos rápidos</label>
                        <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                          <button type="button" class="btn btn-light btn-sm" onclick="setRange('today')">Hoy</button>
                          <button type="button" class="btn btn-light btn-sm" onclick="setRange('7')">7 días</button>
                          <button type="button" class="btn btn-light btn-sm" onclick="setRange('30')">30 días</button>
                          <button type="button" class="btn btn-light btn-sm" onclick="setRange('month')">Este mes</button>
                          <button type="button" class="btn btn-light btn-sm" onclick="setRange('prevmonth')">Mes pasado</button>

                          <div class="ml-auto">
                            <button id="btnMostrar" type="button" class="btn btn-success" onclick="listar()">
                              Mostrar
                            </button>
                          </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                          Recomendado: máximo 60 días para evitar lentitud.
                        </small>
                      </div>
                    </div>

                    <table id="tbllistado" class="table table-striped table-hover text-nowrap" style="width:100%;">
                      <thead>
                        <tr>
                          <th>Fecha</th>
                          <th>Usuario</th>
                          <th>Cliente</th>
                          <th>Comprobante</th>
                          <th>Número</th>
                          <th>Total Ventas</th>
                          <th>Impuesto</th>
                          <th>Estado</th>
                        </tr>
                      </thead>
                    </table>

                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </section>
    </div>

  <?php
  } else {
    require "access.php";
  }

  require "footer.php";
  ?>
  <script src="Views/modules/scripts/clientdatesales.js"></script>
<?php
}

ob_end_flush();
?>