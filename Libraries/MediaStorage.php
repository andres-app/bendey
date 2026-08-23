<?php

/**
 * TiquePOS - almacenamiento central de imágenes cargadas por el cliente.
 *
 * Las imágenes de usuarios, productos y empresa viven en:
 *   storage/images/users
 *   storage/images/products
 *   storage/images/company
 *
 * Assets/img queda reservado para recursos estáticos propios de la aplicación
 * (favicon, logotipo TiquePOS, iconos del tema, etc.).
 */

if (!function_exists('tiquepos_media_root')) {
    function tiquepos_media_root()
    {
        return dirname(__DIR__) . '/storage/images';
    }
}

if (!function_exists('tiquepos_media_legacy_root')) {
    function tiquepos_media_legacy_root()
    {
        return dirname(__DIR__) . '/Assets/img';
    }
}

if (!function_exists('tiquepos_media_validate_type')) {
    function tiquepos_media_validate_type($tipo)
    {
        $tipo = strtolower(trim((string)$tipo));
        $permitidos = array('users', 'products', 'company');

        if (!in_array($tipo, $permitidos, true)) {
            throw new InvalidArgumentException('Tipo de almacenamiento de imagen no válido.');
        }

        return $tipo;
    }
}

if (!function_exists('tiquepos_media_security_rules')) {
    function tiquepos_media_security_rules()
    {
        return "Options -Indexes\n"
            . "<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh|shtml|asp|aspx|jsp)$\">\n"
            . "    <IfModule mod_authz_core.c>\n"
            . "        Require all denied\n"
            . "    </IfModule>\n"
            . "    <IfModule !mod_authz_core.c>\n"
            . "        Order allow,deny\n"
            . "        Deny from all\n"
            . "    </IfModule>\n"
            . "</FilesMatch>\n"
            . "<IfModule mod_headers.c>\n"
            . "    Header set X-Content-Type-Options \"nosniff\"\n"
            . "</IfModule>\n";
    }
}

if (!function_exists('tiquepos_media_ensure_directories')) {
    function tiquepos_media_ensure_directories()
    {
        $root = tiquepos_media_root();
        $directorios = array(
            $root,
            $root . '/users',
            $root . '/products',
            $root . '/company'
        );

        foreach ($directorios as $directorio) {
            if (!is_dir($directorio)) {
                @mkdir($directorio, 0775, true);
            }
        }

        if (is_dir($root)) {
            $htaccess = $root . '/.htaccess';
            if (!is_file($htaccess)) {
                @file_put_contents($htaccess, tiquepos_media_security_rules(), LOCK_EX);
                @chmod($htaccess, 0644);
            }
        }

        return is_dir($root);
    }
}

if (!function_exists('tiquepos_media_dir')) {
    function tiquepos_media_dir($tipo)
    {
        $tipo = tiquepos_media_validate_type($tipo);
        tiquepos_media_ensure_directories();
        return tiquepos_media_root() . '/' . $tipo;
    }
}

if (!function_exists('tiquepos_media_filename')) {
    function tiquepos_media_filename($nombre)
    {
        $nombre = basename(trim((string)$nombre));

        if ($nombre === '' || !preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|webp)$/i', $nombre)) {
            return '';
        }

        return $nombre;
    }
}

if (!function_exists('tiquepos_media_path')) {
    function tiquepos_media_path($tipo, $nombre)
    {
        $nombre = tiquepos_media_filename($nombre);
        if ($nombre === '') {
            return '';
        }

        return tiquepos_media_dir($tipo) . DIRECTORY_SEPARATOR . $nombre;
    }
}

if (!function_exists('tiquepos_media_url')) {
    function tiquepos_media_url($tipo, $nombre)
    {
        $tipo = tiquepos_media_validate_type($tipo);
        $nombre = tiquepos_media_filename($nombre);

        if ($nombre === '') {
            return '';
        }

        return 'storage/images/' . $tipo . '/' . rawurlencode($nombre);
    }
}

if (!function_exists('tiquepos_media_copy_from_legacy')) {
    function tiquepos_media_copy_from_legacy($tipo, $nombre)
    {
        $tipo = tiquepos_media_validate_type($tipo);
        $nombre = tiquepos_media_filename($nombre);

        if ($nombre === '') {
            return false;
        }

        $destino = tiquepos_media_path($tipo, $nombre);
        if ($destino !== '' && is_file($destino)) {
            return true;
        }

        $origen = tiquepos_media_legacy_root() . '/' . $tipo . '/' . $nombre;
        if (!is_file($origen) || $destino === '') {
            return false;
        }

        if (!@copy($origen, $destino)) {
            return false;
        }

        @chmod($destino, 0644);
        return true;
    }
}

if (!function_exists('tiquepos_media_migrate_legacy')) {
    function tiquepos_media_migrate_legacy()
    {
        static $ejecutado = false;

        if ($ejecutado) {
            return;
        }
        $ejecutado = true;

        if (!tiquepos_media_ensure_directories()) {
            return;
        }

        $lock = tiquepos_media_root() . '/.legacy_assets_migrated';
        if (is_file($lock)) {
            return;
        }

        $completo = true;
        foreach (array('users', 'products', 'company') as $tipo) {
            $origen = tiquepos_media_legacy_root() . '/' . $tipo;
            if (!is_dir($origen)) {
                continue;
            }

            $archivos = @scandir($origen);
            if (!is_array($archivos)) {
                $completo = false;
                continue;
            }

            foreach ($archivos as $archivo) {
                if ($archivo === '.' || $archivo === '..') {
                    continue;
                }

                $nombre = tiquepos_media_filename($archivo);
                if ($nombre === '') {
                    continue;
                }

                if (!tiquepos_media_copy_from_legacy($tipo, $nombre)) {
                    $completo = false;
                }
            }
        }

        if ($completo) {
            @file_put_contents(
                $lock,
                'Migrated at ' . date(DATE_ATOM) . PHP_EOL,
                LOCK_EX
            );
            @chmod($lock, 0640);
        }
    }
}

if (!function_exists('tiquepos_media_existing_filename')) {
    function tiquepos_media_existing_filename($tipo, $nombre)
    {
        $nombre = tiquepos_media_filename($nombre);
        if ($nombre === '') {
            return '';
        }

        $ruta = tiquepos_media_path($tipo, $nombre);
        if ($ruta !== '' && is_file($ruta)) {
            return $nombre;
        }

        if (tiquepos_media_copy_from_legacy($tipo, $nombre)) {
            return $nombre;
        }

        return '';
    }
}

if (!function_exists('tiquepos_user_image_filename')) {
    function tiquepos_user_image_filename($idusuario, $imagen)
    {
        tiquepos_media_migrate_legacy();

        $existente = tiquepos_media_existing_filename('users', $imagen);
        if ($existente !== '') {
            return $existente;
        }

        $idusuario = (int)$idusuario;
        if ($idusuario <= 0) {
            return '';
        }

        foreach (array('jpg', 'jpeg', 'png', 'webp') as $extension) {
            $candidato = $idusuario . '.' . $extension;
            $existente = tiquepos_media_existing_filename('users', $candidato);
            if ($existente !== '') {
                return $existente;
            }
        }

        return '';
    }
}

if (!function_exists('tiquepos_user_avatar_data_uri')) {
    function tiquepos_user_avatar_data_uri($nombre)
    {
        $nombre = trim((string)$nombre);
        $inicial = 'U';

        if ($nombre !== '') {
            if (function_exists('mb_substr')) {
                $inicial = mb_strtoupper(mb_substr($nombre, 0, 1, 'UTF-8'), 'UTF-8');
            } else {
                $inicial = strtoupper(substr($nombre, 0, 1));
            }
        }

        $inicial = htmlspecialchars($inicial, ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">'
            . '<rect width="96" height="96" rx="48" fill="#eef2f6"/>'
            . '<text x="48" y="60" text-anchor="middle" font-family="Arial,sans-serif" font-size="38" fill="#5f6b76">'
            . $inicial
            . '</text></svg>';

        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }
}

if (!function_exists('tiquepos_user_avatar_url')) {
    function tiquepos_user_avatar_url($idusuario, $imagen, $nombre)
    {
        $archivo = tiquepos_user_image_filename($idusuario, $imagen);
        if ($archivo !== '') {
            return tiquepos_media_url('users', $archivo);
        }

        return tiquepos_user_avatar_data_uri($nombre);
    }
}
