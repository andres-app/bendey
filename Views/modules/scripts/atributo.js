let tabla;
let valoresCache = [];

function init() {
  mostrarform(false);
  listar();
  cargarEstadisticas();
  registrarEventos();
}

function registrarEventos() {
  $("#formulario").on("submit", guardaryeditar);
  $("#formEditarAtributo").on("submit", guardarEdicionAtributo);
  $("#formValor").on("submit", guardarEditarValor);

  $("#attributeSearch").on("input", function () {
    if (tabla) tabla.search(this.value).draw();
  });

  $("#btnAttributeExportExcel").on("click", function () {
    if (tabla) tabla.button(0).trigger();
  });

  $("#btnAttributeExportPdf").on("click", function () {
    if (tabla) tabla.button(1).trigger();
  });

  $("#attributeValueSearch").on("input", filtrarValores);

  $("#btnCancelarValor").on("click", function () {
    limpiarFormularioValor(true);
    $("#valor").trigger("focus");
  });

  $("#modalValores").on("shown.bs.modal", function () {
    setTimeout(function () { $("#valor").trigger("focus"); }, 100);
  });

  $("#modalValores").on("hidden.bs.modal", function () {
    limpiarFormularioValor(false);
    valoresCache = [];
    $("#titulo-atributo").text("");
    $("#attributeValueSearch").val("");
    actualizarResumenValores([]);
    renderizarValores([]);
  });

  $("#modalEditarAtributo").on("shown.bs.modal", function () {
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

function limpiar() {
  if ($("#formulario")[0]) $("#formulario")[0].reset();
  $("#idatributo").val("");
  $("#btnGuardar").prop("disabled", false).html('<i class="fas fa-save tw-text-xs"></i> Guardar atributo');
}

function cancelarform() {
  mostrarform(false);
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
      {
        extend: "excelHtml5",
        text: "Excel",
        title: "Reporte de Atributos",
        sheetName: "Atributos",
        exportOptions: { columns: [1, 2, 4] },
      },
      {
        extend: "pdfHtml5",
        text: "PDF",
        title: "Reporte de Atributos",
        pageSize: "A4",
        orientation: "landscape",
        exportOptions: { columns: [1, 2, 4] },
      },
    ],
    ajax: {
      url: "Controllers/Atributo.php?op=listar",
      type: "GET",
      dataType: "json",
      dataSrc: function (json) {
        cargarEstadisticas();
        if (!json || json.ok === false) return [];
        return json.aaData || [];
      },
      error: function (xhr) {
        mostrarErrorAjax(xhr, "No se pudieron listar los atributos.");
      },
    },
    columnDefs: [
      { targets: [0], visible: false, searchable: false },
      { targets: [3, 4, 5], orderable: false },
      { targets: [3, 4, 5], className: "text-center" },
    ],
    pageLength: 8,
    lengthChange: false,
    searching: true,
    order: [[1, "asc"]],
    language: {
      processing: "Cargando...",
      emptyTable: "Todavía no hay atributos registrados",
      zeroRecords: "No se encontraron atributos",
      info: "Mostrando _START_ a _END_ de _TOTAL_ atributos",
      infoEmpty: "Sin atributos para mostrar",
      infoFiltered: "(filtrado de _MAX_)",
      paginate: { first: "Primero", last: "Último", next: "›", previous: "‹" },
    },
  });
}

function cargarEstadisticas() {
  $.ajax({
    url: "Controllers/Atributo.php?op=estadisticas",
    type: "GET",
    dataType: "json",
    cache: false,
    success: function (respuesta) {
      const stats = respuesta && respuesta.data ? respuesta.data : respuesta;
      $("#attributeStatTotal").text(Number(stats.total || 0));
      $("#attributeStatActive").text(Number(stats.activos || 0));
      $("#attributeStatInactive").text(Number(stats.inactivos || 0));
    },
    error: function () {
      $("#attributeStatTotal, #attributeStatActive, #attributeStatInactive").text("—");
    },
  });
}

function guardaryeditar(e) {
  e.preventDefault();
  const nombre = $.trim($("#nombre").val());
  if (!nombre) { $("#nombre").trigger("focus"); return; }

  $("#nombre").val(nombre);
  $("#descripcion").val($.trim($("#descripcion").val()));
  const $btn = $("#btnGuardar");
  $btn.prop("disabled", true).html('<i class="fas fa-circle-notch fa-spin tw-text-xs"></i> Guardando...');

  $.ajax({
    url: "Controllers/Atributo.php?op=guardaryeditar",
    type: "POST",
    data: new FormData($("#formulario")[0]),
    contentType: false,
    processData: false,
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok) { mostrarAlerta("Atención", respuesta.mensaje, "warning"); return; }
      mostrarToast(respuesta.mensaje);
      mostrarform(false);
      if (tabla) tabla.ajax.reload(function () { cargarEstadisticas(); }, false);
    },
    error: function (xhr) { mostrarErrorAjax(xhr, "No se pudo guardar el atributo."); },
    complete: function () {
      $btn.prop("disabled", false).html('<i class="fas fa-save tw-text-xs"></i> Guardar atributo');
    },
  });
}

function editarAtributo(idatributo) {
  $.ajax({
    url: "Controllers/Atributo.php?op=mostrar",
    type: "POST",
    data: { idatributo: idatributo },
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok || !respuesta.data) {
        mostrarAlerta("Error", respuesta.mensaje || "No se encontró el atributo.", "error");
        return;
      }
      $("#edit_idatributo").val(respuesta.data.idatributo);
      $("#edit_nombre").val(respuesta.data.nombre || "");
      $("#edit_descripcion").val(respuesta.data.descripcion || "");
      $("#modalEditarAtributo").modal("show");
    },
    error: function (xhr) { mostrarErrorAjax(xhr, "No se pudo cargar el atributo."); },
  });
}

function mostrar(idatributo) {
  editarAtributo(idatributo);
}

function guardarEdicionAtributo(e) {
  e.preventDefault();
  const nombre = $.trim($("#edit_nombre").val());
  if (!nombre) { $("#edit_nombre").trigger("focus"); return; }

  const $btn = $("#btnGuardarEdicionAtributo");
  $btn.prop("disabled", true).html('<i class="fas fa-circle-notch fa-spin tw-text-xs"></i> Guardando...');

  $.ajax({
    url: "Controllers/Atributo.php?op=guardaryeditar",
    type: "POST",
    data: {
      idatributo: $("#edit_idatributo").val(),
      nombre: nombre,
      descripcion: $.trim($("#edit_descripcion").val()),
    },
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok) { mostrarAlerta("Atención", respuesta.mensaje, "warning"); return; }
      $("#modalEditarAtributo").modal("hide");
      mostrarToast(respuesta.mensaje);
      if (tabla) tabla.ajax.reload(function () { cargarEstadisticas(); }, false);
    },
    error: function (xhr) { mostrarErrorAjax(xhr, "No se pudo actualizar el atributo."); },
    complete: function () {
      $btn.prop("disabled", false).html('<i class="fas fa-save tw-text-xs"></i> Guardar cambios');
    },
  });
}

function desactivar(idatributo) { cambiarEstadoAtributo(idatributo, "desactivar"); }
function activar(idatributo) { cambiarEstadoAtributo(idatributo, "activar"); }

function cambiarEstadoAtributo(idatributo, accion) {
  const esDesactivar = accion === "desactivar";
  Swal.fire({
    title: esDesactivar ? "Desactivar atributo" : "Activar atributo",
    text: esDesactivar ? "El atributo dejará de estar disponible para nuevas selecciones." : "El atributo volverá a estar disponible.",
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
      url: "Controllers/Atributo.php?op=" + accion,
      type: "POST",
      data: { idatributo: idatributo },
      dataType: "json",
      success: function (respuesta) {
        if (!respuesta.ok) { mostrarAlerta("Error", respuesta.mensaje, "error"); return; }
        mostrarToast(respuesta.mensaje);
        if (tabla) tabla.ajax.reload(function () { cargarEstadisticas(); }, false);
      },
      error: function (xhr) { mostrarErrorAjax(xhr, "No se pudo cambiar el estado del atributo."); },
    });
  });
}

function gestionarValores(idatributo, nombre) {
  limpiarFormularioValor(false);
  valoresCache = [];
  $("#idatributo_valor").val(idatributo);
  $("#titulo-atributo").text(nombre);
  $("#attributeValueSearch").val("");
  $("#modalValores").modal("show");
  listarValores(idatributo);
}

function guardarEditarValor(e) {
  e.preventDefault();
  const valor = $.trim($("#valor").val());
  if (!valor) { $("#valor").trigger("focus"); return; }
  $("#valor").val(valor);

  const idatributo = $("#idatributo_valor").val();
  const esEdicion = Number($("#idvalor").val()) > 0;
  const $btn = $("#btnGuardarValor");
  $btn.prop("disabled", true).html('<i class="fas fa-circle-notch fa-spin tw-text-xs"></i><span>' + (esEdicion ? 'Actualizando...' : 'Agregando...') + '</span>');

  $.ajax({
    url: "Controllers/AtributoValor.php?op=guardaryeditar",
    type: "POST",
    data: new FormData($("#formValor")[0]),
    contentType: false,
    processData: false,
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok) { mostrarAlerta("Atención", respuesta.mensaje, "warning"); return; }
      mostrarToast(respuesta.mensaje);
      limpiarFormularioValor(true);
      $("#idatributo_valor").val(idatributo);
      listarValores(idatributo);
    },
    error: function (xhr) { mostrarErrorAjax(xhr, "No se pudo guardar el valor."); },
    complete: function () { configurarBotonValor(Number($("#idvalor").val()) > 0); },
  });
}

function listarValores(idatributo) {
  if (!idatributo) { valoresCache = []; actualizarResumenValores([]); renderizarValores([]); return; }

  $("#tblvalores tbody").html('<tr><td colspan="3" class="attribute-loading-cell"><i class="fas fa-circle-notch fa-spin tw-mr-2"></i>Cargando valores...</td></tr>');

  $.ajax({
    url: "Controllers/AtributoValor.php?op=listar",
    type: "GET",
    data: { idatributo: idatributo },
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok) {
        valoresCache = [];
        actualizarResumenValores([]);
        $("#tblvalores tbody").html('<tr><td colspan="3" class="attribute-empty-cell tw-text-rose-500">' + escaparHtml(respuesta.mensaje || "No se pudieron listar los valores.") + '</td></tr>');
        return;
      }

      valoresCache = (respuesta.aaData || []).map(function (item) {
        return {
          valorHtml: item[0],
          valorPlano: $("<div>").html(item[0]).text(),
          estadoHtml: item[1],
          accionesHtml: item[2],
          estado: $("<div>").html(item[1]).text().trim().toLowerCase().indexOf("inactivo") === -1 ? "activo" : "inactivo",
        };
      });
      actualizarResumenValores(valoresCache);
      filtrarValores();
    },
    error: function (xhr) {
      valoresCache = [];
      actualizarResumenValores([]);
      $("#tblvalores tbody").html('<tr><td colspan="3" class="attribute-empty-cell tw-text-rose-500">No se pudieron cargar los valores.</td></tr>');
      mostrarErrorAjax(xhr, "No se pudieron listar los valores.");
    },
  });
}

function filtrarValores() {
  const termino = normalizarTexto($("#attributeValueSearch").val());
  const filtrados = valoresCache.filter(function (item) { return normalizarTexto(item.valorPlano).indexOf(termino) !== -1; });
  renderizarValores(filtrados, termino !== "");
}

function renderizarValores(filas, esBusqueda = false) {
  if (!filas.length) {
    const contenido = esBusqueda
      ? '<div class="attribute-empty-state"><i class="fas fa-search"></i><strong>Sin coincidencias</strong><span>Prueba con otra palabra.</span></div>'
      : '<div class="attribute-empty-state"><i class="fas fa-layer-group"></i><strong>Aún no hay valores</strong><span>Agrega el primero usando el campo superior.</span></div>';
    $("#tblvalores tbody").html('<tr><td colspan="3">' + contenido + '</td></tr>');
    return;
  }

  let html = "";
  filas.forEach(function (item) {
    html += '<tr><td>' + item.valorHtml + '</td><td>' + item.estadoHtml + '</td><td class="tw-text-right">' + item.accionesHtml + '</td></tr>';
  });
  $("#tblvalores tbody").html(html);
}

function actualizarResumenValores(filas) {
  const activos = filas.filter(function (item) { return item.estado === "activo"; }).length;
  $("#totalValores").text(filas.length);
  $("#valoresActivos").text(activos);
  $("#valoresInactivos").text(filas.length - activos);
}

function editarValor(idvalor) {
  $.ajax({
    url: "Controllers/AtributoValor.php?op=mostrar",
    type: "POST",
    data: { idvalor: idvalor },
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok || !respuesta.data) { mostrarAlerta("Error", respuesta.mensaje || "No se encontró el valor.", "error"); return; }
      $("#idvalor").val(respuesta.data.idvalor);
      $("#idatributo_valor").val(respuesta.data.idatributo);
      $("#valor").val(respuesta.data.valor).trigger("focus").select();
      $("#btnCancelarValor").show();
      configurarBotonValor(true);
    },
    error: function (xhr) { mostrarErrorAjax(xhr, "No se pudo cargar el valor."); },
  });
}

function desactivarValor(idvalor) { cambiarEstadoValor(idvalor, "desactivar"); }
function activarValor(idvalor) { cambiarEstadoValor(idvalor, "activar"); }

function cambiarEstadoValor(idvalor, accion) {
  const esDesactivar = accion === "desactivar";
  Swal.fire({
    title: esDesactivar ? "Desactivar valor" : "Activar valor",
    text: esDesactivar ? "Este valor dejará de estar disponible para nuevas selecciones." : "Este valor volverá a estar disponible.",
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
      url: "Controllers/AtributoValor.php?op=" + accion,
      type: "POST",
      data: { idvalor: idvalor },
      dataType: "json",
      success: function (respuesta) {
        if (!respuesta.ok) { mostrarAlerta("Error", respuesta.mensaje, "error"); return; }
        mostrarToast(respuesta.mensaje);
        limpiarFormularioValor(true);
        listarValores($("#idatributo_valor").val());
      },
      error: function (xhr) { mostrarErrorAjax(xhr, "No se pudo cambiar el estado del valor."); },
    });
  });
}

function limpiarFormularioValor(mantenerAtributo) {
  const idatributo = mantenerAtributo ? $("#idatributo_valor").val() : "";
  if ($("#formValor")[0]) $("#formValor")[0].reset();
  $("#idvalor").val("");
  $("#idatributo_valor").val(idatributo);
  $("#btnCancelarValor").hide();
  configurarBotonValor(false);
}

function configurarBotonValor(esEdicion) {
  const $btn = $("#btnGuardarValor");
  $btn.prop("disabled", false).html(esEdicion
    ? '<i class="fas fa-save tw-text-xs"></i><span>Actualizar valor</span>'
    : '<i class="fas fa-plus tw-text-xs"></i><span>Agregar valor</span>');
}

function normalizarTexto(texto) {
  return String(texto || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
}

function mostrarToast(mensaje) {
  Swal.fire({ toast: true, position: "top-end", icon: "success", title: mensaje, showConfirmButton: false, timer: 2200, timerProgressBar: true });
}

function mostrarAlerta(titulo, mensaje, icono) { Swal.fire(titulo, mensaje, icono); }

function mostrarErrorAjax(xhr, mensajePorDefecto) {
  let mensaje = mensajePorDefecto;
  if (xhr && xhr.responseJSON && xhr.responseJSON.mensaje) mensaje = xhr.responseJSON.mensaje;
  else if (xhr && xhr.responseText) {
    try { const r = JSON.parse(xhr.responseText); if (r.mensaje) mensaje = r.mensaje; } catch (e) {}
  }
  mostrarAlerta(xhr && (xhr.status === 409 || xhr.status === 422) ? "Atención" : "Error", mensaje, xhr && (xhr.status === 409 || xhr.status === 422) ? "warning" : "error");
}

function escaparHtml(texto) { return $("<div>").text(texto == null ? "" : String(texto)).html(); }

$(document).ready(init);
