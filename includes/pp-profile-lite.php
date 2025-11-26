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

            // 🔹 ALWAYS USE LIGHT CSS (contains all dark mode styles via body.ppv-dark selectors)
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

            wp_enqueue_script('pp-profile-lite-i18n', PPV_PLUGIN_URL . 'assets/js/pp-profile-lite.js', ['jquery'], filemtime(PPV_PLUGIN_DIR . 'assets/js/pp-profile-lite.js'), true);

wp_localize_script('pp-profile-lite-i18n', 'ppv_profile', [
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
            <!-- ✅ Disable Turbo cache for this page to ensure fresh data after save -->
            <meta name="turbo-cache-control" content="no-cache">

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
        </div>

        <!-- SLOGAN -->
        <div class="ppv-form-group">
            <label data-i18n="slogan"><?php echo esc_html(PPV_Lang::t('slogan')); ?></label>
            <input type="text" name="slogan" value="<?php echo esc_attr($store->slogan ?? ''); ?>">
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
               <div id="ppv-logo-preview" class="ppv-media-preview" style="display: flex; align-items: center; justify-content: center; min-height: 120px; border: 1px solid #ddd; border-radius: 4px;">
    <?php if (!empty($store->logo)): ?>
        <img src="<?php echo esc_url($store->logo); ?>" alt="Logo" style="max-width: 100%; max-height: 100px; object-fit: contain;">
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
                    <label data-i18n="email"><?php echo esc_html(PPV_Lang::t('email')); ?></label>
                    <input type="email" name="email" value="<?php echo esc_attr($store->email ?? ''); ?>">
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

        private static function render_tab_settings($store) {
            ob_start();
            ?>
            <div class="ppv-tab-content" id="tab-settings">
                <h2 data-i18n="profile_settings"><?php echo esc_html(PPV_Lang::t('profile_settings')); ?></h2>

                <h3 style="margin-top: 0;">Aktivierung</h3>

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

                <h3>Wartungsmodus / Karbantartás / Mod de Întreținere</h3>

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

                <h3>Zeitzone / Időzóna / Fus Orar</h3>

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

                <!-- ============================================================
                     ONBOARDING RESET
                     ============================================================ -->
                <h3>Onboarding</h3>

                <div class="ppv-form-group">
                    <p class="ppv-help" data-i18n="onboarding_reset_help" style="margin-bottom: 12px;">
                        <?php echo esc_html(PPV_Lang::t('onboarding_reset_help')); ?>
                    </p>
                    <button type="button" id="ppv-reset-onboarding-btn" class="ppv-btn ppv-btn-secondary" style="width: 100%;">
                        🔄 <span data-i18n="onboarding_reset_btn"><?php echo esc_html(PPV_Lang::t('onboarding_reset_btn')); ?></span>
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

    // Logo upload
    if (!empty($_FILES['logo']['name'])) {
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
        
        'slogan' => sanitize_text_field($_POST['slogan'] ?? ''),
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
        'email' => sanitize_email($_POST['email'] ?? ''),
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
        'timezone' => sanitize_text_field($_POST['timezone'] ?? 'Europe/Berlin'),
        'updated_at' => current_time('mysql'),
        'logo' => sanitize_text_field($_POST['logo'] ?? ''),
// ✅ FIX: Preserve existing gallery if no new uploads
        'gallery' => !empty($gallery_files) ? json_encode($gallery_files) : ($existing_store->gallery ?? ''),
    'opening_hours' => json_encode($opening_hours),  // ← ADD THIS!
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
    '%s',  // company_name (was missing!)
    '%s',  // contact_person (was missing!)
    '%s',  // tax_id
    '%d',  // is_taxable
    '%s',  // phone
    '%s',  // email
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
    '%s',  // timezone
    '%s',  // updated_at (was missing!)
    '%s',  // logo
    '%s',  // gallery
    '%s',  // opening_hours (was missing!)
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
    }



    PPV_Profile_Lite_i18n::hooks();
}