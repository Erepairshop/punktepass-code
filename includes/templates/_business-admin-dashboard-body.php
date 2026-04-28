<?php
if (!defined('ABSPATH')) exit;
$adv = PPV_Advertisers::current_advertiser();
global $wpdb;
$ad_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_ads WHERE advertiser_id = %d AND is_active=1", $adv->id));
$followers = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_advertiser_followers WHERE advertiser_id = %d", $adv->id));
$tier = $adv->tier;
$tier_info = PPV_Advertisers::TIERS[$tier] ?? ['ads'=>1,'push_per_month'=>4];
$days_left = $adv->subscription_until ? max(0, (strtotime($adv->subscription_until) - time()) / 86400) : 0;
?>
<div class="bz-card">
  <h1 class="bz-h1">Üdv, <?php echo esc_html($adv->business_name); ?>!</h1>
  <p style="color:var(--muted);">
    <span class="bz-tag <?php echo esc_attr($tier); ?>"><?php echo esc_html(strtoupper($tier)); ?></span>
    <?php if ($adv->subscription_status === 'trial'): ?>
      &nbsp; ⏳ Trial: <strong><?php echo (int)$days_left; ?> nap</strong> hátra
    <?php endif; ?>
  </p>
</div>
<div class="bz-grid">
  <div class="bz-card"><h2 class="bz-h2">📣 Hirdetések</h2><div style="font-size:32px; font-weight:700;"><?php echo $ad_count; ?> / <?php echo $tier_info['ads']; ?></div><a href="<?php echo esc_url(home_url('/business/admin/ads')); ?>" class="bz-btn secondary" style="margin-top:8px;">Kezelés</a></div>
  <div class="bz-card"><h2 class="bz-h2">👥 Követők</h2><div style="font-size:32px; font-weight:700;"><?php echo $followers; ?></div></div>
  <div class="bz-card"><h2 class="bz-h2">🔔 Push havi</h2><div style="font-size:32px; font-weight:700;"><?php echo (int)$adv->push_used_this_month; ?> / <?php echo $tier_info['push_per_month']; ?></div><a href="<?php echo esc_url(home_url('/business/admin/push')); ?>" class="bz-btn secondary" style="margin-top:8px;">Küldés</a></div>
</div>
<div class="bz-card">
  <h2 class="bz-h2">⚡ Gyors lépések</h2>
  <ol style="line-height:2;">
    <li>📍 Tölts ki <a href="<?php echo esc_url(home_url('/business/admin/profile')); ?>"><strong>profilodat</strong></a> (cím + GPS koordináta) — ez kell a térkép-pinhez</li>
    <li>📣 Hozz létre első <a href="<?php echo esc_url(home_url('/business/admin/ads')); ?>"><strong>hirdetésed</strong></a></li>
    <li>👥 Oszd meg a profilodat: <code><?php echo esc_html(home_url('/business/' . $adv->slug)); ?></code></li>
    <li>🔔 Ha 5+ követőd van, küldj <a href="<?php echo esc_url(home_url('/business/admin/push')); ?>"><strong>push-t</strong></a></li>
  </ol>
</div>
