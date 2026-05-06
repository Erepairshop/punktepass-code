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
    'de' => ['rate'=>'','cta_idle'=>'➕ Folgen','cta_following'=>'✓ Du folgst — Push aktiv','cta_loading'=>'…','desc'=>'Verpasse keine neuen Sorten + Aktionen','thanks'=>'Danke! Du erhältst jetzt Push-Updates.','already'=>'Du folgst schon.','unfollow'=>'Folgen beenden','login_link'=>'Email hinzufügen für Coupons','push_blocked'=>'Push blockiert. Aktiviere Benachrichtigungen in den Einstellungen.','install_app_ios'=>'PunktePass App installieren','install_app_android'=>'App installieren','route'=>'🗺 Wegbeschreibung','offers'=>'📣 Aktuelle Angebote','no_offers'=>'Noch keine aktive Werbung.','details'=>'Details →','followers_only'=>'⭐ Nur für Follower'],
    'hu' => ['rate'=>'','cta_idle'=>'➕ Követés','cta_following'=>'✓ Követed — Push aktív','cta_loading'=>'…','desc'=>'Ne maradj le új ízekről és akciókról','thanks'=>'Köszönjük! Mostantól megkapod a push-üzeneteket.','already'=>'Már követed.','unfollow'=>'Követés befejezése','login_link'=>'Email megadása kuponokhoz','push_blocked'=>'Push letiltva. Kapcsold be a beállításokban.','install_app_ios'=>'PunktePass App telepítése','install_app_android'=>'App telepítése','route'=>'🗺 Útvonal','offers'=>'📣 Aktuális ajánlatok','no_offers'=>'Még nincs aktív hirdetés.','details'=>'Részletek →','followers_only'=>'⭐ Csak követőknek'],
    'ro' => ['rate'=>'','cta_idle'=>'➕ Urmărește','cta_following'=>'✓ Urmărești — Push activ','cta_loading'=>'…','desc'=>'Nu rata sortimente noi și oferte','thanks'=>'Mulțumim! Vei primi notificări push.','already'=>'Urmărești deja.','unfollow'=>'Oprește urmărirea','login_link'=>'Adaugă email pentru cupoane','push_blocked'=>'Push blocat. Activează notificările.','install_app_ios'=>'Instalează PunktePass','install_app_android'=>'Instalează aplicația','route'=>'🗺 Direcții','offers'=>'📣 Oferte curente','no_offers'=>'Încă nu sunt anunțuri active.','details'=>'Detalii →','followers_only'=>'⭐ Doar pentru urmăritori'],
    'en' => ['rate'=>'','cta_idle'=>'➕ Follow','cta_following'=>'✓ Following — Push active','cta_loading'=>'…','desc'=>'Never miss new flavors + offers','thanks'=>'Thanks! You will now receive push updates.','already'=>'Already following.','unfollow'=>'Stop following','login_link'=>'Add email for coupons','push_blocked'=>'Push blocked. Enable notifications in settings.','install_app_ios'=>'Install PunktePass App','install_app_android'=>'Install app','route'=>'🗺 Directions','offers'=>'📣 Current offers','no_offers'=>'No active offers yet.','details'=>'Details →','followers_only'=>'⭐ Followers only'],
];
$T = $L[$lang] ?? $L['de'];

// Post-follow modal copy ("install the app for more")
$post_modal = [
    'de' => ['title' => 'Geschafft! Du folgst jetzt.', 'body' => 'Lade die kostenlose PunktePass App, um Aktionen, Coupons und mehr zu erhalten.', 'cta' => '📱 App herunterladen', 'skip' => 'Später'],
    'hu' => ['title' => 'Kész! Most már követed.', 'body' => 'Töltsd le az ingyenes PunktePass alkalmazást akciókért, kuponokért és többért.', 'cta' => '📱 Alkalmazás letöltése', 'skip' => 'Később'],
    'ro' => ['title' => 'Gata! Urmărești acum.', 'body' => 'Descarcă aplicația PunktePass gratuită pentru oferte, cupoane și mai mult.', 'cta' => '📱 Descarcă aplicația', 'skip' => 'Mai târziu'],
    'en' => ['title' => 'Done! You are now following.', 'body' => 'Get the free PunktePass app for offers, coupons and more.', 'cta' => '📱 Get the app', 'skip' => 'Later'],
];
$PM = $post_modal[$lang] ?? $post_modal['de'];
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
    <?php if ($adv->lat && $adv->lng): ?><a target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $adv->lat; ?>,<?php echo $adv->lng; ?>" class="bp-btn gray"><?php echo esc_html($T['route']); ?></a><?php endif; ?>
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
  <h2 style="margin:20px 0 12px;"><?php echo esc_html($T['offers']); ?></h2>
  <?php if (empty($ads)): ?>
    <p style="color:#6b7280;"><?php echo esc_html($T['no_offers']); ?></p>
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
          <?php if ($ad->followers_only): ?><span class="bp-ad-fol"><?php echo esc_html($T['followers_only']); ?></span><?php endif; ?>
        </h3>
        <p><?php echo wp_kses_post(wpautop($body)); ?></p>
        <?php if ($ad->cta_url): ?><a href="<?php echo esc_url(home_url('/wp-json/punktepass/v1/ad-click/' . (int)$ad->id . '?to=' . urlencode($ad->cta_url))); ?>" class="bp-btn primary" rel="noopener"><?php echo esc_html($T['details']); ?></a><?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Post-follow modal: shown after a successful follow + push activation,
     deep-linking the user to the right app store. -->
<div id="bp-app-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;padding:24px;">
  <div style="background:#fff;border-radius:16px;max-width:380px;width:100%;padding:24px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.25);">
    <div style="font-size:42px;line-height:1;margin-bottom:8px;">🎉</div>
    <div style="font-size:18px;font-weight:700;color:#111;margin-bottom:8px;"><?php echo esc_html($PM['title']); ?></div>
    <div style="font-size:14px;color:#444;margin-bottom:18px;line-height:1.45;"><?php echo esc_html($PM['body']); ?></div>
    <a id="bp-app-cta" href="#" style="display:block;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:14px;border-radius:10px;text-decoration:none;font-weight:700;font-size:15px;margin-bottom:8px;"><?php echo esc_html($PM['cta']); ?></a>
    <button type="button" id="bp-app-skip" style="background:none;border:none;color:#9ca3af;font-size:13px;cursor:pointer;padding:6px;"><?php echo esc_html($PM['skip']); ?></button>
  </div>
</div>

<script>
// Detect platform; pick the right store URL.
function ppvAppStoreUrl(){
  var ua = navigator.userAgent || '';
  if (/iPhone|iPad|iPod/.test(ua)) return 'https://apps.apple.com/app/punktepass/id6755680197';
  if (/Android/.test(ua))         return 'https://play.google.com/store/apps/details?id=de.punktepass.twa';
  return 'https://punktepass.de/'; // desktop fallback → marketing page
}
function ppvShowAppModal(){
  var modal = document.getElementById('bp-app-modal');
  if (!modal) return;
  var cta = document.getElementById('bp-app-cta');
  var skip = document.getElementById('bp-app-skip');
  if (cta) cta.href = ppvAppStoreUrl();
  modal.style.display = 'flex';
  if (skip) skip.onclick = function(){ modal.style.display = 'none'; };
}
</script>
<script>
// Firebase Web SDK config (mirror of class-ppv-user-settings.php and
// firebase-messaging-sw.js — keep in sync if those change).
var ppvFirebaseConfig = {
    apiKey: "AIzaSyBB4-sQb-ZlMEDj4LVGYSenB8b8R_mUuOI",
    authDomain: "punktepass.firebaseapp.com",
    projectId: "punktepass",
    storageBucket: "punktepass.firebasestorage.app",
    messagingSenderId: "373165045072",
    appId: "1:373165045072:web:1ef83f576e6fc222a7a855"
};
var ppvVapidKey = 'BCCTa3Fuxw0ZHzNsUf_pkuYsajMCwp69kCSxvV6x9lpYNDkz4MkRM4Kezp8s48qyxXo5GVu8TBcIs3Ih42Vci1Y';

function ppvLoadScript(src){
    return new Promise(function(res, rej){
        var s = document.createElement('script');
        s.src = src; s.onload = res; s.onerror = rej;
        document.head.appendChild(s);
    });
}

// Register an FCM device token with the existing /push/register endpoint.
// If we have only an anon session here, the backend get-or-create has already
// run inside /anon-follow, so $_SESSION['ppv_user_id'] is set by the time we
// hit /push/register. The endpoint reads user_id from session as a fallback.
async function ppvRegisterPushToken(lang) {
    var dbg = function(stage, val){ try { console.log('[PPV-Push]', stage, val); } catch(e){} };
    if (!('Notification' in window)) throw new Error('Notification API unavailable');
    if (Notification.permission !== 'granted') throw new Error('Notification permission ' + Notification.permission);
    if (!('serviceWorker' in navigator)) throw new Error('serviceWorker unavailable');

    if (typeof firebase === 'undefined') {
        dbg('loading firebase SDK');
        await ppvLoadScript('https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js');
        await ppvLoadScript('https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js');
    }
    if (!firebase.apps.length) firebase.initializeApp(ppvFirebaseConfig);

    dbg('registering SW');
    var swReg;
    try {
        swReg = await navigator.serviceWorker.register('/firebase-messaging-sw.js', { scope: '/' });
    } catch(e) { throw new Error('SW reg fail: ' + (e.message||e)); }

    // Wait for the SW to be activated — Chrome rejects PushManager.subscribe()
    // unless the SW reached "activated" state.
    if (swReg.installing || swReg.waiting) {
        dbg('waiting for SW activation');
        await new Promise(function(resolve){
            var sw = swReg.installing || swReg.waiting;
            if (!sw) return resolve();
            sw.addEventListener('statechange', function(){
                if (sw.state === 'activated') resolve();
            });
            // Safety net
            setTimeout(resolve, 8000);
        });
    }
    // Belt-and-braces: also wait on the global ready promise.
    try { await navigator.serviceWorker.ready; } catch(e){}

    dbg('getting FCM token');
    var token;
    try {
        token = await firebase.messaging().getToken({ vapidKey: ppvVapidKey, serviceWorkerRegistration: swReg });
    } catch(e) { throw new Error('getToken fail: ' + (e.message||e)); }
    if (!token) throw new Error('getToken returned empty');
    dbg('got token len=' + token.length);

    var resp = await fetch('/wp-json/punktepass/v1/push/register', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token, platform: 'web', language: lang || 'de', device_name: 'Web (PunktePass /business)' })
    });
    var d = await resp.json();
    dbg('register resp', d);
    if (!d || !d.success) throw new Error('register fail: ' + JSON.stringify(d));
    try { localStorage.setItem('ppv_fcm_token', token); } catch(e){}
    return token;
}

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
      // Now register an FCM token so the backend can actually deliver pushes.
      // Done after the follow REST call so the anon user/session exists.
      if (perm === 'granted') {
        ppvRegisterPushToken(<?php echo wp_json_encode($lang); ?>).then(function(t){
          if (msg) { msg.style.color = '#fff'; msg.textContent = T.thanks + ' (push: OK)'; }
        }).catch(function(err){
          if (msg) { msg.style.color = '#fee'; msg.textContent = 'Push: ' + (err && err.message ? err.message : err); }
        });
      }
      // Show "install the app for more" modal a moment after the success state
      // settles, so the user sees their follow confirmed first.
      setTimeout(function(){ if (typeof ppvShowAppModal === 'function') ppvShowAppModal(); }, 900);
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

  // Auto-retry push token registration if user is already following and
  // browser already has notification permission, but no FCM token recorded
  // locally. Catches "tapped follow but token reg silently failed" cases.
  (async function maybeRetryPushReg(){
    if (!following) return;
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    let already = null;
    try { already = localStorage.getItem('ppv_fcm_token'); } catch(e){}
    if (already) return;
    if (msg) { msg.style.color = '#fff'; msg.textContent = 'Push registrierung läuft…'; }
    try {
      await ppvRegisterPushToken(<?php echo wp_json_encode($lang); ?>);
      if (msg) { msg.style.color = '#fff'; msg.textContent = T.thanks + ' (push: OK)'; }
    } catch(err) {
      if (msg) { msg.style.color = '#fee'; msg.textContent = 'Push: ' + (err && err.message ? err.message : err); }
    }
  })();
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
