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
        error_log("🧠 [PPV_MyPoints_REST] register_rest_route aktiválva");

        register_rest_route('ppv/v1', '/mypoints', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_points'],
            'permission_callback' => ['PPV_Permissions', 'check_authenticated'],
        ]);
    }

    /** 🔹 Adatok visszaadása JSON-ban */
    public static function rest_get_points($request) {
        global $wpdb;
        $start = microtime(true);

        error_log("🔍 [PPV_MyPoints_REST::rest_get_points] ========== START ==========");
        error_log("🔍 [PPV_MyPoints_REST] Request URL: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        error_log("🔍 [PPV_MyPoints_REST] Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));

        // Log all headers
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        error_log("🔍 [PPV_MyPoints_REST] Request Headers:");
        foreach ($headers as $key => $value) {
            if (stripos($key, 'auth') !== false || stripos($key, 'ppv') !== false || stripos($key, 'nonce') !== false) {
                $safe_value = substr($value, 0, 20) . '...';
                error_log("    - {$key}: {$safe_value}");
            }
        }

        // 🔹 Force language reload (Cookie / Session / Header alapján)
if (class_exists('PPV_Lang')) {
    if (session_status() === PHP_SESSION_NONE) @session_start();

    $cookie_lang  = $_COOKIE['ppv_lang'] ?? '';
    $session_lang = $_SESSION['ppv_lang'] ?? '';
    $header_lang  = $_SERVER['HTTP_X_PPV_LANG'] ?? '';

    error_log("🔍 [PPV_MyPoints_REST] Lang detection:");
    error_log("    - Cookie: " . ($cookie_lang ?: 'EMPTY'));
    error_log("    - Session: " . ($session_lang ?: 'EMPTY'));
    error_log("    - Header: " . ($header_lang ?: 'EMPTY'));

    $lang = $header_lang ?: ($cookie_lang ?: $session_lang ?: 'de');

    if (!empty($lang) && in_array($lang, ['de','hu','ro'], true)) {
        PPV_Lang::load($lang);
        PPV_Lang::$active = $lang;
        error_log("🌍 [PPV_MyPoints_REST] Lang forced before response → {$lang}");
    } else {
        error_log("⚠️ [PPV_MyPoints_REST] No valid lang detected, fallback → de");
    }
}

        // ✅ SAME AS USER DASHBOARD - Simple user detection
        // Start session if not started yet
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
            error_log("🔍 [PPV_MyPoints_REST] Session started");
        } else {
            error_log("🔍 [PPV_MyPoints_REST] Session already active, SID: " . session_id());
        }

        error_log("🔍 [PPV_MyPoints_REST] Session data BEFORE restore:");
        error_log("    - ppv_user_id: " . ($_SESSION['ppv_user_id'] ?? 'NOT SET'));
        error_log("    - ppv_user_type: " . ($_SESSION['ppv_user_type'] ?? 'NOT SET'));
        error_log("    - ppv_email: " . ($_SESSION['ppv_email'] ?? 'NOT SET'));

        error_log("🔍 [PPV_MyPoints_REST] Cookies:");
        error_log("    - ppv_user_token: " . (isset($_COOKIE['ppv_user_token']) ? substr($_COOKIE['ppv_user_token'], 0, 20) . '...' : 'NOT SET'));

        // ✅ CRITICAL: Force session restore from token BEFORE checking user_id
        // This is REQUIRED for Google/Facebook/TikTok login to work
        if (class_exists('PPV_SessionBridge') && empty($_SESSION['ppv_user_id'])) {
            error_log("🔄 [PPV_MyPoints_REST] Session empty - calling PPV_SessionBridge::restore_from_token()");
            PPV_SessionBridge::restore_from_token();
            error_log("🔄 [PPV_MyPoints_REST] After restore:");
            error_log("    - ppv_user_id: " . ($_SESSION['ppv_user_id'] ?? 'STILL NOT SET'));
        } else {
            error_log("🔍 [PPV_MyPoints_REST] Session restore skipped (already have user_id or no SessionBridge)");
        }

        // Try WordPress user first
        $wp_user_id = get_current_user_id();
        error_log("🔍 [PPV_MyPoints_REST] WordPress user ID: " . ($wp_user_id ?: 'NOT LOGGED IN'));

        $user_id = $wp_user_id;

        // Fallback to session (Google/Facebook/TikTok login)
        if (!$user_id && !empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
            error_log("🔍 [PPV_MyPoints_REST] Using SESSION user_id: {$user_id}");
        }

        if ($user_id <= 0) {
            error_log("❌ [PPV_MyPoints_REST] No user found");
            error_log("    - WP_user: " . get_current_user_id());
            error_log("    - SESSION ppv_user_id: " . ($_SESSION['ppv_user_id'] ?? 'none'));
            error_log("    - COOKIE ppv_user_token: " . (isset($_COOKIE['ppv_user_token']) ? 'exists' : 'none'));
            error_log("🔍 [PPV_MyPoints_REST::rest_get_points] ========== END (401) ==========");
            return new WP_REST_Response(['error' => 'unauthorized', 'message' => 'Nicht angemeldet'], 401);
        }

        error_log("✅ [PPV_MyPoints_REST] User authenticated: user_id={$user_id}");

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

// 🌍 Nyelv betöltése, ha még nem történt meg
$headers = function_exists('getallheaders') ? getallheaders() : [];
$header_lang = $headers['X-PPV-Lang'] ?? '';
$cookie_lang = $_COOKIE['ppv_lang'] ?? '';
$session_lang = $_SESSION['ppv_lang'] ?? '';
$active_lang = $header_lang ?: $cookie_lang ?: $session_lang ?: 'de';

if (class_exists('PPV_Lang')) {
    PPV_Lang::load($active_lang);
    PPV_Lang::$active = $active_lang;
    error_log("🌍 [PPV_MyPoints_REST] Lang forced before labels → {$active_lang}");
}

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

            error_log("✅ [PPV_MYPOINTS_REST] Success, response ready (" . round(microtime(true)-$start,3) . "s)");
            error_log("📦 [PPV_MYPOINTS_REST] Data: " . substr(json_encode($response),0,500));

            return rest_ensure_response($response);

        } catch (Throwable $e) {
            error_log("❌ [PPV_MYPOINTS_REST] ERROR: " . $e->getMessage());
            return rest_ensure_response(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }
}

PPV_My_Points_REST::hooks();
