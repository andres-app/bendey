<?php
require_once "../Config/config.php"; // tu conexión real

class Sunat
{
    public function listar()
    {
        global $conn;
        $sql = "SELECT v.idventa, 
                       CONCAT(v.tipo_comprobante, '-', v.serie_comprobante, '-', v.num_comprobante) AS comprobante,
                       p.nombre AS cliente,
                       v.total_venta AS total,
                       v.fecha_hora AS fecha
                FROM venta v
                INNER JOIN persona p ON v.idcliente = p.idpersona
                ORDER BY v.fecha_hora DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // ⚡ IMPORTANTE
    }
}
?>
