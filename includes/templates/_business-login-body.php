<?php if (!defined('ABSPATH')) exit; ?>
<div class="bz-card" style="max-width:420px; margin:40px auto;">
  <h1 class="bz-h1">Bejelentkezés</h1>
  <?php if (!empty($_GET['err'])): ?>
    <div class="bz-msg err">Hibás email vagy jelszó.</div>
  <?php endif; ?>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('ppv_advertiser_login'); ?>
    <input type="hidden" name="action" value="ppv_advertiser_login">
    <label class="bz-label">Email</label>
    <input type="email" name="email" class="bz-input" required style="margin-bottom:12px;">
    <label class="bz-label">Jelszó</label>
    <input type="password" name="password" class="bz-input" required style="margin-bottom:18px;">
    <button type="submit" class="bz-btn" style="width:100%;">Belépés</button>
  </form>
  <p style="margin-top:20px; text-align:center;">
    Még nincs fiókod? <a href="<?php echo esc_url(home_url('/business/register')); ?>">Regisztrálj</a>
  </p>
</div>
