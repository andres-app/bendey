<?php

declare(strict_types=1);

final class TiquePOSInstaller
{
    private string $root;
    private string $schemaPath;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
        $this->schemaPath = $this->root . '/database/base.sql';
    }

    public function environment(): array
    {
        return [
            'php' => [
                'ok' => version_compare(PHP_VERSION, '7.4.0', '>='),
                'label' => 'PHP 7.4 o superior',
                'value' => PHP_VERSION,
            ],
            'pdo_mysql' => [
                'ok' => extension_loaded('pdo_mysql'),
                'label' => 'PDO MySQL',
                'value' => extension_loaded('pdo_mysql') ? 'Disponible' : 'No disponible',
            ],
            'curl' => [
                'ok' => function_exists('curl_init') || (bool) ini_get('allow_url_fopen'),
                'label' => 'Conexiones HTTPS salientes',
                'value' => function_exists('curl_init') ? 'cURL' : ((bool) ini_get('allow_url_fopen') ? 'allow_url_fopen' : 'No disponible'),
            ],
            'openssl' => [
                'ok' => extension_loaded('openssl') && function_exists('openssl_verify'),
                'label' => 'OpenSSL',
                'value' => extension_loaded('openssl') ? OPENSSL_VERSION_TEXT : 'No disponible',
            ],
            'config_writable' => [
                'ok' => is_writable($this->root . '/Config'),
                'label' => 'Carpeta Config escribible',
                'value' => is_writable($this->root . '/Config') ? 'Sí' : 'No',
            ],
            'storage_writable' => [
                'ok' => is_writable($this->root . '/storage'),
                'label' => 'Carpeta storage escribible',
                'value' => is_writable($this->root . '/storage') ? 'Sí' : 'No',
            ],
            'schema' => [
                'ok' => is_file($this->schemaPath) && is_readable($this->schemaPath),
                'label' => 'Plantilla de base de datos',
                'value' => is_file($this->schemaPath) ? 'Disponible' : 'No encontrada',
            ],
        ];
    }

    public function canInstall(): bool
    {
        foreach ($this->environment() as $check) {
            if (empty($check['ok'])) {
                return false;
            }
        }
        return true;
    }

    public function install(array $input, array $defaults): array
    {
        if (!$this->canInstall()) {
            throw new RuntimeException('El servidor no cumple los requisitos mínimos de instalación.');
        }

        if ($this->isInstalled()) {
            throw new RuntimeException('TiquePOS ya está instalado en este dominio.');
        }

        $data = $this->normalize($input, $defaults);
        $pdo = $this->connect($data);

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if (count($tables) > 0) {
            throw new RuntimeException('La base de datos seleccionada no está vacía. Usa una base de datos nueva para evitar sobrescribir información.');
        }

        try {
            $this->importSchema($pdo);
            $this->createInstallationData($pdo, $data);
            $this->writeLocalConfig($data);
            $this->createRuntimeDirectories();
            $this->writeLock($data);
            $this->scrubInstallerSecrets();
        } catch (Throwable $e) {
            $this->cleanupEmptyTargetDatabase($pdo);
            @unlink($this->root . '/Config/local.php');
            @unlink($this->root . '/storage/installed.lock');
            throw $e;
        }

        return [
            'company' => $data['company_name'],
            'domain' => $data['domain'],
            'database' => $data['db_name'],
            'login' => $data['admin_login'],
            'control_enabled' => $data['control_enabled'],
            'cron_path' => $this->root . '/Control/sync.php',
        ];
    }

    public function isInstalled(): bool
    {
        return is_file($this->root . '/Config/local.php')
            && is_file($this->root . '/storage/installed.lock');
    }

    private function normalize(array $input, array $defaults): array
    {
        $get = static function (string $key, string $fallback = '') use ($input): string {
            return trim((string)($input[$key] ?? $fallback));
        };

        $domain = strtolower($get('domain', (string)($defaults['domain'] ?? '')));
        $domain = preg_replace('/^https?:\/\//i', '', $domain) ?? $domain;
        $domain = trim($domain, '/ ');
        if ($domain === '' || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            throw new InvalidArgumentException('El dominio ingresado no es válido.');
        }

        $dbHost = $get('db_host', 'localhost');
        $dbPort = (int)$get('db_port', '3306');
        $dbName = $get('db_name');
        $dbUser = $get('db_user');
        $dbPass = (string)($input['db_pass'] ?? '');
        if ($dbHost === '' || $dbName === '' || $dbUser === '' || $dbPort <= 0 || $dbPort > 65535) {
            throw new InvalidArgumentException('Completa correctamente los datos de conexión a MySQL.');
        }

        if (!preg_match('/^[A-Za-z0-9_$.-]{1,128}$/', $dbName)) {
            throw new InvalidArgumentException('El nombre de la base de datos contiene caracteres no permitidos.');
        }

        $company = $get('company_name');
        $ruc = preg_replace('/\D+/', '', $get('company_ruc')) ?? '';
        $address = $get('company_address');
        if ($company === '') {
            throw new InvalidArgumentException('Ingresa el nombre de la empresa.');
        }
        if ($ruc !== '' && strlen($ruc) !== 11) {
            throw new InvalidArgumentException('El RUC debe tener 11 dígitos o dejarse vacío para configurarlo después.');
        }

        $adminName = $get('admin_name');
        $adminLogin = $get('admin_login', 'admin');
        $adminPassword = (string)($input['admin_password'] ?? '');
        if ($adminName === '' || !preg_match('/^[A-Za-z0-9._-]{3,20}$/', $adminLogin)) {
            throw new InvalidArgumentException('Revisa el nombre y usuario del administrador.');
        }
        if (strlen($adminPassword) < 8) {
            throw new InvalidArgumentException('La contraseña del administrador debe tener al menos 8 caracteres.');
        }

        $turnstileSite = $get('turnstile_site_key', (string)($defaults['turnstile_site_key'] ?? ''));
        $turnstileSecret = trim((string)($input['turnstile_secret_key'] ?? ''));
        if ($turnstileSecret === '') {
            $turnstileSecret = trim((string)($defaults['turnstile_secret_key'] ?? ''));
        }
        if ($turnstileSite === '' || $turnstileSecret === '') {
            throw new InvalidArgumentException('Cloudflare Turnstile requiere Site Key y Secret Key.');
        }

        $controlEnabled = isset($input['control_enabled']) ? 1 : 0;
        $controlUrl = $get('control_url', (string)($defaults['control_url'] ?? 'https://admin.tiquepos.com'));
        $controlClientKey = $get('control_client_key', (string)($defaults['control_client_key'] ?? ''));
        $controlClientSecret = trim((string)($input['control_client_secret'] ?? ''));
        if ($controlClientSecret === '') {
            $controlClientSecret = trim((string)($defaults['control_client_secret'] ?? ''));
        }
        if ($controlEnabled && ($controlUrl === '' || $controlClientKey === '' || $controlClientSecret === '')) {
            throw new InvalidArgumentException('Faltan las credenciales de TiquePOS Control.');
        }

        return [
            'domain' => $domain,
            'db_host' => $dbHost,
            'db_port' => $dbPort,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_pass' => $dbPass,
            'company_name' => $company,
            'company_ruc' => $ruc,
            'company_address' => $address,
            'company_phone' => preg_replace('/\D+/', '', $get('company_phone')) ?? '',
            'company_email' => $get('company_email'),
            'company_city' => $get('company_city'),
            'company_country' => $get('company_country', 'Perú'),
            'admin_name' => $adminName,
            'admin_document' => preg_replace('/\D+/', '', $get('admin_document')) ?? '',
            'admin_email' => $get('admin_email'),
            'admin_login' => $adminLogin,
            'admin_password' => $adminPassword,
            'turnstile_site_key' => $turnstileSite,
            'turnstile_secret_key' => $turnstileSecret,
            'control_enabled' => $controlEnabled,
            'control_url' => rtrim($controlUrl, '/'),
            'control_client_key' => $controlClientKey,
            'control_client_secret' => $controlClientSecret,
        ];
    }

    private function connect(array $data): PDO
    {
        try {
            $pdo = new PDO(
                'mysql:host=' . $data['db_host'] . ';port=' . $data['db_port'] . ';dbname=' . $data['db_name'] . ';charset=utf8mb4',
                $data['db_user'],
                $data['db_pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $pdo->exec("SET time_zone = '-05:00'");
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('No se pudo conectar a la base de datos. Verifica host, nombre, usuario y contraseña.');
        }
    }

    private function importSchema(PDO $pdo): void
    {
        $sql = file_get_contents($this->schemaPath);
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('La plantilla database/base.sql está vacía o no se puede leer.');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($this->splitSql($sql) as $statement) {
                $statement = trim($statement);
                if ($statement === '') {
                    continue;
                }
                $pdo->exec($statement);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function splitSql(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $state = 'normal';
        $escaped = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $ch = $sql[$i];
            $buffer .= $ch;

            if ($state === 'single') {
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === "'") {
                    $state = 'normal';
                }
                continue;
            }

            if ($state === 'double') {
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === '"') {
                    $state = 'normal';
                }
                continue;
            }

            if ($state === 'backtick') {
                if ($ch === '`') {
                    $state = 'normal';
                }
                continue;
            }

            if ($ch === "'") {
                $state = 'single';
            } elseif ($ch === '"') {
                $state = 'double';
            } elseif ($ch === '`') {
                $state = 'backtick';
            } elseif ($ch === ';') {
                $statements[] = $buffer;
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    private function createInstallationData(PDO $pdo, array $data): void
    {
        $pdo->beginTransaction();
        try {
            $phone = $data['company_phone'] !== '' ? (int)$data['company_phone'] : 0;
            $visibleFields = json_encode([
                'tipo_comprobante' => 1,
                'cliente' => 1,
                'direccion' => 1,
                'tipo_pago' => 1,
                'forma_pago' => 1,
                'celular' => 1,
                'fecha_emision' => 1,
                'tipo_operacion_sunat' => 0,
                'descuento' => 1,
                'envio_comprobante' => 1,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $stmt = $pdo->prepare(
                'INSERT INTO datos_negocio
                (nombre,ndocumento,documento,direccion,telefono,email,pais,ciudad,nombre_impuesto,monto_impuesto,moneda,simbolo,condicion,
                 apisunat_production,venta_tipo_comprobante_predeterminado,venta_tipo_pago_predeterminado,venta_idforma_pago_predeterminada,
                 venta_modo_envio_predeterminado,logo,tipo_operacion_sunat_predeterminado,codigo_afectacion_igv_predeterminado,
                 porcentaje_igv_predeterminado,unidad_medida_sunat_predeterminada,permitir_cambio_afectacion_venta,precios_incluyen_impuesto,
                 venta_campos_visibles)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,0,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $data['company_name'], 'RUC', $data['company_ruc'] !== '' ? $data['company_ruc'] : null,
                $data['company_address'], $phone, $data['company_email'] !== '' ? $data['company_email'] : null,
                $data['company_country'], $data['company_city'], 'IGV', 18.00, 'SOLES', 'S/',
                'Boleta Electrónica', '1', 1, 'manual', 'default_logo.png', '0101', '10', 18.00, 'NIU', 1, 1, $visibleFields,
            ]);

            $stmt = $pdo->prepare(
                'INSERT INTO sucursal (codigo,nombre,direccion,codigo_establecimiento_sunat,principal,activo,hereda_configuracion_tributaria)
                 VALUES (?,?,?,?,1,1,1)'
            );
            $stmt->execute(['SUC001', $data['company_name'], $data['company_address'], '0000']);
            $branchId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO almacen (idsucursal,nombre,ubicacion,descripcion,estado) VALUES (?,?,?,?,1)');
            $stmt->execute([$branchId, 'Almacén principal', $data['company_address'], 'Almacén principal']);
            $warehouseId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO caja_fisica (idsucursal,codigo,nombre,descripcion,permite_efectivo,activo) VALUES (?,?,?,?,1,1)');
            $stmt->execute([$branchId, 'CAJ001', 'Caja principal', 'Caja física principal']);
            $cashboxId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO configuracion_caja (idsucursal,modo,modo_objetivo,idcaja_unica) VALUES (?,\'CAJA_UNICA\',\'CAJA_UNICA\',?)');
            $stmt->execute([$branchId, $cashboxId]);

            $adminDoc = $data['admin_document'] !== '' ? $data['admin_document'] : '00000000';
            $stmt = $pdo->prepare(
                'INSERT INTO usuario
                (nombre,tipo_documento,num_documento,direccion,telefono,email,cargo,login,clave,imagen,condicion)
                VALUES (?,\'DNI\',?,?,?,?,\'Administrador\',?,?,\'\',1)'
            );
            $stmt->execute([
                $data['admin_name'], $adminDoc, '', '', $data['admin_email'] !== '' ? $data['admin_email'] : null,
                $data['admin_login'], hash('sha256', $data['admin_password']),
            ]);
            $userId = (int)$pdo->lastInsertId();

            $permissionInsert = $pdo->prepare('INSERT INTO usuario_permiso (idusuario,idpermiso) VALUES (?,?)');
            foreach (range(1, 8) as $permissionId) {
                $permissionInsert->execute([$userId, $permissionId]);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO usuario_sucursal
                 (idusuario,idsucursal,puede_vender,puede_cobrar,puede_abrir_caja,puede_cerrar_caja,activo)
                 VALUES (?,?,1,1,1,1,1)'
            );
            $stmt->execute([$userId, $branchId]);

            $stmt = $pdo->prepare('INSERT INTO usuario_almacen (idusuario,idalmacen,activo) VALUES (?,?,1)');
            $stmt->execute([$userId, $warehouseId]);

            $stmt = $pdo->prepare(
                'INSERT INTO usuario_caja
                 (idusuario,idcaja,rol,puede_abrir,puede_cerrar,puede_operar,activo)
                 VALUES (?,?,\'ADMINISTRADOR\',1,1,1,1)'
            );
            $stmt->execute([$userId, $cashboxId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function writeLocalConfig(array $data): void
    {
        $cronToken = bin2hex(random_bytes(24));
        $content = "<?php\n\ndeclare(strict_types=1);\n\n"
            . "/* Generado por el instalador de TiquePOS. No se reemplaza durante actualizaciones. */\n"
            . "define('HOST', " . var_export($data['db_host'], true) . ");\n"
            . "define('DB_USER', " . var_export($data['db_user'], true) . ");\n"
            . "define('DB_PASS', " . var_export($data['db_pass'], true) . ");\n"
            . "define('DB_NAME', " . var_export($data['db_name'], true) . ");\n"
            . "define('PORT', " . (int)$data['db_port'] . ");\n"
            . "define('CHARSET', 'utf8mb4');\n"
            . "define('API_KEY', '');\n\n"
            . "define('APP_DOMAIN', " . var_export($data['domain'], true) . ");\n\n"
            . "define('TURNSTILE_SITE_KEY', " . var_export($data['turnstile_site_key'], true) . ");\n"
            . "define('TURNSTILE_SECRET_KEY', " . var_export($data['turnstile_secret_key'], true) . ");\n"
            . "define('TURNSTILE_ALLOWED_HOSTNAMES', " . var_export($data['domain'], true) . ");\n\n"
            . "define('CONTROL_ENABLED', " . ($data['control_enabled'] ? 'true' : 'false') . ");\n"
            . "define('CONTROL_URL', " . var_export($data['control_url'], true) . ");\n"
            . "define('CONTROL_DOMAIN', " . var_export($data['domain'], true) . ");\n"
            . "define('CONTROL_CLIENT_KEY', " . var_export($data['control_client_key'], true) . ");\n"
            . "define('CONTROL_CLIENT_SECRET', " . var_export($data['control_client_secret'], true) . ");\n"
            . "define('CONTROL_ENFORCE_LICENSE', true);\n"
            . "define('CONTROL_BOOTSTRAP_GRACE_HOURS', 24);\n"
            . "define('CONTROL_HTTP_TIMEOUT', 45);\n"
            . "define('CONTROL_CRON_TOKEN', " . var_export($cronToken, true) . ");\n";

        $path = $this->root . '/Config/local.php';
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo crear Config/local.php. Verifica permisos de escritura.');
        }
        @chmod($path, 0640);
    }

    private function createRuntimeDirectories(): void
    {
        $dirs = [
            '/storage/control',
            '/storage/apisunat/xml',
            '/storage/apisunat/cdr',
            '/storage/private',
            '/storage/private/qr',
            '/storage/images/products',
            '/storage/images/company',
            '/storage/images/users',
        ];
        foreach ($dirs as $relative) {
            $dir = $this->root . $relative;
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('No se pudo crear la carpeta ' . $relative);
            }
        }
    }

    private function writeLock(array $data): void
    {
        $payload = json_encode([
            'installed_at' => date(DATE_ATOM),
            'domain' => $data['domain'],
            'company' => $data['company_name'],
            'database' => $data['db_name'],
            'version' => is_file($this->root . '/VERSION') ? trim((string)file_get_contents($this->root . '/VERSION')) : '1.0.0',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if (file_put_contents($this->root . '/storage/installed.lock', $payload, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo crear el bloqueo de instalación.');
        }
    }


    private function scrubInstallerSecrets(): void
    {
        $path = $this->root . '/install/defaults.php';
        if (!is_file($path)) {
            return;
        }

        // Después de una instalación correcta, las credenciales ya viven en
        // Config/local.php (fuera de los releases). Eliminamos la copia de
        // bootstrap para reducir la superficie de exposición.
        if (@unlink($path)) {
            return;
        }

        $safe = <<<'PHP'
<?php

declare(strict_types=1);

return [
    'domain' => '',
    'turnstile_site_key' => '',
    'turnstile_secret_key' => '',
    'control_url' => 'https://admin.tiquepos.com',
    'control_client_key' => '',
    'control_client_secret' => '',
];
PHP;
        @file_put_contents($path, $safe, LOCK_EX);
        @chmod($path, 0640);
    }

    private function cleanupEmptyTargetDatabase(PDO $pdo): void
    {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $safe = str_replace('`', '``', (string)$table);
                $pdo->exec('DROP TABLE IF EXISTS `' . $safe . '`');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $ignored) {
            // La pantalla de instalación mostrará el error original.
        }
    }
}
