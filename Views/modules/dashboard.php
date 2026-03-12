<?php
ob_start();
session_start();

if (!isset($_SESSION['nombre'])) {
    header("Location: login");
    exit;
}

require_once "header.php";
require_once "sidebar.php";
?>

<style>
    :root {
        --tp-green: #00A46A;
        --tp-pink: #F95F9B;
        --tp-orange: #FF9961;
        --tp-mint: #66CDAA;
        --tp-teal: #4FC3B3;
        --tp-dark: #2D2D2D;
        --tp-bg: #f3f4f6;
        --tp-card: #ffffff;
        --tp-text: #ffffff;
    }

    .dashboard-modern {
        padding: 20px;
        background: #f1f3f6;
        border-radius: 24px;
    }

    .dashboard-toggle {
        width: 52px;
        height: 28px;
        background: var(--tp-green);
        border-radius: 30px;
        position: relative;
        display: inline-block;
    }

    .dashboard-toggle::after {
        content: "";
        width: 22px;
        height: 22px;
        background: #fff;
        border-radius: 50%;
        position: absolute;
        top: 3px;
        left: 27px;
    }

    .avatar-mini {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #ddd;
        object-fit: cover;
        border: 2px solid #fff;
    }

    .tp-stat-card,
    .tp-menu-card {
        border-radius: 20px;
        color: #fff;
        padding: 26px 24px;
        position: relative;
        overflow: hidden;
        min-height: 180px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .tp-stat-card:hover,
    .tp-menu-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 26px rgba(0,0,0,0.12);
    }

    .tp-green { background: var(--tp-green); }
    .tp-pink { background: var(--tp-pink); }
    .tp-orange { background: var(--tp-orange); }
    .tp-mint { background: var(--tp-mint); }
    .tp-teal { background: var(--tp-teal); }
    .tp-dark { background: var(--tp-dark); }

    .tp-stat-value {
        font-size: 46px;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 8px;
        color: #fff;
    }

    .tp-stat-label {
        font-size: 25px;
        font-weight: 500;
        color: rgba(255,255,255,0.95);
        margin-bottom: 0;
    }

    .tp-mini-plus {
        position: absolute;
        right: 22px;
        bottom: 18px;
        width: 42px;
        height: 42px;
        background: rgba(255,255,255,0.9);
        color: rgba(0,0,0,0.25);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        font-weight: 700;
    }

    .tp-menu-card {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 18px;
        text-decoration: none !important;
        min-height: 180px;
    }

    .tp-menu-card i {
        font-size: 42px;
        color: #fff;
    }

    .tp-menu-card span {
        font-size: 24px;
        font-weight: 600;
        color: #fff;
    }

    .tp-chart-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        margin-top: 22px;
        overflow: hidden;
    }

    .tp-chart-card .card-header {
        background: transparent;
        border-bottom: 1px solid #f1f1f1;
        padding: 18px 20px 10px;
    }

    .tp-chart-card .card-header h4 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #2d2d2d;
    }

    .tp-chart-card .card-body {
        padding: 20px;
    }

    @media (max-width: 768px) {
        .tp-stat-value {
            font-size: 34px;
        }

        .tp-stat-label,
        .tp-menu-card span {
            font-size: 20px;
        }

        .tp-menu-card i {
            font-size: 34px;
        }
    }
</style>

<?php if (isset($_SESSION['dashboard']) && $_SESSION['dashboard'] == 1): ?>
    <div class="main-content">
        <section class="section">
            <div class="section-body">
                <div class="dashboard-modern">
                    <div class="row">
                        <!-- VENTAS DEL DIA -->
                        <div class="col-lg-4 col-md-6 col-12 mb-4">
                            <a href="listsales" style="text-decoration:none;">
                                <div class="tp-stat-card tp-green">
                                    <div class="tp-stat-value">S/<span id="tventahoy"></span></div>
                                    <p class="tp-stat-label">Ventas del día</p>
                                    <div class="tp-mini-plus">+</div>
                                </div>
                            </a>
                        </div>

                        <!-- COMPRAS DEL DIA -->
                        <div class="col-lg-4 col-md-6 col-12 mb-4">
                            <a href="buy" style="text-decoration:none;">
                                <div class="tp-stat-card tp-pink">
                                    <div class="tp-stat-value">S/<span id="tcomprahoy"></span></div>
                                    <p class="tp-stat-label">Compras del día</p>
                                    <div class="tp-mini-plus">+</div>
                                </div>
                            </a>
                        </div>

                        <!-- PENDIENTES -->
                        <div class="col-lg-4 col-md-6 col-12 mb-4">
                            <a href="cotizacion" style="text-decoration:none;">
                                <div class="tp-stat-card tp-orange">
                                    <div class="tp-stat-value"><span id="tpendientes">22</span></div>
                                    <p class="tp-stat-label">Pendientes</p>
                                    <div class="tp-mini-plus">+</div>
                                </div>
                            </a>
                        </div>

                        <!-- REPORTES -->
                        <div class="col-lg-4 col-md-6 col-12 mb-4">
                            <a href="clientdatesales" class="tp-menu-card tp-dark">
                                <i class="fas fa-chart-bar"></i>
                                <span>Reportes</span>
                            </a>
                        </div>

                        <!-- PRODUCTOS -->
                        <div class="col-lg-4 col-md-6 col-12 mb-4">
                            <a href="product" class="tp-menu-card tp-teal">
                                <i class="fas fa-clipboard-list"></i>
                                <span>Productos</span>
                            </a>
                        </div>

                        <!-- AJUSTES -->
                        <div class="col-lg-4 col-md-6 col-12 mb-4">
                            <a href="generalsetting" class="tp-menu-card tp-mint">
                                <i class="fas fa-cog"></i>
                                <span>Ajustes</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
<?php else: ?>
    <?php require_once "access.php"; ?>
<?php endif; ?>

<?php require_once "footer.php"; ?>

<script src="Assets/bundles/highcharts/highcharts.js"></script>
<script src="Assets/bundles/chartjs/chart.min.js"></script>
<script src="Views/modules/scripts/dashboard.js"></script>

<?php
ob_end_flush();
?>