<?php if (!defined('ABSPATH')) exit; ?>
<div class="bz-card">
  <h1 class="bz-h1"><?php echo esc_html(PPV_Lang::t('biz_register_title')); ?></h1>
  <p style="color:var(--muted); margin-bottom:24px;"><?php echo wp_kses_post(PPV_Lang::t('biz_register_subtitle')); ?></p>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('ppv_advertiser_register'); ?>
    <input type="hidden" name="action" value="ppv_advertiser_register">
    <div class="bz-grid">
      <div>
        <label class="bz-label"><?php echo esc_html(PPV_Lang::t('biz_register_company')); ?></label>
        <input type="text" name="business_name" class="bz-input" required>
      </div>
      <div>
        <label class="bz-label"><?php echo esc_html(PPV_Lang::t('biz_register_email')); ?></label>
        <input type="email" name="email" class="bz-input" required>
      </div>
      <div>
        <label class="bz-label"><?php echo esc_html(PPV_Lang::t('biz_register_password')); ?></label>
        <input type="password" name="password" class="bz-input" required minlength="6">
      </div>
    </div>
    <div style="margin-top:18px;">
      <button type="submit" class="bz-btn"><?php echo esc_html(PPV_Lang::t('biz_register_submit')); ?></button>
    </div>
  </form>
</div>
<div class="bz-card" style="background:#f0f9ff;">
  <h2 class="bz-h2"><?php echo esc_html(PPV_Lang::t('biz_register_what_title')); ?></h2>
  <ul>
    <li><?php echo esc_html(PPV_Lang::t('biz_register_what_1')); ?></li>
    <li><?php echo esc_html(PPV_Lang::t('biz_register_what_2')); ?></li>
    <li><?php echo esc_html(PPV_Lang::t('biz_register_what_3')); ?></li>
    <li><?php echo esc_html(PPV_Lang::t('biz_register_what_4')); ?></li>
    <li><?php echo esc_html(PPV_Lang::t('biz_register_what_5')); ?></li>
  </ul>
</div>
