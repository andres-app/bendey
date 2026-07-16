var tabla;
var catalogosUsuarios = {
  sucursales: [],
  cajas: [],
  almacenes: []
};


var avatarUsuarioPredeterminado =
  "data:image/svg+xml;charset=UTF-8," +
  encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="180" viewBox="0 0 180 180">' +
      '<rect width="180" height="180" rx="28" fill="#f1f3f8"/>' +
      '<circle cx="90" cy="67" r="31" fill="#aeb7c8"/>' +
      '<path d="M35 157c5-34 27-52 55-52s50 18 55 52" fill="#aeb7c8"/>' +
    '</svg>'
  );

function colocarAvatarSeguro(ruta) {
  var $imagen = $("#imagenmuestra");

  $imagen
    .off("error.usuario")
    .on("error.usuario", function () {
      $(this).off("error.usuario").attr("src", avatarUsuarioPredeterminado);
    })
    .attr("src", ruta || avatarUsuarioPredeterminado)
    .show();
}

function init() {
  mostrarform(false);
  mostrarform_clave(false);
  enlazarEventos();

  cargarCatalogos()
    .always(function () {
      listar();
      cargarPermisos(0);
    });
}

function enlazarEventos() {
  $("#formulario").on("submit", guardaryeditar);
  $("#formularioc").on("submit", editar_clave);

  $("#idsucursal").on("change", function () {
    var idsucursal = parseInt($(this).val() || 0, 10);
    filtrarCajas(idsucursal, []);
    filtrarAlmacenes(idsucursal, 0);
    actualizarResumenAsignacion();
  });

  $("#idcaja, #idalmacen").on("change", actualizarResumenAsignacion);

  $("#rol").on("change", function () {
    aplicarPerfilRol($(this).val());
    actualizarResumenAsignacion();
  });

  $("#imagen").on("change", previsualizarImagen);
}

function respuestaJson(datos) {
  if (typeof datos === "object" && datos !== null) {
    return datos;
  }

  try {
    return JSON.parse(datos);
  } catch (error) {
    return {
      ok: false,
      mensaje: String(datos || "Respuesta no válida del servidor.")
    };
  }
}

function mostrarAlerta(titulo, mensaje, icono) {
  swal({
    title: titulo,
    text: mensaje,
    icon: icono || "info",
    button: "Aceptar"
  });
}

function cargarCatalogos() {
  return $.ajax({
    url: "Controllers/User.php?op=catalogos",
    type: "GET",
    dataType: "json"
  })
    .done(function (respuesta) {
      if (!respuesta.ok) {
        mostrarAlerta("Configuración", respuesta.mensaje, "warning");
        return;
      }

      catalogosUsuarios.sucursales = respuesta.sucursales || [];
      catalogosUsuarios.cajas = respuesta.cajas || [];
      catalogosUsuarios.almacenes = respuesta.almacenes || [];

      llenarSucursales();

      var sucursalInicial = parseInt($("#idsucursal").val() || 0, 10);
      filtrarCajas(sucursalInicial, []);
      filtrarAlmacenes(sucursalInicial, 0);
    })
    .fail(function (xhr) {
      console.error(xhr.responseText);
      mostrarAlerta(
        "No se pudieron cargar las asignaciones",
        "Revisa que las tablas sucursal, caja y almacén estén disponibles.",
        "error"
      );
    });
}

function llenarSucursales(valorSeleccionado) {
  var $select = $("#idsucursal");
  $select.empty();
  $select.append('<option value="">Selecciona una sucursal</option>');

  $.each(catalogosUsuarios.sucursales, function (_, sucursal) {
    var texto = sucursal.nombre || ("Sucursal #" + sucursal.idsucursal);

    if (parseInt(sucursal.principal || 0, 10) === 1) {
      texto += " · Principal";
    }

    $select.append(
      $("<option>", {
        value: sucursal.idsucursal,
        text: texto
      })
    );
  });

  if (valorSeleccionado) {
    $select.val(String(valorSeleccionado));
  }
}

function filtrarCajas(idsucursal, valoresSeleccionados) {
  var $select = $("#idcaja");
  var seleccionados = Array.isArray(valoresSeleccionados)
    ? valoresSeleccionados.map(String)
    : [];

  $select.empty();

  $.each(catalogosUsuarios.cajas, function (_, caja) {
    var sucursalCaja = parseInt(caja.idsucursal || 0, 10);

    if (
      idsucursal > 0 &&
      sucursalCaja > 0 &&
      sucursalCaja !== idsucursal
    ) {
      return;
    }

    $select.append(
      $("<option>", {
        value: caja.idcaja,
        text: caja.etiqueta || ("Caja #" + caja.idcaja)
      })
    );
  });

  $.each(seleccionados, function (_, idcaja) {
    if ($select.find('option[value="' + idcaja + '"]').length === 0) {
      $select.append(
        $("<option>", {
          value: idcaja,
          text: "Caja #" + idcaja
        })
      );
    }
  });

  $select.val(seleccionados);

  if ($select.find("option").length === 0) {
    $select.append(
      $("<option>", {
        value: "",
        text: "No hay cajas registradas"
      }).prop("disabled", true)
    );
  }

  if ($.fn.select2) {
    if ($select.hasClass("select2-hidden-accessible")) {
      $select.trigger("change.select2");
    } else {
      $select.select2({
        width: "100%",
        placeholder: "Selecciona una o más cajas",
        closeOnSelect: false
      });
    }
  }
}

function filtrarAlmacenes(idsucursal, valorSeleccionado) {
  var $select = $("#idalmacen");
  $select.empty();
  $select.append('<option value="">Sin almacén asignado</option>');

  $.each(catalogosUsuarios.almacenes, function (_, almacen) {
    var sucursalAlmacen = parseInt(almacen.idsucursal || 0, 10);

    if (
      idsucursal > 0 &&
      sucursalAlmacen > 0 &&
      sucursalAlmacen !== idsucursal
    ) {
      return;
    }

    $select.append(
      $("<option>", {
        value: almacen.idalmacen,
        text: almacen.etiqueta || ("Almacén #" + almacen.idalmacen)
      })
    );
  });

  if (valorSeleccionado) {
    $select.val(String(valorSeleccionado));

    if ($select.val() === null) {
      $select.append(
        $("<option>", {
          value: valorSeleccionado,
          text: "Almacén #" + valorSeleccionado
        })
      ).val(String(valorSeleccionado));
    }
  }
}

function aplicarPerfilRol(rol) {
  rol = String(rol || "VENDEDOR").toUpperCase();

  var perfil = {
    puede_vender: 1,
    puede_cobrar: 0,
    puede_abrir_caja_sucursal: 0,
    puede_cerrar_caja_sucursal: 0,
    puede_abrir: 0,
    puede_cerrar: 0,
    puede_operar: 1
  };

  if (rol === "ADMINISTRADOR") {
    $.each(perfil, function (campo) {
      perfil[campo] = 1;
    });
  } else if (rol === "CAJERO") {
    perfil.puede_cobrar = 1;
    perfil.puede_abrir_caja_sucursal = 1;
    perfil.puede_cerrar_caja_sucursal = 1;
    perfil.puede_abrir = 1;
    perfil.puede_cerrar = 1;
  }

  $.each(perfil, function (campo, valor) {
    $("#" + campo).prop("checked", valor === 1);
  });
}

function actualizarResumenAsignacion() {
  var sucursal = $("#idsucursal option:selected").text() || "Sin sucursal";
  var cajas = $("#idcaja option:selected")
    .map(function () {
      return $(this).text();
    })
    .get();

  var caja = cajas.length > 0 ? cajas.join(", ") : "Sin caja";
  var almacen = $("#idalmacen option:selected").text() || "Sin almacén";
  var rol = $("#rol").val() || "Sin rol";

  $("#resumenRol").text(rol);
  $("#resumenSucursal").text(sucursal);
  $("#resumenCaja").text(caja);
  $("#resumenAlmacen").text(almacen);
}

function cargarPermisos(idusuario) {
  return $.post(
    "Controllers/User.php?op=permisos&id=" + parseInt(idusuario || 0, 10),
    function (html) {
      $("#permisos").html(html);
    }
  );
}

function limpiar() {
  $("#formulario")[0].reset();
  $("#idusuario").val("");
  $("#imagenactual").val("");
  colocarAvatarSeguro(avatarUsuarioPredeterminado);

  $("#tipo_documento").val("DNI");
  $("#rol").val("VENDEDOR");

  llenarSucursales();

  var sucursalPrincipal = 0;

  $.each(catalogosUsuarios.sucursales, function (_, sucursal) {
    if (parseInt(sucursal.principal || 0, 10) === 1) {
      sucursalPrincipal = parseInt(sucursal.idsucursal, 10);
      return false;
    }
  });

  if (sucursalPrincipal > 0) {
    $("#idsucursal").val(String(sucursalPrincipal));
  }

  filtrarCajas(sucursalPrincipal, []);
  filtrarAlmacenes(sucursalPrincipal, 0);
  aplicarPerfilRol("VENDEDOR");
  cargarPermisos(0);
  actualizarResumenAsignacion();

  $("#claves").show();
  $("#clave").prop("required", true);
  $("#btnGuardar").prop("disabled", false);
}

function mostrarform(flag) {
  if (flag) {
    $("#listadoregistros").hide();
    $("#formulario_clave").hide();
    $("#formularioregistros").show();
    $("#btnagregar").hide();
    $("#tituloFormulario").text(
      $("#idusuario").val() ? "Editar usuario" : "Nuevo usuario"
    );
  } else {
    $("#formularioregistros").hide();
    $("#formulario_clave").hide();
    $("#listadoregistros").show();
    $("#btnagregar").show();
  }
}

function nuevoUsuario() {
  limpiar();
  mostrarform(true);
  $("#tituloFormulario").text("Nuevo usuario");
}

function mostrarform_clave(flag) {
  if (flag) {
    $("#listadoregistros").hide();
    $("#formularioregistros").hide();
    $("#formulario_clave").show();
    $("#btnagregar").hide();
    $("#btnGuardar_clave").prop("disabled", false);
  } else {
    $("#formulario_clave").hide();
    $("#listadoregistros").show();
    $("#btnagregar").show();
  }
}

function cancelarform() {
  limpiar();
  mostrarform(false);
}

function cancelarform_clave() {
  $("#formularioc")[0].reset();
  mostrarform_clave(false);
}

function listar() {
  if ($.fn.DataTable.isDataTable("#tbllistado")) {
    $("#tbllistado").DataTable().clear().destroy();
    $("#tbllistado tbody").empty();
  }

  tabla = $("#tbllistado")
    .DataTable({
      language: {
        search: "Buscar:",
        zeroRecords: "No se encontraron usuarios",
        info: "Mostrando _START_ a _END_ de _TOTAL_ usuarios",
        infoEmpty: "No hay usuarios registrados",
        lengthMenu: "Mostrar _MENU_ usuarios",
        loadingRecords: "Cargando...",
        processing: "Procesando...",
        paginate: {
          previous: "Anterior",
          next: "Siguiente"
        }
      },
      processing: true,
      serverSide: false,
      responsive: false,
      autoWidth: false,
      deferRender: true,
      dom: "Bfrtip",
      buttons: [
        {
          extend: "excelHtml5",
          text: '<i class="fas fa-file-excel mr-1"></i> Excel',
          className: "btn btn-outline-success btn-sm",
          title: "Reporte de Usuarios",
          sheetName: "Usuarios",
          exportOptions: {
            columns: [1, 2, 3, 4, 5, 6, 7, 8]
          }
        },
        {
          extend: "pdfHtml5",
          text: '<i class="fas fa-file-pdf mr-1"></i> PDF',
          className: "btn btn-outline-danger btn-sm",
          title: "Reporte de Usuarios",
          pageSize: "A4",
          orientation: "landscape",
          download: "open",
          exportOptions: {
            columns: [1, 2, 3, 4, 5, 6, 7, 8]
          }
        }
      ],
      ajax: {
        url: "Controllers/User.php?op=listar",
        type: "GET",
        dataType: "json",
        error: function (xhr) {
          console.error(xhr.responseText);
          mostrarAlerta(
            "No se pudo listar",
            "Revisa la respuesta del controlador en la consola.",
            "error"
          );
        }
      },
      columnDefs: [
        { targets: [0], orderable: false, searchable: false, width: "105px" },
        { targets: [1], width: "190px" },
        { targets: [2], width: "115px" },
        { targets: [3], width: "190px" },
        { targets: [4], width: "125px", className: "text-center" },
        { targets: [5], width: "170px" },
        { targets: [6], width: "170px" },
        { targets: [7], width: "150px" },
        { targets: [8], width: "85px", className: "text-center" }
      ],
      destroy: true,
      pageLength: 10,
      order: [[1, "asc"]]
    });
}

function guardaryeditar(evento) {
  evento.preventDefault();

  var $boton = $("#btnGuardar");
  $boton.prop("disabled", true);

  var formData = new FormData($("#formulario")[0]);

  $.ajax({
    url: "Controllers/User.php?op=guardaryeditar",
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,
    dataType: "json"
  })
    .done(function (respuesta) {
      if (!respuesta.ok) {
        mostrarAlerta("No se pudo guardar", respuesta.mensaje, "error");
        return;
      }

      mostrarAlerta("Usuario guardado", respuesta.mensaje, "success");
      mostrarform(false);
      tabla.ajax.reload(null, false);
      limpiar();
    })
    .fail(function (xhr) {
      console.error(xhr.responseText);
      mostrarAlerta(
        "Error del servidor",
        "No se pudo procesar el registro. Revisa la consola.",
        "error"
      );
    })
    .always(function () {
      $boton.prop("disabled", false);
    });
}

function editar_clave(evento) {
  evento.preventDefault();

  var $boton = $("#btnGuardar_clave");
  $boton.prop("disabled", true);

  $.ajax({
    url: "Controllers/User.php?op=editar_clave",
    type: "POST",
    data: new FormData($("#formularioc")[0]),
    contentType: false,
    processData: false,
    dataType: "json"
  })
    .done(function (respuesta) {
      if (!respuesta.ok) {
        mostrarAlerta("No se pudo actualizar", respuesta.mensaje, "error");
        return;
      }

      mostrarAlerta("Contraseña actualizada", respuesta.mensaje, "success");
      cancelarform_clave();
    })
    .fail(function (xhr) {
      console.error(xhr.responseText);
      mostrarAlerta("Error", "No se pudo actualizar la contraseña.", "error");
    })
    .always(function () {
      $boton.prop("disabled", false);
    });
}

function mostrar(idusuario) {
  $.ajax({
    url: "Controllers/User.php?op=mostrar",
    type: "POST",
    data: { idusuario: idusuario },
    dataType: "json"
  })
    .done(function (data) {
      if (!data || !data.idusuario) {
        mostrarAlerta("Usuario", "No se encontró el registro solicitado.", "warning");
        return;
      }

      limpiar();

      $("#idusuario").val(data.idusuario);
      $("#nombre").val(data.nombre);
      $("#tipo_documento").val(String(data.tipo_documento || "").trim());
      $("#num_documento").val(data.num_documento);
      $("#direccion").val(data.direccion);
      $("#telefono").val(data.telefono);
      $("#email").val(data.email);
      $("#cargo").val(data.cargo);
      $("#login").val(data.login);
      $("#rol").val(data.rol || "VENDEDOR");

      $("#idsucursal").val(String(data.idsucursal || ""));

      var cajasAsignadas = Array.isArray(data.idcajas)
        ? data.idcajas.map(String)
        : [];

      filtrarCajas(
        parseInt(data.idsucursal || 0, 10),
        cajasAsignadas
      );

      filtrarAlmacenes(
        parseInt(data.idsucursal || 0, 10),
        parseInt(data.idalmacen || 0, 10)
      );

      $("#puede_vender").prop("checked", parseInt(data.puede_vender || 0, 10) === 1);
      $("#puede_cobrar").prop("checked", parseInt(data.puede_cobrar || 0, 10) === 1);
      $("#puede_abrir_caja_sucursal").prop(
        "checked",
        parseInt(data.puede_abrir_caja_sucursal || 0, 10) === 1
      );
      $("#puede_cerrar_caja_sucursal").prop(
        "checked",
        parseInt(data.puede_cerrar_caja_sucursal || 0, 10) === 1
      );
      $("#puede_abrir").prop("checked", parseInt(data.puede_abrir || 0, 10) === 1);
      $("#puede_cerrar").prop("checked", parseInt(data.puede_cerrar || 0, 10) === 1);
      $("#puede_operar").prop("checked", parseInt(data.puede_operar || 0, 10) === 1);

      var imagen = data.imagen
        ? "Assets/img/users/" + encodeURIComponent(data.imagen)
        : avatarUsuarioPredeterminado;

      colocarAvatarSeguro(imagen);
      $("#imagenactual").val(data.imagen || "");

      $("#claves").hide();
      $("#clave").prop("required", false).val("");

      cargarPermisos(data.idusuario);
      actualizarResumenAsignacion();

      mostrarform(true);
      $("#tituloFormulario").text("Editar usuario");
    })
    .fail(function (xhr) {
      console.error(xhr.responseText);
      mostrarAlerta("Error", "No se pudo cargar el usuario.", "error");
    });
}

function mostrar_clave(idusuario) {
  $("#formularioc")[0].reset();
  $("#idusuarioc").val(idusuario);
  mostrarform_clave(true);
}

function cambiarEstado(idusuario, activarUsuario) {
  var accion = activarUsuario ? "activar" : "desactivar";
  var titulo = activarUsuario ? "¿Activar usuario?" : "¿Desactivar usuario?";
  var mensaje = activarUsuario
    ? "El usuario podrá ingresar nuevamente al sistema."
    : "El usuario ya no podrá iniciar sesión.";

  swal({
    title: titulo,
    text: mensaje,
    icon: "warning",
    buttons: {
      cancel: "Cancelar",
      confirm: activarUsuario ? "Sí, activar" : "Sí, desactivar"
    },
    dangerMode: !activarUsuario
  }).then(function (confirmado) {
    if (!confirmado) {
      return;
    }

    $.ajax({
      url: "Controllers/User.php?op=" + accion,
      type: "POST",
      data: { idusuario: idusuario },
      dataType: "json"
    })
      .done(function (respuesta) {
        mostrarAlerta(
          activarUsuario ? "Usuario activado" : "Usuario desactivado",
          respuesta.mensaje,
          respuesta.ok ? "success" : "error"
        );

        if (respuesta.ok) {
          tabla.ajax.reload(null, false);
        }
      })
      .fail(function (xhr) {
        console.error(xhr.responseText);
        mostrarAlerta("Error", "No se pudo cambiar el estado.", "error");
      });
  });
}

function desactivar(idusuario) {
  cambiarEstado(idusuario, false);
}

function activar(idusuario) {
  cambiarEstado(idusuario, true);
}

function previsualizarImagen(evento) {
  var archivo = evento.target.files && evento.target.files[0];

  if (!archivo) {
    return;
  }

  if (!archivo.type.match(/^image\/(jpeg|png|webp)$/)) {
    mostrarAlerta("Imagen no válida", "Selecciona un archivo JPG, PNG o WEBP.", "warning");
    $("#imagen").val("");
    return;
  }

  var lector = new FileReader();

  lector.onload = function (e) {
    colocarAvatarSeguro(e.target.result);
  };

  lector.readAsDataURL(archivo);
}

$(function () {
  init();
});
