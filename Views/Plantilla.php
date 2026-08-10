<?php

if (isset($_GET["url"])) {

    $url = trim((string)$_GET["url"]);

    $rutasPermitidas = [
        "dashboard",
        "users",
        "salir",
        "category",
        "atributos",
        "almacenes",
        "product",
        "supplier",
        "customer",
        "sunat",
        "cotizacion",
        "newsale",
        "newsale2",
        "newsale3",
        "listsales",
        "notacredito",

        // COBRANZAS
        "cobranzas",

        // CONTABILIDAD
        "contabilidad_libro_ventas",
        "contabilidad_libro_compras",
        "contabilidad_reporte_ventas",
        "contabilidad_reporte_compras",

        "generalsetting",
        "cajachica",
        "vouchersetting",
        "paymentstype",
        "paymentformat",
        "datebuy",
        "clientdatesales",
        "permissions",
        "buy",
        "graphics",
        "editsale",
        "editbuy",
        "salesproduct",
        "purchaseproduct",
        "kardex",
        "medida",
        "login"
    ];

    if (in_array($url, $rutasPermitidas, true)) {
        $archivoModulo =
            __DIR__
            . "/modules/"
            . $url
            . ".php";

        if (is_file($archivoModulo)) {
            include $archivoModulo;
        } else {
            include __DIR__ . "/modules/404.php";
        }
    } else {
        include __DIR__ . "/modules/404.php";
    }

} else {
    include __DIR__ . "/modules/login.php";
}
