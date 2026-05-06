<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$slug = sanitize_title(get_query_var('slug'));
$adv = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppv_advertisers WHERE slug = %s AND is_active = 1",
    $slug
));
if (!$adv) {
    status_header(404);
    echo '<h1>404 — bolt nem található</h1>';
    return;
}

$lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de';
$desc = (string)($adv->{'description_' . $lang} ?: $adv->description_de ?: '');

$ads = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppv_ads
     WHERE filiale_id = %d AND is_active = 1
       AND (valid_from IS NULL OR valid_from <= NOW())
       AND (valid_to IS NULL OR valid_to >= NOW())
     ORDER BY id DESC", $adv->id
));

// Impression tracking removed — only clicks count (pin-click + Részletek button).

if (function_exists('ppv_maybe_start_session')) ppv_maybe_start_session();
$user_id = !empty($_SESSION['ppv_user_id']) ? (int)$_SESSION['ppv_user_id'] : 0;
$is_following = $user_id ? PPV_Advertisers::is_following($user_id, $adv->id) : false;
$is_anon = !empty($_SESSION['ppv_is_anon']);

// Smart App Banner: only emit if Apple App Store ID is configured.
$ios_app_id = (string) get_option('ppv_ios_app_store_id', '');
$current_url = (is_ssl() ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// i18n strings for follow CTA
$L = [
    'de' => ['rate'=>'','cta_idle'=>'🔔 Folgen + Push aktivieren','cta_following'=>'✓ Du folgst — Push aktiv','cta_loading'=>'…','desc'=>'Verpasse keine neuen Sorten + Aktionen','thanks'=>'Danke! Du erhältst jetzt Push-Updates.','already'=>'Du folgst schon.','unfollow'=>'Folgen beenden','login_link'=>'Email hinzufügen für Coupons','push_blocked'=>'Push blockiert. Aktiviere Benachrichtigungen in den Einstellungen.','install_app_ios'=>'PunktePass App installieren','install_app_android'=>'App installieren'],
    'hu' => ['rate'=>'','cta_idle'=>'🔔 Követés + Push bekapcsolása','cta_following'=>'✓ Követed — Push aktív','cta_loading'=>'…','desc'=>'Ne maradj le új ízekről és akciókról','thanks'=>'Köszönjük! Mostantól megkapod a push-üzeneteket.','already'=>'Már követed.','unfollow'=>'Követés befejezése','login_link'=>'Email megadása kuponokhoz','push_blocked'=>'Push letiltva. Kapcsold be a beállításokban.','install_app_ios'=>'PunktePass App telepítése','install_app_android'=>'App telepítése'],
    'ro' => ['rate'=>'','cta_idle'=>'🔔 Urmărește + Activează Push','cta_following'=>'✓ Urmărești — Push activ','cta_loading'=>'…','desc'=>'Nu rata sortimente noi și oferte','thanks'=>'Mulțumim! Vei primi notificări push.','already'=>'Urmărești deja.','unfollow'=>'Oprește urmărirea','login_link'=>'Adaugă email pentru cupoane','push_blocked'=>'Push blocat. Activează notificările.','install_app_ios'=>'Instalează PunktePass','install_app_android'=>'Instalează aplicația'],
    'en' => ['rate'=>'','cta_idle'=>'🔔 Follow + Enable Push','cta_following'=>'✓ Following — Push active','cta_loading'=>'…','desc'=>'Never miss new flavors + offers','thanks'=>'Thanks! You will now receive push updates.','already'=>'Already following.','unfollow'=>'Stop following','login_link'=>'Add email for coupons','push_blocked'=>'Push blocked. Enable notifications in settings.','install_app_ios'=>'Install PunktePass App','install_app_android'=>'Install app'],
];
$T = $L[$lang] ?? $L['de'];
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html($adv->business_name); ?> – PunktePass</title>
<?php if ($ios_app_id): ?>
<!-- Apple Smart App Banner: shows native "GET / OPEN" banner in Safari -->
<meta name="apple-itunes-app" content="app-id=<?php echo esc_attr($ios_app_id); ?>, app-argument=<?php echo esc_url($current_url); ?>">
<?php endif; ?>
<link rel="manifest" href="/manifest.json">
<style>
* { box-sizing:border-box; }
body { margin:0; font:14px/1.5 system-ui,-apple-system,sans-serif; background:#f9fafb; }
.bp-cover { height:200px; background:#6366f1 center/cover; <?php if ($adv->cover_url): ?>background-image:url('<?php echo esc_url($adv->cover_url); ?>');<?php endif; ?> }
.bp-head { position:relative; max-width:680px; margin:-60px auto 0; padding:20px; background:#fff; border-radius:12px 12px 0 0; box-shadow:0 -4px 12px rgba(0,0,0,.06); }
.bp-logo { width:80px; height:80px; border-radius:50%; border:4px solid #fff; background:#eee center/cover; margin-top:-50px; <?php if ($adv->logo_url): ?>background-image:url('<?php echo esc_url($adv->logo_url); ?>');<?php endif; ?> }
.bp-name { font-size:22px; font-weight:700; margin:8px 0 4px; }
.bp-cat  { color:#6b7280; font-size:13px; }
.bp-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
.bp-btn { padding:10px 16px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13px; }
.bp-btn.primary { background:#6366f1; color:#fff; }
.bp-btn.green { background:#10b981; color:#fff; }
.bp-btn.wa { background:#25d366; color:#fff; }
.bp-btn.gray { background:#fff; color:#111; border:1px solid #e5e7eb; }
.bp-body { max-width:680px; margin:0 auto; padding:20px; background:#fff; }
.bp-ads { max-width:680px; margin:0 auto; padding:20px; }
.bp-ad { background:#fff; border-radius:12px; padding:16px; margin-bottom:12px; box-shadow:0 1px 3px rgba(0,0,0,.05); }
.bp-ad img { width:100%; border-radius:8px; max-height:240px; object-fit:cover; margin-bottom:8px; }
.bp-ad h3 { margin:0 0 4px; }
.bp-ad-fol { display:inline-block; background:gold; padding:2px 6px; border-radius:4px; font-size:10px; }
</style>
</head>
<body>
<div class="bp-cover"></div>
<div class="bp-head">
  <div class="bp-logo"></div>
  <div class="bp-name"><?php echo esc_html($adv->business_name); ?></div>
  <div class="bp-cat"><?php echo esc_html((string)($adv->category ?? '')); ?> • <?php echo esc_html(trim((string)($adv->city ?? '') . ', ' . (string)($adv->country ?? ''), ', ')); ?></div>
  <div class="bp-actions">
    <?php if ($adv->phone): ?><a href="tel:<?php echo esc_attr($adv->phone); ?>" class="bp-btn green">📞 <?php echo esc_html($adv->phone); ?></a><?php endif; ?>
    <?php if ($adv->whatsapp): ?><a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/','',$adv->whatsapp)); ?>" class="bp-btn wa">💬 WhatsApp</a><?php endif; ?>
    <?php if ($adv->lat && $adv->lng): ?><a target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $adv->lat; ?>,<?php echo $adv->lng; ?>" class="bp-btn gray">🗺 Útvonal</a><?php endif; ?>
  </div>

  <!-- Anonymous-friendly follow + push CTA. Big, prominent, single-tap. -->
  <div id="bp-follow-card" data-slug="<?php echo esc_attr($adv->slug); ?>" data-following="<?php echo $is_following ? '1' : '0'; ?>" style="margin-top:18px;padding:18px;border-radius:14px;background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#fff;box-shadow:0 4px 16px rgba(245,158,11,.3);">
    <button type="button" id="bp-follow-btn" style="width:100%;padding:14px 18px;border-radius:10px;border:none;background:rgba(255,255,255,.95);color:#92400e;font-size:16px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
      <span id="bp-follow-label"><?php echo esc_html($is_following ? $T['cta_following'] : $T['cta_idle']); ?></span>
    </button>
    <div style="font-size:12px;text-align:center;margin-top:8px;opacity:.95;"><?php echo esc_html($T['desc']); ?></div>
    <div id="bp-follow-msg" style="font-size:12px;text-align:center;margin-top:6px;min-height:1em;"></div>
    <?php if ($is_anon): ?>
    <div style="font-size:11px;text-align:center;margin-top:8px;opacity:.85;">
      <a href="<?php echo esc_url(home_url('/login?return=' . urlencode($_SERVER['REQUEST_URI']))); ?>" style="color:#fff;text-decoration:underline;"><?php echo esc_html($T['login_link']); ?></a>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php if ($desc): ?><div class="bp-body"><?php echo wp_kses_post(wpautop($desc)); ?></div><?php endif; ?>
<div class="bp-ads">
  <h2 style="margin:20px 0 12px;">📣 Aktuális ajánlatok</h2>
  <?php if (empty($ads)): ?>
    <p style="color:#6b7280;">Még nincs aktív hirdetés.</p>
  <?php else: ?>
    <?php foreach ($ads as $ad):
      // Fallback: per-lang → de → simple `title`/`body` (új form csak ezt tölti)
      $title = (string)($ad->{'title_' . $lang} ?: ($ad->title_de ?: ($ad->title ?? '')));
      $body  = (string)($ad->{'body_' . $lang}  ?: ($ad->body_de  ?: ($ad->body  ?? '')));
      $promo_value = $ad->promo_value ?? '';
    ?>
      <div class="bp-ad">
        <?php if ($ad->image_url): ?><img src="<?php echo esc_url($ad->image_url); ?>" alt=""><?php endif; ?>
        <h3><?php echo esc_html($title); ?>
          <?php if ($ad->followers_only): ?><span class="bp-ad-fol">⭐ Csak követőknek</span><?php endif; ?>
        </h3>
        <p><?php echo wp_kses_post(wpautop($body)); ?></p>
        <?php if ($ad->cta_url): ?><a href="<?php echo esc_url(home_url('/wp-json/punktepass/v1/ad-click/' . (int)$ad->id . '?to=' . urlencode($ad->cta_url))); ?>" class="bp-btn primary" rel="noopener">Részletek →</a><?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
// Follow + push activation flow. One tap on the big yellow button:
//   1) Ask browser for Notification permission (if not yet granted).
//   2) POST /wp-json/ppv/v1/anon-follow → backend creates anon user + cookie
//      and inserts follower row.
//   3) Subscribe to FCM if user accepted notifications and FCM is wired.
//   4) Update button label to "Du folgst — Push aktiv".
(function () {
  const T = <?php echo wp_json_encode($T); ?>;
  const card = document.getElementById('bp-follow-card');
  const btn  = document.getElementById('bp-follow-btn');
  const lbl  = document.getElementById('bp-follow-label');
  const msg  = document.getElementById('bp-follow-msg');
  if (!card || !btn) return;

  const slug = card.dataset.slug;
  let following = card.dataset.following === '1';

  function setUi(state, text) {
    if (state === 'loading') { lbl.textContent = T.cta_loading || '…'; btn.disabled = true; }
    else if (state === 'following') { lbl.textContent = T.cta_following; btn.disabled = false; following = true; }
    else if (state === 'idle') { lbl.textContent = T.cta_idle; btn.disabled = false; following = false; }
    if (text !== undefined) msg.textContent = text;
  }
  setUi(following ? 'following' : 'idle');

  async function askNotificationPermission() {
    if (!('Notification' in window)) return 'unsupported';
    if (Notification.permission === 'granted') return 'granted';
    if (Notification.permission === 'denied') return 'denied';
    try {
      const r = await Notification.requestPermission();
      return r;
    } catch (e) { return 'error'; }
  }

  async function followShop() {
    setUi('loading');
    const perm = await askNotificationPermission();
    try {
      const r = await fetch('/wp-json/ppv/v1/anon-follow', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ advertiser_slug: slug })
      });
      const j = await r.json();
      if (!j || !j.success) {
        setUi('idle', (j && j.msg) || 'Error');
        return;
      }
      let note = T.thanks;
      if (perm === 'denied') note = T.push_blocked;
      setUi('following', note);
      // Trigger FCM subscription if available (existing PP push bridge wakes up here).
      if (window.ppvActivatePush) { try { window.ppvActivatePush(); } catch(e){} }
    } catch (e) {
      setUi('idle', String(e));
    }
  }

  async function unfollowShop() {
    if (!confirm(T.unfollow + '?')) return;
    setUi('loading');
    try {
      await fetch('/wp-json/ppv/v1/anon-unfollow', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ advertiser_slug: slug })
      });
      setUi('idle', '');
    } catch (e) { setUi('following', String(e)); }
  }

  btn.addEventListener('click', () => following ? unfollowShop() : followShop());
})();

// Android: surface PWA install prompt as a custom inline banner if the
// browser supports beforeinstallprompt (Chrome/Edge/Samsung Internet).
let _ppvDeferredPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  _ppvDeferredPrompt = e;
  const card = document.getElementById('bp-follow-card');
  if (!card) return;
  const banner = document.createElement('button');
  banner.textContent = '📱 ' + (<?php echo wp_json_encode($T['install_app_android']); ?>);
  banner.type = 'button';
  banner.style.cssText = 'width:100%;margin-top:10px;padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,.6);background:transparent;color:#fff;font-weight:600;cursor:pointer;';
  banner.addEventListener('click', async () => {
    if (!_ppvDeferredPrompt) return;
    _ppvDeferredPrompt.prompt();
    await _ppvDeferredPrompt.userChoice;
    _ppvDeferredPrompt = null;
    banner.remove();
  });
  card.appendChild(banner);
});
</script>
</body>
</html>
