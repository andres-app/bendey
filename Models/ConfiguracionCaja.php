<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';

class ConfiguracionCaja
{
    private Conexion $conexion;

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER SUCURSAL PRINCIPAL Y CONFIGURACIÓN ACTUAL
    |--------------------------------------------------------------------------
    */
    public function obtenerSucursalPrincipal(): ?array
    {
        $sql = "SELECT
                    s.idsucursal,
                    s.codigo AS codigo_sucursal,
                    s.nombre AS nombre_sucursal,
                    s.direccion,
                    s.codigo_establecimiento_sunat,
                    s.principal,
                    s.activo AS sucursal_activa,

                    COALESCE(
                        cc.modo,
                        'LEGACY'
                    ) AS modo,

                    cc.idcaja_unica,

                    cf.codigo AS codigo_caja_unica,
                    cf.nombre AS nombre_caja_unica,
                    cf.permite_efectivo,
                    cf.activo AS caja_unica_activa,

                    cc.created_at,
                    cc.updated_at

                FROM sucursal AS s

                LEFT JOIN configuracion_caja AS cc
                    ON cc.idsucursal = s.idsucursal

                LEFT JOIN caja_fisica AS cf
                    ON cf.idcaja = cc.idcaja_unica

                WHERE s.principal = 1
                  AND s.activo = 1

                ORDER BY s.idsucursal ASC
                LIMIT 1";

        $resultado = $this->conexion->getData(
            $sql,
            []
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER CONFIGURACIÓN POR SUCURSAL
    |--------------------------------------------------------------------------
    */
    public function obtenerPorSucursal(
        int $idsucursal
    ): ?array {
        if ($idsucursal <= 0) {
            return null;
        }

        $sql = "SELECT
                    s.idsucursal,
                    s.codigo AS codigo_sucursal,
                    s.nombre AS nombre_sucursal,
                    s.direccion,
                    s.codigo_establecimiento_sunat,
                    s.principal,
                    s.activo AS sucursal_activa,

                    COALESCE(
                        cc.modo,
                        'LEGACY'
                    ) AS modo,

                    cc.idcaja_unica,

                    cf.codigo AS codigo_caja_unica,
                    cf.nombre AS nombre_caja_unica,
                    cf.permite_efectivo,
                    cf.activo AS caja_unica_activa,

                    cc.created_at,
                    cc.updated_at

                FROM sucursal AS s

                LEFT JOIN configuracion_caja AS cc
                    ON cc.idsucursal = s.idsucursal

                LEFT JOIN caja_fisica AS cf
                    ON cf.idcaja = cc.idcaja_unica

                WHERE s.idsucursal = ?
                LIMIT 1";

        $resultado = $this->conexion->getData(
            $sql,
            [$idsucursal]
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | LISTAR CAJAS ACTIVAS DE UNA SUCURSAL
    |--------------------------------------------------------------------------
    */
    public function listarCajasActivas(
        int $idsucursal
    ): array {
        if ($idsucursal <= 0) {
            return [];
        }

        $sql = "SELECT
                    cf.idcaja,
                    cf.idsucursal,
                    cf.codigo,
                    cf.nombre,
                    cf.descripcion,
                    cf.permite_efectivo,
                    cf.activo

                FROM caja_fisica AS cf

                WHERE cf.idsucursal = ?
                  AND cf.activo = 1

                ORDER BY
                    cf.nombre ASC,
                    cf.idcaja ASC";

        return $this->conexion->getDataAll(
            $sql,
            [$idsucursal]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CONTAR CAJAS ACTIVAS
    |--------------------------------------------------------------------------
    */
    public function contarCajasActivas(
        int $idsucursal
    ): int {
        if ($idsucursal <= 0) {
            return 0;
        }

        $resultado = $this->conexion->getData(
            "SELECT
                COUNT(*) AS total
             FROM caja_fisica
             WHERE idsucursal = ?
               AND activo = 1",
            [$idsucursal]
        );

        return is_array($resultado)
            ? (int)($resultado['total'] ?? 0)
            : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR CAJA EN SUCURSAL
    |--------------------------------------------------------------------------
    */
    public function cajaActivaPerteneceSucursal(
        int $idcaja,
        int $idsucursal
    ): bool {
        if (
            $idcaja <= 0
            || $idsucursal <= 0
        ) {
            return false;
        }

        $resultado = $this->conexion->getData(
            "SELECT idcaja
             FROM caja_fisica
             WHERE idcaja = ?
               AND idsucursal = ?
               AND activo = 1
             LIMIT 1",
            [
                $idcaja,
                $idsucursal
            ]
        );

        return is_array($resultado);
    }
}