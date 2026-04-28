<?php
/**
 * Shared shell wrapper for /business/* pages.
 * Renders <html> with head + nav, then includes $body_template once.
 * Caller should set $body_title (string), $body_template (path), and optional $body_data (array).
 */
if (!defined('ABSPATH')) exit;

$lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de';
$adv  = PPV_Advertisers::current_advertiser();
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html($body_title ?? 'PunktePass Business'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
<style>
:root { --pp:#6366f1; --pp2:#8b5cf6; --bg:#f9fafb; --card:#fff; --text:#111827; --muted:#6b7280; --border:#e5e7eb; }
* { box-sizing:border-box; }
body { margin:0; font:14px/1.5 system-ui,-apple-system,sans-serif; background:var(--bg); color:var(--text); padding-bottom:env(safe-area-inset-bottom); }
.bz-header { background:linear-gradient(135deg,var(--pp),var(--pp2)); color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:50; box-shadow:0 2px 8px rgba(0,0,0,.08); }
.bz-header a { color:#fff; text-decoration:none; }
.bz-brand { font-weight:700; font-size:16px; display:flex; align-items:center; gap:8px; }
.bz-brand i { font-size:20px; }
.bz-nav { display:flex; gap:4px; }
.bz-nav a { padding:6px 10px; border-radius:8px; font-size:13px; font-weight:500; opacity:.9; transition:background .15s; }
.bz-nav a:hover, .bz-nav a:focus { background:rgba(255,255,255,.15); opacity:1; }
.bz-nav .logout { opacity:.7; }
.bz-wrap { max-width:1100px; margin:16px auto; padding:0 12px; }
.bz-card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:18px; margin-bottom:12px; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
.bz-grid { display:grid; gap:10px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
.bz-input, .bz-select, .bz-textarea { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; background:#fff; }
.bz-textarea { min-height:90px; resize:vertical; }
.bz-label { display:block; font-size:11px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:4px; }
.bz-btn { display:inline-flex; align-items:center; gap:6px; padding:10px 16px; background:var(--pp); color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer; text-decoration:none; font-size:14px; transition:transform .15s, background .15s; }
.bz-btn:active { transform:scale(.97); }
.bz-btn:hover { background:var(--pp2); }
.bz-btn.secondary { background:#fff; color:var(--text); border:1px solid var(--border); }
.bz-btn.danger { background:#dc2626; }
.bz-btn.full { width:100%; justify-content:center; }
.bz-tag { display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:600; }
.bz-tag.basic { background:#dbeafe; color:#1e40af; }
.bz-tag.plus  { background:#fef3c7; color:#92400e; }
.bz-tag.pro   { background:#dcfce7; color:#166534; }
.bz-msg { padding:10px 14px; border-radius:8px; margin-bottom:14px; font-weight:500; }
.bz-msg.ok { background:#dcfce7; color:#166534; }
.bz-msg.err { background:#fee2e2; color:#991b1b; }
.bz-h1 { margin:0 0 6px; font-size:20px; line-height:1.2; }
.bz-h2 { margin:0 0 8px; font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; font-weight:600; }
table.bz-table { width:100%; border-collapse:collapse; }
table.bz-table th, table.bz-table td { padding:10px 8px; border-bottom:1px solid var(--border); text-align:left; font-size:13px; }

/* Mobile bottom nav */
.bz-bottom-nav { display:none; position:fixed; bottom:0; left:0; right:0; background:#fff; border-top:1px solid var(--border); padding:6px calc(6px + env(safe-area-inset-left)) calc(6px + env(safe-area-inset-bottom)) calc(6px + env(safe-area-inset-right)); z-index:50; box-shadow:0 -4px 16px rgba(0,0,0,.06); }
.bz-bottom-nav .b { flex:1; display:flex; flex-direction:column; align-items:center; gap:2px; padding:6px 4px; color:var(--muted); text-decoration:none; font-size:10px; font-weight:600; border-radius:8px; transition:color .15s, background .15s; }
.bz-bottom-nav .b i { font-size:20px; line-height:1; }
.bz-bottom-nav .b.active { color:var(--pp); background:rgba(99,102,241,.08); }
.bz-bottom-nav .b:active { transform:scale(.94); }
.bz-bottom-nav-inner { display:flex; gap:2px; max-width:600px; margin:0 auto; }

@media (max-width:720px) {
  .bz-header { padding:10px 14px; }
  .bz-brand { font-size:15px; }
  .bz-brand i { font-size:18px; }
  .bz-nav { display:none; }
  .bz-bottom-nav { display:block; }
  .bz-wrap { padding:0 10px 80px; margin-top:10px; }
  .bz-card { padding:14px; border-radius:12px; }
  .bz-h1 { font-size:18px; }
  .bz-grid { grid-template-columns:1fr 1fr; gap:8px; }
  .bz-grid .bz-card { padding:12px; }
}
@media (max-width:380px) {
  .bz-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>
<?php
$current_path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
$nav_items = [
  ['url' => '/business/admin', 'label' => 'Dashboard', 'icon' => 'ri-dashboard-line'],
  ['url' => '/business/admin/profile', 'label' => 'Profil', 'icon' => 'ri-user-settings-line'],
  ['url' => '/business/admin/ads', 'label' => 'Werbung', 'icon' => 'ri-megaphone-line'],
  ['url' => '/business/admin/push', 'label' => 'Push', 'icon' => 'ri-notification-3-line'],
  ['url' => '/business/admin/stats', 'label' => 'Statistik', 'icon' => 'ri-bar-chart-line'],
];
function bz_is_active($url, $current) {
  $u = trim(parse_url($url, PHP_URL_PATH), '/');
  $c = trim($current, '/');
  return $c === $u;
}
?>
<div class="bz-header">
  <div class="bz-brand"><i class="ri-megaphone-fill"></i> Business</div>
  <nav class="bz-nav">
    <?php if ($adv): ?>
      <?php foreach ($nav_items as $item): ?>
        <a href="<?php echo esc_url(home_url($item['url'])); ?>" class="<?php echo bz_is_active($item['url'], $current_path) ? 'active' : ''; ?>"><?php echo esc_html__($item['label'], 'punktepass'); ?></a>
      <?php endforeach; ?>
      <a href="<?php echo esc_url(home_url('/business/logout')); ?>" class="logout"><i class="ri-logout-box-line"></i></a>
    <?php else: ?>
      <a href="<?php echo esc_url(home_url('/business/login')); ?>"><?php echo esc_html__('Bejelentkezés', 'punktepass'); ?></a>
      <a href="<?php echo esc_url(home_url('/business/register')); ?>"><?php echo esc_html__('Regisztráció', 'punktepass'); ?></a>
    <?php endif; ?>
  </nav>
</div>
<div class="bz-wrap">
<?php require $body_template; ?>
</div>
<?php if ($adv): ?>
<nav class="bz-bottom-nav">
  <div class="bz-bottom-nav-inner">
    <?php foreach ($nav_items as $item): ?>
      <a href="<?php echo esc_url(home_url($item['url'])); ?>" class="b <?php echo bz_is_active($item['url'], $current_path) ? 'active' : ''; ?>">
        <i class="<?php echo esc_attr($item['icon']); ?>"></i>
        <span><?php echo esc_html__($item['label'], 'punktepass'); ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>
</body>
</html>
