var tabla;

/* ===============================
   INIT
=============================== */
function init() {
  mostrarform(false);
  listar();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });
}

/* ===============================
   LIMPIAR
=============================== */
function limpiar() {
  $("#idforma_pago").val("");
  $("#nombre").val("");
  $("#es_efectivo").val("0");
  $("#condicion").val("1");
}

/* ===============================
   MOSTRAR / OCULTAR FORM
=============================== */
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

/* ===============================
   LISTAR (DATATABLE)
=============================== */
function listar() {
  tabla = $("#tbllistado")
    .DataTable({
      aProcessing: true,
      aServerSide: true,
      dom: "Bfrtip",
      buttons: [
        {
          extend: "excelHtml5",
          text: '<i class="fa fa-file-excel-o"></i> Excel',
          titleAttr: "Exportar a Excel",
          title: "Reporte de Formas de Pago",
        },
        {
          extend: "pdfHtml5",
          text: '<i class="fa fa-file-pdf-o"></i> PDF',
          titleAttr: "Exportar a PDF",
          title: "Reporte de Formas de Pago",
        },
      ],
      ajax: {
        url: "Controllers/Paymentformat.php?op=listar",
        type: "get",
        dataType: "json",
        error: function (e) {
          console.log(e.responseText);
        },
      },
      bDestroy: true,
      iDisplayLength: 10,
      order: [[0, "desc"]],
    });
}

/* ===============================
   GUARDAR / EDITAR
=============================== */
function guardaryeditar(e) {
  e.preventDefault();
  $("#btnGuardar").prop("disabled", true);

  var formData = new FormData($("#formulario")[0]);

  $.ajax({
    url: "Controllers/Paymentformat.php?op=guardaryeditar",
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,
    success: function (datos) {
      swal({
        title: "Forma de Pago",
        text: datos,
        icon: "success",
        buttons: { confirm: "OK" },
      });

      mostrarform(false);
      tabla.ajax.reload();
    },
  });

  limpiar();
}

/* ===============================
   MOSTRAR
=============================== */
function mostrar(idforma_pago) {
  $.post(
    "Controllers/Paymentformat.php?op=mostrar",
    { idforma_pago: idforma_pago },
    function (data) {
      data = JSON.parse(data);
      mostrarform(true);

      $("#idforma_pago").val(data.idforma_pago);
      $("#nombre").val(data.nombre);
      $("#es_efectivo").val(data.es_efectivo);
      $("#condicion").val(data.condicion);
    }
  );
}

/* ===============================
   DESACTIVAR
=============================== */
function desactivar(idforma_pago) {
  swal({
    title: "¿Desactivar?",
    text: "¿Está seguro de desactivar esta forma de pago?",
    icon: "warning",
    buttons: {
      cancel: "No",
      confirm: "Sí, desactivar",
    },
    dangerMode: true,
  }).then((ok) => {
    if (ok) {
      $.post(
        "Controllers/Paymentformat.php?op=desactivar",
        { idforma_pago: idforma_pago },
        function (e) {
          swal(e, { icon: "success" });
          tabla.ajax.reload();
        }
      );
    }
  });
}

/* ===============================
   ACTIVAR
=============================== */
function activar(idforma_pago) {
  swal({
    title: "¿Activar?",
    text: "¿Está seguro de activar esta forma de pago?",
    icon: "warning",
    buttons: {
      cancel: "No",
      confirm: "Sí, activar",
    },
    dangerMode: true,
  }).then((ok) => {
    if (ok) {
      $.post(
        "Controllers/Paymentformat.php?op=activar",
        { idforma_pago: idforma_pago },
        function (e) {
          swal(e, { icon: "success" });
          tabla.ajax.reload();
        }
      );
    }
  });
}

init();
