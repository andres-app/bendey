<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuración APISUNAT
|--------------------------------------------------------------------------
| Este archivo debe permanecer únicamente en el servidor.
| No debe enviarse al navegador ni publicarse en GitHub.
*/

return [

    // APISUNAT utiliza la misma URL para desarrollo y producción.
    'base_url' => 'https://back.apisunat.com',

    /*
    |--------------------------------------------------------------------------
    | Credenciales de FELICITY
    |--------------------------------------------------------------------------
    | Pega aquí las credenciales de producción que ya compartiste.
    | No uses las credenciales de la empresa de desarrollo del JSON.
    */
    'persona_id' => '66c107fef16bdf001541ea2a',

    'persona_token' => 'PRD_C2AmVkVMtfFkNsIZ4fnwyk4pqz7Py8QJtihosZ74PET0rBt8Vx5WHZdRnC0I5vMm',

    // Felicity está en producción.
    'production' => true,

    // Tiempo máximo para establecer conexión.
    'connect_timeout' => 15,

    // Tiempo máximo total de la petición.
    'timeout' => 60,

    // Siempre verificar el certificado HTTPS.
    'verify_ssl' => true,
];