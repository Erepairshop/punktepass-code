<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – POS Gateway (v1.0)
 * Modul 1/5 – Alap, jogosultság és route setup
 */

class PPV_POS_GATEWAY {

    /** ============================================================
     *  🔹 Inicializálás
     * ============================================================ */
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('init', [__CLASS__, 'auto_restore_session']);
    }

    /** ============================================================
     *  🔹 Engedélyezés: WP user, POS token vagy admin
     * ============================================================ */
    public static function verify_access() {
        global $wpdb;

        // Admin mindig engedélyezett
        if (current_user_can('manage_options')) return true;

        // Bejelentkezett WP user
        if (is_user_logged_in()) return true;

        // POS token (cookie vagy header)
        $token = '';
        if (!empty($_COOKIE['ppv_pos_token'])) {
            $token = sanitize_text_field($_COOKIE['ppv_pos_token']);
        } elseif (!empty($_SERVER['HTTP_PPV_POS_TOKEN'])) {
            $token = sanitize_text_field($_SERVER['HTTP_PPV_POS_TOKEN']);
        }

        if ($token) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE pos_token=%s AND pos_enabled=1",
                $token
            ));
            if ($exists > 0) return true;
        }

        return false;
    }

    /** ============================================================
     *  🔹 REST route regisztráció (későbbi moduloknak)
     * ============================================================ */
    public static function register_routes() {
        // a további endpointokat a következő részek fogják hozzáadni
        error_log("🧠 [PPV_POS_GATEWAY] Base route setup ready.");
    }

    /** ============================================================
     *  🔹 POS Session auto-restore
     * ============================================================ */
    public static function auto_restore_session() {
        global $wpdb;

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        if (!empty($_COOKIE['ppv_pos_token']) && empty($_SESSION['ppv_active_store'])) {
            $token = sanitize_text_field($_COOKIE['ppv_pos_token']);
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT id, company_name FROM {$wpdb->prefix}ppv_stores
                WHERE pos_token=%s AND pos_enabled=1
                LIMIT 1
            ", $token));

            if ($store) {
                $_SESSION['ppv_active_store'] = intval($store->id);
                $_SESSION['ppv_is_pos'] = true;
                $_SESSION['ppv_store_name'] = $store->company_name;
                $GLOBALS['ppv_active_store'] = $store;
                $GLOBALS['ppv_is_pos'] = true;

                error_log("✅ [POS_SESSION] Restored for store={$store->id}");
            }
        }
    }
}

// Inicializálás
add_action('plugins_loaded', ['PPV_POS_GATEWAY', 'hooks']);
    /** ============================================================
     *  2️⃣ POS Login + Token rendszer
     * ============================================================ */
    public static function register_routes() {
        register_rest_route('punktepass/v1', '/pos/login', [
            'methods' => ['POST'],
            'callback' => [__CLASS__, 'pos_login'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('punktepass/v1', '/pos/logout', [
            'methods' => ['POST'],
            'callback' => [__CLASS__, 'pos_logout'],
            'permission_callback' => '__return_true'
        ]);

        error_log("🧠 [PPV_POS_GATEWAY] Login endpoints registered.");
    }

    /** 🔹 POS bejelentkezés PIN vagy API kulccsal */
    public static function pos_login($req) {
        global $wpdb;

        $params = $req->get_json_params() ?: [];
        $pin     = sanitize_text_field($params['pin'] ?? '');
        $api_key = sanitize_text_field($params['api_key'] ?? '');

        if (empty($pin) && empty($api_key)) {
            return rest_ensure_response(['success' => false, 'message' => 'Missing PIN or API key']);
        }

        // PIN → store keresés
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT id, company_name, pos_token
            FROM {$wpdb->prefix}ppv_stores
            WHERE (pos_pin=%s OR pos_api_key=%s) AND pos_enabled=1
            LIMIT 1
        ", $pin, $api_key));

        if (!$store) {
            return rest_ensure_response(['success' => false, 'message' => '❌ Invalid credentials']);
        }

        // Ha nincs token, generálunk
        if (empty($store->pos_token)) {
            $token = 'PPTOK_' . wp_generate_password(24, false);
            $wpdb->update(
                "{$wpdb->prefix}ppv_stores",
                ['pos_token' => $token],
                ['id' => $store->id]
            );
        } else {
            $token = $store->pos_token;
        }

        // Süti beállítása (7 nap)
        setcookie('ppv_pos_token', $token, time() + 604800, '/');
        $_SESSION['ppv_active_store'] = intval($store->id);
        $_SESSION['ppv_is_pos'] = true;
        $_SESSION['ppv_store_name'] = $store->company_name;
        $GLOBALS['ppv_active_store'] = $store;
        $GLOBALS['ppv_is_pos'] = true;

        return rest_ensure_response([
            'success' => true,
            'message' => 'POS login successful',
            'store' => [
                'id' => intval($store->id),
                'name' => $store->company_name,
                'token' => $token
            ]
        ]);
    }

    /** 🔹 POS logout – token törlés + session lezárás */
    public static function pos_logout($req) {
        if (isset($_COOKIE['ppv_pos_token'])) {
            setcookie('ppv_pos_token', '', time() - 3600, '/');
        }
        session_destroy();
        $GLOBALS['ppv_active_store'] = null;
        $GLOBALS['ppv_is_pos'] = false;

        return rest_ensure_response([
            'success' => true,
            'message' => 'POS logged out'
        ]);
    }
    /** ============================================================
     *  3️⃣ Sales + Points + Offline Sync
     * ============================================================ */
    public static function register_routes() {
        // Megtartjuk az eddigi endpointokat és bővítjük
        register_rest_route('punktepass/v1', '/pos/sale', [
            'methods' => ['POST'],
            'callback' => [__CLASS__, 'pos_sale'],
            'permission_callback' => [__CLASS__, 'verify_access']
        ]);

        register_rest_route('punktepass/v1', '/pos/sync_offline', [
            'methods' => ['POST'],
            'callback' => [__CLASS__, 'sync_offline'],
            'permission_callback' => [__CLASS__, 'verify_access']
        ]);

        error_log("🧠 [PPV_POS_GATEWAY] Points + Sync endpoints ready.");
    }

    /** 🔹 POS sale – pontjóváírás vásárláskor */
    public static function pos_sale($req) {
        global $wpdb;

        $params = $req->get_json_params() ?: [];
        $email  = sanitize_text_field($params['email'] ?? '');
        $points = intval($params['points'] ?? 0);
        $amount = floatval($params['amount'] ?? 0);

        // Aktív bolt azonosítása
        $store_id = intval($_SESSION['ppv_active_store'] ?? 0);
        if (!$store_id) return rest_ensure_response(['success' => false, 'message' => 'Missing store session']);

        // Felhasználó keresés
        $user_id = 0;
        if ($email) {
            $user_id = intval($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email=%s", $email
            )));
        }

        if (!$user_id) {
            return rest_ensure_response(['success' => false, 'message' => 'User not found']);
        }

        // Pont hozzáadása
        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id'  => $user_id,
            'store_id' => $store_id,
            'points'   => $points,
            'type'     => 'sale',
            'reference'=> 'POS Sale #' . uniqid(),
            'created'  => current_time('mysql')
        ]);

        error_log("💰 [POS_SALE] +{$points} points for user={$user_id}, store={$store_id}");

        return rest_ensure_response([
            'success' => true,
            'message' => "+{$points} Punkte hinzugefügt",
            'points'  => $points,
            'email'   => $email
        ]);
    }

    /** 🔹 Offline tranzakciók szinkronizálása (Dock Bridge → PunktePass) */
    public static function sync_offline($req) {
        global $wpdb;

        $params = $req->get_json_params() ?: [];
        $transactions = $params['transactions'] ?? [];
        $store_id = intval($_SESSION['ppv_active_store'] ?? 0);

        if (!$store_id || empty($transactions)) {
            return rest_ensure_response(['success' => false, 'message' => 'Missing store or transactions']);
        }

        $count = 0;
        foreach ($transactions as $t) {
            $email  = sanitize_text_field($t['email'] ?? '');
            $points = intval($t['points'] ?? 0);
            if (!$email || !$points) continue;

            $user_id = intval($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email=%s", $email
            )));
            if (!$user_id) continue;

            $wpdb->insert("{$wpdb->prefix}ppv_points", [
                'user_id'  => $user_id,
                'store_id' => $store_id,
                'points'   => $points,
                'type'     => 'offline_sync',
                'reference'=> 'OfflineSync #' . uniqid(),
                'created'  => current_time('mysql')
            ]);
            $count++;
        }

        error_log("🔄 [POS_SYNC_OFFLINE] {$count} offline transactions imported (store={$store_id})");

        return rest_ensure_response([
            'success' => true,
            'message' => "✅ {$count} offline transactions synced",
            'count'   => $count
        ]);
    }
    /** ============================================================
     *  4️⃣ Redeem + Bonus + Stats modul
     * ============================================================ */
    public static function register_routes() {
        register_rest_route('punktepass/v1', '/pos/redeem', [
            'methods' => ['POST'],
            'callback' => [__CLASS__, 'pos_redeem_reward'],
            'permission_callback' => [__CLASS__, 'verify_access']
        ]);

        register_rest_route('punktepass/v1', '/pos/bonus', [
            'methods' => ['GET'],
            'callback' => [__CLASS__, 'pos_get_bonus'],
            'permission_callback' => [__CLASS__, 'verify_access']
        ]);

        register_rest_route('punktepass/v1', '/pos/stats', [
            'methods' => ['GET'],
            'callback' => [__CLASS__, 'pos_get_stats'],
            'permission_callback' => [__CLASS__, 'verify_access']
        ]);

        error_log("🧩 [PPV_POS_GATEWAY] Redeem + Bonus + Stats endpoints ready.");
    }

    /** 🔹 Reward beváltás (POS terminálról) */
    public static function pos_redeem_reward($req) {
        global $wpdb;

        $params = $req->get_json_params() ?: [];
        $email  = sanitize_text_field($params['email'] ?? '');
        $reward = sanitize_text_field($params['reward_code'] ?? '');
        $store_id = $_SESSION['ppv_active_store'] ?? 0;

        if (!$store_id || !$email || !$reward) {
            return rest_ensure_response(['success' => false, 'message' => 'Missing data']);
        }

        $user_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email=%s", $email
        ));
        if (!$user_id) {
            return rest_ensure_response(['success' => false, 'message' => 'User not found']);
        }

        $reward_data = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_rewards 
            WHERE store_id=%d 
            AND (title LIKE %s OR action_value LIKE %s)
            LIMIT 1
        ", $store_id, "%$reward%", "%$reward%"));

        if (!$reward_data) {
            return rest_ensure_response(['success' => false, 'message' => 'Reward not found']);
        }

        $total_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d", $user_id
        ));
        if ($total_points < $reward_data->required_points) {
            return rest_ensure_response([
                'success' => false,
                'message' => 'Not enough points',
                'current' => $total_points,
                'needed' => $reward_data->required_points
            ]);
        }

        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id' => $user_id,
            'store_id' => $store_id,
            'points' => -abs($reward_data->required_points),
            'type' => 'pos_redeem',
            'reference' => $reward_data->title,
            'created' => current_time('mysql')
        ]);

        return rest_ensure_response([
            'success' => true,
            'message' => "🎁 {$reward_data->title} eingelöst",
            'reward' => [
                'type' => $reward_data->action_type,
                'value' => $reward_data->action_value
            ]
        ]);
    }

    /** 🔹 Aktív bónusznap lekérdezése */
    public static function pos_get_bonus($req) {
        global $wpdb;
        $store_id = $_SESSION['ppv_active_store'] ?? 0;

        if (!$store_id) {
            return rest_ensure_response(['success' => false, 'message' => 'No store session']);
        }

        $today = date('Y-m-d');
        $bonus = $wpdb->get_row($wpdb->prepare("
            SELECT multiplier, extra_points FROM {$wpdb->prefix}ppv_bonus_days 
            WHERE store_id=%d AND date=%s AND active=1 LIMIT 1
        ", $store_id, $today));

        if ($bonus) {
            return rest_ensure_response([
                'success' => true,
                'bonus' => [
                    'multiplier' => floatval($bonus->multiplier),
                    'extra_points' => intval($bonus->extra_points)
                ],
                'message' => "Bonus aktiv (x{$bonus->multiplier} +{$bonus->extra_points})"
            ]);
        }

        return rest_ensure_response([
            'success' => true,
            'bonus' => null,
            'message' => "Kein Bonus aktiv"
        ]);
    }

    /** 🔹 Napi és heti statisztika */
    public static function pos_get_stats($req) {
        global $wpdb;
        $store_id = $_SESSION['ppv_active_store'] ?? 0;

        if (!$store_id) {
            return rest_ensure_response(['success' => false, 'message' => 'No store session']);
        }

        $today = date('Y-m-d');
        $week_start = date('Y-m-d', strtotime('monday this week'));

        $daily = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points 
            WHERE store_id=%d AND DATE(created)=%s
        ", $store_id, $today));

        $weekly = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points 
            WHERE store_id=%d AND DATE(created) >= %s
        ", $store_id, $week_start));

        return rest_ensure_response([
            'success' => true,
            'stats' => [
                'today_scans' => $daily,
                'week_scans' => $weekly
            ],
            'message' => 'Stats loaded'
        ]);
    }
    /** ============================================================
     *  5️⃣ Security + Access + Logging
     * ============================================================ */

    /** 🔐 Hozzáférés-ellenőrzés minden POS endpointnál */
    public static function verify_access($request) {
        global $wpdb;

        // 🔹 Admin mindig engedélyezett
        if (current_user_can('manage_options')) {
            return true;
        }

        // 🔹 Token ellenőrzés (header vagy cookie)
        $token = $request->get_header('ppv-pos-token') ?: ($_COOKIE['ppv_pos_token'] ?? '');
        if ($token) {
            $store_id = get_transient("ppv_pos_session_{$token}");
            if ($store_id) {
                $GLOBALS['ppv_active_store'] = $store_id;
                $_SESSION['ppv_active_store'] = $store_id;
                return true;
            }
        }

        // 🔹 API-kulcs ellenőrzés
        $api_key = sanitize_text_field($request->get_param('api_key') ?? '');
        if ($api_key) {
            $count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE pos_api_key=%s AND pos_enabled=1",
                $api_key
            ));
            if ($count > 0) return true;
        }

        error_log("🚫 [PPV_POS_SECURITY] Unauthorized request");
        return new WP_Error('unauthorized', 'Du bist leider nicht berechtigt, diese Aktion durchzuführen.', ['status' => 403]);
    }

    /** 🔹 Napló mentése (minden POS műveletről) */
    public static function log_event($store_id, $action, $message, $status = 'ok') {
        global $wpdb;
        if (!$store_id) return;

        $wpdb->insert("{$wpdb->prefix}ppv_pos_log", [
            'store_id'   => intval($store_id),
            'action'     => sanitize_text_field($action),
            'message'    => sanitize_textarea_field($message),
            'status'     => sanitize_text_field($status),
            'created_at' => current_time('mysql')
        ]);

        error_log("📝 [PPV_POS_LOG] {$action} → {$message}");
    }
}
