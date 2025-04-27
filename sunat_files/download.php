<?php
require_once "Config/Conexion.php";

// Obtener ID de la venta
$idventa = intval($_GET['idventa'] ?? 0);

if ($idventa <= 0) {
    die("❌ Venta no válida");
}

// Buscar datos de la venta
$stmt = $conn->prepare("SELECT serie_comprobante, num_comprobante, tipo_comprobante FROM venta WHERE idventa = ?");
$stmt->execute([$idventa]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    die("❌ Venta no encontrada");
}

// Construir nombre de archivo XML
$ruc = "20123456789"; // ⚠️ Cambia aquí tu RUC real
$serie = $venta['serie_comprobante'];
$numero = str_pad($venta['num_comprobante'], 8, "0", STR_PAD_LEFT);
$nombreXML = "$ruc-01-$serie-$numero.xml";

// Ruta del archivo
$ruta = __DIR__ . "/sunat/xml/$nombreXML";

// Verificar si existe
if (!file_exists($ruta)) {
    die("❌ Archivo XML no encontrado");
}

// Descargar archivo
header('Content-Description: File Transfer');
header('Content-Type: application/xml');
header('Content-Disposition: attachment; filename="'.basename($nombreXML).'"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
exit;
?>
