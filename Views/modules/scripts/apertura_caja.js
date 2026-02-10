$(function () {

    console.log("apertura_caja.js cargado");

    // Verificar apertura automática
    setTimeout(function () {

        $.getJSON(
            'Controllers/Cajachica.php?op=verificar_apertura',
            function (resp) {

                console.log("Respuesta verificar:", resp);

                if (!resp.existe) {
                    $('#modalCajaChica').modal('show');
                    $('#montoApertura').focus();
                }

            }
        );

    }, 300);


    // Evento botón abrir caja
    $(document).on('click', '#btnAbrirCaja', function () {

        console.log("Click iniciar caja");

        let monto = $('#montoApertura').val();

        if (!monto || parseFloat(monto) <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Monto inválido',
                text: 'Ingrese un monto válido'
            });
            return;
        }

        $.ajax({
            url: 'Controllers/Cajachica.php?op=guardar_apertura',
            type: 'POST',
            data: { monto: monto },
            success: function (resp) {

                console.log("Respuesta guardar:", resp);

                try {
                    let r = JSON.parse(resp);

                    if (r.status === 'ok') {

                        $('#modalCajaChica').modal('hide');

                        Swal.fire({
                            icon: 'success',
                            title: 'Caja abierta correctamente',
                            timer: 1200,
                            showConfirmButton: false
                        });

                        setTimeout(function () {
                            location.reload();
                        }, 1200);

                    } else {
                        Swal.fire('Error', 'No se pudo guardar', 'error');
                    }

                } catch (e) {
                    console.error(e);
                    Swal.fire('Error', 'Respuesta inválida del servidor', 'error');
                }

            },
            error: function () {
                Swal.fire('Error', 'Fallo en AJAX', 'error');
            }
        });

    });

});
