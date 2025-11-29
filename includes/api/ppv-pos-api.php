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
        // 🎂 Birthday Bonus
        // ===============================
        $birthday_bonus_applied = 0;
        if ($user_id && $points_add > 0) {
            $birthday_settings = $wpdb->get_row($wpdb->prepare("
                SELECT birthday_bonus_enabled, birthday_bonus_type, birthday_bonus_value, birthday_bonus_message
                FROM {$wpdb->prefix}ppv_stores WHERE id = %d
            ", $store_id));

            if ($birthday_settings && $birthday_settings->birthday_bonus_enabled) {
                $user_bday_data = $wpdb->get_row($wpdb->prepare("
                    SELECT birthday, last_birthday_bonus_at FROM {$wpdb->prefix}ppv_users WHERE id = %d
                ", $user_id));

                if ($user_bday_data && $user_bday_data->birthday) {
                    $today_md = date('m-d');
                    $birthday_md = date('m-d', strtotime($user_bday_data->birthday));

                    if ($today_md === $birthday_md) {
                        // Anti-abuse check: minimum 320 days between birthday bonuses
                        $can_receive_bonus = true;
                        if ($user_bday_data->last_birthday_bonus_at) {
                            $last_bonus_date = strtotime($user_bday_data->last_birthday_bonus_at);
                            $days_since_last_bonus = floor((time() - $last_bonus_date) / (60 * 60 * 24));
                            if ($days_since_last_bonus < 320) {
                                $can_receive_bonus = false;
                            }
                        }

                        if ($can_receive_bonus) {
                            $bonus_type = $birthday_settings->birthday_bonus_type ?? 'double_points';
                            // 🔒 FIX: Save true base points BEFORE bonus calculation
                            $true_base_points = $points_add;

                            switch ($bonus_type) {
                                case 'double_points':
                                    $birthday_bonus_applied = $true_base_points;
                                    break;
                                case 'fixed_points':
                                    $birthday_bonus_applied = intval($birthday_settings->birthday_bonus_value ?? 0);
                                    break;
                            }

                            if ($birthday_bonus_applied > 0) {
                                // 🔒 FIX: Use atomic UPDATE with WHERE to prevent race condition
                                $rows_updated = $wpdb->query($wpdb->prepare("
                                    UPDATE {$wpdb->prefix}ppv_users
                                    SET last_birthday_bonus_at = %s
                                    WHERE id = %d
                                    AND (last_birthday_bonus_at IS NULL OR last_birthday_bonus_at < DATE_SUB(CURDATE(), INTERVAL 320 DAY))
                                ", date('Y-m-d'), $user_id));

                                if ($rows_updated > 0) {
                                    $points_add += $birthday_bonus_applied;
                                    $response_message[] = "🎂 Geburtstags-Bonus: +{$birthday_bonus_applied}";
                                } else {
                                    // Race condition prevented
                                    $birthday_bonus_applied = 0;
                                    ppv_log("🔒 [POS API] Birthday bonus race condition prevented for user {$user_id}");
                                }
                            }
                        }
                    }
                }
            }
        }

        // ===============================
        // 🔹 Pont jóváírás (ha van) - WITH TRANSACTION PROTECTION
        // ===============================
        if ($points_add !== 0) {
            $safe_user_id = intval($user_id) > 0 ? intval($user_id) : 0;
            $table_name = $wpdb->prefix . 'ppv_points';

            // 🔒 Get IP address for tracking
            $ip_address = self::get_client_ip();

            // 🔒 START TRANSACTION for atomicity
            $wpdb->query('START TRANSACTION');

            try {
                // 🔒 DUPLICATE CHECK: Prevent race condition double-inserts (for user scans)
                if ($safe_user_id > 0) {
                    $recent_insert = $wpdb->get_var($wpdb->prepare("
                        SELECT id FROM {$wpdb->prefix}ppv_points
                        WHERE user_id = %d AND store_id = %d
                        AND created > DATE_SUB(NOW(), INTERVAL 5 SECOND)
                        LIMIT 1
                    ", $safe_user_id, $store_id));

                    if ($recent_insert) {
                        $wpdb->query('ROLLBACK');
                        ppv_log("⚠️ [POS API] Duplicate scan blocked: user={$safe_user_id}, store={$store_id}, existing_id={$recent_insert}");
                        return rest_ensure_response([
                            'success' => false,
                            'message' => '⚠️ Doppelte Transaktion blockiert',
                            'error_type' => 'duplicate'
                        ]);
                    }
                }

                $insert_result = $wpdb->insert(
                    $table_name,
                    [
                        'user_id'   => $safe_user_id,
                        'store_id'  => intval($store_id),
                        'points'    => intval($points_add),
                        'type'      => sanitize_text_field($type ?? 'pos_auto'),
                        // 🔒 NEW: Device/GPS tracking fields
                        'ip_address' => $ip_address,
                        'reference' => sanitize_text_field($invoice_id ?? ''),
                        'created'   => current_time('mysql')
                    ]
                );

                if ($insert_result === false) {
                    $wpdb->query('ROLLBACK');
                    ppv_log("❌ [POS API] Failed to insert points: " . $wpdb->last_error);
                    return rest_ensure_response([
                        'success' => false,
                        'message' => '❌ Datenbankfehler',
                        'error_type' => 'db_error'
                    ]);
                }

                $wpdb->query('COMMIT');

                if ($safe_user_id === 0) {
                    $response_message[] = "+{$points_add} Punkte gutgeschrieben (POS Modus)";
                } else {
                    $response_message[] = "+{$points_add} Punkte gutgeschrieben (User Modus)";
                }

            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                ppv_log("❌ [POS API] Transaction failed: " . $e->getMessage());
                return rest_ensure_response([
                    'success' => false,
                    'message' => '❌ Transaktionsfehler',
                    'error_type' => 'transaction_error'
                ]);
            }
        }



        // ===============================
        // 🔹 Reward beváltás (ha van kód) - WITH TRANSACTION PROTECTION
        // ===============================
        if ($reward_code) {
            $reward = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM wp_ppv_rewards
                WHERE store_id=%d
                AND (action_value LIKE %s OR title LIKE %s)
                LIMIT 1
            ", $store_id, '%' . $wpdb->esc_like($reward_code) . '%', '%' . $wpdb->esc_like($reward_code) . '%'));

            if ($reward && $user_id) {
                // 🔒 START TRANSACTION for redemption atomicity
                $wpdb->query('START TRANSACTION');

                try {
                    // 🔒 FIX: Lock and check points in single query to prevent race condition
                    $total_points = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d FOR UPDATE",
                        $user_id
                    ));

                    if ($total_points >= $reward->required_points) {
                        $insert_result = $wpdb->insert("{$wpdb->prefix}ppv_points", [
                            'user_id' => $user_id,
                            'store_id' => $store_id,
                            'points' => -abs($reward->required_points),
                            'type' => 'redeem',
                            'ip_address' => self::get_client_ip(),
                            'reference' => $reward->title,
                            'created' => current_time('mysql')
                        ]);

                        if ($insert_result === false) {
                            $wpdb->query('ROLLBACK');
                            $response_message[] = "Fehler bei der Einlösung";
                        } else {
                            $wpdb->query('COMMIT');
                            $response_message[] = "{$reward->title} eingelöst";
                        }
                    } else {
                        $wpdb->query('ROLLBACK');
                        $response_message[] = "Nicht genug Punkte für {$reward->title}";
                    }
                } catch (Exception $e) {
                    $wpdb->query('ROLLBACK');
                    ppv_log("❌ [POS API] Redemption failed: " . $e->getMessage());
                    $response_message[] = "Einlösungsfehler";
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

        // Auto-detect IP address if not provided
        if (empty($data['ip_address'])) {
            $ip_raw = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            $data['ip_address'] = sanitize_text_field(explode(',', $ip_raw)[0]);
        }

        // Auto-detect user agent if not provided
        if (empty($data['user_agent'])) {
            $data['user_agent'] = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        }

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
            campaign_id BIGINT UNSIGNED NULL,
            device_fingerprint VARCHAR(64) NULL COMMENT 'SHA256 hash of scanner device fingerprint',
            ip_address VARCHAR(45) NULL COMMENT 'IP address of scan request',
            latitude DECIMAL(10,8) NULL COMMENT 'GPS latitude of scan location',
            longitude DECIMAL(11,8) NULL COMMENT 'GPS longitude of scan location',
            scanner_id BIGINT UNSIGNED NULL COMMENT 'User ID of employee who scanned',
            reference VARCHAR(255) NULL,
            created DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_store (user_id, store_id),
            INDEX idx_device_fingerprint (device_fingerprint),
            INDEX idx_ip_address (ip_address),
            INDEX idx_scanner_id (scanner_id)
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

add_action('plugins_loaded', ['PPV_POS_AUTO_API', 'hooks']);
