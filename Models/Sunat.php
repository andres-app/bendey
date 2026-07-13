<?php

declare(strict_types=1);

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
        return $this->conexion->getDataAll(
            "SELECT
                v.idventa,
                CONCAT(
                    v.serie_comprobante,
                    '-',
                    v.num_comprobante
                ) AS comprobante,
                v.tipo_comprobante,
                p.nombre AS cliente,
                v.total_venta AS total,
                vs.document_id,
                vs.xml,
                vs.cdr,
                vs.xml_local,
                vs.cdr_local,
                vs.estado_sunat,
                vs.mensaje_sunat,
                DATE_FORMAT(
                    v.fecha_hora,
                    '%d/%m/%Y %H:%i'
                ) AS fecha
             FROM venta v
             INNER JOIN persona p
                ON p.idpersona = v.idcliente
             LEFT JOIN venta_sunat vs
                ON vs.idventa = v.idventa
             WHERE v.tipo_comprobante IN (
                'Factura Electrónica',
                'Boleta Electrónica'
             )
             ORDER BY v.idventa DESC"
        );
    }

    public function detalle(
        int $idventa
    ): ?array {
        $resultado = $this->conexion->getData(
            "SELECT
                v.idventa,
                v.tipo_comprobante,
                CONCAT(
                    v.serie_comprobante,
                    '-',
                    v.num_comprobante
                ) AS comprobante,
                p.nombre AS cliente,
                v.total_venta AS total,
                vs.document_id,
                vs.xml,
                vs.cdr,
                vs.xml_local,
                vs.cdr_local,
                vs.estado_sunat,
                vs.mensaje_sunat
             FROM venta v
             INNER JOIN persona p
                ON p.idpersona = v.idcliente
             LEFT JOIN venta_sunat vs
                ON vs.idventa = v.idventa
             WHERE v.idventa = ?
             LIMIT 1",
            [$idventa]
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    public function obtenerArchivo(
        int $idventa,
        string $tipo
    ): ?array {
        $tipo = strtolower($tipo);

        if (!in_array(
            $tipo,
            ['xml', 'cdr'],
            true
        )) {
            return null;
        }

        $columnaUrl = $tipo;
        $columnaLocal = $tipo . '_local';

        $resultado = $this->conexion->getData(
            "SELECT
                {$columnaUrl} AS url,
                {$columnaLocal} AS ruta_local
             FROM venta_sunat
             WHERE idventa = ?
             LIMIT 1",
            [$idventa]
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    public function actualizarRutaLocal(
        int $idventa,
        string $tipo,
        string $ruta
    ): bool {
        $tipo = strtolower($tipo);

        if (!in_array(
            $tipo,
            ['xml', 'cdr'],
            true
        )) {
            return false;
        }

        $columna = $tipo . '_local';

        return $this->conexion->setData(
            "UPDATE venta_sunat
             SET
                {$columna} = ?,
                fecha_descarga_archivos = NOW()
             WHERE idventa = ?",
            [
                $ruta,
                $idventa
            ]
        );
    }
}