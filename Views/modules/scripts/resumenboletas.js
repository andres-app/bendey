"use strict";

let rbPendientes = [];
let rbResumenes = [];

$(document).ready(function () {
    rbRegistrarEventos();
    rbCargarPendientes();
    rbCargarResumenes();
});

function rbRegistrarEventos() {
    $(document).on("click", ".rb-tab", function () {
        const tab = String($(this).data("rb-tab") || "pendientes");
        $(".rb-tab").removeClass("active");
        $(this).addClass("active");
        $(".rb-panel").removeClass("active");
        $(`.rb-panel[data-rb-panel="${tab}"]`).addClass("active");
    });

    $(document).on("change", "#rbFecha", rbCargarPendientes);
    $(document).on("click", "#rbActualizarPendientes", rbCargarPendientes);
    $(document).on("click", "#rbActualizarResumenes", rbCargarResumenes);

    $(document).on("change", "#rbSeleccionarTodos", function () {
        $(".rb-check-venta").prop("checked", $(this).is(":checked"));
        rbActualizarSeleccion();
    });

    $(document).on("change", ".rb-check-venta", rbActualizarSeleccion);
    $(document).on("click", "#rbCrearResumen", rbCrearResumen);

    $(document).on("click", ".rb-ver-detalle", function () {
        rbVerDetalle(Number.parseInt($(this).data("id"), 10) || 0);
    });

    $(document).on("click", ".rb-descartar", function () {
        rbDescartarResumen(Number.parseInt($(this).data("id"), 10) || 0);
    });
}

function rbEscapar(valor) {
    return String(valor ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function rbMoneda(valor) {
    return "S/ " + (Number.parseFloat(valor) || 0).toFixed(2);
}

function rbMensajeAjax(xhr, respaldo) {
    if (xhr && xhr.responseJSON && xhr.responseJSON.mensaje) {
        return String(xhr.responseJSON.mensaje);
    }

    try {
        const data = JSON.parse(String(xhr.responseText || ""));
        if (data && data.mensaje) return String(data.mensaje);
    } catch (_) {}

    return respaldo;
}

function rbCargarPendientes() {
    const fecha = String($("#rbFecha").val() || "");

    $("#rbPendientesBody").html(
        '<tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm mr-2"></span>Cargando boletas...</td></tr>'
    );
    $("#rbPendientesVacio").hide();

    $.ajax({
        url: "Controllers/ResumenBoletas.php",
        type: "GET",
        dataType: "json",
        cache: false,
        data: { op: "pendientes", fecha: fecha, v: Date.now() }
    }).done(function (respuesta) {
        rbPendientes = respuesta && Array.isArray(respuesta.data) ? respuesta.data : [];
        rbRenderPendientes();
        $("#rbMetricPendientes").text(rbPendientes.length);
        $("#rbMetricTotal").text(rbMoneda(respuesta && respuesta.total));
    }).fail(function (xhr) {
        rbPendientes = [];
        rbRenderPendientes();
        Swal.fire("No se pudo cargar", rbMensajeAjax(xhr, "No fue posible obtener las boletas pendientes."), "error");
    });
}

function rbRenderPendientes() {
    const $body = $("#rbPendientesBody");
    $body.empty();
    $("#rbSeleccionarTodos").prop("checked", false);

    if (rbPendientes.length === 0) {
        $("#rbTablaPendientes").hide();
        $("#rbPendientesVacio").show();
        rbActualizarSeleccion();
        return;
    }

    $("#rbTablaPendientes").show();
    $("#rbPendientesVacio").hide();

    rbPendientes.forEach(function (venta) {
        const id = Number.parseInt(venta.idventa, 10) || 0;
        $body.append(`
            <tr>
                <td><input type="checkbox" class="rb-check-venta" value="${id}" aria-label="Seleccionar ${rbEscapar(venta.comprobante)}"></td>
                <td><div class="rb-doc"><strong>${rbEscapar(venta.comprobante)}</strong><small>Boleta electrónica · 03</small></div></td>
                <td>${rbEscapar(venta.cliente)}</td>
                <td>${rbEscapar(venta.fecha)}</td>
                <td class="text-right font-weight-bold">${rbMoneda(venta.total_venta)}</td>
                <td><span class="rb-state rb-state-pending">Pendiente de RC</span></td>
            </tr>
        `);
    });

    rbActualizarSeleccion();
}

function rbActualizarSeleccion() {
    const ids = [];
    let total = 0;

    $(".rb-check-venta:checked").each(function () {
        const id = Number.parseInt($(this).val(), 10) || 0;
        const venta = rbPendientes.find(function (item) {
            return (Number.parseInt(item.idventa, 10) || 0) === id;
        });
        if (id > 0) ids.push(id);
        if (venta) total += Number.parseFloat(venta.total_venta) || 0;
    });

    $("#rbSeleccionadas").text(ids.length);
    $("#rbTotalSeleccionado").text(rbMoneda(total));
    $("#rbCrearResumen").prop("disabled", ids.length === 0);

    const todos = rbPendientes.length > 0 && ids.length === rbPendientes.length;
    $("#rbSeleccionarTodos").prop("checked", todos);
}

function rbCrearResumen() {
    const ventas = $(".rb-check-venta:checked").map(function () {
        return Number.parseInt($(this).val(), 10) || 0;
    }).get().filter(function (id) { return id > 0; });

    const fecha = String($("#rbFecha").val() || "");

    if (ventas.length === 0) return;

    Swal.fire({
        icon: "question",
        title: "Crear Resumen Diario",
        text: `Se agruparán ${ventas.length} boleta(s) del ${fecha}.`,
        showCancelButton: true,
        confirmButtonText: "Crear resumen",
        cancelButtonText: "Cancelar"
    }).then(function (resultado) {
        if (!resultado.isConfirmed) return;

        const $boton = $("#rbCrearResumen");
        const original = $boton.html();
        $boton.prop("disabled", true).html('<span class="spinner-border spinner-border-sm mr-2"></span>Creando...');

        $.ajax({
            url: "Controllers/ResumenBoletas.php?op=crear",
            type: "POST",
            dataType: "json",
            data: { fecha: fecha, ventas: JSON.stringify(ventas) }
        }).done(function (respuesta) {
            const data = respuesta && respuesta.data ? respuesta.data : {};
            Swal.fire({
                icon: "success",
                title: "Resumen preparado",
                text: `${String(data.codigo_resumen || "RC")} · ${Number(data.cantidad_documentos || 0)} boleta(s) · ${rbMoneda(data.total_documentos)}`
            });
            rbCargarPendientes();
            rbCargarResumenes();
        }).fail(function (xhr) {
            Swal.fire("No se creó el resumen", rbMensajeAjax(xhr, "Revise las boletas seleccionadas y vuelva a intentarlo."), "error");
        }).always(function () {
            $boton.html(original);
            rbActualizarSeleccion();
        });
    });
}

function rbCargarResumenes() {
    $("#rbResumenesBody").html(
        '<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm mr-2"></span>Cargando resúmenes...</td></tr>'
    );
    $("#rbResumenesVacio").hide();

    $.ajax({
        url: "Controllers/ResumenBoletas.php",
        type: "GET",
        dataType: "json",
        cache: false,
        data: { op: "resumenes", v: Date.now() }
    }).done(function (respuesta) {
        rbResumenes = respuesta && Array.isArray(respuesta.data) ? respuesta.data : [];
        rbRenderResumenes();
        $("#rbMetricResumenes").text(rbResumenes.length);
    }).fail(function (xhr) {
        rbResumenes = [];
        rbRenderResumenes();
        Swal.fire("No se pudo cargar", rbMensajeAjax(xhr, "No fue posible obtener el historial de resúmenes."), "error");
    });
}

function rbBadgeEstado(estadoOriginal) {
    const estado = String(estadoOriginal || "NO_ENVIADO").toUpperCase();
    if (estado === "ACEPTADO") return '<span class="rb-state rb-state-ok">Aceptado</span>';
    if (["RECHAZADO", "ERROR", "EXCEPCION"].includes(estado)) return `<span class="rb-state rb-state-error">${rbEscapar(estado)}</span>`;
    if (["PENDIENTE", "EN_PROCESO", "ENVIADO"].includes(estado)) return `<span class="rb-state rb-state-process">${rbEscapar(estado)}</span>`;
    return '<span class="rb-state rb-state-pending">Preparado</span>';
}

function rbRenderResumenes() {
    const $body = $("#rbResumenesBody");
    $body.empty();

    if (rbResumenes.length === 0) {
        $("#rbResumenesVacio").show();
        return;
    }

    $("#rbResumenesVacio").hide();

    rbResumenes.forEach(function (resumen) {
        const id = Number.parseInt(resumen.idresumen, 10) || 0;
        const estado = String(resumen.estado_sunat || "NO_ENVIADO").toUpperCase();
        const sePuedeDescartar = estado === "NO_ENVIADO" && !String(resumen.document_id || "").trim() && !String(resumen.ticket || "").trim();

        $body.append(`
            <tr>
                <td><div class="rb-doc"><strong>${rbEscapar(resumen.codigo_resumen)}</strong><small>Creado ${rbEscapar(resumen.fecha_generacion)}</small></div></td>
                <td>${rbEscapar(resumen.fecha_documentos_texto)}</td>
                <td class="text-center">${Number.parseInt(resumen.cantidad_documentos, 10) || 0}</td>
                <td class="text-right font-weight-bold">${rbMoneda(resumen.total_documentos)}</td>
                <td>${rbEscapar(resumen.ticket || "—")}</td>
                <td>${rbBadgeEstado(estado)}</td>
                <td class="text-right" style="white-space:nowrap;">
                    <button type="button" class="btn btn-outline-primary btn-sm rb-ver-detalle" data-id="${id}"><i class="far fa-eye"></i></button>
                    ${sePuedeDescartar ? `<button type="button" class="btn btn-outline-danger btn-sm rb-descartar" data-id="${id}" title="Descartar"><i class="far fa-trash-alt"></i></button>` : ""}
                </td>
            </tr>
        `);
    });
}

function rbVerDetalle(idresumen) {
    if (idresumen <= 0) return;

    $.ajax({
        url: "Controllers/ResumenBoletas.php",
        type: "GET",
        dataType: "json",
        cache: false,
        data: { op: "detalle", idresumen: idresumen, v: Date.now() }
    }).done(function (respuesta) {
        const paquete = respuesta && respuesta.data ? respuesta.data : {};
        const resumen = paquete.resumen || {};
        const detalle = Array.isArray(paquete.detalle) ? paquete.detalle : [];
        let total = 0;
        let html = "";

        detalle.forEach(function (fila) {
            total += Number.parseFloat(fila.total_venta) || 0;
            html += `<tr><td><strong>${rbEscapar(fila.comprobante)}</strong></td><td>${rbEscapar(fila.cliente)}</td><td>${rbEscapar(fila.fecha_venta)}</td><td class="text-right font-weight-bold">${rbMoneda(fila.total_venta)}</td></tr>`;
        });

        $("#rbDetalleTitulo").text(String(resumen.codigo_resumen || "Resumen Diario"));
        $("#rbDetalleSubtitulo").text(`Fecha de documentos: ${String(resumen.fecha_documentos_texto || "—")} · Estado: ${String(resumen.estado_sunat || "NO_ENVIADO")}`);
        $("#rbDetalleBody").html(html || '<tr><td colspan="4" class="text-center py-4 text-muted">Sin detalle.</td></tr>');
        $("#rbDetalleCantidad").text(`${detalle.length} boleta(s)`);
        $("#rbDetalleTotal").text(rbMoneda(total));
        $("#rbDetalleModal").modal("show");
    }).fail(function (xhr) {
        Swal.fire("No se pudo abrir", rbMensajeAjax(xhr, "No fue posible obtener el detalle del resumen."), "error");
    });
}

function rbDescartarResumen(idresumen) {
    if (idresumen <= 0) return;

    Swal.fire({
        icon: "warning",
        title: "Descartar resumen",
        text: "Las boletas volverán a la bandeja de pendientes. Solo es posible antes del envío.",
        showCancelButton: true,
        confirmButtonText: "Descartar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#d33"
    }).then(function (resultado) {
        if (!resultado.isConfirmed) return;

        $.ajax({
            url: "Controllers/ResumenBoletas.php?op=descartar",
            type: "POST",
            dataType: "json",
            data: { idresumen: idresumen }
        }).done(function (respuesta) {
            Swal.fire("Resumen descartado", String(respuesta.mensaje || "Las boletas volvieron a pendientes."), "success");
            rbCargarPendientes();
            rbCargarResumenes();
        }).fail(function (xhr) {
            Swal.fire("No se pudo descartar", rbMensajeAjax(xhr, "El resumen ya no puede modificarse."), "error");
        });
    });
}
