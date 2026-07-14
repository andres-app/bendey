// Views/modules/scripts/apertura_caja.js

$(document).ready(function () {
  verificarAperturaCaja();

  $(document).on(
    "click",
    "#btnAbrirCaja",
    function () {
      abrirCaja();
    }
  );
});

/*
|--------------------------------------------------------------------------
| VERIFICAR APERTURA
|--------------------------------------------------------------------------
*/
function verificarAperturaCaja() {
  $.ajax({
    url:
      "Controllers/Cajachica.php" +
      "?op=verificar_apertura",

    type: "GET",
    dataType: "json",

    success: function (resp) {
      console.log(
        "Respuesta verificar apertura:",
        resp
      );

      if (resp.status === "error") {
        Swal.fire({
          icon: "error",
          title: "Error de caja",
          text:
            resp.message ||
            "No se pudo verificar la caja.",
        });

        return;
      }

      if (resp.existe === true) {
        $("#modalCajaChica").modal(
          "hide"
        );

        return;
      }

      $("#modalCajaChica").modal({
        backdrop: "static",
        keyboard: false,
        show: true,
      });

      setTimeout(function () {
        $("#montoApertura").trigger(
          "focus"
        );
      }, 300);
    },

    error: function (xhr) {
      console.error(
        "HTTP:",
        xhr.status
      );

      console.error(
        "Respuesta:",
        xhr.responseText
      );

      Swal.fire({
        icon: "error",
        title: "Error del servidor",
        text:
          "No se pudo verificar la apertura de caja.",
      });
    },
  });
}

/*
|--------------------------------------------------------------------------
| REGISTRAR APERTURA
|--------------------------------------------------------------------------
*/
function abrirCaja() {
  const valorMonto = String(
    $("#montoApertura").val() || ""
  ).trim();

  if (valorMonto === "") {
    Swal.fire({
      icon: "warning",
      title: "Monto requerido",
      text:
        "Ingrese el monto inicial de caja.",
    });

    return;
  }

  const monto =
    parseFloat(valorMonto);

  if (
    !Number.isFinite(monto) ||
    monto < 0
  ) {
    Swal.fire({
      icon: "warning",
      title: "Monto inválido",
      text:
        "Ingrese un monto válido.",
    });

    return;
  }

  const boton =
    $("#btnAbrirCaja");

  boton
    .prop("disabled", true)
    .html(
      '<i class="fas fa-spinner fa-spin"></i> Abriendo...'
    );

  $.ajax({
    url:
      "Controllers/Cajachica.php" +
      "?op=guardar_apertura",

    type: "POST",

    /*
     * El servidor ya devuelve JSON.
     * No se debe utilizar JSON.parse().
     */
    dataType: "json",

    data: {
      monto: monto.toFixed(2),
    },

    success: function (resp) {
      console.log(
        "Respuesta guardar apertura:",
        resp
      );

      if (resp.status === "ok") {
        $("#modalCajaChica").modal(
          "hide"
        );

        Swal.fire({
          icon: "success",
          title:
            "Caja abierta correctamente",
          text:
            resp.message || "",
          timer: 1400,
          showConfirmButton: false,
        });

        setTimeout(function () {
          window.location.reload();
        }, 1400);

        return;
      }

      /*
       * Puede ocurrir cuando el primer intento
       * ya creó la apertura correctamente.
       */
      if (
        String(
          resp.message || ""
        )
          .toLowerCase()
          .includes(
            "ya existe una caja abierta"
          )
      ) {
        $("#modalCajaChica").modal(
          "hide"
        );

        Swal.fire({
          icon: "info",
          title:
            "La caja ya está abierta",
          text:
            resp.message,
          timer: 1600,
          showConfirmButton: false,
        });

        setTimeout(function () {
          window.location.reload();
        }, 1600);

        return;
      }

      Swal.fire({
        icon: "error",
        title:
          "No se pudo abrir la caja",
        text:
          resp.message ||
          "Ocurrió un error.",
      });
    },

    error: function (xhr) {
      console.error(
        "HTTP:",
        xhr.status
      );

      console.error(
        "Respuesta:",
        xhr.responseText
      );

      let mensaje =
        "No se pudo comunicar con el servidor.";

      if (
        xhr.responseJSON &&
        (
          xhr.responseJSON.message ||
          xhr.responseJSON.error
        )
      ) {
        mensaje =
          xhr.responseJSON.message ||
          xhr.responseJSON.error;
      }

      Swal.fire({
        icon: "error",
        title: "Error del servidor",
        text: mensaje,
      });
    },

    complete: function () {
      boton
        .prop("disabled", false)
        .html(
          '<i class="fas fa-lock-open"></i> Abrir caja'
        );
    },
  });
}