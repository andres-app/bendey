(() => {
    'use strict';

    const boot = window.TIQUEPOS_POS_BOOT || {};
    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const money2 = (value) => Math.round((Number(value) || 0) * 100) / 100;
    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    const normalize = (value) => String(value ?? '')
        .trim()
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
    const uid = () => (window.crypto?.randomUUID?.() || `sale-${Date.now()}-${Math.random().toString(16).slice(2)}`);

    const ICONS = {
        success: '<svg viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>',
        error: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>',
        warning: '<svg viewBox="0 0 24 24"><path d="M12 3L2.5 20h19z"/><path d="M12 9v4M12 17h.01"/></svg>',
        info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/></svg>',
        cart: '<svg class="cart-mini" viewBox="0 0 24 24"><path d="M3 5h2l2.1 9a2 2 0 0 0 2 1.5h7.8a2 2 0 0 0 1.9-1.4L21 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>',
        close: '<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>',
        file: '<svg viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg>',
        plus: '<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>',
        minus: '<svg viewBox="0 0 24 24"><path d="M5 12h14"/></svg>',
        edit: '<svg viewBox="0 0 24 24"><path d="M4 20l4.2-1 10.5-10.5a2.1 2.1 0 0 0-3-3L5.2 16z"/><path d="M14 7l3 3"/></svg>',
        trash: '<svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>',
        box: '<svg viewBox="0 0 24 24"><path d="M21 16V8l-9-5-9 5v8l9 5z"/><path d="M3.3 7L12 12l8.7-5M12 22V12"/></svg>'
    };

    const state = {
        bootstrap: null,
        products: [],
        categories: [],
        vouchers: [],
        payments: [],
        company: {},
        tax: {},
        sales: [],
        activeSaleId: null,
        activeCategory: 0,
        query: '',
        onlyStock: false,
        customerTimer: null,
        customerRequestSeq: 0,
        editItemId: null,
        checkout: {
            type: 'Contado',
            rows: [],
            processing: false
        },
        scanner: {
            instance: null,
            active: false,
            starting: false,
            processing: false,
            keyboardBuffer: '',
            keyboardLast: 0
        },
        lastSaleResult: null
    };

    function toast(message, type = 'success', title = '') {
        const stack = qs('#posToastStack');
        if (!stack) return;
        const item = document.createElement('div');
        item.className = `pos-toast ${type}`;
        item.innerHTML = `
            <span class="icon">${ICONS[type] || ICONS.info}</span>
            <div>
                <strong>${escapeHtml(title || (type === 'error' ? 'No se pudo completar' : type === 'warning' ? 'Atención' : 'Listo'))}</strong>
                <p>${escapeHtml(message)}</p>
            </div>`;
        stack.appendChild(item);
        window.setTimeout(() => item.remove(), 3600);
    }

    async function api(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            ...options
        });
        const contentType = response.headers.get('content-type') || '';
        let data;
        if (contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            try { data = JSON.parse(text); } catch { data = text; }
        }
        if (response.status === 401) {
            window.location.href = 'login';
            throw new Error('Sesión expirada.');
        }
        if (!response.ok) {
            const message = typeof data === 'object' && data?.mensaje
                ? data.mensaje
                : `Error HTTP ${response.status}`;
            throw new Error(message);
        }
        return data;
    }

    function storageKey() {
        const branch = Number(state.bootstrap?.caja?.idsucursal || 0);
        return `tiquepos.pos.premium.v2.${Number(boot.userId || 0)}.${branch}`;
    }

    function defaultVoucherName() {
        const preferred = String(state.company?.venta_tipo_comprobante_predeterminado || '').trim();
        const usable = usableVouchers();
        if (preferred && usable.some(v => normalize(v.nombre) === normalize(preferred))) return preferred;
        const boleta = usable.find(v => normalize(v.nombre).includes('boleta'));
        return boleta?.nombre || usable[0]?.nombre || 'Boleta Electrónica';
    }

    function genericCustomer() {
        return {
            idpersona: 0,
            tipo_documento: 'DNI',
            num_documento: '99999999',
            nombre: 'CLIENTE VARIOS',
            direccion: '-',
            telefono: '',
            email: '',
            generic: true,
            source: 'generic'
        };
    }

    function newSale(name = '') {
        const nextNumber = state.sales.length + 1;
        return {
            id: uid(),
            name: name || `Venta ${nextNumber}`,
            voucherName: defaultVoucherName(),
            customer: genericCustomer(),
            cart: [],
            discountMode: 'amount',
            discountValue: 0,
            createdAt: Date.now()
        };
    }

    function loadSales() {
        let saved = null;
        try { saved = JSON.parse(localStorage.getItem(storageKey()) || 'null'); } catch { saved = null; }
        if (!saved || !Array.isArray(saved.sales) || saved.sales.length === 0) {
            state.sales = [newSale('Venta 1')];
            state.activeSaleId = state.sales[0].id;
            persistSales();
            return;
        }
        state.sales = saved.sales.slice(0, 8).map((sale, index) => normalizeSavedSale(sale, index));
        state.activeSaleId = state.sales.some(s => s.id === saved.activeSaleId)
            ? saved.activeSaleId
            : state.sales[0].id;
        persistSales();
    }

    function normalizeSavedSale(sale, index) {
        const productMap = new Map(state.products.map(p => [Number(p.idarticulo), p]));
        const cart = Array.isArray(sale.cart) ? sale.cart.map(item => {
            const product = productMap.get(Number(item.idarticulo));
            if (!product) return null;
            const maxStock = Math.max(0, Number(product.stock) || 0);
            const qty = Math.min(Math.max(1, Number(item.qty) || 1), Math.max(1, maxStock));
            return {
                ...item,
                stock: maxStock,
                qty,
                buyPrice: Number(product.precio_compra) || Number(item.buyPrice) || 0,
                taxCode: String(product.codigo_afectacion_igv || item.taxCode || '10'),
                taxPercent: Number(product.porcentaje_igv ?? item.taxPercent ?? 18),
                unitSunat: String(product.unidad_medida_sunat || item.unitSunat || 'NIU'),
                sunatCode: String(product.codigo_producto_sunat || item.sunatCode || ''),
                image: String(product.imagen || item.image || ''),
                category: String(product.categoria || item.category || '')
            };
        }).filter(Boolean) : [];
        const voucher = usableVouchers().some(v => v.nombre === sale.voucherName) ? sale.voucherName : defaultVoucherName();
        return {
            id: sale.id || uid(),
            name: String(sale.name || `Venta ${index + 1}`),
            voucherName: voucher,
            customer: sale.customer && typeof sale.customer === 'object' ? sale.customer : genericCustomer(),
            cart,
            discountMode: sale.discountMode === 'percent' ? 'percent' : 'amount',
            discountValue: Math.max(0, Number(sale.discountValue) || 0),
            createdAt: Number(sale.createdAt) || Date.now()
        };
    }

    function persistSales() {
        try {
            localStorage.setItem(storageKey(), JSON.stringify({
                activeSaleId: state.activeSaleId,
                sales: state.sales
            }));
        } catch (error) {
            console.warn('No se pudo guardar el borrador POS:', error);
        }
    }

    function activeSale() {
        let sale = state.sales.find(s => s.id === state.activeSaleId);
        if (!sale) {
            sale = state.sales[0] || newSale('Venta 1');
            if (!state.sales.length) state.sales.push(sale);
            state.activeSaleId = sale.id;
        }
        return sale;
    }

    function usableVouchers() {
        return state.vouchers.filter(v => {
            const name = normalize(v.nombre);
            return !name.includes('nota de credito') && !name.includes('nota de crédito') && !name.includes('recibo');
        });
    }

    function currentVoucher() {
        const sale = activeSale();
        return usableVouchers().find(v => v.nombre === sale.voucherName) || usableVouchers()[0] || null;
    }

    function currencySymbol() {
        return String(state.company?.simbolo || 'S/.').trim() || 'S/.';
    }

    function fmt(value) {
        return `${currencySymbol()} ${money2(value).toFixed(2)}`;
    }

    function totals(sale = activeSale()) {
        const subtotal = money2(sale.cart.reduce((sum, item) => sum + (Number(item.qty) || 0) * (Number(item.unitPrice) || 0), 0));
        let discount = 0;
        if (sale.discountMode === 'percent') {
            discount = money2(subtotal * Math.min(100, Math.max(0, Number(sale.discountValue) || 0)) / 100);
        } else {
            discount = money2(Math.min(subtotal, Math.max(0, Number(sale.discountValue) || 0)));
        }
        const total = money2(Math.max(0, subtotal - discount));
        return { subtotal, discount, total };
    }

    function documentMeta(voucher) {
        if (!voucher) return { label: 'Documento', caption: 'Comprobante de venta' };
        const name = normalize(voucher.nombre);
        if (name.includes('factura')) return { caption: 'Para empresas con RUC' };
        if (name.includes('boleta')) return { caption: 'Para personas naturales' };
        if (name.includes('cotizacion')) return { caption: 'Presupuesto para el cliente' };
        if (name.includes('nota de venta')) return { caption: 'Comprobante interno' };
        return { caption: 'Comprobante de venta' };
    }

    function seriesLabel(voucher) {
        if (!voucher) return '—';
        const letter = String(voucher.letra_serie || '').trim();
        const series = String(voucher.serie_comprobante || '').trim();
        return `${letter}${series}` || '—';
    }

    function renderCompanyShell() {
        qs('#posEmpresaNombre').textContent = state.company.nombre || 'Punto de venta';
        const caja = state.bootstrap?.caja || {};
        const text = Number(caja.idcaja) > 0 ? `#${Number(caja.idcaja)}` : (String(caja.modo || 'LEGACY') === 'LEGACY' ? 'Legacy' : 'Sin abrir');
        qs('#posCajaTexto').textContent = text;
        updateOnlineStatus();
    }

    function updateOnlineStatus() {
        const el = qs('#posEstadoConexion');
        if (!el) return;
        const online = navigator.onLine;
        el.classList.toggle('offline', !online);
        qs('span:last-child', el).textContent = online ? 'Disponible' : 'Sin conexión';
    }

    function renderSalesTabs() {
        const container = qs('#posSalesTabs');
        container.innerHTML = state.sales.map(sale => `
            <button type="button" class="pos-sale-tab ${sale.id === state.activeSaleId ? 'active' : ''}" data-sale-id="${escapeHtml(sale.id)}" role="tab" aria-selected="${sale.id === state.activeSaleId ? 'true' : 'false'}">
                ${ICONS.cart}
                <span class="tab-name">${escapeHtml(sale.name)}</span>
                <span class="tab-count">${sale.cart.reduce((sum, i) => sum + Number(i.qty || 0), 0)}</span>
                <span class="tab-close" data-close-sale="${escapeHtml(sale.id)}" title="Cerrar venta">${ICONS.close}</span>
            </button>
        `).join('');
    }

    function renderCategories() {
        const container = qs('#posCategoryList');
        const all = `<button type="button" class="pos-category-chip ${state.activeCategory === 0 ? 'active' : ''}" data-category="0"><span class="dot"></span>Todos</button>`;
        container.innerHTML = all + state.categories.map(cat => `
            <button type="button" class="pos-category-chip ${Number(cat.idcategoria) === state.activeCategory ? 'active' : ''}" data-category="${Number(cat.idcategoria)}">
                <span class="dot"></span>${escapeHtml(cat.nombre || 'Sin categoría')}
            </button>
        `).join('');
    }

    function productCategoryOrder(product) {
        const id = Number(product?.idcategoria) || 0;
        const index = state.categories.findIndex(cat => Number(cat.idcategoria) === id);
        return index >= 0 ? index : 999999;
    }

    function compareProducts(a, b) {
        const categoryDiff = productCategoryOrder(a) - productCategoryOrder(b);
        if (categoryDiff !== 0) return categoryDiff;

        const subcategoryDiff = String(a?.subcategoria || '').localeCompare(
            String(b?.subcategoria || ''),
            'es',
            { sensitivity: 'base', numeric: true }
        );
        if (subcategoryDiff !== 0) return subcategoryDiff;

        // Mantiene disponibles primero y agotados al final dentro de cada grupo.
        const stockA = Number(a?.stock) > 0 ? 0 : 1;
        const stockB = Number(b?.stock) > 0 ? 0 : 1;
        if (stockA !== stockB) return stockA - stockB;

        const nameDiff = String(a?.nombre || '').localeCompare(
            String(b?.nombre || ''),
            'es',
            { sensitivity: 'base', numeric: true }
        );
        if (nameDiff !== 0) return nameDiff;

        return String(a?.codigo || '').localeCompare(
            String(b?.codigo || ''),
            'es',
            { sensitivity: 'base', numeric: true }
        );
    }

    function filteredProducts() {
        const query = normalize(state.query);
        return state.products
            .filter(p => {
                if (state.activeCategory > 0 && Number(p.idcategoria) !== state.activeCategory) return false;
                if (state.onlyStock && Number(p.stock) <= 0) return false;
                if (!query) return true;
                return [p.nombre, p.codigo, p.descripcion, p.categoria, p.subcategoria]
                    .some(v => normalize(v).includes(query));
            })
            .slice()
            .sort(compareProducts);
    }

    function productImageUrl(product) {
        const raw = String(product?.imagen || '').trim();
        const fallback = 'Assets/img/products/default.png';
        if (!raw) return fallback;

        if (/^(https?:)?\/\//i.test(raw) || raw.startsWith('/') || raw.startsWith('data:') || raw.startsWith('blob:')) {
            return raw;
        }

        if (/^Assets\/img\/products\//i.test(raw)) {
            return raw.split('/').map((part, index) => index < 3 ? part : encodeURIComponent(part)).join('/');
        }

        return 'Assets/img/products/' + raw
            .split('/')
            .filter(Boolean)
            .map(part => encodeURIComponent(part))
            .join('/');
    }

    function renderProductCard(product) {
        const stock = Math.max(0, Number(product.stock) || 0);
        const image = productImageUrl(product);
        const code = String(product.codigo || '').trim();
        const tax = taxLabel(product);
        const exempt = ['20', '30', '40'].includes(String(product.codigo_afectacion_igv || ''));
        const subcategory = String(product.subcategoria || '').trim();

        return `
            <article class="pos-product-card ${stock <= 0 ? 'out-of-stock' : ''}" data-product-id="${Number(product.idarticulo)}">
                <div class="pos-product-media">
                    <img src="${escapeHtml(image)}"
                         alt="${escapeHtml(product.nombre || 'Producto')}"
                         loading="lazy"
                         decoding="async"
                         onerror="this.onerror=null;this.src='Assets/img/products/default.png'">
                    <span class="pos-product-stock-badge ${stock === 0 ? 'zero' : stock <= 5 ? 'low' : ''}">${stock === 0 ? 'Sin stock' : `Stock ${stock}`}</span>
                </div>
                <div class="pos-product-body">
                    <div class="pos-product-name" title="${escapeHtml(product.nombre || '')}">${escapeHtml(product.nombre || 'Producto')}</div>
                    <div class="pos-product-path" title="${escapeHtml(subcategory || product.categoria || '')}">
                        ${escapeHtml(subcategory || product.categoria || 'General')}
                    </div>
                    <div class="pos-product-flags">
                        <span class="pos-product-tax-pill ${exempt ? 'exempt' : 'affected'}">${escapeHtml(tax)}</span>
                    </div>
                    <div class="pos-product-meta">
                        <span class="pos-product-sku">${escapeHtml(code || 'Sin SKU')}</span>
                        <strong class="pos-product-price">${fmt(product.precio_venta)}</strong>
                    </div>
                    <button type="button" class="pos-product-add" data-add-product="${Number(product.idarticulo)}" ${stock <= 0 ? 'disabled' : ''}>
                        ${ICONS.plus}<span>${stock <= 0 ? 'Agotado' : 'Agregar'}</span>
                    </button>
                </div>
            </article>`;
    }

    function taxLabel(product) {
        const code = String(product.codigo_afectacion_igv || '10');
        if (code === '20') return 'Exonerado';
        if (code === '30') return 'Inafecto';
        if (code === '40') return 'Exportación';
        return Number(product.porcentaje_igv || 0) > 0 ? `IGV ${Number(product.porcentaje_igv)}%` : 'Gravado';
    }

    function renderProducts() {
        const products = filteredProducts();
        const grid = qs('#posProductsGrid');
        const empty = qs('#posProductsEmpty');
        const category = state.categories.find(c => Number(c.idcategoria) === state.activeCategory);
        qs('#posCatalogTitle').textContent = state.activeCategory > 0 ? (category?.nombre || 'Productos') : 'Todos los productos';
        const stockAvailable = products.filter(p => Number(p.stock) > 0).length;
        qs('#posCatalogSubtitle').textContent = `${stockAvailable} disponibles para venta · Haz clic para agregar`;
        qs('#posProductCount').textContent = `${products.length} producto${products.length === 1 ? '' : 's'}`;
        grid.hidden = products.length === 0;
        empty.hidden = products.length !== 0;
        if (!products.length) {
            grid.innerHTML = '';
            return;
        }
        // En "Todos" se respeta la jerarquía Categoría → Subcategoría → Producto.
        // Los encabezados de categoría ocupan todo el ancho y evitan que el catálogo
        // de más de 100 productos se perciba como una cuadrícula desordenada.
        if (state.activeCategory === 0) {
            const groups = new Map();
            products.forEach(product => {
                const id = Number(product.idcategoria) || 0;
                if (!groups.has(id)) {
                    groups.set(id, {
                        id,
                        name: String(product.categoria || 'Sin categoría').trim() || 'Sin categoría',
                        products: []
                    });
                }
                groups.get(id).products.push(product);
            });

            grid.innerHTML = Array.from(groups.values()).map((group, index) => `
                <div class="pos-product-group-header ${index === 0 ? 'first' : ''}">
                    <div>
                        <span class="pos-product-group-dot"></span>
                        <strong>${escapeHtml(group.name)}</strong>
                    </div>
                    <small>${group.products.length} producto${group.products.length === 1 ? '' : 's'}</small>
                </div>
                ${group.products.map(renderProductCard).join('')}
            `).join('');
            return;
        }

        grid.innerHTML = products.map(renderProductCard).join('');
    }

    function renderDocumentMenu() {
        const sale = activeSale();
        const menu = qs('#posDocumentMenu');
        menu.innerHTML = usableVouchers().map(v => {
            const meta = documentMeta(v);
            return `
                <button type="button" class="pos-doc-option ${v.nombre === sale.voucherName ? 'active' : ''}" data-voucher="${escapeHtml(v.nombre)}">
                    <span class="doc-icon">${ICONS.file}</span>
                    <span class="doc-copy"><strong>${escapeHtml(v.nombre)}</strong><small>${escapeHtml(meta.caption)}</small></span>
                    <span class="series">${escapeHtml(seriesLabel(v))}</span>
                </button>`;
        }).join('');
    }

    function renderDocumentButton() {
        const voucher = currentVoucher();
        qs('#posDocumentoNombre').textContent = voucher?.nombre || 'Comprobante';
        qs('#posDocumentoSerie').textContent = seriesLabel(voucher);
        renderDocumentMenu();
    }

    function renderCustomer() {
        const customer = activeSale().customer || genericCustomer();
        const generic = Boolean(customer.generic);
        qs('#posCustomerDocType').value = customer.tipo_documento === 'RUC' ? 'RUC' : 'DNI';
        qs('#posCustomerDocument').value = generic ? '' : (customer.num_documento || '');
        qs('#posCustomerName').value = generic ? '' : (customer.nombre || '');
        qs('#posCustomerCaption').textContent = generic ? 'Cliente varios' : (customer.nombre || 'Cliente seleccionado');
        qs('#posCustomerCheck').hidden = generic;
    }

    function renderCart() {
        const sale = activeSale();
        const list = qs('#posCartList');
        const empty = qs('#posCartEmpty');
        const { subtotal, discount, total } = totals(sale);
        const unitCount = sale.cart.reduce((sum, item) => sum + Number(item.qty || 0), 0);
        list.hidden = sale.cart.length === 0;
        empty.hidden = sale.cart.length !== 0;
        list.innerHTML = sale.cart.map(item => {
            const offer = Number(item.unitPrice) < Number(item.originalPrice) - .001;
            return `
                <div class="pos-cart-item" data-cart-id="${Number(item.idarticulo)}">
                    <div class="pos-cart-item-main">
                        <div class="pos-cart-item-name">
                            <strong title="${escapeHtml(item.displayName || item.name)}">${escapeHtml(item.displayName || item.name)}</strong>
                            ${offer ? '<span class="pos-offer-badge">Oferta</span>' : ''}
                        </div>
                        <div class="pos-cart-item-meta"><span>${escapeHtml(item.code || 'Sin SKU')}</span><span>·</span><span>Stock ${Number(item.stock)}</span></div>
                        <div class="pos-cart-item-price">${offer ? `<span class="old">${fmt(item.originalPrice)}</span>` : ''}${fmt(item.unitPrice)} c/u</div>
                        <div class="pos-cart-item-controls">
                            <div class="pos-qty-control">
                                <button type="button" data-cart-action="minus" data-id="${Number(item.idarticulo)}" ${Number(item.qty) <= 1 ? 'disabled' : ''}>${ICONS.minus}</button>
                                <strong>${Number(item.qty)}</strong>
                                <button type="button" data-cart-action="plus" data-id="${Number(item.idarticulo)}" ${Number(item.qty) >= Number(item.stock) ? 'disabled' : ''}>${ICONS.plus}</button>
                            </div>
                            <div class="pos-cart-item-actions">
                                <button type="button" data-cart-action="edit" data-id="${Number(item.idarticulo)}" title="Editar precio">${ICONS.edit}</button>
                                <button type="button" class="danger" data-cart-action="remove" data-id="${Number(item.idarticulo)}" title="Quitar">${ICONS.trash}</button>
                            </div>
                        </div>
                    </div>
                    <div class="pos-cart-item-side"><strong class="pos-cart-item-total">${fmt(Number(item.qty) * Number(item.unitPrice))}</strong></div>
                </div>`;
        }).join('');
        qs('#posSubtotal').textContent = fmt(subtotal);
        qs('#posDiscountLine').hidden = discount <= 0;
        qs('#posDiscountTotal').textContent = `- ${fmt(discount)}`;
        qs('#posTotal').textContent = fmt(total);
        qs('#posCheckoutAmount').textContent = fmt(total);
        qs('#btnCobrarVenta').disabled = sale.cart.length === 0 || total <= 0;
        qs('#posDiscountValue').value = String(Number(sale.discountValue) || 0);
        qs('#posDiscountPrefix').textContent = sale.discountMode === 'percent' ? '%' : currencySymbol();
        qsa('[data-discount-mode]').forEach(btn => btn.classList.toggle('active', btn.dataset.discountMode === sale.discountMode));
        qs('#posMobileCartCount').textContent = String(unitCount);
        qs('#posMobileCartTotal').textContent = fmt(total);
        renderSalesTabs();
    }

    function renderActiveSale() {
        renderDocumentButton();
        renderCustomer();
        renderCart();
        persistSales();
    }

    function addProduct(productId) {
        const product = state.products.find(p => Number(p.idarticulo) === Number(productId));
        if (!product) return;
        const stock = Math.max(0, Number(product.stock) || 0);
        if (stock <= 0) {
            toast('Este producto no tiene stock disponible.', 'warning', 'Producto agotado');
            return;
        }
        const sale = activeSale();
        const existing = sale.cart.find(item => Number(item.idarticulo) === Number(product.idarticulo));
        if (existing) {
            if (Number(existing.qty) >= stock) {
                toast(`Solo hay ${stock} unidad(es) disponibles.`, 'warning', 'Stock insuficiente');
                return;
            }
            existing.qty += 1;
        } else {
            sale.cart.push({
                idarticulo: Number(product.idarticulo),
                idingreso: Number(product.idingreso) || 0,
                code: String(product.codigo || ''),
                name: String(product.nombre || 'Producto'),
                displayName: String(product.nombre || 'Producto'),
                qty: 1,
                stock,
                buyPrice: Number(product.precio_compra) || 0,
                unitPrice: Number(product.precio_venta) || 0,
                originalPrice: Number(product.precio_venta) || 0,
                taxCode: String(product.codigo_afectacion_igv || '10'),
                taxPercent: Number(product.porcentaje_igv ?? 18),
                unitSunat: String(product.unidad_medida_sunat || 'NIU'),
                sunatCode: String(product.codigo_producto_sunat || ''),
                image: String(product.imagen || ''),
                category: String(product.categoria || '')
            });
        }
        persistSales();
        renderCart();
        if (window.innerWidth <= 930) {
            toast(`${product.nombre} agregado al pedido.`, 'success', 'Producto agregado');
        }
    }

    function cartAction(action, productId) {
        const sale = activeSale();
        const item = sale.cart.find(i => Number(i.idarticulo) === Number(productId));
        if (!item) return;
        if (action === 'plus') {
            if (Number(item.qty) >= Number(item.stock)) {
                toast(`Stock máximo: ${item.stock}`, 'warning');
                return;
            }
            item.qty += 1;
        } else if (action === 'minus') {
            item.qty = Math.max(1, Number(item.qty) - 1);
        } else if (action === 'remove') {
            sale.cart = sale.cart.filter(i => Number(i.idarticulo) !== Number(productId));
        } else if (action === 'edit') {
            openEditItem(productId);
            return;
        }
        persistSales();
        renderCart();
    }

    function openEditItem(productId) {
        const item = activeSale().cart.find(i => Number(i.idarticulo) === Number(productId));
        if (!item) return;
        state.editItemId = Number(productId);
        qs('#editItemName').value = item.displayName || item.name;
        qs('#editItemPrice').value = money2(item.unitPrice).toFixed(2);
        qs('#editItemCurrency').textContent = currencySymbol();
        openModal('modalEditarItem');
        window.setTimeout(() => qs('#editItemPrice')?.focus(), 100);
    }

    function saveEditedItem() {
        const item = activeSale().cart.find(i => Number(i.idarticulo) === Number(state.editItemId));
        if (!item) return;
        const price = money2(qs('#editItemPrice').value);
        const name = String(qs('#editItemName').value || '').trim();
        if (price <= 0) {
            toast('El precio debe ser mayor que cero.', 'error');
            return;
        }
        item.unitPrice = price;
        item.displayName = name || item.name;
        persistSales();
        closeModal('modalEditarItem');
        renderCart();
        toast('Precio actualizado para esta venta.', 'success');
    }

    function setGenericCustomer() {
        const sale = activeSale();
        sale.customer = genericCustomer();
        if (normalize(sale.name).startsWith('venta')) sale.name = `Venta ${state.sales.indexOf(sale) + 1}`;
        persistSales();
        renderCustomer();
        renderSalesTabs();
        hideCustomerResults();
    }

    function selectCustomer(customer, source = 'local') {
        const sale = activeSale();
        sale.customer = {
            idpersona: Number(customer.idpersona) || 0,
            tipo_documento: String(customer.tipo_documento || '').toUpperCase() === 'RUC' ? 'RUC' : 'DNI',
            num_documento: String(customer.num_documento || '').trim(),
            nombre: String(customer.nombre || '').trim(),
            direccion: String(customer.direccion || '').trim(),
            telefono: String(customer.telefono || '').trim(),
            email: String(customer.email || '').trim(),
            generic: false,
            source
        };
        const first = sale.customer.nombre.split(/\s+/).filter(Boolean).slice(0, 2).join(' ');
        if (first) sale.name = first.length > 18 ? `${first.slice(0, 18)}…` : first;
        persistSales();
        renderCustomer();
        renderSalesTabs();
        hideCustomerResults();
    }

    function hideCustomerResults() {
        const el = qs('#posCustomerResults');
        el.hidden = true;
        el.innerHTML = '';
    }

    async function searchCustomers(term) {
        term = String(term || '').trim();
        if (term.length < 2) {
            hideCustomerResults();
            return;
        }
        const seq = ++state.customerRequestSeq;
        try {
            const response = await api(`Controllers/Sell.php?op=buscarClientesPos&q=${encodeURIComponent(term)}`);
            if (seq !== state.customerRequestSeq) return;
            const clients = Array.isArray(response?.clientes) ? response.clientes : [];
            const results = qs('#posCustomerResults');
            results.hidden = false;
            results.innerHTML = clients.length ? clients.map((c, index) => `
                <button type="button" class="pos-customer-result" data-customer-index="${index}">
                    <span class="avatar">${escapeHtml(String(c.nombre || 'C').charAt(0).toUpperCase())}</span>
                    <span class="copy"><strong>${escapeHtml(c.nombre || 'Cliente')}</strong><small>${escapeHtml((c.tipo_documento || '') + ' ' + (c.num_documento || ''))}</small></span>
                </button>`).join('') : '<div class="pos-customer-result empty">No hay clientes guardados con esa búsqueda.</div>';
            results._clients = clients;
        } catch (error) {
            hideCustomerResults();
        }
    }

    async function lookupCustomerDocument() {
        const type = qs('#posCustomerDocType').value === 'RUC' ? 'RUC' : 'DNI';
        const doc = String(qs('#posCustomerDocument').value || '').replace(/\D/g, '');
        const required = type === 'RUC' ? 11 : 8;
        if (doc.length !== required) {
            toast(`El ${type} debe tener ${required} dígitos.`, 'warning', 'Documento incompleto');
            qs('#posCustomerDocument').focus();
            return;
        }
        const button = qs('#btnBuscarDocumento');
        button.disabled = true;
        try {
            const local = await api(`Controllers/Sell.php?op=buscarClientesPos&q=${encodeURIComponent(doc)}`);
            const exact = (local?.clientes || []).find(c => String(c.num_documento || '').replace(/\D/g, '') === doc);
            if (exact) {
                selectCustomer(exact, 'local');
                toast('Cliente encontrado en tus registros.', 'success');
                return;
            }
            const body = new URLSearchParams({ tipo_documento: type, num_documento: doc });
            const external = await api('Controllers/Person.php?op=getCustomerInfo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: body.toString()
            });
            if (!external || external.estado !== true || !external.resultado) {
                throw new Error(external?.mensaje || 'No se encontró información para este documento.');
            }
            const r = external.resultado;
            const name = String(r.nombre || r.nombre_completo || r.razon_social || r.razonSocial || r.nombre_o_razon_social || '').trim();
            const address = String(r.direccion || r.domicilio_fiscal || r.direccion_completa || '').trim();
            if (!name) throw new Error('La consulta no devolvió el nombre del cliente.');
            selectCustomer({
                idpersona: 0,
                tipo_documento: type,
                num_documento: doc,
                nombre: name,
                direccion: address,
                telefono: '',
                email: ''
            }, 'api');
            toast('Datos del cliente cargados correctamente.', 'success');
        } catch (error) {
            toast(error.message || 'No se pudo consultar el documento.', 'error', 'Consulta DNI/RUC');
        } finally {
            button.disabled = false;
        }
    }

    function changeVoucher(name) {
        const voucher = usableVouchers().find(v => v.nombre === name);
        if (!voucher) return;
        const sale = activeSale();
        sale.voucherName = voucher.nombre;
        qs('#posDocumentMenu').hidden = true;
        qs('#btnDocumentoVenta').setAttribute('aria-expanded', 'false');
        persistSales();
        renderDocumentButton();
        const isInvoice = normalize(voucher.nombre).includes('factura');
        if (isInvoice && sale.customer?.generic) {
            toast('Para una factura selecciona un cliente con RUC válido.', 'warning', 'Cliente requerido');
            qs('#posCustomerDocType').value = 'RUC';
            qs('#posCustomerDocument').focus();
        }
    }

    function addNewSale() {
        if (state.sales.length >= 8) {
            toast('Puedes mantener hasta 8 ventas abiertas al mismo tiempo.', 'warning', 'Límite de ventas');
            return;
        }
        const sale = newSale(`Venta ${state.sales.length + 1}`);
        state.sales.push(sale);
        state.activeSaleId = sale.id;
        persistSales();
        renderSalesTabs();
        renderActiveSale();
        qs('#posProductSearch').focus();
    }

    function closeSale(saleId) {
        const sale = state.sales.find(s => s.id === saleId);
        if (!sale) return;
        if (sale.cart.length > 0) {
            toast('Vacía el pedido antes de cerrar esta venta para evitar perder productos.', 'warning', 'Venta con productos');
            return;
        }
        if (state.sales.length === 1) {
            state.sales[0] = newSale('Venta 1');
            state.activeSaleId = state.sales[0].id;
        } else {
            const index = state.sales.findIndex(s => s.id === saleId);
            state.sales = state.sales.filter(s => s.id !== saleId);
            if (state.activeSaleId === saleId) {
                const next = state.sales[Math.max(0, Math.min(index, state.sales.length - 1))];
                state.activeSaleId = next.id;
            }
        }
        persistSales();
        renderActiveSale();
    }

    function switchSale(saleId) {
        if (!state.sales.some(s => s.id === saleId)) return;
        state.activeSaleId = saleId;
        persistSales();
        renderActiveSale();
    }

    function paymentMethods({ includeCombined = false } = {}) {
        return state.payments.filter(p => includeCombined || Number(p.es_combinado) !== 1);
    }

    function defaultPaymentId() {
        const configured = Number(state.company?.venta_idforma_pago_predeterminada || 0);
        if (paymentMethods().some(p => Number(p.idforma_pago) === configured)) return configured;
        const cash = paymentMethods().find(p => Number(p.es_efectivo) === 1);
        return Number(cash?.idforma_pago || paymentMethods()[0]?.idforma_pago || 0);
    }

    function openCheckout() {
        const sale = activeSale();
        const t = totals(sale);
        if (!sale.cart.length || t.total <= 0) return;
        const voucher = currentVoucher();
        const invoice = normalize(voucher?.nombre).includes('factura');
        if (invoice) {
            const c = sale.customer || genericCustomer();
            if (c.generic || c.tipo_documento !== 'RUC' || !/^\d{11}$/.test(String(c.num_documento || ''))) {
                toast('Selecciona un cliente con RUC de 11 dígitos antes de emitir una factura.', 'warning', 'Cliente requerido');
                qs('#posCustomerDocType').value = 'RUC';
                qs('#posCustomerDocument').focus();
                return;
            }
        }
        state.checkout.type = 'Contado';
        state.checkout.rows = [{ methodId: defaultPaymentId(), amount: t.total }];
        state.checkout.processing = false;
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        qs('#checkoutFirstDue').min = tomorrow.toISOString().slice(0, 10);
        qs('#checkoutFirstDue').value = tomorrow.toISOString().slice(0, 10);
        qs('#checkoutInstallments').value = '1';
        renderCheckout();
        openModal('modalCheckout');
    }

    function renderCheckout() {
        const sale = activeSale();
        const t = totals(sale);
        const voucher = currentVoucher();
        const invoice = normalize(voucher?.nombre).includes('factura');
        const clientLabel = sale.customer?.generic ? 'Cliente varios' : (sale.customer?.nombre || 'Cliente');
        qs('#checkoutDocumentCaption').textContent = `${voucher?.nombre || 'Comprobante'} · ${clientLabel}`;
        qs('#checkoutTotal').textContent = fmt(t.total);
        qs('#checkoutSubtotal').textContent = fmt(t.subtotal);
        qs('#checkoutDiscountRow').hidden = t.discount <= 0;
        qs('#checkoutDiscount').textContent = `- ${fmt(t.discount)}`;
        qs('#checkoutGrandTotal').textContent = fmt(t.total);
        qs('#checkoutProcessAmount').textContent = fmt(t.total);
        qs('#checkoutItemsCount').textContent = `${sale.cart.length} producto${sale.cart.length === 1 ? '' : 's'}`;
        qs('#checkoutItems').innerHTML = sale.cart.map(item => `
            <div class="pos-checkout-item">
                <span class="name"><strong>${escapeHtml(item.displayName || item.name)}</strong><small>${Number(item.qty)} × ${fmt(item.unitPrice)}</small></span>
                <strong>${fmt(Number(item.qty) * Number(item.unitPrice))}</strong>
            </div>`).join('');
        qsa('[data-payment-type]').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.paymentType === state.checkout.type);
            btn.disabled = btn.dataset.paymentType === 'Crédito' && !invoice;
        });
        if (!invoice && state.checkout.type === 'Crédito') state.checkout.type = 'Contado';
        qs('#checkoutCreditFields').hidden = state.checkout.type !== 'Crédito';
        qs('#checkoutPaymentTypeWrap').title = invoice ? '' : 'El crédito está disponible únicamente para factura electrónica.';
        renderPaymentRows();
    }

    function renderPaymentRows() {
        const methods = paymentMethods();
        const rows = qs('#checkoutPaymentRows');
        rows.innerHTML = state.checkout.rows.map((row, index) => {
            const options = methods.map(method => `<option value="${Number(method.idforma_pago)}" ${Number(method.idforma_pago) === Number(row.methodId) ? 'selected' : ''}>${escapeHtml(method.nombre)}</option>`).join('');
            return `
                <div class="pos-payment-row" data-payment-row="${index}">
                    <label class="pos-form-field"><span>Forma de pago</span><select data-payment-method="${index}">${options}</select></label>
                    <label class="pos-form-field"><span>Monto recibido</span><div class="pos-prefix-input"><span>${escapeHtml(currencySymbol())}</span><input data-payment-amount="${index}" type="number" inputmode="decimal" min="0" step="0.01" value="${money2(row.amount).toFixed(2)}"></div></label>
                    <button type="button" class="remove-payment" data-remove-payment="${index}" ${state.checkout.rows.length === 1 ? 'disabled' : ''} title="Eliminar forma de pago">${ICONS.trash}</button>
                </div>`;
        }).join('');
        updatePaymentSummary();
    }

    function updatePaymentSummary() {
        const total = totals().total;
        const paid = money2(state.checkout.rows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0));
        let change = 0;
        if (state.checkout.rows.length === 1) {
            const method = state.payments.find(p => Number(p.idforma_pago) === Number(state.checkout.rows[0].methodId));
            if (Number(method?.es_efectivo) === 1 && paid > total) change = money2(paid - total);
        }
        qs('#checkoutPaymentsCount').textContent = String(state.checkout.rows.length);
        qs('#checkoutChange').textContent = fmt(change);
        const helper = qs('#checkoutPaymentHelper');
        if (state.checkout.rows.length > 1) {
            const remaining = money2(total - paid);
            helper.textContent = Math.abs(remaining) <= .01
                ? 'Pago mixto completo. La suma coincide con el total de la venta.'
                : remaining > 0
                    ? `Falta distribuir ${fmt(remaining)} entre las formas de pago.`
                    : `Los pagos exceden el total por ${fmt(Math.abs(remaining))}. Ajusta los montos.`;
        } else {
            const method = state.payments.find(p => Number(p.idforma_pago) === Number(state.checkout.rows[0]?.methodId));
            helper.textContent = Number(method?.es_efectivo) === 1
                ? 'En efectivo puedes ingresar un monto mayor al total; el vuelto se calcula automáticamente.'
                : 'El monto debe coincidir con el total de la venta.';
        }
    }

    function addPaymentRow() {
        if (state.checkout.rows.length >= 4) {
            toast('Puedes usar hasta 4 formas de pago en una venta.', 'warning');
            return;
        }
        const used = new Set(state.checkout.rows.map(r => Number(r.methodId)));
        const method = paymentMethods().find(p => !used.has(Number(p.idforma_pago))) || paymentMethods()[0];
        if (!method) return;
        state.checkout.rows.push({ methodId: Number(method.idforma_pago), amount: 0 });
        renderPaymentRows();
    }

    function validateCheckout() {
        const sale = activeSale();
        const voucher = currentVoucher();
        const t = totals(sale);
        if (!sale.cart.length || t.total <= 0) throw new Error('El pedido no contiene productos válidos.');
        if (normalize(voucher?.nombre).includes('factura')) {
            const c = sale.customer || genericCustomer();
            if (c.generic || c.tipo_documento !== 'RUC' || !/^\d{11}$/.test(String(c.num_documento || ''))) {
                throw new Error('La factura requiere un cliente con RUC válido.');
            }
        }
        if (!state.checkout.rows.length) throw new Error('Selecciona una forma de pago.');
        const paid = money2(state.checkout.rows.reduce((sum, row) => sum + Number(row.amount || 0), 0));
        const selected = state.checkout.rows.map(row => state.payments.find(p => Number(p.idforma_pago) === Number(row.methodId)));
        if (selected.some(v => !v || Number(v.es_combinado) === 1)) throw new Error('Selecciona formas de pago válidas.');
        if (state.checkout.rows.length === 1) {
            const method = selected[0];
            if (Number(method.es_efectivo) === 1) {
                if (paid + .01 < t.total) throw new Error('El monto recibido es menor que el total de la venta.');
            } else if (Math.abs(paid - t.total) > .01) {
                throw new Error('El monto de la forma de pago debe coincidir con el total de la venta.');
            }
        } else {
            const ids = new Set(state.checkout.rows.map(r => Number(r.methodId)));
            if (ids.size < 2) throw new Error('El pago mixto requiere al menos dos formas de pago diferentes.');
            if (Math.abs(paid - t.total) > .01) throw new Error('La suma de los pagos mixtos debe ser igual al total de la venta.');
        }
        if (state.checkout.type === 'Crédito') {
            if (!normalize(voucher?.nombre).includes('factura')) throw new Error('El crédito solo está disponible para factura electrónica.');
            const installments = Number(qs('#checkoutInstallments').value || 0);
            const due = String(qs('#checkoutFirstDue').value || '');
            if (installments < 1 || installments > 36) throw new Error('El número de cuotas debe estar entre 1 y 36.');
            if (!/^\d{4}-\d{2}-\d{2}$/.test(due)) throw new Error('Selecciona la fecha de la primera cuota.');
        }
        return { sale, voucher, totals: t, selected };
    }

    function effectiveSendMode(voucher) {
        let mode = String(state.company?.venta_modo_envio_predeterminado || 'inmediato').trim().toLowerCase();
        if (!['inmediato', 'manual', 'resumen_diario'].includes(mode)) mode = 'inmediato';
        if (mode === 'resumen_diario' && !normalize(voucher?.nombre).includes('boleta')) mode = 'inmediato';
        return mode;
    }

    async function processSale() {
        if (state.checkout.processing) return;
        let validated;
        try { validated = validateCheckout(); } catch (error) {
            toast(error.message, 'error', 'Revisa el cobro');
            return;
        }
        const { sale, voucher, totals: t } = validated;
        const form = new FormData();
        form.append('tipo_comprobante', voucher.nombre);
        form.append('fecha_emision', boot.today || new Date().toISOString().slice(0, 10));
        form.append('modo_envio', effectiveSendMode(voucher));
        form.append('moneda_codigo', String(state.tax?.moneda_codigo || 'PEN'));
        form.append('tipo_cambio_sunat', '1');
        form.append('tipo_operacion_sunat', String(state.tax?.tipo_operacion_sunat || '0101'));
        const customer = sale.customer || genericCustomer();
        form.append('idcliente', String(Number(customer.idpersona) || 0));
        form.append('cliente_generico', customer.generic ? '1' : '0');
        form.append('tipo_documento', customer.generic ? 'DNI' : String(customer.tipo_documento || 'DNI'));
        form.append('num_documento', customer.generic ? '99999999' : String(customer.num_documento || ''));
        form.append('num_doc_real', customer.generic ? '99999999' : String(customer.num_documento || ''));
        form.append('nombre_cli', customer.generic ? 'CLIENTE VARIOS' : String(customer.nombre || ''));
        form.append('direccion', customer.generic ? '-' : String(customer.direccion || '-'));
        form.append('celular', customer.generic ? '' : String(customer.telefono || ''));
        form.append('email', customer.generic ? '' : String(customer.email || ''));
        form.append('descuento_total', t.discount.toFixed(2));
        const discountPercent = t.subtotal > 0 ? money2((t.discount / t.subtotal) * 100) : 0;
        form.append('descuento_porcentaje', discountPercent.toFixed(2));
        form.append('idtipopago', state.checkout.type);
        form.append('numero_cuotas', state.checkout.type === 'Crédito' ? String(Number(qs('#checkoutInstallments').value || 1)) : '0');
        form.append('fecha_pago', state.checkout.type === 'Crédito' ? String(qs('#checkoutFirstDue').value || '') : '');
        form.append('num_transac', '');
        sale.cart.forEach(item => {
            form.append('idingreso[]', String(Number(item.idingreso) || 0));
            form.append('idarticulo[]', String(Number(item.idarticulo)));
            form.append('cantidad[]', String(Number(item.qty)));
            form.append('precio_compra[]', money2(item.buyPrice).toFixed(2));
            form.append('precio_venta[]', money2(item.unitPrice).toFixed(2));
            form.append('descuento[]', '0');
        });
        if (state.checkout.rows.length === 1) {
            form.append('idforma_pago', String(Number(state.checkout.rows[0].methodId)));
        } else {
            const mixed = state.payments.find(p => Number(p.es_combinado) === 1);
            if (!mixed) {
                toast('No existe una forma de pago Mixto configurada.', 'error', 'Configuración incompleta');
                return;
            }
            form.append('idforma_pago', String(Number(mixed.idforma_pago)));
            state.checkout.rows.forEach((row, index) => {
                form.append(`pagos[${index}][idforma_pago]`, String(Number(row.methodId)));
                form.append(`pagos[${index}][monto]`, money2(row.amount).toFixed(2));
            });
        }
        state.checkout.processing = true;
        const button = qs('#btnProcesarVenta');
        button.disabled = true;
        button.querySelector('.label').innerHTML = '<span class="pos-spinner" style="width:16px;height:16px;margin:0;border-width:2px;border-color:rgba(255,255,255,.35);border-top-color:#fff"></span> Procesando...';
        try {
            const result = await api('Controllers/Sell.php?op=guardaryeditar', { method: 'POST', body: form });
            if (!result || result.success !== true) throw new Error(result?.mensaje || 'No se pudo registrar la venta.');
            state.lastSaleResult = result;
            closeModal('modalCheckout');
            showSaleSuccess(result);
            resetCompletedSale(sale.id);
            refreshCatalogAfterSale();
        } catch (error) {
            toast(error.message || 'No se pudo procesar la venta.', 'error', 'Venta no registrada');
        } finally {
            state.checkout.processing = false;
            button.disabled = false;
            button.querySelector('.label').innerHTML = '<svg viewBox="0 0 24 24"><path d="M4 7h16v10H4z"/><path d="M8 12h8M12 8v8"/></svg> Procesar venta';
        }
    }

    function resetCompletedSale(saleId) {
        const index = state.sales.findIndex(s => s.id === saleId);
        const replacement = newSale(`Venta ${Math.max(1, index + 1)}`);
        if (index >= 0) state.sales[index] = replacement;
        else state.sales.push(replacement);
        state.activeSaleId = replacement.id;
        persistSales();
        renderActiveSale();
    }

    async function refreshCatalogAfterSale() {
        try {
            const data = await api('Controllers/Sell.php?op=bootstrapPos');
            if (data?.success !== true) return;
            state.products = Array.isArray(data.productos) ? data.productos : state.products;
            state.categories = Array.isArray(data.categorias) ? data.categorias : state.categories;
            renderCategories();
            renderProducts();
        } catch (error) {
            console.warn('No se pudo refrescar stock:', error);
        }
    }

    function sunatLabel(result) {
        const status = String(result?.sunat?.status || '').toUpperCase();
        if (status === 'ACEPTADO') return 'Aceptado';
        if (status === 'NO_ENVIADO') return 'Pendiente';
        if (status === 'NO_APLICA') return 'No aplica';
        if (status === 'ERROR' || status === 'RECHAZADO') return status === 'ERROR' ? 'Error de envío' : 'Rechazado';
        return status || 'Registrado';
    }

    function showSaleSuccess(result) {
        qs('#saleSuccessMessage').textContent = result.mensaje || 'La venta se registró correctamente.';
        qs('#saleSuccessVoucher').textContent = result.comprobante || `${result.serie_comprobante || ''}-${result.num_comprobante || ''}`;
        qs('#saleSuccessTotal').textContent = fmt(result.total_venta || 0);
        qs('#saleSuccessSunat').textContent = sunatLabel(result);
        openModal('modalSaleSuccess');
    }

    function openModal(id) {
        const modal = qs(`#${id}`);
        if (!modal) return;
        modal.hidden = false;
        document.documentElement.style.overflow = 'hidden';
    }

    function closeModal(id) {
        const modal = qs(`#${id}`);
        if (!modal) return;
        modal.hidden = true;
        if (id === 'modalScanner') stopCameraScanner();
        if (!qsa('.pos-modal:not([hidden])').length) document.documentElement.style.overflow = '';
    }

    function openMobileCart() {
        qs('#posCartPanel').classList.add('open');
        qs('#posMobileBackdrop').hidden = false;
    }
    function closeMobileCart() {
        qs('#posCartPanel').classList.remove('open');
        qs('#posMobileBackdrop').hidden = true;
    }

    function scannerErrorMessage(error) {
        const text = String(error?.name || error?.message || error || '').toLowerCase();
        if (text.includes('notallowed') || text.includes('permission')) {
            return 'Permiso de cámara denegado. Habilítalo en Safari y vuelve a intentar.';
        }
        if (text.includes('notfound') || text.includes('devicesnotfound')) {
            return 'No se encontró una cámara disponible en este dispositivo.';
        }
        if (text.includes('notreadable') || text.includes('trackstart')) {
            return 'La cámara está siendo usada por otra aplicación o no puede iniciarse.';
        }
        if (text.includes('overconstrained')) {
            return 'No se pudo iniciar la cámara posterior. Intenta nuevamente.';
        }
        return 'No se pudo iniciar la cámara. Revisa los permisos del navegador.';
    }

    function createHtml5Scanner() {
        if (state.scanner.instance) return state.scanner.instance;
        if (typeof window.Html5Qrcode !== 'function') return null;

        const formats = [];
        if (window.Html5QrcodeSupportedFormats) {
            [
                'QR_CODE', 'CODE_128', 'CODE_39', 'CODE_93',
                'EAN_13', 'EAN_8', 'UPC_A', 'UPC_E', 'ITF',
                'DATA_MATRIX', 'PDF_417', 'AZTEC'
            ].forEach(name => {
                const value = window.Html5QrcodeSupportedFormats[name];
                if (typeof value !== 'undefined') formats.push(value);
            });
        }

        state.scanner.instance = new window.Html5Qrcode(
            'posScannerReader',
            formats.length ? { formatsToSupport: formats, verbose: false } : { verbose: false }
        );
        return state.scanner.instance;
    }

    async function startCameraScanner() {
        openModal('modalScanner');
        const message = qs('#posScannerMessage');

        if (state.scanner.starting || state.scanner.active) return;
        if (!window.isSecureContext) {
            message.textContent = 'La cámara requiere una conexión HTTPS.';
            return;
        }
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            message.textContent = 'Este navegador no permite acceder a la cámara.';
            return;
        }

        const scanner = createHtml5Scanner();
        if (!scanner) {
            message.textContent = 'No se pudo cargar el lector de cámara.';
            return;
        }

        state.scanner.starting = true;
        state.scanner.processing = false;
        message.textContent = 'Solicitando acceso a la cámara...';

        const config = {
            fps: 12,
            disableFlip: false
        };

        const onSuccess = async decodedText => {
            const code = String(decodedText || '').trim();
            if (state.scanner.processing || code.length < 2) return;

            state.scanner.processing = true;
            message.textContent = 'Código detectado. Procesando...';
            if (navigator.vibrate) navigator.vibrate(60);

            await stopCameraScanner();
            closeModal('modalScanner');
            window.setTimeout(() => {
                handleScannedCode(code);
                state.scanner.processing = false;
            }, 120);
        };

        const onFailure = () => {
            // Es normal que algunos fotogramas no contengan un código legible.
        };

        try {
            await scanner.start(
                { facingMode: 'environment' },
                config,
                onSuccess,
                onFailure
            );
            state.scanner.active = true;
            state.scanner.starting = false;
            message.textContent = 'Cámara activa · centra el código dentro del marco.';
            return;
        } catch (firstError) {
            try {
                const cameras = await window.Html5Qrcode.getCameras();
                if (!Array.isArray(cameras) || !cameras.length) throw firstError;

                // En iPhone suele funcionar mejor seleccionar explícitamente una cámara.
                const preferred = cameras.find(camera => /back|rear|environment|trasera/i.test(camera.label || '')) || cameras[cameras.length - 1];
                await scanner.start(
                    preferred.id,
                    config,
                    onSuccess,
                    onFailure
                );
                state.scanner.active = true;
                state.scanner.starting = false;
                message.textContent = 'Cámara activa · centra el código dentro del marco.';
                return;
            } catch (secondError) {
                state.scanner.active = false;
                state.scanner.starting = false;
                message.textContent = scannerErrorMessage(secondError || firstError);
            }
        }
    }

    async function stopCameraScanner() {
        const scanner = state.scanner.instance;
        state.scanner.active = false;
        state.scanner.starting = false;
        if (!scanner) return;

        try {
            await scanner.stop();
        } catch (error) {
            // Si ya estaba detenido, continuamos con la limpieza.
        }
        try {
            scanner.clear();
        } catch (error) {
            // El contenedor se limpiará al crear la siguiente instancia.
        }
        if (state.scanner.instance === scanner) state.scanner.instance = null;
    }

    function handleScannedCode(code) {
        const normalized = normalize(code);
        const product = state.products.find(p => normalize(p.codigo) === normalized);
        if (!product) {
            state.query = code;
            qs('#posProductSearch').value = code;
            renderProducts();
            toast(`No existe un producto con el código ${code}.`, 'warning', 'Código no encontrado');
            return;
        }
        addProduct(product.idarticulo);
        toast(`${product.nombre} agregado.`, 'success', 'Código leído');
    }

    function handleGlobalScanner(event) {
        const target = event.target;
        const editable = target && (target.matches('input,textarea,select,[contenteditable="true"]') || target.closest?.('input,textarea,select,[contenteditable="true"]'));
        if (editable) return;
        const now = performance.now();
        if (event.key === 'Enter') {
            if (state.scanner.keyboardBuffer.length >= 3) {
                const code = state.scanner.keyboardBuffer;
                state.scanner.keyboardBuffer = '';
                handleScannedCode(code);
                event.preventDefault();
            }
            return;
        }
        if (event.key.length !== 1) return;
        if (now - state.scanner.keyboardLast > 120) state.scanner.keyboardBuffer = '';
        state.scanner.keyboardLast = now;
        state.scanner.keyboardBuffer += event.key;
    }

    async function loadBootstrap(showLoader = true) {
        if (showLoader) qs('#posLoading').hidden = false;
        try {
            const data = await api('Controllers/Sell.php?op=bootstrapPos');
            if (!data || data.success !== true) throw new Error(data?.mensaje || 'No se pudo iniciar el punto de venta.');
            state.bootstrap = data;
            state.company = data.empresa || {};
            state.tax = data.tributaria || {};
            state.products = Array.isArray(data.productos) ? data.productos : [];
            state.categories = Array.isArray(data.categorias) ? data.categorias : [];
            state.vouchers = Array.isArray(data.comprobantes) ? data.comprobantes : [];
            state.payments = Array.isArray(data.formas_pago) ? data.formas_pago : [];
            loadSales();
            renderCompanyShell();
            renderCategories();
            renderProducts();
            renderSalesTabs();
            renderActiveSale();
            qs('#posApp').setAttribute('aria-busy', 'false');
        } catch (error) {
            toast(error.message || 'No se pudo cargar el POS.', 'error', 'Error de inicio');
            qs('#posLoading .pos-loading-card').innerHTML = `<span class="pos-spinner" style="animation:none;border-color:#fee2e2;border-top-color:#ef4444"></span><strong>No se pudo iniciar el POS</strong><p>${escapeHtml(error.message || 'Revisa la conexión con el servidor.')}</p><button type="button" class="pos-primary-btn" onclick="location.reload()" style="margin-top:14px">Reintentar</button>`;
            return;
        } finally {
            if (state.bootstrap) qs('#posLoading').hidden = true;
        }
    }

    function bindEvents() {
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        document.addEventListener('keydown', handleGlobalScanner);
        document.addEventListener('keydown', event => {
            if (event.key === 'F2') {
                event.preventDefault();
                qs('#posProductSearch').focus();
                qs('#posProductSearch').select();
            } else if (event.key === 'F4') {
                event.preventDefault();
                openCheckout();
            } else if (event.key === 'Escape') {
                qsa('.pos-modal:not([hidden])').forEach(modal => {
                    if (modal.id !== 'modalSaleSuccess') closeModal(modal.id);
                });
                qs('#posDocumentMenu').hidden = true;
                qs('#posUserPopover').hidden = true;
                hideCustomerResults();
                closeMobileCart();
            }
        });

        qs('#posProductSearch').addEventListener('input', event => {
            state.query = event.target.value;
            renderProducts();
        });
        qs('#posProductSearch').addEventListener('keydown', event => {
            if (event.key !== 'Enter') return;
            const raw = String(event.currentTarget.value || '').trim();
            if (!raw) return;
            const exact = state.products.find(p => normalize(p.codigo) === normalize(raw));
            if (exact) {
                event.preventDefault();
                addProduct(exact.idarticulo);
                event.currentTarget.select();
            }
        });

        qs('#posCategoryList').addEventListener('click', event => {
            const btn = event.target.closest('[data-category]');
            if (!btn) return;
            state.activeCategory = Number(btn.dataset.category) || 0;
            renderCategories();
            renderProducts();
        });

        qs('#posProductsGrid').addEventListener('click', event => {
            const btn = event.target.closest('[data-add-product]');
            const card = event.target.closest('.pos-product-card');
            if (btn) {
                event.stopPropagation();
                addProduct(Number(btn.dataset.addProduct));
            } else if (card && !card.classList.contains('out-of-stock')) {
                addProduct(Number(card.dataset.productId));
            }
        });

        qs('#btnSoloStock').addEventListener('click', event => {
            state.onlyStock = !state.onlyStock;
            event.currentTarget.setAttribute('aria-pressed', state.onlyStock ? 'true' : 'false');
            renderProducts();
        });

        qs('#posSalesTabs').addEventListener('click', event => {
            const close = event.target.closest('[data-close-sale]');
            if (close) {
                event.stopPropagation();
                closeSale(close.dataset.closeSale);
                return;
            }
            const tab = event.target.closest('[data-sale-id]');
            if (tab) switchSale(tab.dataset.saleId);
        });
        qs('#btnNuevaVenta').addEventListener('click', addNewSale);

        qs('#btnDocumentoVenta').addEventListener('click', event => {
            event.stopPropagation();
            const menu = qs('#posDocumentMenu');
            menu.hidden = !menu.hidden;
            event.currentTarget.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
            qs('#posUserPopover').hidden = true;
        });
        qs('#posDocumentMenu').addEventListener('click', event => {
            const btn = event.target.closest('[data-voucher]');
            if (btn) changeVoucher(btn.dataset.voucher);
        });

        qs('#btnUsuarioPos').addEventListener('click', event => {
            event.stopPropagation();
            const pop = qs('#posUserPopover');
            pop.hidden = !pop.hidden;
            event.currentTarget.setAttribute('aria-expanded', pop.hidden ? 'false' : 'true');
            qs('#posDocumentMenu').hidden = true;
        });
        document.addEventListener('click', event => {
            if (!event.target.closest('.pos-document-selector')) qs('#posDocumentMenu').hidden = true;
            if (!event.target.closest('.pos-user-menu-wrap')) qs('#posUserPopover').hidden = true;
            if (!event.target.closest('.pos-customer-block')) hideCustomerResults();
        });

        qs('#btnClienteGenerico').addEventListener('click', setGenericCustomer);
        qs('#btnBuscarDocumento').addEventListener('click', lookupCustomerDocument);
        qs('#posCustomerDocument').addEventListener('input', event => {
            const type = qs('#posCustomerDocType').value;
            event.target.maxLength = type === 'RUC' ? 11 : 8;
            event.target.value = event.target.value.replace(/\D/g, '').slice(0, type === 'RUC' ? 11 : 8);
            clearTimeout(state.customerTimer);
            state.customerTimer = setTimeout(() => searchCustomers(event.target.value), 180);
        });
        qs('#posCustomerDocument').addEventListener('keydown', event => {
            if (event.key === 'Enter') { event.preventDefault(); lookupCustomerDocument(); }
        });
        qs('#posCustomerName').addEventListener('input', event => {
            clearTimeout(state.customerTimer);
            state.customerTimer = setTimeout(() => searchCustomers(event.target.value), 180);
        });
        qs('#posCustomerDocType').addEventListener('change', event => {
            const input = qs('#posCustomerDocument');
            input.value = '';
            input.maxLength = event.target.value === 'RUC' ? 11 : 8;
            input.placeholder = event.target.value === 'RUC' ? '11 dígitos' : '8 dígitos';
            input.focus();
        });
        qs('#posCustomerResults').addEventListener('click', event => {
            const btn = event.target.closest('[data-customer-index]');
            if (!btn) return;
            const clients = event.currentTarget._clients || [];
            const customer = clients[Number(btn.dataset.customerIndex)];
            if (customer) selectCustomer(customer, 'local');
        });

        qs('#posCartList').addEventListener('click', event => {
            const btn = event.target.closest('[data-cart-action]');
            if (btn) cartAction(btn.dataset.cartAction, Number(btn.dataset.id));
        });
        qs('#btnVaciarCarrito').addEventListener('click', () => {
            const sale = activeSale();
            if (!sale.cart.length) return;
            sale.cart = [];
            sale.discountValue = 0;
            persistSales();
            renderCart();
            toast('Pedido vaciado.', 'success');
        });
        qsa('[data-discount-mode]').forEach(btn => btn.addEventListener('click', () => {
            const sale = activeSale();
            sale.discountMode = btn.dataset.discountMode;
            if (sale.discountMode === 'percent') sale.discountValue = Math.min(100, Number(sale.discountValue) || 0);
            persistSales();
            renderCart();
        }));
        qs('#posDiscountValue').addEventListener('input', event => {
            const sale = activeSale();
            let value = Math.max(0, Number(event.target.value) || 0);
            if (sale.discountMode === 'percent') value = Math.min(100, value);
            sale.discountValue = value;
            persistSales();
            renderCart();
        });
        qs('#btnCobrarVenta').addEventListener('click', openCheckout);
        qs('#btnGuardarItemEditado').addEventListener('click', saveEditedItem);

        qs('#checkoutPaymentRows').addEventListener('change', event => {
            const method = event.target.closest('[data-payment-method]');
            if (method) {
                state.checkout.rows[Number(method.dataset.paymentMethod)].methodId = Number(method.value);
                updatePaymentSummary();
            }
        });
        qs('#checkoutPaymentRows').addEventListener('input', event => {
            const amount = event.target.closest('[data-payment-amount]');
            if (amount) {
                state.checkout.rows[Number(amount.dataset.paymentAmount)].amount = Math.max(0, Number(amount.value) || 0);
                updatePaymentSummary();
            }
        });
        qs('#checkoutPaymentRows').addEventListener('click', event => {
            const remove = event.target.closest('[data-remove-payment]');
            if (remove && state.checkout.rows.length > 1) {
                state.checkout.rows.splice(Number(remove.dataset.removePayment), 1);
                renderPaymentRows();
            }
        });
        qs('#btnAddPayment').addEventListener('click', addPaymentRow);
        qsa('[data-payment-type]').forEach(btn => btn.addEventListener('click', () => {
            if (btn.disabled) return;
            state.checkout.type = btn.dataset.paymentType;
            renderCheckout();
        }));
        qs('#btnProcesarVenta').addEventListener('click', processSale);

        qsa('[data-close-modal]').forEach(el => el.addEventListener('click', () => closeModal(el.dataset.closeModal)));
        qs('#btnCamaraScanner').addEventListener('click', startCameraScanner);
        qs('#btnAbrirCarritoMovil').addEventListener('click', openMobileCart);
        qs('#btnCerrarCarritoMovil').addEventListener('click', closeMobileCart);
        qs('#posMobileBackdrop').addEventListener('click', closeMobileCart);

        qs('#btnRecargarPos').addEventListener('click', async () => {
            const button = qs('#btnRecargarPos');
            button.disabled = true;
            try {
                const data = await api('Controllers/Sell.php?op=bootstrapPos');
                if (data?.success !== true) throw new Error(data?.mensaje || 'No se pudo actualizar.');
                state.products = data.productos || [];
                state.categories = data.categorias || [];
                state.vouchers = data.comprobantes || [];
                state.payments = data.formas_pago || [];
                state.company = data.empresa || state.company;
                state.tax = data.tributaria || state.tax;
                renderCompanyShell(); renderCategories(); renderProducts(); renderActiveSale();
                toast('Catálogo y stock actualizados.', 'success');
            } catch (error) { toast(error.message, 'error'); }
            finally { button.disabled = false; }
        });
        qs('#btnPantallaCompleta').addEventListener('click', async () => {
            try {
                if (!document.fullscreenElement) await document.documentElement.requestFullscreen();
                else await document.exitFullscreen();
            } catch { toast('El navegador no permitió activar pantalla completa.', 'warning'); }
        });

        qs('#btnPrint80').addEventListener('click', () => {
            const id = Number(state.lastSaleResult?.idventa || 0);
            if (id > 0) window.open(`Reports/80mm.php?id=${id}`, '_blank', 'noopener');
        });
        qs('#btnPrintA4').addEventListener('click', () => {
            const id = Number(state.lastSaleResult?.idventa || 0);
            if (id > 0) window.open(`Reports/a4.php?id=${id}`, '_blank', 'noopener');
        });
        qs('#btnNuevaVentaSuccess').addEventListener('click', () => {
            closeModal('modalSaleSuccess');
            qs('#posProductSearch').focus();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindEvents();
        loadBootstrap(true);
    });
})();
