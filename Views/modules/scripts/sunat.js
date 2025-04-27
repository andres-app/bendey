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

// Acción del botón "Ver"
function verDetalle(idventa) {
    Swal.fire({
        title: 'Opciones SUNAT',
        text: "¿Qué quieres hacer con el comprobante?",
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Descargar XML',
        denyButtonText: 'Enviar a SUNAT',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open('download_xml.php?idventa=' + idventa, '_blank');
        } else if (result.isDenied) {
            window.location.href = 'send_sunat.php?idventa=' + idventa;
        }
    });
}
