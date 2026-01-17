let tabla;

function init() {
  mostrarform(false);
  listar();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });
}

// ==============================
// MOSTRAR / OCULTAR FORMULARIO
// ==============================
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

// ==============================
// CANCELAR FORMULARIO
// ==============================
function cancelarform() {
  limpiar();
  mostrarform(false);
}

// ==============================
// LIMPIAR FORMULARIO
// ==============================
function limpiar() {
  $("#idalmacen").val("");
  $("#nombre").val("");
  $("#ubicacion").val("");
  $("#descripcion").val("");
}

// ==============================
// LISTAR ALMACENES
// ==============================
function listar() {
  tabla = $("#tbllistado").DataTable({
    ajax: {
      url: "Controllers/Almacen.php?op=listar",
      type: "GET",
      dataType: "json",
      error: function (e) {
        console.error("Error cargando almacenes:", e.responseText);
      },
    },
    dom: "Bfrtip",
    buttons: [
      {
        extend: "excelHtml5",
        text: '<i class="fa fa-file-excel-o"></i> Excel',
        title: "Reporte de Almacenes",
        exportOptions: {
          columns: [0, 1, 2, 3, 4],
        },
      },
      {
        extend: "pdfHtml5",
        text: '<i class="fa fa-file-pdf-o"></i> PDF',
        title: "Reporte de Almacenes",
        pageSize: "A4",
        exportOptions: {
          columns: [0, 1, 2, 3, 4],
        },
      },
    ],
    destroy: true,
    responsive: true,
    order: [[0, "desc"]],
    language: {
      lengthMenu: "Mostrar _MENU_ registros",
      zeroRecords: "No se encontraron resultados",
      info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
      infoEmpty: "Mostrando 0 a 0 de 0 registros",
      infoFiltered: "(filtrado de _MAX_ registros)",
      search: "Buscar:",
      paginate: {
        first: "Primero",
        last: "Último",
        next: "Siguiente",
        previous: "Anterior",
      },
    },
  });
}

// ==============================
// GUARDAR / EDITAR
// ==============================
function guardaryeditar(e) {
  e.preventDefault();

  $("#btnGuardar").prop("disabled", true);

  let formData = new FormData($("#formulario")[0]);

  $.ajax({
    url: "Controllers/Almacen.php?op=guardaryeditar",
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,
    success: function (resp) {
      swal({
        title: "Almacén",
        text: resp,
        icon: "success",
        button: "OK",
      });

      mostrarform(false);
      tabla.ajax.reload(null, false);
    },
    error: function () {
      swal({
        title: "Error",
        text: "No se pudo guardar el almacén",
        icon: "error",
        button: "OK",
      });
    },
    complete: function () {
      $("#btnGuardar").prop("disabled", false);
      limpiar();
    },
  });
}

// ==============================
// MOSTRAR PARA EDITAR
// ==============================
function mostrar(idalmacen) {
  $.post(
    "Controllers/Almacen.php?op=mostrar",
    { idalmacen: idalmacen },
    function (data) {
      data = JSON.parse(data);

      mostrarform(true);

      $("#idalmacen").val(data.idalmacen);
      $("#nombre").val(data.nombre);
      $("#ubicacion").val(data.ubicacion);
      $("#descripcion").val(data.descripcion);
    }
  );
}

// ==============================
// DESACTIVAR
// ==============================
function desactivar(idalmacen) {
  swal({
    title: "¿Está seguro?",
    text: "El almacén será desactivado",
    icon: "warning",
    buttons: true,
    dangerMode: true,
  }).then((willDelete) => {
    if (willDelete) {
      $.post(
        "Controllers/Almacen.php?op=desactivar",
        { idalmacen: idalmacen },
        function (resp) {
          swal("Correcto", resp, "success");
          tabla.ajax.reload(null, false);
        }
      );
    }
  });
}

// ==============================
// ACTIVAR
// ==============================
function activar(idalmacen) {
  swal({
    title: "¿Está seguro?",
    text: "El almacén será activado",
    icon: "warning",
    buttons: true,
  }).then((willActivate) => {
    if (willActivate) {
      $.post(
        "Controllers/Almacen.php?op=activar",
        { idalmacen: idalmacen },
        function (resp) {
          swal("Correcto", resp, "success");
          tabla.ajax.reload(null, false);
        }
      );
    }
  });
}

// ==============================
init();
