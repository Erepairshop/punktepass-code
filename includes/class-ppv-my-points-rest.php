<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Meine Punkte REST API v3 (Multilang + Debug)
 * PPV_Lang::t() kompatibilis verzió – ugyanúgy, mint a User Dashboard
 */

class PPV_My_Points_REST {

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /** 🔹 REST útvonal regisztrálása */
    public static function register_routes() {
        ppv_log("🧠 [PPV_MyPoints_REST] register_rest_route aktiválva");

        register_rest_route('ppv/v1', '/mypoints', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_points'],
            'permission_callback' => [__CLASS__, 'check_mypoints_permission'],  // ✅ SAJÁT PERMISSION CALLBACK, mint Belohnungen!
        ]);
    }

    /** 🔹 Permission Check - Session alapú, NEM WordPress nonce! */
    public static function check_mypoints_permission($request) {
        ppv_log("🔍 [PPV_MyPoints_REST::check_mypoints_permission] ========== START ==========");

        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
            ppv_log("🔍 [check_mypoints_permission] Session started");
        }

        // WordPress user check
        if (is_user_logged_in()) {
            $wp_user_id = get_current_user_id();
            ppv_log("✅ [check_mypoints_permission] WordPress user logged in: {$wp_user_id}");
            return true;
        }

        // Session user check (Google/Facebook/TikTok login)
        if (!empty($_SESSION['ppv_user_id'])) {
            $session_user_id = intval($_SESSION['ppv_user_id']);
            ppv_log("✅ [check_mypoints_permission] Session user found: {$session_user_id}");
            return true;
        }

        // Try to restore from cookie token
        if (class_exists('PPV_SessionBridge')) {
            ppv_log("🔄 [check_mypoints_permission] No session user - trying PPV_SessionBridge::restore_from_token()");
            PPV_SessionBridge::restore_from_token();

            if (!empty($_SESSION['ppv_user_id'])) {
                $restored_user_id = intval($_SESSION['ppv_user_id']);
                ppv_log("✅ [check_mypoints_permission] Session restored from token: {$restored_user_id}");
                return true;
            } else {
                ppv_log("❌ [check_mypoints_permission] Session restore failed - still no ppv_user_id");
            }
        }

        ppv_log("❌ [check_mypoints_permission] UNAUTHORIZED - No user found");
        ppv_log("    - WP user: " . (is_user_logged_in() ? get_current_user_id() : 'NOT LOGGED IN'));
        ppv_log("    - SESSION ppv_user_id: " . ($_SESSION['ppv_user_id'] ?? 'NOT SET'));
        ppv_log("    - COOKIE ppv_user_token: " . (isset($_COOKIE['ppv_user_token']) ? 'EXISTS' : 'NOT SET'));
        return new WP_Error('unauthorized', 'Nicht angemeldet', ['status' => 401]);
    }

    /** 🔹 Adatok visszaadása JSON-ban */
    public static function rest_get_points($request) {
        global $wpdb;
        $start = microtime(true);

        ppv_log("🔍 [PPV_MyPoints_REST::rest_get_points] ========== START ==========");
        ppv_log("🔍 [PPV_MyPoints_REST] Request URL: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        ppv_log("🔍 [PPV_MyPoints_REST] Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));

        // Log all headers
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        ppv_log("🔍 [PPV_MyPoints_REST] Request Headers:");
        foreach ($headers as $key => $value) {
            if (stripos($key, 'auth') !== false || stripos($key, 'ppv') !== false || stripos($key, 'nonce') !== false) {
                $safe_value = substr($value, 0, 20) . '...';
                ppv_log("    - {$key}: {$safe_value}");
            }
        }

        // 🔹 Force language reload (Cookie / Session / Header alapján)
if (class_exists('PPV_Lang')) {
    if (session_status() === PHP_SESSION_NONE) @session_start();

    $cookie_lang  = $_COOKIE['ppv_lang'] ?? '';
    $session_lang = $_SESSION['ppv_lang'] ?? '';
    $header_lang  = $_SERVER['HTTP_X_PPV_LANG'] ?? '';

    ppv_log("🔍 [PPV_MyPoints_REST] Lang detection:");
    ppv_log("    - Cookie: " . ($cookie_lang ?: 'EMPTY'));
    ppv_log("    - Session: " . ($session_lang ?: 'EMPTY'));
    ppv_log("    - Header: " . ($header_lang ?: 'EMPTY'));

    $lang = $header_lang ?: ($cookie_lang ?: $session_lang ?: 'de');

    if (!empty($lang) && in_array($lang, ['de','hu','ro'], true)) {
        PPV_Lang::load($lang);
        PPV_Lang::$active = $lang;
        ppv_log("🌍 [PPV_MyPoints_REST] Lang forced before response → {$lang}");
    } else {
        ppv_log("⚠️ [PPV_MyPoints_REST] No valid lang detected, fallback → de");
    }
}

        // ✅ SAME AS USER DASHBOARD - Simple user detection
        // Start session if not started yet
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
            ppv_log("🔍 [PPV_MyPoints_REST] Session started");
        } else {
            ppv_log("🔍 [PPV_MyPoints_REST] Session already active, SID: " . session_id());
        }

        ppv_log("🔍 [PPV_MyPoints_REST] Session data BEFORE restore:");
        ppv_log("    - ppv_user_id: " . ($_SESSION['ppv_user_id'] ?? 'NOT SET'));
        ppv_log("    - ppv_user_type: " . ($_SESSION['ppv_user_type'] ?? 'NOT SET'));
        ppv_log("    - ppv_email: " . ($_SESSION['ppv_email'] ?? 'NOT SET'));

        ppv_log("🔍 [PPV_MyPoints_REST] Cookies:");
        ppv_log("    - ppv_user_token: " . (isset($_COOKIE['ppv_user_token']) ? substr($_COOKIE['ppv_user_token'], 0, 20) . '...' : 'NOT SET'));

        // ✅ CRITICAL: Force session restore from token BEFORE checking user_id
        // This is REQUIRED for Google/Facebook/TikTok login to work
        if (class_exists('PPV_SessionBridge') && empty($_SESSION['ppv_user_id'])) {
            ppv_log("🔄 [PPV_MyPoints_REST] Session empty - calling PPV_SessionBridge::restore_from_token()");
            PPV_SessionBridge::restore_from_token();
            ppv_log("🔄 [PPV_MyPoints_REST] After restore:");
            ppv_log("    - ppv_user_id: " . ($_SESSION['ppv_user_id'] ?? 'STILL NOT SET'));
        } else {
            ppv_log("🔍 [PPV_MyPoints_REST] Session restore skipped (already have user_id or no SessionBridge)");
        }

        // Try WordPress user first
        $wp_user_id = get_current_user_id();
        ppv_log("🔍 [PPV_MyPoints_REST] WordPress user ID: " . ($wp_user_id ?: 'NOT LOGGED IN'));

        $user_id = $wp_user_id;

        // Fallback to session (Google/Facebook/TikTok login)
        if (!$user_id && !empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
            ppv_log("🔍 [PPV_MyPoints_REST] Using SESSION user_id: {$user_id}");
        }

        if ($user_id <= 0) {
            ppv_log("❌ [PPV_MyPoints_REST] No user found");
            ppv_log("    - WP_user: " . get_current_user_id());
            ppv_log("    - SESSION ppv_user_id: " . ($_SESSION['ppv_user_id'] ?? 'none'));
            ppv_log("    - COOKIE ppv_user_token: " . (isset($_COOKIE['ppv_user_token']) ? 'exists' : 'none'));
            ppv_log("🔍 [PPV_MyPoints_REST::rest_get_points] ========== END (401) ==========");
            return new WP_REST_Response(['error' => 'unauthorized', 'message' => 'Nicht angemeldet'], 401);
        }

        ppv_log("✅ [PPV_MyPoints_REST] User authenticated: user_id={$user_id}");

        $days = intval($request->get_param('range')) ?: 30;
        $points_table  = $wpdb->prefix . 'ppv_points';
        $stores_table  = $wpdb->prefix . 'ppv_stores';
        $rewards_table = $wpdb->prefix . 'ppv_rewards';

        $where = $wpdb->prepare("WHERE p.user_id = %d", $user_id);
        if ($days > 0) $where .= $wpdb->prepare(" AND p.created >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days);

        $data = [];

        try {
            $data['total'] = (int) $wpdb->get_var($wpdb->prepare("SELECT SUM(points) FROM $points_table WHERE user_id=%d", $user_id));
            $data['avg']   = (float) $wpdb->get_var($wpdb->prepare("SELECT AVG(points) FROM $points_table WHERE user_id=%d", $user_id));

            $data['top_day'] = $wpdb->get_row("
                SELECT DATE(created) AS day, SUM(points) AS total
                FROM $points_table
                WHERE user_id=$user_id
                GROUP BY DATE(created)
                ORDER BY total DESC LIMIT 1
            ");

            $data['top_store'] = $wpdb->get_row("
                SELECT s.name AS store_name, SUM(p.points) AS total
                FROM $points_table p
                LEFT JOIN $stores_table s ON p.store_id=s.id
                WHERE p.user_id=$user_id
                GROUP BY p.store_id
                ORDER BY total DESC LIMIT 1
            ");

            $data['top3'] = $wpdb->get_results("
                SELECT s.name AS store_name, SUM(p.points) AS total
                FROM $points_table p
                LEFT JOIN $stores_table s ON p.store_id=s.id
                $where
                GROUP BY p.store_id
                ORDER BY total DESC LIMIT 3
            ");

            $next = $wpdb->get_var("SELECT MIN(required_points) FROM $rewards_table WHERE required_points>0");
            $data['next_reward'] = $next;
            $data['remaining'] = $next ? max(0, $next - $data['total']) : null;

            $data['today_scans'] = (int) $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT store_id)
                FROM $points_table
                WHERE user_id=%d AND DATE(created)=CURDATE()
            ", $user_id));

            $data['entries'] = $wpdb->get_results("
                SELECT p.id, p.points, p.created, s.name AS store_name
                FROM $points_table p
                LEFT JOIN $stores_table s ON p.store_id=s.id
                $where
                ORDER BY p.created DESC LIMIT 50
            ");

            // 🏆 TIER LEVEL INFO - User's current level and progress to next
            // ✅ FIX: Use $lang from line 103 (was using undefined $active_lang)
            if (class_exists('PPV_User_Level')) {
                $data['tier'] = PPV_User_Level::get_user_level_info($user_id, $lang);

                // All tier thresholds for display
                $data['tiers'] = [
                    'starter'  => ['min' => 0,    'max' => 99,   'name' => PPV_User_Level::LEVELS['starter']['name_' . $lang] ?? 'Starter'],
                    'bronze'   => ['min' => 100,  'max' => 499,  'name' => PPV_User_Level::LEVELS['bronze']['name_' . $lang] ?? 'Bronze'],
                    'silver'   => ['min' => 500,  'max' => 999,  'name' => PPV_User_Level::LEVELS['silver']['name_' . $lang] ?? 'Silber'],
                    'gold'     => ['min' => 1000, 'max' => 1999, 'name' => PPV_User_Level::LEVELS['gold']['name_' . $lang] ?? 'Gold'],
                    'platinum' => ['min' => 2000, 'max' => 999999, 'name' => PPV_User_Level::LEVELS['platinum']['name_' . $lang] ?? 'Platin'],
                ];

                ppv_log("🏆 [PPV_MyPoints_REST] Tier info: level=" . ($data['tier']['level'] ?? 'unknown') . ", progress=" . ($data['tier']['progress'] ?? 0) . "%");
            }

            // 🎁 REFERRAL PROGRAM DATA
            $data['referral'] = self::get_user_referral_data($user_id, $lang);

            // ✅ REMOVED duplicate lang load - lang is already loaded at line 103-112

            // 🔹 Nyelvi kulcsok a Dashboard mintájára
            $labels = [
  'title'        => PPV_Lang::$strings['mypoints_title'] ?? 'Meine Punkte',
  'total'        => PPV_Lang::$strings['mypoints_total'] ?? 'Gesamtpunkte',
  'avg'          => PPV_Lang::$strings['mypoints_avg'] ?? 'Ø Punkte',
  'best_day'     => PPV_Lang::$strings['mypoints_best_day'] ?? 'Bester Tag',
  'top_store'    => PPV_Lang::$strings['mypoints_top_store'] ?? 'Top Laden',
  'activity'     => PPV_Lang::$strings['mypoints_activity'] ?? 'Aktivität heute',
  'next_reward'  => PPV_Lang::$strings['mypoints_next_reward'] ?? 'Nächste Prämie',
  'top3'         => PPV_Lang::$strings['mypoints_top3'] ?? 'Top 3 Geschäfte',
  'recent'       => PPV_Lang::$strings['mypoints_recent'] ?? 'Letzte Aktivitäten'
];


            $response = ['labels' => $labels, 'data' => $data];

            ppv_log("✅ [PPV_MYPOINTS_REST] Success, response ready (" . round(microtime(true)-$start,3) . "s)");
            ppv_log("📦 [PPV_MYPOINTS_REST] Data: " . substr(json_encode($response),0,500));

            return rest_ensure_response($response);

        } catch (Throwable $e) {
            ppv_log("❌ [PPV_MYPOINTS_REST] ERROR: " . $e->getMessage());
            return rest_ensure_response(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * 🎁 Get user referral program data
     * Returns referral code, stores with referral enabled, and stats
     */
    private static function get_user_referral_data($user_id, $lang = 'de') {
        global $wpdb;

        $stores_table = $wpdb->prefix . 'ppv_stores';
        $points_table = $wpdb->prefix . 'ppv_points';
        $referrals_table = $wpdb->prefix . 'ppv_referrals';
        $codes_table = $wpdb->prefix . 'ppv_user_referral_codes';

        $result = [
            'enabled' => false,
            'code' => null,
            'stores' => [],
            'total_referrals' => 0,
            'successful_referrals' => 0,
        ];

        // Check if referral tables exist
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$codes_table}'");
        if (!$table_exists) {
            ppv_log("⚠️ [PPV_MyPoints_REST] Referral tables not yet created");
            return $result;
        }

        // Get or create user's referral code
        $user_code = $wpdb->get_var($wpdb->prepare(
            "SELECT referral_code FROM {$codes_table} WHERE user_id = %d",
            $user_id
        ));

        if (!$user_code) {
            // Generate unique 8-char code
            $user_code = strtoupper(substr(md5($user_id . time() . wp_rand()), 0, 8));
            $wpdb->insert($codes_table, [
                'user_id' => $user_id,
                'referral_code' => $user_code,
                'created_at' => current_time('mysql')
            ]);
            ppv_log("🎁 [PPV_MyPoints_REST] Created referral code for user {$user_id}: {$user_code}");
        }

        $result['code'] = $user_code;

        // Get stores where:
        // 1. User has points (is a customer)
        // 2. Referral is enabled
        // 3. Grace period has passed (referral_activated_at + grace_days < now)
        $stores = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT
                s.id,
                s.name,
                s.store_key,
                s.referral_enabled,
                s.referral_activated_at,
                s.referral_grace_days,
                s.referral_reward_type,
                s.referral_reward_value,
                s.referral_reward_gift,
                COALESCE(SUM(p.points), 0) as user_points
            FROM {$stores_table} s
            INNER JOIN {$points_table} p ON p.store_id = s.id AND p.user_id = %d
            WHERE s.referral_enabled = 1
              AND s.referral_activated_at IS NOT NULL
              AND DATEDIFF(NOW(), s.referral_activated_at) >= s.referral_grace_days
            GROUP BY s.id
            ORDER BY user_points DESC
        ", $user_id));

        if (!empty($stores)) {
            $result['enabled'] = true;

            foreach ($stores as $store) {
                // Get referral stats for this store
                $stats = $wpdb->get_row($wpdb->prepare("
                    SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status IN ('completed', 'approved') THEN 1 ELSE 0 END) as successful,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                    FROM {$referrals_table}
                    WHERE referrer_user_id = %d AND store_id = %d
                ", $user_id, $store->id));

                // Build share URL
                $share_url = home_url("/r/{$user_code}/{$store->store_key}");

                // Reward text based on type
                $reward_text = '';
                switch ($store->referral_reward_type) {
                    case 'points':
                        $reward_text = $store->referral_reward_value . ' ' . ($lang === 'de' ? 'Punkte' : ($lang === 'hu' ? 'pont' : 'puncte'));
                        break;
                    case 'euro':
                        $reward_text = $store->referral_reward_value . '€ ' . ($lang === 'de' ? 'Rabatt' : ($lang === 'hu' ? 'kedvezmény' : 'reducere'));
                        break;
                    case 'gift':
                        $reward_text = $store->referral_reward_gift ?: ($lang === 'de' ? 'Geschenk' : ($lang === 'hu' ? 'ajándék' : 'cadou'));
                        break;
                }

                $result['stores'][] = [
                    'id' => (int) $store->id,
                    'name' => $store->name,
                    'store_key' => $store->store_key,
                    'reward_type' => $store->referral_reward_type,
                    'reward_value' => (int) $store->referral_reward_value,
                    'reward_text' => $reward_text,
                    'share_url' => $share_url,
                    'stats' => [
                        'total' => (int) ($stats->total ?? 0),
                        'successful' => (int) ($stats->successful ?? 0),
                        'pending' => (int) ($stats->pending ?? 0),
                    ],
                    'user_points' => (int) $store->user_points,
                ];

                $result['total_referrals'] += (int) ($stats->total ?? 0);
                $result['successful_referrals'] += (int) ($stats->successful ?? 0);
            }

            ppv_log("🎁 [PPV_MyPoints_REST] Referral data: " . count($result['stores']) . " stores, code={$user_code}");
        }

        return $result;
    }
}

PPV_My_Points_REST::hooks();
