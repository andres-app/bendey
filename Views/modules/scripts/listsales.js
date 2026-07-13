// Views/modules/scripts/listsales.js

let tabla = null;

/*
|--------------------------------------------------------------------------
| INICIALIZACIÓN
|--------------------------------------------------------------------------
*/
function init() {
  listar();
}

$(document).on("click", "#btnagregar", function () {
  window.location.href = "newsale3";
});

/*
|--------------------------------------------------------------------------
| LISTADO DE VENTAS
|--------------------------------------------------------------------------
*/
function listar() {
  tabla = $("#tbllistado")
    .DataTable({
      processing: true,
      serverSide: false,
      dom: "Bfrtip",

      buttons: [
        {
          extend: "excelHtml5",
          text: '<i class="fa fa-file-excel-o bg-green"></i> Excel',
          titleAttr: "Exportar a Excel",
          title: "Reporte de Ventas",
          sheetName: "Ventas",
          exportOptions: {
            columns: [1, 2, 3, 4, 5, 6, 7],
          },
        },
        {
          extend: "pdfHtml5",
          text: '<i class="fa fa-file-pdf-o bg-red"></i> PDF',
          titleAttr: "Exportar a PDF",
          title: "Reporte de Ventas",
          pageSize: "A4",
          exportOptions: {
            columns: [1, 2, 3, 4, 5, 6, 7],
          },
        },
      ],

      ajax: {
        url: "Controllers/Sell.php?op=listar",
        type: "GET",
        dataType: "json",

        error: function (xhr) {
          console.error(
            "Error al listar ventas:",
            xhr.responseText
          );
        },
      },

      destroy: true,
      pageLength: 10,
      order: [],
    });
}

/*
|--------------------------------------------------------------------------
| MOSTRAR VENTA
|--------------------------------------------------------------------------
*/
function mostrar(idventa) {
  const id = parseInt(idventa, 10);

  if (!id || id <= 0) {
    swal(
      "Error",
      "El ID de venta no es válido.",
      "error"
    );

    return;
  }

  limpiarVistaVenta();

  $("#getCodeModal").modal("show");

  /*
  |--------------------------------------------------------------------------
  | CABECERA
  |--------------------------------------------------------------------------
  */
  $.ajax({
    url: "Controllers/Sell.php?op=mostrar",
    type: "POST",
    dataType: "json",
    data: {
      idventa: id,
    },

    success: function (data) {
      console.log("VENTA:", data);

      if (
        !data ||
        typeof data !== "object" ||
        !data.idventa
      ) {
        swal(
          "Venta no encontrada",
          "No se pudo cargar la información de la venta.",
          "warning"
        );

        return;
      }

      $("#idventam").val(
        data.idventa || ""
      );

      $("#cliente").val(
        data.cliente || "SIN CLIENTE"
      );

      $("#fecha_horam").val(
        data.fecha || ""
      );

      $("#tipo_comprobantem").val(
        data.tipo_comprobante || ""
      );

      $("#serie_comprobantem").val(
        data.serie_comprobante || ""
      );

      $("#num_comprobantem").val(
        data.num_comprobante || ""
      );

      $("#impuestom").val(
        formatearNumero(
          data.impuesto || 0
        )
      );

      /*
      |--------------------------------------------------------------------------
      | FORMA Y CONDICIÓN
      |--------------------------------------------------------------------------
      */
      $("#tipo_pagom").val(
        data.forma_pago ||
        "No especificado"
      );

      const condicion =
        normalizarCondicionPago(
          data.tipo_pago
        );

      $("#condicion_pagom").val(
        condicion
      );

      if (condicion === "CRÉDITO") {
        $("#bloquePagoMixto").hide();
        $("#detallePagom").empty();

        cargarCuotasVenta(id);
      } else {
        $("#bloqueCuotas").hide();
        $("#detalleCuotasm").empty();

        cargarPagosVenta(id);
      }
    },

    error: function (xhr) {
      console.error(
        "Error al cargar la venta:",
        xhr.responseText
      );

      swal(
        "Error",
        "No se pudo cargar la cabecera de la venta.",
        "error"
      );
    },
  });

  /*
  |--------------------------------------------------------------------------
  | DETALLE DE PRODUCTOS
  |--------------------------------------------------------------------------
  */
  $.ajax({
    url:
      "Controllers/Sell.php?op=listarDetalle&id=" +
      encodeURIComponent(id),

    type: "POST",

    success: function (html) {
      $("#detallesm").html(html);
    },

    error: function (xhr) {
      console.error(
        "Error al cargar productos:",
        xhr.responseText
      );

      $("#detallesm").html(`
        <tbody>
          <tr>
            <td class="text-center text-danger">
              No se pudo cargar el detalle de productos.
            </td>
          </tr>
        </tbody>
      `);
    },
  });
}

/*
|--------------------------------------------------------------------------
| PAGOS DE VENTA AL CONTADO
|--------------------------------------------------------------------------
*/
function cargarPagosVenta(idventa) {
  $.ajax({
    url:
      "Controllers/Sell.php?op=pagos&idventa=" +
      encodeURIComponent(idventa),

    type: "GET",
    dataType: "json",

    success: function (pagos) {
      const tbody = $("#detallePagom");

      tbody.empty();

      if (
        !Array.isArray(pagos) ||
        pagos.length === 0
      ) {
        $("#bloquePagoMixto").hide();
        return;
      }

      let totalPagado = 0;

      pagos.forEach(function (pago) {
        const nombre =
          escaparHtml(
            pago.nombre ||
            "No especificado"
          );

        const monto =
          parseFloat(pago.monto) || 0;

        totalPagado += monto;

        tbody.append(`
          <tr>
            <td>${nombre}</td>
            <td class="text-right">
              S/ ${formatearNumero(monto)}
            </td>
          </tr>
        `);
      });

      tbody.append(`
        <tr>
          <th class="text-right">
            Total pagado
          </th>

          <th class="text-right">
            S/ ${formatearNumero(totalPagado)}
          </th>
        </tr>
      `);

      /*
       * Ahora se muestra también cuando existe
       * un solo método de pago.
       */
      $("#bloquePagoMixto").show();
    },

    error: function (xhr) {
      console.error(
        "Error al cargar pagos:",
        xhr.responseText
      );

      $("#bloquePagoMixto").hide();
    },
  });
}

/*
|--------------------------------------------------------------------------
| CUOTAS DE VENTA AL CRÉDITO
|--------------------------------------------------------------------------
*/
function cargarCuotasVenta(idventa) {
  $.ajax({
    url:
      "Controllers/Sell.php?op=cuotas&idventa=" +
      encodeURIComponent(idventa),

    type: "GET",
    dataType: "json",

    success: function (cuotas) {
      const tbody = $("#detalleCuotasm");

      tbody.empty();

      $("#totalPendienteCuotasm").text(
        "S/ 0.00"
      );

      $("#resumenCuotasm").text("");

      if (
        !Array.isArray(cuotas) ||
        cuotas.length === 0
      ) {
        tbody.html(`
          <tr>
            <td
              colspan="6"
              class="text-center text-muted">

              Esta venta al crédito no tiene cuotas registradas.

            </td>
          </tr>
        `);

        $("#bloqueCuotas").show();

        return;
      }

      let totalPendiente = 0;
      let totalCredito = 0;

      cuotas.forEach(function (cuota) {
        const codigo =
          escaparHtml(
            cuota.codigo ||
            `Cuota${String(
              cuota.numero_cuota || ""
            ).padStart(3, "0")}`
          );

        const monto =
          parseFloat(cuota.monto) || 0;

        const pagado =
          parseFloat(
            cuota.monto_pagado
          ) || 0;

        const saldo =
          parseFloat(cuota.saldo) ||
          Math.max(
            monto - pagado,
            0
          );

        totalCredito += monto;
        totalPendiente += saldo;

        const fecha =
          escaparHtml(
            cuota.fecha_vencimiento ||
            ""
          );

        const estadoOriginal =
          String(
            cuota.estado ||
            "PENDIENTE"
          )
            .trim()
            .toUpperCase();

        const badge =
          obtenerBadgeCuota(
            estadoOriginal
          );

        tbody.append(`
          <tr>
            <td>
              <strong>${codigo}</strong>
            </td>

            <td class="text-right">
              S/ ${formatearNumero(monto)}
            </td>

            <td>
              ${fecha}
            </td>

            <td class="text-right">
              S/ ${formatearNumero(pagado)}
            </td>

            <td class="text-right">
              <strong>
                S/ ${formatearNumero(saldo)}
              </strong>
            </td>

            <td class="text-center">
              ${badge}
            </td>
          </tr>
        `);
      });

      $("#totalPendienteCuotasm").text(
        "S/ " +
        formatearNumero(
          totalPendiente
        )
      );

      $("#resumenCuotasm").text(
        cuotas.length +
        (
          cuotas.length === 1
            ? " cuota"
            : " cuotas"
        ) +
        " · Total S/ " +
        formatearNumero(
          totalCredito
        )
      );

      $("#bloqueCuotas").show();
    },

    error: function (xhr) {
      console.error(
        "Error al cargar cuotas:",
        xhr.responseText
      );

      $("#detalleCuotasm").html(`
        <tr>
          <td
            colspan="6"
            class="text-center text-danger">

            No se pudo cargar el cronograma de cuotas.

          </td>
        </tr>
      `);

      $("#bloqueCuotas").show();
    },
  });
}

/*
|--------------------------------------------------------------------------
| LIMPIAR MODAL
|--------------------------------------------------------------------------
*/
function limpiarVistaVenta() {
  $("#idventam").val("");
  $("#cliente").val("");
  $("#fecha_horam").val("");
  $("#tipo_comprobantem").val("");
  $("#serie_comprobantem").val("");
  $("#num_comprobantem").val("");
  $("#impuestom").val("");
  $("#tipo_pagom").val("");
  $("#condicion_pagom").val("");

  $("#detallePagom").empty();
  $("#detalleCuotasm").empty();

  $("#bloquePagoMixto").hide();
  $("#bloqueCuotas").hide();

  $("#totalPendienteCuotasm").text(
    "S/ 0.00"
  );

  $("#resumenCuotasm").text("");

  $("#detallesm").html(`
    <tbody>
      <tr>
        <td class="text-center text-muted">
          Cargando detalle...
        </td>
      </tr>
    </tbody>
  `);
}

/*
|--------------------------------------------------------------------------
| NORMALIZAR CONTADO / CRÉDITO
|--------------------------------------------------------------------------
*/
function normalizarCondicionPago(valor) {
  const texto = String(
    valor || ""
  )
    .trim()
    .toUpperCase()
    .normalize("NFD")
    .replace(
      /[\u0300-\u036f]/g,
      ""
    );

  if (
    texto === "4" ||
    texto.includes("CREDITO")
  ) {
    return "CRÉDITO";
  }

  if (
    texto === "1" ||
    texto.includes("CONTADO")
  ) {
    return "CONTADO";
  }

  return texto || "NO ESPECIFICADO";
}

/*
|--------------------------------------------------------------------------
| ESTADO VISUAL DE CUOTA
|--------------------------------------------------------------------------
*/
function obtenerBadgeCuota(estado) {
  switch (estado) {
    case "PAGADO":
      return `
        <span class="badge badge-success">
          Pagado
        </span>
      `;

    case "PARCIAL":
    case "PAGO_PARCIAL":
      return `
        <span class="badge badge-warning">
          Pago parcial
        </span>
      `;

    case "VENCIDO":
      return `
        <span class="badge badge-danger">
          Vencido
        </span>
      `;

    case "ANULADO":
      return `
        <span class="badge badge-secondary">
          Anulado
        </span>
      `;

    case "PENDIENTE":
    default:
      return `
        <span class="badge badge-info">
          Pendiente
        </span>
      `;
  }
}

/*
|--------------------------------------------------------------------------
| UTILIDADES
|--------------------------------------------------------------------------
*/
function formatearNumero(valor) {
  const numero =
    parseFloat(valor) || 0;

  return numero.toLocaleString(
    "es-PE",
    {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }
  );
}

function escaparHtml(valor) {
  return String(valor || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

/*
|--------------------------------------------------------------------------
| ANULAR VENTA
|--------------------------------------------------------------------------
*/
function anular(idventa) {
  swal({
    title: "¿Anular venta?",
    text: "¿Está seguro de anular esta venta?",
    icon: "warning",

    buttons: {
      cancel: "No, cancelar",
      confirm: "Sí, anular",
    },

    dangerMode: true,
  }).then(function (confirmado) {
    if (!confirmado) {
      return;
    }

    $.post(
      "Controllers/Sell.php?op=anular",
      {
        idventa: idventa,
      },

      function (respuesta) {
        swal(
          respuesta,
          {
            icon: "success",
          }
        );

        if (tabla) {
          tabla.ajax.reload(
            null,
            false
          );
        }
      }
    );
  });
}

init();