<?php

declare(strict_types=1);

/**
 * Cliente SMTP liviano sin dependencias externas.
 *
 * Soporta AUTH LOGIN, STARTTLS/SMTPS, HTML UTF-8, Reply-To y adjuntos.
 * Mantiene compatibilidad con las llamadas antiguas send($to,$subject,$html).
 */
final class SmtpMailer
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    private static function limpiarCabecera(string $valor): string
    {
        return trim(str_replace(["\r", "\n"], '', $valor));
    }

    private static function validarEmail(string $email, string $campo): string
    {
        $email = self::limpiarCabecera($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException($campo . ' no es un correo electrónico válido.');
        }
        return $email;
    }

    private static function codificarCabecera(string $valor): string
    {
        $valor = self::limpiarCabecera($valor);
        if ($valor === '') {
            return '';
        }
        return '=?UTF-8?B?' . base64_encode($valor) . '?=';
    }

    /** @return array{code:int,response:string} */
    private function comando($socket, ?string $comando, array $esperados): array
    {
        if ($comando !== null) {
            $payload = $comando . "\r\n";
            $escrito = 0;
            $longitud = strlen($payload);
            while ($escrito < $longitud) {
                $n = fwrite($socket, substr($payload, $escrito));
                if ($n === false || $n === 0) {
                    throw new RuntimeException('La conexión SMTP se cerró al enviar un comando.');
                }
                $escrito += $n;
            }
        }

        $respuesta = '';
        $codigo = 0;
        while (($linea = fgets($socket, 8192)) !== false) {
            $respuesta .= $linea;
            if (preg_match('/^(\d{3})([ -])/', $linea, $m)) {
                $codigo = (int)$m[1];
                if ($m[2] === ' ') {
                    break;
                }
            }
        }

        $meta = stream_get_meta_data($socket);
        if (!empty($meta['timed_out'])) {
            throw new RuntimeException('El servidor SMTP agotó el tiempo de espera.');
        }

        if ($respuesta === '') {
            throw new RuntimeException('El servidor SMTP no respondió.');
        }

        if ($esperados !== [] && !in_array($codigo, $esperados, true)) {
            $detalle = trim(preg_replace('/\s+/', ' ', $respuesta) ?? $respuesta);
            throw new RuntimeException('Respuesta SMTP inesperada (' . $codigo . '): ' . $detalle);
        }

        return ['code' => $codigo, 'response' => $respuesta];
    }

    private function escribirDatos($socket, string $mensaje): void
    {
        // RFC 5321: líneas que empiezan con punto deben duplicarlo.
        $mensaje = preg_replace('/(?m)^\./', '..', $mensaje) ?? $mensaje;
        $payload = rtrim($mensaje, "\r\n") . "\r\n.\r\n";
        $escrito = 0;
        $longitud = strlen($payload);

        while ($escrito < $longitud) {
            $n = fwrite($socket, substr($payload, $escrito));
            if ($n === false || $n === 0) {
                throw new RuntimeException('No se pudo completar el envío del mensaje SMTP.');
            }
            $escrito += $n;
        }
    }

    private function construirMensaje(
        string $to,
        string $subject,
        string $html,
        array $attachments,
        ?string $text
    ): string {
        $fromEmail = self::validarEmail(
            (string)($this->config['from_email'] ?? $this->config['username'] ?? ''),
            'El remitente SMTP'
        );
        $fromName = self::limpiarCabecera((string)($this->config['from_name'] ?? 'TiquePOS'));
        $replyTo = trim((string)($this->config['reply_to'] ?? ''));
        if ($replyTo !== '') {
            $replyTo = self::validarEmail($replyTo, 'Reply-To');
        }

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . preg_replace('/[^A-Za-z0-9.-]/', '', (string)($this->config['ehlo'] ?? 'tiquepos.local')) . '>',
            'From: ' . ($fromName !== '' ? self::codificarCabecera($fromName) . ' ' : '') . '<' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::codificarCabecera($subject),
            'MIME-Version: 1.0',
        ];

        if ($replyTo !== '') {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }

        $text = $text !== null && trim($text) !== ''
            ? trim($text)
            : trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($attachments === []) {
            $boundary = 'alt_' . bin2hex(random_bytes(12));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($text)) . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($html)) . "\r\n";
            $body .= "--{$boundary}--\r\n";
            return implode("\r\n", $headers) . "\r\n\r\n" . $body;
        }

        $mixed = 'mixed_' . bin2hex(random_bytes(12));
        $alt = 'alt_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixed . '"';

        $body = "--{$mixed}\r\n";
        $body .= 'Content-Type: multipart/alternative; boundary="' . $alt . "\"\r\n\r\n";
        $body .= "--{$alt}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($text)) . "\r\n";
        $body .= "--{$alt}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n";
        $body .= "--{$alt}--\r\n";

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }
            $content = null;
            if (array_key_exists('content', $attachment)) {
                $content = (string)$attachment['content'];
            } elseif (!empty($attachment['path']) && is_file((string)$attachment['path'])) {
                $content = file_get_contents((string)$attachment['path']);
                if ($content === false) {
                    $content = null;
                }
            }
            if ($content === null || $content === '') {
                continue;
            }

            $filename = basename((string)($attachment['filename'] ?? 'archivo.bin'));
            $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'archivo.bin';
            $mime = self::limpiarCabecera((string)($attachment['mime'] ?? 'application/octet-stream'));
            if (!preg_match('#^[A-Za-z0-9.+-]+/[A-Za-z0-9.+-]+$#', $mime)) {
                $mime = 'application/octet-stream';
            }

            $body .= "--{$mixed}\r\n";
            $body .= 'Content-Type: ' . $mime . '; name="' . $filename . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $filename . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode($content)) . "\r\n";
        }

        $body .= "--{$mixed}--\r\n";
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /**
     * @param array<int,array{path?:string,content?:string,filename?:string,mime?:string}> $attachments
     */
    public function send($to, $subject, $html, array $attachments = [], ?string $text = null): bool
    {
        $to = self::validarEmail((string)$to, 'El destinatario');
        $subject = self::limpiarCabecera((string)$subject);
        $html = (string)$html;

        $host = trim((string)($this->config['host'] ?? ''));
        if ($host === '' || preg_match('/[\r\n\s]/', $host)) {
            throw new RuntimeException('El host SMTP no es válido.');
        }

        $port = (int)($this->config['port'] ?? 587);
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('El puerto SMTP no es válido.');
        }

        $encryption = strtolower(trim((string)($this->config['encryption'] ?? 'tls')));
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new RuntimeException('La seguridad SMTP no es válida.');
        }

        $username = trim((string)($this->config['username'] ?? ''));
        $password = (string)($this->config['password'] ?? '');
        $fromEmail = self::validarEmail(
            (string)($this->config['from_email'] ?? $username),
            'El remitente SMTP'
        );
        $ehlo = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($this->config['ehlo'] ?? ($_SERVER['SERVER_NAME'] ?? 'tiquepos.local')));
        if ($ehlo === '') {
            $ehlo = 'tiquepos.local';
        }

        $timeout = max(5, min(60, (int)($this->config['timeout'] ?? 20)));
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $scheme = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $scheme . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            throw new RuntimeException('No se pudo conectar al SMTP: ' . ($errstr !== '' ? $errstr : 'error ' . $errno));
        }

        try {
            stream_set_timeout($socket, $timeout);
            $this->comando($socket, null, [220]);
            $this->comando($socket, 'EHLO ' . $ehlo, [250]);

            if ($encryption === 'tls') {
                $this->comando($socket, 'STARTTLS', [220]);
                $metodo = defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
                    ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                    : STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $metodo |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                if (@stream_socket_enable_crypto($socket, true, $metodo) !== true) {
                    throw new RuntimeException('No se pudo negociar una conexión TLS segura con el servidor SMTP.');
                }
                $this->comando($socket, 'EHLO ' . $ehlo, [250]);
            }

            if ($username !== '') {
                if ($password === '') {
                    throw new RuntimeException('La contraseña SMTP no está configurada.');
                }
                $this->comando($socket, 'AUTH LOGIN', [334]);
                $this->comando($socket, base64_encode($username), [334]);
                $this->comando($socket, base64_encode($password), [235]);
            }

            $this->comando($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->comando($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->comando($socket, 'DATA', [354]);
            $this->escribirDatos($socket, $this->construirMensaje($to, $subject, $html, $attachments, $text));
            $this->comando($socket, null, [250]);
            $this->comando($socket, 'QUIT', [221]);
            fclose($socket);
            return true;
        } catch (Throwable $e) {
            if (is_resource($socket)) {
                @fwrite($socket, "QUIT\r\n");
                @fclose($socket);
            }
            throw new RuntimeException('[SMTP] ' . $e->getMessage(), 0, $e);
        }
    }
}
