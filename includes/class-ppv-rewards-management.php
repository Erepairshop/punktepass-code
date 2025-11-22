<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Prämien Management (CRUD)
 * Version: 1.1 – FIXED + PPV_Lang Translation
 * Használat: [ppv_rewards_management]
 */

class PPV_Rewards_Management {

    public static function hooks() {
        add_shortcode('ppv_rewards_management', [__CLASS__, 'render_management_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    /** ============================================================
     *  📡 REST ENDPOINTS
     * ============================================================ */
    public static function register_rest_routes() {
        register_rest_route('ppv/v1', '/rewards/list', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_list_rewards'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        register_rest_route('ppv/v1', '/rewards/save', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_save_reward'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        register_rest_route('ppv/v1', '/rewards/delete', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_delete_reward'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        register_rest_route('ppv/v1', '/rewards/update', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_update_reward'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);
    }

    /** ============================================================
     *  🎨 ASSETS
     * ============================================================ */
    public static function enqueue_assets() {
        // 🔐 Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        wp_enqueue_script(
            'ppv-rewards-management',
            PPV_PLUGIN_URL . 'assets/js/ppv-rewards-management.js',
            ['jquery'],
            time(),
            true
        );

        $payload = [
            'base'   => esc_url(rest_url('ppv/v1/')),
            'nonce'  => wp_create_nonce('wp_rest'),
            'store_id' => self::get_store_id()
        ];

        wp_add_inline_script(
            'ppv-rewards-management',
            "window.ppv_rewards_mgmt = " . wp_json_encode($payload) . ";",
            'before'
        );

        // 🌍 FORDÍTÁSOK - ÚJ KÓDSOR!
        if (class_exists('PPV_Lang')) {
            wp_add_inline_script(
                'ppv-rewards-management',
                "window.ppv_lang = " . wp_json_encode(PPV_Lang::$strings) . ";",
                'before'
            );
        }
    }

    /** ============================================================
     *  🔍 GET STORE ID
     * ============================================================ */
    private static function get_store_id() {
        global $wpdb;

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return intval($_SESSION['ppv_current_filiale_id']);
        }

        // Session - base store
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        // Fallback: vendor store
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return intval($_SESSION['ppv_vendor_store_id']);
        }

        // Logged in user (WordPress user - rare case)
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                $uid
            ));
            if ($store_id) {
                return intval($store_id);
            }
        }

        return 0;
    }

    /** ============================================================
     *  🎨 FRONTEND RENDER
     * ============================================================ */
    public static function render_management_page() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        global $wpdb;
        $store_id = self::get_store_id();

        if (!$store_id) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('rewards_login_required') : 'Kérlek jelentkezz be vagy aktiváld a boltot.';
            return '<div class="ppv-warning">⚠️ ' . esc_html($msg) . '</div>';
        }

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT company_name, country FROM {$wpdb->prefix}ppv_stores WHERE id=%d",
            $store_id
        ));

        // Currency mapping
        $country = $store->country ?? 'DE';
        $currency_map = ['DE' => 'EUR', 'HU' => 'HUF', 'RO' => 'RON'];
        $currency = $currency_map[$country] ?? 'EUR';

        ob_start();
        ?>
        <script>window.PPV_STORE_ID = <?php echo intval($store_id); ?>;</script>

        <div class="ppv-rewards-management-wrapper glass-section">
            <h2 style="font-size: 18px; margin-bottom: 16px;"><i class="ri-gift-line"></i> <?php echo esc_html(PPV_Lang::t('rewards_title') ?: 'Jutalmak kezelése – '); ?><?php echo esc_html($store->company_name ?? 'Store'); ?></h2>

            <!-- CREATE/EDIT FORM -->
            <form id="ppv-reward-form" class="ppv-reward-form">
                <input type="hidden" id="reward-id" name="id" value="">
                
                <label><?php echo esc_html(PPV_Lang::t('rewards_form_title') ?: 'Cím *'); ?></label>
                <input type="text" name="title" id="reward-title" placeholder="<?php echo esc_attr(PPV_Lang::t('rewards_form_title_placeholder') ?: 'pl. 10% rabatt'); ?>" required>

                <label><?php echo esc_html(PPV_Lang::t('rewards_form_points') ?: 'Szükséges pontok *'); ?></label>
                <input type="number" name="required_points" id="reward-points" placeholder="<?php echo esc_attr(PPV_Lang::t('rewards_form_points_placeholder') ?: 'pl. 50'); ?>" min="1" required>

                <label><?php echo esc_html(PPV_Lang::t('rewards_form_description') ?: 'Leírás (opcionális)'); ?></label>
                <textarea name="description" id="reward-description" placeholder="<?php echo esc_attr(PPV_Lang::t('rewards_form_description_placeholder') ?: 'Részletek a jutalomról'); ?>"></textarea>

                <label><?php echo esc_html(PPV_Lang::t('rewards_form_type_label') ?: 'Jutalmazás típusa'); ?></label>
                <select name="action_type" id="reward-type">
                    <option value="discount_percent"><?php echo esc_html(PPV_Lang::t('rewards_form_type_percent') ?: 'Rabatt (%)'); ?></option>
                    <option value="discount_fixed"><?php echo esc_html(PPV_Lang::t('rewards_form_type_fixed') ?: 'Fix rabatt'); ?></option>
                    <option value="free_product"><?php echo esc_html(PPV_Lang::t('rewards_form_type_free') ?: 'Ingyenes termék'); ?></option>
                </select>

                <label><?php echo esc_html(sprintf(PPV_Lang::t('rewards_form_value') ?: 'Érték (%s) *', $currency)); ?></label>
                <input type="text" name="action_value" id="reward-value" placeholder="<?php echo esc_attr(PPV_Lang::t('rewards_form_value_placeholder') ?: 'pl. 10'); ?>" required>
                <small style="color: #999;">💶 <?php echo esc_html($currency); ?></small>

                <label><?php echo esc_html(PPV_Lang::t('rewards_form_points_given') ?: 'Pontok adott (ha beváltják) *'); ?></label>
                <input type="number" name="points_given" id="reward-points-given" placeholder="<?php echo esc_attr(PPV_Lang::t('rewards_form_points_given_placeholder') ?: 'pl. 5'); ?>" min="0" required>
                <small style="color: #999;">⭐ <?php echo esc_html(PPV_Lang::t('rewards_form_points_given_helper') ?: 'Ezek a pontok jutalmazzák az ügyfelet'); ?></small>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="ppv-btn-blue" id="save-btn">💾 <?php echo esc_html(PPV_Lang::t('rewards_form_save') ?: 'Mentés'); ?></button>
                    <button type="button" class="ppv-btn-outline" id="cancel-btn" style="display:none;"><?php echo esc_html(PPV_Lang::t('rewards_form_cancel') ?: 'Mégse'); ?></button>
                </div>
            </form>

            <!-- REWARDS LIST -->
            <div id="ppv-rewards-list" class="ppv-reward-grid">
                <p>⏳ <?php echo esc_html(PPV_Lang::t('rewards_list_loading') ?: 'Betöltés...'); ?></p>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    /** ============================================================
     *  📋 REST – List Rewards
     * ============================================================ */
    public static function rest_list_rewards($request) {
        global $wpdb;
        $store_id = intval($request->get_param('store_id') ?? 0);

        if (!$store_id) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('rewards_error_no_store') : 'Nincs Store ID';
            return new WP_REST_Response([
                'success' => false,
                'rewards' => [],
                'message' => '❌ ' . $msg
            ], 400);
        }

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_rewards
            WHERE store_id=%d
            ORDER BY required_points ASC, id DESC
        ", $store_id));

        return new WP_REST_Response([
            'success' => true,
            'rewards' => $rows ?: [],
            'message' => '✅ OK'
        ], 200);
    }

    /** ============================================================
     *  💾 REST – Save Reward (Create)
     * ============================================================ */
    public static function rest_save_reward($request) {
        global $wpdb;
        $data = $request->get_json_params();

        $store_id = intval($data['store_id'] ?? 0);
        $title    = sanitize_text_field($data['title'] ?? '');
        $points   = intval($data['required_points'] ?? 0);
        $points_given = intval($data['points_given'] ?? 0);
        $desc     = sanitize_textarea_field($data['description'] ?? '');
        $type     = sanitize_text_field($data['action_type'] ?? '');
        $value    = sanitize_text_field($data['action_value'] ?? '');

        // Currency automatikus
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT country FROM {$wpdb->prefix}ppv_stores WHERE id=%d",
            $store_id
        ));
        $country = $store->country ?? 'DE';
        $currency_map = ['DE' => 'EUR', 'HU' => 'HUF', 'RO' => 'RON'];
        $currency = $currency_map[$country] ?? 'EUR';

        if (!$store_id || !$title || $points <= 0) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('rewards_error_invalid') : 'Érvénytelen bevitel.';
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ ' . $msg
            ], 400);
        }

        $wpdb->insert("{$wpdb->prefix}ppv_rewards", [
            'store_id'        => $store_id,
            'title'           => $title,
            'required_points' => $points,
            'points_given'    => $points_given,
            'description'     => $desc,
            'action_type'     => $type,
            'action_value'    => $value,
            'currency'        => $currency,
            'created_at'      => current_time('mysql')
        ]);

        $msg = class_exists('PPV_Lang') ? PPV_Lang::t('rewards_saved') : 'Jutalmazás mentve.';
        return new WP_REST_Response([
            'success' => true,
            'message' => '✅ ' . $msg
        ], 200);
    }

    /** ============================================================
     *  ✏️ REST – Update Reward
     * ============================================================ */
    public static function rest_update_reward($request) {
        global $wpdb;
        $data = $request->get_json_params();

        $id       = intval($data['id'] ?? 0);
        $store_id = intval($data['store_id'] ?? 0);
        $title    = sanitize_text_field($data['title'] ?? '');
        $points   = intval($data['required_points'] ?? 0);
        $points_given = intval($data['points_given'] ?? 0);
        $desc     = sanitize_textarea_field($data['description'] ?? '');
        $type     = sanitize_text_field($data['action_type'] ?? '');
        $value    = sanitize_text_field($data['action_value'] ?? '');

        // Currency automatikus
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT country FROM {$wpdb->prefix}ppv_stores WHERE id=%d",
            $store_id
        ));
        $country = $store->country ?? 'DE';
        $currency_map = ['DE' => 'EUR', 'HU' => 'HUF', 'RO' => 'RON'];
        $currency = $currency_map[$country] ?? 'EUR';

        if (!$id || !$store_id || !$title || $points <= 0) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('rewards_error_invalid') : 'Érvénytelen bevitel.';
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ ' . $msg
            ], 400);
        }

        $wpdb->update(
            "{$wpdb->prefix}ppv_rewards",
            [
                'title'           => $title,
                'required_points' => $points,
                'points_given'    => $points_given,
                'description'     => $desc,
                'action_type'     => $type,
                'action_value'    => $value,
                'currency'        => $currency
            ],
            [
                'id'       => $id,
                'store_id' => $store_id
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s'],
            ['%d', '%d']
        );

        $msg = class_exists('PPV_Lang') ? PPV_Lang::t('rewards_updated') : 'Jutalmazás frissítve.';
        return new WP_REST_Response([
            'success' => true,
            'message' => '✅ ' . $msg
        ], 200);
    }

    /** ============================================================
     *  🗑️ REST – Delete Reward
     * ============================================================ */
    public static function rest_delete_reward($request) {
        global $wpdb;
        $data = $request->get_json_params();
        $store_id = intval($data['store_id'] ?? 0);
        $id = intval($data['id'] ?? 0);

        if (!$store_id || !$id) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('rewards_error_missing') : 'Hiányzó adat.';
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ ' . $msg
            ], 400);
        }

        $wpdb->delete(
            "{$wpdb->prefix}ppv_rewards",
            ['id' => $id, 'store_id' => $store_id]
        );

        $msg = class_exists('PPV_Lang') ? PPV_Lang::t('rewards_deleted') : 'Jutalmazás törölve.';
        return new WP_REST_Response([
            'success' => true,
            'message' => '🗑️ ' . $msg
        ], 200);
    }
}

PPV_Rewards_Management::hooks();