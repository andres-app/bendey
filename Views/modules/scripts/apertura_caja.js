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
  ocultarFormularioApertura();
  ocultarMensajeCaja();
  cargarContextoCaja();

  $(document).on("click", "#btnAbrirCaja", function () {
    abrirCaja();
  });

  $(document).on("change", "#idcajaOperacion", function () {
    const idcaja = Number($(this).val() || 0);

    if (idcaja <= 0) {
      contextoCajaActual.idcajaActiva = 0;
      contextoCajaActual.idaperturaActiva = 0;

      actualizarIndicadorCajaSesion(null);
      configurarCajaSinSeleccionar();
      return;
    }

    seleccionarCajaOperacion(idcaja);
  });

  $(document).on("blur", "#montoApertura", function () {
    const valor = String($(this).val() || "").trim();

    if (valor === "") {
      return;
    }

    const monto = Number(valor);

    if (Number.isFinite(monto) && monto >= 0) {
      $(this).val(monto.toFixed(2));
    }
  });
});

/* ==========================================================
   CONTEXTO GENERAL
========================================================== */

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
      console.error(
        "Error al cargar contexto de caja:",
        xhr.responseText
      );

      aplicarContextoLegacy();
      verificarAperturaCaja();
    },
  });
}

function renderizarContextoCaja() {
  ocultarBloquesContexto();

  const cajaActual = contextoCajaActual.cajas.find(function (registro) {
    return Number(registro.idcaja) === Number(
      contextoCajaActual.idcajaActiva ||
      contextoCajaActual.idcajaPreparada ||
      0
    );
  });

  if (cajaActual) {
    actualizarIndicadorCajaSesion(cajaActual);
  }

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
  ocultarFormularioApertura();
  ocultarMensajeCaja();
}

function renderizarCajaUnica() {
  const caja = contextoCajaActual.cajas.find(function (registro) {
    return Number(registro.idcaja) === Number(
      contextoCajaActual.idcajaUnica
    );
  });

  contextoCajaActual.idcajaActiva = Number(
    contextoCajaActual.idcajaUnica || 0
  );

  actualizarIndicadorCajaSesion(caja || null);

  $("#grupoCajaAutomatica").removeClass("d-none");

  $("#nombreCajaAutomatica").text(
    caja ? String(caja.nombre || "") : "Caja no encontrada"
  );

  $("#codigoCajaAutomatica").text(
    caja ? String(caja.codigo || "") : "—"
  );
}

function renderizarMulticaja() {
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

    mostrarMensajeCaja(
      "warning",
      "No tienes una caja autorizada para operar."
    );

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
    const nombre = String(caja.nombre || "").trim();
    const codigo = String(caja.codigo || "").trim();

    let texto = nombre;

    if (codigo !== "") {
      texto += nombre !== "" ? " — " + codigo : codigo;
    }

    if (texto === "") {
      texto = "Caja #" + Number(caja.idcaja || 0);
    }

    $select.append(
      $("<option>", {
        value: Number(caja.idcaja),
        text: texto,
      })
    );
  });

  if (contextoCajaActual.idcajaActiva > 0) {
    $select.val(String(contextoCajaActual.idcajaActiva));
  }
}

/* ==========================================================
   SELECCIÓN DE CAJA
========================================================== */

function seleccionarCajaOperacion(idcaja) {
  $("#idcajaOperacion").prop("disabled", true);

  ocultarFormularioApertura();
  ocultarMensajeCaja();

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
        contextoCajaActual.idcajaPreparada = 0;
        contextoCajaActual.idaperturaActiva = 0;

        $("#idcajaOperacion").val("");
        actualizarIndicadorCajaSesion(null);

        configurarCajaSinSeleccionar();

        Swal.fire({
          icon: "error",
          title: "No se pudo seleccionar la caja",
          text:
            resp && resp.mensaje
              ? resp.mensaje
              : "Operación no válida.",
        });

        return;
      }

      if (resp.operativa !== true) {
        contextoCajaActual.idcajaActiva = 0;
        contextoCajaActual.idaperturaActiva = 0;

        $("#idcajaOperacion").val("");
        actualizarIndicadorCajaSesion(null);

        mostrarMensajeCaja(
          "warning",
          resp.mensaje ||
          "La caja todavía no se encuentra disponible para operar."
        );

        return;
      }

      contextoCajaActual.idcajaActiva = Number(
        resp.idcaja_activa || idcaja
      );

      contextoCajaActual.idcajaPreparada = Number(
        resp.idcaja_preparada ||
        resp.idcaja_activa ||
        idcaja
      );

      contextoCajaActual.idaperturaActiva = Number(
        resp.idapertura_activa || 0
      );

      const cajaSeleccionada =
        resp.caja ||
        contextoCajaActual.cajas.find(function (registro) {
          return Number(registro.idcaja) === Number(
            contextoCajaActual.idcajaActiva
          );
        }) ||
        null;

      actualizarIndicadorCajaSesion(cajaSeleccionada);
      verificarAperturaCaja();
    },

    error: function (xhr) {
      contextoCajaActual.idcajaActiva = 0;
      contextoCajaActual.idcajaPreparada = 0;
      contextoCajaActual.idaperturaActiva = 0;

      $("#idcajaOperacion").val("");
      actualizarIndicadorCajaSesion(null);

      configurarCajaSinSeleccionar();

      Swal.fire({
        icon: "error",
        title: "Error del servidor",
        text:
          (xhr.responseJSON && xhr.responseJSON.mensaje) ||
          "No se pudo seleccionar la caja.",
      });
    },

    complete: function () {
      if (
        Array.isArray(contextoCajaActual.cajas) &&
        contextoCajaActual.cajas.length > 0
      ) {
        $("#idcajaOperacion").prop("disabled", false);
      }
    },
  });
}

/* ==========================================================
   CAJA Y PERMISOS
========================================================== */

function obtenerCajaSeleccionada() {
  let idcaja = contextoCajaActual.idcajaActiva;

  if (contextoCajaActual.modo === "CAJA_UNICA") {
    idcaja = contextoCajaActual.idcajaUnica;
  }

  if (contextoCajaActual.modo === "MULTICAJA") {
    idcaja = Number(
      $("#idcajaOperacion").val() ||
      contextoCajaActual.idcajaActiva ||
      0
    );
  }

  return (
    contextoCajaActual.cajas.find(function (registro) {
      return Number(registro.idcaja) === Number(idcaja);
    }) || null
  );
}

function actualizarIndicadorCajaSesion(caja) {
  const $header = $("#textoCajaSesionHeader");
  const $dropdown = $("#textoCajaSesionDropdown");

  if (!caja || Number(caja.idcaja || 0) <= 0) {
    $header
      .text("Sin caja seleccionada")
      .removeClass("caja-activa")
      .addClass("caja-inactiva");

    $dropdown.text("Sin caja seleccionada");
    return;
  }

  const idcaja = Number(caja.idcaja || 0);
  const codigo = String(caja.codigo || "").trim();
  const nombre = String(caja.nombre || "").trim();

  let textoCaja = "";

  if (codigo !== "" && nombre !== "") {
    textoCaja = codigo + " - " + nombre;
  } else if (nombre !== "") {
    textoCaja = nombre;
  } else if (codigo !== "") {
    textoCaja = codigo;
  } else {
    textoCaja = "Caja #" + idcaja;
  }

  $header
    .text(textoCaja)
    .removeClass("caja-inactiva")
    .addClass("caja-activa");

  $dropdown.text(textoCaja);
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
    ocultarFormularioApertura();

    mostrarMensajeCaja(
      "warning",
      "Esta caja está cerrada y no tienes permiso para abrirla."
    );

    return;
  }

  ocultarMensajeCaja();
  mostrarFormularioApertura();
}

function configurarCajaSinSeleccionar() {
  ocultarFormularioApertura();
  ocultarMensajeCaja();
}

/* ==========================================================
   INTERFAZ DEL MODAL
========================================================== */

function mostrarFormularioApertura() {
  ocultarMensajeCaja();

  $("#bloqueAperturaCaja").removeClass("d-none");

  $("#montoApertura").prop("disabled", false);

  $("#btnAbrirCaja")
    .prop("disabled", false)
    .html(
      '<i class="fas fa-lock-open mr-2"></i> Abrir caja'
    );

  setTimeout(function () {
    if (
      $("#modalCajaChica").hasClass("show") &&
      !$("#montoApertura").prop("disabled")
    ) {
      $("#montoApertura").trigger("focus");
    }
  }, 180);
}

function ocultarFormularioApertura(limpiarMonto = true) {
  $("#bloqueAperturaCaja").addClass("d-none");

  $("#montoApertura").prop("disabled", true);

  $("#btnAbrirCaja")
    .prop("disabled", true)
    .html(
      '<i class="fas fa-lock-open mr-2"></i> Abrir caja'
    );

  if (limpiarMonto) {
    $("#montoApertura").val("");
  }
}

function mostrarMensajeCaja(tipo, mensaje) {
  const $mensaje = $("#mensajePermisoCaja");

  $mensaje
    .removeClass(
      "d-none alert-info alert-warning alert-danger alert-success"
    )
    .addClass("alert-" + tipo)
    .text(mensaje);
}

function ocultarMensajeCaja() {
  $("#mensajePermisoCaja")
    .addClass("d-none")
    .removeClass(
      "alert-info alert-warning alert-danger alert-success"
    )
    .text("");
}

function ocultarBloquesContexto() {
  $("#grupoSeleccionCaja").addClass("d-none");
  $("#grupoCajaAutomatica").addClass("d-none");

  $("#idcajaOperacion").empty();

  ocultarFormularioApertura();
  ocultarMensajeCaja();
}

function mostrarModalCaja() {
  const $modal = $("#modalCajaChica");

  if ($modal.hasClass("show")) {
    return;
  }

  $modal.modal({
    backdrop: "static",
    keyboard: false,
    show: true,
  });
}

/* ==========================================================
   VERIFICACIÓN DE APERTURA
========================================================== */

function verificarAperturaCaja() {
  ocultarFormularioApertura();
  ocultarMensajeCaja();

  $.ajax({
    url: "Controllers/Cajachica.php?op=verificar_apertura",
    type: "GET",
    dataType: "json",
    cache: false,

    success: function (resp) {
      if (!resp || String(resp.status || "") === "error") {
        ocultarFormularioApertura();

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

        actualizarIndicadorCajaSesion(null);
        configurarCajaSinSeleccionar();
        mostrarModalCaja();

        return;
      }

      if (resp.caja && contextoCajaActual.modo !== "LEGACY") {
        contextoCajaActual.idcajaActiva = Number(
          resp.caja.idcaja ||
          contextoCajaActual.idcajaActiva ||
          0
        );

        actualizarIndicadorCajaSesion(resp.caja);
      }

      /*
       * Si la caja ya está abierta, se cierra el modal.
       */
      if (resp.existe === true && resp.apertura) {
        contextoCajaActual.idaperturaActiva = Number(
          resp.apertura.idapertura || 0
        );

        ocultarFormularioApertura();
        $("#modalCajaChica").modal("hide");

        return;
      }

      /*
       * La caja está cerrada.
       * Recién aquí aparece el campo de monto.
       */
      contextoCajaActual.idaperturaActiva = 0;

      mostrarModalCaja();

      if (contextoCajaActual.modo === "LEGACY") {
        mostrarFormularioApertura();
        return;
      }

      actualizarPermisoCajaSeleccionada(resp.caja || null);
    },

    error: function (xhr) {
      console.error(
        "Error al verificar apertura:",
        xhr.responseText
      );

      ocultarFormularioApertura();

      Swal.fire({
        icon: "error",
        title: "Error del servidor",
        text: "No se pudo verificar la apertura de caja.",
      });
    },
  });
}

/* ==========================================================
   APERTURA
========================================================== */

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
        text: "No tiene permiso para abrir esta caja.",
      });

      return;
    }
  }

  const valorMonto = String(
    $("#montoApertura").val() || ""
  ).trim();

  if (valorMonto === "") {
    Swal.fire({
      icon: "warning",
      title: "Monto requerido",
      text: "Ingrese el efectivo inicial.",
    });

    return;
  }

  const monto = Number(valorMonto);

  if (!Number.isFinite(monto) || monto < 0) {
    Swal.fire({
      icon: "warning",
      title: "Monto inválido",
      text: "Ingrese un monto válido.",
    });

    return;
  }

  const $boton = $("#btnAbrirCaja");
  let aperturaCompletada = false;

  $boton
    .prop("disabled", true)
    .html(
      '<i class="fas fa-spinner fa-spin mr-2"></i> Abriendo...'
    );

  $.ajax({
    url: "Controllers/Cajachica.php?op=guardar_apertura",
    type: "POST",
    dataType: "json",
    data: {
      monto: monto.toFixed(2),
    },

    success: function (resp) {
      if (resp && resp.status === "ok") {
        aperturaCompletada = true;

        contextoCajaActual.idaperturaActiva = Number(
          resp.idapertura ||
          (resp.apertura && resp.apertura.idapertura) ||
          0
        );

        ocultarFormularioApertura(false);
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
      console.error(
        "Error al abrir caja:",
        xhr.responseText
      );

      Swal.fire({
        icon: "error",
        title: "Error del servidor",
        text:
          (xhr.responseJSON &&
            (
              xhr.responseJSON.message ||
              xhr.responseJSON.error
            )) ||
          "No se pudo comunicar con el servidor.",
      });
    },

    complete: function () {
      if (aperturaCompletada) {
        return;
      }

      $boton.html(
        '<i class="fas fa-lock-open mr-2"></i> Abrir caja'
      );

      if (contextoCajaActual.modo === "LEGACY") {
        $boton.prop("disabled", false);
        return;
      }

      actualizarPermisoCajaSeleccionada();
    },
  });
}
