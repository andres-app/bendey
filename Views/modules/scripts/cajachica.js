// Views/modules/scripts/cajachica.js

$(document).ready(function () {
  cargarCaja();

  $(
    "#fecha_inicio, #fecha_fin, #idusuario"
  ).on(
    "change",
    function () {
      cargarCaja();
    }
  );
});

/*
|--------------------------------------------------------------------------
| CARGAR RESUMEN
|--------------------------------------------------------------------------
*/
function cargarCaja() {
  const fechaInicio =
    $("#fecha_inicio").val();

  const fechaFin =
    $("#fecha_fin").val();

  const idusuario =
    $("#idusuario").val();

  $.ajax({
    url:
      "Controllers/Cajachica.php" +
      "?op=resumen",

    type: "GET",
    dataType: "json",

    data: {
      fecha_inicio: fechaInicio,
      fecha_fin: fechaFin,
      idusuario: idusuario,
    },

    success: function (resp) {
      if (resp.status !== "ok") {
        Swal.fire(
          "Error",
          resp.message ||
            "No se pudo cargar la caja.",
          "error"
        );

        return;
      }

      renderTabla(
        resp.detalle || [],
        resp.apertura || null
      );

      renderTotales(
        resp.totales || {},
        resp.apertura || null
      );

      actualizarEstadoCaja(
        resp.estado
      );
    },

    error: function (xhr) {
      console.error(
        "Error al cargar caja:",
        xhr.responseText
      );
    },
  });
}

/*
|--------------------------------------------------------------------------
| ESTADO DE CAJA
|--------------------------------------------------------------------------
*/
function actualizarEstadoCaja(
  estado
) {
  const boton =
    $("#btnCerrarCaja");

  const badge =
    $("#estadoCajaBadge");

  if (estado === "ABIERTA") {
    boton
      .prop("disabled", false)
      .removeClass(
        "btn-secondary"
      )
      .addClass(
        "btn-warning"
      );

    badge
      .removeClass(
        "badge-danger badge-warning"
      )
      .addClass(
        "badge-success"
      )
      .text(
        "Caja Abierta"
      );

    return;
  }

  if (estado === "CERRADA") {
    boton
      .prop("disabled", true)
      .removeClass(
        "btn-warning"
      )
      .addClass(
        "btn-secondary"
      );

    badge
      .removeClass(
        "badge-success badge-warning"
      )
      .addClass(
        "badge-danger"
      )
      .text(
        "Caja Cerrada"
      );

    return;
  }

  boton
    .prop("disabled", true)
    .removeClass(
      "btn-warning"
    )
    .addClass(
      "btn-secondary"
    );

  badge
    .removeClass(
      "badge-success badge-danger"
    )
    .addClass(
      "badge-warning"
    )
    .text(
      "Sin apertura"
    );
}

/*
|--------------------------------------------------------------------------
| EXPORTACIONES
|--------------------------------------------------------------------------
*/
function exportarExcel() {
  const fechaInicio =
    $("#fecha_inicio").val();

  const fechaFin =
    $("#fecha_fin").val();

  const idusuario =
    $("#idusuario").val();

  const url =
    "Reports/ExcelCajaChica.php" +
    "?fecha_inicio=" +
    encodeURIComponent(fechaInicio) +
    "&fecha_fin=" +
    encodeURIComponent(fechaFin) +
    "&idusuario=" +
    encodeURIComponent(idusuario);

  window.open(
    url,
    "_blank"
  );
}

function exportarPDF() {
  const fechaInicio =
    $("#fecha_inicio").val();

  const fechaFin =
    $("#fecha_fin").val();

  const url =
    "Reports/caja_chica.php" +
    "?fecha_inicio=" +
    encodeURIComponent(fechaInicio) +
    "&fecha_fin=" +
    encodeURIComponent(fechaFin);

  window.open(
    url,
    "_blank"
  );
}

/*
|--------------------------------------------------------------------------
| TABLA
|--------------------------------------------------------------------------
*/
function renderTabla(
  data,
  apertura
) {
  const filas = {};

  if (!Array.isArray(data)) {
    data = [];
  }

  data.forEach(function (registro) {
    const tipo =
      registro.tipo_comprobante ||
      "SIN COMPROBANTE";

    if (!filas[tipo]) {
      filas[tipo] = {
        efectivo: 0,
        tarjeta: 0,
        transferencia: 0,
        yape: 0,
        plin: 0,
        otros: 0,
      };
    }

    const monto =
      parseFloat(
        registro.total
      ) || 0;

    const forma =
      String(
        registro.forma_pago || ""
      )
        .toLowerCase()
        .trim();

    if (
      forma.includes(
        "efectivo"
      )
    ) {
      filas[tipo].efectivo +=
        monto;
    } else if (
      forma.includes(
        "tarjeta"
      ) ||
      forma.includes(
        "izipay"
      )
    ) {
      filas[tipo].tarjeta +=
        monto;
    } else if (
      forma.includes(
        "transfer"
      )
    ) {
      filas[tipo].transferencia +=
        monto;
    } else if (
      forma.includes(
        "yape"
      )
    ) {
      filas[tipo].yape +=
        monto;
    } else if (
      forma.includes(
        "plin"
      )
    ) {
      filas[tipo].plin +=
        monto;
    } else {
      filas[tipo].otros +=
        monto;
    }
  });

  let html = "";

  const montoApertura =
    parseFloat(
      apertura?.monto_apertura
    ) || 0;

  if (apertura) {
    html += `
      <tr class="table-success font-weight-bold">
        <td>
          APERTURA DE CAJA
        </td>

        <td class="text-right">
          S/ ${formatearMonto(
            montoApertura
          )}
        </td>

        <td class="text-right">-</td>
        <td class="text-right">-</td>
        <td class="text-right">-</td>

        <td class="text-right">
          S/ ${formatearMonto(
            montoApertura
          )}
        </td>
      </tr>
    `;
  }

  Object.keys(
    filas
  ).forEach(function (tipo) {
    const fila =
      filas[tipo];

    const billeteras =
      fila.yape +
      fila.plin;

    const total =
      fila.efectivo +
      fila.tarjeta +
      fila.transferencia +
      billeteras +
      fila.otros;

    html += `
      <tr>
        <td>
          ${escaparHtml(tipo)}
        </td>

        <td class="text-right">
          S/ ${formatearMonto(
            fila.efectivo
          )}
        </td>

        <td class="text-right">
          S/ ${formatearMonto(
            fila.tarjeta
          )}
        </td>

        <td class="text-right">
          S/ ${formatearMonto(
            fila.transferencia
          )}
        </td>

        <td class="text-right">
          S/ ${formatearMonto(
            billeteras
          )}
        </td>

        <td class="text-right font-weight-bold">
          S/ ${formatearMonto(
            total
          )}
        </td>
      </tr>
    `;
  });

  if (apertura?.estado === "CERRADA") {
    html += `
      <tr class="table-danger font-weight-bold">
        <td>
          CIERRE DE CAJA
        </td>

        <td
          colspan="4"
          class="text-center">
          Caja cerrada
        </td>

        <td class="text-right">
          <i class="fas fa-check"></i>
        </td>
      </tr>
    `;
  }

  if (html === "") {
    html = `
      <tr>
        <td
          colspan="6"
          class="text-center text-muted">
          No existen movimientos en el periodo.
        </td>
      </tr>
    `;
  }

  $("#tablaCaja tbody").html(
    html
  );
}

/*
|--------------------------------------------------------------------------
| TOTALES
|--------------------------------------------------------------------------
*/
function renderTotales(
  totales,
  apertura
) {
  const ingresos =
    parseFloat(
      totales.ingresos
    ) || 0;

  const efectivo =
    parseFloat(
      totales.efectivo
    ) || 0;

  const egresos =
    parseFloat(
      totales.egresos
    ) || 0;

  const egresosEfectivo =
    parseFloat(
      totales.egresos_efectivo
    ) || 0;

  const montoApertura =
    parseFloat(
      apertura?.monto_apertura
    ) || 0;

  const totalCajaFisica =
    montoApertura +
    efectivo -
    egresosEfectivo;

  $("#montoAperturaCard").text(
    "S/ " +
    formatearMonto(
      montoApertura
    )
  );

  $("#totalIngresos").text(
    "S/ " +
    formatearMonto(
      ingresos
    )
  );

  $("#totalEgresos").text(
    "S/ " +
    formatearMonto(
      egresos
    )
  );

  /*
   * Total en Caja representa únicamente
   * el efectivo físico esperado.
   * No incluye Yape, tarjetas ni cuentas bancarias.
   */
  $("#totalCaja").text(
    "S/ " +
    formatearMonto(
      totalCajaFisica
    )
  );
}

/*
|--------------------------------------------------------------------------
| CERRAR CAJA
|--------------------------------------------------------------------------
*/
function cerrarCaja() {
  $.ajax({
    url:
      "Controllers/Cajachica.php" +
      "?op=datos_cierre",

    type: "GET",
    dataType: "json",

    success: function (resp) {
      if (!resp.status) {
        Swal.fire(
          "Atención",
          resp.message ||
            "No existe una caja abierta.",
          "warning"
        );

        return;
      }

      const totalSistema =
        parseFloat(
          resp.total_sistema
        ) || 0;

      const montoApertura =
        parseFloat(
          resp.monto_apertura
        ) || 0;

      const ventasEfectivo =
        parseFloat(
          resp.ventas_efectivo
        ) || 0;

      const otrosIngresos =
        parseFloat(
          resp.otros_ingresos_efectivo
        ) || 0;

      const egresosEfectivo =
        parseFloat(
          resp.egresos_efectivo
        ) || 0;

      Swal.fire({
        title:
          "Arqueo de Caja",

        html: `
          <div class="text-left">

            <div class="mb-2">
              <strong>Apertura:</strong>
              S/ ${formatearMonto(
                montoApertura
              )}
            </div>

            <div class="mb-2">
              <strong>Ventas en efectivo:</strong>
              S/ ${formatearMonto(
                ventasEfectivo
              )}
            </div>

            <div class="mb-2">
              <strong>Otros ingresos en efectivo:</strong>
              S/ ${formatearMonto(
                otrosIngresos
              )}
            </div>

            <div class="mb-3">
              <strong>Egresos en efectivo:</strong>
              S/ ${formatearMonto(
                egresosEfectivo
              )}
            </div>

            <label>
              Efectivo esperado en gaveta
            </label>

            <input
              type="text"
              class="swal2-input"
              value="S/ ${formatearMonto(
                totalSistema
              )}"
              readonly>

            <label>
              Efectivo contado físicamente
            </label>

            <input
              type="number"
              step="0.01"
              min="0"
              id="montoContado"
              class="swal2-input"
              placeholder="0.00">

            <div
              id="diferenciaBox"
              style="
                margin-top:10px;
                font-weight:bold;
              ">
            </div>

          </div>
        `,

        showCancelButton: true,
        confirmButtonText:
          "Cerrar Caja",
        cancelButtonText:
          "Cancelar",

        didOpen: function () {
          const input =
            document.getElementById(
              "montoContado"
            );

          const diferenciaBox =
            document.getElementById(
              "diferenciaBox"
            );

          input.addEventListener(
            "input",
            function () {
              const contado =
                parseFloat(
                  this.value || 0
                ) || 0;

              const diferencia =
                contado -
                totalSistema;

              let color =
                "green";

              let texto =
                "Cuadre exacto";

              if (diferencia > 0) {
                color =
                  "orange";

                texto =
                  "Sobrante";
              } else if (
                diferencia < 0
              ) {
                color =
                  "red";

                texto =
                  "Faltante";
              }

              diferenciaBox.innerHTML = `
                ${texto}:
                <span style="color:${color}">
                  S/ ${formatearMonto(
                    diferencia
                  )}
                </span>
              `;
            }
          );
        },

        preConfirm: function () {
          const campo =
            document.getElementById(
              "montoContado"
            );

          const valor =
            String(
              campo.value || ""
            ).trim();

          if (valor === "") {
            Swal.showValidationMessage(
              "Ingrese el monto contado."
            );

            return false;
          }

          const montoContado =
            parseFloat(valor);

          if (
            !Number.isFinite(
              montoContado
            ) ||
            montoContado < 0
          ) {
            Swal.showValidationMessage(
              "Ingrese un monto válido."
            );

            return false;
          }

          return {
            montoContado:
              montoContado,
          };
        },
      }).then(function (resultado) {
        if (!resultado.isConfirmed) {
          return;
        }

        registrarCierreCaja(
          resultado.value
            .montoContado
        );
      });
    },

    error: function (xhr) {
      console.error(
        "Error en datos de cierre:",
        xhr.responseText
      );

      Swal.fire(
        "Error",
        "No se pudieron obtener los datos del cierre.",
        "error"
      );
    },
  });
}

/*
|--------------------------------------------------------------------------
| REGISTRAR CIERRE
|--------------------------------------------------------------------------
*/
function registrarCierreCaja(
  montoContado
) {
  const boton =
    $("#btnCerrarCaja");

  boton.prop(
    "disabled",
    true
  );

  $.ajax({
    url:
      "Controllers/Cajachica.php" +
      "?op=cerrar_caja",

    type: "POST",
    dataType: "json",

    data: {
      monto_contado:
        montoContado,
    },

    success: function (resp) {
      if (resp.status === "ok") {
        let mensaje =
          "Total del sistema: S/ " +
          formatearMonto(
            resp.total_sistema
          );

        mensaje +=
          "\nMonto contado: S/ " +
          formatearMonto(
            resp.monto_contado
          );

        mensaje +=
          "\nDiferencia: S/ " +
          formatearMonto(
            resp.diferencia
          );

        Swal.fire({
          icon: "success",
          title:
            "Caja cerrada correctamente",
          text: mensaje,
        }).then(function () {
          window.location.reload();
        });

        return;
      }

      Swal.fire(
        "Error",
        resp.message ||
          "No se pudo cerrar la caja.",
        "error"
      );
    },

    error: function (xhr) {
      console.error(
        "Error al cerrar caja:",
        xhr.responseText
      );

      Swal.fire(
        "Error",
        "No se pudo registrar el cierre.",
        "error"
      );
    },

    complete: function () {
      boton.prop(
        "disabled",
        false
      );
    },
  });
}

/*
|--------------------------------------------------------------------------
| UTILIDADES
|--------------------------------------------------------------------------
*/
function formatearMonto(
  monto
) {
  return (
    parseFloat(monto) || 0
  ).toLocaleString(
    "es-PE",
    {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }
  );
}

function escaparHtml(
  texto
) {
  return String(
    texto || ""
  )
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}