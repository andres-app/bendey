var tabla;

//funcion que se ejecuta al inicio
function init() {
  mostrarform(false);
  listar();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });
}

//funcion limpiar
function limpiar() {
  $("#nombre").val("");
  $("#num_documento").val("");
  $("#direccion").val("");
  $("#telefono").val("");
  $("#email").val("");
  $("#idpersona").val("");
  $("#es_preferencial").val("0");
  $("#tipo_documento").val("DNI");
  $("#tiq-form-title").text("Nuevo cliente");
}

//funcion mostrar formulario
function mostrarform(flag) {
  limpiar();
  if (flag) {
    $("#listadoregistros").hide();
    $("#formularioregistros").show();
    $("#btnGuardar").prop("disabled", false);
    $("#btnagregar").hide();
  } else {
    $("#listadoregistros").show();
    $("#formularioregistros").hide();
    $("#btnagregar").show();
  }
}

//cancelar form
function cancelarform() {
  limpiar();
  mostrarform(false);
}

//funcion listar
function listar() {
  tabla = $("#tbllistado")
    .dataTable({
      aProcessing: true, //activamos el procedimiento del datatable
      aServerSide: true, //paginacion y filrado realizados por el server
      dom: "rtip", //búsqueda en la barra personalizada; conserva tabla, información y paginación
      ajax: {
        url: "Controllers/Person.php?op=listarc",
        type: "get",
        dataType: "json",
        error: function (e) {
          console.log(e.responseText);
        },
      },
      bDestroy: true,
      initComplete: function(settings, json) {
        if ($.fn.dataTable.Buttons) {
          var api = this.api();
          var toolbar = $('#tiq-export-toolbar');
          toolbar.empty();
          new $.fn.dataTable.Buttons(api, {buttons: [
            {extend:'excelHtml5', text:'Excel', className:'tiq-export-btn tiq-export-excel', titleAttr:'Exportar clientes a Excel', title:'Clientes', exportOptions:{columns:[1,2,3,4,5,6]}},
            {extend:'pdfHtml5', text:'PDF', className:'tiq-export-btn tiq-export-pdf', titleAttr:'Exportar clientes a PDF', title:'Clientes', pageSize:'A4', orientation:'landscape', exportOptions:{columns:[1,2,3,4,5,6]}}
          ]});
          $(api.buttons().container()).appendTo(toolbar);
        }
        // Un solo buscador sobre la tabla, al lado de Excel/PDF.
        // Se evita la fila adicional que DataTables generaba con "f" en el DOM.
        $('#tiq-clientes-buscar').off('input.tiqClientes search.tiqClientes')
          .on('input.tiqClientes search.tiqClientes', function () {
            api.search(this.value).draw();
          });
        var rs = (json && json.aaData) || [];
        $("#tiq-clientes-total").text(rs.length);
        $("#tiq-clientes-preferenciales").text(rs.filter(function(r){ return String(r[6] || "").indexOf("Preferencial") >= 0; }).length);
      },
      iDisplayLength: 10, //paginacion
      order: [[1, "asc"]], //ordenar (columna, orden)
    })
    .DataTable();
}
//funcion para guardaryeditar
function guardaryeditar(e) {
  e.preventDefault(); //no se activara la accion predeterminada
  var tipo = $("#tipo_documento").val(), numero = $("#num_documento").val().trim();
  if (numero && ((tipo === "DNI" && !/^\d{8}$/.test(numero)) || (tipo === "RUC" && !/^\d{11}$/.test(numero)))) {
    swal("Documento inválido", tipo === "DNI" ? "El DNI requiere 8 dígitos." : "El RUC requiere 11 dígitos.", "warning");return;
  }
  $("#btnGuardar").prop("disabled", true);
  var formData = new FormData($("#formulario")[0]);

  $.ajax({
    url: "Controllers/Person.php?op=guardaryeditar",
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,

    success: function (datos) {
      var tabla = $("#tbllistado").DataTable();
      if (!/^Datos (registrados|actualizados) correctamente/.test(String(datos).trim())) { $("#btnGuardar").prop("disabled",false); swal("No se pudo guardar", String(datos), "error"); return; }
      swal({
        title: "Registro",
        text: datos,
        icon: "info",
        buttons: {
          confirm: "OK",
        },
      }),
        mostrarform(false);
      tabla.ajax.reload();
      $("#btnGuardar").prop("disabled", false);
    },
    error: function () { $("#btnGuardar").prop("disabled", false); swal("Error", "No se pudieron guardar los datos del cliente.", "error"); },
  });

  // Devolver los controles a su estado normal tras responder el servidor.
}

function tiqEditarCliente(idpersona) {
  if (!Number.isInteger(Number(idpersona)) || Number(idpersona)<=0) return;
  $.post(
    "Controllers/Person.php?op=mostrar",
    { idpersona: idpersona },
    function (data, status) {
      try { data = typeof data === "string" ? JSON.parse(data) : data; } catch (_) { swal("Error", "Respuesta inválida del servidor.", "error"); return; }
      if (!data || !data.idpersona) { swal("Error", "No se encontró el cliente.", "error"); return; }
      mostrarform(true);

      $("#nombre").val(data.nombre);
      $("#tipo_documento").val(data.tipo_documento);
      //$("#tipo_documento").selectpicker("refresh");
      $("#num_documento").val(data.num_documento);
      $("#direccion").val(data.direccion);
      $("#telefono").val(data.telefono);
      $("#email").val(data.email);
      $("#es_preferencial").val(String(Number(data.es_preferencial) === 1 ? 1 : 0));
      $("#idpersona").val(data.idpersona);
      $("#tiq-form-title").text("Editar cliente");
    }, "json"
  ).fail(function(){ swal("Error", "No se pudo obtener los datos del cliente.", "error"); });
}

//funcion para desactivar
function eliminar(idpersona) {
  swal({
    title: "Eliminar?",
    text: "Esá seguro de eliminar?",
    icon: "warning",
    buttons: {
      cancel: "No, cancelar",
      confirm: "Si, eliminar",
    },
    //buttons: true,
    dangerMode: true,
  }).then((willDelete) => {
    if (willDelete) {
      $.post(
        "Controllers/Person.php?op=eliminar",
        { idpersona: idpersona },
        function (e) {
          swal(e, "Desactivado!", {
            icon: "success",
          });
          var tabla = $("#tbllistado").DataTable();
          tabla.ajax.reload();
        }
      );
    }
  });
}

function consultarCliente() {
  let num_documento = $("#num_documento").val();
  let tipo_documento = $("#tipo_documento").val();

  if (!num_documento || !tipo_documento) {
    alert("Todos los campos son necesarios para la consulta.");
    return;
  }

  $.ajax({
    url: "Controllers/Person.php?op=getCustomerInfo",
    type: "POST",
    data: { num_documento: num_documento, tipo_documento: tipo_documento },
    success: function (response) {
      let data = JSON.parse(response);
      if (data.estado) {
        // Diferenciar entre RUC y DNI al asignar los valores
        if (tipo_documento === 'RUC') {
          $("#nombre").val(data.resultado.razon_social || '');
        } else if (tipo_documento === 'DNI') {
          $("#nombre").val(data.resultado.nombre || '');
        }
        $("#direccion").val(data.resultado.direccion || '');
      } else {
        alert("Documento no encontrado: " + (data.mensaje || 'Sin detalles adicionales'));
      }
    },
    error: function (xhr, status, error) {
      console.error("Error en la petición: ", error);
      alert("Error al consultar el documento: " + xhr.responseText);
    }
  });
}













init();

// Acciones con nombres exclusivos para evitar conflictos con otros módulos.
window.tiqEditarCliente = tiqEditarCliente;

