'use strict';

let tablaCompras = null;
let detallesCompra = [];
let productosCompra = [];
let datosFormularioCompra = {
    categorias: [],
    subcategorias: [],
    medidas: [],
    almacenes: [],
    categorias_compra: [],
    tipos_pago: [],
    formas_pago: []
};
let datosCompraCargados = false;
let guardandoCompra = false;
let temporizadorCoincidencias = null;
let modoProductoNuevoCompra = 'individual';
let secuenciaFilaMasivaCompra = 0;
let catalogosMasivosCompraProducto = {
    categorias: [],
    subcategorias: [],
    almacenes: [],
    medidas: [],
    afectaciones_igv: [],
    tributacion_predeterminada: { codigo_afectacion_igv: '10' }
};
let catalogosMasivosCompraCargados = false;
let cargandoCatalogosMasivosCompra = null;

function escaparHtmlCompra(valor) {
    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function normalizarTextoCompra(valor) {
    return String(valor || '')
        .trim()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function numeroCompra(valor, decimales = 2) {
    const numero = Number.parseFloat(valor);

    if (!Number.isFinite(numero)) {
        return 0;
    }

    return Number(numero.toFixed(decimales));
}

function formatearMonedaCompra(valor) {
    return 'S/ ' + numeroCompra(valor).toFixed(2);
}

function alertaCompra(icono, titulo, mensaje) {
    if (typeof Swal !== 'undefined' && Swal.fire) {
        return Swal.fire({
            icon: icono,
            title: titulo,
            text: mensaje
        });
    }

    if (typeof swal !== 'undefined') {
        return swal({
            icon: icono,
            title: titulo,
            text: mensaje
        });
    }

    window.alert(titulo + '\n\n' + mensaje);
    return Promise.resolve();
}

function confirmarCompra(titulo, mensaje, textoConfirmar) {
    if (typeof Swal !== 'undefined' && Swal.fire) {
        return Swal.fire({
            icon: 'warning',
            title: titulo,
            text: mensaje,
            showCancelButton: true,
            confirmButtonText: textoConfirmar,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        }).then(function (resultado) {
            return Boolean(resultado.isConfirmed);
        });
    }

    if (typeof swal !== 'undefined') {
        return swal({
            icon: 'warning',
            title: titulo,
            text: mensaje,
            buttons: {
                cancel: 'Cancelar',
                confirm: textoConfirmar
            },
            dangerMode: true
        });
    }

    return Promise.resolve(window.confirm(mensaje));
}

function mensajeRespuestaCompra(xhr, predeterminado) {
    if (
        xhr
        && xhr.responseJSON
        && typeof xhr.responseJSON.mensaje === 'string'
    ) {
        return xhr.responseJSON.mensaje;
    }

    if (xhr && typeof xhr.responseText === 'string') {
        try {
            const respuesta = JSON.parse(xhr.responseText);

            if (respuesta && typeof respuesta.mensaje === 'string') {
                return respuesta.mensaje;
            }
        } catch (error) {
            // La respuesta no era JSON.
        }
    }

    return predeterminado;
}

function fechaActualCompra() {
    const ahora = new Date();
    const anio = ahora.getFullYear();
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const dia = String(ahora.getDate()).padStart(2, '0');

    return `${anio}-${mes}-${dia}`;
}

function limpiarCompra() {
    const formulario = document.getElementById('formulario');

    if (formulario) {
        formulario.reset();
    }

    $('#idingreso').val('');
    $('#fecha_hora').val(fechaActualCompra());
    $('#tipo_comprobante').val('Factura');
    $('#impuesto').val('18');
    $('#serie_comprobante').val('');
    $('#num_comprobante').val('');
    $('#observacion').val('');

    detallesCompra = [];
    renderizarDetallesCompra();

    const formProductoNuevo = document.getElementById('formProductoNuevo');
    const formGastoServicio = document.getElementById('formGastoServicio');

    if (formProductoNuevo) {
        formProductoNuevo.reset();
    }

    if (formGastoServicio) {
        formGastoServicio.reset();
    }

    $('#nuevo_cantidad').val('1');
    $('#gasto_cantidad').val('1');
    $('#coincidenciasProductoNuevo').hide().empty();
}

function mostrarform(flag) {
    limpiarCompra();

    if (flag) {
        $('#listadoregistros').hide();
        $('#formularioregistros').show();
        $('#btnagregar').hide();
        $('#comprasExportDropdown').hide();
        $('#btnCancelar').show();

        cargarDatosCompra();
        window.setTimeout(function () {
            $('#idproveedor').trigger('focus');
        }, 120);
    } else {
        $('#formularioregistros').hide();
        $('#listadoregistros').show();
        $('#btnagregar').show();
        $('#comprasExportDropdown').show();
    }
}

function cancelarform() {
    mostrarform(false);
}

function cargarProveedoresCompra(idSeleccionar = null) {
    const seleccionActual = idSeleccionar !== null
        ? String(idSeleccionar)
        : String($('#idproveedor').val() || '');

    return $.ajax({
        url: 'Controllers/Buy.php?op=selectProveedor',
        method: 'GET',
        cache: false
    })
        .done(function (respuesta) {
            $('#idproveedor').html(respuesta);
            if (seleccionActual) {
                $('#idproveedor').val(seleccionActual);
            }
        })
        .fail(function () {
            $('#idproveedor').html(
                '<option value="">No se pudieron cargar los proveedores</option>'
            );
        });
}

function limpiarProveedorCompra() {
    const form = document.getElementById('formProveedorCompra');
    if (form) {
        form.reset();
    }

    $('#proveedor_tipo_documento').val('RUC');
    $('#proveedor_num_documento')
        .attr('maxlength', '11')
        .attr('placeholder', 'Ingresa el RUC')
        .val('');
    $('#proveedor_nombre, #proveedor_direccion, #proveedor_telefono, #proveedor_email').val('');
    $('#proveedorApiEstado')
        .addClass('tw-hidden')
        .removeClass('is-success is-error')
        .empty();
}

function actualizarDocumentoProveedorCompra() {
    const tipo = String($('#proveedor_tipo_documento').val() || 'RUC').toUpperCase();
    const longitud = tipo === 'DNI' ? 8 : 11;
    $('#proveedor_num_documento')
        .attr('maxlength', String(longitud))
        .attr('placeholder', tipo === 'DNI' ? 'Ingresa el DNI' : 'Ingresa el RUC')
        .val(String($('#proveedor_num_documento').val() || '').replace(/\D/g, '').slice(0, longitud));
}

function mostrarEstadoProveedorApi(tipo, mensaje) {
    $('#proveedorApiEstado')
        .removeClass('tw-hidden is-success is-error')
        .addClass(tipo === 'success' ? 'is-success' : 'is-error')
        .html(mensaje);
}

function consultarProveedorCompraApi() {
    const tipoDocumento = String($('#proveedor_tipo_documento').val() || '').toUpperCase();
    const numeroDocumento = String($('#proveedor_num_documento').val() || '').replace(/\D/g, '');
    const longitud = tipoDocumento === 'DNI' ? 8 : 11;

    if (numeroDocumento.length !== longitud) {
        mostrarEstadoProveedorApi(
            'error',
            `<i class="fas fa-exclamation-circle mr-1"></i> ${tipoDocumento} debe tener ${longitud} dígitos.`
        );
        return;
    }

    const $boton = $('#btnConsultarProveedorApi');
    $boton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Consultando...');
    mostrarEstadoProveedorApi('success', '<span class="spinner-border spinner-border-sm mr-1"></span> Consultando PeruDev...');

    $.ajax({
        url: 'Controllers/Buy.php?op=consultarProveedorApi',
        method: 'POST',
        dataType: 'json',
        data: {
            tipo_documento: tipoDocumento,
            num_documento: numeroDocumento
        }
    }).done(function (respuesta) {
        if (!respuesta || respuesta.success !== true) {
            mostrarEstadoProveedorApi(
                'error',
                `<i class="fas fa-exclamation-circle mr-1"></i> ${escaparHtmlCompra((respuesta && respuesta.mensaje) || 'No se encontraron datos.')}`
            );
            return;
        }

        if (respuesta.existente && respuesta.proveedor) {
            const proveedor = respuesta.proveedor;
            cargarProveedoresCompra(proveedor.idpersona).then(function () {
                $('#modalProveedorCompra').modal('hide');
                alertaCompra('success', 'Proveedor seleccionado', 'El proveedor ya estaba registrado y quedó seleccionado en la compra.');
            });
            return;
        }

        const resultado = respuesta.resultado || {};
        const nombre = String(
            resultado.razon_social
            || resultado.nombre_o_razon_social
            || resultado.nombre_completo
            || resultado.nombre
            || ''
        ).trim();
        const direccion = String(
            resultado.direccion_completa
            || resultado.direccion
            || resultado.direccion_fiscal
            || resultado.domicilio_fiscal
            || ''
        ).trim();

        if (nombre) {
            $('#proveedor_nombre').val(nombre);
        }
        if (direccion) {
            $('#proveedor_direccion').val(direccion);
        }

        mostrarEstadoProveedorApi(
            'success',
            '<i class="fas fa-check-circle mr-1"></i> Datos encontrados en PeruDev. Revisa la información y guarda el proveedor.'
        );
        $('#proveedor_nombre').trigger('focus');
    }).fail(function (xhr) {
        mostrarEstadoProveedorApi(
            'error',
            `<i class="fas fa-exclamation-circle mr-1"></i> ${escaparHtmlCompra(mensajeRespuestaCompra(xhr, 'No se pudo consultar PeruDev.'))}`
        );
    }).always(function () {
        $boton.prop('disabled', false).html('<i class="fas fa-search"></i> Consultar PeruDev');
    });
}

function guardarProveedorDesdeCompra(evento) {
    evento.preventDefault();

    const formulario = document.getElementById('formProveedorCompra');
    if (!formulario || !formulario.checkValidity()) {
        if (formulario) formulario.reportValidity();
        return;
    }

    const tipoDocumento = String($('#proveedor_tipo_documento').val() || '').toUpperCase();
    const numeroDocumento = String($('#proveedor_num_documento').val() || '').replace(/\D/g, '');
    const nombre = String($('#proveedor_nombre').val() || '').trim();
    const longitud = tipoDocumento === 'DNI' ? 8 : 11;

    if (numeroDocumento.length !== longitud) {
        mostrarEstadoProveedorApi('error', `${tipoDocumento} debe tener ${longitud} dígitos.`);
        return;
    }

    const $boton = $('#btnGuardarProveedorCompra');
    $boton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Guardando...');

    $.ajax({
        url: 'Controllers/Buy.php?op=crearProveedor',
        method: 'POST',
        dataType: 'json',
        data: {
            tipo_documento: tipoDocumento,
            num_documento: numeroDocumento,
            nombre: nombre,
            direccion: String($('#proveedor_direccion').val() || '').trim(),
            telefono: String($('#proveedor_telefono').val() || '').trim(),
            email: String($('#proveedor_email').val() || '').trim()
        }
    }).done(function (respuesta) {
        if (!respuesta || respuesta.success !== true || !respuesta.proveedor) {
            mostrarEstadoProveedorApi('error', escaparHtmlCompra((respuesta && respuesta.mensaje) || 'No se pudo guardar el proveedor.'));
            return;
        }

        cargarProveedoresCompra(respuesta.proveedor.idpersona).then(function () {
            $('#modalProveedorCompra').modal('hide');
            alertaCompra('success', 'Proveedor listo', respuesta.mensaje || 'Proveedor registrado correctamente.');
        });
    }).fail(function (xhr) {
        mostrarEstadoProveedorApi('error', escaparHtmlCompra(mensajeRespuestaCompra(xhr, 'No se pudo guardar el proveedor.')));
    }).always(function () {
        $boton.prop('disabled', false).html('<i class="fas fa-save"></i> Guardar proveedor');
    });
}

function cargarDatosCompra(forzar = false) {
    if (datosCompraCargados && !forzar) {
        poblarSelectoresCompra();
        return $.Deferred().resolve().promise();
    }

    return $.ajax({
        url: 'Controllers/Buy.php?op=datosFormulario',
        method: 'GET',
        dataType: 'json',
        cache: false,
        data: { v: Date.now() }
    })
        .done(function (respuesta) {
            if (!respuesta || respuesta.success !== true || !respuesta.datos) {
                throw new Error(
                    respuesta && respuesta.mensaje
                        ? respuesta.mensaje
                        : 'No se recibieron los datos del formulario.'
                );
            }

            datosFormularioCompra = {
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
                    : [],
                categorias_compra: Array.isArray(respuesta.datos.categorias_compra)
                    ? respuesta.datos.categorias_compra
                    : [],
                tipos_pago: Array.isArray(respuesta.datos.tipos_pago)
                    ? respuesta.datos.tipos_pago
                    : [],
                formas_pago: Array.isArray(respuesta.datos.formas_pago)
                    ? respuesta.datos.formas_pago
                    : []
            };

            datosCompraCargados = true;
            poblarSelectoresCompra();
        })
        .fail(function (xhr) {
            alertaCompra(
                'error',
                'Datos no disponibles',
                mensajeRespuestaCompra(
                    xhr,
                    'No se pudieron cargar las categorías, unidades y almacenes.'
                )
            );
        });
}

function opcionesSelectCompra(
    registros,
    campoValor,
    textoRegistro,
    textoInicial
) {
    let html = `<option value="">${escaparHtmlCompra(textoInicial)}</option>`;

    registros.forEach(function (registro) {
        const valor = registro[campoValor];
        const texto = typeof textoRegistro === 'function'
            ? textoRegistro(registro)
            : registro[textoRegistro];

        html += `<option value="${escaparHtmlCompra(valor)}">${escaparHtmlCompra(texto)}</option>`;
    });

    return html;
}

function poblarSelectoresCompra() {
    $('#nuevo_idcategoria').html(
        opcionesSelectCompra(
            datosFormularioCompra.categorias,
            'idcategoria',
            'nombre',
            'Seleccione una categoría'
        )
    );

    $('#nuevo_idmedida, #gasto_idmedida').html(
        opcionesSelectCompra(
            datosFormularioCompra.medidas,
            'idmedida',
            function (medida) {
                const codigo = String(medida.codigo || '').trim();
                return codigo
                    ? `${medida.nombre} (${codigo})`
                    : medida.nombre;
            },
            'Seleccione una unidad'
        )
    );

    $('#nuevo_idalmacen').html(
        opcionesSelectCompra(
            datosFormularioCompra.almacenes,
            'idalmacen',
            'nombre',
            'Seleccione un almacén'
        )
    );

    $('#gasto_categoria').html(
        opcionesSelectCompra(
            datosFormularioCompra.categorias_compra,
            'idcategoria_compra',
            'nombre',
            'Seleccione una categoría'
        )
    );

    if (datosFormularioCompra.categorias.length > 0) {
        $('#nuevo_idcategoria').val(
            String(datosFormularioCompra.categorias[0].idcategoria)
        );
    }

    if (datosFormularioCompra.medidas.length > 0) {
        const medidaPredeterminada = datosFormularioCompra.medidas.find(
            function (medida) {
                return String(medida.codigo || '').toUpperCase() === 'NIU';
            }
        ) || datosFormularioCompra.medidas[0];

        $('#nuevo_idmedida, #gasto_idmedida').val(
            String(medidaPredeterminada.idmedida)
        );
    }

    if (datosFormularioCompra.almacenes.length > 0) {
        $('#nuevo_idalmacen').val(
            String(datosFormularioCompra.almacenes[0].idalmacen)
        );
    }

    if (datosFormularioCompra.categorias_compra.length > 0) {
        $('#gasto_categoria').val(
            String(datosFormularioCompra.categorias_compra[0].idcategoria_compra)
        );
    }

    actualizarSubcategoriasCompra();

    const tiposPago = Array.isArray(
        datosFormularioCompra.tipos_pago
    )
        ? datosFormularioCompra.tipos_pago
        : [];

    let opcionesTipoPago = '<option value="">Seleccione una condición de pago</option>';

    tiposPago.forEach(function (tipoPago) {
        const condicion = String(tipoPago.condicion || '').toUpperCase();
        const descripcion = String(tipoPago.descripcion || '').trim();
        const nombre = String(tipoPago.nombre || '').trim();
        const texto = descripcion ? `${nombre} · ${descripcion}` : nombre;

        opcionesTipoPago +=
            `<option value="${escaparHtmlCompra(tipoPago.idtipopago)}" ` +
            `data-condicion="${escaparHtmlCompra(condicion)}">` +
            `${escaparHtmlCompra(texto)}</option>`;
    });

    $('#condicion_pago').html(opcionesTipoPago);

    if (tiposPago.length > 0 && !$('#condicion_pago').val()) {
        const tipoContado = tiposPago.find(function (tipoPago) {
            return String(tipoPago.condicion || '').toUpperCase() === 'CONTADO';
        }) || tiposPago[0];

        $('#condicion_pago').val(String(tipoContado.idtipopago));
    }

    const formasPago = Array.isArray(
        datosFormularioCompra.formas_pago
    )
        ? datosFormularioCompra.formas_pago
        : [];

    $('#idforma_pago').html(
        opcionesSelectCompra(
            formasPago,
            'idforma_pago',
            function (forma) {
                return String(forma.nombre || '');
            },
            'Seleccione una forma de pago'
        )
    );

    if (formasPago.length > 0 && !$('#idforma_pago').val()) {
        $('#idforma_pago').val(
            String(formasPago[0].idforma_pago)
        );
    }

    actualizarEstadoPagoCompra();
}

function actualizarSubcategoriasCompra() {
    const idcategoria = Number.parseInt(
        $('#nuevo_idcategoria').val(),
        10
    ) || 0;

    const subcategorias = datosFormularioCompra.subcategorias.filter(
        function (subcategoria) {
            return Number.parseInt(subcategoria.idcategoria, 10) === idcategoria;
        }
    );

    if (subcategorias.length === 0) {
        $('#nuevo_idsubcategoria')
            .prop('disabled', true)
            .html('<option value="">Sin subcategoría</option>');
        return;
    }

    $('#nuevo_idsubcategoria')
        .prop('disabled', false)
        .html(
            opcionesSelectCompra(
                subcategorias,
                'idsubcategoria',
                'nombre',
                'Seleccione una subcategoría'
            )
        );
}

function cargarProductosCompra(forzar = false) {
    if (productosCompra.length > 0 && !forzar) {
        renderizarProductosCompra(productosCompra);
        return $.Deferred().resolve().promise();
    }

    $('#listaProductosCompra').html(
        '<div class="text-center text-muted py-5">' +
            '<span class="spinner-border text-success mb-3"></span>' +
            '<div>Cargando productos...</div>' +
        '</div>'
    );

    return $.ajax({
        url: 'Controllers/Buy.php?op=productosCompra',
        method: 'GET',
        dataType: 'json',
        cache: false,
        data: { v: Date.now() }
    })
        .done(function (respuesta) {
            if (!respuesta || respuesta.success !== true) {
                throw new Error(
                    respuesta && respuesta.mensaje
                        ? respuesta.mensaje
                        : 'No se recibieron los productos.'
                );
            }

            productosCompra = Array.isArray(respuesta.productos)
                ? respuesta.productos
                : [];

            renderizarProductosCompra(productosCompra);
        })
        .fail(function (xhr) {
            $('#listaProductosCompra').html(
                '<div class="text-center text-danger py-5">' +
                    '<i class="fas fa-exclamation-circle mb-2" style="font-size:2rem;"></i>' +
                    '<div>No se pudieron cargar los productos.</div>' +
                '</div>'
            );

            console.error('ERROR PRODUCTOS COMPRA:', xhr.responseText);
        });
}

function renderizarProductosCompra(productos) {
    if (!Array.isArray(productos) || productos.length === 0) {
        $('#listaProductosCompra').html(
            '<div class="text-center text-muted py-5">' +
                '<i class="fas fa-box-open mb-2" style="font-size:2rem;"></i>' +
                '<div>No se encontraron productos.</div>' +
            '</div>'
        );
        return;
    }

    let html = '';

    productos.forEach(function (producto) {
        const idarticulo = Number.parseInt(producto.idarticulo, 10) || 0;
        const imagen = String(producto.imagen || '').trim();
        const codigo = String(producto.codigo || '').trim();
        const almacen = String(producto.almacen || 'Sin almacén').trim();
        const stock = Number.parseInt(producto.stock, 10) || 0;
        const precioCompra = numeroCompra(producto.precio_compra);
        const precioVenta = numeroCompra(producto.precio_venta);

        html += `
            <div class="producto-compra-item">
                <div class="producto-compra-thumb">
                    ${imagen
                        ? `<img src="storage/images/products/${escaparHtmlCompra(imagen)}" alt="${escaparHtmlCompra(producto.nombre)}">`
                        : '<i class="fas fa-box text-muted"></i>'}
                </div>

                <div class="producto-compra-meta">
                    <div class="producto-compra-nombre">${escaparHtmlCompra(producto.nombre)}</div>
                    <div class="producto-compra-sub">
                        SKU: ${escaparHtmlCompra(codigo || 'Sin código')} ·
                        Stock: ${stock} ·
                        ${escaparHtmlCompra(almacen)}
                    </div>
                    <div class="producto-compra-sub">
                        Último costo: ${formatearMonedaCompra(precioCompra)} ·
                        Precio venta: ${formatearMonedaCompra(precioVenta)}
                    </div>
                </div>

                <button
                    type="button"
                    class="btn btn-success btn-sm btnSeleccionarProductoCompra"
                    data-idarticulo="${idarticulo}">
                    <i class="fas fa-plus mr-1"></i>
                    Agregar
                </button>
            </div>`;
    });

    $('#listaProductosCompra').html(html);
}

function agregarProductoExistente(idarticulo) {
    const producto = productosCompra.find(function (item) {
        return Number.parseInt(item.idarticulo, 10) === idarticulo;
    });

    if (!producto) {
        alertaCompra('error', 'Producto', 'No se encontró el producto seleccionado.');
        return;
    }

    const indiceExistente = detallesCompra.findIndex(function (detalle) {
        return detalle.tipo_detalle === 'INVENTARIO'
            && detalle.origen === 'EXISTENTE'
            && Number.parseInt(detalle.idarticulo, 10) === idarticulo;
    });

    if (indiceExistente >= 0) {
        detallesCompra[indiceExistente].cantidad = numeroCompra(
            detallesCompra[indiceExistente].cantidad + 1,
            3
        );
    } else {
        detallesCompra.push({
            tipo_detalle: 'INVENTARIO',
            origen: 'EXISTENTE',
            idarticulo: idarticulo,
            descripcion: String(producto.nombre || ''),
            nombre: String(producto.nombre || ''),
            codigo: String(producto.codigo || ''),
            idcategoria: Number.parseInt(producto.idcategoria, 10) || 0,
            idsubcategoria: Number.parseInt(producto.idsubcategoria, 10) || 0,
            idmedida: Number.parseInt(producto.idmedida, 10) || 0,
            idalmacen: Number.parseInt(producto.idalmacen, 10) || 0,
            cantidad: 1,
            precio_compra: numeroCompra(producto.precio_compra),
            precio_venta: producto.precio_venta === null
                ? null
                : numeroCompra(producto.precio_venta),
            importe: numeroCompra(producto.precio_compra)
        });
    }

    renderizarDetallesCompra();
    $('#modalProductoExistente').modal('hide');
}

function agregarProductoNuevoDesdeFormulario(evento) {
    evento.preventDefault();

    if (modoProductoNuevoCompra === 'masivo') {
        agregarProductosMasivosALaCompra();
        return;
    }

    const formulario = document.getElementById('formProductoNuevo');

    if (!formulario || !formulario.checkValidity()) {
        if (formulario) {
            formulario.reportValidity();
        }
        return;
    }

    const nombre = String($('#nuevo_nombre').val() || '').trim();
    const codigo = String($('#nuevo_codigo').val() || '')
        .trim()
        .toUpperCase()
        .replace(/[^A-Z0-9._\-]/g, '');
    const idcategoria = Number.parseInt($('#nuevo_idcategoria').val(), 10) || 0;
    const idsubcategoria = Number.parseInt($('#nuevo_idsubcategoria').val(), 10) || 0;
    const idmedida = Number.parseInt($('#nuevo_idmedida').val(), 10) || 0;
    const idalmacen = Number.parseInt($('#nuevo_idalmacen').val(), 10) || 0;
    const cantidad = Number.parseInt($('#nuevo_cantidad').val(), 10) || 0;
    const precioCompra = numeroCompra($('#nuevo_precio_compra').val());
    const precioVentaTexto = String($('#nuevo_precio_venta').val() || '').trim();
    const precioVenta = precioVentaTexto === ''
        ? null
        : numeroCompra(precioVentaTexto);

    if (idcategoria <= 0 || idmedida <= 0 || idalmacen <= 0) {
        alertaCompra(
            'warning',
            'Producto incompleto',
            'Selecciona la categoría, la unidad y el almacén.'
        );
        return;
    }

    if (cantidad <= 0 || precioCompra <= 0) {
        alertaCompra(
            'warning',
            'Valores inválidos',
            'La cantidad y el costo unitario deben ser mayores que cero.'
        );
        return;
    }

    if (codigo !== '') {
        const codigoDuplicado = productosCompra.some(function (producto) {
            return String(producto.codigo || '').trim().toUpperCase() === codigo;
        }) || detallesCompra.some(function (detalle) {
            return String(detalle.codigo || '').trim().toUpperCase() === codigo;
        });

        if (codigoDuplicado) {
            alertaCompra(
                'warning',
                'Código duplicado',
                'Ya existe un producto o detalle con el código ' + codigo + '.'
            );
            return;
        }
    }

    detallesCompra.push({
        tipo_detalle: 'INVENTARIO',
        origen: 'NUEVO',
        producto_tipo: 'simple',
        grupo: '',
        variante: '',
        codigo_afectacion_igv: '10',
        idarticulo: 0,
        descripcion: nombre,
        nombre: nombre,
        codigo: codigo,
        idcategoria: idcategoria,
        idsubcategoria: idsubcategoria,
        idmedida: idmedida,
        idalmacen: idalmacen,
        cantidad: cantidad,
        precio_compra: precioCompra,
        precio_venta: precioVenta,
        importe: numeroCompra(cantidad * precioCompra)
    });

    renderizarDetallesCompra();
    $('#modalProductoNuevo').modal('hide');
    formulario.reset();
    poblarSelectoresCompra();
    $('#nuevo_cantidad').val('1');
    $('#coincidenciasProductoNuevo').hide().empty();
}


function camposMasivosCompraProducto() {
    return [
        'tipo', 'grupo', 'nombre', 'codigo', 'variante', 'stock',
        'precio_compra', 'precio_venta', 'categoria', 'subcategoria',
        'almacen', 'medida', 'codigo_afectacion_igv'
    ];
}

function normalizarTipoMasivoCompraProducto(valor) {
    const tipo = normalizarTextoCompra(valor);
    if (['variante', 'variacion', 'variable'].includes(tipo)) return 'variante';
    if (['simple', 'producto simple', 'normal'].includes(tipo)) return 'simple';
    return '';
}

function opcionesTipoMasivoCompraProducto(seleccion) {
    const actual = normalizarTipoMasivoCompraProducto(seleccion) || 'simple';
    return `<option value="simple"${actual === 'simple' ? ' selected' : ''}>Simple</option>`
        + `<option value="variante"${actual === 'variante' ? ' selected' : ''}>Variante</option>`;
}

function asegurarCatalogosMasivosCompra() {
    if (catalogosMasivosCompraCargados) {
        return $.Deferred().resolve(catalogosMasivosCompraProducto).promise();
    }
    if (cargandoCatalogosMasivosCompra) return cargandoCatalogosMasivosCompra;

    $('#compraMasivoEstado').html('<span class="spinner-border spinner-border-sm mr-1"></span> Cargando categorías, almacenes, unidades y tributación...');

    cargandoCatalogosMasivosCompra = $.ajax({
        url: 'Controllers/Product.php?op=datosImportacion',
        type: 'GET',
        dataType: 'json',
        cache: false
    }).done(function (respuesta) {
        if (!respuesta || respuesta.success !== true || !respuesta.datos) {
            throw new Error((respuesta && respuesta.mensaje) || 'No se pudieron cargar los catálogos.');
        }

        catalogosMasivosCompraProducto = {
            categorias: Array.isArray(respuesta.datos.categorias) ? respuesta.datos.categorias : [],
            subcategorias: Array.isArray(respuesta.datos.subcategorias) ? respuesta.datos.subcategorias : [],
            almacenes: Array.isArray(respuesta.datos.almacenes) ? respuesta.datos.almacenes : [],
            medidas: Array.isArray(respuesta.datos.medidas) ? respuesta.datos.medidas : [],
            afectaciones_igv: Array.isArray(respuesta.datos.afectaciones_igv) ? respuesta.datos.afectaciones_igv : [],
            tributacion_predeterminada: respuesta.datos.tributacion_predeterminada || { codigo_afectacion_igv: '10' }
        };
        catalogosMasivosCompraCargados = true;
        $('#compraMasivoEstado').html('<i class="fas fa-check-circle text-success mr-1"></i> Catálogos listos. Puedes registrar productos simples y variantes.');
    }).fail(function (xhr) {
        const mensaje = mensajeRespuestaCompra(xhr, 'No se pudieron cargar los catálogos para la importación.');
        $('#compraMasivoEstado').text(mensaje);
        alertaCompra('error', 'No se pudo iniciar la carga masiva', mensaje);
    }).always(function () {
        cargandoCatalogosMasivosCompra = null;
    });

    return cargandoCatalogosMasivosCompra;
}

function opcionesCatalogoMasivoCompra(items, tipo, seleccion) {
    const valor = String(seleccion == null ? '' : seleccion);
    let html = '<option value="">Seleccionar...</option>';

    items.forEach(function (item) {
        const id = tipo === 'categoria' ? item.idcategoria
            : tipo === 'subcategoria' ? item.idsubcategoria
                : tipo === 'almacen' ? item.idalmacen
                    : item.idmedida;
        let etiqueta = `${id} - ${item.nombre || ''}`;
        if (tipo === 'medida' && item.codigo) etiqueta += ` (${item.codigo})`;
        if (tipo === 'subcategoria' && item.categoria) etiqueta += ` · ${item.categoria}`;
        html += `<option value="${escaparHtmlCompra(id)}"${String(id) === valor ? ' selected' : ''}>${escaparHtmlCompra(etiqueta)}</option>`;
    });

    return html;
}

function resolverAfectacionMasivoCompra(valor) {
    const texto = String(valor == null ? '' : valor).trim();
    const predeterminado = String(
        (catalogosMasivosCompraProducto.tributacion_predeterminada || {}).codigo_afectacion_igv || '10'
    );
    if (!texto) return predeterminado;

    const codigoInicial = texto.match(/^\s*([0-9]{2})\s*(?:-|$)/);
    if (codigoInicial) {
        const existe = catalogosMasivosCompraProducto.afectaciones_igv.some(function (item) {
            return String(item.codigo || '') === codigoInicial[1];
        });
        if (existe) return codigoInicial[1];
    }

    const objetivo = normalizarTextoCompra(texto);
    const encontrado = catalogosMasivosCompraProducto.afectaciones_igv.find(function (item) {
        const codigo = String(item.codigo || '');
        const descripcion = String(item.descripcion || '');
        return [codigo, descripcion, `${codigo} - ${descripcion}`, `${codigo} — ${descripcion}`]
            .some(function (candidato) { return normalizarTextoCompra(candidato) === objetivo; });
    });

    return encontrado ? String(encontrado.codigo || '') : '';
}

function opcionesAfectacionMasivoCompra(seleccion) {
    const actual = resolverAfectacionMasivoCompra(seleccion);
    let html = '<option value="">Seleccionar...</option>';
    catalogosMasivosCompraProducto.afectaciones_igv.forEach(function (item) {
        const codigo = String(item.codigo || '');
        const descripcion = String(item.descripcion || '');
        html += `<option value="${escaparHtmlCompra(codigo)}"${codigo === actual ? ' selected' : ''}>${escaparHtmlCompra(`${codigo} - ${descripcion}`)}</option>`;
    });
    return html;
}

function resolverCatalogoMasivoCompra(valor, items, tipo) {
    const texto = String(valor == null ? '' : valor).trim();
    if (!texto) return '';

    const coincidenciaId = texto.match(/^\s*(\d+)\s*(?:-|$)/);
    if (coincidenciaId) {
        const id = coincidenciaId[1];
        const existe = items.some(function (item) {
            const itemId = tipo === 'categoria' ? item.idcategoria
                : tipo === 'subcategoria' ? item.idsubcategoria
                    : tipo === 'almacen' ? item.idalmacen
                        : item.idmedida;
            return String(itemId) === String(id);
        });
        if (existe) return String(id);
    }

    const objetivo = normalizarTextoCompra(texto);
    const encontrado = items.find(function (item) {
        const id = tipo === 'categoria' ? item.idcategoria
            : tipo === 'subcategoria' ? item.idsubcategoria
                : tipo === 'almacen' ? item.idalmacen
                    : item.idmedida;
        const candidatos = [String(item.nombre || ''), `${id} - ${item.nombre || ''}`];
        if (tipo === 'medida') candidatos.push(String(item.codigo || ''), `${item.nombre || ''} (${item.codigo || ''})`);
        if (tipo === 'subcategoria' && item.categoria) candidatos.push(`${item.nombre || ''} · ${item.categoria}`);
        return candidatos.some(function (candidato) { return normalizarTextoCompra(candidato) === objetivo; });
    });

    if (!encontrado) return '';
    return String(
        tipo === 'categoria' ? encontrado.idcategoria
            : tipo === 'subcategoria' ? encontrado.idsubcategoria
                : tipo === 'almacen' ? encontrado.idalmacen
                    : encontrado.idmedida
    );
}

function agregarFilaCompraMasiva(datos = {}, validar = true) {
    const idFila = ++secuenciaFilaMasivaCompra;
    const tipo = normalizarTipoMasivoCompraProducto(datos.tipo ?? 'Simple') || 'simple';
    const categoria = resolverCatalogoMasivoCompra(datos.categoria ?? datos.idcategoria ?? '', catalogosMasivosCompraProducto.categorias, 'categoria');
    const almacen = resolverCatalogoMasivoCompra(datos.almacen ?? datos.idalmacen ?? '', catalogosMasivosCompraProducto.almacenes, 'almacen');
    const medida = resolverCatalogoMasivoCompra(datos.medida ?? datos.idmedida ?? '', catalogosMasivosCompraProducto.medidas, 'medida');
    const afectacion = resolverAfectacionMasivoCompra(datos.codigo_afectacion_igv ?? datos.afectacion_igv ?? '');

    const $fila = $(
        `<tr data-row-id="${idFila}">
            <td><div class="tp-sheet-rownum"><span class="tp-row-state"></span><span class="tp-row-number">1</span></div></td>
            <td><select class="tp-sheet-select" data-field="tipo">${opcionesTipoMasivoCompraProducto(tipo)}</select></td>
            <td><input class="tp-sheet-cell" data-field="grupo" maxlength="50" autocomplete="off" placeholder="POLO-001"></td>
            <td><input class="tp-sheet-cell" data-field="nombre" maxlength="100" autocomplete="off" placeholder="Producto"></td>
            <td><input class="tp-sheet-cell" data-field="codigo" maxlength="100" autocomplete="off" placeholder="SKU"></td>
            <td><input class="tp-sheet-cell" data-field="variante" maxlength="150" autocomplete="off" placeholder="Negro - M"></td>
            <td><input class="tp-sheet-cell is-number" data-field="stock" inputmode="numeric" autocomplete="off" value="0"></td>
            <td><input class="tp-sheet-cell is-number" data-field="precio_compra" inputmode="decimal" autocomplete="off" value="0.00"></td>
            <td><input class="tp-sheet-cell is-number" data-field="precio_venta" inputmode="decimal" autocomplete="off" value="0.00"></td>
            <td><select class="tp-sheet-select" data-field="categoria">${opcionesCatalogoMasivoCompra(catalogosMasivosCompraProducto.categorias, 'categoria', categoria)}</select></td>
            <td><select class="tp-sheet-select" data-field="subcategoria"><option value="">Sin subcategoría</option></select></td>
            <td><select class="tp-sheet-select" data-field="almacen">${opcionesCatalogoMasivoCompra(catalogosMasivosCompraProducto.almacenes, 'almacen', almacen)}</select></td>
            <td><select class="tp-sheet-select" data-field="medida">${opcionesCatalogoMasivoCompra(catalogosMasivosCompraProducto.medidas, 'medida', medida)}</select></td>
            <td><select class="tp-sheet-select" data-field="codigo_afectacion_igv">${opcionesAfectacionMasivoCompra(afectacion)}</select></td>
            <td><div class="tp-sheet-row-actions"><button type="button" class="tp-sheet-remove compra-sheet-remove" title="Eliminar fila"><i class="fas fa-times"></i></button></div></td>
        </tr>`
    );

    $('#compraMasivoBody').append($fila);
    $fila.find('[data-field="grupo"]').val(datos.grupo ?? datos.grupo_sku ?? '');
    $fila.find('[data-field="nombre"]').val(datos.nombre ?? datos.producto ?? '');
    $fila.find('[data-field="codigo"]').val(datos.codigo ?? datos.sku ?? '');
    $fila.find('[data-field="variante"]').val(datos.variante ?? datos.combinacion ?? '');
    $fila.find('[data-field="stock"]').val(datos.stock === undefined || datos.stock === '' ? '0' : datos.stock);
    $fila.find('[data-field="precio_compra"]').val(datos.precio_compra ?? datos.preciocompra ?? '0.00');
    $fila.find('[data-field="precio_venta"]').val(datos.precio_venta ?? datos.precioventa ?? '0.00');
    $fila.find('[data-field="codigo_afectacion_igv"]').val(afectacion);

    const subValor = resolverCatalogoMasivoCompra(datos.subcategoria ?? datos.idsubcategoria ?? '', catalogosMasivosCompraProducto.subcategorias, 'subcategoria');
    actualizarSubcategoriasFilaCompraMasiva($fila, categoria, subValor);
    actualizarTipoFilaCompraMasiva($fila, false);
    renumerarFilasCompraMasiva();
    $('#compraMasivoEmpty').hide();
    if (validar) validarCompraMasiva();
    return $fila;
}

function actualizarTipoFilaCompraMasiva($fila, limpiar = true) {
    const tipo = normalizarTipoMasivoCompraProducto($fila.find('[data-field="tipo"]').val()) || 'simple';
    const esVariante = tipo === 'variante';
    const $grupo = $fila.find('[data-field="grupo"]');
    const $variante = $fila.find('[data-field="variante"]');

    $grupo.prop('disabled', !esVariante).attr('placeholder', esVariante ? 'SKU padre' : 'No aplica');
    $variante.prop('disabled', !esVariante).attr('placeholder', esVariante ? 'Ej. Negro - M' : 'No aplica');

    if (!esVariante && limpiar) {
        $grupo.val('');
        $variante.val('');
    }

    $fila.toggleClass('is-variant-row', esVariante);
}

function actualizarSubcategoriasFilaCompraMasiva($fila, idCategoria, idSeleccionado) {
    const categoria = String(idCategoria || '');
    const filtradas = catalogosMasivosCompraProducto.subcategorias.filter(function (item) {
        return !categoria || String(item.idcategoria) === categoria;
    });
    const $select = $fila.find('[data-field="subcategoria"]');
    $select.html('<option value="">Sin subcategoría</option>' + opcionesCatalogoMasivoCompra(filtradas, 'subcategoria', idSeleccionado).replace('<option value="">Seleccionar...</option>', ''));
    if (idSeleccionado) $select.val(String(idSeleccionado));
}

function renumerarFilasCompraMasiva() {
    $('#compraMasivoBody tr').each(function (indice) {
        $(this).find('.tp-row-number').text(indice + 1);
    });
    $('#compraMasivoEmpty').toggle($('#compraMasivoBody tr').length === 0);
}

function datosFilaCompraMasiva($fila) {
    const datos = { fila_cliente: String($fila.data('row-id') || '') };
    camposMasivosCompraProducto().forEach(function (campo) {
        datos[campo] = String($fila.find(`[data-field="${campo}"]`).val() ?? '').trim();
    });
    return datos;
}

function filaCompraMasivaVacia(datos) {
    return !String(datos.grupo || '').trim()
        && !String(datos.nombre || '').trim()
        && !String(datos.codigo || '').trim()
        && !String(datos.variante || '').trim()
        && (!String(datos.stock || '').trim() || Number(datos.stock) === 0)
        && (!String(datos.precio_compra || '').trim() || Number(datos.precio_compra) === 0)
        && (!String(datos.precio_venta || '').trim() || Number(datos.precio_venta) === 0)
        && !String(datos.categoria || '').trim()
        && !String(datos.subcategoria || '').trim()
        && !String(datos.almacen || '').trim()
        && !String(datos.medida || '').trim();
}

function validarCompraMasiva() {
    const entradas = [];
    const skuMap = {};
    const grupos = {};
    const codigosYaUsados = new Set();

    productosCompra.forEach(function (producto) {
        const codigo = normalizarTextoCompra(producto.codigo || '');
        if (codigo) codigosYaUsados.add(codigo);
    });
    detallesCompra.forEach(function (detalle) {
        const codigo = normalizarTextoCompra(detalle.codigo || '');
        const grupo = normalizarTextoCompra(detalle.grupo || '');
        if (codigo) codigosYaUsados.add(codigo);
        if (grupo) codigosYaUsados.add(grupo);
    });

    $('#compraMasivoBody tr').each(function () {
        const $fila = $(this);
        const datos = datosFilaCompraMasiva($fila);
        datos.tipo = normalizarTipoMasivoCompraProducto(datos.tipo);
        $fila.removeClass('is-valid has-error').removeAttr('title');
        $fila.find('[data-invalid]').removeAttr('data-invalid');

        if (filaCompraMasivaVacia(datos)) return;

        const entrada = { datos: datos, $fila: $fila, mensajes: [] };
        entradas.push(entrada);

        function marcar(campo, mensaje) {
            if (!entrada.mensajes.includes(mensaje)) entrada.mensajes.push(mensaje);
            if (campo) $fila.find(`[data-field="${campo}"]`).attr('data-invalid', '1');
        }
        entrada.marcar = marcar;

        if (!datos.tipo) marcar('tipo', 'Selecciona Simple o Variante');
        if (!datos.nombre) marcar('nombre', 'Falta el nombre');
        if (datos.nombre && datos.nombre.length > 100) marcar('nombre', 'El nombre supera 100 caracteres');
        if (!datos.codigo) marcar('codigo', 'Falta el SKU');

        if (datos.tipo === 'simple' && datos.codigo.length > 50) {
            marcar('codigo', 'El SKU de un producto simple admite máximo 50 caracteres');
        }

        if (datos.tipo === 'variante') {
            if (!datos.grupo) marcar('grupo', 'Falta el Grupo / SKU padre');
            if (datos.grupo.length > 50) marcar('grupo', 'El SKU padre admite máximo 50 caracteres');
            if (!datos.variante) marcar('variante', 'Falta la descripción de la variante');
            if (datos.variante.length > 150) marcar('variante', 'La variante admite máximo 150 caracteres');
            if (datos.codigo.length > 100) marcar('codigo', 'El SKU de variante admite máximo 100 caracteres');
            if (normalizarTextoCompra(datos.grupo) && normalizarTextoCompra(datos.grupo) === normalizarTextoCompra(datos.codigo)) {
                marcar('grupo', 'El SKU padre no puede ser igual al SKU de la variante');
                marcar('codigo', 'El SKU de variante debe ser distinto al SKU padre');
            }
        }

        if (!datos.categoria) marcar('categoria', 'Selecciona una categoría');
        if (!datos.almacen) marcar('almacen', 'Selecciona un almacén');
        if (!datos.medida) marcar('medida', 'Selecciona una unidad');
        if (!datos.codigo_afectacion_igv || !resolverAfectacionMasivoCompra(datos.codigo_afectacion_igv)) {
            marcar('codigo_afectacion_igv', 'Selecciona una afectación IGV válida');
        }

        if (datos.stock === '' || !/^\d+$/.test(datos.stock) || Number(datos.stock) <= 0) marcar('stock', 'La cantidad/stock debe ser mayor que 0');
        if (datos.precio_compra === '' || !Number.isFinite(Number(datos.precio_compra)) || Number(datos.precio_compra) <= 0) marcar('precio_compra', 'El precio de compra debe ser mayor a 0');
        if (datos.precio_venta === '' || !Number.isFinite(Number(datos.precio_venta)) || Number(datos.precio_venta) <= 0) marcar('precio_venta', 'El precio de venta debe ser mayor a 0');

        if (datos.subcategoria) {
            const sub = catalogosMasivosCompraProducto.subcategorias.find(function (item) {
                return String(item.idsubcategoria) === datos.subcategoria;
            });
            if (!sub || String(sub.idcategoria) !== datos.categoria) marcar('subcategoria', 'La subcategoría no pertenece a la categoría');
        }

        const skuKey = normalizarTextoCompra(datos.codigo);
        if (skuKey) {
            if (codigosYaUsados.has(skuKey)) marcar('codigo', 'El SKU ya existe o ya fue agregado a la compra');
            if (!skuMap[skuKey]) skuMap[skuKey] = [];
            skuMap[skuKey].push(entrada);
        }

        if (datos.tipo === 'variante') {
            const grupoKey = normalizarTextoCompra(datos.grupo);
            if (grupoKey) {
                if (codigosYaUsados.has(grupoKey)) marcar('grupo', 'El SKU padre ya existe o ya fue agregado a la compra');
                if (!grupos[grupoKey]) grupos[grupoKey] = [];
                grupos[grupoKey].push(entrada);
            }
        }
    });

    Object.keys(skuMap).forEach(function (skuKey) {
        if (skuMap[skuKey].length < 2) return;
        skuMap[skuKey].forEach(function (entrada) {
            entrada.marcar('codigo', 'SKU repetido dentro de la hoja');
        });
    });

    Object.keys(grupos).forEach(function (grupoKey) {
        const grupo = grupos[grupoKey];
        if (!grupo.length) return;
        const base = grupo[0].datos;

        if (skuMap[grupoKey] && skuMap[grupoKey].length) {
            grupo.forEach(function (entrada) {
                entrada.marcar('grupo', 'El SKU padre coincide con otro SKU de la hoja');
            });
            skuMap[grupoKey].forEach(function (entrada) {
                entrada.marcar('codigo', 'Este SKU coincide con el SKU padre de un producto variable');
            });
        }

        const consistente = grupo.every(function (entrada) {
            const datos = entrada.datos;
            return normalizarTextoCompra(datos.nombre) === normalizarTextoCompra(base.nombre)
                && datos.categoria === base.categoria
                && datos.subcategoria === base.subcategoria
                && datos.almacen === base.almacen
                && datos.medida === base.medida
                && datos.codigo_afectacion_igv === base.codigo_afectacion_igv;
        });

        if (!consistente) {
            grupo.forEach(function (entrada) {
                entrada.marcar(null, 'Todas las variantes del grupo deben usar el mismo producto, categoría, subcategoría, almacén, unidad y afectación IGV');
            });
        }

        const grupoTieneError = grupo.some(function (entrada) { return entrada.mensajes.length > 0; });
        if (grupoTieneError) {
            grupo.forEach(function (entrada) {
                if (!entrada.mensajes.length) entrada.marcar(null, 'El grupo contiene otra variante con errores; corrige el grupo completo');
            });
        }
    });

    let errores = 0;
    let validas = 0;
    let productosValidos = 0;
    const gruposValidosContados = {};
    const filas = [];

    entradas.forEach(function (entrada) {
        const datos = entrada.datos;
        datos._errores = entrada.mensajes.slice();
        filas.push(datos);

        if (entrada.mensajes.length) {
            errores += 1;
            entrada.$fila.addClass('has-error').attr('title', entrada.mensajes.join(' · '));
        } else {
            validas += 1;
            entrada.$fila.addClass('is-valid');
            if (datos.tipo === 'simple') {
                productosValidos += 1;
            } else {
                const grupoKey = normalizarTextoCompra(datos.grupo);
                if (!gruposValidosContados[grupoKey]) {
                    gruposValidosContados[grupoKey] = true;
                    productosValidos += 1;
                }
            }
        }
    });

    $('#compraMasivoTotal').text(filas.length);
    $('#compraMasivoValidas').text(validas);
    $('#compraMasivoErrores').text(errores);
    $('#btnAgregarProductosMasivos')
        .prop('disabled', validas === 0)
        .html(`<i class="fas fa-cart-plus"></i> Agregar ${productosValidos} producto${productosValidos === 1 ? '' : 's'} (${validas} fila${validas === 1 ? '' : 's'})`);

    if (!filas.length) {
        $('#compraMasivoEstado').text('Agrega una fila o pega información desde Excel.');
    } else if (errores) {
        $('#compraMasivoEstado').html(`<strong>${validas}</strong> filas listas · <span class="text-danger"><strong>${errores}</strong> requieren corrección</span>`);
    } else {
        $('#compraMasivoEstado').html(`<span class="text-success"><i class="fas fa-check-circle mr-1"></i><strong>${productosValidos}</strong> producto${productosValidos === 1 ? '' : 's'} listo${productosValidos === 1 ? '' : 's'} para agregar a la compra</span>`);
    }

    return filas;
}

function limpiarCompraMasiva(confirmar = false) {
    const ejecutar = function () {
        $('#compraMasivoBody').empty();
        for (let i = 0; i < 5; i += 1) agregarFilaCompraMasiva({}, false);
        validarCompraMasiva();
    };

    if (!confirmar || !$('#compraMasivoBody tr.is-valid, #compraMasivoBody tr.has-error').length) {
        ejecutar();
        return;
    }

    if (typeof Swal !== 'undefined' && Swal.fire) {
        Swal.fire({
            title: '¿Limpiar la hoja?',
            text: 'Se eliminarán los datos digitados o pegados que todavía no se han agregado a la compra.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, limpiar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#00a46a'
        }).then(function (resultado) { if (resultado.isConfirmed) ejecutar(); });
        return;
    }

    if (window.confirm('¿Limpiar la hoja?')) ejecutar();
}

function cambiarModoProductoNuevoCompra(modo) {
    modoProductoNuevoCompra = modo === 'masivo' ? 'masivo' : 'individual';
    $('[data-producto-modo]').removeClass('is-active');
    $(`[data-producto-modo="${modoProductoNuevoCompra}"]`).addClass('is-active');

    const esMasivo = modoProductoNuevoCompra === 'masivo';
    $('#productoModoIndividual').toggleClass('tw-hidden', esMasivo);
    $('#productoModoMasivo').toggleClass('tw-hidden', !esMasivo);
    $('#btnAgregarProductoIndividual').toggleClass('tw-hidden', esMasivo);

    if (esMasivo) {
        asegurarCatalogosMasivosCompra().then(function () {
            if (!$('#compraMasivoBody tr').length) limpiarCompraMasiva(false);
        });
    }
}

function agregarProductosMasivosALaCompra() {
    const filas = validarCompraMasiva();
    const validas = filas.filter(function (fila) { return !fila._errores || fila._errores.length === 0; });

    if (!validas.length) {
        alertaCompra('warning', 'Sin productos válidos', 'Corrige los campos marcados antes de agregarlos a la compra.');
        return;
    }

    const productos = {};
    validas.forEach(function (fila) {
        const clave = fila.tipo === 'variante'
            ? `v:${normalizarTextoCompra(fila.grupo)}`
            : `s:${normalizarTextoCompra(fila.codigo)}`;
        productos[clave] = true;
    });
    const totalProductos = Object.keys(productos).length;

    validas.forEach(function (fila) {
        const esVariante = fila.tipo === 'variante';
        const nombreDetalle = esVariante
            ? `${fila.nombre} - ${fila.variante}`
            : fila.nombre;

        detallesCompra.push({
            tipo_detalle: 'INVENTARIO',
            origen: 'NUEVO',
            producto_tipo: esVariante ? 'variante' : 'simple',
            grupo: esVariante ? String(fila.grupo || '').trim().toUpperCase() : '',
            variante: esVariante ? String(fila.variante || '').trim() : '',
            idarticulo: 0,
            descripcion: nombreDetalle,
            nombre: String(fila.nombre || '').trim(),
            codigo: String(fila.codigo || '').trim().toUpperCase(),
            idcategoria: Number.parseInt(fila.categoria, 10) || 0,
            idsubcategoria: Number.parseInt(fila.subcategoria, 10) || 0,
            idmedida: Number.parseInt(fila.medida, 10) || 0,
            idalmacen: Number.parseInt(fila.almacen, 10) || 0,
            codigo_afectacion_igv: String(fila.codigo_afectacion_igv || '10'),
            cantidad: Number.parseInt(fila.stock, 10) || 0,
            precio_compra: numeroCompra(fila.precio_compra),
            precio_venta: numeroCompra(fila.precio_venta),
            importe: numeroCompra((Number.parseInt(fila.stock, 10) || 0) * numeroCompra(fila.precio_compra))
        });

        $(`#compraMasivoBody tr[data-row-id="${fila.fila_cliente}"]`).remove();
    });

    renumerarFilasCompraMasiva();
    validarCompraMasiva();
    renderizarDetallesCompra();
    $('#modalProductoNuevo').modal('hide');

    alertaCompra(
        'success',
        'Productos agregados',
        `${totalProductos} producto${totalProductos === 1 ? '' : 's'} (${validas.length} fila${validas.length === 1 ? '' : 's'}) se agregaron a la compra.`
    );
}

function parsearTextoPegadoCompraMasiva(texto) {
    const limpio = String(texto || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').trimEnd();
    if (!limpio) return [];
    return limpio.split('\n').map(function (linea) { return linea.split('\t'); });
}

function pegarMatrizCompraMasiva(matriz, $filaInicio, columnaInicio) {
    if (!Array.isArray(matriz) || !matriz.length) return;
    const campos = camposMasivosCompraProducto();
    let $fila = $filaInicio && $filaInicio.length ? $filaInicio : $('#compraMasivoBody tr').last();
    if (!$fila.length) $fila = agregarFilaCompraMasiva({}, false);

    matriz.forEach(function (columnas, indiceFila) {
        if (indiceFila > 0) {
            let $siguiente = $fila.next('tr');
            if (!$siguiente.length) $siguiente = agregarFilaCompraMasiva({}, false);
            $fila = $siguiente;
        }

        columnas.forEach(function (valor, offset) {
            const campo = campos[columnaInicio + offset];
            if (!campo) return;
            const $control = $fila.find(`[data-field="${campo}"]`);
            if (!$control.length) return;

            if (campo === 'tipo') {
                const tipo = normalizarTipoMasivoCompraProducto(valor);
                $control.val(tipo || '');
                actualizarTipoFilaCompraMasiva($fila, false);
            } else if (campo === 'codigo_afectacion_igv') {
                $control.val(resolverAfectacionMasivoCompra(valor));
            } else if (['categoria', 'subcategoria', 'almacen', 'medida'].includes(campo)) {
                const items = campo === 'categoria' ? catalogosMasivosCompraProducto.categorias
                    : campo === 'subcategoria' ? catalogosMasivosCompraProducto.subcategorias
                        : campo === 'almacen' ? catalogosMasivosCompraProducto.almacenes
                            : catalogosMasivosCompraProducto.medidas;
                const id = resolverCatalogoMasivoCompra(valor, items, campo);
                if (campo === 'categoria') {
                    $control.val(id);
                    actualizarSubcategoriasFilaCompraMasiva($fila, id, '');
                } else if (campo === 'subcategoria') {
                    actualizarSubcategoriasFilaCompraMasiva($fila, String($fila.find('[data-field="categoria"]').val() || ''), id);
                } else {
                    $control.val(id);
                }
            } else {
                $control.val(String(valor == null ? '' : valor).trim());
            }
        });
    });

    renumerarFilasCompraMasiva();
    validarCompraMasiva();
}

function cargarArchivoProductosCompraMasivo(archivo) {
    if (!archivo) return;

    asegurarCatalogosMasivosCompra().then(function () {
        const formData = new FormData();
        formData.append('archivo_productos', archivo);

        $.ajax({
            url: 'Controllers/Product.php?op=previsualizarMasivo',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            beforeSend: function () {
                $('#compraMasivoEstado').html('<span class="spinner-border spinner-border-sm mr-1"></span> Leyendo archivo...');
            }
        }).done(function (respuesta) {
            if (!respuesta || respuesta.success !== true) {
                alertaCompra('error', 'Archivo no válido', (respuesta && respuesta.mensaje) || 'No se pudo leer el archivo.');
                return;
            }

            const filas = Array.isArray(respuesta.filas) ? respuesta.filas : [];
            $('#compraMasivoBody').empty();
            filas.forEach(function (fila) { agregarFilaCompraMasiva(fila, false); });
            if (!filas.length) {
                for (let i = 0; i < 5; i += 1) agregarFilaCompraMasiva({}, false);
            }
            validarCompraMasiva();

            if (typeof Swal !== 'undefined' && Swal.fire) {
                Swal.fire({
                    icon: 'success',
                    title: 'Archivo cargado',
                    text: `${filas.length} fila${filas.length === 1 ? '' : 's'} preparada${filas.length === 1 ? '' : 's'} para revisión.`,
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        }).fail(function (xhr) {
            const mensaje = mensajeRespuestaCompra(xhr, 'No se pudo procesar el archivo.');
            alertaCompra('error', 'Error', mensaje);
        });
    });
}


function agregarGastoServicioDesdeFormulario(evento) {
    evento.preventDefault();

    const formulario = document.getElementById('formGastoServicio');

    if (!formulario || !formulario.checkValidity()) {
        if (formulario) {
            formulario.reportValidity();
        }
        return;
    }

    const descripcion = String($('#gasto_descripcion').val() || '').trim();
    const idcategoriaCompra = Number.parseInt($('#gasto_categoria').val(), 10) || 0;
    const idmedida = Number.parseInt($('#gasto_idmedida').val(), 10) || 0;
    const cantidad = numeroCompra($('#gasto_cantidad').val(), 3);
    const precioCompra = numeroCompra($('#gasto_precio').val());

    if (idcategoriaCompra <= 0) {
        alertaCompra(
            'warning',
            'Categoría obligatoria',
            'Selecciona la categoría del gasto o servicio.'
        );
        return;
    }

    if (cantidad <= 0 || precioCompra <= 0) {
        alertaCompra(
            'warning',
            'Valores inválidos',
            'La cantidad y el costo unitario deben ser mayores que cero.'
        );
        return;
    }

    detallesCompra.push({
        tipo_detalle: 'NO_INVENTARIO',
        origen: 'GASTO',
        idarticulo: 0,
        descripcion: descripcion,
        nombre: descripcion,
        codigo: '',
        idcategoria_compra: idcategoriaCompra,
        idmedida: idmedida,
        cantidad: cantidad,
        precio_compra: precioCompra,
        precio_venta: null,
        importe: numeroCompra(cantidad * precioCompra)
    });

    renderizarDetallesCompra();
    $('#modalGastoServicio').modal('hide');
    formulario.reset();
    poblarSelectoresCompra();
    $('#gasto_cantidad').val('1');
}

function tipoDetalleHtml(detalle) {
    if (detalle.tipo_detalle === 'INVENTARIO') {
        const texto = detalle.origen === 'NUEVO'
            ? (detalle.producto_tipo === 'variante' ? 'Producto variable' : 'Producto nuevo')
            : 'Inventario';

        return '<span class="detalle-tipo detalle-tipo-inventario">'
            + escaparHtmlCompra(texto)
            + '</span>';
    }

    return '<span class="detalle-tipo detalle-tipo-gasto">Gasto / servicio</span>';
}

function renderizarDetallesCompra() {
    if (detallesCompra.length === 0) {
        $('#detallesCompraBody').empty();
        $('#detalleCompraVacio').show();
        $('#btnGuardar').prop('disabled', true);
        sincronizarDetallesCompra();
        calcularTotalesCompra();
        return;
    }

    let html = '';

    detallesCompra.forEach(function (detalle, indice) {
        const esInventario = detalle.tipo_detalle === 'INVENTARIO';
        const descripcionSecundaria = esInventario
            ? (detalle.codigo ? `SKU: ${detalle.codigo}` : 'Sin código asignado')
            : 'No afecta inventario';

        html += `
            <tr data-indice="${indice}">
                <td>${tipoDetalleHtml(detalle)}</td>
                <td>
                    <div class="font-weight-bold text-dark">${escaparHtmlCompra(detalle.descripcion || detalle.nombre)}</div>
                    <small class="text-muted">${escaparHtmlCompra(descripcionSecundaria)}</small>
                </td>
                <td>
                    <input
                        type="number"
                        class="form-control detalle-compra-input"
                        data-indice="${indice}"
                        data-campo="cantidad"
                        min="${esInventario ? '1' : '0.001'}"
                        step="${esInventario ? '1' : '0.001'}"
                        value="${numeroCompra(detalle.cantidad, 3)}">
                </td>
                <td>
                    <input
                        type="number"
                        class="form-control detalle-compra-input"
                        data-indice="${indice}"
                        data-campo="precio_compra"
                        min="0.01"
                        step="0.01"
                        value="${numeroCompra(detalle.precio_compra).toFixed(2)}">
                </td>
                <td>
                    ${esInventario
                        ? `<input
                            type="number"
                            class="form-control detalle-compra-input"
                            data-indice="${indice}"
                            data-campo="precio_venta"
                            min="0"
                            step="0.01"
                            placeholder="Opcional"
                            value="${detalle.precio_venta === null || detalle.precio_venta === ''
                                ? ''
                                : numeroCompra(detalle.precio_venta).toFixed(2)}">`
                        : '<span class="text-muted">—</span>'}
                </td>
                <td>
                    <strong class="importe-detalle-compra" data-indice="${indice}">
                        ${formatearMonedaCompra(detalle.importe)}
                    </strong>
                </td>
                <td class="text-right">
                    <button
                        type="button"
                        class="btn btn-outline-danger btn-sm btnEliminarDetalleCompra"
                        data-indice="${indice}"
                        title="Quitar detalle">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
    });

    $('#detallesCompraBody').html(html);
    $('#detalleCompraVacio').hide();
    $('#btnGuardar').prop('disabled', false);

    sincronizarDetallesCompra();
    calcularTotalesCompra();
}

function actualizarDetalleCompraDesdeInput(elemento) {
    const indice = Number.parseInt($(elemento).attr('data-indice'), 10);
    const campo = String($(elemento).attr('data-campo') || '');

    if (!Number.isInteger(indice) || !detallesCompra[indice]) {
        return;
    }

    const detalle = detallesCompra[indice];
    const texto = String($(elemento).val() || '').trim();

    if (campo === 'precio_venta') {
        detalle.precio_venta = texto === '' ? null : numeroCompra(texto);
    } else if (campo === 'cantidad') {
        detalle.cantidad = numeroCompra(texto, 3);
    } else if (campo === 'precio_compra') {
        detalle.precio_compra = numeroCompra(texto);
    }

    detalle.importe = numeroCompra(
        Number(detalle.cantidad || 0) * Number(detalle.precio_compra || 0)
    );

    $(`.importe-detalle-compra[data-indice="${indice}"]`).text(
        formatearMonedaCompra(detalle.importe)
    );

    sincronizarDetallesCompra();
    calcularTotalesCompra();
}

function eliminarDetalleCompra(indice) {
    if (!Number.isInteger(indice) || !detallesCompra[indice]) {
        return;
    }

    detallesCompra.splice(indice, 1);
    renderizarDetallesCompra();
}

function sincronizarDetallesCompra() {
    $('#detalles_json').val(JSON.stringify(detallesCompra));
}

function calcularTotalesCompra() {
    const total = numeroCompra(
        detallesCompra.reduce(function (acumulado, detalle) {
            return acumulado + Number(detalle.importe || 0);
        }, 0)
    );

    const porcentaje = numeroCompra($('#impuesto').val());
    const subtotal = porcentaje > 0
        ? numeroCompra(total / (1 + porcentaje / 100))
        : total;
    const impuesto = numeroCompra(total - subtotal);

    $('#total').text(formatearMonedaCompra(subtotal));
    $('#most_imp').text(formatearMonedaCompra(impuesto));
    $('#most_total').text(formatearMonedaCompra(total));
    $('#total_compra').val(total.toFixed(2));
    $('#labelImpuestoTotal').text(
        porcentaje > 0 ? `IGV ${porcentaje}%` : 'Impuesto'
    );
}


function obtenerCondicionPagoCompra() {
    return String(
        $('#condicion_pago option:selected').attr('data-condicion') || ''
    ).toUpperCase();
}

function obtenerFormaPagoCompraSeleccionada() {
    const id = Number($('#idforma_pago').val() || 0);

    if (
        !datosFormularioCompra
        || !Array.isArray(datosFormularioCompra.formas_pago)
        || id <= 0
    ) {
        return null;
    }

    return datosFormularioCompra.formas_pago.find(function (forma) {
        return Number(forma.idforma_pago) === id;
    }) || null;
}

function actualizarEstadoPagoCompra() {
    const condicion = obtenerCondicionPagoCompra();
    const esContado = condicion === 'CONTADO';

    $('#grupoFormaPagoCompra').toggle(esContado);
    $('#grupoOperacionCompra').toggle(esContado);

    $('#idforma_pago').prop('required', esContado);

    if (!esContado) {
        $('#numero_operacion')
            .val('')
            .prop('required', false);

        $('#avisoTrazabilidadCompra')
            .removeClass(
                'tw-border-emerald-100 tw-bg-emerald-50/70 tw-text-emerald-800'
            )
            .addClass(
                'tw-border-slate-200 tw-bg-slate-50 tw-text-slate-600'
            )
            .html(
                '<i class="fas fa-clock tw-mt-0.5"></i>' +
                '<span>La compra quedará pendiente de pago. ' +
                'No se generará ningún movimiento de caja.</span>'
            );

        return;
    }

    const forma = obtenerFormaPagoCompraSeleccionada();

    const requiereOperacion =
        forma
        && Number(forma.requiere_operacion || 0) === 1;

    const requiereCaja =
        forma
        && Number(forma.requiere_caja_abierta || 0) === 1;

    $('#numero_operacion').prop(
        'required',
        Boolean(requiereOperacion)
    );

    $('#ayudaOperacionCompra').text(
        requiereOperacion
            ? 'Obligatorio para esta forma de pago.'
            : 'Opcional para esta forma de pago.'
    );

    if (requiereCaja) {
        $('#ayudaFormaPagoCompra').text(
            'Efectivo: se validará caja seleccionada + apertura ABIERTA.'
        );

        $('#avisoTrazabilidadCompra')
            .removeClass(
                'tw-border-slate-200 tw-bg-slate-50 tw-text-slate-600'
            )
            .addClass(
                'tw-border-emerald-100 tw-bg-emerald-50/70 tw-text-emerald-800'
            )
            .html(
                '<i class="fas fa-cash-register tw-mt-0.5"></i>' +
                '<span>Este pago saldrá de la caja activa y quedará ligado ' +
                'a su apertura para el arqueo.</span>'
            );

        return;
    }

    $('#ayudaFormaPagoCompra').text(
        'El pago se registrará en su cuenta financiera y no reducirá el efectivo físico.'
    );

    $('#avisoTrazabilidadCompra')
        .removeClass(
            'tw-border-emerald-100 tw-bg-emerald-50/70 tw-text-emerald-800'
        )
        .addClass(
            'tw-border-slate-200 tw-bg-slate-50 tw-text-slate-600'
        )
        .html(
            '<i class="fas fa-university tw-mt-0.5"></i>' +
            '<span>Este pago afectará la cuenta financiera configurada, ' +
            'no el efectivo de la caja.</span>'
        );
}

function validarCompraAntesDeGuardar() {
    const formulario = document.getElementById('formulario');

    if (!formulario || !formulario.checkValidity()) {
        if (formulario) {
            formulario.reportValidity();
        }
        return false;
    }

    if (detallesCompra.length === 0) {
        alertaCompra(
            'warning',
            'Compra vacía',
            'Agrega por lo menos un producto, gasto o servicio.'
        );
        return false;
    }

    const condicionPago = obtenerCondicionPagoCompra();

    if (!['CONTADO', 'CREDITO'].includes(condicionPago)) {
        alertaCompra(
            'warning',
            'Condición de pago',
            'Seleccione un tipo de pago válido.'
        );
        return false;
    }

    if (condicionPago === 'CONTADO') {
        const forma = obtenerFormaPagoCompraSeleccionada();

        if (!forma) {
            alertaCompra(
                'warning',
                'Forma de pago',
                'Seleccione cómo se pagará la compra.'
            );
            return false;
        }

        if (
            Number(forma.requiere_operacion || 0) === 1
            && String($('#numero_operacion').val() || '').trim() === ''
        ) {
            alertaCompra(
                'warning',
                'Número de operación',
                'Ingrese el número de operación de la forma de pago seleccionada.'
            );
            return false;
        }
    }

    for (let indice = 0; indice < detallesCompra.length; indice += 1) {
        const detalle = detallesCompra[indice];
        const cantidad = Number(detalle.cantidad || 0);
        const precio = Number(detalle.precio_compra || 0);

        if (cantidad <= 0 || precio <= 0) {
            alertaCompra(
                'warning',
                'Detalle incompleto',
                `Revisa la cantidad y el costo del detalle ${indice + 1}.`
            );
            return false;
        }

        if (
            detalle.tipo_detalle === 'INVENTARIO'
            && Math.abs(cantidad - Math.round(cantidad)) > 0.0001
        ) {
            alertaCompra(
                'warning',
                'Cantidad no válida',
                `Los productos inventariables deben usar cantidades enteras (detalle ${indice + 1}).`
            );
            return false;
        }
    }

    sincronizarDetallesCompra();
    return true;
}

function guardaryeditar(evento) {
    evento.preventDefault();

    if (guardandoCompra || !validarCompraAntesDeGuardar()) {
        return;
    }

    const formulario = document.getElementById('formulario');
    const datos = new FormData(formulario);
    const $boton = $('#btnGuardar');
    const textoOriginal = $boton.html();

    guardandoCompra = true;
    $boton
        .prop('disabled', true)
        .html(
            '<span class="spinner-border spinner-border-sm mr-2"></span>' +
            'Guardando...'
        );

    $.ajax({
        url: 'Controllers/Buy.php?op=guardaryeditar',
        method: 'POST',
        data: datos,
        processData: false,
        contentType: false,
        dataType: 'json',
        cache: false
    })
        .done(function (respuesta) {
            if (!respuesta || respuesta.success !== true) {
                alertaCompra(
                    'error',
                    'No se registró la compra',
                    respuesta && respuesta.mensaje
                        ? respuesta.mensaje
                        : 'El servidor no confirmó el registro.'
                );
                return;
            }

            productosCompra = [];

            alertaCompra(
                'success',
                'Compra registrada',
                respuesta.condicion_pago === 'CONTADO'
                    ? `Compra #${respuesta.idingreso} registrada y pagada por ${respuesta.forma_pago || 'la forma seleccionada'}.`
                    : `Compra #${respuesta.idingreso} registrada a crédito, pendiente de pago.`
            ).then(function () {
                mostrarform(false);

                if (tablaCompras) {
                    tablaCompras.ajax.reload(null, false);
                }
            });
        })
        .fail(function (xhr) {
            alertaCompra(
                'error',
                'No se registró la compra',
                mensajeRespuestaCompra(
                    xhr,
                    'Ocurrió un error al guardar. No se aplicó ningún cambio parcial.'
                )
            );
        })
        .always(function () {
            guardandoCompra = false;
            $boton.html(textoOriginal);

            if (detallesCompra.length > 0) {
                $boton.prop('disabled', false);
            }
        });
}


let filtroFechaComprasRegistrado = false;

const ESTADO_SELECTOR_FECHA_COMPRA = {
    vista: null,
    objetivo: null
};

function formatearFechaInputCompra(fecha) {
    const anio = fecha.getFullYear();
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');

    return `${anio}-${mes}-${dia}`;
}

function fechaISOCompraValida(valor) {
    return /^\d{4}-\d{2}-\d{2}$/.test(
        String(valor || '').trim()
    );
}

function fechaISOCompraADateLocal(valor) {
    if (!fechaISOCompraValida(valor)) {
        return null;
    }

    const partes = String(valor)
        .split('-')
        .map(Number);

    const fecha = new Date(
        partes[0],
        partes[1] - 1,
        partes[2]
    );

    if (
        fecha.getFullYear() !== partes[0]
        || fecha.getMonth() !== partes[1] - 1
        || fecha.getDate() !== partes[2]
    ) {
        return null;
    }

    return fecha;
}

function formatearFechaSelectorCompra(
    valor,
    modo = 'corto'
) {
    const fecha = fechaISOCompraADateLocal(valor);

    if (!fecha) {
        return '';
    }

    const opciones = modo === 'largo'
        ? {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        }
        : {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        };

    return new Intl.DateTimeFormat(
        'es-PE',
        opciones
    )
        .format(fecha)
        .replace(/\./g, '');
}

function obtenerHoyCompraISO() {
    return formatearFechaInputCompra(new Date());
}

function sincronizarFechasFiltroCompraVisual() {
    const configuraciones = [
        {
            input: '#compraFechaDesde',
            texto: '#compraFechaDesdeTexto'
        },
        {
            input: '#compraFechaHasta',
            texto: '#compraFechaHastaTexto'
        }
    ];

    configuraciones.forEach(function (configuracion) {
        const valor = String(
            $(configuracion.input).val() || ''
        ).trim();

        const $texto = $(configuracion.texto);

        if (!$texto.length) {
            return;
        }

        if (!fechaISOCompraValida(valor)) {
            $texto
                .text('Seleccionar fecha')
                .addClass('is-empty');

            return;
        }

        $texto
            .text(
                formatearFechaSelectorCompra(
                    valor,
                    'corto'
                )
            )
            .removeClass('is-empty');
    });

    if (
        ESTADO_SELECTOR_FECHA_COMPRA.objetivo
        && $('#modalCompraFecha').hasClass('show')
    ) {
        const valorActivo = String(
            $(ESTADO_SELECTOR_FECHA_COMPRA.objetivo)
                .val()
            || ''
        ).trim();

        $('#compraFechaSeleccionResumen').text(
            fechaISOCompraValida(valorActivo)
                ? formatearFechaSelectorCompra(
                    valorActivo,
                    'largo'
                )
                : 'Sin fecha seleccionada'
        );
    }
}

function renderizarCalendarioFiltroCompra() {
    const contenedor =
        document.getElementById(
            'compraFechaDias'
        );

    if (
        !contenedor
        || !ESTADO_SELECTOR_FECHA_COMPRA.objetivo
    ) {
        return;
    }

    const hoyISO = obtenerHoyCompraISO();
    const fechaMaxima =
        fechaISOCompraADateLocal(hoyISO)
        || new Date();

    const valorSeleccionado = String(
        $(ESTADO_SELECTOR_FECHA_COMPRA.objetivo)
            .val()
        || ''
    ).trim();

    const fechaSeleccionada =
        fechaISOCompraADateLocal(
            valorSeleccionado
        )
        || fechaMaxima;

    if (
        !(
            ESTADO_SELECTOR_FECHA_COMPRA.vista
            instanceof Date
        )
    ) {
        ESTADO_SELECTOR_FECHA_COMPRA.vista =
            new Date(
                fechaSeleccionada.getFullYear(),
                fechaSeleccionada.getMonth(),
                1
            );
    }

    const limiteMes = new Date(
        fechaMaxima.getFullYear(),
        fechaMaxima.getMonth(),
        1
    );

    if (
        ESTADO_SELECTOR_FECHA_COMPRA.vista
        > limiteMes
    ) {
        ESTADO_SELECTOR_FECHA_COMPRA.vista =
            new Date(limiteMes);
    }

    const anio =
        ESTADO_SELECTOR_FECHA_COMPRA.vista
            .getFullYear();

    const mes =
        ESTADO_SELECTOR_FECHA_COMPRA.vista
            .getMonth();

    const primerDia =
        new Date(anio, mes, 1);

    const ultimoDiaMes =
        new Date(anio, mes + 1, 0)
            .getDate();

    const desplazamiento =
        (primerDia.getDay() + 6) % 7;

    $('#compraFechaMesTitulo').text(
        new Intl.DateTimeFormat(
            'es-PE',
            {
                month: 'long',
                year: 'numeric'
            }
        ).format(primerDia)
    );

    const fragmento =
        document.createDocumentFragment();

    for (
        let celda = 0;
        celda < 42;
        celda += 1
    ) {
        const numeroDia =
            celda - desplazamiento + 1;

        if (
            numeroDia < 1
            || numeroDia > ultimoDiaMes
        ) {
            const vacio =
                document.createElement('span');

            vacio.className =
                'compra-calendario-dia is-empty';

            vacio.setAttribute(
                'aria-hidden',
                'true'
            );

            fragmento.appendChild(vacio);
            continue;
        }

        const fechaCelda =
            new Date(
                anio,
                mes,
                numeroDia
            );

        const fechaISO =
            formatearFechaInputCompra(
                fechaCelda
            );

        const esFutura =
            fechaCelda > fechaMaxima;

        const esSeleccionada =
            fechaISO === valorSeleccionado;

        const esHoy =
            fechaISO === hoyISO;

        const boton =
            document.createElement('button');

        boton.type = 'button';
        boton.className =
            'compra-calendario-dia';

        boton.textContent =
            String(numeroDia);

        boton.dataset.fecha =
            fechaISO;

        boton.setAttribute(
            'role',
            'gridcell'
        );

        boton.setAttribute(
            'aria-label',
            formatearFechaSelectorCompra(
                fechaISO,
                'largo'
            )
        );

        if (esSeleccionada) {
            boton.classList.add(
                'is-selected'
            );

            boton.setAttribute(
                'aria-selected',
                'true'
            );
        }

        if (esHoy) {
            boton.classList.add(
                'is-today'
            );
        }

        if (esFutura) {
            boton.classList.add(
                'is-disabled'
            );

            boton.disabled = true;

            boton.setAttribute(
                'aria-disabled',
                'true'
            );
        }

        fragmento.appendChild(boton);
    }

    contenedor.replaceChildren(fragmento);

    const siguiente =
        document.getElementById(
            'btnCompraFechaSiguiente'
        );

    if (siguiente) {
        const mesSiguiente =
            new Date(
                anio,
                mes + 1,
                1
            );

        const deshabilitar =
            mesSiguiente > limiteMes;

        siguiente.disabled =
            deshabilitar;

        siguiente.setAttribute(
            'aria-disabled',
            deshabilitar
                ? 'true'
                : 'false'
        );
    }

    $('#compraFechaSeleccionResumen').text(
        fechaISOCompraValida(
            valorSeleccionado
        )
            ? formatearFechaSelectorCompra(
                valorSeleccionado,
                'largo'
            )
            : 'Sin fecha seleccionada'
    );
}

function abrirSelectorFechaCompra(objetivo) {
    if (
        objetivo !== '#compraFechaDesde'
        && objetivo !== '#compraFechaHasta'
    ) {
        return;
    }

    ESTADO_SELECTOR_FECHA_COMPRA.objetivo =
        objetivo;

    const esDesde =
        objetivo === '#compraFechaDesde';

    const valor = String(
        $(objetivo).val() || ''
    ).trim();

    const fecha =
        fechaISOCompraADateLocal(valor)
        || fechaISOCompraADateLocal(
            obtenerHoyCompraISO()
        )
        || new Date();

    ESTADO_SELECTOR_FECHA_COMPRA.vista =
        new Date(
            fecha.getFullYear(),
            fecha.getMonth(),
            1
        );

    $('#compraFechaModalTitulo').text(
        esDesde
            ? 'Fecha desde'
            : 'Fecha hasta'
    );

    $('#compraFechaModalAyuda').text(
        esDesde
            ? 'Selecciona desde qué día deseas consultar las compras'
            : 'Selecciona hasta qué día deseas consultar las compras'
    );

    renderizarCalendarioFiltroCompra();

    $('#modalCompraFecha').modal('show');
}

function seleccionarFechaFiltroCompra(
    fecha
) {
    if (
        !fechaISOCompraValida(fecha)
        || !ESTADO_SELECTOR_FECHA_COMPRA.objetivo
    ) {
        return;
    }

    const objetivo =
        ESTADO_SELECTOR_FECHA_COMPRA.objetivo;

    const $objetivo =
        $(objetivo);

    const esDesde =
        objetivo === '#compraFechaDesde';

    if (esDesde) {
        const hasta = String(
            $('#compraFechaHasta').val()
            || ''
        ).trim();

        if (
            fechaISOCompraValida(hasta)
            && fecha > hasta
        ) {
            $('#compraFechaHasta')
                .val(fecha);
        }
    } else {
        const desde = String(
            $('#compraFechaDesde').val()
            || ''
        ).trim();

        if (
            fechaISOCompraValida(desde)
            && fecha < desde
        ) {
            $('#compraFechaDesde')
                .val(fecha);
        }
    }

    $objetivo
        .val(fecha)
        .trigger('change');

    sincronizarFechasFiltroCompraVisual();

    $('#modalCompraFecha').modal('hide');
}

function inicializarSelectorFechasCompra() {
    if (
        !$('#btnCompraFechaDesde').length
        || !$('#btnCompraFechaHasta').length
        || !$('#modalCompraFecha').length
    ) {
        return;
    }

    sincronizarFechasFiltroCompraVisual();

    $(document)
        .off(
            'click.compraFechaDesde',
            '#btnCompraFechaDesde'
        )
        .on(
            'click.compraFechaDesde',
            '#btnCompraFechaDesde',
            function () {
                abrirSelectorFechaCompra(
                    '#compraFechaDesde'
                );
            }
        )
        .off(
            'click.compraFechaHasta',
            '#btnCompraFechaHasta'
        )
        .on(
            'click.compraFechaHasta',
            '#btnCompraFechaHasta',
            function () {
                abrirSelectorFechaCompra(
                    '#compraFechaHasta'
                );
            }
        )
        .off(
            'change.compraFechaVisual',
            '#compraFechaDesde, #compraFechaHasta'
        )
        .on(
            'change.compraFechaVisual',
            '#compraFechaDesde, #compraFechaHasta',
            function () {
                sincronizarFechasFiltroCompraVisual();
            }
        )
        .off(
            'click.compraFechaDia',
            '#compraFechaDias [data-fecha]'
        )
        .on(
            'click.compraFechaDia',
            '#compraFechaDias [data-fecha]',
            function () {
                if (this.disabled) {
                    return;
                }

                seleccionarFechaFiltroCompra(
                    String(
                        this.dataset.fecha
                        || ''
                    ).trim()
                );
            }
        )
        .off(
            'click.compraFechaAnterior',
            '#btnCompraFechaAnterior'
        )
        .on(
            'click.compraFechaAnterior',
            '#btnCompraFechaAnterior',
            function () {
                const vista =
                    ESTADO_SELECTOR_FECHA_COMPRA.vista;

                if (!(vista instanceof Date)) {
                    return;
                }

                ESTADO_SELECTOR_FECHA_COMPRA.vista =
                    new Date(
                        vista.getFullYear(),
                        vista.getMonth() - 1,
                        1
                    );

                renderizarCalendarioFiltroCompra();
            }
        )
        .off(
            'click.compraFechaSiguiente',
            '#btnCompraFechaSiguiente'
        )
        .on(
            'click.compraFechaSiguiente',
            '#btnCompraFechaSiguiente',
            function () {
                const vista =
                    ESTADO_SELECTOR_FECHA_COMPRA.vista;

                if (
                    this.disabled
                    || !(vista instanceof Date)
                ) {
                    return;
                }

                ESTADO_SELECTOR_FECHA_COMPRA.vista =
                    new Date(
                        vista.getFullYear(),
                        vista.getMonth() + 1,
                        1
                    );

                renderizarCalendarioFiltroCompra();
            }
        )
        .off(
            'click.compraFechaHoy',
            '#btnCompraFechaHoy'
        )
        .on(
            'click.compraFechaHoy',
            '#btnCompraFechaHoy',
            function () {
                seleccionarFechaFiltroCompra(
                    obtenerHoyCompraISO()
                );
            }
        );

    $('#modalCompraFecha')
        .off('shown.bs.modal.compraFecha')
        .on(
            'shown.bs.modal.compraFecha',
            function () {
                const seleccionado =
                    this.querySelector(
                        '.compra-calendario-dia.is-selected:not(:disabled)'
                    );

                if (seleccionado) {
                    seleccionado.focus({
                        preventScroll: true
                    });
                }
            }
        );
}

function parsearFechaCompra(valor) {
    const texto = String(valor || '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    if (texto === '') {
        return null;
    }

    let coincidencia = texto.match(
        /\b(\d{4})-(\d{2})-(\d{2})\b/
    );

    if (coincidencia) {
        return new Date(
            Number.parseInt(coincidencia[1], 10),
            Number.parseInt(coincidencia[2], 10) - 1,
            Number.parseInt(coincidencia[3], 10)
        );
    }

    coincidencia = texto.match(
        /\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/
    );

    if (coincidencia) {
        return new Date(
            Number.parseInt(coincidencia[3], 10),
            Number.parseInt(coincidencia[2], 10) - 1,
            Number.parseInt(coincidencia[1], 10)
        );
    }

    return null;
}

function formatearFechaVisibleCompra(valor) {
    const fecha = parsearFechaCompra(valor);

    if (!fecha || Number.isNaN(fecha.getTime())) {
        return '';
    }

    return [
        String(fecha.getDate()).padStart(2, '0'),
        String(fecha.getMonth() + 1).padStart(2, '0'),
        fecha.getFullYear()
    ].join('/');
}

function obtenerFechaInputCompra(selector) {
    const valor = String(
        $(selector).val() || ''
    ).trim();

    if (!/^\d{4}-\d{2}-\d{2}$/.test(valor)) {
        return null;
    }

    const partes = valor.split('-');

    const fecha = new Date(
        Number.parseInt(partes[0], 10),
        Number.parseInt(partes[1], 10) - 1,
        Number.parseInt(partes[2], 10)
    );

    return Number.isNaN(fecha.getTime())
        ? null
        : fecha;
}

function registrarFiltroFechaCompras() {
    if (
        filtroFechaComprasRegistrado
        || !$.fn.dataTable
        || !Array.isArray($.fn.dataTable.ext.search)
    ) {
        return;
    }

    $.fn.dataTable.ext.search.push(
        function (settings, data) {
            if (
                !settings
                || !settings.nTable
                || settings.nTable.id !== 'tbllistado'
            ) {
                return true;
            }

            const desde =
                obtenerFechaInputCompra(
                    '#compraFechaDesde'
                );

            const hasta =
                obtenerFechaInputCompra(
                    '#compraFechaHasta'
                );

            if (!desde && !hasta) {
                return true;
            }

            const fechaRegistro =
                parsearFechaCompra(
                    data[1] || ''
                );

            if (
                !fechaRegistro
                || Number.isNaN(fechaRegistro.getTime())
            ) {
                return false;
            }

            fechaRegistro.setHours(0, 0, 0, 0);

            if (desde) {
                desde.setHours(0, 0, 0, 0);

                if (
                    fechaRegistro.getTime()
                    < desde.getTime()
                ) {
                    return false;
                }
            }

            if (hasta) {
                hasta.setHours(23, 59, 59, 999);

                if (
                    fechaRegistro.getTime()
                    > hasta.getTime()
                ) {
                    return false;
                }
            }

            return true;
        }
    );

    filtroFechaComprasRegistrado = true;
}

function actualizarResumenFiltroCompras() {
    const periodo = String(
        $('#compraFiltroPeriodo').val()
        || 'mes'
    );

    const desde = String(
        $('#compraFechaDesde').val()
        || ''
    );

    const hasta = String(
        $('#compraFechaHasta').val()
        || ''
    );

    let texto = 'Todo el historial';

    if (periodo === 'hoy') {
        texto = 'Compras de hoy';
    } else if (periodo === '7dias') {
        texto = 'Compras de los últimos 7 días';
    } else if (periodo === 'mes') {
        texto = 'Compras del mes actual';
    } else if (
        desde !== ''
        || hasta !== ''
    ) {
        const desdeVisible =
            formatearFechaVisibleCompra(desde);

        const hastaVisible =
            formatearFechaVisibleCompra(hasta);

        if (
            desdeVisible !== ''
            && hastaVisible !== ''
        ) {
            texto =
                `Del ${desdeVisible} al ${hastaVisible}`;
        } else if (desdeVisible !== '') {
            texto =
                `Desde ${desdeVisible}`;
        } else if (hastaVisible !== '') {
            texto =
                `Hasta ${hastaVisible}`;
        }
    }

    $('#compraPeriodoResumen span').text(texto);
}

function aplicarPeriodoFiltroCompras(
    periodo,
    redibujar = true
) {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);

    let desde = null;
    let hasta = null;

    switch (periodo) {
        case 'hoy':
            desde = new Date(hoy);
            hasta = new Date(hoy);
            break;

        case '7dias':
            desde = new Date(hoy);
            desde.setDate(desde.getDate() - 6);
            hasta = new Date(hoy);
            break;

        case 'mes':
            desde = new Date(
                hoy.getFullYear(),
                hoy.getMonth(),
                1
            );
            hasta = new Date(hoy);
            break;

        case 'todo':
            break;

        case 'personalizado':
            actualizarResumenFiltroCompras();

            if (
                redibujar
                && tablaCompras
            ) {
                tablaCompras.draw();
            }

            return;

        default:
            periodo = 'mes';
            $('#compraFiltroPeriodo').val('mes');

            desde = new Date(
                hoy.getFullYear(),
                hoy.getMonth(),
                1
            );
            hasta = new Date(hoy);
            break;
    }

    $('#compraFechaDesde').val(
        desde
            ? formatearFechaInputCompra(desde)
            : ''
    );

    $('#compraFechaHasta').val(
        hasta
            ? formatearFechaInputCompra(hasta)
            : ''
    );

    sincronizarFechasFiltroCompraVisual();
    actualizarResumenFiltroCompras();

    if (
        redibujar
        && tablaCompras
    ) {
        tablaCompras.draw();
    }
}

function actualizarCantidadFiltradaCompras() {
    if (!tablaCompras) {
        $('#compraResultadoCount').text('0 registros');
        return;
    }

    const cantidad =
        tablaCompras
            .rows({
                search: 'applied'
            })
            .count();

    $('#compraResultadoCount').text(
        cantidad === 1
            ? '1 registro'
            : `${cantidad} registros`
    );
}

function registrarEventosFiltroCompras() {
    $('#compraFiltroPeriodo').on(
        'change',
        function () {
            aplicarPeriodoFiltroCompras(
                String($(this).val() || 'mes')
            );
        }
    );

    $('#compraFechaDesde, #compraFechaHasta').on(
        'change',
        function () {
            $('#compraFiltroPeriodo').val(
                'personalizado'
            );

            actualizarResumenFiltroCompras();

            if (tablaCompras) {
                tablaCompras.draw();
            }
        }
    );

    $('#compraBuscar').on(
        'input',
        function () {
            if (!tablaCompras) {
                return;
            }

            tablaCompras
                .search(
                    String($(this).val() || '')
                )
                .draw();
        }
    );

    $('#btnLimpiarFiltroCompras').on(
        'click',
        function () {
            $('#compraBuscar').val('');

            if (tablaCompras) {
                tablaCompras.search('');
            }

            $('#compraFiltroPeriodo').val('mes');

            aplicarPeriodoFiltroCompras('mes');
        }
    );
}

function exportarCompras(formato) {
    if (!tablaCompras) {
        alertaCompra(
            'info',
            'Reporte no disponible',
            'Espera a que termine de cargar el listado de compras.'
        );
        return;
    }

    const formatoNormalizado = String(formato || '').toLowerCase();
    const selector = formatoNormalizado === 'pdf'
        ? '.buttons-pdf'
        : formatoNormalizado === 'excel'
            ? '.buttons-excel'
            : '';

    if (selector === '') {
        return;
    }

    try {
        const boton = tablaCompras.button(selector);

        if (!boton || !boton.node || boton.node().length === 0) {
            throw new Error('No se encontró el botón de exportación.');
        }

        boton.trigger();
    } catch (error) {
        console.error('ERROR EXPORTAR COMPRAS:', error);
        alertaCompra(
            'error',
            'No se pudo exportar',
            'No fue posible generar el reporte. Intenta nuevamente.'
        );
    }
}

function listar() {
    if ($.fn.DataTable.isDataTable('#tbllistado')) {
        $('#tbllistado').DataTable().destroy();
    }

    tablaCompras = $('#tbllistado').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        responsive: false,

        dom:
            'Brt' +
            '<"row align-items-center mt-3"' +
                '<"col-sm-12 col-md-6"i>' +
                '<"col-sm-12 col-md-6"p>' +
            '>',
        buttons: [
            {
                extend: 'excelHtml5',
                text:
                    '<i class="fas fa-file-excel mr-1"></i> Excel',
                titleAttr:
                    'Exportar compras filtradas a Excel',
                title:
                    'Reporte de compras',
                sheetName:
                    'Compras',
                exportOptions: {
                    columns: [1, 2, 3, 4, 5, 6, 7, 8],
                    modifier: {
                        search: 'applied',
                        order: 'applied'
                    }
                }
            },
            {
                extend: 'pdfHtml5',
                text:
                    '<i class="fas fa-file-pdf mr-1"></i> PDF',
                titleAttr:
                    'Exportar compras filtradas a PDF',
                title:
                    'Reporte de compras',
                pageSize:
                    'A4',
                orientation:
                    'landscape',
                exportOptions: {
                    columns: [1, 2, 3, 4, 5, 6, 7, 8],
                    modifier: {
                        search: 'applied',
                        order: 'applied'
                    }
                }
            }
        ],
        ajax: {
            url:
                'Controllers/Buy.php?op=listar',
            type:
                'GET',
            dataType:
                'json',
            cache:
                false,
            data: function () {
                return {
                    v: Date.now()
                };
            },
            error: function (xhr) {
                console.error(
                    'ERROR LISTAR COMPRAS:',
                    xhr.responseText
                );
            }
        },
        pageLength: 15,
        order: [[1, 'desc']],
        columnDefs: [
            {
                targets: [0],
                orderable: false,
                searchable: false
            },
            {
                targets: [1],
                render: function (
                    data,
                    type
                ) {
                    if (
                        type !== 'sort'
                        && type !== 'type'
                    ) {
                        return data;
                    }

                    const fecha =
                        parsearFechaCompra(data);

                    return (
                        fecha
                        && !Number.isNaN(
                            fecha.getTime()
                        )
                    )
                        ? fecha.getTime()
                        : 0;
                }
            },
            {
                targets: [7],
                className: 'text-right'
            }
        ],
        initComplete: function () {
            const api =
                this.api();

            api
                .buttons()
                .container()
                .addClass('compra-export-engine')
                .attr('aria-hidden', 'true');

            actualizarCantidadFiltradaCompras();
        },
        drawCallback: function () {
            actualizarCantidadFiltradaCompras();
        },
        language: {
            emptyTable:
                'No hay compras para el periodo seleccionado',
            processing:
                'Cargando compras...',
            info:
                'Mostrando _START_ a _END_ de _TOTAL_ compras',
            infoEmpty:
                'Sin compras para mostrar',
            infoFiltered:
                '',
            zeroRecords:
                'No se encontraron compras con estos filtros',
            paginate: {
                first:
                    'Primero',
                last:
                    'Último',
                next:
                    'Siguiente',
                previous:
                    'Anterior'
            }
        }
    });
}

function mostrarCompra(idingreso) {
    const id = Number.parseInt(idingreso, 10) || 0;

    if (id <= 0) {
        return;
    }

    $('#detallesm').html(
        '<tr><td colspan="5" class="text-center text-muted py-4">Cargando...</td></tr>'
    );
    $('#getCodeModal').modal('show');

    const solicitudCompra = $.ajax({
        url: 'Controllers/Buy.php?op=mostrar',
        method: 'POST',
        dataType: 'json',
        data: { idingreso: id }
    });

    const solicitudDetalle = $.ajax({
        url: 'Controllers/Buy.php?op=listarDetalle',
        method: 'GET',
        dataType: 'json',
        data: { id: id }
    });

    $.when(solicitudCompra, solicitudDetalle)
        .done(function (respuestaCompraAjax, respuestaDetalleAjax) {
            const respuestaCompra = respuestaCompraAjax[0];
            const respuestaDetalle = respuestaDetalleAjax[0];

            if (!respuestaCompra.success || !respuestaCompra.compra) {
                throw new Error(
                    respuestaCompra.mensaje || 'No se pudo cargar la compra.'
                );
            }

            const compra = respuestaCompra.compra;
            const detalles = respuestaDetalle.success
                && Array.isArray(respuestaDetalle.detalles)
                ? respuestaDetalle.detalles
                : [];

            const documento = [
                compra.tipo_comprobante,
                [compra.serie_comprobante, compra.num_comprobante]
                    .filter(Boolean)
                    .join('-')
            ].filter(Boolean).join(' · ');

            $('#vistaCompraDocumento').text(documento);
            $('#vistaCompraProveedor').text(compra.proveedor || '-');
            $('#vistaCompraFecha').text(compra.fecha || '-');
            $('#vistaCompraTipo').text(compra.tipo_compra || '-');
            $('#vistaCompraTotal').text(formatearMonedaCompra(compra.total_compra));

            let html = '';

            detalles.forEach(function (detalle) {
                const tipo = detalle.tipo_detalle === 'INVENTARIO'
                    ? 'Inventario'
                    : 'Gasto / servicio';

                html += `
                    <tr>
                        <td>${escaparHtmlCompra(tipo)}</td>
                        <td>
                            <div class="font-weight-bold">${escaparHtmlCompra(detalle.nombre || detalle.descripcion)}</div>
                            ${detalle.categoria_compra
                                ? `<small class="text-muted">${escaparHtmlCompra(detalle.categoria_compra)}</small>`
                                : ''}
                        </td>
                        <td>${numeroCompra(detalle.cantidad, 3)}</td>
                        <td>${formatearMonedaCompra(detalle.precio_compra)}</td>
                        <td>${formatearMonedaCompra(detalle.importe)}</td>
                    </tr>`;
            });

            $('#detallesm').html(
                html || '<tr><td colspan="5" class="text-center text-muted">Sin detalles</td></tr>'
            );
        })
        .fail(function (xhr) {
            $('#getCodeModal').modal('hide');
            alertaCompra(
                'error',
                'Compra no disponible',
                mensajeRespuestaCompra(xhr, 'No se pudo cargar la compra.')
            );
        });
}

function anularCompra(idingreso) {
    const id = Number.parseInt(idingreso, 10) || 0;

    if (id <= 0) {
        return;
    }

    confirmarCompra(
        'Anular compra',
        'Se revertirá el stock inventariable. La anulación se bloqueará si parte de la mercadería ya fue vendida.',
        'Sí, anular'
    ).then(function (confirmado) {
        if (!confirmado) {
            return;
        }

        $.ajax({
            url: 'Controllers/Buy.php?op=anular',
            method: 'POST',
            dataType: 'json',
            data: { idingreso: id }
        })
            .done(function (respuesta) {
                if (!respuesta || respuesta.success !== true) {
                    alertaCompra(
                        'error',
                        'No se pudo anular',
                        respuesta && respuesta.mensaje
                            ? respuesta.mensaje
                            : 'El servidor no confirmó la anulación.'
                    );
                    return;
                }

                productosCompra = [];
                alertaCompra('success', 'Compra anulada', respuesta.mensaje);

                if (tablaCompras) {
                    tablaCompras.ajax.reload(null, false);
                }
            })
            .fail(function (xhr) {
                alertaCompra(
                    'error',
                    'No se pudo anular',
                    mensajeRespuestaCompra(
                        xhr,
                        'La compra no fue anulada.'
                    )
                );
            });
    });
}

function actualizarCoincidenciasProductoNuevo() {
    window.clearTimeout(temporizadorCoincidencias);

    temporizadorCoincidencias = window.setTimeout(function () {
        const nombre = normalizarTextoCompra($('#nuevo_nombre').val());
        const codigo = String($('#nuevo_codigo').val() || '').trim().toUpperCase();

        if (nombre.length < 3 && codigo.length < 2) {
            $('#coincidenciasProductoNuevo').hide().empty();
            return;
        }

        const coincidencias = productosCompra.filter(function (producto) {
            const nombreProducto = normalizarTextoCompra(producto.nombre);
            const codigoProducto = String(producto.codigo || '').trim().toUpperCase();

            return (
                (codigo !== '' && codigoProducto === codigo)
                || (nombre.length >= 3 && nombreProducto.includes(nombre))
                || (nombre.length >= 3 && nombre.includes(nombreProducto))
            );
        }).slice(0, 5);

        if (coincidencias.length === 0) {
            $('#coincidenciasProductoNuevo').hide().empty();
            return;
        }

        const lista = coincidencias.map(function (producto) {
            return '<li>'
                + escaparHtmlCompra(producto.nombre)
                + ' — '
                + escaparHtmlCompra(producto.codigo || 'sin código')
                + '</li>';
        }).join('');

        $('#coincidenciasProductoNuevo')
            .html(
                '<strong>Revisa estos productos similares:</strong>' +
                '<ul class="mb-0 mt-1 pl-3">' + lista + '</ul>'
            )
            .show();
    }, 180);
}

function init() {
    mostrarform(false);

    registrarFiltroFechaCompras();
    registrarEventosFiltroCompras();
    inicializarSelectorFechasCompra();

    /*
     * Al abrir Compras, mostrar únicamente el mes actual.
     */
    $('#compraFiltroPeriodo').val('mes');
    aplicarPeriodoFiltroCompras(
        'mes',
        false
    );

    listar();

    $(document).on('click', '.compra-export-option', function (event) {
        event.preventDefault();
        exportarCompras($(this).attr('data-formato'));
    });

    cargarProveedoresCompra();
    cargarDatosCompra();
    cargarProductosCompra();

    $('#formulario').on('submit', guardaryeditar);

    $('#condicion_pago, #idforma_pago')
        .on('change', actualizarEstadoPagoCompra);

    $('#formProductoNuevo').on('submit', agregarProductoNuevoDesdeFormulario);
    $('#formProveedorCompra').on('submit', guardarProveedorDesdeCompra);
    $('#formGastoServicio').on('submit', agregarGastoServicioDesdeFormulario);

    $('#btnProductoExistente').on('click', function () {
        $('#buscarProductoCompra').val('');
        $('#modalProductoExistente').modal('show');
        cargarProductosCompra();
    });

    $('#btnProductoNuevo').on('click', function () {
        $.when(cargarDatosCompra(), cargarProductosCompra()).always(function () {
            $('#formProductoNuevo')[0].reset();
            poblarSelectoresCompra();
            $('#nuevo_cantidad').val('1');
            $('#coincidenciasProductoNuevo').hide().empty();
            $('#compraMasivoBody').empty();
            cambiarModoProductoNuevoCompra('individual');
            $('#modalProductoNuevo').modal('show');
        });
    });

    $('#btnNuevoProveedorCompra').on('click', function () {
        limpiarProveedorCompra();
        $('#modalProveedorCompra').modal('show');
    });

    $('#proveedor_tipo_documento').on('change', actualizarDocumentoProveedorCompra);
    $('#proveedor_num_documento').on('input', function () {
        actualizarDocumentoProveedorCompra();
        $('#proveedorApiEstado').addClass('tw-hidden').removeClass('is-success is-error').empty();
    });
    $('#btnConsultarProveedorApi').on('click', consultarProveedorCompraApi);

    $('[data-producto-modo]').on('click', function () {
        cambiarModoProductoNuevoCompra($(this).attr('data-producto-modo'));
    });

    $('#btnAgregarFilaCompraMasiva').on('click', function () {
        asegurarCatalogosMasivosCompra().then(function () {
            const $fila = agregarFilaCompraMasiva({}, true);
            $fila.find('[data-field="nombre"]').trigger('focus');
        });
    });

    $('#btnLimpiarCompraMasiva').on('click', function () {
        limpiarCompraMasiva(true);
    });

    $('#btnAgregarProductosMasivos').on('click', agregarProductosMasivosALaCompra);
    $('#btnCerrarCompraMasiva').on('click', function () {
        cambiarModoProductoNuevoCompra('individual');
    });

    $('#archivoProductosCompraMasivo').on('change', function () {
        if (this.files && this.files[0]) cargarArchivoProductosCompraMasivo(this.files[0]);
        this.value = '';
    });

    $(document).on('click', '#compraMasivoBody .compra-sheet-remove', function () {
        $(this).closest('tr').remove();
        renumerarFilasCompraMasiva();
        validarCompraMasiva();
    });

    $(document).on('input change', '#compraMasivoBody .tp-sheet-cell, #compraMasivoBody .tp-sheet-select', function () {
        const $fila = $(this).closest('tr');
        const campo = String($(this).data('field') || '');
        if (campo === 'tipo') actualizarTipoFilaCompraMasiva($fila);
        if (campo === 'categoria') actualizarSubcategoriasFilaCompraMasiva($fila, String($(this).val() || ''), '');
        validarCompraMasiva();
    });

    $(document).on('paste', '#compraMasivoBody .tp-sheet-cell, #compraMasivoBody .tp-sheet-select', function (evento) {
        const original = evento.originalEvent;
        const texto = original && original.clipboardData ? original.clipboardData.getData('text') : '';
        if (!texto || (!texto.includes('\t') && !texto.includes('\n') && !texto.includes('\r'))) return;
        evento.preventDefault();
        const $fila = $(this).closest('tr');
        const inicio = camposMasivosCompraProducto().indexOf(String($(this).data('field') || ''));
        pegarMatrizCompraMasiva(parsearTextoPegadoCompraMasiva(texto), $fila, Math.max(0, inicio));
    });

    $(document).on('keydown', '#compraMasivoBody .tp-sheet-cell, #compraMasivoBody .tp-sheet-select', function (evento) {
        if (evento.key !== 'Enter') return;
        evento.preventDefault();
        const $fila = $(this).closest('tr');
        const campo = String($(this).data('field') || '');
        let $siguiente = $fila.next('tr');
        if (!$siguiente.length) $siguiente = agregarFilaCompraMasiva({}, true);
        const $destino = $siguiente.find(`[data-field="${campo}"]`);
        if ($destino.length) $destino.trigger('focus');
    });

    $('#btnGastoServicio').on('click', function () {
        cargarDatosCompra();
        $('#formGastoServicio')[0].reset();
        poblarSelectoresCompra();
        $('#gasto_cantidad').val('1');
        $('#modalGastoServicio').modal('show');
    });

    $('#modalProductoExistente').on('shown.bs.modal', function () {
        $('#buscarProductoCompra').trigger('focus');
    });

    $('#modalProductoNuevo').on('shown.bs.modal', function () {
        if (modoProductoNuevoCompra === 'individual') {
            $('#nuevo_nombre').trigger('focus');
            return;
        }

        asegurarCatalogosMasivosCompra().then(function () {
            if (!$('#compraMasivoBody tr').length) limpiarCompraMasiva(false);
            $('#compraMasivoBody tr:first [data-field="nombre"]').trigger('focus');
        });
    });

    $('#modalProveedorCompra').on('shown.bs.modal', function () {
        $('#proveedor_num_documento').trigger('focus');
    });

    $('#modalGastoServicio').on('shown.bs.modal', function () {
        $('#gasto_descripcion').trigger('focus');
    });

    $('#nuevo_idcategoria').on('change', actualizarSubcategoriasCompra);
    $('#impuesto').on('change', calcularTotalesCompra);

    $('#buscarProductoCompra').on('input', function () {
        const texto = normalizarTextoCompra($(this).val());

        if (texto === '') {
            renderizarProductosCompra(productosCompra);
            return;
        }

        const filtrados = productosCompra.filter(function (producto) {
            return normalizarTextoCompra(producto.nombre).includes(texto)
                || normalizarTextoCompra(producto.codigo).includes(texto);
        });

        renderizarProductosCompra(filtrados);
    });

    $('#nuevo_nombre, #nuevo_codigo').on(
        'input',
        actualizarCoincidenciasProductoNuevo
    );

    $(document).on('click', '.btnSeleccionarProductoCompra', function () {
        agregarProductoExistente(
            Number.parseInt($(this).attr('data-idarticulo'), 10) || 0
        );
    });

    $(document).on('input change', '.detalle-compra-input', function () {
        actualizarDetalleCompraDesdeInput(this);
    });

    $(document).on('click', '.btnEliminarDetalleCompra', function () {
        eliminarDetalleCompra(
            Number.parseInt($(this).attr('data-indice'), 10)
        );
    });
}

$(document).ready(init);
