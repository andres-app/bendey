let tabla;

function init() {
  mostrarform(false);
  listar();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });

  $("#formValor").on("submit", function (e) {
    guardarEditarValor(e);
  });

  $("#btnCancelarValor").on("click", function () {
    limpiarFormularioValor(true);
  });

  $("#modalValores").on("hidden.bs.modal", function () {
    limpiarFormularioValor(false);
    $("#titulo-atributo").text("");
    $("#tblvalores tbody").html("");
  });
}

function mostrarform(flag) {
  limpiar();

  if (flag) {
    $("#listadoregistros").hide();
    $("#formularioregistros").show();
    $("#btnGuardar").prop("disabled", false);
    $("#btnagregar").hide();
    $("#nombre").trigger("focus");
  } else {
    $("#listadoregistros").show();
    $("#formularioregistros").hide();
    $("#btnagregar").show();
  }
}

function limpiar() {
  $("#idatributo").val("");
  $("#nombre").val("");
  $("#descripcion").val("");
}

function cancelarform() {
  mostrarform(false);
}

function listar() {
  tabla = $("#tbllistado").DataTable({
    processing: true,
    serverSide: false,
    dom: "Bfrtip",
    buttons: ["excelHtml5", "pdfHtml5"],
    ajax: {
      url: "Controllers/Atributo.php?op=listar",
      type: "GET",
      dataType: "json",
      dataSrc: function (json) {
        if (!json.ok) {
          Swal.fire("Error", json.mensaje || "No se pudieron listar los atributos.", "error");
          return [];
        }

        return json.aaData || [];
      },
      error: function (xhr) {
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
    ],
    destroy: true,
    pageLength: 10,
    order: [[0, "desc"]],
  });
}

function guardaryeditar(e) {
  e.preventDefault();

  const $boton = $("#btnGuardar");
  $boton.prop("disabled", true);

  $.ajax({
    url: "Controllers/Atributo.php?op=guardaryeditar",
    type: "POST",
    data: new FormData($("#formulario")[0]),
    contentType: false,
    processData: false,
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok) {
        Swal.fire("Atención", respuesta.mensaje, "warning");
        return;
      }

      Swal.fire("Registro", respuesta.mensaje, "success");
      mostrarform(false);
      tabla.ajax.reload(null, false);
    },
    error: function (xhr) {
      mostrarErrorAjax(xhr, "No se pudo guardar el atributo.");
    },
    complete: function () {
      $boton.prop("disabled", false);
    },
  });
}

function mostrar(idatributo) {
  $.ajax({
    url: "Controllers/Atributo.php?op=mostrar",
    type: "POST",
    data: { idatributo: idatributo },
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok || !respuesta.data) {
        Swal.fire("Error", respuesta.mensaje || "No se encontró el atributo.", "error");
        return;
      }

      mostrarform(true);
      $("#idatributo").val(respuesta.data.idatributo);
      $("#nombre").val(respuesta.data.nombre);
      $("#descripcion").val(respuesta.data.descripcion || "");
    },
    error: function (xhr) {
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

  Swal.fire({
    title: "¿Está seguro?",
    text: esDesactivar
      ? "El atributo dejará de estar disponible para nuevas selecciones."
      : "El atributo volverá a estar disponible.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: esDesactivar ? "Sí, desactivar" : "Sí, activar",
    cancelButtonText: "Cancelar",
  }).then(function (resultado) {
    if (!resultado.isConfirmed) {
      return;
    }

    $.ajax({
      url: "Controllers/Atributo.php?op=" + accion,
      type: "POST",
      data: { idatributo: idatributo },
      dataType: "json",
      success: function (respuesta) {
        if (!respuesta.ok) {
          Swal.fire("Error", respuesta.mensaje, "error");
          return;
        }

        Swal.fire("Actualizado", respuesta.mensaje, "success");
        tabla.ajax.reload(null, false);
      },
      error: function (xhr) {
        mostrarErrorAjax(xhr, "No se pudo cambiar el estado del atributo.");
      },
    });
  });
}

function gestionarValores(idatributo, nombre) {
  limpiarFormularioValor(false);
  $("#idatributo_valor").val(idatributo);
  $("#titulo-atributo").text(nombre);
  listarValores(idatributo);
  $("#modalValores").modal("show");
}

function guardarEditarValor(e) {
  e.preventDefault();

  const idatributo = $("#idatributo_valor").val();
  const $boton = $("#btnGuardarValor");
  $boton.prop("disabled", true);

  $.ajax({
    url: "Controllers/AtributoValor.php?op=guardaryeditar",
    type: "POST",
    data: new FormData($("#formValor")[0]),
    contentType: false,
    processData: false,
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok) {
        Swal.fire("Atención", respuesta.mensaje, "warning");
        return;
      }

      Swal.fire("Éxito", respuesta.mensaje, "success");
      limpiarFormularioValor(true);
      $("#idatributo_valor").val(idatributo);
      listarValores(idatributo);
    },
    error: function (xhr) {
      mostrarErrorAjax(xhr, "No se pudo guardar el valor.");
    },
    complete: function () {
      $boton.prop("disabled", false);
    },
  });
}

function listarValores(idatributo) {
  if (!idatributo) {
    $("#tblvalores tbody").html(
      '<tr><td colspan="3" class="text-center">Atributo no válido</td></tr>'
    );
    return;
  }

  $.ajax({
    url: "Controllers/AtributoValor.php?op=listar",
    type: "GET",
    data: { idatributo: idatributo },
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok) {
        $("#tblvalores tbody").html(
          '<tr><td colspan="3" class="text-center text-danger">' +
            escaparHtml(respuesta.mensaje || "No se pudieron listar los valores.") +
            "</td></tr>"
        );
        return;
      }

      const filas = respuesta.aaData || [];

      if (filas.length === 0) {
        $("#tblvalores tbody").html(
          '<tr><td colspan="3" class="text-center">No se encontraron valores</td></tr>'
        );
        return;
      }

      let html = "";
      filas.forEach(function (item) {
        html +=
          "<tr>" +
          "<td>" + item[0] + "</td>" +
          "<td>" + item[1] + "</td>" +
          "<td>" + item[2] + "</td>" +
          "</tr>";
      });

      $("#tblvalores tbody").html(html);
    },
    error: function (xhr) {
      mostrarErrorAjax(xhr, "No se pudieron listar los valores.");
    },
  });
}

function editarValor(idvalor) {
  $.ajax({
    url: "Controllers/AtributoValor.php?op=mostrar",
    type: "POST",
    data: { idvalor: idvalor },
    dataType: "json",
    success: function (respuesta) {
      if (!respuesta.ok || !respuesta.data) {
        Swal.fire("Error", respuesta.mensaje || "No se encontró el valor.", "error");
        return;
      }

      $("#idvalor").val(respuesta.data.idvalor);
      $("#idatributo_valor").val(respuesta.data.idatributo);
      $("#valor").val(respuesta.data.valor).trigger("focus");
      $("#labelValor").text("Editar valor");
      $("#btnGuardarValor").html('<i class="fa fa-save"></i> Actualizar valor');
      $("#btnCancelarValor").show();
    },
    error: function (xhr) {
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
    title: "¿Está seguro?",
    text: esDesactivar
      ? "El valor dejará de estar disponible para nuevas selecciones."
      : "El valor volverá a estar disponible.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: esDesactivar ? "Sí, desactivar" : "Sí, activar",
    cancelButtonText: "Cancelar",
  }).then(function (resultado) {
    if (!resultado.isConfirmed) {
      return;
    }

    $.ajax({
      url: "Controllers/AtributoValor.php?op=" + accion,
      type: "POST",
      data: { idvalor: idvalor },
      dataType: "json",
      success: function (respuesta) {
        if (!respuesta.ok) {
          Swal.fire("Error", respuesta.mensaje, "error");
          return;
        }

        Swal.fire("Actualizado", respuesta.mensaje, "success");
        limpiarFormularioValor(true);
        listarValores($("#idatributo_valor").val());
      },
      error: function (xhr) {
        mostrarErrorAjax(xhr, "No se pudo cambiar el estado del valor.");
      },
    });
  });
}

function limpiarFormularioValor(mantenerAtributo) {
  const idatributo = mantenerAtributo ? $("#idatributo_valor").val() : "";

  if ($("#formValor").length) {
    $("#formValor")[0].reset();
  }

  $("#idvalor").val("");
  $("#idatributo_valor").val(idatributo);
  $("#labelValor").text("Nuevo valor");
  $("#btnGuardarValor").html('<i class="fa fa-save"></i> Guardar valor');
  $("#btnCancelarValor").hide();
}

function mostrarErrorAjax(xhr, mensajePorDefecto) {
  let mensaje = mensajePorDefecto;

  if (xhr.responseJSON && xhr.responseJSON.mensaje) {
    mensaje = xhr.responseJSON.mensaje;
  } else if (xhr.responseText) {
    try {
      const respuesta = JSON.parse(xhr.responseText);
      if (respuesta.mensaje) {
        mensaje = respuesta.mensaje;
      }
    } catch (error) {
      console.error(xhr.responseText);
    }
  }

  Swal.fire("Error", mensaje, "error");
}

function escaparHtml(texto) {
  return $("<div>").text(texto == null ? "" : String(texto)).html();
}

$(document).ready(function () {
  init();
});
