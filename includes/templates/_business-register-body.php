<?php if (!defined('ABSPATH')) exit; ?>
<div class="bz-card">
  <h1 class="bz-h1">📣 Regisztrálj 30 napig ingyen</h1>
  <p style="color:var(--muted); margin-bottom:24px;">Ne loyalty / pont-rendszer — csak hirdetés a térképen + Push követőknek. <strong>5 €/hó</strong> alapcsomag, az első 30 nap ingyen.</p>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('ppv_advertiser_register'); ?>
    <input type="hidden" name="action" value="ppv_advertiser_register">
    <div class="bz-grid">
      <div>
        <label class="bz-label">Cégnév</label>
        <input type="text" name="business_name" class="bz-input" required>
      </div>
      <div>
        <label class="bz-label">Email</label>
        <input type="email" name="email" class="bz-input" required>
      </div>
      <div>
        <label class="bz-label">Jelszó (min 6)</label>
        <input type="password" name="password" class="bz-input" required minlength="6">
      </div>
    </div>
    <div style="margin-top:18px;">
      <button type="submit" class="bz-btn">Regisztráció + 30 nap ingyen</button>
    </div>
  </form>
</div>
<div class="bz-card" style="background:#f0f9ff;">
  <h2 class="bz-h2">Mit kapsz?</h2>
  <ul>
    <li>📍 Térkép pin a `/karte` oldalon — userek látnak</li>
    <li>📣 1 aktív hirdetés (kép + szöveg + akció)</li>
    <li>🔔 4 push/hó követőidnek (kupon, akció)</li>
    <li>🌍 4-nyelvű (DE/HU/RO/EN) automatikus megjelenítés</li>
    <li>📊 Statisztika: hányan láttak, kattintottak</li>
  </ul>
</div>
