<?php
require_once __DIR__ . '/../Config/Conexion.php';

class Cajachica
{
    private $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    /**
     * Resumen por comprobante y forma de pago
     */
    public function resumen($fecha_inicio, $fecha_fin, $idusuario = null)
    {
        $sql = "
            SELECT 
                v.tipo_comprobante,
                fp.nombre AS forma_pago,
                SUM(vp.monto) AS total
            FROM venta_pago vp
            INNER JOIN venta v ON v.idventa = vp.idventa
            INNER JOIN forma_pago fp ON fp.idforma_pago = vp.idforma_pago
            WHERE DATE(v.fecha_hora) BETWEEN ? AND ?
              AND v.estado = 'Aceptado'
        ";

        $params = [$fecha_inicio, $fecha_fin];

        if (!empty($idusuario)) {
            $sql .= " AND v.idusuario = ?";
            $params[] = $idusuario;
        }

        $sql .= "
            GROUP BY v.tipo_comprobante, fp.nombre
            ORDER BY v.tipo_comprobante
        ";

        return $this->conexion->getDataAll($sql, $params);
    }

    /**
     * Totales generales
     */
    public function totales($fecha_inicio, $fecha_fin, $idusuario = null)
    {
        $sql = "
            SELECT 
                SUM(vp.monto) AS ingresos
            FROM venta_pago vp
            INNER JOIN venta v ON v.idventa = vp.idventa
            WHERE DATE(v.fecha_hora) BETWEEN ? AND ?
              AND v.estado = 'Aceptado'
        ";

        $params = [$fecha_inicio, $fecha_fin];

        if (!empty($idusuario)) {
            $sql .= " AND v.idusuario = ?";
            $params[] = $idusuario;
        }

        return $this->conexion->getData($sql, $params);
    }
}
