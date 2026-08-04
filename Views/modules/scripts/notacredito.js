"use strict";

const ncState = {
  data: null,
  formasPago: [],
  pagosOriginales: [],
  totalNota: 0,
  valorVenta: 0,
  igv: 0,
  montoCuotas: 0,
  montoDevolver: 0,
  pagosTocados: false,
  guardando: false,
};

$(document).ready(function () {
  const idventa = obtenerIdVentaOrigen();

  if (idventa <= 0) {
    mostrarErrorInicial(
      "Venta inválida",
      "No se pudo determinar el comprobante de origen."
    );
    return;
  }

  /*
   * Mantener sincronizado el campo oculto para el guardado posterior.
   */
  $("#idventa").val(idventa);

  cargarDatosNota(idventa);
  registrarEventosNota();
});

/*
|--------------------------------------------------------------------------
| OBTENER ID DE LA VENTA ORIGINAL
|--------------------------------------------------------------------------
| Primero se toma el campo oculto generado por PHP. Si el RewriteRule perdió
| la query string al cargar la vista, se recupera directamente de la URL que
| conserva el navegador: /notacredito?idventa=123.
*/
function obtenerIdVentaOrigen() {
  let idventa =
    Number.parseInt(
      $("#idventa").val(),
      10
    ) || 0;

  if (idventa > 0) {
    return idventa;
  }

  try {
    const parametros =
      new URLSearchParams(
        window.location.search || ""
      );

    idventa =
      Number.parseInt(
        parametros.get("idventa"),
        10
      ) || 0;
  } catch (error) {
    console.error(
      "No se pudo leer idventa desde la URL:",
      error
    );

    idventa = 0;
  }

  return idventa > 0
    ? idventa
    : 0;
}

function registrarEventosNota() {
  $(document).on("change", "#codigo_motivo", function () {
    aplicarReglasMotivo();
    recalcularNota();
  });

  $(document).on("change", ".nc-item-check", function () {
    const $fila = $(this).closest("tr");
    const seleccionado = $(this).is(":checked");

    $fila.find(".nc-quantity-input").prop("disabled", !seleccionado);

    if (seleccionado) {
      const actual = parseFloat($fila.find(".nc-quantity-input").val()) || 0;
      const disponible = parseFloat($fila.data("disponible")) || 0;

      if (actual <= 0) {
        $fila.find(".nc-quantity-input").val(formatearInputCantidad(disponible));
      }
    }

    recalcularNota();
  });

  $(document).on("input change", ".nc-quantity-input", function () {
    const $fila = $(this).closest("tr");
    const disponible = parseFloat($fila.data("disponible")) || 0;
    let cantidad = parseFloat($(this).val()) || 0;

    if (cantidad < 0) {
      cantidad = 0;
    }

    if (cantidad > disponible) {
      cantidad = disponible;
      $(this).val(formatearInputCantidad(disponible));
    }

    $fila.find(".nc-item-check").prop("checked", cantidad > 0);
    recalcularNota();
  });

  $(document).on("click", "#btnSeleccionarTodo", function () {
    const motivo = String($("#codigo_motivo").val() || "");

    if (motivo === "01" || motivo === "06") {
      seleccionarTodasLasCantidades();
      return;
    }

    const filasDisponibles = $("#ncDetalleProductos tr[data-detalle]");
    const todasSeleccionadas = filasDisponibles
      .filter(function () {
        return (parseFloat($(this).data("disponible")) || 0) > 0;
      })
      .find(".nc-item-check:not(:checked)").length === 0;

    filasDisponibles.each(function () {
      const disponible = parseFloat($(this).data("disponible")) || 0;
      const seleccionar = !todasSeleccionadas && disponible > 0;

      $(this).find(".nc-item-check").prop("checked", seleccionar);
      $(this).find(".nc-quantity-input")
        .prop("disabled", !seleccionar)
        .val(seleccionar ? formatearInputCantidad(disponible) : "0");
    });

    recalcularNota();
  });

  $(document).on("click", "#btnAgregarFormaDevolucion", function () {
    ncState.pagosTocados = true;
    agregarFilaPago();
  });

  $(document).on("click", ".nc-remove-payment", function () {
    ncState.pagosTocados = true;
    const filas = $("[data-payment-row]");

    if (filas.length <= 1) {
      $(this).closest("[data-payment-row]").find("select").val("");
      $(this).closest("[data-payment-row]").find("input").val("0.00");
    } else {
      $(this).closest("[data-payment-row]").remove();
    }

    actualizarTotalPagos();
  });

  $(document).on("change input", ".nc-forma-pago, .nc-monto-pago", function () {
    ncState.pagosTocados = true;
    actualizarTotalPagos();
  });

  $(document).on("submit", "#formNotaCredito", function (event) {
    event.preventDefault();
    guardarNotaCredito();
  });
}

function cargarDatosNota(idventa) {
  $.ajax({
    url: "Controllers/CreditNote.php?op=preparar",
    type: "GET",
    dataType: "json",
    cache: false,
    data: { idventa: idventa },

    success: function (respuesta) {
      if (!respuesta || respuesta.status !== true || !respuesta.data) {
        mostrarErrorInicial(
          "No se pudo preparar la nota",
          (respuesta && respuesta.message) || "Respuesta inválida del servidor."
        );
        return;
      }

      ncState.data = respuesta.data;
      ncState.formasPago = Array.isArray(respuesta.data.formas_pago)
        ? respuesta.data.formas_pago
        : [];
      ncState.pagosOriginales = Array.isArray(respuesta.data.pagos_originales)
        ? respuesta.data.pagos_originales
        : [];

      renderizarDatosBase();
      renderizarMotivos();
      renderizarProductos();
      reconstruirPagosOriginales();

      $("#ncEstadoCarga").hide();
      $("#formNotaCredito").show();
      recalcularNota();
    },

    error: function (xhr) {
      mostrarErrorInicial(
        "No se puede generar la nota",
        obtenerMensajeAjax(xhr, "No se pudo cargar el comprobante original.")
      );
    },
  });
}

function renderizarDatosBase() {
  const venta = ncState.data.venta || {};
  const cliente = ncState.data.cliente || {};

  $("#ncComprobanteOriginal").text(
    [venta.tipo_comprobante, venta.comprobante].filter(Boolean).join(" · ")
  );
  $("#ncFechaOriginal").text(venta.fecha || "—");
  $("#ncCondicionOriginal").text(venta.condicion_pago || "—");
  $("#ncClienteOriginal").text(cliente.nombre || "SIN CLIENTE");
  $("#ncDocumentoCliente").text(
    [cliente.tipo_documento, cliente.num_documento].filter(Boolean).join(": ")
  );
  $("#ncTotalOriginal").text(moneda(venta.total_venta));
  $("#ncSaldoAcreditable").text(moneda(venta.saldo_acreditable));
  $("#ncEstadoSunatOriginal").text(venta.estado_sunat || "ACEPTADO");
}

function renderizarMotivos() {
  const motivos = Array.isArray(ncState.data.motivos)
    ? ncState.data.motivos
    : [];
  const $select = $("#codigo_motivo");

  $select.empty().append('<option value="">Seleccione...</option>');

  motivos.forEach(function (motivo) {
    $select.append(
      $("<option>", {
        value: motivo.codigo,
        text: motivo.codigo + " · " + motivo.descripcion,
      }).attr({
        "data-descripcion": motivo.descripcion,
        "data-parcial": motivo.permite_parcial,
        "data-stock": motivo.afecta_stock_default,
      })
    );
  });
}

function renderizarProductos() {
  const detalles = Array.isArray(ncState.data.detalles)
    ? ncState.data.detalles
    : [];
  const $tbody = $("#ncDetalleProductos");

  $tbody.empty();

  detalles.forEach(function (detalle) {
    const disponible = parseFloat(detalle.cantidad_disponible) || 0;
    const agotado = disponible <= 0;

    $tbody.append(`
      <tr
        data-detalle
        data-id="${Number(detalle.iddetalle_venta)}"
        data-disponible="${disponible}"
        data-original="${parseFloat(detalle.cantidad_original) || 0}"
        data-precio="${parseFloat(detalle.precio_venta) || 0}"
        data-descuento="${parseFloat(detalle.descuento) || 0}">
        <td class="text-center">
          <input
            type="checkbox"
            class="nc-item-check"
            ${agotado ? "disabled" : ""}>
        </td>
        <td>
          <span class="nc-product-name">${escaparHtml(detalle.descripcion_articulo)}</span>
          <span class="nc-product-code">${escaparHtml(detalle.codigo_articulo || "Sin código")}</span>
        </td>
        <td class="text-center">${formatearCantidad(detalle.cantidad_original)}</td>
        <td class="text-center">
          ${agotado
            ? '<span class="text-muted">Sin saldo</span>'
            : `<strong>${formatearCantidad(disponible)}</strong>`}
        </td>
        <td class="text-center">
          <input
            type="number"
            class="form-control form-control-sm nc-quantity-input"
            min="0"
            max="${disponible}"
            step="1"
            value="0"
            ${agotado ? "disabled" : "disabled"}>
        </td>
        <td class="text-right">${moneda(detalle.precio_venta)}</td>
        <td class="text-right nc-line-total">S/ 0.00</td>
      </tr>
    `);
  });
}

function aplicarReglasMotivo() {
  const codigo = String($("#codigo_motivo").val() || "");
  const $opcion = $("#codigo_motivo option:selected");
  const descripcion = String($opcion.data("descripcion") || "");
  const esTotal = codigo === "01" || codigo === "06";

  if (codigo && !String($("#sustento").val() || "").trim()) {
    $("#sustento").val(descripcion);
  }

  if (esTotal) {
    seleccionarTodasLasCantidades();
    $("#btnSeleccionarTodo").prop("disabled", true);
    $("#ncAyudaProductos").text(
      "Este motivo requiere incluir todo el saldo disponible del comprobante."
    );
  } else {
    $("#btnSeleccionarTodo").prop("disabled", false);
    $("#ncAyudaProductos").text(
      "Selecciona únicamente los productos y cantidades que serán devueltos."
    );

    $("#ncDetalleProductos tr[data-detalle]").each(function () {
      const disponible = parseFloat($(this).data("disponible")) || 0;
      $(this).find(".nc-item-check").prop("disabled", disponible <= 0);
      $(this).find(".nc-quantity-input").prop(
        "disabled",
        !$(this).find(".nc-item-check").is(":checked") || disponible <= 0
      );
    });
  }
}

function seleccionarTodasLasCantidades() {
  $("#ncDetalleProductos tr[data-detalle]").each(function () {
    const disponible = parseFloat($(this).data("disponible")) || 0;
    const seleccionar = disponible > 0;

    $(this).find(".nc-item-check")
      .prop("checked", seleccionar)
      .prop("disabled", true);
    $(this).find(".nc-quantity-input")
      .prop("disabled", true)
      .val(seleccionar ? formatearInputCantidad(disponible) : "0");
  });

  recalcularNota();
}

function recalcularNota() {
  if (!ncState.data) {
    return;
  }

  const venta = ncState.data.venta || {};
  const detalles = Array.isArray(ncState.data.detalles)
    ? ncState.data.detalles
    : [];

  let brutoOriginal = 0;
  detalles.forEach(function (detalle) {
    brutoOriginal +=
      (parseFloat(detalle.cantidad_original) || 0) *
      (parseFloat(detalle.precio_venta) || 0) -
      (parseFloat(detalle.descuento) || 0);
  });

  const descuentoOriginal = parseFloat(venta.descuento_total) || 0;
  const factorDescuento = brutoOriginal > 0
    ? Math.min(descuentoOriginal / brutoOriginal, 1)
    : 0;

  let total = 0;
  let seleccionados = 0;

  $("#ncDetalleProductos tr[data-detalle]").each(function () {
    const $fila = $(this);
    const seleccionado = $fila.find(".nc-item-check").is(":checked");
    const cantidad = seleccionado
      ? parseFloat($fila.find(".nc-quantity-input").val()) || 0
      : 0;
    const precio = parseFloat($fila.data("precio")) || 0;
    const descuentoItemOriginal = parseFloat($fila.data("descuento")) || 0;
    const cantidadOriginal = parseFloat($fila.data("original")) || 0;
    const descuentoItem = cantidadOriginal > 0
      ? descuentoItemOriginal * (cantidad / cantidadOriginal)
      : 0;
    const bruto = Math.max(cantidad * precio - descuentoItem, 0);
    const linea = redondear(bruto - bruto * factorDescuento, 2);

    if (cantidad > 0) {
      seleccionados += 1;
      total += linea;
    }

    $fila.find(".nc-line-total").text(moneda(linea));
  });

  total = redondear(total, 2);

  const motivo = String($("#codigo_motivo").val() || "");
  const esTotal = motivo === "01" || motivo === "06";

  if (esTotal && seleccionados > 0) {
    total = redondear(parseFloat(venta.saldo_acreditable) || 0, 2);
  }

  ncState.totalNota = total;
  ncState.valorVenta = redondear(total / 1.18, 2);
  ncState.igv = redondear(total - ncState.valorVenta, 2);

  $("#ncValorVenta").text(moneda(ncState.valorVenta));
  $("#ncIgv").text(moneda(ncState.igv));
  $("#ncTotalNota").text(moneda(ncState.totalNota));

  recalcularAplicacionFinanciera();
}

function recalcularAplicacionFinanciera() {
  const venta = ncState.data.venta || {};
  const credito = ncState.data.credito || {};
  const esCredito = String(venta.condicion_pago || "") === "CREDITO";

  ncState.montoCuotas = esCredito
    ? Math.min(
        ncState.totalNota,
        parseFloat(credito.saldo_pendiente) || 0
      )
    : 0;
  ncState.montoCuotas = redondear(ncState.montoCuotas, 2);
  ncState.montoDevolver = redondear(
    ncState.totalNota - ncState.montoCuotas,
    2
  );

  if (esCredito) {
    $("#ncResumenCredito").show();
    $("#ncMontoCuotas").text(moneda(ncState.montoCuotas));
    $("#ncMontoDevolverCredito").text(moneda(ncState.montoDevolver));
    $("#ncAyudaFinanciera").text(
      "Primero se reducirá el saldo de las cuotas. Solo el excedente se devolverá por caja o medio de pago."
    );
  } else {
    $("#ncResumenCredito").hide();
    $("#ncAyudaFinanciera").text(
      "Distribuye el total de la nota entre una o más formas de devolución."
    );
  }

  const requierePago = ncState.montoDevolver > 0.009;
  $("#ncPagosDevolucion, #btnAgregarFormaDevolucion, .nc-payment-total")
    .toggle(requierePago);

  if (!requierePago) {
    $("[data-payment-row] select").val("");
    $("[data-payment-row] input").val("0.00");
    ncState.pagosTocados = false;
  } else if (!ncState.pagosTocados) {
    distribuirPagosOriginales();
  }

  actualizarTotalPagos();
}

function reconstruirPagosOriginales() {
  $("#ncPagosDevolucion").empty();

  if (ncState.pagosOriginales.length > 1) {
    ncState.pagosOriginales.forEach(function (pago) {
      agregarFilaPago(pago.idforma_pago, 0);
    });
  } else {
    agregarFilaPago(
      ncState.pagosOriginales[0]
        ? ncState.pagosOriginales[0].idforma_pago
        : "",
      0
    );
  }

  ncState.pagosTocados = false;
}

function agregarFilaPago(idforma = "", monto = 0) {
  const opciones = construirOpcionesFormasPago(idforma);

  $("#ncPagosDevolucion").append(`
    <div class="nc-payment-row" data-payment-row>
      <div class="row align-items-end">
        <div class="col-md-7 col-12 form-group mb-md-0">
          <label>Forma de devolución</label>
          <select class="form-control nc-forma-pago">
            ${opciones}
          </select>
        </div>
        <div class="col-md-4 col-9 form-group mb-md-0">
          <label>Monto</label>
          <input
            type="number"
            class="form-control nc-monto-pago"
            min="0"
            step="0.01"
            value="${redondear(monto, 2).toFixed(2)}">
        </div>
        <div class="col-md-1 col-3 form-group mb-0 text-right">
          <button
            type="button"
            class="btn btn-outline-danger nc-remove-payment"
            title="Quitar forma">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
    </div>
  `);
}

function construirOpcionesFormasPago(seleccionado) {
  let html = '<option value="">Seleccione...</option>';

  ncState.formasPago.forEach(function (forma) {
    const id = String(forma.idforma_pago);
    html += `<option value="${id}" ${String(seleccionado) === id ? "selected" : ""}>${escaparHtml(forma.nombre)}</option>`;
  });

  return html;
}

function distribuirPagosOriginales() {
  const $filas = $("[data-payment-row]");

  if ($filas.length === 0) {
    agregarFilaPago();
  }

  const pagos = ncState.pagosOriginales;
  const totalOriginalPagos = pagos.reduce(function (suma, pago) {
    return suma + (parseFloat(pago.monto) || 0);
  }, 0);

  if (pagos.length === 0 || totalOriginalPagos <= 0) {
    const $primera = $("[data-payment-row]").first();
    $primera.find(".nc-monto-pago").val(ncState.montoDevolver.toFixed(2));
    return;
  }

  if ($("[data-payment-row]").length !== pagos.length) {
    $("#ncPagosDevolucion").empty();
    pagos.forEach(function (pago) {
      agregarFilaPago(pago.idforma_pago, 0);
    });
  }

  let asignado = 0;
  $("[data-payment-row]").each(function (indice) {
    const pago = pagos[indice];
    let monto;

    if (indice === pagos.length - 1) {
      monto = redondear(ncState.montoDevolver - asignado, 2);
    } else {
      monto = redondear(
        ncState.montoDevolver * ((parseFloat(pago.monto) || 0) / totalOriginalPagos),
        2
      );
      asignado += monto;
    }

    $(this).find(".nc-forma-pago").val(String(pago.idforma_pago));
    $(this).find(".nc-monto-pago").val(Math.max(monto, 0).toFixed(2));
  });
}

function actualizarTotalPagos() {
  let total = 0;

  $(".nc-monto-pago").each(function () {
    total += parseFloat($(this).val()) || 0;
  });

  $("#ncTotalPagos")
    .text(moneda(total))
    .toggleClass("text-danger", Math.abs(total - ncState.montoDevolver) > 0.01)
    .toggleClass("text-success", Math.abs(total - ncState.montoDevolver) <= 0.01);
}

function guardarNotaCredito() {
  if (ncState.guardando) {
    return;
  }

  const codigoMotivo = String($("#codigo_motivo").val() || "");
  const sustento = String($("#sustento").val() || "").trim();
  const items = obtenerItemsSeleccionados();
  const pagos = obtenerPagosSeleccionados();

  if (!codigoMotivo) {
    avisoValidacion("Seleccione el motivo SUNAT de la nota de crédito.");
    return;
  }

  if (sustento.length < 3) {
    avisoValidacion("Ingrese un sustento válido.");
    return;
  }

  if (items.length === 0 || ncState.totalNota <= 0) {
    avisoValidacion("Seleccione al menos un producto y una cantidad válida.");
    return;
  }

  if (ncState.totalNota > (parseFloat(ncState.data.venta.saldo_acreditable) || 0) + 0.01) {
    avisoValidacion("El total de la nota supera el saldo acreditable.");
    return;
  }

  if (ncState.montoDevolver > 0.009) {
    const totalPagos = redondear(
      pagos.reduce(function (suma, pago) {
        return suma + pago.monto;
      }, 0),
      2
    );

    if (pagos.length === 0) {
      avisoValidacion("Seleccione cómo se devolverá el importe al cliente.");
      return;
    }

    if (Math.abs(totalPagos - ncState.montoDevolver) > 0.01) {
      avisoValidacion(
        "Las formas de devolución deben sumar " + moneda(ncState.montoDevolver) + "."
      );
      return;
    }
  }

  Swal.fire({
    icon: "warning",
    title: "Confirmar nota de crédito",
    html:
      '<div style="text-align:left">' +
      '<p>Se generará una nota por <strong>' + moneda(ncState.totalNota) + '</strong>.</p>' +
      '<p>El documento original permanecerá registrado y quedará relacionado con esta nota.</p>' +
      '<p>El stock, la caja y las cuotas se modificarán cuando SUNAT acepte la nota.</p>' +
      '</div>',
    showCancelButton: true,
    confirmButtonText: "Sí, generar nota",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    allowOutsideClick: false,
  }).then(function (resultado) {
    if (!resultado.isConfirmed) {
      return;
    }

    enviarRegistroNota(items, pagos);
  });
}

function enviarRegistroNota(items, pagos) {
  ncState.guardando = true;

  const $boton = $("#btnGuardarNota");
  const contenidoOriginal = $boton.html();

  $boton
    .prop("disabled", true)
    .html('<span class="spinner-border spinner-border-sm mr-2"></span>Generando...');

  $.ajax({
    url: "Controllers/CreditNote.php?op=guardar",
    type: "POST",
    dataType: "json",
    cache: false,
    data: {
      idventa: Number.parseInt($("#idventa").val(), 10),
      codigo_motivo: $("#codigo_motivo").val(),
      sustento: $("#sustento").val(),
      observacion: $("#observacion").val(),
      modo_envio: $("#modo_envio").val(),
      items_json: JSON.stringify(items),
      pagos_json: JSON.stringify(pagos),
    },

    success: function (respuesta) {
      if (!respuesta || respuesta.status !== true || !respuesta.resultado) {
        Swal.fire({
          icon: "error",
          title: "No se pudo generar",
          text: (respuesta && respuesta.message) || "Respuesta inválida del servidor.",
        });
        return;
      }

      const resultado = respuesta.resultado;
      const sunat = respuesta.sunat || null;
      const idnota = Number.parseInt(resultado.idnota_credito, 10) || 0;
      const enviado = sunat && sunat.success === true;
      const mensajeSunat = sunat
        ? escaparHtml(sunat.mensaje || "")
        : "La nota quedó lista para envío manual.";

      $("#formNotaCredito :input").prop("disabled", true);

      if (enviado && ["PENDIENTE", "EN_PROCESO", "ENVIADO"].includes(String(sunat.status || "").toUpperCase())) {
        window.setTimeout(function () {
          consultarNotaSilenciosa(idnota);
        }, 4000);
      }

      Swal.fire({
        icon: enviado || !sunat ? "success" : "warning",
        title: "Nota de crédito registrada",
        html:
          '<div style="text-align:left">' +
          '<p><strong>Comprobante:</strong> ' + escaparHtml(resultado.comprobante) + '</p>' +
          '<p><strong>Total:</strong> ' + moneda(resultado.total_nota) + '</p>' +
          '<p>' + mensajeSunat + '</p>' +
          '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">' +
          '<a class="btn btn-outline-secondary btn-sm" target="_blank" href="Reports/notacredito_80mm.php?id=' + idnota + '">Imprimir 80 mm</a>' +
          '<a class="btn btn-outline-secondary btn-sm" target="_blank" href="Reports/notacredito_a4.php?id=' + idnota + '">Imprimir A4</a>' +
          '</div>' +
          '</div>',
        showCancelButton: true,
        confirmButtonText: "Ir al módulo SUNAT",
        cancelButtonText: "Volver a ventas",
        reverseButtons: true,
        allowOutsideClick: false,
      }).then(function (modal) {
        window.location.href = modal.isConfirmed ? "sunat" : "listsales";
      });
    },

    error: function (xhr) {
      Swal.fire({
        icon: "error",
        title: "No se pudo generar",
        text: obtenerMensajeAjax(xhr, "Ocurrió un error al registrar la nota."),
      });
    },

    complete: function () {
      ncState.guardando = false;
      $boton.prop("disabled", false).html(contenidoOriginal);
    },
  });
}

function consultarNotaSilenciosa(idnota) {
  if (!idnota) {
    return;
  }

  $.ajax({
    url: "Controllers/CreditNote.php?op=consultar",
    type: "POST",
    dataType: "json",
    cache: false,
    data: { idnota_credito: idnota },
  });
}

function obtenerItemsSeleccionados() {
  const items = [];

  $("#ncDetalleProductos tr[data-detalle]").each(function () {
    const seleccionado = $(this).find(".nc-item-check").is(":checked");
    const cantidad = parseFloat($(this).find(".nc-quantity-input").val()) || 0;

    if (seleccionado && cantidad > 0) {
      items.push({
        iddetalle_venta: Number.parseInt($(this).data("id"), 10),
        cantidad: cantidad,
      });
    }
  });

  return items;
}

function obtenerPagosSeleccionados() {
  const pagos = [];

  if (ncState.montoDevolver <= 0.009) {
    return pagos;
  }

  $("[data-payment-row]").each(function () {
    const idforma = Number.parseInt($(this).find(".nc-forma-pago").val(), 10) || 0;
    const monto = redondear(parseFloat($(this).find(".nc-monto-pago").val()) || 0, 2);

    if (idforma > 0 && monto > 0) {
      pagos.push({ idforma_pago: idforma, monto: monto });
    }
  });

  return pagos;
}

function mostrarErrorInicial(titulo, mensaje) {
  $("#ncEstadoCarga")
    .removeClass("alert-light")
    .addClass("alert-danger")
    .html('<i class="fas fa-exclamation-circle mr-2"></i>' + escaparHtml(mensaje));

  Swal.fire({
    icon: "error",
    title: titulo,
    text: mensaje,
    confirmButtonText: "Volver a ventas",
    allowOutsideClick: false,
  }).then(function () {
    window.location.href = "listsales";
  });
}

function avisoValidacion(mensaje) {
  Swal.fire({
    icon: "warning",
    title: "Revisa la información",
    text: mensaje,
  });
}

function obtenerMensajeAjax(xhr, predeterminado) {
  if (xhr.responseJSON) {
    return xhr.responseJSON.message || xhr.responseJSON.mensaje || predeterminado;
  }

  return predeterminado;
}

function moneda(valor) {
  const numero = parseFloat(valor) || 0;

  return "S/ " + numero.toLocaleString("es-PE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatearCantidad(valor) {
  const numero = parseFloat(valor) || 0;

  return Number.isInteger(numero)
    ? String(numero)
    : numero.toFixed(3).replace(/0+$/, "").replace(/\.$/, "");
}

function formatearInputCantidad(valor) {
  const numero = parseFloat(valor) || 0;
  return Number.isInteger(numero) ? String(numero) : numero.toFixed(3);
}

function redondear(numero, decimales) {
  const factor = Math.pow(10, decimales);
  return Math.round((Number(numero) + Number.EPSILON) * factor) / factor;
}

function escaparHtml(valor) {
  return String(valor == null ? "" : valor)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
