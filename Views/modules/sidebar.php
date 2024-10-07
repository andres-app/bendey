<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="dashboard">
                <img alt="image" src="Assets/img/logo.png" class="header-logo" />
                <span class="logo-name">TiquePOS</span>
            </a>
        </div>
        <ul class="sidebar-menu">
            <li class="menu-header">Menú</li>
            <?php if ($_SESSION['dashboard'] == 1) {
                $cd = '';
                ($_GET['url'] == 'dashboard') ? $cd = 'active' : ' '; ?>
                <li class="<?php echo $cd; ?>"><a class="nav-link" href="dashboard"><i data-feather="monitor"></i><span>Escritorio</span></a></li>
            <?php } ?>

            <!-- Módulo de Productos independiente -->
            <?php if ($_SESSION['almacen'] == 1) {
                $cp = '';
                ($_GET['url'] == 'product') ? $cp = 'active' : ''; ?>
                <li class="<?php echo $cp; ?>"><a class="nav-link" href="product"><i data-feather="box"></i><span>Productos</span></a></li>
            <?php } ?>

            <?php if ($_SESSION['compras'] == 1) {
                $cc = '';
                ($_GET['url'] == 'supplier' || $_GET['url'] == 'buy') ? $cc = 'active' : ' '; ?>
                <li class="dropdown <?php echo $cc; ?>">
                    <a href="#" class="nav-link has-dropdown"><i data-feather="shopping-bag"></i><span>Compras</span></a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="buy">Ingresos</a></li>
                        <li><a class="nav-link" href="supplier">Proveedores</a></li>
                    </ul>
                </li>
            <?php } ?>

            <?php if ($_SESSION['ventas'] == 1) {
                $cv = '';
                ($_GET['url'] == 'customer' || $_GET['url'] == 'listsales') ? $cv = 'active' : ' '; ?>
                <li class="dropdown <?php echo $cv; ?>">
                    <a href="#" class="nav-link has-dropdown"><i data-feather="shopping-cart"></i><span>Ventas</span></a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="newsale">Nueva venta</a></li>
                        <li><a class="nav-link" href="listsales">Ventas</a></li>
                        <li><a class="nav-link" href="customer">Clientes</a></li>
                    </ul>
                </li>
            <?php } ?>

            <?php if ($_SESSION['users'] == 1) {
                $cu = '';
                ($_GET['url'] == 'users' || $_GET['url'] == 'permissions') ? $cu = 'active' : ''; ?>
                <li class="dropdown <?php echo $cu; ?>">
                    <a href="#" class="nav-link has-dropdown"><i data-feather="users"></i><span>Usuarios</span></a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="users">Usuarios</a></li>
                        <li><a class="nav-link" href="permissions">Permisos</a></li>
                    </ul>
                </li>
            <?php } ?>

            <!-- Módulo de Almacén sin Productos (Mantenimiento) -->
            <?php if ($_SESSION['almacen'] == 1) {
                $ca = '';
                ($_GET['url'] == 'category' || $_GET['url'] == 'medida') ? $ca = 'active' : ''; ?>
                <li class="dropdown <?php echo $ca; ?>">
                    <a href="#" class="nav-link has-dropdown"><i data-feather="layers"></i><span>Mantenimiento</span></a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="category">Categorías</a></li>
                        <li><a class="nav-link" href="medida">Medidas</a></li>
                    </ul>
                </li>
            <?php } ?>

            <?php if ($_SESSION['settings'] == 1) {
                $cs = '';
                ($_GET['url'] == 'generalsetting' || $_GET['url'] == 'vouchersetting' || $_GET['url'] == 'paymentstype') ? $cs = 'active' : ' '; ?>
                <li class="dropdown <?php echo $cs; ?>">
                    <a href="#" class="nav-link has-dropdown"><i data-feather="settings"></i><span>Configuración</span></a>
                    <ul class="dropdown-menu">
                        <li><a class="nav-link" href="generalsetting">Datos generales</a></li>
                        <li><a class="nav-link" href="vouchersetting">Comprobantes</a></li>
                        <li><a class="nav-link" href="paymentstype">Tipos de pago</a></li>
                    </ul>
                </li>
            <?php } ?>

            <li class="menu-header">Reportes</li>
            <?php $cr = ($_GET['url'] == 'graphics' || $_GET['url'] == 'datebuy' || $_GET['url'] == 'purchaseproduct' || $_GET['url'] == 'clientdatesales' || $_GET['url'] == 'salesproduct' || $_GET['url'] == 'kardex') ? 'active' : ''; ?>
            <li class="dropdown <?php echo $cr; ?>">
                <a href="#" class="nav-link has-dropdown"><i data-feather="grid"></i><span>Reportes</span></a>
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

            <li><a class="nav-link" href="#"><i data-feather="grid"></i><span>Ayuda</span></a></li>
        </ul>
    </aside>
</div>
