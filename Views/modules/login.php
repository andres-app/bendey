<?php

$turnstileConfig = require __DIR__ . '/../../Config/turnstile.php';
$turnstileSiteKey = trim((string)($turnstileConfig['site_key'] ?? ''));
$turnstileConfigurado = $turnstileSiteKey !== ''
    && strpos($turnstileSiteKey, 'REEMPLAZA_') !== 0;

?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
  <title>JapiPOS</title>
  <link rel="stylesheet" href="Assets/css/app.min.css">
  <link rel="stylesheet" href="Assets/css/style.css">
  <link rel="stylesheet" href="Assets/css/components.css">
  <link rel="stylesheet" href="Assets/css/custom.css">
  <link rel="shortcut icon" type="image/x-icon" href="Assets/img/favicon.ico">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

  <style>
    html,
    body {
      height: 100%;
      background: #fff !important;
    }

    body {
      min-height: 100vh !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
    }

    .section,
    .container,
    .row,
    .col-12,
    .card.card-primary {
      height: 100%;
      margin: 0;
      padding: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .card.card-primary {
      border: none !important;
      box-shadow: 0 6px 30px #0001;
      border-radius: 0 !important;
      min-width: 340px;
      width: 100%;
      max-width: 400px;
      background: #fff;
      margin: 0;
    }

    .card-body {
      width: 100%;
      padding-top: 10px;
    }

    .form-control {
      border-radius: 12px !important;
      border: 1.5px solid #E5E7EB !important;
      padding: 13px 16px !important;
      font-size: 1.08em !important;
      margin-bottom: 0 !important;
    }

    .form-control:focus {
      border: 1.5px solid #10B981 !important;
    }

    .btn.l-bg-red.btn-block {
      background: #12B265 !important;
      color: #fff !important;
      border: none !important;
      border-radius: 12px !important;
      font-weight: 600 !important;
      font-size: 1.08em !important;
      padding: 12px 0 !important;
      cursor: pointer;
      width: 100% !important;
      transition: background 0.2s, opacity 0.2s;
      margin-top: 8px;
    }

    .btn.l-bg-red.btn-block:hover:not(:disabled) {
      background: #0ea85e !important;
    }

    .btn.l-bg-red.btn-block:disabled {
      cursor: wait;
      opacity: 0.65;
    }

    .forgot-link {
      display: block;
      text-align: center;
      color: #7A7A7A;
      font-size: 0.98em;
      margin-top: 8px;
      margin-bottom: 20px;
      text-decoration: none;
      transition: color 0.2s;
    }

    .forgot-link:hover {
      color: #12B265;
    }

    .login-actions {
      display: flex;
      gap: 22px;
      justify-content: center;
      margin-top: 28px;
    }

    .login-action-btn {
      border: 1px solid #E5E7EB;
      background: #fff;
      border-radius: 10px;
      padding: 13px 24px;
      display: flex;
      align-items: center;
      gap: 8px;
      color: #12B265;
      font-weight: 500;
      font-size: 1em;
      transition: border 0.2s, background 0.2s;
      text-decoration: none;
      margin-bottom: 8px;
    }

    .login-action-btn:hover {
      border: 1.5px solid #10B981;
      background: #F0FFF4;
    }

    .icon-web,
    .icon-whatsapp {
      font-size: 1.3em;
    }

    .logo-flor {
      width: 130px;
      display: block;
      margin: 0 auto 28px auto;
    }

    .turnstile-wrap {
      width: 100%;
      min-height: 72px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 2px 0 4px;
    }

    .cf-turnstile {
      width: 100%;
      min-width: 300px;
    }

    .turnstile-status {
      min-height: 20px;
      margin: 0 0 6px;
      text-align: center;
      color: #6B7280;
      font-size: 0.86rem;
    }

    .turnstile-status.is-success {
      color: #0f9f5b;
    }

    .turnstile-status.is-error {
      color: #dc3545;
    }

    @media (max-width: 480px) {
      .logo-flor {
        width: 90px;
        margin-bottom: 18px;
      }

      .card.card-primary {
        min-width: 90vw;
        max-width: 98vw;
      }

      .card-body {
        padding-left: 20px;
        padding-right: 20px;
      }

      .login-actions {
        gap: 10px;
      }

      .login-action-btn {
        padding: 12px 16px;
      }
    }
  </style>
</head>

<body>
  <div class="loader center-div"></div>

  <div id="app">
    <section class="section">
      <div class="container">
        <div class="row">
          <div class="col-12 col-sm-8 offset-sm-2 col-md-6 offset-md-3 col-lg-6 offset-lg-3 col-xl-4 offset-xl-4">
            <div class="card card-primary">
              <div class="card-body">
                <img src="Assets/img/tiquepos_logo.jpg" class="logo-flor" alt="Logo TiquePOS">

                <form method="POST" action="" class="needs-validation" novalidate id="formAcceso" autocomplete="off">
                  <div class="form-group">
                    <label for="nombre" class="sr-only">Usuario</label>
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
                      Por favor complete su usuario
                    </div>
                  </div>

                  <div class="form-group" style="position: relative;">
                    <label for="clave" class="sr-only">Contraseña</label>
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
                      aria-label="Mostrar contraseña"
                      style="position:absolute; top:50%; right:10px; transform:translateY(-50%); cursor:pointer; color:#9CA3AF; border:0; background:transparent; padding:6px;"
                    >
                      <i class="fa fa-eye" id="eye-icon" aria-hidden="true"></i>
                    </button>
                    <div class="invalid-feedback">
                      Por favor ingrese su contraseña
                    </div>
                  </div>

                  <div class="turnstile-wrap">
                    <?php if ($turnstileConfigurado): ?>
                      <div
                        id="turnstile-login"
                        class="cf-turnstile"
                        data-sitekey="<?php echo htmlspecialchars($turnstileSiteKey, ENT_QUOTES, 'UTF-8'); ?>"
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

                  <div id="turnstile-status" class="turnstile-status<?php echo $turnstileConfigurado ? '' : ' is-error'; ?>" role="status" aria-live="polite">
                    <?php echo $turnstileConfigurado
                        ? 'Verificando seguridad...'
                        : 'Falta configurar la Site Key de Cloudflare Turnstile.'; ?>
                  </div>

                  <div class="form-group">
                    <button type="submit" id="btnAcceso" class="btn l-bg-red btn-block" tabindex="4">
                      <span class="btn-login-text">Iniciar sesión</span>
                    </button>
                  </div>
                </form>

                <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>

                <div class="login-actions">
                  <a href="#" class="login-action-btn">
                    <i class="fa fa-globe icon-web"></i> Web
                  </a>
                  <a href="#" class="login-action-btn">
                    <i class="fab fa-whatsapp icon-whatsapp"></i> Consulta
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="Assets/js/app.min.js"></script>
  <script src="Assets/js/scripts.js"></script>
  <script src="Assets/js/custom.js"></script>
  <script src="Assets/bundles/sweetalert/sweetalert.min.js"></script>

  <script>
    window.TIQUEPOS_TURNSTILE = {
      configurado: <?php echo $turnstileConfigurado ? 'true' : 'false'; ?>,
      siteKey: <?php echo json_encode($turnstileSiteKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    };
  </script>

  <?php
    $rutaLoginJs = __DIR__ . '/scripts/login.js';
    $versionLoginJs = is_file($rutaLoginJs) ? (string)filemtime($rutaLoginJs) : (string)time();
  ?>
  <script src="/Views/modules/scripts/login.js?v=<?php echo rawurlencode($versionLoginJs); ?>"></script>

  <?php if ($turnstileConfigurado): ?>
    <script
      src="https://challenges.cloudflare.com/turnstile/v0/api.js"
      async
      defer
      onerror="window.tiqueposTurnstileScriptError && window.tiqueposTurnstileScriptError();"
    ></script>
  <?php endif; ?>
</body>

</html>
