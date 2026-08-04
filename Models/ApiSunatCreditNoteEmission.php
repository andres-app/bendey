<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/ApiSunat.php';
require_once __DIR__ . '/ApiSunatCreditNoteDocument.php';

/**
 * Emisión de notas de crédito mediante la misma API usada por facturas.
 */
class ApiSunatCreditNoteEmission
{
    private PDO $pdo;
    private ApiSunat $apiSunat;
    private ApiSunatCreditNoteDocument $documento;

    public function __construct(
        ?PDO $pdo = null,
        ?ApiSunat $apiSunat = null,
        ?ApiSunatCreditNoteDocument $documento = null
    ) {
        $this->pdo = $pdo ?? Conexion::conectar();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );

        $this->apiSunat = $apiSunat ?? new ApiSunat();
        $this->documento = $documento
            ?? new ApiSunatCreditNoteDocument();
    }

    public function enviar(int $idnotaCredito): array
    {
        if ($idnotaCredito <= 0) {
            throw new InvalidArgumentException(
                'El ID de la nota de crédito no es válido.'
            );
        }

        $comprobante = $this->documento->construir($idnotaCredito);
        $this->validarComprobante($comprobante);

        $registroActual = $this->obtenerRegistro($idnotaCredito);
        $this->validarQueNoFueEnviado($registroActual);

        $ultimoDocumento = $this->apiSunat->obtenerUltimoDocumento(
            '07',
            (string)$comprobante['serie']
        );

        if (($ultimoDocumento['success'] ?? false) !== true) {
            throw new RuntimeException(
                'No se pudo verificar el correlativo de la nota en APISUNAT: '
                . ($ultimoDocumento['message'] ?? 'Sin detalle.')
            );
        }

        if (($ultimoDocumento['production'] ?? null) !== true) {
            throw new RuntimeException(
                'Las credenciales configuradas no corresponden a producción.'
            );
        }

        $numeroEsperado = str_pad(
            (string)($ultimoDocumento['suggestedNumber'] ?? ''),
            8,
            '0',
            STR_PAD_LEFT
        );
        $numeroNota = str_pad(
            (string)$comprobante['numero'],
            8,
            '0',
            STR_PAD_LEFT
        );

        if ($numeroEsperado !== $numeroNota) {
            throw new RuntimeException(
                'El correlativo local no coincide con APISUNAT. '
                . 'APISUNAT espera '
                . $comprobante['serie'] . '-' . $numeroEsperado
                . ', pero la nota tiene '
                . $comprobante['serie'] . '-' . $numeroNota . '.'
            );
        }

        $requestSeguro = $this->apiSunat->crearRequestSeguro(
            (string)$comprobante['fileName'],
            (array)$comprobante['documentBody'],
            $comprobante['customerEmail'] ?? null
        );

        $this->reservarEnvio(
            $idnotaCredito,
            $comprobante,
            $requestSeguro
        );

        try {
            $respuesta = $this->apiSunat->enviarComprobante(
                (string)$comprobante['fileName'],
                (array)$comprobante['documentBody'],
                $comprobante['customerEmail'] ?? null
            );
        } catch (Throwable $e) {
            $this->guardarErrorTecnico(
                $idnotaCredito,
                $e->getMessage()
            );
            throw $e;
        }

        $this->guardarRespuestaEnvio($idnotaCredito, $respuesta);

        if (($respuesta['success'] ?? false) !== true) {
            return [
                'success' => false,
                'idnota_credito' => $idnotaCredito,
                'fileName' => $comprobante['fileName'],
                'status' => $respuesta['status'] ?? 'ERROR',
                'documentId' => $respuesta['documentId'] ?? null,
                'mensaje' =>
                    $respuesta['message']
                    ?? 'APISUNAT rechazó la solicitud.',
                'http_code' => $respuesta['http_code'] ?? 0,
                'production' => true
            ];
        }

        return [
            'success' => true,
            'idnota_credito' => $idnotaCredito,
            'fileName' => $comprobante['fileName'],
            'tipoSunat' => '07',
            'serie' => $comprobante['serie'],
            'numero' => $comprobante['numero'],
            'status' => $respuesta['status'],
            'documentId' => $respuesta['documentId'],
            'mensaje' =>
                'La nota de crédito fue recibida por APISUNAT y está pendiente de procesamiento.',
            'production' => true
        ];
    }

    private function validarComprobante(array $comprobante): void
    {
        foreach ([
            'idnota_credito',
            'fileName',
            'tipoSunat',
            'serie',
            'numero',
            'documentBody'
        ] as $campo) {
            if (!array_key_exists($campo, $comprobante)) {
                throw new RuntimeException(
                    'Falta el campo ' . $campo . ' en la nota de crédito.'
                );
            }
        }

        if ((string)$comprobante['tipoSunat'] !== '07') {
            throw new RuntimeException(
                'El documento construido no es una nota de crédito SUNAT.'
            );
        }

        if (
            empty($comprobante['documentBody'])
            || !is_array($comprobante['documentBody'])
        ) {
            throw new RuntimeException(
                'El documentBody de la nota está vacío.'
            );
        }
    }

    private function obtenerRegistro(int $idnotaCredito): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                idnota_credito_sunat,
                idnota_credito,
                document_id,
                file_name,
                estado_sunat,
                mensaje_sunat,
                fecha_envio,
                fecha_respuesta
             FROM nota_credito_sunat
             WHERE idnota_credito = :idnota
             LIMIT 1"
        );
        $stmt->execute([':idnota' => $idnotaCredito]);
        $registro = $stmt->fetch();

        return $registro !== false ? $registro : null;
    }

    private function validarQueNoFueEnviado(?array $registro): void
    {
        if ($registro === null) {
            return;
        }

        $estado = strtoupper(
            trim((string)($registro['estado_sunat'] ?? ''))
        );
        $documentId = trim((string)($registro['document_id'] ?? ''));

        if (
            in_array(
                $estado,
                ['RECHAZADO', 'EXCEPCION', 'ERROR', 'NO_ENVIADO'],
                true
            )
        ) {
            return;
        }

        if (
            in_array(
                $estado,
                ['EN_PROCESO', 'PENDIENTE', 'ENVIADO', 'ACEPTADO'],
                true
            )
        ) {
            throw new RuntimeException(
                'La nota ya tiene un proceso APISUNAT con estado '
                . $estado . '.'
            );
        }

        if ($documentId !== '') {
            throw new RuntimeException(
                'La nota ya tiene un documentId y no puede volver a enviarse.'
            );
        }
    }

    private function reservarEnvio(
        int $idnotaCredito,
        array $comprobante,
        array $requestSeguro
    ): void {
        $requestJson = $this->convertirJson($requestSeguro);

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT
                    idnota_credito_sunat,
                    document_id,
                    estado_sunat
                 FROM nota_credito_sunat
                 WHERE idnota_credito = :idnota
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([':idnota' => $idnotaCredito]);
            $registro = $stmt->fetch();

            if ($registro !== false) {
                $this->validarQueNoFueEnviado($registro);

                $actualizar = $this->pdo->prepare(
                    "UPDATE nota_credito_sunat
                     SET
                        document_id = NULL,
                        file_name = :file_name,
                        tipo_documento_sunat = '07',
                        production = 1,
                        estado_sunat = 'EN_PROCESO',
                        mensaje_sunat = 'Preparando envío a APISUNAT.',
                        referencia = :referencia,
                        faults = NULL,
                        notes = NULL,
                        request_json = :request_json,
                        response_json = NULL,
                        intentos_consulta = 0,
                        fecha_ultima_consulta = NULL,
                        fecha_envio = NULL,
                        fecha_respuesta = NULL
                     WHERE idnota_credito = :idnota"
                );
                $actualizar->execute([
                    ':file_name' => $comprobante['fileName'],
                    ':referencia' => $comprobante['comprobanteOrigen'],
                    ':request_json' => $requestJson,
                    ':idnota' => $idnotaCredito
                ]);
            } else {
                $insertar = $this->pdo->prepare(
                    "INSERT INTO nota_credito_sunat (
                        idnota_credito,
                        file_name,
                        tipo_documento_sunat,
                        production,
                        estado_sunat,
                        mensaje_sunat,
                        referencia,
                        request_json,
                        intentos_consulta
                    ) VALUES (
                        :idnota,
                        :file_name,
                        '07',
                        1,
                        'EN_PROCESO',
                        'Preparando envío a APISUNAT.',
                        :referencia,
                        :request_json,
                        0
                    )"
                );
                $insertar->execute([
                    ':idnota' => $idnotaCredito,
                    ':file_name' => $comprobante['fileName'],
                    ':referencia' => $comprobante['comprobanteOrigen'],
                    ':request_json' => $requestJson
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
        int $idnotaCredito,
        array $respuesta
    ): void {
        $success = ($respuesta['success'] ?? false) === true;
        $estado = strtoupper(
            trim((string)($respuesta['status'] ?? 'ERROR'))
        );
        $estado = $estado !== '' ? $estado : 'ERROR';
        $documentId = trim((string)($respuesta['documentId'] ?? ''));
        $mensaje = trim((string)($respuesta['message'] ?? ''));

        $responseJson = $this->convertirJson([
            'success' => $success,
            'status' => $estado,
            'documentId' => $documentId !== '' ? $documentId : null,
            'message' => $mensaje,
            'http_code' => $respuesta['http_code'] ?? 0,
            'response' => $respuesta['response'] ?? null
        ]);

        $sql = $success
            ? "UPDATE nota_credito_sunat
               SET
                    document_id = :document_id,
                    estado_sunat = :estado,
                    mensaje_sunat = :mensaje,
                    response_json = :response_json,
                    fecha_envio = NOW(),
                    fecha_respuesta = NULL
               WHERE idnota_credito = :idnota"
            : "UPDATE nota_credito_sunat
               SET
                    document_id = :document_id,
                    estado_sunat = :estado,
                    mensaje_sunat = :mensaje,
                    response_json = :response_json,
                    fecha_respuesta = NOW()
               WHERE idnota_credito = :idnota";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':document_id' => $documentId !== '' ? $documentId : null,
            ':estado' => $estado,
            ':mensaje' => $mensaje !== '' ? $mensaje : 'Sin mensaje.',
            ':response_json' => $responseJson,
            ':idnota' => $idnotaCredito
        ]);
    }

    private function guardarErrorTecnico(
        int $idnotaCredito,
        string $mensaje
    ): void {
        $stmt = $this->pdo->prepare(
            "UPDATE nota_credito_sunat
             SET
                estado_sunat = 'ERROR',
                mensaje_sunat = :mensaje,
                fecha_respuesta = NOW()
             WHERE idnota_credito = :idnota"
        );
        $stmt->execute([
            ':mensaje' => mb_substr(trim($mensaje), 0, 2000),
            ':idnota' => $idnotaCredito
        ]);
    }

    private function convertirJson(mixed $datos): string
    {
        $json = json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
        );

        if ($json === false) {
            throw new RuntimeException(
                'No se pudo generar el JSON: ' . json_last_error_msg()
            );
        }

        return $json;
    }
}
