<?php
if (!defined('ABSPATH')) exit;
$adv = PPV_Advertisers::current_advertiser();
global $wpdb;
$followers = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_advertiser_followers WHERE advertiser_id = %d AND push_enabled=1", $adv->id));
$effective_push_limit = PPV_Advertisers::get_effective_push_limit($adv->id);
$remaining = max(0, $effective_push_limit - (int)$adv->push_used_this_month);

// Reset date: stored on advertiser, or first day of next month as fallback
$reset_ts = !empty($adv->push_month_reset_at) ? strtotime($adv->push_month_reset_at) : strtotime('first day of next month');
$reset_date = date_i18n(get_option('date_format', 'Y-m-d'), $reset_ts);
$days_until_reset = max(0, (int)ceil(($reset_ts - time()) / 86400));
?>
<div class="bz-card">
  <h1 class="bz-h1"><?php echo esc_html(PPV_Lang::t('biz_push_title')); ?></h1>
  <?php if (!empty($_GET['sent'])): ?><div class="bz-msg ok"><?php echo esc_html(sprintf(PPV_Lang::t('biz_push_sent_msg'), (int)$_GET['sent'])); ?></div><?php endif; ?>
  <?php if (!empty($_GET['err']) && $_GET['err']==='cap'): ?><div class="bz-msg err"><?php echo esc_html(sprintf(PPV_Lang::t('biz_push_err_cap'), $effective_push_limit)); ?> — <?php echo esc_html(sprintf(PPV_Lang::t('biz_push_next_send'), $reset_date)); ?> (<?php echo esc_html(sprintf(PPV_Lang::t('biz_push_days_left'), $days_until_reset)); ?>)</div><?php endif; ?>
  <?php if (!empty($_GET['err']) && $_GET['err']==='empty'): ?><div class="bz-msg err"><?php echo esc_html(PPV_Lang::t('biz_push_err_empty')); ?></div><?php endif; ?>
  <?php
    $push_lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de';
    if (!in_array($push_lang, ['de','hu','ro','en'], true)) $push_lang = 'de';
  ?>
  <?php if (!empty($_GET['err']) && $_GET['err']==='window'):
    $win_msg = [
      'de' => '⏰ Push nur zwischen 08:00 und 22:00 Uhr erlaubt — bitte versuche es später noch einmal.',
      'hu' => '⏰ Push csak 08:00 és 22:00 között küldhető — próbáld meg később.',
      'ro' => '⏰ Push permis doar între 08:00 și 22:00 — încearcă mai târziu.',
      'en' => '⏰ Push only allowed between 08:00 and 22:00 — please try again later.',
    ];
  ?>
    <div class="bz-msg err"><?php echo esc_html($win_msg[$push_lang]); ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['err']) && $_GET['err']==='cooldown'):
    $wait = max(1, (int)($_GET['wait'] ?? 0));
    $cd_msg = [
      'de' => '⏳ Du hast vor kurzem schon eine Push gesendet. Nächste Push in ~%dh möglich. (Schutz vor zu viel Push für deine Follower.)',
      'hu' => '⏳ Nemrég már küldtél push-t. Következő push ~%d óra múlva. (Védelem a túl gyakori értesítések ellen.)',
      'ro' => '⏳ Ai trimis recent o notificare push. Următoarea push în ~%dh. (Protecție împotriva spamului către urmăritori.)',
      'en' => '⏳ You sent a push recently. Next push possible in ~%dh. (Protects your followers from too many notifications.)',
    ];
  ?>
    <div class="bz-msg err"><?php echo esc_html(sprintf($cd_msg[$push_lang], $wait)); ?></div>
  <?php endif; ?>

  <?php
    // Soft hint: warn the user about the cooldown rules BEFORE they fill the form
    $tier_cap = PPV_Advertisers::TIERS[$adv->tier]['push_per_month'] ?? 4;
    $gap_h    = ($tier_cap >= 16) ? 24 : 72;
    $gap_label = ($gap_h === 24) ? '24h' : '3 ' . ['de'=>'Tage','hu'=>'nap','ro'=>'zile','en'=>'days'][$push_lang];
    $hint_msg = [
      'de' => '<strong>Push-Regeln:</strong> nur zwischen 08:00–22:00, mindestens %s zwischen Pushes.',
      'hu' => '<strong>Push-szabályok:</strong> csak 08:00–22:00 között, legalább %s a push-ok között.',
      'ro' => '<strong>Reguli push:</strong> doar între 08:00–22:00, minim %s între notificări.',
      'en' => '<strong>Push rules:</strong> only 08:00–22:00, at least %s between pushes.',
    ];
  ?>
  <div class="bz-msg" style="background:#eef2ff; color:#3730a3; padding:8px 12px; border-radius:6px; margin-bottom:10px; font-size:13px;">
    <?php echo wp_kses_post(sprintf($hint_msg[$push_lang], esc_html($gap_label))); ?>
  </div>
  <p><?php echo wp_kses_post(sprintf(PPV_Lang::t('biz_push_followers_line'), $followers, $remaining)); ?></p>
  <?php if ($followers === 0): ?>
    <div class="bz-msg" style="background:#fef3c7; color:#92400e; padding:8px 12px; border-radius:6px; margin-bottom:10px;">
      <?php echo esc_html(PPV_Lang::t('biz_push_no_followers_warn')); ?><br>
      <?php echo esc_html(PPV_Lang::t('biz_push_share_link')); ?> <code><?php echo esc_html(home_url('/business/' . $adv->slug)); ?></code>
    </div>
  <?php endif; ?>

  <?php if ($remaining === 0): ?>
    <div class="bz-msg" style="background:#fee2e2; color:#991b1b; padding:10px 14px; border-radius:8px; margin-bottom:10px;">
      <strong><?php echo esc_html(PPV_Lang::t('biz_push_no_remaining_full')); ?></strong><br>
      📅 <?php echo esc_html(sprintf(PPV_Lang::t('biz_push_next_send'), $reset_date)); ?>
      <span style="opacity:0.8;">(<?php echo esc_html(sprintf(PPV_Lang::t('biz_push_days_left'), $days_until_reset)); ?>)</span>
    </div>
  <?php else: ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('ppv_advertiser_send_push'); ?>
      <input type="hidden" name="action" value="ppv_advertiser_send_push">
      <label class="bz-label"><?php echo esc_html(PPV_Lang::t('biz_push_title_label')); ?></label>
      <input type="text" name="title" class="bz-input" maxlength="60" required placeholder="<?php echo esc_attr(PPV_Lang::t('biz_push_title_ph')); ?>">
      <label class="bz-label" style="margin-top:8px;"><?php echo esc_html(PPV_Lang::t('biz_push_body_label')); ?></label>
      <textarea name="body" class="bz-textarea" maxlength="140" required placeholder="<?php echo esc_attr(PPV_Lang::t('biz_push_body_ph')); ?>"></textarea>
      <div style="margin-top:14px;">
        <button type="submit" class="bz-btn" onclick="return confirm(<?php echo esc_attr(wp_json_encode(sprintf(PPV_Lang::t('biz_push_confirm'), $followers))); ?>)"><?php echo esc_html(PPV_Lang::t('biz_push_send_btn')); ?></button>
      </div>
    </form>
  <?php endif; ?>
  <p style="margin-top:1rem; opacity:0.8; font-size:90%;">
    <?php echo esc_html(PPV_Lang::t('biz_push_scale_hint')); ?>
  </p>
</div>
<div class="bz-card" style="background:#fef3c7;">
  <?php echo wp_kses_post(PPV_Lang::t('biz_push_tip')); ?>
</div>
