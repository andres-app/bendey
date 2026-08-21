<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';

/**
 * Valida de forma centralizada la forma de pago y el contexto físico de caja.
 *
 * Importante:
 * - No confía únicamente en la sesión.
 * - Vuelve a comprobar usuario, sucursal, caja y apertura en la BD.
 * - Debe ejecutarse dentro de la misma transacción de la operación financiera.
 */
class CajaOperacionGuard
{
    private Conexion $conexion;

    public function __construct(?Conexion $conexion = null)
    {
        $this->conexion = $conexion ?? new Conexion();
    }

    public function prepararFormaPago(
        int $idformaPago,
        string $numeroOperacion,
        array $contexto
    ): array {
        if ($idformaPago <= 0) {
            throw new RuntimeException(
                'Seleccione una forma de pago válida.'
            );
        }

        $forma = $this->conexion->getData(
            "SELECT
                fp.idforma_pago,
                fp.nombre,
                fp.es_efectivo,
                fp.es_combinado,
                fp.activo,
                fp.condicion,

                fpd.idcuenta_financiera,
                fpd.requiere_caja_abierta,
                fpd.requiere_operacion,

                cf.nombre AS cuenta_financiera,
                cf.tipo AS tipo_cuenta,
                cf.activo AS cuenta_activa

             FROM forma_pago AS fp

             LEFT JOIN forma_pago_destino AS fpd
                ON fpd.idforma_pago = fp.idforma_pago

             LEFT JOIN cuenta_financiera AS cf
                ON cf.idcuenta_financiera = fpd.idcuenta_financiera

             WHERE fp.idforma_pago = ?
               AND fp.activo = 1
               AND fp.condicion = 1

             LIMIT 1
             FOR UPDATE",
            [$idformaPago]
        );

        if (!is_array($forma)) {
            throw new RuntimeException(
                'La forma de pago seleccionada no está disponible.'
            );
        }

        if ((int)($forma['es_combinado'] ?? 0) === 1) {
            throw new RuntimeException(
                'El pago mixto de compras se habilitará en una siguiente etapa. Use una sola forma de pago.'
            );
        }

        $idCuenta = (int)($forma['idcuenta_financiera'] ?? 0);

        if (
            $idCuenta <= 0
            || (int)($forma['cuenta_activa'] ?? 0) !== 1
        ) {
            throw new RuntimeException(
                'La forma de pago '
                . (string)$forma['nombre']
                . ' no tiene una cuenta financiera activa configurada.'
            );
        }

        $numeroOperacion = trim($numeroOperacion);

        if (
            (int)($forma['requiere_operacion'] ?? 0) === 1
            && $numeroOperacion === ''
        ) {
            throw new RuntimeException(
                'Ingrese el número de operación para '
                . (string)$forma['nombre']
                . '.'
            );
        }

        $idApertura = null;
        $idCaja = null;
        $idSucursal = null;

        if ((int)($forma['requiere_caja_abierta'] ?? 0) === 1) {
            $contextoCaja = $this->validarAperturaFisica($contexto);

            $idApertura = $contextoCaja['idapertura'];
            $idCaja = $contextoCaja['idcaja'];
            $idSucursal = $contextoCaja['idsucursal'];
        } else {
            $idsucursalSesion = (int)(
                $contexto['idsucursal']
                ?? 0
            );

            $idSucursal = $idsucursalSesion > 0
                ? $idsucursalSesion
                : null;
        }

        return [
            'idforma_pago' => (int)$forma['idforma_pago'],
            'forma_pago' => (string)$forma['nombre'],
            'es_efectivo' => (int)($forma['es_efectivo'] ?? 0),
            'requiere_caja_abierta' =>
                (int)($forma['requiere_caja_abierta'] ?? 0),
            'requiere_operacion' =>
                (int)($forma['requiere_operacion'] ?? 0),
            'idcuenta_financiera' => $idCuenta,
            'cuenta_financiera' =>
                (string)($forma['cuenta_financiera'] ?? ''),
            'idapertura' => $idApertura,
            'idcaja' => $idCaja,
            'idsucursal' => $idSucursal,
            'numero_operacion' =>
                $numeroOperacion !== ''
                    ? $numeroOperacion
                    : null
        ];
    }

    private function validarAperturaFisica(
        array $contexto
    ): array {
        $idusuario = (int)($contexto['idusuario'] ?? 0);
        $idsucursal = (int)($contexto['idsucursal'] ?? 0);
        $idcaja = (int)($contexto['idcaja'] ?? 0);
        $idapertura = (int)($contexto['idapertura'] ?? 0);
        $modoCaja = strtoupper(
            trim((string)($contexto['modo_caja'] ?? 'LEGACY'))
        );

        if ($idusuario <= 0) {
            throw new RuntimeException(
                'La sesión del usuario no es válida.'
            );
        }

        /*
         * Compatibilidad con instalaciones que aún estén en LEGACY.
         */
        if ($modoCaja === 'LEGACY') {
            if ($idapertura > 0) {
                $apertura = $this->conexion->getData(
                    "SELECT
                        idapertura,
                        idsucursal,
                        idcaja,
                        estado
                     FROM caja_apertura
                     WHERE idapertura = ?
                       AND estado = 'ABIERTA'
                     LIMIT 1
                     FOR UPDATE",
                    [$idapertura]
                );

                if (is_array($apertura)) {
                    return [
                        'idapertura' =>
                            (int)$apertura['idapertura'],
                        'idsucursal' =>
                            (int)($apertura['idsucursal'] ?? 0) ?: null,
                        'idcaja' =>
                            (int)($apertura['idcaja'] ?? 0) ?: null
                    ];
                }
            }

            $aperturas = $this->conexion->getDataAll(
                "SELECT
                    idapertura,
                    idsucursal,
                    idcaja
                 FROM caja_apertura
                 WHERE estado = 'ABIERTA'
                   AND (
                        idusuario = ?
                        OR idusuario_apertura = ?
                        OR idusuario_responsable = ?
                   )
                 ORDER BY idapertura DESC
                 LIMIT 2
                 FOR UPDATE",
                [
                    $idusuario,
                    $idusuario,
                    $idusuario
                ]
            );

            if (count($aperturas) !== 1) {
                throw new RuntimeException(
                    count($aperturas) === 0
                        ? 'Debe abrir la caja antes de registrar un movimiento en efectivo.'
                        : 'Existe más de una apertura posible. Seleccione una caja física antes de continuar.'
                );
            }

            return [
                'idapertura' =>
                    (int)$aperturas[0]['idapertura'],
                'idsucursal' =>
                    (int)($aperturas[0]['idsucursal'] ?? 0) ?: null,
                'idcaja' =>
                    (int)($aperturas[0]['idcaja'] ?? 0) ?: null
            ];
        }

        if ($idsucursal <= 0) {
            throw new RuntimeException(
                'No existe una sucursal activa para esta operación.'
            );
        }

        if ($idcaja <= 0) {
            throw new RuntimeException(
                'Seleccione la caja que utilizará antes de registrar un movimiento en efectivo.'
            );
        }

        if ($idapertura <= 0) {
            throw new RuntimeException(
                'Debe abrir la caja antes de registrar un movimiento en efectivo.'
            );
        }

        /*
         * Revalidación de permisos en la BD. No basta con que la sesión
         * conserve un idcaja de una autorización anterior.
         */
        $autorizacion = $this->conexion->getData(
            "SELECT
                uc.idusuario_caja
             FROM usuario_caja AS uc
             INNER JOIN caja_fisica AS cf
                ON cf.idcaja = uc.idcaja
             INNER JOIN usuario_sucursal AS us
                ON us.idusuario = uc.idusuario
               AND us.idsucursal = cf.idsucursal
             INNER JOIN usuario AS u
                ON u.idusuario = uc.idusuario
             WHERE uc.idusuario = ?
               AND uc.idcaja = ?
               AND cf.idsucursal = ?
               AND uc.activo = 1
               AND uc.puede_operar = 1
               AND cf.activo = 1
               AND us.activo = 1
               AND u.condicion = 1
             LIMIT 1
             FOR UPDATE",
            [
                $idusuario,
                $idcaja,
                $idsucursal
            ]
        );

        if (!is_array($autorizacion)) {
            throw new RuntimeException(
                'Ya no tiene autorización para operar la caja seleccionada.'
            );
        }

        $apertura = $this->conexion->getData(
            "SELECT
                idapertura,
                idsucursal,
                idcaja,
                estado
             FROM caja_apertura
             WHERE idapertura = ?
               AND idsucursal = ?
               AND idcaja = ?
               AND estado = 'ABIERTA'
             LIMIT 1
             FOR UPDATE",
            [
                $idapertura,
                $idsucursal,
                $idcaja
            ]
        );

        if (!is_array($apertura)) {
            throw new RuntimeException(
                'La caja seleccionada ya no tiene una apertura activa. Vuelva a abrirla antes de continuar.'
            );
        }

        return [
            'idapertura' => (int)$apertura['idapertura'],
            'idsucursal' => (int)$apertura['idsucursal'],
            'idcaja' => (int)$apertura['idcaja']
        ];
    }
}
