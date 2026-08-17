<?php

require_once __DIR__ . '/../Config/Conexion.php';

class Contabilidad
{
    private Conexion $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    public function obtenerEmpresa(): array
    {
        $data = $this->conexion->getData(
            "SELECT id_negocio, nombre, ndocumento, documento, pais, moneda, simbolo
             FROM datos_negocio
             WHERE condicion = 1
             ORDER BY id_negocio ASC
             LIMIT 1"
        );

        return is_array($data) ? $data : [];
    }

    public function listarSucursalesUsuario(int $idusuario): array
    {
        $sql = "SELECT
                    s.idsucursal,
                    s.codigo,
                    s.nombre,
                    s.direccion,
                    s.codigo_establecimiento_sunat,
                    s.principal
                FROM sucursal s
                WHERE s.activo = 1
                  AND (
                        NOT EXISTS (
                            SELECT 1
                            FROM usuario_sucursal ux
                            WHERE ux.idusuario = ?
                              AND ux.activo = 1
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM usuario_sucursal us
                            WHERE us.idusuario = ?
                              AND us.idsucursal = s.idsucursal
                              AND us.activo = 1
                        )
                  )
                ORDER BY s.principal DESC, s.nombre ASC";

        $rows = $this->conexion->getDataAll($sql, [$idusuario, $idusuario]);
        return is_array($rows) ? $rows : [];
    }

    public function sucursalPermitida(int $idusuario, int $idsucursal): bool
    {
        if ($idsucursal <= 0) {
            return true;
        }

        $sql = "SELECT COUNT(*) AS total
                FROM sucursal s
                WHERE s.idsucursal = ?
                  AND s.activo = 1
                  AND (
                        NOT EXISTS (
                            SELECT 1
                            FROM usuario_sucursal ux
                            WHERE ux.idusuario = ?
                              AND ux.activo = 1
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM usuario_sucursal us
                            WHERE us.idusuario = ?
                              AND us.idsucursal = s.idsucursal
                              AND us.activo = 1
                        )
                  )";

        $row = $this->conexion->getData($sql, [$idsucursal, $idusuario, $idusuario]);
        return (int)($row['total'] ?? 0) > 0;
    }

    public function listarLibroVentas(
        string $fechaInicio,
        string $fechaFin,
        string $tipoDocumento = 'TODOS',
        int $idsucursal = 0,
        int $idusuario = 0
    ): array {
        $registros = [];

        if ($tipoDocumento === 'TODOS' || in_array($tipoDocumento, ['01', '03'], true)) {
            $registros = array_merge(
                $registros,
                $this->listarVentasBase(
                    $fechaInicio,
                    $fechaFin,
                    $tipoDocumento,
                    $idsucursal,
                    $idusuario
                )
            );
        }

        if ($tipoDocumento === 'TODOS' || $tipoDocumento === '07') {
            $registros = array_merge(
                $registros,
                $this->listarNotasCreditoBase(
                    $fechaInicio,
                    $fechaFin,
                    $idsucursal,
                    $idusuario
                )
            );
        }

        usort($registros, static function (array $a, array $b): int {
            $fechaA = (string)($a['fecha_hora'] ?? '');
            $fechaB = (string)($b['fecha_hora'] ?? '');
            if ($fechaA === $fechaB) {
                return (int)($a['orden_id'] ?? 0) <=> (int)($b['orden_id'] ?? 0);
            }
            return strcmp($fechaA, $fechaB);
        });

        return $registros;
    }

    private function listarVentasBase(
        string $fechaInicio,
        string $fechaFin,
        string $tipoDocumento,
        int $idsucursal,
        int $idusuario
    ): array {
        $params = [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59'];

        $sql = "SELECT
                    'VENTA' AS origen,
                    v.idventa AS origen_id,
                    v.idventa AS orden_id,
                    v.fecha_hora,
                    COALESCE(
                        NULLIF(vs.tipo_documento_sunat, ''),
                        CASE
                            WHEN v.tipo_comprobante = 'Factura Electrónica' THEN '01'
                            WHEN v.tipo_comprobante = 'Boleta Electrónica' THEN '03'
                            ELSE ''
                        END
                    ) AS tipo_documento_sunat,
                    v.tipo_comprobante,
                    COALESCE(v.serie_comprobante, '') AS serie_comprobante,
                    COALESCE(v.num_comprobante, '') AS num_comprobante,
                    COALESCE(p.tipo_documento, '') AS cliente_tipo_documento,
                    COALESCE(p.num_documento, '') AS cliente_num_documento,
                    COALESCE(p.nombre, 'CLIENTE VARIOS') AS cliente_nombre,
                    COALESCE(v.total_exportacion, 0) AS total_exportacion,
                    COALESCE(v.total_gravado, 0) AS total_gravado,
                    COALESCE(v.total_igv, 0) AS total_igv,
                    COALESCE(v.total_exonerado, 0) AS total_exonerado,
                    COALESCE(v.total_inafecto, 0) AS total_inafecto,
                    COALESCE(v.total_venta, 0) AS total_documento,
                    COALESCE(v.descuento_total, 0) AS descuento_total,
                    COALESCE(NULLIF(v.moneda_codigo, ''), 'PEN') AS moneda_codigo,
                    COALESCE(NULLIF(v.tipo_cambio_sunat, 0), 1) AS tipo_cambio,
                    COALESCE((SELECT MIN(vc.fecha_vencimiento) FROM venta_cuota vc WHERE vc.idventa = v.idventa), '') AS fecha_vencimiento,
                    COALESCE(v.tipo_pago, '') AS tipo_pago_codigo,
                    COALESCE(fp.nombre, '') AS forma_pago_nombre,
                    '' AS fecha_doc_modificado,
                    '' AS tipo_doc_modificado,
                    '' AS serie_doc_modificado,
                    '' AS numero_doc_modificado,
                    COALESCE(v.estado, '') AS estado_interno,
                    CASE
                        WHEN v.estado <> 'Aceptado' THEN 'ANULADO'
                        WHEN vs.idventa_sunat IS NULL THEN 'NO ENVIADO'
                        WHEN COALESCE(vs.document_id, '') = '' THEN 'NO ENVIADO'
                        WHEN COALESCE(vs.estado_sunat, '') = '' THEN 'PENDIENTE'
                        ELSE UPPER(TRIM(vs.estado_sunat))
                    END AS estado_comprobante,
                    COALESCE(v.idsucursal, 0) AS idsucursal
                FROM venta v
                LEFT JOIN persona p
                    ON p.idpersona = v.idcliente
                LEFT JOIN forma_pago fp
                    ON fp.idforma_pago = v.idforma_pago
                LEFT JOIN venta_sunat vs
                    ON vs.idventa_sunat = (
                        SELECT MAX(vsx.idventa_sunat)
                        FROM venta_sunat vsx
                        WHERE vsx.idventa = v.idventa
                    )
                WHERE v.fecha_hora BETWEEN ? AND ?
                  AND v.tipo_comprobante IN ('Factura Electrónica', 'Boleta Electrónica')";

        if ($tipoDocumento === '01') {
            $sql .= " AND v.tipo_comprobante = 'Factura Electrónica'";
        } elseif ($tipoDocumento === '03') {
            $sql .= " AND v.tipo_comprobante = 'Boleta Electrónica'";
        }

        if ($idsucursal > 0) {
            $sql .= ' AND v.idsucursal = ?';
            $params[] = $idsucursal;
        } else {
            $sql .= " AND (
                        NOT EXISTS (
                            SELECT 1
                            FROM usuario_sucursal ux
                            WHERE ux.idusuario = ?
                              AND ux.activo = 1
                        )
                        OR v.idsucursal IS NULL
                        OR v.idsucursal = 0
                        OR EXISTS (
                            SELECT 1
                            FROM usuario_sucursal us
                            WHERE us.idusuario = ?
                              AND us.idsucursal = v.idsucursal
                              AND us.activo = 1
                        )
                    )";
            $params[] = $idusuario;
            $params[] = $idusuario;
        }

        $sql .= ' ORDER BY v.fecha_hora ASC, v.idventa ASC';

        $rows = $this->conexion->getDataAll($sql, $params);
        return is_array($rows) ? $rows : [];
    }

    private function listarNotasCreditoBase(
        string $fechaInicio,
        string $fechaFin,
        int $idsucursal,
        int $idusuario
    ): array {
        $params = [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59'];

        $sql = "SELECT
                    'NOTA_CREDITO' AS origen,
                    nc.idnota_credito AS origen_id,
                    (1000000000 + nc.idnota_credito) AS orden_id,
                    nc.fecha_hora,
                    '07' AS tipo_documento_sunat,
                    nc.tipo_comprobante,
                    COALESCE(nc.serie_comprobante, '') AS serie_comprobante,
                    COALESCE(nc.num_comprobante, '') AS num_comprobante,
                    COALESCE(nc.cliente_tipo_documento, p.tipo_documento, '') AS cliente_tipo_documento,
                    COALESCE(nc.cliente_num_documento, p.num_documento, '') AS cliente_num_documento,
                    COALESCE(NULLIF(nc.cliente_nombre, ''), p.nombre, 'CLIENTE VARIOS') AS cliente_nombre,
                    -ABS(COALESCE(nc.total_exportacion, 0)) AS total_exportacion,
                    -ABS(COALESCE(nc.total_gravado, 0)) AS total_gravado,
                    -ABS(COALESCE(nc.igv, 0)) AS total_igv,
                    -ABS(COALESCE(nc.total_exonerado, 0)) AS total_exonerado,
                    -ABS(COALESCE(nc.total_inafecto, 0)) AS total_inafecto,
                    -ABS(COALESCE(nc.total_nota, 0)) AS total_documento,
                    -ABS(COALESCE(nc.descuento_total, 0)) AS descuento_total,
                    COALESCE(NULLIF(nc.moneda, ''), 'PEN') AS moneda_codigo,
                    COALESCE(NULLIF(vo.tipo_cambio_sunat, 0), 1) AS tipo_cambio,
                    '' AS fecha_vencimiento,
                    '' AS tipo_pago_codigo,
                    '' AS forma_pago_nombre,
                    DATE_FORMAT(vo.fecha_hora, '%Y-%m-%d') AS fecha_doc_modificado,
                    COALESCE(NULLIF(nc.tipo_documento_modificado, ''), '') AS tipo_doc_modificado,
                    COALESCE(nc.serie_documento_modificado, '') AS serie_doc_modificado,
                    COALESCE(nc.numero_documento_modificado, '') AS numero_doc_modificado,
                    COALESCE(nc.estado, '') AS estado_interno,
                    CASE
                        WHEN nc.estado = 'ANULADA' THEN 'ANULADO'
                        WHEN ncs.idnota_credito_sunat IS NULL THEN 'NO ENVIADO'
                        WHEN COALESCE(ncs.document_id, '') = '' THEN 'NO ENVIADO'
                        WHEN COALESCE(ncs.estado_sunat, '') = '' THEN 'PENDIENTE'
                        ELSE UPPER(TRIM(ncs.estado_sunat))
                    END AS estado_comprobante,
                    COALESCE(nc.idsucursal, 0) AS idsucursal
                FROM nota_credito nc
                LEFT JOIN venta vo
                    ON vo.idventa = nc.idventa
                LEFT JOIN persona p
                    ON p.idpersona = nc.idcliente
                LEFT JOIN nota_credito_sunat ncs
                    ON ncs.idnota_credito_sunat = (
                        SELECT MAX(ncsx.idnota_credito_sunat)
                        FROM nota_credito_sunat ncsx
                        WHERE ncsx.idnota_credito = nc.idnota_credito
                    )
                WHERE nc.fecha_hora BETWEEN ? AND ?
                  AND nc.estado <> 'BORRADOR'";

        if ($idsucursal > 0) {
            $sql .= ' AND nc.idsucursal = ?';
            $params[] = $idsucursal;
        } else {
            $sql .= " AND (
                        NOT EXISTS (
                            SELECT 1
                            FROM usuario_sucursal ux
                            WHERE ux.idusuario = ?
                              AND ux.activo = 1
                        )
                        OR nc.idsucursal IS NULL
                        OR nc.idsucursal = 0
                        OR EXISTS (
                            SELECT 1
                            FROM usuario_sucursal us
                            WHERE us.idusuario = ?
                              AND us.idsucursal = nc.idsucursal
                              AND us.activo = 1
                        )
                    )";
            $params[] = $idusuario;
            $params[] = $idusuario;
        }

        $sql .= ' ORDER BY nc.fecha_hora ASC, nc.idnota_credito ASC';

        $rows = $this->conexion->getDataAll($sql, $params);
        return is_array($rows) ? $rows : [];
    }
}
