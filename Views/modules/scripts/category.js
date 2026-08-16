var tabla;

function init() {
  mostrarform(false);
  listar();
  cargarEstadisticas();

  $("#formulario").on("submit", function (e) {
    guardaryeditar(e);
  });

  $("#formEditarCategoria").on("submit", function (e) {
    guardarEdicionCategoria(e);
  });

  $("#categorySearch").on("input", function () {
    if (tabla) {
      tabla.search(this.value).draw();
    }
  });

  $("#btnExportExcel").on("click", function () {
    if (tabla) tabla.button(0).trigger();
  });

  $("#btnExportPdf").on("click", function () {
    if (tabla) tabla.button(1).trigger();
  });

  $("#sub_nombre").on("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      guardarSubcategoria();
    }
  });

  $("#modalSubcategorias").on("shown.bs.modal", function () {
    setTimeout(function () {
      $("#sub_nombre").trigger("focus");
    }, 100);
  });

  $("#modalEditarCategoria").on("shown.bs.modal", function () {
    setTimeout(function () {
      const input = document.getElementById("edit_nombre");
      if (input) {
        input.focus();
        input.select();
      }
    }, 100);
  });
}

function limpiar() {
  $("#idcategoria").val("");
  $("#nombre").val("");
  $("#descripcion").val("");
}

function setFormMode(isEdit) {
  $("#categoryFormTitle").text(isEdit ? "Editar categoría" : "Nueva categoría");
  $("#categoryFormSubtitle").text(
    isEdit
      ? "Actualiza la información de esta categoría."
      : "Completa los datos para crear una categoría."
  );
  $("#btnGuardar").html(
    isEdit
      ? '<i class="fas fa-save tw-text-xs"></i> Guardar cambios'
      : '<i class="fas fa-save tw-text-xs"></i> Guardar categoría'
  );
}

function verSubcategorias(idcategoria, nombre) {
  $("#categoriaNombre").text(nombre);
  $("#sub_idcategoria").val(idcategoria);
  $("#sub_nombre").val("");
  $("#tablaSubcategorias").html(`
    <tr>
      <td colspan="3" class="tw-py-8 tw-text-center tw-text-slate-400">
        <i class="fas fa-circle-notch fa-spin tw-mr-2"></i> Cargando subcategorías...
      </td>
    </tr>
  `);
  $("#modalSubcategorias").modal("show");
  listarSubcategorias(idcategoria);
}

function listarSubcategorias(idcategoria) {
  $.get(
    "Controllers/Subcategoria.php?op=listar&idcategoria=" + encodeURIComponent(idcategoria),
    function (data) {
      let res;
      try {
        res = typeof data === "string" ? JSON.parse(data) : data;
      } catch (e) {
        $("#tablaSubcategorias").html(`
          <tr>
            <td colspan="3" class="tw-py-8 tw-text-center tw-text-rose-500">
              No se pudieron cargar las subcategorías.
            </td>
          </tr>
        `);
        return;
      }

      if (!Array.isArray(res) || res.length === 0) {
        $("#tablaSubcategorias").html(`
          <tr>
            <td colspan="3" class="tw-py-9 tw-text-center">
              <div class="tw-w-11 tw-h-11 tw-mx-auto tw-mb-2.5 tw-rounded-2xl tw-bg-slate-100 tw-text-slate-400 tw-flex tw-items-center tw-justify-center">
                <i class="fas fa-sitemap"></i>
              </div>
              <div class="tw-text-[.86rem] tw-font-medium tw-text-slate-600">Sin subcategorías todavía</div>
              <div class="tw-mt-1 tw-text-[.76rem] tw-text-slate-400">Agrega la primera usando el campo superior.</div>
            </td>
          </tr>
        `);
        return;
      }

      let html = "";
      res.forEach(function (r) {
        const activo = Number(r.estado) === 1;
        html += `
          <tr>
            <td>
              <div class="tw-flex tw-items-center tw-gap-2.5">
                <span class="tw-w-8 tw-h-8 tw-rounded-lg tw-bg-slate-100 tw-text-slate-500 tw-flex tw-items-center tw-justify-center tw-shrink-0">
                  <i class="fas fa-tag tw-text-[.68rem]"></i>
                </span>
                <span class="tw-font-medium tw-text-slate-700">${escapeHtml(r.nombre)}</span>
              </div>
            </td>
            <td>
              <span class="category-status ${activo ? "category-status--active" : "category-status--inactive"}">
                ${activo ? "Activa" : "Inactiva"}
              </span>
            </td>
            <td class="tw-text-center">
              ${
                activo
                  ? `<button type="button" class="category-sub-action is-danger" title="Desactivar" aria-label="Desactivar subcategoría" onclick="desactivarSub(${Number(r.idsubcategoria)})"><i class="fas fa-times tw-text-xs"></i></button>`
                  : `<button type="button" class="category-sub-action is-success" title="Activar" aria-label="Activar subcategoría" onclick="activarSub(${Number(r.idsubcategoria)})"><i class="fas fa-check tw-text-xs"></i></button>`
              }
            </td>
          </tr>
        `;
      });

      $("#tablaSubcategorias").html(html);
    }
  ).fail(function () {
    $("#tablaSubcategorias").html(`
      <tr>
        <td colspan="3" class="tw-py-8 tw-text-center tw-text-rose-500">No se pudieron cargar las subcategorías.</td>
      </tr>
    `);
  });
}

function guardarSubcategoria() {
  const idcategoria = $("#sub_idcategoria").val();
  const nombre = $("#sub_nombre").val().trim();

  if (nombre === "") {
    $("#sub_nombre").trigger("focus");
    swal({
      title: "Falta el nombre",
      text: "Escribe un nombre para la subcategoría.",
      icon: "warning",
      button: "Entendido",
    });
    return;
  }

  const $input = $("#sub_nombre");
  const $button = $input.next("button");
  $input.prop("disabled", true);
  $button.prop("disabled", true).addClass("tw-opacity-60");

  $.post(
    "Controllers/Subcategoria.php?op=guardar",
    { idcategoria: idcategoria, nombre: nombre },
    function () {
      $input.val("");
      listarSubcategorias(idcategoria);
    }
  )
    .fail(function () {
      swal("No se pudo guardar", "Intenta nuevamente.", "error");
    })
    .always(function () {
      $input.prop("disabled", false);
      $button.prop("disabled", false).removeClass("tw-opacity-60");
      $input.trigger("focus");
    });
}

function activarSub(id) {
  cambiarEstadoSubcategoria(id, "activar");
}

function desactivarSub(id) {
  cambiarEstadoSubcategoria(id, "desactivar");
}

function cambiarEstadoSubcategoria(id, accion) {
  $.post(
    "Controllers/Subcategoria.php?op=" + accion,
    { idsubcategoria: id },
    function () {
      listarSubcategorias($("#sub_idcategoria").val());
    }
  );
}

function mostrarform(flag) {
  limpiar();
  setFormMode(false);

  if (flag) {
    $("#listadoregistros").hide();
    $("#formularioregistros").fadeIn(160);
    $("#btnGuardar").prop("disabled", false);
    $("#btnagregar").hide();
    setTimeout(function () {
      $("#nombre").trigger("focus");
    }, 100);
  } else {
    $("#formularioregistros").hide();
    $("#listadoregistros").fadeIn(140);
    $("#btnagregar").show();
  }
}

function cancelarform() {
  limpiar();
  mostrarform(false);
}

function listar() {
  if ($.fn.DataTable && $.fn.DataTable.isDataTable("#tbllistado")) {
    $("#tbllistado").DataTable().clear().destroy();
  }

  tabla = $("#tbllistado").DataTable({
    processing: true,
    serverSide: false,
    dom: "Brtip",
    buttons: [
      {
        extend: "excelHtml5",
        text: "Excel",
        titleAttr: "Exportar a Excel",
        title: "Reporte de Categorías",
        sheetName: "Categorías",
        exportOptions: { columns: [0, 2] },
      },
      {
        extend: "pdfHtml5",
        text: "PDF",
        titleAttr: "Exportar a PDF",
        title: "Reporte de Categorías",
        pageSize: "A4",
        exportOptions: { columns: [0, 2] },
      },
    ],
    ajax: {
      url: "Controllers/Category.php?op=listar",
      type: "GET",
      dataType: "json",
      dataSrc: function (json) {
        cargarEstadisticas();
        return json && json.aaData ? json.aaData : [];
      },
      error: function (e) {
        console.log(e.responseText);
      },
    },
    destroy: true,
    pageLength: 8,
    lengthChange: false,
    searching: true,
    autoWidth: false,
    order: [[0, "asc"]],
    language: {
      processing: "Cargando...",
      zeroRecords: "No se encontraron categorías",
      emptyTable: "Todavía no hay categorías registradas",
      info: "Mostrando _START_ a _END_ de _TOTAL_ categorías",
      infoEmpty: "Sin categorías para mostrar",
      infoFiltered: "(filtrado de _MAX_)",
      paginate: {
        first: "Primero",
        last: "Último",
        next: "›",
        previous: "‹",
      },
    },
    columnDefs: [
      { targets: [1, 2, 3], orderable: false },
      { targets: 3, className: "text-right" },
    ],
  });
}

function cargarEstadisticas() {
  $.ajax({
    url: "Controllers/Category.php?op=estadisticas",
    type: "GET",
    dataType: "json",
    cache: false,
    success: function (stats) {
      $("#categoryStatTotal").text(Number(stats.total || 0));
      $("#categoryStatActive").text(Number(stats.activas || 0));
      $("#categoryStatInactive").text(Number(stats.inactivas || 0));
    },
    error: function () {
      $("#categoryStatTotal, #categoryStatActive, #categoryStatInactive").text("—");
    },
  });
}

function guardaryeditar(e) {
  e.preventDefault();

  const nombre = $("#nombre").val().trim();
  if (!nombre) {
    $("#nombre").trigger("focus");
    return;
  }

  const $btn = $("#btnGuardar");
  $btn.prop("disabled", true);
  const formData = new FormData($("#formulario")[0]);

  $.ajax({
    url: "Controllers/Category.php?op=guardaryeditar",
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,
    success: function (datos) {
      swal({
        title: "Categoría creada",
        text: datos,
        icon: "success",
        button: "Aceptar",
      });
      mostrarform(false);
      if (tabla) tabla.ajax.reload(function () { cargarEstadisticas(); }, false);
    },
    error: function () {
      swal("No se pudo guardar", "Revisa los datos e intenta nuevamente.", "error");
    },
    complete: function () {
      $btn.prop("disabled", false);
    },
  });
}

function editarCategoria(idcategoria) {
  $("#btnGuardarEdicion").prop("disabled", true);

  $.ajax({
    url: "Controllers/Category.php?op=mostrar",
    type: "POST",
    data: { idcategoria: idcategoria },
    dataType: "json",
    success: function (data) {
      if (!data || !data.idcategoria) {
        swal("Error", "No se pudo cargar la categoría.", "error");
        return;
      }

      $("#edit_idcategoria").val(data.idcategoria);
      $("#edit_nombre").val(data.nombre || "");
      $("#modalEditarCategoria").modal("show");
    },
    error: function () {
      swal("Error", "No se pudo cargar la categoría.", "error");
    },
    complete: function () {
      $("#btnGuardarEdicion").prop("disabled", false);
    },
  });
}

function guardarEdicionCategoria(e) {
  e.preventDefault();

  const idcategoria = $("#edit_idcategoria").val();
  const nombre = $("#edit_nombre").val().trim();

  if (!idcategoria || !nombre) {
    $("#edit_nombre").trigger("focus");
    return;
  }

  const $btn = $("#btnGuardarEdicion");
  $btn.prop("disabled", true);

  $.ajax({
    url: "Controllers/Category.php?op=editarNombre",
    type: "POST",
    dataType: "json",
    data: {
      idcategoria: idcategoria,
      nombre: nombre,
    },
    success: function (respuesta) {
      if (!respuesta || respuesta.ok !== true) {
        swal("No se pudo actualizar", respuesta && respuesta.mensaje ? respuesta.mensaje : "Intenta nuevamente.", "error");
        return;
      }

      $("#modalEditarCategoria").modal("hide");
      swal("Categoría actualizada", respuesta.mensaje, "success");
      if (tabla) {
        tabla.ajax.reload(function () {
          cargarEstadisticas();
        }, false);
      } else {
        cargarEstadisticas();
      }
    },
    error: function () {
      swal("No se pudo actualizar", "Intenta nuevamente.", "error");
    },
    complete: function () {
      $btn.prop("disabled", false);
    },
  });
}

function desactivar(idcategoria) {
  swal({
    title: "Desactivar categoría",
    text: "La categoría dejará de estar disponible para nuevos registros.",
    icon: "warning",
    buttons: {
      cancel: "Cancelar",
      confirm: "Sí, desactivar",
    },
    dangerMode: true,
  }).then(function (confirmado) {
    if (!confirmado) return;

    $.post(
      "Controllers/Category.php?op=desactivar",
      { idcategoria: idcategoria },
      function (mensaje) {
        swal("Categoría desactivada", mensaje, "success");
        if (tabla) tabla.ajax.reload(function () { cargarEstadisticas(); }, false);
      }
    );
  });
}

function activar(idcategoria) {
  swal({
    title: "Activar categoría",
    text: "La categoría volverá a estar disponible.",
    icon: "warning",
    buttons: {
      cancel: "Cancelar",
      confirm: "Sí, activar",
    },
  }).then(function (confirmado) {
    if (!confirmado) return;

    $.post(
      "Controllers/Category.php?op=activar",
      { idcategoria: idcategoria },
      function (mensaje) {
        swal("Categoría activada", mensaje, "success");
        if (tabla) tabla.ajax.reload(function () { cargarEstadisticas(); }, false);
      }
    );
  });
}

function escapeHtml(value) {
  return String(value == null ? "" : value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

init();
