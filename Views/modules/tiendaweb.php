<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['nombre'])) {
    header('Location: login');
    exit;
}

require_once dirname(__DIR__, 2) . '/Models/TiendaWeb.php';
require_once dirname(__DIR__, 2) . '/Libraries/MediaStorage.php';
require 'header.php';
require 'sidebar.php';

$puedeConfigurar = (int)($_SESSION['settings'] ?? 0) === 1;
$puedeInventario = (int)($_SESSION['almacen'] ?? 0) === 1;

if (!$puedeConfigurar && !$puedeInventario) {
    require 'access.php';
    require 'footer.php';
    ob_end_flush();
    return;
}

$modelTienda = new TiendaWeb();
$config = $modelTienda->obtenerConfiguracion();
$productos = $modelTienda->listarProductosAdmin();

if (empty($_SESSION['tienda_web_csrf'])) {
    $_SESSION['tienda_web_csrf'] = bin2hex(random_bytes(24));
}
$csrf = (string)$_SESSION['tienda_web_csrf'];

$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$slug = (string)($config['slug'] ?? 'mi-tienda');
$appBase = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
if ($appBase === '/' || $appBase === '.') {
    $appBase = '';
}
$publicPath = $appBase . '/tienda/' . rawurlencode($slug);
$logo = tiquepos_media_url('company', (string)($config['logo'] ?? ''));
if ($logo === '') {
    $logo = 'Assets/img/tiquepos_logo.png';
}
$simbolo = trim((string)($config['simbolo'] ?? 'S/')) ?: 'S/';
$activo = (int)($config['activo'] ?? 0) === 1;

$totalProductos = count($productos);
$publicados = 0;
$destacados = 0;
$categoriasSet = [];
foreach ($productos as $producto) {
    if ((int)($producto['publicado'] ?? 0) === 1 && (int)($producto['condicion'] ?? 0) === 1) $publicados++;
    if ((int)($producto['destacado'] ?? 0) === 1) $destacados++;
    $cat = trim((string)($producto['categoria'] ?? ''));
    if ($cat !== '') $categoriasSet[$cat] = true;
}
$totalCategorias = count($categoriasSet);
?>
<style>
:root{
    --tw-brand:#00a46a;
    --tw-brand-2:#078454;
    --tw-ink:#18212f;
    --tw-muted:#718096;
    --tw-line:#e8edf2;
    --tw-soft:#f6f8fb;
    --tw-card:#fff;
    --tw-shadow:0 18px 45px rgba(24,33,47,.07);
}
.tw-page{color:var(--tw-ink)}
.tw-shell{max-width:1640px;margin:0 auto;padding-bottom:36px}
.tw-top{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:20px}
.tw-title-wrap{display:flex;align-items:center;gap:14px;min-width:0}
.tw-title-icon{width:48px;height:48px;border-radius:15px;background:linear-gradient(135deg,#e9fff5,#d8f7e8);color:var(--tw-brand-2);display:grid;place-items:center;font-size:19px;box-shadow:inset 0 0 0 1px rgba(0,164,106,.1)}
.tw-top h1{margin:0 0 4px;font-size:1.55rem;font-weight:900;letter-spacing:-.035em;color:#111827}
.tw-top p{margin:0;color:var(--tw-muted);font-size:.8rem}
.tw-top-actions{display:flex;align-items:center;gap:9px;flex-wrap:wrap;justify-content:flex-end}
.tw-btn{min-height:42px;border:1px solid #dfe5eb;border-radius:12px;background:#fff;color:#4b596a;padding:0 15px;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-size:.74rem;font-weight:800;cursor:pointer;transition:.16s ease;white-space:nowrap}
.tw-btn:hover{transform:translateY(-1px);border-color:#cfd8e1;box-shadow:0 6px 15px rgba(24,33,47,.06)}
.tw-btn.primary{border-color:var(--tw-brand);background:var(--tw-brand);color:#fff;box-shadow:0 9px 20px rgba(0,164,106,.2)}
.tw-btn.primary:hover{background:var(--tw-brand-2);border-color:var(--tw-brand-2)}
.tw-btn.dark{border-color:#252d3b;background:#252d3b;color:#fff}
.tw-btn.ghost-green{border-color:#cceede;background:#f2fbf7;color:#08764f}
.tw-btn:disabled{opacity:.6;cursor:not-allowed;transform:none!important}
.tw-status{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:999px;background:#ecf9f2;color:#08784f;font-size:.66rem;font-weight:900}
.tw-status.off{background:#f1f3f6;color:#6f7886}
.tw-status-dot{width:7px;height:7px;border-radius:50%;background:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.12)}
.tw-status.off .tw-status-dot{background:#a4acb7;box-shadow:none}

.tw-overview{display:grid;grid-template-columns:1.6fr repeat(3,.8fr);gap:12px;margin-bottom:18px}
.tw-site-card,.tw-metric{border:1px solid var(--tw-line);background:#fff;border-radius:17px;box-shadow:0 8px 26px rgba(24,33,47,.045)}
.tw-site-card{display:flex;align-items:center;gap:13px;padding:14px 16px;min-width:0}
.tw-site-link-icon{width:42px;height:42px;border-radius:12px;background:#f0faf5;color:var(--tw-brand-2);display:grid;place-items:center;flex:0 0 42px}
.tw-site-link{min-width:0;flex:1}
.tw-site-link small,.tw-metric small{display:block;color:#8a95a4;font-size:.63rem;font-weight:800;text-transform:uppercase;letter-spacing:.055em;margin-bottom:3px}
.tw-site-link strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.74rem;color:#3a4758;font-weight:750}
.tw-mini-actions{display:flex;gap:5px}
.tw-icon-btn{width:35px;height:35px;border:1px solid #e2e7ec;border-radius:10px;background:#fff;color:#667385;display:grid;place-items:center;cursor:pointer;transition:.15s}
.tw-icon-btn:hover{border-color:#bfe5d3;color:var(--tw-brand-2);background:#f8fcfa}
.tw-metric{padding:13px 15px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.tw-metric strong{font-size:1.25rem;letter-spacing:-.04em;color:#1f2937}
.tw-metric-icon{width:38px;height:38px;border-radius:11px;background:#f6f8fa;color:#7c8795;display:grid;place-items:center}

.tw-workspace{display:grid;grid-template-columns:minmax(390px,.86fr) minmax(520px,1.14fr);gap:18px;align-items:start}
.tw-panel{border:1px solid var(--tw-line);border-radius:20px;background:#fff;box-shadow:var(--tw-shadow);overflow:hidden}
.tw-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 20px;border-bottom:1px solid var(--tw-line)}
.tw-panel-head h3{margin:0 0 4px;font-size:.94rem;font-weight:900;color:#243041}
.tw-panel-head p{margin:0;color:#8893a1;font-size:.68rem}
.tw-editor{min-width:0}
.tw-publish-box{margin:16px 16px 0;padding:14px;border:1px solid #e4e9ee;border-radius:15px;background:linear-gradient(135deg,#fbfcfd,#f6faf8);display:flex;align-items:center;justify-content:space-between;gap:14px}
.tw-publish-copy{display:flex;align-items:center;gap:11px;min-width:0}
.tw-publish-mark{width:38px;height:38px;border-radius:11px;background:#eaf8f1;color:#0b895d;display:grid;place-items:center;flex:0 0 38px}
.tw-publish-copy strong{display:block;font-size:.76rem;color:#2f3b4a}.tw-publish-copy small{display:block;margin-top:2px;color:#87919e;font-size:.63rem;line-height:1.35}
.tw-switch{position:relative;width:44px;height:25px;flex:0 0 44px}.tw-switch input{position:absolute;opacity:0;pointer-events:none}.tw-slider{position:absolute;inset:0;border-radius:30px;background:#cdd4dc;cursor:pointer;transition:.2s}.tw-slider:before{content:"";position:absolute;width:19px;height:19px;left:3px;top:3px;border-radius:50%;background:#fff;box-shadow:0 2px 5px rgba(0,0,0,.17);transition:.2s}.tw-switch input:checked+.tw-slider{background:var(--tw-brand)}.tw-switch input:checked+.tw-slider:before{transform:translateX(19px)}.tw-switch input:disabled+.tw-slider{cursor:not-allowed;opacity:.6}

.tw-tabs{display:flex;gap:6px;padding:14px 16px 0;overflow:auto;scrollbar-width:none}.tw-tabs::-webkit-scrollbar{display:none}
.tw-tab{flex:0 0 auto;border:0;border-radius:10px;background:transparent;color:#7c8795;padding:9px 11px;font-size:.68rem;font-weight:850;cursor:pointer}.tw-tab i{margin-right:6px}.tw-tab.active{background:#eff8f4;color:#087c54}
.tw-form-area{padding:16px 18px 18px}
.tw-tab-pane{display:none}.tw-tab-pane.active{display:block}
.tw-section-title{margin:2px 0 12px}.tw-section-title strong{display:block;font-size:.78rem;color:#303c4d}.tw-section-title span{display:block;margin-top:2px;color:#909aa6;font-size:.64rem}
.tw-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.tw-field.full{grid-column:1/-1}
.tw-field label{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;color:#4e5a69;font-size:.65rem;font-weight:850}.tw-field label em{font-style:normal;color:#a1a9b4;font-size:.58rem;font-weight:700}
.tw-field input,.tw-field textarea{width:100%;border:1px solid #dfe5eb;border-radius:11px;background:#fff;color:#2f3b49;padding:10px 11px;font-size:.73rem;outline:0;box-shadow:none;transition:.15s}
.tw-field input{height:42px}.tw-field textarea{min-height:92px;resize:vertical;line-height:1.5}.tw-field input:focus,.tw-field textarea:focus{border-color:#75c7a6;box-shadow:0 0 0 3px rgba(0,164,106,.08)}
.tw-slug-wrap{display:flex;border:1px solid #dfe5eb;border-radius:11px;overflow:hidden;background:#fff}.tw-slug-prefix{display:flex;align-items:center;padding:0 10px;background:#f7f9fb;color:#909aa6;font-size:.64rem;border-right:1px solid #e7ebef;white-space:nowrap}.tw-slug-wrap input{border:0;border-radius:0;box-shadow:none!important}
.tw-color-control{display:flex;align-items:center;gap:9px}.tw-color-control input[type=color]{width:46px;padding:4px;flex:0 0 46px}.tw-color-control input[type=text]{flex:1}
.tw-swatches{display:flex;gap:7px;flex-wrap:wrap;margin-top:9px}.tw-swatch{width:27px;height:27px;border:3px solid #fff;border-radius:9px;box-shadow:0 0 0 1px #dfe4e9;cursor:pointer}.tw-swatch.active{box-shadow:0 0 0 2px #263241}
.tw-options{display:grid;gap:8px}.tw-option{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 12px;border:1px solid #e5eaef;border-radius:12px;background:#fbfcfd}.tw-option-copy{display:flex;align-items:flex-start;gap:9px;min-width:0}.tw-option-icon{width:30px;height:30px;border-radius:9px;background:#f1f5f7;color:#718092;display:grid;place-items:center;font-size:.7rem;flex:0 0 30px}.tw-option strong{display:block;font-size:.68rem;color:#3e4a59}.tw-option small{display:block;color:#929ba7;font-size:.59rem;margin-top:2px;line-height:1.35}
.tw-form-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px;padding-top:15px;border-top:1px solid #edf0f3}.tw-form-footer small{color:#98a1ad;font-size:.6rem}.tw-form-footer .tw-btn{min-height:40px}

.tw-preview-panel{position:sticky;top:88px}
.tw-device-tools{display:flex;align-items:center;gap:4px;border:1px solid #e2e7eb;border-radius:10px;padding:3px;background:#f7f9fa}.tw-device-btn{width:32px;height:29px;border:0;border-radius:7px;background:transparent;color:#8792a0;cursor:pointer}.tw-device-btn.active{background:#fff;color:#2e3b4b;box-shadow:0 2px 7px rgba(0,0,0,.07)}
.tw-preview-stage{padding:18px;background:radial-gradient(circle at 10% 0,#eaf8f1 0,transparent 32%),linear-gradient(180deg,#f7faf9,#f3f6f7);min-height:595px}
.tw-browser{width:100%;margin:auto;border:1px solid #dfe5e3;border-radius:16px;background:#fff;overflow:hidden;box-shadow:0 24px 52px rgba(25,45,36,.13);transform-origin:top center;transition:max-width .22s ease}
.tw-browser.mobile{max-width:370px}.tw-browser.desktop{max-width:100%}
.tw-browser-chrome{height:38px;padding:0 11px;display:flex;align-items:center;gap:8px;background:#f7f8fa;border-bottom:1px solid #e7ebee}.tw-browser-dots{display:flex;gap:4px}.tw-browser-dots span{width:7px;height:7px;border-radius:50%;background:#cbd2d9}.tw-browser-address{flex:1;min-width:0;height:24px;border:1px solid #e2e6ea;border-radius:7px;background:#fff;color:#87919d;font-size:.55rem;display:flex;align-items:center;padding:0 9px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.tw-browser-lock{color:#7ea48e;margin-right:5px}
.tw-pv-nav{height:58px;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 15px;border-bottom:1px solid #edf0ef}.tw-pv-brand{display:flex;align-items:center;gap:8px;min-width:0}.tw-pv-brand img{width:34px;height:34px;border:1px solid #e8ecea;border-radius:10px;object-fit:contain;background:#fff}.tw-pv-brand strong{display:block;font-size:.65rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.tw-pv-brand small{display:block;font-size:.49rem;color:#89958e;margin-top:1px}.tw-pv-nav-actions{display:flex;gap:6px}.tw-pv-nav-pill{padding:6px 8px;border-radius:8px;background:#f4f7f5;color:#68756e;font-size:.48rem;font-weight:800}.tw-pv-nav-pill.brand{background:#edf8f3;color:#087e55}
.tw-pv-hero{position:relative;overflow:hidden;margin:12px;border-radius:14px;background:linear-gradient(120deg,#102e25 0%,var(--tw-brand) 100%);color:#fff;padding:23px 22px 26px}.tw-pv-hero:after{content:"";position:absolute;width:150px;height:150px;right:-47px;top:-65px;border-radius:50%;background:rgba(255,255,255,.1)}.tw-pv-eyebrow{font-size:.47rem;letter-spacing:.08em;text-transform:uppercase;font-weight:800;opacity:.72}.tw-pv-hero h4{position:relative;margin:5px 0 6px;color:#fff;font-size:1.25rem;letter-spacing:-.035em;z-index:1}.tw-pv-hero p{position:relative;margin:0;max-width:68%;font-size:.57rem;line-height:1.55;opacity:.84;z-index:1}.tw-pv-hero-btn{position:relative;z-index:1;display:inline-flex;margin-top:12px;padding:7px 9px;border-radius:8px;background:#fff;color:#234336;font-size:.49rem;font-weight:850}
.tw-pv-content{padding:2px 12px 14px}.tw-pv-content-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:8px 0}.tw-pv-content-head strong{font-size:.62rem}.tw-pv-search{width:140px;height:25px;border:1px solid #e4e9e6;border-radius:7px;color:#a1aaa5;font-size:.46rem;display:flex;align-items:center;padding:0 8px;gap:5px}.tw-pv-cats{display:flex;gap:5px;overflow:hidden;margin-bottom:9px}.tw-pv-cat{padding:5px 7px;border:1px solid #e4e9e6;border-radius:999px;color:#7b8780;font-size:.43rem;font-weight:750;white-space:nowrap}.tw-pv-cat.active{border-color:var(--tw-brand);background:var(--tw-brand);color:#fff}
.tw-pv-products{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}.tw-pv-product{border:1px solid #e9edeb;border-radius:10px;background:#fff;overflow:hidden}.tw-pv-img{position:relative;aspect-ratio:1.35/1;background:#f1f4f2;overflow:hidden}.tw-pv-img img{width:100%;height:100%;object-fit:cover}.tw-pv-fallback{width:100%;height:100%;display:grid;place-items:center;color:#a1aca6}.tw-pv-featured{position:absolute;left:5px;top:5px;padding:3px 5px;border-radius:999px;background:rgba(255,255,255,.92);color:#9d7600;font-size:.36rem;font-weight:900}.tw-pv-product-body{padding:7px}.tw-pv-category{font-size:.37rem;color:var(--tw-brand);font-weight:850;text-transform:uppercase}.tw-pv-product-name{height:23px;margin:3px 0 2px;font-size:.5rem;font-weight:850;line-height:1.25;overflow:hidden}.tw-pv-code{font-size:.38rem;color:#9aa39e}.tw-pv-price-row{display:flex;align-items:center;justify-content:space-between;gap:4px;margin-top:5px}.tw-pv-price{font-size:.58rem;font-weight:900;color:#26342e}.tw-pv-stock{font-size:.37rem;color:#7f8b84}.tw-pv-whatsapp{height:22px;margin-top:6px;border-radius:6px;background:var(--tw-brand);color:#fff;font-size:.4rem;font-weight:800;display:flex;align-items:center;justify-content:center;gap:4px}
.tw-preview-note{display:flex;align-items:flex-start;gap:8px;margin-top:12px;padding:10px 12px;border:1px solid #e1ebe6;border-radius:11px;background:#f8fcfa;color:#6d7d74;font-size:.6rem;line-height:1.45}.tw-preview-note i{margin-top:2px;color:#159465}

.tw-products-panel{margin-top:18px}
.tw-products-headline{display:flex;align-items:center;justify-content:space-between;gap:14px}
.tw-products-toolbar{display:flex;align-items:center;gap:10px;padding:13px 16px;border-bottom:1px solid var(--tw-line);background:#fbfcfd}
.tw-search{position:relative;flex:1;max-width:430px}.tw-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9aa4af;font-size:.72rem}.tw-search input{width:100%;height:39px;border:1px solid #dfe5ea;border-radius:10px;background:#fff;padding:0 12px 0 35px;font-size:.7rem;outline:0}.tw-search input:focus{border-color:#85c9ad;box-shadow:0 0 0 3px rgba(0,164,106,.07)}
.tw-product-filters{display:flex;gap:5px;overflow:auto;scrollbar-width:none}.tw-product-filters::-webkit-scrollbar{display:none}.tw-filter{flex:0 0 auto;height:34px;border:1px solid #e0e6eb;border-radius:9px;background:#fff;color:#74808e;padding:0 10px;font-size:.62rem;font-weight:850;cursor:pointer}.tw-filter.active{border-color:#cdebdc;background:#edf8f3;color:#087b53}
.tw-bulk-actions{margin-left:auto;display:flex;gap:6px}.tw-bulk-actions .tw-btn{min-height:35px;padding:0 10px;font-size:.62rem}
.tw-product-grid-admin{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:14px 16px 16px;background:#fff}
.tw-product-card-admin{display:flex;align-items:center;gap:12px;border:1px solid #e8edf1;border-radius:14px;padding:10px;background:#fff;transition:.15s;min-width:0}.tw-product-card-admin:hover{border-color:#d7e3dc;box-shadow:0 7px 18px rgba(24,33,47,.05)}.tw-product-card-admin.tw-state-inactive{opacity:.58}.tw-product-thumb{width:60px;height:60px;border:1px solid #e8ecef;border-radius:11px;object-fit:cover;background:#f4f6f7;flex:0 0 60px}.tw-product-main{min-width:0;flex:1}.tw-product-title-line{display:flex;align-items:center;gap:6px;min-width:0}.tw-product-title-line strong{font-size:.7rem;color:#344150;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.tw-product-title-line .tw-feature-tag{flex:0 0 auto;padding:3px 5px;border-radius:999px;background:#fff6d8;color:#9a7500;font-size:.45rem;font-weight:900}.tw-product-meta{display:flex;gap:5px;align-items:center;flex-wrap:wrap;margin-top:4px}.tw-meta-pill{padding:3px 5px;border-radius:6px;background:#f5f7f8;color:#808b97;font-size:.5rem}.tw-product-data{display:flex;align-items:center;gap:11px;margin-top:7px}.tw-product-price{font-size:.7rem;font-weight:900;color:#273544}.tw-product-stock{font-size:.55rem;color:#7f8a95}.tw-product-actions{display:flex;align-items:center;gap:7px;flex:0 0 auto}.tw-star{width:34px;height:34px;border:1px solid #e1e6eb;border-radius:9px;background:#fff;color:#a7b0ba;cursor:pointer}.tw-star.active{color:#dc9e00;background:#fff9e8;border-color:#efd991}.tw-product-card-admin.hidden-by-filter{display:none}.tw-empty{grid-column:1/-1;padding:42px;text-align:center;color:#8e98a4;font-size:.7rem}.tw-empty i{display:block;margin-bottom:8px;font-size:1.4rem;color:#b4bdc6}.tw-empty strong{display:block;margin-bottom:3px;color:#5d6876}

@media(max-width:1280px){.tw-workspace{grid-template-columns:minmax(360px,.95fr) minmax(470px,1.05fr)}.tw-overview{grid-template-columns:1.4fr repeat(3,.7fr)}.tw-product-grid-admin{grid-template-columns:1fr}}
@media(max-width:1100px){.tw-workspace{grid-template-columns:1fr}.tw-preview-panel{position:relative;top:auto}.tw-overview{grid-template-columns:1fr 1fr}.tw-site-card{grid-column:1/-1}}
@media(max-width:760px){.tw-shell{padding:0 2px 28px}.tw-top{align-items:flex-start;flex-direction:column}.tw-top-actions{width:100%;justify-content:flex-start}.tw-top-actions .tw-btn{flex:1}.tw-overview{grid-template-columns:1fr 1fr}.tw-metric{padding:12px}.tw-site-card{grid-column:1/-1}.tw-workspace{display:block}.tw-preview-panel{margin-top:14px}.tw-form-grid{grid-template-columns:1fr}.tw-field.full{grid-column:auto}.tw-tabs{padding-right:10px}.tw-preview-stage{padding:10px;min-height:0}.tw-pv-products{grid-template-columns:repeat(2,minmax(0,1fr))}.tw-pv-product:nth-child(3){display:none}.tw-products-toolbar{align-items:stretch;flex-wrap:wrap}.tw-search{max-width:none;flex:1 1 100%}.tw-product-filters{flex:1 1 100%}.tw-bulk-actions{margin-left:0;width:100%}.tw-bulk-actions .tw-btn{flex:1}.tw-product-grid-admin{grid-template-columns:1fr;padding:10px}.tw-product-card-admin{gap:9px}.tw-product-thumb{width:50px;height:50px;flex-basis:50px}.tw-products-headline{align-items:flex-start;flex-direction:column}.tw-panel-head{padding:15px}.tw-browser.mobile{max-width:330px}}
</style>

<div class="main-content tw-page">
<section class="section"><div class="section-body tw-shell">
    <div class="tw-top">
        <div class="tw-title-wrap">
            <div class="tw-title-icon"><i class="fas fa-store"></i></div>
            <div>
                <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:2px">
                    <h1>Mi página web</h1>
                    <span class="tw-status <?= $activo ? '' : 'off' ?>" id="estadoTienda"><span class="tw-status-dot"></span><span><?= $activo ? 'Publicada' : 'Sin publicar' ?></span></span>
                </div>
                <p>Configura tu vitrina digital. Productos, precios, imágenes y stock se sincronizan con TiquePOS.</p>
            </div>
        </div>
        <div class="tw-top-actions">
            <button type="button" class="tw-btn" id="btnCopiarUrl"><i class="far fa-copy"></i> Copiar enlace</button>
            <?php if ($puedeConfigurar): ?>
                <button type="submit" form="formTiendaWeb" class="tw-btn ghost-green" id="btnGuardarSuperior"><i class="fas fa-check"></i> Guardar</button>
            <?php endif; ?>
            <button type="button" class="tw-btn <?= $activo ? 'dark' : 'primary' ?>" id="btnAbrirTienda"><i class="fas <?= $activo ? 'fa-external-link-alt' : 'fa-rocket' ?>"></i> <span><?= $activo ? 'Ver mi web' : 'Publicar y abrir' ?></span></button>
        </div>
    </div>

    <div class="tw-overview">
        <div class="tw-site-card">
            <div class="tw-site-link-icon"><i class="fas fa-link"></i></div>
            <div class="tw-site-link"><small>Dirección de tu página</small><strong id="urlPublicaTexto"><?= $esc($publicPath) ?></strong></div>
            <div class="tw-mini-actions">
                <button type="button" class="tw-icon-btn" id="btnCopiarUrlMini" title="Copiar enlace"><i class="far fa-copy"></i></button>
                <button type="button" class="tw-icon-btn" id="btnAbrirUrlMini" title="Abrir página"><i class="fas fa-arrow-up-right-from-square fa-external-link-alt"></i></button>
            </div>
        </div>
        <div class="tw-metric"><div><small>Publicados</small><strong id="metricPublicados"><?= (int)$publicados ?></strong></div><div class="tw-metric-icon"><i class="fas fa-eye"></i></div></div>
        <div class="tw-metric"><div><small>Destacados</small><strong id="metricDestacados"><?= (int)$destacados ?></strong></div><div class="tw-metric-icon"><i class="fas fa-star"></i></div></div>
        <div class="tw-metric"><div><small>Categorías</small><strong><?= (int)$totalCategorias ?></strong></div><div class="tw-metric-icon"><i class="fas fa-layer-group"></i></div></div>
    </div>

    <div class="tw-workspace">
        <div class="tw-panel tw-editor">
            <div class="tw-panel-head">
                <div><h3>Editor de la página</h3><p>Personaliza la información que verá tu cliente.</p></div>
                <span style="color:#9ba5af;font-size:.65rem"><i class="fas fa-sync-alt"></i> Sincronizado</span>
            </div>

            <form id="formTiendaWeb">
                <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                <div class="tw-publish-box">
                    <div class="tw-publish-copy">
                        <div class="tw-publish-mark"><i class="fas fa-globe-americas"></i></div>
                        <div><strong>Publicar página</strong><small id="publicacionAyuda"><?= $activo ? 'Tu catálogo está visible para cualquier cliente con el enlace.' : 'Activa esta opción para que tus clientes puedan abrir el catálogo.' ?></small></div>
                    </div>
                    <label class="tw-switch"><input type="checkbox" name="activo" id="activoTienda" value="1" <?= $activo ? 'checked' : '' ?> <?= $puedeConfigurar ? '' : 'disabled' ?>><span class="tw-slider"></span></label>
                </div>

                <div class="tw-tabs" role="tablist">
                    <button type="button" class="tw-tab active" data-tab="identidad"><i class="fas fa-store-alt"></i> Identidad</button>
                    <button type="button" class="tw-tab" data-tab="pagina"><i class="fas fa-sliders-h"></i> Página</button>
                    <button type="button" class="tw-tab" data-tab="contacto"><i class="fab fa-whatsapp"></i> Contacto</button>
                </div>

                <div class="tw-form-area">
                    <div class="tw-tab-pane active" data-pane="identidad">
                        <div class="tw-section-title"><strong>Nombre y mensaje</strong><span>Estos textos aparecen en la cabecera principal de tu catálogo.</span></div>
                        <div class="tw-form-grid">
                            <div class="tw-field full"><label>Título de la página <em>Máx. 120</em></label><input type="text" name="titulo" maxlength="120" value="<?= $esc($config['titulo'] ?? $config['nombre'] ?? '') ?>" placeholder="Ej. Tiquepos Market" <?= $puedeConfigurar ? '' : 'disabled' ?>></div>
                            <div class="tw-field full"><label>Texto principal <em>Máx. 255</em></label><input type="text" name="subtitulo" maxlength="255" value="<?= $esc($config['subtitulo'] ?? '') ?>" placeholder="Ej. Todo lo que necesitas en un solo lugar" <?= $puedeConfigurar ? '' : 'disabled' ?>></div>
                            <div class="tw-field full"><label>Descripción del negocio</label><textarea name="descripcion" placeholder="Cuenta brevemente qué ofrece tu negocio..." <?= $puedeConfigurar ? '' : 'disabled' ?>><?= $esc($config['descripcion'] ?? '') ?></textarea></div>
                        </div>
                    </div>

                    <div class="tw-tab-pane" data-pane="pagina">
                        <div class="tw-section-title"><strong>Dirección y apariencia</strong><span>Personaliza tu enlace y el color de tu marca.</span></div>
                        <div class="tw-form-grid">
                            <div class="tw-field full">
                                <label>Dirección pública <em>3–60 caracteres</em></label>
                                <div class="tw-slug-wrap"><span class="tw-slug-prefix">/tienda/</span><input type="text" name="slug" id="slugTienda" maxlength="60" value="<?= $esc($slug) ?>" <?= $puedeConfigurar ? '' : 'disabled' ?>></div>
                            </div>
                            <div class="tw-field full">
                                <label>Color principal</label>
                                <div class="tw-color-control"><input type="color" id="colorPicker" value="<?= $esc($config['color_primario'] ?? '#00A46A') ?>" <?= $puedeConfigurar ? '' : 'disabled' ?>><input type="text" name="color_primario" id="colorTexto" maxlength="7" value="<?= $esc($config['color_primario'] ?? '#00A46A') ?>" <?= $puedeConfigurar ? '' : 'disabled' ?>></div>
                                <div class="tw-swatches" id="twSwatches">
                                    <?php foreach (['#00A46A','#2563EB','#7C3AED','#E11D48','#EA580C','#111827'] as $sw): ?><button type="button" class="tw-swatch" data-color="<?= $sw ?>" style="background:<?= $sw ?>" title="<?= $sw ?>" <?= $puedeConfigurar ? '' : 'disabled' ?>></button><?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="tw-section-title" style="margin-top:18px"><strong>Información visible</strong><span>Decide qué datos del inventario se muestran al público.</span></div>
                        <div class="tw-options">
                            <?php
                            $opciones = [
                                ['mostrar_precios','fa-tags','Mostrar precios','Usa automáticamente el precio de venta del inventario'],
                                ['mostrar_stock','fa-boxes','Mostrar stock','Indica disponibilidad en tiempo real'],
                                ['mostrar_codigo','fa-barcode','Mostrar SKU','Muestra el código interno del producto'],
                                ['mostrar_descripcion','fa-align-left','Mostrar descripción','Incluye la descripción dentro del detalle'],
                                ['mostrar_sin_stock','fa-eye','Mostrar agotados','Mantiene visibles productos con stock cero'],
                                ['boton_whatsapp','fa-comment-dots','Consulta por WhatsApp','Agrega una acción de contacto en cada producto']
                            ];
                            foreach ($opciones as [$campo,$icono,$tituloOpcion,$detalle]): ?>
                                <div class="tw-option">
                                    <div class="tw-option-copy"><div class="tw-option-icon"><i class="fas <?= $esc($icono) ?>"></i></div><div><strong><?= $esc($tituloOpcion) ?></strong><small><?= $esc($detalle) ?></small></div></div>
                                    <label class="tw-switch"><input type="checkbox" name="<?= $esc($campo) ?>" value="1" data-visibility="<?= $esc($campo) ?>" <?= (int)($config[$campo] ?? 1) === 1 ? 'checked' : '' ?> <?= $puedeConfigurar ? '' : 'disabled' ?>><span class="tw-slider"></span></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="tw-tab-pane" data-pane="contacto">
                        <div class="tw-section-title"><strong>Canales de contacto</strong><span>Facilita que tus clientes pasen del catálogo a una conversación o a tus redes.</span></div>
                        <div class="tw-form-grid">
                            <div class="tw-field full"><label>WhatsApp <em>Con código de país</em></label><input type="text" name="whatsapp" maxlength="25" placeholder="Ej. 51999999999" value="<?= $esc($config['whatsapp'] ?? '') ?>" <?= $puedeConfigurar ? '' : 'disabled' ?>></div>
                            <div class="tw-field full"><label>Instagram</label><input type="text" name="instagram" placeholder="instagram.com/tuempresa" value="<?= $esc($config['instagram'] ?? '') ?>" <?= $puedeConfigurar ? '' : 'disabled' ?>></div>
                            <div class="tw-field full"><label>Facebook</label><input type="text" name="facebook" placeholder="facebook.com/tuempresa" value="<?= $esc($config['facebook'] ?? '') ?>" <?= $puedeConfigurar ? '' : 'disabled' ?>></div>
                        </div>
                    </div>

                    <?php if ($puedeConfigurar): ?>
                    <div class="tw-form-footer">
                        <small><i class="fas fa-info-circle"></i> Los productos se siguen editando desde Inventario.</small>
                        <button class="tw-btn primary" type="submit" id="btnGuardarTienda"><i class="fas fa-save"></i> Guardar cambios</button>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="tw-panel tw-preview-panel">
            <div class="tw-panel-head">
                <div><h3>Vista previa</h3><p>Así verá tu catálogo un cliente desde la web.</p></div>
                <div class="tw-device-tools"><button class="tw-device-btn active" type="button" data-device="desktop" title="Escritorio"><i class="fas fa-desktop"></i></button><button class="tw-device-btn" type="button" data-device="mobile" title="Móvil"><i class="fas fa-mobile-alt"></i></button></div>
            </div>
            <div class="tw-preview-stage">
                <div class="tw-browser desktop" id="previewBrowser">
                    <div class="tw-browser-chrome"><div class="tw-browser-dots"><span></span><span></span><span></span></div><div class="tw-browser-address"><i class="fas fa-lock tw-browser-lock"></i><span id="previewAddress"><?= $esc($publicPath) ?></span></div></div>
                    <div class="tw-pv-nav">
                        <div class="tw-pv-brand"><img src="<?= $esc($logo) ?>" alt=""><div><strong id="previewNombre"><?= $esc($config['titulo'] ?? $config['nombre'] ?? 'Mi tienda') ?></strong><small>Catálogo en línea</small></div></div>
                        <div class="tw-pv-nav-actions"><span class="tw-pv-nav-pill">Productos</span><span class="tw-pv-nav-pill brand"><i class="fab fa-whatsapp"></i> Contacto</span></div>
                    </div>
                    <div class="tw-pv-hero" id="previewHero">
                        <div class="tw-pv-eyebrow">Compra fácil · información actualizada</div>
                        <h4 id="previewTitulo"><?= $esc($config['titulo'] ?? $config['nombre'] ?? 'Mi tienda') ?></h4>
                        <p id="previewSubtitulo"><?= $esc($config['subtitulo'] ?? 'Conoce nuestros productos y precios actualizados.') ?></p>
                        <span class="tw-pv-hero-btn">Ver productos <i class="fas fa-arrow-right" style="margin-left:5px"></i></span>
                    </div>
                    <div class="tw-pv-content">
                        <div class="tw-pv-content-head"><strong>Nuestros productos</strong><div class="tw-pv-search"><i class="fas fa-search"></i> Buscar producto...</div></div>
                        <div class="tw-pv-cats"><span class="tw-pv-cat active">Todos</span><?php foreach (array_slice(array_keys($categoriasSet),0,4) as $cat): ?><span class="tw-pv-cat"><?= $esc($cat) ?></span><?php endforeach; ?></div>
                        <div class="tw-pv-products">
                            <?php $previewProducts = array_slice(array_values(array_filter($productos, static fn($p) => (int)($p['condicion'] ?? 0) === 1)), 0, 3); ?>
                            <?php foreach ($previewProducts as $p):
                                $previewImg = tiquepos_media_url('products', (string)($p['imagen'] ?? ''));
                                if ($previewImg === '') $previewImg = 'Assets/img/products/default.png';
                                $pMin=(float)($p['precio_min'] ?? 0); $pMax=(float)($p['precio_max'] ?? 0);
                                $pPrice=$simbolo.' '.number_format($pMin,2); if($pMax>$pMin+.001)$pPrice.='+';
                            ?>
                            <div class="tw-pv-product">
                                <div class="tw-pv-img"><img src="<?= $esc($previewImg) ?>" onerror="this.src='Assets/img/products/default.png'" alt=""><?php if ((int)($p['destacado'] ?? 0) === 1): ?><span class="tw-pv-featured">★ Destacado</span><?php endif; ?></div>
                                <div class="tw-pv-product-body"><div class="tw-pv-category"><?= $esc($p['categoria'] ?? '') ?></div><div class="tw-pv-product-name"><?= $esc($p['nombre'] ?? '') ?></div><div class="tw-pv-code" data-preview-code>SKU <?= $esc($p['codigo'] ?: '—') ?></div><div class="tw-pv-price-row"><span class="tw-pv-price" data-preview-price><?= $esc($pPrice) ?></span><span class="tw-pv-stock" data-preview-stock><?= number_format((float)($p['stock_mostrado'] ?? 0),0) ?> disp.</span></div><div class="tw-pv-whatsapp" data-preview-whatsapp><i class="fab fa-whatsapp"></i> Consultar</div></div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (!$previewProducts): ?>
                            <div class="tw-pv-product"><div class="tw-pv-img"><div class="tw-pv-fallback"><i class="fas fa-box-open"></i></div></div><div class="tw-pv-product-body"><div class="tw-pv-category">Categoría</div><div class="tw-pv-product-name">Tu producto aparecerá aquí</div><div class="tw-pv-code" data-preview-code>SKU —</div><div class="tw-pv-price-row"><span class="tw-pv-price" data-preview-price><?= $esc($simbolo) ?> 0.00</span><span class="tw-pv-stock" data-preview-stock>0 disp.</span></div><div class="tw-pv-whatsapp" data-preview-whatsapp><i class="fab fa-whatsapp"></i> Consultar</div></div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="tw-preview-note"><i class="fas fa-bolt"></i><span>La vista previa cambia mientras editas. Los cambios públicos se aplican cuando presionas <strong>Guardar cambios</strong>.</span></div>
            </div>
        </div>
    </div>

    <div class="tw-panel tw-products-panel">
        <div class="tw-panel-head tw-products-headline">
            <div><h3>Productos de la página</h3><p>Controla qué productos se publican y cuáles aparecen primero como destacados.</p></div>
            <span style="font-size:.64rem;color:#8d97a3"><strong><?= (int)$totalProductos ?></strong> productos en inventario</span>
        </div>
        <div class="tw-products-toolbar">
            <div class="tw-search"><i class="fas fa-search"></i><input type="search" id="buscarProductoTienda" placeholder="Buscar por producto, SKU o categoría..."></div>
            <div class="tw-product-filters">
                <button type="button" class="tw-filter active" data-filter="todos">Todos</button>
                <button type="button" class="tw-filter" data-filter="publicados">Publicados</button>
                <button type="button" class="tw-filter" data-filter="ocultos">Ocultos</button>
                <button type="button" class="tw-filter" data-filter="destacados">Destacados</button>
            </div>
            <div class="tw-bulk-actions"><button class="tw-btn" type="button" data-publicar-todos="1"><i class="fas fa-eye"></i> Publicar todos</button><button class="tw-btn" type="button" data-publicar-todos="0"><i class="fas fa-eye-slash"></i> Ocultar todos</button></div>
        </div>
        <div class="tw-product-grid-admin" id="productosGridTienda">
            <?php foreach ($productos as $p):
                $imagenProducto = tiquepos_media_url('products', (string)($p['imagen'] ?? ''));
                if ($imagenProducto === '') $imagenProducto = 'Assets/img/products/default.png';
                $precioMin = (float)$p['precio_min']; $precioMax = (float)$p['precio_max'];
                $textoPrecio = $simbolo . ' ' . number_format($precioMin, 2);
                if ($precioMax > $precioMin + 0.001) $textoPrecio .= ' – ' . $simbolo . ' ' . number_format($precioMax, 2);
                $isPublished = (int)$p['publicado'] === 1;
                $isFeatured = (int)$p['destacado'] === 1;
            ?>
            <div class="tw-product-card-admin <?= (int)$p['condicion'] === 1 ? '' : 'tw-state-inactive' ?>" data-row-producto data-search="<?= $esc(strtolower(($p['nombre'] ?? '').' '.($p['codigo'] ?? '').' '.($p['categoria'] ?? ''))) ?>" data-published="<?= $isPublished ? '1':'0' ?>" data-featured="<?= $isFeatured ? '1':'0' ?>">
                <img class="tw-product-thumb" src="<?= $esc($imagenProducto) ?>" onerror="this.src='Assets/img/products/default.png'" alt="">
                <div class="tw-product-main">
                    <div class="tw-product-title-line"><strong><?= $esc($p['nombre']) ?></strong><span class="tw-feature-tag" data-feature-tag style="<?= $isFeatured ? '' : 'display:none' ?>">★ Destacado</span></div>
                    <div class="tw-product-meta"><span class="tw-meta-pill"><?= $esc($p['categoria']) ?></span><span class="tw-meta-pill"><?= $esc($p['codigo'] ?: 'Sin SKU') ?></span><?php if ((int)$p['cantidad_variaciones'] > 0): ?><span class="tw-meta-pill"><?= (int)$p['cantidad_variaciones'] ?> variantes</span><?php endif; ?><?php if ((int)$p['condicion'] !== 1): ?><span class="tw-meta-pill">Inactivo</span><?php endif; ?></div>
                    <div class="tw-product-data"><span class="tw-product-price"><?= $esc($textoPrecio) ?></span><span class="tw-product-stock"><i class="fas fa-box"></i> <?= number_format((float)$p['stock_mostrado'],0) ?> stock</span></div>
                </div>
                <div class="tw-product-actions">
                    <button type="button" class="tw-star <?= $isFeatured ? 'active' : '' ?>" data-destacado data-id="<?= (int)$p['idarticulo'] ?>" title="Destacar producto"><i class="fas fa-star"></i></button>
                    <label class="tw-switch" title="Publicar producto"><input type="checkbox" data-publicado data-id="<?= (int)$p['idarticulo'] ?>" <?= $isPublished ? 'checked' : '' ?> <?= (int)$p['condicion'] === 1 ? '' : 'disabled' ?>><span class="tw-slider"></span></label>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="tw-empty" id="sinResultadosTienda" style="<?= $productos ? 'display:none' : '' ?>"><i class="fas fa-search"></i><strong>No hay productos para mostrar</strong><span>Prueba con otro criterio de búsqueda o filtro.</span></div>
        </div>
    </div>
</div></section>
</div>

<script>
(function(){
    'use strict';
    const csrf = <?= json_encode($csrf) ?>;
    const appBase = <?= json_encode($appBase) ?>;
    const puedeConfigurar = <?= $puedeConfigurar ? 'true' : 'false' ?>;
    const endpoint = 'Controllers/TiendaWeb.php';
    const form = document.getElementById('formTiendaWeb');
    const slugInput = document.getElementById('slugTienda');
    const urlTexto = document.getElementById('urlPublicaTexto');
    const previewAddress = document.getElementById('previewAddress');
    const activoInput = document.getElementById('activoTienda');
    const btnAbrir = document.getElementById('btnAbrirTienda');
    let paginaActiva = <?= $activo ? 'true' : 'false' ?>;
    let filtroProducto = 'todos';

    function normalizarSlug(valor){
        return (valor || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9-]+/g,'-').replace(/-+/g,'-').replace(/^-+|-+$/g,'');
    }
    function publicUrl(){
        const slug = normalizarSlug(slugInput ? slugInput.value : '');
        return window.location.origin + appBase + '/tienda/' + encodeURIComponent(slug || 'mi-tienda');
    }
    function actualizarUrl(){
        const url = publicUrl();
        if(urlTexto) urlTexto.textContent = url;
        if(previewAddress) previewAddress.textContent = url;
    }
    slugInput?.addEventListener('input', actualizarUrl); actualizarUrl();

    async function copiarUrl(){
        try{ await navigator.clipboard.writeText(publicUrl()); Swal.fire({icon:'success',title:'Enlace copiado',text:'Ya puedes compartirlo con tus clientes.',timer:1300,showConfirmButton:false}); }
        catch(e){ Swal.fire({icon:'info',title:'Enlace de tu página',text:publicUrl()}); }
    }
    document.getElementById('btnCopiarUrl')?.addEventListener('click', copiarUrl);
    document.getElementById('btnCopiarUrlMini')?.addEventListener('click', copiarUrl);
    document.getElementById('btnAbrirUrlMini')?.addEventListener('click', ()=>abrirPagina());

    document.querySelectorAll('.tw-tab').forEach(btn=>btn.addEventListener('click',function(){
        document.querySelectorAll('.tw-tab').forEach(x=>x.classList.toggle('active',x===this));
        document.querySelectorAll('.tw-tab-pane').forEach(x=>x.classList.toggle('active',x.dataset.pane===this.dataset.tab));
    }));

    document.querySelectorAll('[data-device]').forEach(btn=>btn.addEventListener('click',function(){
        document.querySelectorAll('[data-device]').forEach(x=>x.classList.toggle('active',x===this));
        const browser=document.getElementById('previewBrowser');
        browser.classList.toggle('mobile',this.dataset.device==='mobile');
        browser.classList.toggle('desktop',this.dataset.device!=='mobile');
    }));

    const picker=document.getElementById('colorPicker'), colorTexto=document.getElementById('colorTexto');
    function aplicarColor(v){
        if(!/^#[0-9a-f]{6}$/i.test(v)) return;
        v=v.toUpperCase();
        document.documentElement.style.setProperty('--tw-brand',v);
        if(picker) picker.value=v;
        if(colorTexto) colorTexto.value=v;
        document.querySelectorAll('.tw-swatch').forEach(s=>s.classList.toggle('active',s.dataset.color.toUpperCase()===v));
    }
    picker?.addEventListener('input',()=>aplicarColor(picker.value));
    colorTexto?.addEventListener('input',()=>aplicarColor(colorTexto.value));
    document.querySelectorAll('.tw-swatch').forEach(s=>s.addEventListener('click',()=>aplicarColor(s.dataset.color)));
    aplicarColor(colorTexto?.value||'#00A46A');

    form?.querySelector('[name=titulo]')?.addEventListener('input',e=>{
        const valor=e.target.value.trim()||'Mi tienda';
        document.getElementById('previewTitulo').textContent=valor;
        document.getElementById('previewNombre').textContent=valor;
    });
    form?.querySelector('[name=subtitulo]')?.addEventListener('input',e=>document.getElementById('previewSubtitulo').textContent=e.target.value.trim()||'Conoce nuestros productos y precios actualizados.');

    function syncPreviewVisibility(){
        const map={mostrar_precios:'[data-preview-price]',mostrar_stock:'[data-preview-stock]',mostrar_codigo:'[data-preview-code]',boton_whatsapp:'[data-preview-whatsapp]'};
        Object.entries(map).forEach(([name,selector])=>{
            const input=form?.querySelector('[name="'+name+'"]');
            document.querySelectorAll(selector).forEach(el=>el.style.display=input?.checked?'':'none');
        });
    }
    form?.querySelectorAll('[data-visibility]').forEach(el=>el.addEventListener('change',syncPreviewVisibility)); syncPreviewVisibility();

    function actualizarEstadoVisual(activo){
        paginaActiva=!!activo;
        const estado=document.getElementById('estadoTienda');
        if(estado){estado.classList.toggle('off',!paginaActiva);estado.querySelector('span:last-child').textContent=paginaActiva?'Publicada':'Sin publicar';}
        const ayuda=document.getElementById('publicacionAyuda');
        if(ayuda) ayuda.textContent=paginaActiva?'Tu catálogo está visible para cualquier cliente con el enlace.':'Activa esta opción para que tus clientes puedan abrir el catálogo.';
        if(btnAbrir){
            btnAbrir.classList.toggle('primary',!paginaActiva);btnAbrir.classList.toggle('dark',paginaActiva);
            const icon=btnAbrir.querySelector('i'); if(icon){icon.className='fas '+(paginaActiva?'fa-external-link-alt':'fa-rocket');}
            const txt=btnAbrir.querySelector('span'); if(txt) txt.textContent=paginaActiva?'Ver mi web':'Publicar y abrir';
        }
    }
    activoInput?.addEventListener('change',()=>actualizarEstadoVisual(activoInput.checked));

    async function guardarConfiguracion(opciones={}){
        if(!puedeConfigurar || !form) return false;
        const botones=[document.getElementById('btnGuardarTienda'),document.getElementById('btnGuardarSuperior'),btnAbrir].filter(Boolean);
        botones.forEach(b=>b.disabled=true);
        try{
            const res=await fetch(endpoint+'?op=guardar_configuracion',{method:'POST',body:new FormData(form),credentials:'same-origin'});
            const data=await res.json();
            if(!res.ok||!data.success) throw new Error(data.mensaje||'No se pudo guardar.');
            if(data.config?.slug && slugInput){slugInput.value=data.config.slug;actualizarUrl();}
            const activo=Number(data.config?.activo||0)===1;
            if(activoInput) activoInput.checked=activo;
            actualizarEstadoVisual(activo);
            if(!opciones.silencioso){Swal.fire({icon:'success',title:activo?'Página actualizada':'Cambios guardados',text:activo?'Los cambios ya están visibles en tu web.':'La configuración se guardó. La página continúa sin publicar.',timer:1550,showConfirmButton:false});}
            if(opciones.abrirDespues) setTimeout(()=>window.open(publicUrl(),'_blank','noopener'),180);
            return true;
        }catch(err){Swal.fire({icon:'error',title:'No se pudo guardar',text:err.message});return false;}
        finally{botones.forEach(b=>b.disabled=false);}
    }
    form?.addEventListener('submit',async e=>{e.preventDefault();await guardarConfiguracion();});

    async function abrirPagina(){
        if(paginaActiva){window.open(publicUrl(),'_blank','noopener');return;}
        if(!puedeConfigurar){Swal.fire({icon:'info',title:'Página sin publicar',text:'Un usuario con permiso de configuración debe publicar el catálogo primero.'});return;}
        const r=await Swal.fire({icon:'question',title:'Publicar tu página',text:'La página todavía está desactivada. ¿Deseas publicarla y abrirla ahora?',showCancelButton:true,confirmButtonText:'Sí, publicar y abrir',cancelButtonText:'Cancelar',confirmButtonColor:'#00a46a'});
        if(!r.isConfirmed) return;
        if(activoInput) activoInput.checked=true;
        await guardarConfiguracion({abrirDespues:true,silencioso:true});
    }
    btnAbrir?.addEventListener('click',abrirPagina);

    async function guardarProducto(id, publicado, destacado){
        const fd=new FormData();fd.append('csrf',csrf);fd.append('idarticulo',id);fd.append('publicado',publicado?'1':'0');fd.append('destacado',destacado?'1':'0');
        const res=await fetch(endpoint+'?op=guardar_producto',{method:'POST',body:fd,credentials:'same-origin'});const data=await res.json();if(!res.ok||!data.success)throw new Error(data.mensaje||'No se pudo actualizar el producto.');
    }
    function actualizarMetricas(){
        let pub=0,dest=0;
        document.querySelectorAll('[data-row-producto]').forEach(card=>{if(card.dataset.published==='1'&&!card.classList.contains('tw-state-inactive'))pub++;if(card.dataset.featured==='1')dest++;});
        const mp=document.getElementById('metricPublicados'),md=document.getElementById('metricDestacados');if(mp)mp.textContent=pub;if(md)md.textContent=dest;
    }
    function aplicarFiltroProductos(){
        const q=(document.getElementById('buscarProductoTienda')?.value||'').trim().toLowerCase();let visibles=0;
        document.querySelectorAll('[data-row-producto]').forEach(card=>{
            const porTexto=!q||card.dataset.search.includes(q);
            const porFiltro=filtroProducto==='todos'||(filtroProducto==='publicados'&&card.dataset.published==='1')||(filtroProducto==='ocultos'&&card.dataset.published==='0')||(filtroProducto==='destacados'&&card.dataset.featured==='1');
            const show=porTexto&&porFiltro;card.classList.toggle('hidden-by-filter',!show);if(show)visibles++;
        });
        const empty=document.getElementById('sinResultadosTienda');if(empty)empty.style.display=visibles?'none':'block';
    }
    document.getElementById('buscarProductoTienda')?.addEventListener('input',aplicarFiltroProductos);
    document.querySelectorAll('[data-filter]').forEach(btn=>btn.addEventListener('click',function(){filtroProducto=this.dataset.filter;document.querySelectorAll('[data-filter]').forEach(x=>x.classList.toggle('active',x===this));aplicarFiltroProductos();}));

    document.querySelectorAll('[data-publicado]').forEach(el=>el.addEventListener('change',async function(){
        const card=this.closest('[data-row-producto]'),star=card.querySelector('[data-destacado]'),anterior=card.dataset.published;
        card.dataset.published=this.checked?'1':'0';actualizarMetricas();aplicarFiltroProductos();
        try{await guardarProducto(this.dataset.id,this.checked,star.classList.contains('active'));}
        catch(err){this.checked=!this.checked;card.dataset.published=anterior;actualizarMetricas();aplicarFiltroProductos();Swal.fire({icon:'error',title:'No se pudo actualizar',text:err.message});}
    }));
    document.querySelectorAll('[data-destacado]').forEach(el=>el.addEventListener('click',async function(){
        const card=this.closest('[data-row-producto]'),check=card.querySelector('[data-publicado]'),nuevo=!this.classList.contains('active'),anterior=card.dataset.featured;
        this.classList.toggle('active',nuevo);card.dataset.featured=nuevo?'1':'0';const tag=card.querySelector('[data-feature-tag]');if(tag)tag.style.display=nuevo?'':'none';actualizarMetricas();aplicarFiltroProductos();
        try{await guardarProducto(this.dataset.id,check.checked,nuevo);}
        catch(err){this.classList.toggle('active',!nuevo);card.dataset.featured=anterior;if(tag)tag.style.display=anterior==='1'?'':'none';actualizarMetricas();aplicarFiltroProductos();Swal.fire({icon:'error',title:'No se pudo actualizar',text:err.message});}
    }));
    document.querySelectorAll('[data-publicar-todos]').forEach(btn=>btn.addEventListener('click',async function(){
        const publicar=this.dataset.publicarTodos==='1';
        const r=await Swal.fire({icon:'question',title:publicar?'Publicar todos los productos':'Ocultar todos los productos',text:publicar?'Los productos activos quedarán visibles en tu catálogo.':'Los productos dejarán de aparecer en la página pública.',showCancelButton:true,confirmButtonText:publicar?'Publicar todos':'Ocultar todos',cancelButtonText:'Cancelar',confirmButtonColor:publicar?'#00a46a':'#374151'});if(!r.isConfirmed)return;
        const fd=new FormData();fd.append('csrf',csrf);fd.append('publicado',publicar?'1':'0');
        try{const res=await fetch(endpoint+'?op=publicar_todos',{method:'POST',body:fd,credentials:'same-origin'});const data=await res.json();if(!res.ok||!data.success)throw new Error(data.mensaje||'No se pudo actualizar.');document.querySelectorAll('[data-publicado]:not(:disabled)').forEach(x=>{x.checked=publicar;const card=x.closest('[data-row-producto]');if(card)card.dataset.published=publicar?'1':'0';});actualizarMetricas();aplicarFiltroProductos();Swal.fire({icon:'success',title:publicar?'Productos publicados':'Productos ocultos',timer:1200,showConfirmButton:false});}catch(err){Swal.fire({icon:'error',title:'No se pudo actualizar',text:err.message});}
    }));

    actualizarMetricas();
})();
</script>
<?php
require 'footer.php';
ob_end_flush();
?>
