<?php
if (!defined('ABSPATH')) exit;
$adv = PPV_Advertisers::current_advertiser();
global $wpdb;
$followers = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_advertiser_followers WHERE advertiser_id = %d AND push_enabled=1", $adv->id));
$tier_info = PPV_Advertisers::TIERS[$adv->tier] ?? ['push_per_month'=>4];
$remaining = max(0, $tier_info['push_per_month'] - (int)$adv->push_used_this_month);
?>
<div class="bz-card">
  <h1 class="bz-h1"><?php echo esc_html(PPV_Lang::t('biz_push_title')); ?></h1>
  <?php if (!empty($_GET['sent'])): ?><div class="bz-msg ok"><?php echo esc_html(sprintf(PPV_Lang::t('biz_push_sent_msg'), (int)$_GET['sent'])); ?></div><?php endif; ?>
  <?php if (!empty($_GET['err']) && $_GET['err']==='cap'): ?><div class="bz-msg err"><?php echo esc_html(sprintf(PPV_Lang::t('biz_push_err_cap'), $tier_info['push_per_month'])); ?></div><?php endif; ?>
  <?php if (!empty($_GET['err']) && $_GET['err']==='empty'): ?><div class="bz-msg err"><?php echo esc_html(PPV_Lang::t('biz_push_err_empty')); ?></div><?php endif; ?>
  <p><?php echo wp_kses_post(sprintf(PPV_Lang::t('biz_push_followers_line'), $followers, $remaining)); ?></p>

  <?php if ($followers === 0): ?>
    <div class="bz-msg err"><?php echo esc_html(PPV_Lang::t('biz_push_no_followers')); ?> <code><?php echo esc_html(home_url('/business/' . $adv->slug)); ?></code></div>
  <?php elseif ($remaining === 0): ?>
    <p style="color:var(--muted);"><?php echo esc_html(PPV_Lang::t('biz_push_no_remaining')); ?></p>
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
</div>
<div class="bz-card" style="background:#fef3c7;">
  <?php echo wp_kses_post(PPV_Lang::t('biz_push_tip')); ?>
</div>
