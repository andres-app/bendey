// Views/modules/scripts/cajachica.js

let contextoCajaActual = {
  modo: "LEGACY",
  idapertura: 0,
  estado: "SIN_APERTURA",
  efectivoEsperado: 0,
};

let cajaRequestActual = null;
let cajaCargaEnCurso = false;

$(document).ready(function () {
  inicializarCajaChica();
});

function inicializarCajaChica() {
  inicializarSelectorFechasCaja();

  $("#fecha_inicio, #fecha_fin, #idusuario").on("change", function () {
    sincronizarFechasCajaVisuales();
    marcarRangoRapidoSegunFechas();
    actualizarEtiquetaPeriodo();
    cargarCaja();
  });

  $("#cajaRangosRapidos").on("click", ".caja-range-btn", function () {
    aplicarRangoRapido(String($(this).data("range") || "today"));
  });

  actualizarEtiquetaPeriodo();
  cargarCaja();
}

/*
|--------------------------------------------------------------------------
| SELECTOR VISUAL DE FECHAS
|--------------------------------------------------------------------------
| Replica el patrón de newsale3.php: el valor real permanece en un input
| oculto YYYY-MM-DD y el usuario interactúa con un calendario consistente
| en escritorio, iOS y Android.
*/
const ESTADO_FECHA_CAJA = {
  inputId: null,
  vista: null,
};

function fechaISOValidaCaja(valor) {
  return /^\d{4}-\d{2}-\d{2}$/.test(String(valor || "").trim());
}

function fechaISOADateLocalCaja(valor) {
  if (!fechaISOValidaCaja(valor)) {
    return null;
  }

  const [anio, mes, dia] = String(valor).split("-").map(Number);
  const fecha = new Date(anio, mes - 1, dia);

  if (
    fecha.getFullYear() !== anio ||
    fecha.getMonth() !== mes - 1 ||
    fecha.getDate() !== dia
  ) {
    return null;
  }

  return fecha;
}

function formatearFechaCaja(valor, modo = "corto") {
  const fecha = fechaISOADateLocalCaja(valor);

  if (!fecha) {
    return "";
  }

  const opciones = modo === "largo"
    ? { day: "numeric", month: "long", year: "numeric" }
    : { day: "2-digit", month: "short", year: "numeric" };

  return new Intl.DateTimeFormat("es-PE", opciones)
    .format(fecha)
    .replace(/\.$/, "");
}

function obtenerMaxFechaCajaISO(inputId = null) {
  const id = inputId || ESTADO_FECHA_CAJA.inputId || "fecha_fin";
  const $input = $(`#${id}`);
  const maximo = String($input.attr("data-max") || fechaLocalISO(new Date())).trim();

  return fechaISOValidaCaja(maximo) ? maximo : fechaLocalISO(new Date());
}

function sincronizarFechaCajaVisual(inputId) {
  const $input = $(`#${inputId}`);

  if (!$input.length) {
    return;
  }

  let valor = String($input.val() || "").trim();
  const maximo = obtenerMaxFechaCajaISO(inputId);

  if (!fechaISOValidaCaja(valor)) {
    valor = maximo;
    $input.val(valor);
  }

  if (valor > maximo) {
    valor = maximo;
    $input.val(valor);
  }

  const destino = inputId === "fecha_inicio"
    ? "#fechaInicioCajaTexto"
    : "#fechaFinCajaTexto";

  $(destino).text(formatearFechaCaja(valor, "corto"));
}

function sincronizarFechasCajaVisuales() {
  sincronizarFechaCajaVisual("fecha_inicio");
  sincronizarFechaCajaVisual("fecha_fin");

  if (ESTADO_FECHA_CAJA.inputId) {
    const valor = String($(`#${ESTADO_FECHA_CAJA.inputId}`).val() || "").trim();
    $("#fechaCajaSeleccionResumen").text(formatearFechaCaja(valor, "largo"));
  }
}

function renderizarCalendarioFechaCaja() {
  const contenedor = document.getElementById("fechaCajaDias");
  const inputId = ESTADO_FECHA_CAJA.inputId;

  if (!contenedor || !inputId) {
    return;
  }

  const maximoISO = obtenerMaxFechaCajaISO(inputId);
  const valorSeleccionado = String($(`#${inputId}`).val() || maximoISO).trim();
  const fechaSeleccionada = fechaISOADateLocalCaja(valorSeleccionado)
    || fechaISOADateLocalCaja(maximoISO)
    || new Date();
  const fechaMaxima = fechaISOADateLocalCaja(maximoISO) || new Date();

  if (!(ESTADO_FECHA_CAJA.vista instanceof Date)) {
    ESTADO_FECHA_CAJA.vista = new Date(
      fechaSeleccionada.getFullYear(),
      fechaSeleccionada.getMonth(),
      1
    );
  }

  const limiteMes = new Date(fechaMaxima.getFullYear(), fechaMaxima.getMonth(), 1);

  if (ESTADO_FECHA_CAJA.vista > limiteMes) {
    ESTADO_FECHA_CAJA.vista = new Date(limiteMes);
  }

  const anio = ESTADO_FECHA_CAJA.vista.getFullYear();
  const mes = ESTADO_FECHA_CAJA.vista.getMonth();
  const primerDia = new Date(anio, mes, 1);
  const ultimoDiaMes = new Date(anio, mes + 1, 0).getDate();
  const desplazamiento = (primerDia.getDay() + 6) % 7;
  const hoyISO = fechaLocalISO(new Date());

  $("#fechaCajaMesTitulo").text(
    new Intl.DateTimeFormat("es-PE", { month: "long", year: "numeric" }).format(primerDia)
  );

  const fragmento = document.createDocumentFragment();

  for (let celda = 0; celda < 42; celda += 1) {
    const numeroDia = celda - desplazamiento + 1;

    if (numeroDia < 1 || numeroDia > ultimoDiaMes) {
      const vacio = document.createElement("span");
      vacio.className = "caja-calendario-dia is-empty";
      vacio.setAttribute("aria-hidden", "true");
      fragmento.appendChild(vacio);
      continue;
    }

    const fechaCelda = new Date(anio, mes, numeroDia);
    const fechaISO = fechaLocalISO(fechaCelda);
    const esFutura = fechaCelda > fechaMaxima;
    const esSeleccionada = fechaISO === valorSeleccionado;
    const esHoy = fechaISO === hoyISO;
    const boton = document.createElement("button");

    boton.type = "button";
    boton.className = "caja-calendario-dia";
    boton.textContent = String(numeroDia);
    boton.dataset.fecha = fechaISO;
    boton.setAttribute("role", "gridcell");
    boton.setAttribute("aria-label", formatearFechaCaja(fechaISO, "largo"));

    if (esSeleccionada) {
      boton.classList.add("is-selected");
      boton.setAttribute("aria-selected", "true");
    }

    if (esHoy) {
      boton.classList.add("is-today");
    }

    if (esFutura) {
      boton.classList.add("is-disabled");
      boton.disabled = true;
      boton.setAttribute("aria-disabled", "true");
    }

    fragmento.appendChild(boton);
  }

  contenedor.replaceChildren(fragmento);

  const siguiente = document.getElementById("btnFechaCajaSiguiente");

  if (siguiente) {
    const mesSiguiente = new Date(anio, mes + 1, 1);
    const deshabilitar = mesSiguiente > limiteMes;
    siguiente.disabled = deshabilitar;
    siguiente.setAttribute("aria-disabled", deshabilitar ? "true" : "false");
  }

  $("#fechaCajaSeleccionResumen").text(
    formatearFechaCaja(valorSeleccionado, "largo")
  );
}

function abrirSelectorFechaCaja(inputId, titulo) {
  const $input = $(`#${inputId}`);

  if (!$input.length) {
    return;
  }

  const maximo = obtenerMaxFechaCajaISO(inputId);
  const valor = String($input.val() || maximo).trim();
  const fecha = fechaISOADateLocalCaja(valor)
    || fechaISOADateLocalCaja(maximo)
    || new Date();

  ESTADO_FECHA_CAJA.inputId = inputId;
  ESTADO_FECHA_CAJA.vista = new Date(fecha.getFullYear(), fecha.getMonth(), 1);

  $("#modalFechaCajaTitulo").text(titulo || "Seleccionar fecha");
  renderizarCalendarioFechaCaja();
  $("#modalFechaCaja").modal("show");
}

function inicializarSelectorFechasCaja() {
  const hoy = fechaLocalISO(new Date());

  ["fecha_inicio", "fecha_fin"].forEach(function (inputId) {
    const $input = $(`#${inputId}`);

    if (!$input.length) {
      return;
    }

    $input.attr("data-max", hoy);

    if (!fechaISOValidaCaja($input.val())) {
      $input.val(hoy);
    }
  });

  sincronizarFechasCajaVisuales();

  $(document)
    .off("click.cajaFechaAbrir", "[data-caja-fecha]")
    .on("click.cajaFechaAbrir", "[data-caja-fecha]", function () {
      abrirSelectorFechaCaja(
        String($(this).attr("data-caja-fecha") || ""),
        String($(this).attr("data-caja-fecha-titulo") || "Seleccionar fecha")
      );
    })
    .off("click.cajaFechaDia", "#fechaCajaDias [data-fecha]")
    .on("click.cajaFechaDia", "#fechaCajaDias [data-fecha]", function () {
      if (this.disabled || !ESTADO_FECHA_CAJA.inputId) {
        return;
      }

      const fecha = String(this.dataset.fecha || "").trim();

      if (!fechaISOValidaCaja(fecha)) {
        return;
      }

      $(`#${ESTADO_FECHA_CAJA.inputId}`)
        .val(fecha)
        .trigger("change");

      $("#modalFechaCaja").modal("hide");
    })
    .off("click.cajaFechaAnterior", "#btnFechaCajaAnterior")
    .on("click.cajaFechaAnterior", "#btnFechaCajaAnterior", function () {
      if (!(ESTADO_FECHA_CAJA.vista instanceof Date)) {
        return;
      }

      ESTADO_FECHA_CAJA.vista = new Date(
        ESTADO_FECHA_CAJA.vista.getFullYear(),
        ESTADO_FECHA_CAJA.vista.getMonth() - 1,
        1
      );
      renderizarCalendarioFechaCaja();
    })
    .off("click.cajaFechaSiguiente", "#btnFechaCajaSiguiente")
    .on("click.cajaFechaSiguiente", "#btnFechaCajaSiguiente", function () {
      if (this.disabled || !(ESTADO_FECHA_CAJA.vista instanceof Date)) {
        return;
      }

      ESTADO_FECHA_CAJA.vista = new Date(
        ESTADO_FECHA_CAJA.vista.getFullYear(),
        ESTADO_FECHA_CAJA.vista.getMonth() + 1,
        1
      );
      renderizarCalendarioFechaCaja();
    })
    .off("click.cajaFechaHoy", "#btnFechaCajaHoy")
    .on("click.cajaFechaHoy", "#btnFechaCajaHoy", function () {
      if (!ESTADO_FECHA_CAJA.inputId) {
        return;
      }

      const fecha = obtenerMaxFechaCajaISO(ESTADO_FECHA_CAJA.inputId);
      $(`#${ESTADO_FECHA_CAJA.inputId}`)
        .val(fecha)
        .trigger("change");
      $("#modalFechaCaja").modal("hide");
    });

  $("#modalFechaCaja")
    .off("shown.bs.modal.cajaFecha")
    .on("shown.bs.modal.cajaFecha", function () {
      const seleccionado = this.querySelector(
        ".caja-calendario-dia.is-selected:not(:disabled)"
      );

      if (seleccionado) {
        seleccionado.focus({ preventScroll: true });
      }
    });
}

/*
|--------------------------------------------------------------------------
| RANGOS RÁPIDOS
|--------------------------------------------------------------------------
*/
function aplicarRangoRapido(rango) {
  const hoy = new Date();
  const fechaFin = fechaLocalISO(hoy);
  let fechaInicio = fechaFin;

  if (rango === "7days") {
    const desde = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() - 6);
    fechaInicio = fechaLocalISO(desde);
  } else if (rango === "month") {
    const desde = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    fechaInicio = fechaLocalISO(desde);
  }

  $("#fecha_inicio").val(fechaInicio);
  $("#fecha_fin").val(fechaFin);
  sincronizarFechasCajaVisuales();

  $(".caja-range-btn").removeClass("is-active");
  $(`.caja-range-btn[data-range="${rango}"]`).addClass("is-active");

  actualizarEtiquetaPeriodo();
  cargarCaja();
}

function marcarRangoRapidoSegunFechas() {
  const inicio = $("#fecha_inicio").val();
  const fin = $("#fecha_fin").val();
  const hoy = new Date();
  const hoyIso = fechaLocalISO(hoy);
  const haceSeisDias = fechaLocalISO(new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() - 6));
  const inicioMes = fechaLocalISO(new Date(hoy.getFullYear(), hoy.getMonth(), 1));

  $(".caja-range-btn").removeClass("is-active");

  if (inicio === hoyIso && fin === hoyIso) {
    $('.caja-range-btn[data-range="today"]').addClass("is-active");
  } else if (inicio === haceSeisDias && fin === hoyIso) {
    $('.caja-range-btn[data-range="7days"]').addClass("is-active");
  } else if (inicio === inicioMes && fin === hoyIso) {
    $('.caja-range-btn[data-range="month"]').addClass("is-active");
  }
}

function actualizarEtiquetaPeriodo() {
  const inicio = $("#fecha_inicio").val();
  const fin = $("#fecha_fin").val();

  if (!inicio || !fin) {
    $("#periodoCajaLabel").text("Periodo seleccionado");
    return;
  }

  if (inicio === fin) {
    $("#periodoCajaLabel").text(formatearFechaCorta(inicio));
    return;
  }

  $("#periodoCajaLabel").text(`${formatearFechaCorta(inicio)} — ${formatearFechaCorta(fin)}`);
}

/*
|--------------------------------------------------------------------------
| CARGAR RESUMEN
|--------------------------------------------------------------------------
*/
function cargarCaja(opciones = {}) {
  const fechaInicio = $("#fecha_inicio").val();
  const fechaFin = $("#fecha_fin").val();
  const idusuario = $("#idusuario").val();

  if (!fechaInicio || !fechaFin) {
    return;
  }

  if (fechaInicio > fechaFin) {
    Swal.fire({
      icon: "warning",
      title: "Revisa el periodo",
      text: "La fecha de inicio no puede ser posterior a la fecha fin.",
      confirmButtonColor: "#00a46a",
    });
    return;
  }

  if (cajaRequestActual && cajaRequestActual.readyState !== 4) {
    cajaRequestActual.abort();
  }

  setCajaLoading(true);
  cajaCargaEnCurso = true;

  cajaRequestActual = $.ajax({
    url: "Controllers/Cajachica.php?op=resumen",
    type: "GET",
    dataType: "json",
    cache: opciones.forzar ? false : true,
    data: {
      fecha_inicio: fechaInicio,
      fecha_fin: fechaFin,
      idusuario: idusuario,
      _ts: opciones.forzar ? Date.now() : undefined,
    },
    success: function (resp) {
      if (resp.status !== "ok") {
        Swal.fire({
          icon: "error",
          title: "No se pudo cargar la caja",
          text: resp.message || "Ocurrió un problema al consultar los movimientos.",
          confirmButtonColor: "#00a46a",
        });
        return;
      }

      contextoCajaActual = {
        modo: String(resp.modo || "LEGACY").toUpperCase(),
        idapertura:
          Number.parseInt(resp.apertura?.idapertura, 10) || 0,
        estado: String(resp.estado || "SIN_APERTURA").toUpperCase(),
        efectivoEsperado:
          (parseFloat(resp.apertura?.monto_apertura) || 0) +
          (parseFloat(resp.totales?.efectivo) || 0) -
          (parseFloat(resp.totales?.egresos_efectivo) || 0),
      };

      const detalle = Array.isArray(resp.detalle) ? resp.detalle : [];

      renderTabla(detalle, resp.apertura || null);
      renderTotales(resp.totales || {}, resp.apertura || null);
      actualizarEstadoCaja(resp.estado);
      actualizarMetaCaja(detalle);
      actualizarEtiquetaPeriodo();
      marcarRangoRapidoSegunFechas();
    },
    error: function (xhr, estado) {
      if (estado === "abort") {
        return;
      }

      console.error("Error al cargar caja:", xhr.responseText);

      Swal.fire({
        icon: "error",
        title: "Error de conexión",
        text: "No se pudo actualizar el resumen de caja.",
        confirmButtonColor: "#00a46a",
      });
    },
    complete: function () {
      cajaCargaEnCurso = false;
      setCajaLoading(false);
    },
  });
}

function setCajaLoading(activo) {
  const overlay = $("#cajaLoading");
  const icono = $("#iconActualizarCaja");
  const boton = $("#btnActualizarCaja");

  if (activo) {
    overlay.removeClass("tw-hidden").addClass("tw-flex");
    icono.addClass("fa-spin");
    boton.prop("disabled", true).addClass("tw-opacity-70 tw-cursor-wait");
    return;
  }

  overlay.addClass("tw-hidden").removeClass("tw-flex");
  icono.removeClass("fa-spin");
  boton.prop("disabled", false).removeClass("tw-opacity-70 tw-cursor-wait");
}

function actualizarMetaCaja(detalle) {
  const cantidad = Array.isArray(detalle) ? detalle.length : 0;
  const texto = cantidad === 1 ? "1 movimiento" : `${cantidad} movimientos`;
  $("#tablaCajaMeta").text(texto);

  const ahora = new Date();
  $("#ultimaActualizacionCaja").text(
    `Actualizado ${ahora.toLocaleTimeString("es-PE", { hour: "2-digit", minute: "2-digit" })}`
  );
}

/*
|--------------------------------------------------------------------------
| ESTADO DE CAJA
|--------------------------------------------------------------------------
*/
function actualizarEstadoCaja(estado) {
  const boton = $("#btnCerrarCaja");
  const badge = $("#estadoCajaBadge");
  const texto = $("#estadoCajaTexto");
  const dot = $("#estadoCajaDot");

  badge.removeClass(
    "tw-bg-white/15 tw-border-white/20 tw-bg-rose-500/20 tw-border-rose-200/30 tw-bg-amber-400/20 tw-border-amber-200/30"
  );
  dot.removeClass("tw-bg-emerald-300 tw-bg-rose-300 tw-bg-amber-300");

  if (estado === "ABIERTA") {
    boton.prop("disabled", false);
    texto.text("Caja abierta");
    badge.addClass("tw-bg-white/15 tw-border-white/20");
    dot.addClass("tw-bg-emerald-300");
    return;
  }

  if (estado === "CERRADA") {
    boton.prop("disabled", true);
    texto.text("Caja cerrada");
    badge.addClass("tw-bg-rose-500/20 tw-border-rose-200/30");
    dot.addClass("tw-bg-rose-300");
    return;
  }

  boton.prop("disabled", true);

  if (estado === "SIN_CAJA_SELECCIONADA") {
    texto.text("Selecciona una caja");
  } else {
    texto.text("Sin apertura");
  }

  badge.addClass("tw-bg-amber-400/20 tw-border-amber-200/30");
  dot.addClass("tw-bg-amber-300");
}

/*
|--------------------------------------------------------------------------
| EXPORTACIONES
|--------------------------------------------------------------------------
*/
function exportarExcel() {
  abrirReporteCaja("Reports/ExcelCajaChica.php");
}

function exportarPDF() {
  abrirReporteCaja("Reports/caja_chica.php");
}

function abrirReporteCaja(ruta) {
  const parametros = new URLSearchParams({
    fecha_inicio: $("#fecha_inicio").val() || "",
    fecha_fin: $("#fecha_fin").val() || "",
    idusuario: $("#idusuario").val() || "",
    idapertura: String(contextoCajaActual.idapertura || 0),
  });

  window.open(`${ruta}?${parametros.toString()}`, "_blank");
}

/*
|--------------------------------------------------------------------------
| TABLA + TARJETAS MÓVILES
|--------------------------------------------------------------------------
*/
function renderTabla(data, apertura) {
  const filas = agruparMovimientosCaja(data);
  const registros = [];
  const montoApertura = parseFloat(apertura?.monto_apertura) || 0;

  if (apertura) {
    registros.push({
      tipo: "APERTURA DE CAJA",
      clase: "apertura",
      icono: "fa-door-open",
      efectivo: montoApertura,
      tarjeta: null,
      transferencia: null,
      billeteras: null,
      total: montoApertura,
    });
  }

  Object.keys(filas)
    .sort((a, b) => a.localeCompare(b, "es"))
    .forEach(function (tipo) {
      const fila = filas[tipo];
      const billeteras = fila.yape + fila.plin;
      const total = fila.efectivo + fila.tarjeta + fila.transferencia + billeteras + fila.otros;

      registros.push({
        tipo: tipo,
        clase: total < 0 ? "egreso" : "movimiento",
        icono: total < 0 ? "fa-arrow-up" : "fa-receipt",
        efectivo: fila.efectivo,
        tarjeta: fila.tarjeta,
        transferencia: fila.transferencia,
        billeteras: billeteras,
        total: total,
      });
    });

  if (apertura?.estado === "CERRADA") {
    registros.push({
      tipo: "CIERRE DE CAJA",
      clase: "cierre",
      icono: "fa-check",
      cierre: true,
    });
  }

  renderTablaDesktopCaja(registros);
  renderListaMovilCaja(registros);
}

function agruparMovimientosCaja(data) {
  const filas = {};

  if (!Array.isArray(data)) {
    return filas;
  }

  data.forEach(function (registro) {
    const tipo = registro.tipo_comprobante || "SIN COMPROBANTE";

    if (!filas[tipo]) {
      filas[tipo] = {
        efectivo: 0,
        tarjeta: 0,
        transferencia: 0,
        yape: 0,
        plin: 0,
        otros: 0,
      };
    }

    const monto = parseFloat(registro.total) || 0;
    const forma = String(registro.forma_pago || "").toLowerCase().trim();

    if (forma.includes("efectivo")) {
      filas[tipo].efectivo += monto;
    } else if (forma.includes("tarjeta") || forma.includes("izipay")) {
      filas[tipo].tarjeta += monto;
    } else if (forma.includes("transfer")) {
      filas[tipo].transferencia += monto;
    } else if (forma.includes("yape")) {
      filas[tipo].yape += monto;
    } else if (forma.includes("plin")) {
      filas[tipo].plin += monto;
    } else {
      filas[tipo].otros += monto;
    }
  });

  return filas;
}

function renderTablaDesktopCaja(registros) {
  let html = "";

  registros.forEach(function (registro) {
    if (registro.cierre) {
      html += `
        <tr class="tw-bg-slate-50">
          <td class="tw-px-5 tw-py-4">
            <div class="tw-flex tw-items-center tw-gap-3">
              <span class="tw-flex tw-h-9 tw-w-9 tw-items-center tw-justify-center tw-rounded-xl tw-bg-slate-200 tw-text-slate-600"><i class="fas ${registro.icono} tw-text-xs"></i></span>
              <span class="tw-text-sm tw-font-medium tw-text-slate-700">${escaparHtml(registro.tipo)}</span>
            </div>
          </td>
          <td colspan="4" class="tw-px-4 tw-py-4 tw-text-center tw-text-sm tw-text-slate-500">Caja cerrada</td>
          <td class="tw-px-5 tw-py-4 tw-text-right tw-text-sm tw-font-semibold tw-text-tique-600"><i class="fas fa-check-circle"></i></td>
        </tr>`;
      return;
    }

    const esApertura = registro.clase === "apertura";
    const esEgreso = registro.clase === "egreso";
    const filaClase = esApertura ? "tw-bg-tique-50/70" : esEgreso ? "tw-bg-rose-50/50" : "hover:tw-bg-slate-50/80";
    const iconoClase = esApertura
      ? "tw-bg-tique-100 tw-text-tique-700"
      : esEgreso
      ? "tw-bg-rose-100 tw-text-rose-600"
      : "tw-bg-slate-100 tw-text-slate-500";

    html += `
      <tr class="${filaClase} tw-transition-colors">
        <td class="tw-px-5 tw-py-4">
          <div class="tw-flex tw-items-center tw-gap-3">
            <span class="tw-flex tw-h-9 tw-w-9 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-xl ${iconoClase}"><i class="fas ${registro.icono} tw-text-xs"></i></span>
            <div class="tw-min-w-0">
              <div class="tw-truncate tw-text-sm tw-font-medium tw-text-slate-800">${escaparHtml(registro.tipo)}</div>
              ${esApertura ? '<div class="tw-mt-0.5 tw-text-[11px] tw-text-tique-600">Saldo inicial</div>' : ''}
            </div>
          </div>
        </td>
        <td class="tw-px-4 tw-py-4 tw-text-right tw-text-sm tw-text-slate-600">${formatearCeldaMovimiento(registro.efectivo)}</td>
        <td class="tw-px-4 tw-py-4 tw-text-right tw-text-sm tw-text-slate-600">${formatearCeldaMovimiento(registro.tarjeta)}</td>
        <td class="tw-px-4 tw-py-4 tw-text-right tw-text-sm tw-text-slate-600">${formatearCeldaMovimiento(registro.transferencia)}</td>
        <td class="tw-px-4 tw-py-4 tw-text-right tw-text-sm tw-text-slate-600">${formatearCeldaMovimiento(registro.billeteras)}</td>
        <td class="tw-px-5 tw-py-4 tw-text-right tw-text-sm tw-font-semibold ${esEgreso ? "tw-text-rose-600" : esApertura ? "tw-text-tique-700" : "tw-text-slate-900"}">${formatearMovimiento(registro.total)}</td>
      </tr>`;
  });

  if (!html) {
    html = `
      <tr>
        <td colspan="6" class="tw-px-5 tw-py-14 tw-text-center">
          <div class="tw-mx-auto tw-flex tw-h-12 tw-w-12 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-slate-100 tw-text-slate-400"><i class="fas fa-inbox"></i></div>
          <div class="tw-mt-3 tw-text-sm tw-font-medium tw-text-slate-600">No hay movimientos</div>
          <div class="tw-mt-1 tw-text-xs tw-text-slate-400">Prueba con otro periodo o vendedor.</div>
        </td>
      </tr>`;
  }

  $("#tablaCaja tbody").html(html);
}

function renderListaMovilCaja(registros) {
  let html = "";

  registros.forEach(function (registro) {
    if (registro.cierre) {
      html += `
        <div class="tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
          <div class="tw-flex tw-items-center tw-justify-between tw-gap-3">
            <div class="tw-flex tw-items-center tw-gap-3">
              <span class="tw-flex tw-h-9 tw-w-9 tw-items-center tw-justify-center tw-rounded-xl tw-bg-slate-200 tw-text-slate-600"><i class="fas fa-check"></i></span>
              <div><div class="tw-text-sm tw-font-medium tw-text-slate-800">Cierre de caja</div><div class="tw-text-xs tw-text-slate-500">Caja cerrada</div></div>
            </div>
            <i class="fas fa-check-circle tw-text-tique-600"></i>
          </div>
        </div>`;
      return;
    }

    const esApertura = registro.clase === "apertura";
    const esEgreso = registro.clase === "egreso";
    const cardClase = esApertura
      ? "tw-border-tique-200 tw-bg-tique-50/60"
      : esEgreso
      ? "tw-border-rose-200 tw-bg-rose-50/50"
      : "tw-border-slate-200 tw-bg-white";

    html += `
      <div class="tw-rounded-2xl tw-border ${cardClase} tw-p-4">
        <div class="tw-flex tw-items-start tw-justify-between tw-gap-3">
          <div class="tw-min-w-0">
            <div class="tw-truncate tw-text-sm tw-font-medium tw-text-slate-800">${escaparHtml(registro.tipo)}</div>
            <div class="tw-mt-1 tw-text-[11px] ${esApertura ? "tw-text-tique-600" : esEgreso ? "tw-text-rose-500" : "tw-text-slate-400"}">${esApertura ? "Saldo inicial" : esEgreso ? "Movimiento de salida" : "Movimiento registrado"}</div>
          </div>
          <div class="tw-whitespace-nowrap tw-text-base tw-font-semibold ${esEgreso ? "tw-text-rose-600" : esApertura ? "tw-text-tique-700" : "tw-text-slate-900"}">${formatearMovimiento(registro.total)}</div>
        </div>

        <div class="tw-mt-4 tw-grid tw-grid-cols-2 tw-gap-x-4 tw-gap-y-3 tw-border-t tw-border-slate-200/70 tw-pt-3">
          ${crearDatoMovilCaja("Efectivo", registro.efectivo, "fa-money-bill-wave")}
          ${crearDatoMovilCaja("Tarjeta", registro.tarjeta, "fa-credit-card")}
          ${crearDatoMovilCaja("Transferencia", registro.transferencia, "fa-exchange-alt")}
          ${crearDatoMovilCaja("Yape / Plin", registro.billeteras, "fa-mobile-alt")}
        </div>
      </div>`;
  });

  if (!html) {
    html = `
      <div class="tw-rounded-2xl tw-border tw-border-dashed tw-border-slate-200 tw-p-9 tw-text-center">
        <div class="tw-mx-auto tw-flex tw-h-12 tw-w-12 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-slate-100 tw-text-slate-400"><i class="fas fa-inbox"></i></div>
        <div class="tw-mt-3 tw-text-sm tw-font-medium tw-text-slate-600">No hay movimientos</div>
        <div class="tw-mt-1 tw-text-xs tw-text-slate-400">Prueba con otro periodo o vendedor.</div>
      </div>`;
  }

  $("#cajaMobileList").html(html);
}

function crearDatoMovilCaja(etiqueta, monto, icono) {
  return `
    <div class="tw-min-w-0">
      <div class="tw-flex tw-items-center tw-gap-1.5 tw-text-[11px] tw-text-slate-400"><i class="fas ${icono} tw-w-3"></i><span>${etiqueta}</span></div>
      <div class="tw-mt-1 tw-text-sm tw-font-medium tw-text-slate-700">${formatearCeldaMovimiento(monto)}</div>
    </div>`;
}

function formatearCeldaMovimiento(monto) {
  if (monto === null || typeof monto === "undefined") {
    return '<span class="tw-text-slate-300">—</span>';
  }

  const numero = parseFloat(monto) || 0;

  if (Math.abs(numero) < 0.000001) {
    return '<span class="tw-text-slate-300">S/ 0.00</span>';
  }

  return formatearMovimiento(numero);
}

/*
|--------------------------------------------------------------------------
| TOTALES
|--------------------------------------------------------------------------
*/
function renderTotales(totales, apertura) {
  const ventasBrutas = parseFloat(totales.ventas_brutas) || 0;
  const notasCredito = parseFloat(totales.notas_credito) || 0;
  const otrosIngresos = parseFloat(totales.otros_ingresos) || 0;
  const otrosEgresos = parseFloat(totales.otros_egresos) || 0;
  const resultadoNeto = parseFloat(totales.resultado_neto) || 0;
  const efectivo = parseFloat(totales.efectivo) || 0;
  const egresosEfectivo = parseFloat(totales.egresos_efectivo) || 0;
  const montoApertura = parseFloat(apertura?.monto_apertura) || 0;
  const totalCajaFisica = montoApertura + efectivo - egresosEfectivo;

  $("#totalVentasBrutas").text(`S/ ${formatearMonto(ventasBrutas)}`);
  $("#totalNotasCredito").text(`- S/ ${formatearMonto(notasCredito)}`);
  $("#totalOtrosIngresos").text(`S/ ${formatearMonto(otrosIngresos)}`);
  $("#totalOtrosEgresos").text(`- S/ ${formatearMonto(otrosEgresos)}`);

  $("#totalResultadoNeto")
    .removeClass("tw-text-tique-700 tw-text-rose-600")
    .addClass(resultadoNeto < 0 ? "tw-text-rose-600" : "tw-text-tique-700")
    .text(formatearMovimiento(resultadoNeto));

  $("#totalCaja")
    .removeClass("tw-text-white tw-text-rose-300")
    .addClass(totalCajaFisica < 0 ? "tw-text-rose-300" : "tw-text-white")
    .text(formatearMovimiento(totalCajaFisica));
}

/*
|--------------------------------------------------------------------------
| AUDITORÍA MULTICAJA
|--------------------------------------------------------------------------
| Solo lectura. No repara ni modifica registros.
*/
function auditarMulticaja() {
  Swal.fire({
    title: "Auditando cajas...",
    text: "Verificando aperturas, ventas, compras, devoluciones y cierres.",
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: function () {
      Swal.showLoading();
    },
  });

  $.ajax({
    url: "Controllers/Cajachica.php?op=auditoria_multicaja",
    type: "GET",
    dataType: "json",
    cache: false,
    data: {
      v: Date.now(),
    },
    success: function (resp) {
      if (!resp || resp.status !== "ok" || !resp.auditoria) {
        Swal.fire({
          icon: "warning",
          title: "No se pudo auditar",
          text:
            (resp && resp.message) ||
            "No se obtuvo un resultado válido.",
          confirmButtonColor: "#00a46a",
        });
        return;
      }

      mostrarResultadoAuditoriaCaja(resp.auditoria);
    },
    error: function (xhr) {
      let mensaje =
        "No se pudo ejecutar la auditoría.";

      if (
        xhr.responseJSON
        && (
          xhr.responseJSON.message
          || xhr.responseJSON.mensaje
        )
      ) {
        mensaje =
          xhr.responseJSON.message
          || xhr.responseJSON.mensaje;
      }

      Swal.fire({
        icon: "error",
        title: "Error de auditoría",
        text: mensaje,
        confirmButtonColor: "#00a46a",
      });
    },
  });
}

function mostrarResultadoAuditoriaCaja(auditoria) {
  const resumen = auditoria.resumen || {};
  const criticos = Number(resumen.criticos || 0);
  const advertencias = Number(resumen.advertencias || 0);
  const cajas = Array.isArray(auditoria.cajas)
    ? auditoria.cajas
    : [];
  const hallazgos = Array.isArray(auditoria.hallazgos)
    ? auditoria.hallazgos
    : [];
  const cierres = Array.isArray(auditoria.cierres_recientes)
    ? auditoria.cierres_recientes
    : [];

  const estadoGeneral =
    criticos === 0 && advertencias === 0
      ? {
          icono: "check-circle",
          clase: "tw-border-emerald-200 tw-bg-emerald-50 tw-text-emerald-800",
          titulo: "Integridad correcta",
          texto:
            "No se detectaron cruces ni alteraciones entre cajas.",
        }
      : criticos === 0
        ? {
            icono: "exclamation-triangle",
            clase: "tw-border-amber-200 tw-bg-amber-50 tw-text-amber-800",
            titulo: "Correcto con pendientes",
            texto:
              "No hay errores críticos, pero existen operaciones que requieren atención.",
          }
        : {
            icono: "times-circle",
            clase: "tw-border-red-200 tw-bg-red-50 tw-text-red-800",
            titulo: "Se detectaron inconsistencias",
            texto:
              "No lleves estos cambios a producción hasta corregir los hallazgos críticos.",
          };

  const htmlCajas = cajas.length
    ? cajas
        .map(function (caja) {
          const abierta =
            String(caja.estado || "").toUpperCase() === "ABIERTA";

          const badge = abierta
            ? '<span class="tw-rounded-full tw-bg-emerald-100 tw-px-2 tw-py-1 tw-text-[11px] tw-font-medium tw-text-emerald-700">Abierta</span>'
            : '<span class="tw-rounded-full tw-bg-slate-100 tw-px-2 tw-py-1 tw-text-[11px] tw-font-medium tw-text-slate-600">Cerrada</span>';

          const efectivo =
            caja.efectivo_esperado === null
              ? "—"
              : "S/ " + formatearMonto(caja.efectivo_esperado);

          return (
            '<div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-3">' +
              '<div class="tw-flex tw-items-start tw-justify-between tw-gap-3">' +
                '<div class="tw-min-w-0">' +
                  '<div class="tw-text-sm tw-font-medium tw-text-slate-900">' +
                    escaparHtml(caja.codigo || "") +
                    " · " +
                    escaparHtml(caja.nombre || "") +
                  "</div>" +
                  '<div class="tw-mt-1 tw-text-xs tw-text-slate-500">' +
                    (abierta
                      ? "Apertura #" +
                        Number(caja.idapertura || 0) +
                        " · " +
                        escaparHtml(caja.responsable || "Sin responsable")
                      : "Sin apertura activa") +
                  "</div>" +
                "</div>" +
                badge +
              "</div>" +
              '<div class="tw-mt-3 tw-flex tw-items-center tw-justify-between tw-border-t tw-border-slate-100 tw-pt-2">' +
                '<span class="tw-text-xs tw-text-slate-500">Efectivo esperado</span>' +
                '<strong class="tw-text-sm tw-text-slate-900">' +
                  efectivo +
                "</strong>" +
              "</div>" +
            "</div>"
          );
        })
        .join("")
    : '<div class="tw-text-sm tw-text-slate-500">No existen cajas físicas para mostrar.</div>';

  const htmlHallazgos = hallazgos.length
    ? hallazgos
        .map(function (item) {
          const critico =
            String(item.nivel || "") === "CRITICO";

          const clase = critico
            ? "tw-border-red-200 tw-bg-red-50"
            : "tw-border-amber-200 tw-bg-amber-50";

          const icono = critico
            ? "fa-times-circle tw-text-red-600"
            : "fa-exclamation-triangle tw-text-amber-600";

          return (
            '<div class="tw-rounded-xl tw-border ' +
            clase +
            ' tw-p-3">' +
              '<div class="tw-flex tw-items-start tw-gap-2">' +
                '<i class="fas ' +
                  icono +
                  ' tw-mt-0.5"></i>' +
                '<div class="tw-min-w-0 tw-flex-1">' +
                  '<div class="tw-flex tw-items-center tw-justify-between tw-gap-2">' +
                    '<strong class="tw-text-sm tw-text-slate-900">' +
                      escaparHtml(item.titulo || "") +
                    "</strong>" +
                    '<span class="tw-rounded-full tw-bg-white/80 tw-px-2 tw-py-0.5 tw-text-[11px] tw-font-medium tw-text-slate-700">' +
                      Number(item.cantidad || 0) +
                    "</span>" +
                  "</div>" +
                  '<div class="tw-mt-1 tw-text-xs tw-leading-5 tw-text-slate-600">' +
                    escaparHtml(item.detalle || "") +
                  "</div>" +
                  '<div class="tw-mt-1 tw-text-[10px] tw-uppercase tw-tracking-wide tw-text-slate-400">' +
                    escaparHtml(item.codigo || "") +
                  "</div>" +
                "</div>" +
              "</div>" +
            "</div>"
          );
        })
        .join("")
    : '<div class="tw-rounded-xl tw-border tw-border-emerald-200 tw-bg-emerald-50 tw-p-3 tw-text-sm tw-text-emerald-800"><i class="fas fa-check mr-2"></i>No hay hallazgos.</div>';

  const cierresConProblema = cierres.filter(function (cierre) {
    return (
      cierre.diferencia_integridad !== null
      && Math.abs(Number(cierre.diferencia_integridad || 0)) > 0.01
    );
  });

  const resumenCierres =
    cierres.length === 0
      ? "Sin cierres físicos recientes."
      : cierresConProblema.length === 0
        ? "Los últimos " +
          cierres.length +
          " cierres conservan su total original."
        : cierresConProblema.length +
          " cierre(s) cambiaron después de cerrarse.";

  const contenido =
    '<div class="tw-text-left">' +
      '<div class="tw-rounded-2xl tw-border tw-p-4 ' +
        estadoGeneral.clase +
        '">' +
        '<div class="tw-flex tw-items-start tw-gap-3">' +
          '<i class="fas fa-' +
            estadoGeneral.icono +
            ' tw-mt-0.5 tw-text-lg"></i>' +
          "<div>" +
            '<div class="tw-font-medium">' +
              estadoGeneral.titulo +
            "</div>" +
            '<div class="tw-mt-1 tw-text-xs tw-leading-5">' +
              estadoGeneral.texto +
            "</div>" +
          "</div>" +
        "</div>" +
      "</div>" +

      '<div class="tw-mt-4 tw-grid tw-grid-cols-2 tw-gap-2 sm:tw-grid-cols-4">' +
        tarjetaAuditoria("Modo", auditoria.modo || "—") +
        tarjetaAuditoria("Cajas", auditoria.total_cajas || 0) +
        tarjetaAuditoria("Críticos", criticos) +
        tarjetaAuditoria("Advertencias", advertencias) +
      "</div>" +

      '<div class="tw-mt-5">' +
        '<div class="tw-mb-2 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wide tw-text-slate-500">Estado actual</div>' +
        '<div class="tw-grid tw-gap-2 sm:tw-grid-cols-2">' +
          htmlCajas +
        "</div>" +
      "</div>" +

      '<div class="tw-mt-5">' +
        '<div class="tw-mb-2 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wide tw-text-slate-500">Integridad</div>' +
        '<div class="tw-space-y-2">' +
          htmlHallazgos +
        "</div>" +
      "</div>" +

      '<div class="tw-mt-5 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3">' +
        '<div class="tw-text-xs tw-font-medium tw-text-slate-700">Cierres recientes</div>' +
        '<div class="tw-mt-1 tw-text-xs tw-leading-5 tw-text-slate-500">' +
          escaparHtml(resumenCierres) +
        "</div>" +
      "</div>" +

      '<div class="tw-mt-3 tw-text-[11px] tw-leading-5 tw-text-slate-400">' +
        "Auditoría de solo lectura. No modifica ventas, caja ni base de datos." +
      "</div>" +
    "</div>";

  Swal.fire({
    title: "Auditoría de cajas",
    html: contenido,
    width: 860,
    confirmButtonText: "Cerrar",
    confirmButtonColor: "#00a46a",
  });
}

function tarjetaAuditoria(etiqueta, valor) {
  return (
    '<div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-3">' +
      '<div class="tw-text-[11px] tw-uppercase tw-tracking-wide tw-text-slate-400">' +
        escaparHtml(etiqueta) +
      "</div>" +
      '<div class="tw-mt-1 tw-text-sm tw-font-semibold tw-text-slate-900">' +
        escaparHtml(String(valor)) +
      "</div>" +
    "</div>"
  );
}

/*
|--------------------------------------------------------------------------
| MOVIMIENTOS MANUALES
|--------------------------------------------------------------------------
*/
function abrirMovimientoManualCaja() {
  if (window.TIQUEPOS_CAJA_PUEDE_MOVIMIENTO_MANUAL !== true) {
    Swal.fire({
      icon: "warning",
      title: "Sin permiso",
      text: "Solo un Administrador o Cajero puede registrar movimientos manuales.",
      confirmButtonColor: "#00a46a",
    });
    return;
  }

  if (
    contextoCajaActual.estado !== "ABIERTA" ||
    contextoCajaActual.idapertura <= 0
  ) {
    Swal.fire({
      icon: "warning",
      title: "Caja cerrada",
      text: "Debe abrir la caja antes de registrar un movimiento de efectivo.",
      confirmButtonColor: "#00a46a",
    });
    return;
  }

  Swal.fire({
    title: "Nuevo movimiento de caja",
    width: 560,
    html: `
      <div class="tw-text-left">
        <div class="tw-mb-4 tw-rounded-xl tw-border tw-border-emerald-100 tw-bg-emerald-50 tw-p-3 tw-text-xs tw-leading-5 tw-text-emerald-800">
          <i class="fas fa-shield-alt tw-mr-1"></i>
          El movimiento quedará ligado a la apertura activa y afectará únicamente el efectivo físico.
        </div>

        <label class="tw-mb-1.5 tw-block tw-text-xs tw-font-medium tw-text-slate-600">
          Tipo de movimiento
        </label>
        <select id="swalMovimientoCajaClase" class="swal2-input" style="width:100%;margin:0 0 14px 0;">
          <option value="INGRESO">Ingreso manual</option>
          <option value="EGRESO">Egreso manual</option>
          <option value="RETIRO">Retiro de efectivo</option>
          <option value="AJUSTE_POSITIVO">Ajuste positivo</option>
          <option value="AJUSTE_NEGATIVO">Ajuste negativo</option>
        </select>

        <label class="tw-mb-1.5 tw-block tw-text-xs tw-font-medium tw-text-slate-600">
          Monto
        </label>
        <input
          id="swalMovimientoCajaMonto"
          class="swal2-input"
          type="number"
          min="0.01"
          step="0.01"
          inputmode="decimal"
          placeholder="0.00"
          style="width:100%;margin:0 0 14px 0;">

        <label class="tw-mb-1.5 tw-block tw-text-xs tw-font-medium tw-text-slate-600">
          Motivo
        </label>
        <textarea
          id="swalMovimientoCajaConcepto"
          class="swal2-textarea"
          maxlength="180"
          rows="3"
          placeholder="Ej.: retiro para gastos de movilidad"
          style="width:100%;margin:0;"></textarea>

        <div class="tw-mt-3 tw-text-xs tw-text-slate-500">
          Efectivo esperado actual:
          <strong>S/ ${formatearMonto(
            Math.max(0, contextoCajaActual.efectivoEsperado)
          )}</strong>
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: "Registrar movimiento",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#00a46a",
    reverseButtons: true,
    focusConfirm: false,
    didOpen: function () {
      const monto = document.getElementById(
        "swalMovimientoCajaMonto"
      );

      if (monto) {
        window.setTimeout(function () {
          monto.focus();
        }, 100);
      }
    },
    preConfirm: function () {
      const clase = String(
        document.getElementById("swalMovimientoCajaClase")?.value || ""
      );

      const monto = Number(
        document.getElementById("swalMovimientoCajaMonto")?.value || 0
      );

      const concepto = String(
        document.getElementById("swalMovimientoCajaConcepto")?.value || ""
      ).trim();

      if (monto <= 0) {
        Swal.showValidationMessage(
          "Ingrese un monto mayor que cero."
        );
        return false;
      }

      if (concepto.length < 4) {
        Swal.showValidationMessage(
          "Escriba un motivo breve para conservar la trazabilidad."
        );
        return false;
      }

      return {
        clase: clase,
        monto: monto,
        concepto: concepto,
      };
    },
  }).then(function (resultado) {
    if (!resultado.isConfirmed || !resultado.value) {
      return;
    }

    registrarMovimientoManualCaja(resultado.value);
  });
}

function registrarMovimientoManualCaja(datos) {
  Swal.fire({
    title: "Registrando...",
    text: "Validando apertura y efectivo disponible.",
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: function () {
      Swal.showLoading();
    },
  });

  $.ajax({
    url: "Controllers/Cajachica.php?op=guardar_movimiento_manual",
    type: "POST",
    dataType: "json",
    cache: false,
    data: {
      clase: datos.clase,
      monto: Number(datos.monto).toFixed(2),
      concepto: datos.concepto,
    },
    success: function (resp) {
      if (!resp || resp.status !== "ok") {
        Swal.fire({
          icon: "warning",
          title: "No se registró",
          text:
            (resp && resp.message) ||
            "No se pudo registrar el movimiento.",
          confirmButtonColor: "#00a46a",
        });
        return;
      }

      const movimiento = resp.movimiento || {};

      Swal.fire({
        icon: "success",
        title: "Movimiento registrado",
        html:
          '<div class="tw-text-sm tw-text-slate-600">' +
          escaparHtml(movimiento.concepto || "") +
          '<br><strong class="tw-text-slate-900">S/ ' +
          formatearMonto(movimiento.monto || 0) +
          "</strong></div>",
        timer: 1600,
        showConfirmButton: false,
      }).then(function () {
        cargarCaja({ forzar: true });
      });
    },
    error: function (xhr) {
      let mensaje =
        "No se pudo registrar el movimiento.";

      if (
        xhr.responseJSON &&
        typeof xhr.responseJSON.message === "string"
      ) {
        mensaje = xhr.responseJSON.message;
      } else {
        try {
          const respuesta = JSON.parse(
            String(xhr.responseText || "")
          );

          if (
            respuesta &&
            typeof respuesta.message === "string"
          ) {
            mensaje = respuesta.message;
          }
        } catch (error) {
          // Conserva el mensaje seguro.
        }
      }

      Swal.fire({
        icon: "error",
        title: "Movimiento rechazado",
        text: mensaje,
        confirmButtonColor: "#00a46a",
      });

      cargarCaja({ forzar: true });
    },
  });
}

/*
|--------------------------------------------------------------------------
| CERRAR CAJA
|--------------------------------------------------------------------------
*/
function cerrarCaja() {
  if (cajaCargaEnCurso) {
    return;
  }

  $.ajax({
    url: "Controllers/Cajachica.php?op=datos_cierre",
    type: "GET",
    dataType: "json",
    success: function (resp) {
      if (!resp.status) {
        Swal.fire({
          icon: "warning",
          title: "No hay una caja abierta",
          text: resp.message || "No existe una caja abierta para realizar el arqueo.",
          confirmButtonColor: "#00a46a",
        });
        return;
      }

      mostrarModalArqueoCaja(resp);
    },
    error: function (xhr) {
      console.error("Error en datos de cierre:", xhr.responseText);
      Swal.fire({
        icon: "error",
        title: "No se pudo iniciar el arqueo",
        text: "No se pudieron obtener los datos del cierre.",
        confirmButtonColor: "#00a46a",
      });
    },
  });
}

function mostrarModalArqueoCaja(resp) {
  const totalSistema = parseFloat(resp.total_sistema) || 0;
  const montoApertura = parseFloat(resp.monto_apertura) || 0;
  const ventasEfectivo = parseFloat(resp.ventas_efectivo) || 0;
  const otrosIngresos = parseFloat(resp.otros_ingresos_efectivo) || 0;
  const egresosEfectivo = parseFloat(resp.egresos_efectivo) || 0;
  const notasCreditoEfectivo = parseFloat(resp.notas_credito_efectivo) || 0;
  const otrosEgresosEfectivo = parseFloat(resp.otros_egresos_efectivo) || 0;

  Swal.fire({
    title: "Arqueo de caja",
    width: 620,
    html: `
      <div class="tw-text-left">
        <p class="tw-m-0 tw-mb-4 tw-text-sm tw-leading-6 tw-text-slate-500">Verifica el efectivo físico antes de cerrar la caja. La diferencia se calculará automáticamente.</p>

        <div class="tw-grid tw-grid-cols-2 tw-gap-2 sm:tw-grid-cols-3">
          ${tarjetaArqueo("Apertura", montoApertura, "fa-door-open", "tw-text-tique-700", false)}
          ${tarjetaArqueo("Ventas efectivo", ventasEfectivo, "fa-receipt", "tw-text-slate-800", false)}
          ${tarjetaArqueo("Otros ingresos", otrosIngresos, "fa-arrow-down", "tw-text-sky-700", false)}
          ${tarjetaArqueo("Devoluciones N.C.", notasCreditoEfectivo, "fa-undo-alt", "tw-text-rose-600", true)}
          ${tarjetaArqueo("Otros egresos", otrosEgresosEfectivo, "fa-arrow-up", "tw-text-amber-700", true)}
          ${tarjetaArqueo("Egresos efectivo", egresosEfectivo, "fa-wallet", "tw-text-rose-600", true)}
        </div>

        <div class="tw-mt-4 tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
          <div class="tw-flex tw-items-center tw-justify-between tw-gap-3">
            <div>
              <div class="tw-text-xs tw-font-medium tw-text-slate-500">Efectivo esperado en gaveta</div>
              <div class="tw-mt-1 tw-text-2xl tw-font-semibold tw-text-slate-900">S/ ${formatearMonto(totalSistema)}</div>
            </div>
            <span class="tw-flex tw-h-11 tw-w-11 tw-items-center tw-justify-center tw-rounded-xl tw-bg-slate-900 tw-text-white"><i class="fas fa-cash-register"></i></span>
          </div>
        </div>

        <label for="montoContado" class="tw-mt-4 tw-block tw-text-xs tw-font-medium tw-text-slate-600">Efectivo contado físicamente</label>
        <div class="tw-relative tw-mt-1.5">
          <span class="tw-pointer-events-none tw-absolute tw-inset-y-0 tw-left-0 tw-flex tw-w-12 tw-items-center tw-justify-center tw-text-sm tw-font-medium tw-text-slate-500">S/</span>
          <input
            type="number"
            step="0.01"
            min="0"
            inputmode="decimal"
            id="montoContado"
            class="tw-h-14 tw-w-full tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-pl-12 tw-pr-4 tw-text-xl tw-font-semibold tw-text-slate-900 tw-outline-none tw-transition focus:tw-border-tique-400 focus:tw-ring-4 focus:tw-ring-tique-100"
            placeholder="0.00"
            autocomplete="off">
        </div>

        <div id="diferenciaBox" class="tw-mt-3 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-px-4 tw-py-3 tw-text-sm tw-text-slate-500">
          Ingresa el efectivo contado para ver el cuadre.
        </div>
      </div>`,
    showCancelButton: true,
    confirmButtonText: '<i class="fas fa-lock tw-mr-2"></i>Cerrar caja',
    cancelButtonText: "Cancelar",
    focusConfirm: false,
    allowOutsideClick: false,
    customClass: {
      popup: "caja-swal-popup",
    },
    didOpen: function () {
      const input = document.getElementById("montoContado");
      const diferenciaBox = document.getElementById("diferenciaBox");

      if (!input || !diferenciaBox) {
        return;
      }

      setTimeout(() => input.focus(), 80);

      input.addEventListener("focus", function () {
        if (this.value === "0" || this.value === "0.00") {
          this.value = "";
        }
      });

      input.addEventListener("input", function () {
        const contado = parseFloat(this.value || 0) || 0;
        const diferencia = contado - totalSistema;

        diferenciaBox.className = "tw-mt-3 tw-rounded-xl tw-border tw-px-4 tw-py-3 tw-text-sm";

        if (!String(this.value || "").trim()) {
          diferenciaBox.classList.add("tw-border-slate-200", "tw-bg-slate-50", "tw-text-slate-500");
          diferenciaBox.innerHTML = "Ingresa el efectivo contado para ver el cuadre.";
          return;
        }

        if (Math.abs(diferencia) < 0.005) {
          diferenciaBox.classList.add("tw-border-emerald-200", "tw-bg-emerald-50", "tw-text-emerald-700");
          diferenciaBox.innerHTML = `<div class="tw-flex tw-items-center tw-justify-between tw-gap-3"><span><i class="fas fa-check-circle tw-mr-2"></i>Cuadre exacto</span><strong>S/ ${formatearMonto(0)}</strong></div>`;
        } else if (diferencia > 0) {
          diferenciaBox.classList.add("tw-border-amber-200", "tw-bg-amber-50", "tw-text-amber-700");
          diferenciaBox.innerHTML = `<div class="tw-flex tw-items-center tw-justify-between tw-gap-3"><span><i class="fas fa-plus-circle tw-mr-2"></i>Sobrante</span><strong>S/ ${formatearMonto(diferencia)}</strong></div>`;
        } else {
          diferenciaBox.classList.add("tw-border-rose-200", "tw-bg-rose-50", "tw-text-rose-700");
          diferenciaBox.innerHTML = `<div class="tw-flex tw-items-center tw-justify-between tw-gap-3"><span><i class="fas fa-minus-circle tw-mr-2"></i>Faltante</span><strong>- S/ ${formatearMonto(Math.abs(diferencia))}</strong></div>`;
        }
      });
    },
    preConfirm: function () {
      const campo = document.getElementById("montoContado");
      const valor = String(campo?.value || "").trim();

      if (!valor) {
        Swal.showValidationMessage("Ingrese el monto contado.");
        return false;
      }

      const montoContado = parseFloat(valor);

      if (!Number.isFinite(montoContado) || montoContado < 0) {
        Swal.showValidationMessage("Ingrese un monto válido.");
        return false;
      }

      return { montoContado };
    },
  }).then(function (resultado) {
    if (!resultado.isConfirmed) {
      return;
    }

    registrarCierreCaja(resultado.value.montoContado);
  });
}

function tarjetaArqueo(titulo, monto, icono, claseTexto, negativo) {
  const numero = Math.abs(parseFloat(monto) || 0);
  const prefijo = negativo ? "- S/ " : "S/ ";

  return `
    <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-3">
      <div class="tw-flex tw-items-center tw-gap-2 tw-text-[11px] tw-text-slate-500"><i class="fas ${icono} tw-w-3"></i><span>${titulo}</span></div>
      <div class="tw-mt-1.5 tw-text-sm tw-font-semibold ${claseTexto}">${prefijo}${formatearMonto(numero)}</div>
    </div>`;
}

/*
|--------------------------------------------------------------------------
| REGISTRAR CIERRE
|--------------------------------------------------------------------------
*/
function registrarCierreCaja(montoContado) {
  const boton = $("#btnCerrarCaja");
  boton.prop("disabled", true);

  Swal.fire({
    title: "Cerrando caja",
    html: '<div class="tw-text-sm tw-text-slate-500">Registrando el arqueo y finalizando la sesión...</div>',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  });

  $.ajax({
    url: "Controllers/Cajachica.php?op=cerrar_caja",
    type: "POST",
    dataType: "json",
    data: {
      monto_contado: montoContado,
    },
    success: function (resp) {
      if (resp.status === "ok") {
        const diferencia = parseFloat(resp.diferencia) || 0;

        Swal.fire({
          icon: "success",
          title: "Caja cerrada correctamente",
          html: `
            <div class="tw-mx-auto tw-max-w-md tw-text-left">
              <div class="tw-grid tw-grid-cols-3 tw-gap-2">
                <div class="tw-rounded-xl tw-bg-slate-50 tw-p-3"><div class="tw-text-[11px] tw-text-slate-500">Sistema</div><div class="tw-mt-1 tw-text-sm tw-font-semibold tw-text-slate-800">S/ ${formatearMonto(resp.total_sistema)}</div></div>
                <div class="tw-rounded-xl tw-bg-slate-50 tw-p-3"><div class="tw-text-[11px] tw-text-slate-500">Contado</div><div class="tw-mt-1 tw-text-sm tw-font-semibold tw-text-slate-800">S/ ${formatearMonto(resp.monto_contado)}</div></div>
                <div class="tw-rounded-xl ${Math.abs(diferencia) < 0.005 ? "tw-bg-emerald-50" : diferencia < 0 ? "tw-bg-rose-50" : "tw-bg-amber-50"} tw-p-3"><div class="tw-text-[11px] tw-text-slate-500">Diferencia</div><div class="tw-mt-1 tw-text-sm tw-font-semibold ${Math.abs(diferencia) < 0.005 ? "tw-text-emerald-700" : diferencia < 0 ? "tw-text-rose-700" : "tw-text-amber-700"}">${formatearMovimiento(diferencia)}</div></div>
              </div>
              <p class="tw-m-0 tw-mt-4 tw-text-center tw-text-sm tw-text-slate-500">La sesión se cerrará para completar el proceso.</p>
            </div>`,
          allowOutsideClick: false,
          allowEscapeKey: false,
          confirmButtonText: "Ir al inicio de sesión",
          confirmButtonColor: "#00a46a",
        }).then(function () {
          window.location.href = resp.redirect || "login";
        });
        return;
      }

      boton.prop("disabled", false);
      Swal.fire({
        icon: "error",
        title: "No se pudo cerrar la caja",
        text: resp.message || "Ocurrió un problema al registrar el cierre.",
        confirmButtonColor: "#00a46a",
      });
    },
    error: function (xhr) {
      console.error("Error al cerrar caja:", xhr.responseText);
      boton.prop("disabled", false);

      Swal.fire({
        icon: "error",
        title: "Error de conexión",
        text: "No se pudo registrar el cierre de caja.",
        confirmButtonColor: "#00a46a",
      });
    },
  });
}

/*
|--------------------------------------------------------------------------
| UTILIDADES
|--------------------------------------------------------------------------
*/
function formatearMonto(monto) {
  return (parseFloat(monto) || 0).toLocaleString("es-PE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatearMovimiento(monto) {
  const numero = parseFloat(monto) || 0;
  const prefijo = numero < 0 ? "- S/ " : "S/ ";
  return prefijo + formatearMonto(Math.abs(numero));
}

function fechaLocalISO(fecha) {
  const year = fecha.getFullYear();
  const month = String(fecha.getMonth() + 1).padStart(2, "0");
  const day = String(fecha.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function formatearFechaCorta(fechaIso) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(String(fechaIso || ""))) {
    return fechaIso || "";
  }

  const [year, month, day] = fechaIso.split("-").map(Number);
  const fecha = new Date(year, month - 1, day);

  return fecha.toLocaleDateString("es-PE", {
    day: "2-digit",
    month: "short",
  });
}

function escaparHtml(texto) {
  return String(texto || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
