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
$resumen = $modelTienda->resumenProductosAdmin();

if (empty($_SESSION['tienda_web_csrf'])) {
    $_SESSION['tienda_web_csrf'] = bin2hex(random_bytes(24));
}
$csrf = (string)$_SESSION['tienda_web_csrf'];
$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$slug = (string)($config['slug'] ?? 'mi-tienda');
$appBase = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
if ($appBase === '/' || $appBase === '.') $appBase = '';
$proto = (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
if ($proto === '') $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
$publicPath = $appBase . '/tienda/' . rawurlencode($slug);
$publicUrl = $host !== '' ? $proto . '://' . $host . $publicPath : $publicPath;
$activo = (int)($config['activo'] ?? 0) === 1;
$simbolo = trim((string)($config['simbolo'] ?? 'S/')) ?: 'S/';
?>
<style>
:root{--web-brand:#00a46a;--web-brand-dark:#087b53;--web-ink:#17212c;--web-muted:#748091;--web-line:#e5eaf0;--web-soft:#f6f8fb;--web-card:#fff;--web-shadow:0 16px 42px rgba(19,32,45,.07)}
.web-page{color:var(--web-ink)}.web-shell{max-width:1640px;margin:0 auto;padding-bottom:42px}
.web-head{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:18px}.web-head-left{display:flex;align-items:center;gap:13px;min-width:0}.web-head-icon{width:48px;height:48px;border-radius:15px;display:grid;place-items:center;background:linear-gradient(135deg,#ecfff6,#daf7e9);color:var(--web-brand-dark);font-size:18px;border:1px solid #d7f0e4}.web-head h1{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin:0 0 4px;font-size:1.55rem;font-weight:900;letter-spacing:-.035em}.web-head p{margin:0;color:var(--web-muted);font-size:.76rem}.web-actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
.web-status{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:999px;background:#eaf9f2;color:#087a52;font-size:.62rem;font-weight:900}.web-status.off{background:#f1f3f6;color:#6f7885}.web-status-dot{width:7px;height:7px;border-radius:50%;background:#12aa73}.web-status.off .web-status-dot{background:#a6adb7}
.web-btn{min-height:41px;padding:0 14px;border:1px solid #dfe5eb;border-radius:12px;background:#fff;color:#4a5868;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-size:.7rem;font-weight:850;cursor:pointer;transition:.16s;white-space:nowrap}.web-btn:hover{transform:translateY(-1px);border-color:#cfd8e0;box-shadow:0 6px 14px rgba(20,30,40,.06)}.web-btn.primary{background:var(--web-brand);border-color:var(--web-brand);color:#fff;box-shadow:0 9px 20px rgba(0,164,106,.2)}.web-btn.dark{background:#27303d;border-color:#27303d;color:#fff}.web-btn.soft{background:#f0faf5;border-color:#d8eee3;color:#08794f}.web-btn.danger-soft{background:#fff7f6;border-color:#f1d8d4;color:#a6534b}.web-btn:disabled{opacity:.6;cursor:not-allowed;transform:none}
.web-overview{display:grid;grid-template-columns:1.55fr repeat(4,.72fr);gap:11px;margin-bottom:16px}.web-overview-card{background:#fff;border:1px solid var(--web-line);border-radius:17px;box-shadow:0 8px 24px rgba(23,33,44,.04);min-width:0}.web-url-card{display:flex;align-items:center;gap:12px;padding:13px 15px}.web-url-icon,.web-metric-icon{width:39px;height:39px;border-radius:12px;display:grid;place-items:center;background:#effaf5;color:#0b865b;flex:0 0 39px}.web-url-copy{min-width:0;flex:1}.web-url-copy small,.web-metric small{display:block;font-size:.58rem;text-transform:uppercase;letter-spacing:.06em;font-weight:850;color:#939daa;margin-bottom:3px}.web-url-copy strong{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#394657;font-size:.69rem}.web-url-actions{display:flex;gap:5px}.web-icon-btn{width:34px;height:34px;border:1px solid #e1e6eb;border-radius:10px;background:#fff;color:#687587;display:grid;place-items:center;cursor:pointer}.web-icon-btn:hover{background:#f4fbf7;color:#087a52;border-color:#cce7da}.web-metric{padding:12px 13px;display:flex;align-items:center;justify-content:space-between;gap:10px}.web-metric strong{font-size:1.2rem;letter-spacing:-.04em}.web-metric-icon{background:#f6f8fa;color:#7b8795}
.web-editor-grid{display:grid;grid-template-columns:minmax(0,1fr) 390px;gap:16px;align-items:start;margin-bottom:16px}.web-panel{background:#fff;border:1px solid var(--web-line);border-radius:20px;box-shadow:var(--web-shadow);overflow:hidden}.web-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 19px;border-bottom:1px solid var(--web-line)}.web-panel-head h3{margin:0 0 3px;font-size:.92rem;font-weight:900}.web-panel-head p{margin:0;color:#8994a1;font-size:.65rem}.web-sync{font-size:.61rem;color:#8b96a3;white-space:nowrap}.web-sync.dirty{color:#b67a16}.web-tabs{display:flex;gap:6px;padding:14px 17px 0;overflow:auto;scrollbar-width:none}.web-tabs::-webkit-scrollbar{display:none}.web-tab{border:0;background:transparent;border-radius:10px;padding:9px 11px;color:#768293;font-size:.66rem;font-weight:850;cursor:pointer;white-space:nowrap}.web-tab.active{background:#eef9f4;color:#087b53}.web-tab i{margin-right:6px}.web-form-body{padding:16px 18px 18px}.web-pane{display:none}.web-pane.active{display:block}.web-section-title{margin:0 0 13px}.web-section-title strong{display:block;font-size:.76rem}.web-section-title span{display:block;color:#919ba8;font-size:.62rem;margin-top:3px}.web-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.web-field.full{grid-column:1/-1}.web-field label{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;font-size:.63rem;font-weight:850;color:#4b5868}.web-field label em{font-style:normal;color:#a0a8b2;font-size:.56rem}.web-field input,.web-field textarea{width:100%;border:1px solid #dfe5eb;border-radius:11px;background:#fff;color:#2d3948;padding:10px 11px;font-size:.71rem;outline:none;transition:.15s}.web-field input{height:41px}.web-field textarea{min-height:106px;resize:vertical;line-height:1.55}.web-field input:focus,.web-field textarea:focus{border-color:#72c4a2;box-shadow:0 0 0 3px rgba(0,164,106,.08)}.web-slug{display:flex;border:1px solid #dfe5eb;border-radius:11px;overflow:hidden}.web-slug span{display:flex;align-items:center;padding:0 10px;background:#f7f9fb;border-right:1px solid #e7ebef;color:#9099a5;font-size:.62rem;white-space:nowrap}.web-slug input{border:0;border-radius:0;box-shadow:none!important}.web-color{display:flex;gap:9px}.web-color input[type=color]{width:48px;flex:0 0 48px;padding:4px}.web-swatches{display:flex;gap:7px;flex-wrap:wrap;margin-top:9px}.web-swatch{width:28px;height:28px;border-radius:9px;border:3px solid #fff;box-shadow:0 0 0 1px #dfe5e9;cursor:pointer}.web-form-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 18px;border-top:1px solid var(--web-line);background:#fbfcfd}.web-form-footer small{color:#8a95a2;font-size:.61rem}
.web-side{position:sticky;top:86px}.web-side-body{padding:15px}.web-publish{padding:14px;border:1px solid #ddece4;border-radius:15px;background:linear-gradient(135deg,#f9fcfa,#f0faf5);display:flex;align-items:center;justify-content:space-between;gap:13px}.web-publish-copy{display:flex;align-items:center;gap:10px;min-width:0}.web-publish-mark{width:38px;height:38px;border-radius:12px;background:#e5f7ee;color:#0c875a;display:grid;place-items:center;flex:0 0 38px}.web-publish strong{display:block;font-size:.7rem}.web-publish small{display:block;margin-top:2px;color:#85918b;font-size:.58rem;line-height:1.4}.web-switch{position:relative;width:44px;height:25px;flex:0 0 44px}.web-switch input{position:absolute;opacity:0;pointer-events:none}.web-slider{position:absolute;inset:0;border-radius:999px;background:#cbd2da;cursor:pointer;transition:.2s}.web-slider:before{content:"";position:absolute;width:19px;height:19px;left:3px;top:3px;border-radius:50%;background:#fff;box-shadow:0 2px 5px rgba(0,0,0,.18);transition:.2s}.web-switch input:checked+.web-slider{background:var(--web-brand)}.web-switch input:checked+.web-slider:before{transform:translateX(19px)}.web-side-block{margin-top:14px}.web-side-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}.web-side-title strong{font-size:.68rem}.web-side-title span{font-size:.56rem;color:#9aa3ae}.web-live-link{padding:12px;border:1px solid var(--web-line);border-radius:13px;background:#fbfcfd}.web-live-link small{display:block;color:#909aa6;font-size:.56rem;margin-bottom:4px}.web-live-link strong{display:block;font-size:.63rem;color:#3d4a59;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.web-live-actions{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-top:9px}.web-option-list{display:grid;gap:7px}.web-option{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 11px;border:1px solid #e6eaee;border-radius:12px;background:#fff}.web-option-copy{display:flex;align-items:center;gap:9px;min-width:0}.web-option-ico{width:31px;height:31px;border-radius:9px;background:#f3f6f8;color:#73808e;display:grid;place-items:center;flex:0 0 31px;font-size:.68rem}.web-option strong{display:block;font-size:.63rem}.web-option small{display:block;font-size:.54rem;color:#929ba6;margin-top:2px}.web-mini-switch{position:relative;width:37px;height:21px;flex:0 0 37px}.web-mini-switch input{position:absolute;opacity:0}.web-mini-slider{position:absolute;inset:0;border-radius:999px;background:#ccd3da;cursor:pointer}.web-mini-slider:before{content:"";position:absolute;width:15px;height:15px;left:3px;top:3px;border-radius:50%;background:#fff;transition:.2s}.web-mini-switch input:checked+.web-mini-slider{background:var(--web-brand)}.web-mini-switch input:checked+.web-mini-slider:before{transform:translateX(16px)}
.web-products{margin-top:0}.web-products-top{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 18px;border-bottom:1px solid var(--web-line)}.web-products-title h3{margin:0 0 3px;font-size:.93rem;font-weight:900}.web-products-title p{margin:0;font-size:.64rem;color:#8b96a2}.web-products-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}.web-search{position:relative;width:300px}.web-search input{width:100%;height:39px;border:1px solid #dfe5ea;border-radius:11px;padding:0 12px 0 35px;font-size:.67rem;outline:none}.web-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:.64rem;color:#9aa3ae}.web-filters{display:flex;gap:5px;overflow:auto;scrollbar-width:none}.web-filter{height:35px;padding:0 10px;border:1px solid #e1e6eb;border-radius:10px;background:#fff;color:#697687;font-size:.59rem;font-weight:850;cursor:pointer;white-space:nowrap}.web-filter.active{background:#edf8f3;border-color:#d3ecdf;color:#087a51}.web-bulk{display:flex;gap:6px;padding:10px 18px;border-bottom:1px solid var(--web-line);background:#fbfcfd}.web-bulk .web-btn{min-height:34px;font-size:.59rem;padding:0 10px}.web-product-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding:14px 16px 8px}.web-product-card{display:flex;align-items:center;gap:11px;padding:11px;border:1px solid #e5e9ed;border-radius:15px;background:#fff;min-width:0;transition:.15s}.web-product-card:hover{border-color:#d6e3dc;box-shadow:0 7px 18px rgba(25,35,45,.045)}.web-product-img{width:58px;height:58px;border-radius:12px;overflow:hidden;background:#f1f4f5;display:grid;place-items:center;color:#9ba5af;flex:0 0 58px}.web-product-img img{width:100%;height:100%;object-fit:cover}.web-product-copy{min-width:0;flex:1}.web-product-cat{font-size:.53rem;color:#0b865b;font-weight:900;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.web-product-name{font-size:.67rem;font-weight:850;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.web-product-meta{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-top:5px;color:#89949f;font-size:.55rem}.web-product-meta b{color:#475362}.web-product-actions{display:flex;align-items:center;gap:6px;flex:0 0 auto}.web-star{width:31px;height:31px;border:1px solid #e2e7eb;border-radius:9px;background:#fff;color:#a0a8b2;display:grid;place-items:center;cursor:pointer}.web-star.on{background:#fff8de;border-color:#f1e0a0;color:#c28c00}.web-pub-toggle{display:flex;align-items:center;gap:6px;padding:5px 7px;border:1px solid #e2e7eb;border-radius:9px;background:#fff}.web-pub-toggle span{font-size:.52rem;font-weight:850;color:#75818f}.web-load-zone{padding:14px 16px 18px;text-align:center}.web-load-more{display:inline-flex;align-items:center;gap:8px;color:#87929e;font-size:.61rem}.web-spinner{width:18px;height:18px;border-radius:50%;border:2px solid #e0e5e9;border-top-color:var(--web-brand);animation:webspin .7s linear infinite}@keyframes webspin{to{transform:rotate(360deg)}}.web-empty{padding:44px 20px;text-align:center;color:#8a95a2}.web-empty i{display:block;font-size:28px;color:#c3cad1;margin-bottom:9px}.web-empty strong{display:block;color:#53606d;font-size:.72rem}.web-empty span{display:block;margin-top:4px;font-size:.61rem}.web-progress-note{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:11px;background:#f2faf6;color:#5c7467;font-size:.58rem}.web-progress-note i{color:#0b8d5e}
@media(max-width:1320px){.web-overview{grid-template-columns:1.4fr repeat(4,.65fr)}.web-product-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.web-editor-grid{grid-template-columns:minmax(0,1fr) 350px}}
@media(max-width:1100px){.web-editor-grid{grid-template-columns:1fr}.web-side{position:relative;top:auto}.web-overview{grid-template-columns:1fr 1fr}.web-url-card{grid-column:1/-1}.web-product-grid{grid-template-columns:1fr 1fr}}
@media(max-width:760px){.web-shell{padding:0 2px 28px}.web-head{align-items:flex-start;flex-direction:column}.web-actions{width:100%;justify-content:flex-start}.web-actions .web-btn{flex:1}.web-overview{grid-template-columns:1fr 1fr}.web-grid{grid-template-columns:1fr}.web-field.full{grid-column:auto}.web-products-top{align-items:stretch;flex-direction:column}.web-products-tools{justify-content:flex-start}.web-search{width:100%}.web-product-grid{grid-template-columns:1fr;padding:10px}.web-bulk{flex-wrap:wrap}.web-product-card{gap:9px}.web-product-actions{flex-direction:column}.web-pub-toggle span{display:none}}
</style>

<div class="main-content web-page">
<section class="section"><div class="section-body web-shell">
    <div class="web-head">
        <div class="web-head-left">
            <div class="web-head-icon"><i class="fas fa-store"></i></div>
            <div>
                <h1>Mi página web <span class="web-status <?= $activo ? '' : 'off' ?>" id="estadoTienda"><span class="web-status-dot"></span><span><?= $activo ? 'Publicada' : 'Sin publicar' ?></span></span></h1>
                <p>Administra tu vitrina digital sin duplicar inventario. Los productos, precios e imágenes se sincronizan con TiquePOS.</p>
            </div>
        </div>
        <div class="web-actions">
            <button class="web-btn" type="button" id="btnCopiarEnlace"><i class="far fa-copy"></i> Copiar enlace</button>
            <?php if ($puedeConfigurar): ?><button class="web-btn soft" type="submit" form="formTiendaWeb" id="btnGuardarTop"><i class="fas fa-check"></i> Guardar</button><?php endif; ?>
            <button class="web-btn <?= $activo ? 'dark' : 'primary' ?>" type="button" id="btnVerWeb"><i class="fas <?= $activo ? 'fa-external-link-alt' : 'fa-rocket' ?>"></i> <span><?= $activo ? 'Ver mi web' : 'Publicar y abrir' ?></span></button>
        </div>
    </div>

    <div class="web-overview">
        <div class="web-overview-card web-url-card">
            <div class="web-url-icon"><i class="fas fa-link"></i></div>
            <div class="web-url-copy"><small>Dirección de tu página</small><strong id="urlPublicaTexto"><?= $esc($publicUrl) ?></strong></div>
            <div class="web-url-actions"><button class="web-icon-btn" type="button" id="btnCopiarMini" title="Copiar"><i class="far fa-copy"></i></button><button class="web-icon-btn" type="button" id="btnAbrirMini" title="Abrir"><i class="fas fa-external-link-alt"></i></button></div>
        </div>
        <div class="web-overview-card web-metric"><div><small>Inventario</small><strong id="metricTotal"><?= (int)$resumen['total'] ?></strong></div><div class="web-metric-icon"><i class="fas fa-boxes"></i></div></div>
        <div class="web-overview-card web-metric"><div><small>Publicados</small><strong id="metricPublicados"><?= (int)$resumen['publicados'] ?></strong></div><div class="web-metric-icon"><i class="fas fa-eye"></i></div></div>
        <div class="web-overview-card web-metric"><div><small>Destacados</small><strong id="metricDestacados"><?= (int)$resumen['destacados'] ?></strong></div><div class="web-metric-icon"><i class="fas fa-star"></i></div></div>
        <div class="web-overview-card web-metric"><div><small>Categorías</small><strong id="metricCategorias"><?= (int)$resumen['categorias'] ?></strong></div><div class="web-metric-icon"><i class="fas fa-layer-group"></i></div></div>
    </div>

    <form id="formTiendaWeb">
        <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
        <div class="web-editor-grid">
            <div class="web-panel">
                <div class="web-panel-head"><div><h3>Configuración de la tienda</h3><p>Edita la información y apariencia que verá tu cliente.</p></div><span class="web-sync" id="estadoCambios"><i class="fas fa-check-circle"></i> Guardado</span></div>
                <div class="web-tabs">
                    <button type="button" class="web-tab active" data-tab="identidad"><i class="fas fa-store-alt"></i> Identidad</button>
                    <button type="button" class="web-tab" data-tab="pagina"><i class="fas fa-palette"></i> Página</button>
                    <button type="button" class="web-tab" data-tab="contacto"><i class="fab fa-whatsapp"></i> Contacto</button>
                </div>
                <div class="web-form-body">
                    <div class="web-pane active" data-pane="identidad">
                        <div class="web-section-title"><strong>Nombre y mensaje principal</strong><span>Define cómo se presenta tu negocio antes de mostrar los productos.</span></div>
                        <div class="web-grid">
                            <div class="web-field full"><label>Título de la página <em>Máx. 120</em></label><input type="text" name="titulo" maxlength="120" value="<?= $esc($config['titulo'] ?? $config['nombre'] ?? '') ?>" placeholder="Nombre comercial" <?= $puedeConfigurar ? '' : 'disabled' ?>></div>
                            <div class="web-field full"><label>Texto principal <em>Máx. 255</em></label><input type="text" name="subtitulo" maxlength="255" value="<?= $esc($config['subtitulo'] ?? '') ?>" placeholder="Describe brevemente qué ofreces" <?= $puedeConfigurar ? '' : 'disabled' ?>></div>
                            <div class="web-field full"><label>Descripción del negocio</label><textarea name="descripcion" placeholder="Cuenta qué hace especial a tu negocio..." <?= $puedeConfigurar ? '' : 'disabled' ?>><?= $esc($config['descripcion'] ?? '') ?></textarea></div>
                        </div>
                    </div>
                    <div class="web-pane" data-pane="pagina">
                        <div class="web-section-title"><strong>Dirección y apariencia</strong><span>Configura una URL fácil de compartir y los colores de tu marca.</span></div>
                        <div class="web-grid">
                            <div class="web-field full"><label>Dirección pública <em>3–60 caracteres</em></label><div class="web-slug"><span>/tienda/</span><input type="text" name="slug" id="slugTienda" maxlength="60" value="<?= $esc($slug) ?>" <?= $puedeConfigurar ? '' : 'disabled' ?>></div></div>
                            <div class="web-field full"><label>Color principal</label><div class="web-color"><input type="color" id="colorPicker" value="<?= $esc($config['color_primario'] ?? '#00A46A') ?>" <?= $puedeConfigurar ? '' : 'disabled' ?>><input type="text" name="color_primario" id="colorTexto" maxlength="7" value="<?= $esc($config['color_primario'] ?? '#00A46A') ?>" <?= $puedeConfigurar ? '' : 'disabled' ?>></div><div class="web-swatches"><?php foreach (['#00A46A','#15803D','#2563EB','#7C3AED','#E11D48','#EA580C','#111827'] as $sw): ?><button type="button" class="web-swatch" data-color="<?= $sw ?>" style="background:<?= $sw ?>" <?= $puedeConfigurar ? '' : 'disabled' ?>></button><?php endforeach; ?></div></div>
                        </div>
                    </div>
                    <div class="web-pane" data-pane="contacto">
                        <div class="web-section-title"><strong>Canales de contacto</strong><span>Permite que los visitantes pasen de mirar productos a contactarte.</span></div>
                        <div class="web-grid">
                            <div class="web-field"><label>WhatsApp</label><input type="text" name="whatsapp" value="<?= $esc($config['whatsapp'] ?? '') ?>" placeholder="51987654321" <?= $puedeConfigurar ? '' : 'disabled' ?>></div>
                            <div class="web-field"><label>Instagram</label><input type="text" name="instagram" value="<?= $esc($config['instagram'] ?? '') ?>" placeholder="instagram.com/tuempresa" <?= $puedeConfigurar ? '' : 'disabled' ?>></div>
                            <div class="web-field full"><label>Facebook</label><input type="text" name="facebook" value="<?= $esc($config['facebook'] ?? '') ?>" placeholder="facebook.com/tuempresa" <?= $puedeConfigurar ? '' : 'disabled' ?>></div>
                        </div>
                    </div>
                </div>
                <div class="web-form-footer"><small><i class="fas fa-info-circle"></i> Los productos se administran desde Inventario; aquí decides cómo se muestran en tu web.</small><?php if ($puedeConfigurar): ?><button class="web-btn primary" type="submit" id="btnGuardarForm"><i class="fas fa-save"></i> Guardar cambios</button><?php endif; ?></div>
            </div>

            <aside class="web-panel web-side">
                <div class="web-panel-head"><div><h3>Publicación y visibilidad</h3><p>Controla qué puede ver el público.</p></div></div>
                <div class="web-side-body">
                    <div class="web-publish">
                        <div class="web-publish-copy"><div class="web-publish-mark"><i class="fas fa-globe-americas"></i></div><div><strong>Publicar página</strong><small id="textoPublicacion"><?= $activo ? 'Tu página está visible para tus clientes.' : 'Actívala cuando esté lista para compartir.' ?></small></div></div>
                        <label class="web-switch"><input type="checkbox" name="activo" id="activoTienda" value="1" <?= $activo ? 'checked' : '' ?> <?= $puedeConfigurar ? '' : 'disabled' ?>><span class="web-slider"></span></label>
                    </div>

                    <div class="web-side-block">
                        <div class="web-side-title"><strong>Enlace público</strong><span>Vista real</span></div>
                        <div class="web-live-link"><small>Tu cliente abrirá exactamente esta dirección</small><strong id="urlLateral"><?= $esc($publicUrl) ?></strong><div class="web-live-actions"><button type="button" class="web-btn" id="btnCopiarLateral"><i class="far fa-copy"></i> Copiar</button><button type="button" class="web-btn dark" id="btnAbrirLateral"><i class="fas fa-external-link-alt"></i> Abrir web</button></div></div>
                    </div>

                    <div class="web-side-block">
                        <div class="web-side-title"><strong>Información visible</strong><span>Se guarda con la configuración</span></div>
                        <div class="web-option-list">
                            <?php $opts=[
                                ['mostrar_precios','fa-tags','Precios','Muestra el precio de venta'],
                                ['mostrar_stock','fa-boxes','Stock','Indica disponibilidad'],
                                ['mostrar_codigo','fa-barcode','SKU','Muestra el código del producto'],
                                ['mostrar_descripcion','fa-align-left','Descripción','Detalle dentro de la ficha'],
                                ['mostrar_sin_stock','fa-eye','Agotados','Mantiene productos sin stock'],
                                ['boton_whatsapp','fa-comment-dots','WhatsApp','Botón de consulta por producto'],
                            ]; foreach($opts as [$campo,$icono,$titulo,$detalle]): ?>
                            <div class="web-option"><div class="web-option-copy"><div class="web-option-ico"><i class="fas <?= $esc($icono) ?>"></i></div><div><strong><?= $esc($titulo) ?></strong><small><?= $esc($detalle) ?></small></div></div><label class="web-mini-switch"><input type="checkbox" name="<?= $esc($campo) ?>" value="1" <?= (int)($config[$campo] ?? 1) === 1 ? 'checked' : '' ?> <?= $puedeConfigurar ? '' : 'disabled' ?>><span class="web-mini-slider"></span></label></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </form>

    <div class="web-panel web-products">
        <div class="web-products-top">
            <div class="web-products-title"><h3>Productos de la página</h3><p>La lista se carga por bloques mientras haces scroll para mantener esta pantalla rápida incluso con inventarios grandes.</p></div>
            <div class="web-products-tools">
                <div class="web-search"><i class="fas fa-search"></i><input type="search" id="buscarProductoWeb" placeholder="Buscar producto, SKU o categoría..."></div>
                <div class="web-filters"><button class="web-filter active" type="button" data-filter="todos">Todos</button><button class="web-filter" type="button" data-filter="publicados">Publicados</button><button class="web-filter" type="button" data-filter="ocultos">Ocultos</button><button class="web-filter" type="button" data-filter="destacados">Destacados</button></div>
            </div>
        </div>
        <div class="web-bulk">
            <div class="web-progress-note"><i class="fas fa-bolt"></i><span id="textoCargaProductos">Se mostrarán hasta 24 productos por bloque.</span></div>
            <?php if ($puedeInventario || $puedeConfigurar): ?><button type="button" class="web-btn soft" id="btnPublicarTodos"><i class="fas fa-eye"></i> Publicar todos</button><button type="button" class="web-btn danger-soft" id="btnOcultarTodos"><i class="fas fa-eye-slash"></i> Ocultar todos</button><?php endif; ?>
        </div>
        <div class="web-product-grid" id="productosGridTienda"></div>
        <div class="web-empty" id="sinResultadosTienda" style="display:none"><i class="fas fa-search"></i><strong>No hay productos para mostrar</strong><span>Prueba con otro término o filtro.</span></div>
        <div class="web-load-zone" id="zonaCargaProductos"><div class="web-load-more"><span class="web-spinner"></span><span>Cargando productos...</span></div></div>
        <div id="sentinelaProductos" style="height:1px"></div>
    </div>
</div></section>
</div>

<script>
(function(){
const endpoint='Controllers/TiendaWeb.php';
const csrf=<?= json_encode($csrf) ?>;
const puedeConfigurar=<?= $puedeConfigurar ? 'true' : 'false' ?>;
const puedeProductos=<?= ($puedeInventario || $puedeConfigurar) ? 'true' : 'false' ?>;
const simbolo=<?= json_encode($simbolo) ?>;
const appBase=<?= json_encode($appBase) ?>;
let publicUrl=<?= json_encode($publicUrl) ?>;
const form=document.getElementById('formTiendaWeb');
const estadoCambios=document.getElementById('estadoCambios');
let dirty=false;

function marcarDirty(){if(!puedeConfigurar)return;dirty=true;if(estadoCambios){estadoCambios.classList.add('dirty');estadoCambios.innerHTML='<i class="fas fa-circle"></i> Cambios sin guardar';}}
function marcarGuardado(){dirty=false;if(estadoCambios){estadoCambios.classList.remove('dirty');estadoCambios.innerHTML='<i class="fas fa-check-circle"></i> Guardado';}}
function toast(icon,title){if(window.Swal){Swal.fire({toast:true,position:'top-end',icon,title,showConfirmButton:false,timer:2200,timerProgressBar:true});}else{console.log(title);}}
function slugify(value){return (value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,60);}
function recalcularUrl(){const input=document.getElementById('slugTienda');const slug=slugify(input?.value)||'mi-tienda';if(input&&input.value!==slug) input.value=slug;const base=publicUrl.replace(/\/tienda\/[^/?#]+(?:[?#].*)?$/,'/tienda/');publicUrl=base+encodeURIComponent(slug);['urlPublicaTexto','urlLateral'].forEach(id=>{const el=document.getElementById(id);if(el)el.textContent=publicUrl;});}

form?.querySelectorAll('input,textarea').forEach(el=>{el.addEventListener('input',()=>{marcarDirty();if(el.id==='slugTienda')recalcularUrl();});el.addEventListener('change',marcarDirty);});
document.querySelectorAll('.web-tab').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('.web-tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.web-pane').forEach(x=>x.classList.remove('active'));btn.classList.add('active');document.querySelector('[data-pane="'+btn.dataset.tab+'"]')?.classList.add('active');}));
const colorPicker=document.getElementById('colorPicker'),colorTexto=document.getElementById('colorTexto');
colorPicker?.addEventListener('input',()=>{colorTexto.value=colorPicker.value.toUpperCase();marcarDirty();});
colorTexto?.addEventListener('input',()=>{if(/^#[0-9a-f]{6}$/i.test(colorTexto.value)){colorPicker.value=colorTexto.value;}});
document.querySelectorAll('.web-swatch').forEach(btn=>btn.addEventListener('click',()=>{if(btn.disabled)return;colorPicker.value=btn.dataset.color;colorTexto.value=btn.dataset.color;marcarDirty();}));

async function guardarConfiguracion({silencioso=false}={}){
  if(!puedeConfigurar||!form)return false;
  const data=new FormData(form);data.append('csrf',csrf);
  const buttons=[document.getElementById('btnGuardarTop'),document.getElementById('btnGuardarForm')].filter(Boolean);buttons.forEach(b=>b.disabled=true);
  try{
    const res=await fetch(endpoint+'?op=guardar_configuracion',{method:'POST',body:data,credentials:'same-origin'});const json=await res.json();
    if(!json.success)throw new Error(json.mensaje||'No se pudo guardar.');
    marcarGuardado();
    const cfg=json.config||{};const activo=Number(cfg.activo||0)===1;const estado=document.getElementById('estadoTienda');if(estado){estado.classList.toggle('off',!activo);estado.querySelector('span:last-child').textContent=activo?'Publicada':'Sin publicar';}const txt=document.getElementById('textoPublicacion');if(txt)txt.textContent=activo?'Tu página está visible para tus clientes.':'Actívala cuando esté lista para compartir.';const ver=document.getElementById('btnVerWeb');if(ver){ver.classList.toggle('dark',activo);ver.classList.toggle('primary',!activo);ver.innerHTML=activo?'<i class="fas fa-external-link-alt"></i> <span>Ver mi web</span>':'<i class="fas fa-rocket"></i> <span>Publicar y abrir</span>';}
    if(cfg.slug){const url=new URL(publicUrl,window.location.href);url.pathname=url.pathname.replace(/\/tienda\/[^/]+$/,'/tienda/'+encodeURIComponent(cfg.slug));publicUrl=url.toString();['urlPublicaTexto','urlLateral'].forEach(id=>{const el=document.getElementById(id);if(el)el.textContent=publicUrl;});}
    if(!silencioso)toast('success','Cambios guardados');return true;
  }catch(e){if(window.Swal)Swal.fire({icon:'error',title:'No se pudo guardar',text:e.message});else alert(e.message);return false;}finally{buttons.forEach(b=>b.disabled=false);}
}
form?.addEventListener('submit',e=>{e.preventDefault();guardarConfiguracion();});

async function copiarEnlace(){try{await navigator.clipboard.writeText(publicUrl);toast('success','Enlace copiado');}catch(e){const ta=document.createElement('textarea');ta.value=publicUrl;document.body.appendChild(ta);ta.select();document.execCommand('copy');ta.remove();toast('success','Enlace copiado');}}
['btnCopiarEnlace','btnCopiarMini','btnCopiarLateral'].forEach(id=>document.getElementById(id)?.addEventListener('click',copiarEnlace));
function abrirWeb(){window.open(publicUrl,'_blank','noopener');}
['btnVerWeb','btnAbrirMini','btnAbrirLateral'].forEach(id=>document.getElementById(id)?.addEventListener('click',async()=>{if(id==='btnVerWeb'){const activo=document.getElementById('activoTienda');if(activo&&!activo.checked&&puedeConfigurar){activo.checked=true;marcarDirty();}if(dirty){const ok=await guardarConfiguracion({silencioso:true});if(!ok)return;}}abrirWeb();}));

// Productos: paginación incremental real
const grid=document.getElementById('productosGridTienda'),empty=document.getElementById('sinResultadosTienda'),loading=document.getElementById('zonaCargaProductos'),sentinel=document.getElementById('sentinelaProductos'),search=document.getElementById('buscarProductoWeb'),loadText=document.getElementById('textoCargaProductos');
let offset=0,limit=24,total=0,hasMore=true,isLoading=false,currentFilter='todos',currentSearch='',requestToken=0,searchTimer=null;
function money(n){return simbolo+' '+Number(n||0).toFixed(2);}
function createEl(tag,cls,text){const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;}
function productoCard(p){
  const card=createEl('div','web-product-card');card.dataset.id=p.idarticulo;
  const imgBox=createEl('div','web-product-img');if(p.imagen_url){const img=document.createElement('img');img.src=p.imagen_url;img.alt=p.nombre||'';img.loading='lazy';img.onerror=()=>{img.remove();imgBox.innerHTML='<i class="fas fa-box"></i>';};imgBox.appendChild(img);}else{imgBox.innerHTML='<i class="fas fa-box"></i>';}
  const copy=createEl('div','web-product-copy');copy.appendChild(createEl('div','web-product-cat',p.categoria||'Catálogo'));copy.appendChild(createEl('div','web-product-name',p.nombre||'Producto'));
  const meta=createEl('div','web-product-meta');const priceMin=Number(p.precio_min||0),priceMax=Number(p.precio_max||0);const price=priceMax>priceMin+.001?money(priceMin)+' – '+money(priceMax):money(priceMin);const sku=createEl('span','');sku.innerHTML='<b>SKU</b> '+(p.codigo||'—');const stock=createEl('span','');stock.innerHTML='<b>Stock</b> '+Number(p.stock_mostrado||0).toFixed(0);const pr=createEl('span','');pr.innerHTML='<b>'+price+'</b>';meta.append(sku,stock,pr);copy.appendChild(meta);
  const actions=createEl('div','web-product-actions');const star=createEl('button','web-star'+(Number(p.destacado)===1?' on':''));star.type='button';star.title='Destacar producto';star.innerHTML='<i class="fas fa-star"></i>';
  const pubWrap=createEl('label','web-pub-toggle');const pubText=createEl('span','',Number(p.publicado)===1?'Visible':'Oculto');const sw=createEl('span','web-mini-switch');const check=document.createElement('input');check.type='checkbox';check.checked=Number(p.publicado)===1;const slider=createEl('span','web-mini-slider');sw.append(check,slider);pubWrap.append(pubText,sw);
  if(!puedeProductos){star.disabled=true;check.disabled=true;}
  async function save(pub,featured){star.disabled=true;check.disabled=true;try{const fd=new FormData();fd.append('csrf',csrf);fd.append('idarticulo',p.idarticulo);fd.append('publicado',pub?1:0);fd.append('destacado',featured?1:0);const r=await fetch(endpoint+'?op=guardar_producto',{method:'POST',body:fd,credentials:'same-origin'});const j=await r.json();if(!j.success)throw new Error(j.mensaje||'No se pudo actualizar.');p.publicado=pub?1:0;p.destacado=featured?1:0;pubText.textContent=pub?'Visible':'Oculto';star.classList.toggle('on',featured);refreshResumen();}catch(e){check.checked=Number(p.publicado)===1;star.classList.toggle('on',Number(p.destacado)===1);toast('error',e.message);}finally{star.disabled=!puedeProductos;check.disabled=!puedeProductos;}}
  check.addEventListener('change',()=>save(check.checked,Number(p.destacado)===1));star.addEventListener('click',()=>save(Number(p.publicado)===1,Number(p.destacado)!==1));actions.append(star,pubWrap);card.append(imgBox,copy,actions);return card;
}
async function cargarProductos(reset=false){
  if(isLoading||(!hasMore&&!reset))return;if(reset){offset=0;total=0;hasMore=true;grid.innerHTML='';empty.style.display='none';requestToken++;}
  const token=requestToken;isLoading=true;loading.style.display='block';
  try{const qs=new URLSearchParams({op:'listar_productos',limit:String(limit),offset:String(offset),buscar:currentSearch,filtro:currentFilter});const r=await fetch(endpoint+'?'+qs.toString(),{credentials:'same-origin'});const j=await r.json();if(token!==requestToken)return;if(!j.success)throw new Error(j.mensaje||'No se pudieron cargar los productos.');if(j.total!==null&&j.total!==undefined)total=Number(j.total||0);(j.data||[]).forEach(p=>grid.appendChild(productoCard(p)));offset+=Array.isArray(j.data)?j.data.length:0;hasMore=Boolean(j.has_more);empty.style.display=total===0?'block':'none';loadText.textContent=total?('Mostrando '+offset+' de '+total+' productos. El resto se carga automáticamente al bajar.'):'No hay productos con este filtro.';loading.style.display=hasMore?'block':'none';}
  catch(e){if(token===requestToken){loading.style.display='none';toast('error',e.message);}}finally{isLoading=false;}
}
const observer=new IntersectionObserver(entries=>{if(entries.some(e=>e.isIntersecting)&&hasMore&&!isLoading)cargarProductos(false);},{rootMargin:'450px 0px'});if(sentinel)observer.observe(sentinel);
search?.addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>{currentSearch=search.value.trim();cargarProductos(true);},320);});
document.querySelectorAll('.web-filter').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('.web-filter').forEach(x=>x.classList.remove('active'));btn.classList.add('active');currentFilter=btn.dataset.filter||'todos';cargarProductos(true);}));
async function refreshResumen(){try{const r=await fetch(endpoint+'?op=resumen_productos',{credentials:'same-origin'});const j=await r.json();if(!j.success)return;const x=j.resumen||{};[['metricTotal','total'],['metricPublicados','publicados'],['metricDestacados','destacados'],['metricCategorias','categorias']].forEach(([id,k])=>{const el=document.getElementById(id);if(el)el.textContent=Number(x[k]||0);});}catch(e){}}
async function bulk(publicar){if(!puedeProductos)return;let ok=true;if(window.Swal){const r=await Swal.fire({icon:'question',title:publicar?'Publicar todos':'Ocultar todos',text:publicar?'Los productos activos quedarán visibles en tu web.':'Todos los productos dejarán de mostrarse públicamente.',showCancelButton:true,confirmButtonText:publicar?'Publicar todos':'Ocultar todos',cancelButtonText:'Cancelar',confirmButtonColor:publicar?'#00a46a':'#9d4f49'});ok=r.isConfirmed;}else ok=confirm(publicar?'¿Publicar todos?':'¿Ocultar todos?');if(!ok)return;try{const fd=new FormData();fd.append('csrf',csrf);fd.append('publicado',publicar?1:0);const r=await fetch(endpoint+'?op=publicar_todos',{method:'POST',body:fd,credentials:'same-origin'});const j=await r.json();if(!j.success)throw new Error(j.mensaje||'No se pudo completar.');toast('success',publicar?'Productos publicados':'Productos ocultados');await refreshResumen();cargarProductos(true);}catch(e){toast('error',e.message);}}
document.getElementById('btnPublicarTodos')?.addEventListener('click',()=>bulk(true));document.getElementById('btnOcultarTodos')?.addEventListener('click',()=>bulk(false));
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
cargarProductos(true);
})();
</script>
<?php require 'footer.php'; ob_end_flush(); ?>
