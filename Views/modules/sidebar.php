            <div class="main-sidebar sidebar-style-2">
                <aside id="sidebar-wrapper">
                    <div class="sidebar-brand">
                        <a href="index.html"> <img alt="image" src="Assets/img/logo.png" class="header-logo" /> <span
                                class="logo-name">JapiPOS</span>
                        </a>
                    </div>
                    <!--<div class="sidebar-user">
                        <div class="sidebar-user-picture">
                            <img alt="image" src="Assets/img/users/<?php //echo $_SESSION['imagen']; ?>" class="mr-3 user-img-radious-style user-list-img">
                        </div>
                        <div class="sidebar-user-details">
                            <div class="user-name"><?php //echo $_SESSION['nombre']; ?></div>
                            <div class="user-role"><?php //echo $_SESSION['cargo'];?></div>
                        </div>
                    </div>-->
                    <ul class="sidebar-menu">
                        <li class="menu-header">Menú</li>
                            <?php if($_SESSION['dashboard']==1) {
                                $cd='';
                              ($_GET['url']=='dashboard')?$cd='active':' ';?>
                                <li class="<?php echo $cd;?>"><a class="nav-link" href="dashboard"><i data-feather="monitor"></i><span>Escritorio</span></a></li>
                            <?php }
                            if($_SESSION['almacen']==1){
                                $ca=''; 
                              ($_GET['url']=='category' ||$_GET['url']=='product' ||$_GET['url']=='kardex')?$ca='active':'';?>
                        <li class="dropdown <?php echo $ca;?>">
                            <a href="#" class="nav-link has-dropdown"><i
                                    data-feather="layers"></i><span>Almacen</span></a>
                            <ul class="dropdown-menu">
                                <li><a class="nav-link" href="product">Productos</a></li>
                                <li><a class="nav-link" href="category">Categorías</a></li>
                                <li><a class="nav-link" href="kardex">Kardex</a></li>
                            </ul>
                        </li>
                        <?php }
                        if($_SESSION['compras']==1){
                            $cc=''; 
                            ($_GET['url']=='supplier' ||$_GET['url']=='buy')?$cc='active':' ';?>
                        <li class="dropdown <?php echo $cc;?>">
                            <a href="#" class="nav-link has-dropdown"><i
                                    data-feather="shopping-bag"></i><span>Compras</span></a>
                            <ul class="dropdown-menu">
                                <li><a class="nav-link" href="buy">Ingresos</a></li>
                                 <li><a class="nav-link" href="supplier">Proveedores</a></li>
                            </ul>
                        </li>
                        <?php }
                        if($_SESSION['ventas']==1){
                            $cv='';
                            ($_GET['url']=='customer'||$_GET['url']=='listsales')?$cv='active':' '; ?>
                        <li class="dropdown <?php echo $cv;?>">
                            <a href="#" class="nav-link has-dropdown"><i data-feather="shopping-cart"></i><span>Ventas</span></a>
                            <ul class="dropdown-menu">
                                <li><a class="nav-link" href="newsale">Nueva venta</a></li>
                                <li><a class="nav-link" href="listsales">Ventas</a></li>
                                <li><a class="nav-link" href="customer">Clientes</a></li>
                            </ul>
                        </li>
                        <?php }
                         if($_SESSION['users']==1){
                             $cu=''; 
                              ($_GET['url']=='users' || $_GET['url']=='permissions')?$cu='active':'';?>
                        <li class="dropdown <?php echo $cu;?>">
                            <a href="#" class="nav-link has-dropdown"><i data-feather="users"></i><span>Usuarios</span></a>
                            <ul class="dropdown-menu">
                                <li><a class="nav-link" href="users">Usuarios</a></li>
                                <li><a class="nav-link" href="permissions">Permisos</a></li>
                            </ul>
                        </li>
                        <?php }
                        if($_SESSION['settings']==1){
                            $cs='';
                            ($_GET['url']=='generalsetting' ||$_GET['url']=='vouchersetting'||$_GET['url']=='paymentstype')?$cs='active':' '; ?>
                        <li class="dropdown <?php echo $cs;?>">
                            <a href="#" class="nav-link has-dropdown"><i data-feather="users"></i><span>Configuración</span></a>
                            <ul class="dropdown-menu">
                                <li><a class="nav-link" href="generalsetting">Datos generales</a></li>
                                <li><a class="nav-link" href="vouchersetting">Comprobantes</a></li>
                                <li><a class="nav-link" href="paymentstype">Tipos de pago</a></li>
                            </ul>
                        </li>
                        <?php } ?>
                        <li class="menu-header">Reportes</li>
                        <?php ($_GET['url']=='graphics')?$cg='active':'';?>
                        <li class="<?php echo $cg;?>"><a class="nav-link" href="graphics"><i data-feather="grid"></i><span>Gráficos</span></a></li>
                        <?php if($_SESSION['datebuy']==1){
                            $cdb=''; 
                              ($_GET['url']=='datebuy' ||$_GET['url']=='purchaseproduct')?$cdb='active':'';?>
                        <li class="dropdown <?php echo $cdb;?>">
                            <a href="#" class="nav-link has-dropdown"><i
                                    data-feather="shopping-bag"></i><span>Consulta de compras</span></a>
                            <ul class="dropdown-menu">
                                <li><a class="nav-link" href="datebuy">Compras por fechas</a></li>
                                <li><a class="nav-link" href="purchaseproduct">Compras articulos</a></li>
                            </ul>
                        </li>
                        <?php } ?>
                        <?php if($_SESSION['clientdatesales']==1){
                            $ccds=''; 
                              ($_GET['url']=='clientdatesales' ||$_GET['url']=='salesproduct')?$ccds='active':'';?>
                        <li class="dropdown <?php echo $ccds; ?>">
                            <a href="#" class="nav-link has-dropdown"><i
                                    data-feather="layout"></i><span>Consulta de ventas</span></a>
                            <ul class="dropdown-menu">
                                <li><a class="nav-link" href="clientdatesales">Consulta Ventas</a></li>
                                <li><a class="nav-link" href="salesproduct">Ventas articulo</a></li>
                            </ul>
                        </li>
                        <?php } ?>
                        <li><a class="nav-link" href="#"><i data-feather="grid"></i><span>Ayuda</span></a></li>
                    </ul>
                </aside>
            </div>