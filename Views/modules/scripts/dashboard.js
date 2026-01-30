"use strict";
function init() {
  cuadros1();
  cuadros2();
  compra10dias();
  venta12meses();
  cat_vendidas();
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
function compra10dias() {
  $.post("Controllers/Dashboard.php?op=compras10dias", function (data, status) {
    data = JSON.parse(data);

    const MAX_MESES = 6;
    data.fechas = data.fechas.slice(-MAX_MESES);
    data.totales = data.totales.slice(-MAX_MESES);

    const maxValor = Math.max(...data.totales);
    const step = calcularEscala(maxValor);
    const maxEjeY = Math.ceil(maxValor / step) * step;

    const ctx = document.getElementById("compra10dias").getContext("2d");

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
    var ctx = document.getElementById("venta12meses").getContext("2d");
    var myChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: data.fechas,
        datasets: [
          {
            label: "Ventas",
            data: data.totales,
            //borderWidth: 2,
            backgroundColor: [
              "#fc544b",
              "#F4D03F",
              "#63ed7a",
              "#1262F7",
              "#ffa426",
              "#6777ef",
              "#fc544b",
              "#F4D03F",
              "#63ed7a",
              "#1262F7",
              "#ffa426",
              "#6777ef",
            ],
            //borderColor: "#6777ef",
            //borderWidth: 2.5,
            //pointBackgroundColor: "#ffffff",
            //pointRadius: 4,
          },
        ],
      },
      options: {
        legend: {
          display: true,
        },
        scales: {
          yAxes: [
            {
              gridLines: {
                drawBorder: true,
                color: "#f2f2f2",
              },
              ticks: {
                beginAtZero: true,
                stepSize: 1500,
                fontColor: "#9aa0ac", // Font Color
              },
            },
          ],
          xAxes: [
            {
              ticks: {
                display: true,
              },
              gridLines: {
                display: true,
              },
            },
          ],
        },
      },
    });
  });
}

function cat_vendidas() {
  $.post(
    "Controllers/Dashboard.php?op=cateogriasMasVendidas",
    function (data, status) {
      data = JSON.parse(data);
      //console.log(data);
      var cant,
        cat,
        datos = [];
      for (var i = 0; i < data.length; i++) {
        datos.push(
          (cat = {
            name: data[i].categoria,
            y: parseFloat(data[i].cantidad),
          })
        );
      }
      // Build the chart
      Highcharts.chart("cat_mas_vendidas", {
        chart: {
          plotBackgroundColor: null,
          plotBorderWidth: null,
          plotShadow: false,
          type: "pie",
        },
        title: {
          text: "Categorías mas vendias",
        },
        tooltip: {
          pointFormat: "{series.name}: <b>{point.percentage:.1f}%</b>",
        },
        accessibility: {
          point: {
            valueSuffix: "%",
          },
        },
        plotOptions: {
          pie: {
            allowPointSelect: true,
            cursor: "pointer",
            dataLabels: {
              enabled: false,
            },
            showInLegend: true,
          },
        },
        series: [
          {
            name: "Venta",
            colorByPoint: true,
            data: datos,
          },
        ],
      });
    }
  );
}

$.get("Controllers/Dashboard.php?op=stockCategoria", function (data) {
  let categorias = data.map(item => item.categoria);
  let stock = data.map(item => parseInt(item.stock_total));

  Highcharts.chart('stock_por_categorias', {
    chart: {
      type: 'column'
    },
    title: {
      text: 'Stock por Categoría'
    },
    xAxis: {
      categories: categorias,
      crosshair: true
    },
    yAxis: {
      min: 0,
      title: {
        text: 'Unidades en stock'
      }
    },
    series: [{
      name: 'Stock',
      data: stock
    }]
  });
}, "json");


// Ejemplo de configuración de la gráfica de stock por categorías
document.addEventListener('DOMContentLoaded', function () {
  Highcharts.chart('stock_por_categorias', {
    chart: {
      type: 'column'
    },
    title: {
      text: 'Stock por Categorías'
    },
    xAxis: {
      categories: ['Categoría 1', 'Categoría 2', 'Categoría 3'] // Reemplaza con tus categorías
    },
    yAxis: {
      min: 0,
      title: {
        text: 'Stock'
      }
    },
    series: [{
      name: 'Stock',
      data: [29, 71, 106] // Reemplaza con los datos de stock por categoría
    }]
  });
});



init();
