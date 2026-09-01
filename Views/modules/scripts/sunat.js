"use strict";

let tablaSunat = null;
let filtroEstadoSunat = "PENDIENTES";
let filtroSunatRegistrado = false;
let envioMasivoSunatActivo = false;

$(document).ready(function () {
    activarFiltroInicialPendientesSunat();
    registrarFiltroEstadosSunat();
    inicializarTablaSunat();
    instalarEnvioMasivoSunat();

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

    $(document).on(
        "click",
        "#btnEnviarPendientesHoy",
        function () {
            iniciarEnvioMasivoPendientesHoy();
        }
    );

    $("#tbllistado").on(
        "draw.dt xhr.dt",
        function () {
            window.setTimeout(
                actualizarBotonEnvioMasivoSunat,
                40
            );
        }
    );
});

function activarFiltroInicialPendientesSunat() {
    filtroEstadoSunat = "PENDIENTES";

    $(".sunat-status-filter")
        .removeClass("is-active");

    $(
        '.sunat-status-filter[data-filtro="PENDIENTES"]'
    ).addClass("is-active");
}

function registrarFiltroEstadosSunat() {
    if (filtroSunatRegistrado) {
        return;
    }

    $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex) {
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

            /*
             * DataTables entrega en `data` el valor preparado para búsqueda.
             * En columnas HTML puede llegar como texto plano (por ejemplo
             * "No enviado"), perdiéndose data-estado="NO_ENVIADO".
             *
             * Por eso primero tomamos el dato ORIGINAL de la fila y luego
             * normalizamos ambos formatos. Esto corrige el caso en el que el
             * contador muestra 1 pendiente, pero la pestaña Pendientes queda
             * vacía.
             */
            let valorEstado = String(data[3] || "");

            try {
                const filaInterna =
                    settings.aoData
                    && settings.aoData[dataIndex]
                        ? settings.aoData[dataIndex]._aData
                        : null;

                if (
                    filaInterna
                    && typeof filaInterna === "object"
                    && filaInterna["3"] != null
                ) {
                    valorEstado = String(
                        filaInterna["3"]
                    );
                }
            } catch (error) {
                console.debug(
                    "No se pudo leer el estado original de la fila SUNAT.",
                    error
                );
            }

            const estado = normalizarEstadoSunat(
                valorEstado
            );

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
        },

        initComplete: function () {
            activarFiltroInicialPendientesSunat();

            if (tablaSunat) {
                tablaSunat.draw(false);
            }

            actualizarBotonEnvioMasivoSunat();
        }
    });
}

/*
|--------------------------------------------------------------------------
| ENVÍO MASIVO DE PENDIENTES DEL DÍA
|--------------------------------------------------------------------------
| - Solo documentos NO_ENVIADO.
| - Solo fecha de hoy.
| - Procesamiento SECUENCIAL, nunca en paralelo.
| - Orden por serie y correlativo ascendente.
|--------------------------------------------------------------------------
*/
function instalarEnvioMasivoSunat() {
    if ($("#btnEnviarPendientesHoy").length > 0) {
        actualizarBotonEnvioMasivoSunat();
        return;
    }

    const $filtros = $("#sunatStatusFilters");

    if ($filtros.length === 0) {
        return;
    }

    agregarEstilosEnvioMasivoSunat();

    const $acciones = $(
        '<div class="sunat-toolbar-actions"></div>'
    );

    const $boton = $(
        '<button type="button" '
        + 'class="btn btn-sm sunat-bulk-button" '
        + 'id="btnEnviarPendientesHoy" disabled>'
        + '<i class="fas fa-paper-plane mr-1"></i>'
        + '<span class="sunat-bulk-button-text">Enviar pendientes de hoy</span>'
        + '<span class="sunat-bulk-count" id="sunatBulkCount">0</span>'
        + '</button>'
    );

    $filtros.before($acciones);
    $acciones.append($boton);
    $acciones.append($filtros);

    actualizarBotonEnvioMasivoSunat();
}

function agregarEstilosEnvioMasivoSunat() {
    if (document.getElementById("sunatBulkStyles")) {
        return;
    }

    const style = document.createElement("style");
    style.id = "sunatBulkStyles";
    style.textContent = `
        .sunat-toolbar-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .sunat-bulk-button {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 0 13px;
            border: 1px solid #b7e4d5;
            border-radius: 10px;
            color: #067a5b;
            background: #ecfdf6;
            box-shadow: 0 4px 12px rgba(16, 185, 129, .08);
            font-size: .72rem;
            font-weight: 750;
            white-space: nowrap;
            transition: transform .18s ease,
                        box-shadow .18s ease,
                        border-color .18s ease,
                        background .18s ease;
        }

        .sunat-bulk-button:not(:disabled):hover,
        .sunat-bulk-button:not(:disabled):focus {
            color: #056447;
            border-color: #8fd3bc;
            background: #dff8ef;
            box-shadow: 0 7px 18px rgba(16, 185, 129, .15);
            transform: translateY(-1px);
            outline: none;
        }

        .sunat-bulk-button:disabled {
            cursor: not-allowed;
            opacity: .52;
            box-shadow: none;
        }

        .sunat-bulk-count {
            min-width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 4px;
            padding: 0 6px;
            border-radius: 999px;
            color: #ffffff;
            background: #0ca678;
            font-size: .64rem;
            font-weight: 800;
            line-height: 1;
        }

        .sunat-bulk-swal .swal2-html-container {
            margin: 1em 1.2em 0;
            overflow: visible;
        }

        .sunat-bulk-modal {
            text-align: left;
        }

        .sunat-bulk-current {
            display: flex;
            align-items: center;
            gap: 13px;
            min-height: 82px;
            padding: 14px;
            border: 1px solid #e5e9ee;
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
        }

        .sunat-bulk-current-icon {
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            color: #4f5fd1;
            background: #eef0ff;
            font-size: 1.05rem;
            transition: all .22s ease;
        }

        .sunat-bulk-current.is-sending .sunat-bulk-current-icon {
            animation: sunatBulkPulse 1.35s ease-in-out infinite;
        }

        .sunat-bulk-current.is-success .sunat-bulk-current-icon {
            color: #087f5b;
            background: #e7f8f1;
            animation: sunatBulkPop .28s ease-out;
        }

        .sunat-bulk-current.is-error .sunat-bulk-current-icon {
            color: #c92a2a;
            background: #fff0f0;
            animation: sunatBulkShake .3s ease-out;
        }

        .sunat-bulk-current-copy {
            min-width: 0;
            flex: 1 1 auto;
        }

        .sunat-bulk-current-copy small {
            display: block;
            margin-bottom: 3px;
            color: #98a2b3;
            font-size: .67rem;
            font-weight: 700;
            letter-spacing: .025em;
            text-transform: uppercase;
        }

        .sunat-bulk-current-copy strong {
            display: block;
            overflow: hidden;
            color: #2f3944;
            font-size: .92rem;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sunat-bulk-current-state {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 5px;
            color: #667085;
            font-size: .73rem;
            font-weight: 650;
        }

        .sunat-bulk-progress {
            height: 8px;
            margin: 14px 0 12px;
            overflow: hidden;
            border-radius: 999px;
            background: #edf0f3;
        }

        .sunat-bulk-progress > span {
            width: 0;
            height: 100%;
            display: block;
            border-radius: inherit;
            background: linear-gradient(90deg, #5f6de8, #10b981);
            transition: width .42s cubic-bezier(.22, 1, .36, 1);
        }

        .sunat-bulk-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }

        .sunat-bulk-stat {
            padding: 9px 10px;
            border: 1px solid #e6e9ed;
            border-radius: 10px;
            background: #fff;
        }

        .sunat-bulk-stat span {
            display: block;
            color: #98a2b3;
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .sunat-bulk-stat strong {
            display: block;
            margin-top: 2px;
            color: #344054;
            font-size: .9rem;
        }

        .sunat-bulk-log {
            max-height: 190px;
            overflow: auto;
            border: 1px solid #e5e9ee;
            border-radius: 11px;
            background: #fbfcfd;
        }

        .sunat-bulk-log-empty {
            padding: 14px;
            color: #98a2b3;
            font-size: .72rem;
            text-align: center;
        }

        .sunat-bulk-log-item {
            display: grid;
            grid-template-columns: 26px minmax(0, 1fr) auto;
            gap: 8px;
            align-items: center;
            padding: 9px 11px;
            border-bottom: 1px solid #eef1f3;
            animation: sunatBulkFadeIn .24s ease-out;
        }

        .sunat-bulk-log-item:last-child {
            border-bottom: 0;
        }

        .sunat-bulk-log-icon {
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: .66rem;
        }

        .sunat-bulk-log-item.is-success .sunat-bulk-log-icon {
            color: #087f5b;
            background: #dff6ec;
        }

        .sunat-bulk-log-item.is-error .sunat-bulk-log-icon {
            color: #c92a2a;
            background: #ffe3e3;
        }

        .sunat-bulk-log-main {
            min-width: 0;
        }

        .sunat-bulk-log-main strong {
            display: block;
            overflow: hidden;
            color: #3b4651;
            font-size: .72rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sunat-bulk-log-main small {
            display: block;
            overflow: hidden;
            margin-top: 1px;
            color: #98a2b3;
            font-size: .62rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sunat-bulk-log-result {
            font-size: .64rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .sunat-bulk-log-item.is-success .sunat-bulk-log-result {
            color: #087f5b;
        }

        .sunat-bulk-log-item.is-error .sunat-bulk-log-result {
            color: #c92a2a;
        }

        .sunat-bulk-preview {
            max-height: 215px;
            overflow: auto;
            margin-top: 12px;
            padding: 4px;
            border: 1px solid #e7eaee;
            border-radius: 11px;
            background: #fafbfc;
            text-align: left;
        }

        .sunat-bulk-preview-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 9px;
            border-bottom: 1px solid #eceff2;
            color: #4b5563;
            font-size: .72rem;
        }

        .sunat-bulk-preview-row:last-child {
            border-bottom: 0;
        }

        .sunat-bulk-preview-row strong {
            color: #344054;
        }

        @keyframes sunatBulkPulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(79, 95, 209, .12);
                transform: scale(1);
            }
            50% {
                box-shadow: 0 0 0 9px rgba(79, 95, 209, 0);
                transform: scale(1.035);
            }
        }

        @keyframes sunatBulkPop {
            0% { transform: scale(.82); }
            70% { transform: scale(1.08); }
            100% { transform: scale(1); }
        }

        @keyframes sunatBulkShake {
            0%, 100% { transform: translateX(0); }
            30% { transform: translateX(-3px); }
            60% { transform: translateX(3px); }
        }

        @keyframes sunatBulkFadeIn {
            from {
                opacity: 0;
                transform: translateY(4px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 991.98px) {
            .sunat-toolbar-actions {
                width: 100%;
                align-items: stretch;
                justify-content: flex-start;
            }

            .sunat-bulk-button {
                width: 100%;
            }

            .sunat-toolbar-actions .sunat-status-filters {
                width: 100%;
            }
        }

        @media (max-width: 575.98px) {
            .sunat-bulk-stats {
                grid-template-columns: 1fr;
            }

            .sunat-bulk-current {
                align-items: flex-start;
            }
        }
    `;

    document.head.appendChild(style);
}

function actualizarBotonEnvioMasivoSunat() {
    const $boton = $("#btnEnviarPendientesHoy");

    if ($boton.length === 0) {
        return;
    }

    const documentos = obtenerPendientesHoySunat();
    const cantidad = documentos.length;

    $("#sunatBulkCount").text(
        cantidad > 99 ? "99+" : String(cantidad)
    );

    $boton.prop(
        "disabled",
        envioMasivoSunatActivo || cantidad === 0
    );

    $boton.attr(
        "title",
        cantidad === 0
            ? "No hay comprobantes pendientes de envío con fecha de hoy."
            : cantidad === 1
                ? "Enviar el comprobante pendiente de hoy."
                : "Enviar " + cantidad + " comprobantes pendientes de hoy en orden correlativo."
    );
}

function obtenerPendientesHoySunat() {
    if (
        !tablaSunat
        || !$.fn.DataTable.isDataTable("#tbllistado")
    ) {
        return [];
    }

    const filas = tablaSunat
        .rows()
        .data()
        .toArray();

    const documentos = filas
        .map(filaADocumentoMasivoSunat)
        .filter(Boolean)
        .filter(function (documento) {
            return documento.estado === "NO_ENVIADO"
                && esFechaHoySunat(documento.fecha);
        });

    documentos.sort(function (a, b) {
        const serieA = String(a.serie || "");
        const serieB = String(b.serie || "");

        const comparacionSerie = serieA.localeCompare(
            serieB,
            "es",
            {
                numeric: true,
                sensitivity: "base"
            }
        );

        if (comparacionSerie !== 0) {
            return comparacionSerie;
        }

        if (a.correlativo !== b.correlativo) {
            return a.correlativo - b.correlativo;
        }

        return a.id - b.id;
    });

    return documentos;
}

function filaADocumentoMasivoSunat(fila) {
    if (!fila || typeof fila !== "object") {
        return null;
    }

    const estado = normalizarEstadoSunat(
        fila["3"]
    );

    const fecha = textoPlanoSunat(
        fila["4"]
    );

    const accionHtml = String(
        fila["5"] || ""
    );

    const coincidenciaAccion = accionHtml.match(
        /enviarSunatManual\(\s*['"]([^'"]+)['"]\s*,\s*(\d+)\s*\)/i
    );

    if (!coincidenciaAccion) {
        return null;
    }

    const tipoRegistro = normalizarTipoRegistroJs(
        coincidenciaAccion[1]
    );

    const id = Number.parseInt(
        coincidenciaAccion[2],
        10
    ) || 0;

    if (id <= 0) {
        return null;
    }

    const documentoHtml = String(
        fila["0"] || ""
    );

    const $documento = $("<div>")
        .html(documentoHtml);

    let numero = $documento
        .find("strong")
        .first()
        .text()
        .trim();

    if (numero === "") {
        numero = textoPlanoSunat(
            documentoHtml
        );
    }

    const textoDocumento = normalizarTextoSunat(
        documentoHtml
    );

    let etiqueta = "Boleta";

    if (tipoRegistro === "NOTA_CREDITO") {
        etiqueta = "Nota de crédito";
    } else if (textoDocumento.includes("FACTURA")) {
        etiqueta = "Factura";
    } else if (textoDocumento.includes("BOLETA")) {
        etiqueta = "Boleta";
    } else {
        etiqueta = "Comprobante";
    }

    const correlativo = extraerCorrelativoSunat(
        numero
    );

    return {
        tipoRegistro: tipoRegistro,
        id: id,
        estado: estado,
        fecha: fecha,
        numero: numero,
        etiqueta: etiqueta,
        serie: correlativo.serie,
        correlativo: correlativo.numero,
        cliente: textoPlanoSunat(fila["1"]),
        total: textoPlanoSunat(fila["2"])
    };
}

function extraerCorrelativoSunat(comprobante) {
    const texto = String(comprobante || "")
        .trim()
        .toUpperCase();

    const coincidencia = texto.match(
        /([A-Z0-9]{1,10})\s*-\s*(\d{1,20})/
    );

    if (!coincidencia) {
        return {
            serie: texto,
            numero: Number.MAX_SAFE_INTEGER
        };
    }

    const numero = Number.parseInt(
        coincidencia[2],
        10
    );

    return {
        serie: coincidencia[1],
        numero: Number.isFinite(numero)
            ? numero
            : Number.MAX_SAFE_INTEGER
    };
}

function esFechaHoySunat(fechaTexto) {
    const texto = String(fechaTexto || "").trim();
    const coincidencia = texto.match(
        /^(\d{1,2})\/(\d{1,2})\/(\d{4})/
    );

    if (!coincidencia) {
        return false;
    }

    const ahora = new Date();

    return Number.parseInt(coincidencia[1], 10) === ahora.getDate()
        && Number.parseInt(coincidencia[2], 10) === (ahora.getMonth() + 1)
        && Number.parseInt(coincidencia[3], 10) === ahora.getFullYear();
}

function iniciarEnvioMasivoPendientesHoy() {
    if (envioMasivoSunatActivo) {
        return;
    }

    const documentos = obtenerPendientesHoySunat();

    if (documentos.length === 0) {
        Swal.fire({
            icon: "info",
            title: "No hay pendientes de hoy",
            text:
                "No se encontraron comprobantes con estado No enviado y fecha de hoy."
        });
        return;
    }

    const vistaPrevia = documentos
        .map(function (documento, indice) {
            return (
                '<div class="sunat-bulk-preview-row">'
                + '<span>'
                + '<strong>'
                + escaparHtmlSunat(
                    (indice + 1) + ". "
                    + documento.etiqueta
                )
                + '</strong>'
                + '</span>'
                + '<span>'
                + escaparHtmlSunat(documento.numero)
                + '</span>'
                + '</div>'
            );
        })
        .join("");

    Swal.fire({
        icon: "question",
        title:
            documentos.length === 1
                ? "Enviar pendiente de hoy"
                : "Enviar pendientes de hoy",
        html:
            '<div style="text-align:left;color:#667085;font-size:.79rem;line-height:1.5">'
            + 'Se enviarán <strong>'
            + documentos.length
            + '</strong> documento(s) <strong>uno por uno</strong>, '
            + 'respetando el orden de serie y correlativo. '
            + 'El siguiente envío comenzará únicamente cuando termine el anterior.'
            + '</div>'
            + '<div class="sunat-bulk-preview">'
            + vistaPrevia
            + '</div>',
        showCancelButton: true,
        confirmButtonText:
            '<i class="fas fa-paper-plane mr-1"></i> Iniciar envío',
        cancelButtonText: "Cancelar",
        reverseButtons: true,
        allowOutsideClick: false,
        width: 640
    }).then(function (resultado) {
        if (!resultado.isConfirmed) {
            return;
        }

        ejecutarEnvioMasivoSunat(documentos);
    });
}

async function ejecutarEnvioMasivoSunat(documentos) {
    envioMasivoSunatActivo = true;
    actualizarBotonEnvioMasivoSunat();

    let enviados = 0;
    let errores = 0;
    const fallidos = [];

    mostrarModalEnvioMasivoSunat(
        documentos.length
    );

    for (
        let indice = 0;
        indice < documentos.length;
        indice += 1
    ) {
        const documento = documentos[indice];

        actualizarDocumentoActualMasivoSunat(
            documento,
            indice,
            documentos.length,
            "ENVIANDO"
        );

        const resultado = await enviarDocumentoMasivoSunat(
            documento
        );

        if (resultado.ok) {
            enviados += 1;

            actualizarDocumentoActualMasivoSunat(
                documento,
                indice,
                documentos.length,
                "ENVIADO",
                resultado.estado
            );

            agregarLogEnvioMasivoSunat(
                documento,
                true,
                resultado.mensaje,
                resultado.estado
            );

            await esperarSunat(520);
        } else {
            errores += 1;

            fallidos.push({
                documento: documento,
                mensaje: resultado.mensaje,
                estado: resultado.estado
            });

            actualizarDocumentoActualMasivoSunat(
                documento,
                indice,
                documentos.length,
                "ERROR",
                resultado.estado
            );

            agregarLogEnvioMasivoSunat(
                documento,
                false,
                resultado.mensaje,
                resultado.estado
            );

            await esperarSunat(760);
        }

        actualizarEstadisticasMasivoSunat(
            indice + 1,
            documentos.length,
            enviados,
            errores
        );
    }

    envioMasivoSunatActivo = false;

    if (
        tablaSunat
        && $.fn.DataTable.isDataTable("#tbllistado")
    ) {
        tablaSunat.ajax.reload(
            function () {
                actualizarBotonEnvioMasivoSunat();
            },
            false
        );
    }

    actualizarContadorGlobalSunat();

    await esperarSunat(280);

    const htmlFallidos = fallidos.length > 0
        ? (
            '<div style="margin-top:12px;text-align:left">'
            + '<strong style="display:block;margin-bottom:6px;color:#7a2e2e;font-size:.75rem">Documentos con error</strong>'
            + '<div class="sunat-bulk-preview">'
            + fallidos.map(function (item) {
                return (
                    '<div class="sunat-bulk-preview-row">'
                    + '<span><strong>'
                    + escaparHtmlSunat(
                        item.documento.etiqueta
                        + " "
                        + item.documento.numero
                    )
                    + '</strong></span>'
                    + '<span style="color:#c92a2a">'
                    + escaparHtmlSunat(
                        item.estado || "Error"
                    )
                    + '</span>'
                    + '</div>'
                );
            }).join("")
            + '</div>'
            + '</div>'
        )
        : '';

    Swal.fire({
        icon: errores > 0 ? "warning" : "success",
        title:
            errores > 0
                ? "Envío masivo finalizado"
                : "Pendientes enviados",
        html:
            '<div style="font-size:.82rem;color:#667085">'
            + '<strong style="color:#087f5b">'
            + enviados
            + '</strong> enviados correctamente'
            + (errores > 0
                ? ' · <strong style="color:#c92a2a">'
                    + errores
                    + '</strong> con error'
                : '')
            + '.</div>'
            + htmlFallidos,
        confirmButtonText: "Cerrar",
        width: 620
    });
}

function mostrarModalEnvioMasivoSunat(total) {
    Swal.fire({
        title: "Enviando a SUNAT",
        html:
            '<div class="sunat-bulk-modal">'
            + '<div class="sunat-bulk-current is-sending" id="sunatBulkCurrent">'
            + '<div class="sunat-bulk-current-icon" id="sunatBulkCurrentIcon">'
            + '<i class="fas fa-paper-plane"></i>'
            + '</div>'
            + '<div class="sunat-bulk-current-copy">'
            + '<small id="sunatBulkPaso">Preparando envío</small>'
            + '<strong id="sunatBulkDocumentoActual">—</strong>'
            + '<span class="sunat-bulk-current-state" id="sunatBulkEstadoActual">'
            + '<i class="fas fa-circle-notch fa-spin"></i> Preparando...'
            + '</span>'
            + '</div>'
            + '</div>'
            + '<div class="sunat-bulk-progress">'
            + '<span id="sunatBulkProgressBar"></span>'
            + '</div>'
            + '<div class="sunat-bulk-stats">'
            + '<div class="sunat-bulk-stat"><span>Procesados</span><strong id="sunatBulkProcesados">0/'
            + total
            + '</strong></div>'
            + '<div class="sunat-bulk-stat"><span>Enviados</span><strong id="sunatBulkEnviados">0</strong></div>'
            + '<div class="sunat-bulk-stat"><span>Errores</span><strong id="sunatBulkErrores">0</strong></div>'
            + '</div>'
            + '<div class="sunat-bulk-log" id="sunatBulkLog">'
            + '<div class="sunat-bulk-log-empty" id="sunatBulkLogEmpty">Aquí verás cada envío conforme se complete.</div>'
            + '</div>'
            + '</div>',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        width: 650,
        customClass: {
            popup: "sunat-bulk-swal"
        }
    });
}

function actualizarDocumentoActualMasivoSunat(
    documento,
    indice,
    total,
    estadoVisual,
    estadoApi = ""
) {
    const $actual = $("#sunatBulkCurrent");
    const $icono = $("#sunatBulkCurrentIcon");
    const $estado = $("#sunatBulkEstadoActual");

    if ($actual.length === 0) {
        return;
    }

    $actual.removeClass(
        "is-sending is-success is-error"
    );

    $("#sunatBulkPaso").text(
        "Documento "
        + (indice + 1)
        + " de "
        + total
    );

    $("#sunatBulkDocumentoActual").text(
        documento.etiqueta
        + " "
        + documento.numero
    );

    if (estadoVisual === "ENVIANDO") {
        $actual.addClass("is-sending");
        $icono.html(
            '<i class="fas fa-paper-plane"></i>'
        );
        $estado.html(
            '<i class="fas fa-circle-notch fa-spin"></i> Enviando...'
        );
        return;
    }

    if (estadoVisual === "ENVIADO") {
        $actual.addClass("is-success");
        $icono.html(
            '<i class="fas fa-check"></i>'
        );
        $estado.html(
            '<i class="fas fa-check-circle"></i> Enviado'
            + (
                estadoApi
                    ? ' · ' + escaparHtmlSunat(estadoApi)
                    : ''
            )
        );
        return;
    }

    $actual.addClass("is-error");
    $icono.html(
        '<i class="fas fa-exclamation"></i>'
    );
    $estado.html(
        '<i class="fas fa-times-circle"></i> No enviado'
        + (
            estadoApi
                ? ' · ' + escaparHtmlSunat(estadoApi)
                : ''
        )
    );
}

function actualizarEstadisticasMasivoSunat(
    procesados,
    total,
    enviados,
    errores
) {
    $("#sunatBulkProcesados").text(
        procesados + "/" + total
    );
    $("#sunatBulkEnviados").text(enviados);
    $("#sunatBulkErrores").text(errores);

    const porcentaje = total > 0
        ? Math.round((procesados / total) * 100)
        : 0;

    $("#sunatBulkProgressBar").css(
        "width",
        porcentaje + "%"
    );
}

function agregarLogEnvioMasivoSunat(
    documento,
    correcto,
    mensaje,
    estadoApi
) {
    const $log = $("#sunatBulkLog");

    if ($log.length === 0) {
        return;
    }

    $("#sunatBulkLogEmpty").remove();

    const clase = correcto
        ? "is-success"
        : "is-error";

    const icono = correcto
        ? "fa-check"
        : "fa-times";

    const resultado = correcto
        ? "ENVIADO"
        : (estadoApi || "ERROR");

    const mensajeCorto = String(mensaje || "")
        .replace(/\s+/g, " ")
        .trim();

    const html =
        '<div class="sunat-bulk-log-item '
        + clase
        + '">'
        + '<span class="sunat-bulk-log-icon"><i class="fas '
        + icono
        + '"></i></span>'
        + '<div class="sunat-bulk-log-main">'
        + '<strong>'
        + escaparHtmlSunat(
            documento.etiqueta
            + " "
            + documento.numero
        )
        + '</strong>'
        + '<small title="'
        + escaparHtmlSunat(mensajeCorto)
        + '">'
        + escaparHtmlSunat(
            mensajeCorto || (
                correcto
                    ? "Solicitud procesada por APISUNAT."
                    : "No se pudo completar el envío."
            )
        )
        + '</small>'
        + '</div>'
        + '<span class="sunat-bulk-log-result">'
        + escaparHtmlSunat(resultado)
        + '</span>'
        + '</div>';

    $log.append(html);
    $log.scrollTop($log[0].scrollHeight);
}

function enviarDocumentoMasivoSunat(documento) {
    return new Promise(function (resolve) {
        $.ajax({
            url:
                "Controllers/Sunat.php"
                + "?op=enviarsunat",
            type: "POST",
            dataType: "json",
            cache: false,
            timeout: 90000,
            data: {
                tipo_registro: documento.tipoRegistro,
                id: documento.id
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
                    respuesta && respuesta.message
                        ? respuesta.message
                        : (
                            resultado.mensaje
                            || resultado.message
                            || "APISUNAT no devolvió un mensaje."
                        )
                );

                resolve({
                    ok:
                        respuesta
                        && respuesta.status === true,
                    estado: estado,
                    mensaje: mensaje,
                    respuesta: respuesta
                });
            },

            error: function (xhr, textStatus) {
                resolve({
                    ok: false,
                    estado:
                        textStatus === "timeout"
                            ? "TIMEOUT"
                            : "ERROR",
                    mensaje: obtenerMensajeAjaxSunat(
                        xhr,
                        textStatus === "timeout"
                            ? "El envío superó el tiempo de espera."
                            : "No se pudo completar el envío."
                    ),
                    respuesta: null
                });
            }
        });
    });
}

function esperarSunat(milisegundos) {
    return new Promise(function (resolve) {
        window.setTimeout(resolve, milisegundos);
    });
}

function normalizarEstadoSunat(valor) {
    const html = String(valor || "");
    const coincidencia = html.match(
        /data-estado=["']([^"']+)["']/i
    );

    let estado = coincidencia
        ? String(coincidencia[1] || "")
        : normalizarTextoSunat(html);

    estado = estado
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim()
        .toUpperCase()
        .replace(/[\s-]+/g, "_")
        .replace(/_+/g, "_");

    const equivalencias = {
        NOENVIADO: "NO_ENVIADO",
        NO_ENVIADO: "NO_ENVIADO",
        PENDIENTE: "PENDIENTE",
        ENPROCESO: "EN_PROCESO",
        EN_PROCESO: "EN_PROCESO",
        ENVIADO: "ENVIADO",
        ACEPTADO: "ACEPTADO",
        RECHAZADO: "RECHAZADO",
        EXCEPCION: "EXCEPCION",
        ERROR: "ERROR",
        ANULADO: "ANULADO"
    };

    return equivalencias[estado]
        || equivalencias[estado.replace(/_/g, "")]
        || estado;
}

function textoPlanoSunat(valor) {
    return $("<div>")
        .html(String(valor == null ? "" : valor))
        .text()
        .replace(/\s+/g, " ")
        .trim();
}

/*
|--------------------------------------------------------------------------
| ENVÍO INDIVIDUAL
|--------------------------------------------------------------------------
*/
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

            actualizarBotonEnvioMasivoSunat();

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
