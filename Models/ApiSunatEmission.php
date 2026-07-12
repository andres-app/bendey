<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/ApiSunat.php';
require_once __DIR__ . '/ApiSunatDocument.php';

class ApiSunatEmission
{
    private PDO $pdo;
    private ApiSunat $apiSunat;
    private ApiSunatDocument $documento;

    public function __construct(
        ?PDO $pdo = null,
        ?ApiSunat $apiSunat = null,
        ?ApiSunatDocument $documento = null
    ) {
        $this->pdo = $pdo ?? Conexion::conectar();

        $this->pdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $this->pdo->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );

        $this->apiSunat = $apiSunat ?? new ApiSunat();
        $this->documento = $documento
            ?? new ApiSunatDocument();
    }

    /**
     * Envía una venta real a APISUNAT.
     *
     * Antes de enviarla:
     * - valida que no haya sido enviada;
     * - consulta el último correlativo;
     * - registra la solicitud en venta_sunat.
     */
    public function enviarVenta(int $idventa): array
    {
        if ($idventa <= 0) {
            throw new InvalidArgumentException(
                'El ID de venta no es válido.'
            );
        }

        $comprobante = $this->documento->construir(
            $idventa
        );

        $this->validarComprobante(
            $comprobante
        );

        $registroActual = $this->obtenerRegistroVenta(
            $idventa
        );

        $this->validarQueNoFueEnviado(
            $registroActual
        );

        /*
        |--------------------------------------------------------------------------
        | Verificar correlativo en APISUNAT
        |--------------------------------------------------------------------------
        */
        $ultimoDocumento =
            $this->apiSunat->obtenerUltimoDocumento(
                (string)$comprobante['tipoSunat'],
                (string)$comprobante['serie']
            );

        if (
            ($ultimoDocumento['success'] ?? false)
            !== true
        ) {
            throw new RuntimeException(
                'No se pudo verificar el correlativo en APISUNAT: '
                . (
                    $ultimoDocumento['message']
                    ?? 'Sin detalle.'
                )
            );
        }

        if (
            ($ultimoDocumento['production'] ?? null)
            !== true
        ) {
            throw new RuntimeException(
                'Las credenciales configuradas no corresponden al ambiente de producción.'
            );
        }

        $numeroEsperado = str_pad(
            (string)(
                $ultimoDocumento['suggestedNumber']
                ?? ''
            ),
            8,
            '0',
            STR_PAD_LEFT
        );

        $numeroVenta = str_pad(
            (string)$comprobante['numero'],
            8,
            '0',
            STR_PAD_LEFT
        );

        if ($numeroEsperado !== $numeroVenta) {
            throw new RuntimeException(
                'El correlativo local no coincide con APISUNAT. '
                . 'APISUNAT espera '
                . $comprobante['serie']
                . '-'
                . $numeroEsperado
                . ', pero la venta tiene '
                . $comprobante['serie']
                . '-'
                . $numeroVenta
                . '.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Registrar reserva antes del envío
        |--------------------------------------------------------------------------
        */
        $requestSeguro =
            $this->apiSunat->crearRequestSeguro(
                (string)$comprobante['fileName'],
                (array)$comprobante['documentBody'],
                $comprobante['customerEmail'] ?? null
            );

        $this->reservarEnvio(
            $idventa,
            $comprobante,
            $requestSeguro
        );

        /*
        |--------------------------------------------------------------------------
        | Envío real a APISUNAT
        |--------------------------------------------------------------------------
        */
        try {
            $respuesta =
                $this->apiSunat->enviarComprobante(
                    (string)$comprobante['fileName'],
                    (array)$comprobante['documentBody'],
                    $comprobante['customerEmail'] ?? null
                );
        } catch (Throwable $e) {
            $this->guardarErrorTecnico(
                $idventa,
                $e->getMessage()
            );

            throw $e;
        }

        $this->guardarRespuestaEnvio(
            $idventa,
            $respuesta
        );

        if (
            ($respuesta['success'] ?? false)
            !== true
        ) {
            return [
                'success' => false,
                'idventa' => $idventa,
                'fileName' =>
                    $comprobante['fileName'],
                'status' =>
                    $respuesta['status']
                    ?? 'ERROR',
                'documentId' =>
                    $respuesta['documentId']
                    ?? null,
                'mensaje' =>
                    $respuesta['message']
                    ?? 'APISUNAT rechazó la solicitud.',
                'http_code' =>
                    $respuesta['http_code']
                    ?? 0,
                'production' => true
            ];
        }

        return [
            'success' => true,
            'idventa' => $idventa,
            'fileName' =>
                $comprobante['fileName'],
            'tipoSunat' =>
                $comprobante['tipoSunat'],
            'serie' =>
                $comprobante['serie'],
            'numero' =>
                $comprobante['numero'],
            'status' =>
                $respuesta['status'],
            'documentId' =>
                $respuesta['documentId'],
            'mensaje' =>
                'El comprobante fue recibido por APISUNAT y está pendiente de procesamiento.',
            'production' => true
        ];
    }

    private function validarComprobante(
        array $comprobante
    ): void {
        $campos = [
            'idventa',
            'fileName',
            'tipoSunat',
            'serie',
            'numero',
            'documentBody'
        ];

        foreach ($campos as $campo) {
            if (
                !array_key_exists(
                    $campo,
                    $comprobante
                )
            ) {
                throw new RuntimeException(
                    'Falta el campo '
                    . $campo
                    . ' en el comprobante.'
                );
            }
        }

        if (
            !in_array(
                (string)$comprobante['tipoSunat'],
                ['01', '03'],
                true
            )
        ) {
            throw new RuntimeException(
                'Solo se pueden enviar facturas y boletas.'
            );
        }

        if (
            empty($comprobante['documentBody'])
            || !is_array(
                $comprobante['documentBody']
            )
        ) {
            throw new RuntimeException(
                'El documentBody está vacío.'
            );
        }
    }

    private function obtenerRegistroVenta(
        int $idventa
    ): ?array {
        $sql = "
            SELECT
                idventa_sunat,
                idventa,
                document_id,
                file_name,
                estado_sunat,
                mensaje_sunat,
                fecha_envio,
                fecha_respuesta
            FROM venta_sunat
            WHERE idventa = :idventa
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':idventa' => $idventa
        ]);

        $registro = $stmt->fetch();

        return $registro !== false
            ? $registro
            : null;
    }

    private function validarQueNoFueEnviado(
        ?array $registro
    ): void {
        if ($registro === null) {
            return;
        }

        $documentId = trim(
            (string)(
                $registro['document_id']
                ?? ''
            )
        );

        if ($documentId !== '') {
            throw new RuntimeException(
                'Esta venta ya tiene un documentId de APISUNAT y no puede enviarse nuevamente.'
            );
        }

        $estado = strtoupper(
            trim(
                (string)(
                    $registro['estado_sunat']
                    ?? ''
                )
            )
        );

        $estadosBloqueados = [
            'EN_PROCESO',
            'PENDIENTE',
            'ENVIADO',
            'ACEPTADO',
            'RECHAZADO',
            'EXCEPCION'
        ];

        if (
            in_array(
                $estado,
                $estadosBloqueados,
                true
            )
        ) {
            throw new RuntimeException(
                'La venta ya tiene un proceso APISUNAT con estado '
                . $estado
                . '.'
            );
        }
    }

    private function reservarEnvio(
        int $idventa,
        array $comprobante,
        array $requestSeguro
    ): void {
        $requestJson = $this->convertirJson(
            $requestSeguro
        );

        try {
            $this->pdo->beginTransaction();

            $stmtBloqueo = $this->pdo->prepare(
                "
                SELECT
                    idventa_sunat,
                    document_id,
                    estado_sunat
                FROM venta_sunat
                WHERE idventa = :idventa
                LIMIT 1
                FOR UPDATE
                "
            );

            $stmtBloqueo->execute([
                ':idventa' => $idventa
            ]);

            $registro = $stmtBloqueo->fetch();

            if ($registro !== false) {
                $this->validarQueNoFueEnviado(
                    $registro
                );

                $sqlActualizar = "
                    UPDATE venta_sunat
                    SET
                        document_id = NULL,
                        file_name = :file_name,
                        tipo_documento_sunat = :tipo,
                        production = 1,
                        estado_sunat = 'EN_PROCESO',
                        mensaje_sunat =
                            'Preparando envío a APISUNAT.',
                        referencia = NULL,
                        faults = NULL,
                        notes = NULL,
                        request_json = :request_json,
                        response_json = NULL,
                        intentos_consulta = 0,
                        fecha_ultima_consulta = NULL,
                        fecha_envio = NULL,
                        fecha_respuesta = NULL
                    WHERE idventa = :idventa
                ";

                $stmtActualizar =
                    $this->pdo->prepare(
                        $sqlActualizar
                    );

                $stmtActualizar->execute([
                    ':file_name' =>
                        $comprobante['fileName'],
                    ':tipo' =>
                        $comprobante['tipoSunat'],
                    ':request_json' =>
                        $requestJson,
                    ':idventa' =>
                        $idventa
                ]);
            } else {
                $sqlInsertar = "
                    INSERT INTO venta_sunat (
                        idventa,
                        document_id,
                        file_name,
                        tipo_documento_sunat,
                        production,
                        estado_sunat,
                        mensaje_sunat,
                        request_json,
                        intentos_consulta
                    ) VALUES (
                        :idventa,
                        NULL,
                        :file_name,
                        :tipo,
                        1,
                        'EN_PROCESO',
                        'Preparando envío a APISUNAT.',
                        :request_json,
                        0
                    )
                ";

                $stmtInsertar =
                    $this->pdo->prepare(
                        $sqlInsertar
                    );

                $stmtInsertar->execute([
                    ':idventa' =>
                        $idventa,
                    ':file_name' =>
                        $comprobante['fileName'],
                    ':tipo' =>
                        $comprobante['tipoSunat'],
                    ':request_json' =>
                        $requestJson
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function guardarRespuestaEnvio(
        int $idventa,
        array $respuesta
    ): void {
        $success =
            ($respuesta['success'] ?? false)
            === true;

        $estado = strtoupper(
            trim(
                (string)(
                    $respuesta['status']
                    ?? 'ERROR'
                )
            )
        );

        if ($estado === '') {
            $estado = 'ERROR';
        }

        $documentId = trim(
            (string)(
                $respuesta['documentId']
                ?? ''
            )
        );

        $mensaje = trim(
            (string)(
                $respuesta['message']
                ?? ''
            )
        );

        $responseSeguro = [
            'success' => $success,
            'status' => $estado,
            'documentId' =>
                $documentId !== ''
                    ? $documentId
                    : null,
            'message' => $mensaje,
            'http_code' =>
                $respuesta['http_code']
                ?? 0,
            'response' =>
                $respuesta['response']
                ?? null
        ];

        $responseJson = $this->convertirJson(
            $responseSeguro
        );

        if ($success) {
            $sql = "
                UPDATE venta_sunat
                SET
                    document_id = :document_id,
                    estado_sunat = :estado,
                    mensaje_sunat = :mensaje,
                    response_json = :response_json,
                    fecha_envio = NOW(),
                    fecha_respuesta = NULL
                WHERE idventa = :idventa
            ";
        } else {
            $sql = "
                UPDATE venta_sunat
                SET
                    document_id = :document_id,
                    estado_sunat = :estado,
                    mensaje_sunat = :mensaje,
                    response_json = :response_json,
                    fecha_respuesta = NOW()
                WHERE idventa = :idventa
            ";
        }

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':document_id' =>
                $documentId !== ''
                    ? $documentId
                    : null,
            ':estado' => $estado,
            ':mensaje' =>
                $mensaje !== ''
                    ? $mensaje
                    : 'Sin mensaje.',
            ':response_json' =>
                $responseJson,
            ':idventa' =>
                $idventa
        ]);
    }

    private function guardarErrorTecnico(
        int $idventa,
        string $mensaje
    ): void {
        $sql = "
            UPDATE venta_sunat
            SET
                estado_sunat = 'ERROR',
                mensaje_sunat = :mensaje,
                fecha_respuesta = NOW()
            WHERE idventa = :idventa
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':mensaje' =>
                mb_substr(
                    trim($mensaje),
                    0,
                    2000
                ),
            ':idventa' =>
                $idventa
        ]);
    }

    private function convertirJson(
        mixed $datos
    ): string {
        $json = json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
        );

        if ($json === false) {
            throw new RuntimeException(
                'No se pudo generar el JSON: '
                . json_last_error_msg()
            );
        }

        return $json;
    }
}