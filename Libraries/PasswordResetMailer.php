<?php

require_once __DIR__ . '/SmtpMailer.php';

class PasswordResetMailer
{
    private array $config;


    public function __construct(array $config = array())
    {
        $this->config = $config;
    }



    public function enviarOtp($destinatario, $nombre, $codigo, $minutosVigencia)
    {

        try {


            $destinatario = trim((string)$destinatario);
            $nombre = trim((string)$nombre);
            $codigo = trim((string)$codigo);
            $minutosVigencia = max(1, (int)$minutosVigencia);



            if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {

                throw new Exception(
                    'Correo destinatario inválido'
                );

            }




            $nombreSeguro = htmlspecialchars(
                $nombre !== '' ? $nombre : 'usuario',
                ENT_QUOTES,
                'UTF-8'
            );


            $codigoSeguro = htmlspecialchars(
                $codigo,
                ENT_QUOTES,
                'UTF-8'
            );




            $html = '
            <!doctype html>
            <html lang="es">
            <head>
            <meta charset="utf-8">
            </head>

            <body style="font-family:Arial;background:#f5f7f8;padding:30px">

            <div style="max-width:560px;margin:auto;background:white;padding:30px;border-radius:15px">

            <h2 style="color:#00a46a">
            TiquePOS
            </h2>


            <p>
            Hola '.$nombreSeguro.',
            </p>


            <p>
            Recibimos una solicitud para cambiar tu contraseña.
            </p>


            <div style="
            font-size:35px;
            font-weight:bold;
            letter-spacing:8px;
            text-align:center;
            background:#f0fdf7;
            padding:20px;
            border-radius:12px;
            color:#008e5b">

            '.$codigoSeguro.'

            </div>


            <p>
            Este código vence en '.$minutosVigencia.' minutos.
            </p>


            </div>

            </body>
            </html>';




            $smtp = new SmtpMailer(
                $this->config
            );



            return $smtp->send(
                $destinatario,
                'Código de recuperación TiquePOS',
                $html
            );



        } catch (Throwable $e) {


            error_log(
                '[PASSWORD RESET SMTP] '
                .$e->getMessage()
            );


            throw $e;

        }

    }

}