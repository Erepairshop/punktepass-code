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
        // Load repair-specific translations
        PPV_Lang::load_extra('ppv-repair-lang');
        $lang = PPV_Lang::current();
        $ajax_url = admin_url('admin-ajax.php');

        $js_strings = json_encode([
            'login_failed'     => PPV_Lang::t('repair_login_failed'),
            'connection_error' => PPV_Lang::t('repair_connection_error'),
        ], JSON_UNESCAPED_UNICODE);

        ?><!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?php echo esc_html(PPV_Lang::t('repair_login_title')); ?></title>
<meta name="theme-color" content="#667eea">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
<link rel="preconnect" href="https://accounts.google.com" crossorigin>
<script src="https://accounts.google.com/gsi/client" async defer></script>
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
.login-lang-wrap{position:absolute;top:16px;right:16px;z-index:10}
.login-lang-toggle{display:flex;align-items:center;gap:4px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:5px 10px;font-size:12px;font-weight:700;color:#6b7280;cursor:pointer;font-family:inherit;transition:all .2s}
.login-lang-toggle:hover{background:#e5e7eb;color:#374151}
.login-lang-toggle i{font-size:14px}
.login-lang-opts{display:none;position:absolute;top:100%;right:0;margin-top:4px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.1);overflow:hidden;min-width:52px}
.login-lang-wrap.open .login-lang-opts{display:block}
.login-lang-opt{display:block;padding:6px 12px;font-size:12px;font-weight:700;color:#6b7280;text-decoration:none;cursor:pointer;border:none;background:none;width:100%;text-align:center;font-family:inherit;letter-spacing:.5px;transition:all .15s}
.login-lang-opt:hover{background:#f0f2ff;color:#4338ca}
.login-lang-opt.active{color:#667eea}
.oauth-btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px 14px;font-size:14px;font-weight:600;border-radius:10px;cursor:pointer;transition:all .2s;font-family:inherit;margin-bottom:8px}
.oauth-google{background:#fff;border:1.5px solid #e5e7eb;color:#374151}
.oauth-google:hover{border-color:#d1d5db;background:#f9fafb}
.oauth-apple{background:#000;border:1.5px solid #000;color:#fff}
.oauth-apple:hover{background:#1a1a1a}
.oauth-divider{display:flex;align-items:center;margin:16px 0;gap:10px}
.oauth-divider::before,.oauth-divider::after{content:'';flex:1;height:1px;background:#e5e7eb}
.oauth-divider span{font-size:12px;font-weight:500;color:#9ca3af;white-space:nowrap}
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-box">
        <!-- Language Switcher -->
        <div class="login-lang-wrap" id="login-lang-wrap">
            <button class="login-lang-toggle" onclick="this.parentElement.classList.toggle('open')">
                <i class="ri-global-line"></i> <?php echo strtoupper($lang); ?>
            </button>
            <div class="login-lang-opts">
                <?php foreach (['de','en','hu','ro'] as $lc): ?>
                <button class="login-lang-opt <?php echo $lang === $lc ? 'active' : ''; ?>" data-lang="<?php echo $lc; ?>"><?php echo strtoupper($lc); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="login-logo"><i class="ri-tools-line"></i></div>
        <h2><?php echo esc_html(PPV_Lang::t('repair_login_heading')); ?></h2>
        <p class="subtitle"><?php echo esc_html(PPV_Lang::t('repair_login_subtitle')); ?></p>
        <!-- OAuth Login -->
        <button type="button" class="oauth-btn oauth-google" onclick="ppvOAuthLogin('google')">
            <svg width="16" height="16" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z"/><path fill="#FBBC05" d="M3.964 10.71c-.18-.54-.282-1.117-.282-1.71s.102-1.17.282-1.71V4.958H.957C.347 6.173 0 7.548 0 9s.348 2.827.957 4.042l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/></svg>
            <?php echo esc_html(PPV_Lang::t('repair_login_google')); ?>
        </button>
        <button type="button" class="oauth-btn oauth-apple" onclick="ppvOAuthLogin('apple')">
            <svg width="16" height="16" viewBox="0 0 18 18"><path fill="currentColor" d="M14.94 9.88c-.02-2.07 1.69-3.06 1.77-3.11-.96-1.41-2.46-1.6-3-1.63-1.27-.13-2.49.75-3.14.75-.65 0-1.65-.73-2.72-.71-1.4.02-2.69.81-3.41 2.07-1.46 2.53-.37 6.27 1.05 8.32.69 1 1.52 2.13 2.61 2.09 1.05-.04 1.44-.68 2.71-.68 1.27 0 1.62.68 2.72.66 1.13-.02 1.84-1.02 2.53-2.03.8-1.16 1.12-2.28 1.14-2.34-.02-.01-2.19-.84-2.21-3.34zM12.87 3.53c.58-.7.97-1.67.86-2.64-.83.03-1.84.55-2.44 1.25-.53.62-1 1.61-.87 2.56.93.07 1.87-.47 2.45-1.17z"/></svg>
            <?php echo esc_html(PPV_Lang::t('repair_login_apple')); ?>
        </button>
        <div class="oauth-divider"><span><?php echo esc_html(PPV_Lang::t('repair_login_or')); ?></span></div>
        <form id="login-form" autocomplete="off">
            <div class="field">
                <label for="login-email"><?php echo esc_html(PPV_Lang::t('repair_email_label')); ?></label>
                <input type="email" id="login-email" required placeholder="<?php echo esc_attr(PPV_Lang::t('repair_reg_email_placeholder')); ?>" autocomplete="email">
            </div>
            <div class="field">
                <label for="login-pass"><?php echo esc_html(PPV_Lang::t('repair_login_password')); ?></label>
                <input type="password" id="login-pass" required placeholder="<?php echo esc_attr(PPV_Lang::t('repair_login_password_placeholder')); ?>" autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login" id="login-btn">
                <i class="ri-loader-4-line ri-spin spinner"></i>
                <span class="label"><?php echo esc_html(PPV_Lang::t('repair_login_submit')); ?></span>
            </button>
            <div class="error-msg" id="login-error"></div>
        </form>
        <p class="register-link"><?php echo esc_html(PPV_Lang::t('repair_login_no_account')); ?> <a href="/formular"><?php echo esc_html(PPV_Lang::t('repair_login_register')); ?></a></p>
    </div>
    <div class="footer-text"><?php echo esc_html(PPV_Lang::t('repair_powered_by')); ?> <a href="https://punktepass.de" target="_blank">PunktePass</a></div>
</div>

<script>
(function(){
    var ppvLang = <?php echo $js_strings; ?>;

    // Language switcher
    document.querySelectorAll('.login-lang-opt').forEach(function(btn){
        btn.addEventListener('click', function(){
            var lang = btn.getAttribute('data-lang');
            var url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            document.cookie = 'ppv_lang=' + lang + ';path=/;max-age=31536000';
            window.location.href = url.toString();
        });
    });
    document.addEventListener('click', function(e){ var w=document.getElementById('login-lang-wrap'); if(w && !w.contains(e.target)) w.classList.remove('open'); });

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

        fetch(<?php echo json_encode(esc_url($ajax_url)); ?>,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){
                window.location.href=data.data.redirect||"/formular/admin";
            }else{
                err.textContent=data.data&&data.data.message?data.data.message:ppvLang.login_failed;
                err.style.display="block";
            }
        })
        .catch(function(){
            err.textContent=ppvLang.connection_error;
            err.style.display="block";
        })
        .finally(function(){
            btn.classList.remove("loading");
            btn.disabled=false;
        });
    });
})();

// ── Google OAuth (GSI) for Login ──
var ppvGoogleClientId = '<?php echo defined("PPV_GOOGLE_CLIENT_ID") ? PPV_GOOGLE_CLIENT_ID : get_option("ppv_google_client_id", "645942978357-ndj7dgrapd2dgndnjf03se1p08l0o9ra.apps.googleusercontent.com"); ?>';
var ppvAppleClientId  = '<?php echo defined("PPV_APPLE_CLIENT_ID") ? PPV_APPLE_CLIENT_ID : get_option("ppv_apple_client_id", ""); ?>';
var AJAX_URL = <?php echo json_encode(esc_url($ajax_url)); ?>;
var ppvGoogleInit = false;

function ppvInitGoogle() {
    if (ppvGoogleInit || typeof google === 'undefined' || !google.accounts) return false;
    try {
        google.accounts.id.initialize({
            client_id: ppvGoogleClientId,
            callback: ppvGoogleCallback,
            auto_select: false
        });
        ppvGoogleInit = true;
    } catch(e) {}
    return ppvGoogleInit;
}

function ppvGoogleCallback(response) {
    if (!response.credential) return;
    var errEl = document.getElementById('login-error');
    errEl.style.display = 'none';

    var fd = new FormData();
    fd.append('action', 'ppv_repair_google_login');
    fd.append('credential', response.credential);
    fd.append('mode', 'login');

    fetch(AJAX_URL, {method: 'POST', body: fd, credentials: 'same-origin'})
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            window.location.href = data.data.redirect || '/formular/admin';
        } else {
            errEl.textContent = data.data && data.data.message ? data.data.message : 'Google Login fehlgeschlagen';
            errEl.style.display = 'block';
        }
    })
    .catch(function() {
        errEl.textContent = 'Netzwerkfehler';
        errEl.style.display = 'block';
    });
}

function ppvOAuthLogin(provider) {
    if (provider === 'google') {
        ppvInitGoogle();
        if (ppvGoogleInit && typeof google !== 'undefined' && google.accounts) {
            try { google.accounts.id.cancel(); } catch(e) {}
            google.accounts.id.prompt(function(notification) {
                if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
                    var container = document.createElement('div');
                    container.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;background:#fff;padding:24px;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,0.2)';
                    document.body.appendChild(container);
                    google.accounts.id.renderButton(container, { theme:'outline', size:'large', width:280 });
                    setTimeout(function() { var b = container.querySelector('[role="button"]'); if(b) b.click(); }, 200);
                    setTimeout(function() { if(container.parentNode) container.parentNode.removeChild(container); }, 30000);
                }
            });
        }
    } else if (provider === 'apple') {
        ppvAppleLogin();
    }
}

function ppvAppleLogin() {
    if (!ppvAppleClientId) {
        var errEl = document.getElementById('login-error');
        errEl.textContent = 'Apple Sign-In ist derzeit nicht verfügbar';
        errEl.style.display = 'block';
        return;
    }
    if (typeof AppleID === 'undefined') {
        var s = document.createElement('script');
        s.src = 'https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js';
        s.onload = function() { doAppleLogin(); };
        document.head.appendChild(s);
    } else {
        doAppleLogin();
    }
}

function doAppleLogin() {
    try {
        AppleID.auth.init({
            clientId: ppvAppleClientId,
            scope: 'name email',
            redirectURI: window.location.origin + '/formular/admin/login',
            usePopup: true
        });
        AppleID.auth.signIn().then(function(response) {
            if (!response.authorization || !response.authorization.id_token) return;

            var fd = new FormData();
            fd.append('action', 'ppv_repair_apple_login');
            fd.append('id_token', response.authorization.id_token);
            fd.append('mode', 'login');
            if (response.user) fd.append('user', JSON.stringify(response.user));

            fetch(AJAX_URL, {method: 'POST', body: fd, credentials: 'same-origin'})
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.href = data.data.redirect || '/formular/admin';
                } else {
                    var errEl = document.getElementById('login-error');
                    errEl.textContent = data.data && data.data.message ? data.data.message : 'Apple Login fehlgeschlagen';
                    errEl.style.display = 'block';
                }
            })
            .catch(function() {
                var errEl = document.getElementById('login-error');
                errEl.textContent = 'Netzwerkfehler';
                errEl.style.display = 'block';
            });
        }).catch(function(err) {
            if (err.error !== 'popup_closed_by_user') {
                var errEl = document.getElementById('login-error');
                errEl.textContent = 'Apple Sign-In fehlgeschlagen';
                errEl.style.display = 'block';
            }
        });
    } catch(e) {
        var errEl = document.getElementById('login-error');
        errEl.textContent = 'Apple Sign-In fehlgeschlagen';
        errEl.style.display = 'block';
    }
}

// Init Google on page load
if (typeof google !== 'undefined' && google.accounts) { ppvInitGoogle(); }
else { window.addEventListener('load', function() { setTimeout(ppvInitGoogle, 500); }); }
</script>
</body>
</html><?php
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

        // Load translations
        PPV_Lang::load_extra('ppv-repair-lang');
        $lang = PPV_Lang::current();

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

        // Auto-generate store_slug if missing
        if (empty($store->store_slug)) {
            $base_slug = sanitize_title($store->name ?: 'store-' . $store->id);
            $slug = $base_slug;
            $counter = 1;
            // Ensure unique slug
            while ($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$prefix}ppv_stores WHERE store_slug = %s AND id != %d",
                $slug, $store->id
            ))) {
                $slug = $base_slug . '-' . $counter++;
            }
            $wpdb->update("{$prefix}ppv_stores", ['store_slug' => $slug], ['id' => $store->id]);
            $store->store_slug = $slug;
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

        // Recent repairs - active (not done) first, then by date
        $recent = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_repairs WHERE store_id = %d
             ORDER BY CASE WHEN status IN ('done','delivered','cancelled') THEN 1 ELSE 0 END ASC,
             created_at DESC LIMIT 50", $store->id
        ));

        $form_url   = home_url("/formular/{$store->store_slug}");
        $ajax_url   = admin_url('admin-ajax.php');
        $nonce      = wp_create_nonce('ppv_repair_admin');
        $is_premium = !empty($store->repair_premium);
        $limit_pct  = ($store->repair_form_limit > 0) ? min(100, round(($store->repair_form_count / $store->repair_form_limit) * 100)) : 0;

        $store_name  = esc_html($store->name);
        $store_logo  = esc_url($store->logo ?: '');
        $store_slug  = esc_attr($store->store_slug);
        $store_color = esc_attr($store->repair_color ?: '#667eea');

        // Filialen: get parent ID and all repair-enabled filialen
        $parent_store_id = $store->parent_store_id ? intval($store->parent_store_id) : $store->id;
        $filialen = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, city, plz, store_slug, parent_store_id
             FROM {$prefix}ppv_stores
             WHERE (parent_store_id = %d OR id = %d)
               AND repair_enabled = 1
             ORDER BY CASE WHEN parent_store_id IS NULL THEN 0 ELSE 1 END, id ASC",
            $parent_store_id, $parent_store_id
        ));
        $has_filialen = count($filialen) > 1;
        $parent_store = $wpdb->get_row($wpdb->prepare(
            "SELECT max_filialen, repair_premium, subscription_status, subscription_expires_at FROM {$prefix}ppv_stores WHERE id = %d",
            $parent_store_id
        ));
        $max_filialen = max(intval($parent_store->max_filialen ?? 1), 5);
        $p_sub_active = ($parent_store->subscription_status ?? '') === 'active'
            && (!$parent_store->subscription_expires_at || strtotime($parent_store->subscription_expires_at) > time());
        $parent_is_premium = !empty($parent_store->repair_premium) || $p_sub_active;

        $pp_enabled = isset($store->repair_punktepass_enabled) ? intval($store->repair_punktepass_enabled) : 1;
        $reward_name = esc_attr($store->repair_reward_name ?? '10 Euro Rabatt');
        $reward_desc = esc_attr($store->repair_reward_description ?? '10 Euro Rabatt auf Ihre nächste Reparatur');
        $required_points = intval($store->repair_required_points ?? 4);
        $raw_title = $store->repair_form_title ?? '';
        $form_title = esc_attr(($raw_title === '' || $raw_title === 'Reparaturauftrag') ? PPV_Lang::t('repair_admin_form_title_ph') : $raw_title);
        $form_subtitle = esc_attr($store->repair_form_subtitle ?? '');
        $service_type = esc_attr($store->repair_service_type ?? 'Allgemein');
        $reward_type = esc_attr($store->repair_reward_type ?? 'discount_fixed');
        $reward_value = floatval($store->repair_reward_value ?? 10);
        $reward_product = esc_attr($store->repair_reward_product ?? '');
        $inv_prefix = esc_attr($store->repair_invoice_prefix ?? 'RE-');
        $inv_next = intval($store->repair_invoice_next_number ?? 1);

        // Auto-detect highest existing invoice number from database (only matching current prefix)
        $inv_detected_max = 0;
        $search_prefix = $inv_prefix ?: 'RE-';
        $all_invoice_numbers = $wpdb->get_col($wpdb->prepare(
            "SELECT invoice_number FROM {$prefix}ppv_repair_invoices WHERE store_id = %d AND (doc_type = 'rechnung' OR doc_type IS NULL) AND invoice_number LIKE %s",
            $store_id,
            $wpdb->esc_like($search_prefix) . '%'
        ));
        foreach ($all_invoice_numbers as $inv_num) {
            // Extract numeric part after prefix
            if (preg_match('/(\d+)$/', $inv_num, $m)) {
                $num = intval($m[1]);
                if ($num > $inv_detected_max) {
                    $inv_detected_max = $num;
                }
            }
        }
        $inv_suggested_next = $inv_detected_max > 0 ? $inv_detected_max + 1 : $inv_next;
        // Effective next = what will actually be used (matches creation logic)
        $effective_next = $inv_detected_max >= $inv_next ? $inv_detected_max + 1 : $inv_next;

        // Count total invoices (all prefixes) for info display
        $inv_total_count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_repair_invoices WHERE store_id = %d AND (doc_type = 'rechnung' OR doc_type IS NULL)",
            $store_id
        ));

        $vat_enabled = isset($store->repair_vat_enabled) ? intval($store->repair_vat_enabled) : 1;
        $vat_rate = floatval($store->repair_vat_rate ?? 19);
        $field_config = json_decode($store->repair_field_config ?? '', true) ?: [];
        $fc_defaults = [
            'device_brand'    => ['enabled' => true,  'label' => PPV_Lang::t('repair_brand_label')],
            'device_model'    => ['enabled' => true,  'label' => PPV_Lang::t('repair_model_label')],
            'device_imei'     => ['enabled' => true,  'label' => PPV_Lang::t('repair_imei_label')],
            'device_pattern'  => ['enabled' => true,  'label' => PPV_Lang::t('repair_pin_label')],
            'accessories'     => ['enabled' => true,  'label' => PPV_Lang::t('repair_accessories_label')],
            'customer_phone'  => ['enabled' => true,  'label' => PPV_Lang::t('repair_phone_label')],
            'customer_address'=> ['enabled' => true,  'label' => PPV_Lang::t('repair_address_label')],
            'muster_image'    => ['enabled' => true,  'label' => PPV_Lang::t('repair_pattern_label')],
            'photo_upload'    => ['enabled' => false, 'label' => PPV_Lang::t('repair_fb_photo')],
            'condition_check' => ['enabled' => false, 'label' => PPV_Lang::t('repair_fb_condition')],
            'priority'        => ['enabled' => false, 'label' => PPV_Lang::t('repair_fb_priority')],
            'purchase_date'   => ['enabled' => false, 'label' => PPV_Lang::t('repair_fb_purchase_date')],
            'device_color'    => ['enabled' => false, 'label' => PPV_Lang::t('repair_fb_color')],
            'cost_limit'      => ['enabled' => false, 'label' => PPV_Lang::t('repair_fb_cost_limit')],
            // KFZ / Vehicle fields
            'vehicle_plate'       => ['enabled' => false, 'label' => PPV_Lang::t('repair_vehicle_plate_label')],
            'vehicle_vin'         => ['enabled' => false, 'label' => PPV_Lang::t('repair_vehicle_vin_label')],
            'vehicle_mileage'     => ['enabled' => false, 'label' => PPV_Lang::t('repair_vehicle_mileage_label')],
            'vehicle_first_reg'   => ['enabled' => false, 'label' => PPV_Lang::t('repair_vehicle_first_reg_label')],
            'vehicle_tuev'        => ['enabled' => false, 'label' => PPV_Lang::t('repair_vehicle_tuev_label')],
            'condition_check_kfz' => ['enabled' => false, 'label' => PPV_Lang::t('repair_fb_condition_kfz')],
            // PC / Computer fields
            'condition_check_pc' => ['enabled' => false, 'label' => PPV_Lang::t('repair_fb_condition_pc')],
        ];
        foreach ($fc_defaults as $k => $v) { if (!isset($field_config[$k])) $field_config[$k] = $v; }

        // Email template settings
        $email_subject = esc_attr($store->repair_invoice_email_subject ?? 'Ihre Rechnung {invoice_number}');
        $email_body_default = "Sehr geehrte/r {customer_name},\n\nanbei erhalten Sie Ihre Rechnung {invoice_number} vom {invoice_date}.\n\nGesamtbetrag: {total} €\n\nBei Fragen stehen wir Ihnen gerne zur Verfügung.\n\nMit freundlichen Grüßen,\n{company_name}";
        $email_body = esc_textarea($store->repair_invoice_email_body ?? $email_body_default);

        // Payment/Bank settings for invoices
        $bank_name = esc_attr($store->repair_bank_name ?? '');
        $bank_iban = esc_attr($store->repair_bank_iban ?? '');
        $bank_bic = esc_attr($store->repair_bank_bic ?? '');
        $paypal_email = esc_attr($store->repair_paypal_email ?? '');
        $steuernummer = esc_attr($store->repair_steuernummer ?? '');
        $website_url = esc_attr($store->repair_website_url ?? '');

        // Status notification settings
        $status_notify_enabled = isset($store->repair_status_notify_enabled) ? intval($store->repair_status_notify_enabled) : 0;
        $status_notify_statuses = $store->repair_status_notify_statuses ?? 'in_progress,done,delivered';
        $notify_statuses_arr = explode(',', $status_notify_statuses);

        // Feedback email settings
        $feedback_email_enabled = isset($store->repair_feedback_email_enabled) ? intval($store->repair_feedback_email_enabled) : 0;
        $google_review_url = esc_attr($store->repair_google_review_url ?? '');
        $warranty_text = esc_textarea($store->repair_warranty_text ?? '');

        // Custom form options (for different branches)
        $custom_brands = esc_textarea($store->repair_custom_brands ?? '');
        $custom_problems = esc_textarea($store->repair_custom_problems ?? '');
        $custom_accessories = esc_textarea($store->repair_custom_accessories ?? '');
        $success_message = esc_textarea($store->repair_success_message ?? '');
        $opening_hours = esc_attr($store->repair_opening_hours ?? '');
        $terms_url = esc_attr($store->repair_terms_url ?? '');

        // Partner directory: all active partners for the Lieferanten section
        $all_partners = $wpdb->get_results(
            "SELECT id, company_name, logo_url, website, city, country FROM {$prefix}ppv_partners WHERE status = 'active' ORDER BY company_name ASC"
        );
        $my_partner_id = intval($store->partner_id ?? 0);

        // Build repairs HTML
        $repairs_html = '';
        if (empty($recent)) {
            $repairs_html = '<div class="ra-empty"><i class="ri-inbox-line"></i><p>' . esc_html(PPV_Lang::t('repair_admin_no_repairs')) . '</p></div>';
        } else {
            foreach ($recent as $r) {
                $repairs_html .= self::build_repair_card_html($r, $store);
            }
        }

        echo '<!DOCTYPE html>
<html lang="' . esc_attr($lang) . '">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>' . $store_name . ' - ' . esc_html(PPV_Lang::t('repair_admin_title')) . '</title>
<meta name="theme-color" content="' . $store_color . '">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
<style>
/* ========== Reset & Base - Modern ========== */
*{margin:0;padding:0;box-sizing:border-box}
html{min-height:100%;overflow-y:scroll}
body{font-family:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f8fafc;color:#0f172a;line-height:1.6;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;min-height:100vh}
a{color:#667eea;text-decoration:none;transition:color .2s}
a:hover{color:#5a67d8}

/* ========== Layout ========== */
@keyframes raFadeIn{from{opacity:0.99}to{opacity:1}}
.ra-wrap{width:100%;max-width:100%;margin:0 auto;padding:16px 24px 40px;box-sizing:border-box;animation:raFadeIn 0.01s ease-out}
@media(min-width:1200px){.ra-wrap{padding:16px 40px 40px}}

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

/* ========== Partner / Supplier Directory ========== */
.ra-suppliers{background:#fff;border-radius:14px;margin-bottom:16px;border:1px solid #e2e8f0;overflow:hidden}
.ra-suppliers-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;cursor:pointer;user-select:none;transition:background .2s}
.ra-suppliers-header:hover{background:#f8fafc}
.ra-suppliers-title{display:flex;align-items:center;gap:10px;font-size:14px;font-weight:700;color:#1e293b}
.ra-suppliers-title i{font-size:20px;color:#667eea}
.ra-suppliers-count{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:100px;min-width:22px;text-align:center}
.ra-suppliers-toggle{background:none;border:none;cursor:pointer;padding:4px;color:#94a3b8;font-size:20px;transition:transform .3s}
.ra-suppliers-toggle.open{transform:rotate(180deg)}
.ra-suppliers-body{padding:0 18px 18px;display:none}
.ra-suppliers-body.open{display:block}
.ra-suppliers-scroll{display:flex;gap:14px;overflow-x:auto;padding:4px 2px 8px;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:thin;scrollbar-color:#e2e8f0 transparent}
.ra-suppliers-scroll::-webkit-scrollbar{height:4px}
.ra-suppliers-scroll::-webkit-scrollbar-track{background:transparent}
.ra-suppliers-scroll::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:4px}
.ra-supplier-card{min-width:180px;max-width:200px;background:#f8fafc;border-radius:14px;padding:20px 16px;text-align:center;border:1.5px solid #e2e8f0;flex-shrink:0;scroll-snap-align:start;transition:all .25s;position:relative}
.ra-supplier-card:hover{border-color:#c7d2fe;box-shadow:0 4px 16px rgba(99,102,241,.1);transform:translateY(-2px)}
.ra-supplier-mine{border-color:#818cf8;background:linear-gradient(180deg,#f5f3ff,#f0f4ff)}
.ra-supplier-badge{position:absolute;top:-8px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:9px;font-weight:700;padding:3px 10px;border-radius:100px;white-space:nowrap;display:flex;align-items:center;gap:3px;text-transform:uppercase;letter-spacing:.5px}
.ra-supplier-badge i{font-size:10px}
.ra-supplier-logo-wrap{width:64px;height:64px;margin:0 auto 12px;border-radius:14px;overflow:hidden;background:#fff;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center}
.ra-supplier-logo{width:100%;height:100%;object-fit:contain;padding:6px}
.ra-supplier-logo-ph{font-size:28px;color:#94a3b8}
.ra-supplier-name{font-size:14px;font-weight:700;color:#1e293b;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ra-supplier-city{font-size:11px;color:#64748b;display:flex;align-items:center;justify-content:center;gap:3px;margin-bottom:12px}
.ra-supplier-city i{font-size:11px}
.ra-supplier-btn{display:inline-flex;align-items:center;gap:5px;padding:8px 16px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;transition:all .2s}
.ra-supplier-btn:hover{box-shadow:0 3px 10px rgba(102,126,234,.35);color:#fff;transform:translateY(-1px)}

/* ========== Settings Panel - Modern Accordion ========== */
.ra-hidden{display:none !important}
.ra-settings{background:#fff;border-radius:20px;padding:0;margin-bottom:16px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.04),0 4px 12px rgba(0,0,0,0.04);overflow:visible}
.ra-settings h3{font-size:18px;font-weight:700;color:#0f172a;margin:0;padding:20px 24px;display:flex;align-items:center;gap:10px;border-bottom:1px solid #f1f5f9;background:linear-gradient(180deg,#fff,#fafbfc)}
.ra-settings h3 i{font-size:20px;color:#667eea}

/* Accordion Section Title */
.ra-section-title{font-size:15px;font-weight:700;color:#0f172a;margin:0;padding:18px 24px;display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;transition:all .2s;border-bottom:1px solid transparent}
.ra-section-title:hover{background:#f8fafc}
.ra-section-title i{font-size:18px;color:#667eea;width:24px;flex-shrink:0}
.ra-section-title::after{content:"\\eb96";font-family:"remixicon";margin-left:auto;font-size:18px;color:#94a3b8;transition:transform .2s}
.ra-section-title.ra-open::after{transform:rotate(180deg)}
.ra-section-title.ra-open{background:#f8fafc;border-bottom-color:#e2e8f0}

/* Section Content */
.ra-section-content{display:none;padding:20px 24px;background:#fff;border-bottom:1px solid #f1f5f9}
.ra-section-content.ra-show{display:block}
.ra-section-content:last-child{border-bottom:none}

/* Divider */
.ra-section-divider{border:none;height:1px;background:#f1f5f9;margin:24px 0}

.ra-settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(min-width:1024px){.ra-settings-grid{grid-template-columns:repeat(3,1fr)}}
.ra-settings .field{margin-bottom:0}
.ra-settings .field label{display:block;font-size:13px;font-weight:600;color:#64748b;margin-bottom:6px}
.ra-settings .field input[type="text"],
.ra-settings .field input[type="number"],
.ra-settings .field input[type="email"],
.ra-settings .field input[type="url"],
.ra-settings .field input[type="color"],
.ra-settings .field select{width:100%;padding:12px 14px;border:2px solid #e2e8f0;border-radius:12px;font-size:14px;color:#0f172a;background:#f8fafc;outline:none;transition:all .2s;font-family:inherit}
.ra-settings .field input:hover,
.ra-settings .field select:hover{border-color:#cbd5e1}
.ra-settings .field input:focus,
.ra-settings .field select:focus{border-color:#667eea;background:#fff;box-shadow:0 0 0 4px rgba(102,126,234,0.1)}
.ra-settings .field select{-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2712%27 height=%2712%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%236b7280%27 stroke-width=%272%27%3E%3Cpath d=%27M6 9l6 6 6-6%27/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:36px;cursor:pointer}
.ra-settings .field input[type="color"]{height:48px;padding:6px;cursor:pointer}
.ra-logo-section{margin-top:20px;margin-bottom:20px}
.ra-logo-section>label{display:block;font-size:13px;font-weight:600;color:#64748b;margin-bottom:10px}
.ra-logo-upload{display:flex;align-items:center;gap:16px}
.ra-logo-preview{width:64px;height:64px;border-radius:16px;object-fit:cover;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:28px;color:#94a3b8;flex-shrink:0;overflow:hidden;border:2px solid #e2e8f0}
.ra-logo-preview img{width:100%;height:100%;object-fit:cover}
.ra-settings-save{margin-top:24px;padding:20px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;margin:-20px -24px -24px -24px}
.ra-legal-links{margin-top:28px;padding-top:24px;border-top:1px solid #e2e8f0}
.ra-legal-links h4{font-size:13px;font-weight:700;color:#64748b;margin-bottom:12px;text-transform:uppercase;letter-spacing:0.5px}
.ra-legal-links a{display:inline-flex;align-items:center;gap:6px;margin-right:20px;margin-bottom:10px;font-size:14px;color:#667eea;font-weight:500}
.ra-legal-links a:hover{color:#5a67d8}
.ra-pp-link{margin-top:20px;padding-top:20px;border-top:1px solid #e2e8f0}

/* ========== Settings Tabs ========== */
.ra-settings-tabs{display:flex;gap:4px;padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;overflow-x:auto;-webkit-overflow-scrolling:touch}
.ra-settings-tab{padding:10px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:transparent;color:#64748b;transition:all .2s;white-space:nowrap;display:inline-flex;align-items:center;gap:6px}
.ra-settings-tab i{font-size:15px}
.ra-settings-tab:hover{background:#e2e8f0;color:#0f172a}
.ra-settings-tab.active{background:#667eea;color:#fff}
.ra-settings-panel{display:none;padding:24px}
.ra-settings-panel.active{display:block}
.ra-settings-panel h4{font-size:15px;font-weight:700;color:#0f172a;margin:0 0 16px 0;padding-bottom:12px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px}
.ra-settings-panel h4 i{color:#667eea;font-size:18px}
.ra-settings-panel h4:not(:first-child){margin-top:28px}
.ra-settings-group{background:#f8fafc;border-radius:12px;padding:16px;margin-bottom:16px}
.ra-settings-group-title{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px}

/* ========== Chip/Tag Editor ========== */
.ra-chip-editor{border:2px solid #e2e8f0;border-radius:12px;background:#f8fafc;padding:8px 10px;min-height:48px;transition:all .2s;cursor:text}
.ra-chip-editor:focus-within{border-color:#667eea;background:#fff;box-shadow:0 0 0 4px rgba(102,126,234,0.1)}
.ra-chip-wrap{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.ra-chip{display:inline-flex;align-items:center;gap:4px;padding:5px 8px 5px 10px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border-radius:20px;font-size:12.5px;font-weight:500;animation:ra-chip-in .2s ease}
@keyframes ra-chip-in{from{opacity:0;transform:scale(.85)}to{opacity:1;transform:scale(1)}}
.ra-chip-x{width:18px;height:18px;border-radius:50%;background:rgba(255,255,255,0.25);border:none;color:#fff;font-size:14px;line-height:1;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;padding:0;transition:background .15s}
.ra-chip-x:hover{background:rgba(255,255,255,0.45)}
.ra-chip-input{border:none;outline:none;background:transparent;font-size:13px;font-family:inherit;color:#0f172a;min-width:120px;flex:1;padding:4px 2px}
.ra-chip-input::placeholder{color:#94a3b8}
.ra-chip-count{font-size:11px;color:#94a3b8;margin-top:4px}

/* ========== Toolbar ========== */
.ra-toolbar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap}
.ra-search{flex:1;position:relative;min-width:200px}
.ra-search i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:18px}
.ra-search input{width:100%;padding:11px 14px 11px 42px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;color:#1f2937;background:#fff;outline:none;transition:border-color .2s}
.ra-search input:focus{border-color:#667eea}
.ra-search input::placeholder{color:#9ca3af}
.ra-filters select{padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;color:#374151;background:#fff;outline:none;cursor:pointer;appearance:auto;min-width:140px}
.ra-filters select:focus{border-color:#667eea}

/* ========== Repair Cards - Professional Widget ========== */
.ra-repairs{display:grid;grid-template-columns:1fr;gap:16px}
@media(min-width:768px){.ra-repairs{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1400px){.ra-repairs{grid-template-columns:repeat(3,1fr)}}
.ra-repair-card{background:#fff;border-radius:16px;padding:0;border:1px solid #e2e8f0;transition:box-shadow .2s,transform .2s;box-shadow:0 1px 4px rgba(0,0,0,0.04);overflow:hidden}
.ra-repair-card:hover{box-shadow:0 8px 24px rgba(0,0,0,.08);transform:translateY(-2px)}
/* Status top-border accent */
.ra-repair-card[data-status="new"]{border-top:3px solid #3b82f6}
.ra-repair-card[data-status="in_progress"]{border-top:3px solid #f59e0b}
.ra-repair-card[data-status="waiting_parts"]{border-top:3px solid #ec4899}
.ra-repair-card[data-status="done"]{border-top:3px solid #10b981}
.ra-repair-card[data-status="delivered"]{border-top:3px solid #6b7280}
.ra-repair-card[data-status="cancelled"]{border-top:3px solid #ef4444}
/* Header */
.ra-repair-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px 12px;background:#f8fafc;border-bottom:1px solid #f1f5f9}
.ra-repair-header-left{display:flex;align-items:center;gap:12px}
.ra-repair-id{font-size:14px;font-weight:800;color:#374151;font-variant-numeric:tabular-nums}
.ra-repair-date-inline{font-size:11px;color:#9ca3af;display:flex;align-items:center;gap:3px}
.ra-repair-date-inline i{font-size:12px}
.ra-status{display:inline-flex;align-items:center;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.ra-status-new{background:#dbeafe;color:#1d4ed8}
.ra-status-progress{background:#fef3c7;color:#92400e}
.ra-status-waiting{background:#fce7f3;color:#9d174d}
.ra-status-done{background:#d1fae5;color:#065f46}
.ra-status-delivered{background:#e5e7eb;color:#374151}
.ra-status-cancelled{background:#fecaca;color:#991b1b}
/* Card sections */
.ra-card-section{padding:12px 20px;border-bottom:1px solid #f1f5f9}
.ra-card-section:last-of-type{border-bottom:none}
.ra-card-section-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#94a3b8;margin-bottom:8px;display:flex;align-items:center;gap:5px}
.ra-card-section-title i{font-size:13px}
/* Customer section */
.ra-card-section-customer{padding:16px 20px}
.ra-customer-row{display:flex;align-items:flex-start;gap:12px}
.ra-customer-avatar{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.ra-customer-info{flex:1;min-width:0}
.ra-customer-info strong{font-size:16px;color:#111827;display:block;margin-bottom:4px}
.ra-repair-meta{display:flex;flex-wrap:wrap;gap:8px 14px;font-size:12px;color:#6b7280}
.ra-repair-meta span{display:inline-flex;align-items:center;gap:3px}
.ra-repair-meta i{font-size:12px;color:#9ca3af}
.ra-repair-address{font-size:12px;color:#6b7280;margin-top:6px;display:flex;align-items:center;gap:4px}
.ra-repair-address i{color:#9ca3af}
/* Device section */
.ra-card-section-device{background:#fafbfc}
.ra-device-name{font-size:15px;font-weight:600;color:#1f2937;margin-bottom:6px}
.ra-repair-imei{font-size:12px;color:#6b7280;margin-top:3px;display:flex;align-items:center;gap:4px}
.ra-repair-imei i{color:#9ca3af}
.ra-repair-pin{font-size:12px;color:#667eea;font-weight:600;margin-top:3px;display:flex;align-items:center;gap:4px}
.ra-repair-acc{font-size:12px;color:#6b7280;margin-top:3px;display:flex;align-items:center;gap:4px}
.ra-repair-acc i{color:#9ca3af}
.ra-repair-muster{margin:6px 0 0;padding:8px;background:#f1f5f9;border-radius:8px;display:inline-block}
.ra-repair-muster img{width:80px;height:80px;border-radius:6px;border:1px solid #e2e8f0;cursor:pointer;transition:transform .2s}
.ra-repair-muster img:hover{transform:scale(2);position:relative;z-index:10;box-shadow:0 4px 12px rgba(0,0,0,0.15)}
/* Problem section */
.ra-repair-problem{font-size:13px;color:#374151;line-height:1.6}
.ra-tracking-section{background:#fefce8;border-top:1px solid #fde68a}
.ra-tracking-row{display:flex;align-items:center;gap:6px}
.ra-tracking-btn{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:6px 12px;border-radius:8px;border:1px solid #fde68a;cursor:pointer;transition:all .15s;text-decoration:none}
.ra-tracking-btn-copy{background:#fffbeb;color:#92400e}
.ra-tracking-btn-copy:hover{background:#fef3c7}
.ra-tracking-btn-open{background:#fff;color:#92400e}
.ra-tracking-btn-open:hover{background:#fef3c7}
.ra-tracking-btn i{font-size:14px}
/* Details section (custom fields) */
.ra-card-section-details{background:#fafbfc}
/* Badges row */
.ra-card-badges{display:flex;flex-wrap:wrap;gap:6px;padding:8px 20px}
.ra-card-updated{font-size:11px;color:#9ca3af;padding:4px 20px 0;display:flex;align-items:center;gap:4px}
.ra-card-updated i{font-size:12px}
/* Actions toolbar */
.ra-repair-actions{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1px solid #f1f5f9;background:#fafbfc;gap:8px}
.ra-actions-left{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.ra-status-select{padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;color:#374151;background:#fff;outline:none;cursor:pointer;appearance:auto;max-width:180px}
.ra-status-select:focus{border-color:#667eea}
.ra-btn-resubmit{padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid #c7d2fe;background:#eef2ff;color:#667eea;display:inline-flex;align-items:center;gap:4px;transition:all .2s;white-space:nowrap}
.ra-btn-resubmit:hover{background:#667eea;color:#fff;border-color:#667eea}
.ra-btn-invoice{padding:6px 10px;border-radius:8px;font-size:14px;cursor:pointer;border:1px solid #bbf7d0;background:#f0fdf4;color:#16a34a;display:inline-flex;align-items:center;justify-content:center;transition:all .2s}
.ra-btn-invoice:hover{background:#16a34a;color:#fff;border-color:#16a34a}
.ra-btn-invoice-exists{border-color:#fbbf24;background:#fffbeb;color:#d97706}
.ra-btn-invoice-exists:hover{background:#d97706;color:#fff;border-color:#d97706}
.ra-invoice-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#d97706;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:3px 10px}
.ra-invoice-badge i{font-size:12px}
.ra-btn-delete{padding:6px 10px;border-radius:8px;font-size:14px;cursor:pointer;border:1px solid #fecaca;background:#fef2f2;color:#dc2626;display:inline-flex;align-items:center;justify-content:center;transition:all .2s}
.ra-btn-delete:hover{background:#dc2626;color:#fff;border-color:#dc2626}
.ra-btn-print{padding:6px 10px;border-radius:8px;font-size:14px;cursor:pointer;border:1px solid #e5e7eb;background:#f9fafb;color:#374151;display:inline-flex;align-items:center;justify-content:center;transition:all .2s}
.ra-btn-print:hover{background:#374151;color:#fff;border-color:#374151}
.ra-btn-email{padding:6px 10px;border-radius:8px;font-size:14px;cursor:pointer;border:1px solid #bfdbfe;background:#eff6ff;color:#2563eb;display:inline-flex;align-items:center;justify-content:center;transition:all .2s}
.ra-btn-email:hover{background:#2563eb;color:#fff;border-color:#2563eb}
.ra-btn-angebot{padding:6px 10px;border-radius:8px;font-size:14px;cursor:pointer;border:1px solid #bbf7d0;background:#f0fdf4;color:#16a34a;display:inline-flex;align-items:center;justify-content:center;transition:all .2s}
.ra-btn-angebot:hover{background:#16a34a;color:#fff;border-color:#16a34a}

/* ========== Reward Section ========== */
.ra-reward-toggle-section{padding:10px 20px;margin:0}
.ra-btn-reward-toggle{padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .2s;border:none}
.ra-reward-badge-available{background:linear-gradient(135deg,#fef3c7,#fde68a);color:#92400e}
.ra-reward-badge-available:hover{background:linear-gradient(135deg,#fde68a,#fcd34d)}
.ra-reward-badge-rejected{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.ra-reward-badge-rejected:hover{background:#fee2e2}
.ra-reward-container{margin-top:10px;animation:slideDown .2s ease}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.ra-reward-section{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px 16px}
.ra-reward-section.ra-reward-rejected{background:#fef2f2;border-color:#fecaca}
.ra-reward-header{font-size:14px;font-weight:700;color:#92400e;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.ra-reward-rejected .ra-reward-header{color:#991b1b}
.ra-reward-info{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.ra-reward-badge{background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:4px}
.ra-reward-badge i{color:#f59e0b}
.ra-reward-name{font-size:13px;color:#374151;font-weight:500}
.ra-reward-rejection-info{background:#fee2e2;border-radius:8px;padding:8px 12px;font-size:12px;color:#991b1b;margin-bottom:12px}
.ra-reward-actions{display:flex;gap:8px;flex-wrap:wrap}
.ra-reward-approve{padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:#059669;color:#fff;display:inline-flex;align-items:center;gap:4px;transition:all .2s}
.ra-reward-approve:hover{background:#047857}
.ra-reward-reject{padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid #fecaca;background:#fff;color:#dc2626;display:inline-flex;align-items:center;gap:4px;transition:all .2s}
.ra-reward-reject:hover{background:#fef2f2}
.ra-reward-approved-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#d1fae5;color:#065f46;font-size:12px;font-weight:600;border-radius:8px;margin:8px 20px}
.ra-btn-parts-arrived{display:inline-flex;align-items:center;gap:8px;margin:10px 20px;padding:10px 18px;background:linear-gradient(135deg,#d97706,#f59e0b);color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;transition:all .25s ease;width:fit-content;box-shadow:0 2px 8px rgba(217,119,6,.3);letter-spacing:.2px;position:relative;overflow:hidden}
.ra-btn-parts-arrived::before{content:"";position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.25),transparent);transition:left .5s}
.ra-btn-parts-arrived:hover{background:linear-gradient(135deg,#b45309,#d97706);box-shadow:0 4px 14px rgba(217,119,6,.4);transform:translateY(-1px)}
.ra-btn-parts-arrived:hover::before{left:100%}
.ra-btn-parts-arrived:active{transform:translateY(0) scale(.98);box-shadow:0 1px 4px rgba(217,119,6,.3)}
.ra-btn-parts-arrived i{font-size:17px}

/* ========== Comments Widget - Modern ========== */
.ra-repair-comments-section{padding:10px 20px;margin:0}
.ra-btn-comments-toggle{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid #e0e7ff;background:#f0f4ff;color:#6366f1;transition:all .2s}
.ra-btn-comments-toggle:hover{background:#e0e7ff;border-color:#c7d2fe}
.ra-btn-comments-toggle.active{background:#6366f1;color:#fff;border-color:#6366f1}
.ra-btn-comments-toggle i{font-size:15px}
.ra-comments-container{margin-top:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px;animation:slideDown .2s ease}
.ra-comments-list{max-height:200px;overflow-y:auto;margin-bottom:10px}
.ra-comments-list::-webkit-scrollbar{width:4px}
.ra-comments-list::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}
.ra-comment-item{position:relative;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px 32px 10px 12px;margin-bottom:8px;transition:box-shadow .15s}
.ra-comment-item:hover{box-shadow:0 2px 8px rgba(0,0,0,.06)}
.ra-comment-item:last-child{margin-bottom:0}
.ra-comment-text{font-size:13px;color:#1e293b;line-height:1.5;word-break:break-word}
.ra-comment-meta{font-size:11px;color:#94a3b8;margin-top:4px;display:flex;align-items:center;gap:4px}
.ra-comment-delete{position:absolute;top:6px;right:6px;width:22px;height:22px;border:none;background:transparent;color:#cbd5e1;cursor:pointer;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:14px;transition:all .15s;padding:0}
.ra-comment-delete:hover{background:#fee2e2;color:#ef4444}
.ra-comment-add{display:flex;gap:8px;align-items:center}
.ra-comment-input{flex:1;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;background:#fff;outline:none;transition:border-color .2s}
.ra-comment-input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.08)}
.ra-comment-input::placeholder{color:#94a3b8}
.ra-comment-submit{width:38px;height:38px;border:none;background:linear-gradient(135deg,#6366f1,#818cf8);color:#fff;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;transition:all .2s;flex-shrink:0}
.ra-comment-submit:hover{background:linear-gradient(135deg,#4f46e5,#6366f1);transform:scale(1.05)}
.ra-comment-submit:disabled{opacity:.5;transform:none}
.ra-comments-empty{text-align:center;padding:16px;color:#94a3b8;font-size:12px}

/* ========== Reward Reject Modal ========== */
.ra-reject-modal{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9998;display:none;align-items:center;justify-content:center;padding:16px}
.ra-reject-modal.show{display:flex}
.ra-reject-modal-content{background:#fff;border-radius:16px;padding:24px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.15)}
.ra-reject-modal h3{font-size:18px;font-weight:700;color:#111827;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.ra-reject-modal textarea{width:100%;padding:12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;resize:vertical;min-height:100px;margin-bottom:16px}
.ra-reject-modal textarea:focus{border-color:#667eea;outline:none}
.ra-reject-modal-actions{display:flex;gap:10px;justify-content:flex-end}

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
    .ra-wrap{padding:12px 12px 32px}
    .ra-stats{grid-template-columns:repeat(2,1fr)}
    .ra-header{flex-direction:column;align-items:flex-start}
    .ra-header-right{width:100%;justify-content:flex-start}
    .ra-settings-grid{grid-template-columns:1fr}
    .ra-toolbar{flex-direction:column}
    .ra-filters select{width:100%}
    .ra-title{font-size:18px}
    .ra-repairs{grid-template-columns:1fr}
}
/* ========== Toggle Switch ========== */
.ra-toggle{display:flex;align-items:center;gap:10px;margin-bottom:16px}
.ra-toggle-switch{position:relative;width:44px;height:24px;flex-shrink:0}
.ra-toggle-switch input{opacity:0;width:0;height:0}
.ra-toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#d1d5db;border-radius:24px;transition:.3s}
.ra-toggle-slider:before{content:"";position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
.ra-toggle-switch input:checked+.ra-toggle-slider{background:#667eea}
.ra-toggle-switch input:checked+.ra-toggle-slider:before{transform:translateX(20px)}
.ra-toggle-label{font-size:14px;font-weight:600;color:#374151}
.ra-toggle-desc{font-size:12px;color:#6b7280;font-weight:400}
/* ========== Section Divider ========== */
.ra-section-divider{border:none;border-top:1px solid #f0f0f0;margin:24px 0}
.ra-section-title{font-size:15px;font-weight:700;color:#111827;margin-bottom:16px;display:flex;align-items:center;gap:8px}
/* ========== WYSIWYG Form Builder ========== */
.ra-fb{background:#f0f2f5;border:2px solid #e2e8f0;border-radius:16px;overflow:hidden;margin-top:12px}
.ra-fb-toolbar{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}
.ra-fb-toolbar-title{font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px}
.ra-fb-toolbar-hint{font-size:11px;opacity:0.8;font-weight:500}
.ra-fb-body{display:flex;min-height:500px}
.ra-fb-main{flex:1;min-width:0;padding:20px;overflow-y:auto}
.ra-fb-preview{max-width:520px;margin:0 auto}
.ra-fb-section{background:#fff;border-radius:14px;padding:20px 18px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.04),0 4px 12px rgba(0,0,0,0.03)}
.ra-fb-section-title{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.ra-fb-step-num{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
.ra-fb-section-title h3{font-size:15px;font-weight:700;color:#0f172a;margin:0}
.ra-fb-field{position:relative;border:2px dashed transparent;border-radius:10px;padding:10px 12px;margin-bottom:6px;transition:all .25s;cursor:default}
.ra-fb-field:hover{border-color:#c7d2fe;background:rgba(102,126,234,0.02)}
.ra-fb-field.ra-fb-hidden{display:none}
.ra-fb-field.dragging{opacity:0.4;border-color:#667eea;background:rgba(102,126,234,0.06)}
.ra-fb-field.drag-over{border-color:#667eea;background:rgba(102,126,234,0.08);box-shadow:inset 0 0 0 2px rgba(102,126,234,0.15)}
.ra-fb-field.locked{cursor:default}
.ra-fb-field.locked:hover{border-color:transparent;background:transparent}
.ra-fb-field.ra-fb-flash{animation:fbFlash .6s ease}
@keyframes fbFlash{0%{background:rgba(102,126,234,0.15);border-color:#667eea}100%{background:transparent;border-color:transparent}}
.ra-fb-controls{position:absolute;top:6px;right:6px;display:flex;align-items:center;gap:3px;opacity:0;transition:opacity .15s;z-index:5}
.ra-fb-field:hover .ra-fb-controls{opacity:1}
.ra-fb-field.locked .ra-fb-controls{display:none}
.ra-fb-ctrl-btn{width:24px;height:24px;border-radius:6px;border:none;background:rgba(255,255,255,0.95);color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s;box-shadow:0 1px 4px rgba(0,0,0,0.1)}
.ra-fb-ctrl-btn:hover{background:#fff;color:#667eea;box-shadow:0 2px 8px rgba(0,0,0,0.15)}
.ra-fb-ctrl-btn.danger:hover{color:#dc2626}
.ra-fb-drag{position:absolute;top:50%;left:-2px;transform:translateY(-50%);color:#c4c4c4;font-size:14px;opacity:0;transition:opacity .15s;cursor:grab}
.ra-fb-drag:active{cursor:grabbing}
.ra-fb-field:hover .ra-fb-drag{opacity:0.7}
.ra-fb-field:hover .ra-fb-drag:hover{opacity:1;color:#667eea}
.ra-fb-field-body label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:5px;cursor:pointer;user-select:none}
.ra-fb-field-body label:hover{color:#667eea}
.ra-fb-field-body input[type=text],.ra-fb-field-body input[type=tel],.ra-fb-field-body input[type=email],.ra-fb-field-body input[type=number],.ra-fb-field-body input[type=date],.ra-fb-field-body textarea,.ra-fb-field-body select{width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:10px;font-size:14px;color:#94a3b8;background:#f8fafc;pointer-events:none;font-family:inherit;box-sizing:border-box}
.ra-fb-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.ra-fb-label-input{border:none;border-bottom:2px solid #667eea;background:transparent;font-size:13px;font-weight:600;color:#667eea;padding:0 0 2px 0;outline:none;width:100%;font-family:inherit}
.ra-fb-req{color:#f59e0b;font-weight:700;margin-left:2px}
.ra-fb-lock-icon{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;color:#94a3b8;margin-left:6px}
.ra-fb-settings{display:none;margin-top:10px;padding:12px;background:#f8fafc;border-radius:10px;border:1.5px solid #e2e8f0}
.ra-fb-settings.open{display:block}
.ra-fb-settings-row{display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:12px;color:#374151}
.ra-fb-settings-row:last-child{margin-bottom:0}
.ra-fb-settings-row label{display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:500}
.ra-fb-settings-row select,.ra-fb-settings-row textarea{padding:6px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:12px;background:#fff;font-family:inherit;outline:none}
.ra-fb-settings-row select:focus,.ra-fb-settings-row textarea:focus{border-color:#667eea}
.ra-fb-settings-row textarea{width:100%;resize:vertical}
.ra-fb-checkbox-preview{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:100px;border:1.5px solid #e2e8f0;font-size:13px;color:#64748b;margin-right:6px;margin-bottom:4px}
.ra-fb-muster-preview{display:inline-flex;align-items:center;justify-content:center;width:80px;height:80px;border:2px dashed #e2e8f0;border-radius:10px;background:#f8fafc;color:#94a3b8;font-size:24px}
.ra-fb-sig-preview{width:100%;height:60px;border:2px dashed #e2e8f0;border-radius:10px;background:#f8fafc;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:13px;gap:6px}
.ra-fb-photo-preview{display:flex;gap:8px;flex-wrap:wrap}
.ra-fb-photo-box{width:70px;height:70px;border:2px dashed #e2e8f0;border-radius:10px;background:#f8fafc;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;font-size:18px;gap:2px}
.ra-fb-photo-box span{font-size:9px;font-weight:600}
.ra-fb-condition-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.ra-fb-condition-item{display:flex;align-items:center;justify-content:space-between;padding:6px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:12px;color:#64748b;background:#f8fafc}
.ra-fb-condition-item .ra-fb-cond-btns{display:flex;gap:4px}
.ra-fb-condition-item .ra-fb-cond-btn{padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;border:none}
.ra-fb-cond-btn.ok{background:#d1fae5;color:#059669}
.ra-fb-cond-btn.nok{background:#fef2f2;color:#dc2626;opacity:0.4}
.ra-fb-priority-cards{display:flex;gap:8px}
.ra-fb-priority-card{flex:1;padding:10px 8px;border:2px solid #e2e8f0;border-radius:10px;text-align:center;font-size:11px;color:#64748b;background:#f8fafc}
.ra-fb-priority-card.active{border-color:#667eea;background:#eff6ff;color:#667eea}
.ra-fb-priority-card i{font-size:18px;display:block;margin-bottom:4px}
.ra-fb-priority-card strong{display:block;font-size:12px;color:#0f172a}
.ra-fb-color-swatches{display:flex;gap:8px;flex-wrap:wrap}
.ra-fb-color-dot{width:28px;height:28px;border-radius:50%;border:2px solid #e2e8f0;cursor:default}
.ra-fb-color-dot.active{border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.2)}
.ra-fb-cost-options{display:flex;gap:6px;flex-wrap:wrap}
.ra-fb-cost-opt{padding:8px 14px;border:1.5px solid #e2e8f0;border-radius:20px;font-size:12px;font-weight:600;color:#64748b;background:#f8fafc}
.ra-fb-cost-opt.active{border-color:#667eea;background:#eff6ff;color:#667eea}
/* ===== Palette Sidebar ===== */
.ra-fb-palette{width:230px;flex-shrink:0;background:#fff;border-left:2px solid #e2e8f0;padding:16px 14px;overflow-y:auto}
.ra-fb-pal-title{font-size:14px;font-weight:700;color:#0f172a;margin-bottom:14px;display:flex;align-items:center;gap:6px}
.ra-fb-pal-title i{color:#667eea;font-size:16px}
.ra-fb-pal-group{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.8px;margin:14px 0 6px;padding-top:10px;border-top:1px solid #f1f5f9}
.ra-fb-pal-group:first-of-type{margin-top:0;border-top:none;padding-top:0}
.ra-fb-pal-item{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:9px;font-size:12px;font-weight:600;color:#374151;cursor:pointer;transition:all .2s;border:1.5px solid transparent;margin-bottom:3px;user-select:none}
.ra-fb-pal-item:hover{background:#f0f2ff;border-color:#c7d2fe;color:#667eea}
.ra-fb-pal-item i{font-size:15px;width:20px;text-align:center;color:#667eea;flex-shrink:0}
.ra-fb-pal-item .ra-fb-pal-check{margin-left:auto;font-size:14px;color:#10b981;display:none}
.ra-fb-pal-item.used{opacity:0.4;background:#f8fafc}
.ra-fb-pal-item.used .ra-fb-pal-check{display:inline}
.ra-fb-pal-item.used:hover{opacity:0.6}
.ra-fb-pal-add{display:flex;align-items:center;gap:6px;width:100%;padding:8px 10px;border:1.5px dashed #d1d5db;border-radius:9px;background:none;color:#64748b;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;font-family:inherit;margin-top:6px}
.ra-fb-pal-add:hover{border-color:#667eea;color:#667eea;background:rgba(102,126,234,0.04)}
@media(max-width:768px){.ra-fb-body{flex-direction:column-reverse}.ra-fb-palette{width:100%;border-left:none;border-top:2px solid #e2e8f0;max-height:200px;display:flex;flex-wrap:wrap;gap:4px;align-content:flex-start;padding:12px}.ra-fb-pal-group{width:100%;margin:6px 0 2px}.ra-fb-pal-title{display:none}.ra-fb-pal-item{flex:0 0 auto}.ra-fb-main{padding:14px}.ra-fb-row{grid-template-columns:1fr}.ra-fb-toolbar{flex-direction:column;gap:6px;text-align:center}.ra-fb-condition-grid{grid-template-columns:1fr}.ra-fb-priority-cards{flex-direction:column}}
/* ========== Tabs - Modern Pill Style ========== */
.ra-tabs{display:flex;gap:6px;margin-bottom:20px;overflow-x:auto;-webkit-overflow-scrolling:touch;background:#f1f5f9;padding:6px;border-radius:14px}
.ra-tab{padding:12px 20px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;border:none;background:transparent;color:#64748b;transition:all .2s cubic-bezier(.4,0,.2,1);white-space:nowrap;display:inline-flex;align-items:center;gap:8px}
.ra-tab i{font-size:16px}
.ra-tab.active{background:#fff;color:#0f172a;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
.ra-tab:hover:not(.active){color:#0f172a;background:rgba(255,255,255,0.5)}
.ra-tab-content{display:none}
.ra-tab-content.active{display:block}
/* ========== Invoice List ========== */
.ra-inv-filters{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:flex-end}
.ra-inv-filters .field{margin:0}
.ra-inv-filters .field label{display:block;font-size:11px;font-weight:600;color:#6b7280;margin-bottom:4px}
.ra-inv-filters .field input{padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;color:#1f2937;background:#fff;outline:none}
.ra-inv-filters .field input:focus{border-color:#667eea}
.ra-summary-toggle{transition:all .2s}
.ra-summary-toggle.active{background:#667eea;color:#fff;border-color:#667eea}

/* ========== Feedback Button & Modal ========== */
.ra-feedback-btn{position:fixed;bottom:20px;right:20px;width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#0d1b2a,#1b263b);color:#00eaff;border:none;font-size:22px;cursor:pointer;box-shadow:0 4px 15px rgba(0,0,0,0.3);z-index:999;display:flex;align-items:center;justify-content:center;transition:all .3s}
.ra-feedback-btn:hover{transform:scale(1.1);box-shadow:0 6px 20px rgba(0,234,255,0.3)}
.ra-feedback-modal{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;display:none;align-items:center;justify-content:center;padding:16px}
.ra-feedback-modal.show{display:flex}
.ra-feedback-content{background:linear-gradient(145deg,#0d1b2a,#1b263b);border-radius:20px;padding:24px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,0.4);color:#fff;position:relative}
.ra-feedback-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.ra-feedback-header h3{font-size:18px;font-weight:700;color:#00eaff;display:flex;align-items:center;gap:8px;margin:0}
.ra-feedback-close{background:none;border:none;color:#6b7280;font-size:24px;cursor:pointer;padding:0;line-height:1}
.ra-feedback-close:hover{color:#fff}
.ra-feedback-subtitle{color:#94a3b8;font-size:14px;margin-bottom:16px;text-align:center}
.ra-feedback-cats{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.ra-feedback-cat{background:rgba(0,234,255,0.05);border:1px solid rgba(0,234,255,0.2);border-radius:12px;padding:16px;text-align:center;cursor:pointer;transition:all .2s}
.ra-feedback-cat:hover{background:rgba(0,234,255,0.1);border-color:rgba(0,234,255,0.4)}
.ra-feedback-cat i{font-size:24px;color:#00eaff;display:block;margin-bottom:8px}
.ra-feedback-cat span{font-size:13px;color:#e2e8f0;font-weight:500}
.ra-feedback-step{display:none}
.ra-feedback-step.active{display:block}
.ra-feedback-back{background:none;border:none;color:#94a3b8;font-size:14px;cursor:pointer;display:flex;align-items:center;gap:4px;padding:0;margin-bottom:16px}
.ra-feedback-back:hover{color:#00eaff}
.ra-feedback-cat-header{display:flex;align-items:center;gap:8px;margin-bottom:16px;padding:12px;background:rgba(0,234,255,0.1);border-radius:10px}
.ra-feedback-cat-header i{font-size:20px;color:#00eaff}
.ra-feedback-cat-header span{font-size:15px;font-weight:600;color:#fff}
.ra-feedback-input{width:100%;padding:12px;border:1px solid rgba(255,255,255,0.1);border-radius:10px;background:rgba(0,0,0,0.3);color:#fff;font-size:14px;font-family:inherit;resize:vertical;box-sizing:border-box}
.ra-feedback-input:focus{border-color:#00eaff;outline:none}
.ra-feedback-input::placeholder{color:#6b7280}
.ra-feedback-stars{display:flex;gap:8px;justify-content:center;margin:16px 0}
.ra-feedback-star{background:none;border:none;font-size:28px;color:#4b5563;cursor:pointer;padding:4px;transition:all .2s}
.ra-feedback-star:hover,.ra-feedback-star.active{color:#fbbf24;transform:scale(1.15)}
.ra-feedback-submit{width:100%;padding:14px;background:linear-gradient(135deg,#00eaff,#00b4d8);color:#0d1b2a;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;margin-top:16px;transition:all .2s}
.ra-feedback-submit:hover{transform:translateY(-2px);box-shadow:0 4px 15px rgba(0,234,255,0.4)}
/* Feedback Tab Styles */
.ra-feedback-cats-inline{display:flex;gap:8px;flex-wrap:wrap}
.ra-fb-cat-btn{padding:10px 16px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:1.5px solid #e5e7eb;background:#fff;color:#374151;transition:all .2s;display:inline-flex;align-items:center;gap:6px}
.ra-fb-cat-btn:hover{border-color:#667eea;color:#667eea}
.ra-fb-cat-btn.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-color:transparent}
.ra-fb-stars{display:flex;gap:8px}
.ra-fb-star{background:none;border:none;font-size:28px;color:#d1d5db;cursor:pointer;padding:4px;transition:all .2s}
.ra-fb-star:hover,.ra-fb-star.active{color:#fbbf24;transform:scale(1.1)}
.ra-fb-msg{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;display:none}
.ra-fb-msg.show{display:block}
.ra-fb-msg.error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.ra-fb-msg.success{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.ra-feedback-submit:disabled{opacity:0.6;cursor:not-allowed;transform:none}
.ra-feedback-msg{padding:12px;border-radius:10px;margin-bottom:12px;font-size:14px;text-align:center;display:none}
.ra-feedback-msg.show{display:block}
.ra-feedback-msg.error{background:rgba(239,68,68,0.2);color:#f87171}
.ra-feedback-msg.success{background:rgba(34,197,94,0.2);color:#4ade80}
.ra-feedback-success{text-align:center;padding:30px 20px}
.ra-feedback-success i{font-size:64px;color:#4ade80;margin-bottom:16px}
.ra-feedback-success h4{color:#fff;font-size:20px;margin:0 0 8px 0}
.ra-feedback-success p{color:#94a3b8;font-size:14px;margin:0}

.ra-inv-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.ra-inv-summary-card{background:#fff;border-radius:12px;padding:14px;text-align:center;border:1px solid #f0f0f0}
.ra-inv-summary-val{font-size:22px;font-weight:800;color:#111827}
.ra-inv-summary-label{font-size:11px;color:#6b7280;margin-top:2px}
.ra-inv-table{width:100%;background:#fff;border-radius:14px;border:1px solid #f0f0f0;overflow:hidden}
.ra-inv-table th{background:#f9fafb;padding:10px 14px;text-align:left;font-size:12px;font-weight:600;color:#6b7280;border-bottom:1px solid #f0f0f0}
.ra-inv-table td{padding:10px 14px;font-size:13px;color:#374151;border-bottom:1px solid #f0f0f0}
.ra-inv-table tr:last-child td{border-bottom:none}
.ra-inv-table tr:hover td{background:#f9fafb}
.ra-inv-status{display:inline-block;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:600}
.ra-inv-status-draft{background:#fef3c7;color:#92400e}
.ra-inv-status-sent{background:#dbeafe;color:#1d4ed8}
.ra-inv-status-paid{background:#d1fae5;color:#065f46}
.ra-inv-status-cancelled{background:#fecaca;color:#991b1b}
/* Sortable columns */
.ra-sortable{cursor:pointer;user-select:none;white-space:nowrap}
.ra-sortable:hover{background:#f3f4f6}
.ra-sort-icon{font-size:14px;margin-left:4px;opacity:.4;transition:opacity .2s}
.ra-sortable:hover .ra-sort-icon{opacity:.7}
.ra-sortable.ra-sort-asc .ra-sort-icon,.ra-sortable.ra-sort-desc .ra-sort-icon{opacity:1;color:#667eea}
.ra-inv-actions{display:flex;gap:6px}
.ra-inv-btn{padding:6px 10px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid #e5e7eb;background:#fff;color:#374151;transition:all .2s;display:inline-flex;align-items:center;gap:4px;text-decoration:none}
.ra-inv-btn:hover{border-color:#667eea;color:#667eea}
.ra-inv-btn-pdf{color:#dc2626;border-color:#fecaca}
.ra-inv-btn-pdf:hover{background:#fef2f2;border-color:#dc2626}
.ra-inv-btn-edit{color:#667eea;border-color:#c7d2fe;background:none;cursor:pointer;padding:5px 8px;font-size:14px}
.ra-inv-btn-edit:hover{background:#eef2ff;border-color:#667eea}
.ra-inv-btn-del{color:#dc2626;border-color:#fecaca;background:none;cursor:pointer;padding:5px 8px;font-size:14px}
.ra-inv-btn-del:hover{background:#fef2f2;border-color:#dc2626}
/* ========== Invoice Modal ========== */
.ra-modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9998;display:none;align-items:center;justify-content:center;padding:16px}
.ra-modal-overlay.show{display:flex}
.ra-modal{background:#fff;border-radius:16px;padding:24px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.15);position:relative}
.ra-modal h3{font-size:18px;font-weight:700;color:#111827;margin-bottom:4px;display:flex;align-items:center;gap:8px}
.ra-modal-sub{font-size:13px;color:#6b7280;margin-bottom:20px}
.ra-inv-lines{display:flex;flex-direction:column;gap:8px;margin-bottom:12px}
.ra-inv-line{display:flex;gap:8px;align-items:center}
.ra-inv-line-desc{flex:1;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:14px;color:#1f2937;background:#fafafa;outline:none;min-width:0}
.ra-inv-line-desc:focus{border-color:#667eea;background:#fff}
.ra-inv-line-amount{width:100px;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:14px;color:#1f2937;background:#fafafa;outline:none;text-align:right}
.ra-inv-line-amount:focus{border-color:#667eea;background:#fff}
.ra-inv-line-remove{background:none;border:none;font-size:18px;color:#d1d5db;cursor:pointer;padding:4px 8px;flex-shrink:0}
.ra-inv-line-remove:hover{color:#dc2626}
.ra-inv-add{background:none;border:1.5px dashed #d1d5db;border-radius:8px;padding:10px;font-size:13px;color:#6b7280;cursor:pointer;width:100%;text-align:center;transition:all .2s;margin-bottom:16px}
.ra-inv-add:hover{border-color:#667eea;color:#667eea}
.ra-inv-totals{border-top:1px solid #e5e7eb;padding-top:12px;margin-bottom:20px}
.ra-inv-total-row{display:flex;justify-content:space-between;padding:6px 0;font-size:14px;color:#374151}
.ra-inv-total-final{font-size:16px;font-weight:700;color:#111827;border-top:2px solid #111827;padding-top:8px;margin-top:4px}
@media(max-width:640px){
    .ra-inv-summary{grid-template-columns:1fr}
    .ra-inv-filters{flex-direction:column}
    .ra-inv-table{font-size:12px}
    .ra-inv-table th,.ra-inv-table td{padding:8px 10px}
    .ra-inv-line{flex-wrap:wrap}
    .ra-inv-line-amount{width:100%}
}
/* ===== Modern Angebot Modal ===== */
.nang-modal{max-width:660px!important;padding:0!important;border-radius:20px!important;overflow:hidden;display:flex;flex-direction:column}
.nang-header{display:flex;align-items:center;gap:14px;padding:20px 24px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;position:relative}
.nang-header-icon{width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.nang-header h3{font-size:17px;font-weight:700;color:#fff!important;margin:0}
.nang-header p{font-size:12px;color:rgba(255,255,255,.8);margin:2px 0 0}
.nang-close{position:absolute;top:16px;right:16px;background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:8px;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s}
.nang-close:hover{background:rgba(255,255,255,.3)}

/* Steps */
.nang-steps{display:flex;align-items:center;gap:0;padding:14px 24px;background:#f8fafc;border-bottom:1px solid #e5e7eb}
.nang-step{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;color:#94a3b8;transition:color .2s}
.nang-step.active{color:#059669;font-weight:600}
.nang-step-num{width:24px;height:24px;border-radius:50%;background:#e2e8f0;color:#64748b;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;transition:all .2s}
.nang-step.active .nang-step-num{background:#059669;color:#fff}
.nang-step.completed .nang-step-num{background:#059669;color:#fff}
.nang-step-line{flex:1;height:2px;background:#e2e8f0;margin:0 12px}

/* Sections */
.nang-section{padding:20px 24px 24px;animation:nangFadeIn .25s ease;overflow-y:auto;flex:1}
@keyframes nangFadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* Search */
.nang-search-wrap{display:flex;align-items:center;gap:10px;background:#f1f5f9;border:2px solid #e2e8f0;border-radius:12px;padding:0 14px;margin-bottom:4px;transition:border-color .2s,box-shadow .2s}
.nang-search-wrap:focus-within{border-color:#059669;box-shadow:0 0 0 3px rgba(5,150,105,.1);background:#fff}
.nang-search-wrap i{color:#94a3b8;font-size:18px;flex-shrink:0}
.nang-search-wrap input{flex:1;padding:12px 0;border:none;background:transparent;font-size:14px;font-family:inherit;color:#1e293b;outline:none}
.nang-search-wrap kbd{background:#e2e8f0;border-radius:4px;padding:2px 6px;font-size:10px;color:#64748b;font-family:inherit;border:none;flex-shrink:0}

.nang-search-results{display:none;background:#fff;border:1px solid #e2e8f0;border-radius:12px;max-height:200px;overflow-y:auto;margin-bottom:8px;box-shadow:0 8px 24px rgba(0,0,0,.08)}
.nang-search-results.show{display:block}
.nang-search-result{padding:12px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:background .1s}
.nang-search-result:last-child{border-bottom:none}
.nang-search-result:hover{background:#f0fdf4}
.nang-search-result strong{font-size:14px;color:#1e293b}
.nang-search-result .nang-sr-company{color:#64748b;font-size:13px;margin-left:6px}
.nang-search-result .nang-sr-meta{font-size:12px;color:#94a3b8;margin-top:2px}

/* Fields */
.nang-fields{margin-top:16px}
.nang-field-row{display:flex;gap:12px;margin-bottom:12px}
.nang-field{display:flex;flex-direction:column;gap:4px}
.nang-field-grow{flex:1}
.nang-field label{font-size:12px;font-weight:600;color:#64748b;letter-spacing:.3px;text-transform:uppercase}
.nang-input-wrap{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:0 12px;transition:all .2s}
.nang-input-wrap:focus-within{border-color:#059669;background:#fff;box-shadow:0 0 0 3px rgba(5,150,105,.08)}
.nang-input-wrap i{color:#94a3b8;font-size:16px;flex-shrink:0}
.nang-input-wrap input{flex:1;padding:10px 0;border:none;background:transparent;font-size:14px;font-family:inherit;color:#1e293b;outline:none}
.nang-field-hint{font-size:11px;color:#94a3b8;min-height:16px;padding-left:2px}

/* Next button */
.nang-next-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;font-family:inherit;cursor:pointer;margin-top:20px;transition:all .2s;box-shadow:0 2px 8px rgba(5,150,105,.25)}
.nang-next-btn:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(5,150,105,.35)}

/* Back link */
.nang-back-link{display:inline-flex;align-items:center;gap:6px;background:#f1f5f9;border:1.5px solid #e2e8f0;color:#475569;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;padding:8px 16px 8px 12px;margin-bottom:16px;border-radius:8px;transition:all .15s}
.nang-back-link:hover{background:#e2e8f0;border-color:#cbd5e1;color:#1e293b}
.nang-back-link:active{transform:scale(.97)}

/* Customer chip */
.nang-customer-chip{display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;margin-bottom:16px;font-size:13px;color:#166534}
.nang-customer-chip i{font-size:16px}
.nang-customer-chip .nang-cc-name{font-weight:600}

/* Line items */
.nang-positions-header{display:flex;align-items:center;gap:8px;padding:0 8px 8px;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px}
.nang-ph-desc{flex:1;min-width:0}
.nang-ph-qty{width:52px;text-align:center}
.nang-ph-price{width:90px;text-align:right}
.nang-ph-total{width:80px;text-align:right}
.nang-ph-del{width:36px}

.nang-lines{display:flex;flex-direction:column;gap:8px;margin-bottom:12px}
.nang-line{display:flex;align-items:center;gap:8px;padding:10px 10px 10px 14px;background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;transition:all .2s;animation:nangFadeIn .2s ease}
.nang-line:hover{border-color:#cbd5e1;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.nang-line-desc{flex:1;min-width:0;padding:8px 0;border:none;background:transparent;font-size:14px;font-family:inherit;color:#1e293b;outline:none}
.nang-line-desc::placeholder{color:#cbd5e1}
.nang-line-qty{width:52px;padding:8px 4px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;color:#1e293b;text-align:center;background:#f8fafc;outline:none;-moz-appearance:textfield}
.nang-line-qty::-webkit-inner-spin-button{-webkit-appearance:none}
.nang-line-qty:focus{border-color:#059669;background:#fff}
.nang-line-price-wrap{display:flex;align-items:center;width:90px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;overflow:hidden;transition:border-color .2s}
.nang-line-price-wrap:focus-within{border-color:#059669;background:#fff}
.nang-line-price{flex:1;width:100%;padding:8px 4px 8px 8px;border:none;background:transparent;font-size:13px;font-family:inherit;color:#1e293b;text-align:right;outline:none;-moz-appearance:textfield}
.nang-line-price::-webkit-inner-spin-button{-webkit-appearance:none}
.nang-line-currency{padding:0 8px 0 2px;font-size:13px;color:#94a3b8;flex-shrink:0}
.nang-line-total{width:80px;text-align:right;font-size:13px;font-weight:600;color:#1e293b;flex-shrink:0}
.nang-line-del{background:none;border:none;color:#d1d5db;cursor:pointer;padding:6px;border-radius:6px;font-size:16px;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
.nang-line-del:hover{color:#ef4444;background:#fef2f2}

.nang-add-line{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:12px;background:none;border:2px dashed #d1d5db;border-radius:12px;font-size:13px;font-weight:500;font-family:inherit;color:#64748b;cursor:pointer;transition:all .2s}
.nang-add-line:hover{border-color:#059669;color:#059669;background:#f0fdf4}

/* Totals card */
.nang-totals-card{background:linear-gradient(135deg,#f8fafc,#f1f5f9);border:1px solid #e2e8f0;border-radius:14px;padding:16px 18px;margin:16px 0}
.nang-totals-row{display:flex;justify-content:space-between;padding:6px 0;font-size:14px;color:#475569}
.nang-totals-final{font-size:18px;font-weight:700;color:#059669;border-top:2px solid #059669;padding-top:10px;margin-top:6px}

/* Meta row */
.nang-meta-row{display:flex;gap:12px;margin-bottom:20px}
.nang-meta-row label{display:flex;align-items:center;gap:4px}
.nang-meta-row label i{font-size:14px}
.nang-meta-input{width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:inherit;color:#1e293b;background:#f8fafc;outline:none;transition:all .2s}
.nang-meta-input:focus{border-color:#059669;background:#fff;box-shadow:0 0 0 3px rgba(5,150,105,.08)}

/* Action buttons */
.nang-actions{display:flex;gap:12px}
.nang-cancel-btn{flex:1;padding:14px;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;border-radius:12px;font-size:14px;font-weight:500;font-family:inherit;cursor:pointer;transition:all .15s}
.nang-cancel-btn:hover{background:#e2e8f0;color:#475569}
.nang-submit-btn{flex:2;display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;font-family:inherit;cursor:pointer;transition:all .2s;box-shadow:0 2px 8px rgba(5,150,105,.25)}
.nang-submit-btn:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(5,150,105,.35)}

/* Responsive */
@media(max-width:640px){
    .nang-modal{border-radius:16px!important}
    .nang-header{padding:16px 18px}
    .nang-section{padding:16px 18px 20px}
    .nang-field-row{flex-direction:column;gap:10px}
    .nang-positions-header{display:none}
    .nang-line{flex-wrap:wrap;padding:12px}
    .nang-line-desc{width:100%;flex:none;border-bottom:1px solid #f1f5f9;padding-bottom:10px;margin-bottom:4px}
    .nang-line-qty{width:48px}
    .nang-line-price-wrap{flex:1}
    .nang-line-total{width:auto}
    .nang-meta-row{flex-direction:column}
    .nang-steps{padding:10px 18px}
    .nang-step{font-size:12px}
}

/* ===== Invoice Modal (blue variant) ===== */
.ninv-modal .nang-step.active .nang-step-num{background:#2563eb}
.ninv-modal .nang-step.completed .nang-step-num{background:#2563eb}
.ninv-focus:focus-within{border-color:#2563eb!important;box-shadow:0 0 0 3px rgba(37,99,235,.08)!important}
.ninv-modal .nang-search-wrap:focus-within{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.ninv-modal .nang-line-qty:focus{border-color:#2563eb}
.ninv-modal .nang-line-price-wrap:focus-within{border-color:#2563eb}
.ninv-modal .nang-add-line:hover{border-color:#2563eb;color:#2563eb;background:#eff6ff}
.ninv-modal .nang-back-link{background:#eff6ff;border-color:#bfdbfe;color:#2563eb}
.ninv-modal .nang-back-link:hover{background:#dbeafe;border-color:#93c5fd;color:#1d4ed8}
.ninv-modal .nang-meta-input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.08)}
.ninv-options{margin:12px 0 16px}
.ninv-checkbox-label{display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;color:#475569;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;transition:all .2s}
.ninv-checkbox-label:hover{background:#eff6ff;border-color:#bfdbfe}
.ninv-checkbox-label input[type="checkbox"]{display:none}
.ninv-checkmark{width:20px;height:20px;border:2px solid #d1d5db;border-radius:6px;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0;font-size:14px;color:transparent}
.ninv-checkbox-label input:checked~.ninv-checkmark{background:#2563eb;border-color:#2563eb;color:#fff}
.ninv-discount-section{margin:0 0 12px;padding:14px;background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border:1.5px solid #86efac;border-radius:10px}
.ninv-discount-header{display:flex;align-items:center;gap:6px;margin-bottom:10px;font-size:13px;font-weight:700;color:#059669}
.ninv-discount-header i{font-size:16px}
.ninv-discount-row{display:flex;gap:10px;align-items:center}
.ninv-discount-remove{width:28px;height:28px;border:none;background:#fee2e2;color:#dc2626;border-radius:6px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s}
.ninv-discount-remove:hover{background:#dc2626;color:#fff}
.ninv-skip-btn{padding:10px 18px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;border:1.5px solid #bbf7d0;background:#f0fdf4;color:#16a34a;transition:all .2s;white-space:nowrap}
.ninv-skip-btn:hover{background:#16a34a;color:#fff;border-color:#16a34a}
.ninv-step1-actions{display:flex;gap:10px;margin-top:12px;padding-top:16px;border-top:1px dashed #e2e8f0}

/* Language Switcher */
.ra-lang-wrap{position:relative}
.ra-lang-toggle{display:flex;align-items:center;gap:4px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:5px 10px;font-size:11px;font-weight:700;color:#6b7280;cursor:pointer;font-family:inherit;transition:all .2s}
.ra-lang-toggle:hover{background:#e5e7eb;color:#374151}
.ra-lang-toggle i{font-size:14px}
.ra-lang-opts{display:none;position:absolute;top:100%;right:0;margin-top:4px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.1);overflow:hidden;min-width:52px;z-index:100}
.ra-lang-wrap.open .ra-lang-opts{display:block}
.ra-lang-opt{display:block;padding:6px 12px;font-size:11px;font-weight:700;color:#6b7280;cursor:pointer;border:none;background:none;width:100%;text-align:center;font-family:inherit;letter-spacing:.5px;transition:all .15s}
.ra-lang-opt:hover{background:#f0f2ff;color:#4338ca}
.ra-lang-opt.active{color:#667eea}
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
                <div class="ra-subtitle">' . esc_html(PPV_Lang::t('repair_admin_title')) . '</div>
            </div>';

        // Filiale switcher (only if multiple filialen exist OR premium)
        if ($has_filialen || $parent_is_premium) {
            echo '<div class="ra-filiale-switch" style="position:relative;margin-left:8px">
                <button type="button" id="ra-filiale-btn" class="ra-btn ra-btn-outline" style="font-size:12px;padding:6px 12px;gap:6px">
                    <i class="ri-building-2-line"></i>
                    <span id="ra-filiale-current">' . esc_html($store->city ?: $store->name) . '</span>
                    <i class="ri-arrow-down-s-line" style="font-size:14px"></i>
                </button>
                <div id="ra-filiale-dropdown" class="ra-hidden" style="position:absolute;top:100%;left:0;z-index:50;min-width:220px;background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.12);border:1px solid #e5e7eb;margin-top:4px;overflow:hidden">';

            foreach ($filialen as $f) {
                $is_current = ($f->id == $store->id);
                $f_label = esc_html($f->name . ($f->city ? " ({$f->city})" : ''));
                $f_is_parent = empty($f->parent_store_id);
                echo '<button type="button" class="ra-filiale-item' . ($is_current ? ' active' : '') . '" data-id="' . intval($f->id) . '" style="display:flex;align-items:center;gap:8px;width:100%;padding:10px 14px;border:none;background:' . ($is_current ? '#f0f4ff' : '#fff') . ';cursor:pointer;font-size:13px;color:#374151;text-align:left;transition:background .15s;font-family:inherit">
                    <i class="' . ($f_is_parent ? 'ri-home-4-line' : 'ri-building-2-line') . '" style="color:#667eea;font-size:15px"></i>
                    <span style="flex:1;' . ($is_current ? 'font-weight:600;color:#4338ca' : '') . '">' . $f_label . '</span>
                    ' . ($is_current ? '<i class="ri-check-line" style="color:#667eea"></i>' : '') . '
                </button>';
            }

            // Add new filiale button (premium only)
            if ($parent_is_premium && count($filialen) < $max_filialen) {
                echo '<div style="border-top:1px solid #e5e7eb;padding:8px">
                    <button type="button" id="ra-filiale-add-btn" style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 10px;border:none;background:none;cursor:pointer;font-size:13px;color:#667eea;font-weight:600;border-radius:8px;transition:background .15s;font-family:inherit" onmouseover="this.style.background=\'#f0f4ff\'" onmouseout="this.style.background=\'none\'">
                        <i class="ri-add-circle-line" style="font-size:16px"></i> ' . esc_html(PPV_Lang::t('repair_admin_filiale_add')) . '
                    </button>
                </div>';
            }

            echo '</div></div>';
        }

        echo '</div>
        <div class="ra-header-right">
            <div class="ra-lang-wrap" id="ra-lang-wrap">
                <button class="ra-lang-toggle" onclick="this.parentElement.classList.toggle(\'open\')">
                    <i class="ri-global-line"></i> ' . strtoupper($lang) . '
                </button>
                <div class="ra-lang-opts">';
        foreach (['de','en','hu','ro'] as $lc) {
            echo '<button class="ra-lang-opt ' . ($lang === $lc ? 'active' : '') . '" data-lang="' . $lc . '">' . strtoupper($lc) . '</button>';
        }
        echo '</div></div>
            <a href="' . esc_url($form_url) . '" target="_blank" class="ra-btn ra-btn-outline">
                <i class="ri-external-link-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_view_form')) . '
            </a>
            <button class="ra-btn-icon" id="ra-settings-toggle-btn" title="' . esc_attr(PPV_Lang::t('repair_admin_settings')) . '">
                <i class="ri-settings-3-line"></i>
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="ra-stats">
        <div class="ra-stat-card">
            <div class="ra-stat-value">' . $total_repairs . '</div>
            <div class="ra-stat-label">' . esc_html(PPV_Lang::t('repair_admin_stat_total')) . '</div>
        </div>
        <div class="ra-stat-card ra-stat-highlight">
            <div class="ra-stat-value">' . $open_repairs . '</div>
            <div class="ra-stat-label">' . esc_html(PPV_Lang::t('repair_admin_stat_open')) . '</div>
        </div>
        <div class="ra-stat-card">
            <div class="ra-stat-value">' . $today_repairs . '</div>
            <div class="ra-stat-label">' . esc_html(PPV_Lang::t('repair_admin_stat_today')) . '</div>
        </div>
        <div class="ra-stat-card">
            <div class="ra-stat-value">' . $total_customers . '</div>
            <div class="ra-stat-label">' . esc_html(PPV_Lang::t('repair_admin_stat_customers')) . '</div>
        </div>
    </div>';

        // Check if has active subscription for usage display
        $sub_status_check = $store->subscription_status ?? 'trial';
        $sub_expires_check = $store->subscription_expires_at ?? null;
        $is_expired_check = $sub_expires_check && strtotime($sub_expires_check) < time();
        $has_active_subscription = $is_premium || ($sub_status_check === 'active' && !$is_expired_check);

        // Usage bar or premium badge
        if (!$has_active_subscription) {
            $limit_reached_now = ($store->repair_form_count >= $store->repair_form_limit);

            // Show prominent upgrade banner when limit reached
            if ($limit_reached_now) {
                echo '<div class="ra-upgrade-banner" style="background:linear-gradient(135deg,#dc2626,#991b1b);border-radius:14px;padding:20px 24px;margin-bottom:16px;color:#fff;text-align:center">
                    <div style="font-size:24px;margin-bottom:8px"><i class="ri-error-warning-line"></i></div>
                    <h3 style="font-size:18px;font-weight:700;margin-bottom:8px">' . esc_html(PPV_Lang::t('repair_admin_limit_reached')) . '</h3>
                    <p style="font-size:14px;opacity:0.9;margin-bottom:16px">' . esc_html(PPV_Lang::t('repair_admin_limit_text')) . '</p>
                    <a href="/checkout" class="ra-btn" style="background:#fff;color:#dc2626;padding:12px 24px;font-size:15px">
                        <i class="ri-vip-crown-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_upgrade_price')) . '
                    </a>
                </div>';
            } else {
                echo '<div class="ra-usage">
            <div class="ra-usage-info">
                <span>Formulare: ' . intval($store->repair_form_count) . ' / ' . intval($store->repair_form_limit) . '</span>';
                if ($limit_pct >= 80) {
                    echo '<span class="ra-usage-warn"><a href="/checkout" style="color:#dc2626;text-decoration:underline">' . esc_html(PPV_Lang::t('repair_admin_upgrade_now')) . '</a></span>';
                }
                echo '</div>
            <div class="ra-usage-bar">
                <div class="ra-usage-fill" style="width:' . $limit_pct . '%"></div>
            </div>
        </div>';
            }
        } else {
            echo '<div class="ra-premium-badge"><i class="ri-vip-crown-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_premium_active')) . '</div>';
        }

        // Formular-Link card
        echo '<div class="ra-link-card">
        <div class="ra-link-label">' . esc_html(PPV_Lang::t('repair_admin_form_link')) . '</div>
        <div class="ra-link-row">
            <input type="text" value="' . esc_attr($form_url) . '" readonly class="ra-link-input" id="ra-form-url">
            <button class="ra-btn-copy" id="ra-copy-btn" title="' . esc_attr(PPV_Lang::t('repair_admin_copy_link')) . '">
                <i class="ri-file-copy-line"></i>
            </button>
            <button class="ra-btn-tips" id="ra-tips-btn" onclick="openKioskTips()" title="' . esc_attr(PPV_Lang::t('repair_admin_kiosk_tips')) . '">
                <i class="ri-lightbulb-line"></i>
            </button>
        </div>
    </div>';

        // Partner directory section (all active partners) — temporarily disabled, will return later
        /* COMMENTED OUT - Recommended Suppliers section
        if (!empty($all_partners)) {
            $partner_count = count($all_partners);
            echo '<div class="ra-suppliers">
        <div class="ra-suppliers-header">
            <div class="ra-suppliers-title">
                <i class="ri-store-3-line"></i>
                <span>' . esc_html(PPV_Lang::t('repair_admin_suppliers_title', 'Empfohlene Lieferanten')) . '</span>
                <span class="ra-suppliers-count">' . $partner_count . '</span>
            </div>
            <button class="ra-suppliers-toggle" id="ra-suppliers-toggle" onclick="toggleSuppliers()">
                <i class="ri-arrow-down-s-line" id="ra-suppliers-arrow"></i>
            </button>
        </div>
        <div class="ra-suppliers-body" id="ra-suppliers-body">
            <div class="ra-suppliers-scroll">';

            foreach ($all_partners as $p) {
                $p_name = esc_html($p->company_name);
                $p_logo = esc_url($p->logo_url ?? '');
                $p_web  = esc_url($p->website ?? '');
                $p_city = esc_html($p->city ?? '');
                $is_my  = ($p->id == $my_partner_id);

                echo '<div class="ra-supplier-card' . ($is_my ? ' ra-supplier-mine' : '') . '">';
                if ($is_my) {
                    echo '<div class="ra-supplier-badge"><i class="ri-handshake-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_partner_tag', 'Ihr Partner')) . '</div>';
                }
                if ($p_logo) {
                    echo '<div class="ra-supplier-logo-wrap"><img src="' . $p_logo . '" alt="' . $p_name . '" class="ra-supplier-logo"></div>';
                } else {
                    echo '<div class="ra-supplier-logo-wrap"><div class="ra-supplier-logo-ph"><i class="ri-building-2-line"></i></div></div>';
                }
                echo '<div class="ra-supplier-name">' . $p_name . '</div>';
                if ($p_city) {
                    echo '<div class="ra-supplier-city"><i class="ri-map-pin-2-line"></i> ' . $p_city . '</div>';
                }
                if ($p_web) {
                    echo '<a href="' . $p_web . '" target="_blank" rel="noopener" class="ra-supplier-btn">
                        <i class="ri-external-link-line"></i> Webshop
                    </a>';
                }
                echo '</div>';
            }

            echo '</div></div></div>';
        }
        END COMMENTED OUT */

        // Settings panel
        echo '<div id="ra-settings" class="ra-settings ra-hidden">
        <h3><i class="ri-settings-3-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_settings')) . '</h3>

        <!-- Settings Tabs Navigation -->
        <div class="ra-settings-tabs" id="ra-settings-tabs">
            <button type="button" class="ra-settings-tab active" data-panel="general"><i class="ri-store-2-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_tab_general')) . '</button>
            <button type="button" class="ra-settings-tab" data-panel="form"><i class="ri-edit-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_tab_form')) . '</button>
            <button type="button" class="ra-settings-tab" data-panel="invoice"><i class="ri-file-list-3-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_tab_invoices')) . '</button>
            <button type="button" class="ra-settings-tab" data-panel="email"><i class="ri-mail-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_tab_emails')) . '</button>
            <button type="button" class="ra-settings-tab" data-panel="punktepass"><i class="ri-star-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_tab_pp')) . '</button>
            <button type="button" class="ra-settings-tab" data-panel="abo"><i class="ri-vip-crown-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_tab_abo')) . '</button>
            <button type="button" class="ra-settings-tab" data-panel="filialen"><i class="ri-building-2-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_tab_filialen')) . '</button>
            <button type="button" class="ra-settings-tab" data-panel="widget"><i class="ri-code-s-slash-line"></i> Widget</button>
        </div>

        <form id="ra-settings-form">
            <!-- ==================== PANEL: Allgemein ==================== -->
            <div class="ra-settings-panel active" data-panel="general">
                <h4><i class="ri-store-2-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_business_data')) . '</h4>
                <div class="ra-settings-grid">
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_company_name')) . '</label>
                    <input type="text" name="name" value="' . esc_attr($store->name) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_owner_name')) . '</label>
                    <input type="text" name="repair_owner_name" value="' . esc_attr($store->repair_owner_name) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_address')) . '</label>
                    <input type="text" name="address" value="' . esc_attr($store->address) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_zip')) . '</label>
                    <input type="text" name="plz" value="' . esc_attr($store->plz) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_city')) . '</label>
                    <input type="text" name="city" value="' . esc_attr($store->city) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_country')) . '</label>
                    <select name="country">';

        $eu_countries = [
            'DE' => 'Deutschland', 'AT' => 'Österreich', 'CH' => 'Schweiz',
            'HU' => 'Magyarország', 'RO' => 'România',
            'BE' => 'Belgien', 'BG' => 'Bulgarien', 'HR' => 'Kroatien',
            'CY' => 'Zypern', 'CZ' => 'Tschechien', 'DK' => 'Dänemark',
            'EE' => 'Estland', 'FI' => 'Finnland', 'FR' => 'Frankreich',
            'GR' => 'Griechenland', 'IE' => 'Irland', 'IT' => 'Italien',
            'LV' => 'Lettland', 'LT' => 'Litauen', 'LU' => 'Luxemburg',
            'MT' => 'Malta', 'NL' => 'Niederlande', 'PL' => 'Polen',
            'PT' => 'Portugal', 'SK' => 'Slowakei', 'SI' => 'Slowenien',
            'ES' => 'Spanien', 'SE' => 'Schweden',
            'GB' => 'United Kingdom', 'NO' => 'Norwegen',
        ];
        $current_country = $store->country ?? '';
        foreach ($eu_countries as $code => $name) {
            echo '<option value="' . $code . '"' . ($current_country === $code ? ' selected' : '') . '>' . esc_html($name) . '</option>';
        }

        echo '</select>
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_phone')) . '</label>
                    <input type="text" name="phone" value="' . esc_attr($store->phone) . '">
                </div>';

        echo '
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_vat_id')) . '</label>
                    <input type="text" name="repair_tax_id" value="' . esc_attr($store->repair_tax_id) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_login_email')) . ' <small style="color:#6b7280">(' . esc_html(PPV_Lang::t('repair_admin_login_email_hint')) . ')</small></label>
                    <input type="email" name="email" value="' . esc_attr($store->email) . '" required>
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_accent_color')) . '</label>
                    <input type="color" name="repair_color" value="' . $store_color . '">
                </div>
            </div>

            <!-- Logo upload -->
            <div class="ra-logo-section">
                <label>' . esc_html(PPV_Lang::t('repair_admin_logo')) . '</label>
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
                        <i class="ri-upload-2-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_upload_logo')) . '
                    </button>
                </div>
            </div>


                <h4><i class="ri-file-list-3-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_legal_title')) . ' <small style="font-weight:normal;color:#6b7280">(' . esc_html(PPV_Lang::t('repair_admin_legal_optional')) . ')</small></h4>
            <p style="font-size:12px;color:#6b7280;margin-bottom:12px">' . esc_html(PPV_Lang::t('repair_admin_legal_hint')) . '</p>
            <div class="ra-settings-grid">
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_imprint_company')) . '</label>
                    <input type="text" name="repair_company_name" value="' . esc_attr($store->repair_company_name) . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_imprint_ph')) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_diff_address')) . '</label>
                    <input type="text" name="repair_company_address" value="' . esc_attr($store->repair_company_address) . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_diff_hint')) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_diff_phone')) . '</label>
                    <input type="text" name="repair_company_phone" value="' . esc_attr($store->repair_company_phone) . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_diff_short')) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_diff_email')) . '</label>
                    <input type="text" name="repair_company_email" value="' . esc_attr($store->repair_company_email) . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_diff_short')) . '">
                </div>
            </div>
            </div><!-- END PANEL: general -->

            <!-- ==================== PANEL: PunktePass ==================== -->
            <div class="ra-settings-panel" data-panel="punktepass">
                <h4><i class="ri-star-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_pp_title')) . '</h4>
            <div class="ra-toggle">
                <label class="ra-toggle-switch">
                    <input type="checkbox" id="ra-pp-toggle" ' . ($pp_enabled ? 'checked' : '') . '>
                    <span class="ra-toggle-slider"></span>
                </label>
                <div>
                    <div class="ra-toggle-label">' . esc_html(PPV_Lang::t('repair_admin_pp_enable')) . '</div>
                    <div class="ra-toggle-desc">' . esc_html(PPV_Lang::t('repair_admin_pp_desc')) . '</div>
                </div>
            </div>
            <input type="hidden" name="repair_punktepass_enabled" id="ra-pp-enabled-val" value="' . $pp_enabled . '">

            <div id="ra-pp-settings" ' . ($pp_enabled ? '' : 'style="display:none"') . '>
                <div class="ra-settings-grid">
                    <div class="field">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_pp_per_form')) . '</label>
                        <input type="number" name="repair_points_per_form" value="' . intval($store->repair_points_per_form) . '" min="0" max="50">
                    </div>
                    <div class="field">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_pp_required')) . '</label>
                        <input type="number" name="repair_required_points" value="' . $required_points . '" min="1" max="100">
                    </div>
                    <div class="field">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_pp_reward')) . '</label>
                        <input type="text" name="repair_reward_name" value="' . $reward_name . '">
                    </div>
                    <div class="field">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_pp_reward_desc')) . '</label>
                        <input type="text" name="repair_reward_description" value="' . $reward_desc . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_pp_reward_ph')) . '">
                    </div>
                </div>
                <hr style="border:none;border-top:1px solid #f0f0f0;margin:16px 0">
                <label style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:8px">' . esc_html(PPV_Lang::t('repair_admin_pp_redemption')) . '</label>
                <div class="ra-settings-grid">
                    <div class="field">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_pp_type')) . '</label>
                        <select name="repair_reward_type" class="ra-status-select" style="max-width:none" id="ra-reward-type">
                            <option value="discount_fixed" ' . ($reward_type === 'discount_fixed' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_pp_discount_fixed')) . '</option>
                            <option value="discount_percent" ' . ($reward_type === 'discount_percent' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_pp_discount_pct')) . '</option>
                            <option value="free_product" ' . ($reward_type === 'free_product' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_pp_free_product')) . '</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_pp_value')) . '</label>
                        <input type="number" name="repair_reward_value" value="' . $reward_value . '" min="0" step="0.01" placeholder="10.00">
                    </div>
                    <div class="field" id="ra-reward-product-field" ' . ($reward_type === 'free_product' ? '' : 'style="display:none"') . '>
                        <label>' . esc_html(PPV_Lang::t('repair_admin_pp_product_name')) . '</label>
                        <input type="text" name="repair_reward_product" value="' . $reward_product . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_pp_product_ph')) . '">
                    </div>
                </div>
            </div>
            </div><!-- END PANEL: punktepass -->

            <!-- ==================== PANEL: Formular ==================== -->
            <div class="ra-settings-panel" data-panel="form">
                <h4><i class="ri-edit-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_form_customize')) . '</h4>
            <div class="ra-settings-grid">
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_form_title')) . '</label>
                    <input type="text" name="repair_form_title" value="' . $form_title . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_form_title_ph')) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_form_subtitle')) . '</label>
                    <input type="text" name="repair_form_subtitle" value="' . $form_subtitle . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_form_optional')) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_service_type')) . '</label>
                    <select name="repair_service_type" class="ra-status-select" style="max-width:none">
                        <option value="Allgemein" ' . ($service_type === 'Allgemein' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_svc_general')) . '</option>
                        <option value="Handy-Reparatur" ' . ($service_type === 'Handy-Reparatur' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_svc_handy')) . '</option>
                        <option value="Computer-Reparatur" ' . ($service_type === 'Computer-Reparatur' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_svc_computer')) . '</option>
                        <option value="Fahrrad-Reparatur" ' . ($service_type === 'Fahrrad-Reparatur' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_svc_bicycle')) . '</option>
                        <option value="KFZ-Reparatur" ' . ($service_type === 'KFZ-Reparatur' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_svc_car')) . '</option>
                        <option value="Schmuck-Reparatur" ' . ($service_type === 'Schmuck-Reparatur' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_svc_jewelry')) . '</option>
                        <option value="Elektro-Reparatur" ' . ($service_type === 'Elektro-Reparatur' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_svc_electrical')) . '</option>
                        <option value="Uhren-Reparatur" ' . ($service_type === 'Uhren-Reparatur' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_svc_watch')) . '</option>
                        <option value="Schuh-Reparatur" ' . ($service_type === 'Schuh-Reparatur' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_svc_shoe')) . '</option>
                        <option value="Sonstige" ' . ($service_type === 'Sonstige' ? 'selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_svc_other')) . '</option>
                    </select>
                </div>
            </div>

            <div style="margin-top:20px">
                <label style="display:block;font-size:13px;font-weight:700;color:#374151;margin-bottom:8px">' . esc_html(PPV_Lang::t('repair_admin_form_fields')) . '</label>
                <div class="ra-fb" id="ra-form-builder">
                    <div class="ra-fb-toolbar">
                        <span class="ra-fb-toolbar-title"><i class="ri-eye-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_fc_live_preview')) . '</span>
                        <span class="ra-fb-toolbar-hint"><i class="ri-drag-move-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_fc_drag_hint')) . '</span>
                    </div>
                    <div class="ra-fb-body">
                    <div class="ra-fb-main"><div class="ra-fb-preview">';

// Helper to render a built-in field block
$fb_all_builtins = [
    'customer_phone'  => ['section' => 1, 'icon' => 'ri-phone-line',    'ph' => PPV_Lang::t('repair_phone_placeholder'),   'input' => 'tel'],
    'customer_address'=> ['section' => 1, 'icon' => 'ri-map-pin-line',  'ph' => PPV_Lang::t('repair_address_placeholder'), 'input' => 'text'],
    'device_brand'    => ['section' => 2, 'icon' => 'ri-smartphone-line','ph' => PPV_Lang::t('repair_brand_label'),  'input' => 'text'],
    'device_model'    => ['section' => 2, 'icon' => 'ri-device-line',   'ph' => PPV_Lang::t('repair_model_label'),  'input' => 'text'],
    'device_imei'     => ['section' => 2, 'icon' => 'ri-barcode-line',  'ph' => PPV_Lang::t('repair_imei_label'),   'input' => 'text'],
    'device_pattern'  => ['section' => 2, 'icon' => 'ri-lock-password-line','ph' => PPV_Lang::t('repair_pin_label'),'input' => 'text'],
    'muster_image'    => ['section' => 2, 'icon' => 'ri-grid-line',     'ph' => PPV_Lang::t('repair_pattern_label'),'input' => 'muster'],
    'device_color'    => ['section' => 2, 'icon' => 'ri-palette-line',  'ph' => '',    'input' => 'color'],
    'purchase_date'   => ['section' => 2, 'icon' => 'ri-calendar-line', 'ph' => '',    'input' => 'date'],
    'condition_check' => ['section' => 2, 'icon' => 'ri-shield-check-line','ph' => '', 'input' => 'condition'],
    // KFZ / Vehicle fields
    'vehicle_plate'       => ['section' => 2, 'icon' => 'ri-car-line',       'ph' => PPV_Lang::t('repair_vehicle_plate_placeholder'), 'input' => 'text'],
    'vehicle_vin'         => ['section' => 2, 'icon' => 'ri-fingerprint-line','ph' => PPV_Lang::t('repair_vehicle_vin_placeholder'),   'input' => 'text'],
    'vehicle_mileage'     => ['section' => 2, 'icon' => 'ri-dashboard-3-line','ph' => PPV_Lang::t('repair_vehicle_mileage_placeholder'),'input' => 'number'],
    'vehicle_first_reg'   => ['section' => 2, 'icon' => 'ri-calendar-check-line','ph' => PPV_Lang::t('repair_vehicle_first_reg_placeholder'),'input' => 'text'],
    'vehicle_tuev'        => ['section' => 2, 'icon' => 'ri-shield-star-line','ph' => PPV_Lang::t('repair_vehicle_tuev_placeholder'), 'input' => 'text'],
    'condition_check_kfz' => ['section' => 2, 'icon' => 'ri-car-washing-line','ph' => '', 'input' => 'condition_kfz'],
    // PC / Computer fields
    'condition_check_pc' => ['section' => 2, 'icon' => 'ri-computer-line','ph' => '', 'input' => 'condition_pc'],
    'accessories'     => ['section' => 3, 'icon' => 'ri-checkbox-multiple-line','ph' => '', 'input' => 'accessories'],
    'photo_upload'    => ['section' => 3, 'icon' => 'ri-camera-line',   'ph' => '',    'input' => 'photo'],
    'priority'        => ['section' => 3, 'icon' => 'ri-flashlight-line','ph' => '',   'input' => 'priority'],
    'cost_limit'      => ['section' => 3, 'icon' => 'ri-money-euro-circle-line','ph'=>'','input' => 'cost'],
];

// Render a single built-in field block
function ppv_fb_render_field($fk, $meta, $fc, $fc_defaults) {
    $enabled = !empty($fc['enabled']);
    $label = esc_attr($fc['label'] ?? $fc_defaults[$fk]['label']);
    $required = !empty($fc['required']);
    $hidden = !$enabled ? ' ra-fb-hidden' : '';

    $html = '<div class="ra-fb-field' . $hidden . '" data-key="' . $fk . '" data-builtin="1" draggable="true">
        <i class="ri-draggable ra-fb-drag"></i>
        <div class="ra-fb-controls">
            <button type="button" class="ra-fb-ctrl-btn ra-fb-btn-settings" title="Einstellungen"><i class="ri-settings-3-line"></i></button>
            <button type="button" class="ra-fb-ctrl-btn danger ra-fb-field-remove" title="Entfernen"><i class="ri-close-line"></i></button>
        </div>
        <div class="ra-fb-field-body">
            <label data-label-for="' . $fk . '">' . esc_html($label) . ($required ? '<span class="ra-fb-req">*</span>' : '') . '</label>';

    $input = $meta['input'];
    if ($input === 'muster') {
        $html .= '<div class="ra-fb-muster-preview"><i class="ri-grid-line"></i></div>';
    } elseif ($input === 'accessories') {
        $html .= '<div><span class="ra-fb-checkbox-preview"><i class="ri-checkbox-blank-line"></i> ' . esc_html(PPV_Lang::t('repair_acc_charger')) . '</span><span class="ra-fb-checkbox-preview"><i class="ri-checkbox-blank-line"></i> ' . esc_html(PPV_Lang::t('repair_acc_case')) . '</span><span class="ra-fb-checkbox-preview"><i class="ri-checkbox-blank-line"></i> ' . esc_html(PPV_Lang::t('repair_acc_other')) . '</span></div>';
    } elseif ($input === 'photo') {
        $html .= '<div class="ra-fb-photo-preview"><div class="ra-fb-photo-box"><i class="ri-camera-line"></i><span>Foto 1</span></div><div class="ra-fb-photo-box"><i class="ri-add-line"></i><span>Foto 2</span></div><div class="ra-fb-photo-box"><i class="ri-add-line"></i><span>Foto 3</span></div></div>';
    } elseif ($input === 'condition') {
        $html .= '<div class="ra-fb-condition-grid"><div class="ra-fb-condition-item"><span>Display</span><div class="ra-fb-cond-btns"><span class="ra-fb-cond-btn ok">OK</span><span class="ra-fb-cond-btn nok">Defekt</span></div></div><div class="ra-fb-condition-item"><span>Tasten</span><div class="ra-fb-cond-btns"><span class="ra-fb-cond-btn ok">OK</span><span class="ra-fb-cond-btn nok">Defekt</span></div></div><div class="ra-fb-condition-item"><span>Kamera</span><div class="ra-fb-cond-btns"><span class="ra-fb-cond-btn ok">OK</span><span class="ra-fb-cond-btn nok">Defekt</span></div></div><div class="ra-fb-condition-item"><span>Lautsprecher</span><div class="ra-fb-cond-btns"><span class="ra-fb-cond-btn ok">OK</span><span class="ra-fb-cond-btn nok">Defekt</span></div></div></div>';
    } elseif ($input === 'condition_kfz') {
        $html .= '<div class="ra-fb-condition-grid"><div class="ra-fb-condition-item"><span>' . esc_html(PPV_Lang::t('repair_cond_kfz_motor')) . '</span><div class="ra-fb-cond-btns"><span class="ra-fb-cond-btn ok">OK</span><span class="ra-fb-cond-btn nok">Defekt</span></div></div><div class="ra-fb-condition-item"><span>' . esc_html(PPV_Lang::t('repair_cond_kfz_bremsen')) . '</span><div class="ra-fb-cond-btns"><span class="ra-fb-cond-btn ok">OK</span><span class="ra-fb-cond-btn nok">Defekt</span></div></div><div class="ra-fb-condition-item"><span>' . esc_html(PPV_Lang::t('repair_cond_kfz_reifen')) . '</span><div class="ra-fb-cond-btns"><span class="ra-fb-cond-btn ok">OK</span><span class="ra-fb-cond-btn nok">Defekt</span></div></div><div class="ra-fb-condition-item"><span>' . esc_html(PPV_Lang::t('repair_cond_kfz_karosserie')) . '</span><div class="ra-fb-cond-btns"><span class="ra-fb-cond-btn ok">OK</span><span class="ra-fb-cond-btn nok">Defekt</span></div></div></div>';
    } elseif ($input === 'priority') {
        $html .= '<div class="ra-fb-priority-cards"><div class="ra-fb-priority-card"><i class="ri-flashlight-line"></i><strong>Express</strong>24h</div><div class="ra-fb-priority-card active"><i class="ri-time-line"></i><strong>Normal</strong>3-5 Tage</div><div class="ra-fb-priority-card"><i class="ri-leaf-line"></i><strong>Sparsam</strong>7-10 Tage</div></div>';
    } elseif ($input === 'color') {
        $html .= '<div class="ra-fb-color-swatches"><div class="ra-fb-color-dot active" style="background:#000" title="Schwarz"></div><div class="ra-fb-color-dot" style="background:#fff" title="Weiß"></div><div class="ra-fb-color-dot" style="background:#c0c0c0" title="Silber"></div><div class="ra-fb-color-dot" style="background:#d4a437" title="Gold"></div><div class="ra-fb-color-dot" style="background:#3b82f6" title="Blau"></div><div class="ra-fb-color-dot" style="background:#ef4444" title="Rot"></div></div>';
    } elseif ($input === 'cost') {
        $html .= '<div class="ra-fb-cost-options"><span class="ra-fb-cost-opt">max. 50&euro;</span><span class="ra-fb-cost-opt active">max. 100&euro;</span><span class="ra-fb-cost-opt">max. 200&euro;</span><span class="ra-fb-cost-opt">Kein Limit</span></div>';
    } elseif ($input === 'date') {
        $html .= '<input type="date" lang="' . esc_attr(PPV_Lang::current()) . '" placeholder="' . esc_attr($label) . '">';
    } else {
        $html .= '<input type="' . esc_attr($input) . '" placeholder="' . esc_attr($meta['ph']) . '">';
    }

    $html .= '</div>
        <div class="ra-fb-settings">
            <div class="ra-fb-settings-row"><label>' . esc_html(PPV_Lang::t('repair_admin_fc_label')) . ':</label><input type="text" data-field-label="' . $fk . '" value="' . $label . '" class="ra-fb-label-input" style="flex:1"></div>
            <div class="ra-fb-settings-row"><label style="cursor:pointer"><input type="checkbox" data-field-required="' . $fk . '" ' . ($required ? 'checked' : '') . ' style="accent-color:#667eea"> ' . esc_html(PPV_Lang::t('repair_admin_fc_required')) . '</label></div>
        </div>
    </div>';
    return $html;
}

// Collect fields per section, sorted by order
$sections = [1 => [], 2 => [], 3 => []];
foreach ($fb_all_builtins as $fk => $meta) {
    $fc = $field_config[$fk] ?? $fc_defaults[$fk];
    $order = $fc['order'] ?? 99;
    $sections[$meta['section']][] = ['key' => $fk, 'order' => $order];
}
foreach ($sections as &$sec) { usort($sec, function($a, $b) { return $a['order'] - $b['order']; }); }
unset($sec);

// === Section 1: Customer Info ===
echo '<div class="ra-fb-section">
    <div class="ra-fb-section-title"><span class="ra-fb-step-num">1</span><h3>' . esc_html(PPV_Lang::t('repair_step1_title')) . '</h3></div>
    <div class="ra-fb-field locked"><div class="ra-fb-field-body">
        <label>' . esc_html(PPV_Lang::t('repair_name_label')) . ' <span class="ra-fb-lock-icon"><i class="ri-lock-line"></i></span></label>
        <input type="text" placeholder="' . esc_attr(PPV_Lang::t('repair_name_placeholder')) . '">
    </div></div>
    <div class="ra-fb-field locked"><div class="ra-fb-field-body">
        <label>' . esc_html(PPV_Lang::t('repair_email_label')) . ' <span class="ra-fb-lock-icon"><i class="ri-lock-line"></i></span></label>
        <input type="email" placeholder="ihre@email.de">
    </div></div>
    <div id="ra-fb-sec1">';
foreach ($sections[1] as $item) {
    echo ppv_fb_render_field($item['key'], $fb_all_builtins[$item['key']], $field_config[$item['key']] ?? $fc_defaults[$item['key']], $fc_defaults);
}
echo '</div></div>';

// === Section 2: Device Info ===
echo '<div class="ra-fb-section">
    <div class="ra-fb-section-title"><span class="ra-fb-step-num">2</span><h3>' . esc_html(PPV_Lang::t('repair_step2_title')) . '</h3></div>
    <div id="ra-fb-sec2">';
foreach ($sections[2] as $item) {
    echo ppv_fb_render_field($item['key'], $fb_all_builtins[$item['key']], $field_config[$item['key']] ?? $fc_defaults[$item['key']], $fc_defaults);
}
echo '</div></div>';

// === Section 3: Problem + Extras + Custom Fields ===
echo '<div class="ra-fb-section">
    <div class="ra-fb-section-title"><span class="ra-fb-step-num">3</span><h3>' . esc_html(PPV_Lang::t('repair_step3_title')) . '</h3></div>
    <div class="ra-fb-field locked"><div class="ra-fb-field-body">
        <label>' . esc_html(PPV_Lang::t('repair_problem_label')) . ' <span class="ra-fb-lock-icon"><i class="ri-lock-line"></i></span></label>
        <textarea rows="3" placeholder="' . esc_attr(PPV_Lang::t('repair_problem_placeholder')) . '"></textarea>
    </div></div>
    <div id="ra-fb-sec3">';
foreach ($sections[3] as $item) {
    echo ppv_fb_render_field($item['key'], $fb_all_builtins[$item['key']], $field_config[$item['key']] ?? $fc_defaults[$item['key']], $fc_defaults);
}

// Custom fields
echo '<div id="ra-fb-custom-fields">';
foreach ($field_config as $fk => $fc) {
    if (strpos($fk, 'custom_') !== 0) continue;
    $enabled = !empty($fc['enabled']);
    $label = esc_attr($fc['label'] ?? '');
    $type = esc_attr($fc['type'] ?? 'text');
    $required = !empty($fc['required']);
    $options_raw = $fc['options'] ?? '';
    $hidden = !$enabled ? ' ra-fb-hidden' : '';

    echo '<div class="ra-fb-field' . $hidden . '" data-key="' . $fk . '" data-builtin="0" draggable="true">
        <i class="ri-draggable ra-fb-drag"></i>
        <div class="ra-fb-controls">
            <button type="button" class="ra-fb-ctrl-btn ra-fb-btn-settings" title="Einstellungen"><i class="ri-settings-3-line"></i></button>
            <button type="button" class="ra-fb-ctrl-btn danger ra-fb-remove" title="' . esc_attr(PPV_Lang::t('repair_admin_filiale_delete')) . '"><i class="ri-delete-bin-line"></i></button>
        </div>
        <div class="ra-fb-field-body">';
    if ($type === 'textarea') {
        echo '<label data-label-for="' . $fk . '">' . ($label ?: '<em style="color:#94a3b8">Neues Feld</em>') . ($required ? '<span class="ra-fb-req">*</span>' : '') . '</label><textarea rows="2" placeholder="' . esc_attr($label) . '"></textarea>';
    } elseif ($type === 'select') {
        echo '<label data-label-for="' . $fk . '">' . ($label ?: '<em style="color:#94a3b8">Neues Feld</em>') . ($required ? '<span class="ra-fb-req">*</span>' : '') . '</label><select style="pointer-events:none"><option>' . esc_html($label ?: '-- Auswahl --') . '</option></select>';
    } elseif ($type === 'checkbox') {
        echo '<label data-label-for="' . $fk . '"><span class="ra-fb-checkbox-preview"><i class="ri-checkbox-blank-line"></i> ' . ($label ?: 'Checkbox') . '</span>' . ($required ? '<span class="ra-fb-req">*</span>' : '') . '</label>';
    } elseif ($type === 'number') {
        echo '<label data-label-for="' . $fk . '">' . ($label ?: '<em style="color:#94a3b8">Neues Feld</em>') . ($required ? '<span class="ra-fb-req">*</span>' : '') . '</label><input type="number" placeholder="' . esc_attr($label) . '">';
    } else {
        echo '<label data-label-for="' . $fk . '">' . ($label ?: '<em style="color:#94a3b8">Neues Feld</em>') . ($required ? '<span class="ra-fb-req">*</span>' : '') . '</label><input type="text" placeholder="' . esc_attr($label) . '">';
    }
    echo '</div>
        <div class="ra-fb-settings">
            <div class="ra-fb-settings-row"><label>' . esc_html(PPV_Lang::t('repair_admin_fc_label')) . ':</label><input type="text" data-field-label="' . $fk . '" value="' . $label . '" class="ra-fb-label-input" style="flex:1"></div>
            <div class="ra-fb-settings-row"><label>' . esc_html(PPV_Lang::t('repair_admin_fc_fieldtype')) . ':</label>
                <select data-field-type="' . $fk . '"><option value="text"' . ($type === 'text' ? ' selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_fc_type_text')) . '</option><option value="textarea"' . ($type === 'textarea' ? ' selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_fc_type_textarea')) . '</option><option value="number"' . ($type === 'number' ? ' selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_fc_type_number')) . '</option><option value="select"' . ($type === 'select' ? ' selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_fc_type_select')) . '</option><option value="checkbox"' . ($type === 'checkbox' ? ' selected' : '') . '>' . esc_html(PPV_Lang::t('repair_admin_fc_type_checkbox')) . '</option></select>
            </div>
            <div class="ra-fb-settings-row ra-fb-options-row"' . ($type !== 'select' ? ' style="display:none"' : '') . '><textarea data-field-options="' . $fk . '" rows="3" placeholder="Option 1&#10;Option 2&#10;Option 3" style="width:100%">' . esc_textarea($options_raw) . '</textarea></div>
            <div class="ra-fb-settings-row"><label style="cursor:pointer"><input type="checkbox" data-field-required="' . $fk . '" ' . ($required ? 'checked' : '') . ' style="accent-color:#667eea"> ' . esc_html(PPV_Lang::t('repair_admin_fc_required')) . '</label></div>
        </div>
    </div>';
}
echo '</div></div></div>';

// === Section 4: Signature (locked) ===
echo '<div class="ra-fb-section">
    <div class="ra-fb-section-title"><span class="ra-fb-step-num">4</span><h3>' . esc_html(PPV_Lang::t('repair_step4_title')) . '</h3></div>
    <div class="ra-fb-field locked"><div class="ra-fb-field-body">
        <label>' . esc_html(PPV_Lang::t('repair_signature_label')) . ' <span class="ra-fb-lock-icon"><i class="ri-lock-line"></i></span></label>
        <div class="ra-fb-sig-preview"><i class="ri-edit-line"></i> Unterschrift</div>
    </div></div>
</div>';

echo '</div></div>';

// === PALETTE SIDEBAR ===
echo '<div class="ra-fb-palette">
    <div class="ra-fb-pal-title"><i class="ri-apps-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_fc_palette')) . '</div>';

// Palette groups
$pal_groups = [
    PPV_Lang::t('repair_admin_fc_basic') => [
        'customer_phone'   => ['icon' => 'ri-phone-line',    'name' => $fc_defaults['customer_phone']['label']],
        'customer_address' => ['icon' => 'ri-map-pin-line',  'name' => $fc_defaults['customer_address']['label']],
    ],
    PPV_Lang::t('repair_admin_fc_device') => [
        'device_brand'    => ['icon' => 'ri-smartphone-line',    'name' => $fc_defaults['device_brand']['label']],
        'device_model'    => ['icon' => 'ri-device-line',        'name' => $fc_defaults['device_model']['label']],
        'device_imei'     => ['icon' => 'ri-barcode-line',       'name' => $fc_defaults['device_imei']['label']],
        'device_pattern'  => ['icon' => 'ri-lock-password-line', 'name' => $fc_defaults['device_pattern']['label']],
        'muster_image'    => ['icon' => 'ri-grid-line',          'name' => $fc_defaults['muster_image']['label']],
        'device_color'    => ['icon' => 'ri-palette-line',       'name' => $fc_defaults['device_color']['label']],
        'purchase_date'   => ['icon' => 'ri-calendar-line',      'name' => $fc_defaults['purchase_date']['label']],
        'condition_check' => ['icon' => 'ri-shield-check-line',  'name' => $fc_defaults['condition_check']['label']],
    ],
    PPV_Lang::t('repair_admin_fc_vehicle') => [
        'vehicle_plate'       => ['icon' => 'ri-car-line',            'name' => $fc_defaults['vehicle_plate']['label']],
        'vehicle_vin'         => ['icon' => 'ri-fingerprint-line',    'name' => $fc_defaults['vehicle_vin']['label']],
        'vehicle_mileage'     => ['icon' => 'ri-dashboard-3-line',    'name' => $fc_defaults['vehicle_mileage']['label']],
        'vehicle_first_reg'   => ['icon' => 'ri-calendar-check-line', 'name' => $fc_defaults['vehicle_first_reg']['label']],
        'vehicle_tuev'        => ['icon' => 'ri-shield-star-line',    'name' => $fc_defaults['vehicle_tuev']['label']],
        'condition_check_kfz' => ['icon' => 'ri-car-washing-line',    'name' => $fc_defaults['condition_check_kfz']['label']],
    ],
    PPV_Lang::t('repair_admin_fc_pc') => [
        'condition_check_pc' => ['icon' => 'ri-computer-line',       'name' => $fc_defaults['condition_check_pc']['label']],
    ],
    PPV_Lang::t('repair_admin_fc_extra') => [
        'accessories'   => ['icon' => 'ri-checkbox-multiple-line',   'name' => $fc_defaults['accessories']['label']],
        'photo_upload'  => ['icon' => 'ri-camera-line',              'name' => $fc_defaults['photo_upload']['label']],
        'priority'      => ['icon' => 'ri-flashlight-line',          'name' => $fc_defaults['priority']['label']],
        'cost_limit'    => ['icon' => 'ri-money-euro-circle-line',   'name' => $fc_defaults['cost_limit']['label']],
    ],
];

foreach ($pal_groups as $group_name => $items) {
    echo '<div class="ra-fb-pal-group">' . esc_html($group_name) . '</div>';
    foreach ($items as $pk => $pi) {
        $is_enabled = !empty(($field_config[$pk] ?? $fc_defaults[$pk])['enabled']);
        echo '<div class="ra-fb-pal-item' . ($is_enabled ? ' used' : '') . '" data-target="' . $pk . '"><i class="' . $pi['icon'] . '"></i> ' . esc_html($pi['name']) . '<i class="ri-check-line ra-fb-pal-check"></i></div>';
    }
}
echo '<div class="ra-fb-pal-group">' . esc_html(PPV_Lang::t('repair_admin_fc_custom')) . '</div>
    <button type="button" class="ra-fb-pal-add" id="ra-fb-add-custom"><i class="ri-add-circle-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_fc_add_custom')) . '</button>
</div>';

echo '</div></div>
            </div>


                <h4><i class="ri-settings-4-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_branch_options')) . '</h4>
            <p style="font-size:12px;color:#6b7280;margin-bottom:16px">' . esc_html(PPV_Lang::t('repair_admin_branch_hint')) . '</p>

            <div class="field" style="margin-bottom:16px">
                <label>' . esc_html(PPV_Lang::t('repair_admin_brands_label')) . '</label>
                <div class="ra-chip-editor" data-chip-for="repair_custom_brands">
                    <div class="ra-chip-wrap">
                        <input type="text" class="ra-chip-input" placeholder="+ Apple, Samsung, Huawei…">
                    </div>
                </div>
                <textarea name="repair_custom_brands" style="display:none">' . $custom_brands . '</textarea>
                <div class="ra-chip-count"></div>
                <p style="font-size:11px;color:#9ca3af;margin-top:2px">' . esc_html(PPV_Lang::t('repair_admin_brands_hint')) . '</p>
            </div>

            <div class="field" style="margin-bottom:16px">
                <label>' . esc_html(PPV_Lang::t('repair_admin_problems_label')) . '</label>
                <div class="ra-chip-editor" data-chip-for="repair_custom_problems">
                    <div class="ra-chip-wrap">
                        <input type="text" class="ra-chip-input" placeholder="+ Display gebrochen, Akku tauschen…">
                    </div>
                </div>
                <textarea name="repair_custom_problems" style="display:none">' . $custom_problems . '</textarea>
                <div class="ra-chip-count"></div>
                <p style="font-size:11px;color:#9ca3af;margin-top:2px">' . esc_html(PPV_Lang::t('repair_admin_problems_hint')) . '</p>
            </div>

            <div class="field" style="margin-bottom:16px">
                <label>' . esc_html(PPV_Lang::t('repair_admin_accessories_label')) . '</label>
                <div class="ra-chip-editor" data-chip-for="repair_custom_accessories">
                    <div class="ra-chip-wrap">
                        <input type="text" class="ra-chip-input" placeholder="+ Hülle, Panzerglas, Ladekabel…">
                    </div>
                </div>
                <textarea name="repair_custom_accessories" style="display:none">' . $custom_accessories . '</textarea>
                <div class="ra-chip-count"></div>
                <p style="font-size:11px;color:#9ca3af;margin-top:2px">' . esc_html(PPV_Lang::t('repair_admin_accessories_hint')) . '</p>
            </div>

            <div class="ra-settings-grid" style="margin-bottom:16px">
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_opening_hours')) . '</label>
                    <input type="text" name="repair_opening_hours" value="' . $opening_hours . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_opening_hours_ph')) . '">
                    <p style="font-size:11px;color:#9ca3af;margin-top:4px">' . esc_html(PPV_Lang::t('repair_admin_opening_hint')) . '</p>
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_terms_url')) . '</label>
                    <input type="text" name="repair_terms_url" value="' . $terms_url . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_terms_url_ph')) . '">
                    <p style="font-size:11px;color:#9ca3af;margin-top:4px">' . esc_html(PPV_Lang::t('repair_admin_terms_hint')) . '</p>
                </div>
            </div>

            <div class="field" style="margin-bottom:16px">
                <label>' . esc_html(PPV_Lang::t('repair_admin_success_msg')) . '</label>
                <textarea name="repair_success_message" rows="3" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;font-family:inherit;resize:vertical" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_success_msg_ph')) . '">' . $success_message . '</textarea>
                <p style="font-size:11px;color:#9ca3af;margin-top:4px">' . esc_html(PPV_Lang::t('repair_admin_success_hint')) . '</p>
            </div>

            </div><!-- END PANEL: form -->

            <!-- ==================== PANEL: Rechnungen ==================== -->
            <div class="ra-settings-panel" data-panel="invoice">
                <h4><i class="ri-file-list-3-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_inv_numbers')) . '</h4>
            <div class="ra-settings-grid">
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_inv_prefix')) . '</label>
                    <input type="text" name="repair_invoice_prefix" id="ra-inv-prefix" value="' . $inv_prefix . '" placeholder="RE-" oninput="updateInvPreview()">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_inv_next')) . '</label>
                    <input type="number" name="repair_invoice_next_number" id="ra-inv-next" value="' . $effective_next . '" min="1" oninput="updateInvPreview()">
                </div>
            </div>
            <div style="margin-top:12px;padding:12px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                    <div>
                        <div style="font-size:12px;color:#0369a1;font-weight:500">' . esc_html(PPV_Lang::t('repair_admin_inv_preview')) . '</div>
                        <div style="font-size:18px;font-weight:600;color:#0c4a6e;margin-top:2px" id="ra-inv-preview">' . esc_html($inv_prefix . str_pad($effective_next, 4, '0', STR_PAD_LEFT)) . '</div>
                    </div>
                    ' . ($inv_detected_max > 0 && $inv_suggested_next > $inv_next ? '
                    <div style="text-align:right">
                        <div style="font-size:11px;color:#6b7280">' . esc_html(PPV_Lang::t('repair_admin_inv_highest')) . ' "' . esc_html($inv_prefix) . '": <strong>' . esc_html($inv_detected_max) . '</strong></div>
                        <button type="button" onclick="document.getElementById(\'ra-inv-next\').value=' . $inv_suggested_next . ';updateInvPreview()" style="margin-top:4px;padding:6px 12px;background:#0ea5e9;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer">' . esc_html(PPV_Lang::t('repair_admin_inv_apply')) . ': ' . $inv_suggested_next . '</button>
                    </div>' : '') . '
                </div>
                ' . ($inv_total_count > 0 ? '<div style="margin-top:8px;padding-top:8px;border-top:1px solid #bae6fd;font-size:11px;color:#6b7280">' . esc_html(PPV_Lang::t('repair_admin_inv_total_count')) . ': <strong>' . $inv_total_count . '</strong>' . ($inv_detected_max == 0 && count($all_invoice_numbers) == 0 ? ' (' . esc_html(PPV_Lang::t('repair_admin_inv_no_prefix')) . ' "' . esc_html($inv_prefix) . '")' : ' (' . count($all_invoice_numbers) . ' ' . esc_html(PPV_Lang::t('repair_admin_inv_with_prefix')) . ' "' . esc_html($inv_prefix) . '")') . '</div>' : '') . '
            </div>
            <script>
            function updateInvPreview(){
                var p=document.getElementById("ra-inv-prefix").value||"RE-";
                var n=parseInt(document.getElementById("ra-inv-next").value)||1;
                document.getElementById("ra-inv-preview").textContent=p+String(n).padStart(4,"0");
            }
            </script>


                <h4><i class="ri-percent-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_vat_title')) . '</h4>
            <div class="ra-toggle">
                <label class="ra-toggle-switch">
                    <input type="checkbox" id="ra-vat-toggle" ' . ($vat_enabled ? 'checked' : '') . '>
                    <span class="ra-toggle-slider"></span>
                </label>
                <div>
                    <div class="ra-toggle-label">' . esc_html(PPV_Lang::t('repair_admin_vat_liable')) . '</div>
                    <div class="ra-toggle-desc">' . esc_html(PPV_Lang::t('repair_admin_vat_small')) . '</div>
                </div>
            </div>
            <input type="hidden" name="repair_vat_enabled" id="ra-vat-enabled-val" value="' . $vat_enabled . '">
            <div id="ra-vat-settings" ' . ($vat_enabled ? '' : 'style="display:none"') . '>
                <div class="ra-settings-grid">
                    <div class="field">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_vat_rate')) . '</label>
                        <input type="number" name="repair_vat_rate" value="' . $vat_rate . '" min="0" max="100" step="0.01" placeholder="19.00">
                    </div>
                </div>
            </div>

                <h4 style="margin-top:24px"><i class="ri-file-text-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_tax_data')) . '</h4>
            <div class="ra-settings-grid">
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_tax_number')) . '</label>
                    <input type="text" name="repair_steuernummer" value="' . $steuernummer . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_tax_number_ph')) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_website')) . '</label>
                    <input type="text" name="repair_website_url" value="' . $website_url . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_website_ph')) . '">
                </div>
            </div>

                <h4 style="margin-top:24px"><i class="ri-bank-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_bank_details')) . '</h4>
            <p style="font-size:12px;color:#6b7280;margin-bottom:12px">' . esc_html(PPV_Lang::t('repair_admin_bank_hint')) . '</p>
            <div class="ra-settings-grid">
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_bank_name')) . '</label>
                    <input type="text" name="repair_bank_name" value="' . $bank_name . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_bank_name_ph')) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_iban')) . '</label>
                    <input type="text" name="repair_bank_iban" value="' . $bank_iban . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_iban_ph')) . '">
                </div>
                <div class="field">
                    <label>' . esc_html(PPV_Lang::t('repair_admin_bic')) . '</label>
                    <input type="text" name="repair_bank_bic" value="' . $bank_bic . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_bic_ph')) . '">
                </div>
            </div>

                <h4 style="margin-top:24px"><i class="ri-paypal-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_paypal')) . '</h4>
            <div class="field">
                <label>' . esc_html(PPV_Lang::t('repair_admin_paypal_email')) . '</label>
                <input type="text" name="repair_paypal_email" value="' . $paypal_email . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_paypal_ph')) . '">
            </div>

                <h4 style="margin-top:24px"><i class="ri-shield-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_warranty_default')) . '</h4>
            <p style="font-size:12px;color:#6b7280;margin-bottom:12px">' . esc_html(PPV_Lang::t('repair_admin_warranty_default_hint')) . '</p>
            <div class="field">
                <textarea name="repair_warranty_text" rows="3" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_warranty_desc_ph')) . '" style="resize:vertical">' . $warranty_text . '</textarea>
            </div>

            </div><!-- END PANEL: invoice -->

            <!-- ==================== PANEL: E-Mail ==================== -->
            <div class="ra-settings-panel" data-panel="email">
                <h4><i class="ri-mail-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_email_template')) . '</h4>
            <p style="font-size:12px;color:#6b7280;margin-bottom:12px">' . esc_html(PPV_Lang::t('repair_admin_email_tmpl_hint')) . '</p>
            <div class="field" style="margin-bottom:12px">
                <label>' . esc_html(PPV_Lang::t('repair_admin_email_subject')) . '</label>
                <input type="text" name="repair_invoice_email_subject" value="' . $email_subject . '" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_email_subject_ph')) . '">
            </div>
            <div class="field">
                <label>' . esc_html(PPV_Lang::t('repair_admin_email_message')) . '</label>
                <textarea name="repair_invoice_email_body" rows="6" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-family:inherit;resize:vertical">' . $email_body . '</textarea>
            </div>


                <h4><i class="ri-notification-3-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_status_notify')) . '</h4>
            <p style="font-size:12px;color:#6b7280;margin-bottom:12px">' . esc_html(PPV_Lang::t('repair_admin_status_notify_hint')) . '</p>
            <div class="ra-toggle" style="margin-bottom:16px">
                <label class="ra-toggle-switch">
                    <input type="checkbox" name="repair_status_notify_enabled" value="1" ' . ($status_notify_enabled ? 'checked' : '') . '>
                    <span class="ra-toggle-slider"></span>
                </label>
                <div>
                    <strong>' . esc_html(PPV_Lang::t('repair_admin_status_notify_enable')) . '</strong>
                    <div style="font-size:12px;color:#6b7280">' . esc_html(PPV_Lang::t('repair_admin_status_notify_desc')) . '</div>
                </div>
            </div>
            <div class="field" style="margin-bottom:8px">
                <label style="margin-bottom:8px;display:block">' . esc_html(PPV_Lang::t('repair_admin_notify_statuses')) . '</label>
                <div style="display:flex;flex-wrap:wrap;gap:12px">
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="notify_in_progress" value="1" ' . (in_array('in_progress', $notify_statuses_arr) ? 'checked' : '') . ' style="width:16px;height:16px">
                        <span style="color:#d97706"><i class="ri-loader-4-line"></i></span> ' . esc_html(PPV_Lang::t('repair_admin_status_progress')) . '
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="notify_waiting_parts" value="1" ' . (in_array('waiting_parts', $notify_statuses_arr) ? 'checked' : '') . ' style="width:16px;height:16px">
                        <span style="color:#ec4899"><i class="ri-time-line"></i></span> ' . esc_html(PPV_Lang::t('repair_admin_status_waiting')) . '
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="notify_parts_arrived" value="1" ' . (in_array('parts_arrived', $notify_statuses_arr) ? 'checked' : '') . ' style="width:16px;height:16px">
                        <span style="color:#059669"><i class="ri-checkbox-circle-fill"></i></span> ' . esc_html(PPV_Lang::t('repair_admin_parts_arrived_short')) . '
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="notify_done" value="1" ' . (in_array('done', $notify_statuses_arr) ? 'checked' : '') . ' style="width:16px;height:16px">
                        <span style="color:#059669"><i class="ri-checkbox-circle-line"></i></span> ' . esc_html(PPV_Lang::t('repair_admin_status_done')) . '
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="notify_delivered" value="1" ' . (in_array('delivered', $notify_statuses_arr) ? 'checked' : '') . ' style="width:16px;height:16px">
                        <span style="color:#6b7280"><i class="ri-truck-line"></i></span> ' . esc_html(PPV_Lang::t('repair_admin_status_delivered')) . '
                    </label>
                </div>
            </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0">

                <h4><i class="ri-star-smile-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_feedback_title')) . '</h4>
            <p style="font-size:12px;color:#6b7280;margin-bottom:12px">' . esc_html(PPV_Lang::t('repair_admin_feedback_hint')) . '</p>

            <div class="ra-toggle" style="margin-bottom:16px">
                <label class="ra-toggle-switch">
                    <input type="checkbox" name="repair_feedback_email_enabled" value="1" ' . ($feedback_email_enabled ? 'checked' : '') . '>
                    <span class="ra-toggle-slider"></span>
                </label>
                <div>
                    <strong>' . esc_html(PPV_Lang::t('repair_admin_feedback_enable')) . '</strong>
                    <div style="font-size:12px;color:#6b7280">' . esc_html(PPV_Lang::t('repair_admin_feedback_desc')) . '</div>
                </div>
            </div>

            <div class="field" style="margin-bottom:12px">
                <label>' . esc_html(PPV_Lang::t('repair_admin_google_review_url')) . '</label>
                <input type="url" name="repair_google_review_url" value="' . $google_review_url . '" placeholder="https://g.page/r/...">
            </div>

            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:16px;margin-bottom:8px">
                <div style="font-size:13px;font-weight:600;color:#0369a1;margin-bottom:8px"><i class="ri-question-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_review_help_title')) . '</div>
                <ol style="font-size:12px;color:#475569;margin:0;padding-left:18px;line-height:1.8">
                    <li>' . esc_html(PPV_Lang::t('repair_admin_review_help_1')) . '</li>
                    <li>' . esc_html(PPV_Lang::t('repair_admin_review_help_2')) . '</li>
                    <li>' . esc_html(PPV_Lang::t('repair_admin_review_help_3')) . '</li>
                    <li>' . esc_html(PPV_Lang::t('repair_admin_review_help_4')) . '</li>
                </ol>
            </div>

            <div style="background:#fefce8;border:1px solid #fde68a;border-radius:10px;padding:14px;font-size:12px;color:#854d0e">
                <strong><i class="ri-lightbulb-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_feedback_how_title')) . '</strong><br>
                ' . esc_html(PPV_Lang::t('repair_admin_feedback_how_desc')) . '
            </div>

            </div><!-- END PANEL: email -->

            <!-- ==================== PANEL: Abo ==================== -->
            <div class="ra-settings-panel" data-panel="abo">
                <h4><i class="ri-vip-crown-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_abo_title')) . '</h4>
            <div class="ra-abo-box" style="background:#fafafa;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px">
                    <div>
                        <div style="font-size:13px;color:#6b7280;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_abo_status')) . '</div>
                        <div style="font-size:16px;font-weight:600;color:#111827">';

        // Subscription status display
        $sub_status = $store->subscription_status ?? 'trial';
        $sub_expires = $store->subscription_expires_at ?? null;
        $is_expired = $sub_expires && strtotime($sub_expires) < time();
        $payment_method = $store->payment_method ?? '';

        // Active subscription = either repair_premium=1 OR subscription_status=active with valid date
        if (($is_premium || $sub_status === 'active') && !$is_expired) {
            echo '<span style="color:#059669"><i class="ri-checkbox-circle-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_abo_premium')) . '</span>';
        } elseif ($sub_status === 'pending_payment') {
            echo '<span style="color:#d97706"><i class="ri-time-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_abo_pending')) . '</span>';
        } elseif ($sub_status === 'canceled') {
            echo '<span style="color:#dc2626"><i class="ri-close-circle-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_abo_cancelled')) . '</span>';
        } elseif ($is_expired) {
            echo '<span style="color:#dc2626"><i class="ri-error-warning-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_abo_expired')) . '</span>';
        } else {
            echo '<span style="color:#6b7280"><i class="ri-information-line"></i> ' . ucfirst($sub_status) . '</span>';
        }

        echo '</div>
                    </div>
                    <div>
                        <div style="font-size:13px;color:#6b7280;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_abo_limit')) . '</div>
                        <div style="font-size:16px;font-weight:600;color:#111827">';

        if ($is_premium || ($sub_status === 'active' && !$is_expired)) {
            echo '<span style="color:#059669"><i class="ri-infinity-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_abo_unlimited')) . '</span>';
        } else {
            echo intval($store->repair_form_count) . ' / ' . intval($store->repair_form_limit);
        }

        echo '</div>
                    </div>';

        if ($sub_expires) {
            echo '<div>
                        <div style="font-size:13px;color:#6b7280;margin-bottom:4px">' . ($is_expired ? esc_html(PPV_Lang::t('repair_admin_abo_expired_on')) : esc_html(PPV_Lang::t('repair_admin_abo_valid_until'))) . '</div>
                        <div style="font-size:16px;font-weight:600;color:' . ($is_expired ? '#dc2626' : '#111827') . '">' . date('d.m.Y', strtotime($sub_expires)) . '</div>
                    </div>';
        }

        if ($payment_method) {
            echo '<div>
                        <div style="font-size:13px;color:#6b7280;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_abo_payment')) . '</div>
                        <div style="font-size:16px;font-weight:600;color:#111827">';
            if ($payment_method === 'paypal') {
                echo '<i class="ri-paypal-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_abo_paypal'));
            } elseif ($payment_method === 'bank_transfer') {
                echo '<i class="ri-bank-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_abo_transfer'));
            } else {
                echo ucfirst($payment_method);
            }
            echo '</div>
                    </div>';
        }

        echo '</div>
            </div>';

        // Check if has active subscription (either repair_premium=1 OR subscription_status=active with valid date)
        $has_active_sub = $is_premium || ($sub_status === 'active' && !$is_expired);

        // Upgrade button for non-premium or expired
        if (!$has_active_sub) {
            echo '<a href="/checkout" class="ra-btn ra-btn-primary" style="margin-bottom:16px">
                <i class="ri-vip-crown-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_abo_upgrade')) . '
            </a>';
        }

        // Cancellation section for active subscription users
        if ($has_active_sub && $sub_status !== 'canceled') {
            echo '<div class="ra-kuendigung" style="margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb">
                <div style="font-size:14px;font-weight:600;color:#374151;margin-bottom:8px"><i class="ri-close-circle-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_abo_cancel')) . '</div>
                <p style="font-size:13px;color:#6b7280;margin-bottom:12px">' . esc_html(PPV_Lang::t('repair_admin_abo_cancel_hint')) . '</p>
                <button type="button" class="ra-btn ra-btn-outline" style="color:#dc2626;border-color:#fecaca" id="ra-cancel-sub-btn">
                    <i class="ri-close-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_abo_cancel_btn')) . '
                </button>
            </div>';
        }

        echo '
            </div><!-- END PANEL: abo -->

            <!-- ==================== PANEL: Filialen ==================== -->
            <div class="ra-settings-panel" data-panel="filialen">
                <h4><i class="ri-building-2-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_filialen_title')) . '</h4>';

        if (!$parent_is_premium) {
            // Free tier - show upgrade prompt
            echo '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:20px;text-align:center;margin-bottom:16px">
                <i class="ri-vip-crown-line" style="font-size:28px;color:#d97706;margin-bottom:8px;display:block"></i>
                <div style="font-size:15px;font-weight:600;color:#92400e;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_filialen_premium_only')) . '</div>
                <div style="font-size:13px;color:#a16207">' . esc_html(PPV_Lang::t('repair_admin_filialen_premium_desc')) . '</div>
            </div>';
        } else {
            // Premium - show filiale list and add button
            echo '<div style="margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                    <span style="font-size:13px;color:#6b7280">' . count($filialen) . ' / ' . $max_filialen . ' ' . esc_html(PPV_Lang::t('repair_admin_filialen_count')) . '</span>
                </div>';

            // List existing filialen
            $site_url = home_url();
            foreach ($filialen as $f) {
                $f_is_parent = empty($f->parent_store_id);
                $f_slug = esc_attr($f->store_slug);
                $f_url  = $site_url . '/formular/' . $f_slug;
                $f_id   = intval($f->id);
                echo '<div class="ra-filiale-card" data-fid="' . $f_id . '" style="padding:12px 14px;background:#fafafa;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:8px">
                    <div style="display:flex;align-items:center;gap:12px">
                        <i class="' . ($f_is_parent ? 'ri-home-4-line' : 'ri-building-2-line') . '" style="color:#667eea;font-size:18px;flex-shrink:0"></i>
                        <div style="flex:1;min-width:0">
                            <div class="ra-filiale-name" style="font-size:14px;font-weight:600;color:#1f2937">' . esc_html($f->name) . '</div>
                            <div style="font-size:12px;color:#6b7280">' . esc_html($f->city ?: '') . ($f->plz ? ' ' . esc_html($f->plz) : '') . '</div>
                        </div>
                        ' . ($f_is_parent ? '<span style="font-size:11px;background:#e0e7ff;color:#4338ca;padding:2px 8px;border-radius:6px;font-weight:600">' . esc_html(PPV_Lang::t('repair_admin_filialen_main')) . '</span>' : '') . '
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;margin-top:8px;padding-top:8px;border-top:1px solid #e5e7eb">
                        <button type="button" class="ra-filiale-copy" data-url="' . esc_attr($f_url) . '" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border:1px solid #e5e7eb;background:#fff;border-radius:6px;font-size:11px;color:#6b7280;cursor:pointer;font-family:inherit;transition:all .15s" title="Link kopieren">
                            <i class="ri-link"></i> <span style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">/formular/' . $f_slug . '</span>
                        </button>
                        <div style="flex:1"></div>
                        <button type="button" class="ra-filiale-edit" data-fid="' . $f_id . '" data-name="' . esc_attr($f->name) . '" data-city="' . esc_attr($f->city ?: '') . '" data-plz="' . esc_attr($f->plz ?: '') . '" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border:1px solid #e5e7eb;background:#fff;border-radius:6px;font-size:11px;color:#374151;cursor:pointer;font-family:inherit;transition:all .15s" title="' . esc_attr(PPV_Lang::t('repair_admin_filiale_edit')) . '">
                            <i class="ri-pencil-line"></i>
                        </button>
                        ' . (!$f_is_parent ? '<button type="button" class="ra-filiale-delete" data-fid="' . $f_id . '" data-name="' . esc_attr($f->name) . '" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border:1px solid #fecaca;background:#fff;border-radius:6px;font-size:11px;color:#dc2626;cursor:pointer;font-family:inherit;transition:all .15s" title="' . esc_attr(PPV_Lang::t('repair_admin_filiale_delete')) . '">
                            <i class="ri-delete-bin-line"></i>
                        </button>' : '') . '
                    </div>
                </div>';
            }

            echo '</div>';

            // Add filiale form
            if (count($filialen) < $max_filialen) {
                echo '<div style="border-top:1px solid #e5e7eb;padding-top:16px">
                    <h5 style="font-size:14px;font-weight:600;color:#374151;margin-bottom:12px"><i class="ri-add-circle-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_filiale_add')) . '</h5>
                    <div class="ra-settings-grid">
                        <div class="field">
                            <label>' . esc_html(PPV_Lang::t('repair_admin_filiale_name')) . ' *</label>
                            <input type="text" id="ra-new-filiale-name" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_filiale_name_ph')) . '">
                        </div>
                        <div class="field">
                            <label>' . esc_html(PPV_Lang::t('repair_admin_city')) . ' *</label>
                            <input type="text" id="ra-new-filiale-city" placeholder="Berlin">
                        </div>
                        <div class="field">
                            <label>' . esc_html(PPV_Lang::t('repair_admin_zip')) . '</label>
                            <input type="text" id="ra-new-filiale-plz" placeholder="10115">
                        </div>
                    </div>
                    <button type="button" id="ra-create-filiale-btn" class="ra-btn ra-btn-primary" style="margin-top:12px">
                        <i class="ri-add-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_filiale_create')) . '
                    </button>
                    <div id="ra-filiale-msg" class="ra-hidden" style="margin-top:8px;padding:8px 12px;border-radius:8px;font-size:13px"></div>
                </div>';
            } else {
                echo '<div style="text-align:center;padding:16px;color:#6b7280;font-size:13px">
                    <i class="ri-information-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_filialen_limit_reached')) . '
                </div>';
            }
        }

        echo '
            </div><!-- END PANEL: filialen -->

            <!-- ==================== PANEL: Widget ==================== -->
            <div class="ra-settings-panel" data-panel="widget">
                <h4><i class="ri-code-s-slash-line"></i> Widget / Embed Code</h4>
                <p style="font-size:13px;color:#64748b;margin:0 0 20px">
                    Betten Sie Ihr Reparaturformular auf Ihrer Website ein. Kunden können direkt von Ihrer Seite eine Reparatur beauftragen.
                </p>

                <div class="ra-settings-grid" style="grid-template-columns:1fr 1fr;gap:16px">
                    <div class="field">
                        <label>Widget-Modus</label>
                        <select name="widget_mode" id="ra-widget-mode" class="ra-select" style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px">
                            <option value="float">Floating Button (empfohlen)</option>
                            <option value="ai">KI-Diagnose Widget (empfohlen)</option>
                            <option value="inline">Inline Banner</option>
                            <option value="button">Einfacher Button</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Position</label>
                        <select name="widget_position" id="ra-widget-position" class="ra-select" style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px">
                            <option value="bottom-right">Unten rechts</option>
                            <option value="bottom-left">Unten links</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Farbe</label>
                        <input type="color" name="widget_color" id="ra-widget-color" value="' . $store_color . '" style="width:100%;height:42px;border:1.5px solid #e2e8f0;border-radius:8px;padding:4px;cursor:pointer">
                    </div>
                    <div class="field">
                        <label>Sprache</label>
                        <select name="widget_lang" id="ra-widget-lang" class="ra-select" style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px">
                            <option value="de">Deutsch</option>
                            <option value="en">English</option>
                            <option value="hu">Magyar</option>
                            <option value="ro">Română</option>
                            <option value="it">Italiano</option>
                        </select>
                    </div>
                    <div class="field" id="ra-widget-text-wrap">
                        <label>Button-Text (optional)</label>
                        <input type="text" name="widget_text" id="ra-widget-text" placeholder="Reparatur anfragen" style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px">
                    </div>
                    <div class="field" id="ra-widget-target-wrap" style="display:none">
                        <label>CSS-Selector (inline/button)</label>
                        <input type="text" name="widget_target" id="ra-widget-target" placeholder="#mein-widget" style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px">
                    </div>
                </div>

                <h4 style="margin-top:28px"><i class="ri-eye-line"></i> Vorschau</h4>
                <div id="ra-widget-preview" style="border:2px dashed #e2e8f0;border-radius:12px;padding:24px;min-height:100px;background:#fafbfc;position:relative;overflow:hidden">
                    <div id="ra-widget-preview-float" style="display:flex;align-items:center;gap:10px;padding:14px 22px;border-radius:50px;color:#fff;font-size:14px;font-weight:600;box-shadow:0 4px 24px rgba(0,0,0,.15);cursor:default;width:fit-content;margin-left:auto;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;background:linear-gradient(135deg,' . $store_color . ',#4338ca)">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                        <span id="ra-widget-preview-text">Reparatur anfragen</span>
                    </div>
                    <div id="ra-widget-preview-inline" style="display:none;max-width:400px;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);background:#fff">
                        <div id="ra-widget-preview-inline-hdr" style="padding:20px 24px;color:#fff;background:linear-gradient(135deg,' . $store_color . ',#4338ca)">
                            <div style="font-size:18px;font-weight:700">Reparatur einreichen</div>
                            <div style="font-size:13px;opacity:.85;margin-top:4px">Füllen Sie das Formular aus und wir melden uns.</div>
                        </div>
                        <div style="padding:16px 24px">
                            <div id="ra-widget-preview-inline-cta" style="display:block;width:100%;padding:14px;border:none;border-radius:12px;font-size:15px;font-weight:700;text-align:center;color:#fff;background:linear-gradient(135deg,' . $store_color . ',#4338ca)">Formular öffnen →</div>
                        </div>
                        <div style="padding:10px 24px;border-top:1px solid #f1f5f9;text-align:center;font-size:11px;color:#94a3b8">Powered by <b style="color:#64748b">PunktePass</b></div>
                    </div>
                    <div id="ra-widget-preview-button" style="display:none">
                        <div id="ra-widget-preview-btn" style="display:inline-flex;align-items:center;gap:10px;padding:14px 28px;border-radius:12px;color:#fff;font-size:15px;font-weight:700;cursor:default;box-shadow:0 4px 16px rgba(102,126,234,.3);font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;background:linear-gradient(135deg,' . $store_color . ',#4338ca)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                            <span id="ra-widget-preview-btn-text">Reparatur anfragen</span>
                        </div>
                    </div>
                </div>

                <h4 style="margin-top:28px"><i class="ri-clipboard-line"></i> Embed Code</h4>
                <div style="position:relative">
                    <pre id="ra-widget-code" style="background:#1e293b;color:#e2e8f0;padding:16px 20px;border-radius:12px;font-size:12px;line-height:1.6;overflow-x:auto;white-space:pre-wrap;word-break:break-all;margin:0"></pre>
                    <button type="button" id="ra-widget-copy" style="position:absolute;top:8px;right:8px;background:rgba(255,255,255,.12);border:none;color:#e2e8f0;padding:8px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:background .2s">
                        <i class="ri-file-copy-line"></i> Kopieren
                    </button>
                </div>
                <p style="font-size:12px;color:#94a3b8;margin:8px 0 0">Fügen Sie diesen Code auf Ihrer Website ein, z.B. vor dem &lt;/body&gt; Tag.</p>

                <h4 style="margin-top:28px"><i class="ri-link"></i> Direkter Link</h4>
                <div style="display:flex;gap:8px;align-items:center">
                    <input type="text" readonly id="ra-widget-direct-link" value="' . esc_attr($form_url) . '" style="flex:1;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#f8fafc;color:#475569">
                    <button type="button" id="ra-widget-copy-link" style="background:#667eea;border:none;color:#fff;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:6px;transition:background .2s">
                        <i class="ri-file-copy-line"></i> Kopieren
                    </button>
                </div>

                <!-- AI Widget Konfiguration -->
                <div style="margin-top:32px;border-top:2px solid #e2e8f0;padding-top:24px">
                    <h4 style="margin:0 0 4px"><i class="ri-robot-line"></i> ' . esc_html(PPV_Lang::t('wai_title')) . '</h4>
                    <p style="font-size:13px;color:#64748b;margin:0 0 16px">
                        ' . esc_html(PPV_Lang::t('wai_desc')) . '
                    </p>

                    ' . (!empty($store->widget_setup_complete) ?
                        '<div id="ra-wai-status" style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;margin-bottom:16px;font-size:13px;color:#166534">
                            <i class="ri-checkbox-circle-fill" style="font-size:18px"></i> ' . esc_html(PPV_Lang::t('wai_status_done')) . '
                        </div>' :
                        '<div id="ra-wai-status" style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:#fffbeb;border:1.5px solid #fed7aa;border-radius:10px;margin-bottom:16px;font-size:13px;color:#92400e">
                            <i class="ri-information-line" style="font-size:18px"></i> ' . esc_html(PPV_Lang::t('wai_status_pending')) . '
                        </div>') . '

                    <!-- Brands overview (if any) -->
                    <div id="ra-wai-brands" style="margin-bottom:12px;display:none">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                            <span style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px">' . esc_html(PPV_Lang::t('wai_brands_label')) . '</span>
                            <span id="ra-wai-brand-count" style="font-size:11px;color:#94a3b8"></span>
                        </div>
                        <div id="ra-wai-brand-list" style="display:flex;flex-wrap:wrap;gap:6px"></div>
                    </div>

                    <!-- Services overview (if any) -->
                    <div id="ra-wai-services" style="margin-bottom:16px;display:none">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                            <span style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px">' . esc_html(PPV_Lang::t('wai_services_label')) . '</span>
                            <span id="ra-wai-service-count" style="font-size:11px;color:#94a3b8"></span>
                        </div>
                        <div id="ra-wai-service-list" style="display:flex;flex-wrap:wrap;gap:6px"></div>
                    </div>

                    <!-- File upload area -->
                    <div id="ra-wai-upload-area" style="border:2px dashed #e2e8f0;border-radius:12px;padding:16px;text-align:center;margin-bottom:16px;cursor:pointer;transition:all .2s;background:#fafbfc">
                        <input type="file" id="ra-wai-file" accept=".csv,.txt,.xlsx,.xls,.jpg,.jpeg,.png,.webp,.pdf" style="display:none">
                        <i class="ri-upload-cloud-line" style="font-size:28px;color:#94a3b8"></i>
                        <p style="font-size:13px;color:#64748b;margin:6px 0 2px;font-weight:600">' . esc_html(PPV_Lang::t('wai_upload_title')) . '</p>
                        <p style="font-size:11px;color:#94a3b8;margin:0">' . esc_html(PPV_Lang::t('wai_upload_hint')) . '</p>
                    </div>
                    <div id="ra-wai-upload-status" style="display:none;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:12px"></div>

                    <!-- AI Chat -->
                    <div id="ra-wai-chat" style="border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden;background:#fff">
                        <div id="ra-wai-messages" style="height:280px;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px">
                            <div class="ra-wai-msg bot" style="background:#f1f5f9;border-radius:12px 12px 12px 4px;padding:10px 14px;font-size:13px;color:#334155;max-width:85%;line-height:1.5">
                                ' . esc_html(PPV_Lang::t('wai_chat_welcome')) . '<br><br>
                                ' . esc_html(PPV_Lang::t('wai_chat_options_intro')) . '<br>
                                • <b>' . esc_html(PPV_Lang::t('wai_chat_opt_prices')) . '</b><br>
                                • <b>' . esc_html(PPV_Lang::t('wai_chat_opt_brands')) . '</b><br>
                                • <b>' . esc_html(PPV_Lang::t('wai_chat_opt_design')) . '</b><br>
                                • <b>' . esc_html(PPV_Lang::t('wai_chat_opt_info')) . '</b>
                            </div>
                        </div>
                        <div style="border-top:1.5px solid #e2e8f0;display:flex;align-items:end;gap:8px;padding:10px 12px;background:#fafbfc">
                            <textarea id="ra-wai-input" rows="1" placeholder="' . esc_attr(PPV_Lang::t('wai_input_placeholder')) . '" style="flex:1;border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:13px;font-family:inherit;resize:none;outline:none;min-height:36px;max-height:100px;transition:border-color .2s;line-height:1.4"></textarea>
                            <button type="button" id="ra-wai-send" style="background:linear-gradient(135deg,' . $store_color . ',' . esc_attr(self::darken_hex($store_color, 0.25)) . ');border:none;color:#fff;width:36px;height:36px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .2s">
                                <i class="ri-send-plane-fill" style="font-size:16px"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Quick action chips -->
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px">
                        <button type="button" class="ra-wai-chip" data-msg="' . esc_attr(PPV_Lang::t('wai_chip_ai_mode_msg')) . '" style="padding:6px 12px;border:1.5px solid #e2e8f0;border-radius:20px;background:#fff;cursor:pointer;font-size:12px;color:#64748b;transition:all .2s">' . esc_html(PPV_Lang::t('wai_chip_ai_mode')) . '</button>
                        <button type="button" class="ra-wai-chip" data-msg="' . esc_attr(PPV_Lang::t('wai_chip_brands_msg')) . '" style="padding:6px 12px;border:1.5px solid #e2e8f0;border-radius:20px;background:#fff;cursor:pointer;font-size:12px;color:#64748b;transition:all .2s">' . esc_html(PPV_Lang::t('wai_chip_brands')) . '</button>
                        <button type="button" class="ra-wai-chip" data-msg="' . esc_attr(PPV_Lang::t('wai_chip_prices_msg')) . '" style="padding:6px 12px;border:1.5px solid #e2e8f0;border-radius:20px;background:#fff;cursor:pointer;font-size:12px;color:#64748b;transition:all .2s">' . esc_html(PPV_Lang::t('wai_chip_prices')) . '</button>
                        <button type="button" class="ra-wai-chip" data-msg="' . esc_attr(PPV_Lang::t('wai_chip_options_msg')) . '" style="padding:6px 12px;border:1.5px solid #e2e8f0;border-radius:20px;background:#fff;cursor:pointer;font-size:12px;color:#64748b;transition:all .2s">' . esc_html(PPV_Lang::t('wai_chip_options')) . '</button>
                    </div>
                </div>

            </div><!-- END PANEL: widget -->

            <!-- Save button visible on all panels -->
            <div class="ra-settings-save" style="padding:20px 24px;background:#f8fafc;border-top:1px solid #e2e8f0">
                <button type="submit" class="ra-btn ra-btn-primary ra-btn-full">
                    <i class="ri-save-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_save_settings')) . '
                </button>
            </div>
        </form>

        <!-- Legal pages -->
        <div class="ra-legal-links" style="padding:0 24px 24px">
            <h4>' . esc_html(PPV_Lang::t('repair_admin_auto_pages')) . '</h4>
            <a href="/formular/' . $store_slug . '/datenschutz" target="_blank"><i class="ri-shield-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_privacy')) . '</a>
            <a href="/formular/' . $store_slug . '/agb" target="_blank"><i class="ri-file-text-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_terms')) . '</a>
            <a href="/formular/' . $store_slug . '/impressum" target="_blank"><i class="ri-building-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_imprint')) . '</a>
        </div>

        <!-- PunktePass admin -->
        <div class="ra-pp-link" style="display:flex;gap:8px;align-items:center">
            <a href="/qr-center" class="ra-btn ra-btn-outline">
                <i class="ri-qr-code-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_pp_admin')) . '
            </a>
            <button class="ra-btn ra-btn-outline" style="color:#dc2626;border-color:#fecaca" onclick="if(confirm(\'' . esc_js(PPV_Lang::t('repair_admin_logout')) . '?\')){var fd=new FormData();fd.append(\'action\',\'ppv_repair_logout\');fetch(\'' . esc_url($ajax_url) . '\',{method:\'POST\',body:fd,credentials:\'same-origin\'}).finally(function(){window.location.href=\'/formular/admin/login\';})}">
                <i class="ri-logout-box-r-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_logout')) . '
            </button>
        </div>
    </div>';

        // Tabs: Reparaturen | Rechnung/Angebot | Kunden | Feedback
        echo '<div class="ra-tabs">
        <div class="ra-tab active" data-tab="repairs"><i class="ri-tools-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_repairs')) . '</div>
        <div class="ra-tab" data-tab="invoices"><i class="ri-file-list-3-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_inv_quote')) . '</div>
        <div class="ra-tab" data-tab="customers"><i class="ri-user-3-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_customers')) . '</div>
        <div class="ra-tab" data-tab="feedback"><i class="ri-feedback-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_feedback')) . '</div>
    </div>';

        // ========== TAB: Reparaturen ==========
        echo '<div class="ra-tab-content active" id="ra-tab-repairs">';

        // Search & Filters
        echo '<div class="ra-toolbar">
        <div class="ra-search">
            <i class="ri-search-line"></i>
            <input type="text" id="ra-search-input" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_search_ph')) . '">
        </div>
        <div class="ra-filters">
            <select id="ra-filter-status">
                <option value="">' . esc_html(PPV_Lang::t('repair_admin_status_all')) . '</option>
                <option value="new">' . esc_html(PPV_Lang::t('repair_admin_status_new')) . '</option>
                <option value="in_progress">' . esc_html(PPV_Lang::t('repair_admin_status_progress')) . '</option>
                <option value="waiting_parts">' . esc_html(PPV_Lang::t('repair_admin_status_waiting')) . '</option>
                <option value="done">' . esc_html(PPV_Lang::t('repair_admin_status_done')) . '</option>
                <option value="delivered">' . esc_html(PPV_Lang::t('repair_admin_status_delivered')) . '</option>
                <option value="cancelled">' . esc_html(PPV_Lang::t('repair_admin_status_cancelled')) . '</option>
            </select>
        </div>
    </div>';

        // Repairs list
        echo '<div class="ra-repairs" id="ra-repairs-list">' . $repairs_html . '</div>';

        // Load more
        if ($total_repairs > 20) {
            echo '<div class="ra-load-more"><button class="ra-btn ra-btn-outline" id="ra-load-more" data-page="1">' . esc_html(PPV_Lang::t('repair_admin_load_more')) . '</button></div>';
        }

        echo '</div>'; // end ra-tab-repairs

        // ========== TAB: Rechnungen ==========
        echo '<div class="ra-tab-content" id="ra-tab-invoices">';

        // Filters + New Invoice/Angebot/Ankauf buttons
        echo '<div class="ra-inv-filters">
            <button class="ra-btn ra-btn-primary ra-btn-sm" id="ra-new-invoice-btn">
                <i class="ri-add-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_new_invoice')) . '
            </button>
            <button class="ra-btn ra-btn-sm" id="ra-new-angebot-btn" style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0">
                <i class="ri-add-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_new_quote')) . '
            </button>
            <button class="ra-btn ra-btn-sm" id="ra-new-ankauf-btn" style="background:#fef3c7;color:#b45309;border:1px solid #fcd34d">
                <i class="ri-shopping-basket-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_new_purchase')) . '
            </button>
            <select id="ra-inv-type-filter" class="ra-input" style="padding:8px 12px;font-size:13px;min-width:120px">
                <option value="">' . esc_html(PPV_Lang::t('repair_admin_all_types')) . '</option>
                <option value="rechnung">' . esc_html(PPV_Lang::t('repair_admin_invoices')) . '</option>
                <option value="angebot">' . esc_html(PPV_Lang::t('repair_admin_quotes')) . '</option>
                <option value="ankauf">' . esc_html(PPV_Lang::t('repair_admin_purchases')) . '</option>
            </select>
            <div class="field">
                <label>' . esc_html(PPV_Lang::t('repair_admin_from')) . '</label>
                <input type="date" lang="' . esc_attr($lang) . '" id="ra-inv-from">
            </div>
            <div class="field">
                <label>' . esc_html(PPV_Lang::t('repair_admin_to')) . '</label>
                <input type="date" lang="' . esc_attr($lang) . '" id="ra-inv-to">
            </div>
            <button class="ra-btn ra-btn-outline ra-btn-sm" id="ra-inv-filter-btn">
                <i class="ri-filter-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_filter')) . '
            </button>
            <div style="position:relative;display:inline-block">
                <button class="ra-btn ra-btn-outline ra-btn-sm" id="ra-inv-export-btn">
                    <i class="ri-download-2-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_export')) . ' <i class="ri-arrow-down-s-line"></i>
                </button>
                <div id="ra-inv-export-dropdown" style="display:none;position:absolute;top:100%;left:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:220px;z-index:100;margin-top:4px">
                    <div style="padding:8px 0">
                        <div style="padding:6px 12px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">DATEV / CSV / Excel</div>
                        <a href="#" class="ra-inv-export-opt" data-format="datev" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-text-line" style="margin-right:8px;color:#8b5cf6"></i>DATEV-Export (.csv)</a>
                        <a href="#" class="ra-inv-export-opt" data-format="csv" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-excel-2-line" style="margin-right:8px;color:#059669"></i>CSV-Export</a>
                        <a href="#" class="ra-inv-export-opt" data-format="excel" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-excel-line" style="margin-right:8px;color:#217346"></i>Excel-Export (.xlsx)</a>
                        <div style="border-top:1px solid #e5e7eb;margin:6px 0"></div>
                        <div style="padding:6px 12px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">PDF / JSON</div>
                        <a href="#" class="ra-inv-export-opt" data-format="pdf" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-pdf-line" style="margin-right:8px;color:#dc2626"></i>PDF (.zip)</a>
                        <a href="#" class="ra-inv-export-opt" data-format="json" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-code-s-slash-line" style="margin-right:8px;color:#3b82f6"></i>JSON-Export</a>
                    </div>
                </div>
            </div>
            <button class="ra-btn ra-btn-outline ra-btn-sm" id="ra-billbee-import-btn" style="margin-left:auto;background:#fff7ed;color:#ea580c;border-color:#fed7aa">
                <i class="ri-upload-cloud-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_billbee')) . '
            </button>
        </div>';

        // Summary toggle button
        echo '<button class="ra-btn ra-btn-outline ra-btn-sm ra-summary-toggle" id="ra-inv-summary-toggle" style="margin-bottom:12px">
            <i class="ri-bar-chart-box-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_show_stats')) . '
        </button>';

        // Summary cards (hidden by default)
        echo '<div class="ra-inv-summary" id="ra-inv-summary" style="display:none">
            <div class="ra-inv-summary-card">
                <div class="ra-inv-summary-val" id="ra-inv-count">-</div>
                <div class="ra-inv-summary-label">' . esc_html(PPV_Lang::t('repair_admin_invoices')) . '</div>
            </div>
            <div class="ra-inv-summary-card">
                <div class="ra-inv-summary-val" id="ra-inv-revenue">-</div>
                <div class="ra-inv-summary-label">' . esc_html(PPV_Lang::t('repair_admin_revenue')) . '</div>
            </div>
            <div class="ra-inv-summary-card">
                <div class="ra-inv-summary-val" id="ra-inv-discounts">-</div>
                <div class="ra-inv-summary-label">' . esc_html(PPV_Lang::t('repair_admin_discounts')) . '</div>
            </div>
        </div>';

        // Bulk actions bar
        echo '<div id="ra-inv-bulk-bar" style="display:none;background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:12px 16px;margin-bottom:12px">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-weight:600;color:#4f46e5"><span id="ra-inv-selected-count">0</span> ' . esc_html(PPV_Lang::t('repair_admin_selected')) . '</span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button class="ra-btn ra-btn-sm" id="ra-bulk-paid" style="background:#059669;color:#fff"><i class="ri-checkbox-circle-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_mark_paid')) . '</button>
                    <button class="ra-btn ra-btn-sm" id="ra-bulk-send" style="background:#3b82f6;color:#fff"><i class="ri-mail-send-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_send_emails')) . '</button>
                    <button class="ra-btn ra-btn-sm" id="ra-bulk-reminder" style="background:#d97706;color:#fff"><i class="ri-alarm-warning-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_reminders')) . '</button>
                    <div style="position:relative;display:inline-block">
                        <button class="ra-btn ra-btn-sm" id="ra-bulk-export" style="background:#8b5cf6;color:#fff"><i class="ri-download-2-line"></i> Export <i class="ri-arrow-down-s-line"></i></button>
                        <div id="ra-export-dropdown" style="display:none;position:absolute;top:100%;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:220px;z-index:100;margin-top:4px">
                            <div style="padding:8px 0">
                                <div style="padding:6px 12px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">DATEV / CSV / Excel</div>
                                <a href="#" class="ra-export-opt" data-format="datev" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-text-line" style="margin-right:8px;color:#8b5cf6"></i>DATEV-Export (.csv)</a>
                                <a href="#" class="ra-export-opt" data-format="csv" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-excel-2-line" style="margin-right:8px;color:#059669"></i>CSV-Export</a>
                                <a href="#" class="ra-export-opt" data-format="excel" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-excel-line" style="margin-right:8px;color:#217346"></i>Excel-Export (.xlsx)</a>
                                <div style="border-top:1px solid #e5e7eb;margin:6px 0"></div>
                                <div style="padding:6px 12px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">PDF / JSON</div>
                                <a href="#" class="ra-export-opt" data-format="pdf" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-pdf-line" style="margin-right:8px;color:#dc2626"></i>PDF (.zip)</a>
                                <a href="#" class="ra-export-opt" data-format="json" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-code-s-slash-line" style="margin-right:8px;color:#3b82f6"></i>JSON-Export</a>
                            </div>
                        </div>
                    </div>
                    <button class="ra-btn ra-btn-sm" id="ra-bulk-delete" style="background:#dc2626;color:#fff"><i class="ri-delete-bin-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_delete')) . '</button>
                    <button class="ra-btn ra-btn-sm" id="ra-bulk-cancel" style="background:#6b7280;color:#fff"><i class="ri-close-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_cancel')) . '</button>
                </div>
            </div>
        </div>';

        // Invoice table
        echo '<div style="overflow-x:auto">
        <table class="ra-inv-table">
            <thead>
                <tr>
                    <th style="width:40px"><input type="checkbox" id="ra-inv-select-all" style="width:16px;height:16px;cursor:pointer"></th>
                    <th class="ra-sortable" data-sort="invoice_number">' . esc_html(PPV_Lang::t('repair_admin_col_nr')) . ' <i class="ri-arrow-up-down-line ra-sort-icon"></i></th>
                    <th class="ra-sortable" data-sort="created_at">' . esc_html(PPV_Lang::t('repair_admin_col_date')) . ' <i class="ri-arrow-up-down-line ra-sort-icon"></i></th>
                    <th class="ra-sortable" data-sort="customer_name">' . esc_html(PPV_Lang::t('repair_admin_col_customer')) . ' <i class="ri-arrow-up-down-line ra-sort-icon"></i></th>
                    <th class="ra-sortable" data-sort="net_amount">' . esc_html(PPV_Lang::t('repair_admin_col_net')) . ' <i class="ri-arrow-up-down-line ra-sort-icon"></i></th>
                    <th>' . esc_html(PPV_Lang::t('repair_admin_col_vat')) . '</th>
                    <th class="ra-sortable" data-sort="total">' . esc_html(PPV_Lang::t('repair_admin_col_total')) . ' <i class="ri-arrow-up-down-line ra-sort-icon"></i></th>
                    <th class="ra-sortable" data-sort="status">' . esc_html(PPV_Lang::t('repair_admin_col_status')) . ' <i class="ri-arrow-up-down-line ra-sort-icon"></i></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="ra-inv-body">
                <tr><td colspan="9" style="text-align:center;padding:32px;color:#9ca3af;">' . esc_html(PPV_Lang::t('repair_admin_loading_inv')) . '</td></tr>
            </tbody>
        </table>
        </div>
        <div class="ra-load-more" id="ra-inv-load-more" style="display:none">
            <button class="ra-btn ra-btn-outline" id="ra-inv-more-btn" data-page="1">' . esc_html(PPV_Lang::t('repair_admin_load_more')) . '</button>
        </div>';

        echo '</div>'; // end ra-tab-invoices

        // ========== TAB: Kunden ==========
        echo '<div class="ra-tab-content" id="ra-tab-customers">';

        // Customer toolbar
        echo '<div class="ra-inv-filters">
            <button class="ra-btn ra-btn-primary ra-btn-sm" id="ra-new-customer-btn">
                <i class="ri-user-add-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_new_customer')) . '
            </button>
            <div class="ra-search" style="flex:1;max-width:400px">
                <i class="ri-search-line"></i>
                <input type="text" id="ra-customer-search" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_search_customer')) . '">
            </div>
        </div>';

        // Customer summary
        echo '<div class="ra-inv-summary" id="ra-cust-summary">
            <div class="ra-inv-summary-card">
                <div class="ra-inv-summary-val" id="ra-cust-count">-</div>
                <div class="ra-inv-summary-label">' . esc_html(PPV_Lang::t('repair_admin_customers')) . '</div>
            </div>
        </div>';

        // Customer table
        echo '<div style="overflow-x:auto">
        <table class="ra-inv-table">
            <thead>
                <tr>
                    <th>' . esc_html(PPV_Lang::t('repair_admin_col_name')) . '</th>
                    <th>' . esc_html(PPV_Lang::t('repair_admin_col_company')) . '</th>
                    <th>' . esc_html(PPV_Lang::t('repair_admin_col_email')) . '</th>
                    <th>' . esc_html(PPV_Lang::t('repair_admin_col_phone')) . '</th>
                    <th>' . esc_html(PPV_Lang::t('repair_admin_col_city')) . '</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="ra-cust-body">
                <tr><td colspan="6" style="text-align:center;padding:32px;color:#9ca3af;">' . esc_html(PPV_Lang::t('repair_admin_loading_cust')) . '</td></tr>
            </tbody>
        </table>
        </div>
        <div class="ra-load-more" id="ra-cust-load-more" style="display:none">
            <button class="ra-btn ra-btn-outline" id="ra-cust-more-btn" data-page="1">' . esc_html(PPV_Lang::t('repair_admin_load_more')) . '</button>
        </div>';

        echo '</div>'; // end ra-tab-customers

        // ========== TAB: Feedback ==========
        echo '<div class="ra-tab-content" id="ra-tab-feedback">
        <div class="ra-settings" style="max-width:600px">
            <h3><i class="ri-feedback-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_fb_title')) . '</h3>
            <p style="font-size:14px;color:#6b7280;margin-bottom:20px">' . esc_html(PPV_Lang::t('repair_admin_fb_desc')) . '</p>

            <div style="margin-bottom:20px">
                <label style="font-size:12px;font-weight:600;color:#6b7280;display:block;margin-bottom:8px">' . esc_html(PPV_Lang::t('repair_admin_fb_category')) . '</label>
                <div class="ra-feedback-cats-inline" id="ra-fb-cats">
                    <button type="button" class="ra-fb-cat-btn" data-cat="bug"><i class="ri-bug-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_fb_bug')) . '</button>
                    <button type="button" class="ra-fb-cat-btn" data-cat="feature"><i class="ri-lightbulb-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_fb_idea')) . '</button>
                    <button type="button" class="ra-fb-cat-btn" data-cat="question"><i class="ri-question-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_fb_question')) . '</button>
                    <button type="button" class="ra-fb-cat-btn" data-cat="rating"><i class="ri-star-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_fb_rating')) . '</button>
                </div>
            </div>

            <div id="ra-fb-rating-section" style="display:none;margin-bottom:20px">
                <label style="font-size:12px;font-weight:600;color:#6b7280;display:block;margin-bottom:8px">' . esc_html(PPV_Lang::t('repair_admin_fb_rating')) . '</label>
                <div class="ra-fb-stars">
                    <button type="button" class="ra-fb-star" data-rating="1"><i class="ri-star-line"></i></button>
                    <button type="button" class="ra-fb-star" data-rating="2"><i class="ri-star-line"></i></button>
                    <button type="button" class="ra-fb-star" data-rating="3"><i class="ri-star-line"></i></button>
                    <button type="button" class="ra-fb-star" data-rating="4"><i class="ri-star-line"></i></button>
                    <button type="button" class="ra-fb-star" data-rating="5"><i class="ri-star-line"></i></button>
                </div>
            </div>

            <div class="field" style="margin-bottom:16px">
                <label style="font-size:12px;font-weight:600;color:#6b7280;display:block;margin-bottom:6px">' . esc_html(PPV_Lang::t('repair_admin_fb_message')) . '</label>
                <textarea id="ra-fb-message" class="ra-input" rows="5" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_fb_message_ph')) . '" style="width:100%;resize:vertical"></textarea>
            </div>

            <div class="field" style="margin-bottom:20px">
                <label style="font-size:12px;font-weight:600;color:#6b7280;display:block;margin-bottom:6px">' . esc_html(PPV_Lang::t('repair_admin_fb_email')) . '</label>
                <input type="email" id="ra-fb-email" class="ra-input" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_fb_email_ph')) . '" style="width:100%">
            </div>

            <div id="ra-fb-msg" class="ra-fb-msg"></div>

            <button type="button" class="ra-btn ra-btn-primary" id="ra-fb-submit" style="width:100%">
                <i class="ri-send-plane-fill"></i> ' . esc_html(PPV_Lang::t('repair_admin_fb_submit')) . '
            </button>
        </div>
        </div>'; // end ra-tab-feedback

        // Toast notification
        echo '<div class="ra-toast" id="ra-toast"></div>';

        // Reward reject modal
        echo '<div class="ra-reject-modal" id="ra-reject-modal">
            <div class="ra-reject-modal-content">
                <h3><i class="ri-close-circle-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_reject_title')) . '</h3>
                <p style="font-size:13px;color:#6b7280;margin-bottom:12px">' . esc_html(PPV_Lang::t('repair_admin_reject_hint')) . '</p>
                <textarea id="ra-reject-reason" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_reject_ph')) . '"></textarea>
                <input type="hidden" id="ra-reject-repair-id" value="">
                <div class="ra-reject-modal-actions">
                    <button class="ra-btn ra-btn-outline" id="ra-reject-cancel">' . esc_html(PPV_Lang::t('repair_admin_cancel')) . '</button>
                    <button class="ra-btn" style="background:#dc2626;color:#fff" id="ra-reject-submit">' . esc_html(PPV_Lang::t('repair_admin_reject_btn')) . '</button>
                </div>
            </div>
        </div>';

        // Invoice modal (shown when status changes to "Fertig")
        echo '<div class="ra-modal-overlay" id="ra-invoice-modal">
    <div class="ra-modal">
        <h3 id="ra-inv-modal-title"><i class="ri-file-list-3-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_finish_repair')) . '</h3>
        <p class="ra-modal-sub" id="ra-inv-modal-subtitle">' . esc_html(PPV_Lang::t('repair_admin_finish_sub')) . '</p>
        <div id="ra-inv-modal-info" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#0369a1;display:none"></div>
        <div class="ra-inv-lines" id="ra-inv-lines">
            <div class="ra-inv-line">
                <input type="text" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_service_ph')) . '" class="ra-inv-line-desc">
                <input type="number" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_gross')) . '" step="0.01" min="0" class="ra-inv-line-amount">
                <button type="button" class="ra-inv-line-remove" title="' . esc_attr(PPV_Lang::t('repair_admin_delete')) . '">&times;</button>
            </div>
        </div>
        <button type="button" class="ra-inv-add" id="ra-inv-add-line">
            <i class="ri-add-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_add_line')) . '
        </button>
        <div id="ra-inv-discount-section" style="display:none;margin:12px 0;padding:12px 14px;background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border:1.5px solid #86efac;border-radius:10px">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                <i class="ri-gift-line" style="color:#059669;font-size:16px"></i>
                <span style="font-size:13px;font-weight:700;color:#059669">' . esc_html(PPV_Lang::t('repair_admin_reward_discount')) . '</span>
            </div>
            <div style="display:flex;gap:10px;align-items:center">
                <input type="text" id="ra-inv-discount-desc" class="ra-input" style="flex:2;font-size:13px;background:#fff" placeholder="Rabatt">
                <div style="display:flex;align-items:center;gap:4px;flex:1">
                    <span style="color:#dc2626;font-weight:700;font-size:14px">&minus;</span>
                    <input type="number" id="ra-inv-discount-amount" class="ra-input" style="flex:1;font-size:13px;background:#fff;color:#dc2626;font-weight:600" step="0.01" min="0" placeholder="0.00">
                    <span style="color:#dc2626;font-weight:600;font-size:13px">&euro;</span>
                </div>
                <button type="button" id="ra-inv-discount-remove" style="width:28px;height:28px;border:none;background:#fee2e2;color:#dc2626;border-radius:6px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center" title="' . esc_attr(PPV_Lang::t('repair_admin_delete')) . '">&times;</button>
            </div>
        </div>
        <div class="ra-inv-totals">
            <div class="ra-inv-total-row">
                <span>' . esc_html(PPV_Lang::t('repair_admin_net')) . '</span>
                <span id="ra-inv-modal-net">0,00 &euro;</span>
            </div>
            <div class="ra-inv-total-row" id="ra-inv-modal-vat-row">
                <span>' . esc_html(PPV_Lang::t('repair_admin_col_vat')) . ' <span id="ra-inv-modal-vat-pct">' . intval($vat_rate) . '</span>%:</span>
                <span id="ra-inv-modal-vat">0,00 &euro;</span>
            </div>
            <div class="ra-inv-total-row ra-inv-total-final">
                <span>' . esc_html(PPV_Lang::t('repair_admin_total')) . '</span>
                <span id="ra-inv-modal-total">0,00 &euro;</span>
            </div>
        </div>

        <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600">
                <input type="checkbox" id="ra-inv-paid-toggle" style="width:18px;height:18px;cursor:pointer">
                ' . esc_html(PPV_Lang::t('repair_admin_already_paid')) . '
            </label>
            <div id="ra-inv-paid-fields" style="display:none;margin-top:12px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_payment_method')) . '</label>
                        <select id="ra-inv-payment-method" class="ra-input" style="width:100%">
                            <option value="">' . esc_html(PPV_Lang::t('repair_admin_please_select')) . '</option>
                            <option value="bar">' . esc_html(PPV_Lang::t('repair_admin_pay_cash')) . '</option>
                            <option value="ec">' . esc_html(PPV_Lang::t('repair_admin_pay_ec')) . '</option>
                            <option value="kreditkarte">' . esc_html(PPV_Lang::t('repair_admin_pay_credit')) . '</option>
                            <option value="ueberweisung">' . esc_html(PPV_Lang::t('repair_admin_pay_transfer')) . '</option>
                            <option value="paypal">' . esc_html(PPV_Lang::t('repair_admin_pay_paypal')) . '</option>
                            <option value="andere">' . esc_html(PPV_Lang::t('repair_admin_pay_other')) . '</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_payment_date')) . '</label>
                        <input type="date" lang="' . esc_attr($lang) . '" id="ra-inv-paid-date" class="ra-input" style="width:100%">
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:12px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
                <input type="checkbox" id="ra-inv-differenz" style="width:18px;height:18px;cursor:pointer">
                <span>' . esc_html(PPV_Lang::t('repair_admin_diff_tax')) . '</span>
            </label>
        </div>

        <div style="margin-top:12px">
            <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px"><i class="ri-shield-check-line" style="font-size:13px;vertical-align:-1px"></i> ' . esc_html(PPV_Lang::t('repair_admin_warranty_date')) . '</label>
            <input type="date" lang="' . esc_attr($lang) . '" id="ra-inv-warranty-date" class="ra-input" style="width:100%;max-width:200px">
            <textarea id="ra-inv-warranty-desc" class="ra-input" rows="2" style="width:100%;margin-top:6px;font-size:12px;display:none" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_warranty_desc_ph')) . '"></textarea>
        </div>

        <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
            <button type="button" class="ra-btn ra-btn-outline" style="flex:1;min-width:100px" id="ra-inv-modal-cancel">' . esc_html(PPV_Lang::t('repair_admin_cancel')) . '</button>
            <button type="button" class="ra-btn" style="flex:1;min-width:140px;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0" id="ra-inv-modal-skip">
                <i class="ri-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_only_done')) . '
            </button>
            <button type="button" class="ra-btn ra-btn-primary" style="flex:2;min-width:180px" id="ra-inv-modal-submit">
                <i class="ri-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_finish_invoice')) . '
            </button>
        </div>
    </div>
</div>';

        // Termin Modal (Teil angekommen - part arrived, schedule appointment)
        echo '<div class="ra-modal-overlay" id="ra-termin-modal">
    <div class="ra-modal" style="max-width:440px">
        <h3 style="display:flex;align-items:center;gap:8px"><i class="ri-checkbox-circle-fill" style="color:#059669"></i> ' . esc_html(PPV_Lang::t('repair_admin_parts_arrived')) . '</h3>
        <p class="ra-modal-sub">' . esc_html(PPV_Lang::t('repair_admin_parts_arrived_desc')) . '</p>
        <div id="ra-termin-info" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#0369a1;display:none"></div>

        <div style="margin-bottom:16px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600">
                <input type="checkbox" id="ra-termin-no-termin" style="width:18px;height:18px;cursor:pointer;accent-color:#d97706">
                <i class="ri-checkbox-circle-fill" style="color:#d97706"></i> ' . esc_html(PPV_Lang::t('repair_admin_no_termin')) . '
            </label>
            <p style="margin:6px 0 0 26px;font-size:12px;color:#92400e">' . esc_html(PPV_Lang::t('repair_admin_no_termin_desc')) . '</p>
        </div>

        <div id="ra-termin-fields">
        <div style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px"><i class="ri-calendar-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_termin_date')) . '</label>
            <input type="date" lang="' . esc_attr($lang) . '" id="ra-termin-date" class="ra-input" style="width:100%;font-size:15px;padding:10px 12px">
        </div>
        <div style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px"><i class="ri-time-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_termin_time')) . '</label>
            <input type="time" id="ra-termin-time" class="ra-input" style="width:100%;font-size:15px;padding:10px 12px">
        </div>
        <div style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px"><i class="ri-chat-1-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_termin_message')) . '</label>
            <textarea id="ra-termin-message" class="ra-input" rows="2" style="width:100%;font-size:13px;padding:10px 12px;resize:vertical" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_termin_message_ph')) . '"></textarea>
        </div>

        <div style="margin-bottom:16px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600">
                <input type="checkbox" id="ra-termin-send-email" checked style="width:18px;height:18px;cursor:pointer;accent-color:#059669">
                <i class="ri-mail-send-line" style="color:#059669"></i> ' . esc_html(PPV_Lang::t('repair_admin_termin_send_email')) . '
            </label>
        </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:16px">
            <button type="button" class="ra-btn ra-btn-outline" style="flex:1" id="ra-termin-cancel">' . esc_html(PPV_Lang::t('repair_admin_cancel')) . '</button>
            <button type="button" class="ra-btn ra-btn-primary" style="flex:2;background:#059669" id="ra-termin-submit">
                <i class="ri-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_termin_confirm')) . '
            </button>
        </div>
    </div>
</div>';

        // New Invoice Modal (standalone invoice creation)
        // New Invoice Modal - Modern Design
        echo '<div class="ra-modal-overlay" id="ra-new-invoice-modal">
    <div class="ra-modal nang-modal ninv-modal">

        <!-- Header -->
        <div class="nang-header" style="background:linear-gradient(135deg,#2563eb,#3b82f6)">
            <div class="nang-header-icon"><i class="ri-file-list-3-line"></i></div>
            <div>
                <h3>' . esc_html(PPV_Lang::t('repair_admin_create_invoice')) . '</h3>
                <p>' . esc_html(PPV_Lang::t('repair_admin_create_inv_sub')) . '</p>
            </div>
            <button type="button" class="nang-close" id="ra-ninv-close">&times;</button>
        </div>

        <!-- Step indicator -->
        <div class="nang-steps">
            <div class="nang-step active" data-step="1"><span class="nang-step-num" style="background:#2563eb;color:#fff">1</span> ' . esc_html(PPV_Lang::t('repair_admin_customer_data')) . '</div>
            <div class="nang-step-line"></div>
            <div class="nang-step" data-step="2"><span class="nang-step-num">2</span> ' . esc_html(PPV_Lang::t('repair_admin_positions')) . '</div>
        </div>

        <!-- SECTION 1: Customer -->
        <div class="nang-section" id="ninv-sec-customer">

            <!-- Search -->
            <div class="nang-search-wrap" style="--focus-color:#2563eb">
                <i class="ri-search-line"></i>
                <input type="text" id="ra-ninv-customer-search" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_cust_search_ph')) . '" autocomplete="off">
                <kbd>ESC</kbd>
            </div>
            <div id="ra-ninv-customer-results" class="nang-search-results"></div>

            <!-- Customer fields -->
            <div class="nang-fields">
                <div class="nang-field-row">
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_name_required')) . '</label>
                        <div class="nang-input-wrap ninv-focus">
                            <i class="ri-user-line"></i>
                            <input type="text" id="ra-ninv-name" placeholder="Max Mustermann" required>
                        </div>
                    </div>
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_company')) . '</label>
                        <div class="nang-input-wrap ninv-focus">
                            <i class="ri-building-line"></i>
                            <input type="text" id="ra-ninv-company" placeholder="Firma GmbH">
                        </div>
                    </div>
                </div>
                <div class="nang-field-row">
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_email')) . '</label>
                        <div class="nang-input-wrap ninv-focus">
                            <i class="ri-mail-line"></i>
                            <input type="email" id="ra-ninv-email" placeholder="email@example.de">
                        </div>
                    </div>
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_col_phone')) . '</label>
                        <div class="nang-input-wrap ninv-focus">
                            <i class="ri-phone-line"></i>
                            <input type="text" id="ra-ninv-phone" placeholder="+49...">
                        </div>
                    </div>
                </div>
                <div class="nang-field-row">
                    <div class="nang-field" style="flex:1">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_full_address')) . '</label>
                        <div class="nang-input-wrap ninv-focus">
                            <i class="ri-map-pin-line"></i>
                            <input type="text" id="ra-ninv-full-address" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_full_address_ph')) . '">
                        </div>
                        <span class="nang-field-hint" id="ninv-address-hint"></span>
                    </div>
                </div>
                <div class="nang-field-row">
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_vat_id')) . '</label>
                        <div class="nang-input-wrap ninv-focus">
                            <i class="ri-hashtag"></i>
                            <input type="text" id="ra-ninv-taxid" placeholder="DE123456789">
                        </div>
                    </div>
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_inv_number')) . '</label>
                        <div class="nang-input-wrap ninv-focus" style="background:#eff6ff;border-color:#bfdbfe">
                            <i class="ri-file-text-line" style="color:#2563eb"></i>
                            <input type="text" id="ra-ninv-number" placeholder="' . esc_attr($inv_prefix . str_pad($inv_suggested_next, 4, '0', STR_PAD_LEFT)) . '">
                        </div>
                        <span class="nang-field-hint">' . sprintf(esc_html(PPV_Lang::t('repair_admin_inv_auto_hint')), esc_html($inv_prefix . str_pad($inv_suggested_next, 4, '0', STR_PAD_LEFT))) . '</span>
                    </div>
                </div>
            </div>

            <input type="hidden" id="ra-ninv-customer-id" value="">
            <input type="hidden" id="ra-ninv-address" value="">
            <input type="hidden" id="ra-ninv-plz" value="">
            <input type="hidden" id="ra-ninv-city" value="">

            <button type="button" class="nang-next-btn" id="ninv-next-to-positions" style="background:linear-gradient(135deg,#2563eb,#3b82f6);box-shadow:0 2px 8px rgba(37,99,235,.25)">
                ' . esc_html(PPV_Lang::t('repair_admin_positions')) . ' <i class="ri-arrow-right-line"></i>
            </button>

            <!-- Quick actions for repair→done mode (visible on step 1) -->
            <div class="ninv-step1-actions" id="ninv-step1-actions" style="display:none">
                <button type="button" id="ra-ninv-skip-step1" class="ninv-skip-btn">
                    <i class="ri-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_only_done')) . '
                </button>
                <button type="button" class="nang-cancel-btn" id="ra-ninv-cancel-step1">' . esc_html(PPV_Lang::t('repair_admin_cancel')) . '</button>
            </div>
        </div>

        <!-- SECTION 2: Positions + Totals -->
        <div class="nang-section" id="ninv-sec-positions" style="display:none">

            <button type="button" class="nang-back-link" id="ninv-back-to-customer">
                <i class="ri-arrow-left-s-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_customer_data')) . '
            </button>

            <!-- Customer summary chip -->
            <div class="nang-customer-chip" id="ninv-customer-chip" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af"></div>

            <!-- Line items -->
            <div class="nang-positions-header">
                <span class="nang-ph-desc">' . esc_html(PPV_Lang::t('repair_admin_service')) . '</span>
                <span class="nang-ph-qty">' . esc_html(PPV_Lang::t('repair_admin_qty')) . '</span>
                <span class="nang-ph-price">' . esc_html(PPV_Lang::t('repair_admin_unit_price')) . '</span>
                <span class="nang-ph-total">' . esc_html(PPV_Lang::t('repair_admin_line_total')) . '</span>
                <span class="nang-ph-del"></span>
            </div>
            <div class="nang-lines" id="ra-ninv-lines">
                <div class="nang-line" data-idx="0">
                    <input type="text" placeholder="z.B. Displaytausch iPhone 15" class="nang-line-desc">
                    <input type="number" value="1" min="1" step="1" class="nang-line-qty">
                    <div class="nang-line-price-wrap">
                        <input type="number" placeholder="0,00" step="0.01" min="0" class="nang-line-price">
                        <span class="nang-line-currency">&euro;</span>
                    </div>
                    <span class="nang-line-total">0,00 &euro;</span>
                    <button type="button" class="nang-line-del" title="' . esc_attr(PPV_Lang::t('repair_admin_delete')) . '"><i class="ri-delete-bin-line"></i></button>
                </div>
            </div>
            <button type="button" class="nang-add-line" id="ra-ninv-add-line">
                <i class="ri-add-circle-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_add_position')) . '
            </button>

            <!-- Totals card -->
            <div class="nang-totals-card">
                <div class="nang-totals-row">
                    <span>' . esc_html(PPV_Lang::t('repair_admin_net')) . '</span>
                    <span id="ra-ninv-net">0,00 &euro;</span>
                </div>
                <div class="nang-totals-row" id="ra-ninv-vat-row">
                    <span>' . esc_html(PPV_Lang::t('repair_admin_col_vat')) . ' ' . intval($vat_rate) . '%</span>
                    <span id="ra-ninv-vat">0,00 &euro;</span>
                </div>
                <div class="nang-totals-row nang-totals-final" style="border-color:#2563eb;color:#2563eb">
                    <span>' . esc_html(PPV_Lang::t('repair_admin_gross')) . '</span>
                    <span id="ra-ninv-total">0,00 &euro;</span>
                </div>
            </div>

            <!-- Reward discount (only visible when from repair with reward) -->
            <div id="ra-ninv-discount-section" class="ninv-discount-section" style="display:none">
                <div class="ninv-discount-header">
                    <i class="ri-gift-line"></i>
                    <span>' . esc_html(PPV_Lang::t('repair_admin_reward_discount')) . '</span>
                </div>
                <div class="ninv-discount-row">
                    <input type="text" id="ra-ninv-discount-desc" class="nang-meta-input" style="flex:2" placeholder="Rabatt">
                    <div style="display:flex;align-items:center;gap:4px;flex:1">
                        <span style="color:#dc2626;font-weight:700;font-size:14px">&minus;</span>
                        <input type="number" id="ra-ninv-discount-amount" class="nang-meta-input" style="flex:1;color:#dc2626;font-weight:600" step="0.01" min="0" placeholder="0.00">
                        <span style="color:#dc2626;font-weight:600;font-size:13px">&euro;</span>
                    </div>
                    <button type="button" id="ra-ninv-discount-remove" class="ninv-discount-remove" title="' . esc_attr(PPV_Lang::t('repair_admin_delete')) . '">&times;</button>
                </div>
            </div>

            <!-- Options -->
            <div class="ninv-options">
                <label class="ninv-checkbox-label">
                    <input type="checkbox" id="ra-ninv-differenz">
                    <span class="ninv-checkmark"><i class="ri-check-line"></i></span>
                    <span>' . esc_html(PPV_Lang::t('repair_admin_diff_tax')) . '</span>
                </label>
            </div>

            <!-- Payment section (only visible in repair→done mode) -->
            <div id="ra-ninv-payment-section" style="display:none">
                <div class="ninv-options">
                    <label class="ninv-checkbox-label">
                        <input type="checkbox" id="ra-ninv-paid-toggle">
                        <span class="ninv-checkmark"><i class="ri-check-line"></i></span>
                        <span>' . esc_html(PPV_Lang::t('repair_admin_already_paid')) . '</span>
                    </label>
                </div>
                <div id="ra-ninv-paid-fields" style="display:none;margin-bottom:16px">
                    <div class="nang-field-row">
                        <div class="nang-field nang-field-grow">
                            <label>' . esc_html(PPV_Lang::t('repair_admin_payment_method')) . '</label>
                            <div class="nang-input-wrap ninv-focus">
                                <i class="ri-bank-card-line"></i>
                                <select id="ra-ninv-payment-method" style="border:none;outline:none;background:transparent;font-size:14px;width:100%;padding:2px 0;color:#334155">
                                    <option value="">' . esc_html(PPV_Lang::t('repair_admin_please_select')) . '</option>
                                    <option value="bar">' . esc_html(PPV_Lang::t('repair_admin_pay_cash')) . '</option>
                                    <option value="ec">' . esc_html(PPV_Lang::t('repair_admin_pay_ec')) . '</option>
                                    <option value="kreditkarte">' . esc_html(PPV_Lang::t('repair_admin_pay_credit')) . '</option>
                                    <option value="ueberweisung">' . esc_html(PPV_Lang::t('repair_admin_pay_transfer')) . '</option>
                                    <option value="paypal">' . esc_html(PPV_Lang::t('repair_admin_pay_paypal')) . '</option>
                                    <option value="andere">' . esc_html(PPV_Lang::t('repair_admin_pay_other')) . '</option>
                                </select>
                            </div>
                        </div>
                        <div class="nang-field nang-field-grow">
                            <label>' . esc_html(PPV_Lang::t('repair_admin_payment_date')) . '</label>
                            <div class="nang-input-wrap ninv-focus">
                                <i class="ri-calendar-line"></i>
                                <input type="date" lang="' . esc_attr($lang) . '" id="ra-ninv-paid-date" style="border:none;outline:none;background:transparent;font-size:14px;width:100%;padding:2px 0;color:#334155">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warranty -->
            <div class="nang-field" style="margin-bottom:4px">
                <label><i class="ri-shield-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_warranty_date')) . '</label>
                <div class="nang-input-wrap ninv-focus" style="max-width:220px">
                    <i class="ri-calendar-check-line"></i>
                    <input type="date" lang="' . esc_attr($lang) . '" id="ra-ninv-warranty-date" style="border:none;outline:none;background:transparent;font-size:14px;width:100%;padding:2px 0;color:#334155">
                </div>
            </div>
            <div class="nang-field" id="ra-ninv-warranty-desc-wrap" style="margin-bottom:12px;display:none">
                <label>' . esc_html(PPV_Lang::t('repair_admin_warranty_desc')) . '</label>
                <textarea id="ra-ninv-warranty-desc" class="nang-meta-input" rows="2" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_warranty_desc_ph')) . '" style="resize:vertical;font-size:12px"></textarea>
            </div>

            <!-- Notes -->
            <div class="nang-field" style="margin-bottom:20px">
                <label><i class="ri-sticky-note-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_notes')) . '</label>
                <textarea id="ra-ninv-notes" class="nang-meta-input" rows="2" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_notes_ph')) . '" style="resize:vertical"></textarea>
            </div>

            <!-- Action buttons -->
            <div class="nang-actions">
                <button type="button" class="nang-cancel-btn" id="ra-ninv-cancel">' . esc_html(PPV_Lang::t('repair_admin_cancel')) . '</button>
                <button type="button" id="ra-ninv-skip" class="ninv-skip-btn" style="display:none">
                    <i class="ri-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_only_done')) . '
                </button>
                <button type="button" class="nang-submit-btn" id="ra-ninv-submit" style="background:linear-gradient(135deg,#2563eb,#3b82f6);box-shadow:0 2px 8px rgba(37,99,235,.25)">
                    <i class="ri-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_create_inv_btn')) . '
                </button>
            </div>
        </div>

    </div>
</div>';

        // New Angebot Modal (quote creation) - Modern Design
        echo '<div class="ra-modal-overlay" id="ra-new-angebot-modal">
    <div class="ra-modal nang-modal">

        <!-- Header -->
        <div class="nang-header">
            <div class="nang-header-icon"><i class="ri-draft-line"></i></div>
            <div>
                <h3>' . esc_html(PPV_Lang::t('repair_admin_create_quote')) . '</h3>
                <p>' . esc_html(PPV_Lang::t('repair_admin_create_quote_sub')) . '</p>
            </div>
            <button type="button" class="nang-close" id="ra-nang-close">&times;</button>
        </div>

        <!-- Step indicator -->
        <div class="nang-steps">
            <div class="nang-step active" data-step="1"><span class="nang-step-num">1</span> ' . esc_html(PPV_Lang::t('repair_admin_customer_data')) . '</div>
            <div class="nang-step-line"></div>
            <div class="nang-step" data-step="2"><span class="nang-step-num">2</span> ' . esc_html(PPV_Lang::t('repair_admin_positions')) . '</div>
        </div>

        <!-- SECTION 1: Customer -->
        <div class="nang-section" id="nang-sec-customer">

            <!-- Search -->
            <div class="nang-search-wrap">
                <i class="ri-search-line"></i>
                <input type="text" id="ra-nang-customer-search" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_cust_search_ph')) . '" autocomplete="off">
                <kbd>ESC</kbd>
            </div>
            <div id="ra-nang-customer-results" class="nang-search-results"></div>

            <!-- Customer fields -->
            <div class="nang-fields">
                <div class="nang-field-row">
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_name_required')) . '</label>
                        <div class="nang-input-wrap">
                            <i class="ri-user-line"></i>
                            <input type="text" id="ra-nang-name" placeholder="Max Mustermann" required>
                        </div>
                    </div>
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_company')) . '</label>
                        <div class="nang-input-wrap">
                            <i class="ri-building-line"></i>
                            <input type="text" id="ra-nang-company" placeholder="Firma GmbH">
                        </div>
                    </div>
                </div>
                <div class="nang-field-row">
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_email')) . '</label>
                        <div class="nang-input-wrap">
                            <i class="ri-mail-line"></i>
                            <input type="email" id="ra-nang-email" placeholder="email@example.de">
                        </div>
                    </div>
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_col_phone')) . '</label>
                        <div class="nang-input-wrap">
                            <i class="ri-phone-line"></i>
                            <input type="text" id="ra-nang-phone" placeholder="+49...">
                        </div>
                    </div>
                </div>
                <div class="nang-field-row">
                    <div class="nang-field" style="flex:1">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_full_address')) . '</label>
                        <div class="nang-input-wrap">
                            <i class="ri-map-pin-line"></i>
                            <input type="text" id="ra-nang-full-address" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_full_address_ph')) . '">
                        </div>
                        <span class="nang-field-hint" id="nang-address-hint"></span>
                    </div>
                </div>
            </div>

            <input type="hidden" id="ra-nang-customer-id" value="">
            <input type="hidden" id="ra-nang-address" value="">
            <input type="hidden" id="ra-nang-plz" value="">
            <input type="hidden" id="ra-nang-city" value="">

            <button type="button" class="nang-next-btn" id="nang-next-to-positions">
                ' . esc_html(PPV_Lang::t('repair_admin_positions')) . ' <i class="ri-arrow-right-line"></i>
            </button>
        </div>

        <!-- SECTION 2: Positions + Totals -->
        <div class="nang-section" id="nang-sec-positions" style="display:none">

            <button type="button" class="nang-back-link" id="nang-back-to-customer">
                <i class="ri-arrow-left-s-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_customer_data')) . '
            </button>

            <!-- Customer summary chip -->
            <div class="nang-customer-chip" id="nang-customer-chip"></div>

            <!-- Line items -->
            <div class="nang-positions-header">
                <span class="nang-ph-desc">' . esc_html(PPV_Lang::t('repair_admin_service')) . '</span>
                <span class="nang-ph-qty">' . esc_html(PPV_Lang::t('repair_admin_qty')) . '</span>
                <span class="nang-ph-price">' . esc_html(PPV_Lang::t('repair_admin_unit_price')) . '</span>
                <span class="nang-ph-total">' . esc_html(PPV_Lang::t('repair_admin_line_total')) . '</span>
                <span class="nang-ph-del"></span>
            </div>
            <div class="nang-lines" id="ra-nang-lines">
                <div class="nang-line" data-idx="0">
                    <input type="text" placeholder="z.B. Displaytausch iPhone 15" class="nang-line-desc">
                    <input type="number" value="1" min="1" step="1" class="nang-line-qty">
                    <div class="nang-line-price-wrap">
                        <input type="number" placeholder="0,00" step="0.01" min="0" class="nang-line-price">
                        <span class="nang-line-currency">&euro;</span>
                    </div>
                    <span class="nang-line-total">0,00 &euro;</span>
                    <button type="button" class="nang-line-del" title="' . esc_attr(PPV_Lang::t('repair_admin_delete')) . '"><i class="ri-delete-bin-line"></i></button>
                </div>
            </div>
            <button type="button" class="nang-add-line" id="ra-nang-add-line">
                <i class="ri-add-circle-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_add_position')) . '
            </button>

            <!-- Totals card -->
            <div class="nang-totals-card">
                <div class="nang-totals-row">
                    <span>' . esc_html(PPV_Lang::t('repair_admin_net')) . '</span>
                    <span id="ra-nang-net">0,00 &euro;</span>
                </div>
                <div class="nang-totals-row" id="ra-nang-vat-row">
                    <span>' . esc_html(PPV_Lang::t('repair_admin_col_vat')) . ' ' . intval($vat_rate) . '%</span>
                    <span id="ra-nang-vat">0,00 &euro;</span>
                </div>
                <div class="nang-totals-row nang-totals-final">
                    <span>' . esc_html(PPV_Lang::t('repair_admin_gross')) . '</span>
                    <span id="ra-nang-total">0,00 &euro;</span>
                </div>
            </div>

            <!-- Valid until + Notes -->
            <div class="nang-meta-row">
                <div class="nang-field nang-field-grow">
                    <label><i class="ri-calendar-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_valid_until')) . '</label>
                    <input type="date" lang="' . esc_attr($lang) . '" id="ra-nang-valid-until" class="nang-meta-input">
                </div>
                <div class="nang-field nang-field-grow">
                    <label><i class="ri-sticky-note-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_notes')) . '</label>
                    <input type="text" id="ra-nang-notes" class="nang-meta-input" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_notes_quote_ph')) . '">
                </div>
            </div>

            <!-- Action buttons -->
            <div class="nang-actions">
                <button type="button" class="nang-cancel-btn" id="ra-nang-cancel">' . esc_html(PPV_Lang::t('repair_admin_cancel')) . '</button>
                <button type="button" class="nang-submit-btn" id="ra-nang-submit">
                    <i class="ri-draft-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_create_quote_btn')) . '
                </button>
            </div>
        </div>

    </div>
</div>';

        // Ankauf now opens in a separate window at /formular/admin/ankauf

        // Customer Modal (new/edit customer)
        echo '<div class="ra-modal-overlay" id="ra-customer-modal">
    <div class="ra-modal" style="max-width:500px">
        <h3 id="ra-cust-modal-title"><i class="ri-user-add-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_cust_new')) . '</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
            <div style="grid-column:span 2">
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_name_required')) . '</label>
                <input type="text" id="ra-cust-name" class="ra-input" required>
            </div>
            <div style="grid-column:span 2">
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_company')) . '</label>
                <input type="text" id="ra-cust-company" class="ra-input">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_email')) . '</label>
                <input type="email" id="ra-cust-email" class="ra-input">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_col_phone')) . '</label>
                <input type="text" id="ra-cust-phone" class="ra-input">
            </div>
            <div style="grid-column:span 2">
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_street')) . '</label>
                <input type="text" id="ra-cust-address" class="ra-input">
            </div>
            <div style="display:grid;grid-template-columns:80px 1fr;gap:8px">
                <div>
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_zip')) . '</label>
                    <input type="text" id="ra-cust-plz" class="ra-input">
                </div>
                <div>
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_col_city')) . '</label>
                    <input type="text" id="ra-cust-city" class="ra-input">
                </div>
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_vat_id')) . '</label>
                <input type="text" id="ra-cust-taxid" class="ra-input">
            </div>
            <div style="grid-column:span 2">
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_notes')) . '</label>
                <textarea id="ra-cust-notes" class="ra-input" rows="2" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_cust_notes_ph')) . '"></textarea>
            </div>
        </div>
        <input type="hidden" id="ra-cust-id" value="">
        <div style="display:flex;gap:10px">
            <button type="button" class="ra-btn ra-btn-outline" style="flex:1" id="ra-cust-cancel">' . esc_html(PPV_Lang::t('repair_admin_cancel')) . '</button>
            <button type="button" class="ra-btn ra-btn-primary" style="flex:2" id="ra-cust-submit">
                <i class="ri-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_save')) . '
            </button>
        </div>
    </div>
</div>';

        // Invoice Edit Modal (with customer fields)
        echo '<div class="ra-modal-overlay" id="ra-edit-invoice-modal">
    <div class="ra-modal nang-modal ninv-modal">

        <!-- Header -->
        <div class="nang-header" style="background:linear-gradient(135deg,#2563eb,#3b82f6)">
            <div class="nang-header-icon"><i class="ri-pencil-line"></i></div>
            <div>
                <h3>' . esc_html(PPV_Lang::t('repair_admin_edit_invoice')) . '</h3>
                <p id="ra-einv-subtitle">' . esc_html(PPV_Lang::t('repair_admin_edit_inv_sub')) . '</p>
            </div>
            <button type="button" class="nang-close" id="ra-einv-close">&times;</button>
        </div>

        <!-- Step indicator -->
        <div class="nang-steps">
            <div class="nang-step active" data-step="1"><span class="nang-step-num" style="background:#2563eb;color:#fff">1</span> ' . esc_html(PPV_Lang::t('repair_admin_customer_data')) . '</div>
            <div class="nang-step-line"></div>
            <div class="nang-step" data-step="2"><span class="nang-step-num">2</span> ' . esc_html(PPV_Lang::t('repair_admin_positions')) . '</div>
        </div>

        <!-- SECTION 1: Customer -->
        <div class="nang-section" id="einv-sec-customer">
            <div class="nang-fields">
                <div class="nang-field-row">
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_name_required')) . '</label>
                        <div class="nang-input-wrap ninv-focus"><i class="ri-user-line"></i>
                            <input type="text" id="ra-einv-name" required>
                        </div>
                    </div>
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_company')) . '</label>
                        <div class="nang-input-wrap ninv-focus"><i class="ri-building-line"></i>
                            <input type="text" id="ra-einv-company">
                        </div>
                    </div>
                </div>
                <div class="nang-field-row">
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_email')) . '</label>
                        <div class="nang-input-wrap ninv-focus"><i class="ri-mail-line"></i>
                            <input type="email" id="ra-einv-email">
                        </div>
                    </div>
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_col_phone')) . '</label>
                        <div class="nang-input-wrap ninv-focus"><i class="ri-phone-line"></i>
                            <input type="tel" id="ra-einv-phone">
                        </div>
                    </div>
                </div>
                <div class="nang-field-row">
                    <div class="nang-field" style="flex:2">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_address')) . '</label>
                        <div class="nang-input-wrap ninv-focus"><i class="ri-map-pin-line"></i>
                            <input type="text" id="ra-einv-address">
                        </div>
                    </div>
                    <div class="nang-field" style="flex:0.6">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_zip')) . '</label>
                        <div class="nang-input-wrap ninv-focus">
                            <input type="text" id="ra-einv-plz">
                        </div>
                    </div>
                    <div class="nang-field" style="flex:1">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_col_city')) . '</label>
                        <div class="nang-input-wrap ninv-focus">
                            <input type="text" id="ra-einv-city">
                        </div>
                    </div>
                </div>
                <div class="nang-field-row">
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_vat_id')) . '</label>
                        <div class="nang-input-wrap ninv-focus"><i class="ri-hashtag"></i>
                            <input type="text" id="ra-einv-taxid" placeholder="DE123456789">
                        </div>
                    </div>
                    <div class="nang-field nang-field-grow">
                        <label>' . esc_html(PPV_Lang::t('repair_admin_inv_number')) . '</label>
                        <div class="nang-input-wrap ninv-focus" style="background:#eff6ff;border-color:#bfdbfe">
                            <i class="ri-file-text-line" style="color:#2563eb"></i>
                            <input type="text" id="ra-einv-number-input" style="font-weight:600">
                        </div>
                        <span class="nang-field-hint" id="ra-einv-date"></span>
                    </div>
                </div>
            </div>

            <button type="button" class="nang-next-btn" id="einv-next-to-positions" style="background:linear-gradient(135deg,#2563eb,#3b82f6);box-shadow:0 2px 8px rgba(37,99,235,.25)">
                ' . esc_html(PPV_Lang::t('repair_admin_positions')) . ' <i class="ri-arrow-right-line"></i>
            </button>
        </div>

        <!-- SECTION 2: Positions + Totals -->
        <div class="nang-section" id="einv-sec-positions" style="display:none">

            <button type="button" class="nang-back-link" id="einv-back-to-customer">
                <i class="ri-arrow-left-s-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_customer_data')) . '
            </button>

            <div class="nang-customer-chip" id="einv-customer-chip" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af"></div>

            <!-- Line items -->
            <div class="nang-positions-header">
                <span class="nang-ph-desc">' . esc_html(PPV_Lang::t('repair_admin_service')) . '</span>
                <span class="nang-ph-qty">' . esc_html(PPV_Lang::t('repair_admin_qty')) . '</span>
                <span class="nang-ph-price">' . esc_html(PPV_Lang::t('repair_admin_unit_price')) . '</span>
                <span class="nang-ph-total">' . esc_html(PPV_Lang::t('repair_admin_line_total')) . '</span>
                <span class="nang-ph-del"></span>
            </div>
            <div class="nang-lines" id="ra-einv-lines">
                <div class="nang-line" data-idx="0">
                    <input type="text" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_service')) . '" class="nang-line-desc">
                    <input type="number" value="1" min="1" step="1" class="nang-line-qty">
                    <div class="nang-line-price-wrap">
                        <input type="number" placeholder="0,00" step="0.01" min="0" class="nang-line-price">
                        <span class="nang-line-currency">&euro;</span>
                    </div>
                    <span class="nang-line-total">0,00 &euro;</span>
                    <button type="button" class="nang-line-del" title="' . esc_attr(PPV_Lang::t('repair_admin_delete')) . '"><i class="ri-delete-bin-line"></i></button>
                </div>
            </div>
            <button type="button" class="nang-add-line" id="ra-einv-add-line">
                <i class="ri-add-circle-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_add_position')) . '
            </button>

            <!-- Totals card -->
            <div class="nang-totals-card">
                <div class="nang-totals-row"><span>' . esc_html(PPV_Lang::t('repair_admin_net')) . '</span><span id="ra-einv-net">0,00 &euro;</span></div>
                <div class="nang-totals-row" id="ra-einv-vat-row"><span>' . esc_html(PPV_Lang::t('repair_admin_col_vat')) . ' ' . intval($vat_rate) . '%</span><span id="ra-einv-vat">0,00 &euro;</span></div>
                <div class="nang-totals-row nang-totals-final" style="border-color:#2563eb;color:#2563eb"><span>' . esc_html(PPV_Lang::t('repair_admin_gross')) . '</span><span id="ra-einv-total">0,00 &euro;</span></div>
            </div>

            <!-- Options -->
            <div class="ninv-options">
                <label class="ninv-checkbox-label">
                    <input type="checkbox" id="ra-einv-differenz">
                    <span class="ninv-checkmark"><i class="ri-check-line"></i></span>
                    <span>' . esc_html(PPV_Lang::t('repair_admin_diff_tax')) . '</span>
                </label>
            </div>

            <!-- Payment section -->
            <div id="ra-einv-payment-section">
                <div class="ninv-options">
                    <label class="ninv-checkbox-label">
                        <input type="checkbox" id="ra-einv-paid-toggle">
                        <span class="ninv-checkmark"><i class="ri-check-line"></i></span>
                        <span>' . esc_html(PPV_Lang::t('repair_admin_already_paid')) . '</span>
                    </label>
                </div>
                <div id="ra-einv-paid-fields" style="display:none;margin-bottom:16px">
                    <div class="nang-field-row">
                        <div class="nang-field nang-field-grow">
                            <label>' . esc_html(PPV_Lang::t('repair_admin_payment_method')) . '</label>
                            <div class="nang-input-wrap ninv-focus"><i class="ri-bank-card-line"></i>
                                <select id="ra-einv-payment-method" style="border:none;outline:none;background:transparent;font-size:14px;width:100%;padding:2px 0;color:#334155">
                                    <option value="">' . esc_html(PPV_Lang::t('repair_admin_please_select')) . '</option>
                                    <option value="bar">' . esc_html(PPV_Lang::t('repair_admin_pay_cash')) . '</option>
                                    <option value="ec">' . esc_html(PPV_Lang::t('repair_admin_pay_ec')) . '</option>
                                    <option value="kreditkarte">' . esc_html(PPV_Lang::t('repair_admin_pay_credit')) . '</option>
                                    <option value="ueberweisung">' . esc_html(PPV_Lang::t('repair_admin_pay_transfer')) . '</option>
                                    <option value="paypal">' . esc_html(PPV_Lang::t('repair_admin_pay_paypal')) . '</option>
                                    <option value="andere">' . esc_html(PPV_Lang::t('repair_admin_pay_other')) . '</option>
                                </select>
                            </div>
                        </div>
                        <div class="nang-field nang-field-grow">
                            <label>' . esc_html(PPV_Lang::t('repair_admin_payment_date')) . '</label>
                            <div class="nang-input-wrap ninv-focus"><i class="ri-calendar-line"></i>
                                <input type="date" lang="' . esc_attr($lang) . '" id="ra-einv-paid-date" style="border:none;outline:none;background:transparent;font-size:14px;width:100%;padding:2px 0;color:#334155">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warranty -->
            <div class="nang-field" style="margin-bottom:4px">
                <label><i class="ri-shield-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_warranty_date')) . '</label>
                <div class="nang-input-wrap ninv-focus" style="max-width:220px">
                    <i class="ri-calendar-check-line"></i>
                    <input type="date" lang="' . esc_attr($lang) . '" id="ra-einv-warranty-date" style="border:none;outline:none;background:transparent;font-size:14px;width:100%;padding:2px 0;color:#334155">
                </div>
            </div>
            <div class="nang-field" id="ra-einv-warranty-desc-wrap" style="margin-bottom:12px;display:none">
                <label>' . esc_html(PPV_Lang::t('repair_admin_warranty_desc')) . '</label>
                <textarea id="ra-einv-warranty-desc" class="nang-meta-input" rows="2" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_warranty_desc_ph')) . '" style="resize:vertical;font-size:12px"></textarea>
            </div>

            <!-- Notes -->
            <div class="nang-field" style="margin-bottom:20px">
                <label><i class="ri-sticky-note-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_notes')) . '</label>
                <textarea id="ra-einv-notes" class="nang-meta-input" rows="2" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_notes_ph')) . '" style="resize:vertical"></textarea>
            </div>

            <input type="hidden" id="ra-einv-id" value="">

            <!-- Action buttons -->
            <div class="nang-actions">
                <button type="button" class="nang-cancel-btn" id="ra-einv-cancel">' . esc_html(PPV_Lang::t('repair_admin_cancel')) . '</button>
                <button type="button" class="nang-submit-btn" id="ra-einv-submit" style="background:linear-gradient(135deg,#2563eb,#3b82f6);box-shadow:0 2px 8px rgba(37,99,235,.25)">
                    <i class="ri-save-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_save')) . '
                </button>
            </div>
        </div>

    </div>
</div>';

        // Payment Method Modal
        echo '<div class="ra-modal-overlay" id="ra-payment-modal">
    <div class="ra-modal" style="max-width:400px">
        <h3><i class="ri-bank-card-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_record_payment')) . '</h3>
        <p class="ra-modal-sub">' . esc_html(PPV_Lang::t('repair_admin_record_pay_sub')) . '</p>

        <div style="margin-bottom:16px">
            <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_payment_method')) . '</label>
            <select id="ra-payment-method" class="ra-input" style="width:100%">
                <option value="">' . esc_html(PPV_Lang::t('repair_admin_please_select')) . '</option>
                <option value="bar">' . esc_html(PPV_Lang::t('repair_admin_pay_cash')) . '</option>
                <option value="ec">' . esc_html(PPV_Lang::t('repair_admin_pay_ec')) . '</option>
                <option value="kreditkarte">' . esc_html(PPV_Lang::t('repair_admin_pay_credit')) . '</option>
                <option value="ueberweisung">' . esc_html(PPV_Lang::t('repair_admin_pay_transfer')) . '</option>
                <option value="paypal">' . esc_html(PPV_Lang::t('repair_admin_pay_paypal')) . '</option>
                <option value="andere">' . esc_html(PPV_Lang::t('repair_admin_pay_other')) . '</option>
            </select>
        </div>

        <div style="margin-bottom:20px">
            <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">' . esc_html(PPV_Lang::t('repair_admin_payment_date')) . '</label>
            <input type="date" lang="' . esc_attr($lang) . '" id="ra-payment-date" class="ra-input" style="width:100%">
        </div>

        <input type="hidden" id="ra-payment-inv-id" value="">

        <div style="display:flex;gap:10px">
            <button type="button" class="ra-btn ra-btn-outline" style="flex:1" id="ra-payment-cancel">' . esc_html(PPV_Lang::t('repair_admin_cancel')) . '</button>
            <button type="button" class="ra-btn ra-btn-primary" style="flex:2" id="ra-payment-submit">
                <i class="ri-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_confirm')) . '
            </button>
        </div>
    </div>
</div>';

        // Billbee Import Modal
        echo '<div class="ra-modal-overlay" id="ra-billbee-modal">
    <div class="ra-modal" style="max-width:500px">
        <h3 style="color:#ea580c"><i class="ri-upload-cloud-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_billbee_title')) . '</h3>
        <p style="font-size:13px;color:#6b7280;margin-bottom:20px">' . esc_html(PPV_Lang::t('repair_admin_billbee_hint')) . '</p>

        <div style="border:2px dashed #fed7aa;border-radius:12px;padding:32px 20px;text-align:center;background:#fffbeb;margin-bottom:20px" id="ra-billbee-dropzone">
            <i class="ri-file-upload-line" style="font-size:48px;color:#f97316;margin-bottom:12px;display:block"></i>
            <p style="color:#92400e;font-weight:500;margin:0 0 8px">' . esc_html(PPV_Lang::t('repair_admin_drop_xml')) . '</p>
            <p style="color:#a16207;font-size:12px;margin:0 0 12px">' . esc_html(PPV_Lang::t('repair_admin_or_click')) . '</p>
            <input type="file" id="ra-billbee-file" accept=".xml" style="display:none">
            <button type="button" class="ra-btn ra-btn-sm" style="background:#f97316;color:#fff" id="ra-billbee-select">' . esc_html(PPV_Lang::t('repair_admin_select_file')) . '</button>
        </div>

        <div id="ra-billbee-selected" style="display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;margin-bottom:20px">
            <div style="display:flex;align-items:center;gap:10px">
                <i class="ri-file-text-line" style="font-size:24px;color:#16a34a"></i>
                <div style="flex:1">
                    <div style="font-weight:500;color:#166534" id="ra-billbee-filename">datei.xml</div>
                    <div style="font-size:12px;color:#6b7280" id="ra-billbee-filesize">0 KB</div>
                </div>
                <button type="button" class="ra-btn ra-btn-sm" style="background:#fee2e2;color:#dc2626" id="ra-billbee-remove"><i class="ri-close-line"></i></button>
            </div>
        </div>

        <div id="ra-billbee-progress" style="display:none;margin-bottom:20px">
            <div style="display:flex;align-items:center;gap:10px;color:#ea580c">
                <i class="ri-loader-4-line ri-spin" style="font-size:20px"></i>
                <span>' . esc_html(PPV_Lang::t('repair_admin_import_running')) . '</span>
            </div>
        </div>

        <div style="display:flex;gap:10px">
            <button type="button" class="ra-btn ra-btn-outline" style="flex:1" id="ra-billbee-cancel">' . esc_html(PPV_Lang::t('repair_admin_cancel')) . '</button>
            <button type="button" class="ra-btn" style="flex:2;background:#ea580c;color:#fff" id="ra-billbee-submit" disabled>
                <i class="ri-upload-2-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_import_btn')) . '
            </button>
        </div>
    </div>
</div>';

        // Footer
        echo '<div style="text-align:center;margin-top:32px;padding-top:20px;border-top:1px solid #e5e7eb;">
        <div style="font-size:12px;color:#9ca3af;">Powered by <a href="https://punktepass.de" target="_blank" style="color:#667eea;">PunktePass</a></div>
    </div>

</div>

<script>
(function(){
    var AJAX="' . esc_url($ajax_url) . '",
        NONCE="' . esc_attr($nonce) . '",
        STORE=' . intval($store->id) . ',
        SLUG="' . esc_attr($store_slug) . '",
        VAT_ENABLED=!!parseInt("' . $vat_enabled . '"),
        VAT_RATE=parseFloat("' . $vat_rate . '"),
        STORE_NAME="' . esc_js($store->name) . '",
        STORE_COMPANY="' . esc_js($store->repair_company_name ?: $store->name) . '",
        STORE_ADDRESS="' . esc_js($store->repair_company_address ?? '') . '",
        STORE_PHONE="' . esc_js($store->repair_company_phone ?? '') . '",
        STORE_EMAIL="' . esc_js($store->repair_company_email ?? '') . '",
        STORE_TAX_ID="' . esc_js($store->repair_tax_id ?? '') . '",
        STORE_OWNER="' . esc_js($store->repair_owner_name ?? '') . '",
        REWARD_NAME="' . esc_js($reward_name) . '",
        REWARD_TYPE="' . esc_js($reward_type) . '",
        REWARD_VALUE=parseFloat("' . $reward_value . '"),
        PP_ENABLED=!!parseInt("' . intval($store->repair_punktepass_enabled ?? 1) . '"),
        WARRANTY_DEFAULT=' . json_encode($store->repair_warranty_text ?? '') . ',
        FORM_BASE_URL=' . json_encode(home_url("/formular/{$store->store_slug}/status/")) . ',
        searchTimer=null,
        currentPage=1,
        invSortBy="created_at",
        invSortDir="desc";

    // Translation bridge
    var L=' . json_encode([
        'link_copied' => PPV_Lang::t('repair_admin_js_link_copied'),
        'status_updated' => PPV_Lang::t('repair_admin_js_status_updated'),
        'error' => PPV_Lang::t('repair_admin_js_error'),
        'connection_error' => PPV_Lang::t('repair_admin_js_connection_error'),
        'confirm_delete_repair' => PPV_Lang::t('repair_admin_js_confirm_delete_repair'),
        'repair_deleted' => PPV_Lang::t('repair_admin_js_repair_deleted'),
        'delete_error' => PPV_Lang::t('repair_admin_js_delete_error'),
        'popup_blocked' => PPV_Lang::t('repair_admin_js_popup_blocked'),
        'no_email' => PPV_Lang::t('repair_admin_js_no_email'),
        'send_repair' => PPV_Lang::t('repair_admin_js_send_repair'),
        'email_sent' => PPV_Lang::t('repair_admin_js_email_sent'),
        'send_error' => PPV_Lang::t('repair_admin_js_send_error'),
        'no_results' => PPV_Lang::t('repair_admin_js_no_results'),
        'marked_done' => PPV_Lang::t('repair_admin_js_marked_done'),
        'min_one_item' => PPV_Lang::t('repair_admin_js_min_one_item'),
        'inv_updated' => PPV_Lang::t('repair_admin_js_inv_updated'),
        'repair_completed' => PPV_Lang::t('repair_admin_js_repair_completed'),
        'marked_paid' => PPV_Lang::t('repair_admin_js_marked_paid'),
        'confirm_delete_inv' => PPV_Lang::t('repair_admin_js_confirm_delete_inv'),
        'inv_deleted' => PPV_Lang::t('repair_admin_js_inv_deleted'),
        'send_inv_email' => PPV_Lang::t('repair_admin_js_send_inv_email'),
        'send_reminder' => PPV_Lang::t('repair_admin_js_send_reminder'),
        'reminder_sent' => PPV_Lang::t('repair_admin_js_reminder_sent'),
        'export_prep' => PPV_Lang::t('repair_admin_js_export_prep'),
        'export_ok' => PPV_Lang::t('repair_admin_js_export_ok'),
        'export_fail' => PPV_Lang::t('repair_admin_js_export_fail'),
        'mark_paid_confirm' => PPV_Lang::t('repair_admin_js_mark_paid_confirm'),
        'send_emails_confirm' => PPV_Lang::t('repair_admin_js_send_emails_confirm'),
        'send_reminders_confirm' => PPV_Lang::t('repair_admin_js_send_reminders_confirm'),
        'no_inv_selected' => PPV_Lang::t('repair_admin_js_no_inv_selected'),
        'bulk_delete' => PPV_Lang::t('repair_admin_js_bulk_delete'),
        'deleted_ok' => PPV_Lang::t('repair_admin_js_deleted_ok'),
        'settings_saved' => PPV_Lang::t('repair_admin_js_settings_saved'),
        'saved' => PPV_Lang::t('repair_admin_js_saved'),
        'save_error' => PPV_Lang::t('repair_admin_js_save_error'),
        'logo_uploaded' => PPV_Lang::t('repair_admin_js_logo_uploaded'),
        'upload_failed' => PPV_Lang::t('repair_admin_js_upload_failed'),
        'name_required' => PPV_Lang::t('repair_admin_js_name_required'),
        'inv_created' => PPV_Lang::t('repair_admin_js_inv_created'),
        'quote_created' => PPV_Lang::t('repair_admin_js_quote_created'),
        'cust_name_req' => PPV_Lang::t('repair_admin_js_cust_name_req'),
        'no_customers' => PPV_Lang::t('repair_admin_js_no_customers'),
        'cant_delete_form_cust' => PPV_Lang::t('repair_admin_js_cant_delete_form_cust'),
        'confirm_delete_cust' => PPV_Lang::t('repair_admin_js_confirm_delete_cust'),
        'cust_deleted' => PPV_Lang::t('repair_admin_js_cust_deleted'),
        'no_purchases' => PPV_Lang::t('repair_admin_js_no_purchases'),
        'purchase_handy' => PPV_Lang::t('repair_admin_js_purchase_handy'),
        'purchase_car' => PPV_Lang::t('repair_admin_js_purchase_car'),
        'purchase_other' => PPV_Lang::t('repair_admin_js_purchase_other'),
        'confirm_delete_purchase' => PPV_Lang::t('repair_admin_js_confirm_delete_purchase'),
        'purchase_deleted' => PPV_Lang::t('repair_admin_js_purchase_deleted'),
        'cust_saved' => PPV_Lang::t('repair_admin_js_cust_saved'),
        'only_xml' => PPV_Lang::t('repair_admin_js_only_xml'),
        'import_ok' => PPV_Lang::t('repair_admin_js_import_ok'),
        'import_fail' => PPV_Lang::t('repair_admin_js_import_fail'),
        'confirm_delete_comment' => PPV_Lang::t('repair_admin_js_confirm_delete_comment'),
        'comment_deleted' => PPV_Lang::t('repair_admin_js_comment_deleted'),
        'comment_added' => PPV_Lang::t('repair_admin_js_comment_added'),
        'no_comments' => PPV_Lang::t('repair_admin_js_no_comments'),
        'loading_error' => PPV_Lang::t('repair_admin_js_loading_error'),
        'cancel_sub' => PPV_Lang::t('repair_admin_js_cancel_sub'),
        'processing' => PPV_Lang::t('repair_admin_js_processing'),
        'sub_cancelled' => PPV_Lang::t('repair_admin_js_sub_cancelled'),
        'cancel_error' => PPV_Lang::t('repair_admin_js_cancel_error'),
        'approve_confirm' => PPV_Lang::t('repair_admin_js_approve_confirm'),
        'reward_approved' => PPV_Lang::t('repair_admin_js_reward_approved'),
        'approve' => PPV_Lang::t('repair_admin_js_approve'),
        'reject_reason' => PPV_Lang::t('repair_admin_js_reject_reason'),
        'saving' => PPV_Lang::t('repair_admin_js_saving'),
        'reward_rejected' => PPV_Lang::t('repair_admin_js_reward_rejected'),
        'fb_category' => PPV_Lang::t('repair_admin_js_fb_category'),
        'fb_rating' => PPV_Lang::t('repair_admin_js_fb_rating'),
        'fb_describe' => PPV_Lang::t('repair_admin_js_fb_describe'),
        'sending' => PPV_Lang::t('repair_admin_js_sending'),
        'fb_thanks' => PPV_Lang::t('repair_admin_js_fb_thanks'),
        'fb_error' => PPV_Lang::t('repair_admin_js_fb_error'),
        'show_stats' => PPV_Lang::t('repair_admin_show_stats'),
        'hide_stats' => PPV_Lang::t('repair_admin_hide_stats'),
        'load_more' => PPV_Lang::t('repair_admin_load_more'),
        'cancel' => PPV_Lang::t('repair_admin_cancel'),
        'reject_btn' => PPV_Lang::t('repair_admin_reject_btn'),
        'fb_submit' => PPV_Lang::t('repair_admin_fb_submit'),
        'abo_cancel_btn' => PPV_Lang::t('repair_admin_abo_cancel_btn'),
        'service' => PPV_Lang::t('repair_admin_service'),
        'gross' => PPV_Lang::t('repair_admin_gross'),
        'delete' => PPV_Lang::t('repair_admin_delete'),
        'cust_edit' => PPV_Lang::t('repair_admin_cust_edit'),
        'cust_save_as' => PPV_Lang::t('repair_admin_cust_new'),
        'cust_new' => PPV_Lang::t('repair_admin_cust_new'),
        'cust_form_badge' => PPV_Lang::t('repair_admin_cust_form_badge'),
        'cust_saved_badge' => PPV_Lang::t('repair_admin_save'),
        'orders_count' => PPV_Lang::t('repair_admin_repairs'),
        'small_business' => PPV_Lang::t('repair_admin_small_business'),
        'edit_btn' => PPV_Lang::t('repair_admin_js_edit_btn'),
        'delete_btn' => PPV_Lang::t('repair_admin_js_delete_btn'),
        'success' => PPV_Lang::t('repair_admin_js_success'),
        'deleting' => PPV_Lang::t('repair_admin_js_deleting'),
        'no_entries' => PPV_Lang::t('repair_admin_js_no_entries'),
        'email_title' => PPV_Lang::t('repair_admin_js_email_title'),
        'reminder_title' => PPV_Lang::t('repair_admin_js_reminder_title'),
        'inv_subtitle' => PPV_Lang::t('repair_admin_inv_subtitle'),
        'bulk_processing' => PPV_Lang::t('repair_admin_js_bulk_processing'),
        'bulk_paid_btn' => PPV_Lang::t('repair_admin_js_bulk_paid_btn'),
        'bulk_send_btn' => PPV_Lang::t('repair_admin_js_bulk_send_btn'),
        'bulk_reminder_btn' => PPV_Lang::t('repair_admin_js_bulk_reminder_btn'),
        'ankauf_item' => PPV_Lang::t('repair_admin_js_ankauf_item'),
        'document_item' => PPV_Lang::t('repair_admin_js_document_item'),
        'saved_btn' => PPV_Lang::t('repair_admin_js_saved_btn'),
        // Invoice status labels
        'inv_status_draft' => PPV_Lang::t('repair_admin_inv_status_draft'),
        'inv_status_sent' => PPV_Lang::t('repair_admin_inv_status_sent'),
        'inv_status_paid' => PPV_Lang::t('repair_admin_inv_status_paid'),
        'inv_status_cancelled' => PPV_Lang::t('repair_admin_inv_status_cancelled'),
        'inv_status_accepted' => PPV_Lang::t('repair_admin_inv_status_accepted'),
        'inv_status_rejected' => PPV_Lang::t('repair_admin_inv_status_rejected'),
        'inv_status_expired' => PPV_Lang::t('repair_admin_inv_status_expired'),
        'inv_type_invoice' => PPV_Lang::t('repair_admin_inv_type_invoice'),
        'inv_type_quote' => PPV_Lang::t('repair_admin_inv_type_quote'),
        // Print template
        'print_title' => PPV_Lang::t('repair_admin_print_title'),
        'print_date' => PPV_Lang::t('repair_admin_print_date'),
        'print_customer' => PPV_Lang::t('repair_admin_print_customer'),
        'print_device' => PPV_Lang::t('repair_admin_print_device'),
        'print_phone' => PPV_Lang::t('repair_admin_print_phone'),
        'print_email' => PPV_Lang::t('repair_admin_print_email'),
        'print_pin' => PPV_Lang::t('repair_admin_print_pin'),
        'print_address' => PPV_Lang::t('repair_admin_print_address'),
        'print_problem' => PPV_Lang::t('repair_admin_print_problem'),
        'print_privacy' => PPV_Lang::t('repair_admin_print_privacy'),
        'print_privacy_text' => PPV_Lang::t('repair_admin_print_privacy_text'),
        'print_sig_customer' => PPV_Lang::t('repair_admin_print_sig_customer'),
        'print_sig_shop' => PPV_Lang::t('repair_admin_print_sig_shop'),
        // Table column labels
        'col_nr' => PPV_Lang::t('repair_admin_col_nr'),
        'col_date' => PPV_Lang::t('repair_admin_col_date'),
        'col_customer' => PPV_Lang::t('repair_admin_col_customer'),
        'col_net' => PPV_Lang::t('repair_admin_col_net'),
        'col_vat' => PPV_Lang::t('repair_admin_col_vat'),
        'col_total' => PPV_Lang::t('repair_admin_col_total'),
        'col_status' => PPV_Lang::t('repair_admin_col_status'),
        'col_name' => PPV_Lang::t('repair_admin_col_name'),
        'col_company' => PPV_Lang::t('repair_admin_col_company'),
        'col_email' => PPV_Lang::t('repair_admin_col_email'),
        'col_phone' => PPV_Lang::t('repair_admin_col_phone'),
        'col_city' => PPV_Lang::t('repair_admin_col_city'),
        'col_seller' => PPV_Lang::t('repair_admin_col_seller'),
        'col_article' => PPV_Lang::t('repair_admin_col_article'),
        'col_imei' => PPV_Lang::t('repair_admin_col_imei'),
        'col_price' => PPV_Lang::t('repair_admin_col_price'),
        // Repair card buttons
        'btn_print' => PPV_Lang::t('repair_admin_print'),
        'btn_email' => PPV_Lang::t('repair_admin_send_email'),
        'btn_resubmit' => PPV_Lang::t('repair_admin_resubmit'),
        'btn_create_inv' => PPV_Lang::t('repair_admin_create_inv_card'),
        'invoice_exists' => PPV_Lang::t('repair_admin_invoice_exists'),
        'invoice_duplicate_warn' => PPV_Lang::t('repair_admin_invoice_duplicate_warn'),
        'btn_angebot' => PPV_Lang::t('repair_admin_create_quote_btn'),
        'btn_delete' => PPV_Lang::t('repair_admin_delete_repair'),
        'comments_label' => PPV_Lang::t('repair_admin_comments'),
        'add_comment_ph' => PPV_Lang::t('repair_admin_add_comment_ph'),
        'parts_arrived' => PPV_Lang::t('repair_admin_parts_arrived'),
        'parts_arrived_desc' => PPV_Lang::t('repair_admin_parts_arrived_desc'),
        'termin_confirm' => PPV_Lang::t('repair_admin_termin_confirm'),
        'termin_success' => PPV_Lang::t('repair_admin_termin_success'),
        'termin_no_date' => PPV_Lang::t('repair_admin_termin_no_date'),
        'no_termin_confirm' => PPV_Lang::t('repair_admin_no_termin_confirm'),
        'no_termin_success' => PPV_Lang::t('repair_admin_no_termin_success'),
        'only_done' => PPV_Lang::t('repair_admin_only_done'),
        'finish_inv' => PPV_Lang::t('repair_admin_finish_invoice'),
        'create_inv_btn' => PPV_Lang::t('repair_admin_create_inv_btn'),
        'finish_title' => PPV_Lang::t('repair_admin_finish_repair'),
        'print_name' => PPV_Lang::t('repair_admin_print_name'),
        'print_muster' => PPV_Lang::t('repair_admin_print_muster'),
        'print_owner' => PPV_Lang::t('repair_admin_print_owner'),
        'print_tel' => PPV_Lang::t('repair_admin_print_tel'),
        'print_vatid' => PPV_Lang::t('repair_admin_print_vatid'),
        // Card section labels
        'device_section' => PPV_Lang::t('repair_admin_device_section', 'Gerät'),
        'problem_section' => PPV_Lang::t('repair_admin_problem_section', 'Problem'),
        'tracking_url' => PPV_Lang::t('repair_admin_tracking_url'),
        'details_section' => PPV_Lang::t('repair_admin_details_section', 'Details'),
        // Status labels
        'status_new' => PPV_Lang::t('repair_admin_status_new'),
        'status_progress' => PPV_Lang::t('repair_admin_status_progress'),
        'status_waiting' => PPV_Lang::t('repair_admin_status_waiting'),
        'status_done' => PPV_Lang::t('repair_admin_status_done'),
        'status_delivered' => PPV_Lang::t('repair_admin_status_delivered'),
        'status_cancelled' => PPV_Lang::t('repair_admin_status_cancelled'),
        // Custom field labels for print
        'cf_color' => PPV_Lang::t('repair_fb_color'),
        'cf_purchase_date' => PPV_Lang::t('repair_fb_purchase_date'),
        'cf_priority' => PPV_Lang::t('repair_fb_priority'),
        'cf_cost_limit' => PPV_Lang::t('repair_fb_cost_limit'),
        // KFZ field labels for print
        'cf_vehicle_plate' => PPV_Lang::t('repair_vehicle_plate_label'),
        'cf_vehicle_vin' => PPV_Lang::t('repair_vehicle_vin_label'),
        'cf_vehicle_mileage' => PPV_Lang::t('repair_vehicle_mileage_label'),
        'cf_vehicle_first_reg' => PPV_Lang::t('repair_vehicle_first_reg_label'),
        'cf_vehicle_tuev' => PPV_Lang::t('repair_vehicle_tuev_label'),
        'cf_condition_kfz' => PPV_Lang::t('repair_fb_condition_kfz'),
    ], JSON_UNESCAPED_UNICODE) . ';

    // Language switcher
    document.querySelectorAll(".ra-lang-opt").forEach(function(btn){
        btn.addEventListener("click",function(){
            var lang=this.getAttribute("data-lang");
            document.cookie="ppv_lang="+lang+";path=/;max-age=31536000";
            var url=new URL(window.location.href);
            url.searchParams.set("lang",lang);
            window.location.href=url.toString();
        });
    });
    document.addEventListener("click",function(e){var w=document.getElementById("ra-lang-wrap");if(w&&!w.contains(e.target))w.classList.remove("open");});

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
            toast(L.link_copied);
            setTimeout(function(){btn.innerHTML=\'<i class="ri-file-copy-line"></i>\'},2000);
        });
    });

    /* ===== Status Change ===== */
    function bindStatusSelects(container){
        container.querySelectorAll(".ra-status-select").forEach(function(sel){
            sel.setAttribute("data-prev",sel.value);
            sel.addEventListener("change",function(){
                var rid=this.getAttribute("data-repair-id"),
                    st=this.value;
                // If "done" → show invoice modal instead of immediate AJAX
                if(st==="done"){
                    var card=this.closest(".ra-repair-card");
                    var existingInv=card?card.dataset.invoice:"";
                    if(existingInv){
                        if(!confirm(L.invoice_duplicate_warn.replace("%s",existingInv))){
                            this.value=this.getAttribute("data-prev")||"in_progress";
                            return;
                        }
                    }
                    // Open modern invoice modal in "finish repair" mode
                    openNinvFromRepair(card,rid,this);
                    return;
                }
                var fd=new FormData();
                fd.append("action","ppv_repair_update_status");
                fd.append("nonce",NONCE);
                fd.append("repair_id",rid);
                fd.append("status",st);
                var self=this;
                fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
                .then(function(r){return r.json()})
                .then(function(data){
                    if(data.success){
                        toast(L.status_updated);
                        updateBadge(self.closest(".ra-repair-card"),st);
                        self.setAttribute("data-prev",st);
                    }else{
                        toast(data.data&&data.data.message?data.data.message:L.error);
                    }
                })
                .catch(function(){toast(L.connection_error)});
            });
        });
    }

    function updateBadge(card,status){
        var map={
            new:[L.status_new,"ra-status-new"],
            in_progress:[L.status_progress,"ra-status-progress"],
            waiting_parts:[L.status_waiting,"ra-status-waiting"],
            done:[L.status_done,"ra-status-done"],
            delivered:[L.status_delivered,"ra-status-delivered"],
            cancelled:[L.status_cancelled,"ra-status-cancelled"]
        };
        card.setAttribute("data-status",status);
        var badge=card.querySelector(".ra-status");
        if(badge&&map[status]){
            badge.className="ra-status "+map[status][1];
            badge.textContent=map[status][0];
        }
    }

    bindStatusSelects(document);

    /* ===== Nochmal Anliegen ===== */
    document.getElementById("ra-repairs-list").addEventListener("click",function(e){
        var btn=e.target.closest(".ra-btn-resubmit");
        if(!btn)return;
        var card=btn.closest(".ra-repair-card");
        if(!card)return;
        var params=new URLSearchParams();
        if(card.dataset.name)params.set("name",card.dataset.name);
        if(card.dataset.email)params.set("email",card.dataset.email);
        if(card.dataset.phone)params.set("phone",card.dataset.phone);
        if(card.dataset.address)params.set("address",card.dataset.address);
        if(card.dataset.brand)params.set("brand",card.dataset.brand);
        if(card.dataset.model)params.set("model",card.dataset.model);
        if(card.dataset.imei)params.set("imei",card.dataset.imei);
        if(card.dataset.pin)params.set("pin",card.dataset.pin);
        if(card.dataset.problem)params.set("problem",card.dataset.problem);
        window.open("/formular/"+SLUG+"?"+params.toString(),"_blank");
    });

    /* ===== Direct Invoice Button ===== */
    document.getElementById("ra-repairs-list").addEventListener("click",function(e){
        var btn=e.target.closest(".ra-btn-invoice");
        if(!btn)return;
        var card=btn.closest(".ra-repair-card");
        if(!card)return;
        var rid=card.dataset.id;
        var existingInv=card.dataset.invoice;
        if(existingInv){
            if(!confirm(L.invoice_duplicate_warn.replace("%s",existingInv))){return}
        }
        // Open modern invoice modal with pre-filled data from repair card
        openNinvFromRepair(card,rid,null);
    });

    /* ===== Delete Repair ===== */
    document.getElementById("ra-repairs-list").addEventListener("click",function(e){
        var btn=e.target.closest(".ra-btn-delete");
        if(!btn)return;
        var card=btn.closest(".ra-repair-card");
        if(!card)return;
        var rid=card.dataset.id;
        if(!confirm(L.confirm_delete_repair.replace("%s",rid)))return;
        btn.disabled=true;
        btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i>\';
        var fd=new FormData();
        fd.append("action","ppv_repair_delete");
        fd.append("nonce",NONCE);
        fd.append("repair_id",rid);
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(res){
            if(res.success){
                card.remove();
                toast(L.repair_deleted);
            }else{
                btn.disabled=false;
                btn.innerHTML=\'<i class="ri-delete-bin-line"></i>\';
                toast(res.data&&res.data.message?res.data.message:L.delete_error);
            }
        })
        .catch(function(){
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-delete-bin-line"></i>\';
            toast(L.connection_error);
        });
    });

    /* ===== Teil angekommen (Parts Arrived) + Termin Modal ===== */
    var terminModal=document.getElementById("ra-termin-modal"),
        terminRepairId=null,
        terminNoTerminCb=document.getElementById("ra-termin-no-termin"),
        terminFields=document.getElementById("ra-termin-fields"),
        terminSubmitBtn=document.getElementById("ra-termin-submit");

    // Toggle termin fields visibility
    terminNoTerminCb.addEventListener("change",function(){
        if(this.checked){
            terminFields.style.display="none";
            terminSubmitBtn.innerHTML=\'<i class="ri-check-line"></i> \'+L.no_termin_confirm;
        }else{
            terminFields.style.display="";
            terminSubmitBtn.innerHTML=\'<i class="ri-check-line"></i> \'+L.termin_confirm;
        }
    });

    document.getElementById("ra-repairs-list").addEventListener("click",function(e){
        var btn=e.target.closest(".ra-btn-parts-arrived");
        if(!btn)return;
        var card=btn.closest(".ra-repair-card");
        if(!card)return;
        terminRepairId=card.dataset.id;
        // Show customer info
        var info=document.getElementById("ra-termin-info");
        var cname=card.dataset.name||"";
        var cdev=((card.dataset.brand||"")+" "+(card.dataset.model||"")).trim();
        if(cname||cdev){info.textContent=cname+(cdev?" \u2013 "+cdev:"");info.style.display="block"}else{info.style.display="none"}
        // Reset fields with tomorrow as default
        var tomorrow=new Date();tomorrow.setDate(tomorrow.getDate()+1);
        document.getElementById("ra-termin-date").value=tomorrow.toISOString().split("T")[0];
        document.getElementById("ra-termin-time").value="10:00";
        document.getElementById("ra-termin-message").value="";
        document.getElementById("ra-termin-send-email").checked=true;
        // Reset no-termin checkbox
        terminNoTerminCb.checked=false;
        terminFields.style.display="";
        terminSubmitBtn.innerHTML=\'<i class="ri-check-line"></i> \'+L.termin_confirm;
        terminModal.classList.add("show");
        // Scroll modal content to top and ensure visibility
        var mContent=terminModal.querySelector(".ra-modal");
        if(mContent)mContent.scrollTop=0;
        window.scrollTo(0,0);
    });

    document.getElementById("ra-termin-cancel").addEventListener("click",function(){
        terminModal.classList.remove("show");
        terminRepairId=null;
    });
    terminModal.addEventListener("click",function(e){
        if(e.target===terminModal){terminModal.classList.remove("show");terminRepairId=null}
    });

    document.getElementById("ra-termin-submit").addEventListener("click",function(){
        if(!terminRepairId)return;
        var noTermin=terminNoTerminCb.checked;
        var tDate="",tTime="",msg="",sendEmail=false;
        if(!noTermin){
            tDate=document.getElementById("ra-termin-date").value;
            if(!tDate){toast(L.termin_no_date);return}
            tTime=document.getElementById("ra-termin-time").value;
            msg=document.getElementById("ra-termin-message").value;
            sendEmail=document.getElementById("ra-termin-send-email").checked;
        }
        var btn=this;
        btn.disabled=true;
        btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i>\';
        var fd=new FormData();
        fd.append("action","ppv_repair_parts_arrived");
        fd.append("nonce",NONCE);
        fd.append("repair_id",terminRepairId);
        if(noTermin){
            fd.append("no_termin","1");
        }else{
            fd.append("termin_date",tDate);
            fd.append("termin_time",tTime);
            fd.append("custom_message",msg);
            if(sendEmail)fd.append("send_email","1");
        }
        var btnLabel=noTermin?L.no_termin_confirm:L.termin_confirm;
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-check-line"></i> \'+btnLabel;
            if(data.success){
                toast(noTermin?L.no_termin_success:L.termin_success);
                terminModal.classList.remove("show");
                // Update card status to in_progress
                var card=document.querySelector(\'.ra-repair-card[data-id="\'+terminRepairId+\'"]\');
                if(card){
                    updateBadge(card,"in_progress");
                    var sel=card.querySelector(".ra-status-select");
                    if(sel){sel.value="in_progress";sel.setAttribute("data-prev","in_progress")}
                    // Remove the parts-arrived button
                    var paBtn=card.querySelector(".ra-btn-parts-arrived");
                    if(paBtn)paBtn.remove();
                    // Add termin badge only if termin was set
                    if(!noTermin){
                        var body=card.querySelector(".ra-repair-body");
                        if(body&&tDate){
                            var tBadge=document.createElement("div");
                            tBadge.className="ra-termin-badge";
                            var dParts=tDate.split("-");
                            tBadge.innerHTML=\'<i class="ri-calendar-check-line"></i> \'+dParts[2]+"."+dParts[1]+"."+dParts[0]+(tTime?" "+tTime:"");
                            body.appendChild(tBadge);
                        }
                    }
                }
                terminRepairId=null;
            }else{
                toast(data.data&&data.data.message?data.data.message:L.error);
            }
        })
        .catch(function(){
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-check-line"></i> \'+btnLabel;
            toast(L.connection_error);
        });
    });

    /* ===== Print Repair ===== */
    document.getElementById("ra-repairs-list").addEventListener("click",function(e){
        var btn=e.target.closest(".ra-btn-print");
        if(!btn)return;
        var card=btn.closest(".ra-repair-card");
        if(!card)return;
        printRepair(card);
    });

    function printRepair(card){
        var data={
            id: card.dataset.id,
            name: card.dataset.name||"",
            email: card.dataset.email||"",
            phone: card.dataset.phone||"",
            address: card.dataset.address||"",
            brand: card.dataset.brand||"",
            model: card.dataset.model||"",
            pin: card.dataset.pin||"",
            imei: card.dataset.imei||"",
            accessories: card.dataset.accessories||"",
            problem: card.dataset.problem||"",
            date: card.dataset.date||"",
            muster: card.dataset.muster||"",
            signature: card.dataset.signature||"",
            customfields: card.dataset.customfields||""
        };
        var w=window.open("","_blank","width=800,height=900");
        if(!w){alert(L.popup_blocked);return;}
        var device=((data.brand||"")+" "+(data.model||"")).trim();
        var musterHtml=data.muster&&data.muster.indexOf("data:image/")===0?\'<div class="field"><span class="label">\'+L.print_muster+\':</span><img src="\'+data.muster+\'" style="max-width:80px;border:1px solid #ddd;border-radius:4px"></div>\':"";
        var pinHtml=data.pin?\'<div class="field"><span class="label">\'+L.print_pin+\':</span><span class="value highlight">\'+esc(data.pin)+\'</span></div>\':"";
        var imeiHtml=data.imei?\'<div class="field"><span class="label">IMEI:</span><span class="value">\'+esc(data.imei)+\'</span></div>\':"";
        var addressHtml=data.address?\'<div class="field"><span class="label">\'+L.print_address+\':</span><span class="value">\'+esc(data.address)+\'</span></div>\':"";
        var accHtml=data.accessories?\'<div class="field"><span class="label">Zubehör:</span><span class="value">\'+esc(data.accessories)+\'</span></div>\':"";
        var signatureHtml=data.signature&&data.signature.indexOf("data:image/")===0?\'<div class="sig-img"><img src="\'+data.signature+\'" style="max-height:40px"></div>\':\'<div class="signature-line"></div>\';
        // Parse custom fields for print
        var cfHtml="";
        if(data.customfields){
            try{
                var cf=JSON.parse(data.customfields);
                var cfLabels={device_color:L.cf_color||"Farbe",purchase_date:L.cf_purchase_date||"Kaufdatum",priority:L.cf_priority||"Priorität",cost_limit:L.cf_cost_limit||"Kostenrahmen",vehicle_plate:L.cf_vehicle_plate||"Kennzeichen",vehicle_vin:L.cf_vehicle_vin||"FIN/VIN",vehicle_mileage:L.cf_vehicle_mileage||"Kilometerstand",vehicle_first_reg:L.cf_vehicle_first_reg||"Erstzulassung",vehicle_tuev:L.cf_vehicle_tuev||"TÜV/HU"};
                for(var ck in cf){
                    if(ck==="photos"||ck==="condition_check"||ck==="condition_check_kfz"||ck==="condition_check_pc") continue;
                    var lbl=cfLabels[ck]||ck;
                    if(typeof cf[ck]==="string"&&cf[ck]) cfHtml+=\'<div class="field"><span class="label">\'+esc(lbl)+\':</span><span class="value">\'+esc(cf[ck])+\'</span></div>\';
                }
                if(cf.condition_check){
                    var cond=typeof cf.condition_check==="string"?JSON.parse(cf.condition_check):cf.condition_check;
                    var cParts=[];
                    for(var cp in cond) cParts.push(cp+": "+cond[cp].toUpperCase());
                    if(cParts.length) cfHtml+=\'<div class="field"><span class="label">Zustand:</span><span class="value">\'+esc(cParts.join(", "))+\'</span></div>\';
                }
                if(cf.condition_check_kfz){
                    var ckfz=typeof cf.condition_check_kfz==="string"?JSON.parse(cf.condition_check_kfz):cf.condition_check_kfz;
                    var kParts=[];
                    for(var kp in ckfz) kParts.push(kp+": "+ckfz[kp].toUpperCase());
                    if(kParts.length) cfHtml+=\'<div class="field"><span class="label">\'+(L.cf_condition_kfz||"KFZ-Zustand")+\':</span><span class="value">\'+esc(kParts.join(", "))+\'</span></div>\';
                }
                if(cf.condition_check_pc){
                    var cpc=typeof cf.condition_check_pc==="string"?JSON.parse(cf.condition_check_pc):cf.condition_check_pc;
                    var pcParts=[];
                    for(var pp in cpc) pcParts.push(pp+": "+cpc[pp].toUpperCase());
                    if(pcParts.length) cfHtml+=\'<div class="field"><span class="label">PC-Zustand:</span><span class="value">\'+esc(pcParts.join(", "))+\'</span></div>\';
                }
                if(cf.photos&&cf.photos.length){
                    cfHtml+=\'<div class="field"><span class="label">Fotos:</span><span class="value">\';
                    cf.photos.forEach(function(u){cfHtml+=\'<img src="\'+u+\'" style="width:50px;height:50px;object-fit:cover;border-radius:4px;border:1px solid #ddd;margin-right:4px">\'});
                    cfHtml+=\'</span></div>\';
                }
            }catch(e){}
        }
        var html=\'<!DOCTYPE html><html><head><meta charset="UTF-8"><title>\'+L.print_title+\' #\'+data.id+\'</title><style>\'+
            \'*{margin:0;padding:0;box-sizing:border-box}\'+
            \'body{font-family:Arial,sans-serif;padding:15px 20px;color:#1f2937;line-height:1.3;font-size:11px}\'+
            \'.header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #667eea;padding-bottom:8px;margin-bottom:12px}\'+
            \'.logo{font-size:18px;font-weight:700;color:#667eea}\'+
            \'.logo span{color:#1f2937}\'+
            \'.header-info{text-align:right;font-size:10px;color:#6b7280}\'+
            \'.header-info strong{color:#1f2937}\'+
            \'.title{text-align:center;margin-bottom:12px;padding:8px;background:#667eea;color:#fff;border-radius:6px}\'+
            \'.title h1{font-size:14px;margin:0}\'+
            \'.title p{font-size:10px;margin-top:2px;opacity:0.9}\'+
            \'.two-col{display:flex;gap:12px;margin-bottom:10px}\'+
            \'.section{background:#f9fafb;border-radius:6px;padding:10px 12px;border:1px solid #e5e7eb;flex:1}\'+
            \'.section-title{font-size:9px;font-weight:600;color:#667eea;text-transform:uppercase;margin-bottom:8px;padding-bottom:4px;border-bottom:1px solid #e5e7eb}\'+
            \'.field{display:flex;margin-bottom:4px}\'+
            \'.label{width:55px;font-weight:500;color:#6b7280;font-size:10px}\'+
            \'.value{flex:1;font-size:11px;color:#1f2937}\'+
            \'.value.highlight{color:#667eea;font-weight:600}\'+
            \'.problem-section{background:#f9fafb;border-radius:6px;padding:10px 12px;border:1px solid #e5e7eb;margin-bottom:10px}\'+
            \'.datenschutz{background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;padding:8px 10px;margin-bottom:10px}\'+
            \'.datenschutz-title{font-weight:600;color:#92400e;margin-bottom:4px;font-size:9px;text-transform:uppercase}\'+
            \'.datenschutz-text{font-size:8px;color:#78350f;line-height:1.4}\'+
            \'.datenschutz-text ul{margin:4px 0;padding-left:14px}\'+
            \'.signature-area{display:flex;gap:20px;margin-top:10px;padding-top:8px;border-top:1px dashed #d1d5db}\'+
            \'.signature-box{flex:1}\'+
            \'.signature-box label{display:block;font-size:9px;color:#6b7280;margin-bottom:4px}\'+
            \'.signature-line{border-bottom:1px solid #1f2937;height:35px}\'+
            \'.sig-img{height:35px;display:flex;align-items:flex-end;border-bottom:1px solid #ccc}\'+
            \'.footer{text-align:center;margin-top:8px;font-size:9px;color:#9ca3af}\'+
            \'@media print{body{padding:10px 15px}@page{margin:10mm}}\'+
            \'</style></head><body>\'+
            \'<div class="header">\'+
                \'<div class="logo">\'+esc(STORE_COMPANY)+(STORE_OWNER?\'<br><span style="font-size:10px;font-weight:normal;color:#6b7280">\'+L.print_owner+\' \'+esc(STORE_OWNER)+\'</span>\':\'\')+\'</div>\'+
                \'<div class="header-info">\'+(STORE_ADDRESS?esc(STORE_ADDRESS)+\'<br>\':\'\')+
                (STORE_PHONE?\'<strong>\'+L.print_tel+\': \'+esc(STORE_PHONE)+\'</strong><br>\':\'\')+
                (STORE_EMAIL?L.print_email+\': \'+esc(STORE_EMAIL)+\'<br>\':\'\')+
                (STORE_TAX_ID?L.print_vatid+\': \'+esc(STORE_TAX_ID):\'\')+
                \'</div>\'+
            \'</div>\'+
            \'<div class="title"><h1>\'+L.print_title+\' #\'+data.id+\'</h1><p>\'+L.print_date+\': \'+esc(data.date)+\'</p></div>\'+
            \'<div class="two-col">\'+
                \'<div class="section"><div class="section-title">\'+L.print_customer+\'</div>\'+
                    \'<div class="field"><span class="label">\'+L.print_name+\':</span><span class="value">\'+esc(data.name)+\'</span></div>\'+
                    \'<div class="field"><span class="label">\'+L.print_phone+\':</span><span class="value">\'+esc(data.phone)+\'</span></div>\'+
                    \'<div class="field"><span class="label">\'+L.print_email+\':</span><span class="value">\'+esc(data.email)+\'</span></div>\'+
                    addressHtml+
                \'</div>\'+
                \'<div class="section"><div class="section-title">\'+L.print_device+\'</div>\'+
                    \'<div class="field"><span class="label">\'+L.print_device+\':</span><span class="value">\'+esc(device)+\'</span></div>\'+
                    imeiHtml+
                    pinHtml+
                    musterHtml+
                    cfHtml+
                \'</div>\'+
            \'</div>\'+
            \'<div class="problem-section"><div class="section-title">\'+L.print_problem+\'</div>\'+
                \'<div style="font-size:11px">\'+esc(data.problem)+\'</div>\'+
                accHtml+
            \'</div>\'+
            \'<div class="datenschutz">\'+
                \'<div class="datenschutz-title">\'+L.print_privacy+\'</div>\'+
                \'<div class="datenschutz-text">\'+L.print_privacy_text+\'</div>\'+
            \'</div>\'+
            \'<div class="signature-area">\'+
                \'<div class="signature-box" style="max-width:250px"><label>\'+L.print_sig_customer+\':</label>\'+signatureHtml+\'</div>\'+
            \'</div>\'+
            \'<div class="footer">\'+esc(STORE_COMPANY)+(STORE_ADDRESS?\' | \'+esc(STORE_ADDRESS):\'\')+(STORE_PHONE?\' | \'+L.print_tel+\': \'+esc(STORE_PHONE):\'\')+(STORE_EMAIL?\' | \'+esc(STORE_EMAIL):\'\')+\'</div>\'+
            \'<script>window.onload=function(){window.print();}<\\/script>\'+
            \'</body></html>\';
        w.document.write(html);
        w.document.close();
    }

    /* ===== Email Repair ===== */
    document.getElementById("ra-repairs-list").addEventListener("click",function(e){
        var btn=e.target.closest(".ra-btn-email");
        if(!btn)return;
        var card=btn.closest(".ra-repair-card");
        if(!card)return;
        var email=card.dataset.email;
        if(!email){
            toast(L.no_email);
            return;
        }
        if(!confirm(L.send_repair.replace("%s",email))){
            return;
        }
        btn.disabled=true;
        btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i>\';
        var fd=new FormData();
        fd.append("action","ppv_repair_send_email");
        fd.append("nonce",NONCE);
        fd.append("repair_id",card.dataset.id);
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(res){
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-mail-send-line"></i>\';
            if(res.success){
                toast(L.email_sent);
            }else{
                toast(res.data&&res.data.message?res.data.message:L.send_error);
            }
        })
        .catch(function(){
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-mail-send-line"></i>\';
            toast(L.connection_error);
        });
    });

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
            /* Preserve reward sections before clearing (polling would lose them) */
            var savedRewards={};
            if(page===1){
                list.querySelectorAll(".ra-repair-card").forEach(function(card){
                    var rid=card.dataset.id;
                    var el=card.querySelector(".ra-reward-toggle-section")||card.querySelector(".ra-reward-approved-badge");
                    if(el){savedRewards[rid]=card.removeChild(el)}
                });
                list.innerHTML="";
            }
            if(repairs.length===0&&page===1){
                list.innerHTML=\'<div class="ra-empty"><i class="ri-search-line"></i><p>\'+L.no_results+\'</p></div>\';
            }else{
                repairs.forEach(function(r){
                    list.insertAdjacentHTML("beforeend",buildCardHTML(r));
                });
                /* Re-attach saved reward sections into rebuilt cards */
                for(var rid in savedRewards){
                    var card=list.querySelector(\'.ra-repair-card[data-id="\'+rid+\'"]\');
                    if(card){
                        var actions=card.querySelector(".ra-repair-actions");
                        if(actions){card.insertBefore(savedRewards[rid],actions)}
                    }
                }
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

    /* ===== Auto-Polling: refresh repairs list every 15s ===== */
    var pollTimer=null;
    function pollRepairs(){
        if(document.hidden)return;
        if(searchInput.value.trim()!==""||filterSelect.value!=="")return;
        doSearch(1);
    }
    function startPoll(){if(!pollTimer)pollTimer=setInterval(pollRepairs,15000)}
    function stopPoll(){if(pollTimer){clearInterval(pollTimer);pollTimer=null}}
    document.addEventListener("visibilitychange",function(){
        if(document.hidden){stopPoll()}else{startPoll();pollRepairs()}
    });
    startPoll();

    /* ===== Build Card HTML (client side) ===== */
    function buildCardHTML(r){
        var map={
            new:[L.status_new,"ra-status-new"],
            in_progress:[L.status_progress,"ra-status-progress"],
            waiting_parts:[L.status_waiting,"ra-status-waiting"],
            done:[L.status_done,"ra-status-done"],
            delivered:[L.status_delivered,"ra-status-delivered"],
            cancelled:[L.status_cancelled,"ra-status-cancelled"]
        };
        var st=map[r.status]||["?",""];
        var device=((r.device_brand||"")+" "+(r.device_model||"")).trim();
        var d=r.created_at?new Date(r.created_at.replace(/-/g,"/")):"";
        var dateStr=d?pad(d.getDate())+"."+pad(d.getMonth()+1)+"."+d.getFullYear()+" "+pad(d.getHours())+":"+pad(d.getMinutes()):"";
        var problem=(r.problem_description||"");
        if(problem.length>150)problem=problem.substring(0,150)+"...";
        var fullProblem=r.problem_description||"";
        var opts=["new","in_progress","waiting_parts","done","delivered","cancelled"];
        var labels=[L.status_new,L.status_progress,L.status_waiting,L.status_done,L.status_delivered,L.status_cancelled];
        var selectHtml="";
        for(var i=0;i<opts.length;i++){
            selectHtml+=\'<option value="\'+opts[i]+\'" \'+(r.status===opts[i]?"selected":"")+\'>\'+labels[i]+\'</option>\';
        }
        var addressHtml=r.customer_address?\'<div class="ra-repair-address"><i class="ri-map-pin-line"></i> \'+esc(r.customer_address)+\'</div>\':"";
        var musterHtml=(r.muster_image&&r.muster_image.indexOf("data:image/")===0)?\'<div class="ra-repair-muster"><img src="\'+r.muster_image+\'" alt="\'+L.print_muster+\'" title="\'+L.print_muster+\'"></div>\':"";
        var hasInvoice=!!(r.invoice_numbers);
        var invoiceBadgeHtml=hasInvoice?\'<div class="ra-invoice-badge"><i class="ri-file-list-3-line"></i> \'+esc(r.invoice_numbers)+\'</div>\':"";
        var invBtnClass=hasInvoice?"ra-btn-invoice ra-btn-invoice-exists":"ra-btn-invoice";
        var invBtnTitle=hasInvoice?(L.invoice_exists+": "+esc(r.invoice_numbers)):L.btn_create_inv;
        var invBtnIcon=hasInvoice?"ri-file-list-3-fill":"ri-file-list-3-line";
        // Accessories (skip empty/[] values)
        var acc=r.accessories||"";
        var accHtml=(acc&&acc!=="[]"&&acc!=="[\\"\\"]")?\'<div class="ra-repair-acc"><i class="ri-checkbox-multiple-line"></i> \'+esc(acc)+\'</div>\':"";
        // IMEI
        var imeiHtml=r.device_imei?\'<div class="ra-repair-imei"><i class="ri-barcode-line"></i> IMEI: \'+esc(r.device_imei)+\'</div>\':"";
        // PIN
        var pinHtml=r.device_pattern?\'<div class="ra-repair-pin"><i class="ri-lock-password-line"></i> PIN: \'+esc(r.device_pattern)+\'</div>\':"";
        // Device section
        var deviceSection="";
        if(device||imeiHtml||pinHtml||musterHtml||accHtml){
            deviceSection=\'<div class="ra-card-section ra-card-section-device">\'+
                \'<div class="ra-card-section-title"><i class="ri-smartphone-line"></i> \'+(L.device_section||"Gerät")+\'</div>\'+
                (device?\'<div class="ra-device-name">\'+esc(device)+\'</div>\':"")+
                imeiHtml+pinHtml+musterHtml+accHtml+
            \'</div>\';
        }
        // Badges
        var badges="";
        if(invoiceBadgeHtml)badges+=invoiceBadgeHtml;
        if(r.termin_at&&new Date(r.termin_at.replace(/-/g,"/"))>new Date()){
            badges+=\'<div class="ra-termin-badge"><i class="ri-calendar-check-line"></i> \'+new Date(r.termin_at.replace(/-/g,"/")).toLocaleDateString("de-DE")+\'</div>\';
        }
        var badgesRow=badges?\'<div class="ra-card-badges">\'+badges+\'</div>\':"";
        // Comments
        var commentsHtml=\'<div class="ra-repair-comments-section">\'+
            \'<button class="ra-btn-comments-toggle" data-repair-id="\'+r.id+\'"><i class="ri-chat-3-line"></i> \'+L.comments_label+\'</button>\'+
            \'<div class="ra-comments-container" style="display:none" data-repair-id="\'+r.id+\'">\'+
                \'<div class="ra-comments-list"></div>\'+
                \'<div class="ra-comment-add">\'+
                    \'<input type="text" class="ra-comment-input" placeholder="\'+esc(L.add_comment_ph)+\'">\'+
                    \'<button class="ra-comment-submit"><i class="ri-send-plane-fill"></i></button>\'+
                \'</div>\'+
            \'</div>\'+
        \'</div>\';
        return \'<div class="ra-repair-card" data-id="\'+r.id+\'" data-status="\'+r.status+\'" data-name="\'+esc(r.customer_name)+\'" data-email="\'+esc(r.customer_email)+\'" data-phone="\'+esc(r.customer_phone||"")+\'" data-address="\'+esc(r.customer_address||"")+\'" data-brand="\'+esc(r.device_brand||"")+\'" data-model="\'+esc(r.device_model||"")+\'" data-pin="\'+esc(r.device_pattern||"")+\'" data-imei="\'+esc(r.device_imei||"")+\'" data-problem="\'+esc(fullProblem)+\'" data-date="\'+dateStr+\'" data-muster="\'+esc(r.muster_image||"")+\'" data-signature="\'+esc(r.signature_image||"")+\'" data-accessories="\'+esc(r.accessories||"")+\'" data-invoice="\'+esc(r.invoice_numbers||"")+\'">\'+
            \'<div class="ra-repair-header">\'+
                \'<div class="ra-repair-header-left"><div class="ra-repair-id">#\'+r.id+\'</div><div class="ra-repair-date-inline"><i class="ri-time-line"></i> \'+dateStr+\'</div></div>\'+
                \'<span class="ra-status \'+st[1]+\'">\'+st[0]+\'</span>\'+
            \'</div>\'+
            \'<div class="ra-card-section ra-card-section-customer">\'+
                \'<div class="ra-customer-row">\'+
                    \'<div class="ra-customer-avatar"><i class="ri-user-3-fill"></i></div>\'+
                    \'<div class="ra-customer-info">\'+
                        \'<strong>\'+esc(r.customer_name)+\'</strong>\'+
                        \'<div class="ra-repair-meta">\'+
                            (r.customer_email?\'<span><i class="ri-mail-line"></i> \'+esc(r.customer_email)+\'</span>\':"")+
                            (r.customer_phone?\'<span><i class="ri-phone-line"></i> \'+esc(r.customer_phone)+\'</span>\':"")+
                        \'</div>\'+
                        addressHtml+
                    \'</div>\'+
                \'</div>\'+
            \'</div>\'+
            deviceSection+
            \'<div class="ra-card-section ra-card-section-problem">\'+
                \'<div class="ra-card-section-title"><i class="ri-error-warning-line"></i> \'+(L.problem_section||"Problem")+\'</div>\'+
                \'<div class="ra-repair-problem">\'+esc(problem)+\'</div>\'+
            \'</div>\'+
            (r.tracking_token?\'<div class="ra-card-section ra-tracking-section"><div class="ra-card-section-title"><i class="ri-live-line"></i> Live-Tracking</div><div class="ra-tracking-row"><button type="button" class="ra-tracking-btn ra-tracking-btn-copy" onclick="var u=\\\'\'+esc(FORM_BASE_URL+r.tracking_token)+\'\\\';navigator.clipboard?navigator.clipboard.writeText(u).then(function(){}.bind(this)):function(){var t=document.createElement(\\\'textarea\\\');t.value=u;document.body.appendChild(t);t.select();document.execCommand(\\\'copy\\\');document.body.removeChild(t)}();this.innerHTML=\\\'<i class=&quot;ri-check-line&quot;></i> Kopiert!\\\';var b=this;setTimeout(function(){b.innerHTML=\\\'<i class=&quot;ri-file-copy-line&quot;></i> Link kopieren\\\'},1500)"><i class="ri-file-copy-line"></i> Link kopieren</button><a href="\'+esc(FORM_BASE_URL+r.tracking_token)+\'" target="_blank" class="ra-tracking-btn ra-tracking-btn-open"><i class="ri-external-link-line"></i> Öffnen</a></div></div>\':"")+
            badgesRow+
            (r.status==="waiting_parts"?\'<button class="ra-btn-parts-arrived" data-repair-id="\'+r.id+\'"><i class="ri-checkbox-circle-fill"></i> \'+L.parts_arrived+\'</button>\':"")+
            commentsHtml+
            \'<div class="ra-repair-actions">\'+
                \'<div class="ra-actions-left">\'+
                    \'<button class="ra-btn-print" title="\'+L.btn_print+\'"><i class="ri-printer-line"></i></button>\'+
                    \'<button class="ra-btn-email" title="\'+L.btn_email+\'"><i class="ri-mail-send-line"></i></button>\'+
                    \'<button class="ra-btn-resubmit" title="\'+L.btn_resubmit+\'"><i class="ri-repeat-line"></i></button>\'+
                    \'<button class="\'+invBtnClass+\'" title="\'+invBtnTitle+\'"><i class="\'+invBtnIcon+\'"></i></button>\'+
                    \'<button class="ra-btn-angebot" title="\'+L.btn_angebot+\'"><i class="ri-draft-line"></i></button>\'+
                    \'<button class="ra-btn-delete" title="\'+L.btn_delete+\'"><i class="ri-delete-bin-line"></i></button>\'+
                \'</div>\'+
                \'<select class="ra-status-select" data-repair-id="\'+r.id+\'">\'+selectHtml+\'</select>\'+
            \'</div>\'+
        \'</div>\';
    }
    function pad(n){return n<10?"0"+n:n}
    function esc(s){if(!s)return"";var d=document.createElement("div");d.textContent=s;return d.innerHTML}

    /* ===== Invoice Modal (Fertig status) ===== */
    var invoiceModal=document.getElementById("ra-invoice-modal"),
        invoiceRepairId=null,
        invoiceSelect=null,
        invoiceEditId=null;

    // Hide VAT row if Kleinunternehmer
    if(!VAT_ENABLED){document.getElementById("ra-inv-modal-vat-row").style.display="none"}

    function buildLineHtml(desc,amt){
        return \'<div class="ra-inv-line"><input type="text" placeholder="\'+L.service+\'" class="ra-inv-line-desc" value="\'+esc(desc||"")+\'"><input type="number" placeholder="\'+L.gross+\'" step="0.01" min="0" class="ra-inv-line-amount" value="\'+(amt||"")+\'"><button type="button" class="ra-inv-line-remove" title="\'+L.delete+\'">&times;</button></div>\';
    }

    function showInvoiceModal(repairId,selectEl){
        invoiceRepairId=repairId;
        invoiceSelect=selectEl;
        invoiceEditId=null;
        // Title for create mode
        document.getElementById("ra-inv-modal-title").innerHTML=\'<i class="ri-file-list-3-line"></i> \'+L.finish_title;
        document.getElementById("ra-inv-modal-subtitle").textContent=L.inv_subtitle;
        document.getElementById("ra-inv-modal-submit").innerHTML=\'<i class="ri-check-line"></i> \'+L.finish_inv;
        // Show device info
        var card=selectEl.closest(".ra-repair-card");
        var info=document.getElementById("ra-inv-modal-info");
        if(card){
            var cname=card.querySelector(".ra-repair-customer strong");
            var cdev=card.querySelector(".ra-repair-device");
            var txt=(cname?cname.textContent:"")+(cdev?" \u2013 "+cdev.textContent.trim():"");
            if(txt){info.textContent=txt;info.style.display="block"}else{info.style.display="none"}
        }
        // Reset lines
        document.getElementById("ra-inv-lines").innerHTML=buildLineHtml("","");
        // Auto-populate reward discount if approved
        var discSec=document.getElementById("ra-inv-discount-section");
        var discDesc=document.getElementById("ra-inv-discount-desc");
        var discAmt=document.getElementById("ra-inv-discount-amount");
        if(card && card.dataset.rewardApproved==="1" && PP_ENABLED && REWARD_VALUE>0){
            discDesc.value=REWARD_NAME;
            discAmt.value=REWARD_VALUE.toFixed(2);
            discSec.style.display="block";
        }else{
            discDesc.value="";
            discAmt.value="";
            discSec.style.display="none";
        }
        // Reset payment fields
        document.getElementById("ra-inv-paid-toggle").checked=false;
        document.getElementById("ra-inv-paid-fields").style.display="none";
        document.getElementById("ra-inv-payment-method").value="";
        document.getElementById("ra-inv-paid-date").value=new Date().toISOString().split("T")[0];
        document.getElementById("ra-inv-warranty-date").value="";
        document.getElementById("ra-inv-warranty-desc").value="";
        document.getElementById("ra-inv-warranty-desc").style.display="none";
        recalcInvoiceModal();
        invoiceModal.classList.add("show");
        document.querySelector("#ra-inv-lines .ra-inv-line-desc").focus();
    }

    // Toggle payment fields visibility
    document.getElementById("ra-inv-paid-toggle").addEventListener("change",function(){
        document.getElementById("ra-inv-paid-fields").style.display=this.checked?"block":"none";
    });

    function buildEinvLineHtml(desc,amt){
        var idx=0;
        var price=amt||0;
        return \'<div class="nang-line" data-idx="\'+idx+\'"><input type="text" placeholder="\'+L.service+\'" class="nang-line-desc" value="\'+esc(desc||"")+\'"><input type="number" value="1" min="1" step="1" class="nang-line-qty"><div class="nang-line-price-wrap"><input type="number" placeholder="0,00" step="0.01" min="0" class="nang-line-price" value="\'+(price||"")+\'"><span class="nang-line-currency">&euro;</span></div><span class="nang-line-total">\'+(price?fmtEur(price):\'0,00 &euro;\')+\'</span><button type="button" class="nang-line-del" title="\'+L["delete"]+\'"><i class="ri-delete-bin-line"></i></button></div>\';
    }

    function showEditInvoiceModal(inv){
        var modal=document.getElementById("ra-edit-invoice-modal");
        // Reset to step 1
        document.getElementById("einv-sec-customer").style.display="block";
        document.getElementById("einv-sec-positions").style.display="none";
        modal.querySelectorAll(".nang-step").forEach(function(s,i){
            s.classList.toggle("active",i===0);
            var num=s.querySelector(".nang-step-num");
            if(num){num.style.background=i===0?"#2563eb":"";num.style.color=i===0?"#fff":""}
        });

        document.getElementById("ra-einv-id").value=inv.id;
        document.getElementById("ra-einv-number-input").value=inv.invoice_number;
        var d=new Date(inv.created_at);
        document.getElementById("ra-einv-date").textContent=d.toLocaleDateString("de-DE");

        // Fill customer data
        document.getElementById("ra-einv-name").value=inv.customer_name||"";
        document.getElementById("ra-einv-company").value=inv.customer_company||"";
        document.getElementById("ra-einv-email").value=inv.customer_email||"";
        document.getElementById("ra-einv-phone").value=inv.customer_phone||"";
        document.getElementById("ra-einv-address").value=inv.customer_address||"";
        document.getElementById("ra-einv-plz").value=inv.customer_plz||"";
        document.getElementById("ra-einv-city").value=inv.customer_city||"";
        document.getElementById("ra-einv-taxid").value=inv.customer_tax_id||"";
        document.getElementById("ra-einv-notes").value=inv.notes||"";
        document.getElementById("ra-einv-warranty-date").value=inv.warranty_date||"";
        document.getElementById("ra-einv-warranty-desc").value=inv.warranty_description||"";
        document.getElementById("ra-einv-warranty-desc-wrap").style.display=inv.warranty_date?"block":"none";
        document.getElementById("ra-einv-differenz").checked=!!parseInt(inv.is_differenzbesteuerung);

        // Fill payment status
        var isPaid=inv.status==="paid";
        document.getElementById("ra-einv-paid-toggle").checked=isPaid;
        document.getElementById("ra-einv-paid-fields").style.display=isPaid?"block":"none";
        document.getElementById("ra-einv-payment-method").value=inv.payment_method||"";
        document.getElementById("ra-einv-paid-date").value=inv.paid_at?inv.paid_at.split(" ")[0]:new Date().toISOString().split("T")[0];

        // Fill line items (nang-line format)
        var lines=document.getElementById("ra-einv-lines");
        var items=[];
        try{items=JSON.parse(inv.line_items||"[]")}catch(e){}
        if(items&&items.length>0){
            lines.innerHTML=items.map(function(it){return buildEinvLineHtml(it.description,it.amount)}).join("");
        }else{
            lines.innerHTML=buildEinvLineHtml("",inv.subtotal||"");
        }
        recalcEditInvoiceModal();
        modal.classList.add("show");
    }

    function hideInvoiceModal(){
        invoiceModal.classList.remove("show");
        invoiceRepairId=null;
        invoiceSelect=null;
        invoiceEditId=null;
    }

    // Payment modal handling
    var paymentOnConfirm=null;
    var paymentOnCancel=null;
    function showPaymentModal(invId,onConfirm,onCancel){
        paymentOnConfirm=onConfirm;
        paymentOnCancel=onCancel;
        document.getElementById("ra-payment-inv-id").value=invId;
        document.getElementById("ra-payment-method").value="";
        document.getElementById("ra-payment-date").value=new Date().toISOString().split("T")[0];
        document.getElementById("ra-payment-modal").classList.add("show");
    }
    function hidePaymentModal(){
        document.getElementById("ra-payment-modal").classList.remove("show");
        paymentOnConfirm=null;
        paymentOnCancel=null;
    }
    document.getElementById("ra-payment-cancel").addEventListener("click",function(){
        if(paymentOnCancel)paymentOnCancel();
        hidePaymentModal();
    });
    document.getElementById("ra-payment-submit").addEventListener("click",function(){
        var method=document.getElementById("ra-payment-method").value;
        var paidAt=document.getElementById("ra-payment-date").value;
        if(paymentOnConfirm)paymentOnConfirm(method,paidAt);
        hidePaymentModal();
    });
    document.getElementById("ra-payment-modal").addEventListener("click",function(e){
        if(e.target===this){
            if(paymentOnCancel)paymentOnCancel();
            hidePaymentModal();
        }
    });

    function recalcInvoiceModal(){
        var amounts=document.querySelectorAll("#ra-inv-lines .ra-inv-line-amount");
        var brutto=0;
        amounts.forEach(function(a){brutto+=parseFloat(a.value)||0});
        // Subtract discount if visible
        var discSec=document.getElementById("ra-inv-discount-section");
        var discountVal=0;
        if(discSec&&discSec.style.display!=="none"){
            discountVal=parseFloat(document.getElementById("ra-inv-discount-amount").value)||0;
        }
        var afterDiscount=Math.max(0,brutto-discountVal);
        var net,vat;
        if(VAT_ENABLED){
            net=Math.round(afterDiscount/(1+VAT_RATE/100)*100)/100;
            vat=Math.round((afterDiscount-net)*100)/100;
        }else{
            net=afterDiscount;
            vat=0;
        }
        document.getElementById("ra-inv-modal-net").textContent=fmtEur(net);
        document.getElementById("ra-inv-modal-vat").textContent=fmtEur(vat);
        document.getElementById("ra-inv-modal-total").textContent=fmtEur(afterDiscount);
    }

    // Line items: delegated events
    document.getElementById("ra-inv-lines").addEventListener("click",function(e){
        var rm=e.target.closest(".ra-inv-line-remove");
        if(rm){
            var line=rm.closest(".ra-inv-line");
            if(document.querySelectorAll("#ra-inv-lines .ra-inv-line").length>1){
                line.remove();
            }else{
                line.querySelector(".ra-inv-line-desc").value="";
                line.querySelector(".ra-inv-line-amount").value="";
            }
            recalcInvoiceModal();
        }
    });
    document.getElementById("ra-inv-lines").addEventListener("input",function(e){
        if(e.target.classList.contains("ra-inv-line-amount"))recalcInvoiceModal();
    });
    // Discount amount input recalc
    document.getElementById("ra-inv-discount-amount").addEventListener("input",function(){recalcInvoiceModal()});
    // Discount remove button
    document.getElementById("ra-inv-discount-remove").addEventListener("click",function(){
        document.getElementById("ra-inv-discount-section").style.display="none";
        document.getElementById("ra-inv-discount-desc").value="";
        document.getElementById("ra-inv-discount-amount").value="";
        recalcInvoiceModal();
    });

    document.getElementById("ra-inv-add-line").addEventListener("click",function(){
        var lines=document.getElementById("ra-inv-lines");
        var line=document.createElement("div");
        line.className="ra-inv-line";
        line.innerHTML=\'<input type="text" placeholder="\'+L.service+\'" class="ra-inv-line-desc"><input type="number" placeholder="\'+L.gross+\'" step="0.01" min="0" class="ra-inv-line-amount"><button type="button" class="ra-inv-line-remove" title="\'+L.delete+\'">&times;</button>\';
        lines.appendChild(line);
        line.querySelector(".ra-inv-line-desc").focus();
    });

    document.getElementById("ra-inv-modal-cancel").addEventListener("click",function(){
        if(invoiceSelect)invoiceSelect.value=invoiceSelect.getAttribute("data-prev")||"in_progress";
        hideInvoiceModal();
    });

    // Skip invoice, just mark as done
    document.getElementById("ra-inv-modal-skip").addEventListener("click",function(){
        if(!invoiceRepairId){hideInvoiceModal();return;}
        var btn=this;
        btn.disabled=true;
        btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i>\';
        var fd=new FormData();
        fd.append("action","ppv_repair_update_status");
        fd.append("nonce",NONCE);
        fd.append("repair_id",invoiceRepairId);
        fd.append("status","done");
        fd.append("skip_invoice","1");
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){
                toast(L.marked_done);
                if(invoiceSelect){
                    updateBadge(invoiceSelect.closest(".ra-repair-card"),"done");
                    invoiceSelect.value="done";
                    invoiceSelect.setAttribute("data-prev","done");
                }
                hideInvoiceModal();
            }else{
                toast(data.data&&data.data.message?data.data.message:L.error);
            }
        })
        .catch(function(){toast(L.connection_error)})
        .finally(function(){
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-check-line"></i> \'+L.only_done;
        });
    });

    document.getElementById("ra-inv-modal-submit").addEventListener("click",function(){
        var items=[];
        document.querySelectorAll("#ra-inv-lines .ra-inv-line").forEach(function(line){
            var desc=line.querySelector(".ra-inv-line-desc").value.trim();
            var amt=parseFloat(line.querySelector(".ra-inv-line-amount").value)||0;
            if(desc||amt>0)items.push({description:desc,amount:amt});
        });
        if(items.length===0){toast(L.min_one_item);return}
        var totalAmt=0;
        items.forEach(function(i){totalAmt+=i.amount});

        var fd=new FormData();
        fd.append("nonce",NONCE);

        // Add differenzbesteuerung flag
        fd.append("is_differenzbesteuerung",document.getElementById("ra-inv-differenz").checked?"1":"0");

        // Collect manual discount from modal (if visible)
        var discSec=document.getElementById("ra-inv-discount-section");
        var manualDiscDesc="",manualDiscVal=0;
        if(discSec&&discSec.style.display!=="none"){
            manualDiscDesc=document.getElementById("ra-inv-discount-desc").value.trim();
            manualDiscVal=parseFloat(document.getElementById("ra-inv-discount-amount").value)||0;
        }

        // Warranty date (both modes)
        var wDateOld=document.getElementById("ra-inv-warranty-date").value;
        if(wDateOld){fd.append("warranty_date",wDateOld);fd.append("warranty_description",document.getElementById("ra-inv-warranty-desc").value)}

        if(invoiceEditId){
            // Edit mode
            fd.append("action","ppv_repair_invoice_update");
            fd.append("invoice_id",invoiceEditId);
            fd.append("subtotal",totalAmt);
            fd.append("line_items",JSON.stringify(items));
            if(manualDiscVal>0){
                fd.append("manual_discount_desc",manualDiscDesc);
                fd.append("manual_discount_value",manualDiscVal);
            }
        }else{
            // Create mode
            fd.append("action","ppv_repair_update_status");
            fd.append("repair_id",invoiceRepairId);
            fd.append("status","done");
            fd.append("final_cost",totalAmt);
            fd.append("line_items",JSON.stringify(items));
            if(manualDiscVal>0){
                fd.append("manual_discount_desc",manualDiscDesc);
                fd.append("manual_discount_value",manualDiscVal);
            }
            // Check if already paid
            if(document.getElementById("ra-inv-paid-toggle").checked){
                fd.append("mark_paid","1");
                var pm=document.getElementById("ra-inv-payment-method").value;
                var pd=document.getElementById("ra-inv-paid-date").value;
                if(pm)fd.append("payment_method",pm);
                if(pd)fd.append("paid_at",pd);
            }
        }

        var btn=document.getElementById("ra-inv-modal-submit");
        btn.disabled=true;

        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){
                if(invoiceEditId){
                    toast(L.inv_updated);
                }else{
                    toast(L.repair_completed);
                    if(invoiceSelect){
                        updateBadge(invoiceSelect.closest(".ra-repair-card"),"done");
                        invoiceSelect.setAttribute("data-prev","done");
                    }
                }
                invoicesLoaded=false;
                hideInvoiceModal();
                // Reload invoices tab if visible
                if(document.getElementById("ra-tab-invoices").classList.contains("active")){loadInvoices(1)}
            }else{
                toast(data.data&&data.data.message?data.data.message:L.error);
            }
        })
        .catch(function(){toast(L.connection_error)})
        .finally(function(){btn.disabled=false});
    });

    // Close on overlay click
    invoiceModal.addEventListener("click",function(e){
        if(e.target===invoiceModal){
            if(invoiceSelect)invoiceSelect.value=invoiceSelect.getAttribute("data-prev")||"in_progress";
            hideInvoiceModal();
        }
    });

    /* ===== PunktePass Toggle ===== */
    document.getElementById("ra-pp-toggle").addEventListener("change",function(){
        var val=this.checked?1:0;
        document.getElementById("ra-pp-enabled-val").value=val;
        document.getElementById("ra-pp-settings").style.display=this.checked?"":"none";
    });

    /* ===== VAT Toggle ===== */
    document.getElementById("ra-vat-toggle").addEventListener("change",function(){
        var val=this.checked?1:0;
        document.getElementById("ra-vat-enabled-val").value=val;
        document.getElementById("ra-vat-settings").style.display=this.checked?"":"none";
    });

    /* ===== Reward Type Toggle ===== */
    document.getElementById("ra-reward-type").addEventListener("change",function(){
        var pf=document.getElementById("ra-reward-product-field");
        pf.style.display=this.value==="free_product"?"":"none";
    });

    /* ===== Settings Panel Toggle & Persistence ===== */
    var settingsPanel = document.getElementById("ra-settings");
    var settingsToggleBtn = document.getElementById("ra-settings-toggle-btn");

    function toggleSettings(forceShow) {
        var isHidden = settingsPanel.classList.contains("ra-hidden");
        if (forceShow === true || isHidden) {
            settingsPanel.classList.remove("ra-hidden");
            localStorage.setItem("ra_settings_open", "1");
        } else {
            settingsPanel.classList.add("ra-hidden");
            localStorage.setItem("ra_settings_open", "0");
        }
        // Force layout recalculation to fix rendering issues
        setTimeout(function() {
            window.dispatchEvent(new Event("resize"));
        }, 50);
    }

    if (settingsToggleBtn) {
        settingsToggleBtn.addEventListener("click", function() {
            toggleSettings();
        });
    }

    // Restore settings panel state on page load
    if (localStorage.getItem("ra_settings_open") === "1") {
        settingsPanel.classList.remove("ra-hidden");
    }

    // Force repaint on desktop browsers
    window.addEventListener("load", function() {
        var wrap = document.querySelector(".ra-wrap");
        if (wrap) {
            // Force layout recalculation
            wrap.style.display = "none";
            wrap.offsetHeight; // Force reflow
            wrap.style.display = "";

            // Trigger resize event after slight delay
            setTimeout(function() {
                window.dispatchEvent(new Event("resize"));
                // Force scroll to bottom and back
                var docHeight = document.body.scrollHeight;
                window.scrollTo(0, docHeight);
                setTimeout(function() {
                    window.scrollTo(0, 0);
                }, 50);
            }, 100);
        }
    });

    /* ===== Settings Tabs ===== */
    var settingsTabs = document.querySelectorAll(".ra-settings-tab");
    var settingsPanels = document.querySelectorAll(".ra-settings-panel");

    function switchSettingsTab(panelName) {
        settingsTabs.forEach(function(t) { t.classList.remove("active"); });
        settingsPanels.forEach(function(p) { p.classList.remove("active"); });

        var tabBtn = document.querySelector(\'.ra-settings-tab[data-panel="\' + panelName + \'"]\');
        var panel = document.querySelector(\'.ra-settings-panel[data-panel="\' + panelName + \'"]\');

        if (tabBtn) tabBtn.classList.add("active");
        if (panel) panel.classList.add("active");

        localStorage.setItem("ra_settings_tab", panelName);
    }

    settingsTabs.forEach(function(tab) {
        tab.addEventListener("click", function() {
            var panelName = this.getAttribute("data-panel");
            switchSettingsTab(panelName);
        });
    });

    // Restore settings tab on page load
    var savedSettingsTab = localStorage.getItem("ra_settings_tab");
    if (savedSettingsTab) {
        switchSettingsTab(savedSettingsTab);
    }

    /* ===== Widget Embed Tab ===== */
    (function(){
        var wMode = document.getElementById("ra-widget-mode");
        var wPos = document.getElementById("ra-widget-position");
        var wColor = document.getElementById("ra-widget-color");
        var wLang = document.getElementById("ra-widget-lang");
        var wText = document.getElementById("ra-widget-text");
        var wTarget = document.getElementById("ra-widget-target");
        var wTargetWrap = document.getElementById("ra-widget-target-wrap");
        var wTextWrap = document.getElementById("ra-widget-text-wrap");
        var wCode = document.getElementById("ra-widget-code");
        var wCopy = document.getElementById("ra-widget-copy");
        var wCopyLink = document.getElementById("ra-widget-copy-link");
        var wDirectLink = document.getElementById("ra-widget-direct-link");
        var previewFloat = document.getElementById("ra-widget-preview-float");
        var previewInline = document.getElementById("ra-widget-preview-inline");
        var previewButton = document.getElementById("ra-widget-preview-button");
        var previewText = document.getElementById("ra-widget-preview-text");
        var previewBtnText = document.getElementById("ra-widget-preview-btn-text");
        var storeSlug = ' . wp_json_encode($store_slug) . ';
        var baseUrl = ' . wp_json_encode(home_url()) . ';

        if (!wMode || !wCode) return;

        var defaultTexts = {de:"Reparatur anfragen",en:"Request repair",hu:"Javítás kérése",ro:"Solicită reparație",it:"Richiedi riparazione"};

        function darkenHex(hex, pct) {
            var r=parseInt(hex.slice(1,3),16), g=parseInt(hex.slice(3,5),16), b=parseInt(hex.slice(5,7),16);
            r=Math.round(r*(1-pct)); g=Math.round(g*(1-pct)); b=Math.round(b*(1-pct));
            return "#"+((1<<24)+(r<<16)+(g<<8)+b).toString(16).slice(1);
        }

        function updateWidget() {
            var mode = wMode.value;
            var color = wColor.value;
            var lang = wLang.value;
            var text = wText.value.trim();
            var target = wTarget.value.trim();
            var pos = wPos.value;
            var color2 = darkenHex(color, 0.25);
            var grad = "linear-gradient(135deg," + color + "," + color2 + ")";
            var displayText = text || defaultTexts[lang] || defaultTexts.de;

            // Toggle visibility of fields
            wTargetWrap.style.display = (mode === "inline" || mode === "button") ? "" : "none";
            wTextWrap.style.display = (mode === "float" || mode === "button") ? "" : "none";
            wPos.parentNode.style.display = (mode === "float") ? "" : "none";

            // Update preview
            previewFloat.style.display = mode === "float" ? "flex" : "none";
            previewInline.style.display = mode === "inline" ? "" : "none";
            previewButton.style.display = mode === "button" ? "" : "none";

            previewFloat.style.background = grad;
            previewFloat.style.marginLeft = pos === "bottom-left" ? "0" : "auto";
            previewFloat.style.marginRight = pos === "bottom-left" ? "auto" : "0";
            previewText.textContent = displayText;
            previewBtnText.textContent = displayText;

            var inlineHdr = document.getElementById("ra-widget-preview-inline-hdr");
            var inlineCta = document.getElementById("ra-widget-preview-inline-cta");
            var previewBtn = document.getElementById("ra-widget-preview-btn");
            if (inlineHdr) inlineHdr.style.background = grad;
            if (inlineCta) inlineCta.style.background = grad;
            if (previewBtn) previewBtn.style.background = grad;

            // Generate embed code
            var attrs = \'data-store="\' + storeSlug + \'"\';
            if (mode !== "float") attrs += \' data-mode="\' + mode + \'"\';
            if (lang !== "de") attrs += \' data-lang="\' + lang + \'"\';
            if (color !== "#667eea") attrs += \' data-color="\' + color + \'"\';
            if (mode === "float" && pos !== "bottom-right") attrs += \' data-position="\' + pos + \'"\';
            if (text) attrs += \' data-text="\' + text.replace(/"/g, "&quot;") + \'"\';
            if ((mode === "inline" || mode === "button") && target) attrs += \' data-target="\' + target.replace(/"/g, "&quot;") + \'"\';

            var code = \'<script src="\' + baseUrl + \'/formular/shop-widget.js" \' + attrs + \'><\' + \'/script>\';
            if (mode === "inline") {
                code = \'<div id="mein-reparatur-widget"></div>\\n\' + code.replace(\'data-target="\' + target.replace(/"/g, "&quot;") + \'"\', \'data-target="#mein-reparatur-widget"\');
                if (!target) code = \'<div id="mein-reparatur-widget"></div>\\n<script src="\' + baseUrl + \'/formular/shop-widget.js" \' + attrs + \' data-target="#mein-reparatur-widget"><\' + \'/script>\';
            }
            if (mode === "button" && !target) {
                code = \'<div id="mein-reparatur-btn"></div>\\n<script src="\' + baseUrl + \'/formular/shop-widget.js" \' + attrs + \' data-target="#mein-reparatur-btn"><\' + \'/script>\';
            }

            wCode.textContent = code;
        }

        wMode.addEventListener("change", updateWidget);
        wPos.addEventListener("change", updateWidget);
        wColor.addEventListener("input", updateWidget);
        wLang.addEventListener("change", updateWidget);
        wText.addEventListener("input", updateWidget);
        wTarget.addEventListener("input", updateWidget);
        updateWidget();

        // Copy embed code
        if (wCopy) {
            wCopy.addEventListener("click", function() {
                navigator.clipboard.writeText(wCode.textContent).then(function() {
                    wCopy.innerHTML = \'<i class="ri-check-line"></i> Kopiert!\';
                    setTimeout(function() { wCopy.innerHTML = \'<i class="ri-file-copy-line"></i> Kopieren\'; }, 2000);
                });
            });
        }

        // Copy direct link
        if (wCopyLink) {
            wCopyLink.addEventListener("click", function() {
                navigator.clipboard.writeText(wDirectLink.value).then(function() {
                    wCopyLink.innerHTML = \'<i class="ri-check-line"></i> Kopiert!\';
                    setTimeout(function() { wCopyLink.innerHTML = \'<i class="ri-file-copy-line"></i> Kopieren\'; }, 2000);
                });
            });
        }
    })();

    /* ===== Widget AI Configuration Chat ===== */
    (function(){
        var waiInput = document.getElementById("ra-wai-input");
        var waiSend = document.getElementById("ra-wai-send");
        var waiMessages = document.getElementById("ra-wai-messages");
        var waiFile = document.getElementById("ra-wai-file");
        var waiUploadArea = document.getElementById("ra-wai-upload-area");
        var waiUploadStatus = document.getElementById("ra-wai-upload-status");
        var waiServicesDiv = document.getElementById("ra-wai-services");
        var waiServiceList = document.getElementById("ra-wai-service-list");
        var waiBrandsDiv = document.getElementById("ra-wai-brands");
        var waiBrandList = document.getElementById("ra-wai-brand-list");
        var waiBrandCount = document.getElementById("ra-wai-brand-count");

        // Translated strings from PHP
        var waiT = ' . wp_json_encode([
            'setup_done'       => PPV_Lang::t('wai_js_setup_done'),
            'settings_updated' => PPV_Lang::t('wai_js_settings_updated'),
            'error_prefix'     => PPV_Lang::t('wai_js_error_prefix'),
            'error_unknown'    => PPV_Lang::t('wai_js_error_unknown'),
            'conn_error'       => PPV_Lang::t('wai_js_connection_error'),
            'uploading'        => PPV_Lang::t('wai_js_uploading'),
            'upload_success'   => PPV_Lang::t('wai_js_upload_success'),
            'upload_bot_msg'   => PPV_Lang::t('wai_js_upload_bot_msg'),
            'upload_saved'     => PPV_Lang::t('wai_js_upload_saved'),
            'upload_image_msg' => PPV_Lang::t('wai_js_upload_image_msg'),
            'upload_error'     => PPV_Lang::t('wai_js_upload_error'),
            'conn_error_short' => PPV_Lang::t('wai_js_connection_error_short'),
            'service'          => PPV_Lang::t('wai_js_service'),
            'services'         => PPV_Lang::t('wai_js_services'),
            'brand'            => PPV_Lang::t('wai_js_brand'),
            'brands'           => PPV_Lang::t('wai_js_brands'),
        ]) . ';
        var waiServiceCount = document.getElementById("ra-wai-service-count");
        var waiStatus = document.getElementById("ra-wai-status");

        if (!waiInput || !waiSend) return;

        var ajaxUrl = ' . wp_json_encode($ajax_url) . ';
        var nonce = ' . wp_json_encode($nonce) . ';
        var chatHistory = [];
        var sending = false;

        // Load existing config+knowledge to show brands and services
        var existingConfig = ' . wp_json_encode(!empty($store->widget_ai_config) ? json_decode($store->widget_ai_config, true) : null) . ';
        var existingKnowledge = ' . wp_json_encode(!empty($store->widget_ai_knowledge) ? json_decode($store->widget_ai_knowledge, true) : null) . ';
        if (existingConfig && existingConfig.brands && existingConfig.brands.length > 0) {
            renderBrands(existingConfig.brands);
        }
        if (existingKnowledge && existingKnowledge.services && existingKnowledge.services.length > 0) {
            renderServices(existingKnowledge.services);
        }

        function renderBrands(brands) {
            if (!brands || !brands.length) { waiBrandsDiv.style.display = "none"; return; }
            waiBrandsDiv.style.display = "";
            waiBrandCount.textContent = brands.length + " " + (brands.length > 1 ? waiT.brands : waiT.brand);
            var html = "";
            for (var i = 0; i < brands.length; i++) {
                var b = brands[i];
                var label = typeof b === "string" ? b : (b.label || b.id || b);
                var icon = (typeof b === "object" && b.icon) ? b.icon : "";
                html += \'<span style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;background:#eff6ff;border-radius:8px;font-size:12px;color:#1e40af">\' +
                    (icon ? \'<span>\' + icon + \'</span> \' : \'\') +
                    \'<b>\' + escH(label) + \'</b></span>\';
            }
            waiBrandList.innerHTML = html;
        }

        function renderServices(services) {
            if (!services || !services.length) { waiServicesDiv.style.display = "none"; return; }
            waiServicesDiv.style.display = "";
            waiServiceCount.textContent = services.length + " " + (services.length > 1 ? waiT.services : waiT.service);
            var html = "";
            for (var i = 0; i < services.length; i++) {
                var s = services[i];
                html += \'<span style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;background:#f1f5f9;border-radius:8px;font-size:12px;color:#475569">\' +
                    \'<b>\' + escH(s.name) + \'</b>\' +
                    (s.price ? \' <span style="color:#94a3b8">·</span> \' + escH(s.price) : \'\') +
                    (s.time ? \' <span style="color:#94a3b8">·</span> <span style="color:#94a3b8">\' + escH(s.time) + \'</span>\' : \'\') +
                    \'</span>\';
            }
            waiServiceList.innerHTML = html;
        }

        function escH(s) { var d = document.createElement("div"); d.textContent = s; return d.innerHTML; }

        function addMessage(role, text) {
            var div = document.createElement("div");
            div.className = "ra-wai-msg " + role;
            var isBot = role === "bot";
            div.style.cssText = isBot ?
                "background:#f1f5f9;border-radius:12px 12px 12px 4px;padding:10px 14px;font-size:13px;color:#334155;max-width:85%;line-height:1.5" :
                "background:linear-gradient(135deg,#667eea,#4338ca);border-radius:12px 12px 4px 12px;padding:10px 14px;font-size:13px;color:#fff;max-width:85%;align-self:flex-end;line-height:1.5";
            div.innerHTML = text.replace(/\n/g, "<br>");
            waiMessages.appendChild(div);
            waiMessages.scrollTop = waiMessages.scrollHeight;
            return div;
        }

        function showTyping() {
            var div = document.createElement("div");
            div.id = "ra-wai-typing";
            div.style.cssText = "background:#f1f5f9;border-radius:12px 12px 12px 4px;padding:10px 14px;font-size:13px;color:#94a3b8;max-width:85%;line-height:1.5";
            div.innerHTML = \'<span style="display:inline-flex;gap:4px"><span style="animation:ra-wai-dot 1s infinite">·</span><span style="animation:ra-wai-dot 1s .2s infinite">·</span><span style="animation:ra-wai-dot 1s .4s infinite">·</span></span>\';
            waiMessages.appendChild(div);
            waiMessages.scrollTop = waiMessages.scrollHeight;
        }

        function removeTyping() {
            var t = document.getElementById("ra-wai-typing");
            if (t) t.remove();
        }

        // Add typing animation CSS
        var dotStyle = document.createElement("style");
        dotStyle.textContent = "@keyframes ra-wai-dot{0%,60%,100%{opacity:.3}30%{opacity:1}}";
        document.head.appendChild(dotStyle);

        function sendMessage(text) {
            if (sending || !text.trim()) return;
            sending = true;
            waiSend.style.opacity = "0.5";

            addMessage("user", text);
            chatHistory.push({role: "user", content: text});
            waiInput.value = "";
            waiInput.style.height = "36px";
            showTyping();

            var fd = new FormData();
            fd.append("action", "ppv_widget_ai_chat");
            fd.append("nonce", nonce);
            fd.append("message", text);
            fd.append("history", JSON.stringify(chatHistory));

            fetch(ajaxUrl, {method: "POST", body: fd})
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    removeTyping();
                    sending = false;
                    waiSend.style.opacity = "1";

                    if (data.success) {
                        addMessage("bot", data.data.message);
                        chatHistory.push({role: "assistant", content: data.data.message});

                        // Update UI based on applied actions
                        if (data.data.actions_applied && data.data.actions_applied.length > 0) {
                            var acts = data.data.actions_applied;
                            // Update widget controls if mode/color/lang changed
                            var wMode = document.getElementById("ra-widget-mode");
                            var wColor = document.getElementById("ra-widget-color");
                            var wLang = document.getElementById("ra-widget-lang");
                            var wText = document.getElementById("ra-widget-text");
                            var cfg = data.data.config || {};

                            if (acts.indexOf("mode") >= 0 && wMode && cfg.mode) {
                                wMode.value = cfg.mode;
                                wMode.dispatchEvent(new Event("change"));
                            }
                            if (acts.indexOf("color") >= 0 && wColor && cfg.color) {
                                wColor.value = cfg.color;
                                wColor.dispatchEvent(new Event("input"));
                            }
                            if (acts.indexOf("lang") >= 0 && wLang && cfg.lang) {
                                wLang.value = cfg.lang;
                                wLang.dispatchEvent(new Event("change"));
                            }
                            if (acts.indexOf("text") >= 0 && wText && cfg.text) {
                                wText.value = cfg.text;
                                wText.dispatchEvent(new Event("input"));
                            }

                            // Update brands display
                            if (acts.indexOf("brands") >= 0 && cfg.brands) {
                                renderBrands(cfg.brands);
                            }

                            // Update services display
                            if (acts.indexOf("services") >= 0 && data.data.knowledge && data.data.knowledge.services) {
                                renderServices(data.data.knowledge.services);
                            }

                            // Update setup status
                            if (acts.indexOf("setup_complete") >= 0 && waiStatus) {
                                waiStatus.style.background = "#f0fdf4";
                                waiStatus.style.borderColor = "#bbf7d0";
                                waiStatus.style.color = "#166534";
                                waiStatus.innerHTML = \'<i class="ri-checkbox-circle-fill" style="font-size:18px"></i> \' + waiT.setup_done;
                            }

                            // Show a subtle notification for config changes
                            var notif = document.createElement("div");
                            notif.style.cssText = "background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:6px 12px;font-size:12px;color:#166534;text-align:center;margin-top:4px";
                            notif.innerHTML = \'<i class="ri-check-line"></i> \' + acts.length + " " + waiT.settings_updated;
                            waiMessages.appendChild(notif);
                            waiMessages.scrollTop = waiMessages.scrollHeight;
                            setTimeout(function() { notif.style.opacity = "0"; setTimeout(function() { notif.remove(); }, 300); }, 3000);
                        }
                    } else {
                        addMessage("bot", waiT.error_prefix + ": " + (data.data && data.data.message ? data.data.message : waiT.error_unknown));
                    }
                })
                .catch(function() {
                    removeTyping();
                    sending = false;
                    waiSend.style.opacity = "1";
                    addMessage("bot", waiT.conn_error);
                });
        }

        // Send button
        waiSend.addEventListener("click", function() { sendMessage(waiInput.value); });

        // Enter to send (Shift+Enter for new line)
        waiInput.addEventListener("keydown", function(e) {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                sendMessage(waiInput.value);
            }
        });

        // Auto-resize textarea
        waiInput.addEventListener("input", function() {
            this.style.height = "36px";
            this.style.height = Math.min(this.scrollHeight, 100) + "px";
        });

        // Quick action chips
        document.querySelectorAll(".ra-wai-chip").forEach(function(chip) {
            chip.addEventListener("click", function() {
                sendMessage(this.getAttribute("data-msg"));
            });
        });

        // File upload
        if (waiUploadArea && waiFile) {
            waiUploadArea.addEventListener("click", function() { waiFile.click(); });
            waiUploadArea.addEventListener("dragover", function(e) { e.preventDefault(); this.style.borderColor = "#667eea"; this.style.background = "#f0f4ff"; });
            waiUploadArea.addEventListener("dragleave", function() { this.style.borderColor = "#e2e8f0"; this.style.background = "#fafbfc"; });
            waiUploadArea.addEventListener("drop", function(e) {
                e.preventDefault();
                this.style.borderColor = "#e2e8f0";
                this.style.background = "#fafbfc";
                if (e.dataTransfer.files.length) {
                    waiFile.files = e.dataTransfer.files;
                    uploadFile(e.dataTransfer.files[0]);
                }
            });
            waiFile.addEventListener("change", function() {
                if (this.files.length) uploadFile(this.files[0]);
            });
        }

        function uploadFile(file) {
            waiUploadStatus.style.display = "";
            waiUploadStatus.style.background = "#eff6ff";
            waiUploadStatus.style.border = "1.5px solid #bfdbfe";
            waiUploadStatus.style.color = "#1e40af";
            waiUploadStatus.innerHTML = \'<i class="ri-loader-4-line" style="animation:ra-wai-dot 1s infinite"></i> \' + waiT.uploading;

            var fd = new FormData();
            fd.append("action", "ppv_widget_ai_upload");
            fd.append("nonce", nonce);
            fd.append("file", file);

            fetch(ajaxUrl, {method: "POST", body: fd})
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        var d = data.data;
                        waiUploadStatus.style.background = "#f0fdf4";
                        waiUploadStatus.style.borderColor = "#bbf7d0";
                        waiUploadStatus.style.color = "#166534";

                        if (d.file_type === "image" || d.file_type === "pdf" || d.file_type === "other") {
                            // Non-text file: uploaded but no auto-parse possible
                            waiUploadStatus.innerHTML = \'<i class="ri-check-line"></i> \' + escH(d.file_name) + \' \' + waiT.upload_saved;
                            addMessage("bot", waiT.upload_image_msg.replace(\'%s\', escH(d.file_name)));
                        } else {
                            waiUploadStatus.innerHTML = \'<i class="ri-check-line"></i> \' + escH(d.file_name) + \' \' + waiT.upload_success.replace(\'%d\', d.service_count);
                            addMessage("bot", waiT.upload_bot_msg.replace(\'%s\', escH(d.file_name)).replace(\'%d\', d.service_count));
                        }

                        // Update services display
                        if (d.parsed_services && d.parsed_services.length) {
                            renderServices(d.parsed_services);
                        }

                        setTimeout(function() { waiUploadStatus.style.display = "none"; }, 5000);
                    } else {
                        waiUploadStatus.style.background = "#fef2f2";
                        waiUploadStatus.style.borderColor = "#fecaca";
                        waiUploadStatus.style.color = "#991b1b";
                        waiUploadStatus.innerHTML = \'<i class="ri-error-warning-line"></i> \' + (data.data && data.data.message ? escH(data.data.message) : waiT.upload_error);
                    }
                })
                .catch(function() {
                    waiUploadStatus.style.background = "#fef2f2";
                    waiUploadStatus.style.borderColor = "#fecaca";
                    waiUploadStatus.style.color = "#991b1b";
                    waiUploadStatus.innerHTML = \'<i class="ri-error-warning-line"></i> \' + waiT.conn_error_short;
                });
        }
    })();

    /* ===== Tab Switching ===== */
    var invoicesLoaded=false;
    var customersLoaded=false;

    function switchTab(target){
        document.querySelectorAll(".ra-tab").forEach(function(t){t.classList.remove("active")});
        document.querySelectorAll(".ra-tab-content").forEach(function(c){c.classList.remove("active")});
        var tabBtn=document.querySelector(\'.ra-tab[data-tab="\'+target+\'"]\');
        if(tabBtn)tabBtn.classList.add("active");
        var tabContent=document.getElementById("ra-tab-"+target);
        if(tabContent)tabContent.classList.add("active");
        if(target==="invoices"&&!invoicesLoaded){loadInvoices(1);invoicesLoaded=true;}
        if(target==="customers"&&!customersLoaded){loadCustomers(1);customersLoaded=true;}
        localStorage.setItem("ra_active_tab",target);
    }

    document.querySelectorAll(".ra-tab").forEach(function(tab){
        tab.addEventListener("click",function(){
            var target=this.getAttribute("data-tab");
            switchTab(target);
        });
    });

    /* ===== Invoices ===== */

    function fmtEur(n){return parseFloat(n||0).toFixed(2).replace(".",",")+" \u20ac"}

    function loadInvoices(page){
        page=page||1;
        var docType=document.getElementById("ra-inv-type-filter").value;

        // If Ankäufe selected, load from separate endpoint
        if(docType==="ankauf"){
            loadAnkaufList(page);
            return;
        }

        var fd=new FormData();
        fd.append("action","ppv_repair_invoices_list");
        fd.append("nonce",NONCE);
        fd.append("page",page);
        var df=document.getElementById("ra-inv-from").value;
        var dt=document.getElementById("ra-inv-to").value;
        if(df)fd.append("date_from",df);
        if(dt)fd.append("date_to",dt);
        if(docType)fd.append("doc_type",docType);
        fd.append("sort_by",invSortBy);
        fd.append("sort_dir",invSortDir);

        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(!data.success)return;
            var d=data.data;
            // Summary
            document.getElementById("ra-inv-count").textContent=d.summary.count||"0";
            document.getElementById("ra-inv-revenue").textContent=fmtEur(d.summary.revenue);
            document.getElementById("ra-inv-discounts").textContent=fmtEur(d.summary.discounts);

            // Table
            var tbody=document.getElementById("ra-inv-body");
            if(page===1)tbody.innerHTML="";
            if(!d.invoices||d.invoices.length===0){
                if(page===1)tbody.innerHTML=\'<tr><td colspan="8" style="text-align:center;padding:32px;color:#9ca3af;">\'+L.no_entries+\'</td></tr>\';
            }else{
                d.invoices.forEach(function(inv){
                    var statusMap={draft:[L.inv_status_draft,"ra-inv-status-draft"],sent:[L.inv_status_sent,"ra-inv-status-sent"],paid:[L.inv_status_paid,"ra-inv-status-paid"],cancelled:[L.inv_status_cancelled,"ra-inv-status-cancelled"],accepted:[L.inv_status_accepted,"ra-inv-status-paid"],rejected:[L.inv_status_rejected,"ra-inv-status-cancelled"],expired:[L.inv_status_expired,"ra-inv-status-cancelled"]};
                    var st=statusMap[inv.status]||["?",""];
                    var date=inv.created_at?new Date(inv.created_at.replace(/-/g,"/")):"";
                    var dateStr=date?pad(date.getDate())+"."+pad(date.getMonth()+1)+"."+date.getFullYear():"";
                    var vatCol=parseInt(inv.is_kleinunternehmer)?\'<span style="font-size:11px;color:#9ca3af">\'+L.small_business+\'</span>\':fmtEur(inv.vat_amount);
                    var pdfUrl=AJAX+"?action=ppv_repair_invoice_pdf&invoice_id="+inv.id+"&nonce="+NONCE;

                    // Doc type badge
                    var isAngebot=inv.doc_type==="angebot";
                    var typeBadge=isAngebot
                        ?\'<span style="display:inline-block;font-size:9px;padding:2px 5px;border-radius:4px;background:#f0fdf4;color:#16a34a;margin-left:6px">\'+L.inv_type_quote+\'</span>\'
                        :\'<span style="display:inline-block;font-size:9px;padding:2px 5px;border-radius:4px;background:#eff6ff;color:#2563eb;margin-left:6px">\'+L.inv_type_invoice+\'</span>\';

                    var row=document.createElement("tr");
                    row.setAttribute("data-inv-id",inv.id);
                    row.innerHTML=\'<td><input type="checkbox" class="ra-inv-checkbox" data-inv-id="\'+inv.id+\'" style="width:16px;height:16px;cursor:pointer"></td>\'+
                        \'<td data-label="\'+L.col_nr+\'"><strong>\'+esc(inv.invoice_number)+\'</strong>\'+typeBadge+\'</td>\'+
                        \'<td data-label="\'+L.col_date+\'">\'+dateStr+\'</td>\'+
                        \'<td data-label="\'+L.col_customer+\'">\'+esc(inv.customer_name)+\'</td>\'+
                        \'<td data-label="\'+L.col_net+\'">\'+fmtEur(inv.net_amount)+\'</td>\'+
                        \'<td data-label="\'+L.col_vat+\'">\'+vatCol+\'</td>\'+
                        \'<td data-label="\'+L.col_total+\'"><strong>\'+fmtEur(inv.total)+\'</strong></td>\'+
                        \'<td data-label="\'+L.col_status+\'"><span class="ra-inv-status \'+st[1]+\'">\'+st[0]+\'</span></td>\'+
                        \'<td data-label=""><div class="ra-inv-actions">\'+
                            \'<a href="\'+pdfUrl+\'" target="_blank" class="ra-inv-btn ra-inv-btn-pdf"><i class="ri-file-pdf-line"></i> PDF</a>\'+
                            \'<button class="ra-inv-btn ra-inv-btn-email" data-inv-id="\'+inv.id+\'" data-customer-email="\'+esc(inv.customer_email||"")+\'" title="\'+L.email_title+\'"><i class="ri-mail-send-line"></i></button>\'+
                            (inv.status!=="paid"&&inv.doc_type!=="angebot"?\'<button class="ra-inv-btn ra-inv-btn-reminder" data-inv-id="\'+inv.id+\'" title="\'+L.reminder_title+\'" style="background:#fef3c7;color:#d97706"><i class="ri-alarm-warning-line"></i></button>\':\'\')+
                            \'<button class="ra-inv-btn ra-inv-btn-edit" data-invoice=\\\'\'+JSON.stringify(inv).replace(/\'/g,"&#39;")+\'\\\' title="\'+L.edit_btn+\'"><i class="ri-pencil-line"></i></button>\'+
                            \'<select class="ra-inv-btn ra-inv-status-sel" data-inv-id="\'+inv.id+\'" data-old-status="\'+inv.status+\'" style="padding:5px 8px;font-size:12px;border-radius:6px">\'+
                                \'<option value="draft" \'+(inv.status==="draft"?"selected":"")+\'>\'+L.inv_status_draft+\'</option>\'+
                                \'<option value="sent" \'+(inv.status==="sent"?"selected":"")+\'>\'+L.inv_status_sent+\'</option>\'+
                                \'<option value="paid" \'+(inv.status==="paid"?"selected":"")+\'>\'+L.inv_status_paid+\'</option>\'+
                                \'<option value="cancelled" \'+(inv.status==="cancelled"?"selected":"")+\'>\'+L.inv_status_cancelled+\'</option>\'+
                            \'</select>\'+
                            \'<button class="ra-inv-btn ra-inv-btn-del" data-inv-id="\'+inv.id+\'" title="\'+L.delete_btn+\'"><i class="ri-delete-bin-line"></i></button>\'+
                        \'</div></td>\';
                    tbody.appendChild(row);
                });
                // Bind status change
                tbody.querySelectorAll(".ra-inv-status-sel").forEach(function(sel){
                    sel.addEventListener("change",function(){
                        var iid=this.getAttribute("data-inv-id"),ns=this.value;
                        var oldStatus=this.getAttribute("data-old-status");
                        var selEl=this;
                        if(ns==="paid"&&oldStatus!=="paid"){
                            // Show payment method modal
                            showPaymentModal(iid,function(paymentMethod,paidAt){
                                var fd2=new FormData();
                                fd2.append("action","ppv_repair_invoice_update");
                                fd2.append("nonce",NONCE);
                                fd2.append("invoice_id",iid);
                                fd2.append("status",ns);
                                if(paymentMethod)fd2.append("payment_method",paymentMethod);
                                if(paidAt)fd2.append("paid_at",paidAt);
                                fetch(AJAX,{method:"POST",body:fd2,credentials:"same-origin"})
                                .then(function(r){return r.json()})
                                .then(function(res){
                                    toast(res.success?L.marked_paid:L.error);
                                    if(res.success){selEl.setAttribute("data-old-status","paid");invoicesLoaded=false;loadInvoices(1)}
                                });
                            },function(){
                                // Cancelled - revert select
                                selEl.value=oldStatus;
                            });
                        }else{
                            var fd2=new FormData();
                            fd2.append("action","ppv_repair_invoice_update");
                            fd2.append("nonce",NONCE);
                            fd2.append("invoice_id",iid);
                            fd2.append("status",ns);
                            fetch(AJAX,{method:"POST",body:fd2,credentials:"same-origin"})
                            .then(function(r){return r.json()})
                            .then(function(res){
                                toast(res.success?L.status_updated:L.error);
                                if(res.success)selEl.setAttribute("data-old-status",ns);
                            });
                        }
                    });
                });
                // Bind edit
                tbody.querySelectorAll(".ra-inv-btn-edit").forEach(function(btn){
                    btn.addEventListener("click",function(){
                        var inv=JSON.parse(this.getAttribute("data-invoice"));
                        showEditInvoiceModal(inv);
                    });
                });
                // Bind delete
                tbody.querySelectorAll(".ra-inv-btn-del").forEach(function(btn){
                    btn.addEventListener("click",function(){
                        if(!confirm(L.confirm_delete_inv))return;
                        var iid=this.getAttribute("data-inv-id");
                        var row=this.closest("tr");
                        var fd2=new FormData();
                        fd2.append("action","ppv_repair_invoice_delete");
                        fd2.append("nonce",NONCE);
                        fd2.append("invoice_id",iid);
                        fetch(AJAX,{method:"POST",body:fd2,credentials:"same-origin"})
                        .then(function(r){return r.json()})
                        .then(function(res){
                            if(res.success){row.remove();toast(L.inv_deleted);invoicesLoaded=false;loadInvoices(1)}
                            else{toast(res.data&&res.data.message?res.data.message:L.error)}
                        });
                    });
                });
                // Bind email send
                tbody.querySelectorAll(".ra-inv-btn-email").forEach(function(btn){
                    btn.addEventListener("click",function(){
                        var iid=this.getAttribute("data-inv-id");
                        var email=this.getAttribute("data-customer-email");
                        if(!email){toast(L.no_email);return;}
                        if(!confirm(L.send_inv_email.replace("%s",email)))return;
                        btn.disabled=true;
                        btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i>\';
                        var fd2=new FormData();
                        fd2.append("action","ppv_repair_invoice_email");
                        fd2.append("nonce",NONCE);
                        fd2.append("invoice_id",iid);
                        fetch(AJAX,{method:"POST",body:fd2,credentials:"same-origin"})
                        .then(function(r){return r.json()})
                        .then(function(res){
                            btn.disabled=false;
                            btn.innerHTML=\'<i class="ri-mail-send-line"></i>\';
                            if(res.success){toast(L.email_sent);invoicesLoaded=false;loadInvoices(1)}
                            else{toast(res.data&&res.data.message?res.data.message:L.send_error)}
                        });
                    });
                });
                // Reminder button handler
                tbody.querySelectorAll(".ra-inv-btn-reminder").forEach(function(btn){
                    btn.addEventListener("click",function(){
                        var iid=this.getAttribute("data-inv-id");
                        if(!confirm(L.send_reminder))return;
                        btn.disabled=true;
                        btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i>\';
                        var fd2=new FormData();
                        fd2.append("action","ppv_repair_invoice_reminder");
                        fd2.append("nonce",NONCE);
                        fd2.append("invoice_id",iid);
                        fetch(AJAX,{method:"POST",body:fd2,credentials:"same-origin"})
                        .then(function(r){return r.json()})
                        .then(function(res){
                            btn.disabled=false;
                            btn.innerHTML=\'<i class="ri-alarm-warning-line"></i>\';
                            if(res.success){toast(res.data.message||"Erinnerung gesendet!")}
                            else{toast(res.data&&res.data.message?res.data.message:L.send_error)}
                        });
                    });
                });
                // Checkbox handlers for bulk selection
                tbody.querySelectorAll(".ra-inv-checkbox").forEach(function(cb){
                    cb.addEventListener("change",updateBulkSelection);
                });
            }
            // Reset select all checkbox
            document.getElementById("ra-inv-select-all").checked=false;
            updateBulkSelection();
            // Load more
            var lm=document.getElementById("ra-inv-load-more");
            if(d.page<d.pages){
                lm.style.display="";
                document.getElementById("ra-inv-more-btn").setAttribute("data-page",d.page);
            }else{
                lm.style.display="none";
            }
        });
    }

    // Invoice summary toggle
    document.getElementById("ra-inv-summary-toggle").addEventListener("click",function(){
        var summary=document.getElementById("ra-inv-summary");
        var btn=this;
        if(summary.style.display==="none"){
            summary.style.display="grid";
            btn.innerHTML=\'<i class="ri-bar-chart-box-line"></i> \'+L.hide_stats;
            btn.classList.add("active");
        }else{
            summary.style.display="none";
            btn.innerHTML=\'<i class="ri-bar-chart-box-line"></i> \'+L.show_stats;
            btn.classList.remove("active");
        }
    });

    // Invoice filter
    document.getElementById("ra-inv-filter-btn").addEventListener("click",function(){loadInvoices(1)});
    // Auto-reload when type filter changes
    document.getElementById("ra-inv-type-filter").addEventListener("change",function(){loadInvoices(1)});
    // Invoice sorting
    document.querySelectorAll(".ra-inv-table .ra-sortable").forEach(function(th){
        th.addEventListener("click",function(){
            var col=this.getAttribute("data-sort");
            // Toggle direction or set new column
            if(invSortBy===col){
                invSortDir=invSortDir==="asc"?"desc":"asc";
            }else{
                invSortBy=col;
                invSortDir="desc";
            }
            // Update UI
            document.querySelectorAll(".ra-inv-table .ra-sortable").forEach(function(h){
                h.classList.remove("ra-sort-asc","ra-sort-desc");
                var icon=h.querySelector(".ra-sort-icon");
                if(icon)icon.className="ri-arrow-up-down-line ra-sort-icon";
            });
            this.classList.add(invSortDir==="asc"?"ra-sort-asc":"ra-sort-desc");
            var icon=this.querySelector(".ra-sort-icon");
            if(icon)icon.className=(invSortDir==="asc"?"ri-arrow-up-line":"ri-arrow-down-line")+" ra-sort-icon";
            invoicesLoaded=false;
            loadInvoices(1);
        });
    });
    // Invoice load more
    document.getElementById("ra-inv-more-btn").addEventListener("click",function(){
        loadInvoices(parseInt(this.getAttribute("data-page"))+1);
    });
    // Export dropdown toggle
    var invExportBtn=document.getElementById("ra-inv-export-btn");
    var invExportDropdown=document.getElementById("ra-inv-export-dropdown");
    if(invExportBtn&&invExportDropdown){
        invExportBtn.addEventListener("click",function(e){
            e.stopPropagation();
            invExportDropdown.style.display=invExportDropdown.style.display==="none"?"block":"none";
        });
        document.addEventListener("click",function(e){
            if(!invExportBtn.contains(e.target)&&!invExportDropdown.contains(e.target)){
                invExportDropdown.style.display="none";
            }
        });
        // Hover effect for dropdown items
        document.querySelectorAll(".ra-inv-export-opt").forEach(function(opt){
            opt.addEventListener("mouseenter",function(){this.style.background="#f3f4f6"});
            opt.addEventListener("mouseleave",function(){this.style.background="transparent"});
        });
    }

    // Export all invoices (with date filter)
    document.querySelectorAll(".ra-inv-export-opt").forEach(function(opt){
        opt.addEventListener("click",function(e){
            e.preventDefault();
            var format=this.getAttribute("data-format");
            invExportDropdown.style.display="none";
            toast(L.export_prep);
            var df=document.getElementById("ra-inv-from").value;
            var dt=document.getElementById("ra-inv-to").value;
            var docType=document.getElementById("ra-inv-type").value;
            var fd=new FormData();
            fd.append("action","ppv_repair_invoice_export_all");
            fd.append("nonce",NONCE);
            fd.append("format",format);
            if(df)fd.append("date_from",df);
            if(dt)fd.append("date_to",dt);
            if(docType)fd.append("doc_type",docType);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(res){
                if(res.success&&res.data.download_url){
                    var a=document.createElement("a");
                    a.href=res.data.download_url;
                    a.download=res.data.filename||"export";
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    toast(L.export_ok);
                }else{
                    toast(res.data&&res.data.message?res.data.message:L.export_fail);
                }
            });
        });
    });

    // ===== Bulk Selection Functions =====
    function getSelectedInvoiceIds(){
        var ids=[];
        document.querySelectorAll(".ra-inv-checkbox:checked").forEach(function(cb){
            ids.push(cb.getAttribute("data-inv-id"));
        });
        return ids;
    }
    function getSelectedAnkaufIds(){
        var ids=[];
        document.querySelectorAll(".ra-ankauf-checkbox:checked").forEach(function(cb){
            ids.push(cb.getAttribute("data-ankauf-id"));
        });
        return ids;
    }
    function getSelectedIds(){
        var docType=document.getElementById("ra-inv-type-filter").value;
        if(docType==="ankauf"){return getSelectedAnkaufIds()}
        return getSelectedInvoiceIds();
    }
    function isAnkaufMode(){
        return document.getElementById("ra-inv-type-filter").value==="ankauf";
    }
    function updateBulkSelection(){
        var selected=getSelectedIds();
        var bulkBar=document.getElementById("ra-inv-bulk-bar");
        var countEl=document.getElementById("ra-inv-selected-count");
        var ankaufMode=isAnkaufMode();
        if(selected.length>0){
            bulkBar.style.display="block";
            countEl.textContent=selected.length;
            // Show/hide buttons based on mode
            document.getElementById("ra-bulk-paid").style.display=ankaufMode?"none":"";
            document.getElementById("ra-bulk-send").style.display=ankaufMode?"none":"";
            document.getElementById("ra-bulk-reminder").style.display=ankaufMode?"none":"";
        }else{
            bulkBar.style.display="none";
        }
    }
    // Select all checkbox
    document.getElementById("ra-inv-select-all").addEventListener("change",function(){
        var checked=this.checked;
        var selector=isAnkaufMode()?".ra-ankauf-checkbox":".ra-inv-checkbox";
        document.querySelectorAll(selector).forEach(function(cb){cb.checked=checked});
        updateBulkSelection();
    });
    // Bulk cancel
    document.getElementById("ra-bulk-cancel").addEventListener("click",function(){
        document.querySelectorAll(".ra-inv-checkbox,.ra-ankauf-checkbox").forEach(function(cb){cb.checked=false});
        document.getElementById("ra-inv-select-all").checked=false;
        updateBulkSelection();
    });
    // Bulk mark as paid
    document.getElementById("ra-bulk-paid").addEventListener("click",function(){
        var ids=getSelectedInvoiceIds();
        if(!ids.length)return;
        if(!confirm(L.mark_paid_confirm.replace("%d",ids.length)))return;
        var btn=this;btn.disabled=true;btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> \'+L.bulk_processing;
        var fd=new FormData();
        fd.append("action","ppv_repair_invoice_bulk");
        fd.append("nonce",NONCE);
        fd.append("operation","mark_paid");
        fd.append("invoice_ids",JSON.stringify(ids));
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(res){
            btn.disabled=false;btn.innerHTML=\'<i class="ri-checkbox-circle-line"></i> \'+L.bulk_paid_btn;
            if(res.success){toast(res.data.message||L.success);invoicesLoaded=false;loadInvoices(1)}
            else{toast(res.data&&res.data.message?res.data.message:L.error)}
        });
    });
    // Bulk send emails
    document.getElementById("ra-bulk-send").addEventListener("click",function(){
        var ids=getSelectedInvoiceIds();
        if(!ids.length)return;
        if(!confirm(L.send_emails_confirm.replace("%d",ids.length)))return;
        var btn=this;btn.disabled=true;btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> \'+L.sending;
        var fd=new FormData();
        fd.append("action","ppv_repair_invoice_bulk");
        fd.append("nonce",NONCE);
        fd.append("operation","send_email");
        fd.append("invoice_ids",JSON.stringify(ids));
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(res){
            btn.disabled=false;btn.innerHTML=\'<i class="ri-mail-send-line"></i> \'+L.bulk_send_btn;
            if(res.success){toast(res.data.message||L.success);invoicesLoaded=false;loadInvoices(1)}
            else{toast(res.data&&res.data.message?res.data.message:L.error)}
        });
    });
    // Bulk reminders
    document.getElementById("ra-bulk-reminder").addEventListener("click",function(){
        var ids=getSelectedInvoiceIds();
        if(!ids.length)return;
        if(!confirm(L.send_reminders_confirm.replace("%d",ids.length)))return;
        var btn=this;btn.disabled=true;btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> \'+L.sending;
        var fd=new FormData();
        fd.append("action","ppv_repair_invoice_bulk");
        fd.append("nonce",NONCE);
        fd.append("operation","send_reminder");
        fd.append("invoice_ids",JSON.stringify(ids));
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(res){
            btn.disabled=false;btn.innerHTML=\'<i class="ri-alarm-warning-line"></i> \'+L.bulk_reminder_btn;
            if(res.success){toast(res.data.message||L.success)}
            else{toast(res.data&&res.data.message?res.data.message:L.error)}
        });
    });

    // Export dropdown toggle
    var exportBtn=document.getElementById("ra-bulk-export");
    var exportDropdown=document.getElementById("ra-export-dropdown");
    if(exportBtn&&exportDropdown){
        exportBtn.addEventListener("click",function(e){
            e.stopPropagation();
            exportDropdown.style.display=exportDropdown.style.display==="none"?"block":"none";
        });
        document.addEventListener("click",function(e){
            if(!exportBtn.contains(e.target)&&!exportDropdown.contains(e.target)){
                exportDropdown.style.display="none";
            }
        });
        // Hover effect for dropdown items
        document.querySelectorAll(".ra-export-opt").forEach(function(opt){
            opt.addEventListener("mouseenter",function(){this.style.background="#f3f4f6"});
            opt.addEventListener("mouseleave",function(){this.style.background="transparent"});
        });
    }

    // Bulk export
    document.querySelectorAll(".ra-export-opt").forEach(function(opt){
        opt.addEventListener("click",function(e){
            e.preventDefault();
            var ids=getSelectedInvoiceIds();
            if(!ids.length){toast(L.no_inv_selected);return;}
            var format=this.getAttribute("data-format");
            exportDropdown.style.display="none";
            toast(L.export_prep);
            var fd=new FormData();
            fd.append("action","ppv_repair_invoice_bulk");
            fd.append("nonce",NONCE);
            fd.append("operation","export");
            fd.append("format",format);
            fd.append("invoice_ids",JSON.stringify(ids));
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(res){
                if(res.success&&res.data.download_url){
                    var a=document.createElement("a");
                    a.href=res.data.download_url;
                    a.download=res.data.filename||"export";
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    toast(L.export_ok);
                }else{
                    toast(res.data&&res.data.message?res.data.message:L.export_fail);
                }
            });
        });
    });

    // Bulk delete
    document.getElementById("ra-bulk-delete").addEventListener("click",function(){
        var ankaufMode=isAnkaufMode();
        var ids=ankaufMode?getSelectedAnkaufIds():getSelectedInvoiceIds();
        if(!ids.length)return;
        var itemName=ankaufMode?L.ankauf_item:L.document_item;
        if(!confirm(L.bulk_delete.replace("%d",ids.length).replace("%s",itemName)))return;
        var btn=this;btn.disabled=true;btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> \'+L.deleting;
        var fd=new FormData();
        if(ankaufMode){
            fd.append("action","ppv_repair_ankauf_bulk_delete");
            fd.append("nonce",NONCE);
            fd.append("ankauf_ids",JSON.stringify(ids));
        }else{
            fd.append("action","ppv_repair_invoice_bulk");
            fd.append("nonce",NONCE);
            fd.append("operation","delete");
            fd.append("invoice_ids",JSON.stringify(ids));
        }
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(res){
            btn.disabled=false;btn.innerHTML=\'<i class="ri-delete-bin-line"></i> \'+L.delete_btn;
            if(res.success){
                toast(res.data.message||L.deleted_ok);
                if(ankaufMode){loadAnkaufList(1)}
                else{invoicesLoaded=false;loadInvoices(1)}
                updateBulkSelection();
            }else{toast(res.data&&res.data.message?res.data.message:L.error)}
        });
    });

    /* ===== WYSIWYG Form Builder with Palette ===== */
    function fbSyncPalette(key,active){
        var pi=document.querySelector(\'.ra-fb-pal-item[data-target="\'+key+\'"]\');
        if(pi){if(active){pi.classList.add("used")}else{pi.classList.remove("used")}}
    }
    function fbWireField(field){
        var key=field.getAttribute("data-key");
        // Settings panel toggle
        var settingsBtn=field.querySelector(".ra-fb-btn-settings");
        if(settingsBtn) settingsBtn.addEventListener("click",function(){
            var panel=field.querySelector(".ra-fb-settings");
            if(panel) panel.classList.toggle("open");
        });
        // Remove/hide field (X button) - goes back to palette
        var removeBuiltin=field.querySelector(".ra-fb-field-remove");
        if(removeBuiltin) removeBuiltin.addEventListener("click",function(){
            field.classList.add("ra-fb-hidden");
            fbSyncPalette(key,false);
        });
        // Remove custom field permanently
        var removeBtn=field.querySelector(".ra-fb-remove");
        if(removeBtn) removeBtn.addEventListener("click",function(){
            if(confirm("Feld wirklich entfernen?")) field.remove();
        });
        // Inline label editing (double-click)
        var lbl=field.querySelector("[data-label-for]");
        if(lbl) lbl.addEventListener("dblclick",function(){
            var labelInput=field.querySelector("input[data-field-label]");
            if(!labelInput) return;
            var inp=document.createElement("input");
            inp.type="text";inp.className="ra-fb-label-input";inp.value=labelInput.value;
            var origHTML=this.innerHTML;
            this.innerHTML="";this.appendChild(inp);inp.focus();inp.select();
            var self=this;
            function finish(){
                labelInput.value=inp.value;
                var req=field.querySelector("input[data-field-required]");
                self.innerHTML=inp.value||origHTML;
                if(req&&req.checked) self.innerHTML+="<span class=\\"ra-fb-req\\">*</span>";
            }
            inp.addEventListener("blur",finish);
            inp.addEventListener("keydown",function(ev){if(ev.key==="Enter"){ev.preventDefault();inp.blur()}});
        });
        // Type change (custom fields)
        var typeSel=field.querySelector("select[data-field-type]");
        if(typeSel) typeSel.addEventListener("change",function(){
            var optRow=field.querySelector(".ra-fb-options-row");
            if(optRow) optRow.style.display=this.value==="select"?"flex":"none";
        });
        // Label input sync
        var labelInput=field.querySelector("input[data-field-label]");
        if(labelInput){
            labelInput.addEventListener("input",function(){
                var labelEl=field.querySelector("[data-label-for]");
                if(labelEl){
                    var req=field.querySelector("input[data-field-required]");
                    labelEl.innerHTML=this.value||"<em style=\\"color:#94a3b8\\">Neues Feld</em>";
                    if(req&&req.checked) labelEl.innerHTML+="<span class=\\"ra-fb-req\\">*</span>";
                }
            });
        }
        // Required toggle sync
        var reqCb=field.querySelector("input[data-field-required]");
        if(reqCb) reqCb.addEventListener("change",function(){
            var labelEl=field.querySelector("[data-label-for]");
            if(!labelEl) return;
            var li=field.querySelector("input[data-field-label]");
            labelEl.innerHTML=li?li.value:"";
            if(!labelEl.innerHTML) labelEl.innerHTML="<em style=\\"color:#94a3b8\\">Neues Feld</em>";
            if(this.checked) labelEl.innerHTML+="<span class=\\"ra-fb-req\\">*</span>";
        });
        // Drag and drop reorder
        field.addEventListener("dragstart",function(e){
            field.classList.add("dragging");
            e.dataTransfer.effectAllowed="move";
            e.dataTransfer.setData("text/plain",key);
        });
        field.addEventListener("dragend",function(){
            field.classList.remove("dragging");
            document.querySelectorAll(".ra-fb-field.drag-over").forEach(function(f){f.classList.remove("drag-over")});
        });
        field.addEventListener("dragover",function(e){
            e.preventDefault();e.dataTransfer.dropEffect="move";
            var dragging=document.querySelector(".ra-fb-field.dragging");
            if(dragging&&dragging!==field&&!field.classList.contains("locked")){
                field.classList.add("drag-over");
            }
        });
        field.addEventListener("dragleave",function(){field.classList.remove("drag-over")});
        field.addEventListener("drop",function(e){
            e.preventDefault();field.classList.remove("drag-over");
            var dragging=document.querySelector(".ra-fb-field.dragging");
            if(!dragging||dragging===field) return;
            var parent=field.parentNode;
            var rect=field.getBoundingClientRect();
            var midY=rect.top+rect.height/2;
            if(e.clientY<midY){parent.insertBefore(dragging,field)}
            else{parent.insertBefore(dragging,field.nextSibling)}
        });
    }
    // Wire all existing fields
    document.querySelectorAll(".ra-fb-field:not(.locked)").forEach(fbWireField);

    // Palette click → show/hide field in form
    document.querySelectorAll(".ra-fb-pal-item").forEach(function(pi){
        pi.addEventListener("click",function(){
            var target=this.getAttribute("data-target");
            var field=document.querySelector(\'.ra-fb-field[data-key="\'+target+\'"]\');
            if(!field) return;
            if(this.classList.contains("used")){
                // Remove from form
                field.classList.add("ra-fb-hidden");
                this.classList.remove("used");
            }else{
                // Add to form
                field.classList.remove("ra-fb-hidden");
                this.classList.add("used");
                field.classList.add("ra-fb-flash");
                setTimeout(function(){field.classList.remove("ra-fb-flash")},600);
                field.scrollIntoView({behavior:"smooth",block:"center"});
            }
        });
    });

    // Add custom field
    var fbAddBtn=document.getElementById("ra-fb-add-custom");
    if(fbAddBtn){
        fbAddBtn.addEventListener("click",function(){
            var key="custom_"+Date.now();
            var container=document.getElementById("ra-fb-custom-fields");
            var field=document.createElement("div");
            field.className="ra-fb-field";
            field.setAttribute("data-key",key);
            field.setAttribute("data-builtin","0");
            field.setAttribute("draggable","true");
            field.innerHTML=\'<i class="ri-draggable ra-fb-drag"></i>\
                <div class="ra-fb-controls">\
                    <button type="button" class="ra-fb-ctrl-btn ra-fb-btn-settings" title="Einstellungen"><i class="ri-settings-3-line"></i></button>\
                    <button type="button" class="ra-fb-ctrl-btn danger ra-fb-remove" title="Entfernen"><i class="ri-delete-bin-line"></i></button>\
                </div>\
                <div class="ra-fb-field-body">\
                    <label data-label-for="\'+key+\'"><em style="color:#94a3b8">Neues Feld</em></label>\
                    <input type="text" placeholder="...">\
                </div>\
                <div class="ra-fb-settings open">\
                    <div class="ra-fb-settings-row"><label>Feldname:</label><input type="text" data-field-label="\'+key+\'" value="" class="ra-fb-label-input" style="flex:1" placeholder="z.B. Garantie, Farbe, Zustand..."></div>\
                    <div class="ra-fb-settings-row"><label>Feldtyp:</label>\
                        <select data-field-type="\'+key+\'">\
                            <option value="text">Textfeld</option>\
                            <option value="textarea">Textbereich</option>\
                            <option value="number">Zahl</option>\
                            <option value="select">Auswahl (Dropdown)</option>\
                            <option value="checkbox">Checkbox</option>\
                        </select>\
                    </div>\
                    <div class="ra-fb-settings-row ra-fb-options-row" style="display:none">\
                        <textarea data-field-options="\'+key+\'" rows="3" placeholder="Option 1&#10;Option 2&#10;Option 3" style="width:100%"></textarea>\
                    </div>\
                    <div class="ra-fb-settings-row"><label style="cursor:pointer"><input type="checkbox" data-field-required="\'+key+\'" style="accent-color:#667eea"> Pflichtfeld</label></div>\
                </div>\';
            container.appendChild(field);
            fbWireField(field);
            field.querySelector("input[data-field-label]").focus();
            field.scrollIntoView({behavior:"smooth",block:"center"});
        });
    }
    /* ===== Chip/Tag Editors ===== */
    document.querySelectorAll(".ra-chip-editor").forEach(function(editor){
        var taName=editor.getAttribute("data-chip-for");
        var ta=document.querySelector("textarea[name=\""+taName+"\"]");
        if(!ta)return;
        var wrap=editor.querySelector(".ra-chip-wrap");
        var inp=editor.querySelector(".ra-chip-input");
        var countEl=editor.parentNode.querySelector(".ra-chip-count");

        function syncTA(){
            var chips=wrap.querySelectorAll(".ra-chip");
            var vals=[];
            chips.forEach(function(c){vals.push(c.getAttribute("data-val"))});
            ta.value=vals.join("\\n");
            if(countEl)countEl.textContent=vals.length>0?vals.length+" Einträge":"";
        }

        function addChip(text){
            text=text.trim();
            if(!text)return;
            var exists=false;
            wrap.querySelectorAll(".ra-chip").forEach(function(c){if(c.getAttribute("data-val").toLowerCase()===text.toLowerCase())exists=true});
            if(exists)return;
            var chip=document.createElement("span");
            chip.className="ra-chip";
            chip.setAttribute("data-val",text);
            chip.innerHTML=text+" <button type=\\"button\\" class=\\"ra-chip-x\\" title=\\"Entfernen\\">&times;</button>";
            chip.querySelector(".ra-chip-x").addEventListener("click",function(){chip.remove();syncTA()});
            wrap.insertBefore(chip,inp);
            syncTA();
        }

        // Init from textarea
        var initVal=ta.value.trim();
        if(initVal){initVal.split("\\n").forEach(function(line){addChip(line)})}

        // Enter / comma to add
        inp.addEventListener("keydown",function(e){
            if(e.key==="Enter"||e.key===","){
                e.preventDefault();
                addChip(inp.value.replace(/,$/,""));
                inp.value="";
            }
            // Backspace on empty input removes last chip
            if(e.key==="Backspace"&&!inp.value){
                var chips=wrap.querySelectorAll(".ra-chip");
                if(chips.length){chips[chips.length-1].remove();syncTA()}
            }
        });

        // Also add on blur
        inp.addEventListener("blur",function(){
            if(inp.value.trim()){addChip(inp.value);inp.value=""}
        });

        // Click on editor focuses input
        editor.addEventListener("click",function(){inp.focus()});
    });

    /* ===== Settings Form ===== */
    var settingsFormEl = document.getElementById("ra-settings-form");
    if(settingsFormEl){
        settingsFormEl.addEventListener("submit",function(e){
            e.preventDefault();
            var fd=new FormData(this);
            // Collect field config from all builder fields (visible = enabled)
            var fc={};
            var order=0;
            document.querySelectorAll(".ra-fb-field:not(.locked)").forEach(function(field){
                order++;
                var key=field.getAttribute("data-key");
                if(!key) return;
                var isVisible=!field.classList.contains("ra-fb-hidden");
                var labelInp=field.querySelector("input[data-field-label]");
                var reqCb=field.querySelector("input[data-field-required]");
                var entry={enabled:isVisible, label:labelInp?labelInp.value:"", required:reqCb?reqCb.checked:false, order:order};
                if(field.getAttribute("data-builtin")==="0"){
                    var typeSel=field.querySelector("select[data-field-type]");
                    var optsTa=field.querySelector("textarea[data-field-options]");
                    entry.type=typeSel?typeSel.value:"text";
                    entry.options=optsTa?optsTa.value:"";
                }
                fc[key]=entry;
            });
            fd.append("repair_field_config",JSON.stringify(fc));
            fd.append("action","ppv_repair_save_settings");
            fd.append("nonce",NONCE);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success){
                    toast(L.settings_saved);
                    var btn=settingsFormEl.querySelector("button[type=submit]");
                    if(btn){
                        var orig=btn.innerHTML;
                        btn.innerHTML=\'<i class="ri-check-line"></i> \'+L.saved_btn;
                        btn.style.background="#10b981";
                        setTimeout(function(){btn.innerHTML=orig;btn.style.background=""},2000);
                    }
                }else{
                    toast(L.save_error);
                }
            })
            .catch(function(err){toast(L.connection_error)});
        });
    }

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
                toast(L.logo_uploaded);
            }else{
                toast(data.data&&data.data.message?data.data.message:L.upload_failed);
            }
        })
        .catch(function(){toast(L.upload_failed)});
    });

    /* ===== NEW INVOICE MODAL ===== */
    /* ===== NEW INVOICE MODAL (Modern) ===== */
    var ninvModal=document.getElementById("ra-new-invoice-modal");
    var ninvSearchTimer=null;
    var ninvLineIdx=1;
    var ninvRepairMode=false; // true when opened from repair card or status→done
    var ninvRepairSelect=null; // the status select element to restore on cancel

    // Open modal (standalone - from Rechnungen tab)
    document.getElementById("ra-new-invoice-btn").addEventListener("click",function(){
        clearNewInvoiceForm();
        document.getElementById("ra-ninv-payment-section").style.display="block";
        document.getElementById("ra-ninv-paid-date").value=new Date().toISOString().split("T")[0];
        ninvModal.classList.add("show");
    });

    // Open from repair card (finish repair or direct invoice button)
    function openNinvFromRepair(card,rid,selectEl){
        clearNewInvoiceForm();
        ninvRepairMode=!!selectEl;
        ninvRepairSelect=selectEl||null;
        // Pre-fill customer data
        document.getElementById("ra-ninv-name").value=card.dataset.name||"";
        document.getElementById("ra-ninv-email").value=card.dataset.email||"";
        document.getElementById("ra-ninv-phone").value=card.dataset.phone||"";
        var addr=card.dataset.address||"";
        document.getElementById("ra-ninv-address").value=addr;
        document.getElementById("ra-ninv-full-address").value=addr;
        ninvParseAddress();
        // Pre-fill first line with device + problem
        var device=((card.dataset.brand||"")+" "+(card.dataset.model||"")).trim();
        var desc=device;
        if(card.dataset.problem)desc+=(desc?" - ":"")+card.dataset.problem;
        if(desc){
            var firstDesc=document.querySelector("#ra-ninv-lines .nang-line-desc");
            if(firstDesc)firstDesc.value=desc;
        }
        // Store repair_id
        document.getElementById("ra-ninv-customer-id").dataset.repairId=rid;
        // Show repair-mode extras: skip button, payment section
        document.getElementById("ra-ninv-skip").style.display=ninvRepairMode?"":"none";
        document.getElementById("ninv-step1-actions").style.display=ninvRepairMode?"flex":"none";
        document.getElementById("ra-ninv-payment-section").style.display="block";
        document.getElementById("ra-ninv-paid-date").value=new Date().toISOString().split("T")[0];
        // Reward discount
        var discSec=document.getElementById("ra-ninv-discount-section");
        if(card.dataset.rewardApproved==="1" && PP_ENABLED && REWARD_VALUE>0){
            document.getElementById("ra-ninv-discount-desc").value=REWARD_NAME;
            document.getElementById("ra-ninv-discount-amount").value=REWARD_VALUE.toFixed(2);
            discSec.style.display="block";
        }
        // Update submit button text for finish mode
        if(ninvRepairMode){
            document.getElementById("ra-ninv-submit").innerHTML=\'<i class="ri-check-line"></i> \'+L.finish_inv;
        }
        ninvModal.classList.add("show");
    }

    function closeNinvModal(){
        // Restore select if cancelled from status→done
        if(ninvRepairSelect){
            ninvRepairSelect.value=ninvRepairSelect.getAttribute("data-prev")||"in_progress";
        }
        ninvModal.classList.remove("show");
        ninvRepairMode=false;
        ninvRepairSelect=null;
    }
    // Close
    document.getElementById("ra-ninv-close").addEventListener("click",closeNinvModal);
    document.getElementById("ra-ninv-cancel").addEventListener("click",closeNinvModal);
    document.getElementById("ra-ninv-cancel-step1").addEventListener("click",closeNinvModal);
    ninvModal.addEventListener("click",function(e){ if(e.target===ninvModal)closeNinvModal(); });

    // Step1 skip button reuses the same logic as step2 skip
    document.getElementById("ra-ninv-skip-step1").addEventListener("click",function(){
        document.getElementById("ra-ninv-skip").click();
    });

    // ESC key
    ninvModal.addEventListener("keydown",function(e){
        if(e.key==="Escape"){
            var res=document.getElementById("ra-ninv-customer-results");
            if(res.classList.contains("show")){res.classList.remove("show");e.stopPropagation();}
            else ninvModal.classList.remove("show");
        }
    });

    // Step navigation
    function ninvShowStep(step){
        var sec1=document.getElementById("ninv-sec-customer");
        var sec2=document.getElementById("ninv-sec-positions");
        var steps=ninvModal.querySelectorAll(".nang-step");
        if(step===1){
            sec1.style.display="";sec2.style.display="none";
            steps[0].classList.add("active");steps[0].classList.remove("completed");
            steps[0].querySelector(".nang-step-num").style.cssText="background:#2563eb;color:#fff";
            steps[1].classList.remove("active");
            steps[1].querySelector(".nang-step-num").style.cssText="";
        }else{
            sec1.style.display="none";sec2.style.display="";
            steps[0].classList.remove("active");steps[0].classList.add("completed");
            steps[1].classList.add("active");
            steps[1].querySelector(".nang-step-num").style.cssText="background:#2563eb;color:#fff";
            // Update customer chip
            var cname=document.getElementById("ra-ninv-name").value.trim();
            var cemail=document.getElementById("ra-ninv-email").value.trim();
            var ccompany=document.getElementById("ra-ninv-company").value.trim();
            var chip=document.getElementById("ninv-customer-chip");
            chip.innerHTML=\'<i class="ri-user-line"></i> <span class="nang-cc-name">\'+esc(cname)+\'</span>\'+(ccompany?\' <span style="color:#6b7280;font-size:12px">(\'+esc(ccompany)+\')</span>\':\'\')+
                (cemail?\' <span style="color:#94a3b8;margin-left:4px;font-size:12px">\'+esc(cemail)+\'</span>\':\'\')+
                \' <span style="color:#94a3b8;font-size:12px;margin-left:4px">\'+esc(document.getElementById("ra-ninv-full-address").value)+\'</span>\';
        }
    }
    document.getElementById("ninv-next-to-positions").addEventListener("click",function(){
        var name=document.getElementById("ra-ninv-name").value.trim();
        if(!name){toast(L.name_required);document.getElementById("ra-ninv-name").focus();return;}
        ninvParseAddress();
        ninvShowStep(2);
    });
    document.getElementById("ninv-back-to-customer").addEventListener("click",function(){ ninvShowStep(1); });

    function clearNewInvoiceForm(){
        ninvShowStep(1);
        ninvRepairMode=false;
        ninvRepairSelect=null;
        document.getElementById("ra-ninv-customer-id").value="";
        document.getElementById("ra-ninv-customer-id").dataset.repairId="";
        document.getElementById("ra-ninv-name").value="";
        document.getElementById("ra-ninv-company").value="";
        document.getElementById("ra-ninv-email").value="";
        document.getElementById("ra-ninv-phone").value="";
        document.getElementById("ra-ninv-address").value="";
        document.getElementById("ra-ninv-plz").value="";
        document.getElementById("ra-ninv-city").value="";
        document.getElementById("ra-ninv-full-address").value="";
        document.getElementById("ninv-address-hint").textContent="";
        document.getElementById("ra-ninv-taxid").value="";
        document.getElementById("ra-ninv-number").value="";
        document.getElementById("ra-ninv-notes").value="";
        document.getElementById("ra-ninv-warranty-date").value="";
        document.getElementById("ra-ninv-warranty-desc").value="";
        document.getElementById("ra-ninv-warranty-desc-wrap").style.display="none";
        document.getElementById("ra-ninv-differenz").checked=false;
        document.getElementById("ra-ninv-customer-search").value="";
        // Reset repair-mode extras
        document.getElementById("ra-ninv-skip").style.display="none";
        document.getElementById("ninv-step1-actions").style.display="none";
        document.getElementById("ra-ninv-payment-section").style.display="none";
        document.getElementById("ra-ninv-paid-toggle").checked=false;
        document.getElementById("ra-ninv-paid-fields").style.display="none";
        document.getElementById("ra-ninv-payment-method").value="";
        document.getElementById("ra-ninv-discount-section").style.display="none";
        document.getElementById("ra-ninv-discount-desc").value="";
        document.getElementById("ra-ninv-discount-amount").value="";
        document.getElementById("ra-ninv-submit").innerHTML=\'<i class="ri-check-line"></i> \'+L.create_inv_btn;
        ninvLineIdx=1;
        var lines=document.getElementById("ra-ninv-lines");
        lines.innerHTML=\'<div class="nang-line" data-idx="0"><input type="text" placeholder="z.B. Displaytausch iPhone 15" class="nang-line-desc"><input type="number" value="1" min="1" step="1" class="nang-line-qty"><div class="nang-line-price-wrap"><input type="number" placeholder="0,00" step="0.01" min="0" class="nang-line-price"><span class="nang-line-currency">&euro;</span></div><span class="nang-line-total">0,00 &euro;</span><button type="button" class="nang-line-del" title="\'+L["delete"]+\'"><i class="ri-delete-bin-line"></i></button></div>\';
        updateNinvTotals();
    }

    // Address parsing (same logic as Angebot)
    function ninvParseAddress(){
        var raw=document.getElementById("ra-ninv-full-address").value.trim();
        var street="",plz="",city="";
        if(raw){
            var m=raw.match(/^(.+?)\s*,\s*(\d{5})\s+(.+)$/);
            if(m){street=m[1];plz=m[2];city=m[3];}
            else{
                var m2=raw.match(/^(.+?)\s*,\s*(.+)$/);
                if(m2){street=m2[1];city=m2[2];}
                else{
                    var m3=raw.match(/^(\d{5})\s+(.+)$/);
                    if(m3){plz=m3[1];city=m3[2];}
                    else{street=raw;}
                }
            }
        }
        document.getElementById("ra-ninv-address").value=street;
        document.getElementById("ra-ninv-plz").value=plz;
        document.getElementById("ra-ninv-city").value=city;
        var hint=document.getElementById("ninv-address-hint");
        if(street||plz||city){
            var parts=[];
            if(street)parts.push(street);
            if(plz)parts.push(plz);
            if(city)parts.push(city);
            hint.textContent=parts.length>1?"\u2192 "+parts.join(" \u00b7 "):"";
        }else{hint.textContent="";}
    }
    document.getElementById("ra-ninv-full-address").addEventListener("blur",ninvParseAddress);

    // Customer search
    document.getElementById("ra-ninv-customer-search").addEventListener("input",function(){
        var q=this.value.trim();
        if(ninvSearchTimer)clearTimeout(ninvSearchTimer);
        var res=document.getElementById("ra-ninv-customer-results");
        if(q.length<2){res.classList.remove("show");return;}
        ninvSearchTimer=setTimeout(function(){
            var fd=new FormData();
            fd.append("action","ppv_repair_customer_search");
            fd.append("nonce",NONCE);
            fd.append("search",q);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(!data.success||!data.data.customers||data.data.customers.length===0){
                    res.classList.remove("show");return;
                }
                res.innerHTML="";
                data.data.customers.forEach(function(c){
                    var div=document.createElement("div");
                    div.className="nang-search-result";
                    var isRepairSource=c.source==="repair"||parseInt(c.id)<0;
                    var badge=isRepairSource
                        ?"<span style=\'display:inline-block;font-size:9px;padding:2px 6px;border-radius:4px;background:#fef3c7;color:#92400e;margin-left:6px;\'>"+L.cust_form_badge+"</span>"
                        :"<span style=\'display:inline-block;font-size:9px;padding:2px 6px;border-radius:4px;background:#dbeafe;color:#1e40af;margin-left:6px;\'>"+L.cust_saved_badge+"</span>";
                    div.innerHTML="<strong>"+esc(c.name)+"</strong>"+badge+(c.company_name?" <span class=\'nang-sr-company\'>("+esc(c.company_name)+")</span>":"")+"<div class=\'nang-sr-meta\'>"+(c.email||"")+(c.phone?" &middot; "+c.phone:"")+"</div>";
                    div.addEventListener("click",function(){
                        document.getElementById("ra-ninv-customer-id").value=isRepairSource?"":c.id;
                        document.getElementById("ra-ninv-name").value=c.name||"";
                        document.getElementById("ra-ninv-company").value=c.company_name||"";
                        document.getElementById("ra-ninv-email").value=c.email||"";
                        document.getElementById("ra-ninv-phone").value=c.phone||"";
                        document.getElementById("ra-ninv-address").value=c.address||"";
                        document.getElementById("ra-ninv-plz").value=c.plz||"";
                        document.getElementById("ra-ninv-city").value=c.city||"";
                        document.getElementById("ra-ninv-taxid").value=c.tax_id||"";
                        // Build full address
                        var full=(c.address||"");
                        if(c.plz||c.city) full+=(full?", ":"")+(c.plz||"")+(c.plz&&c.city?" ":"")+(c.city||"");
                        document.getElementById("ra-ninv-full-address").value=full;
                        ninvParseAddress();
                        document.getElementById("ra-ninv-customer-search").value="";
                        res.classList.remove("show");
                    });
                    res.appendChild(div);
                });
                res.classList.add("show");
            });
        },300);
    });

    // Add line
    document.getElementById("ra-ninv-add-line").addEventListener("click",function(){
        var line=document.createElement("div");
        line.className="nang-line";
        line.dataset.idx=ninvLineIdx++;
        line.innerHTML=\'<input type="text" placeholder="z.B. Displaytausch iPhone 15" class="nang-line-desc"><input type="number" value="1" min="1" step="1" class="nang-line-qty"><div class="nang-line-price-wrap"><input type="number" placeholder="0,00" step="0.01" min="0" class="nang-line-price"><span class="nang-line-currency">&euro;</span></div><span class="nang-line-total">0,00 &euro;</span><button type="button" class="nang-line-del" title="\'+L["delete"]+\'"><i class="ri-delete-bin-line"></i></button>\';
        document.getElementById("ra-ninv-lines").appendChild(line);
        line.querySelector(".nang-line-desc").focus();
    });

    // Remove line + update totals
    document.getElementById("ra-ninv-lines").addEventListener("click",function(e){
        var del=e.target.closest(".nang-line-del");
        if(!del)return;
        var lines=this.querySelectorAll(".nang-line");
        if(lines.length>1){
            var line=del.closest(".nang-line");
            line.style.opacity="0";line.style.transform="translateX(20px)";
            setTimeout(function(){line.remove();updateNinvTotals();},150);
        }
        updateNinvTotals();
    });

    // Input on lines -> recalc
    document.getElementById("ra-ninv-lines").addEventListener("input",function(e){
        var line=e.target.closest(".nang-line");
        if(line){
            var qty=parseFloat(line.querySelector(".nang-line-qty").value)||1;
            var price=parseFloat(line.querySelector(".nang-line-price").value)||0;
            var lineTotal=qty*price;
            line.querySelector(".nang-line-total").textContent=fmtEur(lineTotal);
        }
        updateNinvTotals();
    });

    function updateNinvTotals(){
        var brutto=0;
        document.querySelectorAll("#ra-ninv-lines .nang-line").forEach(function(line){
            var qty=parseFloat(line.querySelector(".nang-line-qty").value)||1;
            var price=parseFloat(line.querySelector(".nang-line-price").value)||0;
            brutto+=qty*price;
        });
        brutto=Math.round(brutto*100)/100;
        // Subtract discount if visible
        var discSec=document.getElementById("ra-ninv-discount-section");
        var discountVal=0;
        if(discSec&&discSec.style.display!=="none"){
            discountVal=parseFloat(document.getElementById("ra-ninv-discount-amount").value)||0;
        }
        var afterDiscount=Math.max(0,brutto-discountVal);
        var net,vat;
        if(VAT_ENABLED){
            net=Math.round(afterDiscount/(1+VAT_RATE/100)*100)/100;
            vat=Math.round((afterDiscount-net)*100)/100;
        }else{
            net=afterDiscount;vat=0;
        }
        document.getElementById("ra-ninv-net").textContent=fmtEur(net);
        document.getElementById("ra-ninv-vat").textContent=fmtEur(vat);
        document.getElementById("ra-ninv-total").textContent=fmtEur(afterDiscount);
        if(!VAT_ENABLED)document.getElementById("ra-ninv-vat-row").style.display="none";
    }

    // Paid toggle
    document.getElementById("ra-ninv-paid-toggle").addEventListener("change",function(){
        document.getElementById("ra-ninv-paid-fields").style.display=this.checked?"block":"none";
    });

    // Discount recalc + remove
    document.getElementById("ra-ninv-discount-amount").addEventListener("input",function(){updateNinvTotals()});
    document.getElementById("ra-ninv-discount-remove").addEventListener("click",function(){
        document.getElementById("ra-ninv-discount-section").style.display="none";
        document.getElementById("ra-ninv-discount-desc").value="";
        document.getElementById("ra-ninv-discount-amount").value="";
        updateNinvTotals();
    });

    // Skip button (only in repair→done mode): mark as done without invoice
    document.getElementById("ra-ninv-skip").addEventListener("click",function(){
        var repairId=document.getElementById("ra-ninv-customer-id").dataset.repairId||"";
        if(!repairId){closeNinvModal();return;}
        var btn=this;
        btn.disabled=true;btn.style.opacity="0.7";
        var fd=new FormData();
        fd.append("action","ppv_repair_update_status");
        fd.append("nonce",NONCE);
        fd.append("repair_id",repairId);
        fd.append("status","done");
        fd.append("skip_invoice","1");
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            btn.disabled=false;btn.style.opacity="";
            if(data.success){
                toast(L.marked_done);
                if(ninvRepairSelect){
                    updateBadge(ninvRepairSelect.closest(".ra-repair-card"),"done");
                    ninvRepairSelect.value="done";
                    ninvRepairSelect.setAttribute("data-prev","done");
                }
                ninvRepairSelect=null;
                ninvModal.classList.remove("show");
            }else{
                toast(data.data&&data.data.message?data.data.message:L.error);
            }
        })
        .catch(function(){btn.disabled=false;btn.style.opacity="";toast(L.connection_error)});
    });

    // Submit new invoice (dual mode: standalone or repair→done)
    document.getElementById("ra-ninv-submit").addEventListener("click",function(){
        var name=document.getElementById("ra-ninv-name").value.trim();
        if(!name){toast(L.name_required);ninvShowStep(1);return;}
        ninvParseAddress();
        // Build line items: qty * price = amount per line
        var items=[];
        document.querySelectorAll("#ra-ninv-lines .nang-line").forEach(function(line){
            var d=line.querySelector(".nang-line-desc").value.trim();
            var qty=parseFloat(line.querySelector(".nang-line-qty").value)||1;
            var price=parseFloat(line.querySelector(".nang-line-price").value)||0;
            var amount=Math.round(qty*price*100)/100;
            if(d||amount>0){
                var desc=d;
                if(qty>1)desc+=" (x"+qty+")";
                items.push({description:desc,amount:amount});
            }
        });
        var subtotal=0;
        items.forEach(function(i){subtotal+=i.amount});

        var repairId=document.getElementById("ra-ninv-customer-id").dataset.repairId||"";

        // Collect discount
        var discSec=document.getElementById("ra-ninv-discount-section");
        var manualDiscDesc="",manualDiscVal=0;
        if(discSec&&discSec.style.display!=="none"){
            manualDiscDesc=document.getElementById("ra-ninv-discount-desc").value.trim();
            manualDiscVal=parseFloat(document.getElementById("ra-ninv-discount-amount").value)||0;
        }

        var fd=new FormData();
        fd.append("nonce",NONCE);
        fd.append("is_differenzbesteuerung",document.getElementById("ra-ninv-differenz").checked?"1":"0");

        if(ninvRepairMode && repairId){
            // Finish repair mode: use ppv_repair_update_status (creates invoice + marks done)
            fd.append("action","ppv_repair_update_status");
            fd.append("repair_id",repairId);
            fd.append("status","done");
            fd.append("final_cost",subtotal);
            fd.append("line_items",JSON.stringify(items));
            if(manualDiscVal>0){
                fd.append("manual_discount_desc",manualDiscDesc);
                fd.append("manual_discount_value",manualDiscVal);
            }
            var wDate1=document.getElementById("ra-ninv-warranty-date").value;
            if(wDate1){fd.append("warranty_date",wDate1);fd.append("warranty_description",document.getElementById("ra-ninv-warranty-desc").value)}
            if(document.getElementById("ra-ninv-paid-toggle").checked){
                fd.append("mark_paid","1");
                var pm=document.getElementById("ra-ninv-payment-method").value;
                var pd=document.getElementById("ra-ninv-paid-date").value;
                if(pm)fd.append("payment_method",pm);
                if(pd)fd.append("paid_at",pd);
            }
        }else{
            // Standalone invoice mode: use ppv_repair_invoice_create
            fd.append("action","ppv_repair_invoice_create");
            fd.append("customer_id",document.getElementById("ra-ninv-customer-id").value);
            fd.append("customer_name",name);
            fd.append("customer_email",document.getElementById("ra-ninv-email").value);
            fd.append("customer_phone",document.getElementById("ra-ninv-phone").value);
            fd.append("customer_company",document.getElementById("ra-ninv-company").value);
            fd.append("customer_tax_id",document.getElementById("ra-ninv-taxid").value);
            fd.append("customer_address",document.getElementById("ra-ninv-address").value);
            fd.append("customer_plz",document.getElementById("ra-ninv-plz").value);
            fd.append("customer_city",document.getElementById("ra-ninv-city").value);
            fd.append("save_customer","1");
            fd.append("line_items",JSON.stringify(items));
            fd.append("subtotal",subtotal);
            fd.append("notes",document.getElementById("ra-ninv-notes").value);
            var wDate=document.getElementById("ra-ninv-warranty-date").value;
            if(wDate){fd.append("warranty_date",wDate);fd.append("warranty_description",document.getElementById("ra-ninv-warranty-desc").value)}
            if(manualDiscVal>0){
                fd.append("manual_discount_desc",manualDiscDesc);
                fd.append("manual_discount_value",manualDiscVal);
            }
            var customInvNum=document.getElementById("ra-ninv-number").value.trim();
            if(customInvNum)fd.append("invoice_number",customInvNum);
            if(repairId)fd.append("repair_id",repairId);
            // Payment status
            if(document.getElementById("ra-ninv-paid-toggle").checked){
                fd.append("mark_paid","1");
                var pm2=document.getElementById("ra-ninv-payment-method").value;
                var pd2=document.getElementById("ra-ninv-paid-date").value;
                if(pm2)fd.append("payment_method",pm2);
                if(pd2)fd.append("paid_at",pd2);
            }
        }

        var btn=document.getElementById("ra-ninv-submit");
        btn.disabled=true;btn.style.opacity="0.7";
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            btn.disabled=false;btn.style.opacity="";
            if(data.success){
                if(ninvRepairMode && repairId){
                    toast(L.repair_completed);
                    if(ninvRepairSelect){
                        updateBadge(ninvRepairSelect.closest(".ra-repair-card"),"done");
                        ninvRepairSelect.setAttribute("data-prev","done");
                    }
                }else{
                    toast(L.inv_created.replace("%s",data.data.invoice_number));
                }
                ninvRepairSelect=null;
                ninvModal.classList.remove("show");
                invoicesLoaded=false;
                loadInvoices(1);
            }else{
                toast(data.data&&data.data.message?data.data.message:L.error);
            }
        })
        .catch(function(){btn.disabled=false;btn.style.opacity="";toast(L.connection_error)});
    });

    /* ===== NEW ANGEBOT MODAL (Modern) ===== */
    var nangModal=document.getElementById("ra-new-angebot-modal");
    var nangSearchTimer=null;
    var nangLineIdx=1;

    // Open modal
    document.getElementById("ra-new-angebot-btn").addEventListener("click",function(){
        clearNewAngebotForm();
        nangModal.classList.add("show");
    });
    // Close
    document.getElementById("ra-nang-close").addEventListener("click",function(){ nangModal.classList.remove("show"); });
    document.getElementById("ra-nang-cancel").addEventListener("click",function(){ nangModal.classList.remove("show"); });
    nangModal.addEventListener("click",function(e){ if(e.target===nangModal)nangModal.classList.remove("show"); });

    // ESC key closes search results or modal
    nangModal.addEventListener("keydown",function(e){
        if(e.key==="Escape"){
            var res=document.getElementById("ra-nang-customer-results");
            if(res.classList.contains("show")){res.classList.remove("show");e.stopPropagation();}
            else nangModal.classList.remove("show");
        }
    });

    // Step navigation
    function nangShowStep(step){
        var sec1=document.getElementById("nang-sec-customer");
        var sec2=document.getElementById("nang-sec-positions");
        var steps=nangModal.querySelectorAll(".nang-step");
        if(step===1){
            sec1.style.display="";sec2.style.display="none";
            steps[0].classList.add("active");steps[0].classList.remove("completed");
            steps[1].classList.remove("active");
        }else{
            sec1.style.display="none";sec2.style.display="";
            steps[0].classList.remove("active");steps[0].classList.add("completed");
            steps[1].classList.add("active");
            // Update customer chip
            var cname=document.getElementById("ra-nang-name").value.trim();
            var cemail=document.getElementById("ra-nang-email").value.trim();
            var chip=document.getElementById("nang-customer-chip");
            chip.innerHTML=\'<i class="ri-user-line"></i> <span class="nang-cc-name">\'+esc(cname)+\'</span>\'+(cemail?\' <span style="color:#94a3b8;margin-left:4px">\'+esc(cemail)+\'</span>\':\'\')+\' <span style="color:#94a3b8;font-size:12px;margin-left:4px">\'+esc(document.getElementById("ra-nang-full-address").value)+\'</span>\';
        }
    }
    document.getElementById("nang-next-to-positions").addEventListener("click",function(){
        var name=document.getElementById("ra-nang-name").value.trim();
        if(!name){toast(L.name_required);document.getElementById("ra-nang-name").focus();return;}
        // Parse address before switching
        nangParseAddress();
        nangShowStep(2);
    });
    document.getElementById("nang-back-to-customer").addEventListener("click",function(){ nangShowStep(1); });

    // Angebot from repair card (event delegation)
    document.addEventListener("click",function(e){
        var btn=e.target.closest(".ra-btn-angebot");
        if(!btn)return;
        var card=btn.closest(".ra-repair-card");
        if(!card)return;
        clearNewAngebotForm();
        document.getElementById("ra-nang-name").value=card.dataset.name||"";
        document.getElementById("ra-nang-email").value=card.dataset.email||"";
        document.getElementById("ra-nang-phone").value=card.dataset.phone||"";
        // Combine address fields into full address
        var addr=card.dataset.address||"";
        document.getElementById("ra-nang-address").value=addr;
        document.getElementById("ra-nang-full-address").value=addr;
        // Pre-fill first line with device + problem
        var device=((card.dataset.brand||"")+" "+(card.dataset.model||"")).trim();
        var desc=device;
        if(card.dataset.problem)desc+=(desc?" - ":"")+card.dataset.problem;
        if(desc){
            var firstDesc=document.querySelector("#ra-nang-lines .nang-line-desc");
            if(firstDesc)firstDesc.value=desc;
        }
        nangModal.classList.add("show");
    });

    function clearNewAngebotForm(){
        nangShowStep(1);
        document.getElementById("ra-nang-customer-id").value="";
        document.getElementById("ra-nang-name").value="";
        document.getElementById("ra-nang-company").value="";
        document.getElementById("ra-nang-email").value="";
        document.getElementById("ra-nang-phone").value="";
        document.getElementById("ra-nang-address").value="";
        document.getElementById("ra-nang-plz").value="";
        document.getElementById("ra-nang-city").value="";
        document.getElementById("ra-nang-full-address").value="";
        document.getElementById("nang-address-hint").textContent="";
        document.getElementById("ra-nang-notes").value="";
        document.getElementById("ra-nang-customer-search").value="";
        // Default valid_until: 30 days from now
        var d=new Date();d.setDate(d.getDate()+30);
        document.getElementById("ra-nang-valid-until").value=d.toISOString().split("T")[0];
        nangLineIdx=1;
        var lines=document.getElementById("ra-nang-lines");
        lines.innerHTML=\'<div class="nang-line" data-idx="0"><input type="text" placeholder="z.B. Displaytausch iPhone 15" class="nang-line-desc"><input type="number" value="1" min="1" step="1" class="nang-line-qty"><div class="nang-line-price-wrap"><input type="number" placeholder="0,00" step="0.01" min="0" class="nang-line-price"><span class="nang-line-currency">&euro;</span></div><span class="nang-line-total">0,00 &euro;</span><button type="button" class="nang-line-del" title="\'+L["delete"]+\'"><i class="ri-delete-bin-line"></i></button></div>\';
        updateNangTotals();
    }

    // --- Address parsing ---
    // "Musterstraße 1, 12345 Berlin" → street:"Musterstraße 1", plz:"12345", city:"Berlin"
    function nangParseAddress(){
        var raw=document.getElementById("ra-nang-full-address").value.trim();
        var street="",plz="",city="";
        if(raw){
            // Try pattern: "Street Nr, PLZ City"
            var m=raw.match(/^(.+?)\s*,\s*(\d{5})\s+(.+)$/);
            if(m){street=m[1];plz=m[2];city=m[3];}
            else{
                // Try "Street Nr, City" (no PLZ)
                var m2=raw.match(/^(.+?)\s*,\s*(.+)$/);
                if(m2){street=m2[1];city=m2[2];}
                else{
                    // Try "PLZ City" only
                    var m3=raw.match(/^(\d{5})\s+(.+)$/);
                    if(m3){plz=m3[1];city=m3[2];}
                    else{street=raw;} // fallback: everything is street
                }
            }
        }
        document.getElementById("ra-nang-address").value=street;
        document.getElementById("ra-nang-plz").value=plz;
        document.getElementById("ra-nang-city").value=city;
        // Show hint
        var hint=document.getElementById("nang-address-hint");
        if(street||plz||city){
            var parts=[];
            if(street)parts.push(street);
            if(plz)parts.push(plz);
            if(city)parts.push(city);
            hint.textContent=parts.length>1?"→ "+parts.join(" · "):"";
        }else{hint.textContent="";}
    }
    document.getElementById("ra-nang-full-address").addEventListener("blur",nangParseAddress);

    // Customer search for Angebot
    document.getElementById("ra-nang-customer-search").addEventListener("input",function(){
        var q=this.value.trim();
        if(nangSearchTimer)clearTimeout(nangSearchTimer);
        var res=document.getElementById("ra-nang-customer-results");
        if(q.length<2){res.classList.remove("show");return;}
        nangSearchTimer=setTimeout(function(){
            var fd=new FormData();
            fd.append("action","ppv_repair_customer_search");
            fd.append("nonce",NONCE);
            fd.append("search",q);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(!data.success||!data.data.customers||data.data.customers.length===0){
                    res.classList.remove("show");return;
                }
                res.innerHTML="";
                data.data.customers.forEach(function(c){
                    var div=document.createElement("div");
                    div.className="nang-search-result";
                    div.innerHTML="<strong>"+esc(c.name)+"</strong>"+(c.company_name?" <span class=\'nang-sr-company\'>("+esc(c.company_name)+")</span>":"")+"<div class=\'nang-sr-meta\'>"+(c.email||"")+(c.phone?" &middot; "+c.phone:"")+"</div>";
                    div.addEventListener("click",function(){
                        document.getElementById("ra-nang-customer-id").value=c.id||"";
                        document.getElementById("ra-nang-name").value=c.name||"";
                        document.getElementById("ra-nang-company").value=c.company_name||"";
                        document.getElementById("ra-nang-email").value=c.email||"";
                        document.getElementById("ra-nang-phone").value=c.phone||"";
                        document.getElementById("ra-nang-address").value=c.address||"";
                        document.getElementById("ra-nang-plz").value=c.plz||"";
                        document.getElementById("ra-nang-city").value=c.city||"";
                        // Build full address for display
                        var full=(c.address||"");
                        if(c.plz||c.city) full+=(full?", ":"")+(c.plz||"")+(c.plz&&c.city?" ":"")+(c.city||"");
                        document.getElementById("ra-nang-full-address").value=full;
                        nangParseAddress();
                        document.getElementById("ra-nang-customer-search").value="";
                        res.classList.remove("show");
                    });
                    res.appendChild(div);
                });
                res.classList.add("show");
            });
        },300);
    });

    // Add line for Angebot
    document.getElementById("ra-nang-add-line").addEventListener("click",function(){
        var line=document.createElement("div");
        line.className="nang-line";
        line.dataset.idx=nangLineIdx++;
        line.innerHTML=\'<input type="text" placeholder="z.B. Displaytausch iPhone 15" class="nang-line-desc"><input type="number" value="1" min="1" step="1" class="nang-line-qty"><div class="nang-line-price-wrap"><input type="number" placeholder="0,00" step="0.01" min="0" class="nang-line-price"><span class="nang-line-currency">&euro;</span></div><span class="nang-line-total">0,00 &euro;</span><button type="button" class="nang-line-del" title="\'+L["delete"]+\'"><i class="ri-delete-bin-line"></i></button>\';
        document.getElementById("ra-nang-lines").appendChild(line);
        line.querySelector(".nang-line-desc").focus();
    });

    // Remove line + update totals
    document.getElementById("ra-nang-lines").addEventListener("click",function(e){
        var del=e.target.closest(".nang-line-del");
        if(!del)return;
        var lines=this.querySelectorAll(".nang-line");
        if(lines.length>1){
            var line=del.closest(".nang-line");
            line.style.opacity="0";line.style.transform="translateX(20px)";
            setTimeout(function(){line.remove();updateNangTotals();},150);
        }
        updateNangTotals();
    });

    // Input on lines -> recalc
    document.getElementById("ra-nang-lines").addEventListener("input",function(e){
        // Update individual line total
        var line=e.target.closest(".nang-line");
        if(line){
            var qty=parseFloat(line.querySelector(".nang-line-qty").value)||1;
            var price=parseFloat(line.querySelector(".nang-line-price").value)||0;
            var lineTotal=qty*price;
            line.querySelector(".nang-line-total").textContent=fmtEur(lineTotal);
        }
        updateNangTotals();
    });

    function updateNangTotals(){
        var brutto=0;
        document.querySelectorAll("#ra-nang-lines .nang-line").forEach(function(line){
            var qty=parseFloat(line.querySelector(".nang-line-qty").value)||1;
            var price=parseFloat(line.querySelector(".nang-line-price").value)||0;
            brutto+=qty*price;
        });
        brutto=Math.round(brutto*100)/100;
        var net,vat;
        if(VAT_ENABLED){
            net=Math.round(brutto/(1+VAT_RATE/100)*100)/100;
            vat=Math.round((brutto-net)*100)/100;
        }else{
            net=brutto;vat=0;
        }
        document.getElementById("ra-nang-net").textContent=fmtEur(net);
        document.getElementById("ra-nang-vat").textContent=fmtEur(vat);
        document.getElementById("ra-nang-total").textContent=fmtEur(brutto);
        if(!VAT_ENABLED)document.getElementById("ra-nang-vat-row").style.display="none";
    }

    // Submit new Angebot
    document.getElementById("ra-nang-submit").addEventListener("click",function(){
        var name=document.getElementById("ra-nang-name").value.trim();
        if(!name){toast(L.name_required);nangShowStep(1);return;}
        // Parse address
        nangParseAddress();
        // Build line items: qty * price = amount per line
        var items=[];
        document.querySelectorAll("#ra-nang-lines .nang-line").forEach(function(line){
            var d=line.querySelector(".nang-line-desc").value.trim();
            var qty=parseFloat(line.querySelector(".nang-line-qty").value)||1;
            var price=parseFloat(line.querySelector(".nang-line-price").value)||0;
            var amount=Math.round(qty*price*100)/100;
            if(d||amount>0){
                var desc=d;
                if(qty>1)desc+=" (x"+qty+")";
                items.push({description:desc,amount:amount});
            }
        });
        var subtotal=0;
        items.forEach(function(i){subtotal+=i.amount});

        var fd=new FormData();
        fd.append("action","ppv_repair_angebot_create");
        fd.append("nonce",NONCE);
        fd.append("customer_id",document.getElementById("ra-nang-customer-id").value);
        fd.append("customer_name",name);
        fd.append("customer_email",document.getElementById("ra-nang-email").value);
        fd.append("customer_phone",document.getElementById("ra-nang-phone").value);
        fd.append("customer_company",document.getElementById("ra-nang-company").value);
        fd.append("customer_address",document.getElementById("ra-nang-address").value);
        fd.append("customer_plz",document.getElementById("ra-nang-plz").value);
        fd.append("customer_city",document.getElementById("ra-nang-city").value);
        fd.append("line_items",JSON.stringify(items));
        fd.append("subtotal",subtotal);
        fd.append("valid_until",document.getElementById("ra-nang-valid-until").value);
        fd.append("notes",document.getElementById("ra-nang-notes").value);

        var btn=document.getElementById("ra-nang-submit");
        btn.disabled=true;btn.style.opacity="0.7";
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            btn.disabled=false;btn.style.opacity="";
            if(data.success){
                toast(L.quote_created.replace("%s",data.data.angebot_number));
                nangModal.classList.remove("show");
                invoicesLoaded=false;
                loadInvoices(1);
            }else{
                toast(data.data&&data.data.message?data.data.message:L.error);
            }
        })
        .catch(function(){btn.disabled=false;btn.style.opacity="";toast(L.connection_error)});
    });

    /* ===== EDIT INVOICE MODAL ===== */
    var editInvModal=document.getElementById("ra-edit-invoice-modal");

    var einvLineIdx=1;

    function recalcEditInvoiceModal(){
        var brutto=0;
        document.querySelectorAll("#ra-einv-lines .nang-line").forEach(function(line){
            var qty=parseFloat(line.querySelector(".nang-line-qty").value)||1;
            var price=parseFloat(line.querySelector(".nang-line-price").value)||0;
            var t=Math.round(qty*price*100)/100;
            line.querySelector(".nang-line-total").textContent=fmtEur(t);
            brutto+=t;
        });
        var net,vat;
        if(VAT_ENABLED){
            net=Math.round(brutto/(1+VAT_RATE/100)*100)/100;
            vat=Math.round((brutto-net)*100)/100;
        }else{net=brutto;vat=0}
        document.getElementById("ra-einv-net").textContent=fmtEur(net);
        document.getElementById("ra-einv-vat").textContent=fmtEur(vat);
        document.getElementById("ra-einv-total").textContent=fmtEur(brutto);
    }

    // Step navigation
    document.getElementById("einv-next-to-positions").addEventListener("click",function(){
        var name=document.getElementById("ra-einv-name").value.trim();
        if(!name){toast(L.cust_name_req);return}
        document.getElementById("einv-sec-customer").style.display="none";
        document.getElementById("einv-sec-positions").style.display="block";
        var chip=document.getElementById("einv-customer-chip");
        chip.innerHTML=\'<i class="ri-user-line"></i> <strong>\'+esc(name)+\'</strong>\';
        var email=document.getElementById("ra-einv-email").value;
        if(email)chip.innerHTML+=\' &bull; \'+esc(email);
        editInvModal.querySelectorAll(".nang-step").forEach(function(s,i){
            s.classList.toggle("active",i===1);
            var num=s.querySelector(".nang-step-num");
            if(num){num.style.background=i<=1?"#2563eb":"";num.style.color=i<=1?"#fff":""}
        });
    });
    document.getElementById("einv-back-to-customer").addEventListener("click",function(){
        document.getElementById("einv-sec-positions").style.display="none";
        document.getElementById("einv-sec-customer").style.display="block";
        editInvModal.querySelectorAll(".nang-step").forEach(function(s,i){
            s.classList.toggle("active",i===0);
            var num=s.querySelector(".nang-step-num");
            if(num){num.style.background=i===0?"#2563eb":"";num.style.color=i===0?"#fff":""}
        });
    });

    // Close
    document.getElementById("ra-einv-close").addEventListener("click",function(){editInvModal.classList.remove("show")});
    document.getElementById("ra-einv-cancel").addEventListener("click",function(){editInvModal.classList.remove("show")});
    editInvModal.addEventListener("click",function(e){if(e.target===editInvModal)editInvModal.classList.remove("show")});

    // Paid toggle
    document.getElementById("ra-einv-paid-toggle").addEventListener("change",function(){
        document.getElementById("ra-einv-paid-fields").style.display=this.checked?"block":"none";
    });

    // Add line
    document.getElementById("ra-einv-add-line").addEventListener("click",function(){
        var idx=einvLineIdx++;
        var line=document.createElement("div");
        line.className="nang-line";
        line.setAttribute("data-idx",idx);
        line.innerHTML=\'<input type="text" placeholder="\'+L.service+\'" class="nang-line-desc"><input type="number" value="1" min="1" step="1" class="nang-line-qty"><div class="nang-line-price-wrap"><input type="number" placeholder="0,00" step="0.01" min="0" class="nang-line-price"><span class="nang-line-currency">&euro;</span></div><span class="nang-line-total">0,00 &euro;</span><button type="button" class="nang-line-del" title="\'+L["delete"]+\'"><i class="ri-delete-bin-line"></i></button>\';
        document.getElementById("ra-einv-lines").appendChild(line);
    });

    // Remove line + recalc
    document.getElementById("ra-einv-lines").addEventListener("click",function(e){
        var del=e.target.closest(".nang-line-del");
        if(del){
            var lines=this.querySelectorAll(".nang-line");
            if(lines.length>1)del.closest(".nang-line").remove();
            recalcEditInvoiceModal();
        }
    });
    document.getElementById("ra-einv-lines").addEventListener("input",function(){recalcEditInvoiceModal()});

    // Submit edit
    document.getElementById("ra-einv-submit").addEventListener("click",function(){
        var name=document.getElementById("ra-einv-name").value.trim();
        if(!name){toast(L.cust_name_req);return}

        // Collect line items
        var items=[];var subtotal=0;
        document.querySelectorAll("#ra-einv-lines .nang-line").forEach(function(line){
            var desc=line.querySelector(".nang-line-desc").value.trim();
            var qty=parseFloat(line.querySelector(".nang-line-qty").value)||1;
            var price=parseFloat(line.querySelector(".nang-line-price").value)||0;
            var amt=Math.round(qty*price*100)/100;
            if(desc||amt>0){items.push({description:desc,amount:amt});subtotal+=amt}
        });

        var fd=new FormData();
        fd.append("action","ppv_repair_invoice_update");
        fd.append("nonce",NONCE);
        fd.append("invoice_id",document.getElementById("ra-einv-id").value);
        fd.append("invoice_number",document.getElementById("ra-einv-number-input").value.trim());
        fd.append("customer_name",name);
        fd.append("customer_company",document.getElementById("ra-einv-company").value);
        fd.append("customer_email",document.getElementById("ra-einv-email").value);
        fd.append("customer_phone",document.getElementById("ra-einv-phone").value);
        fd.append("customer_address",document.getElementById("ra-einv-address").value);
        fd.append("customer_plz",document.getElementById("ra-einv-plz").value);
        fd.append("customer_city",document.getElementById("ra-einv-city").value);
        fd.append("customer_tax_id",document.getElementById("ra-einv-taxid").value);
        fd.append("notes",document.getElementById("ra-einv-notes").value);
        fd.append("warranty_date",document.getElementById("ra-einv-warranty-date").value);
        fd.append("warranty_description",document.getElementById("ra-einv-warranty-desc").value);
        fd.append("is_differenzbesteuerung",document.getElementById("ra-einv-differenz").checked?"1":"0");
        fd.append("line_items",JSON.stringify(items));
        fd.append("subtotal",subtotal);
        // Payment status
        if(document.getElementById("ra-einv-paid-toggle").checked){
            fd.append("status","paid");
            var pm=document.getElementById("ra-einv-payment-method").value;
            var pd=document.getElementById("ra-einv-paid-date").value;
            if(pm)fd.append("payment_method",pm);
            if(pd)fd.append("paid_at",pd);
        }

        var btn=this;btn.disabled=true;btn.style.opacity="0.7";
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            btn.disabled=false;btn.style.opacity="";
            if(data.success){
                toast(L.inv_updated);
                editInvModal.classList.remove("show");
                invoicesLoaded=false;
                loadInvoices(1);
            }else{
                toast(data.data&&data.data.message?data.data.message:L.error);
            }
        })
        .catch(function(){btn.disabled=false;btn.style.opacity="";toast(L.connection_error)});
    });

    /* ===== CUSTOMERS TAB ===== */
    var custSearchTimer=null;

    function loadCustomers(page){
        page=page||1;
        var fd=new FormData();
        fd.append("action","ppv_repair_customers_list");
        fd.append("nonce",NONCE);
        fd.append("page",page);
        var q=document.getElementById("ra-customer-search").value.trim();
        if(q)fd.append("search",q);

        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(!data.success)return;
            var d=data.data;
            document.getElementById("ra-cust-count").textContent=d.total||"0";
            var tbody=document.getElementById("ra-cust-body");
            if(page===1)tbody.innerHTML="";
            if(!d.customers||d.customers.length===0){
                if(page===1)tbody.innerHTML=\'<tr><td colspan="6" style="text-align:center;padding:32px;color:#9ca3af;">\'+L.no_customers+\'</td></tr>\';
            }else{
                d.customers.forEach(function(c){
                    var row=document.createElement("tr");
                    var isRepairSource=c.source==="repair"||parseInt(c.id)<0;
                    var sourceBadge=isRepairSource
                        ?\'<span style="display:inline-block;font-size:10px;padding:2px 6px;border-radius:6px;background:#fef3c7;color:#92400e;margin-left:6px;">\'+L.cust_form_badge+\'</span>\'
                        :\'<span style="display:inline-block;font-size:10px;padding:2px 6px;border-radius:6px;background:#d1fae5;color:#065f46;margin-left:6px;">\'+L.cust_saved_badge+\'</span>\';
                    var repairCount=c.repair_count>0?\'<span style="font-size:11px;color:#6b7280;margin-left:4px;">(\'+c.repair_count+\' \'+L.orders_count+\')</span>\':\'\';
                    var actions=\'<button class="ra-inv-btn ra-inv-btn-edit ra-cust-edit" data-cust=\\\'\'+JSON.stringify(c).replace(/\'/g,"&#39;")+\'\\\' title="\'+L.cust_edit+\'"><i class="ri-pencil-line"></i></button>\'+
                        \'<button class="ra-inv-btn ra-inv-btn-del ra-cust-del" data-cust-id="\'+c.id+\'" data-cust-source="\'+c.source+\'" title="\'+L.delete+\'"><i class="ri-delete-bin-line"></i></button>\';
                    row.innerHTML=\'<td data-label="\'+L.col_name+\'"><strong>\'+esc(c.name)+\'</strong>\'+sourceBadge+repairCount+\'</td>\'+
                        \'<td data-label="\'+L.col_company+\'">\'+esc(c.company_name||"-")+\'</td>\'+
                        \'<td data-label="\'+L.col_email+\'">\'+esc(c.email||"-")+\'</td>\'+
                        \'<td data-label="\'+L.col_phone+\'">\'+esc(c.phone||"-")+\'</td>\'+
                        \'<td data-label="\'+L.col_city+\'">\'+esc(c.city||"-")+\'</td>\'+
                        \'<td data-label=""><div class="ra-inv-actions">\'+actions+\'</div></td>\';
                    tbody.appendChild(row);
                });
            }
            // Pagination
            var more=document.getElementById("ra-cust-load-more");
            var btn=document.getElementById("ra-cust-more-btn");
            if(d.page<d.pages){
                more.style.display="block";
                btn.setAttribute("data-page",d.page);
            }else{
                more.style.display="none";
            }
        });
    }

    document.getElementById("ra-customer-search").addEventListener("input",function(){
        if(custSearchTimer)clearTimeout(custSearchTimer);
        custSearchTimer=setTimeout(function(){loadCustomers(1)},300);
    });
    document.getElementById("ra-cust-more-btn").addEventListener("click",function(){
        loadCustomers(parseInt(this.getAttribute("data-page"))+1);
    });

    // Customer table actions
    document.getElementById("ra-cust-body").addEventListener("click",function(e){
        var editBtn=e.target.closest(".ra-cust-edit");
        if(editBtn){
            var c=JSON.parse(editBtn.getAttribute("data-cust"));
            openCustomerModal(c);
            return;
        }
        var delBtn=e.target.closest(".ra-cust-del");
        if(delBtn){
            var custId=parseInt(delBtn.getAttribute("data-cust-id"));
            var custSource=delBtn.getAttribute("data-cust-source");
            // Repair-sourced customers can\'t be deleted (they\'re from repair orders)
            if(custId<0||custSource==="repair"){
                toast(L.cant_delete_form_cust);
                return;
            }
            if(!confirm(L.confirm_delete_cust))return;
            var fd=new FormData();
            fd.append("action","ppv_repair_customer_delete");
            fd.append("nonce",NONCE);
            fd.append("customer_id",custId);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success){
                    toast(L.cust_deleted);
                    loadCustomers(1);
                }else{
                    toast(data.data&&data.data.message?data.data.message:L.error);
                }
            });
        }
    });

    /* ===== ANKAUF LIST ===== */
    function loadAnkaufList(page){
        page=page||1;
        var fd=new FormData();
        fd.append("action","ppv_repair_ankauf_list");
        fd.append("nonce",NONCE);
        fd.append("page",page);
        var df=document.getElementById("ra-inv-from").value;
        var dt=document.getElementById("ra-inv-to").value;
        if(df)fd.append("date_from",df);
        if(dt)fd.append("date_to",dt);

        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(!data.success)return;
            var d=data.data;
            // Summary - Ankäufe
            document.getElementById("ra-inv-count").textContent=d.total||"0";
            var totalPrice=0;
            if(d.items)d.items.forEach(function(a){totalPrice+=parseFloat(a.ankauf_price||0)});
            document.getElementById("ra-inv-revenue").textContent=fmtEur(totalPrice);
            document.getElementById("ra-inv-discounts").textContent="-";

            // Table
            var tbody=document.getElementById("ra-inv-body");
            if(page===1)tbody.innerHTML="";
            if(!d.items||d.items.length===0){
                if(page===1)tbody.innerHTML=\'<tr><td colspan="8" style="text-align:center;padding:32px;color:#9ca3af;">\'+L.no_purchases+\'</td></tr>\';
            }else{
                d.items.forEach(function(a){
                    var date=a.created_at?new Date(a.created_at.replace(/-/g,"/")):"";
                    var dateStr=date?pad(date.getDate())+"."+pad(date.getMonth()+1)+"."+date.getFullYear():"";
                    var typeLabels={handy:L.purchase_handy,kfz:L.purchase_car,sonstiges:L.purchase_other};
                    var typeLabel=typeLabels[a.ankauf_type]||"Handy";
                    var typeBadge="<span style=\'display:inline-block;font-size:9px;padding:2px 5px;border-radius:4px;background:#fef3c7;color:#b45309;margin-left:6px\'>"+typeLabel+"</span>";
                    var pdfUrl=AJAX+"?action=ppv_repair_ankauf_pdf&ankauf_id="+a.id+"&nonce="+NONCE;
                    var device=esc((a.device_brand||"")+" "+(a.device_model||"")).trim()||"-";

                    var row=document.createElement("tr");
                    row.setAttribute("data-ankauf-id",a.id);
                    row.innerHTML=\'<td><input type="checkbox" class="ra-ankauf-checkbox" data-ankauf-id="\'+a.id+\'" style="width:16px;height:16px;cursor:pointer"></td>\'+
                        \'<td data-label="\'+L.col_nr+\'"><strong>\'+esc(a.ankauf_number)+\'</strong>\'+typeBadge+\'</td>\'+
                        \'<td data-label="\'+L.col_date+\'">\'+dateStr+\'</td>\'+
                        \'<td data-label="\'+L.col_seller+\'">\'+esc(a.seller_name)+\'</td>\'+
                        \'<td data-label="\'+L.col_article+\'">\'+device+\'</td>\'+
                        \'<td data-label="\'+L.col_imei+\'">\'+esc(a.device_imei||a.kfz_kennzeichen||"-")+\'</td>\'+
                        \'<td data-label="\'+L.col_price+\'"><strong>\'+fmtEur(a.ankauf_price)+\'</strong></td>\'+
                        \'<td data-label=""><div class="ra-inv-actions">\'+
                            \'<a href="\'+pdfUrl+\'" target="_blank" class="ra-inv-btn ra-inv-btn-pdf"><i class="ri-file-pdf-line"></i> PDF</a>\'+
                            \'<button class="ra-inv-btn ra-ankauf-edit" data-ankauf-id="\'+a.id+\'" title="\'+L.edit_btn+\'" style="background:#e0f2fe;color:#0284c7"><i class="ri-pencil-line"></i></button>\'+
                            \'<button class="ra-inv-btn ra-ankauf-del" data-ankauf-id="\'+a.id+\'" title="\'+L.delete_btn+\'"><i class="ri-delete-bin-line"></i></button>\'+
                        \'</div></td>\';
                    tbody.appendChild(row);
                });
                // Bind edit - opens in window
                tbody.querySelectorAll(".ra-ankauf-edit").forEach(function(btn){
                    btn.addEventListener("click",function(){
                        var aid=this.getAttribute("data-ankauf-id");
                        var w=900,h=800;
                        var left=(screen.width-w)/2;
                        var top=(screen.height-h)/2;
                        window.open("/formular/admin/ankauf?id="+aid,"ankauf_edit_"+aid,"width="+w+",height="+h+",left="+left+",top="+top+",scrollbars=yes,resizable=yes");
                    });
                });
                // Bind delete
                tbody.querySelectorAll(".ra-ankauf-del").forEach(function(btn){
                    btn.addEventListener("click",function(){
                        if(!confirm(L.confirm_delete_purchase))return;
                        var aid=this.getAttribute("data-ankauf-id");
                        var fd2=new FormData();
                        fd2.append("action","ppv_repair_ankauf_delete");
                        fd2.append("nonce",NONCE);
                        fd2.append("ankauf_id",aid);
                        fetch(AJAX,{method:"POST",body:fd2,credentials:"same-origin"})
                        .then(function(r){return r.json()})
                        .then(function(res){
                            if(res.success){toast(L.purchase_deleted);loadAnkaufList(1)}
                            else{toast(res.data&&res.data.message||L.error)}
                        });
                    });
                });
                // Checkbox handlers for bulk selection (Ankauf)
                tbody.querySelectorAll(".ra-ankauf-checkbox").forEach(function(cb){
                    cb.addEventListener("change",updateBulkSelection);
                });
            }
            // Reset select all checkbox
            document.getElementById("ra-inv-select-all").checked=false;
            updateBulkSelection();
            // Pagination
            var moreBtn=document.getElementById("ra-inv-more-btn");
            if(d.page<d.pages){moreBtn.style.display="inline-block";moreBtn.setAttribute("data-page",d.page)}
            else{moreBtn.style.display="none"}
        });
    }

    /* ===== ANKAUF - NEW WINDOW ===== */
    document.getElementById("ra-new-ankauf-btn").addEventListener("click",function(){
        var w = 900, h = 800;
        var left = (screen.width - w) / 2;
        var top = (screen.height - h) / 2;
        window.open("/formular/admin/ankauf", "ankauf_window", "width=" + w + ",height=" + h + ",left=" + left + ",top=" + top + ",scrollbars=yes,resizable=yes");
    });

    // Global function for child window to refresh list
    window.refreshAnkaufList = function() {
        var docType=document.getElementById("ra-inv-type-filter").value;
        if(docType==="ankauf"){loadAnkaufList(1)}
        else if (typeof loadInvoices === "function") loadInvoices(1);
    };




    /* ===== CUSTOMER MODAL ===== */
    var custModal=document.getElementById("ra-customer-modal");

    function openCustomerModal(c){
        // For repair-sourced customers (negative ID), clear ID so it creates a new saved record
        var isRepairSource=c&&(c.source==="repair"||parseInt(c.id)<0);
        var hasValidId=c&&c.id&&parseInt(c.id)>0;
        document.getElementById("ra-cust-modal-title").innerHTML=hasValidId
            ?\'<i class="ri-user-settings-line"></i> \'+L.cust_edit
            :(isRepairSource?\'<i class="ri-save-line"></i> \'+L.cust_save_as:\'<i class="ri-user-add-line"></i> \'+L.cust_new);
        document.getElementById("ra-cust-id").value=hasValidId?c.id:"";
        document.getElementById("ra-cust-name").value=c&&c.name?c.name:"";
        document.getElementById("ra-cust-company").value=c&&c.company_name?c.company_name:"";
        document.getElementById("ra-cust-email").value=c&&c.email?c.email:"";
        document.getElementById("ra-cust-phone").value=c&&c.phone?c.phone:"";
        document.getElementById("ra-cust-address").value=c&&c.address?c.address:"";
        document.getElementById("ra-cust-plz").value=c&&c.plz?c.plz:"";
        document.getElementById("ra-cust-city").value=c&&c.city?c.city:"";
        document.getElementById("ra-cust-taxid").value=c&&c.tax_id?c.tax_id:"";
        document.getElementById("ra-cust-notes").value=c&&c.notes?c.notes:"";
        custModal.classList.add("show");
    }

    document.getElementById("ra-new-customer-btn").addEventListener("click",function(){
        openCustomerModal(null);
    });
    document.getElementById("ra-cust-cancel").addEventListener("click",function(){
        custModal.classList.remove("show");
    });
    custModal.addEventListener("click",function(e){
        if(e.target===custModal)custModal.classList.remove("show");
    });

    document.getElementById("ra-cust-submit").addEventListener("click",function(){
        var name=document.getElementById("ra-cust-name").value.trim();
        if(!name){toast(L.name_required);return;}
        var fd=new FormData();
        fd.append("action","ppv_repair_customer_save");
        fd.append("nonce",NONCE);
        fd.append("customer_id",document.getElementById("ra-cust-id").value);
        fd.append("name",name);
        fd.append("company_name",document.getElementById("ra-cust-company").value);
        fd.append("email",document.getElementById("ra-cust-email").value);
        fd.append("phone",document.getElementById("ra-cust-phone").value);
        fd.append("address",document.getElementById("ra-cust-address").value);
        fd.append("plz",document.getElementById("ra-cust-plz").value);
        fd.append("city",document.getElementById("ra-cust-city").value);
        fd.append("tax_id",document.getElementById("ra-cust-taxid").value);
        fd.append("notes",document.getElementById("ra-cust-notes").value);

        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){
                toast(data.data.message||L.cust_saved);
                custModal.classList.remove("show");
                loadCustomers(1);
            }else{
                toast(data.data&&data.data.message?data.data.message:L.error);
            }
        })
        .catch(function(){toast(L.connection_error)});
    });

    /* ===== Billbee Import ===== */
    var bbModal=document.getElementById("ra-billbee-modal"),
        bbFile=document.getElementById("ra-billbee-file"),
        bbDropzone=document.getElementById("ra-billbee-dropzone"),
        bbSelected=document.getElementById("ra-billbee-selected"),
        bbFilename=document.getElementById("ra-billbee-filename"),
        bbFilesize=document.getElementById("ra-billbee-filesize"),
        bbProgress=document.getElementById("ra-billbee-progress"),
        bbSubmit=document.getElementById("ra-billbee-submit"),
        selectedFile=null;

    document.getElementById("ra-billbee-import-btn").addEventListener("click",function(){
        bbModal.classList.add("show");
        resetBillbeeForm();
    });
    document.getElementById("ra-billbee-cancel").addEventListener("click",function(){
        bbModal.classList.remove("show");
    });
    bbModal.addEventListener("click",function(e){if(e.target===bbModal)bbModal.classList.remove("show")});

    document.getElementById("ra-billbee-select").addEventListener("click",function(){bbFile.click()});
    bbDropzone.addEventListener("click",function(e){if(e.target===bbDropzone)bbFile.click()});

    bbDropzone.addEventListener("dragover",function(e){e.preventDefault();bbDropzone.style.borderColor="#f97316"});
    bbDropzone.addEventListener("dragleave",function(){bbDropzone.style.borderColor="#fed7aa"});
    bbDropzone.addEventListener("drop",function(e){
        e.preventDefault();
        bbDropzone.style.borderColor="#fed7aa";
        if(e.dataTransfer.files.length)handleFileSelect(e.dataTransfer.files[0]);
    });

    bbFile.addEventListener("change",function(){if(this.files.length)handleFileSelect(this.files[0])});

    function handleFileSelect(file){
        if(!file.name.toLowerCase().endsWith(".xml")){
            toast(L.only_xml);
            return;
        }
        selectedFile=file;
        bbFilename.textContent=file.name;
        bbFilesize.textContent=(file.size/1024).toFixed(1)+" KB";
        bbDropzone.style.display="none";
        bbSelected.style.display="block";
        bbSubmit.disabled=false;
    }

    document.getElementById("ra-billbee-remove").addEventListener("click",function(){
        resetBillbeeForm();
    });

    function resetBillbeeForm(){
        selectedFile=null;
        bbFile.value="";
        bbDropzone.style.display="block";
        bbSelected.style.display="none";
        bbProgress.style.display="none";
        bbSubmit.disabled=true;
    }

    bbSubmit.addEventListener("click",function(){
        if(!selectedFile)return;
        bbProgress.style.display="block";
        bbSubmit.disabled=true;

        var fd=new FormData();
        fd.append("action","ppv_repair_billbee_import");
        fd.append("nonce",NONCE);
        fd.append("billbee_xml",selectedFile);

        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            bbProgress.style.display="none";
            if(data.success){
                toast(data.data.message||L.import_ok);
                bbModal.classList.remove("show");
                loadInvoices(1);
            }else{
                toast(data.data&&data.data.message?data.data.message:L.import_fail);
                bbSubmit.disabled=false;
            }
        })
        .catch(function(){
            bbProgress.style.display="none";
            toast(L.connection_error);
            bbSubmit.disabled=false;
        });
    });

    /* ===== Repair Comments ===== */
    document.addEventListener("click",function(e){
        // Toggle comments
        if(e.target.closest(".ra-btn-comments-toggle")){
            var btn=e.target.closest(".ra-btn-comments-toggle");
            var rid=btn.getAttribute("data-repair-id");
            var container=document.querySelector(\'.ra-comments-container[data-repair-id="\'+rid+\'"]\');
            if(!container)return;
            var isOpen=container.style.display!=="none";
            if(isOpen){
                container.style.display="none";
                btn.classList.remove("active");
            }else{
                container.style.display="block";
                btn.classList.add("active");
                loadComments(rid,container);
            }
        }
        // Delete comment
        if(e.target.closest(".ra-comment-delete")){
            var btn=e.target.closest(".ra-comment-delete");
            var cid=btn.getAttribute("data-comment-id");
            if(!confirm(L.confirm_delete_comment))return;
            var fd=new FormData();
            fd.append("action","ppv_repair_comment_delete");
            fd.append("nonce",NONCE);
            fd.append("comment_id",cid);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success){
                    btn.closest(".ra-comment-item").remove();
                    toast(L.comment_deleted);
                }else{
                    toast(data.data&&data.data.message?data.data.message:L.error);
                }
            });
        }
        // Submit comment
        if(e.target.closest(".ra-comment-submit")){
            var btn=e.target.closest(".ra-comment-submit");
            var container=btn.closest(".ra-comments-container");
            var rid=container.getAttribute("data-repair-id");
            var input=container.querySelector(".ra-comment-input");
            var text=input.value.trim();
            if(!text)return;
            btn.disabled=true;
            var fd=new FormData();
            fd.append("action","ppv_repair_comment_add");
            fd.append("nonce",NONCE);
            fd.append("repair_id",rid);
            fd.append("comment",text);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success){
                    input.value="";
                    loadComments(rid,container);
                    toast(L.comment_added);
                }else{
                    toast(data.data&&data.data.message?data.data.message:L.error);
                }
            })
            .finally(function(){btn.disabled=false});
        }
    });

    // Enter key to submit comment
    document.addEventListener("keypress",function(e){
        if(e.key==="Enter"&&e.target.classList.contains("ra-comment-input")){
            e.preventDefault();
            var container=e.target.closest(".ra-comments-container");
            container.querySelector(".ra-comment-submit").click();
        }
    });

    function loadComments(rid,container){
        var list=container.querySelector(".ra-comments-list");
        list.innerHTML=\'<div style="text-align:center;padding:10px;color:#9ca3af"><i class="ri-loader-4-line ri-spin"></i></div>\';
        var fd=new FormData();
        fd.append("action","ppv_repair_comments_list");
        fd.append("nonce",NONCE);
        fd.append("repair_id",rid);
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success&&data.data.comments){
                if(data.data.comments.length===0){
                    list.innerHTML=\'<div class="ra-comments-empty">\'+L.no_comments+\'</div>\';
                }else{
                    var html="";
                    data.data.comments.forEach(function(c){
                        html+=\'<div class="ra-comment-item">\'+
                            \'<button class="ra-comment-delete" data-comment-id="\'+c.id+\'"><i class="ri-close-line"></i></button>\'+
                            \'<div class="ra-comment-text">\'+escapeHtml(c.comment)+\'</div>\'+
                            \'<div class="ra-comment-meta">\'+c.created_at+\'</div>\'+
                        \'</div>\';
                    });
                    list.innerHTML=html;
                }
            }else{
                list.innerHTML=\'<div class="ra-comments-empty">\'+L.loading_error+\'</div>\';
            }
        });
    }

    function escapeHtml(t){
        var d=document.createElement("div");
        d.textContent=t;
        return d.innerHTML;
    }

    /* ===== Subscription Cancellation ===== */
    var cancelSubBtn=document.getElementById("ra-cancel-sub-btn");
    if(cancelSubBtn){
        cancelSubBtn.addEventListener("click",function(){
            if(!confirm(L.cancel_sub)){
                return;
            }
            cancelSubBtn.disabled=true;
            cancelSubBtn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> \'+L.processing;
            fetch("/wp-json/punktepass/v1/repair/cancel-subscription",{
                method:"POST",
                headers:{"Content-Type":"application/json"},
                credentials:"same-origin"
            })
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success){
                    toast(L.sub_cancelled);
                    setTimeout(function(){location.reload()},2000);
                }else{
                    toast(data.error||data.message||L.cancel_error);
                    cancelSubBtn.disabled=false;
                    cancelSubBtn.innerHTML=\'<i class="ri-close-line"></i> \'+L.abo_cancel_btn;
                }
            })
            .catch(function(){
                toast(L.connection_error);
                cancelSubBtn.disabled=false;
                cancelSubBtn.innerHTML=\'<i class="ri-close-line"></i> \'+L.abo_cancel_btn;
            });
        });
    }

    /* ===== Reward Toggle, Approve & Reject ===== */
    document.addEventListener("click",function(e){
        // Toggle reward section
        if(e.target.closest(".ra-btn-reward-toggle")){
            var btn=e.target.closest(".ra-btn-reward-toggle");
            var rid=btn.getAttribute("data-repair-id");
            var container=document.querySelector(\'.ra-reward-container[data-repair-id="\'+rid+\'"]\');
            if(!container)return;
            var isOpen=container.style.display!=="none";
            if(isOpen){
                container.style.display="none";
                btn.classList.remove("active");
            }else{
                container.style.display="block";
                btn.classList.add("active");
            }
        }

        // Approve reward
        if(e.target.closest(".ra-reward-approve")){
            var btn=e.target.closest(".ra-reward-approve");
            var rid=btn.getAttribute("data-repair-id");
            var pts=btn.getAttribute("data-points")||4;
            if(!confirm(L.approve_confirm))return;
            btn.disabled=true;
            btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i>\';
            var fd=new FormData();
            fd.append("action","ppv_repair_reward_approve");
            fd.append("nonce",NONCE);
            fd.append("repair_id",rid);
            fd.append("points",pts);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success){
                    toast(L.reward_approved);
                    setTimeout(function(){location.reload()},1500);
                }else{
                    toast(data.data&&data.data.message?data.data.message:L.error);
                    btn.disabled=false;
                    btn.innerHTML=\'<i class="ri-check-line"></i> \'+L.approve;
                }
            })
            .catch(function(){
                toast(L.connection_error);
                btn.disabled=false;
                btn.innerHTML=\'<i class="ri-check-line"></i> \'+L.approve;
            });
        }

        // Reject reward (open modal)
        if(e.target.closest(".ra-reward-reject")){
            var btn=e.target.closest(".ra-reward-reject");
            var rid=btn.getAttribute("data-repair-id");
            document.getElementById("ra-reject-repair-id").value=rid;
            document.getElementById("ra-reject-reason").value="";
            document.getElementById("ra-reject-modal").classList.add("show");
        }
    });

    // Reject modal handlers
    var rejectModal=document.getElementById("ra-reject-modal");
    if(rejectModal){
        document.getElementById("ra-reject-cancel").addEventListener("click",function(){
            rejectModal.classList.remove("show");
        });
        rejectModal.addEventListener("click",function(e){
            if(e.target===rejectModal)rejectModal.classList.remove("show");
        });
        document.getElementById("ra-reject-submit").addEventListener("click",function(){
            var rid=document.getElementById("ra-reject-repair-id").value;
            var reason=document.getElementById("ra-reject-reason").value.trim();
            if(!reason){
                alert(L.reject_reason);
                return;
            }
            var btn=this;
            btn.disabled=true;
            btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> \'+L.saving;
            var fd=new FormData();
            fd.append("action","ppv_repair_reward_reject");
            fd.append("nonce",NONCE);
            fd.append("repair_id",rid);
            fd.append("reason",reason);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success){
                    toast(L.reward_rejected);
                    rejectModal.classList.remove("show");
                    setTimeout(function(){location.reload()},1500);
                }else{
                    toast(data.data&&data.data.message?data.data.message:L.error);
                    btn.disabled=false;
                    btn.innerHTML=L.reject_btn;
                }
            })
            .catch(function(){
                toast(L.connection_error);
                btn.disabled=false;
                btn.innerHTML=L.reject_btn;
            });
        });
    }

    /* ===== Feedback Tab ===== */
    var fbCategory="";
    var fbRating=0;

    // Category selection
    document.querySelectorAll(".ra-fb-cat-btn").forEach(function(btn){
        btn.addEventListener("click",function(){
            document.querySelectorAll(".ra-fb-cat-btn").forEach(function(b){b.classList.remove("active")});
            this.classList.add("active");
            fbCategory=this.getAttribute("data-cat");
            document.getElementById("ra-fb-rating-section").style.display=(fbCategory==="rating")?"block":"none";
            document.getElementById("ra-fb-msg").className="ra-fb-msg";
        });
    });

    // Star rating
    document.querySelectorAll(".ra-fb-star").forEach(function(star){
        star.addEventListener("click",function(){
            fbRating=parseInt(this.getAttribute("data-rating"));
            document.querySelectorAll(".ra-fb-star").forEach(function(s,idx){
                if(idx<fbRating){
                    s.classList.add("active");
                    s.querySelector("i").className="ri-star-fill";
                }else{
                    s.classList.remove("active");
                    s.querySelector("i").className="ri-star-line";
                }
            });
        });
    });

    // Submit feedback
    document.getElementById("ra-fb-submit").addEventListener("click",function(){
        var btn=this;
        var msg=document.getElementById("ra-fb-msg");
        var message=document.getElementById("ra-fb-message").value.trim();
        var email=document.getElementById("ra-fb-email").value.trim();

        if(!fbCategory){
            msg.textContent=L.fb_category;
            msg.className="ra-fb-msg show error";
            return;
        }
        if(fbCategory==="rating"&&fbRating===0){
            msg.textContent=L.fb_rating;
            msg.className="ra-fb-msg show error";
            return;
        }
        if(!message&&fbCategory!=="rating"){
            msg.textContent=L.fb_describe;
            msg.className="ra-fb-msg show error";
            return;
        }

        btn.disabled=true;
        btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> \'+L.sending;

        var fd=new FormData();
        fd.append("action","ppv_submit_feedback");
        fd.append("nonce","' . wp_create_nonce('ppv_feedback_nonce') . '");
        fd.append("category",fbCategory);
        fd.append("message",message);
        fd.append("rating",fbRating);
        fd.append("email",email);
        fd.append("user_type","repair_handler");
        fd.append("page_url",window.location.href);
        fd.append("device_info",navigator.userAgent);
        fd.append("source","repair_formular");

        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){
                msg.textContent=L.fb_thanks;
                msg.className="ra-fb-msg show success";
                // Reset form
                document.getElementById("ra-fb-message").value="";
                document.getElementById("ra-fb-email").value="";
                document.querySelectorAll(".ra-fb-cat-btn").forEach(function(b){b.classList.remove("active")});
                document.querySelectorAll(".ra-fb-star").forEach(function(s){s.classList.remove("active");s.querySelector("i").className="ri-star-line"});
                document.getElementById("ra-fb-rating-section").style.display="none";
                fbCategory="";
                fbRating=0;
            }else{
                msg.textContent=data.data&&data.data.message?data.data.message:L.fb_error;
                msg.className="ra-fb-msg show error";
            }
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-send-plane-fill"></i> \'+L.fb_submit;
        })
        .catch(function(){
            msg.textContent=L.connection_error;
            msg.className="ra-fb-msg show error";
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-send-plane-fill"></i> \'+L.fb_submit;
        });
    });

    // Restore saved tab on page load (must be after function definitions)
    var savedTab=localStorage.getItem("ra_active_tab");
    if(savedTab&&document.querySelector(\'.ra-tab[data-tab="\'+savedTab+\'"]\'))switchTab(savedTab);

    // Kiosk Tips Modal Functions
    window.openKioskTips=function(){document.getElementById("kiosk-tips-modal").classList.add("active");document.body.style.overflow="hidden"};
    window.closeKioskTips=function(){document.getElementById("kiosk-tips-modal").classList.remove("active");document.body.style.overflow=""};
    window.copyKioskUrl=function(){var url=document.getElementById("kiosk-form-url").textContent;navigator.clipboard.writeText(url).then(function(){var btn=document.querySelector(".kiosk-copy-btn");btn.innerHTML=\'<i class="ri-check-line"></i>\';setTimeout(function(){btn.innerHTML=\'<i class="ri-file-copy-line"></i>\'},2000)})};
    document.addEventListener("keydown",function(e){if(e.key==="Escape")closeKioskTips()});

    /* COMMENTED OUT - Supplier directory toggle (temporarily disabled)
    window.toggleSuppliers=function(){
        var body=document.getElementById("ra-suppliers-body");
        var arrow=document.getElementById("ra-suppliers-arrow");
        var toggle=document.getElementById("ra-suppliers-toggle");
        if(!body)return;
        var isOpen=body.classList.contains("open");
        body.classList.toggle("open");
        if(toggle)toggle.classList.toggle("open");
        try{localStorage.setItem("ra_suppliers_open",isOpen?"0":"1")}catch(e){}
    };
    (function(){
        var body=document.getElementById("ra-suppliers-body");
        var toggle=document.getElementById("ra-suppliers-toggle");
        if(!body)return;
        var saved=null;try{saved=localStorage.getItem("ra_suppliers_open")}catch(e){}
        if(saved==="1"||saved===null){body.classList.add("open");if(toggle)toggle.classList.add("open")}
    })();
    var suppH=document.querySelector(".ra-suppliers-header");
    if(suppH)suppH.addEventListener("click",function(){toggleSuppliers()});
    END COMMENTED OUT */

    /* ===== Filiale Switcher ===== */
    var filialeBtn = document.getElementById("ra-filiale-btn");
    var filialeDropdown = document.getElementById("ra-filiale-dropdown");

    if (filialeBtn && filialeDropdown) {
        filialeBtn.addEventListener("click", function(e) {
            e.stopPropagation();
            filialeDropdown.classList.toggle("ra-hidden");
        });
        document.addEventListener("click", function() {
            if (filialeDropdown) filialeDropdown.classList.add("ra-hidden");
        });
        filialeDropdown.addEventListener("click", function(e) { e.stopPropagation(); });

        // Switch filiale
        var filialeItems = document.querySelectorAll(".ra-filiale-item");
        filialeItems.forEach(function(item) {
            item.addEventListener("click", function() {
                var id = this.getAttribute("data-id");
                var fd = new FormData();
                fd.append("action", "ppv_repair_switch_filiale");
                fd.append("nonce", NONCE);
                fd.append("filiale_id", id);
                fetch(AJAX, {method:"POST", body:fd, credentials:"same-origin"})
                .then(function(r){return r.json()})
                .then(function(data){
                    if(data.success) window.location.reload();
                    else alert(data.data && data.data.message ? data.data.message : "Fehler");
                });
            });
        });

        // Add filiale button (in dropdown)
        var addBtnDropdown = document.getElementById("ra-filiale-add-btn");
        if (addBtnDropdown) {
            addBtnDropdown.addEventListener("click", function() {
                filialeDropdown.classList.add("ra-hidden");
                // Open settings panel on filialen tab
                toggleSettings(true);
                var filialenTab = document.querySelector(\'[data-panel="filialen"]\');
                if (filialenTab) filialenTab.click();
            });
        }
    }

    /* ===== Create Filiale (Settings Panel) ===== */
    var createFilialeBtn = document.getElementById("ra-create-filiale-btn");
    if (createFilialeBtn) {
        createFilialeBtn.addEventListener("click", function() {
            var name = document.getElementById("ra-new-filiale-name").value.trim();
            var city = document.getElementById("ra-new-filiale-city").value.trim();
            var plz  = document.getElementById("ra-new-filiale-plz").value.trim();
            var msgEl = document.getElementById("ra-filiale-msg");

            if (!name || !city) {
                msgEl.textContent = "Name und Stadt sind Pflichtfelder";
                msgEl.style.background = "#fef2f2";
                msgEl.style.color = "#991b1b";
                msgEl.classList.remove("ra-hidden");
                return;
            }

            createFilialeBtn.disabled = true;
            createFilialeBtn.innerHTML = \'<i class="ri-loader-4-line ri-spin"></i> Erstelle...\';

            var fd = new FormData();
            fd.append("action", "ppv_repair_create_filiale");
            fd.append("nonce", NONCE);
            fd.append("filiale_name", name);
            fd.append("city", city);
            fd.append("plz", plz);

            fetch(AJAX, {method:"POST", body:fd, credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if (data.success) {
                    msgEl.textContent = data.data.message || "Filiale erstellt!";
                    msgEl.style.background = "#f0fdf4";
                    msgEl.style.color = "#166534";
                    msgEl.classList.remove("ra-hidden");
                    setTimeout(function(){ window.location.reload(); }, 1500);
                } else {
                    msgEl.textContent = data.data && data.data.message ? data.data.message : "Fehler";
                    msgEl.style.background = "#fef2f2";
                    msgEl.style.color = "#991b1b";
                    msgEl.classList.remove("ra-hidden");
                    createFilialeBtn.disabled = false;
                    createFilialeBtn.innerHTML = \'<i class="ri-add-line"></i> Filiale erstellen\';
                }
            })
            .catch(function(){
                msgEl.textContent = "Netzwerkfehler";
                msgEl.style.background = "#fef2f2";
                msgEl.style.color = "#991b1b";
                msgEl.classList.remove("ra-hidden");
                createFilialeBtn.disabled = false;
                createFilialeBtn.innerHTML = \'<i class="ri-add-line"></i> Filiale erstellen\';
            });
        });
    }

    /* ===== Copy Filiale Link ===== */
    document.querySelectorAll(".ra-filiale-copy").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var url = this.getAttribute("data-url");
            var el = this;
            navigator.clipboard.writeText(url).then(function() {
                var orig = el.innerHTML;
                el.innerHTML = \'<i class="ri-check-line" style="color:#22c55e"></i> Kopiert!\';
                setTimeout(function(){ el.innerHTML = orig; }, 2000);
            });
        });
    });

    /* ===== Edit Filiale ===== */
    document.querySelectorAll(".ra-filiale-edit").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var fid = this.getAttribute("data-fid");
            var card = this.closest(".ra-filiale-card");
            var curName = this.getAttribute("data-name");
            var curCity = this.getAttribute("data-city");
            var curPlz  = this.getAttribute("data-plz");

            var newName = prompt("Name:", curName);
            if (newName === null) return;
            var newCity = prompt("Stadt:", curCity);
            if (newCity === null) return;
            var newPlz  = prompt("PLZ:", curPlz);
            if (newPlz === null) return;

            var fd = new FormData();
            fd.append("action", "ppv_repair_edit_filiale");
            fd.append("nonce", NONCE);
            fd.append("filiale_id", fid);
            fd.append("filiale_name", newName);
            fd.append("city", newCity);
            fd.append("plz", newPlz);

            fetch(AJAX, {method:"POST", body:fd, credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success) window.location.reload();
                else alert(data.data && data.data.message ? data.data.message : "Fehler");
            });
        });
    });

    /* ===== Delete Filiale ===== */
    document.querySelectorAll(".ra-filiale-delete").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var fid = this.getAttribute("data-fid");
            var fname = this.getAttribute("data-name");

            if (!confirm("Filiale \\"" + fname + "\\" wirklich löschen? Alle Daten gehen verloren.")) return;

            var fd = new FormData();
            fd.append("action", "ppv_repair_delete_filiale");
            fd.append("nonce", NONCE);
            fd.append("filiale_id", fid);

            fetch(AJAX, {method:"POST", body:fd, credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success) window.location.reload();
                else alert(data.data && data.data.message ? data.data.message : "Fehler");
            });
        });
    });

    // Warranty date toggle: show/hide description textarea and pre-fill from store default
    function setupWarrantyToggle(dateId,descId,wrapId){
        var dateEl=document.getElementById(dateId);
        if(!dateEl)return;
        dateEl.addEventListener("change",function(){
            var wrap=document.getElementById(wrapId);
            var desc=document.getElementById(descId);
            if(this.value){
                if(wrap)wrap.style.display="block";
                if(desc&&!desc.value&&WARRANTY_DEFAULT)desc.value=WARRANTY_DEFAULT;
            }else{
                if(wrap)wrap.style.display="none";
            }
        });
    }
    setupWarrantyToggle("ra-ninv-warranty-date","ra-ninv-warranty-desc","ra-ninv-warranty-desc-wrap");
    setupWarrantyToggle("ra-einv-warranty-date","ra-einv-warranty-desc","ra-einv-warranty-desc-wrap");
    // Old invoice modal: show desc when date filled
    document.getElementById("ra-inv-warranty-date").addEventListener("change",function(){
        var desc=document.getElementById("ra-inv-warranty-desc");
        if(this.value){
            desc.style.display="block";
            if(!desc.value&&WARRANTY_DEFAULT)desc.value=WARRANTY_DEFAULT;
        }else{
            desc.style.display="none";
        }
    });

})();
</script>

<!-- Kiosk Tips Modal -->
<div id="kiosk-tips-modal" class="kiosk-tips-modal" onclick="if(event.target===this)closeKioskTips()">
    <div class="kiosk-tips-content">
        <button type="button" class="kiosk-tips-close" onclick="closeKioskTips()"><i class="ri-close-line"></i></button>
        <div class="kiosk-tips-header">
            <div class="kiosk-tips-icon"><i class="ri-tablet-line"></i></div>
            <h2>' . esc_html(PPV_Lang::t('repair_admin_kiosk_title')) . '</h2>
            <p>' . esc_html(PPV_Lang::t('repair_admin_kiosk_desc')) . '</p>
        </div>

        <div class="kiosk-tips-body">
            <div class="kiosk-tip-section">
                <h3><i class="ri-download-2-line"></i> ' . PPV_Lang::t('repair_admin_kiosk_step1') . '</h3>
                <p>' . PPV_Lang::t('repair_admin_kiosk_step1_desc') . '</p>
                <a href="https://play.google.com/store/apps/details?id=de.ozerov.fully" target="_blank" class="kiosk-tip-link">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/7/78/Google_Play_Store_badge_EN.svg" alt="Google Play" style="height:40px">
                </a>
            </div>

            <div class="kiosk-tip-section">
                <h3><i class="ri-settings-3-line"></i> ' . PPV_Lang::t('repair_admin_kiosk_step2') . '</h3>
                <p>' . PPV_Lang::t('repair_admin_kiosk_step2_desc') . '</p>
                <div class="kiosk-tip-url">
                    <code id="kiosk-form-url">' . esc_url($form_url) . '</code>
                    <button type="button" onclick="copyKioskUrl()" class="kiosk-copy-btn"><i class="ri-file-copy-line"></i></button>
                </div>
            </div>

            <div class="kiosk-tip-section">
                <h3><i class="ri-fullscreen-line"></i> ' . PPV_Lang::t('repair_admin_kiosk_step3') . '</h3>
                <p>' . PPV_Lang::t('repair_admin_kiosk_step3_desc') . '</p>
                <ul class="kiosk-tip-list">
                    <li><i class="ri-checkbox-circle-fill"></i> <strong>Web Content &rarr; Fullscreen Mode</strong> aktivieren</li>
                    <li><i class="ri-checkbox-circle-fill"></i> <strong>Device Management &rarr; Hide Status Bar</strong> aktivieren</li>
                    <li><i class="ri-checkbox-circle-fill"></i> <strong>Device Management &rarr; Hide Navigation Bar</strong> aktivieren</li>
                    <li><i class="ri-checkbox-circle-fill"></i> <strong>Device Management &rarr; Keep Screen On</strong> aktivieren</li>
                    <li><i class="ri-checkbox-circle-fill"></i> <strong>Kiosk Mode &rarr; Enable Kiosk Mode</strong> aktivieren</li>
                    <li><i class="ri-checkbox-circle-fill"></i> <strong>Kiosk Mode &rarr; Kiosk Exit PIN</strong> setzen (z.B. 1234)</li>
                </ul>
            </div>

            <div class="kiosk-tip-section">
                <h3><i class="ri-rocket-line"></i> ' . PPV_Lang::t('repair_admin_kiosk_step4') . '</h3>
                <p>' . PPV_Lang::t('repair_admin_kiosk_step4_desc') . '</p>
                <p class="kiosk-tip-note"><i class="ri-lock-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_kiosk_exit')) . '</p>
            </div>
        </div>

        <div class="kiosk-tips-footer">
            <a href="https://www.fully-kiosk.com" target="_blank" class="kiosk-tip-external">
                <i class="ri-external-link-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_kiosk_website')) . '
            </a>
        </div>
    </div>
</div>

<style>
.ra-btn-tips{width:40px;height:40px;border:none;background:#fef3c7;border-radius:8px;color:#d97706;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;transition:all .2s}
.ra-btn-tips:hover{background:#fde68a;transform:scale(1.05)}
.kiosk-tips-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:10000;padding:20px;overflow-y:auto;backdrop-filter:blur(4px)}
.kiosk-tips-modal.active{display:flex;align-items:flex-start;justify-content:center}
.kiosk-tips-content{background:#fff;border-radius:16px;max-width:540px;width:100%;margin:20px auto;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);animation:kioskSlideIn .3s ease;position:relative}
@keyframes kioskSlideIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
.kiosk-tips-close{position:absolute;top:12px;right:12px;width:32px;height:32px;border:none;background:rgba(0,0,0,0.05);border-radius:50%;cursor:pointer;font-size:20px;color:#64748b;display:flex;align-items:center;justify-content:center;transition:all .2s;z-index:1}
.kiosk-tips-close:hover{background:rgba(0,0,0,0.1);color:#1e293b}
.kiosk-tips-header{position:relative;padding:28px 24px 20px;text-align:center;border-bottom:1px solid #e2e8f0}
.kiosk-tips-icon{width:56px;height:56px;background:linear-gradient(135deg,#667eea,#8b5cf6);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px;color:#fff}
.kiosk-tips-header h2{font-size:20px;font-weight:700;color:#1e293b;margin:0 0 4px}
.kiosk-tips-header p{font-size:14px;color:#64748b;margin:0}
.kiosk-tips-body{padding:20px 24px}
.kiosk-tip-section{margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid #f1f5f9}
.kiosk-tip-section:last-child{margin-bottom:0;padding-bottom:0;border-bottom:none}
.kiosk-tip-section h3{font-size:15px;font-weight:600;color:#1e293b;margin:0 0 10px;display:flex;align-items:center;gap:8px}
.kiosk-tip-section h3 i{color:#667eea;font-size:18px}
.kiosk-tip-section p{font-size:14px;color:#475569;margin:0 0 12px;line-height:1.6}
.kiosk-tip-link{display:inline-block;margin:8px 0}
.kiosk-tip-note{font-size:12px;color:#94a3b8;background:#f8fafc;padding:10px 12px;border-radius:8px;display:flex;align-items:flex-start;gap:6px}
.kiosk-tip-note i{color:#f59e0b;font-size:14px;margin-top:1px}
.kiosk-tip-url{display:flex;align-items:center;gap:8px;background:#f1f5f9;padding:10px 14px;border-radius:8px;margin:8px 0}
.kiosk-tip-url code{flex:1;font-size:13px;color:#334155;word-break:break-all;font-family:monospace}
.kiosk-copy-btn{width:32px;height:32px;border:none;background:#667eea;border-radius:6px;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s}
.kiosk-copy-btn:hover{transform:scale(1.05)}
.kiosk-tip-list{list-style:none;padding:0;margin:12px 0 0}
.kiosk-tip-list li{display:flex;align-items:flex-start;gap:8px;padding:8px 0;font-size:13px;color:#475569}
.kiosk-tip-list li i{color:#10b981;font-size:16px;margin-top:1px}
.kiosk-tips-footer{padding:16px 24px;background:#f8fafc;border-radius:0 0 16px 16px;text-align:center}
.kiosk-tip-external{font-size:13px;color:#667eea;text-decoration:none;display:inline-flex;align-items:center;gap:4px;font-weight:500}
.kiosk-tip-external:hover{text-decoration:underline}
</style>
';
        // Render floating AI chat widget for repair admin
        if (class_exists('PPV_AI_Support')) {
            PPV_AI_Support::render_repair_widget($lang);
        }
        echo '
</body>
</html>';
    }

    /** Darken a hex color by percentage */
    private static function darken_hex($hex, $pct) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return '#667eea';
        $r = max(0, round(hexdec(substr($hex, 0, 2)) * (1 - $pct)));
        $g = max(0, round(hexdec(substr($hex, 2, 2)) * (1 - $pct)));
        $b = max(0, round(hexdec(substr($hex, 4, 2)) * (1 - $pct)));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** ============================================================
     * Build HTML for a single repair card (server-side)
     * ============================================================ */
    private static function build_repair_card_html($r, $store = null) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $status_map = [
            'new'           => [PPV_Lang::t('repair_admin_status_new'),       'ra-status-new'],
            'in_progress'   => [PPV_Lang::t('repair_admin_status_progress'),  'ra-status-progress'],
            'waiting_parts' => [PPV_Lang::t('repair_admin_status_waiting'),   'ra-status-waiting'],
            'done'          => [PPV_Lang::t('repair_admin_status_done'),      'ra-status-done'],
            'delivered'     => [PPV_Lang::t('repair_admin_status_delivered'), 'ra-status-delivered'],
            'cancelled'     => [PPV_Lang::t('repair_admin_status_cancelled'),'ra-status-cancelled'],
        ];

        $st    = $status_map[$r->status] ?? ['?', ''];
        $device = trim(($r->device_brand ?: '') . ' ' . ($r->device_model ?: ''));
        $date   = date('d.m.Y H:i', strtotime($r->created_at));
        $updated = !empty($r->updated_at) && $r->updated_at !== $r->created_at ? date('d.m.Y H:i', strtotime($r->updated_at)) : '';
        $problem = esc_html(mb_strimwidth($r->problem_description, 0, 150, '...'));
        $phone  = $r->customer_phone ? (' &middot; ' . esc_html($r->customer_phone)) : '';

        $device_html = '';
        if ($device) {
            $device_html = '<div class="ra-repair-device"><i class="ri-smartphone-line"></i> ' . esc_html($device) . '</div>';
        }

        // PIN code display
        $pin_html = '';
        if (!empty($r->device_pattern)) {
            $pin_html = '<div class="ra-repair-pin"><i class="ri-lock-password-line"></i> PIN: ' . esc_html($r->device_pattern) . '</div>';
        }

        // Muster image display
        $muster_html = '';
        if (!empty($r->muster_image) && strpos($r->muster_image, 'data:image/') === 0) {
            $muster_html = '<div class="ra-repair-muster"><img src="' . esc_attr($r->muster_image) . '" alt="' . esc_attr(PPV_Lang::t('repair_admin_unlock_pattern')) . '" title="' . esc_attr(PPV_Lang::t('repair_admin_unlock_pattern')) . '"></div>';
        }

        // Address display
        $address_html = '';
        if (!empty($r->customer_address)) {
            $address_html = '<div class="ra-repair-address"><i class="ri-map-pin-line"></i> ' . esc_html($r->customer_address) . '</div>';
        }

        // IMEI display
        $imei_html = '';
        if (!empty($r->device_imei)) {
            $imei_html = '<div class="ra-repair-imei" style="font-size:12px;color:#6b7280;margin-top:2px"><i class="ri-barcode-line"></i> IMEI: ' . esc_html($r->device_imei) . '</div>';
        }

        // Accessories display
        $acc_html = '';
        if (!empty($r->accessories) && $r->accessories !== '[]' && $r->accessories !== '[""]') {
            $acc_html = '<div class="ra-repair-acc"><i class="ri-checkbox-multiple-line"></i> ' . esc_html($r->accessories) . '</div>';
        }

        // Custom fields (includes new built-in extras: color, date, condition, priority, cost, photos)
        $cf_html = '';
        $cf_data_json = '';
        if (!empty($r->custom_fields)) {
            $cf = json_decode($r->custom_fields, true);
            if (is_array($cf) && !empty($cf)) {
                $cf_data_json = esc_attr($r->custom_fields);
                $cf_parts = [];
                $cf_labels = [
                    'device_color' => ['icon' => 'ri-palette-line', 'label' => PPV_Lang::t('repair_fb_color')],
                    'purchase_date' => ['icon' => 'ri-calendar-line', 'label' => PPV_Lang::t('repair_fb_purchase_date')],
                    'priority' => ['icon' => 'ri-flashlight-line', 'label' => PPV_Lang::t('repair_fb_priority')],
                    'cost_limit' => ['icon' => 'ri-money-euro-circle-line', 'label' => PPV_Lang::t('repair_fb_cost_limit')],
                    'vehicle_plate' => ['icon' => 'ri-car-line', 'label' => PPV_Lang::t('repair_vehicle_plate_label')],
                    'vehicle_vin' => ['icon' => 'ri-fingerprint-line', 'label' => PPV_Lang::t('repair_vehicle_vin_label')],
                    'vehicle_mileage' => ['icon' => 'ri-dashboard-3-line', 'label' => PPV_Lang::t('repair_vehicle_mileage_label')],
                    'vehicle_first_reg' => ['icon' => 'ri-calendar-check-line', 'label' => PPV_Lang::t('repair_vehicle_first_reg_label')],
                    'vehicle_tuev' => ['icon' => 'ri-shield-star-line', 'label' => PPV_Lang::t('repair_vehicle_tuev_label')],
                ];
                foreach ($cf as $ck => $cv) {
                    if ($ck === 'photos' || $ck === 'condition_check' || $ck === 'condition_check_kfz' || $ck === 'condition_check_pc') continue; // handled separately
                    $icon = 'ri-file-text-line';
                    $lbl = $ck;
                    if (isset($cf_labels[$ck])) { $icon = $cf_labels[$ck]['icon']; $lbl = $cf_labels[$ck]['label']; }
                    elseif (strpos($ck, 'custom_') === 0) {
                        // Get label from field_config
                        $fc_entry = $field_config[$ck] ?? null;
                        if ($fc_entry && !empty($fc_entry['label'])) $lbl = $fc_entry['label'];
                    }
                    if (is_string($cv) && $cv !== '') {
                        $cf_parts[] = '<div style="font-size:12px;color:#6b7280;margin-top:2px"><i class="' . $icon . '"></i> ' . esc_html($lbl) . ': <strong style="color:#374151">' . esc_html($cv) . '</strong></div>';
                    }
                }
                // Condition check
                if (!empty($cf['condition_check'])) {
                    $cond = is_string($cf['condition_check']) ? json_decode($cf['condition_check'], true) : $cf['condition_check'];
                    if (is_array($cond)) {
                        $cond_items = [];
                        foreach ($cond as $part => $status) {
                            $color = $status === 'ok' ? '#059669' : '#dc2626';
                            $cond_items[] = '<span style="color:' . $color . '">' . esc_html($part) . ': ' . esc_html(strtoupper($status)) . '</span>';
                        }
                        $cf_parts[] = '<div style="font-size:12px;color:#6b7280;margin-top:2px"><i class="ri-shield-check-line"></i> ' . esc_html(PPV_Lang::t('repair_fb_condition')) . ': ' . implode(', ', $cond_items) . '</div>';
                    }
                }
                // KFZ Condition check
                if (!empty($cf['condition_check_kfz'])) {
                    $cond = is_string($cf['condition_check_kfz']) ? json_decode($cf['condition_check_kfz'], true) : $cf['condition_check_kfz'];
                    if (is_array($cond)) {
                        $cond_items = [];
                        foreach ($cond as $part => $status) {
                            $color = $status === 'ok' ? '#059669' : '#dc2626';
                            $cond_items[] = '<span style="color:' . $color . '">' . esc_html($part) . ': ' . esc_html(strtoupper($status)) . '</span>';
                        }
                        $cf_parts[] = '<div style="font-size:12px;color:#6b7280;margin-top:2px"><i class="ri-car-washing-line"></i> ' . esc_html(PPV_Lang::t('repair_fb_condition_kfz')) . ': ' . implode(', ', $cond_items) . '</div>';
                    }
                }
                // PC Condition check
                if (!empty($cf['condition_check_pc'])) {
                    $cond = is_string($cf['condition_check_pc']) ? json_decode($cf['condition_check_pc'], true) : $cf['condition_check_pc'];
                    if (is_array($cond)) {
                        $cond_items = [];
                        foreach ($cond as $part => $status) {
                            $color = $status === 'ok' ? '#059669' : '#dc2626';
                            $cond_items[] = '<span style="color:' . $color . '">' . esc_html($part) . ': ' . esc_html(strtoupper($status)) . '</span>';
                        }
                        $cf_parts[] = '<div style="font-size:12px;color:#6b7280;margin-top:2px"><i class="ri-computer-line"></i> ' . esc_html(PPV_Lang::t('repair_fb_condition_pc')) . ': ' . implode(', ', $cond_items) . '</div>';
                    }
                }
                // Photos
                if (!empty($cf['photos']) && is_array($cf['photos'])) {
                    $photo_imgs = '';
                    foreach ($cf['photos'] as $purl) {
                        $photo_imgs .= '<img src="' . esc_url($purl) . '" style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;margin-right:4px">';
                    }
                    $cf_parts[] = '<div style="margin-top:4px"><i class="ri-camera-line" style="font-size:12px;color:#6b7280"></i> ' . $photo_imgs . '</div>';
                }
                $cf_html = implode('', $cf_parts);
            }
        }

        // Check if invoice already exists for this repair
        $invoice_numbers = '';
        if (!empty($r->invoice_numbers)) {
            $invoice_numbers = $r->invoice_numbers;
        } else {
            $inv_num = $wpdb->get_var($wpdb->prepare(
                "SELECT GROUP_CONCAT(invoice_number ORDER BY id DESC) FROM {$prefix}ppv_repair_invoices WHERE repair_id = %d AND (doc_type = 'rechnung' OR doc_type IS NULL)",
                $r->id
            ));
            if ($inv_num) $invoice_numbers = $inv_num;
        }
        $has_invoice = !empty($invoice_numbers);

        // Status options
        $options = '';
        foreach ($status_map as $key => $label) {
            $sel = ($r->status === $key) ? ' selected' : '';
            $options .= '<option value="' . esc_attr($key) . '"' . $sel . '>' . esc_html($label[0]) . '</option>';
        }

        // Build reward section if PunktePass is enabled
        $reward_html = '';
        if ($store && !empty($store->repair_punktepass_enabled)) {
            $required_points = intval($store->repair_required_points ?? 4);
            $reward_name = esc_html($store->repair_reward_name ?? '10 Euro Rabatt');
            $reward_value = floatval($store->repair_reward_value ?? 10);
            $reward_type = $store->repair_reward_type ?? 'discount_fixed';

            // Get customer's user_id and points
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$prefix}ppv_users WHERE email = %s LIMIT 1",
                $r->customer_email
            ));

            $total_points = 0;
            if ($user_id) {
                $total_points = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(points), 0) FROM {$prefix}ppv_points WHERE user_id = %d AND store_id = %d",
                    $user_id, $store->id
                ));
            }

            // Check if reward already approved for this repair
            $reward_approved = !empty($r->reward_approved);
            $reward_rejected = !empty($r->reward_rejected);
            $rejection_reason = $r->reward_rejection_reason ?? '';

            // Show reward section if customer has enough points
            if ($total_points >= $required_points && !$reward_approved) {
                $discount_label = PPV_Lang::t('repair_admin_discount_suffix');
                $reward_display = ($reward_type === 'discount_percent')
                    ? $reward_value . '% ' . $discount_label
                    : number_format($reward_value, 2, ',', '.') . ' &euro; ' . $discount_label;

                $badge_class = $reward_rejected ? 'ra-reward-badge-rejected' : 'ra-reward-badge-available';

                // Toggle button (always visible)
                $reward_html = '<div class="ra-reward-toggle-section">'
                    . '<button class="ra-btn-reward-toggle ' . $badge_class . '" data-repair-id="' . intval($r->id) . '">'
                        . '<i class="ri-gift-line"></i> ' . ($reward_rejected ? esc_html(PPV_Lang::t('repair_admin_reward_rejected_label')) : esc_html(PPV_Lang::t('repair_admin_reward_available')))
                    . '</button>'
                    . '<div class="ra-reward-container" data-repair-id="' . intval($r->id) . '">';

                if ($reward_rejected) {
                    // Show rejection info with option to reconsider
                    $reward_html .= '<div class="ra-reward-section ra-reward-rejected">'
                        . '<div class="ra-reward-header"><i class="ri-gift-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_reward_rejected_title')) . '</div>'
                        . '<div class="ra-reward-info">'
                            . '<span class="ra-reward-badge"><i class="ri-star-fill"></i> ' . $total_points . '/' . $required_points . ' ' . esc_html(PPV_Lang::t('repair_admin_points')) . '</span>'
                            . '<span class="ra-reward-name">' . $reward_name . '</span>'
                        . '</div>'
                        . '<div class="ra-reward-rejection-info">'
                            . '<i class="ri-close-circle-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_reason')) . ': ' . esc_html($rejection_reason)
                        . '</div>'
                        . '<div class="ra-reward-actions">'
                            . '<button class="ra-reward-approve" data-repair-id="' . intval($r->id) . '" data-points="' . $required_points . '"><i class="ri-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_reward_still_approve')) . '</button>'
                        . '</div>'
                    . '</div>';
                } else {
                    // Show approve/reject buttons
                    $reward_html .= '<div class="ra-reward-section">'
                        . '<div class="ra-reward-header"><i class="ri-gift-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_reward_redeem')) . '</div>'
                        . '<div class="ra-reward-info">'
                            . '<span class="ra-reward-badge"><i class="ri-star-fill"></i> ' . $total_points . '/' . $required_points . ' ' . esc_html(PPV_Lang::t('repair_admin_points')) . '</span>'
                            . '<span class="ra-reward-name">' . $reward_name . ' (' . $reward_display . ')</span>'
                        . '</div>'
                        . '<div class="ra-reward-actions">'
                            . '<button class="ra-reward-approve" data-repair-id="' . intval($r->id) . '" data-points="' . $required_points . '"><i class="ri-check-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_reward_approve')) . '</button>'
                            . '<button class="ra-reward-reject" data-repair-id="' . intval($r->id) . '"><i class="ri-close-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_reward_reject')) . '</button>'
                        . '</div>'
                    . '</div>';
                }

                $reward_html .= '</div></div>';
            } elseif ($reward_approved) {
                // Show approved status (small badge, no toggle needed)
                $reward_html = '<div class="ra-reward-approved-badge">'
                    . '<i class="ri-check-double-line"></i> ' . $reward_name . ' ' . esc_html(PPV_Lang::t('repair_admin_reward_approved'))
                . '</div>';
            }
        }

        // Invoice badge HTML
        $invoice_badge_html = '';
        if ($has_invoice) {
            $inv_list = esc_html($invoice_numbers);
            $invoice_badge_html = '<div class="ra-invoice-badge"><i class="ri-file-list-3-line"></i> ' . $inv_list . '</div>';
        }

        // Invoice button: different style if invoice already exists
        $inv_btn_class = $has_invoice ? 'ra-btn-invoice ra-btn-invoice-exists' : 'ra-btn-invoice';
        $inv_btn_title = $has_invoice
            ? esc_attr(PPV_Lang::t('repair_admin_invoice_exists') . ': ' . $invoice_numbers)
            : esc_attr(PPV_Lang::t('repair_admin_create_inv_card'));
        $inv_btn_icon = $has_invoice ? 'ri-file-list-3-fill' : 'ri-file-list-3-line';

        // Build detail rows for device section
        $device_details = '';
        if ($imei_html) $device_details .= $imei_html;
        if ($pin_html) $device_details .= $pin_html;
        if ($muster_html) $device_details .= $muster_html;
        if ($acc_html) $device_details .= $acc_html;

        // Device section block
        $device_section = '';
        if ($device_html || $device_details) {
            $device_section = '<div class="ra-card-section ra-card-section-device">'
                . '<div class="ra-card-section-title"><i class="ri-smartphone-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_device_section', 'Gerät')) . '</div>'
                . ($device_html ? '<div class="ra-device-name">' . esc_html($device) . '</div>' : '')
                . $device_details
            . '</div>';
        }

        // Problem section
        $problem_section = '<div class="ra-card-section ra-card-section-problem">'
            . '<div class="ra-card-section-title"><i class="ri-error-warning-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_problem_section', 'Problem')) . '</div>'
            . '<div class="ra-repair-problem">' . $problem . '</div>'
        . '</div>';

        // Custom fields section
        $cf_section = '';
        if ($cf_html) {
            $cf_section = '<div class="ra-card-section ra-card-section-details">'
                . '<div class="ra-card-section-title"><i class="ri-file-list-3-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_details_section', 'Details')) . '</div>'
                . $cf_html
            . '</div>';
        }

        // Badges row (invoice, termin, parts arrived)
        $badges = '';
        if ($invoice_badge_html) $badges .= $invoice_badge_html;
        if (!empty($r->termin_at) && strtotime($r->termin_at) > time()) {
            $badges .= '<div class="ra-termin-badge"><i class="ri-calendar-check-line"></i> ' . date('d.m.Y', strtotime($r->termin_at)) . (date('H:i', strtotime($r->termin_at)) !== '00:00' ? ' ' . date('H:i', strtotime($r->termin_at)) : '') . '</div>';
        }
        $badges_row = $badges ? '<div class="ra-card-badges">' . $badges . '</div>' : '';

        $parts_btn = ($r->status === 'waiting_parts') ? '<button class="ra-btn-parts-arrived" data-repair-id="' . intval($r->id) . '"><i class="ri-checkbox-circle-fill"></i> ' . esc_html(PPV_Lang::t('repair_admin_parts_arrived')) . '</button>' : '';

        return '<div class="ra-repair-card" data-id="' . intval($r->id) . '"'
            . ' data-status="' . esc_attr($r->status) . '"'
            . ' data-name="' . esc_attr($r->customer_name) . '"'
            . ' data-email="' . esc_attr($r->customer_email) . '"'
            . ' data-phone="' . esc_attr($r->customer_phone) . '"'
            . ' data-address="' . esc_attr($r->customer_address) . '"'
            . ' data-brand="' . esc_attr($r->device_brand) . '"'
            . ' data-model="' . esc_attr($r->device_model) . '"'
            . ' data-pin="' . esc_attr($r->device_pattern) . '"'
            . ' data-imei="' . esc_attr($r->device_imei) . '"'
            . ' data-problem="' . esc_attr($r->problem_description) . '"'
            . ' data-date="' . esc_attr($date) . '"'
            . ' data-muster="' . esc_attr($r->muster_image) . '"'
            . ' data-signature="' . esc_attr($r->signature_image) . '"'
            . ' data-accessories="' . esc_attr($r->accessories) . '"'
            . ' data-customfields="' . $cf_data_json . '"'
            . ' data-invoice="' . esc_attr($invoice_numbers) . '"'
            . ' data-reward-approved="' . (int)(!empty($r->reward_approved)) . '"'
            . '>'
            // Header: ID + Status badge + Date
            . '<div class="ra-repair-header">'
                . '<div class="ra-repair-header-left">'
                    . '<div class="ra-repair-id">#' . intval($r->id) . '</div>'
                    . '<div class="ra-repair-date-inline"><i class="ri-time-line"></i> ' . $date . '</div>'
                . '</div>'
                . '<span class="ra-status ' . esc_attr($st[1]) . '">' . esc_html($st[0]) . '</span>'
            . '</div>'
            // Customer section
            . '<div class="ra-card-section ra-card-section-customer">'
                . '<div class="ra-customer-row">'
                    . '<div class="ra-customer-avatar"><i class="ri-user-3-fill"></i></div>'
                    . '<div class="ra-customer-info">'
                        . '<strong>' . esc_html($r->customer_name) . '</strong>'
                        . '<div class="ra-repair-meta">'
                            . ($r->customer_email ? '<span><i class="ri-mail-line"></i> ' . esc_html($r->customer_email) . '</span>' : '')
                            . ($r->customer_phone ? '<span><i class="ri-phone-line"></i> ' . esc_html($r->customer_phone) . '</span>' : '')
                        . '</div>'
                        . $address_html
                    . '</div>'
                . '</div>'
            . '</div>'
            // Device section
            . $device_section
            // Problem section
            . $problem_section
            // Custom fields section
            . $cf_section
            // Tracking section
            . (!empty($r->tracking_token) && $store ? (function() use ($r, $store) {
                $tracking_url = esc_attr(home_url("/formular/{$store->store_slug}/status/{$r->tracking_token}"));
                return '<div class="ra-card-section ra-tracking-section">'
                    . '<div class="ra-card-section-title"><i class="ri-live-line"></i> Live-Tracking</div>'
                    . '<div class="ra-tracking-row">'
                        . '<button type="button" class="ra-tracking-btn ra-tracking-btn-copy" onclick="'
                            . "var u='" . $tracking_url . "';"
                            . "navigator.clipboard?navigator.clipboard.writeText(u):function(){var t=document.createElement('textarea');t.value=u;document.body.appendChild(t);t.select();document.execCommand('copy');document.body.removeChild(t)}();"
                            . "this.innerHTML='<i class=&quot;ri-check-line&quot;></i> Kopiert!';"
                            . "var b=this;setTimeout(function(){b.innerHTML='<i class=&quot;ri-file-copy-line&quot;></i> Link kopieren'},1500)"
                        . '"><i class="ri-file-copy-line"></i> Link kopieren</button>'
                        . '<a href="' . $tracking_url . '" target="_blank" class="ra-tracking-btn ra-tracking-btn-open"><i class="ri-external-link-line"></i> Öffnen</a>'
                    . '</div>'
                . '</div>';
            })() : '')
            // Badges row
            . $badges_row
            // Parts arrived button
            . $parts_btn
            // Updated timestamp (subtle)
            . ($updated ? '<div class="ra-card-updated"><i class="ri-edit-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_last_modified')) . ': ' . $updated . '</div>' : '')
            // Reward
            . $reward_html
            // Comments
            . '<div class="ra-repair-comments-section">'
                . '<button class="ra-btn-comments-toggle" data-repair-id="' . intval($r->id) . '"><i class="ri-chat-3-line"></i> ' . esc_html(PPV_Lang::t('repair_admin_comments')) . '</button>'
                . '<div class="ra-comments-container" style="display:none" data-repair-id="' . intval($r->id) . '">'
                    . '<div class="ra-comments-list"></div>'
                    . '<div class="ra-comment-add">'
                        . '<input type="text" class="ra-comment-input" placeholder="' . esc_attr(PPV_Lang::t('repair_admin_add_comment_ph')) . '">'
                        . '<button class="ra-comment-submit"><i class="ri-send-plane-fill"></i></button>'
                    . '</div>'
                . '</div>'
            . '</div>'
            // Actions toolbar
            . '<div class="ra-repair-actions">'
                . '<div class="ra-actions-left">'
                    . '<button class="ra-btn-print" title="' . esc_attr(PPV_Lang::t('repair_admin_print')) . '"><i class="ri-printer-line"></i></button>'
                    . '<button class="ra-btn-email" title="' . esc_attr(PPV_Lang::t('repair_admin_send_email')) . '"><i class="ri-mail-send-line"></i></button>'
                    . '<button class="ra-btn-resubmit" title="' . esc_attr(PPV_Lang::t('repair_admin_resubmit')) . '"><i class="ri-repeat-line"></i></button>'
                    . '<button class="' . $inv_btn_class . '" title="' . $inv_btn_title . '"><i class="' . $inv_btn_icon . '"></i></button>'
                    . '<button class="ra-btn-angebot" title="' . esc_attr(PPV_Lang::t('repair_admin_create_quote_btn')) . '"><i class="ri-draft-line"></i></button>'
                    . '<button class="ra-btn-delete" title="' . esc_attr(PPV_Lang::t('repair_admin_delete_repair')) . '"><i class="ri-delete-bin-line"></i></button>'
                . '</div>'
                . '<select class="ra-status-select" data-repair-id="' . intval($r->id) . '">' . $options . '</select>'
            . '</div>'
        . '</div>';
    }
}
