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
    | LISTAR TODAS LAS CAJAS PARA ADMINISTRACIÓN
    |--------------------------------------------------------------------------
    */
    public function listarCajasGestion(
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
                    cf.activo,

                    CASE
                        WHEN cc.idcaja_unica = cf.idcaja THEN 1
                        ELSE 0
                    END AS es_principal,

                    ca.idapertura AS idapertura_abierta,
                    ca.monto_apertura,
                    ca.created_at AS fecha_apertura,

                    COUNT(
                        DISTINCT CASE
                            WHEN uc.activo = 1 THEN uc.idusuario
                            ELSE NULL
                        END
                    ) AS usuarios_asignados

                FROM caja_fisica AS cf

                LEFT JOIN configuracion_caja AS cc
                    ON cc.idsucursal = cf.idsucursal

                LEFT JOIN caja_apertura AS ca
                    ON ca.idcaja = cf.idcaja
                   AND ca.estado = 'ABIERTA'

                LEFT JOIN usuario_caja AS uc
                    ON uc.idcaja = cf.idcaja
                   AND uc.activo = 1

                WHERE cf.idsucursal = ?

                GROUP BY
                    cf.idcaja,
                    cf.idsucursal,
                    cf.codigo,
                    cf.nombre,
                    cf.descripcion,
                    cf.permite_efectivo,
                    cf.activo,
                    cc.idcaja_unica,
                    ca.idapertura,
                    ca.monto_apertura,
                    ca.created_at

                ORDER BY cf.idcaja ASC";

        $resultado = $this->conexion->getDataAll(
            $sql,
            [$idsucursal]
        );

        return is_array($resultado)
            ? $resultado
            : [];
    }

    /*
    |--------------------------------------------------------------------------
    | CREAR CAJA FÍSICA
    |--------------------------------------------------------------------------
    */
    public function crearCaja(
        int $idsucursal,
        string $nombre,
        string $descripcion,
        bool $permiteEfectivo = true
    ): array {
        if ($idsucursal <= 0) {
            throw new RuntimeException(
                'La sucursal seleccionada no es válida.'
            );
        }

        $nombre = trim($nombre);
        $descripcion = trim($descripcion);

        if ($nombre === '') {
            throw new RuntimeException(
                'Ingrese un nombre para la caja.'
            );
        }

        if (mb_strlen($nombre) > 100) {
            throw new RuntimeException(
                'El nombre de la caja no puede superar los 100 caracteres.'
            );
        }

        if (mb_strlen($descripcion) > 255) {
            throw new RuntimeException(
                'La descripción no puede superar los 255 caracteres.'
            );
        }

        $transaccionActiva = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionActiva = true;

            $sucursal = $this->conexion->getData(
                "SELECT idsucursal
                 FROM sucursal
                 WHERE idsucursal = ?
                   AND activo = 1
                 LIMIT 1
                 FOR UPDATE",
                [$idsucursal]
            );

            if (!is_array($sucursal)) {
                throw new RuntimeException(
                    'La sucursal no existe o se encuentra inactiva.'
                );
            }

            $cajasExistentes = $this->conexion->getDataAll(
                "SELECT idcaja, codigo, nombre
                 FROM caja_fisica
                 WHERE idsucursal = ?
                 ORDER BY idcaja ASC
                 FOR UPDATE",
                [$idsucursal]
            );

            $nombreNormalizado = mb_strtoupper($nombre, 'UTF-8');
            $maximo = 0;

            foreach ($cajasExistentes as $caja) {
                if (
                    mb_strtoupper(
                        trim((string)($caja['nombre'] ?? '')),
                        'UTF-8'
                    ) === $nombreNormalizado
                ) {
                    throw new RuntimeException(
                        'Ya existe una caja con ese nombre en la sucursal.'
                    );
                }

                $codigoActual = strtoupper(
                    trim((string)($caja['codigo'] ?? ''))
                );

                if (preg_match('/^CAJ(\\d+)$/', $codigoActual, $coincidencia)) {
                    $maximo = max(
                        $maximo,
                        (int)$coincidencia[1]
                    );
                }
            }

            $numero = $maximo + 1;
            $codigo = 'CAJ' . str_pad(
                (string)$numero,
                max(3, strlen((string)$numero)),
                '0',
                STR_PAD_LEFT
            );

            $idcaja = (int)$this->conexion->setDataReturnId(
                "INSERT INTO caja_fisica (
                    idsucursal,
                    codigo,
                    nombre,
                    descripcion,
                    permite_efectivo,
                    activo
                 ) VALUES (?, ?, ?, ?, ?, 1)",
                [
                    $idsucursal,
                    $codigo,
                    $nombre,
                    $descripcion !== '' ? $descripcion : null,
                    $permiteEfectivo ? 1 : 0
                ]
            );

            if ($idcaja <= 0) {
                throw new RuntimeException(
                    'No se pudo crear la caja física.'
                );
            }

            $this->conexion->commit();
            $transaccionActiva = false;

            return [
                'idcaja' => $idcaja,
                'codigo' => $codigo,
                'nombre' => $nombre
            ];
        } catch (Throwable $e) {
            if ($transaccionActiva) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[CAJA FISICA CREAR ROLLBACK] '
                        . $rollbackError->getMessage()
                    );
                }
            }

            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EDITAR CAJA FÍSICA
    |--------------------------------------------------------------------------
    */
    public function editarCaja(
        int $idsucursal,
        int $idcaja,
        string $nombre,
        string $descripcion,
        bool $permiteEfectivo = true
    ): bool {
        if ($idsucursal <= 0 || $idcaja <= 0) {
            throw new RuntimeException(
                'La caja seleccionada no es válida.'
            );
        }

        $nombre = trim($nombre);
        $descripcion = trim($descripcion);

        if ($nombre === '') {
            throw new RuntimeException(
                'Ingrese un nombre para la caja.'
            );
        }

        if (mb_strlen($nombre) > 100) {
            throw new RuntimeException(
                'El nombre de la caja no puede superar los 100 caracteres.'
            );
        }

        if (mb_strlen($descripcion) > 255) {
            throw new RuntimeException(
                'La descripción no puede superar los 255 caracteres.'
            );
        }

        $transaccionActiva = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionActiva = true;

            $caja = $this->conexion->getData(
                "SELECT idcaja, codigo, nombre, activo
                 FROM caja_fisica
                 WHERE idcaja = ?
                   AND idsucursal = ?
                 LIMIT 1
                 FOR UPDATE",
                [$idcaja, $idsucursal]
            );

            if (!is_array($caja)) {
                throw new RuntimeException(
                    'La caja no pertenece a la sucursal.'
                );
            }

            $apertura = $this->conexion->getData(
                "SELECT idapertura
                 FROM caja_apertura
                 WHERE idcaja = ?
                   AND estado = 'ABIERTA'
                 LIMIT 1
                 FOR UPDATE",
                [$idcaja]
            );

            if (is_array($apertura)) {
                throw new RuntimeException(
                    'Cierre la caja antes de modificar su configuración.'
                );
            }

            $duplicada = $this->conexion->getData(
                "SELECT idcaja
                 FROM caja_fisica
                 WHERE idsucursal = ?
                   AND idcaja <> ?
                   AND UPPER(TRIM(nombre)) = UPPER(TRIM(?))
                 LIMIT 1",
                [$idsucursal, $idcaja, $nombre]
            );

            if (is_array($duplicada)) {
                throw new RuntimeException(
                    'Ya existe otra caja con ese nombre en la sucursal.'
                );
            }

            $resultado = (bool)$this->conexion->setData(
                "UPDATE caja_fisica
                 SET
                    nombre = ?,
                    descripcion = ?,
                    permite_efectivo = ?
                 WHERE idcaja = ?
                   AND idsucursal = ?",
                [
                    $nombre,
                    $descripcion !== '' ? $descripcion : null,
                    $permiteEfectivo ? 1 : 0,
                    $idcaja,
                    $idsucursal
                ]
            );

            $this->conexion->commit();
            $transaccionActiva = false;

            return $resultado;
        } catch (Throwable $e) {
            if ($transaccionActiva) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[CAJA FISICA EDITAR ROLLBACK] '
                        . $rollbackError->getMessage()
                    );
                }
            }

            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVAR / DESACTIVAR CAJA FÍSICA
    |--------------------------------------------------------------------------
    */
    public function cambiarEstadoCaja(
        int $idsucursal,
        int $idcaja,
        bool $activar
    ): bool {
        if ($idsucursal <= 0 || $idcaja <= 0) {
            throw new RuntimeException(
                'La caja seleccionada no es válida.'
            );
        }

        $transaccionActiva = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionActiva = true;

            $caja = $this->conexion->getData(
                "SELECT idcaja, codigo, nombre, activo
                 FROM caja_fisica
                 WHERE idcaja = ?
                   AND idsucursal = ?
                 LIMIT 1
                 FOR UPDATE",
                [$idcaja, $idsucursal]
            );

            if (!is_array($caja)) {
                throw new RuntimeException(
                    'La caja no pertenece a la sucursal.'
                );
            }

            $estadoActual = (int)($caja['activo'] ?? 0) === 1;

            if ($estadoActual === $activar) {
                $this->conexion->commit();
                $transaccionActiva = false;
                return true;
            }

            if (!$activar) {
                $apertura = $this->conexion->getData(
                    "SELECT idapertura
                     FROM caja_apertura
                     WHERE idcaja = ?
                       AND estado = 'ABIERTA'
                     LIMIT 1
                     FOR UPDATE",
                    [$idcaja]
                );

                if (is_array($apertura)) {
                    throw new RuntimeException(
                        'No se puede desactivar una caja que se encuentra abierta.'
                    );
                }

                $configuracion = $this->conexion->getData(
                    "SELECT modo, idcaja_unica
                     FROM configuracion_caja
                     WHERE idsucursal = ?
                     LIMIT 1
                     FOR UPDATE",
                    [$idsucursal]
                );

                if (
                    is_array($configuracion)
                    && (int)($configuracion['idcaja_unica'] ?? 0) === $idcaja
                ) {
                    throw new RuntimeException(
                        'Esta caja está definida como caja principal. Seleccione otra caja principal antes de desactivarla.'
                    );
                }

                if (
                    is_array($configuracion)
                    && strtoupper((string)($configuracion['modo'] ?? '')) === 'MULTICAJA'
                    && $this->contarCajasActivas($idsucursal) <= 2
                ) {
                    throw new RuntimeException(
                        'Multicaja requiere por lo menos dos cajas físicas activas. Cambie primero a Caja única o active otra caja.'
                    );
                }
            }

            $resultado = (bool)$this->conexion->setData(
                "UPDATE caja_fisica
                 SET activo = ?
                 WHERE idcaja = ?
                   AND idsucursal = ?",
                [
                    $activar ? 1 : 0,
                    $idcaja,
                    $idsucursal
                ]
            );

            $this->conexion->commit();
            $transaccionActiva = false;

            return $resultado;
        } catch (Throwable $e) {
            if ($transaccionActiva) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[CAJA FISICA ESTADO ROLLBACK] '
                        . $rollbackError->getMessage()
                    );
                }
            }

            throw $e;
        }
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
    | CONTAR APERTURAS ABIERTAS DE LA SUCURSAL
    |--------------------------------------------------------------------------
    | Incluye aperturas físicas asociadas a la sucursal y aperturas LEGACY
    | sin sucursal/caja identificable, para impedir cambios inseguros.
    */
    public function contarAperturasAbiertasSucursal(
        int $idsucursal
    ): int {
        if ($idsucursal <= 0) {
            return 0;
        }

        $resultado = $this->conexion->getData(
            "SELECT COUNT(DISTINCT ca.idapertura) AS total
             FROM caja_apertura AS ca
             LEFT JOIN caja_fisica AS cf
                ON cf.idcaja = ca.idcaja
             WHERE ca.estado = 'ABIERTA'
               AND (
                    ca.idsucursal = ?
                    OR cf.idsucursal = ?
                    OR (
                        ca.idsucursal IS NULL
                        AND ca.idcaja IS NULL
                    )
               )",
            [
                $idsucursal,
                $idsucursal
            ]
        );

        return is_array($resultado)
            ? (int)($resultado['total'] ?? 0)
            : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | CAMBIAR MODALIDAD OPERATIVA
    |--------------------------------------------------------------------------
    | El cambio es real: actualiza configuracion_caja.modo.
    | Nunca se permite cambiar de modalidad ni de caja única mientras exista
    | una apertura activa que pudiera quedar asociada al contexto anterior.
    */
    public function cambiarModalidad(
        int $idsucursal,
        string $modo,
        int $idcajaUnica
    ): bool {
        if ($idsucursal <= 0) {
            throw new RuntimeException(
                'La sucursal seleccionada no es válida.'
            );
        }

        $modo = strtoupper(
            trim($modo)
        );

        if (!in_array(
            $modo,
            [
                'CAJA_UNICA',
                'MULTICAJA'
            ],
            true
        )) {
            throw new RuntimeException(
                'La modalidad de caja seleccionada no es válida.'
            );
        }

        if (
            $modo === 'MULTICAJA'
            && $this->contarCajasActivas($idsucursal) < 2
        ) {
            throw new RuntimeException(
                'Multicaja requiere por lo menos dos cajas físicas activas.'
            );
        }

        $transaccionActiva = false;

        try {
            $this->conexion->beginTransaction();
            $transaccionActiva = true;

            /*
             * Bloqueamos la configuración para que dos cambios simultáneos
             * no puedan dejar la sucursal en estados distintos.
             */
            $configuracionActual =
                $this->conexion->getData(
                    "SELECT
                        idsucursal,
                        modo,
                        modo_objetivo,
                        idcaja_unica
                     FROM configuracion_caja
                     WHERE idsucursal = ?
                     LIMIT 1
                     FOR UPDATE",
                    [$idsucursal]
                );

            if (!is_array($configuracionActual)) {
                throw new RuntimeException(
                    'No existe una configuración de caja para la sucursal.'
                );
            }

            $modoActual = strtoupper(
                trim(
                    (string)(
                        $configuracionActual['modo']
                        ?? 'LEGACY'
                    )
                )
            );

            $idcajaActual = (int)(
                $configuracionActual['idcaja_unica']
                ?? 0
            );

            /*
             * En Multicaja conservamos una caja de referencia para facilitar
             * un futuro retorno a Caja única. Si el formulario no envía una,
             * mantenemos la existente.
             */
            $idcajaGuardar = $idcajaUnica > 0
                ? $idcajaUnica
                : $idcajaActual;

            if (
                $modo === 'CAJA_UNICA'
                && $idcajaGuardar <= 0
            ) {
                throw new RuntimeException(
                    'Seleccione una caja principal válida para utilizar Caja única.'
                );
            }

            if (
                $idcajaGuardar > 0
                && !$this->cajaActivaPerteneceSucursal(
                    $idcajaGuardar,
                    $idsucursal
                )
            ) {
                throw new RuntimeException(
                    'La caja seleccionada no pertenece a la sucursal o está inactiva.'
                );
            }

            /*
             * Guardar sin cambios reales no necesita cerrar una caja abierta.
             */
            if (
                $modoActual === $modo
                && $idcajaActual === $idcajaGuardar
            ) {
                $this->conexion->commit();
                $transaccionActiva = false;
                return true;
            }

            /*
             * Bloqueamos las aperturas actuales antes del cambio. También se
             * consideran aperturas LEGACY sin sucursal/caja para no mezclar
             * efectivo de dos contextos operativos.
             */
            $aperturasAbiertas =
                $this->conexion->getDataAll(
                    "SELECT DISTINCT ca.idapertura
                     FROM caja_apertura AS ca
                     LEFT JOIN caja_fisica AS cf
                        ON cf.idcaja = ca.idcaja
                     WHERE ca.estado = 'ABIERTA'
                       AND (
                            ca.idsucursal = ?
                            OR cf.idsucursal = ?
                            OR (
                                ca.idsucursal IS NULL
                                AND ca.idcaja IS NULL
                            )
                       )
                     FOR UPDATE",
                    [
                        $idsucursal,
                        $idsucursal
                    ]
                );

            $cantidadAbiertas = is_array($aperturasAbiertas)
                ? count($aperturasAbiertas)
                : 0;

            if ($cantidadAbiertas > 0) {
                throw new RuntimeException(
                    'No se puede cambiar la modalidad mientras exista '
                    . $cantidadAbiertas
                    . (
                        $cantidadAbiertas === 1
                            ? ' caja abierta. Cierre la caja primero.'
                            : ' cajas abiertas. Cierre todas las cajas primero.'
                    )
                );
            }

            $resultado = (bool)$this->conexion->setData(
                "UPDATE configuracion_caja
                 SET
                    modo = ?,
                    modo_objetivo = ?,
                    idcaja_unica = ?
                 WHERE idsucursal = ?",
                [
                    $modo,
                    $modo,
                    $idcajaGuardar > 0
                        ? $idcajaGuardar
                        : null,
                    $idsucursal
                ]
            );

            if (!$resultado) {
                throw new RuntimeException(
                    'No se pudo actualizar la modalidad de caja.'
                );
            }

            $this->conexion->commit();
            $transaccionActiva = false;

            return true;
        } catch (Throwable $e) {
            if ($transaccionActiva) {
                try {
                    $this->conexion->rollBack();
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[CONFIGURACION CAJA ROLLBACK] '
                        . $rollbackError->getMessage()
                    );
                }
            }

            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | COMPATIBILIDAD CON LLAMADAS EXISTENTES
    |--------------------------------------------------------------------------
    */
    public function guardarPreferencia(
        int $idsucursal,
        string $modoObjetivo,
        int $idcajaUnica
    ): bool {
        return $this->cambiarModalidad(
            $idsucursal,
            $modoObjetivo,
            $idcajaUnica
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

    /*
|--------------------------------------------------------------------------
| OBTENER CAJA AUTORIZADA DEL USUARIO
|--------------------------------------------------------------------------
*/
    public function obtenerCajaAutorizadaUsuario(
        int $idusuario,
        int $idsucursal,
        int $idcaja
    ): ?array {
        if (
            $idusuario <= 0
            || $idsucursal <= 0
            || $idcaja <= 0
        ) {
            return null;
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
              AND cf.idcaja = ?
              AND u.condicion = 1
              AND cf.activo = 1
              AND uc.activo = 1
              AND us.activo = 1
              AND uc.puede_operar = 1

            LIMIT 1";

        $resultado = $this->conexion->getData(
            $sql,
            [
                $idusuario,
                $idsucursal,
                $idcaja
            ]
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    /*
|--------------------------------------------------------------------------
| OBTENER PERMISOS DEL USUARIO EN LA SUCURSAL
|--------------------------------------------------------------------------
*/
    public function obtenerPermisoSucursalUsuario(
        int $idusuario,
        int $idsucursal
    ): ?array {
        if (
            $idusuario <= 0
            || $idsucursal <= 0
        ) {
            return null;
        }

        $resultado = $this->conexion->getData(
            "SELECT
            us.idusuario_sucursal,
            us.idusuario,
            us.idsucursal,
            us.puede_vender,
            us.puede_cobrar,
            us.puede_abrir_caja,
            us.puede_cerrar_caja,
            us.activo,

            u.condicion AS usuario_activo,
            s.activo AS sucursal_activa

         FROM usuario_sucursal AS us

         INNER JOIN usuario AS u
            ON u.idusuario = us.idusuario

         INNER JOIN sucursal AS s
            ON s.idsucursal = us.idsucursal

         WHERE us.idusuario = ?
           AND us.idsucursal = ?
           AND us.activo = 1
           AND u.condicion = 1
           AND s.activo = 1

         LIMIT 1",
            [
                $idusuario,
                $idsucursal
            ]
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }
}
