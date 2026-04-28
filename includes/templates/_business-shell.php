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
<style>
:root { --pp:#6366f1; --pp2:#8b5cf6; --bg:#f9fafb; --card:#fff; --text:#111827; --muted:#6b7280; --border:#e5e7eb; }
* { box-sizing:border-box; }
body { margin:0; font:14px/1.5 system-ui,-apple-system,sans-serif; background:var(--bg); color:var(--text); }
.bz-header { background:linear-gradient(135deg,var(--pp),var(--pp2)); color:#fff; padding:14px 20px; display:flex; justify-content:space-between; align-items:center; }
.bz-header a { color:#fff; text-decoration:none; }
.bz-brand { font-weight:700; font-size:18px; }
.bz-nav a { margin-left:14px; opacity:.85; }
.bz-nav a:hover { opacity:1; }
.bz-wrap { max-width:1100px; margin:24px auto; padding:0 16px; }
.bz-card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:24px; margin-bottom:16px; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
.bz-grid { display:grid; gap:12px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
.bz-input, .bz-select, .bz-textarea { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font:inherit; background:#fff; }
.bz-textarea { min-height:90px; resize:vertical; }
.bz-label { display:block; font-size:12px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:4px; }
.bz-btn { display:inline-block; padding:10px 18px; background:var(--pp); color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer; text-decoration:none; }
.bz-btn:hover { background:var(--pp2); }
.bz-btn.secondary { background:#fff; color:var(--text); border:1px solid var(--border); }
.bz-btn.danger { background:#dc2626; }
.bz-tag { display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:600; }
.bz-tag.basic { background:#dbeafe; color:#1e40af; }
.bz-tag.plus  { background:#fef3c7; color:#92400e; }
.bz-tag.pro   { background:#dcfce7; color:#166534; }
.bz-msg { padding:10px 14px; border-radius:8px; margin-bottom:14px; font-weight:500; }
.bz-msg.ok { background:#dcfce7; color:#166534; }
.bz-msg.err { background:#fee2e2; color:#991b1b; }
.bz-h1 { margin:0 0 8px; font-size:22px; }
.bz-h2 { margin:0 0 12px; font-size:16px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; }
table.bz-table { width:100%; border-collapse:collapse; }
table.bz-table th, table.bz-table td { padding:10px 8px; border-bottom:1px solid var(--border); text-align:left; font-size:13px; }
</style>
</head>
<body>
<div class="bz-header">
  <div class="bz-brand">📣 PunktePass Business</div>
  <nav class="bz-nav">
    <?php if ($adv): ?>
      <a href="<?php echo esc_url(home_url('/business/admin')); ?>"><?php echo esc_html__('Dashboard', 'punktepass'); ?></a>
      <a href="<?php echo esc_url(home_url('/business/admin/profile')); ?>"><?php echo esc_html__('Profil', 'punktepass'); ?></a>
      <a href="<?php echo esc_url(home_url('/business/admin/ads')); ?>"><?php echo esc_html__('Werbung', 'punktepass'); ?></a>
      <a href="<?php echo esc_url(home_url('/business/admin/push')); ?>"><?php echo esc_html__('Push', 'punktepass'); ?></a>
      <a href="<?php echo esc_url(home_url('/business/admin/stats')); ?>"><?php echo esc_html__('Statistik', 'punktepass'); ?></a>
      <a href="<?php echo esc_url(home_url('/business/logout')); ?>"><?php echo esc_html__('Logout', 'punktepass'); ?></a>
    <?php else: ?>
      <a href="<?php echo esc_url(home_url('/business/login')); ?>"><?php echo esc_html__('Bejelentkezés', 'punktepass'); ?></a>
      <a href="<?php echo esc_url(home_url('/business/register')); ?>"><?php echo esc_html__('Regisztráció', 'punktepass'); ?></a>
    <?php endif; ?>
  </nav>
</div>
<div class="bz-wrap">
<?php require $body_template; ?>
</div>
</body>
</html>
