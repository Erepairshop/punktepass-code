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

if (function_exists('ppv_maybe_start_session')) ppv_maybe_start_session();
$user_id = !empty($_SESSION['ppv_user_id']) ? (int)$_SESSION['ppv_user_id'] : 0;
$is_following = $user_id ? PPV_Advertisers::is_following($user_id, $adv->id) : false;
$is_anon = !empty($_SESSION['ppv_is_anon']);

// Smart App Banner: only emit if Apple App Store ID is configured.
$ios_app_id  = (string) get_option('ppv_ios_app_store_id', '');
$current_url = (is_ssl() ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Reviews aggregate (avg + count)
$rev_agg     = class_exists('PPV_Advertiser_Reviews') ? PPV_Advertiser_Reviews::aggregate($adv->id) : ['avg'=>null, 'count'=>0];
$rev_recent  = class_exists('PPV_Advertiser_Reviews') ? PPV_Advertiser_Reviews::recent_reviews($adv->id, 6) : [];

$L = [
    'de' => ['cta_idle'=>'Folgen','cta_following'=>'Du folgst','cta_loading'=>'…','desc'=>'Verpasse keine neuen Aktionen','thanks'=>'Push aktiviert. Du erhältst jetzt Updates.','unfollow'=>'Folgen beenden?','login_link'=>'E-Mail hinzufügen für Coupons','push_blocked'=>'Push blockiert — bitte Benachrichtigungen erlauben.','install_app_ios'=>'PunktePass App','install_app_android'=>'App installieren','route'=>'Route','call'=>'Anrufen','whatsapp'=>'WhatsApp','offers'=>'Aktionen','no_offers'=>'Aktuell keine aktiven Aktionen.','details'=>'Details','followers_only'=>'Follower-Aktion','hours_title'=>'Öffnungszeiten','open_now'=>'Geöffnet','closed_now'=>'Geschlossen','closed_day'=>'Geschlossen','website'=>'Webseite','address'=>'Adresse','about'=>'Über uns','gallery'=>'Galerie','reviews'=>'Bewertungen','rate_title'=>'Bewerte dieses Geschäft','rate_comment'=>'Kommentar (optional)','rate_submit'=>'Bewertung senden','rate_thanks'=>'Danke für deine Bewertung!','rate_login'=>'Erst folgen, dann bewerten.','rate_already'=>'Du kannst dieses Geschäft nur 1× pro Jahr bewerten.','rate_choose'=>'Wähle 1–5 Sterne.','no_reviews'=>'Noch keine Bewertungen.','days'=>['mo'=>'Mo','di'=>'Di','mi'=>'Mi','do'=>'Do','fr'=>'Fr','sa'=>'Sa','so'=>'So']],
    'hu' => ['cta_idle'=>'Követés','cta_following'=>'Követed','cta_loading'=>'…','desc'=>'Ne maradj le az akciókról','thanks'=>'Push bekapcsolva. Megkapod a frissítéseket.','unfollow'=>'Követés megszüntetése?','login_link'=>'E-mail hozzáadása kuponokhoz','push_blocked'=>'A push le van tiltva — engedélyezd az értesítéseket.','install_app_ios'=>'PunktePass App','install_app_android'=>'App telepítése','route'=>'Útvonal','call'=>'Hívás','whatsapp'=>'WhatsApp','offers'=>'Akciók','no_offers'=>'Jelenleg nincs aktív akció.','details'=>'Részletek','followers_only'=>'Csak követőknek','hours_title'=>'Nyitvatartás','open_now'=>'Nyitva','closed_now'=>'Zárva','closed_day'=>'Zárva','website'=>'Weboldal','address'=>'Cím','about'=>'Rólunk','gallery'=>'Galéria','reviews'=>'Értékelések','rate_title'=>'Értékeld az üzletet','rate_comment'=>'Megjegyzés (opcionális)','rate_submit'=>'Értékelés küldése','rate_thanks'=>'Köszönjük az értékelést!','rate_login'=>'Előbb kövesd, utána értékelj.','rate_already'=>'Évente csak egyszer értékelheted ezt az üzletet.','rate_choose'=>'Válassz 1–5 csillagot.','no_reviews'=>'Még nincs értékelés.','days'=>['mo'=>'H','di'=>'K','mi'=>'Sze','do'=>'Cs','fr'=>'P','sa'=>'Szo','so'=>'V']],
    'ro' => ['cta_idle'=>'Urmărește','cta_following'=>'Urmărești','cta_loading'=>'…','desc'=>'Nu rata noile oferte','thanks'=>'Push activat. Vei primi notificări.','unfollow'=>'Oprești urmărirea?','login_link'=>'Adaugă email pentru cupoane','push_blocked'=>'Push blocat — activează notificările.','install_app_ios'=>'Aplicația PunktePass','install_app_android'=>'Instalează aplicația','route'=>'Direcții','call'=>'Sună','whatsapp'=>'WhatsApp','offers'=>'Oferte','no_offers'=>'Niciun anunț activ momentan.','details'=>'Detalii','followers_only'=>'Doar pentru urmăritori','hours_title'=>'Program','open_now'=>'Deschis','closed_now'=>'Închis','closed_day'=>'Închis','website'=>'Site web','address'=>'Adresă','about'=>'Despre noi','gallery'=>'Galerie','reviews'=>'Recenzii','rate_title'=>'Evaluează magazinul','rate_comment'=>'Comentariu (opțional)','rate_submit'=>'Trimite evaluarea','rate_thanks'=>'Mulțumim pentru evaluare!','rate_login'=>'Întâi urmărește, apoi evaluează.','rate_already'=>'Poți evalua acest magazin doar o dată pe an.','rate_choose'=>'Alege 1–5 stele.','no_reviews'=>'Încă nu sunt recenzii.','days'=>['mo'=>'Lu','di'=>'Ma','mi'=>'Mi','do'=>'Jo','fr'=>'Vi','sa'=>'Sâ','so'=>'Du']],
    'en' => ['cta_idle'=>'Follow','cta_following'=>'Following','cta_loading'=>'…','desc'=>'Never miss new offers','thanks'=>'Push activated. You will now receive updates.','unfollow'=>'Stop following?','login_link'=>'Add email for coupons','push_blocked'=>'Push blocked — please allow notifications.','install_app_ios'=>'PunktePass App','install_app_android'=>'Install app','route'=>'Directions','call'=>'Call','whatsapp'=>'WhatsApp','offers'=>'Offers','no_offers'=>'No active offers yet.','details'=>'Details','followers_only'=>'Followers only','hours_title'=>'Opening hours','open_now'=>'Open','closed_now'=>'Closed','closed_day'=>'Closed','website'=>'Website','address'=>'Address','about'=>'About','gallery'=>'Gallery','reviews'=>'Reviews','rate_title'=>'Rate this business','rate_comment'=>'Comment (optional)','rate_submit'=>'Submit review','rate_thanks'=>'Thanks for your review!','rate_login'=>'Follow first, then review.','rate_already'=>'You can review this business once per year.','rate_choose'=>'Pick 1–5 stars.','no_reviews'=>'No reviews yet.','days'=>['mo'=>'Mon','di'=>'Tue','mi'=>'Wed','do'=>'Thu','fr'=>'Fri','sa'=>'Sat','so'=>'Sun']],
];
$T = $L[$lang] ?? $L['de'];

$post_modal = [
    'de' => ['title'=>'Geschafft! Du folgst jetzt.','body'=>'Lade die kostenlose PunktePass App für Aktionen und Coupons.','cta'=>'App herunterladen','skip'=>'Später'],
    'hu' => ['title'=>'Kész! Most már követed.','body'=>'Töltsd le az ingyenes PunktePass alkalmazást akciókért és kuponokért.','cta'=>'Alkalmazás letöltése','skip'=>'Később'],
    'ro' => ['title'=>'Gata! Urmărești acum.','body'=>'Descarcă aplicația PunktePass gratuită pentru oferte și cupoane.','cta'=>'Descarcă aplicația','skip'=>'Mai târziu'],
    'en' => ['title'=>'Done! You are following.','body'=>'Get the free PunktePass app for offers and coupons.','cta'=>'Get the app','skip'=>'Later'],
];
$PM = $post_modal[$lang] ?? $post_modal['de'];

// Parse JSON-encoded fields once, with safe defaults.
$hours_data = !empty($adv->opening_hours) ? json_decode($adv->opening_hours, true) : null;
$gallery    = !empty($adv->gallery) ? json_decode($adv->gallery, true) : null;
$social     = !empty($adv->social) ? json_decode($adv->social, true) : null;

// Open-now status
$open_now = null; $cur = null;
if (is_array($hours_data)) {
    $tz_str = function_exists('wp_timezone_string') ? wp_timezone_string() : 'Europe/Berlin';
    try { $now = new DateTime('now', new DateTimeZone($tz_str)); } catch (Exception $e) { $now = new DateTime('now'); }
    $day_keys = ['mo','di','mi','do','fr','sa','so'];
    $cur = $day_keys[((int)$now->format('N')) - 1] ?? null;
    if ($cur && isset($hours_data[$cur])) {
        $d = $hours_data[$cur];
        if (!empty($d['closed'])) $open_now = false;
        elseif (!empty($d['von']) && !empty($d['bis'])) {
            $cur_t = (int) $now->format('Hi');
            $von_t = (int) str_replace(':', '', $d['von']);
            $bis_t = (int) str_replace(':', '', $d['bis']);
            $open_now = ($cur_t >= $von_t && $cur_t <= $bis_t);
        }
    }
}

$full_address = trim(implode(', ', array_filter([
    (string)($adv->address ?? ''),
    trim((string)($adv->postcode ?? '') . ' ' . (string)($adv->city ?? '')),
    (string)($adv->country ?? ''),
])), ', ');
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html($adv->business_name); ?> – PunktePass</title>
<?php if ($ios_app_id): ?>
<meta name="apple-itunes-app" content="app-id=<?php echo esc_attr($ios_app_id); ?>, app-argument=<?php echo esc_url($current_url); ?>">
<?php endif; ?>
<link rel="manifest" href="/manifest.json">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #f5f7fb;
  --surface: #ffffff;
  --text: #0f172a;
  --text-soft: #475569;
  --muted: #94a3b8;
  --border: #e2e8f0;
  --primary: #6366f1;
  --primary-700: #4f46e5;
  --accent: #f59e0b;
  --accent-2: #fbbf24;
  --green: #10b981;
  --red: #ef4444;
  --whats: #25d366;
  --shadow: 0 8px 24px rgba(15,23,42,.08);
  --shadow-soft: 0 2px 8px rgba(15,23,42,.05);
  --radius: 16px;
}
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  background: var(--bg); color: var(--text);
  font-size: 15px; line-height: 1.5;
  -webkit-font-smoothing: antialiased;
}
img { display: block; max-width: 100%; }
a { color: var(--primary-700); }

.bp-cover {
  height: 220px;
  background: linear-gradient(135deg, var(--primary), #8b5cf6) center/cover;
  <?php if ($adv->cover_url): ?>background-image: url('<?php echo esc_url($adv->cover_url); ?>'); background-size: cover; background-position: center;<?php endif; ?>
}
.bp-page { max-width: 720px; margin: 0 auto; padding: 0 16px 80px; }
.bp-card {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 22px;
  margin-bottom: 14px;
}
.bp-card.compact { padding: 18px; }
.bp-card h2 {
  font-size: 17px; font-weight: 700; color: var(--text);
  margin: 0 0 14px; display: flex; align-items: center; gap: 10px;
}
.bp-card h2 i { color: var(--primary); font-size: 22px; }

.bp-head {
  position: relative;
  margin-top: -70px;
  text-align: left;
}
.bp-logo {
  width: 88px; height: 88px; border-radius: 22px;
  background: var(--surface) center/cover no-repeat;
  border: 4px solid var(--surface);
  box-shadow: var(--shadow);
  margin-bottom: 14px;
  <?php if ($adv->logo_url): ?>background-image: url('<?php echo esc_url($adv->logo_url); ?>');<?php endif; ?>
}
.bp-name { font-size: 26px; font-weight: 800; margin: 0 0 6px; color: var(--text); letter-spacing: -.01em; }
.bp-meta {
  color: var(--text-soft); font-size: 14px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
}
.bp-meta i { color: var(--muted); font-size: 16px; }
.bp-meta-rating { display:inline-flex; align-items:center; gap:4px; font-weight:600; color:var(--text); }
.bp-meta-rating i { color: var(--accent); }

.bp-quick {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
  gap: 8px; margin-top: 18px;
}
.bp-qbtn {
  display: flex; align-items: center; justify-content: center; gap: 6px;
  padding: 12px; border-radius: 12px; text-decoration: none;
  font-size: 13px; font-weight: 600;
  background: #f1f5f9; color: var(--text); border: 1px solid var(--border);
  transition: all .15s;
}
.bp-qbtn:active { transform: scale(.97); }
.bp-qbtn i { font-size: 18px; }
.bp-qbtn.green { background: linear-gradient(135deg, #10b981, #059669); color: #fff; border-color: transparent; }
.bp-qbtn.wa    { background: linear-gradient(135deg, #25d366, #128c7e); color: #fff; border-color: transparent; }
.bp-qbtn.route { background: linear-gradient(135deg, var(--primary), #8b5cf6); color: #fff; border-color: transparent; }

.bp-follow {
  margin-top: 14px;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  border-radius: var(--radius);
  padding: 18px;
  color: #fff;
  box-shadow: 0 6px 16px rgba(245,158,11,.32);
}
.bp-follow.active { background: linear-gradient(135deg, var(--green), #059669); box-shadow: 0 6px 16px rgba(16,185,129,.30); }
.bp-follow-btn {
  width: 100%; padding: 15px; border-radius: 12px; border: none;
  background: rgba(255,255,255,.97); color: #92400e;
  font-size: 16px; font-weight: 700;
  display: flex; align-items: center; justify-content: center; gap: 10px;
  cursor: pointer; font-family: inherit;
  transition: transform .15s;
}
.bp-follow.active .bp-follow-btn { color: #064e3b; }
.bp-follow-btn:active { transform: scale(.98); }
.bp-follow-btn i { font-size: 20px; }
.bp-follow-desc { font-size: 12px; text-align: center; margin-top: 8px; opacity: .95; }
.bp-follow-msg { font-size: 12px; text-align: center; margin-top: 6px; min-height: 1em; opacity: .95; }
.bp-follow-link { font-size: 11px; text-align: center; margin-top: 8px; opacity: .85; }
.bp-follow-link a { color: #fff; text-decoration: underline; }

.bp-hours { width: 100%; border-collapse: collapse; }
.bp-hours tr { border-bottom: 1px solid #f1f5f9; }
.bp-hours tr:last-child { border-bottom: none; }
.bp-hours td { padding: 10px 4px; font-size: 14px; }
.bp-hours td:first-child { font-weight: 600; color: var(--text); width: 40%; }
.bp-hours td:last-child { color: var(--text-soft); }
.bp-hours tr.today { background: #fef3c7; border-radius: 6px; }
.bp-hours tr.today td:first-child::after { content: '•'; color: var(--accent); margin-left: 6px; }
.bp-status-pill {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 4px 10px; border-radius: 999px;
  font-size: 12px; font-weight: 600;
  margin-left: auto;
}
.bp-status-pill.open  { background: #d1fae5; color: #065f46; }
.bp-status-pill.closed{ background: #fee2e2; color: #991b1b; }

.bp-social { display: flex; flex-wrap: wrap; gap: 8px; }
.bp-social a {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 14px; border-radius: 10px;
  text-decoration: none; font-weight: 600; font-size: 13px;
  color: #fff; transition: opacity .15s;
}
.bp-social a:active { opacity: .85; }
.bp-social a i { font-size: 16px; }
.bp-social .fb { background: #1877f2; }
.bp-social .ig { background: linear-gradient(135deg, #833ab4, #fd1d1d, #fcb045); }
.bp-social .tt { background: #000; }
.bp-social .yt { background: #ff0000; }
.bp-social .web{ background: var(--primary); }

.bp-gallery {
  display: flex; gap: 10px;
  overflow-x: auto; padding: 4px 0 8px;
  scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;
}
.bp-gallery a {
  flex: 0 0 auto; scroll-snap-align: start;
  border-radius: 12px; overflow: hidden;
  box-shadow: var(--shadow-soft);
}
.bp-gallery img { height: 160px; width: auto; object-fit: cover; }

.bp-ad {
  background: #fff; border-radius: 12px;
  padding: 14px;
  border: 1px solid var(--border);
  margin-bottom: 10px;
}
.bp-ad img { width: 100%; border-radius: 10px; max-height: 240px; object-fit: cover; margin-bottom: 10px; }
.bp-ad h3 { margin: 0 0 4px; font-size: 16px; font-weight: 700; }
.bp-ad p { margin: 4px 0 0; color: var(--text-soft); font-size: 13px; }
.bp-ad-tag { display: inline-block; background: var(--accent); color: #fff; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; margin-left: 6px; }
.bp-ad-cta {
  display: inline-flex; align-items: center; gap: 6px;
  margin-top: 10px; padding: 9px 14px; border-radius: 10px;
  background: var(--primary); color: #fff;
  text-decoration: none; font-weight: 600; font-size: 13px;
}

/* Reviews */
.bp-rev-summary {
  display: flex; align-items: center; gap: 14px; margin-bottom: 14px;
  padding-bottom: 14px; border-bottom: 1px solid var(--border);
}
.bp-rev-avg { font-size: 38px; font-weight: 800; color: var(--text); line-height: 1; }
.bp-rev-stars-static i { color: var(--accent); font-size: 18px; }
.bp-rev-stars-static i.empty { color: #cbd5e1; }
.bp-rev-count { font-size: 13px; color: var(--text-soft); }
.bp-rev-list { display: flex; flex-direction: column; gap: 12px; }
.bp-rev-item { padding: 12px; background: #f8fafc; border-radius: 10px; }
.bp-rev-item .head { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-soft); }
.bp-rev-item .head .stars i { color: var(--accent); font-size: 14px; }
.bp-rev-item .head .stars i.empty { color: #cbd5e1; }
.bp-rev-item .head .date { margin-left: auto; font-size: 12px; color: var(--muted); }
.bp-rev-item .text { margin: 6px 0 0; font-size: 14px; color: var(--text); }
.bp-rev-item .reply { margin-top: 8px; padding: 8px 10px; background: #fff; border-left: 3px solid var(--primary); border-radius: 6px; font-size: 13px; color: var(--text-soft); }

.bp-rate {
  margin-top: 14px; padding: 14px; background: #fffbeb; border: 1px dashed var(--accent); border-radius: 12px;
}
.bp-rate-stars {
  display: flex; gap: 6px; cursor: pointer; user-select: none;
  font-size: 32px; line-height: 1; color: #d1d5db;
}
.bp-rate-stars i.active { color: var(--accent); }
.bp-rate textarea {
  width: 100%; margin-top: 10px;
  border: 1px solid var(--border); border-radius: 10px;
  padding: 10px; font: inherit; resize: vertical;
}
.bp-rate-submit {
  width: 100%; margin-top: 10px; padding: 12px;
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  color: #fff; border: none; border-radius: 10px;
  font-size: 14px; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.bp-rate-msg { font-size: 12px; text-align: center; margin-top: 8px; min-height: 1em; color: var(--text-soft); }

/* Modal */
.bp-modal { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.6); z-index: 9999; align-items: center; justify-content: center; padding: 24px; }
.bp-modal.open { display: flex; animation: fadeIn .2s ease; }
.bp-modal-card { background: #fff; border-radius: var(--radius); max-width: 380px; width: 100%; padding: 26px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
.bp-modal-icon { font-size: 48px; line-height: 1; margin-bottom: 8px; }
.bp-modal-title { font-size: 19px; font-weight: 800; color: var(--text); margin-bottom: 8px; }
.bp-modal-body { font-size: 14px; color: var(--text-soft); margin-bottom: 18px; line-height: 1.5; }
.bp-modal-cta { display: flex; align-items: center; justify-content: center; gap: 8px; background: linear-gradient(135deg, var(--primary), #8b5cf6); color: #fff; padding: 14px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 15px; margin-bottom: 6px; }
.bp-modal-skip { background: none; border: none; color: var(--muted); font-size: 13px; cursor: pointer; padding: 8px; font-family: inherit; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>
</head>
<body>
<div class="bp-cover"></div>
<div class="bp-page">

  <!-- HEADER CARD -->
  <div class="bp-card">
    <div class="bp-head">
      <div class="bp-logo"></div>
      <h1 class="bp-name"><?php echo esc_html($adv->business_name); ?></h1>
      <div class="bp-meta">
        <?php if ($adv->category): ?><span><i class="ri-price-tag-3-line"></i><?php echo esc_html($adv->category); ?></span><?php endif; ?>
        <?php if ($adv->city): ?><span><i class="ri-map-pin-line"></i><?php echo esc_html(trim((string)$adv->city . ', ' . (string)($adv->country ?? ''), ', ')); ?></span><?php endif; ?>
        <?php if ($rev_agg['count'] > 0): ?>
          <span class="bp-meta-rating"><i class="ri-star-fill"></i><?php echo number_format($rev_agg['avg'], 1); ?> <span style="color:var(--muted);font-weight:500;">(<?php echo (int)$rev_agg['count']; ?>)</span></span>
        <?php endif; ?>
      </div>

      <div class="bp-quick">
        <?php if ($adv->phone): ?>
          <a href="tel:<?php echo esc_attr($adv->phone); ?>" class="bp-qbtn green"><i class="ri-phone-fill"></i><?php echo esc_html($T['call']); ?></a>
        <?php endif; ?>
        <?php if ($adv->whatsapp): ?>
          <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/','',$adv->whatsapp)); ?>" class="bp-qbtn wa" target="_blank" rel="noopener"><i class="ri-whatsapp-fill"></i><?php echo esc_html($T['whatsapp']); ?></a>
        <?php endif; ?>
        <?php if ($adv->lat && $adv->lng): ?>
          <a target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $adv->lat; ?>,<?php echo $adv->lng; ?>" class="bp-qbtn route"><i class="ri-route-line"></i><?php echo esc_html($T['route']); ?></a>
        <?php endif; ?>
      </div>

      <div id="bp-follow" class="bp-follow<?php echo $is_following ? ' active' : ''; ?>" data-slug="<?php echo esc_attr($adv->slug); ?>" data-following="<?php echo $is_following ? '1' : '0'; ?>">
        <button type="button" id="bp-follow-btn" class="bp-follow-btn">
          <i class="ri-notification-3-line"></i>
          <span id="bp-follow-label"><?php echo esc_html($is_following ? $T['cta_following'] : $T['cta_idle']); ?></span>
        </button>
        <div class="bp-follow-desc"><?php echo esc_html($T['desc']); ?></div>
        <div id="bp-follow-msg" class="bp-follow-msg"></div>
        <?php if ($is_anon): ?>
          <div class="bp-follow-link"><a href="<?php echo esc_url(home_url('/login?return=' . urlencode($_SERVER['REQUEST_URI']))); ?>"><?php echo esc_html($T['login_link']); ?></a></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($desc): ?>
  <div class="bp-card">
    <h2><i class="ri-information-line"></i><?php echo esc_html($T['about']); ?></h2>
    <div style="color:var(--text-soft);line-height:1.6;"><?php echo wp_kses_post(wpautop($desc)); ?></div>
  </div>
  <?php endif; ?>

  <?php if (is_array($hours_data)): ?>
  <div class="bp-card">
    <h2>
      <i class="ri-time-line"></i><?php echo esc_html($T['hours_title']); ?>
      <?php if ($open_now === true): ?><span class="bp-status-pill open">● <?php echo esc_html($T['open_now']); ?></span>
      <?php elseif ($open_now === false): ?><span class="bp-status-pill closed">● <?php echo esc_html($T['closed_now']); ?></span><?php endif; ?>
    </h2>
    <table class="bp-hours">
      <?php foreach (['mo','di','mi','do','fr','sa','so'] as $k):
        $d = $hours_data[$k] ?? null;
        $is_today = $cur === $k;
      ?>
      <tr<?php if ($is_today): ?> class="today"<?php endif; ?>>
        <td><?php echo esc_html($T['days'][$k] ?? $k); ?></td>
        <td>
          <?php if (!$d || !empty($d['closed']) || empty($d['von'])): ?>
            <?php echo esc_html($T['closed_day']); ?>
          <?php else: ?>
            <?php echo esc_html($d['von'] . ' – ' . $d['bis']); ?>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($full_address): ?>
  <div class="bp-card compact">
    <h2><i class="ri-map-pin-2-line"></i><?php echo esc_html($T['address']); ?></h2>
    <p style="margin:0;color:var(--text-soft);"><?php echo esc_html($full_address); ?></p>
  </div>
  <?php endif; ?>

  <?php
  $social_links = [];
  if (is_array($social)) {
      $meta = ['facebook'=>['fb','ri-facebook-circle-fill','Facebook'],'instagram'=>['ig','ri-instagram-line','Instagram'],'tiktok'=>['tt','ri-tiktok-fill','TikTok'],'youtube'=>['yt','ri-youtube-fill','YouTube']];
      foreach ($meta as $k=>[$cls,$icn,$lbl]) if (!empty($social[$k])) $social_links[]=['cls'=>$cls,'icn'=>$icn,'lbl'=>$lbl,'url'=>$social[$k]];
  }
  if (!empty($adv->website)) $social_links[]=['cls'=>'web','icn'=>'ri-global-line','lbl'=>$T['website'],'url'=>$adv->website];
  ?>
  <?php if (!empty($social_links)): ?>
  <div class="bp-card compact">
    <div class="bp-social">
      <?php foreach ($social_links as $sl): ?>
        <a href="<?php echo esc_url($sl['url']); ?>" target="_blank" rel="noopener" class="<?php echo esc_attr($sl['cls']); ?>"><i class="<?php echo esc_attr($sl['icn']); ?>"></i><?php echo esc_html($sl['lbl']); ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (is_array($gallery) && !empty(array_filter($gallery))): ?>
  <div class="bp-card">
    <h2><i class="ri-image-2-line"></i><?php echo esc_html($T['gallery']); ?></h2>
    <div class="bp-gallery">
      <?php foreach ($gallery as $g): if (empty($g)) continue; ?>
        <a href="<?php echo esc_url($g); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url($g); ?>" alt=""></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ADS / OFFERS -->
  <div class="bp-card">
    <h2><i class="ri-megaphone-line"></i><?php echo esc_html($T['offers']); ?></h2>
    <?php if (empty($ads)): ?>
      <p style="margin:0;color:var(--text-soft);"><?php echo esc_html($T['no_offers']); ?></p>
    <?php else: ?>
      <?php foreach ($ads as $ad):
        $title = (string)($ad->{'title_' . $lang} ?: ($ad->title_de ?: ($ad->title ?? '')));
        $body  = (string)($ad->{'body_' . $lang}  ?: ($ad->body_de  ?: ($ad->body  ?? '')));
      ?>
      <div class="bp-ad">
        <?php if ($ad->image_url): ?><img src="<?php echo esc_url($ad->image_url); ?>" alt=""><?php endif; ?>
        <h3>
          <?php echo esc_html($title); ?>
          <?php if ($ad->followers_only): ?><span class="bp-ad-tag"><i class="ri-vip-crown-line"></i> <?php echo esc_html($T['followers_only']); ?></span><?php endif; ?>
        </h3>
        <?php if ($body): ?><p><?php echo esc_html($body); ?></p><?php endif; ?>
        <?php if ($ad->cta_url): ?>
          <a href="<?php echo esc_url(home_url('/wp-json/punktepass/v1/ad-click/' . (int)$ad->id . '?to=' . urlencode($ad->cta_url))); ?>" class="bp-ad-cta" rel="noopener"><?php echo esc_html($T['details']); ?> <i class="ri-arrow-right-line"></i></a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- REVIEWS -->
  <div class="bp-card">
    <h2><i class="ri-star-line"></i><?php echo esc_html($T['reviews']); ?></h2>

    <?php if ($rev_agg['count'] > 0): ?>
    <div class="bp-rev-summary">
      <div class="bp-rev-avg"><?php echo number_format($rev_agg['avg'], 1); ?></div>
      <div>
        <div class="bp-rev-stars-static">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="<?php echo $i <= round($rev_agg['avg']) ? 'ri-star-fill' : 'ri-star-line empty'; ?>"></i>
          <?php endfor; ?>
        </div>
        <div class="bp-rev-count"><?php echo (int)$rev_agg['count']; ?> <?php echo esc_html($T['reviews']); ?></div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (empty($rev_recent)): ?>
      <p style="margin:0 0 12px;color:var(--text-soft);"><?php echo esc_html($T['no_reviews']); ?></p>
    <?php else: ?>
    <div class="bp-rev-list">
      <?php foreach ($rev_recent as $r): ?>
      <div class="bp-rev-item">
        <div class="head">
          <span class="stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <i class="<?php echo $i <= $r['rating'] ? 'ri-star-fill' : 'ri-star-line empty'; ?>"></i>
            <?php endfor; ?>
          </span>
          <span class="date"><?php echo esc_html($r['date']); ?></span>
        </div>
        <?php if (!empty($r['comment'])): ?><div class="text"><?php echo esc_html($r['comment']); ?></div><?php endif; ?>
        <?php if (!empty($r['reply'])): ?><div class="reply"><strong><?php echo esc_html($adv->business_name); ?>:</strong> <?php echo esc_html($r['reply']); ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Submit form -->
    <div id="bp-rate" class="bp-rate" data-slug="<?php echo esc_attr($adv->slug); ?>">
      <div style="font-weight:600;margin-bottom:8px;"><?php echo esc_html($T['rate_title']); ?></div>
      <div class="bp-rate-stars" id="bp-rate-stars" data-rating="0">
        <?php for ($n = 1; $n <= 5; $n++): ?>
          <i class="ri-star-fill" data-n="<?php echo $n; ?>"></i>
        <?php endfor; ?>
      </div>
      <textarea id="bp-rate-comment" rows="2" placeholder="<?php echo esc_attr($T['rate_comment']); ?>"></textarea>
      <button type="button" id="bp-rate-submit" class="bp-rate-submit"><i class="ri-send-plane-fill"></i><?php echo esc_html($T['rate_submit']); ?></button>
      <div id="bp-rate-msg" class="bp-rate-msg"></div>
    </div>
  </div>

</div>

<!-- Post-follow modal -->
<div id="bp-app-modal" class="bp-modal">
  <div class="bp-modal-card">
    <div class="bp-modal-icon">🎉</div>
    <div class="bp-modal-title"><?php echo esc_html($PM['title']); ?></div>
    <div class="bp-modal-body"><?php echo esc_html($PM['body']); ?></div>
    <a id="bp-app-cta" href="#" class="bp-modal-cta"><i class="ri-download-cloud-2-fill"></i><?php echo esc_html($PM['cta']); ?></a>
    <button type="button" id="bp-app-skip" class="bp-modal-skip"><?php echo esc_html($PM['skip']); ?></button>
  </div>
</div>

<script>
var ppvFirebaseConfig = {
    apiKey: "AIzaSyBB4-sQb-ZlMEDj4LVGYSenB8b8R_mUuOI",
    authDomain: "punktepass.firebaseapp.com",
    projectId: "punktepass",
    storageBucket: "punktepass.firebasestorage.app",
    messagingSenderId: "373165045072",
    appId: "1:373165045072:web:1ef83f576e6fc222a7a855"
};
var ppvVapidKey = 'BCCTa3Fuxw0ZHzNsUf_pkuYsajMCwp69kCSxvV6x9lpYNDkz4MkRM4Kezp8s48qyxXo5GVu8TBcIs3Ih42Vci1Y';
function ppvLoadScript(src){return new Promise(function(r,j){var s=document.createElement('script');s.src=src;s.onload=r;s.onerror=j;document.head.appendChild(s);});}

async function ppvRegisterPushToken(lang) {
  if (!('Notification' in window)) throw new Error('Notification API unavailable');
  if (Notification.permission !== 'granted') throw new Error('Notification permission ' + Notification.permission);
  if (!('serviceWorker' in navigator)) throw new Error('serviceWorker unavailable');
  if (typeof firebase === 'undefined') {
    await ppvLoadScript('https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js');
    await ppvLoadScript('https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js');
  }
  if (!firebase.apps.length) firebase.initializeApp(ppvFirebaseConfig);
  var swReg = await navigator.serviceWorker.register('/firebase-messaging-sw.js', { scope: '/' });
  if (swReg.installing || swReg.waiting) {
    await new Promise(function(resolve){
      var sw = swReg.installing || swReg.waiting;
      if (!sw) return resolve();
      sw.addEventListener('statechange', function(){ if (sw.state === 'activated') resolve(); });
      setTimeout(resolve, 8000);
    });
  }
  try { await navigator.serviceWorker.ready; } catch(e){}
  var token = await firebase.messaging().getToken({ vapidKey: ppvVapidKey, serviceWorkerRegistration: swReg });
  if (!token) throw new Error('getToken returned empty');
  var resp = await fetch('/wp-json/punktepass/v1/push/register', {
    method: 'POST', credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token: token, platform: 'web', language: lang || 'de', device_name: 'Web (PunktePass /business)' })
  });
  var d = await resp.json();
  if (!d || !d.success) throw new Error('register fail: ' + JSON.stringify(d));
  try { localStorage.setItem('ppv_fcm_token', token); } catch(e){}
  return token;
}

function ppvAppStoreUrl(){
  var ua = navigator.userAgent || '';
  if (/iPhone|iPad|iPod/.test(ua)) return 'https://apps.apple.com/app/punktepass/id6755680197';
  if (/Android/.test(ua))         return 'https://play.google.com/store/apps/details?id=de.punktepass.twa';
  return 'https://punktepass.de/';
}
function ppvShowAppModal(){
  var modal = document.getElementById('bp-app-modal');
  if (!modal) return;
  var cta = document.getElementById('bp-app-cta');
  var skip = document.getElementById('bp-app-skip');
  if (cta) cta.href = ppvAppStoreUrl();
  modal.classList.add('open');
  if (skip) skip.onclick = function(){ modal.classList.remove('open'); };
}

(function initFollow() {
  var T = <?php echo wp_json_encode($T); ?>;
  var card = document.getElementById('bp-follow');
  var btn  = document.getElementById('bp-follow-btn');
  var lbl  = document.getElementById('bp-follow-label');
  var msg  = document.getElementById('bp-follow-msg');
  if (!card || !btn) return;
  var slug = card.dataset.slug;
  var following = card.dataset.following === '1';

  function setUi(state, text) {
    if (state === 'loading') { lbl.textContent = T.cta_loading; btn.disabled = true; }
    else if (state === 'following') { lbl.textContent = T.cta_following; btn.disabled = false; following = true; card.classList.add('active'); }
    else if (state === 'idle') { lbl.textContent = T.cta_idle; btn.disabled = false; following = false; card.classList.remove('active'); }
    if (text !== undefined) msg.textContent = text;
  }

  async function followShop() {
    setUi('loading');
    var perm = ('Notification' in window) ? Notification.permission : 'unsupported';
    if (perm === 'default') {
      try { perm = await Notification.requestPermission(); } catch(e) { perm = 'error'; }
    }
    try {
      var r = await fetch('/wp-json/ppv/v1/anon-follow', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ advertiser_slug: slug })
      });
      var j = await r.json();
      if (!j || !j.success) { setUi('idle', (j && j.msg) || 'Error'); return; }
      var note = T.thanks;
      if (perm === 'denied') note = T.push_blocked;
      setUi('following', note);
      if (perm === 'granted') {
        ppvRegisterPushToken(<?php echo wp_json_encode($lang); ?>).catch(function(err){
          if (msg) msg.textContent = 'Push: ' + (err && err.message ? err.message : err);
        });
      }
      setTimeout(function(){ ppvShowAppModal(); }, 900);
    } catch (e) { setUi('idle', String(e)); }
  }

  async function unfollowShop() {
    if (!confirm(T.unfollow)) return;
    setUi('loading');
    try {
      await fetch('/wp-json/ppv/v1/anon-unfollow', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ advertiser_slug: slug }) });
      setUi('idle', '');
    } catch (e) { setUi('following', String(e)); }
  }

  btn.addEventListener('click', function(){ if (following) unfollowShop(); else followShop(); });

  // Auto-retry push if already following + no token
  (async function maybeRetryPush(){
    if (!following) return;
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    var t = null; try { t = localStorage.getItem('ppv_fcm_token'); } catch(e){}
    if (t) return;
    if (msg) { msg.textContent = '…'; }
    try {
      await ppvRegisterPushToken(<?php echo wp_json_encode($lang); ?>);
      if (msg) msg.textContent = T.thanks;
    } catch(err) { if (msg) msg.textContent = 'Push: ' + (err && err.message ? err.message : err); }
  })();
})();

// Rating stars + submit
(function initRating() {
  var T = <?php echo wp_json_encode($T); ?>;
  var box = document.getElementById('bp-rate');
  var stars = document.getElementById('bp-rate-stars');
  var submit = document.getElementById('bp-rate-submit');
  var msg = document.getElementById('bp-rate-msg');
  var ta = document.getElementById('bp-rate-comment');
  if (!box || !stars || !submit) return;
  var slug = box.dataset.slug;

  function setStars(n) {
    stars.dataset.rating = String(n);
    stars.querySelectorAll('i').forEach(function(i){
      i.classList.toggle('active', parseInt(i.dataset.n,10) <= n);
    });
  }
  stars.querySelectorAll('i').forEach(function(i){
    i.addEventListener('click', function(){ setStars(parseInt(i.dataset.n,10)); });
  });

  submit.addEventListener('click', async function(){
    var rating = parseInt(stars.dataset.rating || '0', 10);
    var comment = (ta.value || '').trim();
    if (rating < 1 || rating > 5) { msg.style.color = 'var(--red)'; msg.textContent = T.rate_choose; return; }
    submit.disabled = true; msg.style.color = 'var(--text-soft)'; msg.textContent = '…';
    try {
      var r = await fetch('/wp-json/ppv/v1/advertiser-review', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ slug: slug, rating: rating, comment: comment })
      });
      var j = await r.json();
      if (j && j.success) {
        msg.style.color = 'var(--green)';
        msg.textContent = T.rate_thanks;
        ta.value = ''; setStars(0);
        setTimeout(function(){ window.location.reload(); }, 1200);
      } else {
        msg.style.color = 'var(--red)';
        if (j && j.msg === 'rate_limit') msg.textContent = T.rate_already;
        else if (j && j.msg === 'no actor') msg.textContent = T.rate_login;
        else msg.textContent = (j && j.msg) ? j.msg : 'Error';
      }
    } catch (e) { msg.style.color = 'var(--red)'; msg.textContent = String(e); }
    finally { submit.disabled = false; }
  });
})();

// Android PWA install prompt → custom inline button
let _ppvDeferredPrompt = null;
window.addEventListener('beforeinstallprompt', function(e){
  e.preventDefault(); _ppvDeferredPrompt = e;
});
</script>
</body>
</html>
