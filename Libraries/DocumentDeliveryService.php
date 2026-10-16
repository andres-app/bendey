<?php

declare(strict_types=1);

require_once __DIR__ . '/../Config/Conexion.php';
require_once __DIR__ . '/../Models/Company.php';
require_once __DIR__ . '/SmtpMailer.php';

/**
 * Envío unificado de documentos por correo y WhatsApp.
 *
 * El PDF se comparte mediante un enlace firmado. El adjunto es opcional y,
 * si falla su obtención, el correo se envía de todos modos con el enlace.
 */
final class DocumentDeliveryService
{
    private PDO $db;
    private Company $company;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: Conexion::conectar();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->company = new Company();
    }

    private static function normalizarTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));
        $mapa = [
            'venta' => 'venta',
            'cotizacion' => 'venta',
            'cotización' => 'venta',
            'guia' => 'guia',
            'guia_remision' => 'guia',
            'guía' => 'guia',
            'nota' => 'nota_credito',
            'nota_credito' => 'nota_credito',
        ];
        if (!isset($mapa[$tipo])) {
            throw new InvalidArgumentException('Tipo de documento no permitido.');
        }
        return $mapa[$tipo];
    }

    private static function valorSeguro(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private static function claveFirma(): string
    {
        if (defined('DOCUMENT_LINK_SECRET') && trim((string)DOCUMENT_LINK_SECRET) !== '') {
            return (string)DOCUMENT_LINK_SECRET;
        }

        // Fallback estable por instalación sin guardar otra clave en BD.
        $material = (defined('DB_PASS') ? (string)DB_PASS : '')
            . '|' . (defined('DB_NAME') ? (string)DB_NAME : '')
            . '|' . (defined('APP_DOMAIN') ? (string)APP_DOMAIN : '')
            . '|TiquePOS|document-link|v1';
        return hash('sha256', $material, true);
    }

    private static function baseUrl(): string
    {
        $domain = trim((string)(defined('APP_DOMAIN') ? APP_DOMAIN : ''));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = trim($domain, '/');

        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $root = rtrim(dirname(dirname($script)), '/.');
        if ($root === '') {
            $root = '';
        }

        $local = in_array($domain, ['', 'localhost', '127.0.0.1'], true);
        $scheme = $local
            ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
            : 'https';
        $host = $domain !== ''
            ? $domain
            : preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));

        return $scheme . '://' . $host . $root;
    }

    public static function firmar(string $tipo, int $id, int $expira): string
    {
        $tipo = self::normalizarTipo($tipo);
        if ($id <= 0 || $expira <= 0) {
            throw new InvalidArgumentException('Parámetros de firma inválidos.');
        }
        return hash_hmac('sha256', $tipo . '|' . $id . '|' . $expira, self::claveFirma());
    }

    public static function validarFirma(string $tipo, int $id, int $expira, string $firma): bool
    {
        try {
            $tipo = self::normalizarTipo($tipo);
        } catch (Throwable) {
            return false;
        }
        if ($id <= 0 || $expira < time() || !preg_match('/^[a-f0-9]{64}$/i', $firma)) {
            return false;
        }
        return hash_equals(self::firmar($tipo, $id, $expira), strtolower($firma));
    }

    public function pdfUrl(string $tipo, int $id, int $dias = 365): string
    {
        $tipo = self::normalizarTipo($tipo);
        $dias = max(1, min(1825, $dias));
        $expira = time() + ($dias * 86400);
        $firma = self::firmar($tipo, $id, $expira);
        return self::baseUrl()
            . '/Controllers/DocumentoPublico.php?tipo=' . rawurlencode($tipo)
            . '&id=' . $id
            . '&exp=' . $expira
            . '&sig=' . rawurlencode($firma);
    }


    public function autorizarAccesoUsuario(string $tipo, int $id, int $usuario): bool
    {
        $tipo = self::normalizarTipo($tipo);
        if ($id <= 0 || $usuario <= 0) {
            return false;
        }

        if ($tipo === 'guia') {
            return $this->one(
                "SELECT g.idguia FROM guia_remision g
                 INNER JOIN usuario_sucursal us ON us.idsucursal=g.idsucursal
                    AND us.idusuario=? AND us.activo=1
                 WHERE g.idguia=? LIMIT 1",
                [$usuario, $id]
            ) !== null;
        }

        if ($tipo === 'nota_credito') {
            return $this->one(
                "SELECT n.idnota_credito FROM nota_credito n
                 LEFT JOIN usuario_sucursal us ON us.idsucursal=n.idsucursal
                    AND us.idusuario=? AND us.activo=1
                 WHERE n.idnota_credito=?
                   AND (n.idsucursal IS NULL OR us.idusuario IS NOT NULL)
                 LIMIT 1",
                [$usuario, $id]
            ) !== null;
        }

        return $this->one(
            "SELECT v.idventa FROM venta v
             LEFT JOIN usuario_sucursal us ON us.idsucursal=v.idsucursal
                AND us.idusuario=? AND us.activo=1
             WHERE v.idventa=?
               AND (v.idsucursal IS NULL OR us.idusuario IS NOT NULL)
             LIMIT 1",
            [$usuario, $id]
        ) !== null;
    }

    public function obtenerDocumento(string $tipo, int $id): array
    {
        $tipo = self::normalizarTipo($tipo);
        if ($id <= 0) {
            throw new InvalidArgumentException('Documento inválido.');
        }

        if ($tipo === 'venta') {
            $row = $this->one(
                "SELECT
                    v.idventa AS id,
                    v.idusuario,
                    v.tipo_comprobante,
                    v.serie_comprobante,
                    v.num_comprobante,
                    v.total_venta,
                    v.fecha_hora,
                    COALESCE(NULLIF(v.email_cliente,''), p.email, '') AS email,
                    COALESCE(NULLIF(v.celular_cliente,''), p.telefono, '') AS telefono,
                    p.nombre AS destinatario,
                    COALESCE(vs.pdf, '') AS pdf_oficial
                 FROM venta v
                 INNER JOIN persona p ON p.idpersona=v.idcliente
                 LEFT JOIN venta_sunat vs ON vs.idventa=v.idventa
                 WHERE v.idventa=?
                 LIMIT 1",
                [$id]
            );
            if (!$row) {
                throw new RuntimeException('No se encontró la venta o cotización.');
            }
            $row['tipo_interno'] = 'venta';
            $row['numero'] = trim((string)$row['serie_comprobante']) . '-' . trim((string)$row['num_comprobante']);
            $row['titulo'] = trim((string)$row['tipo_comprobante']) !== '' ? trim((string)$row['tipo_comprobante']) : 'Comprobante';
            return $row;
        }

        if ($tipo === 'guia') {
            $row = $this->one(
                "SELECT
                    g.idguia AS id,
                    g.idusuario,
                    g.destinatario_nombre AS destinatario,
                    COALESCE(g.destinatario_email,'') AS email,
                    COALESCE(g.destinatario_telefono,'') AS telefono,
                    COALESCE(g.serie,'') AS serie_comprobante,
                    COALESCE(g.correlativo,0) AS num_comprobante,
                    g.fecha_emision AS fecha_hora,
                    0 AS total_venta,
                    COALESCE(gs.pdf_url,'') AS pdf_oficial
                 FROM guia_remision g
                 LEFT JOIN guia_remision_sunat gs ON gs.idguia=g.idguia
                 WHERE g.idguia=?
                 LIMIT 1",
                [$id]
            );
            if (!$row) {
                throw new RuntimeException('No se encontró la guía de remisión.');
            }
            $row['tipo_interno'] = 'guia';
            $serie = trim((string)($row['serie_comprobante'] ?? ''));
            $correlativo = (int)($row['num_comprobante'] ?? 0);
            $row['numero'] = $serie !== '' && $correlativo > 0
                ? $serie . '-' . str_pad((string)$correlativo, 8, '0', STR_PAD_LEFT)
                : 'GRE #' . $id;
            $row['titulo'] = 'Guía de remisión electrónica';
            return $row;
        }

        $row = $this->one(
            "SELECT
                n.idnota_credito AS id,
                n.idusuario,
                n.cliente_nombre AS destinatario,
                COALESCE(NULLIF(n.cliente_email,''), p.email, '') AS email,
                COALESCE(NULLIF(v.celular_cliente,''), p.telefono, '') AS telefono,
                n.serie_comprobante,
                n.num_comprobante,
                n.total_nota AS total_venta,
                n.fecha_hora,
                COALESCE(ns.pdf,'') AS pdf_oficial
             FROM nota_credito n
             LEFT JOIN venta v ON v.idventa=n.idventa
             LEFT JOIN persona p ON p.idpersona=n.idcliente
             LEFT JOIN nota_credito_sunat ns ON ns.idnota_credito=n.idnota_credito
             WHERE n.idnota_credito=?
             LIMIT 1",
            [$id]
        );
        if (!$row) {
            throw new RuntimeException('No se encontró la nota de crédito.');
        }
        $row['tipo_interno'] = 'nota_credito';
        $row['numero'] = trim((string)$row['serie_comprobante']) . '-' . trim((string)$row['num_comprobante']);
        $row['titulo'] = 'Nota de crédito electrónica';
        return $row;
    }

    public function obtenerPdfOficial(string $tipo, int $id): string
    {
        $documento = $this->obtenerDocumento($tipo, $id);
        $url = trim((string)($documento['pdf_oficial'] ?? ''));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https') {
            return $url;
        }
        return '';
    }

    private static function normalizarTelefono(string $telefono): string
    {
        $digitos = preg_replace('/\D+/', '', trim($telefono)) ?? '';
        if (str_starts_with($digitos, '00')) {
            $digitos = substr($digitos, 2);
        }
        if (preg_match('/^9\d{8}$/', $digitos)) {
            return '51' . $digitos;
        }
        if (preg_match('/^519\d{8}$/', $digitos)) {
            return $digitos;
        }
        if (preg_match('/^\d{8,15}$/', $digitos)) {
            return $digitos;
        }
        return '';
    }

    public function whatsapp(string $tipo, int $id, ?string $telefono = null): array
    {
        $doc = $this->obtenerDocumento($tipo, $id);
        $telefonoFinal = self::normalizarTelefono(
            $telefono !== null && trim($telefono) !== ''
                ? $telefono
                : (string)($doc['telefono'] ?? '')
        );
        if ($telefonoFinal === '') {
            throw new InvalidArgumentException('Ingrese un celular válido para WhatsApp.');
        }

        $pdf = $this->pdfUrl((string)$doc['tipo_interno'], (int)$doc['id']);
        $empresa = $this->company->mostrarActivoSeguro() ?: [];
        $nombreEmpresa = trim((string)($empresa['nombre'] ?? 'TiquePOS'));
        $texto = 'Hola. ' . $nombreEmpresa . ' comparte tu ' . mb_strtolower((string)$doc['titulo'], 'UTF-8')
            . ' ' . (string)$doc['numero'] . ".\nPDF: " . $pdf;

        $this->registrarLog((string)$doc['tipo_interno'], (int)$doc['id'], 'WHATSAPP', $telefonoFinal, 'PREPARADO', '', $pdf, 0);

        return [
            'telefono' => $telefonoFinal,
            'pdf_url' => $pdf,
            'whatsapp_url' => 'https://wa.me/' . rawurlencode($telefonoFinal) . '?text=' . rawurlencode($texto),
            'mensaje' => $texto,
        ];
    }

    private function htmlCorreo(array $doc, string $pdfUrl): array
    {
        $empresa = $this->company->mostrarActivoSeguro() ?: [];
        $nombreEmpresa = trim((string)($empresa['nombre'] ?? 'TiquePOS'));
        $titulo = trim((string)$doc['titulo']);
        $numero = trim((string)$doc['numero']);
        $destinatario = trim((string)($doc['destinatario'] ?? 'Cliente'));
        $asunto = $titulo . ' ' . $numero . ' - ' . $nombreEmpresa;

        $totalHtml = '';
        if ((float)($doc['total_venta'] ?? 0) > 0) {
            $totalHtml = '<p style="margin:8px 0 0;color:#475467;font-size:14px">Total: <strong>S/ '
                . number_format((float)$doc['total_venta'], 2, '.', ',') . '</strong></p>';
        }

        $html = '<!doctype html><html lang="es"><head><meta charset="utf-8"></head>'
            . '<body style="margin:0;padding:28px;background:#f4f7f6;font-family:Arial,sans-serif;color:#1f2937">'
            . '<div style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #e6ece9;border-radius:16px;overflow:hidden">'
            . '<div style="padding:24px 28px;background:#0f9f6e;color:#fff"><div style="font-size:13px;opacity:.86">' . self::valorSeguro($nombreEmpresa) . '</div>'
            . '<h1 style="margin:6px 0 0;font-size:22px">' . self::valorSeguro($titulo) . '</h1></div>'
            . '<div style="padding:28px"><p style="margin-top:0">Hola ' . self::valorSeguro($destinatario !== '' ? $destinatario : 'cliente') . ',</p>'
            . '<p>Te compartimos tu documento <strong>' . self::valorSeguro($numero) . '</strong>.</p>'
            . $totalHtml
            . '<p style="margin:24px 0"><a href="' . self::valorSeguro($pdfUrl) . '" style="display:inline-block;background:#0f9f6e;color:#fff;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:10px">Ver / descargar PDF</a></p>'
            . '<p style="font-size:12px;color:#667085;word-break:break-all">Si el botón no abre, copia este enlace:<br>' . self::valorSeguro($pdfUrl) . '</p>'
            . '</div></div></body></html>';

        $text = 'Hola ' . ($destinatario !== '' ? $destinatario : 'cliente') . ",\n\n"
            . $nombreEmpresa . ' comparte tu ' . mb_strtolower($titulo, 'UTF-8') . ' ' . $numero . ".\n"
            . 'PDF: ' . $pdfUrl;

        return ['subject' => $asunto, 'html' => $html, 'text' => $text];
    }

    private function descargarPdf(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/pdf'],
            CURLOPT_USERAGENT => 'TiquePOS-DocumentDelivery/1.0',
        ]);
        $contenido = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tipo = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (!is_string($contenido) || $http !== 200 || strlen($contenido) > 8 * 1024 * 1024) {
            return null;
        }
        if (!str_starts_with($contenido, '%PDF') && stripos($tipo, 'application/pdf') === false) {
            return null;
        }
        return $contenido;
    }

    public function email(string $tipo, int $id, ?string $email = null, bool $automatico = false): array
    {
        $doc = $this->obtenerDocumento($tipo, $id);
        $destino = trim($email !== null && trim($email) !== '' ? $email : (string)($doc['email'] ?? ''));
        if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Ingrese un correo válido para enviar el documento.');
        }

        $smtp = $this->company->obtenerConfiguracionSmtp();
        if ((int)($smtp['enabled'] ?? 0) !== 1) {
            throw new RuntimeException('El SMTP de la empresa está deshabilitado. Actívelo en Configuración de empresa.');
        }

        // Protección básica contra abuso del SMTP compartido; cada instalación
        // debe complementar este límite con cuotas del proveedor central.
        if (!empty($smtp['managed'])) {
            $quota = $this->one(
                "SELECT COUNT(*) AS total FROM documento_envio_log
                 WHERE canal='EMAIL' AND estado='ENVIADO'
                   AND created_at >= CURRENT_DATE()"
            );
            if ((int)($quota['total'] ?? 0) >= 200) {
                throw new RuntimeException('Se alcanzó el límite diario de 200 correos gestionados. Contacte a soporte TiquePOS.');
            }
        }

        if ($automatico && (int)($smtp['auto_send'] ?? 0) !== 1) {
            return [
                'enviado' => false,
                'omitido' => true,
                'mensaje' => 'El envío automático por correo está desactivado.',
                'email' => $destino,
                'pdf_url' => $this->pdfUrl((string)$doc['tipo_interno'], (int)$doc['id']),
            ];
        }

        if ($automatico && $this->yaEnviado((string)$doc['tipo_interno'], (int)$doc['id'], $destino)) {
            return [
                'enviado' => true,
                'omitido' => true,
                'mensaje' => 'El documento ya fue enviado a este correo.',
                'email' => $destino,
                'pdf_url' => $this->pdfUrl((string)$doc['tipo_interno'], (int)$doc['id']),
            ];
        }

        $pdfUrl = $this->pdfUrl((string)$doc['tipo_interno'], (int)$doc['id']);
        $correo = $this->htmlCorreo($doc, $pdfUrl);
        $attachments = [];
        $adjunto = 0;
        $advertenciaAdjunto = '';

        if ((int)($smtp['attach_pdf'] ?? 0) === 1) {
            $pdf = $this->descargarPdf($pdfUrl);
            if ($pdf !== null) {
                $archivo = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$doc['titulo'] . '_' . (string)$doc['numero']) ?: 'documento';
                $attachments[] = [
                    'content' => $pdf,
                    'filename' => $archivo . '.pdf',
                    'mime' => 'application/pdf',
                ];
                $adjunto = 1;
            } else {
                $advertenciaAdjunto = 'No se pudo adjuntar el PDF; el correo se envió con el enlace firmado.';
            }
        }

        try {
            $mailer = new SmtpMailer($smtp);
            $mailer->send($destino, (string)$correo['subject'], (string)$correo['html'], $attachments, (string)$correo['text']);
            $this->registrarLog((string)$doc['tipo_interno'], (int)$doc['id'], 'EMAIL', $destino, 'ENVIADO', '', $pdfUrl, $adjunto, (string)$correo['subject']);
            return [
                'enviado' => true,
                'omitido' => false,
                'email' => $destino,
                'pdf_url' => $pdfUrl,
                'adjunto' => $adjunto === 1,
                'advertencia' => $advertenciaAdjunto,
                'mensaje' => $advertenciaAdjunto !== '' ? $advertenciaAdjunto : 'Correo enviado correctamente.',
            ];
        } catch (Throwable $e) {
            $this->registrarLog((string)$doc['tipo_interno'], (int)$doc['id'], 'EMAIL', $destino, 'ERROR', mb_substr($e->getMessage(), 0, 1000, 'UTF-8'), $pdfUrl, $adjunto, (string)$correo['subject']);
            throw $e;
        }
    }

    private function yaEnviado(string $tipo, int $id, string $destino): bool
    {
        try {
            $row = $this->one(
                "SELECT idenvio FROM documento_envio_log
                 WHERE tipo_documento=? AND id_documento=? AND canal='EMAIL'
                   AND destinatario=? AND estado='ENVIADO'
                 ORDER BY idenvio DESC LIMIT 1",
                [$tipo, $id, $destino]
            );
            return $row !== null;
        } catch (Throwable) {
            return false;
        }
    }

    private function registrarLog(
        string $tipo,
        int $id,
        string $canal,
        string $destinatario,
        string $estado,
        string $error,
        string $pdfUrl,
        int $adjunto,
        string $asunto = ''
    ): void {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO documento_envio_log
                    (tipo_documento,id_documento,canal,destinatario,estado,asunto,mensaje_error,pdf_url,adjunto,intentos,fecha_envio)
                 VALUES (?,?,?,?,?,?,?,?,?,1,CASE WHEN ?='ENVIADO' THEN NOW() ELSE NULL END)"
            );
            $stmt->execute([$tipo, $id, $canal, $destinatario, $estado, $asunto !== '' ? $asunto : null, $error !== '' ? $error : null, $pdfUrl, $adjunto, $estado]);
        } catch (Throwable $e) {
            error_log('[DOCUMENT DELIVERY LOG] ' . $e->getMessage());
        }
    }
}
