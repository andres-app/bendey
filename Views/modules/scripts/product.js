var tabla;

$(document).ready(function () {
  init();
  cargarOpcionesAtributos();
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
        exportOptions: { columns: [0, 1, 2, 4, 5, 6] }
      },
      {
        extend: "pdfHtml5",
        text: '<i class="fa fa-file-pdf-o"></i> PDF',
        title: "Reporte de Artículos",
        exportOptions: { columns: [0, 1, 2, 4, 5, 6] }
      }
    ],
    ajax: {
      url: "Controllers/Product.php?op=listar_json_todo",
      type: "get",
      dataType: "json",
      dataSrc: function (json) {
        console.log("📦 Datos recibidos del backend:", json); // 👈 Agrega esto
        return json;
      }
    },
    columns: [
      { data: "codigo" },
      { data: "nombre" },
      { data: "categoria", defaultContent: "Sin categoría" },
      { data: "subcategoria", defaultContent: "Sin subcategoría" },
      { data: "medida", defaultContent: "-" },
      {
        data: "stock",
        render: function (data, type, row) {
          const stock = parseInt(data);
          if (stock <= 10) {
            return `<button class="btn btn-danger btn-sm">${stock}</button>`;
          } else if (stock > 10 && stock < 30) {
            return `<button class="btn btn-warning btn-sm">${stock}</button>`;
          } else {
            return `<button class="btn btn-success btn-sm">${stock}</button>`;
          }
        }
      },
      {
        data: "imagen",
        render: function (data, type, row) {
          return data
            ? `<img src="Assets/img/products/${data}" height="40" style="border-radius:4px;">`
            : "Sin imagen";
        }
      },
      {
        data: "precio_compra",
        render: function (data, type, row) {
          // Si es producto padre y tiene variaciones
          if (row.tiene_variaciones && parseFloat(data) === 0) {
            return "-";
          }
          return parseFloat(data) === 0 ? "-" : parseFloat(data).toFixed(2);
        }
      },
      {
        data: "precio_venta",
        render: function (data, type, row) {
          if (row.tiene_variaciones && parseFloat(data) === 0) {
            return "-";
          }
          return parseFloat(data) === 0 ? "-" : parseFloat(data).toFixed(2);
        }
      },

      {
        data: "condicion",
        render: function (data) {
          return data == 1
            ? '<div class="badge badge-success">Activo</div>'
            : '<div class="badge badge-danger">Inactivo</div>';
        },
        defaultContent: "-"
      },
      { data: "almacen", defaultContent: "Sin almacén" }, // <- ya corresponde con <th>Almacén</th>
      {
        data: null,
        render: function (data, type, row) {
          const id = row.idarticulo || row.idvariacion;
          const estado = row.condicion == 1;
          return `
            <button class="btn btn-primary btn-sm details-control"><i class="fas fa-eye"></i></button>
            <button class="btn btn-warning btn-sm" onclick="mostrar(${id})"><i class="fas fa-pencil-alt"></i></button>
            <button class="btn ${estado ? 'btn-danger' : 'btn-primary'} btn-sm" onclick="${estado ? 'desactivar' : 'activar'}(${id})">
              <i class="fas ${estado ? 'fa-times' : 'fa-check'}"></i>
            </button>
          `;
        }
      }
    ],




    bDestroy: true,
    iDisplayLength: 10,
    order: [[0, "desc"]]
  });

  $('#tbllistado tbody').on('click', '.details-control', function () {
    var tr = $(this).closest('tr');
    var row = tabla.row(tr);

    if (row.child.isShown()) {
      row.child.hide();
      tr.removeClass('shown');
    } else {
      $.post("Controllers/Product.php?op=variaciones_por_articulo", { idarticulo: row.data().idarticulo }, function (res) {
        const variaciones = JSON.parse(res);

        if (variaciones.length === 0) {
          row.child("<em>Este producto no tiene variaciones registradas.</em>").show();
          tr.addClass('shown');
          return;
        }

        let html = "<table class='table table-bordered table-sm mb-0'><thead><tr><th>Combinación</th><th>SKU</th><th>Stock</th><th>Precio Compra</th><th>Precio Venta</th></tr></thead><tbody>";

        variaciones.forEach(v => {
          html += `<tr>
            <td>${v.combinacion}</td>
            <td>${v.sku}</td>
            <td>${v.stock}</td>
            <td>${v.precio_compra}</td>
            <td>${v.precio_venta}</td>
          </tr>`;
        });

        html += "</tbody></table>";
        row.child(html).show();
        tr.addClass('shown');
      });
    }
  });

}

function guardaryeditar(e) {
  e.preventDefault();

  // 🚨 Validación obligatoria si está activado el modo atributos
  if ($("#activar_atributos").is(":checked")) {
    if ($("#variaciones-lista tr").length === 0) {
      Swal.fire("Aviso", "Debes generar al menos una combinación antes de guardar.", "warning");
      return; // No sigue con el guardado
    }
  }

  $("#btnGuardar").prop("disabled", true);
  var formData = new FormData($("#formulario")[0]);

  // Recoger variaciones manualmente
  const variaciones = [];
  $("#variaciones-lista tr").each(function () {
    const combinacion = $(this).find("input[name*='combinacion']").val();
    const sku = $(this).find("input[name*='sku']").val();
    const stock = $(this).find("input[name*='stock']").val();
    const precio_compra = $(this).find("input[name*='precio_compra']").val();
    const precio_venta = $(this).find("input[name*='precio_venta']").val();

    variaciones.push({
      combinacion,
      sku,
      stock,
      precio_compra,
      precio_venta
    });
  });

  // Agregar variaciones como string JSON
  formData.append("variaciones_json", JSON.stringify(variaciones));

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
  const seleccionados = $("#atributos_seleccionados").val() || [];

  if (seleccionados.length === 0) {
    Swal.fire("Aviso", "Selecciona al menos un atributo y sus valores", "warning");
    return;
  }

  const valoresPorAtributo = [];

  let hayValores = false;

  seleccionados.forEach(id => {
    const selector = `#atributo_${id}`;
    const valores = $(selector).val() || [];

    if (valores.length > 0) hayValores = true;

    valoresPorAtributo.push(valores);
  });

  if (!hayValores) {
    Swal.fire("Aviso", "Selecciona al menos un valor para generar combinaciones", "warning");
    return;
  }

  // Generar combinaciones usando producto cartesiano
  function combinar(listas) {
    return listas.reduce((a, b) =>
      a.flatMap(d => b.map(e => [...d, e])), [[]]);
  }

  const combinacionesCrudas = combinar(valoresPorAtributo);

  let html = "";

  combinacionesCrudas.forEach((combo, index) => {
    const combinacionTexto = combo.join(" - ");
    html += `
      <tr>
        <td><input type="text" name="variaciones[${index}][combinacion]" class="form-control" value="${combinacionTexto}" readonly></td>
        <td><input type="text" name="variaciones[${index}][sku]" class="form-control" placeholder="SKU"></td>
        <td><input type="number" name="variaciones[${index}][stock]" class="form-control" placeholder="Stock"></td>
        <td><input type="number" name="variaciones[${index}][precio_compra]" class="form-control" placeholder="Precio Compra" step="0.01"></td>
        <td><input type="number" name="variaciones[${index}][precio_venta]" class="form-control" placeholder="Precio Venta" step="0.01"></td>
      </tr>
    `;
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

function cargarAtributosDinamicos() {
  $.get("Controllers/Atributo.php?op=atributos_activos", function (data) {
    const atributos = JSON.parse(data);
    const contenedor = $("#contenedor_atributos");
    contenedor.empty();

    atributos.forEach(attr => {
      const selectId = `atributo_${attr.idatributo}`;
      const placeholder = `Selecciona ${attr.nombre.toLowerCase()}`;
      const label = `<label for="${selectId}">${attr.nombre}:</label>`;
      const select = `
        <select id="${selectId}" class="form-control select2" multiple
                data-id="${attr.idatributo}" data-placeholder="${placeholder}" style="width: 100%;">
        </select>`;

      const formGroup = `<div class="form-group col-lg-6">${label}${select}</div>`;
      contenedor.append(formGroup);

      // Cargar valores por atributo
      cargarValoresAtributo(attr.idatributo, `#${selectId}`);
    });
  });
}

function toggleAtributos() {
  const activo = document.getElementById("activar_atributos").checked;

  // Mostrar u ocultar la sección de atributos
  $("#atributos_section").toggle(activo);

  // Ocultar o mostrar los campos principales según el estado
  $("#grupo_sku_principal").toggle(!activo);
  $("#grupo_stock_principal").toggle(!activo);
  $("#grupo_precio_compra_principal").toggle(!activo);
  $("#grupo_precio_venta_principal").toggle(!activo);

  // Si se activa, cargar atributos dinámicos
  if (activo) {
    const seleccionados = $("#atributos_seleccionados").val() || [];
    cargarAtributosDinamicosSeleccionados(seleccionados);
  } else {
    $("#contenedor_atributos").empty();
    $("#variaciones-lista").empty();
    $("#variaciones-container").hide();
  }
}


function cargarOpcionesAtributos() {
  $.get("Controllers/Atributo.php?op=atributos_activos", function (data) {
    const atributos = JSON.parse(data);
    const select = $("#atributos_seleccionados");
    select.empty();

    atributos.forEach(attr => {
      select.append(`<option value="${attr.idatributo}">${attr.nombre}</option>`);
    });

    // Inicializar select2
    select.select2({
      allowClear: true,
      width: 'resolve'
    });
  });
}

function cargarAtributosDinamicosSeleccionados(idsSeleccionados) {
  $.get("Controllers/Atributo.php?op=atributos_activos", function (data) {
    const atributos = JSON.parse(data);
    const contenedor = $("#contenedor_atributos");
    contenedor.empty();

    atributos.forEach(attr => {
      if (!idsSeleccionados.includes(attr.idatributo.toString())) return;

      const selectId = `atributo_${attr.idatributo}`;
      const placeholder = `Selecciona ${attr.nombre.toLowerCase()}`;
      const label = `<label for="${selectId}">${attr.nombre}:</label>`;
      const select = `
        <select id="${selectId}" class="form-control select2" multiple
                data-id="${attr.idatributo}" data-nombre="${attr.nombre}"
                data-placeholder="${placeholder}" style="width: 100%;">
        </select>`;

      const formGroup = `<div class="form-group col-lg-6">${label}${select}</div>`;
      contenedor.append(formGroup);

      cargarValoresAtributo(attr.idatributo, `#${selectId}`);
    });
  });
}

$("#atributos_seleccionados").on("change", function () {
  const seleccionados = $(this).val();
  cargarAtributosDinamicosSeleccionados(seleccionados);
});


