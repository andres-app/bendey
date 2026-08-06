<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/ApiSunat.php';
require_once __DIR__ . '/ApiSunatStorage.php';

class ApiSunatStatus
{
    private PDO $pdo;
    private ApiSunat $apiSunat;
    private ApiSunatStorage $storage;

    public function __construct(
        ?PDO $pdo = null,
        ?ApiSunat $apiSunat = null,
        ?ApiSunatStorage $storage = null
    ) {
        $this->pdo = $pdo
            ?? Conexion::conectar();

        $this->pdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $this->pdo->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );

        $this->apiSunat = $apiSunat
            ?? new ApiSunat();

        $this->storage = $storage
            ?? new ApiSunatStorage();
    }

    public function consultarYGuardar(
        int $idventa
    ): array {
        if ($idventa <= 0) {
            throw new InvalidArgumentException(
                'El ID de venta no es válido.'
            );
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                idventa_sunat,
                idventa,
                document_id,
                file_name,
                estado_sunat,
                xml,
                cdr,
                xml_local,
                cdr_local
             FROM venta_sunat
             WHERE idventa = :idventa
             LIMIT 1"
        );

        $stmt->execute([
            ':idventa' => $idventa
        ]);

        $registro = $stmt->fetch();

        if (!$registro) {
            throw new RuntimeException(
                'La venta no tiene registro en venta_sunat.'
            );
        }

        $documentId = trim(
            (string)($registro['document_id'] ?? '')
        );

        if ($documentId === '') {
            throw new RuntimeException(
                'La venta todavía no tiene documentId.'
            );
        }

        $consulta = $this->apiSunat
            ->consultarDocumento($documentId);

        $estado = strtoupper(
            trim(
                (string)(
                    $consulta['status']
                    ?? 'ERROR'
                )
            )
        );

        if ($estado === '') {
            $estado = 'ERROR';
        }

        $xmlUrl = trim(
            (string)(
                $consulta['xml']
                ?? $registro['xml']
                ?? ''
            )
        );

        $cdrUrl = trim(
            (string)(
                $consulta['cdr']
                ?? $registro['cdr']
                ?? ''
            )
        );

        $xmlLocal = trim(
            (string)(
                $registro['xml_local']
                ?? ''
            )
        );

        $cdrLocal = trim(
            (string)(
                $registro['cdr_local']
                ?? ''
            )
        );

        $erroresDescarga = [];

        if ($estado === 'ACEPTADO') {
            if (
                $xmlUrl !== ''
                && !$this->storage->existe($xmlLocal)
            ) {
                try {
                    $xmlLocal =
                        $this->storage->guardarDesdeUrl(
                            $xmlUrl,
                            'xml'
                        );
                } catch (Throwable $e) {
                    $erroresDescarga[] =
                        'XML: ' . $e->getMessage();
                }
            }

            if (
                $cdrUrl !== ''
                && !$this->storage->existe($cdrLocal)
            ) {
                try {
                    $cdrLocal =
                        $this->storage->guardarDesdeUrl(
                            $cdrUrl,
                            'cdr'
                        );
                } catch (Throwable $e) {
                    $erroresDescarga[] =
                        'CDR: ' . $e->getMessage();
                }
            }
        }

        $faults = is_array(
            $consulta['faults'] ?? null
        )
            ? $consulta['faults']
            : [];

        $notes = is_array(
            $consulta['notes'] ?? null
        )
            ? $consulta['notes']
            : [];

        $faultsJson = $this->jsonSeguro(
            $faults
        );

        $notesJson = $this->jsonSeguro(
            $notes
        );

        $responseJson = $this->jsonSeguro(
            $consulta['document']
            ?? $consulta
        );

        $mensaje = trim(
            (string)(
                $consulta['message']
                ?? ''
            )
        );

        if ($mensaje === '') {
            $mensaje =
                'Estado consultado en APISUNAT.';
        }

        $mensaje = $this->construirMensajeDetallado(
            $mensaje,
            $faults,
            $notes
        );

        if (
            $this->respuestaEsNumeracionRepetida(
                $mensaje,
                $faults,
                $notes,
                $consulta
            )
        ) {
            $estado = 'RECHAZADO';
        }

        $estadosFinales = [
            'ACEPTADO',
            'RECHAZADO',
            'EXCEPCION'
        ];

        $fechaRespuesta = in_array(
            $estado,
            $estadosFinales,
            true
        )
            ? date('Y-m-d H:i:s')
            : null;

        $archivosDescargados =
            $this->storage->existe($xmlLocal)
            || $this->storage->existe($cdrLocal);

        $actualizar = $this->pdo->prepare(
            "UPDATE venta_sunat
             SET
                estado_sunat = :estado,
                mensaje_sunat = :mensaje,
                xml = :xml,
                cdr = :cdr,
                xml_local = :xml_local,
                cdr_local = :cdr_local,
                referencia = :referencia,
                faults = :faults,
                notes = :notes,
                response_json = :response_json,
                intentos_consulta =
                    intentos_consulta + 1,
                fecha_ultima_consulta = NOW(),
                fecha_respuesta =
                    COALESCE(
                        :fecha_respuesta,
                        fecha_respuesta
                    ),
                fecha_descarga_archivos =
                    CASE
                        WHEN :descargados = 1
                        THEN NOW()
                        ELSE fecha_descarga_archivos
                    END
             WHERE idventa = :idventa"
        );

        $actualizar->execute([
            ':estado' => $estado,
            ':mensaje' => $mensaje,
            ':xml' => $xmlUrl !== ''
                ? $xmlUrl
                : null,
            ':cdr' => $cdrUrl !== ''
                ? $cdrUrl
                : null,
            ':xml_local' => $xmlLocal !== ''
                ? $xmlLocal
                : null,
            ':cdr_local' => $cdrLocal !== ''
                ? $cdrLocal
                : null,
            ':referencia' => trim(
                (string)(
                    $consulta['reference']
                    ?? ''
                )
            ),
            ':faults' => $faultsJson,
            ':notes' => $notesJson,
            ':response_json' => $responseJson,
            ':fecha_respuesta' => $fechaRespuesta,
            ':descargados' => $archivosDescargados
                ? 1
                : 0,
            ':idventa' => $idventa
        ]);

        return [
            'success' =>
                ($consulta['success'] ?? false)
                === true,
            'idventa' => $idventa,
            'documentId' => $documentId,
            'fileName' =>
                $consulta['fileName']
                ?? $registro['file_name'],
            'production' =>
                $consulta['production']
                ?? true,
            'status' => $estado,
            'mensaje' => $mensaje,
            'xml' => $xmlUrl,
            'cdr' => $cdrUrl,
            'xml_local' => $xmlLocal,
            'cdr_local' => $cdrLocal,
            'faults' => $faults,
            'notes' => $notes,
            'errores_descarga' =>
                $erroresDescarga,
            'fecha_respuesta' =>
                $fechaRespuesta
        ];
    }

    private function respuestaEsNumeracionRepetida(
        string $mensaje,
        array $faults,
        array $notes,
        array $consulta
    ): bool {
        $texto = strtoupper(
            implode(
                ' ',
                [
                    $mensaje,
                    json_encode(
                        $faults,
                        JSON_UNESCAPED_UNICODE
                    ) ?: '',
                    json_encode(
                        $notes,
                        JSON_UNESCAPED_UNICODE
                    ) ?: '',
                    json_encode(
                        $consulta,
                        JSON_UNESCAPED_UNICODE
                    ) ?: ''
                ]
            )
        );

        $texto = strtr(
            $texto,
            [
                'Á' => 'A',
                'É' => 'E',
                'Í' => 'I',
                'Ó' => 'O',
                'Ú' => 'U'
            ]
        );

        return str_contains($texto, '"CODE":"1033"')
            || str_contains($texto, '"CODE": "1033"')
            || str_contains($texto, 'CODE 1033')
            || str_contains($texto, 'CODIGO 1033')
            || str_contains($texto, 'NUMERACION REPETIDA')
            || (
                str_contains($texto, 'DOCUMENTO CON NUMERO')
                && str_contains($texto, 'YA EXISTE')
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
        $faults = $this->normalizarMensajes(
            $faults
        );

        $notes = $this->normalizarMensajes(
            $notes
        );

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

    private function jsonSeguro(
        mixed $datos
    ): string {
        $json = json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
        );

        return $json !== false
            ? $json
            : '{}';
    }
}