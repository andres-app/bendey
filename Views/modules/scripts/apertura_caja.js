// Views/modules/scripts/apertura_caja.js

$(function () {
  verificarAperturaCaja();

  $(document).on(
    "click",
    "#btnAbrirCaja",
    function () {
      registrarAperturaCaja();
    }
  );
});

/*
|--------------------------------------------------------------------------
| VERIFICAR CAJA ABIERTA
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
        "Verificación de caja:",
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

      if (!resp.existe) {
        $("#modalCajaChica").modal(
          "show"
        );

        setTimeout(function () {
          $("#montoApertura").trigger(
            "focus"
          );
        }, 300);
      }
    },

    error: function (xhr) {
      console.error(
        "Error al verificar caja:",
        xhr.responseText
      );
    },
  });
}

/*
|--------------------------------------------------------------------------
| REGISTRAR APERTURA
|--------------------------------------------------------------------------
*/
function registrarAperturaCaja() {
  const monto =
    parseFloat(
      $("#montoApertura").val()
    ) || 0;

  if (monto < 0) {
    Swal.fire({
      icon: "warning",
      title: "Monto inválido",
      text:
        "El monto de apertura no puede ser negativo.",
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
    dataType: "json",

    data: {
      monto: monto.toFixed(2),
    },

    success: function (resp) {
      if (resp.status === "ok") {
        $("#modalCajaChica").modal(
          "hide"
        );

        Swal.fire({
          icon: "success",
          title:
            "Caja abierta correctamente",
          timer: 1200,
          showConfirmButton: false,
        });

        setTimeout(function () {
          window.location.reload();
        }, 1200);

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
        "Error al abrir caja:",
        xhr.responseText
      );

      Swal.fire({
        icon: "error",
        title: "Error",
        text:
          "No se pudo comunicar con el servidor.",
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