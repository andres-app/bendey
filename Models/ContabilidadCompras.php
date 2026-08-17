<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';

class ContabilidadCompras
{
    private Conexion $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    public function obtenerEmpresa(): array
    {
        $row = $this->conexion->getData(
            "SELECT id_negocio, nombre, ndocumento, documento, pais, moneda, simbolo
             FROM datos_negocio
             WHERE condicion = 1
             ORDER BY id_negocio ASC
             LIMIT 1"
        );
        return is_array($row) ? $row : [];
    }

    public function listarSucursalesUsuario(int $idusuario): array
    {
        $rows = $this->conexion->getDataAll(
            "SELECT s.idsucursal, s.codigo, s.nombre, s.direccion,
                    s.codigo_establecimiento_sunat, s.principal
             FROM sucursal s
             WHERE s.activo = 1
               AND (
                    NOT EXISTS (
                        SELECT 1 FROM usuario_sucursal ux
                        WHERE ux.idusuario = ? AND ux.activo = 1
                    )
                    OR EXISTS (
                        SELECT 1 FROM usuario_sucursal us
                        WHERE us.idusuario = ?
                          AND us.idsucursal = s.idsucursal
                          AND us.activo = 1
                    )
               )
             ORDER BY s.principal DESC, s.nombre ASC",
            [$idusuario, $idusuario]
        );
        return is_array($rows) ? $rows : [];
    }

    public function sucursalPermitida(int $idusuario, int $idsucursal): bool
    {
        if ($idsucursal <= 0) {
            return true;
        }
        $row = $this->conexion->getData(
            "SELECT COUNT(*) total
             FROM sucursal s
             WHERE s.idsucursal = ? AND s.activo = 1
               AND (
                    NOT EXISTS (
                        SELECT 1 FROM usuario_sucursal ux
                        WHERE ux.idusuario = ? AND ux.activo = 1
                    )
                    OR EXISTS (
                        SELECT 1 FROM usuario_sucursal us
                        WHERE us.idusuario = ?
                          AND us.idsucursal = s.idsucursal
                          AND us.activo = 1
                    )
               )",
            [$idsucursal, $idusuario, $idusuario]
        );
        return (int)($row['total'] ?? 0) > 0;
    }

    public function listarCompras(
        string $fechaInicio,
        string $fechaFin,
        string $buscarPor,
        string $tipoDocumento,
        int $idsucursal,
        int $idusuario
    ): array {
        $columnaFecha = $buscarPor === 'registro' ? 'i.f_registro' : 'i.fecha_hora';
        $params = [$fechaInicio . ' 00:00:00', $fechaFin . ' 23:59:59'];

        $sql = "SELECT
                    i.idingreso,
                    i.f_registro,
                    i.fecha_hora,
                    i.idproveedor,
                    i.idsucursal,
                    i.tipo_comprobante,
                    COALESCE(i.serie_comprobante, '') AS serie_comprobante,
                    COALESCE(i.num_comprobante, '') AS num_comprobante,
                    COALESCE(i.impuesto, 0) AS impuesto,
                    COALESCE(i.total_compra, 0) AS total_compra,
                    COALESCE(i.tipo_compra, '') AS tipo_compra,
                    COALESCE(i.observacion, '') AS observacion,
                    COALESCE(i.estado, '') AS estado,
                    COALESCE(p.tipo_documento, '') AS proveedor_tipo_documento,
                    COALESCE(p.num_documento, '') AS proveedor_num_documento,
                    COALESCE(p.nombre, '') AS proveedor_nombre,
                    COALESCE(s.nombre, '') AS sucursal_nombre
                FROM ingreso i
                INNER JOIN persona p ON p.idpersona = i.idproveedor
                LEFT JOIN sucursal s ON s.idsucursal = i.idsucursal
                WHERE {$columnaFecha} BETWEEN ? AND ?
                  AND UPPER(TRIM(COALESCE(i.tipo_comprobante, ''))) <> 'STOCK INICIAL'
                  AND UPPER(TRIM(COALESCE(i.estado, ''))) NOT IN ('ANULADO', 'ANULADA')";

        $tipoMap = [
            '01' => 'Factura',
            '03' => 'Boleta',
            '12' => 'Ticket',
            '14' => 'Recibo',
            '00' => 'Otro',
        ];
        if (isset($tipoMap[$tipoDocumento])) {
            $sql .= ' AND i.tipo_comprobante = ?';
            $params[] = $tipoMap[$tipoDocumento];
        }

        if ($idsucursal > 0) {
            $sql .= ' AND i.idsucursal = ?';
            $params[] = $idsucursal;
        } else {
            $sql .= " AND (
                        NOT EXISTS (
                            SELECT 1 FROM usuario_sucursal ux
                            WHERE ux.idusuario = ? AND ux.activo = 1
                        )
                        OR i.idsucursal IS NULL
                        OR i.idsucursal = 0
                        OR EXISTS (
                            SELECT 1 FROM usuario_sucursal us
                            WHERE us.idusuario = ?
                              AND us.idsucursal = i.idsucursal
                              AND us.activo = 1
                        )
                    )";
            $params[] = $idusuario;
            $params[] = $idusuario;
        }

        $sql .= " ORDER BY {$columnaFecha} ASC, i.idingreso ASC";
        $rows = $this->conexion->getDataAll($sql, $params);
        return is_array($rows) ? $rows : [];
    }
}
