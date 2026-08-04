<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN DE CLOUDFLARE TURNSTILE
|--------------------------------------------------------------------------
*/

$turnstileConfig = require __DIR__ . '/../../Config/turnstile.php';

$turnstileSiteKey = trim(
    (string) ($turnstileConfig['site_key'] ?? '')
);

$turnstileConfigurado =
    $turnstileSiteKey !== ''
    && strpos($turnstileSiteKey, 'REEMPLAZA_') !== 0;

/*
|--------------------------------------------------------------------------
| VERSIONADO AUTOMÁTICO DE ARCHIVOS
|--------------------------------------------------------------------------
| Evita que el navegador conserve una versión antigua del CSS o JS.
*/

$rutaLoginCss = __DIR__ . '/../../Assets/css/login.css';

$versionLoginCss = is_file($rutaLoginCss)
    ? (string) filemtime($rutaLoginCss)
    : '1';

$rutaLoginJs = __DIR__ . '/scripts/login.js';

$versionLoginJs = is_file($rutaLoginJs)
    ? (string) filemtime($rutaLoginJs)
    : '1';

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no"
    >

    <title>TiquePOS</title>

    <!-- CSS base -->
    <link rel="stylesheet" href="Assets/css/app.min.css">
    <link rel="stylesheet" href="Assets/css/style.css">
    <link rel="stylesheet" href="Assets/css/components.css">
    <link rel="stylesheet" href="Assets/css/custom.css">

    <!-- CSS exclusivo del login -->
    <link
        rel="stylesheet"
        href="Assets/css/login.css?v=<?php echo rawurlencode($versionLoginCss); ?>"
    >

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
    >

    <!-- Favicon -->
    <link
        rel="shortcut icon"
        type="image/x-icon"
        href="Assets/img/favicon.ico"
    >
</head>

<body>

    <div class="loader center-div"></div>

    <div id="app">
        <section class="section">

            <div class="container">

                <div class="row">

                    <div
                        class="col-12 col-sm-8 offset-sm-2
                               col-md-6 offset-md-3
                               col-lg-6 offset-lg-3
                               col-xl-4 offset-xl-4"
                    >

                        <div class="card card-primary">

                            <div class="card-body">

                                <!-- Logo -->
                                <img
                                    src="Assets/img/tiquepos_logo.jpg"
                                    class="logo-flor"
                                    alt="Logo TiquePOS"
                                >

                                <!-- Formulario -->
                                <form
                                    method="POST"
                                    action=""
                                    class="needs-validation"
                                    novalidate
                                    id="formAcceso"
                                    autocomplete="off"
                                >

                                    <!-- Usuario -->
                                    <div class="form-group">

                                        <label for="nombre" class="sr-only">
                                            Usuario
                                        </label>

                                        <input
                                            id="nombre"
                                            type="text"
                                            class="form-control"
                                            name="nombre"
                                            tabindex="1"
                                            required
                                            autofocus
                                            placeholder="Usuario"
                                            autocomplete="username"
                                        >

                                        <div class="invalid-feedback">
                                            Por favor complete su usuario.
                                        </div>

                                    </div>

                                    <!-- Contraseña -->
                                    <div class="form-group password-field">

                                        <label for="clave" class="sr-only">
                                            Contraseña
                                        </label>

                                        <input
                                            id="clave"
                                            type="password"
                                            class="form-control"
                                            name="clave"
                                            tabindex="2"
                                            required
                                            placeholder="Contraseña"
                                            autocomplete="current-password"
                                        >

                                        <button
                                            type="button"
                                            id="togglePasswordButton"
                                            class="password-toggle"
                                            aria-label="Mostrar contraseña"
                                            aria-controls="clave"
                                        >
                                            <i
                                                class="fa fa-eye"
                                                id="eye-icon"
                                                aria-hidden="true"
                                            ></i>
                                        </button>

                                        <div class="invalid-feedback">
                                            Por favor ingrese su contraseña.
                                        </div>

                                    </div>

                                    <!-- Cloudflare Turnstile -->
                                    <div class="turnstile-wrap">

                                        <?php if ($turnstileConfigurado): ?>

                                            <div
                                                id="turnstile-login"
                                                class="cf-turnstile"
                                                data-sitekey="<?php
                                                    echo htmlspecialchars(
                                                        $turnstileSiteKey,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );
                                                ?>"
                                                data-action="login"
                                                data-theme="light"
                                                data-language="es"
                                                data-size="flexible"
                                                data-retry="auto"
                                                data-refresh-expired="auto"
                                                data-callback="tiqueposTurnstileOk"
                                                data-error-callback="tiqueposTurnstileError"
                                                data-expired-callback="tiqueposTurnstileExpired"
                                                data-timeout-callback="tiqueposTurnstileTimeout"
                                            ></div>

                                        <?php endif; ?>

                                    </div>

                                    <!-- Estado de Turnstile -->
                                    <div
                                        id="turnstile-status"
                                        class="turnstile-status<?php
                                            echo $turnstileConfigurado
                                                ? ''
                                                : ' is-error';
                                        ?>"
                                        role="status"
                                        aria-live="polite"
                                    >
                                        <?php
                                        echo $turnstileConfigurado
                                            ? 'Verificando seguridad...'
                                            : 'Falta configurar la Site Key de Cloudflare Turnstile.';
                                        ?>
                                    </div>

                                    <!-- Botón ingresar -->
                                    <div class="form-group">

                                        <button
                                            type="submit"
                                            id="btnAcceso"
                                            class="btn l-bg-red btn-block"
                                            tabindex="4"
                                        >
                                            <span class="btn-login-text">
                                                Iniciar sesión
                                            </span>
                                        </button>

                                    </div>

                                </form>

                                <!-- Recuperar contraseña -->
                                <a href="#" class="forgot-link">
                                    ¿Olvidaste tu contraseña?
                                </a>

                                <!-- Acciones inferiores -->
                                <div class="login-actions">

                                    <a href="#" class="login-action-btn">
                                        <i class="fa fa-globe icon-web"></i>
                                        <span>Web</span>
                                    </a>

                                    <a href="#" class="login-action-btn">
                                        <i class="fab fa-whatsapp icon-whatsapp"></i>
                                        <span>Consulta</span>
                                    </a>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>
    </div>

    <!-- JavaScript base -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="Assets/js/app.min.js"></script>
    <script src="Assets/js/scripts.js"></script>
    <script src="Assets/js/custom.js"></script>
    <script src="Assets/bundles/sweetalert/sweetalert.min.js"></script>

    <!-- Configuración accesible desde JavaScript -->
    <script>
        window.TIQUEPOS_TURNSTILE = {
            configurado: <?php
                echo $turnstileConfigurado ? 'true' : 'false';
            ?>,
            siteKey: <?php
                echo json_encode(
                    $turnstileSiteKey,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                );
            ?>
        };
    </script>

    <!-- JavaScript exclusivo del login -->
    <script
        src="/Views/modules/scripts/login.js?v=<?php
            echo rawurlencode($versionLoginJs);
        ?>"
    ></script>

    <!-- Cloudflare Turnstile -->
    <?php if ($turnstileConfigurado): ?>

        <script
            src="https://challenges.cloudflare.com/turnstile/v0/api.js"
            async
            defer
            onerror="
                window.tiqueposTurnstileScriptError
                && window.tiqueposTurnstileScriptError();
            "
        ></script>

    <?php endif; ?>

</body>

</html>