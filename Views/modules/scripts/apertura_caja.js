"use strict";

/*
|--------------------------------------------------------------------------
| CONTEXTO ACTUAL
|--------------------------------------------------------------------------
*/
let contextoCajaActual = {
    modo: "LEGACY",
    modoObjetivo: "",
    idsucursal: 0,
    idcajaUnica: 0,
    idcajaActiva: 0,
    cajas: []
};

$(document).ready(function () {
    cargarContextoCaja();

    $(document).on(
        "click",
        "#btnAbrirCaja",
        function () {
            abrirCaja();
        }
    );

    $(document).on(
        "change",
        "#idcajaOperacion",
        function () {
            actualizarPermisoCajaSeleccionada();
        }
    );
});

/*
|--------------------------------------------------------------------------
| CARGAR CONTEXTO DE CAJA
|--------------------------------------------------------------------------
*/
function cargarContextoCaja() {
    $.ajax({
        url:
            "Controllers/ContextoCaja.php" +
            "?op=obtener",

        type: "GET",
        dataType: "json",
        cache: false,

        success: function (resp) {
            if (
                !resp ||
                resp.success !== true ||
                !resp.contexto
            ) {
                console.warn(
                    "No se pudo cargar el contexto de caja.",
                    resp
                );

                aplicarContextoLegacy();
                verificarAperturaCaja();

                return;
            }

            contextoCajaActual = {
                modo: String(
                    resp.contexto.modo || "LEGACY"
                ).toUpperCase(),

                modoObjetivo: String(
                    resp.contexto.modo_objetivo || ""
                ).toUpperCase(),

                idsucursal: Number(
                    resp.contexto.idsucursal || 0
                ),

                idcajaUnica: Number(
                    resp.contexto.idcaja_unica || 0
                ),

                idcajaActiva: Number(
                    resp.contexto.idcaja_activa || 0
                ),

                cajas: Array.isArray(resp.cajas)
                    ? resp.cajas
                    : []
            };

            renderizarContextoCaja();

            /*
             * Mientras el modo real sea LEGACY,
             * la comprobación continúa por usuario.
             */
            if (
                contextoCajaActual.modo ===
                "LEGACY"
            ) {
                verificarAperturaCaja();

                return;
            }

            /*
             * Los modos nuevos aún no deben operar
             * hasta adaptar Cajachica.php.
             */
            mostrarModalModoEnPreparacion();
        },

        error: function (xhr) {
            console.error(
                "Error al cargar contexto de caja:",
                xhr.status,
                xhr.responseText
            );

            /*
             * Si falla el contexto nuevo,
             * preservamos temporalmente LEGACY.
             */
            aplicarContextoLegacy();
            verificarAperturaCaja();
        }
    });
}

/*
|--------------------------------------------------------------------------
| RENDERIZAR CONTEXTO
|--------------------------------------------------------------------------
*/
function renderizarContextoCaja() {
    ocultarBloquesContexto();

    switch (contextoCajaActual.modo) {
        case "CAJA_UNICA":
            renderizarCajaUnica();
            break;

        case "MULTICAJA":
            renderizarMulticaja();
            break;

        default:
            aplicarContextoLegacy();
            break;
    }
}

/*
|--------------------------------------------------------------------------
| MODO LEGACY
|--------------------------------------------------------------------------
*/
function aplicarContextoLegacy() {
    contextoCajaActual.modo = "LEGACY";

    ocultarBloquesContexto();

    $("#btnAbrirCaja")
        .prop("disabled", false);

    $("#montoApertura")
        .prop("disabled", false);
}

/*
|--------------------------------------------------------------------------
| CAJA ÚNICA
|--------------------------------------------------------------------------
*/
function renderizarCajaUnica() {
    const caja = contextoCajaActual.cajas.find(
        function (registro) {
            return Number(registro.idcaja) ===
                contextoCajaActual.idcajaUnica;
        }
    );

    $("#bloqueContextoCaja")
        .removeClass("d-none");

    $("#tituloContextoCaja")
        .text("Caja única");

    $("#descripcionContextoCaja")
        .text(
            "Todos los usuarios autorizados trabajarán sobre una misma apertura."
        );

    $("#grupoCajaAutomatica")
        .removeClass("d-none");

    $("#nombreCajaAutomatica")
        .text(
            caja
                ? caja.nombre
                : "Caja no encontrada"
        );

    $("#codigoCajaAutomatica")
        .text(
            caja
                ? caja.codigo
                : "—"
        );

    mostrarBloqueEnPreparacion();
}

/*
|--------------------------------------------------------------------------
| MULTICAJA
|--------------------------------------------------------------------------
*/
function renderizarMulticaja() {
    $("#bloqueContextoCaja")
        .removeClass("d-none");

    $("#tituloContextoCaja")
        .text("Multicaja");

    $("#descripcionContextoCaja")
        .text(
            "Cada caja física tendrá su propia apertura, cierre y control de efectivo."
        );

    $("#grupoSeleccionCaja")
        .removeClass("d-none");

    cargarCajasAutorizadas();

    mostrarBloqueEnPreparacion();
}

/*
|--------------------------------------------------------------------------
| CARGAR CAJAS AUTORIZADAS
|--------------------------------------------------------------------------
*/
function cargarCajasAutorizadas() {
    const $select =
        $("#idcajaOperacion");

    $select.empty();

    if (
        !Array.isArray(
            contextoCajaActual.cajas
        ) ||
        contextoCajaActual.cajas.length === 0
    ) {
        $select.append(
            $("<option>", {
                value: "",
                text:
                    "No tiene cajas autorizadas"
            })
        );

        return;
    }

    $select.append(
        $("<option>", {
            value: "",
            text:
                "Seleccione una caja"
        })
    );

    contextoCajaActual.cajas.forEach(
        function (caja) {
            $select.append(
                $("<option>", {
                    value:
                        Number(caja.idcaja),

                    text:
                        String(
                            caja.nombre || ""
                        ) +
                        " (" +
                        String(
                            caja.codigo || ""
                        ) +
                        ")"
                })
            );
        }
    );

    if (
        contextoCajaActual.idcajaActiva > 0
    ) {
        $select.val(
            String(
                contextoCajaActual.idcajaActiva
            )
        );
    }
}

/*
|--------------------------------------------------------------------------
| PERMISO DE LA CAJA SELECCIONADA
|--------------------------------------------------------------------------
*/
function actualizarPermisoCajaSeleccionada() {
    const idcaja = Number(
        $("#idcajaOperacion").val() || 0
    );

    const caja =
        contextoCajaActual.cajas.find(
            function (registro) {
                return Number(
                    registro.idcaja
                ) === idcaja;
            }
        );

    if (!caja) {
        return;
    }

    const puedeAbrir =
        Number(caja.puede_abrir || 0) === 1 &&
        Number(
            caja.puede_abrir_caja || 0
        ) === 1;

    if (!puedeAbrir) {
        $("#mensajePermisoCaja")
            .removeClass("d-none")
            .text(
                "Puede operar esta caja, pero no tiene permiso para abrirla."
            );

        return;
    }

    $("#mensajePermisoCaja")
        .removeClass("d-none")
        .removeClass("alert-warning")
        .addClass("alert-info")
        .text(
            "Caja autorizada. La apertura se habilitará cuando finalice la adaptación del flujo."
        );
}

/*
|--------------------------------------------------------------------------
| MODO NUEVO TODAVÍA EN PREPARACIÓN
|--------------------------------------------------------------------------
*/
function mostrarBloqueEnPreparacion() {
    $("#mensajePermisoCaja")
        .removeClass("d-none alert-info")
        .addClass("alert-warning")
        .text(
            "Esta modalidad está configurada, pero todavía no se encuentra habilitada para operar."
        );

    $("#btnAbrirCaja")
        .prop("disabled", true);

    $("#montoApertura")
        .prop("disabled", true);
}

function mostrarModalModoEnPreparacion() {
    $("#modalCajaChica").modal({
        backdrop: "static",
        keyboard: false,
        show: true
    });
}

/*
|--------------------------------------------------------------------------
| OCULTAR BLOQUES NUEVOS
|--------------------------------------------------------------------------
*/
function ocultarBloquesContexto() {
    $("#bloqueContextoCaja")
        .addClass("d-none");

    $("#grupoSeleccionCaja")
        .addClass("d-none");

    $("#grupoCajaAutomatica")
        .addClass("d-none");

    $("#mensajePermisoCaja")
        .addClass("d-none")
        .removeClass("alert-info")
        .addClass("alert-warning");

    $("#idcajaOperacion")
        .empty();
}

/*
|--------------------------------------------------------------------------
| VERIFICAR APERTURA LEGACY
|--------------------------------------------------------------------------
*/
function verificarAperturaCaja() {
    $.ajax({
        url:
            "Controllers/Cajachica.php" +
            "?op=verificar_apertura",

        type: "GET",
        dataType: "json",
        cache: false,

        success: function (resp) {
            console.log(
                "Respuesta verificar apertura:",
                resp
            );

            if (resp.status === "error") {
                Swal.fire({
                    icon: "error",
                    title: "Error de caja",
                    text:
                        resp.message ||
                        "No se pudo verificar la caja."
                });

                return;
            }

            if (resp.existe === true) {
                $("#modalCajaChica")
                    .modal("hide");

                return;
            }

            $("#modalCajaChica").modal({
                backdrop: "static",
                keyboard: false,
                show: true
            });

            setTimeout(function () {
                $("#montoApertura")
                    .trigger("focus");
            }, 300);
        },

        error: function (xhr) {
            console.error(
                "HTTP:",
                xhr.status
            );

            console.error(
                "Respuesta:",
                xhr.responseText
            );

            Swal.fire({
                icon: "error",
                title: "Error del servidor",
                text:
                    "No se pudo verificar la apertura de caja."
            });
        }
    });
}

/*
|--------------------------------------------------------------------------
| REGISTRAR APERTURA LEGACY
|--------------------------------------------------------------------------
*/
function abrirCaja() {
    /*
     * Protección temporal:
     * Cajachica.php todavía abre por usuario.
     */
    if (
        contextoCajaActual.modo !==
        "LEGACY"
    ) {
        Swal.fire({
            icon: "warning",
            title:
                "Modalidad todavía no habilitada",
            text:
                "Primero debe completarse la adaptación de aperturas, ventas, cobranzas y cierres."
        });

        return;
    }

    const valorMonto = String(
        $("#montoApertura").val() || ""
    ).trim();

    if (valorMonto === "") {
        Swal.fire({
            icon: "warning",
            title: "Monto requerido",
            text:
                "Ingrese el monto inicial de caja."
        });

        return;
    }

    const monto =
        parseFloat(valorMonto);

    if (
        !Number.isFinite(monto) ||
        monto < 0
    ) {
        Swal.fire({
            icon: "warning",
            title: "Monto inválido",
            text:
                "Ingrese un monto válido."
        });

        return;
    }

    const boton =
        $("#btnAbrirCaja");

    boton
        .prop("disabled", true)
        .html(
            '<i class="fas fa-spinner fa-spin"></i> Abriendo...'
        );

    $.ajax({
        url:
            "Controllers/Cajachica.php" +
            "?op=guardar_apertura",

        type: "POST",
        dataType: "json",

        data: {
            monto: monto.toFixed(2)
        },

        success: function (resp) {
            console.log(
                "Respuesta guardar apertura:",
                resp
            );

            if (resp.status === "ok") {
                $("#modalCajaChica")
                    .modal("hide");

                Swal.fire({
                    icon: "success",
                    title:
                        "Caja abierta correctamente",
                    text:
                        resp.message || "",
                    timer: 1400,
                    showConfirmButton: false
                });

                setTimeout(function () {
                    window.location.reload();
                }, 1400);

                return;
            }

            if (
                String(
                    resp.message || ""
                )
                    .toLowerCase()
                    .includes(
                        "ya existe una caja abierta"
                    )
            ) {
                $("#modalCajaChica")
                    .modal("hide");

                Swal.fire({
                    icon: "info",
                    title:
                        "La caja ya está abierta",
                    text:
                        resp.message,
                    timer: 1600,
                    showConfirmButton: false
                });

                setTimeout(function () {
                    window.location.reload();
                }, 1600);

                return;
            }

            Swal.fire({
                icon: "error",
                title:
                    "No se pudo abrir la caja",
                text:
                    resp.message ||
                    "Ocurrió un error."
            });
        },

        error: function (xhr) {
            console.error(
                "HTTP:",
                xhr.status
            );

            console.error(
                "Respuesta:",
                xhr.responseText
            );

            let mensaje =
                "No se pudo comunicar con el servidor.";

            if (
                xhr.responseJSON &&
                (
                    xhr.responseJSON.message ||
                    xhr.responseJSON.error
                )
            ) {
                mensaje =
                    xhr.responseJSON.message ||
                    xhr.responseJSON.error;
            }

            Swal.fire({
                icon: "error",
                title: "Error del servidor",
                text: mensaje
            });
        },

        complete: function () {
            boton
                .prop("disabled", false)
                .html(
                    '<i class="fas fa-lock-open"></i> INICIAR CAJA'
                );
        }
    });
}