<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass POS AUTO API v2
 * – kombinált rendszer: user + kassza integráció
 * – automatikus pontkezelés, naplózás, bonus, reward
 * @author Erik
 */

class PPV_POS_AUTO_API {

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('plugins_loaded', [__CLASS__, 'maybe_create_tables']);
    }

    /** 🔹 API route regisztrálása */
    public static function register_routes() {
        register_rest_route('punktepass/v1', '/pos/auto', [
            'methods' => ['POST', 'GET'],
            'callback' => [__CLASS__, 'handle_pos_auto'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);
    }

    /** 🔹 Diagnosztika GET módra */
    public static function handle_pos_auto($req) {
        global $wpdb;

        if ($req->get_method() === 'GET' && isset($_GET['test'])) {
            return rest_ensure_response([
                'success' => true,
                'message' => 'POS AUTO API aktiv ✅',
                'time' => current_time('mysql')
            ]);
        }

        // JSON + Query merge
        $params = array_merge($req->get_json_params() ?: [], $req->get_query_params() ?: []);

        $email       = sanitize_text_field($params['email'] ?? '');
        $store_id    = intval($params['store_id'] ?? 0);
        $store_pin   = sanitize_text_field($params['store_pin'] ?? '');
        $points_add  = intval($params['points_add'] ?? 0);
        $reward_code = sanitize_text_field($params['reward_code'] ?? '');
        $invoice_id  = sanitize_text_field($params['invoice_id'] ?? '');
        $type        = sanitize_text_field($params['type'] ?? 'sale');
        $response_message = [];

        // ===============================
        // 🔍 Bolt azonosítás
        // ===============================
        if ($store_pin) {
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name FROM {$wpdb->prefix}ppv_stores WHERE pos_pin=%s",
                $store_pin
            ));
        } elseif ($store_id) {
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name FROM {$wpdb->prefix}ppv_stores WHERE id=%d",
                $store_id
            ));
        } else {
            return rest_ensure_response(['success' => false, 'message' => 'Fehlende store_id oder PIN']);
        }

        if (!$store) {
            self::log_transaction([
                'store_id' => 0,
                'email' => $email,
                'message' => 'Invalid store or PIN',
                'status' => 'error'
            ]);
            return rest_ensure_response(['success' => false, 'message' => 'Store nicht gefunden / PIN ungültig']);
        }

        $store_id = intval($store->id);

        // ===============================
        // 🔍 User azonosítás
        // ===============================
        $user_id = null;
        if ($email) {
            $user_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM wp_ppv_users WHERE email=%s", $email));
        }

        // ===============================
        // 🔹 Bonus rendszer (ha aktív)
        // ===============================
        $today = date('Y-m-d');
        $bonus = $wpdb->get_row($wpdb->prepare("
            SELECT multiplier, extra_points FROM wp_ppv_bonus_days
            WHERE store_id=%d AND date=%s AND active=1 LIMIT 1
        ", $store_id, $today));

        if ($bonus && $points_add > 0) {
            $orig = $points_add;
            $points_add = ($points_add * floatval($bonus->multiplier)) + intval($bonus->extra_points);
            $response_message[] = "+{$points_add} Punkte gutgeschrieben (Bonus aktiv: x{$bonus->multiplier} +{$bonus->extra_points}, original {$orig})";
        } elseif ($points_add > 0) {
            $response_message[] = "+{$points_add} Punkte gutgeschrieben";
        }

       // ===============================
// 🔹 Pont jóváírás (ha van)
// ===============================
if ($points_add !== 0) {
    $safe_user_id = intval($user_id) > 0 ? intval($user_id) : 0;

    $table_name = $wpdb->prefix . 'ppv_points';
    error_log("🧩 POS_DEBUG: inserting into {$table_name} (user_id={$safe_user_id}, store_id={$store_id}, points={$points_add})");

    $insert_result = $wpdb->insert(
        $table_name,
        [
            'user_id'   => $safe_user_id,
            'store_id'  => intval($store_id),
            'points'    => intval($points_add),
            'type'      => sanitize_text_field($type ?? 'pos_auto'),
            'reference' => sanitize_text_field($invoice_id ?? ''),
            'created'   => current_time('mysql')
        ]
    );

    if ($insert_result === false) {
        error_log("❌ POS_DEBUG_INSERT_ERROR: " . $wpdb->last_error);
    } else {
        error_log("✅ POS_DEBUG_INSERT_SUCCESS: ID=" . $wpdb->insert_id);
    }

    if ($safe_user_id === 0) {
        $response_message[] = "+{$points_add} Punkte gutgeschrieben (POS Modus)";
    } else {
        $response_message[] = "+{$points_add} Punkte gutgeschrieben (User Modus)";
    }
}



        // ===============================
        // 🔹 Reward beváltás (ha van kód)
        // ===============================
        if ($reward_code) {
            $reward = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM wp_ppv_rewards 
                WHERE store_id=%d 
                AND (action_value LIKE %s OR title LIKE %s)
                LIMIT 1
            ", $store_id, '%' . $wpdb->esc_like($reward_code) . '%', '%' . $wpdb->esc_like($reward_code) . '%'));

            if ($reward && $user_id) {
                $total_points = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(points) FROM wp_ppv_points WHERE user_id=%d", $user_id
                ));

                if ($total_points >= $reward->required_points) {
                    $wpdb->insert("wp_ppv_points", [
                        'user_id' => $user_id,
                        'store_id' => $store_id,
                        'points' => -abs($reward->required_points),
                        'type' => 'redeem',
                        'reference' => $reward->title,
                        'created' => current_time('mysql')
                    ]);
                    $response_message[] = "{$reward->title} eingelöst";
                } else {
                    $response_message[] = "Nicht genug Punkte für {$reward->title}";
                }
            } else {
                $response_message[] = "Ungültiger Reward-Code";
            }
        }

        // ===============================
        // 🔹 User pont újraszámolás
        // ===============================
        $user_points = 0;
        if ($user_id) {
            $user_points = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT SUM(points) FROM wp_ppv_points WHERE user_id=%d", $user_id
            ));
        }

        // ===============================
        // 🔹 Naplózás minden hívásról
        // ===============================
        self::log_transaction([
            'user_id'       => $user_id,
            'store_id'      => $store_id,
            'email'         => $email,
            'points_change' => $points_add,
            'reward_code'   => $reward_code,
            'invoice_id'    => $invoice_id,
            'message'       => implode(', ', $response_message),
            'status'        => 'ok'
        ]);

        return rest_ensure_response([
            'success' => true,
            'mode' => $store_pin ? 'POS' : 'USER',
            'message' => implode(', ', $response_message),
            'user_points' => $user_points,
            'store' => $store->name,
            'timestamp' => current_time('mysql')
        ]);
    }

    /** 🔹 Tranzakció log mentése */
    public static function log_transaction($data) {
        global $wpdb;
        $wpdb->insert("wp_ppv_pos_log", [
            'user_id' => intval($data['user_id'] ?? 0),
            'store_id' => intval($data['store_id'] ?? 0),
            'email' => sanitize_email($data['email'] ?? ''),
            'points_change' => intval($data['points_change'] ?? 0),
            'reward_code' => sanitize_text_field($data['reward_code'] ?? ''),
            'invoice_id' => sanitize_text_field($data['invoice_id'] ?? ''),
            'message' => sanitize_textarea_field($data['message'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'ok'),
            'ip_address' => sanitize_text_field($data['ip_address'] ?? ''),
            'user_agent' => sanitize_text_field($data['user_agent'] ?? ''),
            'metadata' => isset($data['metadata']) ? wp_json_encode($data['metadata']) : '',
            'created_at' => current_time('mysql')
        ]);
    }

    /** 🔹 Hiányzó táblák automatikus létrehozása */
    public static function maybe_create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        $sql[] = "CREATE TABLE IF NOT EXISTS wp_ppv_points (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            store_id BIGINT UNSIGNED NOT NULL,
            points INT NOT NULL,
            type VARCHAR(50) DEFAULT 'sale',
            reference VARCHAR(255) NULL,
            created DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset;";

        $sql[] = "CREATE TABLE IF NOT EXISTS wp_ppv_pos_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            store_id BIGINT UNSIGNED NULL,
            email VARCHAR(255),
            points_change INT,
            reward_code VARCHAR(100),
            invoice_id VARCHAR(100),
            message TEXT,
            status VARCHAR(50),
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            metadata TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset;";

        $sql[] = "CREATE TABLE IF NOT EXISTS wp_ppv_bonus_days (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            store_id BIGINT UNSIGNED,
            date DATE,
            multiplier FLOAT DEFAULT 1.0,
            extra_points INT DEFAULT 0,
            active TINYINT DEFAULT 1
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $query) dbDelta($query);
    }
}

add_action('plugins_loaded', ['PPV_POS_AUTO_API', 'hooks']);
