var tabla;

function init() {
  mostrarform(false);
  listar();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });
}

function limpiar() {
  $("#idatributo").val("");
  $("#nombre").val("");
  $("#descripcion").val("");
}

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

function cancelarform() {
  limpiar();
  mostrarform(false);
}

function listar() {
  tabla = $("#tbllistado")
    .dataTable({
      aProcessing: true,
      aServerSide: true,
      dom: "Bfrtip",
      buttons: ["excelHtml5", "pdf"],
      ajax: {
        url: "Controllers/Atributo.php?op=listar",
        type: "get",
        dataType: "json",
        error: function (e) {
          console.log(e.responseText);
        },
      },
      columnDefs: [
        {
          targets: [0], // Oculta la columna con índice 0 (idatributo)
          visible: false,
          searchable: false,
        },
      ],
      bDestroy: true,
      iDisplayLength: 10,
      order: [[0, "desc"]],
    })
    .DataTable();
}


function guardaryeditar(e) {
  e.preventDefault();
  $("#btnGuardar").prop("disabled", true);
  var formData = new FormData($("#formulario")[0]);

  $.ajax({
    url: "Controllers/Atributo.php?op=guardaryeditar",
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,

    success: function (datos) {
      Swal.fire("Registro", datos, "success");
      mostrarform(false);
      tabla.ajax.reload();
    },
  });

  limpiar();
}

function mostrar(idatributo) {
  $.post("Controllers/Atributo.php?op=mostrar", { idatributo: idatributo }, function (data, status) {
    data = JSON.parse(data);
    mostrarform(true);
    $("#idatributo").val(data.idatributo);
    $("#nombre").val(data.nombre);
    $("#descripcion").val(data.descripcion);
  });
}

function desactivar(idatributo) {
  Swal.fire({
    title: "¿Estás seguro?",
    text: "¡El atributo será desactivado!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Sí, desactivar!",
  }).then((result) => {
    if (result.isConfirmed) {
      $.post("Controllers/Atributo.php?op=desactivar", { idatributo: idatributo }, function (e) {
        Swal.fire("Desactivado!", e, "success");
        tabla.ajax.reload();
      });
    }
  });
}

function activar(idatributo) {
  Swal.fire({
    title: "¿Estás seguro?",
    text: "¡El atributo será activado!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Sí, activar!",
  }).then((result) => {
    if (result.isConfirmed) {
      $.post("Controllers/Atributo.php?op=activar", { idatributo: idatributo }, function (e) {
        Swal.fire("Activado!", e, "success");
        tabla.ajax.reload();
      });
    }
  });
}

function gestionarValores(idatributo, nombre) {
  $("#idatributo_valor").val(idatributo);
  $("#titulo-atributo").text(nombre);
  $("#modalValores").modal("show");
  // Aquí puedes llamar a una función que liste los valores
}

init();
