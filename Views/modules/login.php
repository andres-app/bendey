<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
  <title>JapiPOS</title>
  <link rel="stylesheet" href="Assets/css/app.min.css">
  <link rel="stylesheet" href="Assets/css/style.css">
  <link rel="stylesheet" href="Assets/css/components.css">
  <link rel="stylesheet" href="Assets/css/custom.css">
  <link rel='shortcut icon' type='image/x-icon' href='Assets/img/favicon.ico' />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
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
      /* <-- Sin bordes redondeados */
      min-width: 340px;
      width: 100%;
      max-width: 400px;
      background: #fff;
      margin: 0;
    }

    .card-header {
      border: none;
      background: transparent;
      justify-content: center !important;
    }

    .card-header h4 {
      color: #12B265;
      font-weight: 700;
      text-align: center;
      width: 100%;
    }

    .card-body {
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
      transition: background 0.2s;
      margin-top: 8px;
    }

    .btn.l-bg-red.btn-block:hover {
      background: #0ea85e !important;
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

    @media (max-width: 480px) {
      .logo-flor {
        width: 90px;
        margin-bottom: 18px;
      }

      .card.card-primary {
        min-width: 90vw;
        max-width: 98vw;
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
                <!-- Logo centrado -->
                <img src="Assets/img/tiquepos_logo.jpg" class="logo-flor" alt="Logo" />
                <form method="POST" action="" class="needs-validation" novalidate="" id="formAcceso" autocomplete="off">
                  <div class="form-group">
                    <label for="nombre" class="sr-only">Usuario</label>
                    <input id="nombre" type="text" class="form-control" name="nombre" tabindex="1" required autofocus placeholder="usuario" autocomplete="username">
                    <div class="invalid-feedback">
                      Por favor complete su usuario
                    </div>
                  </div>
                  <div class="form-group" style="position:relative;">
                    <label for="clave" class="sr-only">Contraseña</label>
                    <input id="clave" type="password" class="form-control" name="clave" tabindex="2" required placeholder="contraseña" autocomplete="current-password">
                    <span style="position:absolute;top:50%;right:16px;transform:translateY(-50%);cursor:pointer;color:#9CA3AF;" onclick="togglePassword()">
                      <i class="fa fa-eye" id="eye-icon"></i>
                    </span>
                    <div class="invalid-feedback">
                      Por favor ingrese su contraseña
                    </div>
                  </div>
                  <div class="form-group">
                    <button type="submit" class="btn l-bg-red btn-block" tabindex="4">
                      Iniciar sesión
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
  <script src="Views/modules/scripts/login.js"></script>
  <script src="Assets/js/app.min.js"></script>
  <script src="Assets/js/scripts.js"></script>
  <script src="Assets/js/custom.js"></script>
  <script src="Assets/bundles/sweetalert/sweetalert.min.js"></script>
  <script>
    function togglePassword() {
      const input = document.getElementById('clave');
      const icon = document.getElementById('eye-icon');
      if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    }

    $(document).ready(function() {
      $('.forgot-link').on('click', function(e) {
        e.preventDefault();
        swal({
          title: "Funcionalidad no disponible",
          text: "La recuperación de contraseña aún no está implementada.",
          icon: "info",
          button: "OK",
        });
      });
    });
  </script>
</body>

</html>