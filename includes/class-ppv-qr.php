<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Kassenscanner & Kampagnen v5.3
 * Refactored with Traits for better maintainability
 * 
 * Author: Erik Borota / PunktePass
 */

// Load traits
require_once PPV_PLUGIN_DIR . 'includes/traits/trait-ppv-qr-scanner.php';
require_once PPV_PLUGIN_DIR . 'includes/traits/trait-ppv-qr-campaigns.php';
require_once PPV_PLUGIN_DIR . 'includes/traits/trait-ppv-qr-users.php';
require_once PPV_PLUGIN_DIR . 'includes/traits/trait-ppv-qr-devices.php';
require_once PPV_PLUGIN_DIR . 'includes/traits/trait-ppv-qr-rest.php';

class PPV_QR {

    // Use all traits
    use PPV_QR_Scanner_Trait;
    use PPV_QR_Campaigns_Trait;
    use PPV_QR_Users_Trait;
    use PPV_QR_Devices_Trait;
    use PPV_QR_REST_Trait;

    // ============================================================
    // 🔹 INITIALIZATION
    // ============================================================
    public static function hooks() {
        // Standalone route: intercept /qr-center before WP theme loads
        add_action('init', [__CLASS__, 'maybe_render_standalone'], 1);
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

    /** ============================================================
     *  🏢 GET ALL FILIALEN FOR HANDLER
     * ============================================================ */
    public static function get_handler_filialen() {
        global $wpdb;

        // Get the base store ID (not filiale-specific)
        $base_store_id = null;

        if (!empty($_SESSION['ppv_store_id'])) {
            $base_store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            $base_store_id = intval($_SESSION['ppv_vendor_store_id']);
        } elseif (!empty($GLOBALS['ppv_active_store_id'])) {
            $base_store_id = intval($GLOBALS['ppv_active_store_id']);
        }

        if (!$base_store_id) {
            // Try DB fallback
            $uid = get_current_user_id();
            if ($uid > 0) {
                $base_store_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                    $uid
                ));
            }
        }

        if (!$base_store_id) {
            return [];
        }

        // Get all stores: parent + children
        $filialen = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, company_name, address, city, plz
            FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d OR parent_store_id = %d
            ORDER BY (id = %d) DESC, name ASC
        ", $base_store_id, $base_store_id, $base_store_id));

        return $filialen ?: [];
    }

    /** ============================================================
     *  🕒 CHECK IF STORE IS OPEN (for scan validation)
     *  Returns: ['open' => bool, 'hours' => string|null]
     * ============================================================ */
    private static function is_store_open_for_scan($store_id) {
        global $wpdb;

        // Get opening_hours and enforce_opening_hours flag from store
        $store_data = $wpdb->get_row($wpdb->prepare(
            "SELECT opening_hours, enforce_opening_hours FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));

        // Check if opening hours enforcement is disabled
        if (!empty($store_data) && $store_data->enforce_opening_hours == 0) {
            return ['open' => true, 'hours' => null, 'reason' => 'enforcement_disabled'];
        }

        $opening_hours = $store_data->opening_hours ?? null;

        // If no opening hours set, assume always open (backwards compatibility)
        if (empty($opening_hours)) {
            return ['open' => true, 'hours' => null, 'reason' => 'no_hours_set'];
        }

        $hours = json_decode($opening_hours, true);
        if (!is_array($hours)) {
            return ['open' => true, 'hours' => null, 'reason' => 'invalid_json'];
        }

        // Get current day and time
        $now = current_time('timestamp');
        $day_map = [
            'monday' => 'mo',
            'tuesday' => 'di',
            'wednesday' => 'mi',
            'thursday' => 'do',
            'friday' => 'fr',
            'saturday' => 'sa',
            'sunday' => 'so'
        ];

        $day_name = strtolower(date('l', $now));
        $day = $day_map[$day_name] ?? 'mo';
        $current_time = date('H:i', $now);

        // Check if day exists in schedule
        if (!isset($hours[$day])) {
            return ['open' => false, 'hours' => null, 'reason' => 'day_not_set'];
        }

        $day_hours = $hours[$day];

        // Check closed flag
        if (!is_array($day_hours) || !empty($day_hours['closed'])) {
            return ['open' => false, 'hours' => 'Geschlossen', 'reason' => 'closed_flag'];
        }

        // Extract opening times
        $von = $day_hours['von'] ?? '';
        $bis = $day_hours['bis'] ?? '';

        if (empty($von) || empty($bis)) {
            return ['open' => false, 'hours' => null, 'reason' => 'no_times'];
        }

        // Check if current time is within opening hours
        $is_open = ($current_time >= $von && $current_time <= $bis);

        return [
            'open' => $is_open,
            'hours' => "{$von} - {$bis}",
            'current_time' => $current_time,
            'reason' => $is_open ? 'within_hours' : 'outside_hours'
        ];
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

    private static function insert_log($store_id, $user_id, $msg, $type = 'scan', $error_type = null, $scanner_id = null, $scanner_name = null, $points_change = 0) {
        global $wpdb;

        // Get IP address
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address)[0]);

        // Get user agent
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        // ═══════════════════════════════════════════════════════════
        // 🔒 DUPLICATE ERROR LOG PREVENTION
        // Skip if same error type was logged in last 1 hour from same IP
        // ═══════════════════════════════════════════════════════════
        if ($type === 'error' && $error_type !== null) {
            $recent_error = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_pos_log
                 WHERE store_id = %d
                   AND type = 'error'
                   AND ip_address = %s
                   AND JSON_EXTRACT(metadata, '$.error_type') = %s
                   AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 LIMIT 1",
                $store_id, $ip_address, $error_type
            ));

            if ($recent_error) {
                ppv_log("🔄 [Log] Skipping duplicate error log: {$error_type} from {$ip_address}");
                return null; // Skip duplicate
            }
        }

        // Prepare metadata (can be extended with additional info)
        $metadata_array = [
            'timestamp' => current_time('mysql'),
            'type' => $type
        ];

        // Add error_type to metadata if provided (for client-side translation)
        if ($error_type !== null) {
            $metadata_array['error_type'] = $error_type;
        }

        // Add scanner info to metadata (who performed the scan)
        if ($scanner_id !== null) {
            $metadata_array['scanner_id'] = $scanner_id;
            $metadata_array['scanner_name'] = $scanner_name;
        }

        $metadata = json_encode($metadata_array);

        $wpdb->insert("{$wpdb->prefix}ppv_pos_log", [
            'store_id' => $store_id,
            'user_id' => $user_id,
            'message' => sanitize_text_field($msg),
            'type' => $type,
            'points_change' => intval($points_change),
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

        // ✅ PPQR- format: Timed QR codes from user dashboard (30 min validity)
        // Format: PPQR-{user_id}-{timed_qr_token}
        // Token is 32-char MD5 hash stored in ppv_users.timed_qr_token
        if (strpos($qr, 'PPQR-') === 0) {
            $parts = explode('-', $qr);
            $uid_from_qr = intval($parts[1] ?? 0);
            $token_from_qr = $parts[2] ?? '';

            if (!empty($token_from_qr) && strlen($token_from_qr) === 32) {
                // Look up user by timed_qr_token (must not be expired)
                $actual_user_id = $wpdb->get_var($wpdb->prepare("
                    SELECT id FROM {$wpdb->prefix}ppv_users
                    WHERE timed_qr_token=%s AND timed_qr_expires > NOW() AND active=1
                    LIMIT 1
                ", $token_from_qr));

                if ($actual_user_id) {
                    if ($uid_from_qr != $actual_user_id) {
                        ppv_log("⚠️ [PPV_QR] decode_user_from_qr (PPQR): QR user_id mismatch! QR={$uid_from_qr}, actual={$actual_user_id} - using actual");
                    }
                    return intval($actual_user_id);
                } else {
                    // Token not found or expired
                    ppv_log("⚠️ [PPV_QR] decode_user_from_qr (PPQR): Timed QR token not found or expired for user_id={$uid_from_qr}");
                    return false;
                }
            }

            // Fallback to QR user_id if token format invalid
            ppv_log("⚠️ [PPV_QR] decode_user_from_qr (PPQR): Invalid token format, falling back to user_id={$uid_from_qr}");
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

        // RemixIcons loaded globally in punktepass.php

        // Only enqueue camera scanner JS for handlers/scanners
        if ($is_handler) {
            // Load Ably JS library + shared manager if configured
            $ably_enabled = class_exists('PPV_Ably') && PPV_Ably::is_enabled();
            if ($ably_enabled) {
                PPV_Ably::enqueue_scripts(); // Enqueues ably-js + ppv-ably-manager
            }

            // JS file version (cache busting)
            $js_version = '6.5.1';

            // Modular QR Scanner JS files with proper dependencies
            wp_enqueue_script('ppv-qr-core', PPV_PLUGIN_URL . 'assets/js/ppv-qr-core.js', ['jquery'], $js_version, true);
            wp_enqueue_script('ppv-qr-ui', PPV_PLUGIN_URL . 'assets/js/ppv-qr-ui.js', ['ppv-qr-core'], $js_version, true);
            wp_enqueue_script('ppv-qr-sync', PPV_PLUGIN_URL . 'assets/js/ppv-qr-sync.js', ['ppv-qr-core', 'ppv-qr-ui'], $js_version, true);
            wp_enqueue_script('ppv-qr-campaigns', PPV_PLUGIN_URL . 'assets/js/ppv-qr-campaigns.js', ['ppv-qr-core'], $js_version, true);
            wp_enqueue_script('ppv-qr-camera', PPV_PLUGIN_URL . 'assets/js/ppv-qr-camera.js', ['ppv-qr-core'], $js_version, true);

            // Main init module - depends on all other modules + optionally Ably
            $init_deps = ['ppv-qr-core', 'ppv-qr-ui', 'ppv-qr-sync', 'ppv-qr-campaigns', 'ppv-qr-camera'];
            if ($ably_enabled) {
                $init_deps[] = 'ppv-ably-manager';
            }
            wp_enqueue_script('ppv-qr', PPV_PLUGIN_URL . 'assets/js/ppv-qr-init.js', $init_deps, $js_version, true);

            // 🌐 Use PPV_Lang (already detected at init) - don't do separate detection!
            // This ensures consistency across all pages
            $lang = class_exists('PPV_Lang') ? PPV_Lang::$active : 'de';
            if (empty($lang) || !in_array($lang, ['de', 'hu', 'ro', 'en'])) {
                $lang = 'de';
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
                'plugin_url' => PPV_PLUGIN_URL, // For local vendor scripts (no CDN dependency)
            ];

            // Add scanner info if this is a scanner user
            if (!empty($_SESSION['ppv_user_type']) && $_SESSION['ppv_user_type'] === 'scanner' && !empty($_SESSION['ppv_user_id'])) {
                $scanner_id = intval($_SESSION['ppv_user_id']);

                // 🔧 FIX: Get scanner's name from ppv_users
                // Scanner users are created with 'username' field (e.g., "Adrian"), NOT display_name!
                $scanner_user = $wpdb->get_row($wpdb->prepare(
                    "SELECT display_name, username, first_name, last_name, email FROM {$wpdb->prefix}ppv_users WHERE id = %d LIMIT 1",
                    $scanner_id
                ));

                // Priority: display_name > username > first_name + last_name > email
                $scanner_name = '';
                if ($scanner_user) {
                    if (!empty($scanner_user->display_name)) {
                        $scanner_name = $scanner_user->display_name;
                    } elseif (!empty($scanner_user->username)) {
                        $scanner_name = $scanner_user->username;
                    } elseif (!empty($scanner_user->first_name) || !empty($scanner_user->last_name)) {
                        $scanner_name = trim(($scanner_user->first_name ?? '') . ' ' . ($scanner_user->last_name ?? ''));
                    } elseif (!empty($scanner_user->email)) {
                        $scanner_name = $scanner_user->email;
                    }
                }

                $store_data['scanner_id'] = $scanner_id;
                $store_data['scanner_name'] = $scanner_name ?: sanitize_email($_SESSION['ppv_user_email'] ?? '');
            }

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
    // 🚀 STANDALONE RENDERING (bypasses WordPress theme)
    // ============================================================

    /** Intercept /qr-center URL at init and render standalone page */
    public static function maybe_render_standalone() {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = rtrim($path, '/');
        if ($path !== '/qr-center') return;

        // ── Disable ALL WP/LiteSpeed optimization for standalone page ──
        ppv_disable_wp_optimization();

        // Start session
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        // Auth check
        if (!class_exists('PPV_Permissions')) {
            header('Location: /login');
            exit;
        }

        $auth_check = PPV_Permissions::check_handler();
        if (is_wp_error($auth_check)) {
            header('Location: /login');
            exit;
        }

        self::render_standalone_page();
        exit;
    }

    /** Render complete standalone HTML page for QR Center */
    private static function render_standalone_page() {
        global $wpdb;

        $plugin_url = PPV_PLUGIN_URL;
        $version    = PPV_Core::asset_version();
        $site_url   = get_site_url();
        $js_version = '6.5.1';

        // ─── Language detection (same as render_qr_center) ───
        $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? '');
        if (empty($lang) && isset($_GET['lang'])) {
            $lang = sanitize_text_field($_GET['lang']);
        }
        if ((empty($lang) || !in_array($lang, ['de', 'hu', 'ro', 'en'])) && !empty($_SESSION['ppv_user_id'])) {
            $user_lang = $wpdb->get_var($wpdb->prepare(
                "SELECT language FROM {$wpdb->prefix}ppv_users WHERE id=%d LIMIT 1",
                intval($_SESSION['ppv_user_id'])
            ));
            if (!empty($user_lang) && in_array($user_lang, ['de', 'hu', 'ro', 'en'])) {
                $lang = $user_lang;
            }
        }
        if (empty($lang) || !in_array($lang, ['de', 'hu', 'ro', 'en'])) {
            $lang = defined('PPV_LANG_ACTIVE') ? PPV_LANG_ACTIVE : 'de';
        }

        $lang_file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang}.php";
        if (!file_exists($lang_file)) {
            $lang_file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-de.php";
        }
        $strings = include $lang_file;
        $GLOBALS['ppv_current_lang'] = $strings;

        // Set PPV_Lang strings for components that read them
        if (class_exists('PPV_Lang')) {
            PPV_Lang::$strings = $strings;
            PPV_Lang::$active  = $lang;
        }

        // ─── Store ID resolution (same as enqueue_assets) ───
        $store_id  = 0;
        $store_key = '';

        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id = intval($_SESSION['ppv_current_filiale_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_active_store'])) {
            $store_id = intval($_SESSION['ppv_active_store']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            $store_id = intval($_SESSION['ppv_vendor_store_id']);
        }

        if ($store_id > 0) {
            $store_key = $wpdb->get_var($wpdb->prepare(
                "SELECT store_key FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
                $store_id
            )) ?: '';
        }

        // ─── Build JS data objects ───
        $store_data = [
            'store_id'   => intval($store_id),
            'store_key'  => $store_key,
            'plugin_url' => $plugin_url,
        ];

        // Scanner user info
        if (!empty($_SESSION['ppv_user_type']) && $_SESSION['ppv_user_type'] === 'scanner' && !empty($_SESSION['ppv_user_id'])) {
            $scanner_id   = intval($_SESSION['ppv_user_id']);
            $scanner_user = $wpdb->get_row($wpdb->prepare(
                "SELECT display_name, username, first_name, last_name, email FROM {$wpdb->prefix}ppv_users WHERE id=%d LIMIT 1",
                $scanner_id
            ));
            $scanner_name = '';
            if ($scanner_user) {
                if (!empty($scanner_user->display_name))    $scanner_name = $scanner_user->display_name;
                elseif (!empty($scanner_user->username))    $scanner_name = $scanner_user->username;
                elseif (!empty($scanner_user->first_name))  $scanner_name = trim($scanner_user->first_name . ' ' . ($scanner_user->last_name ?? ''));
                elseif (!empty($scanner_user->email))       $scanner_name = $scanner_user->email;
            }
            $store_data['scanner_id']   = $scanner_id;
            $store_data['scanner_name'] = $scanner_name ?: sanitize_email($_SESSION['ppv_user_email'] ?? '');
        }

        // Ably
        $ably_enabled = class_exists('PPV_Ably') && PPV_Ably::is_enabled();
        if ($ably_enabled) {
            $store_data['ably'] = ['key' => PPV_Ably::get_key()];
        }

        $scan_data = [
            'rest_url'   => esc_url(rest_url('punktepass/v1/pos/scan')),
            'store_key'  => $store_key,
            'plugin_url' => $plugin_url,
            'lang'       => substr($lang, 0, 2),
        ];

        // Rewards management data
        $ably_config_rewards = null;
        if ($ably_enabled) {
            $ably_config_rewards = [
                'key'     => PPV_Ably::get_key(),
                'channel' => 'store-' . $store_id
            ];
        }
        $rewards_mgmt_data = [
            'base'     => esc_url(rest_url('ppv/v1/')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'store_id' => intval($store_id),
            'ably'     => $ably_config_rewards
        ];

        // VIP settings data
        $vip_data = [
            'base'     => esc_url(rest_url('ppv/v1/')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'store_id' => intval($store_id)
        ];

        // ─── Scanner user check ───
        $is_scanner = class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user();

        // ─── Dark mode (ppv_theme cookie used by theme-loader.js) ───
        $theme_cookie = $_COOKIE['ppv_theme'] ?? ($_COOKIE['ppv_handler_theme'] ?? 'light');
        $is_dark = ($theme_cookie === 'dark');

        // ─── Capture tab content from trait render methods ───
        ob_start();
        self::render_pos_scanner();
        $scanner_html = ob_get_clean();

        ob_start();
        self::render_user_devices($is_scanner);
        $devices_html = ob_get_clean();

        $rewards_html = '';
        $scanner_users_html = '';
        $vip_html = '';
        if (!$is_scanner) {
            if (class_exists('PPV_Rewards_Management')) {
                $rewards_html = PPV_Rewards_Management::render_management_page();
            }
            ob_start();
            self::render_scanner_users();
            $scanner_users_html = ob_get_clean();

            if (class_exists('PPV_VIP_Settings')) {
                $vip_html = PPV_VIP_Settings::render_settings_page();
            }
        }

        // ─── Global header ───
        $global_header = '';
        if (class_exists('PPV_User_Dashboard')) {
            ob_start();
            PPV_User_Dashboard::render_global_header();
            $global_header = ob_get_clean();
        }

        // ─── Bottom nav ───
        $bottom_nav = '';
        $bottom_nav_context = '';
        if (class_exists('PPV_Bottom_Nav')) {
            $bottom_nav = PPV_Bottom_Nav::render_nav();
            ob_start();
            PPV_Bottom_Nav::inject_context();
            $bottom_nav_context = ob_get_clean();
        }

        // ─── Body classes ───
        $body_classes = ['ppv-standalone', 'ppv-app-mode', 'ppv-handler-mode'];
        $body_classes[] = $is_dark ? 'ppv-dark' : 'ppv-light';
        if ($is_dark) $body_classes[] = 'ppv-dark-mode';
        $body_class = implode(' ', $body_classes);

        // ─── Output complete HTML ───
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>" data-theme="<?php echo $is_dark ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <?php ppv_standalone_cleanup_head(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>QR Center - PunktePass</title>
    <link rel="manifest" href="<?php echo esc_url($site_url); ?>/manifest.json">
    <link rel="icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-core.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-components.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-global-header.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-handler.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-bottom-nav.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-qr.css?v=<?php echo esc_attr($version); ?>">
<?php if ($is_dark): ?>
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-theme-dark-colors.css?v=<?php echo esc_attr($version); ?>">
<?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script>
    var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    var PPV_STORE_DATA = <?php echo wp_json_encode($store_data); ?>;
    var PPV_SCAN_DATA = <?php echo wp_json_encode($scan_data); ?>;
    window.ppv_lang = <?php echo wp_json_encode($strings); ?>;
    window.ppv_rewards_mgmt = <?php echo wp_json_encode($rewards_mgmt_data); ?>;
    window.ppv_vip = <?php echo wp_json_encode($vip_data); ?>;
    </script>
    <style>
    html,body{margin:0;padding:0;min-height:100vh;min-height:100dvh;background:var(--pp-bg,#f5f5f7);overflow-y:auto;overflow-x:hidden;overscroll-behavior:none;overscroll-behavior-y:none;touch-action:pan-y pan-x}
    .ppv-standalone-wrap{max-width:768px;margin:0 auto;padding:0 0 90px 0;min-height:100vh;min-height:100dvh;padding-top:12px}
    </style>
</head>
<body class="<?php echo esc_attr($body_class); ?>">
<?php echo $global_header; ?>
<div class="ppv-standalone-wrap">
    <div class="ppv-qr-wrapper glass-card">
        <h2 style="font-size:18px;margin-bottom:16px;">
            <i class="ri-broadcast-line" style="margin-right:6px;"></i><?php echo esc_html($strings['qrcamp_title'] ?? 'Kassenscanner & Kampagnen'); ?>
        </h2>

        <div class="ppv-tabs">
            <button class="ppv-tab active" data-tab="scanner" id="ppv-tab-scanner">
                <i class="ri-qr-scan-2-line"></i> <?php echo esc_html($strings['tab_scanner'] ?? 'Kassenscanner'); ?>
            </button>
            <button class="ppv-tab" data-tab="devices" id="ppv-tab-devices">
                <i class="ri-smartphone-line"></i> <?php echo esc_html($strings['tab_devices'] ?? 'Geräte'); ?>
            </button>
<?php if (!$is_scanner): ?>
            <button class="ppv-tab" data-tab="rewards" id="ppv-tab-rewards">
                <i class="ri-gift-line"></i> <?php echo esc_html($strings['tab_rewards'] ?? 'Prämien'); ?>
            </button>
            <button class="ppv-tab" data-tab="scanner-users" id="ppv-tab-scanner-users">
                <i class="ri-team-line"></i> <?php echo esc_html($strings['tab_scanner_users'] ?? 'Scanner Benutzer'); ?>
            </button>
            <button class="ppv-tab" data-tab="vip" id="ppv-tab-vip">
                <i class="ri-vip-crown-line"></i> <?php echo esc_html($strings['tab_vip'] ?? 'VIP Einstellungen'); ?>
            </button>
<?php endif; ?>
        </div>

        <div class="ppv-tab-content active" id="tab-scanner" style="border:none;box-shadow:none;padding:8px 0;background:transparent;">
            <?php echo $scanner_html; ?>
        </div>

        <div class="ppv-tab-content" id="tab-devices" style="border:none;box-shadow:none;padding:8px 0;background:transparent;">
            <?php echo $devices_html; ?>
        </div>

<?php if (!$is_scanner): ?>
        <div class="ppv-tab-content" id="tab-rewards" style="border:none;box-shadow:none;padding:8px 0;background:transparent;">
            <?php echo $rewards_html; ?>
        </div>

        <div class="ppv-tab-content" id="tab-scanner-users" style="border:none;box-shadow:none;padding:8px 0;background:transparent;">
            <?php echo $scanner_users_html; ?>
        </div>

        <div class="ppv-tab-content" id="tab-vip" style="border:none;box-shadow:none;padding:8px 0;background:transparent;">
            <?php echo $vip_html; ?>
        </div>
<?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($){
        var savedTab = localStorage.getItem('ppv_active_tab');
        var $savedTabBtn = $(".ppv-tab[data-tab='" + savedTab + "']");
        if (savedTab && $savedTabBtn.length) {
            $(".ppv-tab").removeClass("active");
            $savedTabBtn.addClass("active");
            $(".ppv-tab-content").removeClass("active");
            $("#tab-" + savedTab).addClass("active");
        }
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
</div>
<?php echo $bottom_nav; ?>
<?php echo $bottom_nav_context; ?>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-qr-core.js?v=<?php echo esc_attr($js_version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-qr-ui.js?v=<?php echo esc_attr($js_version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-qr-sync.js?v=<?php echo esc_attr($js_version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-qr-campaigns.js?v=<?php echo esc_attr($js_version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-qr-camera.js?v=<?php echo esc_attr($js_version); ?>"></script>
<?php if ($ably_enabled): ?>
<script src="https://cdn.ably.com/lib/ably.min-1.js"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-ably-manager.js?v=<?php echo esc_attr($version); ?>"></script>
<?php endif; ?>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-qr-init.js?v=<?php echo esc_attr($js_version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-hidden-scan.js?v=<?php echo esc_attr($js_version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-rewards-management.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-vip-settings.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-theme-loader.js?v=<?php echo esc_attr($version); ?>"></script>
<?php if (class_exists('PPV_Bottom_Nav')): ?>
<script><?php echo PPV_Bottom_Nav::inline_js(); ?></script>
<?php endif; ?>
</body>
</html>
<?php
    }

    // ============================================================
    // 🎨 FRONTEND RENDERING (WordPress shortcode - fallback)
    // ============================================================
    public static function render_qr_center() {
        global $wpdb;

        // 🎨 Enqueue QR Center CSS
        wp_enqueue_style('ppv-qr', PPV_PLUGIN_URL . 'assets/css/ppv-qr.css', ['ppv-components'], PPV_Core::asset_version());

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

        // 🌐 Language priority: Cookie > GET > DB > default
        $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? '');
        if (empty($lang) && isset($_GET['lang'])) {
            $lang = sanitize_text_field($_GET['lang']);
        }
        if ((empty($lang) || !in_array($lang, ['de', 'hu', 'ro', 'en'])) && !empty($_SESSION['ppv_user_id'])) {
            global $wpdb;
            $user_lang = $wpdb->get_var($wpdb->prepare(
                "SELECT language FROM {$wpdb->prefix}ppv_users WHERE id=%d LIMIT 1",
                intval($_SESSION['ppv_user_id'])
            ));
            if (!empty($user_lang) && in_array($user_lang, ['de', 'hu', 'ro', 'en'])) {
                $lang = $user_lang;
            }
        }
        if (empty($lang) || !in_array($lang, ['de', 'hu', 'ro', 'en'])) {
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

        <!-- Interface with Tabs (Scanner user sees limited tabs) -->
        <div class="ppv-qr-wrapper glass-card">
            <h2 style="font-size: 18px; margin-bottom: 16px;"><i class="ri-broadcast-line" style="margin-right: 6px;"></i><?php echo self::t('qrcamp_title', 'Kassenscanner & Kampagnen'); ?></h2>

            <div class="ppv-tabs">
                <button class="ppv-tab active" data-tab="scanner" id="ppv-tab-scanner">
                    <i class="ri-qr-scan-2-line"></i> <?php echo self::t('tab_scanner', 'Kassenscanner'); ?>
                </button>
                <button class="ppv-tab" data-tab="devices" id="ppv-tab-devices">
                    <i class="ri-smartphone-line"></i> <?php echo self::t('tab_devices', 'Geräte'); ?>
                </button>
                <?php if (!$is_scanner): ?>
                <button class="ppv-tab" data-tab="rewards" id="ppv-tab-rewards">
                    <i class="ri-gift-line"></i> <?php echo self::t('tab_rewards', 'Prämien'); ?>
                </button>
                <button class="ppv-tab" data-tab="scanner-users" id="ppv-tab-scanner-users">
                    <i class="ri-team-line"></i> <?php echo self::t('tab_scanner_users', 'Scanner Benutzer'); ?>
                </button>
                <button class="ppv-tab" data-tab="vip" id="ppv-tab-vip">
                    <i class="ri-vip-crown-line"></i> <?php echo self::t('tab_vip', 'VIP Einstellungen'); ?>
                </button>
                <?php endif; ?>
            </div>

            <!-- TAB CONTENT: SCANNER -->
            <div class="ppv-tab-content active" id="tab-scanner" style="border: none; box-shadow: none; padding: 8px 0; background: transparent;">
                <?php self::render_pos_scanner(); ?>
            </div>

            <!-- TAB CONTENT: GERÄTE -->
            <div class="ppv-tab-content" id="tab-devices" style="border: none; box-shadow: none; padding: 8px 0; background: transparent;">
                <?php self::render_user_devices($is_scanner); ?>
            </div>

            <?php if (!$is_scanner): ?>
            <!-- TAB CONTENT: PRÄMIEN -->
            <div class="ppv-tab-content" id="tab-rewards" style="border: none; box-shadow: none; padding: 8px 0; background: transparent;">
                <?php echo do_shortcode('[ppv_rewards_management]'); ?>
            </div>

            <!-- TAB CONTENT: SCANNER BENUTZER -->
            <div class="ppv-tab-content" id="tab-scanner-users" style="border: none; box-shadow: none; padding: 8px 0; background: transparent;">
                <?php self::render_scanner_users(); ?>
            </div>

            <!-- TAB CONTENT: VIP EINSTELLUNGEN -->
            <div class="ppv-tab-content" id="tab-vip" style="border: none; box-shadow: none; padding: 8px 0; background: transparent;">
                <?php echo do_shortcode('[ppv_vip_settings]'); ?>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($){
            // Restore saved tab on page load (only if the tab button exists)
            var savedTab = localStorage.getItem('ppv_active_tab');
            var $savedTabBtn = $(".ppv-tab[data-tab='" + savedTab + "']");

            // Only restore if the saved tab exists (scanner users don't have all tabs)
            if (savedTab && $savedTabBtn.length) {
                $(".ppv-tab").removeClass("active");
                $savedTabBtn.addClass("active");
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
}
