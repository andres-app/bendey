"use strict";

function init() {
  cuadros1();
  cuadros2();

  if (document.getElementById("compra6meses")) {
    compra6meses();
  }

  if (document.getElementById("venta12meses")) {
    venta12meses();
  }

  if (document.getElementById("cat_mas_vendidas")) {
    cat_vendidas();
  }

  if (document.getElementById("stock_por_categorias")) {
    stockPorCategoria();
  }
}

function cuadros1() {
  $.post(
    "Controllers/Dashboard.php?op=cuadros1",
    function (data) {
      const respuesta =
        normalizarRespuestaJson(data);

      $("#tcomprahoy").text(
        formatearMontoDashboard(
          respuesta.totalcomprahoy
        )
      );

      $("#tventahoy").text(
        formatearMontoDashboard(
          respuesta.totalventaneta
          ?? respuesta.totalventahoy
        )
      );

      $("#tventabruta").text(
        formatearMontoDashboard(
          respuesta.totalventabruta
        )
      );

      $("#tnotascredito").text(
        formatearMontoDashboard(
          respuesta.totalnotascredito
        )
      );

      $("#tclientes").text(
        respuesta.cantidadclientes || 0
      );

      $("#tproveedores").text(
        respuesta.cantidadproveedores || 0
      );
    }
  ).fail(function (xhr) {
    console.error(
      "No se pudo cargar cuadros1:",
      xhr.responseText
    );
  });
}

function cuadros2() {
  $.post(
    "Controllers/Dashboard.php?op=cuadros2",
    function (data) {
      const respuesta =
        normalizarRespuestaJson(data);

      $("#tcategorias").text(
        respuesta.cantidadcategorias || 0
      );

      $("#tarticulos").text(
        respuesta.cantidadarticulos || 0
      );
    }
  ).fail(function (xhr) {
    console.error(
      "No se pudo cargar cuadros2:",
      xhr.responseText
    );
  });
}

function calcularEscala(maxValor) {
  const maximo =
    Math.max(
      Number(maxValor) || 0,
      1
    );

  if (maximo <= 1000) return 200;
  if (maximo <= 5000) return 1000;
  if (maximo <= 10000) return 2000;
  if (maximo <= 20000) return 5000;

  return 10000;
}

function compra6meses() {
  $.post(
    "Controllers/Dashboard.php?op=compras10dias",
    function (data) {
      const respuesta =
        normalizarRespuestaJson(data);

      const fechas =
        Array.isArray(respuesta.fechas)
          ? respuesta.fechas.slice(-6)
          : [];

      const totales =
        Array.isArray(respuesta.totales)
          ? respuesta.totales
              .slice(-6)
              .map(Number)
          : [];

      const canvas =
        document.getElementById(
          "compra6meses"
        );

      if (!canvas) {
        return;
      }

      const maxValor =
        Math.max(0, ...totales);

      const step =
        calcularEscala(maxValor);

      const maxEjeY =
        Math.max(
          step,
          Math.ceil(maxValor / step)
            * step
        );

      if (window.compraChart) {
        window.compraChart.destroy();
      }

      window.compraChart =
        new Chart(
          canvas.getContext("2d"),
          {
            type: "bar",
            data: {
              labels: fechas,
              datasets: [
                {
                  label: "Compras",
                  data: totales,
                  backgroundColor:
                    "#4F46E5",
                  hoverBackgroundColor:
                    "#6366F1",
                  borderRadius: 10,
                  barThickness: 38,
                },
              ],
            },
            options: {
              responsive: true,
              plugins: {
                legend: {
                  display: false,
                },
                tooltip: {
                  callbacks: {
                    label: function (ctx) {
                      return (
                        " S/ "
                        + Number(
                          ctx.parsed.y || 0
                        ).toLocaleString(
                          "es-PE"
                        )
                      );
                    },
                  },
                },
              },
              scales: {
                y: {
                  beginAtZero: true,
                  max: maxEjeY,
                  ticks: {
                    stepSize: step,
                    maxTicksLimit: 6,
                    color: "#6B7280",
                    callback: function (v) {
                      return (
                        "S/ "
                        + Number(v)
                          .toLocaleString(
                            "es-PE"
                          )
                      );
                    },
                  },
                },
                x: {
                  grid: {
                    display: false,
                  },
                },
              },
            },
          }
        );
    }
  );
}

function venta12meses() {
  $.post(
    "Controllers/Dashboard.php?op=ventas12meses",
    function (data) {
      const respuesta =
        normalizarRespuestaJson(data);

      const canvas =
        document.getElementById(
          "venta12meses"
        );

      if (!canvas) {
        return;
      }

      const fechas =
        Array.isArray(respuesta.fechas)
          ? respuesta.fechas.slice(-12)
          : [];

      const brutas =
        Array.isArray(
          respuesta.ventas_brutas
        )
          ? respuesta.ventas_brutas
              .slice(-12)
              .map(Number)
          : [];

      const notas =
        Array.isArray(
          respuesta.notas_credito
        )
          ? respuesta.notas_credito
              .slice(-12)
              .map(Number)
          : [];

      const netas =
        Array.isArray(respuesta.totales)
          ? respuesta.totales
              .slice(-12)
              .map(Number)
          : [];

      const maxValor =
        Math.max(
          0,
          ...brutas,
          ...netas
        );

      const step =
        calcularEscala(maxValor);

      const maxEjeY =
        Math.max(
          step,
          Math.ceil(maxValor / step)
            * step
        );

      if (window.ventaChart) {
        window.ventaChart.destroy();
      }

      window.ventaChart =
        new Chart(
          canvas.getContext("2d"),
          {
            type: "bar",
            data: {
              labels: fechas,
              datasets: [
                {
                  label:
                    "Ventas brutas",
                  data: brutas,
                  backgroundColor:
                    "#9CA3AF",
                  borderRadius: 8,
                },
                {
                  label:
                    "Notas de crédito",
                  data: notas,
                  backgroundColor:
                    "#EF4444",
                  borderRadius: 8,
                },
                {
                  label:
                    "Ventas netas",
                  data: netas,
                  backgroundColor:
                    "#10B981",
                  borderRadius: 8,
                },
              ],
            },
            options: {
              responsive: true,
              plugins: {
                legend: {
                  display: true,
                  position: "bottom",
                },
              },
              scales: {
                y: {
                  beginAtZero: true,
                  max: maxEjeY,
                  ticks: {
                    stepSize: step,
                    callback: function (v) {
                      return (
                        "S/ "
                        + Number(v)
                          .toLocaleString(
                            "es-PE"
                          )
                      );
                    },
                  },
                },
                x: {
                  grid: {
                    display: false,
                  },
                },
              },
            },
          }
        );
    }
  );
}

function cat_vendidas() {
  $.post(
    "Controllers/Dashboard.php?op=cateogriasMasVendidas",
    function (data) {
      const respuesta =
        normalizarRespuestaJson(data);

      const registros =
        Array.isArray(respuesta)
          ? respuesta
          : [];

      registros.sort(function (a, b) {
        return (
          parseFloat(b.cantidad || 0)
          - parseFloat(a.cantidad || 0)
        );
      });

      const datos =
        registros
          .slice(0, 5)
          .map(function (item) {
            return {
              name:
                item.categoria,
              y:
                parseFloat(
                  item.cantidad
                ) || 0,
            };
          });

      Highcharts.chart(
        "cat_mas_vendidas",
        {
          chart: {
            type: "pie",
          },
          title: {
            text:
              "Top 5 Categorías más vendidas",
          },
          tooltip: {
            pointFormat:
              "<b>{point.percentage:.1f}%</b>",
          },
          plotOptions: {
            pie: {
              dataLabels: {
                enabled: false,
              },
              showInLegend: true,
            },
          },
          series: [
            {
              name: "Ventas",
              data: datos,
            },
          ],
        }
      );
    }
  );
}

function stockPorCategoria() {
  $.get(
    "Controllers/Dashboard.php?op=stockCategoria",
    function (data) {
      const registros =
        Array.isArray(data)
          ? data
          : [];

      registros.sort(function (a, b) {
        return (
          parseInt(
            b.stock_total || 0,
            10
          )
          - parseInt(
              a.stock_total || 0,
              10
            )
        );
      });

      const top =
        registros.slice(0, 6);

      Highcharts.chart(
        "stock_por_categorias",
        {
          chart: {
            type: "column",
          },
          title: {
            text:
              "Stock por Categoría",
          },
          xAxis: {
            categories:
              top.map(function (item) {
                return item.categoria;
              }),
          },
          yAxis: {
            min: 0,
            title: {
              text:
                "Unidades en stock",
            },
          },
          series: [
            {
              name: "Stock",
              data:
                top.map(function (item) {
                  return (
                    parseInt(
                      item.stock_total || 0,
                      10
                    ) || 0
                  );
                }),
            },
          ],
        }
      );
    },
    "json"
  );
}

function normalizarRespuestaJson(
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

function formatearMontoDashboard(
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
