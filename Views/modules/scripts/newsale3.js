let productosCache = [];
let categoriasCache = [];
let datosProductoRapidoCache = {
    categorias: [],
    subcategorias: [],
    medidas: [],
    almacenes: []
};
let datosProductoRapidoCargados = false;
let cargandoDatosProductoRapido = false;
let guardandoProductoRapido = false;
let categoriaActiva = 0;
let buscandoCodigoProducto = false;
let temporizadorBusquedaProducto = null;

const ESTADO_ESCANER = {
    buffer: '',
    inicio: 0,
    ultimo: 0,
    temporizador: null
};

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
    inicializarEscanerProductos();

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

        if (textoNormalizado(condicion) === 'CREDITO') {
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
    if (textoNormalizado($('#condicion_pago').val()) === 'CREDITO') return;

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

    if (textoNormalizado($('#condicion_pago').val()) !== 'CREDITO') return;

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
    $('#catList').html(`
        <li>
            <span class="categoria-chip">
                <span class="spinner-border spinner-border-sm mr-1"></span>
                Cargando categorías
            </span>
        </li>
    `);

    mostrarCargandoProductos('Cargando productos...');

    $.ajax({
        url: 'Controllers/Sell.php?op=listarCategorias',
        type: 'GET',
        dataType: 'json',
        cache: false,

        success: function (data) {
            categoriasCache = Array.isArray(data) ? data : [];

            let html = '';
            let opcionesRapidas = '<option value="">Seleccione...</option>';

            if (categoriasCache.length === 0) {
                $('#catList').html(`
                    <li>
                        <span class="categoria-chip text-muted">
                            Sin categorías disponibles
                        </span>
                    </li>
                `);

                $('#rapido_idcategoria').html(
                    '<option value="">No disponible</option>'
                );

                productosCache = [];
                renderProductos([]);
                return;
            }

            categoriasCache.forEach(function (cat, indice) {
                const id = Number.parseInt(cat.idcategoria, 10) || 0;
                const nombre = escaparHtmlProducto(
                    cat.nombre || 'Sin categoría'
                );

                html += `
                    <li>
                        <button
                            type="button"
                            class="categoria-chip ${indice === 0 ? 'active' : ''}"
                            data-id="${id}">
                            <i class="bi bi-tag"></i>
                            ${nombre}
                        </button>
                    </li>
                `;

                opcionesRapidas += `
                    <option value="${id}">${nombre}</option>
                `;
            });

            $('#catList').html(html);
            $('#rapido_idcategoria').html(opcionesRapidas);

            const primeraCategoria = Number.parseInt(
                categoriasCache[0].idcategoria,
                10
            ) || 0;

            categoriaActiva = primeraCategoria;
            $('#rapido_idcategoria').val(String(primeraCategoria));
            listarArticulosPorCategoria(primeraCategoria);
        },

        error: function () {
            categoriasCache = [];
            productosCache = [];

            $('#catList').html(`
                <li>
                    <span class="categoria-chip text-danger">
                        No se pudieron cargar las categorías
                    </span>
                </li>
            `);

            $('#rapido_idcategoria').html(
                '<option value="">No disponible</option>'
            );

            renderProductos([]);
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

    (Array.isArray(registros) ? registros : []).forEach(function (registro) {
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

function mostrarEstadoCargaProductoRapido() {
    $('#rapido_idcategoria')
        .prop('disabled', true)
        .html('<option value="">Cargando categorías...</option>');

    $('#rapido_idsubcategoria')
        .prop('disabled', true)
        .html('<option value="">Cargando subcategorías...</option>');

    $('#rapido_idmedida')
        .prop('disabled', true)
        .html('<option value="">Cargando unidades...</option>');

    $('#rapido_idalmacen')
        .prop('disabled', true)
        .html('<option value="">Cargando almacenes...</option>');

    $('#rapido_resumen_destino').html(
        '<span class="text-muted">Cargando clasificación, unidad y almacén...</span>'
    );
}

function cargarDatosProductoRapido(forzar = false) {
    if (datosProductoRapidoCargados && !forzar) {
        poblarDatosProductoRapido();
        return;
    }

    if (cargandoDatosProductoRapido) {
        return;
    }

    cargandoDatosProductoRapido = true;
    mostrarEstadoCargaProductoRapido();

    $.ajax({
        url: 'Controllers/Product.php?op=datosRapidos',
        method: 'GET',
        dataType: 'json',
        cache: false,
        data: {
            v: Date.now()
        },

        success: function (respuesta) {
            if (
                !respuesta
                || respuesta.success !== true
                || !respuesta.datos
            ) {
                const mensaje = String(
                    respuesta && respuesta.mensaje
                        ? respuesta.mensaje
                        : 'No se recibieron categorías, unidades o almacenes.'
                );

                $('#rapido_idcategoria, #rapido_idmedida, #rapido_idalmacen')
                    .prop('disabled', false)
                    .html('<option value="">No disponible</option>');

                $('#rapido_idsubcategoria')
                    .prop('disabled', true)
                    .html('<option value="">Sin datos</option>');

                Swal.fire(
                    'No se pudo preparar el formulario',
                    mensaje,
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
            console.error(
                'ERROR DATOS PRODUCTO RÁPIDO:',
                xhr.status,
                xhr.responseText
            );

            let mensaje = 'No se pudieron cargar las categorías, unidades y almacenes.';

            if (
                xhr.responseJSON
                && typeof xhr.responseJSON.mensaje === 'string'
            ) {
                mensaje = xhr.responseJSON.mensaje;
            }

            $('#rapido_idcategoria, #rapido_idmedida, #rapido_idalmacen')
                .prop('disabled', false)
                .html('<option value="">No disponible</option>');

            $('#rapido_idsubcategoria')
                .prop('disabled', true)
                .html('<option value="">Sin datos</option>');

            Swal.fire('Error', mensaje, 'error');
        },

        complete: function () {
            cargandoDatosProductoRapido = false;
        }
    });
}

function poblarDatosProductoRapido() {
    const datos = datosProductoRapidoCache;

    $('#rapido_idcategoria')
        .prop('disabled', false)
        .html(
            crearOpcionesProductoRapido(
                datos.categorias,
                'idcategoria',
                'nombre',
                'Selecciona una categoría'
            )
        );

    $('#rapido_idmedida')
        .prop('disabled', false)
        .html(
            crearOpcionesProductoRapido(
                datos.medidas,
                'idmedida',
                function (medida) {
                    const nombre = String(medida.nombre || '').trim();
                    const codigo = String(medida.codigo || '').trim();

                    return codigo !== ''
                        ? `${nombre} (${codigo})`
                        : nombre;
                },
                'Selecciona una unidad'
            )
        );

    $('#rapido_idalmacen')
        .prop('disabled', false)
        .html(
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
            return Number.parseInt(
                subcategoria.idcategoria,
                10
            ) === idcategoria;
        }
    );

    if (idcategoria <= 0) {
        $('#rapido_idsubcategoria')
            .prop('disabled', true)
            .html('<option value="">Selecciona primero la categoría</option>');
    } else if (subcategorias.length === 0) {
        $('#rapido_idsubcategoria')
            .prop('disabled', true)
            .html('<option value="">Sin subcategoría</option>');
    } else {
        $('#rapido_idsubcategoria')
            .prop('disabled', false)
            .html(
                crearOpcionesProductoRapido(
                    subcategorias,
                    'idsubcategoria',
                    'nombre',
                    'Selecciona una subcategoría'
                )
            )
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
        && subcategoria !== 'Selecciona primero la categoría'
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
        } else {
            $('#rapido_idsubcategoria')
                .prop('disabled', true)
                .html('<option value="">Selecciona primero la categoría</option>');
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

    if (!formulario.checkValidity()) {
        formulario.reportValidity();
        return;
    }

    if (stock < 1) {
        Swal.fire(
            'Stock inválido',
            'El stock inicial debe ser por lo menos 1.',
            'warning'
        );
        return;
    }

    if (precioCompra <= 0 || precioVenta <= 0) {
        Swal.fire(
            'Precio inválido',
            'Los precios de compra y venta deben ser mayores que cero.',
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
            'Guardando...'
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
                title: 'Producto agregado',
                text: 'Se registró en el inventario y ya está en el pedido.',
                timer: 1600,
                showConfirmButton: false
            });
        },

        error: function (xhr) {
            let mensaje = 'No se pudo registrar el producto rápido.';

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

            Swal.fire(
                'Error',
                mensaje,
                'error'
            );
        },

        complete: function () {
            guardandoProductoRapido = false;

            $boton
                .prop('disabled', false)
                .html(textoOriginal);
        }
    });
}

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
    'input change',
    '#rapido_precio_compra, #rapido_precio_venta',
    function () {
        calcularGananciaProductoRapido();
    }
);

$(document).on('click', '#catList .categoria-chip[data-id]', function (e) {
    e.preventDefault();

    const idcategoria = Number.parseInt(
        $(this).attr('data-id'),
        10
    ) || 0;

    $('#catList .categoria-chip').removeClass('active');
    $(this).addClass('active');

    categoriaActiva = idcategoria;
    $('#buscarProducto').val('');
    actualizarAyudaBusqueda(
        'Mostrando los productos de la categoría seleccionada.',
        'info'
    );

    listarArticulosPorCategoria(idcategoria);
});

$(document).on('click', '#catPrev, #catNext', function () {
    const contenedor = document.getElementById('catList');

    if (!contenedor) {
        return;
    }

    const direccion = this.id === 'catPrev' ? -1 : 1;
    const desplazamiento = Math.max(220, contenedor.clientWidth * 0.72);

    contenedor.scrollBy({
        left: direccion * desplazamiento,
        behavior: 'smooth'
    });
});

function mostrarCargandoProductos(mensaje = 'Buscando productos...') {
    $('#productosList').html(`
        <div class="col-12 d-flex flex-column align-items-center justify-content-center text-muted" style="min-height:260px;">
            <span class="spinner-border text-success mb-3" role="status"></span>
            <div>${escaparHtmlProducto(mensaje)}</div>
        </div>
    `);
}

function listarArticulosPorCategoria(idcategoria) {
    const categoria = Number.parseInt(idcategoria, 10) || 0;

    if (categoria <= 0) {
        productosCache = [];
        renderProductos([]);
        return;
    }

    categoriaActiva = categoria;
    mostrarCargandoProductos();

    $.ajax({
        url: 'Controllers/Sell.php?op=listarArticulosPorCategoria',
        type: 'GET',
        dataType: 'json',
        cache: false,
        data: {
            idcategoria: categoria,
            v: Date.now()
        },

        success: function (data) {
            productosCache = Array.isArray(data) ? data : [];
            renderProductos(productosCache);
        },

        error: function (xhr) {
            console.error(
                'ERROR CARGAR PRODUCTOS:',
                xhr.status,
                xhr.responseText
            );

            productosCache = [];
            renderProductos([]);
            actualizarAyudaBusqueda(
                'No se pudieron cargar los productos de esta categoría.',
                'error'
            );
        }
    });
}

function renderProductos(data) {
    let prodHtml = '';

    if (!Array.isArray(data) || data.length === 0) {
        prodHtml = `
            <div class="col-12 d-flex flex-column align-items-center justify-content-center text-center text-muted" style="min-height:300px;">
                <span class="d-inline-flex align-items-center justify-content-center mb-3" style="width:68px;height:68px;border-radius:20px;background:#edf2ef;">
                    <i class="bi bi-search" style="font-size:1.8rem;"></i>
                </span>
                <div class="font-weight-bold text-dark mb-1">No se encontraron productos</div>
                <div class="small">Prueba otra categoría o escanea el código de barras.</div>
            </div>
        `;
    } else {
        data.forEach(function (prod) {
            const idingreso = Number.parseInt(
                prod.idingreso || prod.iddetalle_ingreso || 0,
                10
            ) || 0;

            const idarticulo = Number.parseInt(prod.idarticulo, 10) || 0;
            const codigo = String(prod.codigo || '').trim();
            const nombre = String(prod.nombre || '').trim();
            const imagen = String(prod.imagen || '').trim();
            const precioCompra = Number.parseFloat(prod.precio_compra) || 0;
            const precioVenta = Number.parseFloat(prod.precio_venta) || 0;
            const stock = Number.parseInt(prod.stock, 10) || 0;

            const codigoHtml = escaparHtmlProducto(codigo);
            const nombreHtml = escaparHtmlProducto(nombre);
            const imagenHtml = escaparHtmlProducto(imagen);

            prodHtml += `
                <div class="col-12 col-sm-6 col-lg-4 producto-item"
                     data-nombre="${nombreHtml.toLowerCase()}"
                     data-codigo="${codigoHtml.toLowerCase()}">

                    <div
                        class="card h-100 producto-card"
                        role="button"
                        tabindex="0"
                        data-idingreso="${idingreso}"
                        data-idarticulo="${idarticulo}"
                        data-codigo="${codigoHtml}"
                        data-nombre="${nombreHtml}"
                        data-precio-compra="${precioCompra}"
                        data-precio-venta="${precioVenta}"
                        data-stock="${stock}">

                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start" style="gap:13px;">
                                <div class="producto-imagen">
                                    ${imagen
                                        ? `<img src="Assets/img/products/${imagenHtml}" alt="${nombreHtml}">`
                                        : '<i class="bi bi-box-seam text-secondary" style="font-size:1.75rem;"></i>'
                                    }
                                </div>

                                <div class="flex-grow-1 min-width-0" style="min-width:0;">
                                    <div class="producto-nombre">${nombreHtml}</div>
                                    <div class="producto-codigo mt-1" title="${codigoHtml}">
                                        Código: ${codigoHtml || 'Sin código'}
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top">
                                <span class="producto-stock">
                                    <i class="bi bi-box mr-1"></i>
                                    Stock: ${stock}
                                </span>

                                <span class="producto-precio">
                                    S/ ${precioVenta.toFixed(2)}
                                </span>
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


function normalizarBusquedaProducto(valor) {
    return String(valor || '')
        .trim()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function actualizarAyudaBusqueda(mensaje, tipo = 'info') {
    const iconos = {
        info: 'bi-info-circle',
        success: 'bi-check-circle-fill',
        warning: 'bi-exclamation-circle-fill',
        error: 'bi-x-circle-fill',
        loading: 'bi-arrow-repeat'
    };

    const clases = {
        info: 'text-muted',
        success: 'text-success',
        warning: 'text-warning',
        error: 'text-danger',
        loading: 'text-primary'
    };

    $('#resultadoBusquedaProducto')
        .removeClass('text-muted text-success text-warning text-danger text-primary')
        .addClass(clases[tipo] || clases.info)
        .html(`
            <i class="bi ${iconos[tipo] || iconos.info}"></i>
            ${escaparHtmlProducto(mensaje)}
        `);
}

$(document).on('input', '#buscarProducto', function () {
    const textoOriginal = String($(this).val() || '').trim();
    const texto = normalizarBusquedaProducto(textoOriginal);

    window.clearTimeout(temporizadorBusquedaProducto);

    temporizadorBusquedaProducto = window.setTimeout(function () {
        if (texto === '') {
            renderProductos(productosCache);
            actualizarAyudaBusqueda(
                'La búsqueda por código de barras es global. Presiona Enter para agregar el producto exacto.',
                'info'
            );
            return;
        }

        const filtrados = productosCache.filter(function (producto) {
            const nombre = normalizarBusquedaProducto(producto.nombre);
            const codigo = normalizarBusquedaProducto(producto.codigo);

            return nombre.includes(texto) || codigo.includes(texto);
        });

        renderProductos(filtrados);

        if (filtrados.length > 0) {
            actualizarAyudaBusqueda(
                `${filtrados.length} producto(s) encontrado(s) en la categoría actual. Enter busca el código globalmente.`,
                'success'
            );
        } else {
            actualizarAyudaBusqueda(
                'No aparece en esta categoría. Presiona Enter para buscar ese código en todo el inventario.',
                'warning'
            );
        }
    }, 90);
});

$(document).on('keydown', '#buscarProducto', function (evento) {
    if (evento.key !== 'Enter') {
        return;
    }

    evento.preventDefault();
    evento.stopPropagation();

    const codigo = String($(this).val() || '').trim();

    if (codigo.length < 2) {
        actualizarAyudaBusqueda(
            'Escribe o escanea un código válido.',
            'warning'
        );
        return;
    }

    buscarProductoPorCodigo(codigo, {
        origen: 'modal'
    });
});

$(document).on('click', '#btnAbrirModal', function () {
    cerrarProductoRapido(true);
    $('#buscarProducto').val('');
    actualizarAyudaBusqueda(
        'La búsqueda por código de barras es global. Presiona Enter para agregar el producto exacto.',
        'info'
    );
    $('#modalProductos').modal('show');
    listarCategorias();
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

$('#modalProductos').on('shown.bs.modal', function () {
    window.setTimeout(function () {
        $('#buscarProducto').trigger('focus');
    }, 120);
});

$('#modalProductos').on('hidden.bs.modal', function () {
    cerrarProductoRapido(true);
    limpiarCapturaEscaner();
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
// SCANNER GLOBAL DE CÓDIGO DE BARRAS
// ===============================

function esElementoEditable(elemento) {
    if (!elemento) {
        return false;
    }

    return Boolean(
        elemento.closest(
            'input:not(#scannerInput), textarea, select, [contenteditable="true"]'
        )
    );
}

function limpiarCapturaEscaner() {
    window.clearTimeout(ESTADO_ESCANER.temporizador);

    ESTADO_ESCANER.buffer = '';
    ESTADO_ESCANER.inicio = 0;
    ESTADO_ESCANER.ultimo = 0;

    const input = document.getElementById('scannerInput');

    if (input) {
        input.value = '';
    }
}

function mostrarFeedbackEscaner(mensaje) {
    $('#scannerFeedback').remove();

    const $feedback = $(`
        <div class="scanner-feedback" id="scannerFeedback" role="status">
            <i class="bi bi-upc-scan"></i>
            <span>${escaparHtmlProducto(mensaje)}</span>
        </div>
    `);

    $('body').append($feedback);

    window.setTimeout(function () {
        $feedback.fadeOut(180, function () {
            $(this).remove();
        });
    }, 1500);
}

function activarEscanerProductos(origen = 'pantalla') {
    limpiarCapturaEscaner();

    const input = document.getElementById('scannerInput');

    if (!input) {
        Swal.fire(
            'Lector no disponible',
            'No se encontró el campo de captura del escáner.',
            'error'
        );
        return;
    }

    if ($('#modalProductos').hasClass('show')) {
        actualizarAyudaBusqueda(
            'Lector activo. Escanea ahora el código de barras.',
            'loading'
        );
    }

    mostrarFeedbackEscaner('Lector activo: escanea el código ahora');

    /*
     * El aviso es un elemento pasivo y no roba el foco.
     * Se repite el focus para cubrir animaciones del modal/botón.
     */
    input.focus({ preventScroll: true });

    window.setTimeout(function () {
        input.focus({ preventScroll: true });
    }, 60);
}

function procesarCodigoEscaneado(codigo, origen = 'lector') {
    const codigoLimpio = String(codigo || '')
        .replace(/[\r\n\t]/g, '')
        .trim();

    limpiarCapturaEscaner();

    if (codigoLimpio.length < 2) {
        return;
    }

    buscarProductoPorCodigo(codigoLimpio, {
        origen: origen
    });
}

function inicializarEscanerProductos() {
    const scannerInput = document.getElementById('scannerInput');

    if (scannerInput) {
        scannerInput.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Enter' && evento.key !== 'Tab') {
                return;
            }

            evento.preventDefault();
            evento.stopPropagation();

            procesarCodigoEscaneado(
                scannerInput.value,
                'lector-activado'
            );
        });

        scannerInput.addEventListener('input', function () {
            window.clearTimeout(ESTADO_ESCANER.temporizador);

            ESTADO_ESCANER.temporizador = window.setTimeout(function () {
                const valor = String(scannerInput.value || '').trim();

                /*
                 * Algunos lectores no envían Enter. Si dejaron de escribir
                 * durante 180 ms, procesamos el código completo.
                 */
                if (valor.length >= 3) {
                    procesarCodigoEscaneado(
                        valor,
                        'lector-sin-enter'
                    );
                }
            }, 180);
        });
    }

    document.addEventListener('keydown', function (evento) {
        if (evento.ctrlKey || evento.altKey || evento.metaKey) {
            return;
        }

        if (
            evento.target
            && evento.target.id === 'scannerInput'
        ) {
            return;
        }

        /*
         * Dentro del buscador del modal, Enter ejecuta la búsqueda global.
         * Los demás campos se respetan para no mezclar códigos con DNI,
         * cantidades, precios u observaciones.
         */
        if (esElementoEditable(evento.target)) {
            return;
        }

        const ahora = Date.now();

        if (evento.key === 'Enter' || evento.key === 'Tab') {
            const duracion = ESTADO_ESCANER.inicio > 0
                ? ahora - ESTADO_ESCANER.inicio
                : 0;

            const pareceLectura =
                ESTADO_ESCANER.buffer.length >= 3
                && duracion > 0
                && duracion <= 1800;

            if (pareceLectura) {
                evento.preventDefault();
                evento.stopPropagation();

                procesarCodigoEscaneado(
                    ESTADO_ESCANER.buffer,
                    'lector-global'
                );
            } else {
                limpiarCapturaEscaner();
            }

            return;
        }

        if (evento.key.length !== 1) {
            return;
        }

        const separacion = ESTADO_ESCANER.ultimo > 0
            ? ahora - ESTADO_ESCANER.ultimo
            : 0;

        if (separacion > 140) {
            ESTADO_ESCANER.buffer = '';
            ESTADO_ESCANER.inicio = ahora;
        }

        if (ESTADO_ESCANER.buffer === '') {
            ESTADO_ESCANER.inicio = ahora;
        }

        ESTADO_ESCANER.buffer += evento.key;
        ESTADO_ESCANER.ultimo = ahora;

        window.clearTimeout(ESTADO_ESCANER.temporizador);
        ESTADO_ESCANER.temporizador = window.setTimeout(function () {
            limpiarCapturaEscaner();
        }, 900);
    }, true);
}

$(document).on(
    'click',
    '#btnActivarEscaner, #btnEscanearDesdeModal, #btnEscanearModalFooter',
    function () {
        activarEscanerProductos(this.id);
    }
);

function normalizarRespuestaProductoEscaneado(respuesta) {
    if (!respuesta || typeof respuesta !== 'object') {
        return null;
    }

    if (
        respuesta.producto
        && typeof respuesta.producto === 'object'
    ) {
        return respuesta.producto;
    }

    if (
        respuesta.data
        && typeof respuesta.data === 'object'
        && !Array.isArray(respuesta.data)
    ) {
        return respuesta.data;
    }

    if (Array.isArray(respuesta)) {
        return respuesta.length > 0 ? respuesta[0] : null;
    }

    return respuesta;
}

function codigoProductoNormalizado(valor) {
    return String(valor || '')
        .replace(/[\r\n\t]/g, '')
        .trim()
        .toUpperCase();
}

function productoEscaneadoValido(producto) {
    return Boolean(
        producto
        && typeof producto === 'object'
        && (Number.parseInt(producto.idarticulo, 10) || 0) > 0
    );
}

function cargarCategoriasParaBusquedaGlobal() {
    const diferido = $.Deferred();

    if (Array.isArray(categoriasCache) && categoriasCache.length > 0) {
        diferido.resolve(categoriasCache);
        return diferido.promise();
    }

    $.ajax({
        url: 'Controllers/Sell.php?op=listarCategorias',
        type: 'GET',
        dataType: 'json',
        cache: false
    })
        .done(function (respuesta) {
            categoriasCache = Array.isArray(respuesta) ? respuesta : [];
            diferido.resolve(categoriasCache);
        })
        .fail(function () {
            diferido.reject();
        });

    return diferido.promise();
}

function buscarCodigoEnTodasLasCategorias(codigo) {
    const diferido = $.Deferred();
    const codigoBuscado = codigoProductoNormalizado(codigo);

    cargarCategoriasParaBusquedaGlobal()
        .done(function (categorias) {
            let indice = 0;

            function buscarSiguienteCategoria() {
                if (indice >= categorias.length) {
                    diferido.resolve(null);
                    return;
                }

                const idcategoria = Number.parseInt(
                    categorias[indice].idcategoria,
                    10
                ) || 0;

                indice += 1;

                if (idcategoria <= 0) {
                    buscarSiguienteCategoria();
                    return;
                }

                $.ajax({
                    url: 'Controllers/Sell.php?op=listarArticulosPorCategoria',
                    type: 'GET',
                    dataType: 'json',
                    cache: false,
                    data: {
                        idcategoria: idcategoria,
                        v: Date.now()
                    }
                })
                    .done(function (productos) {
                        const lista = Array.isArray(productos)
                            ? productos
                            : [];

                        const encontrado = lista.find(function (producto) {
                            return codigoProductoNormalizado(
                                producto.codigo
                            ) === codigoBuscado;
                        });

                        if (encontrado) {
                            diferido.resolve(encontrado);
                            return;
                        }

                        buscarSiguienteCategoria();
                    })
                    .fail(function () {
                        /* Una categoría con error no detiene la búsqueda. */
                        buscarSiguienteCategoria();
                    });
            }

            buscarSiguienteCategoria();
        })
        .fail(function () {
            diferido.resolve(null);
        });

    return diferido.promise();
}

function agregarProductoEncontradoPorCodigo(producto, codigoLimpio) {
    const idarticulo = Number.parseInt(
        producto && producto.idarticulo,
        10
    ) || 0;
    const stock = Number.parseInt(
        producto && producto.stock,
        10
    ) || 0;

    if (idarticulo <= 0) {
        return false;
    }

    if (stock <= 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Producto sin stock',
            text: `${String(producto.nombre || 'El producto')} no tiene unidades disponibles.`
        });
        return true;
    }

    agregarDetalle(
        Number.parseInt(
            producto.idingreso || producto.iddetalle_ingreso,
            10
        ) || 0,
        idarticulo,
        String(producto.codigo || codigoLimpio),
        String(producto.nombre || 'Producto'),
        Number.parseFloat(producto.precio_compra) || 0,
        Number.parseFloat(producto.precio_venta) || 0,
        stock,
        1
    );

    return true;
}

function finalizarBusquedaGlobalCodigo() {
    buscandoCodigoProducto = false;

    $('#pedidoVacio').removeClass('opacity-25');
    $('#btnActivarEscaner, #btnEscanearDesdeModal, #btnEscanearModalFooter')
        .prop('disabled', false);

    const input = document.getElementById('scannerInput');

    if (
        input
        && !$('#modalProductos').hasClass('show')
        && !esElementoEditable(document.activeElement)
    ) {
        input.focus({ preventScroll: true });
    }
}

function mostrarCodigoNoEncontrado(codigoLimpio) {
    if ($('#modalProductos').hasClass('show')) {
        actualizarAyudaBusqueda(
            `No existe un producto con el código ${codigoLimpio}.`,
            'warning'
        );
    }

    Swal.fire({
        icon: 'warning',
        title: 'Código no encontrado',
        text: `No existe un producto disponible con el código ${codigoLimpio}.`,
        confirmButtonText: 'Entendido'
    });
}

function ejecutarRespaldoBusquedaCodigo(codigoLimpio) {
    if ($('#modalProductos').hasClass('show')) {
        actualizarAyudaBusqueda(
            'Verificando el código en todas las categorías...',
            'loading'
        );
    }

    buscarCodigoEnTodasLasCategorias(codigoLimpio)
        .done(function (productoAlternativo) {
            if (
                !productoEscaneadoValido(productoAlternativo)
                || !agregarProductoEncontradoPorCodigo(
                    productoAlternativo,
                    codigoLimpio
                )
            ) {
                mostrarCodigoNoEncontrado(codigoLimpio);
            }
        })
        .always(function () {
            finalizarBusquedaGlobalCodigo();
        });
}

function buscarProductoPorCodigo(codigo, opciones = {}) {
    const codigoLimpio = codigoProductoNormalizado(codigo);

    if (codigoLimpio.length < 2 || buscandoCodigoProducto) {
        return;
    }

    buscandoCodigoProducto = true;

    $('#pedidoVacio').addClass('opacity-25');
    $('#btnActivarEscaner, #btnEscanearDesdeModal, #btnEscanearModalFooter')
        .prop('disabled', true);

    if ($('#modalProductos').hasClass('show')) {
        $('#buscarProducto').val(codigoLimpio);
        actualizarAyudaBusqueda(
            `Buscando el código ${codigoLimpio} en todo el inventario...`,
            'loading'
        );
    }

    $.ajax({
        url: 'Controllers/Sell.php?op=buscarProductoPorCodigo',
        type: 'POST',
        dataType: 'json',
        cache: false,
        data: {
            codigo: codigoLimpio
        }
    })
        .done(function (respuesta) {
            const producto = normalizarRespuestaProductoEscaneado(
                respuesta
            );

            if (
                productoEscaneadoValido(producto)
                && agregarProductoEncontradoPorCodigo(
                    producto,
                    codigoLimpio
                )
            ) {
                finalizarBusquedaGlobalCodigo();
                return;
            }

            /*
             * Respaldo real: si el endpoint principal no encuentra el código,
             * se revisan las categorías una por una usando los endpoints que
             * ya existen en esta pantalla.
             */
            ejecutarRespaldoBusquedaCodigo(codigoLimpio);
        })
        .fail(function (xhr) {
            console.error(
                'ERROR ENDPOINT BUSCAR CÓDIGO:',
                xhr.status,
                xhr.responseText
            );

            ejecutarRespaldoBusquedaCodigo(codigoLimpio);
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
    if (textoNormalizado(condicion) === 'CREDITO') {

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