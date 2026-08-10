// Views/modules/scripts/generalsetting.js
"use strict";

let datosEmpresaConfiguracionCache = null;
let opcionesVentaPredeterminadaCargadas = false;

/*
|--------------------------------------------------------------------------
| INICIALIZACIÓN
|--------------------------------------------------------------------------
*/
function init() {
    configurarAcordeonConfiguracion();
    cargarOpcionesVentaPredeterminada();
    cargarDatosEmpresa();
    cargarConfiguracionCaja();
    configurarEventosTributariosEmpresa();

    $("#formulario").on(
        "submit",
        function (e) {
            guardaryeditar(e);
        }
    );

    $("#formConfiguracionCaja").on(
        "submit",
        function (e) {
            guardarPreferenciaCaja(e);
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
| ACORDEÓN DE CONFIGURACIÓN
|--------------------------------------------------------------------------
*/
function configurarAcordeonConfiguracion() {
    const $root = $("#configAccordionPrincipal");

    if (!$root.length) {
        return;
    }

    $root
        .find(".config-accordion-item")
        .each(function () {
            const $item = $(this);
            const abierto = $item.hasClass("is-open");

            $item
                .children(".config-accordion-content")
                .first()
                .toggle(abierto);

            $item
                .children(".config-accordion-trigger")
                .first()
                .attr("aria-expanded", abierto ? "true" : "false");
        });

    actualizarBarraGuardadoSegunSeccion();

    $root
        .off("click.configAccordion", "[data-config-accordion-trigger]")
        .on(
            "click.configAccordion",
            "[data-config-accordion-trigger]",
            function (evento) {
                evento.preventDefault();

                const $item = $(this).closest(
                    ".config-accordion-item"
                );

                abrirItemAcordeonConfiguracion(
                    $item,
                    true
                );
            }
        );
}

function obtenerItemsGrupoAcordeonConfiguracion(
    grupo
) {
    const selector =
        '.config-accordion-item[data-accordion-group="' +
        String(grupo || "") +
        '"]';

    if (grupo === "apis") {
        return $("#configAccordionApis").find(selector);
    }

    return $("#configAccordionPrincipal").find(selector);
}

function abrirItemAcordeonConfiguracion(
    $item,
    desplazar
) {
    if (!$item || !$item.length) {
        return;
    }

    const grupo = String(
        $item.attr("data-accordion-group") || "principal"
    );

    const yaAbierto = $item.hasClass("is-open");

    /*
     * Si el usuario vuelve a pulsar la sección que ya está abierta,
     * se permite plegarla manualmente. El acordeón puede quedar sin
     * ninguna sección abierta.
     */
    if (yaAbierto) {
        cerrarItemAcordeonConfiguracion($item);

        if (grupo === "principal") {
            actualizarBarraGuardadoSegunSeccion();
        }

        return;
    }

    const $itemsGrupo =
        obtenerItemsGrupoAcordeonConfiguracion(grupo);

    $itemsGrupo.each(function () {
        const $otro = $(this);

        if ($otro.is($item)) {
            return;
        }

        cerrarItemAcordeonConfiguracion($otro);
    });

    const $contenido = $item
        .children(".config-accordion-content")
        .first();

    $item
        .addClass("is-open")
        .children(".config-accordion-trigger")
        .first()
        .attr("aria-expanded", "true");

    $contenido
        .stop(true, true)
        .slideDown(180, function () {
            if (desplazar !== false) {
                desplazarAInicioAcordeonConfiguracion(
                    $item
                );
            }
        });

    if (grupo === "principal") {
        actualizarBarraGuardadoSegunSeccion($item);
    }
}

function cerrarItemAcordeonConfiguracion(
    $item
) {
    if (!$item || !$item.length) {
        return;
    }

    $item
        .removeClass("is-open")
        .children(".config-accordion-trigger")
        .first()
        .attr("aria-expanded", "false");

    $item
        .children(".config-accordion-content")
        .first()
        .stop(true, true)
        .slideUp(150);
}

function desplazarAInicioAcordeonConfiguracion(
    $item
) {
    if (!$item || !$item.length) {
        return;
    }

    const $navbar = $(".main-navbar:visible").first();
    const alturaNavbar = $navbar.length
        ? Number($navbar.outerHeight() || 0)
        : 0;

    const posicion = Math.max(
        0,
        Number($item.offset().top || 0) -
            alturaNavbar -
            16
    );

    $("html, body")
        .stop(true)
        .animate(
            {
                scrollTop: posicion
            },
            220
        );
}

function actualizarBarraGuardadoSegunSeccion(
    $itemActivo
) {
    let $activo = $itemActivo;

    if (!$activo || !$activo.length) {
        $activo = $(
            '#configAccordionPrincipal ' +
            '.config-accordion-item' +
            '[data-accordion-group="principal"].is-open'
        ).first();
    }

    const esCaja = String(
        $activo.attr("data-config-section") || ""
    ) === "caja";

    $("#configEmpresaSavebar").toggleClass(
        "d-none",
        esCaja
    );
}

function abrirSeccionParaCampoConfiguracion(
    campo
) {
    if (!campo) {
        return;
    }

    const $campo = $(campo);

    const $principal = $campo.closest(
        '.config-accordion-item[data-accordion-group="principal"]'
    );

    if ($principal.length) {
        abrirItemAcordeonConfiguracion(
            $principal,
            false
        );
    }

    const $api = $campo.closest(
        '.config-accordion-item[data-accordion-group="apis"]'
    );

    if ($api.length) {
        window.setTimeout(
            function () {
                abrirItemAcordeonConfiguracion(
                    $api,
                    false
                );
            },
            90
        );
    }

    window.setTimeout(
        function () {
            desplazarAInicioAcordeonConfiguracion(
                $api.length ? $api : $principal
            );
        },
        230
    );
}

function validarCamposFormularioConfiguracion(
    formulario
) {
    if (!formulario || !formulario.elements) {
        return true;
    }

    const campos = Array.from(
        formulario.elements
    );

    const campoInvalido = campos.find(
        function (campo) {
            return (
                campo &&
                !campo.disabled &&
                typeof campo.checkValidity === "function" &&
                !campo.checkValidity()
            );
        }
    );

    if (!campoInvalido) {
        return true;
    }

    abrirSeccionParaCampoConfiguracion(
        campoInvalido
    );

    window.setTimeout(
        function () {
            try {
                campoInvalido.focus({
                    preventScroll: true
                });
            } catch (error) {
                campoInvalido.focus();
            }

            if (
                typeof campoInvalido.reportValidity ===
                "function"
            ) {
                campoInvalido.reportValidity();
            }
        },
        260
    );

    return false;
}

function mostrarSeccionConfiguracionPorSelector(
    selector
) {
    const elemento = document.querySelector(
        selector
    );

    if (elemento) {
        abrirSeccionParaCampoConfiguracion(
            elemento
        );
    }
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

            $("#tipo_operacion_sunat_predeterminado").val(
                data.tipo_operacion_sunat_predeterminado || "0101"
            );

            $("#codigo_afectacion_igv_predeterminado").val(
                data.codigo_afectacion_igv_predeterminado || "10"
            );

            $("#porcentaje_igv_predeterminado").val(
                Number(
                    data.porcentaje_igv_predeterminado
                    ?? data.monto_impuesto
                    ?? 18
                ).toFixed(2)
            );

            $("#unidad_medida_sunat_predeterminada").val(
                data.unidad_medida_sunat_predeterminada || "NIU"
            );

            $("#permitir_cambio_afectacion_venta").prop(
                "checked",
                Number(data.permitir_cambio_afectacion_venta || 0) === 1
            );

            $("#precios_incluyen_impuesto").prop(
                "checked",
                Number(data.precios_incluyen_impuesto ?? 1) === 1
            );

            sincronizarAfectacionTributariaEmpresa(false);

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

            datosEmpresaConfiguracionCache = data;
            aplicarValoresPredeterminadosVenta(data);

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
| OPCIONES PREDETERMINADAS DE NUEVA VENTA
|--------------------------------------------------------------------------
*/
function cargarOpcionesVentaPredeterminada() {
    const solicitudComprobantes = $.ajax({
        url: "Controllers/Sell.php?op=selectComprobante",
        type: "GET",
        dataType: "html",
        cache: false
    }).done(function (html) {
        $("#venta_tipo_comprobante_predeterminado")
            .html(html)
            .find("option:first")
            .text("Sin comprobante predeterminado");
    });

    const solicitudTiposPago = $.ajax({
        url: "Controllers/Paymentstype.php?op=selectTipopago",
        type: "GET",
        dataType: "html",
        cache: false
    }).done(function (html) {
        $("#venta_tipo_pago_predeterminado")
            .html(html)
            .find("option:first")
            .text("Sin tipo de pago predeterminado");
    });

    const solicitudFormasPago = $.ajax({
        url: "Controllers/Sell.php?op=selectFormaPago",
        type: "GET",
        dataType: "html",
        cache: false
    }).done(function (html) {
        $("#venta_idforma_pago_predeterminada")
            .html(html)
            .find("option:first")
            .text("Sin forma de pago predeterminada");
    });

    $.when(
        solicitudComprobantes,
        solicitudTiposPago,
        solicitudFormasPago
    ).done(function () {
        opcionesVentaPredeterminadaCargadas = true;

        if (datosEmpresaConfiguracionCache) {
            aplicarValoresPredeterminadosVenta(
                datosEmpresaConfiguracionCache
            );
        }
    }).fail(function () {
        opcionesVentaPredeterminadaCargadas = false;

        mostrarAlertaConfiguracion(
            "Opciones de venta",
            "No se pudieron cargar todos los valores disponibles para la nueva venta.",
            "warning"
        );
    });
}

function aplicarValoresPredeterminadosVenta(data) {
    if (
        !data
        || typeof data !== "object"
        || !opcionesVentaPredeterminadaCargadas
    ) {
        return;
    }

    seleccionarOpcionConfiguracion(
        "#venta_tipo_comprobante_predeterminado",
        data.venta_tipo_comprobante_predeterminado || ""
    );

    seleccionarOpcionConfiguracion(
        "#venta_tipo_pago_predeterminado",
        data.venta_tipo_pago_predeterminado || ""
    );

    const idFormaPago = Number(
        data.venta_idforma_pago_predeterminada || 0
    );

    $("#venta_idforma_pago_predeterminada").val(
        idFormaPago > 0
            ? String(idFormaPago)
            : ""
    );

    const modoEnvio = String(
        data.venta_modo_envio_predeterminado || ""
    ).trim().toLowerCase();

    $("#venta_modo_envio_predeterminado").val(
        ["inmediato", "manual", "resumen_diario"].includes(modoEnvio)
            ? modoEnvio
            : ""
    );
}

function seleccionarOpcionConfiguracion(
    selector,
    valor
) {
    const $select = $(selector);
    const buscado = String(valor || "").trim();

    if (!$select.length || buscado === "") {
        $select.val("");
        return false;
    }

    const buscadoNormalizado = normalizarTextoConfiguracion(
        buscado
    );

    let valorEncontrado = "";

    $select.find("option").each(function () {
        const valorOpcion = String(
            $(this).val() || ""
        ).trim();

        const textoOpcion = String(
            $(this).text() || ""
        ).trim();

        if (
            valorOpcion === buscado
            || normalizarTextoConfiguracion(valorOpcion)
                === buscadoNormalizado
            || normalizarTextoConfiguracion(textoOpcion)
                === buscadoNormalizado
        ) {
            valorEncontrado = valorOpcion;
            return false;
        }
    });

    $select.val(valorEncontrado);
    return valorEncontrado !== "";
}

function normalizarTextoConfiguracion(valor) {
    return String(valor || "")
        .trim()
        .toUpperCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");
}


/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN TRIBUTARIA DE EMPRESA
|--------------------------------------------------------------------------
*/
function configurarEventosTributariosEmpresa() {
    $("#codigo_afectacion_igv_predeterminado")
        .off("change.tributarioEmpresa")
        .on("change.tributarioEmpresa", function () {
            sincronizarAfectacionTributariaEmpresa(true);
        });

    $("#monto_impuesto")
        .off("input.tributarioEmpresa")
        .on("input.tributarioEmpresa", function () {
            if ($("#codigo_afectacion_igv_predeterminado").val() === "10") {
                const tasa = Number($(this).val() || 0);
                $("#porcentaje_igv_predeterminado").val(
                    Math.max(0, tasa).toFixed(2)
                );
            }
            actualizarResumenTributarioEmpresa();
        });

    $("#tipo_operacion_sunat_predeterminado, #unidad_medida_sunat_predeterminada")
        .off("change.resumenTributarioEmpresa")
        .on("change.resumenTributarioEmpresa", actualizarResumenTributarioEmpresa);
}

function sincronizarAfectacionTributariaEmpresa(actualizarTasa) {
    const codigo = String(
        $("#codigo_afectacion_igv_predeterminado").val() || "10"
    );

    if (codigo === "10") {
        const tasaGeneral = Number($("#monto_impuesto").val() || 18);
        if (actualizarTasa || $("#porcentaje_igv_predeterminado").val() === "") {
            $("#porcentaje_igv_predeterminado").val(
                Math.max(0, tasaGeneral).toFixed(2)
            );
        }
    } else {
        $("#porcentaje_igv_predeterminado").val("0.00");
    }

    actualizarResumenTributarioEmpresa();
}

function actualizarResumenTributarioEmpresa() {
    const codigo = String(
        $("#codigo_afectacion_igv_predeterminado").val() || "10"
    );
    const tasa = Number(
        $("#porcentaje_igv_predeterminado").val() || 0
    );
    const etiquetas = {
        "10": "Gravado",
        "20": "Exonerado",
        "30": "Inafecto",
        "40": "Exportación"
    };

    $("#estadoConfiguracionTributaria").html(
        '<i class="fas fa-shield-alt"></i> ' +
        (etiquetas[codigo] || "Configuración") +
        (codigo === "10" ? " " + tasa.toFixed(2) + "%" : " 0%")
    );
}

function validarConfiguracionTributariaEmpresa() {
    const tipoOperacion = String(
        $("#tipo_operacion_sunat_predeterminado").val() || ""
    ).trim();
    const afectacion = String(
        $("#codigo_afectacion_igv_predeterminado").val() || ""
    ).trim();
    const tasa = Number(
        $("#porcentaje_igv_predeterminado").val() || 0
    );
    const unidad = String(
        $("#unidad_medida_sunat_predeterminada").val() || ""
    ).trim();

    if (!/^\d{4}$/.test(tipoOperacion)) {
        mostrarSeccionConfiguracionPorSelector(
            "#tipo_operacion_sunat_predeterminado"
        );
        mostrarAlertaConfiguracion(
            "Tipo de operación",
            "Seleccione un tipo de operación SUNAT válido.",
            "warning"
        );
        return false;
    }

    if (!["10", "20", "30", "40"].includes(afectacion)) {
        mostrarSeccionConfiguracionPorSelector(
            "#codigo_afectacion_igv_predeterminado"
        );
        mostrarAlertaConfiguracion(
            "Afectación al IGV",
            "Seleccione una afectación tributaria válida.",
            "warning"
        );
        return false;
    }

    if (afectacion === "10" && (tasa <= 0 || tasa > 100)) {
        mostrarSeccionConfiguracionPorSelector(
            "#porcentaje_igv_predeterminado"
        );
        mostrarAlertaConfiguracion(
            "Tasa de IGV",
            "Una operación gravada debe tener una tasa de IGV válida.",
            "warning"
        );
        return false;
    }

    if (afectacion !== "10" && tasa !== 0) {
        mostrarSeccionConfiguracionPorSelector(
            "#porcentaje_igv_predeterminado"
        );
        mostrarAlertaConfiguracion(
            "Tasa de IGV",
            "Las operaciones exoneradas, inafectas y de exportación deben usar tasa 0%.",
            "warning"
        );
        return false;
    }

    if (!/^[A-Z0-9]{2,3}$/.test(unidad)) {
        mostrarSeccionConfiguracionPorSelector(
            "#unidad_medida_sunat_predeterminada"
        );
        mostrarAlertaConfiguracion(
            "Unidad SUNAT",
            "Seleccione una unidad de medida SUNAT válida.",
            "warning"
        );
        return false;
    }

    return true;
}

/*
|--------------------------------------------------------------------------
| CARGAR CONFIGURACIÓN DE CAJA
|--------------------------------------------------------------------------
*/
function cargarConfiguracionCaja() {
    $.ajax({
        url: "Controllers/ConfiguracionCaja.php",
        type: "GET",
        dataType: "json",
        cache: false,

        data: {
            op: "obtener",
            v: Date.now()
        },

        success: function (data) {
            if (
                !data ||
                data.success !== true ||
                !data.configuracion
            ) {
                mostrarErrorConfiguracionCaja(
                    data && data.mensaje
                        ? data.mensaje
                        : "No se encontró la configuración de caja."
                );

                return;
            }

            const configuracion =
                data.configuracion;

            const cajas =
                Array.isArray(data.cajas)
                    ? data.cajas
                    : [];

            const modoReal = String(
                configuracion.modo || "LEGACY"
            );

            const modoObjetivo = String(
                configuracion.modo_objetivo || ""
            );

            const modoSeleccionado =
                modoObjetivo !== ""
                    ? modoObjetivo
                    : (
                        modoReal !== "LEGACY"
                            ? modoReal
                            : ""
                    );

            $("#idsucursalCaja").val(
                configuracion.idsucursal || ""
            );

            $("#cajaSucursalNombre").text(
                configuracion.nombre_sucursal || "—"
            );

            $("#cajaSucursalCodigo").text(
                configuracion.codigo_sucursal || "—"
            );

            $("#cajaPrincipalNombre").text(
                configuracion.nombre_caja_unica || "Sin asignar"
            );

            $("#cajaPrincipalCodigo").text(
                configuracion.codigo_caja_unica || "—"
            );

            $("#totalCajasActivas").text(
                Number(data.total_cajas || 0)
            );

            cargarOpcionesCajas(
                cajas,
                configuracion.idcaja_unica
            );

            $("#modoCajaUnica").prop(
                "checked",
                modoSeleccionado === "CAJA_UNICA"
            );

            $("#modoMulticaja").prop(
                "checked",
                modoSeleccionado === "MULTICAJA"
            );

            const modalidadYaActiva =
                modoReal !== "LEGACY";

            $(
                "#modoCajaUnica, " +
                "#modoMulticaja, " +
                "#idcajaUnica, " +
                "#btnGuardarConfiguracionCaja"
            ).prop(
                "disabled",
                modalidadYaActiva
            );

            actualizarEstadoConfiguracionCaja(
                modoReal,
                modoObjetivo
            );
        },

        error: function (xhr) {
            console.error(
                "Error al cargar configuración de caja:",
                xhr.status,
                xhr.responseText
            );

            mostrarErrorConfiguracionCaja(
                obtenerMensajeError(
                    xhr,
                    "No se pudo cargar la configuración de caja."
                )
            );
        }
    });
}

/*
|--------------------------------------------------------------------------
| CARGAR CAJAS EN EL SELECT
|--------------------------------------------------------------------------
*/
function cargarOpcionesCajas(
    cajas,
    idcajaSeleccionada
) {
    const $select =
        $("#idcajaUnica");

    $select.empty();

    if (cajas.length === 0) {
        $select.append(
            $("<option>", {
                value: "",
                text: "No existen cajas activas"
            })
        );

        return;
    }

    cajas.forEach(function (caja) {
        const nombre =
            String(caja.nombre || "");

        const codigo =
            String(caja.codigo || "");

        $select.append(
            $("<option>", {
                value: caja.idcaja,
                text:
                    nombre +
                    (
                        codigo !== ""
                            ? " (" + codigo + ")"
                            : ""
                    )
            })
        );
    });

    if (idcajaSeleccionada) {
        $select.val(
            String(idcajaSeleccionada)
        );
    }
}

/*
|--------------------------------------------------------------------------
| ESTADO VISUAL DE CONFIGURACIÓN DE CAJA
|--------------------------------------------------------------------------
*/
function actualizarEstadoConfiguracionCaja(
    modoReal,
    modoObjetivo
) {
    const $estado =
        $("#estadoConfiguracionCaja");

    const $titulo =
        $("#configuracionCajaTitulo");

    const $mensaje =
        $("#configuracionCajaMensaje");

    $estado.removeClass(
        "badge-secondary " +
        "badge-warning " +
        "badge-success " +
        "badge-primary " +
        "badge-danger"
    );

    if (modoReal === "CAJA_UNICA") {
        $estado
            .text("Caja única activa")
            .addClass("badge-success");

        $titulo.text(
            "La sucursal trabaja con Caja única."
        );

        $mensaje.text(
            "Todos los usuarios autorizados utilizan una misma apertura y caja física."
        );

        return;
    }

    if (modoReal === "MULTICAJA") {
        $estado
            .text("Multicaja activo")
            .addClass("badge-primary");

        $titulo.text(
            "La sucursal trabaja con varias cajas."
        );

        $mensaje.text(
            "Cada caja física administra su propia apertura, cierre y efectivo."
        );

        return;
    }

    $estado
        .text("Pendiente de activación")
        .addClass("badge-warning");

    if (modoObjetivo === "CAJA_UNICA") {
        $titulo.text(
            "Caja única seleccionada."
        );

        $mensaje.text(
            "La preferencia fue guardada, pero el sistema continúa temporalmente en modo LEGACY hasta completar la adaptación de aperturas, ventas, cobranzas y cierres."
        );

        return;
    }

    if (modoObjetivo === "MULTICAJA") {
        $titulo.text(
            "Multicaja seleccionada."
        );

        $mensaje.text(
            "La preferencia fue guardada, pero el sistema continúa temporalmente en modo LEGACY hasta completar la adaptación de aperturas, ventas, cobranzas y cierres."
        );

        return;
    }

    $titulo.text(
        "Seleccione una modalidad de caja."
    );

    $mensaje.text(
        "La modalidad elegida quedará guardada como preferencia, pero todavía no será activada."
    );
}

/*
|--------------------------------------------------------------------------
| GUARDAR PREFERENCIA DE CAJA
|--------------------------------------------------------------------------
*/
function guardarPreferenciaCaja(e) {
    e.preventDefault();

    const idsucursal = Number(
        $("#idsucursalCaja").val() || 0
    );

    const modoObjetivo = String(
        $(
            "input[name='modo_caja']:checked"
        ).val() || ""
    );

    const idcajaUnica = Number(
        $("#idcajaUnica").val() || 0
    );

    if (idsucursal <= 0) {
        mostrarAlertaConfiguracion(
            "Sucursal inválida",
            "No se pudo identificar la sucursal.",
            "warning"
        );

        return;
    }

    if (
        modoObjetivo !== "CAJA_UNICA"
        && modoObjetivo !== "MULTICAJA"
    ) {
        mostrarAlertaConfiguracion(
            "Seleccione una modalidad",
            "Debe elegir Caja única o Multicaja.",
            "warning"
        );

        return;
    }

    if (idcajaUnica <= 0) {
        mostrarAlertaConfiguracion(
            "Seleccione una caja",
            "Debe seleccionar una caja principal válida.",
            "warning"
        );

        return;
    }

    const $boton =
        $("#btnGuardarConfiguracionCaja");

    const contenidoOriginal =
        $boton.html();

    $boton
        .prop("disabled", true)
        .html(
            '<span class="spinner-border ' +
            'spinner-border-sm mr-2"></span>' +
            "Guardando..."
        );

    $.ajax({
        url:
            "Controllers/ConfiguracionCaja.php" +
            "?op=guardar_preferencia",

        type: "POST",
        dataType: "json",
        cache: false,

        data: {
            idsucursal: idsucursal,
            modo_objetivo: modoObjetivo,
            idcaja_unica: idcajaUnica
        },

        success: function (data) {
            if (
                !data
                || data.success !== true
            ) {
                mostrarAlertaConfiguracion(
                    "No se guardó",
                    data && data.mensaje
                        ? data.mensaje
                        : "No se pudo guardar la modalidad.",
                    "warning"
                );

                return;
            }

            mostrarAlertaConfiguracion(
                "Modalidad guardada",
                data.mensaje ||
                "La preferencia fue guardada correctamente.",
                "success"
            );

            cargarConfiguracionCaja();
        },

        error: function (xhr) {
            console.error(
                "Error al guardar modalidad:",
                xhr.status,
                xhr.responseText
            );

            mostrarAlertaConfiguracion(
                "Error",
                obtenerMensajeError(
                    xhr,
                    "No se pudo guardar la modalidad de caja."
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
| ERROR VISUAL DE CONFIGURACIÓN DE CAJA
|--------------------------------------------------------------------------
*/
function mostrarErrorConfiguracionCaja(
    mensaje
) {
    $("#estadoConfiguracionCaja")
        .text("Error")
        .removeClass(
            "badge-secondary badge-warning badge-success badge-primary"
        )
        .addClass(
            "badge-danger"
        );

    $("#configuracionCajaTitulo").text(
        "No se pudo cargar la configuración."
    );

    $("#configuracionCajaMensaje").text(
        String(mensaje || "")
    );

    $("#cajaSucursalNombre").text("—");
    $("#cajaSucursalCodigo").text("—");
    $("#cajaPrincipalNombre").text("—");
    $("#cajaPrincipalCodigo").text("—");
    $("#totalCajasActivas").text("0");
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

    if (!validarCamposFormularioConfiguracion(formulario)) {
        return;
    }

    if (!validarConfiguracionTributariaEmpresa()) {
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
        mostrarSeccionConfiguracionPorSelector(
            "#apisunat_persona_id"
        );
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
        mostrarSeccionConfiguracionPorSelector(
            "#apisunat_persona_token"
        );
        mostrarAlertaConfiguracion(
            "Persona Token inválido",
            "El Persona Token ingresado parece incompleto.",
            "warning"
        );

        return;
    }

    const tipoComprobantePredeterminado = String(
        $("#venta_tipo_comprobante_predeterminado").val() || ""
    ).trim();

    const tipoPagoPredeterminado = String(
        $("#venta_tipo_pago_predeterminado").val() || ""
    ).trim();

    const formaPagoPredeterminada = Number(
        $("#venta_idforma_pago_predeterminada").val() || 0
    );

    const comprobanteNormalizado = normalizarTextoConfiguracion(
        tipoComprobantePredeterminado
    );

    const tipoPagoPredeterminadoTexto = String(
        $("#venta_tipo_pago_predeterminado option:selected").text() || ""
    ).trim();

    const pagoNormalizado = normalizarTextoConfiguracion(
        tipoPagoPredeterminado + " " + tipoPagoPredeterminadoTexto
    );

    const modoEnvioPredeterminado = String(
        $("#venta_modo_envio_predeterminado").val() || ""
    ).trim().toLowerCase();

    if (
        modoEnvioPredeterminado === "resumen_diario"
        && comprobanteNormalizado.includes("FACTURA")
    ) {
        mostrarSeccionConfiguracionPorSelector(
            "#venta_modo_envio_predeterminado"
        );
        mostrarAlertaConfiguracion(
            "Configuración incompatible",
            "El Resumen Diario solo puede utilizarse con Boleta Electrónica.",
            "warning"
        );

        return;
    }

    if (
        pagoNormalizado.includes("CREDITO")
        && !comprobanteNormalizado.includes("FACTURA")
    ) {
        mostrarSeccionConfiguracionPorSelector(
            "#venta_tipo_comprobante_predeterminado"
        );
        mostrarAlertaConfiguracion(
            "Configuración incompatible",
            "El pago al crédito está habilitado únicamente para facturas electrónicas. Seleccione Factura Electrónica como comprobante predeterminado.",
            "warning"
        );

        return;
    }

    if (
        pagoNormalizado.includes("CREDITO")
        && formaPagoPredeterminada > 0
        && Number(
            $("#venta_idforma_pago_predeterminada option:selected")
                .attr("data-combinado") || 0
        ) === 1
    ) {
        mostrarSeccionConfiguracionPorSelector(
            "#venta_idforma_pago_predeterminada"
        );
        mostrarAlertaConfiguracion(
            "Forma de pago incompatible",
            "No establezca Pago mixto como forma predeterminada para ventas al crédito.",
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