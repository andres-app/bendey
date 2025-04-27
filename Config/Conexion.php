<?php
// Config/Conexion.php

require_once "Config.php"; // Incluimos la conexión a la base de datos

// Ejecutar una consulta y devolver múltiples filas (PDOStatement)
function ejecutarConsulta($sql)
{
    global $conn;
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt;
}

// Ejecutar una consulta y devolver una sola fila
function ejecutarConsultaSimpleFila($sql)
{
    global $conn;
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC); // Trae una sola fila como array asociativo
}
?>
