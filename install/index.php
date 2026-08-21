<?php

declare(strict_types=1);

session_start();

$root = dirname(__DIR__);
require_once __DIR__ . '/Installer.php';
$defaultsFile = __DIR__ . '/defaults.php';
$defaults = is_file($defaultsFile)
    ? require $defaultsFile
    : [
        'domain' => '',
        'turnstile_site_key' => '',
        'turnstile_secret_key' => '',
        'control_url' => 'https://admin.tiquepos.com',
        'control_client_key' => '',
        'control_client_secret' => '',
    ];
$installer = new TiquePOSInstaller($root);

$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
    || in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(24));
}

$errors = [];
$success = null;
$controlSync = null;

$currentHost = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?? '');
$defaultDomain = preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $currentHost)
    ? $currentHost
    : (string)($defaults['domain'] ?? '');

$form = [
    'domain' => $defaultDomain,
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => '',
    'db_user' => '',
    'company_name' => '',
    'company_ruc' => '',
    'company_address' => '',
    'company_phone' => '',
    'company_email' => '',
    'company_city' => 'Pucallpa',
    'company_country' => 'Perú',
    'admin_name' => '',
    'admin_document' => '',
    'admin_email' => '',
    'admin_login' => 'admin',
    'turnstile_site_key' => (string)($defaults['turnstile_site_key'] ?? ''),
    'control_url' => (string)($defaults['control_url'] ?? 'https://admin.tiquepos.com'),
    'control_client_key' => (string)($defaults['control_client_key'] ?? ''),
    'control_enabled' => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installer->isInstalled()) {
    foreach (array_keys($form) as $key) {
        if (array_key_exists($key, $_POST)) {
            $form[$key] = is_string($_POST[$key]) ? trim($_POST[$key]) : '';
        }
    }
    $form['control_enabled'] = isset($_POST['control_enabled']) ? '1' : '';

    if (!$isHttps) {
        $errors[] = 'La instalación debe realizarse mediante HTTPS para proteger las credenciales de la base de datos.';
    } elseif (!hash_equals((string)$_SESSION['install_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'La sesión de instalación venció. Recarga la página e intenta nuevamente.';
    } else {
        try {
            $success = $installer->install($_POST, $defaults);
            session_regenerate_id(true);

            // Primer heartbeat: no impide instalar si admin.tiquepos.com aún no está disponible.
            if (!defined('HOST')) {
                require $root . '/Config/local.php';
            }
            if (defined('CONTROL_ENABLED') && CONTROL_ENABLED) {
                try {
                    define('TIQUEPOS_CONTROL_BYPASS', true);
                    require_once $root . '/Control/Agent.php';
                    $agent = new TiquePOSControlAgent();
                    $controlSync = $agent->sync();
                } catch (Throwable $e) {
                    $controlSync = ['success' => false, 'message' => $e->getMessage()];
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$checks = $installer->environment();
$installed = $installer->isInstalled();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Instalación · TiquePOS</title>
    <style>
        :root{--brand:#00a46a;--ink:#12231d;--muted:#64756e;--line:#dce6e1;--bg:#f3f7f5;--danger:#c33b3b;--ok:#127a50}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--ink)}
        .shell{width:min(1120px,calc(100% - 28px));margin:32px auto 70px}.brand{font-size:28px;letter-spacing:-1px;margin-bottom:22px}.brand b{color:var(--brand)}
        .hero{background:#fff;border:1px solid var(--line);border-radius:22px;padding:28px;box-shadow:0 20px 55px rgba(17,44,34,.06);margin-bottom:18px}.hero h1{margin:0 0 8px;font-size:25px}.hero p{margin:0;color:var(--muted);line-height:1.55}
        .notice{border-radius:14px;padding:13px 15px;margin:14px 0;font-size:14px;line-height:1.45}.notice.bad{background:#fff0f0;color:#8e2525;border:1px solid #f4caca}.notice.good{background:#edfbf5;color:#176342;border:1px solid #c7ead9}.notice.info{background:#f1f6ff;color:#345275;border:1px solid #d8e5f7}
        .checks{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:20px}.check{border:1px solid var(--line);border-radius:14px;padding:13px;background:#fbfdfc}.check strong{display:block;font-size:13px}.check span{font-size:12px;color:var(--muted)}.dot{float:right;width:9px;height:9px;border-radius:99px;margin-top:5px;background:#c33b3b}.dot.ok{background:var(--brand)}
        form{display:grid;gap:18px}.card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:24px;box-shadow:0 12px 38px rgba(17,44,34,.04)}.card h2{font-size:17px;margin:0 0 5px}.card .sub{color:var(--muted);font-size:13px;margin:0 0 18px;line-height:1.45}
        .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}.field{grid-column:span 6}.field.third{grid-column:span 4}.field.full{grid-column:1/-1}.field.small{grid-column:span 3}label{display:block;font-size:12px;color:#44554e;margin:0 0 7px}
        input{width:100%;height:46px;border:1px solid #cfdcd6;border-radius:12px;padding:0 13px;font-size:16px;color:var(--ink);background:#fff;outline:none;font-weight:400}input:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(0,164,106,.10)}input[readonly]{background:#f5f8f7;color:#617069}
        .help{font-size:11px;color:#809088;margin-top:6px;line-height:1.4}.secret{display:flex;gap:8px}.secret input{flex:1}.toggle{width:auto;padding:0 13px;border:1px solid #cfdcd6;border-radius:12px;background:#fff;color:#405149;cursor:pointer}
        .switchline{display:flex;align-items:center;gap:10px;margin-bottom:16px}.switchline input{width:18px;height:18px;accent-color:var(--brand)}.switchline label{margin:0;font-size:14px;color:var(--ink)}
        .actions{display:flex;justify-content:flex-end;gap:10px}.submit{border:0;background:var(--brand);color:#fff;border-radius:13px;padding:13px 22px;font-size:15px;cursor:pointer;font-weight:500}.submit:disabled{opacity:.45;cursor:not-allowed}.back{display:inline-flex;text-decoration:none;color:var(--ink);border:1px solid var(--line);background:#fff;border-radius:12px;padding:11px 16px}
        .done{max-width:720px;margin:40px auto;background:#fff;border:1px solid var(--line);border-radius:22px;padding:30px;box-shadow:0 20px 55px rgba(17,44,34,.06)}.done h1{margin-top:0}.summary{display:grid;gap:8px;margin:18px 0}.summary div{display:flex;justify-content:space-between;gap:20px;padding:10px 0;border-bottom:1px solid #edf2ef;font-size:14px}.summary span{color:var(--muted)}code{background:#f0f5f3;border-radius:7px;padding:2px 6px;font-size:12px;word-break:break-all}
        @media(max-width:760px){.shell{margin-top:18px}.hero,.card{padding:18px;border-radius:16px}.grid{display:block}.field{margin-bottom:14px}.actions{justify-content:stretch}.submit{width:100%}}
    </style>
</head>
<body>
<?php if ($installed): ?>
    <main class="done">
        <div class="brand">Tique<b>POS</b></div>
        <h1>Instalación completada</h1>
        <p style="color:var(--muted);line-height:1.55">El instalador quedó bloqueado. La aplicación ya usa una base limpia y su configuración privada está en <code>Config/local.php</code>.</p>
        <?php if ($success): ?>
            <div class="summary">
                <div><span>Empresa</span><strong><?= h((string)$success['company']) ?></strong></div>
                <div><span>Dominio</span><strong><?= h((string)$success['domain']) ?></strong></div>
                <div><span>Base de datos</span><strong><?= h((string)$success['database']) ?></strong></div>
                <div><span>Usuario</span><strong><?= h((string)$success['login']) ?></strong></div>
            </div>
            <?php if (is_array($controlSync) && !empty($controlSync['success'])): ?>
                <div class="notice good">TiquePOS Control respondió correctamente y la licencia local quedó sincronizada.</div>
            <?php elseif (is_array($controlSync)): ?>
                <div class="notice info">La aplicación quedó instalada, pero el primer sincronizado con admin.tiquepos.com no respondió: <?= h((string)($controlSync['message'] ?? 'sin detalle')) ?>. Puedes sincronizarlo después.</div>
            <?php endif; ?>
            <div class="notice info">Cron recomendado: ejecuta <code><?= h((string)$success['cron_path']) ?></code> cada 2 minutos desde el panel del hosting.</div>
        <?php endif; ?>
        <a class="back" href="/">Ir a TiquePOS</a>
    </main>
<?php else: ?>
<main class="shell">
    <div class="brand">Tique<b>POS</b></div>
    <section class="hero">
        <h1>Nueva instalación</h1>
        <p>Este proceso crea una instalación independiente: base de datos limpia, empresa, sucursal, almacén, caja y administrador inicial. Solo se conservan los catálogos genéricos del sistema.</p>
        <?php if (!$isHttps): ?><div class="notice bad">Abre esta página mediante HTTPS antes de ingresar credenciales.</div><?php endif; ?>
        <?php foreach ($errors as $error): ?><div class="notice bad"><?= h((string)$error) ?></div><?php endforeach; ?>
        <div class="checks">
            <?php foreach ($checks as $check): ?>
                <div class="check"><i class="dot<?= !empty($check['ok']) ? ' ok' : '' ?>"></i><strong><?= h((string)$check['label']) ?></strong><span><?= h((string)$check['value']) ?></span></div>
            <?php endforeach; ?>
        </div>
    </section>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= h((string)$_SESSION['install_csrf']) ?>">

        <section class="card">
            <h2>1. Instalación y base de datos</h2>
            <p class="sub">La base debe existir en el hosting y estar completamente vacía. El instalador no borra ni reutiliza bases con tablas existentes.</p>
            <div class="grid">
                <div class="field full"><label>Dominio</label><input name="domain" value="<?= h($form['domain']) ?>" required></div>
                <div class="field"><label>Host MySQL</label><input name="db_host" value="<?= h($form['db_host']) ?>" required></div>
                <div class="field"><label>Nombre de la base de datos</label><input name="db_name" value="<?= h($form['db_name']) ?>" placeholder="ej. u123456_app" required></div>
                <div class="field"><label>Usuario de la base de datos</label><input name="db_user" value="<?= h($form['db_user']) ?>" required></div>
                <div class="field small"><label>Puerto</label><input name="db_port" inputmode="numeric" value="<?= h($form['db_port']) ?>" required></div>
                <div class="field"><label>Contraseña MySQL</label><div class="secret"><input id="db_pass" type="password" name="db_pass" required><button class="toggle" type="button" data-target="db_pass">Ver</button></div></div>
            </div>
        </section>

        <section class="card">
            <h2>2. Empresa</h2>
            <p class="sub">Estos datos crean la configuración inicial. Credenciales APISUNAT, logo y demás parámetros se completan luego desde Configuración.</p>
            <div class="grid">
                <div class="field"><label>Nombre / razón social</label><input name="company_name" value="<?= h($form['company_name']) ?>" required></div>
                <div class="field"><label>RUC</label><input name="company_ruc" inputmode="numeric" maxlength="11" value="<?= h($form['company_ruc']) ?>"></div>
                <div class="field full"><label>Dirección</label><input name="company_address" value="<?= h($form['company_address']) ?>"></div>
                <div class="field third"><label>Teléfono</label><input name="company_phone" inputmode="tel" value="<?= h($form['company_phone']) ?>"></div>
                <div class="field third"><label>Correo</label><input type="email" name="company_email" value="<?= h($form['company_email']) ?>"></div>
                <div class="field third"><label>Ciudad</label><input name="company_city" value="<?= h($form['company_city']) ?>"></div>
                <div class="field third"><label>País</label><input name="company_country" value="<?= h($form['company_country']) ?>"></div>
            </div>
        </section>

        <section class="card">
            <h2>3. Administrador inicial</h2>
            <p class="sub">Se crea un único administrador con acceso a todos los permisos, la sucursal principal, el almacén principal y la caja principal.</p>
            <div class="grid">
                <div class="field"><label>Nombre completo</label><input name="admin_name" value="<?= h($form['admin_name']) ?>" required></div>
                <div class="field"><label>DNI</label><input name="admin_document" inputmode="numeric" value="<?= h($form['admin_document']) ?>"></div>
                <div class="field"><label>Correo</label><input type="email" name="admin_email" value="<?= h($form['admin_email']) ?>"></div>
                <div class="field"><label>Usuario</label><input name="admin_login" value="<?= h($form['admin_login']) ?>" required></div>
                <div class="field"><label>Contraseña</label><div class="secret"><input id="admin_password" type="password" name="admin_password" minlength="8" required><button class="toggle" type="button" data-target="admin_password">Ver</button></div><div class="help">Mínimo 8 caracteres.</div></div>
            </div>
        </section>

        <section class="card">
            <h2>4. Cloudflare Turnstile</h2>
            <p class="sub">El login usa el widget existente y valida cada token en el servidor mediante Siteverify. La Secret Key nunca se envía al navegador durante el uso normal de la aplicación.</p>
            <div class="grid">
                <div class="field"><label>Site Key</label><input name="turnstile_site_key" value="<?= h($form['turnstile_site_key']) ?>" required></div>
                <div class="field"><label>Secret Key</label><div class="secret"><input id="turnstile_secret_key" type="password" name="turnstile_secret_key" placeholder="Secret Key de Turnstile"><button class="toggle" type="button" data-target="turnstile_secret_key">Ver</button></div><div class="help">Ingresa la Secret Key del widget Turnstile autorizado para este dominio.</div></div>
            </div>
        </section>

        <section class="card">
            <h2>5. TiquePOS Control</h2>
            <p class="sub">Vincula esta instalación con admin.tiquepos.com para licencia, heartbeat y despliegues masivos. Las credenciales de TiquePOS Control fueron generadas para este cliente desde admin.tiquepos.com.</p>
            <div class="switchline"><input id="control_enabled" type="checkbox" name="control_enabled" value="1" <?= $form['control_enabled'] === '1' ? 'checked' : '' ?>><label for="control_enabled">Administrar esta instalación desde admin.tiquepos.com</label></div>
            <div class="grid">
                <div class="field"><label>Servidor central</label><input name="control_url" value="<?= h($form['control_url']) ?>"></div>
                <div class="field"><label>Client Key</label><input name="control_client_key" value="<?= h($form['control_client_key']) ?>"></div>
                <div class="field"><label>Client Secret</label><div class="secret"><input id="control_client_secret" type="password" name="control_client_secret" placeholder="Se completa automáticamente desde el paquete"><button class="toggle" type="button" data-target="control_client_secret">Ver</button></div></div>
            </div>
        </section>

        <div class="actions"><button class="submit" type="submit" <?= (!$installer->canInstall() || !$isHttps) ? 'disabled' : '' ?>>Instalar TiquePOS</button></div>
    </form>
</main>
<script>
    document.querySelectorAll('[data-target]').forEach(function(button){
        button.addEventListener('click',function(){
            var input=document.getElementById(this.getAttribute('data-target'));
            if(!input)return;
            var show=input.type==='password';
            input.type=show?'text':'password';
            this.textContent=show?'Ocultar':'Ver';
        });
    });
</script>
<?php endif; ?>
</body>
</html>
