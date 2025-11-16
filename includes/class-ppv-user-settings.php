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
        add_action('wp_ajax_ppv_upload_avatar', [__CLASS__, 'ajax_upload_avatar']);
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
    $lang_code = $GLOBALS['ppv_lang_code'] ?? 'de';
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
        wp_enqueue_style('remixicons', 'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css', [], null);
        wp_enqueue_style('ppv-user-settings', PPV_PLUGIN_URL . 'assets/css/ppv-user-settings.css', [], time());
        wp_enqueue_script('ppv-user-settings', PPV_PLUGIN_URL . 'assets/js/ppv-user-settings.js', ['jquery'], time(), true);

        $data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ppv_user_settings_nonce'),
            'lang'     => $GLOBALS['ppv_lang_code'] ?? 'de'
        ];
        wp_add_inline_script('ppv-user-settings', 'window.ppv_user_settings=' . wp_json_encode($data) . ';', 'before');
    }

    /** ============================================================
     *  🔹 Avatar feltöltés
     * ============================================================ */
    public static function ajax_upload_avatar() {
        self::ensure_user_context();
        $user_id = get_current_user_id() ?: intval($_SESSION['ppv_user_id'] ?? 0);
        if (!$user_id) wp_send_json_error(['msg' => 'Nicht eingeloggt']);

        if (!empty($_FILES['avatar']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $upload = wp_handle_upload($_FILES['avatar'], ['test_form' => false]);
            if (isset($upload['url'])) {
                update_user_meta($user_id, 'ppv_avatar', esc_url($upload['url']));
                wp_send_json_success(['url' => $upload['url']]);
            }
        }
        wp_send_json_error(['msg' => 'Upload fehlgeschlagen']);
    }

    /** ============================================================
     *  🔹 AJAX mentés
     * ============================================================ */
    public static function ajax_save_settings() {
        check_ajax_referer('ppv_user_settings_nonce', 'nonce');
        self::ensure_user_context();
        $user_id = get_current_user_id() ?: intval($_SESSION['ppv_user_id'] ?? 0);
        if (!$user_id) wp_send_json_error(['msg' => 'Nicht eingeloggt']);

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

        // nyelv frissítés
        if (isset($_POST['language'])) {
            $lang = sanitize_text_field($_POST['language']);
            setcookie('ppv_lang', $lang, time() + (3600*24*365), "/");
            $_SESSION['ppv_lang'] = $lang;
            $GLOBALS['ppv_lang_code'] = $lang;
        }

        wp_send_json_success(['msg' => 'Einstellungen gespeichert']);
    }

    /** ============================================================
     *  🔹 Oldal renderelése
     * ============================================================ */
    public static function render_settings_page() {
        self::ensure_user_context();

        $user_id = get_current_user_id() ?: intval($_SESSION['ppv_user_id'] ?? 0);
        if (!$user_id) return '<div class="ppv-notice">'.self::t('login_required').'</div>';

        $user = get_userdata($user_id);
        $avatar = get_user_meta($user_id, 'ppv_avatar', true) ?: PPV_PLUGIN_URL.'assets/img/default-avatar.png';
        $lang = $GLOBALS['ppv_lang_code'] ?? 'de';

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

                

                <!-- Data Export -->
                <div class="ppv-section">
                    <h3><i class="ri-download-cloud-2-line"></i> <?php echo self::t('data_export'); ?></h3>
                    <button type="button" id="ppv-export-data" class="neutral-btn"><i class="ri-file-download-line"></i> <?php echo self::t('export_data'); ?></button>
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
                    <button type="button" id="ppv-delete-account" class="danger-btn"><i class="ri-delete-back-2-line"></i> <?php echo self::t('delete_account'); ?></button>
                </div>

                <div class="ppv-actions">
                    <button type="submit" class="save-btn"><i class="ri-save-3-line"></i> <?php echo self::t('save_settings'); ?></button>
                </div>
            </form>
        </div>
        <?php
$content = ob_get_clean();
$content .= do_shortcode('[ppv_bottom_nav]');
return $content;


    }
    
}



PPV_User_Settings::hooks();
