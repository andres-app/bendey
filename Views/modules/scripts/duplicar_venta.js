(function ($) {
    'use strict';

    const PARAMETRO_DUPLICAR = 'duplicar';

    function numeroSeguro(valor, defecto = 0) {
        const numero = Number.parseFloat(valor);
        return Number.isFinite(numero) ? numero : defecto;
    }

    function enteroSeguro(valor, defecto = 0) {
        const numero = Number.parseInt(valor, 10);
        return Number.isFinite(numero) ? numero : defecto;
    }

    function normalizarTexto(valor) {
        return String(valor || '')
            .trim()
            .toUpperCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function escaparHtml(valor) {
        return String(valor ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function obtenerIdDuplicacion() {
        const parametros = new URLSearchParams(window.location.search);
        const idventa = enteroSeguro(
            parametros.get(PARAMETRO_DUPLICAR),
            0
        );

        return idventa > 0 ? idventa : 0;
    }

    function limpiarParametroDuplicacion() {
        const url = new URL(window.location.href);
        url.searchParams.delete(PARAMETRO_DUPLICAR);

        window.history.replaceState(
            {},
            document.title,
            url.pathname + url.search + url.hash
        );
    }

    function esperarFormularioListo() {
        return new Promise(function (resolve, reject) {
            let intentos = 0;
            const maximoIntentos = 120;

            const temporizador = window.setInterval(function () {
                intentos += 1;

                const listo =
                    document.getElementById('formularioVenta') !== null
                    && $('#tipo_comprobante option').length > 1
                    && $('#tipo_pago option').length > 1
                    && $('#forma_pago option').length > 1
                    && typeof window.agregarDetalle === 'function'
                    && typeof window.calcularTotales === 'function'
                    && typeof window.actualizarMensajePedido === 'function';

                if (listo) {
                    window.clearInterval(temporizador);
                    resolve();
                    return;
                }

                if (intentos >= maximoIntentos) {
                    window.clearInterval(temporizador);
                    reject(
                        new Error(
                            'La pantalla de nueva venta no terminó de cargar.'
                        )
                    );
                }
            }, 100);
        });
    }

    function seleccionarOpcionFlexible(selector, valor) {
        const $select = $(selector);
        const buscado = String(valor ?? '').trim();

        if (!$select.length || buscado === '') {
            return false;
        }

        const buscadoNormalizado = normalizarTexto(buscado);
        let encontrado = '';

        $select.find('option').each(function () {
            const valorOpcion = String($(this).val() ?? '').trim();
            const textoOpcion = String($(this).text() ?? '').trim();

            if (
                valorOpcion === buscado
                || normalizarTexto(valorOpcion) === buscadoNormalizado
                || normalizarTexto(textoOpcion) === buscadoNormalizado
                || normalizarTexto(textoOpcion).includes(buscadoNormalizado)
                || buscadoNormalizado.includes(normalizarTexto(textoOpcion))
            ) {
                encontrado = valorOpcion;
                return false;
            }
        });

        if (encontrado === '') {
            return false;
        }

        $select.val(encontrado).trigger('change');
        return true;
    }

    function cargarCliente(cliente) {
        const numeroDocumento = String(
            cliente.num_documento || ''
        ).replace(/\D/g, '');

        const esGenerico = numeroDocumento === '99999999';

        $('#idcliente').val(
            enteroSeguro(cliente.idcliente, 0) || ''
        );
        $('#tipo_documento').val(
            String(cliente.tipo_documento || '')
        );
        $('#num_doc_real').val(numeroDocumento);
        $('#nombre_cli').val(String(cliente.nombre || ''));
        $('#direccion').val(String(cliente.direccion || ''));
        $('#email').val(String(cliente.email || ''));
        $('#celular').val(
            String(cliente.telefono || '')
                .replace(/\D/g, '')
                .replace(/^51/, '')
                .slice(-9)
        );
        $('#cliente_generico').val(esGenerico ? '1' : '0');
        $('#num_documento').val(esGenerico ? '' : numeroDocumento);

        $('#nombre_cliente')
            .removeClass(
                'text-muted text-primary text-danger'
            )
            .addClass('text-success')
            .text(
                esGenerico
                    ? 'CLIENTE VARIOS'
                    : String(cliente.nombre || 'Cliente cargado')
            );
    }

    function limpiarProductosActuales() {
        $('#detallesCards').empty();
        window.cont = 0;
        window.actualizarMensajePedido();
        window.calcularTotales();
    }

    function buscarTarjetaArticulo(idarticulo) {
        let $encontrado = $();

        $("input[name='idarticulo[]']").each(function () {
            if (
                enteroSeguro($(this).val(), 0)
                === enteroSeguro(idarticulo, 0)
            ) {
                $encontrado = $(this).closest('.filas');
                return false;
            }
        });

        return $encontrado;
    }

    function cargarProductos(productos) {
        const lista = Array.isArray(productos) ? productos : [];
        let cargados = 0;

        limpiarProductosActuales();

        lista.forEach(function (producto) {
            if (!producto || producto.puede_cargar !== true) {
                return;
            }

            const cantidad = enteroSeguro(
                producto.cantidad_cargar,
                0
            );
            const stock = enteroSeguro(producto.stock, 0);

            if (cantidad <= 0 || stock <= 0) {
                return;
            }

            window.agregarDetalle(
                enteroSeguro(producto.idingreso, 0),
                enteroSeguro(producto.idarticulo, 0),
                String(producto.codigo || ''),
                String(producto.articulo || 'Producto'),
                numeroSeguro(producto.precio_compra, 0),
                numeroSeguro(producto.precio_venta, 0),
                stock,
                1
            );

            const $tarjeta = buscarTarjetaArticulo(
                producto.idarticulo
            );

            if (!$tarjeta.length) {
                return;
            }

            const precioVenta = numeroSeguro(
                producto.precio_venta,
                0
            );

            $tarjeta
                .find("input[name='idingreso[]']")
                .val(enteroSeguro(producto.idingreso, 0));

            $tarjeta
                .find("input[name='precio_compra[]']")
                .val(
                    numeroSeguro(
                        producto.precio_compra,
                        0
                    ).toFixed(2)
                );

            $tarjeta
                .find("input[name='precio_venta[]']")
                .val(precioVenta.toFixed(2));

            $tarjeta
                .find("input[name='cantidad[]']")
                .val(cantidad);

            $tarjeta
                .find("[id^='cantidadLabel']")
                .text(cantidad);

            $tarjeta
                .find("[id^='subtotal']")
                .text((cantidad * precioVenta).toFixed(2));

            cargados += 1;
        });

        window.calcularTotales();
        window.actualizarMensajePedido();

        return cargados;
    }

    function cargarDescuento(venta) {
        const descuentoTotal = Math.max(
            0,
            numeroSeguro(venta.descuento_total, 0)
        );

        if (descuentoTotal <= 0) {
            $('#descuentoSwitch')
                .prop('checked', true)
                .trigger('change');
            $('#descuentoPorcentaje')
                .val('0')
                .trigger('input');
            return;
        }

        /*
         * Se usa el modo soles para mantener exactamente el descuento
         * original, aun cuando el porcentaje almacenado haya sido redondeado.
         */
        $('#descuentoSwitch')
            .prop('checked', false)
            .trigger('change');

        $('#descuentoPorcentaje')
            .val(descuentoTotal.toFixed(2))
            .trigger('input');
    }

    function cargarPago(venta) {
        seleccionarOpcionFlexible(
            '#tipo_pago',
            venta.tipo_pago
        );

        const idFormaPago = enteroSeguro(
            venta.idforma_pago,
            0
        );

        if (idFormaPago > 0) {
            $('#forma_pago')
                .val(String(idFormaPago))
                .trigger('change');
        }

        const tipoPagoNormalizado = normalizarTexto(
            venta.tipo_pago
        );

        const esCredito =
            tipoPagoNormalizado === '4'
            || tipoPagoNormalizado.includes('CREDITO');

        if (esCredito) {
            const numeroCuotas = enteroSeguro(
                venta.numero_cuotas,
                0
            );

            if (numeroCuotas > 0) {
                $('#numero_cuotas')
                    .val(numeroCuotas)
                    .trigger('input');
            }

            /* La fecha anterior no se reutiliza. */
            $('#fecha_pago').val('');
        }
    }

    function actualizarEncabezado(origen) {
        const comprobante = String(
            origen.comprobante || ''
        ).trim();

        if (comprobante !== '') {
            $('.card-header h4')
                .first()
                .text('Nueva venta basada en ' + comprobante);
        }
    }

    function mostrarResultado(origen, cargados, advertencias) {
        const listaAdvertencias = Array.isArray(advertencias)
            ? advertencias
            : [];

        let html =
            '<div style="text-align:left">' +
            '<p>Se preparó una nueva venta basada en <strong>' +
            escaparHtml(origen.comprobante || '') +
            '</strong>.</p>' +
            '<p>Productos cargados: <strong>' + cargados + '</strong>.</p>' +
            '<p>Puede cambiar cliente, productos, cantidades, precios, descuento y forma de pago antes de procesarla.</p>' +
            '<p><strong>No se copió el correlativo anterior.</strong> El número definitivo se asignará al guardar.</p>';

        if (listaAdvertencias.length > 0) {
            html +=
                '<hr><strong>Debe revisar:</strong><ul>' +
                listaAdvertencias.map(function (mensaje) {
                    return '<li>' + escaparHtml(mensaje) + '</li>';
                }).join('') +
                '</ul>';
        }

        html += '</div>';

        Swal.fire({
            icon: listaAdvertencias.length > 0
                ? 'warning'
                : 'success',
            title: 'Nueva venta preparada',
            html: html,
            confirmButtonText: 'Revisar venta'
        });
    }

    function solicitarPlantilla(idventa) {
        return $.ajax({
            url: 'Controllers/Sell.php',
            type: 'GET',
            dataType: 'json',
            cache: false,
            data: {
                op: 'duplicar',
                idventa: idventa,
                v: Date.now()
            }
        });
    }

    function cargarDuplicacion(respuesta) {
        if (!respuesta || respuesta.success !== true) {
            throw new Error(
                String(
                    respuesta && respuesta.mensaje
                        ? respuesta.mensaje
                        : 'No se pudo cargar el comprobante.'
                )
            );
        }

        const venta = respuesta.venta || {};
        const cliente = respuesta.cliente || {};
        const origen = respuesta.origen || {};

        if (
            !seleccionarOpcionFlexible(
                '#tipo_comprobante',
                venta.tipo_comprobante
            )
        ) {
            throw new Error(
                'El tipo de comprobante original ya no está activo.'
            );
        }

        cargarCliente(cliente);

        const cargados = cargarProductos(
            respuesta.productos
        );

        cargarDescuento(venta);
        cargarPago(venta);
        window.calcularTotales();
        actualizarEncabezado(origen);
        limpiarParametroDuplicacion();

        if (
            typeof window.ventaColaSincronizarDesdeFormulario === 'function'
        ) {
            window.ventaColaSincronizarDesdeFormulario({
                nombreSugerido:
                    origen.comprobante
                        ? 'Copia ' + String(origen.comprobante)
                        : 'Venta duplicada'
            });
        }

        mostrarResultado(
            origen,
            cargados,
            respuesta.advertencias
        );
    }

    $(document).ready(function () {
        const idventa = obtenerIdDuplicacion();

        if (idventa <= 0) {
            return;
        }

        Swal.fire({
            title: 'Cargando comprobante',
            text: 'Preparando una nueva venta editable...',
            allowOutsideClick: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        esperarFormularioListo()
            .then(function () {
                if (
                    typeof window.ventaColaPrepararDuplicacion === 'function'
                ) {
                    return window.ventaColaPrepararDuplicacion();
                }

                return null;
            })
            .then(function () {
                return solicitarPlantilla(idventa);
            })
            .then(function (respuesta) {
                Swal.close();
                cargarDuplicacion(respuesta);
            })
            .catch(function (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo duplicar',
                    text: String(
                        error && error.message
                            ? error.message
                            : 'Ocurrió un error al preparar la nueva venta.'
                    )
                });
            });
    });
})(jQuery);
