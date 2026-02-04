<?php
require_once 'Models/EnviarSunat.php';

$sunat = new EnviarSunat();

$res = $sunat->consultarEstado(
    '20609068800',
    '03',
    'B001',
    4
);

echo '<pre>';
print_r($res);
