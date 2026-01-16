<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">

        <!-- LOGO -->
        <div class="sidebar-brand">
            <a href="dashboard">
                <img alt="image" src="Assets/img/logo.png" class="header-logo" />
                <span class="logo-name">TiquePOS</span>
            </a>
        </div>

        <ul class="sidebar-menu">

            <!-- MENÚ -->
            <li class="menu-header">Menú</li>

            <!-- ESCRITORIO -->
            <?php if ($_SESSION['dashboard'] == 1) {
                $cd = ($_GET['url'] == 'dashboard') ? 'active' : '';
            ?>
                <li class="<?= $cd ?>">
                    <a class="nav-link" href="dashboard">
                        <i data-feather="monitor"></i>
                        <span>Escritorio</span>
                    </a>
                </li>
            <?php } ?>

            <!-- PRODUCTOS -->
            <?php if ($_SESSION['almacen'] == 1) {
                $cp = (
                    $_GET['url'] == 'product' ||
                    $_GET['url'] == 'category' ||
                    $_GET['url'] == 'atributos' ||
                    $_GET['url'] == 'almacenes'
                ) ? 'active' : '';
            ?>
                <li class="dropdown <?= $cp ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="box"></i>
                        <span>Productos</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="product">Productos</a></li>
                        <li><a class="nav-link" href="category">Categorías</a></li>
                        <li><a class="nav-link" href="atributos">Atributos</a></li>
                        <li><a class="nav-link" href="almacenes">Almacenes</a></li>
                    </ul>
                </li>
            <?php } ?>

            <!-- COMPRAS -->
            <?php if ($_SESSION['compras'] == 1) {
                $cc = ($_GET['url'] == 'buy' || $_GET['url'] == 'supplier') ? 'active' : '';
            ?>
                <li class="dropdown <?= $cc ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="shopping-bag"></i>
                        <span>Compras</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="buy">Ingresos</a></li>
                        <li><a class="nav-link" href="supplier">Proveedores</a></li>
                    </ul>
                </li>
            <?php } ?>

            <!-- VENTAS -->
            <?php if ($_SESSION['ventas'] == 1) {
                $cv = (
                    $_GET['url'] == 'newsale3' ||
                    $_GET['url'] == 'listsales' ||
                    $_GET['url'] == 'customer' ||
                    $_GET['url'] == 'sunat'
                ) ? 'active' : '';
            ?>
                <li class="dropdown <?= $cv ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="shopping-cart"></i>
                        <span>Ventas</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="newsale3">Nueva venta</a></li>
                        <li><a class="nav-link" href="listsales">Ventas</a></li>
                        <li><a class="nav-link" href="customer">Clientes</a></li>
                        <li><a class="nav-link" href="sunat">SUNAT</a></li>
                    </ul>
                </li>
            <?php } ?>

            <!-- USUARIOS -->
            <?php if ($_SESSION['users'] == 1) {
                $cu = ($_GET['url'] == 'users' || $_GET['url'] == 'permissions') ? 'active' : '';
            ?>
                <li class="dropdown <?= $cu ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="users"></i>
                        <span>Usuarios</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="users">Usuarios</a></li>
                        <li><a class="nav-link" href="permissions">Permisos</a></li>
                    </ul>
                </li>
            <?php } ?>

            <!-- ✅ MANTENIMIENTO (JUSTO ANTES DE CONFIGURACIÓN) -->
            <?php if ($_SESSION['almacen'] == 1) {
                $cm = ($_GET['url'] == 'medida') ? 'active' : '';
            ?>
                <li class="dropdown <?= $cm ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="layers"></i>
                        <span>Mantenimiento</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="medida">Medidas</a></li>
                    </ul>
                </li>
            <?php } ?>

            <!-- CONFIGURACIÓN -->
            <?php if ($_SESSION['settings'] == 1) {
                $cs = (
                    $_GET['url'] == 'generalsetting' ||
                    $_GET['url'] == 'vouchersetting' ||
                    $_GET['url'] == 'paymentstype'
                ) ? 'active' : '';
            ?>
                <li class="dropdown <?= $cs ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="settings"></i>
                        <span>Configuración</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="generalsetting">Datos generales</a></li>
                        <li><a class="nav-link" href="vouchersetting">Comprobantes</a></li>
                        <li><a class="nav-link" href="paymentstype">Tipos de pago</a></li>
                    </ul>
                </li>
            <?php } ?>

            <!-- REPORTES -->
            <li class="menu-header">Reportes</li>
            <?php
            $cr = (
                $_GET['url'] == 'graphics' ||
                $_GET['url'] == 'datebuy' ||
                $_GET['url'] == 'purchaseproduct' ||
                $_GET['url'] == 'clientdatesales' ||
                $_GET['url'] == 'salesproduct' ||
                $_GET['url'] == 'kardex'
            ) ? 'active' : '';
            ?>
            <li class="dropdown <?= $cr ?>">
                <a href="#" class="nav-link has-dropdown">
                    <i data-feather="grid"></i>
                    <span>Reportes</span>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="nav-link" href="graphics">Gráficos</a></li>
                    <?php if ($_SESSION['datebuy'] == 1) { ?>
                        <li><a class="nav-link" href="datebuy">Compras por fechas</a></li>
                        <li><a class="nav-link" href="purchaseproduct">Compras artículos</a></li>
                    <?php } ?>
                    <?php if ($_SESSION['clientdatesales'] == 1) { ?>
                        <li><a class="nav-link" href="clientdatesales">Consulta ventas</a></li>
                        <li><a class="nav-link" href="salesproduct">Ventas artículos</a></li>
                    <?php } ?>
                    <?php if ($_SESSION['almacen'] == 1) { ?>
                        <li><a class="nav-link" href="kardex">Kardex</a></li>
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
