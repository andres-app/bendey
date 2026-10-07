(function ($) {
  'use strict';

  var state = {
    catalogos: { categorias: [], subcategorias: [], almacenes: [], productos: [], empresa: {} },
    inventario: [],
    movimientos: [],
    totales: {},
    kardexData: null,
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
      dom: 'frtip'
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
    state.kardexData = data || null;
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

  function textoSelect(selector, fallback) {
    var $el = $(selector), texto = $el.find('option:selected').text();
    texto = $.trim(texto || '');
    return texto && !/^(todas|todos)$/i.test(texto) ? texto : (fallback || 'Todos');
  }

  function datosReporte() {
    var empresa = state.catalogos.empresa || {};
    var logo = $.trim(String(empresa.logo || ''));
    return {
      empresa: $.trim(String(empresa.nombre || 'TiquePOS')) || 'TiquePOS',
      documento: $.trim(String(empresa.ndocumento || empresa.documento || '')),
      direccion: $.trim(String(empresa.direccion || '')),
      telefono: $.trim(String(empresa.telefono || '')),
      email: $.trim(String(empresa.email || '')),
      ciudad: $.trim(String(empresa.ciudad || '')),
      pais: $.trim(String(empresa.pais || '')),
      simbolo: $.trim(String(empresa.simbolo || 'S/')) || 'S/',
      logoUrl: logo ? 'storage/images/company/' + encodeURIComponent(logo) : 'Assets/img/tiquepos_logo.png',
      usuario: $.trim(String($('.inventory-report').data('report-user') || 'Usuario')),
      desde: $('#fecha_inicio').val(),
      hasta: $('#fecha_fin').val(),
      almacen: textoSelect('#idalmacen', 'Todos'),
      categoria: textoSelect('#idcategoria', 'Todas'),
      subcategoria: textoSelect('#idsubcategoria', 'Todas'),
      busqueda: $.trim($('#buscarInventario').val() || '') || 'Todos los productos'
    };
  }

  function nombreArchivo(prefijo) {
    var f = $('#fecha_fin').val() || '';
    return (prefijo || 'inventario_valorizado') + '_' + f.replace(/-/g, '') + '.xlsx';
  }

  function fechaHoraEmision() {
    var d = new Date();
    return d.toLocaleDateString('es-PE') + ' ' + d.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
  }

  function estadisticasAlertas(rows) {
    var r = { sinStock: 0, negativos: 0, diferencias: 0, sinMovimiento: 0 };
    (rows || []).forEach(function (x) {
      var stock = Number(x.saldo_actual || 0);
      if (stock <= 0) r.sinStock++;
      if (stock < 0) r.negativos++;
      if (Math.abs(Number(x.diferencia_stock || 0)) > 0.0001) r.diferencias++;
      if (Number(x.entradas || 0) === 0 && Number(x.salidas || 0) === 0) r.sinMovimiento++;
    });
    return r;
  }

  function resumenCategorias(rows) {
    var grupos = {};
    (rows || []).forEach(function (r) {
      var key = String(r.categoria || 'SIN CATEGORÍA') + '||' + String(r.subcategoria || 'SIN SUBCATEGORÍA');
      if (!grupos[key]) grupos[key] = { categoria:r.categoria || 'SIN CATEGORÍA', subcategoria:r.subcategoria || 'SIN SUBCATEGORÍA', productos:0, saldo_inicial:0, entradas:0, salidas:0, saldo_actual:0, saldo_valorizado:0, diferencias:0 };
      grupos[key].productos++;
      grupos[key].saldo_inicial += Number(r.saldo_inicial || 0);
      grupos[key].entradas += Number(r.entradas || 0);
      grupos[key].salidas += Number(r.salidas || 0);
      grupos[key].saldo_actual += Number(r.saldo_actual || 0);
      grupos[key].saldo_valorizado += Number(r.saldo_valorizado || 0);
      if (Math.abs(Number(r.diferencia_stock || 0)) > 0.0001) grupos[key].diferencias++;
    });
    return Object.keys(grupos).map(function(k){ return grupos[k]; }).sort(function(a,b){
      var ca = String(a.categoria).localeCompare(String(b.categoria), 'es');
      return ca || String(a.subcategoria).localeCompare(String(b.subcategoria), 'es');
    });
  }

  function productosTop(rows, limite) {
    return (rows || []).slice().sort(function(a,b){ return Number(b.saldo_valorizado || 0) - Number(a.saldo_valorizado || 0); }).slice(0, limite || 10);
  }

  function canvasDataUrl(id) {
    var el = document.getElementById(id);
    if (!el || el.classList.contains('d-none')) return null;
    try { return el.toDataURL('image/png', 1); } catch (e) { return null; }
  }

  function graficoTopProductosDataUrl() {
    if (typeof Chart === 'undefined') return null;
    var top = productosTop(state.inventario, 10);
    if (!top.length) return null;
    var canvas = document.createElement('canvas');
    canvas.width = 1000; canvas.height = 420;
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
    var chart = new Chart(ctx, {
      type:'horizontalBar',
      data:{ labels:top.map(function(r){ return (r.codigo ? r.codigo + ' · ' : '') + r.producto; }), datasets:[{ label:'Saldo valorizado', data:top.map(function(r){ return Number(r.saldo_valorizado || 0); }), backgroundColor:'#00ad74', borderWidth:0 }] },
      options:{ responsive:false, animation:{duration:0}, legend:{display:false}, scales:{ xAxes:[{ticks:{beginAtZero:true,callback:function(v){return 'S/ '+numero(v,0);}},gridLines:{color:'#edf1f5'}}], yAxes:[{gridLines:{display:false},ticks:{fontSize:11}}] }, tooltips:{enabled:false} }
    });
    var data = null;
    try { chart.update(0); data = canvas.toDataURL('image/png', 1); } catch (e) { data = null; }
    chart.destroy();
    return data;
  }

  function imagenesReporte() {
    return {
      categorias: canvasDataUrl('chartCategorias'),
      movimientos: canvasDataUrl('chartMovimientos'),
      subcategorias: canvasDataUrl('chartSubcategorias'),
      topProductos: graficoTopProductosDataUrl(),
      kardex: canvasDataUrl('chartKardex')
    };
  }

  function cargarImagenDataUrl(url) {
    var d = $.Deferred();
    if (!url) { d.resolve(null); return d.promise(); }
    var img = new Image();
    img.onload = function () {
      try {
        var c = document.createElement('canvas');
        var maxW = 600, maxH = 240, scale = Math.min(maxW / img.naturalWidth, maxH / img.naturalHeight, 1);
        c.width = Math.max(1, Math.round(img.naturalWidth * scale));
        c.height = Math.max(1, Math.round(img.naturalHeight * scale));
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        d.resolve(c.toDataURL('image/png', 1));
      } catch (e) { d.resolve(null); }
    };
    img.onerror = function () { d.resolve(null); };
    img.src = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'v=' + Date.now();
    return d.promise();
  }

  function celdaKpiPdf(label, value, fill) {
    return { stack:[{text:label,style:'kpiLabel'},{text:value,style:'kpiValue'}], fillColor:fill || '#F7F9FC', margin:[8,7,8,7] };
  }

  function imagenPdf(dataUrl, fit) {
    return dataUrl ? { image:dataUrl, fit:fit || [350,180], alignment:'center', margin:[0,4,0,2] } : { text:'Sin datos para graficar', color:'#98A2B3', italics:true, alignment:'center', margin:[0,55,0,55] };
  }

  function tablaProductosPdf() {
    var body = [[
      {text:'Producto / SKU',style:'tableHeader'}, {text:'Categoría',style:'tableHeader'}, {text:'Subcategoría',style:'tableHeader'}, {text:'Almacén',style:'tableHeader'},
      {text:'Saldo inicial',style:'tableHeader'}, {text:'Entradas',style:'tableHeader'}, {text:'Salidas',style:'tableHeader'}, {text:'Saldo actual',style:'tableHeader'},
      {text:'Costo actual',style:'tableHeader'}, {text:'Saldo valorizado',style:'tableHeader'}, {text:'Conciliación',style:'tableHeader'}
    ]];
    state.inventario.forEach(function(r){
      var dif = Number(r.diferencia_stock || 0);
      body.push([
        (r.codigo ? r.codigo + ' · ' : '') + r.producto, r.categoria, r.subcategoria, r.almacen,
        numero(r.saldo_inicial,0), numero(r.entradas,0), numero(r.salidas,0), numero(r.saldo_actual,0),
        moneda(r.costo_promedio_actual), moneda(r.saldo_valorizado), Math.abs(dif)<0.0001 ? 'Conciliado' : 'Diferencia ' + (dif>0?'+':'') + numero(dif,0)
      ]);
    });
    return { table:{ headerRows:1, widths:[92,57,57,48,39,34,34,39,46,54,47], body:body }, layout:{ fillColor:function(row){ return row===0 ? '#00A46A' : (row%2===0 ? '#F8FAFC' : null); }, hLineColor:function(){return '#E7ECF2';}, vLineColor:function(){return '#E7ECF2';}, paddingLeft:function(){return 4;},paddingRight:function(){return 4;},paddingTop:function(){return 4;},paddingBottom:function(){return 4;} }, fontSize:6.4 };
  }

  function tablaCategoriasPdf() {
    var rows = resumenCategorias(state.inventario), body = [[
      {text:'Categoría',style:'tableHeader'}, {text:'Subcategoría',style:'tableHeader'}, {text:'Productos',style:'tableHeader'}, {text:'Saldo inicial',style:'tableHeader'},
      {text:'Entradas',style:'tableHeader'}, {text:'Salidas',style:'tableHeader'}, {text:'Saldo actual',style:'tableHeader'}, {text:'Saldo valorizado',style:'tableHeader'}, {text:'Diferencias',style:'tableHeader'}
    ]];
    rows.forEach(function(r){ body.push([r.categoria,r.subcategoria,numero(r.productos,0),numero(r.saldo_inicial,0),numero(r.entradas,0),numero(r.salidas,0),numero(r.saldo_actual,0),moneda(r.saldo_valorizado),numero(r.diferencias,0)]); });
    return { table:{headerRows:1,widths:[95,95,46,58,48,48,58,72,52],body:body}, layout:{fillColor:function(row){return row===0?'#00A46A':(row%2===0?'#F8FAFC':null);},hLineColor:function(){return '#E7ECF2';},vLineColor:function(){return '#E7ECF2';}}, fontSize:7 };
  }

  function tablaKardexPdf() {
    var data=state.kardexData||{}, movs=data.movimientos||[], body=[[{text:'Fecha',style:'tableHeader'},{text:'Documento / Detalle',style:'tableHeader'},{text:'Ent. cant.',style:'tableHeader'},{text:'Ent. costo',style:'tableHeader'},{text:'Ent. total',style:'tableHeader'},{text:'Sal. cant.',style:'tableHeader'},{text:'Sal. costo',style:'tableHeader'},{text:'Sal. total',style:'tableHeader'},{text:'Saldo cant.',style:'tableHeader'},{text:'Saldo costo',style:'tableHeader'},{text:'Saldo total',style:'tableHeader'}]];
    movs.forEach(function(m){ body.push([fechaHumana(m.fecha),m.detalle,Number(m.entrada_cantidad)?numero(m.entrada_cantidad,0):'',Number(m.entrada_cantidad)?moneda(m.entrada_costo):'',Number(m.entrada_cantidad)?moneda(m.entrada_total):'',Number(m.salida_cantidad)?numero(m.salida_cantidad,0):'',Number(m.salida_cantidad)?moneda(m.salida_costo):'',Number(m.salida_cantidad)?moneda(m.salida_total):'',numero(m.saldo_cantidad,0),moneda(m.saldo_costo),moneda(m.saldo_total)]); });
    return { table:{headerRows:1,widths:[42,120,38,45,48,38,45,48,42,46,52],body:body},layout:{fillColor:function(row){return row===0?'#00A46A':(row%2===0?'#F8FAFC':null);},hLineColor:function(){return '#E7ECF2';},vLineColor:function(){return '#E7ECF2';}},fontSize:6.6 };
  }

  function exportarPdfProfesional() {
    var d = $.Deferred();
    if (typeof pdfMake === 'undefined') { mostrarError('El componente de PDF no está disponible.'); d.resolve(); return d.promise(); }
    var meta=datosReporte(), imgs=imagenesReporte(), alertas=estadisticasAlertas(state.inventario), kardexActivo=$('a[href="#tabKardex"]').hasClass('active') && state.kardexData;
    cargarImagenDataUrl(meta.logoUrl).always(function(logo){
      try {
        var encabezado = { columns:[ logo ? {image:logo,width:82,margin:[0,0,14,0]} : {text:'TIQUEPOS',bold:true,fontSize:14,color:'#00A46A',width:82}, { width:'*', stack:[{text:kardexActivo?'KARDEX VALORIZADO':'REPORTE DE INVENTARIO VALORIZADO',fontSize:19,bold:true,color:'#182033'},{text:meta.empresa,fontSize:11,bold:true,color:'#475467',margin:[0,3,0,0]},{text:(meta.documento?'RUC/DOC: '+meta.documento+' · ':'')+(meta.direccion||[meta.ciudad,meta.pais].filter(Boolean).join(', ')),fontSize:7.5,color:'#98A2B3',margin:[0,2,0,0]}] }, {width:115,stack:[{text:'EMITIDO',fontSize:7,bold:true,color:'#98A2B3',alignment:'right'},{text:fechaHoraEmision(),fontSize:8.5,bold:true,color:'#344054',alignment:'right',margin:[0,2,0,0]},{text:'Por: '+meta.usuario,fontSize:7,color:'#667085',alignment:'right',margin:[0,3,0,0]}]} ], margin:[0,0,0,12] };
        var content=[encabezado,{table:{widths:['*'],body:[[{stack:[{text:'PERÍODO Y FILTROS',fontSize:7,bold:true,color:'#667085'},{text:fechaHumana(meta.desde)+' al '+fechaHumana(meta.hasta)+'  ·  Almacén: '+meta.almacen+'  ·  Categoría: '+meta.categoria+'  ·  Subcategoría: '+meta.subcategoria+'  ·  Producto/SKU: '+meta.busqueda,fontSize:8.2,color:'#344054',margin:[0,3,0,0]}],fillColor:'#F7F9FC',margin:[8,6,8,6]}]]},layout:'noBorders',margin:[0,0,0,12]}];

        if (kardexActivo) {
          var kd=state.kardexData||{}, p=kd.producto||{}, actual=kd.actual||{}, inicial=kd.saldo_inicial||{}, movs=kd.movimientos||[];
          var ent=movs.reduce(function(s,m){return s+Number(m.entrada_cantidad||0);},0), sal=movs.reduce(function(s,m){return s+Number(m.salida_cantidad||0);},0), dif=Number(actual.diferencia_stock||0);
          content.push({text:(p.codigo?p.codigo+' · ':'')+(p.producto||''),fontSize:14,bold:true,color:'#182033',margin:[0,2,0,3]},{text:(p.categoria||'')+' / '+(p.subcategoria||'')+' · '+(p.almacen||''),fontSize:8,color:'#667085',margin:[0,0,0,9]},
            {table:{widths:['*','*','*','*','*','*'],body:[[celdaKpiPdf('SALDO INICIAL',numero(inicial.cantidad,0)+' und.','#F7F9FC'),celdaKpiPdf('ENTRADAS',numero(ent,0)+' und.','#EAF8F2'),celdaKpiPdf('SALIDAS',numero(sal,0)+' und.','#FFF4E6'),celdaKpiPdf('SALDO ACTUAL',numero(actual.saldo_fisico,0)+' und.','#EEF2FF'),celdaKpiPdf('VALOR ACTUAL',moneda(actual.saldo_valorizado),'#EAF8F2'),celdaKpiPdf('CONCILIACIÓN',Math.abs(dif)<0.0001?'Conciliado':'Dif. '+(dif>0?'+':'')+numero(dif,0),Math.abs(dif)<0.0001?'#EAF8F2':'#FEF0F0')]]},layout:{hLineWidth:function(){return 0;},vLineWidth:function(){return 0;}},margin:[0,0,0,12]},
            {table:{widths:['*'],body:[[{stack:[{text:'Evolución del saldo',style:'chartTitle'},imagenPdf(imgs.kardex,[680,230])],fillColor:'#FFFFFF',margin:[8,6,8,6]}]]},layout:{hLineColor:function(){return '#E7ECF2';},vLineColor:function(){return '#E7ECF2';}},margin:[0,0,0,12]},
            {text:'Detalle de movimientos',style:'sectionTitle',pageBreak:'before',margin:[0,0,0,7]},tablaKardexPdf());
        } else {
          var t=state.totales||{}, productos=Number(t.productos||state.inventario.length||0), diferencias=Number(t.diferencias||0), pct=productos?Math.max(0,(productos-diferencias)/productos*100):100;
          content.push({table:{widths:['*','*','*','*','*','*'],body:[[celdaKpiPdf('VALOR INVENTARIO',moneda(t.saldo_valorizado),'#EAF8F2'),celdaKpiPdf('SALDO ACTUAL',numero(t.saldo_actual,0)+' und.','#EEF2FF'),celdaKpiPdf('ENTRADAS',numero(t.entradas,0)+' und.','#EAF8F2'),celdaKpiPdf('SALIDAS',numero(t.salidas,0)+' und.','#FFF4E6'),celdaKpiPdf('SIN STOCK',numero(alertas.sinStock,0),'#FEF0F0'),celdaKpiPdf('CONCILIACIÓN',numero(pct,1)+'%','#EAF8F2')]]},layout:{hLineWidth:function(){return 0;},vLineWidth:function(){return 0;}},margin:[0,0,0,12]});
          content.push({columns:[{width:'50%',table:{widths:['*'],body:[[{stack:[{text:'Valor por categoría',style:'chartTitle'},{text:'Saldo valorizado actual',style:'chartSub'},imagenPdf(imgs.categorias,[335,170])],fillColor:'#FFFFFF',margin:[8,7,8,5]}]]},layout:{hLineColor:function(){return '#E7ECF2';},vLineColor:function(){return '#E7ECF2';}},margin:[0,0,5,0]},{width:'50%',table:{widths:['*'],body:[[{stack:[{text:'Entradas vs. salidas',style:'chartTitle'},{text:'Movimiento diario de unidades',style:'chartSub'},imagenPdf(imgs.movimientos,[335,170])],fillColor:'#FFFFFF',margin:[8,7,8,5]}]]},layout:{hLineColor:function(){return '#E7ECF2';},vLineColor:function(){return '#E7ECF2';}},margin:[5,0,0,0]}],margin:[0,0,0,10]});
          content.push({columns:[{width:'50%',table:{widths:['*'],body:[[{stack:[{text:'Participación por subcategoría',style:'chartTitle'},{text:'Composición del valor del inventario',style:'chartSub'},imagenPdf(imgs.subcategorias,[335,170])],fillColor:'#FFFFFF',margin:[8,7,8,5]}]]},layout:{hLineColor:function(){return '#E7ECF2';},vLineColor:function(){return '#E7ECF2';}},margin:[0,0,5,0]},{width:'50%',table:{widths:['*'],body:[[{stack:[{text:'Top 10 productos por valor',style:'chartTitle'},{text:'Productos con mayor capital en stock',style:'chartSub'},imagenPdf(imgs.topProductos,[335,170])],fillColor:'#FFFFFF',margin:[8,7,8,5]}]]},layout:{hLineColor:function(){return '#E7ECF2';},vLineColor:function(){return '#E7ECF2';}},margin:[5,0,0,0]}],margin:[0,0,0,10]});
          content.push({text:'Detalle por producto',style:'sectionTitle',pageBreak:'before',margin:[0,0,0,7]},tablaProductosPdf(),{text:'Resumen por categoría / subcategoría',style:'sectionTitle',pageBreak:'before',margin:[0,0,0,7]},tablaCategoriasPdf());
        }

        var doc={pageSize:'A4',pageOrientation:'landscape',pageMargins:[26,25,26,32],content:content,styles:{kpiLabel:{fontSize:6.5,bold:true,color:'#667085'},kpiValue:{fontSize:11,bold:true,color:'#182033',margin:[0,3,0,0]},chartTitle:{fontSize:9,bold:true,color:'#182033'},chartSub:{fontSize:6.8,color:'#98A2B3',margin:[0,2,0,3]},sectionTitle:{fontSize:12,bold:true,color:'#182033'},tableHeader:{bold:true,color:'#FFFFFF',fontSize:6.5}},defaultStyle:{fontSize:8,color:'#344054'},footer:function(currentPage,pageCount){return {columns:[{text:'TiquePOS · '+meta.empresa,color:'#98A2B3',fontSize:6.8,margin:[26,8,0,0]},{text:'Página '+currentPage+' de '+pageCount,color:'#667085',fontSize:6.8,alignment:'right',margin:[0,8,26,0]}]};}};
        pdfMake.createPdf(doc).download((kardexActivo?'kardex_valorizado_':'inventario_valorizado_')+String(meta.hasta||'').replace(/-/g,'')+'.pdf');
      } catch(e) { console.error(e); mostrarError('No fue posible generar el PDF profesional.'); }
      d.resolve();
    });
    return d.promise();
  }

  function tablaHtmlProductos() {
    var h='<table><thead><tr><th>Producto / SKU</th><th>Categoría</th><th>Subcategoría</th><th>Almacén</th><th>Saldo inicial</th><th>Entradas</th><th>Salidas</th><th>Saldo actual</th><th>Costo actual</th><th>Saldo valorizado</th><th>Conciliación</th></tr></thead><tbody>';
    state.inventario.forEach(function(r){var dif=Number(r.diferencia_stock||0);h+='<tr><td><strong>'+escapeHtml((r.codigo?r.codigo+' · ':'')+r.producto)+'</strong></td><td>'+escapeHtml(r.categoria)+'</td><td>'+escapeHtml(r.subcategoria)+'</td><td>'+escapeHtml(r.almacen)+'</td><td>'+numero(r.saldo_inicial,0)+'</td><td>'+numero(r.entradas,0)+'</td><td>'+numero(r.salidas,0)+'</td><td><strong>'+numero(r.saldo_actual,0)+'</strong></td><td>'+moneda(r.costo_promedio_actual)+'</td><td><strong>'+moneda(r.saldo_valorizado)+'</strong></td><td>'+(Math.abs(dif)<0.0001?'Conciliado':'Diferencia '+(dif>0?'+':'')+numero(dif,0))+'</td></tr>';});
    return h+'</tbody></table>';
  }

  function tablaHtmlKardex() {
    var d=state.kardexData||{},h='<table><thead><tr><th>Fecha</th><th>Documento / Detalle</th><th>Entrada cant.</th><th>Entrada costo</th><th>Entrada total</th><th>Salida cant.</th><th>Salida costo</th><th>Salida total</th><th>Saldo cant.</th><th>Saldo costo</th><th>Saldo total</th></tr></thead><tbody>';
    (d.movimientos||[]).forEach(function(m){h+='<tr><td>'+escapeHtml(fechaHumana(m.fecha))+'</td><td>'+escapeHtml(m.detalle)+'</td><td>'+(Number(m.entrada_cantidad)?numero(m.entrada_cantidad,0):'')+'</td><td>'+(Number(m.entrada_cantidad)?moneda(m.entrada_costo):'')+'</td><td>'+(Number(m.entrada_cantidad)?moneda(m.entrada_total):'')+'</td><td>'+(Number(m.salida_cantidad)?numero(m.salida_cantidad,0):'')+'</td><td>'+(Number(m.salida_cantidad)?moneda(m.salida_costo):'')+'</td><td>'+(Number(m.salida_cantidad)?moneda(m.salida_total):'')+'</td><td>'+numero(m.saldo_cantidad,0)+'</td><td>'+moneda(m.saldo_costo)+'</td><td>'+moneda(m.saldo_total)+'</td></tr>';});
    return h+'</tbody></table>';
  }

  function imprimirProfesional() {
    var d=$.Deferred(), meta=datosReporte(), imgs=imagenesReporte(), alertas=estadisticasAlertas(state.inventario), kardexActivo=$('a[href="#tabKardex"]').hasClass('active') && state.kardexData;
    var win=window.open('','_blank');
    if(!win){mostrarError('El navegador bloqueó la ventana de impresión. Permita ventanas emergentes para TiquePOS.');d.resolve();return d.promise();}
    win.document.write('<div style="font-family:Arial;padding:30px;color:#667085">Preparando reporte...</div>');
    cargarImagenDataUrl(meta.logoUrl).always(function(logo){
      try{
        var t=state.totales||{},productos=Number(t.productos||state.inventario.length||0),dif=Number(t.diferencias||0),pct=productos?Math.max(0,(productos-dif)/productos*100):100;
        var css='@page{size:A4 landscape;margin:11mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;color:#182033;margin:0;font-size:10px}.head{display:flex;align-items:flex-start;gap:16px;border-bottom:3px solid #00a46a;padding-bottom:12px;margin-bottom:12px}.logo{width:108px;max-height:55px;object-fit:contain}.grow{flex:1}.title{font-size:22px;font-weight:800;margin:0 0 3px}.company{font-size:12px;font-weight:700;color:#475467}.muted{color:#98a2b3}.right{text-align:right}.filters{background:#f7f9fc;border:1px solid #e7ecf2;border-radius:8px;padding:8px 10px;margin:10px 0 12px}.kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:7px;margin-bottom:12px}.kpi{border:1px solid #e7ecf2;border-radius:9px;padding:9px;background:#fff}.kpi label{display:block;font-size:8px;font-weight:800;color:#667085;text-transform:uppercase}.kpi strong{display:block;font-size:14px;margin-top:4px}.charts{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px}.chart{border:1px solid #e7ecf2;border-radius:10px;padding:9px;break-inside:avoid}.chart h3{font-size:11px;margin:0 0 2px}.chart p{margin:0 0 6px;color:#98a2b3;font-size:8px}.chart img{width:100%;height:185px;object-fit:contain}.section-title{font-size:14px;margin:16px 0 7px}.page-break{break-before:page}table{width:100%;border-collapse:collapse;font-size:7.5px}th{background:#00a46a;color:#fff;text-align:left;padding:6px 5px;border:1px solid #008f60}td{padding:5px;border:1px solid #e7ecf2}tbody tr:nth-child(even){background:#f8fafc}.footer{margin-top:12px;border-top:1px solid #e7ecf2;padding-top:7px;display:flex;justify-content:space-between;color:#98a2b3;font-size:8px}@media print{.no-print{display:none!important}.page-break{break-before:page}.chart,.kpi{break-inside:avoid}}';
        var header='<div class="head">'+(logo?'<img class="logo" src="'+logo+'">':'<div style="font-size:18px;font-weight:800;color:#00a46a;width:108px">TIQUEPOS</div>')+'<div class="grow"><div class="title">'+(kardexActivo?'Kardex valorizado':'Reporte de inventario valorizado')+'</div><div class="company">'+escapeHtml(meta.empresa)+'</div><div class="muted">'+escapeHtml((meta.documento?'RUC/DOC: '+meta.documento+' · ':'')+(meta.direccion||[meta.ciudad,meta.pais].filter(Boolean).join(', ')))+'</div></div><div class="right"><strong>Emitido</strong><br>'+escapeHtml(fechaHoraEmision())+'<br><span class="muted">Por: '+escapeHtml(meta.usuario)+'</span></div></div>';
        var filtros='<div class="filters"><strong>Período:</strong> '+escapeHtml(fechaHumana(meta.desde))+' al '+escapeHtml(fechaHumana(meta.hasta))+' &nbsp; · &nbsp; <strong>Almacén:</strong> '+escapeHtml(meta.almacen)+' &nbsp; · &nbsp; <strong>Categoría:</strong> '+escapeHtml(meta.categoria)+' &nbsp; · &nbsp; <strong>Subcategoría:</strong> '+escapeHtml(meta.subcategoria)+' &nbsp; · &nbsp; <strong>Producto/SKU:</strong> '+escapeHtml(meta.busqueda)+'</div>';
        var body='';
        if(kardexActivo){
          var kd=state.kardexData||{},p=kd.producto||{},actual=kd.actual||{},ini=kd.saldo_inicial||{},movs=kd.movimientos||[],ent=movs.reduce(function(s,m){return s+Number(m.entrada_cantidad||0);},0),sal=movs.reduce(function(s,m){return s+Number(m.salida_cantidad||0);},0),df=Number(actual.diferencia_stock||0);
          body+='<h2 style="margin:5px 0 2px">'+escapeHtml((p.codigo?p.codigo+' · ':'')+(p.producto||''))+'</h2><div class="muted" style="margin-bottom:10px">'+escapeHtml((p.categoria||'')+' / '+(p.subcategoria||'')+' · '+(p.almacen||''))+'</div><div class="kpis"><div class="kpi"><label>Saldo inicial</label><strong>'+numero(ini.cantidad,0)+' und.</strong></div><div class="kpi"><label>Entradas</label><strong>'+numero(ent,0)+' und.</strong></div><div class="kpi"><label>Salidas</label><strong>'+numero(sal,0)+' und.</strong></div><div class="kpi"><label>Saldo actual</label><strong>'+numero(actual.saldo_fisico,0)+' und.</strong></div><div class="kpi"><label>Valor actual</label><strong>'+moneda(actual.saldo_valorizado)+'</strong></div><div class="kpi"><label>Conciliación</label><strong>'+(Math.abs(df)<0.0001?'Conciliado':'Dif. '+(df>0?'+':'')+numero(df,0))+'</strong></div></div><div class="chart"> <h3>Evolución del saldo</h3><p>Entradas, salidas y saldo acumulado.</p>'+(imgs.kardex?'<img src="'+imgs.kardex+'">':'<div class="muted">Sin datos para graficar</div>')+'</div><h3 class="section-title page-break">Detalle de movimientos</h3>'+tablaHtmlKardex();
        }else{
          body+='<div class="kpis"><div class="kpi"><label>Valor inventario</label><strong>'+moneda(t.saldo_valorizado)+'</strong></div><div class="kpi"><label>Saldo actual</label><strong>'+numero(t.saldo_actual,0)+' und.</strong></div><div class="kpi"><label>Entradas</label><strong>'+numero(t.entradas,0)+' und.</strong></div><div class="kpi"><label>Salidas</label><strong>'+numero(t.salidas,0)+' und.</strong></div><div class="kpi"><label>Sin stock</label><strong>'+numero(alertas.sinStock,0)+'</strong></div><div class="kpi"><label>Conciliación</label><strong>'+numero(pct,1)+'%</strong></div></div><div class="charts"><div class="chart"><h3>Valor por categoría</h3><p>Saldo valorizado actual</p>'+(imgs.categorias?'<img src="'+imgs.categorias+'">':'<div class="muted">Sin datos</div>')+'</div><div class="chart"><h3>Entradas vs. salidas</h3><p>Movimiento diario del período</p>'+(imgs.movimientos?'<img src="'+imgs.movimientos+'">':'<div class="muted">Sin datos</div>')+'</div><div class="chart"><h3>Participación por subcategoría</h3><p>Composición del valor actual</p>'+(imgs.subcategorias?'<img src="'+imgs.subcategorias+'">':'<div class="muted">Sin datos</div>')+'</div><div class="chart"><h3>Top 10 productos por valor</h3><p>Mayor capital en stock</p>'+(imgs.topProductos?'<img src="'+imgs.topProductos+'">':'<div class="muted">Sin datos</div>')+'</div></div><h3 class="section-title page-break">Detalle por producto</h3>'+tablaHtmlProductos();
        }
        var footer='<div class="footer"><span>TiquePOS · '+escapeHtml(meta.empresa)+'</span><span>Reporte generado desde Inventario valorizado</span></div>';
        win.document.open();win.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Reporte de inventario</title><style>'+css+'</style></head><body>'+header+filtros+body+footer+'</body></html>');win.document.close();
        setTimeout(function(){win.focus();win.print();d.resolve();},650);
      }catch(e){console.error(e);try{win.close();}catch(ignore){}mostrarError('No fue posible preparar la impresión.');d.resolve();}
    });
    return d.promise();
  }

  function xmlEscape(v) {
    return String(v == null ? '' : v).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g,'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&apos;');
  }

  function excelCol(n) {
    var s=''; n++;
    while(n>0){var m=(n-1)%26;s=String.fromCharCode(65+m)+s;n=Math.floor((n-1)/26);}return s;
  }

  function xlsxCell(ref, value, style, numeric) {
    if (numeric) return '<c r="'+ref+'" s="'+(style||0)+'"><v>'+Number(value||0)+'</v></c>';
    return '<c r="'+ref+'" t="inlineStr" s="'+(style||0)+'"><is><t xml:space="preserve">'+xmlEscape(value)+'</t></is></c>';
  }

  function xlsxRow(r, cells, height) {
    return '<row r="'+r+'"'+(height?' ht="'+height+'" customHeight="1"':'')+'>'+cells.join('')+'</row>';
  }

  function xlsxStyles() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'+
    '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;S/ &quot;#,##0.00"/></numFmts><fonts count="7"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FF182033"/><name val="Calibri"/></font><font><sz val="9"/><color rgb="FF667085"/><name val="Calibri"/></font><font><b/><sz val="9"/><color rgb="FF667085"/><name val="Calibri"/></font><font><b/><sz val="14"/><color rgb="FF182033"/><name val="Calibri"/></font></fonts><fills count="9"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF00A46A"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF182033"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF8F2"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF7F9FC"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEEF2FF"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF4E6"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFEF0F0"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFE7ECF2"/></left><right style="thin"><color rgb="FFE7ECF2"/></right><top style="thin"><color rgb="FFE7ECF2"/></top><bottom style="thin"><color rgb="FFE7ECF2"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="14"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/><xf numFmtId="0" fontId="5" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="164" fontId="6" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="3" fontId="6" fillId="6" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="3" fontId="6" fillId="7" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="3" fontId="6" fillId="8" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="164" fontId="2" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
  }

  function sheetWrap(cols, rows, merges, freezeRow, autoFilter, drawing) {
    var colXml='<cols>'+cols.map(function(w,i){return '<col min="'+(i+1)+'" max="'+(i+1)+'" width="'+w+'" customWidth="1"/>';}).join('')+'</cols>';
    var views='<sheetViews><sheetView workbookViewId="0">'+(freezeRow?'<pane ySplit="'+freezeRow+'" topLeftCell="A'+(freezeRow+1)+'" activePane="bottomLeft" state="frozen"/>':'')+'</sheetView></sheetViews>';
    var mergeXml=merges&&merges.length?'<mergeCells count="'+merges.length+'">'+merges.map(function(m){return '<mergeCell ref="'+m+'"/>';}).join('')+'</mergeCells>':'';
    var filterXml=autoFilter?'<autoFilter ref="'+autoFilter+'"/>':'';
    var drawingXml=drawing?'<drawing r:id="rId1"/>':'';
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'+views+colXml+'<sheetData>'+rows.join('')+'</sheetData>'+filterXml+mergeXml+drawingXml+'</worksheet>';
  }

  function buildSummarySheet(meta, withDrawing) {
    var t=state.totales||{}, alerts=estadisticasAlertas(state.inventario), productos=Number(t.productos||state.inventario.length||0), diferencias=Number(t.diferencias||0), pct=productos?Math.max(0,(productos-diferencias)/productos*100):100;
    var rows=[];
    rows.push(xlsxRow(1,[xlsxCell('C1','REPORTE DE INVENTARIO VALORIZADO',1,false)],26));
    rows.push(xlsxRow(3,[xlsxCell('A3',meta.empresa,2,false),xlsxCell('F3','Emitido: '+fechaHoraEmision(),2,false)]));
    rows.push(xlsxRow(4,[xlsxCell('A4','Período: '+fechaHumana(meta.desde)+' al '+fechaHumana(meta.hasta),2,false),xlsxCell('F4','Usuario: '+meta.usuario,2,false)]));
    rows.push(xlsxRow(5,[xlsxCell('A5','Almacén: '+meta.almacen+' · Categoría: '+meta.categoria+' · Subcategoría: '+meta.subcategoria+' · Producto/SKU: '+meta.busqueda,2,false)]));
    rows.push(xlsxRow(7,[xlsxCell('A7','INDICADORES EJECUTIVOS',3,false)]));
    rows.push(xlsxRow(8,[xlsxCell('A8','Valor inventario',8,false),xlsxCell('C8','Saldo actual',8,false),xlsxCell('E8','Entradas',8,false),xlsxCell('G8','Salidas',8,false),xlsxCell('I8','Sin stock',8,false),xlsxCell('K8','Conciliación %',8,false)]));
    rows.push(xlsxRow(9,[xlsxCell('A9',Number(t.saldo_valorizado||0),9,true),xlsxCell('C9',Number(t.saldo_actual||0),10,true),xlsxCell('E9',Number(t.entradas||0),10,true),xlsxCell('G9',Number(t.salidas||0),11,true),xlsxCell('I9',alerts.sinStock,12,true),xlsxCell('K9',Number(pct.toFixed(1)),10,true)],25));
    rows.push(xlsxRow(11,[xlsxCell('A11','GRÁFICOS DEL INVENTARIO',3,false)]));
    rows.push(xlsxRow(49,[xlsxCell('A49','Alertas: '+alerts.negativos+' con stock negativo · '+alerts.diferencias+' diferencias FIFO · '+alerts.sinMovimiento+' sin movimiento en el período.',2,false)]));
    return sheetWrap([18,3,18,3,18,3,18,3,18,3,18,3],rows,['C1:L2','A5:L5','A7:L7','A8:B8','C8:D8','E8:F8','G8:H8','I8:J8','K8:L8','A9:B9','C9:D9','E9:F9','G9:H9','I9:J9','K9:L9','A11:L11','A49:L49'],0,null,!!withDrawing);
  }

  function buildProductsSheet(meta) {
    var rows=[], r=1;
    rows.push(xlsxRow(r++,[xlsxCell('A1','DETALLE DE INVENTARIO POR PRODUCTO',1,false)],26));r++;
    rows.push(xlsxRow(r++,[xlsxCell('A3','Período: '+fechaHumana(meta.desde)+' al '+fechaHumana(meta.hasta)+' · Almacén: '+meta.almacen+' · Categoría: '+meta.categoria+' · Subcategoría: '+meta.subcategoria,2,false)]));r++;
    var headerRow=r, headers=['Producto / SKU','Categoría','Subcategoría','Almacén','Saldo inicial','Entradas','Salidas','Saldo actual','Costo actual','Saldo valorizado','Conciliación'];
    rows.push(xlsxRow(r,headers.map(function(h,i){return xlsxCell(excelCol(i)+r,h,4,false);}),26));r++;
    var total={si:0,en:0,sa:0,ac:0,val:0};
    state.inventario.forEach(function(x){var dif=Number(x.diferencia_stock||0); total.si+=Number(x.saldo_inicial||0);total.en+=Number(x.entradas||0);total.sa+=Number(x.salidas||0);total.ac+=Number(x.saldo_actual||0);total.val+=Number(x.saldo_valorizado||0);var vals=[(x.codigo?x.codigo+' · ':'')+x.producto,x.categoria,x.subcategoria,x.almacen,Number(x.saldo_inicial||0),Number(x.entradas||0),Number(x.salidas||0),Number(x.saldo_actual||0),Number(x.costo_promedio_actual||0),Number(x.saldo_valorizado||0),Math.abs(dif)<0.0001?'Conciliado':'Diferencia '+(dif>0?'+':'')+numero(dif,0)];rows.push(xlsxRow(r,vals.map(function(v,i){var numeric=i>=4&&i<=9,style=numeric?(i>=8?7:6):5;return xlsxCell(excelCol(i)+r,v,style,numeric);}),20));r++;});
    rows.push(xlsxRow(r,[xlsxCell('A'+r,'TOTAL',3,false),xlsxCell('E'+r,total.si,6,true),xlsxCell('F'+r,total.en,6,true),xlsxCell('G'+r,total.sa,6,true),xlsxCell('H'+r,total.ac,6,true),xlsxCell('J'+r,total.val,13,true)],22));
    return sheetWrap([31,18,20,16,13,11,11,13,14,16,16],rows,['A1:K2','A3:K3','A'+r+':D'+r],headerRow, 'A'+headerRow+':K'+(r-1),false);
  }

  function buildCategoriesSheet(meta) {
    var data=resumenCategorias(state.inventario),rows=[],r=1;
    rows.push(xlsxRow(r++,[xlsxCell('A1','INVENTARIO POR CATEGORÍA Y SUBCATEGORÍA',1,false)],26));r++;
    rows.push(xlsxRow(r++,[xlsxCell('A3','Período: '+fechaHumana(meta.desde)+' al '+fechaHumana(meta.hasta),2,false)]));r++;
    var headerRow=r,headers=['Categoría','Subcategoría','Productos','Saldo inicial','Entradas','Salidas','Saldo actual','Saldo valorizado','Diferencias'];
    rows.push(xlsxRow(r,headers.map(function(h,i){return xlsxCell(excelCol(i)+r,h,4,false);}),26));r++;
    data.forEach(function(x){var vals=[x.categoria,x.subcategoria,x.productos,x.saldo_inicial,x.entradas,x.salidas,x.saldo_actual,x.saldo_valorizado,x.diferencias];rows.push(xlsxRow(r,vals.map(function(v,i){var numeric=i>=2,style=numeric?(i===7?7:6):5;return xlsxCell(excelCol(i)+r,v,style,numeric);}),20));r++;});
    return sheetWrap([24,24,12,14,12,12,14,17,12],rows,['A1:I2','A3:I3'],headerRow,'A'+headerRow+':I'+Math.max(headerRow,r-1),false);
  }

  function buildKardexSheet(meta, withDrawing) {
    if(!state.kardexData) return null;
    var d=state.kardexData,p=d.producto||{},rows=[],r=1;
    rows.push(xlsxRow(r++,[xlsxCell('A1','KARDEX VALORIZADO',1,false)],26));r++;
    rows.push(xlsxRow(r++,[xlsxCell('A3',(p.codigo?p.codigo+' · ':'')+(p.producto||''),2,false)]));
    rows.push(xlsxRow(r++,[xlsxCell('A4',(p.categoria||'')+' / '+(p.subcategoria||'')+' · '+(p.almacen||''),2,false)]));
    rows.push(xlsxRow(r++,[xlsxCell('A5','Período: '+fechaHumana(meta.desde)+' al '+fechaHumana(meta.hasta),2,false)]));r++;
    var headerRow=r,headers=['Fecha','Documento / Detalle','Entrada cant.','Entrada costo','Entrada total','Salida cant.','Salida costo','Salida total','Saldo cant.','Saldo costo','Saldo total'];
    rows.push(xlsxRow(r,headers.map(function(h,i){return xlsxCell(excelCol(i)+r,h,4,false);}),30));r++;
    (d.movimientos||[]).forEach(function(m){var vals=[fechaHumana(m.fecha),m.detalle,Number(m.entrada_cantidad||0),Number(m.entrada_costo||0),Number(m.entrada_total||0),Number(m.salida_cantidad||0),Number(m.salida_costo||0),Number(m.salida_total||0),Number(m.saldo_cantidad||0),Number(m.saldo_costo||0),Number(m.saldo_total||0)];rows.push(xlsxRow(r,vals.map(function(v,i){var numeric=i>=2,style=numeric?((i===3||i===4||i===6||i===7||i===9||i===10)?7:6):5;return xlsxCell(excelCol(i)+r,v,style,numeric);}),20));r++;});
    return sheetWrap([13,38,12,14,15,12,14,15,12,14,15],rows,['A1:K2','A3:K3','A4:K4','A5:K5'],headerRow,'A'+headerRow+':K'+Math.max(headerRow,r-1),!!withDrawing);
  }

  function drawingXml(images) {
    var anchors=images.map(function(img,i){var p=img.pos;return '<xdr:twoCellAnchor><xdr:from><xdr:col>'+p.c1+'</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>'+p.r1+'</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:to><xdr:col>'+p.c2+'</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>'+p.r2+'</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="'+(i+1)+'" name="'+xmlEscape(img.name)+'"/><xdr:cNvPicPr/></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId'+(i+1)+'"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:twoCellAnchor>';}).join('');
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'+anchors+'</xdr:wsDr>';
  }

  function drawingRelsXml(images, startMediaIndex) {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'+images.map(function(_,i){return '<Relationship Id="rId'+(i+1)+'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image'+(startMediaIndex+i)+'.png"/>';}).join('')+'</Relationships>';
  }

  function descargarBlob(blob, filename) {
    var url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=filename;document.body.appendChild(a);a.click();document.body.removeChild(a);setTimeout(function(){URL.revokeObjectURL(url);},1200);
  }

  function exportarExcelProfesional() {
    var d=$.Deferred();
    if(typeof JSZip==='undefined'){mostrarError('El componente de Excel no está disponible.');d.resolve();return d.promise();}
    var meta=datosReporte(),imgs=imagenesReporte();
    cargarImagenDataUrl(meta.logoUrl).always(function(logo){
      try{
        var zip=new JSZip();
        var summaryImages=[]; if(logo) summaryImages.push({name:'Logo',data:logo,pos:{c1:0,r1:0,c2:1,r2:2}}); if(imgs.categorias) summaryImages.push({name:'Valor por categoria',data:imgs.categorias,pos:{c1:0,r1:11,c2:5,r2:27}}); if(imgs.movimientos) summaryImages.push({name:'Entradas y salidas',data:imgs.movimientos,pos:{c1:6,r1:11,c2:11,r2:27}}); if(imgs.subcategorias) summaryImages.push({name:'Subcategorias',data:imgs.subcategorias,pos:{c1:0,r1:29,c2:5,r2:46}}); if(imgs.topProductos) summaryImages.push({name:'Top productos',data:imgs.topProductos,pos:{c1:6,r1:29,c2:11,r2:46}});
        var kardexImages=[]; if(state.kardexData&&imgs.kardex) kardexImages.push({name:'Evolucion Kardex',data:imgs.kardex,pos:{c1:0,r1:7,c2:10,r2:22}});
        var sheets=[{name:'Resumen',xml:buildSummarySheet(meta,summaryImages.length>0),drawing:summaryImages.length>0},{name:'Productos',xml:buildProductsSheet(meta),drawing:false},{name:'Categorias',xml:buildCategoriesSheet(meta),drawing:false}];
        var kardexXml=buildKardexSheet(meta,kardexImages.length>0); if(kardexXml) sheets.push({name:'Kardex',xml:kardexXml,drawing:kardexImages.length>0});

        var overrides=sheets.map(function(_,i){return '<Override PartName="/xl/worksheets/sheet'+(i+1)+'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';}).join('');
        var drawingOverrides='';if(summaryImages.length)drawingOverrides+='<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';if(kardexImages.length)drawingOverrides+='<Override PartName="/xl/drawings/drawing2.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
        zip.file('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'+overrides+drawingOverrides+'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>');
        zip.folder('_rels').file('.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>');
        var wbSheets=sheets.map(function(s,i){return '<sheet name="'+xmlEscape(s.name)+'" sheetId="'+(i+1)+'" r:id="rId'+(i+1)+'"/>';}).join('');
        zip.folder('xl').file('workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'+wbSheets+'</sheets><calcPr calcId="0"/></workbook>').file('styles.xml',xlsxStyles());
        var wbRels=sheets.map(function(_,i){return '<Relationship Id="rId'+(i+1)+'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'+(i+1)+'.xml"/>';}).join('')+'<Relationship Id="rId'+(sheets.length+1)+'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        zip.folder('xl').folder('_rels').file('workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'+wbRels+'</Relationships>');
        var wsFolder=zip.folder('xl').folder('worksheets'), wsRels=wsFolder.folder('_rels');
        sheets.forEach(function(s,i){wsFolder.file('sheet'+(i+1)+'.xml',s.xml);});
        var media=zip.folder('xl').folder('media'),draw=zip.folder('xl').folder('drawings'),drawRels=draw.folder('_rels'),mediaIndex=1;
        if(summaryImages.length){wsRels.file('sheet1.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>');draw.file('drawing1.xml',drawingXml(summaryImages));drawRels.file('drawing1.xml.rels',drawingRelsXml(summaryImages,mediaIndex));summaryImages.forEach(function(im){media.file('image'+(mediaIndex++)+'.png',im.data.split(',')[1],{base64:true});});}
        if(kardexImages.length){var kSheetIndex=sheets.length;wsRels.file('sheet'+kSheetIndex+'.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing2.xml"/></Relationships>');draw.file('drawing2.xml',drawingXml(kardexImages));drawRels.file('drawing2.xml.rels',drawingRelsXml(kardexImages,mediaIndex));kardexImages.forEach(function(im){media.file('image'+(mediaIndex++)+'.png',im.data.split(',')[1],{base64:true});});}
        var now=new Date().toISOString();zip.folder('docProps').file('core.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Inventario valorizado</dc:title><dc:creator>'+xmlEscape(meta.usuario)+'</dc:creator><cp:lastModifiedBy>'+xmlEscape(meta.usuario)+'</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">'+now+'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'+now+'</dcterms:modified></cp:coreProperties>').file('app.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>TiquePOS</Application><Company>'+xmlEscape(meta.empresa)+'</Company></Properties>');
        var blob=zip.generate({type:'blob',compression:'DEFLATE'});descargarBlob(blob,nombreArchivo('inventario_valorizado'));d.resolve();
      }catch(e){console.error(e);mostrarError('No fue posible generar el Excel profesional.');d.resolve();}
    });
    return d.promise();
  }

  function ejecutarExportacion($btn, texto, accion) {
    if(!$btn || !$btn.length) return;
    var html=$btn.html();
    $('.inv-actions .btn').prop('disabled',true);
    $btn.html('<i class="fas fa-spinner fa-spin mr-1"></i>'+texto);
    var p;
    try{p=accion();}catch(e){console.error(e);mostrarError('No fue posible generar el reporte.');}
    $.when(p).always(function(){setTimeout(function(){$('.inv-actions .btn').prop('disabled',false);$btn.html(html);},250);});
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
  $('#btnExportExcel').on('click',function(){ ejecutarExportacion($(this),'Generando...',exportarExcelProfesional); });
  $('#btnExportPdf').on('click',function(){ ejecutarExportacion($(this),'Generando...',exportarPdfProfesional); });
  $('#btnPrintInventario').on('click',function(){ ejecutarExportacion($(this),'Preparando...',imprimirProfesional); });
  $('a[data-toggle="tab"]').on('shown.bs.tab', function(){ Object.keys(state.charts).forEach(function(k){ if(state.charts[k] && state.charts[k].resize) state.charts[k].resize(); }); });

  cargarCatalogos().always(function(){ cargarInventario(); });
})(jQuery);
