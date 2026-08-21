<?php

declare(strict_types=1);

class TiquePOSLicenseGuard
{
    private static function baseDir(): string
    {
        return __DIR__ . '/../storage/control';
    }

    private static function publicKey(): string
    {
        $path = __DIR__ . '/../Config/control_public.pem';
        return is_file($path) ? (string)file_get_contents($path) : '';
    }

    private static function readCache(): ?array
    {
        $path = self::baseDir() . '/license.json';
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        $box = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($box)) {
            return null;
        }
        $licenseJson = (string)($box['license_json'] ?? '');
        $signature = (string)($box['signature'] ?? '');
        if ($licenseJson === '' || $signature === '') {
            return null;
        }
        $pub = self::publicKey();
        $sigRaw = base64_decode($signature, true);
        if ($pub === '' || $sigRaw === false || openssl_verify($licenseJson, $sigRaw, $pub, OPENSSL_ALGO_SHA256) !== 1) {
            return null;
        }
        $payload = json_decode($licenseJson, true);
        if (!is_array($payload)) {
            return null;
        }
        if (defined('CONTROL_DOMAIN') && CONTROL_DOMAIN !== '' && strcasecmp((string)($payload['domain'] ?? ''), (string)CONTROL_DOMAIN) !== 0) {
            return null;
        }
        if (defined('CONTROL_CLIENT_KEY') && strpos((string)CONTROL_CLIENT_KEY, 'PEGAR_') !== 0 && !hash_equals((string)CONTROL_CLIENT_KEY, (string)($payload['client_key'] ?? ''))) {
            return null;
        }
        return $payload;
    }

    private static function bootstrapAllowed(): bool
    {
        $dir = self::baseDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/installed_at';
        if (!is_file($path)) {
            @file_put_contents($path, (string)time(), LOCK_EX);
        }
        $installed = (int)trim((string)@file_get_contents($path));
        if ($installed <= 0) {
            return false;
        }
        $hours = defined('CONTROL_BOOTSTRAP_GRACE_HOURS') ? max(0, (int)CONTROL_BOOTSTRAP_GRACE_HOURS) : 24;
        return time() <= ($installed + $hours * 3600);
    }

    public static function enforce(): void
    {
        if (!defined('CONTROL_ENABLED') || !CONTROL_ENABLED) {
            return;
        }

        // Mientras no se hayan pegado credenciales del panel, permite completar el despliegue inicial.
        if (!defined('CONTROL_CLIENT_KEY') || !defined('CONTROL_CLIENT_SECRET') || strpos((string)CONTROL_CLIENT_KEY, 'PEGAR_') === 0 || strpos((string)CONTROL_CLIENT_SECRET, 'PEGAR_') === 0) {
            return;
        }

        $payload = self::readCache();
        if (!$payload) {
            if (self::bootstrapAllowed()) {
                return;
            }
            self::deny('UNVERIFIED', 'No fue posible validar la licencia de esta instalación.');
        }

        $status = strtoupper((string)($payload['status'] ?? 'UNVERIFIED'));
        if (in_array($status, array('SUSPENDED','BLOCKED','EXPIRED'), true)) {
            self::deny($status, $status === 'SUSPENDED' ? 'El servicio se encuentra temporalmente suspendido.' : ($status === 'EXPIRED' ? 'La licencia de esta instalación ha vencido.' : 'El acceso a esta instalación está bloqueado.'));
        }

        if ($status !== 'ACTIVE') {
            self::deny($status, 'La licencia de esta instalación no es válida.');
        }

        $cacheUntil = strtotime((string)($payload['cache_until'] ?? '')) ?: 0;
        $graceUntil = strtotime((string)($payload['grace_until'] ?? '')) ?: 0;
        if (time() <= $cacheUntil || time() <= $graceUntil) {
            return;
        }
        self::deny('OFFLINE_EXPIRED', 'No se ha podido renovar la licencia dentro del periodo de gracia.');
    }

    private static function deny(string $code, string $message): void
    {
        http_response_code(423);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $status = strtoupper($code);
        $copies = array(
            'SUSPENDED' => array(
                'eyebrow' => 'Estado de servicio',
                'title' => 'Servicio temporalmente suspendido',
                'message' => 'El acceso a TiquePOS para esta empresa está temporalmente suspendido. Tus datos permanecen seguros y no se ha eliminado ninguna información.',
                'help' => 'Para reactivar el servicio, comunícate con el administrador de tu cuenta.',
                'badge' => 'SUSPENDIDO',
            ),
            'BLOCKED' => array(
                'eyebrow' => 'Estado de servicio',
                'title' => 'Acceso temporalmente restringido',
                'message' => 'Esta instalación de TiquePOS se encuentra temporalmente restringida.',
                'help' => 'Comunícate con el administrador de tu cuenta para revisar el estado del servicio.',
                'badge' => 'RESTRINGIDO',
            ),
            'EXPIRED' => array(
                'eyebrow' => 'Estado de servicio',
                'title' => 'Periodo de servicio finalizado',
                'message' => 'El periodo habilitado para esta instalación ha finalizado. Tus datos permanecen almacenados de forma segura.',
                'help' => 'Renueva o reactiva el servicio para continuar utilizando TiquePOS.',
                'badge' => 'VENCIDO',
            ),
            'OFFLINE_EXPIRED' => array(
                'eyebrow' => 'Verificación de servicio',
                'title' => 'No pudimos validar el servicio',
                'message' => 'TiquePOS no ha podido renovar la validación de esta instalación dentro del periodo permitido.',
                'help' => 'Intenta nuevamente en unos minutos. Si continúa, comunícate con el administrador de tu cuenta.',
                'badge' => 'POR VERIFICAR',
            ),
            'UNVERIFIED' => array(
                'eyebrow' => 'Verificación de servicio',
                'title' => 'Estamos verificando tu servicio',
                'message' => $message,
                'help' => 'Intenta nuevamente en unos instantes.',
                'badge' => 'POR VERIFICAR',
            ),
        );
        $ui = $copies[$status] ?? $copies['UNVERIFIED'];

        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $uri = strtolower((string)($_SERVER['REQUEST_URI'] ?? ''));
        if (strpos($accept, 'application/json') !== false || strpos($uri, '/controllers/') !== false) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'status' => 'error',
                'license_status' => $status,
                'error_code' => 'LICENSE_' . $status,
                'title' => $ui['title'],
                'message' => $ui['message'],
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#00a46a"><title>TiquePOS · '.$e($ui['title']).'</title><style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:22px;background:#f4f7f6;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#17211d}.card{width:min(100%,510px);background:#fff;border:1px solid #e1e9e5;border-radius:26px;box-shadow:0 26px 80px rgba(15,23,42,.13);padding:34px 34px 30px;text-align:center}.brand{display:inline-flex;font-size:27px;font-weight:850;letter-spacing:-.04em}.brand span{color:#00a46a}.icon{width:66px;height:66px;margin:24px auto 18px;border-radius:20px;display:grid;place-items:center;background:#fff7e8;color:#d58b13;border:1px solid #f6dfb5}.icon svg{width:32px;height:32px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.eyebrow{font-size:12px;font-weight:760;letter-spacing:.08em;text-transform:uppercase;color:#718079}h1{margin:8px 0 10px;font-size:23px;line-height:1.2;letter-spacing:-.025em}.msg{margin:0 auto;color:#66736d;line-height:1.62;font-size:14px;max-width:410px}.help{margin:16px auto 0;padding:13px 15px;background:#f6f9f8;border-radius:14px;color:#44534c;font-size:13px;line-height:1.5}.badge{display:inline-flex;margin-top:18px;padding:7px 10px;border-radius:999px;background:#fff7e8;color:#ad6c08;font-size:10px;font-weight:850;letter-spacing:.08em}.actions{margin-top:22px}.btn{appearance:none;border:0;border-radius:13px;background:#00a46a;color:#fff;padding:12px 20px;font:inherit;font-weight:750;cursor:pointer;box-shadow:0 9px 24px rgba(0,164,106,.18)}.btn:hover{background:#008d5b}.note{margin-top:16px;color:#95a19b;font-size:11px}@media(max-width:560px){body{padding:14px}.card{padding:28px 20px 25px;border-radius:22px}h1{font-size:21px}}</style></head><body><main class="card"><div class="brand">Tique<span>POS</span></div><div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9"/><path d="M12 7v5l3 2"/><path d="M18 3v5M15.5 5.5h5"/></svg></div><div class="eyebrow">'.$e($ui['eyebrow']).'</div><h1>'.$e($ui['title']).'</h1><p class="msg">'.$e($ui['message']).'</p><div class="help">'.$e($ui['help']).'</div><span class="badge">'.$e($ui['badge']).'</span><div class="actions"><button class="btn" type="button" onclick="location.reload()">Reintentar</button></div><div class="note">La información de tu empresa permanece intacta.</div></main></body></html>';
        exit;
    }
}
