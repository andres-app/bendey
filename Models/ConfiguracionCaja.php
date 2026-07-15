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
    | OBTENER SUCURSAL PRINCIPAL Y CONFIGURACIÓN
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

                    cc.modo_objetivo,
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

                    cc.modo_objetivo,
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
    | VALIDAR CAJA ACTIVA EN LA SUCURSAL
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

    /*
    |--------------------------------------------------------------------------
    | GUARDAR MODALIDAD OBJETIVO
    |--------------------------------------------------------------------------
    | Guarda la elección administrativa, pero no modifica el modo real.
    | El campo modo seguirá siendo LEGACY.
    */
    public function guardarPreferencia(
        int $idsucursal,
        string $modoObjetivo,
        int $idcajaUnica
    ): bool {
        if ($idsucursal <= 0) {
            throw new RuntimeException(
                'La sucursal seleccionada no es válida.'
            );
        }

        $modoObjetivo = strtoupper(
            trim($modoObjetivo)
        );

        $modosPermitidos = [
            'CAJA_UNICA',
            'MULTICAJA'
        ];

        if (!in_array(
            $modoObjetivo,
            $modosPermitidos,
            true
        )) {
            throw new RuntimeException(
                'La modalidad de caja seleccionada no es válida.'
            );
        }

        if (
            !$this->cajaActivaPerteneceSucursal(
                $idcajaUnica,
                $idsucursal
            )
        ) {
            throw new RuntimeException(
                'La caja seleccionada no pertenece a la sucursal o está inactiva.'
            );
        }

        $configuracionActual =
            $this->conexion->getData(
                "SELECT
                    idsucursal,
                    modo
                 FROM configuracion_caja
                 WHERE idsucursal = ?
                 LIMIT 1",
                [$idsucursal]
            );

        if (!is_array($configuracionActual)) {
            throw new RuntimeException(
                'No existe una configuración de caja para la sucursal.'
            );
        }

        $modoReal = strtoupper(
            trim(
                (string)(
                    $configuracionActual['modo']
                    ?? ''
                )
            )
        );

        if ($modoReal !== 'LEGACY') {
            throw new RuntimeException(
                'La modalidad real ya fue activada y no puede modificarse desde esta etapa.'
            );
        }

        return (bool)$this->conexion->setData(
            "UPDATE configuracion_caja
             SET
                modo_objetivo = ?,
                idcaja_unica = ?
             WHERE idsucursal = ?
               AND modo = 'LEGACY'",
            [
                $modoObjetivo,
                $idcajaUnica,
                $idsucursal
            ]
        );
    }

    /*
|--------------------------------------------------------------------------
| LISTAR CAJAS AUTORIZADAS DEL USUARIO
|--------------------------------------------------------------------------
*/
    public function listarCajasAutorizadasUsuario(
        int $idusuario,
        int $idsucursal
    ): array {
        if (
            $idusuario <= 0
            || $idsucursal <= 0
        ) {
            return [];
        }

        $sql = "SELECT
                cf.idcaja,
                cf.idsucursal,
                cf.codigo,
                cf.nombre,
                cf.descripcion,
                cf.permite_efectivo,
                cf.activo,

                uc.rol,
                uc.puede_operar,
                uc.puede_abrir,
                uc.puede_cerrar,

                us.puede_vender,
                us.puede_cobrar,
                us.puede_abrir_caja,
                us.puede_cerrar_caja

            FROM usuario_caja AS uc

            INNER JOIN caja_fisica AS cf
                ON cf.idcaja = uc.idcaja

            INNER JOIN usuario AS u
                ON u.idusuario = uc.idusuario

            INNER JOIN usuario_sucursal AS us
                ON us.idusuario = uc.idusuario
               AND us.idsucursal = cf.idsucursal

            WHERE uc.idusuario = ?
              AND cf.idsucursal = ?
              AND u.condicion = 1
              AND cf.activo = 1
              AND uc.activo = 1
              AND us.activo = 1
              AND uc.puede_operar = 1

            ORDER BY
                cf.idcaja ASC";

        $resultado = $this->conexion->getDataAll(
            $sql,
            [
                $idusuario,
                $idsucursal
            ]
        );

        return is_array($resultado)
            ? $resultado
            : [];
    }
}
