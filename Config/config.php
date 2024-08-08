<?php
// Detectar el entorno automáticamente
if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
    // Entorno de desarrollo
    define("ENVIRONMENT", "development");
} else {
    // Entorno de producción
    define("ENVIRONMENT", "production");
}

// Configuración específica de cada entorno
if (ENVIRONMENT == 'development') {
    define("HOST", 'localhost');
    define("DB_USER", 'root');
    define("DB_PASS", '');
    define("DB_NAME", 'bendey');
    define("API_KEY", 'your_development_api_key');
} else {
    define("HOST", 'localhost');
    define("DB_USER", 'u274409976_japipos');
    define("DB_PASS", 'Redes2804751$$$');
    define("DB_NAME", 'u274409976_japipos');
    define("API_KEY", 'your_production_api_key');
}

define("PORT", 3306);
define("CHARSET", 'utf8');
define("SYSTEMNAME", "Bendey");
