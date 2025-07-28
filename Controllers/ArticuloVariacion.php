<?php
require_once "../Models/ArticuloVariacion.php";
require_once "../Models/VariacionAtributoValor.php";

$variacion = new ArticuloVariacion();
$atributoValor = new VariacionAtributoValor();

// Recibir datos del formulario
$idarticulo = $_POST['idarticulo'];
$combinaciones = json_decode($_POST['combinaciones'], true); // array de combinaciones

foreach ($combinaciones as $combo) {
    // combo = ['sku' => ..., 'precio_venta' => ..., 'precio_compra' => ..., 'stock' => ..., 'atributos' => [idvalor1, idvalor2, ...]]

    // Insertar variación
    $idvariacion = $variacion->insertar(
        $idarticulo,
        $combo['sku'],
        $combo['stock'],
        $combo['precio_venta'],
        $combo['precio_compra'],
        isset($combo['imagen']) ? $combo['imagen'] : null
    );

    // Insertar valores de atributos para esa variación
    foreach ($combo['atributos'] as $idvalor) {
        $atributoValor->insertar($idvariacion, $idvalor);
    }
}

echo json_encode(["status" => "success"]);
