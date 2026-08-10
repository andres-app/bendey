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
    | ESTADO SUNAT: UNA SOLA FUENTE DE VERDAD
    |--------------------------------------------------------------------------
    | Factura / Boleta: venta_sunat.estado_sunat
    | Nota de crédito: nota_credito_sunat.estado_sunat
    |
    | document_id solo se usa como respaldo cuando el estado está vacío.
    | Nunca se reemplaza un ERROR/RECHAZADO/etc. por NO_ENVIADO solo porque
    | document_id esté vacío.
    */

    /**
     * Bandeja unificada: facturas, boletas y notas de crédito.
     */
    public function listar(): array
    {
        $sql = "
            SELECT *
            FROM (
                SELECT
                    'VENTA' AS tipo_registro,
                    v.idventa AS iddocumento,
                    v.idventa,
                    NULL AS idnota_credito,

                    CONCAT(
                        v.serie_comprobante,
                        '-',
                        v.num_comprobante
                    ) AS comprobante,

                    v.tipo_comprobante AS tipo_documento,

                    COALESCE(
                        NULLIF(TRIM(vs.tipo_documento_sunat), ''),
                        CASE
                            WHEN v.tipo_comprobante = 'Factura Electrónica' THEN '01'
                            WHEN v.tipo_comprobante = 'Boleta Electrónica' THEN '03'
                            ELSE ''
                        END
                    ) AS tipo_documento_sunat,

                    '' AS comprobante_origen,

                    COALESCE(
                        p.nombre,
                        'SIN CLIENTE'
                    ) AS cliente,

                    v.total_venta AS total,
                    v.fecha_hora AS fecha_hora_raw,

                    DATE_FORMAT(
                        v.fecha_hora,
                        '%d/%m/%Y %H:%i'
                    ) AS fecha,

                    vs.document_id,
                    vs.file_name,
                    vs.xml,
                    vs.cdr,
                    vs.xml_local,
                    vs.cdr_local,

                    CASE
                        WHEN vs.idventa_sunat IS NULL
                        THEN 'NO_ENVIADO'

                        WHEN TRIM(
                            COALESCE(
                                vs.estado_sunat,
                                ''
                            )
                        ) <> ''
                        THEN UPPER(
                            TRIM(vs.estado_sunat)
                        )

                        WHEN COALESCE(
                            vs.document_id,
                            ''
                        ) = ''
                        THEN 'NO_ENVIADO'

                        ELSE 'PENDIENTE'
                    END AS estado_sunat,

                    COALESCE(
                        vs.mensaje_sunat,
                        ''
                    ) AS mensaje_sunat,

                    vs.faults,
                    vs.notes

                FROM venta v

                LEFT JOIN persona p
                    ON p.idpersona = v.idcliente

                LEFT JOIN venta_sunat vs
                    ON vs.idventa = v.idventa

                WHERE v.tipo_comprobante IN (
                    'Factura Electrónica',
                    'Boleta Electrónica'
                )

                UNION ALL

                SELECT
                    'NOTA_CREDITO' AS tipo_registro,
                    nc.idnota_credito AS iddocumento,
                    nc.idventa,
                    nc.idnota_credito,

                    CONCAT(
                        nc.serie_comprobante,
                        '-',
                        nc.num_comprobante
                    ) AS comprobante,

                    'Nota de Crédito Electrónica' AS tipo_documento,
                    '07' AS tipo_documento_sunat,

                    CONCAT(
                        nc.serie_documento_modificado,
                        '-',
                        nc.numero_documento_modificado
                    ) AS comprobante_origen,

                    COALESCE(
                        NULLIF(TRIM(nc.cliente_nombre), ''),
                        'SIN CLIENTE'
                    ) AS cliente,

                    nc.total_nota AS total,
                    nc.fecha_hora AS fecha_hora_raw,

                    DATE_FORMAT(
                        nc.fecha_hora,
                        '%d/%m/%Y %H:%i'
                    ) AS fecha,

                    ncs.document_id,
                    ncs.file_name,
                    ncs.xml,
                    ncs.cdr,
                    ncs.xml_local,
                    ncs.cdr_local,

                    CASE
                        WHEN ncs.idnota_credito IS NULL
                        THEN 'NO_ENVIADO'

                        WHEN TRIM(
                            COALESCE(
                                ncs.estado_sunat,
                                ''
                            )
                        ) <> ''
                        THEN UPPER(
                            TRIM(ncs.estado_sunat)
                        )

                        WHEN COALESCE(
                            ncs.document_id,
                            ''
                        ) = ''
                        THEN 'NO_ENVIADO'

                        ELSE 'PENDIENTE'
                    END AS estado_sunat,

                    COALESCE(
                        ncs.mensaje_sunat,
                        ''
                    ) AS mensaje_sunat,

                    ncs.faults,
                    ncs.notes

                FROM nota_credito nc

                LEFT JOIN nota_credito_sunat ncs
                    ON ncs.idnota_credito = nc.idnota_credito
            ) documentos

            ORDER BY
                documentos.fecha_hora_raw DESC,
                documentos.iddocumento DESC
        ";

        $resultado = $this->conexion->getDataAll($sql);

        return is_array($resultado)
            ? $resultado
            : [];
    }

    /**
     * Detalle SUNAT de una factura o boleta.
     */
    public function detalle(int $idventa): ?array
    {
        if ($idventa <= 0) {
            return null;
        }

        $sql = "
            SELECT
                'VENTA' AS tipo_registro,
                v.idventa AS iddocumento,
                v.idventa,
                NULL AS idnota_credito,

                v.tipo_comprobante AS tipo_documento,

                COALESCE(
                    NULLIF(TRIM(vs.tipo_documento_sunat), ''),
                    CASE
                        WHEN v.tipo_comprobante = 'Factura Electrónica' THEN '01'
                        WHEN v.tipo_comprobante = 'Boleta Electrónica' THEN '03'
                        ELSE ''
                    END
                ) AS tipo_documento_sunat,

                CONCAT(
                    v.serie_comprobante,
                    '-',
                    v.num_comprobante
                ) AS comprobante,

                '' AS comprobante_origen,

                COALESCE(
                    p.nombre,
                    'SIN CLIENTE'
                ) AS cliente,

                v.total_venta AS total,

                vs.document_id,
                vs.file_name,
                vs.xml,
                vs.cdr,
                vs.xml_local,
                vs.cdr_local,

                CASE
                    WHEN vs.idventa_sunat IS NULL
                    THEN 'NO_ENVIADO'

                    WHEN TRIM(
                        COALESCE(
                            vs.estado_sunat,
                            ''
                        )
                    ) <> ''
                    THEN UPPER(TRIM(vs.estado_sunat))

                    WHEN COALESCE(vs.document_id, '') = ''
                    THEN 'NO_ENVIADO'

                    ELSE 'PENDIENTE'
                END AS estado_sunat,

                COALESCE(vs.mensaje_sunat, '') AS mensaje_sunat,
                vs.faults,
                vs.notes,
                vs.fecha_envio,
                vs.fecha_respuesta,
                vs.fecha_ultima_consulta,
                vs.fecha_descarga_archivos

            FROM venta v

            LEFT JOIN persona p
                ON p.idpersona = v.idcliente

            LEFT JOIN venta_sunat vs
                ON vs.idventa = v.idventa

            WHERE v.idventa = ?
              AND v.tipo_comprobante IN (
                  'Factura Electrónica',
                  'Boleta Electrónica'
              )

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

    /**
     * Detalle SUNAT de una nota de crédito.
     */
    public function detalleNotaCredito(
        int $idnotaCredito
    ): ?array {
        if ($idnotaCredito <= 0) {
            return null;
        }

        $sql = "
            SELECT
                'NOTA_CREDITO' AS tipo_registro,
                nc.idnota_credito AS iddocumento,
                nc.idventa,
                nc.idnota_credito,

                'Nota de Crédito Electrónica' AS tipo_documento,
                '07' AS tipo_documento_sunat,

                CONCAT(
                    nc.serie_comprobante,
                    '-',
                    nc.num_comprobante
                ) AS comprobante,

                CONCAT(
                    nc.serie_documento_modificado,
                    '-',
                    nc.numero_documento_modificado
                ) AS comprobante_origen,

                COALESCE(
                    NULLIF(TRIM(nc.cliente_nombre), ''),
                    'SIN CLIENTE'
                ) AS cliente,

                nc.total_nota AS total,

                ncs.document_id,
                ncs.file_name,
                ncs.xml,
                ncs.cdr,
                ncs.xml_local,
                ncs.cdr_local,

                CASE
                    WHEN ncs.idnota_credito IS NULL
                    THEN 'NO_ENVIADO'

                    WHEN TRIM(
                        COALESCE(
                            ncs.estado_sunat,
                            ''
                        )
                    ) <> ''
                    THEN UPPER(TRIM(ncs.estado_sunat))

                    WHEN COALESCE(ncs.document_id, '') = ''
                    THEN 'NO_ENVIADO'

                    ELSE 'PENDIENTE'
                END AS estado_sunat,

                COALESCE(ncs.mensaje_sunat, '') AS mensaje_sunat,
                ncs.faults,
                ncs.notes,
                ncs.fecha_envio,
                ncs.fecha_respuesta,
                ncs.fecha_descarga_archivos

            FROM nota_credito nc

            LEFT JOIN nota_credito_sunat ncs
                ON ncs.idnota_credito = nc.idnota_credito

            WHERE nc.idnota_credito = ?

            LIMIT 1
        ";

        $resultado = $this->conexion->getData(
            $sql,
            [$idnotaCredito]
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    /**
     * Cantidad de documentos electrónicos pendientes de ENVÍO.
     *
     * El contador del header NO representa incidencias ni documentos
     * en procesamiento. Solo muestra documentos que todavía no han sido
     * enviados a APISUNAT:
     *
     * - Factura/boleta sin fila en venta_sunat.
     * - Factura/boleta con estado NO_ENVIADO.
     * - Nota de crédito sin fila en nota_credito_sunat.
     * - Nota de crédito con estado NO_ENVIADO.
     *
     * PENDIENTE, EN_PROCESO, ENVIADO, ACEPTADO, RECHAZADO,
     * EXCEPCION y ERROR no se incluyen.
     */
    public function contarPendientes(): int
    {
        $sql = "
            SELECT
                (
                    SELECT COUNT(*)

                    FROM venta v

                    LEFT JOIN venta_sunat vs
                        ON vs.idventa = v.idventa

                    WHERE v.tipo_comprobante IN (
                        'Factura Electrónica',
                        'Boleta Electrónica'
                    )

                      AND v.estado = 'Aceptado'

                      AND (
                            vs.idventa_sunat IS NULL

                            OR UPPER(
                                TRIM(
                                    COALESCE(
                                        vs.estado_sunat,
                                        ''
                                    )
                                )
                            ) = 'NO_ENVIADO'

                            OR (
                                TRIM(
                                    COALESCE(
                                        vs.estado_sunat,
                                        ''
                                    )
                                ) = ''

                                AND COALESCE(
                                    vs.document_id,
                                    ''
                                ) = ''
                            )
                      )
                )
                +
                (
                    SELECT COUNT(*)

                    FROM nota_credito nc

                    LEFT JOIN nota_credito_sunat ncs
                        ON ncs.idnota_credito =
                           nc.idnota_credito

                    WHERE UPPER(
                        TRIM(
                            COALESCE(
                                nc.estado,
                                ''
                            )
                        )
                    ) <> 'ANULADA'

                      AND (
                            ncs.idnota_credito IS NULL

                            OR UPPER(
                                TRIM(
                                    COALESCE(
                                        ncs.estado_sunat,
                                        ''
                                    )
                                )
                            ) = 'NO_ENVIADO'

                            OR (
                                TRIM(
                                    COALESCE(
                                        ncs.estado_sunat,
                                        ''
                                    )
                                ) = ''

                                AND COALESCE(
                                    ncs.document_id,
                                    ''
                                ) = ''
                            )
                      )
                ) AS cantidad
        ";

        $resultado = $this->conexion->getData(
            $sql
        );

        return max(
            (int)($resultado['cantidad'] ?? 0),
            0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER XML O CDR DE FACTURA / BOLETA
    |--------------------------------------------------------------------------
    */
    public function obtenerArchivo(
        int $idventa,
        string $tipo
    ): ?array {
        if ($idventa <= 0) {
            return null;
        }

        $tipo = strtolower(trim($tipo));

        if (!in_array($tipo, ['xml', 'cdr'], true)) {
            return null;
        }

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

    public function actualizarRutaLocal(
        int $idventa,
        string $tipo,
        string $ruta
    ): bool {
        if ($idventa <= 0) {
            return false;
        }

        $tipo = strtolower(trim($tipo));
        $ruta = trim($ruta);

        if (
            !in_array($tipo, ['xml', 'cdr'], true)
            || $ruta === ''
        ) {
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
            [$ruta, $idventa]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | COMPATIBILIDAD: ENVÍO MANUAL DE FACTURAS / BOLETAS
    |--------------------------------------------------------------------------
    */
    public function puedeEnviarManual(
        int $idventa
    ): bool {
        if ($idventa <= 0) {
            return false;
        }

        $resultado = $this->conexion->getData(
            "SELECT
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
             LIMIT 1",
            [$idventa]
        );

        if (!is_array($resultado)) {
            return false;
        }

        if ((string)($resultado['estado'] ?? '') !== 'Aceptado') {
            return false;
        }

        return trim(
            (string)($resultado['document_id'] ?? '')
        ) === '';
    }

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
                COALESCE(p.nombre, 'SIN CLIENTE') AS cliente,
                v.total_venta AS total,
                v.fecha_hora

            FROM venta v

            LEFT JOIN persona p
                ON p.idpersona = v.idcliente

            LEFT JOIN venta_sunat vs
                ON vs.idventa = v.idventa

            WHERE v.tipo_comprobante IN (
                'Factura Electrónica',
                'Boleta Electrónica'
            )
              AND v.estado = 'Aceptado'
              AND (
                  vs.idventa_sunat IS NULL
                  OR UPPER(
                      TRIM(
                          COALESCE(
                              vs.estado_sunat,
                              'NO_ENVIADO'
                          )
                      )
                  ) = 'NO_ENVIADO'
              )

            ORDER BY
                v.tipo_comprobante ASC,
                v.serie_comprobante ASC,
                CAST(v.num_comprobante AS UNSIGNED) ASC
        ";

        $resultado = $this->conexion->getDataAll($sql);

        return is_array($resultado)
            ? $resultado
            : [];
    }
}
