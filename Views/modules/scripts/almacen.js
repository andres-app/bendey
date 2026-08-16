let tabla;

function init() {
  mostrarform(false);
  listar();
  cargarEstadisticas();

  $("#formulario").on("submit", guardaryeditar);
  $("#formEditarAlmacen").on("submit", guardarEdicionAlmacen);

  $("#warehouseSearch").on("input", function () {
    if (tabla) tabla.search(this.value).draw();
  });

  $("#btnWarehouseExportExcel").on("click", function () {
    if (tabla) tabla.button(0).trigger();
  });
  $("#btnWarehouseExportPdf").on("click", function () {
    if (tabla) tabla.button(1).trigger();
  });

  $("#modalEditarAlmacen").on("shown.bs.modal", function () {
    setTimeout(function () {
      const input = document.getElementById("edit_nombre");
      if (input) { input.focus(); input.select(); }
    }, 100);
  });
}

function mostrarform(flag) {
  limpiar();
  if (flag) {
    $("#listadoregistros").hide();
    $("#formularioregistros").fadeIn(160);
    $("#btnagregar").hide();
    setTimeout(function () { $("#nombre").trigger("focus"); }, 100);
  } else {
    $("#formularioregistros").hide();
    $("#listadoregistros").fadeIn(140);
    $("#btnagregar").show();
  }
}

function cancelarform() { mostrarform(false); }

function limpiar() {
  if ($("#formulario")[0]) $("#formulario")[0].reset();
  $("#idalmacen").val("");
  $("#btnGuardar").prop("disabled", false).html('<i class="fas fa-save tw-text-xs"></i> Guardar almacén');
}

function listar() {
  if ($.fn.DataTable && $.fn.DataTable.isDataTable("#tbllistado")) {
    $("#tbllistado").DataTable().clear().destroy();
  }

  tabla = $("#tbllistado").DataTable({
    processing: true,
    serverSide: false,
    responsive: false,
    autoWidth: false,
    dom: "Brtip",
    buttons: [
      { extend: "excelHtml5", title: "Reporte de Almacenes", sheetName: "Almacenes", exportOptions: { columns: [1,2,3,4] } },
      { extend: "pdfHtml5", title: "Reporte de Almacenes", pageSize: "A4", orientation: "landscape", exportOptions: { columns: [1,2,3,4] } },
    ],
    ajax: {
      url: "Controllers/Almacen.php?op=listar",
      type: "GET",
      dataType: "json",
      dataSrc: function (json) {
        cargarEstadisticas();
        return json && json.aaData ? json.aaData : [];
      },
      error: function (xhr) { mostrarError("No se pudieron cargar los almacenes."); console.error(xhr.responseText); },
    },
    columnDefs: [
      { targets: [0], visible: false, searchable: false },
      { targets: [4,5], orderable: false },
      { targets: [4,5], className: "text-center" },
    ],
    pageLength: 8,
    lengthChange: false,
    searching: true,
    order: [[1, "asc"]],
    language: {
      processing: "Cargando...",
      zeroRecords: "No se encontraron almacenes",
      emptyTable: "Todavía no hay almacenes registrados",
      info: "Mostrando _START_ a _END_ de _TOTAL_ almacenes",
      infoEmpty: "Sin almacenes para mostrar",
      infoFiltered: "(filtrado de _MAX_)",
      paginate: { first:"Primero", last:"Último", next:"›", previous:"‹" },
    },
  });
}

function cargarEstadisticas() {
  $.ajax({
    url: "Controllers/Almacen.php?op=estadisticas",
    type: "GET",
    dataType: "json",
    cache: false,
    success: function (stats) {
      $("#warehouseStatTotal").text(Number(stats.total || 0));
      $("#warehouseStatActive").text(Number(stats.activos || 0));
      $("#warehouseStatInactive").text(Number(stats.inactivos || 0));
    },
    error: function () { $("#warehouseStatTotal, #warehouseStatActive, #warehouseStatInactive").text("—"); },
  });
}

function guardaryeditar(e) {
  e.preventDefault();
  const nombre = $.trim($("#nombre").val());
  if (!nombre) { $("#nombre").trigger("focus"); return; }

  const $btn = $("#btnGuardar");
  $btn.prop("disabled", true).html('<i class="fas fa-circle-notch fa-spin tw-text-xs"></i> Guardando...');

  $.ajax({
    url: "Controllers/Almacen.php?op=guardaryeditar",
    type: "POST",
    data: new FormData($("#formulario")[0]),
    contentType: false,
    processData: false,
    dataType: "json",
    success: function (resp) {
      if (!resp.ok) { mostrarError(resp.mensaje || "No se pudo guardar el almacén."); return; }
      mostrarToast(resp.mensaje);
      mostrarform(false);
      if (tabla) tabla.ajax.reload(function () { cargarEstadisticas(); }, false);
    },
    error: function (xhr) { mostrarErrorAjax(xhr, "No se pudo guardar el almacén."); },
    complete: function () { $btn.prop("disabled", false).html('<i class="fas fa-save tw-text-xs"></i> Guardar almacén'); },
  });
}

function editarAlmacen(idalmacen) {
  $.ajax({
    url: "Controllers/Almacen.php?op=mostrar",
    type: "POST",
    data: { idalmacen: idalmacen },
    dataType: "json",
    success: function (data) {
      if (!data || !data.idalmacen) { mostrarError("No se encontró el almacén."); return; }
      $("#edit_idalmacen").val(data.idalmacen);
      $("#edit_nombre").val(data.nombre || "");
      $("#edit_ubicacion").val(data.ubicacion || "");
      $("#edit_descripcion").val(data.descripcion || "");
      $("#modalEditarAlmacen").modal("show");
    },
    error: function (xhr) { mostrarErrorAjax(xhr, "No se pudo cargar el almacén."); },
  });
}

function mostrar(idalmacen) {
  editarAlmacen(idalmacen);
}

function guardarEdicionAlmacen(e) {
  e.preventDefault();
  const nombre = $.trim($("#edit_nombre").val());
  if (!nombre) { $("#edit_nombre").trigger("focus"); return; }

  const $btn = $("#btnGuardarEdicionAlmacen");
  $btn.prop("disabled", true).html('<i class="fas fa-circle-notch fa-spin tw-text-xs"></i> Guardando...');

  $.ajax({
    url: "Controllers/Almacen.php?op=guardaryeditar",
    type: "POST",
    data: {
      idalmacen: $("#edit_idalmacen").val(),
      nombre: nombre,
      ubicacion: $.trim($("#edit_ubicacion").val()),
      descripcion: $.trim($("#edit_descripcion").val()),
    },
    dataType: "json",
    success: function (resp) {
      if (!resp.ok) { mostrarError(resp.mensaje || "No se pudo actualizar el almacén."); return; }
      $("#modalEditarAlmacen").modal("hide");
      mostrarToast(resp.mensaje);
      if (tabla) tabla.ajax.reload(function () { cargarEstadisticas(); }, false);
    },
    error: function (xhr) { mostrarErrorAjax(xhr, "No se pudo actualizar el almacén."); },
    complete: function () { $btn.prop("disabled", false).html('<i class="fas fa-save tw-text-xs"></i> Guardar cambios'); },
  });
}

function desactivar(idalmacen) { cambiarEstado(idalmacen, "desactivar"); }
function activar(idalmacen) { cambiarEstado(idalmacen, "activar"); }

function cambiarEstado(idalmacen, accion) {
  const esDesactivar = accion === "desactivar";
  Swal.fire({
    title: esDesactivar ? "Desactivar almacén" : "Activar almacén",
    text: esDesactivar ? "El almacén dejará de estar disponible para nuevas operaciones." : "El almacén volverá a estar disponible.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: esDesactivar ? "#dc2626" : "#00a46a",
    cancelButtonColor: "#94a3b8",
    confirmButtonText: esDesactivar ? "Sí, desactivar" : "Sí, activar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
  }).then(function (resultado) {
    if (!resultado.isConfirmed) return;
    $.ajax({
      url: "Controllers/Almacen.php?op=" + accion,
      type: "POST",
      data: { idalmacen: idalmacen },
      dataType: "json",
      success: function (resp) {
        if (!resp.ok) { mostrarError(resp.mensaje || "No se pudo cambiar el estado."); return; }
        mostrarToast(resp.mensaje);
        if (tabla) tabla.ajax.reload(function () { cargarEstadisticas(); }, false);
      },
      error: function (xhr) { mostrarErrorAjax(xhr, "No se pudo cambiar el estado del almacén."); },
    });
  });
}

function mostrarToast(mensaje) {
  Swal.fire({ toast:true, position:"top-end", icon:"success", title:mensaje, showConfirmButton:false, timer:2200, timerProgressBar:true });
}
function mostrarError(mensaje) { Swal.fire("Error", mensaje, "error"); }
function mostrarErrorAjax(xhr, fallback) {
  let mensaje = fallback;
  if (xhr && xhr.responseJSON && xhr.responseJSON.mensaje) mensaje = xhr.responseJSON.mensaje;
  else if (xhr && xhr.responseText) {
    try { const r = JSON.parse(xhr.responseText); if (r.mensaje) mensaje = r.mensaje; } catch (e) {}
  }
  Swal.fire(xhr && (xhr.status === 409 || xhr.status === 422) ? "Atención" : "Error", mensaje, xhr && (xhr.status === 409 || xhr.status === 422) ? "warning" : "error");
}

$(document).ready(init);
