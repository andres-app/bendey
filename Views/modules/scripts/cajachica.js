$(document).ready(function () {

    cargarCaja();

    $('#fecha_inicio, #fecha_fin, #idusuario').on('change', function () {
        cargarCaja();
    });

});

function cargarCaja() {

    let fecha_inicio = $('#fecha_inicio').val();
    let fecha_fin = $('#fecha_fin').val();
    let idusuario = $('#idusuario').val();

    $.getJSON(
        'Controllers/Cajachica.php?op=resumen',
        {
            fecha_inicio,
            fecha_fin,
            idusuario
        },
        function (resp) {
            renderTabla(resp.detalle);
            renderTotales(resp.totales);
        }
    );
}

function renderTabla(data) {

    let filas = {};

    data.forEach(r => {

        if (!filas[r.tipo_comprobante]) {
            filas[r.tipo_comprobante] = {
                efectivo: 0,
                tarjeta: 0,
                transferencia: 0,
                yape: 0,
                plin: 0
            };
        }

        let monto = parseFloat(r.total);
        let forma = r.forma_pago.toLowerCase().trim();

        if (forma.includes('efectivo')) {
            filas[r.tipo_comprobante].efectivo += monto;

        } else if (forma.includes('tarjeta')) {
            // debito + credito
            filas[r.tipo_comprobante].tarjeta += monto;

        } else if (forma.includes('transfer')) {
            filas[r.tipo_comprobante].transferencia += monto;

        } else if (forma.includes('yape')) {
            filas[r.tipo_comprobante].yape += monto;

        } else if (forma.includes('plin')) {
            filas[r.tipo_comprobante].plin += monto;
        }
    });

    let html = '';

    Object.keys(filas).forEach(tc => {

        let f = filas[tc];

        let total =
            f.efectivo +
            f.tarjeta +
            f.transferencia +
            f.yape +
            f.plin;

        html += `
            <tr>
                <td>${tc}</td>
                <td class="text-right">S/ ${f.efectivo.toFixed(2)}</td>
                <td class="text-right">S/ ${f.tarjeta.toFixed(2)}</td>
                <td class="text-right">S/ ${f.transferencia.toFixed(2)}</td>
                <td class="text-right">S/ ${(f.yape + f.plin).toFixed(2)}</td>
                <td class="text-right font-weight-bold">S/ ${total.toFixed(2)}</td>
            </tr>
        `;
    });

    $('#tablaCaja tbody').html(html);
}

function renderTotales(t) {
    let ingresos = parseFloat(t.ingresos || 0);
    $('#totalIngresos').text('S/ ' + ingresos.toFixed(2));
    $('#totalCaja').text('S/ ' + ingresos.toFixed(2));
}
