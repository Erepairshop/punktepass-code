<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Main REST API (User + POS Unified)
 * Version: 4.3 Stable
 * Author: Erik Borota / PunktePass
 */

class PPV_API {

    /** ============================================================
     * 🔹 Hook registration
     * ============================================================ */
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /** ============================================================
     * 🔐 Permission check (WP user or POS token)
     * ============================================================ */
    public static function verify_pos_or_user($request = null) {
        global $wpdb;

        // Admin → mindig engedélyezett
        if (is_user_logged_in() && current_user_can('manage_options')) return true;

        // Token headerből / GET / cookie
        $token = '';
        if ($request) $token = $request->get_header('ppv-pos-token');
        if (empty($token) && isset($_GET['pos_token'])) $token = sanitize_text_field($_GET['pos_token']);
        if (empty($token) && isset($_COOKIE['ppv_pos_token'])) $token = sanitize_text_field($_COOKIE['ppv_pos_token']);
        if (empty($token)) return false;

        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE pos_token=%s AND pos_enabled=1",
            $token
        ));
        return $exists > 0;
    }

    /** ============================================================
     * 🔹 Register REST routes
     * ============================================================ */
    public static function register_routes() {

        register_rest_route('punktepass/v1', '/check-user', [
            'methods' => ['POST', 'GET'],
            'callback' => [__CLASS__, 'check_user'],
            'permission_callback' => [__CLASS__, 'verify_pos_or_user']
        ]);

        register_rest_route('punktepass/v1', '/add-points', [
            'methods' => ['POST', 'GET'],
            'callback' => [__CLASS__, 'add_points'],
            'permission_callback' => [__CLASS__, 'verify_pos_or_user']
        ]);

        register_rest_route('punktepass/v1', '/mypoints', [
            'methods' => ['GET'],
            'callback' => [__CLASS__, 'get_mypoints'],
            'permission_callback' => [__CLASS__, 'verify_pos_or_user']
        ]);

        register_rest_route('punktepass/v1', '/repair-bonus', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'repair_bonus'],
            'permission_callback' => [__CLASS__, 'verify_store_api_key']
        ]);
    }

    /** ============================================================
     * 🔐 Verify store API key (for external integrations)
     * ============================================================ */
    public static function verify_store_api_key($request = null) {
        global $wpdb;

        $api_key = '';
        // Try header first (X-Api-Key)
        if ($request) $api_key = $request->get_header('x-api-key');
        // Fallback: JSON body api_key
        if (empty($api_key) && $request) {
            $params = $request->get_json_params();
            $api_key = sanitize_text_field($params['api_key'] ?? '');
        }
        // Fallback: GET parameter
        if (empty($api_key) && isset($_GET['api_key'])) {
            $api_key = sanitize_text_field($_GET['api_key']);
        }

        if (empty($api_key)) {
            ppv_log("❌ [PPV_API] verify_store_api_key: No API key found in header/body/GET");
            return false;
        }

        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE pos_api_key=%s AND active=1",
            $api_key
        ));

        if (!$exists) {
            ppv_log("❌ [PPV_API] verify_store_api_key: Invalid API key: " . substr($api_key, 0, 8) . "...");
        }

        return $exists > 0;
    }

    /** ============================================================
     * 🔹 1️⃣ Check user points
     * ============================================================ */
    public static function check_user($req) {
        global $wpdb;
        $params = $req->get_json_params();
        $email   = sanitize_text_field($params['email'] ?? ($_GET['email'] ?? ''));
        $user_id = intval($params['user_id'] ?? ($_GET['user_id'] ?? 0));

        if ($email && !$user_id) {
            $user_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email=%s LIMIT 1", $email
            ));
        }
        if (!$user_id) return ['status'=>'error','message'=>'User not found'];

        $total_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points),0) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d", $user_id
        ));

        return [
            'status'=>'ok',
            'user_id'=>$user_id,
            'email'=>$email,
            'total_points'=>$total_points
        ];
    }

    /** ============================================================
     * 🔹 2️⃣ Add points (POS or user)
     * ============================================================ */
    public static function add_points($req) {
        global $wpdb;
        $params = $req->get_json_params();
        if (empty($params)) $params = $req->get_params();

        $email    = sanitize_text_field($params['email'] ?? ($_GET['email'] ?? ''));
        $user_id  = intval($params['user_id'] ?? ($_GET['user_id'] ?? 0));
        $store_id = intval($params['store_id'] ?? ($_GET['store_id'] ?? 0));
        $points   = intval($params['points'] ?? ($_GET['points'] ?? 0));
        $store_key = sanitize_text_field($params['store_key'] ?? ($_GET['store_key'] ?? ''));

        /** 🩵 Auto session restore via PPV_Session */
        if (class_exists('PPV_Session')) {
            $store = PPV_Session::current_store();
            if (!$store && $store_key) {
                $store = PPV_Session::get_store_by_key($store_key);
                if ($store) {
                    $store_id = intval($store->id);
                    $_SESSION['ppv_active_store'] = $store_id;
                    $_SESSION['ppv_is_pos'] = true;
                    $GLOBALS['ppv_active_store'] = $store;
                    $GLOBALS['ppv_is_pos'] = true;
                }
            }
        }

        if ($email && !$user_id) {
            $user_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email=%s LIMIT 1", $email
            ));
        }

        if (!$user_id || !$points) {
            return ['status'=>'error','message'=>'Missing user or points'];
        }

        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id'=>$user_id,
            'store_id'=>$store_id,
            'points'=>$points,
            'created'=>current_time('mysql')
        ]);

        // Update lifetime_points for VIP level calculation (only for positive points)
        if (class_exists('PPV_User_Level') && $points > 0) {
            PPV_User_Level::add_lifetime_points($user_id, $points);
        }

        $new_total = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points),0) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d", $user_id
        ));

        return [
            'status'=>'ok',
            'user_id'=>$user_id,
            'email'=>$email,
            'points_added'=>$points,
            'total_points'=>$new_total,
            'store_id'=>$store_id
        ];
    }

    /** ============================================================
     * 🔹 3️⃣ Repair Bonus (external integration)
     * Creates user if needed, adds bonus points, sends email
     * ============================================================ */
    public static function repair_bonus($req) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $params = $req->get_json_params();
        $email     = sanitize_email($params['email'] ?? '');
        $name      = sanitize_text_field($params['name'] ?? '');
        $store_id  = intval($params['store_id'] ?? 0);
        $points    = intval($params['points'] ?? 2);
        $reference = sanitize_text_field($params['reference'] ?? 'Reparatur-Formular Bonus');

        if (empty($email) || !is_email($email)) {
            return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid email'], 400);
        }
        if (!$store_id) {
            return new WP_REST_Response(['status' => 'error', 'message' => 'Missing store_id'], 400);
        }

        // Verify store exists
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, email as store_email FROM {$prefix}ppv_stores WHERE id=%d AND active=1", $store_id
        ));
        if (!$store) {
            return new WP_REST_Response(['status' => 'error', 'message' => 'Store not found'], 404);
        }

        // Check if user exists
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, first_name FROM {$prefix}ppv_users WHERE email=%s LIMIT 1", $email
        ));

        $is_new_user = false;
        $generated_password = null;

        if (!$user) {
            // Create new user
            $is_new_user = true;
            $generated_password = wp_generate_password(8, false, false);
            $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
            $qr_token = wp_generate_password(10, false, false);
            $login_token = bin2hex(random_bytes(32));

            $name_parts = explode(' ', trim($name), 2);
            $first_name = $name_parts[0] ?? '';
            $last_name  = $name_parts[1] ?? '';

            $wpdb->insert("{$prefix}ppv_users", [
                'email'        => $email,
                'password'     => $password_hash,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => trim($name) ?: $first_name,
                'qr_token'     => $qr_token,
                'login_token'  => $login_token,
                'user_type'    => 'user',
                'active'       => 1,
                'created_at'   => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
            ], ['%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s']);

            $user_id = $wpdb->insert_id;

            if (!$user_id) {
                ppv_log("❌ [PPV_API] repair_bonus: Failed to create user: " . $wpdb->last_error);
                return new WP_REST_Response(['status' => 'error', 'message' => 'User creation failed'], 500);
            }

            ppv_log("✅ [PPV_API] repair_bonus: Created user #{$user_id} ({$email}) for store #{$store_id}");
        } else {
            $user_id = (int) $user->id;
        }

        // Add bonus points
        $wpdb->insert("{$prefix}ppv_points", [
            'user_id'   => $user_id,
            'store_id'  => $store_id,
            'points'    => $points,
            'type'      => 'bonus',
            'reference' => $reference,
            'created'   => current_time('mysql'),
        ], ['%d','%d','%d','%s','%s','%s']);

        // Update lifetime_points
        if (class_exists('PPV_User_Level') && $points > 0) {
            PPV_User_Level::add_lifetime_points($user_id, $points);
        }

        // Get total points for this store
        $total_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points),0) FROM {$prefix}ppv_points WHERE user_id=%d AND store_id=%d",
            $user_id, $store_id
        ));

        ppv_log("✅ [PPV_API] repair_bonus: +{$points} points for user #{$user_id} at store #{$store_id} (total: {$total_points})");

        // Send email
        $first_name = $is_new_user ? (explode(' ', trim($name))[0] ?: 'Kunde') : ($user->first_name ?: 'Kunde');
        if ($is_new_user) {
            self::send_repair_welcome_email($email, $first_name, $generated_password, $points, $total_points, $store->name);
        } else {
            self::send_repair_points_email($email, $first_name, $points, $total_points, $store->name);
        }

        return new WP_REST_Response([
            'status'       => 'ok',
            'is_new_user'  => $is_new_user,
            'user_id'      => $user_id,
            'points_added' => $points,
            'total_points' => $total_points,
        ]);
    }

    /** ============================================================
     * 📧 Welcome email for new repair bonus user
     * ============================================================ */
    private static function send_repair_welcome_email($email, $first_name, $password, $points, $total_points, $store_name) {
        $points_to_reward = max(0, 4 - $total_points);

        $subject = "Willkommen bei PunktePass – {$points} Bonuspunkte von {$store_name}!";

        $body = '<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<div style="max-width:560px;margin:0 auto;padding:20px;">
<div style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:16px 16px 0 0;padding:32px 28px;text-align:center;">
    <h1 style="color:#fff;font-size:24px;margin:0 0 8px;">Willkommen bei PunktePass!</h1>
    <p style="color:rgba(255,255,255,0.85);font-size:14px;margin:0;">Ihr Treuekonto bei ' . esc_html($store_name) . '</p>
</div>
<div style="background:#fff;padding:32px 28px;border-radius:0 0 16px 16px;">
    <p style="font-size:16px;color:#1f2937;margin:0 0 20px;">Hallo <strong>' . esc_html($first_name) . '</strong>,</p>
    <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
        vielen Dank für Ihren Reparaturauftrag bei ' . esc_html($store_name) . '! Wir haben automatisch ein
        <strong>PunktePass-Konto</strong> für Sie erstellt, mit dem Sie bei jedem Besuch Treuepunkte sammeln können.
    </p>
    <div style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border:1px solid #bbf7d0;border-radius:12px;padding:20px;text-align:center;margin:0 0 24px;">
        <div style="font-size:36px;font-weight:800;color:#059669;">+' . $points . ' Punkte</div>
        <div style="font-size:13px;color:#6b7280;margin-top:4px;">wurden Ihrem Konto gutgeschrieben</div>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #d1fae5;font-size:13px;color:#374151;">
            Gesamt: <strong>' . $total_points . ' / 4 Punkte</strong>'
            . ($points_to_reward > 0 ? ' &mdash; noch ' . $points_to_reward . ' bis zum <strong>10&euro; Rabatt!</strong>' : ' &mdash; <strong style="color:#059669;">10&euro; Rabatt einlösbar!</strong>')
        . '</div>
    </div>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:0 0 24px;">
        <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:12px;text-transform:uppercase;letter-spacing:0.5px;">Ihre Zugangsdaten</div>
        <table style="width:100%;font-size:14px;color:#1f2937;">
            <tr><td style="padding:6px 0;color:#6b7280;width:100px;">E-Mail:</td><td style="padding:6px 0;font-weight:600;">' . esc_html($email) . '</td></tr>
            <tr><td style="padding:6px 0;color:#6b7280;">Passwort:</td><td style="padding:6px 0;font-weight:600;font-family:monospace;letter-spacing:1px;">' . esc_html($password) . '</td></tr>
        </table>
    </div>
    <p style="font-size:13px;color:#6b7280;line-height:1.6;margin:0 0 24px;">
        Mit der PunktePass-App können Sie Ihren QR-Code vorzeigen und bei jedem Besuch Punkte sammeln.
        Melden Sie sich einfach auf <strong>punktepass.de</strong> mit Ihren Zugangsdaten an.
    </p>
    <div style="text-align:center;">
        <a href="https://punktepass.de" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;font-size:15px;">
            Jetzt bei PunktePass anmelden
        </a>
    </div>
</div>
<div style="text-align:center;padding:20px;font-size:12px;color:#9ca3af;">
    <p>' . esc_html($store_name) . ' &middot; powered by PunktePass</p>
</div>
</div></body></html>';

        wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    /** ============================================================
     * 📧 Points notification email for existing user
     * ============================================================ */
    private static function send_repair_points_email($email, $first_name, $points, $total_points, $store_name) {
        $points_to_reward = max(0, 4 - $total_points);

        $subject = "+{$points} Bonuspunkte auf Ihr PunktePass-Konto!";

        $body = '<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<div style="max-width:560px;margin:0 auto;padding:20px;">
<div style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);border-radius:16px 16px 0 0;padding:32px 28px;text-align:center;">
    <div style="font-size:48px;font-weight:800;color:#fff;">+' . $points . '</div>
    <p style="color:rgba(255,255,255,0.9);font-size:15px;margin:8px 0 0;font-weight:500;">Bonuspunkte gutgeschrieben</p>
</div>
<div style="background:#fff;padding:32px 28px;border-radius:0 0 16px 16px;">
    <p style="font-size:16px;color:#1f2937;margin:0 0 20px;">Hallo <strong>' . esc_html($first_name) . '</strong>,</p>
    <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
        vielen Dank für Ihren Reparaturauftrag bei ' . esc_html($store_name) . '! Wir haben
        <strong>' . $points . ' Bonuspunkte</strong> auf Ihr PunktePass-Konto gutgeschrieben.
    </p>
    <div style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border:1px solid #bbf7d0;border-radius:12px;padding:20px;text-align:center;margin:0 0 24px;">
        <div style="font-size:13px;color:#6b7280;margin-bottom:4px;">Ihr Punktestand bei ' . esc_html($store_name) . '</div>
        <div style="font-size:36px;font-weight:800;color:#059669;">' . $total_points . ' Punkte</div>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #d1fae5;font-size:13px;color:#374151;">'
            . ($points_to_reward > 0 ? 'Noch ' . $points_to_reward . ' Punkte bis zum <strong>10&euro; Rabatt!</strong>' : '<strong style="color:#059669;">10&euro; Rabatt einlösbar!</strong>')
        . '</div>
    </div>
    <div style="text-align:center;">
        <a href="https://punktepass.de" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;font-size:15px;">Punkte ansehen</a>
    </div>
</div>
<div style="text-align:center;padding:20px;font-size:12px;color:#9ca3af;">
    <p>' . esc_html($store_name) . ' &middot; powered by PunktePass</p>
</div>
</div></body></html>';

        wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    /** ============================================================
     * 🔹 4️⃣ MyPoints data + translations
     * ============================================================ */
    public static function get_mypoints($req) {
        global $wpdb;
        if (class_exists('PPV_Lang')) PPV_Lang::boot();

        $user_id = intval($req->get_param('user_id'));
        if (!$user_id && is_user_logged_in()) $user_id = get_current_user_id();
        if (!$user_id) return ['status'=>'error','message'=>PPV_Lang::t('please_login')];

        $points_total = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points),0) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d", $user_id
        ));

        $avg_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT ROUND(AVG(points)) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d", $user_id
        ));

        $best_day = $wpdb->get_row($wpdb->prepare(
            "SELECT DATE(created) as day, SUM(points) as total 
             FROM {$wpdb->prefix}ppv_points 
             WHERE user_id=%d GROUP BY DATE(created)
             ORDER BY total DESC LIMIT 1", $user_id
        ));

        $top_store = $wpdb->get_row($wpdb->prepare(
            "SELECT s.company_name as store_name, SUM(p.points) as total 
             FROM {$wpdb->prefix}ppv_points p
             LEFT JOIN {$wpdb->prefix}ppv_stores s ON p.store_id=s.id
             WHERE p.user_id=%d GROUP BY p.store_id
             ORDER BY total DESC LIMIT 1", $user_id
        ));

        $labels = [
            'title'=>PPV_Lang::t('title'),
            'total'=>PPV_Lang::t('total'),
            'avg'=>PPV_Lang::t('avg'),
            'best_day'=>PPV_Lang::t('best_day'),
            'top_store'=>PPV_Lang::t('top_store'),
            'next_reward'=>PPV_Lang::t('next_reward'),
            'remaining'=>PPV_Lang::t('remaining'),
            'top3'=>PPV_Lang::t('top3'),
            'recent'=>PPV_Lang::t('recent'),
            'motivation'=>PPV_Lang::t('motivation'),
        ];

        return [
            'status'=>'ok',
            'labels'=>$labels,
            'data'=>[
                'total'=>$points_total,
                'avg'=>$avg_points,
                'top_day'=>$best_day,
                'top_store'=>$top_store,
            ]
        ];
    }
}

// ============================================================
// 🔹 Init
// ============================================================
add_action('plugins_loaded', function() {
    if (class_exists('PPV_API')) PPV_API::hooks();
});
