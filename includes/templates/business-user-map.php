<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de';
$advertisers = PPV_Advertisers::get_active_ads_for_map($lang);
$pins = [];
foreach ($advertisers as $a) {
    $pins[] = [
        'id'   => (int)$a->id,
        'slug' => $a->slug,
        'name' => $a->business_name,
        'lat'  => (float)$a->lat,
        'lng'  => (float)$a->lng,
        'cat'  => $a->category ?? 'other',
        'logo' => $a->logo_url,
        'tier' => $a->tier,
        'feat' => (int)$a->featured,
    ];
}
$pins_json = wp_json_encode($pins);

$labels = [
    'de' => ['title'=>'Karte','search'=>'Suchen…','filter_all'=>'Alle','followers'=>'Folgen','call'=>'Anrufen','whatsapp'=>'WhatsApp','directions'=>'Wegbeschreibung','no_pins'=>'Keine Geschäfte in dieser Region.'],
    'hu' => ['title'=>'Térkép','search'=>'Keresés…','filter_all'=>'Mind','followers'=>'Követés','call'=>'Hívás','whatsapp'=>'WhatsApp','directions'=>'Útvonal','no_pins'=>'Nincs üzlet ebben a régióban.'],
    'ro' => ['title'=>'Hartă','search'=>'Caută…','filter_all'=>'Toate','followers'=>'Urmărește','call'=>'Sună','whatsapp'=>'WhatsApp','directions'=>'Direcții','no_pins'=>'Nu sunt magazine în această regiune.'],
    'en' => ['title'=>'Map','search'=>'Search…','filter_all'=>'All','followers'=>'Follow','call'=>'Call','whatsapp'=>'WhatsApp','directions'=>'Directions','no_pins'=>'No shops in this area yet.'],
];
$L = $labels[$lang] ?? $labels['de'];
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html($L['title']); ?> — PunktePass</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
* { box-sizing:border-box; }
html,body { margin:0; height:100%; font:14px/1.5 system-ui,sans-serif; }
.km-bar { padding:10px 16px; background:#fff; border-bottom:1px solid #e5e7eb; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.km-bar input, .km-bar select { padding:8px 10px; border:1px solid #e5e7eb; border-radius:6px; font:inherit; }
#map { height:calc(100vh - 60px); }
.km-popup { min-width:220px; }
.km-popup .logo { width:48px; height:48px; border-radius:8px; object-fit:cover; vertical-align:middle; margin-right:8px; }
.km-popup h3 { margin:6px 0; font-size:15px; display:inline-block; vertical-align:middle; }
.km-popup .actions { margin-top:8px; display:flex; gap:6px; flex-wrap:wrap; }
.km-popup .actions a { padding:6px 10px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:600; }
.km-popup .actions a.call { background:#10b981; color:#fff; }
.km-popup .actions a.wa { background:#25d366; color:#fff; }
.km-popup .actions a.dir { background:#3b82f6; color:#fff; }
.km-popup .actions a.fol { background:#6366f1; color:#fff; }
.km-popup .desc { font-size:12px; color:#6b7280; margin-top:4px; max-height:60px; overflow:hidden; }
.km-feat { background:gold; padding:2px 6px; font-size:10px; border-radius:4px; vertical-align:middle; margin-left:4px; }
</style>
</head>
<body>
<div class="km-bar">
  <strong style="margin-right:8px;">📍 <?php echo esc_html($L['title']); ?></strong>
  <input type="text" id="km-search" placeholder="<?php echo esc_attr($L['search']); ?>" style="flex:1; min-width:140px;">
  <select id="km-cat">
    <option value=""><?php echo esc_html($L['filter_all']); ?></option>
    <option value="food">🍔 Food / Étterem</option>
    <option value="cafe">☕ Café</option>
    <option value="retail">🛍 Retail</option>
    <option value="service">🔧 Service</option>
    <option value="beauty">💇 Beauty</option>
    <option value="auto">🚗 Auto</option>
    <option value="health">⚕️ Health</option>
  </select>
</div>
<div id="map"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const PINS = <?php echo $pins_json; ?>;
const LANG = <?php echo wp_json_encode($lang); ?>;
const L_LABEL = <?php echo wp_json_encode($L); ?>;

// Default center: Carei area (or Bucharest fallback)
const initLat = PINS.length ? PINS[0].lat : 47.7;
const initLng = PINS.length ? PINS[0].lng : 22.5;
const map = L.map('map').setView([initLat, initLng], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap',
  maxZoom: 19,
}).addTo(map);

let markers = [];

// Try geolocation
if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(p => {
    map.setView([p.coords.latitude, p.coords.longitude], 14);
    L.circleMarker([p.coords.latitude, p.coords.longitude], { radius:8, color:'#3b82f6', fillColor:'#60a5fa', fillOpacity:0.7 }).addTo(map);
  });
}

function renderPins(filter) {
  markers.forEach(m => map.removeLayer(m));
  markers = [];
  const cat = document.getElementById('km-cat').value;
  const q = (document.getElementById('km-search').value || '').toLowerCase();
  PINS.forEach(p => {
    if (cat && p.cat !== cat) return;
    if (q && !p.name.toLowerCase().includes(q)) return;
    const icon = L.divIcon({
      className:'',
      html: `<div style="width:42px;height:42px;border-radius:50%;background:#fff;border:3px solid ${p.feat?'gold':'#6366f1'};box-shadow:0 2px 6px rgba(0,0,0,.3);overflow:hidden;display:flex;align-items:center;justify-content:center;">${p.logo?`<img src="${p.logo}" style="width:100%;height:100%;object-fit:cover">`:'<span style="font-size:20px;">📍</span>'}</div>`,
      iconSize:[42,42],
      iconAnchor:[21,21],
    });
    const marker = L.marker([p.lat, p.lng], { icon }).addTo(map);
    marker.on('click', () => loadPopup(marker, p));
    markers.push(marker);
  });
}

function loadPopup(marker, p) {
  const featTag = p.feat ? `<span class="km-feat">⭐ Featured</span>` : '';
  const logo = p.logo ? `<img src="${p.logo}" class="logo" alt="">` : '';
  const callA = `<a class="call" href="/business/${p.slug}">${L_LABEL.call}</a>`;
  const dirA  = `<a class="dir" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=${p.lat},${p.lng}">${L_LABEL.directions}</a>`;
  const html = `<div class="km-popup">
    ${logo}<h3>${p.name}</h3>${featTag}
    <div class="actions">${callA} ${dirA}
      <a href="/business/${p.slug}" style="background:#6366f1;color:#fff;">${L_LABEL.followers} →</a>
    </div>
  </div>`;
  marker.bindPopup(html).openPopup();
}

document.getElementById('km-cat').addEventListener('change', () => renderPins());
document.getElementById('km-search').addEventListener('input', () => renderPins());
renderPins();

if (PINS.length === 0) {
  document.getElementById('map').innerHTML = '<div style="padding:40px; text-align:center; color:#6b7280;"><h2>📍 ' + L_LABEL.no_pins + '</h2></div>';
}
</script>
</body>
</html>
