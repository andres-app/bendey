$(document).ready(function () {
    verificarAperturaCaja();
});

function verificarAperturaCaja() {

    $.getJSON(
        'Controllers/Cajachica.php?op=verificar_apertura',
        function (resp) {
            if (resp.existe === false) {
                $('#modalCajaChica').modal('show');
            }
        }
    );
}

function guardarAperturaCaja() {

    let monto = $('#montoApertura').val();

    if (!monto || parseFloat(monto) <= 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Monto inválido'
        });
        return;
    }

    $.post(
        'Controllers/Cajachica.php?op=guardar_apertura',
        { monto },
        function (resp) {

            let r = JSON.parse(resp);

            if (r.status === 'ok') {
                $('#modalCajaChica').modal('hide');
            }
        }
    );
}
