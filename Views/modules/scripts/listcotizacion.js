"use strict";

let tablaCotizaciones = null;
let cotizacionRefreshTimer = null;
let cotizacionChannel = null;
let cotizacionRefreshBusy = false;

function normalizarJsonCotizacion(data) {
  if (data && typeof data === "object") return data;
  try { return JSON.parse(data || "{}"); } catch (_) { return {}; }
}

function setCotizacionLiveLoading(loading) {
  const pill = document.getElementById("cotizacionLiveStatus");
  if (pill) pill.classList.toggle("is-loading", !!loading);
}

function cargarResumenCotizaciones() {
  return $.getJSON("Controllers/Sell.php?op=cotizacionesPendientes&limite=5")
    .done(function (respuesta) {
      const total = Number(respuesta?.total || 0);
      $("#cotizacionesPendientesCount").text(total);
    })
    .fail(function () {
      console.warn("No se pudo actualizar el contador de cotizaciones pendientes.");
    });
}

function refrescarCotizaciones(silencioso = true) {
  if (cotizacionRefreshBusy || document.hidden) return;
  cotizacionRefreshBusy = true;
  setCotizacionLiveLoading(true);

  const tareas = [cargarResumenCotizaciones()];
  if (tablaCotizaciones) {
    tareas.push(new Promise((resolve) => {
      tablaCotizaciones.ajax.reload(() => resolve(), false);
    }));
  }

  Promise.allSettled(tareas).finally(() => {
    cotizacionRefreshBusy = false;
    setCotizacionLiveLoading(false);
  });
}

function iniciarActualizacionCotizaciones() {
  if (cotizacionRefreshTimer) clearInterval(cotizacionRefreshTimer);
  cotizacionRefreshTimer = setInterval(() => refrescarCotizaciones(true), 2500);

  if ("BroadcastChannel" in window) {
    try {
      cotizacionChannel = new BroadcastChannel("tiquepos-cotizaciones");
      cotizacionChannel.addEventListener("message", () => refrescarCotizaciones(true));
    } catch (_) {
      cotizacionChannel = null;
    }
  }

  window.addEventListener("storage", (event) => {
    if (event.key === "tiquepos_cotizaciones_actualizadas") refrescarCotizaciones(true);
  });

  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) refrescarCotizaciones(true);
  });
}

function init() {
  listar();
  cargarResumenCotizaciones();
  iniciarActualizacionCotizaciones();
}

$("#btnagregar").on("click", function () {
  window.location.href = "pos?comprobante=cotizacion";
});

function listar() {
  tablaCotizaciones = $("#tbllistado").DataTable({
    processing: true,
    serverSide: false,
    responsive: false,
    dom: "Bfrtip",
    buttons: [
      {
        extend: "excelHtml5",
        text: '<i class="fa fa-file-excel-o bg-green"></i> Excel',
        titleAttr: "Exportar a Excel",
        title: "Reporte de Cotizaciones",
        sheetName: "Cotizaciones",
        exportOptions: { columns: [1, 2, 3, 4, 5, 6, 7] },
      },
      {
        extend: "pdfHtml5",
        text: '<i class="fa fa-file-pdf-o bg-red"></i> PDF',
        titleAttr: "Exportar a PDF",
        title: "Reporte de Cotizaciones",
        pageSize: "A4",
        exportOptions: { columns: [1, 2, 3, 4, 5, 6, 7] },
      },
    ],
    ajax: {
      url: "Controllers/Sell.php?op=listarCotizaciones",
      type: "GET",
      dataType: "json",
      cache: false,
      error: function (xhr) {
        console.error("No se pudo listar cotizaciones:", xhr.responseText);
      },
    },
    pageLength: 10,
    order: [[1, "desc"]],
    drawCallback: function () {
      cargarResumenCotizaciones();
    },
    language: {
      emptyTable: "No hay cotizaciones registradas.",
      zeroRecords: "No se encontraron cotizaciones con ese criterio.",
    },
  });
}

function ejecutarCotizacion(idventa) {
  const id = Number(idventa || 0);
  if (id <= 0) return;
  window.location.href = "pos?ejecutar_cotizacion=" + encodeURIComponent(id);
}

function mostrar(idventa) {
  $("#getCodeModal").modal("show");

  $.post(
    "Controllers/Sell.php?op=mostrar",
    { idventa },
    function (respuesta) {
      const data = normalizarJsonCotizacion(respuesta);

      $("#cliente").val(data.cliente || "");
      $("#tipo_comprobantem").val(data.tipo_comprobante || "");
      $("#serie_comprobantem").val(data.serie_comprobante || "");
      $("#num_comprobantem").val(data.num_comprobante || "");
      $("#fecha_horam").val(data.fecha || "");
      $("#impuestom").val(data.impuesto ?? 0);
      $("#idventam").val(data.idventa || "");
      $("#tipo_pagom").val(data.tipo_pago && data.tipo_pago !== "No aplica" ? data.tipo_pago : "No aplica");
      $("#condicion_pagom").val("No aplica");
      $("#detallePagom").empty();
      $("#bloquePagoMixto").hide();
    }
  );

  $.post(
    "Controllers/Sell.php?op=listarDetalle&id=" + encodeURIComponent(idventa),
    function (r) {
      $("#detallesm").html(r);
    }
  );
}

window.ejecutarCotizacion = ejecutarCotizacion;
window.mostrar = mostrar;

init();
