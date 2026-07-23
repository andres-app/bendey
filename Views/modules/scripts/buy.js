'use strict';

let tablaCompras = null;
let detallesCompra = [];
let productosCompra = [];
let datosFormularioCompra = {
    categorias: [],
    subcategorias: [],
    medidas: [],
    almacenes: [],
    categorias_compra: []
};
let datosCompraCargados = false;
let guardandoCompra = false;
let temporizadorCoincidencias = null;

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
        $('#btnCancelar').show();

        cargarDatosCompra();
        window.setTimeout(function () {
            $('#idproveedor').trigger('focus');
        }, 120);
    } else {
        $('#formularioregistros').hide();
        $('#listadoregistros').show();
        $('#btnagregar').show();
    }
}

function cancelarform() {
    mostrarform(false);
}

function cargarProveedoresCompra() {
    $.ajax({
        url: 'Controllers/Buy.php?op=selectProveedor',
        method: 'GET',
        cache: false
    })
        .done(function (respuesta) {
            $('#idproveedor').html(respuesta);
        })
        .fail(function () {
            $('#idproveedor').html(
                '<option value="">No se pudieron cargar los proveedores</option>'
            );
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
                        ? `<img src="Assets/img/products/${escaparHtmlCompra(imagen)}" alt="${escaparHtmlCompra(producto.nombre)}">`
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
                `Compra #${respuesta.idingreso} registrada como ${respuesta.tipo_compra}.`
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

function listar() {
    if ($.fn.DataTable.isDataTable('#tbllistado')) {
        $('#tbllistado').DataTable().destroy();
    }

    tablaCompras = $('#tbllistado').DataTable({
        processing: true,
        serverSide: false,
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fa fa-file-excel-o"></i> Excel',
                titleAttr: 'Exportar a Excel',
                title: 'Reporte de compras',
                sheetName: 'Compras',
                exportOptions: { columns: [1, 2, 3, 4, 5, 6, 7, 8] }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fa fa-file-pdf-o"></i> PDF',
                titleAttr: 'Exportar a PDF',
                title: 'Reporte de compras',
                pageSize: 'A4',
                orientation: 'landscape',
                exportOptions: { columns: [1, 2, 3, 4, 5, 6, 7, 8] }
            }
        ],
        ajax: {
            url: 'Controllers/Buy.php?op=listar',
            type: 'GET',
            dataType: 'json',
            error: function (xhr) {
                console.error('ERROR LISTAR COMPRAS:', xhr.responseText);
            }
        },
        pageLength: 15,
        order: [[1, 'desc']],
        columnDefs: [
            { targets: [0], orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No hay compras registradas',
            processing: 'Cargando compras...',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: 'Mostrando _START_ a _END_ de _TOTAL_',
            infoEmpty: 'Sin registros',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
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
    listar();
    cargarProveedoresCompra();
    cargarDatosCompra();
    cargarProductosCompra();

    $('#formulario').on('submit', guardaryeditar);
    $('#formProductoNuevo').on('submit', agregarProductoNuevoDesdeFormulario);
    $('#formGastoServicio').on('submit', agregarGastoServicioDesdeFormulario);

    $('#btnProductoExistente').on('click', function () {
        $('#buscarProductoCompra').val('');
        $('#modalProductoExistente').modal('show');
        cargarProductosCompra();
    });

    $('#btnProductoNuevo').on('click', function () {
        cargarDatosCompra();
        cargarProductosCompra();
        $('#formProductoNuevo')[0].reset();
        poblarSelectoresCompra();
        $('#nuevo_cantidad').val('1');
        $('#coincidenciasProductoNuevo').hide().empty();
        $('#modalProductoNuevo').modal('show');
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
        $('#nuevo_nombre').trigger('focus');
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
