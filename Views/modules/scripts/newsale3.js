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

        // Forma de pago
        $.post("Controllers/Sell.php?op=selectFormaPago", function (r) {
            $("#forma_pago").html(r);
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
                try { data = JSON.parse(resp); } catch (e) { data = { success: false, mensaje: resp }; }
                if (data.success) {
                    Swal.fire("¡Venta registrada!", "La venta se guardó correctamente.", "success");
                    // Limpiar el formulario, recargar carrito, totales, etc.
                    form.reset();
                    cargarCarrito();
                } else {
                    Swal.fire("Error", data.mensaje || "No se pudo guardar la venta.", "error");
                }
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
        let subtotal = 0;
        let descuento = parseFloat($('#descuentoPorcentaje').val()) || 0;
        // Recorre productos en tu carrito (ajusta el selector si usas otro HTML)
        $('#carrito-productos .producto-total').each(function () {
            subtotal += parseFloat($(this).text()) || 0;
        });
        let total = subtotal * (1 - descuento / 100);
        $('#totalRecibido').val('S/' + total.toFixed(2));
        // Puedes calcular vuelto y mostrarlo donde necesites
    }

    function consultarCliente() {
        var tipo_documento = $('#tipo_documento').val();
        var num_documento = $('#num_documento').val();

        $.ajax({
            url: 'Controllers/Person.php?op=getCustomerByDocument',
            type: 'POST',
            data: { tipo_documento: tipo_documento, num_documento: num_documento },
            success: function (response) {
                // -----> AGREGA ESTO:
                console.log('Respuesta de getCustomerByDocument:', response);
                var data;
                try {
                    data = JSON.parse(response);
                } catch (e) {
                    alert('Error al procesar la respuesta del servidor.');
                    return;
                }

                if (data.estado && data.resultado && data.resultado.nombre) {
                    // REEMPLAZA el valor del input con el nombre
                    $("#num_documento").val(data.resultado.nombre || '');
                    // Si quieres guardar el idpersona de forma oculta:
                    $("#idpersona").val(data.resultado.idpersona || '');
                    // Opcional: autollenar otros campos, pero solo si existen
                    // $("#direccion").val(data.resultado.direccion || '');
                } else {
                    // Cliente NO encontrado: el mismo flujo que ya tienes
                    Swal.fire({
                        icon: 'warning',
                        title: 'Cliente no registrado',
                        text: 'El cliente no está en la base de datos. ¿Deseas buscar en RENIEC/SUNAT?',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, buscar',
                        cancelButtonText: 'No, cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            consultarClienteReniec(tipo_documento, num_documento);
                        }
                    });
                }

            },
            error: function () {
                alert('Error al consultar el cliente en la base de datos.');
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
                        <span class="fw-semibold">S/
                            <input type="number"
                                name="precio_venta[]"
                                value="${precio_venta}"
                                min="0"
                                step="0.01"
                                style="width:70px"
                                class="form-control form-control-sm d-inline-block ms-1"
                                onchange="modificarSubtotales()">
                        </span>
                    </div>

                    <div class="text-muted small">
                        Cantidad:
                        <span class="fw-semibold">
                            <input type="number"
                                name="cantidad[]"
                                value="${cantidad}"
                                min="1"
                                max="${stock}"
                                style="width:60px"
                                class="form-control form-control-sm d-inline-block ms-1"
                                onchange="ver_stock(this.value, ${stock}); modificarSubtotales();">
                        </span>
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
                        <button class="btn btn-outline-success btn-sm px-2 py-1"
                            onclick="incrementarCantidad(${cont}, ${stock})">
                            <i class="bi bi-plus"></i>
                        </button>

                        <button class="btn btn-outline-secondary btn-sm px-2 py-1"
                            onclick="decrementarCantidad(${cont})">
                            <i class="bi bi-dash"></i>
                        </button>
                    </div>

                    <button class="btn btn-outline-danger btn-sm px-2 py-1 mt-3"
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
        let input = $("input[name='cantidad[]']").eq(indice);
        let valor = parseInt(input.val()) + 1;

        if (valor > stock) {
            Swal.fire("Stock insuficiente", "No hay más unidades.", "warning");
            return;
        }

        input.val(valor);
        modificarSubtotales();
    }

    function decrementarCantidad(indice) {
        let input = $("input[name='cantidad[]']").eq(indice);
        let valor = parseInt(input.val()) - 1;

        if (valor < 1) return;

        input.val(valor);
        modificarSubtotales();
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
    


