<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/Cajachica.php';

/**
 * Auditoría de integridad para CAJA_UNICA / MULTICAJA.
 *
 * Solo lectura: no corrige ni modifica información.
 */
class CajaAuditoria
{
    private Conexion $conexion;
    private Cajachica $caja;

    public function __construct()
    {
        $this->conexion = new Conexion();
        $this->caja = new Cajachica();
    }

    public function ejecutar(int $idsucursal): array
    {
        if ($idsucursal <= 0) {
            throw new RuntimeException(
                'La sucursal para auditar no es válida.'
            );
        }

        $configuracion = $this->conexion->getData(
            "SELECT
                idsucursal,
                modo,
                modo_objetivo,
                idcaja_unica
             FROM configuracion_caja
             WHERE idsucursal = ?
             LIMIT 1",
            [$idsucursal]
        );

        if (!is_array($configuracion)) {
            throw new RuntimeException(
                'No existe configuración de caja para la sucursal.'
            );
        }

        $modo = strtoupper(
            trim((string)($configuracion['modo'] ?? 'LEGACY'))
        );

        $cajas = $this->cargarEstadoCajas($idsucursal);
        $inicioFisico = $this->obtenerInicioOperacionFisica($idsucursal);

        $hallazgos = [];

        $this->auditarAperturas(
            $idsucursal,
            $hallazgos
        );

        $this->auditarVentas(
            $idsucursal,
            $inicioFisico,
            $hallazgos
        );

        $this->auditarMovimientos(
            $idsucursal,
            $inicioFisico,
            $hallazgos
        );

        $this->auditarCompras(
            $idsucursal,
            $inicioFisico,
            $hallazgos
        );

        $this->auditarNotasCredito(
            $idsucursal,
            $inicioFisico,
            $hallazgos
        );

        $cierres = $this->auditarCierres(
            $idsucursal,
            $hallazgos
        );

        $criticos = 0;
        $advertencias = 0;

        foreach ($hallazgos as $hallazgo) {
            if (($hallazgo['nivel'] ?? '') === 'CRITICO') {
                $criticos++;
            } elseif (($hallazgo['nivel'] ?? '') === 'ADVERTENCIA') {
                $advertencias++;
            }
        }

        return [
            'modo' => $modo,
            'idsucursal' => $idsucursal,
            'inicio_operacion_fisica' => $inicioFisico,
            'total_cajas' => count($cajas),
            'cajas' => $cajas,
            'cierres_recientes' => $cierres,
            'hallazgos' => $hallazgos,
            'resumen' => [
                'criticos' => $criticos,
                'advertencias' => $advertencias,
                'correcto' =>
                    $criticos === 0
                    && $advertencias === 0
            ]
        ];
    }

    private function cargarEstadoCajas(
        int $idsucursal
    ): array {
        $cajas = $this->conexion->getDataAll(
            "SELECT
                cf.idcaja,
                cf.codigo,
                cf.nombre,
                cf.activo,
                cf.permite_efectivo,
                ca.idapertura,
                ca.estado AS estado_apertura,
                ca.monto_apertura,
                ca.created_at AS fecha_apertura,
                ca.idusuario_responsable,
                u.nombre AS responsable

             FROM caja_fisica AS cf

             LEFT JOIN caja_apertura AS ca
                ON ca.idcaja = cf.idcaja
               AND ca.estado = 'ABIERTA'

             LEFT JOIN usuario AS u
                ON u.idusuario = ca.idusuario_responsable

             WHERE cf.idsucursal = ?

             ORDER BY
                cf.activo DESC,
                cf.codigo ASC,
                cf.idcaja ASC",
            [$idsucursal]
        );

        $resultado = [];

        foreach (is_array($cajas) ? $cajas : [] as $caja) {
            $idapertura = (int)($caja['idapertura'] ?? 0);
            $totales = null;

            if ($idapertura > 0) {
                try {
                    $totales =
                        $this->caja
                            ->calcularTotalesAperturaFisica(
                                $idapertura
                            );
                } catch (Throwable $e) {
                    $totales = null;
                }
            }

            $resultado[] = [
                'idcaja' => (int)$caja['idcaja'],
                'codigo' => (string)$caja['codigo'],
                'nombre' => (string)$caja['nombre'],
                'activo' => (int)$caja['activo'],
                'permite_efectivo' =>
                    (int)$caja['permite_efectivo'],
                'idapertura' =>
                    $idapertura > 0
                        ? $idapertura
                        : null,
                'estado' =>
                    $idapertura > 0
                        ? 'ABIERTA'
                        : 'CERRADA',
                'fecha_apertura' =>
                    $caja['fecha_apertura'] ?? null,
                'responsable' =>
                    $caja['responsable'] ?? null,
                'efectivo_esperado' =>
                    is_array($totales)
                        ? round(
                            (float)(
                                $totales['total_sistema']
                                ?? 0
                            ),
                            2
                        )
                        : null
            ];
        }

        return $resultado;
    }

    private function obtenerInicioOperacionFisica(
        int $idsucursal
    ): ?string {
        $fila = $this->conexion->getData(
            "SELECT MIN(created_at) AS inicio
             FROM caja_apertura
             WHERE idsucursal = ?
               AND idcaja IS NOT NULL",
            [$idsucursal]
        );

        $inicio = trim(
            (string)($fila['inicio'] ?? '')
        );

        return $inicio !== ''
            ? $inicio
            : null;
    }

    private function agregarHallazgo(
        array &$hallazgos,
        string $nivel,
        string $codigo,
        string $titulo,
        string $detalle,
        int $cantidad = 1
    ): void {
        if ($cantidad <= 0) {
            return;
        }

        $hallazgos[] = [
            'nivel' => $nivel,
            'codigo' => $codigo,
            'titulo' => $titulo,
            'detalle' => $detalle,
            'cantidad' => $cantidad
        ];
    }

    private function auditarAperturas(
        int $idsucursal,
        array &$hallazgos
    ): void {
        $duplicadas = $this->conexion->getDataAll(
            "SELECT
                idcaja,
                COUNT(*) AS total
             FROM caja_apertura
             WHERE idsucursal = ?
               AND idcaja IS NOT NULL
               AND estado = 'ABIERTA'
             GROUP BY idcaja
             HAVING COUNT(*) > 1",
            [$idsucursal]
        );

        $this->agregarHallazgo(
            $hallazgos,
            'CRITICO',
            'APERTURA_DUPLICADA',
            'Una caja tiene más de una apertura activa',
            'Cada caja física solo puede mantener una apertura ABIERTA al mismo tiempo.',
            count(is_array($duplicadas) ? $duplicadas : [])
        );

        $invalidas = $this->conexion->getDataAll(
            "SELECT ca.idapertura
             FROM caja_apertura AS ca
             LEFT JOIN caja_fisica AS cf
                ON cf.idcaja = ca.idcaja
             WHERE ca.idsucursal = ?
               AND ca.estado = 'ABIERTA'
               AND (
                    ca.idcaja IS NULL
                    OR cf.idcaja IS NULL
                    OR cf.idsucursal <> ca.idsucursal
                    OR cf.activo <> 1
               )",
            [$idsucursal]
        );

        $this->agregarHallazgo(
            $hallazgos,
            'CRITICO',
            'APERTURA_CAJA_INVALIDA',
            'Apertura activa asociada a una caja inválida',
            'Una apertura física activa debe apuntar a una caja activa de la misma sucursal.',
            count(is_array($invalidas) ? $invalidas : [])
        );
    }

    private function auditarVentas(
        int $idsucursal,
        ?string $inicioFisico,
        array &$hallazgos
    ): void {
        if ($inicioFisico === null) {
            return;
        }

        $sinContexto = $this->conexion->getData(
            "SELECT COUNT(*) AS total
             FROM venta v
             WHERE v.idsucursal = ?
               AND v.estado = 'Aceptado'
               AND v.fecha_hora >= ?
               AND (
                    v.idcaja IS NULL
                    OR v.idapertura IS NULL
               )",
            [
                $idsucursal,
                $inicioFisico
            ]
        );

        $this->agregarHallazgo(
            $hallazgos,
            'CRITICO',
            'VENTA_SIN_APERTURA',
            'Ventas sin contexto físico de caja',
            'Desde el inicio de operación física, una venta aceptada no debe quedar sin caja y apertura.',
            (int)($sinContexto['total'] ?? 0)
        );

        $cruzadas = $this->conexion->getData(
            "SELECT COUNT(*) AS total
             FROM venta v
             INNER JOIN caja_apertura ca
                ON ca.idapertura = v.idapertura
             WHERE v.idsucursal = ?
               AND v.estado = 'Aceptado'
               AND v.fecha_hora >= ?
               AND (
                    v.idcaja <> ca.idcaja
                    OR v.idsucursal <> ca.idsucursal
               )",
            [
                $idsucursal,
                $inicioFisico
            ]
        );

        $this->agregarHallazgo(
            $hallazgos,
            'CRITICO',
            'VENTA_CRUZADA',
            'Ventas vinculadas a otra caja',
            'El idcaja/idsucursal de la venta debe coincidir con la apertura utilizada.',
            (int)($cruzadas['total'] ?? 0)
        );

        $posterioresCierre = $this->conexion->getData(
            "SELECT COUNT(*) AS total
             FROM venta v
             INNER JOIN caja_apertura ca
                ON ca.idapertura = v.idapertura
             WHERE v.idsucursal = ?
               AND v.estado = 'Aceptado'
               AND ca.estado = 'CERRADA'
               AND ca.fecha_cierre IS NOT NULL
               AND v.fecha_hora > ca.fecha_cierre",
            [$idsucursal]
        );

        $this->agregarHallazgo(
            $hallazgos,
            'CRITICO',
            'VENTA_POST_CIERRE',
            'Venta registrada después del cierre de su apertura',
            'Una apertura cerrada nunca debe recibir nuevas ventas.',
            (int)($posterioresCierre['total'] ?? 0)
        );
    }

    private function auditarMovimientos(
        int $idsucursal,
        ?string $inicioFisico,
        array &$hallazgos
    ): void {
        if ($inicioFisico === null) {
            return;
        }

        $sinApertura = $this->conexion->getData(
            "SELECT COUNT(DISTINCT mf.idmovimiento) AS total
             FROM movimiento_financiero mf
             INNER JOIN forma_pago_destino fpd
                ON fpd.idforma_pago = mf.idforma_pago
             INNER JOIN usuario_sucursal us
                ON us.idusuario = mf.idusuario
               AND us.idsucursal = ?
               AND us.activo = 1
             LEFT JOIN caja_apertura ca
                ON ca.idapertura = mf.idapertura
             WHERE mf.estado = 'ACTIVO'
               AND mf.created_at >= ?
               AND fpd.requiere_caja_abierta = 1
               AND (
                    mf.idapertura IS NULL
                    OR ca.idapertura IS NULL
               )",
            [
                $idsucursal,
                $inicioFisico
            ]
        );

        $this->agregarHallazgo(
            $hallazgos,
            'CRITICO',
            'EFECTIVO_SIN_APERTURA',
            'Movimientos de efectivo sin apertura',
            'Toda forma de pago que exige caja abierta debe quedar ligada a un idapertura válido.',
            (int)($sinApertura['total'] ?? 0)
        );

        $postCierre = $this->conexion->getData(
            "SELECT COUNT(*) AS total
             FROM movimiento_financiero mf
             INNER JOIN caja_apertura ca
                ON ca.idapertura = mf.idapertura
             WHERE mf.estado = 'ACTIVO'
               AND ca.idsucursal = ?
               AND ca.estado = 'CERRADA'
               AND ca.fecha_cierre IS NOT NULL
               AND mf.created_at > ca.fecha_cierre",
            [$idsucursal]
        );

        $this->agregarHallazgo(
            $hallazgos,
            'CRITICO',
            'MOVIMIENTO_POST_CIERRE',
            'Una apertura cerrada recibió movimientos posteriores',
            'Esto altera retroactivamente un arqueo cerrado y debe permanecer siempre en cero.',
            (int)($postCierre['total'] ?? 0)
        );
    }

    private function auditarCompras(
        int $idsucursal,
        ?string $inicioFisico,
        array &$hallazgos
    ): void {
        if ($inicioFisico === null) {
            return;
        }

        $efectivoIncompleto = $this->conexion->getData(
            "SELECT COUNT(*) AS total
             FROM ingreso i
             INNER JOIN forma_pago_destino fpd
                ON fpd.idforma_pago = i.idforma_pago
             WHERE i.idsucursal = ?
               AND i.fecha_hora >= ?
               AND i.estado = 'Aceptado'
               AND i.condicion_pago = 'CONTADO'
               AND fpd.requiere_caja_abierta = 1
               AND i.idapertura IS NULL",
            [
                $idsucursal,
                $inicioFisico
            ]
        );

        $this->agregarHallazgo(
            $hallazgos,
            'CRITICO',
            'COMPRA_EFECTIVO_SIN_APERTURA',
            'Compra pagada en efectivo sin apertura',
            'Una compra al contado en efectivo debe tener idapertura.',
            (int)($efectivoIncompleto['total'] ?? 0)
        );

        $sinMovimiento = $this->conexion->getData(
            "SELECT COUNT(*) AS total
             FROM ingreso i
             LEFT JOIN movimiento_financiero mf
                ON mf.origen = 'COMPRA'
               AND mf.idreferencia = i.idingreso
               AND mf.estado = 'ACTIVO'
             WHERE i.idsucursal = ?
               AND i.fecha_hora >= ?
               AND i.estado = 'Aceptado'
               AND i.condicion_pago = 'CONTADO'
               AND i.estado_pago = 'PAGADO'
               AND mf.idmovimiento IS NULL",
            [
                $idsucursal,
                $inicioFisico
            ]
        );

        $this->agregarHallazgo(
            $hallazgos,
            'CRITICO',
            'COMPRA_SIN_MOVIMIENTO',
            'Compra pagada sin movimiento financiero',
            'Toda compra al contado PAGADA debe generar su EGRESO financiero.',
            (int)($sinMovimiento['total'] ?? 0)
        );

        $aperturaDistinta = $this->conexion->getData(
            "SELECT COUNT(*) AS total
             FROM ingreso i
             INNER JOIN movimiento_financiero mf
                ON mf.origen = 'COMPRA'
               AND mf.idreferencia = i.idingreso
               AND mf.estado = 'ACTIVO'
             WHERE i.idsucursal = ?
               AND i.fecha_hora >= ?
               AND i.idapertura IS NOT NULL
               AND mf.idapertura IS NOT NULL
               AND i.idapertura <> mf.idapertura",
            [
                $idsucursal,
                $inicioFisico
            ]
        );

        $this->agregarHallazgo(
            $hallazgos,
            'CRITICO',
            'COMPRA_APERTURA_DISTINTA',
            'Compra y egreso financiero apuntan a aperturas diferentes',
            'La compra y su movimiento de efectivo deben compartir exactamente el mismo idapertura.',
            (int)($aperturaDistinta['total'] ?? 0)
        );
    }

    private function auditarNotasCredito(
        int $idsucursal,
        ?string $inicioFisico,
        array &$hallazgos
    ): void {
        if ($inicioFisico === null) {
            return;
        }

        $cruzadas = $this->conexion->getData(
            "SELECT COUNT(*) AS total
             FROM nota_credito nc
             INNER JOIN nota_credito_pago ncp
                ON ncp.idnota_credito = nc.idnota_credito
             INNER JOIN movimiento_financiero mf
                ON mf.idmovimiento = ncp.idmovimiento
             WHERE nc.idsucursal = ?
               AND nc.created_at >= ?
               AND ncp.idapertura IS NOT NULL
               AND mf.idapertura IS NOT NULL
               AND ncp.idapertura <> mf.idapertura",
            [
                $idsucursal,
                $inicioFisico
            ]
        );

        $this->agregarHallazgo(
            $hallazgos,
            'CRITICO',
            'NC_APERTURA_DISTINTA',
            'Devolución y movimiento financiero usan aperturas diferentes',
            'La devolución efectiva y su EGRESO deben quedar en la misma apertura.',
            (int)($cruzadas['total'] ?? 0)
        );

        $pendientes = $this->conexion->getData(
            "SELECT COUNT(*) AS total
             FROM nota_credito nc
             INNER JOIN nota_credito_sunat ncs
                ON ncs.idnota_credito = nc.idnota_credito
             WHERE nc.idsucursal = ?
               AND nc.created_at >= ?
               AND nc.estado <> 'ANULADA'
               AND nc.genera_devolucion_dinero = 1
               AND nc.finanzas_aplicadas <> 1
               AND UPPER(ncs.estado_sunat) = 'ACEPTADO'",
            [
                $idsucursal,
                $inicioFisico
            ]
        );

        $this->agregarHallazgo(
            $hallazgos,
            'ADVERTENCIA',
            'NC_DEVOLUCION_PENDIENTE',
            'Notas aceptadas con devolución financiera pendiente',
            'No es una inconsistencia: deben procesarse desde una apertura válida antes de entregar el efectivo.',
            (int)($pendientes['total'] ?? 0)
        );
    }

    private function auditarCierres(
        int $idsucursal,
        array &$hallazgos
    ): array {
        $cierres = $this->conexion->getDataAll(
            "SELECT
                ca.idapertura,
                ca.idcaja,
                cf.codigo,
                cf.nombre,
                ca.fecha_cierre,
                cc.total_sistema AS total_cierre,
                cc.monto_contado,
                cc.diferencia
             FROM caja_apertura ca
             INNER JOIN caja_fisica cf
                ON cf.idcaja = ca.idcaja
             LEFT JOIN caja_cierre cc
                ON cc.caja_apertura_id = ca.idapertura
             WHERE ca.idsucursal = ?
               AND ca.estado = 'CERRADA'
             ORDER BY
                ca.fecha_cierre DESC,
                ca.idapertura DESC
             LIMIT 20",
            [$idsucursal]
        );

        $resultado = [];
        $descuadrados = 0;

        foreach (is_array($cierres) ? $cierres : [] as $cierre) {
            $idapertura = (int)$cierre['idapertura'];
            $actual = null;

            try {
                $totales =
                    $this->caja
                        ->calcularTotalesAperturaFisica(
                            $idapertura
                        );

                $actual = round(
                    (float)(
                        $totales['total_sistema']
                        ?? 0
                    ),
                    2
                );
            } catch (Throwable $e) {
                $actual = null;
            }

            $guardado =
                $cierre['total_cierre'] !== null
                    ? round(
                        (float)$cierre['total_cierre'],
                        2
                    )
                    : null;

            $diferenciaIntegridad =
                $actual !== null
                && $guardado !== null
                    ? round(
                        $actual - $guardado,
                        2
                    )
                    : null;

            if (
                $diferenciaIntegridad !== null
                && abs($diferenciaIntegridad) > 0.01
            ) {
                $descuadrados++;
            }

            $resultado[] = [
                'idapertura' => $idapertura,
                'codigo' => (string)$cierre['codigo'],
                'nombre' => (string)$cierre['nombre'],
                'fecha_cierre' =>
                    $cierre['fecha_cierre'],
                'total_cierre' => $guardado,
                'total_recalculado' => $actual,
                'diferencia_integridad' =>
                    $diferenciaIntegridad,
                'diferencia_arqueo' =>
                    $cierre['diferencia'] !== null
                        ? round(
                            (float)$cierre['diferencia'],
                            2
                        )
                        : null
            ];
        }

        $this->agregarHallazgo(
            $hallazgos,
            'CRITICO',
            'CIERRE_MUTADO',
            'Cierres cuyo total cambió después de cerrar',
            'El total recalculado de una apertura cerrada debe coincidir con el total_sistema guardado en el cierre.',
            $descuadrados
        );

        return $resultado;
    }
}
