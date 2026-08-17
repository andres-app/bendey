var tabla;
var supplierSaving = false;

function init() {
  mostrarform(false);
  configurarDocumentoProveedor();
  listar();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });

  $("#tipo_documento").on("change", function () {
    configurarDocumentoProveedor();
  });

  $("#num_documento").on("input", function () {
    normalizarDocumentoProveedor(this);
  });
}

function limpiar() {
  $("#nombre").val("");
  $("#tipo_documento").val("RUC");
  $("#num_documento").val("");
  $("#direccion").val("");
  $("#telefono").val("");
  $("#email").val("");
  $("#idpersona").val("");
  $("#tipo_persona").val("Proveedor");
  configurarDocumentoProveedor();
  actualizarTituloFormularioProveedor(false);
}

function configurarDocumentoProveedor() {
  var tipo = String($("#tipo_documento").val() || "RUC").toUpperCase();
  var $documento = $("#num_documento");
  var help = "";

  if (tipo === "DNI") {
    $documento.attr({ maxlength: 8, inputmode: "numeric", placeholder: "8 dígitos" });
    help = "DNI: 8 dígitos.";
  } else if (tipo === "RUC") {
    $documento.attr({ maxlength: 11, inputmode: "numeric", placeholder: "11 dígitos" });
    help = "RUC: 11 dígitos.";
  } else {
    $documento.attr({ maxlength: 20, inputmode: "text", placeholder: "Ingresa el número" });
    help = "Cédula: hasta 20 caracteres.";
  }

  $("#supplierDocumentHelp").text(help);
  normalizarDocumentoProveedor($documento.get(0));
}

function normalizarDocumentoProveedor(input) {
  if (!input) return;

  var tipo = String($("#tipo_documento").val() || "").toUpperCase();
  var valor = String(input.value || "");

  if (tipo === "DNI" || tipo === "RUC") {
    valor = valor.replace(/\D+/g, "");
  } else {
    valor = valor.replace(/[^0-9A-Za-zÁÉÍÓÚÜÑáéíóúüñ\-\.]/g, "");
  }

  input.value = valor.slice(0, parseInt(input.maxLength, 10) || 20);
}

function actualizarTituloFormularioProveedor(editando) {
  if (editando) {
    $("#supplierFormTitle").text("Editar proveedor");
    $("#supplierFormSubtitle").text("Actualiza los datos del proveedor seleccionado.");
    $("#btnGuardar .supplier-save-label").text("Actualizar proveedor");
  } else {
    $("#supplierFormTitle").text("Nuevo proveedor");
    $("#supplierFormSubtitle").text("Completa la información comercial y de contacto.");
    $("#btnGuardar .supplier-save-label").text("Guardar proveedor");
  }
}

function mostrarform(flag) {
  if (flag) {
    $("#listadoregistros").hide();
    $("#formularioregistros").fadeIn(140);
    $("#btnGuardar").prop("disabled", false);
    $("#btnagregar").hide();
    actualizarTituloFormularioProveedor(Boolean($("#idpersona").val()));

    setTimeout(function () {
      $("#nombre").trigger("focus");
    }, 120);
  } else {
    $("#formularioregistros").hide();
    $("#listadoregistros").fadeIn(140);
    $("#btnagregar").show();
  }
}

function cancelarform() {
  limpiar();
  mostrarform(false);
}

function listar() {
  tabla = $("#tbllistado")
    .DataTable({
      processing: true,
      serverSide: false,
      autoWidth: false,
      dom: '<"supplier-dt-toolbar"<"supplier-dt-actions"B><"supplier-dt-search"f>>rt<"supplier-dt-footer"<"supplier-dt-info"i><"supplier-dt-pages"p>>',
      buttons: [
        {
          extend: "excelHtml5",
          text: '<i class="fas fa-file-excel"></i> Excel',
          titleAttr: "Exportar a Excel",
          title: "Reporte de Proveedores",
          sheetName: "Proveedores",
          exportOptions: { columns: [1, 2, 3, 4, 5] },
        },
        {
          extend: "pdfHtml5",
          text: '<i class="fas fa-file-pdf"></i> PDF',
          titleAttr: "Exportar a PDF",
          title: "Reporte de Proveedores",
          pageSize: "A4",
          exportOptions: { columns: [1, 2, 3, 4, 5] },
        },
      ],
      ajax: {
        url: "Controllers/Person.php?op=listarp",
        type: "GET",
        dataType: "json",
        dataSrc: function (json) {
          if (json && Array.isArray(json.aaData)) return json.aaData;
          if (json && Array.isArray(json.data)) return json.data;
          return [];
        },
        error: function (xhr) {
          console.error("Error al cargar proveedores:", xhr.responseText || xhr.statusText);
          swal({
            title: "No se pudo cargar",
            text: "Ocurrió un problema al leer la lista de proveedores.",
            icon: "error",
            buttons: { confirm: "Aceptar" },
          });
        },
      },
      destroy: true,
      pageLength: 10,
      order: [[1, "asc"]],
      language: {
        search: "",
        searchPlaceholder: "Buscar proveedor, RUC, dirección...",
        emptyTable: "No hay proveedores registrados.",
        zeroRecords: "No se encontraron proveedores con ese criterio.",
        info: "Mostrando _START_ a _END_ de _TOTAL_ proveedores",
        infoEmpty: "Sin proveedores para mostrar",
        infoFiltered: "(filtrado de _MAX_ registros)",
        paginate: {
          previous: "Anterior",
          next: "Siguiente",
        },
        processing: "Cargando...",
      },
      initComplete: function () {
        var $buscar = $("#tbllistado_filter input");
        $buscar
          .attr("placeholder", "Buscar proveedor, RUC, dirección...")
          .attr("aria-label", "Buscar proveedores")
          .attr("autocomplete", "off");
      },
    });
}

function validarFormularioProveedor() {
  var nombre = $.trim($("#nombre").val());
  var tipo = String($("#tipo_documento").val() || "").toUpperCase();
  var documento = $.trim($("#num_documento").val());
  var email = $.trim($("#email").val());

  if (!nombre) {
    $("#nombre").trigger("focus");
    return "Ingresa el nombre o razón social del proveedor.";
  }

  if (documento && tipo === "DNI" && !/^\d{8}$/.test(documento)) {
    $("#num_documento").trigger("focus");
    return "El DNI debe contener exactamente 8 dígitos.";
  }

  if (documento && tipo === "RUC" && !/^\d{11}$/.test(documento)) {
    $("#num_documento").trigger("focus");
    return "El RUC debe contener exactamente 11 dígitos.";
  }

  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    $("#email").trigger("focus");
    return "Ingresa un correo electrónico válido.";
  }

  return "";
}

function guardaryeditar(e) {
  e.preventDefault();

  if (supplierSaving) return;

  var error = validarFormularioProveedor();
  if (error) {
    swal({
      title: "Revisa los datos",
      text: error,
      icon: "warning",
      buttons: { confirm: "Aceptar" },
    });
    return;
  }

  $("#nombre").val($.trim($("#nombre").val()));
  $("#num_documento").val($.trim($("#num_documento").val()));
  $("#direccion").val($.trim($("#direccion").val()));
  $("#telefono").val($.trim($("#telefono").val()));
  $("#email").val($.trim($("#email").val()).toLowerCase());

  var formData = new FormData($("#formulario")[0]);
  var editando = Boolean($("#idpersona").val());

  supplierSaving = true;
  $("#formulario").addClass("supplier-saving");
  $("#btnGuardar").prop("disabled", true);
  $("#btnGuardar .supplier-save-label").text(editando ? "Actualizando..." : "Guardando...");

  $.ajax({
    url: "Controllers/Person.php?op=guardaryeditar",
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,
    dataType: "text",
  })
    .done(function (respuesta) {
      var mensaje = $.trim(respuesta || "");
      var correcto = /correctamente/i.test(mensaje);

      if (!correcto) {
        swal({
          title: "No se pudo guardar",
          text: mensaje || "El servidor no devolvió una respuesta válida.",
          icon: "error",
          buttons: { confirm: "Aceptar" },
        });
        return;
      }

      swal({
        title: editando ? "Proveedor actualizado" : "Proveedor registrado",
        text: mensaje,
        icon: "success",
        buttons: { confirm: "Aceptar" },
      });

      limpiar();
      mostrarform(false);

      if ($.fn.DataTable.isDataTable("#tbllistado")) {
        $("#tbllistado").DataTable().ajax.reload(null, false);
      }
    })
    .fail(function (xhr) {
      console.error("Error al guardar proveedor:", xhr.responseText || xhr.statusText);
      swal({
        title: "No se pudo guardar",
        text: "Ocurrió un error de comunicación al guardar el proveedor.",
        icon: "error",
        buttons: { confirm: "Aceptar" },
      });
    })
    .always(function () {
      supplierSaving = false;
      $("#formulario").removeClass("supplier-saving");
      $("#btnGuardar").prop("disabled", false);
      actualizarTituloFormularioProveedor(Boolean($("#idpersona").val()));
    });
}

function mostrar(idpersona) {
  if (!idpersona) return;

  $.ajax({
    url: "Controllers/Person.php?op=mostrar",
    type: "POST",
    data: { idpersona: idpersona },
    dataType: "json",
  })
    .done(function (data) {
      if (!data || !data.idpersona) {
        swal({
          title: "Proveedor no encontrado",
          text: "No se pudo leer la información del proveedor seleccionado.",
          icon: "warning",
          buttons: { confirm: "Aceptar" },
        });
        return;
      }

      $("#idpersona").val(data.idpersona || "");
      $("#tipo_persona").val("Proveedor");
      $("#nombre").val(data.nombre || "");
      $("#tipo_documento").val(data.tipo_documento || "RUC");
      $("#num_documento").val(data.num_documento || "");
      $("#direccion").val(data.direccion || "");
      $("#telefono").val(data.telefono || "");
      $("#email").val(data.email || "");

      configurarDocumentoProveedor();
      actualizarTituloFormularioProveedor(true);
      mostrarform(true);
    })
    .fail(function (xhr) {
      console.error("Error al leer proveedor:", xhr.responseText || xhr.statusText);
      swal({
        title: "No se pudo abrir",
        text: "Ocurrió un error al leer el proveedor.",
        icon: "error",
        buttons: { confirm: "Aceptar" },
      });
    });
}

function eliminar(idpersona) {
  if (!idpersona) return;

  swal({
    title: "¿Eliminar proveedor?",
    text: "Esta acción eliminará el registro seleccionado.",
    icon: "warning",
    buttons: {
      cancel: "Cancelar",
      confirm: "Sí, eliminar",
    },
    dangerMode: true,
  }).then(function (willDelete) {
    if (!willDelete) return;

    $.ajax({
      url: "Controllers/Person.php?op=eliminar",
      type: "POST",
      data: { idpersona: idpersona },
      dataType: "text",
    })
      .done(function (respuesta) {
        var mensaje = $.trim(respuesta || "");
        var correcto = /correctamente/i.test(mensaje);

        swal({
          title: correcto ? "Proveedor eliminado" : "No se pudo eliminar",
          text: mensaje || "No se recibió respuesta del servidor.",
          icon: correcto ? "success" : "error",
          buttons: { confirm: "Aceptar" },
        });

        if (correcto && $.fn.DataTable.isDataTable("#tbllistado")) {
          $("#tbllistado").DataTable().ajax.reload(null, false);
        }
      })
      .fail(function (xhr) {
        console.error("Error al eliminar proveedor:", xhr.responseText || xhr.statusText);
        swal({
          title: "No se pudo eliminar",
          text: "Ocurrió un error de comunicación al eliminar el proveedor.",
          icon: "error",
          buttons: { confirm: "Aceptar" },
        });
      });
  });
}

init();
