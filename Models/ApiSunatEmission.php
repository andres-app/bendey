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
        | VERIFICAR CORRELATIVO SOLO EN EL PRIMER ENVÍO
        |--------------------------------------------------------------------------
        | Los comprobantes RECHAZADOS, con EXCEPCIÓN o ERROR se reenvían
        | usando exactamente la serie y el número original de la venta.
        */
        $esReintento = $this->esReintento(
            $registroActual
        );

        if (!$esReintento) {
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
                'faults' =>
                    is_array($respuesta['faults'] ?? null)
                        ? $respuesta['faults']
                        : [],
                'notes' =>
                    is_array($respuesta['notes'] ?? null)
                        ? $respuesta['notes']
                        : [],
                'reintento' => $esReintento,
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
                $esReintento
                    ? 'El comprobante fue reenviado con su serie y número original. APISUNAT lo recibió y está pendiente de procesamiento.'
                    : 'El comprobante fue recibido por APISUNAT y está pendiente de procesamiento.',
            'reintento' => $esReintento,
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
                faults,
                notes,
                response_json,
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

    private function esReintento(
        ?array $registro
    ): bool {
        if ($registro === null) {
            return false;
        }

        $estado = strtoupper(
            trim(
                (string)(
                    $registro['estado_sunat']
                    ?? ''
                )
            )
        );

        return in_array(
            $estado,
            [
                'RECHAZADO',
                'EXCEPCION',
                'ERROR'
            ],
            true
        );
    }

    private function validarQueNoFueEnviado(
        ?array $registro
    ): void {
        if ($registro === null) {
            return;
        }

        $estado = strtoupper(
            trim(
                (string)(
                    $registro['estado_sunat']
                    ?? ''
                )
            )
        );

        $documentId = trim(
            (string)(
                $registro['document_id']
                ?? ''
            )
        );

        /*
         * Un comprobante rechazado, con excepción o error no fue
         * aceptado tributariamente. Después de corregir el XML se
         * permite reenviar el mismo correlativo. reservarEnvio()
         * reemplazará el documentId anterior y registrará el nuevo
         * intento de manera controlada.
         */
        $estadosReintentables = [
            'RECHAZADO',
            'EXCEPCION',
            'ERROR',
            'NO_ENVIADO'
        ];

        if (
            in_array(
                $estado,
                $estadosReintentables,
                true
            )
        ) {
            return;
        }

        $estadosBloqueados = [
            'EN_PROCESO',
            'PENDIENTE',
            'ENVIADO',
            'ACEPTADO'
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

        /*
         * Si existe documentId pero el estado está vacío o no es
         * reconocible, se bloquea para evitar un envío duplicado.
         */
        if ($documentId !== '') {
            throw new RuntimeException(
                'Esta venta ya tiene un documentId de APISUNAT y su estado no permite reenviarla.'
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
                        request_json = :request_json,
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

        $faults = $this->normalizarMensajes(
            $respuesta['faults']
            ?? (
                is_array($respuesta['response'] ?? null)
                    ? ($respuesta['response']['faults'] ?? [])
                    : []
            )
        );

        $notes = $this->normalizarMensajes(
            $respuesta['notes']
            ?? (
                is_array($respuesta['response'] ?? null)
                    ? ($respuesta['response']['notes'] ?? [])
                    : []
            )
        );

        $mensajeBase = trim(
            (string)(
                $respuesta['message']
                ?? ''
            )
        );

        $mensaje = $this->construirMensajeDetallado(
            $mensajeBase !== ''
                ? $mensajeBase
                : 'APISUNAT no devolvió un mensaje.',
            $faults,
            $notes
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
            'faults' => $faults,
            'notes' => $notes,
            'response' =>
                $respuesta['response']
                ?? null
        ];

        $responseJson = $this->convertirJson(
            $responseSeguro
        );

        $faultsJson = count($faults) > 0
            ? $this->convertirJson($faults)
            : null;

        $notesJson = count($notes) > 0
            ? $this->convertirJson($notes)
            : null;

        if ($success) {
            $sql = "
                UPDATE venta_sunat
                SET
                    document_id = :document_id,
                    estado_sunat = :estado,
                    mensaje_sunat = :mensaje,
                    faults = NULL,
                    notes = NULL,
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
                    faults = :faults,
                    notes = :notes,
                    response_json = :response_json,
                    fecha_respuesta = NOW()
                WHERE idventa = :idventa
            ";
        }

        $stmt = $this->pdo->prepare($sql);

        $parametros = [
            ':document_id' =>
                $documentId !== ''
                    ? $documentId
                    : null,
            ':estado' => $estado,
            ':mensaje' =>
                mb_substr(
                    $mensaje,
                    0,
                    4000
                ),
            ':response_json' =>
                $responseJson,
            ':idventa' =>
                $idventa
        ];

        if (!$success) {
            $parametros[':faults'] = $faultsJson;
            $parametros[':notes'] = $notesJson;
        }

        $stmt->execute(
            $parametros
        );
    }

    private function normalizarMensajes(
        mixed $valor
    ): array {
        $salida = [];

        $recorrer = function (
            mixed $dato
        ) use (
            &$recorrer,
            &$salida
        ): void {
            if (is_string($dato)) {
                $texto = trim($dato);

                if ($texto !== '') {
                    $salida[] = $texto;
                }

                return;
            }

            if (
                is_int($dato)
                || is_float($dato)
            ) {
                $salida[] = (string)$dato;
                return;
            }

            if (!is_array($dato)) {
                return;
            }

            foreach ($dato as $item) {
                $recorrer($item);
            }
        };

        $recorrer($valor);

        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn(string $texto): string =>
                            preg_replace(
                                '/\s+/u',
                                ' ',
                                trim($texto)
                            ) ?? trim($texto),
                        $salida
                    ),
                    static fn(string $texto): bool =>
                        $texto !== ''
                )
            )
        );
    }

    private function construirMensajeDetallado(
        string $mensaje,
        array $faults,
        array $notes
    ): string {
        $partes = [];
        $mensaje = trim($mensaje);

        if ($mensaje !== '') {
            $partes[] = $mensaje;
        }

        foreach ($faults as $fault) {
            if (
                $fault !== ''
                && !str_contains(
                    implode(' | ', $partes),
                    $fault
                )
            ) {
                $partes[] = $fault;
            }
        }

        foreach ($notes as $note) {
            if (
                $note !== ''
                && !str_contains(
                    implode(' | ', $partes),
                    $note
                )
            ) {
                $partes[] = 'Nota: ' . $note;
            }
        }

        return implode(
            ' | ',
            $partes
        );
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