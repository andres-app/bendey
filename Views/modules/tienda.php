<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/Models/TiendaWeb.php';
require_once dirname(__DIR__, 2) . '/Libraries/MediaStorage.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$modelTienda = new TiendaWeb(false);
$catalogo = $slug !== '' ? $modelTienda->obtenerCatalogoPublicoInicial($slug, 24) : null;

$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$appBase = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
if ($appBase === '/' || $appBase === '.') {
    $appBase = '';
}

if (!$catalogo) {
    http_response_code(404);
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Catálogo no disponible</title><style>body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;background:#f6f8f7;color:#25312c;display:grid;place-items:center;min-height:100vh}.box{max-width:460px;margin:20px;padding:34px;text-align:center;background:#fff;border:1px solid #e4e9e6;border-radius:22px;box-shadow:0 18px 45px rgba(15,23,42,.08)}.mark{width:58px;height:58px;margin:0 auto 15px;border-radius:16px;background:#eaf7f1;color:#0f7f57;display:grid;place-items:center;font-size:26px}h1{font-size:20px;margin:0 0 8px}p{margin:0;color:#738079;line-height:1.55;font-size:14px}</style></head><body><div class="box"><div class="mark">◫</div><h1>Catálogo no disponible</h1><p>Esta página no existe o todavía no ha sido publicada por el negocio.</p></div></body></html><?php
    exit;
}

$config = $catalogo['config'];
$productos = is_array($catalogo['productos'] ?? null) ? $catalogo['productos'] : [];
$categorias = is_array($catalogo['categorias'] ?? null) ? $catalogo['categorias'] : [];
$categoriasDetalle = is_array($catalogo['categorias_detalle'] ?? null) ? $catalogo['categorias_detalle'] : [];
$productCount = (int)($catalogo['total'] ?? count($productos));
$hasMoreInitial = (bool)($catalogo['has_more'] ?? false);
$pageLimit = (int)($catalogo['limit'] ?? 24);
$apiUrl = $appBase . '/Controllers/TiendaPublica.php';
$color = preg_match('/^#[0-9A-F]{6}$/i', (string)($config['color_primario'] ?? '')) ? (string)$config['color_primario'] : '#00A46A';
$nombre = trim((string)($config['titulo'] ?? '')) ?: trim((string)($config['empresa_nombre'] ?? 'Mi tienda'));
$subtitulo = trim((string)($config['subtitulo'] ?? '')) ?: 'Productos y precios actualizados.';
$descripcion = trim((string)($config['descripcion'] ?? ''));
$simbolo = trim((string)($config['simbolo'] ?? 'S/')) ?: 'S/';
$logo = tiquepos_media_url('company', (string)($config['logo'] ?? ''));
$logo = $logo !== '' ? $appBase . '/' . ltrim($logo, '/') : $appBase . '/Assets/img/tiquepos_logo.png';
$whatsapp = preg_replace('/\D+/', '', (string)($config['whatsapp'] ?? '')) ?? '';
$mostrarPrecios = (int)($config['mostrar_precios'] ?? 1) === 1;
$mostrarCodigo = (int)($config['mostrar_codigo'] ?? 1) === 1;
$mostrarStock = (int)($config['mostrar_stock'] ?? 1) === 1;
$mostrarDescripcion = (int)($config['mostrar_descripcion'] ?? 1) === 1;
$mostrarWhatsapp = $whatsapp !== '' && (int)($config['boton_whatsapp'] ?? 1) === 1;

$imageFor = static function (array $p) use ($appBase): array {
    $raw = trim((string)($p['imagen'] ?? ''));
    if ($raw === '') {
        return ['', false];
    }
    $url = tiquepos_media_url('products', $raw);
    if ($url === '') {
        return ['', false];
    }
    return [$appBase . '/' . ltrim($url, '/'), true];
};

$featured = [];
foreach ($productos as $p) {
    if ((int)($p['destacado'] ?? 0) === 1) {
        $featured[] = $p;
    }
}
if (!$featured) {
    $featured = array_slice($productos, 0, min(4, count($productos)));
}

$categoryCards = [];
foreach ($categoriasDetalle as $catRow) {
    $catName = trim((string)($catRow['nombre'] ?? '')) ?: 'General';
    $rawImage = trim((string)($catRow['imagen'] ?? ''));
    $catImage = '';
    $catHasImage = false;
    if ($rawImage !== '') {
        $url = tiquepos_media_url('products', $rawImage);
        if ($url !== '') {
            $catImage = $appBase . '/' . ltrim($url, '/');
            $catHasImage = true;
        }
    }
    $categoryCards[] = [
        'nombre' => $catName,
        'cantidad' => (int)($catRow['cantidad'] ?? 0),
        'imagen' => $catImage,
        'has_image' => $catHasImage,
    ];
}
$categoryCards = array_slice($categoryCards, 0, 8);
$heroProduct = $featured[0] ?? ($productos[0] ?? null);

$buildPrice = static function (array $p) use ($simbolo): string {
    $min = (float)($p['precio_min'] ?? 0);
    $max = (float)($p['precio_max'] ?? 0);
    $price = $simbolo . ' ' . number_format($min, 2);
    if ($max > $min + 0.001) {
        $price .= ' – ' . $simbolo . ' ' . number_format($max, 2);
    }
    return $price;
};

$heroImage = '';
$heroHasImage = false;
if ($heroProduct) {
    [$heroImage, $heroHasImage] = $imageFor($heroProduct);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= $esc($nombre) ?> · Tienda</title>
<meta name="description" content="<?= $esc($subtitulo) ?>">
<meta name="theme-color" content="<?= $esc($color) ?>">
<style>
:root{--brand:<?= $esc($color) ?>;--deep:#16342a;--deep-2:#0e281f;--ink:#17221e;--muted:#6f7c76;--cream:#f4efe7;--soft:#f7f8f5;--line:#e4e9e6;--white:#fff;--shadow:0 20px 55px rgba(20,35,28,.10);--shadow-sm:0 8px 24px rgba(20,35,28,.06)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink);background:#fff}a{text-decoration:none;color:inherit}button,input{font:inherit}button{cursor:pointer}img{display:block;max-width:100%}.wrap{width:min(1280px,calc(100% - 40px));margin:auto}
.promo{background:var(--deep-2);color:#fff}.promo .wrap{min-height:36px;display:grid;grid-template-columns:repeat(3,1fr);align-items:center;text-align:center;gap:10px;font-size:11px}.promo span{opacity:.94}.promo b{font-weight:800}
.header{background:#fff;border-bottom:1px solid var(--line)}.header-main{min-height:82px;display:grid;grid-template-columns:240px minmax(360px,1fr) 330px;gap:24px;align-items:center}.brand{display:flex;align-items:center;gap:12px;min-width:0}.brand-logo{width:48px;height:48px;border-radius:15px;border:1px solid var(--line);background:#fff;display:grid;place-items:center;overflow:hidden}.brand-logo img{width:100%;height:100%;object-fit:contain}.brand-text{min-width:0}.brand-text strong{display:block;font-size:18px;letter-spacing:-.02em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.brand-text small{display:block;margin-top:2px;color:var(--muted);font-size:11px}.search{position:relative;width:100%}.search input{width:100%;height:46px;border:1px solid #dfe4e1;border-radius:999px;background:#f7f8f6;padding:0 50px 0 18px;outline:none;font-size:13px;transition:.18s}.search input:focus{background:#fff;border-color:var(--brand);box-shadow:0 0 0 4px rgba(18,132,86,.08)}.search button{position:absolute;right:5px;top:5px;width:36px;height:36px;border:0;border-radius:50%;background:transparent;color:#4d5a54;font-size:18px}.header-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px}.head-action{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:12px;color:#38453f;font-size:11px;font-weight:750}.head-action .icon{width:34px;height:34px;border-radius:50%;border:1px solid var(--line);display:grid;place-items:center;background:#fff;font-size:16px}.head-action.primary .icon{background:var(--deep);color:#fff;border-color:var(--deep)}.head-action strong{display:block;font-size:11px}.head-action small{display:block;font-size:9px;color:#87928d;margin-top:1px}
.navbar{background:#fff;border-bottom:1px solid var(--line)}.nav-inner{min-height:50px;display:flex;align-items:center;justify-content:center;gap:4px}.nav-inner a{padding:9px 15px;border-radius:999px;color:#405047;font-size:12px;font-weight:800}.nav-inner a:hover{background:#f0f6f2;color:#0d7650}
.hero{background:#f7f3ec;padding:22px 0}.hero-card{position:relative;overflow:hidden;min-height:470px;border-radius:0;background:linear-gradient(90deg,#f4efe7 0%,#f4efe7 48%,#e6ebdf 48%,#e6ebdf 100%);display:grid;grid-template-columns:1fr 1.08fr}.hero-left{padding:62px 50px 52px 34px;position:relative;z-index:2}.kicker{font-size:11px;letter-spacing:.34em;text-transform:uppercase;color:#526158;font-weight:800}.hero h1{max-width:550px;margin:16px 0 14px;font-size:clamp(42px,5.5vw,72px);line-height:.96;letter-spacing:-.055em;font-weight:850;color:#11271f}.hero p{max-width:520px;margin:0;color:#59675f;font-size:16px;line-height:1.7}.hero-cta{display:inline-flex;align-items:center;gap:12px;margin-top:24px;padding:0 22px;height:48px;border-radius:16px;background:var(--deep);color:#fff;font-size:13px;font-weight:850;box-shadow:0 12px 24px rgba(15,42,31,.16)}.hero-points{display:flex;gap:18px;flex-wrap:wrap;margin-top:30px}.hero-point{display:flex;align-items:center;gap:9px;min-width:132px}.hero-point .dot{width:32px;height:32px;border-radius:50%;background:#fff;display:grid;place-items:center;box-shadow:var(--shadow-sm);color:var(--deep);font-weight:900}.hero-point span{font-size:11px;color:#4d5c53;font-weight:750;line-height:1.25}
.hero-right{position:relative;min-height:470px;overflow:hidden}.hero-right:before{content:"";position:absolute;width:330px;height:330px;border-radius:50%;background:#f6d7b7;right:36px;top:54px;opacity:.8}.hero-right:after{content:"";position:absolute;width:160px;height:160px;border-radius:50%;background:#c9dfce;left:42px;bottom:32px;opacity:.9}.hero-product{position:absolute;z-index:2;right:56px;bottom:0;width:min(430px,66%);height:420px;display:flex;align-items:flex-end;justify-content:center}.hero-product.has-image img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 24px 24px rgba(30,50,40,.18))}.hero-fallback{width:320px;height:330px;border-radius:80px 80px 36px 36px;background:linear-gradient(160deg,#fff,#eadfce);display:grid;place-items:center;box-shadow:0 30px 60px rgba(50,58,46,.16);position:relative}.hero-fallback:before{content:"";width:128px;height:110px;border-radius:18px;background:#8da69a;box-shadow:0 -36px 0 -18px #8da69a}.hero-fallback:after{content:"SIN IMAGEN";position:absolute;bottom:24px;font-size:10px;letter-spacing:.18em;color:#6d776f;font-weight:800}.hero-note{position:absolute;z-index:3;right:22px;top:84px;width:150px;padding:22px 16px;background:rgba(255,255,255,.84);border-radius:24px;box-shadow:var(--shadow);transform:rotate(-4deg);text-align:center}.hero-note strong{display:block;font-family:Georgia,serif;font-size:22px;line-height:1.05;color:#28352f}.hero-note small{display:block;margin-top:10px;color:#758179;font-size:10px}.hero-mini-card{position:absolute;z-index:3;left:28px;top:58px;width:170px;padding:14px;border-radius:18px;background:rgba(255,255,255,.9);box-shadow:var(--shadow-sm)}.hero-mini-card span{display:block;color:#849088;font-size:10px}.hero-mini-card strong{display:block;margin-top:5px;font-size:14px}.hero-mini-card em{display:block;margin-top:8px;font-style:normal;color:var(--brand);font-weight:850;font-size:13px}
.category-section{padding:18px 0 28px;background:#fff}.category-row{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:14px}.category-btn{border:0;background:transparent;text-align:center;padding:0}.cat-circle{width:82px;height:82px;margin:auto;border-radius:50%;background:#f2f2ee;overflow:hidden;display:grid;place-items:center;border:1px solid #edf0ed;transition:.2s}.cat-circle img{width:100%;height:100%;object-fit:cover}.cat-circle .fallback{font-size:26px;color:#7b8b81}.category-btn:hover .cat-circle{transform:translateY(-3px);box-shadow:var(--shadow-sm)}.category-btn strong{display:block;margin-top:9px;font-size:11px;line-height:1.2}.category-btn small{display:block;margin-top:2px;font-size:9px;color:#87928d}
.section{padding:20px 0}.section-head{display:flex;align-items:end;justify-content:space-between;gap:16px;margin-bottom:14px}.section-head h2{margin:0;font-size:22px;letter-spacing:-.03em}.section-head p{margin:4px 0 0;color:var(--muted);font-size:11px}.section-head a{font-size:11px;color:#44524b;font-weight:800}.products{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.product-card{background:#fff}.product-image{position:relative;aspect-ratio:1.18/1;border-radius:16px;overflow:hidden;background:#f1eee8;cursor:pointer}.product-image img{width:100%;height:100%;object-fit:cover;transition:.25s}.product-card:hover .product-image img{transform:scale(1.025)}.product-placeholder{width:100%;height:100%;display:grid;place-items:center;background:linear-gradient(145deg,#eee7db,#e0e8df);color:#66786d}.product-placeholder .shape{width:88px;height:76px;border-radius:14px;background:#91a99c;box-shadow:0 -26px 0 -14px #91a99c}.heart{position:absolute;top:10px;right:10px;width:30px;height:30px;border-radius:50%;border:1px solid rgba(255,255,255,.9);background:rgba(255,255,255,.92);display:grid;place-items:center;font-size:15px;color:#405047}.badge{position:absolute;left:10px;top:10px;padding:6px 9px;border-radius:999px;background:rgba(255,255,255,.94);font-size:9px;font-weight:850;color:#374740}.product-info{padding:10px 2px 2px}.product-cat{font-size:9px;text-transform:uppercase;color:#789084;font-weight:800;letter-spacing:.06em}.product-name{margin-top:5px;font-size:13px;font-weight:800;line-height:1.3;min-height:34px}.product-meta{display:flex;justify-content:space-between;gap:10px;align-items:end;margin-top:8px}.price{font-size:15px;font-weight:900;color:#15271f}.stock{font-size:9px;color:#7e8b84}.stock.out{color:#b85a5a}.sku{margin-top:6px;font-size:9px;color:#9aa39e}.card-actions{display:flex;gap:8px;margin-top:10px}.card-actions button{flex:1;height:34px;border-radius:10px;border:1px solid #dfe5e1;background:#fff;color:#45534b;font-size:10px;font-weight:800}.card-actions button.primary{background:var(--deep);border-color:var(--deep);color:#fff}
.promo-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.promo-card{position:relative;min-height:210px;border-radius:18px;overflow:hidden;padding:26px;background:#eeeae1}.promo-card.pink{background:#f1d9d7}.promo-card.green{background:#dceadf}.promo-card h3{position:relative;z-index:2;width:48%;margin:0;font-size:30px;line-height:1;letter-spacing:-.04em}.promo-card p{position:relative;z-index:2;width:50%;margin:10px 0 0;color:#5e6b63;font-size:12px;line-height:1.5}.promo-card a{position:relative;z-index:2;display:inline-flex;margin-top:15px;padding:9px 13px;border-radius:999px;background:var(--deep);color:#fff;font-size:10px;font-weight:800}.promo-art{position:absolute;right:0;top:0;width:48%;height:100%;display:grid;place-items:center}.promo-art.has-image img{width:100%;height:100%;object-fit:cover}.promo-art .blob{width:145px;height:145px;border-radius:50%;background:rgba(255,255,255,.75);display:grid;place-items:center;box-shadow:var(--shadow-sm)}.promo-art .blob:before{content:"";width:70px;height:64px;border-radius:10px;background:#8aa194;box-shadow:0 -22px 0 -12px #8aa194}
.browse{padding:24px 0 30px}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}.filters{display:flex;gap:7px;overflow:auto;scrollbar-width:none}.filters::-webkit-scrollbar{display:none}.filters button{flex:0 0 auto;height:34px;padding:0 12px;border-radius:999px;border:1px solid var(--line);background:#fff;color:#526059;font-size:10px;font-weight:800}.filters button.active{background:var(--deep);border-color:var(--deep);color:#fff}.browse-search{position:relative;width:min(340px,100%)}.browse-search input{width:100%;height:40px;border:1px solid var(--line);border-radius:999px;padding:0 42px 0 14px;outline:none}.browse-search span{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#839087}.empty{display:none;padding:50px 20px;text-align:center;border:1px dashed #d8e0dc;border-radius:18px;color:#728078}.empty strong{display:block;color:#34413b;margin-bottom:6px}.catalog-loader{display:flex;align-items:center;justify-content:center;gap:9px;min-height:58px;color:#77847d;font-size:11px}.catalog-loader.hidden{display:none}.catalog-spinner{width:20px;height:20px;border-radius:50%;border:2px solid #dfe6e2;border-top-color:var(--brand);animation:catalogSpin .7s linear infinite}@keyframes catalogSpin{to{transform:rotate(360deg)}}.catalog-progress{text-align:center;color:#8a958f;font-size:10px;margin-top:6px}
.trust{padding:18px 0 28px}.trust-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:1px;border:1px solid var(--line);border-radius:18px;overflow:hidden;background:var(--line)}.trust-item{background:#fbfcfa;padding:18px 16px;display:flex;gap:10px}.trust-icon{width:34px;height:34px;border-radius:50%;border:1px solid #dfe6e2;display:grid;place-items:center;flex:0 0 34px}.trust-item strong{display:block;font-size:11px}.trust-item small{display:block;margin-top:3px;color:#87928d;font-size:9px;line-height:1.35}
.about{padding:28px 0;background:#f7f5ef}.about-grid{display:grid;grid-template-columns:1.25fr .75fr;gap:30px}.about h3{margin:0 0 10px;font-size:24px;letter-spacing:-.03em}.about p{margin:0;color:#647169;font-size:13px;line-height:1.7}.contact-box{display:grid;grid-template-columns:1fr 1fr;gap:9px}.contact-item{padding:13px;border-radius:14px;background:#fff;border:1px solid #e5e8e4}.contact-item strong{display:block;font-size:10px;text-transform:uppercase;color:#859089}.contact-item span{display:block;margin-top:4px;font-size:11px}.socials{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.socials a{padding:9px 12px;border-radius:999px;background:#fff;border:1px solid var(--line);font-size:10px;font-weight:800}
.footer{background:#132a22;color:#d7e4dd;padding:28px 0 16px}.footer-grid{display:grid;grid-template-columns:1.3fr repeat(3,.8fr);gap:30px}.footer-brand{display:flex;align-items:center;gap:11px}.footer-brand img{width:46px;height:46px;border-radius:13px;background:#fff;object-fit:contain}.footer h4{margin:0 0 10px;color:#fff;font-size:12px}.footer p,.footer a,.footer li{font-size:10px;color:rgba(255,255,255,.72);line-height:1.7}.footer ul{list-style:none;margin:0;padding:0}.footer-bottom{margin-top:22px;padding-top:13px;border-top:1px solid rgba(255,255,255,.08);display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:9px;color:rgba(255,255,255,.55)}
.modal{position:fixed;inset:0;z-index:100;display:none;place-items:center;padding:18px;background:rgba(11,24,18,.58);backdrop-filter:blur(5px)}.modal.open{display:grid}.modal-card{width:min(900px,100%);max-height:min(88vh,780px);overflow:auto;border-radius:22px;background:#fff;box-shadow:0 28px 80px rgba(0,0,0,.28)}.modal-grid{display:grid;grid-template-columns:.92fr 1.08fr}.modal-media{min-height:420px;background:#f2efe8;display:grid;place-items:center}.modal-media img{width:100%;height:100%;min-height:420px;object-fit:cover}.modal-fallback{width:120px;height:105px;border-radius:18px;background:#91a99c;box-shadow:0 -38px 0 -20px #91a99c}.modal-body{position:relative;padding:28px}.close{position:absolute;right:14px;top:14px;width:36px;height:36px;border:1px solid var(--line);border-radius:10px;background:#fff;color:#66736c}.modal-cat{color:var(--brand);font-size:10px;font-weight:850;text-transform:uppercase}.modal h3{margin:8px 44px 8px 0;font-size:25px;letter-spacing:-.03em}.modal-code{color:#8a958f;font-size:11px}.modal-price{margin:18px 0 10px;font-size:27px;font-weight:900}.modal-desc{color:#69776f;font-size:12px;line-height:1.65}.variants{margin-top:17px}.variants h4{margin:0 0 9px;font-size:11px;text-transform:uppercase;color:#65716b}.variant{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--line)}.variant strong{display:block;font-size:12px}.variant small{display:block;margin-top:2px;color:#86918b;font-size:10px}.modal-action{height:42px;margin-top:16px;padding:0 16px;border:0;border-radius:12px;background:var(--deep);color:#fff;font-size:11px;font-weight:850}
@media(max-width:1100px){.promo .wrap{grid-template-columns:1fr}.promo span:nth-child(n+2){display:none}.header-main{grid-template-columns:1fr;gap:12px;padding:14px 0}.header-actions{justify-content:flex-start}.hero-card{grid-template-columns:1fr}.hero-right{min-height:390px}.category-row{grid-template-columns:repeat(4,1fr)}.products{grid-template-columns:repeat(3,1fr)}.trust-grid{grid-template-columns:repeat(3,1fr)}.footer-grid,.about-grid{grid-template-columns:1fr 1fr}}
@media(max-width:760px){.wrap{width:min(100%,calc(100% - 20px))}.nav-inner{justify-content:flex-start;overflow:auto}.hero-left{padding:38px 24px}.hero-card{min-height:0}.hero-right{min-height:330px}.hero-product{right:20px;width:65%;height:300px}.hero-note{right:12px;top:28px;width:120px}.hero-mini-card{left:12px;top:24px;width:140px}.category-row{grid-template-columns:repeat(4,1fr);gap:10px}.cat-circle{width:64px;height:64px}.products{grid-template-columns:repeat(2,1fr)}.promo-grid{grid-template-columns:1fr}.trust-grid{grid-template-columns:1fr 1fr}.footer-grid,.about-grid{grid-template-columns:1fr}.header-actions .head-action:nth-child(1){display:none}}
@media(max-width:520px){.header-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}.head-action{justify-content:center}.hero h1{font-size:42px}.hero-right{min-height:290px}.hero-note{display:none}.hero-mini-card{top:18px}.category-row{grid-template-columns:repeat(3,1fr)}.products{grid-template-columns:1fr 1fr;gap:10px}.product-name{font-size:12px}.promo-card h3,.promo-card p{width:70%}.trust-grid{grid-template-columns:1fr}.modal-grid{grid-template-columns:1fr}.modal-media,.modal-media img{min-height:280px}.contact-box{grid-template-columns:1fr}.hero-points{gap:10px}.hero-point{min-width:110px}.promo-card{min-height:190px}}
</style>
</head>
<body>
<div class="promo"><div class="wrap"><span>✦ <b>Catálogo actualizado</b> con tus datos reales</span><span>↻ <b>Precios y stock</b> sincronizados con TiquePOS</span><span>✓ <b>Compra informada</b> y contacto directo</span></div></div>

<header class="header">
    <div class="wrap header-main">
        <a href="#inicio" class="brand">
            <span class="brand-logo"><img src="<?= $esc($logo) ?>" alt="<?= $esc($nombre) ?>"></span>
            <span class="brand-text"><strong><?= $esc($nombre) ?></strong><small>Catálogo en línea</small></span>
        </a>
        <div class="search">
            <input type="search" id="buscarHero" placeholder="Buscar productos, categorías o códigos...">
            <button type="button" aria-label="Buscar">⌕</button>
        </div>
        <div class="header-actions">
            <a class="head-action" href="#contacto"><span class="icon">♙</span><span><strong>Contacto</strong><small>Datos del negocio</small></span></a>
            <a class="head-action" href="#destacados"><span class="icon">♡</span><span><strong>Destacados</strong><small>Lo más visible</small></span></a>
            <?php if ($mostrarWhatsapp): ?><a class="head-action primary" href="https://wa.me/<?= $esc($whatsapp) ?>" target="_blank" rel="noopener"><span class="icon">◉</span><span><strong>WhatsApp</strong><small>Consultar ahora</small></span></a><?php endif; ?>
        </div>
    </div>
</header>
<nav class="navbar"><div class="wrap nav-inner"><a href="#inicio">Inicio</a><a href="#productos">Tienda</a><a href="#destacados">Destacados</a><a href="#categorias">Categorías</a><a href="#contacto">Nosotros</a></div></nav>

<main id="inicio">
<section class="hero">
    <div class="wrap">
        <div class="hero-card">
            <div class="hero-left">
                <div class="kicker">Vive mejor cada día</div>
                <h1><?= $esc($nombre) ?></h1>
                <p><?= $esc($subtitulo) ?></p>
                <a class="hero-cta" href="#productos">Ver productos <span>→</span></a>
                <div class="hero-points">
                    <div class="hero-point"><span class="dot">✓</span><span>Catálogo<br>actualizado</span></div>
                    <div class="hero-point"><span class="dot">♡</span><span>Productos<br>seleccionados</span></div>
                    <div class="hero-point"><span class="dot">↗</span><span>Consulta<br>directa</span></div>
                </div>
            </div>
            <div class="hero-right">
                <?php if ($heroProduct): ?>
                    <div class="hero-mini-card"><span>Producto destacado</span><strong><?= $esc($heroProduct['nombre'] ?? '') ?></strong><?php if ($mostrarPrecios): ?><em><?= $esc($buildPrice($heroProduct)) ?></em><?php endif; ?></div>
                    <div class="hero-product <?= $heroHasImage ? 'has-image' : '' ?>"><?php if ($heroHasImage): ?><img src="<?= $esc($heroImage) ?>" alt="<?= $esc($heroProduct['nombre'] ?? '') ?>"><?php else: ?><div class="hero-fallback"></div><?php endif; ?></div>
                <?php endif; ?>
                <div class="hero-note"><strong>Buenas compras.<br>Mejores días.</strong><small><?= (int)$productCount ?> producto<?= $productCount === 1 ? '' : 's' ?> en catálogo</small></div>
            </div>
        </div>
    </div>
</section>

<section class="category-section" id="categorias"><div class="wrap"><div class="category-row">
<?php if ($categoryCards): foreach ($categoryCards as $cat): ?>
    <button type="button" class="category-btn" data-jump-category="<?= $esc(strtolower((string)$cat['nombre'])) ?>"><span class="cat-circle"><?php if ($cat['has_image']): ?><img src="<?= $esc($cat['imagen']) ?>" alt="<?= $esc($cat['nombre']) ?>"><?php else: ?><span class="fallback">◫</span><?php endif; ?></span><strong><?= $esc($cat['nombre']) ?></strong><small><?= (int)$cat['cantidad'] ?> producto<?= (int)$cat['cantidad'] === 1 ? '' : 's' ?></small></button>
<?php endforeach; else: ?><button class="category-btn" type="button"><span class="cat-circle"><span class="fallback">◫</span></span><strong>Catálogo</strong><small>Sin categorías</small></button><?php endif; ?>
</div></div></section>

<section class="section" id="destacados"><div class="wrap">
    <div class="section-head"><div><h2>Productos destacados</h2><p>Una selección visible al inicio de tu tienda.</p></div><a href="#productos">Ver todo →</a></div>
    <div class="products">
    <?php foreach (array_slice($featured,0,4) as $p):
        [$img,$hasImg]=$imageFor($p); $stock=(float)($p['stock_mostrado']??0); $precio=$buildPrice($p);
        $detail=['id'=>(int)($p['idarticulo']??0),'nombre'=>(string)($p['nombre']??''),'categoria'=>(string)($p['categoria']??''),'codigo'=>(string)($p['codigo']??''),'descripcion'=>(string)($p['descripcion']??''),'imagen'=>$img,'has_image'=>$hasImg,'precio'=>$precio,'stock'=>$stock,'variaciones'=>$p['variaciones']??[]];
    ?>
        <article class="product-card" data-producto data-search="<?= $esc(strtolower(($p['nombre']??'').' '.($p['codigo']??'').' '.($p['categoria']??''))) ?>" data-category="<?= $esc(strtolower((string)($p['categoria']??''))) ?>" data-detail='<?= $esc(json_encode($detail,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>'>
            <div class="product-image" data-open-detail><?php if($hasImg): ?><img src="<?= $esc($img) ?>" alt="<?= $esc($p['nombre']??'') ?>" loading="lazy"><?php else: ?><div class="product-placeholder"><div class="shape"></div></div><?php endif; ?><span class="badge">Destacado</span><button type="button" class="heart" aria-label="Favorito">♡</button></div>
            <div class="product-info"><div class="product-cat"><?= $esc($p['categoria']??'Catálogo') ?></div><div class="product-name"><?= $esc($p['nombre']??'') ?></div><?php if($mostrarCodigo&&!empty($p['codigo'])):?><div class="sku">SKU <?= $esc($p['codigo']) ?></div><?php endif; ?><div class="product-meta"><?php if($mostrarPrecios):?><div class="price"><?= $esc($precio) ?></div><?php endif; ?><?php if($mostrarStock):?><div class="stock <?= $stock<=0?'out':'' ?>"><?= $stock>0?'Disponible':'Agotado' ?></div><?php endif; ?></div><div class="card-actions"><button type="button" data-open-detail>Ver detalle</button><?php if($mostrarWhatsapp):?><button type="button" class="primary" data-whatsapp>Consultar</button><?php endif;?></div></div>
        </article>
    <?php endforeach; ?>
    </div>
</div></section>

<section class="section"><div class="wrap"><div class="promo-grid">
    <div class="promo-card green"><h3>Renueva tu espacio</h3><p>Explora tus productos por categoría y encuentra opciones de forma rápida.</p><a href="#productos">Explorar catálogo</a><div class="promo-art <?= $heroHasImage?'has-image':'' ?>"><?php if($heroHasImage):?><img src="<?= $esc($heroImage) ?>" alt=""><?php else:?><div class="blob"></div><?php endif;?></div></div>
    <div class="promo-card pink"><h3>Compra con confianza</h3><p>Información clara, precios visibles y contacto directo con el negocio.</p><?php if($mostrarWhatsapp):?><a href="https://wa.me/<?= $esc($whatsapp) ?>" target="_blank" rel="noopener">Escribir por WhatsApp</a><?php else:?><a href="#contacto">Ver contacto</a><?php endif;?><div class="promo-art"><div class="blob"></div></div></div>
</div></div></section>

<section class="browse" id="productos"><div class="wrap">
    <div class="section-head"><div><h2>Explora nuestros productos</h2><p><span id="contadorProductos"><?= (int)$productCount ?></span> producto<?= $productCount===1?'':'s' ?> disponible<?= $productCount===1?'':'s' ?>.</p></div></div>
    <div class="toolbar"><div class="filters" id="filtrosCategorias"><button type="button" class="active" data-cat="">Todos</button><?php foreach($categorias as $categoria):?><button type="button" data-cat="<?= $esc(strtolower((string)$categoria)) ?>"><?= $esc($categoria) ?></button><?php endforeach;?></div><div class="browse-search"><input type="search" id="buscarCatalogo" placeholder="Buscar producto o código..."><span>⌕</span></div></div>
    <div class="products" id="gridProductos">
    <?php foreach($productos as $p):
        [$img,$hasImg]=$imageFor($p); $stock=(float)($p['stock_mostrado']??0); $precio=$buildPrice($p);
        $detail=['id'=>(int)($p['idarticulo']??0),'nombre'=>(string)($p['nombre']??''),'categoria'=>(string)($p['categoria']??''),'codigo'=>(string)($p['codigo']??''),'descripcion'=>(string)($p['descripcion']??''),'imagen'=>$img,'has_image'=>$hasImg,'precio'=>$precio,'stock'=>$stock,'variaciones'=>$p['variaciones']??[]];
    ?>
        <article class="product-card" data-producto data-search="<?= $esc(strtolower(($p['nombre']??'').' '.($p['codigo']??'').' '.($p['categoria']??''))) ?>" data-category="<?= $esc(strtolower((string)($p['categoria']??''))) ?>" data-detail='<?= $esc(json_encode($detail,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>'>
            <div class="product-image" data-open-detail><?php if($hasImg):?><img src="<?= $esc($img) ?>" alt="<?= $esc($p['nombre']??'') ?>" loading="lazy"><?php else:?><div class="product-placeholder"><div class="shape"></div></div><?php endif;?><?php if((int)($p['destacado']??0)===1):?><span class="badge">Destacado</span><?php elseif((int)($p['cantidad_variaciones']??0)>0):?><span class="badge">Variantes</span><?php endif;?><button type="button" class="heart" aria-label="Favorito">♡</button></div>
            <div class="product-info"><div class="product-cat"><?= $esc($p['categoria']??'Catálogo') ?></div><div class="product-name"><?= $esc($p['nombre']??'') ?></div><?php if($mostrarCodigo&&!empty($p['codigo'])):?><div class="sku">SKU <?= $esc($p['codigo']) ?></div><?php endif;?><div class="product-meta"><?php if($mostrarPrecios):?><div class="price"><?= $esc($precio) ?></div><?php endif;?><?php if($mostrarStock):?><div class="stock <?= $stock<=0?'out':'' ?>"><?= $stock>0?'Disponible':'Agotado' ?></div><?php endif;?></div><div class="card-actions"><button type="button" data-open-detail>Ver detalle</button><?php if($mostrarWhatsapp):?><button type="button" class="primary" data-whatsapp>Consultar</button><?php endif;?></div></div>
        </article>
    <?php endforeach;?>
    </div>
    <div class="catalog-loader <?= $hasMoreInitial ? '' : 'hidden' ?>" id="catalogLoader"><span class="catalog-spinner"></span><span>Cargando más productos...</span></div>
    <div class="catalog-progress" id="catalogProgress">Mostrando <?= count($productos) ?> de <?= (int)$productCount ?> productos</div>
    <div id="catalogSentinel" style="height:1px"></div>
    <div class="empty" id="catalogoVacio"><strong>No encontramos productos.</strong>Prueba con otra búsqueda o categoría.</div>
</div></section>

<section class="trust"><div class="wrap"><div class="trust-grid"><div class="trust-item"><span class="trust-icon">↻</span><div><strong>Datos sincronizados</strong><small>Precios y stock consumidos desde TiquePOS.</small></div></div><div class="trust-item"><span class="trust-icon">✓</span><div><strong>Información clara</strong><small>El cliente ve lo esencial antes de consultar.</small></div></div><div class="trust-item"><span class="trust-icon">◉</span><div><strong>Contacto directo</strong><small>WhatsApp integrado por producto.</small></div></div><div class="trust-item"><span class="trust-icon">▦</span><div><strong>Organizado por categorías</strong><small>Navegación sencilla y rápida.</small></div></div><div class="trust-item"><span class="trust-icon">♡</span><div><strong>Diseño adaptable</strong><small>Optimizado para computadora y celular.</small></div></div></div></div></section>

<section class="about" id="contacto"><div class="wrap"><div class="about-grid"><div><h3>Sobre <?= $esc($nombre) ?></h3><p><?= $descripcion!==''?nl2br($esc($descripcion)):'Conoce nuestro catálogo y consulta directamente por los productos que te interesan.' ?></p><div class="socials"><?php if($mostrarWhatsapp):?><a href="https://wa.me/<?= $esc($whatsapp) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif;?><?php if($config['instagram']??''):?><a href="<?= $esc($config['instagram']) ?>" target="_blank" rel="noopener">Instagram</a><?php endif;?><?php if($config['facebook']??''):?><a href="<?= $esc($config['facebook']) ?>" target="_blank" rel="noopener">Facebook</a><?php endif;?></div></div><div class="contact-box"><?php if($config['direccion']??''):?><div class="contact-item"><strong>Dirección</strong><span><?= $esc($config['direccion']) ?></span></div><?php endif;?><?php if($config['empresa_telefono']??''):?><div class="contact-item"><strong>Teléfono</strong><span><?= $esc($config['empresa_telefono']) ?></span></div><?php endif;?><?php if($config['empresa_email']??''):?><div class="contact-item"><strong>Email</strong><span><?= $esc($config['empresa_email']) ?></span></div><?php endif;?><?php if(($config['ciudad']??'')||($config['pais']??'')):?><div class="contact-item"><strong>Ubicación</strong><span><?= $esc(trim((string)($config['ciudad']??'').(((string)($config['ciudad']??'')!==''&&(string)($config['pais']??'')!=='')?', ':'').(string)($config['pais']??''))) ?></span></div><?php endif;?></div></div></div></section>
</main>

<footer class="footer"><div class="wrap"><div class="footer-grid"><div><div class="footer-brand"><img src="<?= $esc($logo) ?>" alt="<?= $esc($nombre) ?>"><div><h4><?= $esc($nombre) ?></h4><p><?= $esc($subtitulo) ?></p></div></div></div><div><h4>Tienda</h4><ul><li><a href="#productos">Todos los productos</a></li><li><a href="#destacados">Destacados</a></li><li><a href="#categorias">Categorías</a></li></ul></div><div><h4>Ayuda</h4><ul><li><a href="#contacto">Contacto</a></li><?php if($mostrarWhatsapp):?><li><a href="https://wa.me/<?= $esc($whatsapp) ?>" target="_blank" rel="noopener">WhatsApp</a></li><?php endif;?></ul></div><div><h4>Información</h4><ul><?php if($config['empresa_telefono']??''):?><li><?= $esc($config['empresa_telefono']) ?></li><?php endif;?><?php if($config['empresa_email']??''):?><li><?= $esc($config['empresa_email']) ?></li><?php endif;?></ul></div></div><div class="footer-bottom"><span>© <?= date('Y') ?> <?= $esc($nombre) ?>.</span><span>Catálogo actualizado desde TiquePOS · Precios y disponibilidad sujetos a cambios.</span></div></div></footer>

<div class="modal" id="modalProducto" aria-hidden="true"><div class="modal-card"><div class="modal-grid"><div class="modal-media" id="modalMedia"><img id="modalImagen" src="" alt=""><div class="modal-fallback" id="modalFallback" style="display:none"></div></div><div class="modal-body"><button class="close" type="button" id="cerrarModal">×</button><div class="modal-cat" id="modalCategoria"></div><h3 id="modalNombre"></h3><div class="modal-code" id="modalCodigo"></div><div class="modal-price" id="modalPrecio"></div><div class="modal-desc" id="modalDescripcion"></div><div class="variants" id="modalVariantes"></div><?php if($mostrarWhatsapp):?><button class="modal-action" type="button" id="modalWhatsapp">Consultar por WhatsApp</button><?php endif;?></div></div></div></div>

<script>
(function(){
const apiUrl=<?= json_encode($apiUrl) ?>;
const slug=<?= json_encode($slug) ?>;
const whatsapp=<?= json_encode($whatsapp) ?>;
const mostrarDescripcion=<?= $mostrarDescripcion?'true':'false' ?>;
const mostrarPrecios=<?= $mostrarPrecios?'true':'false' ?>;
const mostrarCodigo=<?= $mostrarCodigo?'true':'false' ?>;
const mostrarStock=<?= $mostrarStock?'true':'false' ?>;
const mostrarWhatsapp=<?= $mostrarWhatsapp?'true':'false' ?>;
const simbolo=<?= json_encode($simbolo) ?>;
const pageLimit=<?= (int)$pageLimit ?>;
const grid=document.getElementById('gridProductos');
const input=document.getElementById('buscarCatalogo');
const heroInput=document.getElementById('buscarHero');
const cats=[...document.querySelectorAll('#filtrosCategorias [data-cat]')];
const empty=document.getElementById('catalogoVacio');
const counter=document.getElementById('contadorProductos');
const loader=document.getElementById('catalogLoader');
const progress=document.getElementById('catalogProgress');
const sentinel=document.getElementById('catalogSentinel');
let offset=<?= count($productos) ?>;
let total=<?= (int)$productCount ?>;
let hasMore=<?= $hasMoreInitial ? 'true' : 'false' ?>;
let loading=false;
let currentCat='';
let currentSearch='';
let searchTimer=null;
let requestSeq=0;
let activeController=null;

function priceFor(p){
  const min=Number(p.precio_min||0),max=Number(p.precio_max||0);
  let text=simbolo+' '+min.toFixed(2);
  if(max>min+.001)text+=' – '+simbolo+' '+max.toFixed(2);
  return text;
}
function updateLoadUI(){
  if(loader)loader.classList.toggle('hidden',!hasMore&&!loading);
  if(progress)progress.textContent='Mostrando '+Math.min(offset,total)+' de '+total+' productos';
  if(counter)counter.textContent=total;
  if(empty)empty.style.display=total===0?'block':'none';
}
function make(tag,cls,text){const e=document.createElement(tag);if(cls)e.className=cls;if(text!==undefined)e.textContent=text;return e;}
function buildCard(p){
  const article=make('article','product-card');
  article.dataset.producto='';
  article.dataset.category=(p.categoria||'').toLowerCase();
  const price=priceFor(p),hasImage=Boolean(p.imagen_url);
  article._detail={id:Number(p.idarticulo||0),nombre:p.nombre||'',categoria:p.categoria||'',codigo:p.codigo||'',descripcion:p.descripcion||'',imagen:p.imagen_url||'',has_image:hasImage,precio:price,stock:Number(p.stock_mostrado||0),variaciones:Array.isArray(p.variaciones)?p.variaciones:[]};

  const media=make('div','product-image');media.dataset.openDetail='';
  if(hasImage){const img=document.createElement('img');img.src=p.imagen_url;img.alt=p.nombre||'';img.loading='lazy';img.onerror=()=>{img.remove();if(!media.querySelector('.product-placeholder'))media.prepend(createPlaceholder());};media.appendChild(img);}else media.appendChild(createPlaceholder());
  if(Number(p.destacado)===1){media.appendChild(make('span','badge','Destacado'));}else if(Number(p.cantidad_variaciones)>0){media.appendChild(make('span','badge','Variantes'));}
  const heart=make('button','heart','♡');heart.type='button';heart.setAttribute('aria-label','Favorito');media.appendChild(heart);

  const info=make('div','product-info');info.appendChild(make('div','product-cat',p.categoria||'Catálogo'));info.appendChild(make('div','product-name',p.nombre||'Producto'));
  if(mostrarCodigo&&p.codigo)info.appendChild(make('div','sku','SKU '+p.codigo));
  const meta=make('div','product-meta');if(mostrarPrecios)meta.appendChild(make('div','price',price));if(mostrarStock){const st=make('div','stock'+(Number(p.stock_mostrado||0)<=0?' out':''),Number(p.stock_mostrado||0)>0?'Disponible':'Agotado');meta.appendChild(st);}info.appendChild(meta);
  const actions=make('div','card-actions');const detail=make('button','', 'Ver detalle');detail.type='button';detail.dataset.openDetail='';actions.appendChild(detail);if(mostrarWhatsapp){const ask=make('button','primary','Consultar');ask.type='button';ask.dataset.whatsapp='';actions.appendChild(ask);}info.appendChild(actions);
  article.append(media,info);return article;
}
function createPlaceholder(){const p=make('div','product-placeholder');p.appendChild(make('div','shape'));return p;}

async function loadPage({reset=false}={}){
  if(reset&&loading&&activeController){activeController.abort();loading=false;}
  if(loading)return;
  if(!reset&&!hasMore)return;
  if(reset){
    offset=0;total=0;hasMore=true;grid.innerHTML='';requestSeq++;
    if(activeController)activeController.abort();
  }
  const seq=requestSeq;
  activeController=new AbortController();
  loading=true;if(loader)loader.classList.remove('hidden');
  try{
    const qs=new URLSearchParams({op:'productos',slug,limit:String(pageLimit),offset:String(offset),buscar:currentSearch,categoria:currentCat});
    const response=await fetch(apiUrl+'?'+qs.toString(),{signal:activeController.signal,credentials:'same-origin'});
    const data=await response.json();
    if(seq!==requestSeq)return;
    if(!response.ok||!data.success)throw new Error(data.mensaje||'No se pudo cargar el catálogo.');
    if(data.total!==null&&data.total!==undefined)total=Number(data.total||0);
    const items=Array.isArray(data.data)?data.data:[];
    items.forEach(p=>grid.appendChild(buildCard(p)));
    offset+=items.length;
    hasMore=Boolean(data.has_more);
  }catch(e){
    if(e.name!=='AbortError'){console.error(e);hasMore=false;}
  }finally{
    if(seq===requestSeq){loading=false;updateLoadUI();}
  }
}

function scheduleSearch(value){clearTimeout(searchTimer);searchTimer=setTimeout(()=>{currentSearch=(value||'').trim();if(input&&input.value!==currentSearch)input.value=currentSearch;if(heroInput&&heroInput.value!==currentSearch)heroInput.value=currentSearch;loadPage({reset:true});},320);}
input?.addEventListener('input',()=>scheduleSearch(input.value));
heroInput?.addEventListener('input',()=>scheduleSearch(heroInput.value));
heroInput?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();document.getElementById('productos')?.scrollIntoView({behavior:'smooth',block:'start'});}});
cats.forEach(b=>b.addEventListener('click',()=>{cats.forEach(x=>x.classList.remove('active'));b.classList.add('active');currentCat=b.dataset.cat||'';loadPage({reset:true});}));
document.querySelectorAll('[data-jump-category]').forEach(btn=>btn.addEventListener('click',()=>{const value=btn.dataset.jumpCategory||'';const target=cats.find(x=>(x.dataset.cat||'')===value);if(target)target.click();document.getElementById('productos')?.scrollIntoView({behavior:'smooth',block:'start'});}));

const observer=new IntersectionObserver(entries=>{if(entries.some(e=>e.isIntersecting)&&hasMore&&!loading)loadPage();},{rootMargin:'650px 0px'});if(sentinel)observer.observe(sentinel);

const modal=document.getElementById('modalProducto'),mi=document.getElementById('modalImagen'),mf=document.getElementById('modalFallback'),mn=document.getElementById('modalNombre'),mc=document.getElementById('modalCategoria'),mk=document.getElementById('modalCodigo'),mp=document.getElementById('modalPrecio'),md=document.getElementById('modalDescripcion'),mv=document.getElementById('modalVariantes');let actual=null;
function detalle(card){if(card?._detail)return card._detail;try{return JSON.parse(card?.dataset.detail||'{}')}catch(e){return null}}
function abrir(card){const d=detalle(card);if(!d||!modal)return;actual=d;if(d.has_image&&d.imagen){mi.src=d.imagen;mi.style.display='block';mf.style.display='none'}else{mi.style.display='none';mf.style.display='block'}mn.textContent=d.nombre||'';mc.textContent=d.categoria||'';mk.textContent=d.codigo?'SKU '+d.codigo:'';mp.textContent=mostrarPrecios?(d.precio||''):'Consultar precio';md.textContent=mostrarDescripcion&&d.descripcion?d.descripcion:'';mv.innerHTML='';if(Array.isArray(d.variaciones)&&d.variaciones.length){const h=document.createElement('h4');h.textContent='Opciones disponibles';mv.appendChild(h);d.variaciones.forEach(v=>{const row=document.createElement('div');row.className='variant';const left=document.createElement('div');const st=document.createElement('strong');st.textContent=v.combinacion||v.sku||'Variante';const sm=document.createElement('small');sm.textContent=Number(v.stock||0)>0?'Disponible':'Agotado';left.append(st,sm);const right=document.createElement('strong');right.textContent=mostrarPrecios&&Number(v.precio_venta||0)>0?simbolo+' '+Number(v.precio_venta).toFixed(2):'';row.append(left,right);mv.appendChild(row)})}modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden'}
function cerrar(){modal?.classList.remove('open');modal?.setAttribute('aria-hidden','true');document.body.style.overflow=''}
function abrirWhats(d){if(!whatsapp||!d)return;const texto='Hola, deseo información sobre '+(d.nombre||'este producto')+(d.codigo?' (SKU '+d.codigo+')':'')+(mostrarPrecios&&d.precio?' - '+d.precio:'');window.open('https://wa.me/'+whatsapp+'?text='+encodeURIComponent(texto),'_blank','noopener')}

document.addEventListener('click',e=>{
  const heart=e.target.closest('.heart');if(heart){e.stopPropagation();heart.textContent=heart.textContent==='♥'?'♡':'♥';heart.style.color=heart.textContent==='♥'?'#b34d57':'#405047';return;}
  const detailBtn=e.target.closest('[data-open-detail]');if(detailBtn){abrir(detailBtn.closest('[data-producto]'));return;}
  const whats=e.target.closest('[data-whatsapp]');if(whats){abrirWhats(detalle(whats.closest('[data-producto]')));return;}
});
document.getElementById('cerrarModal')?.addEventListener('click',cerrar);modal?.addEventListener('click',e=>{if(e.target===modal)cerrar()});document.addEventListener('keydown',e=>{if(e.key==='Escape')cerrar()});document.getElementById('modalWhatsapp')?.addEventListener('click',()=>abrirWhats(actual));
updateLoadUI();
})();
</script>
</body>
</html>
