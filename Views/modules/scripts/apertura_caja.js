"use strict";

let contextoCajaActual = {
  modo: "LEGACY",
  modoObjetivo: "",
  idsucursal: 0,
  idcajaUnica: 0,
  idcajaActiva: 0,
  idcajaPreparada: 0,
  idaperturaActiva: 0,
  cajas: [],
};

$(document).ready(function () {
  cargarContextoCaja();

  $(document).on("click", "#btnAbrirCaja", function () {
    abrirCaja();
  });

  $(document).on("change", "#idcajaOperacion", function () {
    const idcaja = Number($(this).val() || 0);

    if (idcaja <= 0) {
      contextoCajaActual.idcajaActiva = 0;
      configurarCajaSinSeleccionar();
      return;
    }

    seleccionarCajaOperacion(idcaja);
  });
});

function cargarContextoCaja() {
  $.ajax({
    url: "Controllers/ContextoCaja.php?op=obtener",
    type: "GET",
    dataType: "json",
    cache: false,

    success: function (resp) {
      if (!resp || resp.success !== true || !resp.contexto) {
        aplicarContextoLegacy();
        verificarAperturaCaja();
        return;
      }

      contextoCajaActual = {
        modo: String(resp.contexto.modo || "LEGACY").toUpperCase(),
        modoObjetivo: String(
          resp.contexto.modo_objetivo || ""
        ).toUpperCase(),
        idsucursal: Number(resp.contexto.idsucursal || 0),
        idcajaUnica: Number(resp.contexto.idcaja_unica || 0),
        idcajaActiva: Number(resp.contexto.idcaja_activa || 0),
        idcajaPreparada: Number(resp.contexto.idcaja_preparada || 0),
        idaperturaActiva: Number(resp.contexto.idapertura_activa || 0),
        cajas: Array.isArray(resp.cajas) ? resp.cajas : [],
      };

      renderizarContextoCaja();
    },

    error: function (xhr) {
      console.error("Error al cargar contexto de caja:", xhr.responseText);
      aplicarContextoLegacy();
      verificarAperturaCaja();
    },
  });
}

function renderizarContextoCaja() {
  ocultarBloquesContexto();

  if (contextoCajaActual.modo === "CAJA_UNICA") {
    renderizarCajaUnica();
    verificarAperturaCaja();
    return;
  }

  if (contextoCajaActual.modo === "MULTICAJA") {
    renderizarMulticaja();

    if (contextoCajaActual.idcajaActiva > 0) {
      $("#idcajaOperacion").val(
        String(contextoCajaActual.idcajaActiva)
      );
      actualizarPermisoCajaSeleccionada();
      verificarAperturaCaja();
    } else {
      configurarCajaSinSeleccionar();
      mostrarModalCaja();
    }

    return;
  }

  aplicarContextoLegacy();
  verificarAperturaCaja();
}

function aplicarContextoLegacy() {
  contextoCajaActual.modo = "LEGACY";

  ocultarBloquesContexto();

  $("#btnAbrirCaja")
    .prop("disabled", false)
    .html('<i class="fas fa-lock-open"></i> INICIAR CAJA');

  $("#montoApertura").prop("disabled", false);
}

function renderizarCajaUnica() {
  const caja = contextoCajaActual.cajas.find(function (registro) {
    return Number(registro.idcaja) === contextoCajaActual.idcajaUnica;
  });

  contextoCajaActual.idcajaActiva = contextoCajaActual.idcajaUnica;

  $("#bloqueContextoCaja").removeClass("d-none");
  $("#tituloContextoCaja").text("Caja única");
  $("#descripcionContextoCaja").text(
    "Todos los usuarios autorizados trabajan sobre la misma apertura."
  );

  $("#grupoCajaAutomatica").removeClass("d-none");
  $("#nombreCajaAutomatica").text(
    caja ? String(caja.nombre || "") : "Caja no encontrada"
  );
  $("#codigoCajaAutomatica").text(
    caja ? String(caja.codigo || "") : "—"
  );

  actualizarPermisoCajaSeleccionada(caja || null);
}

function renderizarMulticaja() {
  $("#bloqueContextoCaja").removeClass("d-none");
  $("#tituloContextoCaja").text("Multicaja");
  $("#descripcionContextoCaja").text(
    "Seleccione la caja física que operará. Cada caja mantiene su propia apertura y cierre."
  );

  $("#grupoSeleccionCaja").removeClass("d-none");
  cargarCajasAutorizadas();
}

function cargarCajasAutorizadas() {
  const $select = $("#idcajaOperacion");
  $select.empty();

  if (
    !Array.isArray(contextoCajaActual.cajas) ||
    contextoCajaActual.cajas.length === 0
  ) {
    $select.append(
      $("<option>", {
        value: "",
        text: "No tiene cajas autorizadas",
      })
    );
    $select.prop("disabled", true);
    return;
  }

  $select.prop("disabled", false);
  $select.append(
    $("<option>", {
      value: "",
      text: "Seleccione una caja",
    })
  );

  contextoCajaActual.cajas.forEach(function (caja) {
    $select.append(
      $("<option>", {
        value: Number(caja.idcaja),
        text:
          String(caja.nombre || "") +
          " (" +
          String(caja.codigo || "") +
          ")",
      })
    );
  });

  if (contextoCajaActual.idcajaActiva > 0) {
    $select.val(String(contextoCajaActual.idcajaActiva));
  }
}

function seleccionarCajaOperacion(idcaja) {
  $("#idcajaOperacion").prop("disabled", true);
  $("#btnAbrirCaja").prop("disabled", true);
  $("#montoApertura").prop("disabled", true);

  mostrarMensajeCaja(
    "info",
    "Validando la caja seleccionada..."
  );

  $.ajax({
    url: "Controllers/ContextoCaja.php?op=seleccionar",
    type: "POST",
    dataType: "json",
    data: {
      idcaja: idcaja,
    },

    success: function (resp) {
      if (!resp || resp.success !== true) {
        contextoCajaActual.idcajaActiva = 0;
        $("#idcajaOperacion").val("");

        Swal.fire({
          icon: "error",
          title: "No se pudo seleccionar la caja",
          text: resp && resp.mensaje ? resp.mensaje : "Operación no válida.",
        });

        configurarCajaSinSeleccionar();
        return;
      }

      if (resp.operativa !== true) {
        Swal.fire({
          icon: "warning",
          title: "Caja no operativa",
          text:
            resp.mensaje ||
            "La caja fue preparada, pero el modo real todavía no está activo.",
        });
        return;
      }

      contextoCajaActual.idcajaActiva = Number(
        resp.idcaja_activa || idcaja
      );
      contextoCajaActual.idaperturaActiva = 0;

      actualizarPermisoCajaSeleccionada(resp.caja || null);
      verificarAperturaCaja();
    },

    error: function (xhr) {
      contextoCajaActual.idcajaActiva = 0;
      $("#idcajaOperacion").val("");

      Swal.fire({
        icon: "error",
        title: "Error del servidor",
        text:
          (xhr.responseJSON && xhr.responseJSON.mensaje) ||
          "No se pudo seleccionar la caja.",
      });

      configurarCajaSinSeleccionar();
    },

    complete: function () {
      $("#idcajaOperacion").prop("disabled", false);
    },
  });
}

function obtenerCajaSeleccionada() {
  let idcaja = contextoCajaActual.idcajaActiva;

  if (contextoCajaActual.modo === "CAJA_UNICA") {
    idcaja = contextoCajaActual.idcajaUnica;
  }

  if (contextoCajaActual.modo === "MULTICAJA") {
    idcaja = Number($("#idcajaOperacion").val() || idcaja || 0);
  }

  return (
    contextoCajaActual.cajas.find(function (registro) {
      return Number(registro.idcaja) === Number(idcaja);
    }) || null
  );
}

function usuarioPuedeAbrirCaja(caja) {
  if (!caja) {
    return false;
  }

  return (
    Number(caja.puede_abrir || 0) === 1 &&
    Number(caja.puede_abrir_caja || 0) === 1
  );
}

function actualizarPermisoCajaSeleccionada(cajaForzada) {
  const caja = cajaForzada || obtenerCajaSeleccionada();

  if (!caja) {
    configurarCajaSinSeleccionar();
    return;
  }

  if (!usuarioPuedeAbrirCaja(caja)) {
    $("#btnAbrirCaja").prop("disabled", true);
    $("#montoApertura").prop("disabled", true);

    mostrarMensajeCaja(
      "warning",
      "Puede operar esta caja cuando se encuentre abierta, pero no tiene permiso para abrirla."
    );
    return;
  }

  $("#btnAbrirCaja")
    .prop("disabled", false)
    .html('<i class="fas fa-lock-open"></i> INICIAR CAJA');

  $("#montoApertura").prop("disabled", false);

  mostrarMensajeCaja(
    "info",
    "Caja autorizada. Puede registrar la apertura si todavía se encuentra cerrada."
  );
}

function configurarCajaSinSeleccionar() {
  $("#btnAbrirCaja").prop("disabled", true);
  $("#montoApertura").prop("disabled", true);

  mostrarMensajeCaja(
    "warning",
    "Seleccione primero la caja que operará."
  );
}

function mostrarMensajeCaja(tipo, mensaje) {
  const $mensaje = $("#mensajePermisoCaja");

  $mensaje
    .removeClass("d-none alert-info alert-warning alert-danger alert-success")
    .addClass("alert-" + tipo)
    .text(mensaje);
}

function ocultarBloquesContexto() {
  $("#bloqueContextoCaja").addClass("d-none");
  $("#grupoSeleccionCaja").addClass("d-none");
  $("#grupoCajaAutomatica").addClass("d-none");

  $("#mensajePermisoCaja")
    .addClass("d-none")
    .removeClass("alert-info alert-warning alert-danger alert-success");

  $("#idcajaOperacion").empty();
}

function mostrarModalCaja() {
  $("#modalCajaChica").modal({
    backdrop: "static",
    keyboard: false,
    show: true,
  });
}

function verificarAperturaCaja() {
  $.ajax({
    url: "Controllers/Cajachica.php?op=verificar_apertura",
    type: "GET",
    dataType: "json",
    cache: false,

    success: function (resp) {
      if (!resp || String(resp.status || "") === "error") {
        Swal.fire({
          icon: "error",
          title: "Error de caja",
          text:
            (resp && resp.message) ||
            "No se pudo verificar la apertura de caja.",
        });
        return;
      }

      if (resp.estado === "SIN_CAJA_SELECCIONADA") {
        contextoCajaActual.idcajaActiva = 0;
        contextoCajaActual.idaperturaActiva = 0;
        configurarCajaSinSeleccionar();
        mostrarModalCaja();
        return;
      }

      if (resp.caja && contextoCajaActual.modo !== "LEGACY") {
        contextoCajaActual.idcajaActiva = Number(
          resp.caja.idcaja || contextoCajaActual.idcajaActiva || 0
        );
      }

      if (resp.existe === true && resp.apertura) {
        contextoCajaActual.idaperturaActiva = Number(
          resp.apertura.idapertura || 0
        );

        $("#modalCajaChica").modal("hide");
        return;
      }

      contextoCajaActual.idaperturaActiva = 0;
      mostrarModalCaja();

      if (contextoCajaActual.modo === "LEGACY") {
        $("#btnAbrirCaja").prop("disabled", false);
        $("#montoApertura").prop("disabled", false);
      } else {
        actualizarPermisoCajaSeleccionada(resp.caja || null);
      }

      setTimeout(function () {
        if (!$("#montoApertura").prop("disabled")) {
          $("#montoApertura").trigger("focus");
        }
      }, 250);
    },

    error: function (xhr) {
      console.error("Error al verificar apertura:", xhr.responseText);

      Swal.fire({
        icon: "error",
        title: "Error del servidor",
        text: "No se pudo verificar la apertura de caja.",
      });
    },
  });
}

function abrirCaja() {
  if (
    contextoCajaActual.modo === "MULTICAJA" &&
    contextoCajaActual.idcajaActiva <= 0
  ) {
    Swal.fire({
      icon: "warning",
      title: "Seleccione una caja",
      text: "Debe seleccionar la caja que operará.",
    });
    return;
  }

  if (contextoCajaActual.modo !== "LEGACY") {
    const caja = obtenerCajaSeleccionada();

    if (!usuarioPuedeAbrirCaja(caja)) {
      Swal.fire({
        icon: "warning",
        title: "Sin permiso de apertura",
        text:
          "Puede operar esta caja cuando esté abierta, pero no tiene permiso para abrirla.",
      });
      return;
    }
  }

  const valorMonto = String($("#montoApertura").val() || "").trim();

  if (valorMonto === "") {
    Swal.fire({
      icon: "warning",
      title: "Monto requerido",
      text: "Ingrese el monto inicial de caja.",
    });
    return;
  }

  const monto = parseFloat(valorMonto);

  if (!Number.isFinite(monto) || monto < 0) {
    Swal.fire({
      icon: "warning",
      title: "Monto inválido",
      text: "Ingrese un monto válido.",
    });
    return;
  }

  const $boton = $("#btnAbrirCaja");

  $boton
    .prop("disabled", true)
    .html('<i class="fas fa-spinner fa-spin"></i> Abriendo...');

  $.ajax({
    url: "Controllers/Cajachica.php?op=guardar_apertura",
    type: "POST",
    dataType: "json",
    data: {
      monto: monto.toFixed(2),
    },

    success: function (resp) {
      if (resp && resp.status === "ok") {
        contextoCajaActual.idaperturaActiva = Number(
          resp.idapertura ||
            (resp.apertura && resp.apertura.idapertura) ||
            0
        );

        $("#modalCajaChica").modal("hide");

        Swal.fire({
          icon: resp.ya_estaba_abierta ? "info" : "success",
          title: resp.ya_estaba_abierta
            ? "La caja ya estaba abierta"
            : "Caja abierta correctamente",
          text: resp.message || "",
          timer: 1500,
          showConfirmButton: false,
        }).then(function () {
          window.location.reload();
        });

        return;
      }

      Swal.fire({
        icon: "error",
        title: "No se pudo abrir la caja",
        text:
          (resp && resp.message) ||
          "Ocurrió un error al registrar la apertura.",
      });
    },

    error: function (xhr) {
      console.error("Error al abrir caja:", xhr.responseText);

      Swal.fire({
        icon: "error",
        title: "Error del servidor",
        text:
          (xhr.responseJSON &&
            (xhr.responseJSON.message || xhr.responseJSON.error)) ||
          "No se pudo comunicar con el servidor.",
      });
    },

    complete: function () {
      $boton.html('<i class="fas fa-lock-open"></i> INICIAR CAJA');

      if (contextoCajaActual.modo === "LEGACY") {
        $boton.prop("disabled", false);
      } else {
        actualizarPermisoCajaSeleccionada();
      }
    },
  });
}
