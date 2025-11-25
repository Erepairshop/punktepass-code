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

        // 📡 Ably config
        $ably_config = null;
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            $ably_config = [
                'key' => PPV_Ably::get_key(),
                'channel' => 'store-' . self::get_store_id()
            ];
        }

        $payload = [
            'base'   => esc_url(rest_url('ppv/v1/')),
            'nonce'  => wp_create_nonce('wp_rest'),
            'store_id' => self::get_store_id(),
            'ably' => $ably_config
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
     *  🏪 GET VENDOR STORE ID (parent/main store)
     * ============================================================ */
    private static function get_vendor_store_id() {
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return intval($_SESSION['ppv_vendor_store_id']);
        }
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }
        return 0;
    }

    /** ============================================================
     *  🏢 GET ALL FILIALEN FOR VENDOR
     * ============================================================ */
    private static function get_filialen_for_vendor() {
        global $wpdb;
        $vendor_store_id = self::get_vendor_store_id();

        if (!$vendor_store_id) {
            return [];
        }

        // Get all stores: parent + children
        $filialen = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, company_name, address, city, plz
            FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d OR parent_store_id = %d
            ORDER BY (id = %d) DESC, name ASC
        ", $vendor_store_id, $vendor_store_id, $vendor_store_id));

        return $filialen ?: [];
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

        // 🏢 Get all filialen for this vendor
        $filialen = self::get_filialen_for_vendor();
        $has_multiple_filialen = count($filialen) > 1;

        ob_start();
        ?>
        <script>
            window.PPV_STORE_ID = <?php echo intval($store_id); ?>;
            window.PPV_FILIALEN = <?php echo wp_json_encode($filialen); ?>;
            window.PPV_HAS_MULTIPLE_FILIALEN = <?php echo $has_multiple_filialen ? 'true' : 'false'; ?>;
        </script>

        <div class="ppv-rewards-management-wrapper">
            <h2>🎁 <?php echo esc_html(PPV_Lang::t('rewards_title') ?: 'Jutalmak kezelése – '); ?><?php echo esc_html($store->company_name ?? 'Store'); ?></h2>

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

                <!-- 📅 CAMPAIGN DATE FIELDS -->
                <div class="ppv-campaign-section" style="margin-top: 15px; padding: 15px; background: rgba(255,165,0,0.1); border: 1px dashed rgba(255,165,0,0.3); border-radius: 8px;">
                    <label style="font-weight: 600; color: #f97316; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="is_campaign" id="reward-is-campaign" value="1" style="width: 18px; height: 18px;">
                        <span><i class="ri-calendar-event-line"></i> <?php echo esc_html(PPV_Lang::t('rewards_form_campaign') ?: 'Kampány (időkorlátos)'); ?></span>
                    </label>
                    <small style="color: #999; margin-left: 26px; display: block; margin-bottom: 10px;">
                        <?php echo esc_html(PPV_Lang::t('rewards_form_campaign_hint') ?: 'Csak adott időszakban érhető el'); ?>
                    </small>

                    <div id="campaign-date-fields" style="display: none; margin-top: 12px;">
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 150px;">
                                <label style="font-size: 0.85em; color: #ccc;"><?php echo esc_html(PPV_Lang::t('rewards_form_start_date') ?: 'Kezdő dátum'); ?></label>
                                <input type="date" name="start_date" id="reward-start-date" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.2); color: #fff;">
                            </div>
                            <div style="flex: 1; min-width: 150px;">
                                <label style="font-size: 0.85em; color: #ccc;"><?php echo esc_html(PPV_Lang::t('rewards_form_end_date') ?: 'Befejező dátum'); ?></label>
                                <input type="date" name="end_date" id="reward-end-date" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.2); color: #fff;">
                            </div>
                        </div>
                        <small style="color: #f97316; margin-top: 8px; display: block;">
                            <i class="ri-information-line"></i> <?php echo esc_html(PPV_Lang::t('rewards_form_campaign_dates_hint') ?: 'Hagyd üresen a kezdő dátumot ha azonnal aktív, vagy a befejezőt ha nincs lejárat'); ?>
                        </small>
                    </div>
                </div>

                <?php if ($has_multiple_filialen): ?>
                <!-- 🏢 FILIALE SELECTOR -->
                <div class="ppv-filiale-selector" style="margin-top: 15px; padding: 15px; background: rgba(0,230,255,0.1); border-radius: 8px;">
                    <label style="font-weight: 600; color: #00e6ff;">
                        <i class="ri-store-2-line"></i> <?php echo esc_html(PPV_Lang::t('rewards_form_filiale') ?: 'Melyik filiálé(k)nak?'); ?>
                    </label>

                    <select name="target_store_id" id="reward-target-store" style="margin-top: 8px;">
                        <option value="current"><?php echo esc_html(PPV_Lang::t('rewards_form_filiale_current') ?: 'Csak ez a filiálé'); ?> (<?php echo esc_html($store->company_name ?? ''); ?>)</option>
                        <?php foreach ($filialen as $fil): ?>
                            <?php if (intval($fil->id) !== $store_id): ?>
                            <option value="<?php echo intval($fil->id); ?>">
                                <?php echo esc_html($fil->company_name ?: $fil->name); ?>
                                <?php if ($fil->city): ?>(<?php echo esc_html($fil->city); ?>)<?php endif; ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>

                    <div style="margin-top: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="apply_to_all" id="reward-apply-all" value="1" style="width: 18px; height: 18px;">
                            <span style="color: #34d399; font-weight: 500;">
                                <i class="ri-checkbox-multiple-line"></i>
                                <?php echo esc_html(PPV_Lang::t('rewards_form_apply_all') ?: 'Alkalmazás az összes filiáléra'); ?>
                            </span>
                        </label>
                        <small style="color: #999; margin-left: 26px;"><?php echo esc_html(PPV_Lang::t('rewards_form_apply_all_hint') ?: 'Ugyanez a jutalom létrejön minden filiálénál'); ?></small>
                    </div>
                </div>
                <?php endif; ?>

                <div style="display: flex; gap: 10px; margin-top: 15px;">
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

        // 📅 Campaign fields
        $is_campaign = !empty($data['is_campaign']) ? 1 : 0;
        $start_date  = !empty($data['start_date']) ? sanitize_text_field($data['start_date']) : null;
        $end_date    = !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null;

        // 🏢 Filiale options
        $target_store_id = sanitize_text_field($data['target_store_id'] ?? 'current');
        $apply_to_all = !empty($data['apply_to_all']);

        if (!$store_id || !$title || $points <= 0) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('rewards_error_invalid') : 'Érvénytelen bevitel.';
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ ' . $msg
            ], 400);
        }

        // 🏢 Determine which stores to create reward for
        $target_stores = [];

        if ($apply_to_all) {
            // Get all filialen for this vendor
            $filialen = self::get_filialen_for_vendor();
            foreach ($filialen as $fil) {
                $target_stores[] = intval($fil->id);
            }
        } elseif ($target_store_id !== 'current' && is_numeric($target_store_id)) {
            // Specific filiale selected
            $target_stores[] = intval($target_store_id);
        } else {
            // Current store only
            $target_stores[] = $store_id;
        }

        // Create reward for each target store
        $created_count = 0;
        foreach ($target_stores as $target_id) {
            // Get currency for this store
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT country FROM {$wpdb->prefix}ppv_stores WHERE id=%d",
                $target_id
            ));
            $country = $store->country ?? 'DE';
            $currency_map = ['DE' => 'EUR', 'HU' => 'HUF', 'RO' => 'RON'];
            $currency = $currency_map[$country] ?? 'EUR';

            $wpdb->insert("{$wpdb->prefix}ppv_rewards", [
                'store_id'        => $target_id,
                'title'           => $title,
                'required_points' => $points,
                'points_given'    => $points_given,
                'description'     => $desc,
                'action_type'     => $type,
                'action_value'    => $value,
                'currency'        => $currency,
                'active'          => 1,  // FIX: Set active by default!
                'start_date'      => $start_date,
                'end_date'        => $end_date,
                'is_campaign'     => $is_campaign,
                'created_at'      => current_time('mysql')
            ]);
            $created_count++;
        }

        if ($created_count > 1) {
            $msg = sprintf(
                class_exists('PPV_Lang') ? PPV_Lang::t('rewards_saved_multiple') : 'Jutalom létrehozva %d filiálénál.',
                $created_count
            );
        } else {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('rewards_saved') : 'Jutalmazás mentve.';
        }

        // 📡 Ably: Notify real-time about new reward
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            foreach ($target_stores as $target_id) {
                PPV_Ably::trigger_reward_update($target_id, [
                    'action' => 'created',
                    'title' => $title,
                    'required_points' => $points,
                ]);
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => '✅ ' . $msg,
            'created_count' => $created_count
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

        // 📅 Campaign fields
        $is_campaign = !empty($data['is_campaign']) ? 1 : 0;
        $start_date  = !empty($data['start_date']) ? sanitize_text_field($data['start_date']) : null;
        $end_date    = !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null;

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

        // 📡 Ably: Notify real-time about updated reward
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            PPV_Ably::trigger_reward_update($store_id, [
                'action' => 'updated',
                'reward_id' => $id,
                'title' => $title,
                'required_points' => $points,
            ]);
        }

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

        // 📡 Ably: Notify real-time about deleted reward
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            PPV_Ably::trigger_reward_update($store_id, [
                'action' => 'deleted',
                'reward_id' => $id,
            ]);
        }

        $msg = class_exists('PPV_Lang') ? PPV_Lang::t('rewards_deleted') : 'Jutalmazás törölve.';
        return new WP_REST_Response([
            'success' => true,
            'message' => '🗑️ ' . $msg
        ], 200);
    }
}

PPV_Rewards_Management::hooks();