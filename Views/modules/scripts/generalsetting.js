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
  $("#codigo").val("");
  $("#nombre").val("");
  $("#ndocumento").val("");
  $("#documento").val("");
  $("#direccion").val("");
  $("#telefono").val("");
  $("#email").val("");
  $("#pais").val("");
  $("#ciudad").val("");
  $("#nombre_impuesto").val("");
  $("#monto_impuesto").val("");
  $("#moneda").val("");
  $("#simbolo").val("");
  $("#id_negocio").val("");
  $("#tokendniruc").val(""); // Limpiar el campo del token
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
      buttons: ["excelHtml5", "pdf"],
      ajax: {
        url: "Controllers/Company.php?op=listar",
        type: "get",
        dataType: "json",
        error: function (e) {
          console.log(e.responseText);
        },
      },
      bDestroy: true,
      iDisplayLength: 5, //paginacion
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
    url: "Controllers/Company.php?op=guardaryeditar",
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

document.getElementById('toggleTokenVisibility').addEventListener('click', function () {
  var tokenInput = document.getElementById('tokendniruc');
  var eyeIcon = document.getElementById('eyeIcon');
  
  if (tokenInput.type === 'password') {
      tokenInput.type = 'text';
      eyeIcon.classList.remove('fa-eye');
      eyeIcon.classList.add('fa-eye-slash');
  } else {
      tokenInput.type = 'password';
      eyeIcon.classList.remove('fa-eye-slash');
      eyeIcon.classList.add('fa-eye');
  }
});


//funcion para mostrar datos en el formulario
function mostrar(id_negocio) {
  $.post(
    "Controllers/Company.php?op=mostrar",
    { id_negocio: id_negocio },
    function (data, status) {
      data = JSON.parse(data);
      mostrarform(true);
      $("#codigo").val(data.codigo);
      $("#nombre").val(data.nombre);
      $("#ndocumento").val(data.ndocumento);
      $("#documento").val(data.documento);
      $("#direccion").val(data.direccion);
      $("#telefono").val(data.telefono);
      $("#email").val(data.email);
      $("#pais").val(data.pais);
      $("#ciudad").val(data.ciudad);
      $("#nombre_impuesto").val(data.nombre_impuesto);
      $("#monto_impuesto").val(data.monto_impuesto);
      $("#moneda").val(data.moneda);
      $("#simbolo").val(data.simbolo);
      $("#id_negocio").val(data.id_negocio);
      $("#tokendniruc").val(data.token_reniec_sunat); // Mostrar el token en el formulario
    }
  );
}

init();
