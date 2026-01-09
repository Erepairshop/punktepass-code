<?php
/**
 * PunktePass – Admin Profil Handler (v2.0 i18n - PRODUCTION FIXED)
 * ✅ DE, HU, RO Language Support
 * ✅ Custom PPV Login Support
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('PPV_Profile_Lite_i18n')) {

    class PPV_Profile_Lite_i18n {

        const UPLOAD_MAX_SIZE = 4 * 1024 * 1024;
        const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
        const NONCE_ACTION = 'ppv_save_profile';
        const NONCE_NAME = 'ppv_nonce';

        public static function hooks() {
            add_action('wp_head', [__CLASS__, 'add_turbo_no_cache_meta'], 1);
            add_action('wp_ajax_ppv_get_strings', [__CLASS__, 'ajax_get_strings']);
            add_action('wp_ajax_ppv_geocode_address', [__CLASS__, 'ajax_geocode_address']);
            add_action('wp_ajax_nopriv_ppv_geocode_address', [__CLASS__, 'ajax_geocode_address']);
            add_action('wp_ajax_nopriv_ppv_get_strings', [__CLASS__, 'ajax_get_strings']);
            add_action('ppv_render_profile_form', [__CLASS__, 'render_form']);
            add_action('init', [__CLASS__, 'handle_form_submit']);
            add_shortcode('pp_store_profile', [__CLASS__, 'render_form']);
            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
            add_action('wp_ajax_ppv_save_profile', [__CLASS__, 'ajax_save_profile']);
            add_action('wp_ajax_nopriv_ppv_save_profile', [__CLASS__, 'ajax_save_profile']); // ✅ PPV session auth
            add_action('wp_ajax_ppv_delete_media', [__CLASS__, 'ajax_delete_media']);
            add_action('wp_ajax_nopriv_ppv_delete_media', [__CLASS__, 'ajax_delete_media']); // ✅ PPV session auth
            add_action('wp_ajax_ppv_auto_save_profile', [__CLASS__, 'ajax_auto_save_profile']);
            add_action('wp_ajax_nopriv_ppv_auto_save_profile', [__CLASS__, 'ajax_auto_save_profile']); // ✅ PPV session auth
            add_action('wp_ajax_ppv_delete_gallery_image', [__CLASS__, 'ajax_delete_gallery_image']);
            add_action('wp_ajax_nopriv_ppv_delete_gallery_image', [__CLASS__, 'ajax_delete_gallery_image']); // ✅ PPV session auth
            add_action('wp_ajax_ppv_reset_trusted_device', [__CLASS__, 'ajax_reset_trusted_device']);
            add_action('wp_ajax_nopriv_ppv_reset_trusted_device', [__CLASS__, 'ajax_reset_trusted_device']); // ✅ PPV session auth

            // Referral Program
            add_action('wp_ajax_ppv_activate_referral_grace_period', [__CLASS__, 'ajax_activate_referral_grace_period']);
            add_action('wp_ajax_nopriv_ppv_activate_referral_grace_period', [__CLASS__, 'ajax_activate_referral_grace_period']);

            // Account Settings - Email & Password Change
            add_action('wp_ajax_ppv_change_email', [__CLASS__, 'ajax_change_email']);
            add_action('wp_ajax_nopriv_ppv_change_email', [__CLASS__, 'ajax_change_email']);
            add_action('wp_ajax_ppv_change_password', [__CLASS__, 'ajax_change_password']);
            add_action('wp_ajax_nopriv_ppv_change_password', [__CLASS__, 'ajax_change_password']);
        }

        // ==================== TURBO CACHE FIX ====================
        public static function add_turbo_no_cache_meta() {
            global $post;
            $is_profile_page = false;

            // 1. Shortcode check
            if ($post && has_shortcode($post->post_content, 'pp_store_profile')) {
                $is_profile_page = true;
            }

            // 2. URL pattern check
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, '/profil') !== false ||
                strpos($uri, '/profile') !== false ||
                strpos($uri, '/einstellungen') !== false ||
                strpos($uri, '/admin') !== false) {
                $is_profile_page = true;
            }

            // 3. Page slug check
            if ($post && (
                strpos($post->post_name, 'profil') !== false ||
                strpos($post->post_name, 'profile') !== false ||
                strpos($post->post_name, 'einstellungen') !== false
            )) {
                $is_profile_page = true;
            }

            if ($is_profile_page) {
                echo '<meta name="turbo-cache-control" content="no-cache">' . "\n";
                echo '<!-- PPV Profile - No Cache, Turbo SPA enabled -->' . "\n";
            }
        }

        // ==================== AUTH CHECK ====================
        private static function check_auth() {
            if (is_user_logged_in()) {
                return ['valid' => true, 'type' => 'wp_user', 'user_id' => get_current_user_id()];
            }

            // 🏪 FILIALE SUPPORT: Use session-aware store ID
            if (!empty($_SESSION['ppv_store_id']) || !empty($_SESSION['ppv_current_filiale_id'])) {
                $store_id = self::get_store_id();
                return ['valid' => true, 'type' => 'ppv_stores', 'store_id' => $store_id, 'is_pos' => !empty($_SESSION['ppv_is_pos'])];
            }

            if (!empty($_SESSION['ppv_user_id'])) {
                return ['valid' => true, 'type' => 'ppv_user', 'user_id' => intval($_SESSION['ppv_user_id'])];
            }

            if (!empty($_COOKIE['ppv_pos_token'])) {
                global $wpdb;
                $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_stores WHERE pos_token = %s LIMIT 1", sanitize_text_field($_COOKIE['ppv_pos_token'])));
                if ($store) {
                    return ['valid' => true, 'type' => 'ppv_stores', 'store_id' => intval($store->id), 'store' => $store];
                }
            }

            if (!empty($_COOKIE['ppv_user_token'])) {
                global $wpdb;
                $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_users WHERE login_token = %s LIMIT 1", sanitize_text_field($_COOKIE['ppv_user_token'])));
                if ($user) {
                    return ['valid' => true, 'type' => 'ppv_user', 'user_id' => intval($user->id), 'user' => $user];
                }
            }

            return ['valid' => false];
        }

        private static function ensure_session() {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                @session_start();
            }
        }

        /** ============================================================
         *  🔐 GET STORE ID (with FILIALE support)
         * ============================================================ */
        private static function get_store_id() {
            global $wpdb;

            self::ensure_session();

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

            // Fallback: WordPress user (rare case)
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

        public static function ajax_get_strings() {
            $lang = sanitize_text_field($_GET['lang'] ?? PPV_Lang::current());
            $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang}.php";
            if (!file_exists($file)) {
                $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-de.php";
            }
            $strings = include $file;
            wp_send_json_success($strings);
        }

        public static function enqueue_assets() {
            if (!class_exists('PPV_Lang')) {
                return;
            }

            // 🔹 MODULAR CSS - Load core + profile components
            wp_enqueue_style(
                'ppv-theme-core',
                PPV_PLUGIN_URL . 'assets/css/ppv-theme-core.css',
                [],
                PPV_VERSION
            );
            wp_enqueue_style(
                'ppv-theme-profile',
                PPV_PLUGIN_URL . 'assets/css/ppv-theme-profile.css',
                ['ppv-theme-core'],
                PPV_VERSION
            );
            // Google Maps JS API
if (defined('PPV_GOOGLE_MAPS_KEY') && PPV_GOOGLE_MAPS_KEY) {
    wp_enqueue_script(
        'google-maps-api',
        'https://maps.googleapis.com/maps/api/js?key=' . PPV_GOOGLE_MAPS_KEY,
        [],
        null,
        true
    );
}

            // Profile Lite Modular JS (v3.0)
            $js_base = PPV_PLUGIN_URL . 'assets/js/';
            $js_dir = PPV_PLUGIN_DIR . 'assets/js/';

            // 1. Core module (state, helpers, Turbo cache fix)
            wp_enqueue_script('pp-profile-core', $js_base . 'pp-profile-core.js', [], filemtime($js_dir . 'pp-profile-core.js'), true);

            // 2. Tabs module
            wp_enqueue_script('pp-profile-tabs', $js_base . 'pp-profile-tabs.js', ['pp-profile-core'], filemtime($js_dir . 'pp-profile-tabs.js'), true);

            // 3. Form module
            wp_enqueue_script('pp-profile-form', $js_base . 'pp-profile-form.js', ['pp-profile-core', 'pp-profile-tabs'], filemtime($js_dir . 'pp-profile-form.js'), true);

            // 4. Media module
            wp_enqueue_script('pp-profile-media', $js_base . 'pp-profile-media.js', ['pp-profile-core'], filemtime($js_dir . 'pp-profile-media.js'), true);

            // 5. Geocoding module
            wp_enqueue_script('pp-profile-geocoding', $js_base . 'pp-profile-geocoding.js', ['pp-profile-core'], filemtime($js_dir . 'pp-profile-geocoding.js'), true);

            // 6. Init module (depends on all others)
            wp_enqueue_script('pp-profile-init', $js_base . 'pp-profile-init.js', ['pp-profile-core', 'pp-profile-tabs', 'pp-profile-form', 'pp-profile-media', 'pp-profile-geocoding'], filemtime($js_dir . 'pp-profile-init.js'), true);

            // Localize script data
            wp_localize_script('pp-profile-core', 'ppv_profile', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(self::NONCE_ACTION),
                'strings' => PPV_Lang::$strings,
                'lang' => PPV_Lang::current(),
                'googleMapsKey' => defined('PPV_GOOGLE_MAPS_KEY') ? PPV_GOOGLE_MAPS_KEY : '',
            ]);
        }

        public static function render_form() {
            self::ensure_session();

            // ✅ FIX: Send no-cache headers to bypass server-level caching (LiteSpeed, Cloudflare, etc.)
            if (!headers_sent()) {
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Cache-Control: post-check=0, pre-check=0', false);
                header('Pragma: no-cache');
                header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            }

            // ✅ SCANNER USERS: Don't show profile/onboarding page
            if (class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user()) {
                echo '<div class="ppv-alert ppv-alert-info" style="padding: 20px; text-align: center;">
                    ℹ️ Diese Seite ist nur für Händler verfügbar.
                </div>';
                return;
            }

            $store = self::get_current_store();

            if (!$store) {
                echo '<div class="ppv-alert ppv-alert-error">' . esc_html(PPV_Lang::t('error')) . '</div>';
                return;
            }

            ob_start();
            ?>
            <div class="ppv-profile-container">
                <div class="ppv-profile-header">
                    <div class="ppv-header-left">
                        <h1><?php echo esc_html(PPV_Lang::t('profile_header_title')); ?></h1>
                        <p><?php echo esc_html(PPV_Lang::t('profile_header_subtitle')); ?></p>
                    </div>
                    <div class="ppv-header-right">
                        <div class="ppv-store-status">
                            <span class="ppv-status-badge" id="ppv-status">🟢 <?php echo esc_html(PPV_Lang::t('status_active')); ?></span>
                            <span class="ppv-last-updated" id="ppv-last-updated">—</span>
                        </div>
                    </div>
                </div>

                <div class="ppv-tabs-nav">
                    <button class="ppv-tab-btn active" data-tab="general" data-i18n="tab_general">📋 <?php echo esc_html(PPV_Lang::t('tab_general')); ?></button>
                    <button class="ppv-tab-btn" data-tab="hours" data-i18n="tab_hours">🕒 <?php echo esc_html(PPV_Lang::t('tab_hours')); ?></button>
                    <button class="ppv-tab-btn" data-tab="media" data-i18n="tab_media">🖼️ <?php echo esc_html(PPV_Lang::t('tab_media')); ?></button>
                    <button class="ppv-tab-btn" data-tab="contact" data-i18n="tab_contact">📞 <?php echo esc_html(PPV_Lang::t('tab_contact')); ?></button>
                    <button class="ppv-tab-btn" data-tab="marketing" data-i18n="tab_marketing">⚡ <?php echo esc_html(PPV_Lang::t('tab_marketing')); ?></button>
                    <button class="ppv-tab-btn" data-tab="settings" data-i18n="tab_settings">⚙️ <?php echo esc_html(PPV_Lang::t('tab_settings')); ?></button>
                </div>

                <div id="ppv-alert-zone"></div>

                <form id="ppv-profile-form" class="ppv-profile-form">
                    <input type="hidden" name="store_id" value="<?php echo esc_attr($store->id); ?>">
                    <input type="hidden" name="<?php echo esc_attr(self::NONCE_NAME); ?>" value="<?php echo esc_attr(wp_create_nonce(self::NONCE_ACTION)); ?>">

                    <?php echo self::render_tab_general($store); ?>
                    <?php echo self::render_tab_hours($store); ?>
                    <?php echo self::render_tab_media($store); ?>
                    <?php echo self::render_tab_contact($store); ?>
                    <?php echo self::render_tab_marketing($store); ?>
                    <?php echo self::render_tab_settings($store); ?>

                    <div class="ppv-form-footer">
                        <button type="submit" class="ppv-btn ppv-btn-primary" id="ppv-submit-btn">💾 <span data-i18n="save"><?php echo esc_html(PPV_Lang::t('save')); ?></span></button>
                        <span class="ppv-save-indicator" id="ppv-save-indicator"></span>
                    </div>
                </form>
            </div>
            <?php
            echo ob_get_clean();
            echo do_shortcode('[ppv_bottom_nav]');
        }

       
/**
 * ✅ TELJES render_tab_general() FÜGGVÉNY
 * - Összes meglévő mező
 * - 3 ÚJ mező: Ország, Adószám, ÁFA
 * - Fordítások (i18n) mindenütt
 */

private static function render_tab_general($store) {
    ob_start();
    ?>
    <div class="ppv-tab-content active" id="tab-general">
        <h2 data-i18n="general_info"><?php echo esc_html(PPV_Lang::t('general_info')); ?></h2>

        <!-- STORE NAME -->
        <div class="ppv-form-group">
            <label data-i18n="store_name"><?php echo esc_html(PPV_Lang::t('store_name')); ?> *</label>
            <input type="text" name="store_name" value="<?php echo esc_attr($store->name ?? ''); ?>" required>
            <small class="ppv-field-hint" data-i18n="store_name_hint"><?php echo esc_html(PPV_Lang::t('store_name_hint')); ?></small>
        </div>

        <!-- SLOGAN -->
        <div class="ppv-form-group">
            <label data-i18n="slogan"><?php echo esc_html(PPV_Lang::t('slogan')); ?></label>
            <input type="text" name="slogan" value="<?php echo esc_attr($store->slogan ?? ''); ?>" maxlength="50">
        </div>

        <!-- CATEGORY -->
        <div class="ppv-form-group">
            <label data-i18n="category"><?php echo esc_html(PPV_Lang::t('category')); ?></label>
            <select name="category">
                <option value="" data-i18n="category_select"><?php echo esc_html(PPV_Lang::t('category_select')); ?></option>
                <option value="cafe" <?php selected($store->category ?? '', 'cafe'); ?> data-i18n="category_cafe"><?php echo esc_html(PPV_Lang::t('category_cafe')); ?></option>
                <option value="restaurant" <?php selected($store->category ?? '', 'restaurant'); ?> data-i18n="category_restaurant"><?php echo esc_html(PPV_Lang::t('category_restaurant')); ?></option>
                <option value="friseur" <?php selected($store->category ?? '', 'friseur'); ?> data-i18n="category_friseur"><?php echo esc_html(PPV_Lang::t('category_friseur')); ?></option>
                <option value="beauty" <?php selected($store->category ?? '', 'beauty'); ?> data-i18n="category_beauty"><?php echo esc_html(PPV_Lang::t('category_beauty')); ?></option>
                <option value="mode" <?php selected($store->category ?? '', 'mode'); ?> data-i18n="category_mode"><?php echo esc_html(PPV_Lang::t('category_mode')); ?></option>
                <option value="fitness" <?php selected($store->category ?? '', 'fitness'); ?> data-i18n="category_fitness"><?php echo esc_html(PPV_Lang::t('category_fitness')); ?></option>
                <option value="pharmacy" <?php selected($store->category ?? '', 'pharmacy'); ?> data-i18n="category_pharmacy"><?php echo esc_html(PPV_Lang::t('category_pharmacy')); ?></option>
                <option value="sportshop" <?php selected($store->category ?? '', 'sportshop'); ?> data-i18n="category_sportshop"><?php echo esc_html(PPV_Lang::t('category_sportshop')); ?></option>
                <option value="other" <?php selected($store->category ?? '', 'other'); ?> data-i18n="category_other"><?php echo esc_html(PPV_Lang::t('category_other')); ?></option>
            </select>
        </div>

        <hr>

        <!-- ============================================================
             ✅ ÚJ SZEKCIÓ: ORSZÁG ÉS ADÓ INFORMÁCIÓK
             ============================================================ -->
        <h3 data-i18n="country_tax_section"><?php echo esc_html(PPV_Lang::t('country_tax_section')); ?></h3>

        <!-- ORSZÁG KIVÁLASZTÁS -->
        <div class="ppv-form-group">
            <label data-i18n="country" style="font-weight: 600;"><?php echo esc_html(PPV_Lang::t('country')); ?> *</label>
            <select name="country" required>
                <option value="">-- <?php echo esc_html(PPV_Lang::t('country_select')); ?> --</option>
                <option value="DE" <?php selected($store->country ?? '', 'DE'); ?> data-i18n="country_de">🇩🇪 <?php echo esc_html(PPV_Lang::t('country_de')); ?></option>
                <option value="HU" <?php selected($store->country ?? '', 'HU'); ?> data-i18n="country_hu">🇭🇺 <?php echo esc_html(PPV_Lang::t('country_hu')); ?></option>
                <option value="RO" <?php selected($store->country ?? '', 'RO'); ?> data-i18n="country_ro">🇷🇴 <?php echo esc_html(PPV_Lang::t('country_ro')); ?></option>
            </select>
        </div>

        <!-- ADÓSZÁM (TAX ID) -->
        <div class="ppv-form-group">
            <label data-i18n="tax_id" style="font-weight: 600;"><?php echo esc_html(PPV_Lang::t('tax_id')); ?></label>
            <input type="text" name="tax_id" value="<?php echo esc_attr($store->tax_id ?? ''); ?>" placeholder="<?php echo esc_attr(PPV_Lang::t('tax_id_placeholder')); ?>">
            <small style="display: block; margin-top: 4px; color: #666;" data-i18n="tax_id_help">
                <?php echo esc_html(PPV_Lang::t('tax_id_help')); ?>
            </small>
        </div>

        <!-- ÁFA STATUS CHECKBOX -->
        <div class="ppv-checkbox-group">
            <label class="ppv-checkbox">
                <input type="checkbox" name="is_taxable" value="1" <?php checked($store->is_taxable ?? 1, 1); ?>>
                <strong data-i18n="is_taxable"><?php echo esc_html(PPV_Lang::t('is_taxable')); ?></strong>
                <small data-i18n="is_taxable_help"><?php echo esc_html(PPV_Lang::t('is_taxable_help')); ?></small>
            </label>
        </div>

        <hr>

        <!-- ============================================================
             MEGLÉVŐ: CÍM ADATOK
             ============================================================ -->
        <h3 data-i18n="address_section"><?php echo esc_html(PPV_Lang::t('address_section')); ?></h3>

        <!-- STREET ADDRESS -->
        <div class="ppv-form-group">
            <label data-i18n="street_address"><?php echo esc_html(PPV_Lang::t('street_address')); ?></label>
            <input type="text" name="address" value="<?php echo esc_attr($store->address ?? ''); ?>">
        </div>

        <!-- POSTAL CODE & CITY (2 columns) -->
        <div class="ppv-form-row">
            <div class="ppv-form-group">
                <label data-i18n="postal_code"><?php echo esc_html(PPV_Lang::t('postal_code')); ?></label>
                <input type="text" name="plz" value="<?php echo esc_attr($store->plz ?? ''); ?>">
            </div>
            <div class="ppv-form-group">
                <label data-i18n="city"><?php echo esc_html(PPV_Lang::t('city')); ?></label>
                <input type="text" name="city" value="<?php echo esc_attr($store->city ?? ''); ?>">
            </div>
        </div>

        <hr>

        <!-- ============================================================
             MEGLÉVŐ: HELYKOORDINÁTÁK (LOCATION)
             ============================================================ -->
        <h3 data-i18n="location_section"><?php echo esc_html(PPV_Lang::t('location_section')); ?></h3>

        <!-- LATITUDE -->
        <div class="ppv-form-group">
            <label data-i18n="latitude"><?php echo esc_html(PPV_Lang::t('latitude')); ?></label>
            <input type="number" step="0.0001" name="latitude" id="store_latitude" value="<?php echo esc_attr($store->latitude ?? ''); ?>" placeholder="47.5095">
        </div>

        <!-- LONGITUDE -->
        <div class="ppv-form-group">
            <label data-i18n="longitude"><?php echo esc_html(PPV_Lang::t('longitude')); ?></label>
            <input type="number" step="0.0001" name="longitude" id="store_longitude" value="<?php echo esc_attr($store->longitude ?? ''); ?>" placeholder="19.0408">
        </div>

        <!-- GEOCODE BUTTON -->
        <div class="ppv-form-group">
            <button type="button" id="ppv-geocode-btn" class="ppv-btn ppv-btn-secondary" style="width: 100%; margin-top: 10px;" data-i18n="geocode_button">
                🗺️ <?php echo esc_html(PPV_Lang::t('geocode_button')); ?>
            </button>
        </div>

        <!-- MAP (Google Maps / Leaflet) -->
        <div id="ppv-location-map" style="width: 100%; height: 300px; border-radius: 8px; margin-top: 12px; border: 1px solid #ddd; background: #f0f0f0;"></div>

        <hr>

        <!-- ============================================================
             MEGLÉVŐ: CÉGINFORMÁCIÓK (COMPANY)
             ============================================================ -->
        <h3 data-i18n="company_section"><?php echo esc_html(PPV_Lang::t('company_section')); ?></h3>

        <!-- COMPANY NAME -->
        <div class="ppv-form-group">
            <label data-i18n="company_name"><?php echo esc_html(PPV_Lang::t('company_name')); ?></label>
            <input type="text" name="company_name" value="<?php echo esc_attr($store->company_name ?? ''); ?>">
        </div>

        <!-- CONTACT PERSON -->
        <div class="ppv-form-group">
            <label data-i18n="contact_person"><?php echo esc_html(PPV_Lang::t('contact_person')); ?></label>
            <input type="text" name="contact_person" value="<?php echo esc_attr($store->contact_person ?? ''); ?>">
        </div>

        <!-- DESCRIPTION -->
        <div class="ppv-form-group">
            <label data-i18n="description"><?php echo esc_html(PPV_Lang::t('description')); ?></label>
            <textarea name="description"><?php echo esc_textarea($store->description ?? ''); ?></textarea>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

        private static function render_tab_hours($store) {
            ob_start();
            ?>
            <div class="ppv-tab-content" id="tab-hours">
                <h2 data-i18n="opening_hours"><?php echo esc_html(PPV_Lang::t('opening_hours')); ?></h2>
                <p data-i18n="opening_hours_info"><?php echo esc_html(PPV_Lang::t('opening_hours_info')); ?></p>

                <div class="ppv-opening-hours-wrapper" id="ppv-hours">
                    <?php
                    $days = ['mo', 'di', 'mi', 'do', 'fr', 'sa', 'so'];
                    $day_names = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                    $hours = json_decode($store->opening_hours ?? '{}', true);

                    foreach ($days as $idx => $day) {
                        $day_data = $hours[$day] ?? [];
                        $von = $day_data['von'] ?? '';
                        $bis = $day_data['bis'] ?? '';
                        $closed = $day_data['closed'] ?? 0;
                        ?>
                        <div class="ppv-hour-row">
                            <label data-i18n="<?php echo esc_attr($day_names[$idx]); ?>"><?php echo esc_html(PPV_Lang::t($day_names[$idx])); ?></label>
                            <div class="ppv-hour-inputs">
                                <input type="time" name="hours[<?php echo esc_attr($day); ?>][von]" value="<?php echo esc_attr($von); ?>">
                                <span>-</span>
                                <input type="time" name="hours[<?php echo esc_attr($day); ?>][bis]" value="<?php echo esc_attr($bis); ?>">
                                <label class="ppv-checkbox">
                                    <input type="checkbox" name="hours[<?php echo esc_attr($day); ?>][closed]" <?php checked($closed, 1); ?>>
                                    <span data-i18n="closed"><?php echo esc_html(PPV_Lang::t('closed')); ?></span>
                                </label>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private static function render_tab_media($store) {
            ob_start();
            ?>
            <div class="ppv-tab-content" id="tab-media">
                <h2 data-i18n="media_section"><?php echo esc_html(PPV_Lang::t('media_section')); ?></h2>

                <div class="ppv-media-group">
                    <label data-i18n="logo"><?php echo esc_html(PPV_Lang::t('logo')); ?></label>
                    <p class="ppv-help" data-i18n="logo_info"><?php echo esc_html(PPV_Lang::t('logo_info')); ?></p>
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="ppv-file-input">
                    <input type="hidden" name="delete_logo" id="ppv-delete-logo" value="0">
               <div id="ppv-logo-preview" class="ppv-media-preview" style="display: flex; align-items: center; justify-content: center; min-height: 120px; border: 1px solid #ddd; border-radius: 4px; position: relative;">
    <?php if (!empty($store->logo)): ?>
        <img src="<?php echo esc_url($store->logo); ?>" alt="Logo" style="max-width: 100%; max-height: 100px; object-fit: contain;">
        <button type="button" id="ppv-delete-logo-btn" class="ppv-delete-logo-btn" style="position: absolute; top: 5px; right: 5px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px;" title="<?php echo esc_attr(PPV_Lang::t('delete_logo')); ?>">
            <i class="ri-delete-bin-line"></i>
        </button>
    <?php endif; ?>
</div>
                </div>

                <div class="ppv-media-group">
                    <label data-i18n="gallery"><?php echo esc_html(PPV_Lang::t('gallery')); ?></label>
                    <p class="ppv-help" data-i18n="gallery_info"><?php echo esc_html(PPV_Lang::t('gallery_info')); ?></p>
                    <input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp" class="ppv-file-input">
<div id="ppv-gallery-preview" class="ppv-gallery-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 10px;">
<?php 

if (!empty($store->gallery)) {
    $gallery = json_decode($store->gallery, true);
    if (is_array($gallery)) {
        foreach ($gallery as $image_url) {
            echo '<div class="ppv-gallery-item" style="position: relative; display: inline-block; width: 100%;">';
            // ✅ OPTIMIZED: Added loading="lazy" for performance
            echo '<img src="' . esc_url($image_url) . '" alt="Gallery" loading="lazy" style="width: 100%; height: auto; border-radius: 4px;">';
            echo '<button type="button" class="ppv-gallery-delete-btn" data-image-url="' . esc_attr($image_url) . '" style="position: absolute; top: -10px; right: -10px; background: red; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 18px; padding: 0; line-height: 1;">×</button>';
            echo '</div>';
        }
    }
}
?>
</div>                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private static function render_tab_contact($store) {
            ob_start();
            ?>
            <div class="ppv-tab-content" id="tab-contact">
                <h2 data-i18n="contact_data"><?php echo esc_html(PPV_Lang::t('contact_data')); ?></h2>

                <div class="ppv-form-group">
                    <label data-i18n="phone"><?php echo esc_html(PPV_Lang::t('phone')); ?></label>
                    <input type="tel" name="phone" value="<?php echo esc_attr($store->phone ?? ''); ?>">
                </div>

                <div class="ppv-form-group">
                    <label data-i18n="public_email"><?php echo esc_html(PPV_Lang::t('public_email') ?: 'Öffentliche E-Mail'); ?></label>
                    <input type="email" name="public_email" value="<?php echo esc_attr($store->public_email ?? ''); ?>" placeholder="<?php echo esc_attr(PPV_Lang::t('public_email_placeholder') ?: 'Wird auf Store-Karte angezeigt'); ?>">
                    <small class="ppv-field-hint" style="color: #888; font-size: 12px; margin-top: 4px; display: block;">
                        <?php echo esc_html(PPV_Lang::t('public_email_hint') ?: 'Diese E-Mail wird Kunden auf Ihrer Store-Karte angezeigt (nicht Ihre Login-E-Mail)'); ?>
                    </small>
                </div>

                <div class="ppv-form-group">
                    <label data-i18n="website"><?php echo esc_html(PPV_Lang::t('website')); ?></label>
                    <input type="url" name="website" value="<?php echo esc_attr($store->website ?? ''); ?>">
                </div>

                <div class="ppv-form-group">
                    <label data-i18n="whatsapp"><?php echo esc_html(PPV_Lang::t('whatsapp')); ?></label>
                    <input type="tel" name="whatsapp" value="<?php echo esc_attr($store->whatsapp ?? ''); ?>">
                </div>

                <hr>

                <h3 data-i18n="social_media"><?php echo esc_html(PPV_Lang::t('social_media')); ?></h3>

                <div class="ppv-form-group">
                    <label data-i18n="facebook_url"><?php echo esc_html(PPV_Lang::t('facebook_url')); ?></label>
                    <input type="url" name="facebook" value="<?php echo esc_attr($store->facebook ?? ''); ?>">
                </div>

                <div class="ppv-form-group">
                    <label data-i18n="instagram_url"><?php echo esc_html(PPV_Lang::t('instagram_url')); ?></label>
                    <input type="url" name="instagram" value="<?php echo esc_attr($store->instagram ?? ''); ?>">
                </div>

                <div class="ppv-form-group">
                    <label data-i18n="tiktok_url"><?php echo esc_html(PPV_Lang::t('tiktok_url')); ?></label>
                    <input type="url" name="tiktok" value="<?php echo esc_attr($store->tiktok ?? ''); ?>">
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * ============================================================
         * MARKETING & AUTOMATION TAB
         * ============================================================
         */
        private static function render_tab_marketing($store) {
            ob_start();
            ?>
            <div class="ppv-tab-content" id="tab-marketing">
                <h2 data-i18n="marketing_automation"><?php echo esc_html(PPV_Lang::t('marketing_automation')); ?></h2>
                <p class="ppv-help" style="margin-bottom: 20px;" data-i18n="marketing_automation_help">
                    <?php echo esc_html(PPV_Lang::t('marketing_automation_help')); ?>
                </p>

                <!-- ============================================================
                     GOOGLE REVIEW REQUEST
                     ============================================================ -->
                <div class="ppv-marketing-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div class="ppv-marketing-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div>
                            <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 24px;">⭐</span>
                                <span data-i18n="google_review_title"><?php echo esc_html(PPV_Lang::t('google_review_title')); ?></span>
                            </h3>
                            <small style="color: #888;" data-i18n="google_review_desc"><?php echo esc_html(PPV_Lang::t('google_review_desc')); ?></small>
                        </div>
                        <label class="ppv-toggle">
                            <input type="checkbox" name="google_review_enabled" value="1" <?php checked($store->google_review_enabled ?? 0, 1); ?>>
                            <span class="ppv-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="ppv-marketing-body" id="google-review-settings" style="<?php echo empty($store->google_review_enabled) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                        <div class="ppv-form-group">
                            <label data-i18n="google_review_url"><?php echo esc_html(PPV_Lang::t('google_review_url')); ?></label>
                            <input type="url" name="google_review_url" value="<?php echo esc_attr($store->google_review_url ?? ''); ?>" placeholder="https://g.page/r/...">
                            <small style="color: #666;" data-i18n="google_review_url_help"><?php echo esc_html(PPV_Lang::t('google_review_url_help')); ?></small>
                        </div>

                        <div class="ppv-form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="ppv-form-group">
                                <label data-i18n="google_review_threshold"><?php echo esc_html(PPV_Lang::t('google_review_threshold')); ?></label>
                                <input type="number" name="google_review_threshold" value="<?php echo esc_attr($store->google_review_threshold ?? 100); ?>" min="10" max="1000" step="10">
                            </div>
                            <div class="ppv-form-group">
                                <label data-i18n="google_review_bonus_points"><?php echo esc_html(PPV_Lang::t('google_review_bonus_points')); ?></label>
                                <input type="number" name="google_review_bonus_points" value="<?php echo esc_attr($store->google_review_bonus_points ?? 5); ?>" min="0" max="100" step="1">
                                <small style="color: #888;" data-i18n="google_review_bonus_help"><?php echo esc_html(PPV_Lang::t('google_review_bonus_help')); ?></small>
                            </div>
                        </div>

                        <!-- How it works collapsible -->
                        <details class="ppv-how-it-works" style="margin-top: 15px; background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.2); border-radius: 10px; overflow: hidden;">
                            <summary style="padding: 12px 15px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 500; color: #3b82f6; list-style: none;">
                                <span style="font-size: 16px;">💡</span>
                                <span data-i18n="google_review_how_it_works"><?php echo esc_html(PPV_Lang::t('google_review_how_it_works')); ?></span>
                                <svg style="margin-left: auto; width: 16px; height: 16px; transition: transform 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div style="padding: 0 15px 15px 15px; color: #ccc; font-size: 13px; line-height: 1.6;">
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                        <span style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">1</span>
                                        <span data-i18n="google_review_step1"><?php echo esc_html(PPV_Lang::t('google_review_step1')); ?></span>
                                    </div>
                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                        <span style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">2</span>
                                        <span data-i18n="google_review_step2"><?php echo esc_html(PPV_Lang::t('google_review_step2')); ?></span>
                                    </div>
                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                        <span style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">3</span>
                                        <span data-i18n="google_review_step3"><?php echo esc_html(PPV_Lang::t('google_review_step3')); ?></span>
                                    </div>
                                </div>
                            </div>
                        </details>
                        <style>
                            .ppv-how-it-works[open] summary svg { transform: rotate(180deg); }
                            .ppv-how-it-works summary::-webkit-details-marker { display: none; }
                        </style>
                    </div>
                </div>

                <!-- ============================================================
                     BIRTHDAY BONUS
                     ============================================================ -->
                <div class="ppv-marketing-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div class="ppv-marketing-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div>
                            <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 24px;">🎂</span>
                                <span data-i18n="birthday_bonus_title"><?php echo esc_html(PPV_Lang::t('birthday_bonus_title')); ?></span>
                            </h3>
                            <small style="color: #888;" data-i18n="birthday_bonus_desc"><?php echo esc_html(PPV_Lang::t('birthday_bonus_desc')); ?></small>
                        </div>
                        <label class="ppv-toggle">
                            <input type="checkbox" name="birthday_bonus_enabled" value="1" <?php checked($store->birthday_bonus_enabled ?? 0, 1); ?>>
                            <span class="ppv-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="ppv-marketing-body" id="birthday-bonus-settings" style="<?php echo empty($store->birthday_bonus_enabled) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                        <div class="ppv-form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="ppv-form-group">
                                <label data-i18n="birthday_bonus_type"><?php echo esc_html(PPV_Lang::t('birthday_bonus_type')); ?></label>
                                <select name="birthday_bonus_type" id="birthday_bonus_type">
                                    <option value="double_points" <?php selected($store->birthday_bonus_type ?? 'double_points', 'double_points'); ?> data-i18n="bonus_double_points"><?php echo esc_html(PPV_Lang::t('bonus_double_points')); ?></option>
                                    <option value="fixed_points" <?php selected($store->birthday_bonus_type ?? '', 'fixed_points'); ?> data-i18n="bonus_fixed_points"><?php echo esc_html(PPV_Lang::t('bonus_fixed_points')); ?></option>
                                    <option value="free_product" <?php selected($store->birthday_bonus_type ?? '', 'free_product'); ?> data-i18n="bonus_free_product"><?php echo esc_html(PPV_Lang::t('bonus_free_product')); ?></option>
                                </select>
                            </div>
                            <div class="ppv-form-group" id="birthday_bonus_value_group" style="<?php echo ($store->birthday_bonus_type ?? 'double_points') !== 'fixed_points' ? 'display: none;' : ''; ?>">
                                <label data-i18n="birthday_bonus_value"><?php echo esc_html(PPV_Lang::t('birthday_bonus_value')); ?></label>
                                <input type="number" id="birthday_bonus_value_input" name="birthday_bonus_value" value="<?php echo esc_attr($store->birthday_bonus_value ?? 50); ?>" min="1" max="1000" <?php echo ($store->birthday_bonus_type ?? 'double_points') !== 'fixed_points' ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <div class="ppv-form-group">
                            <label data-i18n="birthday_bonus_message"><?php echo esc_html(PPV_Lang::t('birthday_bonus_message')); ?></label>
                            <textarea name="birthday_bonus_message" rows="2" placeholder="<?php echo esc_attr(PPV_Lang::t('birthday_bonus_message_placeholder')); ?>"><?php echo esc_textarea($store->birthday_bonus_message ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     COMEBACK CAMPAIGN
                     ============================================================ -->
                <div class="ppv-marketing-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div class="ppv-marketing-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div>
                            <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 24px;">👋</span>
                                <span data-i18n="comeback_title"><?php echo esc_html(PPV_Lang::t('comeback_title')); ?></span>
                            </h3>
                            <small style="color: #888;" data-i18n="comeback_desc"><?php echo esc_html(PPV_Lang::t('comeback_desc')); ?></small>
                        </div>
                        <label class="ppv-toggle">
                            <input type="checkbox" name="comeback_enabled" value="1" <?php checked($store->comeback_enabled ?? 0, 1); ?>>
                            <span class="ppv-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="ppv-marketing-body" id="comeback-settings" style="<?php echo empty($store->comeback_enabled) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                        <div class="ppv-form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="ppv-form-group">
                                <label data-i18n="comeback_days"><?php echo esc_html(PPV_Lang::t('comeback_days')); ?></label>
                                <select name="comeback_days">
                                    <option value="14" <?php selected($store->comeback_days ?? 30, 14); ?>>14 <?php echo esc_html(PPV_Lang::t('days')); ?></option>
                                    <option value="30" <?php selected($store->comeback_days ?? 30, 30); ?>>30 <?php echo esc_html(PPV_Lang::t('days')); ?></option>
                                    <option value="60" <?php selected($store->comeback_days ?? 30, 60); ?>>60 <?php echo esc_html(PPV_Lang::t('days')); ?></option>
                                    <option value="90" <?php selected($store->comeback_days ?? 30, 90); ?>>90 <?php echo esc_html(PPV_Lang::t('days')); ?></option>
                                </select>
                            </div>
                            <div class="ppv-form-group">
                                <label data-i18n="comeback_bonus_type"><?php echo esc_html(PPV_Lang::t('comeback_bonus_type')); ?></label>
                                <select name="comeback_bonus_type" id="comeback_bonus_type">
                                    <option value="double_points" <?php selected($store->comeback_bonus_type ?? 'double_points', 'double_points'); ?> data-i18n="bonus_double_points"><?php echo esc_html(PPV_Lang::t('bonus_double_points')); ?></option>
                                    <option value="fixed_points" <?php selected($store->comeback_bonus_type ?? '', 'fixed_points'); ?> data-i18n="bonus_fixed_points"><?php echo esc_html(PPV_Lang::t('bonus_fixed_points')); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="ppv-form-group" id="comeback_bonus_value_group" style="<?php echo ($store->comeback_bonus_type ?? 'double_points') !== 'fixed_points' ? 'display: none;' : ''; ?>">
                            <label data-i18n="comeback_bonus_value"><?php echo esc_html(PPV_Lang::t('comeback_bonus_value')); ?></label>
                            <input type="number" id="comeback_bonus_value_input" name="comeback_bonus_value" value="<?php echo esc_attr($store->comeback_bonus_value ?? 50); ?>" min="1" max="500" <?php echo ($store->comeback_bonus_type ?? 'double_points') !== 'fixed_points' ? 'disabled' : ''; ?>>
                        </div>

                        <div class="ppv-form-group">
                            <label data-i18n="comeback_message"><?php echo esc_html(PPV_Lang::t('comeback_message')); ?></label>
                            <textarea name="comeback_message" rows="2" placeholder="<?php echo esc_attr(PPV_Lang::t('comeback_message_placeholder')); ?>"><?php echo esc_textarea($store->comeback_message ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     WHATSAPP CLOUD API
                     ============================================================ -->
                <div class="ppv-marketing-card" style="background: linear-gradient(135deg, rgba(37, 211, 102, 0.08), rgba(37, 211, 102, 0.02)); border: 1px solid rgba(37, 211, 102, 0.3); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div class="ppv-marketing-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 24px;">💬</span>
                                <span>WhatsApp Business</span>
                                <?php if (!empty($store->whatsapp_enabled)): ?>
                                <span style="background: #25D366; color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600;" data-i18n="status_active"><?php echo esc_html(PPV_Lang::t('status_active')); ?></span>
                                <?php else: ?>
                                <span style="background: rgba(255,255,255,0.1); color: #888; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600;" data-i18n="status_inactive"><?php echo esc_html(PPV_Lang::t('status_inactive')); ?></span>
                                <?php endif; ?>
                            </h3>
                            <small style="color: #888;" data-i18n="whatsapp_desc"><?php echo esc_html(PPV_Lang::t('whatsapp_desc')); ?></small>
                        </div>
                        <label class="ppv-toggle">
                            <input type="checkbox" name="whatsapp_enabled" value="1" <?php checked($store->whatsapp_enabled ?? 0, 1); ?>>
                            <span class="ppv-toggle-slider"></span>
                        </label>
                    </div>
                    <?php if (empty($store->whatsapp_phone_id)): ?>
                    <div style="margin-top: 15px; padding: 12px; background: rgba(255,152,0,0.1); border: 1px solid rgba(255,152,0,0.3); border-radius: 8px;">
                        <p style="margin: 0; color: #ff9800; font-size: 13px;">
                            <strong>⚠️ <?php echo esc_html(PPV_Lang::t('whatsapp_not_configured')); ?></strong><br>
                            <span style="color: #888;"><?php echo esc_html(PPV_Lang::t('whatsapp_managed_by_team')); ?></span>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ============================================================
                     REFERRAL PROGRAM
                     ============================================================ -->
                <?php
                // Calculate grace period status
                $referral_activated_at = $store->referral_activated_at ?? null;
                $referral_grace_days = intval($store->referral_grace_days ?? 60);
                $grace_period_over = false;
                $grace_days_remaining = $referral_grace_days;

                if ($referral_activated_at) {
                    $activated_date = new DateTime($referral_activated_at);
                    $now = new DateTime();
                    $days_since_activation = $now->diff($activated_date)->days;
                    $grace_days_remaining = max(0, $referral_grace_days - $days_since_activation);
                    $grace_period_over = ($grace_days_remaining === 0);
                }
                ?>
                <div class="ppv-marketing-card" style="background: linear-gradient(135deg, rgba(255, 107, 107, 0.08), rgba(255, 107, 107, 0.02)); border: 1px solid rgba(255, 107, 107, 0.3); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div class="ppv-marketing-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div>
                            <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 24px;">🎁</span>
                                <span><?php echo PPV_Lang::t('referral_admin_title') ?: 'Referral Program'; ?></span>
                                <?php if (!$referral_activated_at): ?>
                                    <span style="background: rgba(255,255,255,0.1); color: #888; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600;"><?php echo PPV_Lang::t('referral_admin_not_started') ?: 'NEU'; ?></span>
                                <?php elseif (!$grace_period_over): ?>
                                    <span style="background: rgba(255,152,0,0.3); color: #ff9800; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600;">⏳ <?php echo $grace_days_remaining; ?> <?php echo PPV_Lang::t('days') ?: 'Tage'; ?></span>
                                <?php elseif (!empty($store->referral_enabled)): ?>
                                    <span style="background: #ff6b6b; color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600;"><?php echo strtoupper(PPV_Lang::t('referral_admin_active') ?: 'AKTIV'); ?></span>
                                <?php endif; ?>
                            </h3>
                            <small style="color: #888;"><?php echo PPV_Lang::t('referral_section_subtitle') ?: 'Kunden werben Kunden - Neue Kunden durch Empfehlungen'; ?></small>
                        </div>
                        <label class="ppv-toggle">
                            <input type="checkbox" name="referral_enabled" value="1" <?php checked($store->referral_enabled ?? 0, 1); ?> <?php echo !$grace_period_over ? 'disabled' : ''; ?>>
                            <span class="ppv-toggle-slider"></span>
                        </label>
                    </div>

                    <?php if (!$referral_activated_at): ?>
                    <!-- Not yet activated - show activation prompt -->
                    <div style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 20px; text-align: center;">
                        <p style="color: #f1f5f9; margin: 0 0 15px;">
                            <strong>🚀 <?php echo PPV_Lang::t('referral_admin_start_title') ?: 'Referral Program starten'; ?></strong><br>
                            <span style="color: #888; font-size: 13px;">
                                <?php echo sprintf(PPV_Lang::t('referral_admin_start_desc') ?: 'Nach der Aktivierung beginnt eine %d-tägige Sammelphase. In dieser Zeit werden alle bestehenden Kunden erfasst, damit nur echte Neukunden als Referrals zählen.', $referral_grace_days); ?>
                            </span>
                        </p>
                        <button type="button" id="activate-referral-btn" class="ppv-btn" style="background: linear-gradient(135deg, #ff6b6b, #ee5a5a); border: none; color: white; padding: 12px 24px; border-radius: 8px; cursor: pointer;">
                            <i class="ri-rocket-line"></i> <?php echo PPV_Lang::t('referral_admin_start_btn') ?: 'Jetzt starten'; ?>
                        </button>
                    </div>

                    <?php elseif (!$grace_period_over): ?>
                    <!-- Grace period active -->
                    <div style="background: rgba(255,152,0,0.1); border: 1px solid rgba(255,152,0,0.3); border-radius: 8px; padding: 15px;">
                        <p style="margin: 0; color: #ff9800;">
                            <strong>⏳ <?php echo PPV_Lang::t('referral_admin_grace_title') ?: 'Sammelphase läuft'; ?></strong><br>
                            <span style="color: #888;">
                                <?php echo sprintf(PPV_Lang::t('referral_admin_grace_remaining') ?: 'Noch %d Tage bis das Referral Program aktiviert werden kann.', $grace_days_remaining); ?><br>
                                <?php echo PPV_Lang::t('referral_admin_grace_desc') ?: 'In dieser Zeit werden bestehende Kunden erfasst.'; ?>
                            </span>
                        </p>
                        <div style="margin-top: 10px; background: rgba(0,0,0,0.2); border-radius: 4px; height: 8px; overflow: hidden;">
                            <?php $progress = (($referral_grace_days - $grace_days_remaining) / $referral_grace_days) * 100; ?>
                            <div style="background: linear-gradient(90deg, #ff6b6b, #ff9800); height: 100%; width: <?php echo $progress; ?>%; transition: width 0.3s;"></div>
                        </div>
                    </div>

                    <!-- Grace period settings (editable) -->
                    <div style="margin-top: 15px; background: rgba(0,0,0,0.2); border-radius: 8px; padding: 15px;">
                        <div class="ppv-form-group" style="margin-bottom: 0;">
                            <label><?php echo PPV_Lang::t('referral_admin_grace_days') ?: 'Grace Period (Tage)'; ?></label>
                            <input type="number" name="referral_grace_days" value="<?php echo esc_attr($referral_grace_days); ?>" min="7" max="180" style="width: 100px;">
                            <small style="color: #666;"><?php echo PPV_Lang::t('min_max_days') ?: 'Mindestens 7, maximal 180 Tage'; ?></small>
                        </div>
                    </div>

                    <?php else: ?>
                    <!-- Grace period over - show full settings -->
                    <div class="ppv-marketing-body" id="referral-settings" style="<?php echo empty($store->referral_enabled) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">

                        <!-- Reward Type Selection -->
                        <div style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                            <h4 style="margin: 0 0 10px; color: #ff6b6b; font-size: 14px;">🎁 <?php echo PPV_Lang::t('referral_admin_reward_type') ?: 'Belohnung konfigurieren'; ?></h4>
                            <p style="color: #888; font-size: 12px; margin-bottom: 15px;"><?php echo PPV_Lang::t('referral_section_subtitle') ?: 'Was bekommen Werber und Geworbener?'; ?></p>

                            <div class="ppv-form-row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">
                                <label style="display: flex; flex-direction: column; align-items: center; gap: 8px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; cursor: pointer; border: 2px solid <?php echo ($store->referral_reward_type ?? 'points') === 'points' ? '#ff6b6b' : 'transparent'; ?>;">
                                    <input type="radio" name="referral_reward_type" value="points" <?php checked($store->referral_reward_type ?? 'points', 'points'); ?> style="display: none;" onchange="updateReferralRewardUI()">
                                    <span style="font-size: 24px;">⭐</span>
                                    <strong style="color: #f1f5f9;"><?php echo PPV_Lang::t('referral_admin_reward_points') ?: 'Punkte'; ?></strong>
                                </label>

                                <label style="display: flex; flex-direction: column; align-items: center; gap: 8px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; cursor: pointer; border: 2px solid <?php echo ($store->referral_reward_type ?? '') === 'euro' ? '#ff6b6b' : 'transparent'; ?>;">
                                    <input type="radio" name="referral_reward_type" value="euro" <?php checked($store->referral_reward_type ?? '', 'euro'); ?> style="display: none;" onchange="updateReferralRewardUI()">
                                    <span style="font-size: 24px;">💶</span>
                                    <strong style="color: #f1f5f9;"><?php echo PPV_Lang::t('referral_admin_reward_euro') ?: 'Euro'; ?></strong>
                                </label>

                                <label style="display: flex; flex-direction: column; align-items: center; gap: 8px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; cursor: pointer; border: 2px solid <?php echo ($store->referral_reward_type ?? '') === 'gift' ? '#ff6b6b' : 'transparent'; ?>;">
                                    <input type="radio" name="referral_reward_type" value="gift" <?php checked($store->referral_reward_type ?? '', 'gift'); ?> style="display: none;" onchange="updateReferralRewardUI()">
                                    <span style="font-size: 24px;">🎀</span>
                                    <strong style="color: #f1f5f9;"><?php echo PPV_Lang::t('referral_admin_reward_gift') ?: 'Geschenk'; ?></strong>
                                </label>
                            </div>

                            <!-- Points value -->
                            <div id="referral-value-points" class="ppv-form-group" style="<?php echo ($store->referral_reward_type ?? 'points') !== 'points' ? 'display:none;' : ''; ?>">
                                <label><?php echo PPV_Lang::t('referral_admin_points_value') ?: 'Punkte pro Empfehlung'; ?></label>
                                <input type="number" name="referral_reward_value" value="<?php echo esc_attr($store->referral_reward_value ?? 50); ?>" min="1" max="500" style="width: 100px;">
                                <small style="color: #666;"><?php echo PPV_Lang::t('referral_section_subtitle') ?: 'Werber UND Geworbener bekommen diese Punkte'; ?></small>
                            </div>

                            <!-- Euro value -->
                            <div id="referral-value-euro" class="ppv-form-group" style="<?php echo ($store->referral_reward_type ?? '') !== 'euro' ? 'display:none;' : ''; ?>">
                                <label><?php echo PPV_Lang::t('referral_admin_euro_value') ?: 'Euro Rabatt'; ?></label>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <input type="number" name="referral_reward_value_euro" value="<?php echo esc_attr($store->referral_reward_value ?? 5); ?>" min="1" max="50" style="width: 80px;">
                                    <span style="color: #888;">€</span>
                                </div>
                            </div>

                            <!-- Gift -->
                            <div id="referral-value-gift" class="ppv-form-group" style="<?php echo ($store->referral_reward_type ?? '') !== 'gift' ? 'display:none;' : ''; ?>">
                                <label><?php echo PPV_Lang::t('referral_admin_gift_value') ?: 'Geschenk-Beschreibung'; ?></label>
                                <input type="text" name="referral_reward_gift" value="<?php echo esc_attr($store->referral_reward_gift ?? ''); ?>" placeholder="<?php echo PPV_Lang::t('referral_admin_gift_placeholder') ?: 'z.B. Gratis Kaffee, 1x Dessert...'; ?>">
                            </div>
                        </div>

                        <!-- Manual Approval -->
                        <div style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="referral_manual_approval" value="1" <?php checked($store->referral_manual_approval ?? 0, 1); ?> style="width: 18px; height: 18px;">
                                <span>
                                    <strong style="color: #f1f5f9;">🔍 <?php echo PPV_Lang::t('referral_admin_manual_approval') ?: 'Manuelle Freigabe'; ?></strong><br>
                                    <small style="color: #888;"><?php echo PPV_Lang::t('referral_admin_manual_desc') ?: 'Jeden neuen Referral vor der Belohnung prüfen'; ?></small>
                                </span>
                            </label>
                        </div>

                        <!-- Statistics -->
                        <?php
                        global $wpdb;
                        $referral_stats = $wpdb->get_row($wpdb->prepare(
                            "SELECT
                                COUNT(*) as total,
                                SUM(CASE WHEN status IN ('completed', 'approved') THEN 1 ELSE 0 END) as successful,
                                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                             FROM {$wpdb->prefix}ppv_referrals WHERE store_id = %d",
                            $store->id
                        ));
                        ?>
                        <div style="background: rgba(255,107,107,0.1); border: 1px solid rgba(255,107,107,0.3); border-radius: 8px; padding: 15px;">
                            <h4 style="margin: 0 0 10px; color: #ff6b6b; font-size: 14px;">📊 <?php echo PPV_Lang::t('referral_admin_stats') ?: 'Referral Statistik'; ?></h4>
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; text-align: center;">
                                <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px;">
                                    <div style="font-size: 24px; font-weight: bold; color: #f1f5f9;"><?php echo intval($referral_stats->total ?? 0); ?></div>
                                    <div style="font-size: 11px; color: #888;"><?php echo PPV_Lang::t('referral_admin_total') ?: 'Gesamt'; ?></div>
                                </div>
                                <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px;">
                                    <div style="font-size: 24px; font-weight: bold; color: #4caf50;"><?php echo intval($referral_stats->successful ?? 0); ?></div>
                                    <div style="font-size: 11px; color: #888;"><?php echo PPV_Lang::t('referral_successful') ?: 'Erfolgreich'; ?></div>
                                </div>
                                <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px;">
                                    <div style="font-size: 24px; font-weight: bold; color: #ff9800;"><?php echo intval($referral_stats->pending ?? 0); ?></div>
                                    <div style="font-size: 11px; color: #888;"><?php echo PPV_Lang::t('referral_pending') ?: 'Ausstehend'; ?></div>
                                </div>
                                <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px;">
                                    <div style="font-size: 24px; font-weight: bold; color: #f44336;"><?php echo intval($referral_stats->rejected ?? 0); ?></div>
                                    <div style="font-size: 11px; color: #888;"><?php echo PPV_Lang::t('referral_admin_rejected') ?: 'Abgelehnt'; ?></div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <?php endif; ?>

                    <!-- How it works collapsible (always visible) -->
                    <details class="ppv-how-it-works" style="margin-top: 15px; background: rgba(255,107,107,0.08); border: 1px solid rgba(255,107,107,0.2); border-radius: 10px; overflow: hidden;">
                        <summary style="padding: 12px 15px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 500; color: #ff6b6b; list-style: none;">
                            <span style="font-size: 16px;">💡</span>
                            <span data-i18n="referral_how_it_works"><?php echo esc_html(PPV_Lang::t('referral_how_it_works') ?: 'So funktioniert\'s'); ?></span>
                            <svg style="margin-left: auto; width: 16px; height: 16px; transition: transform 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <div style="padding: 0 15px 15px 15px; color: #ccc; font-size: 13px; line-height: 1.6;">
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <div style="display: flex; align-items: flex-start; gap: 10px;">
                                    <span style="background: linear-gradient(135deg, #ff6b6b, #ee5a5a); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">1</span>
                                    <span data-i18n="referral_step1"><?php echo esc_html(PPV_Lang::t('referral_step1') ?: 'Ein Kunde teilt seinen persönlichen Empfehlungslink oder QR-Code mit Freunden und Familie.'); ?></span>
                                </div>
                                <div style="display: flex; align-items: flex-start; gap: 10px;">
                                    <span style="background: linear-gradient(135deg, #ff6b6b, #ee5a5a); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">2</span>
                                    <span data-i18n="referral_step2"><?php echo esc_html(PPV_Lang::t('referral_step2') ?: 'Der geworbene Neukunde registriert sich über den Link und sammelt seine ersten Punkte.'); ?></span>
                                </div>
                                <div style="display: flex; align-items: flex-start; gap: 10px;">
                                    <span style="background: linear-gradient(135deg, #ff6b6b, #ee5a5a); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">3</span>
                                    <span data-i18n="referral_step3"><?php echo esc_html(PPV_Lang::t('referral_step3') ?: 'Beide erhalten automatisch die eingestellte Belohnung - der Werber und der Geworbene.'); ?></span>
                                </div>
                            </div>
                        </div>
                    </details>
                </div>

                <!-- Info box -->
                <div class="ppv-info-box" style="background: rgba(0, 230, 255, 0.05); border: 1px solid rgba(0, 230, 255, 0.2); border-radius: 8px; padding: 15px; margin-top: 20px;">
                    <p style="margin: 0; color: #00e6ff; font-size: 13px;">
                        <strong>💡 <?php echo esc_html(PPV_Lang::t('marketing_tip_title')); ?></strong><br>
                        <span style="color: #888;"><?php echo esc_html(PPV_Lang::t('marketing_tip_text')); ?></span>
                    </p>
                </div>
            </div>

            <script>
            // WhatsApp nonce for AJAX
            window.ppvWhatsAppNonce = '<?php echo wp_create_nonce('ppv_whatsapp_nonce'); ?>';
            window.ppvWhatsAppStoreId = <?php echo intval($store->id ?? 0); ?>;

            // Referral Program UI
            function updateReferralRewardUI() {
                const type = document.querySelector('input[name="referral_reward_type"]:checked')?.value || 'points';

                // Update radio button styles
                document.querySelectorAll('input[name="referral_reward_type"]').forEach(radio => {
                    const label = radio.closest('label');
                    if (label) {
                        label.style.borderColor = radio.checked ? '#ff6b6b' : 'transparent';
                    }
                });

                // Show/hide value inputs
                document.getElementById('referral-value-points').style.display = type === 'points' ? 'block' : 'none';
                document.getElementById('referral-value-euro').style.display = type === 'euro' ? 'block' : 'none';
                document.getElementById('referral-value-gift').style.display = type === 'gift' ? 'block' : 'none';
            }

            // Referral toggle
            document.addEventListener('DOMContentLoaded', function() {
                const referralToggle = document.querySelector('input[name="referral_enabled"]');
                const referralSettings = document.getElementById('referral-settings');
                if (referralToggle && referralSettings) {
                    referralToggle.addEventListener('change', function() {
                        referralSettings.style.opacity = this.checked ? '1' : '0.5';
                        referralSettings.style.pointerEvents = this.checked ? 'auto' : 'none';
                    });
                }

                // Activate referral grace period button
                const activateBtn = document.getElementById('activate-referral-btn');
                if (activateBtn) {
                    activateBtn.addEventListener('click', function() {
                        if (!confirm('Grace Period starten? Diese Aktion kann nicht rückgängig gemacht werden.')) return;

                        this.disabled = true;
                        this.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Wird aktiviert...';

                        jQuery.ajax({
                            url: ppv_profile.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'ppv_activate_referral_grace_period',
                                store_id: window.ppvWhatsAppStoreId,
                                nonce: ppv_profile.nonce
                            },
                            success: function(res) {
                                if (res.success) {
                                    location.reload();
                                } else {
                                    alert(res.data?.msg || 'Fehler');
                                    activateBtn.disabled = false;
                                    activateBtn.innerHTML = '<i class="ri-rocket-line"></i> Grace Period starten';
                                }
                            },
                            error: function() {
                                alert('Netzwerkfehler');
                                activateBtn.disabled = false;
                                activateBtn.innerHTML = '<i class="ri-rocket-line"></i> Grace Period starten';
                            }
                        });
                    });
                }
            });
            </script>
            <script>
            // Toggle settings visibility based on checkbox state
            document.addEventListener('DOMContentLoaded', function() {
                // Google Review toggle
                const googleToggle = document.querySelector('input[name="google_review_enabled"]');
                const googleSettings = document.getElementById('google-review-settings');
                if (googleToggle && googleSettings) {
                    googleToggle.addEventListener('change', function() {
                        googleSettings.style.opacity = this.checked ? '1' : '0.5';
                        googleSettings.style.pointerEvents = this.checked ? 'auto' : 'none';
                    });
                }

                // Birthday Bonus toggle
                const birthdayToggle = document.querySelector('input[name="birthday_bonus_enabled"]');
                const birthdaySettings = document.getElementById('birthday-bonus-settings');
                if (birthdayToggle && birthdaySettings) {
                    birthdayToggle.addEventListener('change', function() {
                        birthdaySettings.style.opacity = this.checked ? '1' : '0.5';
                        birthdaySettings.style.pointerEvents = this.checked ? 'auto' : 'none';
                    });
                }

                // Birthday bonus type change - show/hide value field
                const birthdayType = document.getElementById('birthday_bonus_type');
                const birthdayValueGroup = document.getElementById('birthday_bonus_value_group');
                const birthdayValueInput = document.getElementById('birthday_bonus_value_input');
                if (birthdayType && birthdayValueGroup && birthdayValueInput) {
                    birthdayType.addEventListener('change', function() {
                        const isFixed = this.value === 'fixed_points';
                        birthdayValueGroup.style.display = isFixed ? 'block' : 'none';
                        birthdayValueInput.disabled = !isFixed;
                    });
                }

                // Comeback toggle
                const comebackToggle = document.querySelector('input[name="comeback_enabled"]');
                const comebackSettings = document.getElementById('comeback-settings');
                if (comebackToggle && comebackSettings) {
                    comebackToggle.addEventListener('change', function() {
                        comebackSettings.style.opacity = this.checked ? '1' : '0.5';
                        comebackSettings.style.pointerEvents = this.checked ? 'auto' : 'none';
                    });
                }

                // Comeback bonus type change - show/hide value field
                const comebackType = document.getElementById('comeback_bonus_type');
                const comebackValueGroup = document.getElementById('comeback_bonus_value_group');
                const comebackValueInput = document.getElementById('comeback_bonus_value_input');
                if (comebackType && comebackValueGroup && comebackValueInput) {
                    comebackType.addEventListener('change', function() {
                        const isFixed = this.value === 'fixed_points';
                        comebackValueGroup.style.display = isFixed ? 'block' : 'none';
                        comebackValueInput.disabled = !isFixed;
                    });
                }

                // Google Review Test Email Button
                const testBtn = document.getElementById('google-review-test-btn');
                const testEmailInput = document.getElementById('google-review-test-email');
                const testResult = document.getElementById('google-review-test-result');

                if (testBtn && testEmailInput && testResult) {
                    testBtn.addEventListener('click', function() {
                        const email = testEmailInput.value.trim();
                        if (!email) {
                            testResult.style.display = 'block';
                            testResult.innerHTML = '<span style="color: #ef4444;">⚠️ Kérlek add meg az email címet!</span>';
                            return;
                        }

                        // Disable button and show loading
                        testBtn.disabled = true;
                        testBtn.innerHTML = '⏳ Küldés...';
                        testResult.style.display = 'block';
                        testResult.innerHTML = '<span style="color: #888;">Email küldése folyamatban...</span>';

                        // Get store ID from form
                        const storeIdInput = document.querySelector('input[name="store_id"]');
                        const storeId = storeIdInput ? storeIdInput.value : 0;

                        // Send AJAX request
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=ppv_test_google_review&store_id=' + storeId + '&target_email=' + encodeURIComponent(email)
                        })
                        .then(response => response.json())
                        .then(data => {
                            testBtn.disabled = false;
                            testBtn.innerHTML = '📧 <?php echo esc_html(PPV_Lang::t('send_test') ?? 'Teszt küldés'); ?>';

                            if (data.success) {
                                testResult.innerHTML = '<span style="color: #22c55e;">✅ ' + data.data.message + '</span>';
                            } else {
                                let errorMsg = data.data.message || 'Ismeretlen hiba';
                                if (data.data.error) {
                                    errorMsg += '<br><small style="color: #888;">Hiba: ' + data.data.error + '</small>';
                                }
                                testResult.innerHTML = '<span style="color: #ef4444;">❌ ' + errorMsg + '</span>';
                            }
                        })
                        .catch(error => {
                            testBtn.disabled = false;
                            testBtn.innerHTML = '📧 <?php echo esc_html(PPV_Lang::t('send_test') ?? 'Teszt küldés'); ?>';
                            testResult.innerHTML = '<span style="color: #ef4444;">❌ Hálózati hiba: ' + error.message + '</span>';
                        });
                    });
                }

                // WhatsApp toggle
                const whatsappToggle = document.querySelector('input[name="whatsapp_enabled"]');
                const whatsappSettings = document.getElementById('whatsapp-settings');
                if (whatsappToggle && whatsappSettings) {
                    whatsappToggle.addEventListener('change', function() {
                        whatsappSettings.style.opacity = this.checked ? '1' : '0.5';
                        whatsappSettings.style.pointerEvents = this.checked ? 'auto' : 'none';
                    });
                }

                // WhatsApp Verify Connection Button
                const waVerifyBtn = document.getElementById('whatsapp-verify-btn');
                const waVerifyResult = document.getElementById('whatsapp-verify-result');
                if (waVerifyBtn && waVerifyResult) {
                    waVerifyBtn.addEventListener('click', function() {
                        waVerifyBtn.disabled = true;
                        waVerifyBtn.innerHTML = '⏳ Prüfe...';
                        waVerifyResult.style.display = 'inline-block';
                        waVerifyResult.style.background = 'rgba(255,255,255,0.1)';
                        waVerifyResult.innerHTML = '<span style="color: #888;">Verbindung wird geprüft...</span>';

                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=ppv_whatsapp_verify_connection&nonce=' + window.ppvWhatsAppNonce + '&store_id=' + window.ppvWhatsAppStoreId
                        })
                        .then(response => response.json())
                        .then(data => {
                            waVerifyBtn.disabled = false;
                            waVerifyBtn.innerHTML = '✓ Verbindung testen';
                            if (data.success) {
                                waVerifyResult.style.background = 'rgba(34, 197, 94, 0.2)';
                                waVerifyResult.innerHTML = '<span style="color: #22c55e;">✅ ' + data.data.message + '</span>';
                            } else {
                                waVerifyResult.style.background = 'rgba(239, 68, 68, 0.2)';
                                waVerifyResult.innerHTML = '<span style="color: #ef4444;">❌ ' + (data.data.message || 'Fehler') + '</span>';
                            }
                        })
                        .catch(error => {
                            waVerifyBtn.disabled = false;
                            waVerifyBtn.innerHTML = '✓ Verbindung testen';
                            waVerifyResult.style.background = 'rgba(239, 68, 68, 0.2)';
                            waVerifyResult.innerHTML = '<span style="color: #ef4444;">❌ Netzwerkfehler</span>';
                        });
                    });
                }

                // WhatsApp Test Message Button
                const waTestBtn = document.getElementById('whatsapp-test-btn');
                const waTestPhone = document.getElementById('whatsapp-test-phone');
                const waTestResult = document.getElementById('whatsapp-test-result');
                if (waTestBtn && waTestPhone && waTestResult) {
                    waTestBtn.addEventListener('click', function() {
                        const phone = waTestPhone.value.trim();
                        if (!phone) {
                            waTestResult.style.display = 'block';
                            waTestResult.innerHTML = '<span style="color: #ef4444;">⚠️ Bitte Telefonnummer eingeben!</span>';
                            return;
                        }

                        waTestBtn.disabled = true;
                        waTestBtn.innerHTML = '⏳ Sende...';
                        waTestResult.style.display = 'block';
                        waTestResult.innerHTML = '<span style="color: #888;">Nachricht wird gesendet...</span>';

                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=ppv_whatsapp_send_test&nonce=' + window.ppvWhatsAppNonce + '&store_id=' + window.ppvWhatsAppStoreId + '&phone=' + encodeURIComponent(phone)
                        })
                        .then(response => response.json())
                        .then(data => {
                            waTestBtn.disabled = false;
                            waTestBtn.innerHTML = '📱 Test senden';
                            if (data.success) {
                                waTestResult.innerHTML = '<span style="color: #22c55e;">✅ ' + data.data.message + '</span>';
                            } else {
                                waTestResult.innerHTML = '<span style="color: #ef4444;">❌ ' + (data.data.message || 'Fehler') + '</span>';
                            }
                        })
                        .catch(error => {
                            waTestBtn.disabled = false;
                            waTestBtn.innerHTML = '📱 Test senden';
                            waTestResult.innerHTML = '<span style="color: #ef4444;">❌ Netzwerkfehler</span>';
                        });
                    });
                }

                // Logo delete button handler
                const deleteLogoBtn = document.getElementById('ppv-delete-logo-btn');
                const deleteLogoInput = document.getElementById('ppv-delete-logo');
                const logoPreview = document.getElementById('ppv-logo-preview');
                if (deleteLogoBtn && deleteLogoInput && logoPreview) {
                    deleteLogoBtn.addEventListener('click', function() {
                        if (confirm('Möchten Sie das Logo wirklich löschen?')) {
                            deleteLogoInput.value = '1';
                            logoPreview.innerHTML = '<span style="color: #64748b; font-size: 13px;">Logo wird beim Speichern gelöscht</span>';
                        }
                    });
                }
            });
            </script>
            <?php
            return ob_get_clean();
        }

        private static function render_tab_settings($store) {
            ob_start();
            ?>
            <div class="ppv-tab-content" id="tab-settings">
                <h2 data-i18n="profile_settings"><?php echo esc_html(PPV_Lang::t('profile_settings')); ?></h2>

                <h3 style="margin-top: 0;" data-i18n="activation_section"><?php echo esc_html(PPV_Lang::t('activation_section')); ?></h3>

                <div class="ppv-checkbox-group">
                    <label class="ppv-checkbox">
                        <input type="checkbox" name="active" value="1" <?php checked($store->active ?? 1, 1); ?>>
                        <strong data-i18n="store_active"><?php echo esc_html(PPV_Lang::t('store_active')); ?></strong>
                        <small data-i18n="store_active_help"><?php echo esc_html(PPV_Lang::t('store_active_help')); ?></small>
                    </label>
                </div>

                <div class="ppv-checkbox-group">
                    <label class="ppv-checkbox">
                        <input type="checkbox" name="visible" value="1" <?php checked($store->visible ?? 1, 1); ?>>
                        <strong data-i18n="store_visible"><?php echo esc_html(PPV_Lang::t('store_visible')); ?></strong>
                        <small data-i18n="store_visible_help"><?php echo esc_html(PPV_Lang::t('store_visible_help')); ?></small>
                    </label>
                </div>

                <hr>

                <h3 data-i18n="maintenance_section"><?php echo esc_html(PPV_Lang::t('maintenance_section')); ?></h3>

                <div class="ppv-checkbox-group">
                    <label class="ppv-checkbox">
                        <input type="checkbox" name="maintenance_mode" value="1" <?php checked($store->maintenance_mode ?? 0, 1); ?>>
                        <strong data-i18n="maintenance_mode"><?php echo esc_html(PPV_Lang::t('maintenance_mode')); ?></strong>
                        <small data-i18n="maintenance_mode_help"><?php echo esc_html(PPV_Lang::t('maintenance_mode_help')); ?></small>
                    </label>
                </div>

                <div class="ppv-form-group" style="margin-top: 12px;">
                    <label data-i18n="maintenance_message"><?php echo esc_html(PPV_Lang::t('maintenance_message')); ?></label>
                    <p class="ppv-help" data-i18n="maintenance_message_help" style="margin-bottom: 8px;">
                        <?php echo esc_html(PPV_Lang::t('maintenance_message_help')); ?>
                    </p>
                    <textarea name="maintenance_message" placeholder="<?php echo esc_attr(PPV_Lang::t('maintenance_message_placeholder')); ?>" style="min-height: 100px;">
<?php echo esc_textarea($store->maintenance_message ?? ''); ?>
                    </textarea>
                </div>

                <hr>

                <h3 data-i18n="opening_hours_section"><?php echo esc_html(PPV_Lang::t('opening_hours_section')); ?></h3>

                <div class="ppv-checkbox-group">
                    <label class="ppv-checkbox">
                        <input type="checkbox" name="enforce_opening_hours" value="1" <?php checked($store->enforce_opening_hours ?? 1, 1); ?>>
                        <strong data-i18n="enforce_opening_hours"><?php echo esc_html(PPV_Lang::t('enforce_opening_hours')); ?></strong>
                        <small data-i18n="enforce_opening_hours_help"><?php echo esc_html(PPV_Lang::t('enforce_opening_hours_help')); ?></small>
                    </label>
                </div>

                <hr>

                <h3 data-i18n="timezone_section"><?php echo esc_html(PPV_Lang::t('timezone_section')); ?></h3>

                <div class="ppv-form-group">
                    <label data-i18n="timezone"><?php echo esc_html(PPV_Lang::t('timezone')); ?></label>
                    <p class="ppv-help" data-i18n="timezone_help" style="margin-bottom: 8px;">
                        <?php echo esc_html(PPV_Lang::t('timezone_help')); ?>
                    </p>
                    <select name="timezone" style="margin-bottom: 16px;">
                        <option value="Europe/Berlin" <?php selected($store->timezone ?? '', 'Europe/Berlin'); ?> data-i18n="timezone_berlin">
                            <?php echo esc_html(PPV_Lang::t('timezone_berlin')); ?>
                        </option>
                        <option value="Europe/Budapest" <?php selected($store->timezone ?? '', 'Europe/Budapest'); ?> data-i18n="timezone_budapest">
                            <?php echo esc_html(PPV_Lang::t('timezone_budapest')); ?>
                        </option>
                        <option value="Europe/Bucharest" <?php selected($store->timezone ?? '', 'Europe/Bucharest'); ?> data-i18n="timezone_bucharest">
                            <?php echo esc_html(PPV_Lang::t('timezone_bucharest')); ?>
                        </option>
                    </select>
                </div>

                <hr>

                <?php
                // Only show onboarding reset for trial subscription handlers
                if (($store->subscription_status ?? '') === 'trial'):
                ?>
                <!-- ============================================================
                     ONBOARDING RESET (TRIAL ONLY)
                     ============================================================ -->
                <h3 data-i18n="onboarding_section"><?php echo esc_html(PPV_Lang::t('onboarding_section')); ?></h3>

                <div class="ppv-form-group">
                    <p class="ppv-help" data-i18n="onboarding_reset_help" style="margin-bottom: 12px;">
                        <?php echo esc_html(PPV_Lang::t('onboarding_reset_help')); ?>
                    </p>
                    <button type="button" id="ppv-reset-onboarding-btn" class="ppv-btn ppv-btn-secondary" style="width: 100%;">
                        🔄 <span data-i18n="onboarding_reset_btn"><?php echo esc_html(PPV_Lang::t('onboarding_reset_btn')); ?></span>
                    </button>
                </div>

                <hr>
                <?php endif; ?>

                <!-- ============================================================
                     ACCOUNT SETTINGS - EMAIL & PASSWORD CHANGE
                     ============================================================ -->
                <h3>📧 <?php echo esc_html(PPV_Lang::t('account_settings', 'Fiók beállítások')); ?></h3>

                <!-- Current Email Display -->
                <?php
                $current_user = wp_get_current_user();
                $current_email = $current_user->user_email ?? '';
                ?>

                <!-- Email Change Section -->
                <div class="ppv-form-group ppv-account-section" style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                    <label style="font-weight: bold; margin-bottom: 10px; display: block;">
                        📧 <?php echo esc_html(PPV_Lang::t('email_change', 'E-mail cím módosítása')); ?>
                    </label>
                    <p class="ppv-help" style="margin-bottom: 12px; color: #888;">
                        <?php echo esc_html(PPV_Lang::t('email_change_help', 'Jelenlegi e-mail cím:')); ?> <strong><?php echo esc_html($current_email); ?></strong>
                    </p>
                    <input type="email" id="ppv-new-email" placeholder="<?php echo esc_attr(PPV_Lang::t('new_email_placeholder', 'Új e-mail cím')); ?>" style="margin-bottom: 10px;">
                    <input type="email" id="ppv-confirm-email" placeholder="<?php echo esc_attr(PPV_Lang::t('confirm_email_placeholder', 'Új e-mail cím megerősítése')); ?>" style="margin-bottom: 10px;">
                    <button type="button" id="ppv-change-email-btn" class="ppv-btn ppv-btn-secondary" style="width: 100%;">
                        📧 <?php echo esc_html(PPV_Lang::t('change_email_btn', 'E-mail cím módosítása')); ?>
                    </button>
                </div>

                <!-- Password Change Section -->
                <div class="ppv-form-group ppv-account-section" style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 12px;">
                    <label style="font-weight: bold; margin-bottom: 10px; display: block;">
                        🔐 <?php echo esc_html(PPV_Lang::t('password_change', 'Jelszó módosítása')); ?>
                    </label>
                    <p class="ppv-help" style="margin-bottom: 12px; color: #888;">
                        <?php echo esc_html(PPV_Lang::t('password_change_help', 'Adja meg a jelenlegi és az új jelszót.')); ?>
                    </p>
                    <input type="password" id="ppv-current-password" placeholder="<?php echo esc_attr(PPV_Lang::t('current_password_placeholder', 'Jelenlegi jelszó')); ?>" style="margin-bottom: 10px;">
                    <input type="password" id="ppv-new-password" placeholder="<?php echo esc_attr(PPV_Lang::t('new_password_placeholder', 'Új jelszó')); ?>" style="margin-bottom: 10px;">
                    <input type="password" id="ppv-confirm-password" placeholder="<?php echo esc_attr(PPV_Lang::t('confirm_password_placeholder', 'Új jelszó megerősítése')); ?>" style="margin-bottom: 10px;">
                    <button type="button" id="ppv-change-password-btn" class="ppv-btn ppv-btn-secondary" style="width: 100%;">
                        🔐 <?php echo esc_html(PPV_Lang::t('change_password_btn', 'Jelszó módosítása')); ?>
                    </button>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }


        private static function get_current_store() {
            global $wpdb;

            // ✅ FIX: Flush WordPress object cache to ensure fresh data
            wp_cache_flush();
            $wpdb->flush();

            // 🏪 FILIALE SUPPORT: Use session-aware store ID
            $store_id = self::get_store_id();

            ppv_log("📖 [DEBUG] get_current_store() - store_id: {$store_id}");
            ppv_log("📖 [DEBUG] Session ppv_store_id: " . ($_SESSION['ppv_store_id'] ?? 'NULL'));
            ppv_log("📖 [DEBUG] Session ppv_current_filiale_id: " . ($_SESSION['ppv_current_filiale_id'] ?? 'NULL'));

            if ($store_id) {
                // SQL_NO_CACHE ensures MySQL doesn't return cached results
                $store = $wpdb->get_row($wpdb->prepare("SELECT SQL_NO_CACHE * FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1", $store_id));
                ppv_log("📖 [DEBUG] Loaded store name: " . ($store->name ?? 'NULL'));
                return $store;
            }

            // Fallback: GET parameter (admin use)
            if (!empty($_GET['store_id'])) {
                return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1", intval($_GET['store_id'])));
            }

            if (!empty($_COOKIE['ppv_pos_token'])) {
                return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_stores WHERE pos_token = %s LIMIT 1", sanitize_text_field($_COOKIE['ppv_pos_token'])));
            }

            if (is_user_logged_in()) {
                return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1", get_current_user_id()));
            }

            return null;
        }

public static function ajax_save_profile() {
    global $wpdb; // ✅ FIX: Declare $wpdb at the start of the function

    if (!isset($_POST[self::NONCE_NAME])) {
        wp_send_json_error(['msg' => 'Nonce missing']);
    }

    if (!wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
        wp_send_json_error(['msg' => 'Invalid nonce']);
    }

    self::ensure_session();
    $auth = self::check_auth();

    if (!$auth['valid']) {
        wp_send_json_error(['msg' => PPV_Lang::t('error')]);
    }

    // 🏪 FILIALE SUPPORT: ALWAYS use session-aware store ID, ignore POST parameter
    $store_id = self::get_store_id();

    if ($auth['type'] === 'ppv_stores' && $store_id != $auth['store_id']) {
        wp_send_json_error(['msg' => 'Unauthorized']);
    }

    $upload_dir = wp_upload_dir();
    $gallery_files = [];

    // ✅ FIX: Get existing store data to preserve logo/gallery if not uploading new
    $existing_store = $wpdb->get_row($wpdb->prepare(
        "SELECT logo, gallery FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
        $store_id
    ));

    // Logo upload or delete
    if (!empty($_POST['delete_logo']) && $_POST['delete_logo'] === '1') {
        // 🗑️ Delete logo
        $_POST['logo'] = '';
    } elseif (!empty($_FILES['logo']['name'])) {
        $tmp_file = $_FILES['logo']['tmp_name'];
        $filename = basename($_FILES['logo']['name']);
        $new_file = $upload_dir['path'] . '/' . $filename;

        if (move_uploaded_file($tmp_file, $new_file)) {
            $_POST['logo'] = $upload_dir['url'] . '/' . $filename;
        }
    } else {
        // ✅ FIX: Preserve existing logo if no new upload
        $_POST['logo'] = $existing_store->logo ?? '';
    }

    // Gallery upload
    if (!empty($_FILES['gallery']['name'][0])) {
        // ✅ FIX: Get existing gallery to merge with new uploads
        $existing_gallery = json_decode($existing_store->gallery ?? '[]', true) ?: [];

        foreach ($_FILES['gallery']['name'] as $key => $filename) {
            if ($_FILES['gallery']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_file = $_FILES['gallery']['tmp_name'][$key];
                $new_file = $upload_dir['path'] . '/' . basename($filename);

                if (move_uploaded_file($tmp_file, $new_file)) {
                    $gallery_files[] = $upload_dir['url'] . '/' . basename($filename);
                }
            }
        }

        // ✅ FIX: Merge new uploads with existing gallery (append new images)
        $gallery_files = array_merge($existing_gallery, $gallery_files);
    }


    // ============================================================
    // ✅ OPENING HOURS - FELDOLGOZÁS
    // ============================================================
    $opening_hours = [];
    $days = ['mo', 'di', 'mi', 'do', 'fr', 'sa', 'so'];
    
    foreach ($days as $day) {
        $von = sanitize_text_field($_POST['hours'][$day]['von'] ?? '');
        $bis = sanitize_text_field($_POST['hours'][$day]['bis'] ?? '');
        $closed = !empty($_POST['hours'][$day]['closed']) ? 1 : 0;
        
        $opening_hours[$day] = [
            'von' => $von,
            'bis' => $bis,
            'closed' => $closed
        ];
    }
    // ============================================================
    // ✅ ÖSSZES MEZŐ - TAX_ID ÉS IS_TAXABLE BENNE!
    // ============================================================
    $update_data = [
        'name' => sanitize_text_field($_POST['store_name'] ?? ''),
        'country' => sanitize_text_field($_POST['country'] ?? 'DE'),

        'latitude' => floatval($_POST['latitude'] ?? 0),
        'longitude' => floatval($_POST['longitude'] ?? 0),
        
        'slogan' => mb_substr(sanitize_text_field($_POST['slogan'] ?? ''), 0, 50),
        'category' => sanitize_text_field($_POST['category'] ?? ''),
        'address' => sanitize_text_field($_POST['address'] ?? ''),
        'plz' => sanitize_text_field($_POST['plz'] ?? ''),
        'city' => sanitize_text_field($_POST['city'] ?? ''),

        // ✅ COMPANY FIELDS (were missing!)
        'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
        'contact_person' => sanitize_text_field($_POST['contact_person'] ?? ''),

        // ✅ ÚJ MEZŐK:
        'tax_id' => sanitize_text_field($_POST['tax_id'] ?? ''),
        'is_taxable' => !empty($_POST['is_taxable']) ? 1 : 0,
        
        'phone' => sanitize_text_field($_POST['phone'] ?? ''),
        'public_email' => sanitize_email($_POST['public_email'] ?? ''),
        'website' => esc_url_raw($_POST['website'] ?? ''),
        'whatsapp' => sanitize_text_field($_POST['whatsapp'] ?? ''),
        'facebook' => esc_url_raw($_POST['facebook'] ?? ''),
        'instagram' => esc_url_raw($_POST['instagram'] ?? ''),
        'tiktok' => esc_url_raw($_POST['tiktok'] ?? ''),
        'description' => wp_kses_post($_POST['description'] ?? ''),
        'active' => !empty($_POST['active']) ? 1 : 0,
        'visible' => !empty($_POST['visible']) ? 1 : 0,
        'maintenance_mode' => !empty($_POST['maintenance_mode']) ? 1 : 0,
        'maintenance_message' => wp_kses_post($_POST['maintenance_message'] ?? ''),
        'enforce_opening_hours' => !empty($_POST['enforce_opening_hours']) ? 1 : 0,
        'timezone' => sanitize_text_field($_POST['timezone'] ?? 'Europe/Berlin'),
        'updated_at' => current_time('mysql'),
        'logo' => sanitize_text_field($_POST['logo'] ?? ''),
// ✅ FIX: Preserve existing gallery if no new uploads
        'gallery' => !empty($gallery_files) ? json_encode($gallery_files) : ($existing_store->gallery ?? ''),
    'opening_hours' => json_encode($opening_hours),

        // ============================================================
        // ✅ MARKETING AUTOMATION FIELDS
        // ============================================================
        // Google Review
        'google_review_enabled' => !empty($_POST['google_review_enabled']) ? 1 : 0,
        'google_review_url' => esc_url_raw($_POST['google_review_url'] ?? ''),
        'google_review_threshold' => intval($_POST['google_review_threshold'] ?? 100),
        'google_review_frequency' => sanitize_text_field($_POST['google_review_frequency'] ?? 'once'),

        // Birthday Bonus
        'birthday_bonus_enabled' => !empty($_POST['birthday_bonus_enabled']) ? 1 : 0,
        'birthday_bonus_type' => sanitize_text_field($_POST['birthday_bonus_type'] ?? 'double_points'),
        'birthday_bonus_value' => intval($_POST['birthday_bonus_value'] ?? 0),
        'birthday_bonus_message' => sanitize_text_field($_POST['birthday_bonus_message'] ?? ''),

        // Comeback Campaign
        'comeback_enabled' => !empty($_POST['comeback_enabled']) ? 1 : 0,
        'comeback_days' => intval($_POST['comeback_days'] ?? 30),
        'comeback_bonus_type' => sanitize_text_field($_POST['comeback_bonus_type'] ?? 'double_points'),
        'comeback_bonus_value' => intval($_POST['comeback_bonus_value'] ?? 50),
        'comeback_message' => sanitize_text_field($_POST['comeback_message'] ?? ''),

        // ============================================================
        // ✅ WHATSAPP CLOUD API - Only enable/disable toggle
        // API settings are managed in /admin/whatsapp
        // ============================================================
        'whatsapp_enabled' => !empty($_POST['whatsapp_enabled']) ? 1 : 0,

        // ============================================================
        // ✅ REFERRAL PROGRAM FIELDS
        // ============================================================
        'referral_enabled' => !empty($_POST['referral_enabled']) ? 1 : 0,
        'referral_grace_days' => max(7, min(180, intval($_POST['referral_grace_days'] ?? 60))),
        'referral_reward_type' => in_array($_POST['referral_reward_type'] ?? '', ['points', 'euro', 'gift']) ? $_POST['referral_reward_type'] : 'points',
        'referral_reward_value' => intval($_POST['referral_reward_value'] ?? $_POST['referral_reward_value_euro'] ?? 50),
        'referral_reward_gift' => sanitize_text_field($_POST['referral_reward_gift'] ?? ''),
        'referral_manual_approval' => !empty($_POST['referral_manual_approval']) ? 1 : 0,
];

// ✅ Format specifierek az összes mezőhöz
$format_specs = [
    '%s',  // name
    '%s',  // country
    '%f',  // latitude
    '%f',  // longitude
    '%s',  // slogan
    '%s',  // category
    '%s',  // address
    '%s',  // plz
    '%s',  // city
    '%s',  // company_name
    '%s',  // contact_person
    '%s',  // tax_id
    '%d',  // is_taxable
    '%s',  // phone
    '%s',  // public_email
    '%s',  // website
    '%s',  // whatsapp
    '%s',  // facebook
    '%s',  // instagram
    '%s',  // tiktok
    '%s',  // description
    '%d',  // active
    '%d',  // visible
    '%d',  // maintenance_mode
    '%s',  // maintenance_message
    '%d',  // enforce_opening_hours
    '%s',  // timezone
    '%s',  // updated_at
    '%s',  // logo
    '%s',  // gallery
    '%s',  // opening_hours
    // Marketing Automation
    '%d',  // google_review_enabled
    '%s',  // google_review_url
    '%d',  // google_review_threshold
    '%s',  // google_review_frequency
    '%d',  // birthday_bonus_enabled
    '%s',  // birthday_bonus_type
    '%d',  // birthday_bonus_value
    '%s',  // birthday_bonus_message
    '%d',  // comeback_enabled
    '%d',  // comeback_days
    '%s',  // comeback_bonus_type
    '%d',  // comeback_bonus_value
    '%s',  // comeback_message
    // WhatsApp Cloud API - only enable toggle (settings managed in /admin/whatsapp)
    '%d',  // whatsapp_enabled
    // Referral Program
    '%d',  // referral_enabled
    '%d',  // referral_grace_days
    '%s',  // referral_reward_type
    '%d',  // referral_reward_value
    '%s',  // referral_reward_gift
    '%d',  // referral_manual_approval
];

ppv_log("💾 [DEBUG] Saving store ID: {$store_id}");
ppv_log("💾 [DEBUG] Country: " . ($update_data['country'] ?? 'NULL'));
ppv_log("💾 [DEBUG] Store Name: " . ($update_data['name'] ?? 'NULL'));
ppv_log("💾 [DEBUG] Session store_id: " . ($_SESSION['ppv_store_id'] ?? 'NULL'));
ppv_log("💾 [DEBUG] Session filiale_id: " . ($_SESSION['ppv_current_filiale_id'] ?? 'NULL'));

$result = $wpdb->update(
    $wpdb->prefix . 'ppv_stores',
    $update_data,
    ['id' => $store_id],
    $format_specs,
    ['%d']
);

ppv_log("💾 [DEBUG] Update result: " . ($result !== false ? 'OK (rows: ' . $result . ')' : 'FAILED'));
ppv_log("💾 [DEBUG] Last SQL error: " . $wpdb->last_error);

    if ($result !== false) {
        // ✅ FIX: Return updated store data so JS can refresh form fields without reload
        $updated_store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));

        wp_send_json_success([
            'msg' => PPV_Lang::t('profile_saved_success'),
            'store_id' => $store_id,
            'store' => $updated_store  // ✅ This enables updateFormFields() in JS
        ]);
    } else {
        wp_send_json_error(['msg' => PPV_Lang::t('profile_save_error')]);
    }
}

        public static function ajax_auto_save_profile() {
            if (!isset($_POST[self::NONCE_NAME])) {
                wp_send_json_error(['msg' => 'Nonce missing']);
            }

            if (!wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
                wp_send_json_error(['msg' => 'Invalid nonce']);
            }

            self::ensure_session();
            $auth = self::check_auth();

            if (!$auth['valid']) {
                wp_send_json_error(['msg' => 'Not authenticated']);
            }

            $draft_data = $_POST['draft'] ?? [];

            // 🏪 FILIALE SUPPORT: ALWAYS use session-aware store ID, ignore POST parameter
            $store_id = self::get_store_id();

            if ($auth['type'] === 'ppv_stores' && $store_id != $auth['store_id']) {
                wp_send_json_error(['msg' => 'Unauthorized']);
            }

            global $wpdb;
            $wpdb->update($wpdb->prefix . 'ppv_stores', ['draft_data' => json_encode($draft_data)], ['id' => $store_id], ['%s'], ['%d']);

            wp_send_json_success(['msg' => 'Draft saved', 'timestamp' => current_time('mysql')]);
        }
        
        public static function ajax_delete_gallery_image() {
            if (!isset($_POST[self::NONCE_NAME])) {
                wp_send_json_error(['msg' => 'Nonce missing']);
            }

            if (!wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
                wp_send_json_error(['msg' => 'Invalid nonce']);
            }

            self::ensure_session();
            $auth = self::check_auth();

            if (!$auth['valid']) {
                wp_send_json_error(['msg' => 'Not authenticated']);
            }

            // 🏪 FILIALE SUPPORT: ALWAYS use session-aware store ID, ignore POST parameter
            $store_id = self::get_store_id();
            $image_url = sanitize_text_field($_POST['image_url'] ?? '');

            if ($auth['type'] === 'ppv_stores' && $store_id != $auth['store_id']) {
                wp_send_json_error(['msg' => 'Unauthorized']);
            }

            global $wpdb;
            $store = $wpdb->get_row($wpdb->prepare("SELECT gallery FROM {$wpdb->prefix}ppv_stores WHERE id = %d", $store_id));

            if (!$store) {
                wp_send_json_error(['msg' => 'Store not found']);
            }

            $gallery = json_decode($store->gallery, true);
            if (!is_array($gallery)) {
                wp_send_json_error(['msg' => 'Gallery error']);
            }

            // Remove the image
            $gallery = array_filter($gallery, function($url) use ($image_url) {
                return $url !== $image_url;
            });

            // Reindex array
            $gallery = array_values($gallery);

            // Update database
            $result = $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                ['gallery' => json_encode($gallery)],
                ['id' => $store_id],
                ['%s'],
                ['%d']
            );

            if ($result !== false) {
                wp_send_json_success(['msg' => 'Törölt']);
            } else {
                wp_send_json_error(['msg' => 'Hiba']);
            }
        }

        public static function ajax_delete_media() {
        // Nonce ellenőrzés
            if (!isset($_POST[self::NONCE_NAME])) {
                wp_send_json_error(['msg' => 'Nonce missing']);
                return;
            }

            if (!wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
                wp_send_json_error(['msg' => 'Invalid nonce']);
                return;
            }
            self::ensure_session();
            $auth = self::check_auth();

            if (!$auth['valid']) {
                wp_send_json_error(['msg' => 'Unauthorized']);
            }

            $media_id = intval($_POST['media_id'] ?? 0);

            if (wp_delete_attachment($media_id, true)) {
                wp_send_json_success(['msg' => 'Deleted']);
            } else {
                wp_send_json_error(['msg' => 'Error']);
            }
        }

        public static function handle_form_submit() {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST[self::NONCE_NAME])) {
                return;
            }

            if (!wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
                wp_die(esc_html(PPV_Lang::t('error')));
            }
        }
/**
 * 🗺️ GEOCODE ADDRESS - FIX (Romania/Hungary support)
 * Egyszerűen másold be ezt a függvényt a PHP fájlba
 */

public static function ajax_geocode_address() {
    if (!isset($_POST[self::NONCE_NAME])) {
        wp_send_json_error(['msg' => 'Nonce missing']);
        return;
    }

    if (!wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
        wp_send_json_error(['msg' => 'Invalid nonce']);
        return;
    }

    self::ensure_session();

    $address = sanitize_text_field($_POST['address'] ?? '');
    $plz = sanitize_text_field($_POST['plz'] ?? '');
    $city = sanitize_text_field($_POST['city'] ?? '');
    $country = sanitize_text_field($_POST['country'] ?? 'DE');

if (empty($address) || empty($city) || empty($country)) {
    wp_send_json_error(['msg' => 'Cím, város és ország szükséges!']);
    return;
}
    // ✅ ORSZÁG NEVEI
    $country_names = [
        'DE' => 'Deutschland',
        'HU' => 'Hungary',
        'RO' => 'Romania'
    ];
    $country_name = $country_names[$country] ?? 'Germany';

    // ✅ JOBB FORMÁTUM (vessző, ország)
    $full_address = "{$address}, {$plz} {$city}, {$country_name}";
    
    ppv_log("🔍 [PPV_GEOCODE] Keresés: {$full_address}");

    $google_api_key = defined('PPV_GOOGLE_MAPS_KEY') ? PPV_GOOGLE_MAPS_KEY : '';


// ============================================================
// 1️⃣ GOOGLE MAPS GEOCODING (Erőteljes keresés)
// ============================================================
if ($google_api_key) {
    ppv_log("🔍 [PPV_GEOCODE] Google Maps API keresés iniciálva");
    
    // Több keresési variáns
    $search_variants = [
        $full_address, // Teljes: "Str. Noua 742, 447080 Capleni, Romania"
        "{$address}, {$plz} {$city}, {$country_name}",
        "{$address}, {$city}, {$country_name}",
        str_replace(['Str.', 'str.'], 'Strada', $address) . ", {$plz} {$city}, {$country_name}",
    ];

    foreach ($search_variants as $search_query) {
        ppv_log("  → Variáns: {$search_query}");
        
        $url = 'https://maps.googleapis.com/maps/api/geocode/json';
        $response = wp_remote_get(
            add_query_arg([
                'address' => $search_query,
                'components' => 'country:' . strtolower($country),
                'key' => $google_api_key,
                'language' => 'en'
            ], $url),
            ['timeout' => 10]
        );

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            ppv_log("  ✓ Status: " . ($data['status'] ?? 'unknown'));

            if ($data['status'] === 'OK' && !empty($data['results'])) {
                $first = $data['results'][0];
                $lat = floatval($first['geometry']['location']['lat'] ?? 0);
                $lon = floatval($first['geometry']['location']['lng'] ?? 0);

                $detected_country = 'DE';
                if (!empty($first['address_components'])) {
                    foreach ($first['address_components'] as $component) {
                        if (in_array('country', $component['types'], true)) {
                            $detected_country = strtoupper($component['short_name']);
                            break;
                        }
                    }
                }

                ppv_log("✅ [PPV_GEOCODE] Google Maps MEGTALÁLTA: {$lat}, {$lon} ({$detected_country})");

                wp_send_json_success([
                    'lat' => round($lat, 4),
                    'lon' => round($lon, 4),
                    'country' => $detected_country,
                    'display_name' => $first['formatted_address'] ?? $search_query,
                    'source' => 'google_maps'
                ]);
                return;
            }
        }
    }
    
ppv_log("⚠️ [PPV_GEOCODE] Google Maps utca: NINCS TALÁLAT - fallback városra");

// FALLBACK: Csak város keresése
$city_search = "{$city}, {$country_name}";
ppv_log("🔍 [PPV_GEOCODE] Fallback keresés: {$city_search}");

$url = 'https://maps.googleapis.com/maps/api/geocode/json';
$response = wp_remote_get(
    add_query_arg([
        'address' => $city_search,
        'key' => $google_api_key,
        'language' => 'en'
    ], $url),
    ['timeout' => 10]
);

if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($data['status'] === 'OK' && !empty($data['results'])) {
        $first = $data['results'][0];
        $lat = floatval($first['geometry']['location']['lat'] ?? 0);
        $lon = floatval($first['geometry']['location']['lng'] ?? 0);

        $detected_country = 'DE';
        if (!empty($first['address_components'])) {
            foreach ($first['address_components'] as $component) {
                if (in_array('country', $component['types'], true)) {
                    $detected_country = strtoupper($component['short_name']);
                    break;
                }
            }
        }

        ppv_log("✅ [PPV_GEOCODE] Város MEGTALÁLVA: {$lat}, {$lon}");

        // 🔴 FONTOS: flag hogy manuálisra kell váltani
        wp_send_json_success([
            'lat' => round($lat, 4),
            'lon' => round($lon, 4),
            'country' => $detected_country,
            'display_name' => $first['formatted_address'] ?? $city_search,
            'source' => 'google_maps_city',
            'open_manual_map' => true  // ← FONTOS!
        ]);
        return;
    }
}

ppv_log("❌ [PPV_GEOCODE] Város sem találva!");
}


// ============================================================
// 2️⃣ OPENSTREETMAP (Nominatim) - FALLBACK (Multistep search)
// ============================================================

$search_variants = [
    // 1. Teljes: "Str. Noua 742, 447080 Capleni, Romania"
    "{$address}, {$plz} {$city}, {$country_name}",
    
    // 2. "Strada Noua" helyett (román forma)
    str_replace(['Str.', 'str.'], 'Strada', "{$address}, {$plz} {$city}, {$country_name}"),
    
    // 3. Csak házszám nélkül: "Str. Noua, 447080 Capleni, Romania"
    "{$address}, {$plz} {$city}, {$country_name}",
    
    // 4. Vezetéknév nélkül: "Noua 742, Capleni, Romania"
    preg_replace('/^Str\.\s*/', '', $address) . ", {$city}, {$country_name}",
];

foreach ($search_variants as $idx => $search_query) {
    ppv_log("🔍 [PPV_GEOCODE] Keresési variáns #" . ($idx + 1) . ": {$search_query}");
    
    $url = 'https://nominatim.openstreetmap.org/search';
    $response = wp_remote_get(
        add_query_arg([
            'format' => 'json',
            'q' => $search_query,
            'limit' => 10,
            'addressdetails' => 1,
            'bounded' => 1,
            'viewbox' => '20.2,43.6,29.8,48.3' // Romania bounding box
        ], $url),
        [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'PunktePass (https://punktepass.de)',
                'Accept-Language' => 'de,en;q=0.9'
            ]
        ]
    );

    if (!is_wp_error($response)) {
        $results = json_decode(wp_remote_retrieve_body($response), true);
        
        ppv_log("📍 [PPV_GEOCODE] Variáns #" . ($idx + 1) . " találatok: " . count($results ?? []) . "");
        
        if (!empty($results)) {
            // Legjobb találat: házszámos street vagy épület
            $best = null;
            
            foreach ($results as $result) {
                $type = $result['addresstype'] ?? '';
                $importance = floatval($result['importance'] ?? 0);
                
                // Prioritás: house > building > street
                if ($type === 'house' || $type === 'building') {
                    if (!$best || $importance > floatval($best['importance'] ?? 0)) {
                        $best = $result;
                    }
                }
            }
            
            // Ha nincs house/building, próbáljunk street-et
            if (!$best) {
                foreach ($results as $result) {
                    if ($result['addresstype'] === 'street') {
                        $best = $result;
                        break;
                    }
                }
            }
            
            // Ha még sincs, első találat
            if (!$best) {
                $best = $results[0];
            }

            if ($best) {
                $lat = floatval($best['lat']);
                $lon = floatval($best['lon']);
                $detected_country = 'DE';

                if (!empty($best['address'])) {
                    $addr = $best['address'];
                    if (!empty($addr['country_code'])) {
                        $detected_country = strtoupper($addr['country_code']);
                    }
                }

                ppv_log("✅ [PPV_GEOCODE] Nominatim MEGTALÁLVA (variáns #" . ($idx + 1) . "): {$lat}, {$lon} ({$detected_country})");
                ppv_log("   Display: " . ($best['display_name'] ?? 'N/A'));

                wp_send_json_success([
                    'lat' => round($lat, 4),
                    'lon' => round($lon, 4),
                    'country' => $detected_country,
                    'display_name' => $best['display_name'] ?? $search_query,
                    'source' => 'nominatim_variant_' . ($idx + 1)
                ]);
                return;
            }
        }
    }
    
    // Kis késleltetés az API-hoz
    usleep(500000);
}

ppv_log("❌ [PPV_GEOCODE] Egyik variáns sem találta meg: {$full_address}");
wp_send_json_error(['msg' => 'A cím nem található! Próbáld meg máshogyan írni (pl. teljes utcanévvel).']);
}

        /**
         * ============================================================
         * 🔒 RESET TRUSTED DEVICE FINGERPRINT
         * ============================================================
         */
        public static function ajax_reset_trusted_device() {
            self::ensure_session();

            $auth = self::check_auth();
            if (!$auth['valid']) {
                wp_send_json_error(['message' => 'Nincs jogosultság']);
                return;
            }

            $store_id = self::get_store_id();
            if (!$store_id) {
                wp_send_json_error(['message' => 'Store not found']);
                return;
            }

            global $wpdb;

            // Reset the trusted device fingerprint
            $result = $wpdb->update(
                "{$wpdb->prefix}ppv_stores",
                ['trusted_device_fingerprint' => null],
                ['id' => $store_id]
            );

            if ($result !== false) {
                ppv_log("[PPV_DEVICES] Trusted device reset for store #{$store_id}");
                wp_send_json_success(['message' => 'Megbízható eszköz visszaállítva']);
            } else {
                wp_send_json_error(['message' => 'Hiba történt']);
            }
        }

        /**
         * ============================================================
         * 🎁 ACTIVATE REFERRAL GRACE PERIOD
         * ============================================================
         */
        public static function ajax_activate_referral_grace_period() {
            self::ensure_session();

            $auth = self::check_auth();
            if (!$auth['valid']) {
                wp_send_json_error(['msg' => 'Nincs jogosultság']);
                return;
            }

            $store_id = self::get_store_id();
            if (!$store_id) {
                wp_send_json_error(['msg' => 'Store not found']);
                return;
            }

            global $wpdb;

            // Check if already activated
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT referral_activated_at FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
                $store_id
            ));

            if (!empty($store->referral_activated_at)) {
                wp_send_json_error(['msg' => 'Grace Period bereits aktiviert']);
                return;
            }

            // Activate grace period
            $result = $wpdb->update(
                "{$wpdb->prefix}ppv_stores",
                ['referral_activated_at' => current_time('mysql')],
                ['id' => $store_id],
                ['%s'],
                ['%d']
            );

            if ($result !== false) {
                ppv_log("[PPV_REFERRAL] Grace period started for store #{$store_id}");
                wp_send_json_success(['msg' => 'Grace Period gestartet!']);
            } else {
                wp_send_json_error(['msg' => 'Fehler beim Starten der Grace Period']);
            }
        }

        /**
         * ============================================================
         * 📧 CHANGE EMAIL
         * ============================================================
         */
        public static function ajax_change_email() {
            self::ensure_session();

            $auth = self::check_auth();
            if (!$auth['valid']) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_no_permission', 'Nincs jogosultság')]);
                return;
            }

            // Get user ID from session
            $user_id = 0;
            if (!empty($_SESSION['ppv_user_id'])) {
                $user_id = intval($_SESSION['ppv_user_id']);
            } elseif (is_user_logged_in()) {
                $user_id = get_current_user_id();
            }

            if (!$user_id) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_no_user', 'Felhasználó nem található')]);
                return;
            }

            $new_email = sanitize_email($_POST['new_email'] ?? '');
            $confirm_email = sanitize_email($_POST['confirm_email'] ?? '');

            // Validation
            if (empty($new_email)) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_email_required', 'E-mail cím megadása kötelező')]);
                return;
            }

            if (!is_email($new_email)) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_email_invalid', 'Érvénytelen e-mail cím')]);
                return;
            }

            if ($new_email !== $confirm_email) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_email_mismatch', 'Az e-mail címek nem egyeznek')]);
                return;
            }

            // Check if email already exists
            if (email_exists($new_email) && email_exists($new_email) !== $user_id) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_email_exists', 'Ez az e-mail cím már foglalt')]);
                return;
            }

            // Update user email
            $result = wp_update_user([
                'ID' => $user_id,
                'user_email' => $new_email
            ]);

            if (is_wp_error($result)) {
                wp_send_json_error(['msg' => $result->get_error_message()]);
                return;
            }

            // Also update store email if exists
            global $wpdb;
            $store_id = self::get_store_id();
            if ($store_id) {
                $wpdb->update(
                    "{$wpdb->prefix}ppv_stores",
                    ['email' => $new_email],
                    ['id' => $store_id],
                    ['%s'],
                    ['%d']
                );
                ppv_log("[PPV_ACCOUNT] Store email synced for store #{$store_id}");
            }

            // ✅ Also update ppv_users email if handler/vendor
            $ppv_user_id = $_SESSION['ppv_user_id'] ?? 0;
            if ($ppv_user_id > 0) {
                $wpdb->update(
                    "{$wpdb->prefix}ppv_users",
                    ['email' => $new_email],
                    ['id' => $ppv_user_id],
                    ['%s'],
                    ['%d']
                );
                ppv_log("[PPV_ACCOUNT] PPV user email synced for ppv_user #{$ppv_user_id}");

                // Update session email
                $_SESSION['ppv_user_email'] = $new_email;
            }

            ppv_log("[PPV_ACCOUNT] Email changed for user #{$user_id} to: {$new_email}");
            wp_send_json_success(['msg' => PPV_Lang::t('email_changed_success', 'E-mail cím sikeresen módosítva!')]);
        }

        /**
         * ============================================================
         * 🔐 CHANGE PASSWORD
         * ============================================================
         */
        public static function ajax_change_password() {
            self::ensure_session();

            $auth = self::check_auth();
            if (!$auth['valid']) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_no_permission', 'Nincs jogosultság')]);
                return;
            }

            // Get user ID from session
            $user_id = 0;
            if (!empty($_SESSION['ppv_user_id'])) {
                $user_id = intval($_SESSION['ppv_user_id']);
            } elseif (is_user_logged_in()) {
                $user_id = get_current_user_id();
            }

            if (!$user_id) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_no_user', 'Felhasználó nem található')]);
                return;
            }

            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Validation
            if (empty($current_password)) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_current_password_required', 'Jelenlegi jelszó megadása kötelező')]);
                return;
            }

            if (empty($new_password)) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_new_password_required', 'Új jelszó megadása kötelező')]);
                return;
            }

            if (strlen($new_password) < 6) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_password_too_short', 'A jelszó legalább 6 karakter legyen')]);
                return;
            }

            if ($new_password !== $confirm_password) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_password_mismatch', 'Az új jelszavak nem egyeznek')]);
                return;
            }

            // Verify current password
            $user = get_user_by('ID', $user_id);
            if (!$user || !wp_check_password($current_password, $user->user_pass, $user_id)) {
                wp_send_json_error(['msg' => PPV_Lang::t('error_current_password_wrong', 'A jelenlegi jelszó helytelen')]);
                return;
            }

            // Update password
            wp_set_password($new_password, $user_id);

            // Re-authenticate the user (wp_set_password logs them out)
            wp_set_auth_cookie($user_id, true);

            ppv_log("[PPV_ACCOUNT] Password changed for user #{$user_id}");
            wp_send_json_success(['msg' => PPV_Lang::t('password_changed_success', 'Jelszó sikeresen módosítva!')]);
        }
    }



    PPV_Profile_Lite_i18n::hooks();
}