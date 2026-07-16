// Views/modules/scripts/cobranzas.js

let tablaCobranzas = null;
let formasPagoCobranza = [];
let contadorPagosCobranza = 0;

$(document).ready(function () {
  cargarFormasPagoCobranza();
  listarCuentasPorCobrar();

  $(document).on("click", "#btnAgregarPagoCobranza", function () {
    agregarLineaPagoCobranza();
  });

  $(document).on(
    "change input",
    ".chk-aplicar-cuota, .monto-aplicar-cuota, .monto-pago-cobranza",
    recalcularCobranza
  );

  $(document).on("change", ".forma-pago-cobranza", function () {
    actualizarLineaPago($(this).closest(".pago-linea"));
    recalcularCobranza();
  });

  $(document).on("click", ".btn-eliminar-pago-cobranza", function () {
    $(this).closest(".pago-linea").remove();
    recalcularCobranza();
  });

  $(document).on("click", "#btnGuardarCobranza", registrarCobranza);
});

function listarCuentasPorCobrar() {
  tablaCobranzas = $("#tablaCobranzas").DataTable({
    processing: true,
    serverSide: false,
    destroy: true,
    pageLength: 10,
    order: [],
    ajax: {
      url: "Controllers/Cobranza.php?op=listar",
      type: "GET",
      dataType: "json",
      error: function (xhr) {
        console.error(xhr.responseText);
      },
    },
    columnDefs: [
      {
        targets: 0,
        orderable: false,
        searchable: false,
        width: "70px",
      },
    ],
  });
}

function cargarFormasPagoCobranza() {
  $.ajax({
    url: "Controllers/Cobranza.php?op=formas_pago",
    type: "GET",
    dataType: "json",
    success: function (resp) {
      formasPagoCobranza = resp.success && Array.isArray(resp.data)
        ? resp.data
        : [];
    },
    error: function (xhr) {
      console.error(xhr.responseText);
    },
  });
}

function abrirCobranza(idventa) {
  limpiarModalCobranza();

  $.ajax({
    url:
      "Controllers/Cobranza.php?op=detalle&idventa=" +
      encodeURIComponent(idventa),
    type: "GET",
    dataType: "json",
    success: function (resp) {
      if (!resp.success || !resp.data) {
        Swal.fire(
          "Error",
          resp.mensaje || "No se pudo cargar la cuenta por cobrar.",
          "error"
        );
        return;
      }

      const venta = resp.data;

      $("#cobranzaIdVenta").val(venta.idventa);
      $("#cobranzaCliente").text(venta.cliente || "-");
      $("#cobranzaFactura").text(
        (venta.serie_comprobante || "") +
        "-" +
        (venta.num_comprobante || "")
      );
      $("#cobranzaTotal").text(
        "S/ " + formatearMontoCobranza(venta.total_venta)
      );
      $("#cobranzaSunat").text(venta.estado_sunat || "-");

      renderizarCuotasCobranza(venta.cuotas || []);
      renderizarHistorialCobranza(venta.historial || []);
      agregarLineaPagoCobranza();
      recalcularCobranza();
      
      $("#modalCobranza").modal("show");
    },
    error: function (xhr) {
      console.error(xhr.responseText);
      Swal.fire(
        "Error",
        "No se pudo cargar la cuenta por cobrar.",
        "error"
      );
    },
  });
}

function renderizarCuotasCobranza(cuotas) {
  const tbody = $("#tablaCuotasCobranza");
  tbody.empty();

  if (!Array.isArray(cuotas) || cuotas.length === 0) {
    tbody.html(`
      <tr>
        <td colspan="8" class="text-center text-muted">
          La venta no tiene cuotas registradas.
        </td>
      </tr>
    `);
    return;
  }

  cuotas.forEach(function (cuota) {
    const saldo = parseFloat(cuota.saldo) || 0;
    const pagada = saldo <= 0.009;

    tbody.append(`
      <tr
        data-idcuota="${parseInt(cuota.idventa_cuota, 10)}"
        class="${pagada ? "cuota-row-pagada" : ""}">

        <td class="text-center">
          <input
            type="checkbox"
            class="chk-aplicar-cuota"
            ${pagada ? "disabled" : ""}>
        </td>

        <td>
          <strong>${escaparHtmlCobranza(cuota.codigo || "")}</strong>
        </td>

        <td>${escaparHtmlCobranza(cuota.fecha_vencimiento || "")}</td>

        <td class="text-right">
          S/ ${formatearMontoCobranza(cuota.monto)}
        </td>

        <td class="text-right">
          S/ ${formatearMontoCobranza(cuota.monto_pagado)}
        </td>

        <td class="text-right saldo-cuota" data-saldo="${saldo}">
          S/ ${formatearMontoCobranza(saldo)}
        </td>

        <td>
          <input
            type="number"
            min="0.01"
            step="0.01"
            max="${saldo.toFixed(2)}"
            class="form-control form-control-sm monto-aplicar-cuota"
            value="${pagada ? "0.00" : saldo.toFixed(2)}"
            ${pagada ? "disabled" : ""}>
        </td>

        <td>
          ${badgeEstadoCuota(cuota.estado)}
        </td>
      </tr>
    `);
  });

  const primeraPendiente = tbody
    .find(".chk-aplicar-cuota:not(:disabled)")
    .first();

  if (primeraPendiente.length) {
    primeraPendiente.prop("checked", true);
  }

  recalcularCobranza();
}

function renderizarHistorialCobranza(historial) {
  const tbody = $("#historialCobranza");
  tbody.empty();

  if (!Array.isArray(historial) || historial.length === 0) {
    tbody.html(`
      <tr>
        <td colspan="6" class="text-center text-muted">
          Todavía no existen cobranzas registradas.
        </td>
      </tr>
    `);
    return;
  }

  historial.forEach(function (item) {
    tbody.append(`
      <tr>
        <td><strong>${escaparHtmlCobranza(item.codigo)}</strong></td>
        <td>${escaparHtmlCobranza(item.fecha_hora)}</td>
        <td>${escaparHtmlCobranza(item.usuario)}</td>
        <td>${escaparHtmlCobranza(item.formas_pago || "-")}</td>
        <td class="text-right">
          S/ ${formatearMontoCobranza(item.monto_total)}
        </td>
        <td>${escaparHtmlCobranza(item.estado)}</td>
      </tr>
    `);
  });
}

function agregarLineaPagoCobranza() {
  if (
    !Array.isArray(formasPagoCobranza) ||
    formasPagoCobranza.length === 0
  ) {
    Swal.fire(
      "Atención",
      "No existen formas de pago habilitadas para cobranzas.",
      "warning"
    );

    return;
  }

  contadorPagosCobranza++;

  const opciones = formasPagoCobranza
    .map(function (forma) {
      const destino =
        forma.cuenta_destino ||
        "Sin destino configurado";

      return `
        <option
          value="${forma.idforma_pago}"
          data-requiere-operacion="${forma.requiere_operacion}"
          data-requiere-caja="${forma.requiere_caja_abierta}"
          data-destino="${escaparHtmlCobranza(destino)}">

          ${escaparHtmlCobranza(forma.nombre)}
        </option>
      `;
    })
    .join("");

  $("#contenedorPagosCobranza").append(`
    <div
      class="pago-linea"
      data-linea="${contadorPagosCobranza}">

      <div class="row align-items-end">

        <div class="col-md-4">

          <label>
            Forma de pago
          </label>

          <select
            class="form-control forma-pago-cobranza">

            <option
              value=""
              data-requiere-operacion="0"
              data-requiere-caja="0"
              data-destino="">

              Seleccione...
            </option>

            ${opciones}
          </select>

          <div
            class="destino-pago-cobranza mt-2 px-3 py-2"
            style="
              display:block;
              min-height:38px;
              border:1px solid #dfe6ee;
              border-radius:8px;
              background:#f5f8fb;
              color:#506176;
              font-size:13px;
              font-weight:600;
            ">

            <i class="fas fa-university mr-1"></i>
            Destino: seleccione una forma de pago
          </div>

        </div>

        <div class="col-md-3">

          <label>
            Monto
          </label>

          <input
            type="number"
            min="0.01"
            step="0.01"
            class="form-control monto-pago-cobranza"
            value="0.00">

        </div>

        <div
          class="col-md-4 bloque-operacion-cobranza"
          style="display:none;">

          <label>
            Número de operación
          </label>

          <input
            type="text"
            maxlength="100"
            class="form-control numero-operacion-cobranza"
            placeholder="Operación o referencia">

        </div>

        <div class="col-md-1">

          <button
            type="button"
            class="btn btn-outline-danger btn-eliminar-pago-cobranza"
            title="Eliminar">

            <i class="fas fa-trash"></i>

          </button>

        </div>

      </div>
    </div>
  `);
}

function actualizarLineaPago(linea) {
  const opcion = linea.find(
    ".forma-pago-cobranza option:selected"
  );

  const idFormaPago =
    parseInt(opcion.val(), 10) || 0;

  const requiereOperacion =
    parseInt(
      opcion.attr("data-requiere-operacion"),
      10
    ) === 1;

  const requiereCaja =
    parseInt(
      opcion.attr("data-requiere-caja"),
      10
    ) === 1;

  const destino =
    opcion.attr("data-destino") || "";

  linea
    .find(".bloque-operacion-cobranza")
    .toggle(requiereOperacion);

  if (!requiereOperacion) {
    linea
      .find(".numero-operacion-cobranza")
      .val("");
  }

  let textoDestino = `
    <i class="fas fa-university mr-1"></i>
    Destino: seleccione una forma de pago
  `;

  if (idFormaPago > 0) {
    textoDestino = `
      <i class="fas fa-university mr-1"></i>
      Destino: ${escaparHtmlCobranza(
        destino || "Sin destino configurado"
      )}
    `;

    if (requiereCaja) {
      textoDestino += `
        <br>
        <small>
          <i class="fas fa-cash-register mr-1"></i>
          Se registrará en la apertura de caja activa
        </small>
      `;
    }
  }

  linea
    .find(".destino-pago-cobranza")
    .html(textoDestino);
}

function recalcularCobranza() {
  let totalAplicado = 0;

  $("#tablaCuotasCobranza tr[data-idcuota]").each(function () {
    const fila = $(this);
    const checked = fila.find(".chk-aplicar-cuota").is(":checked");
    const input = fila.find(".monto-aplicar-cuota");

    input.prop("disabled", !checked);

    if (!checked) {
      return;
    }

    const saldo = parseFloat(fila.find(".saldo-cuota").data("saldo")) || 0;
    let monto = parseFloat(input.val()) || 0;

    if (monto > saldo) {
      monto = saldo;
      input.val(saldo.toFixed(2));
    }

    totalAplicado += monto;
  });

  let totalPagos = 0;

  $(".monto-pago-cobranza").each(function () {
    totalPagos += parseFloat($(this).val()) || 0;
  });

  $("#totalAplicadoCobranza").text(
    "S/ " + formatearMontoCobranza(totalAplicado)
  );

  $("#totalPagosCobranza").text(
    "S/ " + formatearMontoCobranza(totalPagos)
  );

  const lineasPago = $(".pago-linea");

  if (lineasPago.length === 1) {
    const monto = lineasPago.find(".monto-pago-cobranza");
    const valorActual = parseFloat(monto.val()) || 0;

    if (valorActual === 0 && totalAplicado > 0) {
      monto.val(totalAplicado.toFixed(2));
      $("#totalPagosCobranza").text(
        "S/ " + formatearMontoCobranza(totalAplicado)
      );
    }
  }
}

function registrarCobranza() {
  try {
    const idventa = parseInt($("#cobranzaIdVenta").val(), 10);
    const aplicaciones = [];

    $("#tablaCuotasCobranza tr[data-idcuota]").each(function () {
      const fila = $(this);

      if (!fila.find(".chk-aplicar-cuota").is(":checked")) {
        return;
      }

      const monto =
        parseFloat(fila.find(".monto-aplicar-cuota").val()) || 0;

      if (monto > 0) {
        aplicaciones.push({
          idventa_cuota: parseInt(fila.data("idcuota"), 10),
          monto: monto,
        });
      }
    });

    const pagos = [];
    const formasSeleccionadas = new Set();

    $(".pago-linea").each(function () {
      const linea = $(this);
      const idformaPago = parseInt(
        linea.find(".forma-pago-cobranza").val(),
        10
      );
      const monto =
        parseFloat(linea.find(".monto-pago-cobranza").val()) || 0;
      const numeroOperacion = String(
        linea.find(".numero-operacion-cobranza").val() || ""
      ).trim();

      if (!idformaPago && monto === 0) {
        return;
      }

      if (!idformaPago || monto <= 0) {
        throw new Error("Complete correctamente todas las formas de pago.");
      }

      if (formasSeleccionadas.has(idformaPago)) {
        throw new Error("No repita la misma forma de pago.");
      }

      formasSeleccionadas.add(idformaPago);

      pagos.push({
        idforma_pago: idformaPago,
        monto: monto,
        numero_operacion: numeroOperacion,
      });
    });

    if (!idventa || aplicaciones.length === 0) {
      Swal.fire(
        "Atención",
        "Seleccione al menos una cuota y un monto válido.",
        "warning"
      );
      return;
    }

    if (pagos.length === 0) {
      Swal.fire(
        "Atención",
        "Registre al menos una forma de pago.",
        "warning"
      );
      return;
    }

    const totalAplicado = aplicaciones.reduce(
      (suma, item) => suma + parseFloat(item.monto || 0),
      0
    );

    const totalPagos = pagos.reduce(
      (suma, item) => suma + parseFloat(item.monto || 0),
      0
    );

    if (Math.abs(totalAplicado - totalPagos) > 0.01) {
      Swal.fire(
        "Totales diferentes",
        "El total aplicado a cuotas debe coincidir con el total de las formas de pago.",
        "warning"
      );
      return;
    }

    const boton = $("#btnGuardarCobranza");

    boton
      .prop("disabled", true)
      .html('<i class="fas fa-spinner fa-spin"></i> Registrando...');

    $.ajax({
      url: "Controllers/Cobranza.php?op=registrar",
      type: "POST",
      dataType: "json",
      contentType: "application/json; charset=utf-8",
      data: JSON.stringify({
        idventa: idventa,
        aplicaciones: aplicaciones,
        pagos: pagos,
        observacion: String($("#observacionCobranza").val() || "").trim(),
      }),
      success: function (resp) {
        if (!resp.success) {
          Swal.fire(
            "No se pudo registrar",
            resp.mensaje || "Ocurrió un error.",
            "error"
          );
          return;
        }

        Swal.fire({
          icon: "success",
          title: "Cobranza registrada",
          html:
            "<strong>" +
            escaparHtmlCobranza(resp.codigo) +
            "</strong><br>Monto: S/ " +
            formatearMontoCobranza(resp.monto_total),
        }).then(function () {
          $("#modalCobranza").modal("hide");

          if (tablaCobranzas) {
            tablaCobranzas.ajax.reload(null, false);
          }
        });
      },
      error: function (xhr) {
        console.error(xhr.responseText);
        Swal.fire(
          "Error",
          "No se pudo comunicar con el servidor.",
          "error"
        );
      },
      complete: function () {
        boton
          .prop("disabled", false)
          .html('<i class="fas fa-save"></i> Registrar cobranza');
      },
    });
  } catch (error) {
    Swal.fire("Atención", error.message || "Datos inválidos.", "warning");
  }
}

function limpiarModalCobranza() {
  $("#cobranzaIdVenta").val("");
  $("#cobranzaCliente").text("-");
  $("#cobranzaFactura").text("-");
  $("#cobranzaTotal").text("S/ 0.00");
  $("#cobranzaSunat").text("-");
  $("#tablaCuotasCobranza").empty();
  $("#historialCobranza").empty();
  $("#contenedorPagosCobranza").empty();
  $("#observacionCobranza").val("");
  $("#totalAplicadoCobranza").text("S/ 0.00");
  $("#totalPagosCobranza").text("S/ 0.00");
  contadorPagosCobranza = 0;
}

function badgeEstadoCuota(estado) {
  const valor = String(estado || "PENDIENTE").toUpperCase();

  const clase = {
    PAGADO: "success",
    PARCIAL: "warning",
    VENCIDO: "danger",
    PENDIENTE: "info",
  }[valor] || "secondary";

  return `
    <span class="badge badge-${clase}">
      ${escaparHtmlCobranza(valor)}
    </span>
  `;
}

function formatearMontoCobranza(valor) {
  return (parseFloat(valor) || 0).toLocaleString("es-PE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function escaparHtmlCobranza(valor) {
  return String(valor || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
