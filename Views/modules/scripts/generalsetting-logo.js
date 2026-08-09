"use strict";

let logoEmpresaServidor = "";
let logoEmpresaObjetoUrl = null;

function configurarLogoEmpresa() {
    const input = document.getElementById("logo");
    const dropzone = document.getElementById("logoEmpresaDropzone");
    const btnGestionar = document.getElementById("btnGestionarLogo");
    const editor = document.getElementById("logoEmpresaEditor");
    const btnCambiar = document.getElementById("btnCambiarLogo");
    const btnQuitar = document.getElementById("btnQuitarLogo");

    if (
        !input
        || !dropzone
        || !btnGestionar
        || !editor
        || !btnCambiar
        || !btnQuitar
    ) {
        return;
    }

    cargarLogoEmpresaActual();

    btnGestionar.addEventListener(
        "click",
        function () {
            const abierto =
                btnGestionar.getAttribute("aria-expanded")
                === "true";

            establecerEditorLogoEmpresa(
                !abierto,
                !abierto
            );
        }
    );

    btnCambiar.addEventListener(
        "click",
        function () {
            input.click();
        }
    );

    btnQuitar.addEventListener(
        "click",
        function () {
            quitarLogoEmpresa();
        }
    );

    dropzone.addEventListener(
        "click",
        function () {
            input.click();
        }
    );

    dropzone.addEventListener(
        "keydown",
        function (evento) {
            if (
                evento.key === "Enter"
                || evento.key === " "
            ) {
                evento.preventDefault();
                input.click();
            }
        }
    );

    input.addEventListener(
        "change",
        function () {
            const archivo =
                this.files
                && this.files[0]
                    ? this.files[0]
                    : null;

            if (archivo) {
                procesarLogoEmpresaSeleccionado(
                    archivo
                );
            }
        }
    );

    [
        "dragenter",
        "dragover"
    ].forEach(function (eventoNombre) {
        dropzone.addEventListener(
            eventoNombre,
            function (evento) {
                evento.preventDefault();
                evento.stopPropagation();
                dropzone.classList.add(
                    "is-dragover"
                );
            }
        );
    });

    [
        "dragleave",
        "drop"
    ].forEach(function (eventoNombre) {
        dropzone.addEventListener(
            eventoNombre,
            function (evento) {
                evento.preventDefault();
                evento.stopPropagation();
                dropzone.classList.remove(
                    "is-dragover"
                );
            }
        );
    });

    dropzone.addEventListener(
        "drop",
        function (evento) {
            const archivo =
                evento.dataTransfer
                && evento.dataTransfer.files
                && evento.dataTransfer.files[0]
                    ? evento.dataTransfer.files[0]
                    : null;

            if (!archivo) {
                return;
            }

            const transferencia =
                new DataTransfer();

            transferencia.items.add(
                archivo
            );

            input.files =
                transferencia.files;

            procesarLogoEmpresaSeleccionado(
                archivo
            );
        }
    );

    /*
     * El JS principal guarda con FormData. Cuando termina,
     * se vuelve a consultar el logo para reflejar el archivo
     * definitivo almacenado por el servidor.
     */
    $(document).ajaxComplete(
        function (
            evento,
            xhr,
            opciones
        ) {
            const url = String(
                opciones
                && opciones.url
                    ? opciones.url
                    : ""
            );

            if (
                url.includes(
                    "Controllers/Company.php?op=guardaryeditar"
                )
                && xhr.status >= 200
                && xhr.status < 300
                && String(
                    xhr.responseText || ""
                )
                    .toLowerCase()
                    .includes("correctamente")
            ) {
                window.setTimeout(
                    cargarLogoEmpresaActual,
                    250
                );
            }
        }
    );
}

function establecerEditorLogoEmpresa(
    abierto,
    enfocar
) {
    const $editor =
        $("#logoEmpresaEditor");

    const $boton =
        $("#btnGestionarLogo");

    if (
        !$editor.length
        || !$boton.length
    ) {
        return;
    }

    $boton.attr(
        "aria-expanded",
        abierto ? "true" : "false"
    );

    $editor.attr(
        "aria-hidden",
        abierto ? "false" : "true"
    );

    if (abierto) {
        $editor
            .stop(true, true)
            .slideDown(160);

        if (enfocar) {
            window.setTimeout(
                function () {
                    const dropzone =
                        document.getElementById(
                            "logoEmpresaDropzone"
                        );

                    if (dropzone) {
                        dropzone.focus({
                            preventScroll: true
                        });
                    }
                },
                180
            );
        }

        return;
    }

    $editor
        .stop(true, true)
        .slideUp(140);
}

function actualizarBotonGestionLogoEmpresa() {
    const tieneLogoServidor =
        String(
            logoEmpresaServidor || ""
        ).trim() !== "";

    const tieneArchivoNuevo =
        document.getElementById("logo")
        && document.getElementById("logo").files
        && document.getElementById("logo").files.length > 0;

    const pendienteQuitar =
        String(
            $("#eliminar_logo").val() || "0"
        ) === "1";

    let texto = "Agregar logo";

    if (
        tieneLogoServidor
        || tieneArchivoNuevo
        || pendienteQuitar
    ) {
        texto = "Administrar logo";
    }

    $("#textoBtnGestionarLogo").text(
        texto
    );
}

function cargarLogoEmpresaActual() {
    $.ajax({
        url: "Controllers/Company.php",
        type: "GET",
        dataType: "json",
        cache: false,

        data: {
            op: "mostrar_datos",
            logo_v: Date.now()
        },

        success: function (data) {
            const logo = String(
                data
                && data.logo
                    ? data.logo
                    : ""
            ).trim();

            logoEmpresaServidor =
                logo;

            $("#logo").val("");
            $("#eliminar_logo").val("0");

            establecerEditorLogoEmpresa(
                false,
                false
            );

            actualizarBotonGestionLogoEmpresa();

            mostrarLogoEmpresaServidor(
                logo
            );
        },

        error: function () {
            mostrarLogoEmpresaVacio(
                "No se pudo cargar el logo actual.",
                "danger"
            );
        }
    });
}

function procesarLogoEmpresaSeleccionado(
    archivo
) {
    const tiposPermitidos = [
        "image/png",
        "image/jpeg",
        "image/webp"
    ];

    if (
        !tiposPermitidos.includes(
            String(archivo.type || "")
        )
    ) {
        limpiarSeleccionLogoEmpresa();

        mostrarAlertaLogoEmpresa(
            "Formato no permitido",
            "Selecciona un archivo PNG, JPG o WEBP. Los WEBP se convertirán a PNG.",
            "warning"
        );

        return;
    }

    if (
        Number(archivo.size || 0)
        > (2 * 1024 * 1024)
    ) {
        limpiarSeleccionLogoEmpresa();

        mostrarAlertaLogoEmpresa(
            "Archivo muy grande",
            "El logo debe pesar como máximo 2 MB.",
            "warning"
        );

        return;
    }

    if (logoEmpresaObjetoUrl) {
        URL.revokeObjectURL(
            logoEmpresaObjetoUrl
        );
    }

    logoEmpresaObjetoUrl =
        URL.createObjectURL(
            archivo
        );

    mostrarLogoEmpresa(
        logoEmpresaObjetoUrl
    );

    $("#eliminar_logo").val("0");

    establecerEditorLogoEmpresa(
        true,
        false
    );

    actualizarBotonGestionLogoEmpresa();

    actualizarEstadoLogoEmpresa(
        "Nuevo logo listo para guardar",
        "primary"
    );

    $("#logoEmpresaEstado")
        .removeClass(
            "text-muted text-danger text-success"
        )
        .addClass(
            "text-primary"
        )
        .text(
            archivo.name
            + " · "
            + formatearPesoLogoEmpresa(
                archivo.size
            )
        );

    $("#btnQuitarLogo").prop(
        "disabled",
        false
    );
}

function mostrarLogoEmpresaServidor(
    nombreLogo
) {
    nombreLogo = String(
        nombreLogo || ""
    ).trim();

    if (nombreLogo === "") {
        mostrarLogoEmpresaVacio(
            "PNG, JPG o WEBP. Máximo 2 MB.",
            "muted"
        );

        actualizarBotonGestionLogoEmpresa();

        return;
    }

    const ruta =
        "Assets/img/company/"
        + encodeURIComponent(
            nombreLogo
        )
        + "?v="
        + Date.now();

    const imagen =
        document.getElementById(
            "logoEmpresaPreview"
        );

    imagen.onload = function () {
        mostrarLogoEmpresa(
            ruta
        );

        $("#logoEmpresaEstado")
            .removeClass(
                "text-danger text-primary"
            )
            .addClass(
                "text-success"
            )
            .text(
                "Logo actual: "
                + nombreLogo
            );

        actualizarEstadoLogoEmpresa(
            "Logo configurado",
            "success"
        );

        $("#btnQuitarLogo").prop(
            "disabled",
            false
        );

        actualizarBotonGestionLogoEmpresa();
    };

    imagen.onerror = function () {
        mostrarLogoEmpresaVacio(
            "El archivo registrado no se encontró en el servidor.",
            "danger"
        );
    };

    imagen.src =
        ruta;
}

function mostrarLogoEmpresa(
    origen
) {
    $("#logoEmpresaPreview")
        .attr(
            "src",
            origen
        )
        .show();

    $("#logoEmpresaVacio").hide();
}

function mostrarLogoEmpresaVacio(
    mensaje,
    tipo
) {
    $("#logoEmpresaPreview")
        .attr(
            "src",
            ""
        )
        .hide();

    $("#logoEmpresaVacio").show();

    $("#logoEmpresaEstado")
        .removeClass(
            "text-muted text-danger text-success text-primary"
        )
        .addClass(
            tipo === "danger"
                ? "text-danger"
                : "text-muted"
        )
        .text(
            String(mensaje || "")
        );

    actualizarEstadoLogoEmpresa(
        "Sin logo",
        tipo === "danger"
            ? "danger"
            : "light"
    );

    $("#btnQuitarLogo").prop(
        "disabled",
        true
    );

    actualizarBotonGestionLogoEmpresa();
}

function quitarLogoEmpresa() {
    $("#logo").val("");
    $("#eliminar_logo").val("1");

    if (logoEmpresaObjetoUrl) {
        URL.revokeObjectURL(
            logoEmpresaObjetoUrl
        );

        logoEmpresaObjetoUrl =
            null;
    }

    mostrarLogoEmpresaVacio(
        "El logo se quitará al guardar la configuración.",
        "muted"
    );

    actualizarEstadoLogoEmpresa(
        "Pendiente de quitar",
        "warning"
    );

    $("#btnQuitarLogo").prop(
        "disabled",
        true
    );

    establecerEditorLogoEmpresa(
        true,
        false
    );

    actualizarBotonGestionLogoEmpresa();
}

function limpiarSeleccionLogoEmpresa() {
    $("#logo").val("");

    if (logoEmpresaObjetoUrl) {
        URL.revokeObjectURL(
            logoEmpresaObjetoUrl
        );

        logoEmpresaObjetoUrl =
            null;
    }

    mostrarLogoEmpresaServidor(
        logoEmpresaServidor
    );

    establecerEditorLogoEmpresa(
        true,
        false
    );

    actualizarBotonGestionLogoEmpresa();
}

function actualizarEstadoLogoEmpresa(
    texto,
    tipo
) {
    const clases =
        "badge-light badge-primary "
        + "badge-success badge-warning "
        + "badge-danger border";

    $("#logoEmpresaBadge")
        .removeClass(
            clases
        )
        .addClass(
            tipo === "primary"
                ? "badge-primary"
                : tipo === "success"
                    ? "badge-success"
                    : tipo === "warning"
                        ? "badge-warning"
                        : tipo === "danger"
                            ? "badge-danger"
                            : "badge-light border"
        )
        .text(
            texto
        );
}

function formatearPesoLogoEmpresa(
    bytes
) {
    const kb =
        Number(bytes || 0)
        / 1024;

    if (kb < 1024) {
        return kb.toFixed(0)
            + " KB";
    }

    return (
        kb / 1024
    ).toFixed(2)
        + " MB";
}

function mostrarAlertaLogoEmpresa(
    titulo,
    mensaje,
    tipo
) {
    if (
        window.Swal
        && typeof window.Swal.fire
            === "function"
    ) {
        window.Swal.fire({
            icon: tipo,
            title: titulo,
            text: mensaje
        });

        return;
    }

    window.alert(
        titulo
        + "\n\n"
        + mensaje
    );
}

$(document).ready(
    function () {
        configurarLogoEmpresa();
    }
);
