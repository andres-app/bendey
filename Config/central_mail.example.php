<?php
// Copiar a Config/central_mail.php SOLO en la instalación administrada.
// No versionar ni colocar credenciales en repositorios públicos.
return [
    'enabled' => false, // true únicamente después de configurar y probar DNS/SPF/DKIM
    'host' => '', // SMTP del proveedor transaccional de TiquePOS
    'port' => 587,
    'encryption' => 'tls',
    'username' => '',
    'password' => '',
    'from_email' => 'comprobantes@tiquepos.com', // verificar el dominio primero
];
