(function ($, window, document) {
    'use strict';

    const VERSION = 1;
    const MAX_VENTAS = 8;
    const PREFIJO_STORAGE = 'tiquepos.pos.cola.v1';

    let cola = {
        version: VERSION,
        activeId: null,
        nextNumber: 1,
        tabs: []
    };

    let inicializada = false;
    let restaurando = false;
    let bloqueada = false;
    let estadoBase = null;
    let temporizadorGuardado = null;
    let observadorFormulario = null;
    let promesaInicializacion = null;

    function numeroSeguro(valor, defecto = 0) {
        const numero = Number.parseFloat(valor);
        return Number.isFinite(numero)
            ? numero
            : defecto;
    }

    function enteroSeguro(valor, defecto = 0) {
        const numero = Number.parseInt(valor, 10);
        return Number.isFinite(numero)
            ? numero
            : defecto;
    }

    function escaparHtml(valor) {
        return String(valor ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function clonar(valor) {
        return JSON.parse(
            JSON.stringify(valor)
        );
    }

    function generarId() {
        return (
            'v'
            + Date.now().toString(36)
            + Math.random()
                .toString(36)
                .slice(2, 8)
        );
    }

    function obtenerShell() {
        return document.getElementById(
            'ventaColaShell'
        );
    }

    function obtenerClaveStorage() {
        const shell = obtenerShell();

        if (!shell) {
            return PREFIJO_STORAGE;
        }

        const usuario =
            String(
                shell.dataset.usuario || '0'
            );

        const sucursal =
            String(
                shell.dataset.sucursal || '0'
            );

        const caja =
            String(
                shell.dataset.caja || '0'
            );

        return [
            PREFIJO_STORAGE,
            'u' + usuario,
            's' + sucursal,
            'c' + caja
        ].join('.');
    }

    function leerStorage() {
        try {
            const crudo = window.sessionStorage.getItem(
                obtenerClaveStorage()
            );

            if (!crudo) {
                return null;
            }

            const datos = JSON.parse(crudo);

            if (
                !datos
                || datos.version !== VERSION
                || !Array.isArray(datos.tabs)
            ) {
                return null;
            }

            return datos;
        } catch (error) {
            console.warn(
                'No se pudo recuperar la cola de ventas:',
                error
            );

            return null;
        }
    }

    function guardarStorage() {
        try {
            window.sessionStorage.setItem(
                obtenerClaveStorage(),
                JSON.stringify(cola)
            );
        } catch (error) {
            console.warn(
                'No se pudo guardar la cola de ventas:',
                error
            );
        }
    }

    function textoClienteActual() {
        const texto = String(
            $('#nombre_cliente').text() || ''
        ).trim();

        return texto;
    }

    function obtenerIndicePagoMixtoSiguiente() {
        let maximo = -1;

        $('#pagosMixtosContainer .pago-mixto-fila')
            .each(function () {
                maximo = Math.max(
                    maximo,
                    enteroSeguro(
                        $(this).attr('data-i'),
                        -1
                    )
                );
            });

        return maximo + 1;
    }

    function capturarEstadoFormulario() {
        const total =
            typeof window.totalVentaActual === 'function'
                ? numeroSeguro(
                    window.totalVentaActual(),
                    0
                )
                : 0;

        const panelResponsive =
            $('#ventaPanelProductos')
                .hasClass('venta-panel-activo')
                ? 'productos'
                : 'datos';

        return {
            tipoComprobante:
                String(
                    $('#tipo_comprobante').val() || ''
                ),

            cliente: {
                id:
                    String(
                        $('#idcliente').val() || ''
                    ),
                generico:
                    String(
                        $('#cliente_generico').val() || '0'
                    ),
                tipoDocumento:
                    String(
                        $('#tipo_documento').val() || ''
                    ),
                numeroReal:
                    String(
                        $('#num_doc_real').val() || ''
                    ),
                documentoVisible:
                    String(
                        $('#num_documento').val() || ''
                    ),
                nombre:
                    String(
                        $('#nombre_cli').val() || ''
                    ),
                direccion:
                    String(
                        $('#direccion').val() || ''
                    ),
                email:
                    String(
                        $('#email').val() || ''
                    ),
                textoAyuda:
                    textoClienteActual(),
                claseAyuda:
                    String(
                        $('#nombre_cliente')
                            .attr('class') || ''
                    )
            },

            celular:
                String(
                    $('#celular').val() || ''
                ),

            tipoPago:
                String(
                    $('#tipo_pago').val() || ''
                ),

            condicionPago:
                String(
                    $('#condicion_pago').val() || ''
                ),

            formaPago:
                String(
                    $('#forma_pago').val() || ''
                ),

            credito: {
                numeroCuotas:
                    String(
                        $('#numero_cuotas').val() || ''
                    ),
                montoCuota:
                    String(
                        $('#monto_cuota').val() || ''
                    ),
                montoCuotaReal:
                    String(
                        $('#monto_cuota_real').val()
                        || '0.00'
                    ),
                fechaPago:
                    String(
                        $('#fecha_pago').val() || ''
                    )
            },

            tipoOperacionSunat:
                String(
                    $('#tipo_operacion_sunat').val()
                    || ''
                ),

            descuento: {
                esPorcentaje:
                    $('#descuentoSwitch')
                        .is(':checked'),
                valor:
                    String(
                        $('#descuentoPorcentaje').val()
                        || '0'
                    )
            },

            totalRecibido:
                String(
                    $('#total_recibido').val() || ''
                ),

            totalRecibidoManual:
                $('#total_recibido')
                    .data('manual') === true,

            vuelto:
                String(
                    $('#vuelto').val() || '0.00'
                ),

            modoEnvio:
                String(
                    $('#modo_envio').val()
                    || 'inmediato'
                ),

            detallesHtml:
                String(
                    $('#detallesCards').html() || ''
                ),

            cont:
                enteroSeguro(
                    window.cont,
                    0
                ),

            pagosMixtosHtml:
                String(
                    $('#pagosMixtosContainer').html()
                    || ''
                ),

            pagoMixtoIndex:
                obtenerIndicePagoMixtoSiguiente(),

            panelResponsive:
                panelResponsive,

            total:
                Number(total.toFixed(2)),

            updatedAt:
                Date.now()
        };
    }

    function crearEstadoVacio() {
        const base = clonar(
            estadoBase
            || capturarEstadoFormulario()
        );

        base.cliente = {
            id: '',
            generico: '0',
            tipoDocumento: '',
            numeroReal: '',
            documentoVisible: '',
            nombre: '',
            direccion: '',
            email: '',
            textoAyuda:
                'Déjelo vacío para usar CLIENTE VARIOS.',
            claseAyuda:
                'text-muted d-block mt-2'
        };

        base.celular = '';
        base.credito = {
            numeroCuotas: '',
            montoCuota: '',
            montoCuotaReal: '0.00',
            fechaPago: ''
        };

        base.descuento = {
            esPorcentaje: true,
            valor: '0'
        };

        base.totalRecibido = '';
        base.totalRecibidoManual = false;
        base.vuelto = '0.00';
        base.detallesHtml = '';
        base.cont = 0;
        base.pagosMixtosHtml = '';
        base.pagoMixtoIndex = 0;
        base.panelResponsive = 'datos';
        base.total = 0;
        base.updatedAt = Date.now();

        return base;
    }

    function buscarTab(id) {
        return cola.tabs.find(
            function (tab) {
                return tab.id === id;
            }
        ) || null;
    }

    function tabActiva() {
        return buscarTab(
            cola.activeId
        );
    }

    function nombreNuevaVenta() {
        const numero = Math.max(
            enteroSeguro(
                cola.nextNumber,
                1
            ),
            1
        );

        cola.nextNumber = numero + 1;

        return 'Venta ' + numero;
    }

    function nuevaTab(
        estado = null,
        nombre = ''
    ) {
        const tab = {
            id: generarId(),
            name:
                String(nombre || '').trim()
                || nombreNuevaVenta(),
            createdAt: Date.now(),
            state:
                clonar(
                    estado || crearEstadoVacio()
                )
        };

        cola.tabs.push(tab);
        cola.activeId = tab.id;

        return tab;
    }

    function totalTab(tab) {
        return numeroSeguro(
            tab
            && tab.state
            && tab.state.total,
            0
        );
    }

    function renderizarTabs() {
        const $contenedor =
            $('#ventaColaTabs');

        if (!$contenedor.length) {
            return;
        }

        let html = '';

        cola.tabs.forEach(function (tab) {
            const activa =
                tab.id === cola.activeId;

            html += `
                <div
                    class="venta-cola-tab-item ${activa ? 'active' : ''}"
                    data-venta-cola-id="${escaparHtml(tab.id)}">

                    <button
                        type="button"
                        class="venta-cola-tab"
                        data-venta-cola-seleccionar="${escaparHtml(tab.id)}"
                        role="tab"
                        aria-selected="${activa ? 'true' : 'false'}"
                        title="${escaparHtml(tab.name)}">

                        <span class="venta-cola-tab-icono">
                            <i class="bi bi-receipt"></i>
                        </span>

                        <span class="venta-cola-tab-contenido">
                            <span class="venta-cola-tab-nombre">
                                ${escaparHtml(tab.name)}
                            </span>

                            <span class="venta-cola-tab-total">
                                S/ ${totalTab(tab).toFixed(2)}
                            </span>
                        </span>
                    </button>

                    <button
                        type="button"
                        class="venta-cola-tab-menu-btn"
                        data-venta-cola-menu="${escaparHtml(tab.id)}"
                        aria-label="Opciones de ${escaparHtml(tab.name)}"
                        title="Opciones">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                </div>
            `;
        });

        $contenedor.html(html);

        window.requestAnimationFrame(
            function () {
                const activa =
                    $contenedor
                        .find(
                            '.venta-cola-tab-item.active'
                        )
                        .get(0);

                if (activa) {
                    activa.scrollIntoView({
                        block: 'nearest',
                        inline: 'nearest'
                    });
                }
            }
        );
    }

    function cerrarMenu() {
        $('#ventaColaMenu')
            .removeClass('show')
            .attr('aria-hidden', 'true')
            .removeAttr('data-venta-cola-id');
    }

    function abrirMenu(
        id,
        boton
    ) {
        const $menu =
            $('#ventaColaMenu');

        if (
            !$menu.length
            || !boton
        ) {
            return;
        }

        $menu
            .attr(
                'data-venta-cola-id',
                id
            )
            .addClass('show')
            .attr('aria-hidden', 'false');

        const rect =
            boton.getBoundingClientRect();

        const ancho =
            $menu.outerWidth() || 190;

        const alto =
            $menu.outerHeight() || 140;

        let left =
            Math.min(
                rect.right - ancho,
                window.innerWidth - ancho - 8
            );

        left = Math.max(
            8,
            left
        );

        let top =
            rect.bottom + 6;

        if (
            top + alto
            > window.innerHeight - 8
        ) {
            top =
                Math.max(
                    8,
                    rect.top - alto - 6
                );
        }

        $menu.css({
            left:
                Math.round(left) + 'px',
            top:
                Math.round(top) + 'px'
        });
    }

    function guardarActual(
        renderizar = true
    ) {
        if (
            !inicializada
            || restaurando
        ) {
            return;
        }

        const tab =
            tabActiva();

        if (!tab) {
            return;
        }

        tab.state =
            capturarEstadoFormulario();

        guardarStorage();

        if (renderizar) {
            renderizarTabs();
        }
    }

    function programarGuardado() {
        if (
            !inicializada
            || restaurando
            || bloqueada
        ) {
            return;
        }

        window.clearTimeout(
            temporizadorGuardado
        );

        temporizadorGuardado =
            window.setTimeout(
                function () {
                    guardarActual(true);
                },
                140
            );
    }

    function mostrarBloquesSegunEstado(
        estado
    ) {
        const condicion =
            String(
                estado.condicionPago || ''
            )
                .trim()
                .toUpperCase()
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                );

        if (
            condicion.includes('CREDITO')
        ) {
            $('#bloque_credito')
                .stop(true, true)
                .show();
        } else {
            $('#bloque_credito')
                .stop(true, true)
                .hide();
        }

        const esMixto =
            Number(
                $('#forma_pago option:selected')
                    .attr('data-combinado')
                    || 0
            ) === 1;

        if (esMixto) {
            $('#bloque_pago_mixto')
                .stop(true, true)
                .show();

            $('#total_recibido')
                .prop('readonly', true)
                .addClass('bg-light');
        } else {
            $('#bloque_pago_mixto')
                .stop(true, true)
                .hide();

            $('#total_recibido')
                .prop('readonly', false)
                .removeClass('bg-light');
        }
    }

    function restaurarEstado(
        estado
    ) {
        if (!estado) {
            return;
        }

        restaurando = true;

        try {
            const formulario =
                document.getElementById(
                    'formularioVenta'
                );

            if (formulario) {
                formulario.reset();
            }

            $('#detallesCards').html(
                String(
                    estado.detallesHtml || ''
                )
            );

            window.cont =
                enteroSeguro(
                    estado.cont,
                    0
                );

            $('#tipo_comprobante')
                .val(
                    String(
                        estado.tipoComprobante
                        || ''
                    )
                )
                .trigger('change');

            const cliente =
                estado.cliente || {};

            $('#idcliente').val(
                String(
                    cliente.id || ''
                )
            );

            $('#cliente_generico').val(
                String(
                    cliente.generico || '0'
                )
            );

            $('#tipo_documento').val(
                String(
                    cliente.tipoDocumento || ''
                )
            );

            $('#num_doc_real').val(
                String(
                    cliente.numeroReal || ''
                )
            );

            $('#num_documento').val(
                String(
                    cliente.documentoVisible || ''
                )
            );

            $('#nombre_cli').val(
                String(
                    cliente.nombre || ''
                )
            );

            $('#direccion').val(
                String(
                    cliente.direccion || ''
                )
            );

            $('#email').val(
                String(
                    cliente.email || ''
                )
            );

            $('#celular').val(
                String(
                    estado.celular || ''
                )
            );

            $('#tipo_pago')
                .val(
                    String(
                        estado.tipoPago || ''
                    )
                )
                .trigger('change');

            $('#condicion_pago').val(
                String(
                    estado.condicionPago || ''
                )
            );

            const credito =
                estado.credito || {};

            $('#numero_cuotas').val(
                String(
                    credito.numeroCuotas || ''
                )
            );

            $('#monto_cuota').val(
                String(
                    credito.montoCuota || ''
                )
            );

            $('#monto_cuota_real').val(
                String(
                    credito.montoCuotaReal || '0.00'
                )
            );

            $('#fecha_pago').val(
                String(
                    credito.fechaPago || ''
                )
            );

            $('#forma_pago')
                .val(
                    String(
                        estado.formaPago || ''
                    )
                )
                .trigger('change');

            $('#pagosMixtosContainer').html(
                String(
                    estado.pagosMixtosHtml || ''
                )
            );

            if (
                typeof window
                    .ventaPosEstablecerPagoMixtoIndex
                === 'function'
            ) {
                window
                    .ventaPosEstablecerPagoMixtoIndex(
                        Math.max(
                            enteroSeguro(
                                estado.pagoMixtoIndex,
                                0
                            ),
                            obtenerIndicePagoMixtoSiguiente()
                        )
                    );
            }

            $('#tipo_operacion_sunat')
                .val(
                    String(
                        estado.tipoOperacionSunat
                        || ''
                    )
                )
                .trigger('change');

            const descuento =
                estado.descuento || {};

            $('#descuentoSwitch')
                .prop(
                    'checked',
                    descuento.esPorcentaje !== false
                )
                .trigger('change');

            $('#descuentoPorcentaje')
                .val(
                    String(
                        descuento.valor || '0'
                    )
                )
                .trigger('input');

            $('#modo_envio')
                .val(
                    String(
                        estado.modoEnvio
                        || 'inmediato'
                    )
                )
                .trigger('change');

            mostrarBloquesSegunEstado(
                estado
            );

            if (
                typeof window.calcularTotales
                === 'function'
            ) {
                window.calcularTotales();
            }

            const esMixto =
                Number(
                    $('#forma_pago option:selected')
                        .attr('data-combinado')
                        || 0
                ) === 1;

            if (
                esMixto
                && typeof window.calcularPagoMixtoForma
                    === 'function'
            ) {
                window.calcularPagoMixtoForma();
            } else {
                $('#total_recibido')
                    .val(
                        String(
                            estado.totalRecibido
                            || ''
                        )
                    )
                    .data(
                        'manual',
                        estado.totalRecibidoManual
                        === true
                    );

                $('#vuelto').val(
                    String(
                        estado.vuelto || '0.00'
                    )
                );
            }

            if (
                typeof window.actualizarMensajePedido
                === 'function'
            ) {
                window.actualizarMensajePedido();
            }

            if (
                typeof window.aplicarPanelVentaResponsive
                === 'function'
            ) {
                window.aplicarPanelVentaResponsive(
                    estado.panelResponsive
                    === 'productos'
                        ? 'productos'
                        : 'datos',
                    {
                        desplazar: false,
                        enfocar: false
                    }
                );
            }

            /*
             * La regla del comprobante puede haber reemplazado
             * el texto del cliente. Restaurarlo al final.
             */
            if (
                cliente.claseAyuda
            ) {
                $('#nombre_cliente')
                    .attr(
                        'class',
                        String(
                            cliente.claseAyuda
                        )
                    );
            }

            if (
                typeof cliente.textoAyuda
                === 'string'
                && cliente.textoAyuda !== ''
            ) {
                $('#nombre_cliente').text(
                    cliente.textoAyuda
                );
            }

            if (
                typeof window.mostrarSerieNumero
                === 'function'
            ) {
                window.mostrarSerieNumero();
            }
        } finally {
            restaurando = false;
        }
    }

    function cambiarTab(id) {
        if (
            bloqueada
            || id === cola.activeId
        ) {
            return;
        }

        const destino =
            buscarTab(id);

        if (!destino) {
            return;
        }

        guardarActual(false);
        cola.activeId = id;
        guardarStorage();
        renderizarTabs();
        restaurarEstado(
            destino.state
        );
    }

    function estadoTieneContenido(
        estado
    ) {
        if (!estado) {
            return false;
        }

        if (
            String(
                estado.detallesHtml || ''
            ).trim() !== ''
        ) {
            return true;
        }

        const cliente =
            estado.cliente || {};

        if (
            String(
                cliente.documentoVisible
                || ''
            ).trim() !== ''
            || String(
                cliente.nombre || ''
            ).trim() !== ''
            || String(
                estado.celular || ''
            ).trim() !== ''
        ) {
            return true;
        }

        return numeroSeguro(
            estado
            && estado.descuento
            && estado.descuento.valor,
            0
        ) > 0;
    }

    function crearNuevaVenta() {
        if (bloqueada) {
            return;
        }

        if (
            cola.tabs.length
            >= MAX_VENTAS
        ) {
            Swal.fire({
                icon: 'warning',
                title: 'Límite de ventas abiertas',
                text:
                    'Puede mantener hasta '
                    + MAX_VENTAS
                    + ' ventas en cola al mismo tiempo.'
            });

            return;
        }

        guardarActual(false);

        const tab =
            nuevaTab();

        guardarStorage();
        renderizarTabs();
        restaurarEstado(
            tab.state
        );
    }

    function renombrarTab(id) {
        const tab =
            buscarTab(id);

        if (
            !tab
            || bloqueada
        ) {
            return;
        }

        Swal.fire({
            title: 'Renombrar venta',
            input: 'text',
            inputValue: tab.name,
            inputPlaceholder:
                'Ej.: Mesa 3, María, Pedido oficina',
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            inputAttributes: {
                maxlength: '40',
                autocomplete: 'off'
            },
            inputValidator:
                function (valor) {
                    if (
                        String(valor || '')
                            .trim() === ''
                    ) {
                        return 'Ingrese un nombre para la pestaña.';
                    }

                    return undefined;
                }
        }).then(function (resultado) {
            if (!resultado.isConfirmed) {
                return;
            }

            tab.name =
                String(
                    resultado.value || ''
                )
                    .trim()
                    .slice(0, 40);

            guardarStorage();
            renderizarTabs();
        });
    }

    function duplicarTab(id) {
        if (
            bloqueada
            || cola.tabs.length
                >= MAX_VENTAS
        ) {
            if (
                cola.tabs.length
                >= MAX_VENTAS
            ) {
                Swal.fire(
                    'Límite alcanzado',
                    'Cierre una venta antes de duplicar otra pestaña.',
                    'warning'
                );
            }

            return;
        }

        if (id === cola.activeId) {
            guardarActual(false);
        }

        const original =
            buscarTab(id);

        if (!original) {
            return;
        }

        const copia =
            nuevaTab(
                original.state,
                (
                    original.name
                    + ' copia'
                ).slice(0, 40)
            );

        copia.state.updatedAt =
            Date.now();

        guardarStorage();
        renderizarTabs();
        restaurarEstado(
            copia.state
        );
    }

    function eliminarTabSinConfirmar(id) {
        const indice =
            cola.tabs.findIndex(
                function (tab) {
                    return tab.id === id;
                }
            );

        if (indice < 0) {
            return false;
        }

        const eraActiva =
            cola.activeId === id;

        cola.tabs.splice(
            indice,
            1
        );

        if (
            cola.tabs.length === 0
        ) {
            const nueva =
                nuevaTab();

            cola.activeId =
                nueva.id;
        } else if (eraActiva) {
            const nuevoIndice =
                Math.min(
                    indice,
                    cola.tabs.length - 1
                );

            cola.activeId =
                cola.tabs[
                    nuevoIndice
                ].id;
        }

        guardarStorage();
        renderizarTabs();

        if (eraActiva) {
            const actual =
                tabActiva();

            if (actual) {
                restaurarEstado(
                    actual.state
                );
            }
        }

        return true;
    }

    function cerrarTab(id) {
        if (bloqueada) {
            return;
        }

        if (id === cola.activeId) {
            guardarActual(false);
        }

        const tab =
            buscarTab(id);

        if (!tab) {
            return;
        }

        if (
            !estadoTieneContenido(
                tab.state
            )
        ) {
            eliminarTabSinConfirmar(id);
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Cerrar venta',
            text:
                'Se eliminará este borrador y sus productos de la cola.',
            showCancelButton: true,
            confirmButtonText: 'Cerrar venta',
            cancelButtonText: 'Conservar'
        }).then(function (resultado) {
            if (
                resultado.isConfirmed
            ) {
                eliminarTabSinConfirmar(
                    id
                );
            }
        });
    }

    function esperarFormularioListo() {
        return new Promise(
            function (resolve) {
                let intentos = 0;
                const maximo = 100;

                const revisar =
                    function () {
                        intentos += 1;

                        const listo =
                            document.getElementById(
                                'formularioVenta'
                            )
                            && $('#tipo_comprobante option')
                                .length > 1
                            && $('#tipo_pago option')
                                .length > 1
                            && $('#forma_pago option')
                                .length > 1
                            && typeof window
                                .calcularTotales
                                === 'function';

                        const configuracionLista =
                            (
                                typeof configuracionVentaPredeterminadaCargada
                                    === 'undefined'
                                || configuracionVentaPredeterminadaCargada
                            )
                            && (
                                typeof configuracionTributariaVentaCargada
                                    === 'undefined'
                                || configuracionTributariaVentaCargada
                            );

                        if (
                            listo
                            && (
                                configuracionLista
                                || intentos >= 35
                            )
                        ) {
                            resolve();
                            return;
                        }

                        if (
                            intentos >= maximo
                        ) {
                            resolve();
                            return;
                        }

                        window.setTimeout(
                            revisar,
                            100
                        );
                    };

                revisar();
            }
        );
    }

    function vincularEventos() {
        $(document)
            .off(
                'click.ventaCola',
                '[data-venta-cola-seleccionar]'
            )
            .on(
                'click.ventaCola',
                '[data-venta-cola-seleccionar]',
                function () {
                    cambiarTab(
                        String(
                            $(this).attr(
                                'data-venta-cola-seleccionar'
                            ) || ''
                        )
                    );
                }
            );

        $(document)
            .off(
                'click.ventaColaMenu',
                '[data-venta-cola-menu]'
            )
            .on(
                'click.ventaColaMenu',
                '[data-venta-cola-menu]',
                function (evento) {
                    evento.preventDefault();
                    evento.stopPropagation();

                    const id =
                        String(
                            $(this).attr(
                                'data-venta-cola-menu'
                            ) || ''
                        );

                    abrirMenu(
                        id,
                        this
                    );
                }
            );

        $(document)
            .off(
                'click.ventaColaAccion',
                '#ventaColaMenu [data-venta-cola-accion]'
            )
            .on(
                'click.ventaColaAccion',
                '#ventaColaMenu [data-venta-cola-accion]',
                function () {
                    const $menu =
                        $('#ventaColaMenu');

                    const id =
                        String(
                            $menu.attr(
                                'data-venta-cola-id'
                            ) || ''
                        );

                    const accion =
                        String(
                            $(this).attr(
                                'data-venta-cola-accion'
                            ) || ''
                        );

                    cerrarMenu();

                    if (
                        accion === 'renombrar'
                    ) {
                        renombrarTab(id);
                    } else if (
                        accion === 'duplicar'
                    ) {
                        duplicarTab(id);
                    } else if (
                        accion === 'cerrar'
                    ) {
                        cerrarTab(id);
                    }
                }
            );

        $('#btnNuevaVentaCola')
            .off('click.ventaCola')
            .on(
                'click.ventaCola',
                crearNuevaVenta
            );

        $(document)
            .off(
                'input.ventaCola change.ventaCola',
                '#formularioVenta input, #formularioVenta select, #formularioVenta textarea'
            )
            .on(
                'input.ventaCola change.ventaCola',
                '#formularioVenta input, #formularioVenta select, #formularioVenta textarea',
                programarGuardado
            );

        $(document)
            .off('mousedown.ventaColaCerrarMenu')
            .on(
                'mousedown.ventaColaCerrarMenu',
                function (evento) {
                    if (
                        !$(evento.target)
                            .closest(
                                '#ventaColaMenu, [data-venta-cola-menu]'
                            )
                            .length
                    ) {
                        cerrarMenu();
                    }
                }
            );

        $(window)
            .off('resize.ventaCola scroll.ventaCola')
            .on(
                'resize.ventaCola scroll.ventaCola',
                cerrarMenu
            );

        window.addEventListener(
            'beforeunload',
            function () {
                guardarActual(false);
            }
        );

        const formulario =
            document.getElementById(
                'formularioVenta'
            );

        if (
            formulario
            && typeof MutationObserver
                !== 'undefined'
        ) {
            observadorFormulario =
                new MutationObserver(
                    function () {
                        programarGuardado();
                    }
                );

            observadorFormulario.observe(
                formulario,
                {
                    subtree: true,
                    childList: true,
                    characterData: true
                }
            );
        }
    }

    function inicializar() {
        if (promesaInicializacion) {
            return promesaInicializacion;
        }

        promesaInicializacion =
            esperarFormularioListo()
                .then(function () {
                    if (
                        !document.getElementById(
                            'ventaColaShell'
                        )
                    ) {
                        return false;
                    }

                    estadoBase =
                        capturarEstadoFormulario();

                    const guardada =
                        leerStorage();

                    if (
                        guardada
                        && guardada.tabs.length > 0
                    ) {
                        cola = guardada;

                        if (
                            !buscarTab(
                                cola.activeId
                            )
                        ) {
                            cola.activeId =
                                cola.tabs[0].id;
                        }

                        cola.nextNumber =
                            Math.max(
                                enteroSeguro(
                                    cola.nextNumber,
                                    cola.tabs.length + 1
                                ),
                                cola.tabs.length + 1
                            );
                    } else {
                        cola = {
                            version: VERSION,
                            activeId: null,
                            nextNumber: 1,
                            tabs: []
                        };

                        nuevaTab(
                            crearEstadoVacio()
                        );
                    }

                    inicializada = true;
                    vincularEventos();
                    renderizarTabs();

                    const activa =
                        tabActiva();

                    if (activa) {
                        restaurarEstado(
                            activa.state
                        );
                    }

                    guardarStorage();

                    return true;
                });

        return promesaInicializacion;
    }

    window.ventaColaProgramarGuardado =
        programarGuardado;

    window.ventaColaGuardarActual =
        function () {
            guardarActual(true);
        };

    window.ventaColaBloquear =
        function (estado) {
            bloqueada =
                estado === true;

            $('#ventaColaShell')
                .toggleClass(
                    'is-locked',
                    bloqueada
                );

            if (bloqueada) {
                cerrarMenu();
            }
        };

    window.ventaColaPrepararProcesamiento =
        function () {
            if (!inicializada) {
                return null;
            }

            guardarActual(true);
            window.ventaColaBloquear(true);

            return cola.activeId;
        };

    window.ventaColaFinalizarVentaProcesada =
        function (id) {
            if (
                !inicializada
                || !id
            ) {
                return false;
            }

            const eliminado =
                eliminarTabSinConfirmar(
                    String(id)
                );

            window.ventaColaBloquear(false);

            return eliminado;
        };

    window.ventaColaSincronizarDesdeFormulario =
        function (opciones = {}) {
            if (!inicializada) {
                return false;
            }

            guardarActual(false);

            const tab =
                tabActiva();

            const sugerido =
                String(
                    opciones.nombreSugerido || ''
                ).trim();

            if (
                tab
                && sugerido !== ''
                && /^Venta \d+$/i.test(
                    String(tab.name || '')
                )
            ) {
                tab.name =
                    sugerido.slice(0, 40);
            }

            guardarStorage();
            renderizarTabs();

            return true;
        };

    window.ventaColaPrepararDuplicacion =
        function () {
            return inicializar()
                .then(function () {
                    if (!inicializada) {
                        return false;
                    }

                    guardarActual(false);

                    const actual =
                        tabActiva();

                    /*
                     * Si la pestaña activa ya contiene un pedido,
                     * la duplicación de ListSales se abre en una nueva
                     * pestaña para no sobrescribirlo.
                     */
                    if (
                        actual
                        && estadoTieneContenido(
                            actual.state
                        )
                    ) {
                        if (
                            cola.tabs.length
                            >= MAX_VENTAS
                        ) {
                            throw new Error(
                                'Cierre una venta de la cola antes de duplicar otra.'
                            );
                        }

                        const nueva =
                            nuevaTab(
                                crearEstadoVacio(),
                                'Venta duplicada'
                            );

                        guardarStorage();
                        renderizarTabs();
                        restaurarEstado(
                            nueva.state
                        );
                    }

                    return true;
                });
        };

    $(document).ready(function () {
        inicializar();
    });

})(jQuery, window, document);
