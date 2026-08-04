<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/ApiSunat.php';
require_once __DIR__ . '/ApiSunatStorage.php';
require_once __DIR__ . '/CreditNote.php';

/**
 * Consulta el estado de una nota de crédito y aplica sus efectos al aceptarse.
 */
class ApiSunatCreditNoteStatus
{
    private PDO $pdo;
    private ApiSunat $apiSunat;
    private ApiSunatStorage $storage;
    private CreditNote $notas;

    public function __construct(
        ?PDO $pdo = null,
        ?ApiSunat $apiSunat = null,
        ?ApiSunatStorage $storage = null,
        ?CreditNote $notas = null
    ) {
        $this->pdo = $pdo ?? Conexion::conectar();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );

        $this->apiSunat = $apiSunat ?? new ApiSunat();
        $this->storage = $storage ?? new ApiSunatStorage();
        $this->notas = $notas ?? new CreditNote();
    }

    public function consultarYGuardar(int $idnotaCredito): array
    {
        if ($idnotaCredito <= 0) {
            throw new InvalidArgumentException(
                'El ID de la nota de crédito no es válido.'
            );
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                idnota_credito_sunat,
                idnota_credito,
                document_id,
                file_name,
                estado_sunat,
                xml,
                cdr,
                xml_local,
                cdr_local
             FROM nota_credito_sunat
             WHERE idnota_credito = :idnota
             LIMIT 1"
        );
        $stmt->execute([':idnota' => $idnotaCredito]);
        $registro = $stmt->fetch();

        if (!$registro) {
            throw new RuntimeException(
                'La nota no tiene seguimiento en nota_credito_sunat.'
            );
        }

        $documentId = trim((string)($registro['document_id'] ?? ''));

        if ($documentId === '') {
            throw new RuntimeException(
                'La nota todavía no tiene documentId.'
            );
        }

        $consulta = $this->apiSunat->consultarDocumento($documentId);
        $estado = strtoupper(
            trim((string)($consulta['status'] ?? 'ERROR'))
        );
        $estado = $estado !== '' ? $estado : 'ERROR';

        $xmlUrl = trim(
            (string)($consulta['xml'] ?? $registro['xml'] ?? '')
        );
        $cdrUrl = trim(
            (string)($consulta['cdr'] ?? $registro['cdr'] ?? '')
        );
        $xmlLocal = trim((string)($registro['xml_local'] ?? ''));
        $cdrLocal = trim((string)($registro['cdr_local'] ?? ''));
        $erroresDescarga = [];

        if ($estado === 'ACEPTADO') {
            if ($xmlUrl !== '' && !$this->storage->existe($xmlLocal)) {
                try {
                    $xmlLocal = $this->storage->guardarDesdeUrl(
                        $xmlUrl,
                        'xml'
                    );
                } catch (Throwable $e) {
                    $erroresDescarga[] = 'XML: ' . $e->getMessage();
                }
            }

            if ($cdrUrl !== '' && !$this->storage->existe($cdrLocal)) {
                try {
                    $cdrLocal = $this->storage->guardarDesdeUrl(
                        $cdrUrl,
                        'cdr'
                    );
                } catch (Throwable $e) {
                    $erroresDescarga[] = 'CDR: ' . $e->getMessage();
                }
            }
        }

        $faults = is_array($consulta['faults'] ?? null)
            ? $consulta['faults']
            : [];
        $notes = is_array($consulta['notes'] ?? null)
            ? $consulta['notes']
            : [];
        $mensaje = trim((string)($consulta['message'] ?? ''));
        $mensaje = $mensaje !== ''
            ? $mensaje
            : 'Estado consultado en APISUNAT.';

        $estadosFinales = ['ACEPTADO', 'RECHAZADO', 'EXCEPCION'];
        $fechaRespuesta = in_array($estado, $estadosFinales, true)
            ? date('Y-m-d H:i:s')
            : null;
        $archivosDescargados =
            $this->storage->existe($xmlLocal)
            || $this->storage->existe($cdrLocal);

        $actualizar = $this->pdo->prepare(
            "UPDATE nota_credito_sunat
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
                intentos_consulta = intentos_consulta + 1,
                fecha_ultima_consulta = NOW(),
                fecha_respuesta = COALESCE(:fecha_respuesta, fecha_respuesta),
                fecha_descarga_archivos = CASE
                    WHEN :descargados = 1 THEN NOW()
                    ELSE fecha_descarga_archivos
                END
             WHERE idnota_credito = :idnota"
        );
        $actualizar->execute([
            ':estado' => $estado,
            ':mensaje' => $mensaje,
            ':xml' => $xmlUrl !== '' ? $xmlUrl : null,
            ':cdr' => $cdrUrl !== '' ? $cdrUrl : null,
            ':xml_local' => $xmlLocal !== '' ? $xmlLocal : null,
            ':cdr_local' => $cdrLocal !== '' ? $cdrLocal : null,
            ':referencia' => trim((string)($consulta['reference'] ?? '')),
            ':faults' => $this->jsonSeguro($faults),
            ':notes' => $this->jsonSeguro($notes),
            ':response_json' => $this->jsonSeguro(
                $consulta['document'] ?? $consulta
            ),
            ':fecha_respuesta' => $fechaRespuesta,
            ':descargados' => $archivosDescargados ? 1 : 0,
            ':idnota' => $idnotaCredito
        ]);

        $efectos = null;
        $errorEfectos = null;

        if ($estado === 'ACEPTADO') {
            try {
                $efectos = $this->notas->aplicarEfectos($idnotaCredito);
            } catch (Throwable $e) {
                $errorEfectos = $e->getMessage();
                error_log(
                    '[NC EFECTOS TRAS SUNAT] '
                    . $idnotaCredito . ': ' . $e->getMessage()
                );
            }
        }

        return [
            'success' => ($consulta['success'] ?? false) === true,
            'idnota_credito' => $idnotaCredito,
            'documentId' => $documentId,
            'fileName' => $consulta['fileName'] ?? $registro['file_name'],
            'production' => $consulta['production'] ?? true,
            'status' => $estado,
            'mensaje' => $mensaje,
            'xml' => $xmlUrl,
            'cdr' => $cdrUrl,
            'xml_local' => $xmlLocal,
            'cdr_local' => $cdrLocal,
            'faults' => $faults,
            'notes' => $notes,
            'errores_descarga' => $erroresDescarga,
            'fecha_respuesta' => $fechaRespuesta,
            'efectos' => $efectos,
            'error_efectos' => $errorEfectos
        ];
    }

    private function jsonSeguro(mixed $datos): string
    {
        $json = json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
        );

        return $json !== false ? $json : '{}';
    }
}
