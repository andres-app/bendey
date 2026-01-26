let productosCache = [];

// newsale3.js
$(document).ready(function () {
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
        if ($(this).is(':checked')) {
            $('#descuentoPorcentaje').prop('disabled', false);
        } else {
            $('#descuentoPorcentaje').prop('disabled', true).val(0);
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
                    <input id="swal-input-cel"
                           class="swal2-input"
                           maxlength="9"
                           placeholder="Celular"
                           value="${celularBase}">
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

    sincronizarTotalRecibido();   // contado / efectivo
    recalcularCuotasCredito();    // 🔥 crédito
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

            let html = '';

            if (data.length === 0) {
                html = `
                  <li class="nav-item">
                    <span class="nav-link text-muted">Sin categorías</span>
                  </li>`;
            } else {

                data.forEach((cat, idx) => {
                    html += `
                      <li class="nav-item">
                        <a href="#"
                           class="nav-link px-3 py-2 ${idx === 0 ? 'active fw-semibold text-success border-bottom border-2' : 'text-secondary'}"
                           data-id="${cat.idcategoria}">
                           ${cat.nombre}
                        </a>
                      </li>`;
                });
            }

            $('#catList').html(html);

            if (data.length > 0) {
                listarArticulosPorCategoria(data[0].idcategoria);
            }
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

            productosCache = data;   // 🔥 cache
            renderProductos(data);   // 🔥 render central
        },
        error: function () {
            productosCache = [];
            renderProductos([]);
        }
    });
}


$(document).on('click', '#catList a.nav-link:not(.disabled)', function (e) {
    e.preventDefault();

    // 🔹 quitar active a todos
    $('#catList a.nav-link').removeClass('active');

    // 🔹 activar el seleccionado
    $(this).addClass('active');

    // 🔹 obtener id
    let idcategoria = $(this).data('id');

    // 🔹 cargar productos
    listarArticulosPorCategoria(idcategoria);
});



function renderProductos(data) {

    let prodHtml = '';

    if (!data || data.length === 0) {
        prodHtml = `
            <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-search fs-1 mb-3"></i>
                <div>No se encontraron productos</div>
            </div>`;
    } else {

        data.forEach(function (prod) {

            prodHtml += `
                <div class="col-12 col-md-6 col-lg-4 mb-4 producto-item"
                     data-nombre="${prod.nombre.toLowerCase()}"
                     data-codigo="${prod.codigo.toLowerCase()}">

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
                                <div style="width:90px;height:90px;background:#f2f2f2;border-radius:12px;
                                            display:flex;align-items:center;justify-content:center;margin-right:24px;">
                                    ${prod.imagen
                                        ? `<img src="Assets/img/products/${prod.imagen}" style="max-width:80px; max-height:80px; border-radius:10px;">`
                                        : `<i class="bi bi-image fs-1 text-secondary"></i>`}
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



$('#buscarProducto').on('input', function () {

    let texto = $(this).val().toLowerCase().trim();

    if (texto === '') {
        renderProductos(productosCache);
        return;
    }

    let filtrados = productosCache.filter(p =>
        p.nombre.toLowerCase().includes(texto) ||
        p.codigo.toLowerCase().includes(texto)
    );

    renderProductos(filtrados);
});

$('#btnAbrirModal').on('click', function () {
    $('#modalProductos').modal('show');
    $('#buscarProducto').val('');
    listarCategorias();
});


function listarArticulosPorCategoria(idcategoria, tabElement) {

    // UI: activar tab
    $('#catList a.nav-link').removeClass('active');
    if (tabElement) $(tabElement).addClass('active');

    $.ajax({
        url: 'Controllers/Sell.php?op=listarArticulosPorCategoria&idcategoria=' + idcategoria,
        type: 'get',
        dataType: 'json',
        success: function (data) {

            productosCache = data;   // 🔥 guardas productos de ESA categoría
            renderProductos(data);   // 🔥 pintas usando el render central
        },
        error: function () {
            productosCache = [];
            renderProductos([]);
        }
    });
}




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

let bufferScan = '';

$('#scannerInput').on('keydown', function (e) {

    if (e.key === 'Enter') {
        e.preventDefault();

        let codigo = bufferScan.trim();
        bufferScan = '';

        if (codigo !== '') buscarProductoPorCodigo(codigo);

        $(this).val('');
        return;
    }

    if (e.key.length === 1) {
        bufferScan += e.key;
    }
});

function buscarProductoPorCodigo(codigo) {

    $.post(
        "Controllers/Sell.php?op=buscarProductoPorCodigo",
        { codigo: codigo },
        function (resp) {

            let data = JSON.parse(resp);

            if (!data || data.length === 0) {
                Swal.fire('Producto no encontrado', codigo, 'warning');
                return;
            }
            
            // 🔥 TOMAMOS EL PRIMER REGISTRO
            let p = data[0];
            
            agregarDetalle(
                p.idingreso,
                p.idarticulo,
                p.nombre,
                p.precio_compra,
                p.precio_venta,
                p.stock,
                1
            );
            
        }
    );
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
    calcularVuelto();
});


$('#formularioVenta').on('submit', function (e) {

    e.preventDefault(); // ⛔ siempre primero

    let condicion = $('#condicion_pago').val();
    let nombreForma = getNombreFormaPago();
    let totalVenta = totalVentaActual();

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

    let nombreForma = getNombreFormaPago();

    // RESET GENERAL
    $('#bloque_pago_mixto').hide();
    $('#pagosMixtosContainer').html('');
    $('#vuelto').val('0.00');

    let totalVenta = totalVentaActual();

    // =========================
    // 🔹 PAGO MIXTO
    // =========================
    if (nombreForma === 'Mixto') {

        $('#bloque_pago_mixto').slideDown();
        pagoMixtoIndex = 0;

        agregarPagoMixtoFila();
        agregarPagoMixtoFila();

        // 🔒 BLOQUEAR TOTAL RECIBIDO
        $('#total_recibido')
            .val('0.00')
            .prop('readonly', true)
            .addClass('bg-light');

        $('#vuelto').val('0.00');

        return;
    }

    // =========================
    // 🔹 PAGO NORMAL
    // =========================

    // 🔓 HABILITAR TOTAL RECIBIDO
    $('#total_recibido')
        .prop('readonly', false)
        .removeClass('bg-light')
        .val(totalVenta.toFixed(2));

    calcularVuelto();
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

        // ✅ SOLO backend
        $("#forma_pago").html(r);

        // 🔒 estado inicial
        $('#bloque_pago_mixto').hide();
        $('#total_recibido')
            .val('')
            .prop('readonly', true)
            .addClass('bg-light');

        $('#vuelto').val('0.00');
    });
}




