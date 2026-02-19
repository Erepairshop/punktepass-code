<?php
/**
 * PunktePass - Public Repair Form (Standalone Page)
 * Renders a complete branded repair form for each store
 * URL: /formular/{shopslug}
 *
 * Multi-language: DE, HU, RO via PPV_Lang
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Form {

    /** ============================================================
     * Render the standalone public repair form page
     * Called from PPV_Repair_Core::handle_repair_page()
     * ============================================================ */
    public static function render_standalone_page($store, $limit_reached = false) {
        // Load repair-specific translations
        PPV_Lang::load_extra('ppv-repair-lang');
        $lang = PPV_Lang::current();

        $store_name = esc_html($store->repair_company_name ?: $store->name);
        $color      = esc_attr($store->repair_color ?: '#667eea');
        $logo       = esc_url($store->logo ?: PPV_PLUGIN_URL . 'assets/img/punktepass-logo.png');
        $slug       = esc_attr($store->store_slug);
        $points     = intval($store->repair_points_per_form ?: 2);
        $store_id   = intval($store->id);
        $address    = esc_html(trim(($store->address ?: '') . ' ' . ($store->plz ?: '') . ' ' . ($store->city ?: '')));

        // Load partner data for co-branding
        $partner = null;
        if (!empty($store->partner_id)) {
            global $wpdb;
            $partner = $wpdb->get_row($wpdb->prepare(
                "SELECT company_name, logo_url, partnership_model FROM {$wpdb->prefix}ppv_partners WHERE id = %d AND status = 'active'",
                intval($store->partner_id)
            ));
        }

        $pp_enabled   = isset($store->repair_punktepass_enabled) ? intval($store->repair_punktepass_enabled) : 1;
        $raw_title    = $store->repair_form_title ?? '';
        $form_title   = esc_html(($raw_title === '' || $raw_title === 'Reparaturauftrag') ? PPV_Lang::t('repair_admin_form_title_ph') : $raw_title);
        $form_subtitle = esc_html($store->repair_form_subtitle ?? '');
        $service_type  = esc_html($store->repair_service_type ?? 'Allgemein');
        $reward_name   = esc_html($store->repair_reward_name ?? '10 Euro Rabatt');
        $required_pts  = intval($store->repair_required_points ?? 4);
        $field_config  = json_decode($store->repair_field_config ?? '', true) ?: [];
        $fc_defaults   = [
            'device_brand'    => ['enabled' => true, 'label' => PPV_Lang::t('repair_brand_label')],
            'device_model'    => ['enabled' => true, 'label' => PPV_Lang::t('repair_model_label')],
            'device_imei'     => ['enabled' => true, 'label' => PPV_Lang::t('repair_imei_label')],
            'device_pattern'  => ['enabled' => true, 'label' => PPV_Lang::t('repair_pin_label')],
            'accessories'     => ['enabled' => true, 'label' => PPV_Lang::t('repair_accessories_label')],
            'customer_phone'  => ['enabled' => true, 'label' => PPV_Lang::t('repair_phone_label')],
            'customer_address'=> ['enabled' => true, 'label' => PPV_Lang::t('repair_address_label')],
            'muster_image'    => ['enabled' => true, 'label' => PPV_Lang::t('repair_pattern_label')],
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

        // Custom form options (branch-specific)
        $custom_brands_raw = trim($store->repair_custom_brands ?? '');
        $custom_brands = $custom_brands_raw ? array_filter(array_map('trim', explode("\n", $custom_brands_raw))) : [];
        $custom_problems_raw = trim($store->repair_custom_problems ?? '');
        $custom_problems = $custom_problems_raw ? array_filter(array_map('trim', explode("\n", $custom_problems_raw))) : [];
        $custom_accessories_raw = trim($store->repair_custom_accessories ?? '');
        $custom_accessories = $custom_accessories_raw ? array_filter(array_map('trim', explode("\n", $custom_accessories_raw))) : [];
        $success_message = trim($store->repair_success_message ?? '');
        $opening_hours = trim($store->repair_opening_hours ?? '');
        $terms_url = trim($store->repair_terms_url ?? '');

        $nonce = wp_create_nonce('ppv_repair_form');
        $ajax_url = admin_url('admin-ajax.php');

        // Prefill from query params (for "Nochmal Anliegen" button)
        $pf_name    = esc_attr($_GET['name'] ?? '');
        $pf_email   = esc_attr($_GET['email'] ?? '');
        $pf_phone   = esc_attr($_GET['phone'] ?? '');
        $pf_address = esc_attr($_GET['address'] ?? '');
        $pf_brand   = esc_attr($_GET['brand'] ?? '');
        $pf_model   = esc_attr($_GET['model'] ?? '');
        $pf_imei    = esc_attr($_GET['imei'] ?? '');
        $pf_pin     = esc_attr($_GET['pin'] ?? '');
        $pf_problem = esc_attr($_GET['problem'] ?? '');

        // Country code for Nominatim address search
        $nominatim_cc = 'de';
        if ($lang === 'hu') $nominatim_cc = 'hu';
        elseif ($lang === 'ro') $nominatim_cc = 'ro';

        // JS translation strings
        $js_strings = json_encode([
            'welcome_back'     => PPV_Lang::t('repair_welcome_back'),
            'error_generic'    => PPV_Lang::t('repair_error_generic'),
            'connection_error' => PPV_Lang::t('repair_connection_error'),
            'offline_saved'    => PPV_Lang::t('repair_offline_saved'),
            'offline_mode'     => PPV_Lang::t('repair_offline_mode'),
            'points_total'     => PPV_Lang::t('repair_points_total'),
            'points_remaining' => PPV_Lang::t('repair_points_remaining'),
            'points_redeemable'=> PPV_Lang::t('repair_points_redeemable'),
            'auto_redirect'    => PPV_Lang::t('repair_auto_redirect'),
            'qr_scan_title'    => PPV_Lang::t('repair_qr_scan_title'),
            'qr_scan_hint'     => PPV_Lang::t('repair_qr_scan_hint'),
            'qr_scan_success'  => PPV_Lang::t('repair_qr_scan_success'),
            'qr_scan_error'    => PPV_Lang::t('repair_qr_scan_error'),
            'qr_scan_no_camera'=> PPV_Lang::t('repair_qr_scan_no_camera'),
            'ai_btn'           => PPV_Lang::t('repair_ai_btn'),
            'ai_analyzing'     => PPV_Lang::t('repair_ai_analyzing'),
            'ai_title'         => PPV_Lang::t('repair_ai_title'),
            'ai_error'         => PPV_Lang::t('repair_ai_error'),
            'ai_hint'          => PPV_Lang::t('repair_ai_hint'),
        ], JSON_UNESCAPED_UNICODE);

        ob_start();
        // Prevent WebView caching (Fully Kiosk)
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <title><?php echo $form_title; ?> - <?php echo $store_name; ?></title>
    <?php echo PPV_SEO::get_form_page_head($store); ?>
    <?php echo PPV_SEO::get_performance_hints(); ?>
    <?php echo PPV_SEO::get_favicon_links($store->logo ?: null); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo PPV_PLUGIN_URL; ?>assets/css/ppv-repair.css?v=<?php echo PPV_VERSION; ?>">
    <style>
    :root{--repair-accent:<?php echo $color; ?>;--repair-accent-dark:color-mix(in srgb,<?php echo $color; ?>,#000 20%);--repair-accent-light:color-mix(in srgb,<?php echo $color; ?>,#fff 30%)}
    .repair-select{width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:10px;font-size:16px;background:#f8fafc;color:#0f172a;outline:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:44px;cursor:pointer;transition:all .2s cubic-bezier(.4,0,.2,1)}
    .repair-select:hover{border-color:#cbd5e1}
    .repair-select:focus{border-color:var(--repair-accent);background:#fff;box-shadow:0 0 0 4px rgba(102,126,234,0.1)}
    .repair-problem-tag{transition:all .2s cubic-bezier(.4,0,.2,1)}
    .repair-problem-tag.active{background:var(--repair-accent)!important;border-color:var(--repair-accent)!important;color:#fff!important;box-shadow:0 4px 12px rgba(102,126,234,0.25)}
    .repair-lang{position:absolute;top:12px;right:16px;z-index:50}
    .repair-lang-btn{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.15);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;color:rgba(255,255,255,.9);cursor:pointer;transition:all .2s;font-family:inherit}
    .repair-lang-btn:hover{background:rgba(255,255,255,.25)}
    .repair-lang-btn i{font-size:14px}
    .repair-lang-dd{display:none;position:absolute;top:100%;right:0;margin-top:4px;background:rgba(30,30,50,.95);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.15);border-radius:8px;overflow:hidden;min-width:110px}
    .repair-lang.open .repair-lang-dd{display:block}
    .repair-lang-dd a{display:block;padding:7px 14px;color:rgba(255,255,255,.85);text-decoration:none;font-size:12px;font-weight:500;transition:background .15s}
    .repair-lang-dd a:hover{background:rgba(255,255,255,.1);color:#fff}

    /* ===== Autocomplete suggestions ===== */
    .rf-suggestions-desktop{display:none;position:absolute;top:100%;left:0;right:0;z-index:999;background:#fff;border:2px solid var(--repair-accent);border-top:none;border-radius:0 0 10px 10px;max-height:200px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,0.12)}
    .rf-sug-item{padding:12px 14px;cursor:pointer;font-size:15px;border-bottom:1px solid #f1f5f9;-webkit-tap-highlight-color:transparent}
    .rf-sug-item:active{background:#f1f5f9}
    .rf-sug-main{font-weight:500;color:#0f172a}
    .rf-sug-sub{font-size:12px;color:#94a3b8;margin-top:2px}

    /* Mobile bottom-sheet overlay for suggestions */
    .rf-sheet-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(0,0,0,.45);-webkit-tap-highlight-color:transparent}
    .rf-sheet-overlay.show{display:flex;flex-direction:column;justify-content:flex-end}
    .rf-sheet{background:#fff;border-radius:16px 16px 0 0;max-height:70vh;display:flex;flex-direction:column;animation:rfSheetUp .25s ease-out}
    @keyframes rfSheetUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
    .rf-sheet-handle{text-align:center;padding:10px 0 4px;flex-shrink:0}
    .rf-sheet-handle span{display:inline-block;width:36px;height:4px;border-radius:2px;background:#d1d5db}
    .rf-sheet-header{display:flex;align-items:center;justify-content:space-between;padding:8px 16px 12px;border-bottom:1px solid #f1f5f9;flex-shrink:0}
    .rf-sheet-header h4{margin:0;font-size:15px;font-weight:600;color:#1e293b}
    .rf-sheet-close{width:32px;height:32px;border:none;background:#f1f5f9;border-radius:50%;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;-webkit-tap-highlight-color:transparent}
    .rf-sheet-close:active{background:#e2e8f0}
    .rf-sheet-list{overflow-y:auto;-webkit-overflow-scrolling:touch;flex:1;overscroll-behavior:contain}
    .rf-sheet-item{padding:14px 16px;border-bottom:1px solid #f1f5f9;cursor:pointer;min-height:48px;display:flex;flex-direction:column;justify-content:center;-webkit-tap-highlight-color:transparent}
    .rf-sheet-item:active{background:#f8fafc}
    .rf-sheet-item-main{font-weight:500;color:#0f172a;font-size:15px}
    .rf-sheet-item-sub{font-size:13px;color:#94a3b8;margin-top:3px}
    .rf-sheet-empty{padding:24px 16px;text-align:center;color:#94a3b8;font-size:14px}
    .rf-sheet-loading{padding:24px 16px;text-align:center;color:#94a3b8;font-size:14px}
    </style>
</head>
<body class="ppv-repair-body">

<div class="repair-page">
    <!-- Hero Header -->
    <div class="repair-header" style="position:relative">
        <?php
        $supported_langs = ['de', 'en', 'hu', 'ro', 'it'];
        $other_langs = array_diff($supported_langs, [$lang]);
        $lang_names = ['de'=>'Deutsch','en'=>'English','hu'=>'Magyar','ro'=>'Română','it'=>'Italiano'];
        ?>
        <div class="repair-lang" onclick="event.stopPropagation();this.classList.toggle('open')" id="repair-lang-switcher">
            <span class="repair-lang-btn">
                <i class="ri-global-line"></i>
                <?php echo strtoupper($lang); ?>
                <i class="ri-arrow-down-s-line" style="font-size:13px;margin-left:-2px"></i>
            </span>
            <div class="repair-lang-dd">
                <?php foreach ($other_langs as $ol): ?>
                <a href="?lang=<?php echo $ol; ?>" onclick="document.cookie='ppv_lang=<?php echo $ol; ?>;path=/;max-age=31536000'"><?php echo $lang_names[$ol]; ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="repair-hero-bg">
            <div class="repair-hero-blob repair-hero-blob--1"></div>
            <div class="repair-hero-blob repair-hero-blob--2"></div>
            <div class="repair-hero-blob repair-hero-blob--3"></div>
        </div>
        <div class="repair-header-inner">
            <?php if ($partner && $partner->logo_url && $partner->partnership_model === 'co_branded'): ?>
                <!-- Co-branded: Partner logo + Store logo side by side -->
                <div class="repair-logo-wrap" style="display:flex;align-items:center;gap:12px;justify-content:center;flex-wrap:wrap">
                    <img src="<?php echo esc_url($partner->logo_url); ?>" alt="<?php echo esc_html($partner->company_name); ?>" class="repair-logo" style="max-height:40px">
                    <span style="color:rgba(255,255,255,0.4);font-size:20px;font-weight:300">&times;</span>
                    <img src="<?php echo $logo; ?>" alt="<?php echo $store_name; ?>" class="repair-logo" style="max-height:40px">
                </div>
            <?php elseif ($partner && $partner->logo_url): ?>
                <!-- Partner logo shown small above store logo -->
                <div style="text-align:center;margin-bottom:6px;opacity:0.7">
                    <img src="<?php echo esc_url($partner->logo_url); ?>" alt="<?php echo esc_html($partner->company_name); ?>" style="max-height:24px;border-radius:4px">
                </div>
                <?php if ($store->logo): ?>
                <div class="repair-logo-wrap">
                    <img src="<?php echo $logo; ?>" alt="<?php echo $store_name; ?>" class="repair-logo">
                </div>
                <?php endif; ?>
            <?php elseif ($store->logo): ?>
                <div class="repair-logo-wrap">
                    <img src="<?php echo $logo; ?>" alt="<?php echo $store_name; ?>" class="repair-logo">
                </div>
            <?php endif; ?>
            <h1 class="repair-shop-name"><?php echo $store_name; ?></h1>
            <?php if ($form_subtitle): ?>
                <p class="repair-hero-subtitle"><?php echo $form_subtitle; ?></p>
            <?php else: ?>
                <p class="repair-hero-subtitle"><?php echo esc_html(sprintf(PPV_Lang::t('repair_default_subtitle'), $form_title)); ?></p>
            <?php endif; ?>
            <?php if ($address): ?>
                <p class="repair-shop-address"><i class="ri-map-pin-line"></i> <?php echo $address; ?></p>
            <?php endif; ?>
            <?php if ($opening_hours): ?>
                <p class="repair-shop-address" style="margin-top:4px"><i class="ri-time-line"></i> <?php echo esc_html($opening_hours); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($limit_reached): ?>
    <!-- Limit reached message -->
    <div class="repair-limit-reached">
        <div class="repair-limit-icon">&#9888;</div>
        <h2><?php echo esc_html(PPV_Lang::t('repair_limit_title')); ?></h2>
        <p><?php echo esc_html(PPV_Lang::t('repair_limit_text')); ?></p>
        <?php if ($store->phone): ?>
            <a href="tel:<?php echo esc_attr($store->phone); ?>" class="repair-btn-phone">
                <i class="ri-phone-line"></i> <?php echo esc_html($store->phone); ?>
            </a>
        <?php endif; ?>
    </div>

    <?php else: ?>

    <!-- QR Scan Button (PunktePass login) -->
    <?php if ($pp_enabled): ?>
    <div class="repair-qr-login" id="repair-qr-login">
        <button type="button" id="repair-qr-scan-btn" class="repair-qr-scan-btn" onclick="ppvQrScan.open()">
            <i class="ri-qr-scan-2-line"></i>
            <?php echo esc_html(PPV_Lang::t('repair_qr_scan_btn')); ?>
        </button>
    </div>

    <!-- QR Camera Modal -->
    <div id="repair-qr-modal" class="repair-qr-modal" style="display:none">
        <div class="repair-qr-modal-inner">
            <div class="repair-qr-modal-header">
                <h3><i class="ri-qr-scan-2-line"></i> <?php echo esc_html(PPV_Lang::t('repair_qr_scan_title')); ?></h3>
                <button type="button" class="repair-qr-modal-close" onclick="ppvQrScan.close()">&times;</button>
            </div>
            <div class="repair-qr-video-wrap">
                <video id="repair-qr-video" playsinline></video>
                <div class="repair-qr-overlay">
                    <div class="repair-qr-frame"></div>
                </div>
            </div>
            <p class="repair-qr-modal-hint"><?php echo esc_html(PPV_Lang::t('repair_qr_scan_hint')); ?></p>
            <div id="repair-qr-status" class="repair-qr-status"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form id="repair-form" class="repair-form" autocomplete="off" enctype="multipart/form-data">
        <input type="hidden" name="action" value="ppv_repair_submit">
        <input type="hidden" name="nonce" value="<?php echo $nonce; ?>">
        <input type="hidden" name="store_id" value="<?php echo $store_id; ?>">
        <input type="hidden" name="qr_user_id" id="rf-qr-user-id" value="">

        <!-- Step 1: Customer info -->
        <div class="repair-section" id="step-customer">
            <div class="repair-section-title">
                <span class="repair-step-num">1</span>
                <h2><?php echo esc_html(PPV_Lang::t('repair_step1_title')); ?></h2>
            </div>

            <div class="repair-field">
                <label for="rf-name"><?php echo esc_html(PPV_Lang::t('repair_name_label')); ?></label>
                <input type="text" id="rf-name" name="customer_name" required placeholder="<?php echo esc_attr(PPV_Lang::t('repair_name_placeholder')); ?>" value="<?php echo $pf_name; ?>" autocomplete="name">
            </div>

            <div class="repair-field" style="position:relative">
                <label for="rf-email"><?php echo esc_html(PPV_Lang::t('repair_email_label')); ?></label>
                <input type="email" id="rf-email" name="customer_email" placeholder="ihre@email.de" value="<?php echo $pf_email; ?>" autocomplete="email">
                <div id="rf-email-suggestions" class="rf-suggestions-desktop"></div>
                <p class="repair-field-hint"><i class="ri-gift-line"></i> <?php echo esc_html(PPV_Lang::t('repair_email_hint')); ?></p>
            </div>

            <?php if (!empty($field_config['customer_phone']['enabled'])): ?>
            <div class="repair-field">
                <label for="rf-phone"><?php echo esc_html($field_config['customer_phone']['label'] ?? PPV_Lang::t('repair_phone_label')); ?></label>
                <input type="tel" id="rf-phone" name="customer_phone" placeholder="<?php echo esc_attr(PPV_Lang::t('repair_phone_placeholder')); ?>" value="<?php echo $pf_phone; ?>" autocomplete="tel">
            </div>
            <?php endif; ?>

            <?php if (!empty($field_config['customer_address']['enabled'])): ?>
            <div class="repair-field" style="position:relative">
                <label for="rf-address"><?php echo esc_html($field_config['customer_address']['label'] ?? PPV_Lang::t('repair_address_label')); ?></label>
                <input type="text" id="rf-address" name="customer_address" placeholder="<?php echo esc_attr(PPV_Lang::t('repair_address_placeholder')); ?>" value="<?php echo $pf_address; ?>" autocomplete="street-address">
                <div id="rf-address-suggestions" class="rf-suggestions-desktop"></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Step 2: Informationen -->
        <?php
        $has_device_fields = !empty($field_config['device_brand']['enabled']) || !empty($field_config['device_model']['enabled']) || !empty($field_config['device_imei']['enabled']) || !empty($field_config['device_pattern']['enabled']) || !empty($field_config['muster_image']['enabled']) || !empty($field_config['vehicle_plate']['enabled']) || !empty($field_config['vehicle_vin']['enabled']) || !empty($field_config['vehicle_mileage']['enabled']) || !empty($field_config['vehicle_first_reg']['enabled']) || !empty($field_config['vehicle_tuev']['enabled']) || !empty($field_config['condition_check_kfz']['enabled']);
        if ($has_device_fields):
        ?>
        <div class="repair-section" id="step-device">
            <div class="repair-section-title">
                <span class="repair-step-num">2</span>
                <h2><?php echo ($service_type !== 'Allgemein') ? esc_html($service_type) : esc_html(PPV_Lang::t('repair_step2_title')); ?></h2>
            </div>

            <?php if (!empty($field_config['device_brand']['enabled']) || !empty($field_config['device_model']['enabled'])): ?>
            <div class="repair-row">
                <?php if (!empty($field_config['device_brand']['enabled'])): ?>
                <div class="repair-field">
                    <label for="rf-brand"><?php echo esc_html($field_config['device_brand']['label'] ?? PPV_Lang::t('repair_brand_label')); ?></label>
                    <?php if (!empty($custom_brands)): ?>
                    <select id="rf-brand-select" class="repair-select" onchange="if(this.value==='_other'){document.getElementById('rf-brand-other').style.display='block';document.getElementById('rf-brand').value=''}else{document.getElementById('rf-brand-other').style.display='none';document.getElementById('rf-brand').value=this.value}">
                        <option value=""><?php echo esc_html(PPV_Lang::t('repair_select_placeholder')); ?></option>
                        <?php foreach ($custom_brands as $brand): ?>
                        <option value="<?php echo esc_attr($brand); ?>" <?php echo ($pf_brand === $brand) ? 'selected' : ''; ?>><?php echo esc_html($brand); ?></option>
                        <?php endforeach; ?>
                        <option value="_other"><?php echo esc_html(PPV_Lang::t('repair_other_option')); ?></option>
                    </select>
                    <input type="hidden" id="rf-brand" name="device_brand" value="<?php echo $pf_brand; ?>">
                    <input type="text" id="rf-brand-other" placeholder="<?php echo esc_attr(($field_config['device_brand']['label'] ?? PPV_Lang::t('repair_brand_label')) . ' ' . PPV_Lang::t('repair_enter_suffix')); ?>" style="display:none;margin-top:8px" oninput="document.getElementById('rf-brand').value=this.value">
                    <?php else: ?>
                    <input type="text" id="rf-brand" name="device_brand" placeholder="<?php echo esc_attr($field_config['device_brand']['label'] ?? PPV_Lang::t('repair_brand_label')); ?>" value="<?php echo $pf_brand; ?>">
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($field_config['device_model']['enabled'])): ?>
                <div class="repair-field">
                    <label for="rf-model"><?php echo esc_html($field_config['device_model']['label'] ?? PPV_Lang::t('repair_model_label')); ?></label>
                    <input type="text" id="rf-model" name="device_model" placeholder="<?php echo esc_attr($field_config['device_model']['label'] ?? PPV_Lang::t('repair_model_label')); ?>" value="<?php echo $pf_model; ?>">
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($field_config['device_imei']['enabled'])): ?>
            <div class="repair-field">
                <label for="rf-imei"><?php echo esc_html($field_config['device_imei']['label'] ?? PPV_Lang::t('repair_imei_label')); ?></label>
                <input type="text" id="rf-imei" name="device_imei" placeholder="<?php echo esc_attr($field_config['device_imei']['label'] ?? PPV_Lang::t('repair_imei_label')); ?>" value="<?php echo $pf_imei; ?>">
            </div>
            <?php endif; ?>

            <?php
            $show_pin = !empty($field_config['device_pattern']['enabled']);
            $show_muster = !empty($field_config['muster_image']['enabled']);
            if ($show_pin || $show_muster):
            ?>
            <div class="repair-field repair-unlock-field">
                <label><?php echo ($show_pin && $show_muster) ? esc_html(PPV_Lang::t('repair_unlock_method')) : ($show_pin ? esc_html($field_config['device_pattern']['label'] ?? PPV_Lang::t('repair_pin_label')) : esc_html($field_config['muster_image']['label'] ?? PPV_Lang::t('repair_pattern_label'))); ?></label>
                <?php if ($show_pin && $show_muster): ?>
                <div class="repair-unlock-toggle">
                    <button type="button" class="repair-unlock-btn active" data-mode="pin"><i class="ri-lock-password-line"></i> <?php echo esc_html(PPV_Lang::t('repair_pin_code')); ?></button>
                    <button type="button" class="repair-unlock-btn" data-mode="muster"><i class="ri-grid-line"></i> <?php echo esc_html(PPV_Lang::t('repair_pattern')); ?></button>
                </div>
                <?php endif; ?>
                <?php if ($show_pin): ?>
                <div class="repair-unlock-content" id="repair-unlock-pin">
                    <input type="text" id="rf-pattern" name="device_pattern" placeholder="<?php echo esc_attr($field_config['device_pattern']['label'] ?? PPV_Lang::t('repair_pin_label')); ?>" value="<?php echo $pf_pin; ?>">
                </div>
                <?php endif; ?>
                <?php if ($show_muster): ?>
                <div class="repair-unlock-content" id="repair-unlock-muster" <?php echo $show_pin ? 'style="display:none"' : ''; ?>>
                    <div class="repair-muster-canvas-wrap">
                        <canvas id="rf-muster-canvas" width="200" height="200"></canvas>
                        <input type="hidden" name="muster_image" id="rf-muster-data">
                        <button type="button" class="repair-muster-reset" id="rf-muster-reset"><i class="ri-refresh-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reset')); ?></button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($field_config['device_color']['enabled'])): ?>
            <div class="repair-field">
                <label><?php echo esc_html($field_config['device_color']['label'] ?? PPV_Lang::t('repair_fb_color')); ?></label>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <?php
                    $colors = ['Schwarz' => '#000', 'Weiß' => '#fff', 'Silber' => '#c0c0c0', 'Gold' => '#d4a437', 'Blau' => '#3b82f6', 'Rot' => '#ef4444', 'Grün' => '#22c55e', 'Rosa' => '#ec4899'];
                    foreach ($colors as $cname => $chex): ?>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 12px;border:1.5px solid #e2e8f0;border-radius:20px;font-size:13px;transition:all .2s" class="repair-color-label">
                        <input type="radio" name="device_color" value="<?php echo esc_attr($cname); ?>" style="display:none">
                        <span style="width:16px;height:16px;border-radius:50%;background:<?php echo $chex; ?>;border:1.5px solid #d1d5db;display:inline-block"></span>
                        <?php echo esc_html($cname); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($field_config['purchase_date']['enabled'])): ?>
            <div class="repair-field">
                <label for="rf-purchase-date"><?php echo esc_html($field_config['purchase_date']['label'] ?? PPV_Lang::t('repair_fb_purchase_date')); ?></label>
                <input type="date" id="rf-purchase-date" name="purchase_date" style="width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:10px;font-size:16px;background:#f8fafc;color:#0f172a;outline:none">
            </div>
            <?php endif; ?>

            <?php if (!empty($field_config['condition_check']['enabled'])): ?>
            <div class="repair-field">
                <label><?php echo esc_html($field_config['condition_check']['label'] ?? PPV_Lang::t('repair_fb_condition')); ?></label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px" id="rf-condition-grid">
                    <?php
                    $cond_parts = ['Display', 'Tasten', 'Kamera', 'Lautsprecher', 'Mikrofon', 'Ladebuchse', 'Akku', 'Gehäuse'];
                    foreach ($cond_parts as $part): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#f8fafc">
                        <span><?php echo esc_html($part); ?></span>
                        <div style="display:flex;gap:4px">
                            <button type="button" class="repair-cond-btn" data-part="<?php echo esc_attr($part); ?>" data-val="ok" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;border:none;cursor:pointer;background:#d1fae5;color:#059669" onclick="ppvCondToggle(this,'ok')">OK</button>
                            <button type="button" class="repair-cond-btn" data-part="<?php echo esc_attr($part); ?>" data-val="defekt" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;border:none;cursor:pointer;background:#f1f5f9;color:#94a3b8" onclick="ppvCondToggle(this,'defekt')">Defekt</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="condition_check" id="rf-condition-data">
            </div>
            <?php endif; ?>

            <?php // === KFZ / Vehicle fields === ?>
            <?php if (!empty($field_config['vehicle_plate']['enabled']) || !empty($field_config['vehicle_vin']['enabled'])): ?>
            <div class="repair-row">
                <?php if (!empty($field_config['vehicle_plate']['enabled'])): ?>
                <div class="repair-field">
                    <label for="rf-vehicle-plate"><?php echo esc_html($field_config['vehicle_plate']['label'] ?? PPV_Lang::t('repair_vehicle_plate_label')); ?></label>
                    <input type="text" id="rf-vehicle-plate" name="vehicle_plate" placeholder="<?php echo esc_attr(PPV_Lang::t('repair_vehicle_plate_placeholder')); ?>" style="text-transform:uppercase">
                </div>
                <?php endif; ?>
                <?php if (!empty($field_config['vehicle_vin']['enabled'])): ?>
                <div class="repair-field">
                    <label for="rf-vehicle-vin"><?php echo esc_html($field_config['vehicle_vin']['label'] ?? PPV_Lang::t('repair_vehicle_vin_label')); ?></label>
                    <input type="text" id="rf-vehicle-vin" name="vehicle_vin" placeholder="<?php echo esc_attr(PPV_Lang::t('repair_vehicle_vin_placeholder')); ?>" maxlength="17" style="text-transform:uppercase">
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($field_config['vehicle_mileage']['enabled'])): ?>
            <div class="repair-field">
                <label for="rf-vehicle-mileage"><?php echo esc_html($field_config['vehicle_mileage']['label'] ?? PPV_Lang::t('repair_vehicle_mileage_label')); ?></label>
                <input type="number" id="rf-vehicle-mileage" name="vehicle_mileage" placeholder="<?php echo esc_attr(PPV_Lang::t('repair_vehicle_mileage_placeholder')); ?>" min="0" step="1">
            </div>
            <?php endif; ?>

            <?php if (!empty($field_config['vehicle_first_reg']['enabled']) || !empty($field_config['vehicle_tuev']['enabled'])): ?>
            <div class="repair-row">
                <?php if (!empty($field_config['vehicle_first_reg']['enabled'])): ?>
                <div class="repair-field">
                    <label for="rf-vehicle-first-reg"><?php echo esc_html($field_config['vehicle_first_reg']['label'] ?? PPV_Lang::t('repair_vehicle_first_reg_label')); ?></label>
                    <input type="text" id="rf-vehicle-first-reg" name="vehicle_first_reg" placeholder="<?php echo esc_attr(PPV_Lang::t('repair_vehicle_first_reg_placeholder')); ?>">
                </div>
                <?php endif; ?>
                <?php if (!empty($field_config['vehicle_tuev']['enabled'])): ?>
                <div class="repair-field">
                    <label for="rf-vehicle-tuev"><?php echo esc_html($field_config['vehicle_tuev']['label'] ?? PPV_Lang::t('repair_vehicle_tuev_label')); ?></label>
                    <input type="text" id="rf-vehicle-tuev" name="vehicle_tuev" placeholder="<?php echo esc_attr(PPV_Lang::t('repair_vehicle_tuev_placeholder')); ?>">
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($field_config['condition_check_kfz']['enabled'])): ?>
            <div class="repair-field">
                <label><?php echo esc_html($field_config['condition_check_kfz']['label'] ?? PPV_Lang::t('repair_fb_condition_kfz')); ?></label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px" id="rf-condition-kfz-grid">
                    <?php
                    $kfz_parts = [
                        PPV_Lang::t('repair_cond_kfz_motor'),
                        PPV_Lang::t('repair_cond_kfz_getriebe'),
                        PPV_Lang::t('repair_cond_kfz_bremsen'),
                        PPV_Lang::t('repair_cond_kfz_reifen'),
                        PPV_Lang::t('repair_cond_kfz_karosserie'),
                        PPV_Lang::t('repair_cond_kfz_elektronik'),
                        PPV_Lang::t('repair_cond_kfz_klima'),
                        PPV_Lang::t('repair_cond_kfz_beleuchtung'),
                        PPV_Lang::t('repair_cond_kfz_lenkung'),
                        PPV_Lang::t('repair_cond_kfz_fahrwerk'),
                    ];
                    foreach ($kfz_parts as $part): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#f8fafc">
                        <span><?php echo esc_html($part); ?></span>
                        <div style="display:flex;gap:4px">
                            <button type="button" class="repair-cond-kfz-btn" data-part="<?php echo esc_attr($part); ?>" data-val="ok" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;border:none;cursor:pointer;background:#d1fae5;color:#059669" onclick="ppvCondKfzToggle(this,'ok')">OK</button>
                            <button type="button" class="repair-cond-kfz-btn" data-part="<?php echo esc_attr($part); ?>" data-val="defekt" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;border:none;cursor:pointer;background:#f1f5f9;color:#94a3b8" onclick="ppvCondKfzToggle(this,'defekt')">Defekt</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="condition_check_kfz" id="rf-condition-kfz-data">
            </div>
            <?php endif; ?>

            <?php if (!empty($field_config['condition_check_pc']['enabled'])): ?>
            <div class="repair-field">
                <label><?php echo esc_html($field_config['condition_check_pc']['label'] ?? PPV_Lang::t('repair_fb_condition_pc')); ?></label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px" id="rf-condition-pc-grid">
                    <?php
                    $pc_parts = [
                        PPV_Lang::t('repair_cond_pc_mainboard'),
                        PPV_Lang::t('repair_cond_pc_cpu'),
                        PPV_Lang::t('repair_cond_pc_ram'),
                        PPV_Lang::t('repair_cond_pc_storage'),
                        PPV_Lang::t('repair_cond_pc_gpu'),
                        PPV_Lang::t('repair_cond_pc_display'),
                        PPV_Lang::t('repair_cond_pc_keyboard'),
                        PPV_Lang::t('repair_cond_pc_fan'),
                        PPV_Lang::t('repair_cond_pc_power'),
                        PPV_Lang::t('repair_cond_pc_ports'),
                    ];
                    foreach ($pc_parts as $part): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#f8fafc">
                        <span><?php echo esc_html($part); ?></span>
                        <div style="display:flex;gap:4px">
                            <button type="button" class="repair-cond-pc-btn" data-part="<?php echo esc_attr($part); ?>" data-val="ok" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;border:none;cursor:pointer;background:#d1fae5;color:#059669" onclick="ppvCondPcToggle(this,'ok')">OK</button>
                            <button type="button" class="repair-cond-pc-btn" data-part="<?php echo esc_attr($part); ?>" data-val="defekt" style="padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;border:none;cursor:pointer;background:#f1f5f9;color:#94a3b8" onclick="ppvCondPcToggle(this,'defekt')">Defekt</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="condition_check_pc" id="rf-condition-pc-data">
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>

        <!-- Step: Problem -->
        <div class="repair-section" id="step-problem">
            <div class="repair-section-title">
                <span class="repair-step-num"><?php echo $has_device_fields ? '3' : '2'; ?></span>
                <h2><?php echo esc_html(PPV_Lang::t('repair_step3_title')); ?></h2>
            </div>

            <?php if (!empty($custom_problems)): ?>
            <div class="repair-field">
                <label><?php echo esc_html(PPV_Lang::t('repair_quick_select')); ?></label>
                <div class="repair-problem-tags" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
                    <?php foreach ($custom_problems as $problem): ?>
                    <button type="button" class="repair-problem-tag" onclick="toggleProblemTag(this, '<?php echo esc_js($problem); ?>')" style="padding:8px 14px;border-radius:20px;border:1.5px solid #e5e7eb;background:#fff;font-size:13px;cursor:pointer;transition:all .2s"><?php echo esc_html($problem); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="repair-field">
                <label for="rf-problem"><?php echo esc_html(PPV_Lang::t('repair_problem_label')); ?></label>
                <textarea id="rf-problem" name="problem_description" required rows="4" placeholder="<?php echo esc_attr(!empty($custom_problems) ? PPV_Lang::t('repair_problem_detail_placeholder') : PPV_Lang::t('repair_problem_placeholder')); ?>"><?php echo $pf_problem; ?></textarea>
                <?php
                require_once PPV_PLUGIN_DIR . 'includes/class-ppv-ai-engine.php';
                if (PPV_AI_Engine::is_available()):
                ?>
                <button type="button" id="rf-ai-btn" class="repair-ai-btn" style="display:none">
                    <i class="ri-sparkling-2-fill"></i>
                    <span id="rf-ai-btn-text"><?php echo esc_html(PPV_Lang::t('repair_ai_btn')); ?></span>
                </button>
                <div id="rf-ai-result" class="repair-ai-result" style="display:none">
                    <div class="repair-ai-header">
                        <i class="ri-sparkling-2-fill"></i>
                        <span><?php echo esc_html(PPV_Lang::t('repair_ai_title')); ?></span>
                        <button type="button" id="rf-ai-close" class="repair-ai-close">&times;</button>
                    </div>
                    <div id="rf-ai-content" class="repair-ai-content"></div>
                    <div class="repair-ai-hint"><?php echo esc_html(PPV_Lang::t('repair_ai_hint')); ?></div>
                </div>
                <?php endif; ?>
            </div>


            <?php if (!empty($field_config['accessories']['enabled'])): ?>
            <div class="repair-field">
                <label><?php echo esc_html($field_config['accessories']['label'] ?? PPV_Lang::t('repair_accessories_label')); ?></label>
                <div class="repair-accessories">
                    <?php if (!empty($custom_accessories)): ?>
                        <?php foreach ($custom_accessories as $acc): ?>
                        <label class="repair-checkbox"><input type="checkbox" name="acc[]" value="<?php echo esc_attr(sanitize_title($acc)); ?>"> <?php echo esc_html($acc); ?></label>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <label class="repair-checkbox"><input type="checkbox" name="acc[]" value="charger"> <?php echo esc_html(PPV_Lang::t('repair_acc_charger')); ?></label>
                    <label class="repair-checkbox"><input type="checkbox" name="acc[]" value="case"> <?php echo esc_html(PPV_Lang::t('repair_acc_case')); ?></label>
                    <label class="repair-checkbox"><input type="checkbox" name="acc[]" value="keys"> <?php echo esc_html(PPV_Lang::t('repair_acc_keys')); ?></label>
                    <label class="repair-checkbox"><input type="checkbox" name="acc[]" value="other"> <?php echo esc_html(PPV_Lang::t('repair_acc_other')); ?></label>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($field_config['photo_upload']['enabled'])): ?>
            <div class="repair-field">
                <label><?php echo esc_html($field_config['photo_upload']['label'] ?? PPV_Lang::t('repair_fb_photo')); ?></label>
                <div style="display:flex;gap:8px;flex-wrap:wrap" id="rf-photo-previews"></div>
                <label style="display:flex;align-items:center;gap:8px;padding:14px;border:2px dashed #e2e8f0;border-radius:10px;cursor:pointer;color:#64748b;font-size:13px;font-weight:600;transition:all .2s;text-align:center;justify-content:center;margin-top:8px" id="rf-photo-label">
                    <i class="ri-camera-line" style="font-size:20px;color:var(--repair-accent)"></i>
                    <?php echo esc_html(PPV_Lang::t('repair_fb_photo_btn')); ?>
                    <input type="file" name="repair_photos[]" accept="image/*" multiple capture="environment" style="display:none" id="rf-photo-input" onchange="ppvPhotoPreview(this)">
                </label>
            </div>
            <?php endif; ?>

            <?php if (!empty($field_config['priority']['enabled'])): ?>
            <div class="repair-field">
                <label><?php echo esc_html($field_config['priority']['label'] ?? PPV_Lang::t('repair_fb_priority')); ?></label>
                <div style="display:flex;gap:8px" id="rf-priority-cards">
                    <label style="flex:1;padding:12px 8px;border:2px solid #e2e8f0;border-radius:10px;text-align:center;cursor:pointer;transition:all .2s;background:#f8fafc" class="repair-priority-opt" onclick="ppvPrioritySelect(this)">
                        <input type="radio" name="priority" value="express" style="display:none">
                        <i class="ri-flashlight-line" style="font-size:20px;display:block;margin-bottom:4px;color:#f59e0b"></i>
                        <strong style="font-size:12px">Express</strong><br><span style="font-size:11px;color:#64748b">24h</span>
                    </label>
                    <label style="flex:1;padding:12px 8px;border:2px solid var(--repair-accent);border-radius:10px;text-align:center;cursor:pointer;transition:all .2s;background:#eff6ff" class="repair-priority-opt active" onclick="ppvPrioritySelect(this)">
                        <input type="radio" name="priority" value="normal" checked style="display:none">
                        <i class="ri-time-line" style="font-size:20px;display:block;margin-bottom:4px;color:var(--repair-accent)"></i>
                        <strong style="font-size:12px">Normal</strong><br><span style="font-size:11px;color:#64748b">3-5 <?php echo esc_html(PPV_Lang::t('repair_fb_days')); ?></span>
                    </label>
                    <label style="flex:1;padding:12px 8px;border:2px solid #e2e8f0;border-radius:10px;text-align:center;cursor:pointer;transition:all .2s;background:#f8fafc" class="repair-priority-opt" onclick="ppvPrioritySelect(this)">
                        <input type="radio" name="priority" value="economy" style="display:none">
                        <i class="ri-leaf-line" style="font-size:20px;display:block;margin-bottom:4px;color:#22c55e"></i>
                        <strong style="font-size:12px"><?php echo esc_html(PPV_Lang::t('repair_fb_economy')); ?></strong><br><span style="font-size:11px;color:#64748b">7-10 <?php echo esc_html(PPV_Lang::t('repair_fb_days')); ?></span>
                    </label>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($field_config['cost_limit']['enabled'])): ?>
            <div class="repair-field">
                <label><?php echo esc_html($field_config['cost_limit']['label'] ?? PPV_Lang::t('repair_fb_cost_limit')); ?></label>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <?php foreach (['50' => 'max. 50€', '100' => 'max. 100€', '200' => 'max. 200€', 'unlimited' => PPV_Lang::t('repair_fb_no_limit')] as $cv => $cl): ?>
                    <label style="padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:20px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;transition:all .2s" class="repair-cost-opt" onclick="ppvCostSelect(this)">
                        <input type="radio" name="cost_limit" value="<?php echo esc_attr($cv); ?>" style="display:none">
                        <?php echo esc_html($cl); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // Custom fields from field_config
            foreach ($field_config as $cf_key => $cf) {
                if (strpos($cf_key, 'custom_') !== 0) continue;
                if (empty($cf['enabled'])) continue;
                $cf_label = esc_html($cf['label'] ?? '');
                $cf_type  = $cf['type'] ?? 'text';
                $cf_req   = !empty($cf['required']) ? 'required' : '';
                $cf_name  = 'cf_' . esc_attr($cf_key);
                if (!$cf_label) continue;
            ?>
            <div class="repair-field">
                <label for="<?php echo $cf_name; ?>"><?php echo $cf_label; ?><?php if ($cf_req): ?> <span style="color:#f59e0b">*</span><?php endif; ?></label>
                <?php if ($cf_type === 'textarea'): ?>
                    <textarea id="<?php echo $cf_name; ?>" name="<?php echo $cf_name; ?>" rows="3" <?php echo $cf_req; ?> placeholder="<?php echo $cf_label; ?>"></textarea>
                <?php elseif ($cf_type === 'select' && !empty($cf['options'])): ?>
                    <select id="<?php echo $cf_name; ?>" name="<?php echo $cf_name; ?>" class="repair-select" <?php echo $cf_req; ?>>
                        <option value=""><?php echo esc_html(PPV_Lang::t('repair_select_placeholder')); ?></option>
                        <?php foreach (explode("\n", $cf['options']) as $opt): $opt = trim($opt); if ($opt === '') continue; ?>
                        <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($cf_type === 'checkbox'): ?>
                    <label class="repair-checkbox" style="margin-top:4px"><input type="checkbox" name="<?php echo $cf_name; ?>" value="1"> <?php echo $cf_label; ?></label>
                <?php elseif ($cf_type === 'number'): ?>
                    <input type="number" id="<?php echo $cf_name; ?>" name="<?php echo $cf_name; ?>" <?php echo $cf_req; ?> placeholder="<?php echo $cf_label; ?>">
                <?php else: ?>
                    <input type="text" id="<?php echo $cf_name; ?>" name="<?php echo $cf_name; ?>" <?php echo $cf_req; ?> placeholder="<?php echo $cf_label; ?>">
                <?php endif; ?>
            </div>
            <?php } ?>
        </div>

        <!-- Signature Section -->
        <div class="repair-section" id="step-signature">
            <div class="repair-section-title">
                <span class="repair-step-num"><?php echo $has_device_fields ? '4' : '3'; ?></span>
                <h2><?php echo esc_html(PPV_Lang::t('repair_step4_title')); ?></h2>
            </div>

            <div class="repair-field">
                <label><i class="ri-edit-line"></i> <?php echo esc_html(PPV_Lang::t('repair_signature_label')); ?></label>
                <div class="repair-signature-container">
                    <canvas id="rf-signature-canvas" width="400" height="150"></canvas>
                    <p class="repair-signature-hint"><?php echo esc_html(PPV_Lang::t('repair_signature_hint')); ?></p>
                    <div class="repair-signature-actions">
                        <button type="button" class="repair-signature-reset" id="rf-signature-reset">
                            <i class="ri-refresh-line"></i> <?php echo esc_html(PPV_Lang::t('repair_signature_clear')); ?>
                        </button>
                    </div>
                </div>
                <input type="hidden" name="signature_image" id="rf-signature-data">
            </div>
        </div>

        <!-- Terms -->
        <div class="repair-terms">
            <label class="repair-checkbox">
                <input type="checkbox" id="rf-terms" required>
                <?php echo esc_html(PPV_Lang::t('repair_accept_terms')); ?> <a href="/formular/<?php echo $slug; ?>/datenschutz" target="_blank"><?php echo esc_html(PPV_Lang::t('repair_privacy_policy')); ?></a> <?php echo esc_html(PPV_Lang::t('repair_and')); ?> <a href="<?php echo $terms_url ? esc_url($terms_url) : '/formular/' . $slug . '/agb'; ?>" target="_blank"><?php echo esc_html(PPV_Lang::t('repair_agb')); ?></a>
            </label>
        </div>

        <!-- Submit -->
        <button type="submit" id="rf-submit" class="repair-submit">
            <span class="repair-submit-text"><i class="ri-send-plane-fill"></i> <?php echo $form_title; ?> <?php echo esc_html(PPV_Lang::t('repair_submit_suffix')); ?></span>
            <span class="repair-submit-loading" style="display:none"><i class="ri-loader-4-line ri-spin"></i> <?php echo esc_html(PPV_Lang::t('repair_submitting')); ?></span>
        </button>

        <div id="rf-error" class="repair-error" style="display:none"></div>
    </form>
    <?php endif; ?>

    <!-- Success screen (hidden by default) -->
    <div id="repair-success" class="repair-success" style="display:none">
        <div class="repair-success-animation">
            <div class="repair-success-check">&#10003;</div>
        </div>
        <h2><?php echo esc_html(PPV_Lang::t('repair_success_title')); ?></h2>
        <?php if ($success_message): ?>
        <p><?php echo esc_html($success_message); ?></p>
        <?php else: ?>
        <p><?php echo esc_html(sprintf(PPV_Lang::t('repair_success_text'), $form_title)); ?></p>
        <?php endif; ?>

        <!-- Tracking Card -->
        <div id="repair-tracking-card" class="repair-tracking-card" style="display:none">
            <div class="repair-tracking-qr" id="repair-qr-container"></div>
            <div class="repair-tracking-info">
                <div class="repair-tracking-label"><?php echo esc_html(PPV_Lang::t('repair_order_number')); ?></div>
                <div class="repair-tracking-id" id="repair-tracking-id">#---</div>
                <a href="#" id="repair-tracking-link" class="repair-tracking-link" target="_blank">
                    <i class="ri-external-link-line"></i> <?php echo esc_html(PPV_Lang::t('repair_track_status')); ?>
                </a>
            </div>
        </div>

        <?php if ($pp_enabled): ?>
        <div id="repair-points-card" class="repair-points-card" style="display:none">
            <div class="repair-points-badge">
                <span class="repair-points-plus">+</span>
                <span id="repair-points-count" class="repair-points-count">0</span>
            </div>
            <div class="repair-points-label"><?php echo esc_html(PPV_Lang::t('repair_bonus_points')); ?></div>
            <div id="repair-points-total" class="repair-points-total"></div>
        </div>
        <?php endif; ?>

        <p class="repair-success-info"><?php echo esc_html(PPV_Lang::t('repair_email_confirmation')); ?></p>

        <a href="/formular/<?php echo $slug; ?>" class="repair-btn-back" id="repair-new-form-btn" onclick="sessionStorage.removeItem('ppv_repair_submitted_<?php echo $store_id; ?>');sessionStorage.removeItem('ppv_repair_submitted_<?php echo $store_id; ?>_data')">
            <i class="ri-restart-line"></i> <?php echo esc_html(PPV_Lang::t('repair_new_form')); ?>
        </a>
        <div id="repair-auto-redirect" class="repair-auto-redirect"></div>
    </div>

    <!-- Professional Footer -->
    <div class="repair-footer">
        <div class="repair-footer-trust">
            <div class="repair-footer-trust-item">
                <i class="ri-lock-line"></i> <?php echo esc_html(PPV_Lang::t('repair_ssl_encrypted')); ?>
            </div>
            <div class="repair-footer-trust-item">
                <i class="ri-shield-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_dsgvo_conform')); ?>
            </div>
        </div>
        <div class="repair-footer-links">
            <a href="/formular/<?php echo $slug; ?>/datenschutz"><?php echo esc_html(PPV_Lang::t('repair_datenschutz')); ?></a>
            <span class="repair-footer-dot"></span>
            <a href="/formular/<?php echo $slug; ?>/agb"><?php echo esc_html(PPV_Lang::t('repair_agb')); ?></a>
            <span class="repair-footer-dot"></span>
            <a href="/formular/<?php echo $slug; ?>/impressum"><?php echo esc_html(PPV_Lang::t('repair_impressum')); ?></a>
            <span class="repair-footer-dot"></span>
            <a href="/formular/partner<?php echo $lang !== 'de' ? '?lang=' . $lang : ''; ?>"><i class="ri-handshake-line" style="font-size:12px;vertical-align:-1px"></i> Partner</a>
        </div>
        <div class="repair-footer-powered">
            <?php if ($partner && $partner->logo_url): ?>
                <span style="display:inline-flex;align-items:center;gap:8px">
                    <img src="<?php echo esc_url($partner->logo_url); ?>" alt="<?php echo esc_html($partner->company_name); ?>" style="height:16px;border-radius:3px;opacity:0.7">
                    <span style="opacity:0.4">&amp;</span>
                </span>
            <?php endif; ?>
            <span><?php echo esc_html(PPV_Lang::t('repair_powered_by')); ?></span>
            <a href="https://punktepass.de" target="_blank" class="repair-footer-brand">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="1" y="1" width="14" height="14" rx="4" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <strong>PunktePass</strong>
            </a>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<?php if ($pp_enabled): ?>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<?php endif; ?>
<script>
// Close language dropdown when clicking outside
document.addEventListener('click', function() {
    var ls = document.getElementById('repair-lang-switcher');
    if (ls) ls.classList.remove('open');
});

// Translation strings for JS
var ppvLang = <?php echo $js_strings; ?>;

<?php if ($pp_enabled): ?>
// ===== QR SCAN FOR PUNKTEPASS LOGIN =====
var ppvQrScan = (function() {
    var modal = document.getElementById('repair-qr-modal');
    var video = document.getElementById('repair-qr-video');
    var statusDiv = document.getElementById('repair-qr-status');
    var scanBtn = document.getElementById('repair-qr-scan-btn');
    var stream = null;
    var scanning = false;
    var canvas = document.createElement('canvas');
    var ctx = canvas.getContext('2d', { willReadFrequently: true });
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var storeId = <?php echo $store_id; ?>;

    function open() {
        if (!modal || !video) return;
        modal.style.display = 'flex';
        statusDiv.textContent = '';
        statusDiv.className = 'repair-qr-status';
        startCamera();
    }

    function close() {
        if (!modal) return;
        modal.style.display = 'none';
        stopCamera();
    }

    function startCamera() {
        if (scanning) return;
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 640 } }
        })
        .then(function(s) {
            stream = s;
            video.srcObject = s;
            video.play();
            scanning = true;
            requestAnimationFrame(scanFrame);
        })
        .catch(function() {
            statusDiv.textContent = ppvLang.qr_scan_no_camera;
            statusDiv.className = 'repair-qr-status error';
        });
    }

    function stopCamera() {
        scanning = false;
        if (stream) {
            stream.getTracks().forEach(function(t) { t.stop(); });
            stream = null;
        }
        video.srcObject = null;
    }

    function scanFrame() {
        if (!scanning || !video.videoWidth) {
            if (scanning) requestAnimationFrame(scanFrame);
            return;
        }
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);
        var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

        if (typeof jsQR !== 'undefined') {
            var code = jsQR(imageData.data, canvas.width, canvas.height, { inversionAttempts: 'dontInvert' });
            if (code && code.data && (code.data.indexOf('PPU') === 0 || code.data.indexOf('PPUSER-') === 0 || code.data.indexOf('PPQR-') === 0)) {
                onQrDetected(code.data);
                return;
            }
        }
        requestAnimationFrame(scanFrame);
    }

    function onQrDetected(qrCode) {
        scanning = false;
        statusDiv.textContent = '⏳';
        statusDiv.className = 'repair-qr-status';

        var fd = new FormData();
        fd.append('action', 'ppv_repair_qr_lookup');
        fd.append('qr_code', qrCode);
        fd.append('store_id', storeId);

        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.data.found) {
                    var d = data.data;
                    // Fill form fields
                    var fields = [
                        ['rf-name', d.name],
                        ['rf-email', d.email],
                        ['rf-phone', d.phone],
                        ['rf-address', d.address]
                    ];
                    fields.forEach(function(f) {
                        var el = document.getElementById(f[0]);
                        if (el && f[1]) {
                            el.value = f[1];
                            // Green highlight animation
                            el.style.transition = 'background 0.3s, border-color 0.3s';
                            el.style.background = '#d1fae5';
                            el.style.borderColor = '#10b981';
                            setTimeout(function() {
                                el.style.background = '';
                                el.style.borderColor = '';
                            }, 2000);
                        }
                    });

                    // Set hidden user_id
                    var uidEl = document.getElementById('rf-qr-user-id');
                    if (uidEl) uidEl.value = d.user_id;

                    // Update scan button to show success
                    var firstName = (d.name || '').split(' ')[0];
                    var successMsg = ppvLang.qr_scan_success.replace('%s', firstName);
                    statusDiv.textContent = '✅ ' + successMsg;
                    statusDiv.className = 'repair-qr-status success';

                    // Close modal after short delay
                    setTimeout(function() {
                        close();
                        // Update button to "signed in" state
                        scanBtn.innerHTML = '<i class="ri-checkbox-circle-fill"></i> ' + successMsg;
                        scanBtn.classList.add('scanned');
                        // Scroll to first empty field
                        var firstEmpty = document.querySelector('#repair-form input:not([type=hidden]):not([value=""])');
                        if (!firstEmpty) {
                            firstEmpty = document.querySelector('#repair-form input[value=""]');
                        }
                    }, 800);
                } else {
                    statusDiv.textContent = ppvLang.qr_scan_error;
                    statusDiv.className = 'repair-qr-status error';
                    // Resume scanning after 2s
                    setTimeout(function() {
                        scanning = true;
                        requestAnimationFrame(scanFrame);
                    }, 2000);
                }
            })
            .catch(function() {
                statusDiv.textContent = ppvLang.qr_scan_error;
                statusDiv.className = 'repair-qr-status error';
                setTimeout(function() {
                    scanning = true;
                    requestAnimationFrame(scanFrame);
                }, 2000);
            });
    }

    // Close on backdrop click
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) close();
        });
    }

    return { open: open, close: close };
})();
<?php endif; ?>


// ===== New field type handlers =====
// Color selection
document.querySelectorAll('.repair-color-label').forEach(function(lbl){
    lbl.addEventListener('click',function(){
        document.querySelectorAll('.repair-color-label').forEach(function(l){l.style.borderColor='#e2e8f0';l.style.background=''});
        this.style.borderColor='var(--repair-accent)';this.style.background='#eff6ff';
    });
});
// Condition check toggle
function ppvCondToggle(btn,val){
    var row=btn.parentNode;
    row.querySelectorAll('.repair-cond-btn').forEach(function(b){b.style.opacity='0.4';b.style.background='#f1f5f9';b.style.color='#94a3b8'});
    if(val==='ok'){btn.style.opacity='1';btn.style.background='#d1fae5';btn.style.color='#059669'}
    else{btn.style.opacity='1';btn.style.background='#fee2e2';btn.style.color='#dc2626'}
    // Update hidden input
    var result={};
    document.querySelectorAll('#rf-condition-grid .repair-cond-btn').forEach(function(b){
        if(parseFloat(b.style.opacity)===1){result[b.getAttribute('data-part')]=b.getAttribute('data-val')}
    });
    var inp=document.getElementById('rf-condition-data');
    if(inp) inp.value=JSON.stringify(result);
}
// KFZ Condition check toggle
function ppvCondKfzToggle(btn,val){
    var row=btn.parentNode;
    row.querySelectorAll('.repair-cond-kfz-btn').forEach(function(b){b.style.opacity='0.4';b.style.background='#f1f5f9';b.style.color='#94a3b8'});
    if(val==='ok'){btn.style.opacity='1';btn.style.background='#d1fae5';btn.style.color='#059669'}
    else{btn.style.opacity='1';btn.style.background='#fee2e2';btn.style.color='#dc2626'}
    var result={};
    document.querySelectorAll('#rf-condition-kfz-grid .repair-cond-kfz-btn').forEach(function(b){
        if(parseFloat(b.style.opacity)===1){result[b.getAttribute('data-part')]=b.getAttribute('data-val')}
    });
    var inp=document.getElementById('rf-condition-kfz-data');
    if(inp) inp.value=JSON.stringify(result);
}
// PC Condition check toggle
function ppvCondPcToggle(btn,val){
    var row=btn.parentNode;
    row.querySelectorAll('.repair-cond-pc-btn').forEach(function(b){b.style.opacity='0.4';b.style.background='#f1f5f9';b.style.color='#94a3b8'});
    if(val==='ok'){btn.style.opacity='1';btn.style.background='#d1fae5';btn.style.color='#059669'}
    else{btn.style.opacity='1';btn.style.background='#fee2e2';btn.style.color='#dc2626'}
    var result={};
    document.querySelectorAll('#rf-condition-pc-grid .repair-cond-pc-btn').forEach(function(b){
        if(parseFloat(b.style.opacity)===1){result[b.getAttribute('data-part')]=b.getAttribute('data-val')}
    });
    var inp=document.getElementById('rf-condition-pc-data');
    if(inp) inp.value=JSON.stringify(result);
}
// Photo preview
function ppvPhotoPreview(input){
    var container=document.getElementById('rf-photo-previews');
    if(!container||!input.files) return;
    container.innerHTML='';
    var max=Math.min(input.files.length,5);
    for(var i=0;i<max;i++){
        var reader=new FileReader();
        reader.onload=function(e){
            var img=document.createElement('img');
            img.src=e.target.result;
            img.style.cssText='width:70px;height:70px;object-fit:cover;border-radius:8px;border:2px solid #e2e8f0';
            container.appendChild(img);
        };
        reader.readAsDataURL(input.files[i]);
    }
}
// Priority selection
function ppvPrioritySelect(lbl){
    document.querySelectorAll('.repair-priority-opt').forEach(function(l){
        l.style.borderColor='#e2e8f0';l.style.background='#f8fafc';l.classList.remove('active');
    });
    lbl.style.borderColor='var(--repair-accent)';lbl.style.background='#eff6ff';lbl.classList.add('active');
    lbl.querySelector('input').checked=true;
}
// Cost limit selection
function ppvCostSelect(lbl){
    document.querySelectorAll('.repair-cost-opt').forEach(function(l){
        l.style.borderColor='#e2e8f0';l.style.color='#64748b';l.style.background='';
    });
    lbl.style.borderColor='var(--repair-accent)';lbl.style.color='var(--repair-accent)';lbl.style.background='#eff6ff';
    lbl.querySelector('input').checked=true;
}

// Problem tag toggle for quick selection
function toggleProblemTag(btn, text) {
    var problemField = document.getElementById('rf-problem');
    if (!problemField) return;

    var isActive = btn.classList.contains('active');
    if (isActive) {
        btn.classList.remove('active');
        btn.style.background = '#fff';
        btn.style.borderColor = '#e5e7eb';
        btn.style.color = '';
        // Remove from textarea
        var lines = problemField.value.split('\n').filter(function(l) { return l.trim() !== text; });
        problemField.value = lines.join('\n');
    } else {
        btn.classList.add('active');
        btn.style.background = 'var(--repair-accent)';
        btn.style.borderColor = 'var(--repair-accent)';
        btn.style.color = '#fff';
        // Add to textarea
        if (problemField.value.trim()) {
            problemField.value = problemField.value.trim() + '\n' + text;
        } else {
            problemField.value = text;
        }
    }
}

(function() {
    var form = document.getElementById('repair-form');
    if (!form) return;

    var submitBtn = document.getElementById('rf-submit');
    var submitText = form.querySelector('.repair-submit-text');
    var submitLoading = form.querySelector('.repair-submit-loading');
    var errorDiv = document.getElementById('rf-error');
    var successDiv = document.getElementById('repair-success');
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var storeId = <?php echo $store_id; ?>;
    var formSubmitted = false;

    // Shared function to display the success page with data
    function showSuccessPage(d) {
        // Hide form, show success
        form.style.display = 'none';
        var bonusBadge = document.querySelector('.repair-bonus-badge');
        if (bonusBadge) bonusBadge.style.display = 'none';
        if (!successDiv) return;
        successDiv.style.display = 'block';

        // Collapse hero header to give more room for success content
        var header = document.querySelector('.repair-header');
        if (header) {
            header.style.padding = '20px 24px 16px';
            var heroSubtitle = header.querySelector('.repair-hero-subtitle');
            if (heroSubtitle) heroSubtitle.style.display = 'none';
            var heroAddresses = header.querySelectorAll('.repair-shop-address');
            for (var ai = 0; ai < heroAddresses.length; ai++) heroAddresses[ai].style.display = 'none';
        }

        // Show tracking card with QR code
        try {
            if (d && d.tracking_url && d.repair_id) {
                var trackCard = document.getElementById('repair-tracking-card');
                var trackId = document.getElementById('repair-tracking-id');
                var trackLink = document.getElementById('repair-tracking-link');
                var qrContainer = document.getElementById('repair-qr-container');
                if (trackCard) trackCard.style.display = 'flex';
                if (trackId) trackId.textContent = '#' + d.repair_id;
                if (trackLink) trackLink.href = d.tracking_url;

                // Generate QR code
                if (qrContainer && typeof qrcode !== 'undefined') {
                    var qr = qrcode(0, 'M');
                    qr.addData(d.tracking_url);
                    qr.make();
                    qrContainer.innerHTML = qr.createImgTag(4, 0);
                }
            }
        } catch(e) { console.warn('[Repair] QR render error:', e); }

        // Show points card
        <?php if ($pp_enabled): ?>
        try {
            if (d && d.points_added > 0) {
                var ptsCard = document.getElementById('repair-points-card');
                var ptsCount = document.getElementById('repair-points-count');
                var ptsTotal = document.getElementById('repair-points-total');
                if (ptsCard) ptsCard.style.display = 'block';
                if (ptsCount) ptsCount.textContent = d.points_added;
                if (ptsTotal && d.total_points) {
                    var reqPts = <?php echo $required_pts; ?>;
                    var rwName = <?php echo json_encode($reward_name); ?>;
                    var remaining = Math.max(0, reqPts - d.total_points);
                    var totalStr = ppvLang.points_total.replace('%d', d.total_points).replace('%d', reqPts);
                    var suffixStr = remaining > 0
                        ? ppvLang.points_remaining.replace('%d', remaining).replace('%s', rwName)
                        : ppvLang.points_redeemable.replace('%s', rwName);
                    ptsTotal.textContent = totalStr + ' — ' + suffixStr;
                }
            }
        } catch(e) { console.warn('[Repair] Points render error:', e); }
        <?php endif; ?>

        // Confetti effect
        try { createConfetti(); } catch(e) {}

        // Start auto-redirect countdown (2 min)
        try { startAutoRedirect(); } catch(e) {}

        // Scroll to success content (past the header)
        try { successDiv.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch(e) {}
    }

    // Check if form was already submitted in this session
    var dupKey = 'ppv_repair_submitted_' + storeId;
    var lastSubmit = sessionStorage.getItem(dupKey);
    if (lastSubmit && (Date.now() - parseInt(lastSubmit)) < 300000) { // 5 min
        // Restore saved success data (tracking card, points, QR code)
        var savedData = null;
        try { savedData = JSON.parse(sessionStorage.getItem(dupKey + '_data')); } catch(e) {}
        try {
            showSuccessPage(savedData);
        } catch(e) {
            form.style.display = 'none';
            successDiv.style.display = 'block';
        }
    }

    // Email lookup for returning customers with debounce
    var emailField = document.getElementById('rf-email');
    var nameField = document.getElementById('rf-name');
    var phoneField = document.getElementById('rf-phone');
    var brandField = document.getElementById('rf-brand');
    var modelField = document.getElementById('rf-model');
    var emailLookupDone = {};
    var emailDebounceTimer = null;
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function doEmailLookup(email) {
        if (!email || emailLookupDone[email] || !emailRegex.test(email)) return;

        var fd = new FormData();
        fd.append('action', 'ppv_repair_customer_lookup');
        fd.append('email', email);
        fd.append('store_id', storeId);

        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success && res.data.found) {
                    emailLookupDone[email] = true;
                    // Only fill empty fields
                    if (nameField && !nameField.value) nameField.value = res.data.name || '';
                    if (phoneField && !phoneField.value) phoneField.value = res.data.phone || '';
                    if (brandField && !brandField.value) brandField.value = res.data.brand || '';
                    if (modelField && !modelField.value) modelField.value = res.data.model || '';
                    // Show hint
                    showEmailHint(ppvLang.welcome_back);
                }
            })
            .catch(function() {});
    }

    if (emailField) {
        // Debounced input handler (800ms delay)
        emailField.addEventListener('input', function() {
            clearTimeout(emailDebounceTimer);
            var email = emailField.value.trim();
            if (email && emailRegex.test(email)) {
                emailDebounceTimer = setTimeout(function() {
                    doEmailLookup(email);
                }, 800);
            }
        });
        // Immediate on blur
        emailField.addEventListener('blur', function() {
            clearTimeout(emailDebounceTimer);
            doEmailLookup(emailField.value.trim());
        });
    }

    function showEmailHint(msg) {
        var hint = document.createElement('div');
        hint.className = 'repair-email-hint';
        hint.innerHTML = '<i class="ri-user-heart-line"></i> ' + msg;
        emailField.parentNode.appendChild(hint);
        setTimeout(function() { hint.classList.add('show'); }, 10);
        setTimeout(function() { hint.classList.remove('show'); setTimeout(function() { hint.remove(); }, 300); }, 4000);
    }

    // PIN/Muster toggle handling
    var unlockToggleBtns = document.querySelectorAll('.repair-unlock-btn');
    var unlockPinContent = document.getElementById('repair-unlock-pin');
    var unlockMusterContent = document.getElementById('repair-unlock-muster');

    if (unlockToggleBtns.length > 0) {
        unlockToggleBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var mode = btn.getAttribute('data-mode');
                unlockToggleBtns.forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');
                if (mode === 'pin') {
                    if (unlockPinContent) unlockPinContent.style.display = '';
                    if (unlockMusterContent) unlockMusterContent.style.display = 'none';
                } else {
                    if (unlockPinContent) unlockPinContent.style.display = 'none';
                    if (unlockMusterContent) unlockMusterContent.style.display = '';
                }
            });
        });
    }

    // Muster pattern canvas handling (3x3 grid like Android unlock)
    var musterCanvas = document.getElementById('rf-muster-canvas');
    var musterDataInput = document.getElementById('rf-muster-data');
    var musterResetBtn = document.getElementById('rf-muster-reset');

    if (musterCanvas) {
        var mCtx = musterCanvas.getContext('2d');
        var gridNum = 3;
        var gridGap = musterCanvas.width / (gridNum + 1);
        var musterPoints = [];
        var musterDrawing = false;
        var musterLastPointIndex = null;

        function createMusterGrid() {
            mCtx.clearRect(0, 0, musterCanvas.width, musterCanvas.height);
            musterPoints = [];
            mCtx.fillStyle = '#f3f4f6';
            mCtx.fillRect(0, 0, musterCanvas.width, musterCanvas.height);

            for (var i = 1; i <= gridNum; i++) {
                for (var j = 1; j <= gridNum; j++) {
                    var point = { x: j * gridGap, y: i * gridGap, isDrawn: false, index: ((i-1) * gridNum) + (j-1) };
                    musterPoints.push(point);
                    mCtx.beginPath();
                    mCtx.arc(point.x, point.y, 18, 0, Math.PI * 2);
                    mCtx.fillStyle = '#e5e7eb';
                    mCtx.fill();
                    mCtx.beginPath();
                    mCtx.arc(point.x, point.y, 7, 0, Math.PI * 2);
                    mCtx.fillStyle = '#6b7280';
                    mCtx.fill();
                }
            }
            musterLastPointIndex = null;
            if (musterDataInput) musterDataInput.value = '';
        }

        function getMusterCoords(e) {
            var rect = musterCanvas.getBoundingClientRect();
            var scaleX = musterCanvas.width / rect.width;
            var scaleY = musterCanvas.height / rect.height;
            if (e.touches) {
                return { x: (e.touches[0].clientX - rect.left) * scaleX, y: (e.touches[0].clientY - rect.top) * scaleY };
            }
            return { x: (e.clientX - rect.left) * scaleX, y: (e.clientY - rect.top) * scaleY };
        }

        function drawMusterPoint(x, y) {
            var idx = musterPoints.findIndex(function(p) {
                return Math.sqrt(Math.pow(p.x - x, 2) + Math.pow(p.y - y, 2)) < gridGap / 2 && !p.isDrawn;
            });
            if (idx !== -1) {
                musterPoints[idx].isDrawn = true;
                mCtx.beginPath();
                mCtx.arc(musterPoints[idx].x, musterPoints[idx].y, 18, 0, Math.PI * 2);
                mCtx.fillStyle = 'var(--repair-accent, #667eea)';
                mCtx.fill();
                mCtx.beginPath();
                mCtx.arc(musterPoints[idx].x, musterPoints[idx].y, 7, 0, Math.PI * 2);
                mCtx.fillStyle = 'white';
                mCtx.fill();
                if (musterLastPointIndex !== null) {
                    mCtx.beginPath();
                    mCtx.moveTo(musterPoints[musterLastPointIndex].x, musterPoints[musterLastPointIndex].y);
                    mCtx.lineTo(musterPoints[idx].x, musterPoints[idx].y);
                    mCtx.strokeStyle = 'var(--repair-accent, #667eea)';
                    mCtx.lineWidth = 4;
                    mCtx.lineCap = 'round';
                    mCtx.stroke();
                }
                musterLastPointIndex = idx;
            }
        }

        function startMusterDraw(e) { musterDrawing = true; var c = getMusterCoords(e); drawMusterPoint(c.x, c.y); e.preventDefault(); }
        function moveMusterDraw(e) { if (musterDrawing) { var c = getMusterCoords(e); drawMusterPoint(c.x, c.y); e.preventDefault(); } }
        function stopMusterDraw() { musterDrawing = false; if (musterDataInput) musterDataInput.value = musterCanvas.toDataURL(); }

        musterCanvas.addEventListener('mousedown', startMusterDraw);
        musterCanvas.addEventListener('mousemove', moveMusterDraw);
        musterCanvas.addEventListener('mouseup', stopMusterDraw);
        musterCanvas.addEventListener('mouseout', stopMusterDraw);
        musterCanvas.addEventListener('touchstart', startMusterDraw);
        musterCanvas.addEventListener('touchmove', moveMusterDraw);
        musterCanvas.addEventListener('touchend', stopMusterDraw);

        if (musterResetBtn) {
            musterResetBtn.addEventListener('click', function() { createMusterGrid(); });
        }

        createMusterGrid();
    }

    // Signature canvas handling
    var sigCanvas = document.getElementById('rf-signature-canvas');
    var sigDataInput = document.getElementById('rf-signature-data');
    var sigResetBtn = document.getElementById('rf-signature-reset');

    if (sigCanvas) {
        var sigCtx = sigCanvas.getContext('2d');
        var sigDrawing = false;
        var sigLastX = 0;
        var sigLastY = 0;

        function initSignatureCanvas() {
            sigCtx.fillStyle = '#f9fafb';
            sigCtx.fillRect(0, 0, sigCanvas.width, sigCanvas.height);
            sigCtx.strokeStyle = '#1f2937';
            sigCtx.lineWidth = 2;
            sigCtx.lineCap = 'round';
            sigCtx.lineJoin = 'round';
            if (sigDataInput) sigDataInput.value = '';
        }

        function getSigCoords(e) {
            var rect = sigCanvas.getBoundingClientRect();
            var scaleX = sigCanvas.width / rect.width;
            var scaleY = sigCanvas.height / rect.height;
            if (e.touches) {
                return { x: (e.touches[0].clientX - rect.left) * scaleX, y: (e.touches[0].clientY - rect.top) * scaleY };
            }
            return { x: (e.clientX - rect.left) * scaleX, y: (e.clientY - rect.top) * scaleY };
        }

        function startSigDraw(e) {
            sigDrawing = true;
            var coords = getSigCoords(e);
            sigLastX = coords.x;
            sigLastY = coords.y;
            e.preventDefault();
        }

        function moveSigDraw(e) {
            if (!sigDrawing) return;
            var coords = getSigCoords(e);
            sigCtx.beginPath();
            sigCtx.moveTo(sigLastX, sigLastY);
            sigCtx.lineTo(coords.x, coords.y);
            sigCtx.stroke();
            sigLastX = coords.x;
            sigLastY = coords.y;
            e.preventDefault();
        }

        function stopSigDraw() {
            if (sigDrawing && sigDataInput) {
                sigDataInput.value = sigCanvas.toDataURL('image/png');
            }
            sigDrawing = false;
        }

        sigCanvas.addEventListener('mousedown', startSigDraw);
        sigCanvas.addEventListener('mousemove', moveSigDraw);
        sigCanvas.addEventListener('mouseup', stopSigDraw);
        sigCanvas.addEventListener('mouseout', stopSigDraw);
        sigCanvas.addEventListener('touchstart', startSigDraw);
        sigCanvas.addEventListener('touchmove', moveSigDraw);
        sigCanvas.addEventListener('touchend', stopSigDraw);

        if (sigResetBtn) {
            sigResetBtn.addEventListener('click', function() { initSignatureCanvas(); });
        }

        initSignatureCanvas();
    }

    // ========== OFFLINE SUPPORT ==========
    var offlineStorageKey = 'repair_form_draft_' + storeId;
    var offlineQueueKey = 'repair_form_queue_' + storeId;
    var isOnline = navigator.onLine;

    // Create offline indicator
    var offlineIndicator = document.createElement('div');
    offlineIndicator.id = 'rf-offline-indicator';
    offlineIndicator.style.cssText = 'display:none;position:fixed;top:0;left:0;right:0;background:#f59e0b;color:#fff;text-align:center;padding:10px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 2px 10px rgba(0,0,0,0.2)';
    offlineIndicator.innerHTML = '<i class="ri-wifi-off-line"></i> ' + ppvLang.offline_mode;
    document.body.insertBefore(offlineIndicator, document.body.firstChild);

    function updateOnlineStatus() {
        isOnline = navigator.onLine;
        offlineIndicator.style.display = isOnline ? 'none' : 'block';
        if (isOnline) syncOfflineQueue();
    }

    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    updateOnlineStatus();

    // Auto-save form to localStorage
    function saveFormDraft() {
        var formData = {};
        form.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]), textarea, select').forEach(function(el) {
            if (el.name && el.value) formData[el.name] = el.value;
        });
        // Save checkboxes
        var accChecked = [];
        form.querySelectorAll('input[name="acc[]"]:checked').forEach(function(cb) {
            accChecked.push(cb.value);
        });
        formData['_acc_checked'] = accChecked;
        localStorage.setItem(offlineStorageKey, JSON.stringify(formData));
    }

    // Restore form from localStorage
    function restoreFormDraft() {
        try {
            var saved = localStorage.getItem(offlineStorageKey);
            if (!saved) return;
            var formData = JSON.parse(saved);
            Object.keys(formData).forEach(function(name) {
                if (name === '_acc_checked') {
                    formData[name].forEach(function(val) {
                        var cb = form.querySelector('input[name="acc[]"][value="' + val + '"]');
                        if (cb) cb.checked = true;
                    });
                } else {
                    var el = form.querySelector('[name="' + name + '"]');
                    if (el && !el.value) el.value = formData[name];
                }
            });
        } catch(e) {}
    }

    // Debounced auto-save
    var draftSaveTimer = null;
    form.addEventListener('input', function() {
        clearTimeout(draftSaveTimer);
        draftSaveTimer = setTimeout(saveFormDraft, 500);
    });
    form.addEventListener('change', saveFormDraft);

    // Restore draft on page load
    restoreFormDraft();

    // Offline queue management
    function addToOfflineQueue(formDataObj) {
        var queue = [];
        try { queue = JSON.parse(localStorage.getItem(offlineQueueKey) || '[]'); } catch(e) {}
        formDataObj._timestamp = Date.now();
        queue.push(formDataObj);
        localStorage.setItem(offlineQueueKey, JSON.stringify(queue));
    }

    function syncOfflineQueue() {
        var queue = [];
        try { queue = JSON.parse(localStorage.getItem(offlineQueueKey) || '[]'); } catch(e) {}
        if (queue.length === 0) return;

        var item = queue.shift();
        localStorage.setItem(offlineQueueKey, JSON.stringify(queue));

        var fd = new FormData();
        Object.keys(item).forEach(function(k) {
            if (k !== '_timestamp') fd.append(k, item[k]);
        });

        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    // Continue syncing
                    if (queue.length > 0) syncOfflineQueue();
                } else {
                    // Re-add to queue on failure
                    queue.unshift(item);
                    localStorage.setItem(offlineQueueKey, JSON.stringify(queue));
                }
            })
            .catch(function() {
                queue.unshift(item);
                localStorage.setItem(offlineQueueKey, JSON.stringify(queue));
            });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Prevent double submission
        if (formSubmitted) return;

        // Validate
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Collect accessories
        var accessories = [];
        form.querySelectorAll('input[name="acc[]"]:checked').forEach(function(cb) {
            accessories.push(cb.value);
        });

        // Show loading
        submitBtn.disabled = true;
        submitText.style.display = 'none';
        submitLoading.style.display = 'inline-flex';
        errorDiv.style.display = 'none';

        // Build form data
        var fd = new FormData(form);
        fd.set('accessories', JSON.stringify(accessories));

        // If offline, queue for later
        if (!navigator.onLine) {
            var formDataObj = {};
            fd.forEach(function(val, key) { formDataObj[key] = val; });
            addToOfflineQueue(formDataObj);

            // Clear draft
            localStorage.removeItem(offlineStorageKey);

            // Show offline success
            form.style.display = 'none';
            successDiv.style.display = 'block';
            successDiv.querySelector('p').textContent = ppvLang.offline_saved;

            submitBtn.disabled = false;
            submitText.style.display = 'inline-flex';
            submitLoading.style.display = 'none';
            return;
        }

        fetch(ajaxUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
        .then(function(res) {
            if (!res.ok) console.warn('[Repair] HTTP', res.status);
            return res.json();
        })
        .then(function(data) {
            if (data.success) {
                formSubmitted = true;
                sessionStorage.setItem(dupKey, Date.now().toString());

                // Save success data for page refresh restore
                try { sessionStorage.setItem(dupKey + '_data', JSON.stringify(data.data)); } catch(e) {}

                // Clear saved draft
                localStorage.removeItem(offlineStorageKey);

                // Show success with full data
                try {
                    showSuccessPage(data.data);
                } catch(err) {
                    console.error('[Repair] showSuccessPage error:', err);
                    // Ensure success div is visible even if JS error
                    form.style.display = 'none';
                    successDiv.style.display = 'block';
                }
            } else {
                errorDiv.textContent = data.data?.message || ppvLang.error_generic;
                errorDiv.style.display = 'block';
                // If server detected duplicate, block further attempts
                if (data.data?.duplicate) {
                    formSubmitted = true;
                    sessionStorage.setItem(dupKey, Date.now().toString());
                }
            }
        })
        .catch(function(err) {
            console.error('[Repair] Submit error:', err);
            // If form already hidden (success was partial), show success div anyway
            if (form.style.display === 'none') {
                successDiv.style.display = 'block';
            } else {
                errorDiv.textContent = ppvLang.connection_error;
                errorDiv.style.display = 'block';
            }
        })
        .finally(function() {
            if (!formSubmitted) {
                submitBtn.disabled = false;
                submitText.style.display = 'inline-flex';
                submitLoading.style.display = 'none';
            }
        });
    });

    // Simple confetti
    function createConfetti() {
        var colors = ['#667eea', '#764ba2', '#f59e0b', '#10b981', '#ef4444', '#3b82f6'];
        for (var i = 0; i < 50; i++) {
            var confetti = document.createElement('div');
            confetti.className = 'confetti-piece';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 2 + 's';
            confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
            document.body.appendChild(confetti);
            setTimeout(function() { confetti.remove(); }, 4000);
        }
    }

    // Auto-redirect countdown (2 min)
    function startAutoRedirect() {
        var seconds = 120;
        var redirectDiv = document.getElementById('repair-auto-redirect');
        var newFormBtn = document.getElementById('repair-new-form-btn');
        if (!redirectDiv || !newFormBtn) return;

        var formUrl = newFormBtn.href;

        function updateCountdown() {
            redirectDiv.textContent = ppvLang.auto_redirect.replace('%d', seconds);
            if (seconds <= 0) {
                sessionStorage.removeItem(dupKey);
                sessionStorage.removeItem(dupKey + '_data');
                window.location.href = formUrl;
                return;
            }
            seconds--;
            setTimeout(updateCountdown, 1000);
        }

        // Reset timer on any touch/click
        var resetTimer = function() { seconds = 120; };
        document.addEventListener('click', resetTimer);
        document.addEventListener('touchstart', resetTimer);

        updateCountdown();
    }
})();
</script>

<script>
(function(){
    // XHR helper (works on all WebViews)
    function xhrGet(url, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try { cb(null, JSON.parse(xhr.responseText)); }
                    catch(e) { cb('parse error'); }
                } else { cb('HTTP ' + xhr.status); }
            }
        };
        xhr.onerror = function(){ cb('Network error'); };
        xhr.timeout = 8000;
        xhr.ontimeout = function(){ cb('Timeout'); };
        xhr.send();
    }

    function escH(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    // ===== TOUCH DETECTION =====
    var isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

    // ===== BOTTOM-SHEET (mobile) =====
    var sheetOverlay = document.getElementById('rf-sheet-overlay');
    var sheetList = document.getElementById('rf-sheet-list');
    var sheetTitle = document.getElementById('rf-sheet-title');
    var sheetClose = document.getElementById('rf-sheet-close');

    function showSheet(title) {
        sheetTitle.textContent = title;
        sheetOverlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function hideSheet() {
        sheetOverlay.classList.remove('show');
        document.body.style.overflow = '';
    }
    if (sheetClose) sheetClose.addEventListener('click', hideSheet);
    if (sheetOverlay) sheetOverlay.addEventListener('click', function(e) {
        if (e.target === sheetOverlay) hideSheet();
    });

    function showSuggestions(box, title) {
        if (isTouchDevice) {
            // Move content to bottom-sheet
            sheetList.innerHTML = box.innerHTML;
            // Re-bind click handlers by cloning approach
            var origItems = box.querySelectorAll('.rf-sug-item');
            var sheetItems = sheetList.querySelectorAll('.rf-sug-item');
            for (var si = 0; si < sheetItems.length; si++) {
                (function(idx){
                    sheetItems[idx].addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        // Trigger click on the original hidden item
                        if (origItems[idx] && origItems[idx]._onSelect) {
                            origItems[idx]._onSelect();
                        }
                        hideSheet();
                    });
                })(si);
            }
            showSheet(title);
        } else {
            box.style.display = 'block';
        }
    }
    function hideSuggestions(box) {
        box.style.display = 'none';
        if (isTouchDevice) hideSheet();
    }

    // ===== DISMISS: hide dropdowns when tapping OUTSIDE =====
    var emailInput = document.getElementById('rf-email');
    var emailBox = document.getElementById('rf-email-suggestions');
    var addrInput = document.getElementById('rf-address');
    var addrBox = document.getElementById('rf-address-suggestions');

    document.addEventListener('click', function(e) {
        if (emailBox && emailInput && !emailInput.contains(e.target) && !emailBox.contains(e.target)) {
            emailBox.style.display = 'none';
        }
        if (addrBox && addrInput && !addrInput.contains(e.target) && !addrBox.contains(e.target)) {
            addrBox.style.display = 'none';
        }
    }, true);

    // ===== EMAIL AUTOCOMPLETE =====
    if (emailInput && emailBox) {
        var storeEl = document.querySelector('input[name="store_id"]');
        var storeId = storeEl ? storeEl.value : 0;
        var emailTimer = null;
        var lastEmailQ = '';

        function triggerEmailSearch(){
            clearTimeout(emailTimer);
            var q = emailInput.value.trim();
            if (q === lastEmailQ) return;
            lastEmailQ = q;
            if (q.length < 2) { hideSuggestions(emailBox); return; }
            emailTimer = setTimeout(function(){ searchEmails(q); }, 300);
        }

        emailInput.addEventListener('input', triggerEmailSearch);
        emailInput.addEventListener('keyup', triggerEmailSearch);

        function searchEmails(q) {
            var url = '<?php echo admin_url("admin-ajax.php"); ?>?action=ppv_repair_customer_email_search&store_id=' + storeId + '&q=' + encodeURIComponent(q);
            xhrGet(url, function(err, resp){
                if (err || !resp || !resp.success || !resp.data || !resp.data.length) {
                    hideSuggestions(emailBox);
                    return;
                }
                emailBox.innerHTML = '';
                resp.data.forEach(function(c){
                    var item = document.createElement('div');
                    item.className = 'rf-sug-item';
                    item.innerHTML = '<div class="rf-sug-main">' + escH(c.customer_email) + '</div>' +
                        '<div class="rf-sug-sub">' + escH(c.customer_name) + (c.customer_phone ? ' &bull; ' + escH(c.customer_phone) : '') + '</div>';
                    item._onSelect = function(){
                        emailInput.value = c.customer_email;
                        emailBox.style.display = 'none';
                        var nameF = document.getElementById('rf-name');
                        var phoneF = document.getElementById('rf-phone');
                        var addrF = document.getElementById('rf-address');
                        if (nameF && c.customer_name) nameF.value = c.customer_name;
                        if (phoneF && c.customer_phone) phoneF.value = c.customer_phone;
                        if (addrF && c.customer_address) addrF.value = c.customer_address;
                        [nameF, phoneF, addrF].forEach(function(f){
                            if (f && f.value) {
                                f.style.transition = 'background .3s';
                                f.style.background = '#d1fae5';
                                setTimeout(function(){ f.style.background = ''; }, 1000);
                            }
                        });
                    };
                    item.addEventListener('click', function(e){
                        e.preventDefault();
                        e.stopPropagation();
                        item._onSelect();
                    });
                    emailBox.appendChild(item);
                });
                showSuggestions(emailBox, 'E-Mail');
            });
        }
    }

    // ===== ADDRESS AUTOCOMPLETE =====
    if (addrInput && addrBox) {
        var addrTimer = null;
        var lastAddrQ = '';
        var nominatimCC = '<?php echo esc_js($nominatim_cc); ?>';

        function getUserStreet(val) {
            var parts = val.split(',');
            if (parts.length > 1) return parts[0].trim();
            var m = val.match(/^(.+?)\s+\d{4,5}\b/);
            if (m) return m[1].trim();
            return val.trim();
        }

        function triggerAddrSearch(){
            clearTimeout(addrTimer);
            var q = addrInput.value.trim();
            if (q === lastAddrQ) return;
            lastAddrQ = q;
            if (q.length < 3) { hideSuggestions(addrBox); return; }
            addrTimer = setTimeout(function(){ fetchAddrSuggestions(q); }, 400);
        }

        addrInput.addEventListener('input', triggerAddrSearch);
        addrInput.addEventListener('keyup', triggerAddrSearch);

        function fetchAddrSuggestions(q) {
            var url = 'https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=5&countrycodes=' + nominatimCC + '&q=' + encodeURIComponent(q);
            xhrGet(url, function(err, results){
                if (err || !results || !results.length) {
                    hideSuggestions(addrBox);
                    return;
                }
                var userStreet = getUserStreet(addrInput.value);
                addrBox.innerHTML = '';
                results.forEach(function(r){
                    var a = r.address || {};
                    var apiStreet = (a.road || '') + (a.house_number ? ' ' + a.house_number : '');
                    var city = a.city || a.town || a.village || a.municipality || '';
                    var plz = a.postcode || '';
                    var street = apiStreet;
                    if (userStreet && !a.house_number) {
                        var road = (a.road || '').toLowerCase();
                        if (road && userStreet.toLowerCase().indexOf(road) === 0) {
                            street = userStreet;
                        }
                    }
                    var displayShort = (street ? street + ', ' : '') + (plz ? plz + ' ' : '') + city;
                    var displayFull = r.display_name;
                    var item = document.createElement('div');
                    item.className = 'rf-sug-item';
                    item.innerHTML = '<div class="rf-sug-main">' + escH(displayShort || displayFull) + '</div>' +
                        '<div class="rf-sug-sub">' + escH(displayFull) + '</div>';
                    item._onSelect = function(){
                        var finalStreet = street || userStreet;
                        addrInput.value = finalStreet + ', ' + (plz ? plz + ' ' : '') + city;
                        addrBox.style.display = 'none';
                    };
                    item.addEventListener('click', function(e){
                        e.preventDefault();
                        e.stopPropagation();
                        item._onSelect();
                    });
                    addrBox.appendChild(item);
                });
                showSuggestions(addrBox, '<?php echo esc_js(PPV_Lang::t('repair_address_label')); ?>');
            });
        }
    }
})();
</script>

<?php if (PPV_AI_Engine::is_available()): ?>
<style>
.repair-ai-btn{display:inline-flex;align-items:center;gap:6px;margin-top:8px;padding:8px 16px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border:none;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;transition:all .3s cubic-bezier(.4,0,.2,1);font-family:inherit;box-shadow:0 2px 8px rgba(102,126,234,0.25)}
.repair-ai-btn:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(102,126,234,0.35)}
.repair-ai-btn:active{transform:translateY(0)}
.repair-ai-btn:disabled{opacity:.6;cursor:wait;transform:none}
.repair-ai-btn i{font-size:16px}
@keyframes ppvAiPulse{0%,100%{opacity:1}50%{opacity:.5}}
.repair-ai-btn:disabled i{animation:ppvAiPulse 1.2s ease-in-out infinite}
.repair-ai-result{margin-top:10px;border:1.5px solid #e0e7ff;border-radius:12px;background:linear-gradient(135deg,#f5f3ff 0%,#eff6ff 100%);overflow:hidden;animation:ppvAiFadeIn .3s ease}
@keyframes ppvAiFadeIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.repair-ai-header{display:flex;align-items:center;gap:6px;padding:10px 14px;background:rgba(102,126,234,0.08);font-size:13px;font-weight:600;color:#4f46e5}
.repair-ai-header i{font-size:16px}
.repair-ai-close{margin-left:auto;background:none;border:none;font-size:18px;color:#94a3b8;cursor:pointer;padding:0 4px;line-height:1}
.repair-ai-close:hover{color:#64748b}
.repair-ai-content{padding:12px 14px;font-size:13px;line-height:1.6;color:#334155;white-space:pre-line}
.repair-ai-hint{padding:6px 14px 10px;font-size:11px;color:#94a3b8;font-style:italic}
</style>
<script>
(function(){
    var aiBtn = document.getElementById('rf-ai-btn');
    var aiBtnText = document.getElementById('rf-ai-btn-text');
    var aiResult = document.getElementById('rf-ai-result');
    var aiContent = document.getElementById('rf-ai-content');
    var aiClose = document.getElementById('rf-ai-close');
    var problemField = document.getElementById('rf-problem');
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var lang = '<?php echo esc_js($lang); ?>';
    var serviceType = <?php echo json_encode($service_type); ?>;

    if (!aiBtn || !problemField) return;

    // Show AI button when user types enough text
    var showTimer = null;
    problemField.addEventListener('input', function() {
        clearTimeout(showTimer);
        var len = problemField.value.trim().length;
        if (len >= 10) {
            showTimer = setTimeout(function() {
                aiBtn.style.display = 'inline-flex';
            }, 500);
        } else {
            aiBtn.style.display = 'none';
            aiResult.style.display = 'none';
        }
    });

    // Check on load (for restored drafts)
    if (problemField.value.trim().length >= 10) {
        aiBtn.style.display = 'inline-flex';
    }

    aiBtn.addEventListener('click', function() {
        var problem = problemField.value.trim();
        if (problem.length < 5) return;

        // Get device info if available
        var brandEl = document.getElementById('rf-brand');
        var modelEl = document.getElementById('rf-model');
        var brand = brandEl ? brandEl.value : '';
        var model = modelEl ? modelEl.value : '';

        // Loading state
        aiBtn.disabled = true;
        aiBtnText.textContent = ppvLang.ai_analyzing;
        aiResult.style.display = 'none';

        var fd = new FormData();
        fd.append('action', 'ppv_repair_ai_analyze');
        fd.append('problem', problem);
        fd.append('brand', brand);
        fd.append('model', model);
        fd.append('service_type', serviceType);
        fd.append('lang', lang);

        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.data.analysis) {
                    aiContent.textContent = data.data.analysis;
                    aiResult.style.display = 'block';
                    aiResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    aiContent.textContent = data.data?.message || ppvLang.ai_error;
                    aiResult.style.display = 'block';
                }
            })
            .catch(function() {
                aiContent.textContent = ppvLang.ai_error;
                aiResult.style.display = 'block';
            })
            .finally(function() {
                aiBtn.disabled = false;
                aiBtnText.textContent = ppvLang.ai_btn;
            });
    });

    // Close AI result
    if (aiClose) {
        aiClose.addEventListener('click', function() {
            aiResult.style.display = 'none';
        });
    }
})();
</script>
<?php endif; ?>

<!-- Mobile suggestions bottom-sheet -->
<div class="rf-sheet-overlay" id="rf-sheet-overlay">
    <div class="rf-sheet">
        <div class="rf-sheet-handle"><span></span></div>
        <div class="rf-sheet-header">
            <h4 id="rf-sheet-title"></h4>
            <button type="button" class="rf-sheet-close" id="rf-sheet-close">&times;</button>
        </div>
        <div class="rf-sheet-list" id="rf-sheet-list"></div>
    </div>
</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }
}
