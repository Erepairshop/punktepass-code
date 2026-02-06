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

        $pp_enabled = isset($store->repair_punktepass_enabled) ? intval($store->repair_punktepass_enabled) : 1;
        $reward_name = esc_attr($store->repair_reward_name ?? '10 Euro Rabatt');
        $reward_desc = esc_attr($store->repair_reward_description ?? '10 Euro Rabatt auf Ihre nächste Reparatur');
        $required_points = intval($store->repair_required_points ?? 4);
        $form_title = esc_attr($store->repair_form_title ?? 'Reparaturauftrag');
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

        // Count total invoices (all prefixes) for info display
        $inv_total_count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_repair_invoices WHERE store_id = %d AND (doc_type = 'rechnung' OR doc_type IS NULL)",
            $store_id
        ));

        $vat_enabled = isset($store->repair_vat_enabled) ? intval($store->repair_vat_enabled) : 1;
        $vat_rate = floatval($store->repair_vat_rate ?? 19);
        $field_config = json_decode($store->repair_field_config ?? '', true) ?: [];
        $fc_defaults = ['device_brand' => ['enabled' => true, 'label' => 'Marke'], 'device_model' => ['enabled' => true, 'label' => 'Modell'], 'device_imei' => ['enabled' => true, 'label' => 'Seriennummer / IMEI'], 'device_pattern' => ['enabled' => true, 'label' => 'Entsperrcode / PIN'], 'accessories' => ['enabled' => true, 'label' => 'Mitgegebenes Zubehör'], 'customer_phone' => ['enabled' => true, 'label' => 'Telefon'], 'customer_address' => ['enabled' => true, 'label' => 'Adresse'], 'muster_image' => ['enabled' => true, 'label' => 'Entsperrmuster']];
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

        // Custom form options (for different branches)
        $custom_brands = esc_textarea($store->repair_custom_brands ?? '');
        $custom_problems = esc_textarea($store->repair_custom_problems ?? '');
        $custom_accessories = esc_textarea($store->repair_custom_accessories ?? '');
        $success_message = esc_textarea($store->repair_success_message ?? '');
        $opening_hours = esc_attr($store->repair_opening_hours ?? '');
        $terms_url = esc_attr($store->repair_terms_url ?? '');

        // Build repairs HTML
        $repairs_html = '';
        if (empty($recent)) {
            $repairs_html = '<div class="ra-empty"><i class="ri-inbox-line"></i><p>Noch keine Reparaturen. Teilen Sie Ihren Formular-Link!</p></div>';
        } else {
            foreach ($recent as $r) {
                $repairs_html .= self::build_repair_card_html($r, $store);
            }
        }

        echo '<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>' . $store_name . ' - Reparatur Admin</title>
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
.ra-wrap{width:100%;max-width:100%;margin:0 auto;padding:16px 24px 40px;box-sizing:border-box;transform:translateZ(0);backface-visibility:hidden;animation:raFadeIn 0.01s ease-out}
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
.ra-settings .field input[type="color"]{width:100%;padding:12px 14px;border:2px solid #e2e8f0;border-radius:12px;font-size:14px;color:#0f172a;background:#f8fafc;outline:none;transition:all .2s}
.ra-settings .field input:hover{border-color:#cbd5e1}
.ra-settings .field input:focus{border-color:#667eea;background:#fff;box-shadow:0 0 0 4px rgba(102,126,234,0.1)}
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

/* ========== Toolbar ========== */
.ra-toolbar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap}
.ra-search{flex:1;position:relative;min-width:200px}
.ra-search i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:18px}
.ra-search input{width:100%;padding:11px 14px 11px 42px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;color:#1f2937;background:#fff;outline:none;transition:border-color .2s}
.ra-search input:focus{border-color:#667eea}
.ra-search input::placeholder{color:#9ca3af}
.ra-filters select{padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;color:#374151;background:#fff;outline:none;cursor:pointer;appearance:auto;min-width:140px}
.ra-filters select:focus{border-color:#667eea}

/* ========== Repair Cards - Modern ========== */
.ra-repairs{display:grid;grid-template-columns:1fr;gap:14px}
@media(min-width:768px){.ra-repairs{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1200px){.ra-repairs{grid-template-columns:repeat(3,1fr)}}
@media(min-width:1600px){.ra-repairs{grid-template-columns:repeat(4,1fr)}}
.ra-repair-card{background:#fff;border-radius:16px;padding:20px;border:1px solid #e2e8f0;transition:box-shadow .2s,transform .2s;box-shadow:0 1px 3px rgba(0,0,0,0.02)}
.ra-repair-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08);transform:translateY(-2px)}
.ra-repair-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.ra-repair-id{font-size:13px;font-weight:700;color:#6b7280}
.ra-status{display:inline-flex;align-items:center;padding:4px 10px;border-radius:8px;font-size:12px;font-weight:600}
.ra-status-new{background:#dbeafe;color:#1d4ed8}
.ra-status-progress{background:#fef3c7;color:#92400e}
.ra-status-waiting{background:#fce7f3;color:#9d174d}
.ra-status-done{background:#d1fae5;color:#065f46}
.ra-status-delivered{background:#e5e7eb;color:#374151}
.ra-status-cancelled{background:#fecaca;color:#991b1b}
/* Card border colors by status */
.ra-repair-card[data-status="new"]{border-left:4px solid #3b82f6}
.ra-repair-card[data-status="in_progress"]{border-left:4px solid #f59e0b}
.ra-repair-card[data-status="waiting_parts"]{border-left:4px solid #ec4899}
.ra-repair-card[data-status="done"]{border-left:4px solid #10b981}
.ra-repair-card[data-status="delivered"]{border-left:4px solid #6b7280}
.ra-repair-card[data-status="cancelled"]{border-left:4px solid #ef4444}
.ra-repair-body{margin-bottom:12px}
.ra-repair-customer{margin-bottom:6px}
.ra-repair-customer strong{font-size:15px;color:#111827;display:block}
.ra-repair-meta{font-size:13px;color:#6b7280}
.ra-repair-device{font-size:13px;color:#374151;margin-bottom:4px;display:flex;align-items:center;gap:4px}
.ra-repair-pin{font-size:12px;color:#667eea;font-weight:600;margin-bottom:4px;display:flex;align-items:center;gap:4px}
.ra-repair-address{font-size:12px;color:#6b7280;margin-top:4px;display:flex;align-items:center;gap:4px}
.ra-repair-muster{margin:8px 0;padding:8px;background:#f9fafb;border-radius:8px;display:inline-block}
.ra-repair-muster img{width:80px;height:80px;border-radius:4px;border:1px solid #e5e7eb;cursor:pointer}
.ra-repair-muster img:hover{transform:scale(2);position:relative;z-index:10;box-shadow:0 4px 12px rgba(0,0,0,0.15)}
.ra-repair-problem{font-size:13px;color:#6b7280;margin-bottom:6px;line-height:1.5}
.ra-repair-date{font-size:12px;color:#9ca3af;display:flex;align-items:center;gap:4px}
.ra-repair-actions{display:flex;align-items:center;gap:8px}
.ra-status-select{padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;color:#374151;background:#fff;outline:none;cursor:pointer;appearance:auto;width:100%;max-width:200px}
.ra-status-select:focus{border-color:#667eea}
.ra-btn-resubmit{padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid #c7d2fe;background:#eef2ff;color:#667eea;display:inline-flex;align-items:center;gap:4px;transition:all .2s;white-space:nowrap}
.ra-btn-resubmit:hover{background:#667eea;color:#fff;border-color:#667eea}
.ra-btn-invoice{padding:6px 10px;border-radius:8px;font-size:14px;cursor:pointer;border:1px solid #bbf7d0;background:#f0fdf4;color:#16a34a;display:inline-flex;align-items:center;justify-content:center;transition:all .2s}
.ra-btn-invoice:hover{background:#16a34a;color:#fff;border-color:#16a34a}
.ra-btn-delete{padding:6px 10px;border-radius:8px;font-size:14px;cursor:pointer;border:1px solid #fecaca;background:#fef2f2;color:#dc2626;display:inline-flex;align-items:center;justify-content:center;transition:all .2s}
.ra-btn-delete:hover{background:#dc2626;color:#fff;border-color:#dc2626}
.ra-btn-print{padding:6px 10px;border-radius:8px;font-size:14px;cursor:pointer;border:1px solid #e5e7eb;background:#f9fafb;color:#374151;display:inline-flex;align-items:center;justify-content:center;transition:all .2s}
.ra-btn-print:hover{background:#374151;color:#fff;border-color:#374151}
.ra-btn-email{padding:6px 10px;border-radius:8px;font-size:14px;cursor:pointer;border:1px solid #bfdbfe;background:#eff6ff;color:#2563eb;display:inline-flex;align-items:center;justify-content:center;transition:all .2s}
.ra-btn-email:hover{background:#2563eb;color:#fff;border-color:#2563eb}

/* ========== Reward Section ========== */
.ra-reward-toggle-section{margin:12px 0}
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
.ra-reward-approved-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#d1fae5;color:#065f46;font-size:12px;font-weight:600;border-radius:8px;margin:8px 0}

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
/* ========== Field Config ========== */
.ra-field-config{display:flex;flex-direction:column;gap:10px}
.ra-field-row{display:flex;align-items:center;gap:12px;padding:10px 14px;background:#fafafa;border-radius:10px;border:1px solid #f0f0f0}
.ra-field-row label.ra-fc-toggle{display:flex;align-items:center;gap:8px;flex-shrink:0;cursor:pointer}
.ra-field-row input[type="checkbox"]{width:18px;height:18px;accent-color:#667eea;cursor:pointer}
.ra-field-row input[type="text"]{flex:1;padding:8px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;color:#1f2937;background:#fff;outline:none;min-width:0}
.ra-field-row input[type="text"]:focus{border-color:#667eea}
.ra-fc-name{font-size:12px;font-weight:600;color:#6b7280;min-width:60px}
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
            <button class="ra-btn-icon" id="ra-settings-toggle-btn" title="Einstellungen">
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
                    <h3 style="font-size:18px;font-weight:700;margin-bottom:8px">Formularlimit erreicht!</h3>
                    <p style="font-size:14px;opacity:0.9;margin-bottom:16px">Ihr Formular ist f&uuml;r Kunden nicht mehr verf&uuml;gbar. Upgraden Sie jetzt auf Premium f&uuml;r unbegrenzte Formulare.</p>
                    <a href="/checkout" class="ra-btn" style="background:#fff;color:#dc2626;padding:12px 24px;font-size:15px">
                        <i class="ri-vip-crown-line"></i> Jetzt upgraden - 39,00 &euro; / Monat
                    </a>
                </div>';
            } else {
                echo '<div class="ra-usage">
            <div class="ra-usage-info">
                <span>Formulare: ' . intval($store->repair_form_count) . ' / ' . intval($store->repair_form_limit) . '</span>';
                if ($limit_pct >= 80) {
                    echo '<span class="ra-usage-warn"><a href="/checkout" style="color:#dc2626;text-decoration:underline">Jetzt upgraden</a></span>';
                }
                echo '</div>
            <div class="ra-usage-bar">
                <div class="ra-usage-fill" style="width:' . $limit_pct . '%"></div>
            </div>
        </div>';
            }
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

        <!-- Settings Tabs Navigation -->
        <div class="ra-settings-tabs" id="ra-settings-tabs">
            <button type="button" class="ra-settings-tab active" data-panel="general"><i class="ri-store-2-line"></i> Allgemein</button>
            <button type="button" class="ra-settings-tab" data-panel="form"><i class="ri-edit-line"></i> Formular</button>
            <button type="button" class="ra-settings-tab" data-panel="invoice"><i class="ri-file-list-3-line"></i> Rechnungen</button>
            <button type="button" class="ra-settings-tab" data-panel="email"><i class="ri-mail-line"></i> E-Mails</button>
            <button type="button" class="ra-settings-tab" data-panel="punktepass"><i class="ri-star-line"></i> PunktePass</button>
            <button type="button" class="ra-settings-tab" data-panel="abo"><i class="ri-vip-crown-line"></i> Abo</button>
        </div>

        <form id="ra-settings-form">
            <!-- ==================== PANEL: Allgemein ==================== -->
            <div class="ra-settings-panel active" data-panel="general">
                <h4><i class="ri-store-2-line"></i> Gesch&auml;ftsdaten</h4>
                <div class="ra-settings-grid">
                <div class="field">
                    <label>Firmenname</label>
                    <input type="text" name="name" value="' . esc_attr($store->name) . '">
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
                    <label>Login E-Mail <small style="color:#6b7280">(f&uuml;r Anmeldung)</small></label>
                    <input type="email" name="email" value="' . esc_attr($store->email) . '" required>
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


                <h4><i class="ri-file-list-3-line"></i> Impressum / Rechtliches</h4>
            <div class="ra-settings-grid">
                <div class="field">
                    <label>Firmenname (Impressum)</label>
                    <input type="text" name="repair_company_name" value="' . esc_attr($store->repair_company_name) . '">
                </div>
                <div class="field">
                    <label>Inhaber (Impressum)</label>
                    <input type="text" name="repair_owner_name" value="' . esc_attr($store->repair_owner_name) . '">
                </div>
                <div class="field">
                    <label>Adresse</label>
                    <input type="text" name="repair_company_address" value="' . esc_attr($store->repair_company_address) . '" placeholder="Musterstraße 1, 12345 Stadt">
                </div>
                <div class="field">
                    <label>Telefon</label>
                    <input type="text" name="repair_company_phone" value="' . esc_attr($store->repair_company_phone) . '" placeholder="+49 123 456789">
                </div>
                <div class="field">
                    <label>E-Mail</label>
                    <input type="text" name="repair_company_email" value="' . esc_attr($store->repair_company_email) . '" placeholder="info@beispiel.de">
                </div>
                <div class="field">
                    <label>USt-IdNr.</label>
                    <input type="text" name="repair_tax_id" value="' . esc_attr($store->repair_tax_id) . '">
                </div>
            </div>
            </div><!-- END PANEL: general -->

            <!-- ==================== PANEL: PunktePass ==================== -->
            <div class="ra-settings-panel" data-panel="punktepass">
                <h4><i class="ri-star-line"></i> PunktePass Integration</h4>
            <div class="ra-toggle">
                <label class="ra-toggle-switch">
                    <input type="checkbox" id="ra-pp-toggle" ' . ($pp_enabled ? 'checked' : '') . '>
                    <span class="ra-toggle-slider"></span>
                </label>
                <div>
                    <div class="ra-toggle-label">PunktePass aktivieren</div>
                    <div class="ra-toggle-desc">Kunden sammeln Bonuspunkte bei jeder Formularabgabe</div>
                </div>
            </div>
            <input type="hidden" name="repair_punktepass_enabled" id="ra-pp-enabled-val" value="' . $pp_enabled . '">

            <div id="ra-pp-settings" ' . ($pp_enabled ? '' : 'style="display:none"') . '>
                <div class="ra-settings-grid">
                    <div class="field">
                        <label>Punkte pro Formular</label>
                        <input type="number" name="repair_points_per_form" value="' . intval($store->repair_points_per_form) . '" min="0" max="50">
                    </div>
                    <div class="field">
                        <label>Ben&ouml;tigte Punkte f&uuml;r Belohnung</label>
                        <input type="number" name="repair_required_points" value="' . $required_points . '" min="1" max="100">
                    </div>
                    <div class="field">
                        <label>Belohnung</label>
                        <input type="text" name="repair_reward_name" value="' . $reward_name . '" placeholder="z.B. 10 Euro Rabatt">
                    </div>
                    <div class="field">
                        <label>Belohnungsbeschreibung</label>
                        <input type="text" name="repair_reward_description" value="' . $reward_desc . '" placeholder="z.B. 10 Euro Rabatt auf Ihre n&auml;chste Reparatur">
                    </div>
                </div>
                <hr style="border:none;border-top:1px solid #f0f0f0;margin:16px 0">
                <label style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:8px">BELOHNUNG BEI EINL&Ouml;SUNG</label>
                <div class="ra-settings-grid">
                    <div class="field">
                        <label>Belohnungstyp</label>
                        <select name="repair_reward_type" class="ra-status-select" style="max-width:none" id="ra-reward-type">
                            <option value="discount_fixed" ' . ($reward_type === 'discount_fixed' ? 'selected' : '') . '>Fester Rabatt (&euro;)</option>
                            <option value="discount_percent" ' . ($reward_type === 'discount_percent' ? 'selected' : '') . '>Prozent-Rabatt (%)</option>
                            <option value="free_product" ' . ($reward_type === 'free_product' ? 'selected' : '') . '>Gratis Produkt</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Wert</label>
                        <input type="number" name="repair_reward_value" value="' . $reward_value . '" min="0" step="0.01" placeholder="10.00">
                    </div>
                    <div class="field" id="ra-reward-product-field" ' . ($reward_type === 'free_product' ? '' : 'style="display:none"') . '>
                        <label>Gratis-Produkt Name</label>
                        <input type="text" name="repair_reward_product" value="' . $reward_product . '" placeholder="z.B. Panzerglasfolie">
                    </div>
                </div>
            </div>
            </div><!-- END PANEL: punktepass -->

            <!-- ==================== PANEL: Formular ==================== -->
            <div class="ra-settings-panel" data-panel="form">
                <h4><i class="ri-edit-line"></i> Formular anpassen</h4>
            <div class="ra-settings-grid">
                <div class="field">
                    <label>Formulartitel</label>
                    <input type="text" name="repair_form_title" value="' . $form_title . '" placeholder="Reparaturauftrag">
                </div>
                <div class="field">
                    <label>Untertitel</label>
                    <input type="text" name="repair_form_subtitle" value="' . $form_subtitle . '" placeholder="Optional">
                </div>
                <div class="field">
                    <label>Branche / Service-Typ</label>
                    <select name="repair_service_type" class="ra-status-select" style="max-width:none">
                        <option value="Allgemein" ' . ($service_type === 'Allgemein' ? 'selected' : '') . '>Allgemein</option>
                        <option value="Handy-Reparatur" ' . ($service_type === 'Handy-Reparatur' ? 'selected' : '') . '>Handy-Reparatur</option>
                        <option value="Computer-Reparatur" ' . ($service_type === 'Computer-Reparatur' ? 'selected' : '') . '>Computer-Reparatur</option>
                        <option value="Fahrrad-Reparatur" ' . ($service_type === 'Fahrrad-Reparatur' ? 'selected' : '') . '>Fahrrad-Reparatur</option>
                        <option value="KFZ-Reparatur" ' . ($service_type === 'KFZ-Reparatur' ? 'selected' : '') . '>KFZ-Reparatur</option>
                        <option value="Schmuck-Reparatur" ' . ($service_type === 'Schmuck-Reparatur' ? 'selected' : '') . '>Schmuck-Reparatur</option>
                        <option value="Elektro-Reparatur" ' . ($service_type === 'Elektro-Reparatur' ? 'selected' : '') . '>Elektro-Reparatur</option>
                        <option value="Uhren-Reparatur" ' . ($service_type === 'Uhren-Reparatur' ? 'selected' : '') . '>Uhren-Reparatur</option>
                        <option value="Schuh-Reparatur" ' . ($service_type === 'Schuh-Reparatur' ? 'selected' : '') . '>Schuh-Reparatur</option>
                        <option value="Sonstige" ' . ($service_type === 'Sonstige' ? 'selected' : '') . '>Sonstige</option>
                    </select>
                </div>
            </div>

            <div style="margin-top:16px">
                <label style="display:block;font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px">FORMULARFELDER</label>
                <div class="ra-field-config" id="ra-field-config">';

// Field config rows
$fc_field_names = [
    'device_brand' => 'Marke',
    'device_model' => 'Modell',
    'device_imei' => 'Seriennummer',
    'device_pattern' => 'Entsperrcode',
    'muster_image' => 'Entsperrmuster',
    'accessories' => 'Zubeh&ouml;r',
    'customer_phone' => 'Telefon',
    'customer_address' => 'Adresse',
];
foreach ($fc_field_names as $fk => $fn) {
    $fc = $field_config[$fk] ?? $fc_defaults[$fk];
    $checked = !empty($fc['enabled']) ? 'checked' : '';
    $label = esc_attr($fc['label'] ?? $fc_defaults[$fk]['label']);
    echo '<div class="ra-field-row">
        <label class="ra-fc-toggle"><input type="checkbox" data-field="' . $fk . '" ' . $checked . '></label>
        <span class="ra-fc-name">' . $fn . '</span>
        <input type="text" data-field-label="' . $fk . '" value="' . $label . '" placeholder="Feldbezeichnung">
    </div>';
}

echo '          </div>
            </div>


                <h4><i class="ri-settings-4-line"></i> Branchenspezifische Optionen</h4>
            <p style="font-size:12px;color:#6b7280;margin-bottom:16px">Passen Sie das Formular f&uuml;r Ihre Branche an (Handy, Computer, Fahrrad, KFZ, Schmuck, Uhren, Schuhe, etc.)</p>

            <div class="field" style="margin-bottom:16px">
                <label>Marken / Hersteller <span style="color:#9ca3af;font-weight:normal">(pro Zeile eine)</span></label>
                <textarea name="repair_custom_brands" rows="4" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;font-family:inherit;resize:vertical" placeholder="Apple&#10;Samsung&#10;Huawei&#10;Xiaomi&#10;...">' . $custom_brands . '</textarea>
                <p style="font-size:11px;color:#9ca3af;margin-top:4px">Leer lassen = freie Texteingabe im Formular</p>
            </div>

            <div class="field" style="margin-bottom:16px">
                <label>H&auml;ufige Probleme / Reparaturarten <span style="color:#9ca3af;font-weight:normal">(pro Zeile eine)</span></label>
                <textarea name="repair_custom_problems" rows="5" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;font-family:inherit;resize:vertical" placeholder="Display gebrochen&#10;Akku tauschen&#10;Wasserschaden&#10;Ladebuchse defekt&#10;...">' . $custom_problems . '</textarea>
                <p style="font-size:11px;color:#9ca3af;margin-top:4px">Leer lassen = nur Freitext. Wird als Schnellauswahl-Buttons im Formular angezeigt</p>
            </div>

            <div class="field" style="margin-bottom:16px">
                <label>Zubeh&ouml;r-Optionen <span style="color:#9ca3af;font-weight:normal">(pro Zeile eine)</span></label>
                <textarea name="repair_custom_accessories" rows="4" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;font-family:inherit;resize:vertical" placeholder="Ladekabel&#10;H&uuml;lle / Case&#10;Schl&uuml;ssel&#10;Originalkarton&#10;...">' . $custom_accessories . '</textarea>
                <p style="font-size:11px;color:#9ca3af;margin-top:4px">Leer lassen = Standard-Zubeh&ouml;r (Ladekabel, H&uuml;lle, Schl&uuml;ssel, Sonstiges)</p>
            </div>

            <div class="ra-settings-grid" style="margin-bottom:16px">
                <div class="field">
                    <label>&Ouml;ffnungszeiten <span style="color:#9ca3af;font-weight:normal">(optional)</span></label>
                    <input type="text" name="repair_opening_hours" value="' . $opening_hours . '" placeholder="Mo-Fr 9-18, Sa 10-14">
                    <p style="font-size:11px;color:#9ca3af;margin-top:4px">Wird im Formular-Header angezeigt</p>
                </div>
                <div class="field">
                    <label>Eigene AGB-URL <span style="color:#9ca3af;font-weight:normal">(optional)</span></label>
                    <input type="text" name="repair_terms_url" value="' . $terms_url . '" placeholder="https://ihre-website.de/agb">
                    <p style="font-size:11px;color:#9ca3af;margin-top:4px">Leer = automatisch generierte AGB</p>
                </div>
            </div>

            <div class="field" style="margin-bottom:16px">
                <label>Erfolgsmeldung nach Absenden <span style="color:#9ca3af;font-weight:normal">(optional)</span></label>
                <textarea name="repair_success_message" rows="3" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;font-family:inherit;resize:vertical" placeholder="Vielen Dank! Wir melden uns innerhalb von 24 Stunden bei Ihnen.">' . $success_message . '</textarea>
                <p style="font-size:11px;color:#9ca3af;margin-top:4px">Leer = Standard-Meldung</p>
            </div>

            </div><!-- END PANEL: form -->

            <!-- ==================== PANEL: Rechnungen ==================== -->
            <div class="ra-settings-panel" data-panel="invoice">
                <h4><i class="ri-file-list-3-line"></i> Rechnungsnummern</h4>
            <div class="ra-settings-grid">
                <div class="field">
                    <label>Rechnungsnr. Pr&auml;fix</label>
                    <input type="text" name="repair_invoice_prefix" id="ra-inv-prefix" value="' . $inv_prefix . '" placeholder="RE-" oninput="updateInvPreview()">
                </div>
                <div class="field">
                    <label>N&auml;chste Rechnungsnr.</label>
                    <input type="number" name="repair_invoice_next_number" id="ra-inv-next" value="' . $inv_next . '" min="1" oninput="updateInvPreview()">
                </div>
            </div>
            <div style="margin-top:12px;padding:12px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                    <div>
                        <div style="font-size:12px;color:#0369a1;font-weight:500">Vorschau n&auml;chste Rechnung</div>
                        <div style="font-size:18px;font-weight:600;color:#0c4a6e;margin-top:2px" id="ra-inv-preview">' . esc_html($inv_prefix . str_pad($inv_next, 4, '0', STR_PAD_LEFT)) . '</div>
                    </div>
                    ' . ($inv_detected_max > 0 && $inv_suggested_next > $inv_next ? '
                    <div style="text-align:right">
                        <div style="font-size:11px;color:#6b7280">H&ouml;chste mit "' . esc_html($inv_prefix) . '": <strong>' . esc_html($inv_detected_max) . '</strong></div>
                        <button type="button" onclick="document.getElementById(\'ra-inv-next\').value=' . $inv_suggested_next . ';updateInvPreview()" style="margin-top:4px;padding:6px 12px;background:#0ea5e9;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer">&Uuml;bernehmen: ' . $inv_suggested_next . '</button>
                    </div>' : '') . '
                </div>
                ' . ($inv_total_count > 0 ? '<div style="margin-top:8px;padding-top:8px;border-top:1px solid #bae6fd;font-size:11px;color:#6b7280">Rechnungen gesamt: <strong>' . $inv_total_count . '</strong>' . ($inv_detected_max == 0 && count($all_invoice_numbers) == 0 ? ' (keine mit Pr&auml;fix "' . esc_html($inv_prefix) . '")' : ' (' . count($all_invoice_numbers) . ' mit Pr&auml;fix "' . esc_html($inv_prefix) . '")') . '</div>' : '') . '
            </div>
            <script>
            function updateInvPreview(){
                var p=document.getElementById("ra-inv-prefix").value||"RE-";
                var n=parseInt(document.getElementById("ra-inv-next").value)||1;
                document.getElementById("ra-inv-preview").textContent=p+String(n).padStart(4,"0");
            }
            </script>


                <h4><i class="ri-percent-line"></i> Umsatzsteuer (MwSt)</h4>
            <div class="ra-toggle">
                <label class="ra-toggle-switch">
                    <input type="checkbox" id="ra-vat-toggle" ' . ($vat_enabled ? 'checked' : '') . '>
                    <span class="ra-toggle-slider"></span>
                </label>
                <div>
                    <div class="ra-toggle-label">Umsatzsteuerpflichtig</div>
                    <div class="ra-toggle-desc">Deaktivieren f&uuml;r Kleinunternehmer gem. &sect;19 UStG</div>
                </div>
            </div>
            <input type="hidden" name="repair_vat_enabled" id="ra-vat-enabled-val" value="' . $vat_enabled . '">
            <div id="ra-vat-settings" ' . ($vat_enabled ? '' : 'style="display:none"') . '>
                <div class="ra-settings-grid">
                    <div class="field">
                        <label>MwSt-Satz (%)</label>
                        <input type="number" name="repair_vat_rate" value="' . $vat_rate . '" min="0" max="100" step="0.01" placeholder="19.00">
                    </div>
                </div>
            </div>

                <h4 style="margin-top:24px"><i class="ri-file-text-line"></i> Steuerdaten</h4>
            <div class="ra-settings-grid">
                <div class="field">
                    <label>Steuernummer</label>
                    <input type="text" name="repair_steuernummer" value="' . $steuernummer . '" placeholder="z.B. 109/206/40309">
                </div>
                <div class="field">
                    <label>Webseite</label>
                    <input type="text" name="repair_website_url" value="' . $website_url . '" placeholder="www.example.de">
                </div>
            </div>

                <h4 style="margin-top:24px"><i class="ri-bank-line"></i> Bankverbindung</h4>
            <p style="font-size:12px;color:#6b7280;margin-bottom:12px">Wird im Fu&szlig;bereich der Rechnung angezeigt</p>
            <div class="ra-settings-grid">
                <div class="field">
                    <label>Bank Name</label>
                    <input type="text" name="repair_bank_name" value="' . $bank_name . '" placeholder="z.B. Sparkasse">
                </div>
                <div class="field">
                    <label>IBAN</label>
                    <input type="text" name="repair_bank_iban" value="' . $bank_iban . '" placeholder="DE...">
                </div>
                <div class="field">
                    <label>BIC</label>
                    <input type="text" name="repair_bank_bic" value="' . $bank_bic . '" placeholder="z.B. BYLADEM1DLG">
                </div>
            </div>

                <h4 style="margin-top:24px"><i class="ri-paypal-line"></i> PayPal</h4>
            <div class="field">
                <label>PayPal E-Mail / Adresse</label>
                <input type="text" name="repair_paypal_email" value="' . $paypal_email . '" placeholder="paypal@example.de">
            </div>

            </div><!-- END PANEL: invoice -->

            <!-- ==================== PANEL: E-Mail ==================== -->
            <div class="ra-settings-panel" data-panel="email">
                <h4><i class="ri-mail-line"></i> Rechnungs-E-Mail Vorlage</h4>
            <p style="font-size:12px;color:#6b7280;margin-bottom:12px">Wird verwendet wenn Sie Rechnungen per E-Mail versenden. Platzhalter: {customer_name}, {invoice_number}, {invoice_date}, {total}, {company_name}</p>
            <div class="field" style="margin-bottom:12px">
                <label>Betreff</label>
                <input type="text" name="repair_invoice_email_subject" value="' . $email_subject . '" placeholder="Ihre Rechnung {invoice_number}">
            </div>
            <div class="field">
                <label>Nachricht</label>
                <textarea name="repair_invoice_email_body" rows="6" style="width:100%;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-family:inherit;resize:vertical">' . $email_body . '</textarea>
            </div>


                <h4><i class="ri-notification-3-line"></i> Status-Benachrichtigungen</h4>
            <p style="font-size:12px;color:#6b7280;margin-bottom:12px">Kunden automatisch per E-Mail informieren wenn sich der Reparatur-Status &auml;ndert.</p>
            <div class="ra-toggle" style="margin-bottom:16px">
                <label class="ra-toggle-switch">
                    <input type="checkbox" name="repair_status_notify_enabled" value="1" ' . ($status_notify_enabled ? 'checked' : '') . '>
                    <span class="ra-toggle-slider"></span>
                </label>
                <div>
                    <strong>Status-Benachrichtigungen aktivieren</strong>
                    <div style="font-size:12px;color:#6b7280">E-Mails bei Status&auml;nderungen versenden</div>
                </div>
            </div>
            <div class="field" style="margin-bottom:8px">
                <label style="margin-bottom:8px;display:block">Bei diesen Status benachrichtigen:</label>
                <div style="display:flex;flex-wrap:wrap;gap:12px">
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="notify_in_progress" value="1" ' . (in_array('in_progress', $notify_statuses_arr) ? 'checked' : '') . ' style="width:16px;height:16px">
                        <span style="color:#d97706"><i class="ri-loader-4-line"></i></span> In Bearbeitung
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="notify_waiting_parts" value="1" ' . (in_array('waiting_parts', $notify_statuses_arr) ? 'checked' : '') . ' style="width:16px;height:16px">
                        <span style="color:#ec4899"><i class="ri-time-line"></i></span> Wartet auf Teile
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="notify_done" value="1" ' . (in_array('done', $notify_statuses_arr) ? 'checked' : '') . ' style="width:16px;height:16px">
                        <span style="color:#059669"><i class="ri-checkbox-circle-line"></i></span> Fertig
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="notify_delivered" value="1" ' . (in_array('delivered', $notify_statuses_arr) ? 'checked' : '') . ' style="width:16px;height:16px">
                        <span style="color:#6b7280"><i class="ri-truck-line"></i></span> Abgeholt
                    </label>
                </div>
            </div>

            </div><!-- END PANEL: email -->

            <!-- ==================== PANEL: Abo ==================== -->
            <div class="ra-settings-panel" data-panel="abo">
                <h4><i class="ri-vip-crown-line"></i> Abo &amp; Zahlung</h4>
            <div class="ra-abo-box" style="background:#fafafa;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px">
                    <div>
                        <div style="font-size:13px;color:#6b7280;margin-bottom:4px">Status</div>
                        <div style="font-size:16px;font-weight:600;color:#111827">';

        // Subscription status display
        $sub_status = $store->subscription_status ?? 'trial';
        $sub_expires = $store->subscription_expires_at ?? null;
        $is_expired = $sub_expires && strtotime($sub_expires) < time();
        $payment_method = $store->payment_method ?? '';

        // Active subscription = either repair_premium=1 OR subscription_status=active with valid date
        if (($is_premium || $sub_status === 'active') && !$is_expired) {
            echo '<span style="color:#059669"><i class="ri-checkbox-circle-line"></i> Premium aktiv</span>';
        } elseif ($sub_status === 'pending_payment') {
            echo '<span style="color:#d97706"><i class="ri-time-line"></i> Zahlung ausstehend</span>';
        } elseif ($sub_status === 'canceled') {
            echo '<span style="color:#dc2626"><i class="ri-close-circle-line"></i> Gekündigt</span>';
        } elseif ($is_expired) {
            echo '<span style="color:#dc2626"><i class="ri-error-warning-line"></i> Abgelaufen</span>';
        } else {
            echo '<span style="color:#6b7280"><i class="ri-information-line"></i> ' . ucfirst($sub_status) . '</span>';
        }

        echo '</div>
                    </div>
                    <div>
                        <div style="font-size:13px;color:#6b7280;margin-bottom:4px">Formularlimit</div>
                        <div style="font-size:16px;font-weight:600;color:#111827">';

        if ($is_premium || ($sub_status === 'active' && !$is_expired)) {
            echo '<span style="color:#059669"><i class="ri-infinity-line"></i> Unbegrenzt</span>';
        } else {
            echo intval($store->repair_form_count) . ' / ' . intval($store->repair_form_limit);
        }

        echo '</div>
                    </div>';

        if ($sub_expires) {
            echo '<div>
                        <div style="font-size:13px;color:#6b7280;margin-bottom:4px">' . ($is_expired ? 'Abgelaufen am' : 'Gültig bis') . '</div>
                        <div style="font-size:16px;font-weight:600;color:' . ($is_expired ? '#dc2626' : '#111827') . '">' . date('d.m.Y', strtotime($sub_expires)) . '</div>
                    </div>';
        }

        if ($payment_method) {
            echo '<div>
                        <div style="font-size:13px;color:#6b7280;margin-bottom:4px">Zahlungsart</div>
                        <div style="font-size:16px;font-weight:600;color:#111827">';
            if ($payment_method === 'paypal') {
                echo '<i class="ri-paypal-line"></i> PayPal';
            } elseif ($payment_method === 'bank_transfer') {
                echo '<i class="ri-bank-line"></i> Überweisung';
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
                <i class="ri-vip-crown-line"></i> Jetzt upgraden - 39,00 € / Monat (netto)
            </a>';
        }

        // Cancellation section for active subscription users
        if ($has_active_sub && $sub_status !== 'canceled') {
            echo '<div class="ra-kuendigung" style="margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb">
                <div style="font-size:14px;font-weight:600;color:#374151;margin-bottom:8px"><i class="ri-close-circle-line"></i> Abo kündigen</div>
                <p style="font-size:13px;color:#6b7280;margin-bottom:12px">Bei Kündigung bleibt Ihr Abo bis zum Ende der bezahlten Periode aktiv.</p>
                <button type="button" class="ra-btn ra-btn-outline" style="color:#dc2626;border-color:#fecaca" id="ra-cancel-sub-btn">
                    <i class="ri-close-line"></i> Abo zum Laufzeitende kündigen
                </button>
            </div>';
        }

        echo '
            </div><!-- END PANEL: abo -->

            <!-- Save button visible on all panels -->
            <div class="ra-settings-save" style="padding:20px 24px;background:#f8fafc;border-top:1px solid #e2e8f0">
                <button type="submit" class="ra-btn ra-btn-primary ra-btn-full">
                    <i class="ri-save-line"></i> Einstellungen speichern
                </button>
            </div>
        </form>

        <!-- Legal pages -->
        <div class="ra-legal-links" style="padding:0 24px 24px">
            <h4>Automatisch generierte Seiten</h4>
            <a href="/formular/' . $store_slug . '/datenschutz" target="_blank"><i class="ri-shield-check-line"></i> Datenschutz</a>
            <a href="/formular/' . $store_slug . '/agb" target="_blank"><i class="ri-file-text-line"></i> AGB</a>
            <a href="/formular/' . $store_slug . '/impressum" target="_blank"><i class="ri-building-line"></i> Impressum</a>
        </div>

        <!-- PunktePass admin -->
        <div class="ra-pp-link" style="display:flex;gap:8px;align-items:center">
            <a href="/qr-center" class="ra-btn ra-btn-outline">
                <i class="ri-qr-code-line"></i> PunktePass Admin &ouml;ffnen
            </a>
            <button class="ra-btn ra-btn-outline" style="color:#dc2626;border-color:#fecaca" onclick="if(confirm(\'Wirklich abmelden?\')){var fd=new FormData();fd.append(\'action\',\'ppv_repair_logout\');fetch(\'' . esc_url($ajax_url) . '\',{method:\'POST\',body:fd,credentials:\'same-origin\'}).finally(function(){window.location.href=\'/formular/admin/login\';})}">
                <i class="ri-logout-box-r-line"></i> Abmelden
            </button>
        </div>
    </div>';

        // Tabs: Reparaturen | Rechnung/Angebot | Kunden | Feedback
        echo '<div class="ra-tabs">
        <div class="ra-tab active" data-tab="repairs"><i class="ri-tools-line"></i> Reparaturen</div>
        <div class="ra-tab" data-tab="invoices"><i class="ri-file-list-3-line"></i> Rechnung / Angebot</div>
        <div class="ra-tab" data-tab="customers"><i class="ri-user-3-line"></i> Kunden</div>
        <div class="ra-tab" data-tab="feedback"><i class="ri-feedback-line"></i> Feedback</div>
    </div>';

        // ========== TAB: Reparaturen ==========
        echo '<div class="ra-tab-content active" id="ra-tab-repairs">';

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

        echo '</div>'; // end ra-tab-repairs

        // ========== TAB: Rechnungen ==========
        echo '<div class="ra-tab-content" id="ra-tab-invoices">';

        // Filters + New Invoice/Angebot buttons
        echo '<div class="ra-inv-filters">
            <button class="ra-btn ra-btn-primary ra-btn-sm" id="ra-new-invoice-btn">
                <i class="ri-add-line"></i> Neue Rechnung
            </button>
            <button class="ra-btn ra-btn-sm" id="ra-new-angebot-btn" style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0">
                <i class="ri-add-line"></i> Neues Angebot
            </button>
            <select id="ra-inv-type-filter" class="ra-input" style="padding:8px 12px;font-size:13px;min-width:120px">
                <option value="">Alle Typen</option>
                <option value="rechnung">Rechnungen</option>
                <option value="angebot">Angebote</option>
            </select>
            <div class="field">
                <label>Von</label>
                <input type="date" id="ra-inv-from">
            </div>
            <div class="field">
                <label>Bis</label>
                <input type="date" id="ra-inv-to">
            </div>
            <button class="ra-btn ra-btn-outline ra-btn-sm" id="ra-inv-filter-btn">
                <i class="ri-filter-line"></i> Filtern
            </button>
            <div style="position:relative;display:inline-block">
                <button class="ra-btn ra-btn-outline ra-btn-sm" id="ra-inv-export-btn">
                    <i class="ri-download-2-line"></i> Export <i class="ri-arrow-down-s-line"></i>
                </button>
                <div id="ra-inv-export-dropdown" style="display:none;position:absolute;top:100%;left:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:220px;z-index:100;margin-top:4px">
                    <div style="padding:8px 0">
                        <div style="padding:6px 12px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">Buchhalter-Formate</div>
                        <a href="#" class="ra-inv-export-opt" data-format="datev" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-text-line" style="margin-right:8px;color:#8b5cf6"></i>DATEV-Export (.csv)</a>
                        <a href="#" class="ra-inv-export-opt" data-format="csv" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-excel-2-line" style="margin-right:8px;color:#059669"></i>CSV-Export</a>
                        <a href="#" class="ra-inv-export-opt" data-format="excel" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-excel-line" style="margin-right:8px;color:#217346"></i>Excel-Export (.xlsx)</a>
                        <div style="border-top:1px solid #e5e7eb;margin:6px 0"></div>
                        <div style="padding:6px 12px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">Dokumente</div>
                        <a href="#" class="ra-inv-export-opt" data-format="pdf" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-pdf-line" style="margin-right:8px;color:#dc2626"></i>PDF-Sammlung (.zip)</a>
                        <a href="#" class="ra-inv-export-opt" data-format="json" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-code-s-slash-line" style="margin-right:8px;color:#3b82f6"></i>JSON-Export</a>
                    </div>
                </div>
            </div>
            <button class="ra-btn ra-btn-outline ra-btn-sm" id="ra-billbee-import-btn" style="margin-left:auto;background:#fff7ed;color:#ea580c;border-color:#fed7aa">
                <i class="ri-upload-cloud-line"></i> Billbee Import
            </button>
        </div>';

        // Summary toggle button
        echo '<button class="ra-btn ra-btn-outline ra-btn-sm ra-summary-toggle" id="ra-inv-summary-toggle" style="margin-bottom:12px">
            <i class="ri-bar-chart-box-line"></i> Statistik anzeigen
        </button>';

        // Summary cards (hidden by default)
        echo '<div class="ra-inv-summary" id="ra-inv-summary" style="display:none">
            <div class="ra-inv-summary-card">
                <div class="ra-inv-summary-val" id="ra-inv-count">-</div>
                <div class="ra-inv-summary-label">Rechnungen</div>
            </div>
            <div class="ra-inv-summary-card">
                <div class="ra-inv-summary-val" id="ra-inv-revenue">-</div>
                <div class="ra-inv-summary-label">Umsatz</div>
            </div>
            <div class="ra-inv-summary-card">
                <div class="ra-inv-summary-val" id="ra-inv-discounts">-</div>
                <div class="ra-inv-summary-label">Rabatte</div>
            </div>
        </div>';

        // Bulk actions bar
        echo '<div id="ra-inv-bulk-bar" style="display:none;background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:12px 16px;margin-bottom:12px">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-weight:600;color:#4f46e5"><span id="ra-inv-selected-count">0</span> ausgew&auml;hlt</span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button class="ra-btn ra-btn-sm" id="ra-bulk-paid" style="background:#059669;color:#fff"><i class="ri-checkbox-circle-line"></i> Als bezahlt</button>
                    <button class="ra-btn ra-btn-sm" id="ra-bulk-send" style="background:#3b82f6;color:#fff"><i class="ri-mail-send-line"></i> E-Mails senden</button>
                    <button class="ra-btn ra-btn-sm" id="ra-bulk-reminder" style="background:#d97706;color:#fff"><i class="ri-alarm-warning-line"></i> Erinnerungen</button>
                    <div style="position:relative;display:inline-block">
                        <button class="ra-btn ra-btn-sm" id="ra-bulk-export" style="background:#8b5cf6;color:#fff"><i class="ri-download-2-line"></i> Export <i class="ri-arrow-down-s-line"></i></button>
                        <div id="ra-export-dropdown" style="display:none;position:absolute;top:100%;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:220px;z-index:100;margin-top:4px">
                            <div style="padding:8px 0">
                                <div style="padding:6px 12px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">Buchhalter-Formate</div>
                                <a href="#" class="ra-export-opt" data-format="datev" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-text-line" style="margin-right:8px;color:#8b5cf6"></i>DATEV-Export (.csv)</a>
                                <a href="#" class="ra-export-opt" data-format="csv" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-excel-2-line" style="margin-right:8px;color:#059669"></i>CSV-Export</a>
                                <a href="#" class="ra-export-opt" data-format="excel" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-excel-line" style="margin-right:8px;color:#217346"></i>Excel-Export (.xlsx)</a>
                                <div style="border-top:1px solid #e5e7eb;margin:6px 0"></div>
                                <div style="padding:6px 12px;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase">Dokumente</div>
                                <a href="#" class="ra-export-opt" data-format="pdf" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-file-pdf-line" style="margin-right:8px;color:#dc2626"></i>PDF-Sammlung (.zip)</a>
                                <a href="#" class="ra-export-opt" data-format="json" style="display:block;padding:8px 12px;color:#374151;text-decoration:none;font-size:13px"><i class="ri-code-s-slash-line" style="margin-right:8px;color:#3b82f6"></i>JSON-Export</a>
                            </div>
                        </div>
                    </div>
                    <button class="ra-btn ra-btn-sm" id="ra-bulk-delete" style="background:#dc2626;color:#fff"><i class="ri-delete-bin-line"></i> L&ouml;schen</button>
                    <button class="ra-btn ra-btn-sm" id="ra-bulk-cancel" style="background:#6b7280;color:#fff"><i class="ri-close-line"></i> Abbrechen</button>
                </div>
            </div>
        </div>';

        // Invoice table
        echo '<div style="overflow-x:auto">
        <table class="ra-inv-table">
            <thead>
                <tr>
                    <th style="width:40px"><input type="checkbox" id="ra-inv-select-all" style="width:16px;height:16px;cursor:pointer"></th>
                    <th class="ra-sortable" data-sort="invoice_number">Nr. <i class="ri-arrow-up-down-line ra-sort-icon"></i></th>
                    <th class="ra-sortable" data-sort="created_at">Datum <i class="ri-arrow-up-down-line ra-sort-icon"></i></th>
                    <th class="ra-sortable" data-sort="customer_name">Kunde <i class="ri-arrow-up-down-line ra-sort-icon"></i></th>
                    <th class="ra-sortable" data-sort="net_amount">Netto <i class="ri-arrow-up-down-line ra-sort-icon"></i></th>
                    <th>MwSt</th>
                    <th class="ra-sortable" data-sort="total">Gesamt <i class="ri-arrow-up-down-line ra-sort-icon"></i></th>
                    <th class="ra-sortable" data-sort="status">Status <i class="ri-arrow-up-down-line ra-sort-icon"></i></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="ra-inv-body">
                <tr><td colspan="9" style="text-align:center;padding:32px;color:#9ca3af;">Rechnungen werden geladen...</td></tr>
            </tbody>
        </table>
        </div>
        <div class="ra-load-more" id="ra-inv-load-more" style="display:none">
            <button class="ra-btn ra-btn-outline" id="ra-inv-more-btn" data-page="1">Mehr laden</button>
        </div>';

        echo '</div>'; // end ra-tab-invoices

        // ========== TAB: Kunden ==========
        echo '<div class="ra-tab-content" id="ra-tab-customers">';

        // Customer toolbar
        echo '<div class="ra-inv-filters">
            <button class="ra-btn ra-btn-primary ra-btn-sm" id="ra-new-customer-btn">
                <i class="ri-user-add-line"></i> Neuer Kunde
            </button>
            <div class="ra-search" style="flex:1;max-width:400px">
                <i class="ri-search-line"></i>
                <input type="text" id="ra-customer-search" placeholder="Kunde suchen...">
            </div>
        </div>';

        // Customer summary
        echo '<div class="ra-inv-summary" id="ra-cust-summary">
            <div class="ra-inv-summary-card">
                <div class="ra-inv-summary-val" id="ra-cust-count">-</div>
                <div class="ra-inv-summary-label">Kunden</div>
            </div>
        </div>';

        // Customer table
        echo '<div style="overflow-x:auto">
        <table class="ra-inv-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Firma</th>
                    <th>E-Mail</th>
                    <th>Telefon</th>
                    <th>Stadt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="ra-cust-body">
                <tr><td colspan="6" style="text-align:center;padding:32px;color:#9ca3af;">Kunden werden geladen...</td></tr>
            </tbody>
        </table>
        </div>
        <div class="ra-load-more" id="ra-cust-load-more" style="display:none">
            <button class="ra-btn ra-btn-outline" id="ra-cust-more-btn" data-page="1">Mehr laden</button>
        </div>';

        echo '</div>'; // end ra-tab-customers

        // ========== TAB: Feedback ==========
        echo '<div class="ra-tab-content" id="ra-tab-feedback">
        <div class="ra-settings" style="max-width:600px">
            <h3><i class="ri-feedback-line"></i> Feedback senden</h3>
            <p style="font-size:14px;color:#6b7280;margin-bottom:20px">Wir freuen uns &uuml;ber Ihr Feedback! Teilen Sie uns Fehler, Ideen oder Fragen mit.</p>

            <div style="margin-bottom:20px">
                <label style="font-size:12px;font-weight:600;color:#6b7280;display:block;margin-bottom:8px">Kategorie</label>
                <div class="ra-feedback-cats-inline" id="ra-fb-cats">
                    <button type="button" class="ra-fb-cat-btn" data-cat="bug"><i class="ri-bug-line"></i> Fehler</button>
                    <button type="button" class="ra-fb-cat-btn" data-cat="feature"><i class="ri-lightbulb-line"></i> Idee</button>
                    <button type="button" class="ra-fb-cat-btn" data-cat="question"><i class="ri-question-line"></i> Frage</button>
                    <button type="button" class="ra-fb-cat-btn" data-cat="rating"><i class="ri-star-line"></i> Bewertung</button>
                </div>
            </div>

            <div id="ra-fb-rating-section" style="display:none;margin-bottom:20px">
                <label style="font-size:12px;font-weight:600;color:#6b7280;display:block;margin-bottom:8px">Bewertung</label>
                <div class="ra-fb-stars">
                    <button type="button" class="ra-fb-star" data-rating="1"><i class="ri-star-line"></i></button>
                    <button type="button" class="ra-fb-star" data-rating="2"><i class="ri-star-line"></i></button>
                    <button type="button" class="ra-fb-star" data-rating="3"><i class="ri-star-line"></i></button>
                    <button type="button" class="ra-fb-star" data-rating="4"><i class="ri-star-line"></i></button>
                    <button type="button" class="ra-fb-star" data-rating="5"><i class="ri-star-line"></i></button>
                </div>
            </div>

            <div class="field" style="margin-bottom:16px">
                <label style="font-size:12px;font-weight:600;color:#6b7280;display:block;margin-bottom:6px">Nachricht</label>
                <textarea id="ra-fb-message" class="ra-input" rows="5" placeholder="Beschreiben Sie Ihr Anliegen..." style="width:100%;resize:vertical"></textarea>
            </div>

            <div class="field" style="margin-bottom:20px">
                <label style="font-size:12px;font-weight:600;color:#6b7280;display:block;margin-bottom:6px">E-Mail (optional)</label>
                <input type="email" id="ra-fb-email" class="ra-input" placeholder="Ihre E-Mail f&uuml;r R&uuml;ckfragen" style="width:100%">
            </div>

            <div id="ra-fb-msg" class="ra-fb-msg"></div>

            <button type="button" class="ra-btn ra-btn-primary" id="ra-fb-submit" style="width:100%">
                <i class="ri-send-plane-fill"></i> Feedback senden
            </button>
        </div>
        </div>'; // end ra-tab-feedback

        // Toast notification
        echo '<div class="ra-toast" id="ra-toast"></div>';

        // Reward reject modal
        echo '<div class="ra-reject-modal" id="ra-reject-modal">
            <div class="ra-reject-modal-content">
                <h3><i class="ri-close-circle-line"></i> Belohnung ablehnen</h3>
                <p style="font-size:13px;color:#6b7280;margin-bottom:12px">Bitte geben Sie einen Grund f&uuml;r die Ablehnung an. Der Kunde kann die Belohnung beim n&auml;chsten Besuch erneut einl&ouml;sen.</p>
                <textarea id="ra-reject-reason" placeholder="Grund f&uuml;r die Ablehnung..."></textarea>
                <input type="hidden" id="ra-reject-repair-id" value="">
                <div class="ra-reject-modal-actions">
                    <button class="ra-btn ra-btn-outline" id="ra-reject-cancel">Abbrechen</button>
                    <button class="ra-btn" style="background:#dc2626;color:#fff" id="ra-reject-submit">Ablehnen</button>
                </div>
            </div>
        </div>';

        // Invoice modal (shown when status changes to "Fertig")
        echo '<div class="ra-modal-overlay" id="ra-invoice-modal">
    <div class="ra-modal">
        <h3 id="ra-inv-modal-title"><i class="ri-file-list-3-line"></i> Reparatur abschlie&szlig;en</h3>
        <p class="ra-modal-sub" id="ra-inv-modal-subtitle">Leistungen und Bruttobetr&auml;ge f&uuml;r die Rechnung eingeben</p>
        <div id="ra-inv-modal-info" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#0369a1;display:none"></div>
        <div class="ra-inv-lines" id="ra-inv-lines">
            <div class="ra-inv-line">
                <input type="text" placeholder="Leistung (z.B. Displaytausch)" class="ra-inv-line-desc">
                <input type="number" placeholder="Brutto" step="0.01" min="0" class="ra-inv-line-amount">
                <button type="button" class="ra-inv-line-remove" title="Entfernen">&times;</button>
            </div>
        </div>
        <button type="button" class="ra-inv-add" id="ra-inv-add-line">
            <i class="ri-add-line"></i> Weitere Position hinzuf&uuml;gen
        </button>
        <div class="ra-inv-totals">
            <div class="ra-inv-total-row">
                <span>Netto:</span>
                <span id="ra-inv-modal-net">0,00 &euro;</span>
            </div>
            <div class="ra-inv-total-row" id="ra-inv-modal-vat-row">
                <span>MwSt <span id="ra-inv-modal-vat-pct">' . intval($vat_rate) . '</span>%:</span>
                <span id="ra-inv-modal-vat">0,00 &euro;</span>
            </div>
            <div class="ra-inv-total-row ra-inv-total-final">
                <span>Gesamt:</span>
                <span id="ra-inv-modal-total">0,00 &euro;</span>
            </div>
        </div>

        <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600">
                <input type="checkbox" id="ra-inv-paid-toggle" style="width:18px;height:18px;cursor:pointer">
                Bereits bezahlt
            </label>
            <div id="ra-inv-paid-fields" style="display:none;margin-top:12px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Zahlungsart</label>
                        <select id="ra-inv-payment-method" class="ra-input" style="width:100%">
                            <option value="">-- Bitte w&auml;hlen --</option>
                            <option value="bar">Barzahlung</option>
                            <option value="ec">EC-Karte</option>
                            <option value="kreditkarte">Kreditkarte</option>
                            <option value="ueberweisung">&Uuml;berweisung</option>
                            <option value="paypal">PayPal</option>
                            <option value="andere">Andere</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Zahlungsdatum</label>
                        <input type="date" id="ra-inv-paid-date" class="ra-input" style="width:100%">
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:12px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
                <input type="checkbox" id="ra-inv-differenz" style="width:18px;height:18px;cursor:pointer">
                <span>Differenzbesteuerung <span style="font-size:11px;color:#6b7280">(&sect;25a UStG - 0% MwSt)</span></span>
            </label>
        </div>

        <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
            <button type="button" class="ra-btn ra-btn-outline" style="flex:1;min-width:100px" id="ra-inv-modal-cancel">Abbrechen</button>
            <button type="button" class="ra-btn" style="flex:1;min-width:140px;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0" id="ra-inv-modal-skip">
                <i class="ri-check-line"></i> Nur Fertig
            </button>
            <button type="button" class="ra-btn ra-btn-primary" style="flex:2;min-width:180px" id="ra-inv-modal-submit">
                <i class="ri-check-line"></i> Abschlie&szlig;en &amp; Rechnung
            </button>
        </div>
    </div>
</div>';

        // New Invoice Modal (standalone invoice creation)
        echo '<div class="ra-modal-overlay" id="ra-new-invoice-modal">
    <div class="ra-modal" style="max-width:600px">
        <h3><i class="ri-file-list-3-line"></i> Neue Rechnung erstellen</h3>
        <p class="ra-modal-sub">Rechnung ohne Reparaturauftrag erstellen</p>

        <div style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:600;margin-bottom:6px;display:block">Kunde suchen oder neu eingeben</label>
            <div class="ra-search" style="margin-bottom:8px">
                <i class="ri-search-line"></i>
                <input type="text" id="ra-ninv-customer-search" placeholder="Kundenname, E-Mail oder Firma suchen..." autocomplete="off">
            </div>
            <div id="ra-ninv-customer-results" style="display:none;background:#fff;border:1px solid #e5e7eb;border-radius:8px;max-height:200px;overflow-y:auto;position:relative;z-index:10"></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Name *</label>
                <input type="text" id="ra-ninv-name" class="ra-input" placeholder="Max Mustermann" required>
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Firma</label>
                <input type="text" id="ra-ninv-company" class="ra-input" placeholder="Firma GmbH">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">E-Mail</label>
                <input type="email" id="ra-ninv-email" class="ra-input" placeholder="email@example.de">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Telefon</label>
                <input type="text" id="ra-ninv-phone" class="ra-input" placeholder="+49...">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Stra&szlig;e</label>
                <input type="text" id="ra-ninv-address" class="ra-input" placeholder="Musterstra&szlig;e 1">
            </div>
            <div style="display:grid;grid-template-columns:80px 1fr;gap:8px">
                <div>
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">PLZ</label>
                    <input type="text" id="ra-ninv-plz" class="ra-input" placeholder="12345">
                </div>
                <div>
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Stadt</label>
                    <input type="text" id="ra-ninv-city" class="ra-input" placeholder="Berlin">
                </div>
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">USt-IdNr.</label>
                <input type="text" id="ra-ninv-taxid" class="ra-input" placeholder="DE123456789">
            </div>
        </div>

        <div style="margin-bottom:16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px">
            <label style="font-size:12px;color:#0369a1;display:block;margin-bottom:4px">Rechnungsnummer</label>
            <input type="text" id="ra-ninv-number" class="ra-input" placeholder="' . esc_attr($inv_prefix . str_pad($inv_suggested_next, 4, '0', STR_PAD_LEFT)) . '" style="background:#fff">
            <p style="font-size:11px;color:#6b7280;margin:4px 0 0">N&auml;chste: <strong>' . esc_html($inv_prefix . str_pad($inv_suggested_next, 4, '0', STR_PAD_LEFT)) . '</strong> &ndash; Leer lassen f&uuml;r automatische Nummerierung</p>
        </div>

        <div style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:600;margin-bottom:6px;display:block">Positionen</label>
            <div class="ra-inv-lines" id="ra-ninv-lines">
                <div class="ra-inv-line">
                    <input type="text" placeholder="Leistung" class="ra-inv-line-desc">
                    <input type="number" placeholder="Brutto" step="0.01" min="0" class="ra-inv-line-amount">
                    <button type="button" class="ra-inv-line-remove" title="Entfernen">&times;</button>
                </div>
            </div>
            <button type="button" class="ra-inv-add" id="ra-ninv-add-line">
                <i class="ri-add-line"></i> Position hinzuf&uuml;gen
            </button>
        </div>

        <div class="ra-inv-totals" style="margin-bottom:16px">
            <div class="ra-inv-total-row">
                <span>Netto:</span>
                <span id="ra-ninv-net">0,00 &euro;</span>
            </div>
            <div class="ra-inv-total-row" id="ra-ninv-vat-row">
                <span>MwSt ' . intval($vat_rate) . '%:</span>
                <span id="ra-ninv-vat">0,00 &euro;</span>
            </div>
            <div class="ra-inv-total-row ra-inv-total-final">
                <span>Gesamt:</span>
                <span id="ra-ninv-total">0,00 &euro;</span>
            </div>
        </div>

        <div style="margin-bottom:12px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
                <input type="checkbox" id="ra-ninv-differenz" style="width:18px;height:18px;cursor:pointer">
                <span>Differenzbesteuerung <span style="font-size:11px;color:#6b7280">(&sect;25a UStG - 0% MwSt)</span></span>
            </label>
        </div>

        <div>
            <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Notizen</label>
            <textarea id="ra-ninv-notes" class="ra-input" rows="2" placeholder="Interne Notizen..."></textarea>
        </div>

        <input type="hidden" id="ra-ninv-customer-id" value="">

        <div style="display:flex;gap:10px;margin-top:20px">
            <button type="button" class="ra-btn ra-btn-outline" style="flex:1" id="ra-ninv-cancel">Abbrechen</button>
            <button type="button" class="ra-btn ra-btn-primary" style="flex:2" id="ra-ninv-submit">
                <i class="ri-check-line"></i> Rechnung erstellen
            </button>
        </div>
    </div>
</div>';

        // New Angebot Modal (quote creation)
        echo '<div class="ra-modal-overlay" id="ra-new-angebot-modal">
    <div class="ra-modal" style="max-width:600px">
        <h3 style="color:#16a34a"><i class="ri-draft-line"></i> Neues Angebot erstellen</h3>
        <p class="ra-modal-sub">Kostenvoranschlag / Angebot f&uuml;r Kunden</p>

        <div style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:600;margin-bottom:6px;display:block">Kunde suchen oder neu eingeben</label>
            <div class="ra-search" style="margin-bottom:8px">
                <i class="ri-search-line"></i>
                <input type="text" id="ra-nang-customer-search" placeholder="Kundenname, E-Mail oder Firma suchen..." autocomplete="off">
            </div>
            <div id="ra-nang-customer-results" style="display:none;background:#fff;border:1px solid #e5e7eb;border-radius:8px;max-height:200px;overflow-y:auto;position:relative;z-index:10"></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Name *</label>
                <input type="text" id="ra-nang-name" class="ra-input" placeholder="Max Mustermann" required>
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Firma</label>
                <input type="text" id="ra-nang-company" class="ra-input" placeholder="Firma GmbH">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">E-Mail</label>
                <input type="email" id="ra-nang-email" class="ra-input" placeholder="email@example.de">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Telefon</label>
                <input type="text" id="ra-nang-phone" class="ra-input" placeholder="+49...">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Stra&szlig;e</label>
                <input type="text" id="ra-nang-address" class="ra-input" placeholder="Musterstra&szlig;e 1">
            </div>
            <div style="display:grid;grid-template-columns:80px 1fr;gap:8px">
                <div>
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">PLZ</label>
                    <input type="text" id="ra-nang-plz" class="ra-input" placeholder="12345">
                </div>
                <div>
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Stadt</label>
                    <input type="text" id="ra-nang-city" class="ra-input" placeholder="Berlin">
                </div>
            </div>
        </div>

        <div style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:600;margin-bottom:6px;display:block">Positionen</label>
            <div class="ra-inv-lines" id="ra-nang-lines">
                <div class="ra-inv-line">
                    <input type="text" placeholder="Leistung" class="ra-inv-line-desc">
                    <input type="number" placeholder="Brutto" step="0.01" min="0" class="ra-inv-line-amount">
                    <button type="button" class="ra-inv-line-remove" title="Entfernen">&times;</button>
                </div>
            </div>
            <button type="button" class="ra-inv-add" id="ra-nang-add-line">
                <i class="ri-add-line"></i> Position hinzuf&uuml;gen
            </button>
        </div>

        <div class="ra-inv-totals" style="margin-bottom:16px">
            <div class="ra-inv-total-row">
                <span>Netto:</span>
                <span id="ra-nang-net">0,00 &euro;</span>
            </div>
            <div class="ra-inv-total-row" id="ra-nang-vat-row">
                <span>MwSt ' . intval($vat_rate) . '%:</span>
                <span id="ra-nang-vat">0,00 &euro;</span>
            </div>
            <div class="ra-inv-total-row ra-inv-total-final">
                <span>Gesamt:</span>
                <span id="ra-nang-total">0,00 &euro;</span>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;padding:12px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0">
            <div>
                <label style="font-size:12px;color:#16a34a;display:block;margin-bottom:4px;font-weight:600">G&uuml;ltig bis</label>
                <input type="date" id="ra-nang-valid-until" class="ra-input" style="width:100%">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Notizen</label>
                <input type="text" id="ra-nang-notes" class="ra-input" placeholder="z.B. Lieferzeit 2 Wochen">
            </div>
        </div>

        <input type="hidden" id="ra-nang-customer-id" value="">

        <div style="display:flex;gap:10px">
            <button type="button" class="ra-btn ra-btn-outline" style="flex:1" id="ra-nang-cancel">Abbrechen</button>
            <button type="button" class="ra-btn" style="flex:2;background:#16a34a;color:#fff" id="ra-nang-submit">
                <i class="ri-draft-line"></i> Angebot erstellen
            </button>
        </div>
    </div>
</div>';

        // Customer Modal (new/edit customer)
        echo '<div class="ra-modal-overlay" id="ra-customer-modal">
    <div class="ra-modal" style="max-width:500px">
        <h3 id="ra-cust-modal-title"><i class="ri-user-add-line"></i> Neuer Kunde</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
            <div style="grid-column:span 2">
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Name *</label>
                <input type="text" id="ra-cust-name" class="ra-input" required>
            </div>
            <div style="grid-column:span 2">
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Firma</label>
                <input type="text" id="ra-cust-company" class="ra-input">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">E-Mail</label>
                <input type="email" id="ra-cust-email" class="ra-input">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Telefon</label>
                <input type="text" id="ra-cust-phone" class="ra-input">
            </div>
            <div style="grid-column:span 2">
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Stra&szlig;e</label>
                <input type="text" id="ra-cust-address" class="ra-input">
            </div>
            <div style="display:grid;grid-template-columns:80px 1fr;gap:8px">
                <div>
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">PLZ</label>
                    <input type="text" id="ra-cust-plz" class="ra-input">
                </div>
                <div>
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Stadt</label>
                    <input type="text" id="ra-cust-city" class="ra-input">
                </div>
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">USt-IdNr.</label>
                <input type="text" id="ra-cust-taxid" class="ra-input">
            </div>
            <div style="grid-column:span 2">
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Notizen</label>
                <textarea id="ra-cust-notes" class="ra-input" rows="2"></textarea>
            </div>
        </div>
        <input type="hidden" id="ra-cust-id" value="">
        <div style="display:flex;gap:10px">
            <button type="button" class="ra-btn ra-btn-outline" style="flex:1" id="ra-cust-cancel">Abbrechen</button>
            <button type="button" class="ra-btn ra-btn-primary" style="flex:2" id="ra-cust-submit">
                <i class="ri-check-line"></i> Speichern
            </button>
        </div>
    </div>
</div>';

        // Invoice Edit Modal (with customer fields)
        echo '<div class="ra-modal-overlay" id="ra-edit-invoice-modal">
    <div class="ra-modal" style="max-width:650px;max-height:90vh;overflow-y:auto">
        <h3><i class="ri-pencil-line"></i> Rechnung bearbeiten</h3>
        <p class="ra-modal-sub" id="ra-einv-subtitle">Rechnung und Kundendaten bearbeiten</p>

        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px;margin-bottom:16px">
            <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end">
                <div>
                    <label style="font-size:12px;color:#0369a1;display:block;margin-bottom:4px">Rechnungsnummer</label>
                    <input type="text" id="ra-einv-number-input" class="ra-input" style="background:#fff;font-weight:600">
                </div>
                <div style="font-size:13px;color:#6b7280;padding-bottom:10px">
                    Datum: <span id="ra-einv-date"></span>
                </div>
            </div>
        </div>

        <details open style="margin-bottom:16px">
            <summary style="font-size:13px;font-weight:600;cursor:pointer;padding:8px 0">Kundendaten</summary>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                <div style="grid-column:span 2">
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Name *</label>
                    <input type="text" id="ra-einv-name" class="ra-input" required>
                </div>
                <div style="grid-column:span 2">
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Firma</label>
                    <input type="text" id="ra-einv-company" class="ra-input">
                </div>
                <div>
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">E-Mail</label>
                    <input type="email" id="ra-einv-email" class="ra-input">
                </div>
                <div>
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Telefon</label>
                    <input type="tel" id="ra-einv-phone" class="ra-input">
                </div>
                <div style="grid-column:span 2">
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Adresse</label>
                    <input type="text" id="ra-einv-address" class="ra-input">
                </div>
                <div>
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">PLZ</label>
                    <input type="text" id="ra-einv-plz" class="ra-input">
                </div>
                <div>
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Stadt</label>
                    <input type="text" id="ra-einv-city" class="ra-input">
                </div>
                <div style="grid-column:span 2">
                    <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">USt-IdNr.</label>
                    <input type="text" id="ra-einv-taxid" class="ra-input">
                </div>
            </div>
        </details>

        <details open style="margin-bottom:16px">
            <summary style="font-size:13px;font-weight:600;cursor:pointer;padding:8px 0">Positionen</summary>
            <div class="ra-inv-lines" id="ra-einv-lines" style="margin-top:12px">
                <div class="ra-inv-line">
                    <input type="text" placeholder="Leistung" class="ra-inv-line-desc">
                    <input type="number" placeholder="Brutto" step="0.01" min="0" class="ra-inv-line-amount">
                    <button type="button" class="ra-inv-line-remove" title="Entfernen">&times;</button>
                </div>
            </div>
            <button type="button" class="ra-inv-add" id="ra-einv-add-line">
                <i class="ri-add-line"></i> Position hinzuf&uuml;gen
            </button>
        </details>

        <div class="ra-inv-totals" style="margin-bottom:16px">
            <div class="ra-inv-total-row">
                <span>Netto:</span>
                <span id="ra-einv-net">0,00 &euro;</span>
            </div>
            <div class="ra-inv-total-row" id="ra-einv-vat-row">
                <span>MwSt ' . intval($vat_rate) . '%:</span>
                <span id="ra-einv-vat">0,00 &euro;</span>
            </div>
            <div class="ra-inv-total-row ra-inv-total-final">
                <span>Gesamt:</span>
                <span id="ra-einv-total">0,00 &euro;</span>
            </div>
        </div>

        <div style="margin-bottom:12px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
                <input type="checkbox" id="ra-einv-differenz" style="width:18px;height:18px;cursor:pointer">
                <span>Differenzbesteuerung <span style="font-size:11px;color:#6b7280">(&sect;25a UStG - 0% MwSt)</span></span>
            </label>
        </div>

        <div style="margin-bottom:16px">
            <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Notizen</label>
            <textarea id="ra-einv-notes" class="ra-input" rows="2" placeholder="Interne Notizen..."></textarea>
        </div>

        <input type="hidden" id="ra-einv-id" value="">

        <div style="display:flex;gap:10px">
            <button type="button" class="ra-btn ra-btn-outline" style="flex:1" id="ra-einv-cancel">Abbrechen</button>
            <button type="button" class="ra-btn ra-btn-primary" style="flex:2" id="ra-einv-submit">
                <i class="ri-save-line"></i> Speichern
            </button>
        </div>
    </div>
</div>';

        // Payment Method Modal
        echo '<div class="ra-modal-overlay" id="ra-payment-modal">
    <div class="ra-modal" style="max-width:400px">
        <h3><i class="ri-bank-card-line"></i> Zahlung erfassen</h3>
        <p class="ra-modal-sub">Zahlungsart und Datum angeben</p>

        <div style="margin-bottom:16px">
            <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Zahlungsart</label>
            <select id="ra-payment-method" class="ra-input" style="width:100%">
                <option value="">-- Bitte w&auml;hlen --</option>
                <option value="bar">Barzahlung</option>
                <option value="ec">EC-Karte</option>
                <option value="kreditkarte">Kreditkarte</option>
                <option value="ueberweisung">&Uuml;berweisung</option>
                <option value="paypal">PayPal</option>
                <option value="andere">Andere</option>
            </select>
        </div>

        <div style="margin-bottom:20px">
            <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Zahlungsdatum</label>
            <input type="date" id="ra-payment-date" class="ra-input" style="width:100%">
        </div>

        <input type="hidden" id="ra-payment-inv-id" value="">

        <div style="display:flex;gap:10px">
            <button type="button" class="ra-btn ra-btn-outline" style="flex:1" id="ra-payment-cancel">Abbrechen</button>
            <button type="button" class="ra-btn ra-btn-primary" style="flex:2" id="ra-payment-submit">
                <i class="ri-check-line"></i> Best&auml;tigen
            </button>
        </div>
    </div>
</div>';

        // Billbee Import Modal
        echo '<div class="ra-modal-overlay" id="ra-billbee-modal">
    <div class="ra-modal" style="max-width:500px">
        <h3 style="color:#ea580c"><i class="ri-upload-cloud-line"></i> Billbee Import</h3>
        <p style="font-size:13px;color:#6b7280;margin-bottom:20px">Importieren Sie Ihre Rechnungen aus Billbee. Exportieren Sie in Billbee als <strong>XML Datei</strong> und laden Sie diese hier hoch.</p>

        <div style="border:2px dashed #fed7aa;border-radius:12px;padding:32px 20px;text-align:center;background:#fffbeb;margin-bottom:20px" id="ra-billbee-dropzone">
            <i class="ri-file-upload-line" style="font-size:48px;color:#f97316;margin-bottom:12px;display:block"></i>
            <p style="color:#92400e;font-weight:500;margin:0 0 8px">XML-Datei hier ablegen</p>
            <p style="color:#a16207;font-size:12px;margin:0 0 12px">oder klicken zum Auswählen</p>
            <input type="file" id="ra-billbee-file" accept=".xml" style="display:none">
            <button type="button" class="ra-btn ra-btn-sm" style="background:#f97316;color:#fff" id="ra-billbee-select">Datei auswählen</button>
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
                <span>Import läuft...</span>
            </div>
        </div>

        <div style="display:flex;gap:10px">
            <button type="button" class="ra-btn ra-btn-outline" style="flex:1" id="ra-billbee-cancel">Abbrechen</button>
            <button type="button" class="ra-btn" style="flex:2;background:#ea580c;color:#fff" id="ra-billbee-submit" disabled>
                <i class="ri-upload-2-line"></i> Importieren
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
        searchTimer=null,
        currentPage=1,
        invSortBy="created_at",
        invSortDir="desc";

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
            sel.setAttribute("data-prev",sel.value);
            sel.addEventListener("change",function(){
                var rid=this.getAttribute("data-repair-id"),
                    st=this.value;
                // If "done" → show invoice modal instead of immediate AJAX
                if(st==="done"){
                    showInvoiceModal(rid,this);
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
                        toast("Status aktualisiert");
                        updateBadge(self.closest(".ra-repair-card"),st);
                        self.setAttribute("data-prev",st);
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
        if(card.dataset.brand)params.set("brand",card.dataset.brand);
        if(card.dataset.model)params.set("model",card.dataset.model);
        window.open("/formular/"+SLUG+"?"+params.toString(),"_blank");
    });

    /* ===== Direct Invoice Button ===== */
    document.getElementById("ra-repairs-list").addEventListener("click",function(e){
        var btn=e.target.closest(".ra-btn-invoice");
        if(!btn)return;
        var card=btn.closest(".ra-repair-card");
        if(!card)return;
        var rid=card.dataset.id;
        var sel=card.querySelector(".ra-status-select");
        showInvoiceModal(rid,sel);
    });

    /* ===== Delete Repair ===== */
    document.getElementById("ra-repairs-list").addEventListener("click",function(e){
        var btn=e.target.closest(".ra-btn-delete");
        if(!btn)return;
        var card=btn.closest(".ra-repair-card");
        if(!card)return;
        var rid=card.dataset.id;
        if(!confirm("Reparatur #"+rid+" wirklich l\u00f6schen?"))return;
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
                toast("Reparatur gel\u00f6scht");
            }else{
                btn.disabled=false;
                btn.innerHTML=\'<i class="ri-delete-bin-line"></i>\';
                toast(res.data&&res.data.message?res.data.message:"Fehler beim L\u00f6schen");
            }
        })
        .catch(function(){
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-delete-bin-line"></i>\';
            toast("Verbindungsfehler");
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
            problem: card.dataset.problem||"",
            date: card.dataset.date||"",
            muster: card.dataset.muster||"",
            signature: card.dataset.signature||""
        };
        var w=window.open("","_blank","width=800,height=900");
        if(!w){alert("Popup blockiert! Bitte erlauben Sie Pop-ups.");return;}
        var device=((data.brand||"")+" "+(data.model||"")).trim();
        var musterHtml=data.muster&&data.muster.indexOf("data:image/")===0?\'<div class="field"><span class="label">Muster:</span><img src="\'+data.muster+\'" style="max-width:80px;border:1px solid #ddd;border-radius:4px"></div>\':"";
        var pinHtml=data.pin?\'<div class="field"><span class="label">PIN:</span><span class="value highlight">\'+esc(data.pin)+\'</span></div>\':"";
        var addressHtml=data.address?\'<div class="field"><span class="label">Adresse:</span><span class="value">\'+esc(data.address)+\'</span></div>\':"";
        var signatureHtml=data.signature&&data.signature.indexOf("data:image/")===0?\'<div class="sig-img"><img src="\'+data.signature+\'" style="max-height:40px"></div>\':\'<div class="signature-line"></div>\';
        var html=\'<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Reparaturauftrag #\'+data.id+\'</title><style>\'+
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
                \'<div class="logo">\'+esc(STORE_COMPANY)+(STORE_OWNER?\'<br><span style="font-size:10px;font-weight:normal;color:#6b7280">Inh. \'+esc(STORE_OWNER)+\'</span>\':\'\')+\'</div>\'+
                \'<div class="header-info">\'+(STORE_ADDRESS?esc(STORE_ADDRESS)+\'<br>\':\'\')+
                (STORE_PHONE?\'<strong>Tel: \'+esc(STORE_PHONE)+\'</strong><br>\':\'\')+
                (STORE_EMAIL?\'E-Mail: \'+esc(STORE_EMAIL)+\'<br>\':\'\')+
                (STORE_TAX_ID?\'USt-IdNr.: \'+esc(STORE_TAX_ID):\'\')+
                \'</div>\'+
            \'</div>\'+
            \'<div class="title"><h1>Reparaturauftrag #\'+data.id+\'</h1><p>Datum: \'+esc(data.date)+\'</p></div>\'+
            \'<div class="two-col">\'+
                \'<div class="section"><div class="section-title">Kunde</div>\'+
                    \'<div class="field"><span class="label">Name:</span><span class="value">\'+esc(data.name)+\'</span></div>\'+
                    \'<div class="field"><span class="label">Telefon:</span><span class="value">\'+esc(data.phone)+\'</span></div>\'+
                    \'<div class="field"><span class="label">E-Mail:</span><span class="value">\'+esc(data.email)+\'</span></div>\'+
                    addressHtml+
                \'</div>\'+
                \'<div class="section"><div class="section-title">Ger\\u00e4t</div>\'+
                    \'<div class="field"><span class="label">Ger\\u00e4t:</span><span class="value">\'+esc(device)+\'</span></div>\'+
                    pinHtml+
                    musterHtml+
                \'</div>\'+
            \'</div>\'+
            \'<div class="problem-section"><div class="section-title">Problembeschreibung</div>\'+
                \'<div style="font-size:11px">\'+esc(data.problem)+\'</div>\'+
            \'</div>\'+
            \'<div class="datenschutz">\'+
                \'<div class="datenschutz-title">Datenschutzhinweis</div>\'+
                \'<div class="datenschutz-text">Mit meiner Unterschrift best\\u00e4tige ich:<ul><li>Die Richtigkeit der angegebenen Daten</li><li>Die Zustimmung zur Datenverarbeitung gem\\u00e4\\u00df DSGVO</li><li>Die Kenntnisnahme der Reparaturbedingungen</li></ul></div>\'+
            \'</div>\'+
            \'<div class="signature-area">\'+
                \'<div class="signature-box" style="max-width:250px"><label>Unterschrift Kunde (Einwilligung Datenschutz):</label>\'+signatureHtml+\'</div>\'+
            \'</div>\'+
            \'<div class="footer">\'+esc(STORE_COMPANY)+(STORE_ADDRESS?\' | \'+esc(STORE_ADDRESS):\'\')+(STORE_PHONE?\' | Tel: \'+esc(STORE_PHONE):\'\')+(STORE_EMAIL?\' | \'+esc(STORE_EMAIL):\'\')+\'</div>\'+
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
            toast("Keine E-Mail-Adresse vorhanden");
            return;
        }
        if(!confirm("Reparaturauftrag an "+email+" senden?")){
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
                toast("E-Mail erfolgreich gesendet!");
            }else{
                toast(res.data&&res.data.message?res.data.message:"Fehler beim Senden");
            }
        })
        .catch(function(){
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-mail-send-line"></i>\';
            toast("Verbindungsfehler");
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
        var pinHtml=r.device_pattern?\'<div class="ra-repair-pin"><i class="ri-lock-password-line"></i> PIN: \'+esc(r.device_pattern)+\'</div>\':"";
        var addressHtml=r.customer_address?\'<div class="ra-repair-address"><i class="ri-map-pin-line"></i> \'+esc(r.customer_address)+\'</div>\':"";
        var musterHtml=(r.muster_image&&r.muster_image.indexOf("data:image/")===0)?\'<div class="ra-repair-muster"><img src="\'+r.muster_image+\'" alt="Muster" title="Entsperrmuster"></div>\':"";
        var fullProblem=r.problem_description||"";
        return \'<div class="ra-repair-card" data-id="\'+r.id+\'" data-status="\'+r.status+\'" data-name="\'+esc(r.customer_name)+\'" data-email="\'+esc(r.customer_email)+\'" data-phone="\'+esc(r.customer_phone||"")+\'" data-address="\'+esc(r.customer_address||"")+\'" data-brand="\'+esc(r.device_brand||"")+\'" data-model="\'+esc(r.device_model||"")+\'" data-pin="\'+esc(r.device_pattern||"")+\'" data-problem="\'+esc(fullProblem)+\'" data-date="\'+dateStr+\'" data-muster="\'+esc(r.muster_image||"")+\'" data-signature="\'+esc(r.signature_image||"")+\'">\'+
            \'<div class="ra-repair-header"><div class="ra-repair-id">#\'+r.id+\'</div><span class="ra-status \'+st[1]+\'">\'+st[0]+\'</span></div>\'+
            \'<div class="ra-repair-body">\'+
                \'<div class="ra-repair-customer"><strong>\'+esc(r.customer_name)+\'</strong><span class="ra-repair-meta">\'+esc(r.customer_email)+phone+\'</span>\'+addressHtml+\'</div>\'+
                deviceHtml+pinHtml+musterHtml+
                \'<div class="ra-repair-problem">\'+esc(problem)+\'</div>\'+
                \'<div class="ra-repair-date"><i class="ri-time-line"></i> \'+dateStr+\'</div>\'+
            \'</div>\'+
            \'<div class="ra-repair-actions"><button class="ra-btn-print" title="Ausdrucken"><i class="ri-printer-line"></i></button><button class="ra-btn-email" title="Per E-Mail senden"><i class="ri-mail-send-line"></i></button><button class="ra-btn-resubmit" title="Nochmal Anliegen"><i class="ri-repeat-line"></i> Nochmal</button><button class="ra-btn-invoice" title="Rechnung erstellen"><i class="ri-file-list-3-line"></i></button><button class="ra-btn-delete" title="L\u00f6schen"><i class="ri-delete-bin-line"></i></button><select class="ra-status-select" data-repair-id="\'+r.id+\'">\'+selectHtml+\'</select></div>\'+
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
        return \'<div class="ra-inv-line"><input type="text" placeholder="Leistung (z.B. Displaytausch)" class="ra-inv-line-desc" value="\'+esc(desc||"")+\'"><input type="number" placeholder="Brutto" step="0.01" min="0" class="ra-inv-line-amount" value="\'+(amt||"")+\'"><button type="button" class="ra-inv-line-remove" title="Entfernen">&times;</button></div>\';
    }

    function showInvoiceModal(repairId,selectEl){
        invoiceRepairId=repairId;
        invoiceSelect=selectEl;
        invoiceEditId=null;
        // Title for create mode
        document.getElementById("ra-inv-modal-title").innerHTML=\'<i class="ri-file-list-3-line"></i> Reparatur abschlie&szlig;en\';
        document.getElementById("ra-inv-modal-subtitle").textContent="Leistungen und Bruttobetr\u00e4ge f\u00fcr die Rechnung eingeben";
        document.getElementById("ra-inv-modal-submit").innerHTML=\'<i class="ri-check-line"></i> Abschlie\u00dfen & Rechnung\';
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
        // Reset payment fields
        document.getElementById("ra-inv-paid-toggle").checked=false;
        document.getElementById("ra-inv-paid-fields").style.display="none";
        document.getElementById("ra-inv-payment-method").value="";
        document.getElementById("ra-inv-paid-date").value=new Date().toISOString().split("T")[0];
        recalcInvoiceModal();
        invoiceModal.classList.add("show");
        document.querySelector("#ra-inv-lines .ra-inv-line-desc").focus();
    }

    // Toggle payment fields visibility
    document.getElementById("ra-inv-paid-toggle").addEventListener("change",function(){
        document.getElementById("ra-inv-paid-fields").style.display=this.checked?"block":"none";
    });

    function showEditInvoiceModal(inv){
        // Use new edit modal with customer fields
        var modal=document.getElementById("ra-edit-invoice-modal");
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
        document.getElementById("ra-einv-differenz").checked=!!parseInt(inv.is_differenzbesteuerung);

        // Fill line items
        var lines=document.getElementById("ra-einv-lines");
        var items=[];
        try{items=JSON.parse(inv.line_items||"[]")}catch(e){}
        if(items&&items.length>0){
            lines.innerHTML=items.map(function(it){return buildLineHtml(it.description,it.amount)}).join("");
        }else{
            lines.innerHTML=buildLineHtml("",inv.subtotal||"");
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
        var net,vat;
        if(VAT_ENABLED){
            net=Math.round(brutto/(1+VAT_RATE/100)*100)/100;
            vat=Math.round((brutto-net)*100)/100;
        }else{
            net=brutto;
            vat=0;
        }
        document.getElementById("ra-inv-modal-net").textContent=fmtEur(net);
        document.getElementById("ra-inv-modal-vat").textContent=fmtEur(vat);
        document.getElementById("ra-inv-modal-total").textContent=fmtEur(brutto);
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

    document.getElementById("ra-inv-add-line").addEventListener("click",function(){
        var lines=document.getElementById("ra-inv-lines");
        var line=document.createElement("div");
        line.className="ra-inv-line";
        line.innerHTML=\'<input type="text" placeholder="Leistung" class="ra-inv-line-desc"><input type="number" placeholder="Brutto" step="0.01" min="0" class="ra-inv-line-amount"><button type="button" class="ra-inv-line-remove" title="Entfernen">&times;</button>\';
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
                toast("Reparatur als Fertig markiert");
                if(invoiceSelect){
                    updateBadge(invoiceSelect.closest(".ra-repair-card"),"done");
                    invoiceSelect.value="done";
                    invoiceSelect.setAttribute("data-prev","done");
                }
                hideInvoiceModal();
            }else{
                toast(data.data&&data.data.message?data.data.message:"Fehler");
            }
        })
        .catch(function(){toast("Verbindungsfehler")})
        .finally(function(){
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-check-line"></i> Nur Fertig\';
        });
    });

    document.getElementById("ra-inv-modal-submit").addEventListener("click",function(){
        var items=[];
        document.querySelectorAll("#ra-inv-lines .ra-inv-line").forEach(function(line){
            var desc=line.querySelector(".ra-inv-line-desc").value.trim();
            var amt=parseFloat(line.querySelector(".ra-inv-line-amount").value)||0;
            if(desc||amt>0)items.push({description:desc,amount:amt});
        });
        if(items.length===0){toast("Bitte mindestens eine Position eingeben");return}
        var totalAmt=0;
        items.forEach(function(i){totalAmt+=i.amount});

        var fd=new FormData();
        fd.append("nonce",NONCE);

        // Add differenzbesteuerung flag
        fd.append("is_differenzbesteuerung",document.getElementById("ra-inv-differenz").checked?"1":"0");

        if(invoiceEditId){
            // Edit mode
            fd.append("action","ppv_repair_invoice_update");
            fd.append("invoice_id",invoiceEditId);
            fd.append("subtotal",totalAmt);
            fd.append("line_items",JSON.stringify(items));
        }else{
            // Create mode
            fd.append("action","ppv_repair_update_status");
            fd.append("repair_id",invoiceRepairId);
            fd.append("status","done");
            fd.append("final_cost",totalAmt);
            fd.append("line_items",JSON.stringify(items));
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
                    toast("Rechnung aktualisiert");
                }else{
                    toast("Reparatur abgeschlossen & Rechnung erstellt");
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
                toast(data.data&&data.data.message?data.data.message:"Fehler");
            }
        })
        .catch(function(){toast("Verbindungsfehler")})
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
        var fd=new FormData();
        fd.append("action","ppv_repair_invoices_list");
        fd.append("nonce",NONCE);
        fd.append("page",page);
        var df=document.getElementById("ra-inv-from").value;
        var dt=document.getElementById("ra-inv-to").value;
        var docType=document.getElementById("ra-inv-type-filter").value;
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
                if(page===1)tbody.innerHTML=\'<tr><td colspan="8" style="text-align:center;padding:32px;color:#9ca3af;">Keine Eintr\u00e4ge vorhanden.</td></tr>\';
            }else{
                d.invoices.forEach(function(inv){
                    var statusMap={draft:["Entwurf","ra-inv-status-draft"],sent:["Gesendet","ra-inv-status-sent"],paid:["Bezahlt","ra-inv-status-paid"],cancelled:["Storniert","ra-inv-status-cancelled"],accepted:["Angenommen","ra-inv-status-paid"],rejected:["Abgelehnt","ra-inv-status-cancelled"],expired:["Abgelaufen","ra-inv-status-cancelled"]};
                    var st=statusMap[inv.status]||["?",""];
                    var date=inv.created_at?new Date(inv.created_at.replace(/-/g,"/")):"";
                    var dateStr=date?pad(date.getDate())+"."+pad(date.getMonth()+1)+"."+date.getFullYear():"";
                    var vatCol=parseInt(inv.is_kleinunternehmer)?"<span style=\'font-size:11px;color:#9ca3af\'>Kl.Unt.</span>":fmtEur(inv.vat_amount);
                    var pdfUrl=AJAX+"?action=ppv_repair_invoice_pdf&invoice_id="+inv.id+"&nonce="+NONCE;

                    // Doc type badge
                    var isAngebot=inv.doc_type==="angebot";
                    var typeBadge=isAngebot
                        ?"<span style=\'display:inline-block;font-size:9px;padding:2px 5px;border-radius:4px;background:#f0fdf4;color:#16a34a;margin-left:6px\'>Angebot</span>"
                        :"<span style=\'display:inline-block;font-size:9px;padding:2px 5px;border-radius:4px;background:#eff6ff;color:#2563eb;margin-left:6px\'>Rechnung</span>";

                    var row=document.createElement("tr");
                    row.setAttribute("data-inv-id",inv.id);
                    row.innerHTML=\'<td><input type="checkbox" class="ra-inv-checkbox" data-inv-id="\'+inv.id+\'" style="width:16px;height:16px;cursor:pointer"></td>\'+
                        \'<td data-label="Nr."><strong>\'+esc(inv.invoice_number)+\'</strong>\'+typeBadge+\'</td>\'+
                        \'<td data-label="Datum">\'+dateStr+\'</td>\'+
                        \'<td data-label="Kunde">\'+esc(inv.customer_name)+\'</td>\'+
                        \'<td data-label="Netto">\'+fmtEur(inv.net_amount)+\'</td>\'+
                        \'<td data-label="MwSt">\'+vatCol+\'</td>\'+
                        \'<td data-label="Gesamt"><strong>\'+fmtEur(inv.total)+\'</strong></td>\'+
                        \'<td data-label="Status"><span class="ra-inv-status \'+st[1]+\'">\'+st[0]+\'</span></td>\'+
                        \'<td data-label=""><div class="ra-inv-actions">\'+
                            \'<a href="\'+pdfUrl+\'" target="_blank" class="ra-inv-btn ra-inv-btn-pdf"><i class="ri-file-pdf-line"></i> PDF</a>\'+
                            \'<button class="ra-inv-btn ra-inv-btn-email" data-inv-id="\'+inv.id+\'" data-customer-email="\'+esc(inv.customer_email||"")+\'" title="E-Mail senden"><i class="ri-mail-send-line"></i></button>\'+
                            (inv.status!=="paid"&&inv.doc_type!=="angebot"?\'<button class="ra-inv-btn ra-inv-btn-reminder" data-inv-id="\'+inv.id+\'" title="Zahlungserinnerung" style="background:#fef3c7;color:#d97706"><i class="ri-alarm-warning-line"></i></button>\':\'\')+
                            \'<button class="ra-inv-btn ra-inv-btn-edit" data-invoice=\\\'\'+JSON.stringify(inv).replace(/\'/g,"&#39;")+\'\\\' title="Bearbeiten"><i class="ri-pencil-line"></i></button>\'+
                            \'<select class="ra-inv-btn ra-inv-status-sel" data-inv-id="\'+inv.id+\'" data-old-status="\'+inv.status+\'" style="padding:5px 8px;font-size:12px;border-radius:6px">\'+
                                \'<option value="draft" \'+(inv.status==="draft"?"selected":"")+\'>Entwurf</option>\'+
                                \'<option value="sent" \'+(inv.status==="sent"?"selected":"")+\'>Gesendet</option>\'+
                                \'<option value="paid" \'+(inv.status==="paid"?"selected":"")+\'>Bezahlt</option>\'+
                                \'<option value="cancelled" \'+(inv.status==="cancelled"?"selected":"")+\'>Storniert</option>\'+
                            \'</select>\'+
                            \'<button class="ra-inv-btn ra-inv-btn-del" data-inv-id="\'+inv.id+\'" title="L&ouml;schen"><i class="ri-delete-bin-line"></i></button>\'+
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
                                    toast(res.success?"Bezahlt markiert":"Fehler");
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
                                toast(res.success?"Status aktualisiert":"Fehler");
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
                        if(!confirm("Rechnung wirklich l\u00f6schen?"))return;
                        var iid=this.getAttribute("data-inv-id");
                        var row=this.closest("tr");
                        var fd2=new FormData();
                        fd2.append("action","ppv_repair_invoice_delete");
                        fd2.append("nonce",NONCE);
                        fd2.append("invoice_id",iid);
                        fetch(AJAX,{method:"POST",body:fd2,credentials:"same-origin"})
                        .then(function(r){return r.json()})
                        .then(function(res){
                            if(res.success){row.remove();toast("Rechnung gel\u00f6scht");invoicesLoaded=false;loadInvoices(1)}
                            else{toast(res.data&&res.data.message?res.data.message:"Fehler")}
                        });
                    });
                });
                // Bind email send
                tbody.querySelectorAll(".ra-inv-btn-email").forEach(function(btn){
                    btn.addEventListener("click",function(){
                        var iid=this.getAttribute("data-inv-id");
                        var email=this.getAttribute("data-customer-email");
                        if(!email){toast("Keine E-Mail-Adresse vorhanden");return;}
                        if(!confirm("Rechnung per E-Mail an "+email+" senden?"))return;
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
                            if(res.success){toast("E-Mail erfolgreich gesendet!");invoicesLoaded=false;loadInvoices(1)}
                            else{toast(res.data&&res.data.message?res.data.message:"Fehler beim Senden")}
                        });
                    });
                });
                // Reminder button handler
                tbody.querySelectorAll(".ra-inv-btn-reminder").forEach(function(btn){
                    btn.addEventListener("click",function(){
                        var iid=this.getAttribute("data-inv-id");
                        if(!confirm("Zahlungserinnerung an den Kunden senden?"))return;
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
                            else{toast(res.data&&res.data.message?res.data.message:"Fehler beim Senden")}
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
            btn.innerHTML=\'<i class="ri-bar-chart-box-line"></i> Statistik ausblenden\';
            btn.classList.add("active");
        }else{
            summary.style.display="none";
            btn.innerHTML=\'<i class="ri-bar-chart-box-line"></i> Statistik anzeigen\';
            btn.classList.remove("active");
        }
    });

    // Invoice filter
    document.getElementById("ra-inv-filter-btn").addEventListener("click",function(){loadInvoices(1)});
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
            toast("Export wird vorbereitet...");
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
                    toast("Export erfolgreich!");
                }else{
                    toast(res.data&&res.data.message?res.data.message:"Export fehlgeschlagen");
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
    function updateBulkSelection(){
        var selected=getSelectedInvoiceIds();
        var bulkBar=document.getElementById("ra-inv-bulk-bar");
        var countEl=document.getElementById("ra-inv-selected-count");
        if(selected.length>0){
            bulkBar.style.display="block";
            countEl.textContent=selected.length;
        }else{
            bulkBar.style.display="none";
        }
    }
    // Select all checkbox
    document.getElementById("ra-inv-select-all").addEventListener("change",function(){
        var checked=this.checked;
        document.querySelectorAll(".ra-inv-checkbox").forEach(function(cb){cb.checked=checked});
        updateBulkSelection();
    });
    // Bulk cancel
    document.getElementById("ra-bulk-cancel").addEventListener("click",function(){
        document.querySelectorAll(".ra-inv-checkbox").forEach(function(cb){cb.checked=false});
        document.getElementById("ra-inv-select-all").checked=false;
        updateBulkSelection();
    });
    // Bulk mark as paid
    document.getElementById("ra-bulk-paid").addEventListener("click",function(){
        var ids=getSelectedInvoiceIds();
        if(!ids.length)return;
        if(!confirm(ids.length+" Rechnung(en) als bezahlt markieren?"))return;
        var btn=this;btn.disabled=true;btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> Wird verarbeitet...\';
        var fd=new FormData();
        fd.append("action","ppv_repair_invoice_bulk");
        fd.append("nonce",NONCE);
        fd.append("operation","mark_paid");
        fd.append("invoice_ids",JSON.stringify(ids));
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(res){
            btn.disabled=false;btn.innerHTML=\'<i class="ri-checkbox-circle-line"></i> Als bezahlt\';
            if(res.success){toast(res.data.message||"Erfolgreich!");invoicesLoaded=false;loadInvoices(1)}
            else{toast(res.data&&res.data.message?res.data.message:"Fehler")}
        });
    });
    // Bulk send emails
    document.getElementById("ra-bulk-send").addEventListener("click",function(){
        var ids=getSelectedInvoiceIds();
        if(!ids.length)return;
        if(!confirm(ids.length+" Rechnung(en) per E-Mail versenden?"))return;
        var btn=this;btn.disabled=true;btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> Wird gesendet...\';
        var fd=new FormData();
        fd.append("action","ppv_repair_invoice_bulk");
        fd.append("nonce",NONCE);
        fd.append("operation","send_email");
        fd.append("invoice_ids",JSON.stringify(ids));
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(res){
            btn.disabled=false;btn.innerHTML=\'<i class="ri-mail-send-line"></i> E-Mails senden\';
            if(res.success){toast(res.data.message||"Erfolgreich!");invoicesLoaded=false;loadInvoices(1)}
            else{toast(res.data&&res.data.message?res.data.message:"Fehler")}
        });
    });
    // Bulk reminders
    document.getElementById("ra-bulk-reminder").addEventListener("click",function(){
        var ids=getSelectedInvoiceIds();
        if(!ids.length)return;
        if(!confirm(ids.length+" Zahlungserinnerung(en) senden?"))return;
        var btn=this;btn.disabled=true;btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> Wird gesendet...\';
        var fd=new FormData();
        fd.append("action","ppv_repair_invoice_bulk");
        fd.append("nonce",NONCE);
        fd.append("operation","send_reminder");
        fd.append("invoice_ids",JSON.stringify(ids));
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(res){
            btn.disabled=false;btn.innerHTML=\'<i class="ri-alarm-warning-line"></i> Erinnerungen\';
            if(res.success){toast(res.data.message||"Erfolgreich!")}
            else{toast(res.data&&res.data.message?res.data.message:"Fehler")}
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
            if(!ids.length){toast("Keine Rechnungen ausgewählt");return;}
            var format=this.getAttribute("data-format");
            exportDropdown.style.display="none";
            toast("Export wird vorbereitet...");
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
                    toast("Export erfolgreich!");
                }else{
                    toast(res.data&&res.data.message?res.data.message:"Export fehlgeschlagen");
                }
            });
        });
    });

    // Bulk delete
    document.getElementById("ra-bulk-delete").addEventListener("click",function(){
        var ids=getSelectedInvoiceIds();
        if(!ids.length)return;
        if(!confirm("Möchten Sie "+ids.length+" Dokument(e) wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden!"))return;
        var btn=this;btn.disabled=true;btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> Wird gelöscht...\';
        var fd=new FormData();
        fd.append("action","ppv_repair_invoice_bulk");
        fd.append("nonce",NONCE);
        fd.append("operation","delete");
        fd.append("invoice_ids",JSON.stringify(ids));
        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(res){
            btn.disabled=false;btn.innerHTML=\'<i class="ri-delete-bin-line"></i> Löschen\';
            if(res.success){toast(res.data.message||"Erfolgreich gelöscht!");invoicesLoaded=false;loadInvoices(1);updateBulkBar()}
            else{toast(res.data&&res.data.message?res.data.message:"Fehler")}
        });
    });

    /* ===== Settings Form ===== */
    console.log("DEBUG: Looking for settings form...");
    var settingsFormEl = document.getElementById("ra-settings-form");
    console.log("DEBUG: Settings form element:", settingsFormEl);
    if(settingsFormEl){
        settingsFormEl.addEventListener("submit",function(e){
            console.log("DEBUG: Form submit triggered!");
            e.preventDefault();
            var fd=new FormData(this);
            console.log("DEBUG: FormData created, AJAX URL:", AJAX);
            // Collect field config
            var fc={};
            document.querySelectorAll("#ra-field-config .ra-field-row").forEach(function(row){
                var cb=row.querySelector("input[type=checkbox]");
                var inp=row.querySelector("input[type=text]");
                if(cb&&inp){
                    fc[cb.getAttribute("data-field")]={enabled:cb.checked,label:inp.value};
                }
            });
            fd.append("repair_field_config",JSON.stringify(fc));
            fd.append("action","ppv_repair_save_settings");
            fd.append("nonce",NONCE);
            console.log("DEBUG: Sending fetch request...");
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){console.log("DEBUG: Response received:", r.status);return r.json()})
            .then(function(data){
                console.log("DEBUG: JSON data:", data);
                if(data.success){
                    toast("Einstellungen gespeichert");
                    // Visual feedback on button
                    var btn=settingsFormEl.querySelector("button[type=submit]");
                    if(btn){
                        var orig=btn.innerHTML;
                        btn.innerHTML=\'<i class="ri-check-line"></i> Gespeichert!\';
                        btn.style.background="#10b981";
                        setTimeout(function(){btn.innerHTML=orig;btn.style.background=""},2000);
                    }
                }else{
                    toast("Fehler beim Speichern");
                }
            })
            .catch(function(err){console.log("DEBUG: Error:", err);toast("Verbindungsfehler")});
        });
    }else{
        console.log("DEBUG: Settings form NOT FOUND!");
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
                toast("Logo hochgeladen");
            }else{
                toast(data.data&&data.data.message?data.data.message:"Upload fehlgeschlagen");
            }
        })
        .catch(function(){toast("Upload fehlgeschlagen")});
    });

    /* ===== NEW INVOICE MODAL ===== */
    var ninvModal=document.getElementById("ra-new-invoice-modal");
    var ninvSearchTimer=null;

    document.getElementById("ra-new-invoice-btn").addEventListener("click",function(){
        clearNewInvoiceForm();
        ninvModal.classList.add("show");
    });
    document.getElementById("ra-ninv-cancel").addEventListener("click",function(){
        ninvModal.classList.remove("show");
    });
    ninvModal.addEventListener("click",function(e){
        if(e.target===ninvModal)ninvModal.classList.remove("show");
    });

    function clearNewInvoiceForm(){
        document.getElementById("ra-ninv-customer-id").value="";
        document.getElementById("ra-ninv-name").value="";
        document.getElementById("ra-ninv-company").value="";
        document.getElementById("ra-ninv-email").value="";
        document.getElementById("ra-ninv-phone").value="";
        document.getElementById("ra-ninv-address").value="";
        document.getElementById("ra-ninv-plz").value="";
        document.getElementById("ra-ninv-city").value="";
        document.getElementById("ra-ninv-taxid").value="";
        document.getElementById("ra-ninv-number").value="";
        document.getElementById("ra-ninv-notes").value="";
        var lines=document.getElementById("ra-ninv-lines");
        lines.innerHTML=\'<div class="ra-inv-line"><input type="text" placeholder="Leistung" class="ra-inv-line-desc"><input type="number" placeholder="Brutto" step="0.01" min="0" class="ra-inv-line-amount"><button type="button" class="ra-inv-line-remove" title="Entfernen">&times;</button></div>\';
        updateNinvTotals();
    }

    // Customer search
    document.getElementById("ra-ninv-customer-search").addEventListener("input",function(){
        var q=this.value.trim();
        if(ninvSearchTimer)clearTimeout(ninvSearchTimer);
        if(q.length<2){document.getElementById("ra-ninv-customer-results").style.display="none";return;}
        ninvSearchTimer=setTimeout(function(){
            var fd=new FormData();
            fd.append("action","ppv_repair_customer_search");
            fd.append("nonce",NONCE);
            fd.append("search",q);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                var res=document.getElementById("ra-ninv-customer-results");
                if(!data.success||!data.data.customers||data.data.customers.length===0){
                    res.style.display="none";return;
                }
                res.innerHTML="";
                data.data.customers.forEach(function(c){
                    var div=document.createElement("div");
                    div.style.cssText="padding:10px 12px;cursor:pointer;border-bottom:1px solid #f3f4f6";
                    var isRepairSource=c.source==="repair"||parseInt(c.id)<0;
                    var badge=isRepairSource
                        ?"<span style=\'display:inline-block;font-size:9px;padding:1px 4px;border-radius:4px;background:#fef3c7;color:#92400e;margin-left:4px;\'>Formular</span>"
                        :"<span style=\'display:inline-block;font-size:9px;padding:1px 4px;border-radius:4px;background:#d1fae5;color:#065f46;margin-left:4px;\'>Gespeichert</span>";
                    div.innerHTML="<strong>"+esc(c.name)+"</strong>"+badge+(c.company_name?" <span style=\'color:#6b7280\'>("+esc(c.company_name)+")</span>":"")+"<br><span style=\'font-size:12px;color:#9ca3af\'>"+(c.email||"")+(c.phone?" &middot; "+c.phone:"")+"</span>";
                    div.addEventListener("click",function(){
                        // For repair-sourced customers (negative ID), don\'t set customer_id
                        document.getElementById("ra-ninv-customer-id").value=isRepairSource?"":c.id;
                        document.getElementById("ra-ninv-name").value=c.name||"";
                        document.getElementById("ra-ninv-company").value=c.company_name||"";
                        document.getElementById("ra-ninv-email").value=c.email||"";
                        document.getElementById("ra-ninv-phone").value=c.phone||"";
                        document.getElementById("ra-ninv-address").value=c.address||"";
                        document.getElementById("ra-ninv-plz").value=c.plz||"";
                        document.getElementById("ra-ninv-city").value=c.city||"";
                        document.getElementById("ra-ninv-taxid").value=c.tax_id||"";
                        document.getElementById("ra-ninv-customer-search").value="";
                        res.style.display="none";
                    });
                    div.addEventListener("mouseenter",function(){this.style.background="#f9fafb"});
                    div.addEventListener("mouseleave",function(){this.style.background=""});
                    res.appendChild(div);
                });
                res.style.display="block";
            });
        },300);
    });

    // Add line
    document.getElementById("ra-ninv-add-line").addEventListener("click",function(){
        var line=document.createElement("div");
        line.className="ra-inv-line";
        line.innerHTML=\'<input type="text" placeholder="Leistung" class="ra-inv-line-desc"><input type="number" placeholder="Brutto" step="0.01" min="0" class="ra-inv-line-amount"><button type="button" class="ra-inv-line-remove" title="Entfernen">&times;</button>\';
        document.getElementById("ra-ninv-lines").appendChild(line);
    });

    // Remove line + totals
    document.getElementById("ra-ninv-lines").addEventListener("click",function(e){
        if(e.target.classList.contains("ra-inv-line-remove")){
            var lines=this.querySelectorAll(".ra-inv-line");
            if(lines.length>1)e.target.closest(".ra-inv-line").remove();
            updateNinvTotals();
        }
    });
    document.getElementById("ra-ninv-lines").addEventListener("input",function(){updateNinvTotals()});

    function updateNinvTotals(){
        var brutto=0;
        document.querySelectorAll("#ra-ninv-lines .ra-inv-line-amount").forEach(function(inp){
            brutto+=parseFloat(inp.value)||0;
        });
        var net,vat;
        if(VAT_ENABLED){
            net=(brutto/(1+VAT_RATE/100)).toFixed(2);
            vat=(brutto-net).toFixed(2);
        }else{
            net=brutto.toFixed(2);vat="0.00";
        }
        document.getElementById("ra-ninv-net").textContent=fmtEur(net);
        document.getElementById("ra-ninv-vat").textContent=fmtEur(vat);
        document.getElementById("ra-ninv-total").textContent=fmtEur(brutto);
        if(!VAT_ENABLED)document.getElementById("ra-ninv-vat-row").style.display="none";
    }

    // Submit new invoice
    document.getElementById("ra-ninv-submit").addEventListener("click",function(){
        var name=document.getElementById("ra-ninv-name").value.trim();
        if(!name){toast("Name ist erforderlich");return;}
        var items=[];
        document.querySelectorAll("#ra-ninv-lines .ra-inv-line").forEach(function(line){
            var d=line.querySelector(".ra-inv-line-desc").value.trim();
            var a=parseFloat(line.querySelector(".ra-inv-line-amount").value)||0;
            if(d||a>0)items.push({description:d,amount:a});
        });
        var subtotal=0;
        items.forEach(function(i){subtotal+=i.amount});

        var fd=new FormData();
        fd.append("action","ppv_repair_invoice_create");
        fd.append("nonce",NONCE);
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
        fd.append("is_differenzbesteuerung",document.getElementById("ra-ninv-differenz").checked?"1":"0");
        var customInvNum=document.getElementById("ra-ninv-number").value.trim();
        if(customInvNum)fd.append("invoice_number",customInvNum);

        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){
                toast("Rechnung "+data.data.invoice_number+" erstellt");
                ninvModal.classList.remove("show");
                invoicesLoaded=false;
                loadInvoices(1);
            }else{
                toast(data.data&&data.data.message?data.data.message:"Fehler");
            }
        })
        .catch(function(){toast("Verbindungsfehler")});
    });

    /* ===== NEW ANGEBOT MODAL ===== */
    var nangModal=document.getElementById("ra-new-angebot-modal");
    var nangSearchTimer=null;

    document.getElementById("ra-new-angebot-btn").addEventListener("click",function(){
        clearNewAngebotForm();
        nangModal.classList.add("show");
    });
    document.getElementById("ra-nang-cancel").addEventListener("click",function(){
        nangModal.classList.remove("show");
    });
    nangModal.addEventListener("click",function(e){
        if(e.target===nangModal)nangModal.classList.remove("show");
    });

    function clearNewAngebotForm(){
        document.getElementById("ra-nang-customer-id").value="";
        document.getElementById("ra-nang-name").value="";
        document.getElementById("ra-nang-company").value="";
        document.getElementById("ra-nang-email").value="";
        document.getElementById("ra-nang-phone").value="";
        document.getElementById("ra-nang-address").value="";
        document.getElementById("ra-nang-plz").value="";
        document.getElementById("ra-nang-city").value="";
        document.getElementById("ra-nang-notes").value="";
        // Default valid_until: 30 days from now
        var d=new Date();d.setDate(d.getDate()+30);
        document.getElementById("ra-nang-valid-until").value=d.toISOString().split("T")[0];
        var lines=document.getElementById("ra-nang-lines");
        lines.innerHTML=\'<div class="ra-inv-line"><input type="text" placeholder="Leistung" class="ra-inv-line-desc"><input type="number" placeholder="Brutto" step="0.01" min="0" class="ra-inv-line-amount"><button type="button" class="ra-inv-line-remove" title="Entfernen">&times;</button></div>\';
        updateNangTotals();
    }

    // Customer search for Angebot
    document.getElementById("ra-nang-customer-search").addEventListener("input",function(){
        var q=this.value.trim();
        if(nangSearchTimer)clearTimeout(nangSearchTimer);
        if(q.length<2){document.getElementById("ra-nang-customer-results").style.display="none";return;}
        nangSearchTimer=setTimeout(function(){
            var fd=new FormData();
            fd.append("action","ppv_repair_customer_search");
            fd.append("nonce",NONCE);
            fd.append("search",q);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                var res=document.getElementById("ra-nang-customer-results");
                if(!data.success||!data.data.customers||data.data.customers.length===0){
                    res.style.display="none";return;
                }
                res.innerHTML="";
                data.data.customers.forEach(function(c){
                    var div=document.createElement("div");
                    div.style.cssText="padding:10px 12px;cursor:pointer;border-bottom:1px solid #f3f4f6";
                    div.innerHTML="<strong>"+esc(c.name)+"</strong>"+(c.company_name?" <span style=\'color:#6b7280\'>("+esc(c.company_name)+")</span>":"")+"<br><span style=\'font-size:12px;color:#9ca3af\'>"+(c.email||"")+(c.phone?" &middot; "+c.phone:"")+"</span>";
                    div.addEventListener("click",function(){
                        document.getElementById("ra-nang-customer-id").value=c.id||"";
                        document.getElementById("ra-nang-name").value=c.name||"";
                        document.getElementById("ra-nang-company").value=c.company_name||"";
                        document.getElementById("ra-nang-email").value=c.email||"";
                        document.getElementById("ra-nang-phone").value=c.phone||"";
                        document.getElementById("ra-nang-address").value=c.address||"";
                        document.getElementById("ra-nang-plz").value=c.plz||"";
                        document.getElementById("ra-nang-city").value=c.city||"";
                        document.getElementById("ra-nang-customer-search").value="";
                        res.style.display="none";
                    });
                    div.addEventListener("mouseenter",function(){this.style.background="#f9fafb"});
                    div.addEventListener("mouseleave",function(){this.style.background=""});
                    res.appendChild(div);
                });
                res.style.display="block";
            });
        },300);
    });

    // Add line for Angebot
    document.getElementById("ra-nang-add-line").addEventListener("click",function(){
        var line=document.createElement("div");
        line.className="ra-inv-line";
        line.innerHTML=\'<input type="text" placeholder="Leistung" class="ra-inv-line-desc"><input type="number" placeholder="Brutto" step="0.01" min="0" class="ra-inv-line-amount"><button type="button" class="ra-inv-line-remove" title="Entfernen">&times;</button>\';
        document.getElementById("ra-nang-lines").appendChild(line);
    });

    // Remove line + totals for Angebot
    document.getElementById("ra-nang-lines").addEventListener("click",function(e){
        if(e.target.classList.contains("ra-inv-line-remove")){
            var lines=this.querySelectorAll(".ra-inv-line");
            if(lines.length>1)e.target.closest(".ra-inv-line").remove();
            updateNangTotals();
        }
    });
    document.getElementById("ra-nang-lines").addEventListener("input",function(){updateNangTotals()});

    function updateNangTotals(){
        var brutto=0;
        document.querySelectorAll("#ra-nang-lines .ra-inv-line-amount").forEach(function(inp){
            brutto+=parseFloat(inp.value)||0;
        });
        var net,vat;
        if(VAT_ENABLED){
            net=(brutto/(1+VAT_RATE/100)).toFixed(2);
            vat=(brutto-net).toFixed(2);
        }else{
            net=brutto.toFixed(2);vat="0.00";
        }
        document.getElementById("ra-nang-net").textContent=fmtEur(net);
        document.getElementById("ra-nang-vat").textContent=fmtEur(vat);
        document.getElementById("ra-nang-total").textContent=fmtEur(brutto);
        if(!VAT_ENABLED)document.getElementById("ra-nang-vat-row").style.display="none";
    }

    // Submit new Angebot
    document.getElementById("ra-nang-submit").addEventListener("click",function(){
        var name=document.getElementById("ra-nang-name").value.trim();
        if(!name){toast("Name ist erforderlich");return;}
        var items=[];
        document.querySelectorAll("#ra-nang-lines .ra-inv-line").forEach(function(line){
            var d=line.querySelector(".ra-inv-line-desc").value.trim();
            var a=parseFloat(line.querySelector(".ra-inv-line-amount").value)||0;
            if(d||a>0)items.push({description:d,amount:a});
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

        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){
                toast("Angebot "+data.data.angebot_number+" erstellt");
                nangModal.classList.remove("show");
                invoicesLoaded=false;
                loadInvoices(1);
            }else{
                toast(data.data&&data.data.message?data.data.message:"Fehler");
            }
        })
        .catch(function(){toast("Verbindungsfehler")});
    });

    /* ===== EDIT INVOICE MODAL ===== */
    var editInvModal=document.getElementById("ra-edit-invoice-modal");

    function recalcEditInvoiceModal(){
        var amounts=document.querySelectorAll("#ra-einv-lines .ra-inv-line-amount");
        var brutto=0;
        amounts.forEach(function(a){brutto+=parseFloat(a.value)||0});
        var net,vat;
        if(VAT_ENABLED){
            net=Math.round(brutto/(1+VAT_RATE/100)*100)/100;
            vat=Math.round((brutto-net)*100)/100;
        }else{
            net=brutto;
            vat=0;
        }
        document.getElementById("ra-einv-net").textContent=fmtEur(net);
        document.getElementById("ra-einv-vat").textContent=fmtEur(vat);
        document.getElementById("ra-einv-total").textContent=fmtEur(brutto);
    }

    document.getElementById("ra-einv-cancel").addEventListener("click",function(){
        editInvModal.classList.remove("show");
    });
    editInvModal.addEventListener("click",function(e){
        if(e.target===editInvModal)editInvModal.classList.remove("show");
    });

    // Add line
    document.getElementById("ra-einv-add-line").addEventListener("click",function(){
        var line=document.createElement("div");
        line.className="ra-inv-line";
        line.innerHTML=\'<input type="text" placeholder="Leistung" class="ra-inv-line-desc"><input type="number" placeholder="Brutto" step="0.01" min="0" class="ra-inv-line-amount"><button type="button" class="ra-inv-line-remove" title="Entfernen">&times;</button>\';
        document.getElementById("ra-einv-lines").appendChild(line);
    });

    // Remove line + recalc
    document.getElementById("ra-einv-lines").addEventListener("click",function(e){
        if(e.target.classList.contains("ra-inv-line-remove")){
            var lines=this.querySelectorAll(".ra-inv-line");
            if(lines.length>1)e.target.closest(".ra-inv-line").remove();
            recalcEditInvoiceModal();
        }
    });
    document.getElementById("ra-einv-lines").addEventListener("input",function(){recalcEditInvoiceModal()});

    // Submit edit
    document.getElementById("ra-einv-submit").addEventListener("click",function(){
        var name=document.getElementById("ra-einv-name").value.trim();
        if(!name){toast("Kundenname ist erforderlich");return;}

        // Collect line items
        var items=[];
        var subtotal=0;
        document.querySelectorAll("#ra-einv-lines .ra-inv-line").forEach(function(line){
            var desc=line.querySelector(".ra-inv-line-desc").value.trim();
            var amt=parseFloat(line.querySelector(".ra-inv-line-amount").value)||0;
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
        fd.append("is_differenzbesteuerung",document.getElementById("ra-einv-differenz").checked?"1":"0");
        fd.append("line_items",JSON.stringify(items));
        fd.append("subtotal",subtotal);

        fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success){
                toast("Rechnung aktualisiert");
                editInvModal.classList.remove("show");
                invoicesLoaded=false;
                loadInvoices(1);
            }else{
                toast(data.data&&data.data.message?data.data.message:"Fehler");
            }
        })
        .catch(function(){toast("Verbindungsfehler")});
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
                if(page===1)tbody.innerHTML=\'<tr><td colspan="6" style="text-align:center;padding:32px;color:#9ca3af;">Keine Kunden vorhanden.</td></tr>\';
            }else{
                d.customers.forEach(function(c){
                    var row=document.createElement("tr");
                    var isRepairSource=c.source==="repair"||parseInt(c.id)<0;
                    var sourceBadge=isRepairSource
                        ?\'<span style="display:inline-block;font-size:10px;padding:2px 6px;border-radius:6px;background:#fef3c7;color:#92400e;margin-left:6px;">Formular</span>\'
                        :\'<span style="display:inline-block;font-size:10px;padding:2px 6px;border-radius:6px;background:#d1fae5;color:#065f46;margin-left:6px;">Gespeichert</span>\';
                    var repairCount=c.repair_count>0?\'<span style="font-size:11px;color:#6b7280;margin-left:4px;">(\'+c.repair_count+\' Auftr&auml;ge)</span>\':\'\';
                    var actions=\'<button class="ra-inv-btn ra-inv-btn-edit ra-cust-edit" data-cust=\\\'\'+JSON.stringify(c).replace(/\'/g,"&#39;")+\'\\\' title="Bearbeiten"><i class="ri-pencil-line"></i></button>\'+
                        \'<button class="ra-inv-btn ra-inv-btn-del ra-cust-del" data-cust-id="\'+c.id+\'" data-cust-source="\'+c.source+\'" title="L&ouml;schen"><i class="ri-delete-bin-line"></i></button>\';
                    row.innerHTML=\'<td data-label="Name"><strong>\'+esc(c.name)+\'</strong>\'+sourceBadge+repairCount+\'</td>\'+
                        \'<td data-label="Firma">\'+esc(c.company_name||"-")+\'</td>\'+
                        \'<td data-label="E-Mail">\'+esc(c.email||"-")+\'</td>\'+
                        \'<td data-label="Telefon">\'+esc(c.phone||"-")+\'</td>\'+
                        \'<td data-label="Stadt">\'+esc(c.city||"-")+\'</td>\'+
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
                toast("Formular-Kunden k\u00f6nnen nicht gel\u00f6scht werden");
                return;
            }
            if(!confirm("Kunde wirklich l\u00f6schen?"))return;
            var fd=new FormData();
            fd.append("action","ppv_repair_customer_delete");
            fd.append("nonce",NONCE);
            fd.append("customer_id",custId);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success){
                    toast("Kunde gel\u00f6scht");
                    loadCustomers(1);
                }else{
                    toast(data.data&&data.data.message?data.data.message:"Fehler");
                }
            });
        }
    });

    /* ===== CUSTOMER MODAL ===== */
    var custModal=document.getElementById("ra-customer-modal");

    function openCustomerModal(c){
        // For repair-sourced customers (negative ID), clear ID so it creates a new saved record
        var isRepairSource=c&&(c.source==="repair"||parseInt(c.id)<0);
        var hasValidId=c&&c.id&&parseInt(c.id)>0;
        document.getElementById("ra-cust-modal-title").innerHTML=hasValidId
            ?\'<i class="ri-user-settings-line"></i> Kunde bearbeiten\'
            :(isRepairSource?\'<i class="ri-save-line"></i> Als Kunde speichern\':\'<i class="ri-user-add-line"></i> Neuer Kunde\');
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
        if(!name){toast("Name ist erforderlich");return;}
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
                toast(data.data.message||"Kunde gespeichert");
                custModal.classList.remove("show");
                loadCustomers(1);
            }else{
                toast(data.data&&data.data.message?data.data.message:"Fehler");
            }
        })
        .catch(function(){toast("Verbindungsfehler")});
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
            toast("Nur XML-Dateien erlaubt");
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
                toast(data.data.message||"Import erfolgreich");
                bbModal.classList.remove("show");
                loadInvoices(1);
            }else{
                toast(data.data&&data.data.message?data.data.message:"Import fehlgeschlagen");
                bbSubmit.disabled=false;
            }
        })
        .catch(function(){
            bbProgress.style.display="none";
            toast("Verbindungsfehler");
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
            if(!confirm("Kommentar wirklich löschen?"))return;
            var fd=new FormData();
            fd.append("action","ppv_repair_comment_delete");
            fd.append("nonce",NONCE);
            fd.append("comment_id",cid);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success){
                    btn.closest(".ra-comment-item").remove();
                    toast("Kommentar gelöscht");
                }else{
                    toast(data.data&&data.data.message?data.data.message:"Fehler");
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
                    toast("Kommentar hinzugefügt");
                }else{
                    toast(data.data&&data.data.message?data.data.message:"Fehler");
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
                    list.innerHTML=\'<div class="ra-comments-empty">Noch keine Kommentare</div>\';
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
                list.innerHTML=\'<div class="ra-comments-empty">Fehler beim Laden</div>\';
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
            if(!confirm("Möchten Sie Ihr Abo wirklich kündigen?\\n\\nIhr Abo bleibt bis zum Ende der bezahlten Periode aktiv.")){
                return;
            }
            cancelSubBtn.disabled=true;
            cancelSubBtn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> Wird bearbeitet...\';
            fetch("/wp-json/punktepass/v1/repair/cancel-subscription",{
                method:"POST",
                headers:{"Content-Type":"application/json"},
                credentials:"same-origin"
            })
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success){
                    toast("Abo wurde gekündigt. Es bleibt bis zum Ablaufdatum aktiv.");
                    setTimeout(function(){location.reload()},2000);
                }else{
                    toast(data.error||data.message||"Fehler bei der Kündigung");
                    cancelSubBtn.disabled=false;
                    cancelSubBtn.innerHTML=\'<i class="ri-close-line"></i> Abo zum Laufzeitende kündigen\';
                }
            })
            .catch(function(){
                toast("Verbindungsfehler");
                cancelSubBtn.disabled=false;
                cancelSubBtn.innerHTML=\'<i class="ri-close-line"></i> Abo zum Laufzeitende kündigen\';
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
            if(!confirm("Belohnung genehmigen?\\n\\nDie Punkte werden abgezogen und der Rabatt wird auf der nächsten Rechnung berücksichtigt."))return;
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
                    toast("Belohnung genehmigt! Rabatt wird auf der Rechnung abgezogen.");
                    setTimeout(function(){location.reload()},1500);
                }else{
                    toast(data.data&&data.data.message?data.data.message:"Fehler");
                    btn.disabled=false;
                    btn.innerHTML=\'<i class="ri-check-line"></i> Genehmigen\';
                }
            })
            .catch(function(){
                toast("Verbindungsfehler");
                btn.disabled=false;
                btn.innerHTML=\'<i class="ri-check-line"></i> Genehmigen\';
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
                alert("Bitte geben Sie einen Grund f\\u00fcr die Ablehnung an.");
                return;
            }
            var btn=this;
            btn.disabled=true;
            btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> Wird gespeichert...\';
            var fd=new FormData();
            fd.append("action","ppv_repair_reward_reject");
            fd.append("nonce",NONCE);
            fd.append("repair_id",rid);
            fd.append("reason",reason);
            fetch(AJAX,{method:"POST",body:fd,credentials:"same-origin"})
            .then(function(r){return r.json()})
            .then(function(data){
                if(data.success){
                    toast("Belohnung abgelehnt.");
                    rejectModal.classList.remove("show");
                    setTimeout(function(){location.reload()},1500);
                }else{
                    toast(data.data&&data.data.message?data.data.message:"Fehler");
                    btn.disabled=false;
                    btn.innerHTML="Ablehnen";
                }
            })
            .catch(function(){
                toast("Verbindungsfehler");
                btn.disabled=false;
                btn.innerHTML="Ablehnen";
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
            msg.textContent="Bitte w\u00e4hlen Sie eine Kategorie.";
            msg.className="ra-fb-msg show error";
            return;
        }
        if(fbCategory==="rating"&&fbRating===0){
            msg.textContent="Bitte geben Sie eine Bewertung ab.";
            msg.className="ra-fb-msg show error";
            return;
        }
        if(!message&&fbCategory!=="rating"){
            msg.textContent="Bitte beschreiben Sie Ihr Anliegen.";
            msg.className="ra-fb-msg show error";
            return;
        }

        btn.disabled=true;
        btn.innerHTML=\'<i class="ri-loader-4-line ri-spin"></i> Wird gesendet...\';

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
                msg.textContent="Vielen Dank f\u00fcr Ihr Feedback!";
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
                msg.textContent=data.data&&data.data.message?data.data.message:"Fehler beim Senden.";
                msg.className="ra-fb-msg show error";
            }
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-send-plane-fill"></i> Feedback senden\';
        })
        .catch(function(){
            msg.textContent="Verbindungsfehler.";
            msg.className="ra-fb-msg show error";
            btn.disabled=false;
            btn.innerHTML=\'<i class="ri-send-plane-fill"></i> Feedback senden\';
        });
    });

    // Restore saved tab on page load (must be after function definitions)
    var savedTab=localStorage.getItem("ra_active_tab");
    if(savedTab&&document.querySelector(\'.ra-tab[data-tab="\'+savedTab+\'"]\'))switchTab(savedTab);

})();
</script>
</body>
</html>';
    }

    /** ============================================================
     * Build HTML for a single repair card (server-side)
     * ============================================================ */
    private static function build_repair_card_html($r, $store = null) {
        global $wpdb;
        $prefix = $wpdb->prefix;

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
            $muster_html = '<div class="ra-repair-muster"><img src="' . esc_attr($r->muster_image) . '" alt="Entsperrmuster" title="Entsperrmuster"></div>';
        }

        // Address display
        $address_html = '';
        if (!empty($r->customer_address)) {
            $address_html = '<div class="ra-repair-address"><i class="ri-map-pin-line"></i> ' . esc_html($r->customer_address) . '</div>';
        }

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
                $reward_display = ($reward_type === 'discount_percent')
                    ? $reward_value . '% Rabatt'
                    : number_format($reward_value, 2, ',', '.') . ' &euro; Rabatt';

                $badge_class = $reward_rejected ? 'ra-reward-badge-rejected' : 'ra-reward-badge-available';

                // Toggle button (always visible)
                $reward_html = '<div class="ra-reward-toggle-section">'
                    . '<button class="ra-btn-reward-toggle ' . $badge_class . '" data-repair-id="' . intval($r->id) . '">'
                        . '<i class="ri-gift-line"></i> ' . ($reward_rejected ? 'Belohnung (abgelehnt)' : 'Belohnung verf&uuml;gbar')
                    . '</button>'
                    . '<div class="ra-reward-container" data-repair-id="' . intval($r->id) . '">';

                if ($reward_rejected) {
                    // Show rejection info with option to reconsider
                    $reward_html .= '<div class="ra-reward-section ra-reward-rejected">'
                        . '<div class="ra-reward-header"><i class="ri-gift-line"></i> Belohnung abgelehnt</div>'
                        . '<div class="ra-reward-info">'
                            . '<span class="ra-reward-badge"><i class="ri-star-fill"></i> ' . $total_points . '/' . $required_points . ' Punkte</span>'
                            . '<span class="ra-reward-name">' . $reward_name . '</span>'
                        . '</div>'
                        . '<div class="ra-reward-rejection-info">'
                            . '<i class="ri-close-circle-line"></i> Grund: ' . esc_html($rejection_reason)
                        . '</div>'
                        . '<div class="ra-reward-actions">'
                            . '<button class="ra-reward-approve" data-repair-id="' . intval($r->id) . '" data-points="' . $required_points . '"><i class="ri-check-line"></i> Doch genehmigen</button>'
                        . '</div>'
                    . '</div>';
                } else {
                    // Show approve/reject buttons
                    $reward_html .= '<div class="ra-reward-section">'
                        . '<div class="ra-reward-header"><i class="ri-gift-line"></i> Belohnung einl&ouml;sen</div>'
                        . '<div class="ra-reward-info">'
                            . '<span class="ra-reward-badge"><i class="ri-star-fill"></i> ' . $total_points . '/' . $required_points . ' Punkte</span>'
                            . '<span class="ra-reward-name">' . $reward_name . ' (' . $reward_display . ')</span>'
                        . '</div>'
                        . '<div class="ra-reward-actions">'
                            . '<button class="ra-reward-approve" data-repair-id="' . intval($r->id) . '" data-points="' . $required_points . '"><i class="ri-check-line"></i> Genehmigen</button>'
                            . '<button class="ra-reward-reject" data-repair-id="' . intval($r->id) . '"><i class="ri-close-line"></i> Ablehnen</button>'
                        . '</div>'
                    . '</div>';
                }

                $reward_html .= '</div></div>';
            } elseif ($reward_approved) {
                // Show approved status (small badge, no toggle needed)
                $reward_html = '<div class="ra-reward-approved-badge">'
                    . '<i class="ri-check-double-line"></i> ' . $reward_name . ' genehmigt'
                . '</div>';
            }
        }

        return '<div class="ra-repair-card" data-id="' . intval($r->id) . '"'
            . ' data-status="' . esc_attr($r->status) . '"'
            . ' data-name="' . esc_attr($r->customer_name) . '"'
            . ' data-email="' . esc_attr($r->customer_email) . '"'
            . ' data-phone="' . esc_attr($r->customer_phone) . '"'
            . ' data-address="' . esc_attr($r->customer_address) . '"'
            . ' data-brand="' . esc_attr($r->device_brand) . '"'
            . ' data-model="' . esc_attr($r->device_model) . '"'
            . ' data-pin="' . esc_attr($r->device_pattern) . '"'
            . ' data-problem="' . esc_attr($r->problem_description) . '"'
            . ' data-date="' . esc_attr($date) . '"'
            . ' data-muster="' . esc_attr($r->muster_image) . '"'
            . ' data-signature="' . esc_attr($r->signature_image) . '">'
            . '<div class="ra-repair-header">'
                . '<div class="ra-repair-id">#' . intval($r->id) . '</div>'
                . '<span class="ra-status ' . esc_attr($st[1]) . '">' . esc_html($st[0]) . '</span>'
            . '</div>'
            . '<div class="ra-repair-body">'
                . '<div class="ra-repair-customer">'
                    . '<strong>' . esc_html($r->customer_name) . '</strong>'
                    . '<span class="ra-repair-meta">' . esc_html($r->customer_email) . $phone . '</span>'
                    . $address_html
                . '</div>'
                . $device_html
                . $pin_html
                . $muster_html
                . '<div class="ra-repair-problem">' . $problem . '</div>'
                . '<div class="ra-repair-date"><i class="ri-time-line"></i> ' . $date . ($updated ? ' <span style="color:#9ca3af;font-size:11px" title="Zuletzt ge&auml;ndert">&middot; <i class="ri-edit-line"></i> ' . $updated . '</span>' : '') . '</div>'
            . '</div>'
            . $reward_html
            . '<div class="ra-repair-comments-section">'
                . '<button class="ra-btn-comments-toggle" data-repair-id="' . intval($r->id) . '"><i class="ri-chat-3-line"></i> Kommentare</button>'
                . '<div class="ra-comments-container" style="display:none" data-repair-id="' . intval($r->id) . '">'
                    . '<div class="ra-comments-list"></div>'
                    . '<div class="ra-comment-add">'
                        . '<input type="text" class="ra-comment-input" placeholder="Kommentar hinzuf&uuml;gen...">'
                        . '<button class="ra-comment-submit"><i class="ri-send-plane-fill"></i></button>'
                    . '</div>'
                . '</div>'
            . '</div>'
            . '<div class="ra-repair-actions">'
                . '<button class="ra-btn-print" title="Ausdrucken"><i class="ri-printer-line"></i></button>'
                . '<button class="ra-btn-email" title="Per E-Mail senden"><i class="ri-mail-send-line"></i></button>'
                . '<button class="ra-btn-resubmit" title="Nochmal Anliegen"><i class="ri-repeat-line"></i> Nochmal</button>'
                . '<button class="ra-btn-invoice" title="Rechnung erstellen"><i class="ri-file-list-3-line"></i></button>'
                . '<button class="ra-btn-delete" title="L&ouml;schen"><i class="ri-delete-bin-line"></i></button>'
                . '<select class="ra-status-select" data-repair-id="' . intval($r->id) . '">' . $options . '</select>'
            . '</div>'
        . '</div>';
    }
}
