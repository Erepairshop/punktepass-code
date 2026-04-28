<?php
if (!defined('ABSPATH')) exit;
$adv = PPV_Advertisers::current_advertiser();

$social = [];
if (!empty($adv->social)) {
  $decoded = json_decode($adv->social, true);
  if (is_array($decoded)) $social = $decoded;
}
$social_keys = [
  'facebook'  => ['Facebook',  'ri-facebook-circle-fill', '#1877f2', 'facebook.com/...'],
  'instagram' => ['Instagram', 'ri-instagram-fill',       '#e4405f', 'instagram.com/...'],
  'tiktok'    => ['TikTok',    'ri-tiktok-fill',          '#000',    'tiktok.com/@...'],
  'youtube'   => ['YouTube',   'ri-youtube-fill',         '#ff0000', 'youtube.com/...'],
  'twitter'   => ['X (Twitter)','ri-twitter-x-fill',      '#000',    'x.com/...'],
  'linkedin'  => ['LinkedIn',  'ri-linkedin-box-fill',    '#0077b5', 'linkedin.com/...'],
  'telegram'  => ['Telegram',  'ri-telegram-fill',        '#0088cc', 't.me/...'],
];

$gallery = [];
if (!empty($adv->gallery)) {
  $decoded = json_decode($adv->gallery, true);
  if (is_array($decoded)) $gallery = $decoded;
}

$eu_countries = [
  'AT'=>'Österreich','BE'=>'België/Belgique','BG'=>'България','HR'=>'Hrvatska','CY'=>'Κύπρος',
  'CZ'=>'Česko','DK'=>'Danmark','EE'=>'Eesti','FI'=>'Suomi','FR'=>'France','DE'=>'Deutschland',
  'GR'=>'Ελλάδα','HU'=>'Magyarország','IE'=>'Ireland','IT'=>'Italia','LV'=>'Latvija',
  'LT'=>'Lietuva','LU'=>'Luxembourg','MT'=>'Malta','NL'=>'Nederland','PL'=>'Polska',
  'PT'=>'Portugal','RO'=>'România','SK'=>'Slovensko','SI'=>'Slovenija','ES'=>'España','SE'=>'Sverige',
  'CH'=>'Schweiz','NO'=>'Norge','UK'=>'United Kingdom',
];
$current_country = $adv->country ?? '';
$is_eu = isset($eu_countries[$current_country]);
?>
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
<style>
.bz-section { background:var(--card); border:1px solid var(--border); border-radius:12px; margin-bottom:10px; overflow:hidden; }
.bz-section summary { padding:14px 16px; font-weight:600; cursor:pointer; list-style:none; display:flex; align-items:center; gap:10px; user-select:none; }
.bz-section summary::-webkit-details-marker { display:none; }
.bz-section summary i.head-ic { font-size:20px; color:var(--pp); }
.bz-section summary .ck { margin-left:auto; font-size:11px; color:#16a34a; font-weight:600; display:none; align-items:center; gap:3px; }
.bz-section summary .ck.show { display:inline-flex; }
.bz-section summary .arr { font-size:18px; color:var(--muted); transition:transform .2s; }
.bz-section[open] summary .arr { transform:rotate(180deg); }
.bz-section .bz-content { padding:0 16px 16px; }
.soc-row { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
.soc-row i.s-ic { font-size:20px; flex-shrink:0; width:24px; text-align:center; }
.soc-row input { flex:1; padding:8px 10px; border:1px solid var(--border); border-radius:7px; font:inherit; font-size:13px; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>

<div class="bz-card" style="padding:14px;">
  <h1 class="bz-h1" style="margin:0;"><?php echo esc_html($adv->business_name); ?></h1>
  <?php if (!empty($_GET['saved'])): ?><div class="bz-msg ok" style="margin-top:10px;">✓ Mentve</div><?php endif; ?>
  <?php if (!empty($_GET['welcome'])): ?><div class="bz-msg ok" style="margin-top:10px;">🎉 Töltsd ki a profilod hogy megjelenj a térképen!</div><?php endif; ?>
</div>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
  <?php wp_nonce_field('ppv_advertiser_save_profile'); ?>
  <input type="hidden" name="action" value="ppv_advertiser_save_profile">

  <!-- ALAP -->
  <details class="bz-section" open>
    <summary><i class="ri-store-2-line head-ic"></i> Alap adatok <span class="ck <?php echo $adv->business_name ? 'show' : ''; ?>"><i class="ri-checkbox-circle-fill"></i></span><i class="ri-arrow-down-s-line arr"></i></summary>
    <div class="bz-content">
      <label class="bz-label">Cégnév</label>
      <input type="text" name="business_name" class="bz-input" value="<?php echo esc_attr($adv->business_name); ?>" required style="margin-bottom:10px;">
      <label class="bz-label">Kategória</label>
      <select name="category" class="bz-select">
        <?php foreach (['food'=>'🍔 Étterem/Food','cafe'=>'☕ Café','retail'=>'🛍 Bolt','service'=>'🔧 Szerviz','beauty'=>'💇 Szépség','auto'=>'🚗 Autó','health'=>'⚕️ Egészség','other'=>'📦 Egyéb'] as $k=>$v): ?>
          <option value="<?php echo $k; ?>" <?php selected($adv->category, $k); ?>><?php echo esc_html($v); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </details>

  <!-- LOGO -->
  <details class="bz-section" <?php echo empty($adv->logo_url) ? 'open' : ''; ?>>
    <summary><i class="ri-image-line head-ic"></i> Logo <span class="ck <?php echo $adv->logo_url ? 'show' : ''; ?>"><i class="ri-checkbox-circle-fill"></i></span><i class="ri-arrow-down-s-line arr"></i></summary>
    <div class="bz-content">
      <div style="display:flex; align-items:center; gap:14px;">
        <div id="logoPreview" style="width:72px; height:72px; border-radius:14px; background:<?php echo $adv->logo_url ? "url('".esc_url($adv->logo_url)."') center/cover" : '#f3f4f6'; ?>; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:24px; border:1px solid var(--border);">
          <?php if (!$adv->logo_url): ?><i class="ri-image-line"></i><?php endif; ?>
        </div>
        <label class="bz-btn secondary" style="cursor:pointer;">
          <i class="ri-upload-2-line"></i> <?php echo $adv->logo_url ? 'Csere' : 'Feltöltés'; ?>
          <input type="file" name="logo_file" accept="image/*" style="display:none;" onchange="previewLogo(this)">
        </label>
      </div>
    </div>
  </details>

  <!-- GALÉRIA -->
  <details class="bz-section">
    <summary><i class="ri-gallery-line head-ic"></i> Galéria <span style="font-weight:400; color:var(--muted); font-size:12px;">(<?php echo count($gallery); ?>/8)</span><span class="ck <?php echo count($gallery) > 0 ? 'show' : ''; ?>"><i class="ri-checkbox-circle-fill"></i></span><i class="ri-arrow-down-s-line arr"></i></summary>
    <div class="bz-content">
      <div id="galleryGrid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(80px, 1fr)); gap:6px; margin-bottom:10px;">
        <?php foreach ($gallery as $img): ?>
          <div class="gal-item" style="position:relative; aspect-ratio:1; border-radius:8px; overflow:hidden; border:1px solid var(--border);">
            <img src="<?php echo esc_url($img); ?>" style="width:100%; height:100%; object-fit:cover;">
            <label style="position:absolute; top:3px; right:3px; background:rgba(0,0,0,.65); color:#fff; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:13px;">
              <input type="checkbox" name="gallery_remove[]" value="<?php echo esc_attr($img); ?>" style="display:none;" onchange="this.parentElement.parentElement.style.opacity = this.checked ? 0.3 : 1; this.parentElement.parentElement.querySelector('.gal-removed').style.display = this.checked ? 'flex' : 'none';">
              <i class="ri-close-line"></i>
            </label>
            <div class="gal-removed" style="display:none; position:absolute; inset:0; background:rgba(220,38,38,.85); color:#fff; align-items:center; justify-content:center; font-size:10px; font-weight:600;">TÖRÖLVE</div>
          </div>
        <?php endforeach; ?>
      </div>
      <label class="bz-btn secondary" style="cursor:pointer; font-size:13px;">
        <i class="ri-add-line"></i> Új képek
        <input type="file" name="gallery_files[]" accept="image/*" multiple style="display:none;" onchange="previewGallery(this)">
      </label>
      <div id="galleryNew" style="display:flex; gap:6px; margin-top:8px; flex-wrap:wrap;"></div>
    </div>
  </details>

  <!-- ELÉRHETŐSÉG -->
  <?php $contact_filled = !empty($adv->phone) || !empty($adv->whatsapp) || !empty($adv->website); ?>
  <details class="bz-section">
    <summary><i class="ri-phone-line head-ic"></i> Elérhetőség <span class="ck <?php echo $contact_filled ? 'show' : ''; ?>"><i class="ri-checkbox-circle-fill"></i></span><i class="ri-arrow-down-s-line arr"></i></summary>
    <div class="bz-content">
      <label class="bz-label">Telefon</label><input type="text" name="phone" class="bz-input" value="<?php echo esc_attr($adv->phone); ?>" style="margin-bottom:8px;">
      <label class="bz-label">WhatsApp</label><input type="text" name="whatsapp" class="bz-input" value="<?php echo esc_attr($adv->whatsapp); ?>" style="margin-bottom:8px;">
      <label class="bz-label">Weboldal</label><input type="url" name="website" class="bz-input" value="<?php echo esc_attr($adv->website); ?>">
    </div>
  </details>

  <!-- SOCIAL -->
  <?php $social_filled = !empty(array_filter($social)); ?>
  <details class="bz-section">
    <summary><i class="ri-share-line head-ic"></i> Közösségi <span class="ck <?php echo $social_filled ? 'show' : ''; ?>"><i class="ri-checkbox-circle-fill"></i></span><i class="ri-arrow-down-s-line arr"></i></summary>
    <div class="bz-content">
      <?php foreach ($social_keys as $key => [$name, $icon, $color, $ph]): ?>
        <div class="soc-row">
          <i class="<?php echo $icon; ?> s-ic" style="color:<?php echo $color; ?>;"></i>
          <input type="url" name="social[<?php echo $key; ?>]" placeholder="<?php echo esc_attr($name . ' — ' . $ph); ?>" value="<?php echo esc_attr($social[$key] ?? ''); ?>">
        </div>
      <?php endforeach; ?>
    </div>
  </details>

  <!-- CÍM + GPS -->
  <?php $loc_filled = !empty($adv->lat) && !empty($adv->lng); ?>
  <details class="bz-section" <?php echo !$loc_filled ? 'open' : ''; ?>>
    <summary><i class="ri-map-pin-line head-ic"></i> Cím + helyszín <span class="ck <?php echo $loc_filled ? 'show' : ''; ?>"><i class="ri-checkbox-circle-fill"></i></span><i class="ri-arrow-down-s-line arr"></i></summary>
    <div class="bz-content">
      <div class="bz-grid" style="margin-bottom:8px;">
        <div><label class="bz-label">Utca, házszám</label><input type="text" name="address" class="bz-input" value="<?php echo esc_attr($adv->address); ?>"></div>
        <div><label class="bz-label">Város</label><input type="text" name="city" class="bz-input" value="<?php echo esc_attr($adv->city); ?>"></div>
      </div>
      <div class="bz-grid" style="margin-bottom:8px;">
        <div>
          <label class="bz-label">Ország</label>
          <select name="country_select" id="countrySelect" class="bz-select" onchange="onCountryChange()">
            <option value="">— Válassz —</option>
            <?php foreach ($eu_countries as $code=>$name): ?>
              <option value="<?php echo $code; ?>" <?php selected($current_country, $code); ?>><?php echo esc_html($name); ?></option>
            <?php endforeach; ?>
            <option value="__other" <?php echo (!$is_eu && $current_country) ? 'selected' : ''; ?>>Egyéb (kézi)</option>
          </select>
          <input type="text" name="country_manual" id="countryManual" class="bz-input" placeholder="Add meg az ország nevét/kódját" value="<?php echo esc_attr(!$is_eu ? $current_country : ''); ?>" style="display:<?php echo (!$is_eu && $current_country) ? '' : 'none'; ?>; margin-top:6px;">
        </div>
        <div><label class="bz-label">Irányítószám</label><input type="text" name="postcode" class="bz-input" value="<?php echo esc_attr($adv->postcode); ?>"></div>
      </div>
      <div class="bz-grid" style="margin-bottom:8px;">
        <div><label class="bz-label">Szélesség (lat)</label><input type="text" name="lat" class="bz-input" value="<?php echo esc_attr($adv->lat); ?>" id="latInput" readonly style="background:#f3f4f6;"></div>
        <div><label class="bz-label">Hosszúság (lng)</label><input type="text" name="lng" class="bz-input" value="<?php echo esc_attr($adv->lng); ?>" id="lngInput" readonly style="background:#f3f4f6;"></div>
      </div>
      <div style="display:flex; gap:6px; flex-wrap:wrap;">
        <button type="button" id="geoBtn" class="bz-btn secondary" style="font-size:13px;"><i class="ri-search-line"></i> Cím → GPS</button>
        <button type="button" id="manualBtn" class="bz-btn secondary" style="font-size:13px;"><i class="ri-pushpin-line"></i> Pin térképen</button>
      </div>
      <div id="mapWrap" style="display:none; margin-top:10px;">
        <div style="font-size:11px; color:var(--muted); margin-bottom:6px;"><i class="ri-information-line"></i> Húzd a pin-t vagy kattints a pontos helyre</div>
        <div id="pickMap" style="width:100%; height:300px; border-radius:10px; overflow:hidden; border:1px solid var(--border);"></div>
      </div>
    </div>
  </details>

  <button type="submit" class="bz-btn full" style="margin-top:12px;"><i class="ri-save-line"></i> Mentés</button>
</form>

<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script>
const latInput = document.getElementById('latInput');
const lngInput = document.getElementById('lngInput');

function onCountryChange() {
  const sel = document.getElementById('countrySelect');
  const man = document.getElementById('countryManual');
  if (sel.value === '__other') {
    man.style.display = '';
    man.required = true;
  } else {
    man.style.display = 'none';
    man.required = false;
  }
}

function previewLogo(input) {
  const f = input.files[0];
  if (!f) return;
  const url = URL.createObjectURL(f);
  const el = document.getElementById('logoPreview');
  el.style.background = `url('${url}') center/cover`;
  el.innerHTML = '';
}

function previewGallery(input) {
  const wrap = document.getElementById('galleryNew');
  wrap.innerHTML = '';
  Array.from(input.files).slice(0, 8).forEach(f => {
    const url = URL.createObjectURL(f);
    const div = document.createElement('div');
    div.style.cssText = 'width:60px; height:60px; border-radius:8px; background:url('+url+') center/cover; border:2px solid #6366f1; position:relative;';
    div.innerHTML = '<div style="position:absolute; bottom:-2px; right:-2px; background:#6366f1; color:#fff; font-size:9px; padding:1px 5px; border-radius:6px; font-weight:700;">ÚJ</div>';
    wrap.appendChild(div);
  });
}

document.getElementById('geoBtn').addEventListener('click', async function() {
  const country = document.getElementById('countrySelect').value === '__other'
    ? document.getElementById('countryManual').value
    : (document.getElementById('countrySelect').selectedOptions[0]?.text || '');
  const addr = [
    document.querySelector('[name=address]').value,
    document.querySelector('[name=city]').value,
    country,
  ].filter(Boolean).join(', ');
  if (!addr) return alert('Töltsd ki utca/város/ország');
  this.innerHTML = '<i class="ri-loader-4-line" style="animation:spin 1s linear infinite;"></i> Keresés...';
  try {
    const r = await fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(addr) + '&limit=1');
    const d = await r.json();
    if (d.length) {
      latInput.value = parseFloat(d[0].lat).toFixed(6);
      lngInput.value = parseFloat(d[0].lon).toFixed(6);
      this.innerHTML = '<i class="ri-checkbox-circle-line"></i> ' + parseFloat(d[0].lat).toFixed(4) + ', ' + parseFloat(d[0].lon).toFixed(4);
      if (pickMap) {
        pickMap.flyTo({ center:[d[0].lon, d[0].lat], zoom:16 });
        marker.setLngLat([d[0].lon, d[0].lat]);
      }
    } else {
      alert('Nem találtam meg — használd a kézi térkép-pin gombot.');
      this.innerHTML = '<i class="ri-search-line"></i> Cím → GPS';
    }
  } catch(e) { alert(e.message); this.innerHTML = '<i class="ri-search-line"></i> Cím → GPS'; }
});

let pickMap = null, marker = null;
document.getElementById('manualBtn').addEventListener('click', function() {
  const wrap = document.getElementById('mapWrap');
  if (wrap.style.display === 'none') {
    wrap.style.display = '';
    if (!pickMap) initMap();
    setTimeout(() => pickMap && pickMap.resize(), 100);
    wrap.scrollIntoView({ behavior:'smooth', block:'center' });
  } else {
    wrap.style.display = 'none';
  }
});
function initMap() {
  const startLat = parseFloat(latInput.value) || 47.685;
  const startLng = parseFloat(lngInput.value) || 22.467;
  pickMap = new maplibregl.Map({
    container: 'pickMap',
    style: 'https://tiles.openfreemap.org/styles/positron',
    center: [startLng, startLat],
    zoom: latInput.value ? 16 : 6,
  });
  pickMap.addControl(new maplibregl.NavigationControl(), 'top-right');
  marker = new maplibregl.Marker({ color:'#6366f1', draggable:true })
    .setLngLat([startLng, startLat]).addTo(pickMap);
  marker.on('dragend', () => {
    const ll = marker.getLngLat();
    latInput.value = ll.lat.toFixed(6);
    lngInput.value = ll.lng.toFixed(6);
  });
  pickMap.on('click', (e) => {
    marker.setLngLat(e.lngLat);
    latInput.value = e.lngLat.lat.toFixed(6);
    lngInput.value = e.lngLat.lng.toFixed(6);
  });
}
</script>
