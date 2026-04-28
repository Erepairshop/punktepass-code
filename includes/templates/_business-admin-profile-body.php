<?php
if (!defined('ABSPATH')) exit;
$adv = PPV_Advertisers::current_advertiser();
?>
<div class="bz-card">
  <h1 class="bz-h1">Cégadatok</h1>
  <?php if (!empty($_GET['saved'])): ?><div class="bz-msg ok">✓ Mentve.</div><?php endif; ?>
  <?php if (!empty($_GET['welcome'])): ?><div class="bz-msg ok">🎉 Sikeres regisztráció! Töltsd ki a profilod hogy megjelenj a térképen.</div><?php endif; ?>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
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
      <div><label class="bz-label">Lat (pl 47.685)</label><input type="text" name="lat" class="bz-input" value="<?php echo esc_attr($adv->lat); ?>" id="latInput"></div>
      <div><label class="bz-label">Lng (pl 22.467)</label><input type="text" name="lng" class="bz-input" value="<?php echo esc_attr($adv->lng); ?>" id="lngInput"></div>
    </div>
    <button type="button" id="geoBtn" class="bz-btn secondary" style="margin-top:8px;">📍 Cím → GPS (auto)</button>

    <h2 class="bz-h2" style="margin-top:24px;">Logo + cover</h2>
    <div class="bz-grid">
      <div><label class="bz-label">Logo URL</label><input type="url" name="logo_url" class="bz-input" value="<?php echo esc_attr($adv->logo_url); ?>" placeholder="https://..."></div>
      <div><label class="bz-label">Cover URL</label><input type="url" name="cover_url" class="bz-input" value="<?php echo esc_attr($adv->cover_url); ?>"></div>
    </div>

    <h2 class="bz-h2" style="margin-top:24px;">Leírás (4 nyelv)</h2>
    <?php foreach (['de'=>'Deutsch','hu'=>'Magyar','ro'=>'Română','en'=>'English'] as $L=>$lname): ?>
      <label class="bz-label"><?php echo $lname; ?></label>
      <textarea name="description_<?php echo $L; ?>" class="bz-textarea"><?php echo esc_textarea($adv->{'description_'.$L}); ?></textarea>
    <?php endforeach; ?>

    <div style="margin-top:20px;"><button type="submit" class="bz-btn">Mentés</button></div>
  </form>
</div>

<script>
document.getElementById('geoBtn').addEventListener('click', async function() {
  const addr = [
    document.querySelector('[name=address]').value,
    document.querySelector('[name=city]').value,
    document.querySelector('[name=country]').value,
  ].filter(Boolean).join(', ');
  if (!addr) return alert('Töltsd ki utca/város/ország');
  this.textContent = '⏳ Keresés...';
  try {
    const r = await fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(addr) + '&limit=1');
    const d = await r.json();
    if (d.length) {
      document.getElementById('latInput').value = parseFloat(d[0].lat).toFixed(6);
      document.getElementById('lngInput').value = parseFloat(d[0].lon).toFixed(6);
      this.textContent = '✓ Megvan: ' + d[0].lat + ', ' + d[0].lon;
    } else {
      alert('Nem találtam meg ezt a címet, írd be kézzel.');
      this.textContent = '📍 Cím → GPS (auto)';
    }
  } catch(e) { alert(e.message); this.textContent = '📍 Cím → GPS (auto)'; }
});
</script>
