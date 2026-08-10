"use strict";

let tablaSunat = null;
let filtroEstadoSunat = "TODOS";
let filtroSunatRegistrado = false;

$(document).ready(function () {
    registrarFiltroEstadosSunat();
    inicializarTablaSunat();

    $(document).on(
        "click",
        "#btnActualizarSunat",
        function () {
            recargarTablaSunat(true);
        }
    );

    $(document).on(
        "click",
        ".sunat-status-filter",
        function () {
            filtroEstadoSunat = String(
                $(this).data("filtro") || "TODOS"
            ).toUpperCase();

            $(".sunat-status-filter")
                .removeClass("is-active");

            $(this).addClass("is-active");

            if (tablaSunat) {
                tablaSunat.draw(false);
            }
        }
    );
});

function registrarFiltroEstadosSunat() {
    if (filtroSunatRegistrado) {
        return;
    }

    $.fn.dataTable.ext.search.push(
        function (settings, data) {
            if (
                !settings
                || !settings.nTable
                || settings.nTable.id !== "tbllistado"
            ) {
                return true;
            }

            if (filtroEstadoSunat === "TODOS") {
                return true;
            }

            const htmlEstado = String(data[3] || "");
            const coincidencia = htmlEstado.match(
                /data-estado=["']([^"']+)["']/i
            );

            const estado = coincidencia
                ? String(coincidencia[1] || "").toUpperCase()
                : normalizarTextoSunat(htmlEstado);

            switch (filtroEstadoSunat) {
                case "PENDIENTES":
                    return estado === "NO_ENVIADO";

                case "PROCESO":
                    return [
                        "PENDIENTE",
                        "EN_PROCESO",
                        "ENVIADO"
                    ].includes(estado);

                case "RECHAZADOS":
                    return [
                        "RECHAZADO",
                        "EXCEPCION",
                        "ERROR"
                    ].includes(estado);

                case "ACEPTADOS":
                    return estado === "ACEPTADO";

                default:
                    return true;
            }
        }
    );

    filtroSunatRegistrado = true;
}

function inicializarTablaSunat() {
    tablaSunat = $("#tbllistado").DataTable({
        responsive: true,
        autoWidth: false,
        scrollX: false,
        processing: true,
        order: [],
        pageLength: 10,

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
                        "No fue posible obtener la bandeja de documentos SUNAT."
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
                responsivePriority: 3
            },
            {
                data: "4",
                className: "text-center",
                responsivePriority: 6
            },
            {
                data: "5",
                className: "text-right",
                orderable: false,
                searchable: false,
                responsivePriority: 1
            }
        ],

        language: {
            emptyTable:
                "No hay documentos electrónicos registrados",
            processing:
                "Cargando documentos...",
            search:
                "Buscar:",
            lengthMenu:
                "Mostrar _MENU_ registros",
            info:
                "Mostrando _START_ a _END_ de _TOTAL_ documentos",
            infoEmpty:
                "No hay documentos disponibles",
            infoFiltered:
                "(filtrado de _MAX_ documentos)",
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

function enviarSunatManual(tipoRegistro, idDocumento) {
    const tipo = normalizarTipoRegistroJs(tipoRegistro);
    const id = Number.parseInt(idDocumento, 10) || 0;

    if (id <= 0) {
        mostrarErrorSunat(
            "Documento inválido",
            "No se pudo determinar el documento electrónico."
        );
        return;
    }

    const esNota = tipo === "NOTA_CREDITO";

    Swal.fire({
        icon: "question",
        title: esNota
            ? "Enviar nota de crédito"
            : "Enviar comprobante",
        html:
            '<div style="text-align:left">' +
            '<p>El documento será enviado mediante APISUNAT.</p>' +
            '<p class="mb-0"><strong>En un reintento se conserva la serie y el número original.</strong> No se crea un nuevo documento ni se consume otro correlativo.</p>' +
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
            title: "Enviando documento",
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
                tipo_registro: tipo,
                id: id
            },

            success: function (respuesta) {
                const resultadoEnvio =
                    respuesta
                    && typeof respuesta.resultado === "object"
                        ? respuesta.resultado
                        : {};

                const estado = String(
                    resultadoEnvio.status
                    || resultadoEnvio.estado
                    || ""
                ).toUpperCase();

                const mensaje = String(
                    respuesta.message
                    || resultadoEnvio.mensaje
                    || "APISUNAT no devolvió un mensaje."
                );

                if (
                    !respuesta
                    || respuesta.status !== true
                ) {
                    mostrarDetalleErrorSunat(
                        "No se pudo enviar",
                        mensaje,
                        resultadoEnvio.faults || [],
                        resultadoEnvio.notes || []
                    );

                    recargarTablaSunat(false);
                    actualizarContadorGlobalSunat();
                    return;
                }

                Swal.fire({
                    icon: "success",
                    title: "Documento enviado",
                    text:
                        mensaje
                        + (
                            estado !== ""
                                ? " Estado: " + estado + "."
                                : ""
                        )
                }).then(function () {
                    recargarTablaSunat(false);
                    actualizarContadorGlobalSunat();

                    if (
                        estado === "PENDIENTE"
                        || estado === "EN_PROCESO"
                        || estado === "ENVIADO"
                    ) {
                        window.setTimeout(
                            function () {
                                consultarSunatManual(
                                    tipo,
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
                actualizarContadorGlobalSunat();
            }
        });
    });
}

function consultarSunatManual(
    tipoRegistro,
    idDocumento,
    automatico = false
) {
    const tipo = normalizarTipoRegistroJs(tipoRegistro);
    const id = Number.parseInt(idDocumento, 10) || 0;

    if (id <= 0) {
        mostrarErrorSunat(
            "Documento inválido",
            "No se pudo determinar el documento electrónico."
        );
        return;
    }

    if (!automatico) {
        Swal.fire({
            title: "Consultando SUNAT",
            text:
                "Obteniendo el estado actual del documento.",
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
            tipo_registro: tipo,
            id: id
        },

        success: function (respuesta) {
            const resultado =
                respuesta
                && typeof respuesta.resultado === "object"
                    ? respuesta.resultado
                    : {};

            const estado = String(
                resultado.status
                || resultado.estado
                || ""
            ).toUpperCase();

            const mensaje = String(
                respuesta.message
                || resultado.mensaje
                || "SUNAT no devolvió información adicional."
            );

            const aceptado = estado === "ACEPTADO";
            const rechazado = [
                "RECHAZADO",
                "EXCEPCION",
                "ERROR"
            ].includes(estado);

            let icono = "info";
            let titulo = "Estado actualizado";

            if (aceptado) {
                icono = "success";
                titulo = "Documento aceptado";
            } else if (rechazado) {
                icono = "error";
                titulo = "Documento no aceptado";
            } else if (
                respuesta
                && respuesta.status !== true
            ) {
                icono = "warning";
                titulo = "No se pudo confirmar el estado";
            }

            if (!automatico) {
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
            }

            recargarTablaSunat(false);
            actualizarContadorGlobalSunat();
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
                        "Revise nuevamente el documento."
                    )
                });
            }

            recargarTablaSunat(false);
            actualizarContadorGlobalSunat();
        }
    });
}

function verDetalleSunat(tipoRegistro, idDocumento) {
    const tipo = normalizarTipoRegistroJs(tipoRegistro);
    const id = Number.parseInt(idDocumento, 10) || 0;

    if (id <= 0) {
        mostrarErrorSunat(
            "Documento inválido",
            "No se pudo determinar el documento electrónico."
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
            tipo_registro: tipo,
            id: id,
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

            const rechazoDefinitivo =
                respuesta.rechazo_definitivo === true;

            const estado = String(
                respuesta.estado || ""
            ).toUpperCase();

            Swal.fire({
                icon:
                    rechazoDefinitivo
                        ? "error"
                        : (
                            estado === "ACEPTADO"
                                ? "success"
                                : (
                                    puedeReintentar
                                        ? "error"
                                        : "info"
                                )
                        ),
                title:
                    rechazoDefinitivo
                        ? "Documento rechazado"
                        : (
                            puedeReintentar
                                ? "Error técnico de envío"
                                : "Detalle SUNAT"
                        ),
                html: construirHtmlDetalleSunat(respuesta),
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
                    enviarSunatManual(tipo, id);
                }
            });
        },

        error: function (xhr) {
            mostrarErrorSunat(
                "No se pudo cargar",
                obtenerMensajeAjaxSunat(
                    xhr,
                    "No fue posible cargar el detalle del documento."
                )
            );
        }
    });
}

function construirHtmlDetalleSunat(respuesta) {
    const estado = String(
        respuesta.estado || "NO_ENVIADO"
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

    const avisoRechazo =
        respuesta.rechazo_definitivo === true
            ? (
                '<div style="margin-bottom:12px;padding:11px 13px;border:1px solid #f1b7bf;border-radius:9px;background:#fff5f6;color:#9b2638;text-align:left;font-size:.78rem;line-height:1.45">' +
                '<strong>No se puede reintentar este número.</strong><br>' +
                'SUNAT/APISUNAT ya registró la numeración. Revise el motivo antes de emitir otro documento.' +
                '</div>'
            )
            : '';

    const origen = String(
        respuesta.comprobante_origen || ""
    ).trim();

    const origenHtml = origen !== ""
        ? (
            '<div><span>Documento original</span><strong>' +
            escaparHtmlSunat(origen) +
            '</strong></div>'
        )
        : '';

    let archivos = '';

    if (respuesta.tiene_xml === true || respuesta.tiene_cdr === true) {
        archivos = '<div class="sunat-detail-files">';

        if (respuesta.tiene_xml === true && respuesta.xml_url) {
            archivos +=
                '<a class="sunat-detail-file" href="' +
                escaparHtmlSunat(respuesta.xml_url) +
                '"><i class="far fa-file-code"></i> Descargar XML</a>';
        }

        if (respuesta.tiene_cdr === true && respuesta.cdr_url) {
            archivos +=
                '<a class="sunat-detail-file" href="' +
                escaparHtmlSunat(respuesta.cdr_url) +
                '"><i class="far fa-file-archive"></i> Descargar CDR</a>';
        }

        archivos += '</div>';
    }

    return (
        avisoRechazo +
        '<div class="sunat-detail-meta">' +
            '<div><span>Documento</span><strong>' +
                escaparHtmlSunat(respuesta.comprobante || "—") +
            '</strong></div>' +
            '<div><span>Estado SUNAT</span><strong>' +
                escaparHtmlSunat(estado) +
            '</strong></div>' +
            '<div><span>Tipo</span><strong>' +
                escaparHtmlSunat(
                    (respuesta.tipo_documento || "Documento electrónico") +
                    (respuesta.tipo_documento_sunat
                        ? " · " + respuesta.tipo_documento_sunat
                        : "")
                ) +
            '</strong></div>' +
            origenHtml +
            '<div><span>Cliente</span><strong>' +
                escaparHtmlSunat(respuesta.cliente || "—") +
            '</strong></div>' +
            '<div><span>Total</span><strong>S/ ' +
                escaparHtmlSunat(respuesta.total || "0.00") +
            '</strong></div>' +
            '<div><span>Document ID</span><strong>' +
                escaparHtmlSunat(respuesta.documentId || "—") +
            '</strong></div>' +
        '</div>' +
        archivos +
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
    const partes = [String(mensaje || "")];

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

function normalizarMensajesSunatJs(valor) {
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

function normalizarTipoRegistroJs(valor) {
    const tipo = String(valor || "VENTA")
        .trim()
        .toUpperCase();

    if (
        [
            "NOTA",
            "NC",
            "NOTA_CREDITO",
            "NOTA-CREDITO"
        ].includes(tipo)
    ) {
        return "NOTA_CREDITO";
    }

    return "VENTA";
}

function normalizarTextoSunat(valor) {
    return $("<div>")
        .html(String(valor || ""))
        .text()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim()
        .toUpperCase();
}

function escaparHtmlSunat(valor) {
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

function recargarTablaSunat(mostrarAviso = false) {
    if (
        !tablaSunat
        || !$.fn.DataTable.isDataTable("#tbllistado")
    ) {
        return;
    }

    const $boton = $("#btnActualizarSunat");
    const contenidoOriginal = $boton.html();

    $boton
        .prop("disabled", true)
        .html(
            '<span class="spinner-border spinner-border-sm mr-1"></span>' +
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
                    title: "Bandeja actualizada",
                    timer: 1100,
                    showConfirmButton: false
                });
            }
        },
        false
    );
}

function actualizarContadorGlobalSunat() {
    if (
        typeof window.actualizarContadorSunatNavbar === "function"
    ) {
        window.setTimeout(
            window.actualizarContadorSunatNavbar,
            250
        );
    }
}

function obtenerMensajeAjaxSunat(xhr, predeterminado) {
    if (
        xhr.responseJSON
        && typeof xhr.responseJSON.message === "string"
    ) {
        return xhr.responseJSON.message;
    }

    if (
        xhr.responseJSON
        && typeof xhr.responseJSON.mensaje === "string"
    ) {
        return xhr.responseJSON.mensaje;
    }

    return predeterminado;
}

function mostrarErrorSunat(titulo, mensaje) {
    Swal.fire({
        icon: "error",
        title: titulo,
        text: mensaje
    });
}
