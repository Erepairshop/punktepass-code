<?php if (!defined('ABSPATH')) exit; ?>
<div class="bz-card" style="max-width:420px; margin:40px auto;">
  <h1 class="bz-h1"><?php echo esc_html(PPV_Lang::t('biz_login_title')); ?></h1>
  <?php if (!empty($_GET['err'])): ?>
    <div class="bz-msg err"><?php echo esc_html(PPV_Lang::t('biz_login_err')); ?></div>
  <?php endif; ?>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('ppv_advertiser_login'); ?>
    <input type="hidden" name="action" value="ppv_advertiser_login">
    <label class="bz-label"><?php echo esc_html(PPV_Lang::t('biz_login_email')); ?></label>
    <input type="email" name="email" class="bz-input" required style="margin-bottom:12px;">
    <label class="bz-label"><?php echo esc_html(PPV_Lang::t('biz_login_password')); ?></label>
    <input type="password" name="password" class="bz-input" required style="margin-bottom:18px;">
    <button type="submit" class="bz-btn" style="width:100%;"><?php echo esc_html(PPV_Lang::t('biz_login_submit')); ?></button>
  </form>
  <p style="margin-top:20px; text-align:center;">
    <?php echo esc_html(PPV_Lang::t('biz_login_no_account')); ?> <a href="<?php echo esc_url(home_url('/business/register')); ?>"><?php echo esc_html(PPV_Lang::t('biz_login_register_link')); ?></a>
  </p>
</div>
