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
//COMPRA DE LOS ULTIMOS 10 DIAS
function compra10dias() {
  $.post("Controllers/Dashboard.php?op=compras10dias", function (data, status) {
    data = JSON.parse(data);
    var ctx = document.getElementById("compra10dias").getContext("2d");
    var myChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: data.fechas,
        datasets: [
          {
            label: "Compras",
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

$.get("Controllers/Dashboard.php?op=stockCategoria", function(data) {
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
