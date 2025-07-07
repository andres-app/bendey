var tabla;

//funcion que se ejecuta al inicio
function init() {
	//mostrar_impuesto();
	listarArticulos();
	$("#t_pago").hide();

	$("#formulario").on("submit", function (e) {
		guardaryeditar(e);
	});

	$("#formulariocliente").on("submit", function (e) {
		agregarCliente(e);
	});

	//cargamos los items al select cliente
	$.post("Controllers/Sell.php?op=selectCliente", function (r) {
		$("#idcliente").html(r);
		//$("#idcliente").selectpicker("refresh");
	});

	//cargamos los items al celect comprobantes
	$.post("Controllers/Sell.php?op=selectComprobante", function (c) {
		//alert(c);
		$("#tipo_comprobante").val("Ticket");
		$("#tipo_comprobante").html(c);
		//$("#tipo_comprobante").selectpicker("refresh");
	});

	//cargamos los items al celect tipo de pago
	$.post("Controllers/Sell.php?op=selectTipopago", function (c) {
		$("#tipo_pago").html(c);
		//$("#tipo_pago").selectpicker("refresh");
	});
}

var cont = 0;
var detalles = 0;
$("#btnGuardar").hide();
//funcion limpiar
function limpiar() {
	$("#idventa").val("");
	$("#idcliente").val("");
	$("#cliente").val("");
	$("#serie_comprobante").val("");
	$("#num_comprobante").val("");
	$("#impuesto").val("");
	$("#total_venta").val("");
	$(".filas").remove();
	$("#total").html("0");
	$("#tpagado").val("");
	//marcamos el primer tipo_documento
	//$("#tipo_comprobante").selectpicker("refresh");
	//$("#idcliente").selectpicker("refresh");

	$("#nombre").val("");
	$("#num_documento").val("");
	$("#direccion").val("");
	$("#telefono").val("");
	$("#email").val("");
	$("#idpersona").val("");
	$("#Modalcliente").modal("hide");
	// $("#detalles").append("");

	detalles = 0;
	evaluar();
	$("#btnGuardar").prop("disabled", false);
}
//__________________________________________________________________________
//mostramos el num_comprobante de la fatura

function ShowComprobante() {
	//mostrar_impuesto();
	var tipo_comprobante = $("#tipo_comprobante").val();
	if (tipo_comprobante.length == 0) {
		$("#serie_comprobante").val("");
		$("#num_comprobante").val("");
	} else {
		serie_comp();
		numero_comp();
	}
}

function ShowTipopago() {
    var tipo_pago = document.getElementById('tipo_pago').value;
    var pago_mixto = document.getElementById('pago_mixto');
    var pago_credito = document.getElementById('pago_credito');
    
    pago_mixto.style.display = 'none';
    pago_credito.style.display = 'none';

    if (tipo_pago == 'Mixto') {
        pago_mixto.style.display = 'block';
    } else if (tipo_pago == 'Credito') {
        pago_credito.style.display = 'block';
    }
}

$(document).ready(function() {
    $('#formulariocliente').on('submit', function(e) {
        e.preventDefault(); // Prevenir el comportamiento predeterminado del formulario

        var formData = $(this).serialize();

        $.ajax({
            type: 'POST',
            url: 'ruta_a_tu_endpoint_para_guardar_cliente', // Reemplaza con la URL de tu endpoint
            data: formData,
            success: function(response) {
                // Suponiendo que la respuesta contiene los datos del nuevo cliente en formato JSON
                var cliente = response.cliente;
                var newOption = new Option(cliente.nombre, cliente.id, true, true);
                $('#clientes').append(newOption).trigger('change');

                // Cerrar el modal
                $('#Modalcliente').modal('hide');

                // Reiniciar el formulario
                $('#formulariocliente')[0].reset();
            },
            error: function(error) {
                console.log('Error:', error);
                // Manejo de errores
            }
        });
    });
});

//mostramos la serie del comprobante
function serie_comp() {
	var tipo_comprobante = $("#tipo_comprobante").val();

	$.post(
		"Controllers/Sell.php?op=mostrar_serie",
		{ tipo_comprobante: tipo_comprobante },
		function (data, status) {
			data = JSON.parse(data);
			//console.log(data);
			$("#serie_comprobante").val(data.letra + ("000" + data.serie).slice(-3)); // "0001"
		}
	);
}

//mostramos el numero de comprobante
function numero_comp() {
	var tipo_comprobante = $("#tipo_comprobante").val();
	$.ajax({
		url: "Controllers/Sell.php?op=mostrar_numero",
		data: { tipo_comprobante: tipo_comprobante },
		type: "get",
		dataType: "json",
		success: function (d) {
			num_comp = d;
			$("#num_comprobante").val(("0000000" + num_comp).slice(-7)); // "0001"
			$("#nFacturas").html(("0000000" + num_comp).slice(-7)); // "0001"
		},
	});
}

/*$("#aplicar_impuesto").change(function () {
  if ($("#aplicar_impuesto").is(":checked")) {
    mostrar_impuesto();
  } else {
    mostrar_impuesto();
  }
});*/
//mostramos el impuesto
var no_aplica = 0;
function mostrar_impuesto() {
	$.ajax({
		url: "Controllers/Company.php?op=mostrar_impuesto",
		type: "get",
		dataType: "json",
		success: function (i) {
			var impuesto = i;
			var sin_imp = 0;
			if ($("#aplicar_impuesto").is(":checked")) {
				$("#impuesto").val(impuesto);
				no_aplica = impuesto;
				calcularTotales();
			} else {
				$("#impuesto").val(sin_imp);
				no_aplica = 0;
				calcularTotales();
			}
		},
	});
}

//declaramos variables necesarias para trabajar con las compras y sus detalles

//_______________________________________________________________________________________________

function listarArticulos() {
	tabla = $("#tblarticulos")
		.dataTable({
			aProcessing: true, //activamos el procedimiento del datatable
			aServerSide: true, //paginacion y filrado realizados por el server
			dom: "Bfrtip", //definimos los elementos del control de la tabla
			buttons: [],
			ajax: {
				url: "Controllers/Sell.php?op=listarArticulos",
				type: "get",
				dataType: "json",
				error: function (e) {
					console.log(e.responseText);
				},
			},
			bDestroy: true,
			iDisplayLength: 10, //paginacion
			order: [[0, "desc"]], //ordenar (columna, orden)
		})
		.DataTable();
	//alert( 'Rows '+tabla.rows( '.selected' ).count()+' are selected' );
}

function guardaryeditar(e) {
    e.preventDefault();
    $("#btnGuardar").prop("disabled", true);
    var formData = new FormData($("#formulario")[0]);

    $.ajax({
        url: "Controllers/Sell.php?op=guardaryeditar",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (datos) {
            let data;
            try {
                data = JSON.parse(datos);
            } catch (e) {
                Swal.fire("Error", "Respuesta inesperada: " + datos, "error");
                $("#btnGuardar").prop("disabled", false);
                return;
            }

            if (data.success) {
                // Extraer celular sin 51
                let celularBase = data.celular ? data.celular.replace(/^51/, '') : '';
				Swal.fire({
					title: 'Venta registrada',
					html: `
						<p>¿Qué deseas hacer ahora?</p>
						<div style="display: flex; align-items: center; justify-content:center;">
							<input id="swal-prefix" class="swal2-input" style="width:55px; text-align:center; margin-right:5px; font-weight:bold;" value="51" readonly>
							<input id="swal-input-cel" class="swal2-input" style="width:180px;" maxlength="9" placeholder="Celular" value="${celularBase}">
						</div>
						
					`,
					icon: 'success',
					showDenyButton: true,
					showCancelButton: true,
					confirmButtonText: 'Imprimir',
					denyButtonText: 'Enviar WhatsApp',
					cancelButtonText: 'Cerrar',
					preConfirm: () => {
						return document.getElementById('swal-input-cel').value.trim();
					}
                }).then((result) => {
                    let celularSinPrefijo = document.getElementById('swal-input-cel').value.trim();
                    limpiar();
                    if (typeof listarArticulos === 'function') listarArticulos();
                    $("#btnGuardar").prop("disabled", false);

                    if (result.isConfirmed) {
                        window.open('Reports/80mm.php?id=' + data.idventa, '_blank');
                    } else if (result.isDenied) {
                        // Validación simple: 9 dígitos numéricos
                        if (!/^\d{9}$/.test(celularSinPrefijo)) {
                            Swal.fire('Número inválido', 'Ingrese los 9 dígitos del celular', 'warning');
                        } else {
                            let celularCompleto = '51' + celularSinPrefijo;
                            let urlPDF = location.origin + "/Reports/80mm.php?id=" + data.idventa;
                            let whatsappLink = `https://wa.me/${celularCompleto}?text=${encodeURIComponent('Hola ' + (data.nombre || '') + ', aquí está tu comprobante de venta: ' + urlPDF)}`;
                            window.open(whatsappLink, '_blank');
                        }
                    }
                });
            } else {
                Swal.fire("Error", data.mensaje, "error");
                $("#btnGuardar").prop("disabled", false);
            }
        },
        error: function () {
            Swal.fire("Error", "No se pudo conectar con el servidor.", "error");
            $("#btnGuardar").prop("disabled", false);
        }
    });
}



//funcion para anular
function anular(idventa) {
	swal({
		title: "Anular?",
		text: "Esá seguro de anular venta?",
		icon: "warning",
		buttons: {
			cancel: "No, cancelar",
			confirm: "Si, anular",
		},
		//buttons: true,
		dangerMode: true,
	}).then((willDelete) => {
		if (willDelete) {
			$.post(
				"Controllers/Sell.php?op=anular",
				{ idventa: idventa },
				function (e) {
					swal(e, "Desactivado!", {
						icon: "success",
					});
					var tabla = $("#tbllistado").DataTable();
					tabla.ajax.reload();
				}
			);
		}
	});
}

var numero_cantidad = 0;
function agregarDetalle(
	idingreso,
	idarticulo,
	articulo,
	precio_compra,
	precio_venta,
	cantidad,
	op
) {
	var stock = cantidad;
	if (op === 1) {
		numero_cantidad = 1;
	} else {
		numero_cantidad = cantidad;
	}
	//op === '1' ? (numero_cantidad = 1) : (numero_cantidad = cantidad);
	var descuento = 0;

	if (idarticulo != "") {
		var subtotal = cantidad * precio_venta;
		var fila =
			'<tr class="filas" id="fila' +
			cont +
			'">' +
			'<td class=""><button type="button" id="del" class="btn btn-danger btn-sm del" onclick="eliminarDetalle(' +
			cont +
			')"><i class="fa fa-times"></i></button></td>' +
			'<td class="col-xs-6"><input style="width : 70px;" type="hidden" name="idingreso[]" value="' +
			idingreso +
			'"><input style="width : 70px;" type="hidden" name="idarticulo[]" value="' +
			idarticulo +
			'"><input style="width : 70px;" type="hidden" name="precio_compra[]" value="' +
			precio_compra +
			'">' +
			articulo +
			'<td class="col-xs-1"><input style="width : 70px;" type="number" min="1" max="' +
			stock +
			'" onchange="ver_stock(this.value,' +
			stock +
			')" name="cantidad[]" id="cantidad[]" value="' +
			numero_cantidad +
			'"></td>' +
			'<td class="col-xs-1"><input style="width : 70px;" type="number" min="1" step="0.01" onchange="modificarSubtotales()" name="precio_venta[]" id="precio_venta[]" value="' +
			precio_venta +
			'"></td>' +
			'<td class="col-xs-1"><input style="width : 70px;" type="number" min="0" step="0.01" onchange="modificarSubtotales()" name="descuento[]" value="' +
			descuento +
			'"></td>' +
			'<td class="col-xs-1"><span id="subtotal' +
			cont +
			'" name="subtotal">' +
			subtotal +
			"</span></td>" +
			"</tr>";
		var product = null;
		var shelf = null;
		var status = null;

		//submit

		cont++;
		detalles++;
		$("#detalles").append(fila);
		modificarSubtotales();
	} else {
		alert("error al ingresar el detalle, revisar las datos del articulo ");
	}
}

//borrar filas del datables
function borrar_filas() {
	$('#tblarticulos tbody tr[role="row"] #addetalle').prop("disabled", false);
	for (let i = 0; i < $(".filas").length; i++) {
		const element = $('input[name="idarticulo[]"]').get(i);
		for (let f = 0; f < $('#tblarticulos tbody tr[role="row"]').length; f++) {
			const button = $('#tblarticulos tbody tr[role="row"] #addetalle').get(f);
			if (button["name"] === element["value"]) {
				button["disabled"] = true;
			}
		}
	}
}

//esta funcion valida la cantidad a vender con el stock
function ver_stock(valor, cantidad) {
	//alert(cantidad);
	var msj = "la cantidad supera al stock actual";
	valor = parseInt(valor);
	if (valor > cantidad) {
		swal({
			title: "Insuficiente",
			text: msj + " (" + cantidad + ")",
			icon: "warning",
			buttons: {
				confirm: "OK",
			},
		}),
			$("#btnGuardar").hide();
	} else {
		$("#btnGuardar").show();
		modificarSubtotales();
	}
}

function modificarSubtotales() {
	var cant = document.getElementsByName("cantidad[]");
	var prev = document.getElementsByName("precio_venta[]");
	var desc = document.getElementsByName("descuento[]");
	var sub = document.getElementsByName("subtotal");

	for (var i = 0; i < cant.length; i++) {
		var inpV = cant[i];
		var inpP = prev[i];
		var inpS = sub[i];
		var des = desc[i];

		inpS.value = inpV.value * inpP.value - des.value;
		document.getElementsByName("subtotal")[i].innerHTML = inpS.value.toFixed(2);
	}

	calcularTotales();
}

// Conclusión
(function () {
	function decimalAdjust(type, value, exp) {
		// Si el exp no está definido o es cero...
		if (typeof exp === "undefined" || +exp === 0) {
			return Math[type](value);
		}
		value = +value;
		exp = +exp;
		// Si el valor no es un número o el exp no es un entero...
		if (isNaN(value) || !(typeof exp === "number" && exp % 1 === 0)) {
			return NaN;
		}
		// Shift
		value = value.toString().split("e");
		value = Math[type](+(value[0] + "e" + (value[1] ? +value[1] - exp : -exp)));
		// Shift back
		value = value.toString().split("e");
		return +(value[0] + "e" + (value[1] ? +value[1] + exp : exp));
	}

	// Decimal ceil
	if (!Math.ceil10) {
		Math.ceil10 = function (value, exp) {
			return decimalAdjust("ceil", value, exp);
		};
	}
})();

function calcularTotales() {
    var sub = document.getElementsByName("subtotal");
    var total = 0.0;
    var total_monto = 0.0;
    var igv = 0.0;
    var simbolo = "";

    for (var i = 0; i < sub.length; i++) {
        total += parseFloat(document.getElementsByName("subtotal")[i].value);
    }

    // Calcular el IGV como el 18% del total
    igv = total * 0.18;

    // Calcular el subtotal restando el IGV del total
    var subtotal = total - igv;

    // Calcular el total monto, que es la suma del subtotal y el IGV
    total_monto = total;

    $.ajax({
        url: "Controllers/Company.php?op=mostrar_simbolo",
        type: "get",
        dataType: "json",
        success: function (sim) {
            simbolo = sim;
            $("#total").html(simbolo + subtotal.toFixed(2));
            $("#total_venta").val(total.toFixed(2));

            $("#most_total").html(simbolo + total_monto.toFixed(2));
            $("#most_imp").html(simbolo + igv.toFixed(2));

            var tpagado = $("#tpagado").val();
            var totalvuelto = 0;

            if (tpagado > 0) {
                totalvuelto = tpagado - total_monto;
                $("#vuelto").html(simbolo + " " + parseFloat(totalvuelto).toFixed(2));
            } else {
                totalvuelto = 0.0;
                $("#vuelto").html(simbolo + " " + parseFloat(totalvuelto).toFixed(2));
            }

            evaluar();
        },
    });
    borrar_filas();
}


function evaluar() {
	if (detalles > 0) {
		$("#btnGuardar").show();
	} else {
		$("#btnGuardar").hide();
		cont = 0;
	}
}

function eliminarDetalle(indice) {
	$("#fila" + indice).remove();
	calcularTotales();
	detalles = detalles - 1;
}

//funcion para guardar nuevo cliente
function agregarCliente(e) {
	$("#Modalcliente").modal("show");
	e.preventDefault(); //no se activara la accion predeterminada
	$("#btnGuardarcliente").prop("disabled", true);
	var formData = new FormData($("#formulariocliente")[0]);

	$.ajax({
		url: "Controllers/Person.php?op=guardaryeditar",
		type: "POST",
		data: formData,
		contentType: false,
		processData: false,
		success: function (datos) {
			var tabla = $("#tbllistado").DataTable();
			swal({
				title: "Registro",
				text: datos,
				icon: "info",
				buttons: {
					confirm: "OK",
				},
			}),
				mostrarform(false);
			tabla.ajax.reload();
		},
	});

	limpiar();
	location.reload(true);
}

function consultarCliente() {
    var tipo_documento = $('#tipo_documento').val();
    var num_documento = $('#num_documento').val();

    $.ajax({
        url: 'Controllers/Person.php?op=getCustomerByDocument',
        type: 'POST',
        data: { tipo_documento: tipo_documento, num_documento: num_documento },
        success: function(response) {
            var data;
            try {
                data = JSON.parse(response);
            } catch (e) {
                alert('Error al procesar la respuesta del servidor.');
                return;
            }

            if (data.estado) {
                // Cliente encontrado en la BD, llena los campos
                $("#nombre").val(data.resultado.nombre || '');
                $("#direccion").val(data.resultado.direccion || '');
                $("#idpersona").val(data.resultado.idpersona || '');
            } else {
                // Cliente NO encontrado en la BD, preguntar con SweetAlert
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
        error: function() {
            alert('Error al consultar el cliente en la base de datos.');
        }
    });
}

function consultarClienteReniec(tipo_documento, num_documento) {
    if (!num_documento || num_documento.trim() === "") {
        Swal.fire("Error", "Debe ingresar un número de documento válido", "error");
        return;
    }
    // Ahora sí, llamada AJAX
    $.ajax({
        url: 'Controllers/Person.php?op=getCustomerInfo',
        type: 'POST',
        data: { tipo_documento: tipo_documento, num_documento: num_documento },
        success: function(response) {
            var data;
            try {
                data = JSON.parse(response);
            } catch (e) {
                Swal.fire('Error', 'Error al procesar la respuesta del servidor.', 'error');
                return;
            }

            if (data.estado) {
                // Si existe en RENIEC o SUNAT, llena los campos
                if (tipo_documento === 'RUC') {
                    $("#nombre").val(data.resultado.razon_social || '');
                } else if (tipo_documento === 'DNI') {
                    $("#nombre").val(data.resultado.nombre || '');
                }
                $("#direccion").val(data.resultado.direccion || '');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'No encontrado',
                    text: data.mensaje || 'No se encontró información del documento.'
                });
            }
        },
        error: function() {
            Swal.fire('Error', 'Error al consultar la RENIEC/SUNAT.', 'error');
        }
    });
}


init();
