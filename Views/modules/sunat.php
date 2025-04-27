<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
    header("location: login");
} else {
    require "header.php";
    require "sidebar.php";

    if ($_SESSION['ventas'] == 1) {
        ?>
        <div class="main-content">
            <section class="section">
                <div class="section-body">
                    <div class="card">
                        <div class="card-header">
                            <h4>Estado de Comprobantes SUNAT</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tbllistado" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Opciones</th>
                                            <th>Comprobante</th>
                                            <th>Cliente</th>
                                            <th>Total</th>
                                            <th>XML</th>
                                            <th>Estado SUNAT</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                    <tfoot>
                                        <tr>
                                            <th>Opciones</th>
                                            <th>Comprobante</th>
                                            <th>Cliente</th>
                                            <th>Total</th>
                                            <th>XML</th>
                                            <th>Estado SUNAT</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </tfoot>
                                </table>
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
    <script src="Views/modules/scripts/sunat.js"></script>
    <?php
}
ob_end_flush();
