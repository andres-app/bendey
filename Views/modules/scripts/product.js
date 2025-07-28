var tabla;

//funcion que se ejecuta al inicio
function init() {
  mostrarform(false);
  listar();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });

  //cargamos los items al select almacen
  $.post("Controllers/Almacen.php?op=selectAlmacen", function (r) {
    $("#idalmacen").html(r);
  });

  //cargamos los items al celect categoria
  $.post("Controllers/Category.php?op=selectCategoria", function (r) {
    $("#idcategoria").html(r);
    //$("#idcategoria").selectpicker("refresh");
  });
  $("#imagenmuestra").hide();

  //cargamos los items al select medida
  $.post("Controllers/Medida.php?op=selectMedida", function (r) {
    $("#idmedida").html(r);
    //$("#idcategoria").selectpicker("refresh");
  });
  $("#imagenmuestra").hide();
}


//funcion limpiar
function limpiar() {
  $("#codigo").val("");
  $("#nombre").val("");
  $("#descripcion").val("");
  $("#stock").val("");
  $("#precio_compra").val("");
  $("#precio_venta").val("");
  $("#imagenmuestra").attr("src", "");
  $("#imagenactual").val("");
  $("#print").hide();
  $("#idarticulo").val("");
}

//funcion mostrar formulario
function mostrarform(flag) {
  limpiar();
  if (flag) {
    $("#listadoregistros").hide();
    $("#formularioregistros").show();
    $("#btnGuardar").prop("disabled", false);
    $("#btnagregar").hide();
    $("#btnreporte").hide();
  } else {
    $("#listadoregistros").show();
    $("#formularioregistros").hide();
    $("#btnagregar").show();
    $("#btnreporte").show();
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
          text: '<i class="fa fa-file-excel-o"></i> Excel',
          titleAttr: "Exportar a Excel",
          title: "Reporte de Productos",
          sheetName: "Productos",
          exportOptions: {
            columns: [1, 2, 3, 5, 6, 7],
          },
        },
        {
          extend: "pdfHtml5",
          text: '<i class="fa fa-file-pdf-o"></i> PDF',
          titleAttr: "Exportar a PDF",
          title: "Reporte de Articulos",
          //messageTop: "Reporte de usuarios",
          pageSize: "A4",
          //orientation: 'landscape',
          exportOptions: {
            columns: [1, 2, 3, 5, 6, 7],
          },
        },
      ],
      ajax: {
        url: "Controllers/Product.php?op=listar",
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
    url: "Controllers/Product.php?op=guardaryeditar",
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

function mostrar(idarticulo) {
  $.post(
    "Controllers/Product.php?op=mostrar",
    { idarticulo: idarticulo },
    function (data, status) {
      data = JSON.parse(data);
      mostrarform(true);

      $("#idcategoria").val(data.idcategoria);
      $.post("Controllers/subcategoria.php?op=selectSubcategoria", { categoria_id: data.idcategoria }, function (r) {
        $("#idsubcategoria").html(r);
        $("#idsubcategoria").val(data.idsubcategoria);
      });
      $("#idmedida").val(data.idmedida);
      //$("#idcategoria").selectpicker("refresh");
      $("#codigo").val(data.codigo);
      $("#nombre").val(data.nombre);
      $("#stock").val(data.stock);
      $("#precio_compra").val(data.precio_compra ?? "");
      $("#precio_venta").val(data.precio_venta ?? "");
      $("#descripcion").val(data.descripcion);
      $("#imagenmuestra").show();
      $("#imagenmuestra").attr("src", "Assets/img/products/" + data.imagen);
      $("#imagenactual").val(data.imagen);
      $("#idarticulo").val(data.idarticulo);
      generarbarcode();
    }
  );
}

//funcion para desactivar
//funcion para desactivar
function desactivar(idarticulo) {
  swal({
    title: "Desactivar?",
    text: "Esá seguro de desactivar?",
    icon: "warning",
    buttons: {
      cancel: "No, cancelar",
      confirm: "Si, desactivar",
    },
    //buttons: true,
    dangerMode: true,
  }).then((willDelete) => {
    if (willDelete) {
      $.post(
        "Controllers/Product.php?op=desactivar",
        { idarticulo: idarticulo },
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

function activar(idarticulo) {
  swal({
    //title: "Activar?",
    text: "Esá seguro de activar?",
    icon: "warning",
    buttons: {
      cancel: "No, cancelar",
      confirm: "Si, activar",
    },
    //buttons: true,
    dangerMode: true,
  }).then((willDelete) => {
    if (willDelete) {
      $.post(
        "Controllers/Product.php?op=activar",
        { idarticulo: idarticulo },
        function (e) {
          swal(e, "Activado!", {
            icon: "success",
          });
          var tabla = $("#tbllistado").DataTable();
          tabla.ajax.reload();
        }
      );
    }
  });
}

function generarbarcode() {
  codigo = $("#codigo").val();
  JsBarcode("#barcode", codigo);
  $("#print").show();
}

function imprimir() {
  $("#print").printArea();
}


function toggleAtributos() {
  const checked = document.getElementById("activar_atributos").checked;
  document.getElementById("atributos_section").style.display = checked ? "flex" : "none";
}

$("#idcategoria").on("change", function () {
  let categoriaId = $(this).val();

  $.ajax({
    url: "Controllers/Subcategoria.php?op=selectSubcategoria",
    method: "POST",
    data: { categoria_id: categoriaId },
    success: function (data) {
      $("#idsubcategoria").html(data);
    }
  });
});

$("#formSubidaMasiva").on("submit", function (e) {
  e.preventDefault();
  var formData = new FormData(this);

  $.ajax({
    url: "Controllers/Product.php?op=subirMasivo",
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,
    beforeSend: function () {
      Swal.fire({ title: "Subiendo productos...", allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    },
    success: function (response) {
      Swal.close();
      try {
        var data = JSON.parse(response);

        if (data.success) {
          let htmlSuccess = "", htmlError = "";

          if (Array.isArray(data.exitosos) && data.exitosos.length > 0) {
            htmlSuccess = "<ul style='text-align:left'>";
            data.exitosos.forEach(msg => {
              htmlSuccess += "<li style='color:green'>" + msg + "</li>";
            });
            htmlSuccess += "</ul>";
          }

          if (Array.isArray(data.errores) && data.errores.length > 0) {
            htmlError = "<ul style='text-align:left'>";
            data.errores.forEach(msg => {
              htmlError += "<li style='color:red'>" + msg + "</li>";
            });
            htmlError += "</ul>";
          }

          Swal.fire({
            title: "Resultado de la carga",
            html: htmlSuccess + htmlError,
            icon: data.errores.length > 0 ? "warning" : "success",
            width: 600
          });

          if (typeof tabla !== 'undefined') tabla.ajax.reload();

        } else {
          Swal.fire("Error", data.mensaje || "Ocurrió un error inesperado.", "error");
        }

      } catch (e) {
        Swal.fire("Error", "Error al procesar la respuesta: " + response, "error");
      }
    },
    error: function () {
      Swal.close();
      Swal.fire("Error", "No se pudo conectar con el servidor.", "error");
    }
  });
});

function togglePlantilla() {
  const seccion = document.getElementById('plantillaSection');
  if (seccion.style.display === 'none' || seccion.style.display === '') {
      seccion.style.display = 'block';
  } else {
      seccion.style.display = 'none';
  }
}

function cargarAtributos() {
  cargarValoresAtributo(1, '#color'); // 1 = ID atributo Color
  cargarValoresAtributo(2, '#talla'); // 2 = ID atributo Talla
}

function cargarValoresAtributo(idAtributo, selector) {
  $.getJSON("Controllers/AtributoValorController.php?op=listar_por_atributo&idatributo=" + idAtributo, function (data) {
    let opciones = '';
    data.forEach(function (item) {
      opciones += `<option value="${item.idvalor}">${item.valor}</option>`;
    });
    $(selector).html(opciones);
  });
}

function generarVariaciones() {
  const colores = Array.from(document.querySelectorAll('#color option:checked')).map(o => ({ id: o.value, text: o.text }));
  const tallas = Array.from(document.querySelectorAll('#talla option:checked')).map(o => ({ id: o.value, text: o.text }));

  if (colores.length === 0 || tallas.length === 0) {
    Swal.fire('Error', 'Selecciona al menos un color y una talla', 'warning');
    return;
  }

  const contenedor = document.getElementById("variaciones-lista");
  contenedor.innerHTML = "";

  colores.forEach(color => {
    tallas.forEach(talla => {
      const combinacion = `
        <div class="col-md-6 mb-3">
          <div class="card p-2 border">
            <strong>${color.text} / ${talla.text}</strong>
            <input type="hidden" name="combinaciones[][atributos][]" value="${color.id}">
            <input type="hidden" name="combinaciones[][atributos][]" value="${talla.id}">
            <div class="form-group">
              <label>SKU</label>
              <input type="text" class="form-control" name="combinaciones[][sku]" value="SKU-${color.text}-${talla.text}">
            </div>
            <div class="form-group">
              <label>Precio Venta</label>
              <input type="number" step="0.01" class="form-control" name="combinaciones[][precio_venta]" required>
            </div>
            <div class="form-group">
              <label>Precio Compra</label>
              <input type="number" step="0.01" class="form-control" name="combinaciones[][precio_compra]">
            </div>
            <div class="form-group">
              <label>Stock</label>
              <input type="number" class="form-control" name="combinaciones[][stock]" value="0">
            </div>
          </div>
        </div>
      `;
      contenedor.insertAdjacentHTML("beforeend", combinacion);
    });
  });

  document.getElementById("variaciones-container").style.display = "block";
}

$(document).ready(function () {
  init();
  cargarAtributos();
});

init();
