<?php
if (!defined('ABSPATH')) exit;
error_log("🟢 PPV_POS_DOCK.php loaded (v1.5 Secure+POSSession)");

/**
 * PunktePass – POS Dock Gateway (v1.5)
 * Unified entry for POS systems and Dock Bridge
 * - Supports: sale • redeem • bonus • stats • sync
 * - Accepts token via header / json / post / get / cookie
 * - Auto-bypass if POS session is active
 * - More robust param handling + debug logs
 */

class PPV_POS_DOCK {

    public static function hooks() {
        // Regisztráljuk a route-ot a rest_api_init-on
        add_action('rest_api_init', [__CLASS__, 'register_routes'], 10);
    }

    /** ============================================================
     *  🔹 Register unified endpoint
     * ============================================================ */
    public static function register_routes() {
        error_log("✅ [PPV_POS_DOCK] register_routes fired");
        register_rest_route('punktepass/v1', '/pos/dock', [
            'methods'  => ['POST', 'GET'],
            'callback' => [__CLASS__, 'handle_request'],
            'permission_callback' => [__CLASS__, 'validate_request']
        ]);
    }

    /** ============================================================
     *  🔐 Permission check (API key or POS token)
     *  Accepts:
     *   - Header: ppv-pos-token
     *   - Header: Authorization: Bearer <token>
     *   - GET/POST param: api_key / ppv_pos_token / store_key
     *   - Cookie: ppv_pos_token
     *   - Auto-bypass if POS session active (PPV_Session)
     * ============================================================ */
    public static function validate_request($req) {
        global $wpdb;

        // 1) Auto-bypass: if PPV_Session indicates POS is active
        if (class_exists('PPV_Session')) {
            try {
                if (method_exists('PPV_Session', 'current_store')) {
                    $cs = PPV_Session::current_store();
                    if (!empty($cs) && is_object($cs)) {
                        error_log("🔓 [PPV_POS_DOCK] validate_request: PPV_Session active, bypassing auth (store={$cs->id})");
                        return true;
                    }
                }
            } catch (Throwable $e) {
                error_log("⚠️ [PPV_POS_DOCK] PPV_Session check failed: " . $e->getMessage());
            }
        }

        // 2) Cookie / Header / Params fallback
        $headers = $req->get_headers();
        $headerToken = '';
        if (!empty($headers['ppv-pos-token'][0])) {
            $headerToken = sanitize_text_field($headers['ppv-pos-token'][0]);
        } elseif (!empty($headers['authorization'][0])) {
            // Accept Authorization: Bearer <token>
            if (preg_match('/Bearer\s+(.+)/i', $headers['authorization'][0], $m)) {
                $headerToken = sanitize_text_field($m[1]);
            }
        }

        $cookieToken = !empty($_COOKIE['ppv_pos_token']) ? sanitize_text_field($_COOKIE['ppv_pos_token']) : '';
        $getParams   = $req->get_query_params() ?: [];
        $postParams  = $req->get_json_params() ?: $req->get_body_params() ?: [];
        $paramToken  = sanitize_text_field($postParams['ppv_pos_token'] ?? $getParams['ppv_pos_token'] ?? $postParams['store_key'] ?? $getParams['store_key'] ?? '');
        $apiKeyParam = sanitize_text_field($postParams['api_key'] ?? $getParams['api_key'] ?? '');

        $tokenToCheck = $headerToken ?: $paramToken ?: $cookieToken;
        $apiToCheck   = $apiKeyParam;

        error_log("🔎 [PPV_POS_DOCK] validate_request tokens → header=" . ($headerToken ? 'yes' : 'no') . " param=" . ($paramToken ? 'yes' : 'no') . " cookie=" . ($cookieToken ? 'yes' : 'no') . " api=" . ($apiToCheck ? 'yes' : 'no'));

        // 3) DB checks
        if (!empty($tokenToCheck)) {
            $exists = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE (pos_token=%s OR store_key=%s) AND pos_enabled=1",
                $tokenToCheck, $tokenToCheck
            ));
            if ($exists > 0) {
                error_log("✅ [PPV_POS_DOCK] validate_request: token validated (store exists)");
                return true;
            }
        }

        if (!empty($apiToCheck)) {
            $exists = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE pos_api_key=%s AND pos_enabled=1",
                $apiToCheck
            ));
            if ($exists > 0) {
                error_log("✅ [PPV_POS_DOCK] validate_request: api_key validated (store exists)");
                return true;
            }
        }

        // nothing matched
        error_log("⛔ [PPV_POS_DOCK] validate_request: unauthorized");
        return false;
    }

    /** ============================================================
     *  🔹 Main request dispatcher
     * ============================================================ */
    public static function handle_request(WP_REST_Request $req) {
        global $wpdb;

        // robust param merging: JSON -> body -> query
        $json    = $req->get_json_params() ?: [];
        $body    = $req->get_body_params() ?: [];
        $query   = $req->get_query_params() ?: [];
        $params  = array_merge($query, $body, $json);

        $action = strtolower(sanitize_text_field($params['action'] ?? ''));
        $email  = sanitize_text_field($params['email'] ?? '');
        $apiKey = sanitize_text_field($params['api_key'] ?? '');
        $points = intval($params['points'] ?? 0);
        $reward_code = sanitize_text_field($params['reward_code'] ?? '');
        $transactions = $params['transactions'] ?? [];

        // Determine store by: api_key OR pos_token/store_key (param/header/cookie)
        $store = null;
        if (!empty($apiKey)) {
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT id, company_name, pos_api_key FROM {$wpdb->prefix}ppv_stores WHERE pos_api_key=%s LIMIT 1",
                $apiKey
            ));
        }

        if (!$store) {
            // fallback: token from header / param / cookie
            $headers = $req->get_headers();
            $headerToken = !empty($headers['ppv-pos-token'][0]) ? sanitize_text_field($headers['ppv-pos-token'][0]) : '';
            if (empty($headerToken) && !empty($headers['authorization'][0]) && preg_match('/Bearer\s+(.+)/i', $headers['authorization'][0], $m)) {
                $headerToken = sanitize_text_field($m[1]);
            }
            $cookieToken = !empty($_COOKIE['ppv_pos_token']) ? sanitize_text_field($_COOKIE['ppv_pos_token']) : '';
            $paramToken  = sanitize_text_field($params['ppv_pos_token'] ?? $params['store_key'] ?? $params['token'] ?? '');

            $tokenToCheck = $headerToken ?: $paramToken ?: $cookieToken;

            if (!empty($tokenToCheck)) {
                $store = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, company_name FROM {$wpdb->prefix}ppv_stores WHERE (pos_token=%s OR store_key=%s) AND pos_enabled=1 LIMIT 1",
                    $tokenToCheck, $tokenToCheck
                ));
            }
        }

        if (!$store) {
            error_log("⛔ [PPV_POS_DOCK] handle_request: store not found (action={$action})");
            return rest_ensure_response(['success' => false, 'message' => '❌ Invalid or inactive API key / token / store']);
        }

        $store_id = intval($store->id);

        // resolve user_id by email if present
        $user_id = 0;
        if (!empty($email)) {
            $user_id = intval($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email=%s LIMIT 1",
                $email
            )));
        }

        $response = ['success' => false, 'action' => $action];
        error_log("🧩 [PPV_POS_DOCK] Action={$action} | Store={$store_id} | User={$user_id}");

        switch ($action) {
            case 'sale':
                $response = self::handle_sale($user_id, $store_id, $points);
                break;
            case 'redeem':
                $response = self::handle_redeem($user_id, $store_id, $reward_code);
                break;
            case 'bonus':
                $response = self::handle_bonus($store_id);
                break;
            case 'stats':
                $response = self::handle_stats($store_id);
                break;
            case 'sync':
                $response = self::handle_sync($store_id, $transactions);
                break;
            default:
                $response['message'] = 'Unknown or missing action';
        }

        // Log transaction with more context
        self::log_transaction([
            'store_id' => $store_id,
            'email'    => $email,
            'message'  => $response['message'] ?? '',
            'status'   => $response['success'] ? 'ok' : 'error'
        ]);

        return rest_ensure_response($response);
    }

    /** ============================================================
     *  🛒 SALE – point add
     * ============================================================ */
    private static function handle_sale($user_id, $store_id, $points) {
        global $wpdb;
        if ($points <= 0 || !$user_id) {
            return ['success' => false, 'message' => 'Missing user or points'];
        }
        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id'  => $user_id,
            'store_id' => $store_id,
            'points'   => $points,
            'type'     => 'sale',
            'created'  => current_time('mysql')
        ]);
        return ['success' => true, 'message' => "+$points Punkte hinzugefügt"];
    }

    /** ============================================================
     *  🎁 REDEEM – reward redeem
     * ============================================================ */
    private static function handle_redeem($user_id, $store_id, $code) {
        global $wpdb;
        if (!$code || !$user_id) {
            return ['success' => false, 'message' => 'Missing reward code or user'];
        }
        $reward = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_rewards WHERE store_id=%d 
             AND (title LIKE %s OR action_value LIKE %s) LIMIT 1",
            $store_id, '%' . $wpdb->esc_like($code) . '%', '%' . $wpdb->esc_like($code) . '%'
        ));
        if (!$reward) return ['success' => false, 'message' => 'Reward not found'];

        $total = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d", $user_id
        ));
        if ($total < $reward->required_points) {
            return ['success' => false, 'message' => 'Not enough points'];
        }

        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id'   => $user_id,
            'store_id'  => $store_id,
            'points'    => -abs($reward->required_points),
            'type'      => 'redeem',
            'reference' => $reward->title,
            'created'   => current_time('mysql')
        ]);

        return [
            'success' => true,
            'message' => "🎁 {$reward->title} eingelöst",
            'reward'  => [
                'type'  => $reward->action_type,
                'value' => $reward->action_value
            ]
        ];
    }

    /** ============================================================
     *  ⭐ BONUS – active bonus day
     * ============================================================ */
    private static function handle_bonus($store_id) {
        global $wpdb;
        $today = date('Y-m-d');
        $bonus = $wpdb->get_row($wpdb->prepare("
            SELECT multiplier, extra_points FROM {$wpdb->prefix}ppv_bonus_days
            WHERE store_id=%d AND date=%s AND active=1 LIMIT 1",
            $store_id, $today
        ));
        if ($bonus) {
            return [
                'success' => true,
                'bonus' => [
                    'multiplier' => floatval($bonus->multiplier),
                    'extra_points' => intval($bonus->extra_points)
                ],
                'message' => "Bonus aktiv (x{$bonus->multiplier} +{$bonus->extra_points})"
            ];
        }
        return ['success' => true, 'bonus' => null, 'message' => 'Kein Bonus aktiv'];
    }

    /** ============================================================
     *  📊 STATS – today statistics
     * ============================================================ */
    private static function handle_stats($store_id) {
        global $wpdb;
        $today = date('Y-m-d');
        $scans = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points WHERE store_id=%d AND DATE(created)=%s",
            $store_id, $today
        ));
        $points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$wpdb->prefix}ppv_points WHERE store_id=%d AND DATE(created)=%s",
            $store_id, $today
        ));
        return [
            'success' => true,
            'stats' => ['today_scans' => $scans, 'today_points' => $points],
            'message' => 'Stats loaded'
        ];
    }

    /** ============================================================
     *  🔄 SYNC – offline transactions
     * ============================================================ */
    private static function handle_sync($store_id, $transactions) {
        global $wpdb;
        if (empty($transactions) || !is_array($transactions)) return ['success' => false, 'message' => 'No transactions'];

        $count = 0;
        foreach ($transactions as $t) {
            $email = sanitize_text_field($t['email'] ?? '');
            $points = intval($t['points'] ?? 0);
            if (!$email || !$points) continue;

            $uid = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email=%s LIMIT 1", $email
            ));
            if (!$uid) continue;

            $wpdb->insert("{$wpdb->prefix}ppv_points", [
                'user_id' => $uid,
                'store_id' => $store_id,
                'points' => $points,
                'type' => 'offline_sync',
                'created' => current_time('mysql')
            ]);
            $count++;
        }
        return ['success' => true, 'message' => "✅ $count offline transactions synced"];
    }

    /** ============================================================
     *  🧾 LOG
     * ============================================================ */
    private static function log_transaction($data) {
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

        $wpdb->insert("{$wpdb->prefix}ppv_pos_log", [
            'store_id'   => intval($data['store_id'] ?? 0),
            'user_id'    => intval($data['user_id'] ?? 0),
            'email'      => sanitize_email($data['email'] ?? ''),
            'message'    => sanitize_textarea_field($data['message'] ?? ''),
            'status'     => sanitize_text_field($data['status'] ?? 'ok'),
            'ip_address' => sanitize_text_field($data['ip_address'] ?? ''),
            'user_agent' => sanitize_text_field($data['user_agent'] ?? ''),
            'metadata'   => isset($data['metadata']) ? wp_json_encode($data['metadata']) : '',
            'created_at' => current_time('mysql')
        ]);
    }
}

// korai betöltés: priority 5
add_action('plugins_loaded', ['PPV_POS_DOCK', 'hooks'], 5);
