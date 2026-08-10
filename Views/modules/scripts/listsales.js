// Views/modules/scripts/listsales.js

let tabla = null;
let tablaNotasCredito = null;
let tipoDocumentoActivo = "ventas";
let filtroPremiumRegistrado = false;
let temporizadorBusquedaDocumentos = null;
let tipoComprobanteFiltro = "TODOS";

/*
|--------------------------------------------------------------------------
| INICIALIZACIÓN
|--------------------------------------------------------------------------
*/
function init() {
  registrarFiltroPremiumDataTables();
  registrarEventosFiltrosPremium();

  /*
   * Al ingresar a ListSales, mostrar por defecto únicamente
   * los comprobantes del mes actual: desde el día 1 hasta hoy.
   * El usuario conserva todos los filtros existentes y puede
   * cambiar a Todo el historial, Hoy, Últimos 7 días o Personalizado.
   */
  inicializarFiltroMesActual();

  listar();
  listarNotasCredito();

  const tipoInicial =
    obtenerTipoDocumentoDesdeUrl();

  if (tipoInicial === "notas") {
    seleccionarTipoComprobante(
      "NOTA_CREDITO",
      "Nota de crédito electrónica",
      "notas",
      "fa-file-invoice-dollar",
      false
    );
  } else {
    seleccionarTipoComprobante(
      "TODOS",
      "Todos los documentos de venta",
      "ventas",
      "fa-layer-group",
      false
    );
  }
}

$(document).on("click", "#btnagregar", function () {
  window.location.href = "newsale3";
});

/*
|--------------------------------------------------------------------------
| LISTADO DE VENTAS
|--------------------------------------------------------------------------
*/
function listar() {
  tabla = $("#tbllistado")
    .DataTable({
      processing: true,
      serverSide: false,
      dom: "Brtip",

      buttons: [
        {
          extend: "excelHtml5",
          text: '<i class="far fa-file-excel"></i> Exportar Excel',
          className: "btn btn-sm btn-export-excel",
          titleAttr: "Exportar a Excel",
          title: "Reporte de Ventas",
          sheetName: "Ventas",
          exportOptions: {
            columns: [0, 1, 2, 3, 4, 5, 6],
          },
        },
        {
          extend: "pdfHtml5",
          text: '<i class="far fa-file-pdf"></i> Exportar PDF',
          className: "btn btn-sm btn-export-pdf",
          titleAttr: "Exportar a PDF",
          title: "Reporte de Ventas",
          pageSize: "A4",
          exportOptions: {
            columns: [0, 1, 2, 3, 4, 5, 6],
          },
        },
      ],

      ajax: {
        url: "Controllers/Sell.php?op=listar",
        type: "GET",
        dataType: "json",

        dataSrc: function (respuesta) {
          const registros =
            respuesta &&
            Array.isArray(respuesta.aaData)
              ? respuesta.aaData
              : [];

          $("#contadorVentas").text(
            registros.length
          );

          return registros;
        },

        error: function (xhr) {
          console.error(
            "Error al listar ventas:",
            xhr.responseText
          );
        },
      },

      destroy: true,
      responsive: true,
      autoWidth: false,
      pageLength: 10,
      order: [],

      initComplete: function () {
        moverExportadoresDataTable(
          tabla,
          "#exportadoresVentas"
        );

        aplicarFiltrosPremium();
      },

      drawCallback: function () {
        actualizarResumenResultados();
      },

      columnDefs: [
        {
          targets: "_all",
          className: "align-middle",
        },
        {
          targets: 5,
          className: "text-right align-middle",
        },
        {
          targets: 6,
          className: "text-center align-middle",
        },
        {
          targets: 7,
          orderable: false,
          searchable: false,
          className: "text-right align-middle",
        },
      ],
    });
}


/*
|--------------------------------------------------------------------------
| LISTADO DE NOTAS DE CRÉDITO
|--------------------------------------------------------------------------
*/
function listarNotasCredito() {
  tablaNotasCredito =
    $("#tblNotasCredito")
      .DataTable({
        processing: true,
        serverSide: false,
        dom: "Brtip",

        buttons: [
          {
            extend: "excelHtml5",
            text:
              '<i class="far fa-file-excel"></i> Exportar Excel',
            className:
              "btn btn-sm btn-export-excel",
            titleAttr:
              "Exportar notas de crédito a Excel",
            title:
              "Reporte de Notas de Crédito",
            sheetName:
              "Notas de crédito",
            exportOptions: {
              columns: [
                0,
                1,
                2,
                3,
                4,
                5,
                6,
                7,
              ],
            },
          },
          {
            extend: "pdfHtml5",
            text:
              '<i class="far fa-file-pdf"></i> Exportar PDF',
            className:
              "btn btn-sm btn-export-pdf",
            titleAttr:
              "Exportar notas de crédito a PDF",
            title:
              "Reporte de Notas de Crédito",
            pageSize:
              "A4",
            orientation:
              "landscape",
            exportOptions: {
              columns: [
                0,
                1,
                2,
                3,
                4,
                5,
                6,
                7,
              ],
            },
          },
        ],

        ajax: {
          url:
            "Controllers/Sell.php" +
            "?op=listarnotascredito",

          type:
            "GET",

          dataType:
            "json",

          dataSrc: function (respuesta) {
            const registros =
              respuesta &&
              Array.isArray(
                respuesta.aaData
              )
                ? respuesta.aaData
                : [];

            $("#contadorNotasCredito")
              .text(
                registros.length
              );

            return registros;
          },

          error: function (xhr) {
            console.error(
              "Error al listar notas de crédito:",
              xhr.responseText
            );

            $("#contadorNotasCredito")
              .text("0");
          },
        },

        destroy:
          true,

        responsive:
          true,

        autoWidth:
          false,

        pageLength:
          10,

        order:
          [],

        initComplete: function () {
          moverExportadoresDataTable(
            tablaNotasCredito,
            "#exportadoresNotas"
          );

          aplicarFiltrosPremium();
        },

        drawCallback: function () {
          actualizarResumenResultados();
        },

        columnDefs: [
          {
            targets:
              "_all",
            className:
              "align-middle",
          },
          {
            targets:
              5,
            className:
              "align-middle",
          },
          {
            targets:
              6,
            className:
              "text-right align-middle",
          },
          {
            targets:
              7,
            className:
              "text-center align-middle",
          },
          {
            targets:
              8,
            orderable:
              false,
            searchable:
              false,
            className:
              "text-right align-middle",
          },
        ],
      });
}

/*
|--------------------------------------------------------------------------
| AJUSTAR TABLAS AL CAMBIAR DE PESTAÑA
|--------------------------------------------------------------------------
*/
function registrarEventosFiltrosPremium() {
  $("#filtroTipoDocumentoBtn")
    .off("click.tipoDocumento")
    .on(
      "click.tipoDocumento",
      function (evento) {
        evento.preventDefault();
        evento.stopPropagation();

        alternarSelectorTipoDocumento();
      }
    );

  $(document)
    .off(
      "click.tipoDocumento",
      ".ventas-document-type-option"
    )
    .on(
      "click.tipoDocumento",
      ".ventas-document-type-option",
      function () {
        seleccionarTipoComprobante(
          String(
            $(this).data("tipo-comprobante")
            || "TODOS"
          ),
          String(
            $(this).data("etiqueta")
            || "Todos los documentos de venta"
          ),
          String(
            $(this).data("destino")
            || "ventas"
          ),
          String(
            $(this).data("icono")
            || "fa-layer-group"
          )
        );
      }
    );

  $("#buscarTipoDocumento")
    .off("input.tipoDocumento")
    .on(
      "input.tipoDocumento",
      function () {
        filtrarOpcionesTipoDocumento(
          String($(this).val() || "")
        );
      }
    );

  $(document)
    .off("click.cerrarTipoDocumento")
    .on(
      "click.cerrarTipoDocumento",
      function (evento) {
        if (
          $(evento.target)
            .closest(
              "#ventasDocumentTypeSelect"
            )
            .length === 0
        ) {
          cerrarSelectorTipoDocumento();
        }
      }
    );

  $(document)
    .off("keydown.tipoDocumento")
    .on(
      "keydown.tipoDocumento",
      function (evento) {
        if (evento.key === "Escape") {
          cerrarSelectorTipoDocumento();
        }
      }
    );

  $("#filtroPeriodo")
    .off("change.filtrosVentas")
    .on(
      "change.filtrosVentas",
      function () {
        aplicarPeriodoSeleccionado(
          String($(this).val() || "todos")
        );
      }
    );

  $(
    "#filtroFechaDesde, #filtroFechaHasta"
  )
    .off("change.filtrosVentas")
    .on(
      "change.filtrosVentas",
      function () {
        $("#filtroPeriodo")
          .val("personalizado");

        aplicarFiltrosPremium();
      }
    );

  $("#filtroEstadoSunat")
    .off("change.filtrosVentas")
    .on(
      "change.filtrosVentas",
      function () {
        aplicarFiltrosPremium();
      }
    );

  $("#filtroBusquedaDocumentos")
    .off("input.filtrosVentas")
    .on(
      "input.filtrosVentas",
      function () {
        window.clearTimeout(
          temporizadorBusquedaDocumentos
        );

        temporizadorBusquedaDocumentos =
          window.setTimeout(
            aplicarFiltrosPremium,
            180
          );
      }
    );

  $("#btnLimpiarFiltros")
    .off("click.filtrosVentas")
    .on(
      "click.filtrosVentas",
      function () {
        limpiarFiltrosPremium();
      }
    );
}

function alternarSelectorTipoDocumento() {
  const contenedor =
    $("#ventasDocumentTypeSelect");

  const abierto =
    contenedor.hasClass("is-open");

  if (abierto) {
    cerrarSelectorTipoDocumento();
    return;
  }

  contenedor.addClass(
    "is-open"
  );

  $("#filtroTipoDocumentoBtn")
    .attr(
      "aria-expanded",
      "true"
    );

  $("#buscarTipoDocumento")
    .val("");

  filtrarOpcionesTipoDocumento(
    ""
  );

  window.setTimeout(
    function () {
      $("#buscarTipoDocumento")
        .trigger("focus");
    },
    40
  );
}

function cerrarSelectorTipoDocumento() {
  $("#ventasDocumentTypeSelect")
    .removeClass(
      "is-open"
    );

  $("#filtroTipoDocumentoBtn")
    .attr(
      "aria-expanded",
      "false"
    );
}

function filtrarOpcionesTipoDocumento(
  termino
) {
  const busqueda =
    normalizarTextoFiltro(
      termino
    );

  let visibles = 0;

  $(".ventas-document-type-option")
    .each(function () {
      const opcion =
        $(this);

      const texto =
        normalizarTextoFiltro(
          opcion.text()
        );

      const mostrar =
        busqueda === ""
        || texto.includes(
          busqueda
        );

      opcion.toggle(
        mostrar
      );

      if (mostrar) {
        visibles++;
      }
    });

  $(".ventas-document-type-group")
    .each(function () {
      const grupo =
        $(this);

      let siguiente =
        grupo.next();

      let tieneVisible =
        false;

      while (
        siguiente.length
        && !siguiente.hasClass(
          "ventas-document-type-group"
        )
      ) {
        if (
          siguiente.hasClass(
            "ventas-document-type-option"
          )
          && siguiente.is(":visible")
        ) {
          tieneVisible = true;
          break;
        }

        siguiente =
          siguiente.next();
      }

      grupo.toggle(
        tieneVisible
      );
    });

  $("#sinTiposDocumento")
    .toggle(
      visibles === 0
    );
}

function seleccionarTipoComprobante(
  tipo,
  etiqueta,
  destino,
  icono,
  actualizarUrl = true
) {
  tipoComprobanteFiltro =
    String(tipo || "TODOS");

  destino =
    destino === "notas"
      ? "notas"
      : "ventas";

  $("#filtroTipoDocumentoTexto")
    .text(
      etiqueta
      || "Todos los documentos de venta"
    );

  $("#filtroTipoDocumentoIcono")
    .attr(
      "class",
      "fas " +
      String(
        icono || "fa-layer-group"
      )
    );

  $(".ventas-document-type-option")
    .removeClass("active")
    .filter(
      '[data-tipo-comprobante="' +
      tipoComprobanteFiltro +
      '"]'
    )
    .addClass("active");

  cerrarSelectorTipoDocumento();

  cambiarTipoDocumento(
    destino,
    actualizarUrl
  );

  aplicarFiltrosPremium();

  if (actualizarUrl) {
    actualizarTipoComprobanteUrl(
      tipoComprobanteFiltro
    );
  }
}

function actualizarTipoComprobanteUrl(
  tipo
) {
  try {
    const url =
      new URL(
        window.location.href
      );

    if (tipo === "TODOS") {
      url.searchParams.delete(
        "tipo"
      );
    } else {
      url.searchParams.set(
        "tipo",
        String(tipo)
          .toLowerCase()
      );
    }

    window.history.replaceState(
      {},
      "",
      url.toString()
    );
  } catch (error) {
    console.warn(
      "No se pudo actualizar el tipo en la URL:",
      error
    );
  }
}

function obtenerBusquedaTipoComprobante() {
  switch (
    tipoComprobanteFiltro
  ) {
    case "FACTURA":
      return "FACTURA";

    case "BOLETA":
      return "BOLETA";

    case "NOTA_VENTA":
      return "NOTA DE VENTA";

    case "RECIBO":
      return "RECIBO";

    case "COTIZACION":
      return "COTIZ";

    default:
      return "";
  }
}

function obtenerTipoDocumentoDesdeUrl() {
  try {
    const parametros =
      new URLSearchParams(
        window.location.search || ""
      );

    const valor = String(
      parametros.get("tab") || ""
    )
      .toLowerCase()
      .trim();

    if (
      valor === "notas"
      || valor === "nota"
      || valor === "notas-credito"
    ) {
      return "notas";
    }
  } catch (error) {
    console.warn(
      "No se pudo leer la pestaña inicial:",
      error
    );
  }

  return "ventas";
}

function cambiarTipoDocumento(
  tipo,
  actualizarUrl = true
) {
  tipoDocumentoActivo =
    tipo === "notas"
      ? "notas"
      : "ventas";

  const mostrandoVentas =
    tipoDocumentoActivo === "ventas";

  $("#ventas-panel")
    .toggleClass(
      "d-none",
      !mostrandoVentas
    );

  $("#notas-credito-panel")
    .toggleClass(
      "d-none",
      mostrandoVentas
    );

  $("#exportadoresVentas")
    .toggleClass(
      "d-none",
      !mostrandoVentas
    );

  $("#exportadoresNotas")
    .toggleClass(
      "d-none",
      mostrandoVentas
    );

  /*
   * El documento activo se controla desde el selector desplegable.
   * Las tarjetas superiores fueron retiradas.
   */

  if (actualizarUrl) {
    actualizarParametroDocumentoUrl(
      tipoDocumentoActivo
    );
  }

  window.setTimeout(
    function () {
      const tablaActiva =
        obtenerTablaActiva();

      if (tablaActiva) {
        tablaActiva
          .columns
          .adjust();

        if (
          tablaActiva.responsive &&
          typeof tablaActiva.responsive
            .recalc === "function"
        ) {
          tablaActiva.responsive.recalc();
        }

        tablaActiva.draw(false);
      }

      actualizarResumenResultados();
    },
    30
  );
}

function actualizarParametroDocumentoUrl(
  tipo
) {
  try {
    const url =
      new URL(
        window.location.href
      );

    url.searchParams.set(
      "tab",
      tipo === "notas"
        ? "notas"
        : "ventas"
    );

    window.history.replaceState(
      {},
      "",
      url.toString()
    );
  } catch (error) {
    console.warn(
      "No se pudo actualizar la URL:",
      error
    );
  }
}

function inicializarFiltroMesActual() {
  const hoy =
    new Date();

  const inicioMes =
    new Date(
      hoy.getFullYear(),
      hoy.getMonth(),
      1
    );

  $("#filtroPeriodo").val(
    "mes"
  );

  $("#filtroFechaDesde").val(
    formatearFechaInput(
      inicioMes
    )
  );

  $("#filtroFechaHasta").val(
    formatearFechaInput(
      hoy
    )
  );
}

function aplicarPeriodoSeleccionado(
  periodo
) {
  const hoy =
    new Date();

  let desde = "";
  let hasta = "";

  if (periodo === "hoy") {
    desde =
      formatearFechaInput(hoy);

    hasta =
      desde;
  } else if (periodo === "7dias") {
    const fechaDesde =
      new Date(hoy);

    fechaDesde.setDate(
      hoy.getDate() - 6
    );

    desde =
      formatearFechaInput(
        fechaDesde
      );

    hasta =
      formatearFechaInput(
        hoy
      );
  } else if (periodo === "mes") {
    const inicioMes =
      new Date(
        hoy.getFullYear(),
        hoy.getMonth(),
        1
      );

    desde =
      formatearFechaInput(
        inicioMes
      );

    hasta =
      formatearFechaInput(
        hoy
      );
  } else if (
    periodo === "personalizado"
  ) {
    aplicarFiltrosPremium();
    return;
  }

  $("#filtroFechaDesde").val(
    desde
  );

  $("#filtroFechaHasta").val(
    hasta
  );

  aplicarFiltrosPremium();
}

function formatearFechaInput(
  fecha
) {
  const anio =
    fecha.getFullYear();

  const mes =
    String(
      fecha.getMonth() + 1
    ).padStart(2, "0");

  const dia =
    String(
      fecha.getDate()
    ).padStart(2, "0");

  return (
    anio +
    "-" +
    mes +
    "-" +
    dia
  );
}

function registrarFiltroPremiumDataTables() {
  if (filtroPremiumRegistrado) {
    return;
  }

  $.fn.dataTable.ext.search.push(
    function (
      settings,
      data
    ) {
      const tablaId =
        settings &&
        settings.nTable
          ? settings.nTable.id
          : "";

      if (
        tablaId !== "tbllistado"
        && tablaId !== "tblNotasCredito"
      ) {
        return true;
      }

      const desde =
        String(
          $("#filtroFechaDesde").val() || ""
        );

      const hasta =
        String(
          $("#filtroFechaHasta").val() || ""
        );

      const estadoBuscado =
        normalizarTextoFiltro(
          $("#filtroEstadoSunat").val()
        );

      const fechaRegistro =
        convertirFechaTabla(
          data[0]
        );

      if (
        desde !== ""
        && fechaRegistro
        && fechaRegistro <
          crearFechaLocal(
            desde,
            false
          )
      ) {
        return false;
      }

      if (
        hasta !== ""
        && fechaRegistro
        && fechaRegistro >
          crearFechaLocal(
            hasta,
            true
          )
      ) {
        return false;
      }

      if (estadoBuscado !== "") {
        const indiceEstado =
          tablaId === "tbllistado"
            ? 6
            : 7;

        const estadoFila =
          normalizarTextoFiltro(
            data[indiceEstado]
          );

        if (
          !estadoFila.includes(
            estadoBuscado
          )
        ) {
          return false;
        }
      }

      return true;
    }
  );

  filtroPremiumRegistrado =
    true;
}

function convertirFechaTabla(
  valor
) {
  const texto =
    limpiarHtmlFiltro(
      valor
    ).trim();

  const coincidencia =
    texto.match(
      /(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?/
    );

  if (!coincidencia) {
    return null;
  }

  return new Date(
    Number(coincidencia[3]),
    Number(coincidencia[2]) - 1,
    Number(coincidencia[1]),
    Number(coincidencia[4] || 0),
    Number(coincidencia[5] || 0),
    0,
    0
  );
}

function crearFechaLocal(
  valor,
  finDelDia
) {
  const partes =
    String(valor)
      .split("-")
      .map(Number);

  if (partes.length !== 3) {
    return null;
  }

  return new Date(
    partes[0],
    partes[1] - 1,
    partes[2],
    finDelDia ? 23 : 0,
    finDelDia ? 59 : 0,
    finDelDia ? 59 : 0,
    finDelDia ? 999 : 0
  );
}

function normalizarTextoFiltro(
  valor
) {
  return limpiarHtmlFiltro(
    valor
  )
    .normalize("NFD")
    .replace(
      /[\u0300-\u036f]/g,
      ""
    )
    .toUpperCase()
    .trim();
}

function limpiarHtmlFiltro(
  valor
) {
  return $("<div>")
    .html(
      String(valor || "")
    )
    .text();
}

function aplicarFiltrosPremium() {
  const busqueda =
    String(
      $("#filtroBusquedaDocumentos").val() || ""
    ).trim();

  if (tabla) {
    tabla
      .column(3)
      .search(
        obtenerBusquedaTipoComprobante()
      );

    tabla
      .search(
        busqueda
      )
      .draw(false);
  }

  if (tablaNotasCredito) {
    tablaNotasCredito
      .search(
        busqueda
      )
      .draw(false);
  }

  actualizarResumenResultados();
}

function limpiarFiltrosPremium() {
  $("#filtroPeriodo").val(
    "todos"
  );

  $("#filtroFechaDesde").val(
    ""
  );

  $("#filtroFechaHasta").val(
    ""
  );

  $("#filtroEstadoSunat").val(
    ""
  );

  $("#filtroBusquedaDocumentos").val(
    ""
  );

  seleccionarTipoComprobante(
    "TODOS",
    "Todos los documentos de venta",
    "ventas",
    "fa-layer-group"
  );
}

function moverExportadoresDataTable(
  instancia,
  selectorDestino
) {
  if (
    !instancia
    || typeof instancia.buttons
      !== "function"
  ) {
    return;
  }

  const contenedor =
    instancia.buttons()
      .container();

  $(selectorDestino)
    .empty()
    .append(
      contenedor
    );
}

function obtenerTablaActiva() {
  return tipoDocumentoActivo === "notas"
    ? tablaNotasCredito
    : tabla;
}

function actualizarResumenResultados() {
  const tablaActiva =
    obtenerTablaActiva();

  if (
    !tablaActiva
    || typeof tablaActiva.page
      !== "function"
  ) {
    return;
  }

  const informacion =
    tablaActiva.page.info();

  const etiqueta =
    tipoDocumentoActivo === "notas"
      ? "notas de crédito"
      : "ventas";

  const totalVisible =
    Number(
      informacion.recordsDisplay || 0
    );

  const totalGeneral =
    Number(
      informacion.recordsTotal || 0
    );

  let texto =
    totalVisible +
    " " +
    (
      totalVisible === 1
        ? etiqueta.replace(
            "notas de crédito",
            "nota de crédito"
          ).replace(
            "ventas",
            "venta"
          )
        : etiqueta
    );

  if (
    totalVisible !== totalGeneral
  ) {
    texto +=
      " de " +
      totalGeneral +
      " registradas";
  } else {
    texto +=
      " registradas";
  }

  $("#resumenFiltroActual")
    .text(
      texto
    );
}

/*
|--------------------------------------------------------------------------
| MOSTRAR VENTA
|--------------------------------------------------------------------------
*/
function mostrar(idventa) {
  const id = parseInt(idventa, 10);

  if (!id || id <= 0) {
    swal(
      "Error",
      "El ID de venta no es válido.",
      "error"
    );

    return;
  }

  limpiarVistaVenta();

  $("#getCodeModal").modal("show");

  /*
  |--------------------------------------------------------------------------
  | CABECERA
  |--------------------------------------------------------------------------
  */
  $.ajax({
    url: "Controllers/Sell.php?op=mostrar",
    type: "POST",
    dataType: "json",
    data: {
      idventa: id,
    },

    success: function (data) {
      console.log("VENTA:", data);

      if (
        !data ||
        typeof data !== "object" ||
        !data.idventa
      ) {
        swal(
          "Venta no encontrada",
          "No se pudo cargar la información de la venta.",
          "warning"
        );

        return;
      }

      $("#idventam").val(
        data.idventa || ""
      );

      $("#cliente").val(
        data.cliente || "SIN CLIENTE"
      );

      $("#fecha_horam").val(
        data.fecha || ""
      );

      $("#tipo_comprobantem").val(
        data.tipo_comprobante || ""
      );

      $("#serie_comprobantem").val(
        data.serie_comprobante || ""
      );

      $("#num_comprobantem").val(
        data.num_comprobante || ""
      );

      $("#impuestom").val(
        formatearNumero(
          data.impuesto || 0
        )
      );

      const totalVenta =
        parseFloat(data.total_venta) || 0;

      const descuentoVenta =
        parseFloat(data.descuento_total) || 0;

      $("#total_ventam").text(
        "S/ " + formatearNumero(totalVenta)
      );

      $("#descuento_ventam").text(
        "S/ " + formatearNumero(descuentoVenta)
      );

      if (descuentoVenta > 0) {
        $("#descuentoResumenWrap").show();
      } else {
        $("#descuentoResumenWrap").hide();
      }

      const comprobanteResumen = [
        String(data.tipo_comprobante || "").trim(),
        [
          String(data.serie_comprobante || "").trim(),
          String(data.num_comprobante || "").trim(),
        ]
          .filter(Boolean)
          .join("-"),
      ]
        .filter(Boolean)
        .join(" · ");

      $("#modalComprobanteResumen").text(
        comprobanteResumen ||
        "Información completa del comprobante"
      );

      /*
      |--------------------------------------------------------------------------
      | FORMA Y CONDICIÓN
      |--------------------------------------------------------------------------
      */
      $("#tipo_pagom").val(
        data.forma_pago ||
        "No especificado"
      );

      const condicion =
        normalizarCondicionPago(
          data.tipo_pago
        );

      $("#condicion_pagom").val(
        condicion
      );

      if (condicion === "CRÉDITO") {
        $("#bloquePagoMixto").hide();
        $("#detallePagom").empty();

        cargarCuotasVenta(id);
      } else {
        $("#bloqueCuotas").hide();
        $("#detalleCuotasm").empty();

        cargarPagosVenta(id);
      }
    },

    error: function (xhr) {
      console.error(
        "Error al cargar la venta:",
        xhr.responseText
      );

      swal(
        "Error",
        "No se pudo cargar la cabecera de la venta.",
        "error"
      );
    },
  });

  /*
  |--------------------------------------------------------------------------
  | DETALLE DE PRODUCTOS
  |--------------------------------------------------------------------------
  */
  $.ajax({
    url:
      "Controllers/Sell.php?op=listarDetalle&id=" +
      encodeURIComponent(id),

    type: "POST",

    success: function (html) {
      $("#detallesm").html(html);
    },

    error: function (xhr) {
      console.error(
        "Error al cargar productos:",
        xhr.responseText
      );

      $("#detallesm").html(`
        <tbody>
          <tr>
            <td colspan="5" class="venta-detalle-vacio text-danger">
              No se pudo cargar el detalle de productos.
            </td>
          </tr>
        </tbody>
      `);
    },
  });
}

/*
|--------------------------------------------------------------------------
| PAGOS DE VENTA AL CONTADO
|--------------------------------------------------------------------------
*/
function cargarPagosVenta(idventa) {
  $.ajax({
    url:
      "Controllers/Sell.php?op=pagos&idventa=" +
      encodeURIComponent(idventa),

    type: "GET",
    dataType: "json",

    success: function (pagos) {
      const tbody = $("#detallePagom");

      tbody.empty();

      if (
        !Array.isArray(pagos) ||
        pagos.length === 0
      ) {
        $("#bloquePagoMixto").hide();
        return;
      }

      let totalPagado = 0;

      pagos.forEach(function (pago) {
        const nombre =
          escaparHtml(
            pago.nombre ||
            "No especificado"
          );

        const monto =
          parseFloat(pago.monto) || 0;

        totalPagado += monto;

        tbody.append(`
          <tr>
            <td>${nombre}</td>
            <td class="text-right">
              S/ ${formatearNumero(monto)}
            </td>
          </tr>
        `);
      });

      tbody.append(`
        <tr>
          <th class="text-right">
            Total pagado
          </th>

          <th class="text-right">
            S/ ${formatearNumero(totalPagado)}
          </th>
        </tr>
      `);

      /*
       * Ahora se muestra también cuando existe
       * un solo método de pago.
       */
      $("#bloquePagoMixto").show();
    },

    error: function (xhr) {
      console.error(
        "Error al cargar pagos:",
        xhr.responseText
      );

      $("#bloquePagoMixto").hide();
    },
  });
}

/*
|--------------------------------------------------------------------------
| CUOTAS DE VENTA AL CRÉDITO
|--------------------------------------------------------------------------
*/
function cargarCuotasVenta(idventa) {
  $.ajax({
    url:
      "Controllers/Sell.php?op=cuotas&idventa=" +
      encodeURIComponent(idventa),

    type: "GET",
    dataType: "json",

    success: function (cuotas) {
      const tbody = $("#detalleCuotasm");

      tbody.empty();

      $("#totalPendienteCuotasm").text(
        "S/ 0.00"
      );

      $("#resumenCuotasm").text("");

      if (
        !Array.isArray(cuotas) ||
        cuotas.length === 0
      ) {
        tbody.html(`
          <tr>
            <td
              colspan="6"
              class="text-center text-muted">

              Esta venta al crédito no tiene cuotas registradas.

            </td>
          </tr>
        `);

        $("#bloqueCuotas").show();

        return;
      }

      let totalPendiente = 0;
      let totalCredito = 0;

      cuotas.forEach(function (cuota) {
        const codigo =
          escaparHtml(
            cuota.codigo ||
            `Cuota${String(
              cuota.numero_cuota || ""
            ).padStart(3, "0")}`
          );

        const monto =
          parseFloat(cuota.monto) || 0;

        const pagado =
          parseFloat(
            cuota.monto_pagado
          ) || 0;

        const saldo =
          parseFloat(cuota.saldo) ||
          Math.max(
            monto - pagado,
            0
          );

        totalCredito += monto;
        totalPendiente += saldo;

        const fecha =
          escaparHtml(
            cuota.fecha_vencimiento ||
            ""
          );

        const estadoOriginal =
          String(
            cuota.estado ||
            "PENDIENTE"
          )
            .trim()
            .toUpperCase();

        const badge =
          obtenerBadgeCuota(
            estadoOriginal
          );

        tbody.append(`
          <tr>
            <td>
              <strong>${codigo}</strong>
            </td>

            <td class="text-right">
              S/ ${formatearNumero(monto)}
            </td>

            <td>
              ${fecha}
            </td>

            <td class="text-right">
              S/ ${formatearNumero(pagado)}
            </td>

            <td class="text-right">
              <strong>
                S/ ${formatearNumero(saldo)}
              </strong>
            </td>

            <td class="text-center">
              ${badge}
            </td>
          </tr>
        `);
      });

      $("#totalPendienteCuotasm").text(
        "S/ " +
        formatearNumero(
          totalPendiente
        )
      );

      $("#resumenCuotasm").text(
        cuotas.length +
        (
          cuotas.length === 1
            ? " cuota"
            : " cuotas"
        ) +
        " · Total S/ " +
        formatearNumero(
          totalCredito
        )
      );

      $("#bloqueCuotas").show();
    },

    error: function (xhr) {
      console.error(
        "Error al cargar cuotas:",
        xhr.responseText
      );

      $("#detalleCuotasm").html(`
        <tr>
          <td
            colspan="6"
            class="text-center text-danger">

            No se pudo cargar el cronograma de cuotas.

          </td>
        </tr>
      `);

      $("#bloqueCuotas").show();
    },
  });
}

/*
|--------------------------------------------------------------------------
| LIMPIAR MODAL
|--------------------------------------------------------------------------
*/
function limpiarVistaVenta() {
  $("#idventam").val("");
  $("#cliente").val("");
  $("#fecha_horam").val("");
  $("#tipo_comprobantem").val("");
  $("#serie_comprobantem").val("");
  $("#num_comprobantem").val("");
  $("#impuestom").val("");
  $("#tipo_pagom").val("");
  $("#condicion_pagom").val("");
  $("#total_ventam").text("S/ 0.00");
  $("#descuento_ventam").text("S/ 0.00");
  $("#descuentoResumenWrap").hide();
  $("#modalComprobanteResumen").text(
    "Información completa del comprobante"
  );

  $("#detallePagom").empty();
  $("#detalleCuotasm").empty();

  $("#bloquePagoMixto").hide();
  $("#bloqueCuotas").hide();

  $("#totalPendienteCuotasm").text(
    "S/ 0.00"
  );

  $("#resumenCuotasm").text("");

  $("#detallesm").html(`
    <tbody>
      <tr>
        <td colspan="5" class="venta-detalle-vacio">
          Cargando detalle...
        </td>
      </tr>
    </tbody>
  `);
}

/*
|--------------------------------------------------------------------------
| NORMALIZAR CONTADO / CRÉDITO
|--------------------------------------------------------------------------
*/
function normalizarCondicionPago(valor) {
  const texto = String(
    valor || ""
  )
    .trim()
    .toUpperCase()
    .normalize("NFD")
    .replace(
      /[\u0300-\u036f]/g,
      ""
    );

  if (
    texto === "4" ||
    texto.includes("CREDITO")
  ) {
    return "CRÉDITO";
  }

  if (
    texto === "1" ||
    texto.includes("CONTADO")
  ) {
    return "CONTADO";
  }

  return texto || "NO ESPECIFICADO";
}

/*
|--------------------------------------------------------------------------
| ESTADO VISUAL DE CUOTA
|--------------------------------------------------------------------------
*/
function obtenerBadgeCuota(estado) {
  switch (estado) {
    case "PAGADO":
      return `
        <span class="venta-estado-cuota cuota-pagada">
          Pagado
        </span>
      `;

    case "PARCIAL":
    case "PAGO_PARCIAL":
      return `
        <span class="venta-estado-cuota cuota-parcial">
          Pago parcial
        </span>
      `;

    case "VENCIDO":
      return `
        <span class="venta-estado-cuota cuota-vencida">
          Vencido
        </span>
      `;

    case "ANULADO":
      return `
        <span class="venta-estado-cuota cuota-anulada">
          Anulado
        </span>
      `;

    case "PENDIENTE":
    default:
      return `
        <span class="venta-estado-cuota cuota-pendiente">
          Pendiente
        </span>
      `;
  }
}

/*
|--------------------------------------------------------------------------
| UTILIDADES
|--------------------------------------------------------------------------
*/
function formatearNumero(valor) {
  const numero =
    parseFloat(valor) || 0;

  return numero.toLocaleString(
    "es-PE",
    {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }
  );
}

function escaparHtml(valor) {
  return String(valor || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}


/*
|--------------------------------------------------------------------------
| GENERAR NOTA DE CRÉDITO
|--------------------------------------------------------------------------
*/
function generarNotaCredito(idventa) {
  const id = Number.parseInt(idventa, 10);

  if (!Number.isInteger(id) || id <= 0) {
    if (
      window.Swal &&
      typeof window.Swal.fire === "function"
    ) {
      window.Swal.fire({
        icon: "warning",
        title: "Venta no válida",
        text: "No se pudo identificar la venta para generar la nota de crédito.",
      });
    } else if (typeof window.swal === "function") {
      window.swal(
        "Venta no válida",
        "No se pudo identificar la venta para generar la nota de crédito.",
        "warning"
      );
    }

    return;
  }

  window.location.href =
    "notacredito?idventa=" +
    encodeURIComponent(id);
}

init();