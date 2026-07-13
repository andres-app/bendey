"use strict";

/*
|--------------------------------------------------------------------------
| INICIALIZACIÓN
|--------------------------------------------------------------------------
*/
function init() {
    cargarDatosEmpresa();

    $("#formulario").on(
        "submit",
        function (e) {
            guardaryeditar(e);
        }
    );

    configurarVisibilidadToken(
        "toggleTokenVisibility",
        "tokendniruc",
        "eyeIcon"
    );

    configurarVisibilidadToken(
        "toggleApiSunatToken",
        "apisunat_persona_token",
        "apiSunatEyeIcon"
    );

    $("#documento").on(
        "input",
        function () {
            this.value = String(this.value)
                .replace(/\D/g, "")
                .slice(0, 11);
        }
    );

    $("#telefono").on(
        "input",
        function () {
            this.value = String(this.value)
                .replace(/[^\d+\-\s]/g, "")
                .slice(0, 20);
        }
    );
}

/*
|--------------------------------------------------------------------------
| CARGAR DATOS DE LA EMPRESA
|--------------------------------------------------------------------------
*/
function cargarDatosEmpresa() {
    $.ajax({
        url: "Controllers/Company.php",
        type: "GET",
        dataType: "json",
        cache: false,

        data: {
            op: "mostrar_datos",
            v: Date.now()
        },

        success: function (data) {
            if (
                !data ||
                typeof data !== "object"
            ) {
                console.warn(
                    "No se encontraron datos de empresa."
                );

                actualizarEstadoApiSunat(
                    false,
                    false
                );

                return;
            }

            $("#id_negocio").val(
                data.id_negocio || ""
            );

            $("#nombre").val(
                data.nombre || ""
            );

            $("#ndocumento").val(
                data.ndocumento || "RUC"
            );

            $("#documento").val(
                data.documento || ""
            );

            $("#direccion").val(
                data.direccion || ""
            );

            $("#telefono").val(
                data.telefono || ""
            );

            $("#email").val(
                data.email || ""
            );

            $("#pais").val(
                data.pais || ""
            );

            $("#ciudad").val(
                data.ciudad || ""
            );

            $("#nombre_impuesto").val(
                data.nombre_impuesto || ""
            );

            $("#monto_impuesto").val(
                data.monto_impuesto ?? ""
            );

            $("#moneda").val(
                data.moneda || ""
            );

            $("#simbolo").val(
                data.simbolo || ""
            );

            /*
             * El token de consulta DNI/RUC continúa
             * siendo independiente de APISUNAT.
             */
            $("#tokendniruc").val(
                data.token_reniec_sunat || ""
            );

            /*
             * Persona ID puede mostrarse.
             */
            $("#apisunat_persona_id").val(
                data.apisunat_persona_id || ""
            );

            /*
             * Nunca se coloca el Persona Token
             * existente dentro del navegador.
             */
            $("#apisunat_persona_token").val("");

            $("#apisunat_production").val(
                String(
                    data.apisunat_production ?? 1
                )
            );

            const tokenConfigurado =
                Number(
                    data.apisunat_token_configurado || 0
                ) === 1;

            const personaIdConfigurado =
                String(
                    data.apisunat_persona_id || ""
                ).trim() !== "";

            actualizarEstadoApiSunat(
                personaIdConfigurado,
                tokenConfigurado
            );
        },

        error: function (xhr) {
            console.error(
                "Error al cargar la empresa:",
                xhr.status,
                xhr.responseText
            );

            actualizarEstadoApiSunat(
                false,
                false
            );

            mostrarAlertaConfiguracion(
                "Error",
                obtenerMensajeError(
                    xhr,
                    "No se pudo cargar la configuración de la empresa."
                ),
                "error"
            );
        }
    });
}

/*
|--------------------------------------------------------------------------
| GUARDAR O EDITAR
|--------------------------------------------------------------------------
*/
function guardaryeditar(e) {
    e.preventDefault();

    const formulario =
        document.getElementById("formulario");

    if (!formulario) {
        mostrarAlertaConfiguracion(
            "Error",
            "No se encontró el formulario de configuración.",
            "error"
        );

        return;
    }

    const personaId = String(
        $("#apisunat_persona_id").val() || ""
    ).trim();

    const personaToken = String(
        $("#apisunat_persona_token").val() || ""
    ).trim();

    if (
        personaId !== "" &&
        !/^[A-Za-z0-9_-]{10,100}$/.test(
            personaId
        )
    ) {
        mostrarAlertaConfiguracion(
            "Persona ID inválido",
            "Revise el Persona ID de APISUNAT.",
            "warning"
        );

        return;
    }

    if (
        personaToken !== "" &&
        personaToken.length < 20
    ) {
        mostrarAlertaConfiguracion(
            "Persona Token inválido",
            "El Persona Token ingresado parece incompleto.",
            "warning"
        );

        return;
    }

    const $boton = $("#btnGuardar");
    const contenidoOriginal = $boton.html();

    $boton
        .prop("disabled", true)
        .html(
            '<span class="spinner-border spinner-border-sm mr-2"></span>' +
            "Guardando..."
        );

    const formData = new FormData(
        formulario
    );

    $.ajax({
        url: "Controllers/Company.php?op=guardaryeditar",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        dataType: "text",
        cache: false,

        success: function (respuesta) {
            const mensaje = String(
                respuesta || ""
            ).trim();

            const guardado =
                mensaje
                    .toLowerCase()
                    .includes("correctamente");

            mostrarAlertaConfiguracion(
                guardado
                    ? "Configuración guardada"
                    : "No se guardó",
                mensaje !== ""
                    ? mensaje
                    : (
                        guardado
                            ? "Los datos fueron actualizados."
                            : "No se pudo actualizar la configuración."
                    ),
                guardado
                    ? "success"
                    : "warning"
            );

            if (guardado) {
                /*
                 * Limpiar el token escrito y volver a consultar
                 * para confirmar que quedó configurado.
                 */
                $("#apisunat_persona_token").val("");

                cargarDatosEmpresa();
            }
        },

        error: function (xhr) {
            console.error(
                "Error al guardar configuración:",
                xhr.status,
                xhr.responseText
            );

            mostrarAlertaConfiguracion(
                "Error",
                obtenerMensajeError(
                    xhr,
                    "No se pudo guardar la configuración."
                ),
                "error"
            );
        },

        complete: function () {
            $boton
                .prop("disabled", false)
                .html(contenidoOriginal);
        }
    });
}

/*
|--------------------------------------------------------------------------
| ESTADO VISUAL APISUNAT
|--------------------------------------------------------------------------
*/
function actualizarEstadoApiSunat(
    personaIdConfigurado,
    tokenConfigurado
) {
    const $textoToken =
        $("#apisunatTokenEstado");

    const $estadoGeneral =
        $("#apisunatEstadoGeneral");

    if (tokenConfigurado) {
        $textoToken
            .text(
                "Token configurado. Déjalo vacío para conservarlo."
            )
            .removeClass(
                "text-muted text-danger"
            )
            .addClass(
                "text-success"
            );
    } else {
        $textoToken
            .text(
                "Token no configurado."
            )
            .removeClass(
                "text-success text-muted"
            )
            .addClass(
                "text-danger"
            );
    }

    if (
        personaIdConfigurado &&
        tokenConfigurado
    ) {
        $estadoGeneral
            .text("Configurado")
            .removeClass(
                "badge-secondary badge-danger badge-warning"
            )
            .addClass(
                "badge-success"
            );

        return;
    }

    if (
        personaIdConfigurado ||
        tokenConfigurado
    ) {
        $estadoGeneral
            .text("Configuración incompleta")
            .removeClass(
                "badge-secondary badge-danger badge-success"
            )
            .addClass(
                "badge-warning"
            );

        return;
    }

    $estadoGeneral
        .text("No configurado")
        .removeClass(
            "badge-secondary badge-success badge-warning"
        )
        .addClass(
            "badge-danger"
        );
}

/*
|--------------------------------------------------------------------------
| MOSTRAR U OCULTAR TOKEN
|--------------------------------------------------------------------------
*/
function configurarVisibilidadToken(
    botonId,
    inputId,
    iconoId
) {
    const boton =
        document.getElementById(botonId);

    const input =
        document.getElementById(inputId);

    const icono =
        document.getElementById(iconoId);

    if (
        !boton ||
        !input ||
        !icono
    ) {
        return;
    }

    boton.addEventListener(
        "click",
        function () {
            const mostrar =
                input.type === "password";

            input.type = mostrar
                ? "text"
                : "password";

            icono.classList.toggle(
                "fa-eye",
                !mostrar
            );

            icono.classList.toggle(
                "fa-eye-slash",
                mostrar
            );
        }
    );
}

/*
|--------------------------------------------------------------------------
| ALERTA COMPATIBLE
|--------------------------------------------------------------------------
*/
function mostrarAlertaConfiguracion(
    titulo,
    mensaje,
    tipo
) {
    if (
        window.Swal &&
        typeof window.Swal.fire === "function"
    ) {
        window.Swal.fire({
            icon: tipo,
            title: String(titulo),
            text: String(mensaje)
        });

        return;
    }

    if (typeof window.swal === "function") {
        window.swal(
            String(titulo),
            String(mensaje),
            String(tipo)
        );

        return;
    }

    window.alert(
        String(titulo) +
        "\n\n" +
        String(mensaje)
    );
}

/*
|--------------------------------------------------------------------------
| MENSAJE DE ERROR AJAX
|--------------------------------------------------------------------------
*/
function obtenerMensajeError(
    xhr,
    mensajePredeterminado
) {
    if (
        xhr.responseJSON &&
        typeof xhr.responseJSON.mensaje === "string"
    ) {
        return xhr.responseJSON.mensaje;
    }

    const texto = String(
        xhr.responseText || ""
    ).trim();

    if (texto !== "") {
        try {
            const json = JSON.parse(texto);

            if (
                json &&
                typeof json.mensaje === "string"
            ) {
                return json.mensaje;
            }
        } catch (error) {
            return texto;
        }
    }

    return mensajePredeterminado;
}

$(document).ready(function () {
    init();
});