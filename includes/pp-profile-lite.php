<?php
/**
 * PunktePass – Admin Profil Handler (v3.0 Modular)
 * ✅ DE, HU, RO Language Support
 * ✅ Custom PPV Login Support
 * ✅ Modular Architecture
 */

if (!defined('ABSPATH')) exit;

// Include modular components
require_once __DIR__ . '/profile-lite/class-profile-auth.php';
require_once __DIR__ . '/profile-lite/class-profile-tabs.php';
require_once __DIR__ . '/profile-lite/class-profile-ajax.php';

if (!class_exists('PPV_Profile_Lite_i18n')) {

    class PPV_Profile_Lite_i18n {

        const UPLOAD_MAX_SIZE = 4 * 1024 * 1024;
        const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
        const NONCE_ACTION = 'ppv_save_profile';
        const NONCE_NAME = 'ppv_nonce';

        public static function hooks() {
            // Turbo cache control
            add_action('wp_head', [__CLASS__, 'add_turbo_no_cache_meta'], 1);

            // Render hooks
            add_action('ppv_render_profile_form', [__CLASS__, 'render_form']);
            add_shortcode('pp_store_profile', [__CLASS__, 'render_form']);

            // Assets
            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

            // Form submit
            add_action('init', [__CLASS__, 'handle_form_submit']);

            // AJAX hooks (delegated to module)
            PPV_Profile_Ajax::register_hooks();
        }

        // ==================== TURBO CACHE FIX ====================
        public static function add_turbo_no_cache_meta() {
            global $post;

            // Check multiple ways to detect profile page
            $is_profile_page = false;

            // 1. Shortcode in post content
            if ($post && has_shortcode($post->post_content, 'pp_store_profile')) {
                $is_profile_page = true;
            }

            // 2. Check if profile form exists (for block editor / templates)
            if ($post && (
                strpos($post->post_name, 'profil') !== false ||
                strpos($post->post_name, 'profile') !== false ||
                strpos($post->post_name, 'einstellungen') !== false ||
                strpos($post->post_name, 'settings') !== false
            )) {
                $is_profile_page = true;
            }

            // 3. Check if we're in admin profile context (session check)
            if (!empty($_SESSION['ppv_store_id']) || !empty($_SESSION['ppv_current_filiale_id'])) {
                if (strpos($_SERVER['REQUEST_URI'], '/admin') !== false ||
                    strpos($_SERVER['REQUEST_URI'], '/profil') !== false ||
                    strpos($_SERVER['REQUEST_URI'], '/profile') !== false) {
                    $is_profile_page = true;
                }
            }

            if ($is_profile_page) {
                echo '<meta name="turbo-cache-control" content="no-cache">' . "\n";
                echo '<!-- PPV Profile Page - No Cache - ' . date('Y-m-d H:i:s') . ' -->' . "\n";
            }
        }

        // ==================== ASSETS ====================
        public static function enqueue_assets() {
            if (!class_exists('PPV_Lang')) {
                return;
            }

            // CSS
            wp_enqueue_style(
                'ppv-theme-light',
                PPV_PLUGIN_URL . 'assets/css/ppv-theme-light.css',
                [],
                filemtime(PPV_PLUGIN_DIR . 'assets/css/ppv-theme-light.css')
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

        // ==================== RENDER FORM ====================
        public static function render_form() {
            PPV_Profile_Auth::ensure_session();

            // Send no-cache headers
            if (!headers_sent()) {
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Cache-Control: post-check=0, pre-check=0', false);
                header('Pragma: no-cache');
                header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            }

            // Scanner users check
            if (class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user()) {
                echo '<div class="ppv-alert ppv-alert-info" style="padding: 20px; text-align: center;">
                    ℹ️ Diese Seite ist nur für Händler verfügbar.
                </div>';
                return;
            }

            $store = PPV_Profile_Auth::get_current_store();

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

                    <?php echo PPV_Profile_Tabs::render_tab_general($store); ?>
                    <?php echo PPV_Profile_Tabs::render_tab_hours($store); ?>
                    <?php echo PPV_Profile_Tabs::render_tab_media($store); ?>
                    <?php echo PPV_Profile_Tabs::render_tab_contact($store); ?>
                    <?php echo PPV_Profile_Tabs::render_tab_marketing($store); ?>
                    <?php echo PPV_Profile_Tabs::render_tab_settings($store); ?>

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

        // ==================== FORM SUBMIT ====================
        public static function handle_form_submit() {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST[self::NONCE_NAME])) {
                return;
            }

            if (!wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
                wp_die(esc_html(PPV_Lang::t('error')));
            }
        }
    }

    PPV_Profile_Lite_i18n::hooks();
}
