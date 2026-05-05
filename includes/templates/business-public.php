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

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$user_id = !empty($_SESSION['ppv_user_id']) ? (int)$_SESSION['ppv_user_id'] : 0;
$is_following = $user_id ? PPV_Advertisers::is_following($user_id, $adv->id) : false;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html($adv->business_name); ?></title>
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
    <?php if ($user_id): ?>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
        <?php wp_nonce_field('ppv_advertiser_follow'); ?>
        <input type="hidden" name="action" value="ppv_advertiser_follow">
        <input type="hidden" name="advertiser_id" value="<?php echo (int)$adv->id; ?>">
        <input type="hidden" name="act" value="<?php echo $is_following ? 'unfollow' : 'follow'; ?>">
        <button type="submit" class="bp-btn primary"><?php echo $is_following ? '✓ Követed' : '➕ Követés'; ?></button>
      </form>
    <?php else: ?>
      <a href="<?php echo esc_url(home_url('/login?return=' . urlencode($_SERVER['REQUEST_URI']))); ?>" class="bp-btn primary">➕ Bejelentkezés a követéshez</a>
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
</body>
</html>
