var tabla;

function init() {
  mostrarform(false);
  listar();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });

  $("#formValor").on("submit", function (e) {
    e.preventDefault();
    const formData = new FormData($("#formValor")[0]);

    $.ajax({
      url: "Controllers/AtributoValor.php?op=guardaryeditar",
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      success: function (response) {
        Swal.fire("Éxito", response, "success");
        $("#formValor")[0].reset();
        listarValores($("#idatributo_valor").val());
      },
      error: function (e) {
        Swal.fire("Error", "No se pudo guardar el valor", "error");
        console.error("Error al guardar valor:", e.responseText);
      },
    });
  });
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

function limpiar() {
  $("#idatributo").val("");
  $("#nombre").val("");
  $("#descripcion").val("");
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
          targets: [0],
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
  $.post("Controllers/Atributo.php?op=mostrar", { idatributo }, function (data) {
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
      $.post("Controllers/Atributo.php?op=desactivar", { idatributo }, function (e) {
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
      $.post("Controllers/Atributo.php?op=activar", { idatributo }, function (e) {
        Swal.fire("Activado!", e, "success");
        tabla.ajax.reload();
      });
    }
  });
}

function gestionarValores(idatributo, nombre) {
  $("#idatributo_valor").val(idatributo);
  $("#titulo-atributo").text(nombre);
  $("#formValor")[0].reset();
  $("#idvalor").val("");
  listarValores(idatributo);
  $("#modalValores").modal("show");
}

function listarValores(idatributo) {
  $.ajax({
    url: "Controllers/AtributoValor.php?op=listar",
    type: "GET",
    data: { idatributo },
    dataType: "json",
    success: function (data) {
      let html = "";
      if (data.aaData && data.aaData.length > 0) {
        data.aaData.forEach(function (item) {
          html += `
            <tr>
              <td>${item[0]}</td>
              <td>${item[1]}</td>
              <td>
                <button class="btn btn-warning btn-sm" onclick="editarValor(${item[3]}, '${item[0]}')"><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger btn-sm" onclick="desactivarValor(${item[3]})"><i class="fas fa-times"></i></button>
              </td>
            </tr>`;
        });
      } else {
        html = `<tr><td colspan="3" class="text-center">No se encontraron valores</td></tr>`;
      }
      $("#tblvalores tbody").html(html);
    },
    error: function (e) {
      console.error("Error al listar valores:", e.responseText);
    },
  });
}

function editarValor(idvalor, valor) {
  $("#idvalor").val(idvalor); // coloca el ID para que el backend lo reconozca
  $("#valor").val(valor);     // muestra el valor actual para editar
}

function desactivarValor(idvalor) {
  Swal.fire({
    title: "¿Seguro de desactivar este valor?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí",
    cancelButtonText: "No",
  }).then((result) => {
    if (result.isConfirmed) {
      $.post("Controllers/AtributoValor.php?op=desactivar", { id: idvalor }, function (resp) {
        Swal.fire("Actualizado", resp, "success");
        listarValores($("#idatributo_valor").val());
      });
    }
  });
}

init();
