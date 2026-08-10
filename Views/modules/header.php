<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
    <title>TiquePOS</title>

    <!-- ✅ General CSS de Stisla -->
    <link rel="stylesheet" href="Assets/css/app.min.css">

    <!-- ✅ DataTables (ya estilizado por Stisla) -->
    <link rel="stylesheet" href="Assets/bundles/datatables/datatables.min.css">
    <link rel="stylesheet" href="Assets/bundles/datatables/DataTables-1.10.16/css/dataTables.bootstrap4.min.css">

    <!-- ✅ Select2 integrado con Stisla -->
    <link rel="stylesheet" href="Assets/bundles/select2/dist/css/select2.min.css" />

    <!-- ✅ Template principal -->
    <link rel="stylesheet" href="Assets/css/style.css">
    <link rel="stylesheet" href="Assets/css/components.css">
    <?php
    $rutaCustomCss = rtrim(
        $_SERVER['DOCUMENT_ROOT'],
        '/\\'
    ) . '/Assets/css/custom.css';

    $versionCustomCss = is_file($rutaCustomCss)
        ? filemtime($rutaCustomCss)
        : time();
    ?>

    <link
        rel="stylesheet"
        href="Assets/css/custom.css?v=<?= (int)$versionCustomCss ?>">

    <!-- ✅ Ícono de pestaña -->
    <link rel="shortcut icon" type="image/x-icon" href="Assets/img/favicon.ico" />

    <!-- ✅ jQuery primero -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- ✅ SweetAlert2 (CDN está bien) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- ✅ Select2 de Stisla -->
    <script src="Assets/bundles/select2/dist/js/select2.full.min.js"></script>


    <!-- Notificación de comprobantes pendientes SUNAT -->
    <style>
        .sunat-navbar-item {
            position: relative;
        }

        .sunat-navbar-link {
            position: relative;
            min-width: 46px;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            padding-right: 12px !important;
            padding-left: 12px !important;
        }

        .sunat-navbar-link > i {
            font-size: 19px;
        }

        .sunat-navbar-counter {
            position: absolute;
            top: 5px;
            right: 2px;
            min-width: 19px;
            height: 19px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
            border: 2px solid #6777ef;
            border-radius: 999px;
            color: #ffffff;
            background: #fc544b;
            box-shadow: 0 3px 8px rgba(252, 84, 75, .35);
            font-size: 10px;
            font-weight: 800;
            line-height: 1;
        }

        .sunat-navbar-counter.is-hidden {
            display: none !important;
        }

        @media (max-width: 575.98px) {
            .sunat-navbar-link {
                min-width: 42px;
                padding-right: 8px !important;
                padding-left: 8px !important;
            }

            .sunat-navbar-counter {
                top: 6px;
                right: -1px;
            }
        }
    </style>
</head>

<?php
$urlActual = $_GET['url'] ?? '';

$esVistaPos = in_array(
    $urlActual,
    ['newsale3', 'editsale'],
    true
);

$class = $esVistaPos
    ? 'sidebar-mini pos-navbar-layout'
    : '';

$modoCajaSesion = strtoupper(
    trim((string)($_SESSION['modo_caja'] ?? 'LEGACY'))
);

$idCajaActivaSesion = (int)(
    $_SESSION['idcaja_activa']
    ?? 0
);

$idCajaPreparadaSesion = (int)(
    $_SESSION['idcaja_preparada']
    ?? 0
);

$idAperturaActivaSesion = (int)(
    $_SESSION['idapertura_activa']
    ?? 0
);

$idCajaMostrar = $idCajaActivaSesion > 0
    ? $idCajaActivaSesion
    : $idCajaPreparadaSesion;

$tieneCajaSesion = $idCajaMostrar > 0;

$textoCajaSesion = $tieneCajaSesion
    ? 'Caja #' . $idCajaMostrar
    : 'Sin caja seleccionada';

$puedeVerSunatNavbar =
    (int)($_SESSION['ventas'] ?? 0) === 1;
?>

<body class="<?php echo $class; ?>">
    <div class="loader"></div>
    <div id="app">
        <div class="main-wrapper main-wrapper-1">
            <div class="navbar-bg"></div>
            <nav class="navbar navbar-expand-lg main-navbar">
                <div class="form-inline mr-auto">
                    <ul class="navbar-nav mr-3">
                        <li>
                            <a href="#" data-toggle="sidebar" class="nav-link nav-link-lg collapse-btn">
                                <i data-feather="align-justify"></i>
                            </a>
                        </li>
                        <li>
                            <a href="newsale3" class="nav-link nav-link-lg">
                                <i data-feather="shopping-cart"></i>
                            </a>
                        </li>
                    </ul>
                    <div
                        class="dropdown caja-sesion-dropdown"
                        id="indicadorCajaSesion">

                        <button
                            type="button"
                            class="caja-sesion-navbar"
                            id="btnCajaSesionNavbar"
                            data-toggle="dropdown"
                            aria-haspopup="true"
                            aria-expanded="false"
                            title="<?= htmlspecialchars($textoCajaSesion, ENT_QUOTES, 'UTF-8') ?>"
                            aria-label="Mostrar caja de trabajo">

                            <span class="caja-sesion-icon" aria-hidden="true">
                                <i class="fas fa-cash-register"></i>
                            </span>

                            <span class="caja-sesion-content">
                                <small>Caja de trabajo</small>

                                <strong
                                    id="textoCajaSesionHeader"
                                    class="<?= $tieneCajaSesion ? 'caja-activa' : 'caja-inactiva' ?>">
                                    <?= htmlspecialchars($textoCajaSesion, ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </span>
                        </button>

                        <div
                            class="dropdown-menu caja-sesion-menu"
                            aria-labelledby="btnCajaSesionNavbar">

                            <div class="caja-sesion-menu-label">
                                Caja de trabajo
                            </div>

                            <div class="caja-sesion-menu-estado">
                                <span class="caja-sesion-menu-icon" aria-hidden="true">
                                    <i class="fas fa-cash-register"></i>
                                </span>

                                <strong
                                    id="textoCajaSesionDetalle"
                                    class="<?= $tieneCajaSesion ? 'caja-activa' : 'caja-inactiva' ?>">
                                    <?= htmlspecialchars($textoCajaSesion, ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
                <ul class="navbar-nav navbar-right">
                    <?php if ($puedeVerSunatNavbar): ?>
                        <li class="nav-item sunat-navbar-item">
                            <a
                                href="sunat"
                                class="nav-link nav-link-lg sunat-navbar-link"
                                title="Documentos pendientes de envío a SUNAT"
                                aria-label="Ver documentos pendientes de envío a SUNAT">

                                <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>

                                <span
                                    id="contadorPendientesSunat"
                                    class="sunat-navbar-counter is-hidden"
                                    aria-live="polite"
                                    aria-label="Sin comprobantes pendientes">
                                    0
                                </span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="dropdown">
                        <a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user">
                            <img alt="image" src="Assets/img/users/<?php echo $_SESSION['imagen']; ?>"
                                class="user-img-radious-style">
                        </a>
                        <div class="dropdown-menu dropdown-menu-right pullDown">
                            <div class="dropdown-title usuario-caja-dropdown">
                                <div class="usuario-nombre">
                                    <?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <a href="#" class="dropdown-item has-icon">
                                <i class="far fa-user"></i> Perfil
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="salir" class="dropdown-item has-icon text-danger">
                                <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                            </a>
                        </div>
                    </li>
                </ul>
            </nav>

            <script>
                (function (window, document, $) {
                    'use strict';

                    const textoPrincipal = document.getElementById(
                        'textoCajaSesionHeader'
                    );

                    const textoDetalle = document.getElementById(
                        'textoCajaSesionDetalle'
                    );

                    const botonCaja = document.getElementById(
                        'btnCajaSesionNavbar'
                    );

                    if (!textoPrincipal || !textoDetalle || !botonCaja) {
                        return;
                    }

                    function sincronizarCajaNavbar() {
                        const texto = String(
                            textoPrincipal.textContent || ''
                        ).trim();

                        textoDetalle.textContent = texto;
                        textoDetalle.className = textoPrincipal.className;
                        botonCaja.title = texto;
                    }

                    sincronizarCajaNavbar();

                    if (typeof MutationObserver === 'function') {
                        const observadorCaja = new MutationObserver(
                            sincronizarCajaNavbar
                        );

                        observadorCaja.observe(
                            textoPrincipal,
                            {
                                childList: true,
                                characterData: true,
                                subtree: true,
                                attributes: true,
                                attributeFilter: ['class']
                            }
                        );
                    }

                    if ($ && typeof $.fn !== 'undefined') {
                        $('#indicadorCajaSesion').on(
                            'show.bs.dropdown',
                            sincronizarCajaNavbar
                        );
                    }
                })(window, document, window.jQuery);
            </script>

            <?php if ($puedeVerSunatNavbar): ?>
                <script>
                    (function (window, document, $) {
                        'use strict';

                        const endpointContadorSunat =
                            'Controllers/Sunat.php?op=contarPendientes';

                        const contador = document.getElementById(
                            'contadorPendientesSunat'
                        );

                        if (!contador) {
                            return;
                        }

                        let consultaEnCurso = false;

                        function mostrarCantidadSunat(cantidad) {
                            const total = Number.parseInt(cantidad, 10);
                            const cantidadValida = Number.isFinite(total)
                                ? Math.max(total, 0)
                                : 0;

                            if (cantidadValida === 0) {
                                contador.textContent = '0';
                                contador.classList.add('is-hidden');
                                contador.setAttribute(
                                    'aria-label',
                                    'Sin documentos pendientes de envío a SUNAT'
                                );
                                return;
                            }

                            contador.textContent = cantidadValida > 99
                                ? '99+'
                                : String(cantidadValida);

                            contador.classList.remove('is-hidden');
                            contador.setAttribute(
                                'aria-label',
                                cantidadValida === 1
                                    ? '1 documento pendiente de envío a SUNAT'
                                    : cantidadValida
                                        + ' documentos pendientes de envío a SUNAT'
                            );
                        }

                        async function actualizarContadorSunatNavbar() {
                            if (consultaEnCurso) {
                                return;
                            }

                            consultaEnCurso = true;

                            try {
                                const respuesta = await fetch(
                                    endpointContadorSunat,
                                    {
                                        method: 'GET',
                                        credentials: 'same-origin',
                                        cache: 'no-store',
                                        headers: {
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest'
                                        }
                                    }
                                );

                                if (!respuesta.ok) {
                                    throw new Error(
                                        'No se pudo consultar el contador SUNAT.'
                                    );
                                }

                                const datos = await respuesta.json();

                                if (datos.status !== true) {
                                    throw new Error(
                                        datos.message
                                        || 'La respuesta del contador SUNAT no es válida.'
                                    );
                                }

                                mostrarCantidadSunat(datos.cantidad);
                            } catch (error) {
                                console.error(
                                    '[SUNAT NAVBAR]',
                                    error
                                );
                            } finally {
                                consultaEnCurso = false;
                            }
                        }

                        window.actualizarContadorSunatNavbar =
                            actualizarContadorSunatNavbar;

                        actualizarContadorSunatNavbar();

                        window.setInterval(
                            actualizarContadorSunatNavbar,
                            60000
                        );

                        document.addEventListener(
                            'visibilitychange',
                            function () {
                                if (!document.hidden) {
                                    actualizarContadorSunatNavbar();
                                }
                            }
                        );

                        if ($ && typeof $.fn !== 'undefined') {
                            $(document).ajaxComplete(
                                function (_evento, _xhr, opciones) {
                                    const url = String(
                                        opciones && opciones.url
                                            ? opciones.url
                                            : ''
                                    );

                                    const esOperacionBandejaSunat =
                                        url.indexOf('Controllers/Sunat.php') !== -1
                                        && (
                                            url.indexOf('op=enviarsunat') !== -1
                                            || url.indexOf('op=consultar') !== -1
                                            || url.indexOf('op=getStatus') !== -1
                                        );

                                    const esOperacionNotaCreditoSunat =
                                        url.indexOf('Controllers/CreditNote.php') !== -1
                                        && (
                                            url.indexOf('op=guardar') !== -1
                                            || url.indexOf('op=enviar') !== -1
                                            || url.indexOf('op=consultar') !== -1
                                        );

                                    if (
                                        esOperacionBandejaSunat
                                        || esOperacionNotaCreditoSunat
                                    ) {
                                        window.setTimeout(
                                            actualizarContadorSunatNavbar,
                                            350
                                        );
                                    }
                                }
                            );
                        }
                    })(window, document, window.jQuery);
                </script>
            <?php endif; ?>
