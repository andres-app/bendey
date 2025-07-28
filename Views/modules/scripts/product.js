var tabla;

$(document).ready(function () {
  init();
  cargarValoresAtributo(1, '#color'); // Color
  cargarValoresAtributo(2, '#talla'); // Talla
});

function init() {
  mostrarform(false);
  listar();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });

  // Cargar selects principales
  $.post("Controllers/Almacen.php?op=selectAlmacen", r => $("#idalmacen").html(r));
  $.post("Controllers/Category.php?op=selectCategoria", r => $("#idcategoria").html(r));
  $.post("Controllers/Medida.php?op=selectMedida", r => $("#idmedida").html(r));

  $("#imagenmuestra").hide();
}

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

function limpiar() {
  $("#formulario")[0].reset();
  $("#imagenmuestra").attr("src", "").hide();
  $("#imagenactual").val("");
  $("#idarticulo").val("");
  $("#variaciones-lista").empty();
  $("#variaciones-container").hide();
}

function cancelarform() {
  limpiar();
  mostrarform(false);
}

function listar() {
  tabla = $("#tbllistado").DataTable({
    aProcessing: true,
    aServerSide: true,
    dom: "Bfrtip",
    buttons: [
      {
        extend: "excelHtml5",
        text: '<i class="fa fa-file-excel-o"></i> Excel',
        title: "Reporte de Productos",
        exportOptions: { columns: [1, 2, 3, 5, 6, 7] }
      },
      {
        extend: "pdfHtml5",
        text: '<i class="fa fa-file-pdf-o"></i> PDF',
        title: "Reporte de Articulos",
        exportOptions: { columns: [1, 2, 3, 5, 6, 7] }
      }
    ],
    ajax: {
      url: "Controllers/Product.php?op=listar",
      type: "get",
      dataType: "json",
      error: e => console.log(e.responseText)
    },
    bDestroy: true,
    iDisplayLength: 10,
    order: [[0, "desc"]]
  });
}

function guardaryeditar(e) {
  e.preventDefault();
  $("#btnGuardar").prop("disabled", true);
  var formData = new FormData($("#formulario")[0]);

  $.ajax({
    url: "Controllers/Product.php?op=guardaryeditar",
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,
    success: function (datos) {
      swal({
        title: "Registro",
        text: datos,
        icon: "info",
        buttons: { confirm: "OK" }
      });
      mostrarform(false);
      tabla.ajax.reload();
    }
  });

  limpiar();
}

function mostrar(idarticulo) {
  $.post("Controllers/Product.php?op=mostrar", { idarticulo }, function (data) {
    data = JSON.parse(data);
    mostrarform(true);

    $("#idcategoria").val(data.idcategoria);
    $.post("Controllers/Subcategoria.php?op=selectSubcategoria", { categoria_id: data.idcategoria }, function (r) {
      $("#idsubcategoria").html(r);
      $("#idsubcategoria").val(data.idsubcategoria);
    });

    $("#idmedida").val(data.idmedida);
    $("#codigo").val(data.codigo);
    $("#nombre").val(data.nombre);
    $("#stock").val(data.stock);
    $("#precio_compra").val(data.precio_compra ?? "");
    $("#precio_venta").val(data.precio_venta ?? "");
    $("#descripcion").val(data.descripcion);
    $("#imagenmuestra").show().attr("src", "Assets/img/products/" + data.imagen);
    $("#imagenactual").val(data.imagen);
    $("#idarticulo").val(data.idarticulo);
    generarbarcode();
  });
}

function desactivar(idarticulo) {
  swal({
    title: "Desactivar?",
    text: "¿Está seguro?",
    icon: "warning",
    buttons: { cancel: "Cancelar", confirm: "Sí, desactivar" },
    dangerMode: true
  }).then(willDelete => {
    if (willDelete) {
      $.post("Controllers/Product.php?op=desactivar", { idarticulo }, function (e) {
        swal(e, { icon: "success" });
        tabla.ajax.reload();
      });
    }
  });
}

function activar(idarticulo) {
  swal({
    text: "¿Está seguro?",
    icon: "warning",
    buttons: { cancel: "Cancelar", confirm: "Sí, activar" },
    dangerMode: true
  }).then(willDelete => {
    if (willDelete) {
      $.post("Controllers/Product.php?op=activar", { idarticulo }, function (e) {
        swal(e, { icon: "success" });
        tabla.ajax.reload();
      });
    }
  });
}

function generarbarcode() {
  let codigo = $("#codigo").val();
  JsBarcode("#barcode", codigo);
  $("#print").show();
}

function imprimir() {
  $("#print").printArea();
}

function toggleAtributos() {
  const activo = document.getElementById("activar_atributos").checked;
  document.getElementById("atributos_section").style.display = activo ? "block" : "none";
  if (activo) {
    cargarValoresAtributo(1, '#color');
    cargarValoresAtributo(2, '#talla');
  }
}

function cargarValoresAtributo(idAtributo, selector) {
  $.get("Controllers/AtributoValor.php?op=valores_por_atributo&idatributo=" + idAtributo, function (data) {
    const valores = JSON.parse(data);
    let html = "";
    valores.forEach(item => {
      html += `<option value="${item.valor}">${item.valor}</option>`;
    });
    $(selector).html(html);

    $(selector).select2({
      placeholder: $(selector).data("placeholder") || "Selecciona",
      allowClear: true,
      width: 'resolve'
    });
  });
}

function generarVariaciones() {
  const colores = $("#color").val() || [];
  const tallas = $("#talla").val() || [];
  const combinaciones = [];

  if (colores.length && tallas.length) {
    colores.forEach(color => {
      tallas.forEach(talla => {
        combinaciones.push({ combinacion: `${color} - ${talla}` });
      });
    });
  } else if (colores.length) {
    colores.forEach(color => combinaciones.push({ combinacion: color }));
  } else if (tallas.length) {
    tallas.forEach(talla => combinaciones.push({ combinacion: talla }));
  }

  if (combinaciones.length === 0) {
    Swal.fire("Aviso", "Selecciona al menos un valor de color o talla", "warning");
    return;
  }

  let html = "";
  combinaciones.forEach((item, index) => {
    html += `
      <tr>
        <td><input type="text" name="variaciones[${index}][combinacion]" class="form-control" value="${item.combinacion}" readonly></td>
        <td><input type="text" name="variaciones[${index}][sku]" class="form-control" placeholder="SKU"></td>
        <td><input type="number" name="variaciones[${index}][stock]" class="form-control" placeholder="Stock"></td>
        <td><input type="number" name="variaciones[${index}][precio]" class="form-control" placeholder="Precio" step="0.01"></td>
      </tr>`;
  });

  $("#variaciones-lista").html(html);
  $("#variaciones-container").show();
}

$("#idcategoria").on("change", function () {
  let categoriaId = $(this).val();
  $.post("Controllers/Subcategoria.php?op=selectSubcategoria", { categoria_id: categoriaId }, function (data) {
    $("#idsubcategoria").html(data);
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
    beforeSend: () => Swal.fire({ title: "Subiendo productos...", didOpen: () => Swal.showLoading() }),
    success: function (response) {
      Swal.close();
      try {
        var data = JSON.parse(response);
        let htmlSuccess = "", htmlError = "";

        if (Array.isArray(data.exitosos)) {
          htmlSuccess = "<ul>" + data.exitosos.map(msg => `<li style='color:green'>${msg}</li>`).join("") + "</ul>";
        }

        if (Array.isArray(data.errores)) {
          htmlError = "<ul>" + data.errores.map(msg => `<li style='color:red'>${msg}</li>`).join("") + "</ul>";
        }

        Swal.fire({
          title: "Resultado de la carga",
          html: htmlSuccess + htmlError,
          icon: data.errores.length > 0 ? "warning" : "success",
          width: 600
        });

        if (tabla) tabla.ajax.reload();

      } catch (e) {
        Swal.fire("Error", "Respuesta inesperada: " + response, "error");
      }
    },
    error: () => Swal.fire("Error", "No se pudo conectar con el servidor.", "error")
  });
});

function togglePlantilla() {
  const seccion = document.getElementById('plantillaSection');
  seccion.style.display = (seccion.style.display === 'none' || !seccion.style.display) ? 'block' : 'none';
}