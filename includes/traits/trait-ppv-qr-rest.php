<?php
if (!defined('ABSPATH')) exit;

/**
 * PPV_QR_REST_Trait
 * REST API and AJAX functions for PPV_QR class
 * 
 * Contains:
 * - ajax_switch_filiale()
 * - register_rest_routes()
 * - rest_get_strings()
 * - rest_process_scan()
 * - rest_get_logs()
 * - rest_export_csv()
 * - ajax_ably_auth()
 * - rest_sync_offline()
 * - rest_create_campaign()
 * - rest_list_campaigns()
 * - rest_delete_campaign()
 * - rest_update_campaign()
 * - rest_archive_campaign()
 * - rest_redemption_user_response()
 * - rest_redemption_handler_response()
 */
trait PPV_QR_REST_Trait {

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
        // 📱 Scanner endpoints - authenticate via PPV-POS-Token header (store key)
        // These do NOT use NONCE because scanners aren't logged into WordPress
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

        // Offline sync also from scanner device - no NONCE
        register_rest_route('punktepass/v1', '/pos/sync_offline', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_sync_offline'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaign', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_create_campaign'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaigns', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_list_campaigns'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaign/delete', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_delete_campaign'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaign/update', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_update_campaign'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaign/archive', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_archive_campaign'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce'],
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

        // ════════════════════════════════════════════════════════════
        // 🎁 REAL-TIME REDEMPTION ENDPOINTS
        // Token-based security - token sent via Ably only to intended user
        // ════════════════════════════════════════════════════════════

        // User responds to redemption prompt (accept/decline)
        // Security: Token is unique 64-char hex, sent via Ably to specific user only
        // Token validated in callback (exists in DB, not expired, correct status)
        register_rest_route('ppv/v1', '/redemption/user-response', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_redemption_user_response'],
            'permission_callback' => '__return_true',
        ]);

        // Handler confirms or rejects redemption (called from POS scanner)
        // Security: PPV-POS-Token header + token validated in callback
        register_rest_route('ppv/v1', '/redemption/handler-response', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_redemption_handler_response'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
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

        // 🔒 SECURITY: Rate limiting
        // 1. General rate limit for ALL requests (prevents spam/DoS) - 20/min
        $rate_check_all = PPV_Permissions::check_rate_limit('pos_scan_all', 20, 60);
        if (is_wp_error($rate_check_all)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => '⚠️ ' . $rate_check_all->get_error_message()
            ], 429);
        }
        PPV_Permissions::increment_rate_limit('pos_scan_all', 60);

        // 2. Successful scan rate limit - 3/min (checked here, incremented on success)
        $rate_check_success = PPV_Permissions::check_rate_limit('pos_scan_success', 3, 60);
        if (is_wp_error($rate_check_success)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => '⚠️ Zu viele erfolgreiche Scans. Bitte warte 1 Minute.'
            ], 429);
        }

        $data = $r->get_json_params();
        $qr_code = sanitize_text_field($data['qr'] ?? '');
        $store_key = sanitize_text_field($data['store_key'] ?? '');
        $campaign_id = intval($data['campaign_id'] ?? 0);

        // GPS data from scanner (optional)
        $scan_lat = isset($data['latitude']) ? floatval($data['latitude']) : null;
        $scan_lng = isset($data['longitude']) ? floatval($data['longitude']) : null;

        // Scanner employee ID (who is scanning) - for accountability
        $scanner_id = isset($data['scanner_id']) ? intval($data['scanner_id']) : null;
        $scanner_name = isset($data['scanner_name']) ? sanitize_text_field($data['scanner_name']) : null;

        // 🔒 Device/IP tracking for fraud detection and audit
        $device_fingerprint = null;
        if (isset($data['device_fingerprint']) && !empty($data['device_fingerprint'])) {
            // Use central validation if available
            if (class_exists('PPV_Device_Fingerprint') && method_exists('PPV_Device_Fingerprint', 'validate_fingerprint')) {
                $fp_validation = PPV_Device_Fingerprint::validate_fingerprint($data['device_fingerprint'], true);
                $device_fingerprint = $fp_validation['valid'] ? $fp_validation['sanitized'] : null;
            } else {
                // Fallback to basic sanitization
                $device_fingerprint = sanitize_text_field($data['device_fingerprint']);
            }
        }
        $ip_address = self::get_client_ip();

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

        // ═══════════════════════════════════════════════════════════
        // 🕒 OPENING HOURS CHECK - Block scans outside business hours
        // ═══════════════════════════════════════════════════════════
        $opening_check = self::is_store_open_for_scan($store_id);
        if (!$opening_check['open']) {
            $store_name = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
                $store_id
            ));

            ppv_log("⏰ [PPV_QR] BLOCKED: Scan outside opening hours - store_id={$store_id}, reason={$opening_check['reason']}, hours={$opening_check['hours']}, current={$opening_check['current_time']}");

            // Log the blocked scan attempt
            self::insert_log($store_id, 0, '⏰ Scan blocked - store closed', 'error', 'store_closed');

            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_store_closed', '⏰ Az üzlet jelenleg zárva van'),
                'detail' => self::t('err_store_closed_detail', 'Scan nem lehetséges nyitvatartási időn kívül'),
                'store_name' => $store_name ?? 'PunktePass',
                'opening_hours' => $opening_check['hours'],
                'current_time' => $opening_check['current_time'] ?? date('H:i'),
                'error_type' => 'store_closed'
            ], 403);
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

        // ═══════════════════════════════════════════════════════════
        // 🚫 SELF-SCAN PROTECTION - Employees cannot scan their own QR
        // ═══════════════════════════════════════════════════════════
        if ($scanner_id !== null && $scanner_id > 0 && $scanner_id === $user_id) {
            ppv_log("🚫 [PPV_QR] BLOCKED: Self-scan attempt! scanner_id={$scanner_id}, user_id={$user_id}");

            // Log the blocked self-scan attempt
            self::insert_log($store_id, $user_id, '🚫 Self-scan blocked', 'error', 'self_scan', $scanner_id, $scanner_name);

            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_self_scan', '🚫 Eigenen QR-Code scannen nicht erlaubt'),
                'detail' => self::t('err_self_scan_detail', 'Mitarbeiter können ihren eigenen QR-Code nicht scannen'),
                'store_name' => $store->name ?? 'PunktePass',
                'error_type' => 'self_scan'
            ], 403);
        }

        $rate_check = self::check_rate_limit($user_id, $store_id);
        if ($rate_check['limited']) {
            $response_data = $rate_check['response']->get_data();
            $error_type = $response_data['error_type'] ?? null;

            // Smart logging: deduplicate repeated errors
            $should_log = true;
            if ($error_type === 'already_scanned_today') {
                // Log only ONCE per day per user+store (not every retry)
                $existing_error = $wpdb->get_var($wpdb->prepare("
                    SELECT id FROM {$wpdb->prefix}ppv_pos_log
                    WHERE user_id = %d AND store_id = %d AND type = 'error'
                    AND DATE(created_at) = CURDATE()
                    LIMIT 1
                ", $user_id, $store_id));
                $should_log = !$existing_error;
            } elseif ($error_type === 'duplicate_scan') {
                // Never log duplicate_scan - just retry spam within 2 min
                $should_log = false;
            }

            if ($should_log) {
                self::insert_log($store_id, $user_id, $response_data['message'] ?? '⚠️ Rate limit', 'error', $error_type);
            }

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
        // GPS 3-ZONE VALIDATION (Fázis 1 - 2025-12)
        // Zone 1 (< 100m): OK
        // Zone 2 (100-200m): LOG only
        // Zone 3 (> 200m): BLOCK
        // Mobile scanner devices: GPS check SKIPPED
        // ═══════════════════════════════════════════════════════════
        if (class_exists('PPV_Scan_Monitoring')) {
            // Pass device_fingerprint for mobile scanner check
            $gps_check = PPV_Scan_Monitoring::validate_scan_location($store_id, $scan_lat, $scan_lng, $device_fingerprint);

            $gps_action = $gps_check['action'] ?? 'none';
            $gps_reason = $gps_check['reason'] ?? null;

            // ACTION: BLOCK - Scan rejected (>200m or wrong country)
            if ($gps_action === 'block' || !$gps_check['valid']) {
                PPV_Scan_Monitoring::log_suspicious_scan($store_id, $user_id, $scan_lat, $scan_lng, $gps_check);

                $distance = $gps_check['distance'] ?? 0;
                $max_allowed = $gps_check['max_allowed'] ?? 200;

                ppv_log("[PPV_QR] ❌ GPS BLOCKED: user={$user_id}, store={$store_id}, distance={$distance}m, reason={$gps_reason}");

                // Get user info for response
                $user_info = $wpdb->get_row($wpdb->prepare("
                    SELECT first_name, last_name, email, avatar
                    FROM {$wpdb->prefix}ppv_users WHERE id = %d
                ", $user_id));
                $customer_name = trim(($user_info->first_name ?? '') . ' ' . ($user_info->last_name ?? ''));

                $error_message = self::t('err_gps_too_far', '❌ Túl messze vagy az üzlettől ({distance}m). Maximum: {max}m');
                $error_message = str_replace(['{distance}', '{max}'], [$distance, $max_allowed], $error_message);

                if ($gps_reason === 'wrong_country') {
                    $error_message = self::t('err_wrong_country', '❌ Rossz ország! A scan csak az üzlet országában működik.');
                }

                return new WP_REST_Response([
                    'success' => false,
                    'message' => $error_message,
                    'user_id' => $user_id,
                    'customer_name' => $customer_name ?: null,
                    'email' => $user_info->email ?? null,
                    'avatar' => $user_info->avatar ?? null,
                    'error_type' => 'gps_blocked',
                    'distance' => $distance,
                    'max_distance' => $max_allowed
                ], 403);
            }

            // ACTION: LOG - Scan allowed but logged as suspicious (100-200m)
            if ($gps_action === 'log') {
                PPV_Scan_Monitoring::log_suspicious_scan($store_id, $user_id, $scan_lat, $scan_lng, $gps_check);
                ppv_log("[PPV_QR] ⚠️ GPS LOG: user={$user_id}, store={$store_id}, distance={$gps_check['distance']}m (Zone 2: 100-200m)");
                // Scan continues
            }

            // GPS SPOOF DETECTION - Check for impossible travel
            if ($scan_lat && $scan_lng) {
                $travel_check = PPV_Scan_Monitoring::check_impossible_travel($user_id, $scan_lat, $scan_lng);

                if ($travel_check['suspicious']) {
                    ppv_log("[PPV_QR] 🚨 GPS SPOOF DETECTED: user={$user_id}, " . json_encode($travel_check['details']));

                    // Log as suspicious scan with impossible_travel reason
                    PPV_Scan_Monitoring::log_suspicious_scan($store_id, $user_id, $scan_lat, $scan_lng, [
                        'valid' => false,
                        'reason' => 'impossible_travel',
                        'distance' => $travel_check['details']['distance_km'] * 1000,
                        'details' => $travel_check['details']
                    ]);

                    // BLOCK the scan - GPS spoofing detected
                    $user_info = $wpdb->get_row($wpdb->prepare("
                        SELECT first_name, last_name, email, avatar
                        FROM {$wpdb->prefix}ppv_users WHERE id = %d
                    ", $user_id));
                    $customer_name = trim(($user_info->first_name ?? '') . ' ' . ($user_info->last_name ?? ''));

                    return new WP_REST_Response([
                        'success' => false,
                        'message' => self::t('err_gps_spoof', '❌ Gyanús GPS aktivitás észlelve. Kérjük, próbáld újra később.'),
                        'user_id' => $user_id,
                        'customer_name' => $customer_name ?: null,
                        'email' => $user_info->email ?? null,
                        'avatar' => $user_info->avatar ?? null,
                        'error_type' => 'gps_spoof_detected'
                    ], 403);
                }

                // Log GPS data for future impossible travel detection
                PPV_Scan_Monitoring::log_gps_scan($user_id, $store_id, $scan_lat, $scan_lng);
            }
        }

        // ═══════════════════════════════════════════════════════════
        // VELOCITY CHECK (Fraud Detection) - Same IP / Scan Frequency
        // Logs and alerts admin if suspicious patterns detected
        // ═══════════════════════════════════════════════════════════
        if (class_exists('PPV_Scan_Monitoring')) {
            $velocity_check = PPV_Scan_Monitoring::perform_velocity_check($store_id, $user_id);

            if (!$velocity_check['passed']) {
                ppv_log("[PPV_QR] ⚠️ VELOCITY ALERT: user={$user_id}, store={$store_id}, alerts=" . json_encode($velocity_check['alerts']));
                // Scan continues - admin notified via email and suspicious_scans table
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

        // 🔒 SECURITY: Max point limit per scan (prevents point inflation)
        $max_points_per_scan = 20;
        if ($points_add > $max_points_per_scan) {
            ppv_log("⚠️ [PPV_QR] Points capped: requested={$points_add}, max={$max_points_per_scan}");
            $points_add = $max_points_per_scan;
        }

        // Check for bonus day (multiplies base/campaign points)
        $bonus = $wpdb->get_row($wpdb->prepare("
            SELECT multiplier, extra_points FROM {$wpdb->prefix}ppv_bonus_days
            WHERE store_id=%d AND date=%s AND active=1
        ", $store_id, date('Y-m-d')));

        if ($bonus) {
            $points_add = (int)round(($points_add * (float)$bonus->multiplier) + (int)$bonus->extra_points);
        }

        // 🔒 FIX: Save TRUE base points BEFORE any bonuses for double_points calculations
        // This prevents birthday/comeback bonus from compounding on VIP bonuses
        $true_base_points = $points_add;

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
                // 🔒 FIX: Store streak params for verification inside transaction
                $streak_bonus_pending = false;
                $streak_expected_scan = 0;
                $streak_count_setting = 0;

                if ($vip_settings->vip_streak_enabled && $user_level !== null) {
                    $streak_count_setting = intval($vip_settings->vip_streak_count);
                    if ($streak_count_setting > 0) {
                        $user_scan_count = (int)$wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
                            WHERE user_id = %d AND store_id = %d AND type = 'qr_scan'
                        ", $user_id, $store_id));

                        $next_scan_number = $user_scan_count + 1;
                        $streak_expected_scan = $next_scan_number;

                        if ($next_scan_number % $streak_count_setting === 0) {
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

                            $streak_bonus_pending = true;
                            ppv_log("🔥 [PPV_QR] Streak bonus PRE-calculated! Expected scan #{$next_scan_number} (every {$streak_count_setting})");
                        }
                    }
                }

                // 4. FIRST SCAN EVER BONUS (one-time per store)
                // 🔒 FIX: Track first-scan bonus for verification inside transaction
                $first_scan_bonus_pending = false;

                if ($vip_settings->vip_daily_enabled && $user_level !== null) {
                    $ever_scanned_here = (int)$wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
                        WHERE user_id = %d AND store_id = %d AND type = 'qr_scan'
                    ", $user_id, $store_id));

                    if ($ever_scanned_here === 0) {
                        $vip_bonus_details['daily'] = $getLevelValue(
                            $vip_settings->vip_daily_bronze ?? 5,
                            $vip_settings->vip_daily_silver,
                            $vip_settings->vip_daily_gold,
                            $vip_settings->vip_daily_platinum
                        );
                        $first_scan_bonus_pending = true;
                        ppv_log("🎉 [PPV_QR] First scan ever bonus PRE-calculated for user {$user_id} at store {$store_id}");
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
        // 🎂 BIRTHDAY BONUS
        // ═══════════════════════════════════════════════════════════
        $birthday_bonus_applied = 0;

        // Get birthday bonus settings from store (or parent store for filiales)
        $birthday_store_id = $store_id;
        if (class_exists('PPV_Filiale')) {
            $birthday_store_id = PPV_Filiale::get_parent_id($store_id);
        }

        $birthday_settings = $wpdb->get_row($wpdb->prepare("
            SELECT birthday_bonus_enabled, birthday_bonus_type, birthday_bonus_value, birthday_bonus_message
            FROM {$wpdb->prefix}ppv_stores WHERE id = %d
        ", $birthday_store_id));

        ppv_log("🎂 [PPV_QR] Birthday check: store_id={$birthday_store_id}, enabled=" . ($birthday_settings->birthday_bonus_enabled ?? 'NULL') . ", type=" . ($birthday_settings->birthday_bonus_type ?? 'NULL'));

        if ($birthday_settings && $birthday_settings->birthday_bonus_enabled) {
            $user_bday_data = $wpdb->get_row($wpdb->prepare("
                SELECT birthday, last_birthday_bonus_at FROM {$wpdb->prefix}ppv_users WHERE id = %d
            ", $user_id));

            ppv_log("🎂 [PPV_QR] User birthday data: user_id={$user_id}, birthday=" . ($user_bday_data->birthday ?? 'NULL') . ", last_bonus=" . ($user_bday_data->last_birthday_bonus_at ?? 'NULL'));

            if ($user_bday_data && $user_bday_data->birthday) {
                $today_md = date('m-d');
                $birthday_md = date('m-d', strtotime($user_bday_data->birthday));

                ppv_log("🎂 [PPV_QR] Date comparison: today={$today_md}, birthday={$birthday_md}, match=" . ($today_md === $birthday_md ? 'YES' : 'NO'));

                if ($today_md === $birthday_md) {
                    // Anti-abuse check: minimum 320 days between birthday bonuses
                    $can_receive_bonus = true;
                    if ($user_bday_data->last_birthday_bonus_at) {
                        $last_bonus_date = strtotime($user_bday_data->last_birthday_bonus_at);
                        $days_since_last_bonus = floor((time() - $last_bonus_date) / (60 * 60 * 24));
                        if ($days_since_last_bonus < 320) {
                            $can_receive_bonus = false;
                            ppv_log("🎂 [PPV_QR] Birthday bonus BLOCKED for user {$user_id}: only {$days_since_last_bonus} days since last bonus (min 320)");
                        }
                    }

                    if ($can_receive_bonus) {
                        ppv_log("🎂 [PPV_QR] Today is user {$user_id}'s birthday!");

                        $bonus_type = $birthday_settings->birthday_bonus_type ?? 'double_points';

                        switch ($bonus_type) {
                            case 'double_points':
                                // 🔒 FIX: Use TRUE base points, not points with VIP bonus already added
                                $birthday_bonus_applied = $true_base_points;
                                break;
                            case 'fixed_points':
                                $birthday_bonus_applied = intval($birthday_settings->birthday_bonus_value ?? 0);
                                break;
                        }

                        if ($birthday_bonus_applied > 0) {
                            // 🔒 FIX: Use atomic UPDATE with WHERE to prevent race condition
                            // Only update if last bonus was > 320 days ago (or never)
                            $rows_updated = $wpdb->query($wpdb->prepare("
                                UPDATE {$wpdb->prefix}ppv_users
                                SET last_birthday_bonus_at = %s
                                WHERE id = %d
                                AND (last_birthday_bonus_at IS NULL OR last_birthday_bonus_at < DATE_SUB(CURDATE(), INTERVAL 320 DAY))
                            ", date('Y-m-d'), $user_id));

                            if ($rows_updated > 0) {
                                // Successfully claimed - add bonus
                                $points_add += $birthday_bonus_applied;
                                ppv_log("🎂 [PPV_QR] Birthday bonus applied: type={$bonus_type}, bonus=+{$birthday_bonus_applied}, total_points={$points_add}");
                            } else {
                                // Race condition - another request already claimed it
                                $birthday_bonus_applied = 0;
                                ppv_log("🎂 [PPV_QR] Birthday bonus race condition prevented for user {$user_id}");
                            }
                        }
                    }
                }
            }
        }

        // ═══════════════════════════════════════════════════════════
        // 👋 COMEBACK CAMPAIGN BONUS
        // ═══════════════════════════════════════════════════════════
        $comeback_bonus_applied = 0;

        // Get comeback settings from store (or parent store for filiales)
        $comeback_store_id = $store_id;
        if (class_exists('PPV_Filiale')) {
            $comeback_store_id = PPV_Filiale::get_parent_id($store_id);
        }

        $comeback_settings = $wpdb->get_row($wpdb->prepare("
            SELECT comeback_enabled, comeback_days, comeback_bonus_type, comeback_bonus_value, comeback_message
            FROM {$wpdb->prefix}ppv_stores WHERE id = %d
        ", $comeback_store_id));

        ppv_log("👋 [PPV_QR] Comeback check: store_id={$comeback_store_id}, enabled=" . ($comeback_settings->comeback_enabled ?? 'NULL') . ", days=" . ($comeback_settings->comeback_days ?? 'NULL'));

        if ($comeback_settings && $comeback_settings->comeback_enabled) {
            // Get user's last scan date at this store (or parent store for filiales)
            $last_scan_date = $wpdb->get_var($wpdb->prepare("
                SELECT MAX(created) FROM {$wpdb->prefix}ppv_points
                WHERE user_id = %d AND store_id IN (
                    SELECT id FROM {$wpdb->prefix}ppv_stores
                    WHERE id = %d OR parent_store_id = %d
                ) AND type = 'qr_scan'
            ", $user_id, $comeback_store_id, $comeback_store_id));

            ppv_log("👋 [PPV_QR] User last scan: user_id={$user_id}, last_scan=" . ($last_scan_date ?? 'NULL (first scan)'));

            if ($last_scan_date) {
                $days_since_last_scan = floor((time() - strtotime($last_scan_date)) / (60 * 60 * 24));
                $comeback_days_required = intval($comeback_settings->comeback_days ?? 30);

                ppv_log("👋 [PPV_QR] Inactivity check: days_inactive={$days_since_last_scan}, required={$comeback_days_required}");

                if ($days_since_last_scan >= $comeback_days_required) {
                    ppv_log("👋 [PPV_QR] User {$user_id} qualifies for comeback bonus! Inactive for {$days_since_last_scan} days.");

                    $comeback_type = $comeback_settings->comeback_bonus_type ?? 'double_points';

                    switch ($comeback_type) {
                        case 'double_points':
                            // 🔒 FIX: Use TRUE base points, not points with VIP/birthday bonus already added
                            $comeback_bonus_applied = $true_base_points;
                            break;
                        case 'fixed_points':
                            $comeback_bonus_applied = intval($comeback_settings->comeback_bonus_value ?? 0);
                            break;
                    }

                    if ($comeback_bonus_applied > 0) {
                        $points_add += $comeback_bonus_applied;
                        ppv_log("👋 [PPV_QR] Comeback bonus applied: type={$comeback_type}, bonus=+{$comeback_bonus_applied}, total_points={$points_add}");
                    }
                }
            }
            // Note: First-time visitors don't get comeback bonus (no previous visit)
        }

        // ═══════════════════════════════════════════════════════════
        // INSERT POINTS (WITH TRANSACTION FOR ATOMICITY)
        // ═══════════════════════════════════════════════════════════
        $wpdb->query('START TRANSACTION');

        try {
            // 🔒 DUPLICATE CHECK: Prevent race condition double-inserts
            // Check if points were already inserted in the last 10 seconds (extended for slow networks)
            $recent_insert = $wpdb->get_var($wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}ppv_points
                WHERE user_id = %d AND store_id = %d AND type = 'qr_scan'
                AND created > DATE_SUB(NOW(), INTERVAL 10 SECOND)
                LIMIT 1
            ", $user_id, $store_id));

            if ($recent_insert) {
                $wpdb->query('ROLLBACK');
                ppv_log("⚠️ [PPV_QR] Duplicate scan blocked: user={$user_id}, store={$store_id}, existing_id={$recent_insert}");
                return new WP_REST_Response([
                    'success' => false,
                    'message' => self::t('err_duplicate_scan', '⚠️ Scan bereits verarbeitet'),
                    'error_type' => 'duplicate_scan'
                ], 429);
            }

            $insert_result = $wpdb->insert("{$wpdb->prefix}ppv_points", [
                'user_id' => $user_id,
                'store_id' => $store_id,
                'points' => $points_add,
                'campaign_id' => $campaign_id ?: null,
                'type' => 'qr_scan',
                // 🔒 NEW: Device/GPS tracking fields for fraud detection
                'device_fingerprint' => $device_fingerprint,
                'ip_address' => $ip_address,
                'latitude' => $scan_lat,
                'longitude' => $scan_lng,
                'scanner_id' => $scanner_id,
                'created' => current_time('mysql')
            ]);

            if ($insert_result === false) {
                $wpdb->query('ROLLBACK');
                ppv_log("❌ [PPV_QR] Failed to insert points: " . $wpdb->last_error);
                return new WP_REST_Response([
                    'success' => false,
                    'message' => '❌ Database error',
                    'error_type' => 'db_error'
                ], 500);
            }

            $points_insert_id = $wpdb->insert_id;

            // 🔒 FIX: Verify streak bonus INSIDE transaction to prevent race condition
            // If another request inserted between our count and our insert, the streak might be invalid
            if (isset($streak_bonus_pending) && $streak_bonus_pending && $streak_count_setting > 0) {
                $actual_scan_count = (int)$wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
                    WHERE user_id = %d AND store_id = %d AND type = 'qr_scan'
                ", $user_id, $store_id));

                // If actual count doesn't match expected streak trigger, remove the bonus
                if ($actual_scan_count % $streak_count_setting !== 0) {
                    // Race condition detected - another scan was inserted
                    $streak_bonus_value = $vip_bonus_details['streak'] ?? 0;
                    if ($streak_bonus_value > 0) {
                        $points_add -= $streak_bonus_value;
                        $vip_bonus_applied -= $streak_bonus_value;
                        $vip_bonus_details['streak'] = 0;

                        // Update the inserted record with corrected points
                        $wpdb->update(
                            "{$wpdb->prefix}ppv_points",
                            ['points' => $points_add],
                            ['id' => $points_insert_id],
                            ['%d'],
                            ['%d']
                        );

                        ppv_log("🔒 [PPV_QR] Streak bonus REVOKED due to race condition: expected scan #{$streak_expected_scan}, actual count={$actual_scan_count}");
                    }
                } else {
                    ppv_log("🔥 [PPV_QR] Streak bonus CONFIRMED! Actual scan count={$actual_scan_count} (every {$streak_count_setting})");
                }
            }

            // 🔒 FIX: Verify first-scan bonus INSIDE transaction to prevent race condition
            if (isset($first_scan_bonus_pending) && $first_scan_bonus_pending) {
                $actual_scan_count_for_first = (int)$wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
                    WHERE user_id = %d AND store_id = %d AND type = 'qr_scan'
                ", $user_id, $store_id));

                // If count > 1, this wasn't actually the first scan (race condition)
                if ($actual_scan_count_for_first > 1) {
                    $first_scan_bonus_value = $vip_bonus_details['daily'] ?? 0;
                    if ($first_scan_bonus_value > 0) {
                        $points_add -= $first_scan_bonus_value;
                        $vip_bonus_applied -= $first_scan_bonus_value;
                        $vip_bonus_details['daily'] = 0;

                        // Update the inserted record with corrected points
                        $wpdb->update(
                            "{$wpdb->prefix}ppv_points",
                            ['points' => $points_add],
                            ['id' => $points_insert_id],
                            ['%d'],
                            ['%d']
                        );

                        ppv_log("🔒 [PPV_QR] First-scan bonus REVOKED due to race condition: actual count={$actual_scan_count_for_first}");
                    }
                } else {
                    ppv_log("🎉 [PPV_QR] First-scan bonus CONFIRMED! This is scan #1 for user at this store");
                }
            }

            // Update lifetime_points for VIP level calculation
            if (class_exists('PPV_User_Level')) {
                PPV_User_Level::add_lifetime_points($user_id, $points_add);
            }

            // 🎁 REFERRAL: Check if this is user's first scan at this store via referral
            if (class_exists('PPV_Referral_Handler')) {
                // Count previous scans for this user at this store
                $previous_scans = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points WHERE user_id = %d AND store_id = %d",
                    $user_id, $store_id
                ));

                // If this is the first scan (count = 1, the one we just inserted)
                if ((int)$previous_scans === 1) {
                    $referral_result = PPV_Referral_Handler::process_referral($user_id, $store_id);
                    if ($referral_result) {
                        ppv_log("🎁 [PPV_QR] Referral processed for user {$user_id} at store {$store_id}");
                    }
                }
            }

            $wpdb->query('COMMIT');
            ppv_log("✅ [PPV_QR] Points inserted successfully: id={$points_insert_id}, user={$user_id}, store={$store_id}, points={$points_add}");

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            ppv_log("❌ [PPV_QR] Transaction failed: " . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ Transaction failed',
                'error_type' => 'transaction_error'
            ], 500);
        }

        // Build log message
        $bonus_parts = [];
        if ($vip_bonus_applied > 0) {
            $bonus_parts[] = "VIP: +{$vip_bonus_applied}";
        }
        if ($birthday_bonus_applied > 0) {
            $bonus_parts[] = "🎂 +{$birthday_bonus_applied}";
        }
        if ($comeback_bonus_applied > 0) {
            $bonus_parts[] = "👋 +{$comeback_bonus_applied}";
        }
        $log_msg = "+{$points_add} " . self::t('points', 'Punkte');
        if (!empty($bonus_parts)) {
            $log_msg .= " (" . implode(", ", $bonus_parts) . ")";
        }
        $log_id = self::insert_log($store_id, $user_id, $log_msg, 'qr_scan', null, $scanner_id, $scanner_name, $points_add);

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
                'birthday_bonus' => $birthday_bonus_applied, // 🎂 Birthday bonus
                'comeback_bonus' => $comeback_bonus_applied, // 👋 Comeback bonus
                'date_short' => date('d.m.'),
                'time_short' => date('H:i'),
                'success' => true,
                'scanner_id' => $scanner_id,       // 👤 Who scanned (employee)
                'scanner_name' => $scanner_name,   // 👤 Scanner email/name
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
                'birthday_bonus' => $birthday_bonus_applied, // 🎂 Birthday bonus
                'comeback_bonus' => $comeback_bonus_applied, // 👋 Comeback bonus
                'success' => true,
            ]);
        }

        // Build response message with bonus info
        $bonus_suffix = '';
        if ($vip_bonus_applied > 0) {
            $bonus_suffix .= " (VIP-Bonus: +{$vip_bonus_applied})";
        }
        if ($birthday_bonus_applied > 0) {
            $bonus_suffix .= " 🎂 Geburtstags-Bonus: +{$birthday_bonus_applied}";
        }
        if ($comeback_bonus_applied > 0) {
            $bonus_suffix .= " 👋 Comeback-Bonus: +{$comeback_bonus_applied}";
        }
        $success_msg = "✅ +{$points_add} " . self::t('points', 'Punkte') . $bonus_suffix;

        // ═══════════════════════════════════════════════════════════
        // 🎁 REDEMPTION PROMPT: Check if user can redeem any rewards
        // ═══════════════════════════════════════════════════════════
        $redemption_prompt = null;

        // Check if there's already a pending prompt (user chose "later" before)
        $pending_prompt = $wpdb->get_row($wpdb->prepare("
            SELECT id, token FROM {$wpdb->prefix}ppv_redemption_prompts
            WHERE user_id = %d AND store_id = %d AND status = 'pending' AND expires_at > NOW()
            ORDER BY created_at DESC LIMIT 1
        ", $user_id, $store_id));

        // Get user's current total points (after this scan)
        $user_total_points = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}ppv_points WHERE user_id = %d",
            $user_id
        ));

        // 🏪 FILIALE FIX: Get rewards from PARENT store if this is a filiale
        $reward_store_id = $store_id;
        if (class_exists('PPV_Filiale')) {
            $reward_store_id = PPV_Filiale::get_parent_id($store_id);
        }

        // Find available rewards that user can redeem
        $available_rewards = $wpdb->get_results($wpdb->prepare("
            SELECT id, title, description, required_points, action_type, action_value, free_product_value
            FROM {$wpdb->prefix}ppv_rewards
            WHERE store_id = %d AND required_points <= %d AND required_points > 0
            ORDER BY required_points DESC
        ", $reward_store_id, $user_total_points));

        if (!empty($available_rewards)) {
            // User can redeem! Create or refresh prompt
            $prompt_token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+60 seconds'));

            // Convert rewards to array for JSON storage
            $rewards_array = array_map(function($r) {
                return [
                    'id' => (int)$r->id,
                    'title' => $r->title,
                    'description' => $r->description,
                    'required_points' => (int)$r->required_points,
                    'action_type' => $r->action_type ?? 'info',
                    'action_value' => floatval($r->action_value ?? 0),
                    'free_product_value' => floatval($r->free_product_value ?? 0)
                ];
            }, $available_rewards);

            // If there's an existing pending prompt, update it; otherwise create new
            if ($pending_prompt) {
                $wpdb->update(
                    "{$wpdb->prefix}ppv_redemption_prompts",
                    [
                        'token' => $prompt_token,
                        'scanner_id' => $scanner_id,
                        'available_rewards' => json_encode($rewards_array),
                        'expires_at' => $expires_at,
                        'created_at' => current_time('mysql')
                    ],
                    ['id' => $pending_prompt->id],
                    ['%s', '%d', '%s', '%s', '%s'],
                    ['%d']
                );
                $prompt_id = $pending_prompt->id;
            } else {
                $wpdb->insert(
                    "{$wpdb->prefix}ppv_redemption_prompts",
                    [
                        'user_id' => $user_id,
                        'store_id' => $store_id,
                        'scanner_id' => $scanner_id,
                        'token' => $prompt_token,
                        'available_rewards' => json_encode($rewards_array),
                        'status' => 'pending',
                        'created_at' => current_time('mysql'),
                        'expires_at' => $expires_at
                    ],
                    ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
                );
                $prompt_id = $wpdb->insert_id;
            }

            $redemption_prompt = [
                'prompt_id' => $prompt_id,
                'token' => $prompt_token,
                'rewards' => $rewards_array,
                'user_total_points' => $user_total_points,
                'expires_at' => $expires_at,
                'timeout_seconds' => 60
            ];

            ppv_log("🎁 [PPV_QR] Redemption prompt created: user={$user_id}, store={$store_id}, rewards=" . count($rewards_array) . ", points={$user_total_points}");

            // 📡 ABLY: Send redemption prompt to user's dashboard
            if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
                PPV_Ably::trigger_redemption_prompt($user_id, [
                    'prompt_id' => $prompt_id,
                    'token' => $prompt_token,
                    'rewards' => $rewards_array,
                    'user_total_points' => $user_total_points,
                    'store_id' => $store_id,
                    'store_name' => $store_name ?? 'PunktePass',
                    'expires_at' => $expires_at,
                    'timeout_seconds' => 60
                ]);
            }
        }

        // 🔒 SECURITY: Increment successful scan counter (rate limit 3/min)
        PPV_Permissions::increment_rate_limit('pos_scan_success', 60);

        // 📊 CUSTOMER INSIGHTS: Get customer insights for Händler display
        $customer_insights = null;
        if (class_exists('PPV_Customer_Insights')) {
            $lang = sanitize_text_field($r->get_header('X-Lang') ?? 'de');
            if (!in_array($lang, ['de', 'hu', 'ro'])) {
                $lang = 'de';
            }
            $customer_insights = PPV_Customer_Insights::get_insights($user_id, $store_id, $lang);
        }

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
            // 🎁 Redemption prompt (if available)
            'redemption_prompt' => $redemption_prompt,
            // 👤 Scanner info (who performed the scan)
            'scanner_id' => $scanner_id,
            'scanner_name' => $scanner_name,
            // 📊 Customer insights for Händler (visit patterns, VIP, points)
            'customer_insights' => $customer_insights,
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
        // ✅ Include metadata for scanner info
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT
                l.id AS log_id,
                l.created_at,
                l.user_id,
                l.message,
                l.type,
                l.metadata,
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

            // Parse metadata for scanner info
            $scanner_id = null;
            $scanner_name = null;
            if (!empty($log->metadata)) {
                $meta = json_decode($log->metadata, true);
                if (is_array($meta)) {
                    $scanner_id = $meta['scanner_id'] ?? null;
                    $scanner_name = $meta['scanner_name'] ?? null;
                }
            }

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
                'scanner_id' => $scanner_id,       // 👤 Who scanned
                'scanner_name' => $scanner_name,   // 👤 Scanner email
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
                l.status,
                l.points_change,
                l.reward_code,
                l.ip_address,
                u.email,
                u.first_name,
                u.last_name
            FROM {$wpdb->prefix}ppv_pos_log l
            LEFT JOIN {$wpdb->prefix}ppv_users u ON l.user_id = u.id
            WHERE l.store_id = %d {$date_filter}
            ORDER BY l.created_at DESC
            LIMIT 1000
        ", $store_id));

        // Build CSV with BOM for Excel UTF-8 support
        $csv_lines = [];
        $csv_lines[] = 'Datum,Zeit,Kunde,Email,Punkte,Status,Prämie,IP,Nachricht';

        foreach ($logs as $log) {
            $created = strtotime($log->created_at);
            $date = date('d.m.Y', $created);
            $time = date('H:i:s', $created);

            $first = trim($log->first_name ?? '');
            $last = trim($log->last_name ?? '');
            $customer = trim("$first $last") ?: 'Unbekannt';
            $email = $log->email ?: '-';

            // Use direct points_change field
            $points = isset($log->points_change) && $log->points_change !== null
                ? intval($log->points_change)
                : '-';

            // Use status field directly
            $status = ($log->status === 'ok' || $log->status === 'success') ? 'OK' : ($log->status ?: '-');
            $reward = $log->reward_code ?: '-';
            $ip = $log->ip_address ?: '-';
            $message = str_replace([',', "\n", "\r", '"'], [';', ' ', ' ', "'"], $log->message ?? '');

            $csv_lines[] = sprintf('"%s","%s","%s","%s","%s","%s","%s","%s","%s"',
                $date, $time, $customer, $email, $points, $status, $reward, $ip, $message
            );
        }

        // Add BOM for Excel UTF-8 support (German umlauts)
        $csv_content = "\xEF\xBB\xBF" . implode("\n", $csv_lines);
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

        // 🔒 SECURITY: Max points per scan limit
        $points_given = (int)($data['points_given'] ?? 1);
        $max_points_per_scan = 20;
        if ($points_given > $max_points_per_scan) {
            return new WP_REST_Response([
                'success' => false,
                'message' => "❌ Maximum {$max_points_per_scan} Punkte pro Scan erlaubt."
            ], 400);
        }

        $fields = [
            'store_id'           => $store->id,
            'title'              => sanitize_text_field($data['title'] ?? ''),
            'start_date'         => sanitize_text_field($data['start_date'] ?? ''),
            'end_date'           => sanitize_text_field($data['end_date'] ?? ''),
            'campaign_type'      => $campaign_type,
            'required_points'    => (int)($data['required_points'] ?? 0),
            'points_given'       => $points_given,
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

        // 🏢 FILIALE SUPPORT: Check filiale_id parameter
        $filiale_param = $r->get_param('filiale_id');

        // Get all filialen for this handler
        $filialen = self::get_handler_filialen();

        // Determine which store IDs to query
        if ($filiale_param === 'all' || empty($filiale_param)) {
            // All filialen
            $store_ids = array_map(function($f) { return intval($f->id); }, $filialen);
        } else {
            // Single filiale selected - verify it belongs to handler
            $filiale_id = intval($filiale_param);
            $valid_ids = array_map(function($f) { return intval($f->id); }, $filialen);
            if (in_array($filiale_id, $valid_ids)) {
                $store_ids = [$filiale_id];
            } else {
                // Fallback to session-aware store
                $store = self::get_session_aware_store_id($store_key);
                $store_ids = $store ? [$store->id] : [];
            }
        }

        if (empty($store_ids)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Token hiányzik vagy ismeretlen bolt'
            ], 400);
        }

        // Build IN clause for multiple stores
        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT c.id, c.title, c.start_date, c.end_date, c.campaign_type, c.multiplier,
                   c.extra_points, c.discount_percent, c.min_purchase, c.fixed_amount, c.status,
                   c.required_points, c.free_product, c.free_product_value, c.points_given, c.store_id,
                   s.company_name as store_name
            FROM {$wpdb->prefix}ppv_campaigns c
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON c.store_id = s.id
            WHERE c.store_id IN ($placeholders) ORDER BY c.start_date DESC
        ", $store_ids));

        if (empty($rows)) {
            return new WP_REST_Response([], 200);
        }

        // Get country from first store (all filialen should have same country)
        $store_country = $wpdb->get_var($wpdb->prepare(
            "SELECT country FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_ids[0]
        ));

        $today = date('Y-m-d');
        $show_store_name = count($store_ids) > 1; // Show store name when viewing all filialen

        $data = array_map(function ($r) use ($today, $store_country, $show_store_name) {
            if ($today < $r->start_date) {
                $state = 'upcoming';
            } elseif ($today > $r->end_date) {
                $state = 'expired';
            } else {
                $state = 'active';
            }

            $item = [
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
                'country' => $store_country,
                'store_id' => intval($r->store_id ?? 0)
            ];

            // Add store name when showing all filialen
            if ($show_store_name && !empty($r->store_name)) {
                $item['store_name'] = $r->store_name;
            }

            return $item;
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

        // 🔒 SECURITY: Max points per scan limit
        $points_given = (int)($d['points_given'] ?? 1);
        $max_points_per_scan = 20;
        if ($points_given > $max_points_per_scan) {
            return new WP_REST_Response([
                'success' => false,
                'message' => "❌ Maximum {$max_points_per_scan} Punkte pro Scan erlaubt."
            ], 400);
        }

        $fields = [
            'title'           => sanitize_text_field($d['title'] ?? ''),
            'start_date'      => sanitize_text_field($d['start_date'] ?? ''),
            'end_date'        => sanitize_text_field($d['end_date'] ?? ''),
            'campaign_type'   => $campaign_type,
            'required_points' => (int)($d['required_points'] ?? 0),
            'points_given'    => $points_given,
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

    // ════════════════════════════════════════════════════════════════
    // 🎁 REST: REAL-TIME REDEMPTION - USER RESPONSE
    // User accepts or declines the redemption prompt
    // ════════════════════════════════════════════════════════════════
    public static function rest_redemption_user_response(WP_REST_Request $r) {
        global $wpdb;

        $data = $r->get_json_params();
        $token = sanitize_text_field($data['token'] ?? '');
        $action = sanitize_text_field($data['action'] ?? ''); // 'accept' or 'decline'
        $selected_reward_id = intval($data['reward_id'] ?? 0);

        if (empty($token) || !in_array($action, ['accept', 'decline'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_invalid_request', '❌ Ungültige Anfrage')
            ], 400);
        }

        // Get the prompt
        $prompt = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_redemption_prompts
            WHERE token = %s AND status = 'pending'
            LIMIT 1
        ", $token));

        if (!$prompt) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_prompt_expired', '⏰ Anfrage abgelaufen')
            ], 404);
        }

        // Check if expired
        if (strtotime($prompt->expires_at) < time()) {
            $wpdb->update(
                "{$wpdb->prefix}ppv_redemption_prompts",
                ['status' => 'expired'],
                ['id' => $prompt->id],
                ['%s'],
                ['%d']
            );
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_prompt_expired', '⏰ Anfrage abgelaufen')
            ], 410);
        }

        // Note: Token-based security - the token was sent via Ably only to the specific user
        // No additional user_id check needed - token validation proves user identity

        if ($action === 'decline') {
            // User chose "Later" - keep prompt for next scan
            $wpdb->update(
                "{$wpdb->prefix}ppv_redemption_prompts",
                [
                    'status' => 'user_declined',
                    'user_response_at' => current_time('mysql')
                ],
                ['id' => $prompt->id],
                ['%s', '%s'],
                ['%d']
            );

            ppv_log("🎁 [PPV_QR] User declined redemption: user={$prompt->user_id}, store={$prompt->store_id}");

            return new WP_REST_Response([
                'success' => true,
                'message' => self::t('redemption_later', '👍 Kein Problem! Beim nächsten Scan wieder.')
            ], 200);
        }

        // User accepted - validate selected reward
        $available_rewards = json_decode($prompt->available_rewards, true) ?: [];
        $valid_reward = false;
        $selected_reward = null;

        foreach ($available_rewards as $reward) {
            if ($reward['id'] == $selected_reward_id) {
                $valid_reward = true;
                $selected_reward = $reward;
                break;
            }
        }

        if (!$valid_reward) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_invalid_reward', '❌ Ungültige Prämie ausgewählt')
            ], 400);
        }

        // Update prompt status
        $wpdb->update(
            "{$wpdb->prefix}ppv_redemption_prompts",
            [
                'status' => 'user_accepted',
                'selected_reward_id' => $selected_reward_id,
                'user_response_at' => current_time('mysql')
            ],
            ['id' => $prompt->id],
            ['%s', '%d', '%s'],
            ['%d']
        );

        ppv_log("🎁 [PPV_QR] User accepted redemption: user={$prompt->user_id}, store={$prompt->store_id}, reward={$selected_reward_id}");

        // Get user info for handler notification
        $user_info = $wpdb->get_row($wpdb->prepare("
            SELECT first_name, last_name, email, avatar
            FROM {$wpdb->prefix}ppv_users WHERE id = %d
        ", $prompt->user_id));

        $customer_name = trim(($user_info->first_name ?? '') . ' ' . ($user_info->last_name ?? ''));

        // Get store name
        $store_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $prompt->store_id
        ));

        // Get user's current point balance
        $current_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}ppv_points WHERE user_id = %d",
            $prompt->user_id
        ));

        // 📡 ABLY: Notify handler about redemption request
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            PPV_Ably::trigger_redemption_request($prompt->store_id, [
                'prompt_id' => $prompt->id,
                'token' => $token,
                'user_id' => $prompt->user_id,
                'scanner_id' => $prompt->scanner_id ?? null, // Target specific scanner device
                'customer_name' => $customer_name ?: ($user_info->email ?? 'Kunde'),
                'email' => $user_info->email ?? null,
                'avatar' => $user_info->avatar ?? null,
                'reward_id' => $selected_reward_id,
                'reward_title' => $selected_reward['title'],
                'reward_description' => $selected_reward['description'] ?? '',
                'reward_points' => $selected_reward['required_points'],
                'reward_type' => $selected_reward['action_type'] ?? 'info',
                'reward_value' => floatval($selected_reward['action_value'] ?? 0),
                'free_product_value' => floatval($selected_reward['free_product_value'] ?? 0),
                'current_points' => $current_points,
                'store_name' => $store_name ?? 'PunktePass',
                'time' => date('H:i'),
            ]);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => self::t('redemption_pending', '⏳ Warte auf Bestätigung vom Händler...'),
            'status' => 'waiting_for_handler'
        ], 200);
    }

    // ════════════════════════════════════════════════════════════════
    // 🎁 REST: REAL-TIME REDEMPTION - HANDLER RESPONSE
    // Handler approves or rejects the redemption
    // ════════════════════════════════════════════════════════════════
    public static function rest_redemption_handler_response(WP_REST_Request $r) {
        global $wpdb;

        $data = $r->get_json_params();
        $token = sanitize_text_field($data['token'] ?? '');
        $action = sanitize_text_field($data['action'] ?? ''); // 'approve' or 'reject'
        $rejection_reason = sanitize_text_field($data['reason'] ?? '');
        $purchase_amount = floatval($data['purchase_amount'] ?? 0); // 🆕 For percent type rewards

        if (empty($token) || !in_array($action, ['approve', 'reject'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_invalid_request', '❌ Ungültige Anfrage')
            ], 400);
        }

        // Get the prompt
        $prompt = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_redemption_prompts
            WHERE token = %s AND status = 'user_accepted'
            LIMIT 1
        ", $token));

        if (!$prompt) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_prompt_not_found', '❌ Anfrage nicht gefunden')
            ], 404);
        }

        // Get selected reward details (including type and value for actual_amount calculation)
        $reward = $wpdb->get_row($wpdb->prepare("
            SELECT id, title, description, required_points, action_type, action_value, free_product_value
            FROM {$wpdb->prefix}ppv_rewards
            WHERE id = %d
        ", $prompt->selected_reward_id));

        if (!$reward) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_reward_not_found', '❌ Prämie nicht gefunden')
            ], 404);
        }

        // Get user info
        $user_info = $wpdb->get_row($wpdb->prepare("
            SELECT first_name, last_name, email
            FROM {$wpdb->prefix}ppv_users WHERE id = %d
        ", $prompt->user_id));

        $customer_name = trim(($user_info->first_name ?? '') . ' ' . ($user_info->last_name ?? ''));

        if ($action === 'reject') {
            // Handler rejected
            $wpdb->update(
                "{$wpdb->prefix}ppv_redemption_prompts",
                [
                    'status' => 'handler_rejected',
                    'handler_response_at' => current_time('mysql'),
                    'rejection_reason' => $rejection_reason
                ],
                ['id' => $prompt->id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            ppv_log("🎁 [PPV_QR] Handler rejected redemption: user={$prompt->user_id}, store={$prompt->store_id}, reason={$rejection_reason}");

            // 📡 ABLY: Notify user about rejection
            if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
                PPV_Ably::trigger_redemption_rejected($prompt->user_id, [
                    'reward_title' => $reward->title,
                    'reason' => $rejection_reason ?: self::t('rejection_default', 'Die Prämie ist derzeit nicht verfügbar'),
                ]);

                // 📡 Notify other handlers that this redemption was handled (close their modals)
                PPV_Ably::trigger_redemption_handled($prompt->store_id, [
                    'token' => $token,
                    'action' => 'rejected'
                ]);
            }

            return new WP_REST_Response([
                'success' => true,
                'message' => '❌ Einlösung abgelehnt',
                'customer_name' => $customer_name
            ], 200);
        }

        // Handler approved - process the redemption
        $wpdb->query('START TRANSACTION');

        try {
            // 1. Verify user still has enough points
            $user_points = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}ppv_points WHERE user_id = %d FOR UPDATE",
                $prompt->user_id
            ));

            if ($user_points < $reward->required_points) {
                $wpdb->query('ROLLBACK');
                return new WP_REST_Response([
                    'success' => false,
                    'message' => self::t('err_not_enough_points', '❌ Nicht genügend Punkte')
                ], 400);
            }

            // 2. Deduct points
            $wpdb->insert("{$wpdb->prefix}ppv_points", [
                'user_id' => $prompt->user_id,
                'store_id' => $prompt->store_id,
                'points' => -$reward->required_points,
                'type' => 'redemption',
                'created' => current_time('mysql')
            ]);

            // 🆕 3. Calculate actual_amount based on reward type
            $actual_amount = null;
            $action_type = $reward->action_type ?? 'info';
            $action_value = floatval($reward->action_value ?? 0);
            $free_product_value = floatval($reward->free_product_value ?? 0);

            switch ($action_type) {
                case 'percent':
                    // % rabatt: actual_amount = purchase_amount * (action_value / 100)
                    if ($purchase_amount > 0 && $action_value > 0) {
                        $actual_amount = round($purchase_amount * ($action_value / 100), 2);
                        ppv_log("💰 [PPV_QR] Percent discount: {$purchase_amount}€ × {$action_value}% = {$actual_amount}€");
                    }
                    break;

                case 'fixed':
                    // Fix rabatt: actual_amount = action_value
                    if ($action_value > 0) {
                        $actual_amount = $action_value;
                        ppv_log("💰 [PPV_QR] Fixed discount: {$actual_amount}€");
                    }
                    break;

                case 'free_product':
                    // Gratis termék: actual_amount = free_product_value
                    if ($free_product_value > 0) {
                        $actual_amount = $free_product_value;
                        ppv_log("💰 [PPV_QR] Free product value: {$actual_amount}€");
                    }
                    break;

                default:
                    // info/points típus: nincs konkrét euró érték
                    ppv_log("💰 [PPV_QR] No actual_amount for type: {$action_type}");
                    break;
            }

            // 4. Create redemption record with actual_amount
            $redemption_data = [
                'user_id' => $prompt->user_id,
                'store_id' => $prompt->store_id,
                'reward_id' => $reward->id,
                'points_spent' => $reward->required_points,
                'status' => 'approved',
                'redeemed_at' => current_time('mysql')
            ];

            // Add actual_amount if calculated
            if ($actual_amount !== null) {
                $redemption_data['actual_amount'] = $actual_amount;
            }

            $wpdb->insert("{$wpdb->prefix}ppv_rewards_redeemed", $redemption_data);

            // 4. Update prompt status
            $wpdb->update(
                "{$wpdb->prefix}ppv_redemption_prompts",
                [
                    'status' => 'completed',
                    'handler_response_at' => current_time('mysql')
                ],
                ['id' => $prompt->id],
                ['%s', '%s'],
                ['%d']
            );

            $wpdb->query('COMMIT');

            $amount_log = $actual_amount !== null ? ", actual_amount={$actual_amount}€" : '';
            ppv_log("🎁 [PPV_QR] Handler approved redemption: user={$prompt->user_id}, store={$prompt->store_id}, reward={$reward->id}, points=-{$reward->required_points}{$amount_log}");

            // 📡 ABLY: Notify user about approval
            if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
                // Get new point balance
                $new_balance = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}ppv_points WHERE user_id = %d",
                    $prompt->user_id
                ));

                PPV_Ably::trigger_redemption_approved($prompt->user_id, [
                    'reward_title' => $reward->title,
                    'points_spent' => $reward->required_points,
                    'new_balance' => $new_balance,
                    'actual_amount' => $actual_amount,
                ]);

                // 📡 Notify other handlers that this redemption was handled (close their modals)
                PPV_Ably::trigger_redemption_handled($prompt->store_id, [
                    'token' => $token,
                    'action' => 'approved'
                ]);
            }

            return new WP_REST_Response([
                'success' => true,
                'message' => '✅ Einlösung bestätigt!',
                'customer_name' => $customer_name,
                'reward_title' => $reward->title,
                'points_spent' => $reward->required_points,
                'actual_amount' => $actual_amount
            ], 200);

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            ppv_log("❌ [PPV_QR] Redemption error: " . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ Fehler bei der Einlösung'
            ], 500);
        }
    }

    // ============================================================
    // 🔒 HELPER: Get client IP address
    // ============================================================
    private static function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',   // Proxy/Load balancer
            'HTTP_X_REAL_IP',         // Nginx proxy
            'REMOTE_ADDR'             // Direct connection
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated list (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
