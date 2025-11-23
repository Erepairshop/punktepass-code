<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – User Settings
 * Version: 4.0 – PWA + Avatar + LangSync + Neon UI
 * Author: PunktePass / Erik Borota
 */

class PPV_User_Settings {

    /** ============================================================
     *  🔹 Hooks
     * ============================================================ */
    public static function hooks() {
        add_shortcode('ppv_user_settings', [__CLASS__, 'render_settings_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_ppv_save_user_settings', [__CLASS__, 'ajax_save_settings']);
        add_action('wp_ajax_nopriv_ppv_save_user_settings', [__CLASS__, 'ajax_save_settings']);
        // ✅ Avatar upload needs nopriv for PunktePass session users
        add_action('wp_ajax_ppv_upload_avatar', [__CLASS__, 'ajax_upload_avatar']);
        add_action('wp_ajax_nopriv_ppv_upload_avatar', [__CLASS__, 'ajax_upload_avatar']);
        add_action('wp_ajax_ppv_logout_all_devices', [__CLASS__, 'ajax_logout_all_devices']);
        add_action('wp_ajax_ppv_delete_account', [__CLASS__, 'ajax_delete_account']);
    }

    /** ============================================================
     *  🔹 Session / Token Bridge
     * ============================================================ */
    private static function ensure_user_context() {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (!is_user_logged_in() && isset($_SESSION['ppv_user_token'])) {
            global $wpdb;
            $token = sanitize_text_field($_SESSION['ppv_user_token']);
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE qr_token=%s LIMIT 1",
                $token
            ));
            if ($user_id) {
                $_SESSION['ppv_user_id'] = intval($user_id);
                $GLOBALS['ppv_active_user'] = intval($user_id);
            }
        }
    }

    /** ============================================================
     *  🔹 Nyelvi rendszer integráció
     * ============================================================ */
private static function t($key) {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    // ✅ Get language from session (set by enqueue_assets)
    $lang_code = $_SESSION['ppv_lang'] ?? 'de';
    $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang_code}.php";

    // ha létezik a fájl és return-nel tér vissza
    if (file_exists($file)) {
        $translations = include $file;
        if (is_array($translations) && isset($translations[$key])) {
            return esc_html($translations[$key]);
        }
    }

    // fallback
    return esc_html($key);
}


    /** ============================================================
     *  🔹 Asset betöltés
     * ============================================================ */
    public static function enqueue_assets() {
        // Start session for language detection
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // ✅ GET ACTIVE LANGUAGE (same logic as ppv-my-points)
        $lang = sanitize_text_field($_GET['lang'] ?? '');
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? '');
        }
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = sanitize_text_field($_SESSION['ppv_lang'] ?? 'de');
        }
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = 'de';
        }

        // Save to session + cookie
        $_SESSION['ppv_lang'] = $lang;
        setcookie('ppv_lang', $lang, time() + 31536000, '/', '', false, true);

        ppv_log("🌍 [PPV_User_Settings] Active language: {$lang}");

        wp_enqueue_style('remixicons', 'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css', [], null);
        wp_enqueue_style('ppv-user-settings', PPV_PLUGIN_URL . 'assets/css/ppv-user-settings.css', [], time());
        wp_enqueue_script('ppv-user-settings', PPV_PLUGIN_URL . 'assets/js/ppv-user-settings.js', ['jquery'], time(), true);

        $data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ppv_user_settings_nonce'),
            'lang'     => $lang
        ];
        wp_add_inline_script('ppv-user-settings', 'window.ppv_user_settings=' . wp_json_encode($data) . ';', 'before');
    }

    /** ============================================================
     *  🔹 Avatar feltöltés
     * ============================================================ */
    public static function ajax_upload_avatar() {
        // ✅ Nonce ellenőrzés
        check_ajax_referer('ppv_user_settings_nonce', 'nonce');

        self::ensure_user_context();
        $user_id = get_current_user_id() ?: intval($_SESSION['ppv_user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(['msg' => self::t('not_logged_in')]);
            return;
        }

        if (empty($_FILES['avatar']['name'])) {
            wp_send_json_error(['msg' => self::t('upload_failed') . ' (no file)']);
            return;
        }

        // ✅ Fájl típus ellenőrzés
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $file_type = wp_check_filetype($_FILES['avatar']['name']);

        if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
            wp_send_json_error(['msg' => self::t('upload_failed') . ' (invalid type)']);
            return;
        }

        // ✅ Méret ellenőrzés (max 4MB)
        if ($_FILES['avatar']['size'] > 4 * 1024 * 1024) {
            wp_send_json_error(['msg' => self::t('upload_failed') . ' (file too large)']);
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upload = wp_handle_upload($_FILES['avatar'], ['test_form' => false]);

        if (isset($upload['error'])) {
            ppv_log("❌ [PPV_Avatar] Upload error: " . $upload['error']);
            wp_send_json_error(['msg' => self::t('upload_failed') . ': ' . $upload['error']]);
            return;
        }

        if (isset($upload['url'])) {
            update_user_meta($user_id, 'ppv_avatar', esc_url($upload['url']));
            wp_send_json_success(['url' => $upload['url']]);
            return;
        }

        wp_send_json_error(['msg' => self::t('upload_failed')]);
    }

    /** ============================================================
     *  🔹 AJAX mentés
     * ============================================================ */
    public static function ajax_save_settings() {
        check_ajax_referer('ppv_user_settings_nonce', 'nonce');
        self::ensure_user_context();
        $user_id = get_current_user_id() ?: intval($_SESSION['ppv_user_id'] ?? 0);
        if (!$user_id) wp_send_json_error(['msg' => self::t('not_logged_in')]);

        // alapadatok
        if (isset($_POST['name'])) {
            wp_update_user(['ID' => $user_id, 'display_name' => sanitize_text_field($_POST['name'])]);
        }
        if (isset($_POST['email']) && is_email($_POST['email'])) {
            wp_update_user(['ID' => $user_id, 'user_email' => sanitize_email($_POST['email'])]);
        }

        // jelszó
        if (!empty($_POST['new_password']) && $_POST['new_password'] === $_POST['confirm_password']) {
            wp_set_password($_POST['new_password'], $user_id);
        }

        // Értesítési beállítások
        if (isset($_POST['email_notifications'])) {
            update_user_meta($user_id, 'ppv_email_notifications', $_POST['email_notifications'] === 'true' ? '1' : '0');
        }
        if (isset($_POST['push_notifications'])) {
            update_user_meta($user_id, 'ppv_push_notifications', $_POST['push_notifications'] === 'true' ? '1' : '0');
        }
        if (isset($_POST['promo_notifications'])) {
            update_user_meta($user_id, 'ppv_promo_notifications', $_POST['promo_notifications'] === 'true' ? '1' : '0');
        }

        // Privacy beállítások
        if (isset($_POST['profile_visible'])) {
            update_user_meta($user_id, 'ppv_profile_visible', $_POST['profile_visible'] === 'true' ? '1' : '0');
        }
        if (isset($_POST['marketing_emails'])) {
            update_user_meta($user_id, 'ppv_marketing_emails', $_POST['marketing_emails'] === 'true' ? '1' : '0');
        }
        if (isset($_POST['data_sharing'])) {
            update_user_meta($user_id, 'ppv_data_sharing', $_POST['data_sharing'] === 'true' ? '1' : '0');
        }

        // Cím
        if (isset($_POST['address'])) {
            update_user_meta($user_id, 'ppv_address', sanitize_text_field($_POST['address']));
        }
        if (isset($_POST['city'])) {
            update_user_meta($user_id, 'ppv_city', sanitize_text_field($_POST['city']));
        }
        if (isset($_POST['zip'])) {
            update_user_meta($user_id, 'ppv_zip', sanitize_text_field($_POST['zip']));
        }

        wp_send_json_success(['msg' => self::t('settings_saved')]);
    }

    /** ============================================================
     *  🔹 Logout all devices
     * ============================================================ */
    public static function ajax_logout_all_devices() {
        check_ajax_referer('ppv_user_settings_nonce', 'nonce');
        self::ensure_user_context();
        $user_id = get_current_user_id() ?: intval($_SESSION['ppv_user_id'] ?? 0);
        if (!$user_id) wp_send_json_error(['msg' => self::t('not_logged_in')]);

        // WordPress sessions törlése
        $sessions = WP_Session_Tokens::get_instance($user_id);
        $sessions->destroy_all();

        // PunktePass sessions törlése
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'ppv_user_sessions', ['user_id' => $user_id]);

        wp_send_json_success(['msg' => self::t('all_devices_logged_out')]);
    }

    /** ============================================================
     *  🔹 Delete account
     * ============================================================ */
    public static function ajax_delete_account() {
        check_ajax_referer('ppv_user_settings_nonce', 'nonce');
        self::ensure_user_context();
        $user_id = get_current_user_id() ?: intval($_SESSION['ppv_user_id'] ?? 0);
        if (!$user_id) wp_send_json_error(['msg' => self::t('not_logged_in')]);

        $password = sanitize_text_field($_POST['password'] ?? '');
        $user = get_userdata($user_id);

        // Jelszó ellenőrzés
        if (!wp_check_password($password, $user->user_pass, $user_id)) {
            wp_send_json_error(['msg' => self::t('wrong_password')]);
        }

        // User törlése
        require_once ABSPATH . 'wp-admin/includes/user.php';
        global $wpdb;

        // PunktePass adatok törlése
        $wpdb->delete($wpdb->prefix . 'ppv_users', ['id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'ppv_points', ['user_id' => $user_id]);

        // WordPress user törlése
        wp_delete_user($user_id);

        // Session törlése
        session_destroy();

        wp_send_json_success(['msg' => self::t('account_deleted'), 'redirect' => home_url()]);
    }

    /** ============================================================
     *  🔹 Oldal renderelése
     * ============================================================ */
    public static function render_settings_page() {
        self::ensure_user_context();

        $user_id = get_current_user_id() ?: intval($_SESSION['ppv_user_id'] ?? 0);
        if (!$user_id) return '<div class="ppv-notice">'.self::t('login_required').'</div>';

        $user = get_userdata($user_id);
        $avatar = get_user_meta($user_id, 'ppv_avatar', true) ?: PPV_PLUGIN_URL.'assets/img/default-avatar.svg';

        // ✅ Get language from session (already set by enqueue_assets)
        $lang = $_SESSION['ppv_lang'] ?? 'de';

        ppv_log("🔍 [PPV_User_Settings::render] Using language: {$lang}");

        // Értesítési beállítások
        $email_notif = get_user_meta($user_id, 'ppv_email_notifications', true) !== '0';
        $push_notif = get_user_meta($user_id, 'ppv_push_notifications', true) !== '0';
        $promo_notif = get_user_meta($user_id, 'ppv_promo_notifications', true) !== '0';

        // Privacy
        $profile_visible = get_user_meta($user_id, 'ppv_profile_visible', true) !== '0';
        $marketing = get_user_meta($user_id, 'ppv_marketing_emails', true) !== '0';
        $data_sharing = get_user_meta($user_id, 'ppv_data_sharing', true) !== '0';

        // Cím
        $address = get_user_meta($user_id, 'ppv_address', true);
        $city = get_user_meta($user_id, 'ppv_city', true);
        $zip = get_user_meta($user_id, 'ppv_zip', true);

        ob_start(); ?>
        <div class="ppv-settings-wrapper">
            <div class="ppv-header-bar">
                
                <h2><i class="ri-settings-4-line"></i> <?php echo self::t('my_settings'); ?></h2>
            </div>

            <!-- Avatar -->
            <div class="ppv-avatar-block">
                <img src="<?php echo esc_url($avatar); ?>" id="ppv-avatar-preview" alt="Avatar">
                <label for="ppv-avatar-upload" class="upload-btn"><i class="ri-camera-line"></i></label>
                <input type="file" id="ppv-avatar-upload" name="avatar" accept="image/*" hidden>
            </div>

            <form id="ppv-settings-form" class="ppv-form">
                <!-- Personal -->
                <div class="ppv-section">
                    <h3><i class="ri-user-line"></i> <?php echo self::t('personal_data'); ?></h3>
                    <label><?php echo self::t('name'); ?></label>
                    <input type="text" name="name" value="<?php echo esc_attr($user->display_name); ?>">
                    <label><?php echo self::t('email'); ?></label>
                    <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>">
                </div>

                <!-- Password -->
                <div class="ppv-section">
                    <h3><i class="ri-lock-password-line"></i> <?php echo self::t('change_password'); ?></h3>
                    <input type="password" name="new_password" placeholder="<?php echo self::t('new_password'); ?>">
                    <input type="password" name="confirm_password" placeholder="<?php echo self::t('repeat_password'); ?>">
                </div>

                <!-- Címkezelés -->
                <div class="ppv-section">
                    <h3><i class="ri-map-pin-line"></i> <?php echo self::t('address'); ?></h3>
                    <label><?php echo self::t('street_address'); ?></label>
                    <input type="text" name="address" value="<?php echo esc_attr($address); ?>" placeholder="<?php echo self::t('street_placeholder'); ?>">

                    <div class="ppv-form-row">
                        <div>
                            <label><?php echo self::t('zip'); ?></label>
                            <input type="text" name="zip" value="<?php echo esc_attr($zip); ?>" placeholder="<?php echo self::t('zip_placeholder'); ?>">
                        </div>
                        <div>
                            <label><?php echo self::t('city'); ?></label>
                            <input type="text" name="city" value="<?php echo esc_attr($city); ?>" placeholder="<?php echo self::t('city_placeholder'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Értesítések -->
                <div class="ppv-section">
                    <h3><i class="ri-notification-3-line"></i> <?php echo self::t('notifications'); ?></h3>

                    <label class="ppv-checkbox">
                        <input type="checkbox" name="email_notifications" <?php checked($email_notif); ?>>
                        <span><?php echo self::t('email_notifications'); ?></span>
                    </label>

                    <label class="ppv-checkbox">
                        <input type="checkbox" name="push_notifications" <?php checked($push_notif); ?>>
                        <span><?php echo self::t('push_notifications'); ?></span>
                    </label>

                    <label class="ppv-checkbox">
                        <input type="checkbox" name="promo_notifications" <?php checked($promo_notif); ?>>
                        <span><?php echo self::t('promo_notifications'); ?></span>
                    </label>
                </div>

                <!-- Privacy -->
                <div class="ppv-section">
                    <h3><i class="ri-shield-user-line"></i> <?php echo self::t('privacy'); ?></h3>

                    <label class="ppv-checkbox">
                        <input type="checkbox" name="profile_visible" <?php checked($profile_visible); ?>>
                        <span><?php echo self::t('profile_visible'); ?></span>
                    </label>

                    <label class="ppv-checkbox">
                        <input type="checkbox" name="marketing_emails" <?php checked($marketing); ?>>
                        <span><?php echo self::t('marketing_emails'); ?></span>
                    </label>

                    <label class="ppv-checkbox">
                        <input type="checkbox" name="data_sharing" <?php checked($data_sharing); ?>>
                        <span><?php echo self::t('data_sharing'); ?></span>
                    </label>
                </div>

                <!-- Devices -->
                <div class="ppv-section">
                    <h3><i class="ri-device-line"></i> <?php echo self::t('devices'); ?></h3>
                    <p class="muted"><?php echo self::t('devices_info'); ?></p>
                    <button type="button" id="ppv-logout-all" class="neutral-btn"><i class="ri-logout-box-line"></i> <?php echo self::t('logout_all_devices'); ?></button>
                </div>

                <!-- Delete -->
                <div class="ppv-section danger">
                    <h3><i class="ri-delete-bin-6-line"></i> <?php echo self::t('account_privacy'); ?></h3>
                    <p><?php echo self::t('delete_info'); ?></p>
                    <button type="button" id="ppv-delete-account-btn" class="danger-btn"><i class="ri-delete-back-2-line"></i> <?php echo self::t('delete_account'); ?></button>
                </div>

                <div class="ppv-actions">
                    <button type="submit" class="save-btn"><i class="ri-save-3-line"></i> <?php echo self::t('save_settings'); ?></button>
                </div>
            </form>

            <!-- Delete Account Modal -->
            <div id="ppv-delete-modal" class="ppv-modal">
                <div class="ppv-modal-content">
                    <span class="ppv-modal-close">&times;</span>
                    <h3><i class="ri-error-warning-line"></i> <?php echo self::t('delete_account_confirm'); ?></h3>
                    <p><?php echo self::t('delete_account_warning'); ?></p>
                    <div class="ppv-modal-form">
                        <label><?php echo self::t('enter_password'); ?></label>
                        <input type="password" id="ppv-delete-password" placeholder="<?php echo self::t('password'); ?>">
                        <div class="ppv-modal-actions">
                            <button type="button" id="ppv-cancel-delete" class="neutral-btn"><?php echo self::t('cancel'); ?></button>
                            <button type="button" id="ppv-confirm-delete" class="danger-btn"><i class="ri-delete-bin-line"></i> <?php echo self::t('delete_permanently'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
$content = ob_get_clean();
$content .= do_shortcode('[ppv_bottom_nav]');
return $content;


    }
    
}



PPV_User_Settings::hooks();
