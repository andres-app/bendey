$(document).ready(function () {
    $('#tbllistado').DataTable({
        responsive: true,
        autoWidth: false,
        scrollX: false,

        // Mantiene el orden recibido desde PHP:
        // último comprobante emitido primero.
        order: [],

        columnDefs: [
            {
                targets: [0, 4, 5, 6],
                orderable: false
            }
        ],

        ajax: {
            url: "Controllers/Sunat.php",
            type: "GET",
            dataType: "json",
            cache: false,

            data: function () {
                return {
                    op: "listar",
                    v: Date.now()
                };
            }
        },

        columns: [
            { data: "0", className: "text-center" },
            { data: "1" },
            { data: "2" },
            { data: "3", className: "text-right" },
            { data: "4", className: "text-center" },
            { data: "5", className: "text-center" },
            { data: "6", className: "text-center" },
            { data: "7" },
            { data: "8", className: "text-center" }
        ],

        language: {
            emptyTable: "No hay comprobantes electrónicos registrados",
            processing: "Cargando comprobantes...",
            search: "Buscar:",
            lengthMenu: "Mostrar _MENU_ registros",
            info: "Mostrando _START_ a _END_ de _TOTAL_ comprobantes",
            infoEmpty: "No hay comprobantes disponibles",
            infoFiltered: "(filtrado de _MAX_ comprobantes)",
            zeroRecords: "No se encontraron resultados",
            paginate: {
                first: "Primero",
                last: "Último",
                next: "Siguiente",
                previous: "Anterior"
            }
        }
    });
});

function verDetalle(idventa) {

    $.post(
        'Controllers/Sunat.php?op=detalle',
        { idventa },
        function (r) {

            let botones = {};
            let footer = '';

            // ========= FASE A: TEXTO DEL BOTÓN =========
            if (!r.xml) {
                botones = { confirmButtonText: 'Generar XML' };

            } else if (r.xml && !r.cdr && r.estado !== 'EN_PROCESO') {
                botones = { confirmButtonText: 'Enviar a SUNAT' };

            } else if (r.estado === 'EN_PROCESO') {
                botones = { confirmButtonText: 'Consultar SUNAT' };

            } else {
                botones = { confirmButtonText: 'Ver CDR' };
                footer = `<a href="${r.cdr}" target="_blank">Descargar CDR</a>`;
            }

            Swal.fire({
                title: '📄 Comprobante Electrónico',
                html: `
                    <div style="text-align:left;font-size:13px">
                        <strong>${r.comprobante}</strong><br>
                        Cliente: ${r.cliente}<br>
                        Total: S/ ${r.total}<br>
                        Estado SUNAT: <strong>${r.estado}</strong>
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#4f6bed',
                cancelButtonText: 'Cerrar',
                footer,
                ...botones
            }).then((result) => {

                if (!result.isConfirmed) return;

                // ========= FASE B: ACCIÓN =========
                if (!r.xml) {
                    generarXML(idventa);

                } else if (r.xml && !r.cdr && r.estado !== 'EN_PROCESO') {
                    enviarSunat(idventa);

                } else if (r.estado === 'EN_PROCESO') {
                    consultarEstado(idventa);

                } else {
                    window.open(r.cdr, '_blank');
                }
            });

        },
        'json'
    );
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

function enviarSunat(idventa) {

    Swal.fire({
        title: 'Enviando a SUNAT',
        text: 'Espere un momento...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.post(
        'Controllers/Sunat.php?op=enviarsunat',
        { idventa: idventa },
        function (r) {

            Swal.close();

            if (r.status) {
                Swal.fire('SUNAT', r.message, 'success');
                $('#tbllistado').DataTable().ajax.reload(null, false);
            } else {
                Swal.fire('Error SUNAT', r.message, 'error');
            }

        },
        'json'
    );
}

function consultarEstado(idventa) {

    Swal.fire({
        title: 'Consultando SUNAT',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.post(
        'Controllers/Sunat.php?op=getStatus',
        { idventa },
        function (r) {
            Swal.close();

            if (r.status) {
                Swal.fire('SUNAT', r.mensaje, 'success');
                $('#tbllistado').DataTable().ajax.reload(null, false);
            } else {
                Swal.fire('Error SUNAT', r.mensaje, 'error');
            }
        },
        'json'
    );
}

/*
|--------------------------------------------------------------------------
| ENVIAR MANUALMENTE A APISUNAT
|--------------------------------------------------------------------------
*/
function enviarSunatManual(idventa) {
    idventa = Number.parseInt(
        idventa,
        10
    );

    if (!idventa || idventa <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Venta inválida',
            text: 'No se pudo determinar la venta.'
        });

        return;
    }

    Swal.fire({
        icon: 'question',
        title: 'Enviar comprobante',
        text:
            'El comprobante será enviado a SUNAT mediante APISUNAT.',
        showCancelButton: true,
        confirmButtonText: 'Sí, enviar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        allowOutsideClick: false
    }).then(function (resultado) {
        if (!resultado.isConfirmed) {
            return;
        }

        Swal.fire({
            title: 'Enviando comprobante',
            text: 'Espere mientras APISUNAT recibe el documento.',
            allowOutsideClick: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'Controllers/ApiSunat.php?op=enviar',
            type: 'POST',
            dataType: 'json',
            cache: false,

            data: {
                idventa: idventa
            },

            success: function (respuesta) {
                console.log(
                    'ENVÍO MANUAL APISUNAT:',
                    respuesta
                );

                if (
                    !respuesta
                    || respuesta.success !== true
                ) {
                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo enviar',
                        text: String(
                            respuesta.mensaje
                            || respuesta.message
                            || 'APISUNAT no recibió el comprobante.'
                        )
                    });

                    recargarTablaSunat();
                    return;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Comprobante enviado',
                    text:
                        'APISUNAT recibió el comprobante. ' +
                        'Estado inicial: ' +
                        String(
                            respuesta.status
                            || 'PENDIENTE'
                        )
                }).then(function () {
                    consultarSunatManual(
                        idventa,
                        true
                    );
                });

                recargarTablaSunat();
            },

            error: function (xhr) {
                console.error(
                    'ERROR ENVÍO MANUAL:',
                    xhr.status,
                    xhr.responseText
                );

                let mensaje =
                    'No se pudo completar el envío.';

                if (
                    xhr.responseJSON
                    && typeof xhr.responseJSON.mensaje
                    === 'string'
                ) {
                    mensaje =
                        xhr.responseJSON.mensaje;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error de envío',
                    text: mensaje
                });

                recargarTablaSunat();
            }
        });
    });
}

/*
|--------------------------------------------------------------------------
| CONSULTAR RESPUESTA DE APISUNAT
|--------------------------------------------------------------------------
*/
function consultarSunatManual(
    idventa,
    automatico = false
) {
    idventa = Number.parseInt(
        idventa,
        10
    );

    if (!idventa || idventa <= 0) {
        return;
    }

    const ejecutarConsulta = function () {
        $.ajax({
            url: 'Controllers/ApiSunat.php',
            type: 'GET',
            dataType: 'json',
            cache: false,

            data: {
                op: 'consultar',
                idventa: idventa,
                v: Date.now()
            },

            success: function (respuesta) {
                console.log(
                    'CONSULTA MANUAL APISUNAT:',
                    respuesta
                );

                const estado = String(
                    respuesta.status || ''
                ).toUpperCase();

                if (estado === 'ACEPTADO') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Comprobante aceptado',
                        text:
                            'SUNAT aceptó correctamente el comprobante.'
                    });

                    recargarTablaSunat();
                    return;
                }

                if (
                    estado === 'RECHAZADO'
                    || estado === 'EXCEPCION'
                ) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Comprobante no aceptado',
                        text: String(
                            respuesta.mensaje
                            || respuesta.message
                            || 'Estado: ' + estado
                        )
                    });

                    recargarTablaSunat();
                    return;
                }

                Swal.fire({
                    icon: 'info',
                    title: 'Comprobante en proceso',
                    text:
                        'APISUNAT todavía está procesando el comprobante.'
                });

                recargarTablaSunat();
            },

            error: function (xhr) {
                console.error(
                    'ERROR CONSULTA APISUNAT:',
                    xhr.responseText
                );

                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo consultar',
                    text:
                        'Revise nuevamente el comprobante desde esta pantalla.'
                });
            }
        });
    };

    if (automatico) {
        window.setTimeout(
            ejecutarConsulta,
            4000
        );

        return;
    }

    Swal.fire({
        title: 'Consultando SUNAT',
        allowOutsideClick: false,
        didOpen: function () {
            Swal.showLoading();
            ejecutarConsulta();
        }
    });
}

/*
|--------------------------------------------------------------------------
| RECARGAR TABLA
|--------------------------------------------------------------------------
*/
function recargarTablaSunat() {
    if (
        $.fn.DataTable
        && $.fn.DataTable.isDataTable(
            '#tbllistado'
        )
    ) {
        $('#tbllistado')
            .DataTable()
            .ajax
            .reload(
                null,
                false
            );
    }
}