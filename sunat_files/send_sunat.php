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

// Aquí debes llamar a tu función de ENVÍO a SUNAT
// Por ahora haremos una simulación:
echo "<h2>✅ Comprobante enviado correctamente a SUNAT.</h2>";
echo "<a href='sunat'>Regresar</a>";

// 🚀 Luego aquí debes integrar la firma, compresión y envío real al webservice SUNAT
?>
