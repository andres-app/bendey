<?php

declare(strict_types=1);

class ApiSunatStorage
{
    private string $raiz;
    private int $maximoBytes = 20971520; // 20 MB

    public function __construct(?string $raiz = null)
    {
        $this->raiz = $raiz
            ? rtrim($raiz, '/\\')
            : dirname(__DIR__) . '/storage/apisunat';

        $this->prepararDirectorios();
    }

    public function guardarDesdeUrl(
        string $url,
        string $tipo
    ): string {
        $tipo = strtolower(trim($tipo));

        if (!in_array($tipo, ['xml', 'cdr'], true)) {
            throw new InvalidArgumentException(
                'El tipo de archivo debe ser xml o cdr.'
            );
        }

        $this->validarUrl($url);

        $rutaUrl = (string)parse_url(
            $url,
            PHP_URL_PATH
        );

        $nombreArchivo = rawurldecode(
            basename($rutaUrl)
        );

        $nombreArchivo = preg_replace(
            '/[^A-Za-z0-9._-]/',
            '_',
            $nombreArchivo
        );

        if (
            $nombreArchivo === ''
            || $nombreArchivo === '.'
            || $nombreArchivo === '..'
        ) {
            throw new RuntimeException(
                'No se pudo determinar el nombre del archivo.'
            );
        }

        if (!str_ends_with(
            strtolower($nombreArchivo),
            '.zip'
        )) {
            $nombreArchivo .= '.ZIP';
        }

        $directorioDestino = $this->raiz
            . DIRECTORY_SEPARATOR
            . $tipo;

        $rutaDestino = $directorioDestino
            . DIRECTORY_SEPARATOR
            . $nombreArchivo;

        if (
            is_file($rutaDestino)
            && filesize($rutaDestino) > 0
        ) {
            return $this->rutaRelativa(
                $tipo,
                $nombreArchivo
            );
        }

        $rutaTemporal = $rutaDestino
            . '.tmp-'
            . bin2hex(random_bytes(6));

        $archivo = fopen(
            $rutaTemporal,
            'wb'
        );

        if ($archivo === false) {
            throw new RuntimeException(
                'No se pudo crear el archivo temporal.'
            );
        }

        $bytesDescargados = 0;
        $excesoTamano = false;

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: application/zip, application/octet-stream;q=0.9, */*;q=0.8'
            ],
            CURLOPT_WRITEFUNCTION => function (
                $curl,
                string $contenido
            ) use (
                $archivo,
                &$bytesDescargados,
                &$excesoTamano
            ): int {
                $longitud = strlen($contenido);

                $bytesDescargados += $longitud;

                if (
                    $bytesDescargados
                    > $this->maximoBytes
                ) {
                    $excesoTamano = true;
                    return 0;
                }

                $escritos = fwrite(
                    $archivo,
                    $contenido
                );

                return $escritos === false
                    ? 0
                    : $escritos;
            }
        ]);

        $resultado = curl_exec($ch);

        $errorCurl = curl_error($ch);
        $codigoHttp = (int)curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);
        fclose($archivo);

        if ($resultado === false) {
            @unlink($rutaTemporal);

            if ($excesoTamano) {
                throw new RuntimeException(
                    'El archivo de APISUNAT supera los 20 MB.'
                );
            }

            throw new RuntimeException(
                'No se pudo descargar el archivo: '
                . ($errorCurl !== ''
                    ? $errorCurl
                    : 'error desconocido')
            );
        }

        if ($codigoHttp !== 200) {
            @unlink($rutaTemporal);

            throw new RuntimeException(
                'APISUNAT respondió con HTTP '
                . $codigoHttp
                . ' al descargar el archivo.'
            );
        }

        if (
            !is_file($rutaTemporal)
            || filesize($rutaTemporal) < 4
        ) {
            @unlink($rutaTemporal);

            throw new RuntimeException(
                'El archivo descargado está vacío.'
            );
        }

        $verificacion = fopen(
            $rutaTemporal,
            'rb'
        );

        $firma = $verificacion !== false
            ? fread($verificacion, 4)
            : '';

        if ($verificacion !== false) {
            fclose($verificacion);
        }

        if (!str_starts_with($firma, 'PK')) {
            @unlink($rutaTemporal);

            throw new RuntimeException(
                'El archivo descargado no es un ZIP válido.'
            );
        }

        if (!rename(
            $rutaTemporal,
            $rutaDestino
        )) {
            @unlink($rutaTemporal);

            throw new RuntimeException(
                'No se pudo mover el archivo descargado.'
            );
        }

        @chmod($rutaDestino, 0640);

        return $this->rutaRelativa(
            $tipo,
            $nombreArchivo
        );
    }

    public function existe(
        ?string $rutaRelativa
    ): bool {
        if (!$rutaRelativa) {
            return false;
        }

        $rutaAbsoluta = $this->rutaAbsoluta(
            $rutaRelativa
        );

        return is_file($rutaAbsoluta)
            && filesize($rutaAbsoluta) > 0;
    }

    public function rutaAbsoluta(
        string $rutaRelativa
    ): string {
        $rutaRelativa = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            trim($rutaRelativa)
        );

        $prefijo = 'storage'
            . DIRECTORY_SEPARATOR
            . 'apisunat'
            . DIRECTORY_SEPARATOR;

        if (!str_starts_with(
            $rutaRelativa,
            $prefijo
        )) {
            throw new RuntimeException(
                'La ruta local no pertenece a APISUNAT.'
            );
        }

        $subRuta = substr(
            $rutaRelativa,
            strlen($prefijo)
        );

        if (
            str_contains($subRuta, '..')
            || $subRuta === ''
        ) {
            throw new RuntimeException(
                'La ruta local no es válida.'
            );
        }

        return $this->raiz
            . DIRECTORY_SEPARATOR
            . $subRuta;
    }

    private function validarUrl(
        string $url
    ): void {
        if (!filter_var(
            $url,
            FILTER_VALIDATE_URL
        )) {
            throw new InvalidArgumentException(
                'La URL del archivo no es válida.'
            );
        }

        $esquema = strtolower(
            (string)parse_url(
                $url,
                PHP_URL_SCHEME
            )
        );

        $host = strtolower(
            (string)parse_url(
                $url,
                PHP_URL_HOST
            )
        );

        if ($esquema !== 'https') {
            throw new RuntimeException(
                'Solo se permiten descargas HTTPS.'
            );
        }

        if ($host !== 'cdn.apisunat.com') {
            throw new RuntimeException(
                'El archivo no pertenece al CDN autorizado de APISUNAT.'
            );
        }
    }

    private function prepararDirectorios(): void
    {
        foreach ([
            $this->raiz,
            $this->raiz . '/xml',
            $this->raiz . '/cdr'
        ] as $directorio) {
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
                    'No se pudo crear el directorio '
                    . $directorio
                );
            }
        }

        $htaccess = $this->raiz
            . '/.htaccess';

        if (!is_file($htaccess)) {
            $contenido = <<<'HTACCESS'
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
                $contenido
            );
        }
    }

    private function rutaRelativa(
        string $tipo,
        string $nombreArchivo
    ): string {
        return 'storage/apisunat/'
            . $tipo
            . '/'
            . $nombreArchivo;
    }
}