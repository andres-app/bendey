<?php
require_once __DIR__ . '/../Config/Conexion.php';

class Sunat
{
    private Conexion $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    public function listar(): array
    {
        $sql = "SELECT 
                    v.idventa,
                    CONCAT(v.tipo_comprobante,'-',v.serie_comprobante,'-',v.num_comprobante) AS comprobante,
                    p.nombre AS cliente,
                    v.total_venta AS total,
                    vs.xml,
                    vs.cdr,
                    vs.estado_sunat,
                    vs.mensaje_sunat,
                    DATE(v.fecha_hora) AS fecha
                FROM venta v
                INNER JOIN persona p ON v.idcliente = p.idpersona
                LEFT JOIN venta_sunat vs ON v.idventa = vs.idventa
                ORDER BY v.fecha_hora DESC";

        return $this->conexion->getDataAll($sql);
    }
}
