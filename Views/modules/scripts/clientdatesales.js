// Views/modules/scripts/clientdatesales.js

let tabla = null;

function pad(numero) {
  return String(numero).padStart(2, "0");
}

function ymd(fecha) {
  return (
    fecha.getFullYear() +
    "-" +
    pad(fecha.getMonth() + 1) +
    "-" +
    pad(fecha.getDate())
  );
}

function setRange(tipo) {
  const hoy = new Date();

  let fechaInicio;
  let fechaFin;

  if (tipo === "today") {
    fechaInicio = new Date(hoy);
    fechaFin = new Date(hoy);
  } else if (tipo === "7" || tipo === "30") {
    fechaFin = new Date(hoy);
    fechaInicio = new Date(hoy);

    fechaInicio.setDate(
      fechaInicio.getDate() - (parseInt(tipo, 10) - 1)
    );
  } else if (tipo === "month") {
    fechaInicio = new Date(
      hoy.getFullYear(),
      hoy.getMonth(),
      1
    );

    fechaFin = new Date(hoy);
  } else if (tipo === "prevmonth") {
    const primerDiaMesActual = new Date(
      hoy.getFullYear(),
      hoy.getMonth(),
      1
    );

    fechaFin = new Date(primerDiaMesActual);
    fechaFin.setDate(fechaFin.getDate() - 1);

    fechaInicio = new Date(
      fechaFin.getFullYear(),
      fechaFin.getMonth(),
      1
    );
  } else {
    fechaFin = new Date(hoy);
    fechaInicio = new Date(hoy);
    fechaInicio.setDate(fechaInicio.getDate() - 6);
  }

  $("#fecha_inicio").val(ymd(fechaInicio));
  $("#fecha_fin").val(ymd(fechaFin));
}

function calcularDiferenciaDias(fechaInicio, fechaFin) {
  const inicio = new Date(fechaInicio + "T00:00:00");
  const fin = new Date(fechaFin + "T00:00:00");

  return Math.round(
    (fin.getTime() - inicio.getTime()) /
    (1000 * 60 * 60 * 24)
  );
}

function validarRango(maximoDias) {
  const fechaInicio = $("#fecha_inicio").val();
  const fechaFin = $("#fecha_fin").val();

  if (!fechaInicio || !fechaFin) {
    return {
      ok: false,
      mensaje: "Seleccione un rango de fechas.",
    };
  }

  const diferencia = calcularDiferenciaDias(
    fechaInicio,
    fechaFin
  );

  if (diferencia < 0) {
    return {
      ok: false,
      mensaje:
        "La fecha final no puede ser menor que la fecha inicial.",
    };
  }

  if (diferencia > maximoDias) {
    return {
      ok: false,
      mensaje:
        "El rango máximo permitido es de " +
        maximoDias +
        " días.",
    };
  }

  return {
    ok: true,
    mensaje: "",
  };
}

function destruirTablaSiExiste() {
  if ($.fn.DataTable.isDataTable("#tbllistado")) {
    $("#tbllistado").DataTable().destroy();
    $("#tbllistado tbody").empty();
  }
}

function listar() {
  const validacion = validarRango(60);

  if (!validacion.ok) {
    Swal.fire(
      "Rango no válido",
      validacion.mensaje,
      "warning"
    );

    return;
  }

  const fechaInicio = $("#fecha_inicio").val();
  const fechaFin = $("#fecha_fin").val();

  destruirTablaSiExiste();

  $("#btnMostrar").prop("disabled", true);

  tabla = $("#tbllistado").DataTable({
    processing: true,

    /*
     * El controlador devuelve todos los registros del rango.
     * Por eso debe permanecer en false.
     */
    serverSide: false,

    destroy: true,
    deferRender: true,
    pageLength: 15,

    /*
     * El modelo ya devuelve las ventas ordenadas por fecha real.
     * No ordenar por la fecha visual dd/mm/yyyy.
     */
    order: [],

    scrollX: true,
    autoWidth: false,

    dom: "Bfrtip",

    buttons: [
      {
        extend: "excelHtml5",
        text:
          '<i class="fa fa-file-excel-o"></i> Excel',

        titleAttr: "Exportar a Excel",

        title:
          "Reporte de ventas del " +
          fechaInicio +
          " al " +
          fechaFin,

        sheetName: "Ventas por fecha",

        exportOptions: {
          columns: [
            0, 1, 2, 3, 4, 5,
            6, 7, 8, 9, 10, 11
          ],
        },
      },

      {
        extend: "pdfHtml5",

        text:
          '<i class="fa fa-file-pdf-o"></i> PDF',

        titleAttr: "Exportar a PDF",

        title:
          "Reporte de ventas del " +
          fechaInicio +
          " al " +
          fechaFin,

        orientation: "landscape",
        pageSize: "A3",

        exportOptions: {
          columns: [
            0, 1, 2, 3, 4, 5,
            6, 7, 8, 9, 10, 11
          ],

          stripHtml: true,
        },

        customize: function (documento) {
          if (
            documento.content &&
            documento.content[1] &&
            documento.content[1].table
          ) {
            documento.content[1].table.widths = [
              "10%",
              "10%",
              "13%",
              "9%",
              "8%",
              "7%",
              "7%",
              "10%",
              "11%",
              "7%",
              "8%",
              "8%",
            ];

            documento.content[1].table.body.forEach(
              function (fila) {
                fila.forEach(function (celda) {
                  celda.fontSize = 7;
                });
              }
            );
          }
        },
      },
    ],

    columnDefs: [
      {
        targets: [5, 6],
        className: "text-right",
      },
      {
        targets: [10, 11],
        className: "text-center",
      },
      {
        targets: "_all",
        defaultContent: "",
      },
    ],

    language: {
      processing: "Procesando...",
      search: "Buscar:",
      lengthMenu: "Mostrar _MENU_ registros",
      info:
        "Mostrando _START_ a _END_ de _TOTAL_ registros",
      infoEmpty: "Sin resultados",
      zeroRecords: "No se encontraron registros",

      paginate: {
        first: "Primero",
        last: "Último",
        next: "Siguiente",
        previous: "Anterior",
      },
    },

    ajax: {
      url: "Controllers/Consult.php?op=ventasfecha",
      type: "GET",
      dataType: "json",

      data: {
        fecha_inicio: fechaInicio,
        fecha_fin: fechaFin,
      },

      dataSrc: function (respuesta) {
        if (
          respuesta.success === false
        ) {
          Swal.fire(
            "No se pudo consultar",
            respuesta.mensaje ||
              "No se pudo obtener el reporte.",
            "error"
          );

          return [];
        }

        return Array.isArray(respuesta.aaData)
          ? respuesta.aaData
          : [];
      },

      complete: function () {
        $("#btnMostrar").prop("disabled", false);
      },

      error: function (xhr) {
        $("#btnMostrar").prop("disabled", false);

        console.error(xhr.responseText);

        Swal.fire(
          "Error",
          "No se pudo cargar el reporte de ventas.",
          "error"
        );
      },
    },
  });
}

function init() {
  setRange("today");
  listar();

  $("#fecha_inicio, #fecha_fin").on(
    "change",
    function () {
      listar();
    }
  );
}

$(document).ready(function () {
  init();
});