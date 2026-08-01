/*
|--------------------------------------------------------------------------
| DUPLICAR VENTA DESDE EL LISTADO
|--------------------------------------------------------------------------
| Abre una nueva venta editable. No modifica la venta original ni consume
| correlativo hasta que el usuario presiona Procesar.
*/
function duplicarVenta(idventa) {
    idventa = Number.parseInt(idventa, 10);

    if (!idventa || idventa <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Venta inválida',
            text: 'No se pudo determinar la venta que desea duplicar.'
        });

        return;
    }

    Swal.fire({
        icon: 'question',
        title: 'Duplicar venta',
        html:
            '<div style="text-align:left">' +
            '<p>Se abrirá una <strong>nueva venta editable</strong> con el cliente, productos, cantidades, precios y descuento de la venta seleccionada.</p>' +
            '<p>La venta original no será modificada. El nuevo correlativo se asignará únicamente cuando presione <strong>Procesar</strong>.</p>' +
            '</div>',
        showCancelButton: true,
        confirmButtonText: 'Crear venta similar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        allowOutsideClick: false
    }).then(function (resultado) {
        if (!resultado.isConfirmed) {
            return;
        }

        window.location.href =
            'newsale3?duplicar=' + encodeURIComponent(idventa);
    });
}
