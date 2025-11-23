<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – POS REST API (Unified)
 * Version: 4.1 Stable
 * Integrált modul: Login, Rewards, Stores, Stats
 */

class PPV_POS_REST {

    /** ============================================================
     *  🔹 Inicializálás
     * ============================================================ */
    public static function hooks() {
            ppv_log("✅ [PPV_POS_REST] hooks() aktiv");

        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** ============================================================
     *  🔐 POS Permission Helper
     * ============================================================ */
public static function pos_permission($request) {
    global $wpdb;

    // 🔹 Admin mindig engedélyezett
    if (current_user_can('manage_options')) {
        return true;
    }

    // 🔹 Token beolvasása headerből vagy GET paraméterből
    $token = $request->get_header('ppv-pos-token');
    if (empty($token) && isset($_GET['pos_token'])) {
        $token = sanitize_text_field($_GET['pos_token']);
    }
    if (empty($token)) {
        ppv_log('❌ [POS_PERMISSION] Missing token');
        return false;
    }

    // 🔹 Debug – prefix és token ellenőrzés
    ppv_log('🧠 [POS_PERMISSION] Checking token=' . $token . ' on table=' . $wpdb->prefix . 'ppv_stores');

    // 🔹 SQL ellenőrzés
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE pos_token = %s AND pos_enabled = 1",
        $token
    ));

    if ($count > 0) {
        ppv_log('✅ [POS_PERMISSION] Token valid');
        return true;
    } else {
        ppv_log('❌ [POS_PERMISSION] Token not found or pos_disabled');
        return false;
    }
}

    /** ============================================================
     *  🔹 REST route regisztráció
     * ============================================================ */
   public static function register_routes() {
    $routes = rest_get_server()->get_routes();
    ppv_log("🧩 [PPV_POS_REST] register_routes aktiválva");

    // 🧱 Guard – ha már regisztrálva máshol, ne duplikáljuk
    if (isset($routes['/ppv/v1/pos/stats'])) {
        ppv_log('⚠️ [PPV_POS_REST] /ppv/v1/pos/stats already registered – skipping duplicate.');
        return;
    }

    $ns = 'ppv/v1'; // ✅ visszaállítva az egységes namespace-re

    // 💳 POS Login
    register_rest_route($ns, '/pos/login', [
        'methods'  => 'POST',
        'callback' => [__CLASS__, 'handle_pos_login'],
        'permission_callback' => ['PPV_Permissions', 'allow_anonymous'],
    ]);
    ppv_log("🧩 [PPV_POS_REST] /pos/login route regisztrálva");

    // 🏪 Multi-Store lista
    register_rest_route($ns, '/pos/stores', [
        'methods'  => 'GET',
        'callback' => [__CLASS__, 'get_pos_stores'],
        'permission_callback' => [__CLASS__, 'pos_permission'],
    ]);

    // 🎁 Reward kezelések
    register_rest_route($ns, '/pos/reward_request', [
        'methods'  => 'POST',
        'callback' => [__CLASS__, 'create_reward_request'],
        'permission_callback' => [__CLASS__, 'pos_permission'],
    ]);

    register_rest_route($ns, '/pos/reward_poll', [
        'methods'  => 'GET',
        'callback' => [__CLASS__, 'poll_reward_requests'],
        'permission_callback' => [__CLASS__, 'pos_permission'],
    ]);

    register_rest_route($ns, '/pos/reward_approve', [
        'methods'  => 'POST',
        'callback' => [__CLASS__, 'approve_reward_request'],
        'permission_callback' => [__CLASS__, 'pos_permission'],
    ]);

    register_rest_route($ns, '/pos/rewards_log', [
        'methods'  => 'GET',
        'callback' => [__CLASS__, 'get_recent_rewards'],
        'permission_callback' => [__CLASS__, 'pos_permission'],
    ]);

    // 📊 POS Statisztikák (egységes)
    register_rest_route($ns, '/pos/stats', [
        'methods'  => 'GET',
        'callback' => [__CLASS__, 'get_pos_stats'],
        'permission_callback' => [__CLASS__, 'pos_permission'],
    ]);

    ppv_log("✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert");
}

    /** ============================================================
     *  💳 POS Login (PIN alapján)
     * ============================================================ */
    public static function handle_pos_login(WP_REST_Request $request) {
    global $wpdb;
    ppv_log("🧩 [POS_Login] STARTED --- incoming data: " . json_encode($request->get_json_params()));

    $pin = sanitize_text_field($request['pin'] ?? '');
    if (empty($pin)) {
        return new WP_REST_Response(['success' => false, 'message' => 'PIN fehlt.'], 400);
    }

    // 🔹 Store keresése PIN alapján (biztosabb feltételekkel)
    $sql = $wpdb->prepare("
        SELECT s.id, s.company_name, s.pos_enabled, s.active, s.subscription_status, s.pos_pin
        FROM {$wpdb->prefix}ppv_stores s
        WHERE s.pos_pin = %s
        LIMIT 1
    ", $pin);

if (
    !$store ||
    empty($store->pos_enabled) ||
    empty($store->active) ||
    !in_array($store->subscription_status, ['active', 'trial'])
) {
    ppv_log("🚫 [POS_Login_DEBUG] FAIL CHECK → " . json_encode([
        'found' => !!$store,
        'pos_enabled' => $store->pos_enabled ?? 'NULL',
        'active' => $store->active ?? 'NULL',
        'subscription_status' => $store->subscription_status ?? 'NULL'
    ]));

    }

    // ✅ Token + session létrehozása
    $token = wp_generate_uuid4();
    set_transient("ppv_pos_session_{$token}", $store->id, HOUR_IN_SECONDS * 12);
    $wpdb->update("{$wpdb->prefix}ppv_stores", ['pos_token' => $token], ['id' => $store->id]);

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $_SESSION['ppv_is_pos'] = true;
    $_SESSION['ppv_active_store'] = intval($store->id);
    $_SESSION['ppv_pos_token'] = $token;
    session_write_close();

    setcookie('ppv_pos_token', $token, [
        'expires'  => time() + 43200,
        'path'     => '/',
        'secure'   => false,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);

    ppv_log("✅ [POS_Login] Erfolgreich – Store={$store->company_name} (ID={$store->id})");

    return new WP_REST_Response([
        'success' => true,
        'data' => [
            'store_id'      => intval($store->id),
            'store_name'    => $store->company_name,
            'session_token' => $token,
            'message'       => 'POS-Login erfolgreich.'
        ]
    ], 200);
}


    /** ============================================================
     *  🏪 Multi-Store lista
     * ============================================================ */
    public static function get_pos_stores($request) {
        global $wpdb;
        $token = sanitize_text_field($request->get_param('token') ?? '');
        $store_id = get_transient("ppv_pos_session_{$token}");
        if (!$store_id) {
            return new WP_Error('invalid_token', 'Ungültige oder abgelaufene Sitzung.', ['status' => 401]);
        }

        $main_user_id = (int) $wpdb->get_var($wpdb->prepare("
            SELECT user_id FROM {$wpdb->prefix}ppv_stores WHERE id = %d
        ", $store_id));

        $stores = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, city, address
            FROM {$wpdb->prefix}ppv_stores
            WHERE user_id = %d AND active = 1
            ORDER BY id ASC
        ", $main_user_id));

        $result = [];
        foreach ($stores as $s) {
            $result[] = [
                'id'      => intval($s->id),
                'name'    => $s->name,
                'city'    => $s->city,
                'address' => $s->address
            ];
        }

        return ['success' => true, 'data' => $result];
    }

    /** ============================================================
     *  📊 POS Statisztikák (egységesített)
     * ============================================================ */
    public static function get_pos_stats(WP_REST_Request $request) {
    global $wpdb;

    $store_id = intval($request->get_param('store_id'));
    if (!$store_id) {
        return new WP_REST_Response(['success' => false, 'message' => '❌ Missing store_id'], 400);
    }

    $prefix = $wpdb->prefix;
    $today  = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('-6 days'));

    // 🔹 Alap statisztikák
    $stats = [
        'today_scans' => (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$prefix}ppv_points
            WHERE store_id=%d AND DATE(created)=%s
        ", $store_id, $today)),

        'today_points' => (int) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points),0) FROM {$prefix}ppv_points
            WHERE store_id=%d AND DATE(created)=%s
        ", $store_id, $today)),

        'today_rewards' => (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$prefix}ppv_rewards
            WHERE store_id=%d AND redeemed=1 AND DATE(redeemed_at)=%s
        ", $store_id, $today)),

        'active_campaigns' => (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$prefix}ppv_campaigns
            WHERE store_id=%d AND status='active'
        ", $store_id)),
    ];

    // 🔹 Heti pontösszesítés (7 nap)
    $weekly = $wpdb->get_results($wpdb->prepare("
        SELECT DATE(created) as day, SUM(points) as total
        FROM {$prefix}ppv_points
        WHERE store_id=%d AND DATE(created) BETWEEN %s AND %s
        GROUP BY DATE(created)
        ORDER BY day ASC
    ", $store_id, $week_start, $today));

    $chart = [];
    foreach ($weekly as $row) {
        $chart[] = [
            'day' => date('d.m', strtotime($row->day)),
            'points' => (int) $row->total,
        ];
    }

    // 🔹 Utolsó 5 felhasználó (QR-scan)
    $recent_users = $wpdb->get_results($wpdb->prepare("
        SELECT u.display_name, MAX(p.created) as last_scan
        FROM {$prefix}ppv_points p
        LEFT JOIN {$prefix}users u ON u.ID = p.user_id
        WHERE p.store_id=%d
        GROUP BY p.user_id
        ORDER BY last_scan DESC
        LIMIT 5
    ", $store_id));

    $users = [];
    foreach ($recent_users as $u) {
        $users[] = [
            'name' => $u->display_name ?: 'Unbekannt',
            'last_scan' => $u->last_scan,
        ];
    }

    // 🔹 Aktív kampány rövid adatok
    $campaign = $wpdb->get_row($wpdb->prepare("
        SELECT title, multiplier, DATE_FORMAT(end_date, '%%d.%%m') as ends
        FROM {$prefix}ppv_campaigns
        WHERE store_id=%d AND status='active'
        ORDER BY end_date ASC
        LIMIT 1
    ", $store_id));

    // 🔹 Válasz összeállítása
    return new WP_REST_Response([
        'success' => true,
        'stats' => $stats,
        'chart' => $chart,
        'recent_users' => $users,
        'campaign' => $campaign ?: null,
    ], 200);
}


    /** ============================================================
     *  📦 Rewards kezelés (request + poll + approve)
     * ============================================================ */
    // --- (a te meglévő reward függvényeid ide bemásolhatók változtatás nélkül) ---

    /** ============================================================
     *  🧩 Asset betöltés
     * ============================================================ */
    public static function enqueue_assets() {
        if (!is_page('pos-dashboard')) return;

        wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);

        global $wpdb;
        $store_id = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;
        if (wp_script_is('ppv-pos-dashboard', 'enqueued')) {
            $__data = is_array($store_id ?? null) ? $store_id : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('ppv-pos-dashboard', "window.PPV_STORE_ID = {$__json};", 'before');
        }
    }
}

// ============================================================
// 🔹 Inicializálás
// ============================================================
add_action('plugins_loaded', ['PPV_POS_REST', 'hooks']);
