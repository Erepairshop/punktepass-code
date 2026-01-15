<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – User Settings
 * Version: 5.0 – PPV Users Table Support + Birthday
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
        add_action('wp_ajax_nopriv_ppv_upload_avatar', [__CLASS__, 'ajax_upload_avatar']);
        add_action('wp_ajax_ppv_logout_all_devices', [__CLASS__, 'ajax_logout_all_devices']);
        add_action('wp_ajax_nopriv_ppv_logout_all_devices', [__CLASS__, 'ajax_logout_all_devices']);
        add_action('wp_ajax_ppv_delete_account', [__CLASS__, 'ajax_delete_account']);
        add_action('wp_ajax_nopriv_ppv_delete_account', [__CLASS__, 'ajax_delete_account']);
    }

    /** ============================================================
     *  🔹 Session / Token Bridge - Get PPV User ID
     * ============================================================ */
    private static function get_ppv_user_id() {
        if (session_status() === PHP_SESSION_NONE) @session_start();

        // Check session first
        if (!empty($_SESSION['ppv_user_id'])) {
            return intval($_SESSION['ppv_user_id']);
        }

        // Try to get from token
        if (!empty($_SESSION['ppv_user_token'])) {
            global $wpdb;
            $token = sanitize_text_field($_SESSION['ppv_user_token']);
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE qr_token=%s LIMIT 1",
                $token
            ));
            if ($user_id) {
                $_SESSION['ppv_user_id'] = intval($user_id);
                return intval($user_id);
            }
        }

        // Try login_token from cookie
        if (!empty($_COOKIE['ppv_user_token'])) {
            global $wpdb;
            $token = sanitize_text_field($_COOKIE['ppv_user_token']);
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE login_token=%s LIMIT 1",
                $token
            ));
            if ($user_id) {
                $_SESSION['ppv_user_id'] = intval($user_id);
                return intval($user_id);
            }
        }

        return 0;
    }

    /** ============================================================
     *  🔹 Get PPV User Data
     * ============================================================ */
    private static function get_ppv_user($user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_users WHERE id=%d LIMIT 1",
            $user_id
        ));
    }

    /** ============================================================
     *  🔹 Nyelvi rendszer integráció
     * ============================================================ */
    private static function t($key) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $lang_code = $_SESSION['ppv_lang'] ?? 'de';
        $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang_code}.php";

        if (file_exists($file)) {
            $translations = include $file;
            if (is_array($translations) && isset($translations[$key])) {
                return esc_html($translations[$key]);
            }
        }

        return esc_html($key);
    }

    /** ============================================================
     *  🔹 Asset betöltés
     * ============================================================ */
    public static function enqueue_assets() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

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

        $_SESSION['ppv_lang'] = $lang;
        // 🔒 SECURITY: Secure cookie flags (HttpOnly nem kell mert JS olvassa)
        setcookie('ppv_lang', $lang, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => true,
            'httponly' => false,  // JS needs to read this
            'samesite' => 'Lax'
        ]);

        ppv_log("🌍 [PPV_User_Settings] Active language: {$lang}");

        wp_enqueue_style('remixicons', 'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css', [], null);
        // ✅ Load dedicated user settings CSS AFTER theme CSS to override styles
        wp_enqueue_style('ppv-user-settings', PPV_PLUGIN_URL . 'assets/css/ppv-user-settings.css', ['ppv-theme-light'], PPV_Core::asset_version(PPV_PLUGIN_DIR . 'assets/css/ppv-user-settings.css'));
        wp_enqueue_script('ppv-user-settings', PPV_PLUGIN_URL . 'assets/js/ppv-user-settings.js', ['jquery'], PPV_Core::asset_version(PPV_PLUGIN_DIR . 'assets/js/ppv-user-settings.js'), true);

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
        check_ajax_referer('ppv_user_settings_nonce', 'nonce');

        $user_id = self::get_ppv_user_id();

        if (!$user_id) {
            wp_send_json_error(['msg' => self::t('not_logged_in')]);
            return;
        }

        if (empty($_FILES['avatar']['name'])) {
            wp_send_json_error(['msg' => self::t('upload_failed') . ' (no file)']);
            return;
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
            wp_send_json_error(['msg' => self::t('upload_failed') . ' (invalid type)']);
            return;
        }

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
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'ppv_users',
                ['avatar_url' => esc_url($upload['url'])],
                ['id' => $user_id],
                ['%s'],
                ['%d']
            );
            wp_send_json_success(['url' => $upload['url']]);
            return;
        }

        wp_send_json_error(['msg' => self::t('upload_failed')]);
    }

    /** ============================================================
     *  🔹 AJAX mentés - PPV Users Table
     * ============================================================ */
    public static function ajax_save_settings() {
        check_ajax_referer('ppv_user_settings_nonce', 'nonce');

        $user_id = self::get_ppv_user_id();
        if (!$user_id) {
            wp_send_json_error(['msg' => self::t('not_logged_in')]);
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ppv_users';

        // Get current user for password verification if changing password
        $current_user = self::get_ppv_user($user_id);
        if (!$current_user) {
            wp_send_json_error(['msg' => self::t('not_logged_in')]);
            return;
        }

        // Build update data array
        $update_data = [];
        $update_format = [];

        // Display name
        if (isset($_POST['name'])) {
            $update_data['display_name'] = sanitize_text_field($_POST['name']);
            $update_format[] = '%s';
        }

        // Email (validate)
        if (isset($_POST['email']) && is_email($_POST['email'])) {
            $new_email = sanitize_email($_POST['email']);
            // Check if email is already used by another user
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE email=%s AND id!=%d LIMIT 1",
                $new_email,
                $user_id
            ));
            if (!$existing) {
                $update_data['email'] = $new_email;
                $update_format[] = '%s';

                // ✅ HANDLER EMAIL SYNC: If user is vendor/handler, also update ppv_stores.email
                $user_type = $current_user->user_type ?? '';
                $vendor_store_id = $current_user->vendor_store_id ?? 0;

                if (($user_type === 'vendor' || $user_type === 'store') && $vendor_store_id > 0) {
                    $wpdb->update(
                        $wpdb->prefix . 'ppv_stores',
                        ['email' => $new_email],
                        ['id' => $vendor_store_id],
                        ['%s'],
                        ['%d']
                    );
                    ppv_log("✅ [PPV_User_Settings] Handler email synced to ppv_stores: user_id={$user_id}, store_id={$vendor_store_id}, new_email={$new_email}");
                }
            }
        }

        // Birthday
        if (isset($_POST['birthday'])) {
            $birthday = sanitize_text_field($_POST['birthday']);
            if (empty($birthday)) {
                $update_data['birthday'] = null;
                $update_format[] = '%s';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
                $update_data['birthday'] = $birthday;
                $update_format[] = '%s';
            }
        }

        // Address fields
        if (isset($_POST['address'])) {
            $update_data['address'] = sanitize_text_field($_POST['address']);
            $update_format[] = '%s';
        }
        if (isset($_POST['city'])) {
            $update_data['city'] = sanitize_text_field($_POST['city']);
            $update_format[] = '%s';
        }
        if (isset($_POST['zip'])) {
            $update_data['zip'] = sanitize_text_field($_POST['zip']);
            $update_format[] = '%s';
        }

        // Notification settings
        if (isset($_POST['email_notifications'])) {
            $update_data['email_notifications'] = $_POST['email_notifications'] === 'true' ? 1 : 0;
            $update_format[] = '%d';
        }
        if (isset($_POST['push_notifications'])) {
            $update_data['push_notifications'] = $_POST['push_notifications'] === 'true' ? 1 : 0;
            $update_format[] = '%d';
        }
        if (isset($_POST['promo_notifications'])) {
            $update_data['promo_notifications'] = $_POST['promo_notifications'] === 'true' ? 1 : 0;
            $update_format[] = '%d';
        }
        if (isset($_POST['whatsapp_notifications'])) {
            $update_data['whatsapp_consent'] = $_POST['whatsapp_notifications'] === 'true' ? 1 : 0;
            $update_format[] = '%d';
            // Track consent timestamp
            if ($_POST['whatsapp_notifications'] === 'true') {
                $update_data['whatsapp_consent_at'] = current_time('mysql');
                $update_format[] = '%s';
            }
        }

        // Phone number for WhatsApp
        if (isset($_POST['phone_number'])) {
            $phone = sanitize_text_field($_POST['phone_number']);
            // Remove spaces, dashes and leading zeros
            $phone = preg_replace('/[\s\-]/', '', $phone);
            $phone = ltrim($phone, '0');

            // Get country prefix (default 49 for Germany)
            $prefix = sanitize_text_field($_POST['phone_prefix'] ?? '49');
            if (!in_array($prefix, ['49', '36', '40'])) {
                $prefix = '49';
            }

            // Combine prefix + number
            if (!empty($phone)) {
                $phone = $prefix . $phone;
            }

            $update_data['phone_number'] = $phone;
            $update_format[] = '%s';
        }

        // Privacy settings
        if (isset($_POST['profile_visible'])) {
            $update_data['profile_visible'] = $_POST['profile_visible'] === 'true' ? 1 : 0;
            $update_format[] = '%d';
        }
        if (isset($_POST['marketing_emails'])) {
            $update_data['marketing_emails'] = $_POST['marketing_emails'] === 'true' ? 1 : 0;
            $update_format[] = '%d';
        }
        if (isset($_POST['data_sharing'])) {
            $update_data['data_sharing'] = $_POST['data_sharing'] === 'true' ? 1 : 0;
            $update_format[] = '%d';
        }

        // Password change
        if (!empty($_POST['new_password']) && $_POST['new_password'] === $_POST['confirm_password']) {
            $new_password = $_POST['new_password'];
            if (strlen($new_password) >= 6) {
                $update_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                $update_format[] = '%s';
            }
        }

        // Update timestamp
        $update_data['updated_at'] = current_time('mysql');
        $update_format[] = '%s';

        // Perform update
        if (!empty($update_data)) {
            $result = $wpdb->update(
                $table,
                $update_data,
                ['id' => $user_id],
                $update_format,
                ['%d']
            );

            if ($result === false) {
                ppv_log("❌ [PPV_User_Settings] Update failed: " . $wpdb->last_error);
                wp_send_json_error(['msg' => self::t('profile_save_error')]);
                return;
            }
        }

        wp_send_json_success(['msg' => self::t('settings_saved')]);
    }

    /** ============================================================
     *  🔹 Logout all devices
     * ============================================================ */
    public static function ajax_logout_all_devices() {
        check_ajax_referer('ppv_user_settings_nonce', 'nonce');

        $user_id = self::get_ppv_user_id();
        if (!$user_id) {
            wp_send_json_error(['msg' => self::t('not_logged_in')]);
            return;
        }

        global $wpdb;

        // Generate new login token to invalidate all existing sessions
        $new_token = md5(uniqid('ppv_logout_', true));
        $wpdb->update(
            $wpdb->prefix . 'ppv_users',
            ['login_token' => $new_token],
            ['id' => $user_id],
            ['%s'],
            ['%d']
        );

        // Delete from sessions table if exists
        $wpdb->delete($wpdb->prefix . 'ppv_user_sessions', ['user_id' => $user_id]);

        wp_send_json_success(['msg' => self::t('all_devices_logged_out')]);
    }

    /** ============================================================
     *  🔹 Delete account
     * ============================================================ */
    public static function ajax_delete_account() {
        check_ajax_referer('ppv_user_settings_nonce', 'nonce');

        $user_id = self::get_ppv_user_id();
        if (!$user_id) {
            wp_send_json_error(['msg' => self::t('not_logged_in')]);
            return;
        }

        $password = sanitize_text_field($_POST['password'] ?? '');
        $user = self::get_ppv_user($user_id);

        if (!$user) {
            wp_send_json_error(['msg' => self::t('not_logged_in')]);
            return;
        }

        // Verify password
        if (!password_verify($password, $user->password)) {
            wp_send_json_error(['msg' => self::t('wrong_password')]);
            return;
        }

        global $wpdb;

        // Delete user data
        $wpdb->delete($wpdb->prefix . 'ppv_users', ['id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'ppv_points', ['user_id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'ppv_user_sessions', ['user_id' => $user_id]);

        // Clear session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Clear cookies - 🔒 SECURITY: Secure flags for cookie deletion
        setcookie('ppv_user_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        wp_send_json_success(['msg' => self::t('account_deleted'), 'redirect' => home_url()]);
    }

    /** ============================================================
     *  🔹 Oldal renderelése
     * ============================================================ */
    public static function render_settings_page() {
        $user_id = self::get_ppv_user_id();
        if (!$user_id) {
            return '<div class="ppv-notice">'.self::t('login_required').'</div>';
        }

        $user = self::get_ppv_user($user_id);
        if (!$user) {
            return '<div class="ppv-notice">'.self::t('login_required').'</div>';
        }

        // Avatar
        $avatar = !empty($user->avatar_url) ? $user->avatar_url : PPV_PLUGIN_URL.'assets/img/default-avatar.svg';

        // Display name
        $display_name = !empty($user->display_name) ? $user->display_name : '';
        if (empty($display_name) && !empty($user->first_name)) {
            $display_name = trim($user->first_name . ' ' . ($user->last_name ?? ''));
        }

        // Language
        $lang = $_SESSION['ppv_lang'] ?? 'de';
        ppv_log("🔍 [PPV_User_Settings::render] Using language: {$lang}");

        // Notification settings (default to 1 if null, except WhatsApp which defaults to 0)
        $email_notif = isset($user->email_notifications) ? (bool)$user->email_notifications : true;
        $push_notif = isset($user->push_notifications) ? (bool)$user->push_notifications : true;
        $promo_notif = isset($user->promo_notifications) ? (bool)$user->promo_notifications : true;
        $whatsapp_notif = isset($user->whatsapp_consent) ? (bool)$user->whatsapp_consent : false;
        $phone_number = $user->phone_number ?? '';

        // Privacy settings
        $profile_visible = isset($user->profile_visible) ? (bool)$user->profile_visible : true;
        $marketing = isset($user->marketing_emails) ? (bool)$user->marketing_emails : true;
        $data_sharing = isset($user->data_sharing) ? (bool)$user->data_sharing : false;

        // Birthday
        $birthday = $user->birthday ?? '';

        // Address
        $address = $user->address ?? '';
        $city = $user->city ?? '';
        $zip = $user->zip ?? '';

        ob_start(); ?>
        <div class="ppv-settings-wrapper">
            <div class="ppv-header-bar">
                <h2><i class="ri-settings-4-line"></i> <?php echo self::t('my_settings'); ?></h2>
            </div>

            <!-- Avatar -->
            <div class="ppv-avatar-block">
                <img src="<?php echo esc_url($avatar); ?>" id="ppv-avatar-preview" alt="Avatar" width="100" height="100">
                <label for="ppv-avatar-upload" class="upload-btn"><i class="ri-camera-line"></i></label>
                <input type="file" id="ppv-avatar-upload" name="avatar" accept="image/*" hidden>
            </div>

            <form id="ppv-settings-form" class="ppv-form">
                <!-- Personal -->
                <div class="ppv-section">
                    <h3><i class="ri-user-line"></i> <?php echo self::t('personal_data'); ?></h3>
                    <label><?php echo self::t('name'); ?></label>
                    <input type="text" name="name" value="<?php echo esc_attr($display_name); ?>">
                    <label><?php echo self::t('email'); ?></label>
                    <input type="email" name="email" value="<?php echo esc_attr($user->email); ?>">
                    <label><?php echo self::t('birthday'); ?></label>
                    <input type="date" name="birthday" value="<?php echo esc_attr($birthday); ?>" max="<?php echo date('Y-m-d'); ?>">
                    <p class="ppv-field-hint"><?php echo self::t('birthday_hint'); ?></p>
                </div>

                <!-- Password -->
                <div class="ppv-section">
                    <h3><i class="ri-lock-password-line"></i> <?php echo self::t('change_password'); ?></h3>
                    <input type="password" name="new_password" placeholder="<?php echo self::t('new_password'); ?>">
                    <input type="password" name="confirm_password" placeholder="<?php echo self::t('repeat_password'); ?>">
                </div>

                <!-- Address -->
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

                <!-- Notifications -->
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

                    <label class="ppv-checkbox ppv-whatsapp-toggle">
                        <input type="checkbox" name="whatsapp_notifications" id="ppv-whatsapp-toggle" <?php checked($whatsapp_notif); ?>>
                        <span><i class="ri-whatsapp-line"></i> <?php echo self::t('whatsapp_notifications'); ?></span>
                    </label>
                    <p class="ppv-field-hint" style="margin-top: -8px; margin-bottom: 12px;"><?php echo self::t('whatsapp_notifications_hint'); ?></p>

                    <div id="ppv-whatsapp-phone-wrapper" class="ppv-whatsapp-phone-field" style="<?php echo $whatsapp_notif ? '' : 'display: none;'; ?>">
                        <label><?php echo self::t('whatsapp_phone'); ?></label>
                        <?php
                        // Detect current country code from saved phone
                        $current_prefix = '49'; // default German
                        $phone_without_prefix = $phone_number;
                        if (!empty($phone_number)) {
                            if (substr($phone_number, 0, 2) === '40') {
                                $current_prefix = '40';
                                $phone_without_prefix = substr($phone_number, 2);
                            } elseif (substr($phone_number, 0, 2) === '36') {
                                $current_prefix = '36';
                                $phone_without_prefix = substr($phone_number, 2);
                            } elseif (substr($phone_number, 0, 2) === '49') {
                                $current_prefix = '49';
                                $phone_without_prefix = substr($phone_number, 2);
                            }
                        }
                        ?>
                        <div class="ppv-phone-input-group">
                            <select name="phone_prefix" id="ppv-phone-prefix" class="ppv-phone-prefix-select">
                                <option value="49" <?php selected($current_prefix, '49'); ?>>🇩🇪 +49</option>
                                <option value="36" <?php selected($current_prefix, '36'); ?>>🇭🇺 +36</option>
                                <option value="40" <?php selected($current_prefix, '40'); ?>>🇷🇴 +40</option>
                            </select>
                            <input type="tel" name="phone_number" id="ppv-phone-number" value="<?php echo esc_attr($phone_without_prefix); ?>" placeholder="<?php echo self::t('whatsapp_phone_placeholder'); ?>">
                        </div>
                        <p class="ppv-field-hint"><?php echo self::t('whatsapp_phone_hint'); ?></p>
                    </div>
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

            <!-- FAQ Section -->
            <div class="ppv-faq-section">
                <div class="ppv-section">
                    <h3><i class="ri-question-answer-line"></i> <?php echo self::t('faq_title'); ?></h3>
                    <p class="ppv-faq-subtitle"><?php echo self::t('faq_subtitle'); ?></p>

                    <!-- Basics -->
                    <div class="ppv-faq-category">
                        <h4><i class="ri-lightbulb-line"></i> <?php echo self::t('faq_basics_title'); ?></h4>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q1'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a1'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q2'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a2'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q3'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a3'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q4'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a4'); ?></div>
                        </div>
                    </div>

                    <!-- Rewards -->
                    <div class="ppv-faq-category">
                        <h4><i class="ri-gift-line"></i> <?php echo self::t('faq_rewards_title'); ?></h4>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q5'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a5'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q6'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a6'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q7'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a7'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q8'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a8'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q9'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a9'); ?></div>
                        </div>
                    </div>

                    <!-- Step by Step -->
                    <div class="ppv-faq-category">
                        <h4><i class="ri-list-ordered"></i> <?php echo self::t('faq_flow_title'); ?></h4>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q10'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer">
                                <p><?php echo self::t('faq_a10_intro'); ?></p>
                                <ol class="ppv-faq-steps">
                                    <li><?php echo self::t('faq_a10_step1'); ?></li>
                                    <li><?php echo self::t('faq_a10_step2'); ?></li>
                                    <li><?php echo self::t('faq_a10_step3'); ?></li>
                                    <li><?php echo self::t('faq_a10_step4'); ?></li>
                                    <li><?php echo self::t('faq_a10_step5'); ?></li>
                                    <li><?php echo self::t('faq_a10_step6'); ?></li>
                                    <li><?php echo self::t('faq_a10_step7'); ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- VIP & Bonuses -->
                    <div class="ppv-faq-category">
                        <h4><i class="ri-vip-crown-line"></i> <?php echo self::t('faq_vip_title'); ?></h4>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q11'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a11'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q12'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a12'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q13'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a13'); ?></div>
                        </div>
                    </div>

                    <!-- Technical -->
                    <div class="ppv-faq-category">
                        <h4><i class="ri-tools-line"></i> <?php echo self::t('faq_tech_title'); ?></h4>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q14'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a14'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q15'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a15'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q16'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a16'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q17'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a17'); ?></div>
                        </div>
                    </div>

                    <!-- Security -->
                    <div class="ppv-faq-category">
                        <h4><i class="ri-shield-check-line"></i> <?php echo self::t('faq_security_title'); ?></h4>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q18'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a18'); ?></div>
                        </div>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q19'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a19'); ?></div>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="ppv-faq-category">
                        <h4><i class="ri-customer-service-2-line"></i> <?php echo self::t('faq_contact_title'); ?></h4>
                        <div class="ppv-faq-item">
                            <button class="ppv-faq-question" type="button">
                                <span><?php echo self::t('faq_q20'); ?></span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                            <div class="ppv-faq-answer"><?php echo self::t('faq_a20'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

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
