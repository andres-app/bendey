(function () {
  'use strict';

  const STORAGE_PRINTERS = 'tiquepos_label_printers_v1';
  const STORAGE_FORMAT = 'tiquepos_label_format_v1';
  const STORAGE_DIRECT_PRINTER = 'tiquepos_label_direct_printer_v1';
  const MAX_PREVIEW_LABELS = 12;

  let productos = [];
  let seleccion = new Map();
  let filtroActual = 'todos';
  let negocio = { nombre: 'TiquePOS', simbolo: 'S/' };
  let perfiles = [];
  let temporizadorBusqueda = null;
  let peticionBusqueda = null;
  let secuenciaBusqueda = 0;
  let ultimaBusqueda = '';
  let catalogoSimpleRespaldo = null;
  let peticionRespaldo = null;
  let usarRespaldoProductos = false;

  const defaults = {
    columns: 1,
    width: 30,
    height: 20,
    gapX: 2,
    gapY: 2,
    showBusiness: false,
    showName: true,
    showSku: true,
    showPrice: true,
    showBorder: false,
    compactMode: false,
    printerId: 'tsc-te200',
    printMode: 'direct',
    directPrinterName: ''
  };

  function esc(value) {
    return String(value ?? '').replace(/[&<>'"]/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[ch];
    });
  }

  function keyProducto(p) {
    return String(p.tipo) + ':' + String(p.idregistro);
  }

  function normalizar(texto) {
    return String(texto ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function formatoMoneda(valor) {
    const n = Number(valor || 0);
    return (negocio.simbolo || 'S/') + ' ' + n.toFixed(2);
  }

  function precioEtiqueta(valor) {
    const n = Number(valor || 0);
    const monto = Number.isInteger(n) ? String(Math.trunc(n)) : n.toFixed(2);
    return { simbolo: String(negocio.simbolo || 'S/').trim(), monto: monto };
  }

  function estilosEtiqueta(s) {
    const escala = Math.max(0.72, Math.min(1.8, Math.min(Number(s.width || 30) / 30, Number(s.height || 20) / 20)));
    const compact = s.compactMode ? 0.9 : 1;
    const ancho = Math.max(15, Number(s.width || 30));
    return {
      business: (4.2 * escala * compact).toFixed(2),
      name: (6.0 * escala * compact).toFixed(2),
      currency: (7.2 * escala * compact).toFixed(2),
      price: (11.8 * escala * compact).toFixed(2),
      sku: (Math.min(5.6, Math.max(4.4, 5.3 * Math.min(1, ancho / 30))) * escala * compact).toFixed(2)
    };
  }

  function cargarPerfiles() {
    const base = [
      {
        id: 'tsc-te200',
        name: 'TSC TE200 · 203 DPI',
        model: 'TSC TE200',
        dpi: 203,
        maxWidth: 108,
        fixed: true,
        note: 'Perfil recomendado para tu TE200. Ancho imprimible máximo: 108 mm. En el driver usa escala 100% y márgenes 0.'
      },
      {
        id: 'system-generic',
        name: 'Impresora del sistema · Genérica',
        model: 'Genérica',
        dpi: 300,
        maxWidth: 210,
        fixed: true,
        note: 'Perfil neutro. La impresora real se elige en el diálogo de impresión.'
      }
    ];

    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_PRINTERS) || '[]');
      perfiles = base.concat(Array.isArray(saved) ? saved : []);
    } catch (_) {
      perfiles = base;
    }
  }

  function guardarPerfiles() {
    localStorage.setItem(STORAGE_PRINTERS, JSON.stringify(perfiles.filter(p => !p.fixed)));
  }

  function renderPerfiles() {
    const actual = $('#printerProfile').val() || defaults.printerId;
    const html = perfiles.map(p => `<option value="${esc(p.id)}">${esc(p.name)}</option>`).join('');
    $('#printerProfile').html(html);
    $('#printerProfile').val(perfiles.some(p => p.id === actual) ? actual : defaults.printerId);
    actualizarPerfilImpresora();

    $('#listaPerfilesImpresora').html(perfiles.map(p => `
      <div class="tw-mb-2 tw-flex tw-items-center tw-justify-between tw-gap-3 tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-p-3.5 last:tw-mb-0">
        <div class="tw-flex tw-min-w-0 tw-items-center tw-gap-3">
          <span class="tw-flex tw-h-9 tw-w-9 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-xl tw-bg-slate-100 tw-text-slate-500"><i class="fas fa-print"></i></span>
          <div class="tw-min-w-0">
            <strong class="tw-block tw-truncate tw-text-[12px] tw-font-semibold tw-text-slate-800">${esc(p.name)}</strong>
            <small class="tw-mt-0.5 tw-block tw-text-[10px] tw-text-slate-400">${esc(p.model)} · ${esc(p.dpi)} DPI · ${esc(p.maxWidth)} mm máx.</small>
          </div>
        </div>
        ${p.fixed ? '<span class="tw-shrink-0 tw-rounded-full tw-bg-slate-100 tw-px-2.5 tw-py-1 tw-text-[9px] tw-font-semibold tw-text-slate-500">Incluido</span>' : `<button type="button" class="btn-delete-printer tw-flex tw-h-8 tw-w-8 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-lg tw-border tw-border-rose-100 tw-bg-rose-50 tw-text-rose-500 hover:tw-bg-rose-100" data-id="${esc(p.id)}" title="Eliminar perfil"><i class="fas fa-trash"></i></button>`}
      </div>`).join(''));
  }

  function getSettings() {
    return {
      columns: Number($('#selectorColumnas .active').data('cols') || 1),
      width: Math.max(15, Number($('#labelWidth').val() || 30)),
      height: Math.max(10, Number($('#labelHeight').val() || 20)),
      gapX: Math.max(0, Number($('#labelGapX').val() || 0)),
      gapY: Math.max(0, Number($('#labelGapY').val() || 0)),
      showBusiness: $('#showBusiness').is(':checked'),
      showName: $('#showName').is(':checked'),
      showSku: $('#showSku').is(':checked'),
      showPrice: $('#showPrice').is(':checked'),
      showBorder: $('#showBorder').is(':checked'),
      compactMode: $('#compactMode').is(':checked'),
      printerId: $('#printerProfile').val() || defaults.printerId,
      printMode: $('input[name="printMode"]:checked').val() || 'direct',
      directPrinterName: $('#directPrinterName').val() || localStorage.getItem(STORAGE_DIRECT_PRINTER) || ''
    };
  }

  function applySettings(s) {
    s = Object.assign({}, defaults, s || {});
    $('#selectorColumnas .lb-column-btn').removeClass('active').filter(`[data-cols="${Number(s.columns)}"]`).addClass('active');
    $('#labelWidth').val(s.width);
    $('#labelHeight').val(s.height);
    $('#labelGapX').val(s.gapX);
    $('#labelGapY').val(s.gapY);
    $('#showBusiness').prop('checked', !!s.showBusiness);
    $('#showName').prop('checked', !!s.showName);
    $('#showSku').prop('checked', !!s.showSku);
    $('#showPrice').prop('checked', !!s.showPrice);
    $('#showBorder').prop('checked', !!s.showBorder);
    $('#compactMode').prop('checked', !!s.compactMode);
    $('#printerProfile').val(s.printerId || defaults.printerId);
    $('input[name="printMode"][value="' + (s.printMode || 'direct') + '"]').prop('checked', true);
    const savedPrinter = s.directPrinterName || localStorage.getItem(STORAGE_DIRECT_PRINTER) || '';
    if (savedPrinter) $('#directPrinterName').attr('data-saved-printer', savedPrinter);
    actualizarPerfilImpresora();
    actualizarModoImpresion();
    actualizarPreview();
  }

  function loadSavedSettings() {
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_FORMAT) || 'null');
      applySettings(saved || defaults);
    } catch (_) {
      applySettings(defaults);
    }
  }

  function cargarNegocio() {
    $.ajax({
      url: 'Controllers/Company.php',
      method: 'GET',
      dataType: 'json',
      data: { op: 'mostrar_datos', v: Date.now() }
    }).done(function (data) {
      if (!data || typeof data !== 'object') return;
      negocio.nombre = String(data.nombre || data.razon_social || 'TiquePOS');
      negocio.simbolo = String(data.simbolo || 'S/');
      actualizarPreview();
    });
  }

  function tipoBusquedaServidor() {
    return (filtroActual === 'simple' || filtroActual === 'variacion')
      ? filtroActual
      : 'todos';
  }

  function productoPorKey(key) {
    const actual = productos.find(function (p) { return keyProducto(p) === key; });
    if (actual) return actual;
    const guardado = seleccion.get(key);
    return guardado ? guardado.producto : null;
  }

  function mostrarEstadoBusqueda(texto) {
    const q = String(texto || '').trim();
    $('#resultadosEtiquetasCount').text(q ? 'Buscando…' : 'Cargando productos…');
    $('#listaProductosEtiquetas').html(`
      <div class="tw-flex tw-min-h-[170px] tw-flex-col tw-items-center tw-justify-center tw-gap-3 tw-p-6 tw-text-center">
        <span class="tw-flex tw-h-11 tw-w-11 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-tique-50 tw-text-tique-600">
          <span class="spinner-border spinner-border-sm"></span>
        </span>
        <div>
          <div class="tw-text-[12px] tw-font-semibold tw-text-slate-700">${q ? 'Buscando productos' : 'Cargando productos'}</div>
          <div class="tw-mt-1 tw-text-[11px] tw-text-slate-400">${q ? `Consultando “${esc(q)}” en el inventario…` : 'Consultando el inventario…'}</div>
        </div>
      </div>`);
  }

  function mapearProductoSimpleRespaldo(row) {
    return {
      tipo: 'simple',
      idregistro: Number(row.idarticulo || 0),
      idarticulo: Number(row.idarticulo || 0),
      codigo: String(row.codigo || '').trim(),
      codigo_padre: String(row.codigo || '').trim(),
      nombre: String(row.nombre || '').trim(),
      nombre_padre: String(row.nombre || '').trim(),
      variante: '',
      stock: Number(row.stock || 0),
      precio_venta: Number(row.precio_venta_min ?? row.precio_venta ?? 0),
      categoria: String(row.categoria || '').trim(),
      almacen: String(row.almacen || '').trim(),
      imagen: String(row.imagen || '').trim()
    };
  }

  function filtrarCatalogoSimpleRespaldo(q) {
    const texto = normalizar(q);
    let lista = Array.isArray(catalogoSimpleRespaldo) ? catalogoSimpleRespaldo.slice() : [];

    if (filtroActual === 'variacion') {
      return [];
    }

    if (texto) {
      lista = lista.filter(function (p) {
        return [p.codigo, p.nombre, p.categoria, p.almacen]
          .some(function (valor) { return normalizar(valor).includes(texto); });
      });

      lista.sort(function (a, b) {
        const pa = puntajeBusqueda(a, texto);
        const pb = puntajeBusqueda(b, texto);
        if (pa !== pb) return pa - pb;
        return String(a.nombre || '').localeCompare(String(b.nombre || ''), 'es', { sensitivity: 'base' });
      });
    }

    return lista.slice(0, 80);
  }

  function aplicarCatalogoSimpleRespaldo(q, secuencia) {
    if (secuencia !== secuenciaBusqueda) return;
    productos = filtrarCatalogoSimpleRespaldo(q);
    renderProductos();
  }

  function cargarProductosRespaldo(q, secuencia, motivo) {
    usarRespaldoProductos = true;

    if (Array.isArray(catalogoSimpleRespaldo)) {
      aplicarCatalogoSimpleRespaldo(q, secuencia);
      return;
    }

    if (peticionRespaldo && peticionRespaldo.readyState !== 4) {
      peticionRespaldo.abort();
    }

    $('#resultadosEtiquetasCount').text('Cargando productos…');

    /*
     * Este es el mismo endpoint que usa la pantalla Productos de TiquePOS.
     * Si el buscador específico de etiquetas falla por una diferencia de DB,
     * al menos los productos simples siguen disponibles inmediatamente.
     */
    peticionRespaldo = $.ajax({
      url: 'Controllers/Product.php',
      method: 'GET',
      dataType: 'json',
      cache: false,
      data: { op: 'listar_json_todo', v: Date.now() }
    }).done(function (resp) {
      if (secuencia !== secuenciaBusqueda) return;

      const filas = Array.isArray(resp) ? resp : (Array.isArray(resp && resp.data) ? resp.data : []);
      catalogoSimpleRespaldo = filas
        .filter(function (row) {
          return Number(row.condicion) === 1
            && Number(row.tiene_variaciones || 0) !== 1
            && String(row.codigo || '').trim() !== '';
        })
        .map(mapearProductoSimpleRespaldo);

      aplicarCatalogoSimpleRespaldo(q, secuencia);
    }).fail(function (xhr, estado) {
      if (estado === 'abort' || secuencia !== secuenciaBusqueda) return;
      productos = [];
      $('#resultadosEtiquetasCount').text('No disponible');
      $('#listaProductosEtiquetas').html(`
        <div class="tw-flex tw-min-h-[170px] tw-flex-col tw-items-center tw-justify-center tw-gap-3 tw-p-6 tw-text-center">
          <span class="tw-flex tw-h-11 tw-w-11 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-rose-50 tw-text-rose-500"><i class="fas fa-exclamation-triangle"></i></span>
          <div><div class="tw-text-[12px] tw-font-semibold tw-text-slate-700">No se pudo cargar Productos</div><div class="tw-mt-1 tw-text-[11px] tw-text-slate-400">El servidor tampoco respondió al listado principal de productos.</div></div>
        </div>`);
      console.error('Etiquetas / respaldo Productos:', motivo || '', xhr.responseText || xhr.statusText);
    });
  }

  function cargarProductos(texto) {
    if (filtroActual === 'seleccionados') {
      renderProductos();
      return;
    }

    const q = String(texto !== undefined ? texto : ($('#buscarEtiquetaProducto').val() || '')).trim();
    ultimaBusqueda = q;
    const secuencia = ++secuenciaBusqueda;

    if (peticionBusqueda && peticionBusqueda.readyState !== 4) {
      peticionBusqueda.abort();
    }

    if (usarRespaldoProductos && Array.isArray(catalogoSimpleRespaldo)) {
      aplicarCatalogoSimpleRespaldo(q, secuencia);
      return;
    }

    mostrarEstadoBusqueda(q);

    peticionBusqueda = $.ajax({
      url: 'Controllers/Product.php',
      method: 'GET',
      dataType: 'json',
      cache: false,
      data: {
        op: 'buscar_etiquetas',
        q: q,
        tipo: tipoBusquedaServidor(),
        limit: 80,
        v: Date.now()
      }
    }).done(function (resp) {
      if (secuencia !== secuenciaBusqueda) return;

      if (resp && resp.success === false) {
        console.warn('Etiquetas / búsqueda específica no disponible. Usando listado principal de Productos.', resp.mensaje || '');
        cargarProductosRespaldo(q, secuencia, resp.mensaje || 'Respuesta success=false');
        return;
      }

      productos = Array.isArray(resp) ? resp : (Array.isArray(resp && resp.data) ? resp.data : []);
      renderProductos();
    }).fail(function (xhr, estado) {
      if (estado === 'abort' || secuencia !== secuenciaBusqueda) return;
      console.error('Etiquetas / búsqueda específica:', xhr.status, xhr.responseText || xhr.statusText);
      cargarProductosRespaldo(q, secuencia, 'HTTP ' + String(xhr.status || ''));
    });
  }

  function programarBusqueda() {
    window.clearTimeout(temporizadorBusqueda);
    const q = String($('#buscarEtiquetaProducto').val() || '').trim();
    temporizadorBusqueda = window.setTimeout(function () {
      cargarProductos(q);
    }, 110);
  }

  function puntajeBusqueda(p, q) {
    if (!q) return 99;
    const sku = normalizar(p.codigo);
    const skuPadre = normalizar(p.codigo_padre);
    const nombre = normalizar(p.nombre);
    if (sku === q) return 0;
    if (skuPadre === q) return 1;
    if (sku.startsWith(q)) return 2;
    if (skuPadre.startsWith(q)) return 3;
    if (nombre.startsWith(q)) return 4;
    return 10;
  }

  function renderProductos() {
    const textoBusqueda = String($('#buscarEtiquetaProducto').val() || '').trim();
    let lista;

    if (filtroActual === 'seleccionados') {
      lista = Array.from(seleccion.values()).map(function (item) { return item.producto; });
    } else {
      // La búsqueda ya llega filtrada desde la base de datos. Esto permite que
      // aparezcan resultados mientras se escribe, igual que en los buscadores
      // del POS, sin depender de precargar todo el catálogo en el navegador.
      lista = productos.slice();
    }

    const totalCoincidencias = lista.length;
    $('#resultadosEtiquetasCount').text(totalCoincidencias + (totalCoincidencias === 1 ? ' resultado' : ' resultados'));

    if (!lista.length) {
      const detalle = textoBusqueda
        ? `No encontramos <strong class="tw-font-semibold tw-text-slate-600">${esc(textoBusqueda)}</strong> en nombre, SKU, SKU padre o variante.`
        : (filtroActual === 'seleccionados' ? 'Todavía no has seleccionado productos.' : 'No hay productos disponibles para este filtro.');
      $('#listaProductosEtiquetas').html(`
        <div class="tw-flex tw-min-h-[170px] tw-flex-col tw-items-center tw-justify-center tw-gap-3 tw-p-6 tw-text-center">
          <span class="tw-flex tw-h-11 tw-w-11 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-slate-100 tw-text-slate-400"><i class="fas fa-search"></i></span>
          <div><div class="tw-text-[12px] tw-font-semibold tw-text-slate-700">Sin coincidencias</div><div class="tw-mt-1 tw-max-w-md tw-text-[11px] tw-leading-5 tw-text-slate-400">${detalle}</div></div>
        </div>`);
      actualizarResumen();
      return;
    }

    let html = lista.map(function (p) {
      const key = keyProducto(p);
      const sel = seleccion.get(key);
      const qty = sel ? sel.qty : 1;
      const esVariacion = p.tipo === 'variacion';
      const skuPadre = String(p.codigo_padre || '').trim();
      const skuActual = String(p.codigo || '').trim();
      const mostrarPadre = esVariacion && skuPadre && normalizar(skuPadre) !== normalizar(skuActual);

      return `
        <div class="lb-product ${sel ? 'is-selected' : ''} tw-mb-2 tw-grid tw-grid-cols-[28px_minmax(0,1fr)_78px] tw-items-center tw-gap-3 tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-p-3 last:tw-mb-0" data-key="${esc(key)}">
          <div class="tw-flex tw-items-center tw-justify-center">
            <input type="checkbox" class="lb-check lb-product-check tw-h-[18px] tw-w-[18px] tw-cursor-pointer" data-key="${esc(key)}" ${sel ? 'checked' : ''}>
          </div>
          <div class="tw-flex tw-min-w-0 tw-items-center tw-gap-3">
            <span class="tw-hidden tw-h-10 tw-w-10 tw-shrink-0 tw-items-center tw-justify-center tw-rounded-xl ${esVariacion ? 'tw-bg-amber-50 tw-text-amber-600' : 'tw-bg-tique-50 tw-text-tique-700'} sm:tw-flex">
              <i class="fas ${esVariacion ? 'fa-layer-group' : 'fa-box'}"></i>
            </span>
            <div class="tw-min-w-0">
              <div class="tw-flex tw-min-w-0 tw-flex-wrap tw-items-center tw-gap-1.5">
                <strong class="tw-max-w-full tw-truncate tw-text-[12px] tw-font-semibold tw-text-slate-800" title="${esc(p.nombre)}">${esc(p.nombre)}</strong>
                ${esVariacion ? '<span class="tw-rounded-full tw-bg-amber-50 tw-px-2 tw-py-0.5 tw-text-[9px] tw-font-bold tw-uppercase tw-tracking-wide tw-text-amber-700">Variante</span>' : '<span class="tw-rounded-full tw-bg-tique-50 tw-px-2 tw-py-0.5 tw-text-[9px] tw-font-bold tw-uppercase tw-tracking-wide tw-text-tique-700">Simple</span>'}
              </div>
              <div class="tw-mt-1.5 tw-flex tw-flex-wrap tw-items-center tw-gap-x-3 tw-gap-y-1 tw-text-[10px] tw-text-slate-400">
                <span class="tw-inline-flex tw-items-center tw-gap-1"><span class="tw-text-slate-400">SKU</span><code class="tw-rounded tw-bg-slate-100 tw-px-1.5 tw-py-0.5 tw-font-mono tw-font-semibold tw-text-slate-700">${esc(skuActual)}</code></span>
                ${mostrarPadre ? `<span class="tw-inline-flex tw-items-center tw-gap-1"><span>Padre</span><code class="tw-font-mono tw-font-semibold tw-text-tique-700">${esc(skuPadre)}</code></span>` : ''}
                <span><i class="fas fa-cubes tw-mr-1"></i>Stock ${esc(p.stock)}</span>
                <span class="tw-font-semibold tw-text-slate-600">${esc(formatoMoneda(p.precio_venta))}</span>
              </div>
              ${(p.categoria || p.almacen) ? `<div class="tw-mt-1 tw-truncate tw-text-[9px] tw-text-slate-400">${esc([p.categoria, p.almacen].filter(Boolean).join(' · '))}</div>` : ''}
            </div>
          </div>
          <div>
            <label class="tw-mb-1 tw-block tw-text-center tw-text-[9px] tw-font-semibold tw-uppercase tw-tracking-wide tw-text-slate-400">Cantidad</label>
            <input class="lb-product-qty tw-h-9 tw-w-full tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-px-2 tw-text-center tw-text-[12px] tw-font-bold tw-text-slate-700 tw-outline-none disabled:tw-cursor-not-allowed disabled:tw-bg-slate-100 disabled:tw-text-slate-400 focus:tw-border-tique-400" type="number" min="1" max="9999" value="${qty}" data-key="${esc(key)}" ${sel ? '' : 'disabled'} title="Cantidad de etiquetas">
          </div>
        </div>`;
    }).join('');

    $('#listaProductosEtiquetas').html(html);
    actualizarResumen();
  }

  function actualizarResumen() {
    let total = 0;
    seleccion.forEach(v => { total += Number(v.qty || 1); });
    $('#cantidadProductosSeleccionados').text(seleccion.size);
    $('#cantidadEtiquetasSeleccionadas').text(total + (total === 1 ? ' etiqueta' : ' etiquetas'));
  }

  function expandirEtiquetas(max) {
    const items = [];
    for (const entry of seleccion.values()) {
      for (let i = 0; i < Number(entry.qty || 1); i++) {
        items.push(entry.producto);
        if (max && items.length >= max) return items;
      }
    }
    return items;
  }

  function crearBarcodeSvg(codigo, compact) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    try {
      JsBarcode(svg, String(codigo), {
        format: 'CODE128',
        displayValue: false,
        margin: 0,
        width: compact ? 0.62 : 0.70,
        height: compact ? 10 : 12
      });
<<<<<<< HEAD
      svg.setAttribute('preserveAspectRatio', 'none');
=======
      svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
>>>>>>> 2bc36874431ef8a8dd84d5086fb5416ffd1db084
      svg.style.overflow = 'hidden';
      return svg.outerHTML;
    } catch (e) {
      return '<div style="font-size:6pt;text-align:center">SKU no compatible</div>';
    }
  }

  function etiquetaHtml(p, s, preview) {
    const border = s.showBorder ? ' with-border' : '';
    const sizes = estilosEtiqueta(s);
    const vars = `--lb-business-size:${sizes.business}pt;--lb-name-size:${sizes.name}pt;--lb-currency-size:${sizes.currency}pt;--lb-price-size:${sizes.price}pt;--lb-sku-size:${sizes.sku}pt;`;
    const dimensions = preview ? `width:${s.width}mm;height:${s.height}mm;` : '';
    const price = precioEtiqueta(p.precio_venta);

    return `<div class="lb-preview-label${border}" style="${dimensions}${vars}">
      <div class="lb-label-heading">
        ${s.showBusiness ? `<div class="lb-label-business">${esc(negocio.nombre)}</div>` : ''}
        <div class="lb-label-name-price">
          ${s.showName ? `<div class="lb-label-name" title="${esc(p.nombre)}">${esc(p.nombre)}</div>` : ''}
          ${s.showPrice ? `<div class="lb-label-price-row"><span class="lb-label-currency">${esc(price.simbolo)}</span><span class="lb-label-price-value">${esc(price.monto)}</span></div>` : ''}
        </div>
      </div>
      <div class="lb-label-barcode">${crearBarcodeSvg(p.codigo, s.compactMode)}</div>
      ${s.showSku ? `<div class="lb-label-sku">${esc(p.codigo)}</div>` : '<div></div>'}
    </div>`;
  }


  function actualizarPerfilImpresora() {
    const s = getSettings();
    const profile = perfiles.find(p => p.id === s.printerId) || perfiles[0];
    if (!profile) return;
    $('#printerProfileNote').text(profile.note || `${profile.model} · ${profile.dpi} DPI · ${profile.maxWidth} mm de ancho máximo.`);
    validarAnchoImpresora();
  }

  function validarAnchoImpresora() {
    const s = getSettings();
    const profile = perfiles.find(p => p.id === s.printerId);
    const totalWidth = s.columns * s.width + Math.max(0, s.columns - 1) * s.gapX;
    if (profile && Number(profile.maxWidth) > 0 && totalWidth > Number(profile.maxWidth)) {
      $('#printerWidthAlert').show().html(`<div class="tw-flex tw-items-start tw-gap-2"><i class="fas fa-exclamation-triangle tw-mt-0.5"></i><span>Este formato ocupa <strong>${totalWidth.toFixed(1)} mm</strong>, pero el perfil ${esc(profile.name)} admite ${esc(profile.maxWidth)} mm imprimibles.</span></div>`);
      return false;
    }
    $('#printerWidthAlert').hide().empty();
    return true;
  }

  function actualizarPreview() {
    const s = getSettings();
    const items = expandirEtiquetas(MAX_PREVIEW_LABELS);
    const total = expandirEtiquetas().length;
    $('#previewSizeBadge').text(`${s.width} × ${s.height} mm · ${s.columns} col.`);
    validarAnchoImpresora();

    if (!items.length) {
      $('#previewStage').html('<div class="tw-flex tw-min-h-[300px] tw-flex-col tw-items-center tw-justify-center tw-gap-3 tw-text-center"><span class="tw-flex tw-h-12 tw-w-12 tw-items-center tw-justify-center tw-rounded-2xl tw-bg-slate-100 tw-text-slate-400"><i class="fas fa-barcode tw-text-lg"></i></span><div><div class="tw-text-[12px] tw-font-semibold tw-text-slate-600">Sin etiquetas seleccionadas</div><div class="tw-mt-1 tw-text-[11px] tw-text-slate-400">Selecciona uno o más productos para ver la vista previa.</div></div></div>');
      $('#previewSummary').text('Selecciona al menos un producto.');
      return;
    }

    $('#previewSummary').text(`Vista previa de ${Math.min(items.length, MAX_PREVIEW_LABELS)} de ${total} etiquetas.`);
    const sheetWidth = s.columns * s.width + Math.max(0, s.columns - 1) * s.gapX;
    const html = `<div class="lb-preview-sheet" style="grid-template-columns:repeat(${s.columns},${s.width}mm);gap:${s.gapY}mm ${s.gapX}mm;width:${sheetWidth}mm">${items.map(p => etiquetaHtml(p, s, true)).join('')}</div>`;
    $('#previewStage').html(html);
  }

  function setDirectStatus(kind, text) {
    const $status = $('#directPrintStatus');
    $status.removeClass('ok err');
    if (kind === 'ok') $status.addClass('ok');
    if (kind === 'err') $status.addClass('err');
    const icon = kind === 'ok' ? 'fa-check-circle' : (kind === 'err' ? 'fa-exclamation-circle' : 'fa-circle');
    $status.html(`<i class="fas ${icon} tw-text-[9px]"></i><span>${esc(text)}</span>`);
  }

  function actualizarModoImpresion() {
    const direct = ($('input[name="printMode"]:checked').val() || 'direct') === 'direct';
    $('#directPrintBox').toggle(direct);
    $('#printMethodNote').text(direct
      ? 'En modo directo, QZ Tray envía TSPL a la impresora elegida sin abrir el cuadro de impresión. El diálogo del sistema queda como respaldo.'
      : 'Se abrirá el cuadro de impresión del sistema operativo para elegir la impresora instalada.');
    $('#btnImprimirEtiquetas').html(direct
      ? '<i class="fas fa-bolt"></i><span>Imprimir directo</span>'
      : '<i class="fas fa-print"></i><span>Imprimir etiquetas</span>');
  }

  function qzDisponible() {
    return typeof window.qz !== 'undefined' && window.qz && qz.websocket && qz.printers && qz.print;
  }

  function abrirDescargaQz() {
    const url = 'https://qz.io/download/?os=windows';
    const win = window.open(url, '_blank', 'noopener,noreferrer');
    if (win) {
      try { win.opener = null; } catch (_) {}
    }
  }

  function conectarQz() {
    if (!qzDisponible()) {
      setDirectStatus('err', 'QZ no cargado');
      return Promise.reject(new Error('No se pudo cargar la librería de QZ Tray. Verifica la conexión a Internet y recarga esta página.'));
    }

    if (qz.websocket.isActive()) {
      return qz.api.getVersion().then(function (version) {
        setDirectStatus('ok', 'QZ ' + version);
        $('#qzVersionInfo').text('QZ Tray ' + version + ' · conexión local activa');
        return version;
      });
    }

    setDirectStatus('', 'Conectando...');
    return qz.websocket.connect({ retries: 2, delay: 1 }).then(function () {
      return qz.api.getVersion();
    }).then(function (version) {
      setDirectStatus('ok', 'QZ ' + version);
      $('#qzVersionInfo').text('QZ Tray ' + version + ' · conexión local activa');
      return version;
    }).catch(function (err) {
      setDirectStatus('err', 'QZ desconectado');
      $('#qzVersionInfo').text('QZ Tray no detectado en este equipo');
      throw new Error((err && err.message)
        ? 'No fue posible conectar con QZ Tray: ' + err.message
        : 'QZ Tray no está instalado o no está abierto.');
    });
  }

  function cargarListaImpresorasDirectas(showSuccess) {
    return conectarQz().then(function () {
      return Promise.all([
        qz.printers.find(),
        qz.printers.getDefault().catch(function () { return ''; })
      ]);
    }).then(function (result) {
      const printers = Array.isArray(result[0]) ? result[0].map(String).filter(Boolean) : [];
      const defaultPrinter = String(result[1] || '');
      if (!printers.length) throw new Error('QZ Tray está conectado, pero Windows no reportó impresoras instaladas.');

      const saved = $('#directPrinterName').attr('data-saved-printer') || localStorage.getItem(STORAGE_DIRECT_PRINTER) || '';
      let preferred = printers.find(function (name) { return String(name).toLowerCase() === String(saved).toLowerCase(); });
      if (!preferred) preferred = printers.find(function (name) { return /\btsc\b|te\s*200/i.test(String(name)); });
      if (!preferred && defaultPrinter) preferred = printers.find(function (name) { return String(name) === defaultPrinter; });
      if (!preferred) preferred = printers[0];

      $('#directPrinterName').html(printers.map(function (name) {
        return `<option value="${esc(name)}">${esc(name)}</option>`;
      }).join('')).val(preferred);
      localStorage.setItem(STORAGE_DIRECT_PRINTER, preferred);
      $('#directPrinterName').attr('data-saved-printer', preferred);
      setDirectStatus('ok', printers.length + (printers.length === 1 ? ' impresora' : ' impresoras'));
      if (showSuccess) {
        Swal.fire({
          icon: 'success',
          title: 'Impresoras detectadas',
          text: `QZ Tray encontró ${printers.length} impresora(s) instalada(s).`,
          timer: 1800,
          showConfirmButton: false
        });
      }
      return printers;
    });
  }

  function mmToDots(mm, dpi) {
    return Math.max(0, Math.round(Number(mm || 0) * Number(dpi || 203) / 25.4));
  }

  function tsplText(value) {
    return String(value == null ? '' : value)
      .replace(/[\r\n\t]+/g, ' ')
      .replace(/"/g, "'")
      .replace(/\\/g, '/');
  }

  function cortarTextoTspl(value, maxChars) {
    const t = tsplText(value).trim();
    if (t.length <= maxChars) return t;
    return t.slice(0, Math.max(1, maxChars - 3)).replace(/\s+$/, '') + '...';
  }

  function envolverTextoTspl(value, maxChars, maxLines) {
    const text = tsplText(value).trim().replace(/\s+/g, ' ');
    if (!text) return [];
    const words = [];
    text.split(' ').forEach(function (word) {
      if (word.length <= maxChars) {
        words.push(word);
        return;
      }
      for (let i = 0; i < word.length; i += maxChars) words.push(word.slice(i, i + maxChars));
    });
    const lines = [];
    let current = '';
    words.forEach(function (word) {
      const candidate = current ? current + ' ' + word : word;
      if (candidate.length <= maxChars) {
        current = candidate;
      } else {
        if (current) lines.push(current);
        current = word;
      }
    });
    if (current) lines.push(current);
    if (lines.length > maxLines) {
      lines.length = maxLines;
      lines[maxLines - 1] = cortarTextoTspl(lines[maxLines - 1], maxChars);
    }
    return lines.slice(0, maxLines);
  }

  function envolverTextoTsplConReserva(value, firstMaxChars, secondMaxChars) {
    const text = tsplText(value).trim().replace(/\s+/g, ' ');
    if (!text) return [];

    const words = text.split(' ');
    const lines = ['', ''];
    let lineIndex = 0;

    words.forEach(function (word) {
      if (lineIndex > 1) return;
      const maxChars = lineIndex === 0 ? firstMaxChars : secondMaxChars;
      const candidate = lines[lineIndex] ? lines[lineIndex] + ' ' + word : word;

      if (candidate.length <= maxChars) {
        lines[lineIndex] = candidate;
        return;
      }

      if (!lines[lineIndex]) {
        lines[lineIndex] = cortarTextoTspl(word, maxChars);
        lineIndex++;
        return;
      }

      lineIndex++;
      if (lineIndex > 1) return;
      const secondCandidate = word;
      lines[lineIndex] = secondCandidate.length <= secondMaxChars
        ? secondCandidate
        : cortarTextoTspl(secondCandidate, secondMaxChars);
    });

    // Si aún queda contenido por colocar en la segunda línea, reconstruir desde el texto restante
    // no es necesario para la etiqueta: se prioriza que nunca invada el precio.
    return lines.filter(Boolean);
  }

  function generarTspl(items, s, profile) {
    const dpi = Number(profile && profile.dpi || 203);
    const pageWidth = s.columns * s.width + Math.max(0, s.columns - 1) * s.gapX;
    const rows = [];
    for (let i = 0; i < items.length; i += s.columns) rows.push(items.slice(i, i + s.columns));

    const labelW = mmToDots(s.width, dpi);
    const labelH = mmToDots(s.height, dpi);
    const gapDots = mmToDots(s.gapX, dpi);
    const padX = mmToDots(s.compactMode ? 0.65 : 0.9, dpi);
    const padY = mmToDots(s.compactMode ? 0.55 : 0.75, dpi);
    const commands = [];

    commands.push(`SIZE ${pageWidth.toFixed(1)} mm,${Number(s.height).toFixed(1)} mm`);
    commands.push(`GAP ${Number(s.gapY).toFixed(1)} mm,0 mm`);
    commands.push('DIRECTION 1');
    commands.push('REFERENCE 0,0');
    commands.push('CODEPAGE UTF-8');

    rows.forEach(function (row) {
      commands.push('CLS');
      row.forEach(function (p, col) {
        const x0 = col * (labelW + gapDots);
        const usableW = Math.max(1, labelW - padX * 2);
        const rightX = x0 + labelW - padX;
        const code = tsplText(p.codigo);

        if (s.showBorder) commands.push(`BOX ${x0 + 1},1,${x0 + labelW - 2},${labelH - 2},1`);

        // Composición 30 x 20 mm: nombre a la izquierda, precio sobre la segunda línea,
        // barcode ancho pero bajo y SKU discreto.
        const price = precioEtiqueta(p.precio_venta);
        const nameTop = padY;
        const nameLineStep = mmToDots(1.82, dpi);
        const secondLineY = nameTop + nameLineStep;

        // Primero calculamos el precio para reservar exactamente su zona en la segunda línea.
        let amountScale = s.compactMode ? 2 : 3;
        const maxPriceWidth = Math.floor(usableW * 0.58);
        while (amountScale > 1 && price.monto.length * 8 * amountScale > maxPriceWidth) amountScale--;
        const symbolScale = Math.max(1, amountScale - 1);
        const amountW = price.monto.length * 8 * amountScale;
        const symbolW = price.simbolo.length * 8 * symbolScale;
        const between = mmToDots(0.28, dpi);
        const priceW = amountW + symbolW + between;
        const amountX = Math.max(x0 + padX, rightX - amountW);
        const symbolX = Math.max(x0 + padX, amountX - between - symbolW);

        if (s.showBusiness) {
          const business = cortarTextoTspl(negocio.nombre, Math.max(10, Math.floor(usableW / 7)));
          commands.push(`TEXT ${x0 + padX},${nameTop},"0",0,1,1,"${business}"`);
        }

        if (s.showName) {
          const firstChars = Math.max(9, Math.floor(usableW / 7));
          const reserve = s.showPrice ? priceW + mmToDots(0.65, dpi) : 0;
          const secondUsable = Math.max(mmToDots(7, dpi), usableW - reserve);
          const secondChars = Math.max(5, Math.floor(secondUsable / 7));
          const nameY = s.showBusiness ? nameTop + nameLineStep : nameTop;
          const secondY = nameY + nameLineStep;
          const nameLines = envolverTextoTsplConReserva(p.nombre, firstChars, secondChars);

          if (nameLines[0]) commands.push(`TEXT ${x0 + padX},${nameY},"0",0,1,1,"${nameLines[0]}"`);
          if (nameLines[1]) commands.push(`TEXT ${x0 + padX},${secondY},"0",0,1,1,"${nameLines[1]}"`);
        }

        if (s.showPrice) {
          const priceY = (s.showBusiness ? secondLineY + nameLineStep : secondLineY) - mmToDots(0.08, dpi);
          const symbolY = priceY + Math.max(0, Math.round((12 * amountScale - 12 * symbolScale) * 0.45));
          commands.push(`TEXT ${symbolX},${symbolY},"0",0,${symbolScale},${symbolScale},"${tsplText(price.simbolo)}"`);
          commands.push(`TEXT ${amountX},${priceY},"0",0,${amountScale},${amountScale},"${tsplText(price.monto)}"`);
        }

<<<<<<< HEAD
        // Barcode: usa el mayor ancho físico posible y deja solo un margen lateral mínimo.
        // En TSPL el módulo de CODE128 se expresa en dots enteros; elegimos el mayor que cabe
        // sin recortar las zonas de silencio ni salir de la etiqueta.
        const barcodeY = padY + mmToDots(s.showBusiness ? 9.9 : 8.7, dpi);
        const barcodeHeight = mmToDots(s.compactMode ? 2.0 : 2.35, dpi);
        const estimatedUnits = Math.max(70, 11 * (code.length + 2) + 13);
        const barcodeMargin = Math.max(2, mmToDots(0.30, dpi));
        const targetBarcodeW = Math.max(1, labelW - barcodeMargin * 2);
        let moduleWidth = Math.max(1, Math.floor(targetBarcodeW / estimatedUnits));
        moduleWidth = Math.min(10, moduleWidth);
        const estimatedWidth = Math.min(targetBarcodeW, estimatedUnits * moduleWidth);
        const barcodeX = x0 + Math.max(barcodeMargin, Math.floor((labelW - estimatedWidth) / 2));
=======
        // Barcode: hasta ~90% del ancho útil, pero deliberadamente bajo.
        const barcodeY = padY + mmToDots(s.showBusiness ? 9.9 : 8.7, dpi);
        const barcodeHeight = mmToDots(s.compactMode ? 2.0 : 2.35, dpi);
        const estimatedUnits = Math.max(70, 11 * (code.length + 2) + 13);
        const targetBarcodeW = Math.floor(usableW * 0.90);
        const moduleWidth = estimatedUnits * 2 <= targetBarcodeW ? 2 : 1;
        const estimatedWidth = Math.min(targetBarcodeW, estimatedUnits * moduleWidth);
        const barcodeX = x0 + Math.max(padX, Math.floor((labelW - estimatedWidth) / 2));
>>>>>>> 2bc36874431ef8a8dd84d5086fb5416ffd1db084
        commands.push(`BARCODE ${barcodeX},${barcodeY},"128",${barcodeHeight},0,0,${moduleWidth},${moduleWidth},"${code}"`);

        // SKU más pequeño y separado del borde.
        if (s.showSku) {
          const skuScale = 1;
          const skuY = Math.min(labelH - mmToDots(1.85, dpi), barcodeY + barcodeHeight + mmToDots(0.42, dpi));
          const skuW = code.length * 8 * skuScale;
          const skuX = x0 + Math.max(padX, Math.floor((labelW - skuW) / 2));
          commands.push(`TEXT ${skuX},${skuY},"0",0,${skuScale},${skuScale},"${code}"`);
        }
      });
      commands.push('PRINT 1,1');
    });

    return commands.join('\r\n') + '\r\n';
  }


  function imprimirDirecto() {
    const s = getSettings();
    const items = expandirEtiquetas();
    if (!items.length) {
      Swal.fire('Sin etiquetas', 'Selecciona al menos un producto o variante.', 'warning');
      return;
    }
    if (!validarAnchoImpresora()) {
      Swal.fire('Formato demasiado ancho', 'Reduce el ancho, la separación o la cantidad de columnas para el perfil seleccionado.', 'warning');
      return;
    }

    const profile = perfiles.find(function (p) { return p.id === s.printerId; }) || perfiles[0];
    const ensurePrinter = $('#directPrinterName').val()
      ? conectarQz()
      : cargarListaImpresorasDirectas(false);

    $('#btnImprimirEtiquetas').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i><span>Enviando...</span>');
    ensurePrinter.then(function () {
      const printerName = $('#directPrinterName').val();
      if (!printerName) throw new Error('Selecciona una impresora instalada.');
      localStorage.setItem(STORAGE_DIRECT_PRINTER, printerName);
      const raw = generarTspl(items, s, profile);
      const config = qz.configs.create(printerName, {
        encoding: 'UTF-8',
        jobName: 'TiquePOS - Etiquetas'
      });
      return qz.print(config, [{ type: 'raw', format: 'command', flavor: 'plain', data: raw }]);
    }).then(function () {
      setDirectStatus('ok', 'Enviado');
      Swal.fire({ icon: 'success', title: 'Enviado a impresión', text: `${items.length} etiqueta(s) enviadas por QZ Tray.`, timer: 1800, showConfirmButton: false });
    }).catch(function (err) {
      console.error('Impresión directa QZ Tray:', err);
      setDirectStatus('err', 'No disponible');
      Swal.fire({
        icon: 'warning',
        title: 'No se pudo imprimir directamente',
        text: (err && err.message) ? err.message : 'Verifica que QZ Tray esté instalado, abierto y autorizado para este sitio.',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Usar impresión normal',
        denyButtonText: 'Descargar QZ Tray',
        cancelButtonText: 'Cerrar'
      }).then(function (result) {
        if (result.isConfirmed) imprimirSistema();
        if (result.isDenied) abrirDescargaQz();
      });
    }).finally(function () {
      actualizarModoImpresion();
      $('#btnImprimirEtiquetas').prop('disabled', false);
    });
  }

  function imprimir() {
    const mode = $('input[name="printMode"]:checked').val() || 'direct';
    if (mode === 'direct') return imprimirDirecto();
    return imprimirSistema();
  }

  function imprimirSistema() {
    const s = getSettings();
    const items = expandirEtiquetas();
    if (!items.length) {
      Swal.fire('Sin etiquetas', 'Selecciona al menos un producto o variante.', 'warning');
      return;
    }
    if (!validarAnchoImpresora()) {
      Swal.fire('Formato demasiado ancho', 'Reduce el ancho, la separación o la cantidad de columnas para el perfil seleccionado.', 'warning');
      return;
    }

    const pageWidth = s.columns * s.width + Math.max(0, s.columns - 1) * s.gapX;
    const pageHeight = s.height + s.gapY;
    const rows = [];
    for (let i = 0; i < items.length; i += s.columns) rows.push(items.slice(i, i + s.columns));

    const pages = rows.map(row => {
      const cells = row.map(p => etiquetaHtml(p, s, false)).join('');
      return `<section class="print-page">${cells}</section>`;
    }).join('');

    const win = window.open('', '_blank');
    if (!win) {
      Swal.fire('Ventana bloqueada', 'Permite ventanas emergentes para abrir la impresión de etiquetas.', 'warning');
      return;
    }

    try { win.opener = null; } catch (_) {}
    win.document.open();
    win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Etiquetas TiquePOS</title><style>
      *{box-sizing:border-box} html,body{margin:0;padding:0;background:#fff} body{font-family:Arial,sans-serif}
      @page{size:${pageWidth}mm ${pageHeight}mm;margin:0}
      .print-page{width:${pageWidth}mm;height:${pageHeight}mm;display:grid;grid-template-columns:repeat(${s.columns},${s.width}mm);column-gap:${s.gapX}mm;align-items:start;page-break-after:always;break-after:page;overflow:hidden}
      .print-page:last-child{page-break-after:auto;break-after:auto}
      .lb-preview-label{width:${s.width}mm;height:${s.height}mm;display:grid;grid-template-rows:48% 31% 17%;row-gap:2%;overflow:hidden;min-width:0;padding:.65mm .75mm .55mm;color:#050505;background:#fff;font-family:Arial,Helvetica,sans-serif;line-height:1}
      .lb-preview-label.with-border{outline:.2mm dashed #999;outline-offset:-.3mm}
      .lb-label-heading{min-width:0;min-height:0;overflow:hidden;display:flex;flex-direction:column;justify-content:flex-start}.lb-label-business{overflow:hidden;flex:0 0 auto;margin:0 0 .18mm;font-size:var(--lb-business-size,4.2pt);font-weight:700;line-height:1;text-align:left;text-overflow:ellipsis;white-space:nowrap}
      .lb-label-name-price{position:relative;min-width:0;min-height:0;flex:1 1 auto;overflow:hidden}
      .lb-label-name{display:-webkit-box;overflow:hidden;min-width:0;min-height:0;margin:0;padding:0 .1mm;-webkit-box-orient:vertical;-webkit-line-clamp:2;font-size:var(--lb-name-size,6pt);font-weight:900;line-height:1.05;letter-spacing:-.018em;text-align:left;white-space:normal;overflow-wrap:anywhere}
      .lb-label-price-row{position:absolute;right:0;bottom:0;z-index:3;display:flex;min-width:0;max-width:62%;align-items:flex-end;justify-content:flex-end;overflow:hidden;padding:0 0 .04mm .45mm;background:#fff;white-space:nowrap}
      .lb-label-currency{flex:0 0 auto;margin:0 .24mm .12em 0;font-size:var(--lb-currency-size,7.2pt);font-weight:500;line-height:.9}
      .lb-label-price-value{flex:0 1 auto;max-width:100%;overflow:hidden;font-size:var(--lb-price-size,11.8pt);font-weight:900;line-height:.84;letter-spacing:-.035em;text-overflow:clip}
<<<<<<< HEAD
      .lb-label-barcode{display:flex;min-width:0;min-height:0;align-items:center;justify-content:center;overflow:visible;margin-left:-.45mm;margin-right:-.45mm;padding:0}
      .lb-label-barcode svg{display:block;width:100%;height:52%;max-width:none;max-height:52%;overflow:hidden;flex:1 1 auto}
=======
      .lb-label-barcode{display:flex;min-width:0;min-height:0;align-items:center;justify-content:center;overflow:hidden;padding:.04mm .15mm}
      .lb-label-barcode svg{display:block;width:90%;height:52%;max-width:90%;max-height:52%;overflow:hidden;flex:0 1 auto}
>>>>>>> 2bc36874431ef8a8dd84d5086fb5416ffd1db084
      .lb-label-sku{display:flex;min-width:0;min-height:0;align-items:flex-start;justify-content:center;overflow:hidden;margin:0;padding:.05mm .2mm 0;font-family:Arial,Helvetica,sans-serif;font-size:var(--lb-sku-size,5.3pt);font-weight:800;line-height:.95;letter-spacing:.015em;text-align:center;text-overflow:ellipsis;white-space:nowrap}
    </style></head><body>${pages}</body></html>`);
    win.document.close();
    win.focus();
    setTimeout(function () { win.print(); }, 220);
  }

  function bindEvents() {
    $('#buscarEtiquetaProducto').on('input', function () {
      if (filtroActual === 'seleccionados') {
        filtroActual = 'todos';
        $('#filtrosEtiquetas .lb-filter').removeClass('active');
        $('#filtrosEtiquetas .lb-filter[data-filter="todos"]').addClass('active');
      }
      programarBusqueda();
    });

    $('#filtrosEtiquetas').on('click', '.lb-filter', function () {
      filtroActual = String($(this).data('filter'));
      $('#filtrosEtiquetas .lb-filter').removeClass('active');
      $(this).addClass('active');

      if (filtroActual === 'seleccionados') {
        if (peticionBusqueda && peticionBusqueda.readyState !== 4) peticionBusqueda.abort();
        renderProductos();
      } else {
        cargarProductos($('#buscarEtiquetaProducto').val());
      }
    });

    $('#listaProductosEtiquetas').on('change', '.lb-product-check', function () {
      const key = String($(this).data('key'));
      const p = productoPorKey(key);
      if (!p) return;
      if (this.checked) {
        seleccion.set(key, { producto: p, qty: 1 });
      } else {
        seleccion.delete(key);
      }
      renderProductos();
      actualizarPreview();
    });

    $('#listaProductosEtiquetas').on('change input', '.lb-product-qty', function () {
      const key = String($(this).data('key'));
      if (!seleccion.has(key)) return;
      const qty = Math.max(1, Math.min(9999, parseInt($(this).val(), 10) || 1));
      $(this).val(qty);
      seleccion.get(key).qty = qty;
      actualizarResumen();
      actualizarPreview();
    });

    $('#btnLimpiarSeleccion').on('click', function () {
      seleccion.clear();
      renderProductos();
      actualizarPreview();
    });

    $('#btnCantidadStock').on('click', function () {
      if (!seleccion.size) {
        Swal.fire('Sin selección', 'Selecciona primero los productos o variantes.', 'info');
        return;
      }
      seleccion.forEach(function (entry) {
        entry.qty = Math.max(1, Math.min(9999, parseInt(entry.producto.stock, 10) || 0));
      });
      renderProductos();
      actualizarPreview();
    });

    $('#selectorColumnas').on('click', '.lb-column-btn', function () {
      $('#selectorColumnas .lb-column-btn').removeClass('active');
      $(this).addClass('active');
      actualizarPreview();
    });

    $('#labelWidth,#labelHeight,#labelGapX,#labelGapY,#showBusiness,#showName,#showSku,#showPrice,#showBorder,#compactMode').on('input change', actualizarPreview);
    $('#printerProfile').on('change', function () { actualizarPerfilImpresora(); actualizarPreview(); });
    $('input[name="printMode"]').on('change', function () { actualizarModoImpresion(); });
    $('#btnDetectarImpresoras').on('click', function () {
      cargarListaImpresorasDirectas(true).catch(function (err) {
        Swal.fire({
          icon: 'warning',
          title: 'QZ Tray no disponible',
          text: (err && err.message) ? err.message : 'Instala y abre QZ Tray en este equipo.',
          showCancelButton: true,
          confirmButtonText: '<i class="fas fa-download mr-1"></i> Descargar QZ Tray',
          cancelButtonText: 'Cerrar'
        }).then(function (result) {
          if (result.isConfirmed) abrirDescargaQz();
        });
      });
    });
    $('#btnDescargarQz').on('click', abrirDescargaQz);
    $('#directPrinterName').on('change', function () {
      const name = String($(this).val() || '');
      if (name) { localStorage.setItem(STORAGE_DIRECT_PRINTER, name); $(this).attr('data-saved-printer', name); }
    });

    $('#btnRestaurarFormato').on('click', function () { applySettings(defaults); });
    $('#btnGuardarFormato').on('click', function () {
      localStorage.setItem(STORAGE_FORMAT, JSON.stringify(getSettings()));
      Swal.fire({ icon: 'success', title: 'Formato guardado', text: 'Se usará como configuración en este dispositivo.', timer: 1600, showConfirmButton: false });
    });

    $('#btnImprimirEtiquetas').on('click', imprimir);
    $('#btnAdministrarImpresoras').on('click', function () { renderPerfiles(); $('#modalImpresorasEtiquetas').modal('show'); });

    $('#btnAgregarPerfilImpresora').on('click', function () {
      const name = String($('#printerName').val() || '').trim();
      const model = String($('#printerModel').val() || '').trim() || 'Personalizada';
      const dpi = Math.max(72, Number($('#printerDpi').val() || 203));
      const maxWidth = Math.max(20, Number($('#printerMaxWidth').val() || 108));
      if (!name) {
        Swal.fire('Nombre requerido', 'Ingresa un nombre para el perfil de impresora.', 'warning');
        return;
      }
      const id = 'custom-' + Date.now();
      perfiles.push({ id, name, model, dpi, maxWidth, fixed: false, note: `${model} · ${dpi} DPI · ${maxWidth} mm de ancho máximo.` });
      guardarPerfiles();
      renderPerfiles();
      $('#printerProfile').val(id).trigger('change');
      $('#printerName,#printerModel').val('');
    });

    $('#listaPerfilesImpresora').on('click', '.btn-delete-printer', function () {
      const id = String($(this).data('id'));
      perfiles = perfiles.filter(p => p.id !== id || p.fixed);
      guardarPerfiles();
      renderPerfiles();
    });
  }

  $(function () {
    cargarPerfiles();
    renderPerfiles();
    bindEvents();
    loadSavedSettings();
    actualizarModoImpresion();
    if (!qzDisponible()) {
      setDirectStatus('err', 'QZ no cargado');
    } else {
      conectarQz().catch(function () { /* estado visual ya actualizado */ });
    }
    cargarNegocio();
    cargarProductos();
  });
})();
