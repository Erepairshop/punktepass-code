<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Händler / POS Reward Redeem System
 * Version: 2.0 – REST API Stable
 */

class PPV_Redeem {

    public static function hooks() {
        add_shortcode('ppv_redeem_rewards', [__CLASS__, 'render_redeem_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    /** ============================================================
     *  🔹 REGISTER REST ROUTES
     * ============================================================ */
    public static function register_rest_routes() {
        register_rest_route('ppv/v1', '/pos/redeem', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_pos_redeem'],
            'permission_callback' => '__return_true'
        ]);
    }

    /** ============================================================
     *  🔹 ASSETS
     * ============================================================ */
    public static function enqueue_assets() {
        wp_enqueue_style('ppv-redeem', PPV_PLUGIN_URL . 'assets/css/ppv-redeem.css', [], time());
        wp_enqueue_script('ppv-redeem', PPV_PLUGIN_URL . 'assets/js/ppv-redeem.js', ['jquery'], time(), true);
        $__data = is_array([
            'rest_url' => esc_url(rest_url('ppv/v1/pos/redeem')),
            'nonce'    => wp_create_nonce('wp_rest')
        ] ?? null) ? [
            'rest_url' => esc_url(rest_url('ppv/v1/pos/redeem')),
            'nonce'    => wp_create_nonce('wp_rest')
        ] : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('ppv-redeem', "window.ppv_redeem = {$__json};", 'before');
    }

    /** ============================================================
     *  🔹 REST API: POS REDEEM HANDLER
     * ============================================================ */
    public static function rest_pos_redeem($request) {
        global $wpdb;

        $params = $request->get_json_params();
        $store_id  = intval($params['store_id'] ?? ($_SESSION['ppv_store_id'] ?? 0));
$user_id   = intval($params['user_id'] ?? ($_SESSION['ppv_user_id'] ?? get_current_user_id()));
$reward_id = intval($params['reward_id'] ?? 0);


        if (!$store_id || !$user_id || !$reward_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Ungültige Anfrage.'], 400);
        }

        $points_table  = $wpdb->prefix . 'ppv_points';
        $rewards_table = $wpdb->prefix . 'ppv_rewards';
        $requests_table = $wpdb->prefix . 'ppv_reward_requests';

        // 🔹 Reward lekérése
        $reward = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $rewards_table WHERE id=%d AND store_id=%d
        ", $reward_id, $store_id));

        if (!$reward) {
            return new WP_REST_Response(['success' => false, 'message' => 'Prämie nicht gefunden.'], 404);
        }

        // 🔹 Pont ellenőrzés
        $user_points = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points),0) FROM $points_table WHERE user_id=%d
        ", $user_id));

        if ($user_points < $reward->required_points) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Nicht genügend Punkte (' . $user_points . ' / ' . $reward->required_points . ')'
            ], 403);
        }
        // 🔹 Ellenőrizzük, van-e már függő vagy friss redeem
$existing = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) FROM $requests_table
    WHERE user_id=%d AND reward_title=%s AND store_id=%d
    AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
", $user_id, $reward->title, $store_id));

if ($existing > 0) {
    return new WP_REST_Response([
        'success' => false,
        'message' => '⚠️ Es gibt bereits eine offene Anfrage.'
    ], 409);
}


        // 🔹 Pont levonás
        $wpdb->insert($points_table, [
            'user_id'   => $user_id,
            'store_id'  => $store_id,
            'points'    => -intval($reward->required_points),
            'type'      => 'redeem',
            'reference' => 'POS-REWARD-' . $reward->id,
            'created'   => current_time('mysql')
        ]);

        
// 🔹 Reward Request log (biztosan illeszkedik az adatbázis oszlopaihoz)
$wpdb->insert($requests_table, [
    'store_id'   => $store_id,
    'user_id'    => $user_id,
    'reward_id'  => $reward->id,
    'status'     => 'approved',
    'created_at' => current_time('mysql'),
    'updated_at' => current_time('mysql')
]);


        // 🔹 Reward státusz frissítése
        $wpdb->update($rewards_table, [
            'redeemed'     => intval($reward->redeemed) + 1,
            'redeemed_by'  => $user_id,
            'redeemed_at'  => current_time('mysql')
        ], ['id' => $reward->id]);

     return new WP_REST_Response([
    'success' => true,
    'message' => '✅ Prämie erfolgreich eingelöst.',
    'user_id' => $user_id,
    'store_id' => $store_id,
    'new_balance' => $user_points - $reward->required_points
], 200);

    }

    /** ============================================================
     *  🔹 RENDER FRONTEND (POS)
     * ============================================================ */
    public static function render_redeem_page() {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        global $wpdb;

        $store_id = $_SESSION['ppv_store_id'] ?? 0;
        if (!$store_id) {
            return '<p style="color:white;text-align:center;padding:40px;">
                ⚠️ Kein aktiver Store gefunden. Bitte anmelden.
            </p>';
        }

        $store = $wpdb->get_row($wpdb->prepare("
            SELECT id, company_name FROM {$wpdb->prefix}ppv_stores WHERE id=%d
        ", $store_id));

        $rewards = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_rewards
            WHERE store_id=%d ORDER BY required_points ASC
        ", $store_id));

        ob_start(); ?>
        
        <div class="ppv-redeem-wrapper">

            <h2 style="text-align:center;margin-bottom:20px;">
                🎁 POS-Prämien – <?php echo esc_html($store->company_name); ?>
            </h2>

            <!-- USER ID INPUT -->
            <div class="ppv-user-input-box">
                <label for="ppv-pos-user-id">👤 User-ID eingeben:</label>
                <input type="number" id="ppv-pos-user-id" placeholder="User-ID" min="1" />
            </div>

            <div class="ppv-rewards-grid">
                <?php if (!empty($rewards)) : ?>
                    <?php foreach ($rewards as $reward) : ?>
                        <div class="ppv-reward-card glass-card">

                            <h3><?php echo esc_html($reward->title); ?></h3>
                            <p><?php echo esc_html($reward->description); ?></p>

                            <div class="ppv-reward-meta">
                                <strong>🌟 <?php echo intval($reward->required_points); ?> Punkte</strong><br>
                                <small><?php echo esc_html($reward->action_type); ?>: <?php echo esc_html($reward->action_value); ?></small>
                            </div>

                            <button class="ppv-pos-redeem-btn"
                                data-id="<?php echo intval($reward->id); ?>">
                                💳 Einlösen
                            </button>

                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p style="text-align:center;">ℹ️ Keine Prämien verfügbar.</p>
                <?php endif; ?>
            </div>

            <div id="ppv-pos-result" class="ppv-result-box"></div>
        </div>

        <?php
        return ob_get_clean();
    }

}

PPV_Redeem::hooks();
