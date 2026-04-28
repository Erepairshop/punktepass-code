<?php
if (!defined('ABSPATH')) exit;
$lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de';
$labels = [
    'de' => ['title'=>'Karte','search'=>'Suchen…','filter_all'=>'Alle','follow'=>'Folgen','following'=>'Folgst du','call'=>'Anrufen','whatsapp'=>'WhatsApp','directions'=>'Wegbeschreibung','no_pins'=>'Keine Geschäfte in dieser Region.','points_here'=>'Punkte sammeln hier','offers'=>'Aktuelle Angebote'],
    'hu' => ['title'=>'Térkép','search'=>'Keresés…','filter_all'=>'Mind','follow'=>'Követés','following'=>'Követed','call'=>'Hívás','whatsapp'=>'WhatsApp','directions'=>'Útvonal','no_pins'=>'Nincs üzlet ebben a régióban.','points_here'=>'Pontot gyűjthetsz','offers'=>'Aktuális ajánlatok'],
    'ro' => ['title'=>'Hartă','search'=>'Caută…','filter_all'=>'Toate','follow'=>'Urmărește','following'=>'Urmărești','call'=>'Sună','whatsapp'=>'WhatsApp','directions'=>'Direcții','no_pins'=>'Nu sunt magazine în această regiune.','points_here'=>'Adună puncte','offers'=>'Oferte curente'],
    'en' => ['title'=>'Map','search'=>'Search…','filter_all'=>'All','follow'=>'Follow','following'=>'Following','call'=>'Call','whatsapp'=>'WhatsApp','directions'=>'Directions','no_pins'=>'No shops in this area yet.','points_here'=>'Earn points here','offers'=>'Current offers'],
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
<style>
* { box-sizing:border-box; }
html,body { margin:0; height:100%; font:14px/1.5 system-ui,-apple-system,sans-serif; }
#map { height:100vh; width:100vw; }
.km-bar { position:absolute; top:10px; left:10px; right:10px; z-index:10; padding:8px 10px; background:#fff; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,.12); display:flex; gap:8px; align-items:center; }
.km-bar input, .km-bar select { padding:8px 10px; border:none; border-radius:6px; font:inherit; background:#f3f4f6; }
.km-bar input { flex:1; min-width:120px; }
.km-bar strong { font-size:18px; }
.km-locate-btn { position:absolute; bottom:18px; right:18px; z-index:10; width:48px; height:48px; border-radius:50%; background:#fff; border:none; box-shadow:0 4px 12px rgba(0,0,0,.2); cursor:pointer; font-size:22px; display:flex; align-items:center; justify-content:center; }
.km-locate-btn:hover { background:#f3f4f6; }
.km-locate-btn.active { color:#3b82f6; }
.km-locate-btn.error { color:#dc2626; }
/* pin */
.km-pin { width:48px; height:48px; cursor:pointer; }
.km-pin.loyalty { --c: #3b82f6; }
.km-pin.advertiser { --c: #f59e0b; }
.km-pin.featured  { --c: #facc15; }
.km-pin .ring { width:48px; height:48px; border-radius:50%; background:#fff; border:3px solid var(--c); box-shadow:0 4px 12px rgba(0,0,0,.25); overflow:hidden; display:flex; align-items:center; justify-content:center; transition:transform .15s; }
.km-pin:hover .ring { transform:scale(1.12); }
.km-pin .ring img { width:100%; height:100%; object-fit:cover; }
.km-pin .ring .ico { font-size:22px; }
.km-pin .badge { position:absolute; top:-4px; right:-4px; background:var(--c); color:#fff; font-size:10px; padding:2px 5px; border-radius:8px; font-weight:700; }
/* card */
.km-sheet { position:absolute; left:0; right:0; bottom:-100%; max-height:75vh; overflow:auto; background:#fff; border-radius:18px 18px 0 0; box-shadow:0 -8px 32px rgba(0,0,0,.25); transition:bottom .3s ease; z-index:20; }
.km-sheet.open { bottom:0; }
.km-sheet-grab { width:48px; height:5px; background:#d1d5db; border-radius:3px; margin:8px auto; }
.km-cover { height:120px; background:linear-gradient(135deg,#6366f1,#8b5cf6) center/cover; }
.km-cover.advertiser { background:linear-gradient(135deg,#f59e0b,#dc2626); }
.km-card-head { padding:0 18px; margin-top:-40px; display:flex; gap:12px; align-items:flex-end; }
.km-card-logo { width:80px; height:80px; border-radius:16px; background:#fff center/cover; border:4px solid #fff; box-shadow:0 4px 12px rgba(0,0,0,.12); }
.km-card-title { padding:8px 18px 6px; font-size:20px; font-weight:700; }
.km-card-sub { padding:0 18px; color:#6b7280; font-size:13px; }
.km-card-tag { display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:600; margin:0 4px 4px 0; }
.km-card-tag.loyalty { background:#dbeafe; color:#1e3a8a; }
.km-card-tag.advertiser { background:#fef3c7; color:#92400e; }
.km-card-actions { display:flex; gap:8px; padding:14px 18px; flex-wrap:wrap; }
.km-card-actions a, .km-card-actions button { flex:1 1 30%; padding:10px 12px; border-radius:10px; text-align:center; font-weight:600; font-size:13px; text-decoration:none; border:none; cursor:pointer; }
.km-card-actions .call { background:#10b981; color:#fff; }
.km-card-actions .wa   { background:#25d366; color:#fff; }
.km-card-actions .dir  { background:#3b82f6; color:#fff; }
.km-card-actions .fol  { background:#6366f1; color:#fff; }
.km-card-actions .fol.active { background:#10b981; }
.km-card-body { padding:0 18px 24px; color:#374151; }
.km-offers { padding:0 18px 18px; }
.km-offers .row { display:flex; overflow-x:auto; gap:10px; scroll-snap-type:x mandatory; padding:6px 0; }
.km-offer { flex:0 0 80%; scroll-snap-align:start; background:#f9fafb; border-radius:12px; overflow:hidden; }
.km-offer img { width:100%; height:120px; object-fit:cover; }
.km-offer .body { padding:10px 12px; }
.km-offer h4 { margin:0 0 4px; font-size:14px; }
.km-offer p { margin:0; font-size:12px; color:#6b7280; }
</style>
</head>
<body>
<div id="map"></div>
<div class="km-bar">
  <strong>📍</strong>
  <input type="text" id="km-search" placeholder="<?php echo esc_attr($L['search']); ?>">
  <select id="km-cat">
    <option value=""><?php echo esc_html($L['filter_all']); ?></option>
    <option value="loyalty">🎁 Loyalty</option>
    <option value="advertiser">📣 Akciók</option>
  </select>
</div>
<button class="km-locate-btn" id="km-locate" title="📍 Helyzetem">📍</button>
<div id="km-sheet" class="km-sheet"></div>
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script>
const L = <?php echo wp_json_encode($L); ?>;
const LANG = <?php echo wp_json_encode($lang); ?>;
const DEFAULT_CENTER = [22.4633, 47.6822]; // Carei, RO

const map = new maplibregl.Map({
  container: 'map',
  style: 'https://tiles.openfreemap.org/styles/liberty',
  center: DEFAULT_CENTER,
  zoom: 12,
  attributionControl: { compact: true },
  pitchWithRotate: false,
  dragRotate: false,
});
map.addControl(new maplibregl.NavigationControl({ visualizePitch: false, showCompass: false }), 'right');

let userMarker = null;
function showUser(lat, lng) {
  const el = document.createElement('div');
  el.style.cssText = 'width:18px;height:18px;border-radius:50%;background:#3b82f6;border:3px solid #fff;box-shadow:0 0 0 6px rgba(59,130,246,.3);';
  if (userMarker) userMarker.remove();
  userMarker = new maplibregl.Marker({ element: el }).setLngLat([lng, lat]).addTo(map);
  map.flyTo({ center: [lng, lat], zoom: 14 });
}
function locateMe() {
  const btn = document.getElementById('km-locate');
  if (!navigator.geolocation) {
    btn.classList.add('error');
    btn.textContent = '✕';
    alert('A böngésző nem támogatja a geolocation-t.');
    return;
  }
  btn.textContent = '⏳';
  navigator.geolocation.getCurrentPosition(
    p => {
      showUser(p.coords.latitude, p.coords.longitude);
      btn.classList.add('active');
      btn.textContent = '📍';
    },
    err => {
      btn.classList.add('error');
      btn.textContent = '⚠️';
      const msg = err.code === 1
        ? 'Engedélyezd a helyhozzáférést a böngésző beállításában (PunktePass app → Berechtigungen → Standort) és frissítsd az oldalt.'
        : 'Helymeghatározás sikertelen: ' + err.message;
      alert(msg);
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
  );
}
document.getElementById('km-locate').addEventListener('click', locateMe);
// Try automatically once on load
locateMe();

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

function makePinEl(f) {
  const div = document.createElement('div');
  div.className = 'km-pin ' + f.type + (f.featured ? ' featured' : '');
  const ico = f.type === 'loyalty' ? '🎁' : '📣';
  div.innerHTML = `<div class="ring">${f.logo ? `<img src="${f.logo}">` : `<span class="ico">${ico}</span>`}</div>`;
  return div;
}

function renderPins() {
  pinMarkers.forEach(m => m.remove());
  pinMarkers = [];
  const cat = document.getElementById('km-cat').value;
  const q = (document.getElementById('km-search').value || '').toLowerCase();
  allFeatures.forEach(f => {
    if (cat && f.type !== cat) return;
    if (q && !f.name.toLowerCase().includes(q)) return;
    const m = new maplibregl.Marker({ element: makePinEl(f), anchor:'center' })
      .setLngLat([f.lng, f.lat]).addTo(map);
    m.getElement().addEventListener('click', () => openSheet(f));
    pinMarkers.push(m);
  });
}

function openSheet(f) {
  const sheet = document.getElementById('km-sheet');
  const isLoyalty = f.type === 'loyalty';
  const tagClass = isLoyalty ? 'loyalty' : 'advertiser';
  const tagLabel = isLoyalty ? '🎁 ' + L.points_here : '📣 ' + L.offers;
  const followLabel = f.following ? ('✓ ' + L.following) : ('➕ ' + L.follow);
  const followClass = f.following ? 'fol active' : 'fol';
  sheet.innerHTML = `
    <div class="km-sheet-grab" onclick="closeSheet()"></div>
    <div class="km-cover ${f.type}"></div>
    <div class="km-card-head">
      <div class="km-card-logo" style="background-image:url('${f.logo || ''}')"></div>
    </div>
    <div class="km-card-title">${f.name}</div>
    <div class="km-card-sub">
      <span class="km-card-tag ${tagClass}">${tagLabel}</span>
      ${f.address ? f.address : ''}
    </div>
    <div class="km-card-actions">
      ${f.phone ? `<a class="call" href="tel:${f.phone}">📞 ${L.call}</a>` : ''}
      ${f.whatsapp ? `<a class="wa" href="https://wa.me/${f.whatsapp.replace(/[^0-9]/g,'')}">💬 ${L.whatsapp}</a>` : ''}
      <a class="dir" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=${f.lat},${f.lng}">🗺 ${L.directions}</a>
      <button class="${followClass}" onclick="toggleFollow('${f.type}',${f.id},this)">${followLabel}</button>
    </div>
  `;
  sheet.classList.add('open');
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
      btn.innerHTML = d.following ? ('✓ ' + L.following) : ('➕ ' + L.follow);
      const f = allFeatures.find(x => x.id === id && x.type === type);
      if (f) f.following = d.following;
    }
  } catch(e) { alert(e.message); }
  btn.disabled = false;
};

document.getElementById('km-cat').addEventListener('change', renderPins);
document.getElementById('km-search').addEventListener('input', renderPins);
loadFeatures();

// Close sheet on map click
map.on('click', () => closeSheet());
</script>
</body>
</html>
