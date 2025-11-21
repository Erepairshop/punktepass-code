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

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id = intval($_SESSION['ppv_current_filiale_id']);
        } else {
            $store_id = intval($store->id);
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

        /** 7) Mentés */
        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id'   => $user_id,
            'store_id'  => $store_id,
            'points'    => $points_add,
            'type'      => 'qr_scan',
            'reference' => 'POS Scan',
            'created'   => current_time('mysql'),
        ]);

        /** 8) Teljes pont */
        $total_points = (int)$wpdb->get_var($wpdb->prepare("
            SELECT SUM(points) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d
        ", $user_id));

        /** 9) Log */
        $ip_address_raw = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address_raw)[0]);


        self::log_scan_attempt($store_id, $user_id, $ip_address, 'ok', "✅ +{$points_add} Punkte", 'scan_success', $points_add, $lang);

        /** 10) Üzenet */
        $msg = [
            'hu' => "✅ +{$points_add} pont hozzáadva",
            'ro' => "✅ +{$points_add} puncte adăugate",
            'de' => "✅ +{$points_add} Punkte hinzugefügt",
        ][$lang] ?? "✅ +{$points_add} Punkte";

        $response = [
            'success'    => true,
            'message'    => $msg,
            'user_id'    => $user_id,
            'store_id'   => $store_id,
            'store_name' => $store->name ?? 'PunktePass',
            'points'     => $points_add,
            'total'      => $total_points
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

        // Convert to CSV format
        $output = fopen('php://temp', 'w');
        foreach ($csv as $row) {
            fputcsv($output, $row, ',', '"');
        }
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        // Filename
        $filename = "pos_logs_{$period}_{$date}.csv";

        // Return CSV response
        return new WP_REST_Response($csv_content, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, must-revalidate',
            'Expires' => '0'
        ]);
    }
}

add_action('plugins_loaded', ['PPV_POS_SCAN', 'hooks'], 5);
