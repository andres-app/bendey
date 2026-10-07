(function ($) {
  'use strict';

  var state = {
    catalogos: { categorias: [], subcategorias: [], almacenes: [], productos: [] },
    inventario: [],
    movimientos: [],
    totales: {},
    tablaInventario: null,
    tablaCategorias: null,
    tablaKardex: null,
    charts: { categorias: null, subcategorias: null, movimientos: null, kardex: null }
  };

  var chartColors = ['#00ad74', '#5268f2', '#f5a742', '#ef5b65', '#7c62e8', '#35a7d8', '#6dbd68', '#b76fd1', '#8c98aa'];

  function escapeHtml(valor) { return $('<div>').text(valor == null ? '' : String(valor)).html(); }
  function numero(valor, decimales) {
    var n = Number(valor || 0);
    return n.toLocaleString('es-PE', { minimumFractionDigits: decimales || 0, maximumFractionDigits: decimales || 0 });
  }
  function moneda(valor) {
    var n = Number(valor || 0);
    return 'S/ ' + n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function fechaHumana(valor) {
    if (!valor) return '';
    var p = String(valor).split('-');
    return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : valor;
  }
  function mostrarError(mensaje) {
    if (typeof iziToast !== 'undefined') iziToast.error({ title: 'Inventario', message: mensaje, position: 'topRight' });
    else alert(mensaje);
  }
  function paramsFiltros() {
    return {
      fecha_inicio: $('#fecha_inicio').val(), fecha_fin: $('#fecha_fin').val(),
      idcategoria: $('#idcategoria').val() || 0, idsubcategoria: $('#idsubcategoria').val() || 0,
      idalmacen: $('#idalmacen').val() || 0, buscar: $.trim($('#buscarInventario').val() || '')
    };
  }
  function validarFechas() {
    var inicio = $('#fecha_inicio').val(), fin = $('#fecha_fin').val();
    if (!inicio || !fin) { mostrarError('Seleccione la fecha inicial y final.'); return false; }
    if (fin < inicio) { mostrarError('La fecha final no puede ser menor que la fecha inicial.'); return false; }
    return true;
  }

  function cargarCatalogos() {
    return $.getJSON('Controllers/Consult.php?op=inventariofiltros').done(function (resp) {
      if (!resp || !resp.success) { mostrarError('No fue posible cargar los filtros del inventario.'); return; }
      state.catalogos = resp.data || state.catalogos;
      llenarSelects();
    }).fail(function () { mostrarError('No fue posible cargar los filtros del inventario.'); });
  }

  function llenarSelects() {
    var h = '<option value="0">Todas</option>';
    (state.catalogos.categorias || []).forEach(function (i) { h += '<option value="' + Number(i.idcategoria) + '">' + escapeHtml(i.nombre) + '</option>'; });
    $('#idcategoria').html(h);

    h = '<option value="0">Todos</option>';
    (state.catalogos.almacenes || []).forEach(function (i) { h += '<option value="' + Number(i.idalmacen) + '">' + escapeHtml(i.nombre) + '</option>'; });
    $('#idalmacen').html(h);

    h = '<option value="">Seleccione un producto</option>';
    (state.catalogos.productos || []).forEach(function (i) {
      h += '<option value="' + Number(i.idarticulo) + '">' + (i.codigo ? escapeHtml(i.codigo) + ' · ' : '') + escapeHtml(i.nombre) + ' (' + numero(i.stock, 0) + ')</option>';
    });
    $('#idarticuloKardex').html(h);
    actualizarSubcategorias();
  }

  function actualizarSubcategorias() {
    var idcategoria = Number($('#idcategoria').val() || 0), actual = Number($('#idsubcategoria').val() || 0), h = '<option value="0">Todas</option>';
    (state.catalogos.subcategorias || []).forEach(function (i) {
      if (idcategoria === 0 || Number(i.idcategoria) === idcategoria) h += '<option value="' + Number(i.idsubcategoria) + '">' + escapeHtml(i.nombre) + '</option>';
    });
    $('#idsubcategoria').html(h);
    if ($('#idsubcategoria option[value="' + actual + '"]').length) $('#idsubcategoria').val(String(actual));
  }

  function dataTableOptions(titulo, orientation) {
    return {
      destroy: true, pageLength: 25, order: [],
      language: { decimal: ',', thousands: '.', search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros', info: 'Mostrando _START_ a _END_ de _TOTAL_', infoEmpty: 'Sin registros', infoFiltered: '(filtrado de _MAX_)', zeroRecords: 'No se encontraron registros', paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' } },
      dom: 'Bfrtip',
      buttons: [
        { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', titleAttr: 'Exportar a Excel', title: titulo },
        { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf"></i> PDF', titleAttr: 'Exportar a PDF', title: titulo, pageSize: 'A4', orientation: orientation || 'landscape' },
        { extend: 'print', text: '<i class="fas fa-print"></i> Imprimir', title: titulo }
      ]
    };
  }

  function cargarInventario() {
    if (!validarFechas()) return;
    $('#btnAplicarFiltros').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Cargando...');
    $.ajax({ url: 'Controllers/Consult.php?op=inventariovalorizado', method: 'GET', dataType: 'json', data: paramsFiltros() })
      .done(function (resp) {
        if (!resp || !resp.success) { mostrarError((resp && resp.mensaje) || 'No fue posible obtener el inventario.'); return; }
        state.inventario = resp.data || [];
        state.movimientos = resp.movimientos_diarios || [];
        state.totales = resp.totales || {};
        actualizarMetricas(state.totales, state.inventario);
        renderResumen(state.inventario, state.movimientos);
        renderInventario(state.inventario);
        renderCategorias(state.inventario);
      })
      .fail(function (xhr) {
        mostrarError((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No fue posible obtener el inventario valorizado.');
      })
      .always(function () { $('#btnAplicarFiltros').prop('disabled', false).html('<i class="fas fa-filter mr-1"></i> Aplicar filtros'); });
  }

  function actualizarMetricas(t, rows) {
    $('#metricSaldoActual').text(numero(t.saldo_actual, 0) + ' und.');
    $('#metricValorActual').text(moneda(t.saldo_valorizado));
    $('#metricEntradas').text(numero(t.entradas, 0) + ' und.');
    $('#metricValorEntradas').text(moneda(t.valor_entradas) + ' valorizado');
    $('#metricSalidas').text(numero(t.salidas, 0) + ' und.');
    $('#metricValorSalidas').text(moneda(t.valor_salidas) + ' valorizado');

    var sinStock = rows.filter(function (r) { return Number(r.saldo_actual || 0) <= 0; }).length;
    var diferencias = Number(t.diferencias || 0);
    var productos = Number(t.productos || rows.length || 0);
    var conciliados = Math.max(productos - diferencias, 0);
    var pct = productos > 0 ? (conciliados / productos) * 100 : 100;
    $('#metricSinStock').text(numero(sinStock, 0));
    $('#metricConciliacion').text(numero(pct, 1) + '%');
    $('#metricDiferencias').text(numero(diferencias, 0) + (diferencias === 1 ? ' diferencia' : ' diferencias'));
    $('#periodoResumen').text(fechaHumana($('#fecha_inicio').val()) + ' — ' + fechaHumana($('#fecha_fin').val()));
  }

  function destroyChart(name) {
    if (state.charts[name]) { state.charts[name].destroy(); state.charts[name] = null; }
  }
  function groupedValue(rows, field) {
    var map = {};
    rows.forEach(function (r) {
      var k = String(r[field] || 'SIN CLASIFICAR');
      map[k] = (map[k] || 0) + Number(r.saldo_valorizado || 0);
    });
    return Object.keys(map).map(function (k) { return { label: k, value: map[k] }; }).sort(function (a, b) { return b.value - a.value; });
  }
  function compactGroups(items, maxItems) {
    if (items.length <= maxItems) return items;
    var head = items.slice(0, maxItems - 1), other = items.slice(maxItems - 1).reduce(function (sum, x) { return sum + x.value; }, 0);
    head.push({ label: 'Otros', value: other });
    return head;
  }
  function chartDefaults() {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.global.defaultFontFamily = 'Nunito, Arial, sans-serif';
    Chart.defaults.global.defaultFontColor = '#7d879b';
    Chart.defaults.global.elements.line.tension = 0.25;
  }

  function renderResumen(rows, movimientos) {
    chartDefaults();
    renderChartCategorias(rows);
    renderChartSubcategorias(rows);
    renderChartMovimientos(movimientos);
    renderTopProductos(rows);
    renderAlertas(rows);
  }

  function renderChartCategorias(rows) {
    destroyChart('categorias');
    var items = compactGroups(groupedValue(rows, 'categoria').filter(function (x) { return x.value > 0; }), 9);
    var canvas = $('#chartCategorias'), empty = $('#emptyChartCategorias');
    if (!items.length || typeof Chart === 'undefined') { canvas.addClass('d-none'); empty.removeClass('d-none'); return; }
    canvas.removeClass('d-none'); empty.addClass('d-none');
    state.charts.categorias = new Chart(canvas[0].getContext('2d'), {
      type: 'horizontalBar',
      data: { labels: items.map(function (x) { return x.label; }), datasets: [{ label: 'Valor', data: items.map(function (x) { return x.value; }), backgroundColor: items.map(function (_, i) { return chartColors[i % chartColors.length]; }), borderWidth: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, legend: { display: false }, scales: { xAxes: [{ ticks: { beginAtZero: true, callback: function (v) { return 'S/ ' + numero(v, 0); } }, gridLines: { color: '#f0f2f5' } }], yAxes: [{ gridLines: { display: false } }] }, tooltips: { callbacks: { label: function (item) { return moneda(item.xLabel); } } } }
    });
  }

  function renderChartSubcategorias(rows) {
    destroyChart('subcategorias');
    var items = compactGroups(groupedValue(rows, 'subcategoria').filter(function (x) { return x.value > 0; }), 7);
    var canvas = $('#chartSubcategorias'), empty = $('#emptyChartSubcategorias');
    if (!items.length || typeof Chart === 'undefined') { canvas.addClass('d-none'); empty.removeClass('d-none'); return; }
    canvas.removeClass('d-none'); empty.addClass('d-none');
    state.charts.subcategorias = new Chart(canvas[0].getContext('2d'), {
      type: 'doughnut',
      data: { labels: items.map(function (x) { return x.label; }), datasets: [{ data: items.map(function (x) { return x.value; }), backgroundColor: items.map(function (_, i) { return chartColors[i % chartColors.length]; }), borderWidth: 2, borderColor: '#fff' }] },
      options: { responsive: true, maintainAspectRatio: false, cutoutPercentage: 66, legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, usePointStyle: true } }, tooltips: { callbacks: { label: function (item, data) { return data.labels[item.index] + ': ' + moneda(data.datasets[0].data[item.index]); } } } }
    });
  }

  function renderChartMovimientos(rows) {
    destroyChart('movimientos');
    var canvas = $('#chartMovimientos'), empty = $('#emptyChartMovimientos');
    if (!rows.length || typeof Chart === 'undefined') { canvas.addClass('d-none'); empty.removeClass('d-none'); return; }
    canvas.removeClass('d-none'); empty.addClass('d-none');
    state.charts.movimientos = new Chart(canvas[0].getContext('2d'), {
      type: 'line',
      data: {
        labels: rows.map(function (r) { return fechaHumana(r.fecha); }),
        datasets: [
          { label: 'Entradas', data: rows.map(function (r) { return Number(r.entradas || 0); }), borderColor: '#00ad74', backgroundColor: 'rgba(0,173,116,.10)', pointBackgroundColor: '#00ad74', borderWidth: 2, pointRadius: 3, fill: false },
          { label: 'Salidas', data: rows.map(function (r) { return Number(r.salidas || 0); }), borderColor: '#ef5b65', backgroundColor: 'rgba(239,91,101,.08)', pointBackgroundColor: '#ef5b65', borderWidth: 2, pointRadius: 3, fill: false }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 9 } }, scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: '#f0f2f5' } }], xAxes: [{ gridLines: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12 } }] }, tooltips: { mode: 'index', intersect: false } }
    });
  }

  function renderTopProductos(rows) {
    var top = rows.slice().sort(function (a, b) { return Number(b.saldo_valorizado || 0) - Number(a.saldo_valorizado || 0); }).slice(0, 10), h = '';
    if (!top.length) h = '<tr><td class="text-center text-muted py-4">No hay productos para mostrar.</td></tr>';
    top.forEach(function (r, idx) {
      h += '<tr><td style="width:38px"><span class="top-rank">' + (idx + 1) + '</span></td>' +
        '<td><div class="product-name">' + escapeHtml(r.producto) + '</div><div class="product-meta">' + escapeHtml(r.codigo || 'SIN SKU') + ' · ' + numero(r.saldo_actual, 0) + ' und.</div></td>' +
        '<td class="text-right money">' + moneda(r.saldo_valorizado) + '</td>' +
        '<td style="width:42px"><button type="button" class="btn btn-sm btn-outline-success js-kardex" data-id="' + Number(r.idarticulo) + '" title="Ver Kardex"><i class="fas fa-exchange-alt"></i></button></td></tr>';
    });
    $('#tablaTopProductos tbody').html(h);
  }

  function renderAlertas(rows) {
    var sinStock = 0, negativos = 0, diferencias = 0, sinMovimiento = 0;
    rows.forEach(function (r) {
      var stock = Number(r.saldo_actual || 0);
      if (stock <= 0) sinStock++;
      if (stock < 0) negativos++;
      if (Math.abs(Number(r.diferencia_stock || 0)) > 0.0001) diferencias++;
      if (Number(r.entradas || 0) === 0 && Number(r.salidas || 0) === 0) sinMovimiento++;
    });
    $('#alertSinStock').text(numero(sinStock, 0));
    $('#alertNegativos').text(numero(negativos, 0));
    $('#alertDiferencias').text(numero(diferencias, 0));
    $('#alertSinMovimiento').text(numero(sinMovimiento, 0));
  }

  function renderInventario(rows) {
    if (state.tablaInventario) { state.tablaInventario.destroy(); state.tablaInventario = null; }
    var body = '', totals = { saldoInicial: 0, entradas: 0, salidas: 0, saldoActual: 0, valor: 0 };
    rows.forEach(function (r) {
      var diferencia = Number(r.diferencia_stock || 0), variantes = Number(r.cantidad_variantes || 0), meta = [];
      if (r.codigo) meta.push('SKU: ' + escapeHtml(r.codigo));
      if (variantes > 0) meta.push(variantes + (variantes === 1 ? ' variante' : ' variantes'));
      totals.saldoInicial += Number(r.saldo_inicial || 0); totals.entradas += Number(r.entradas || 0); totals.salidas += Number(r.salidas || 0); totals.saldoActual += Number(r.saldo_actual || 0); totals.valor += Number(r.saldo_valorizado || 0);
      body += '<tr><td><div class="product-name">' + escapeHtml(r.producto) + '</div><div class="product-meta">' + meta.join(' · ') + '</div></td>' +
        '<td>' + escapeHtml(r.categoria) + '</td><td>' + escapeHtml(r.subcategoria) + '</td><td>' + escapeHtml(r.almacen) + '</td>' +
        '<td class="qty text-right" data-order="' + Number(r.saldo_inicial || 0) + '">' + numero(r.saldo_inicial, 0) + '</td>' +
        '<td class="qty text-right" data-order="' + Number(r.entradas || 0) + '">' + numero(r.entradas, 0) + '</td>' +
        '<td class="qty text-right" data-order="' + Number(r.salidas || 0) + '">' + numero(r.salidas, 0) + '</td>' +
        '<td class="qty text-right font-weight-bold" data-order="' + Number(r.saldo_actual || 0) + '">' + numero(r.saldo_actual, 0) + '</td>' +
        '<td class="money text-right" data-order="' + Number(r.costo_promedio_actual || 0) + '">' + moneda(r.costo_promedio_actual) + '</td>' +
        '<td class="money text-right" data-order="' + Number(r.saldo_valorizado || 0) + '">' + moneda(r.saldo_valorizado) + '</td>';
      body += Math.abs(diferencia) < 0.0001 ? '<td><span class="reconcile-ok"><i class="fas fa-check-circle"></i> Conciliado</span></td>' : '<td><span class="reconcile-alert" title="Stock físico - stock FIFO"><i class="fas fa-exclamation-triangle"></i> ' + (diferencia > 0 ? '+' : '') + numero(diferencia, 0) + '</span></td>';
      body += '<td><button type="button" class="btn btn-sm btn-outline-success js-kardex" data-id="' + Number(r.idarticulo) + '" title="Ver Kardex valorizado"><i class="fas fa-exchange-alt"></i></button></td></tr>';
    });
    $('#tablaInventario tbody').html(body);
    $('#footSaldoInicial').text(numero(totals.saldoInicial, 0)); $('#footEntradas').text(numero(totals.entradas, 0)); $('#footSalidas').text(numero(totals.salidas, 0)); $('#footSaldoActual').text(numero(totals.saldoActual, 0)); $('#footValorizado').text(moneda(totals.valor));
    if ($.fn.DataTable) state.tablaInventario = $('#tablaInventario').DataTable(dataTableOptions('Inventario valorizado ' + fechaHumana($('#fecha_inicio').val()) + ' al ' + fechaHumana($('#fecha_fin').val()), 'landscape'));
  }

  function renderCategorias(rows) {
    if (state.tablaCategorias) { state.tablaCategorias.destroy(); state.tablaCategorias = null; }
    var grupos = {};
    rows.forEach(function (r) {
      var key = String(r.categoria) + '||' + String(r.subcategoria);
      if (!grupos[key]) grupos[key] = { categoria:r.categoria, subcategoria:r.subcategoria, productos:0, saldo_inicial:0, entradas:0, salidas:0, saldo_actual:0, saldo_valorizado:0, diferencias:0 };
      grupos[key].productos++; grupos[key].saldo_inicial += Number(r.saldo_inicial || 0); grupos[key].entradas += Number(r.entradas || 0); grupos[key].salidas += Number(r.salidas || 0); grupos[key].saldo_actual += Number(r.saldo_actual || 0); grupos[key].saldo_valorizado += Number(r.saldo_valorizado || 0);
      if (Math.abs(Number(r.diferencia_stock || 0)) > 0.0001) grupos[key].diferencias++;
    });
    var body = '';
    Object.keys(grupos).sort(function (a,b) { return a.localeCompare(b,'es'); }).forEach(function (key) {
      var g = grupos[key];
      body += '<tr><td class="font-weight-bold">' + escapeHtml(g.categoria) + '</td><td>' + escapeHtml(g.subcategoria) + '</td><td class="text-right">' + numero(g.productos,0) + '</td><td class="text-right">' + numero(g.saldo_inicial,0) + '</td><td class="text-right">' + numero(g.entradas,0) + '</td><td class="text-right">' + numero(g.salidas,0) + '</td><td class="text-right font-weight-bold">' + numero(g.saldo_actual,0) + '</td><td class="text-right money">' + moneda(g.saldo_valorizado) + '</td><td class="text-center">' + (g.diferencias ? '<span class="reconcile-alert">' + numero(g.diferencias,0) + '</span>' : '<span class="reconcile-ok"><i class="fas fa-check-circle"></i> 0</span>') + '</td></tr>';
    });
    $('#tablaCategorias tbody').html(body);
    if ($.fn.DataTable) state.tablaCategorias = $('#tablaCategorias').DataTable(dataTableOptions('Inventario valorizado por categorías', 'landscape'));
  }

  function cargarKardex(idarticulo) {
    idarticulo = Number(idarticulo || $('#idarticuloKardex').val() || 0);
    if (!idarticulo) { mostrarError('Seleccione un producto para consultar su Kardex.'); return; }
    if (!validarFechas()) return;
    $('#idarticuloKardex').val(String(idarticulo));
    $('#btnVerKardex').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Cargando...');
    $.ajax({ url:'Controllers/Consult.php?op=kardexvalorizado', method:'GET', dataType:'json', data:{ idarticulo:idarticulo, fecha_inicio:$('#fecha_inicio').val(), fecha_fin:$('#fecha_fin').val() } })
      .done(function (resp) { if (!resp || !resp.success) { mostrarError((resp && resp.mensaje) || 'No fue posible obtener el Kardex.'); return; } renderKardex(resp.data || {}); })
      .fail(function (xhr) { mostrarError((xhr.responseJSON && xhr.responseJSON.mensaje) || 'No fue posible obtener el Kardex valorizado.'); })
      .always(function () { $('#btnVerKardex').prop('disabled', false).html('<i class="fas fa-search"></i> Ver Kardex'); });
  }

  function renderKardex(data) {
    var p=data.producto||{}, actual=data.actual||{}, inicial=data.saldo_inicial||{}, movs=data.movimientos||[], diferencia=Number(actual.diferencia_stock||0);
    var entradas = movs.reduce(function(s,m){return s+Number(m.entrada_cantidad||0);},0), salidas = movs.reduce(function(s,m){return s+Number(m.salida_cantidad||0);},0);
    var conciliacion = Math.abs(diferencia)<.0001 ? '<span class="reconcile-ok"><i class="fas fa-check-circle"></i> Conciliado</span>' : '<span class="reconcile-alert"><i class="fas fa-exclamation-triangle"></i> Diferencia ' + (diferencia>0?'+':'') + numero(diferencia,0) + '</span>';
    var cab = '<div class="mb-2"><div class="product-name" style="font-size:1.08rem">' + escapeHtml(p.producto||'') + '</div><div class="product-meta">' + escapeHtml(p.codigo||'SIN SKU') + ' · ' + escapeHtml(p.categoria||'') + ' / ' + escapeHtml(p.subcategoria||'') + ' · ' + escapeHtml(p.almacen||'') + '</div></div>';
    cab += '<div class="kardex-summary"><div><span>Saldo inicial</span><strong>' + numero(inicial.cantidad,0) + ' und.<br>' + moneda(inicial.valor) + '</strong></div><div><span>Entradas período</span><strong>' + numero(entradas,0) + ' und.</strong></div><div><span>Salidas período</span><strong>' + numero(salidas,0) + ' und.</strong></div><div><span>Saldo actual físico</span><strong>' + numero(actual.saldo_fisico,0) + ' und.</strong></div><div><span>Saldo valorizado</span><strong>' + moneda(actual.saldo_valorizado) + '</strong></div><div><span>Conciliación FIFO</span><strong>' + conciliacion + '</strong></div></div>';
    $('#kardexCabecera').html(cab);

    renderChartKardex(movs);
    if (state.tablaKardex) { state.tablaKardex.destroy(); state.tablaKardex=null; }
    var body=''; movs.forEach(function(m){ body += '<tr><td>'+escapeHtml(fechaHumana(m.fecha))+'</td><td>'+escapeHtml(m.detalle)+'</td><td class="text-right">'+(Number(m.entrada_cantidad)?numero(m.entrada_cantidad,0):'')+'</td><td class="text-right">'+(Number(m.entrada_cantidad)?moneda(m.entrada_costo):'')+'</td><td class="text-right money">'+(Number(m.entrada_cantidad)?moneda(m.entrada_total):'')+'</td><td class="text-right">'+(Number(m.salida_cantidad)?numero(m.salida_cantidad,0):'')+'</td><td class="text-right">'+(Number(m.salida_cantidad)?moneda(m.salida_costo):'')+'</td><td class="text-right money">'+(Number(m.salida_cantidad)?moneda(m.salida_total):'')+'</td><td class="text-right font-weight-bold">'+numero(m.saldo_cantidad,0)+'</td><td class="text-right">'+moneda(m.saldo_costo)+'</td><td class="text-right money">'+moneda(m.saldo_total)+'</td></tr>'; });
    $('#tablaKardex tbody').html(body);
    if ($.fn.DataTable) state.tablaKardex = $('#tablaKardex').DataTable(dataTableOptions('Kardex valorizado - '+(p.codigo?p.codigo+' ':'')+(p.producto||''),'landscape'));
  }

  function renderChartKardex(movs) {
    destroyChart('kardex');
    if (!movs.length || typeof Chart === 'undefined') { $('#kardexChartCard').addClass('d-none'); return; }
    $('#kardexChartCard').removeClass('d-none');
    state.charts.kardex = new Chart($('#chartKardex')[0].getContext('2d'), {
      type:'line', data:{ labels:movs.map(function(m){return fechaHumana(m.fecha);}), datasets:[
        {label:'Saldo',data:movs.map(function(m){return Number(m.saldo_cantidad||0);}),borderColor:'#5268f2',backgroundColor:'rgba(82,104,242,.08)',borderWidth:2,pointRadius:3,fill:false},
        {label:'Entradas',data:movs.map(function(m){return Number(m.entrada_cantidad||0);}),borderColor:'#00ad74',backgroundColor:'rgba(0,173,116,.08)',borderWidth:2,pointRadius:2,fill:false},
        {label:'Salidas',data:movs.map(function(m){return Number(m.salida_cantidad||0);}),borderColor:'#ef5b65',backgroundColor:'rgba(239,91,101,.08)',borderWidth:2,pointRadius:2,fill:false}
      ]}, options:{responsive:true,maintainAspectRatio:false,legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:9}},scales:{yAxes:[{ticks:{beginAtZero:true,precision:0},gridLines:{color:'#f0f2f5'}}],xAxes:[{gridLines:{display:false},ticks:{autoSkip:true,maxTicksLimit:12}}]},tooltips:{mode:'index',intersect:false}}
    });
  }

  function exportCurrent(buttonIndex) {
    var target = state.tablaInventario;
    if ($('a[href="#tabCategorias"]').hasClass('active')) target = state.tablaCategorias;
    if ($('a[href="#tabKardex"]').hasClass('active')) target = state.tablaKardex;
    if (!target || !target.button) { mostrarError('Abra una tabla con datos para exportar.'); return; }
    target.button(buttonIndex).trigger();
  }

  function limpiarFiltros() {
    var hoy = new Date(), y = hoy.getFullYear(), m = String(hoy.getMonth()+1).padStart(2,'0'), d = String(hoy.getDate()).padStart(2,'0');
    $('#fecha_inicio').val(y+'-'+m+'-01'); $('#fecha_fin').val(y+'-'+m+'-'+d); $('#idcategoria').val('0'); actualizarSubcategorias(); $('#idsubcategoria').val('0'); $('#idalmacen').val('0'); $('#buscarInventario').val(''); cargarInventario();
  }

  $(document).on('click','.js-kardex',function(){ var id=Number($(this).data('id')||0); $('a[href="#tabKardex"]').tab('show'); cargarKardex(id); });
  $('#idcategoria').on('change',actualizarSubcategorias);
  $('#btnAplicarFiltros').on('click',cargarInventario);
  $('#btnLimpiarFiltros').on('click',limpiarFiltros);
  $('#btnVerKardex').on('click',function(){cargarKardex();});
  $('#buscarInventario').on('keydown',function(e){if(e.key==='Enter'){e.preventDefault();cargarInventario();}});
  $('#btnExportExcel').on('click',function(){exportCurrent(0);});
  $('#btnExportPdf').on('click',function(){exportCurrent(1);});
  $('#btnPrintInventario').on('click',function(){exportCurrent(2);});
  $('a[data-toggle="tab"]').on('shown.bs.tab', function(){ Object.keys(state.charts).forEach(function(k){ if(state.charts[k] && state.charts[k].resize) state.charts[k].resize(); }); });

  cargarCatalogos().always(function(){ cargarInventario(); });
})(jQuery);
