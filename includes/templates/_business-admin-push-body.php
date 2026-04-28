<?php
if (!defined('ABSPATH')) exit;
$adv = PPV_Advertisers::current_advertiser();
global $wpdb;
$followers = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_advertiser_followers WHERE advertiser_id = %d AND push_enabled=1", $adv->id));
$tier_info = PPV_Advertisers::TIERS[$adv->tier] ?? ['push_per_month'=>4];
$remaining = max(0, $tier_info['push_per_month'] - (int)$adv->push_used_this_month);
?>
<div class="bz-card">
  <h1 class="bz-h1">🔔 Push küldés</h1>
  <?php if (!empty($_GET['sent'])): ?><div class="bz-msg ok">✓ Push elküldve <?php echo (int)$_GET['sent']; ?> követőnek.</div><?php endif; ?>
  <?php if (!empty($_GET['err']) && $_GET['err']==='cap'): ?><div class="bz-msg err">⚠️ Havi limitet elérted (<?php echo $tier_info['push_per_month']; ?>). Várd meg a hónap-resetet.</div><?php endif; ?>
  <?php if (!empty($_GET['err']) && $_GET['err']==='empty'): ?><div class="bz-msg err">Cím + szöveg kötelező.</div><?php endif; ?>
  <p>👥 <strong><?php echo $followers; ?></strong> aktív követő • havi maradék: <strong><?php echo $remaining; ?> push</strong></p>

  <?php if ($followers === 0): ?>
    <div class="bz-msg err">Még nincs követőd. Oszd meg profilodat: <code><?php echo esc_html(home_url('/business/' . $adv->slug)); ?></code></div>
  <?php elseif ($remaining === 0): ?>
    <p style="color:var(--muted);">Várd meg a hónap-resetet vagy bővíts tier-t.</p>
  <?php else: ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('ppv_advertiser_send_push'); ?>
      <input type="hidden" name="action" value="ppv_advertiser_send_push">
      <label class="bz-label">Cím (max 60)</label>
      <input type="text" name="title" class="bz-input" maxlength="60" required placeholder="Pl: Akció ma! -20%">
      <label class="bz-label" style="margin-top:8px;">Szöveg (max 140)</label>
      <textarea name="body" class="bz-textarea" maxlength="140" required placeholder="Pizza Roma minden méretre 20% kedvezmény, csak ma 18-22 között."></textarea>
      <div style="margin-top:14px;">
        <button type="submit" class="bz-btn" onclick="return confirm('Biztosan elküldöd <?php echo $followers; ?> követődnek?')">📤 Küldés</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<div class="bz-card" style="background:#fef3c7;">
  <strong>💡 Tipp:</strong> Heti 1 push max — több spamnak hat, lecsökkented követőid. Mindig konkrét akciót/kupon-t adj, ne csak hír.
</div>
