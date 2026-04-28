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
<?php
$is_welcome = isset($_GET['welcome']);
$profile_complete = !empty($adv->address) && !empty($adv->lat) && !empty($adv->lng);
$has_ad = $ad_count > 0;
$public_url = home_url('/business/' . $adv->slug);
?>
<?php if ($is_welcome): ?>
<div class="bz-card" style="background:linear-gradient(135deg,#dcfce7,#bbf7d0); border-color:#86efac;">
  <h1 class="bz-h1" style="color:#166534;"><i class="ri-checkbox-circle-fill"></i> Üdv, <?php echo esc_html($adv->business_name); ?>!</h1>
  <p style="margin:0; color:#166534;">Sikeresen regisztráltál — 30 napod van ingyen kipróbálni minden funkciót.</p>
</div>
<?php endif; ?>

<div class="bz-card" style="display:flex; align-items:center; gap:14px;">
  <div style="width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--pp),var(--pp2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700; flex-shrink:0;">
    <?php echo esc_html(mb_strtoupper(mb_substr($adv->business_name, 0, 1))); ?>
  </div>
  <div style="flex:1; min-width:0;">
    <h1 class="bz-h1" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html($adv->business_name); ?></h1>
    <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--muted); flex-wrap:wrap;">
      <span class="bz-tag <?php echo esc_attr($tier); ?>"><?php echo esc_html(strtoupper($tier)); ?></span>
      <?php if ($adv->subscription_status === 'trial'): ?>
        <span><i class="ri-time-line"></i> Trial <strong><?php echo (int)$days_left; ?> nap</strong></span>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="bz-grid">
  <div class="bz-card">
    <h2 class="bz-h2"><i class="ri-megaphone-line"></i> Hirdetések</h2>
    <div style="font-size:26px; font-weight:700; line-height:1;"><?php echo $ad_count; ?><span style="font-size:13px; color:var(--muted); font-weight:500;">/<?php echo $tier_info['ads']; ?></span></div>
  </div>
  <div class="bz-card">
    <h2 class="bz-h2"><i class="ri-group-line"></i> Követők</h2>
    <div style="font-size:26px; font-weight:700; line-height:1;"><?php echo $followers; ?></div>
  </div>
  <div class="bz-card">
    <h2 class="bz-h2"><i class="ri-notification-3-line"></i> Push (hó)</h2>
    <div style="font-size:26px; font-weight:700; line-height:1;"><?php echo (int)$adv->push_used_this_month; ?><span style="font-size:13px; color:var(--muted); font-weight:500;">/<?php echo $tier_info['push_per_month']; ?></span></div>
  </div>
</div>

<div class="bz-card">
  <h2 class="bz-h2"><i class="ri-rocket-line"></i> Gyors lépések</h2>
  <div style="display:flex; flex-direction:column; gap:8px;">
    <a href="<?php echo esc_url(home_url('/business/admin/profile')); ?>" style="display:flex; align-items:center; gap:10px; padding:12px; border-radius:10px; background:<?php echo $profile_complete ? '#dcfce7' : '#fef3c7'; ?>; text-decoration:none; color:var(--text); transition:transform .15s;" onmousedown="this.style.transform='scale(.98)'" onmouseup="this.style.transform=''">
      <i class="<?php echo $profile_complete ? 'ri-checkbox-circle-fill' : 'ri-map-pin-line'; ?>" style="font-size:22px; color:<?php echo $profile_complete ? '#16a34a' : '#d97706'; ?>;"></i>
      <div style="flex:1;">
        <div style="font-weight:600; font-size:13px;">Profil + cím</div>
        <div style="font-size:11px; color:var(--muted);"><?php echo $profile_complete ? 'Kész — látszol a térképen' : 'Kell a térkép-pinhez'; ?></div>
      </div>
      <i class="ri-arrow-right-s-line" style="color:var(--muted);"></i>
    </a>
    <a href="<?php echo esc_url(home_url('/business/admin/ads')); ?>" style="display:flex; align-items:center; gap:10px; padding:12px; border-radius:10px; background:<?php echo $has_ad ? '#dcfce7' : '#fef3c7'; ?>; text-decoration:none; color:var(--text);">
      <i class="<?php echo $has_ad ? 'ri-checkbox-circle-fill' : 'ri-megaphone-line'; ?>" style="font-size:22px; color:<?php echo $has_ad ? '#16a34a' : '#d97706'; ?>;"></i>
      <div style="flex:1;">
        <div style="font-weight:600; font-size:13px;">Első hirdetés</div>
        <div style="font-size:11px; color:var(--muted);"><?php echo $has_ad ? 'Aktív hirdetésed van' : 'Hozz létre egy ajánlatot'; ?></div>
      </div>
      <i class="ri-arrow-right-s-line" style="color:var(--muted);"></i>
    </a>
    <div style="display:flex; align-items:center; gap:10px; padding:12px; border-radius:10px; background:#f3f4f6;">
      <i class="ri-share-line" style="font-size:22px; color:var(--pp);"></i>
      <div style="flex:1; min-width:0;">
        <div style="font-weight:600; font-size:13px;">Publikus profil link</div>
        <div style="font-size:11px; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html($public_url); ?></div>
      </div>
      <button onclick="navigator.clipboard.writeText('<?php echo esc_js($public_url); ?>'); this.innerHTML='<i class=\'ri-check-line\'></i>'; setTimeout(()=>this.innerHTML='<i class=\'ri-file-copy-line\'></i>', 1500);" style="border:none; background:#fff; padding:8px 10px; border-radius:8px; cursor:pointer; color:var(--pp);"><i class="ri-file-copy-line"></i></button>
    </div>
  </div>
</div>
