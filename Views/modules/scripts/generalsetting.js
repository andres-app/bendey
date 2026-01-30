// ================================
// INIT
// ================================
function init() {

  cargarDatosEmpresa();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });
}

// ================================
// CARGAR DATOS (SIN DATATABLE)
// ================================
function cargarDatosEmpresa() {
  $.get(
    "Controllers/Company.php?op=mostrar_datos",
    function (data) {

      if (!data || data === "null") {
        console.warn("No hay datos de empresa");
        return;
      }

      data = JSON.parse(data);

      $("#id_negocio").val(data.id_negocio);
      $("#nombre").val(data.nombre);
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
      $("#tokendniruc").val(data.token_reniec_sunat);
    }
  );
}

// ================================
// GUARDAR / EDITAR
// ================================
function guardaryeditar(e) {
  e.preventDefault();

  $("#btnGuardar").prop("disabled", true);
  let formData = new FormData($("#formulario")[0]);

  $.ajax({
    url: "Controllers/Company.php?op=guardaryeditar",
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,
    success: function (resp) {
      swal("Configuración", resp, "success");
      $("#btnGuardar").prop("disabled", false);
    }
  });
}

// ================================
// TOGGLE TOKEN VISIBILITY
// ================================
document
  .getElementById("toggleTokenVisibility")
  .addEventListener("click", function () {
    const input = document.getElementById("tokendniruc");
    const icon = document.getElementById("eyeIcon");

    if (input.type === "password") {
      input.type = "text";
      icon.classList.replace("fa-eye", "fa-eye-slash");
    } else {
      input.type = "password";
      icon.classList.replace("fa-eye-slash", "fa-eye");
    }
  });

init();
