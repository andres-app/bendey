$(document).ready(function () {
    $('#tbllistado').DataTable({
        ajax: {
            url: 'Controllers/Sunat.php?op=listar',
            type: 'GET',
            dataType: 'json',
            error: function (e) {
                console.log(e.responseText);
            }
        },
        columns: [
            { data: "0" }, // Botón opciones
            { data: "1" }, // Comprobante
            { data: "2" }, // Cliente
            { data: "3" }, // Total
            { data: "4" }, // XML
            { data: "5" }, // Estado SUNAT
            { data: "6" }  // Fecha
        ],
        language: {
            "lengthMenu": "Mostrar _MENU_ registros",
            "zeroRecords": "No se encontraron resultados",
            "info": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "infoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
            "infoFiltered": "(filtrado de un total de _MAX_ registros)",
            "sSearch": "Buscar:",
            "oPaginate": {
                "sFirst": "Primero",
                "sLast":"Último",
                "sNext":"Siguiente",
                "sPrevious": "Anterior"
            },
            "sProcessing":"Procesando...",
        }
    });
});

function verDetalle(idventa) {
    console.log('verDetalle recibe:', idventa); // 👈 DEBUG

    Swal.fire({
        title: 'Comprobante SUNAT',
        text: '¿Qué deseas hacer?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Generar XML',
        cancelButtonText: 'Cancelar'
    }).then((result) => {

        if (result.isConfirmed) {
            generarXML(idventa);
        }

    });
}

function generarXML(idventa) {

    console.log('ID enviado:', idventa); // 👈 DEBUG

    Swal.fire({
        title: 'Generando XML',
        text: 'Por favor espera...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.post(
        'Controllers/Sunat.php?op=generarxml',
        { idventa: idventa }, // 👈 CLAVE
        function (response) {

            Swal.close();
            console.log(response); // 👈 DEBUG

            if (response.status) {
                Swal.fire('Éxito', response.message, 'success');
                $('#tbllistado').DataTable().ajax.reload(null, false);
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        'json'
    );
}