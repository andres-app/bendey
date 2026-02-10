$(document).ready(function () {

    // Solo se ejecuta en la vista Caja Chica
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

            renderTabla(resp.detalle, resp.apertura);
            renderTotales(resp.totales, resp.apertura);

            // 🔥 CONTROL DE ESTADO
            if (resp.estado === 'CERRADA') {

                $('#btnCerrarCaja')
                    .prop('disabled', true)
                    .removeClass('btn-warning')
                    .addClass('btn-secondary');

                $('#estadoCajaBadge')
                    .removeClass('badge-success')
                    .addClass('badge-danger')
                    .text('Caja Cerrada');

            } else {

                $('#btnCerrarCaja')
                    .prop('disabled', false);

                $('#estadoCajaBadge')
                    .removeClass('badge-danger')
                    .addClass('badge-success')
                    .text('Caja Abierta');
            }

        }
    );
}

function exportarExcel() {

    let fecha_inicio = $('#fecha_inicio').val();
    let fecha_fin = $('#fecha_fin').val();
    let idusuario = $('#idusuario').val();

    let url = 'Reports/ExcelCajaChica.php'
        + '?fecha_inicio=' + fecha_inicio
        + '&fecha_fin=' + fecha_fin
        + '&idusuario=' + idusuario;

    window.open(url, '_blank');
}


function exportarPDF() {

    let fecha_inicio = $('#fecha_inicio').val();
    let fecha_fin = $('#fecha_fin').val();

    let url = 'Reports/caja_chica.php'
        + '?fecha_inicio=' + fecha_inicio
        + '&fecha_fin=' + fecha_fin;

    window.open(url, '_blank');
}


function renderTabla(data, apertura) {

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

    // 🔥 INSERTAR APERTURA PRIMERO
    let montoApertura = parseFloat(apertura?.monto_apertura || 0);

    if (montoApertura > 0) {
        html += `
            <tr class="table-success font-weight-bold">
                <td>APERTURA DE CAJA</td>
                <td class="text-right">S/ ${montoApertura.toFixed(2)}</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right">S/ ${montoApertura.toFixed(2)}</td>
            </tr>
        `;
    }



    // 🔹 FILAS NORMALES
    Object.keys(filas).forEach(tc => {

        let f = filas[tc];
        let total = f.efectivo + f.tarjeta + f.transferencia + f.yape + f.plin;

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

    // 🔥 INSERTAR CIERRE SI LA CAJA ESTÁ CERRADA
    if (apertura?.estado === 'CERRADA') {

        html += `
        <tr class="table-danger font-weight-bold">
            <td>CIERRE DE CAJA</td>
            <td colspan="4" class="text-center">
                Caja Cerrada
            </td>
            <td class="text-right">
                ✔
            </td>
        </tr>
    `;
    }


    $('#tablaCaja tbody').html(html);
}


function renderTotales(t, apertura) {

    let ingresos = parseFloat(t.ingresos || 0);
    let montoApertura = parseFloat(apertura?.monto_apertura || 0);

    // Mostrar apertura
    $('#montoAperturaCard').text('S/ ' + montoApertura.toFixed(2));

    // Mostrar ingresos
    $('#totalIngresos').text('S/ ' + ingresos.toFixed(2));

    // Total real en caja
    let totalCaja = montoApertura + ingresos;

    $('#totalCaja').text('S/ ' + totalCaja.toFixed(2));
}

function cerrarCaja() {

    $.getJSON('Controllers/Cajachica.php?op=datos_cierre', function (resp) {

        if (!resp.status) {
            Swal.fire('Atención', 'La caja esta cerrada', 'error');
            return;
        }

        let totalSistema = parseFloat(resp.total_sistema);

        Swal.fire({
            title: 'Arqueo de Caja',
            html: `
                <div class="text-left">
                    <label>Total Efectivo en Gaveta</label>
                    <input type="text" 
                           class="swal2-input" 
                           value="S/ ${totalSistema.toFixed(2)}" 
                           readonly>

                    <label>Monto efectivo Real</label>
                    <input type="number" 
                           step="0.01" 
                           id="montoContado" 
                           class="swal2-input" 
                           placeholder="0.00">

                    <div id="diferenciaBox" 
                         style="margin-top:10px;font-weight:bold;">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Cerrar Caja',
            didOpen: () => {

                const input = document.getElementById('montoContado');
                const diffBox = document.getElementById('diferenciaBox');

                input.addEventListener('input', function () {
                    let contado = parseFloat(this.value || 0);
                    let diferencia = contado - totalSistema;

                    let color = diferencia == 0
                        ? 'green'
                        : (diferencia > 0 ? 'orange' : 'red');

                    diffBox.innerHTML = `
                        Diferencia: 
                        <span style="color:${color}">
                            S/ ${diferencia.toFixed(2)}
                        </span>
                    `;
                });
            },
            preConfirm: () => {

                let montoContado = document.getElementById('montoContado').value;

                if (!montoContado || parseFloat(montoContado) <= 0) {
                    Swal.showValidationMessage('Ingrese monto válido');
                    return false;
                }

                return {
                    montoContado: parseFloat(montoContado)
                };
            }
        }).then((result) => {

            if (result.isConfirmed) {

                $.post(
                    'Controllers/Cajachica.php?op=cerrar_caja',
                    { monto_contado: result.value.montoContado },
                    function (r) {

                        let res = JSON.parse(r);

                        if (res.status === 'ok') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Caja cerrada correctamente',
                                timer: 1500,
                                showConfirmButton: false
                            });

                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                );

            }

        });

    });

}
