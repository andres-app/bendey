let productosCache = [];
let categoriasCache = [];
let datosProductoRapidoCache = {
    categorias: [],
    subcategorias: [],
    medidas: [],
    almacenes: []
};
let datosProductoRapidoCargados = false;
let guardandoProductoRapido = false;

const CLIENTE_GENERICO = Object.freeze({
    tipoDocumento: 'DNI',
    numeroDocumento: '99999999',
    nombre: 'CLIENTE VARIOS',
    direccion: '-'
});

function asegurarCampoClienteGenerico() {
    const formulario = document.getElementById('formularioVenta');

    if (!formulario || document.getElementById('cliente_generico')) {
        return;
    }

    const input = document.createElement('input');

    input.type = 'hidden';
    input.id = 'cliente_generico';
    input.name = 'cliente_generico';
    input.value = '0';

    formulario.appendChild(input);
}

function textoNormalizado(valor) {
    return String(valor || '')
        .trim()
        .toUpperCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function esFacturaSeleccionada() {
    return textoNormalizado(
        $('#tipo_comprobante option:selected').text()
        || $('#tipo_comprobante').val()
    ).includes('FACTURA');
}

function esBoletaSeleccionada() {
    return textoNormalizado(
        $('#tipo_comprobante option:selected').text()
        || $('#tipo_comprobante').val()
    ).includes('BOLETA');
}

function limpiarDatosCliente(mantenerDocumentoVisible = true) {
    const documentoVisible = mantenerDocumentoVisible
        ? String($('#num_documento').val() || '').replace(/\D/g, '')
        : '';

    $('#idcliente').val('');
    $('#cliente_generico').val('0');
    $('#tipo_documento').val('');
    $('#num_doc_real').val('');
    $('#nombre_cli').val('');
    $('#direccion').val('');
    $('#email').val('');

    $('#num_documento')
        .val(documentoVisible)
        .removeClass('is-invalid');

    $('#nombre_cliente')
        .removeClass('text-primary text-success text-danger')
        .addClass('text-muted')
        .text(
            esFacturaSeleccionada()
                ? 'Ingrese un RUC válido de 11 dígitos.'
                : 'Déjelo vacío para usar CLIENTE VARIOS.'
        );
}

function usarClienteGenerico(mostrarMensaje = true) {
    if (esFacturaSeleccionada()) {
        if (mostrarMensaje) {
            Swal.fire(
                'Factura',
                'Para emitir una factura debe ingresar un RUC válido.',
                'warning'
            );
        }

        return false;
    }

    $('#idcliente').val('');
    $('#cliente_generico').val('1');
    $('#tipo_documento').val(CLIENTE_GENERICO.tipoDocumento);
    $('#num_doc_real').val(CLIENTE_GENERICO.numeroDocumento);
    $('#nombre_cli').val(CLIENTE_GENERICO.nombre);
    $('#direccion').val(CLIENTE_GENERICO.direccion);
    $('#email').val('');

    $('#num_documento')
        .val('')
        .removeClass('is-invalid');

    return true;
}

function actualizarReglaCliente() {
    const $documento = $('#num_documento');

    if (esFacturaSeleccionada()) {
        $documento
            .attr('placeholder', 'RUC de 11 dígitos')
            .attr('maxlength', '11')
            .prop('required', true);

        if ($('#cliente_generico').val() === '1') {
            limpiarDatosCliente(false);
        }

        $('#nombre_cliente')
            .removeClass('text-primary text-success text-danger')
            .addClass('text-muted')
            .text('Ingrese un RUC válido de 11 dígitos.');

        return;
    }

    $documento
        .attr('placeholder', 'DNI o RUC')
        .attr('maxlength', '11')
        .prop('required', false);

    $('#nombre_cliente')
        .removeClass('text-primary text-success text-danger')
        .addClass('text-muted')
        .text('Déjelo vacío para usar CLIENTE VARIOS.');
}

function validarClienteAntesDeVender(totalVenta) {
    const documentoVisible = String(
        $('#num_documento').val() || ''
    ).replace(/\D/g, '');

    const documentoReal = String(
        $('#num_doc_real').val() || documentoVisible
    ).replace(/\D/g, '');

    const esGenerico =
        $('#cliente_generico').val() === '1'
        || documentoReal === CLIENTE_GENERICO.numeroDocumento;

    if (esFacturaSeleccionada()) {
        if (
            esGenerico
            || !/^\d{11}$/.test(documentoReal)
        ) {
            Swal.fire(
                'RUC obligatorio',
                'Para emitir una factura debe consultar o registrar un cliente con RUC válido.',
                'warning'
            );

            $('#num_documento').focus();
            return false;
        }

        return true;
    }

    /*
     * Si el campo queda vacío, se prepara automáticamente
     * CLIENTE VARIOS antes de enviar el formulario.
     */
    if (documentoVisible === '' && !$('#idcliente').val()) {
        if (esBoletaSeleccionada() && Number(totalVenta) > 700) {
            Swal.fire(
                'Identificación obligatoria',
                'Las boletas mayores a S/ 700 deben incluir los nombres y el documento del cliente.',
                'warning'
            );

            $('#num_documento').focus();
            return false;
        }

        return usarClienteGenerico(false);
    }

    if (
        esBoletaSeleccionada()
        && Number(totalVenta) > 700
        && (
            esGenerico
            || !/^\d{8}$|^\d{11}$/.test(documentoReal)
        )
    ) {
        Swal.fire(
            'Identificación obligatoria',
            'Las boletas mayores a S/ 700 deben incluir los nombres y el documento del cliente.',
            'warning'
        );

        $('#num_documento').focus();
        return false;
    }

    if (
        documentoReal !== ''
        && !esGenerico
        && !/^\d{8}$|^\d{11}$/.test(documentoReal)
    ) {
        Swal.fire(
            'Documento inválido',
            'Ingrese un DNI de 8 dígitos o un RUC de 11 dígitos.',
            'warning'
        );

        $('#num_documento').focus();
        return false;
    }

    return true;
}

// newsale3.js
$(document).ready(function () {
    asegurarCampoClienteGenerico();
    cargarComprobantes();
    cargarFormaPago();
    inicializarEventos();
    cargarCarrito();
    actualizarMensajePedido();


});

function cargarTipoPago() {
    $.post("Controllers/Paymentstype.php?op=selectTipopago", function (r) {
        $("#tipo_pago").html(r);
    });
}

$(document).ready(function () {
    cargarTipoPago();
});

$(document).ready(function () {
    $('#descuentoSwitch').trigger('change');
});



// 1. CARGA DE SELECTS DINÁMICOS
function cargarComprobantes() {
    $.post("Controllers/Sell.php?op=selectComprobante", function (data) {
        $('#tipo_comprobante').html(data);
        // Opcional: selecciona por defecto el primero
        $('#tipo_comprobante').val($('#tipo_comprobante option:first').val());
        $('#tipo_comprobante').trigger('change');
    });

    // Clientes
    $.post("Controllers/Sell.php?op=selectCliente", function (r) {
        $("#selectCliente").html(r);
        $("#selectCliente").trigger('change');
    });


}

// 2. INICIALIZA EVENTOS DEL FORMULARIO Y SELECTS
function inicializarEventos() {
    // Cuando cambia el comprobante, mostrar serie y número
    $('#tipo_comprobante').on('change', function () {
        mostrarSerieNumero();
        actualizarReglaCliente();
    });

    $('#num_documento').on('input', function () {
        const documento = String($(this).val() || '')
            .replace(/\D/g, '')
            .slice(0, 11);

        $(this).val(documento);

        limpiarDatosCliente(true);

        if (/^\d{8}$/.test(documento)) {
            $('#tipo_documento').val('DNI');
            $('#num_doc_real').val(documento);
        } else if (/^\d{11}$/.test(documento)) {
            $('#tipo_documento').val('RUC');
            $('#num_doc_real').val(documento);
        }
    });

    // Cuando cambia el cliente, actualizar datos relacionados
    $('#selectCliente').on('change', function () {
        let idCliente = $(this).val();
        if (!idCliente) return;
        $.post("Controllers/Person.php?op=getCustomerByID", { id: idCliente }, function (data) {
            if (!data) return;
            try {
                data = JSON.parse(data);
                $('#celular').val(data.celular || '');
                $('#direccion').val(data.direccion || '');
                // ...otros campos si tienes
            } catch (e) { }
        });
    });

    // Manejo de condición de pago para mostrar campos extra
    $('#condicion_pago').on('change', function () {

        let condicion = $(this).val();

        // RESET
        $('#bloque_credito').hide();
        $('#numero_cuotas').val('');
        $('#monto_cuota').val('');

        if (condicion === 'Crédito') {
            $('#bloque_credito').slideDown();
        }
    });

    $('#numero_cuotas').on('input', function () {

        let cuotas = parseInt($(this).val());
        if (!cuotas || cuotas < 1) return;

        let totalVenta = totalVentaActual();

        let monto = totalVenta / cuotas;

        $('#monto_cuota').val('S/ ' + monto.toFixed(2));
    });



    // Control del descuento (switch o input)
    $('#descuentoSwitch').on('change', function () {

        const esPorcentaje = $(this).is(':checked');

        if (esPorcentaje) {
            // 🔢 MODO PORCENTAJE
            $('#labelDescuento').text('Descuento en %');

            $('#descuentoPorcentaje')
                .prop('disabled', false)
                .attr('max', 100)
                .attr('step', '0.1')
                .attr('placeholder', '%');
        } else {
            // 💰 MODO SOLES
            $('#labelDescuento').text('Descuento en S/');

            $('#descuentoPorcentaje')
                .prop('disabled', false)
                .removeAttr('max')
                .attr('step', '0.01')
                .attr('placeholder', 'S/');
        }

        calcularTotales();
    });



    $('#descuentoPorcentaje').on('input', calcularTotales);

}

// 3. OBTIENE SERIE Y NÚMERO DEL COMPROBANTE ACTUAL
function mostrarSerieNumero() {
    let tipo = $("#tipo_comprobante").val();
    if (!tipo) return;
    $.post("Controllers/Sell.php?op=mostrar_serie_numero", { tipo_comprobante: tipo }, function (data) {
        try {
            data = JSON.parse(data);
            $('#serie_comprobante').val(data.serie || '');
            $('#num_comprobante').val(data.numero || '');
        } catch (e) { }
    });
}

// 4. ENVÍA LA VENTA
function guardarVenta() {
    const form = document.getElementById('formularioVenta');

    if (!form) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se encontró el formulario de venta.'
        });

        return;
    }

    const formData = new FormData(form);
    const $boton = $('#btnProcesarVenta');

    const textoOriginal = $boton.html();

    $boton
        .prop('disabled', true)
        .html(
            '<span class="spinner-border spinner-border-sm me-2"></span>' +
            'Procesando...'
        );

    $.ajax({
        url: 'Controllers/Sell.php?op=guardaryeditar',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        cache: false,

        success: function (data) {
            console.log('RESPUESTA GUARDAR VENTA:', data);

            if (!data || typeof data !== 'object') {
                Swal.fire({
                    icon: 'error',
                    title: 'Respuesta inválida',
                    text: 'El servidor no devolvió una respuesta válida.'
                });

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | La venta local no se registró
            |--------------------------------------------------------------------------
            */
            if (data.success !== true) {
                const mensaje =
                    typeof data.mensaje === 'string'
                        ? data.mensaje
                        : 'No se pudo registrar la venta.';

                Swal.fire({
                    icon: 'error',
                    title: 'No se registró la venta',
                    text: mensaje
                });

                return;
            }

            const idventa = Number.parseInt(
                data.idventa,
                10
            ) || 0;

            if (idventa <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Venta registrada',
                    text:
                        'La venta se registró, pero no se recibió el ID de la venta.'
                });

                return;
            }

            const sunat =
                data.sunat && typeof data.sunat === 'object'
                    ? data.sunat
                    : null;

            let titulo = 'Venta registrada';
            let icono = 'success';
            let mensaje = String(
                data.mensaje || 'Venta registrada correctamente.'
            );

            /*
            |--------------------------------------------------------------------------
            | Resultado APISUNAT
            |--------------------------------------------------------------------------
            */
            if (sunat && sunat.aplica === true) {
                const estadoSunat = String(
                    sunat.status || ''
                ).toUpperCase();

                if (sunat.success === true) {
                    titulo = 'Venta enviada a SUNAT';

                    mensaje =
                        'Comprobante: ' +
                        String(data.comprobante || '') +
                        '. Estado inicial: ' +
                        (estadoSunat || 'PENDIENTE') +
                        '.';
                } else {
                    titulo = 'Venta registrada, envío pendiente';
                    icono = 'warning';

                    mensaje =
                        'La venta fue registrada con ID ' +
                        idventa +
                        ', pero no pudo enviarse a APISUNAT. ' +
                        String(
                            sunat.mensaje ||
                            'Revise el estado antes de intentar recuperarla.'
                        );
                }
            }

            const celularBase = String(
                data.celular || $('#celular').val() || ''
            )
                .replace(/\D/g, '')
                .replace(/^51/, '')
                .slice(-9);

            Swal.fire({
                icon: icono,
                title: titulo,
                text: mensaje,
                input: 'tel',
                inputValue: celularBase,
                inputPlaceholder: 'Celular de 9 dígitos',
                inputAttributes: {
                    maxlength: '9',
                    inputmode: 'numeric',
                    autocomplete: 'off'
                },
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: 'Imprimir',
                denyButtonText: 'WhatsApp',
                cancelButtonText: 'Cerrar',
                allowOutsideClick: false,

                inputValidator: function (valor) {
                    /*
                     * El celular solo es obligatorio cuando
                     * posteriormente se selecciona WhatsApp.
                     */
                    if (
                        valor !== ''
                        && !/^\d{9}$/.test(
                            String(valor).replace(/\D/g, '')
                        )
                    ) {
                        return 'Ingrese los 9 dígitos del celular.';
                    }

                    return undefined;
                }
            }).then(function (resultado) {
                const celular = String(
                    resultado.value || ''
                ).replace(/\D/g, '');

                if (resultado.isConfirmed) {
                    window.open(
                        'Reports/80mm.php?id=' +
                        encodeURIComponent(idventa),
                        '_blank'
                    );
                }

                if (resultado.isDenied) {
                    if (!/^\d{9}$/.test(celular)) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Número inválido',
                            text: 'Ingrese los 9 dígitos del celular.'
                        });

                        return;
                    }

                    const urlComprobante =
                        location.origin +
                        '/Reports/80mm.php?id=' +
                        encodeURIComponent(idventa);

                    const textoWhatsApp =
                        'Aquí está tu comprobante de venta: ' +
                        urlComprobante;

                    window.open(
                        'https://wa.me/51' +
                        celular +
                        '?text=' +
                        encodeURIComponent(textoWhatsApp),
                        '_blank'
                    );
                }

                form.reset();

                limpiarDatosCliente(false);
                actualizarReglaCliente();

                $('#detallesCards').empty();
                $('#totalGeneral').text('S/0.00');
                $('#total_recibido').val('');
                $('#vuelto').val('0.00');

                cont = 0;

                actualizarMensajePedido();
                mostrarSerieNumero();

                /*
                 * Consultar el resultado definitivo solo cuando
                 * APISUNAT recibió el comprobante.
                 */
                if (
                    sunat &&
                    sunat.success === true &&
                    String(sunat.status).toUpperCase() === 'PENDIENTE'
                ) {
                    consultarEstadoSunat(idventa);
                }
            });
        },

        error: function (xhr, estado, error) {
            console.error(
                'ERROR GUARDAR VENTA:',
                xhr.status,
                estado,
                error,
                xhr.responseText
            );

            let mensaje =
                'La solicitud terminó con un error. ' +
                'Antes de registrar nuevamente, revise la última venta.';

            if (
                xhr.responseJSON &&
                typeof xhr.responseJSON.mensaje === 'string'
            ) {
                mensaje = xhr.responseJSON.mensaje;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error de comunicación',
                text: mensaje
            });
        },

        complete: function () {
            $boton
                .prop('disabled', false)
                .html(textoOriginal);
        }
    });
}


// 5. CARGA DINÁMICAMENTE EL CARRITO/PEDIDO ACTUAL
function cargarCarrito() {
    $("#detallesCards").html('');

    $.get("Controllers/Sell.php?op=listarProductosCarrito", function (html) {
        $("#detallesCards").append(html);
        actualizarMensajePedido(); // 🔥
        calcularTotales();
    });
}

function sincronizarTotalRecibido() {

    let nombreForma = getNombreFormaPago();

    // ❌ NO tocar en mixto ni crédito
    if (nombreForma === 'Mixto') return;
    if ($('#condicion_pago').val() === 'Crédito') return;

    let totalVenta = totalVentaActual();

    let $input = $('#total_recibido');

    // si el usuario ya escribió, no sobrescribimos
    if ($input.data('manual') === true) return;

    $input
        .val(totalVenta.toFixed(2))
        .trigger('input'); // recalcula vuelto
}



// 6. CALCULA TOTALES (puedes adaptar según tus campos)
function calcularTotales() {
    let subtotal = 0;

    $("span[name='subtotal']").each(function () {
        subtotal += parseFloat($(this).text()) || 0;
    });

    let descuento = 0;
    let valor = parseFloat($('#descuentoPorcentaje').val()) || 0;
    let esPorcentaje = $('#descuentoSwitch').is(':checked');

    if (valor > 0) {
        if (esPorcentaje) {
            // ✅ DESCUENTO EN %
            descuento = subtotal * (valor / 100);
        } else {
            // ✅ DESCUENTO EN SOLES
            descuento = valor;
        }
    }

    if (descuento > subtotal) descuento = subtotal;

    let totalFinal = subtotal - descuento;
    if (totalFinal < 0) totalFinal = 0;

    $("#totalGeneral").text("S/" + totalFinal.toFixed(2));

    // 🔒 BACKEND (SIEMPRE CLARO)
    $('#descuento_total').val(descuento.toFixed(2));
    $('#descuento_porcentaje').val(esPorcentaje ? valor : 0);

    sincronizarTotalRecibido?.();
    recalcularCuotasCredito?.();
}



function recalcularCuotasCredito() {

    if ($('#condicion_pago').val() !== 'Crédito') return;

    let cuotas = parseInt($('#numero_cuotas').val());
    if (!cuotas || cuotas < 1) return;

    let totalVenta = totalVentaActual();
    let monto = totalVenta / cuotas;

    $('#monto_cuota').val('S/ ' + monto.toFixed(2));
}




function consultarCliente() {
    const num_documento = String(
        $('#num_documento').val() || ''
    ).replace(/\D/g, '');

    let tipo_documento = '';

    if (/^\d{8}$/.test(num_documento)) {
        tipo_documento = 'DNI';
    } else if (/^\d{11}$/.test(num_documento)) {
        tipo_documento = 'RUC';
    } else {
        Swal.fire(
            'Documento inválido',
            'Ingrese un DNI de 8 dígitos o un RUC de 11 dígitos.',
            'warning'
        );

        return;
    }

    limpiarDatosCliente(true);

    $('#tipo_documento').val(tipo_documento);
    $('#num_doc_real').val(num_documento);

    $.ajax({
        url: 'Controllers/Person.php?op=getCustomerByDocument',
        type: 'POST',
        data: {
            tipo_documento: tipo_documento,
            num_documento: num_documento
        },

        success: function (response) {
            let data;

            try {
                data = typeof response === 'object'
                    ? response
                    : JSON.parse(response);
            } catch (error) {
                Swal.fire(
                    'Error',
                    'Respuesta inválida del servidor.',
                    'error'
                );

                return;
            }

            if (data.estado && data.resultado) {
                const cliente = data.resultado;

                $('#num_documento').val(
                    cliente.num_documento || num_documento
                );

                $('#num_doc_real').val(
                    cliente.num_documento || num_documento
                );

                $('#tipo_documento').val(
                    cliente.tipo_documento || tipo_documento
                );

                $('#nombre_cli').val(cliente.nombre || '');
                $('#idcliente').val(cliente.idpersona || '');
                $('#direccion').val(cliente.direccion || '');
                $('#email').val(cliente.email || '');
                $('#celular').val(
                    cliente.celular
                    || cliente.telefono
                    || $('#celular').val()
                    || ''
                );

                $('#nombre_cliente')
                    .removeClass('text-muted text-primary text-danger')
                    .addClass('text-success')
                    .text(cliente.nombre || 'Cliente encontrado');

                return;
            }

            Swal.fire({
                icon: 'warning',
                title: 'Cliente no registrado',
                text: '¿Desea buscarlo en RENIEC o SUNAT?',
                showCancelButton: true,
                confirmButtonText: 'Sí, buscar',
                cancelButtonText: 'Cancelar'
            }).then(function (resultado) {
                if (resultado.isConfirmed) {
                    consultarClienteReniec(
                        tipo_documento,
                        num_documento
                    );
                }
            });
        },

        error: function () {
            Swal.fire(
                'Error',
                'No se pudo consultar el cliente.',
                'error'
            );
        }
    });
}

function consultarClienteReniec(
    tipo_documento,
    num_documento
) {
    $.ajax({
        url: 'Controllers/Person.php?op=getCustomerInfo',
        type: 'POST',
        data: {
            tipo_documento: tipo_documento,
            num_documento: num_documento
        },

        success: function (response) {
            let data;

            try {
                data = typeof response === 'object'
                    ? response
                    : JSON.parse(response);
            } catch (error) {
                Swal.fire(
                    'Error',
                    'Error al procesar la respuesta del servidor.',
                    'error'
                );

                return;
            }

            if (!data.estado || !data.resultado) {
                Swal.fire({
                    icon: 'error',
                    title: 'No encontrado',
                    text:
                        data.mensaje
                        || 'No se encontró información del documento.'
                });

                $('#num_documento').addClass('is-invalid');
                return;
            }

            const resultado = data.resultado;

            const nombre = String(
                resultado.nombre
                || resultado.razon_social
                || ''
            ).trim();

            if (nombre === '') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Datos incompletos',
                    text:
                        data.mensaje
                        || 'No se encontró el nombre del cliente.'
                });

                return;
            }

            $('#idcliente').val('');
            $('#cliente_generico').val('0');
            $('#tipo_documento').val(tipo_documento);
            $('#num_documento').val(num_documento);
            $('#num_doc_real').val(num_documento);
            $('#nombre_cli').val(nombre);
            $('#direccion').val(resultado.direccion || '-');
            $('#email').val(resultado.email || '');

            $('#nombre_cliente')
                .removeClass('text-muted text-primary text-danger')
                .addClass('text-success')
                .text(nombre);
        },

        error: function () {
            Swal.fire(
                'Error',
                'Error al consultar RENIEC/SUNAT.',
                'error'
            );
        }
    });
}

function listarCategorias() {
    $.ajax({
        url: 'Controllers/Sell.php?op=listarCategorias',
        type: 'get',
        dataType: 'json',
        cache: false,

        success: function (data) {
            categoriasCache = Array.isArray(data) ? data : [];

            let html = '';

            if (categoriasCache.length === 0) {
                html = `
                  <li class="nav-item">
                    <span class="nav-link text-muted">Sin categorías</span>
                  </li>`;
            } else {
                categoriasCache.forEach(function (cat, idx) {
                    const id = Number.parseInt(cat.idcategoria, 10) || 0;
                    const nombre = escaparHtmlProducto(
                        cat.nombre || 'Sin categoría'
                    );

                    html += `
                      <li class="nav-item">
                        <a href="#"
                           class="nav-link px-3 py-2 ${idx === 0 ? 'active fw-semibold text-success border-bottom border-2' : 'text-secondary'}"
                           data-id="${id}">
                           ${nombre}
                        </a>
                      </li>`;
                });
            }

            $('#catList').html(html);

            if (categoriasCache.length > 0) {
                const primeraCategoria = Number.parseInt(
                    categoriasCache[0].idcategoria,
                    10
                ) || 0;

                listarArticulosPorCategoria(primeraCategoria);
            }
        },

        error: function () {
            categoriasCache = [];
            $('#catList').html(`
                <li class="nav-item">
                    <span class="nav-link text-danger">No se pudieron cargar las categorías</span>
                </li>
            `);
        }
    });
}

function escaparHtmlProducto(valor) {
    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function crearOpcionesProductoRapido(
    registros,
    campoValor,
    campoTexto,
    textoInicial
) {
    let html = `<option value="">${escaparHtmlProducto(textoInicial)}</option>`;

    registros.forEach(function (registro) {
        const valor = String(registro[campoValor] ?? '');
        const texto = typeof campoTexto === 'function'
            ? campoTexto(registro)
            : registro[campoTexto];

        html += `
            <option value="${escaparHtmlProducto(valor)}">
                ${escaparHtmlProducto(texto || '')}
            </option>`;
    });

    return html;
}

function cargarDatosProductoRapido(forzar = false) {
    if (datosProductoRapidoCargados && !forzar) {
        poblarDatosProductoRapido();
        return;
    }

    $('#rapido_idcategoria').html('<option value="">Cargando...</option>');
    $('#rapido_idsubcategoria').html('<option value="">Cargando...</option>');
    $('#rapido_idmedida').html('<option value="">Cargando...</option>');
    $('#rapido_idalmacen').html('<option value="">Cargando...</option>');

    $.ajax({
        url: 'Controllers/Product.php?op=datosRapidos',
        method: 'GET',
        dataType: 'json',
        cache: false,

        success: function (respuesta) {
            if (
                !respuesta
                || respuesta.success !== true
                || !respuesta.datos
            ) {
                Swal.fire(
                    'No se pudo preparar el formulario',
                    String(
                        respuesta && respuesta.mensaje
                            ? respuesta.mensaje
                            : 'No se recibieron categorías, unidades o almacenes.'
                    ),
                    'error'
                );
                return;
            }

            datosProductoRapidoCache = {
                categorias: Array.isArray(respuesta.datos.categorias)
                    ? respuesta.datos.categorias
                    : [],
                subcategorias: Array.isArray(respuesta.datos.subcategorias)
                    ? respuesta.datos.subcategorias
                    : [],
                medidas: Array.isArray(respuesta.datos.medidas)
                    ? respuesta.datos.medidas
                    : [],
                almacenes: Array.isArray(respuesta.datos.almacenes)
                    ? respuesta.datos.almacenes
                    : []
            };

            datosProductoRapidoCargados = true;
            poblarDatosProductoRapido();
        },

        error: function (xhr) {
            let mensaje = 'No se pudieron cargar los datos del producto.';

            if (
                xhr.responseJSON
                && typeof xhr.responseJSON.mensaje === 'string'
            ) {
                mensaje = xhr.responseJSON.mensaje;
            }

            Swal.fire('Error', mensaje, 'error');
        }
    });
}

function poblarDatosProductoRapido() {
    const datos = datosProductoRapidoCache;

    $('#rapido_idcategoria').html(
        crearOpcionesProductoRapido(
            datos.categorias,
            'idcategoria',
            'nombre',
            'Selecciona una categoría'
        )
    );

    $('#rapido_idmedida').html(
        crearOpcionesProductoRapido(
            datos.medidas,
            'idmedida',
            function (medida) {
                return `${medida.nombre || ''} (${medida.codigo || ''})`;
            },
            'Selecciona una unidad'
        )
    );

    $('#rapido_idalmacen').html(
        crearOpcionesProductoRapido(
            datos.almacenes,
            'idalmacen',
            'nombre',
            'Selecciona un almacén'
        )
    );

    if (datos.categorias.length > 0) {
        $('#rapido_idcategoria').val(
            String(datos.categorias[0].idcategoria)
        );
    }

    if (datos.medidas.length > 0) {
        $('#rapido_idmedida').val(
            String(datos.medidas[0].idmedida)
        );
    }

    if (datos.almacenes.length > 0) {
        $('#rapido_idalmacen').val(
            String(datos.almacenes[0].idalmacen)
        );
    }

    actualizarSubcategoriasRapidas();
    actualizarResumenProductoRapido();
    calcularGananciaProductoRapido();
}

function actualizarSubcategoriasRapidas() {
    const idcategoria = Number.parseInt(
        $('#rapido_idcategoria').val(),
        10
    ) || 0;

    const subcategorias = datosProductoRapidoCache.subcategorias.filter(
        function (subcategoria) {
            return Number.parseInt(subcategoria.idcategoria, 10) === idcategoria;
        }
    );

    if (subcategorias.length === 0) {
        $('#rapido_idsubcategoria')
            .html('<option value="">Sin subcategoría</option>')
            .prop('disabled', true);
    } else {
        $('#rapido_idsubcategoria')
            .html(
                crearOpcionesProductoRapido(
                    subcategorias,
                    'idsubcategoria',
                    'nombre',
                    'Selecciona una subcategoría'
                )
            )
            .prop('disabled', false)
            .val(String(subcategorias[0].idsubcategoria));
    }

    actualizarResumenProductoRapido();
}

function actualizarResumenProductoRapido() {
    const categoria = String(
        $('#rapido_idcategoria option:selected').text() || ''
    ).trim();
    const subcategoria = String(
        $('#rapido_idsubcategoria option:selected').text() || ''
    ).trim();
    const medida = String(
        $('#rapido_idmedida option:selected').text() || ''
    ).trim();
    const almacen = String(
        $('#rapido_idalmacen option:selected').text() || ''
    ).trim();

    const categoriaValida = $('#rapido_idcategoria').val();
    const medidaValida = $('#rapido_idmedida').val();
    const almacenValido = $('#rapido_idalmacen').val();

    if (!categoriaValida || !medidaValida || !almacenValido) {
        $('#rapido_resumen_destino').html(
            '<span class="text-muted">Selecciona categoría, unidad y almacén.</span>'
        );
        return;
    }

    let clasificacion = categoria;

    if (
        subcategoria
        && subcategoria !== 'Sin subcategoría'
        && subcategoria !== 'Selecciona una subcategoría'
    ) {
        clasificacion += ' / ' + subcategoria;
    }

    $('#rapido_resumen_destino').html(
        '<div class="small text-muted mb-1">Se registrará como</div>' +
        '<strong>' + escaparHtmlProducto(clasificacion) + '</strong>' +
        '<div class="small text-muted mt-1">' +
        escaparHtmlProducto(medida) + ' · ' +
        escaparHtmlProducto(almacen) +
        '</div>'
    );
}

function calcularGananciaProductoRapido() {
    const compra = Number.parseFloat(
        $('#rapido_precio_compra').val()
    ) || 0;
    const venta = Number.parseFloat(
        $('#rapido_precio_venta').val()
    ) || 0;

    if (compra <= 0 || venta <= 0) {
        $('#rapido_ganancia').html(
            '<span class="text-muted">Ingresa el costo y el precio de venta para ver la ganancia.</span>'
        );
        return;
    }

    const ganancia = venta - compra;
    const porcentaje = compra > 0
        ? (ganancia / compra) * 100
        : 0;
    const clase = ganancia >= 0 ? 'text-success' : 'text-danger';

    $('#rapido_ganancia').html(
        '<div class="small text-muted mb-1">Ganancia estimada por unidad</div>' +
        '<strong class="' + clase + '">S/ ' + ganancia.toFixed(2) + '</strong>' +
        '<span class="small ' + clase + '"> (' + porcentaje.toFixed(1) + '%)</span>'
    );
}

function abrirProductoRapido() {
    const textoBusqueda = String(
        $('#buscarProducto').val() || ''
    ).trim();

    cargarDatosProductoRapido();

    $('#formProductoRapido').stop(true, true).slideDown(180);
    $('#productosList').stop(true, true).slideUp(160);

    $('#btnMostrarProductoRapido')
        .prop('disabled', true)
        .addClass('disabled');

    if (
        textoBusqueda !== ''
        && String($('#rapido_nombre').val() || '').trim() === ''
    ) {
        $('#rapido_nombre').val(textoBusqueda);
    }

    window.setTimeout(function () {
        $('#rapido_nombre').trigger('focus');
    }, 220);
}

function cerrarProductoRapido(limpiar = false) {
    $('#formProductoRapido').stop(true, true).slideUp(160);
    $('#productosList').stop(true, true).slideDown(160);

    $('#btnMostrarProductoRapido')
        .prop('disabled', false)
        .removeClass('disabled');

    if (limpiar) {
        const formulario = document.getElementById('formProductoRapido');

        if (formulario) {
            formulario.reset();
        }

        $('#rapido_stock').val('1');
        $('#rapido_precio_compra').val('');
        $('#rapido_precio_venta').val('');

        if (datosProductoRapidoCargados) {
            poblarDatosProductoRapido();
        }
    }
}

function guardarProductoRapido() {
    if (guardandoProductoRapido) {
        return;
    }

    const formulario = document.getElementById('formProductoRapido');

    if (!formulario) {
        return;
    }

    if (!formulario.checkValidity()) {
        formulario.reportValidity();
        return;
    }

    const idcategoria = Number.parseInt(
        $('#rapido_idcategoria').val(),
        10
    ) || 0;
    const idmedida = Number.parseInt(
        $('#rapido_idmedida').val(),
        10
    ) || 0;
    const idalmacen = Number.parseInt(
        $('#rapido_idalmacen').val(),
        10
    ) || 0;
    const stock = Number.parseInt($('#rapido_stock').val(), 10) || 0;
    const precioCompra = Number.parseFloat(
        $('#rapido_precio_compra').val()
    ) || 0;
    const precioVenta = Number.parseFloat(
        $('#rapido_precio_venta').val()
    ) || 0;

    if (idcategoria <= 0 || idmedida <= 0 || idalmacen <= 0) {
        Swal.fire(
            'Faltan datos',
            'Selecciona la categoría, la unidad de venta y el almacén.',
            'warning'
        );
        return;
    }

    if (stock < 1) {
        Swal.fire(
            'Cantidad inválida',
            'Indica cuántas unidades tienes actualmente. El mínimo es 1.',
            'warning'
        );
        return;
    }

    if (precioCompra <= 0) {
        Swal.fire(
            'Costo inválido',
            'Indica cuánto te costó cada unidad.',
            'warning'
        );
        return;
    }

    if (precioVenta <= 0) {
        Swal.fire(
            'Precio inválido',
            'Indica el precio que se cobrará al cliente.',
            'warning'
        );
        return;
    }

    const datos = new FormData(formulario);

    if ($('#rapido_idsubcategoria').prop('disabled')) {
        datos.set('idsubcategoria', '');
    }

    const $boton = $('#btnGuardarProductoRapido');
    const textoOriginal = $boton.html();

    guardandoProductoRapido = true;

    $boton
        .prop('disabled', true)
        .html(
            '<span class="spinner-border spinner-border-sm mr-2"></span>' +
            'Registrando...'
        );

    $.ajax({
        url: 'Controllers/Product.php?op=guardarRapido',
        method: 'POST',
        data: datos,
        processData: false,
        contentType: false,
        dataType: 'json',
        cache: false,

        success: function (respuesta) {
            if (
                !respuesta
                || respuesta.success !== true
                || !respuesta.producto
            ) {
                Swal.fire({
                    icon: 'error',
                    title: 'No se creó el producto',
                    text: String(
                        respuesta && respuesta.mensaje
                            ? respuesta.mensaje
                            : 'El servidor no devolvió el producto creado.'
                    )
                });
                return;
            }

            const producto = respuesta.producto;

            cerrarProductoRapido(true);

            agregarDetalle(
                Number.parseInt(producto.idingreso, 10) || 0,
                Number.parseInt(producto.idarticulo, 10) || 0,
                String(producto.codigo || ''),
                String(producto.nombre || ''),
                Number.parseFloat(producto.precio_compra) || 0,
                Number.parseFloat(producto.precio_venta) || 0,
                Number.parseInt(producto.stock, 10) || 1,
                1
            );

            Swal.fire({
                icon: 'success',
                title: 'Producto listo para vender',
                html:
                    '<strong>' + escaparHtmlProducto(producto.nombre || '') + '</strong><br>' +
                    'Se guardó en el inventario y se agregó 1 unidad al pedido.',
                timer: 1900,
                showConfirmButton: false
            });
        },

        error: function (xhr) {
            let mensaje = 'No se pudo registrar el producto.';

            if (
                xhr.responseJSON
                && typeof xhr.responseJSON.mensaje === 'string'
            ) {
                mensaje = xhr.responseJSON.mensaje;
            }

            console.error(
                'ERROR PRODUCTO RÁPIDO:',
                xhr.status,
                xhr.responseText
            );

            Swal.fire('Error', mensaje, 'error');
        },

        complete: function () {
            guardandoProductoRapido = false;

            $boton
                .prop('disabled', false)
                .html(textoOriginal);
        }
    });
}


$(document).on('click', '#catList .nav-link', function (e) {
    e.preventDefault();

    // Reset visual
    $('#catList .nav-link')
        .removeClass('active fw-semibold text-success border-bottom border-2')
        .addClass('text-secondary');

    // Activar seleccionado
    $(this)
        .addClass('active fw-semibold text-success border-bottom border-2')
        .removeClass('text-secondary');

    listarArticulosPorCategoria($(this).data('id'));
});



function listarArticulosPorCategoria(idcategoria) {

    $.ajax({
        url: 'Controllers/Sell.php?op=listarArticulosPorCategoria&idcategoria=' + idcategoria,
        type: 'get',
        dataType: 'json',
        success: function (data) {

            productosCache = Array.isArray(data) ? data : [];
            renderProductos(productosCache);
        },
        error: function () {
            productosCache = [];
            renderProductos([]);
        }
    });
}


function renderProductos(data) {
    let prodHtml = '';

    if (!Array.isArray(data) || data.length === 0) {
        prodHtml = `
            <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-search fs-1 mb-3"></i>
                <div>No se encontraron productos</div>
            </div>`;
    } else {
        data.forEach(function (prod) {
            const idingreso = Number.parseInt(
                prod.idingreso || prod.iddetalle_ingreso || 0,
                10
            ) || 0;

            const idarticulo = Number.parseInt(
                prod.idarticulo,
                10
            ) || 0;

            const codigo = String(prod.codigo || '');
            const nombre = String(prod.nombre || '');
            const imagen = String(prod.imagen || '');
            const precioCompra = Number.parseFloat(
                prod.precio_compra
            ) || 0;
            const precioVenta = Number.parseFloat(
                prod.precio_venta
            ) || 0;
            const stock = Number.parseInt(
                prod.stock,
                10
            ) || 0;

            const codigoHtml = escaparHtmlProducto(codigo);
            const nombreHtml = escaparHtmlProducto(nombre);
            const imagenHtml = escaparHtmlProducto(imagen);

            prodHtml += `
                <div class="col-12 col-md-6 col-lg-4 mb-4 producto-item"
                     data-nombre="${nombreHtml.toLowerCase()}"
                     data-codigo="${codigoHtml.toLowerCase()}">

                    <div
                        class="card border-0 shadow-sm h-100 producto-card"
                        role="button"
                        tabindex="0"
                        style="cursor:pointer;"
                        data-idingreso="${idingreso}"
                        data-idarticulo="${idarticulo}"
                        data-codigo="${codigoHtml}"
                        data-nombre="${nombreHtml}"
                        data-precio-compra="${precioCompra}"
                        data-precio-venta="${precioVenta}"
                        data-stock="${stock}">

                        <div class="card-body">

                            <div class="mb-2 fw-bold fs-5" style="color:#353535;">
                                ${nombreHtml}
                            </div>

                            <div class="d-flex align-items-center mb-3">
                                <div style="
                                    width:90px;
                                    height:90px;
                                    background:#f2f2f2;
                                    border-radius:12px;
                                    display:flex;
                                    align-items:center;
                                    justify-content:center;
                                    margin-right:24px;
                                ">
                                    ${imagen
                                        ? `<img
                                            src="Assets/img/products/${imagenHtml}"
                                            alt="${nombreHtml}"
                                            style="
                                                max-width:80px;
                                                max-height:80px;
                                                border-radius:10px;
                                            ">`
                                        : `<i class="bi bi-image fs-1 text-secondary"></i>`
                                    }
                                </div>

                                <div class="small">
                                    <div><strong>SKU:</strong> ${codigoHtml}</div>
                                    <div><strong>Stock:</strong> ${stock}</div>
                                    <div>
                                        <strong>Precio:</strong>
                                        S/${precioVenta.toFixed(2)}
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            `;
        });
    }

    $('#productosList').html(prodHtml);
}

$(document).on(
    'click keydown',
    '.producto-card',
    function (evento) {
        if (
            evento.type === 'keydown'
            && evento.key !== 'Enter'
            && evento.key !== ' '
        ) {
            return;
        }

        evento.preventDefault();

        const $producto = $(this);

        agregarDetalle(
            Number.parseInt($producto.attr('data-idingreso'), 10) || 0,
            Number.parseInt($producto.attr('data-idarticulo'), 10) || 0,
            String($producto.attr('data-codigo') || ''),
            String($producto.attr('data-nombre') || ''),
            Number.parseFloat(
                $producto.attr('data-precio-compra')
            ) || 0,
            Number.parseFloat(
                $producto.attr('data-precio-venta')
            ) || 0,
            Number.parseInt($producto.attr('data-stock'), 10) || 0,
            1
        );
    }
);


$('#buscarProducto').on('input', function () {

    let texto = $(this).val().toLowerCase().trim();

    if (texto === '') {
        renderProductos(productosCache);
        return;
    }

    let filtrados = productosCache.filter(function (p) {
        const nombre = String(p.nombre || '').toLowerCase();
        const codigo = String(p.codigo || '').toLowerCase();

        return nombre.includes(texto) || codigo.includes(texto);
    });

    renderProductos(filtrados);
});

$('#btnAbrirModal').on('click', function () {
    cerrarProductoRapido(true);
    $('#modalProductos').modal('show');
    $('#buscarProducto').val('');
    listarCategorias();
    cargarDatosProductoRapido();
});


$(document).on('click', '#btnMostrarProductoRapido', function () {
    abrirProductoRapido();
});

$(document).on(
    'click',
    '#btnCerrarProductoRapido, #btnCancelarProductoRapido',
    function () {
        cerrarProductoRapido(true);
    }
);

$(document).on('submit', '#formProductoRapido', function (evento) {
    evento.preventDefault();
    evento.stopPropagation();
    guardarProductoRapido();
});

$(document).on('change', '#rapido_idcategoria', function () {
    actualizarSubcategoriasRapidas();
});

$(document).on(
    'change',
    '#rapido_idsubcategoria, #rapido_idmedida, #rapido_idalmacen',
    function () {
        actualizarResumenProductoRapido();
    }
);

$(document).on(
    'input',
    '#rapido_precio_compra, #rapido_precio_venta',
    function () {
        calcularGananciaProductoRapido();
    }
);

$('#modalProductos').on('hidden.bs.modal', function () {
    cerrarProductoRapido(true);
});


var cont = 0;

function agregarDetalle(
    idingreso,
    idarticulo,
    codigo,
    articulo,
    precio_compra,
    precio_venta,
    stock,
    op
) {
    if (!idarticulo || idarticulo === 0) {
        Swal.fire("Error", "Artículo inválido", "error");
        return;
    }


    // Si ya existe, solo suma cantidad
    let existe = false;

    $("input[name='idarticulo[]']").each(function (index) {

        if (parseInt($(this).val()) === parseInt(idarticulo)) {

            let cantidadInput = $("input[name='cantidad[]']").eq(index);
            let cantidadLabel = $("#cantidadLabel" + index);
            let precioVenta = parseFloat($("input[name='precio_venta[]']").eq(index).val());

            let nuevaCantidad = parseInt(cantidadInput.val()) + 1;

            // 🚫 Validar stock
            if (nuevaCantidad > stock) {
                Swal.fire(
                    "Stock insuficiente",
                    "No hay más unidades disponibles.",
                    "warning"
                );
                existe = true;
                return false;
            }

            // ✅ Actualizar cantidad
            cantidadInput.val(nuevaCantidad);
            cantidadLabel.text(nuevaCantidad);

            // ✅ Recalcular subtotal
            let nuevoSubtotal = nuevaCantidad * precioVenta;
            $("#subtotal" + index).text(nuevoSubtotal.toFixed(2));

            // 🔄 Totales generales
            calcularTotales();
            actualizarMensajePedido();

            existe = true;
            return false; // salir del each
        }
    });


    if (existe) {
        $('#modalProductos').modal('hide');
        return;
    }

    let cantidad = 1;
    let descuento = 0;
    let subtotal = cantidad * precio_venta;

    let card = `
        <div class="card border-0 shadow-sm mb-3 bg-white filas" id="fila${cont}">
            <div class="card-body d-flex justify-content-between align-items-start p-3">

                <!-- INPUTS OCULTOS -->
                <input type="hidden" name="idingreso[]" value="${idingreso}">
                <input type="hidden" name="idarticulo[]" value="${idarticulo}">
                <input type="hidden" name="precio_compra[]" value="${precio_compra}">
                <input type="hidden" name="descuento[]" value="${descuento}">

                <!-- INFO PRODUCTO -->
                <div>
                    <div class="fw-bold fs-6 mb-1 text-dark">${articulo}</div>
                    <div class="text-muted small">Almacén: Principal</div>
                    <div class="text-muted small">SKU: ${codigo}</div>

                    <div class="text-muted small">
                        Precio Unitario:
                        <span class="fw-semibold">S/ ${Number(precio_venta).toFixed(2)}</span>

                        <!-- valor real oculto para backend -->
                        <input type="hidden" name="precio_venta[]" value="${precio_venta}">
                    </div>
                    <div class="text-muted small">
                        Cantidad:
                        <span class="fw-semibold cantidad-label" id="cantidadLabel${cont}">
                            ${cantidad}
                        </span>

                        <input type="hidden"
                            name="cantidad[]"
                            id="cantidadInput${cont}"
                            value="${cantidad}">
                    </div>
                    <div class="fw-bold mt-2 text-dark">
                        Total: S/
                        <span name="subtotal" id="subtotal${cont}">
                            ${subtotal.toFixed(2)}
                        </span>
                    </div>
                </div>

                <!-- BOTONES -->
                <div class="d-flex flex-column justify-content-between align-items-end ms-auto"
                    style="min-width:48px;">

                    <div class="d-flex flex-column align-items-center gap-1">
                        <button type="button"
                            class="btn btn-outline-success btn-sm px-2 py-1 mb-1"
                            onclick="incrementarCantidad(${cont}, ${stock})">
                            <i class="bi bi-plus"></i>
                        </button>

                        <button type="button"
                            class="btn btn-outline-secondary btn-sm px-2 py-1"
                            onclick="decrementarCantidad(${cont})">
                            <i class="bi bi-dash"></i>
                        </button>
                    </div>

                    <button type="button" class="btn btn-outline-danger btn-sm px-2 py-1 mt-3"
                        onclick="eliminarDetalle(${cont})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>

            </div>
        </div>
        `;

    $("#detallesCards").append(card);
    actualizarMensajePedido();

    $('#total_recibido').data('manual', false);
    sincronizarTotalRecibido();

    cont++;

    calcularTotales();


    $('#modalProductos').modal('hide');

}

// ===============================
// 📦 SCANNER DE CÓDIGO DE BARRAS
// ===============================

let bufferScan = '';
let scanTimeout = null;

$(document).on('keypress', function (e) {

    // Ignorar inputs normales
    if ($(e.target).is('input, textarea')) return;

    // ENTER → fin de escaneo
    if (e.which === 13) {

        if (bufferScan.length >= 3) {
            console.log('ESCANEADO:', bufferScan);
            buscarProductoPorCodigo(bufferScan);
        }

        bufferScan = '';
        return;
    }

    // Solo caracteres visibles
    if (e.which >= 32 && e.which <= 126) {
        bufferScan += String.fromCharCode(e.which);

        clearTimeout(scanTimeout);
        scanTimeout = setTimeout(() => {
            bufferScan = '';
        }, 1000); // ⏱️ más tolerante para scanner
    }
});




setInterval(() => {
    const input = document.getElementById('scannerInput');
    if (input && document.activeElement !== input) {
        input.focus();
    }
}, 500);


function buscarProductoPorCodigo(codigo) {

    $('#pedidoVacio').addClass('opacity-25');

    $.ajax({
        url: "Controllers/Sell.php?op=buscarProductoPorCodigo",
        type: "POST",
        data: { codigo },
        dataType: "json", // ✅ CLAVE
        success: function (p) {

            console.log("PRODUCTO ESCANEADO:", p);

            if (!p || !p.idarticulo) {
                Swal.fire(
                    'No encontrado',
                    'Producto no existe o sin stock',
                    'warning'
                );
                return;
            }

            agregarDetalle(
                p.idingreso,
                p.idarticulo,
                p.codigo,
                p.nombre,
                parseFloat(p.precio_compra),
                parseFloat(p.precio_venta),
                parseInt(p.stock),
                1
            );

        },
        error: function (xhr) {
            console.error("ERROR AJAX:", xhr.responseText);
            Swal.fire('Error', 'Respuesta inválida del servidor', 'error');
        }
    });
}



function incrementarCantidad(indice, stock) {
    let cantidadInput = document.getElementById('cantidadInput' + indice);
    let cantidadLabel = document.getElementById('cantidadLabel' + indice);
    let precioInput = document.querySelectorAll("input[name='precio_venta[]']")[indice];
    let subtotalSpan = document.getElementById('subtotal' + indice);

    let cantidad = parseInt(cantidadInput.value) + 1;

    if (cantidad > stock) {
        Swal.fire("Stock insuficiente", "No hay más unidades disponibles.", "warning");
        return;
    }

    cantidadInput.value = cantidad;
    cantidadLabel.textContent = cantidad;

    let subtotal = cantidad * parseFloat(precioInput.value);
    subtotalSpan.textContent = subtotal.toFixed(2);

    calcularTotales();
}


function decrementarCantidad(indice) {
    let cantidadInput = document.getElementById('cantidadInput' + indice);
    let cantidadLabel = document.getElementById('cantidadLabel' + indice);
    let precioInput = document.querySelectorAll("input[name='precio_venta[]']")[indice];
    let subtotalSpan = document.getElementById('subtotal' + indice);

    let cantidad = parseInt(cantidadInput.value) - 1;
    if (cantidad < 1) return;

    cantidadInput.value = cantidad;
    cantidadLabel.textContent = cantidad;

    let subtotal = cantidad * parseFloat(precioInput.value);
    subtotalSpan.textContent = subtotal.toFixed(2);

    calcularTotales();
}



function modificarSubtotales() {
    let total = 0;

    $("span[name='subtotal']").each(function () {
        let valor = parseFloat($(this).text()) || 0;
        total += valor;
    });

    $("#totalGeneral").text("S/" + total.toFixed(2));
}

function eliminarDetalle(indice) {
    $("#fila" + indice).remove();
    actualizarMensajePedido();

    if ($("#detallesCards .filas").length === 0) {
        $('#total_recibido').data('manual', false).val('');
        $('#vuelto').val('0.00');
    }

    calcularTotales();
}




function actualizarMensajePedido() {

    const hayProductos = $("#detallesCards .filas").length > 0;

    if (hayProductos) {
        $("#contenedorPedido").addClass("con-items");
    } else {
        $("#contenedorPedido").removeClass("con-items");
    }
}



function calcularVuelto() {

    // 🔹 detectar forma de pago desde el select (BD)
    let nombreForma = getNombreFormaPago();

    // 🔴 si es Mixto, este cálculo NO aplica
    if (nombreForma === 'Mixto') {
        return;
    }

    let totalVenta = totalVentaActual();
    let recibido = parseFloat($('#total_recibido').val()) || 0;

    let vuelto = recibido - totalVenta;
    if (vuelto < 0) vuelto = 0;

    $('#vuelto').val(vuelto.toFixed(2));
}



$('#total_recibido').on('input', function () {
    if ($(this).val() !== '') {
        $(this).data('manual', true);
    }
    calcularVuelto();
});



$('#formularioVenta').on('submit', function (e) {

    e.preventDefault(); // ⛔ siempre primero

    let condicion = $('#condicion_pago').val();
    let nombreForma = getNombreFormaPago();
    let totalVenta = totalVentaActual();

    if (!validarClienteAntesDeVender(totalVenta)) {
        return false;
    }

    // =========================
    // 🔹 VALIDACIÓN CRÉDITO
    // =========================
    if (condicion === 'Crédito') {

        let cuotas = parseInt($('#numero_cuotas').val());

        if (!cuotas || cuotas < 1) {
            Swal.fire(
                'Crédito',
                'Debe ingresar el número de cuotas',
                'warning'
            );
            return false; // ⛔ NO guarda
        }

        // 👉 en crédito NO validamos monto recibido
        guardarVenta();
        return;
    }

    // =========================
    // 🔹 VALIDACIÓN CONTADO / NORMAL
    // =========================
    if (nombreForma !== 'Mixto') {

        let recibido = parseFloat($('#total_recibido').val()) || 0;

        if (recibido < totalVenta) {
            Swal.fire(
                'Pago incompleto',
                'El monto recibido no cubre el total de la venta',
                'warning'
            );
            return false;
        }

        guardarVenta();
        return;
    }

    // =========================
    // 🔹 VALIDACIÓN PAGO MIXTO
    // =========================
    let totalPagado = parseFloat($('#total_recibido').val()) || 0;

    if (totalPagado < totalVenta) {
        Swal.fire(
            'Pago incompleto',
            'La suma de los métodos de pago no cubre el total de la venta',
            'warning'
        );
        return false;
    }

    guardarVenta();
});

function getNombreFormaPago() {
    return $('#forma_pago option:selected').text().trim();
}


// FORMA DE PAGO: mostrar campos mixtos
$('#forma_pago').on('change', function () {

    $('#total_recibido').data('manual', false);

    let nombreForma = getNombreFormaPago();
    let totalVenta = totalVentaActual();

    $('#bloque_pago_mixto').hide();
    $('#pagosMixtosContainer').html('');
    $('#vuelto').val('0.00');

    if (nombreForma === 'Mixto') {
        $('#bloque_pago_mixto').slideDown();
        $('#total_recibido')
            .val('0.00')
            .prop('readonly', true)
            .addClass('bg-light');
        agregarPagoMixtoFila();
        agregarPagoMixtoFila();
        return;
    }

    $('#total_recibido')
        .prop('readonly', false)
        .removeClass('bg-light');

    sincronizarTotalRecibido();
});




let pagoMixtoIndex = 0;

function agregarPagoMixtoFila() {
    let i = pagoMixtoIndex++;

    let fila = `
      <div class="row g-2 align-items-center mb-2 pago-mixto-fila" data-i="${i}">
        <div class="col-md-6">
          <select class="form-control form-select pago-metodo"
                  name="pagos[${i}][metodo]">
            <option value="">Seleccione</option>
            <option value="Efectivo">Efectivo</option>
            <option value="Yape">Yape</option>
            <option value="Plin">Plin</option>
            <option value="Tarjeta debito">Tarjeta debito</option>
            <option value="Tarjeta credito">Tarjeta credito</option>
          </select>
        </div>

        <div class="col-md-4">
          <input type="number" step="0.01" min="0"
                 class="form-control pago-monto"
                 name="pagos[${i}][monto]"
                 placeholder="Monto">
        </div>

        <div class="col-md-2 text-end">
          <button type="button" class="btn btn-outline-danger btn-sm btnQuitarPago">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>
    `;

    $('#pagosMixtosContainer').append(fila);
}

// Agregar fila
$('#btnAgregarPagoMixto').on('click', function () {
    agregarPagoMixtoFila();
});

// Quitar fila (delegado)
$(document).on('click', '.btnQuitarPago', function () {
    $(this).closest('.pago-mixto-fila').remove();
    calcularPagoMixtoForma();
});

// Recalcular cuando cambian montos o método
$(document).on('input change', '.pago-monto, .pago-metodo', function () {
    calcularPagoMixtoForma();
});

function totalVentaActual() {
    let subtotal = 0;

    $("span[name='subtotal']").each(function () {
        subtotal += parseFloat($(this).text()) || 0;
    });

    let descuento = 0;
    let valor = parseFloat($('#descuentoPorcentaje').val()) || 0;
    let esPorcentaje = $('#descuentoSwitch').is(':checked');

    if (valor > 0) {
        if (esPorcentaje) {
            descuento = subtotal * (valor / 100);
        } else {
            descuento = valor;
        }
    }

    if (descuento > subtotal) descuento = subtotal;

    let total = subtotal - descuento;
    if (total < 0) total = 0;

    return total;
}



function calcularPagoMixtoForma() {

    let totalVenta = totalVentaActual();

    let totalPagado = 0;
    let efectivo = 0;
    let noEfectivo = 0;

    $('#pagosMixtosContainer .pago-mixto-fila').each(function () {

        let metodo = $(this).find('.pago-metodo').val();
        let monto = parseFloat($(this).find('.pago-monto').val()) || 0;

        totalPagado += monto;

        if (metodo === 'Efectivo') {
            efectivo += monto;
        } else {
            noEfectivo += monto;
        }
    });

    // Total recibido (solo informativo)
    $('#total_recibido').val(totalPagado.toFixed(2));

    // 🔥 LÓGICA CORRECTA DE VUELTO
    let faltante = totalVenta - noEfectivo;
    if (faltante < 0) faltante = 0;

    let vuelto = efectivo - faltante;
    if (vuelto < 0) vuelto = 0;

    $('#vuelto').val(vuelto.toFixed(2));
}



function cargarFormaPago() {
    $.post("Controllers/Sell.php?op=selectFormaPago", function (r) {

        $("#forma_pago").html(r);

        // ✅ estado inicial NORMAL
        $('#bloque_pago_mixto').hide();

        $('#total_recibido')
            .val('')
            .prop('readonly', false)   // 🔥 CLAVE
            .removeClass('bg-light');

        $('#vuelto').val('0.00');
    });
}

function consultarEstadoSunat(idventa, intento = 1) {
    const maxIntentos = 8;

    window.setTimeout(function () {
        $.ajax({
            url: 'Controllers/ApiSunat.php',
            method: 'GET',
            dataType: 'json',
            cache: false,
            data: {
                op: 'consultar',
                idventa: idventa,
                v: Date.now()
            },

            success: function (respuesta) {
                console.log(
                    'ESTADO APISUNAT:',
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

                    return;
                }

                if (
                    estado === 'RECHAZADO' ||
                    estado === 'EXCEPCION'
                ) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Comprobante no aceptado',
                        text: String(
                            respuesta.mensaje ||
                            'Estado SUNAT: ' + estado
                        )
                    });

                    return;
                }

                if (intento < maxIntentos) {
                    consultarEstadoSunat(
                        idventa,
                        intento + 1
                    );
                }
            },

            error: function (xhr) {
                console.error(
                    'ERROR CONSULTAR APISUNAT:',
                    xhr.responseText
                );

                if (intento < maxIntentos) {
                    consultarEstadoSunat(
                        idventa,
                        intento + 1
                    );
                }
            }
        });
    }, intento === 1 ? 3000 : 5000);
}