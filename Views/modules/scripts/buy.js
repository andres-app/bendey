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
    formas_pago: []
};
let datosCompraCargados = false;
let guardandoCompra = false;
let temporizadorCoincidencias = null;
let modoProductoNuevoCompra = 'individual';
let secuenciaFilaMasivaCompra = 0;

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


function opcionesMiniCompra(items, valorKey, textoFn, seleccionado = '') {
    let html = '<option value="">Seleccione...</option>';
    const selectedText = String(seleccionado || '');

    (Array.isArray(items) ? items : []).forEach(function (item) {
        const valor = String(item[valorKey] ?? '');
        const texto = typeof textoFn === 'function' ? textoFn(item) : String(item[textoFn] ?? '');
        html += `<option value="${escaparHtmlCompra(valor)}"${valor === selectedText ? ' selected' : ''}>${escaparHtmlCompra(texto)}</option>`;
    });

    return html;
}

function resolverCatalogoCompraMasiva(valor, items, idKey, campos) {
    const texto = String(valor ?? '').trim();
    if (!texto) return '';

    const prefijo = texto.match(/^\s*(\d+)\s*(?:-|$)/);
    const idDirecto = prefijo ? prefijo[1] : (/^\d+$/.test(texto) ? texto : '');
    if (idDirecto && items.some(function (item) { return String(item[idKey]) === String(idDirecto); })) {
        return String(idDirecto);
    }

    const objetivo = normalizarTextoCompra(texto);
    for (const item of items) {
        const candidatos = [];
        (campos || []).forEach(function (campo) {
            if (item[campo] !== undefined && item[campo] !== null) candidatos.push(String(item[campo]));
        });
        if (item.nombre !== undefined) candidatos.push(`${item[idKey]} - ${item.nombre}`);
        if (item.nombre !== undefined && item.codigo !== undefined) {
            candidatos.push(`${item.nombre} (${item.codigo})`);
            candidatos.push(`${item[idKey]} - ${item.nombre} (${item.codigo})`);
        }

        if (candidatos.some(function (candidato) { return normalizarTextoCompra(candidato) === objetivo; })) {
            return String(item[idKey]);
        }
    }

    return '';
}

function subcategoriasCompraPorCategoria(idcategoria) {
    const id = Number.parseInt(idcategoria, 10) || 0;
    return datosFormularioCompra.subcategorias.filter(function (item) {
        return Number.parseInt(item.idcategoria, 10) === id;
    });
}

function actualizarSubcategoriaFilaCompraMasiva($fila, idcategoria, seleccionado = '') {
    const items = subcategoriasCompraPorCategoria(idcategoria);
    const $select = $fila.find('[data-mini-field="idsubcategoria"]');

    if (!items.length) {
        $select.html('<option value="">Sin subcategoría</option>').prop('disabled', true);
        return;
    }

    $select
        .prop('disabled', false)
        .html(opcionesMiniCompra(items, 'idsubcategoria', 'nombre', seleccionado));
}

function agregarFilaCompraMasiva(datos = {}, enfocar = false) {
    secuenciaFilaMasivaCompra += 1;
    const rowId = secuenciaFilaMasivaCompra;

    const idcategoria = String(
        datos.idcategoria
        || resolverCatalogoCompraMasiva(datos.categoria, datosFormularioCompra.categorias, 'idcategoria', ['nombre'])
        || ''
    );
    const subItems = subcategoriasCompraPorCategoria(idcategoria);
    const idsubcategoria = String(
        datos.idsubcategoria
        || resolverCatalogoCompraMasiva(datos.subcategoria, subItems, 'idsubcategoria', ['nombre'])
        || ''
    );
    const idmedida = String(
        datos.idmedida
        || resolverCatalogoCompraMasiva(datos.medida, datosFormularioCompra.medidas, 'idmedida', ['nombre', 'codigo'])
        || (datosFormularioCompra.medidas.find(function (item) { return String(item.codigo || '').toUpperCase() === 'NIU'; }) || {}).idmedida
        || ''
    );
    const idalmacen = String(
        datos.idalmacen
        || resolverCatalogoCompraMasiva(datos.almacen, datosFormularioCompra.almacenes, 'idalmacen', ['nombre'])
        || (datosFormularioCompra.almacenes[0] || {}).idalmacen
        || ''
    );
    const tipoImportado = normalizarTextoCompra(datos.tipo || 'simple');
    const esVariante = ['variante', 'variacion', 'variable'].includes(tipoImportado);

    const cantidad = String(datos.cantidad ?? datos.stock ?? '1').trim() || '1';
    const precioCompra = String(datos.precio_compra ?? '').replace(',', '.').trim();
    const precioVenta = String(datos.precio_venta ?? '').replace(',', '.').trim();

    const html = `
        <tr data-mini-row="${rowId}" data-import-type="${esVariante ? 'variante' : 'simple'}">
            <td><input class="compra-mini-control" data-mini-field="nombre" maxlength="100" value="${escaparHtmlCompra(datos.nombre || '')}" placeholder="Nombre del producto"></td>
            <td><input class="compra-mini-control text-uppercase" data-mini-field="codigo" maxlength="50" value="${escaparHtmlCompra(datos.codigo || '')}" placeholder="Opcional"></td>
            <td><select class="compra-mini-control" data-mini-field="idcategoria">${opcionesMiniCompra(datosFormularioCompra.categorias, 'idcategoria', 'nombre', idcategoria)}</select></td>
            <td><select class="compra-mini-control" data-mini-field="idsubcategoria">${opcionesMiniCompra(subItems, 'idsubcategoria', 'nombre', idsubcategoria)}</select></td>
            <td><select class="compra-mini-control" data-mini-field="idmedida">${opcionesMiniCompra(datosFormularioCompra.medidas, 'idmedida', function (item) { return item.codigo ? `${item.nombre} (${item.codigo})` : item.nombre; }, idmedida)}</select></td>
            <td><select class="compra-mini-control" data-mini-field="idalmacen">${opcionesMiniCompra(datosFormularioCompra.almacenes, 'idalmacen', 'nombre', idalmacen)}</select></td>
            <td><input class="compra-mini-control" data-mini-field="cantidad" type="number" min="1" step="1" value="${escaparHtmlCompra(cantidad)}"></td>
            <td><input class="compra-mini-control" data-mini-field="precio_compra" type="number" min="0.01" step="0.01" value="${escaparHtmlCompra(precioCompra)}" placeholder="0.00"></td>
            <td><input class="compra-mini-control" data-mini-field="precio_venta" type="number" min="0" step="0.01" value="${escaparHtmlCompra(precioVenta)}" placeholder="Opcional"></td>
            <td class="tw-text-center">
                <button type="button" class="btnEliminarFilaCompraMasiva tw-inline-flex tw-h-8 tw-w-8 tw-items-center tw-justify-center tw-rounded-lg tw-border-0 tw-bg-rose-50 tw-text-rose-600 hover:tw-bg-rose-100" title="Eliminar fila"><i class="fas fa-times"></i></button>
            </td>
        </tr>`;

    $('#compraMasivoBody').append(html);
    const $fila = $(`#compraMasivoBody tr[data-mini-row="${rowId}"]`);
    actualizarSubcategoriaFilaCompraMasiva($fila, idcategoria, idsubcategoria);

    if (esVariante) {
        $fila.attr('title', 'Las variantes deben importarse desde Inventario > Productos. En Compras se agregan productos simples.');
    }

    if (enfocar) {
        $fila.find('[data-mini-field="nombre"]').trigger('focus');
    }

    validarCompraMasiva();
    return $fila;
}

function leerFilaCompraMasiva($fila) {
    const valor = function (campo) {
        return String($fila.find(`[data-mini-field="${campo}"]`).val() ?? '').trim();
    };

    return {
        row_id: String($fila.attr('data-mini-row') || ''),
        tipo_importado: String($fila.attr('data-import-type') || 'simple'),
        nombre: valor('nombre'),
        codigo: valor('codigo').toUpperCase().replace(/[^A-Z0-9._\-]/g, ''),
        idcategoria: Number.parseInt(valor('idcategoria'), 10) || 0,
        idsubcategoria: Number.parseInt(valor('idsubcategoria'), 10) || 0,
        idmedida: Number.parseInt(valor('idmedida'), 10) || 0,
        idalmacen: Number.parseInt(valor('idalmacen'), 10) || 0,
        cantidad: Number.parseInt(valor('cantidad'), 10) || 0,
        precio_compra: numeroCompra(valor('precio_compra')),
        precio_venta: valor('precio_venta') === '' ? null : numeroCompra(valor('precio_venta'))
    };
}

function validarCompraMasiva() {
    const filas = [];
    const codigosGrid = {};
    const codigosExistentes = new Set(
        productosCompra
            .map(function (producto) { return String(producto.codigo || '').trim().toUpperCase(); })
            .filter(Boolean)
            .concat(
                detallesCompra.map(function (detalle) { return String(detalle.codigo || '').trim().toUpperCase(); }).filter(Boolean)
            )
    );

    $('#compraMasivoBody tr').each(function () {
        const $fila = $(this);
        const datos = leerFilaCompraMasiva($fila);
        const errores = [];

        if (datos.tipo_importado === 'variante') {
            errores.push('Las variantes se importan desde Inventario > Productos.');
        }
        if (!datos.nombre) errores.push('Nombre obligatorio.');
        if (datos.idcategoria <= 0) errores.push('Categoría obligatoria.');
        if (datos.idmedida <= 0) errores.push('Unidad obligatoria.');
        if (datos.idalmacen <= 0) errores.push('Almacén obligatorio.');
        if (datos.cantidad <= 0) errores.push('Cantidad mayor que cero.');
        if (datos.precio_compra <= 0) errores.push('Costo mayor que cero.');

        if (datos.codigo) {
            if (codigosExistentes.has(datos.codigo)) errores.push('SKU ya registrado o agregado a la compra.');
            if (codigosGrid[datos.codigo]) errores.push('SKU repetido en el mini Excel.');
            codigosGrid[datos.codigo] = true;
        }

        datos._errores = errores;
        filas.push(datos);
        $fila.removeClass('has-error is-valid').attr('title', errores.join(' · '));
        $fila.addClass(errores.length ? 'has-error' : 'is-valid');
    });

    const validas = filas.filter(function (fila) { return fila._errores.length === 0; }).length;
    const errores = filas.length - validas;

    $('#compraMasivoTotal').text(filas.length);
    $('#compraMasivoValidas').text(validas);
    $('#compraMasivoErrores').text(errores);
    $('#btnAgregarProductosMasivos')
        .prop('disabled', validas === 0)
        .html(`<i class="fas fa-layer-group"></i> Agregar ${validas} producto${validas === 1 ? '' : 's'} válidos`);

    if (!filas.length) {
        $('#compraMasivoEstado').text('Agrega una fila o pega datos desde Excel.');
    } else if (errores) {
        $('#compraMasivoEstado').html(`<strong>${validas}</strong> listas · <span class="tw-text-rose-600"><strong>${errores}</strong> requieren corrección</span>`);
    } else {
        $('#compraMasivoEstado').html(`<span class="tw-text-emerald-700"><i class="fas fa-check-circle mr-1"></i><strong>${validas}</strong> productos listos para agregar</span>`);
    }

    return filas;
}

function limpiarCompraMasiva() {
    $('#compraMasivoBody').empty();
    for (let i = 0; i < 4; i += 1) {
        agregarFilaCompraMasiva({}, false);
    }
    validarCompraMasiva();
}

function cambiarModoProductoNuevoCompra(modo) {
    modoProductoNuevoCompra = modo === 'masivo' ? 'masivo' : 'individual';
    $('[data-producto-modo]').removeClass('is-active');
    $(`[data-producto-modo="${modoProductoNuevoCompra}"]`).addClass('is-active');

    const esMasivo = modoProductoNuevoCompra === 'masivo';
    $('#productoModoIndividual').toggleClass('tw-hidden', esMasivo);
    $('#productoModoMasivo').toggleClass('tw-hidden', !esMasivo);
    $('#btnAgregarProductoIndividual').toggleClass('tw-hidden', esMasivo);
    $('#btnAgregarProductosMasivos').toggleClass('tw-hidden', !esMasivo);

    if (esMasivo && !$('#compraMasivoBody tr').length) {
        limpiarCompraMasiva();
    }
}

function agregarProductosMasivosALaCompra() {
    const filas = validarCompraMasiva();
    const validas = filas.filter(function (fila) { return fila._errores.length === 0; });

    if (!validas.length) {
        alertaCompra('warning', 'Sin productos válidos', 'Corrige las filas marcadas antes de agregarlas a la compra.');
        return;
    }

    validas.forEach(function (fila) {
        detallesCompra.push({
            tipo_detalle: 'INVENTARIO',
            origen: 'NUEVO',
            idarticulo: 0,
            descripcion: fila.nombre,
            nombre: fila.nombre,
            codigo: fila.codigo,
            idcategoria: fila.idcategoria,
            idsubcategoria: fila.idsubcategoria,
            idmedida: fila.idmedida,
            idalmacen: fila.idalmacen,
            cantidad: fila.cantidad,
            precio_compra: fila.precio_compra,
            precio_venta: fila.precio_venta,
            importe: numeroCompra(fila.cantidad * fila.precio_compra)
        });
        $(`#compraMasivoBody tr[data-mini-row="${fila.row_id}"]`).remove();
    });

    renderizarDetallesCompra();
    validarCompraMasiva();

    if (!$('#compraMasivoBody tr').length || $('#compraMasivoBody tr.has-error').length === 0) {
        $('#modalProductoNuevo').modal('hide');
    }

    alertaCompra(
        'success',
        'Productos agregados',
        `${validas.length} producto${validas.length === 1 ? '' : 's'} nuevo${validas.length === 1 ? '' : 's'} se agregaron a la compra.`
    );
}

function pegarMatrizCompraMasiva(texto, $controlInicio) {
    const limpio = String(texto || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').trimEnd();
    if (!limpio || (!limpio.includes('\t') && !limpio.includes('\n'))) return false;

    const matriz = limpio.split('\n').map(function (linea) { return linea.split('\t'); });
    const campos = ['nombre', 'codigo', 'idcategoria', 'idsubcategoria', 'idmedida', 'idalmacen', 'cantidad', 'precio_compra', 'precio_venta'];
    let $fila = $controlInicio.closest('tr');
    let columnaInicial = campos.indexOf(String($controlInicio.attr('data-mini-field') || ''));
    if (columnaInicial < 0) columnaInicial = 0;

    matriz.forEach(function (columnas, indiceFila) {
        if (indiceFila > 0) {
            let $siguiente = $fila.next('tr');
            if (!$siguiente.length) $siguiente = agregarFilaCompraMasiva({}, false);
            $fila = $siguiente;
        }

        columnas.forEach(function (valor, offset) {
            const campo = campos[columnaInicial + offset];
            if (!campo) return;
            const $control = $fila.find(`[data-mini-field="${campo}"]`);
            if (!$control.length) return;

            if (campo === 'idcategoria') {
                const id = resolverCatalogoCompraMasiva(valor, datosFormularioCompra.categorias, 'idcategoria', ['nombre']);
                $control.val(id);
                actualizarSubcategoriaFilaCompraMasiva($fila, id, '');
            } else if (campo === 'idsubcategoria') {
                const items = subcategoriasCompraPorCategoria($fila.find('[data-mini-field="idcategoria"]').val());
                $control.val(resolverCatalogoCompraMasiva(valor, items, 'idsubcategoria', ['nombre']));
            } else if (campo === 'idmedida') {
                $control.val(resolverCatalogoCompraMasiva(valor, datosFormularioCompra.medidas, 'idmedida', ['nombre', 'codigo']));
            } else if (campo === 'idalmacen') {
                $control.val(resolverCatalogoCompraMasiva(valor, datosFormularioCompra.almacenes, 'idalmacen', ['nombre']));
            } else {
                $control.val(String(valor ?? '').trim());
            }
        });
    });

    validarCompraMasiva();
    return true;
}

function cargarArchivoProductosCompraMasivo(archivo) {
    if (!archivo) return;

    const formData = new FormData();
    formData.append('archivo_productos', archivo);
    $('#compraMasivoEstado').html('<span class="spinner-border spinner-border-sm mr-1"></span> Leyendo archivo...');

    $.ajax({
        url: 'Controllers/Buy.php?op=previsualizarProductosMasivos',
        method: 'POST',
        dataType: 'json',
        data: formData,
        processData: false,
        contentType: false
    }).done(function (respuesta) {
        if (!respuesta || respuesta.success !== true) {
            alertaCompra('error', 'Archivo no válido', (respuesta && respuesta.mensaje) || 'No se pudo leer el archivo.');
            return;
        }

        const filas = Array.isArray(respuesta.filas) ? respuesta.filas : [];
        $('#compraMasivoBody').empty();
        filas.forEach(function (fila) { agregarFilaCompraMasiva(fila, false); });
        if (!filas.length) limpiarCompraMasiva();
        validarCompraMasiva();
        alertaCompra('success', 'Archivo cargado', `${filas.length} fila${filas.length === 1 ? '' : 's'} preparada${filas.length === 1 ? '' : 's'} para revisión.`);
    }).fail(function (xhr) {
        alertaCompra('error', 'No se pudo cargar', mensajeRespuestaCompra(xhr, 'No se pudo procesar el archivo.'));
        validarCompraMasiva();
    }).always(function () {
        $('#archivoProductosCompraMasivo').val('');
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
            ? 'Producto nuevo'
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
    const condicion = String(
        $('#condicion_pago').val() || 'CONTADO'
    ).toUpperCase();

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

    const condicionPago = String(
        $('#condicion_pago').val() || ''
    ).toUpperCase();

    if (!['CONTADO', 'CREDITO'].includes(condicionPago)) {
        alertaCompra(
            'warning',
            'Condición de pago',
            'Seleccione Contado o Crédito.'
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
        agregarFilaCompraMasiva({}, true);
    });

    $('#btnLimpiarCompraMasiva').on('click', function () {
        limpiarCompraMasiva();
    });

    $('#btnAgregarProductosMasivos').on('click', agregarProductosMasivosALaCompra);

    $('#btnCargarArchivoProductosCompra').on('click', function () {
        $('#archivoProductosCompraMasivo').trigger('click');
    });

    $('#archivoProductosCompraMasivo').on('change', function () {
        cargarArchivoProductosCompraMasivo(this.files && this.files[0] ? this.files[0] : null);
    });

    $(document).on('click', '.btnEliminarFilaCompraMasiva', function () {
        $(this).closest('tr').remove();
        validarCompraMasiva();
    });

    $(document).on('change', '#compraMasivoBody [data-mini-field="idcategoria"]', function () {
        const $fila = $(this).closest('tr');
        actualizarSubcategoriaFilaCompraMasiva($fila, $(this).val(), '');
        validarCompraMasiva();
    });

    $(document).on('input change', '#compraMasivoBody .compra-mini-control', function () {
        validarCompraMasiva();
    });

    $(document).on('paste', '#compraMasivoBody .compra-mini-control', function (evento) {
        const original = evento.originalEvent;
        const texto = original && original.clipboardData ? original.clipboardData.getData('text/plain') : '';
        if (pegarMatrizCompraMasiva(texto, $(this))) {
            evento.preventDefault();
        }
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
        }
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
