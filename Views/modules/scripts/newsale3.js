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
let inventarioBusquedaPedidoCache = [];
let inventarioBusquedaPedidoCargado = false;
let cargandoInventarioBusquedaPedido = false;
let solicitudInventarioBusquedaPedido = null;
let resultadosBusquedaPedidoCache = [];
let temporizadorBusquedaPedido = null;
let indiceResultadoBusquedaPedido = -1;
let configuracionVentaPredeterminadaCache = null;
let configuracionVentaPredeterminadaCargada = false;
let configuracionTributariaVentaCache = {
    tipo_operacion_sunat: '0101',
    codigo_afectacion_igv: '10',
    porcentaje_igv: 18,
    unidad_medida_sunat: 'NIU',
    permitir_cambio_afectacion_venta: 0,
    precios_incluyen_impuesto: 1,
    moneda_codigo: 'PEN',
    simbolo: 'S/'
};
let tiposOperacionSunatCache = [];
let configuracionTributariaVentaCargada = false;

const MODO_DUPLICACION_INICIAL = (() => {
    const parametros = new URLSearchParams(
        window.location.search
    );

    return (
        Number.parseInt(
            parametros.get('duplicar'),
            10
        ) || 0
    ) > 0;
})();

const ESTADO_ESCANER = {
    buffer: '',
    inicio: 0,
    ultimo: 0,
    temporizador: null
};

const ESTADO_CAMARA = {
    instancia: null,
    iniciando: false,
    activa: false,
    procesando: false
};

const CLIENTE_GENERICO = Object.freeze({
    tipoDocumento: 'DNI',
    numeroDocumento: '99999999',
    nombre: 'CLIENTE VARIOS',
    direccion: '-'
});


/*
|--------------------------------------------------------------------------
| FECHA DE EMISIÓN - SELECTOR VISUAL
|--------------------------------------------------------------------------
| Se evita el datepicker nativo de iOS/Android porque cambia de tamaño y
| alineación según el navegador. El valor real sigue viajando en formato
| YYYY-MM-DD mediante #fecha_emision.
*/
const ESTADO_FECHA_EMISION = {
    vista: null
};

function obtenerFechaLocalISO(fecha = new Date()) {
    return [
        fecha.getFullYear(),
        String(fecha.getMonth() + 1).padStart(2, '0'),
        String(fecha.getDate()).padStart(2, '0')
    ].join('-');
}

function fechaISOValida(valor) {
    return /^\d{4}-\d{2}-\d{2}$/.test(String(valor || '').trim());
}

function fechaISOADateLocal(valor) {
    if (!fechaISOValida(valor)) {
        return null;
    }

    const [anio, mes, dia] = String(valor)
        .split('-')
        .map(Number);

    const fecha = new Date(anio, mes - 1, dia);

    if (
        fecha.getFullYear() !== anio
        || fecha.getMonth() !== mes - 1
        || fecha.getDate() !== dia
    ) {
        return null;
    }

    return fecha;
}

function formatearFechaEmision(valor, modo = 'corto') {
    const fecha = fechaISOADateLocal(valor);

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

    return new Intl.DateTimeFormat('es-PE', opciones)
        .format(fecha)
        .replace(/\.$/, '');
}

function obtenerMaxFechaEmisionISO() {
    const $input = $('#fecha_emision');
    const maximo = String(
        $input.attr('data-max')
        || obtenerFechaLocalISO()
    ).trim();

    return fechaISOValida(maximo)
        ? maximo
        : obtenerFechaLocalISO();
}

function sincronizarFechaEmisionVisual() {
    const $input = $('#fecha_emision');

    if (!$input.length) {
        return;
    }

    let valor = String($input.val() || '').trim();
    const maximo = obtenerMaxFechaEmisionISO();

    if (!fechaISOValida(valor)) {
        valor = maximo;
        $input.val(valor);
    }

    if (valor > maximo) {
        valor = maximo;
        $input.val(valor);
    }

    $('#fechaEmisionTexto').text(
        formatearFechaEmision(valor, 'corto')
    );

    $('#fechaEmisionSeleccionResumen').text(
        formatearFechaEmision(valor, 'largo')
    );
}

function renderizarCalendarioFechaEmision() {
    const contenedor = document.getElementById('fechaEmisionDias');

    if (!contenedor) {
        return;
    }

    const valorSeleccionado = String(
        $('#fecha_emision').val() || obtenerMaxFechaEmisionISO()
    ).trim();

    const fechaSeleccionada =
        fechaISOADateLocal(valorSeleccionado)
        || fechaISOADateLocal(obtenerMaxFechaEmisionISO())
        || new Date();

    const fechaMaxima =
        fechaISOADateLocal(obtenerMaxFechaEmisionISO())
        || new Date();

    if (!(ESTADO_FECHA_EMISION.vista instanceof Date)) {
        ESTADO_FECHA_EMISION.vista = new Date(
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

    if (ESTADO_FECHA_EMISION.vista > limiteMes) {
        ESTADO_FECHA_EMISION.vista = new Date(limiteMes);
    }

    const anio = ESTADO_FECHA_EMISION.vista.getFullYear();
    const mes = ESTADO_FECHA_EMISION.vista.getMonth();
    const primerDia = new Date(anio, mes, 1);
    const ultimoDiaMes = new Date(anio, mes + 1, 0).getDate();

    /* JS: domingo=0. La grilla visual comienza en lunes. */
    const desplazamiento = (primerDia.getDay() + 6) % 7;
    const hoyISO = obtenerFechaLocalISO();

    $('#fechaEmisionMesTitulo').text(
        new Intl.DateTimeFormat('es-PE', {
            month: 'long',
            year: 'numeric'
        }).format(primerDia)
    );

    const fragmento = document.createDocumentFragment();

    for (let celda = 0; celda < 42; celda += 1) {
        const numeroDia = celda - desplazamiento + 1;

        if (numeroDia < 1 || numeroDia > ultimoDiaMes) {
            const vacio = document.createElement('span');
            vacio.className = 'venta-calendario-dia is-empty';
            vacio.setAttribute('aria-hidden', 'true');
            fragmento.appendChild(vacio);
            continue;
        }

        const fechaCelda = new Date(anio, mes, numeroDia);
        const fechaISO = obtenerFechaLocalISO(fechaCelda);
        const esFutura = fechaCelda > fechaMaxima;
        const esSeleccionada = fechaISO === valorSeleccionado;
        const esHoy = fechaISO === hoyISO;

        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'venta-calendario-dia';
        boton.textContent = String(numeroDia);
        boton.dataset.fecha = fechaISO;
        boton.setAttribute('role', 'gridcell');
        boton.setAttribute(
            'aria-label',
            formatearFechaEmision(fechaISO, 'largo')
        );

        if (esSeleccionada) {
            boton.classList.add('is-selected');
            boton.setAttribute('aria-selected', 'true');
        }

        if (esHoy) {
            boton.classList.add('is-today');
        }

        if (esFutura) {
            boton.classList.add('is-disabled');
            boton.disabled = true;
            boton.setAttribute('aria-disabled', 'true');
        }

        fragmento.appendChild(boton);
    }

    contenedor.replaceChildren(fragmento);

    const siguiente = document.getElementById('btnFechaEmisionSiguiente');

    if (siguiente) {
        const mesSiguiente = new Date(anio, mes + 1, 1);
        const deshabilitar = mesSiguiente > limiteMes;

        siguiente.disabled = deshabilitar;
        siguiente.setAttribute(
            'aria-disabled',
            deshabilitar ? 'true' : 'false'
        );
    }

    sincronizarFechaEmisionVisual();
}

function abrirSelectorFechaEmision() {
    const valor = String(
        $('#fecha_emision').val() || obtenerMaxFechaEmisionISO()
    ).trim();

    const fecha =
        fechaISOADateLocal(valor)
        || fechaISOADateLocal(obtenerMaxFechaEmisionISO())
        || new Date();

    ESTADO_FECHA_EMISION.vista = new Date(
        fecha.getFullYear(),
        fecha.getMonth(),
        1
    );

    renderizarCalendarioFechaEmision();
    $('#modalFechaEmision').modal('show');
}

function inicializarSelectorFechaEmision() {
    const $input = $('#fecha_emision');

    if (!$input.length || !$('#btnFechaEmision').length) {
        return;
    }

    const maximo = obtenerFechaLocalISO();

    $input.attr('data-max', maximo);

    if (!fechaISOValida($input.val())) {
        $input.val(maximo);
    }

    sincronizarFechaEmisionVisual();

    $(document)
        .off('click.fechaEmisionAbrir', '#btnFechaEmision')
        .on('click.fechaEmisionAbrir', '#btnFechaEmision', function () {
            abrirSelectorFechaEmision();
        })
        .off('change.fechaEmision', '#fecha_emision')
        .on('change.fechaEmision', '#fecha_emision', function () {
            sincronizarFechaEmisionVisual();
        })
        .off('click.fechaEmisionDia', '#fechaEmisionDias [data-fecha]')
        .on('click.fechaEmisionDia', '#fechaEmisionDias [data-fecha]', function () {
            if (this.disabled) {
                return;
            }

            const fecha = String(this.dataset.fecha || '').trim();

            if (!fechaISOValida(fecha)) {
                return;
            }

            $input
                .val(fecha)
                .trigger('change');

            $('#modalFechaEmision').modal('hide');
        })
        .off('click.fechaEmisionAnterior', '#btnFechaEmisionAnterior')
        .on('click.fechaEmisionAnterior', '#btnFechaEmisionAnterior', function () {
            if (!(ESTADO_FECHA_EMISION.vista instanceof Date)) {
                return;
            }

            ESTADO_FECHA_EMISION.vista = new Date(
                ESTADO_FECHA_EMISION.vista.getFullYear(),
                ESTADO_FECHA_EMISION.vista.getMonth() - 1,
                1
            );

            renderizarCalendarioFechaEmision();
        })
        .off('click.fechaEmisionSiguiente', '#btnFechaEmisionSiguiente')
        .on('click.fechaEmisionSiguiente', '#btnFechaEmisionSiguiente', function () {
            if (this.disabled || !(ESTADO_FECHA_EMISION.vista instanceof Date)) {
                return;
            }

            ESTADO_FECHA_EMISION.vista = new Date(
                ESTADO_FECHA_EMISION.vista.getFullYear(),
                ESTADO_FECHA_EMISION.vista.getMonth() + 1,
                1
            );

            renderizarCalendarioFechaEmision();
        })
        .off('click.fechaEmisionHoy', '#btnFechaEmisionHoy')
        .on('click.fechaEmisionHoy', '#btnFechaEmisionHoy', function () {
            const hoy = obtenerMaxFechaEmisionISO();

            $input
                .val(hoy)
                .trigger('change');

            $('#modalFechaEmision').modal('hide');
        });

    $('#modalFechaEmision')
        .off('shown.bs.modal.fechaEmision')
        .on('shown.bs.modal.fechaEmision', function () {
            const seleccionado = this.querySelector(
                '.venta-calendario-dia.is-selected:not(:disabled)'
            );

            if (seleccionado) {
                seleccionado.focus({ preventScroll: true });
            }
        });
}

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
    sincronizarDireccionVisible();
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
    sincronizarDireccionVisible();
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
        if (esBoletaSeleccionada() && totalVentaParaReglaSunatPEN(totalVenta) > 700) {
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
        && totalVentaParaReglaSunatPEN(totalVenta) > 700
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
    /*
     * El pedido se administra localmente por cada pestaña de venta.
     * No se carga un carrito global del servidor.
     */
    $('#detallesCards').empty();
    actualizarMensajePedido();
    inicializarEscanerProductos();
    cargarFormasPagoMixto();
    inicializarBuscadorPedido();
    inicializarConfiguracionVentaPredeterminada();
    inicializarConfiguracionTributariaVenta();
    inicializarSwitchVentaResponsive();
    inicializarAjustesCamposVenta();
    inicializarSelectorFechaEmision();

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




/*
 |--------------------------------------------------------------------------
 | AJUSTES DE CAMPOS DE NUEVA VENTA
 |--------------------------------------------------------------------------
 | Los switches son la fuente visual del formulario y cada cambio se guarda
 | automáticamente en datos_negocio.venta_campos_visibles.
 */
const CAMPOS_VENTA_PREDETERMINADOS = Object.freeze({
    tipo_comprobante: 1,
    cliente: 1,
    direccion: 0,
    tipo_pago: 1,
    forma_pago: 1,
    celular: 1,
    fecha_emision: 0,
    tipo_operacion_sunat: 1,
    descuento: 1,
    envio_comprobante: 1
});

let configuracionCamposVenta = {
    ...CAMPOS_VENTA_PREDETERMINADOS
};
let configuracionCamposVentaPersistida = {
    ...CAMPOS_VENTA_PREDETERMINADOS
};
let temporizadorGuardadoCamposVenta = null;
let guardandoCamposVenta = false;
let guardadoCamposVentaPendiente = false;

function normalizarConfiguracionCamposVenta(configuracion) {
    const salida = {
        ...CAMPOS_VENTA_PREDETERMINADOS
    };

    if (configuracion && typeof configuracion === 'object') {
        Object.keys(salida).forEach(function (clave) {
            if (['tipo_comprobante', 'cliente'].includes(clave)) {
                salida[clave] = 1;
                return;
            }

            if (Object.prototype.hasOwnProperty.call(configuracion, clave)) {
                salida[clave] = Number(configuracion[clave]) === 1
                    || configuracion[clave] === true
                    ? 1
                    : 0;
            }
        });
    }

    salida.tipo_comprobante = 1;
    salida.cliente = 1;

    return salida;
}

function aplicarConfiguracionCamposVenta(configuracion) {
    configuracionCamposVenta = normalizarConfiguracionCamposVenta(
        configuracion
    );

    $('[data-venta-campo]').each(function () {
        const clave = String($(this).attr('data-venta-campo') || '');
        const visible = Number(configuracionCamposVenta[clave] ?? 1) === 1;
        const $campo = $(this);

        /*
         * No dependemos solamente de una clase CSS. Se sincronizan
         * simultáneamente hidden, aria-hidden, display y la clase visual.
         * Así OFF siempre significa oculto y ON siempre significa visible,
         * incluso si Bootstrap/Stisla aplica reglas de display posteriores.
         */
        this.hidden = !visible;
        $campo
            .toggleClass('is-hidden', !visible)
            .attr('aria-hidden', visible ? 'false' : 'true');

        if (visible) {
            $campo.removeAttr('hidden').css('display', '');
        } else {
            $campo.attr('hidden', 'hidden').css('display', 'none');
        }
    });

    $('[data-campo-switch]').each(function () {
        const clave = String($(this).attr('data-campo-switch') || '');

        $(this).prop(
            'checked',
            Number(configuracionCamposVenta[clave] ?? 0) === 1
        );
    });
}

function obtenerConfiguracionCamposVentaDesdeSwitches() {
    const configuracion = {
        ...configuracionCamposVenta
    };

    $('[data-campo-switch]').each(function () {
        const clave = String($(this).attr('data-campo-switch') || '');

        if (clave && Object.prototype.hasOwnProperty.call(
            CAMPOS_VENTA_PREDETERMINADOS,
            clave
        )) {
            configuracion[clave] = $(this).is(':checked') ? 1 : 0;
        }
    });

    configuracion.tipo_comprobante = 1;
    configuracion.cliente = 1;

    return normalizarConfiguracionCamposVenta(configuracion);
}

function actualizarEstadoGuardadoCamposVenta(estado, texto) {
    const $contenedor = $('.venta-ajustes-autoguardado');
    const $texto = $('#estadoGuardadoAjustesVenta');

    $contenedor.removeClass('is-saving is-saved is-error');

    if (estado) {
        $contenedor.addClass('is-' + estado);
    }

    if ($texto.length) {
        $texto.text(
            texto || 'Los cambios se guardan automáticamente'
        );
    }
}

function abrirPanelAjustesVenta(abrir = true) {
    const $panel = $('#panelAjustesVenta');
    const $boton = $('#btnAjustesVenta');

    $panel.toggleClass('is-open', abrir)
        .attr('aria-hidden', abrir ? 'false' : 'true');

    $boton.attr('aria-expanded', abrir ? 'true' : 'false');
}

function cargarConfiguracionCamposVenta() {
    actualizarEstadoGuardadoCamposVenta(
        '',
        'Cargando configuración...'
    );

    $.ajax({
        url: 'Controllers/Company.php',
        type: 'GET',
        dataType: 'json',
        cache: false,
        data: {
            op: 'venta_campos_visibles',
            v: Date.now()
        }
    }).done(function (respuesta) {
        if (!respuesta || respuesta.success !== true) {
            aplicarConfiguracionCamposVenta(
                CAMPOS_VENTA_PREDETERMINADOS
            );
            configuracionCamposVentaPersistida = {
                ...configuracionCamposVenta
            };
            actualizarEstadoGuardadoCamposVenta(
                'error',
                'No se pudo cargar la configuración guardada'
            );
            return;
        }

        const configuracion = normalizarConfiguracionCamposVenta(
            respuesta.configuracion || {}
        );

        configuracionCamposVentaPersistida = {
            ...configuracion
        };

        aplicarConfiguracionCamposVenta(configuracion);
        actualizarEstadoGuardadoCamposVenta(
            'saved',
            'Configuración guardada'
        );
    }).fail(function () {
        aplicarConfiguracionCamposVenta(
            CAMPOS_VENTA_PREDETERMINADOS
        );
        configuracionCamposVentaPersistida = {
            ...configuracionCamposVenta
        };
        actualizarEstadoGuardadoCamposVenta(
            'error',
            'No se pudo cargar la configuración guardada'
        );
    });
}

function ejecutarGuardadoAutomaticoCamposVenta() {
    if (guardandoCamposVenta) {
        guardadoCamposVentaPendiente = true;
        return;
    }

    const configuracionAEnviar =
        obtenerConfiguracionCamposVentaDesdeSwitches();

    guardandoCamposVenta = true;
    actualizarEstadoGuardadoCamposVenta(
        'saving',
        'Guardando...'
    );

    $.ajax({
        url: 'Controllers/Company.php?op=guardar_venta_campos_visibles',
        type: 'POST',
        dataType: 'json',
        cache: false,
        data: {
            configuracion: JSON.stringify(configuracionAEnviar)
        }
    }).done(function (respuesta) {
        if (!respuesta || respuesta.success !== true) {
            if (!guardadoCamposVentaPendiente) {
                aplicarConfiguracionCamposVenta(
                    configuracionCamposVentaPersistida
                );
            }

            actualizarEstadoGuardadoCamposVenta(
                'error',
                respuesta && respuesta.mensaje
                    ? respuesta.mensaje
                    : 'No se pudo guardar'
            );

            Swal.fire(
                'No se guardó el ajuste',
                respuesta && respuesta.mensaje
                    ? respuesta.mensaje
                    : 'No se pudo guardar la configuración de Nueva Venta.',
                'error'
            );
            return;
        }

        const configuracionGuardada =
            normalizarConfiguracionCamposVenta(
                respuesta.configuracion || configuracionAEnviar
            );

        const configuracionEsperada = normalizarConfiguracionCamposVenta(
            configuracionAEnviar
        );

        const guardadoCoincide = Object.keys(
            CAMPOS_VENTA_PREDETERMINADOS
        ).every(function (clave) {
            return Number(configuracionGuardada[clave])
                === Number(configuracionEsperada[clave]);
        });

        if (!guardadoCoincide) {
            actualizarEstadoGuardadoCamposVenta(
                'error',
                'La base de datos no confirmó el cambio'
            );

            if (!guardadoCamposVentaPendiente) {
                aplicarConfiguracionCamposVenta(
                    configuracionCamposVentaPersistida
                );
            }

            Swal.fire(
                'No se confirmó el ajuste',
                'La base de datos devolvió una configuración distinta. El cambio no se considerará guardado.',
                'error'
            );
            return;
        }

        configuracionCamposVentaPersistida = {
            ...configuracionGuardada
        };

        /*
         * La respuesta del servidor es la fuente de verdad. Solo cuando
         * coincide exactamente con lo solicitado mostramos "Guardado".
         */
        if (!guardadoCamposVentaPendiente) {
            aplicarConfiguracionCamposVenta(
                configuracionGuardada
            );
        }

        actualizarEstadoGuardadoCamposVenta(
            'saved',
            'Guardado automáticamente'
        );
    }).fail(function (xhr) {
        if (!guardadoCamposVentaPendiente) {
            aplicarConfiguracionCamposVenta(
                configuracionCamposVentaPersistida
            );
        }

        const mensaje = xhr.responseJSON && xhr.responseJSON.mensaje
            ? xhr.responseJSON.mensaje
            : 'No se pudo guardar la configuración de Nueva Venta.';

        actualizarEstadoGuardadoCamposVenta(
            'error',
            'No se pudo guardar'
        );

        Swal.fire(
            'No se guardó el ajuste',
            mensaje,
            'error'
        );
    }).always(function () {
        guardandoCamposVenta = false;

        if (guardadoCamposVentaPendiente) {
            guardadoCamposVentaPendiente = false;
            ejecutarGuardadoAutomaticoCamposVenta();
        }
    });
}

function programarGuardadoAutomaticoCamposVenta() {
    actualizarEstadoGuardadoCamposVenta(
        'saving',
        'Guardando...'
    );

    /*
     * Si existe una petición en curso, no permitimos que su respuesta
     * reemplace un switch que el usuario acaba de mover. La última
     * configuración se enviará inmediatamente al terminar esa petición.
     */
    if (guardandoCamposVenta) {
        guardadoCamposVentaPendiente = true;
        return;
    }

    if (temporizadorGuardadoCamposVenta) {
        window.clearTimeout(
            temporizadorGuardadoCamposVenta
        );
    }

    temporizadorGuardadoCamposVenta = window.setTimeout(
        function () {
            temporizadorGuardadoCamposVenta = null;
            ejecutarGuardadoAutomaticoCamposVenta();
        },
        180
    );
}

function sincronizarDireccionVisible() {
    $('#direccion_visible').val(
        String($('#direccion').val() || '')
    );
}

/*
 * La moneda y el IGV se administran desde Configuración de empresa.
 * Nueva Venta solo consume esa configuración; no ofrece controles locales.
 */
function monedaVentaCodigo() {
    return String(
        configuracionTributariaVentaCache.moneda_codigo || 'PEN'
    ).trim().toUpperCase();
}

function simboloMonedaVenta() {
    const simboloConfigurado = String(
        configuracionTributariaVentaCache.simbolo || ''
    ).trim();

    if (simboloConfigurado) {
        return simboloConfigurado;
    }

    return monedaVentaCodigo() === 'USD'
        ? '$'
        : monedaVentaCodigo() === 'EUR'
            ? '€'
            : 'S/';
}

function tipoCambioVenta() {
    return 1;
}

function totalVentaParaReglaSunatPEN(totalVenta) {
    return Number(totalVenta || 0);
}

function actualizarMonedaVenta() {
    const simbolo = simboloMonedaVenta();

    /*
     * El símbolo vive dentro del control monetario. Los labels se mantienen
     * cortos para que Recibido y Vuelto ocupen solo el ancho necesario.
     */
    $('#prefijoRecibido').text(simbolo);
    $('#prefijoVuelto').text(simbolo);

    calcularTotales();
    recalcularCuotasCredito();
}

function validarDatosAdicionalesVenta() {
    sincronizarDireccionVisible();
    return true;
}

function inicializarAjustesCamposVenta() {
    /*
     * v4 lleva el motor de visibilidad/autoguardado inline en newsale3.php.
     * Esto evita conflictos con copias antiguas del JS y garantiza que
     * OFF oculte y ON muestre el campo inmediatamente.
     */
    if (window.__VENTA_CAMPOS_INLINE_V4__ === true) {
        return;
    }

    const hoy = new Date();
    const fechaLocal = [
        hoy.getFullYear(),
        String(hoy.getMonth() + 1).padStart(2, '0'),
        String(hoy.getDate()).padStart(2, '0')
    ].join('-');

    if ($('#fecha_emision').length) {
        $('#fecha_emision')
            .attr('data-max', fechaLocal)
            .val(fechaLocal)
            .trigger('change');
    }

    /*
     * Se aplican los predeterminados solo mientras llega la BD.
     * En cuanto responde Company.php, switches y campos se sincronizan.
     */
    aplicarConfiguracionCamposVenta(
        CAMPOS_VENTA_PREDETERMINADOS
    );
    cargarConfiguracionCamposVenta();
    sincronizarDireccionVisible();

    $(document)
        .off('click.ajustesVenta', '#btnAjustesVenta')
        .on('click.ajustesVenta', '#btnAjustesVenta', function (e) {
            e.stopPropagation();
            abrirPanelAjustesVenta(
                !$('#panelAjustesVenta').hasClass('is-open')
            );
        })
        .off('click.cerrarAjustesVenta', '#btnCerrarAjustesVenta')
        .on('click.cerrarAjustesVenta', '#btnCerrarAjustesVenta', function () {
            abrirPanelAjustesVenta(false);
        })
        .off('change.camposVentaAuto', '[data-campo-switch]')
        .on('change.camposVentaAuto', '[data-campo-switch]', function () {
            const clave = String(
                $(this).attr('data-campo-switch') || ''
            );

            if (!Object.prototype.hasOwnProperty.call(
                CAMPOS_VENTA_PREDETERMINADOS,
                clave
            )) {
                return;
            }

            configuracionCamposVenta[clave] =
                $(this).is(':checked') ? 1 : 0;

            /* Aparición/desaparición inmediata del campo. */
            aplicarConfiguracionCamposVenta(
                configuracionCamposVenta
            );

            /* Persistencia automática sin botón Guardar. */
            programarGuardadoAutomaticoCamposVenta();
        })
        .off('click.cerrarAjustesVentaFuera')
        .on('click.cerrarAjustesVentaFuera', function (e) {
            if ($(e.target).closest('.venta-ajustes-wrap').length === 0) {
                abrirPanelAjustesVenta(false);
            }
        })
        .off('input.direccionVenta', '#direccion_visible')
        .on('input.direccionVenta', '#direccion_visible', function () {
            $('#direccion').val($(this).val());
        });

    actualizarMonedaVenta();
}

/*
|--------------------------------------------------------------------------
| SWITCH RESPONSIVE: DATOS / PRODUCTOS
|--------------------------------------------------------------------------
| En móvil y tablet se muestra un solo panel a la vez. El selector queda
| fijo en la parte inferior y, al cambiar, la pantalla vuelve al inicio
| del área de venta para evitar desplazamientos largos.
*/
const MEDIA_SWITCH_VENTA = window.matchMedia('(max-width: 1199.98px)');
let panelVentaResponsiveActivo = 'datos';

function obtenerPanelVentaResponsive(nombrePanel) {
    return nombrePanel === 'productos'
        ? $('#ventaPanelProductos')
        : $('#ventaPanelDatos');
}

function desplazarVentaHaciaArriba() {
    const contenedor = document.querySelector('.venta-pos-layout');

    if (!contenedor) {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
        return;
    }

    const margenSuperior = 88;
    const posicion = Math.max(
        0,
        contenedor.getBoundingClientRect().top
            + window.pageYOffset
            - margenSuperior
    );

    window.scrollTo({
        top: posicion,
        behavior: 'smooth'
    });
}

function aplicarPanelVentaResponsive(
    nombrePanel,
    opciones = {}
) {
    const panelNormalizado = nombrePanel === 'productos'
        ? 'productos'
        : 'datos';

    panelVentaResponsiveActivo = panelNormalizado;

    const esResponsive = MEDIA_SWITCH_VENTA.matches;
    const $panelDatos = $('#ventaPanelDatos');
    const $panelProductos = $('#ventaPanelProductos');
    const $switch = $('.venta-mobile-switch');
    const $botones = $('.venta-mobile-switch-btn[data-venta-panel]');

    if (!esResponsive) {
        $('body').removeClass('venta-switch-responsive-activo');
        $switch.removeClass('is-productos');

        $panelDatos
            .addClass('venta-panel-activo')
            .removeAttr('aria-hidden');

        $panelProductos
            .addClass('venta-panel-activo')
            .removeAttr('aria-hidden');

        return;
    }

    $('body').addClass('venta-switch-responsive-activo');

    $switch.toggleClass(
        'is-productos',
        panelNormalizado === 'productos'
    );

    $panelDatos
        .toggleClass(
            'venta-panel-activo',
            panelNormalizado === 'datos'
        )
        .attr(
            'aria-hidden',
            panelNormalizado === 'datos' ? 'false' : 'true'
        );

    $panelProductos
        .toggleClass(
            'venta-panel-activo',
            panelNormalizado === 'productos'
        )
        .attr(
            'aria-hidden',
            panelNormalizado === 'productos' ? 'false' : 'true'
        );

    $botones.each(function () {
        const activo = String(
            $(this).attr('data-venta-panel') || ''
        ) === panelNormalizado;

        $(this)
            .toggleClass('active', activo)
            .attr('aria-selected', activo ? 'true' : 'false')
            .attr('tabindex', activo ? '0' : '-1');
    });

    if (opciones.desplazar === true) {
        window.requestAnimationFrame(function () {
            desplazarVentaHaciaArriba();
        });
    }

    if (
        opciones.enfocar === true
        && panelNormalizado === 'productos'
    ) {
        window.setTimeout(function () {
            $('#buscarProductoPedido').trigger('focus');
        }, 280);
    }
}

function inicializarSwitchVentaResponsive() {
    const $switch = $('#ventaMobileSwitchWrap');

    if (!$switch.length) {
        return;
    }

    $(document)
        .off('click.switchVentaResponsive', '.venta-mobile-switch-btn[data-venta-panel]')
        .on(
            'click.switchVentaResponsive',
            '.venta-mobile-switch-btn[data-venta-panel]',
            function () {
                const botonSwitch = this;
                const panelSolicitado = String(
                    $(botonSwitch).attr('data-venta-panel') || 'datos'
                );

                /*
                 * iOS/Safari puede conservar el foco visual después del toque.
                 * Se retira inmediatamente porque el deslizador ya comunica
                 * cuál opción está seleccionada.
                 */
                window.setTimeout(function () {
                    if (typeof botonSwitch.blur === 'function') {
                        botonSwitch.blur();
                    }
                }, 0);

                if (
                    MEDIA_SWITCH_VENTA.matches
                    && panelSolicitado === panelVentaResponsiveActivo
                ) {
                    return;
                }

                aplicarPanelVentaResponsive(
                    panelSolicitado,
                    {
                        desplazar: true,
                        enfocar: false
                    }
                );
            }
        );

    const manejarCambioBreakpoint = function () {
        aplicarPanelVentaResponsive(
            panelVentaResponsiveActivo,
            {
                desplazar: false,
                enfocar: false
            }
        );
    };

    if (typeof MEDIA_SWITCH_VENTA.addEventListener === 'function') {
        MEDIA_SWITCH_VENTA.addEventListener(
            'change',
            manejarCambioBreakpoint
        );
    } else if (typeof MEDIA_SWITCH_VENTA.addListener === 'function') {
        MEDIA_SWITCH_VENTA.addListener(
            manejarCambioBreakpoint
        );
    }

    aplicarPanelVentaResponsive(
        panelVentaResponsiveActivo,
        {
            desplazar: false,
            enfocar: false
        }
    );
}



/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN TRIBUTARIA EFECTIVA
|--------------------------------------------------------------------------
*/
function inicializarConfiguracionTributariaVenta() {
    $.ajax({
        url: 'Controllers/Sell.php?op=configuracionTributariaVenta',
        type: 'GET',
        dataType: 'json',
        cache: false
    }).done(function (respuesta) {
        if (!respuesta || respuesta.success !== true) {
            console.warn(
                'No se pudo cargar la configuración tributaria:',
                respuesta && respuesta.mensaje
            );
            return;
        }

        const configuracion = respuesta.configuracion || {};

        configuracionTributariaVentaCache = {
            ...configuracionTributariaVentaCache,
            ...configuracion,
            porcentaje_igv: Number(
                configuracion.porcentaje_igv
                ?? configuracionTributariaVentaCache.porcentaje_igv
            ),
            permitir_cambio_afectacion_venta: Number(
                configuracion.permitir_cambio_afectacion_venta || 0
            ),
            precios_incluyen_impuesto: Number(
                configuracion.precios_incluyen_impuesto ?? 1
            ) === 1 ? 1 : 0
        };

        tiposOperacionSunatCache = Array.isArray(respuesta.tipos_operacion)
            ? respuesta.tipos_operacion
            : [];

        configuracionTributariaVentaCargada = true;

        renderizarTiposOperacionSunat();
        actualizarVistaConfiguracionTributaria();

        if ($('#moneda_codigo').length && !$('#moneda_codigo').data('usuario-cambio')) {
            const monedaConfigurada = String(
                configuracionTributariaVentaCache.moneda_codigo || 'PEN'
            ).toUpperCase();
            if ($('#moneda_codigo option[value="' + monedaConfigurada + '"]').length) {
                $('#moneda_codigo').val(monedaConfigurada);
            }
        }

        actualizarMonedaVenta();
        calcularTotales();
    }).fail(function (xhr) {
        console.warn(
            'No se pudo cargar la configuración tributaria de la venta:',
            xhr.status,
            xhr.responseText
        );
    });

    $(document).on('change', '#tipo_operacion_sunat', function () {
        const opcion = $(this).find('option:selected');
        $('#ayudaTipoOperacionSunat').text(
            opcion.data('descripcion')
            || 'Tipo de operación que se declarará en el comprobante electrónico.'
        );
    });
}

function renderizarTiposOperacionSunat() {
    const $select = $('#tipo_operacion_sunat');

    if (!$select.length) {
        return;
    }

    $select.empty();

    const lista = tiposOperacionSunatCache.length > 0
        ? tiposOperacionSunatCache
        : [{
            codigo: configuracionTributariaVentaCache.tipo_operacion_sunat || '0101',
            descripcion: 'Venta interna'
        }];

    lista.forEach(function (tipo) {
        const codigo = String(tipo.codigo || '').trim();
        const descripcion = String(tipo.descripcion || '').trim();

        if (!codigo) {
            return;
        }

        $select.append(
            $('<option>', {
                value: codigo,
                text: codigo + ' — ' + descripcion
            }).attr('data-descripcion', descripcion)
        );
    });

    const predeterminado = String(
        configuracionTributariaVentaCache.tipo_operacion_sunat || '0101'
    );

    if ($select.find(`option[value="${predeterminado}"]`).length) {
        $select.val(predeterminado);
    }

    const permiteCambio = Number(
        configuracionTributariaVentaCache.permitir_cambio_afectacion_venta || 0
    ) === 1;

    $select.prop('disabled', !permiteCambio);

    if (!permiteCambio) {
        $('#ayudaTipoOperacionSunat').text(
            'Valor administrado desde Configuración tributaria de la empresa o sucursal.'
        );
    } else {
        $select.trigger('change');
    }
}

function actualizarVistaConfiguracionTributaria() {
    const incluye = Number(
        configuracionTributariaVentaCache.precios_incluyen_impuesto ?? 1
    ) === 1;

    /*
     * La configuración tributaria sigue siendo necesaria para los
     * cálculos y el backend, pero ya no se muestra como un resumen
     * adicional en el formulario.
     */
    $('#precios_incluyen_impuesto').val(
        incluye ? '1' : '0'
    );

    const afectacion = String(
        configuracionTributariaVentaCache.codigo_afectacion_igv || '10'
    );
    const porcentaje = Number(
        configuracionTributariaVentaCache.porcentaje_igv || 0
    );

    $('#igv_sunat_visual').val(
        etiquetaAfectacionIgv(afectacion, porcentaje)
    );
}

function etiquetaAfectacionIgv(codigo, porcentaje) {
    const codigoNormalizado = String(codigo || '10');
    const tasa = Number(porcentaje || 0);

    switch (codigoNormalizado) {
        case '20':
            return 'Exonerado';
        case '30':
            return 'Inafecto';
        case '40':
            return 'Exportación';
        case '10':
        default:
            return 'Gravado ' + tasa.toFixed(2).replace(/\.00$/, '') + '%';
    }
}

function claseAfectacionIgv(codigo) {
    return 'tax-' + String(codigo || '10').replace(/[^0-9]/g, '');
}

function calcularImporteBrutoTributario(cantidad, precio, codigo, porcentaje) {
    const importeEntrada = Math.max(
        Number(cantidad || 0) * Number(precio || 0),
        0
    );

    const incluye = Number(
        configuracionTributariaVentaCache.precios_incluyen_impuesto ?? 1
    ) === 1;

    if (!incluye && String(codigo) === '10') {
        return importeEntrada * (1 + (Number(porcentaje || 0) / 100));
    }

    return importeEntrada;
}

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN PREDETERMINADA DE NUEVA VENTA
|--------------------------------------------------------------------------
*/
function inicializarConfiguracionVentaPredeterminada() {
    $.ajax({
        url: 'Controllers/Company.php',
        type: 'GET',
        dataType: 'json',
        cache: false,
        data: {
            op: 'mostrar_datos',
            v: Date.now()
        }
    }).done(function (respuesta) {
        if (!respuesta || typeof respuesta !== 'object') {
            return;
        }

        configuracionVentaPredeterminadaCache = respuesta;
        configuracionVentaPredeterminadaCargada = true;

        esperarSelectoresVentaListos()
            .then(function () {
                aplicarConfiguracionVentaPredeterminada({
                    inicial: true
                });
            });
    }).fail(function (xhr) {
        console.warn(
            'No se cargaron los valores predeterminados de venta:',
            xhr.status,
            xhr.responseText
        );
    });
}

function esperarSelectoresVentaListos() {
    return new Promise(function (resolve) {
        let intentos = 0;
        const maximoIntentos = 100;

        const temporizador = window.setInterval(function () {
            intentos += 1;

            const listos =
                $('#tipo_comprobante option').length > 1
                && $('#tipo_pago option').length > 1
                && $('#forma_pago option').length > 1;

            if (listos || intentos >= maximoIntentos) {
                window.clearInterval(temporizador);
                resolve(listos);
            }
        }, 100);
    });
}

function existeSolicitudDuplicacion() {
    return MODO_DUPLICACION_INICIAL;
}

function seleccionarOpcionVentaFlexible(
    selector,
    valor
) {
    const $select = $(selector);
    const buscado = String(valor || '').trim();

    if (!$select.length || buscado === '') {
        return false;
    }

    const buscadoNormalizado = textoNormalizado(
        buscado
    );

    let valorEncontrado = '';

    $select.find('option').each(function () {
        const valorOpcion = String(
            $(this).val() || ''
        ).trim();

        const textoOpcion = String(
            $(this).text() || ''
        ).trim();

        if (
            valorOpcion === buscado
            || textoNormalizado(valorOpcion)
                === buscadoNormalizado
            || textoNormalizado(textoOpcion)
                === buscadoNormalizado
        ) {
            valorEncontrado = valorOpcion;
            return false;
        }
    });

    if (valorEncontrado === '') {
        return false;
    }

    $select
        .val(valorEncontrado)
        .trigger('change');

    return true;
}

function aplicarConfiguracionVentaPredeterminada(
    opciones = {}
) {
    if (
        !configuracionVentaPredeterminadaCargada
        || !configuracionVentaPredeterminadaCache
    ) {
        return false;
    }

    /*
     * En una duplicación, el comprobante original debe ganar.
     * Después de guardar sí se aplican los predeterminados para
     * preparar la siguiente venta normal.
     */
    if (
        opciones.inicial === true
        && existeSolicitudDuplicacion()
    ) {
        return false;
    }

    const configuracion =
        configuracionVentaPredeterminadaCache;

    seleccionarOpcionVentaFlexible(
        '#tipo_comprobante',
        configuracion
            .venta_tipo_comprobante_predeterminado
    );

    seleccionarOpcionVentaFlexible(
        '#tipo_pago',
        configuracion
            .venta_tipo_pago_predeterminado
    );

    const idFormaPago = Number.parseInt(
        configuracion
            .venta_idforma_pago_predeterminada,
        10
    ) || 0;

    if (
        idFormaPago > 0
        && $('#forma_pago option[value="' + idFormaPago + '"]').length
    ) {
        $('#forma_pago')
            .val(String(idFormaPago))
            .trigger('change');
    }

    const modoEnvio = String(
        configuracion
            .venta_modo_envio_predeterminado
        || ''
    ).trim().toLowerCase();

    if (
        ['inmediato', 'manual', 'resumen_diario'].includes(modoEnvio)
    ) {
        $('#modo_envio')
            .val(modoEnvio);

        actualizarDisponibilidadModoEnvioSunat({
            aplicarPredeterminado: true
        });
    }

    return true;
}

/*
|--------------------------------------------------------------------------
| MODO DE ENVÍO SUNAT
|--------------------------------------------------------------------------
| La preferencia nace en Configuración de Empresa.
| RESUMEN_DIARIO es válido únicamente para Boleta Electrónica.
*/
function obtenerModoEnvioPredeterminadoEmpresa() {
    const modo = String(
        configuracionVentaPredeterminadaCache
            ?.venta_modo_envio_predeterminado
        || ''
    ).trim().toLowerCase();

    return ['inmediato', 'manual', 'resumen_diario'].includes(modo)
        ? modo
        : '';
}

function actualizarMensajeModoEnvioSunat() {
    const modo = String(
        $('#modo_envio').val() || 'inmediato'
    ).trim().toLowerCase();

    if (modo === 'resumen_diario') {
        $('#mensajeModoEnvio').html(
            '<strong>Resumen Diario:</strong> ' +
            'la boleta se registrará normalmente y quedará pendiente para ser incluida en el Resumen Diario de Boletas. No se enviará individualmente.'
        );
        return;
    }

    if (modo === 'manual') {
        $('#mensajeModoEnvio').html(
            '<strong>Envío manual:</strong> ' +
            'la venta se registrará y reservará su correlativo, pero no será enviada a SUNAT. Podrá enviarla posteriormente desde Estado de Comprobantes SUNAT.'
        );
        return;
    }

    $('#mensajeModoEnvio').html(
        '<strong>Envío inmediato:</strong> ' +
        'la venta se registrará y será enviada automáticamente mediante APISUNAT.'
    );
}

function actualizarDisponibilidadModoEnvioSunat(opciones = {}) {
    const $select = $('#modo_envio');
    const $resumen = $select.find('option[value="resumen_diario"]');

    if (!$select.length || !$resumen.length) {
        return;
    }

    const esBoleta = esBoletaSeleccionada();
    const predeterminado = obtenerModoEnvioPredeterminadoEmpresa();
    const actual = String($select.val() || '').trim().toLowerCase();

    $resumen.prop('disabled', !esBoleta);

    if (!esBoleta && actual === 'resumen_diario') {
        const reemplazo = ['inmediato', 'manual'].includes(predeterminado)
            ? predeterminado
            : 'inmediato';

        $select.val(reemplazo);
    } else if (
        esBoleta
        && opciones.aplicarPredeterminado === true
        && predeterminado === 'resumen_diario'
    ) {
        $select.val('resumen_diario');
    }

    actualizarMensajeModoEnvioSunat();
}

$(document)
    .off('change.modoEnvioSunat', '#modo_envio')
    .on('change.modoEnvioSunat', '#modo_envio', function () {
        actualizarDisponibilidadModoEnvioSunat();
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
        actualizarDisponibilidadModoEnvioSunat({
            aplicarPredeterminado: true
        });
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
                sincronizarDireccionVisible();
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

        $('#monto_cuota').val(simboloMonedaVenta() + ' ' + monto.toFixed(2));
    });



    // Control del descuento: ACTIVADO = S/. | DESACTIVADO = %
    $('#descuentoSwitch').on('change', function () {

        const esMonto = $(this).is(':checked');

        if (esMonto) {
            $('#labelDescuento').text(simboloMonedaVenta());

            $('#descuentoPorcentaje')
                .prop('disabled', false)
                .removeAttr('max')
                .attr('step', '0.01')
                .attr('placeholder', '0');
        } else {
            $('#labelDescuento').text('%');

            $('#descuentoPorcentaje')
                .prop('disabled', false)
                .attr('max', 100)
                .attr('step', '0.1')
                .attr('placeholder', '0');
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

    /*
     * Guardar el borrador activo y bloquear el cambio de pestaña mientras
     * el backend registra la venta real.
     */
    const idVentaColaProcesada =
        typeof window.ventaColaPrepararProcesamiento === 'function'
            ? window.ventaColaPrepararProcesamiento()
            : null;

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

            const modoEnvioRespuesta = String(
                data.modo_envio || ''
            ).trim().toLowerCase();

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

                if (modoEnvioRespuesta === 'resumen_diario') {
                    titulo = 'Boleta pendiente de Resumen Diario';
                    icono = 'success';
                    mensaje = String(
                        data.mensaje ||
                        'La boleta quedó preparada para el Resumen Diario.'
                    );
                } else if (modoEnvioRespuesta === 'manual') {
                    titulo = 'Venta registrada';
                    icono = 'success';
                    mensaje = String(
                        data.mensaje ||
                        'El comprobante quedó pendiente de envío manual.'
                    );
                } else if (sunat.success === true) {
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

                const colaGestionada =
                    idVentaColaProcesada
                    && typeof window.ventaColaFinalizarVentaProcesada === 'function'
                    && window.ventaColaFinalizarVentaProcesada(
                        idVentaColaProcesada
                    ) === true;

                /*
                 * Respaldo para instalaciones donde el gestor de pestañas
                 * no esté disponible.
                 */
                if (!colaGestionada) {
                    form.reset();

                    limpiarDatosCliente(false);
                    sincronizarDireccionVisible();
                    $('#fecha_emision')
                        .val(obtenerFechaLocalISO())
                        .trigger('change');
                    actualizarReglaCliente();

                    $('#detallesCards').empty();
                    $('#totalGeneral').text(simboloMonedaVenta() + '0.00');
                    $('#total_recibido').val('');
                    $('#vuelto').val('0.00');

                    cont = 0;

                    actualizarMensajePedido();
                    mostrarSerieNumero();

                    window.setTimeout(function () {
                        aplicarConfiguracionVentaPredeterminada({
                            despuesDeGuardar: true
                        });

                        renderizarTiposOperacionSunat();
                        calcularTotales();
                    }, 50);
                }

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

            if (
                typeof window.ventaColaBloquear === 'function'
            ) {
                window.ventaColaBloquear(false);
            }
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
    if (esPagoCombinadoSeleccionado()) return;
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
    const lineas = [];
    let subtotal = 0;

    $('#detallesCards .filas').each(function () {
        const $fila = $(this);
        const cantidad = Number(
            $fila.find("input[name='cantidad[]']").val()
        ) || 0;
        const precio = Number(
            $fila.find("input[name='precio_venta[]']").val()
        ) || 0;
        const codigo = String(
            $fila.find("input[name='codigo_afectacion_igv[]']").val()
            || configuracionTributariaVentaCache.codigo_afectacion_igv
            || '10'
        );
        const porcentaje = codigo === '10'
            ? Number(
                $fila.find("input[name='porcentaje_igv[]']").val()
                || configuracionTributariaVentaCache.porcentaje_igv
                || 0
            )
            : 0;
        const bruto = redondearVenta(
            calcularImporteBrutoTributario(
                cantidad,
                precio,
                codigo,
                porcentaje
            ),
            2
        );

        const idSubtotal = $fila.find("span[name='subtotal']");
        idSubtotal.text(bruto.toFixed(2));

        lineas.push({
            codigo: codigo,
            porcentaje: porcentaje,
            bruto: bruto
        });

        subtotal += bruto;
    });

    subtotal = redondearVenta(subtotal, 2);

    let valorDescuento = Number.parseFloat(
        $('#descuentoPorcentaje').val()
    ) || 0;
    const esPorcentaje = !$('#descuentoSwitch').is(':checked');

    let descuento = 0;

    if (valorDescuento > 0) {
        descuento = esPorcentaje
            ? subtotal * (valorDescuento / 100)
            : valorDescuento;
    }

    descuento = redondearVenta(
        Math.min(Math.max(descuento, 0), subtotal),
        2
    );

    let descuentoAsignado = 0;
    let totalGravado = 0;
    let totalExonerado = 0;
    let totalInafecto = 0;
    let totalExportacion = 0;
    let totalIgv = 0;
    let totalFinal = 0;

    lineas.forEach(function (linea, indice) {
        let descuentoLinea = 0;

        if (indice === lineas.length - 1) {
            descuentoLinea = redondearVenta(
                descuento - descuentoAsignado,
                2
            );
        } else if (subtotal > 0) {
            descuentoLinea = redondearVenta(
                descuento * (linea.bruto / subtotal),
                2
            );
            descuentoAsignado += descuentoLinea;
        }

        descuentoLinea = Math.min(
            Math.max(descuentoLinea, 0),
            linea.bruto
        );

        const totalLinea = redondearVenta(
            linea.bruto - descuentoLinea,
            2
        );

        if (linea.codigo === '10' && linea.porcentaje > 0) {
            const factor = 1 + (linea.porcentaje / 100);
            const base = redondearVenta(totalLinea / factor, 2);
            const igv = redondearVenta(totalLinea - base, 2);

            totalGravado += base;
            totalIgv += igv;
        } else if (linea.codigo === '20') {
            totalExonerado += totalLinea;
        } else if (linea.codigo === '30') {
            totalInafecto += totalLinea;
        } else if (linea.codigo === '40') {
            totalExportacion += totalLinea;
        }

        totalFinal += totalLinea;
    });

    totalGravado = redondearVenta(totalGravado, 2);
    totalExonerado = redondearVenta(totalExonerado, 2);
    totalInafecto = redondearVenta(totalInafecto, 2);
    totalExportacion = redondearVenta(totalExportacion, 2);
    totalIgv = redondearVenta(totalIgv, 2);
    totalFinal = redondearVenta(totalFinal, 2);

    $('#totalGeneral').text(simboloMonedaVenta() + totalFinal.toFixed(2));
    $('#totalPedidoHeader').text(simboloMonedaVenta() + ' ' + totalFinal.toFixed(2));

    $('#descuento_total').val(descuento.toFixed(2));
    $('#descuento_porcentaje').val(
        esPorcentaje ? valorDescuento : 0
    );

    $('#total_gravado').val(totalGravado.toFixed(2));
    $('#total_exonerado').val(totalExonerado.toFixed(2));
    $('#total_inafecto').val(totalInafecto.toFixed(2));
    $('#total_exportacion').val(totalExportacion.toFixed(2));
    $('#total_igv').val(totalIgv.toFixed(2));

    if (typeof sincronizarTotalRecibido === 'function') {
        sincronizarTotalRecibido();
    }

    /*
     * Si Recibido fue escrito manualmente, sincronizarTotalRecibido()
     * no lo sobrescribe. En ese caso debemos recalcular el Vuelto
     * inmediatamente cuando cambie el descuento o el total de la venta.
     */
    if (typeof calcularVuelto === 'function') {
        calcularVuelto();
    }

    if (typeof recalcularCuotasCredito === 'function') {
        recalcularCuotasCredito();
    }
}

function redondearVenta(numero, decimales = 2) {
    const factor = Math.pow(10, decimales);
    return Math.round((Number(numero || 0) + Number.EPSILON) * factor) / factor;
}

function monedaTributaria(valor) {
    const simbolo = String(
        configuracionTributariaVentaCache.simbolo || 'S/'
    );

    return simbolo + ' ' + Number(valor || 0).toFixed(2);
}


function recalcularCuotasCredito() {

    if (textoNormalizado($('#condicion_pago').val()) !== 'CREDITO') return;

    let cuotas = parseInt($('#numero_cuotas').val());
    if (!cuotas || cuotas < 1) return;

    let totalVenta = totalVentaActual();
    let monto = totalVenta / cuotas;

    $('#monto_cuota').val(simboloMonedaVenta() + ' ' + monto.toFixed(2));
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
                sincronizarDireccionVisible();
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
            sincronizarDireccionVisible();
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

/* ================================================================
 * BUSCADOR RÁPIDO DE PRODUCTOS EN "PEDIDO ACTUAL"
 * Busca por SKU/código o por coincidencia parcial del nombre.
 * El inventario se obtiene una sola vez usando los endpoints existentes.
 * ================================================================ */
function estadoBusquedaPedido(mensaje, tipo = 'info') {
    const clases = {
        info: 'text-muted',
        loading: 'text-primary',
        success: 'text-success',
        warning: 'text-warning',
        error: 'text-danger'
    };

    $('#estadoBusquedaPedido')
        .removeClass(
            'text-muted text-primary text-success text-warning text-danger'
        )
        .addClass(clases[tipo] || clases.info)
        .text(String(mensaje || ''));
}

function cerrarResultadosBusquedaPedido() {
    $('#resultadosBusquedaPedido')
        .stop(true, true)
        .hide()
        .empty();

    resultadosBusquedaPedidoCache = [];
    indiceResultadoBusquedaPedido = -1;
}

function limpiarBuscadorPedido(enfocar = false) {
    window.clearTimeout(temporizadorBusquedaPedido);

    $('#buscarProductoPedido').val('');
    cerrarResultadosBusquedaPedido();

    estadoBusquedaPedido(
        'Escribe al menos 2 caracteres para buscar en tus productos existentes.',
        'info'
    );

    if (enfocar) {
        $('#buscarProductoPedido').trigger('focus');
    }
}

function obtenerClaveProductoBusqueda(producto) {
    const idingreso = Number.parseInt(
        producto.idingreso || producto.iddetalle_ingreso,
        10
    ) || 0;

    const idarticulo = Number.parseInt(
        producto.idarticulo,
        10
    ) || 0;

    return idingreso > 0
        ? `INGRESO-${idingreso}`
        : `ARTICULO-${idarticulo}`;
}

function cargarInventarioBusquedaPedido(forzar = false) {
    if (inventarioBusquedaPedidoCargado && !forzar) {
        return $.Deferred()
            .resolve(inventarioBusquedaPedidoCache)
            .promise();
    }

    if (solicitudInventarioBusquedaPedido && !forzar) {
        return solicitudInventarioBusquedaPedido.promise();
    }

    const diferido = $.Deferred();
    solicitudInventarioBusquedaPedido = diferido;
    cargandoInventarioBusquedaPedido = true;

    estadoBusquedaPedido(
        'Preparando el buscador de productos...',
        'loading'
    );

    cargarCategoriasParaBusquedaGlobal()
        .done(function (categorias) {
            const listaCategorias = Array.isArray(categorias)
                ? categorias
                : [];

            const productosUnicos = new Map();
            let indiceCategoria = 0;

            function cargarSiguienteCategoria() {
                if (indiceCategoria >= listaCategorias.length) {
                    inventarioBusquedaPedidoCache = Array.from(
                        productosUnicos.values()
                    );

                    inventarioBusquedaPedidoCargado = true;
                    cargandoInventarioBusquedaPedido = false;
                    solicitudInventarioBusquedaPedido = null;

                    diferido.resolve(inventarioBusquedaPedidoCache);
                    return;
                }

                const categoria = listaCategorias[indiceCategoria];
                indiceCategoria += 1;

                const idcategoria = Number.parseInt(
                    categoria && categoria.idcategoria,
                    10
                ) || 0;

                if (idcategoria <= 0) {
                    cargarSiguienteCategoria();
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
                        (Array.isArray(productos) ? productos : [])
                            .forEach(function (producto) {
                                const idarticulo = Number.parseInt(
                                    producto && producto.idarticulo,
                                    10
                                ) || 0;

                                if (idarticulo <= 0) {
                                    return;
                                }

                                const clave = obtenerClaveProductoBusqueda(
                                    producto
                                );

                                if (!productosUnicos.has(clave)) {
                                    productosUnicos.set(clave, producto);
                                }
                            });
                    })
                    .always(function () {
                        cargarSiguienteCategoria();
                    });
            }

            cargarSiguienteCategoria();
        })
        .fail(function () {
            inventarioBusquedaPedidoCache = [];
            inventarioBusquedaPedidoCargado = false;
            cargandoInventarioBusquedaPedido = false;
            solicitudInventarioBusquedaPedido = null;

            diferido.reject();
        });

    return diferido.promise();
}

function puntajeProductoBusquedaPedido(producto, termino) {
    const codigo = normalizarBusquedaProducto(
        producto.codigo || producto.sku || producto.referencia
    );
    const nombre = normalizarBusquedaProducto(producto.nombre);

    if (codigo === termino) {
        return 0;
    }

    if (codigo.startsWith(termino)) {
        return 1;
    }

    if (nombre.startsWith(termino)) {
        return 2;
    }

    if (codigo.includes(termino)) {
        return 3;
    }

    return 4;
}

function filtrarInventarioBusquedaPedido(terminoOriginal) {
    const termino = normalizarBusquedaProducto(terminoOriginal);

    if (termino.length < 2) {
        return [];
    }

    return inventarioBusquedaPedidoCache
        .filter(function (producto) {
            const codigo = normalizarBusquedaProducto(
                producto.codigo || producto.sku || producto.referencia
            );
            const nombre = normalizarBusquedaProducto(producto.nombre);
            const referencia = normalizarBusquedaProducto(
                producto.referencia || producto.descripcion
            );

            return codigo.includes(termino)
                || nombre.includes(termino)
                || referencia.includes(termino);
        })
        .sort(function (a, b) {
            const puntajeA = puntajeProductoBusquedaPedido(a, termino);
            const puntajeB = puntajeProductoBusquedaPedido(b, termino);

            if (puntajeA !== puntajeB) {
                return puntajeA - puntajeB;
            }

            const stockA = Number.parseInt(a.stock, 10) || 0;
            const stockB = Number.parseInt(b.stock, 10) || 0;

            if ((stockA > 0) !== (stockB > 0)) {
                return stockA > 0 ? -1 : 1;
            }

            return String(a.nombre || '').localeCompare(
                String(b.nombre || ''),
                'es',
                { sensitivity: 'base' }
            );
        })
        .slice(0, 12);
}

function renderResultadosBusquedaPedido(productos, termino) {
    resultadosBusquedaPedidoCache = Array.isArray(productos)
        ? productos
        : [];
    indiceResultadoBusquedaPedido = -1;

    const $contenedor = $('#resultadosBusquedaPedido');

    if (resultadosBusquedaPedidoCache.length === 0) {
        $contenedor
            .html(`
                <div class="resultado-producto-vacio">
                    <i class="bi bi-search d-block mb-2" style="font-size:1.35rem;"></i>
                    No se encontraron productos con “${escaparHtmlProducto(termino)}”.
                </div>
            `)
            .show();

        estadoBusquedaPedido(
            'No se encontraron coincidencias por SKU o nombre.',
            'warning'
        );
        return;
    }

    let html = '';

    resultadosBusquedaPedidoCache.forEach(function (producto, indice) {
        const codigo = String(
            producto.codigo || producto.sku || producto.referencia || ''
        ).trim();
        const nombre = String(producto.nombre || 'Producto').trim();
        const stock = Number.parseInt(producto.stock, 10) || 0;
        const precioVenta = Number.parseFloat(producto.precio_venta) || 0;
        const agotado = stock <= 0;

        html += `
            <button
                type="button"
                class="resultado-producto-pedido ${agotado ? 'resultado-producto-agotado' : ''}"
                data-indice="${indice}"
                role="option"
                aria-label="Agregar ${escaparHtmlProducto(nombre)}">

                <span class="resultado-producto-icono">
                    <i class="bi ${agotado ? 'bi-box-seam' : 'bi-plus-lg'}"></i>
                </span>

                <span class="resultado-producto-info">
                    <span class="resultado-producto-nombre d-block">
                        ${escaparHtmlProducto(nombre)}
                    </span>
                    <span class="resultado-producto-meta d-block">
                        SKU: ${escaparHtmlProducto(codigo || 'Sin código')}
                        · Stock: ${stock}
                    </span>
                </span>

                <span class="resultado-producto-precio">
                    ${simboloMonedaVenta()} ${precioVenta.toFixed(2)}
                </span>
            </button>
        `;
    });

    $contenedor.html(html).show();

    estadoBusquedaPedido(
        `${resultadosBusquedaPedidoCache.length} coincidencia(s). Selecciona un producto para agregarlo.`,
        'success'
    );
}

function ejecutarBusquedaPedido() {
    const terminoOriginal = String(
        $('#buscarProductoPedido').val() || ''
    ).trim();

    if (terminoOriginal.length < 2) {
        cerrarResultadosBusquedaPedido();
        estadoBusquedaPedido(
            'Escribe al menos 2 caracteres para buscar en tus productos existentes.',
            'info'
        );
        return;
    }

    estadoBusquedaPedido('Buscando productos...', 'loading');

    cargarInventarioBusquedaPedido()
        .done(function () {
            const terminoActual = String(
                $('#buscarProductoPedido').val() || ''
            ).trim();

            if (terminoActual !== terminoOriginal) {
                return;
            }

            renderResultadosBusquedaPedido(
                filtrarInventarioBusquedaPedido(terminoOriginal),
                terminoOriginal
            );
        })
        .fail(function () {
            cerrarResultadosBusquedaPedido();
            estadoBusquedaPedido(
                'No se pudo cargar el inventario para realizar la búsqueda.',
                'error'
            );
        });
}

function agregarProductoDesdeBusquedaPedido(indice) {
    const producto = resultadosBusquedaPedidoCache[indice];

    if (!producto) {
        return;
    }

    const stock = Number.parseInt(producto.stock, 10) || 0;

    if (stock <= 0) {
        Swal.fire(
            'Producto sin stock',
            `${String(producto.nombre || 'El producto')} no tiene unidades disponibles.`,
            'warning'
        );
        return;
    }

    agregarDetalle(
        Number.parseInt(
            producto.idingreso || producto.iddetalle_ingreso,
            10
        ) || 0,
        Number.parseInt(producto.idarticulo, 10) || 0,
        String(
            producto.codigo || producto.sku || producto.referencia || ''
        ),
        String(producto.nombre || 'Producto'),
        Number.parseFloat(producto.precio_compra) || 0,
        Number.parseFloat(producto.precio_venta) || 0,
        stock,
        1,
        String(producto.codigo_afectacion_igv || '10'),
        Number(producto.porcentaje_igv ?? 18),
        String(producto.unidad_medida_sunat || 'NIU'),
        String(producto.codigo_producto_sunat || '')
    );

    limpiarBuscadorPedido(true);
}

function moverSeleccionBusquedaPedido(direccion) {
    const $opciones = $('#resultadosBusquedaPedido .resultado-producto-pedido');

    if ($opciones.length === 0) {
        return;
    }

    indiceResultadoBusquedaPedido += direccion;

    if (indiceResultadoBusquedaPedido < 0) {
        indiceResultadoBusquedaPedido = $opciones.length - 1;
    }

    if (indiceResultadoBusquedaPedido >= $opciones.length) {
        indiceResultadoBusquedaPedido = 0;
    }

    $opciones.removeClass('active');

    const $activa = $opciones.eq(indiceResultadoBusquedaPedido);
    $activa.addClass('active');

    const contenedor = document.getElementById('resultadosBusquedaPedido');
    const activa = $activa.get(0);

    if (contenedor && activa) {
        /*
         * Solo desplazar el listado interno de resultados.
         * scrollIntoView() podía mover también la ventana/documento.
         */
        const superior = activa.offsetTop;
        const inferior = superior + activa.offsetHeight;
        const visibleSuperior = contenedor.scrollTop;
        const visibleInferior = visibleSuperior + contenedor.clientHeight;

        if (superior < visibleSuperior) {
            contenedor.scrollTop = superior;
        } else if (inferior > visibleInferior) {
            contenedor.scrollTop = Math.max(
                0,
                inferior - contenedor.clientHeight
            );
        }
    }
}

function inicializarBuscadorPedido() {
    $(document)
        .off('input.buscadorPedido', '#buscarProductoPedido')
        .on('input.buscadorPedido', '#buscarProductoPedido', function () {
            window.clearTimeout(temporizadorBusquedaPedido);

            temporizadorBusquedaPedido = window.setTimeout(
                ejecutarBusquedaPedido,
                180
            );
        });

    $(document)
        .off('focus.buscadorPedido', '#buscarProductoPedido')
        .on('focus.buscadorPedido', '#buscarProductoPedido', function () {
            const termino = String($(this).val() || '').trim();

            if (termino.length >= 2) {
                ejecutarBusquedaPedido();
            }
        });

    $(document)
        .off('keydown.buscadorPedido', '#buscarProductoPedido')
        .on('keydown.buscadorPedido', '#buscarProductoPedido', function (evento) {
            if (evento.key === 'ArrowDown') {
                evento.preventDefault();
                moverSeleccionBusquedaPedido(1);
                return;
            }

            if (evento.key === 'ArrowUp') {
                evento.preventDefault();
                moverSeleccionBusquedaPedido(-1);
                return;
            }

            if (evento.key === 'Escape') {
                cerrarResultadosBusquedaPedido();
                return;
            }

            if (evento.key !== 'Enter') {
                return;
            }

            evento.preventDefault();
            evento.stopPropagation();

            if (resultadosBusquedaPedidoCache.length === 0) {
                ejecutarBusquedaPedido();
                return;
            }

            const indice = indiceResultadoBusquedaPedido >= 0
                ? indiceResultadoBusquedaPedido
                : 0;

            agregarProductoDesdeBusquedaPedido(indice);
        });

    $(document)
        .off('click.buscadorPedido', '#resultadosBusquedaPedido .resultado-producto-pedido')
        .on(
            'click.buscadorPedido',
            '#resultadosBusquedaPedido .resultado-producto-pedido',
            function () {
                agregarProductoDesdeBusquedaPedido(
                    Number.parseInt($(this).attr('data-indice'), 10) || 0
                );
            }
        );

    $(document)
        .off('click.buscadorPedido', '#btnLimpiarBusquedaPedido')
        .on('click.buscadorPedido', '#btnLimpiarBusquedaPedido', function () {
            limpiarBuscadorPedido(true);
        });

    $(document)
        .off('mousedown.buscadorPedido')
        .on('mousedown.buscadorPedido', function (evento) {
            if (!$(evento.target).closest('#buscadorPedidoWrap').length) {
                cerrarResultadosBusquedaPedido();
            }
        });
}

function invalidarInventarioBusquedaPedido() {
    inventarioBusquedaPedidoCache = [];
    inventarioBusquedaPedidoCargado = false;
    cargandoInventarioBusquedaPedido = false;
    solicitudInventarioBusquedaPedido = null;
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
    const costoIngresado = String(
        $('#rapido_precio_compra').val() || ''
    ).trim() !== '';

    const compra = Number.parseFloat(
        $('#rapido_precio_compra').val()
    ) || 0;
    const venta = Number.parseFloat(
        $('#rapido_precio_venta').val()
    ) || 0;

    if (venta <= 0) {
        $('#rapido_ganancia').html(
            '<span class="text-muted">Ingresa el precio de venta para continuar.</span>'
        );
        return;
    }

    if (!costoIngresado || compra === 0) {
        $('#rapido_ganancia').html(
            '<div class="small text-muted mb-1">Costo de compra opcional</div>' +
            '<strong class="text-muted">No se calculará el margen porcentual.</strong>'
        );
        return;
    }

    const ganancia = venta - compra;
    const porcentaje = (ganancia / compra) * 100;
    const clase = ganancia >= 0 ? 'text-success' : 'text-danger';

    $('#rapido_ganancia').html(
        '<div class="small text-muted mb-1">Ganancia estimada por unidad</div>' +
        '<strong class="' + clase + '">' + simboloMonedaVenta() + ' ' + ganancia.toFixed(2) + '</strong>' +
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

    if (precioCompra < 0) {
        Swal.fire(
            'Costo inválido',
            'El costo de compra no puede ser negativo.',
            'warning'
        );
        return;
    }

    if (precioVenta <= 0) {
        Swal.fire(
            'Precio de venta inválido',
            'El precio de venta debe ser mayor que cero.',
            'warning'
        );
        return;
    }

    const datos = new FormData(formulario);

    /*
     * El costo de compra es opcional. Si queda vacío,
     * se envía explícitamente como 0.00 al backend.
     */
    datos.set('precio_compra', precioCompra.toFixed(2));

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

            invalidarInventarioBusquedaPedido();
            cerrarProductoRapido(true);

            agregarDetalle(
                Number.parseInt(producto.idingreso, 10) || 0,
                Number.parseInt(producto.idarticulo, 10) || 0,
                String(producto.codigo || ''),
                String(producto.nombre || ''),
                Number.parseFloat(producto.precio_compra) || 0,
                Number.parseFloat(producto.precio_venta) || 0,
                Number.parseInt(producto.stock, 10) || 1,
                1,
                String(producto.codigo_afectacion_igv || '10'),
                Number(producto.porcentaje_igv ?? 18),
                String(producto.unidad_medida_sunat || 'NIU'),
                String(producto.codigo_producto_sunat || '')
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
            const codigoAfectacion = String(prod.codigo_afectacion_igv || '10');
            const porcentajeIgv = codigoAfectacion === '10'
                ? Number(prod.porcentaje_igv ?? 18)
                : 0;
            const unidadSunat = String(prod.unidad_medida_sunat || 'NIU');
            const codigoProductoSunat = String(prod.codigo_producto_sunat || '');
            const etiquetaImpuesto = etiquetaAfectacionIgv(
                codigoAfectacion,
                porcentajeIgv
            );

            const codigoHtml = escaparHtmlProducto(codigo);
            const nombreHtml = escaparHtmlProducto(nombre);
            const imagenHtml = escaparHtmlProducto(imagen);

            prodHtml += `
                <div class="producto-item"
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
                        data-stock="${stock}"
                        data-codigo-afectacion="${codigoAfectacion}"
                        data-porcentaje-igv="${porcentajeIgv}"
                        data-unidad-sunat="${unidadSunat}"
                        data-codigo-producto-sunat="${escaparHtmlProducto(codigoProductoSunat)}">

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
                                    <span class="venta-product-tax-badge ${claseAfectacionIgv(codigoAfectacion)}">
                                        ${escaparHtmlProducto(etiquetaImpuesto)}
                                    </span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top">
                                <span class="producto-stock">
                                    <i class="bi bi-box mr-1"></i>
                                    Stock: ${stock}
                                </span>

                                <span class="producto-precio">
                                    ${simboloMonedaVenta()} ${precioVenta.toFixed(2)}
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
            1,
            String($producto.attr('data-codigo-afectacion') || '10'),
            Number($producto.attr('data-porcentaje-igv') || 18),
            String($producto.attr('data-unidad-sunat') || 'NIU'),
            String($producto.attr('data-codigo-producto-sunat') || '')
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
    op,
    codigo_afectacion_igv = '10',
    porcentaje_igv = 18,
    unidad_medida_sunat = 'NIU',
    codigo_producto_sunat = ''
) {
    if (!idarticulo || idarticulo === 0) {
        Swal.fire("Error", "Artículo inválido", "error");
        return;
    }

    const stockDisponible = Math.max(
        Number.parseInt(stock, 10) || 0,
        0
    );

    /*
     * Si el producto ya está en el pedido, aumentamos la cantidad
     * trabajando sobre su propia fila. Así no dependemos de la posición
     * del array DOM y evitamos errores cuando antes se eliminó otra fila.
     */
    let existe = false;

    $("#detallesCards input[name='idarticulo[]']").each(function () {
        if (Number.parseInt($(this).val(), 10) !== Number.parseInt(idarticulo, 10)) {
            return;
        }

        const $fila = $(this).closest('.filas');
        const $cantidadInput = $fila.find("input[name='cantidad[]']");
        const cantidadActual = Number.parseInt(
            $cantidadInput.val(),
            10
        ) || 0;

        const stockFila = Number.parseInt(
            $fila.attr('data-stock-max'),
            10
        ) || stockDisponible;

        const nuevaCantidad = cantidadActual + 1;

        if (nuevaCantidad > stockFila) {
            Swal.fire(
                "Stock insuficiente",
                "No hay más unidades disponibles.",
                "warning"
            );

            existe = true;
            return false;
        }

        $cantidadInput.val(nuevaCantidad);
        $fila.find('.cantidad-label').text(nuevaCantidad);

        calcularTotales();
        actualizarMensajePedido();

        existe = true;
        return false;
    });

    if (existe) {
        $('#modalProductos').modal('hide');
        return;
    }

    let cantidad = 1;
    let descuento = 0;

    codigo_afectacion_igv = String(
        codigo_afectacion_igv
        || configuracionTributariaVentaCache.codigo_afectacion_igv
        || '10'
    );

    porcentaje_igv = codigo_afectacion_igv === '10'
        ? Number(
            porcentaje_igv
            ?? configuracionTributariaVentaCache.porcentaje_igv
            ?? 18
        )
        : 0;

    unidad_medida_sunat = String(
        unidad_medida_sunat
        || configuracionTributariaVentaCache.unidad_medida_sunat
        || 'NIU'
    ).toUpperCase();

    codigo_producto_sunat = String(codigo_producto_sunat || '');

    const precioVentaNumero = Number(precio_venta) || 0;
    const precioCompraNumero = Number(precio_compra) || 0;

    let subtotal = calcularImporteBrutoTributario(
        cantidad,
        precioVentaNumero,
        codigo_afectacion_igv,
        porcentaje_igv
    );

    const etiquetaTributaria = etiquetaAfectacionIgv(
        codigo_afectacion_igv,
        porcentaje_igv
    );

    const indiceFila = cont;
    const articuloSeguro = escaparHtmlProducto(
        String(articulo || 'Producto')
    );
    const codigoSeguro = escaparHtmlProducto(
        String(codigo || '')
    );

    let card = `
        <div
            class="card border-0 shadow-sm mb-3 bg-white filas venta-pedido-item tw-bg-white tw-border tw-border-slate-100 tw-rounded-2xl tw-shadow-sm"
            id="fila${indiceFila}"
            data-indice="${indiceFila}"
            data-stock-max="${stockDisponible}"
            data-precio-original="${precioVentaNumero.toFixed(2)}">

            <div class="card-body d-flex justify-content-between align-items-start p-3 tw-gap-4">

                <!-- INPUTS OCULTOS -->
                <input type="hidden" name="idingreso[]" value="${Number(idingreso) || 0}">
                <input type="hidden" name="idarticulo[]" value="${Number(idarticulo) || 0}">
                <input type="hidden" name="precio_compra[]" value="${precioCompraNumero}">
                <input type="hidden" name="descuento[]" value="${descuento}">
                <input type="hidden" name="codigo_afectacion_igv[]" value="${escaparHtmlProducto(codigo_afectacion_igv)}">
                <input type="hidden" name="porcentaje_igv[]" value="${Number(porcentaje_igv) || 0}">
                <input type="hidden" name="unidad_medida_sunat[]" value="${escaparHtmlProducto(unidad_medida_sunat)}">
                <input type="hidden" name="codigo_producto_sunat[]" value="${escaparHtmlProducto(codigo_producto_sunat)}">

                <!-- INFO PRODUCTO -->
                <div class="tw-min-w-0 tw-flex-1">
                    <div class="venta-producto-nombre tw-text-base tw-text-slate-800 tw-mb-1">
                        ${articuloSeguro}
                    </div>

                    <div class="text-muted small">Almacén: Principal</div>
                    <div class="text-muted small">SKU: ${codigoSeguro}</div>

                    <span class="venta-product-tax-badge ${claseAfectacionIgv(codigo_afectacion_igv)}">
                        <i class="fas fa-receipt"></i>
                        ${escaparHtmlProducto(etiquetaTributaria)}
                    </span>

                    <div class="text-muted small tw-mt-2">
                        Precio unitario:
                        <span
                            class="venta-precio-original precio-original-label"
                            id="precioOriginalLabel${indiceFila}">
                            ${simboloMonedaVenta()} ${precioVentaNumero.toFixed(2)}
                        </span>
                        <span
                            class="venta-producto-precio precio-venta-label"
                            id="precioVentaLabel${indiceFila}">
                            ${simboloMonedaVenta()} ${precioVentaNumero.toFixed(2)}
                        </span>
                        <span
                            class="venta-oferta-badge"
                            id="ofertaBadge${indiceFila}">
                            <i class="bi bi-tag-fill" aria-hidden="true"></i>
                            Oferta
                        </span>

                        <input
                            type="hidden"
                            name="precio_venta[]"
                            id="precioVentaInput${indiceFila}"
                            value="${precioVentaNumero}">
                    </div>

                    <div class="text-muted small">
                        Cantidad:
                        <span
                            class="fw-semibold cantidad-label"
                            id="cantidadLabel${indiceFila}">
                            ${cantidad}
                        </span>

                        <input
                            type="hidden"
                            name="cantidad[]"
                            id="cantidadInput${indiceFila}"
                            value="${cantidad}">
                    </div>

                    <div class="venta-producto-total tw-mt-2 tw-text-slate-800">
                        Total: ${simboloMonedaVenta()}
                        <span name="subtotal" id="subtotal${indiceFila}">
                            ${subtotal.toFixed(2)}
                        </span>
                    </div>
                </div>

                <!-- ACCIONES TAILWIND -->
                <div
                    class="venta-item-actions tw-grid tw-grid-cols-2 tw-gap-2 tw-ml-auto"
                    aria-label="Acciones del producto">

                    <button
                        type="button"
                        class="venta-item-btn venta-item-btn--plus tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-border tw-border-tique-200 tw-bg-tique-50 tw-text-tique-700 tw-transition hover:tw-bg-tique-100 hover:tw-shadow-sm"
                        onclick="incrementarCantidad(${indiceFila}, ${stockDisponible})"
                        title="Aumentar cantidad"
                        aria-label="Aumentar cantidad">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    </button>

                    <button
                        type="button"
                        class="venta-item-btn venta-item-btn--minus tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-text-slate-600 tw-transition hover:tw-bg-slate-100 hover:tw-shadow-sm"
                        onclick="decrementarCantidad(${indiceFila})"
                        title="Disminuir cantidad"
                        aria-label="Disminuir cantidad">
                        <i class="bi bi-dash-lg" aria-hidden="true"></i>
                    </button>

                    <button
                        type="button"
                        class="venta-item-btn venta-item-btn--edit tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-border tw-border-tique-200 tw-bg-white tw-text-tique-700 tw-transition hover:tw-bg-tique-50 hover:tw-shadow-sm"
                        onclick="editarProductoPedido(${indiceFila})"
                        title="Editar producto"
                        aria-label="Editar producto">
                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
                    </button>

                    <button
                        type="button"
                        class="venta-item-btn venta-item-btn--delete tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-border tw-border-red-200 tw-bg-red-50 tw-text-red-600 tw-transition hover:tw-bg-red-100 hover:tw-shadow-sm"
                        onclick="eliminarDetalle(${indiceFila})"
                        title="Quitar producto"
                        aria-label="Quitar producto">
                        <i class="bi bi-trash3" aria-hidden="true"></i>
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

function actualizarEstadoCamara(mensaje) {
    const estado = document.getElementById('ventaCamaraEstado');

    if (estado) {
        estado.textContent = String(mensaje || '');
    }
}

function mensajeErrorCamara(error) {
    const texto = String(
        error && (error.message || error.name)
            ? (error.message || error.name)
            : error || ''
    ).toLowerCase();

    if (texto.includes('notallowed') || texto.includes('permission')) {
        return 'Permiso de cámara denegado. Habilítalo en el navegador y vuelve a intentar.';
    }

    if (texto.includes('notfound') || texto.includes('devicesnotfound')) {
        return 'No se encontró una cámara disponible en este dispositivo.';
    }

    if (texto.includes('notreadable') || texto.includes('trackstart')) {
        return 'La cámara está siendo usada por otra aplicación o no puede iniciarse.';
    }

    if (texto.includes('overconstrained')) {
        return 'No se pudo iniciar la cámara preferida. Intenta nuevamente.';
    }

    return 'No se pudo iniciar la cámara. Revisa los permisos del navegador.';
}

function crearInstanciaEscanerCamara() {
    if (ESTADO_CAMARA.instancia) {
        return ESTADO_CAMARA.instancia;
    }

    if (typeof window.Html5Qrcode !== 'function') {
        return null;
    }

    const formatos = [];

    if (window.Html5QrcodeSupportedFormats) {
        [
            'QR_CODE',
            'CODE_128',
            'CODE_39',
            'CODE_93',
            'EAN_13',
            'EAN_8',
            'UPC_A',
            'UPC_E',
            'ITF',
            'DATA_MATRIX',
            'PDF_417',
            'AZTEC'
        ].forEach(function (nombreFormato) {
            const valor = window.Html5QrcodeSupportedFormats[nombreFormato];

            if (typeof valor !== 'undefined') {
                formatos.push(valor);
            }
        });
    }

    const opciones = formatos.length > 0
        ? { formatsToSupport: formatos }
        : undefined;

    ESTADO_CAMARA.instancia = new window.Html5Qrcode(
        'ventaCamaraReader',
        opciones
    );

    return ESTADO_CAMARA.instancia;
}

async function detenerEscanerCamara() {
    const instancia = ESTADO_CAMARA.instancia;

    ESTADO_CAMARA.activa = false;
    ESTADO_CAMARA.iniciando = false;

    if (!instancia) {
        return;
    }

    try {
        await instancia.stop();
    } catch (error) {
        /* Si ya estaba detenido, no interrumpimos el cierre del modal. */
    }

    try {
        instancia.clear();
    } catch (error) {
        /* El contenedor se limpiará al crear una nueva instancia. */
    }

    if (ESTADO_CAMARA.instancia === instancia) {
        ESTADO_CAMARA.instancia = null;
    }
}

async function procesarLecturaCamara(textoDecodificado) {
    const codigo = String(textoDecodificado || '').trim();

    if (ESTADO_CAMARA.procesando || codigo.length < 2) {
        return;
    }

    ESTADO_CAMARA.procesando = true;
    actualizarEstadoCamara('Código detectado. Procesando...');

    if (navigator.vibrate) {
        navigator.vibrate(60);
    }

    await detenerEscanerCamara();

    $('#modalEscanerCamara').modal('hide');

    window.setTimeout(function () {
        procesarCodigoEscaneado(codigo, 'camara');
        ESTADO_CAMARA.procesando = false;
    }, 120);
}

async function iniciarEscanerCamara() {
    if (ESTADO_CAMARA.iniciando || ESTADO_CAMARA.activa) {
        return;
    }

    if (!window.isSecureContext) {
        actualizarEstadoCamara('La cámara requiere HTTPS para funcionar.');
        return;
    }

    if (
        !navigator.mediaDevices
        || typeof navigator.mediaDevices.getUserMedia !== 'function'
    ) {
        actualizarEstadoCamara('Este navegador no permite acceso a la cámara.');
        return;
    }

    const instancia = crearInstanciaEscanerCamara();

    if (!instancia) {
        actualizarEstadoCamara('No se pudo cargar el lector de cámara.');
        return;
    }

    ESTADO_CAMARA.iniciando = true;
    ESTADO_CAMARA.procesando = false;
    actualizarEstadoCamara('Solicitando acceso a la cámara...');

    const configuracion = {
        fps: 12,
        disableFlip: false
    };

    const alDetectar = function (textoDecodificado) {
        procesarLecturaCamara(textoDecodificado);
    };

    const alNoDetectar = function () {
        /* Fallos por frame son normales mientras se apunta al código. */
    };

    try {
        await instancia.start(
            { facingMode: 'environment' },
            configuracion,
            alDetectar,
            alNoDetectar
        );

        ESTADO_CAMARA.activa = true;
        ESTADO_CAMARA.iniciando = false;
        actualizarEstadoCamara('Cámara activa · apunta al código');
        return;
    } catch (primerError) {
        try {
            const camaras = await window.Html5Qrcode.getCameras();

            if (!Array.isArray(camaras) || camaras.length === 0) {
                throw primerError;
            }

            await instancia.start(
                camaras[0].id,
                configuracion,
                alDetectar,
                alNoDetectar
            );

            ESTADO_CAMARA.activa = true;
            ESTADO_CAMARA.iniciando = false;
            actualizarEstadoCamara('Cámara activa · apunta al código');
            return;
        } catch (segundoError) {
            ESTADO_CAMARA.activa = false;
            ESTADO_CAMARA.iniciando = false;
            actualizarEstadoCamara(
                mensajeErrorCamara(segundoError || primerError)
            );
        }
    }
}

function abrirEscanerCamara() {
    limpiarCapturaEscaner();
    actualizarEstadoCamara('Preparando cámara...');
    $('#modalEscanerCamara').modal('show');
}

$(document).on('click', '#btnActivarEscaner', function () {
    abrirEscanerCamara();
});

$(document).on(
    'click',
    '#btnEscanearDesdeModal, #btnEscanearModalFooter',
    function () {
        activarEscanerProductos(this.id);
    }
);

$(document).on('shown.bs.modal', '#modalEscanerCamara', function () {
    iniciarEscanerCamara();
});

$(document).on('hidden.bs.modal', '#modalEscanerCamara', function () {
    detenerEscanerCamara();
    ESTADO_CAMARA.procesando = false;
});

window.addEventListener('beforeunload', function () {
    detenerEscanerCamara();
});

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
        1,
        String(producto.codigo_afectacion_igv || '10'),
        Number(producto.porcentaje_igv ?? 18),
        String(producto.unidad_medida_sunat || 'NIU'),
        String(producto.codigo_producto_sunat || '')
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
    const $fila = $("#fila" + indice);

    if (!$fila.length) {
        return;
    }

    const $cantidadInput = $fila.find("input[name='cantidad[]']");
    const cantidadActual = Number.parseInt(
        $cantidadInput.val(),
        10
    ) || 0;

    const stockMaximo = Number.parseInt(
        $fila.attr('data-stock-max'),
        10
    ) || Number.parseInt(stock, 10) || 0;

    const nuevaCantidad = cantidadActual + 1;

    if (stockMaximo > 0 && nuevaCantidad > stockMaximo) {
        Swal.fire(
            "Stock insuficiente",
            "No hay más unidades disponibles.",
            "warning"
        );
        return;
    }

    $cantidadInput.val(nuevaCantidad);
    $fila.find('.cantidad-label').text(nuevaCantidad);

    calcularTotales();
    actualizarMensajePedido();
}


function decrementarCantidad(indice) {
    const $fila = $("#fila" + indice);

    if (!$fila.length) {
        return;
    }

    const $cantidadInput = $fila.find("input[name='cantidad[]']");
    const cantidadActual = Number.parseInt(
        $cantidadInput.val(),
        10
    ) || 0;

    const nuevaCantidad = cantidadActual - 1;

    if (nuevaCantidad < 1) {
        return;
    }

    $cantidadInput.val(nuevaCantidad);
    $fila.find('.cantidad-label').text(nuevaCantidad);

    calcularTotales();
    actualizarMensajePedido();
}


/*
|--------------------------------------------------------------------------
| EDICIÓN RÁPIDA DEL PRODUCTO EN EL PEDIDO
| El nombre y el precio cambian solamente dentro de la venta actual.
|--------------------------------------------------------------------------
*/
function editarProductoPedido(indice) {
    const $fila = $("#fila" + indice);

    if (!$fila.length) {
        Swal.fire(
            'Producto no disponible',
            'No se encontró el producto dentro del pedido actual.',
            'warning'
        );
        return;
    }

    const precio = Number.parseFloat(
        $fila.find("input[name='precio_venta[]']").val()
    ) || 0;

    const nombre = String(
        $fila.find('.venta-producto-nombre').text()
        || 'Producto'
    ).trim();

    $('#editarPedidoIndice').val(indice);
    $('#editarPedidoNombreInput').val(nombre);
    $('#editarPedidoPrecio').val(precio.toFixed(2));
    $('#editarPedidoMoneda').text(simboloMonedaVenta());

    $('#modalEditarProductoPedido').modal('show');

    window.setTimeout(function () {
        $('#editarPedidoNombreInput')
            .trigger('focus')
            .select();
    }, 180);
}


function aplicarEdicionProductoPedido(
    indice,
    nombreProducto,
    precioVenta
) {
    const $fila = $("#fila" + indice);

    if (!$fila.length) {
        return false;
    }

    const nombreFinal = String(
        nombreProducto || ''
    ).trim();

    const precioFinal = Number.parseFloat(
        precioVenta
    );

    if (
        nombreFinal === ''
        || !Number.isFinite(precioFinal)
        || precioFinal <= 0
    ) {
        return false;
    }

    $fila
        .find('.venta-producto-nombre')
        .text(nombreFinal);

    $fila
        .find("input[name='precio_venta[]']")
        .val(precioFinal.toFixed(2));

    $fila
        .find('.precio-venta-label')
        .text(
            simboloMonedaVenta()
            + ' '
            + precioFinal.toFixed(2)
        );

    const precioOriginal = Number.parseFloat(
        $fila.attr('data-precio-original')
    ) || 0;

    $fila
        .find('.precio-original-label')
        .text(
            simboloMonedaVenta()
            + ' '
            + precioOriginal.toFixed(2)
        );

    const precioFueModificado = (
        precioOriginal > 0
        && Math.abs(precioFinal - precioOriginal) > 0.000001
    );

    $fila.toggleClass(
        'es-oferta',
        precioFueModificado
    );

    calcularTotales();
    actualizarMensajePedido();

    return true;
}


function guardarEdicionProductoPedido() {
    const indice = Number.parseInt(
        $('#editarPedidoIndice').val(),
        10
    );

    const $fila = $("#fila" + indice);

    if (!$fila.length) {
        $('#modalEditarProductoPedido').modal('hide');

        Swal.fire(
            'Producto no disponible',
            'El producto ya no se encuentra en el pedido.',
            'warning'
        );
        return;
    }

    const nombreProducto = String(
        $('#editarPedidoNombreInput').val() || ''
    ).trim();

    const precioTexto = String(
        $('#editarPedidoPrecio').val() || ''
    )
        .replace(',', '.')
        .trim();

    const precioVenta = Number.parseFloat(
        precioTexto
    );

    if (nombreProducto === '') {
        Swal.fire(
            'Nombre requerido',
            'Ingrese el nombre que desea mostrar para este producto.',
            'warning'
        );
        return;
    }

    if (!Number.isFinite(precioVenta) || precioVenta <= 0) {
        Swal.fire(
            'Precio inválido',
            'Ingrese un precio de venta mayor que cero.',
            'warning'
        );
        return;
    }

    if (!aplicarEdicionProductoPedido(
        indice,
        nombreProducto,
        precioVenta
    )) {
        Swal.fire(
            'No se pudo guardar',
            'Revise el nombre y el precio del producto.',
            'error'
        );
        return;
    }

    $('#modalEditarProductoPedido').modal('hide');

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1300,
        timerProgressBar: true
    });

    Toast.fire({
        icon: 'success',
        title: 'Producto actualizado en esta venta'
    });
}


$(document).on(
    'click',
    '#btnGuardarEdicionProductoPedido',
    guardarEdicionProductoPedido
);

$(document).on(
    'keydown',
    '#editarPedidoNombreInput, #editarPedidoPrecio',
    function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            guardarEdicionProductoPedido();
        }
    }
);


function modificarSubtotales() {
    let total = 0;

    $("span[name='subtotal']").each(function () {
        let valor = parseFloat($(this).text()) || 0;
        total += valor;
    });

    $("#totalGeneral").text(simboloMonedaVenta() + total.toFixed(2));
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

    const $filas = $("#detallesCards .filas");
    const cantidadProductos = $filas.length;
    let totalUnidades = 0;

    $("#detallesCards input[name='cantidad[]']").each(function () {
        totalUnidades += Number.parseInt($(this).val(), 10) || 0;
    });

    const hayProductos = cantidadProductos > 0;

    if (hayProductos) {
        $("#contenedorPedido").addClass("con-items");
    } else {
        $("#contenedorPedido").removeClass("con-items");
    }

    const textoProductos =
        cantidadProductos === 1
            ? "1 producto"
            : cantidadProductos + " productos";

    const textoUnidades =
        totalUnidades === 1
            ? "1 unidad"
            : totalUnidades + " unidades";

    $("#contadorProductosPedido").text(
        textoProductos + " · " + textoUnidades
    );

    const totalActual = Number(
        totalVentaActual().toFixed(2)
    );

    $("#totalPedidoHeader").text(
        simboloMonedaVenta() + " " + totalActual.toFixed(2)
    );
}



function calcularVuelto() {

    // 🔹 detectar forma de pago desde el select (BD)
    let nombreForma = getNombreFormaPago();

    // 🔴 si es Mixto, este cálculo NO aplica
    if (esPagoCombinadoSeleccionado()) {
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
    e.preventDefault();

    const condicion = $('#condicion_pago').val();

    const totalVenta = Number(
        totalVentaActual().toFixed(2)
    );

    if (totalVenta <= 0) {
        Swal.fire(
            'Venta vacía',
            'Debe agregar al menos un producto.',
            'warning'
        );

        return false;
    }

    if (!validarDatosAdicionalesVenta()) {
        return false;
    }

    if (!validarClienteAntesDeVender(totalVenta)) {
        return false;
    }

    /*
     * PAGO AL CRÉDITO
     */
    if (
        textoNormalizado(condicion) === 'CREDITO'
    ) {
        const cuotas = Number.parseInt(
            $('#numero_cuotas').val(),
            10
        ) || 0;

        if (cuotas < 1) {
            Swal.fire(
                'Crédito',
                'Debe ingresar el número de cuotas.',
                'warning'
            );

            return false;
        }

        guardarVenta();
        return false;
    }

    /*
     * PAGO NORMAL
     */
    if (!esPagoCombinadoSeleccionado()) {
        const recibido = Number.parseFloat(
            $('#total_recibido').val()
        ) || 0;

        if (recibido < totalVenta) {
            Swal.fire(
                'Pago incompleto',
                'El monto recibido no cubre el total de la venta.',
                'warning'
            );

            return false;
        }

        guardarVenta();
        return false;
    }

    /*
     * PAGO MIXTO
     */
    const $filas = $(
        '#pagosMixtosContainer .pago-mixto-fila'
    );

    if ($filas.length < 2) {
        Swal.fire(
            'Pago mixto',
            'Debe registrar al menos dos formas de pago.',
            'warning'
        );

        return false;
    }

    let totalPagado = 0;
    let filaIncompleta = false;

    const formasSeleccionadas = new Set();

    $filas.each(function () {
        const idformaPago = Number(
            $(this).find('.pago-metodo').val()
        ) || 0;

        const monto = Number.parseFloat(
            $(this).find('.pago-monto').val()
        ) || 0;

        if (idformaPago <= 0 || monto <= 0) {
            filaIncompleta = true;
            return false;
        }

        formasSeleccionadas.add(
            idformaPago
        );

        totalPagado += monto;
    });

    if (filaIncompleta) {
        Swal.fire(
            'Pago mixto incompleto',
            'Seleccione una forma de pago e ingrese un monto mayor que cero en cada fila.',
            'warning'
        );

        return false;
    }

    if (formasSeleccionadas.size < 2) {
        Swal.fire(
            'Pago mixto inválido',
            'Debe utilizar al menos dos formas de pago diferentes.',
            'warning'
        );

        return false;
    }

    totalPagado = Number(
        totalPagado.toFixed(2)
    );

    const diferencia = Math.abs(
        totalPagado - totalVenta
    );

    if (diferencia > 0.01) {
        Swal.fire(
            'Total de pagos incorrecto',
            'La suma de los pagos debe ser exactamente ' + simboloMonedaVenta() + ' ' +
                totalVenta.toFixed(2) +
                '. Actualmente ingresó ' + simboloMonedaVenta() + ' ' +
                totalPagado.toFixed(2) +
                '.',
            'warning'
        );

        return false;
    }

    guardarVenta();
    return false;
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

    if (esPagoCombinadoSeleccionado()) {
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


let formasPagoMixto = [];
let pagoMixtoIndex = 0;
let solicitudFormasPagoMixto = null;

/*
 * El gestor de ventas en cola necesita continuar la numeración de los
 * campos pagos[i] al restaurar una pestaña.
 */
window.ventaPosEstablecerPagoMixtoIndex = function (valor) {
    pagoMixtoIndex = Math.max(
        Number.parseInt(valor, 10) || 0,
        0
    );
};

function escaparHtml(valor) {
    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function esPagoCombinadoSeleccionado() {
    const valor = Number(
        $('#forma_pago option:selected')
            .attr('data-combinado') || 0
    );

    return valor === 1;
}

function cargarFormasPagoMixto() {

    if (solicitudFormasPagoMixto) {
        return solicitudFormasPagoMixto;
    }

    solicitudFormasPagoMixto = $.ajax({
        url: 'Controllers/Paymentformat.php?op=selectFormaPagoMixto',
        type: 'GET',
        dataType: 'json',
        cache: false
    }).done(function (respuesta) {

        if (
            respuesta &&
            respuesta.success === true &&
            Array.isArray(respuesta.data)
        ) {
            formasPagoMixto = respuesta.data;
        } else {
            formasPagoMixto = [];
        }

    }).fail(function (xhr) {

        console.error(
            'No se cargaron las formas de pago:',
            xhr.responseText
        );

        formasPagoMixto = [];

    }).always(function () {
        solicitudFormasPagoMixto = null;
    });

    return solicitudFormasPagoMixto;
}

function construirOpcionesPagoMixto() {

    let opciones = '<option value="">Seleccione</option>';

    formasPagoMixto.forEach(function (formaPago) {

        const idformaPago = Number(
            formaPago.idforma_pago
        ) || 0;

        if (idformaPago <= 0) {
            return;
        }

        const nombre = escaparHtml(
            formaPago.nombre
        );

        const esEfectivo = Number(
            formaPago.es_efectivo
        ) || 0;

        opciones += `
            <option
                value="${idformaPago}"
                data-efectivo="${esEfectivo}">
                ${nombre}
            </option>
        `;
    });

    return opciones;
}

function agregarPagoMixtoFila() {

    if (formasPagoMixto.length === 0) {

        cargarFormasPagoMixto().done(function () {

            if (formasPagoMixto.length === 0) {
                Swal.fire(
                    'Formas de pago',
                    'No existen formas de pago activas disponibles.',
                    'warning'
                );

                return;
            }

            agregarPagoMixtoFila();
        });

        return;
    }

    const i = pagoMixtoIndex++;
    const opciones = construirOpcionesPagoMixto();

    const fila = `
        <div
            class="row g-2 align-items-center mb-2 pago-mixto-fila"
            data-i="${i}">

            <div class="col-md-6">
                <select
                    class="form-control form-select pago-metodo"
                    name="pagos[${i}][idforma_pago]">

                    ${opciones}

                </select>
            </div>

            <div class="col-md-4">
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    class="form-control pago-monto"
                    name="pagos[${i}][monto]"
                    placeholder="Monto">
            </div>

            <div class="col-md-2 text-end">
                <button
                    type="button"
                    class="btn btn-outline-danger btn-sm btnQuitarPago">

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
    let esPorcentaje = !$('#descuentoSwitch').is(':checked');

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
    const totalVenta = Number(
        totalVentaActual().toFixed(2)
    );

    let totalPagado = 0;
    let efectivo = 0;
    let noEfectivo = 0;

    $('#pagosMixtosContainer .pago-mixto-fila').each(function () {
        const $select = $(this).find('.pago-metodo');

        const idformaPago = Number(
            $select.val()
        ) || 0;

        const monto = Number.parseFloat(
            $(this).find('.pago-monto').val()
        ) || 0;

        /*
         * No sumar filas incompletas.
         */
        if (idformaPago <= 0 || monto <= 0) {
            return;
        }

        const esEfectivo =
            Number(
                $select
                    .find('option:selected')
                    .attr('data-efectivo') || 0
            ) === 1;

        totalPagado += monto;

        if (esEfectivo) {
            efectivo += monto;
        } else {
            noEfectivo += monto;
        }
    });

    totalPagado = Number(
        totalPagado.toFixed(2)
    );

    $('#total_recibido').val(
        totalPagado.toFixed(2)
    );

    /*
     * El vuelto solamente puede proceder
     * del importe entregado en efectivo.
     */
    const importePendienteEfectivo = Math.max(
        totalVenta - noEfectivo,
        0
    );

    const vuelto = Math.max(
        efectivo - importePendienteEfectivo,
        0
    );

    $('#vuelto').val(
        vuelto.toFixed(2)
    );
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

/* =========================================================
   TECLADO NUMÉRICO VIRTUAL DEL POS
   - Escritorio: muestra keypad al enfocar Descuento/Recibido.
   - Móvil/tablet angosta: usa el teclado numérico nativo mediante inputmode.
========================================================== */
(function () {
    'use strict';

    const SELECTOR = '[data-venta-keypad="decimal"]';
    const DESKTOP_QUERY = window.matchMedia('(min-width: 768px) and (hover: hover) and (pointer: fine)');
    let keypad = null;
    let activeInput = null;

    function normalizarDecimal(value) {
        let limpio = String(value ?? '')
            .replace(',', '.')
            .replace(/[^0-9.]/g, '');

        const primerPunto = limpio.indexOf('.');
        if (primerPunto !== -1) {
            limpio = limpio.slice(0, primerPunto + 1) + limpio.slice(primerPunto + 1).replace(/\./g, '');
        }

        return limpio;
    }

    function limiteCampo(input) {
        if (!input) return null;
        const max = Number.parseFloat(input.getAttribute('max'));
        return Number.isFinite(max) ? max : null;
    }

    function aplicarValor(input, value) {
        if (!input) return;

        let limpio = normalizarDecimal(value);
        const max = limiteCampo(input);
        const numero = Number.parseFloat(limpio);

        if (max !== null && Number.isFinite(numero) && numero > max) {
            limpio = String(max);
        }

        input.value = limpio;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function crearKeypad() {
        if (keypad) return keypad;

        keypad = document.createElement('div');
        keypad.className = 'venta-keypad';
        keypad.setAttribute('role', 'dialog');
        keypad.setAttribute('aria-label', 'Teclado numérico');
        keypad.innerHTML = `
            <div class="venta-keypad__grid">
                <button type="button" class="venta-keypad__btn" data-key="7">7</button>
                <button type="button" class="venta-keypad__btn" data-key="8">8</button>
                <button type="button" class="venta-keypad__btn" data-key="9">9</button>
                <button type="button" class="venta-keypad__btn venta-keypad__btn--action" data-key="backspace" aria-label="Borrar último dígito">⌫</button>

                <button type="button" class="venta-keypad__btn" data-key="4">4</button>
                <button type="button" class="venta-keypad__btn" data-key="5">5</button>
                <button type="button" class="venta-keypad__btn" data-key="6">6</button>
                <button type="button" class="venta-keypad__btn venta-keypad__btn--action" data-key="clear">C</button>

                <button type="button" class="venta-keypad__btn" data-key="1">1</button>
                <button type="button" class="venta-keypad__btn" data-key="2">2</button>
                <button type="button" class="venta-keypad__btn" data-key="3">3</button>
                <button type="button" class="venta-keypad__btn" data-key="decimal">.</button>

                <button type="button" class="venta-keypad__btn" data-key="00">00</button>
                <button type="button" class="venta-keypad__btn" data-key="0">0</button>
                <button type="button" class="venta-keypad__btn venta-keypad__btn--ok" data-key="done">Listo</button>
            </div>
        `;

        document.body.appendChild(keypad);

        keypad.addEventListener('pointerdown', function (event) {
            // Evita que el input pierda foco antes de procesar la tecla.
            event.preventDefault();
        });

        keypad.addEventListener('click', function (event) {
            const button = event.target.closest('[data-key]');
            if (!button || !activeInput) return;

            const key = button.dataset.key;
            let value = normalizarDecimal(activeInput.value);

            if (key === 'done') {
                const input = activeInput;
                cerrarKeypad();
                input?.blur();
                return;
            }

            if (key === 'clear') {
                aplicarValor(activeInput, '');
                return;
            }

            if (key === 'backspace') {
                aplicarValor(activeInput, value.slice(0, -1));
                return;
            }

            if (key === 'decimal') {
                if (!value.includes('.')) {
                    aplicarValor(activeInput, value === '' ? '0.' : value + '.');
                }
                return;
            }

            aplicarValor(activeInput, value + key);
        });

        return keypad;
    }

    function posicionarKeypad(input) {
        if (!keypad || !input) return;

        const rect = input.getBoundingClientRect();
        const width = keypad.offsetWidth || 236;
        const height = keypad.offsetHeight || 210;
        const gap = 8;
        const margen = 10;

        let left = rect.left + (rect.width / 2) - (width / 2);
        left = Math.max(margen, Math.min(left, window.innerWidth - width - margen));

        let top = rect.bottom + gap;
        if (top + height > window.innerHeight - margen) {
            top = Math.max(margen, rect.top - height - gap);
        }

        keypad.style.left = `${Math.round(left)}px`;
        keypad.style.top = `${Math.round(top)}px`;
    }

    function abrirKeypad(input) {
        if (!DESKTOP_QUERY.matches || !input || input.readOnly || input.disabled) return;

        activeInput = input;
        crearKeypad();
        keypad.classList.add('is-open');
        posicionarKeypad(input);
    }

    function cerrarKeypad() {
        if (keypad) keypad.classList.remove('is-open');
        activeInput = null;
    }

    /*
     * Al hacer click/tap para empezar una nueva captura, el valor anterior
     * se limpia una sola vez. Si el usuario vuelve a hacer click mientras
     * el mismo campo sigue enfocado, no se borra lo que ya está escribiendo.
     *
     * Esto aplica a Descuento y Recibido:
     * - escritorio: antes de abrir el keypad del POS;
     * - móvil: antes de mostrar el teclado numérico nativo.
     */
    document.addEventListener('pointerdown', function (event) {
        const input = event.target.closest?.(SELECTOR);
        if (!input || input.readOnly || input.disabled) return;

        if (document.activeElement !== input) {
            aplicarValor(input, '');
        }
    });

    document.addEventListener('focusin', function (event) {
        const input = event.target.closest?.(SELECTOR);
        if (input) abrirKeypad(input);
    });

    document.addEventListener('click', function (event) {
        const input = event.target.closest?.(SELECTOR);
        if (input) {
            abrirKeypad(input);
            return;
        }

        if (keypad && keypad.classList.contains('is-open') && !keypad.contains(event.target)) {
            cerrarKeypad();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && keypad?.classList.contains('is-open')) {
            cerrarKeypad();
        }

        if (event.key === 'Enter' && activeInput && keypad?.classList.contains('is-open')) {
            const input = activeInput;
            cerrarKeypad();
            input?.blur();
        }
    });

    // Mantiene el contenido numérico también cuando se usa teclado físico.
    document.addEventListener('input', function (event) {
        const input = event.target.closest?.(SELECTOR);
        if (!input || input.dataset.ventaKeypadSanitizing === '1') return;

        let limpio = normalizarDecimal(input.value);
        const max = limiteCampo(input);
        const numero = Number.parseFloat(limpio);

        if (max !== null && Number.isFinite(numero) && numero > max) {
            limpio = String(max);
        }

        if (limpio !== input.value) {
            input.dataset.ventaKeypadSanitizing = '1';
            input.value = limpio;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            delete input.dataset.ventaKeypadSanitizing;
        }
    });

    window.addEventListener('resize', function () {
        if (!DESKTOP_QUERY.matches) {
            cerrarKeypad();
            return;
        }
        if (activeInput && keypad?.classList.contains('is-open')) {
            posicionarKeypad(activeInput);
        }
    });

    window.addEventListener('scroll', function () {
        if (activeInput && keypad?.classList.contains('is-open')) {
            posicionarKeypad(activeInput);
        }
    }, true);
})();
