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

    <!-- ✅ Ícono de pestaña -->
    <link rel="shortcut icon" type="image/x-icon" href="Assets/img/favicon.ico" />

    <!-- ✅ jQuery primero -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- ✅ SweetAlert2 (CDN está bien) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- ✅ Select2 de Stisla -->
    <script src="Assets/bundles/select2/dist/js/select2.full.min.js"></script>
</head>

<?php
$class = ($_GET["url"] == "newsale3" || $_GET["url"] == "editsale") ? 'sidebar-mini' : '';
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
                </div>
                <ul class="navbar-nav navbar-right">
                    <li class="dropdown">
                        <a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user">
                            <img alt="image" src="Assets/img/users/<?php echo $_SESSION['imagen']; ?>"
                                class="user-img-radious-style">
                        </a>
                        <div class="dropdown-menu dropdown-menu-right pullDown">
                            <div class="dropdown-title"><?php echo $_SESSION['nombre']; ?></div>
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