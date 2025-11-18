<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Händler Reward Redeem Verwaltung
 * Version: 3.4 – Full Stable (Session AutoFix + REST + JS kompatibilitás)
 * Teljes verzió – minden modul funkciót tartalmaz
 */

class PPV_Redeem_Admin {

    /** ============================================================
     *  🔹 Hooks
     * ============================================================ */
    public static function hooks() {
        add_shortcode('ppv_redeem_admin', [__CLASS__, 'render_redeem_admin']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);

        // AJAX (legacy kompatibilitás)
        add_action('wp_ajax_ppv_update_redeem_status', [__CLASS__, 'ajax_update_status']);
    }

    /** ============================================================
     *  🔹 Session kezelő
     * ============================================================ */
    private static function ensure_session() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // ✅ USE SessionBridge for multi-device session restore
        if (class_exists('PPV_SessionBridge') && empty($_SESSION['ppv_user_id'])) {
            PPV_SessionBridge::restore_from_token();
            error_log("🔄 [PPV_Redeem_Admin] Restored session from token");
        }
    }

    /** ============================================================
     *  🔹 Assetek (JS + Session AutoFix)
     * ============================================================ */
     
     
   // opcionálisan: private
public static function enqueue_assets() {
    global $wpdb;
    self::ensure_session();
        // 🔹 Nur auf Rewards-Seite aktivieren (robuster Check)
    $load_js = false;
    $post = get_post();
    if ($post && strpos($post->post_content ?? '', '[ppv_rewards]') !== false) {
        $load_js = true;
    }

    if (!$load_js) {
        error_log("⏸️ [PPV_Redeem_Admin] JS übersprungen – Seite ohne [ppv_rewards]");
        return;
    }
 wp_enqueue_script(
        'ppv-rewards',
        PPV_PLUGIN_URL . 'assets/js/ppv-redeem-admin.js',
        ['jquery'],
        time(),
        true
    );



    // 🔹 Store ID biztosítása
    $store_id = 0;
    if (!empty($_SESSION['ppv_store_id'])) {
        $store_id = intval($_SESSION['ppv_store_id']);
    } else {
        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            $store_id = intval($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $current_user_id
            )));
            if ($store_id) $_SESSION['ppv_store_id'] = $store_id;
        }
    }

    // 🔹 Script és CSS betöltése fixen




    // 🔹 Localize adatok JS számára
    $__data = is_array([
        'base_url' => esc_url(rest_url('ppv/v1/')),
        'store_id' => $store_id,
        'user_id'  => get_current_user_id(),
        'debug'    => true
    ] ?? null) ? [
        'base_url' => esc_url(rest_url('ppv/v1/')),
        'store_id' => $store_id,
        'user_id'  => get_current_user_id(),
        'debug'    => true
    ] : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('ppv-redeem-admin', "window.ppv_redeem_admin = {$__json};", 'before');

    error_log("🟢 [PPV_Redeem_Admin] Assets loaded, store_id={$store_id}");
}


    /** ============================================================
     *  🔹 Shortcode Renderer
     * ============================================================ */
    public static function render_redeem_admin() {
    self::ensure_session();
    self::enqueue_assets();
    ob_start();
    ?>
    <script>
      console.log("🧩 PPV Redeem Admin aktiv – silent mode (nur Funktionen, kein Layout)");
    </script>
    <?php
    return ob_get_clean();
}



    /** ============================================================
     *  🔹 REST Endpoints
     * ============================================================ */
    public static function register_rest_routes() {
        register_rest_route('ppv/v1', '/redeem/list', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_list_redeems'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('ppv/v1', '/redeem/update', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_update_status'],
            'permission_callback' => '__return_true'
        ]);
        register_rest_route('ppv/v1', '/redeem/log', [
    'methods'  => 'GET',
    'callback' => [__CLASS__, 'rest_recent_logs'],
    'permission_callback' => '__return_true'
]);

register_rest_route('ppv/v1', '/ping', [
  'methods' => 'GET',
  'callback' => function() {
    $last = (int) get_option('ppv_last_redeem_update', 0);
    return [
      'success' => true,
      'last_update' => $last,
      'server_time' => time()
    ];
  },
  'permission_callback' => '__return_true'
]);



    }
    
    public static function rest_recent_logs($request) {
    global $wpdb;
    
    // ✅ Store ID lekérése
    $store_id = intval($request->get_param('store_id'));
    if (!$store_id && !empty($_SESSION['ppv_store_id'])) {
        $store_id = intval($_SESSION['ppv_store_id']);
    }

    $table = $wpdb->prefix . 'ppv_rewards_redeemed';
    
    // ✅ STORE-SPECIFIKUS szűrés!
    $sql = $store_id > 0 
        ? $wpdb->prepare("
            SELECT 
                r.id,
                u.email AS user_email,
                r.points_spent,
                r.status,
                r.redeemed_at
            FROM $table r
            LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
            WHERE r.store_id = %d
            ORDER BY r.redeemed_at DESC
            LIMIT 10
        ", $store_id)
        : "
            SELECT 
                r.id,
                u.email AS user_email,
                r.points_spent,
                r.status,
                r.redeemed_at
            FROM $table r
            LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
            ORDER BY r.redeemed_at DESC
            LIMIT 10
        ";

    $rows = $wpdb->get_results($sql);

    return ['success' => true, 'items' => $rows ?: []];
}

    

    /** ============================================================
 * 🔹 REST: Einlösungen (Redeem Requests) abrufen – Stable Hybrid Version
 * ============================================================ */
public static function rest_list_redeems($req) {
    global $wpdb;
    self::ensure_session();

    // 🔹 Store-ID Ermittlung (Request → Session → Global)
    $store_id = intval($req->get_param('store_id'));
    if (!$store_id && !empty($_SESSION['ppv_store_id'])) {
        $store_id = intval($_SESSION['ppv_store_id']);
    }
    if (!$store_id && !empty($GLOBALS['ppv_active_store'])) {
        $store_id = intval($GLOBALS['ppv_active_store']);
    }

    // 🧠 Debug log
    error_log("🧠 [PPV_REDEEM_ADMIN] REST store_id: " . $store_id);

    if (!$store_id) {
        error_log("⚠️ [PPV_REDEEM_ADMIN] Kein gültiger Store-ID (Request und Session leer)");
        return [
            'success' => false,
            'message' => 'Kein gültiger Store gefunden.',
            'items'   => []
        ];
    }

    // 🔹 SQL – nur Redeems für diesen Store abrufen
    $table = $wpdb->prefix . 'ppv_rewards_redeemed';
    $sql = $wpdb->prepare("
        SELECT r.id, r.user_id,
               u.first_name AS user_name,
               u.email AS user_email,
               rw.title AS reward_title,
               r.points_spent AS required_points,
               r.status,
               r.redeemed_at
        FROM $table r
        LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
        LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
        WHERE r.store_id = %d
        AND r.status IN ('offen','pending')
        ORDER BY r.id DESC
    ", $store_id);

    $rows = $wpdb->get_results($sql, ARRAY_A);

    if ($wpdb->last_error) {
        error_log("❌ [PPV_REDEEM_ADMIN] SQL Error: " . $wpdb->last_error);
        return [
            'success' => false,
            'message' => 'SQL Fehler',
            'items'   => []
        ];
    }

    // 🔹 Status-Konvertierung (DE → EN)
    foreach ($rows as &$r) {
        $status = strtolower($r['status']);
        if ($status === 'offen') $r['status'] = 'pending';
        if ($status === 'bestätigt') $r['status'] = 'approved';
    }

    // 🟢 Logging + Rückgabe
    error_log("🟢 [PPV_REDEEM_ADMIN] REST result count: " . count($rows));

    return [
        'success'  => true,
        'items'    => $rows,
        'store_id' => $store_id,
        'message'  => 'Redeems geladen.'
    ];
}


    /** ============================================================
     *  🔹 REST: Status ändern (Approve / Cancel)
     * ============================================================ */
public static function rest_update_status($req) {
    global $wpdb;
    self::ensure_session();

    $id     = intval($req['id']);
    $status = sanitize_text_field($req['status']);
    $store  = $_SESSION['ppv_store_id'] ?? 0;

    if (!$id || !$status) {
        return ['success' => false, 'message' => 'Ungültige Daten'];
    }

    $table = $wpdb->prefix . 'ppv_rewards_redeemed';

    // 🔹 Ellenőrizzük a jelenlegi státuszt – csak akkor frissítünk, ha tényleg változott
    $current = $wpdb->get_var($wpdb->prepare("SELECT status FROM $table WHERE id = %d", $id));
    if ($current === $status) {
        return ['success' => true, 'message' => '🔁 Keine Statusänderung'];
    }

    $wpdb->update(
    $table,
    [
        'status'       => $status,
        'redeemed_at'  => current_time('mysql') // 🔹 frissítjük a módosítás dátumát is
    ],
    ['id' => $id],
    ['%s', '%s'],
    ['%d']
);

    if ($wpdb->last_error) {
        error_log('❌ [PPV_REDEEM_ADMIN] Update Error: ' . $wpdb->last_error);
        return ['success' => false, 'message' => 'Update fehlgeschlagen'];
    }

    // ✅ Csak "approved" esetén pontlevonás
if ($status === 'approved') {
    self::deduct_points_for_redeem($id, $store);
}

// 🔹 Ping-Update (cache-safe)
$now = time();
update_option('ppv_last_redeem_update', $now);
error_log("🟡 [PPV_REDEEM_ADMIN] Ping-Update gesetzt: " . $now);

return [
    'success' => true,
    'message' => $status === 'approved'
        ? '✅ Anfrage bestätigt'
        : ($status === 'cancelled' ? '❌ Anfrage abgelehnt' : '🟢 Status aktualisiert')
];

}

    
    

    /** ============================================================
     *  🔹 Pontlevonás (jóváhagyás után)
     * ============================================================ */
    private static function deduct_points_for_redeem($redeem_id, $store_id) {
        global $wpdb;

        $redeem = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_rewards_redeemed WHERE id = %d",
            $redeem_id
        ), ARRAY_A);

        if (!$redeem) return false;

        $user_id = intval($redeem['user_id']);
        $points  = intval($redeem['points_spent']);

        $wpdb->insert(
    $wpdb->prefix . 'ppv_points',
    [
        'user_id'   => $user_id,
        'store_id'  => $store_id,
        'points'    => -$points,
        'type'      => 'redeem',
        'reference' => 'REDEEM-' . $redeem_id,
        'created'   => current_time('mysql')
    ],
    ['%d', '%d', '%d', '%s', '%s', '%s']
);


        if ($wpdb->last_error) {
            error_log('❌ [PPV_REDEEM_ADMIN] Punkteabzug Fehler: ' . $wpdb->last_error);
        }
    }

    /** ============================================================
     *  🔹 AJAX fallback (kompatibilitás)
     * ============================================================ */
    public static function ajax_update_status() {
        $id     = intval($_POST['id']);
        $status = sanitize_text_field($_POST['status']);

        $req = new WP_REST_Request('POST', '/ppv/v1/redeem/update');
        $req->set_param('id', $id);
        $req->set_param('status', $status);

        $result = self::rest_update_status($req);
        wp_send_json($result);
    }
}

PPV_Redeem_Admin::hooks();
