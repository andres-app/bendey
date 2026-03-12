<?php
require_once __DIR__ . '/../Config/Conexion.php';

class Cajachica
{
    private $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

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

    public function obtenerCajaAbiertaHoy()
    {
        $fecha = date('Y-m-d');

        $sql = "SELECT *
                FROM caja_apertura
                WHERE fecha = ?
                  AND estado = 'ABIERTA'
                LIMIT 1";

        return $this->conexion->getData($sql, [$fecha]);
    }

    public function registrarApertura($monto, $idusuario)
    {
        $fecha = date('Y-m-d');
        $createdAt = date('Y-m-d H:i:s');

        $sql = "INSERT INTO caja_apertura (fecha, monto_apertura, idusuario, created_at)
                VALUES (?, ?, ?, ?)";

        return $this->conexion->setData($sql, [$fecha, $monto, $idusuario, $createdAt]);
    }

    public function obtenerAperturaHoy()
    {
        $fecha = date('Y-m-d');

        $sql = "SELECT * 
                FROM caja_apertura 
                WHERE fecha = ? 
                LIMIT 1";

        return $this->conexion->getData($sql, [$fecha]);
    }

    public function obtenerAperturaPorFecha($fecha)
    {
        $sql = "SELECT monto_apertura, estado, created_at, fecha_cierre
                FROM caja_apertura 
                WHERE fecha = ? 
                LIMIT 1";

        return $this->conexion->getData($sql, [$fecha]);
    }

    public function cerrarCaja($montoContado, $idusuario)
    {
        $fecha = date('Y-m-d');
        $fechaCierre = date('Y-m-d H:i:s');

        $sql = "UPDATE caja_apertura
                SET estado = 'CERRADA',
                    fecha_cierre = ?
                WHERE fecha = ?
                  AND idusuario = ?";

        $ok = $this->conexion->setData($sql, [$fechaCierre, $fecha, $idusuario]);

        return [
            'status' => $ok ? 'ok' : 'error'
        ];
    }
}