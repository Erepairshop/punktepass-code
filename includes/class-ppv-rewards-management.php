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

        // 🔒 CSRF: POST endpoints use check_handler_with_nonce
        register_rest_route('ppv/v1', '/rewards/save', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_save_reward'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);

        register_rest_route('ppv/v1', '/rewards/delete', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_delete_reward'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);

        register_rest_route('ppv/v1', '/rewards/update', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_update_reward'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
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

        // 🔧 FIX: Always resolve to PARENT store first
        // If vendor_store_id is a child filiale, get its parent
        $parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $vendor_store_id
        ));

        if (!$parent_id) {
            $parent_id = $vendor_store_id;
        }

        // Get all stores: parent + all children
        $filialen = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, company_name, address, city, plz
            FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d OR parent_store_id = %d
            ORDER BY (id = %d) DESC, name ASC
        ", $parent_id, $parent_id, $parent_id));

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

            <!-- 🎯 HEADER SECTION -->
            <div class="ppv-rewards-header">
                <div class="ppv-rewards-header-icon">
                    <i class="ri-gift-2-fill"></i>
                </div>
                <div class="ppv-rewards-header-text">
                    <h2><?php echo esc_html(PPV_Lang::t('rewards_title') ?: 'Jutalmak'); ?></h2>
                    <span class="ppv-rewards-store-name"><?php echo esc_html($store->company_name ?? 'Store'); ?></span>
                </div>
                <button type="button" class="ppv-btn-add-reward" id="ppv-toggle-form">
                    <i class="ri-add-line"></i>
                    <span><?php echo esc_html(PPV_Lang::t('rewards_add_new') ?: 'Új jutalom'); ?></span>
                </button>
            </div>

            <!-- 📝 CREATE/EDIT FORM (collapsed by default) -->
            <div class="ppv-reward-form-wrapper" id="ppv-reward-form-wrapper" style="display: none;">
                <form id="ppv-reward-form" class="ppv-reward-form">
                    <input type="hidden" id="reward-id" name="id" value="">

                    <div class="ppv-form-grid">
                        <!-- Left Column -->
                        <div class="ppv-form-column">
                            <div class="ppv-form-group">
                                <label><i class="ri-text"></i> <?php echo esc_html(PPV_Lang::t('rewards_form_title') ?: 'Cím'); ?></label>
                                <input type="text" name="title" id="reward-title" placeholder="<?php echo esc_attr(PPV_Lang::t('rewards_form_title_placeholder') ?: 'pl. 10% rabatt'); ?>" required>
                            </div>

                            <div class="ppv-form-group">
                                <label><i class="ri-star-line"></i> <?php echo esc_html(PPV_Lang::t('rewards_form_points') ?: 'Szükséges pontok'); ?></label>
                                <input type="number" name="required_points" id="reward-points" placeholder="<?php echo esc_attr(PPV_Lang::t('rewards_form_points_placeholder') ?: 'pl. 50'); ?>" min="1" required>
                            </div>

                            <div class="ppv-form-group">
                                <label><i class="ri-file-text-line"></i> <?php echo esc_html(PPV_Lang::t('rewards_form_description') ?: 'Leírás'); ?></label>
                                <textarea name="description" id="reward-description" placeholder="<?php echo esc_attr(PPV_Lang::t('rewards_form_description_placeholder') ?: 'Részletek a jutalomról'); ?>" rows="3"></textarea>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="ppv-form-column">
                            <div class="ppv-form-group">
                                <label><i class="ri-price-tag-3-line"></i> <?php echo esc_html(PPV_Lang::t('rewards_form_type_label') ?: 'Típus'); ?></label>
                                <select name="action_type" id="reward-type">
                                    <option value="discount_percent"><?php echo esc_html(PPV_Lang::t('rewards_form_type_percent') ?: 'Kedvezmény (%)'); ?></option>
                                    <option value="discount_fixed"><?php echo esc_html(PPV_Lang::t('rewards_form_type_fixed') ?: 'Fix kedvezmény'); ?></option>
                                    <option value="free_product"><?php echo esc_html(PPV_Lang::t('rewards_form_type_free') ?: 'Ingyenes termék'); ?></option>
                                </select>
                            </div>

                            <div class="ppv-form-group">
                                <label><i class="ri-money-euro-circle-line"></i> <?php echo esc_html(sprintf(PPV_Lang::t('rewards_form_value') ?: 'Érték (%s)', $currency)); ?></label>
                                <input type="text" name="action_value" id="reward-value" placeholder="<?php echo esc_attr(PPV_Lang::t('rewards_form_value_placeholder') ?: 'pl. 10'); ?>" required>
                            </div>

                            <div class="ppv-form-group">
                                <label><i class="ri-coin-line"></i> <?php echo esc_html(PPV_Lang::t('rewards_form_points_given') ?: 'Bónusz pontok'); ?></label>
                                <input type="number" name="points_given" id="reward-points-given" placeholder="<?php echo esc_attr(PPV_Lang::t('rewards_form_points_given_placeholder') ?: 'pl. 5'); ?>" min="0" max="20" required>
                                <small><?php echo esc_html(PPV_Lang::t('rewards_form_points_given_helper') ?: 'Beváltáskor kapott pontok'); ?> (max. 20)</small>
                            </div>
                        </div>
                    </div>

                    <!-- 📅 CAMPAIGN SECTION -->
                    <div class="ppv-campaign-section">
                        <label class="ppv-campaign-toggle">
                            <input type="checkbox" name="is_campaign" id="reward-is-campaign" value="1">
                            <span class="ppv-toggle-slider"></span>
                            <span class="ppv-toggle-label">
                                <i class="ri-calendar-event-fill"></i>
                                <?php echo esc_html(PPV_Lang::t('rewards_form_campaign') ?: 'Időkorlátos kampány'); ?>
                            </span>
                        </label>

                        <div id="campaign-date-fields" class="ppv-campaign-dates">
                            <div class="ppv-date-field">
                                <label><i class="ri-calendar-check-line"></i> <?php echo esc_html(PPV_Lang::t('rewards_form_start_date') ?: 'Kezdés'); ?></label>
                                <input type="date" name="start_date" id="reward-start-date">
                            </div>
                            <div class="ppv-date-field">
                                <label><i class="ri-calendar-close-line"></i> <?php echo esc_html(PPV_Lang::t('rewards_form_end_date') ?: 'Befejezés'); ?></label>
                                <input type="date" name="end_date" id="reward-end-date">
                            </div>
                        </div>
                    </div>

                    <?php if ($has_multiple_filialen): ?>
                    <!-- 🏢 FILIALE SELECTOR -->
                    <div class="ppv-filiale-selector">
                        <div class="ppv-form-group">
                            <label><i class="ri-store-2-line"></i> <?php echo esc_html(PPV_Lang::t('rewards_form_filiale') ?: 'Melyik filiálé(k)nak?'); ?></label>
                            <select name="target_store_id" id="reward-target-store">
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
                        </div>

                        <label class="ppv-apply-all-toggle">
                            <input type="checkbox" name="apply_to_all" id="reward-apply-all" value="1">
                            <span class="ppv-toggle-slider"></span>
                            <span class="ppv-toggle-label">
                                <i class="ri-checkbox-multiple-line"></i>
                                <?php echo esc_html(PPV_Lang::t('rewards_form_apply_all') ?: 'Minden filiálénál'); ?>
                            </span>
                        </label>
                    </div>
                    <?php endif; ?>

                    <!-- Form Actions -->
                    <div class="ppv-form-actions">
                        <button type="submit" class="ppv-btn-save" id="save-btn">
                            <i class="ri-save-line"></i>
                            <span><?php echo esc_html(PPV_Lang::t('rewards_form_save') ?: 'Mentés'); ?></span>
                        </button>
                        <button type="button" class="ppv-btn-cancel" id="cancel-btn">
                            <i class="ri-close-line"></i>
                            <span><?php echo esc_html(PPV_Lang::t('rewards_form_cancel') ?: 'Mégse'); ?></span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- 🎁 REWARDS LIST -->
            <div class="ppv-rewards-list-header">
                <h3><i class="ri-list-check-2"></i> <?php echo esc_html(PPV_Lang::t('rewards_list_title') ?: 'Aktív jutalmak'); ?></h3>
                <span class="ppv-rewards-count" id="ppv-rewards-count">0</span>
            </div>

            <div id="ppv-rewards-list" class="ppv-rewards-grid">
                <div class="ppv-loading-state">
                    <div class="ppv-loading-spinner"></div>
                    <p><?php echo esc_html(PPV_Lang::t('rewards_list_loading') ?: 'Betöltés...'); ?></p>
                </div>
            </div>
        </div>

        <!-- 🎨 INLINE STYLES FOR REWARDS MANAGEMENT -->
        <style>
        /* ============================================
           🎁 REWARDS MANAGEMENT - MODERN DESIGN
           ============================================ */
        .ppv-rewards-management-wrapper {
            max-width: 100%;
            margin: 0 auto;
        }

        /* Header */
        .ppv-rewards-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            background: linear-gradient(135deg, rgba(0, 168, 204, 0.08) 0%, rgba(0, 168, 204, 0.02) 100%);
            border-radius: 16px;
            border: 1px solid rgba(0, 168, 204, 0.15);
            margin-bottom: 20px;
        }

        body.ppv-dark .ppv-rewards-header,
        [data-theme="dark"] .ppv-rewards-header {
            background: linear-gradient(135deg, rgba(0, 168, 204, 0.15) 0%, rgba(0, 168, 204, 0.05) 100%);
            border: 1px solid rgba(0, 168, 204, 0.2);
        }

        .ppv-rewards-header-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #00a8cc 0%, #0077a8 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(0, 168, 204, 0.3);
        }

        .ppv-rewards-header-icon i {
            font-size: 28px;
            color: white;
        }

        .ppv-rewards-header-text {
            flex: 1;
        }

        .ppv-rewards-header-text h2 {
            margin: 0 0 4px 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--pp-text, #1e293b);
        }

        .ppv-rewards-store-name {
            font-size: 0.9rem;
            color: var(--pp-text-2, #64748b);
        }

        /* Dark mode overrides */
        body.ppv-dark .ppv-rewards-header-text h2,
        [data-theme="dark"] .ppv-rewards-header-text h2 {
            color: #e2e8f0;
        }
        body.ppv-dark .ppv-rewards-store-name,
        [data-theme="dark"] .ppv-rewards-store-name {
            color: #94a3b8;
        }

        .ppv-btn-add-reward {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .ppv-btn-add-reward:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .ppv-btn-add-reward i {
            font-size: 1.2rem;
        }

        /* Form Wrapper */
        .ppv-reward-form-wrapper {
            background: var(--pp-surface, #ffffff);
            border-radius: 16px;
            border: 1px solid var(--pp-border, rgba(0, 168, 204, 0.15));
            padding: 24px;
            margin-bottom: 24px;
            animation: slideDown 0.3s ease-out;
            box-shadow: var(--pp-shadow, 0 4px 16px rgba(0,0,0,0.06));
        }

        body.ppv-dark .ppv-reward-form-wrapper,
        [data-theme="dark"] .ppv-reward-form-wrapper {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.9) 100%);
            border: 1px solid rgba(0, 168, 204, 0.15);
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .ppv-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 768px) {
            .ppv-form-grid {
                grid-template-columns: 1fr;
            }
        }

        .ppv-form-column {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .ppv-form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .ppv-form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--pp-text-2, #475569);
        }

        .ppv-form-group label i {
            color: #00a8cc;
            font-size: 1rem;
        }

        .ppv-form-group input,
        .ppv-form-group select,
        .ppv-form-group textarea {
            padding: 12px 16px;
            background: var(--pp-bg-2, #f1f5f9);
            border: 1px solid var(--pp-border, rgba(0, 168, 204, 0.15));
            border-radius: 10px;
            color: var(--pp-text, #1e293b);
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .ppv-form-group input:focus,
        .ppv-form-group select:focus,
        .ppv-form-group textarea:focus {
            outline: none;
            border-color: #00a8cc;
            box-shadow: 0 0 0 3px rgba(0, 168, 204, 0.15);
        }

        .ppv-form-group small {
            font-size: 0.8rem;
            color: var(--pp-text-3, #64748b);
        }

        /* Dark mode - form elements */
        body.ppv-dark .ppv-form-group label,
        [data-theme="dark"] .ppv-form-group label {
            color: #cbd5e1;
        }
        body.ppv-dark .ppv-form-group input,
        body.ppv-dark .ppv-form-group select,
        body.ppv-dark .ppv-form-group textarea,
        [data-theme="dark"] .ppv-form-group input,
        [data-theme="dark"] .ppv-form-group select,
        [data-theme="dark"] .ppv-form-group textarea {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(0, 168, 204, 0.2);
            color: #e2e8f0;
        }

        /* Campaign Section */
        .ppv-campaign-section {
            margin-top: 20px;
            padding: 16px;
            background: linear-gradient(135deg, rgba(249, 115, 22, 0.1) 0%, rgba(249, 115, 22, 0.05) 100%);
            border: 1px solid rgba(249, 115, 22, 0.2);
            border-radius: 12px;
        }

        .ppv-campaign-toggle,
        .ppv-apply-all-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            user-select: none;
        }

        .ppv-campaign-toggle input,
        .ppv-apply-all-toggle input {
            display: none;
        }

        .ppv-toggle-slider {
            width: 44px;
            height: 24px;
            background: rgba(100, 116, 139, 0.4);
            border-radius: 12px;
            position: relative;
            transition: all 0.3s ease;
        }

        .ppv-toggle-slider::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 18px;
            height: 18px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .ppv-campaign-toggle input:checked + .ppv-toggle-slider,
        .ppv-apply-all-toggle input:checked + .ppv-toggle-slider {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }

        .ppv-campaign-toggle input:checked + .ppv-toggle-slider::after,
        .ppv-apply-all-toggle input:checked + .ppv-toggle-slider::after {
            left: 23px;
        }

        .ppv-toggle-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: #f97316;
        }

        .ppv-campaign-dates {
            display: none;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(249, 115, 22, 0.2);
        }

        .ppv-campaign-dates.show {
            display: grid;
        }

        .ppv-date-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .ppv-date-field label {
            font-size: 0.85rem;
            color: var(--pp-text-2, #64748b);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ppv-date-field input {
            padding: 10px 14px;
            background: var(--pp-bg-2, #f1f5f9);
            border: 1px solid rgba(249, 115, 22, 0.3);
            border-radius: 8px;
            color: var(--pp-text, #1e293b);
        }

        body.ppv-dark .ppv-date-field label,
        [data-theme="dark"] .ppv-date-field label {
            color: #94a3b8;
        }
        body.ppv-dark .ppv-date-field input,
        [data-theme="dark"] .ppv-date-field input {
            background: rgba(0, 0, 0, 0.3);
            color: #e2e8f0;
        }

        /* Filiale Selector */
        .ppv-filiale-selector {
            margin-top: 20px;
            padding: 16px;
            background: linear-gradient(135deg, rgba(0, 230, 255, 0.1) 0%, rgba(0, 230, 255, 0.05) 100%);
            border: 1px solid rgba(0, 230, 255, 0.2);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .ppv-apply-all-toggle .ppv-toggle-label {
            color: #34d399;
        }

        .ppv-apply-all-toggle input:checked + .ppv-toggle-slider {
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
        }

        /* Form Actions */
        .ppv-form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 168, 204, 0.1);
        }

        .ppv-btn-save {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: linear-gradient(135deg, #00a8cc 0%, #0077a8 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 168, 204, 0.3);
        }

        .ppv-btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 168, 204, 0.4);
        }

        .ppv-btn-cancel {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 14px 24px;
            background: transparent;
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-radius: 12px;
            color: #ef4444;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .ppv-btn-cancel:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
        }

        /* Rewards List Header */
        .ppv-rewards-list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding: 0 4px;
        }

        .ppv-rewards-list-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--pp-text, #1e293b);
            margin: 0;
        }

        .ppv-rewards-list-header h3 i {
            color: #00a8cc;
        }

        body.ppv-dark .ppv-rewards-list-header h3,
        [data-theme="dark"] .ppv-rewards-list-header h3 {
            color: #e2e8f0;
        }

        .ppv-rewards-count {
            background: linear-gradient(135deg, #00a8cc 0%, #0077a8 100%);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
        }

        /* Rewards Grid */
        .ppv-rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }

        @media (max-width: 640px) {
            .ppv-rewards-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading State */
        .ppv-loading-state {
            grid-column: 1 / -1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            padding: 48px;
            color: #94a3b8;
        }

        .ppv-loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(0, 168, 204, 0.2);
            border-top-color: #00a8cc;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Empty State */
        .ppv-empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 48px 24px;
            background: var(--pp-bg-2, #f1f5f9);
            border-radius: 16px;
            border: 1px dashed var(--pp-border, rgba(0, 168, 204, 0.2));
        }

        .ppv-empty-state i {
            font-size: 48px;
            color: #00a8cc;
            opacity: 0.5;
            margin-bottom: 16px;
        }

        .ppv-empty-state p {
            color: var(--pp-text-2, #64748b);
            font-size: 1rem;
        }

        body.ppv-dark .ppv-empty-state,
        [data-theme="dark"] .ppv-empty-state {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.5) 0%, rgba(15, 23, 42, 0.6) 100%);
        }
        body.ppv-dark .ppv-empty-state p,
        [data-theme="dark"] .ppv-empty-state p {
            color: #94a3b8;
        }

        /* Reward Card */
        .ppv-reward-card {
            background: var(--pp-surface, #ffffff);
            border-radius: 16px;
            border: 1px solid var(--pp-border, rgba(0, 168, 204, 0.15));
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: var(--pp-shadow-sm, 0 2px 8px rgba(0,0,0,0.05));
        }

        .ppv-reward-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--pp-shadow-md, 0 8px 24px rgba(0,0,0,0.08));
            border-color: rgba(0, 168, 204, 0.3);
        }

        body.ppv-dark .ppv-reward-card,
        [data-theme="dark"] .ppv-reward-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
        }
        body.ppv-dark .ppv-reward-card:hover,
        [data-theme="dark"] .ppv-reward-card:hover {
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3);
        }

        .ppv-reward-card-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, rgba(0, 168, 204, 0.1) 0%, rgba(0, 168, 204, 0.02) 100%);
            border-bottom: 1px solid var(--pp-border, rgba(0, 168, 204, 0.1));
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        body.ppv-dark .ppv-reward-card-header,
        [data-theme="dark"] .ppv-reward-card-header {
            background: linear-gradient(135deg, rgba(0, 168, 204, 0.15) 0%, rgba(0, 168, 204, 0.05) 100%);
        }

        .ppv-reward-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--pp-text, #1e293b);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        body.ppv-dark .ppv-reward-card-title,
        [data-theme="dark"] .ppv-reward-card-title {
            color: #e2e8f0;
        }

        .ppv-reward-campaign-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
            white-space: nowrap;
        }

        .ppv-reward-card-body {
            padding: 20px;
        }

        .ppv-reward-description {
            color: var(--pp-text-2, #64748b);
            font-size: 0.9rem;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        body.ppv-dark .ppv-reward-description,
        [data-theme="dark"] .ppv-reward-description {
            color: #94a3b8;
        }

        .ppv-reward-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }

        .ppv-reward-stat {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: var(--pp-bg-2, #f1f5f9);
            border-radius: 8px;
            font-size: 0.85rem;
        }

        body.ppv-dark .ppv-reward-stat,
        [data-theme="dark"] .ppv-reward-stat {
            background: rgba(0, 0, 0, 0.2);
        }

        .ppv-reward-stat i {
            font-size: 1rem;
        }

        .ppv-reward-stat.points {
            color: #0096a8;
            border: 1px solid rgba(0, 150, 168, 0.3);
        }
        body.ppv-dark .ppv-reward-stat.points,
        [data-theme="dark"] .ppv-reward-stat.points {
            color: #00e6ff;
            border: 1px solid rgba(0, 230, 255, 0.2);
        }

        .ppv-reward-stat.bonus {
            color: #059669;
            border: 1px solid rgba(5, 150, 105, 0.3);
        }
        body.ppv-dark .ppv-reward-stat.bonus,
        [data-theme="dark"] .ppv-reward-stat.bonus {
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.2);
        }

        .ppv-reward-stat.value {
            color: #d97706;
            border: 1px solid rgba(217, 119, 6, 0.3);
        }
        body.ppv-dark .ppv-reward-stat.value,
        [data-theme="dark"] .ppv-reward-stat.value {
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.2);
        }

        .ppv-reward-dates {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: rgba(249, 115, 22, 0.1);
            border-radius: 8px;
            font-size: 0.8rem;
            color: #f97316;
            margin-bottom: 16px;
        }

        .ppv-reward-card-actions {
            display: flex;
            gap: 10px;
            padding-top: 16px;
            border-top: 1px solid var(--pp-border, rgba(0, 168, 204, 0.1));
        }

        .ppv-reward-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }

        .ppv-reward-btn-edit {
            background: rgba(0, 168, 204, 0.1);
            color: #0088a8;
            border: 1px solid rgba(0, 168, 204, 0.3);
        }

        .ppv-reward-btn-edit:hover {
            background: rgba(0, 168, 204, 0.2);
        }

        body.ppv-dark .ppv-reward-btn-edit,
        [data-theme="dark"] .ppv-reward-btn-edit {
            background: rgba(0, 168, 204, 0.15);
            color: #00d4ff;
        }

        .ppv-reward-btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .ppv-reward-btn-delete:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        /* ============================================
           📱 MOBILE RESPONSIVE - APP FEEL
           ============================================ */
        @media (max-width: 480px) {
            .ppv-rewards-management-wrapper {
                padding: 0;
            }

            .ppv-rewards-header {
                flex-direction: column;
                align-items: stretch;
                padding: 16px;
                gap: 12px;
            }

            .ppv-rewards-header-icon {
                width: 48px;
                height: 48px;
            }

            .ppv-rewards-header-icon i {
                font-size: 24px;
            }

            .ppv-rewards-header-text h2 {
                font-size: 1.25rem;
            }

            .ppv-btn-add-reward {
                width: 100%;
                justify-content: center;
                padding: 12px 16px;
            }

            .ppv-reward-form-wrapper {
                padding: 16px;
                margin: 0 0 16px 0;
                border-radius: 12px;
            }

            .ppv-form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .ppv-form-column {
                gap: 12px;
            }

            .ppv-form-group input,
            .ppv-form-group select,
            .ppv-form-group textarea {
                padding: 10px 14px;
                font-size: 16px; /* Prevents iOS zoom */
            }

            .ppv-campaign-section {
                margin-top: 16px;
                padding: 14px;
            }

            .ppv-campaign-dates {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .ppv-filiale-selector {
                margin-top: 16px;
                padding: 14px;
            }

            .ppv-form-actions {
                flex-direction: column;
                gap: 10px;
                margin-top: 16px;
                padding-top: 16px;
            }

            .ppv-btn-save,
            .ppv-btn-cancel {
                width: 100%;
                justify-content: center;
                padding: 14px;
            }

            .ppv-rewards-list-header {
                padding: 0;
            }

            .ppv-rewards-list-header h3 {
                font-size: 1rem;
            }

            .ppv-rewards-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .ppv-reward-card {
                border-radius: 12px;
            }

            .ppv-reward-card-header {
                padding: 14px 16px;
            }

            .ppv-reward-card-title {
                font-size: 1rem;
            }

            .ppv-reward-card-body {
                padding: 16px;
            }

            .ppv-reward-stats {
                gap: 8px;
            }

            .ppv-reward-stat {
                padding: 6px 10px;
                font-size: 0.8rem;
            }

            .ppv-reward-card-actions {
                gap: 8px;
                padding-top: 12px;
            }

            .ppv-reward-btn {
                padding: 10px 12px;
                font-size: 0.85rem;
            }

            .ppv-empty-state {
                padding: 32px 16px;
            }

            .ppv-empty-state i {
                font-size: 40px;
            }

            .ppv-empty-state p {
                font-size: 0.9rem;
            }

            .ppv-loading-state {
                padding: 32px;
            }
        }
        </style>

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

        // 🔒 SECURITY: Max points per scan limit
        $max_points_per_scan = 20;
        if ($points_given > $max_points_per_scan) {
            return new WP_REST_Response([
                'success' => false,
                'message' => "❌ Maximum {$max_points_per_scan} Punkte pro Scan erlaubt."
            ], 400);
        }

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

        // 🔒 SECURITY: Max points per scan limit
        $max_points_per_scan = 20;
        if ($points_given > $max_points_per_scan) {
            return new WP_REST_Response([
                'success' => false,
                'message' => "❌ Maximum {$max_points_per_scan} Punkte pro Scan erlaubt."
            ], 400);
        }

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
                'currency'        => $currency,
                'start_date'      => $start_date,
                'end_date'        => $end_date,
                'is_campaign'     => $is_campaign
            ],
            [
                'id'       => $id,
                'store_id' => $store_id
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d'],
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