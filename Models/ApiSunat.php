<?php

declare(strict_types=1);

require_once __DIR__ . '/Company.php';

class ApiSunat
{
    private array $config;

    private string $baseUrl;
    private string $personaId;
    private string $personaToken;
    private string $rucEmpresa;

    private int $idNegocio;
    private int $connectTimeout;
    private int $timeout;

    private bool $verifySsl;
    private bool $production;

    public function __construct()
    {
        /*
        |--------------------------------------------------------------------------
        | CONFIGURACIÓN TÉCNICA
        |--------------------------------------------------------------------------
        */
        $rutaConfig =
            __DIR__ . '/../Config/ApiSunat.php';

        if (!is_file($rutaConfig)) {
            throw new RuntimeException(
                'No existe el archivo Config/ApiSunat.php.'
            );
        }

        $config = require $rutaConfig;

        if (!is_array($config)) {
            throw new RuntimeException(
                'La configuración técnica de APISUNAT no es válida.'
            );
        }

        $this->config = $config;

        $this->baseUrl = rtrim(
            trim(
                (string)(
                    $config['base_url']
                    ?? ''
                )
            ),
            '/'
        );

        $this->connectTimeout = max(
            1,
            (int)(
                $config['connect_timeout']
                ?? 15
            )
        );

        $this->timeout = max(
            $this->connectTimeout,
            (int)(
                $config['timeout']
                ?? 60
            )
        );

        $this->verifySsl = (bool)(
            $config['verify_ssl']
            ?? true
        );

        /*
        |--------------------------------------------------------------------------
        | CREDENCIALES DE LA EMPRESA ACTIVA
        |--------------------------------------------------------------------------
        */
        $company = new Company();

        $credenciales =
            $company->obtenerCredencialesApiSunat();

        $this->idNegocio = (int)(
            $credenciales['id_negocio']
            ?? 0
        );

        $this->rucEmpresa = trim(
            (string)(
                $credenciales['ruc']
                ?? ''
            )
        );

        $this->personaId = trim(
            (string)(
                $credenciales['persona_id']
                ?? ''
            )
        );

        $this->personaToken = trim(
            (string)(
                $credenciales['persona_token']
                ?? ''
            )
        );

        $this->production = (bool)(
            $credenciales['production']
            ?? true
        );

        $this->validarConfiguracion();
    }

    /*
    |--------------------------------------------------------------------------
    | ENVIAR COMPROBANTE
    |--------------------------------------------------------------------------
    */
    public function enviarComprobante(
        string $fileName,
        array $documentBody,
        ?string $customerEmail = null
    ): array {
        $fileName = strtoupper(
            trim($fileName)
        );

        $this->validarFileName(
            $fileName
        );

        /*
         * Verificar que el RUC del nombre del archivo
         * corresponda a la empresa activa.
         */
        $rucFileName = substr(
            $fileName,
            0,
            11
        );

        if (
            $this->rucEmpresa !== ''
            && $rucFileName !== $this->rucEmpresa
        ) {
            throw new RuntimeException(
                'El RUC del comprobante no corresponde a la empresa activa.'
            );
        }

        if (empty($documentBody)) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'documentId' => null,
                'production' =>
                    $this->production,
                'message' =>
                    'El documentBody está vacío.',
                'http_code' => 0,
                'response' => null
            ];
        }

        $payload = [
            'personaId' =>
                $this->personaId,

            'personaToken' =>
                $this->personaToken,

            'fileName' =>
                $fileName,

            'documentBody' =>
                $documentBody
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
            $payload['customerEmail'] =
                $customerEmail;
        }

        $respuesta = $this->request(
            'POST',
            '/personas/v1/sendBill',
            $payload
        );

        $data = is_array(
            $respuesta['data']
            ?? null
        )
            ? $respuesta['data']
            : [];

        $status = strtoupper(
            trim(
                (string)(
                    $data['status']
                    ?? 'ERROR'
                )
            )
        );

        $documentId = trim(
            (string)(
                $data['documentId']
                ?? ''
            )
        );

        $exito = (
            ($respuesta['success'] ?? false)
                === true
            && $status === 'PENDIENTE'
            && $documentId !== ''
        );

        if ($exito) {
            return [
                'success' => true,
                'status' => 'PENDIENTE',
                'documentId' =>
                    $documentId,
                'production' =>
                    isset($data['production'])
                        ? (bool)$data['production']
                        : $this->production,
                'message' =>
                    'Comprobante recibido por APISUNAT.',
                'http_code' =>
                    $respuesta['http_code'],
                'response' =>
                    $data
            ];
        }

        return [
            'success' => false,
            'status' =>
                $status !== ''
                    ? $status
                    : 'ERROR',

            'documentId' =>
                $documentId !== ''
                    ? $documentId
                    : null,

            'production' =>
                isset($data['production'])
                    ? (bool)$data['production']
                    : $this->production,

            'message' =>
                $this->obtenerMensajeError(
                    $data,
                    $respuesta
                ),

            'http_code' =>
                $respuesta['http_code']
                ?? 0,

            'response' =>
                $data
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CONSULTAR DOCUMENTO
    |--------------------------------------------------------------------------
    */
    public function consultarDocumento(
        string $documentId
    ): array {
        $documentId = trim(
            $documentId
        );

        if (
            !$this->documentIdValido(
                $documentId
            )
        ) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' =>
                    'El documentId no es válido.',
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

        $data = is_array(
            $respuesta['data']
            ?? null
        )
            ? $respuesta['data']
            : [];

        $status = strtoupper(
            trim(
                (string)(
                    $data['status']
                    ?? ''
                )
            )
        );

        $estadosValidos = [
            'PENDIENTE',
            'ACEPTADO',
            'RECHAZADO',
            'EXCEPCION'
        ];

        $exito = (
            ($respuesta['success'] ?? false)
                === true
            && in_array(
                $status,
                $estadosValidos,
                true
            )
        );

        return [
            'success' =>
                $exito,

            'status' =>
                $status !== ''
                    ? $status
                    : 'ERROR',

            'message' =>
                $exito
                    ? $this->mensajeSegunEstado(
                        $status
                    )
                    : $this->obtenerMensajeError(
                        $data,
                        $respuesta
                    ),

            'http_code' =>
                $respuesta['http_code']
                ?? 0,

            'document' =>
                $data,

            'xml' => trim(
                (string)(
                    $data['xml']
                    ?? ''
                )
            ),

            'cdr' => trim(
                (string)(
                    $data['cdr']
                    ?? ''
                )
            ),

            'fileName' => trim(
                (string)(
                    $data['fileName']
                    ?? ''
                )
            ),

            'production' =>
                isset($data['production'])
                    ? (bool)$data['production']
                    : $this->production,

            'type' => trim(
                (string)(
                    $data['type']
                    ?? ''
                )
            ),

            'reference' => trim(
                (string)(
                    $data['reference']
                    ?? ''
                )
            ),

            'faults' =>
                is_array(
                    $data['faults']
                    ?? null
                )
                    ? $data['faults']
                    : [],

            'notes' =>
                is_array(
                    $data['notes']
                    ?? null
                )
                    ? $data['notes']
                    : [],

            'issueTime' =>
                $data['issueTime']
                ?? null,

            'responseTime' =>
                $data['responseTime']
                ?? null
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER ÚLTIMO CORRELATIVO
    |--------------------------------------------------------------------------
    */
    public function obtenerUltimoDocumento(
        string $tipo,
        string $serie
    ): array {
        $tipo = trim(
            $tipo
        );

        $serie = strtoupper(
            trim($serie)
        );

        if (
            !in_array(
                $tipo,
                ['01', '03'],
                true
            )
        ) {
            return [
                'success' => false,
                'message' =>
                    'El tipo debe ser 01 para factura o 03 para boleta.'
            ];
        }

        if (
            !preg_match(
                '/^[FB][A-Z0-9]{3}$/',
                $serie
            )
        ) {
            return [
                'success' => false,
                'message' =>
                    'La serie no es válida.'
            ];
        }

        if (
            $tipo === '01'
            && $serie[0] !== 'F'
        ) {
            return [
                'success' => false,
                'message' =>
                    'Una factura debe usar una serie que comience con F.'
            ];
        }

        if (
            $tipo === '03'
            && $serie[0] !== 'B'
        ) {
            return [
                'success' => false,
                'message' =>
                    'Una boleta debe usar una serie que comience con B.'
            ];
        }

        $payload = [
            'personaId' =>
                $this->personaId,

            'personaToken' =>
                $this->personaToken,

            'type' =>
                $tipo,

            'serie' =>
                $serie
        ];

        $respuesta = $this->request(
            'POST',
            '/personas/lastDocument',
            $payload
        );

        $data = is_array(
            $respuesta['data']
            ?? null
        )
            ? $respuesta['data']
            : [];

        if (
            ($respuesta['success'] ?? false)
            !== true
        ) {
            return [
                'success' => false,

                'message' =>
                    $this->obtenerMensajeError(
                        $data,
                        $respuesta
                    ),

                'http_code' =>
                    $respuesta['http_code']
                    ?? 0,

                'response' =>
                    $this->sanitizarRespuesta(
                        $data
                    )
            ];
        }

        return [
            'success' => true,

            'production' =>
                isset($data['production'])
                    ? (bool)$data['production']
                    : $this->production,

            'type' => trim(
                (string)(
                    $data['type']
                    ?? $tipo
                )
            ),

            'serie' => trim(
                (string)(
                    $data['serie']
                    ?? $serie
                )
            ),

            'lastNumber' => str_pad(
                trim(
                    (string)(
                        $data['lastNumber']
                        ?? '0'
                    )
                ),
                8,
                '0',
                STR_PAD_LEFT
            ),

            'suggestedNumber' => str_pad(
                trim(
                    (string)(
                        $data['suggestedNumber']
                        ?? '1'
                    )
                ),
                8,
                '0',
                STR_PAD_LEFT
            ),

            /*
             * Nunca se devuelve el Persona Token.
             * El Persona ID también se muestra enmascarado.
             */
            'response' =>
                $this->sanitizarRespuesta(
                    $data
                )
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | REQUEST SEGURO PARA REGISTRO EN BD
    |--------------------------------------------------------------------------
    */
    public function crearRequestSeguro(
        string $fileName,
        array $documentBody,
        ?string $customerEmail = null
    ): array {
        $request = [
            'personaId' =>
                $this->ocultarPersonaId(
                    $this->personaId
                ),

            'personaToken' =>
                '[OCULTO]',

            'fileName' =>
                strtoupper(
                    trim($fileName)
                ),

            'documentBody' =>
                $documentBody
        ];

        $customerEmail = trim(
            (string)$customerEmail
        );

        if ($customerEmail !== '') {
            $request['customerEmail'] =
                $customerEmail;
        }

        return $request;
    }

    /*
    |--------------------------------------------------------------------------
    | INFORMACIÓN DE AMBIENTE
    |--------------------------------------------------------------------------
    */
    public function esProduccion(): bool
    {
        return $this->production;
    }

    public function obtenerIdNegocio(): int
    {
        return $this->idNegocio;
    }

    public function obtenerRucEmpresa(): string
    {
        return $this->rucEmpresa;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR CONFIGURACIÓN
    |--------------------------------------------------------------------------
    */
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
                'La URL base de APISUNAT no es válida.'
            );
        }

        if ($this->idNegocio <= 0) {
            throw new RuntimeException(
                'No se pudo determinar la empresa activa.'
            );
        }

        if (
            !preg_match(
                '/^\d{11}$/',
                $this->rucEmpresa
            )
        ) {
            throw new RuntimeException(
                'El RUC de la empresa activa no es válido.'
            );
        }

        if ($this->personaId === '') {
            throw new RuntimeException(
                'Falta configurar el Persona ID de APISUNAT.'
            );
        }

        if (
            !preg_match(
                '/^[A-Za-z0-9_-]{10,100}$/',
                $this->personaId
            )
        ) {
            throw new RuntimeException(
                'El Persona ID de APISUNAT no es válido.'
            );
        }

        if ($this->personaToken === '') {
            throw new RuntimeException(
                'Falta configurar el Persona Token de APISUNAT.'
            );
        }

        if (
            strlen($this->personaToken)
            < 20
        ) {
            throw new RuntimeException(
                'El Persona Token de APISUNAT parece incompleto.'
            );
        }

        if (
            str_contains(
                strtoupper($this->personaId),
                'COLOCA_AQUI'
            )
            || str_contains(
                strtoupper($this->personaToken),
                'COLOCA_AQUI'
            )
        ) {
            throw new RuntimeException(
                'Debes registrar las credenciales reales desde la configuración de la empresa.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR NOMBRE DEL COMPROBANTE
    |--------------------------------------------------------------------------
    */
    private function validarFileName(
        string $fileName
    ): void {
        $patron =
            '/^\d{11}-(01|03)-[FB][A-Z0-9]{3}-\d{8}$/';

        if (
            !preg_match(
                $patron,
                $fileName
            )
        ) {
            throw new InvalidArgumentException(
                'fileName inválido: '
                . $fileName
                . '. Debe tener el formato '
                . 'RUC-01-F001-00000001 o '
                . 'RUC-03-B001-00000001.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR DOCUMENT ID
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | PETICIÓN HTTP
    |--------------------------------------------------------------------------
    */
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
                'error' =>
                    'La extensión cURL de PHP no está habilitada.'
            ];
        }

        $method = strtoupper(
            trim($method)
        );

        $url = $this->baseUrl
            . '/'
            . ltrim(
                $path,
                '/'
            );

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8'
        ];

        $ch = curl_init();

        $opciones = [
            CURLOPT_URL =>
                $url,

            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_FOLLOWLOCATION =>
                false,

            CURLOPT_CONNECTTIMEOUT =>
                $this->connectTimeout,

            CURLOPT_TIMEOUT =>
                $this->timeout,

            CURLOPT_CUSTOMREQUEST =>
                $method,

            CURLOPT_HTTPHEADER =>
                $headers,

            CURLOPT_SSL_VERIFYPEER =>
                $this->verifySsl,

            CURLOPT_SSL_VERIFYHOST =>
                $this->verifySsl
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
                    'error' =>
                        'No se pudo convertir la solicitud a JSON: '
                        . json_last_error_msg()
                ];
            }

            $opciones[CURLOPT_POSTFIELDS] =
                $json;
        }

        curl_setopt_array(
            $ch,
            $opciones
        );

        $rawResponse = curl_exec(
            $ch
        );

        $curlError = curl_error(
            $ch
        );

        $httpCode = (int)curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        if ($rawResponse === false) {
            return [
                'success' => false,
                'http_code' =>
                    $httpCode,
                'data' => null,
                'error' =>
                    'Error de conexión con APISUNAT: '
                    . (
                        $curlError !== ''
                            ? $curlError
                            : 'sin detalle'
                    )
            ];
        }

        $data = json_decode(
            $rawResponse,
            true
        );

        if (
            json_last_error()
            !== JSON_ERROR_NONE
            || !is_array($data)
        ) {
            return [
                'success' => false,
                'http_code' =>
                    $httpCode,
                'data' => null,
                'error' =>
                    'APISUNAT devolvió una respuesta que no es JSON válido.',
                'raw_response' =>
                    mb_substr(
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
            'success' =>
                $httpExitoso,

            'http_code' =>
                $httpCode,

            'data' =>
                $data,

            'error' =>
                $httpExitoso
                    ? null
                    : $this->extraerErrorApi(
                        $data
                    )
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | MENSAJE DE ERROR
    |--------------------------------------------------------------------------
    */
    private function obtenerMensajeError(
        array $data,
        array $respuesta
    ): string {
        $mensajeApi =
            $this->extraerErrorApi(
                $data
            );

        if ($mensajeApi !== '') {
            return $mensajeApi;
        }

        $mensajeConexion = trim(
            (string)(
                $respuesta['error']
                ?? ''
            )
        );

        if ($mensajeConexion !== '') {
            return $mensajeConexion;
        }

        $httpCode = (int)(
            $respuesta['http_code']
            ?? 0
        );

        if ($httpCode > 0) {
            return 'APISUNAT respondió con HTTP '
                . $httpCode
                . '.';
        }

        return 'No se pudo completar la solicitud a APISUNAT.';
    }

    /*
    |--------------------------------------------------------------------------
    | EXTRAER ERROR DE APISUNAT
    |--------------------------------------------------------------------------
    */
    private function extraerErrorApi(
        array $data
    ): string {
        if (
            isset($data['message'])
            && is_string($data['message'])
        ) {
            return trim(
                $data['message']
            );
        }

        if (
            isset($data['mensaje'])
            && is_string($data['mensaje'])
        ) {
            return trim(
                $data['mensaje']
            );
        }

        if (isset($data['error'])) {
            if (
                is_string(
                    $data['error']
                )
            ) {
                return trim(
                    $data['error']
                );
            }

            if (
                is_array(
                    $data['error']
                )
            ) {
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

    /*
    |--------------------------------------------------------------------------
    | MENSAJE SEGÚN ESTADO
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | OCULTAR PERSONA ID
    |--------------------------------------------------------------------------
    */
    private function ocultarPersonaId(
        string $personaId
    ): string {
        $longitud = strlen(
            $personaId
        );

        if ($longitud <= 8) {
            return '********';
        }

        return substr(
            $personaId,
            0,
            4
        )
            . str_repeat(
                '*',
                $longitud - 8
            )
            . substr(
                $personaId,
                -4
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SANITIZAR RESPUESTA
    |--------------------------------------------------------------------------
    */
    private function sanitizarRespuesta(
        array $data
    ): array {
        unset(
            $data['personaToken'],
            $data['persona_token']
        );

        if (
            isset($data['personaId'])
            && is_string(
                $data['personaId']
            )
        ) {
            $data['personaId'] =
                $this->ocultarPersonaId(
                    $data['personaId']
                );
        }

        return $data;
    }
}