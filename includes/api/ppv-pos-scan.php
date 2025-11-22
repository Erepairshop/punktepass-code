<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – POS Scan API v4.7 (Ultra Fixed)
 * ✅ entity_type=user filter
 * ✅ Händler QR kizárva
 * ✅ POS token safe
 * ✅ Nincs több 3836 keveredés
 * ✅ Napi limit + duplikált scan
 */

class PPV_POS_SCAN {

    public static function hooks() {
        add_filter('rest_authentication_errors', [__CLASS__, 'allow_pos_requests'], 5);
        add_action('rest_api_init', [__CLASS__, 'register_routes'], 10);
    }

    public static function allow_pos_requests($result) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/ppv/v1/pos/scan') !== false) {
            return true;
        }
        return $result;
    }

    public static function permission_with_logging() {

        $result = PPV_Permissions::check_handler();

        if (is_wp_error($result)) {
            // Return valid JSON error instead of WP_Error
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                ['status' => $result->get_error_data()['status'] ?? 403]
            );
        }

        return true;
    }

    public static function register_routes() {
        register_rest_route('ppv/v1', '/pos/scan', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_scan'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('ppv/v1', '/pos/recent-scans', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_recent_scans'],
            'permission_callback' => [__CLASS__, 'permission_with_logging'],
        ]);

        register_rest_route('ppv/v1', '/pos/export-logs', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'export_logs_csv'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);
    }

    public static function handle_scan(WP_REST_Request $req) {
        global $wpdb;


        $p = $req->get_json_params() ?: [];
        $qr        = sanitize_text_field($p['qr'] ?? '');
        $store_key = sanitize_text_field($p['store_key'] ?? '');
        $lang      = sanitize_text_field($p['lang'] ?? 'de');


        if ($qr === '') {
            return rest_ensure_response(['success' => false, 'message' => '❌ Kein QR-Code empfangen']);
        }

        /** 1) POS Store detektálás */
        $store = null;

        // POS session cookie
        if (!empty($_COOKIE['ppv_pos_token'])) {
            $token = sanitize_text_field($_COOKIE['ppv_pos_token']);
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ppv_stores
                WHERE pos_token=%s AND pos_enabled=1
                LIMIT 1
            ", $token));
        }

        // REST paraméter
        if (!$store && !empty($store_key)) {
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ppv_stores
                WHERE store_key=%s OR pos_token=%s
                LIMIT 1
            ", $store_key, $store_key));
        }

        if (!$store) {
            return rest_ensure_response([
                'success' => false,
                'message' => '❌ Kein aktiver POS-Store'
            ]);
        }

        // 🏪 FILIALE SUPPORT: Check session for FILIALE override
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 🔍 DEBUG: Log session and store status
        error_log("🔍 [POS_SCAN] Debug info: " . json_encode([
            'session_ppv_current_filiale_id' => $_SESSION['ppv_current_filiale_id'] ?? 'NOT_SET',
            'session_ppv_store_id' => $_SESSION['ppv_store_id'] ?? 'NOT_SET',
            'validated_store_id' => $store->id ?? 'NULL',
            'validated_store_name' => $store->name ?? 'NULL',
        ]));

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id = intval($_SESSION['ppv_current_filiale_id']);
            error_log("✅ [POS_SCAN] Using FILIALE store_id: {$store_id}");
        } else {
            $store_id = intval($store->id);
            error_log("⚠️ [POS_SCAN] No FILIALE in session, using validated store_id: {$store_id}");
        }

        if ($store_id === 0) {
            error_log("❌ [POS_SCAN] CRITICAL: store_id is 0! This should not happen!");
            return rest_ensure_response([
                'success' => false,
                'message' => '❌ Invalid store_id (0)'
            ]);
        }

        /** 2) IP Rate Limiting (100 scans per minute for debugging) */
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address)[0]);

        $recent_scans_from_ip = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pos_log
            WHERE ip_address=%s AND created_at >= (NOW() - INTERVAL 1 MINUTE)
        ", $ip_address));

        // DEBUG: Log rate limiting check

        if ($recent_scans_from_ip >= 100) {
            self::log_event($store_id, 'rate_limit', "🚫 Rate limit exceeded from IP {$ip_address}", 'blocked');
            return rest_ensure_response([
                'success' => false,
                'message' => '🚫 Zu viele Anfragen. Bitte warten Sie.',
                'store_name' => $store->name ?? 'PunktePass',
                'error_type' => 'rate_limit'
            ]);
        }


        /** 3) QR -> User ID extraction with security
         *    IMPORTANT: Only USER tokens allowed
         *    Format: PPU{user_id}{16-char-token}
         */
        $user_id = 0;

        if (strpos($qr, 'PPU') === 0) {
            $payload = substr($qr, 3);

            // Find where user_id ends (first non-digit)
            if (preg_match('/^(\d+)(.+)$/', $payload, $matches)) {
                $uid = intval($matches[1]);
                $token_from_qr = $matches[2];

                // Security: Verify token + active status in single JOIN query
                $user_check = $wpdb->get_row($wpdb->prepare("
                    SELECT u.id, u.active
                    FROM {$wpdb->prefix}ppv_users u
                    INNER JOIN {$wpdb->prefix}ppv_tokens t
                        ON t.entity_type='user' AND t.entity_id=u.id
                    WHERE u.id=%d
                        AND t.token=%s
                        AND t.expires_at > NOW()
                    LIMIT 1
                ", $uid, $token_from_qr));

                if ($user_check && $user_check->active == 1) {
                    $user_id = intval($user_check->id);
                } elseif ($user_check && $user_check->active == 0) {
                    self::log_scan_attempt($store_id, $uid, $ip_address, 'blocked', 'User inactive', 'user_blocked', 0, $lang);
                    return rest_ensure_response([
                        'success' => false,
                        'message' => '🚫 Benutzer gesperrt',
                        'store_name' => $store->name ?? 'PunktePass',
                        'error_type' => 'user_blocked'
                    ]);
                }
            }
        }

        if ($user_id <= 0) {
            self::log_scan_attempt($store_id, 0, $ip_address, 'invalid', 'Invalid QR code', 'invalid_qr', 0, $lang);
            return rest_ensure_response([
                'success' => false,
                'message' => '🚫 Ungültiger QR-Code (kein User)',
                'store_name' => $store->name ?? 'PunktePass',
                'error_type' => 'invalid_qr'
            ]);
        }

        /** 4) Napi limit */
        $already_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
            WHERE user_id=%d AND store_id=%d
            AND DATE(created)=CURDATE()
            AND type='qr_scan'
        ", $user_id, $store_id));

        if ($already_today > 0) {
            return rest_ensure_response([
                'success' => false,
                'message' => '⚠️ Heute bereits gescannt',
                'store_name' => $store->name ?? 'PunktePass',
                'error_type' => 'already_scanned_today'
            ]);
        }

        /** 5) Duplikált scan (2 perc) */
        // FIXED: Check wp_ppv_points instead of wp_ppv_pos_log
        // Log table contains ALL attempts (successful and failed), causing false positives
        $recent = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}ppv_points
            WHERE user_id=%d AND store_id=%d
            AND created >= (NOW() - INTERVAL 2 MINUTE)
            AND type='qr_scan'
        ", $user_id, $store_id));

        if ($recent) {
            return rest_ensure_response([
                'success' => false,
                'message' => '⚠️ Bereits gescannt',
                'store_name' => $store->name ?? 'PunktePass',
                'error_type' => 'duplicate_scan'
            ]);
        }

        /** 6) Bónusz nap */
        $points_add = 1;

        $bonus = $wpdb->get_row($wpdb->prepare("
            SELECT multiplier, extra_points FROM {$wpdb->prefix}ppv_bonus_days
            WHERE store_id=%d AND date=%s AND active=1
        ", $store_id, date('Y-m-d')));

        if ($bonus) {
            $points_add = (int)round(($points_add * (float)$bonus->multiplier) + (int)$bonus->extra_points);
        }

        /** 6.5) VIP Level Bonuses (Extended: 4 types) */
        $vip_bonus_details = [
            'pct' => 0,      // 1. Percentage bonus
            'fix' => 0,      // 2. Fixed point bonus
            'streak' => 0,   // 3. Every Xth scan bonus
            'daily' => 0,    // 4. First daily scan bonus
        ];
        $vip_bonus_applied = 0;

        if (class_exists('PPV_User_Level')) {
            // 🏪 FILIALE FIX: Get VIP settings from PARENT store if this is a filiale
            // This ensures all branches inherit VIP settings from the main store
            $vip_store_id = $store_id;
            if (class_exists('PPV_Filiale')) {
                $vip_store_id = PPV_Filiale::get_parent_id($store_id);
                if ($vip_store_id !== $store_id) {
                    error_log("🏪 [POS_SCAN] VIP settings: Using PARENT store {$vip_store_id} instead of filiale {$store_id}");
                }
            }

            // Get all VIP settings for this store (or parent store) - including Bronze columns
            $vip_settings = $wpdb->get_row($wpdb->prepare("
                SELECT
                    vip_enabled, vip_bronze_bonus, vip_silver_bonus, vip_gold_bonus, vip_platinum_bonus,
                    vip_fix_enabled, vip_fix_bronze, vip_fix_silver, vip_fix_gold, vip_fix_platinum,
                    vip_streak_enabled, vip_streak_count, vip_streak_type,
                    vip_streak_bronze, vip_streak_silver, vip_streak_gold, vip_streak_platinum,
                    vip_daily_enabled, vip_daily_bronze, vip_daily_silver, vip_daily_gold, vip_daily_platinum
                FROM {$wpdb->prefix}ppv_stores WHERE id = %d
            ", $vip_store_id));

            if ($vip_settings) {
                // Check if user has VIP status (Bronze or higher = 100+ lifetime points)
                // Starter users (0-99 points) do NOT get VIP bonuses
                $user_level = PPV_User_Level::get_vip_level_for_bonus($user_id);
                $base_points = $points_add; // Store original points for calculations

                // Helper to get level-specific value (returns 0 for Starter/null)
                $getLevelValue = function($bronze, $silver, $gold, $platinum) use ($user_level) {
                    if ($user_level === null) return 0; // Starter = no VIP bonus
                    switch ($user_level) {
                        case 'bronze': return intval($bronze);
                        case 'silver': return intval($silver);
                        case 'gold': return intval($gold);
                        case 'platinum': return intval($platinum);
                        default: return 0;
                    }
                };

                // ═══════════════════════════════════════════════════════════
                // 1. PERCENTAGE BONUS
                // ═══════════════════════════════════════════════════════════
                if ($vip_settings->vip_enabled && $user_level !== null) {
                    $bonus_percent = $getLevelValue(
                        $vip_settings->vip_bronze_bonus ?? 3,
                        $vip_settings->vip_silver_bonus,
                        $vip_settings->vip_gold_bonus,
                        $vip_settings->vip_platinum_bonus
                    );
                    if ($bonus_percent > 0) {
                        $vip_bonus_details['pct'] = (int)round($base_points * ($bonus_percent / 100));
                    }
                }

                // ═══════════════════════════════════════════════════════════
                // 2. FIXED POINT BONUS
                // ═══════════════════════════════════════════════════════════
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

                // ═══════════════════════════════════════════════════════════
                // 3. EVERY Xth SCAN BONUS (Streak)
                // ═══════════════════════════════════════════════════════════
                if ($vip_settings->vip_streak_enabled && $user_level !== null) {
                    $streak_count = intval($vip_settings->vip_streak_count);
                    if ($streak_count > 0) {
                        // Count user's total scans at this store
                        $user_scan_count = (int)$wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
                            WHERE user_id = %d AND store_id = %d AND type = 'qr_scan'
                        ", $user_id, $store_id));

                        // Check if this scan is the Xth (next scan will be user_scan_count + 1)
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
                                $vip_bonus_details['streak'] = $base_points; // +100%
                            } elseif ($streak_type === 'triple') {
                                $vip_bonus_details['streak'] = $base_points * 2; // +200%
                            }

                            error_log("🔥 [POS_SCAN] Streak bonus triggered! Scan #{$next_scan_number} (every {$streak_count})");
                        }
                    }
                }

                // ═══════════════════════════════════════════════════════════
                // 4. FIRST DAILY SCAN BONUS
                // ═══════════════════════════════════════════════════════════
                if ($vip_settings->vip_daily_enabled && $user_level !== null) {
                    // Check if user already scanned at this store today
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
                        error_log("☀️ [POS_SCAN] First daily scan bonus applied for user {$user_id}");
                    }
                }

                // Calculate total VIP bonus
                $vip_bonus_applied = $vip_bonus_details['pct'] + $vip_bonus_details['fix'] + $vip_bonus_details['streak'] + $vip_bonus_details['daily'];

                if ($vip_bonus_applied > 0) {
                    $points_add += $vip_bonus_applied;
                    error_log("✅ [POS_SCAN] VIP bonuses applied: level={$user_level}, pct=+{$vip_bonus_details['pct']}, fix=+{$vip_bonus_details['fix']}, streak=+{$vip_bonus_details['streak']}, daily=+{$vip_bonus_details['daily']}, total_bonus={$vip_bonus_applied}, total_points={$points_add}");
                }
            }
        }

        /** 7) Mentés */
        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id'   => $user_id,
            'store_id'  => $store_id,
            'points'    => $points_add,
            'type'      => 'qr_scan',
            'reference' => 'POS Scan',
            'created'   => current_time('mysql'),
        ]);

        /** 7.5) Update lifetime_points (for VIP level calculation) */
        if (class_exists('PPV_User_Level') && $points_add > 0) {
            PPV_User_Level::add_lifetime_points($user_id, $points_add);
        }

        /** 8) Teljes pont */
        $total_points = (int)$wpdb->get_var($wpdb->prepare("
            SELECT SUM(points) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d
        ", $user_id));

        /** 9) Log */
        $ip_address_raw = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address_raw)[0]);


        self::log_scan_attempt($store_id, $user_id, $ip_address, 'ok', "✅ +{$points_add} Punkte", 'scan_success', $points_add, $lang);

        /** 10) Üzenet */
        // Build message with VIP bonus info if applicable
        $vip_suffix = '';
        if ($vip_bonus_applied > 0) {
            $vip_suffix = [
                'hu' => " (VIP bónusz: +{$vip_bonus_applied})",
                'ro' => " (bonus VIP: +{$vip_bonus_applied})",
                'de' => " (VIP-Bonus: +{$vip_bonus_applied})",
            ][$lang] ?? " (VIP-Bonus: +{$vip_bonus_applied})";
        }

        $msg = [
            'hu' => "✅ +{$points_add} pont hozzáadva{$vip_suffix}",
            'ro' => "✅ +{$points_add} puncte adăugate{$vip_suffix}",
            'de' => "✅ +{$points_add} Punkte hinzugefügt{$vip_suffix}",
        ][$lang] ?? "✅ +{$points_add} Punkte{$vip_suffix}";

        $response = [
            'success'    => true,
            'message'    => $msg,
            'user_id'    => $user_id,
            'store_id'   => $store_id,
            'store_name' => $store->name ?? 'PunktePass',
            'points'     => $points_add,
            'total'      => $total_points,
            'vip_bonus'  => $vip_bonus_applied,
            'vip_bonus_details' => $vip_bonus_details,
        ];


        return rest_ensure_response($response);
    }

    private static function log_event($store_id, $action, $message, $status = 'ok') {
        global $wpdb;
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address)[0]);
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $wpdb->insert("{$wpdb->prefix}ppv_pos_log", [
            'store_id'   => intval($store_id),
            'message'    => sanitize_textarea_field($message),
            'status'     => sanitize_text_field($status),
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'created_at' => current_time('mysql'),
        ]);
    }

    private static function log_scan_attempt($store_id, $user_id, $ip_address, $status, $reason, $message_key = null, $points = 0, $user_lang = 'de') {
        global $wpdb;
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');


        // Store message_key, points, AND user_lang in metadata for translation
        $metadata = json_encode([
            'message_key' => $message_key,
            'points' => $points,
            'user_lang' => $user_lang,  // ✅ Store user's language
            'timestamp' => current_time('mysql')
        ]);

        $result = $wpdb->insert("{$wpdb->prefix}ppv_pos_log", [
            'store_id'   => intval($store_id),
            'user_id'    => intval($user_id),
            'message'    => sanitize_text_field($reason),
            'status'     => sanitize_text_field($status),
            'ip_address' => sanitize_text_field($ip_address),
            'user_agent' => $user_agent,
            'metadata'   => $metadata,
            'created_at' => current_time('mysql'),
        ]);

        if ($result === false) {
        } else {
        }
    }

    /** ============================================================
     * 📋 Get Recent Scans (for live table refresh)
     * ✅ Shows BOTH successful scans AND errors
     * ============================================================ */
    public static function get_recent_scans(WP_REST_Request $req) {
        global $wpdb;

        try {

            // Get store_id from session
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
            $store_id = 0;
            if (!empty($_SESSION['ppv_current_filiale_id'])) {
                $store_id = intval($_SESSION['ppv_current_filiale_id']);
            } elseif (!empty($_SESSION['ppv_store_id'])) {
                $store_id = intval($_SESSION['ppv_store_id']);
            } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
                $store_id = intval($_SESSION['ppv_vendor_store_id']);
            }

            if ($store_id === 0) {
                return rest_ensure_response([
                    'success' => false,
                    'message' => 'No store_id in session'
                ]);
            }

            // ✅ Get handler language from cookie or session
            $handler_lang = $_COOKIE['ppv_lang'] ?? $_SESSION['ppv_lang'] ?? 'de';

            // ✅ Load translations from PPV_Lang (same as used everywhere else)
            // Check if PPV_Lang class exists
            if (!class_exists('PPV_Lang')) {
                return rest_ensure_response([
                    'success' => false,
                    'message' => 'Translation system not loaded'
                ]);
            }

            // Load the correct language file
            PPV_Lang::load($handler_lang);
            $translations = PPV_Lang::$strings;

            // Verify translations loaded correctly
            if (!is_array($translations)) {
                $translations = []; // Fallback to empty array
            }

            // ✅ Get last 15 scan attempts (successful + errors) from pos_log
            $logs = $wpdb->get_results($wpdb->prepare("
                SELECT
                    l.created_at,
                    l.user_id,
                    l.message,
                    l.status,
                    l.metadata,
                    u.email,
                    CONCAT(u.first_name, ' ', u.last_name) as name
                FROM {$wpdb->prefix}ppv_pos_log l
                LEFT JOIN {$wpdb->prefix}ppv_users u ON l.user_id = u.id
                WHERE l.store_id = %d
                ORDER BY l.created_at DESC
                LIMIT 15
            ", $store_id));


            $formatted = [];
            foreach ($logs as $log) {
                // ✅ Show DATE + TIME (not just time)
                $time = date('Y-m-d H:i:s', strtotime($log->created_at));

                // User display (name or email)
                if ($log->user_id > 0) {
                    $user_display = !empty($log->name) && trim($log->name) !== '' ? $log->name : $log->email;
                } else {
                    $user_display = '—'; // No user for errors like rate_limit
                }

                // ✅ TRANSLATE status message based on HANDLER's language (consistent UI)
                $status = $log->message; // Fallback to original message

                // Try to extract message_key from metadata
                if (!empty($log->metadata)) {
                    $metadata = json_decode($log->metadata, true);
                    $message_key = $metadata['message_key'] ?? null;
                    $points = $metadata['points'] ?? 0;

                    // Use HANDLER's language translations (already loaded above)
                    if ($message_key && isset($translations[$message_key])) {
                        $status = $translations[$message_key];
                        // Replace {points} placeholder
                        $status = str_replace('{points}', $points, $status);
                    }
                }

                // If no translation found, fallback to icon prefix based on status
                if ($status === $log->message) {
                    if ($log->status === 'ok') {
                        $status = $log->message; // Keep original if no translation
                    } elseif ($log->status === 'blocked' || $log->status === 'invalid') {
                        $status = '❌ ' . $log->message;
                    } else {
                        $status = '⚠️ ' . $log->message;
                    }
                }

                $formatted[] = [
                    'time' => $time,
                    'user' => $user_display,
                    'status' => $status
                ];
            }


            return rest_ensure_response([
                'success' => true,
                'scans' => $formatted
            ]);
        } catch (Exception $e) {
            return rest_ensure_response([
                'success' => false,
                'message' => 'Internal error: ' . $e->getMessage()
            ]);
        }
    }

    /** ============================================================
     * 📥 Export Logs as CSV
     * Query params: ?period=today|date|month&date=2025-01-20
     * ============================================================ */
    public static function export_logs_csv(WP_REST_Request $req) {
        global $wpdb;

        // Get store_id from session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        $store_id = 0;
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id = intval($_SESSION['ppv_current_filiale_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            $store_id = intval($_SESSION['ppv_vendor_store_id']);
        }

        if ($store_id === 0) {
            return new WP_Error('no_store', 'No store_id in session', ['status' => 403]);
        }

        // Get handler language
        $handler_lang = $_COOKIE['ppv_lang'] ?? $_SESSION['ppv_lang'] ?? 'de';

        // ✅ Load translations from PPV_Lang (same as used everywhere else)
        // Check if PPV_Lang class exists
        if (!class_exists('PPV_Lang')) {
            return new WP_Error('translation_error', 'Translation system not loaded', ['status' => 500]);
        }

        // Load the correct language file
        PPV_Lang::load($handler_lang);
        $t = PPV_Lang::$strings;

        // Verify translations loaded correctly
        if (!is_array($t)) {
            return new WP_Error('translation_error', 'Translations failed to load', ['status' => 500]);
        }

        // Get filter parameters
        $period = $req->get_param('period') ?? 'today'; // today, date, month
        $date = $req->get_param('date') ?? date('Y-m-d');

        // Build WHERE clause based on period
        $where_clause = "l.store_id = %d";
        $params = [$store_id];

        if ($period === 'today') {
            $where_clause .= " AND DATE(l.created_at) = CURDATE()";
        } elseif ($period === 'date') {
            $where_clause .= " AND DATE(l.created_at) = %s";
            $params[] = $date;
        } elseif ($period === 'month') {
            $month = substr($date, 0, 7); // 2025-01
            $where_clause .= " AND DATE_FORMAT(l.created_at, '%Y-%m') = %s";
            $params[] = $month;
        }

        // Query logs
        $query = $wpdb->prepare("
            SELECT
                l.created_at,
                l.user_id,
                l.message,
                l.status,
                l.metadata,
                l.ip_address,
                u.email,
                CONCAT(u.first_name, ' ', u.last_name) as name
            FROM {$wpdb->prefix}ppv_pos_log l
            LEFT JOIN {$wpdb->prefix}ppv_users u ON l.user_id = u.id
            WHERE {$where_clause}
            ORDER BY l.created_at DESC
        ", ...$params);

        $logs = $wpdb->get_results($query);

        // Generate CSV content
        $csv = [];

        // CSV Header
        $csv[] = [
            $t['csv_header_time'],
            $t['csv_header_user'],
            $t['csv_header_email'],
            $t['csv_header_status'],
            $t['csv_header_ip']
        ];

        // CSV Rows
        foreach ($logs as $log) {
            $user_display = !empty($log->name) && trim($log->name) !== '' ? $log->name : '—';
            $email = $log->email ?? '—';

            // Translate status
            $status = $log->message;
            if (!empty($log->metadata)) {
                $metadata = json_decode($log->metadata, true);
                $message_key = $metadata['message_key'] ?? null;
                $points = $metadata['points'] ?? 0;

                if ($message_key && isset($t[$message_key])) {
                    $status = str_replace('{points}', $points, $t[$message_key]);
                }
            }

            $csv[] = [
                $log->created_at,
                $user_display,
                $email,
                $status,
                $log->ip_address ?? '—'
            ];
        }

        // Convert to CSV format with proper encoding
        $output = fopen('php://temp', 'w');

        // Add BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");

        // Use semicolon as delimiter for better Excel compatibility (European)
        foreach ($csv as $row) {
            fputcsv($output, $row, ';', '"');
        }
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        // Filename
        $filename = "pos_logs_{$period}_{$date}.csv";

        // Send headers directly and output CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        header('Pragma: public');

        echo $csv_content;
        exit;
    }
}

add_action('plugins_loaded', ['PPV_POS_SCAN', 'hooks'], 5);
