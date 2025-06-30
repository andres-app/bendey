<?php

if (isset($_GET["url"])) {
  if (
    $_GET["url"] == "dashboard"
    || $_GET["url"] == "users"
    || $_GET["url"] == "salir"
    || $_GET["url"] == "category"
    || $_GET["url"] == "atributos"
    || $_GET["url"] == "almacenes"
    || $_GET["url"] == "product"
    || $_GET["url"] == "supplier"
    || $_GET["url"] == "customer"
    || $_GET["url"] == "sunat"
    || $_GET["url"] == "newsale"
    || $_GET["url"] == "newsale2"
    || $_GET["url"] == "listsales"
    || $_GET["url"] == "generalsetting"
    || $_GET["url"] == "vouchersetting"
    || $_GET["url"] == "paymentstype"
    || $_GET["url"] == "datebuy"
    || $_GET["url"] == "clientdatesales"
    || $_GET["url"] == "permissions"
    || $_GET["url"] == "buy"
    || $_GET["url"] == "graphics"
    || $_GET["url"] == "editsale"
    || $_GET["url"] == "editbuy"
    || $_GET["url"] == "salesproduct"
    || $_GET["url"] == "purchaseproduct"
    || $_GET["url"] == "kardex"
    || $_GET["url"] == "medida"
    || $_GET["url"] == "login"
  ) {
    include "modules/" . $_GET["url"] . ".php";
  } else {
    include "modules/404.php";
  }
} else {
  include "modules/login.php";
}