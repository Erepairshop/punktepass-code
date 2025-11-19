<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Kassenscanner & Kampagnen v5.3 COMPLETE PHP
 * ✅ Gratis Termék opcióval
 * ✅ Dinamikus mezők
 * ✅ Teljes kampány kezelés
 * Author: Erik Borota / PunktePass
 */

class PPV_QR {

    const RATE_LIMIT_SCANS = 1;
    const RATE_LIMIT_WINDOW = 86400;

    // ============================================================
    // 🔹 INITIALIZATION
    // ============================================================
    public static function hooks() {
        add_shortcode('ppv_qr_center', [__CLASS__, 'render_qr_center']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    // ============================================================
    // 🧠 HELPERS
    // ============================================================

    private static function get_lang() {
        static $lang = null;
        if ($lang === null) {
            $lang = class_exists('PPV_Lang') ? (PPV_Lang::$strings ?? []) : [];
        }
        return $lang;
    }

    private static function t($key, $fallback = '') {
        $L = self::get_lang();
        return esc_html($L[$key] ?? $fallback);
    }

    private static function get_store_by_key($store_key) {
        if (empty($store_key)) return null;

        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email FROM {$wpdb->prefix}ppv_stores WHERE store_key=%s LIMIT 1",
            $store_key
        ));

        return $store ?: null;
    }

    private static function validate_store($store_key) {
        $store = self::get_store_by_key($store_key);

        if (!$store) {
            return [
                'valid' => false,
                'response' => new WP_REST_Response([
                    'success' => false,
                    'message' => self::t('err_unknown_store', '❌ Ismeretlen bolt')
                ], 400)
            ];
        }

        return [
            'valid' => true,
            'store' => $store
        ];
    }

    private function prepare_campaign_fields($data, $store_id) {
    $fields = [];

    // 🎯 Alap adatok
    $fields['store_id'] = (int) $store_id;
    $fields['title'] = sanitize_text_field($data['title'] ?? '');
    $fields['description'] = sanitize_textarea_field($data['description'] ?? '');
    $fields['status'] = sanitize_text_field($data['status'] ?? 'active');
    $fields['start_date'] = sanitize_text_field($data['start_date'] ?? '');
    $fields['end_date'] = sanitize_text_field($data['end_date'] ?? '');
    $fields['campaign_type'] = sanitize_text_field($data['campaign_type'] ?? '');

    $type = $fields['campaign_type'];

    // 🧠 Kampány típus szerint értékek hozzárendelése
    switch ($type) {
        case 'points':
            // pl. +50 extra pont
            $fields['extra_points'] = (int)($data['camp_value'] ?? 0);
            break;

        case 'discount':
            // pl. -20% kedvezmény
            $fields['discount_percent'] = (float)($data['camp_value'] ?? 0);
            break;

        case 'fixed':
            // pl. -5€ fix kedvezmény
            $fields['fixed_amount'] = (float)($data['camp_value'] ?? 0);
            break;

        case 'free_product':
            // 🎁 Ajándék termék kampány
            $fields['free_product'] = sanitize_text_field($data['free_product'] ?? '');
            $fields['free_product_value'] = floatval($data['free_product_value'] ?? 0);
            break;

        default:
            // ha típus ismeretlen, biztonsági fallback
            $fields['extra_points'] = 0;
            break;
    }

    // 🪙 Kiegészítő mezők (opcionális)
    $fields['required_points'] = (int)($data['required_points'] ?? 0);
    $fields['points_given'] = (int)($data['points_given'] ?? 0);

    return $fields;
}


    private static function check_rate_limit($user_id, $store_id) {
        global $wpdb;

        $recent = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
            WHERE user_id=%d AND store_id=%d 
            AND created >= (NOW() - INTERVAL %d SECOND)
        ", $user_id, $store_id, self::RATE_LIMIT_WINDOW));

        if ($recent >= self::RATE_LIMIT_SCANS) {
            return [
                'limited' => true,
                'response' => new WP_REST_Response([
                    'success' => false,
                    'message' => self::t('err_rate_limited', '⚠️ Túl sok scan. Kérlek várj!')
                ], 429)
            ];
        }

        return ['limited' => false];
    }

    private static function insert_log($store_id, $user_id, $msg, $type = 'scan') {
        global $wpdb;

        $wpdb->insert("{$wpdb->prefix}ppv_pos_log", [
            'store_id' => $store_id,
            'user_id' => $user_id,
            'message' => sanitize_text_field($msg),
            'type' => $type,
            'created_at' => current_time('mysql')
        ]);
    }

    private static function decode_user_from_qr($qr) {
        if (empty($qr)) return false;

        if (strpos($qr, 'PPU') === 0) {
            $body = substr($qr, 3);
            if (preg_match('/^(\d+)/', $body, $m)) {
                return intval($m[1]);
            }
        }

        if (strpos($qr, 'PPUSER-') === 0) {
            $parts = explode('-', $qr);
            return intval($parts[1] ?? 0);
        }

        return false;
    }

    // ============================================================
    // 📡 ASSET ENQUEUE
    // ============================================================
    public static function enqueue_assets() {
        global $wpdb;

        // ✅ SESSION INICIALIZÁLÁS
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        wp_enqueue_style(
            'remixicons',
            'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css',
            [],
            null
        );

        wp_enqueue_script('ppv-qr', PPV_PLUGIN_URL . 'assets/js/ppv-qr.js', ['jquery'], time(), true);

        $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? '');
        if (empty($lang) || !in_array($lang, ['de', 'hu', 'ro'])) {
            $lang = defined('PPV_LANG_ACTIVE') ? PPV_LANG_ACTIVE : 'de';
        }

        if (class_exists('PPV_Lang')) {
            $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang}.php";
            if (!file_exists($file)) {
                $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-de.php";
            }
            $strings = include $file;
        } else {
            $strings = [];
        }

        wp_add_inline_script('ppv-qr', 'window.ppv_lang = ' . wp_json_encode($strings) . ';', 'before');

        $store_id = 0;
        $store_key = '';

        // 1️⃣ SESSION
        if (!empty($_SESSION['ppv_active_store'])) {
            $store_id = intval($_SESSION['ppv_active_store']);
            error_log("✅ [PPV_QR] Store ID from SESSION: {$store_id}");
        }
        // 2️⃣ GLOBAL
        elseif (!empty($GLOBALS['ppv_active_store'])) {
            $active = $GLOBALS['ppv_active_store'];
            $store_id = is_object($active) ? intval($active->id) : intval($active);
            error_log("✅ [PPV_QR] Store ID from GLOBAL: {$store_id}");
        }
        // 3️⃣ LOGGED IN USER
        elseif (is_user_logged_in()) {
            $uid = get_current_user_id();
            $store_id = intval($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                $uid
            )));
            error_log("✅ [PPV_QR] Store ID from USER ({$uid}): {$store_id}");
        }
        // 4️⃣ ADMIN FALLBACK
        else {
            if (current_user_can('administrator')) {
                $row = $wpdb->get_row("SELECT id, store_key FROM {$wpdb->prefix}ppv_stores WHERE id=1 LIMIT 1");
                if ($row) {
                    $store_id = $row->id;
                    $store_key = $row->store_key;
                    error_log("✅ [PPV_QR] Store ID from ADMIN FALLBACK: {$store_id}");
                } else {
                    error_log("❌ [PPV_QR] No admin store found!");
                }
            }
        }

        // Fetch store_key if store_id is set
        if ($store_id > 0 && empty($store_key)) {
            $store_key = $wpdb->get_var($wpdb->prepare(
                "SELECT store_key FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
                $store_id
            ));
        }

        error_log("🧩 [PPV_QR_ASSET] store_id={$store_id} | store_key={$store_key} | user=" . (is_user_logged_in() ? get_current_user_id() : 'none'));

        wp_localize_script('ppv-qr', 'PPV_STORE_DATA', [
            'store_id' => intval($store_id),
            'store_key' => $store_key ?: '',
        ]);

        wp_enqueue_script('ppv-hidden-scan', PPV_PLUGIN_URL . 'assets/js/ppv-hidden-scan.js', [], time(), true);

        $lang = defined('PPV_LANG_ACTIVE') ? PPV_LANG_ACTIVE : (get_locale() ?? 'de');
        wp_localize_script('ppv-hidden-scan', 'PPV_SCAN_DATA', [
            'rest_url' => esc_url(rest_url('punktepass/v1/pos/scan')),
            'store_key' => $store_key ?: '',
            'plugin_url' => PPV_PLUGIN_URL,
            'lang' => substr($lang, 0, 2),
        ]);
    }

    // ============================================================
    // 🎨 FRONTEND RENDERING
    // ============================================================
    public static function render_qr_center() {
        global $wpdb;
        
        $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? '');
        if (empty($lang) || !in_array($lang, ['de', 'hu', 'ro'])) {
            $lang = defined('PPV_LANG_ACTIVE') ? PPV_LANG_ACTIVE : 'de';
        }
        
        $lang_file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang}.php";
        if (!file_exists($lang_file)) {
            $lang_file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-de.php";
        }
        $GLOBALS['ppv_current_lang'] = include $lang_file;

        ?>
        <div class="ppv-qr-wrapper glass-card">
            <h2>📡 <?php echo self::t('qrcamp_title', 'Kassenscanner & Kampagnen'); ?></h2>

            <div class="ppv-tabs">
                <button class="ppv-tab active" data-tab="scanner" id="ppv-tab-scanner">
                    <?php echo self::t('tab_scanner', 'Kassenscanner'); ?>
                </button>
                <button class="ppv-tab" data-tab="rewards" id="ppv-tab-rewards">
                    🎁 <?php echo self::t('tab_rewards', 'Prämien'); ?>
                </button>
                <button class="ppv-tab" data-tab="campaigns" id="ppv-tab-campaigns">
                    <?php echo self::t('tab_campaigns', 'Kampagnen'); ?>
                </button>
            </div>

            <!-- TAB CONTENT: SCANNER -->
            <div class="ppv-tab-content active" id="tab-scanner">
                <?php self::render_pos_scanner(); ?>
            </div>

            <!-- TAB CONTENT: PRÄMIEN -->
            <div class="ppv-tab-content" id="tab-rewards">
                <?php echo do_shortcode('[ppv_rewards_management]'); ?>
            </div>

            <!-- TAB CONTENT: KAMPAGNEN -->
            <div class="ppv-tab-content" id="tab-campaigns">
                <?php self::render_campaigns(); ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            $(".ppv-tab").on("click", function(){
                $(".ppv-tab").removeClass("active");
                $(this).addClass("active");
                $(".ppv-tab-content").removeClass("active");
                $("#tab-" + $(this).data("tab")).addClass("active");
            });
        });
        </script>

        <?php
        if (class_exists('PPV_Bottom_Nav')) {
            echo PPV_Bottom_Nav::render_nav();
        } else {
            echo do_shortcode('[ppv_bottom_nav]');
        }
    }

    // ============================================================
    // 📲 POS SCANNER
    // ============================================================
    public static function render_pos_scanner() {
        global $wpdb;

        // Get store trial info
        $store_id = 0;
        $trial_days_left = 0;
        $subscription_days_left = 0;
        $subscription_status = 'unknown';

        // Try to get store ID from session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!empty($_SESSION['ppv_active_store'])) {
            $store_id = intval($_SESSION['ppv_active_store']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        }

        // Fetch subscription info from database
        if ($store_id > 0) {
            $store_data = $wpdb->get_row($wpdb->prepare(
                "SELECT trial_ends_at, subscription_status, subscription_expires_at FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
                $store_id
            ));

            if ($store_data) {
                $subscription_status = $store_data->subscription_status ?? 'trial';
                $now = current_time('timestamp');

                // Calculate trial days left
                if (!empty($store_data->trial_ends_at)) {
                    $trial_end = strtotime($store_data->trial_ends_at);
                    $diff_seconds = $trial_end - $now;
                    $trial_days_left = max(0, ceil($diff_seconds / 86400));
                }

                // Calculate subscription days left (for active subscriptions)
                if (!empty($store_data->subscription_expires_at)) {
                    $sub_end = strtotime($store_data->subscription_expires_at);
                    $diff_seconds = $sub_end - $now;
                    $subscription_days_left = max(0, ceil($diff_seconds / 86400));
                }
            }
        }

        // Determine message and styling based on days left
        $info_class = 'info';
        $info_icon = 'ℹ️';
        $info_color = 'rgba(0, 230, 255, 0.1)';
        $border_color = 'rgba(0, 230, 255, 0.3)';
        $show_description = false;

        if ($subscription_status === 'active') {
            // Active subscription with expiry date
            if ($subscription_days_left > 0) {
                if ($subscription_days_left > 30) {
                    $info_message = sprintf(self::t('subscription_active_days', 'Aktives Abo - Noch %d Tage'), $subscription_days_left);
                    $info_class = 'success';
                    $info_icon = '✅';
                    $info_color = 'rgba(0, 230, 118, 0.1)';
                    $border_color = 'rgba(0, 230, 118, 0.3)';
                } elseif ($subscription_days_left > 7) {
                    $info_message = sprintf(self::t('subscription_expiring_soon', 'Abo läuft in %d Tagen ab'), $subscription_days_left);
                    $info_class = 'info';
                    $info_icon = '📅';
                    $info_color = 'rgba(0, 230, 255, 0.1)';
                    $border_color = 'rgba(0, 230, 255, 0.3)';
                } else {
                    $info_message = sprintf(self::t('subscription_expiring_warning', 'Abo endet bald - Nur noch %d Tage!'), $subscription_days_left);
                    $info_class = 'warning';
                    $info_icon = '⚠️';
                    $info_color = 'rgba(255, 171, 0, 0.1)';
                    $border_color = 'rgba(255, 171, 0, 0.3)';
                }
                $show_description = true;
            } else {
                // Active but expired
                $info_message = self::t('subscription_expired', 'Abo abgelaufen');
                $info_class = 'error';
                $info_icon = '❌';
                $info_color = 'rgba(239, 68, 68, 0.1)';
                $border_color = 'rgba(239, 68, 68, 0.3)';
            }
        } elseif ($trial_days_left > 7) {
            $info_message = sprintf(self::t('trial_days_left', 'Noch %d Tage Testversion'), $trial_days_left);
            $info_icon = '📅';
        } elseif ($trial_days_left > 0) {
            $info_message = sprintf(self::t('trial_days_left_warning', 'Nur noch %d Tage!'), $trial_days_left);
            $info_class = 'warning';
            $info_icon = '⚠️';
            $info_color = 'rgba(255, 171, 0, 0.1)';
            $border_color = 'rgba(255, 171, 0, 0.3)';
        } else {
            $info_message = self::t('trial_expired', 'Testversion abgelaufen');
            $info_class = 'error';
            $info_icon = '❌';
            $info_color = 'rgba(239, 68, 68, 0.1)';
            $border_color = 'rgba(239, 68, 68, 0.3)';
        }

        ?>
        <div class="ppv-trial-info-block" style="margin-bottom: 15px; padding: 12px 16px; background: <?php echo $info_color; ?>; border-radius: 10px; border: 2px solid <?php echo $border_color; ?>;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;"><?php echo $info_icon; ?></span>
                    <div>
                        <div style="font-weight: bold; font-size: 15px; margin-bottom: 2px;">
                            <?php echo $info_message; ?>
                        </div>
                        <?php if ($show_description && $subscription_status === 'active'): ?>
                            <div style="font-size: 12px; opacity: 0.7;">
                                <?php echo self::t('subscription_info_desc', 'Aktive Premium-Mitgliedschaft'); ?>
                            </div>
                        <?php elseif ($subscription_status === 'trial' && $trial_days_left > 0): ?>
                            <div style="font-size: 12px; opacity: 0.7;">
                                <?php echo self::t('trial_info_desc', 'Registriert mit 30 Tage Probezeit'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($subscription_status === 'trial' && $trial_days_left <= 7 && $trial_days_left > 0): ?>
                    <a href="/pricing" class="ppv-btn-outline" style="padding: 6px 12px; font-size: 13px; white-space: nowrap; text-decoration: none;">
                        <?php echo self::t('upgrade_now', 'Jetzt upgraden'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="ppv-pos-center glass-section">
            <div id="ppv-offline-banner" style="display:none;background:#ffcc00;padding:8px;border-radius:6px;margin-bottom:10px;">
                🛰️ <?php echo self::t('offline_banner', 'Offline-Modus aktiv'); ?>
                <button id="ppv-sync-btn" class="ppv-btn small" type="button">
                    <?php echo self::t('sync_now', 'Sync'); ?>
                </button>
            </div>

            <h3><?php echo self::t('scanner_title', '📲 Kassenscanner'); ?></h3>
            <p><?php echo self::t('scanner_desc', 'Scanne den QR-Code des Kunden'); ?></p>

            <input type="text" id="ppv-pos-input" placeholder="<?php echo esc_attr(self::t('scan_placeholder', 'Hier scannen...')); ?>" autofocus>

            <button id="ppv-pos-send" class="ppv-btn neon" type="button">
                <?php echo self::t('scan_button', 'QR prüfen'); ?>
            </button>

            <div id="ppv-pos-result" class="ppv-result-box"></div>

            <h4><?php echo self::t('table_title', '📋 Letzte Scans'); ?></h4>

            <table id="ppv-pos-log" class="glass-table">
                <thead>
                    <tr>
                        <th><?php echo self::t('t_col_time', 'Zeit'); ?></th>
                        <th><?php echo self::t('t_col_customer', 'Kunde'); ?></th>
                        <th><?php echo self::t('t_col_status', 'Status'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <!-- 🎥 CAMERA SCANNER MODAL -->
            <div id="ppv-camera-modal" class="ppv-modal" role="dialog" aria-modal="true" style="display: none;">
                <div class="ppv-modal-inner" style="max-width: 100%; width: 100%; max-height: 90vh; overflow-y: auto;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h4 style="margin: 0;">📷 <?php echo self::t('camera_scanner_title', 'Kamera QR-Scanner'); ?></h4>
                        <button id="ppv-camera-close" class="ppv-btn-outline" type="button" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;">
                            ✕
                        </button>
                    </div>

                    <p style="color: #999; font-size: 14px; margin-bottom: 15px;">
                        🎥 <?php echo self::t('camera_scanner_desc', 'Halte den QR-Code des Kunden vor die Kamera. Der Scanner erkennt diesen automatisch.'); ?>
                    </p>

                    <!-- Scanner Area -->
                    <div id="ppv-reader" style="width: 100%; max-width: 400px; height: 400px; margin: 0 auto 20px; border: 2px solid #00e6ff; border-radius: 14px; overflow: hidden; background: #000;"></div>

                    <!-- Result Box -->
                    <div id="ppv-scan-result" style="text-align: center; padding: 15px; background: rgba(0, 230, 255, 0.1); border-radius: 8px; border: 1px solid rgba(0, 230, 255, 0.3); margin-bottom: 15px; min-height: 40px; display: flex; align-items: center; justify-content: center; color: #00e6ff; font-weight: bold;">
                        ⏳ Scanner aktiv...
                    </div>

                    <!-- Controls -->
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button id="ppv-torch-toggle" class="ppv-btn" type="button" style="display: none;">
                            🔦 Taschenlampe
                        </button>
                        <button id="ppv-camera-cancel" class="ppv-btn-outline" type="button" style="flex: 1;">
                            <?php echo self::t('btn_cancel', 'Abbrechen'); ?>
                        </button>
                    </div>

                </div>
            </div>
        </div>
        <?php
    }

    // ============================================================
    // 🎯 KAMPAGNEN - KOMPLETT FORMA
    // ============================================================
    public static function render_campaigns() {
        ?>
        <div class="ppv-campaigns glass-section">
            <div class="ppv-campaign-header">
                <h3>🎯 <?php echo self::t('campaigns_title', 'Kampagnen'); ?></h3>
                <div class="ppv-campaign-controls">
                    <select id="ppv-campaign-filter" class="ppv-filter">
                        <option value="all">📋 <?php echo self::t('camp_filter_all', 'Alle'); ?></option>
                        <option value="active">🟢 <?php echo self::t('camp_filter_active', 'Aktive'); ?></option>
                        <option value="archived">📦 <?php echo self::t('camp_filter_archived', 'Archiv'); ?></option>
                    </select>
                    <button id="ppv-new-campaign" class="ppv-btn neon" type="button"><?php echo self::t('camp_new', '+ Neue Kampagne'); ?></button>
                </div>
            </div>

            <div id="ppv-campaign-list" class="ppv-campaign-list"></div>

            <!-- 🎯 KAMPAGNE MODAL - KOMPLETT FORMA! -->
            <div id="ppv-campaign-modal" class="ppv-modal" role="dialog" aria-modal="true">
                <div class="ppv-modal-inner">
                    <h4><?php echo self::t('camp_edit_modal', 'Kampagne bearbeiten'); ?></h4>

                    <!-- TITEL -->
                    <label><?php echo self::t('label_title', 'Titel'); ?></label>
                    <input type="text" id="camp-title" placeholder="<?php echo esc_attr(self::t('camp_placeholder_title', 'z. B. Doppelte Punkte-Woche')); ?>">

                    <!-- STARTDATUM -->
                    <label><?php echo self::t('label_start', 'Startdatum'); ?></label>
                    <input type="date" id="camp-start">

                    <!-- ENDDATUM -->
                    <label><?php echo self::t('label_end', 'Enddatum'); ?></label>
                    <input type="date" id="camp-end">

                    <!-- KAMPAGNEN TYP -->
                    <label><?php echo self::t('label_type', 'Kampagnen Typ'); ?></label>
                    <select id="camp-type">
                        <option value="points"><?php echo self::t('type_points', 'Extra Punkte'); ?></option>
                        <option value="discount"><?php echo self::t('type_discount', 'Rabatt (%)'); ?></option>
                        <option value="fixed"><?php echo self::t('type_fixed', 'Fix Bonus (€)'); ?></option>
                        <option value="free_product">🎁 <?php echo self::t('type_free_product', 'Gratis Termék'); ?></option>
                    </select>

                    <!-- SZÜKSÉGES PONTOK (csak POINTS típusnál!) -->
                    <div id="camp-required-points-wrapper" style="display: none;">
                        <label><?php echo self::t('label_required_points', 'Szükséges pontok'); ?></label>
                        <input type="number" id="camp-required-points" value="0" min="0" step="1">
                    </div>

                    <!-- WERT -->
                    <label id="camp-value-label"><?php echo self::t('camp_value_label', 'Wert'); ?></label>
                    <input type="number" id="camp-value" value="0" min="0" step="0.1">

                    <!-- PONTOK PER SCAN (csak POINTS típusnál!) -->
                    <div id="camp-points-given-wrapper" style="display: none;">
                        <label><?php echo self::t('label_points_given', 'Pontok per scan'); ?></label>
                        <input type="number" id="camp-points-given" value="1" min="1" step="1">
                    </div>

                    <!-- GRATIS TERMÉK NEVE (csak FREE_PRODUCT típusnál!) -->
                    <div id="camp-free-product-name-wrapper" style="display: none;">
                        <label><?php echo self::t('label_free_product', '🎁 Termék neve'); ?></label>
                        <input type="text" id="camp-free-product-name" placeholder="<?php echo esc_attr(self::t('camp_placeholder_free_product', 'pl. Kávé + Sütemény')); ?>">
                    </div>

                    <!-- GRATIS TERMÉK ÉRTÉKE (csak ha van termék név!) -->
                    <div id="camp-free-product-value-wrapper" style="display: none;">
                        <label style="color: #ff9800;">💰 <?php echo self::t('label_free_product_value', 'Termék értéke'); ?> <span style="color: #ff0000;">*</span></label>
                        <input type="number" id="camp-free-product-value" value="0" min="0.01" step="0.01" placeholder="0.00" style="border-color: #ff9800;">
                    </div>

                    <!-- STATUS -->
                    <label><?php echo self::t('label_status', 'Status'); ?></label>
                    <select id="camp-status">
                        <option value="active"><?php echo self::t('status_active', '🟢 Aktiv'); ?></option>
                        <option value="archived"><?php echo self::t('status_archived', '📦 Archiv'); ?></option>
                    </select>

                    <!-- GOMBÓK -->
                    <div class="ppv-modal-actions">
                        <button id="camp-save" class="ppv-btn neon" type="button">
                            <?php echo self::t('btn_save', '💾 Speichern'); ?>
                        </button>
                        <button id="camp-cancel" class="ppv-btn-outline" type="button">
                            <?php echo self::t('btn_cancel', 'Abbrechen'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    // ============================================================
    // 📡 REST ROUTES REGISTRATION
    // ============================================================
    public static function register_rest_routes() {
        register_rest_route('punktepass/v1', '/pos/scan', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_process_scan'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/logs', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_logs'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/sync_offline', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_sync_offline'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaign', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_create_campaign'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaigns', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_list_campaigns'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaign/delete', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_delete_campaign'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaign/update', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_update_campaign'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaign/archive', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_archive_campaign'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/strings', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_strings'],
            'permission_callback' => ['PPV_Permissions', 'allow_anonymous'],
        ]);
    }

    public static function rest_get_strings(WP_REST_Request $r) {
        $lang = sanitize_text_field($r->get_header('X-Lang') ?? $_GET['lang'] ?? 'de');

        if (!in_array($lang, ['de', 'hu', 'ro'])) {
            $lang = 'de';
        }

        $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang}.php";
        if (!file_exists($file)) {
            $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-de.php";
        }

        $strings = include $file;

        return new WP_REST_Response($strings, 200);
    }

    // ============================================================
    // 🔍 REST: PROCESS SCAN
    // ============================================================
    public static function rest_process_scan(WP_REST_Request $r) {
        global $wpdb;

        $data = $r->get_json_params();
        $qr_code = sanitize_text_field($data['qr'] ?? '');
        $store_key = sanitize_text_field($data['store_key'] ?? '');

        if (empty($qr_code) || empty($store_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_invalid_request', '❌ Érvénytelen kérés')
            ], 400);
        }

        $validation = self::validate_store($store_key);
        if (!$validation['valid']) {
            return $validation['response'];
        }
        $store = $validation['store'];

        $user_id = self::decode_user_from_qr($qr_code);
        if (!$user_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_invalid_qr', '❌ Érvénytelen QR')
            ], 400);
        }

        $rate_check = self::check_rate_limit($user_id, $store->id);
        if ($rate_check['limited']) {
            return $rate_check['response'];
        }

        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id' => $user_id,
            'store_id' => $store->id,
            'points' => 1,
            'type' => 'qr_scan',
            'created' => current_time('mysql')
        ]);

        self::insert_log($store->id, $user_id, self::t('log_point_added', '1 pont hozzáadva'), 'qr_scan');

        return new WP_REST_Response([
            'success' => true,
            'message' => self::t('scan_success', '✅ 1 pont hozzáadva'),
            'user_id' => $user_id,
            'store_id' => $store->id,
            'time' => current_time('mysql')
        ], 200);
    }

    // ============================================================
    // 📜 REST: GET LOGS
    // ============================================================
    public static function rest_get_logs(WP_REST_Request $r) {
        global $wpdb;

        $store_key = sanitize_text_field(
            $r->get_header('ppv-pos-token') ?? $r->get_param('store_key') ?? ''
        );

        if (empty($store_key)) return new WP_REST_Response([], 400);

        $store = self::get_store_by_key($store_key);
        if (!$store) return new WP_REST_Response([], 400);

        return new WP_REST_Response($wpdb->get_results($wpdb->prepare("
            SELECT created_at, user_id, message
            FROM {$wpdb->prefix}ppv_pos_log
            WHERE store_id=%d
            ORDER BY id DESC LIMIT 15
        ", $store->id)), 200);
    }

    // ============================================================
    // 💾 REST: OFFLINE SYNC
    // ============================================================
    public static function rest_sync_offline(WP_REST_Request $r) {
        global $wpdb;

        $payload = $r->get_json_params();
        $scans = $payload['scans'] ?? [];
        $synced = 0;
        $duplicates = [];

        foreach ($scans as $s) {
            $qr = sanitize_text_field($s['qr'] ?? '');
            $store_key = sanitize_text_field($s['store_key'] ?? '');

            if (empty($qr) || empty($store_key)) continue;

            $store = self::get_store_by_key($store_key);
            if (!$store) continue;

            $user = self::decode_user_from_qr($qr);
            if (!$user) continue;

            $recent = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
                WHERE user_id=%d 
                AND store_id=%d
                AND created >= DATE_SUB(%s, INTERVAL 2 MINUTE)
            ", $user, $store->id, current_time('mysql')));

            if ($recent > 0) {
                error_log("⚠️ [OFFLINE_SYNC] Duplikátum detektálva: QR=$qr, User=$user");
                $duplicates[] = $qr;
                continue;
            }

            $wpdb->insert("{$wpdb->prefix}ppv_points", [
                'user_id' => $user,
                'store_id' => $store->id,
                'points' => 1,
                'type' => 'pos_offline',
                'reference' => $qr,
                'created' => current_time('mysql')
            ]);

            self::insert_log($store->id, $user, "Offline szinkronizálva: $qr", 'offline_sync');
            $synced++;
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => self::t('offline_synced', '✅ Offline szinkronizálva'),
            'synced' => $synced,
            'duplicates' => $duplicates,
            'duplicate_count' => count($duplicates)
        ], 200);
    }

    // ============================================================
    // 🎯 REST: CREATE CAMPAIGN
    // ============================================================
    public static function rest_create_campaign(WP_REST_Request $r) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // 🧩 UNIVERZÁLIS JSON PARSE FIX
        $raw = @file_get_contents('php://input');
        if (!$raw || trim($raw) === '') {
            // néha a streamet újra kell nyitni, ha már olvasta a WP
            $stream = fopen('php://input', 'r');
            $raw = stream_get_contents($stream);
            fclose($stream);
        }

        $data = json_decode($raw, true);
        if (empty($data)) $data = $r->get_json_params();
        if (empty($data)) $data = $_POST;

        // 🔍 LOG – ellenőrzéshez
        error_log("🧩 [PPV_CREATE_RAW_BODY] " . substr($raw, 0, 300));
        error_log("🧩 [PPV_CREATE_PARSED] " . print_r($data, true));

        error_log('🧩 [PPV_CREATE_CAMPAIGN_DATA] ' . print_r($data, true));

        $store_key = sanitize_text_field($data['store_key'] ?? '');
        $validation = self::validate_store($store_key);
        if (!$validation['valid']) return $validation['response'];
        $store = $validation['store'];

        // 🎯 Kampány adatok előkészítése
        // ✅ FIX: Don't accept empty campaign_type
        $campaign_type = sanitize_text_field($data['campaign_type'] ?? '');
        if (empty($campaign_type)) {
            error_log("⚠️ [PPV_QR] Empty campaign_type received, defaulting to 'points'");
            $campaign_type = 'points';
        }

        $fields = [
            'store_id'           => $store->id,
            'title'              => sanitize_text_field($data['title'] ?? ''),
            'start_date'         => sanitize_text_field($data['start_date'] ?? ''),
            'end_date'           => sanitize_text_field($data['end_date'] ?? ''),
            'campaign_type'      => $campaign_type,
            'required_points'    => (int)($data['required_points'] ?? 0),
            'points_given'       => (int)($data['points_given'] ?? 1),
            'status'             => sanitize_text_field($data['status'] ?? 'active'),
            'created_at'         => current_time('mysql'),
        ];

        // 🧠 Típusfüggő értékek
        switch ($fields['campaign_type']) {
            case 'points':
                $fields['extra_points'] = (int)($data['camp_value'] ?? 0);
                break;
            case 'discount':
                $fields['discount_percent'] = (float)($data['camp_value'] ?? 0);
                break;
            case 'fixed':
                $fields['fixed_amount'] = (float)($data['camp_value'] ?? 0);
                break;
            case 'free_product':
                $fields['free_product'] = sanitize_text_field($data['free_product'] ?? '');
                $fields['free_product_value'] = (float)($data['free_product_value'] ?? 0);
                break;
            default:
                error_log("⚠️ [PPV_QR] Ismeretlen kampány típus: " . ($fields['campaign_type'] ?? 'null'));
                break;
        }

        // ✅ DEBUG: Log fields before insert
        error_log("🔍 [PPV_QR] Fields to insert: " . print_r($fields, true));

        $wpdb->insert("{$prefix}ppv_campaigns", $fields);

        // ✅ DEBUG: Check if insert succeeded
        if ($wpdb->last_error) {
            error_log("❌ [PPV_QR] SQL Error: " . $wpdb->last_error);
        } else {
            error_log("✅ [PPV_QR] Insert success, ID: " . $wpdb->insert_id);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => '✅ Kampány sikeresen létrehozva',
            'fields'  => $fields,
        ], 200);
    }

    // ============================================================
    // 📋 REST: LIST CAMPAIGNS
    // ============================================================
    public static function rest_list_campaigns(WP_REST_Request $r) {
        global $wpdb;

        $store_key = sanitize_text_field(
            $r->get_header('ppv-pos-token') ?? $r->get_param('store_key') ?? ''
        );

        if (empty($store_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Token hiányzik'
            ], 400);
        }

        $store = self::get_store_by_key($store_key);
        if (!$store) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ismeretlen bolt'
            ], 400);
        }

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT id, title, start_date, end_date, campaign_type, multiplier,
                   extra_points, discount_percent, min_purchase, fixed_amount, status,
                   required_points, free_product, free_product_value, points_given
            FROM {$wpdb->prefix}ppv_campaigns
            WHERE store_id=%d ORDER BY start_date DESC
        ", $store->id));

        if (empty($rows)) {
            return new WP_REST_Response([], 200);
        }

        $store_country = $wpdb->get_var($wpdb->prepare(
            "SELECT country FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store->id
        ));

        $today = date('Y-m-d');
        $data = array_map(function ($r) use ($today, $store_country) {
            if ($today < $r->start_date) {
                $state = 'upcoming';
            } elseif ($today > $r->end_date) {
                $state = 'expired';
            } else {
                $state = 'active';
            }

            return [
                'id' => intval($r->id),
                'title' => $r->title,
                'start_date' => $r->start_date,
                'end_date' => $r->end_date,
                'campaign_type' => $r->campaign_type,
                'multiplier' => intval($r->multiplier),
                'extra_points' => intval($r->extra_points),
                'discount_percent' => floatval($r->discount_percent),
                'min_purchase' => floatval($r->min_purchase),
                'fixed_amount' => floatval($r->fixed_amount),
                'required_points' => intval($r->required_points ?? 0),
                'free_product' => $r->free_product ?? '',
                'free_product_value' => floatval($r->free_product_value ?? 0),
                'points_given' => intval($r->points_given ?? 1),
                'status' => $r->status,
                'state' => $state,
                'country' => $store_country
            ];
        }, $rows);

        return new WP_REST_Response($data, 200);
    }

    // ============================================================
    // 🗑️ REST: DELETE CAMPAIGN
    // ============================================================
    public static function rest_delete_campaign(WP_REST_Request $r) {
        global $wpdb;

        $d = $r->get_json_params();
        $store_key = sanitize_text_field($d['store_key'] ?? '');
        $id = intval($d['id'] ?? 0);

        if (empty($id) || empty($store_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_missing_data', '❌ Hiányzó adat')
            ], 400);
        }

        $validation = self::validate_store($store_key);
        if (!$validation['valid']) {
            return $validation['response'];
        }
        $store = $validation['store'];

        $wpdb->delete("{$wpdb->prefix}ppv_campaigns", [
            'id' => $id,
            'store_id' => $store->id
        ]);

        self::insert_log($store->id, 0, "Kampány törölve: ID {$id}", 'campaign_delete');

        return new WP_REST_Response([
            'success' => true,
            'message' => self::t('campaign_deleted', '🗑️ Kampány törölve!')
        ], 200);
    }

    // ============================================================
    // ✏️ REST: UPDATE CAMPAIGN
    // ============================================================
    public static function rest_update_campaign(WP_REST_Request $r) {
        global $wpdb;
        
        $prefix = $wpdb->prefix;
        $raw = @file_get_contents('php://input');
        if (!$raw || trim($raw) === '') {
            $stream = fopen('php://input', 'r');
            $raw = stream_get_contents($stream);
            fclose($stream);
        }

        $d = json_decode($raw, true);
        if (empty($d)) $d = $r->get_json_params();
        if (empty($d)) $d = $_POST;

        error_log("🧩 [PPV_UPDATE_RAW_BODY] " . substr($raw, 0, 300));
        error_log("🧩 [PPV_UPDATE_PARSED] " . print_r($d, true));

        error_log('🧩 [PPV_UPDATE_CAMPAIGN_DATA] ' . print_r($d, true));

        $id = intval($d['id'] ?? 0);
        $store_key = sanitize_text_field($d['store_key'] ?? '');

        if (empty($id) || empty($store_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ Kampány ID vagy bolt kulcs hiányzik'
            ], 400);
        }

        $validation = self::validate_store($store_key);
        if (!$validation['valid']) {
            return $validation['response'];
        }
        $store = $validation['store'];

        // 🎯 Frissítési mezők
        // ✅ FIX: Don't accept empty campaign_type
        $campaign_type = sanitize_text_field($d['campaign_type'] ?? '');
        if (empty($campaign_type)) {
            error_log("⚠️ [PPV_QR] Empty campaign_type in update, defaulting to 'points'");
            $campaign_type = 'points';
        }

        $fields = [
            'title'           => sanitize_text_field($d['title'] ?? ''),
            'start_date'      => sanitize_text_field($d['start_date'] ?? ''),
            'end_date'        => sanitize_text_field($d['end_date'] ?? ''),
            'campaign_type'   => $campaign_type,
            'required_points' => (int)($d['required_points'] ?? 0),
            'points_given'    => (int)($d['points_given'] ?? 1),
            'status'          => sanitize_text_field($d['status'] ?? 'active'),
            'updated_at'      => current_time('mysql'),
        ];

        // 🧠 Típusfüggő értékek
        switch ($fields['campaign_type']) {
            case 'points':
                $fields['extra_points'] = (int)($d['camp_value'] ?? 0);
                break;
            case 'discount':
                $fields['discount_percent'] = (float)($d['camp_value'] ?? 0);
                break;
            case 'fixed':
                $fields['fixed_amount'] = (float)($d['camp_value'] ?? 0);
                break;
            case 'free_product':
                $fields['free_product'] = sanitize_text_field($d['free_product'] ?? '');
                $fields['free_product_value'] = (float)($d['free_product_value'] ?? 0);
                break;
            default:
                error_log("⚠️ [PPV_QR] Ismeretlen kampány típus frissítésnél: " . ($fields['campaign_type'] ?? 'null'));
                break;
        }

        $wpdb->update("{$prefix}ppv_campaigns", $fields, [
            'id'        => $id,
            'store_id'  => $store->id,
        ]);
        error_log("🧩 [PPV_DEBUG_SQL] UPDATE result=" . $wpdb->rows_affected . " | last_error=" . $wpdb->last_error);

        self::insert_log($store->id, 0, "Kampány frissítve: ID {$id}", 'campaign_update');

        return new WP_REST_Response([
            'success' => true,
            'message' => '✅ Kampány sikeresen frissítve',
            'fields'  => $fields,
        ], 200);
    }

    // ============================================================
    // 📦 REST: ARCHIVE CAMPAIGN
    // ============================================================
    public static function rest_archive_campaign(WP_REST_Request $r) {
        global $wpdb;

        $d = $r->get_json_params();
        $id = intval($d['id'] ?? 0);
        $store_key = sanitize_text_field($d['store_key'] ?? '');

        if (empty($id) || empty($store_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_missing_data', '❌ Hiányzó adat')
            ], 400);
        }

        $validation = self::validate_store($store_key);
        if (!$validation['valid']) {
            return $validation['response'];
        }
        $store = $validation['store'];

        $wpdb->update("{$wpdb->prefix}ppv_campaigns", [
            'status' => 'archived',
            'updated_at' => current_time('mysql')
        ], [
            'id' => $id,
            'store_id' => $store->id
        ]);

        self::insert_log($store->id, 0, "Kampány archivált: ID {$id}", 'campaign_archive');

        return new WP_REST_Response([
            'success' => true,
            'message' => self::t('archived_success', '📦 Archiválva')
        ], 200);
    }
}

PPV_QR::hooks();