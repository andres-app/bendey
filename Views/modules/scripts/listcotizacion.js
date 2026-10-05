"use strict";

let tablaCotizaciones = null;
let cotizacionRefreshTimer = null;
let cotizacionChannel = null;
let cotizacionRefreshBusy = false;
let cotizacionFiltroEstado = "";
let cotizacionPreviewId = 0;
let cotizacionPreviewPrintUrl = "";
let cotizacionPreviewPending = false;

function cqEscapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function normalizarJsonCotizacion(data) {
  if (data && typeof data === "object") return data;
  try {
    return JSON.parse(data || "{}");
  } catch (_) {
    return {};
  }
}

function cqNumero(value) {
  const number = Number(value || 0);
  return Number.isFinite(number) ? number : 0;
}

function cqMoney(value, simbolo = "S/") {
  return `${simbolo || "S/"} ${cqNumero(value).toLocaleString("es-PE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function cqCantidad(value) {
  const number = cqNumero(value);
  if (Math.abs(number - Math.round(number)) < 0.00001) {
    return String(Math.round(number));
  }
  return number.toLocaleString("es-PE", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  });
}

function setCotizacionLiveLoading(loading) {
  const dot = document.getElementById("cotizacionLiveDot");
  if (dot) dot.classList.toggle("is-loading", !!loading);
}

function cargarResumenCotizaciones() {
  return $.ajax({
    url: "Controllers/Sell.php?op=resumenCotizaciones",
    type: "GET",
    dataType: "json",
    cache: false,
  })
    .done(function (respuesta) {
      const data = normalizarJsonCotizacion(respuesta);
      if (data.success === false) return;

      const resumen = data.resumen || {};
      const simbolo = String(data.simbolo || "S/").trim() || "S/";

      $("#cotStatPendientes").text(cqNumero(resumen.pendientes));
      $("#cotStatEjecutadas").text(cqNumero(resumen.ejecutadas));
      $("#cotStatAnuladas").text(cqNumero(resumen.anuladas));
      $("#cotStatMonto").text(cqMoney(resumen.monto_pendiente, simbolo));
    })
    .fail(function (xhr) {
      console.warn("No se pudo actualizar el resumen de cotizaciones.", xhr?.responseText || "");
    });
}

function refrescarCotizaciones() {
  if (cotizacionRefreshBusy || document.hidden) return;

  cotizacionRefreshBusy = true;
  setCotizacionLiveLoading(true);

  const tareas = [cargarResumenCotizaciones()];

  if (tablaCotizaciones) {
    tareas.push(
      new Promise((resolve) => {
        tablaCotizaciones.ajax.reload(() => resolve(), false);
      })
    );
  }

  Promise.allSettled(tareas).finally(() => {
    cotizacionRefreshBusy = false;
    setCotizacionLiveLoading(false);
  });
}

function notificarCotizacionesActualizadas() {
  try {
    if (cotizacionChannel) {
      cotizacionChannel.postMessage({ type: "refresh", at: Date.now() });
    }
    localStorage.setItem("tiquepos_cotizaciones_actualizadas", String(Date.now()));
  } catch (_) {
    // El refresco periódico cubre navegadores sin BroadcastChannel/localStorage.
  }
}

function iniciarActualizacionCotizaciones() {
  if (cotizacionRefreshTimer) clearInterval(cotizacionRefreshTimer);
  cotizacionRefreshTimer = setInterval(refrescarCotizaciones, 2500);

  if ("BroadcastChannel" in window) {
    try {
      cotizacionChannel = new BroadcastChannel("tiquepos-cotizaciones");
      cotizacionChannel.addEventListener("message", () => refrescarCotizaciones());
    } catch (_) {
      cotizacionChannel = null;
    }
  }

  window.addEventListener("storage", (event) => {
    if (event.key === "tiquepos_cotizaciones_actualizadas") {
      refrescarCotizaciones();
    }
  });

  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) refrescarCotizaciones();
  });
}

function aplicarFiltroEstado(estado) {
  cotizacionFiltroEstado = String(estado || "").trim();

  document.querySelectorAll("[data-status-filter]").forEach((button) => {
    button.classList.toggle(
      "is-active",
      String(button.dataset.statusFilter || "") === cotizacionFiltroEstado
    );
  });

  if (!tablaCotizaciones) return;

  if (!cotizacionFiltroEstado) {
    tablaCotizaciones.column(5).search("").draw();
    return;
  }

  tablaCotizaciones
    .column(5)
    .search(`^${$.fn.dataTable.util.escapeRegex(cotizacionFiltroEstado)}$`, true, false)
    .draw();
}

function vincularFiltrosEstado() {
  document.querySelectorAll("[data-status-filter]").forEach((button) => {
    button.addEventListener("click", () => {
      aplicarFiltroEstado(button.dataset.statusFilter || "");
    });
  });
}

function listar() {
  tablaCotizaciones = $("#tbllistado").DataTable({
    processing: true,
    serverSide: false,
    responsive: false,
    autoWidth: false,
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    order: [[1, "desc"]],
    dom: "<'cq-dt-top'<'cq-dt-length'l><'cq-dt-actions'Bf>>rt<'cq-dt-bottom'ip>",
    buttons: [
      {
        extend: "excelHtml5",
        text: '<i class="fa fa-file-excel-o"></i> Excel',
        titleAttr: "Exportar cotizaciones a Excel",
        title: "Reporte de Cotizaciones",
        sheetName: "Cotizaciones",
        exportOptions: { columns: [0, 1, 2, 3, 4, 5] },
      },
      {
        extend: "pdfHtml5",
        text: '<i class="fa fa-file-pdf-o"></i> PDF',
        titleAttr: "Exportar cotizaciones a PDF",
        title: "Reporte de Cotizaciones",
        pageSize: "A4",
        orientation: "landscape",
        exportOptions: { columns: [0, 1, 2, 3, 4, 5] },
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
    columnDefs: [
      { targets: 4, className: "text-right" },
      { targets: 6, className: "text-right", orderable: false, searchable: false },
    ],
    drawCallback: function () {
      if (cotizacionFiltroEstado) {
        // Conserva el filtro visual cuando el Ajax se actualiza en vivo.
        document.querySelectorAll("[data-status-filter]").forEach((button) => {
          button.classList.toggle(
            "is-active",
            String(button.dataset.statusFilter || "") === cotizacionFiltroEstado
          );
        });
      }
    },
    language: {
      processing: "Actualizando…",
      search: "Buscar",
      lengthMenu: "Mostrar _MENU_",
      info: "_START_–_END_ de _TOTAL_ cotizaciones",
      infoEmpty: "Sin cotizaciones",
      infoFiltered: "(filtrado de _MAX_)",
      emptyTable: "Aún no hay cotizaciones registradas.",
      zeroRecords: "No se encontraron cotizaciones con ese criterio.",
      paginate: {
        previous: "‹",
        next: "›",
      },
    },
  });
}

function abrirPreviewCotizacion() {
  const modal = document.getElementById("cotizacionPreviewModal");
  if (!modal) return;

  modal.classList.remove("tw-hidden");
  document.body.classList.add("cq-modal-open");
}

function cerrarPreviewCotizacion() {
  const modal = document.getElementById("cotizacionPreviewModal");
  if (!modal) return;

  modal.classList.add("tw-hidden");
  document.body.classList.remove("cq-modal-open");
  cotizacionPreviewId = 0;
  cotizacionPreviewPrintUrl = "";
  cotizacionPreviewPending = false;
}

function setPreviewLoading(loading) {
  const loader = document.getElementById("cotizacionPreviewLoading");
  const documentView = document.getElementById("cotizacionPreviewDocument");

  if (loader) loader.classList.toggle("tw-hidden", !loading);
  if (documentView) documentView.classList.toggle("tw-hidden", !!loading);
}

function cqStatusClass(status) {
  const normalized = String(status || "").toLowerCase();
  if (normalized.includes("pend")) return "cq-status cq-status--pending";
  if (normalized.includes("ejecut")) return "cq-status cq-status--done";
  if (normalized.includes("anul")) return "cq-status cq-status--cancelled";
  return "cq-status cq-status--other";
}

function renderPreviewCotizacion(data) {
  const cotizacion = data.cotizacion || {};
  const empresa = data.empresa || {};
  const detalles = Array.isArray(data.detalles) ? data.detalles : [];
  const simbolo = String(empresa.simbolo || "S/").trim() || "S/";

  cotizacionPreviewId = Number(cotizacion.idventa || 0);
  cotizacionPreviewPrintUrl = String(data.print_url || "");
  cotizacionPreviewPending = Boolean(cotizacion.pendiente);

  const status = String(cotizacion.estado || "Sin estado");
  const statusClass = cqStatusClass(status);

  const headerStatus = document.getElementById("previewHeaderStatus");
  if (headerStatus) {
    headerStatus.className = statusClass;
    headerStatus.textContent = status;
  }

  $("#previewHeaderNumber").text(cotizacion.numero || "—");
  $("#previewDocumentoNumero").text(cotizacion.numero || "—");
  $("#previewDocumentoFecha").text(cotizacion.fecha || "—");

  const empresaNombre = String(empresa.nombre || "Empresa").trim() || "Empresa";
  $("#previewEmpresaNombre").text(empresaNombre);
  $("#previewEmpresaInitial").text(empresaNombre.charAt(0).toUpperCase() || "T");

  const empresaDocumento = String(empresa.documento || "").trim();
  $("#previewEmpresaDocumento").text(empresaDocumento ? `RUC ${empresaDocumento}` : "");
  $("#previewEmpresaDireccion").text(String(empresa.direccion || "").trim());

  const empresaContacto = [empresa.telefono, empresa.email]
    .map((item) => String(item || "").trim())
    .filter(Boolean)
    .join(" · ");
  $("#previewEmpresaContacto").text(empresaContacto);

  $("#previewClienteNombre").text(cotizacion.cliente || "SIN CLIENTE");

  const clienteDocumento = [
    String(cotizacion.tipo_documento_cliente || "").trim(),
    String(cotizacion.documento_cliente || "").trim(),
  ]
    .filter(Boolean)
    .join(" ");
  $("#previewClienteDocumento").text(clienteDocumento || "Sin documento registrado");

  const direccionCliente = String(cotizacion.direccion_cliente || "").trim();
  $("#previewClienteDireccion").text(direccionCliente || "Sin dirección registrada");

  const clienteContacto = [cotizacion.celular_cliente, cotizacion.email_cliente]
    .map((item) => String(item || "").trim())
    .filter(Boolean)
    .join(" · ");
  $("#previewClienteContacto").text(clienteContacto || "");

  $("#previewVendedor").text(cotizacion.usuario || "—");
  $("#previewEstadoTexto").text(`Estado: ${status}`);
  $("#previewFormaPago").text(`Forma de pago: ${String(cotizacion.forma_pago || "No especificado")}`);

  const detalleBody = document.getElementById("previewDetalleBody");
  if (detalleBody) {
    if (!detalles.length) {
      detalleBody.innerHTML = `
        <tr>
          <td colspan="5" class="tw-px-7 tw-py-10 tw-text-center tw-text-xs tw-font-semibold tw-text-slate-400">
            No se encontraron productos en esta cotización.
          </td>
        </tr>`;
    } else {
      detalleBody.innerHTML = detalles
        .map((item) => {
          const sku = String(item.sku || "").trim();
          const afectacion = String(item.afectacion || "").trim();
          return `
            <tr class="tw-border-b tw-border-slate-100 last:tw-border-b-0">
              <td class="tw-px-5 tw-py-4 sm:tw-px-7">
                <strong class="tw-block tw-text-[11px] tw-font-extrabold tw-text-slate-800">${cqEscapeHtml(item.nombre || "Producto")}</strong>
                <span class="tw-mt-1 tw-block tw-text-[9px] tw-text-slate-400">${cqEscapeHtml([sku, afectacion].filter(Boolean).join(" · "))}</span>
              </td>
              <td class="tw-px-3 tw-py-4 tw-text-center tw-text-[11px] tw-font-bold tw-text-slate-700">${cqCantidad(item.cantidad)}</td>
              <td class="tw-px-3 tw-py-4 tw-text-right tw-text-[11px] tw-text-slate-600">${cqMoney(item.precio_unitario, simbolo)}</td>
              <td class="tw-px-3 tw-py-4 tw-text-right tw-text-[11px] tw-text-slate-500">${cqNumero(item.descuento) > 0 ? cqMoney(item.descuento, simbolo) : "—"}</td>
              <td class="tw-px-5 tw-py-4 tw-text-right tw-text-[11px] tw-font-extrabold tw-text-slate-900 sm:tw-px-7">${cqMoney(item.importe, simbolo)}</td>
            </tr>`;
        })
        .join("");
    }
  }

  const resumen = document.getElementById("previewResumen");
  if (resumen) {
    const filas = [];
    const pushRow = (label, value, options = {}) => {
      if (!options.always && cqNumero(value) <= 0.009) return;
      filas.push(`
        <div class="tw-flex tw-items-center tw-justify-between tw-gap-4 ${options.total ? "tw-mt-3 tw-border-t tw-border-slate-200 tw-pt-3" : "tw-py-1.5"}">
          <span class="${options.total ? "tw-text-xs tw-font-extrabold tw-text-slate-900" : "tw-text-[10px] tw-font-semibold tw-text-slate-500"}">${cqEscapeHtml(label)}</span>
          <strong class="${options.total ? "tw-text-lg tw-font-black tw-text-tique-700" : "tw-text-[11px] tw-font-extrabold tw-text-slate-700"}">${options.negative ? "− " : ""}${cqMoney(value, simbolo)}</strong>
        </div>`);
    };

    pushRow("Operación gravada", cotizacion.total_gravado);
    pushRow("Operación exonerada", cotizacion.total_exonerado);
    pushRow("Operación inafecta", cotizacion.total_inafecto);
    pushRow("Exportación", cotizacion.total_exportacion);
    pushRow(String(empresa.nombre_impuesto || "IGV"), cotizacion.total_igv);
    pushRow("Descuento", cotizacion.descuento_total, { negative: true });
    pushRow("Total", cotizacion.total_venta, { total: true, always: true });

    resumen.innerHTML = filas.join("");
  }

  const btnEjecutar = document.getElementById("btnPreviewEjecutar");
  if (btnEjecutar) {
    btnEjecutar.classList.toggle("tw-hidden", !cotizacionPreviewPending);
    btnEjecutar.classList.toggle("tw-inline-flex", cotizacionPreviewPending);
  }
}

async function mostrar(idventa) {
  const id = Number(idventa || 0);
  if (id <= 0) return;

  abrirPreviewCotizacion();
  setPreviewLoading(true);

  try {
    const respuesta = await fetch(
      `Controllers/Sell.php?op=vistaCotizacion&idventa=${encodeURIComponent(id)}&_=${Date.now()}`,
      {
        credentials: "same-origin",
        cache: "no-store",
        headers: { Accept: "application/json" },
      }
    );

    const data = await respuesta.json();
    if (!respuesta.ok || data.success === false) {
      throw new Error(data.mensaje || "No se pudo cargar la cotización.");
    }

    renderPreviewCotizacion(data);
    setPreviewLoading(false);
  } catch (error) {
    console.error(error);
    cerrarPreviewCotizacion();

    if (window.Swal?.fire) {
      Swal.fire({
        icon: "error",
        title: "No se pudo abrir la cotización",
        text: error.message || "Intenta nuevamente.",
      });
    } else {
      alert(error.message || "No se pudo abrir la cotización.");
    }
  }
}

function ejecutarCotizacion(idventa) {
  const id = Number(idventa || 0);
  if (id <= 0) return;

  window.location.assign(`pos?ejecutar_cotizacion=${encodeURIComponent(id)}`);
}

function vincularPreview() {
  document.querySelectorAll("[data-cot-preview-close]").forEach((element) => {
    element.addEventListener("click", cerrarPreviewCotizacion);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      const modal = document.getElementById("cotizacionPreviewModal");
      if (modal && !modal.classList.contains("tw-hidden")) {
        cerrarPreviewCotizacion();
      }
    }
  });

  document.getElementById("btnPreviewImprimir")?.addEventListener("click", () => {
    if (!cotizacionPreviewPrintUrl) return;
    window.open(cotizacionPreviewPrintUrl, "_blank", "noopener");
  });

  document.getElementById("btnPreviewEjecutar")?.addEventListener("click", () => {
    if (cotizacionPreviewPending && cotizacionPreviewId > 0) {
      ejecutarCotizacion(cotizacionPreviewId);
    }
  });
}

function init() {
  listar();
  vincularFiltrosEstado();
  vincularPreview();
  cargarResumenCotizaciones();
  iniciarActualizacionCotizaciones();
}

window.ejecutarCotizacion = ejecutarCotizacion;
window.mostrar = mostrar;
window.notificarCotizacionesActualizadas = notificarCotizacionesActualizadas;

init();
