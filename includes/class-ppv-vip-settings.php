<?php
/**
 * PunktePass – VIP Settings Management
 * Handlers can configure VIP bonuses for different user levels
 * Version: 1.0
 * Author: PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_VIP_Settings {

    public static function hooks() {
        add_shortcode('ppv_vip_settings', [__CLASS__, 'render_settings_page']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** ============================================================
     *  📡 REST ENDPOINTS
     * ============================================================ */
    public static function register_rest_routes() {
        // Get VIP settings
        register_rest_route('ppv/v1', '/vip/settings', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_settings'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Save VIP settings
        register_rest_route('ppv/v1', '/vip/save', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_save_settings'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);
    }

    /** ============================================================
     *  🎨 ASSETS
     * ============================================================ */
    public static function enqueue_assets() {
        if (!is_page() || !has_shortcode(get_the_content(), 'ppv_vip_settings')) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'ppv-handler-light',
            PPV_PLUGIN_URL . 'assets/css/handler-light.css',
            [],
            time()
        );

        // Remix Icons
        wp_enqueue_style(
            'remix-icons',
            'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css',
            [],
            '3.5.0'
        );

        // JS
        wp_enqueue_script(
            'ppv-vip-settings',
            PPV_PLUGIN_URL . 'assets/js/ppv-vip-settings.js',
            ['jquery'],
            time(),
            true
        );

        wp_add_inline_script(
            'ppv-vip-settings',
            'window.ppv_vip = ' . wp_json_encode([
                'base' => esc_url(rest_url('ppv/v1/')),
                'nonce' => wp_create_nonce('wp_rest'),
                'store_id' => self::get_store_id()
            ]) . ';',
            'before'
        );
    }

    /** ============================================================
     *  🔐 GET STORE ID
     * ============================================================ */
    private static function get_store_id() {
        global $wpdb;

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        // Session store
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        // Filiale
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return intval($_SESSION['ppv_current_filiale_id']);
        }

        // WP User → Store lookup
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));
            if ($store_id) return intval($store_id);
        }

        return 0;
    }

    /** ============================================================
     *  📡 REST: Get VIP Settings
     * ============================================================ */
    public static function rest_get_settings(WP_REST_Request $request) {
        global $wpdb;

        $store_id = self::get_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'msg' => 'Store not found'], 403);
        }

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT vip_enabled, vip_silver_bonus, vip_gold_bonus, vip_platinum_bonus
             FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            return new WP_REST_Response(['success' => false, 'msg' => 'Store not found'], 404);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'vip_enabled' => (bool) ($store->vip_enabled ?? 0),
                'vip_silver_bonus' => intval($store->vip_silver_bonus ?? 5),
                'vip_gold_bonus' => intval($store->vip_gold_bonus ?? 10),
                'vip_platinum_bonus' => intval($store->vip_platinum_bonus ?? 20),
            ]
        ], 200);
    }

    /** ============================================================
     *  📡 REST: Save VIP Settings
     * ============================================================ */
    public static function rest_save_settings(WP_REST_Request $request) {
        global $wpdb;

        $store_id = self::get_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'msg' => 'Store not found'], 403);
        }

        $vip_enabled = (bool) $request->get_param('vip_enabled');
        $silver = max(0, min(100, intval($request->get_param('vip_silver_bonus'))));
        $gold = max(0, min(100, intval($request->get_param('vip_gold_bonus'))));
        $platinum = max(0, min(100, intval($request->get_param('vip_platinum_bonus'))));

        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            [
                'vip_enabled' => $vip_enabled ? 1 : 0,
                'vip_silver_bonus' => $silver,
                'vip_gold_bonus' => $gold,
                'vip_platinum_bonus' => $platinum,
            ],
            ['id' => $store_id],
            ['%d', '%d', '%d', '%d'],
            ['%d']
        );

        if ($result === false) {
            error_log("❌ [PPV_VIP] Failed to save VIP settings for store {$store_id}");
            return new WP_REST_Response(['success' => false, 'msg' => 'Database error'], 500);
        }

        error_log("✅ [PPV_VIP] VIP settings saved: store={$store_id}, enabled={$vip_enabled}, silver={$silver}%, gold={$gold}%, platinum={$platinum}%");

        return new WP_REST_Response(['success' => true, 'msg' => 'VIP settings saved'], 200);
    }

    /** ============================================================
     *  🎨 RENDER SETTINGS PAGE
     * ============================================================ */
    public static function render_settings_page($atts = []) {
        $store_id = self::get_store_id();
        if (!$store_id) {
            return '<div class="ppv-error">Not authorized. Please log in.</div>';
        }

        // Get current language
        $lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de';

        // Translations
        $T = [
            'de' => [
                'title' => 'VIP Bonus-Punkte',
                'subtitle' => 'Gib deinen treuen Kunden extra Punkte basierend auf ihrem Level!',
                'enable_vip' => 'VIP-Bonuspunkte aktivieren',
                'enable_desc' => 'Wenn aktiviert, erhalten höherrangige Kunden automatisch mehr Punkte bei jedem Scan.',
                'bronze_label' => 'Bronze (keine Vorteile)',
                'silver_label' => 'Silber',
                'gold_label' => 'Gold',
                'platinum_label' => 'Platin',
                'bonus_suffix' => '% extra Punkte',
                'save_btn' => 'Einstellungen speichern',
                'saved' => 'Gespeichert!',
                'error' => 'Fehler beim Speichern',
                'preview_title' => 'Vorschau',
                'preview_base' => 'Basis: 1 Punkt',
                'preview_result' => 'ergibt',
            ],
            'hu' => [
                'title' => 'VIP Bónusz Pontok',
                'subtitle' => 'Adj extra pontokat a hűséges vásárlóidnak a szintjük alapján!',
                'enable_vip' => 'VIP bónusz pontok aktiválása',
                'enable_desc' => 'Ha aktiválva van, a magasabb szintű vásárlók automatikusan több pontot kapnak minden beolvasáskor.',
                'bronze_label' => 'Bronz (nincs előny)',
                'silver_label' => 'Ezüst',
                'gold_label' => 'Arany',
                'platinum_label' => 'Platina',
                'bonus_suffix' => '% extra pont',
                'save_btn' => 'Beállítások mentése',
                'saved' => 'Mentve!',
                'error' => 'Mentési hiba',
                'preview_title' => 'Előnézet',
                'preview_base' => 'Alap: 1 pont',
                'preview_result' => 'eredmény',
            ],
            'ro' => [
                'title' => 'Puncte Bonus VIP',
                'subtitle' => 'Oferă puncte extra clienților fideli în funcție de nivelul lor!',
                'enable_vip' => 'Activează puncte bonus VIP',
                'enable_desc' => 'Când este activat, clienții de nivel superior primesc automat mai multe puncte la fiecare scanare.',
                'bronze_label' => 'Bronz (fără avantaje)',
                'silver_label' => 'Argint',
                'gold_label' => 'Aur',
                'platinum_label' => 'Platină',
                'bonus_suffix' => '% puncte extra',
                'save_btn' => 'Salvează setările',
                'saved' => 'Salvat!',
                'error' => 'Eroare la salvare',
                'preview_title' => 'Previzualizare',
                'preview_base' => 'Bază: 1 punct',
                'preview_result' => 'rezultă',
            ],
        ][$lang] ?? [
            'title' => 'VIP Bonus-Punkte',
            'subtitle' => 'Gib deinen treuen Kunden extra Punkte basierend auf ihrem Level!',
            'enable_vip' => 'VIP-Bonuspunkte aktivieren',
            'enable_desc' => 'Wenn aktiviert, erhalten höherrangige Kunden automatisch mehr Punkte bei jedem Scan.',
            'bronze_label' => 'Bronze (keine Vorteile)',
            'silver_label' => 'Silber',
            'gold_label' => 'Gold',
            'platinum_label' => 'Platin',
            'bonus_suffix' => '% extra Punkte',
            'save_btn' => 'Einstellungen speichern',
            'saved' => 'Gespeichert!',
            'error' => 'Fehler beim Speichern',
            'preview_title' => 'Vorschau',
            'preview_base' => 'Basis: 1 Punkt',
            'preview_result' => 'ergibt',
        ];

        ob_start();
        ?>
        <div class="ppv-vip-settings-wrapper" id="ppv-vip-settings">
            <div class="ppv-vip-header">
                <h2><i class="ri-vip-crown-fill"></i> <?php echo esc_html($T['title']); ?></h2>
                <p class="ppv-vip-subtitle"><?php echo esc_html($T['subtitle']); ?></p>
            </div>

            <div class="ppv-vip-form">
                <!-- Enable Toggle -->
                <div class="ppv-vip-toggle-row">
                    <label class="ppv-toggle-switch">
                        <input type="checkbox" id="ppv-vip-enabled" name="vip_enabled">
                        <span class="ppv-toggle-slider"></span>
                    </label>
                    <div class="ppv-toggle-label">
                        <strong><?php echo esc_html($T['enable_vip']); ?></strong>
                        <small><?php echo esc_html($T['enable_desc']); ?></small>
                    </div>
                </div>

                <!-- Bonus Levels -->
                <div class="ppv-vip-levels" id="ppv-vip-levels">
                    <!-- Bronze (disabled) -->
                    <div class="ppv-vip-level ppv-vip-bronze disabled">
                        <div class="ppv-level-icon">
                            <i class="ri-medal-line"></i>
                        </div>
                        <div class="ppv-level-info">
                            <span class="ppv-level-name"><?php echo esc_html($T['bronze_label']); ?></span>
                            <span class="ppv-level-bonus">0% <?php echo esc_html($T['bonus_suffix']); ?></span>
                        </div>
                    </div>

                    <!-- Silver -->
                    <div class="ppv-vip-level ppv-vip-silver">
                        <div class="ppv-level-icon">
                            <i class="ri-medal-fill"></i>
                        </div>
                        <div class="ppv-level-info">
                            <span class="ppv-level-name"><?php echo esc_html($T['silver_label']); ?></span>
                            <div class="ppv-level-input">
                                <input type="number" id="ppv-silver-bonus" name="vip_silver_bonus" min="0" max="100" value="5">
                                <span><?php echo esc_html($T['bonus_suffix']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Gold -->
                    <div class="ppv-vip-level ppv-vip-gold">
                        <div class="ppv-level-icon">
                            <i class="ri-vip-crown-fill"></i>
                        </div>
                        <div class="ppv-level-info">
                            <span class="ppv-level-name"><?php echo esc_html($T['gold_label']); ?></span>
                            <div class="ppv-level-input">
                                <input type="number" id="ppv-gold-bonus" name="vip_gold_bonus" min="0" max="100" value="10">
                                <span><?php echo esc_html($T['bonus_suffix']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Platinum -->
                    <div class="ppv-vip-level ppv-vip-platinum">
                        <div class="ppv-level-icon">
                            <i class="ri-vip-diamond-fill"></i>
                        </div>
                        <div class="ppv-level-info">
                            <span class="ppv-level-name"><?php echo esc_html($T['platinum_label']); ?></span>
                            <div class="ppv-level-input">
                                <input type="number" id="ppv-platinum-bonus" name="vip_platinum_bonus" min="0" max="100" value="20">
                                <span><?php echo esc_html($T['bonus_suffix']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview -->
                <div class="ppv-vip-preview" id="ppv-vip-preview">
                    <h4><?php echo esc_html($T['preview_title']); ?></h4>
                    <p><?php echo esc_html($T['preview_base']); ?>:</p>
                    <div class="ppv-preview-grid">
                        <div class="ppv-preview-item bronze">
                            <span>Bronze</span>
                            <strong>1</strong>
                        </div>
                        <div class="ppv-preview-item silver">
                            <span>Silver</span>
                            <strong id="preview-silver">1.05</strong>
                        </div>
                        <div class="ppv-preview-item gold">
                            <span>Gold</span>
                            <strong id="preview-gold">1.1</strong>
                        </div>
                        <div class="ppv-preview-item platinum">
                            <span>Platinum</span>
                            <strong id="preview-platinum">1.2</strong>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="ppv-vip-actions">
                    <button type="button" id="ppv-vip-save" class="ppv-btn-primary">
                        <i class="ri-save-line"></i>
                        <?php echo esc_html($T['save_btn']); ?>
                    </button>
                    <span id="ppv-vip-status"></span>
                </div>
            </div>
        </div>

        <script>
        window.ppv_vip_translations = <?php echo wp_json_encode($T); ?>;
        </script>
        <?php
        return ob_get_clean();
    }
}

// Initialize
PPV_VIP_Settings::hooks();
