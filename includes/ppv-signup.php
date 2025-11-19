<?php
/**
 * PunktePass - User/Händler Signup System
 * Modern Registration with Google OAuth
 * Multi-language Support (DE/HU/RO)
 *
 * NEW: Händler registration with 30-day trial
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Signup {

    /** ============================================================
     * 🔹 Hooks
     * ============================================================ */
    public static function hooks() {
        add_shortcode('ppv_signup', [__CLASS__, 'render_signup_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // AJAX hooks - for logged OUT users (nopriv)
        add_action('wp_ajax_nopriv_ppv_signup', [__CLASS__, 'ajax_signup']);
        add_action('wp_ajax_nopriv_ppv_google_signup', [__CLASS__, 'ajax_google_signup']);

        // ALSO for logged IN users (safety measure)
        add_action('wp_ajax_ppv_signup', [__CLASS__, 'ajax_signup']);
        add_action('wp_ajax_ppv_google_signup', [__CLASS__, 'ajax_google_signup']);

        error_log("✅ [PPV_Signup] Hooks registered successfully");
    }

    /** ============================================================
     * 🔹 Initialize
     * ============================================================ */
    public static function init() {
        if (!session_id() && !headers_sent()) {
            @session_start();
        }

        self::hooks();
        error_log("✅ [PPV_Signup] Initialized");
    }

    /** ============================================================
     * 🔹 Get Current Language
     * ============================================================ */
    private static function get_current_lang() {
        static $lang = null;
        if ($lang !== null) return $lang;

        if (isset($_COOKIE['ppv_lang'])) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang']);
        } elseif (isset($_GET['lang'])) {
            $lang = sanitize_text_field($_GET['lang']);
        } else {
            $lang = substr(get_locale(), 0, 2);
        }

        if (!in_array($lang, ['de', 'hu', 'ro'])) {
            $lang = 'de';
        }

        return $lang;
    }

    /** ============================================================
     * 🔹 Ensure Session Started
     * ============================================================ */
    private static function ensure_session() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $domain = str_replace('www.', '', $_SERVER['HTTP_HOST'] ?? 'punktepass.de');
            session_set_cookie_params([
                'lifetime' => 86400 * 180,
                'path'     => '/',
                'domain'   => $domain,
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            @session_start();
        }
    }

    /** ============================================================
     * 🔹 Asset Loading
     * ============================================================ */
    public static function enqueue_assets() {
        // Only load on signup page
        if (!is_page(['signup', 'registrierung', 'regisztracio'])) {
            global $post;
            if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ppv_signup')) {
                return;
            }
        }

        // Google Fonts - Inter
        wp_enqueue_style(
            'ppv-inter-font',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            [],
            null
        );

        // Use same CSS as login
        wp_enqueue_style(
            'ppv-login',
            PPV_PLUGIN_URL . 'assets/css/ppv-login-light.css',
            [],
            PPV_VERSION
        );

        // Signup JS
        wp_enqueue_script(
            'ppv-signup',
            PPV_PLUGIN_URL . 'assets/js/ppv-signup.js',
            ['jquery'],
            PPV_VERSION,
            true
        );

        // Google OAuth Library
        wp_enqueue_script(
            'google-platform',
            'https://accounts.google.com/gsi/client',
            [],
            null,
            true
        );

        $google_client_id = defined('PPV_GOOGLE_CLIENT_ID')
            ? PPV_GOOGLE_CLIENT_ID
            : get_option('ppv_google_client_id', '453567547051-odmqrinafba8ls8ktp9snlp7d2fpl9q0.apps.googleusercontent.com');

        // Localize
        wp_localize_script('ppv-signup', 'ppvSignup', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ppv_signup_nonce'),
            'google_client_id' => $google_client_id,
            'redirect_url' => home_url('/user_dashboard'),
            'vendor_redirect_url' => home_url('/haendler'),
            'debug' => true
        ]);

        error_log("✅ [PPV_Signup] Assets enqueued");
    }

    /** ============================================================
     * 🔹 Render Signup Page
     * ============================================================ */
    public static function render_signup_page($atts) {
        self::ensure_session();

        // Auto-redirect if already logged in
        if (!empty($_SESSION['ppv_user_id'])) {
            $user_type = $_SESSION['ppv_user_type'] ?? 'user';
            if ($user_type === 'user') {
                wp_redirect(home_url('/user_dashboard'));
                exit;
            } elseif ($user_type === 'vendor') {
                wp_redirect(home_url('/qr-center'));
                exit;
            }
        }

        $lang = self::get_current_lang();

        ob_start();
        ?>

        <div class="ppv-landing-container">
            <!-- Header -->
            <header class="ppv-landing-header">
                <div class="ppv-header-content">
                    <div class="ppv-logo-section">
                        <img src="<?php echo PPV_PLUGIN_URL; ?>assets/img/logo.webp" alt="PunktePass" class="ppv-logo">
                        <h1>PunktePass</h1>
                    </div>
                    <p class="ppv-slogan"><?php echo PPV_Lang::t('signup_slogan'); ?></p>

                    <!-- Language Switcher -->
                    <div class="ppv-lang-switcher">
                        <select id="ppv-lang-select" class="ppv-lang-select">
                            <option value="de" <?php echo $lang === 'de' ? 'selected' : ''; ?>>🇩🇪 DE</option>
                            <option value="hu" <?php echo $lang === 'hu' ? 'selected' : ''; ?>>🇭🇺 HU</option>
                            <option value="ro" <?php echo $lang === 'ro' ? 'selected' : ''; ?>>🇷🇴 RO</option>
                        </select>
                    </div>
                </div>
            </header>

            <!-- Hero Section -->
            <div class="ppv-hero-section">
                <!-- Animated Background -->
                <div class="ppv-hero-bg">
                    <div class="ppv-bg-shape ppv-shape-1"></div>
                    <div class="ppv-bg-shape ppv-shape-2"></div>
                    <div class="ppv-bg-shape ppv-shape-3"></div>
                </div>

                <div class="ppv-hero-content" style="justify-content: center;">
                    <!-- Signup Card (Centered) -->
                    <div class="ppv-login-section" style="max-width: 520px;">
                        <div class="ppv-login-card">
                            <!-- Welcome Text -->
                            <div class="ppv-login-welcome">
                                <h2><?php echo PPV_Lang::t('signup_title'); ?></h2>
                                <p><?php echo PPV_Lang::t('signup_subtitle'); ?></p>
                            </div>

                            <!-- User Type Selection (NEW!) -->
                            <div class="ppv-user-type-selector" style="margin-bottom: 24px;">
                                <p style="font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 12px;">Ich bin:</p>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <label class="ppv-type-option ppv-type-active" data-type="user">
                                        <input type="radio" name="user_type" value="user" checked style="display: none;">
                                        <div style="padding: 16px; border: 2px solid #E5E7EB; border-radius: 12px; cursor: pointer; text-align: center; transition: all 0.3s;">
                                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 8px; color: #6B7280;">
                                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                                <circle cx="12" cy="7" r="4"/>
                                            </svg>
                                            <strong style="display: block; font-size: 15px; color: #111827; margin-bottom: 4px;">Kunde</strong>
                                            <span style="font-size: 12px; color: #6B7280;">Punkte sammeln</span>
                                        </div>
                                    </label>

                                    <label class="ppv-type-option" data-type="vendor">
                                        <input type="radio" name="user_type" value="vendor" style="display: none;">
                                        <div style="padding: 16px; border: 2px solid #E5E7EB; border-radius: 12px; cursor: pointer; text-align: center; transition: all 0.3s;">
                                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 8px; color: #6B7280;">
                                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                                <polyline points="9 22 9 12 15 12 15 22"/>
                                            </svg>
                                            <strong style="display: block; font-size: 15px; color: #111827; margin-bottom: 4px;">Händler</strong>
                                            <span style="font-size: 12px; color: #6B7280;">30 Tage Test</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Google Signup Button -->
                            <button type="button" id="ppv-google-signup-btn" class="ppv-google-btn">
                                <svg width="20" height="20" viewBox="0 0 48 48">
                                    <path d="M47.532 24.5528C47.532 22.9214 47.3997 21.2811 47.1175 19.6761H24.48V28.9181H37.4434C36.9055 31.8988 35.177 34.5356 32.6461 36.2111V42.2078H40.3801C44.9217 38.0278 47.532 31.8547 47.532 24.5528Z" fill="#4285F4"/>
                                    <path d="M24.48 48.0016C30.9529 48.0016 36.4116 45.8764 40.3888 42.2078L32.6549 36.2111C30.5031 37.675 27.7252 38.5039 24.4888 38.5039C18.2275 38.5039 12.9187 34.2798 11.0139 28.6006H3.03296V34.7825C7.10718 42.8868 15.4056 48.0016 24.48 48.0016Z" fill="#34A853"/>
                                    <path d="M11.0051 28.6006C9.99973 25.6199 9.99973 22.3922 11.0051 19.4115V13.2296H3.03298C-0.371021 20.0112 -0.371021 28.0009 3.03298 34.7825L11.0051 28.6006Z" fill="#FBBC04"/>
                                    <path d="M24.48 9.49932C27.9016 9.44641 31.2086 10.7339 33.6866 13.0973L40.5387 6.24523C36.2 2.17101 30.4414 -0.068932 24.48 0.00161733C15.4055 0.00161733 7.10718 5.11644 3.03296 13.2296L11.005 19.4115C12.901 13.7235 18.2187 9.49932 24.48 9.49932Z" fill="#EA4335"/>
                                </svg>
                                <span><?php echo PPV_Lang::t('signup_google_btn'); ?></span>
                            </button>

                            <!-- Divider -->
                            <div class="ppv-login-divider">
                                <span><?php echo PPV_Lang::t('signup_or_email'); ?></span>
                            </div>

                            <!-- Signup Form -->
                            <form id="ppv-signup-form" class="ppv-login-form" autocomplete="off">
                                <input type="hidden" name="user_type" id="ppv-user-type" value="user">

                                <div class="ppv-form-group">
                                    <label for="ppv-email">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                            <polyline points="22,6 12,13 2,6"/>
                                        </svg>
                                        <?php echo PPV_Lang::t('signup_email_label'); ?>
                                    </label>
                                    <input
                                        type="email"
                                        id="ppv-email"
                                        name="email"
                                        placeholder="<?php echo PPV_Lang::t('signup_email_placeholder'); ?>"
                                        autocomplete="email"
                                        required
                                    >
                                </div>

                                <div class="ppv-form-group">
                                    <label for="ppv-password">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                        </svg>
                                        <?php echo PPV_Lang::t('signup_password_label'); ?>
                                    </label>
                                    <div class="ppv-password-wrapper">
                                        <input
                                            type="password"
                                            id="ppv-password"
                                            name="password"
                                            placeholder="<?php echo PPV_Lang::t('signup_password_placeholder'); ?>"
                                            autocomplete="new-password"
                                            required
                                        >
                                        <button type="button" class="ppv-password-toggle" aria-label="<?php echo PPV_Lang::t('signup_show_password'); ?>">
                                            <svg class="ppv-eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                            <svg class="ppv-eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                                <line x1="1" y1="1" x2="23" y2="23"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <!-- Password Strength Indicator -->
                                    <div id="ppv-password-strength" class="ppv-password-strength" style="display:none;">
                                        <div class="ppv-strength-bar">
                                            <div class="ppv-strength-fill"></div>
                                        </div>
                                        <p class="ppv-strength-text"></p>
                                    </div>
                                    <!-- Password Requirements -->
                                    <div class="ppv-password-requirements">
                                        <p style="font-size: 12px; color: #6B7280; margin: 8px 0 4px 0;"><?php echo PPV_Lang::t('signup_password_requirements'); ?></p>
                                        <ul style="font-size: 12px; color: #6B7280; margin: 0; padding-left: 20px;">
                                            <li id="req-length"><?php echo PPV_Lang::t('signup_req_length'); ?></li>
                                            <li id="req-uppercase"><?php echo PPV_Lang::t('signup_req_uppercase'); ?></li>
                                            <li id="req-number"><?php echo PPV_Lang::t('signup_req_number'); ?></li>
                                            <li id="req-special"><?php echo PPV_Lang::t('signup_req_special'); ?></li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="ppv-form-group">
                                    <label for="ppv-password-confirm">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                        <?php echo PPV_Lang::t('signup_password_confirm_label'); ?>
                                    </label>
                                    <div class="ppv-password-wrapper">
                                        <input
                                            type="password"
                                            id="ppv-password-confirm"
                                            name="password_confirm"
                                            placeholder="<?php echo PPV_Lang::t('signup_password_confirm_placeholder'); ?>"
                                            autocomplete="new-password"
                                            required
                                        >
                                    </div>
                                </div>

                                <div class="ppv-form-footer" style="display: block;">
                                    <label class="ppv-checkbox" style="margin-bottom: 12px;">
                                        <input type="checkbox" name="terms" id="ppv-terms" required>
                                        <span><?php echo PPV_Lang::t('signup_terms_agree'); ?> <a href="/agb" target="_blank"><?php echo PPV_Lang::t('signup_terms_link'); ?></a></span>
                                    </label>
                                    <label class="ppv-checkbox">
                                        <input type="checkbox" name="privacy" id="ppv-privacy" required>
                                        <span><?php echo PPV_Lang::t('signup_privacy_agree'); ?> <a href="/datenschutz" target="_blank"><?php echo PPV_Lang::t('signup_privacy_link'); ?></a></span>
                                    </label>
                                </div>

                                <button type="submit" class="ppv-submit-btn" id="ppv-submit-btn">
                                    <span class="ppv-btn-text"><?php echo PPV_Lang::t('signup_button'); ?></span>
                                    <span class="ppv-btn-loader" style="display:none;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10" opacity="0.25"/>
                                            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round">
                                                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                                            </path>
                                        </svg>
                                    </span>
                                </button>
                            </form>

                            <!-- Alert Box -->
                            <div id="ppv-signup-alert" class="ppv-alert" style="display:none;"></div>

                            <!-- Login Link -->
                            <div class="ppv-register-link">
                                <p><?php echo PPV_Lang::t('signup_have_account'); ?> <a href="/login"><?php echo PPV_Lang::t('signup_login_now'); ?></a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="ppv-landing-footer">
                <p><?php echo PPV_Lang::t('landing_footer_copyright'); ?></p>
                <div class="ppv-footer-links">
                    <a href="/datenschutz"><?php echo PPV_Lang::t('landing_footer_privacy'); ?></a>
                    <span>•</span>
                    <a href="/agb"><?php echo PPV_Lang::t('landing_footer_terms'); ?></a>
                    <span>•</span>
                    <a href="/impressum"><?php echo PPV_Lang::t('landing_footer_imprint'); ?></a>
                </div>
            </footer>
        </div>

        <style>
        /* User Type Selector Styling */
        .ppv-type-option > div {
            transition: all 0.3s ease;
        }

        .ppv-type-option:hover > div {
            border-color: #3B82F6 !important;
            background: #EFF6FF;
        }

        .ppv-type-option.ppv-type-active > div {
            border-color: #3B82F6 !important;
            background: #EFF6FF;
        }

        .ppv-type-option.ppv-type-active svg {
            color: #3B82F6 !important;
        }

        .ppv-type-option.ppv-type-active strong {
            color: #1E40AF !important;
        }
        </style>

        <script>
        // User Type Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const typeOptions = document.querySelectorAll('.ppv-type-option');
            const hiddenInput = document.getElementById('ppv-user-type');

            typeOptions.forEach(option => {
                option.addEventListener('click', function() {
                    typeOptions.forEach(opt => opt.classList.remove('ppv-type-active'));
                    this.classList.add('ppv-type-active');
                    this.querySelector('input[type="radio"]').checked = true;
                    hiddenInput.value = this.querySelector('input[type="radio"]').value;
                });
            });
        });
        </script>

        <?php
        return ob_get_clean();
    }

    /** ============================================================
     * 🔹 AJAX Signup Handler (WITH HÄNDLER SUPPORT)
     * ============================================================ */
    public static function ajax_signup() {
        error_log("========================================");
        error_log("🔹 [PPV_Signup] AJAX signup called");
        error_log("========================================");

        global $wpdb;
        $prefix = $wpdb->prefix;

        if (!$wpdb) {
            error_log("❌ [PPV_Signup] WPDB not available");
            wp_send_json_error(['message' => 'Adatbázis hiba']);
            return;
        }

        self::ensure_session();

        // NONCE CHECK
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ppv_signup_nonce')) {
            error_log("❌ [PPV_Signup] Nonce verification failed");
            wp_send_json_error(['message' => 'Biztonsági ellenőrzés sikertelen!']);
            return;
        }

        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $terms = isset($_POST['terms']) && $_POST['terms'] === 'true';
        $privacy = isset($_POST['privacy']) && $_POST['privacy'] === 'true';
        $user_type = sanitize_text_field($_POST['user_type'] ?? 'user'); // NEW!

        error_log("📧 Email: {$email}");
        error_log("👤 User Type: {$user_type}");

        // Validation
        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => PPV_Lang::t('signup_error_empty')]);
            return;
        }

        if (!is_email($email)) {
            wp_send_json_error(['message' => PPV_Lang::t('signup_error_invalid_email')]);
            return;
        }

        if ($password !== $password_confirm) {
            wp_send_json_error(['message' => PPV_Lang::t('signup_error_password_mismatch')]);
            return;
        }

        if (strlen($password) < 8) {
            wp_send_json_error(['message' => PPV_Lang::t('signup_error_password_short')]);
            return;
        }

        if (!$terms || !$privacy) {
            wp_send_json_error(['message' => PPV_Lang::t('signup_error_terms')]);
            return;
        }

        // Check if email exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}ppv_users WHERE email=%s LIMIT 1",
            $email
        ));

        if ($exists) {
            error_log("❌ [PPV_Signup] Email exists: {$email}");
            wp_send_json_error(['message' => PPV_Lang::t('signup_error_email_exists')]);
            return;
        }

        // Create user
        $qr_token = wp_generate_password(10, false, false);
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);

        $insert_data = [
            'email' => $email,
            'password' => $password_hashed,
            'qr_token' => $qr_token,
            'user_type' => $user_type,
            'active' => 1,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $insert_format = ['%s', '%s', '%s', '%s', '%d', '%s', '%s'];

        $insert_result = $wpdb->insert("{$prefix}ppv_users", $insert_data, $insert_format);

        if ($insert_result === false) {
            error_log("❌ [PPV_Signup] Insert failed: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Regisztráció sikertelen: ' . $wpdb->last_error]);
            return;
        }

        $user_id = $wpdb->insert_id;
        error_log("✅ [PPV_Signup] User created: #{$user_id} ({$user_type})");

        // HÄNDLER: Create store
        $store_id = null;
        if ($user_type === 'vendor') {
            $pos_token = md5(uniqid('pos_', true));
            $trial_ends_at = date('Y-m-d H:i:s', strtotime('+30 days'));

            $store_result = $wpdb->insert(
                "{$prefix}ppv_stores",
                [
                    'user_id' => $user_id,
                    'email' => $email,
                    'name' => 'Mein Geschäft',
                    'pos_token' => $pos_token,
                    'trial_ends_at' => $trial_ends_at,
                    'subscription_status' => 'trial',
                    'active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            );

            if ($store_result !== false) {
                $store_id = $wpdb->insert_id;

                // Link store to user
                $wpdb->update(
                    "{$prefix}ppv_users",
                    ['vendor_store_id' => $store_id],
                    ['id' => $user_id],
                    ['%d'],
                    ['%d']
                );

                error_log("✅ [PPV_Signup] Store created: #{$store_id} | Trial ends: {$trial_ends_at}");
            } else {
                error_log("❌ [PPV_Signup] Store creation failed: " . $wpdb->last_error);
            }
        }

        // Set session
        $_SESSION['ppv_user_id'] = $user_id;
        $_SESSION['ppv_user_type'] = $user_type;
        $_SESSION['ppv_user_email'] = $email;

        if ($user_type === 'vendor' && $store_id) {
            $_SESSION['ppv_vendor_store_id'] = $store_id;
            $_SESSION['ppv_store_id'] = $store_id;
            $_SESSION['ppv_active_store'] = $store_id;
        }

        $GLOBALS['ppv_role'] = $user_type;

        // Generate token
        $token = md5(uniqid('ppv_user_', true));
        $wpdb->update(
            "{$prefix}ppv_users",
            ['login_token' => $token],
            ['id' => $user_id],
            ['%s'],
            ['%d']
        );

        // Set cookie
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        setcookie('ppv_user_token', $token, time() + (86400 * 180), '/', $domain, true, true);

        // Determine redirect
        $redirect_url = $user_type === 'vendor' ? home_url('/qr-center') : home_url('/user_dashboard');

        error_log("========================================");
        error_log("✅ [PPV_Signup] SUCCESS - User #{$user_id}: {$email} ({$user_type})");
        if ($user_type === 'vendor') {
            error_log("   Store: #{$store_id} | Trial: {$trial_ends_at}");
        }
        error_log("========================================");

        wp_send_json_success([
            'message' => PPV_Lang::t('signup_success'),
            'user_id' => (int)$user_id,
            'user_type' => $user_type,
            'user_token' => $token,
            'redirect' => $redirect_url
        ]);
    }

    /** ============================================================
     * 🔹 AJAX Google Signup Handler
     * ============================================================ */
    public static function ajax_google_signup() {
        error_log("🔹 [PPV_Signup] Google signup called");

        global $wpdb;
        $prefix = $wpdb->prefix;

        self::ensure_session();

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ppv_signup_nonce')) {
            error_log("❌ [PPV_Signup] Google signup - nonce failed");
            wp_send_json_error(['message' => PPV_Lang::t('signup_google_error')]);
            return;
        }

        $credential = sanitize_text_field($_POST['credential'] ?? '');
        $user_type = sanitize_text_field($_POST['user_type'] ?? 'user'); // NEW!

        if (empty($credential)) {
            wp_send_json_error(['message' => PPV_Lang::t('signup_google_error')]);
            return;
        }

        $payload = self::verify_google_token($credential);

        if (!$payload) {
            wp_send_json_error(['message' => PPV_Lang::t('signup_google_error')]);
            return;
        }

        $email = sanitize_email($payload['email'] ?? '');
        $google_id = sanitize_text_field($payload['sub'] ?? '');
        $first_name = sanitize_text_field($payload['given_name'] ?? '');
        $last_name = sanitize_text_field($payload['family_name'] ?? '');
        $picture = esc_url_raw($payload['picture'] ?? '');

        if (empty($email) || empty($google_id)) {
            wp_send_json_error(['message' => PPV_Lang::t('signup_google_error')]);
            return;
        }

        // Check if user exists
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_users WHERE email=%s LIMIT 1",
            $email
        ));

        // Create new user if doesn't exist
        if (!$user) {
            $qr_token = wp_generate_password(10, false, false);

            $insert_data = [
                'email' => $email,
                'password' => password_hash(wp_generate_password(32), PASSWORD_DEFAULT),
                'first_name' => $first_name,
                'last_name' => $last_name,
                'google_id' => $google_id,
                'avatar' => $picture,
                'qr_token' => $qr_token,
                'user_type' => $user_type,
                'active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];

            $insert_format = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'];

            $insert_result = $wpdb->insert("{$prefix}ppv_users", $insert_data, $insert_format);

            if ($insert_result === false) {
                error_log("❌ [PPV_Signup] Google user insert failed: " . $wpdb->last_error);
                wp_send_json_error(['message' => PPV_Lang::t('signup_error_create')]);
                return;
            }

            $user_id = $wpdb->insert_id;
            error_log("✅ [PPV_Signup] Google user created: #{$user_id} ({$user_type})");

            // HÄNDLER: Create store
            if ($user_type === 'vendor') {
                $pos_token = md5(uniqid('pos_', true));
                $trial_ends_at = date('Y-m-d H:i:s', strtotime('+30 days'));

                $store_result = $wpdb->insert(
                    "{$prefix}ppv_stores",
                    [
                        'user_id' => $user_id,
                        'email' => $email,
                        'name' => trim($first_name . ' ' . $last_name) ?: 'Mein Geschäft',
                        'pos_token' => $pos_token,
                        'trial_ends_at' => $trial_ends_at,
                        'subscription_status' => 'trial',
                        'active' => 1,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ],
                    ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
                );

                if ($store_result !== false) {
                    $store_id = $wpdb->insert_id;

                    $wpdb->update(
                        "{$prefix}ppv_users",
                        ['vendor_store_id' => $store_id],
                        ['id' => $user_id],
                        ['%d'],
                        ['%d']
                    );

                    error_log("✅ [PPV_Signup] Store created for Google user: #{$store_id}");
                }
            }

            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_users WHERE id=%d LIMIT 1",
                $user_id
            ));
        } else {
            if (empty($user->google_id)) {
                $wpdb->update(
                    "{$prefix}ppv_users",
                    ['google_id' => $google_id],
                    ['id' => $user->id],
                    ['%s'],
                    ['%d']
                );
            }
        }

        // Set session
        $_SESSION['ppv_user_id'] = $user->id;
        $_SESSION['ppv_user_type'] = $user->user_type;
        $_SESSION['ppv_user_email'] = $user->email;

        if ($user->user_type === 'vendor' && !empty($user->vendor_store_id)) {
            $_SESSION['ppv_vendor_store_id'] = $user->vendor_store_id;
            $_SESSION['ppv_store_id'] = $user->vendor_store_id;
            $_SESSION['ppv_active_store'] = $user->vendor_store_id;
        }

        $GLOBALS['ppv_role'] = $user->user_type;

        // Generate token
        $token = md5(uniqid('ppv_user_google_', true));
        $wpdb->update(
            "{$prefix}ppv_users",
            ['login_token' => $token],
            ['id' => $user->id],
            ['%s'],
            ['%d']
        );

        // Set cookie
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        setcookie('ppv_user_token', $token, time() + (86400 * 180), '/', $domain, true, true);

        // Determine redirect
        $redirect_url = $user->user_type === 'vendor' ? home_url('/qr-center') : home_url('/user_dashboard');

        error_log("✅ [PPV_Signup] Google signup success: #{$user->id} ({$user->user_type})");

        wp_send_json_success([
            'message' => PPV_Lang::t('signup_google_success'),
            'user_id' => (int)$user->id,
            'user_type' => $user->user_type,
            'user_token' => $token,
            'redirect' => $redirect_url
        ]);
    }

    /** ============================================================
     * 🔹 Verify Google JWT Token
     * ============================================================ */
    private static function verify_google_token($credential) {
        $client_id = defined('PPV_GOOGLE_CLIENT_ID')
            ? PPV_GOOGLE_CLIENT_ID
            : get_option('ppv_google_client_id', '453567547051-odmqrinafba8ls8ktp9snlp7d2fpl9q0.apps.googleusercontent.com');

        if (empty($client_id)) {
            return false;
        }

        $parts = explode('.', $credential);
        if (count($parts) !== 3) {
            return false;
        }

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

        if (!$payload) {
            return false;
        }

        if (!isset($payload['aud']) || $payload['aud'] !== $client_id) {
            return false;
        }

        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }

        return $payload;
    }

    /** ============================================================
     * 🔹 AJAX Subscription Renewal Request
     * ============================================================ */
    public static function ajax_request_subscription_renewal() {
        global $wpdb;

        // Check if user is logged in
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['ppv_user_id'])) {
            wp_send_json_error(['message' => 'Nicht eingeloggt']);
            return;
        }

        $user_id = intval($_SESSION['ppv_user_id']);
        $store_id = intval($_SESSION['ppv_store_id'] ?? 0);

        // ✅ If no store_id in session, lookup via user_id
        if ($store_id === 0) {
            error_log("🔍 [PPV_Renewal] No store_id in session, looking up via user_id={$user_id}");

            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));

            if (!$store_id) {
                error_log("❌ [PPV_Renewal] No store found for user_id={$user_id}");
                wp_send_json_error(['message' => 'Store nicht gefunden']);
                return;
            }

            $store_id = intval($store_id);
            error_log("✅ [PPV_Renewal] Found store_id={$store_id} via user_id");
        }

        $phone = sanitize_text_field($_POST['phone'] ?? '');

        if (empty($phone)) {
            wp_send_json_error(['message' => 'Telefonnummer ist erforderlich']);
            return;
        }

        // Get store data
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT email, name, company_name FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));

        if (!$store) {
            wp_send_json_error(['message' => 'Store nicht gefunden']);
            return;
        }

        // Update store with renewal request
        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            [
                'subscription_renewal_requested' => current_time('mysql'),
                'renewal_phone' => $phone
            ],
            ['id' => $store_id],
            ['%s', '%s'],
            ['%d']
        );

        // Send email to admin
        $to = 'info@punktepass.de';
        $subject = '📧 Neue Abo-Verlängerungsanfrage - ' . ($store->company_name ?: $store->name);

        $message = "Eine neue Abo-Verlängerungsanfrage ist eingegangen:\n\n";
        $message .= "Store ID: {$store_id}\n";
        $message .= "Firma: " . ($store->company_name ?: $store->name) . "\n";
        $message .= "E-Mail: {$store->email}\n";
        $message .= "Telefon: {$phone}\n";
        $message .= "Zeitpunkt: " . current_time('Y-m-d H:i:s') . "\n\n";
        $message .= "Bitte kontaktieren Sie den Handler schnellstmöglich.\n";

        $headers = [
            'From: PunktePass System <noreply@punktepass.de>',
            'Reply-To: ' . $store->email,
            'Content-Type: text/plain; charset=UTF-8'
        ];

        $mail_sent = wp_mail($to, $subject, $message, $headers);

        if (!$mail_sent) {
            error_log("❌ [PPV_Renewal] Failed to send email to {$to} for store #{$store_id}");
        } else {
            error_log("✅ [PPV_Renewal] Email sent to {$to} for store #{$store_id}");
        }

        error_log("✅ [PPV_Renewal] Request submitted - Store #{$store_id} | Phone: {$phone}");

        wp_send_json_success(['message' => 'Anfrage erfolgreich gesendet']);
    }

    /* ============================================================
     * 🆘 AJAX Submit Support Ticket
     * ============================================================ */
    public static function ajax_submit_support_ticket() {
        global $wpdb;

        // Check if user is logged in
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['ppv_user_id'])) {
            wp_send_json_error(['message' => 'Nicht eingeloggt']);
            return;
        }

        $user_id = intval($_SESSION['ppv_user_id']);
        $store_id = intval($_SESSION['ppv_store_id'] ?? 0);

        // ✅ If no store_id in session, lookup via user_id
        if ($store_id === 0) {
            error_log("🔍 [PPV_Support] No store_id in session, looking up via user_id={$user_id}");

            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));

            if (!$store_id) {
                error_log("❌ [PPV_Support] No store found for user_id={$user_id}");
                wp_send_json_error(['message' => 'Store nicht gefunden']);
                return;
            }

            $store_id = intval($store_id);
            error_log("✅ [PPV_Support] Found store_id={$store_id} via user_id");
        }

        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $priority = sanitize_text_field($_POST['priority'] ?? 'normal');
        $contact_preference = sanitize_text_field($_POST['contact_preference'] ?? 'email');
        $page_url = esc_url_raw($_POST['page_url'] ?? '');

        if (empty($description)) {
            wp_send_json_error(['message' => 'Problembeschreibung ist erforderlich']);
            return;
        }

        // Validate priority
        if (!in_array($priority, ['low', 'normal', 'urgent'])) {
            $priority = 'normal';
        }

        // Validate contact preference
        if (!in_array($contact_preference, ['email', 'phone', 'whatsapp'])) {
            $contact_preference = 'email';
        }

        // Get store data
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT email, phone, name, company_name FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));

        if (!$store) {
            wp_send_json_error(['message' => 'Store nicht gefunden']);
            return;
        }

        // Insert support ticket
        $insert_result = $wpdb->insert(
            "{$wpdb->prefix}ppv_support_tickets",
            [
                'store_id' => $store_id,
                'handler_email' => $store->email,
                'handler_phone' => $store->phone ?: '',
                'store_name' => $store->company_name ?: $store->name,
                'subject' => null, // Auto-generated from description
                'description' => $description,
                'priority' => $priority,
                'status' => 'new',
                'contact_preference' => $contact_preference,
                'page_url' => $page_url,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$insert_result) {
            error_log("❌ [PPV_Support] Failed to insert ticket: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Fehler beim Speichern des Tickets']);
            return;
        }

        $ticket_id = $wpdb->insert_id;

        // Priority emoji and text
        $priority_map = [
            'low' => '🟢 Niedrig',
            'normal' => '🟡 Normal',
            'urgent' => '🔴 Dringend'
        ];
        $priority_text = $priority_map[$priority] ?? '🟡 Normal';

        // Contact preference text
        $contact_map = [
            'email' => '📧 E-Mail',
            'phone' => '📞 Telefon',
            'whatsapp' => '💬 WhatsApp'
        ];
        $contact_text = $contact_map[$contact_preference] ?? '📧 E-Mail';

        // Send email to admin
        $to = 'info@punktepass.de';
        $subject = '🆘 Neues Support-Ticket #' . $ticket_id . ' - ' . ($store->company_name ?: $store->name);

        $message = "Ein neues Support-Ticket ist eingegangen:\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🎫 Ticket ID: #{$ticket_id}\n";
        $message .= "📊 Priorität: {$priority_text}\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "🏪 Store Information:\n";
        $message .= "   • Store ID: {$store_id}\n";
        $message .= "   • Firma: " . ($store->company_name ?: $store->name) . "\n";
        $message .= "   • E-Mail: {$store->email}\n";
        $message .= "   • Telefon: " . ($store->phone ?: 'N/A') . "\n\n";
        $message .= "📞 Bevorzugter Kontakt: {$contact_text}\n\n";
        $message .= "📝 Problembeschreibung:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= $description . "\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "🌐 Seite: {$page_url}\n";
        $message .= "🕐 Zeitpunkt: " . current_time('Y-m-d H:i:s') . "\n\n";
        $message .= "Bitte kontaktieren Sie den Handler schnellstmöglich.\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "PunktePass Support System\n";

        $headers = [
            'From: PunktePass Support <noreply@punktepass.de>',
            'Reply-To: ' . $store->email,
            'Content-Type: text/plain; charset=UTF-8'
        ];

        $mail_sent = wp_mail($to, $subject, $message, $headers);

        if (!$mail_sent) {
            error_log("❌ [PPV_Support] Failed to send email to {$to} for ticket #{$ticket_id}");
        } else {
            error_log("✅ [PPV_Support] Email sent to {$to} for ticket #{$ticket_id}");
        }

        error_log("✅ [PPV_Support] Ticket #{$ticket_id} created - Store #{$store_id} | Priority: {$priority}");

        wp_send_json_success(['message' => '✅ Ticket erfolgreich gesendet! Wir melden uns schnellstmöglich.']);
    }

    /* ============================================================
     * 👥 AJAX Create Scanner User
     * ============================================================ */
    public static function ajax_create_scanner_user() {
        global $wpdb;

        // Check if handler is logged in
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['ppv_user_id'])) {
            wp_send_json_error(['message' => 'Nicht eingeloggt']);
            return;
        }

        $handler_user_id = intval($_SESSION['ppv_user_id']);
        $handler_store_id = intval($_SESSION['ppv_store_id'] ?? 0);

        // Get store_id if missing
        if ($handler_store_id === 0) {
            $handler_store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $handler_user_id
            ));

            if (!$handler_store_id) {
                wp_send_json_error(['message' => 'Store nicht gefunden']);
                return;
            }

            $handler_store_id = intval($handler_store_id);
        }

        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => 'E-Mail und Passwort sind erforderlich']);
            return;
        }

        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Ungültige E-Mail-Adresse']);
            return;
        }

        // Check if email already exists
        if (email_exists($email)) {
            wp_send_json_error(['message' => 'Diese E-Mail ist bereits registriert']);
            return;
        }

        // Create WordPress user
        $user_id = wp_create_user($email, $password, $email);

        if (is_wp_error($user_id)) {
            error_log("❌ [PPV_Scanner] Failed to create user: " . $user_id->get_error_message());
            wp_send_json_error(['message' => 'Fehler beim Erstellen des Benutzers: ' . $user_id->get_error_message()]);
            return;
        }

        // Get user object and set role
        $user = new WP_User($user_id);
        $user->set_role('ppv_scanner');

        // Link scanner to handler's store
        update_user_meta($user_id, 'ppv_scanner_store_id', $handler_store_id);

        error_log("✅ [PPV_Scanner] Scanner user created: ID={$user_id}, Email={$email}, Store={$handler_store_id}");

        wp_send_json_success([
            'message' => '✅ Scanner Benutzer erfolgreich erstellt!',
            'user_id' => $user_id,
            'email' => $email
        ]);
    }

    /* ============================================================
     * 🔄 AJAX Reset Scanner Password
     * ============================================================ */
    public static function ajax_reset_scanner_password() {
        global $wpdb;

        // Check if handler is logged in
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['ppv_user_id'])) {
            wp_send_json_error(['message' => 'Nicht eingeloggt']);
            return;
        }

        $scanner_user_id = intval($_POST['user_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';

        if (!$scanner_user_id || empty($new_password)) {
            wp_send_json_error(['message' => 'Benutzer-ID und neues Passwort sind erforderlich']);
            return;
        }

        // Verify scanner belongs to this handler
        $handler_user_id = intval($_SESSION['ppv_user_id']);
        $handler_store_id = intval($_SESSION['ppv_store_id'] ?? 0);

        if ($handler_store_id === 0) {
            $handler_store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $handler_user_id
            ));
        }

        $scanner_store_id = get_user_meta($scanner_user_id, 'ppv_scanner_store_id', true);

        if ($scanner_store_id != $handler_store_id) {
            wp_send_json_error(['message' => 'Keine Berechtigung für diesen Benutzer']);
            return;
        }

        // Reset password
        wp_set_password($new_password, $scanner_user_id);

        $user = get_userdata($scanner_user_id);

        error_log("✅ [PPV_Scanner] Password reset for scanner: ID={$scanner_user_id}, Email={$user->user_email}");

        wp_send_json_success([
            'message' => '✅ Passwort erfolgreich zurückgesetzt!',
            'email' => $user->user_email
        ]);
    }

    /* ============================================================
     * 🚫 AJAX Toggle Scanner Status
     * ============================================================ */
    public static function ajax_toggle_scanner_status() {
        global $wpdb;

        // Check if handler is logged in
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['ppv_user_id'])) {
            wp_send_json_error(['message' => 'Nicht eingeloggt']);
            return;
        }

        $scanner_user_id = intval($_POST['user_id'] ?? 0);
        $action = sanitize_text_field($_POST['action_type'] ?? '');

        if (!$scanner_user_id || !in_array($action, ['enable', 'disable'])) {
            wp_send_json_error(['message' => 'Ungültige Parameter']);
            return;
        }

        // Verify scanner belongs to this handler
        $handler_user_id = intval($_SESSION['ppv_user_id']);
        $handler_store_id = intval($_SESSION['ppv_store_id'] ?? 0);

        if ($handler_store_id === 0) {
            $handler_store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $handler_user_id
            ));
        }

        $scanner_store_id = get_user_meta($scanner_user_id, 'ppv_scanner_store_id', true);

        if ($scanner_store_id != $handler_store_id) {
            wp_send_json_error(['message' => 'Keine Berechtigung für diesen Benutzer']);
            return;
        }

        // Update user status (WordPress: 0 = active, 1 = blocked)
        $new_status = $action === 'disable' ? 1 : 0;

        $wpdb->update(
            $wpdb->users,
            ['user_status' => $new_status],
            ['ID' => $scanner_user_id],
            ['%d'],
            ['%d']
        );

        $user = get_userdata($scanner_user_id);
        $status_text = $action === 'disable' ? 'deaktiviert' : 'aktiviert';

        error_log("✅ [PPV_Scanner] Scanner {$status_text}: ID={$scanner_user_id}, Email={$user->user_email}");

        wp_send_json_success([
            'message' => "✅ Scanner erfolgreich {$status_text}!",
            'new_status' => $action === 'enable' ? 'active' : 'disabled'
        ]);
    }
}

// ⚠️ CRITICAL: Initialize the class!
add_action('init', ['PPV_Signup', 'init'], 1);

// Register AJAX handlers for renewal request
add_action('wp_ajax_ppv_request_subscription_renewal', ['PPV_Signup', 'ajax_request_subscription_renewal']);
add_action('wp_ajax_nopriv_ppv_request_subscription_renewal', ['PPV_Signup', 'ajax_request_subscription_renewal']);

// Register AJAX handlers for support tickets
add_action('wp_ajax_ppv_submit_support_ticket', ['PPV_Signup', 'ajax_submit_support_ticket']);
add_action('wp_ajax_nopriv_ppv_submit_support_ticket', ['PPV_Signup', 'ajax_submit_support_ticket']);

// Register AJAX handlers for scanner user management
add_action('wp_ajax_ppv_create_scanner_user', ['PPV_Signup', 'ajax_create_scanner_user']);
add_action('wp_ajax_ppv_reset_scanner_password', ['PPV_Signup', 'ajax_reset_scanner_password']);
add_action('wp_ajax_ppv_toggle_scanner_status', ['PPV_Signup', 'ajax_toggle_scanner_status']);

PPV_Signup::hooks();
