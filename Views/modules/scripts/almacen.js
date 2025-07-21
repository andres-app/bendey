let tabla;

function init() {
  mostrarform(false);
  listar();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });
}

// Mostrar u ocultar formulario
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

// Cancelar formulario
function cancelarform() {
  limpiar();
  mostrarform(false);
}

// Limpiar formulario
function limpiar() {
  $("#idalmacen").val("");
  $("#nombre").val("");
  $("#ubicacion").val("");
  $("#descripcion").val("");
}

// Listar registros
function listar() {
  tabla = $('#tbllistado').DataTable({
    "ajax": {
      url: 'Controllers/Almacen.php?op=listar',
      type: "GET",
      dataType: "json",
      error: function (e) {
        console.error("Error cargando almacenes:", e.responseText);
      }
    },
    dom: "Bfrtip",
    buttons: [
      {
        extend: "excelHtml5",
        text: '<i class="fa fa-file-excel-o"></i> Excel',
        titleAttr: "Exportar a Excel",
        title: "Reporte de Almacenes",
        sheetName: "Almacenes",
        exportOptions: {
          columns: [0, 1, 2, 3, 4],
        },
      },
      {
        extend: "pdfHtml5",
        text: '<i class="fa fa-file-pdf-o"></i> PDF',
        titleAttr: "Exportar a PDF",
        title: "Reporte de Almacenes",
        pageSize: "A4",
        exportOptions: {
          columns: [0, 1, 2, 3, 4],
        },
      },
    ],
    "destroy": true,
    "responsive": true,
    "order": [[0, "desc"]],
    "language": {
      "lengthMenu": "Mostrar _MENU_ registros",
      "zeroRecords": "No se encontraron resultados",
      "info": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
      "infoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
      "infoFiltered": "(filtrado de un total de _MAX_ registros)",
      "search": "Buscar:",
      "paginate": {
        "first": "Primero",
        "last": "Último",
        "next": "Siguiente",
        "previous": "Anterior"
      },
    }
  });
}

// Envío del formulario (ajusta si tienes guardaryeditar)
function guardaryeditar(e) {
  e.preventDefault();
  $("#btnGuardar").prop("disabled", true);
  const formData = new FormData($("#formulario")[0]);

  $.ajax({
    url: "Controllers/Almacen.php?op=guardaryeditar",
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,
    success: function (datos) {
      swal({
        title: "Almacén",
        text: datos,
        icon: "info",
        button: "OK",
      });
      mostrarform(false);
      tabla.ajax.reload();
    }
  });

  limpiar();
}

init();
