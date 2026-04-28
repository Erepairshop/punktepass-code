<?php
if (!defined('ABSPATH')) exit;
$adv = PPV_Advertisers::current_advertiser();
global $wpdb;
$ads = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppv_ads WHERE advertiser_id = %d ORDER BY id DESC", $adv->id
));
$tier_info = PPV_Advertisers::TIERS[$adv->tier] ?? ['ads'=>1];
$can_create = count($ads) < $tier_info['ads'];
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit = $edit_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_ads WHERE id = %d AND advertiser_id = %d", $edit_id, $adv->id)) : null;
?>
<div class="bz-card">
  <h1 class="bz-h1">📣 Hirdetések (<?php echo count($ads); ?> / <?php echo $tier_info['ads']; ?>)</h1>
  <?php if (!empty($_GET['saved'])): ?><div class="bz-msg ok">✓ Mentve.</div><?php endif; ?>
  <?php if (!empty($_GET['err']) && $_GET['err'] === 'tier_limit'): ?><div class="bz-msg err">⚠️ Elérted a tier-limitet (<?php echo $tier_info['ads']; ?> aktív hirdetés). Töröld a régieket vagy bővíts.</div><?php endif; ?>
  <?php if (!$edit && $can_create): ?>
    <a href="?edit=new" class="bz-btn">+ Új hirdetés</a>
  <?php endif; ?>
</div>

<?php if ($edit_id): ?>
<div class="bz-card">
  <h2 class="bz-h2"><?php echo $edit ? 'Szerkesztés' : 'Új hirdetés'; ?></h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('ppv_advertiser_save_ad'); ?>
    <input type="hidden" name="action" value="ppv_advertiser_save_ad">
    <input type="hidden" name="ad_id" value="<?php echo $edit ? (int)$edit->id : 0; ?>">
    <?php foreach (['de'=>'Deutsch','hu'=>'Magyar','ro'=>'Română','en'=>'English'] as $L=>$lname): ?>
      <h2 class="bz-h2" style="margin-top:14px;"><?php echo $lname; ?></h2>
      <label class="bz-label">Cím</label>
      <input type="text" name="title_<?php echo $L; ?>" class="bz-input" value="<?php echo esc_attr($edit->{'title_'.$L} ?? ''); ?>">
      <label class="bz-label" style="margin-top:8px;">Szöveg</label>
      <textarea name="body_<?php echo $L; ?>" class="bz-textarea"><?php echo esc_textarea($edit->{'body_'.$L} ?? ''); ?></textarea>
    <?php endforeach; ?>
    <h2 class="bz-h2" style="margin-top:14px;">Kép + URL + érvényesség</h2>
    <div class="bz-grid">
      <div><label class="bz-label">Kép URL</label><input type="url" name="image_url" class="bz-input" value="<?php echo esc_attr($edit->image_url ?? ''); ?>"></div>
      <div><label class="bz-label">Kattintás URL</label><input type="url" name="cta_url" class="bz-input" value="<?php echo esc_attr($edit->cta_url ?? ''); ?>"></div>
      <div><label class="bz-label">Érvényesség kezdő</label><input type="datetime-local" name="valid_from" class="bz-input" value="<?php echo esc_attr($edit ? str_replace(' ','T',substr($edit->valid_from,0,16)) : ''); ?>"></div>
      <div><label class="bz-label">Érvényesség vég</label><input type="datetime-local" name="valid_to" class="bz-input" value="<?php echo esc_attr($edit ? str_replace(' ','T',substr($edit->valid_to,0,16)) : ''); ?>"></div>
    </div>
    <div style="margin-top:14px;">
      <label><input type="checkbox" name="followers_only" value="1" <?php checked($edit->followers_only ?? 0, 1); ?>> Csak követőknek (kupon-akció jelölés)</label><br>
      <label><input type="checkbox" name="is_active" value="1" <?php checked($edit ? $edit->is_active : 1, 1); ?>> Aktív</label>
    </div>
    <div style="margin-top:18px;">
      <button type="submit" class="bz-btn">Mentés</button>
      <a href="<?php echo esc_url(home_url('/business/admin/ads')); ?>" class="bz-btn secondary">Mégse</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="bz-card">
  <h2 class="bz-h2">Aktuális hirdetéseid</h2>
  <?php if (empty($ads)): ?>
    <p style="color:var(--muted);">Még nincs hirdetésed.</p>
  <?php else: ?>
    <table class="bz-table">
      <tr><th>Cím (DE)</th><th>Aktív</th><th>Imp</th><th>Klikk</th><th></th></tr>
      <?php foreach ($ads as $a): ?>
        <tr>
          <td><?php echo esc_html($a->title_de ?: '(üres)'); ?></td>
          <td><?php echo $a->is_active ? '✓' : '–'; ?></td>
          <td><?php echo (int)$a->impressions; ?></td>
          <td><?php echo (int)$a->clicks; ?></td>
          <td><a href="?edit=<?php echo (int)$a->id; ?>" class="bz-btn secondary" style="padding:4px 10px;">Szerkeszt</a></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
