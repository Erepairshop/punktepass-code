<?php
/**
 * PunktePass – VIP Settings Management (Extended)
 * Handlers can configure multiple VIP bonus types:
 * 1. Fixed point bonus per level
 * 2. Every Xth scan bonus
 * 3. First daily scan bonus
 *
 * Version: 2.1
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

        // Save VIP settings - 🔒 CSRF protected
        register_rest_route('ppv/v1', '/vip/save', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_save_settings'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);
    }

    /** ============================================================
     *  🎨 ASSETS
     * ============================================================ */
    public static function enqueue_assets() {
        if (!is_page()) {
            return;
        }

        $content = get_the_content();
        // Load if page has VIP settings shortcode OR QR center (which includes VIP tab)
        if (!has_shortcode($content, 'ppv_vip_settings') && !has_shortcode($content, 'ppv_qr_center')) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'ppv-handler-light',
            PPV_PLUGIN_URL . 'assets/css/handler-light.css',
            [],
            time()
        );

        // RemixIcons loaded globally in punktepass.php

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
     *  🏢 GET ALL FILIALEN FOR HANDLER
     * ============================================================ */
    public static function get_handler_filialen() {
        global $wpdb;

        // Get the base store ID (not filiale-specific)
        $base_store_id = null;

        if (!empty($_SESSION['ppv_store_id'])) {
            $base_store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            $base_store_id = intval($_SESSION['ppv_vendor_store_id']);
        } elseif (!empty($GLOBALS['ppv_active_store_id'])) {
            $base_store_id = intval($GLOBALS['ppv_active_store_id']);
        }

        if (!$base_store_id) {
            // Try DB fallback
            $uid = get_current_user_id();
            if ($uid > 0) {
                $base_store_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                    $uid
                ));
            }
        }

        if (!$base_store_id) {
            return [];
        }

        // Get all stores: parent + children
        $filialen = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, company_name, address, city, plz
            FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d OR parent_store_id = %d
            ORDER BY (id = %d) DESC, name ASC
        ", $base_store_id, $base_store_id, $base_store_id));

        return $filialen ?: [];
    }

    /** ============================================================
     *  📡 REST: Get VIP Settings (Extended)
     * ============================================================ */
    public static function rest_get_settings(WP_REST_Request $request) {
        global $wpdb;

        // Check for filiale_id parameter
        $filiale_param = $request->get_param('filiale_id');
        if ($filiale_param && $filiale_param !== 'all') {
            // Use specific filiale
            $store_id = intval($filiale_param);
        } else {
            // Use default store
            $store_id = self::get_store_id();
        }

        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'msg' => 'Store not found'], 403);
        }

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT
                vip_fix_enabled, vip_fix_bronze, vip_fix_silver, vip_fix_gold, vip_fix_platinum,
                vip_streak_enabled, vip_streak_count, vip_streak_type,
                vip_streak_bronze, vip_streak_silver, vip_streak_gold, vip_streak_platinum
             FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            return new WP_REST_Response(['success' => false, 'msg' => 'Store not found'], 404);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                // 1. Fixed point bonus
                'vip_fix_enabled' => (bool) ($store->vip_fix_enabled ?? 0),
                'vip_fix_bronze' => intval($store->vip_fix_bronze ?? 1),
                'vip_fix_silver' => intval($store->vip_fix_silver ?? 2),
                'vip_fix_gold' => intval($store->vip_fix_gold ?? 3),
                'vip_fix_platinum' => intval($store->vip_fix_platinum ?? 5),

                // 2. Streak bonus (every Xth scan)
                'vip_streak_enabled' => (bool) ($store->vip_streak_enabled ?? 0),
                'vip_streak_count' => intval($store->vip_streak_count ?? 10),
                'vip_streak_type' => $store->vip_streak_type ?? 'fixed',
                'vip_streak_bronze' => intval($store->vip_streak_bronze ?? 1),
                'vip_streak_silver' => intval($store->vip_streak_silver ?? 2),
                'vip_streak_gold' => intval($store->vip_streak_gold ?? 3),
                'vip_streak_platinum' => intval($store->vip_streak_platinum ?? 5),
            ]
        ], 200);
    }

    /** ============================================================
     *  📡 REST: Save VIP Settings (Extended)
     * ============================================================ */
    public static function rest_save_settings(WP_REST_Request $request) {
        global $wpdb;

        // Check for filiale_id parameter
        $filiale_param = $request->get_param('filiale_id');
        if ($filiale_param && $filiale_param !== 'all') {
            // Save to specific filiale
            $store_id = intval($filiale_param);
        } else {
            // Use default store
            $store_id = self::get_store_id();
        }

        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'msg' => 'Store not found'], 403);
        }

        // Get language for error messages
        $lang = sanitize_text_field($request->get_param('lang') ?? 'de');

        // Helper to get integer values with bounds
        $getInt = function($key, $default = 0, $min = 0, $max = 1000) use ($request) {
            return max($min, min($max, intval($request->get_param($key) ?? $default)));
        };

        // 1. Fixed point bonus values
        $fix_enabled = (bool) $request->get_param('vip_fix_enabled');
        $fix_bronze = $getInt('vip_fix_bronze', 1, 0, 1000);
        $fix_silver = $getInt('vip_fix_silver', 2, 0, 1000);
        $fix_gold = $getInt('vip_fix_gold', 3, 0, 1000);
        $fix_platinum = $getInt('vip_fix_platinum', 5, 0, 1000);

        // 2. Streak bonus values
        $streak_enabled = (bool) $request->get_param('vip_streak_enabled');
        $streak_count = $getInt('vip_streak_count', 10, 2, 100);
        $streak_type = sanitize_text_field($request->get_param('vip_streak_type') ?? 'fixed');
        if (!in_array($streak_type, ['fixed', 'double', 'triple'])) {
            $streak_type = 'fixed';
        }
        $streak_bronze = $getInt('vip_streak_bronze', 1, 0, 1000);
        $streak_silver = $getInt('vip_streak_silver', 2, 0, 1000);
        $streak_gold = $getInt('vip_streak_gold', 3, 0, 1000);
        $streak_platinum = $getInt('vip_streak_platinum', 5, 0, 1000);

        // Validation: Bronze ≤ Silver ≤ Gold ≤ Platinum for all enabled bonus types
        $errors = [];
        $error_messages = [
            'de' => [
                'fix' => 'Fixpunkte-Bonus: Die Werte müssen aufsteigend sein (Bronze ≤ Silber ≤ Gold ≤ Platin)',
                'streak' => 'X. Scan Bonus: Die Werte müssen aufsteigend sein (Bronze ≤ Silber ≤ Gold ≤ Platin)',
            ],
            'hu' => [
                'fix' => 'Fix pont bónusz: Az értékeknek növekvő sorrendben kell lenniük (Bronz ≤ Ezüst ≤ Arany ≤ Platina)',
                'streak' => 'X. scan bónusz: Az értékeknek növekvő sorrendben kell lenniük (Bronz ≤ Ezüst ≤ Arany ≤ Platina)',
            ],
            'ro' => [
                'fix' => 'Bonus puncte fixe: Valorile trebuie să fie în ordine crescătoare (Bronz ≤ Argint ≤ Aur ≤ Platină)',
                'streak' => 'Bonus scanare X: Valorile trebuie să fie în ordine crescătoare (Bronz ≤ Argint ≤ Aur ≤ Platină)',
            ],
        ];
        $err = $error_messages[$lang] ?? $error_messages['de'];

        // Check ascending order for each enabled bonus type (Bronze ≤ Silver ≤ Gold ≤ Platinum)
        if ($fix_enabled && !($fix_bronze <= $fix_silver && $fix_silver <= $fix_gold && $fix_gold <= $fix_platinum)) {
            $errors[] = $err['fix'];
        }
        if ($streak_enabled && $streak_type === 'fixed' && !($streak_bronze <= $streak_silver && $streak_silver <= $streak_gold && $streak_gold <= $streak_platinum)) {
            $errors[] = $err['streak'];
        }

        if (!empty($errors)) {
            return new WP_REST_Response([
                'success' => false,
                'msg' => implode("\n", $errors),
                'errors' => $errors
            ], 400);
        }

        // Save to database
        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            [
                // 1. Fixed
                'vip_fix_enabled' => $fix_enabled ? 1 : 0,
                'vip_fix_bronze' => $fix_bronze,
                'vip_fix_silver' => $fix_silver,
                'vip_fix_gold' => $fix_gold,
                'vip_fix_platinum' => $fix_platinum,
                // 2. Streak
                'vip_streak_enabled' => $streak_enabled ? 1 : 0,
                'vip_streak_count' => $streak_count,
                'vip_streak_type' => $streak_type,
                'vip_streak_bronze' => $streak_bronze,
                'vip_streak_silver' => $streak_silver,
                'vip_streak_gold' => $streak_gold,
                'vip_streak_platinum' => $streak_platinum,
            ],
            ['id' => $store_id],
            ['%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d'],
            ['%d']
        );

        if ($result === false) {
            ppv_log("❌ [PPV_VIP] Failed to save VIP settings for store {$store_id}");
            return new WP_REST_Response(['success' => false, 'msg' => 'Database error'], 500);
        }

        // ✅ CACHE INVALIDATION: Increment VIP version to invalidate store list cache
        $current_version = wp_cache_get('ppv_vip_version') ?: 1;
        wp_cache_set('ppv_vip_version', $current_version + 1);
        ppv_log("🔄 [PPV_VIP] Cache invalidated: VIP version incremented to " . ($current_version + 1));

        ppv_log("✅ [PPV_VIP] Extended VIP settings saved: store={$store_id}");

        return new WP_REST_Response(['success' => true, 'msg' => 'VIP settings saved'], 200);
    }

    /** ============================================================
     *  🎨 RENDER SETTINGS PAGE (Extended)
     * ============================================================ */
    public static function render_settings_page($atts = []) {
        $store_id = self::get_store_id();
        if (!$store_id) {
            return '<div class="ppv-error">Not authorized. Please log in.</div>';
        }

        // Get filialen for dropdown
        $filialen = self::get_handler_filialen();
        $has_multiple_filialen = count($filialen) > 1;

        // Get current language
        $lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de';

        // Translations
        $T = [
            'de' => [
                'title' => 'VIP Bonus-Punkte',
                'subtitle' => 'Gib deinen treuen Kunden extra Punkte basierend auf ihrem Level!',
                'all_branches' => 'Alle Filialen',

                // How it works
                'how_it_works' => 'So funktioniert\'s',
                'how_step1' => 'Kunden sammeln Scans durch QR-Code Scans bei allen teilnehmenden PunktePass-Geschäften',
                'how_step2' => 'Das VIP-Level basiert auf der Anzahl der Scans (Bronze ab 25, Silber ab 50, Gold ab 75, Platin ab 100 Scans)',
                'how_step3' => 'Die hier eingestellten Boni gelten, wenn VIP-Kunden bei dir scannen – höheres Level = mehr Bonuspunkte!',
                'how_step4' => 'Du kannst verschiedene Bonus-Typen kombinieren: Fixpunkte pro Scan und Streak-Bonus (z.B. jeder 10. Scan)',
                'bronze_label' => 'Bronze',
                'silver_label' => 'Silber',
                'gold_label' => 'Gold',
                'platinum_label' => 'Platin',
                'save_btn' => 'Einstellungen speichern',
                'saved' => 'Gespeichert!',
                'error' => 'Fehler beim Speichern',

                // Bonus type cards
                'fix_title' => 'Fixpunkte-Bonus',
                'fix_desc' => 'Jeder Scan bringt X extra Punkte',
                'fix_suffix' => ' Punkte',

                'streak_title' => 'X. Scan Bonus',
                'streak_desc' => 'Jeder X. Scan bringt extra Belohnung',
                'streak_every' => 'Jeden',
                'streak_scan' => 'Scan',
                'streak_type_fixed' => 'Fixpunkte',
                'streak_type_double' => 'Doppelt',
                'streak_type_triple' => 'Dreifach',

                // Validation
                'validation_error' => 'Die Werte müssen aufsteigend sein: Bronze ≤ Silber ≤ Gold ≤ Platin',
            ],
            'hu' => [
                'title' => 'VIP Bónusz Pontok',
                'subtitle' => 'Adj extra pontokat a hűséges vásárlóidnak a szintjük alapján!',
                'all_branches' => 'Összes filiale',

                // How it works
                'how_it_works' => 'Így működik',
                'how_step1' => 'A vásárlók scaneléseket gyűjtenek QR-kód beolvasással az összes résztvevő PunktePass üzletben',
                'how_step2' => 'A VIP szint a scanelések száma alapján alakul (Bronz 25-től, Ezüst 50-től, Arany 75-től, Platina 100-tól)',
                'how_step3' => 'Az itt beállított bónuszok akkor érvényesek, amikor VIP vásárlók nálad scanelnek – magasabb szint = több bónuszpont!',
                'how_step4' => 'Kombinálhatod a különböző bónusz típusokat: fix pont scanelésenként és streak bónusz (pl. minden 10. scan)',
                'bronze_label' => 'Bronz',
                'silver_label' => 'Ezüst',
                'gold_label' => 'Arany',
                'platinum_label' => 'Platina',
                'save_btn' => 'Beállítások mentése',
                'saved' => 'Mentve!',
                'error' => 'Mentési hiba',

                // Bonus type cards
                'fix_title' => 'Fix Pont Bónusz',
                'fix_desc' => 'Minden scan X extra pontot hoz',
                'fix_suffix' => ' pont',

                'streak_title' => 'X. Scan Bónusz',
                'streak_desc' => 'Minden X. scan extra jutalmat hoz',
                'streak_every' => 'Minden',
                'streak_scan' => 'scan',
                'streak_type_fixed' => 'Fix pont',
                'streak_type_double' => 'Dupla',
                'streak_type_triple' => 'Tripla',

                // Validation
                'validation_error' => 'Az értékeknek növekvő sorrendben kell lenniük: Bronz ≤ Ezüst ≤ Arany ≤ Platina',
            ],
            'ro' => [
                'title' => 'Puncte Bonus VIP',
                'subtitle' => 'Oferă puncte extra clienților fideli în funcție de nivelul lor!',
                'all_branches' => 'Toate filialele',

                // How it works
                'how_it_works' => 'Cum funcționează',
                'how_step1' => 'Clienții colectează scanări prin scanarea codului QR la toate magazinele PunktePass participante',
                'how_step2' => 'Nivelul VIP se bazează pe numărul de scanări (Bronz de la 25, Argint de la 50, Aur de la 75, Platină de la 100)',
                'how_step3' => 'Bonusurile setate aici se aplică când clienții VIP scanează la tine – nivel mai mare = mai multe puncte bonus!',
                'how_step4' => 'Poți combina diferite tipuri de bonus: puncte fixe per scanare și bonus streak (ex. fiecare a 10-a scanare)',
                'bronze_label' => 'Bronz',
                'silver_label' => 'Argint',
                'gold_label' => 'Aur',
                'platinum_label' => 'Platină',
                'save_btn' => 'Salvează setările',
                'saved' => 'Salvat!',
                'error' => 'Eroare la salvare',

                // Bonus type cards
                'fix_title' => 'Bonus Puncte Fixe',
                'fix_desc' => 'Fiecare scanare aduce X puncte extra',
                'fix_suffix' => ' puncte',

                'streak_title' => 'Bonus Scanare X',
                'streak_desc' => 'Fiecare a X-a scanare aduce recompensă extra',
                'streak_every' => 'La fiecare',
                'streak_scan' => 'scanare',
                'streak_type_fixed' => 'Puncte fixe',
                'streak_type_double' => 'Dublu',
                'streak_type_triple' => 'Triplu',

                // Validation
                'validation_error' => 'Valorile trebuie să fie în ordine crescătoare: Bronz ≤ Argint ≤ Aur ≤ Platină',
            ],
        ][$lang] ?? [
            'title' => 'VIP Bonus-Punkte',
            'subtitle' => 'Gib deinen treuen Kunden extra Punkte basierend auf ihrem Level!',
            'all_branches' => 'Alle Filialen',
            'how_it_works' => 'So funktioniert\'s',
            'how_step1' => 'Kunden sammeln Scans durch QR-Code Scans bei allen teilnehmenden PunktePass-Geschäften',
            'how_step2' => 'Das VIP-Level basiert auf der Anzahl der Scans (Bronze ab 25, Silber ab 50, Gold ab 75, Platin ab 100 Scans)',
            'how_step3' => 'Die hier eingestellten Boni gelten, wenn VIP-Kunden bei dir scannen – höheres Level = mehr Bonuspunkte!',
            'how_step4' => 'Du kannst verschiedene Bonus-Typen kombinieren: Fixpunkte pro Scan und Streak-Bonus (z.B. jeder 10. Scan)',
            'bronze_label' => 'Bronze',
            'silver_label' => 'Silber',
            'gold_label' => 'Gold',
            'platinum_label' => 'Platin',
            'save_btn' => 'Einstellungen speichern',
            'saved' => 'Gespeichert!',
            'error' => 'Fehler beim Speichern',
            'fix_title' => 'Fixpunkte-Bonus',
            'fix_desc' => 'Jeder Scan bringt X extra Punkte',
            'fix_suffix' => ' Punkte',
            'streak_title' => 'X. Scan Bonus',
            'streak_desc' => 'Jeder X. Scan bringt extra Belohnung',
            'streak_every' => 'Jeden',
            'streak_scan' => 'Scan',
            'streak_type_fixed' => 'Fixpunkte',
            'streak_type_double' => 'Doppelt',
            'streak_type_triple' => 'Dreifach',
            'validation_error' => 'Die Werte müssen aufsteigend sein: Bronze ≤ Silber ≤ Gold ≤ Platin',
        ];

        ob_start();
        ?>
        <div class="ppv-vip-settings-wrapper ppv-vip-extended" id="ppv-vip-settings" data-lang="<?php echo esc_attr($lang); ?>">
            <div class="ppv-vip-header">
                <h2><i class="ri-vip-crown-fill"></i> <?php echo esc_html($T['title']); ?></h2>
                <p class="ppv-vip-subtitle"><?php echo esc_html($T['subtitle']); ?></p>
                <?php if ($has_multiple_filialen): ?>
                <div class="ppv-vip-filiale-selector" style="margin-top: 15px;">
                    <select id="ppv-vip-filiale" class="ppv-select">
                        <option value="all"><?php echo esc_html($T['all_branches']); ?></option>
                        <?php foreach ($filialen as $filiale): ?>
                            <option value="<?php echo esc_attr($filiale->id); ?>">
                                <?php echo esc_html($filiale->name ?: $filiale->company_name); ?>
                                <?php if ($filiale->city): ?> – <?php echo esc_html($filiale->city); ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- How it works collapsible -->
                <details class="ppv-vip-how-it-works" style="margin-top: 15px; background: rgba(255,193,7,0.08); border: 1px solid rgba(255,193,7,0.25); border-radius: 10px; overflow: hidden;">
                    <summary style="padding: 12px 15px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 500; color: #f59e0b; list-style: none;">
                        <span style="font-size: 16px;">💡</span>
                        <span><?php echo esc_html($T['how_it_works']); ?></span>
                        <svg style="margin-left: auto; width: 16px; height: 16px; transition: transform 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div style="padding: 0 15px 15px 15px; color: #ccc; font-size: 13px; line-height: 1.6;">
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div style="display: flex; align-items: flex-start; gap: 10px;">
                                <span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">1</span>
                                <span><?php echo esc_html($T['how_step1']); ?></span>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 10px;">
                                <span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">2</span>
                                <span><?php echo esc_html($T['how_step2']); ?></span>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 10px;">
                                <span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">3</span>
                                <span><?php echo esc_html($T['how_step3']); ?></span>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 10px;">
                                <span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">4</span>
                                <span><?php echo esc_html($T['how_step4']); ?></span>
                            </div>
                        </div>
                    </div>
                </details>
                <style>
                    .ppv-vip-how-it-works[open] summary svg { transform: rotate(180deg); }
                    .ppv-vip-how-it-works summary::-webkit-details-marker { display: none; }
                </style>
            </div>

            <div class="ppv-vip-form">

                <!-- ═══════════════════════════════════════════════════════════
                     1. FIXED POINT BONUS CARD
                ═══════════════════════════════════════════════════════════ -->
                <div class="ppv-vip-card" data-bonus-type="fix">
                    <div class="ppv-vip-card-header">
                        <label class="ppv-toggle-switch">
                            <input type="checkbox" id="ppv-fix-enabled" name="vip_fix_enabled">
                            <span class="ppv-toggle-slider"></span>
                        </label>
                        <div class="ppv-card-title">
                            <i class="ri-add-circle-line"></i>
                            <div>
                                <strong><?php echo esc_html($T['fix_title']); ?></strong>
                                <small><?php echo esc_html($T['fix_desc']); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="ppv-vip-card-body">
                        <div class="ppv-vip-levels-row">
                            <div class="ppv-vip-level-input bronze">
                                <label><?php echo esc_html($T['bronze_label']); ?></label>
                                <div class="ppv-input-group">
                                    <span>+</span>
                                    <input type="number" id="ppv-fix-bronze" name="vip_fix_bronze" min="0" max="1000" value="1">
                                    <span><?php echo esc_html($T['fix_suffix']); ?></span>
                                </div>
                            </div>
                            <div class="ppv-vip-level-input silver">
                                <label><?php echo esc_html($T['silver_label']); ?></label>
                                <div class="ppv-input-group">
                                    <span>+</span>
                                    <input type="number" id="ppv-fix-silver" name="vip_fix_silver" min="0" max="1000" value="2">
                                    <span><?php echo esc_html($T['fix_suffix']); ?></span>
                                </div>
                            </div>
                            <div class="ppv-vip-level-input gold">
                                <label><?php echo esc_html($T['gold_label']); ?></label>
                                <div class="ppv-input-group">
                                    <span>+</span>
                                    <input type="number" id="ppv-fix-gold" name="vip_fix_gold" min="0" max="1000" value="3">
                                    <span><?php echo esc_html($T['fix_suffix']); ?></span>
                                </div>
                            </div>
                            <div class="ppv-vip-level-input platinum">
                                <label><?php echo esc_html($T['platinum_label']); ?></label>
                                <div class="ppv-input-group">
                                    <span>+</span>
                                    <input type="number" id="ppv-fix-platinum" name="vip_fix_platinum" min="0" max="1000" value="5">
                                    <span><?php echo esc_html($T['fix_suffix']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="ppv-validation-error" id="ppv-fix-error"></div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════════
                     2. STREAK BONUS CARD (Every Xth scan)
                ═══════════════════════════════════════════════════════════ -->
                <div class="ppv-vip-card" data-bonus-type="streak">
                    <div class="ppv-vip-card-header">
                        <label class="ppv-toggle-switch">
                            <input type="checkbox" id="ppv-streak-enabled" name="vip_streak_enabled">
                            <span class="ppv-toggle-slider"></span>
                        </label>
                        <div class="ppv-card-title">
                            <i class="ri-fire-line"></i>
                            <div>
                                <strong><?php echo esc_html($T['streak_title']); ?></strong>
                                <small><?php echo esc_html($T['streak_desc']); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="ppv-vip-card-body">
                        <!-- Streak count and type -->
                        <div class="ppv-streak-config">
                            <div class="ppv-streak-count">
                                <label><?php echo esc_html($T['streak_every']); ?></label>
                                <input type="number" id="ppv-streak-count" name="vip_streak_count" min="2" max="100" value="10">
                                <label><?php echo esc_html($T['streak_scan']); ?></label>
                            </div>
                            <div class="ppv-streak-type">
                                <select id="ppv-streak-type" name="vip_streak_type">
                                    <option value="fixed"><?php echo esc_html($T['streak_type_fixed']); ?></option>
                                    <option value="double"><?php echo esc_html($T['streak_type_double']); ?></option>
                                    <option value="triple"><?php echo esc_html($T['streak_type_triple']); ?></option>
                                </select>
                            </div>
                        </div>
                        <!-- Fixed point inputs (shown only when type=fixed) -->
                        <div class="ppv-vip-levels-row ppv-streak-fixed-inputs">
                            <div class="ppv-vip-level-input bronze">
                                <label><?php echo esc_html($T['bronze_label']); ?></label>
                                <div class="ppv-input-group">
                                    <span>+</span>
                                    <input type="number" id="ppv-streak-bronze" name="vip_streak_bronze" min="0" max="1000" value="1">
                                    <span><?php echo esc_html($T['fix_suffix']); ?></span>
                                </div>
                            </div>
                            <div class="ppv-vip-level-input silver">
                                <label><?php echo esc_html($T['silver_label']); ?></label>
                                <div class="ppv-input-group">
                                    <span>+</span>
                                    <input type="number" id="ppv-streak-silver" name="vip_streak_silver" min="0" max="1000" value="2">
                                    <span><?php echo esc_html($T['fix_suffix']); ?></span>
                                </div>
                            </div>
                            <div class="ppv-vip-level-input gold">
                                <label><?php echo esc_html($T['gold_label']); ?></label>
                                <div class="ppv-input-group">
                                    <span>+</span>
                                    <input type="number" id="ppv-streak-gold" name="vip_streak_gold" min="0" max="1000" value="3">
                                    <span><?php echo esc_html($T['fix_suffix']); ?></span>
                                </div>
                            </div>
                            <div class="ppv-vip-level-input platinum">
                                <label><?php echo esc_html($T['platinum_label']); ?></label>
                                <div class="ppv-input-group">
                                    <span>+</span>
                                    <input type="number" id="ppv-streak-platinum" name="vip_streak_platinum" min="0" max="1000" value="5">
                                    <span><?php echo esc_html($T['fix_suffix']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="ppv-validation-error" id="ppv-streak-error"></div>
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
