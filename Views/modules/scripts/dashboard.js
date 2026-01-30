"use strict";
function init() {
  cuadros1();
  cuadros2();
  compra6meses();
  venta12meses();
  cat_vendidas();
  stockPorCategoria();
}

function cuadros1() {
  $.post("Controllers/Dashboard.php?op=cuadros1", function (data, status) {
    data = JSON.parse(data);
    //console.log(data.totalcomprahoy);
    //COMPRAS
    $("#tcomprahoy").html(data.totalcomprahoy);
    //VENTAS
    $("#tventahoy").html(data.totalventahoy);
    //CLIENTES
    $("#tclientes").html(data.cantidadclientes);
    //PROVEEDORES
    $("#tproveedores").html(data.cantidadproveedores);
  });
}
function cuadros2() {
  $.post("Controllers/Dashboard.php?op=cuadros2", function (data, status) {
    data = JSON.parse(data);
    //console.log(data.totalcomprahoy);
    //CATEGORIAS
    $("#tcategorias").html(data.cantidadcategorias);
    //ALAMACEN
    $("#tarticulos").html(data.cantidadarticulos);
  });
}

function calcularEscala(maxValor) {
  if (maxValor <= 1000) return 200;
  if (maxValor <= 5000) return 1000;
  if (maxValor <= 10000) return 2000;
  if (maxValor <= 20000) return 5000;
  return 10000; // para valores grandes
}


//COMPRA DE LOS ULTIMOS 6 MESES
function compra6meses() {
  $.post("Controllers/Dashboard.php?op=compras10dias", function (data, status) {
    data = JSON.parse(data);

    const MAX_MESES = 6;
    data.fechas = data.fechas.slice(-MAX_MESES);
    data.totales = data.totales.slice(-MAX_MESES);

    const maxValor = Math.max(...data.totales);
    const step = calcularEscala(maxValor);
    const maxEjeY = Math.ceil(maxValor / step) * step;

    const ctx = document.getElementById("compra6meses").getContext("2d");

    if (window.compraChart) {
      window.compraChart.destroy();
    }

    window.compraChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: data.fechas,
        datasets: [{
          label: "Compras",
          data: data.totales,
          backgroundColor: "#4F46E5",
          hoverBackgroundColor: "#6366F1",
          borderRadius: 10,
          barThickness: 38
        }]
      },
      options: {
        responsive: true, // ✅ OK
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: ctx => " S/ " + ctx.parsed.y.toLocaleString()
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: maxEjeY,
            ticks: {
              stepSize: step,
              maxTicksLimit: 6,
              color: "#6B7280",
              callback: v => "S/ " + v.toLocaleString()
            },
            grid: {
              color: "rgba(0,0,0,0.06)"
            }
          },
          x: {
            ticks: {
              color: "#6B7280"
            },
            grid: {
              display: false
            }
          }
        }
      }
    });
  });
}

//VENTAS DE LOS ULTIMOS 12 MESES
function venta12meses() {
  $.post("Controllers/Dashboard.php?op=ventas12meses", function (data, status) {
    data = JSON.parse(data);

    // ===============================
    // CONFIGURACIÓN
    // ===============================
    const MAX_MESES = 12;

    // Limitar a los últimos 12 meses
    data.fechas = data.fechas.slice(-MAX_MESES);
    data.totales = data.totales.slice(-MAX_MESES);

    // Escala inteligente
    const maxValor = Math.max(...data.totales);
    const step = calcularEscala(maxValor);
    const maxEjeY = Math.ceil(maxValor / step) * step;

    const ctx = document.getElementById("venta12meses").getContext("2d");

    // Evitar duplicar gráficos
    if (window.ventaChart) {
      window.ventaChart.destroy();
    }

    window.ventaChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: data.fechas,
        datasets: [
          {
            label: "Ventas",
            data: data.totales,
            backgroundColor: "#10B981",      // Emerald elegante
            hoverBackgroundColor: "#34D399",
            borderRadius: 10,
            barThickness: 28
          }
        ]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: ctx => " S/ " + ctx.parsed.y.toLocaleString()
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: maxEjeY,
            ticks: {
              stepSize: step,
              maxTicksLimit: 6,
              color: "#6B7280",
              callback: v => "S/ " + v.toLocaleString()
            },
            grid: {
              color: "rgba(0,0,0,0.06)"
            }
          },
          x: {
            ticks: {
              color: "#6B7280"
            },
            grid: {
              display: false
            }
          }
        }
      }
    });
  });
}


function cat_vendidas() {
  $.post(
    "Controllers/Dashboard.php?op=cateogriasMasVendidas",
    function (data, status) {
      data = JSON.parse(data);

      // ===============================
      // CONFIGURACIÓN
      // ===============================
      const MAX_CATEGORIAS = 5;

      // Ordenar por cantidad descendente
      data.sort((a, b) => parseFloat(b.cantidad) - parseFloat(a.cantidad));

      // Tomar Top 5
      const topCategorias = data.slice(0, MAX_CATEGORIAS);

      // Construir datos
      const datos = topCategorias.map(item => ({
        name: item.categoria,
        y: parseFloat(item.cantidad)
      }));

      Highcharts.chart("cat_mas_vendidas", {
        chart: {
          type: "pie"
        },
        title: {
          text: "Top 5 Categorías más vendidas"
        },
        tooltip: {
          pointFormat: "<b>{point.percentage:.1f}%</b>"
        },
        plotOptions: {
          pie: {
            allowPointSelect: true,
            cursor: "pointer",
            dataLabels: {
              enabled: false
            },
            showInLegend: true
          }
        },
        series: [
          {
            name: "Ventas",
            data: datos,
            colors: [
              '#A5B4FC', // Indigo pastel
              '#6EE7B7', // Mint pastel
              '#FDE68A', // Amber pastel
              '#FBCFE8', // Rose pastel
              '#BFDBFE'  // Blue pastel
            ]
          }
        ]
      });
    }
  );
}


function stockPorCategoria() {
  $.get("Controllers/Dashboard.php?op=stockCategoria", function (data) {

    const MAX_CATEGORIAS = 6;

    data.sort((a, b) => parseInt(b.stock_total) - parseInt(a.stock_total));
    const topCategorias = data.slice(0, MAX_CATEGORIAS);

    const categorias = topCategorias.map(item => item.categoria);
    const stock = topCategorias.map(item => parseInt(item.stock_total));

    Highcharts.chart('stock_por_categorias', {
      chart: { type: 'column' },
      title: { text: 'Stock por Categoría' },
      xAxis: {
        categories: categorias,
        crosshair: true,
        labels: { style: { color: '#6B7280' } }
      },
      yAxis: {
        min: 0,
        title: {
          text: 'Unidades en stock',
          style: { color: '#6B7280' }
        }
      },
      tooltip: {
        pointFormat: '<b>{point.y}</b> unidades'
      },
      plotOptions: {
        column: {
          borderRadius: 6,
          pointPadding: 0.1,
          groupPadding: 0.15
        }
      },
      series: [{
        name: 'Stock',
        data: stock,
        colorByPoint: true,
        colors: [
          '#BFDBFE',
          '#A7F3D0',
          '#FDE68A',
          '#FBCFE8',
          '#DDD6FE',
          '#FED7AA'
        ]
      }]
    });

  }, "json");
}

init();
