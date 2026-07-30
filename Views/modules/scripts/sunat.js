"use strict";

let tablaSunat = null;

/*
|--------------------------------------------------------------------------
| INICIALIZACIÓN
|--------------------------------------------------------------------------
*/
$(document).ready(function () {
    inicializarTablaSunat();

    $(document).on(
        "click",
        "#btnActualizarSunat",
        function () {
            recargarTablaSunat(true);
        }
    );
});

/*
|--------------------------------------------------------------------------
| TABLA DE COMPROBANTES
|--------------------------------------------------------------------------
*/
function inicializarTablaSunat() {
    tablaSunat = $("#tbllistado").DataTable({
        responsive: true,
        autoWidth: false,
        scrollX: false,
        processing: true,

        /*
         * El controlador ya devuelve el comprobante más reciente primero.
         */
        order: [],

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
            },

            error: function (xhr) {
                console.error(
                    "ERROR LISTADO SUNAT:",
                    xhr.status,
                    xhr.responseText
                );

                Swal.fire({
                    icon: "error",
                    title: "No se pudo cargar",
                    text:
                        "No fue posible obtener los comprobantes SUNAT."
                });
            }
        },

        columns: [
            {
                data: "0",
                responsivePriority: 2
            },
            {
                data: "1",
                responsivePriority: 4
            },
            {
                data: "2",
                className: "text-right",
                responsivePriority: 5
            },
            {
                data: "3",
                className: "text-center",
                orderable: false,
                searchable: false,
                responsivePriority: 7
            },
            {
                data: "4",
                className: "text-center",
                orderable: false,
                searchable: false,
                responsivePriority: 8
            },
            {
                data: "5",
                className: "text-center",
                orderable: false,
                responsivePriority: 3
            },
            {
                data: "6",
                responsivePriority: 9
            },
            {
                data: "7",
                className: "text-center",
                responsivePriority: 6
            },
            {
                data: "8",
                className: "text-right",
                orderable: false,
                searchable: false,
                responsivePriority: 1
            }
        ],

        language: {
            emptyTable:
                "No hay comprobantes electrónicos registrados",
            processing:
                "Cargando comprobantes...",
            search:
                "Buscar:",
            lengthMenu:
                "Mostrar _MENU_ registros",
            info:
                "Mostrando _START_ a _END_ de _TOTAL_ comprobantes",
            infoEmpty:
                "No hay comprobantes disponibles",
            infoFiltered:
                "(filtrado de _MAX_ comprobantes)",
            zeroRecords:
                "No se encontraron resultados",
            paginate: {
                first: "Primero",
                last: "Último",
                next: "Siguiente",
                previous: "Anterior"
            }
        }
    });
}

/*
|--------------------------------------------------------------------------
| ENVIAR O REINTENTAR
|--------------------------------------------------------------------------
*/
function enviarSunatManual(idventa) {
    const id = Number.parseInt(
        idventa,
        10
    ) || 0;

    if (id <= 0) {
        mostrarErrorSunat(
            "Venta inválida",
            "No se pudo determinar el comprobante."
        );

        return;
    }

    Swal.fire({
        icon: "question",
        title: "Enviar comprobante",
        text:
            "El comprobante será enviado mediante APISUNAT.",
        showCancelButton: true,
        confirmButtonText: "Sí, enviar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
        allowOutsideClick: false
    }).then(function (resultado) {
        if (!resultado.isConfirmed) {
            return;
        }

        Swal.fire({
            title: "Enviando comprobante",
            text:
                "Espere mientras APISUNAT procesa la solicitud.",
            allowOutsideClick: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        $.ajax({
            url:
                "Controllers/Sunat.php" +
                "?op=enviarsunat",

            type: "POST",
            dataType: "json",
            cache: false,

            data: {
                idventa: id
            },

            success: function (respuesta) {
                const resultadoEnvio =
                    respuesta &&
                    typeof respuesta.resultado === "object"
                        ? respuesta.resultado
                        : {};

                const estado = String(
                    resultadoEnvio.status ||
                    resultadoEnvio.estado ||
                    ""
                ).toUpperCase();

                const mensaje = String(
                    respuesta.message ||
                    resultadoEnvio.mensaje ||
                    "APISUNAT no devolvió un mensaje."
                );

                if (
                    !respuesta ||
                    respuesta.status !== true
                ) {
                    Swal.fire({
                        icon: "error",
                        title: "No se pudo enviar",
                        text: mensaje
                    });

                    recargarTablaSunat(false);
                    return;
                }

                Swal.fire({
                    icon: "success",
                    title: "Comprobante enviado",
                    text:
                        mensaje +
                        (
                            estado !== ""
                                ? " Estado: " + estado + "."
                                : ""
                        )
                }).then(function () {
                    recargarTablaSunat(false);

                    if (
                        estado === "PENDIENTE" ||
                        estado === "EN_PROCESO" ||
                        estado === "ENVIADO"
                    ) {
                        window.setTimeout(
                            function () {
                                consultarSunatManual(
                                    id,
                                    true
                                );
                            },
                            3500
                        );
                    }
                });
            },

            error: function (xhr) {
                console.error(
                    "ERROR ENVÍO SUNAT:",
                    xhr.status,
                    xhr.responseText
                );

                Swal.fire({
                    icon: "error",
                    title: "Error de envío",
                    text: obtenerMensajeAjaxSunat(
                        xhr,
                        "No se pudo completar el envío."
                    )
                });

                recargarTablaSunat(false);
            }
        });
    });
}

/*
|--------------------------------------------------------------------------
| CONSULTAR ESTADO ACTUAL
|--------------------------------------------------------------------------
*/
function consultarSunatManual(
    idventa,
    automatico = false
) {
    const id = Number.parseInt(
        idventa,
        10
    ) || 0;

    if (id <= 0) {
        mostrarErrorSunat(
            "Venta inválida",
            "No se pudo determinar el comprobante."
        );

        return;
    }

    if (!automatico) {
        Swal.fire({
            title: "Consultando SUNAT",
            text:
                "Obteniendo el estado actual del comprobante.",
            allowOutsideClick: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });
    }

    $.ajax({
        url:
            "Controllers/Sunat.php" +
            "?op=consultar",

        type: "POST",
        dataType: "json",
        cache: false,

        data: {
            idventa: id
        },

        success: function (respuesta) {
            const resultado =
                respuesta &&
                typeof respuesta.resultado === "object"
                    ? respuesta.resultado
                    : {};

            const estado = String(
                resultado.status ||
                resultado.estado ||
                ""
            ).toUpperCase();

            const mensaje = String(
                respuesta.message ||
                resultado.mensaje ||
                "SUNAT no devolvió información adicional."
            );

            const aceptado =
                estado === "ACEPTADO";

            const rechazado =
                estado === "RECHAZADO" ||
                estado === "EXCEPCION" ||
                estado === "ERROR";

            let icono = "info";
            let titulo = "Estado actualizado";

            if (aceptado) {
                icono = "success";
                titulo = "Comprobante aceptado";
            } else if (rechazado) {
                icono = "error";
                titulo = "Comprobante no aceptado";
            } else if (
                respuesta &&
                respuesta.status !== true
            ) {
                icono = "warning";
                titulo = "No se pudo confirmar el estado";
            }

            Swal.fire({
                icon: icono,
                title: titulo,
                text:
                    (
                        estado !== ""
                            ? "Estado: " + estado + ". "
                            : ""
                    )
                    + mensaje
            });

            recargarTablaSunat(false);
        },

        error: function (xhr) {
            console.error(
                "ERROR CONSULTA SUNAT:",
                xhr.status,
                xhr.responseText
            );

            if (!automatico) {
                Swal.fire({
                    icon: "error",
                    title: "No se pudo consultar",
                    text: obtenerMensajeAjaxSunat(
                        xhr,
                        "Revise nuevamente el comprobante."
                    )
                });
            }

            recargarTablaSunat(false);
        }
    });
}

/*
|--------------------------------------------------------------------------
| RECARGAR TABLA
|--------------------------------------------------------------------------
*/
function recargarTablaSunat(
    mostrarAviso = false
) {
    if (
        !tablaSunat ||
        !$.fn.DataTable.isDataTable(
            "#tbllistado"
        )
    ) {
        return;
    }

    const $boton =
        $("#btnActualizarSunat");

    const contenidoOriginal =
        $boton.html();

    $boton
        .prop("disabled", true)
        .html(
            '<span class="spinner-border ' +
            'spinner-border-sm mr-1"></span>' +
            "Actualizando"
        );

    tablaSunat.ajax.reload(
        function () {
            $boton
                .prop("disabled", false)
                .html(contenidoOriginal);

            if (mostrarAviso) {
                Swal.fire({
                    icon: "success",
                    title: "Tabla actualizada",
                    timer: 1100,
                    showConfirmButton: false
                });
            }
        },
        false
    );
}

/*
|--------------------------------------------------------------------------
| UTILIDADES
|--------------------------------------------------------------------------
*/
function obtenerMensajeAjaxSunat(
    xhr,
    predeterminado
) {
    if (
        xhr.responseJSON &&
        typeof xhr.responseJSON.message === "string"
    ) {
        return xhr.responseJSON.message;
    }

    if (
        xhr.responseJSON &&
        typeof xhr.responseJSON.mensaje === "string"
    ) {
        return xhr.responseJSON.mensaje;
    }

    return predeterminado;
}

function mostrarErrorSunat(
    titulo,
    mensaje
) {
    Swal.fire({
        icon: "error",
        title: titulo,
        text: mensaje
    });
}
