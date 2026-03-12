//funcion que se ejecuta al inicio
function init() {
    listar();
  }
  
  $("#btnagregar").on("click", function () {
    $(location).attr("href", "newsale");
  });
  
  //funcion listar
  function listar() {
    tabla = $("#tbllistado")
      .dataTable({
        aProcessing: true,
        aServerSide: true,
        dom: "Bfrtip",
        buttons: [
          {
            extend: "excelHtml5",
            text: '<i class="fa fa-file-excel-o bg-green"></i> Excel',
            titleAttr: "Exportar a Excel",
            title: "Reporte de Cotizaciones",
            sheetName: "Cotizaciones",
            exportOptions: {
              columns: [1, 2, 3, 4, 5, 6, 7],
            },
          },
          {
            extend: "pdfHtml5",
            text: '<i class="fa fa-file-pdf-o bg-red"></i> PDF',
            titleAttr: "Exportar a PDF",
            title: "Reporte de Cotizaciones",
            pageSize: "A4",
            exportOptions: {
              columns: [1, 2, 3, 4, 5, 6, 7],
            },
          },
        ],
        ajax: {
          url: "Controllers/Sell.php?op=listarCotizaciones",
          type: "get",
          dataType: "json",
          error: function (e) {
            console.log(e.responseText);
          },
        },
        bDestroy: true,
        iDisplayLength: 10,
        order: [[0, "desc"]],
      })
      .DataTable();
  }
  
  function mostrar(idventa) {
    $("#getCodeModal").modal("show");
  
    // CABECERA
    $.post(
      "Controllers/Sell.php?op=mostrar",
      { idventa },
      function (data) {
        data = JSON.parse(data);
        console.log("COTIZACION:", data);
  
        $("#cliente").val(data.cliente);
        $("#tipo_comprobantem").val(data.tipo_comprobante);
        $("#serie_comprobantem").val(data.serie_comprobante);
        $("#num_comprobantem").val(data.num_comprobante);
        $("#fecha_horam").val(data.fecha);
        $("#impuestom").val(data.impuesto ?? 0);
        $("#idventam").val(data.idventa);
  
        $("#tipo_pagom").val(data.tipo_pago ?? "No especificado");
  
        $("#condicion_pagom").val("");
        $("#detallePagom").empty();
        $("#bloquePagoMixto").hide();
  
        cargarPagosVenta(idventa);
      }
    );
  
    function cargarPagosVenta(idventa) {
      $.getJSON(
        "Controllers/Sell.php?op=pagos&idventa=" + idventa,
        function (pagos) {
          let tbody = $("#detallePagom");
          tbody.empty();
  
          if (!pagos || pagos.length === 0) {
            $("#condicion_pagom").val("CRÉDITO");
            $("#bloquePagoMixto").hide();
            return;
          }
  
          $("#condicion_pagom").val("CONTADO");
  
          if (pagos.length > 1) {
            $("#bloquePagoMixto").show();
  
            pagos.forEach((p) => {
              tbody.append(`
                <tr>
                  <td>${p.nombre}</td>
                  <td class="text-right">S/ ${parseFloat(p.monto).toFixed(2)}</td>
                </tr>
              `);
            });
          } else {
            $("#bloquePagoMixto").hide();
          }
        }
      );
    }
  
    // DETALLE PRODUCTOS
    $.post(
      "Controllers/Sell.php?op=listarDetalle&id=" + idventa,
      function (r) {
        $("#detallesm").html(r);
      }
    );
  }
  
  function anular(idventa) {
    swal({
      title: "Anular?",
      text: "Está seguro de anular la cotización?",
      icon: "warning",
      buttons: {
        cancel: "No, cancelar",
        confirm: "Sí, anular",
      },
      dangerMode: true,
    }).then((willDelete) => {
      if (willDelete) {
        $.post(
          "Controllers/Sell.php?op=anular",
          { idventa: idventa },
          function (e) {
            swal(e, "Desactivado!", {
              icon: "success",
            });
            var tabla = $("#tbllistado").DataTable();
            tabla.ajax.reload();
          }
        );
      }
    });
  }
  
  init();