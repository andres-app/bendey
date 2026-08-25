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

  function reiniciarTurnstile(mensaje, tipo) {
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
      typeof tipo === "string" ? tipo : "error"
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
      mostrarError("Asegúrate de completar el correo y la contraseña.");
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

  let recoveryToken = "";
  let recoveryBusy = false;

  const $recoveryModal = $("#passwordRecoveryModal");
  const $recoveryMessage = $("#recoveryMessage");
  const $stepRequest = $("#recoveryStepRequest");
  const $stepOtp = $("#recoveryStepOtp");
  const $stepPassword = $("#recoveryStepPassword");

  function mostrarPasoRecuperacion(paso) {
    $stepRequest.prop("hidden", paso !== "request");
    $stepOtp.prop("hidden", paso !== "otp");
    $stepPassword.prop("hidden", paso !== "password");
    limpiarMensajeRecuperacion();
  }

  function mostrarMensajeRecuperacion(mensaje, tipo) {
    $recoveryMessage
      .removeClass("is-error is-success")
      .addClass("is-visible")
      .addClass(tipo === "success" ? "is-success" : "is-error")
      .text(mensaje || "");
  }

  function limpiarMensajeRecuperacion() {
    $recoveryMessage
      .removeClass("is-visible is-error is-success")
      .text("");
  }

  function abrirRecuperacion() {
    const usuarioActual = String($("#nombre").val() || "").trim();

    recoveryToken = "";
    $("#recoveryLogin").val(usuarioActual);
    $("#recoveryOtp").val("");
    $("#recoveryPassword").val("");
    $("#recoveryPasswordConfirm").val("");
    mostrarPasoRecuperacion("request");

    $recoveryModal.addClass("is-open").attr("aria-hidden", "false");
    $("body").addClass("recovery-open");

    window.setTimeout(function () {
      $("#recoveryLogin").trigger("focus");
    }, 50);
  }

  function cerrarRecuperacion() {
    if (recoveryBusy) {
      return;
    }

    $recoveryModal.removeClass("is-open").attr("aria-hidden", "true");
    $("body").removeClass("recovery-open");
    limpiarMensajeRecuperacion();
  }

  function establecerBotonRecuperacion($btn, estado, textoNormal, textoProcesando) {
    recoveryBusy = estado;
    $btn.prop("disabled", estado);

    if (estado) {
      $btn.html('<i class="fas fa-spinner fa-spin mr-2"></i>' + textoProcesando);
    } else {
      $btn.text(textoNormal);
    }
  }

  function solicitarOtp() {
    if (recoveryBusy) {
      return;
    }

    const login = String($("#recoveryLogin").val() || "").trim();
    const token = obtenerToken();
    const $btn = $("#btnRequestOtp");

    limpiarMensajeRecuperacion();

    if (!login) {
      mostrarMensajeRecuperacion("Ingresa tu correo electrónico registrado.", "error");
      return;
    }

    if (!token) {
      mostrarMensajeRecuperacion(
        "Completa primero la verificación de seguridad que aparece en el login y vuelve a intentarlo.",
        "error"
      );
      return;
    }

    establecerBotonRecuperacion($btn, true, "Enviar código OTP", "Enviando...");

    $.ajax({
      url: "Controllers/User.php?op=solicitar_otp",
      type: "POST",
      dataType: "json",
      timeout: 25000,
      data: {
        login: login,
        "cf-turnstile-response": token
      }
    })
      .done(function (respuesta) {
        if (!respuesta || !respuesta.ok) {
          mostrarMensajeRecuperacion(
            (respuesta && respuesta.mensaje) || "No se pudo enviar el código OTP.",
            "error"
          );
          reiniciarTurnstile();
          return;
        }

        recoveryToken = String(respuesta.reset_token || "");

        if (!recoveryToken) {
          mostrarMensajeRecuperacion("No se recibió el token de recuperación.", "error");
          reiniciarTurnstile();
          return;
        }

        mostrarPasoRecuperacion("otp");
        mostrarMensajeRecuperacion(
          respuesta.mensaje || "Código enviado al correo registrado.",
          "success"
        );

        reiniciarTurnstile("Verificando seguridad para el próximo acceso...", "");

        window.setTimeout(function () {
          $("#recoveryOtp").trigger("focus");
        }, 50);
      })
      .fail(function (xhr) {
        let mensaje = "No se pudo conectar con el servidor.";

        if (xhr && xhr.responseJSON && xhr.responseJSON.mensaje) {
          mensaje = xhr.responseJSON.mensaje;
        }

        mostrarMensajeRecuperacion(mensaje, "error");
        reiniciarTurnstile();
      })
      .always(function () {
        establecerBotonRecuperacion($btn, false, "Enviar código OTP", "Enviando...");
      });
  }

  function verificarOtpRecuperacion() {
    if (recoveryBusy) {
      return;
    }

    const otp = String($("#recoveryOtp").val() || "").replace(/\D/g, "").slice(0, 6);
    const $btn = $("#btnVerifyOtp");

    $("#recoveryOtp").val(otp);
    limpiarMensajeRecuperacion();

    if (!recoveryToken || otp.length !== 6) {
      mostrarMensajeRecuperacion("Ingresa el código OTP de 6 dígitos.", "error");
      return;
    }

    establecerBotonRecuperacion($btn, true, "Verificar código", "Verificando...");

    $.ajax({
      url: "Controllers/User.php?op=verificar_otp",
      type: "POST",
      dataType: "json",
      timeout: 20000,
      data: {
        reset_token: recoveryToken,
        otp: otp
      }
    })
      .done(function (respuesta) {
        if (!respuesta || !respuesta.ok) {
          mostrarMensajeRecuperacion(
            (respuesta && respuesta.mensaje) || "El código OTP no es válido.",
            "error"
          );
          return;
        }

        mostrarPasoRecuperacion("password");
        mostrarMensajeRecuperacion("Código verificado. Define tu nueva contraseña.", "success");

        window.setTimeout(function () {
          $("#recoveryPassword").trigger("focus");
        }, 50);
      })
      .fail(function (xhr) {
        mostrarMensajeRecuperacion(
          (xhr && xhr.responseJSON && xhr.responseJSON.mensaje)
            || "No se pudo verificar el código OTP.",
          "error"
        );
      })
      .always(function () {
        establecerBotonRecuperacion($btn, false, "Verificar código", "Verificando...");
      });
  }

  function restablecerClave() {
    if (recoveryBusy) {
      return;
    }

    const clave = String($("#recoveryPassword").val() || "");
    const confirmar = String($("#recoveryPasswordConfirm").val() || "");
    const $btn = $("#btnResetPassword");

    limpiarMensajeRecuperacion();

    if (clave === "") {
      mostrarMensajeRecuperacion("Ingresa la nueva contraseña.", "error");
      return;
    }

    if (clave !== confirmar) {
      mostrarMensajeRecuperacion("Las contraseñas no coinciden.", "error");
      return;
    }

    establecerBotonRecuperacion($btn, true, "Cambiar contraseña", "Actualizando...");

    $.ajax({
      url: "Controllers/User.php?op=restablecer_clave",
      type: "POST",
      dataType: "json",
      timeout: 20000,
      data: {
        reset_token: recoveryToken,
        clave: clave
      }
    })
      .done(function (respuesta) {
        if (!respuesta || !respuesta.ok) {
          mostrarMensajeRecuperacion(
            (respuesta && respuesta.mensaje) || "No se pudo actualizar la contraseña.",
            "error"
          );
          return;
        }

        $("#clave").val("");
        $("#nombre").val(String($("#recoveryLogin").val() || "").trim());

        recoveryBusy = false;
        cerrarRecuperacion();

        swal({
          title: "Contraseña actualizada",
          text: respuesta.mensaje || "Ya puedes iniciar sesión con tu nueva contraseña.",
          icon: "success",
          button: "Iniciar sesión"
        }).then(function () {
          $("#clave").trigger("focus");
        });
      })
      .fail(function (xhr) {
        mostrarMensajeRecuperacion(
          (xhr && xhr.responseJSON && xhr.responseJSON.mensaje)
            || "No se pudo actualizar la contraseña.",
          "error"
        );
      })
      .always(function () {
        establecerBotonRecuperacion($btn, false, "Cambiar contraseña", "Actualizando...");
      });
  }

  $(".forgot-link").on("click", function (e) {
    e.preventDefault();
    abrirRecuperacion();
  });

  $("#recoveryClose").on("click", cerrarRecuperacion);

  $recoveryModal.on("click", function (e) {
    if (e.target === this) {
      cerrarRecuperacion();
    }
  });

  $(document).on("keydown", function (e) {
    if (e.key === "Escape" && $recoveryModal.hasClass("is-open")) {
      cerrarRecuperacion();
    }
  });

  $("#btnRequestOtp").on("click", solicitarOtp);
  $("#btnVerifyOtp").on("click", verificarOtpRecuperacion);
  $("#btnResetPassword").on("click", restablecerClave);

  $("#recoveryOtp").on("input", function () {
    this.value = String(this.value || "").replace(/\D/g, "").slice(0, 6);
  });

  $("#btnRestartRecovery").on("click", function () {
    recoveryToken = "";
    recoveryBusy = false;
    $("#recoveryOtp").val("");
    mostrarPasoRecuperacion("request");
    mostrarMensajeRecuperacion(
      "Completa nuevamente la verificación de seguridad del login para solicitar otro código.",
      "success"
    );
    reiniciarTurnstile("Verificando seguridad...", "");
  });

  $("#recoveryLogin").on("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      solicitarOtp();
    }
  });

  $("#recoveryOtp").on("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      verificarOtpRecuperacion();
    }
  });

  $("#recoveryPassword, #recoveryPasswordConfirm").on("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      restablecerClave();
    }
  });

})(jQuery, window, document);
