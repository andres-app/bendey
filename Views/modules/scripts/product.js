"use strict";

var tabla;
var secuenciaCargaAlmacen = 0;
var productosCargados = [];
var vistaProductos = localStorage.getItem("tp_productos_vista") || "tabla";
var limiteGridProductos = 24;
var filtroDataTableRegistrado = false;
var filtroRapidoProducto = "todos";
var productoDetalleActual = null;
var catalogosMasivosProducto = { categorias: [], subcategorias: [], almacenes: [], medidas: [] };
var catalogosMasivosCargados = false;
var cargandoCatalogosMasivos = null;
var secuenciaFilaMasiva = 0;
var configuracionTributariaProducto = {
  afectacion: "10",
  porcentaje: 18,
  unidad: "NIU"
};

$(document).ready(function () {
  init();
  cargarOpcionesAtributos();
  cargarConfiguracionTributariaProducto();
  registrarEventosTributariosProducto();
  registrarEventosInterfazProductos();
});

function registrarEventosInterfazProductos() {
  $("#productoBuscar").on("input", function () {
    if (tabla) tabla.search(this.value).draw();
  });

  $("#filtroCategoriaProducto, #filtroTributoProducto, #filtroEstadoProducto")
    .on("change", function () {
      limiteGridProductos = 24;
      if (tabla) tabla.draw();
    });

  $("#filtroStockProducto").on("change", function () {
    const stock = String($(this).val() || "");
    filtroRapidoProducto = stock === "bajo" || stock === "sin_stock" ? stock : (stock === "" ? "todos" : "ninguno");
    limiteGridProductos = 24;
    actualizarEstadoFiltrosRapidosProducto();
    if (tabla) tabla.draw();
  });

  $(document).on("click", "[data-product-filter]", function () {
    aplicarFiltroRapidoProducto(String($(this).data("product-filter") || "todos"));
  });

  $("#btnVistaTabla").on("click", function () {
    cambiarVistaProductos("tabla");
  });

  $("#btnVistaGrid").on("click", function () {
    cambiarVistaProductos("grid");
  });

  $("#btnMostrarMasProductos").on("click", function () {
    limiteGridProductos += 24;
    renderizarGridProductos();
  });

  $("#imagen").on("change", function () {
    const archivo = this.files && this.files[0] ? this.files[0] : null;
    if (!archivo) return;

    const lector = new FileReader();
    lector.onload = function (evento) {
      $("#imagenmuestra").attr("src", evento.target.result).show();
    };
    lector.readAsDataURL(archivo);
  });

  $("#btnAgregarFilaMasiva").on("click", function () {
    asegurarCatalogosMasivos().then(function () { agregarFilaMasivaProducto(); });
  });

  $("#btnLimpiarMasivo").on("click", function () {
    limpiarHojaMasivaProducto(true);
  });

  $("#archivo_productos").on("change", function () {
    if (this.files && this.files[0]) previsualizarArchivoMasivoProducto(this.files[0]);
    this.value = "";
  });

  $("#btnImportarMasivo").on("click", function () {
    importarFilasMasivasProducto();
  });

  $(document).on("input change", "#cuerpoMasivoProductos .tp-sheet-cell, #cuerpoMasivoProductos .tp-sheet-select", function () {
    const $fila = $(this).closest("tr");
    if ($(this).data("field") === "categoria") {
      actualizarSubcategoriasFilaMasiva($fila, String($(this).val() || ""), "");
    }
    validarHojaMasivaProducto();
  });

  $(document).on("click", ".tp-sheet-remove", function () {
    $(this).closest("tr").remove();
    renumerarFilasMasivasProducto();
    validarHojaMasivaProducto();
  });

  $(document).on("paste", "#cuerpoMasivoProductos .tp-sheet-cell", function (evento) {
    const original = evento.originalEvent;
    const texto = original && original.clipboardData ? original.clipboardData.getData("text") : "";
    if (!texto || (!texto.includes("\t") && !texto.includes("\n") && !texto.includes("\r"))) return;
    evento.preventDefault();
    const $fila = $(this).closest("tr");
    const inicio = camposMasivosProducto().indexOf(String($(this).data("field") || ""));
    pegarMatrizMasivaProducto(parsearTextoPegadoProducto(texto), $fila, Math.max(0, inicio));
  });

  $(document).on("keydown", "#cuerpoMasivoProductos .tp-sheet-cell, #cuerpoMasivoProductos .tp-sheet-select", function (evento) {
    if (evento.key !== "Enter") return;
    evento.preventDefault();
    const $fila = $(this).closest("tr");
    const campo = String($(this).data("field") || "");
    let $siguiente = $fila.next("tr");
    if (!$siguiente.length) {
      $siguiente = agregarFilaMasivaProducto();
    }
    const $destino = $siguiente.find(`[data-field="${campo}"]`);
    if ($destino.length) $destino.trigger("focus");
  });

  $(document).on("keydown", function (evento) {
    if (evento.key === "Escape") cerrarDetalleProducto();
  });
}

function cargarSelectAlmacen(idSeleccionado = "", nombreAlmacen = "") {
  const valorSeleccionado = String(idSeleccionado ?? "").trim();
  const numeroSolicitud = ++secuenciaCargaAlmacen;

  return $.ajax({
    url: "Controllers/Almacen.php?op=selectAlmacen",
    type: "POST",
    data: { idseleccionado: valorSeleccionado },
    dataType: "html",
    cache: false
  }).done(function (respuesta) {
    if (numeroSolicitud !== secuenciaCargaAlmacen) return;

    const $almacen = $("#idalmacen");
    $almacen.html(respuesta);

    if (valorSeleccionado === "" || valorSeleccionado === "0") {
      $almacen.val("").trigger("change");
      return;
    }

    let $opcionActual = $almacen.find("option").filter(function () {
      return String($(this).val()).trim() === valorSeleccionado;
    });

    if ($opcionActual.length === 0) {
      const textoAlmacen = String(nombreAlmacen ?? "").trim() !== ""
        ? String(nombreAlmacen).trim() + " (actual)"
        : "Almacén actual — ID " + valorSeleccionado;

      $almacen.append(new Option(textoAlmacen, valorSeleccionado, true, true));
      $opcionActual = $almacen.find("option").filter(function () {
        return String($(this).val()).trim() === valorSeleccionado;
      });
    }

    $almacen.find("option").prop("selected", false);
    $opcionActual.prop("selected", true);
    $almacen.val(valorSeleccionado).trigger("change");

    if (String($almacen.val() ?? "") !== valorSeleccionado) {
      $almacen[0].value = valorSeleccionado;
      $almacen.trigger("change");
    }
  }).fail(function (xhr) {
    if (xhr.statusText !== "abort") {
      console.error("No se pudieron cargar los almacenes:", xhr.status, xhr.responseText);
    }
  });
}

function init() {
  mostrarform(false);
  registrarFiltroProductosDataTable();
  listar();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });

  cargarSelectAlmacen();
  $.post("Controllers/Category.php?op=selectCategoria", function (respuesta) {
    $("#idcategoria").html(respuesta);
  });
  $.post("Controllers/Medida.php?op=selectMedida", function (respuesta) {
    $("#idmedida").html(respuesta);
  });

  aplicarVistaProductos();
}

function cargarConfiguracionTributariaProducto() {
  return $.ajax({
    url: "Controllers/Company.php",
    type: "GET",
    dataType: "json",
    cache: false,
    data: { op: "mostrar_datos", v: Date.now() }
  }).done(function (data) {
    if (!data || typeof data !== "object") return;

    configuracionTributariaProducto = {
      afectacion: String(data.codigo_afectacion_igv_predeterminado || "10"),
      porcentaje: Number(data.porcentaje_igv_predeterminado ?? data.monto_impuesto ?? 18),
      unidad: String(data.unidad_medida_sunat_predeterminada || "NIU")
    };

    if (!$("#idarticulo").val()) aplicarConfiguracionTributariaProductoPredeterminada();
  }).fail(function (xhr) {
    console.warn("No se cargó la configuración tributaria:", xhr.status, xhr.responseText);
  });
}

function registrarEventosTributariosProducto() {
  $("#codigo_afectacion_igv")
    .off("change.tributarioProducto")
    .on("change.tributarioProducto", function () {
      sincronizarAfectacionTributariaProducto(true);
    });
}

function aplicarConfiguracionTributariaProductoPredeterminada() {
  $("#codigo_afectacion_igv").val(configuracionTributariaProducto.afectacion || "10");
  $("#unidad_medida_sunat").val(configuracionTributariaProducto.unidad || "NIU");
  $("#codigo_producto_sunat").val("");
  sincronizarAfectacionTributariaProducto(true);
}

function sincronizarAfectacionTributariaProducto(forzarTasa) {
  const codigo = String($("#codigo_afectacion_igv").val() || "10");
  const etiquetas = { "10": "Gravado", "20": "Exonerado", "30": "Inafecto", "40": "Exportación" };
  let tasa = 0;

  if (codigo === "10") {
    tasa = Number(configuracionTributariaProducto.porcentaje || 18);
    if (!forzarTasa) {
      const actual = Number($("#porcentaje_igv").val());
      if (Number.isFinite(actual) && actual > 0) tasa = actual;
    }
  }

  $("#porcentaje_igv").val(tasa.toFixed(2));
  $("#estadoTributarioProducto").html(
    '<i class="fas fa-percentage"></i> ' +
    (etiquetas[codigo] || codigo) +
    (codigo === "10" ? " " + limpiarDecimalesProducto(tasa) + "%" : " 0%")
  );
}

function validarDatosTributariosProducto() {
  const afectacion = String($("#codigo_afectacion_igv").val() || "");
  const porcentaje = Number($("#porcentaje_igv").val() || 0);
  const unidad = String($("#unidad_medida_sunat").val() || "").trim().toUpperCase();
  const codigoSunat = String($("#codigo_producto_sunat").val() || "").trim();

  if (!["10", "20", "30", "40"].includes(afectacion)) {
    Swal.fire("Afectación inválida", "Selecciona una afectación al IGV válida.", "warning");
    return false;
  }
  if (afectacion === "10" && (porcentaje <= 0 || porcentaje > 100)) {
    Swal.fire("Tasa inválida", "Un producto gravado debe tener una tasa de IGV válida.", "warning");
    return false;
  }
  if (afectacion !== "10" && porcentaje !== 0) {
    Swal.fire("Tasa inválida", "Los productos exonerados, inafectos y de exportación deben usar 0%.", "warning");
    return false;
  }
  if (!/^[A-Z0-9]{2,3}$/.test(unidad)) {
    Swal.fire("Unidad inválida", "Selecciona una unidad SUNAT válida.", "warning");
    return false;
  }
  if (codigoSunat !== "" && !/^[A-Za-z0-9._-]{4,16}$/.test(codigoSunat)) {
    Swal.fire("Código SUNAT", "El código de producto SUNAT contiene caracteres no válidos.", "warning");
    return false;
  }
  return true;
}

function mostrarform(flag) {
  limpiar();

  if (flag) {
    resetSubcategoriaUI("Seleccione subcategoría");
    $("#listadoregistros, #resumenProductos, #plantillaSection").hide().removeClass("is-open");
    $("#formularioregistros").show();
    $("#btnGuardar").prop("disabled", false);
    $("#btnagregar").hide();
    $("#tituloFormularioProducto").text("Nuevo producto");
    $("#subtituloFormularioProducto").text("Completa la información esencial. Las opciones avanzadas están organizadas por sección.");
    window.scrollTo({ top: 0, behavior: "smooth" });
  } else {
    $("#listadoregistros, #resumenProductos").show();
    $("#formularioregistros").hide();
    $("#btnagregar").show();
    aplicarVistaProductos();
  }
}

function limpiar() {
  const formulario = $("#formulario")[0];
  if (formulario) formulario.reset();

  $("#imagenmuestra").attr("src", "storage/images/products/default.png").show();
  $("#imagenactual").val("default.png");
  $("#idarticulo").val("");
  $("#variaciones-lista").empty();
  $("#variaciones-container, #atributos_section").hide();
  $("#activar_atributos").prop("checked", false);
  $("#atributos_seleccionados").val(null).trigger("change");
  resetSubcategoriaUI("Seleccione subcategoría");
  aplicarConfiguracionTributariaProductoPredeterminada();
}

function cancelarform() {
  limpiar();
  mostrarform(false);
}

function registrarFiltroProductosDataTable() {
  if (filtroDataTableRegistrado) return;

  $.fn.dataTable.ext.search.push(function (settings, data, dataIndex, rowData) {
    if (!settings.nTable || settings.nTable.id !== "tbllistado") return true;

    const producto = rowData || productosCargados[dataIndex] || {};
    const categoria = String($("#filtroCategoriaProducto").val() || "");
    const stockFiltro = String($("#filtroStockProducto").val() || "");
    const tributo = String($("#filtroTributoProducto").val() || "");
    const estado = String($("#filtroEstadoProducto").val() || "");
    const stock = Number(producto.stock || 0);

    if (categoria && String(producto.categoria || "") !== categoria) return false;
    if (tributo && String(producto.codigo_afectacion_igv || "10") !== tributo) return false;
    if (estado !== "" && String(Number(producto.condicion) === 1 ? "1" : "0") !== estado) return false;
    if (stockFiltro === "sin_stock" && stock > 0) return false;
    if (stockFiltro === "bajo" && !(stock > 0 && stock <= 10)) return false;
    if (stockFiltro === "normal" && stock <= 10) return false;
    if (filtroRapidoProducto === "variantes" && Number(producto.tiene_variaciones) !== 1) return false;

    return true;
  });

  filtroDataTableRegistrado = true;
}

function listar() {
  tabla = $("#tbllistado").DataTable({
    processing: true,
    serverSide: false,
    destroy: true,
    autoWidth: false,
    pageLength: 10,
    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
    order: [[0, "asc"]],
    dom: "Brtip",
    language: {
      processing: "Cargando productos...",
      zeroRecords: "No se encontraron productos con estos filtros.",
      emptyTable: "Todavía no hay productos registrados.",
      info: "Mostrando _START_ a _END_ de _TOTAL_ productos",
      infoEmpty: "Sin productos",
      infoFiltered: "(filtrado de _MAX_)",
      paginate: { previous: "‹", next: "›" }
    },
    buttons: [
      {
        extend: "excelHtml5",
        className: "tp-export-excel d-none",
        title: "Reporte de Productos",
        exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
      },
      {
        extend: "pdfHtml5",
        className: "tp-export-pdf d-none",
        title: "Reporte de Productos",
        orientation: "landscape",
        exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
      }
    ],
    ajax: {
      url: "Controllers/Product.php?op=listar_json_todo",
      type: "GET",
      dataType: "json",
      cache: false,
      dataSrc: function (json) {
        const datos = Array.isArray(json) ? json : (Array.isArray(json.data) ? json.data : []);
        productosCargados = datos;
        actualizarFiltrosCategorias(datos);
        actualizarKpisProductos(datos);
        return datos;
      },
      error: function (xhr) {
        console.error("Error al cargar productos:", xhr.status, xhr.responseText);
        $("#productosResultado").text("No se pudieron cargar los productos.");
      }
    },
    columns: [
      {
        data: null,
        render: function (data, type, row) {
          if (type === "sort" || type === "filter") {
            return [row.nombre, row.codigo, row.categoria, row.subcategoria, row.almacen].join(" ");
          }
          return construirCeldaProducto(row);
        }
      },
      {
        data: null,
        render: function (data, type, row) {
          if (type !== "display") return String(row.categoria || "") + " " + String(row.subcategoria || "");
          return '<div class="tp-category-cell"><strong>' + escaparHtmlProducto(row.categoria || "Sin categoría") + '</strong><small>' + escaparHtmlProducto(row.subcategoria || "Sin subcategoría") + '</small></div>';
        }
      },
      {
        data: "stock",
        render: function (data, type) {
          if (type !== "display") return Number(data || 0);
          return construirStockProducto(data);
        }
      },
      {
        data: null,
        render: function (data, type, row) {
          if (type !== "display") return Number(row.precio_venta_min ?? row.precio_venta ?? 0);
          return construirPrecioProducto(row);
        }
      },
      {
        data: "codigo_afectacion_igv",
        render: function (data, type, row) {
          if (type !== "display") return String(data || "10");
          return construirPillTributarioProducto(row);
        }
      },
      {
        data: "condicion",
        render: function (data, type) {
          if (type !== "display") return Number(data || 0);
          return Number(data) === 1
            ? '<span class="tp-state-pill tp-state-active"><i class="fas fa-check-circle"></i> Activo</span>'
            : '<span class="tp-state-pill tp-state-inactive"><i class="fas fa-pause-circle"></i> Inactivo</span>';
        }
      },
      {
        data: null,
        orderable: false,
        searchable: false,
        className: "text-right",
        render: function (data, type, row) {
          return type === "display" ? construirAccionesProducto(row) : "";
        }
      }
    ],
    createdRow: function (row, data) {
      if (Number(data.condicion) !== 1) $(row).addClass("tp-product-inactive");
    },
    drawCallback: function () {
      actualizarResultadoProductos();
      if (vistaProductos === "grid") renderizarGridProductos();
    }
  });
}

function construirCeldaProducto(row) {
  const imagen = obtenerRutaImagenProducto(row.imagen);
  const variantes = Number(row.tiene_variaciones) === 1
    ? '<small class="tp-variant-hint"><i class="fas fa-layer-group mr-1"></i>' + Number(row.cantidad_variaciones || 0) + ' variantes</small>'
    : '';

  return '<div class="tp-product-cell">' +
    '<div class="tp-product-thumb"><img src="' + imagen + '" onerror="this.src=\'storage/images/products/default.png\'" alt=""></div>' +
    '<div><strong>' + escaparHtmlProducto(row.nombre || "Sin nombre") + '</strong>' +
    '<small>SKU: ' + escaparHtmlProducto(row.codigo || "Sin código") + '</small>' + variantes + '</div></div>';
}

function construirStockProducto(valor) {
  const stock = Number(valor || 0);
  let clase = "";
  let etiqueta = "Disponible";

  if (stock <= 0) {
    clase = "tp-stock-out";
    etiqueta = "Sin stock";
  } else if (stock <= 10) {
    clase = "tp-stock-low";
    etiqueta = "Stock bajo";
  }

  return '<div class="tp-stock-wrap"><strong>' + formatearCantidadProducto(stock) + ' unidades</strong><span class="tp-stock-state ' + clase + '">' + etiqueta + '</span></div>';
}

function construirPrecioProducto(row) {
  const minimo = Number(row.precio_venta_min ?? row.precio_venta ?? 0);
  const maximo = Number(row.precio_venta_max ?? row.precio_venta ?? minimo);
  let texto = formatearMonedaProducto(minimo);
  let ayuda = "Precio unitario";

  if (Number(row.tiene_variaciones) === 1 && maximo > minimo) {
    texto = formatearMonedaProducto(minimo) + ' – ' + formatearMonedaProducto(maximo);
    ayuda = "Rango de variantes";
  } else if (Number(row.tiene_variaciones) === 1) {
    ayuda = "Precio de variantes";
  }

  return '<div class="tp-price-cell"><strong>' + texto + '</strong><small>' + ayuda + '</small></div>';
}

function construirPillTributarioProducto(row) {
  const codigo = String(row.codigo_afectacion_igv || "10");
  const porcentaje = Number(row.porcentaje_igv || 0);
  const etiquetas = { "10": "Gravado", "20": "Exonerado", "30": "Inafecto", "40": "Exportación" };
  const texto = (etiquetas[codigo] || codigo) + (codigo === "10" ? " " + limpiarDecimalesProducto(porcentaje) + "%" : "");
  return '<span class="afectacion-producto-pill afectacion-' + codigo + '">' + escaparHtmlProducto(texto) + '</span>';
}

function construirAccionesProducto(row) {
  const id = Number(row.idarticulo || 0);
  const activo = Number(row.condicion) === 1;
  const accionEstado = activo
    ? '<button class="dropdown-item text-danger" type="button" onclick="desactivar(' + id + ')"><i class="fas fa-pause-circle"></i> Desactivar</button>'
    : '<button class="dropdown-item text-success" type="button" onclick="activar(' + id + ')"><i class="fas fa-check-circle"></i> Activar</button>';

  return '<div class="tp-row-actions">' +
    '<button class="tp-row-edit" type="button" onclick="mostrar(' + id + ')"><i class="fas fa-pencil-alt mr-1"></i> Editar</button>' +
    '<div class="dropdown"><button class="tp-row-more" type="button" data-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>' +
    '<div class="dropdown-menu dropdown-menu-right">' +
    '<button class="dropdown-item" type="button" onclick="abrirDetalleProducto(' + id + ')"><i class="fas fa-eye"></i> Ver detalle</button>' +
    '<button class="dropdown-item" type="button" onclick="mostrar(' + id + ')"><i class="fas fa-pencil-alt"></i> Editar producto</button>' +
    '<button class="dropdown-item" type="button" onclick="verVariacionesProducto(' + id + ')"><i class="fas fa-layer-group"></i> Ver variaciones</button>' +
    '<div class="dropdown-divider"></div>' + accionEstado + '</div></div></div>';
}

function actualizarFiltrosCategorias(datos) {
  const actual = String($("#filtroCategoriaProducto").val() || "");
  const categorias = [...new Set(datos.map(function (item) { return String(item.categoria || "").trim(); }).filter(Boolean))].sort(function (a, b) { return a.localeCompare(b, "es"); });
  const $select = $("#filtroCategoriaProducto");
  $select.html('<option value="">Todas las categorías</option>');
  categorias.forEach(function (categoria) {
    $select.append(new Option(categoria, categoria));
  });
  $select.val(actual);
}

function actualizarKpisProductos(datos) {
  const total = datos.length;
  const bajo = datos.filter(function (item) { const stock = Number(item.stock || 0); return stock > 0 && stock <= 10; }).length;
  const sinStock = datos.filter(function (item) { return Number(item.stock || 0) <= 0; }).length;
  const variantes = datos.filter(function (item) { return Number(item.tiene_variaciones) === 1; }).length;

  animarKpiProducto("#kpiTotalProductos", total);
  animarKpiProducto("#kpiStockBajo", bajo);
  animarKpiProducto("#kpiSinStock", sinStock);
  animarKpiProducto("#kpiVariaciones", variantes);
  actualizarEstadoFiltrosRapidosProducto();
}

function animarKpiProducto(selector, valor) {
  const $elemento = $(selector);
  if (!$elemento.length) return;

  const destino = Math.max(0, Number(valor || 0));
  const inicio = Math.max(0, Number($elemento.text().replace(/[^0-9.-]/g, "")) || 0);
  const duracion = 260;
  const comienzo = performance.now();
  const $tarjeta = $elemento.closest(".tp-quick-filter");

  $tarjeta.removeClass("pulse-count");

  function paso(ahora) {
    const progreso = Math.min(1, (ahora - comienzo) / duracion);
    const suavizado = 1 - Math.pow(1 - progreso, 3);
    $elemento.text(Math.round(inicio + (destino - inicio) * suavizado));
    if (progreso < 1) {
      requestAnimationFrame(paso);
    } else {
      $elemento.text(destino);
      $tarjeta.addClass("pulse-count");
      setTimeout(function () { $tarjeta.removeClass("pulse-count"); }, 320);
    }
  }

  requestAnimationFrame(paso);
}

function aplicarFiltroRapidoProducto(filtro) {
  const permitidos = ["todos", "bajo", "sin_stock", "variantes"];
  let nuevoFiltro = permitidos.includes(filtro) ? filtro : "todos";

  if (nuevoFiltro === filtroRapidoProducto && nuevoFiltro !== "todos") {
    nuevoFiltro = "todos";
  }

  filtroRapidoProducto = nuevoFiltro;
  limiteGridProductos = 24;

  if (nuevoFiltro === "bajo") {
    $("#filtroStockProducto").val("bajo");
  } else if (nuevoFiltro === "sin_stock") {
    $("#filtroStockProducto").val("sin_stock");
  } else {
    $("#filtroStockProducto").val("");
  }

  actualizarEstadoFiltrosRapidosProducto();
  if (tabla) tabla.draw();
}

function actualizarEstadoFiltrosRapidosProducto() {
  const stock = String($("#filtroStockProducto").val() || "");
  let activo = filtroRapidoProducto;

  if (activo !== "variantes") {
    if (stock === "bajo") activo = "bajo";
    else if (stock === "sin_stock") activo = "sin_stock";
    else if (stock === "") activo = "todos";
    else activo = "ninguno";
  }

  $("[data-product-filter]").each(function () {
    const seleccionado = String($(this).data("product-filter")) === activo;
    $(this)
      .toggleClass("is-active", seleccionado)
      .attr("aria-pressed", seleccionado ? "true" : "false");
  });
}

function obtenerEtiquetaFiltroRapidoProducto() {
  if (filtroRapidoProducto === "variantes") return "Con variantes";
  const stock = String($("#filtroStockProducto").val() || "");
  if (stock === "bajo") return "Stock bajo";
  if (stock === "sin_stock") return "Sin stock";
  if (stock === "normal") return "Stock normal";
  return "";
}

function actualizarResultadoProductos() {
  if (!tabla) return;
  const visibles = tabla.rows({ search: "applied" }).count();
  const total = tabla.rows().count();
  const etiqueta = obtenerEtiquetaFiltroRapidoProducto();
  const base = visibles === total ? total + " productos" : visibles + " de " + total + " productos";
  $("#productosResultado").text(etiqueta ? base + " · " + etiqueta : base);
}

function cambiarVistaProductos(vista) {
  vistaProductos = vista === "grid" ? "grid" : "tabla";
  localStorage.setItem("tp_productos_vista", vistaProductos);
  limiteGridProductos = 24;
  aplicarVistaProductos();
}

function aplicarVistaProductos() {
  const esGrid = vistaProductos === "grid";
  $("#btnVistaGrid").toggleClass("active", esGrid);
  $("#btnVistaTabla").toggleClass("active", !esGrid);
  $("#vistaTablaProductos").toggle(!esGrid);
  $("#vistaGridProductos").toggle(esGrid);
  if (esGrid && tabla) renderizarGridProductos();
}

function renderizarGridProductos() {
  if (!tabla) return;
  const datos = tabla.rows({ search: "applied" }).data().toArray();
  const visibles = datos.slice(0, limiteGridProductos);
  const $grid = $("#productosGrid");
  $grid.empty();

  if (visibles.length === 0) {
    $grid.html('<div class="col-12 text-center py-5 text-muted">No se encontraron productos con estos filtros.</div>');
  } else {
    visibles.forEach(function (producto) {
      $grid.append(construirTarjetaProducto(producto));
    });
  }

  $("#btnMostrarMasProductos").toggle(datos.length > limiteGridProductos);
}

function construirTarjetaProducto(row) {
  const id = Number(row.idarticulo || 0);
  const activo = Number(row.condicion) === 1;
  const stock = Number(row.stock || 0);
  const precio = construirPrecioProductoTexto(row);
  return '<article class="tp-product-grid-card ' + (activo ? '' : 'is-inactive') + '">' +
    '<div class="tp-grid-image"><img src="' + obtenerRutaImagenProducto(row.imagen) + '" onerror="this.src=\'storage/images/products/default.png\'" alt=""></div>' +
    '<div class="tp-grid-body"><h3 class="tp-grid-name">' + escaparHtmlProducto(row.nombre || "Sin nombre") + '</h3>' +
    '<div class="tp-grid-sku">' + escaparHtmlProducto(row.codigo || "Sin SKU") + ' · ' + escaparHtmlProducto(row.categoria || "Sin categoría") + '</div>' +
    '<div class="tp-grid-meta"><div class="tp-grid-price">' + precio + '</div><div class="tp-grid-stock">' + formatearCantidadProducto(stock) + ' unidades<br>' + (stock <= 0 ? 'Sin stock' : (stock <= 10 ? 'Stock bajo' : 'Disponible')) + '</div></div>' +
    '<div class="tp-grid-footer">' + construirPillTributarioProducto(row) + '<div class="tp-grid-actions"><button class="btn btn-light btn-sm" onclick="abrirDetalleProducto(' + id + ')" title="Ver detalle"><i class="fas fa-eye"></i></button><button class="btn btn-success btn-sm" onclick="mostrar(' + id + ')">Editar</button></div></div>' +
    '</div></article>';
}

function construirPrecioProductoTexto(row) {
  const minimo = Number(row.precio_venta_min ?? row.precio_venta ?? 0);
  const maximo = Number(row.precio_venta_max ?? row.precio_venta ?? minimo);
  if (Number(row.tiene_variaciones) === 1 && maximo > minimo) {
    return formatearMonedaProducto(minimo) + ' – ' + formatearMonedaProducto(maximo);
  }
  return formatearMonedaProducto(minimo);
}

function exportarProductos(tipo) {
  if (!tabla) return;
  const selector = tipo === "pdf" ? ".buttons-pdf" : ".buttons-excel";
  tabla.button(selector).trigger();
}

function abrirDetalleProducto(idarticulo) {
  const id = Number.parseInt(idarticulo, 10) || 0;
  if (id <= 0) return;

  $("#detalleProductoOverlay, #detalleProductoDrawer").addClass("is-open");
  $("#detalleProductoDrawer").attr("aria-hidden", "false");
  $("#detalleProductoContenido").html('<div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm mr-2"></span>Cargando producto...</div>');
  $("#detalleProductoAcciones").hide();
  $("body").css("overflow", "hidden");

  const solicitudProducto = $.ajax({
    url: "Controllers/Product.php?op=mostrar",
    type: "POST",
    dataType: "json",
    data: { idarticulo: id }
  });
  const solicitudVariaciones = $.ajax({
    url: "Controllers/Product.php?op=variaciones_por_articulo",
    type: "POST",
    dataType: "json",
    data: { idarticulo: id }
  });

  $.when(solicitudProducto, solicitudVariaciones).done(function (respuestaProducto, respuestaVariaciones) {
    const producto = respuestaProducto[0] || {};
    const variaciones = Array.isArray(respuestaVariaciones[0]) ? respuestaVariaciones[0] : [];
    productoDetalleActual = producto;
    renderizarDetalleProducto(producto, variaciones);
  }).fail(function (xhr) {
    $("#detalleProductoContenido").html('<div class="alert alert-danger">No se pudo cargar el detalle del producto.</div>');
    console.error("Detalle producto:", xhr.responseText);
  });
}

function renderizarDetalleProducto(producto, variaciones) {
  const costo = Number(producto.precio_compra || 0);
  const venta = Number(producto.precio_venta || 0);
  const margen = venta > 0 ? venta - costo : 0;
  const rentabilidad = venta > 0 ? (margen / venta) * 100 : 0;
  const stock = Number(producto.stock_total ?? producto.stock ?? 0);
  const activo = Number(producto.condicion) === 1;
  const descripcion = String(producto.descripcion || "").trim();

  let html = '<div class="tp-detail-cover"><div class="tp-detail-cover-image"><img src="' + obtenerRutaImagenProducto(producto.imagen) + '" onerror="this.src=\'storage/images/products/default.png\'" alt=""></div><div><h3>' + escaparHtmlProducto(producto.nombre || "Sin nombre") + '</h3><p>SKU: ' + escaparHtmlProducto(producto.codigo || "Sin código") + '</p><div class="mt-2">' + (activo ? '<span class="tp-state-pill tp-state-active">Activo</span>' : '<span class="tp-state-pill tp-state-inactive">Inactivo</span>') + '</div></div></div>';

  html += '<div class="tp-detail-grid">' +
    detalleCajaProducto("Precio de venta", construirPrecioProductoTexto(producto)) +
    detalleCajaProducto("Stock disponible", formatearCantidadProducto(stock) + " unidades") +
    detalleCajaProducto("Categoría", producto.categoria || "Sin categoría") +
    detalleCajaProducto("Subcategoría", producto.subcategoria || "Sin subcategoría") +
    detalleCajaProducto("Almacén", producto.almacen_nombre || producto.almacen || "Sin almacén") +
    detalleCajaProducto("Unidad", producto.medida || "Sin unidad") +
    detalleCajaProducto("Costo unitario", costo > 0 ? formatearMonedaProducto(costo) : "No registrado") +
    detalleCajaProducto("Margen estimado", costo > 0 && venta > 0 ? formatearMonedaProducto(margen) + " · " + limpiarDecimalesProducto(rentabilidad) + "%" : "No disponible") +
    '</div>';

  html += '<div class="tp-detail-section"><h5>Tributación</h5><div class="tp-detail-grid">' +
    detalleCajaProducto("Afectación IGV", textoAfectacionProducto(producto)) +
    detalleCajaProducto("Unidad SUNAT", producto.unidad_medida_sunat || "NIU") +
    detalleCajaProducto("Código SUNAT", producto.codigo_producto_sunat || "No registrado") +
    detalleCajaProducto("Variaciones", variaciones.length ? variaciones.length + " registradas" : "Sin variaciones") +
    '</div></div>';

  if (descripcion) {
    html += '<div class="tp-detail-section"><h5>Descripción</h5><div class="tp-detail-box"><strong>' + escaparHtmlProducto(descripcion) + '</strong></div></div>';
  }

  if (variaciones.length) {
    html += '<div class="tp-detail-section"><h5>Variaciones</h5><div class="tp-detail-variants">';
    variaciones.forEach(function (v) {
      html += '<div class="tp-detail-variant"><div><strong>' + escaparHtmlProducto(v.combinacion || "Variación") + '</strong><small>SKU: ' + escaparHtmlProducto(v.sku || "Sin código") + ' · Stock: ' + formatearCantidadProducto(v.stock || 0) + '</small></div><strong>' + formatearMonedaProducto(v.precio_venta || 0) + '</strong></div>';
    });
    html += '</div></div>';
  }

  $("#detalleProductoContenido").html(html);
  $("#detalleProductoAcciones").show();
  $("#btnEditarDesdeDetalle").off("click").on("click", function () {
    cerrarDetalleProducto();
    mostrar(producto.idarticulo);
  });
  $("#btnEstadoDesdeDetalle").text(activo ? "Desactivar" : "Activar").off("click").on("click", function () {
    cerrarDetalleProducto();
    if (activo) desactivar(producto.idarticulo); else activar(producto.idarticulo);
  });
}

function detalleCajaProducto(etiqueta, valor) {
  return '<div class="tp-detail-box"><span>' + escaparHtmlProducto(etiqueta) + '</span><strong>' + escaparHtmlProducto(valor) + '</strong></div>';
}

function cerrarDetalleProducto() {
  $("#detalleProductoOverlay, #detalleProductoDrawer").removeClass("is-open");
  $("#detalleProductoDrawer").attr("aria-hidden", "true");
  $("body").css("overflow", "");
}

function verVariacionesProducto(idarticulo) {
  abrirDetalleProducto(idarticulo);
}

function obtenerRutaImagenProducto(imagen) {
  const nombre = String(imagen || "default.png").replace(/^.*[\\/]/, "");
  return "storage/images/products/" + encodeURIComponent(nombre || "default.png");
}

function textoAfectacionProducto(producto) {
  const codigo = String(producto.codigo_afectacion_igv || "10");
  const etiquetas = { "10": "Gravado", "20": "Exonerado", "30": "Inafecto", "40": "Exportación" };
  return (etiquetas[codigo] || codigo) + (codigo === "10" ? " " + limpiarDecimalesProducto(producto.porcentaje_igv || 0) + "%" : "");
}

function formatearMonedaProducto(valor) {
  return "S/ " + (Number(valor || 0)).toLocaleString("es-PE", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatearCantidadProducto(valor) {
  const numero = Number(valor || 0);
  return Number.isInteger(numero) ? String(numero) : numero.toFixed(3).replace(/0+$/, "").replace(/\.$/, "");
}

function limpiarDecimalesProducto(valor) {
  return Number(valor || 0).toFixed(2).replace(/\.00$/, "").replace(/(\.\d)0$/, "$1");
}

function escaparHtmlProducto(valor) {
  return String(valor == null ? "" : valor)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function guardaryeditar(e) {
  e.preventDefault();
  const usaAtributos = $("#activar_atributos").is(":checked");

  if (!usaAtributos) {
    const precioVenta = parseFloat($("#precio_venta").val());

    if (
      $("#precio_venta").val().trim() === "" ||
      isNaN(precioVenta) ||
      precioVenta <= 0
    ) {
      Swal.fire(
        "Precio obligatorio",
        "Ingresa un precio de venta mayor que cero.",
        "warning"
      );

      $("#precio_venta").focus();
      return;
    }
  }

  // 🚨 Validación obligatoria si está activado el modo atributos
  if ($("#activar_atributos").is(":checked")) {
    if ($("#variaciones-lista tr").length === 0) {
      Swal.fire(
        "Aviso",
        "Debes generar al menos una combinación antes de guardar.",
        "warning"
      );
      return;
    }

    let precioInvalido = false;
    let inputInvalido = null;

    $("#variaciones-lista input[name*='[precio_venta]']").each(function () {
      const precio = parseFloat($(this).val());

      if (
        $(this).val().trim() === "" ||
        isNaN(precio) ||
        precio <= 0
      ) {
        precioInvalido = true;
        inputInvalido = this;
        return false;
      }
    });

    if (precioInvalido) {
      Swal.fire(
        "Precio obligatorio",
        "Todas las variaciones deben tener un precio de venta mayor que cero.",
        "warning"
      );

      if (inputInvalido) {
        inputInvalido.focus();
      }

      return;
    }
  }

  if (!validarDatosTributariosProducto()) {
    return;
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
      const respuesta = String(datos || "").trim();
      const exitoso =
        respuesta.includes("correctamente") ||
        respuesta.includes("actualizado");

      Swal.fire({
        title: exitoso ? "Operación completada" : "No se pudo guardar",
        text: respuesta || "El servidor no devolvió una respuesta.",
        icon: exitoso ? "success" : "warning"
      });

      if (exitoso) {
        mostrarform(false);
        tabla.ajax.reload(null, false);
      }
    },

    error: function (xhr) {
      console.error(
        "Error al guardar producto:",
        xhr.status,
        xhr.responseText
      );

      Swal.fire(
        "Error",
        "No se pudo comunicar con el servidor.",
        "error"
      );
    },

    complete: function () {
      $("#btnGuardar").prop("disabled", false);
    }
  });
}

function mostrar(idarticulo) {
  $.ajax({
    url: "Controllers/Product.php?op=mostrar",
    type: "POST",
    data: {
      idarticulo: idarticulo
    },
    dataType: "json",

    success: function (data) {
      if (!data || !data.idarticulo) {
        Swal.fire(
          "Error",
          "No se encontraron los datos del producto.",
          "error"
        );
        return;
      }

      mostrarform(true);
      $("#tituloFormularioProducto").text("Editar producto");
      $("#subtituloFormularioProducto").text("Actualiza la información comercial, el inventario o la configuración tributaria.");

      $("#idarticulo").val(data.idarticulo);
      $("#codigo").val(data.codigo ?? "");
      $("#nombre").val(data.nombre ?? "");
      $("#stock").val(data.stock ?? 0);
      $("#precio_compra").val(data.precio_compra ?? "");
      $("#precio_venta").val(data.precio_venta ?? "");
      $("#descripcion").val(data.descripcion ?? "");
      $("#codigo_afectacion_igv").val(
        String(data.codigo_afectacion_igv || configuracionTributariaProducto.afectacion)
      );
      $("#porcentaje_igv").val(
        Number(data.porcentaje_igv ?? configuracionTributariaProducto.porcentaje).toFixed(2)
      );
      $("#unidad_medida_sunat").val(
        String(data.unidad_medida_sunat || configuracionTributariaProducto.unidad)
      );
      $("#codigo_producto_sunat").val(data.codigo_producto_sunat || "");
      sincronizarAfectacionTributariaProducto(false);

      /*
       * Categoría y subcategoría
       */
      $("#idcategoria")
        .val(String(data.idcategoria ?? ""))
        .trigger("change");

      $.ajax({
        url: "Controllers/Subcategoria.php?op=selectSubcategoria",
        type: "POST",
        data: {
          categoria_id: data.idcategoria
        },
        dataType: "html",

        success: function (respuesta) {
          const $subcategoria = $("#idsubcategoria");

          $subcategoria.html(respuesta);

          const idsubcategoria =
            String(data.idsubcategoria ?? "").trim();

          const existeSubcategoria = $subcategoria
            .find("option")
            .filter(function () {
              return String($(this).val()) === idsubcategoria;
            })
            .length > 0;

          if (
            idsubcategoria !== "" &&
            idsubcategoria !== "0" &&
            existeSubcategoria
          ) {
            $subcategoria
              .prop("disabled", false)
              .val(idsubcategoria);
          } else {
            $subcategoria
              .prop("disabled", true)
              .val("");
          }
        }
      });

      /*
       * Medida
       */
      $("#idmedida")
        .val(String(data.idmedida ?? ""))
        .trigger("change");

      /*
       * Almacén:
       * primero carga las opciones y después selecciona el valor.
       */
      cargarSelectAlmacen(
        data.idalmacen,
        data.almacen_nombre || data.almacen || ""
      );

      /*
       * Imagen
       */
      const imagen = data.imagen || "default.png";

      $("#imagenactual").val(imagen);

      $("#imagenmuestra")
        .attr(
          "src",
          "storage/images/products/" + imagen
        )
        .show();

      if (data.codigo) {
        generarbarcode();
      }
    },

    error: function (xhr) {
      console.error(
        "Error al cargar producto:",
        xhr.status,
        xhr.responseText
      );

      Swal.fire(
        "Error",
        "No se pudo cargar la información del producto.",
        "error"
      );
    }
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
        <td> <input type="number" name="variaciones[${index}][precio_venta]" class="form-control" placeholder="Precio Venta *" step="0.01" min="0.01" required > </td>
      </tr>
    `;
  });

  $("#variaciones-lista").html(html);
  $("#variaciones-container").show();
}


$("#idcategoria").on("change", function () {
  const categoriaId = $(this).val();

  // Reset inicial
  $("#idsubcategoria")
    .prop("disabled", true)
    .html('<option value="">Seleccione subcategoría</option>');

  if (!categoriaId) return;

  $.post(
    "Controllers/Subcategoria.php?op=selectSubcategoria",
    { categoria_id: categoriaId },
    function (data) {

      // Insertamos el HTML
      $("#idsubcategoria").html(data);

      // 🔍 CONTAMOS OPCIONES REALES
      const totalOpciones = $("#idsubcategoria option").length;

      // 👉 SOLO habilitar si hay MÁS DE 1 opción
      if (totalOpciones > 1) {
        $("#idsubcategoria").prop("disabled", false);
      } else {
        $("#idsubcategoria")
          .prop("disabled", true)
          .html('<option value="">Esta categoría no tiene subcategorías</option>');
      }
    }
  );
});

function resetSubcategoriaUI(msg = "Seleccione subcategoría") {
  $("#idsubcategoria")
    .prop("disabled", true)
    .html(`<option value="">${msg}</option>`);
}



function togglePlantilla(forzar) {
  const $panel = $("#plantillaSection");
  const abrir = typeof forzar === "boolean" ? forzar : !$panel.hasClass("is-open");

  // mostrarform(true) puede haber aplicado display:none inline; lo limpiamos para que la clase controle la vista.
  $panel.removeAttr("style").toggleClass("is-open", abrir).attr("aria-hidden", abrir ? "false" : "true");

  if (!abrir) return;

  asegurarCatalogosMasivos().then(function () {
    if (!$("#cuerpoMasivoProductos tr").length) {
      for (let i = 0; i < 5; i++) agregarFilaMasivaProducto({}, false);
      validarHojaMasivaProducto();
    }

    window.setTimeout(function () {
      if ($panel[0]) $panel[0].scrollIntoView({ behavior: "smooth", block: "start" });
    }, 60);
  });
}

function camposMasivosProducto() {
  return ["nombre", "codigo", "stock", "precio_compra", "precio_venta", "categoria", "subcategoria", "almacen", "medida"];
}

function escaparHtmlMasivoProducto(valor) {
  return String(valor == null ? "" : valor)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function normalizarMasivoProducto(valor) {
  return String(valor == null ? "" : valor)
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, " ");
}

function asegurarCatalogosMasivos() {
  if (catalogosMasivosCargados) return $.Deferred().resolve(catalogosMasivosProducto).promise();
  if (cargandoCatalogosMasivos) return cargandoCatalogosMasivos;

  $("#masivoEstado").html('<span class="spinner-border spinner-border-sm mr-1"></span> Cargando categorías, almacenes y unidades...');

  cargandoCatalogosMasivos = $.ajax({
    url: "Controllers/Product.php?op=datosImportacion",
    type: "GET",
    dataType: "json",
    cache: false
  }).done(function (respuesta) {
    if (!respuesta || respuesta.success !== true || !respuesta.datos) {
      throw new Error((respuesta && respuesta.mensaje) || "No se pudieron cargar los catálogos.");
    }
    catalogosMasivosProducto = {
      categorias: Array.isArray(respuesta.datos.categorias) ? respuesta.datos.categorias : [],
      subcategorias: Array.isArray(respuesta.datos.subcategorias) ? respuesta.datos.subcategorias : [],
      almacenes: Array.isArray(respuesta.datos.almacenes) ? respuesta.datos.almacenes : [],
      medidas: Array.isArray(respuesta.datos.medidas) ? respuesta.datos.medidas : []
    };
    catalogosMasivosCargados = true;
    $("#masivoEstado").html('<i class="fas fa-check-circle text-success mr-1"></i> Catálogos listos. Puedes digitar o pegar desde Excel.');
  }).fail(function (xhr) {
    const mensaje = xhr.responseJSON && xhr.responseJSON.mensaje ? xhr.responseJSON.mensaje : "No se pudieron cargar los catálogos para la importación.";
    $("#masivoEstado").text(mensaje);
    Swal.fire("No se pudo iniciar la carga masiva", mensaje, "error");
  }).always(function () {
    cargandoCatalogosMasivos = null;
  });

  return cargandoCatalogosMasivos;
}

function opcionesCatalogoMasivo(items, tipo, seleccion) {
  const valor = String(seleccion == null ? "" : seleccion);
  let html = '<option value="">Seleccionar...</option>';

  items.forEach(function (item) {
    const id = tipo === "categoria" ? item.idcategoria
      : tipo === "subcategoria" ? item.idsubcategoria
      : tipo === "almacen" ? item.idalmacen
      : item.idmedida;
    let etiqueta = `${id} - ${item.nombre || ""}`;
    if (tipo === "medida" && item.codigo) etiqueta += ` (${item.codigo})`;
    if (tipo === "subcategoria" && item.categoria) etiqueta += ` · ${item.categoria}`;
    html += `<option value="${escaparHtmlMasivoProducto(id)}"${String(id) === valor ? " selected" : ""}>${escaparHtmlMasivoProducto(etiqueta)}</option>`;
  });

  return html;
}

function resolverCatalogoMasivo(valor, items, tipo) {
  const texto = String(valor == null ? "" : valor).trim();
  if (!texto) return "";

  const coincidenciaId = texto.match(/^\s*(\d+)\s*(?:-|$)/);
  if (coincidenciaId) {
    const id = coincidenciaId[1];
    const existe = items.some(function (item) {
      const itemId = tipo === "categoria" ? item.idcategoria : tipo === "subcategoria" ? item.idsubcategoria : tipo === "almacen" ? item.idalmacen : item.idmedida;
      return String(itemId) === String(id);
    });
    if (existe) return String(id);
  }

  const objetivo = normalizarMasivoProducto(texto);
  const encontrado = items.find(function (item) {
    const id = tipo === "categoria" ? item.idcategoria : tipo === "subcategoria" ? item.idsubcategoria : tipo === "almacen" ? item.idalmacen : item.idmedida;
    const candidatos = [String(item.nombre || ""), `${id} - ${item.nombre || ""}`];
    if (tipo === "medida") candidatos.push(String(item.codigo || ""), `${item.nombre || ""} (${item.codigo || ""})`);
    if (tipo === "subcategoria" && item.categoria) candidatos.push(`${item.nombre || ""} · ${item.categoria}`);
    return candidatos.some(function (c) { return normalizarMasivoProducto(c) === objetivo; });
  });

  if (!encontrado) return "";
  return String(tipo === "categoria" ? encontrado.idcategoria : tipo === "subcategoria" ? encontrado.idsubcategoria : tipo === "almacen" ? encontrado.idalmacen : encontrado.idmedida);
}

function agregarFilaMasivaProducto(datos, validar = true) {
  datos = datos || {};
  const idFila = ++secuenciaFilaMasiva;
  const categoria = resolverCatalogoMasivo(datos.categoria ?? datos.idcategoria ?? "", catalogosMasivosProducto.categorias, "categoria");
  const almacen = resolverCatalogoMasivo(datos.almacen ?? datos.idalmacen ?? "", catalogosMasivosProducto.almacenes, "almacen");
  const medida = resolverCatalogoMasivo(datos.medida ?? datos.idmedida ?? "", catalogosMasivosProducto.medidas, "medida");

  const $fila = $(
    `<tr data-row-id="${idFila}">
      <td><div class="tp-sheet-rownum"><span class="tp-row-state"></span><span class="tp-row-number">1</span></div></td>
      <td><input class="tp-sheet-cell" data-field="nombre" maxlength="200" autocomplete="off" placeholder="Producto"></td>
      <td><input class="tp-sheet-cell" data-field="codigo" maxlength="50" autocomplete="off" placeholder="SKU"></td>
      <td><input class="tp-sheet-cell is-number" data-field="stock" inputmode="numeric" autocomplete="off" value="0"></td>
      <td><input class="tp-sheet-cell is-number" data-field="precio_compra" inputmode="decimal" autocomplete="off" value="0.00"></td>
      <td><input class="tp-sheet-cell is-number" data-field="precio_venta" inputmode="decimal" autocomplete="off" value="0.00"></td>
      <td><select class="tp-sheet-select" data-field="categoria">${opcionesCatalogoMasivo(catalogosMasivosProducto.categorias, "categoria", categoria)}</select></td>
      <td><select class="tp-sheet-select" data-field="subcategoria"><option value="">Sin subcategoría</option></select></td>
      <td><select class="tp-sheet-select" data-field="almacen">${opcionesCatalogoMasivo(catalogosMasivosProducto.almacenes, "almacen", almacen)}</select></td>
      <td><select class="tp-sheet-select" data-field="medida">${opcionesCatalogoMasivo(catalogosMasivosProducto.medidas, "medida", medida)}</select></td>
      <td><div class="tp-sheet-row-actions"><button type="button" class="tp-sheet-remove" title="Eliminar fila"><i class="fas fa-times"></i></button></div></td>
    </tr>`
  );

  $("#cuerpoMasivoProductos").append($fila);
  $fila.find('[data-field="nombre"]').val(datos.nombre ?? datos.producto ?? "");
  $fila.find('[data-field="codigo"]').val(datos.codigo ?? datos.sku ?? "");
  $fila.find('[data-field="stock"]').val(datos.stock === undefined || datos.stock === "" ? "0" : datos.stock);
  $fila.find('[data-field="precio_compra"]').val(datos.precio_compra ?? datos.preciocompra ?? "0.00");
  $fila.find('[data-field="precio_venta"]').val(datos.precio_venta ?? datos.precioventa ?? "0.00");

  const subValor = resolverCatalogoMasivo(datos.subcategoria ?? datos.idsubcategoria ?? "", catalogosMasivosProducto.subcategorias, "subcategoria");
  actualizarSubcategoriasFilaMasiva($fila, categoria, subValor);
  renumerarFilasMasivasProducto();
  $("#masivoEmpty").hide();
  if (validar) validarHojaMasivaProducto();
  return $fila;
}

function actualizarSubcategoriasFilaMasiva($fila, idCategoria, idSeleccionado) {
  const categoria = String(idCategoria || "");
  const filtradas = catalogosMasivosProducto.subcategorias.filter(function (item) {
    return !categoria || String(item.idcategoria) === categoria;
  });
  const $select = $fila.find('[data-field="subcategoria"]');
  $select.html('<option value="">Sin subcategoría</option>' + opcionesCatalogoMasivo(filtradas, "subcategoria", idSeleccionado).replace('<option value="">Seleccionar...</option>', ''));
  if (idSeleccionado) $select.val(String(idSeleccionado));
}

function renumerarFilasMasivasProducto() {
  $("#cuerpoMasivoProductos tr").each(function (indice) {
    $(this).find(".tp-row-number").text(indice + 1);
  });
  $("#masivoEmpty").toggle($("#cuerpoMasivoProductos tr").length === 0);
}

function datosFilaMasivaProducto($fila) {
  const datos = { fila_cliente: String($fila.data("row-id") || "") };
  camposMasivosProducto().forEach(function (campo) {
    datos[campo] = String($fila.find(`[data-field="${campo}"]`).val() ?? "").trim();
  });
  return datos;
}

function filaMasivaVaciaProducto(datos) {
  return !String(datos.nombre || "").trim()
    && !String(datos.codigo || "").trim()
    && (!String(datos.stock || "").trim() || Number(datos.stock) === 0)
    && (!String(datos.precio_compra || "").trim() || Number(datos.precio_compra) === 0)
    && (!String(datos.precio_venta || "").trim() || Number(datos.precio_venta) === 0)
    && !String(datos.categoria || "").trim()
    && !String(datos.subcategoria || "").trim()
    && !String(datos.almacen || "").trim()
    && !String(datos.medida || "").trim();
}

function validarHojaMasivaProducto() {
  const filas = [];
  const skus = {};
  let errores = 0;
  let validas = 0;

  $("#cuerpoMasivoProductos tr").each(function () {
    const $fila = $(this);
    const datos = datosFilaMasivaProducto($fila);
    $fila.removeClass("is-valid has-error").removeAttr("title");
    $fila.find("[data-invalid]").removeAttr("data-invalid");

    if (filaMasivaVaciaProducto(datos)) return;

    const mensajes = [];
    function marcar(campo, mensaje) {
      mensajes.push(mensaje);
      $fila.find(`[data-field="${campo}"]`).attr("data-invalid", "1");
    }

    if (!datos.nombre) marcar("nombre", "Falta el nombre");
    if (!datos.codigo) marcar("codigo", "Falta el SKU");
    if (!datos.categoria) marcar("categoria", "Selecciona una categoría");
    if (!datos.almacen) marcar("almacen", "Selecciona un almacén");
    if (!datos.medida) marcar("medida", "Selecciona una unidad");

    if (datos.stock === "" || !/^\d+$/.test(datos.stock) || Number(datos.stock) < 0) marcar("stock", "Stock inválido");
    if (datos.precio_compra === "" || !Number.isFinite(Number(datos.precio_compra)) || Number(datos.precio_compra) < 0) marcar("precio_compra", "Precio de compra inválido");
    if (datos.precio_venta === "" || !Number.isFinite(Number(datos.precio_venta)) || Number(datos.precio_venta) <= 0) marcar("precio_venta", "El precio de venta debe ser mayor a 0");

    if (datos.subcategoria) {
      const sub = catalogosMasivosProducto.subcategorias.find(function (item) { return String(item.idsubcategoria) === datos.subcategoria; });
      if (!sub || String(sub.idcategoria) !== datos.categoria) marcar("subcategoria", "La subcategoría no pertenece a la categoría");
    }

    const skuKey = normalizarMasivoProducto(datos.codigo);
    if (skuKey) {
      if (skus[skuKey]) {
        marcar("codigo", "SKU repetido dentro de la hoja");
        skus[skuKey].find('[data-field="codigo"]').attr("data-invalid", "1").closest("tr").removeClass("is-valid").addClass("has-error");
      } else {
        skus[skuKey] = $fila;
      }
    }

    datos._errores = mensajes;
    filas.push(datos);

    if (mensajes.length) {
      errores++;
      $fila.addClass("has-error").attr("title", mensajes.join(" · "));
    } else {
      validas++;
      $fila.addClass("is-valid");
    }
  });

  // Segunda pasada para reflejar duplicados marcados en una fila anterior.
  $("#cuerpoMasivoProductos tr.has-error").each(function () {
    const id = String($(this).data("row-id") || "");
    const item = filas.find(function (fila) { return fila.fila_cliente === id; });
    if (item && (!item._errores || !item._errores.length)) {
      item._errores = ["SKU repetido dentro de la hoja"];
      validas = Math.max(0, validas - 1);
      errores++;
    }
  });

  $("#masivoTotal").text(filas.length);
  $("#masivoValidas").text(validas);
  $("#masivoErrores").text(errores);
  $("#btnImportarMasivo").prop("disabled", validas === 0).html(`<i class="fas fa-cloud-upload-alt"></i> Importar ${validas} producto${validas === 1 ? "" : "s"} válido${validas === 1 ? "" : "s"}`);

  if (!filas.length) {
    $("#masivoEstado").text("Agrega una fila o pega información desde Excel.");
  } else if (errores) {
    $("#masivoEstado").html(`<strong>${validas}</strong> listas para importar · <span class="text-danger"><strong>${errores}</strong> requieren corrección</span>`);
  } else {
    $("#masivoEstado").html(`<span class="text-success"><i class="fas fa-check-circle mr-1"></i><strong>${validas}</strong> productos listos para importar</span>`);
  }

  return filas;
}

function limpiarHojaMasivaProducto(confirmar) {
  const ejecutar = function () {
    $("#cuerpoMasivoProductos").empty();
    for (let i = 0; i < 5; i++) agregarFilaMasivaProducto({}, false);
    validarHojaMasivaProducto();
  };

  if (!confirmar || !$("#cuerpoMasivoProductos tr.is-valid, #cuerpoMasivoProductos tr.has-error").length) {
    ejecutar();
    return;
  }

  Swal.fire({
    title: "¿Limpiar la hoja?",
    text: "Se eliminarán los datos digitados o pegados que todavía no se han importado.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Sí, limpiar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "#00a46a"
  }).then(function (r) { if (r.isConfirmed) ejecutar(); });
}

function parsearTextoPegadoProducto(texto) {
  const limpio = String(texto || "").replace(/\r\n/g, "\n").replace(/\r/g, "\n").trimEnd();
  if (!limpio) return [];
  return limpio.split("\n").map(function (linea) { return linea.split("\t"); });
}

function pegarMatrizMasivaProducto(matriz, $filaInicio, columnaInicio) {
  if (!Array.isArray(matriz) || !matriz.length) return;
  const campos = camposMasivosProducto();
  let $fila = $filaInicio && $filaInicio.length ? $filaInicio : $("#cuerpoMasivoProductos tr").last();
  if (!$fila.length) $fila = agregarFilaMasivaProducto({}, false);

  matriz.forEach(function (columnas, indiceFila) {
    if (indiceFila > 0) {
      let $siguiente = $fila.next("tr");
      if (!$siguiente.length) $siguiente = agregarFilaMasivaProducto({}, false);
      $fila = $siguiente;
    }

    columnas.forEach(function (valor, offset) {
      const campo = campos[columnaInicio + offset];
      if (!campo) return;
      const $control = $fila.find(`[data-field="${campo}"]`);
      if (!$control.length) return;

      if (["categoria", "subcategoria", "almacen", "medida"].includes(campo)) {
        const items = campo === "categoria" ? catalogosMasivosProducto.categorias : campo === "subcategoria" ? catalogosMasivosProducto.subcategorias : campo === "almacen" ? catalogosMasivosProducto.almacenes : catalogosMasivosProducto.medidas;
        const id = resolverCatalogoMasivo(valor, items, campo);
        if (campo === "categoria") {
          $control.val(id);
          actualizarSubcategoriasFilaMasiva($fila, id, "");
        } else if (campo === "subcategoria") {
          actualizarSubcategoriasFilaMasiva($fila, String($fila.find('[data-field="categoria"]').val() || ""), id);
        } else {
          $control.val(id);
        }
      } else {
        $control.val(String(valor == null ? "" : valor).trim());
      }
    });
  });

  renumerarFilasMasivasProducto();
  validarHojaMasivaProducto();
}

function previsualizarArchivoMasivoProducto(archivo) {
  if (!archivo) return;
  asegurarCatalogosMasivos().then(function () {
    const formData = new FormData();
    formData.append("archivo_productos", archivo);

    $.ajax({
      url: "Controllers/Product.php?op=previsualizarMasivo",
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      dataType: "json",
      beforeSend: function () { $("#masivoEstado").html('<span class="spinner-border spinner-border-sm mr-1"></span> Leyendo archivo...'); }
    }).done(function (respuesta) {
      if (!respuesta || respuesta.success !== true) {
        Swal.fire("Archivo no válido", (respuesta && respuesta.mensaje) || "No se pudo leer el archivo.", "error");
        return;
      }

      const filas = Array.isArray(respuesta.filas) ? respuesta.filas : [];
      $("#cuerpoMasivoProductos").empty();
      filas.forEach(function (fila) { agregarFilaMasivaProducto(fila, false); });
      if (!filas.length) for (let i = 0; i < 5; i++) agregarFilaMasivaProducto({}, false);
      validarHojaMasivaProducto();
      Swal.fire({ icon: "success", title: "Archivo cargado", text: `${filas.length} fila${filas.length === 1 ? "" : "s"} preparada${filas.length === 1 ? "" : "s"} para revisión.`, timer: 1500, showConfirmButton: false });
    }).fail(function (xhr) {
      const mensaje = xhr.responseJSON && xhr.responseJSON.mensaje ? xhr.responseJSON.mensaje : "No se pudo procesar el archivo.";
      Swal.fire("Error", mensaje, "error");
    });
  });
}

function importarFilasMasivasProducto() {
  const filas = validarHojaMasivaProducto().filter(function (fila) { return !fila._errores || fila._errores.length === 0; });
  if (!filas.length) {
    Swal.fire("Sin filas válidas", "Corrige los campos marcados antes de importar.", "warning");
    return;
  }

  Swal.fire({
    title: `Importar ${filas.length} producto${filas.length === 1 ? "" : "s"}`,
    text: "Se registrarán los productos válidos y su stock inicial en el sistema.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Importar ahora",
    cancelButtonText: "Revisar",
    confirmButtonColor: "#00a46a"
  }).then(function (confirmacion) {
    if (!confirmacion.isConfirmed) return;

    $.ajax({
      url: "Controllers/Product.php?op=importarMasivoJson",
      type: "POST",
      dataType: "json",
      data: { filas: JSON.stringify(filas) },
      beforeSend: function () {
        $("#btnImportarMasivo").prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span> Importando...');
      }
    }).done(function (respuesta) {
      const exitos = Array.isArray(respuesta.exitosos) ? respuesta.exitosos : [];
      const erroresServidor = Array.isArray(respuesta.errores) ? respuesta.errores : [];
      const idsExitosos = Array.isArray(respuesta.filas_exitosas) ? respuesta.filas_exitosas.map(String) : [];

      idsExitosos.forEach(function (id) {
        $(`#cuerpoMasivoProductos tr[data-row-id="${id}"]`).remove();
      });

      renumerarFilasMasivasProducto();
      validarHojaMasivaProducto();
      if (tabla) tabla.ajax.reload(null, false);

      let html = `<div style="text-align:left"><div style="margin-bottom:8px"><strong style="color:#008d5b">${exitos.length}</strong> producto${exitos.length === 1 ? "" : "s"} registrado${exitos.length === 1 ? "" : "s"}.</div>`;
      if (erroresServidor.length) {
        html += `<div style="color:#b4232f;margin-bottom:5px"><strong>${erroresServidor.length} no se pudieron importar:</strong></div><ul style="max-height:180px;overflow:auto;padding-left:18px;font-size:12px">${erroresServidor.map(function (e) { return `<li>${escaparHtmlMasivoProducto(e)}</li>`; }).join("")}</ul>`;
      }
      html += "</div>";

      Swal.fire({
        title: erroresServidor.length ? "Importación completada con observaciones" : "Importación completada",
        html: html,
        icon: erroresServidor.length ? "warning" : "success",
        confirmButtonColor: "#00a46a",
        width: 650
      });
    }).fail(function (xhr) {
      const mensaje = xhr.responseJSON && xhr.responseJSON.mensaje ? xhr.responseJSON.mensaje : "No se pudo completar la importación.";
      Swal.fire("Error", mensaje, "error");
    }).always(function () {
      validarHojaMasivaProducto();
    });
  });
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

  $("#atributos_section").toggle(activo);

  $("#grupo_sku_principal").toggle(!activo);
  $("#grupo_stock_principal").toggle(!activo);
  $("#grupo_precio_compra_principal").toggle(!activo);
  $("#grupo_precio_venta_principal").toggle(!activo);

  /*
   * Producto normal:
   * el precio de venta principal es obligatorio.
   *
   * Producto con atributos:
   * el precio principal se oculta y son obligatorios
   * los precios de venta de cada variación.
   */
  $("#precio_venta")
    .prop("required", !activo)
    .prop("disabled", activo);

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


