"use strict";

let graficoCompras = null;
let graficoVentas = null;
let graficoResumenCompras = null;
let graficoResumenVentas = null;

function init() {
  compras_grafica();
  ventas_grafica();
  resumen_compras();
  resumen_ventas();
}

function compras_grafica() {
  $.post(
    "Controllers/Graphics.php?op=compras_grafica",
    function (data) {
      const respuesta =
        normalizarGraphicsJson(data);

      const canvas =
        document.getElementById(
          "compras_grafica"
        );

      if (!canvas) {
        return;
      }

      if (graficoCompras) {
        graficoCompras.destroy();
      }

      graficoCompras =
        new Chart(canvas, {
          type: "line",
          data: {
            labels:
              Array.isArray(
                respuesta.fechas
              )
                ? respuesta.fechas
                : [],
            datasets: [
              {
                label: "Compras",
                data:
                  normalizarSerie(
                    respuesta.totales
                  ),
                backgroundColor:
                  "transparent",
                borderColor:
                  "#f96332",
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor:
                  "#f96332",
              },
            ],
          },
          options: {
            responsive: true,
            tooltips: {
              mode: "index",
              intersect: false,
              callbacks: {
                label: function (
                  tooltipItem,
                  chartData
                ) {
                  const etiqueta =
                    chartData.datasets[
                      tooltipItem
                        .datasetIndex
                    ].label || "";

                  return (
                    etiqueta
                    + ": S/ "
                    + formatearMontoGraphics(
                        tooltipItem.yLabel
                      )
                  );
                },
              },
            },
            legend: {
              display: false,
            },
            scales: {
              xAxes: [
                {
                  gridLines: {
                    display: false,
                  },
                },
              ],
              yAxes: [
                {
                  ticks: {
                    beginAtZero: true,
                    callback:
                      function (value) {
                        return (
                          "S/ "
                          + Number(value)
                            .toLocaleString(
                              "es-PE"
                            )
                        );
                      },
                  },
                },
              ],
            },
          },
        });
    }
  );
}

function ventas_grafica() {
  $.post(
    "Controllers/Graphics.php?op=ventas_grafica",
    function (data) {
      const respuesta =
        normalizarGraphicsJson(data);

      const canvas =
        document.getElementById(
          "ventas_grafica"
        );

      if (!canvas) {
        return;
      }

      if (graficoVentas) {
        graficoVentas.destroy();
      }

      graficoVentas =
        new Chart(canvas, {
          type: "line",
          data: {
            labels:
              Array.isArray(
                respuesta.fechas
              )
                ? respuesta.fechas
                : [],
            datasets: [
              {
                label:
                  "Ventas brutas",
                data:
                  normalizarSerie(
                    respuesta
                      .ventas_brutas
                  ),
                backgroundColor:
                  "transparent",
                borderColor:
                  "#6B7280",
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor:
                  "#6B7280",
              },
              {
                label:
                  "Notas de crédito",
                data:
                  normalizarSerie(
                    respuesta
                      .notas_credito
                  ),
                backgroundColor:
                  "transparent",
                borderColor:
                  "#EF4444",
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor:
                  "#EF4444",
              },
              {
                label:
                  "Ventas netas",
                data:
                  normalizarSerie(
                    respuesta.totales
                  ),
                backgroundColor:
                  "transparent",
                borderColor:
                  "#10B981",
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor:
                  "#10B981",
              },
            ],
          },
          options: {
            responsive: true,
            tooltips: {
              mode: "index",
              intersect: false,
              callbacks: {
                label: function (
                  tooltipItem,
                  chartData
                ) {
                  const etiqueta =
                    chartData.datasets[
                      tooltipItem
                        .datasetIndex
                    ].label || "";

                  return (
                    etiqueta
                    + ": S/ "
                    + formatearMontoGraphics(
                        tooltipItem.yLabel
                      )
                  );
                },
              },
            },
            legend: {
              display: true,
              position: "bottom",
            },
            scales: {
              xAxes: [
                {
                  gridLines: {
                    display: false,
                  },
                },
              ],
              yAxes: [
                {
                  ticks: {
                    beginAtZero: true,
                    callback:
                      function (value) {
                        return (
                          "S/ "
                          + Number(value)
                            .toLocaleString(
                              "es-PE"
                            )
                        );
                      },
                  },
                },
              ],
            },
          },
        });
    }
  );
}

function resumen_compras() {
  $.post(
    "Controllers/Graphics.php?op=resumen_compras",
    function (data) {
      const respuesta =
        normalizarGraphicsJson(data);

      const canvas =
        document.getElementById(
          "resumen_compras"
        );

      if (!canvas) {
        return;
      }

      if (graficoResumenCompras) {
        graficoResumenCompras.destroy();
      }

      graficoResumenCompras =
        new Chart(canvas, {
          type: "pie",
          data: {
            datasets: [
              {
                data:
                  normalizarSerie(
                    respuesta.totales
                  ),
                backgroundColor: [
                  "#191d21",
                  "#63ed7a",
                  "#ffa426",
                  "#fc544b",
                  "#6777ef",
                  "#3abaf4",
                  "#f59e0b",
                  "#8b5cf6",
                  "#14b8a6",
                  "#64748b",
                  "#ec4899",
                  "#22c55e",
                ],
              },
            ],
            labels:
              Array.isArray(
                respuesta.fechas
              )
                ? respuesta.fechas
                : [],
          },
          options: {
            responsive: true,
            legend: {
              position: "bottom",
            },
          },
        });
    }
  );
}

function resumen_ventas() {
  $.post(
    "Controllers/Graphics.php?op=resumen_ventas",
    function (data) {
      const respuesta =
        normalizarGraphicsJson(data);

      const ventasBrutas =
        normalizarSerie(
          respuesta.ventas_brutas
        );

      const notasCredito =
        normalizarSerie(
          respuesta.notas_credito
        );

      const ventasNetas =
        normalizarSerie(
          respuesta.totales
        );

      const totalBruto =
        sumarSerie(ventasBrutas);

      const totalNotas =
        sumarSerie(notasCredito);

      const totalNeto =
        sumarSerie(ventasNetas);

      $("#graficaVentasBrutas").text(
        "S/ "
        + formatearMontoGraphics(
          totalBruto
        )
      );

      $("#graficaNotasCredito").text(
        "- S/ "
        + formatearMontoGraphics(
          totalNotas
        )
      );

      $("#graficaVentasNetas")
        .toggleClass(
          "text-danger",
          totalNeto < 0
        )
        .text(
          (totalNeto < 0
            ? "- S/ "
            : "S/ ")
          + formatearMontoGraphics(
              Math.abs(totalNeto)
            )
        );

      const canvas =
        document.getElementById(
          "resumen_ventas"
        );

      if (!canvas) {
        return;
      }

      if (graficoResumenVentas) {
        graficoResumenVentas.destroy();
      }

      graficoResumenVentas =
        new Chart(canvas, {
          type: "doughnut",
          data: {
            datasets: [
              {
                data: ventasNetas,
                backgroundColor: [
                  "#10B981",
                  "#34D399",
                  "#6EE7B7",
                  "#A7F3D0",
                  "#059669",
                  "#047857",
                  "#065F46",
                  "#14B8A6",
                  "#2DD4BF",
                  "#5EEAD4",
                  "#0F766E",
                  "#115E59",
                ],
              },
            ],
            labels:
              Array.isArray(
                respuesta.fechas
              )
                ? respuesta.fechas
                : [],
          },
          options: {
            responsive: true,
            legend: {
              position: "bottom",
            },
            tooltips: {
              callbacks: {
                label: function (
                  tooltipItem,
                  chartData
                ) {
                  const indice =
                    tooltipItem.index;

                  const etiqueta =
                    chartData.labels[
                      indice
                    ] || "";

                  const valor =
                    chartData.datasets[0]
                      .data[indice] || 0;

                  return (
                    etiqueta
                    + ": S/ "
                    + formatearMontoGraphics(
                        valor
                      )
                  );
                },
              },
            },
          },
        });
    }
  );
}

function normalizarGraphicsJson(
  data
) {
  if (
    data
    && typeof data === "object"
  ) {
    return data;
  }

  try {
    return JSON.parse(data);
  } catch (error) {
    console.error(
      "Respuesta JSON inválida:",
      error,
      data
    );

    return {};
  }
}

function normalizarSerie(
  valores
) {
  if (!Array.isArray(valores)) {
    return [];
  }

  return valores.map(function (valor) {
    return parseFloat(valor) || 0;
  });
}

function sumarSerie(
  valores
) {
  return valores.reduce(
    function (acumulado, valor) {
      return acumulado
        + (parseFloat(valor) || 0);
    },
    0
  );
}

function formatearMontoGraphics(
  valor
) {
  return (
    parseFloat(valor) || 0
  ).toLocaleString(
    "es-PE",
    {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }
  );
}

init();
