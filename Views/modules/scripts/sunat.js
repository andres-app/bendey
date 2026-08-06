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
        html:
            '<div style="text-align:left">' +
            '<p>El comprobante será enviado mediante APISUNAT.</p>' +
            '<p class="mb-0"><strong>En un reintento se conserva la serie y el número original.</strong> No se crea otra venta ni se consume otro correlativo.</p>' +
            '</div>',
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
                    mostrarDetalleErrorSunat(
                        "No se pudo enviar",
                        mensaje,
                        resultadoEnvio.faults || [],
                        resultadoEnvio.notes || []
                    );

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

            if (rechazado) {
                mostrarDetalleErrorSunat(
                    titulo,
                    (
                        estado !== ""
                            ? "Estado: " + estado + ". "
                            : ""
                    ) + mensaje,
                    resultado.faults || [],
                    resultado.notes || []
                );
            } else {
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
            }

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
| VER RESPUESTA COMPLETA DE APISUNAT
|--------------------------------------------------------------------------
*/
function verDetalleSunat(idventa) {
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
        title: "Consultando detalle",
        allowOutsideClick: false,
        didOpen: function () {
            Swal.showLoading();
        }
    });

    $.ajax({
        url:
            "Controllers/Sunat.php" +
            "?op=detalle",

        type: "GET",
        dataType: "json",
        cache: false,

        data: {
            idventa: id,
            v: Date.now()
        },

        success: function (respuesta) {
            if (
                !respuesta
                || respuesta.status !== true
            ) {
                mostrarErrorSunat(
                    "No se encontró el detalle",
                    respuesta && respuesta.message
                        ? respuesta.message
                        : "No fue posible cargar la respuesta de APISUNAT."
                );

                return;
            }

            const puedeReintentar =
                respuesta.puede_reintentar === true;

            Swal.fire({
                icon:
                    puedeReintentar
                        ? "error"
                        : (
                            String(respuesta.estado || "")
                                .toUpperCase() === "ACEPTADO"
                                ? "success"
                                : "info"
                        ),
                title:
                    puedeReintentar
                        ? "Comprobante no aceptado"
                        : "Detalle del comprobante",
                html:
                    construirHtmlDetalleSunat(
                        respuesta
                    ),
                showCancelButton: puedeReintentar,
                confirmButtonText:
                    puedeReintentar
                        ? "Reintentar envío"
                        : "Cerrar",
                cancelButtonText: "Cerrar",
                reverseButtons: true,
                width: 720
            }).then(function (resultado) {
                if (
                    puedeReintentar
                    && resultado.isConfirmed
                ) {
                    enviarSunatManual(id);
                }
            });
        },

        error: function (xhr) {
            mostrarErrorSunat(
                "No se pudo cargar",
                obtenerMensajeAjaxSunat(
                    xhr,
                    "No fue posible cargar el detalle del comprobante."
                )
            );
        }
    });
}

function construirHtmlDetalleSunat(
    respuesta
) {
    const estado = String(
        respuesta.estado || "PENDIENTE"
    ).toUpperCase();

    const mensaje = String(
        respuesta.mensaje || "Sin información adicional."
    );

    const partes = [mensaje];

    normalizarMensajesSunatJs(
        respuesta.faults || []
    ).forEach(function (fault) {
        if (!partes.includes(fault)) {
            partes.push(fault);
        }
    });

    normalizarMensajesSunatJs(
        respuesta.notes || []
    ).forEach(function (note) {
        const texto = "Nota: " + note;

        if (!partes.includes(texto)) {
            partes.push(texto);
        }
    });

    return (
        '<div class="sunat-detail-meta">' +
            '<div><span>Comprobante</span><strong>' +
                escaparHtmlSunat(respuesta.comprobante || "—") +
            '</strong></div>' +
            '<div><span>Estado</span><strong>' +
                escaparHtmlSunat(estado) +
            '</strong></div>' +
            '<div><span>Cliente</span><strong>' +
                escaparHtmlSunat(respuesta.cliente || "—") +
            '</strong></div>' +
            '<div><span>Total</span><strong>S/ ' +
                escaparHtmlSunat(respuesta.total || "0.00") +
            '</strong></div>' +
        '</div>' +
        '<div class="sunat-detail-box">' +
            escaparHtmlSunat(
                partes
                    .filter(Boolean)
                    .join("\n\n")
            ) +
        '</div>'
    );
}

function mostrarDetalleErrorSunat(
    titulo,
    mensaje,
    faults,
    notes
) {
    const partes = [
        String(mensaje || "")
    ];

    normalizarMensajesSunatJs(
        faults || []
    ).forEach(function (fault) {
        if (!partes.includes(fault)) {
            partes.push(fault);
        }
    });

    normalizarMensajesSunatJs(
        notes || []
    ).forEach(function (note) {
        const texto = "Nota: " + note;

        if (!partes.includes(texto)) {
            partes.push(texto);
        }
    });

    Swal.fire({
        icon: "error",
        title: titulo,
        html:
            '<div class="sunat-detail-box">' +
            escaparHtmlSunat(
                partes
                    .filter(Boolean)
                    .join("\n\n")
            ) +
            '</div>',
        width: 720
    });
}

function normalizarMensajesSunatJs(
    valor
) {
    const salida = [];

    const recorrer = function (dato) {
        if (
            typeof dato === "string"
            || typeof dato === "number"
        ) {
            const texto = String(dato).trim();

            if (texto !== "") {
                salida.push(texto);
            }

            return;
        }

        if (Array.isArray(dato)) {
            dato.forEach(recorrer);
            return;
        }

        if (
            dato
            && typeof dato === "object"
        ) {
            Object.keys(dato).forEach(function (clave) {
                recorrer(dato[clave]);
            });
        }
    };

    recorrer(valor);

    return salida
        .map(function (texto) {
            return texto.replace(/\s+/g, " ").trim();
        })
        .filter(Boolean)
        .filter(function (texto, indice, arreglo) {
            return arreglo.indexOf(texto) === indice;
        });
}

function escaparHtmlSunat(
    valor
) {
    return String(
        valor == null
            ? ""
            : valor
    )
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
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
