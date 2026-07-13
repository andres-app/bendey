<?php
// URL actual según tu Plantilla.php
$url = isset($_GET['url']) ? trim($_GET['url']) : '';

// 👉 detectar si es pantalla POS (Nueva Venta)
$esPOS = ($url === 'newsale3');
?>


<style>
    /* =========================================================
       LOGO DEL SIDEBAR
       Evita que la imagen sobresalga al colapsar el menú.
       ========================================================= */
    .main-sidebar .tiquepos-sidebar-brand {
        box-sizing: border-box;
        width: 100%;
        height: 150px !important;
        padding: 20px 12px !important;
        display: flex !important;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        transition: height .25s ease, padding .25s ease;
    }

    .main-sidebar .tiquepos-sidebar-brand > a {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .main-sidebar .tiquepos-sidebar-logo {
        display: block;
        width: auto;
        height: 105px;
        max-width: 100%;
        object-fit: contain;
        object-position: center;
        transition: width .25s ease, height .25s ease, max-width .25s ease;
    }

    /* Estado colapsado de Stisla */
    body.sidebar-mini .main-sidebar .tiquepos-sidebar-brand {
        height: 78px !important;
        padding: 11px 7px !important;
    }

    body.sidebar-mini .main-sidebar .tiquepos-sidebar-brand > a {
        width: 50px !important;
        height: 50px !important;
        max-width: 100%;
        margin: 0 auto;
    }

    body.sidebar-mini .main-sidebar .tiquepos-sidebar-logo {
        width: 46px !important;
        height: 46px !important;
        max-width: 46px !important;
        max-height: 46px !important;
        object-fit: contain;
    }

    /* Protección adicional para pantallas pequeñas */
    @media (max-width: 767.98px) {
        body:not(.sidebar-mini) .main-sidebar .tiquepos-sidebar-brand {
            height: 130px !important;
            padding: 15px 12px !important;
        }

        body:not(.sidebar-mini) .main-sidebar .tiquepos-sidebar-logo {
            height: 92px;
        }
    }
</style>

<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">

        <!-- LOGO EMPRESA -->
        <div class="sidebar-brand tiquepos-sidebar-brand">
            <a href="dashboard" aria-label="Ir al escritorio">
                <img
                    class="tiquepos-sidebar-logo"
                    src="Assets/img/tiquepos_logo.png"
                    alt="TiquePOS">
            </a>
        </div>

        <ul class="sidebar-menu">

            <li class="menu-header">Menú</li>

            <!-- ESCRITORIO -->
            <?php if (!empty($_SESSION['dashboard']) && $_SESSION['dashboard'] == 1) { ?>
                <li class="<?= $url == 'dashboard' ? 'active' : '' ?>">
                    <a class="nav-link" href="dashboard">
                        <i data-feather="monitor"></i>
                        <span>Escritorio</span>
                    </a>
                </li>
            <?php } ?>

            <!-- PRODUCTOS -->
            <?php if (!empty($_SESSION['almacen']) && $_SESSION['almacen'] == 1) {
                $productosActive = in_array($url, ['product', 'category', 'atributos', 'almacenes']);
            ?>
                <li class="dropdown <?= $productosActive ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="box"></i>
                        <span>Productos</span>
                    </a>

                    <ul class="dropdown-menu <?= $productosActive ? 'show' : '' ?>">
                        <li class="<?= $url == 'product' ? 'active' : '' ?>">
                            <a class="nav-link" href="product">Productos</a>
                        </li>
                        <li class="<?= $url == 'category' ? 'active' : '' ?>">
                            <a class="nav-link" href="category">Categorías</a>
                        </li>
                        <li class="<?= $url == 'atributos' ? 'active' : '' ?>">
                            <a class="nav-link" href="atributos">Atributos</a>
                        </li>
                        <li class="<?= $url == 'almacenes' ? 'active' : '' ?>">
                            <a class="nav-link" href="almacenes">Almacenes</a>
                        </li>
                    </ul>
                </li>
            <?php } ?>

            <!-- COMPRAS -->
            <?php if (!empty($_SESSION['compras']) && $_SESSION['compras'] == 1) {
                $comprasActive = in_array($url, ['buy', 'supplier']);
            ?>
                <li class="dropdown <?= $comprasActive ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="shopping-bag"></i>
                        <span>Compras</span>
                    </a>

                    <ul class="dropdown-menu <?= $comprasActive ? 'show' : '' ?>">
                        <li class="<?= $url == 'buy' ? 'active' : '' ?>">
                            <a class="nav-link" href="buy">Ingresos</a>
                        </li>
                        <li class="<?= $url == 'supplier' ? 'active' : '' ?>">
                            <a class="nav-link" href="supplier">Proveedores</a>
                        </li>
                    </ul>
                </li>
            <?php } ?>

            <!-- VENTAS -->
            <?php if (!empty($_SESSION['ventas']) && $_SESSION['ventas'] == 1) {
                $ventasActive = !$esPOS && in_array($url, ['listsales', 'customer', 'sunat', 'cotizacion']);
            ?>
                <li class="dropdown <?= $ventasActive ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="shopping-cart"></i>
                        <span>Ventas</span>
                    </a>

                    <ul class="dropdown-menu <?= $ventasActive ? 'show' : '' ?>">
                        <li class="<?= $url == 'newsale3' ? 'active' : '' ?>">
                            <a class="nav-link" href="newsale3">Nueva venta</a>
                        </li>
                        <li class="<?= $url == 'listsales' ? 'active' : '' ?>">
                            <a class="nav-link" href="listsales">Ventas</a>
                        </li>
                        <li class="<?= $url == 'cotizacion' ? 'active' : '' ?>">
                            <a class="nav-link" href="cotizacion">Cotización</a>
                        </li>
                        <li class="<?= $url == 'customer' ? 'active' : '' ?>">
                            <a class="nav-link" href="customer">Clientes</a>
                        </li>
                        <li class="<?= $url == 'sunat' ? 'active' : '' ?>">
                            <a class="nav-link" href="sunat">SUNAT</a>
                        </li>
                    </ul>
                </li>
            <?php } ?>

            <!-- USUARIOS -->
            <?php if (!empty($_SESSION['users']) && $_SESSION['users'] == 1) {
                $usuariosActive = in_array($url, ['users', 'permissions']);
            ?>
                <li class="dropdown <?= $usuariosActive ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="users"></i>
                        <span>Usuarios</span>
                    </a>

                    <ul class="dropdown-menu <?= $usuariosActive ? 'show' : '' ?>">
                        <li class="<?= $url == 'users' ? 'active' : '' ?>">
                            <a class="nav-link" href="users">Usuarios</a>
                        </li>
                        <li class="<?= $url == 'permissions' ? 'active' : '' ?>">
                            <a class="nav-link" href="permissions">Permisos</a>
                        </li>
                    </ul>
                </li>
            <?php } ?>

            <!-- CAJA CHICA -->
            <li class="<?= $url == 'cajachica' ? 'active' : '' ?>">
                <a class="nav-link" href="cajachica">
                    <i data-feather="dollar-sign"></i>
                    <span>Caja Chica</span>
                </a>
            </li>


            <!-- MANTENIMIENTO -->
            <?php if (!empty($_SESSION['almacen']) && $_SESSION['almacen'] == 1) {
                $mantActive = ($url == 'medida');
            ?>
                <li class="dropdown <?= $mantActive ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="layers"></i>
                        <span>Mantenimiento</span>
                    </a>

                    <ul class="dropdown-menu <?= $mantActive ? 'show' : '' ?>">
                        <li class="<?= $url == 'medida' ? 'active' : '' ?>">
                            <a class="nav-link" href="medida">Medidas</a>
                        </li>
                    </ul>
                </li>
            <?php } ?>

            <!-- CONFIGURACIÓN -->
            <?php if (!empty($_SESSION['settings']) && $_SESSION['settings'] == 1) {
                $configActive = in_array($url, ['generalsetting', 'vouchersetting', 'paymentstype', 'paymentformat']);
            ?>
                <li class="dropdown <?= $configActive ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="settings"></i>
                        <span>Configuración</span>
                    </a>

                    <ul class="dropdown-menu <?= $configActive ? 'show' : '' ?>">
                        <li class="<?= $url == 'generalsetting' ? 'active' : '' ?>">
                            <a class="nav-link" href="generalsetting">Configuración Empresa</a>
                        </li>
                        <li class="<?= $url == 'vouchersetting' ? 'active' : '' ?>">
                            <a class="nav-link" href="vouchersetting">Comprobantes</a>
                        </li>
                        <li class="<?= $url == 'paymentstype' ? 'active' : '' ?>">
                            <a class="nav-link" href="paymentstype">Tipos de pago</a>
                        </li>
                        <li class="<?= $url == 'paymentformat' ? 'active' : '' ?>">
                            <a class="nav-link" href="paymentformat">Forma de pago</a>
                        </li>
                    </ul>
                </li>
            <?php } ?>

            <li class="menu-header">Reportes</li>

            <?php
            $reportesActive = in_array($url, [
                'graphics',
                'datebuy',
                'purchaseproduct',
                'clientdatesales',
                'salesproduct',
                'kardex'
            ]);
            ?>
            <li class="dropdown <?= $reportesActive ? 'active' : '' ?>">
                <a href="#" class="nav-link has-dropdown">
                    <i data-feather="grid"></i>
                    <span>Reportes</span>
                </a>

                <ul class="dropdown-menu <?= $reportesActive ? 'show' : '' ?>">
                    <li class="<?= $url == 'graphics' ? 'active' : '' ?>">
                        <a class="nav-link" href="graphics">Gráficos</a>
                    </li>

                    <?php if (!empty($_SESSION['datebuy']) && $_SESSION['datebuy'] == 1) { ?>
                        <li class="<?= $url == 'datebuy' ? 'active' : '' ?>">
                            <a class="nav-link" href="datebuy">Compras por fechas</a>
                        </li>
                        <li class="<?= $url == 'purchaseproduct' ? 'active' : '' ?>">
                            <a class="nav-link" href="purchaseproduct">Compras artículos</a>
                        </li>
                    <?php } ?>

                    <?php if (!empty($_SESSION['clientdatesales']) && $_SESSION['clientdatesales'] == 1) { ?>
                        <li class="<?= $url == 'clientdatesales' ? 'active' : '' ?>">
                            <a class="nav-link" href="clientdatesales">Reporte de ventas</a>
                        </li>
                        <li class="<?= $url == 'salesproduct' ? 'active' : '' ?>">
                            <a class="nav-link" href="salesproduct">Ventas artículos</a>
                        </li>
                    <?php } ?>

                    <?php if (!empty($_SESSION['almacen']) && $_SESSION['almacen'] == 1) { ?>
                        <li class="<?= $url == 'kardex' ? 'active' : '' ?>">
                            <a class="nav-link" href="kardex">Kardex</a>
                        </li>
                    <?php } ?>
                </ul>
            </li>

            <!-- AYUDA -->
            <li>
                <a class="nav-link" href="#">
                    <i data-feather="help-circle"></i>
                    <span>Ayuda</span>
                </a>
            </li>

        </ul>
    </aside>
</div>