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
      dom: "Bfrtip", //definimos los elementos del control de la tabla
      buttons: [
        {
          extend: "excelHtml5",
          text: '<i class="fa fa-file-excel-o bg-green"></i> Excel',
          titleAttr: "Exportar a Excel",
          title: "Reporte de Clientes",
          sheetName: "Clientes",
          exportOptions: {
            columns: [1, 2, 3, 4, 5],
          },
        },
        {
          extend: "pdfHtml5",
          text: '<i class="fa fa-file-pdf-o bg-red"></i> PDF',
          titleAttr: "Exportar a PDF",
          title: "Reporte de Clientes",
          //messageTop: "Reporte de usuarios",
          pageSize: "A4",
          //orientation: 'landscape',
          exportOptions: {
            columns: [1, 2, 3, 4, 5],
          },
        },
      ],
      ajax: {
        url: "Controllers/Person.php?op=listarc",
        type: "get",
        dataType: "json",
        error: function (e) {
          console.log(e.responseText);
        },
      },
      bDestroy: true,
      iDisplayLength: 10, //paginacion
      order: [[0, "desc"]], //ordenar (columna, orden)
    })
    .DataTable();
}
//funcion para guardaryeditar
function guardaryeditar(e) {
  e.preventDefault(); //no se activara la accion predeterminada
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
    },
  });

  limpiar();
}

function mostrar(idpersona) {
  $.post(
    "Controllers/Person.php?op=mostrar",
    { idpersona: idpersona },
    function (data, status) {
      data = JSON.parse(data);
      mostrarform(true);

      $("#nombre").val(data.nombre);
      $("#tipo_documento").val(data.tipo_documento);
      //$("#tipo_documento").selectpicker("refresh");
      $("#num_documento").val(data.num_documento);
      $("#direccion").val(data.direccion);
      $("#telefono").val(data.telefono);
      $("#email").val(data.email);
      $("#idpersona").val(data.idpersona);
    }
  );
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
