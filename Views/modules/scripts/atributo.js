let tabla;
let valoresCache = [];
let estadoFiltroAtributo = "todos";
let temporizadorBusquedaAtributo;
let temporizadorBusquedaValor;

function init() {
  mostrarform(false);
  listar();
  registrarEventos();
}

function registrarEventos() {
  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });

  $("#formValor").on("submit", function (e) {
    guardarEditarValor(e);
  });

  $("#btnCancelarValor").on("click", function () {
    limpiarFormularioValor(true);
    $("#valor").trigger("focus");
  });

  $("#modalValores").on("shown.bs.modal", function () {
    $("#valor").trigger("focus");
  });

  $("#modalValores").on("hidden.bs.modal", function () {
    limpiarFormularioValor(false);
    valoresCache = [];
    $("#titulo-atributo").text("");
    $("#buscarValor").val("");
    alternarBotonLimpiar("#buscarValor", "#limpiarBusquedaValor");
    actualizarResumenValores([]);
    renderizarValores([]);
  });

  $("#buscarAtributo").on("input", function () {
    const termino = this.value;
    alternarBotonLimpiar("#buscarAtributo", "#limpiarBusquedaAtributo");

    clearTimeout(temporizadorBusquedaAtributo);
    temporizadorBusquedaAtributo = setTimeout(function () {
      if (tabla) {
        tabla.search(termino).draw();
      }
    }, 180);
  });

  $("#limpiarBusquedaAtributo").on("click", function () {
    $("#buscarAtributo").val("").trigger("input").trigger("focus");
  });

  $(".atributo-segmented [data-estado]").on("click", function () {
    estadoFiltroAtributo = $(this).data("estado");
    $(".atributo-segmented [data-estado]").removeClass("is-active");
    $(this).addClass("is-active");
    aplicarFiltroEstadoAtributo();
  });

  $("#exportarExcel").on("click", function () {
    if (tabla) {
      tabla.button(".buttons-excel").trigger();
    }
  });

  $("#exportarPdf").on("click", function () {
    if (tabla) {
      tabla.button(".buttons-pdf").trigger();
    }
  });

  $("#descripcion").on("input", function () {
    $("#contadorDescripcion").text(this.value.length);
  });

  $("#nombre, #descripcion").on("input", function () {
    $(this).removeClass("is-invalid");
  });

  $("#valor").on("input", function () {
    $(this).removeClass("is-invalid");
  });

  $("#buscarValor").on("input", function () {
    alternarBotonLimpiar("#buscarValor", "#limpiarBusquedaValor");

    clearTimeout(temporizadorBusquedaValor);
    temporizadorBusquedaValor = setTimeout(function () {
      filtrarValores();
    }, 150);
  });

  $("#limpiarBusquedaValor").on("click", function () {
    $("#buscarValor").val("").trigger("input").trigger("focus");
  });
}

function mostrarform(flag, esEdicion = false) {
  limpiar();

  if (flag) {
    $("#listadoregistros").hide();
    $("#formularioregistros").fadeIn(160);
    $("#btnagregar").hide();
    actualizarModoFormulario(esEdicion);

    window.setTimeout(function () {
      $("#nombre").trigger("focus");
    }, 170);
  } else {
    $("#formularioregistros").hide();
    $("#listadoregistros").fadeIn(160);
    $("#btnagregar").show();
    actualizarModoFormulario(false);
  }
}

function actualizarModoFormulario(esEdicion) {
  if (esEdicion) {
    $("#formEyebrow").text("Edición de registro");
    $("#tituloFormulario").text("Editar atributo");
    $("#descripcionFormulario").text("Actualiza el nombre o la descripción sin afectar sus valores asociados.");
    $("#textoBtnGuardar").text("Guardar cambios");
  } else {
    $("#formEyebrow").text("Nuevo registro");
    $("#tituloFormulario").text("Crear atributo");
    $("#descripcionFormulario").text("Define una característica que podrá utilizarse en las variaciones de tus productos.");
    $("#textoBtnGuardar").text("Guardar atributo");
  }
}

function limpiar() {
  $("#formulario")[0]?.reset();
  $("#idatributo").val("");
  $("#nombre, #descripcion").removeClass("is-invalid");
  $("#contadorDescripcion").text("0");
  restaurarBoton("#btnGuardar");
}

function cancelarform() {
  mostrarform(false);
}

function listar() {
  tabla = $("#tbllistado").DataTable({
    processing: true,
    serverSide: false,
    responsive: false,
    autoWidth: false,
    dom: "Brt<'row align-items-center mt-3'<'col-md-5'i><'col-md-7'p>>",
    buttons: [
      {
        extend: "excelHtml5",
        title: "Atributos",
        exportOptions: { columns: [1, 2, 3] },
      },
      {
        extend: "pdfHtml5",
        title: "Atributos",
        orientation: "landscape",
        pageSize: "A4",
        exportOptions: { columns: [1, 2, 3] },
      },
    ],
    ajax: {
      url: "Controllers/Atributo.php?op=listar",
      type: "GET",
      dataType: "json",
      dataSrc: function (json) {
        if (!json.ok) {
          mostrarAlerta("Error", json.mensaje || "No se pudieron listar los atributos.", "error");
          actualizarResumenAtributos([]);
          return [];
        }

        const registros = json.aaData || [];
        actualizarResumenAtributos(registros);
        return registros;
      },
      error: function (xhr) {
        actualizarResumenAtributos([]);
        mostrarErrorAjax(xhr, "No se pudieron listar los atributos.");
      },
    },
    columnDefs: [
      {
        targets: [0],
        visible: false,
        searchable: false,
      },
      {
        targets: [4, 5],
        orderable: false,
        searchable: false,
      },
      {
        targets: [3, 4, 5],
        className: "text-center",
      },
    ],
    pageLength: 10,
    lengthChange: false,
    order: [[0, "desc"]],
    language: {
      processing: '<i class="fas fa-circle-notch fa-spin mr-2"></i>Cargando atributos...',
      emptyTable: "Todavía no hay atributos registrados.",
      zeroRecords: "No se encontraron atributos con esos filtros.",
      info: "Mostrando _START_ a _END_ de _TOTAL_ atributos",
      infoEmpty: "Mostrando 0 atributos",
      infoFiltered: "(filtrados de _MAX_)",
      paginate: {
        first: "Primero",
        last: "Último",
        next: '<i class="fas fa-chevron-right"></i>',
        previous: '<i class="fas fa-chevron-left"></i>',
      },
    },
    drawCallback: function () {
      actualizarCantidadResultados();
      activarTooltips();
    },
    initComplete: function () {
      aplicarFiltroEstadoAtributo();
      actualizarCantidadResultados();
    },
  });
}

function actualizarResumenAtributos(registros) {
  let activos = 0;
  let inactivos = 0;

  registros.forEach(function (registro) {
    if (String(registro[3]).indexOf("badge-success") !== -1) {
      activos += 1;
    } else {
      inactivos += 1;
    }
  });

  animarNumero("#kpiTotal", registros.length);
  animarNumero("#kpiActivos", activos);
  animarNumero("#kpiInactivos", inactivos);
}

function animarNumero(selector, destino) {
  const $elemento = $(selector);
  const inicio = Number($elemento.text()) || 0;

  $({ valor: inicio }).stop(true).animate(
    { valor: destino },
    {
      duration: 280,
      step: function () {
        $elemento.text(Math.floor(this.valor));
      },
      complete: function () {
        $elemento.text(destino);
      },
    }
  );
}

function aplicarFiltroEstadoAtributo() {
  if (!tabla) {
    return;
  }

  if (estadoFiltroAtributo === "activo") {
    tabla.column(3).search("^Activo$", true, false).draw();
  } else if (estadoFiltroAtributo === "inactivo") {
    tabla.column(3).search("^Inactivo$", true, false).draw();
  } else {
    tabla.column(3).search("").draw();
  }
}

function actualizarCantidadResultados() {
  if (!tabla) {
    return;
  }

  const cantidad = tabla.rows({ search: "applied" }).count();
  $("#resultadoAtributos").text(cantidad + (cantidad === 1 ? " resultado" : " resultados"));
}

function guardaryeditar(e) {
  e.preventDefault();

  const nombre = $.trim($("#nombre").val());
  if (!nombre) {
    $("#nombre").addClass("is-invalid").trigger("focus");
    return;
  }

  $("#nombre").val(nombre);
  $("#descripcion").val($.trim($("#descripcion").val()));

  const esEdicion = Number($("#idatributo").val()) > 0;
  ponerBotonCargando("#btnGuardar", esEdicion ? "Guardando cambios..." : "Guardando atributo...");

  $.ajax({
    url: "Controllers/Atributo.php?op=guardaryeditar",
    type: "POST",
    data: new FormData($("#formulario")[0]),
    contentType: false,
    processData: false,
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok) {
        mostrarAlerta("Atención", respuesta.mensaje, "warning");
        return;
      }

      mostrarToast(respuesta.mensaje);
      mostrarform(false);
      tabla.ajax.reload(null, false);
    },
    error: function (xhr) {
      mostrarErrorAjax(xhr, "No se pudo guardar el atributo.");
    },
    complete: function () {
      restaurarBoton("#btnGuardar");
      $("#textoBtnGuardar").text(esEdicion ? "Guardar cambios" : "Guardar atributo");
    },
  });
}

function mostrar(idatributo) {
  mostrarCargandoGlobal("Cargando atributo...");

  $.ajax({
    url: "Controllers/Atributo.php?op=mostrar",
    type: "POST",
    data: { idatributo: idatributo },
    dataType: "json",
    success: function (respuesta) {
      Swal.close();

      if (!respuesta.ok || !respuesta.data) {
        mostrarAlerta("Error", respuesta.mensaje || "No se encontró el atributo.", "error");
        return;
      }

      mostrarform(true, true);
      $("#idatributo").val(respuesta.data.idatributo);
      $("#nombre").val(respuesta.data.nombre);
      $("#descripcion").val(respuesta.data.descripcion || "");
      $("#contadorDescripcion").text(String(respuesta.data.descripcion || "").length);
    },
    error: function (xhr) {
      Swal.close();
      mostrarErrorAjax(xhr, "No se pudo cargar el atributo.");
    },
  });
}

function desactivar(idatributo) {
  cambiarEstadoAtributo(idatributo, "desactivar");
}

function activar(idatributo) {
  cambiarEstadoAtributo(idatributo, "activar");
}

function cambiarEstadoAtributo(idatributo, accion) {
  const esDesactivar = accion === "desactivar";
  const nombre = obtenerNombreAtributo(idatributo);

  Swal.fire({
    title: esDesactivar ? "Desactivar atributo" : "Activar atributo",
    html: esDesactivar
      ? 'El atributo <b>“' + escaparHtml(nombre) + '”</b> dejará de aparecer en nuevas selecciones.'
      : 'El atributo <b>“' + escaparHtml(nombre) + '”</b> volverá a estar disponible.',
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: esDesactivar ? "#e05260" : "#36b37e",
    cancelButtonColor: "#8b94a5",
    confirmButtonText: esDesactivar ? "Sí, desactivar" : "Sí, activar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    focusCancel: true,
    showLoaderOnConfirm: true,
    preConfirm: function () {
      return $.ajax({
        url: "Controllers/Atributo.php?op=" + accion,
        type: "POST",
        data: { idatributo: idatributo },
        dataType: "json",
      }).catch(function (xhr) {
        Swal.showValidationMessage(obtenerMensajeAjax(xhr, "No se pudo cambiar el estado del atributo."));
      });
    },
    allowOutsideClick: function () {
      return !Swal.isLoading();
    },
  }).then(function (resultado) {
    if (!resultado.isConfirmed || !resultado.value) {
      return;
    }

    if (!resultado.value.ok) {
      mostrarAlerta("Error", resultado.value.mensaje, "error");
      return;
    }

    mostrarToast(resultado.value.mensaje);
    tabla.ajax.reload(null, false);
  });
}

function obtenerNombreAtributo(idatributo) {
  if (!tabla) {
    return "este atributo";
  }

  let nombre = "este atributo";
  tabla.rows().every(function () {
    const fila = this.data();
    if (Number(fila[0]) === Number(idatributo)) {
      nombre = $("<div>").html(fila[1]).text();
    }
  });

  return nombre;
}

function gestionarValores(idatributo, nombre) {
  limpiarFormularioValor(false);
  valoresCache = [];
  $("#idatributo_valor").val(idatributo);
  $("#titulo-atributo").text(nombre);
  $("#buscarValor").val("");
  alternarBotonLimpiar("#buscarValor", "#limpiarBusquedaValor");
  $("#modalValores").modal("show");
  listarValores(idatributo);
}

function guardarEditarValor(e) {
  e.preventDefault();

  const valor = $.trim($("#valor").val());
  if (!valor) {
    $("#valor").addClass("is-invalid").trigger("focus");
    return;
  }

  $("#valor").val(valor);

  const idatributo = $("#idatributo_valor").val();
  const esEdicion = Number($("#idvalor").val()) > 0;
  ponerBotonCargando("#btnGuardarValor", esEdicion ? "Actualizando..." : "Agregando...");

  $.ajax({
    url: "Controllers/AtributoValor.php?op=guardaryeditar",
    type: "POST",
    data: new FormData($("#formValor")[0]),
    contentType: false,
    processData: false,
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok) {
        mostrarAlerta("Atención", respuesta.mensaje, "warning");
        return;
      }

      mostrarToast(respuesta.mensaje);
      limpiarFormularioValor(true);
      $("#idatributo_valor").val(idatributo);
      listarValores(idatributo);
    },
    error: function (xhr) {
      mostrarErrorAjax(xhr, "No se pudo guardar el valor.");
    },
    complete: function () {
      restaurarBoton("#btnGuardarValor");
      configurarBotonValor(esEdicion && Number($("#idvalor").val()) > 0);
    },
  });
}

function listarValores(idatributo) {
  if (!idatributo) {
    valoresCache = [];
    actualizarResumenValores([]);
    renderizarValores([]);
    return;
  }

  $("#tblvalores tbody").html(
    '<tr><td colspan="3" class="atributo-loading-cell"><i class="fas fa-circle-notch fa-spin"></i>Cargando valores...</td></tr>'
  );

  $.ajax({
    url: "Controllers/AtributoValor.php?op=listar",
    type: "GET",
    data: { idatributo: idatributo },
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok) {
        valoresCache = [];
        actualizarResumenValores([]);
        $("#tblvalores tbody").html(
          '<tr><td colspan="3" class="atributo-empty-cell text-danger">' +
            escaparHtml(respuesta.mensaje || "No se pudieron listar los valores.") +
            "</td></tr>"
        );
        return;
      }

      valoresCache = (respuesta.aaData || []).map(function (item) {
        const valorPlano = $("<div>").html(item[0]).text();
        const activo = String(item[1]).indexOf("badge-success") !== -1;

        return {
          valorHtml: item[0],
          valorPlano: valorPlano,
          estadoHtml: item[1],
          accionesHtml: item[2],
          estado: activo ? "activo" : "inactivo",
        };
      });

      actualizarResumenValores(valoresCache);
      filtrarValores();
    },
    error: function (xhr) {
      valoresCache = [];
      actualizarResumenValores([]);
      $("#tblvalores tbody").html(
        '<tr><td colspan="3" class="atributo-empty-cell text-danger">No se pudieron cargar los valores.</td></tr>'
      );
      mostrarErrorAjax(xhr, "No se pudieron listar los valores.");
    },
  });
}

function filtrarValores() {
  const termino = normalizarTexto($("#buscarValor").val());
  const filtrados = valoresCache.filter(function (item) {
    return normalizarTexto(item.valorPlano).indexOf(termino) !== -1;
  });

  renderizarValores(filtrados, termino !== "");
}

function renderizarValores(filas, esBusqueda = false) {
  if (!filas.length) {
    const contenido = esBusqueda
      ? '<div class="atributo-empty-state"><i class="fas fa-search"></i><strong>Sin coincidencias</strong><span>Prueba con otra palabra.</span></div>'
      : '<div class="atributo-empty-state"><i class="fas fa-layer-group"></i><strong>Aún no hay valores</strong><span>Agrega el primero usando el formulario superior.</span></div>';

    $("#tblvalores tbody").html('<tr><td colspan="3">' + contenido + "</td></tr>");
    return;
  }

  let html = "";
  filas.forEach(function (item) {
    html +=
      '<tr data-estado="' + item.estado + '">' +
      "<td>" + item.valorHtml + "</td>" +
      "<td>" + item.estadoHtml + "</td>" +
      '<td class="text-right">' + item.accionesHtml + "</td>" +
      "</tr>";
  });

  $("#tblvalores tbody").html(html);
  activarTooltips();
}

function actualizarResumenValores(filas) {
  const activos = filas.filter(function (item) {
    return item.estado === "activo";
  }).length;
  const inactivos = filas.length - activos;

  $("#totalValores").text(filas.length);
  $("#valoresActivos").text(activos);
  $("#valoresInactivos").text(inactivos);
}

function editarValor(idvalor) {
  mostrarCargandoGlobal("Cargando valor...");

  $.ajax({
    url: "Controllers/AtributoValor.php?op=mostrar",
    type: "POST",
    data: { idvalor: idvalor },
    dataType: "json",
    success: function (respuesta) {
      Swal.close();

      if (!respuesta.ok || !respuesta.data) {
        mostrarAlerta("Error", respuesta.mensaje || "No se encontró el valor.", "error");
        return;
      }

      $("#idvalor").val(respuesta.data.idvalor);
      $("#idatributo_valor").val(respuesta.data.idatributo);
      $("#valor").val(respuesta.data.valor).removeClass("is-invalid").trigger("focus");
      $("#modoValor").text("Editando valor");
      $("#labelValor").text("Modifica el valor seleccionado");
      $("#editorValor").addClass("is-editing");
      $("#btnCancelarValor").show();
      configurarBotonValor(true);
    },
    error: function (xhr) {
      Swal.close();
      mostrarErrorAjax(xhr, "No se pudo cargar el valor.");
    },
  });
}

function desactivarValor(idvalor) {
  cambiarEstadoValor(idvalor, "desactivar");
}

function activarValor(idvalor) {
  cambiarEstadoValor(idvalor, "activar");
}

function cambiarEstadoValor(idvalor, accion) {
  const esDesactivar = accion === "desactivar";

  Swal.fire({
    title: esDesactivar ? "Desactivar valor" : "Activar valor",
    text: esDesactivar
      ? "Este valor dejará de estar disponible para nuevas selecciones."
      : "Este valor volverá a estar disponible.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: esDesactivar ? "#e05260" : "#36b37e",
    cancelButtonColor: "#8b94a5",
    confirmButtonText: esDesactivar ? "Sí, desactivar" : "Sí, activar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    focusCancel: true,
    showLoaderOnConfirm: true,
    preConfirm: function () {
      return $.ajax({
        url: "Controllers/AtributoValor.php?op=" + accion,
        type: "POST",
        data: { idvalor: idvalor },
        dataType: "json",
      }).catch(function (xhr) {
        Swal.showValidationMessage(obtenerMensajeAjax(xhr, "No se pudo cambiar el estado del valor."));
      });
    },
    allowOutsideClick: function () {
      return !Swal.isLoading();
    },
  }).then(function (resultado) {
    if (!resultado.isConfirmed || !resultado.value) {
      return;
    }

    if (!resultado.value.ok) {
      mostrarAlerta("Error", resultado.value.mensaje, "error");
      return;
    }

    mostrarToast(resultado.value.mensaje);
    limpiarFormularioValor(true);
    listarValores($("#idatributo_valor").val());
  });
}

function limpiarFormularioValor(mantenerAtributo) {
  const idatributo = mantenerAtributo ? $("#idatributo_valor").val() : "";

  $("#formValor")[0]?.reset();
  $("#idvalor").val("");
  $("#idatributo_valor").val(idatributo);
  $("#valor").removeClass("is-invalid");
  $("#modoValor").text("Nuevo valor");
  $("#labelValor").text("Escribe el valor que deseas agregar");
  $("#editorValor").removeClass("is-editing");
  $("#btnCancelarValor").hide();
  configurarBotonValor(false);
}

function configurarBotonValor(esEdicion) {
  const $boton = $("#btnGuardarValor");

  if ($boton.prop("disabled")) {
    return;
  }

  if (esEdicion) {
    $boton.html('<i class="fas fa-save"></i><span>Actualizar valor</span>');
  } else {
    $boton.html('<i class="fas fa-plus"></i><span>Agregar valor</span>');
  }
}

function ponerBotonCargando(selector, texto) {
  const $boton = $(selector);
  if (!$boton.data("html-original")) {
    $boton.data("html-original", $boton.html());
  }

  $boton
    .prop("disabled", true)
    .html('<i class="fas fa-circle-notch fa-spin"></i><span>' + escaparHtml(texto) + "</span>");
}

function restaurarBoton(selector) {
  const $boton = $(selector);
  const htmlOriginal = $boton.data("html-original");

  if (htmlOriginal) {
    $boton.html(htmlOriginal);
  }

  $boton.prop("disabled", false);
}

function alternarBotonLimpiar(inputSelector, botonSelector) {
  const tieneTexto = $.trim($(inputSelector).val()).length > 0;
  $(botonSelector).toggle(tieneTexto);
}

function activarTooltips() {
  if ($.fn.tooltip) {
    $(".atributo-page [title], #modalValores [title]").tooltip("dispose").tooltip({
      container: "body",
      trigger: "hover",
    });
  }
}

function normalizarTexto(texto) {
  return String(texto || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .trim();
}

function mostrarCargandoGlobal(texto) {
  Swal.fire({
    text: texto,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: function () {
      Swal.showLoading();
    },
  });
}

function mostrarToast(mensaje) {
  Swal.fire({
    toast: true,
    position: "top-end",
    icon: "success",
    title: mensaje,
    showConfirmButton: false,
    timer: 2200,
    timerProgressBar: true,
  });
}

function mostrarAlerta(titulo, mensaje, icono) {
  Swal.fire(titulo, mensaje, icono);
}

function mostrarErrorAjax(xhr, mensajePorDefecto) {
  const mensaje = obtenerMensajeAjax(xhr, mensajePorDefecto);
  const titulo = xhr && (xhr.status === 409 || xhr.status === 422) ? "Atención" : "Error";
  const icono = titulo === "Atención" ? "warning" : "error";
  mostrarAlerta(titulo, mensaje, icono);
}

function obtenerMensajeAjax(xhr, mensajePorDefecto) {
  let mensaje = mensajePorDefecto;

  if (xhr && xhr.responseJSON && xhr.responseJSON.mensaje) {
    return xhr.responseJSON.mensaje;
  }

  if (xhr && xhr.responseText) {
    try {
      const respuesta = JSON.parse(xhr.responseText);
      if (respuesta.mensaje) {
        mensaje = respuesta.mensaje;
      }
    } catch (error) {
      console.error(xhr.responseText);
    }
  }

  return mensaje;
}

function escaparHtml(texto) {
  return $("<div>").text(texto == null ? "" : String(texto)).html();
}

$(document).ready(function () {
  init();
});
