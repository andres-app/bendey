<?php

class SmtpMailer
{
    private array $config;


    public function __construct(array $config = [])
    {
        $this->config = $config;
    }



    private function sendCommand($fp, $cmd = null, $expect = [])
    {

        if ($cmd !== null) {
            fwrite($fp, $cmd . "\r\n");
        }


        $resp = '';

        while (($line = fgets($fp, 515)) !== false) {

            $resp .= $line;

            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }


        $code = (int)substr($resp, 0, 3);


        if (!empty($expect) && !in_array($code, $expect, true)) {

            throw new Exception(
                "Respuesta SMTP inesperada: " . $resp
            );
        }


        return $resp;
    }





    public function send($to, $subject, $html)
    {

        $host = $this->config['host'] ?? 'smtp.titan.email';

        $port = (int)($this->config['port'] ?? 587);

        $encryption = $this->config['encryption'] ?? 'tls';



        $scheme = '';

        if ($encryption === 'ssl') {
            $scheme = 'ssl://';
        }




        $fp = stream_socket_client(
            $scheme . $host . ':' . $port,
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT
        );



        if (!$fp) {

            throw new Exception(
                "No se pudo conectar SMTP: $errstr ($errno)"
            );

        }




        try {


            stream_set_timeout(
                $fp,
                20
            );



            /*
            |--------------------------------------------------------------------------
            | Saludo SMTP
            |--------------------------------------------------------------------------
            */

            $this->sendCommand(
                $fp,
                null,
                [220]
            );



            /*
            |--------------------------------------------------------------------------
            | EHLO inicial
            |--------------------------------------------------------------------------
            */

            $this->sendCommand(
                $fp,
                'EHLO localhost',
                [250]
            );




            /*
            |--------------------------------------------------------------------------
            | STARTTLS
            |--------------------------------------------------------------------------
            */

            if ($encryption === 'tls') {


                $this->sendCommand(
                    $fp,
                    'STARTTLS',
                    [220]
                );



                $crypto = stream_socket_enable_crypto(
                    $fp,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                );



                if ($crypto !== true) {

                    throw new Exception(
                        'No se pudo activar TLS'
                    );

                }



                // Nuevo EHLO después de TLS

                $this->sendCommand(
                    $fp,
                    'EHLO localhost',
                    [250]
                );

            }





            /*
            |--------------------------------------------------------------------------
            | DEBUG TEMPORAL
            |--------------------------------------------------------------------------
            */

            file_put_contents(
                __DIR__ . '/smtp_debug.txt',
                "USUARIO: "
                . ($this->config['username'] ?? 'VACIO')
                . PHP_EOL
                .
                "LONGITUD CLAVE: "
                . strlen((string)($this->config['password'] ?? ''))
                . PHP_EOL
            );






            /*
            |--------------------------------------------------------------------------
            | AUTH LOGIN
            |--------------------------------------------------------------------------
            */

            $this->sendCommand(
                $fp,
                'AUTH LOGIN',
                [334]
            );



            $this->sendCommand(
                $fp,
                base64_encode(
                    $this->config['username']
                ),
                [334]
            );



            $this->sendCommand(
                $fp,
                base64_encode(
                    $this->config['password']
                ),
                [235]
            );







            /*
            |--------------------------------------------------------------------------
            | MAIL FROM
            |--------------------------------------------------------------------------
            */

            $this->sendCommand(
                $fp,
                'MAIL FROM:<'.$this->config['from_email'].'>',
                [250]
            );





            /*
            |--------------------------------------------------------------------------
            | RCPT TO
            |--------------------------------------------------------------------------
            */

            $this->sendCommand(
                $fp,
                'RCPT TO:<'.$to.'>',
                [250,251]
            );







            /*
            |--------------------------------------------------------------------------
            | DATA
            |--------------------------------------------------------------------------
            */

            $this->sendCommand(
                $fp,
                'DATA',
                [354]
            );



            $message =
                'From: "'
                .($this->config['from_name'] ?? 'TiquePOS')
                .'" <'
                .$this->config['from_email']
                .">\r\n";


            $message .=
                'To: '
                .$to
                ."\r\n";


            $message .=
                'Subject: '
                .$subject
                ."\r\n";


            $message .=
                "MIME-Version: 1.0\r\n";


            $message .=
                "Content-Type: text/html; charset=UTF-8\r\n";


            $message .= "\r\n";


            $message .= $html;




            fwrite(
                $fp,
                $message . "\r\n.\r\n"
            );



            $this->sendCommand(
                $fp,
                null,
                [250]
            );



            $this->sendCommand(
                $fp,
                'QUIT',
                [221]
            );



            fclose($fp);


            return true;



        } catch (Throwable $e) {


            fclose($fp);



            throw new Exception(
                '[SMTP ERROR] ' . $e->getMessage()
            );

        }

    }

}