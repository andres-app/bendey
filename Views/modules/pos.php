<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Lima');

if (!isset($_SESSION['nombre'])) {
    header('Location: login');
    exit;
}

if ((int)($_SESSION['ventas'] ?? 0) !== 1) {
    http_response_code(403);
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Acceso restringido | TiquePOS</title>
        <link rel="shortcut icon" href="Assets/img/favicon.ico">
        <style>
            body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f8fafc;color:#0f172a;display:grid;min-height:100vh;place-items:center}
            .box{width:min(92vw,460px);padding:34px;border:1px solid #e2e8f0;border-radius:24px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.09);text-align:center}
            a{display:inline-flex;margin-top:18px;padding:11px 18px;border-radius:12px;background:#00a46a;color:#fff;text-decoration:none}
        </style>
    </head>
    <body>
        <div class="box">
            <h1>Acceso restringido</h1>
            <p>Tu usuario no tiene permiso para registrar ventas.</p>
            <a href="dashboard">Volver al panel</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$usuarioNombre = htmlspecialchars((string)($_SESSION['nombre'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8');
$usuarioCargo = htmlspecialchars((string)($_SESSION['cargo'] ?? 'Vendedor'), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#00a46a">
    <title>Punto de Venta | TiquePOS</title>
    <link rel="shortcut icon" type="image/x-icon" href="Assets/img/favicon.ico">
    <link rel="stylesheet" href="Assets/css/pos-premium.css?v=<?= time() ?>">
</head>
<body class="pos-body">
<div id="posApp" class="pos-app" aria-busy="true">
    <header class="pos-topbar">
        <div class="pos-brand-group">
            <a class="pos-icon-btn pos-back-btn" href="dashboard" title="Volver al panel" aria-label="Volver al panel">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/><path d="M9 12h10"/></svg>
            </a>
            <a class="pos-brand" href="dashboard" aria-label="TiquePOS">
                <span class="pos-brand-mark">
                    <img src="Assets/img/tiquepos_logo.png" alt="">
                </span>
                <span class="pos-brand-copy">
                    <strong>TiquePOS</strong>
                    <small id="posEmpresaNombre">Punto de venta</small>
                </span>
            </a>
        </div>

        <div class="pos-topbar-center">
            <span class="pos-status-pill" id="posEstadoConexion">
                <span class="pos-status-dot"></span>
                <span>Disponible</span>
            </span>
            <span class="pos-cash-pill" id="posCajaEstado" title="Caja de trabajo">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v12H4z"/><path d="M7 7V4h10v3"/><path d="M8 12h3M16 12h.01"/></svg>
                <span>Caja</span>
                <strong id="posCajaTexto">—</strong>
            </span>
        </div>

        <div class="pos-topbar-actions">
            <button type="button" class="pos-icon-btn" id="btnPantallaCompleta" title="Pantalla completa" aria-label="Pantalla completa">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"/></svg>
            </button>
            <button type="button" class="pos-icon-btn" id="btnRecargarPos" title="Actualizar catálogo" aria-label="Actualizar catálogo">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6v5h-5"/><path d="M4 18v-5h5"/><path d="M6.1 9a7 7 0 0 1 11.4-2.6L20 11M4 13l2.5 4.6A7 7 0 0 0 17.9 15"/></svg>
            </button>
            <div class="pos-user-menu-wrap">
                <button type="button" class="pos-user-btn" id="btnUsuarioPos" aria-expanded="false">
                    <span class="pos-user-avatar"><?= strtoupper(mb_substr($usuarioNombre, 0, 1, 'UTF-8')) ?></span>
                    <span class="pos-user-copy">
                        <strong><?= $usuarioNombre ?></strong>
                        <small><?= $usuarioCargo ?></small>
                    </span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 10l4 4 4-4"/></svg>
                </button>
                <div class="pos-user-popover" id="posUserPopover" hidden>
                    <a href="dashboard">
                        <svg viewBox="0 0 24 24"><path d="M4 13h6V4H4zM14 20h6v-9h-6zM4 20h6v-3H4zM14 7h6V4h-6z"/></svg>
                        Panel principal
                    </a>
                    <a href="generalsetting">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21h-4v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.1v4H21a1.7 1.7 0 0 0-1.6 1z"/></svg>
                        Configuración
                    </a>
                    <a href="salir" class="danger">
                        <svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg>
                        Cerrar sesión
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="pos-sales-strip" aria-label="Ventas abiertas">
        <div class="pos-sales-scroll" id="posSalesTabs" role="tablist"></div>
        <button type="button" class="pos-new-sale-btn" id="btnNuevaVenta" title="Nueva venta">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h2l2.2 9.2a2 2 0 0 0 2 1.5h7.8a2 2 0 0 0 1.9-1.4L21 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M16 3v4M14 5h4"/></svg>
            <span>Nueva venta</span>
        </button>
    </div>

    <main class="pos-workspace">
        <section class="pos-catalog-panel" aria-label="Catálogo de productos">
            <div class="pos-catalog-toolbar">
                <div class="pos-search-wrap">
                    <span class="pos-search-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
                    </span>
                    <input id="posProductSearch" type="search" autocomplete="off" spellcheck="false" placeholder="Buscar producto, SKU o escanear código..." aria-label="Buscar productos">
                    <kbd>F2</kbd>
                </div>
                <button type="button" class="pos-scan-btn" id="btnCamaraScanner">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7V5a2 2 0 0 1 2-2h2M16 3h2a2 2 0 0 1 2 2v2M20 17v2a2 2 0 0 1-2 2h-2M8 21H6a2 2 0 0 1-2-2v-2"/><path d="M7 12h10M9 9v6M12 9v6M15 9v6"/></svg>
                    <span>Escanear</span>
                </button>
                <button type="button" class="pos-filter-btn" id="btnSoloStock" aria-pressed="false" title="Mostrar solo productos con stock">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h18l-7 8v6l-4 2v-8z"/></svg>
                    <span>Con stock</span>
                </button>
            </div>

            <div class="pos-category-row-wrap">
                <div class="pos-category-row" id="posCategoryList" role="tablist" aria-label="Categorías"></div>
            </div>

            <div class="pos-catalog-meta">
                <div>
                    <h1 id="posCatalogTitle">Todos los productos</h1>
                    <p id="posCatalogSubtitle">Cargando inventario...</p>
                </div>
                <div class="pos-catalog-count" id="posProductCount">0 productos</div>
            </div>

            <div class="pos-products-grid" id="posProductsGrid"></div>

            <div class="pos-products-empty" id="posProductsEmpty" hidden>
                <span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 16V8l-9-5-9 5v8l9 5z"/><path d="M3.3 7L12 12l8.7-5M12 22V12"/></svg>
                </span>
                <strong>No encontramos productos</strong>
                <p>Prueba otra categoría, cambia el texto de búsqueda o revisa el stock.</p>
            </div>
        </section>

        <aside class="pos-cart-panel" id="posCartPanel" aria-label="Pedido actual">
            <div class="pos-cart-mobile-head">
                <strong>Pedido actual</strong>
                <button type="button" class="pos-icon-btn" id="btnCerrarCarritoMovil" aria-label="Cerrar pedido">
                    <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <div class="pos-sale-config">
                <div class="pos-document-selector">
                    <button type="button" class="pos-document-button" id="btnDocumentoVenta" aria-expanded="false">
                        <span class="pos-document-icon" id="posDocumentoIcono">
                            <svg viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg>
                        </span>
                        <span class="pos-document-copy">
                            <small>Comprobante</small>
                            <strong id="posDocumentoNombre">Boleta Electrónica</strong>
                        </span>
                        <span class="pos-document-serie" id="posDocumentoSerie">B001</span>
                        <svg class="pos-chevron" viewBox="0 0 24 24"><path d="M8 10l4 4 4-4"/></svg>
                    </button>
                    <div class="pos-document-menu" id="posDocumentMenu" hidden></div>
                </div>

                <div class="pos-customer-block">
                    <div class="pos-customer-heading">
                        <div>
                            <span class="pos-field-label">Cliente</span>
                            <span class="pos-customer-caption" id="posCustomerCaption">Cliente varios</span>
                        </div>
                        <button type="button" class="pos-link-btn" id="btnClienteGenerico">Usar cliente varios</button>
                    </div>
                    <div class="pos-customer-search-row">
                        <div class="pos-customer-document">
                            <select id="posCustomerDocType" aria-label="Tipo de documento">
                                <option value="DNI">DNI</option>
                                <option value="RUC">RUC</option>
                            </select>
                            <input id="posCustomerDocument" inputmode="numeric" autocomplete="off" placeholder="Número de documento" maxlength="11">
                            <button type="button" id="btnBuscarDocumento" title="Consultar DNI/RUC" aria-label="Consultar documento">
                                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
                            </button>
                        </div>
                        <div class="pos-customer-name-wrap">
                            <input id="posCustomerName" autocomplete="off" placeholder="Nombre o razón social">
                            <span class="pos-customer-check" id="posCustomerCheck" hidden>
                                <svg viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>
                            </span>
                        </div>
                    </div>
                    <div class="pos-customer-results" id="posCustomerResults" hidden></div>
                </div>
            </div>

            <div class="pos-cart-list-wrap">
                <div class="pos-cart-list-header">
                    <span>Productos</span>
                    <button type="button" class="pos-link-btn danger" id="btnVaciarCarrito">Vaciar</button>
                </div>
                <div class="pos-cart-list" id="posCartList"></div>
                <div class="pos-cart-empty" id="posCartEmpty">
                    <span>
                        <svg viewBox="0 0 24 24"><path d="M3 5h2l2.1 9a2 2 0 0 0 2 1.5h7.8a2 2 0 0 0 1.9-1.4L21 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                    </span>
                    <strong>Tu pedido está vacío</strong>
                    <p>Selecciona un producto del catálogo para comenzar.</p>
                </div>
            </div>

            <div class="pos-summary-panel">
                <div class="pos-discount-card">
                    <div class="pos-discount-heading">
                        <span>Descuento</span>
                        <div class="pos-segmented" role="group" aria-label="Tipo de descuento">
                            <button type="button" class="active" data-discount-mode="amount">S/.</button>
                            <button type="button" data-discount-mode="percent">%</button>
                        </div>
                    </div>
                    <div class="pos-money-input">
                        <span id="posDiscountPrefix">S/.</span>
                        <input id="posDiscountValue" type="number" inputmode="decimal" min="0" step="0.01" value="0" aria-label="Descuento">
                    </div>
                </div>

                <div class="pos-totals">
                    <div><span>Subtotal</span><strong id="posSubtotal">S/. 0.00</strong></div>
                    <div id="posDiscountLine" hidden><span>Descuento</span><strong id="posDiscountTotal">- S/. 0.00</strong></div>
                    <div class="pos-total-main"><span>Total</span><strong id="posTotal">S/. 0.00</strong></div>
                </div>

                <button type="button" class="pos-checkout-btn" id="btnCobrarVenta" disabled>
                    <span class="pos-checkout-label">
                        <svg viewBox="0 0 24 24"><path d="M3 5h2l2.1 9a2 2 0 0 0 2 1.5h7.8a2 2 0 0 0 1.9-1.4L21 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                        Cobrar venta
                    </span>
                    <span id="posCheckoutAmount">S/. 0.00</span>
                </button>
            </div>
        </aside>
    </main>

    <button type="button" class="pos-mobile-cart-fab" id="btnAbrirCarritoMovil" aria-label="Abrir pedido">
        <svg viewBox="0 0 24 24"><path d="M3 5h2l2.1 9a2 2 0 0 0 2 1.5h7.8a2 2 0 0 0 1.9-1.4L21 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
        <span class="pos-mobile-cart-count" id="posMobileCartCount">0</span>
        <strong id="posMobileCartTotal">S/. 0.00</strong>
    </button>
    <div class="pos-mobile-backdrop" id="posMobileBackdrop" hidden></div>

    <div class="pos-loading" id="posLoading">
        <div class="pos-loading-card">
            <span class="pos-spinner"></span>
            <strong>Preparando tu punto de venta</strong>
            <p>Cargando productos, comprobantes y formas de pago...</p>
        </div>
    </div>

    <div class="pos-toast-stack" id="posToastStack" aria-live="polite"></div>
</div>

<!-- Modal editar producto de la venta -->
<div class="pos-modal" id="modalEditarItem" hidden role="dialog" aria-modal="true" aria-labelledby="editItemTitle">
    <div class="pos-modal-backdrop" data-close-modal="modalEditarItem"></div>
    <div class="pos-modal-dialog pos-modal-sm">
        <div class="pos-modal-header">
            <div>
                <span class="pos-modal-eyebrow">Pedido actual</span>
                <h2 id="editItemTitle">Editar producto</h2>
            </div>
            <button type="button" class="pos-modal-close" data-close-modal="modalEditarItem" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <div class="pos-modal-body">
            <label class="pos-form-field">
                <span>Nombre para esta venta</span>
                <input id="editItemName" maxlength="100">
            </label>
            <label class="pos-form-field">
                <span>Precio unitario</span>
                <div class="pos-prefix-input">
                    <span id="editItemCurrency">S/.</span>
                    <input id="editItemPrice" type="number" inputmode="decimal" min="0.01" step="0.01">
                </div>
            </label>
            <div class="pos-form-note">
                El cambio se aplica únicamente a esta venta y no modifica el inventario.
            </div>
        </div>
        <div class="pos-modal-footer">
            <button type="button" class="pos-secondary-btn" data-close-modal="modalEditarItem">Cancelar</button>
            <button type="button" class="pos-primary-btn" id="btnGuardarItemEditado">Guardar cambios</button>
        </div>
    </div>
</div>

<!-- Modal de cobro -->
<div class="pos-modal" id="modalCheckout" hidden role="dialog" aria-modal="true" aria-labelledby="checkoutTitle">
    <div class="pos-modal-backdrop" data-close-modal="modalCheckout"></div>
    <div class="pos-modal-dialog pos-checkout-dialog">
        <div class="pos-modal-header pos-checkout-header">
            <div>
                <span class="pos-modal-eyebrow">Finalizar venta</span>
                <h2 id="checkoutTitle">Forma de pago</h2>
                <p id="checkoutDocumentCaption">Boleta Electrónica · Cliente varios</p>
            </div>
            <button type="button" class="pos-modal-close" data-close-modal="modalCheckout" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <div class="pos-checkout-layout">
            <section class="pos-payment-section">
                <div class="pos-payment-topline">
                    <div class="pos-payment-stat"><small>Total a pagar</small><strong id="checkoutTotal">S/. 0.00</strong></div>
                    <div class="pos-payment-stat"><small>Pagos</small><strong id="checkoutPaymentsCount">1</strong></div>
                    <div class="pos-payment-stat"><small>Vuelto</small><strong id="checkoutChange">S/. 0.00</strong></div>
                </div>

                <div class="pos-payment-type" id="checkoutPaymentTypeWrap">
                    <span>Condición</span>
                    <div class="pos-segmented pos-segmented-wide">
                        <button type="button" class="active" data-payment-type="Contado">Contado</button>
                        <button type="button" data-payment-type="Crédito">Crédito</button>
                    </div>
                </div>

                <div class="pos-credit-fields" id="checkoutCreditFields" hidden>
                    <label class="pos-form-field">
                        <span>Número de cuotas</span>
                        <input id="checkoutInstallments" type="number" min="1" max="36" value="1">
                    </label>
                    <label class="pos-form-field">
                        <span>Primera cuota</span>
                        <input id="checkoutFirstDue" type="date">
                    </label>
                </div>

                <div class="pos-payment-list" id="checkoutPaymentRows"></div>
                <button type="button" class="pos-add-payment-btn" id="btnAddPayment">
                    <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    Agregar otra forma de pago
                </button>

                <div class="pos-payment-helper" id="checkoutPaymentHelper">
                    Ingresa el monto recibido. Si pagas en efectivo calcularemos el vuelto automáticamente.
                </div>
            </section>

            <aside class="pos-checkout-summary">
                <div class="pos-checkout-summary-head">
                    <div>
                        <span>Resumen del pedido</span>
                        <strong id="checkoutItemsCount">0 productos</strong>
                    </div>
                </div>
                <div class="pos-checkout-items" id="checkoutItems"></div>
                <div class="pos-checkout-breakdown">
                    <div><span>Subtotal</span><strong id="checkoutSubtotal">S/. 0.00</strong></div>
                    <div id="checkoutDiscountRow" hidden><span>Descuento</span><strong id="checkoutDiscount">- S/. 0.00</strong></div>
                    <div class="main"><span>Total</span><strong id="checkoutGrandTotal">S/. 0.00</strong></div>
                </div>
                <button type="button" class="pos-process-btn" id="btnProcesarVenta">
                    <span class="label">
                        <svg viewBox="0 0 24 24"><path d="M4 7h16v10H4z"/><path d="M8 12h8M12 8v8"/></svg>
                        Procesar venta
                    </span>
                    <span id="checkoutProcessAmount">S/. 0.00</span>
                </button>
            </aside>
        </div>
    </div>
</div>

<!-- Modal escáner de cámara -->
<div class="pos-modal" id="modalScanner" hidden role="dialog" aria-modal="true" aria-labelledby="scannerTitle">
    <div class="pos-modal-backdrop" data-close-modal="modalScanner"></div>
    <div class="pos-modal-dialog pos-scanner-dialog">
        <div class="pos-modal-header">
            <div>
                <span class="pos-modal-eyebrow">Lector integrado</span>
                <h2 id="scannerTitle">Escanear código</h2>
                <p>QR, Code 128, EAN y otros formatos compatibles con tu dispositivo.</p>
            </div>
            <button type="button" class="pos-modal-close" data-close-modal="modalScanner" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <div class="pos-scanner-stage">
            <div id="posScannerReader" class="pos-scanner-reader" aria-label="Vista previa de la cámara"></div>
            <div class="pos-scanner-frame"><span></span><span></span><span></span><span></span></div>
            <div class="pos-scanner-message" id="posScannerMessage">Preparando cámara...</div>
        </div>
        <div class="pos-modal-footer">
            <button type="button" class="pos-secondary-btn" data-close-modal="modalScanner">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal resultado de venta -->
<div class="pos-modal" id="modalSaleSuccess" hidden role="dialog" aria-modal="true" aria-labelledby="saleSuccessTitle">
    <div class="pos-modal-backdrop"></div>
    <div class="pos-modal-dialog pos-success-dialog">
        <div class="pos-success-icon">
            <svg viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg>
        </div>
        <span class="pos-modal-eyebrow">Venta completada</span>
        <h2 id="saleSuccessTitle">Comprobante emitido</h2>
        <p id="saleSuccessMessage">La venta se registró correctamente.</p>
        <div class="pos-success-receipt">
            <div><span>Comprobante</span><strong id="saleSuccessVoucher">—</strong></div>
            <div><span>Total</span><strong id="saleSuccessTotal">—</strong></div>
            <div><span>SUNAT</span><strong id="saleSuccessSunat">—</strong></div>
        </div>
        <div class="pos-success-actions">
            <button type="button" class="pos-secondary-btn" id="btnPrint80">
                <svg viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/></svg>
                Ticket 80 mm
            </button>
            <button type="button" class="pos-secondary-btn" id="btnPrintA4">
                <svg viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg>
                A4 / PDF
            </button>
            <button type="button" class="pos-primary-btn" id="btnNuevaVentaSuccess">Nueva venta</button>
        </div>
    </div>
</div>

<script>
window.TIQUEPOS_POS_BOOT = {
    userName: <?= json_encode((string)($_SESSION['nombre'] ?? 'Usuario'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    userId: <?= (int)($_SESSION['idusuario'] ?? 0) ?>,
    today: <?= json_encode(date('Y-m-d')) ?>
};
</script>
<script
    src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"></script>
<script src="Views/modules/scripts/pos.js?v=<?= time() ?>"></script>
</body>
</html>
