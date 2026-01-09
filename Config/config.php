<?php
// Detectar el entorno automáticamente
if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
    define("ENVIRONMENT", "development");
} else {
    define("ENVIRONMENT", "production");
}

if (ENVIRONMENT == 'development') {
    define("HOST", 'localhost');
    define("DB_USER", 'root');
    define("DB_PASS", '');
    define("DB_NAME", 'bendey');
    define("API_KEY", 'your_development_api_key');
} else {
    define("HOST", 'localhost');
    define("DB_USER", 'root');
    define("DB_PASS", '');
    define("DB_NAME", 'bendey');
    define("API_KEY", 'your_production_api_key');
}

define("PORT", 3306);
define("CHARSET", 'utf8');
define("SYSTEMNAME", "Bendey");

// Crear conexión PDO
try {
    $conn = new PDO(
        "mysql:host=" . HOST . ";dbname=" . DB_NAME . ";charset=" . CHARSET,
        DB_USER,
        DB_PASS
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Error de conexión a la base de datos: " . $e->getMessage());
}
