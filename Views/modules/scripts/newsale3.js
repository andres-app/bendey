// newsale3.js
$(document).ready(function () {
    cargarComprobantes();
    inicializarEventos();
    cargarCarrito();
    actualizarMensajePedido();
});



// 1. CARGA DE SELECTS DINÁMICOS
function cargarComprobantes() {
    $.post("Controllers/Sell.php?op=selectComprobante", function (data) {
        $('#tipo_comprobante').html(data);
        // Opcional: selecciona por defecto el primero
        $('#tipo_comprobante').val($('#tipo_comprobante option:first').val());
    });

    // Clientes
    $.post("Controllers/Sell.php?op=selectCliente", function (r) {
        $("#selectCliente").html(r);
        $("#selectCliente").trigger('change');
    });

    // Condición de pago
    $.post("Controllers/Sell.php?op=selectCondicionPago", function (r) {
        $("#condicion_pago").html(r);
    });


}

// 2. INICIALIZA EVENTOS DEL FORMULARIO Y SELECTS
function inicializarEventos() {
    // Cuando cambia el comprobante, mostrar serie y número
    $('#tipo_comprobante').on('change', function () {
        mostrarSerieNumero();
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
        let tipo = $(this).val();
        $('#pago_mixto, #pago_credito').hide();
        if (tipo === 'Mixto') $('#pago_mixto').show();
        if (tipo === 'Crédito') $('#pago_credito').show();
    });

    // Control del descuento (switch o input)
    $('#descuentoSwitch').on('change', function () {
        if ($(this).is(':checked')) {
            $('#descuentoPorcentaje').prop('disabled', false);
        } else {
            $('#descuentoPorcentaje').prop('disabled', true).val(0);
        }
        calcularTotales();
    });

    $('#descuentoPorcentaje').on('input', calcularTotales);

    // Envío del formulario
    $('#formularioVenta').on('submit', function (e) {
        e.preventDefault();
        guardarVenta();
    });
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
    let form = $('#formularioVenta')[0];
    let formData = new FormData(form);

    $.ajax({
        url: "Controllers/Sell.php?op=guardaryeditar",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (resp) {

            let data;
            try {
                data = JSON.parse(resp);
            } catch (e) {
                data = { success: false, mensaje: resp };
            }

            if (data.success) {

                // 📱 Extraer celular sin 51
                let celularBase = data.celular
                    ? data.celular.replace(/^51/, '')
                    : '';

                Swal.fire({
                    title: 'Venta registrada',
                    html: `
                            <p>¿Qué deseas hacer ahora?</p>
                            <div style="display:flex; justify-content:center; align-items:center;">
                                <input class="swal2-input"
                                       style="width:55px; text-align:center; margin-right:5px; font-weight:bold;"
                                       value="51" readonly>
    
                                <input id="swal-input-cel"
                                       class="swal2-input"
                                       style="width:180px;"
                                       maxlength="9"
                                       placeholder="Celular"
                                       value="${celularBase}">
                            </div>
                        `,
                    icon: 'success',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'Imprimir',
                    denyButtonText: 'Enviar WhatsApp',
                    cancelButtonText: 'Cerrar'
                }).then((result) => {

                    let celular = document
                        .getElementById('swal-input-cel')
                        ?.value.trim();

                    // 🧹 LIMPIEZA
                    form.reset();
                    if (typeof cargarCarrito === 'function') cargarCarrito();
                    if (typeof limpiar === 'function') limpiar();

                    // 🖨️ IMPRIMIR
                    if (result.isConfirmed) {
                        window.open(
                            'Reports/80mm.php?id=' + data.idventa,
                            '_blank'
                        );
                    }

                    // 📲 WHATSAPP
                    else if (result.isDenied) {

                        if (!/^\d{9}$/.test(celular)) {
                            Swal.fire(
                                'Número inválido',
                                'Ingrese los 9 dígitos del celular',
                                'warning'
                            );
                            return;
                        }

                        let celularCompleto = '51' + celular;
                        let urlPDF = location.origin + "/Reports/80mm.php?id=" + data.idventa;

                        let mensaje = `Hola ${data.nombre || ''}, aquí está tu comprobante de venta: ${urlPDF}`;

                        let whatsappLink =
                            `https://wa.me/${celularCompleto}?text=${encodeURIComponent(mensaje)}`;

                        window.open(whatsappLink, '_blank');
                    }
                });

            } else {
                Swal.fire("Error", data.mensaje || "No se pudo guardar la venta.", "error");
            }
        },
        error: function () {
            Swal.fire("Error", "No se pudo conectar con el servidor.", "error");
        }
    });
}


// 5. CARGA DINÁMICAMENTE EL CARRITO/PEDIDO ACTUAL
function cargarCarrito() {
    $.get("Controllers/Sell.php?op=listarProductosCarrito", function (html) {
        $("#detallesCards").html(html);
        actualizarMensajePedido();   // ✅ aquí
        calcularTotales();
    });
}


// 6. CALCULA TOTALES (puedes adaptar según tus campos)
function calcularTotales() {
    let total = 0;

    $("span[name='subtotal']").each(function () {
        total += parseFloat($(this).text()) || 0;
    });

    $("#totalGeneral").text("S/" + total.toFixed(2));

    calcularVuelto(); // 👈 AQUI
}



function consultarCliente() {
    const tipo_documento = $('#tipo_documento').val();
    const num_documento = $('#num_documento').val().trim();

    if (!num_documento) {
        Swal.fire('Atención', 'Ingrese el número de documento', 'warning');
        return;
    }

    // Guardamos el documento REAL
    $('#num_doc_real').val(num_documento);

    $.ajax({
        url: 'Controllers/Person.php?op=getCustomerByDocument',
        type: 'POST',
        data: { tipo_documento: tipo_documento, num_documento: num_documento },
        success: function (response) {

            let data;
            try {
                data = JSON.parse(response);
            } catch {
                Swal.fire('Error', 'Respuesta inválida del servidor', 'error');
                return;
            }

            if (data.estado && data.resultado) {

                // 🔹 VISUALMENTE mostramos el nombre en el MISMO input
                $('#num_documento').val(data.resultado.nombre);

                // 🔹 LÓGICA REAL (no visible)
                $('#num_doc_real').val(data.resultado.num_documento);
                $('#nombre_cli').val(data.resultado.nombre);
                $('#idcliente').val(data.resultado.idpersona);

            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cliente no registrado',
                    text: '¿Deseas buscar en RENIEC / SUNAT?',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, buscar',
                    cancelButtonText: 'No'
                }).then((r) => {
                    if (r.isConfirmed) {
                        consultarClienteReniec(tipo_documento, num_documento);
                    }
                });
            }
        },
        error: function () {
            Swal.fire('Error', 'No se pudo consultar el cliente', 'error');
        }
    });
}



function consultarClienteReniec(tipo_documento, num_documento) {
    if (!num_documento || num_documento.trim() === "") {
        Swal.fire("Error", "Debe ingresar un número de documento válido", "error");
        return;
    }

    // Detecta el tipo automáticamente
    let tipo_detectado = "";
    if (num_documento.length === 8) {
        tipo_detectado = "DNI";
    } else if (num_documento.length === 11) {
        tipo_detectado = "RUC";
    } else {
        Swal.fire("Error", "El número de documento debe tener 8 (DNI) u 11 (RUC) dígitos.", "error");
        return;
    }

    $.ajax({
        url: 'Controllers/Person.php?op=getCustomerInfo',
        type: 'POST',
        data: { tipo_documento: tipo_detectado, num_documento: num_documento },
        success: function (response) {
            var data;
            try {
                data = JSON.parse(response);
            } catch (e) {
                Swal.fire('Error', 'Error al procesar la respuesta del servidor.', 'error');
                return;
            }

            if (data.estado) {
                let nombre = data.resultado.nombre || data.resultado.razon_social || '';
                if (nombre.trim() !== '') {
                    // SOLO NOMBRE en el input
                    $('#num_documento').val(nombre);
                    $('#direccion').val(data.resultado.direccion || '');
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Datos incompletos',
                        text: data.mensaje || 'No se encontraron los datos completos del cliente.'
                    });
                    $('#num_documento').addClass('is-invalid');
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'No encontrado',
                    text: data.mensaje || 'No se encontró información del documento.'
                });
                $('#num_documento').addClass('is-invalid');
            }
        },
        error: function () {
            Swal.fire('Error', 'Error al consultar la RENIEC/SUNAT.', 'error');
        }
    });
}

function listarCategorias() {
    $.ajax({
        url: 'Controllers/Sell.php?op=listarCategorias',
        type: 'get',
        dataType: 'json',
        success: function (data) {
            let catHtml = '';
            if (data.length === 0) {
                catHtml = '<li class="nav-item"><a class="nav-link disabled" href="#">Sin categorías</a></li>';
            } else {
                data.forEach(function (cat, idx) {
                    catHtml += `<li class="nav-item">
                            <a class="nav-link ${idx === 0 ? 'active' : ''}" href="#" data-id="${cat.idcategoria}" onclick="listarArticulosPorCategoria(${cat.idcategoria}, this); return false;">
                                ${cat.nombre}
                            </a>
                        </li>`;
                });
            }
            $('#catList').html(catHtml);

            // Carga la primera categoría por defecto
            if (data.length > 0) {
                listarArticulosPorCategoria(data[0].idcategoria, $('#catList a.nav-link').get(0));
            }
        }
    });
}

function listarArticulosPorCategoria(idcategoria, tabElement) {
    $('#catList a.nav-link').removeClass('active');
    if (tabElement) $(tabElement).addClass('active');

    $.ajax({
        url: 'Controllers/Sell.php?op=listarArticulosPorCategoria&idcategoria=' + idcategoria,
        type: 'get',
        dataType: 'json',
        success: function (data) {
            let prodHtml = '';

            if (data.length === 0) {
                prodHtml = `
                        <div class="col-12 text-center py-5">
                            <b>No hay productos en esta categoría.</b>
                        </div>`;
            } else {
                data.forEach(function (prod) {
                    prodHtml += `
                        <div class="col-12 col-md-6 col-lg-4 mb-4">
                            <div class="card border-0 shadow-sm h-100 producto-card"
                                style="cursor:pointer;"
                                onclick="agregarDetalle(
                                    ${prod.idingreso},
                                    ${prod.idarticulo},
                                    '${prod.nombre.replace(/'/g, "\\'")}',
                                    ${prod.precio_compra},
                                    ${prod.precio_venta},
                                    ${prod.stock},
                                    1
                                )">
                        
                                <div class="card-body">
                        
                                    <div class="mb-2 fw-bold fs-5" style="color:#353535;">
                                        ${prod.nombre}
                                    </div>
                        
                                    <div class="d-flex align-items-center mb-3">
                                        <div style="width:90px; height:90px; background:#f2f2f2; border-radius:12px; display:flex; align-items:center; justify-content:center; margin-right:24px;">
                                            ${prod.imagen
                            ? `<img src="Assets/img/products/${prod.imagen}" style="max-width:80px; max-height:80px; border-radius:10px;">`
                            : '<i class="bi bi-image fs-1 text-secondary"></i>'}
                                        </div>
                        
                                        <div class="small">
                                            <div><strong>SKU:</strong> ${prod.codigo}</div>
                                            <div><strong>Stock:</strong> ${prod.stock}</div>
                                            <div><strong>Precio:</strong> S/${Number(prod.precio_venta).toFixed(2)}</div>
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
    });
}


$('#btnAbrirModal').on('click', function () {
    $('#modalProductos').modal('show');
    listarCategorias(); // <-- Aquí llamas a cargar las categorías al abrir
});

var cont = 0;

function agregarDetalle(
    idingreso,
    idarticulo,
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
            let inputCantidad = $("input[name='cantidad[]']").eq(index);
            let nuevaCantidad = parseInt(inputCantidad.val()) + 1;

            if (nuevaCantidad > stock) {
                Swal.fire("Stock insuficiente", "No hay más unidades disponibles.", "warning");
                return false;
            }

            inputCantidad.val(nuevaCantidad);
            modificarSubtotales();

            // ✅ CLAVE: ya hay productos, ocultar mensaje
            actualizarMensajePedido();

            existe = true;
            return false;
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
                    <div class="text-muted small">SKU: ${idarticulo}</div>

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
                        <button type="button" class="btn btn-outline-success btn-sm px-2 py-1"
                            onclick="incrementarCantidad(${cont}, ${stock})">
                            <i class="bi bi-plus"></i>
                        </button>

                        <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-1"
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

    // 🔥 FUERZA ocultar overlay al agregar

    cont++;

    modificarSubtotales();
    evaluar();

    $('#modalProductos').modal('hide');

}

function incrementarCantidad(indice, stock) {
    let cantidadInput = document.getElementById('cantidadInput' + indice);
    let cantidadLabel = document.getElementById('cantidadLabel' + indice);
    let precioInput   = document.querySelectorAll("input[name='precio_venta[]']")[indice];
    let subtotalSpan  = document.getElementById('subtotal' + indice);

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
    let precioInput   = document.querySelectorAll("input[name='precio_venta[]']")[indice];
    let subtotalSpan  = document.getElementById('subtotal' + indice);

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
    calcularTotales();
    evaluar();
}


function actualizarMensajePedido() {
    const hayProductos = $("#detallesCards .filas").length > 0;

    if (hayProductos) {
        $("#pedidoVacio").addClass("d-none");
    } else {
        $("#pedidoVacio").removeClass("d-none");
    }
}

function calcularVuelto() {
    let totalVenta = totalVentaActual();
    let recibido = parseFloat($('#total_recibido').val()) || 0;

    let vuelto = recibido - totalVenta;
    if (vuelto < 0) vuelto = 0;

    $('#vuelto').val(vuelto.toFixed(2));
}



$('#total_recibido').on('input', function () {
    calcularVuelto();
});


$('#formularioVenta').on('submit', function (e) {

    let forma = $('#forma_pago').val();
    if (forma !== 'Mixto') return; // normal

    let totalVenta = totalVentaActual();
    let totalPagado = 0;

    $('#pagosMixtosContainer .pago-monto').each(function () {
        totalPagado += parseFloat($(this).val()) || 0;
    });

    if (totalPagado < totalVenta) {
        e.preventDefault();
        Swal.fire(
            'Pago incompleto',
            'La suma de los métodos no cubre el total de la venta',
            'warning'
        );
        return false;
    }
});


// FORMA DE PAGO: mostrar campos mixtos
$('#forma_pago').on('change', function () {
    let forma = $(this).val();

    if (forma === 'Mixto') {

        $('#bloque_pago_mixto').slideDown();
        $('#pagosMixtosContainer').html('');
        pagoMixtoIndex = 0;

        agregarPagoMixtoFila();
        agregarPagoMixtoFila();

        $('#total_recibido').val('');
        $('#vuelto').val('0.00');

    } else {

        $('#bloque_pago_mixto').hide();
        $('#pagosMixtosContainer').html('');

        // vuelve al flujo normal
        $('#total_recibido').val('');
        $('#vuelto').val('0.00');
    }
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
    let total = 0;
    $("span[name='subtotal']").each(function () {
        total += parseFloat($(this).text()) || 0;
    });
    return total;
}

function calcularPagoMixtoForma() {
    let totalVenta = totalVentaActual();

    let totalPagado = 0;
    let efectivo = 0;

    $('#pagosMixtosContainer .pago-mixto-fila').each(function () {
        let metodo = $(this).find('.pago-metodo').val();
        let monto  = parseFloat($(this).find('.pago-monto').val()) || 0;

        totalPagado += monto;
        if (metodo === 'Efectivo') efectivo += monto;
    });

    // Vuelto solo desde efectivo
    let vuelto = efectivo - totalVenta;
    if (totalPagado < totalVenta) vuelto = 0;
    if (vuelto < 0) vuelto = 0;

    $('#vuelto').val(vuelto.toFixed(2));
}

