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

    /*
    |--------------------------------------------------------------------------
    | LISTAR COMPROBANTES ELECTRÓNICOS
    |--------------------------------------------------------------------------
    |
    | NO_ENVIADO:
    | La venta está registrada, pero todavía no tiene documentId.
    |
    | PENDIENTE:
    | APISUNAT recibió el comprobante y todavía lo procesa.
    |
    | ACEPTADO:
    | SUNAT aceptó el comprobante.
    |
    */
    public function listar(): array
    {
        $sql = "
            SELECT *
            FROM (
                SELECT
                    'VENTA' AS tipo_origen,
                    v.idventa AS idreferencia,
                    v.idventa,
                    NULL AS idnota_credito,

                    CONCAT(
                        v.serie_comprobante,
                        '-',
                        v.num_comprobante
                    ) AS comprobante,

                    v.tipo_comprobante,
                    v.serie_comprobante,
                    v.num_comprobante,
                    v.estado AS estado_documento,
                    p.nombre AS cliente,
                    v.total_venta AS total,

                    vs.idventa_sunat AS idregistro_sunat,
                    vs.document_id,
                    vs.file_name,
                    vs.xml,
                    vs.cdr,
                    vs.xml_local,
                    vs.cdr_local,

                    CASE
                        WHEN v.estado <> 'Aceptado'
                        THEN 'ANULADO'
                        WHEN vs.idventa_sunat IS NULL
                        THEN 'NO_ENVIADO'
                        WHEN COALESCE(vs.document_id, '') = ''
                        THEN 'NO_ENVIADO'
                        WHEN COALESCE(vs.estado_sunat, '') = ''
                        THEN 'PENDIENTE'
                        ELSE UPPER(vs.estado_sunat)
                    END AS estado_sunat,

                    vs.mensaje_sunat,

                    CASE
                        WHEN v.estado = 'Aceptado'
                         AND COALESCE(vs.document_id, '') = ''
                        THEN 1 ELSE 0
                    END AS puede_enviar_manual,

                    CASE
                        WHEN COALESCE(vs.document_id, '') <> ''
                         AND UPPER(COALESCE(vs.estado_sunat, '')) IN (
                            'PENDIENTE', 'EN_PROCESO', 'ENVIADO'
                         )
                        THEN 1 ELSE 0
                    END AS puede_consultar,

                    DATE_FORMAT(v.fecha_hora, '%d/%m/%Y %H:%i') AS fecha,
                    v.fecha_hora AS fecha_orden

                FROM venta v
                INNER JOIN persona p
                    ON p.idpersona = v.idcliente
                LEFT JOIN venta_sunat vs
                    ON vs.idventa = v.idventa
                WHERE v.tipo_comprobante IN (
                    'Factura Electrónica',
                    'Boleta Electrónica'
                )

                UNION ALL

                SELECT
                    'NOTA_CREDITO' AS tipo_origen,
                    nc.idnota_credito AS idreferencia,
                    nc.idventa,
                    nc.idnota_credito,

                    CONCAT(
                        nc.serie_comprobante,
                        '-',
                        nc.num_comprobante
                    ) AS comprobante,

                    'Nota de Crédito Electrónica' AS tipo_comprobante,
                    nc.serie_comprobante,
                    nc.num_comprobante,
                    nc.estado AS estado_documento,
                    nc.cliente_nombre AS cliente,
                    nc.total_nota AS total,

                    ncs.idnota_credito_sunat AS idregistro_sunat,
                    ncs.document_id,
                    ncs.file_name,
                    ncs.xml,
                    ncs.cdr,
                    ncs.xml_local,
                    ncs.cdr_local,

                    CASE
                        WHEN nc.estado = 'ANULADA'
                        THEN 'ANULADO'
                        WHEN ncs.idnota_credito_sunat IS NULL
                        THEN 'NO_ENVIADO'
                        WHEN COALESCE(ncs.document_id, '') = ''
                        THEN 'NO_ENVIADO'
                        WHEN COALESCE(ncs.estado_sunat, '') = ''
                        THEN 'PENDIENTE'
                        ELSE UPPER(ncs.estado_sunat)
                    END AS estado_sunat,

                    CONCAT(
                        'Modifica ',
                        nc.serie_documento_modificado,
                        '-',
                        nc.numero_documento_modificado,
                        CASE
                            WHEN COALESCE(ncs.mensaje_sunat, '') <> ''
                            THEN CONCAT(' · ', ncs.mensaje_sunat)
                            ELSE ''
                        END
                    ) AS mensaje_sunat,

                    CASE
                        WHEN nc.estado = 'REGISTRADA'
                         AND COALESCE(ncs.document_id, '') = ''
                        THEN 1 ELSE 0
                    END AS puede_enviar_manual,

                    CASE
                        WHEN COALESCE(ncs.document_id, '') <> ''
                         AND UPPER(COALESCE(ncs.estado_sunat, '')) IN (
                            'PENDIENTE', 'EN_PROCESO', 'ENVIADO'
                         )
                        THEN 1 ELSE 0
                    END AS puede_consultar,

                    DATE_FORMAT(nc.fecha_hora, '%d/%m/%Y %H:%i') AS fecha,
                    nc.fecha_hora AS fecha_orden

                FROM nota_credito nc
                LEFT JOIN nota_credito_sunat ncs
                    ON ncs.idnota_credito = nc.idnota_credito
            ) documentos
            ORDER BY fecha_orden DESC, idreferencia DESC
        ";

        $resultado = $this->conexion->getDataAll($sql);

        return is_array($resultado)
            ? $resultado
            : [];
    }

    /*
    |--------------------------------------------------------------------------
    | DETALLE DE UN COMPROBANTE
    |--------------------------------------------------------------------------
    */
    public function detalle(
        int $idventa
    ): ?array {
        if ($idventa <= 0) {
            return null;
        }

        $sql = "
            SELECT
                v.idventa,
                v.tipo_comprobante,
                v.serie_comprobante,
                v.num_comprobante,
                v.estado AS estado_venta,

                CONCAT(
                    v.serie_comprobante,
                    '-',
                    v.num_comprobante
                ) AS comprobante,

                p.nombre AS cliente,
                p.tipo_documento,
                p.num_documento,

                v.total_venta AS total,

                vs.idventa_sunat,
                vs.document_id,
                vs.file_name,

                vs.xml,
                vs.cdr,
                vs.xml_local,
                vs.cdr_local,

                CASE
                    WHEN v.estado <> 'Aceptado'
                    THEN 'ANULADO'

                    WHEN vs.idventa_sunat IS NULL
                    THEN 'NO_ENVIADO'

                    WHEN COALESCE(
                        vs.document_id,
                        ''
                    ) = ''
                    THEN 'NO_ENVIADO'

                    WHEN COALESCE(
                        vs.estado_sunat,
                        ''
                    ) = ''
                    THEN 'PENDIENTE'

                    ELSE UPPER(
                        vs.estado_sunat
                    )
                END AS estado_sunat,

                vs.mensaje_sunat,
                vs.faults,
                vs.notes,
                vs.intentos_consulta,
                vs.fecha_envio,
                vs.fecha_respuesta,
                vs.fecha_ultima_consulta,
                vs.fecha_descarga_archivos,

                CASE
                    WHEN v.estado = 'Aceptado'
                     AND COALESCE(
                        vs.document_id,
                        ''
                     ) = ''
                    THEN 1
                    ELSE 0
                END AS puede_enviar_manual,

                CASE
                    WHEN COALESCE(
                        vs.document_id,
                        ''
                    ) <> ''
                     AND UPPER(
                        COALESCE(
                            vs.estado_sunat,
                            ''
                        )
                     ) IN (
                        'PENDIENTE',
                        'EN_PROCESO',
                        'ENVIADO'
                     )
                    THEN 1
                    ELSE 0
                END AS puede_consultar

            FROM venta v

            INNER JOIN persona p
                ON p.idpersona = v.idcliente

            LEFT JOIN venta_sunat vs
                ON vs.idventa = v.idventa

            WHERE v.idventa = ?

            LIMIT 1
        ";

        $resultado = $this->conexion->getData(
            $sql,
            [$idventa]
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER XML O CDR
    |--------------------------------------------------------------------------
    */
    public function obtenerArchivo(
        int $idventa,
        string $tipo
    ): ?array {
        if ($idventa <= 0) {
            return null;
        }

        $tipo = strtolower(
            trim($tipo)
        );

        if (
            !in_array(
                $tipo,
                ['xml', 'cdr'],
                true
            )
        ) {
            return null;
        }

        /*
         * Las columnas se construyen solamente después
         * de validar el tipo contra la lista permitida.
         */
        $columnaUrl = $tipo;
        $columnaLocal = $tipo . '_local';

        $sql = "
            SELECT
                idventa,
                document_id,
                file_name,

                {$columnaUrl} AS url,
                {$columnaLocal} AS ruta_local,

                estado_sunat

            FROM venta_sunat

            WHERE idventa = ?

            LIMIT 1
        ";

        $resultado = $this->conexion->getData(
            $sql,
            [$idventa]
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR RUTA LOCAL DEL XML O CDR
    |--------------------------------------------------------------------------
    */
    public function actualizarRutaLocal(
        int $idventa,
        string $tipo,
        string $ruta
    ): bool {
        if ($idventa <= 0) {
            return false;
        }

        $tipo = strtolower(
            trim($tipo)
        );

        $ruta = trim(
            $ruta
        );

        if (
            !in_array(
                $tipo,
                ['xml', 'cdr'],
                true
            )
        ) {
            return false;
        }

        if ($ruta === '') {
            return false;
        }

        $columna = $tipo . '_local';

        $sql = "
            UPDATE venta_sunat

            SET
                {$columna} = ?,
                fecha_descarga_archivos = NOW()

            WHERE idventa = ?
        ";

        return (bool)$this->conexion->setData(
            $sql,
            [
                $ruta,
                $idventa
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | COMPROBAR SI PUEDE ENVIARSE MANUALMENTE
    |--------------------------------------------------------------------------
    */
    public function puedeEnviarManual(
        int $idventa
    ): bool {
        if ($idventa <= 0) {
            return false;
        }

        $resultado = $this->conexion->getData(
            "
            SELECT
                v.idventa,
                v.estado,
                vs.document_id

            FROM venta v

            LEFT JOIN venta_sunat vs
                ON vs.idventa = v.idventa

            WHERE v.idventa = ?
              AND v.tipo_comprobante IN (
                  'Factura Electrónica',
                  'Boleta Electrónica'
              )

            LIMIT 1
            ",
            [$idventa]
        );

        if (!is_array($resultado)) {
            return false;
        }

        if (
            (string)($resultado['estado'] ?? '')
            !== 'Aceptado'
        ) {
            return false;
        }

        return trim(
            (string)(
                $resultado['document_id']
                ?? ''
            )
        ) === '';
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER COMPROBANTES PENDIENTES DE ENVÍO
    |--------------------------------------------------------------------------
    |
    | Se ordenan por tipo, serie y correlativo para respetar
    | la secuencia exigida por APISUNAT.
    |
    */
    public function listarPendientesEnvio(): array
    {
        $sql = "
            SELECT
                v.idventa,
                v.tipo_comprobante,
                v.serie_comprobante,
                v.num_comprobante,

                CONCAT(
                    v.serie_comprobante,
                    '-',
                    v.num_comprobante
                ) AS comprobante,

                p.nombre AS cliente,
                v.total_venta AS total,
                v.fecha_hora

            FROM venta v

            INNER JOIN persona p
                ON p.idpersona = v.idcliente

            LEFT JOIN venta_sunat vs
                ON vs.idventa = v.idventa

            WHERE v.tipo_comprobante IN (
                'Factura Electrónica',
                'Boleta Electrónica'
            )

              AND v.estado = 'Aceptado'

              AND COALESCE(
                    vs.document_id,
                    ''
                  ) = ''

            ORDER BY
                v.tipo_comprobante ASC,
                v.serie_comprobante ASC,
                CAST(
                    v.num_comprobante
                    AS UNSIGNED
                ) ASC
        ";

        $resultado = $this->conexion->getDataAll(
            $sql
        );

        return is_array($resultado)
            ? $resultado
            : [];
    }

    /*
    |--------------------------------------------------------------------------
    | CONTAR COMPROBANTES PENDIENTES DE ENVÍO
    |--------------------------------------------------------------------------
    |
    | Cuenta facturas y boletas electrónicas aceptadas localmente
    | que todavía no tienen document_id registrado por APISUNAT.
    |
    */
    public function contarPendientesEnvio(): int
    {
        $resultado = $this->conexion->getData(
            "SELECT
                (
                    SELECT COUNT(DISTINCT v.idventa)
                    FROM venta v
                    LEFT JOIN venta_sunat vs
                        ON vs.idventa = v.idventa
                    WHERE v.tipo_comprobante IN (
                        'Factura Electrónica',
                        'Boleta Electrónica'
                    )
                      AND v.estado = 'Aceptado'
                      AND COALESCE(vs.document_id, '') = ''
                )
                +
                (
                    SELECT COUNT(DISTINCT nc.idnota_credito)
                    FROM nota_credito nc
                    LEFT JOIN nota_credito_sunat ncs
                        ON ncs.idnota_credito = nc.idnota_credito
                    WHERE nc.estado = 'REGISTRADA'
                      AND COALESCE(ncs.document_id, '') = ''
                ) AS cantidad"
        );

        return is_array($resultado)
            ? max(0, (int)($resultado['cantidad'] ?? 0))
            : 0;
    }

}