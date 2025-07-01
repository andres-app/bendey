<?php
// Mostrar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 DEBUG PHP</h2>";

// Verificar ruta del archivo Product.php
echo "<h3>1. Probando listado de productos (Product.php)</h3>";
$response = file_get_contents("http://localhost:8080/bendey/Controllers/Product.php?op=listar");
if ($response) {
    $json = json_decode($response, true);
    if ($json) {
        echo "✅ Productos cargados correctamente: <br>";
        echo "<pre>" . print_r(array_slice($json["aaData"], 0, 3), true) . "</pre>";
    } else {
        echo "❌ JSON inválido o error al decodificar respuesta de Product.php:<br>";
        echo "<pre>$response</pre>";
    }
} else {
    echo "❌ No se pudo acceder a Product.php<br>";
}

echo "<hr>";

echo "<h3>2. Probando carga de categorías (Category.php)</h3>";
$options = file_get_contents("http://localhost:8080/bendey/Controllers/Category.php?op=selectCategoria");
if ($options) {
    echo "✅ Categorías cargadas:<br>";
    echo "<pre>" . htmlspecialchars($options) . "</pre>";
} else {
    echo "❌ No se pudo acceder a Category.php<br>";
}

echo "<hr>";

echo "<h3>3. Probando carga de subcategorías (subcategoria.php, con idcategoria=1)</h3>";
$opts = [
    "http" => [
        "method" => "POST",
        "header" => "Content-Type: application/x-www-form-urlencoded",
        "content" => "categoria_id=1"
    ]
];
$context = stream_context_create($opts);
$response = file_get_contents('http://localhost:8080/bendey/Controllers/Subcategoria.php?op=selectSubcategoria');

if ($response) {
    echo "✅ Subcategorías cargadas:<br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
} else {
    echo "❌ Error al obtener subcategorías<br>";
}
?>
