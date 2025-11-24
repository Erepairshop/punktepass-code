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

    // ============================================================
    // 🔹 INITIALIZATION
    // ============================================================
    public static function hooks() {
        add_shortcode('ppv_qr_center', [__CLASS__, 'render_qr_center']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('wp_ajax_ppv_switch_filiale', [__CLASS__, 'ajax_switch_filiale']);
        // Ably auth endpoint for token requests (both logged-in and guest users)
        add_action('wp_ajax_ppv_ably_auth', [__CLASS__, 'ajax_ably_auth']);
        add_action('wp_ajax_nopriv_ppv_ably_auth', [__CLASS__, 'ajax_ably_auth']);
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

    /** ============================================================
     *  🏪 GET STORE ID (Session-aware with FILIALE support)
     *  Priority: ppv_current_filiale_id > ppv_store_id > store_key
     * ============================================================ */
    private static function get_session_aware_store_id($store_key = '') {
        global $wpdb;

        // 🔐 Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST (if session exists)
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $filiale_id = intval($_SESSION['ppv_current_filiale_id']);
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT id, email FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
                $filiale_id
            ));
            if ($store) {
                return $store;
            }
        }

        // Session - base store
        if (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT id, email FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
                $store_id
            ));
            if ($store) {
                return $store;
            }
        }

        // Fallback: store_key (for POS devices without session)
        if (!empty($store_key)) {
            return self::get_store_by_key($store_key);
        }

        return null;
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

        // Get store name for error responses
        $store_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));

        // 1) Check if already scanned TODAY (daily limit: 1 scan per day per store)
        $already_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
            WHERE user_id=%d AND store_id=%d
            AND DATE(created)=CURDATE()
            AND type='qr_scan'
        ", $user_id, $store_id));

        if ($already_today > 0) {
            // Log the existing scan details
            $existing_scan = $wpdb->get_row($wpdb->prepare("
                SELECT created, points FROM {$wpdb->prefix}ppv_points
                WHERE user_id=%d AND store_id=%d
                AND DATE(created)=CURDATE()
                AND type='qr_scan'
                ORDER BY created DESC LIMIT 1
            ", $user_id, $store_id));

            return [
                'limited' => true,
                'response' => new WP_REST_Response([
                    'success' => false,
                    'message' => self::t('err_already_scanned_today', '⚠️ Heute bereits gescannt'),
                    'store_name' => $store_name ?? 'PunktePass',
                    'error_type' => 'already_scanned_today'
                ], 429)
            ];
        }

        // 2) Check for duplicate scan (within 2 minutes - prevents retry spam)
        // FIXED: Check wp_ppv_points instead of wp_ppv_pos_log
        // Log table contains ALL attempts (successful and failed), causing false positives
        $recent = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}ppv_points
            WHERE user_id=%d AND store_id=%d
            AND created >= (NOW() - INTERVAL 2 MINUTE)
            AND type='qr_scan'
        ", $user_id, $store_id));

        if ($recent) {
            return [
                'limited' => true,
                'response' => new WP_REST_Response([
                    'success' => false,
                    'message' => self::t('err_duplicate_scan', '⚠️ Bereits gescannt. Bitte warten.'),
                    'store_name' => $store_name ?? 'PunktePass',
                    'error_type' => 'duplicate_scan'
                ], 429)
            ];
        }

        return ['limited' => false];
    }

    private static function insert_log($store_id, $user_id, $msg, $type = 'scan', $error_type = null) {
        global $wpdb;

        // Get IP address
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address)[0]);

        // Get user agent
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Prepare metadata (can be extended with additional info)
        $metadata_array = [
            'timestamp' => current_time('mysql'),
            'type' => $type
        ];

        // Add error_type to metadata if provided (for client-side translation)
        if ($error_type !== null) {
            $metadata_array['error_type'] = $error_type;
        }

        $metadata = json_encode($metadata_array);

        $wpdb->insert("{$wpdb->prefix}ppv_pos_log", [
            'store_id' => $store_id,
            'user_id' => $user_id,
            'message' => sanitize_text_field($msg),
            'type' => $type,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'metadata' => $metadata,
            'created_at' => current_time('mysql')
        ]);

        // ✅ Return the log ID for scan_id generation
        return $wpdb->insert_id;
    }

    private static function decode_user_from_qr($qr) {
        global $wpdb;
        if (empty($qr)) return false;

        // ✅ FIX: Look up user by TOKEN from ppv_tokens.entity_id
        // The QR may contain wrong user_id, but ppv_tokens.entity_id is source of truth

        if (strpos($qr, 'PPU') === 0) {
            $body = substr($qr, 3);

            // ✅ FIX: Token is ALWAYS 16 characters - take LAST 16 chars
            // This fixes the bug when token starts with digit (e.g. "5SEmtXSebxC0kwd3")
            // Old regex would incorrectly parse "PPU35SEmtXSebxC0kwd3" as user_id=35
            if (strlen($body) >= 16) {
                $token_from_qr = substr($body, -16); // Last 16 chars = token
                $uid_from_qr = intval(substr($body, 0, -16)); // Everything before = user_id (for logging only)

                // Look up entity_id directly from ppv_tokens by token
                $token_entity_id = $wpdb->get_var($wpdb->prepare("
                    SELECT entity_id
                    FROM {$wpdb->prefix}ppv_tokens
                    WHERE token=%s AND entity_type='user' AND expires_at > NOW()
                    LIMIT 1
                ", $token_from_qr));

                if ($token_entity_id) {
                    if ($uid_from_qr != $token_entity_id) {
                        ppv_log("⚠️ [PPV_QR] decode_user_from_qr: QR user_id mismatch! QR={$uid_from_qr}, token_entity_id={$token_entity_id} - using token_entity_id");
                    }
                    return intval($token_entity_id);
                }

                // Fallback to QR user_id if token not found (legacy support)
                ppv_log("⚠️ [PPV_QR] decode_user_from_qr: Token not found in DB, falling back to QR user_id={$uid_from_qr}");
                return $uid_from_qr;
            }
        }

        if (strpos($qr, 'PPUSER-') === 0) {
            $parts = explode('-', $qr);
            $uid_from_qr = intval($parts[1] ?? 0);
            $token_from_qr = $parts[2] ?? '';

            if (!empty($token_from_qr)) {
                // Look up actual user_id from ppv_users by qr_token
                $actual_user_id = $wpdb->get_var($wpdb->prepare("
                    SELECT id FROM {$wpdb->prefix}ppv_users
                    WHERE qr_token=%s AND active=1
                    LIMIT 1
                ", $token_from_qr));

                if ($actual_user_id) {
                    if ($uid_from_qr != $actual_user_id) {
                        ppv_log("⚠️ [PPV_QR] decode_user_from_qr (PPUSER): QR user_id mismatch! QR={$uid_from_qr}, actual={$actual_user_id} - using actual");
                    }
                    return intval($actual_user_id);
                }
            }

            return $uid_from_qr;
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

        // ⛔ PERMISSION CHECK: Only load camera scanner JS for handlers/scanners
        $is_handler = false;

        // Check session user type
        if (!empty($_SESSION['ppv_user_id'])) {
            $user_type = $_SESSION['ppv_user_type'] ?? '';

            // If not set in session, check database
            if (empty($user_type)) {
                $user_type = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_type FROM {$wpdb->prefix}ppv_users WHERE id=%d LIMIT 1",
                    $_SESSION['ppv_user_id']
                ));
            }

            $handler_types = ['store', 'handler', 'vendor', 'admin', 'scanner'];
            $is_handler = in_array(strtolower(trim($user_type)), $handler_types);
        }

        // 🏪 TRIAL HANDLER SUPPORT: Also check ppv_vendor_store_id
        if (!$is_handler && !empty($_SESSION['ppv_vendor_store_id'])) {
            $is_handler = true;
        }

        wp_enqueue_style(
            'remixicons',
            'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css',
            [],
            null
        );

        // Only enqueue camera scanner JS for handlers/scanners
        if ($is_handler) {
            // Load Ably JS library if configured
            if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
                wp_enqueue_script('ably-js', 'https://cdn.ably.com/lib/ably.min-1.js', [], '1.2', true);
                wp_enqueue_script('ppv-qr', PPV_PLUGIN_URL . 'assets/js/ppv-qr.js', ['jquery', 'ably-js'], time(), true);
            } else {
                wp_enqueue_script('ppv-qr', PPV_PLUGIN_URL . 'assets/js/ppv-qr.js', ['jquery'], time(), true);
            }

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

            // 1️⃣ FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
            if (!empty($_SESSION['ppv_current_filiale_id'])) {
                $store_id = intval($_SESSION['ppv_current_filiale_id']);
            }
            // 2️⃣ SESSION store_id
            elseif (!empty($_SESSION['ppv_store_id'])) {
                $store_id = intval($_SESSION['ppv_store_id']);
            }
            // 3️⃣ SESSION active_store
            elseif (!empty($_SESSION['ppv_active_store'])) {
                $store_id = intval($_SESSION['ppv_active_store']);
            }
            // 4️⃣ TRIAL HANDLER: ppv_vendor_store_id
            elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
                $store_id = intval($_SESSION['ppv_vendor_store_id']);
            }
            // 5️⃣ GLOBAL
            elseif (!empty($GLOBALS['ppv_active_store'])) {
                $active = $GLOBALS['ppv_active_store'];
                $store_id = is_object($active) ? intval($active->id) : intval($active);
            }
            // 6️⃣ LOGGED IN USER (fallback - may return wrong store if multiple!)
            elseif (is_user_logged_in()) {
                $uid = get_current_user_id();
                $store_id = intval($wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                    $uid
                )));
            }
            // 4️⃣ ADMIN FALLBACK
            else {
                if (current_user_can('administrator')) {
                    $row = $wpdb->get_row("SELECT id, store_key FROM {$wpdb->prefix}ppv_stores WHERE id=1 LIMIT 1");
                    if ($row) {
                        $store_id = $row->id;
                        $store_key = $row->store_key;
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

            // Build store data with optional Ably config
            $store_data = [
                'store_id' => intval($store_id),
                'store_key' => $store_key ?: '',
            ];

            // Add Ably config if enabled
            if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
                $store_data['ably'] = [
                    'key' => PPV_Ably::get_key(),
                ];
            }

            wp_localize_script('ppv-qr', 'PPV_STORE_DATA', $store_data);

            wp_enqueue_script('ppv-hidden-scan', PPV_PLUGIN_URL . 'assets/js/ppv-hidden-scan.js', [], time(), true);

            $lang = defined('PPV_LANG_ACTIVE') ? PPV_LANG_ACTIVE : (get_locale() ?? 'de');
            wp_localize_script('ppv-hidden-scan', 'PPV_SCAN_DATA', [
                'rest_url' => esc_url(rest_url('punktepass/v1/pos/scan')),
                'store_key' => $store_key ?: '',
                'plugin_url' => PPV_PLUGIN_URL,
                'lang' => substr($lang, 0, 2),
            ]);
        }
    }

    // ============================================================
    // 🎨 FRONTEND RENDERING
    // ============================================================
    public static function render_qr_center() {
        global $wpdb;

        // ⛔ PERMISSION CHECK: Only handlers and scanners can access
        if (!class_exists('PPV_Permissions')) {
            return '<div class="ppv-error" style="padding: 20px; text-align: center; color: #ff5252;">
                ❌ Zugriff verweigert. Nur für Händler und Scanner.
            </div>';
        }

        // 🔍 DEBUG: Log session before permission check
        ppv_log("🔍 [QR_CENTER] SESSION CHECK: " . json_encode([
            'ppv_user_id' => $_SESSION['ppv_user_id'] ?? 'NOT_SET',
            'ppv_user_type' => $_SESSION['ppv_user_type'] ?? 'NOT_SET',
            'ppv_store_id' => $_SESSION['ppv_store_id'] ?? 'NOT_SET',
            'ppv_vendor_store_id' => $_SESSION['ppv_vendor_store_id'] ?? 'NOT_SET',
            'ppv_current_filiale_id' => $_SESSION['ppv_current_filiale_id'] ?? 'NOT_SET',
        ]));

        $auth_check = PPV_Permissions::check_handler();
        if (is_wp_error($auth_check)) {
            ppv_log("❌ [QR_CENTER] check_handler() FAILED: " . $auth_check->get_error_message());
            return '<div class="ppv-error" style="padding: 20px; text-align: center; color: #ff5252;">
                ❌ Zugriff verweigert. Nur für Händler und Scanner.
            </div>';
        }
        ppv_log("✅ [QR_CENTER] check_handler() PASSED");

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
        <?php
        // ✅ Check if scanner user (only show scanner interface)
        $is_scanner = class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user();
        ?>

        <?php if ($is_scanner): ?>
            <!-- SCANNER USER: Only Scanner Interface -->
            <div class="ppv-pos-center glass-section">
                <?php self::render_pos_scanner(); ?>
            </div>
        <?php else: ?>
            <!-- HANDLER USER: Full Interface with Tabs -->
            <div class="ppv-qr-wrapper glass-card">
                <h2 style="font-size: 18px; margin-bottom: 16px;"><i class="ri-broadcast-line" style="margin-right: 6px;"></i><?php echo self::t('qrcamp_title', 'Kassenscanner & Kampagnen'); ?></h2>

                <div class="ppv-tabs">
                    <button class="ppv-tab active" data-tab="scanner" id="ppv-tab-scanner">
                        <i class="ri-qr-scan-2-line"></i> <?php echo self::t('tab_scanner', 'Kassenscanner'); ?>
                    </button>
                    <button class="ppv-tab" data-tab="vip" id="ppv-tab-vip">
                        <i class="ri-vip-crown-line"></i> <?php echo self::t('tab_vip', 'VIP Beállítások'); ?>
                    </button>
                    <button class="ppv-tab" data-tab="rewards" id="ppv-tab-rewards">
                        <i class="ri-gift-line"></i> <?php echo self::t('tab_rewards', 'Prämien'); ?>
                    </button>
                    <button class="ppv-tab" data-tab="campaigns" id="ppv-tab-campaigns">
                        <i class="ri-focus-3-line"></i> <?php echo self::t('tab_campaigns', 'Kampagnen'); ?>
                    </button>
                    <button class="ppv-tab" data-tab="scanner-users" id="ppv-tab-scanner-users">
                        <i class="ri-team-line"></i> <?php echo self::t('tab_scanner_users', 'Scanner Felhasználók'); ?>
                    </button>
                </div>

                <!-- TAB CONTENT: SCANNER -->
                <div class="ppv-tab-content active" id="tab-scanner">
                    <?php self::render_pos_scanner(); ?>
                </div>

                <!-- TAB CONTENT: VIP BEÁLLÍTÁSOK -->
                <div class="ppv-tab-content" id="tab-vip">
                    <?php echo do_shortcode('[ppv_vip_settings]'); ?>
                </div>

                <!-- TAB CONTENT: PRÄMIEN -->
                <div class="ppv-tab-content" id="tab-rewards">
                    <?php echo do_shortcode('[ppv_rewards_management]'); ?>
                </div>

                <!-- TAB CONTENT: KAMPAGNEN -->
                <div class="ppv-tab-content" id="tab-campaigns">
                    <?php self::render_campaigns(); ?>
                </div>

                <!-- TAB CONTENT: SCANNER FELHASZNÁLÓK -->
                <div class="ppv-tab-content" id="tab-scanner-users">
                    <?php self::render_scanner_users(); ?>
                </div>
            </div>
        <?php endif; ?>

        <script>
        jQuery(document).ready(function($){
            // Restore saved tab on page load
            var savedTab = localStorage.getItem('ppv_active_tab');
            if (savedTab && $(".ppv-tab[data-tab='" + savedTab + "']").length) {
                $(".ppv-tab").removeClass("active");
                $(".ppv-tab[data-tab='" + savedTab + "']").addClass("active");
                $(".ppv-tab-content").removeClass("active");
                $("#tab-" + savedTab).addClass("active");
            }

            // Save tab on click
            $(".ppv-tab").on("click", function(){
                var tabName = $(this).data("tab");
                localStorage.setItem('ppv_active_tab', tabName);
                $(".ppv-tab").removeClass("active");
                $(this).addClass("active");
                $(".ppv-tab-content").removeClass("active");
                $("#tab-" + tabName).addClass("active");
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
        $renewal_requested = false;

        // Try to get store ID from session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id = intval($_SESSION['ppv_current_filiale_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_active_store'])) {
            $store_id = intval($_SESSION['ppv_active_store']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            $store_id = intval($_SESSION['ppv_vendor_store_id']);
        }

        // ✅ If no store_id in session, try to get it via user_id (fallback)
        if ($store_id === 0 && !empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);

            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));

            if ($store_id) {
                $store_id = intval($store_id);
            } else {
            }
        }

        // 🐛 DEBUG: Log store_id

        // Fetch subscription info from database
        // 🏪 FILIALE SUPPORT: Use parent store's subscription for filialen
        if ($store_id > 0) {
            // First check if this is a filiale with a parent
            $parent_check = $wpdb->get_var($wpdb->prepare(
                "SELECT parent_store_id FROM {$wpdb->prefix}ppv_stores WHERE id = %d AND parent_store_id IS NOT NULL AND parent_store_id > 0 LIMIT 1",
                $store_id
            ));

            // Use parent store ID for subscription check if this is a filiale
            $subscription_store_id = $parent_check ? intval($parent_check) : $store_id;

            $store_data = $wpdb->get_row($wpdb->prepare(
                "SELECT trial_ends_at, subscription_status, subscription_expires_at, subscription_renewal_requested FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
                $subscription_store_id
            ));

            if ($store_data) {
                $subscription_status = $store_data->subscription_status ?? 'trial';
                $renewal_requested = !empty($store_data->subscription_renewal_requested);
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

        // ✅ Check if renewal button should be shown
        $show_renewal_button = !$renewal_requested && (
            ($subscription_status === 'trial' && $trial_days_left === 0) ||
            ($subscription_status === 'active' && $subscription_days_left === 0)
        );

        // ✅ Check if upgrade button should be shown (trial with 7 days or less)
        $show_upgrade_button = !$renewal_requested && ($subscription_status === 'trial' && $trial_days_left <= 7 && $trial_days_left > 0);

        // 🐛 DEBUG: Log button visibility

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

        // ✅ Check if scanner user (don't show subscription info to scanners)
        $is_scanner = class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user();

        if (!$is_scanner): ?>
        <div class="ppv-trial-info-block" style="margin-bottom: 15px; padding: 12px 16px; background: <?php echo $info_color; ?>; border-radius: 10px; border: 2px solid <?php echo $border_color; ?>;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;"><?php echo $info_icon; ?></span>
                    <div>
                        <div style="font-weight: bold; font-size: 15px; margin-bottom: 2px;">
                            <?php echo $info_message; ?>
                        </div>
                        <?php if ($renewal_requested): ?>
                            <div style="font-size: 12px; opacity: 0.9; color: #00e6ff;">
                                <?php echo self::t('renewal_in_progress', 'Aboverlängerung in Bearbeitung - Wir kontaktieren Sie bald per E-Mail oder Telefon'); ?>
                            </div>
                        <?php elseif ($show_description && $subscription_status === 'active'): ?>
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
                <?php if ($show_upgrade_button): ?>
                    <button id="ppv-request-renewal-btn" class="ppv-btn-outline" style="padding: 6px 12px; font-size: 13px; white-space: nowrap;">
                        📧 <?php echo self::t('upgrade_now', 'Jetzt upgraden'); ?>
                    </button>
                <?php elseif ($show_renewal_button): ?>
                    <button id="ppv-request-renewal-btn" class="ppv-btn-outline" style="padding: 6px 12px; font-size: 13px; white-space: nowrap;">
                        📧 <?php echo self::t('request_renewal', 'Abo verlängern'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($show_renewal_button || $show_upgrade_button): ?>
        <!-- Renewal Request Modal -->
        <div id="ppv-renewal-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: #1a1a2e; padding: 30px; border-radius: 15px; max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                <h3 style="margin-top: 0; color: #fff;">📧 <?php echo self::t('renewal_request_title', 'Abo Verlängerung anfragen'); ?></h3>
                <p style="color: #ccc; font-size: 14px;"><?php echo self::t('renewal_request_desc', 'Bitte geben Sie Ihre Telefonnummer ein. Wir kontaktieren Sie schnellstmöglich.'); ?></p>
                <input type="tel" id="ppv-renewal-phone" class="ppv-input" placeholder="<?php echo self::t('phone_placeholder', 'Telefonnummer'); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">
                <div id="ppv-renewal-error" style="display: none; color: #ff5252; font-size: 13px; margin-bottom: 10px;"></div>
                <div style="display: flex; gap: 10px;">
                    <button id="ppv-renewal-submit" class="ppv-btn" style="flex: 1; padding: 12px;">
                        ✅ <?php echo self::t('send_request', 'Anfrage senden'); ?>
                    </button>
                    <button id="ppv-renewal-cancel" class="ppv-btn-outline" style="flex: 1; padding: 12px;">
                        ❌ <?php echo self::t('cancel', 'Abbrechen'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; // End scanner check for subscription info ?>

        <?php self::render_filiale_switcher(); ?>

        <div class="ppv-pos-center glass-section">
            <div id="ppv-offline-banner" style="display:none;background:#ffcc00;padding:8px;border-radius:6px;margin-bottom:10px;">
                🛰️ <?php echo self::t('offline_banner', 'Offline-Modus aktiv'); ?>
                <button id="ppv-sync-btn" class="ppv-btn small" type="button">
                    <?php echo self::t('sync_now', 'Sync'); ?>
                </button>
            </div>

            <div class="ppv-pos-header">
                <h4 class="ppv-pos-title"><?php echo self::t('table_title', '📋 Letzte Scans'); ?></h4>

                <!-- 📥 CSV EXPORT DROPDOWN -->
                <div class="ppv-csv-wrapper">
                    <button id="ppv-csv-export-btn" class="ppv-btn ppv-csv-btn">
                        📥 <?php echo self::t('csv_export', 'CSV Export'); ?>
                    </button>
                    <div id="ppv-csv-export-menu" class="ppv-csv-dropdown">
                        <a href="#" class="ppv-csv-export-option" data-period="today">
                            📅 <?php echo self::t('csv_today', 'Heute'); ?>
                        </a>
                        <a href="#" class="ppv-csv-export-option" data-period="date">
                            📆 <?php echo self::t('csv_date', 'Datum wählen'); ?>
                        </a>
                        <a href="#" class="ppv-csv-export-option" data-period="month">
                            <i class="ri-file-chart-line"></i> <?php echo self::t('csv_month', 'Diesen Monat'); ?>
                        </a>
                    </div>
                </div>
            </div>

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
                        <h4 style="margin: 0;"><i class="ri-camera-line"></i> <?php echo self::t('camera_scanner_title', 'Kamera QR-Scanner'); ?></h4>
                        <button id="ppv-camera-close" class="ppv-btn-outline" type="button" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;">
                            ✕
                        </button>
                    </div>

                    <p style="color: #999; font-size: 14px; margin-bottom: 15px;">
                        <i class="ri-vidicon-line"></i> <?php echo self::t('camera_scanner_desc', 'Halte den QR-Code des Kunden vor die Kamera. Der Scanner erkennt diesen automatisch.'); ?>
                    </p>

                    <!-- Scanner Area -->
                    <div id="ppv-reader" style="width: 100%; max-width: 400px; height: 400px; margin: 0 auto 20px; border: 2px solid #00e6ff; border-radius: 14px; overflow: hidden; background: #000;"></div>

                    <!-- Result Box -->
                    <div id="ppv-scan-result" style="text-align: center; padding: 15px; background: rgba(0, 230, 255, 0.1); border-radius: 8px; border: 1px solid rgba(0, 230, 255, 0.3); margin-bottom: 15px; min-height: 40px; display: flex; align-items: center; justify-content: center; color: #00e6ff; font-weight: bold;">
                        <i class="ri-loader-4-line ri-spin"></i> Scanner aktiv...
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
    // 🏪 FILIALE SWITCHER
    // ============================================================
    private static function render_filiale_switcher() {
        global $wpdb;

        // ✅ SCANNER USERS: Don't show filiale switcher
        if (class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user()) {
            return;
        }

        // Get current store ID from session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 🏪 FILIALE SUPPORT: Check all possible store ID sources
        $current_filiale_id = 0;
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $current_filiale_id = intval($_SESSION['ppv_current_filiale_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $current_filiale_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            // Trial handlers may only have vendor_store_id set
            $current_filiale_id = intval($_SESSION['ppv_vendor_store_id']);
        }

        if (!$current_filiale_id) {
            ppv_log("⚠️ [PPV_QR] render_filiale_switcher: No store_id found in session");
            return; // No store in session
        }

        ppv_log("🏪 [PPV_QR] render_filiale_switcher: current_filiale_id={$current_filiale_id}");

        // Get parent store ID (if current is a filiale, get its parent; otherwise it's the parent)
        $parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $current_filiale_id
        ));

        if (!$parent_id) {
            return;
        }

        // Get all filialen belonging to this parent (including parent itself)
        $filialen = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, city, parent_store_id
            FROM {$wpdb->prefix}ppv_stores
            WHERE id=%d OR parent_store_id=%d
            ORDER BY CASE WHEN id=%d THEN 0 ELSE 1 END, name ASC
        ", $parent_id, $parent_id, $parent_id));

        // Always show switcher (even if only 1 location, to allow adding new)
        ?>
        <div class="ppv-filiale-switcher glass-section" style="margin-bottom: 20px; padding: 15px;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <label for="ppv-filiale-select" style="font-weight: 600; margin: 0;">
                    <i class="ri-store-2-line"></i> <?php echo self::t('current_filiale', 'Aktuelle Filiale'); ?>:
                </label>

                <select id="ppv-filiale-select" style="flex: 1; min-width: 200px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color, #e0e0e0); background: var(--bg-secondary, #fff); color: var(--text-primary, #333);">
                    <?php foreach ($filialen as $filiale): ?>
                        <option value="<?php echo esc_attr($filiale->id); ?>" <?php selected($filiale->id, $current_filiale_id); ?>>
                            <?php
                            echo esc_html($filiale->name);
                            // Show "Main Location" label for parent store
                            if ($filiale->id == $parent_id && (empty($filiale->parent_store_id) || $filiale->parent_store_id === null)) {
                                echo ' (' . self::t('main_location', 'Hauptstandort') . ')';
                            }
                            if (!empty($filiale->city)) {
                                echo ' - ' . esc_html($filiale->city);
                            }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button id="ppv-add-filiale-btn" class="ppv-btn" type="button" style="white-space: nowrap;">
                    <i class="ri-add-line"></i> <?php echo self::t('add_filiale', 'Filiale hinzufügen'); ?>
                </button>
            </div>

            <div id="ppv-filiale-message" style="margin-top: 15px; padding: 10px; border-radius: 8px; display: none;"></div>
        </div>

        <!-- Add Filiale Modal -->
        <div id="ppv-add-filiale-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: #1a1a2e; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                <h3 style="margin-top: 0; color: #fff;"><i class="ri-add-circle-line"></i> <?php echo self::t('add_new_filiale', 'Neue Filiale hinzufügen'); ?></h3>

                <label style="display: block; margin-bottom: 5px; color: #ccc; font-size: 14px;">
                    <?php echo self::t('filiale_name', 'Name der Filiale'); ?> <span style="color: #ff5252;">*</span>
                </label>
                <input type="text" id="ppv-new-filiale-name" class="ppv-input" placeholder="<?php echo esc_attr(self::t('filiale_name_placeholder', 'z.B. Wien Filiale 1')); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                <label style="display: block; margin-bottom: 5px; color: #ccc; font-size: 14px;">
                    <?php echo self::t('filiale_city', 'Stadt'); ?>
                </label>
                <input type="text" id="ppv-new-filiale-city" class="ppv-input" placeholder="<?php echo esc_attr(self::t('filiale_city_placeholder', 'z.B. Wien')); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                <label style="display: block; margin-bottom: 5px; color: #ccc; font-size: 14px;">
                    <?php echo self::t('filiale_plz', 'PLZ'); ?>
                </label>
                <input type="text" id="ppv-new-filiale-plz" class="ppv-input" placeholder="<?php echo esc_attr(self::t('filiale_plz_placeholder', 'z.B. 1010')); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                <div id="ppv-add-filiale-error" style="display: none; color: #ff5252; font-size: 13px; margin-bottom: 10px;"></div>

                <div style="display: flex; gap: 10px;">
                    <button id="ppv-save-filiale-btn" class="ppv-btn" style="flex: 1; padding: 12px;">
                        ✅ <?php echo self::t('save', 'Speichern'); ?>
                    </button>
                    <button id="ppv-cancel-filiale-btn" class="ppv-btn-outline" style="flex: 1; padding: 12px;">
                        ❌ <?php echo self::t('cancel', 'Abbrechen'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Contact Modal for Filiale Limit Reached -->
        <div id="ppv-filiale-contact-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: #1a1a2e; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                <h3 style="margin-top: 0; color: #fff;"><i class="ri-building-line"></i> <?php echo self::t('more_filialen_title', 'Mehr Filialen benötigt?'); ?></h3>

                <p style="color: #ccc; font-size: 14px; margin-bottom: 20px;">
                    <?php echo self::t('more_filialen_desc', 'Sie haben das Maximum an Filialen erreicht. Kontaktieren Sie uns, um weitere Filialen freizuschalten!'); ?>
                </p>

                <div id="ppv-filiale-limit-info" style="background: rgba(255,82,82,0.1); border: 1px solid rgba(255,82,82,0.3); border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                    <span style="color: #ff5252; font-size: 13px;"><i class="ri-information-line"></i> <span id="ppv-filiale-limit-text"></span></span>
                </div>

                <label style="display: block; margin-bottom: 5px; color: #ccc; font-size: 14px;">
                    <?php echo self::t('contact_email', 'E-Mail'); ?>
                </label>
                <input type="email" id="ppv-contact-email" class="ppv-input" placeholder="<?php echo esc_attr(self::t('contact_email_placeholder', 'Ihre E-Mail-Adresse')); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                <label style="display: block; margin-bottom: 5px; color: #ccc; font-size: 14px;">
                    <?php echo self::t('contact_phone', 'Telefon'); ?>
                </label>
                <input type="tel" id="ppv-contact-phone" class="ppv-input" placeholder="<?php echo esc_attr(self::t('contact_phone_placeholder', 'Ihre Telefonnummer')); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                <label style="display: block; margin-bottom: 5px; color: #ccc; font-size: 14px;">
                    <?php echo self::t('contact_message', 'Nachricht (optional)'); ?>
                </label>
                <textarea id="ppv-contact-message" class="ppv-input" placeholder="<?php echo esc_attr(self::t('contact_message_placeholder', 'Wie viele Filialen benötigen Sie?')); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px; min-height: 80px; resize: vertical;"></textarea>

                <div id="ppv-contact-error" style="display: none; color: #ff5252; font-size: 13px; margin-bottom: 10px;"></div>
                <div id="ppv-contact-success" style="display: none; color: #4caf50; font-size: 13px; margin-bottom: 10px;"></div>

                <div style="display: flex; gap: 10px;">
                    <button id="ppv-send-contact-btn" class="ppv-btn" style="flex: 1; padding: 12px;">
                        📧 <?php echo self::t('send_request', 'Anfrage senden'); ?>
                    </button>
                    <button id="ppv-cancel-contact-btn" class="ppv-btn-outline" style="flex: 1; padding: 12px;">
                        ❌ <?php echo self::t('cancel', 'Abbrechen'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            // Switch filiale on dropdown change
            $('#ppv-filiale-select').on('change', function(){
                const filialeId = $(this).val();
                const $select = $(this);
                const originalValue = $select.data('original-value') || $select.val();

                $select.prop('disabled', true);

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_switch_filiale',
                        filiale_id: filialeId,
                        nonce: '<?php echo wp_create_nonce('ppv_filiale_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Reload page to update all data
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php echo esc_js(self::t('switch_error', 'Fehler beim Wechseln')); ?>');
                            $select.val(originalValue);
                            $select.prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>');
                        $select.val(originalValue);
                        $select.prop('disabled', false);
                    }
                });
            });

            // Store original value for rollback
            $('#ppv-filiale-select').data('original-value', $('#ppv-filiale-select').val());

            // Show add filiale modal (check limit first)
            $('#ppv-add-filiale-btn').on('click', function(){
                const $btn = $(this);
                $btn.prop('disabled', true);

                // Check filiale limit first
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_check_filiale_limit',
                        parent_store_id: <?php echo intval($parent_id); ?>
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);

                        if (response.success && response.data.can_add) {
                            // Can add more - show add modal
                            $('#ppv-add-filiale-modal').fadeIn(200).css('display', 'flex');
                            $('#ppv-new-filiale-name').val('').focus();
                            $('#ppv-new-filiale-city').val('');
                            $('#ppv-new-filiale-plz').val('');
                            $('#ppv-add-filiale-error').hide();
                        } else {
                            // Limit reached - show contact modal
                            const current = response.data?.current || 1;
                            const max = response.data?.max || 1;
                            $('#ppv-filiale-limit-text').text('<?php echo esc_js(self::t('filiale_limit_info', 'Aktuell')); ?>: ' + current + ' / ' + max + ' <?php echo esc_js(self::t('filialen', 'Filialen')); ?>');
                            $('#ppv-filiale-contact-modal').fadeIn(200).css('display', 'flex');
                            $('#ppv-contact-email').val('').focus();
                            $('#ppv-contact-phone').val('');
                            $('#ppv-contact-message').val('');
                            $('#ppv-contact-error').hide();
                            $('#ppv-contact-success').hide();
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false);
                        // On error, just show the add modal (server will check limit again)
                        $('#ppv-add-filiale-modal').fadeIn(200).css('display', 'flex');
                        $('#ppv-new-filiale-name').val('').focus();
                    }
                });
            });

            // Hide add filiale modal
            $('#ppv-cancel-filiale-btn').on('click', function(){
                $('#ppv-add-filiale-modal').fadeOut(200);
            });

            // Hide contact modal
            $('#ppv-cancel-contact-btn').on('click', function(){
                $('#ppv-filiale-contact-modal').fadeOut(200);
            });

            // Send contact request
            $('#ppv-send-contact-btn').on('click', function(){
                const email = $('#ppv-contact-email').val().trim();
                const phone = $('#ppv-contact-phone').val().trim();
                const message = $('#ppv-contact-message').val().trim();
                const $btn = $(this);
                const $error = $('#ppv-contact-error');
                const $success = $('#ppv-contact-success');

                $error.hide();
                $success.hide();

                if (!email && !phone) {
                    $error.text('<?php echo esc_js(self::t('contact_required', 'Bitte geben Sie eine E-Mail oder Telefonnummer an')); ?>').show();
                    return;
                }

                $btn.prop('disabled', true).text('<?php echo esc_js(self::t('sending', 'Senden...')); ?>');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_request_more_filialen',
                        parent_store_id: <?php echo intval($parent_id); ?>,
                        contact_email: email,
                        contact_phone: phone,
                        message: message
                    },
                    success: function(response) {
                        if (response.success) {
                            $success.text(response.data?.msg || '<?php echo esc_js(self::t('request_sent', 'Anfrage erfolgreich gesendet!')); ?>').show();
                            $btn.text('✅ <?php echo esc_js(self::t('sent', 'Gesendet')); ?>');
                            setTimeout(function(){
                                $('#ppv-filiale-contact-modal').fadeOut(200);
                                $btn.prop('disabled', false).html('📧 <?php echo esc_js(self::t('send_request', 'Anfrage senden')); ?>');
                            }, 2000);
                        } else {
                            $error.text(response.data?.msg || '<?php echo esc_js(self::t('send_error', 'Fehler beim Senden')); ?>').show();
                            $btn.prop('disabled', false).html('📧 <?php echo esc_js(self::t('send_request', 'Anfrage senden')); ?>');
                        }
                    },
                    error: function() {
                        $error.text('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>').show();
                        $btn.prop('disabled', false).html('📧 <?php echo esc_js(self::t('send_request', 'Anfrage senden')); ?>');
                    }
                });
            });

            // Save new filiale
            $('#ppv-save-filiale-btn').on('click', function(){
                const name = $('#ppv-new-filiale-name').val().trim();
                const city = $('#ppv-new-filiale-city').val().trim();
                const plz = $('#ppv-new-filiale-plz').val().trim();
                const $btn = $(this);
                const $error = $('#ppv-add-filiale-error');

                $error.hide();

                if (!name) {
                    $error.text('<?php echo esc_js(self::t('name_required', 'Name ist erforderlich')); ?>').show();
                    return;
                }

                $btn.prop('disabled', true).text('<?php echo esc_js(self::t('saving', 'Speichern...')); ?>');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_create_filiale',
                        parent_store_id: <?php echo intval($parent_id); ?>,
                        filiale_name: name,
                        city: city,
                        plz: plz,
                        nonce: '<?php echo wp_create_nonce('ppv_filiale_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            // Check if limit was reached
                            if (response.data?.limit_reached) {
                                // Close add modal and show contact modal
                                $('#ppv-add-filiale-modal').fadeOut(200);
                                const current = response.data?.current || 1;
                                const max = response.data?.max || 1;
                                $('#ppv-filiale-limit-text').text('<?php echo esc_js(self::t('filiale_limit_info', 'Aktuell')); ?>: ' + current + ' / ' + max + ' <?php echo esc_js(self::t('filialen', 'Filialen')); ?>');
                                setTimeout(function(){
                                    $('#ppv-filiale-contact-modal').fadeIn(200).css('display', 'flex');
                                    $('#ppv-contact-email').val('').focus();
                                }, 200);
                                $btn.prop('disabled', false).html('✅ <?php echo esc_js(self::t('save', 'Speichern')); ?>');
                            } else {
                                $error.text(response.data?.msg || '<?php echo esc_js(self::t('save_error', 'Fehler beim Speichern')); ?>').show();
                                $btn.prop('disabled', false).html('✅ <?php echo esc_js(self::t('save', 'Speichern')); ?>');
                            }
                        }
                    },
                    error: function() {
                        $error.text('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>').show();
                        $btn.prop('disabled', false).html('✅ <?php echo esc_js(self::t('save', 'Speichern')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    // ============================================================
    // 🎯 KAMPAGNEN - KOMPLETT FORMA
    // ============================================================
    public static function render_campaigns() {
        ?>
        <div class="ppv-campaigns glass-section">
            <div class="ppv-campaign-header">
                <h3><i class="ri-focus-3-line"></i> <?php echo self::t('campaigns_title', 'Kampagnen'); ?></h3>
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
                        <label style="color: #ff9800;"><i class="ri-money-euro-circle-line"></i> <?php echo self::t('label_free_product_value', 'Termék értéke'); ?> <span style="color: #ff0000;">*</span></label>
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
    // 👥 RENDER SCANNER USERS MANAGEMENT
    // ============================================================
    public static function render_scanner_users() {
        global $wpdb;

        // Get current handler's store_id (BASE store, not filiale)
        $store_id = 0;
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            $store_id = intval($_SESSION['ppv_vendor_store_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));
        }

        // 🏪 Get parent store ID to fetch all filialen
        $parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));

        // Get all filialen belonging to this parent (including parent itself)
        $filialen = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, city, parent_store_id
            FROM {$wpdb->prefix}ppv_stores
            WHERE id=%d OR parent_store_id=%d
            ORDER BY CASE WHEN id=%d THEN 0 ELSE 1 END, name ASC
        ", $parent_id, $parent_id, $parent_id));

        // Get all scanner users for this handler (BASE store + all filialen)
        $scanners = [];
        if ($store_id) {
            $scanners = $wpdb->get_results($wpdb->prepare(
                "SELECT u.id, u.email, u.created_at, u.active, u.vendor_store_id,
                        s.name as store_name, s.city as store_city, s.parent_store_id
                 FROM {$wpdb->prefix}ppv_users u
                 LEFT JOIN {$wpdb->prefix}ppv_stores s ON u.vendor_store_id = s.id
                 WHERE u.user_type = 'scanner'
                 AND (u.vendor_store_id = %d OR s.parent_store_id = %d)
                 ORDER BY u.created_at DESC",
                $parent_id, $parent_id
            ));
        }

        ?>
        <div class="ppv-scanner-users glass-section">
            <div class="ppv-scanner-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3><i class="ri-team-line"></i> <?php echo self::t('scanner_users_title', 'Scanner Felhasználók'); ?></h3>
                <button id="ppv-new-scanner-btn" class="ppv-btn neon" type="button">
                    <i class="ri-add-line"></i> <?php echo self::t('add_scanner_user', 'Új Scanner Létrehozása'); ?>
                </button>
            </div>

            <?php if (empty($scanners)): ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <p>📭 <?php echo self::t('no_scanner_users', 'Még nincs scanner felhasználó létrehozva.'); ?></p>
                </div>
            <?php else: ?>
                <div class="scanner-users-list">
                    <?php foreach ($scanners as $scanner): ?>
                        <?php
                        $is_active = $scanner->active == 1; // PPV users: 1 = active, 0 = disabled
                        $created_date = date('Y-m-d H:i', strtotime($scanner->created_at));
                        ?>
                        <div class="scanner-user-card glass-card" style="padding: 15px; margin-bottom: 15px; border-left: 4px solid <?php echo $is_active ? '#4caf50' : '#999'; ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">
                                        📧 <?php echo esc_html($scanner->email); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #999;">
                                        <?php echo self::t('created_at', 'Létrehozva'); ?>: <?php echo $created_date; ?>
                                    </div>
                                    <div style="font-size: 12px; color: #00e6ff; margin-top: 3px;">
                                        🏪 <?php
                                        echo esc_html($scanner->store_name ?: 'N/A');
                                        if (!empty($scanner->store_city)) {
                                            echo ' - ' . esc_html($scanner->store_city);
                                        }
                                        // Show if it's main location
                                        if ($scanner->vendor_store_id == $parent_id && (empty($scanner->parent_store_id) || $scanner->parent_store_id === null)) {
                                            echo ' (' . self::t('main_location', 'Hauptstandort') . ')';
                                        }
                                        ?>
                                    </div>
                                    <div style="margin-top: 5px;">
                                        <?php if ($is_active): ?>
                                            <span style="background: #4caf50; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px;">
                                                ✅ <?php echo self::t('status_active', 'Aktív'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #999; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px;">
                                                🚫 <?php echo self::t('status_disabled', 'Letiltva'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <!-- Change Filiale -->
                                    <button class="ppv-scanner-change-filiale ppv-btn-outline" data-user-id="<?php echo $scanner->id; ?>" data-email="<?php echo esc_attr($scanner->email); ?>" data-current-store="<?php echo $scanner->vendor_store_id; ?>" style="padding: 8px 12px; font-size: 13px;">
                                        <i class="ri-store-2-line"></i> <?php echo self::t('change_filiale', 'Filiale ändern'); ?>
                                    </button>

                                    <!-- Password Reset -->
                                    <button class="ppv-scanner-reset-pw ppv-btn-outline" data-user-id="<?php echo $scanner->id; ?>" data-email="<?php echo esc_attr($scanner->email); ?>" style="padding: 8px 12px; font-size: 13px;">
                                        <i class="ri-refresh-line"></i> <?php echo self::t('reset_password', 'Jelszó Reset'); ?>
                                    </button>

                                    <!-- Toggle Active/Disable -->
                                    <?php if ($is_active): ?>
                                        <button class="ppv-scanner-toggle ppv-btn-outline" data-user-id="<?php echo $scanner->id; ?>" data-action="disable" style="padding: 8px 12px; font-size: 13px; background: #f44336; color: white; border: none;">
                                            🚫 <?php echo self::t('disable', 'Letiltás'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button class="ppv-scanner-toggle ppv-btn" data-user-id="<?php echo $scanner->id; ?>" data-action="enable" style="padding: 8px 12px; font-size: 13px;">
                                            ✅ <?php echo self::t('enable', 'Engedélyezés'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Create Scanner Modal -->
            <div id="ppv-scanner-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
                <div style="background: #1a1a2e; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                    <h3 style="margin-top: 0; color: #fff;"><i class="ri-user-add-line"></i> <?php echo self::t('create_scanner_title', 'Új Scanner Létrehozása'); ?></h3>

                    <label style="color: #fff; font-size: 13px; display: block; margin-bottom: 5px;">
                        <?php echo self::t('scanner_email', 'E-mail cím'); ?> <span style="color: #ff5252;">*</span>
                    </label>
                    <input type="email" id="ppv-scanner-email" class="ppv-input" placeholder="scanner@example.com" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                    <label style="color: #fff; font-size: 13px; display: block; margin-bottom: 5px;">
                        <?php echo self::t('scanner_password', 'Jelszó'); ?> <span style="color: #ff5252;">*</span>
                    </label>
                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <input type="text" id="ppv-scanner-password" class="ppv-input" placeholder="<?php echo self::t('enter_password', 'Adjon meg jelszót'); ?>" style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff;">
                        <button id="ppv-scanner-gen-pw" class="ppv-btn-outline" style="padding: 12px; white-space: nowrap;">
                            🎲 <?php echo self::t('generate', 'Generálás'); ?>
                        </button>
                    </div>

                    <label style="color: #fff; font-size: 13px; display: block; margin-bottom: 5px;">
                        <i class="ri-store-2-line"></i> <?php echo self::t('scanner_filiale', 'Filiale zuweisen'); ?> <span style="color: #ff5252;">*</span>
                    </label>
                    <select id="ppv-scanner-filiale" class="ppv-input" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">
                        <?php foreach ($filialen as $filiale): ?>
                            <option value="<?php echo $filiale->id; ?>">
                                <?php
                                echo esc_html($filiale->name);
                                if (!empty($filiale->city)) {
                                    echo ' - ' . esc_html($filiale->city);
                                }
                                // Show if it's main location
                                if ($filiale->id == $parent_id && (empty($filiale->parent_store_id) || $filiale->parent_store_id === null)) {
                                    echo ' (' . self::t('main_location', 'Hauptstandort') . ')';
                                }
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="ppv-scanner-error" style="display: none; color: #ff5252; font-size: 13px; margin-bottom: 10px; padding: 10px; background: rgba(255, 82, 82, 0.1); border-radius: 6px;"></div>
                    <div id="ppv-scanner-success" style="display: none; color: #4caf50; font-size: 13px; margin-bottom: 10px; padding: 10px; background: rgba(76, 175, 80, 0.1); border-radius: 6px;"></div>

                    <div style="display: flex; gap: 10px;">
                        <button id="ppv-scanner-create" class="ppv-btn" style="flex: 1; padding: 12px;">
                            ✅ <?php echo self::t('create', 'Létrehozás'); ?>
                        </button>
                        <button id="ppv-scanner-cancel" class="ppv-btn-outline" style="flex: 1; padding: 12px;">
                            ❌ <?php echo self::t('cancel', 'Mégse'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Change Filiale Modal -->
            <div id="ppv-change-filiale-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
                <div style="background: #1a1a2e; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                    <h3 style="margin-top: 0; color: #fff;"><i class="ri-store-2-line"></i> <?php echo self::t('change_filiale_title', 'Filiale ändern'); ?></h3>

                    <p style="color: #999; font-size: 14px; margin-bottom: 15px;">
                        <strong style="color: #fff;">📧 <span id="ppv-change-filiale-email"></span></strong>
                    </p>

                    <label style="color: #fff; font-size: 13px; display: block; margin-bottom: 5px;">
                        <?php echo self::t('select_new_filiale', 'Neue Filiale auswählen'); ?> <span style="color: #ff5252;">*</span>
                    </label>
                    <select id="ppv-change-filiale-select" class="ppv-input" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">
                        <?php foreach ($filialen as $filiale): ?>
                            <option value="<?php echo $filiale->id; ?>">
                                <?php
                                echo esc_html($filiale->name);
                                if (!empty($filiale->city)) {
                                    echo ' - ' . esc_html($filiale->city);
                                }
                                // Show if it's main location
                                if ($filiale->id == $parent_id && (empty($filiale->parent_store_id) || $filiale->parent_store_id === null)) {
                                    echo ' (' . self::t('main_location', 'Hauptstandort') . ')';
                                }
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="hidden" id="ppv-change-filiale-user-id" value="">

                    <div id="ppv-change-filiale-error" style="display: none; color: #ff5252; font-size: 13px; margin-bottom: 10px; padding: 10px; background: rgba(255, 82, 82, 0.1); border-radius: 6px;"></div>
                    <div id="ppv-change-filiale-success" style="display: none; color: #4caf50; font-size: 13px; margin-bottom: 10px; padding: 10px; background: rgba(76, 175, 80, 0.1); border-radius: 6px;"></div>

                    <div style="display: flex; gap: 10px;">
                        <button id="ppv-change-filiale-save" class="ppv-btn" style="flex: 1; padding: 12px;">
                            ✅ <?php echo self::t('save', 'Speichern'); ?>
                        </button>
                        <button id="ppv-change-filiale-cancel" class="ppv-btn-outline" style="flex: 1; padding: 12px;">
                            ❌ <?php echo self::t('cancel', 'Abbrechen'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // ============================================================
    // 🏪 AJAX: SWITCH FILIALE
    // ============================================================
    public static function ajax_switch_filiale() {
        check_ajax_referer('ppv_filiale_nonce', 'nonce');

        $filiale_id = intval($_POST['filiale_id'] ?? 0);

        if (!$filiale_id) {
            wp_send_json_error(['message' => 'Fehlende Filiale ID']);
        }

        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Update session with new filiale ID
        $_SESSION['ppv_current_filiale_id'] = $filiale_id;

        // Optional: Log the switch
        ppv_log("✅ [PPV_QR] Filiale switched to ID: {$filiale_id}");

        wp_send_json_success(['message' => 'Filiale gewechselt']);
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

        // ✅ CSV Export endpoint
        register_rest_route('punktepass/v1', '/pos/export-csv', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_export_csv'],
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
        $campaign_id = intval($data['campaign_id'] ?? 0);

        // GPS data from scanner (optional)
        $scan_lat = isset($data['latitude']) ? floatval($data['latitude']) : null;
        $scan_lng = isset($data['longitude']) ? floatval($data['longitude']) : null;

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

        // 🏪 FILIALE SUPPORT: Use session-aware store ID for points
        $session_store = self::get_session_aware_store_id($r);
        if ($session_store && isset($session_store->id)) {
            $store_id = intval($session_store->id);
        } else {
            $store_id = intval($store->id); // Fallback to validated store
        }

        // 🔍 DEBUG: Log store_id resolution
        ppv_log("🔍 [PPV_QR rest_process_scan] Store ID resolution: " . json_encode([
            'session_store_object' => $session_store ? 'EXISTS' : 'NULL',
            'session_store_id' => $session_store->id ?? 'NULL',
            'validated_store_id' => $store->id ?? 'NULL',
            'final_store_id' => $store_id,
        ]));

        if ($store_id === 0) {
            ppv_log("❌ [PPV_QR] CRITICAL: store_id is 0! This should not happen!");
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ Invalid store_id (0)'
            ], 400);
        }

        $user_id = self::decode_user_from_qr($qr_code);
        if (!$user_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_invalid_qr', '❌ Érvénytelen QR'),
                'store_name' => $store->name ?? 'PunktePass',
                'error_type' => 'invalid_qr'
            ], 400);
        }

        $rate_check = self::check_rate_limit($user_id, $store_id);
        if ($rate_check['limited']) {
            // Log the rate limit error with error_type for client-side translation
            $response_data = $rate_check['response']->get_data();
            $error_type = $response_data['error_type'] ?? null;
            self::insert_log($store_id, $user_id, $response_data['message'] ?? '⚠️ Rate limit', 'error', $error_type);

            // Get user info for error response
            $user_info = $wpdb->get_row($wpdb->prepare("
                SELECT first_name, last_name, email, avatar
                FROM {$wpdb->prefix}ppv_users WHERE id = %d
            ", $user_id));
            $customer_name = trim(($user_info->first_name ?? '') . ' ' . ($user_info->last_name ?? ''));
            $store_name = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
                $store_id
            ));

            // ✅ Generate unique scan_id for error deduplication
            $error_scan_id = "err-{$store_id}-{$user_id}-" . time();

            // 📡 ABLY: Notify BOTH user AND store (POS) about the error
            if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
                // Notify user dashboard
                PPV_Ably::trigger_user_points($user_id, [
                    'success' => false,
                    'error_type' => $error_type,
                    'message' => $response_data['message'] ?? '⚠️ Rate limit',
                    'store_name' => $store_name ?? 'PunktePass',
                ]);

                // ✅ FIX: Also notify POS (store channel) so error appears in scan list
                PPV_Ably::trigger_scan($store_id, [
                    'scan_id' => $error_scan_id, // ✅ Include scan_id for deduplication
                    'user_id' => $user_id,
                    'customer_name' => $customer_name ?: null,
                    'email' => $user_info->email ?? null,
                    'avatar' => $user_info->avatar ?? null,
                    'message' => $response_data['message'] ?? '⚠️ Rate limit',
                    'points' => '0',
                    'date_short' => date('d.m.'),
                    'time_short' => date('H:i'),
                    'success' => false,
                    'error_type' => $error_type,
                ]);
            }

            // ✅ Include user info in HTTP response for immediate UI display
            $rate_check['response']->set_data(array_merge($response_data, [
                'scan_id' => $error_scan_id, // ✅ Include scan_id for deduplication
                'user_id' => $user_id,
                'customer_name' => $customer_name ?: null,
                'email' => $user_info->email ?? null,
                'avatar' => $user_info->avatar ?? null,
            ]));

            return $rate_check['response'];
        }

        // ═══════════════════════════════════════════════════════════
        // GPS DISTANCE CHECK (Fraud Detection) - LOG ONLY, DON'T BLOCK
        // Scan always goes through, suspicious cases logged for admin review
        // ═══════════════════════════════════════════════════════════
        if (class_exists('PPV_Scan_Monitoring') && ($scan_lat || $scan_lng)) {
            $gps_check = PPV_Scan_Monitoring::validate_scan_location($store_id, $scan_lat, $scan_lng);

            if (!$gps_check['valid']) {
                // Log suspicious scan for admin review - but allow scan to continue
                PPV_Scan_Monitoring::log_suspicious_scan($store_id, $user_id, $scan_lat, $scan_lng, $gps_check);

                $reason = $gps_check['reason'] ?? 'gps_distance';

                if ($reason === 'wrong_country') {
                    ppv_log("[PPV_QR] ⚠️ SUSPICIOUS: Country mismatch (SCAN ALLOWED): user={$user_id}, store={$store_id}, store_country={$gps_check['store_country']}, scan_country={$gps_check['scan_country']}");
                } else {
                    ppv_log("[PPV_QR] ⚠️ SUSPICIOUS: GPS distance exceeded (SCAN ALLOWED): user={$user_id}, store={$store_id}, distance={$gps_check['distance']}m");
                }
                // Scan continues - admin can review suspicious scans in WP admin
            }
        }

        // ═══════════════════════════════════════════════════════════
        // BASE POINTS: Campaign OR Reward (Prämien) points_given
        // ═══════════════════════════════════════════════════════════
        $points_add = 0;
        $points_source = 'none';

        // 1. Check for campaign points FIRST (if campaign_id provided)
        if ($campaign_id > 0) {
            $campaign_points = $wpdb->get_var($wpdb->prepare("
                SELECT points FROM {$wpdb->prefix}ppv_campaigns
                WHERE id=%d AND store_id=%d AND status='active'
            ", $campaign_id, $store_id));

            if ($campaign_points) {
                $points_add = intval($campaign_points);
                $points_source = 'campaign';
                ppv_log("🎯 [PPV_QR] Campaign points applied: campaign_id={$campaign_id}, points={$points_add}");
            }
        }

        // 2. If no campaign, use Prämien (reward) points_given as base
        if ($points_add === 0) {
            // 🏪 FILIALE FIX: Get rewards from PARENT store if this is a filiale
            $reward_store_id = $store_id;
            if (class_exists('PPV_Filiale')) {
                $reward_store_id = PPV_Filiale::get_parent_id($store_id);
                if ($reward_store_id !== $store_id) {
                    ppv_log("🏪 [PPV_QR] Reward lookup: Using PARENT store {$reward_store_id} instead of filiale {$store_id}");
                }
            }

            $reward_points = $wpdb->get_var($wpdb->prepare("
                SELECT points_given FROM {$wpdb->prefix}ppv_rewards
                WHERE store_id=%d AND points_given > 0
                ORDER BY id ASC LIMIT 1
            ", $reward_store_id));

            if ($reward_points && intval($reward_points) > 0) {
                $points_add = intval($reward_points);
                $points_source = 'reward';
                ppv_log("🎁 [PPV_QR] Reward base points applied: reward_store_id={$reward_store_id}, points_given={$points_add}");
            }
        }

        // 3. If neither exists, notify merchant to configure
        if ($points_add === 0) {
            ppv_log("⚠️ [PPV_QR] No points configured: store_id={$store_id}, campaign_id={$campaign_id}");
            return new WP_REST_Response([
                'success' => false,
                'message' => '⚠️ Keine Punkte konfiguriert. Bitte Prämie oder Kampagne einrichten.',
                'store_name' => $store->name ?? 'PunktePass',
                'error_type' => 'no_points_configured'
            ], 400);
        }

        // Check for bonus day (multiplies base/campaign points)
        $bonus = $wpdb->get_row($wpdb->prepare("
            SELECT multiplier, extra_points FROM {$wpdb->prefix}ppv_bonus_days
            WHERE store_id=%d AND date=%s AND active=1
        ", $store_id, date('Y-m-d')));

        if ($bonus) {
            $points_add = (int)round(($points_add * (float)$bonus->multiplier) + (int)$bonus->extra_points);
        }

        // ═══════════════════════════════════════════════════════════
        // VIP LEVEL BONUSES (Extended: 4 types)
        // ═══════════════════════════════════════════════════════════
        $vip_bonus_details = [
            'pct' => 0,      // 1. Percentage bonus
            'fix' => 0,      // 2. Fixed point bonus
            'streak' => 0,   // 3. Every Xth scan bonus
            'daily' => 0,    // 4. First daily scan bonus
        ];
        $vip_bonus_applied = 0;

        if (class_exists('PPV_User_Level')) {
            // 🏪 FILIALE FIX: Get VIP settings from PARENT store if this is a filiale
            $vip_store_id = $store_id;
            if (class_exists('PPV_Filiale')) {
                $vip_store_id = PPV_Filiale::get_parent_id($store_id);
                if ($vip_store_id !== $store_id) {
                    ppv_log("🏪 [PPV_QR] VIP settings: Using PARENT store {$vip_store_id} instead of filiale {$store_id}");
                }
            }

            // Get all VIP settings for this store (or parent store)
            $vip_settings = $wpdb->get_row($wpdb->prepare("
                SELECT
                    vip_fix_enabled, vip_fix_bronze, vip_fix_silver, vip_fix_gold, vip_fix_platinum,
                    vip_streak_enabled, vip_streak_count, vip_streak_type,
                    vip_streak_bronze, vip_streak_silver, vip_streak_gold, vip_streak_platinum,
                    vip_daily_enabled, vip_daily_bronze, vip_daily_silver, vip_daily_gold, vip_daily_platinum
                FROM {$wpdb->prefix}ppv_stores WHERE id = %d
            ", $vip_store_id));

            // 🔍 DEBUG: Log VIP settings
            ppv_log("🔍 [PPV_QR] VIP settings for store {$vip_store_id}: " . json_encode([
                'vip_fix_enabled' => $vip_settings->vip_fix_enabled ?? 'NULL',
                'vip_fix_bronze' => $vip_settings->vip_fix_bronze ?? 'NULL',
                'vip_daily_enabled' => $vip_settings->vip_daily_enabled ?? 'NULL',
            ]));

            if ($vip_settings) {
                // Check if user has VIP status (Bronze or higher = 100+ lifetime points)
                $user_level = PPV_User_Level::get_vip_level_for_bonus($user_id);
                $base_points = $points_add;

                // 🔍 DEBUG: Log user level
                ppv_log("🔍 [PPV_QR] User VIP level: user_id={$user_id}, level=" . ($user_level ?? 'NULL (Starter - no VIP)'));

                // Helper to get level-specific value (returns 0 for Starter/null)
                $getLevelValue = function($bronze, $silver, $gold, $platinum) use ($user_level) {
                    if ($user_level === null) return 0;
                    switch ($user_level) {
                        case 'bronze': return intval($bronze);
                        case 'silver': return intval($silver);
                        case 'gold': return intval($gold);
                        case 'platinum': return intval($platinum);
                        default: return 0;
                    }
                };

                // 1. FIXED POINT BONUS
                if ($vip_settings->vip_fix_enabled && $user_level !== null) {
                    $fix_bonus = $getLevelValue(
                        $vip_settings->vip_fix_bronze ?? 1,
                        $vip_settings->vip_fix_silver,
                        $vip_settings->vip_fix_gold,
                        $vip_settings->vip_fix_platinum
                    );
                    if ($fix_bonus > 0) {
                        $vip_bonus_details['fix'] = $fix_bonus;
                    }
                }

                // 3. EVERY Xth SCAN BONUS (Streak)
                if ($vip_settings->vip_streak_enabled && $user_level !== null) {
                    $streak_count = intval($vip_settings->vip_streak_count);
                    if ($streak_count > 0) {
                        $user_scan_count = (int)$wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
                            WHERE user_id = %d AND store_id = %d AND type = 'qr_scan'
                        ", $user_id, $store_id));

                        $next_scan_number = $user_scan_count + 1;
                        if ($next_scan_number % $streak_count === 0) {
                            $streak_type = $vip_settings->vip_streak_type ?? 'fixed';

                            if ($streak_type === 'fixed') {
                                $vip_bonus_details['streak'] = $getLevelValue(
                                    $vip_settings->vip_streak_bronze ?? 1,
                                    $vip_settings->vip_streak_silver,
                                    $vip_settings->vip_streak_gold,
                                    $vip_settings->vip_streak_platinum
                                );
                            } elseif ($streak_type === 'double') {
                                $vip_bonus_details['streak'] = $base_points;
                            } elseif ($streak_type === 'triple') {
                                $vip_bonus_details['streak'] = $base_points * 2;
                            }

                            ppv_log("🔥 [PPV_QR] Streak bonus triggered! Scan #{$next_scan_number} (every {$streak_count})");
                        }
                    }
                }

                // 4. FIRST DAILY SCAN BONUS
                if ($vip_settings->vip_daily_enabled && $user_level !== null) {
                    $today = date('Y-m-d');
                    $already_scanned_today = (int)$wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
                        WHERE user_id = %d AND store_id = %d AND type = 'qr_scan'
                        AND DATE(created) = %s
                    ", $user_id, $store_id, $today));

                    if ($already_scanned_today === 0) {
                        $vip_bonus_details['daily'] = $getLevelValue(
                            $vip_settings->vip_daily_bronze ?? 5,
                            $vip_settings->vip_daily_silver,
                            $vip_settings->vip_daily_gold,
                            $vip_settings->vip_daily_platinum
                        );
                        ppv_log("☀️ [PPV_QR] First daily scan bonus applied for user {$user_id}");
                    }
                }

                // Calculate total VIP bonus
                $vip_bonus_applied = $vip_bonus_details['pct'] + $vip_bonus_details['fix'] + $vip_bonus_details['streak'] + $vip_bonus_details['daily'];

                if ($vip_bonus_applied > 0) {
                    $points_add += $vip_bonus_applied;
                    ppv_log("✅ [PPV_QR] VIP bonuses applied: level={$user_level}, pct=+{$vip_bonus_details['pct']}, fix=+{$vip_bonus_details['fix']}, streak=+{$vip_bonus_details['streak']}, daily=+{$vip_bonus_details['daily']}, total_bonus={$vip_bonus_applied}, total_points={$points_add}");
                }
            }
        }

        // ═══════════════════════════════════════════════════════════
        // INSERT POINTS
        // ═══════════════════════════════════════════════════════════
        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id' => $user_id,
            'store_id' => $store_id,
            'points' => $points_add,
            'campaign_id' => $campaign_id ?: null,
            'type' => 'qr_scan',
            'created' => current_time('mysql')
        ]);

        // Update lifetime_points for VIP level calculation
        if (class_exists('PPV_User_Level')) {
            PPV_User_Level::add_lifetime_points($user_id, $points_add);
        }

        // Build log message
        $log_msg = $vip_bonus_applied > 0
            ? "+{$points_add} " . self::t('points', 'Punkte') . " (VIP: +{$vip_bonus_applied})"
            : "+{$points_add} " . self::t('points', 'Punkte');
        $log_id = self::insert_log($store_id, $user_id, $log_msg, 'qr_scan');

        // ✅ Generate unique scan_id for deduplication
        $scan_id = "scan-{$store_id}-{$user_id}-{$log_id}";

        // Get store name for response
        $store_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));

        // ✅ Get user info for response AND Ably notification
        $user_info = $wpdb->get_row($wpdb->prepare("
            SELECT first_name, last_name, email, avatar
            FROM {$wpdb->prefix}ppv_users WHERE id = %d
        ", $user_id));
        $customer_name = trim(($user_info->first_name ?? '') . ' ' . ($user_info->last_name ?? ''));

        // 📡 ABLY: Send real-time notification (non-blocking)
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            PPV_Ably::trigger_scan($store_id, [
                'scan_id' => $scan_id, // ✅ Include scan_id for deduplication
                'user_id' => $user_id,
                'customer_name' => $customer_name ?: null,
                'email' => $user_info->email ?? null,
                'avatar' => $user_info->avatar ?? null,
                'message' => $log_msg,
                'points' => (string)$points_add,
                'vip_bonus' => $vip_bonus_applied,
                'date_short' => date('d.m.'),
                'time_short' => date('H:i'),
                'success' => true,
            ]);

            // 📡 ABLY: Also notify user's dashboard of points update
            // ✅ FIX: Query from ppv_points table (not ppv_qr_scans!)
            $total_points = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}ppv_points WHERE user_id = %d",
                $user_id
            ));
            $total_rewards = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_reward_log WHERE user_id = %d",
                $user_id
            ));

            PPV_Ably::trigger_user_points($user_id, [
                'points_added' => $points_add,
                'total_points' => $total_points,
                'total_rewards' => $total_rewards,
                'store_name' => $store_name ?? 'PunktePass',
                'vip_bonus' => $vip_bonus_applied,
                'success' => true,
            ]);
        }

        // Build response message with VIP info
        $vip_suffix = $vip_bonus_applied > 0 ? " (VIP-Bonus: +{$vip_bonus_applied})" : '';
        $success_msg = "✅ +{$points_add} " . self::t('points', 'Punkte') . $vip_suffix;

        return new WP_REST_Response([
            'success' => true,
            'scan_id' => $scan_id, // ✅ Include scan_id for deduplication
            'message' => $success_msg,
            'user_id' => $user_id,
            'store_id' => $store_id,
            'store_name' => $store_name ?? 'PunktePass',
            'points' => $points_add,
            'campaign_id' => $campaign_id ?: null,
            'vip_bonus' => $vip_bonus_applied,
            'vip_bonus_details' => $vip_bonus_details,
            'time' => current_time('mysql'),
            // ✅ Include customer info for immediate UI display
            'customer_name' => $customer_name ?: null,
            'email' => $user_info->email ?? null,
            'avatar' => $user_info->avatar ?? null,
        ], 200);
    }

    // ============================================================
    // 📜 REST: GET LOGS
    // ============================================================
    public static function rest_get_logs(WP_REST_Request $r) {
        global $wpdb;

        // 🏪 FILIALE SUPPORT: Use session-aware store ID
        $session_store = self::get_session_aware_store_id($r);
        if (!$session_store || !isset($session_store->id)) {
            return new WP_REST_Response(['error' => 'Invalid store'], 400);
        }

        $store_id = intval($session_store->id);

        // ✅ FIX: Get logs from ppv_users table (NOT WordPress users!)
        // ✅ FIX: Include log ID for unique scan_id generation
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT
                l.id AS log_id,
                l.created_at,
                l.user_id,
                l.message,
                l.type,
                u.email,
                u.first_name,
                u.last_name,
                u.avatar
            FROM {$wpdb->prefix}ppv_pos_log l
            LEFT JOIN {$wpdb->prefix}ppv_users u ON l.user_id = u.id
            WHERE l.store_id=%d
            ORDER BY l.id DESC LIMIT 15
        ", $store_id));

        // Format response for JS with detailed info
        $formatted = array_map(function($log) use ($store_id) {
            $created = strtotime($log->created_at);

            // Extract points from message (e.g., "+5 Punkte" → 5)
            $points = '-';
            if (preg_match('/\+(\d+)/', $log->message, $m)) {
                $points = $m[1];
            }

            // Build display: Name > Email > #ID
            $first = trim($log->first_name ?? '');
            $last = trim($log->last_name ?? '');
            $full_name = trim("$first $last");
            $email = $log->email ?? '';

            return [
                'scan_id' => "log-{$store_id}-{$log->log_id}", // ✅ Unique ID for deduplication
                'user_id' => $log->user_id,
                'customer_name' => $full_name ?: null,
                'email' => $email ?: null,
                'avatar' => $log->avatar ?: null,
                'message' => $log->message,
                'date_short' => date('d.m.', $created),
                'time_short' => date('H:i', $created),
                'points' => $points,
                'success' => ($log->type === 'qr_scan'),
            ];
        }, $logs);

        return new WP_REST_Response($formatted, 200);
    }

    // ============================================================
    // 📥 REST: EXPORT CSV
    // ============================================================
    public static function rest_export_csv(WP_REST_Request $r) {
        global $wpdb;

        // 🏪 Get store from session
        $session_store = self::get_session_aware_store_id($r);
        if (!$session_store || !isset($session_store->id)) {
            return new WP_REST_Response(['error' => 'Invalid store'], 400);
        }

        $store_id = intval($session_store->id);
        $period = sanitize_text_field($r->get_param('period') ?: 'today');
        $date_param = sanitize_text_field($r->get_param('date') ?: '');

        // Build date filter
        $date_filter = '';
        $filename_suffix = '';

        if ($period === 'today' || ($period === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_param))) {
            $target_date = $period === 'today' ? date('Y-m-d') : $date_param;
            $date_filter = $wpdb->prepare(" AND DATE(l.created_at) = %s", $target_date);
            $filename_suffix = $target_date;
        } elseif ($period === 'month' && preg_match('/^\d{4}-\d{2}$/', $date_param)) {
            $date_filter = $wpdb->prepare(" AND DATE_FORMAT(l.created_at, '%%Y-%%m') = %s", $date_param);
            $filename_suffix = $date_param;
        } else {
            // Default: today
            $date_filter = $wpdb->prepare(" AND DATE(l.created_at) = %s", date('Y-m-d'));
            $filename_suffix = date('Y-m-d');
        }

        // Get logs for CSV
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT
                l.created_at,
                l.user_id,
                l.message,
                l.type,
                u.email,
                u.first_name,
                u.last_name
            FROM {$wpdb->prefix}ppv_pos_log l
            LEFT JOIN {$wpdb->prefix}ppv_users u ON l.user_id = u.id
            WHERE l.store_id = %d {$date_filter}
            ORDER BY l.created_at DESC
            LIMIT 1000
        ", $store_id));

        // Build CSV
        $csv_lines = [];
        $csv_lines[] = 'Datum,Zeit,Kunde,Email,Punkte,Status,Nachricht';

        foreach ($logs as $log) {
            $created = strtotime($log->created_at);
            $date = date('d.m.Y', $created);
            $time = date('H:i:s', $created);

            $first = trim($log->first_name ?? '');
            $last = trim($log->last_name ?? '');
            $customer = trim("$first $last") ?: 'Unbekannt';
            $email = $log->email ?: '-';

            // Extract points from message
            $points = '-';
            if (preg_match('/\+(\d+)/', $log->message, $m)) {
                $points = $m[1];
            }

            $status = $log->type === 'qr_scan' ? 'OK' : 'Fehler';
            $message = str_replace([',', "\n", "\r"], [';', ' ', ' '], $log->message);

            $csv_lines[] = sprintf('"%s","%s","%s","%s","%s","%s","%s"',
                $date, $time, $customer, $email, $points, $status, $message
            );
        }

        $csv_content = implode("\n", $csv_lines);
        $store_name = sanitize_title($session_store->name ?? 'pos');
        $filename = "pos-{$store_name}-{$filename_suffix}.csv";

        return new WP_REST_Response([
            'success' => true,
            'csv' => $csv_content,
            'filename' => $filename,
            'rows' => count($logs)
        ], 200);
    }

    // ============================================================
    // 📡 ABLY: Auth endpoint for token requests
    // ============================================================
    public static function ajax_ably_auth() {
        // Get store from POS token
        $store_key = isset($_SERVER['HTTP_PPV_POS_TOKEN'])
            ? sanitize_text_field($_SERVER['HTTP_PPV_POS_TOKEN'])
            : '';

        if (empty($store_key)) {
            wp_send_json_error('Unauthorized', 401);
        }

        $store = self::get_store_by_key($store_key);
        if (!$store) {
            wp_send_json_error('Invalid store', 401);
        }

        // Validate channel matches store
        $channel_name = sanitize_text_field($_POST['channel_name'] ?? '');
        $socket_id = sanitize_text_field($_POST['socket_id'] ?? '');

        // Channel format: store-{store_id}
        $expected_channel = 'store-' . intval($store->id);
        if ($channel_name !== $expected_channel) {
            wp_send_json_error('Channel mismatch', 403);
        }

        // Check if Ably is configured
        if (!class_exists('PPV_Ably') || !PPV_Ably::is_enabled()) {
            wp_send_json_error('Ably not configured', 500);
        }

        // Generate Ably token request
        $token_request = PPV_Ably::create_token_request('store-' . $store->id);
        if (!$token_request) {
            wp_send_json_error('Token request failed', 500);
        }

        wp_send_json($token_request);
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


        $store_key = sanitize_text_field($data['store_key'] ?? '');

        // 🏪 FILIALE SUPPORT: Use session-aware store ID lookup
        $store = self::get_session_aware_store_id($store_key);
        if (!$store) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Token hiányzik vagy ismeretlen bolt'
            ], 400);
        }

        // 🎯 Kampány adatok előkészítése
        // ✅ FIX: Don't accept empty campaign_type
        $campaign_type = sanitize_text_field($data['campaign_type'] ?? '');
        if (empty($campaign_type)) {
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
                break;
        }

        // ✅ DEBUG: Log fields before insert

        $wpdb->insert("{$prefix}ppv_campaigns", $fields);
        $campaign_id = $wpdb->insert_id;

        // ✅ DEBUG: Check if insert succeeded
        if ($wpdb->last_error) {
        } else {
        }

        // 📡 Ably: Notify real-time about new campaign
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            PPV_Ably::trigger_campaign_update($store->id, [
                'action' => 'created',
                'campaign_id' => $campaign_id,
                'title' => $fields['title'],
                'campaign_type' => $fields['campaign_type'],
            ]);
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

        // 🏪 FILIALE SUPPORT: Use session-aware store ID lookup
        $store = self::get_session_aware_store_id($store_key);

        if (!$store) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Token hiányzik vagy ismeretlen bolt'
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

        if (empty($id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_missing_data', '❌ Hiányzó adat')
            ], 400);
        }

        // 🏪 FILIALE SUPPORT: Use session-aware store ID lookup
        $store = self::get_session_aware_store_id($store_key);
        if (!$store) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Token hiányzik vagy ismeretlen bolt'
            ], 400);
        }

        $wpdb->delete("{$wpdb->prefix}ppv_campaigns", [
            'id' => $id,
            'store_id' => $store->id
        ]);

        self::insert_log($store->id, 0, "Kampány törölve: ID {$id}", 'campaign_delete');

        // 📡 Ably: Notify real-time about deleted campaign
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            PPV_Ably::trigger_campaign_update($store->id, [
                'action' => 'deleted',
                'campaign_id' => $id,
            ]);
        }

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



        $id = intval($d['id'] ?? 0);
        $store_key = sanitize_text_field($d['store_key'] ?? '');

        if (empty($id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ Kampány ID hiányzik'
            ], 400);
        }

        // 🏪 FILIALE SUPPORT: Use session-aware store ID lookup
        $store = self::get_session_aware_store_id($store_key);
        if (!$store) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Token hiányzik vagy ismeretlen bolt'
            ], 400);
        }

        // 🎯 Frissítési mezők
        // ✅ FIX: Don't accept empty campaign_type
        $campaign_type = sanitize_text_field($d['campaign_type'] ?? '');
        if (empty($campaign_type)) {
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
                break;
        }

        $wpdb->update("{$prefix}ppv_campaigns", $fields, [
            'id'        => $id,
            'store_id'  => $store->id,
        ]);

        self::insert_log($store->id, 0, "Kampány frissítve: ID {$id}", 'campaign_update');

        // 📡 Ably: Notify real-time about updated campaign
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            PPV_Ably::trigger_campaign_update($store->id, [
                'action' => 'updated',
                'campaign_id' => $id,
                'title' => $fields['title'],
                'campaign_type' => $fields['campaign_type'],
            ]);
        }

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

        if (empty($id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_missing_data', '❌ Hiányzó adat')
            ], 400);
        }

        // 🏪 FILIALE SUPPORT: Use session-aware store ID lookup
        $store = self::get_session_aware_store_id($store_key);
        if (!$store) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Token hiányzik vagy ismeretlen bolt'
            ], 400);
        }

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