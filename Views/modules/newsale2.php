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
            <div class="row g-3">
                <!-- Categorías -->
                <div class="col-lg-9">
                    <div class="row g-3 mb-4" id="categorias">
                        <!-- Categorías dinámicas -->
                    </div>
                    <div class="border-top my-3"></div>
                    <!-- Productos -->
                    <div class="row g-3" id="productos">
                        <!-- Productos dinámicos -->
                    </div>
                </div>

                <!-- Pedido actual -->
                <div class="col-lg-3">
                    <div class="card shadow rounded-3">
                        <div class="card-header bg-primary text-white text-center fw-bold">
                            Pedido actual
                        </div>
                        <div class="card-body" id="pedido_actual">
                            <ul class="list-group mb-3" id="lista_pedido">
                                <!-- Productos agregados -->
                            </ul>
                            <div class="d-flex justify-content-between fw-bold mb-2">
                                <span>Total:</span>
                                <span id="total_pedido">S/ 0.00</span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-success w-100 py-2 fw-bold" id="btn_cobrar">Cobrar</button>
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
    <script src="Views/modules/scripts/newsale2.js"></script>
    <?php
}
ob_end_flush();
?>