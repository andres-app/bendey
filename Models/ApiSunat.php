<?php

declare(strict_types=1);

class ApiSunat
{
    private array $config;
    private string $baseUrl;
    private string $personaId;
    private string $personaToken;
    private int $connectTimeout;
    private int $timeout;
    private bool $verifySsl;

    public function __construct()
    {
        $rutaConfig = __DIR__ . '/../Config/ApiSunat.php';

        if (!file_exists($rutaConfig)) {
            throw new RuntimeException(
                'No existe el archivo Config/ApiSunat.php.'
            );
        }

        $config = require $rutaConfig;

        if (!is_array($config)) {
            throw new RuntimeException(
                'La configuración de APISUNAT no es válida.'
            );
        }

        $this->config = $config;

        $this->baseUrl = rtrim(
            trim((string)($config['base_url'] ?? '')),
            '/'
        );

        $this->personaId = trim(
            (string)($config['persona_id'] ?? '')
        );

        $this->personaToken = trim(
            (string)($config['persona_token'] ?? '')
        );

        $this->connectTimeout = max(
            1,
            (int)($config['connect_timeout'] ?? 15)
        );

        $this->timeout = max(
            $this->connectTimeout,
            (int)($config['timeout'] ?? 60)
        );

        $this->verifySsl = (bool)(
            $config['verify_ssl'] ?? true
        );

        $this->validarConfiguracion();
    }

    /**
     * Envía una factura o boleta a APISUNAT.
     */
    public function enviarComprobante(
        string $fileName,
        array $documentBody,
        ?string $customerEmail = null
    ): array {
        $fileName = strtoupper(trim($fileName));

        $this->validarFileName($fileName);

        if (empty($documentBody)) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'documentId' => null,
                'message' => 'El documentBody está vacío.',
                'http_code' => 0,
                'response' => null
            ];
        }

        $payload = [
            'personaId' => $this->personaId,
            'personaToken' => $this->personaToken,
            'fileName' => $fileName,
            'documentBody' => $documentBody
        ];

        $customerEmail = trim(
            (string)$customerEmail
        );

        if (
            $customerEmail !== ''
            && filter_var(
                $customerEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $payload['customerEmail'] = $customerEmail;
        }

        $respuesta = $this->request(
            'POST',
            '/personas/v1/sendBill',
            $payload
        );

        $data = is_array($respuesta['data'] ?? null)
            ? $respuesta['data']
            : [];

        $status = strtoupper(
            trim((string)($data['status'] ?? 'ERROR'))
        );

        $documentId = trim(
            (string)($data['documentId'] ?? '')
        );

        $exito = (
            $respuesta['success'] === true
            && $status === 'PENDIENTE'
            && $documentId !== ''
        );

        if ($exito) {
            return [
                'success' => true,
                'status' => 'PENDIENTE',
                'documentId' => $documentId,
                'message' => 'Comprobante recibido por APISUNAT.',
                'http_code' => $respuesta['http_code'],
                'response' => $data
            ];
        }

        return [
            'success' => false,
            'status' => $status !== ''
                ? $status
                : 'ERROR',
            'documentId' => $documentId !== ''
                ? $documentId
                : null,
            'message' => $this->obtenerMensajeError(
                $data,
                $respuesta
            ),
            'http_code' => $respuesta['http_code'],
            'response' => $data
        ];
    }

    /**
     * Consulta un comprobante mediante el documentId.
     */
    public function consultarDocumento(
        string $documentId
    ): array {
        $documentId = trim($documentId);

        if (!$this->documentIdValido($documentId)) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => 'El documentId no es válido.',
                'http_code' => 0,
                'document' => null
            ];
        }

        $respuesta = $this->request(
            'GET',
            '/documents/'
                . rawurlencode($documentId)
                . '/getById'
        );

        $data = is_array($respuesta['data'] ?? null)
            ? $respuesta['data']
            : [];

        $status = strtoupper(
            trim((string)($data['status'] ?? ''))
        );

        /*
         * PENDIENTE puede aparecer mientras APISUNAT
         * todavía está procesando el comprobante.
         */
        $estadosValidos = [
            'PENDIENTE',
            'ACEPTADO',
            'RECHAZADO',
            'EXCEPCION'
        ];

        $exito = (
            $respuesta['success'] === true
            && in_array(
                $status,
                $estadosValidos,
                true
            )
        );

        return [
            'success' => $exito,
            'status' => $status !== ''
                ? $status
                : 'ERROR',
            'message' => $exito
                ? $this->mensajeSegunEstado($status)
                : $this->obtenerMensajeError(
                    $data,
                    $respuesta
                ),
            'http_code' => $respuesta['http_code'],
            'document' => $data,
            'xml' => trim(
                (string)($data['xml'] ?? '')
            ),
            'cdr' => trim(
                (string)($data['cdr'] ?? '')
            ),
            'fileName' => trim(
                (string)($data['fileName'] ?? '')
            ),
            'production' => isset($data['production'])
                ? (bool)$data['production']
                : null,
            'type' => trim(
                (string)($data['type'] ?? '')
            ),
            'reference' => trim(
                (string)($data['reference'] ?? '')
            ),
            'faults' => is_array($data['faults'] ?? null)
                ? $data['faults']
                : [],
            'notes' => is_array($data['notes'] ?? null)
                ? $data['notes']
                : [],
            'issueTime' => $data['issueTime'] ?? null,
            'responseTime' => $data['responseTime'] ?? null
        ];
    }

    /**
 * Consulta el último correlativo registrado en APISUNAT.
 * No emite ningún comprobante.
 */
public function obtenerUltimoDocumento(
    string $tipo,
    string $serie
): array {
    $tipo = trim($tipo);
    $serie = strtoupper(trim($serie));

    if (!in_array($tipo, ['01', '03'], true)) {
        return [
            'success' => false,
            'message' => 'El tipo debe ser 01 para factura o 03 para boleta.'
        ];
    }

    if (!preg_match('/^[FB][A-Z0-9]{3}$/', $serie)) {
        return [
            'success' => false,
            'message' => 'La serie no es válida.'
        ];
    }

    if ($tipo === '01' && $serie[0] !== 'F') {
        return [
            'success' => false,
            'message' => 'Una factura debe usar una serie que comience con F.'
        ];
    }

    if ($tipo === '03' && $serie[0] !== 'B') {
        return [
            'success' => false,
            'message' => 'Una boleta debe usar una serie que comience con B.'
        ];
    }

    $payload = [
        'personaId' => $this->personaId,
        'personaToken' => $this->personaToken,
        'type' => $tipo,
        'serie' => $serie
    ];

    $respuesta = $this->request(
        'POST',
        '/personas/lastDocument',
        $payload
    );

    $data = is_array($respuesta['data'] ?? null)
        ? $respuesta['data']
        : [];

    if ($respuesta['success'] !== true) {
        return [
            'success' => false,
            'message' => $this->obtenerMensajeError(
                $data,
                $respuesta
            ),
            'http_code' => $respuesta['http_code'] ?? 0,
            'response' => $data
        ];
    }

    return [
        'success' => true,
        'production' => isset($data['production'])
            ? (bool)$data['production']
            : null,
        'type' => trim((string)($data['type'] ?? $tipo)),
        'serie' => trim((string)($data['serie'] ?? $serie)),
        'lastNumber' => str_pad(
            trim((string)($data['lastNumber'] ?? '0')),
            8,
            '0',
            STR_PAD_LEFT
        ),
        'suggestedNumber' => str_pad(
            trim((string)($data['suggestedNumber'] ?? '1')),
            8,
            '0',
            STR_PAD_LEFT
        ),
        'response' => $data
    ];
}

    /**
     * Devuelve una copia del request para registrar en BD
     * sin incluir personaToken.
     */
    public function crearRequestSeguro(
        string $fileName,
        array $documentBody,
        ?string $customerEmail = null
    ): array {
        $request = [
            'personaId' => $this->ocultarPersonaId(
                $this->personaId
            ),
            'personaToken' => '[OCULTO]',
            'fileName' => strtoupper(
                trim($fileName)
            ),
            'documentBody' => $documentBody
        ];

        $customerEmail = trim(
            (string)$customerEmail
        );

        if ($customerEmail !== '') {
            $request['customerEmail'] = $customerEmail;
        }

        return $request;
    }

    public function esProduccion(): bool
    {
        return (bool)(
            $this->config['production'] ?? true
        );
    }

    private function validarConfiguracion(): void
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException(
                'Falta base_url en Config/ApiSunat.php.'
            );
        }

        if (
            !filter_var(
                $this->baseUrl,
                FILTER_VALIDATE_URL
            )
        ) {
            throw new RuntimeException(
                'base_url de APISUNAT no es válida.'
            );
        }

        if ($this->personaId === '') {
            throw new RuntimeException(
                'Falta persona_id de Felicity.'
            );
        }

        if ($this->personaToken === '') {
            throw new RuntimeException(
                'Falta persona_token de Felicity.'
            );
        }

        if (
            str_contains(
                $this->personaId,
                'COLOCA_AQUI'
            )
            || str_contains(
                $this->personaToken,
                'COLOCA_AQUI'
            )
        ) {
            throw new RuntimeException(
                'Debes configurar las credenciales reales de Felicity.'
            );
        }
    }

    private function validarFileName(
        string $fileName
    ): void {
        /*
         * RUC-TIPO-SERIE-CORRELATIVO
         * 11 dígitos - 2 dígitos - 4 caracteres - 8 dígitos
         */
        $patron = '/^\d{11}-(01|03)-[FB][A-Z0-9]{3}-\d{8}$/';

        if (!preg_match($patron, $fileName)) {
            throw new InvalidArgumentException(
                'fileName inválido: '
                . $fileName
                . '. Debe tener el formato '
                . 'RUC-01-F001-00000001 o '
                . 'RUC-03-B001-00000001.'
            );
        }
    }

    private function documentIdValido(
        string $documentId
    ): bool {
        return (
            $documentId !== ''
            && strlen($documentId) <= 100
            && preg_match(
                '/^[A-Za-z0-9_-]+$/',
                $documentId
            )
        );
    }

    private function request(
        string $method,
        string $path,
        ?array $payload = null
    ): array {
        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'http_code' => 0,
                'data' => null,
                'error' => 'La extensión cURL de PHP no está habilitada.'
            ];
        }

        $method = strtoupper(trim($method));

        $url = $this->baseUrl
            . '/'
            . ltrim($path, '/');

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8'
        ];

        $ch = curl_init();

        $opciones = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl
                ? 2
                : 0
        ];

        if ($payload !== null) {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
            );

            if ($json === false) {
                curl_close($ch);

                return [
                    'success' => false,
                    'http_code' => 0,
                    'data' => null,
                    'error' => 'No se pudo convertir la solicitud a JSON: '
                        . json_last_error_msg()
                ];
            }

            $opciones[CURLOPT_POSTFIELDS] = $json;
        }

        curl_setopt_array($ch, $opciones);

        $rawResponse = curl_exec($ch);

        $curlErrorNumber = curl_errno($ch);
        $curlError = curl_error($ch);

        $httpCode = (int)curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        if ($rawResponse === false) {
            return [
                'success' => false,
                'http_code' => $httpCode,
                'data' => null,
                'error' => 'Error de conexión con APISUNAT: '
                    . ($curlError !== ''
                        ? $curlError
                        : 'sin detalle')
            ];
        }

        $data = json_decode(
            $rawResponse,
            true
        );

        if (
            json_last_error() !== JSON_ERROR_NONE
            || !is_array($data)
        ) {
            return [
                'success' => false,
                'http_code' => $httpCode,
                'data' => null,
                'error' => 'APISUNAT devolvió una respuesta que no es JSON válido.',
                'raw_response' => mb_substr(
                    (string)$rawResponse,
                    0,
                    1000
                )
            ];
        }

        $httpExitoso = (
            $httpCode >= 200
            && $httpCode < 300
        );

        return [
            'success' => $httpExitoso,
            'http_code' => $httpCode,
            'data' => $data,
            'error' => $httpExitoso
                ? null
                : $this->extraerErrorApi($data)
        ];
    }

    private function obtenerMensajeError(
        array $data,
        array $respuesta
    ): string {
        $mensajeApi = $this->extraerErrorApi(
            $data
        );

        if ($mensajeApi !== '') {
            return $mensajeApi;
        }

        $mensajeConexion = trim(
            (string)($respuesta['error'] ?? '')
        );

        if ($mensajeConexion !== '') {
            return $mensajeConexion;
        }

        $httpCode = (int)(
            $respuesta['http_code'] ?? 0
        );

        if ($httpCode > 0) {
            return 'APISUNAT respondió con HTTP '
                . $httpCode
                . '.';
        }

        return 'No se pudo completar la solicitud a APISUNAT.';
    }

    private function extraerErrorApi(
        array $data
    ): string {
        if (isset($data['message'])) {
            if (is_string($data['message'])) {
                return trim($data['message']);
            }
        }

        if (isset($data['mensaje'])) {
            if (is_string($data['mensaje'])) {
                return trim($data['mensaje']);
            }
        }

        if (isset($data['error'])) {
            if (is_string($data['error'])) {
                return trim($data['error']);
            }

            if (is_array($data['error'])) {
                $jsonError = json_encode(
                    $data['error'],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                );

                return $jsonError !== false
                    ? $jsonError
                    : 'APISUNAT devolvió un error.';
            }
        }

        return '';
    }

    private function mensajeSegunEstado(
        string $status
    ): string {
        return match ($status) {
            'PENDIENTE' =>
                'APISUNAT continúa procesando el comprobante.',

            'ACEPTADO' =>
                'El comprobante fue aceptado.',

            'RECHAZADO' =>
                'El comprobante fue rechazado.',

            'EXCEPCION' =>
                'APISUNAT registró una excepción durante el procesamiento.',

            default =>
                'Estado desconocido.'
        };
    }

    private function ocultarPersonaId(
        string $personaId
    ): string {
        $longitud = strlen($personaId);

        if ($longitud <= 8) {
            return '********';
        }

        return substr($personaId, 0, 4)
            . str_repeat('*', $longitud - 8)
            . substr($personaId, -4);
    }
}