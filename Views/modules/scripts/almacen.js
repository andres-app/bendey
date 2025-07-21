$(document).ready(function () {
    listar();
});

function listar() {
    $('#tbllistado').DataTable({
        "ajax": {
            url: 'Controllers/Almacen.php?op=listar',
            type: "GET",
            dataType: "json",
            error: function (e) {
                console.error("Error cargando almacenes:", e.responseText);
            }
        },
        "destroy": true,
        "responsive": true,
        "order": [[0, "desc"]],
        "language": {
            "lengthMenu": "Mostrar _MENU_ registros",
            "zeroRecords": "No se encontraron resultados",
            "info": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "infoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
            "infoFiltered": "(filtrado de un total de _MAX_ registros)",
            "search": "Buscar:",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            },
        }
    });
}

