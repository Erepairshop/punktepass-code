<?php
if (!defined('ABSPATH')) exit;
$adv = PPV_Advertisers::current_advertiser();
?>
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
<div class="bz-card">
  <h1 class="bz-h1">Cégadatok</h1>
  <?php if (!empty($_GET['saved'])): ?><div class="bz-msg ok">✓ Mentve.</div><?php endif; ?>
  <?php if (!empty($_GET['welcome'])): ?><div class="bz-msg ok">🎉 Sikeres regisztráció! Töltsd ki a profilod hogy megjelenj a térképen.</div><?php endif; ?>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
    <?php wp_nonce_field('ppv_advertiser_save_profile'); ?>
    <input type="hidden" name="action" value="ppv_advertiser_save_profile">
    <h2 class="bz-h2">Alap</h2>
    <div class="bz-grid">
      <div><label class="bz-label">Cégnév</label><input type="text" name="business_name" class="bz-input" value="<?php echo esc_attr($adv->business_name); ?>" required></div>
      <div><label class="bz-label">Kategória</label>
        <select name="category" class="bz-select">
          <?php foreach (['food'=>'🍔 Étterem/Food','cafe'=>'☕ Café','retail'=>'🛍 Bolt','service'=>'🔧 Szerviz','beauty'=>'💇 Szépség','auto'=>'🚗 Autó','health'=>'⚕️ Egészség','other'=>'📦 Egyéb'] as $k=>$v): ?>
            <option value="<?php echo $k; ?>" <?php selected($adv->category, $k); ?>><?php echo esc_html($v); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <h2 class="bz-h2" style="margin-top:24px;">Elérhetőség</h2>
    <div class="bz-grid">
      <div><label class="bz-label">Telefon</label><input type="text" name="phone" class="bz-input" value="<?php echo esc_attr($adv->phone); ?>"></div>
      <div><label class="bz-label">WhatsApp</label><input type="text" name="whatsapp" class="bz-input" value="<?php echo esc_attr($adv->whatsapp); ?>"></div>
      <div><label class="bz-label">Web</label><input type="url" name="website" class="bz-input" value="<?php echo esc_attr($adv->website); ?>"></div>
    </div>

    <h2 class="bz-h2" style="margin-top:24px;">Cím + GPS koordináta</h2>
    <div class="bz-grid">
      <div><label class="bz-label">Utca</label><input type="text" name="address" class="bz-input" value="<?php echo esc_attr($adv->address); ?>"></div>
      <div><label class="bz-label">Város</label><input type="text" name="city" class="bz-input" value="<?php echo esc_attr($adv->city); ?>"></div>
      <div><label class="bz-label">Ország</label><input type="text" name="country" class="bz-input" value="<?php echo esc_attr($adv->country); ?>"></div>
      <div><label class="bz-label">Irányítószám</label><input type="text" name="postcode" class="bz-input" value="<?php echo esc_attr($adv->postcode); ?>"></div>
      <div><label class="bz-label">Lat</label><input type="text" name="lat" class="bz-input" value="<?php echo esc_attr($adv->lat); ?>" id="latInput" readonly style="background:#f3f4f6;"></div>
      <div><label class="bz-label">Lng</label><input type="text" name="lng" class="bz-input" value="<?php echo esc_attr($adv->lng); ?>" id="lngInput" readonly style="background:#f3f4f6;"></div>
    </div>
    <div style="display:flex; gap:8px; margin-top:8px; flex-wrap:wrap;">
      <button type="button" id="geoBtn" class="bz-btn secondary"><i class="ri-map-pin-line"></i> Cím → GPS (auto)</button>
      <button type="button" id="manualBtn" class="bz-btn secondary"><i class="ri-pushpin-line"></i> Pin térképen (kézi)</button>
    </div>

    <div id="mapWrap" style="display:none; margin-top:12px;">
      <div style="display:flex; gap:8px; align-items:center; margin-bottom:6px; font-size:12px; color:var(--muted);">
        <i class="ri-information-line"></i>
        <span>Húzd a pin-t a pontos helyre, vagy kattints a térképen</span>
      </div>
      <div id="pickMap" style="width:100%; height:340px; border-radius:12px; overflow:hidden; border:1px solid var(--border);"></div>
    </div>

    <h2 class="bz-h2" style="margin-top:24px;">Logo</h2>
    <?php $logo = $adv->logo_url ?? ''; ?>
    <div style="display:flex; align-items:center; gap:14px;">
      <div id="logoPreview" style="width:80px; height:80px; border-radius:14px; background:<?php echo $logo ? "url('".esc_url($logo)."') center/cover" : '#f3f4f6'; ?>; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:24px; border:1px solid var(--border);">
        <?php if (!$logo): ?><i class="ri-image-line"></i><?php endif; ?>
      </div>
      <label class="bz-btn secondary" style="cursor:pointer;">
        <i class="ri-upload-2-line"></i> <?php echo $logo ? 'Csere' : 'Logo feltöltése'; ?>
        <input type="file" name="logo_file" accept="image/*" style="display:none;" onchange="previewLogo(this)">
      </label>
    </div>

    <h2 class="bz-h2" style="margin-top:24px;">Galéria <span style="font-weight:400; text-transform:none; letter-spacing:0;">(max 8 kép)</span></h2>
    <?php
    $gallery = [];
    if (!empty($adv->gallery)) {
      $decoded = json_decode($adv->gallery, true);
      if (is_array($decoded)) $gallery = $decoded;
    }
    ?>
    <div id="galleryGrid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(90px, 1fr)); gap:8px; margin-bottom:10px;">
      <?php foreach ($gallery as $img): ?>
        <div class="gal-item" style="position:relative; aspect-ratio:1; border-radius:10px; overflow:hidden; border:1px solid var(--border);">
          <img src="<?php echo esc_url($img); ?>" style="width:100%; height:100%; object-fit:cover;">
          <label style="position:absolute; top:4px; right:4px; background:rgba(0,0,0,.6); color:#fff; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:13px;">
            <input type="checkbox" name="gallery_remove[]" value="<?php echo esc_attr($img); ?>" style="display:none;" onchange="this.parentElement.parentElement.style.opacity = this.checked ? 0.3 : 1; this.parentElement.parentElement.querySelector('.gal-removed').style.display = this.checked ? 'flex' : 'none';">
            <i class="ri-close-line"></i>
          </label>
          <div class="gal-removed" style="display:none; position:absolute; inset:0; background:rgba(220,38,38,.85); color:#fff; align-items:center; justify-content:center; font-size:11px; font-weight:600;">TÖRÖLVE</div>
        </div>
      <?php endforeach; ?>
    </div>
    <label class="bz-btn secondary" style="cursor:pointer;">
      <i class="ri-add-line"></i> Új képek hozzáadása
      <input type="file" name="gallery_files[]" accept="image/*" multiple style="display:none;" onchange="previewGallery(this)">
    </label>
    <div id="galleryNew" style="display:flex; gap:6px; margin-top:8px; flex-wrap:wrap;"></div>

    <div style="margin-top:20px;"><button type="submit" class="bz-btn"><i class="ri-save-line"></i> Mentés</button></div>
  </form>
</div>

<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script>
const latInput = document.getElementById('latInput');
const lngInput = document.getElementById('lngInput');

document.getElementById('geoBtn').addEventListener('click', async function() {
  const addr = [
    document.querySelector('[name=address]').value,
    document.querySelector('[name=city]').value,
    document.querySelector('[name=country]').value,
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
        pickMap.flyTo({ center: [d[0].lon, d[0].lat], zoom: 16 });
        marker.setLngLat([d[0].lon, d[0].lat]);
      }
    } else {
      alert('Nem találtam meg ezt a címet — használd a kézi térkép-pin gombot.');
      this.innerHTML = '<i class="ri-map-pin-line"></i> Cím → GPS (auto)';
    }
  } catch(e) { alert(e.message); this.innerHTML = '<i class="ri-map-pin-line"></i> Cím → GPS (auto)'; }
});

let pickMap = null;
let marker = null;

document.getElementById('manualBtn').addEventListener('click', function() {
  const wrap = document.getElementById('mapWrap');
  if (wrap.style.display === 'none') {
    wrap.style.display = '';
    if (!pickMap) initMap();
    setTimeout(() => pickMap && pickMap.resize(), 100);
    wrap.scrollIntoView({ behavior:'smooth', block:'start' });
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
    .setLngLat([startLng, startLat])
    .addTo(pickMap);
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
<script>
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
    div.style.cssText = 'width:64px; height:64px; border-radius:8px; background:url('+url+') center/cover; border:2px solid #6366f1; position:relative;';
    div.innerHTML = '<div style="position:absolute; bottom:-2px; right:-2px; background:#6366f1; color:#fff; font-size:9px; padding:1px 5px; border-radius:6px; font-weight:700;">ÚJ</div>';
    wrap.appendChild(div);
  });
}
</script>
<style>@keyframes spin { to { transform:rotate(360deg); } }</style>
