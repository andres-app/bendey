// clientdatesales.js (UX mejorado - SOLO FECHA)

var tabla;

function pad(n) { return String(n).padStart(2, "0"); }
function ymd(d) { return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()); }

function setRange(type) {
  const now = new Date();
  let ini, fin;

  if (type === "today") {
    ini = new Date(now);
    fin = new Date(now);
  } else if (type === "7" || type === "30") {
    fin = new Date(now);
    ini = new Date(now);
    ini.setDate(ini.getDate() - (parseInt(type, 10) - 1));
  } else if (type === "month") {
    fin = new Date(now);
    ini = new Date(now.getFullYear(), now.getMonth(), 1);
  } else if (type === "prevmonth") {
    const firstThis = new Date(now.getFullYear(), now.getMonth(), 1);
    fin = new Date(firstThis);
    fin.setDate(fin.getDate() - 1); // último día del mes pasado
    ini = new Date(fin.getFullYear(), fin.getMonth(), 1);
  } else {
    // fallback: últimos 7 días
    fin = new Date(now);
    ini = new Date(now);
    ini.setDate(ini.getDate() - 6);
  }

  $("#fecha_inicio").val(ymd(ini));
  $("#fecha_fin").val(ymd(fin));
}

function diffDays(a, b) {
  const da = new Date(a);
  const db = new Date(b);
  return Math.ceil((db - da) / (1000 * 60 * 60 * 24));
}

function validarRango(maxDias) {
  const ini = $("#fecha_inicio").val();
  const fin = $("#fecha_fin").val();

  if (!ini || !fin) return { ok: false, msg: "Seleccione un rango de fechas." };

  const dias = diffDays(ini, fin);
  if (dias < 0) return { ok: false, msg: "La fecha fin no puede ser menor que la fecha inicio." };
  if (dias > maxDias) return { ok: false, msg: `Rango máximo permitido: ${maxDias} días.` };

  return { ok: true, msg: "" };
}

function destruirTablaSiExiste() {
  if ($.fn.DataTable.isDataTable("#tbllistado")) {
    $("#tbllistado").DataTable().destroy();
    $("#tbllistado tbody").empty();
  }
}

// Función listar
function listar() {
  const v = validarRango(60); // cambia 60 por 30/90 según tu BD
  if (!v.ok) { alert(v.msg); return; }

  const fecha_inicio = $("#fecha_inicio").val();
  const fecha_fin = $("#fecha_fin").val();

  destruirTablaSiExiste();

  // UX: bloquear botón mientras carga (si existe)
  $("#btnMostrar").prop("disabled", true);

  tabla = $("#tbllistado").DataTable({
    processing: true,
    serverSide: true,
    destroy: true,
    pageLength: 15,
    order: [[0, "desc"]],
    dom: "Bfrtip",
    buttons: [
      {
        extend: "excelHtml5",
        text: '<i class="fa fa-file-excel-o"></i> Excel',
        titleAttr: "Exportar a Excel",
        title: "Reporte de ventas por fecha",
      },
      {
        extend: "pdfHtml5",
        text: '<i class="fa fa-file-pdf-o"></i> PDF',
        titleAttr: "Exportar a PDF",
        title: "Reporte de ventas por fecha",
      },
    ],
    language: {
      processing: "Procesando...",
      search: "Buscar:",
      lengthMenu: "Mostrar _MENU_",
      info: "Mostrando _START_ a _END_ de _TOTAL_",
      infoEmpty: "Sin resultados",
      zeroRecords: "No se encontraron registros",
      paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" },
    },
    ajax: {
      url: "Controllers/Consult.php?op=ventasfecha",
      type: "GET",
      dataType: "json",
      data: {
        fecha_inicio: fecha_inicio,
        fecha_fin: fecha_fin,
      },
      complete: function () {
        $("#btnMostrar").prop("disabled", false);
      },
      error: function (e) {
        $("#btnMostrar").prop("disabled", false);
        console.log(e.responseText);
      },
    },
  });
}

function init() {
  setRange("today");
  listar();
}

init();