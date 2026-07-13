<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';

class Company
{
    private string $tableName = 'datos_negocio';
    private Conexion $conexion;

    private string $cipher = 'aes-256-gcm';
    private string $prefijoCifrado = 'ENC_V1:';

    public function __construct()
    {
        $this->conexion = new Conexion();
    }

    /*
    |--------------------------------------------------------------------------
    | EDITAR DATOS DEL NEGOCIO
    |--------------------------------------------------------------------------
    */
    public function editar(
        $id_negocio,
        $nombre,
        $ndocumento,
        $documento,
        $direccion,
        $telefono,
        $email,
        $pais,
        $ciudad,
        $nombre_impuesto,
        $monto_impuesto,
        $moneda,
        $simbolo,
        $token_reniec_sunat,
        $apisunat_persona_id = '',
        $apisunat_persona_token = '',
        $apisunat_production = 1
    ): bool {
        $id_negocio = (int)$id_negocio;

        if ($id_negocio <= 0) {
            $id_negocio = $this->obtenerIdNegocioActivo();
        }

        if ($id_negocio <= 0) {
            throw new RuntimeException(
                'No existe una empresa activa para actualizar.'
            );
        }

        $registroActual = $this->conexion->getData(
            "SELECT
                apisunat_persona_token
             FROM {$this->tableName}
             WHERE id_negocio = ?
             LIMIT 1",
            [$id_negocio]
        );

        if (!is_array($registroActual)) {
            throw new RuntimeException(
                'No se encontró la configuración de la empresa.'
            );
        }

        /*
         * Si el campo llega vacío, se conserva el token actual.
         * Solo se cifra y reemplaza cuando se escribe uno nuevo.
         */
        $tokenApiSunatGuardar = trim(
            (string)(
                $registroActual['apisunat_persona_token']
                ?? ''
            )
        );

        $nuevoTokenApiSunat = trim(
            (string)$apisunat_persona_token
        );

        if ($nuevoTokenApiSunat !== '') {
            $tokenApiSunatGuardar =
                $this->cifrarToken(
                    $nuevoTokenApiSunat
                );
        }

        $apisunatProduction =
            (int)$apisunat_production === 1
                ? 1
                : 0;

        $sql = "UPDATE {$this->tableName}
                SET
                    nombre = ?,
                    ndocumento = ?,
                    documento = ?,
                    direccion = ?,
                    telefono = ?,
                    email = ?,
                    pais = ?,
                    ciudad = ?,
                    nombre_impuesto = ?,
                    monto_impuesto = ?,
                    moneda = ?,
                    simbolo = ?,
                    token_reniec_sunat = ?,
                    apisunat_persona_id = ?,
                    apisunat_persona_token = ?,
                    apisunat_production = ?
                WHERE id_negocio = ?";

        $arrData = [
            trim((string)$nombre),
            trim((string)$ndocumento),
            trim((string)$documento),
            trim((string)$direccion),
            trim((string)$telefono),
            trim((string)$email),
            trim((string)$pais),
            trim((string)$ciudad),
            trim((string)$nombre_impuesto),
            (float)$monto_impuesto,
            trim((string)$moneda),
            trim((string)$simbolo),
            trim((string)$token_reniec_sunat),
            trim((string)$apisunat_persona_id),
            $tokenApiSunatGuardar,
            $apisunatProduction,
            $id_negocio
        ];

        return (bool)$this->conexion->setData(
            $sql,
            $arrData
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MOSTRAR REGISTRO INTERNO
    |--------------------------------------------------------------------------
    | Este método puede incluir el token cifrado.
    | No debe enviarse directamente al navegador.
    */
    public function mostrar(
        int $id_negocio
    ): ?array {
        $resultado = $this->conexion->getData(
            "SELECT *
             FROM {$this->tableName}
             WHERE id_negocio = ?
             LIMIT 1",
            [$id_negocio]
        );

        return is_array($resultado)
            ? $resultado
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | MOSTRAR REGISTRO SEGURO
    |--------------------------------------------------------------------------
    | No devuelve el personaToken.
    */
    public function mostrarSeguro(
        int $id_negocio
    ): ?array {
        $registro = $this->mostrar(
            $id_negocio
        );

        if (!$registro) {
            return null;
        }

        $tokenConfigurado = trim(
            (string)(
                $registro['apisunat_persona_token']
                ?? ''
            )
        ) !== '';

        unset(
            $registro['apisunat_persona_token']
        );

        $registro['apisunat_token_configurado'] =
            $tokenConfigurado
                ? 1
                : 0;

        return $registro;
    }

    /*
    |--------------------------------------------------------------------------
    | MOSTRAR EMPRESA ACTIVA
    |--------------------------------------------------------------------------
    */
    public function mostrarActivoSeguro(): ?array
    {
        $idNegocio =
            $this->obtenerIdNegocioActivo();

        if ($idNegocio <= 0) {
            return null;
        }

        return $this->mostrarSeguro(
            $idNegocio
        );
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER CREDENCIALES APISUNAT
    |--------------------------------------------------------------------------
    | Uso exclusivo del servidor.
    | Devuelve el personaToken descifrado.
    */
    public function obtenerCredencialesApiSunat(
        ?int $idNegocio = null
    ): array {
        $idNegocio = $idNegocio
            && $idNegocio > 0
                ? $idNegocio
                : $this->obtenerIdNegocioActivo();

        if ($idNegocio <= 0) {
            throw new RuntimeException(
                'No existe una empresa activa.'
            );
        }

        $registro = $this->conexion->getData(
            "SELECT
                id_negocio,
                documento,
                apisunat_persona_id,
                apisunat_persona_token,
                apisunat_production
             FROM {$this->tableName}
             WHERE id_negocio = ?
             LIMIT 1",
            [$idNegocio]
        );

        if (!is_array($registro)) {
            throw new RuntimeException(
                'No se encontró la configuración APISUNAT.'
            );
        }

        $personaId = trim(
            (string)(
                $registro['apisunat_persona_id']
                ?? ''
            )
        );

        $tokenCifrado = trim(
            (string)(
                $registro['apisunat_persona_token']
                ?? ''
            )
        );

        $personaToken = $tokenCifrado !== ''
            ? $this->descifrarToken(
                $tokenCifrado
            )
            : '';

        return [
            'id_negocio' =>
                (int)$registro['id_negocio'],

            'ruc' => trim(
                (string)(
                    $registro['documento']
                    ?? ''
                )
            ),

            'persona_id' =>
                $personaId,

            'persona_token' =>
                $personaToken,

            'production' =>
                (int)(
                    $registro['apisunat_production']
                    ?? 1
                ) === 1
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER ID DE EMPRESA ACTIVA
    |--------------------------------------------------------------------------
    */
    public function obtenerIdNegocioActivo(): int
    {
        $registro = $this->conexion->getData(
            "SELECT id_negocio
             FROM {$this->tableName}
             WHERE condicion = 1
             ORDER BY id_negocio DESC
             LIMIT 1",
            []
        );

        return is_array($registro)
            ? (int)($registro['id_negocio'] ?? 0)
            : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | IMPUESTO
    |--------------------------------------------------------------------------
    */
    public function mostrar_impuesto(): array
    {
        return $this->conexion->getDataAll(
            "SELECT monto_impuesto
             FROM {$this->tableName}
             WHERE condicion = 1
             ORDER BY id_negocio DESC
             LIMIT 1"
        );
    }

    public function nombre_impuesto(): array
    {
        return $this->conexion->getDataAll(
            "SELECT nombre_impuesto
             FROM {$this->tableName}
             WHERE condicion = 1
             ORDER BY id_negocio DESC
             LIMIT 1"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SÍMBOLO DE MONEDA
    |--------------------------------------------------------------------------
    */
    public function mostrar_simbolo(): array
    {
        return $this->conexion->getDataAll(
            "SELECT simbolo
             FROM {$this->tableName}
             WHERE condicion = 1
             ORDER BY id_negocio DESC
             LIMIT 1"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LISTAR
    |--------------------------------------------------------------------------
    */
    public function listar(): array
    {
        return $this->conexion->getDataAll(
            "SELECT
                id_negocio,
                nombre,
                ndocumento,
                documento,
                direccion,
                telefono,
                email,
                pais,
                ciudad,
                nombre_impuesto,
                monto_impuesto,
                moneda,
                simbolo,
                condicion,
                apisunat_persona_id,
                apisunat_production,
                CASE
                    WHEN apisunat_persona_token IS NOT NULL
                     AND apisunat_persona_token <> ''
                    THEN 1
                    ELSE 0
                END AS apisunat_token_configurado
             FROM {$this->tableName}
             ORDER BY id_negocio DESC"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TOKEN RENIEC/RUC
    |--------------------------------------------------------------------------
    */
    public function obtenerToken(): string
    {
        $idNegocio =
            $this->obtenerIdNegocioActivo();

        if ($idNegocio <= 0) {
            return '';
        }

        $resultado = $this->conexion->getData(
            "SELECT token_reniec_sunat
             FROM {$this->tableName}
             WHERE id_negocio = ?
             LIMIT 1",
            [$idNegocio]
        );

        return is_array($resultado)
            ? (string)(
                $resultado['token_reniec_sunat']
                ?? ''
            )
            : '';
    }

    public function actualizarToken(
        string $nuevoToken
    ): bool {
        $idNegocio =
            $this->obtenerIdNegocioActivo();

        if ($idNegocio <= 0) {
            return false;
        }

        return (bool)$this->conexion->setData(
            "UPDATE {$this->tableName}
             SET token_reniec_sunat = ?
             WHERE id_negocio = ?",
            [
                trim($nuevoToken),
                $idNegocio
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CIFRAR PERSONA TOKEN
    |--------------------------------------------------------------------------
    */
    private function cifrarToken(
        string $token
    ): string {
        $token = trim($token);

        if ($token === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException(
                'OpenSSL no está disponible en el servidor.'
            );
        }

        $clave = $this->obtenerClaveCifrado();

        $longitudIv = openssl_cipher_iv_length(
            $this->cipher
        );

        if ($longitudIv <= 0) {
            throw new RuntimeException(
                'No se pudo determinar el IV de cifrado.'
            );
        }

        $iv = random_bytes(
            $longitudIv
        );

        $tag = '';

        $contenidoCifrado = openssl_encrypt(
            $token,
            $this->cipher,
            $clave,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($contenidoCifrado === false) {
            throw new RuntimeException(
                'No se pudo cifrar el personaToken.'
            );
        }

        return $this->prefijoCifrado
            . base64_encode(
                $iv
                . $tag
                . $contenidoCifrado
            );
    }

    /*
    |--------------------------------------------------------------------------
    | DESCIFRAR PERSONA TOKEN
    |--------------------------------------------------------------------------
    */
    private function descifrarToken(
        string $valor
    ): string {
        $valor = trim($valor);

        if ($valor === '') {
            return '';
        }

        /*
         * Compatibilidad con registros antiguos guardados
         * en texto simple. Al volver a guardar la empresa,
         * quedará cifrado.
         */
        if (!str_starts_with(
            $valor,
            $this->prefijoCifrado
        )) {
            return $valor;
        }

        if (!function_exists('openssl_decrypt')) {
            throw new RuntimeException(
                'OpenSSL no está disponible en el servidor.'
            );
        }

        $contenidoBase64 = substr(
            $valor,
            strlen($this->prefijoCifrado)
        );

        $contenido = base64_decode(
            $contenidoBase64,
            true
        );

        if ($contenido === false) {
            throw new RuntimeException(
                'El personaToken cifrado no es válido.'
            );
        }

        $longitudIv = openssl_cipher_iv_length(
            $this->cipher
        );

        $longitudTag = 16;

        if (
            strlen($contenido)
            <= ($longitudIv + $longitudTag)
        ) {
            throw new RuntimeException(
                'El personaToken cifrado está incompleto.'
            );
        }

        $iv = substr(
            $contenido,
            0,
            $longitudIv
        );

        $tag = substr(
            $contenido,
            $longitudIv,
            $longitudTag
        );

        $contenidoCifrado = substr(
            $contenido,
            $longitudIv + $longitudTag
        );

        $token = openssl_decrypt(
            $contenidoCifrado,
            $this->cipher,
            $this->obtenerClaveCifrado(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($token === false) {
            throw new RuntimeException(
                'No se pudo descifrar el personaToken.'
            );
        }

        return trim($token);
    }

    /*
    |--------------------------------------------------------------------------
    | CLAVE PRIVADA DE CIFRADO
    |--------------------------------------------------------------------------
    | La genera una sola vez en:
    | storage/private/apisunat.key
    */
    private function obtenerClaveCifrado(): string
    {
        $directorio = dirname(__DIR__)
            . '/storage/private';

        $rutaClave = $directorio
            . '/apisunat.key';

        if (
            !is_dir($directorio)
            && !mkdir(
                $directorio,
                0750,
                true
            )
            && !is_dir($directorio)
        ) {
            throw new RuntimeException(
                'No se pudo crear storage/private.'
            );
        }

        $htaccess = $directorio
            . '/.htaccess';

        if (!is_file($htaccess)) {
            $proteccion = <<<'HTACCESS'
Options -Indexes

<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>
HTACCESS;

            file_put_contents(
                $htaccess,
                $proteccion,
                LOCK_EX
            );
        }

        if (!is_file($rutaClave)) {
            $claveNueva = base64_encode(
                random_bytes(32)
            );

            $guardado = file_put_contents(
                $rutaClave,
                $claveNueva,
                LOCK_EX
            );

            if ($guardado === false) {
                throw new RuntimeException(
                    'No se pudo crear la clave de cifrado.'
                );
            }

            @chmod(
                $rutaClave,
                0600
            );
        }

        $contenido = trim(
            (string)file_get_contents(
                $rutaClave
            )
        );

        $clave = base64_decode(
            $contenido,
            true
        );

        if (
            $clave === false
            || strlen($clave) !== 32
        ) {
            throw new RuntimeException(
                'La clave de cifrado APISUNAT no es válida.'
            );
        }

        return $clave;
    }
}