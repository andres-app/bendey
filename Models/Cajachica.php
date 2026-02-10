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

    // Verificar si ya hay apertura hoy
    public function obtenerCajaAbiertaHoy()
    {
        $sql = "SELECT *
                FROM caja_apertura
                WHERE fecha = CURDATE()
                AND estado = 'ABIERTA'
                LIMIT 1";
    
        return $this->conexion->getData($sql);
    }
    
    

    // Registrar apertura
    public function registrarApertura($monto, $idusuario)
    {
        $sql = "INSERT INTO caja_apertura (fecha, monto_apertura, idusuario)
                VALUES (CURDATE(), ?, ?)";

        return $this->conexion->setData($sql, [$monto, $idusuario]);
    }

    // Obtener apertura actual
    public function obtenerAperturaHoy()
    {
        $sql = "SELECT * FROM caja_apertura WHERE fecha = CURDATE() LIMIT 1";
        return $this->conexion->getData($sql);
    }

    public function obtenerAperturaPorFecha($fecha)
    {
        $sql = "SELECT monto_apertura, estado
                FROM caja_apertura 
                WHERE fecha = ? 
                LIMIT 1";
    
        return $this->conexion->getData($sql, [$fecha]);
    }
    

    public function cerrarCaja($montoContado, $idusuario)
    {
        $sql = "UPDATE caja_apertura
                SET estado = 'CERRADA'
                WHERE fecha = CURDATE()
                AND idusuario = ?";
    
        $ok = $this->conexion->setData($sql, [$idusuario]);
    
        return [
            'status' => $ok ? 'ok' : 'error'
        ];
    }
    
    

}
