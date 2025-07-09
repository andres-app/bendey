// newsale3.js
$(document).ready(function () {
    cargarComprobantes();
    inicializarEventos();

    // Opcional: carga el carrito si usas panel derecho dinámico
    cargarCarrito();
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
        $("#carrito-productos").html(html);
        calcularTotales(); // Llama aquí si quieres recalcular después de cargar productos
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

$('#btnAbrirModal').on('click', function() {
    $('#modalProductos').modal('show');
});
