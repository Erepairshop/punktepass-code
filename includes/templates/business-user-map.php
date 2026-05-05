<?php
if (!defined('ABSPATH')) exit;
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de';
$labels = [
    'de' => ['title'=>'Karte','search'=>'Suchen…','filter_all'=>'Alle','follow'=>'Folgen','following'=>'Folgst du','call'=>'Anrufen','whatsapp'=>'WhatsApp','directions'=>'Wegbeschreibung','no_pins'=>'Keine Geschäfte in dieser Region.','points_here'=>'Punkte sammeln hier','offers'=>'Aktuelle Angebote','follow_banner'=>'Folge dem Geschäft, verpasse keine Aktionen','follow_banner_ad'=>'Folge, verpasse keine Aktionen & Gewinne','redeem'=>'Einlösen','remaining'=>'übrig','sold_out'=>'Vergriffen','sold_out_msg'=>'Leider sind alle Gutscheine vergriffen.'],
    'hu' => ['title'=>'Térkép','search'=>'Keresés…','filter_all'=>'Mind','follow'=>'Követés','following'=>'Követed','call'=>'Hívás','whatsapp'=>'WhatsApp','directions'=>'Útvonal','no_pins'=>'Nincs üzlet ebben a régióban.','points_here'=>'Pontot gyűjthetsz','offers'=>'Aktuális ajánlatok','follow_banner'=>'Kövesd a boltot, ne maradj le akciókról','follow_banner_ad'=>'Kövess, ne maradj le akciókról és nyereményekről','redeem'=>'Beváltás','remaining'=>'maradt','sold_out'=>'Kifogyott','sold_out_msg'=>'Sajnos minden kupon elfogyott.'],
    'ro' => ['title'=>'Hartă','search'=>'Caută…','filter_all'=>'Toate','follow'=>'Urmărește','following'=>'Urmărești','call'=>'Sună','whatsapp'=>'WhatsApp','directions'=>'Direcții','no_pins'=>'Nu sunt magazine în această regiune.','points_here'=>'Adună puncte','offers'=>'Oferte curente','follow_banner'=>'Urmărește magazinul, nu rata oferte','follow_banner_ad'=>'Urmărește, nu rata oferte și premii','redeem'=>'Răscumpărare','remaining'=>'rămase','sold_out'=>'Epuizate','sold_out_msg'=>'Toate cupoanele au fost epuizate.'],
    'en' => ['title'=>'Map','search'=>'Search…','filter_all'=>'All','follow'=>'Follow','following'=>'Following','call'=>'Call','whatsapp'=>'WhatsApp','directions'=>'Directions','no_pins'=>'No shops in this area yet.','points_here'=>'Earn points here','offers'=>'Current offers','follow_banner'=>'Follow the shop to never miss promotions','follow_banner_ad'=>'Follow to never miss promotions & prizes','redeem'=>'Redeem','remaining'=>'left','sold_out'=>'Sold out','sold_out_msg'=>'All coupons have been claimed.'],
];
$L = $labels[$lang] ?? $labels['de'];
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?php echo esc_html($L['title']); ?> — PunktePass</title>
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
<link rel="stylesheet" href="/wp-content/plugins/punktepass/assets/css/ppv-core.css">
<link rel="stylesheet" href="/wp-content/plugins/punktepass/assets/css/ppv-components.css">
<link rel="stylesheet" href="/wp-content/plugins/punktepass/assets/css/ppv-handler.css">
<style>
* { box-sizing:border-box; }
html,body { margin:0; height:100%; font:14px/1.5 system-ui,-apple-system,sans-serif; }
#map { height:100vh; width:100vw; }
.km-bar { position:fixed; top:10px; left:10px; right:10px; z-index:9999; padding:10px 12px; background:#fff; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,.18); display:flex; gap:8px; align-items:center; }
.km-suggestions { position:fixed; top:64px; left:10px; right:10px; z-index:9998; background:#fff; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.18); max-height:50vh; overflow:auto; display:none; }
.km-suggestions.open { display:block; }
.km-sugg-item { display:flex; align-items:center; gap:10px; padding:10px 14px; cursor:pointer; border-bottom:1px solid #f1f5f9; transition:background .12s; }
.km-sugg-item:last-child { border-bottom:none; }
.km-sugg-item:active, .km-sugg-item:hover { background:#f8fafc; }
.km-sugg-icon { width:32px; height:32px; border-radius:8px; background:#dbeafe; color:#1e40af; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:16px; }
.km-sugg-icon.advertiser { background:#fef3c7; color:#92400e; }
.km-sugg-body { flex:1; min-width:0; }
.km-sugg-name { font-weight:600; font-size:14px; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.km-sugg-addr { font-size:12px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.km-sugg-empty { padding:14px; color:#9ca3af; font-size:13px; text-align:center; }
.km-sugg-mark { background:#fef3c7; border-radius:3px; padding:0 2px; }
.km-bar input, .km-bar select { padding:8px 10px; border:none; border-radius:6px; font:inherit; background:#f3f4f6; }
.km-bar input { flex:1; min-width:120px; }
.km-bar strong { font-size:18px; }
.km-locate-btn { width:36px; height:36px; border-radius:8px; background:#3b82f6; color:#fff; border:none; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:transform .15s ease, background .15s ease; }
.km-locate-btn:active { transform:scale(.92); }
.km-locate-btn.active { background:#10b981; }
.km-locate-btn.error { background:#dc2626; }
.km-locate-btn.loading i { animation:km-spin 1s linear infinite; }
@keyframes km-spin { to { transform:rotate(360deg); } }
/* pin */
.km-pin { width:48px; height:48px; cursor:pointer; }
.km-pin.loyalty { --c: #3b82f6; }
.km-pin.advertiser { --c: #f59e0b; }
.km-pin.featured  { --c: #facc15; }
.km-pin .ring { width:48px; height:48px; border-radius:50%; background:#fff; border:3px solid var(--c); box-shadow:0 4px 12px rgba(0,0,0,.25); overflow:hidden; display:flex; align-items:center; justify-content:center; transition:transform .15s; }
.km-pin:hover .ring { transform:scale(1.12); }
.km-pin .ring img { width:100%; height:100%; object-fit:cover; }
.km-pin .ring .ico { font-size:22px; }
.km-pin .ring .name-init { font-size:11px; font-weight:800; color:#fff; line-height:1; text-align:center; padding:2px; letter-spacing:.3px; text-shadow:0 1px 2px rgba(0,0,0,.3); }
.km-pin.loyalty .ring.no-logo { background:linear-gradient(135deg,#6366f1,#8b5cf6); border-color:#fff; }
.km-pin.advertiser .ring.no-logo { background:linear-gradient(135deg,#f59e0b,#d97706); border-color:#fff; }
.km-pin .badge { position:absolute; top:-4px; right:-4px; background:var(--c); color:#fff; font-size:10px; padding:2px 5px; border-radius:8px; font-weight:700; }
/* card */
.km-sheet { position:absolute; left:0; right:0; bottom:-100%; max-height:75vh; overflow:auto; background:#fff; border-radius:18px 18px 0 0; box-shadow:0 -8px 32px rgba(0,0,0,.25); transition:bottom .3s ease; z-index:20; }
.km-sheet.open { bottom:0; }
.km-sheet-grab { width:48px; height:5px; background:#d1d5db; border-radius:3px; margin:8px auto; }
.km-cover { height:48px; background:linear-gradient(135deg,#6366f1,#8b5cf6) center/cover; display:flex; align-items:center; justify-content:flex-end; color:#fff; font-size:12px; font-weight:600; padding:0 16px 0 110px; text-align:right; line-height:1.2; }
.km-cover.advertiser { background:linear-gradient(135deg,#f59e0b,#dc2626); }
.km-card-head { padding:0 18px; margin-top:-40px; display:flex; gap:12px; align-items:flex-end; }
.km-card-logo { width:80px; height:80px; border-radius:16px; background:#fff center/cover no-repeat; border:4px solid #fff; box-shadow:0 4px 12px rgba(0,0,0,.12); display:flex; align-items:center; justify-content:center; overflow:hidden; }
.km-card-logo.no-logo { background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; padding:6px; }
.km-card-logo.no-logo.advertiser { background:linear-gradient(135deg,#f59e0b,#d97706); }
.km-card-logo .km-logo-text { font-weight:700; font-size:13px; line-height:1.15; text-align:center; word-break:break-word; hyphens:auto; max-height:100%; overflow:hidden; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; }
.km-card-title { padding:10px 18px 8px; font-size:20px; font-weight:700; display:flex; align-items:center; gap:10px; justify-content:space-between; position:sticky; top:0; background:#fff; z-index:5; box-shadow:0 1px 0 rgba(0,0,0,.04); }
.km-card-title .name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; }
.km-fol-pill { flex-shrink:0; display:inline-flex; align-items:center; gap:5px; padding:7px 12px; border-radius:999px; border:none; cursor:pointer; font-size:12px; font-weight:700; background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; box-shadow:0 4px 12px rgba(99,102,241,.35); transition:transform .15s ease; white-space:nowrap; }
.km-fol-pill:active { transform:scale(.94); }
.km-fol-pill i { font-size:15px; line-height:1; }
.km-fol-pill.active { background:linear-gradient(135deg,#10b981,#059669); box-shadow:0 4px 12px rgba(16,185,129,.35); }
.km-card-sub { padding:0 18px; color:#6b7280; font-size:13px; }
.km-card-tag { display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:600; margin:0 4px 4px 0; }
.km-card-tag.loyalty { background:#dbeafe; color:#1e3a8a; }
.km-card-tag.advertiser { background:#fef3c7; color:#92400e; }
.km-card-actions { position:sticky; bottom:0; display:flex; gap:6px; padding:10px 14px calc(10px + env(safe-area-inset-bottom)); background:rgba(255,255,255,.96); backdrop-filter:blur(8px); border-top:1px solid #f1f5f9; }
.km-card-actions a, .km-card-actions button { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; padding:8px 4px; border-radius:12px; text-decoration:none; border:none; cursor:pointer; font-size:10px; font-weight:600; line-height:1.1; background:#f8fafc; color:#475569; transition:transform .15s ease, background .15s ease; }
.km-card-actions a:active, .km-card-actions button:active { transform:scale(.94); }
.km-card-actions a i, .km-card-actions button i { font-size:18px; line-height:1; }
.km-card-actions .call { color:#059669; }
.km-card-actions .call i { color:#10b981; }
.km-card-actions .wa { color:#1ea952; }
.km-card-actions .wa i { color:#25d366; }
.km-card-actions .dir { color:#2563eb; }
.km-card-actions .dir i { color:#3b82f6; }
.km-card-actions .fol { color:#fff; background:linear-gradient(135deg,#6366f1,#8b5cf6); }
.km-card-actions .fol i { color:#fff; }
.km-card-actions .fol.active { background:linear-gradient(135deg,#10b981,#059669); }
.km-card-body { padding:0 18px 24px; color:#374151; }
.km-offers { padding:0 18px 18px; }
.km-offers .row { display:flex; overflow-x:auto; gap:10px; scroll-snap-type:x mandatory; padding:6px 0; }
.km-offer { flex:0 0 80%; scroll-snap-align:start; background:#f9fafb; border-radius:12px; overflow:hidden; }
.km-offer img { width:100%; height:120px; object-fit:cover; }
.km-offer .body { padding:10px 12px; }
.km-offer h4 { margin:0 0 4px; font-size:14px; }
.km-offer p { margin:0; font-size:12px; color:#6b7280; }

/* Modern info card */
.mc-status { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:99px; font-size:12px; font-weight:600; }
.mc-status.open { background:#dcfce7; color:#166534; }
.mc-status.closed { background:#fee2e2; color:#991b1b; }
.mc-status::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; }
.mc-section { padding:14px 18px; border-top:1px solid #f3f4f6; }
.mc-row { display:flex; align-items:center; gap:8px; font-size:13px; color:#374151; padding:6px 0; }
.mc-row .ic { font-size:16px; color:#9ca3af; }
.mc-gallery { display:flex; gap:8px; overflow-x:auto; padding:12px 0 4px; scroll-snap-type:x mandatory; }
.mc-gallery img { flex:0 0 auto; width:100px; height:100px; border-radius:10px; object-fit:cover; scroll-snap-align:start; cursor:pointer; transition:transform .15s ease; }
.mc-gallery img:active { transform:scale(.95); }
.km-lightbox { position:fixed; inset:0; background:rgba(0,0,0,.92); z-index:99999; display:none; align-items:center; justify-content:center; touch-action:none; }
.km-lightbox.open { display:flex; }
.km-lightbox-img { max-width:96vw; max-height:88vh; border-radius:8px; box-shadow:0 12px 48px rgba(0,0,0,.6); user-select:none; -webkit-user-drag:none; }
.km-lightbox-close { position:absolute; top:max(12px,env(safe-area-inset-top)); right:14px; width:42px; height:42px; border-radius:50%; background:rgba(255,255,255,.15); backdrop-filter:blur(8px); border:none; color:#fff; font-size:24px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.km-lightbox-nav { position:absolute; top:50%; transform:translateY(-50%); width:46px; height:46px; border-radius:50%; background:rgba(255,255,255,.15); backdrop-filter:blur(8px); border:none; color:#fff; font-size:22px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.km-lightbox-nav.prev { left:12px; }
.km-lightbox-nav.next { right:12px; }
.km-lightbox-counter { position:absolute; bottom:max(20px,env(safe-area-inset-bottom)); left:50%; transform:translateX(-50%); color:#fff; font-size:13px; font-weight:600; background:rgba(0,0,0,.5); padding:6px 14px; border-radius:999px; }
.mc-social { display:flex; gap:8px; margin-top:8px; }
.mc-social a { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#f3f4f6; color:#374151; text-decoration:none; font-size:18px; }
.mc-social a:hover { background:#e5e7eb; }
.mc-rewards { display:flex; flex-direction:column; gap:8px; }
.mc-reward { background:linear-gradient(135deg, #fef3c7, #fde68a); border-radius:10px; padding:10px 12px; }
.mc-reward-title { display:flex; justify-content:space-between; align-items:center; font-weight:600; font-size:14px; color:#92400e; }
.mc-reward-pts { background:#f59e0b; color:#fff; padding:3px 8px; border-radius:99px; font-size:11px; font-weight:700; }
.mc-reward-meta { display:flex; gap:8px; flex-wrap:wrap; margin-top:6px; font-size:12px; color:#78350f; }
.mc-reward-meta span { background:rgba(255,255,255,.6); padding:2px 8px; border-radius:6px; }
.mc-vip-pill { display:inline-flex; gap:4px; align-items:center; background:linear-gradient(135deg,#fce7f3,#fbcfe8); color:#9d174d; padding:4px 10px; border-radius:99px; font-size:12px; font-weight:600; margin-top:4px; }
.mc-vip-table { width:100%; border-collapse:collapse; margin-top:8px; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.06); }
.mc-vip-table th, .mc-vip-table td { padding:8px 6px; text-align:center; font-size:12px; border-bottom:1px solid #f3f4f6; }
.mc-vip-table th { background:#f9fafb; color:#6b7280; font-weight:600; text-transform:uppercase; font-size:10px; letter-spacing:.4px; }
.mc-vip-table th i, .mc-vip-table td i { font-size:14px; }
.mc-vip-table th.bronze { color:#cd7f32; }
.mc-vip-table th.silver { color:#94a3b8; }
.mc-vip-table th.gold { color:#daa520; }
.mc-vip-table th.platinum { color:#6b7280; }
.mc-vip-table td.bronze { color:#cd7f32; font-weight:700; }
.mc-vip-table td.silver { color:#94a3b8; font-weight:700; }
.mc-vip-table td.gold { color:#daa520; font-weight:700; }
.mc-vip-table td.platinum { color:#6b7280; font-weight:700; }
.mc-vip-table td.row-label { background:linear-gradient(90deg,#fef3c7,transparent); color:#92400e; font-weight:600; text-align:left; padding-left:12px; }
.mc-vip-table td.mult { background:linear-gradient(135deg,#fce7f3,#fbcfe8); color:#9d174d; font-weight:700; font-size:14px; }
.mc-h3 { margin:0 0 6px; font-size:13px; text-transform:uppercase; letter-spacing:.5px; color:#6b7280; font-weight:600; }
.mc-tabs { display:flex; gap:6px; padding:0 18px 8px; flex-wrap:wrap; }
.mc-tab { padding:5px 12px; background:#eef2ff; color:#4338ca; border-radius:99px; font-size:12px; font-weight:500; }
</style>
</head>
<body>
<div id="map"></div>
<div class="km-bar">
  <button class="km-locate-btn" id="km-locate" title="<?php echo esc_attr($L['my_location'] ?? 'Helyzetem'); ?>" aria-label="Helyzetem"><i class="ri-focus-3-line"></i></button>
  <input type="text" id="km-search" placeholder="<?php echo esc_attr($L['search']); ?>">
  <select id="km-cat">
    <option value=""><?php echo esc_html($L['filter_all']); ?></option>
    <option value="loyalty">🎁 Loyalty</option>
    <option value="advertiser">📣 Akciók</option>
  </select>
</div>
<div id="km-suggestions" class="km-suggestions"></div>
<div id="km-sheet" class="km-sheet"></div>
<div id="km-lightbox" class="km-lightbox" onclick="closeLightbox(event)">
  <button class="km-lightbox-close" onclick="closeLightbox(event)" aria-label="Close"><i class="ri-close-line"></i></button>
  <button class="km-lightbox-nav prev" onclick="lightboxNav(event,-1)" aria-label="Previous"><i class="ri-arrow-left-s-line"></i></button>
  <img id="km-lightbox-img" class="km-lightbox-img" src="" alt="">
  <button class="km-lightbox-nav next" onclick="lightboxNav(event,1)" aria-label="Next"><i class="ri-arrow-right-s-line"></i></button>
  <div id="km-lightbox-counter" class="km-lightbox-counter">1 / 1</div>
</div>

<!-- COUPON-MODAL — shown when user clicks "Beváltás" on a map ad. Displays the coupon code big enough for the shop owner to verify. -->
<div id="km-coupon-modal" style="position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:99999;display:none;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target.id==='km-coupon-modal') closeCouponModal()">
  <div style="background:#fff;border-radius:16px;max-width:420px;width:100%;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,0.4);text-align:center;">
    <button onclick="closeCouponModal()" style="position:absolute;top:18px;right:24px;width:36px;height:36px;border-radius:50%;background:#f3f4f6;border:none;font-size:22px;cursor:pointer;">×</button>
    <div style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;" data-i18n="coupon_modal_show_to_shop"><?php echo esc_html(PPV_Lang::t('coupon_modal_show_to_shop') ?: 'Mutasd fel a boltban'); ?></div>
    <div id="km-coupon-shop" style="font-size:18px;font-weight:700;color:#1e1b4b;margin-bottom:4px;"></div>
    <div id="km-coupon-title" style="font-size:14px;color:#374151;margin-bottom:14px;"></div>
    <div id="km-coupon-promo" style="display:inline-block;background:#ef4444;color:#fff;padding:6px 14px;border-radius:999px;font-size:14px;font-weight:700;margin-bottom:14px;"></div>
    <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:18px;border-radius:12px;margin:12px 0;">
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.1em;opacity:0.85;margin-bottom:6px;" data-i18n="coupon_modal_code_label"><?php echo esc_html(PPV_Lang::t('coupon_modal_code_label') ?: 'Beváltási kód'); ?></div>
      <div id="km-coupon-code" style="font-size:32px;font-weight:900;letter-spacing:0.15em;font-family:monospace;word-break:break-all;"></div>
    </div>
    <div id="km-coupon-body" style="font-size:13px;color:#6b7280;margin:10px 0;"></div>
    <div style="font-size:12px;color:#92400e;background:#fef3c7;padding:10px;border-radius:8px;margin-top:14px;" data-i18n="coupon_modal_show_to_shop_help"><?php echo esc_html(PPV_Lang::t('coupon_modal_show_to_shop_help') ?: 'A boltos láthatja és érvényesíti a kódot.'); ?></div>
  </div>
</div>
<script>window.__mapDbg = 'HTML loaded ' + Date.now();</script>
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script>
window.addEventListener('error', function(e) {
  document.body.innerHTML += '<div style="position:fixed;top:0;left:0;right:0;background:red;color:white;padding:10px;z-index:99999;font:12px monospace;">JS ERROR: ' + (e.message || e.error) + ' @ ' + (e.filename||'') + ':' + (e.lineno||'') + '</div>';
});
const T = <?php echo wp_json_encode($L); ?>;
const LANG = <?php echo wp_json_encode($lang); ?>;
const DEFAULT_CENTER = [22.4633, 47.6822]; // Carei, RO

const map = new maplibregl.Map({
  container: 'map',
  style: 'https://tiles.openfreemap.org/styles/positron',
  center: DEFAULT_CENTER,
  zoom: 12,
  attributionControl: { compact: true },
});
map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

let userMarker = null;
function showUser(lat, lng) {
  const el = document.createElement('div');
  el.style.cssText = 'width:18px;height:18px;border-radius:50%;background:#3b82f6;border:3px solid #fff;box-shadow:0 0 0 6px rgba(59,130,246,.3);';
  if (userMarker) userMarker.remove();
  userMarker = new maplibregl.Marker({ element: el }).setLngLat([lng, lat]).addTo(map);
  map.flyTo({ center:[lng, lat], zoom: 14 });
}
function locateMe() {
  const btn = document.getElementById('km-locate');
  if (!navigator.geolocation) {
    btn.classList.add('error');
    btn.innerHTML = '<i class="ri-close-line"></i>';
    alert('A böngésző nem támogatja a geolocation-t.');
    return;
  }
  btn.classList.add('loading');
  btn.classList.remove('active','error');
  btn.innerHTML = '<i class="ri-loader-4-line"></i>';
  navigator.geolocation.getCurrentPosition(
    p => {
      showUser(p.coords.latitude, p.coords.longitude);
      btn.classList.remove('loading','error');
      btn.classList.add('active');
      btn.innerHTML = '<i class="ri-focus-3-fill"></i>';
    },
    err => {
      btn.classList.remove('loading','active');
      btn.classList.add('error');
      btn.innerHTML = '<i class="ri-error-warning-line"></i>';
      const msg = err.code === 1
        ? 'Engedélyezd a helyhozzáférést a böngésző beállításában (PunktePass app → Berechtigungen → Standort) és frissítsd az oldalt.'
        : 'Helymeghatározás sikertelen: ' + err.message;
      alert(msg);
    },
    { enableHighAccuracy: true, timeout: 30000, maximumAge: 300000 }
  );
}
document.getElementById('km-locate').addEventListener('click', function(e) {
  e.preventDefault();
  locateMe();
});
// Try automatically once on load (silent)
setTimeout(locateMe, 500);

let allFeatures = [];
let pinMarkers = [];

async function loadFeatures() {
  try {
    const r = await fetch('/wp-json/punktepass/v1/map/nearby', { credentials: 'include' });
    const d = await r.json();
    allFeatures = d.features || [];
    if (allFeatures.length && !userMarker) {
      map.flyTo({ center: [allFeatures[0].lng, allFeatures[0].lat], zoom: 12 });
    }
    renderPins();
  } catch (e) { console.error(e); }
}

function shortName(name) {
  const t = String(name||'').trim();
  if (!t) return '?';
  if (t.length <= 8) return t;
  const words = t.split(/\s+/);
  if (words.length >= 2) {
    return words.slice(0,2).map(w => w.slice(0,4)).join(' ');
  }
  return t.slice(0,7) + '…';
}

function makePinEl(f) {
  const div = document.createElement('div');
  div.className = 'km-pin ' + f.type + (f.featured ? ' featured' : '');
  div.style.pointerEvents = 'auto';
  div.title = f.name || '';
  let inner;
  if (f.logo) {
    inner = `<div class="ring" style="pointer-events:auto;"><img src="${f.logo}" style="width:100%;height:100%;object-fit:cover;pointer-events:none"></div>`;
  } else {
    inner = `<div class="ring no-logo" style="pointer-events:auto;"><span class="name-init" style="pointer-events:none">${escapeHtml(shortName(f.name))}</span></div>`;
  }
  div.innerHTML = inner;
  return div;
}

function matchesQuery(f, q) {
  if (!q) return true;
  const hay = ((f.name||'') + ' ' + (f.address||'') + ' ' + (f.city||'')).toLowerCase();
  return hay.includes(q);
}

function renderPins() {
  pinMarkers.forEach(m => m.remove());
  pinMarkers = [];
  const cat = document.getElementById('km-cat').value;
  const q = (document.getElementById('km-search').value || '').toLowerCase().trim();
  allFeatures.forEach(f => {
    if (cat && f.type !== cat) return;
    if (!matchesQuery(f, q)) return;
    const el = makePinEl(f);
    const m = new maplibregl.Marker({ element: el, anchor:'center' })
      .setLngLat([f.lng, f.lat]).addTo(map);
    const handler = (ev) => { ev.stopPropagation(); ev.preventDefault(); openSheet(f); };
    el.addEventListener('click', handler);
    el.addEventListener('touchend', handler);
    pinMarkers.push(m);
  });
  renderSuggestions(q);
}

function highlight(text, q) {
  if (!q) return escapeHtml(text||'');
  const t = String(text||'');
  const idx = t.toLowerCase().indexOf(q);
  if (idx < 0) return escapeHtml(t);
  return escapeHtml(t.slice(0,idx)) + '<span class="km-sugg-mark">' + escapeHtml(t.slice(idx,idx+q.length)) + '</span>' + escapeHtml(t.slice(idx+q.length));
}

function renderSuggestions(q) {
  const panel = document.getElementById('km-suggestions');
  if (!q) { panel.classList.remove('open'); panel.innerHTML = ''; return; }
  const cat = document.getElementById('km-cat').value;
  const matches = allFeatures.filter(f => (!cat || f.type === cat) && matchesQuery(f, q)).slice(0, 12);
  if (!matches.length) {
    panel.innerHTML = '<div class="km-sugg-empty">' + (T.no_results || 'Nincs találat') + '</div>';
    panel.classList.add('open');
    return;
  }
  panel.innerHTML = matches.map(f => {
    const iconCls = f.type === 'advertiser' ? 'advertiser' : '';
    const iconHtml = f.type === 'advertiser' ? '<i class="ri-megaphone-fill"></i>' : '<i class="ri-store-2-fill"></i>';
    const addrParts = [f.address, f.city].filter(Boolean).join(', ');
    return `<div class="km-sugg-item" onclick="pickSuggestion('${f.type}',${f.id})">
      <div class="km-sugg-icon ${iconCls}">${iconHtml}</div>
      <div class="km-sugg-body">
        <div class="km-sugg-name">${highlight(f.name, q)}</div>
        ${addrParts ? `<div class="km-sugg-addr">${highlight(addrParts, q)}</div>` : ''}
      </div>
    </div>`;
  }).join('');
  panel.classList.add('open');
}

window.pickSuggestion = function(type, id) {
  const f = allFeatures.find(x => x.id === id && x.type === type);
  if (!f) return;
  document.getElementById('km-suggestions').classList.remove('open');
  document.getElementById('km-search').blur();
  map.flyTo({ center:[f.lng, f.lat], zoom:15, duration:600 });
  setTimeout(() => openSheet(f), 350);
};

async function openSheet(f) {
  const sheet = document.getElementById('km-sheet');
  const isLoyalty = f.type === 'loyalty';
  const tagClass = isLoyalty ? 'loyalty' : 'advertiser';
  const tagLabel = isLoyalty ? '🎁 ' + T.points_here : '📣 ' + T.offers;
  const followLabel = f.following
    ? '<i class="ri-check-line"></i><span>' + T.following + '</span>'
    : '<i class="ri-heart-add-line"></i><span>' + T.follow + '</span>';
  const followClass = f.following ? 'km-fol-pill active' : 'km-fol-pill';

  const followBanner = isLoyalty
    ? '<i class="ri-notification-3-fill" style="margin-right:4px;"></i> ' + (T.follow_banner || 'Kövesd a boltot, ne maradj le akciókról')
    : '<i class="ri-megaphone-fill" style="margin-right:4px;"></i> ' + (T.follow_banner_ad || 'Kövesd, ne maradj le akciókról és nyereményekről');

  // Skeleton render first
  sheet.innerHTML = `
    <div class="km-sheet-grab" onclick="closeSheet()"></div>
    <div class="km-cover ${f.type}">${followBanner}</div>
    <div class="km-card-head">
      ${f.logo
        ? `<div class="km-card-logo" style="background-image:url('${f.logo}')"></div>`
        : `<div class="km-card-logo no-logo ${f.type}"><span class="km-logo-text">${escapeHtml(f.name||'')}</span></div>`}
    </div>
    <div class="km-card-title">
      <span class="name">${f.name}</span>
      <button class="${followClass}" onclick="toggleFollow('${f.type}',${f.id},this)">${followLabel}</button>
    </div>
    <div class="km-card-sub">
      <span class="km-card-tag ${tagClass}">${tagLabel}</span>
      ${f.address ? f.address : ''}
    </div>
    <div id="km-rich-content" style="padding:0 18px;color:#6b7280;font-size:13px;">⏳</div>
    <div class="km-card-actions">
      ${f.phone ? `<a class="call" href="tel:${f.phone}"><i class="ri-phone-fill"></i><span>${T.call}</span></a>` : ''}
      ${f.whatsapp ? `<a class="wa" href="https://wa.me/${f.whatsapp.replace(/[^0-9]/g,'')}"><i class="ri-whatsapp-fill"></i><span>${T.whatsapp}</span></a>` : ''}
      <a class="dir" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=${f.lat},${f.lng}"><i class="ri-route-fill"></i><span>${T.directions}</span></a>
    </div>
  `;
  sheet.classList.add('open');

  // Lazy-fetch rich data
  if (isLoyalty) {
    try {
      const r = await fetch('/wp-json/ppv/v1/stores/list-optimized', { credentials: 'include' });
      const d = await r.json();
      const list = Array.isArray(d) ? d : (d.stores || []);
      const store = list.find(s => s.id == f.id);
      if (store) renderLoyaltyRich(store);
      else { document.getElementById('km-rich-content').innerHTML = '<div style="color:#dc2626">Bolt adatok nem találhatók ('+f.id+')</div>'; }
    } catch(e) { console.error(e); }
  } else {
    // Fetch advertiser ads
    try {
      const r = await fetch('/wp-json/punktepass/v1/advertiser/' + f.id, { credentials: 'include' });
      const d = await r.json();
      if (d && d.ads) renderAdvertiserRich(d);
    } catch(e) { console.error(e); }
  }
}

function escapeHtml(s) { return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function renderLoyaltyRich(s) {
  const el = document.getElementById('km-rich-content');
  if (!el) return;
  const currencyMap = { 'DE': '€', 'HU': 'Ft', 'RO': 'RON' };
  const cur = currencyMap[s.country] || '€';
  const lang = LANG;
  const Tloc = {
    de: { open:'Geöffnet', closed:'Geschlossen', rewards:'Belohnungen', points:'Punkte', required:'Benötigt', reward:'Belohnung', perscan:'Pro Scan', valid:'Gültig bis', vip:'VIP-Bonus', call:'Anrufen', email:'E-Mail', web:'Webseite', route:'Route' },
    hu: { open:'Nyitva', closed:'Zárva', rewards:'Jutalmak', points:'pont', required:'Szükséges', reward:'Jutalom', perscan:'Scan/Beolvasás', valid:'Érvényes', vip:'VIP-Bónusz', call:'Hívás', email:'Email', web:'Weboldal', route:'Útvonal' },
    ro: { open:'Deschis', closed:'Închis', rewards:'Recompense', points:'puncte', required:'Necesar', reward:'Recompensă', perscan:'Pe scan', valid:'Valid', vip:'Bonus VIP', call:'Sună', email:'Email', web:'Site web', route:'Direcții' },
    en: { open:'Open', closed:'Closed', rewards:'Rewards', points:'pts', required:'Required', reward:'Reward', perscan:'Per scan', valid:'Valid', vip:'VIP Bonus', call:'Call', email:'Email', web:'Website', route:'Route' },
  };
  const Lt = Tloc[lang] || Tloc.de;

  const status = s.open_now
    ? `<span class="ppv-status-badge ppv-open"><i class="ri-checkbox-blank-circle-fill"></i> ${Lt.open}</span>`
    : `<span class="ppv-status-badge ppv-closed"><i class="ri-checkbox-blank-circle-fill"></i> ${Lt.closed}</span>`;
  const slogan = s.slogan ? `<p class="ppv-store-slogan">${escapeHtml(s.slogan)}</p>` : '';
  const hours = s.open_hours_today ? `<span class="ppv-hours"><i class="ri-time-line"></i> ${escapeHtml(s.open_hours_today)}</span>` : '';
  const addr = s.address ? `<span class="ppv-address"><i class="ri-map-pin-line"></i> ${escapeHtml(s.address)} ${escapeHtml(s.plz||'')} ${escapeHtml(s.city||'')}</span>` : '';

  const gallery = (s.gallery && s.gallery.length) ? `
    <div class="ppv-gallery-thumbs">
      ${s.gallery.map(img => `<img src="${escapeHtml(img)}" class="ppv-gallery-thumb" loading="lazy">`).join('')}
    </div>` : '';

  const social = (s.social && (s.social.facebook||s.social.instagram||s.social.tiktok)) ? `
    <div class="ppv-social-links">
      ${s.social.facebook ? `<a href="${escapeHtml(s.social.facebook)}" target="_blank" class="ppv-social-btn ppv-fb"><i class="ri-facebook-circle-fill"></i></a>` : ''}
      ${s.social.instagram ? `<a href="${escapeHtml(s.social.instagram)}" target="_blank" class="ppv-social-btn ppv-ig"><i class="ri-instagram-fill"></i></a>` : ''}
      ${s.social.tiktok ? `<a href="${escapeHtml(s.social.tiktok)}" target="_blank" class="ppv-social-btn ppv-tk"><i class="ri-tiktok-fill"></i></a>` : ''}
    </div>` : '';

  const rewards = (s.rewards||[]).length ? `
    <div class="ppv-store-rewards">
      <div class="ppv-rewards-header"><h5 style="margin:0;font-weight:600;color:#00e6ff;"><i class="ri-gift-line"></i> ${Lt.rewards}</h5></div>
      <div class="ppv-rewards-list">
        ${s.rewards.map(r => {
          const v = parseFloat(r.action_value).toFixed(0);
          let rt = '';
          if (r.action_type === 'discount_percent') rt = `${v}%`;
          else if (r.action_type === 'discount_fixed') rt = cur === '€' ? `${cur}${v}` : `${v} ${cur}`;
          else if (r.action_type === 'free_product') rt = escapeHtml(r.free_product || 'gratis');
          else rt = `${v} ${Lt.points}`;
          const end = r.end_date ? r.end_date.substring(0,10).split('-').reverse().join('.') : null;
          return `<div class="ppv-reward-mini">
            <div class="ppv-reward-header">
              <strong>${escapeHtml(r.title)}</strong>
              <span class="ppv-reward-badge">${r.required_points} ${Lt.points}</span>
            </div>
            <div class="ppv-reward-details">
              <div class="ppv-reward-row"><span class="ppv-reward-label"><i class="ri-gift-fill"></i> ${Lt.reward}</span><span class="ppv-reward-value"><strong style="color:#34d399;">${rt}</strong></span></div>
              <div class="ppv-reward-row"><span class="ppv-reward-label"><i class="ri-coins-line"></i> ${Lt.perscan}</span><span class="ppv-reward-value"><strong style="color:#00e6ff;">+${r.points_given||0} ${Lt.points}</strong></span></div>
              ${end ? `<div class="ppv-reward-row"><span class="ppv-reward-label"><i class="ri-calendar-line"></i> ${Lt.valid}</span><span class="ppv-reward-value"><strong style="color:#fbbf24;">${end}</strong></span></div>` : ''}
            </div>
          </div>`;
        }).join('')}
      </div>
    </div>` : '';

  const vipTbl = s.vip ? (function() {
    const v = s.vip;
    const rows = [];
    if (v.fix && v.fix.enabled) {
      rows.push(`<tr class="ppv-vip-table-row">
        <td class="ppv-vip-label-cell"><i class="ri-add-circle-line"></i> Fix</td>
        <td class="ppv-vip-cell bronze">+${v.fix.bronze}</td><td class="ppv-vip-cell silver">+${v.fix.silver}</td>
        <td class="ppv-vip-cell gold">+${v.fix.gold}</td><td class="ppv-vip-cell platinum">+${v.fix.platinum}</td>
      </tr>`);
    }
    if (v.streak && v.streak.enabled) {
      const m = v.streak.type === 'double' ? '2x' : v.streak.type === 'triple' ? '3x' : null;
      rows.push(m
        ? `<tr class="ppv-vip-table-row"><td class="ppv-vip-label-cell"><i class="ri-fire-line"></i> ${v.streak.count}.</td><td class="ppv-vip-cell ppv-vip-multiplier" colspan="4">${m}</td></tr>`
        : `<tr class="ppv-vip-table-row"><td class="ppv-vip-label-cell"><i class="ri-fire-line"></i> ${v.streak.count}.</td><td class="ppv-vip-cell bronze">+${v.streak.bronze}</td><td class="ppv-vip-cell silver">+${v.streak.silver}</td><td class="ppv-vip-cell gold">+${v.streak.gold}</td><td class="ppv-vip-cell platinum">+${v.streak.platinum}</td></tr>`);
    }
    return rows.length ? `<div class="ppv-store-vip-table">
      <div class="ppv-vip-table-title"><i class="ri-vip-crown-fill"></i> ${Lt.vip}</div>
      <table class="ppv-vip-mini-table"><thead><tr><th></th><th class="ppv-vip-th bronze">Bronze</th><th class="ppv-vip-th silver">Silver</th><th class="ppv-vip-th gold">Gold</th><th class="ppv-vip-th platinum">Platin</th></tr></thead><tbody>${rows.join('')}</tbody></table>
    </div>` : '';
  })() : '';

  // Modern light-theme rendering
  const statusM = s.open_now
    ? `<span class="mc-status open">${Lt.open}</span>`
    : `<span class="mc-status closed">${Lt.closed}</span>`;

  const galleryImages = (s.gallery || []).map(escapeHtml);
  const galleryM = galleryImages.length ? `
    <div style="padding:0 18px;">
      <div class="mc-gallery">
        ${galleryImages.map((img, i) => `<img src="${img}" loading="lazy" data-gallery-idx="${i}" onclick="openLightbox(${i})">`).join('')}
      </div>
    </div>` : '';
  window.__kmGallery = galleryImages;

  const socialM = (s.social && (s.social.facebook||s.social.instagram||s.social.tiktok) || s.website || s.public_email) ? `
    <div class="mc-social">
      ${s.social && s.social.facebook  ? `<a href="${escapeHtml(s.social.facebook)}"  target="_blank" title="Facebook"><i class="ri-facebook-circle-fill" style="color:#1877f2;"></i></a>` : ''}
      ${s.social && s.social.instagram ? `<a href="${escapeHtml(s.social.instagram)}" target="_blank" title="Instagram"><i class="ri-instagram-fill" style="color:#e4405f;"></i></a>` : ''}
      ${s.social && s.social.tiktok    ? `<a href="${escapeHtml(s.social.tiktok)}"    target="_blank" title="TikTok"><i class="ri-tiktok-fill" style="color:#000;"></i></a>` : ''}
      ${s.website     ? `<a href="${escapeHtml(s.website)}"     target="_blank" title="Web"><i class="ri-global-line"></i></a>` : ''}
      ${s.public_email? `<a href="mailto:${escapeHtml(s.public_email)}" title="Email"><i class="ri-mail-line"></i></a>` : ''}
    </div>` : '';

  const sloganM = s.slogan ? `<p style="margin:6px 0 0;color:#6b7280;font-style:italic;font-size:13px;">${escapeHtml(s.slogan)}</p>` : '';
  const hoursM = s.open_hours_today ? `<div class="mc-row"><i class="ic ri-time-line"></i><span>${escapeHtml(s.open_hours_today)}</span></div>` : '';
  const addrM = s.address ? `<div class="mc-row"><i class="ic ri-map-pin-line"></i><span>${escapeHtml(s.address)} ${escapeHtml(s.plz||'')} ${escapeHtml(s.city||'')}</span></div>` : '';
  const phoneM = s.phone ? `<div class="mc-row"><i class="ic ri-phone-line"></i><a href="tel:${escapeHtml(s.phone)}" style="color:#3b82f6;text-decoration:none;">${escapeHtml(s.phone)}</a></div>` : '';

  const rewardsM = (s.rewards||[]).length ? `
    <div class="mc-section">
      <div class="mc-h3"><i class="ri-gift-line"></i> ${Lt.rewards} (${s.rewards.length})</div>
      <div class="mc-rewards">
        ${s.rewards.map(r => {
          const v = parseFloat(r.action_value||0).toFixed(0);
          let rt = '';
          if (r.action_type === 'discount_percent') rt = `${v}%`;
          else if (r.action_type === 'discount_fixed') rt = cur === '€' ? `${cur}${v}` : `${v} ${cur}`;
          else if (r.action_type === 'free_product') rt = escapeHtml(r.free_product || 'gratis');
          else rt = `${v}`;
          const end = r.end_date ? r.end_date.substring(0,10).split('-').reverse().join('.') : null;
          return `<div class="mc-reward">
            <div class="mc-reward-title">
              <span>${escapeHtml(r.title)}</span>
              <span class="mc-reward-pts">${r.required_points} ${Lt.points}</span>
            </div>
            <div class="mc-reward-meta">
              <span><i class="ri-gift-fill"></i> ${rt}</span>
              <span><i class="ri-coin-line"></i> +${r.points_given||0}/scan</span>
              ${end ? `<span><i class="ri-calendar-line"></i> ${end}</span>` : ''}
            </div>
          </div>`;
        }).join('')}
      </div>
    </div>` : '';

  const vipTable = s.vip ? (function() {
    const v = s.vip;
    const rows = [];
    if (v.fix && v.fix.enabled) {
      rows.push(`<tr>
        <td class="row-label"><i class="ri-add-circle-line"></i> Fix</td>
        <td class="bronze">+${v.fix.bronze}</td>
        <td class="silver">+${v.fix.silver}</td>
        <td class="gold">+${v.fix.gold}</td>
        <td class="platinum">+${v.fix.platinum}</td>
      </tr>`);
    }
    if (v.streak && v.streak.enabled) {
      const m = v.streak.type === 'double' ? '2x' : v.streak.type === 'triple' ? '3x' : null;
      rows.push(m
        ? `<tr><td class="row-label"><i class="ri-fire-line"></i> ${v.streak.count}. scan</td><td class="mult" colspan="4">${m}</td></tr>`
        : `<tr><td class="row-label"><i class="ri-fire-line"></i> ${v.streak.count}. scan</td><td class="bronze">+${v.streak.bronze}</td><td class="silver">+${v.streak.silver}</td><td class="gold">+${v.streak.gold}</td><td class="platinum">+${v.streak.platinum}</td></tr>`);
    }
    return rows.length ? `
      <div class="mc-section">
        <div class="mc-h3"><i class="ri-vip-crown-fill"></i> ${Lt.vip}</div>
        <table class="mc-vip-table">
          <thead>
            <tr>
              <th></th>
              <th class="bronze"><i class="ri-medal-line"></i><div>Bronze</div></th>
              <th class="silver"><i class="ri-medal-line"></i><div>Silver</div></th>
              <th class="gold"><i class="ri-medal-fill"></i><div>Gold</div></th>
              <th class="platinum"><i class="ri-vip-crown-fill"></i><div>Platin</div></th>
            </tr>
          </thead>
          <tbody>${rows.join('')}</tbody>
        </table>
      </div>` : '';
  })() : '';

  const ratingM = s.rating_count > 0 ? `<div style="display:inline-flex;align-items:center;gap:4px;margin-top:4px;font-size:13px;"><i class="ri-star-fill" style="color:#fbbf24;"></i> <strong>${(s.rating_avg||0).toFixed(1)}</strong> <span style="color:#9ca3af;">(${s.rating_count})</span></div>` : '';

  el.innerHTML = `
    <div style="padding:0 18px 8px;">
      ${statusM}
      ${ratingM}
      ${sloganM}
    </div>
    ${galleryM}
    <div class="mc-section">
      ${hoursM}
      ${addrM}
      ${phoneM}
      ${socialM}
    </div>
    ${rewardsM}
    ${vipTable}
  `;
}

function renderAdvertiserRich(d) {
  const el = document.getElementById('km-rich-content');
  if (!el) return;
  const desc = d.description ? `<p>${d.description}</p>` : '';
  const ads = (d.ads || []).map(a => {
    const title = (a.title || '').trim() || '—';
    const body  = (a.body  || '').trim();
    const promo = a.promo_value ? `<span style="display:inline-block;background:#ef4444;color:#fff;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;margin-left:6px;">${escapeHtml(a.promo_value)}</span>` : '';
    const folOnly = a.followers_only ? `<span style="display:inline-block;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;margin-left:6px;">⭐ Follower</span>` : '';
    const hasCoupon = a.coupon_code && (a.coupon_code + '').trim().length > 0;
    const soldOut = (a.max_claims !== null && a.max_claims !== undefined) && (a.remaining === 0);
    const remainBadge = (a.max_claims !== null && a.max_claims !== undefined)
      ? `<span style="display:inline-block;background:${soldOut?'#dc2626':'#10b981'};color:#fff;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;margin-left:6px;">${soldOut ? (T.sold_out||'Kifogyott') : (a.remaining + '/' + a.max_claims + ' ' + (T.remaining||'maradt'))}</span>`
      : '';
    let redeemBtn = '';
    if (hasCoupon && !soldOut) {
      const argShop  = JSON.stringify(d.name || '').replace(/"/g,'&quot;');
      const argTitle = JSON.stringify(title).replace(/"/g,'&quot;');
      const argBody  = JSON.stringify(body).replace(/"/g,'&quot;');
      const argPromo = JSON.stringify(a.promo_value || '').replace(/"/g,'&quot;');
      redeemBtn = `<button onclick="claimCoupon(${a.id}, ${argShop}, ${argTitle}, ${argBody}, ${argPromo})" style="margin-top:8px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;width:100%;display:flex;align-items:center;justify-content:center;gap:6px;"><i class="ri-coupon-3-line"></i> ${T.redeem || 'Beváltás'}</button>`;
    } else if (hasCoupon && soldOut) {
      redeemBtn = `<div style="margin-top:8px;background:#fee2e2;color:#991b1b;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-align:center;">${T.sold_out_msg || 'Sajnos minden kupon elfogyott.'}</div>`;
    }
    return `
    <div style="background:#f9fafb;border-radius:8px;padding:10px;margin-top:8px;">
      ${a.image_url ? `<img src="${escapeHtml(a.image_url)}" style="width:100%;border-radius:6px;max-height:120px;object-fit:cover;margin-bottom:6px;">` : ''}
      <strong>${escapeHtml(title)}</strong>${promo}${folOnly}${remainBadge}
      ${body ? `<p style="margin:4px 0 0;color:#6b7280;font-size:12px;">${escapeHtml(body)}</p>` : ''}
      ${redeemBtn}
    </div>`;
  }).join('');
  el.innerHTML = desc + ads;
}
window.closeSheet = function() { document.getElementById('km-sheet').classList.remove('open'); };
window.toggleFollow = async function(type, id, btn) {
  const wasFollowing = btn.classList.contains('active');
  const action = wasFollowing ? 'unfollow' : 'follow';
  btn.disabled = true;
  try {
    const r = await fetch('/wp-json/punktepass/v1/follow', {
      method:'POST', credentials:'include',
      headers: {'Content-Type':'application/json', 'X-WP-Nonce': window.wpRestNonce || ''},
      body: JSON.stringify({ type, target_id: id, action }),
    });
    const d = await r.json();
    if (d.ok) {
      btn.classList.toggle('active', d.following);
      btn.innerHTML = d.following
        ? '<i class="ri-check-line"></i><span>' + T.following + '</span>'
        : '<i class="ri-heart-add-line"></i><span>' + T.follow + '</span>';
      const f = allFeatures.find(x => x.id === id && x.type === type);
      if (f) f.following = d.following;
    }
  } catch(e) { alert(e.message); }
  btn.disabled = false;
};

window.__kmLightboxIdx = 0;
window.openLightbox = function(idx) {
  const imgs = window.__kmGallery || [];
  if (!imgs.length) return;
  window.__kmLightboxIdx = idx;
  document.getElementById('km-lightbox-img').src = imgs[idx];
  document.getElementById('km-lightbox-counter').textContent = (idx+1) + ' / ' + imgs.length;
  const lb = document.getElementById('km-lightbox');
  lb.classList.add('open');
  const navs = lb.querySelectorAll('.km-lightbox-nav');
  navs.forEach(n => n.style.display = imgs.length > 1 ? 'flex' : 'none');
};
window.closeLightbox = function(e) {
  if (e && e.target && e.target.id === 'km-lightbox-img') return;
  document.getElementById('km-lightbox').classList.remove('open');
};
window.lightboxNav = function(e, dir) {
  if (e) e.stopPropagation();
  const imgs = window.__kmGallery || [];
  if (!imgs.length) return;
  window.__kmLightboxIdx = (window.__kmLightboxIdx + dir + imgs.length) % imgs.length;
  document.getElementById('km-lightbox-img').src = imgs[window.__kmLightboxIdx];
  document.getElementById('km-lightbox-counter').textContent = (window.__kmLightboxIdx+1) + ' / ' + imgs.length;
};

// Coupon claim — atomically reserves one of the limited coupons, then opens modal with code
window.claimCoupon = async function(adId, shopName, title, body, promoValue) {
  try {
    const r = await fetch('/wp-json/punktepass/v1/coupon-claim', {
      method: 'POST', credentials: 'include',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ ad_id: adId })
    });
    if (r.status === 410) {
      alert(T.sold_out_msg || 'Sajnos minden kupon elfogyott.');
      return;
    }
    if (!r.ok) { alert('Hiba: ' + r.status); return; }
    const d = await r.json();
    if (!d.ok) { alert('Hiba: ' + (d.message || 'unknown')); return; }
    showCouponModal(adId, shopName, title, body, d.coupon_code, promoValue, d.remaining);
  } catch (e) { alert('Hiba: ' + e.message); }
};

// Coupon modal — shown when user taps "Beváltás" on a map ad
window.showCouponModal = function(adId, shopName, title, body, code, promoValue, remaining) {
  document.getElementById('km-coupon-shop').textContent = shopName || '';
  document.getElementById('km-coupon-title').textContent = title || '';
  document.getElementById('km-coupon-code').textContent = code || '';
  document.getElementById('km-coupon-body').textContent = body || '';
  const promoEl = document.getElementById('km-coupon-promo');
  if (promoValue) { promoEl.textContent = promoValue; promoEl.style.display = 'inline-block'; }
  else { promoEl.style.display = 'none'; }
  document.getElementById('km-coupon-modal').style.display = 'flex';
};
window.closeCouponModal = function() {
  document.getElementById('km-coupon-modal').style.display = 'none';
};
document.addEventListener('keydown', (e) => {
  const lb = document.getElementById('km-lightbox');
  if (!lb.classList.contains('open')) return;
  if (e.key === 'Escape') closeLightbox();
  else if (e.key === 'ArrowLeft') lightboxNav(null, -1);
  else if (e.key === 'ArrowRight') lightboxNav(null, 1);
});
// Touch swipe
let lbTouchX = null;
document.getElementById('km-lightbox').addEventListener('touchstart', e => { lbTouchX = e.touches[0].clientX; }, {passive:true});
document.getElementById('km-lightbox').addEventListener('touchend', e => {
  if (lbTouchX === null) return;
  const dx = e.changedTouches[0].clientX - lbTouchX;
  if (Math.abs(dx) > 50) lightboxNav(null, dx < 0 ? 1 : -1);
  lbTouchX = null;
}, {passive:true});

document.getElementById('km-cat').addEventListener('change', renderPins);
document.getElementById('km-search').addEventListener('input', renderPins);
document.getElementById('km-search').addEventListener('focus', () => {
  const q = (document.getElementById('km-search').value || '').toLowerCase().trim();
  if (q) renderSuggestions(q);
});
document.addEventListener('click', (e) => {
  if (!e.target.closest('#km-suggestions') && !e.target.closest('#km-search')) {
    document.getElementById('km-suggestions').classList.remove('open');
  }
});
loadFeatures();

// Close sheet on map click (Leaflet)
map.on('click', () => closeSheet());
</script>
</body>
</html>
