<?php
/**
 * PunktePass - Repair Admin Dashboard (Standalone)
 * No WordPress shortcode, no WordPress theme.
 * Routes handled by PPV_Repair_Core.
 *
 * render_login()      => /formular/admin/login
 * render_standalone() => /formular/admin
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Admin {

    /** ============================================================
     * Render complete standalone login page
     * Route: /formular/admin/login
     * ============================================================ */
    public static function render_login() {
        $ajax_url = admin_url('admin-ajax.php');
        echo '<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Login - Reparatur Admin</title>
<meta name="theme-color" content="#667eea">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f4f5f7;color:#1f2937;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-wrap{width:100%;max-width:400px}
.login-box{background:#fff;border-radius:20px;padding:40px 32px;box-shadow:0 4px 24px rgba(0,0,0,.08);text-align:center}
.login-logo{width:56px;height:56px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:28px;color:#fff}
.login-box h2{font-size:22px;font-weight:700;margin-bottom:4px;color:#111827}
.login-box .subtitle{font-size:14px;color:#6b7280;margin-bottom:28px}
.field{text-align:left;margin-bottom:16px}
.field label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.field input{width:100%;padding:12px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:15px;color:#1f2937;background:#fafafa;transition:border-color .2s,box-shadow .2s;outline:none}
.field input:focus{border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,.15);background:#fff}
.field input::placeholder{color:#9ca3af}
.btn-login{width:100%;padding:14px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:600;cursor:pointer;transition:opacity .2s,transform .15s;margin-top:4px}
.btn-login:hover{opacity:.92}
.btn-login:active{transform:scale(.98)}
.btn-login:disabled{opacity:.6;cursor:not-allowed}
.btn-login .spinner{display:none;margin-right:6px}
.btn-login.loading .label{display:none}
.btn-login.loading .spinner{display:inline-block}
.error-msg{display:none;margin-top:14px;padding:10px 14px;background:#fef2f2;color:#991b1b;border-radius:10px;font-size:13px;text-align:left}
.register-link{margin-top:24px;font-size:14px;color:#6b7280}
.register-link a{color:#667eea;text-decoration:none;font-weight:600}
.register-link a:hover{text-decoration:underline}
.footer-text{text-align:center;margin-top:24px;font-size:12px;color:#9ca3af}
.footer-text a{color:#667eea;text-decoration:none}
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <div class="login-logo"><i class="ri-tools-line"></i></div>
        <h2>Reparatur Admin</h2>
        <p class="subtitle">Melden Sie sich an, um Ihre Reparaturen zu verwalten</p>
        <form id="login-form" autocomplete="off">
            <div class="field">
                <label for="login-email">E-Mail</label>
                <input type="email" id="login-email" required placeholder="info@ihr-shop.de" autocomplete="email">
            </div>
            <div class="field">
                <label for="login-pass">Passwort</label>
                <input type="password" id="login-pass" required placeholder="Passwort" autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login" id="login-btn">
                <i class="ri-loader-4-line ri-spin spinner"></i>
                <span class="label">Anmelden</span>
            </button>
            <div class="error-msg" id="login-error"></div>
        </form>
        <p class="register-link">Noch kein Konto? <a href="/formular">Jetzt registrieren</a></p>
    </div>
    <div class="footer-text">Powered by <a href="https://punktepass.de" target="_blank">PunktePass</a></div>
</div>

<script>
(function(){
    var form=document.getElementById("login-form"),
        btn=document.getElementById("login-btn"),
        err=document.getElementById("login-error"),
        emailInput=document.getElementById("login-email"),
        passInput=document.getElementById("login-pass");

    form.addEventListener("submit",function(e){
        e.preventDefault();
        err.style.display="none";
        btn.classList.add("loading");
        btn.disabled=true;

        var fd=new FormData();
        fd.append("action","ppv_repair_login");
        fd.append("email",emailInput.value.trim());
        fd.append("password",passInput.value);

        fetch("' . esc_url($ajax_url) . '",{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){
                window.location.href=data.data.redirect||"/formular/admin";
            }else{
                err.textContent=data.data&&data.data.message?data.data.message:"Anmeldung fehlgeschlagen.";
                err.style.display="block";
            }
        })
        .catch(function(){
            err.textContent="Verbindungsfehler. Bitte versuchen Sie es erneut.";
            err.style.display="block";
        })
        .finally(function(){
            btn.classList.remove("loading");
            btn.disabled=false;
        });
    });
})();
</script>
</body>
</html>';
    }

    /** ============================================================
     * Render complete standalone admin dashboard
     * Route: /formular/admin
     * ============================================================ */
    public static function render_standalone() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $store_id = (int)($_SESSION['ppv_repair_store_id'] ?? 0);
        if (!$store_id) {
            header('Location: /formular/admin/login');
            exit;
        }

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_stores WHERE id = %d AND repair_enabled = 1 LIMIT 1",
            $store_id
        ));

        if (!$store) {
            header('Location: /formular/admin/login');
            exit;
        }

        // Stats
        $total_repairs = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_repairs WHERE store_id = %d", $store->id
        ));
        $open_repairs = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_repairs WHERE store_id = %d AND status IN ('new','in_progress','waiting_parts')", $store->id
        ));
        $today_repairs = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_repairs WHERE store_id = %d AND DATE(created_at) = %s", $store->id, current_time('Y-m-d')
        ));
        $total_customers = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT customer_email) FROM {$prefix}ppv_repairs WHERE store_id = %d", $store->id
        ));

        // Recent repairs
        $recent = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_repairs WHERE store_id = %d ORDER BY created_at DESC LIMIT 20", $store->id
        ));

        $form_url   = home_url("/formular/{$store->slug}");
        $ajax_url   = admin_url('admin-ajax.php');
        $nonce      = wp_create_nonce('ppv_repair_admin');
        $is_premium = !empty($store->repair_premium);
        $limit_pct  = ($store->repair_form_limit > 0) ? min(100, round(($store->repair_form_count / $store->repair_form_limit) * 100)) : 0;

        $store_name  = esc_html($store->name);
        $store_logo  = esc_url($store->logo ?: '');
        $store_slug  = esc_attr($store->slug);
        $store_color = esc_attr($store->repair_color ?: '#667eea');

        // Build repairs HTML
        $repairs_html = '';
        if (empty($recent)) {
            $repairs_html = '<div class="ra-empty"><i class="ri-inbox-line"></i><p>Noch keine Reparaturen. Teilen Sie Ihren Formular-Link!</p></div>';
        } else {
            foreach ($recent as $r) {
                $repairs_html .= self::build_repair_card_html($r);
            }
        }

        echo '<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>' . $store_name . ' - Reparatur Admin</title>
<meta name="theme-color" content="' . $store_color . '">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
<style>
/* ========== Reset & Base ========== */
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f4f5f7;color:#1f2937;line-height:1.5;-webkit-font-smoothing:antialiased}
a{color:#667eea;text-decoration:none}
a:hover{text-decoration:underline}

/* ========== Layout ========== */
.ra-wrap{max-width:800px;margin:0 auto;padding:16px 16px 40px}

/* ========== Header ========== */
.ra-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 0;flex-wrap:wrap}
.ra-header-left{display:flex;align-items:center;gap:12px;min-width:0}
.ra-logo{width:44px;height:44px;border-radius:10px;object-fit:cover;flex-shrink:0}
.ra-title{font-size:20px;font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ra-subtitle{font-size:13px;color:#6b7280;margin-top:1px}
.ra-header-right{display:flex;align-items:center;gap:8px;flex-shrink:0}

/* ========== Buttons ========== */
.ra-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all .2s;text-decoration:none !important;white-space:nowrap}
.ra-btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
.ra-btn-primary:hover{opacity:.9}
.ra-btn-outline{background:#fff;color:#667eea;border:1.5px solid #e5e7eb}
.ra-btn-outline:hover{border-color:#667eea;background:#f5f3ff}
.ra-btn-icon{background:#fff;color:#374151;border:1.5px solid #e5e7eb;padding:10px;border-radius:10px;cursor:pointer;font-size:18px;display:inline-flex;align-items:center;justify-content:center;transition:all .2s}
.ra-btn-icon:hover{border-color:#667eea;color:#667eea}
.ra-btn-sm{padding:8px 12px;font-size:13px}
.ra-btn-full{width:100%}

/* ========== Stats ========== */
.ra-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
.ra-stat-card{background:#fff;border-radius:14px;padding:16px;text-align:center;border:1px solid #f0f0f0}
.ra-stat-value{font-size:28px;font-weight:800;color:#111827}
.ra-stat-label{font-size:12px;color:#6b7280;font-weight:500;margin-top:2px}
.ra-stat-highlight{background:linear-gradient(135deg,#667eea,#764ba2);border:none}
.ra-stat-highlight .ra-stat-value{color:#fff}
.ra-stat-highlight .ra-stat-label{color:rgba(255,255,255,.8)}

/* ========== Usage Bar ========== */
.ra-usage{background:#fff;border-radius:14px;padding:16px 18px;margin-bottom:16px;border:1px solid #f0f0f0}
.ra-usage-info{display:flex;justify-content:space-between;align-items:center;font-size:13px;font-weight:500;color:#374151;margin-bottom:8px}
.ra-usage-warn{color:#dc2626;font-weight:600}
.ra-usage-bar{height:8px;background:#e5e7eb;border-radius:99px;overflow:hidden}
.ra-usage-fill{height:100%;background:linear-gradient(90deg,#667eea,#764ba2);border-radius:99px;transition:width .6s}
.ra-premium-badge{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#fef3c7,#fde68a);color:#92400e;font-size:14px;font-weight:600;padding:10px 16px;border-radius:10px;margin-bottom:16px}

/* ========== Link Card ========== */
.ra-link-card{background:#fff;border-radius:14px;padding:18px;margin-bottom:16px;border:1px solid #f0f0f0}
.ra-link-label{font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
.ra-link-row{display:flex;gap:8px}
.ra-link-input{flex:1;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;color:#374151;background:#fafafa;outline:none;min-width:0}
.ra-link-input:focus{border-color:#667eea}
.ra-btn-copy{background:#f0f0f0;color:#374151;border:none;padding:10px 14px;border-radius:10px;cursor:pointer;font-size:16px;transition:all .2s;display:inline-flex;align-items:center}
.ra-btn-copy:hover{background:#667eea;color:#fff}

/* ========== Settings Panel ========== */
.ra-hidden{display:none !important}
.ra-settings{background:#fff;border-radius:14px;padding:24px;margin-bottom:16px;border:1px solid #f0f0f0}
.ra-settings h3{font-size:17px;font-weight:700;color:#111827;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.ra-settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.ra-settings .field{margin-bottom:0}
.ra-settings .field label{display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:5px}
.ra-settings .field input[type="text"],
.ra-settings .field input[type="number"],
.ra-settings .field input[type="color"]{width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;color:#1f2937;background:#fafafa;outline:none;transition:border-color .2s}
.ra-settings .field input:focus{border-color:#667eea;background:#fff}
.ra-settings .field input[type="color"]{height:44px;padding:4px 6px;cursor:pointer}
.ra-logo-section{margin-top:18px;margin-bottom:18px}
.ra-logo-section>label{display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:8px}
.ra-logo-upload{display:flex;align-items:center;gap:14px}
.ra-logo-preview{width:56px;height:56px;border-radius:12px;object-fit:cover;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:24px;color:#9ca3af;flex-shrink:0;overflow:hidden}
.ra-logo-preview img{width:100%;height:100%;object-fit:cover}
.ra-settings-save{margin-top:20px}
.ra-legal-links{margin-top:24px;padding-top:20px;border-top:1px solid #f0f0f0}
.ra-legal-links h4{font-size:13px;font-weight:600;color:#6b7280;margin-bottom:10px}
.ra-legal-links a{display:inline-flex;align-items:center;gap:5px;margin-right:16px;margin-bottom:8px;font-size:14px;color:#667eea}
.ra-pp-link{margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0}

/* ========== Toolbar ========== */
.ra-toolbar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap}
.ra-search{flex:1;position:relative;min-width:200px}
.ra-search i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:18px}
.ra-search input{width:100%;padding:11px 14px 11px 42px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;color:#1f2937;background:#fff;outline:none;transition:border-color .2s}
.ra-search input:focus{border-color:#667eea}
.ra-search input::placeholder{color:#9ca3af}
.ra-filters select{padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;color:#374151;background:#fff;outline:none;cursor:pointer;appearance:auto;min-width:140px}
.ra-filters select:focus{border-color:#667eea}

/* ========== Repair Cards ========== */
.ra-repairs{display:flex;flex-direction:column;gap:10px}
.ra-repair-card{background:#fff;border-radius:14px;padding:18px;border:1px solid #f0f0f0;transition:box-shadow .2s}
.ra-repair-card:hover{box-shadow:0 2px 12px rgba(0,0,0,.06)}
.ra-repair-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.ra-repair-id{font-size:13px;font-weight:700;color:#6b7280}
.ra-status{display:inline-flex;align-items:center;padding:4px 10px;border-radius:8px;font-size:12px;font-weight:600}
.ra-status-new{background:#dbeafe;color:#1d4ed8}
.ra-status-progress{background:#fef3c7;color:#92400e}
.ra-status-waiting{background:#fce7f3;color:#9d174d}
.ra-status-done{background:#d1fae5;color:#065f46}
.ra-status-delivered{background:#e5e7eb;color:#374151}
.ra-status-cancelled{background:#fecaca;color:#991b1b}
.ra-repair-body{margin-bottom:12px}
.ra-repair-customer{margin-bottom:6px}
.ra-repair-customer strong{font-size:15px;color:#111827;display:block}
.ra-repair-meta{font-size:13px;color:#6b7280}
.ra-repair-device{font-size:13px;color:#374151;margin-bottom:4px;display:flex;align-items:center;gap:4px}
.ra-repair-problem{font-size:13px;color:#6b7280;margin-bottom:6px;line-height:1.5}
.ra-repair-date{font-size:12px;color:#9ca3af;display:flex;align-items:center;gap:4px}
.ra-repair-actions{display:flex;align-items:center;gap:8px}
.ra-status-select{padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;color:#374151;background:#fff;outline:none;cursor:pointer;appearance:auto;width:100%;max-width:200px}
.ra-status-select:focus{border-color:#667eea}

/* ========== Empty & Load More ========== */
.ra-empty{text-align:center;padding:48px 20px;color:#9ca3af}
.ra-empty i{font-size:48px;display:block;margin-bottom:12px}
.ra-empty p{font-size:15px}
.ra-load-more{text-align:center;margin-top:20px}

/* ========== Notifications ========== */
.ra-toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#111827;color:#fff;padding:12px 24px;border-radius:12px;font-size:14px;font-weight:500;z-index:9999;opacity:0;transition:opacity .3s;pointer-events:none}
.ra-toast.show{opacity:1}

/* ========== Logout link ========== */
.ra-logout{font-size:13px;color:#6b7280;margin-top:8px;display:inline-flex;align-items:center;gap:4px;cursor:pointer;border:none;background:none;padding:0}
.ra-logout:hover{color:#dc2626}

/* ========== Responsive ========== */
@media(max-width:640px){
    .ra-stats{grid-template-columns:repeat(2,1fr)}
    .ra-header{flex-direction:column;align-items:flex-start}
    .ra-header-right{width:100%;justify-content:flex-start}
    .ra-settings-grid{grid-template-columns:1fr}
    .ra-toolbar{flex-direction:column}
    .ra-filters select{width:100%}
    .ra-title{font-size:18px}
}
</style>
</head>
<body>
<div class="ra-wrap" data-store-id="' . intval($store->id) . '">

    <!-- Header -->
    <div class="ra-header">
        <div class="ra-header-left">';

        if ($store_logo) {
            echo '<img src="' . $store_logo . '" alt="" class="ra-logo">';
        }

        echo '<div>
                <div class="ra-title">' . $store_name . '</div>
                <div class="ra-subtitle">Reparatur Admin</div>
            </div>
        </div>
        <div class="ra-header-right">
            <a href="' . esc_url($form_url) . '" target="_blank" class="ra-btn ra-btn-outline">
                <i class="ri-external-link-line"></i> Formular ansehen
            </a>
            <button class="ra-btn-icon" onclick="document.getElementById(\'ra-settings\').classList.toggle(\'ra-hidden\')" title="Einstellungen">
                <i class="ri-settings-3-line"></i>
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="ra-stats">
        <div class="ra-stat-card">
            <div class="ra-stat-value">' . $total_repairs . '</div>
            <div class="ra-stat-label">Gesamt</div>
        </div>
        <div class="ra-stat-card ra-stat-highlight">
            <div class="ra-stat-value">' . $open_repairs . '</div>
            <div class="ra-stat-label">Offen</div>
        </div>
        <div class="ra-stat-card">
            <div class="ra-stat-value">' . $today_repairs . '</div>
            <div class="ra-stat-label">Heute</div>
        </div>
        <div class="ra-stat-card">
            <div class="ra-stat-value">' . $total_customers . '</div>
            <div class="ra-stat-label">Kunden</div>
        </div>
    </div>';

        // Usage bar or premium badge
        if (!$is_premium) {
            echo '<div class="ra-usage">
        <div class="ra-usage-info">
            <span>Formulare: ' . intval($store->repair_form_count) . ' / ' . intval($store->repair_form_limit) . '</span>';
            if ($limit_pct >= 80) {
                echo '<span class="ra-usage-warn">Limit fast erreicht!</span>';
            }
            echo '</div>
        <div class="ra-usage-bar">
            <div class="ra-usage-fill" style="width:' . $limit_pct . '%"></div>
        </div>
    </div>';
        } else {
            echo '<div class="ra-premium-badge"><i class="ri-vip-crown-line"></i> Premium aktiv</div>';
        }

        // Formular-Link card
        echo '<div class="ra-link-card">
        <div class="ra-link-label">Ihr Formular-Link</div>
        <div class="ra-link-row">
            <input type="text" value="' . esc_attr($form_url) . '" readonly class="ra-link-input" id="ra-form-url">
            <button class="ra-btn-copy" id="ra-copy-btn" title="Link kopieren">
                <i class="ri-file-copy-line"></i>
            </button>
        </div>
    </div>';

        // Settings panel
        echo '<div id="ra-settings" class="ra-settings ra-hidden">
        <h3><i class="ri-settings-3-line"></i> Einstellungen</h3>
        <form id="ra-settings-form">
            <div class="ra-settings-grid">
                <div class="field">
                    <label>Firmenname</label>
                    <input type="text" name="name" value="' . esc_attr($store->name) . '">
                </div>
                <div class="field">
                    <label>Bonuspunkte pro Formular</label>
                    <input type="number" name="repair_points_per_form" value="' . intval($store->repair_points_per_form) . '" min="0" max="10">
                </div>
                <div class="field">
                    <label>Adresse</label>
                    <input type="text" name="address" value="' . esc_attr($store->address) . '">
                </div>
                <div class="field">
                    <label>PLZ</label>
                    <input type="text" name="plz" value="' . esc_attr($store->plz) . '">
                </div>
                <div class="field">
                    <label>Stadt</label>
                    <input type="text" name="city" value="' . esc_attr($store->city) . '">
                </div>
                <div class="field">
                    <label>Telefon</label>
                    <input type="text" name="phone" value="' . esc_attr($store->phone) . '">
                </div>
                <div class="field">
                    <label>Firmenname (Impressum)</label>
                    <input type="text" name="repair_company_name" value="' . esc_attr($store->repair_company_name) . '">
                </div>
                <div class="field">
                    <label>Inhaber (Impressum)</label>
                    <input type="text" name="repair_owner_name" value="' . esc_attr($store->repair_owner_name) . '">
                </div>
                <div class="field">
                    <label>USt-IdNr.</label>
                    <input type="text" name="repair_tax_id" value="' . esc_attr($store->repair_tax_id) . '">
                </div>
                <div class="field">
                    <label>Akzentfarbe</label>
                    <input type="color" name="repair_color" value="' . $store_color . '">
                </div>
            </div>

            <!-- Logo upload -->
            <div class="ra-logo-section">
                <label>Logo</label>
                <div class="ra-logo-upload">
                    <div class="ra-logo-preview" id="ra-logo-preview">';

        if ($store_logo) {
            echo '<img src="' . $store_logo . '" alt="">';
        } else {
            echo '<i class="ri-image-add-line"></i>';
        }

        echo '</div>
                    <input type="file" id="ra-logo-file" accept="image/*" style="display:none">
                    <button type="button" class="ra-btn ra-btn-outline ra-btn-sm" onclick="document.getElementById(\'ra-logo-file\').click()">
                        <i class="ri-upload-2-line"></i> Logo hochladen
                    </button>
                </div>
            </div>

            <div class="ra-settings-save">
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="ri-save-line"></i> Speichern
                </button>
            </div>
        </form>

        <!-- Legal pages -->
        <div class="ra-legal-links">
            <h4>Automatisch generierte Seiten</h4>
            <a href="/formular/' . $store_slug . '/datenschutz" target="_blank"><i class="ri-shield-check-line"></i> Datenschutz</a>
            <a href="/formular/' . $store_slug . '/agb" target="_blank"><i class="ri-file-text-line"></i> AGB</a>
            <a href="/formular/' . $store_slug . '/impressum" target="_blank"><i class="ri-building-line"></i> Impressum</a>
        </div>

        <!-- PunktePass admin -->
        <div class="ra-pp-link">
            <a href="/qr-center" class="ra-btn ra-btn-outline">
                <i class="ri-qr-code-line"></i> PunktePass Admin &ouml;ffnen
            </a>
        </div>
    </div>';

        // Search & Filters
        echo '<div class="ra-toolbar">
        <div class="ra-search">
            <i class="ri-search-line"></i>
            <input type="text" id="ra-search-input" placeholder="Name, E-Mail, Telefon oder Ger&auml;t suchen...">
        </div>
        <div class="ra-filters">
            <select id="ra-filter-status">
                <option value="">Alle Status</option>
                <option value="new">Neu</option>
                <option value="in_progress">In Bearbeitung</option>
                <option value="waiting_parts">Wartet auf Teile</option>
                <option value="done">Fertig</option>
                <option value="delivered">Abgeholt</option>
                <option value="cancelled">Storniert</option>
            </select>
        </div>
    </div>';

        // Repairs list
        echo '<div class="ra-repairs" id="ra-repairs-list">' . $repairs_html . '</div>';

        // Load more
        if ($total_repairs > 20) {
            echo '<div class="ra-load-more"><button class="ra-btn ra-btn-outline" id="ra-load-more" data-page="1">Mehr laden</button></div>';
        }

        // Toast notification
        echo '<div class="ra-toast" id="ra-toast"></div>';

        // Footer
        echo '<div style="text-align:center;margin-top:32px;padding-top:20px;border-top:1px solid #e5e7eb;">
        <div style="font-size:12px;color:#9ca3af;margin-bottom:8px;">Powered by <a href="https://punktepass.de" target="_blank" style="color:#667eea;">PunktePass</a></div>
        <button class="ra-logout" onclick="if(confirm(\'Wirklich abmelden?\')){var fd=new FormData();fd.append(\'action\',\'ppv_repair_logout\');fetch(\'' . esc_url($ajax_url) . '\',{method:\'POST\',body:fd,credentials:\'same-origin\'}).finally(function(){window.location.href=\'/formular/admin/login\';})}">
            <i class="ri-logout-box-r-line"></i> Abmelden
        </button>
    </div>

</div>

<script>
(function(){
    var AJAX="' . esc_url($ajax_url) . '",
        NONCE="' . esc_attr($nonce) . '",
        STORE=' . intval($store->id) . ',
        searchTimer=null,
        currentPage=1;

    /* ===== Toast ===== */
    function toast(msg){
        var t=document.getElementById("ra-toast");
        t.textContent=msg;
        t.classList.add("show");
        setTimeout(function(){t.classList.remove("show")},2500);
    }

    /* ===== Copy Link ===== */
    document.getElementById("ra-copy-btn").addEventListener("click",function(){
        var input=document.getElementById("ra-form-url");
        navigator.clipboard.writeText(input.value).then(function(){
            var btn=document.getElementById("ra-copy-btn");
            btn.innerHTML=\'<i class="ri-check-line"></i>\';
            toast("Link kopiert!");
            setTimeout(function(){btn.innerHTML=\'<i class="ri-file-copy-line"></i>\'},2000);
        });
    });

    /* ===== Status Change ===== */
    function bindStatusSelects(container){
        container.querySelectorAll(".ra-status-select").forEach(function(sel){
            sel.addEventListener("change",function(){
                var rid=this.getAttribute("data-repair-id"),
                    st=this.value,
                    fd=new FormData();
                fd.append("action","ppv_repair_update_status");
                fd.append("nonce",NONCE);
                fd.append("repair_id",rid);
                fd.append("status",st);
                fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
                .then(function(r){return r.json()})
                .then(function(data){
                    if(data.success){
                        toast("Status aktualisiert");
                        updateBadge(sel.closest(".ra-repair-card"),st);
                    }else{
                        toast(data.data&&data.data.message?data.data.message:"Fehler");
                    }
                })
                .catch(function(){toast("Verbindungsfehler")});
            });
        });
    }

    function updateBadge(card,status){
        var map={
            new:["Neu","ra-status-new"],
            in_progress:["In Bearbeitung","ra-status-progress"],
            waiting_parts:["Wartet auf Teile","ra-status-waiting"],
            done:["Fertig","ra-status-done"],
            delivered:["Abgeholt","ra-status-delivered"],
            cancelled:["Storniert","ra-status-cancelled"]
        };
        var badge=card.querySelector(".ra-status");
        if(badge&&map[status]){
            badge.className="ra-status "+map[status][1];
            badge.textContent=map[status][0];
        }
    }

    bindStatusSelects(document);

    /* ===== Search & Filter ===== */
    var searchInput=document.getElementById("ra-search-input"),
        filterSelect=document.getElementById("ra-filter-status");

    function doSearch(page){
        page=page||1;
        var fd=new FormData();
        fd.append("action","ppv_repair_search");
        fd.append("nonce",NONCE);
        fd.append("search",searchInput.value.trim());
        fd.append("filter_status",filterSelect.value);
        fd.append("page",page);
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(!data.success)return;
            var list=document.getElementById("ra-repairs-list"),
                d=data.data,
                repairs=d.repairs||[];
            if(page===1)list.innerHTML="";
            if(repairs.length===0&&page===1){
                list.innerHTML=\'<div class="ra-empty"><i class="ri-search-line"></i><p>Keine Reparaturen gefunden.</p></div>\';
            }else{
                repairs.forEach(function(r){
                    list.insertAdjacentHTML("beforeend",buildCardHTML(r));
                });
                bindStatusSelects(list);
            }
            // Update load more
            var lm=document.getElementById("ra-load-more");
            if(lm){
                if(d.page<d.pages){
                    lm.style.display="inline-flex";
                    lm.setAttribute("data-page",d.page);
                }else{
                    lm.style.display="none";
                }
            }
            currentPage=d.page;
        });
    }

    searchInput.addEventListener("input",function(){
        clearTimeout(searchTimer);
        searchTimer=setTimeout(function(){doSearch(1)},350);
    });
    filterSelect.addEventListener("change",function(){doSearch(1)});

    /* ===== Load More ===== */
    var loadMoreBtn=document.getElementById("ra-load-more");
    if(loadMoreBtn){
        loadMoreBtn.addEventListener("click",function(){
            var next=parseInt(this.getAttribute("data-page"))+1;
            doSearch(next);
        });
    }

    /* ===== Build Card HTML (client side) ===== */
    function buildCardHTML(r){
        var map={
            new:["Neu","ra-status-new"],
            in_progress:["In Bearbeitung","ra-status-progress"],
            waiting_parts:["Wartet auf Teile","ra-status-waiting"],
            done:["Fertig","ra-status-done"],
            delivered:["Abgeholt","ra-status-delivered"],
            cancelled:["Storniert","ra-status-cancelled"]
        };
        var st=map[r.status]||["Unbekannt",""];
        var device=((r.device_brand||"")+" "+(r.device_model||"")).trim();
        var d=r.created_at?new Date(r.created_at.replace(/-/g,"/")):"";
        var dateStr=d?pad(d.getDate())+"."+pad(d.getMonth()+1)+"."+d.getFullYear()+" "+pad(d.getHours())+":"+pad(d.getMinutes()):"";
        var problem=(r.problem_description||"");
        if(problem.length>150)problem=problem.substring(0,150)+"...";
        var opts=["new","in_progress","waiting_parts","done","delivered","cancelled"];
        var labels=["Neu","In Bearbeitung","Wartet auf Teile","Fertig","Abgeholt","Storniert"];
        var selectHtml="";
        for(var i=0;i<opts.length;i++){
            selectHtml+=\'<option value="\'+opts[i]+\'" \'+(r.status===opts[i]?"selected":"")+\'>\'+labels[i]+\'</option>\';
        }
        var phone=r.customer_phone?(" &middot; "+esc(r.customer_phone)):"";
        var deviceHtml=device?\'<div class="ra-repair-device"><i class="ri-smartphone-line"></i> \'+esc(device)+\'</div>\':"";
        return \'<div class="ra-repair-card" data-id="\'+r.id+\'">\'+
            \'<div class="ra-repair-header"><div class="ra-repair-id">#\'+r.id+\'</div><span class="ra-status \'+st[1]+\'">\'+st[0]+\'</span></div>\'+
            \'<div class="ra-repair-body">\'+
                \'<div class="ra-repair-customer"><strong>\'+esc(r.customer_name)+\'</strong><span class="ra-repair-meta">\'+esc(r.customer_email)+phone+\'</span></div>\'+
                deviceHtml+
                \'<div class="ra-repair-problem">\'+esc(problem)+\'</div>\'+
                \'<div class="ra-repair-date"><i class="ri-time-line"></i> \'+dateStr+\'</div>\'+
            \'</div>\'+
            \'<div class="ra-repair-actions"><select class="ra-status-select" data-repair-id="\'+r.id+\'">\'+selectHtml+\'</select></div>\'+
        \'</div>\';
    }
    function pad(n){return n<10?"0"+n:n}
    function esc(s){if(!s)return"";var d=document.createElement("div");d.textContent=s;return d.innerHTML}

    /* ===== Settings Form ===== */
    document.getElementById("ra-settings-form").addEventListener("submit",function(e){
        e.preventDefault();
        var fd=new FormData(this);
        fd.append("action","ppv_repair_save_settings");
        fd.append("nonce",NONCE);
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            toast(data.success?"Einstellungen gespeichert":"Fehler beim Speichern");
        })
        .catch(function(){toast("Verbindungsfehler")});
    });

    /* ===== Logo Upload ===== */
    document.getElementById("ra-logo-file").addEventListener("change",function(){
        if(!this.files||!this.files[0])return;
        var fd=new FormData();
        fd.append("action","ppv_repair_upload_logo");
        fd.append("nonce",NONCE);
        fd.append("logo",this.files[0]);
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success&&data.data.url){
                var preview=document.getElementById("ra-logo-preview");
                preview.innerHTML=\'<img src="\'+data.data.url+\'" alt="">\';
                toast("Logo hochgeladen");
            }else{
                toast(data.data&&data.data.message?data.data.message:"Upload fehlgeschlagen");
            }
        })
        .catch(function(){toast("Upload fehlgeschlagen")});
    });

})();
</script>
</body>
</html>';
    }

    /** ============================================================
     * Build HTML for a single repair card (server-side)
     * ============================================================ */
    private static function build_repair_card_html($r) {
        $status_map = [
            'new'           => ['Neu',              'ra-status-new'],
            'in_progress'   => ['In Bearbeitung',   'ra-status-progress'],
            'waiting_parts' => ['Wartet auf Teile', 'ra-status-waiting'],
            'done'          => ['Fertig',            'ra-status-done'],
            'delivered'     => ['Abgeholt',          'ra-status-delivered'],
            'cancelled'     => ['Storniert',         'ra-status-cancelled'],
        ];

        $st    = $status_map[$r->status] ?? ['Unbekannt', ''];
        $device = trim(($r->device_brand ?: '') . ' ' . ($r->device_model ?: ''));
        $date   = date('d.m.Y H:i', strtotime($r->created_at));
        $problem = esc_html(mb_strimwidth($r->problem_description, 0, 150, '...'));
        $phone  = $r->customer_phone ? (' &middot; ' . esc_html($r->customer_phone)) : '';

        $device_html = '';
        if ($device) {
            $device_html = '<div class="ra-repair-device"><i class="ri-smartphone-line"></i> ' . esc_html($device) . '</div>';
        }

        // Status options
        $options = '';
        foreach ($status_map as $key => $label) {
            $sel = ($r->status === $key) ? ' selected' : '';
            $options .= '<option value="' . esc_attr($key) . '"' . $sel . '>' . esc_html($label[0]) . '</option>';
        }

        return '<div class="ra-repair-card" data-id="' . intval($r->id) . '">'
            . '<div class="ra-repair-header">'
                . '<div class="ra-repair-id">#' . intval($r->id) . '</div>'
                . '<span class="ra-status ' . esc_attr($st[1]) . '">' . esc_html($st[0]) . '</span>'
            . '</div>'
            . '<div class="ra-repair-body">'
                . '<div class="ra-repair-customer">'
                    . '<strong>' . esc_html($r->customer_name) . '</strong>'
                    . '<span class="ra-repair-meta">' . esc_html($r->customer_email) . $phone . '</span>'
                . '</div>'
                . $device_html
                . '<div class="ra-repair-problem">' . $problem . '</div>'
                . '<div class="ra-repair-date"><i class="ri-time-line"></i> ' . $date . '</div>'
            . '</div>'
            . '<div class="ra-repair-actions">'
                . '<select class="ra-status-select" data-repair-id="' . intval($r->id) . '">' . $options . '</select>'
            . '</div>'
        . '</div>';
    }
}
