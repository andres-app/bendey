<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (
    !isset($_SESSION['nombre'])
    || (int)($_SESSION['ventas'] ?? 0) !== 1
) {
    http_response_code(403);
    exit('Acceso denegado.');
}

require_once __DIR__ . '/Models/ApiSunat.php';
require_once __DIR__ . '/Models/ApiSunatDocument.php';
require_once __DIR__ . '/Models/ApiSunatEmission.php';

$ventasPermitidas = [168, 169, 170, 171, 172];

$idventa = (int)($_GET['idventa'] ?? 0);

if (!in_array($idventa, $ventasPermitidas, true)) {
    exit(
        'Venta no permitida. Use uno de estos IDs: '
        . implode(', ', $ventasPermitidas)
    );
}

function e(string $texto): string
{
    return htmlspecialchars(
        $texto,
        ENT_QUOTES,
        'UTF-8'
    );
}

function jsonBonito(mixed $datos): string
{
    $json = json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRETTY_PRINT
        | JSON_PRESERVE_ZERO_FRACTION
    );

    return $json !== false ? $json : '{}';
}

if (empty($_SESSION['apisunat_recuperar_csrf'])) {
    $_SESSION['apisunat_recuperar_csrf'] =
        bin2hex(random_bytes(32));
}

$csrf = (string)$_SESSION['apisunat_recuperar_csrf'];

$error = '';
$resultado = null;
$comprobante = null;
$ultimoDocumento = null;
$puedeEnviar = false;
$confirmacionEsperada = '';

try {
    $generador = new ApiSunatDocument();

    $comprobante = $generador->construir(
        $idventa
    );

    $tipoSunat = (string)$comprobante['tipoSunat'];
    $serie = (string)$comprobante['serie'];
    $numero = (string)$comprobante['numero'];

    $confirmacionEsperada =
        'ENVIAR '
        . $serie
        . '-'
        . $numero;

    $apiSunat = new ApiSunat();

    $ultimoDocumento =
        $apiSunat->obtenerUltimoDocumento(
            $tipoSunat,
            $serie
        );

    if (
        ($ultimoDocumento['success'] ?? false)
        !== true
    ) {
        throw new RuntimeException(
            'No se pudo consultar el correlativo de APISUNAT: '
            . (
                $ultimoDocumento['message']
                ?? 'Sin detalle.'
            )
        );
    }

    if (
        ($ultimoDocumento['production'] ?? null)
        !== true
    ) {
        throw new RuntimeException(
            'Las credenciales no corresponden a producción.'
        );
    }

    $siguienteApi = str_pad(
        (string)(
            $ultimoDocumento['suggestedNumber']
            ?? ''
        ),
        8,
        '0',
        STR_PAD_LEFT
    );

    if ($siguienteApi !== $numero) {
        throw new RuntimeException(
            'No se puede enviar esta venta todavía. '
            . 'APISUNAT espera '
            . $serie
            . '-'
            . $siguienteApi
            . ', pero esta venta es '
            . $serie
            . '-'
            . $numero
            . '.'
        );
    }

    $puedeEnviar = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfRecibido = (string)(
            $_POST['csrf']
            ?? ''
        );

        if (
            $csrfRecibido === ''
            || !hash_equals(
                $csrf,
                $csrfRecibido
            )
        ) {
            throw new RuntimeException(
                'La sesión de confirmación venció.'
            );
        }

        $confirmacion = strtoupper(
            trim(
                (string)(
                    $_POST['confirmacion']
                    ?? ''
                )
            )
        );

        if (
            $confirmacion
            !== strtoupper($confirmacionEsperada)
        ) {
            throw new RuntimeException(
                'La frase de confirmación no coincide.'
            );
        }

        if (
            ($_POST['acepto'] ?? '')
            !== '1'
        ) {
            throw new RuntimeException(
                'Debe marcar la confirmación.'
            );
        }

        $emision = new ApiSunatEmission();

        $resultado = $emision->enviarVenta(
            $idventa
        );

        $puedeEnviar = false;

        $_SESSION['apisunat_recuperar_csrf'] =
            bin2hex(random_bytes(32));
    }
} catch (Throwable $e) {
    $error = $e->getMessage();

    error_log(
        '[APISUNAT RECUPERAR] Venta '
        . $idventa
        . ': '
        . $e->getMessage()
    );
}

?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Recuperar boleta APISUNAT</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px 16px;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
        }

        .contenedor {
            max-width: 760px;
            margin: auto;
        }

        .card {
            background: #fff;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 18px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .08);
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 16px;
            border-radius: 10px;
        }

        .exito {
            background: #dcfce7;
            color: #166534;
            padding: 16px;
            border-radius: 10px;
        }

        .dato {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        input[type="text"] {
            width: 100%;
            height: 50px;
            padding: 0 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 16px;
        }

        label {
            display: block;
            margin: 18px 0;
        }

        button {
            width: 100%;
            min-height: 54px;
            border: 0;
            border-radius: 10px;
            background: #b91c1c;
            color: white;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
        }

        pre {
            background: #111827;
            color: white;
            padding: 16px;
            border-radius: 10px;
            white-space: pre-wrap;
            word-break: break-word;
            overflow: auto;
        }
    </style>
</head>

<body>

<div class="contenedor">

    <div class="card">
        <h1>Recuperar boleta pendiente</h1>

        <?php if ($error !== ''): ?>

            <div class="error">
                <strong>No se realizó el envío.</strong><br>
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($resultado !== null): ?>

            <div
                class="<?= ($resultado['success'] ?? false)
                    ? 'exito'
                    : 'error' ?>"
            >
                <strong>
                    <?= ($resultado['success'] ?? false)
                        ? 'Comprobante recibido por APISUNAT.'
                        : 'APISUNAT no aceptó la solicitud.' ?>
                </strong>
            </div>

            <pre><?= e(jsonBonito($resultado)) ?></pre>

        <?php endif; ?>
    </div>

    <?php if (is_array($comprobante)): ?>

        <div class="card">
            <h2>Datos del comprobante</h2>

            <div class="dato">
                <span>Venta</span>
                <strong><?= $idventa ?></strong>
            </div>

            <div class="dato">
                <span>Archivo</span>
                <strong>
                    <?= e(
                        (string)$comprobante['fileName']
                    ) ?>
                </strong>
            </div>

            <div class="dato">
                <span>Comprobante</span>
                <strong>
                    <?= e(
                        (string)$comprobante['serie']
                    ) ?>
                    -
                    <?= e(
                        (string)$comprobante['numero']
                    ) ?>
                </strong>
            </div>

            <div class="dato">
                <span>Total</span>
                <strong>
                    S/
                    <?= number_format(
                        (float)$comprobante['totales']['total'],
                        2
                    ) ?>
                </strong>
            </div>

            <?php if (is_array($ultimoDocumento)): ?>

                <div class="dato">
                    <span>Último APISUNAT</span>
                    <strong>
                        <?= e(
                            (string)(
                                $ultimoDocumento['lastNumber']
                                ?? ''
                            )
                        ) ?>
                    </strong>
                </div>

                <div class="dato">
                    <span>Siguiente APISUNAT</span>
                    <strong>
                        <?= e(
                            (string)(
                                $ultimoDocumento['suggestedNumber']
                                ?? ''
                            )
                        ) ?>
                    </strong>
                </div>

            <?php endif; ?>
        </div>

    <?php endif; ?>

    <?php if (
        $puedeEnviar
        && $resultado === null
        && $error === ''
    ): ?>

        <div class="card">
            <h2>Confirmar envío real</h2>

            <p>Escriba exactamente:</p>

            <p>
                <strong>
                    <?= e($confirmacionEsperada) ?>
                </strong>
            </p>

            <form method="post">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e($csrf) ?>"
                >

                <input
                    type="text"
                    name="confirmacion"
                    required
                    autocomplete="off"
                >

                <label>
                    <input
                        type="checkbox"
                        name="acepto"
                        value="1"
                        required
                    >

                    Confirmo que este comprobante debe enviarse
                    realmente a APISUNAT producción.
                </label>

                <button type="submit">
                    ENVIAR COMPROBANTE
                </button>

            </form>
        </div>

    <?php endif; ?>

</div>

</body>
</html>