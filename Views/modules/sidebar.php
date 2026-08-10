    <?php
    // URL actual según tu Plantilla.php
    $url = isset($_GET['url']) ? trim($_GET['url']) : '';

    // 👉 detectar si es pantalla POS (Nueva Venta)
    $esPOS = ($url === 'newsale3');
    ?>


    <style>
        /* =========================================================
           MENÚ LATERAL MÁS COMPACTO
           Reduce el espacio vacío del logo y acerca las opciones a
           los bordes sin alterar el comportamiento de Stisla.
        ========================================================== */
        .main-sidebar .tiquepos-sidebar-brand {
            box-sizing: border-box;
            width: 100%;
            height: 106px !important;
            padding: 12px 10px !important;
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
            height: 76px;
            max-width: 100%;
            object-fit: contain;
            object-position: center;
            transition: width .25s ease, height .25s ease, max-width .25s ease;
        }

        body:not(.sidebar-mini) .main-sidebar .sidebar-menu {
            padding: 0 6px 14px !important;
        }

        body:not(.sidebar-mini) .main-sidebar .sidebar-menu > li > a {
            height: 44px;
            margin: 2px 0;
            padding: 0 13px !important;
            border-radius: 9px;
        }

        body:not(.sidebar-mini) .sidebar-style-2 .sidebar-menu > li.active > a {
            padding-left: 13px !important;
        }

        body:not(.sidebar-mini) .main-sidebar .sidebar-menu li a i {
            width: 24px;
            margin-right: 8px;
        }

        body:not(.sidebar-mini) .main-sidebar .sidebar-menu li.menu-header {
            padding: 8px 10px 4px !important;
        }

        body:not(.sidebar-mini) .main-sidebar .sidebar-menu li ul.dropdown-menu li a {
            height: 34px;
            padding-left: 40px !important;
            font-size: 12px;
        }

        /* Estado colapsado del POS/escritorio. */
        body.sidebar-mini .main-sidebar .tiquepos-sidebar-brand {
            height: 70px !important;
            padding: 10px 7px !important;
        }

        body.sidebar-mini .main-sidebar .tiquepos-sidebar-brand > a {
            width: 46px !important;
            height: 46px !important;
            max-width: 100%;
            margin: 0 auto;
        }

        body.sidebar-mini .main-sidebar .tiquepos-sidebar-logo {
            width: 42px !important;
            height: 42px !important;
            max-width: 42px !important;
            max-height: 42px !important;
            object-fit: contain;
        }

        /*
        |--------------------------------------------------------------------------
        | SIDEBAR MINI: FLYOUT SOLO DURANTE INTERACCIÓN
        |--------------------------------------------------------------------------
        */
        body.sidebar-mini
        .main-sidebar
        .sidebar-menu
        > li.dropdown
        > ul.dropdown-menu,
        body.sidebar-mini
        .main-sidebar
        .sidebar-menu
        > li.dropdown.active
        > ul.dropdown-menu,
        body.sidebar-mini
        .main-sidebar
        .sidebar-menu
        > li.dropdown
        > ul.dropdown-menu.show {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        body.sidebar-mini
        .main-sidebar
        .sidebar-menu
        > li.dropdown:hover
        > ul.dropdown-menu,
        body.sidebar-mini
        .main-sidebar
        .sidebar-menu
        > li.dropdown:focus-within
        > ul.dropdown-menu {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        /* Drawer móvil: conserva el ancho y posicionamiento nativos de Stisla. */
        @media (max-width: 1024px) {
            body:not(.sidebar-mini) .main-sidebar .tiquepos-sidebar-brand {
                height: 86px !important;
                padding: 8px 9px !important;
            }

            body:not(.sidebar-mini) .main-sidebar .tiquepos-sidebar-logo {
                height: 60px;
            }

            body:not(.sidebar-mini) .main-sidebar .sidebar-menu {
                padding-right: 4px !important;
                padding-left: 4px !important;
            }

            body:not(.sidebar-mini) .main-sidebar .sidebar-menu > li > a {
                height: 42px;
                margin: 1px 0;
                padding-right: 11px !important;
                padding-left: 11px !important;
                border-radius: 8px;
                font-size: 13px;
            }

            body:not(.sidebar-mini) .sidebar-style-2 .sidebar-menu > li.active > a {
                padding-left: 11px !important;
            }

            body:not(.sidebar-mini) .main-sidebar .sidebar-menu li ul.dropdown-menu li a {
                height: 32px;
                padding-left: 36px !important;
                font-size: 11.5px;
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
                            <span>Inventario</span>
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
                    $ventasActive = !$esPOS && in_array($url, ['listsales', 'cobranzas', 'customer', 'sunat', 'resumenboletas', 'cotizacion'], true);
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
                            <li class="<?= $url == 'cobranzas' ? 'active' : '' ?>">
                                <a class="nav-link" href="cobranzas">
                                    Cobranzas
                                </a>
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
                            <li class="<?= $url == 'resumenboletas' ? 'active' : '' ?>">
                                <a class="nav-link" href="resumenboletas">Resumen Diario Boletas</a>
                            </li>
                        </ul>
                    </li>
                <?php } ?>

                <!-- CONTABILIDAD -->
                <?php
                $contabilidadActive = in_array(
                    $url,
                    [
                        'contabilidad_libro_ventas',
                        'contabilidad_libro_compras',
                        'contabilidad_reporte_ventas',
                        'contabilidad_reporte_compras'
                    ],
                    true
                );
                ?>
                <li class="dropdown <?= $contabilidadActive ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="book-open"></i>
                        <span>Contabilidad</span>
                    </a>

                    <ul class="dropdown-menu <?= $contabilidadActive ? 'show' : '' ?>">
                        <li class="<?= $url == 'contabilidad_libro_ventas' ? 'active' : '' ?>">
                            <a class="nav-link" href="contabilidad_libro_ventas">
                                Libro Elect. Ventas
                            </a>
                        </li>

                        <li class="<?= $url == 'contabilidad_libro_compras' ? 'active' : '' ?>">
                            <a class="nav-link" href="contabilidad_libro_compras">
                                Libro Elect. Compras
                            </a>
                        </li>

                        <li class="<?= $url == 'contabilidad_reporte_ventas' ? 'active' : '' ?>">
                            <a class="nav-link" href="contabilidad_reporte_ventas">
                                Reporte Detall. Ventas
                            </a>
                        </li>

                        <li class="<?= $url == 'contabilidad_reporte_compras' ? 'active' : '' ?>">
                            <a class="nav-link" href="contabilidad_reporte_compras">
                                Reporte Detall. Compras
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- SIRE -->
                <?php
                $sireActive = in_array(
                    $url,
                    [
                        'sire_ventas',
                        'sire_compras'
                    ],
                    true
                );
                ?>
                <li class="dropdown <?= $sireActive ? 'active' : '' ?>">
                    <a href="#" class="nav-link has-dropdown">
                        <i data-feather="file-text"></i>
                        <span>SIRE</span>
                    </a>

                    <ul class="dropdown-menu <?= $sireActive ? 'show' : '' ?>">
                        <li class="<?= $url == 'sire_ventas' ? 'active' : '' ?>">
                            <a class="nav-link" href="sire_ventas">
                                SIRE Ventas
                            </a>
                        </li>

                        <li class="<?= $url == 'sire_compras' ? 'active' : '' ?>">
                            <a class="nav-link" href="sire_compras">
                                SIRE Compras
                            </a>
                        </li>
                    </ul>
                </li>

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


    <script>
        (function (window, document) {
            'use strict';

            /*
            |--------------------------------------------------------------------------
            | PERSISTENCIA SIMPLE DEL SIDEBAR
            |--------------------------------------------------------------------------
            | Stisla sigue siendo quien abre/cierra el sidebar.
            | Aquí NO se cancela ningún click, NO se usa MutationObserver y
            | NO se fuerza sidebar-mini después de que el usuario intenta abrirlo.
            |
            | Solo guardamos el resultado final del botón en escritorio (>1024px).
            */
            const COOKIE_KEY = 'tiquepos_sidebar_desktop';
            const STORAGE_KEY = 'tiquepos.sidebar.desktop';

            function guardarCookie(estado) {
                let cookie =
                    COOKIE_KEY
                    + '='
                    + encodeURIComponent(estado)
                    + '; path=/'
                    + '; max-age=31536000'
                    + '; SameSite=Lax';

                if (window.location.protocol === 'https:') {
                    cookie += '; Secure';
                }

                document.cookie = cookie;
            }

            function guardarEstado(estado) {
                if (
                    estado !== 'collapsed'
                    && estado !== 'expanded'
                ) {
                    return;
                }

                try {
                    window.localStorage.setItem(
                        STORAGE_KEY,
                        estado
                    );
                } catch (error) {
                    // Puede estar deshabilitado; la cookie sigue funcionando.
                }

                guardarCookie(estado);
            }

            document.addEventListener(
                'click',
                function (evento) {
                    const boton = evento.target.closest(
                        '[data-toggle="sidebar"]'
                    );

                    if (!boton) {
                        return;
                    }

                    /*
                     * Hasta 1024px Stisla usa el sidebar como drawer móvil.
                     * No guardamos ese estado como preferencia de escritorio.
                     */
                    if (window.innerWidth <= 1024) {
                        return;
                    }

                    /*
                     * El manejador propio de Stisla está en el botón y se
                     * ejecuta antes de que el evento llegue a document.
                     * Damos un pequeño margen y leemos el estado REAL final.
                     */
                    window.setTimeout(
                        function () {
                            const colapsado =
                                document.body.classList.contains(
                                    'sidebar-mini'
                                );

                            guardarEstado(
                                colapsado
                                    ? 'collapsed'
                                    : 'expanded'
                            );
                        },
                        30
                    );
                },
                false
            );
        })(window, document);
    </script>

