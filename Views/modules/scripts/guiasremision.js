/* TiquePOS · GRE Remitente · vista MVC (borradores locales, sin emisión tributaria) */
(() => {
  'use strict';
  const C = window.GRE_CONFIG || {};
  const API = 'Controllers/GuiaRemision.php?op=';
  const $id = x => document.getElementById(x);
  const val = x => ($id(x)?.value || '').trim();
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const opt = (v, t, selected='') => `<option value="${esc(v)}" ${String(v)===String(selected)?'selected':''}>${esc(t)}</option>`;
  const modal = name => window.jQuery('#' + name).modal('show');
  const close = name => window.jQuery('#' + name).modal('hide');
  const state = {cat:null, rows:[], lineas:[], idguia:0, idventa:0, uid:0, bloqueado:false};
  function toast(m,t='info') { if(window.Swal) return Swal.fire({icon:t,text:m,confirmButtonColor:'#00a46a'}); alert(m); }
  async function request(op, params={}, data=null) {
    const q = new URLSearchParams(params);
    const url = API + encodeURIComponent(op) + (q.size ? '&'+q : '');
    const init={credentials:'same-origin',headers:{'Accept':'application/json'}};
    if(data !== null){ init.method='POST';init.headers['X-CSRF-Token']=C.csrf;
      if(data instanceof FormData){init.body=data;}else{init.headers['Content-Type']='application/json';init.body=JSON.stringify(data);} }
    const response=await fetch(url,init);
    let body; try { body=await response.json(); } catch(_){throw new Error('Respuesta inesperada del servidor. Verifique la sesión.');}
    if(!body.success) throw new Error(body.message || 'Operación no completada.');
    return body.data;
  }
  async function postAction(op,id) {const f=new FormData();f.set('idguia',id);return request(op,{},f);}
  function fill(id,items,getValue,getName,placeholder='Seleccione...') {
    const el=$id(id);el.innerHTML=opt('',placeholder);
    (items||[]).forEach(i=>el.insertAdjacentHTML('beforeend',opt(getValue(i),getName(i))));
  }
  function field(n,x='') { $id(n).value=x ?? ''; }
  function buscarProducto(id) {return (state.cat?.productos||[]).find(p=>+p.idarticulo===+id);}
  function variantes(id) {return (state.cat?.variantes||[]).filter(v=>+v.idarticulo===+id);}
  function fmtDate(d) {if(!d)return'—'; const v=String(d).substring(0,10).split('-');return v.length===3?`${v[2]}/${v[1]}/${v[0]}`:d;}
  function estado(st) {return `<span class="gre-state ${esc(st)}">${esc(st)}</span>`;}
  function showError(e) {toast(e.message||String(e),'error');}
  function renderList() {
    const rows=state.rows;
    $id('greTotal').textContent=rows.length;
    $id('greBorradores').textContent=rows.filter(r=>r.estado==='BORRADOR').length;
    $id('grePreparadas').textContent=rows.filter(r=>r.estado==='PREPARADA').length;
    $id('greAceptadas').textContent=rows.filter(r=>r.estado==='ACEPTADA').length;
    $id('greFilas').innerHTML=rows.length?rows.map(r=>{
      const number=r.serie&&r.correlativo?r.serie+'-'+String(r.correlativo).padStart(8,'0'):'Sin numeración';
      return `<tr><td><span class="gre-doc">${esc(number)}</span><div class="gre-muted">#${r.idguia} · ${esc(r.sucursal)}</div></td>
      <td>${esc(fmtDate(r.fecha_inicio_traslado))}</td>
      <td><div class="gre-doc">${esc(r.destinatario_nombre)}</div><span class="gre-muted">${esc(r.destinatario_num_documento)}</span></td>
      <td>${esc(r.motivo_descripcion)}</td><td>${estado(r.estado)}</td>
      <td class="text-right"><button class="gre-list-btn" type="button" data-ver="${r.idguia}"><i class="far fa-eye mr-1"></i> Detalle</button>
      ${r.estado==='BORRADOR'?`<button class="gre-list-btn" type="button" data-editar="${r.idguia}"><i class="fas fa-pen"></i></button>`:''}</td></tr>`;
    }).join(''):`<tr><td colspan="6" class="gre-empty">No se encontraron guías. Puede crear un borrador desde «Nueva guía».</td></tr>`;
  }
  async function listar() {
    try {state.rows=await request('listar',{estado:val('greEstadoFiltro'),buscar:val('greBuscar')});renderList();}
    catch(e){$id('greFilas').innerHTML=`<tr><td class="gre-empty" colspan="6">${esc(e.message)}</td></tr>`;}
  }
  function construirCatalogos() {
    const cat=state.cat;
    fill('greSerie',cat.series,x=>x.idserie,x=>x.serie+' · '+(cat.sucursales.find(s=>s.idsucursal===x.idsucursal)?.nombre||''));
    fill('greAlmacen',cat.almacenes,x=>x.idalmacen,x=>x.nombre);
    fill('greCliente',cat.clientes,x=>x.idpersona,x=>x.nombre+' · '+x.num_documento,'Cliente nuevo o no registrado');
    fill('greProducto',cat.productos,x=>x.idarticulo,x=>x.nombre+(x.codigo?' · '+x.codigo:''));
    fill('greMotivo',Object.entries(cat.motivos).map(([codigo,nombre])=>({codigo,nombre})),x=>x.codigo,x=>x.nombre);
    $id('grePlacas').innerHTML=cat.vehiculos.map(x=>`<option value="${esc(x.placa)}"></option>`).join('');
    $id('greConductores').innerHTML=cat.conductores.map(x=>`<option value="${esc(x.nombre)}"></option>`).join('');
    $id('greTransportistas').innerHTML=cat.transportistas.map(x=>`<option value="${esc(x.razon_social)}"></option>`).join('');
  }
  function sucursalSerie(){return state.cat?.series.find(s=>+s.idserie===+val('greSerie'))}
  function filtrarProductos() {
    const almacen=+val('greAlmacen');
    fill('greProducto',state.cat.productos.filter(p=>+p.idalmacen===almacen),p=>p.idarticulo,p=>p.nombre+(p.codigo?' · '+p.codigo:''),'Seleccione un producto del almacén');
  }
  function updateSucursal() {
    const suc=+sucursalSerie()?.idsucursal;
    const opciones=state.cat.almacenes.filter(a=>+a.idsucursal===suc);
    fill('greAlmacen',opciones,x=>x.idalmacen,x=>x.nombre,'Seleccione almacén');
    const loc=state.cat.sucursales.find(s=>+s.idsucursal===suc);
    if(loc) field('grePartida',loc.direccion);
    if(opciones.length===1) field('greAlmacen',opciones[0].idalmacen);
    filtrarProductos();
    // Los ubigeos no figuran en la tabla sucursal. Deben completarse manualmente.
  }
  function onchangeCliente() {
    if(state.idventa) return;
    const c=state.cat.clientes.find(x=>+x.idpersona===+val('greCliente'));
    if(c){field('greDocTipo',c.tipo_documento==='6'?'RUC':c.tipo_documento==='1'?'DNI':c.tipo_documento);
      field('greDocNumero',c.num_documento);field('greDestinoNombre',c.nombre);
      field('greDestinoDireccion',c.direccion||'');field('greLlegada',c.direccion||'');field('greEmail',c.email||'');}
  }
  function modo() {const pub=val('greModalidad')==='01';$id('greTransportePrivado').hidden=pub;$id('greTransportePublico').hidden=!pub;}
  function addLine(p) {
    const product=buscarProducto(p.idarticulo);
    if(!product) {toast('Producto no disponible','warning');return;}
    const lin={uid:++state.uid,idarticulo:+p.idarticulo,idvariacion:+(p.idvariacion||0),iddetalle_venta:+(p.iddetalle_venta||0),
      cantidad:p.cantidad??1,lotes:(p.lotes||[]).map(l=>({...l})),bloqueada:!!p.bloqueada};
    state.lineas.push(lin);renderLines();
  }
  function variantHtml(line) {
    const vs=variantes(line.idarticulo);
    if(!vs.length) return '<span class="gre-muted">Producto simple</span>';
    return `<select class="form-control" data-variante="${line.uid}" ${line.bloqueada?'disabled':''}>${opt('0','Seleccionar presentación',line.idvariacion)}${vs.map(v=>opt(v.idvariacion,v.combinacion+' · '+(v.sku||''),line.idvariacion)).join('')}</select>`;
  }
  function renderLines() {
    $id('greItemsCount').textContent=state.lineas.length+' productos';
    $id('greLineas').innerHTML=state.lineas.length?state.lineas.map(l=>{
      const p=buscarProducto(l.idarticulo);
      const vs=variantes(l.idarticulo);
      const name=esc(p?.nombre||'Producto no disponible');
      const codigo=esc(p?.codigo||'');
      return `<div class="gre-line" data-linea="${l.uid}">
         <div class="gre-line-top"><div><div class="gre-line-name">${name}</div><div class="gre-line-meta">${codigo} ${l.iddetalle_venta?' · Venta #'+l.iddetalle_venta:''}</div></div>
         <button type="button" class="btn-remove" data-quitar="${l.uid}" title="Quitar"><i class="fas fa-times"></i></button></div>
         <div class="gre-line-fields"><div class="form-group"><label>Variante</label>${variantHtml(l)}</div>
           <div class="form-group"><label>Cantidad *</label><input class="form-control" data-cantidad="${l.uid}" type="number" step="0.001" min="0.001" value="${esc(l.cantidad)}"></div></div>
         <div class="gre-lotes" data-lotes="${l.uid}">${(l.lotes||[]).map((lt,k)=>loteRow(l,lt,k)).join('')}</div>
         <button type="button" class="gre-lot-add" data-addlote="${l.uid}"><i class="fas fa-plus-circle mr-1"></i>Agregar lote ${l.bloqueada?'de la venta':'(si corresponde)'}</button>
       </div>`;
    }).join(''):'<div class="gre-lines-empty">Agregue los productos que serán trasladados.</div>';
  }
  function loteRow(l,lt,k) {
    return `<div class="gre-lot-line"><div class="form-group"><label>Lote ${k+1}</label><select class="form-control" data-loteid="${l.uid}:${k}"><option value="${esc(lt.iddetalle_ingreso||'')}">${esc(lt.numero_lote||('Lote #'+lt.iddetalle_ingreso))} ${esc(lt.fecha_vencimiento||'')}</option></select></div>
      <div class="form-group gre-lot-qty"><label>Cantidad</label><input type="number" class="form-control" step="0.001" min="0.001" data-lotecant="${l.uid}:${k}" value="${esc(lt.cantidad)}"></div>
      <button type="button" title="Quitar lote" data-quitar-lote="${l.uid}:${k}"><i class="fas fa-times"></i></button></div>`;
  }
  async function agregarLote(line) {
    const p=buscarProducto(line.idarticulo);
    if(!p) return;
    let lotes=[];
    if(state.idventa) {
      const datos=await request('venta',{idventa:state.idventa});
      const v=datos.items.find(x=>+x.iddetalle_venta===+line.iddetalle_venta);
      lotes=v?.lotes||[];
    } else {
      if(!val('greAlmacen')) throw new Error('Seleccione el almacén antes de elegir lotes.');
      lotes=await request('lotes',{idarticulo:line.idarticulo,idvariacion:line.idvariacion,idalmacen:val('greAlmacen')});
    }
    const disponibles=lotes.filter(x=>!line.lotes.some(a=>+a.iddetalle_ingreso===+x.iddetalle_ingreso));
    if(!disponibles.length){toast('No hay lotes adicionales disponibles para esta línea.','warning');return;}
    if(!window.Swal) throw new Error('No se pudo abrir el selector de lotes.');
    const options={};
    for(const x of disponibles){
      const libre=Math.max(0,Number(x.cantidad||x.disponible)-Number(x.reservado||0));
      options[String(x.iddetalle_ingreso)]=`${x.numero_lote||'Sin número'} · vence ${fmtDate(x.fecha_vencimiento)} · disponible ${libre}`;
    }
    const answer=await Swal.fire({title:'Seleccionar lote',text:'Se guardará el lote y su fecha de vencimiento en la guía.',input:'select',inputOptions:options,inputPlaceholder:'Elija el lote',showCancelButton:true,confirmButtonText:'Agregar',cancelButtonText:'Cancelar',confirmButtonColor:'#00a46a'});
    if(!answer.isConfirmed || !answer.value) return;
    const found=disponibles.find(x=>+x.iddetalle_ingreso===+answer.value);
    if(!found){toast('Seleccione un lote válido.','warning');return;}
    const remaining=Number(found.cantidad??found.disponible)-Number(found.reservado||0);
    if(remaining<=0){toast('Este lote no tiene saldo disponible.','warning');return;}
    line.lotes.push({iddetalle_ingreso:found.iddetalle_ingreso,numero_lote:found.numero_lote,fecha_vencimiento:found.fecha_vencimiento,cantidad:Math.min(remaining,Number(line.cantidad)||remaining)});
    renderLines();
  }
  function resetForm() {
    state.idguia=0;state.idventa=0;state.uid=0;state.lineas=[];
    $id('greFormulario').querySelectorAll('input:not([type=hidden]),textarea').forEach(el=>el.value='');
    field('greId','');field('greFecha',new Date().toLocaleDateString('sv-SE',{timeZone:'America/Lima'}));
    ['greSerie','greAlmacen','greCliente','greMotivo'].forEach(id=>field(id,''));
    field('greModalidad','02');field('greDocTipo','RUC');field('greCondTipo','DNI');
    $id('greOrigenVenta').hidden=true;
    $id('greProductoControls').hidden=false;
    $id('greModalTitulo').textContent='Nueva guía';
    $id('greModalSubtitulo').textContent='El registro se guardará como borrador, sin numeración tributaria.';
    modo();renderLines();
  }
  function crear() {if(!state.cat)return toast('Se están cargando los catálogos.','info');resetForm();if(state.cat.series.length===1){field('greSerie',state.cat.series[0].idserie);updateSucursal();}modal('greModal');}
  async function precargarVenta(idventa) {
    const data=await request('venta',{idventa});
    const v=data.venta;
    const serie=state.cat.series.find(s=>+s.idsucursal===+v.idsucursal);
    if(!serie) throw new Error('No tiene ninguna serie GRE disponible para la sucursal de la venta.');
    crear(); state.idventa=+v.idventa;
    field('greSerie',serie.idserie);updateSucursal();
    const first=data.items.find(x=>+x.disponible>0);
    if(first){const p=buscarProducto(first.idarticulo);if(p){field('greAlmacen',p.idalmacen);filtrarProductos();}}
    field('greCliente',v.idcliente);
    field('greDocTipo',v.destinatario_tipo_documento||'RUC');field('greDocNumero',v.destinatario_num_documento);
    field('greDestinoNombre',v.destinatario_nombre);field('greDestinoDireccion',v.direccion_cliente||v.direccion_registrada);
    field('greLlegada',v.direccion_cliente||v.direccion_registrada);field('greEmail',v.destinatario_email||'');
    field('greMotivo','01');
    $id('greOrigenVenta').hidden=false;
    $id('greOrigenVenta').textContent=`Venta vinculada: ${v.tipo_comprobante} ${v.serie_comprobante}-${v.num_comprobante} · Las cantidades corresponden al saldo aún no asignado a guías.`;
    $id('greProductoControls').hidden=true;
    $id('greModalTitulo').textContent='Guía desde venta';
    for(const item of data.items){
      if(+item.disponible<=0)continue;
      const qty=Number(item.disponible);
      let falta=qty;const alloc=[];
      for(const lote of item.lotes||[]){
        const disp=Math.max(0,Number(lote.cantidad)-Number(lote.reservado));
        if(disp<=0 || falta<=0)continue;
        const n=Math.min(disp,falta);
        alloc.push({iddetalle_ingreso:lote.iddetalle_ingreso,numero_lote:lote.numero_lote,fecha_vencimiento:lote.fecha_vencimiento,cantidad:n});falta-=n;
      }
      addLine({idarticulo:item.idarticulo,idvariacion:item.idvariacion,iddetalle_venta:item.iddetalle_venta,cantidad:qty,lotes:alloc,bloqueada:true});
    }
    if(!state.lineas.length)toast('Esta venta no tiene cantidades disponibles para otra guía.','warning');
  }
  async function editar(id) {
    const data=await request('ver',{idguia:id});const g=data.guia;
    if(g.estado!=='BORRADOR')return toast('Solo puede editar un borrador.','warning');
    resetForm();state.idguia=+g.idguia;state.idventa=+g.idventa||0;
    field('greId',g.idguia);field('greSerie',g.idserie);updateSucursal();field('greAlmacen',g.idalmacen);filtrarProductos();
    ['fecha_inicio_traslado','motivo_traslado','modalidad_transporte','destinatario_idpersona'].forEach((n,i)=>field(['greFecha','greMotivo','greModalidad','greCliente'][i],g[n]));
    const names={greDocTipo:'destinatario_tipo_documento',greDocNumero:'destinatario_num_documento',greDestinoNombre:'destinatario_nombre',greDestinoDireccion:'destinatario_direccion',greEmail:'destinatario_email',grePartidaUbigeo:'partida_ubigeo',grePartida:'partida_direccion',greLlegadaUbigeo:'llegada_ubigeo',greLlegada:'llegada_direccion',greTranspRuc:'transportista_ruc',greTranspNombre:'transportista_nombre',grePlaca:'vehiculo_placa',greCondTipo:'conductor_tipo_documento',greCondNumero:'conductor_num_documento',greCondNombre:'conductor_nombre',greLicencia:'conductor_licencia',grePeso:'peso_bruto',greBultos:'numero_bultos',greObservacion:'observacion'};
    Object.entries(names).forEach(([f,n])=>field(f,g[n]));modo();
    state.lineas=data.items.map(x=>({uid:++state.uid,idarticulo:+x.idarticulo,idvariacion:+x.idvariacion||0,iddetalle_venta:+x.iddetalle_venta||0,cantidad:x.cantidad,lotes:x.lotes||[],bloqueada:state.idventa>0}));
    if(state.idventa){$id('greProductoControls').hidden=true;$id('greOrigenVenta').hidden=false;$id('greOrigenVenta').textContent='Borrador vinculado a venta #'+state.idventa+' · No es posible desvincularlo.';}
    $id('greModalTitulo').textContent='Editar guía #'+id;renderLines();modal('greModal');
  }
  function payload() {
    const map={idguia:state.idguia,idventa:state.idventa,idserie:val('greSerie'),idalmacen:val('greAlmacen'),fecha_inicio_traslado:val('greFecha'),motivo_traslado:val('greMotivo'),modalidad_transporte:val('greModalidad'),
      destinatario_idpersona:val('greCliente'),destinatario_tipo_documento:val('greDocTipo'),destinatario_num_documento:val('greDocNumero'),destinatario_nombre:val('greDestinoNombre'),destinatario_direccion:val('greDestinoDireccion'),destinatario_email:val('greEmail'),partida_ubigeo:val('grePartidaUbigeo'),partida_direccion:val('grePartida'),llegada_ubigeo:val('greLlegadaUbigeo'),llegada_direccion:val('greLlegada'),transportista_ruc:val('greTranspRuc'),transportista_nombre:val('greTranspNombre'),vehiculo_placa:val('grePlaca'),conductor_tipo_documento:val('greCondTipo'),conductor_num_documento:val('greCondNumero'),conductor_nombre:val('greCondNombre'),conductor_licencia:val('greLicencia'),peso_bruto:val('grePeso'),numero_bultos:val('greBultos'),observacion:val('greObservacion'),
      items:state.lineas.map(l=>({idarticulo:l.idarticulo,idvariacion:l.idvariacion,iddetalle_venta:l.iddetalle_venta,cantidad:l.cantidad,lotes:l.lotes.map(o=>({iddetalle_ingreso:o.iddetalle_ingreso,cantidad:o.cantidad}))}))};
    return map;
  }
  async function guardar(){if(state.bloqueado)return;state.bloqueado=true;const b=$id('greGuardar');b.disabled=true;
    try{ const result=await request('guardar',{},payload());close('greModal');await listar();await detalle(result.idguia);toast('Borrador guardado. No se ha emitido a SUNAT.','success'); }
    catch(e){showError(e);} finally{b.disabled=false;state.bloqueado=false;}
  }
  async function detalle(id){
    const x=await request('ver',{idguia:id});const g=x.guia;
    $id('greDetalleTitulo').textContent=(g.serie?g.serie+'-'+String(g.correlativo).padStart(8,'0'):'Borrador #'+g.idguia);
    const tiles=[['Estado',g.estado],['Sucursal',g.sucursal],['Fecha de inicio',fmtDate(g.fecha_inicio_traslado)],['Motivo',g.motivo_descripcion],['Destinatario',g.destinatario_nombre+' · '+g.destinatario_num_documento],['Traslado',g.modalidad_transporte==='01'?'Transporte público':'Transporte privado'],['Origen',g.partida_ubigeo+' · '+g.partida_direccion],['Destino',g.llegada_ubigeo+' · '+g.llegada_direccion],['Peso bruto',g.peso_bruto+' kg'],['Transporte',g.modalidad_transporte==='01'?(g.transportista_nombre||''):(g.vehiculo_placa+' · '+g.conductor_nombre)]];
    $id('greDetalleCuerpo').innerHTML=`<div class="gre-warning"><i class="fas fa-info-circle mr-2"></i>Este documento no sustenta un traslado mientras no haya recibido CDR aceptada de SUNAT.</div><div class="gre-detail-cols">${tiles.map(t=>`<div class="gre-detail-tile"><span>${esc(t[0])}</span><strong>${esc(t[1]||'—')}</strong></div>`).join('')}</div>
      <div class="gre-detail-label">Detalle de mercadería</div><div class="table-responsive"><table class="table gre-detail-table"><thead><tr><th>Producto</th><th>Cantidad</th><th>Lotes</th></tr></thead><tbody>${x.items.map(i=>`<tr><td>${esc(i.descripcion)}<div class="gre-muted">${esc(i.codigo)}</div></td><td>${esc(i.cantidad)} ${esc(i.unidad_sunat)}</td><td>${i.lotes.map(l=>`${esc(l.numero_lote||'Lote sin código')} (${esc(l.cantidad)})`).join('<br>')||'—'}</td></tr>`).join('')}</tbody></table></div>
      ${x.documentos.length?`<p class="gre-muted">Referencia: ${x.documentos.map(d=>esc(d.serie+'-'+d.numero)).join(', ')}</p>`:''}
      <p class="gre-muted">Estado de integración SUNAT: ${esc(g.estado_sunat||'NO_ENVIADO')}.</p>`;
    const actions=[];
    if(g.estado==='BORRADOR') {actions.push(`<button class="btn btn-outline-secondary btn-sm" data-det-editar="${id}">Editar</button>`);actions.push(`<button class="btn gre-btn-primary btn-sm" data-det-action="preparar:${id}">Marcar preparada</button>`);actions.push(`<button class="btn btn-outline-danger btn-sm" data-det-action="descartar:${id}">Descartar</button>`);}
    if(g.estado==='PREPARADA') {actions.push(`<button class="btn btn-outline-secondary btn-sm" data-det-action="volverBorrador:${id}">Volver a borrador</button>`);}
    actions.push(`<a href="Reports/guiaremision_a4.php?id=${id}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="far fa-file-pdf mr-1"></i> Vista PDF no válida</a>`);
    actions.push('<button class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cerrar</button>');
    $id('greDetalleAcciones').innerHTML=actions.join(' ');modal('greDetalleModal');
  }
  async function action(op,id) {
    const msg=op==='descartar'?'¿Descartar esta guía? Se liberarán sus cantidades reservadas.':op==='preparar'?'¿Marcar la guía como preparada? Esto NO la envía a SUNAT.':'¿Volver a borrador para editar?';
    const yes=window.Swal?(await Swal.fire({title:'Confirmar acción',text:msg,icon:'question',showCancelButton:true,confirmButtonColor:'#00a46a'})).isConfirmed:confirm(msg);
    if(!yes)return;
    try{await postAction(op,id);close('greDetalleModal');await listar();await detalle(id);}
    catch(e){showError(e);}
  }
  function bind() {
    $id('greNueva').addEventListener('click',crear);$id('greGuardar').addEventListener('click',guardar);
    $id('greActualizar').addEventListener('click',listar);
    $id('greEstadoFiltro').addEventListener('change',listar);
    let timer;$id('greBuscar').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(listar,300);});
    $id('greCliente').addEventListener('change',onchangeCliente);$id('greSerie').addEventListener('change',()=>{if(state.idventa)return;updateSucursal();});
    $id('greModalidad').addEventListener('change',modo);
    $id('greAlmacen').addEventListener('change',filtrarProductos);
    ['greDocNumero','greDocTipo'].forEach(id=>$id(id).addEventListener('input',()=>{
      if(!state.idventa){
        const c=state.cat?.clientes.find(x=>+x.idpersona===+val('greCliente'));
        if(c && String(c.num_documento)!==val('greDocNumero'))field('greCliente','');
      }
    }));
    $id('greAgregarProducto').addEventListener('click',()=>{
      if(state.idventa)return;
      const id=+val('greProducto'); if(!id)return toast('Seleccione un producto.','warning');
      addLine({idarticulo:id,cantidad:1});field('greProducto','');
    });
    ['grePlaca','greCondNombre','greTranspNombre'].forEach(id=>{
      $id(id).addEventListener('change',()=>{
        if(id==='greCondNombre') {const x=state.cat.conductores.find(c=>c.nombre===val(id));if(x){field('greCondNumero',x.num_documento);field('greCondTipo',x.tipo_documento);field('greLicencia',x.licencia);}}
        if(id==='greTranspNombre'){const x=state.cat.transportistas.find(c=>c.razon_social===val(id));if(x)field('greTranspRuc',x.ruc);}
      });
    });
    $id('greFilas').addEventListener('click',e=>{const b=e.target.closest('[data-ver],[data-editar]');if(!b)return;const k=b.dataset.ver||b.dataset.editar;(b.dataset.editar?editar(k):detalle(k)).catch(showError);});
    $id('greDetalleAcciones').addEventListener('click',e=>{const b=e.target.closest('[data-det-editar],[data-det-action]');if(!b)return;
      if(b.dataset.detEditar){close('greDetalleModal');editar(b.dataset.detEditar).catch(showError);return;}
      const [op,id]=b.dataset.detAction.split(':');action(op,+id).catch(showError);
    });
    $id('greLineas').addEventListener('change',e=>{
      const b=e.target;
      if(b.matches('[data-cantidad]')){const l=state.lineas.find(x=>x.uid===+b.dataset.cantidad);if(l)l.cantidad=b.value;}
      if(b.matches('[data-variante]')){const l=state.lineas.find(x=>x.uid===+b.dataset.variante);if(l){l.idvariacion=+b.value;l.lotes=[];renderLines();}}
      if(b.matches('[data-lotecant]')){const [id,index]=b.dataset.lotecant.split(':').map(Number);const l=state.lineas.find(x=>x.uid===id);if(l?.lotes[index])l.lotes[index].cantidad=b.value;}
    });
    $id('greLineas').addEventListener('click',e=>{
      const b=e.target.closest('[data-quitar],[data-addlote],[data-quitar-lote]');if(!b)return;
      if(b.dataset.quitar){state.lineas=state.lineas.filter(l=>l.uid!==+b.dataset.quitar);renderLines();return;}
      if(b.dataset.addlote){const l=state.lineas.find(x=>x.uid===+b.dataset.addlote);if(l)agregarLote(l).catch(showError);return;}
      if(b.dataset.quitarLote){const [id,index]=b.dataset.quitarLote.split(':').map(Number);const l=state.lineas.find(x=>x.uid===id);if(l){l.lotes.splice(index,1);renderLines();}}
    });
  }
  async function init(){
    bind();try{state.cat=await request('catalogos');construirCatalogos();await listar();
      if(+C.ventaOrigen>0)await precargarVenta(+C.ventaOrigen);
    } catch(e){showError(e);}
  }
  document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init):init();
})();
