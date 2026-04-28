<?php
if (!defined('ABSPATH')) exit;
$adv = PPV_Advertisers::current_advertiser();
global $wpdb;
$ads = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppv_ads WHERE advertiser_id = %d ORDER BY id DESC", $adv->id
));
$tier_info = PPV_Advertisers::TIERS[$adv->tier] ?? ['ads'=>1];
$can_create = count($ads) < $tier_info['ads'];
$edit_id = isset($_GET['edit']) ? ($_GET['edit'] === 'new' ? -1 : (int)$_GET['edit']) : 0;
$edit = null;
if ($edit_id > 0) {
    $edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_ads WHERE id = %d AND advertiser_id = %d", $edit_id, $adv->id));
}

$ad_types = [
  'discount_percent' => ['Akció (%)', 'ri-percent-line', '#dc2626'],
  'discount_fixed'   => ['Akció (€)', 'ri-money-euro-circle-line', '#dc2626'],
  'free_product'     => ['Ajándék',   'ri-gift-line', '#10b981'],
  'coupon'           => ['Kupon kód', 'ri-coupon-line', '#f59e0b'],
  'event'            => ['Esemény',   'ri-calendar-event-line', '#8b5cf6'],
  'announcement'     => ['Hirdetmény','ri-megaphone-line', '#3b82f6'],
];
$current_type = $edit->ad_type ?? 'discount_percent';
$current_vis = $edit->visibility ?? 'public';
$current_pul = $edit->per_user_limit ?? 'lifetime';
$current_badge = $edit->badge ?? '';
?>
<style>
.type-chip { padding:10px 12px; border:2px solid var(--border); border-radius:10px; cursor:pointer; text-align:center; transition:all .15s; background:#fff; }
.type-chip i { font-size:22px; display:block; margin-bottom:3px; }
.type-chip span { font-size:11px; font-weight:600; }
.type-chip.active { transform:translateY(-1px); }
.type-chip:hover { background:#f9fafb; }
.vis-radio { display:flex; gap:8px; }
.vis-radio label { flex:1; cursor:pointer; }
.vis-radio input { display:none; }
.vis-card { padding:12px; border:2px solid var(--border); border-radius:10px; transition:all .15s; }
.vis-card .h { font-weight:700; font-size:13px; display:flex; align-items:center; gap:6px; margin-bottom:3px; }
.vis-card .d { font-size:11px; color:var(--muted); }
.vis-radio input:checked + .vis-card { border-color:var(--pp); background:#eef2ff; }
.char-count { font-size:11px; color:var(--muted); text-align:right; margin-top:2px; }
.char-count.warn { color:#d97706; }
.char-count.over { color:#dc2626; font-weight:700; }
.badge-chip { display:inline-flex; align-items:center; gap:4px; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer; border:2px solid transparent; }
.badge-chip.b-new { background:#dcfce7; color:#166534; }
.badge-chip.b-sale { background:#fee2e2; color:#991b1b; }
.badge-chip.b-limited { background:#fef3c7; color:#92400e; }
.badge-chip.b-ending { background:#ede9fe; color:#6b21a8; }
.badge-chip.active { border-color:#000; }
.badge-chip.empty { background:#f3f4f6; color:var(--muted); }
</style>

<div class="bz-card" style="padding:14px;">
  <h1 class="bz-h1" style="margin:0;"><i class="ri-megaphone-fill"></i> Hirdetések <span style="font-size:13px; font-weight:500; color:var(--muted);">(<?php echo count($ads); ?>/<?php echo $tier_info['ads']; ?>)</span></h1>
  <?php if (!empty($_GET['saved'])): ?><div class="bz-msg ok" style="margin-top:10px;">✓ Mentve</div><?php endif; ?>
  <?php if (!empty($_GET['err']) && $_GET['err'] === 'tier_limit'): ?><div class="bz-msg err" style="margin-top:10px;">⚠️ Tier-limit elérve (<?php echo $tier_info['ads']; ?>). Töröld a régieket vagy bővíts.</div><?php endif; ?>
  <?php if (!$edit_id && $can_create): ?>
    <a href="?edit=new" class="bz-btn full" style="margin-top:12px;"><i class="ri-add-line"></i> Új hirdetés</a>
  <?php endif; ?>
</div>

<?php if ($edit_id !== 0): ?>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
  <?php wp_nonce_field('ppv_advertiser_save_ad'); ?>
  <input type="hidden" name="action" value="ppv_advertiser_save_ad">
  <input type="hidden" name="ad_id" value="<?php echo $edit ? (int)$edit->id : 0; ?>">
  <input type="hidden" name="ad_type" id="adType" value="<?php echo esc_attr($current_type); ?>">
  <input type="hidden" name="badge" id="badgeInput" value="<?php echo esc_attr($current_badge); ?>">

  <!-- TÍPUS -->
  <details class="bz-section" open>
    <summary><i class="ri-price-tag-3-line head-ic"></i> Típus<i class="ri-arrow-down-s-line arr"></i></summary>
    <div class="bz-content">
      <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:6px;">
        <?php foreach ($ad_types as $key => [$label, $icon, $color]): ?>
          <div class="type-chip <?php echo $current_type === $key ? 'active' : ''; ?>" data-type="<?php echo $key; ?>" onclick="selectType('<?php echo $key; ?>', this)" style="<?php echo $current_type === $key ? "border-color:$color; background:".$color."10;" : ''; ?>">
            <i class="<?php echo $icon; ?>" style="color:<?php echo $color; ?>;"></i>
            <span><?php echo esc_html($label); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </details>

  <!-- TARTALOM -->
  <details class="bz-section" open>
    <summary><i class="ri-edit-line head-ic"></i> Tartalom<i class="ri-arrow-down-s-line arr"></i></summary>
    <div class="bz-content">
      <label class="bz-label">Cím (max 60)</label>
      <input type="text" name="title" id="adTitle" class="bz-input" maxlength="60" value="<?php echo esc_attr($edit->title ?? ''); ?>" oninput="updateCount('adTitle', 'adTitleCount', 60)" placeholder="pl. Nyitó akció — minden -20%">
      <div class="char-count" id="adTitleCount">0 / 60</div>

      <label class="bz-label" style="margin-top:10px;">Rövid leírás (max 200)</label>
      <textarea name="body" id="adBody" class="bz-textarea" maxlength="200" oninput="updateCount('adBody', 'adBodyCount', 200)" placeholder="1-2 mondat hogy mit kínálsz"><?php echo esc_textarea($edit->body ?? ''); ?></textarea>
      <div class="char-count" id="adBodyCount">0 / 200</div>

      <!-- Promó érték (típusfüggő) -->
      <div id="promoFieldWrap" style="margin-top:10px;">
        <label class="bz-label" id="promoLabel">Promó érték</label>
        <input type="text" name="promo_value" id="promoValue" class="bz-input" value="<?php echo esc_attr($edit->promo_value ?? ''); ?>">
        <div class="char-count" id="promoHint" style="text-align:left;">—</div>
      </div>

      <!-- Kupon kód (csak coupon típusnál) -->
      <div id="couponWrap" style="margin-top:10px; display:<?php echo $current_type === 'coupon' ? '' : 'none'; ?>;">
        <label class="bz-label">Kupon kód (opcionális)</label>
        <input type="text" name="coupon_code" class="bz-input" maxlength="20" value="<?php echo esc_attr($edit->coupon_code ?? ''); ?>" placeholder="pl. NYAR2025" style="text-transform:uppercase; letter-spacing:1px; font-family:monospace;">
        <div class="char-count" style="text-align:left;">A user bemutatja a kódot — csak akkor szükséges ha kasszában rögzíted</div>
      </div>
    </div>
  </details>

  <!-- BORÍTÓ KÉP -->
  <details class="bz-section" <?php echo empty($edit->image_url) ? 'open' : ''; ?>>
    <summary><i class="ri-image-line head-ic"></i> Borító kép<i class="ri-arrow-down-s-line arr"></i></summary>
    <div class="bz-content">
      <div style="display:flex; align-items:center; gap:14px;">
        <div id="adImagePreview" style="width:140px; height:90px; border-radius:10px; background:<?php echo !empty($edit->image_url) ? "url('".esc_url($edit->image_url)."') center/cover" : '#f3f4f6'; ?>; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:24px; border:1px solid var(--border);">
          <?php if (empty($edit->image_url)): ?><i class="ri-image-line"></i><?php endif; ?>
        </div>
        <label class="bz-btn secondary" style="cursor:pointer;">
          <i class="ri-upload-2-line"></i> <?php echo !empty($edit->image_url) ? 'Csere' : 'Feltöltés'; ?>
          <input type="file" name="image_file" accept="image/*" style="display:none;" onchange="previewAdImage(this)">
        </label>
      </div>
    </div>
  </details>

  <!-- LÁTHATÓSÁG + LIMIT -->
  <details class="bz-section" open>
    <summary><i class="ri-eye-line head-ic"></i> Láthatóság + limit<i class="ri-arrow-down-s-line arr"></i></summary>
    <div class="bz-content">
      <label class="bz-label">Ki látja?</label>
      <div class="vis-radio" style="margin-bottom:12px;">
        <label>
          <input type="radio" name="visibility" value="public" <?php checked($current_vis, 'public'); ?>>
          <div class="vis-card">
            <div class="h"><i class="ri-global-line"></i> Nyilvános</div>
            <div class="d">Bárki látja a térképen</div>
          </div>
        </label>
        <label>
          <input type="radio" name="visibility" value="followers" <?php checked($current_vis, 'followers'); ?>>
          <div class="vis-card">
            <div class="h"><i class="ri-vip-crown-line"></i> Csak követőknek</div>
            <div class="d">Csak followers látja teljesen</div>
          </div>
        </label>
      </div>

      <div class="bz-grid">
        <div>
          <label class="bz-label">Max. beváltás (összesen)</label>
          <input type="number" name="max_claims" class="bz-input" min="0" value="<?php echo esc_attr($edit->max_claims ?? ''); ?>" placeholder="Üres = korlátlan">
        </div>
        <div>
          <label class="bz-label">Per-user limit</label>
          <select name="per_user_limit" class="bz-select">
            <option value="lifetime" <?php selected($current_pul, 'lifetime'); ?>>1× élethosszig</option>
            <option value="daily"    <?php selected($current_pul, 'daily'); ?>>1× naponta</option>
            <option value="weekly"   <?php selected($current_pul, 'weekly'); ?>>1× hetente</option>
            <option value="monthly"  <?php selected($current_pul, 'monthly'); ?>>1× havonta</option>
            <option value="none"     <?php selected($current_pul, 'none'); ?>>Korlátlan</option>
          </select>
        </div>
      </div>
    </div>
  </details>

  <!-- ÉRVÉNYESSÉG -->
  <details class="bz-section">
    <summary><i class="ri-calendar-line head-ic"></i> Érvényesség<i class="ri-arrow-down-s-line arr"></i></summary>
    <div class="bz-content">
      <div class="bz-grid">
        <div><label class="bz-label">Kezdet</label><input type="datetime-local" name="valid_from" class="bz-input" value="<?php echo esc_attr($edit ? str_replace(' ','T',substr($edit->valid_from ?? '',0,16)) : ''); ?>"></div>
        <div><label class="bz-label">Vég</label><input type="datetime-local" name="valid_to" class="bz-input" value="<?php echo esc_attr($edit ? str_replace(' ','T',substr($edit->valid_to ?? '',0,16)) : ''); ?>"></div>
      </div>
    </div>
  </details>

  <!-- BADGE + EXTRA -->
  <details class="bz-section">
    <summary><i class="ri-flag-line head-ic"></i> Badge + extra<i class="ri-arrow-down-s-line arr"></i></summary>
    <div class="bz-content">
      <label class="bz-label">Kiemelő címke (opcionális)</label>
      <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px;">
        <span class="badge-chip empty <?php echo !$current_badge ? 'active' : ''; ?>" onclick="setBadge('')">Nincs</span>
        <span class="badge-chip b-new <?php echo $current_badge === 'NEW' ? 'active' : ''; ?>" onclick="setBadge('NEW')">ÚJ</span>
        <span class="badge-chip b-sale <?php echo $current_badge === 'SALE' ? 'active' : ''; ?>" onclick="setBadge('SALE')">AKCIÓ</span>
        <span class="badge-chip b-limited <?php echo $current_badge === 'LIMITED' ? 'active' : ''; ?>" onclick="setBadge('LIMITED')">LIMITÁLT</span>
        <span class="badge-chip b-ending <?php echo $current_badge === 'ENDING' ? 'active' : ''; ?>" onclick="setBadge('ENDING')">VÉGE</span>
      </div>

      <label class="bz-label">CTA URL (opcionális)</label>
      <input type="url" name="cta_url" class="bz-input" value="<?php echo esc_attr($edit->cta_url ?? ''); ?>" placeholder="https://...">

      <label style="display:flex; align-items:center; gap:8px; margin-top:12px; cursor:pointer;">
        <input type="checkbox" name="is_active" value="1" <?php checked($edit ? $edit->is_active : 1, 1); ?>>
        <span style="font-weight:600;">Aktív</span>
      </label>
    </div>
  </details>

  <div style="display:flex; gap:8px; margin-top:12px;">
    <button type="submit" class="bz-btn" style="flex:1;"><i class="ri-save-line"></i> Mentés</button>
    <a href="<?php echo esc_url(home_url('/business/admin/ads')); ?>" class="bz-btn secondary">Mégse</a>
  </div>
</form>

<script>
function selectType(t, el) {
  document.getElementById('adType').value = t;
  document.querySelectorAll('.type-chip').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  // Update promo label/placeholder dynamically
  const cfg = {
    discount_percent: { label: 'Kedvezmény mértéke (%)', ph: 'pl. 20', hint: 'Számot adj meg, % nélkül' },
    discount_fixed:   { label: 'Kedvezmény (€)', ph: 'pl. 5', hint: 'Számot adj meg, € nélkül' },
    free_product:     { label: 'Ingyen termék neve', ph: 'pl. 1 kávé minden 5 után', hint: 'Mit kap ingyen?' },
    coupon:           { label: 'Promó leírása', ph: 'pl. 30% minden pizzára', hint: 'Mit ér a kupon kód?' },
    event:            { label: 'Esemény dátum + helyszín', ph: 'pl. 2026-05-12 18:00 — Bistro', hint: 'Mikor és hol?' },
    announcement:     { label: 'Hirdetés szöveg (rövid)', ph: 'pl. Új menü minden szombaton', hint: 'Csak rövid összefoglaló' },
  };
  const c = cfg[t] || cfg.announcement;
  document.getElementById('promoLabel').textContent = c.label;
  document.getElementById('promoValue').placeholder = c.ph;
  document.getElementById('promoHint').textContent = c.hint;
  document.getElementById('couponWrap').style.display = t === 'coupon' ? '' : 'none';
}
function setBadge(b) {
  document.getElementById('badgeInput').value = b;
  document.querySelectorAll('.badge-chip').forEach(c => c.classList.remove('active'));
  if (!b) document.querySelector('.badge-chip.empty').classList.add('active');
  else document.querySelector('.badge-chip.b-' + b.toLowerCase()).classList.add('active');
}
function updateCount(inputId, countId, max) {
  const el = document.getElementById(inputId);
  const cnt = document.getElementById(countId);
  const n = el.value.length;
  cnt.textContent = n + ' / ' + max;
  cnt.classList.toggle('warn', n > max * 0.9);
  cnt.classList.toggle('over', n >= max);
}
function previewAdImage(input) {
  const f = input.files[0]; if (!f) return;
  const url = URL.createObjectURL(f);
  const el = document.getElementById('adImagePreview');
  el.style.background = `url('${url}') center/cover`;
  el.innerHTML = '';
}
// Init counters
updateCount('adTitle', 'adTitleCount', 60);
updateCount('adBody', 'adBodyCount', 200);
selectType(document.getElementById('adType').value, document.querySelector('.type-chip[data-type="' + document.getElementById('adType').value + '"]'));
</script>
<?php endif; ?>

<?php if (!$edit_id): ?>
<div class="bz-card">
  <h2 class="bz-h2"><i class="ri-list-check"></i> Aktuális hirdetéseid</h2>
  <?php if (empty($ads)): ?>
    <p style="color:var(--muted); font-size:13px;">Még nincs hirdetésed. Hozz létre egyet fent.</p>
  <?php else: ?>
    <?php foreach ($ads as $a):
      $type_label = $ad_types[$a->ad_type ?? 'announcement'][0] ?? 'Hirdetés';
      $type_icon = $ad_types[$a->ad_type ?? 'announcement'][1] ?? 'ri-megaphone-line';
      $type_color = $ad_types[$a->ad_type ?? 'announcement'][2] ?? '#6b7280';
    ?>
    <div style="display:flex; gap:10px; padding:10px; border:1px solid var(--border); border-radius:10px; margin-bottom:8px; align-items:center;">
      <?php if ($a->image_url): ?>
        <div style="width:60px; height:60px; border-radius:8px; background:url('<?php echo esc_url($a->image_url); ?>') center/cover; flex-shrink:0;"></div>
      <?php else: ?>
        <div style="width:60px; height:60px; border-radius:8px; background:<?php echo $type_color; ?>20; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:<?php echo $type_color; ?>; font-size:24px;"><i class="<?php echo $type_icon; ?>"></i></div>
      <?php endif; ?>
      <div style="flex:1; min-width:0;">
        <div style="font-weight:600; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
          <?php echo esc_html($a->title ?: $a->title_de ?: '(üres)'); ?>
          <?php if ($a->badge): ?><span style="font-size:9px; padding:1px 5px; border-radius:4px; background:#fef3c7; color:#92400e; margin-left:4px;"><?php echo esc_html($a->badge); ?></span><?php endif; ?>
        </div>
        <div style="font-size:11px; color:var(--muted); display:flex; gap:8px; flex-wrap:wrap; margin-top:2px;">
          <span><i class="<?php echo $type_icon; ?>" style="color:<?php echo $type_color; ?>;"></i> <?php echo esc_html($type_label); ?></span>
          <span><i class="ri-eye-line"></i> <?php echo (int)$a->impressions; ?></span>
          <span><i class="ri-cursor-line"></i> <?php echo (int)$a->clicks; ?></span>
          <span><?php echo $a->is_active ? '<span style="color:#16a34a;">● Aktív</span>' : '<span style="color:#9ca3af;">○ Inaktív</span>'; ?></span>
        </div>
      </div>
      <a href="?edit=<?php echo (int)$a->id; ?>" class="bz-btn secondary" style="padding:6px 10px; font-size:12px;"><i class="ri-edit-line"></i></a>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
