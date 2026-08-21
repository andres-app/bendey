// Views/modules/scripts/login.js
(function ($, window, document) {
  "use strict";

  let turnstileToken = "";
  let turnstileWidgetId = null;
  let procesandoLogin = false;
  let redirigiendo = false;

  const $form = $("#formAcceso");
  const $boton = $("#btnAcceso");
  const $estado = $("#turnstile-status");

  function cambiarEstado(mensaje, tipo) {
    $estado.removeClass("is-success is-error").text(mensaje || "");

    if (tipo === "success") {
      $estado.addClass("is-success");
    } else if (tipo === "error") {
      $estado.addClass("is-error");
    }
  }

  function mostrarError(mensaje) {
    swal({
      title: "Error",
      text: mensaje,
      icon: "error",
      buttons: {
        confirm: "OK"
      }
    });
  }

  function establecerProcesando(estado) {
    procesandoLogin = estado;
    $boton.prop("disabled", estado);

    if (estado) {
      $boton.html('<i class="fas fa-spinner fa-spin mr-2"></i> Verificando...');
    } else {
      $boton.html('<span class="btn-login-text">Iniciar sesión</span>');
    }
  }

  function obtenerToken() {
    if (turnstileToken) {
      return turnstileToken;
    }

    return String(
      $("input[name='cf-turnstile-response']").val() || ""
    ).trim();
  }

  function reiniciarTurnstile(mensaje) {
    turnstileToken = "";

    if (
      window.turnstile
      && typeof window.turnstile.reset === "function"
      && turnstileWidgetId !== null
    ) {
      try {
        window.turnstile.reset(turnstileWidgetId);
      } catch (error) {
        console.error("No se pudo reiniciar Turnstile:", error);
      }
    }

    cambiarEstado(
      mensaje || "Realiza nuevamente la verificación de seguridad.",
      "error"
    );
  }

  window.tiqueposTurnstileOk = function (token) {
    turnstileToken = String(token || "").trim();

    cambiarEstado(
      turnstileToken
        ? "Verificación completada."
        : "No se obtuvo el token de seguridad.",
      turnstileToken ? "success" : "error"
    );
  };

  window.tiqueposTurnstileError = function (codigo) {
    turnstileToken = "";

    const codigoTexto = String(codigo || "");
    let mensaje = "No se pudo completar la verificación de seguridad.";

    if (codigoTexto.indexOf("110200") === 0) {
      mensaje = "Este dominio no está autorizado en el widget de Cloudflare Turnstile.";
    } else if (
      codigoTexto.indexOf("110100") === 0
      || codigoTexto.indexOf("110110") === 0
      || codigoTexto.indexOf("400020") === 0
    ) {
      mensaje = "La Site Key configurada no pertenece a un widget válido.";
    } else if (codigoTexto.indexOf("400070") === 0) {
      mensaje = "La Site Key está desactivada en Cloudflare.";
    } else if (codigoTexto.indexOf("200500") === 0) {
      mensaje = "El navegador o la red está bloqueando challenges.cloudflare.com.";
    }

    console.error("Cloudflare Turnstile, código:", codigoTexto);
    cambiarEstado(mensaje + (codigoTexto ? " Código: " + codigoTexto : ""), "error");
    return true;
  };

  window.tiqueposTurnstileExpired = function () {
    turnstileToken = "";
    cambiarEstado("La verificación venció. Espera una nueva validación.", "error");
  };

  window.tiqueposTurnstileTimeout = function () {
    turnstileToken = "";
    cambiarEstado("La verificación agotó el tiempo. Intenta nuevamente.", "error");
  };

  window.tiqueposTurnstileScriptError = function () {
    turnstileToken = "";
    cambiarEstado(
      "No se pudo cargar challenges.cloudflare.com. Revisa bloqueadores, DNS o CSP.",
      "error"
    );
  };

  window.tiqueposTurnstileRender = function () {
    const config = window.TIQUEPOS_TURNSTILE || {};

    if (!config.configurado || !config.siteKey) {
      cambiarEstado("Cloudflare Turnstile no está configurado.", "error");
      return;
    }

    if (!window.turnstile || typeof window.turnstile.render !== "function") {
      cambiarEstado("No se pudo iniciar Cloudflare Turnstile.", "error");
      return;
    }

    try {
      turnstileWidgetId = window.turnstile.render("#turnstile-login", {
        sitekey: String(config.siteKey),
        action: "login",
        theme: "light",
        language: "es",
        size: "flexible",
        retry: "auto",
        "refresh-expired": "auto",
        callback: window.tiqueposTurnstileOk,
        "error-callback": window.tiqueposTurnstileError,
        "expired-callback": window.tiqueposTurnstileExpired,
        "timeout-callback": window.tiqueposTurnstileTimeout
      });
    } catch (error) {
      console.error("No se pudo renderizar Turnstile:", error);
      cambiarEstado("No se pudo iniciar la verificación de seguridad.", "error");
    }
  };

  $form.on("submit", function (e) {
    e.preventDefault();

    if (procesandoLogin || redirigiendo) {
      return;
    }

    const nombre = String($("#nombre").val() || "").trim();
    const clave = String($("#clave").val() || "");
    const token = obtenerToken();

    if (!nombre || !clave) {
      mostrarError("Asegúrate de completar el usuario y la contraseña.");
      return;
    }

    if (!token) {
      mostrarError(
        "Cloudflare todavía no completó la verificación. Revisa el mensaje debajo del captcha."
      );
      return;
    }

    establecerProcesando(true);

    $.ajax({
      url: "Controllers/User.php?op=verificar",
      type: "POST",
      dataType: "text",
      timeout: 25000,
      data: {
        nombre: nombre,
        clave: clave,
        "cf-turnstile-response": token
      }
    })
      .done(function (data) {
        const respuesta = String(data || "").trim();

        if (respuesta === "1") {
          redirigiendo = true;
          window.location.href = "dashboard";
          return;
        }

        if (respuesta === "0") {
          mostrarError("Usuario y/o contraseña incorrectos.");
        } else {
          mostrarError(
            respuesta || "No se pudo iniciar sesión. Intenta nuevamente."
          );
        }

        reiniciarTurnstile();
      })
      .fail(function (xhr, estado) {
        let mensaje = "No se pudo conectar con el servidor. Intenta nuevamente.";

        if (estado === "timeout") {
          mensaje = "La solicitud tardó demasiado. Intenta nuevamente.";
        } else if (xhr && xhr.responseText) {
          const respuestaServidor = String(xhr.responseText).trim();

          if (respuestaServidor && respuestaServidor.length <= 300) {
            mensaje = respuestaServidor;
          }
        }

        mostrarError(mensaje);
        reiniciarTurnstile();
      })
      .always(function () {
        if (!redirigiendo) {
          establecerProcesando(false);
        }
      });
  });

  $("#togglePasswordButton").on("click", function () {
    const input = document.getElementById("clave");
    const icono = document.getElementById("eye-icon");

    if (!input || !icono) {
      return;
    }

    const mostrar = input.type === "password";
    input.type = mostrar ? "text" : "password";

    icono.classList.toggle("fa-eye", !mostrar);
    icono.classList.toggle("fa-eye-slash", mostrar);

    $(this).attr(
      "aria-label",
      mostrar ? "Ocultar contraseña" : "Mostrar contraseña"
    );
  });

  $(".forgot-link").on("click", function (e) {
    e.preventDefault();

    swal({
      title: "Funcionalidad no disponible",
      text: "La recuperación de contraseña aún no está implementada.",
      icon: "info",
      button: "OK"
    });
  });

})(jQuery, window, document);
