<?php
/**
 * PunktePass - Landing Page + Login System
 * Modern Hero Section with Features + Login Card
 * Multi-language Support (DE/HU/RO)
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Login {
    
    /** ============================================================
     * 🔹 Hooks
     * ============================================================ */
    public static function hooks() {
        add_shortcode('ppv_login_form', [__CLASS__, 'render_landing_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_nopriv_ppv_login', [__CLASS__, 'ajax_login']);
        add_action('wp_ajax_nopriv_ppv_google_login', [__CLASS__, 'ajax_google_login']);
        add_action('wp_ajax_nopriv_ppv_facebook_login', [__CLASS__, 'ajax_facebook_login']);
        add_action('wp_ajax_nopriv_ppv_tiktok_login', [__CLASS__, 'ajax_tiktok_login']);
        add_action('template_redirect', [__CLASS__, 'check_already_logged_in'], 1);
    }
    
    /** ============================================================
     * 🔹 Get Current Language (Cookie > GET > Locale)
     * ============================================================ */
    private static function get_current_lang() {
        static $lang = null;
        if ($lang !== null) return $lang;

        // 1. Check cookie
        if (isset($_COOKIE['ppv_lang'])) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang']);
        }
        // 2. Check GET parameter
        elseif (isset($_GET['lang'])) {
            $lang = sanitize_text_field($_GET['lang']);
        }
        // 3. Fallback to locale
        else {
            $lang = substr(get_locale(), 0, 2);
        }

        // Validate (only allow de, hu, ro)
        if (!in_array($lang, ['de', 'hu', 'ro'])) {
            $lang = 'de';
        }

        return $lang;
    }
    
    /** ============================================================
     * 🔹 Ensure Session Started (POS-Safe)
     * ============================================================ */
    private static function ensure_session() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $is_pos = (str_contains($uri, 'pos-admin') || 
                   (isset($_POST['action']) && str_contains($_POST['action'], 'ppv_pos_')));
        
        if ($is_pos) {
            error_log("🚫 [PPV_Login] POS context – session skipped");
            return;
        }
        
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $domain = str_replace('www.', '', $_SERVER['HTTP_HOST'] ?? 'punktepass.de');
            session_set_cookie_params([
                'lifetime' => 86400 * 180,  // 180 days (was 1 day - caused logout!)
                'path'     => '/',
                'domain'   => $domain,
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            @session_start();
            error_log("✅ [PPV_Login] Session started (ID=" . session_id() . ")");
        }
    }
    
    /** ============================================================
     * 🔹 Asset Loading
     * ============================================================ */
    public static function enqueue_assets() {
        // Only load on login page
        if (!is_page(['login', 'bejelentkezes', 'anmelden'])) {
            global $post;
            if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ppv_login')) {
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
        
        // Landing/Login CSS
        wp_enqueue_style(
            'ppv-login',
            PPV_PLUGIN_URL . 'assets/css/ppv-login-light.css',
            [],
            PPV_VERSION . '.' . time()
        );
        
        // Login JS
        wp_enqueue_script(
            'ppv-login',
            PPV_PLUGIN_URL . 'assets/js/ppv-login.js',
            ['jquery'],
            PPV_VERSION . '.' . time(),
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
        
        // Get OAuth credentials
        $facebook_app_id = defined('PPV_FACEBOOK_APP_ID') ? PPV_FACEBOOK_APP_ID : get_option('ppv_facebook_app_id', '');
        $google_client_id = defined('PPV_GOOGLE_CLIENT_ID') ? PPV_GOOGLE_CLIENT_ID : get_option('ppv_google_client_id', '453567547051-odmqrinafba8ls8ktp9snlp7d2fpl9q0.apps.googleusercontent.com');
        $tiktok_client_key = defined('PPV_TIKTOK_CLIENT_KEY') ? PPV_TIKTOK_CLIENT_KEY : get_option('ppv_tiktok_client_key', '');

        // Debug log - SHOW ACTUAL VALUES
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('PPV Login - Facebook constant defined: ' . (defined('PPV_FACEBOOK_APP_ID') ? 'YES' : 'NO'));
            error_log('PPV Login - Facebook constant value: "' . (defined('PPV_FACEBOOK_APP_ID') ? PPV_FACEBOOK_APP_ID : 'N/A') . '"');
            error_log('PPV Login - Facebook final value: "' . $facebook_app_id . '"');
            error_log('PPV Login - Facebook length: ' . strlen($facebook_app_id));
            error_log('PPV Login - Facebook empty: ' . (empty($facebook_app_id) ? 'YES' : 'NO'));
        }

        // Localize
        wp_localize_script('ppv-login', 'ppvLogin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ppv_login_nonce'),
            'google_client_id' => $google_client_id,
            'facebook_app_id' => $facebook_app_id,
            'tiktok_client_key' => $tiktok_client_key,
            'redirect_url' => home_url('/user_dashboard')
        ]);
    }
    
    /** ============================================================
 * 🔐 Check if already logged in - EARLY REDIRECT
 * ============================================================ */
public static function check_already_logged_in() {
    // Only on login page
    if (!is_page(['login', 'bejelentkezes', 'anmelden'])) {
        global $post;
        if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ppv_login_form')) {
            return;
        }
    }
    
    // 🔐 USER already logged in
    if (!empty($_SESSION['ppv_user_id']) && $_SESSION['ppv_user_type'] === 'user') {
        error_log("🔄 [PPV_Login] User redirect from login page");
        wp_safe_redirect(home_url('/user_dashboard'));
        exit;
    }
    
    // 🏪 HANDLER/STORE already logged in
    if (!empty($_SESSION['ppv_store_id']) && $_SESSION['ppv_user_type'] === 'store') {
        error_log("🔄 [PPV_Login] Store redirect from login page");
        wp_safe_redirect(home_url('/qr-center'));
        exit;
    }
    
    // Check cookies too
    if (!empty($_COOKIE['ppv_user_token'])) {
        error_log("🔄 [PPV_Login] User token cookie - redirect");
        wp_safe_redirect(home_url('/user_dashboard'));
        exit;
    }
    
    if (!empty($_COOKIE['ppv_pos_token'])) {
        error_log("🔄 [PPV_Login] POS token cookie - redirect");
        wp_safe_redirect(home_url('/qr-center'));
        exit;
    }
}
    
public static function render_landing_page($atts) {
        self::ensure_session();
        
         
        
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
                    <p class="ppv-slogan"><?php echo PPV_Lang::t('landing_slogan'); ?></p>
                    <!-- Language Switcher -->
                    <?php if (class_exists('PPV_Lang_Switcher')): ?>
                    <div class="ppv-lang-switcher">
                        <?php 
                        $current_lang = self::get_current_lang();
                        $langs = ['de' => 'DE', 'hu' => 'HU', 'ro' => 'RO'];
                            foreach ($langs as $code => $label):
                                $active = $current_lang === $code ? 'active' : '';
                            ?>
                                <button 
                                    type="button" 
                                    class="ppv-lang-btn <?php echo $active; ?>" 
                                    data-lang="<?php echo $code; ?>"
                                    <?php if ($active) echo 'aria-current="true"'; ?>
                                >
                                    <?php echo $label; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
                
                <div class="ppv-hero-content">
                    <!-- Left: Features -->
                    <div class="ppv-features-section">
                        <div class="ppv-features-grid">
                            <!-- Feature 1: QR Code -->
                            <div class="ppv-feature-card">
                                <div class="ppv-feature-icon">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="7" height="7"/>
                                        <rect x="14" y="3" width="7" height="7"/>
                                        <rect x="14" y="14" width="7" height="7"/>
                                        <rect x="3" y="14" width="7" height="7"/>
                                    </svg>
                                </div>
                                <h3><?php echo PPV_Lang::t('landing_feature_qr_title'); ?></h3>
                                <p><?php echo PPV_Lang::t('landing_feature_qr_desc'); ?></p>
                            </div>
                            
                            <!-- Feature 2: Collect Points -->
                            <div class="ppv-feature-card">
                                <div class="ppv-feature-icon">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="8" r="6"/>
                                        <polyline points="8 12 10 14 12 16 14 14 16 12"/>
                                        <path d="M12 14v8"/>
                                    </svg>
                                </div>
                                <h3><?php echo PPV_Lang::t('landing_feature_collect_title'); ?></h3>
                                <p><?php echo PPV_Lang::t('landing_feature_collect_desc'); ?></p>
                            </div>
                            
                            <!-- Feature 3: Get Rewards -->
                            <div class="ppv-feature-card">
                                <div class="ppv-feature-icon">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6"/>
                                        <path d="M12 3v9"/>
                                        <path d="m16 7-4-4-4 4"/>
                                        <rect x="4" y="12" width="16" height="2"/>
                                    </svg>
                                </div>
                                <h3><?php echo PPV_Lang::t('landing_feature_rewards_title'); ?></h3>
                                <p><?php echo PPV_Lang::t('landing_feature_rewards_desc'); ?></p>
                            </div>
                            
                            <!-- Feature 4: Local Offers -->
                            <div class="ppv-feature-card">
                                <div class="ppv-feature-icon">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                </div>
                                <h3><?php echo PPV_Lang::t('landing_feature_local_title'); ?></h3>
                                <p><?php echo PPV_Lang::t('landing_feature_local_desc'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right: Login Card -->
                    <div class="ppv-login-section">
                        <div class="ppv-login-card">
                            <!-- Welcome Text -->
                            <div class="ppv-login-welcome">
                                <h2><?php echo PPV_Lang::t('login_welcome'); ?></h2>
                                <p><?php echo PPV_Lang::t('login_welcome_desc'); ?></p>
                            </div>
                            
                            <!-- Social Login Buttons -->
                            <div class="ppv-social-login-grid">
                                <!-- Google Login Button -->
                                <button type="button" id="ppv-google-login-btn" class="ppv-social-btn ppv-google-btn">
                                    <svg width="20" height="20" viewBox="0 0 48 48">
                                        <path d="M47.532 24.5528C47.532 22.9214 47.3997 21.2811 47.1175 19.6761H24.48V28.9181H37.4434C36.9055 31.8988 35.177 34.5356 32.6461 36.2111V42.2078H40.3801C44.9217 38.0278 47.532 31.8547 47.532 24.5528Z" fill="#4285F4"/>
                                        <path d="M24.48 48.0016C30.9529 48.0016 36.4116 45.8764 40.3888 42.2078L32.6549 36.2111C30.5031 37.675 27.7252 38.5039 24.4888 38.5039C18.2275 38.5039 12.9187 34.2798 11.0139 28.6006H3.03296V34.7825C7.10718 42.8868 15.4056 48.0016 24.48 48.0016Z" fill="#34A853"/>
                                        <path d="M11.0051 28.6006C9.99973 25.6199 9.99973 22.3922 11.0051 19.4115V13.2296H3.03298C-0.371021 20.0112 -0.371021 28.0009 3.03298 34.7825L11.0051 28.6006Z" fill="#FBBC04"/>
                                        <path d="M24.48 9.49932C27.9016 9.44641 31.2086 10.7339 33.6866 13.0973L40.5387 6.24523C36.2 2.17101 30.4414 -0.068932 24.48 0.00161733C15.4055 0.00161733 7.10718 5.11644 3.03296 13.2296L11.005 19.4115C12.901 13.7235 18.2187 9.49932 24.48 9.49932Z" fill="#EA4335"/>
                                    </svg>
                                    <span>Google</span>
                                </button>

                                <!-- Facebook Login Button -->
                                <button type="button" id="ppv-facebook-login-btn" class="ppv-social-btn ppv-facebook-btn">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                        <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047v-2.66c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.971H15.83c-1.49 0-1.955.93-1.955 1.886v2.264h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z" fill="#1877F2"/>
                                    </svg>
                                    <span>Facebook</span>
                                </button>

                                <!-- TikTok Login Button -->
                                <button type="button" id="ppv-tiktok-login-btn" class="ppv-social-btn ppv-tiktok-btn">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                        <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#000000"/>
                                        <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#EE1D52"/>
                                        <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#69C9D0"/>
                                    </svg>
                                    <span>TikTok</span>
                                </button>
                            </div>

                            <!-- Divider -->
                            <div class="ppv-login-divider">
                                <span><?php echo PPV_Lang::t('login_or_email'); ?></span>
                            </div>
                            
                            <!-- Login Form -->
                            <form id="ppv-login-form" class="ppv-login-form" autocomplete="off">
                                <div class="ppv-form-group">
                                    <label for="ppv-email">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                            <polyline points="22,6 12,13 2,6"/>
                                        </svg>
                                        <?php echo PPV_Lang::t('login_email_label'); ?>
                                    </label>
                                    <input 
                                        type="email" 
                                        id="ppv-email" 
                                        name="email" 
                                        placeholder="<?php echo PPV_Lang::t('login_email_placeholder'); ?>"
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
                                        <?php echo PPV_Lang::t('login_password_label'); ?>
                                    </label>
                                    <div class="ppv-password-wrapper">
                                        <input 
                                            type="password" 
                                            id="ppv-password" 
                                            name="password" 
                                            placeholder="<?php echo PPV_Lang::t('login_password_placeholder'); ?>"
                                            autocomplete="current-password"
                                            required
                                        >
                                        <button type="button" class="ppv-password-toggle" aria-label="<?php echo PPV_Lang::t('login_show_password'); ?>">
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
                                </div>
                                
                                <div class="ppv-form-footer">
                                    <label class="ppv-checkbox">
                                        <input type="checkbox" name="remember" id="ppv-remember">
                                        <span><?php echo PPV_Lang::t('login_remember_me'); ?></span>
                                    </label>
                                    <a href="/passwort-vergessen" class="ppv-forgot-link"><?php echo PPV_Lang::t('login_forgot_password'); ?></a>
                                </div>
                                
                                <button type="submit" class="ppv-submit-btn" id="ppv-submit-btn">
                                    <span class="ppv-btn-text"><?php echo PPV_Lang::t('login_button'); ?></span>
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
                            <div id="ppv-login-alert" class="ppv-alert" style="display:none;"></div>
                            
                            <!-- Register Link -->
                            <div class="ppv-register-link">
                                <p><?php echo PPV_Lang::t('login_no_account'); ?> <a href="/signup"><?php echo PPV_Lang::t('login_register_now'); ?></a></p>
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

        <!-- Service Worker Registration (Login Page) -->
        <script>
        if ('serviceWorker' in navigator) {
          window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
              .then(reg => console.log('✅ [Login] SW registered:', reg.scope))
              .catch(err => console.error('❌ [Login] SW error:', err));
          });
        }
        </script>

        <?php
        return ob_get_clean();
    }
    
    /** ============================================================
     * 🔹 AJAX Login Handler (PPV Custom Users + Session)
     * ============================================================ */
    public static function ajax_login() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        self::ensure_session();
        
        check_ajax_referer('ppv_login_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === 'true';
        
        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => PPV_Lang::t('login_error_empty')]);
        }
        
        // 🔹 USER LOGIN (PPV Custom Table)
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_users WHERE email=%s LIMIT 1", 
            $email
        ));
        
        if ($user && password_verify($password, $user->password)) {
            // 🔒 Security: Regenerate session ID to prevent session fixation
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            // Clear any store session
            unset($_SESSION['ppv_store_id'], $_SESSION['ppv_active_store'], $_SESSION['ppv_is_pos']);

            // Set user session
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'user';
            $_SESSION['ppv_user_email'] = $user->email;
            
            $GLOBALS['ppv_role'] = 'user';
            
            // Generate token
            $token = md5(uniqid('ppv_user_', true));
            $wpdb->update("{$prefix}ppv_users", ['login_token' => $token], ['id' => $user->id]);
            
            // Set cookie
            $domain = $_SERVER['HTTP_HOST'] ?? '';
            $expire = $remember ? time() + (86400 * 180) : time() + 86400;
            setcookie('ppv_user_token', $token, $expire, '/', $domain, true, true);
            
            error_log("✅ [PPV_Login] User logged in (#{$user->id})");
            
            wp_send_json_success([
                'message' => PPV_Lang::t('login_success'),
                'role' => 'user',
                'user_id' => (int)$user->id,
                'user_token' => $token,
                'redirect' => home_url('/user_dashboard')
            ]);
        }
        
        // 🔹 STORE/HANDLER LOGIN
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_stores WHERE email=%s AND active=1 LIMIT 1", 
            $email
        ));
        
        if ($store && password_verify($password, $store->password)) {
            error_log("✅ [PPV_Login] Handler match: Store={$store->id}");
            
            // ============================================================
            // 🆕 CREATE VENDOR USER (if not exists)
            // ============================================================
            $vendor_user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_users WHERE email=%s AND user_type='vendor' LIMIT 1",
                $store->email
            ));
            
            if (!$vendor_user) {
                error_log("🆕 [PPV_Login] Creating vendor user...");
                
                // Generate QR token
                do {
                    $qr_token = substr(md5(uniqid(mt_rand(), true)), 0, 8);
                    $check = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$prefix}ppv_users WHERE qr_token=%s LIMIT 1",
                        $qr_token
                    ));
                } while ($check);
                
                // Insert vendor user (NO PASSWORD!)
                $wpdb->insert(
                    "{$prefix}ppv_users",
                    [
                        'email' => $store->email,
                        'password' => '',
                        'first_name' => 'Handler',
                        'last_name' => $store->name ?: '',
                        'user_type' => 'vendor',
                        'vendor_store_id' => $store->id,
                        'qr_token' => $qr_token,
                        'active' => 1
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d']
                );
                
                $user_id = $wpdb->insert_id;
                error_log("✅ [PPV_Login] Vendor user created: ID={$user_id}, QR={$qr_token}");
            } else {
                $user_id = $vendor_user->id;
                error_log("📝 [PPV_Login] Vendor user exists: ID={$user_id}");
            }
            
            // ============================================================
            // 🔑 SET HANDLER SESSION
            // ============================================================
            // 🔒 Security: Regenerate session ID to prevent session fixation
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            $_SESSION['ppv_user_id'] = $user_id;
            $_SESSION['ppv_user_type'] = 'store';
            $_SESSION['ppv_store_id'] = $store->id;
            $_SESSION['ppv_active_store'] = $store->id;
            $_SESSION['ppv_vendor_store_id'] = $store->id;
            $_SESSION['ppv_user_email'] = $store->email;
            
            error_log("✅ [PPV_Login] SESSION: user_id={$user_id}, type=store, store={$store->id}");
            
            $GLOBALS['ppv_role'] = 'handler';
            
            // POS token if enabled
            if (!empty($store->pos_enabled) && intval($store->pos_enabled) === 1) {
                if (empty($store->pos_token)) {
                    $store->pos_token = md5(uniqid('ppv_pos_', true));
                    $wpdb->update("{$prefix}ppv_stores", ['pos_token' => $store->pos_token], ['id' => $store->id]);
                }
                $_SESSION['ppv_is_pos'] = true;
                $_SESSION['ppv_pos_token'] = $store->pos_token;
                
                $domain = $_SERVER['HTTP_HOST'] ?? '';
                setcookie('ppv_pos_token', $store->pos_token, time() + (86400 * 180), '/', $domain, true, true);
                
                error_log("💾 [PPV_Login] POS enabled for store #{$store->id}");
            }
            
            // Generate token
            $token = md5(uniqid('ppv_handler_', true));
            $wpdb->update("{$prefix}ppv_users", ['login_token' => $token], ['id' => $user_id]);
            
            // Set cookie
            $domain = $_SERVER['HTTP_HOST'] ?? '';
            setcookie('ppv_user_token', $token, time() + (86400 * 180), '/', $domain, true, true);
            
            error_log("✅ [PPV_Login] Handler login success!");
            
            wp_send_json_success([
                'message' => PPV_Lang::t('login_success'),
                'role' => 'handler',
                'store_id' => (int)$store->id,
                'user_id' => (int)$user_id,
                'redirect' => home_url('/qr-center')
            ]);
        }
        
        // 🔹 LOGIN FAILED
        error_log("❌ [PPV_Login] Failed login attempt for: {$email}");
        wp_send_json_error(['message' => PPV_Lang::t('login_error_invalid')]);
    }
/** ============================================================
     * 🔹 AJAX Google Login Handler (PPV Custom Users)
     * ============================================================ */
    public static function ajax_google_login() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        self::ensure_session();
        
        check_ajax_referer('ppv_login_nonce', 'nonce');
        
        $credential = sanitize_text_field($_POST['credential'] ?? '');
        
        if (empty($credential)) {
            wp_send_json_error(['message' => PPV_Lang::t('login_google_error')]);
        }
        
        // Verify Google JWT token
        $payload = self::verify_google_token($credential);
        
        if (!$payload) {
            wp_send_json_error(['message' => PPV_Lang::t('login_google_error')]);
        }
        
        $email = sanitize_email($payload['email'] ?? '');
        $google_id = sanitize_text_field($payload['sub'] ?? '');
        $first_name = sanitize_text_field($payload['given_name'] ?? '');
        $last_name = sanitize_text_field($payload['family_name'] ?? '');
        
        if (empty($email) || empty($google_id)) {
            wp_send_json_error(['message' => PPV_Lang::t('login_google_error')]);
        }
        
        // 🔍 Check if user exists in PPV table
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_users WHERE email=%s LIMIT 1",
            $email
        ));
        
        // 🆕 Create new user if doesn't exist
        if (!$user) {
            $insert_result = $wpdb->insert(
                "{$prefix}ppv_users",
                [
                    'email' => $email,
                    'password' => password_hash(wp_generate_password(32), PASSWORD_DEFAULT),
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'google_id' => $google_id,
                    'created_at' => current_time('mysql'),
                    'active' => 1
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%d']
            );
            
            if ($insert_result === false) {
                error_log("❌ [PPV_Login] Failed to create Google user: {$email}");
                wp_send_json_error(['message' => PPV_Lang::t('login_user_create_error')]);
            }
            
            $user_id = $wpdb->insert_id;
            error_log("✅ [PPV_Login] New Google user created (#{$user_id}): {$email}");
            
            // Fetch the newly created user
            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_users WHERE id=%d LIMIT 1",
                $user_id
            ));
        } else {
            // Update Google ID if missing
            if (empty($user->google_id)) {
                $wpdb->update(
                    "{$prefix}ppv_users",
                    ['google_id' => $google_id],
                    ['id' => $user->id],
                    ['%s'],
                    ['%d']
                );
                error_log("✅ [PPV_Login] Google ID updated for user #{$user->id}");
            }
        }
        
        // 🔐 Log user in (Session + Token)
        // 🔒 Security: Regenerate session ID to prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        unset($_SESSION['ppv_store_id'], $_SESSION['ppv_active_store'], $_SESSION['ppv_is_pos']);

        $_SESSION['ppv_user_id'] = $user->id;
        $_SESSION['ppv_user_type'] = 'user';
        $_SESSION['ppv_user_email'] = $user->email;
        
        $GLOBALS['ppv_role'] = 'user';
        
        // Generate token
        $token = md5(uniqid('ppv_user_google_', true));
        $wpdb->update(
            "{$prefix}ppv_users",
            ['login_token' => $token],
            ['id' => $user->id],
            ['%s'],
            ['%d']
        );
        
        // Set cookie (180 days for Google login)
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        setcookie('ppv_user_token', $token, time() + (86400 * 180), '/', $domain, true, true);
        
        error_log("✅ [PPV_Login] Google login successful (#{$user->id}): {$email}");
        
        wp_send_json_success([
            'message' => PPV_Lang::t('login_google_success'),
            'role' => 'user',
            'user_id' => (int)$user->id,
            'user_token' => $token,
            'redirect' => home_url('/user_dashboard')
        ]);
    }
    
    /** ============================================================
     * 🔹 Verify Google JWT Token
     * ============================================================ */
  private static function verify_google_token($credential) {
        // Check wp-config.php define first, then options table
        $client_id = defined('PPV_GOOGLE_CLIENT_ID') ? PPV_GOOGLE_CLIENT_ID : get_option('ppv_google_client_id', '453567547051-odmqrinafba8ls8ktp9snlp7d2fpl9q0.apps.googleusercontent.com');
        
        error_log("🔍 [PPV_Google] Starting token verification");
        
        if (empty($client_id)) {
            return false;
        }
        
        // Decode JWT
        $parts = explode('.', $credential);
        if (count($parts) !== 3) {
            return false;
        }
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        
        // Verify audience
        if (!isset($payload['aud']) || $payload['aud'] !== $client_id) {
            return false;
        }
        
        // Verify expiry
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }

    /** ============================================================
     * 🔹 AJAX Facebook Login Handler
     * ============================================================ */
    public static function ajax_facebook_login() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        self::ensure_session();

        check_ajax_referer('ppv_login_nonce', 'nonce');

        $access_token = sanitize_text_field($_POST['access_token'] ?? '');

        if (empty($access_token)) {
            wp_send_json_error(['message' => 'Facebook Login fehlgeschlagen']);
        }

        // Verify Facebook token and get user data
        $fb_data = self::verify_facebook_token($access_token);

        if (!$fb_data) {
            wp_send_json_error(['message' => 'Facebook Token ungültig']);
        }

        $email = sanitize_email($fb_data['email'] ?? '');
        $facebook_id = sanitize_text_field($fb_data['id'] ?? '');
        $first_name = sanitize_text_field($fb_data['first_name'] ?? '');
        $last_name = sanitize_text_field($fb_data['last_name'] ?? '');

        if (empty($email) || empty($facebook_id)) {
            wp_send_json_error(['message' => 'Facebook Daten unvollständig']);
        }

        // Check if user exists
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_users WHERE email=%s OR facebook_id=%s LIMIT 1",
            $email, $facebook_id
        ));

        // Create new user if doesn't exist
        if (!$user) {
            $insert_result = $wpdb->insert(
                "{$prefix}ppv_users",
                [
                    'email' => $email,
                    'password' => password_hash(wp_generate_password(32), PASSWORD_DEFAULT),
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'facebook_id' => $facebook_id,
                    'created_at' => current_time('mysql'),
                    'active' => 1
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%d']
            );

            if ($insert_result === false) {
                error_log("❌ [PPV_Login] Failed to create Facebook user: {$email}");
                wp_send_json_error(['message' => 'Benutzer konnte nicht erstellt werden']);
            }

            $user_id = $wpdb->insert_id;
            error_log("✅ [PPV_Login] New Facebook user created (#{$user_id}): {$email}");

            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_users WHERE id=%d LIMIT 1",
                $user_id
            ));
        } else {
            // Update Facebook ID if missing
            if (empty($user->facebook_id)) {
                $wpdb->update(
                    "{$prefix}ppv_users",
                    ['facebook_id' => $facebook_id],
                    ['id' => $user->id],
                    ['%s'],
                    ['%d']
                );
                error_log("✅ [PPV_Login] Facebook ID updated for user #{$user->id}");
            }
        }

        // Log user in
        // 🔒 Security: Regenerate session ID to prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        unset($_SESSION['ppv_store_id'], $_SESSION['ppv_active_store'], $_SESSION['ppv_is_pos']);

        $_SESSION['ppv_user_id'] = $user->id;
        $_SESSION['ppv_user_type'] = 'user';
        $_SESSION['ppv_user_email'] = $user->email;

        $GLOBALS['ppv_role'] = 'user';

        // Generate token
        $token = md5(uniqid('ppv_user_fb_', true));
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

        error_log("✅ [PPV_Login] Facebook login successful (#{$user->id}): {$email}");

        wp_send_json_success([
            'message' => 'Erfolgreich angemeldet!',
            'role' => 'user',
            'user_id' => (int)$user->id,
            'user_token' => $token,
            'redirect' => home_url('/user_dashboard')
        ]);
    }

    /** ============================================================
     * 🔹 AJAX TikTok Login Handler
     * ============================================================ */
    public static function ajax_tiktok_login() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        self::ensure_session();

        check_ajax_referer('ppv_login_nonce', 'nonce');

        $code = sanitize_text_field($_POST['code'] ?? '');

        if (empty($code)) {
            wp_send_json_error(['message' => 'TikTok Login fehlgeschlagen']);
        }

        // Exchange code for access token and get user data
        $tiktok_data = self::get_tiktok_user_data($code);

        if (!$tiktok_data) {
            wp_send_json_error(['message' => 'TikTok Authentifizierung fehlgeschlagen']);
        }

        $email = sanitize_email($tiktok_data['email'] ?? '');
        $tiktok_id = sanitize_text_field($tiktok_data['open_id'] ?? '');
        $display_name = sanitize_text_field($tiktok_data['display_name'] ?? '');

        // Split display name into first/last
        $name_parts = explode(' ', $display_name, 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $name_parts[1] ?? '';

        if (empty($tiktok_id)) {
            wp_send_json_error(['message' => 'TikTok Daten unvollständig']);
        }

        // If no email, generate placeholder
        if (empty($email)) {
            $email = "tiktok_{$tiktok_id}@punktepass.placeholder";
        }

        // Check if user exists
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_users WHERE tiktok_id=%s LIMIT 1",
            $tiktok_id
        ));

        // Create new user if doesn't exist
        if (!$user) {
            $insert_result = $wpdb->insert(
                "{$prefix}ppv_users",
                [
                    'email' => $email,
                    'password' => password_hash(wp_generate_password(32), PASSWORD_DEFAULT),
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'tiktok_id' => $tiktok_id,
                    'created_at' => current_time('mysql'),
                    'active' => 1
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%d']
            );

            if ($insert_result === false) {
                error_log("❌ [PPV_Login] Failed to create TikTok user: {$tiktok_id}");
                wp_send_json_error(['message' => 'Benutzer konnte nicht erstellt werden']);
            }

            $user_id = $wpdb->insert_id;
            error_log("✅ [PPV_Login] New TikTok user created (#{$user_id}): {$tiktok_id}");

            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_users WHERE id=%d LIMIT 1",
                $user_id
            ));
        }

        // Log user in
        // 🔒 Security: Regenerate session ID to prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        unset($_SESSION['ppv_store_id'], $_SESSION['ppv_active_store'], $_SESSION['ppv_is_pos']);

        $_SESSION['ppv_user_id'] = $user->id;
        $_SESSION['ppv_user_type'] = 'user';
        $_SESSION['ppv_user_email'] = $user->email;

        $GLOBALS['ppv_role'] = 'user';

        // Generate token
        $token = md5(uniqid('ppv_user_tt_', true));
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

        error_log("✅ [PPV_Login] TikTok login successful (#{$user->id}): {$tiktok_id}");

        wp_send_json_success([
            'message' => 'Erfolgreich angemeldet!',
            'role' => 'user',
            'user_id' => (int)$user->id,
            'user_token' => $token,
            'redirect' => home_url('/user_dashboard')
        ]);
    }

    /** ============================================================
     * 🔹 Verify Facebook Access Token
     * ============================================================ */
    private static function verify_facebook_token($access_token) {
        $app_id = defined('PPV_FACEBOOK_APP_ID') ? PPV_FACEBOOK_APP_ID : get_option('ppv_facebook_app_id', '');
        $app_secret = defined('PPV_FACEBOOK_APP_SECRET') ? PPV_FACEBOOK_APP_SECRET : get_option('ppv_facebook_app_secret', '');

        if (empty($app_id) || empty($app_secret)) {
            error_log("❌ [PPV_Facebook] App ID or Secret not configured");
            return false;
        }

        // Verify token with Facebook
        $verify_url = "https://graph.facebook.com/debug_token?input_token={$access_token}&access_token={$app_id}|{$app_secret}";
        $verify_response = wp_remote_get($verify_url);

        if (is_wp_error($verify_response)) {
            error_log("❌ [PPV_Facebook] Token verification failed: " . $verify_response->get_error_message());
            return false;
        }

        $verify_data = json_decode(wp_remote_retrieve_body($verify_response), true);

        if (!isset($verify_data['data']['is_valid']) || !$verify_data['data']['is_valid']) {
            error_log("❌ [PPV_Facebook] Invalid token");
            return false;
        }

        // Get user data
        $user_url = "https://graph.facebook.com/me?fields=id,email,first_name,last_name&access_token={$access_token}";
        $user_response = wp_remote_get($user_url);

        if (is_wp_error($user_response)) {
            error_log("❌ [PPV_Facebook] User data fetch failed: " . $user_response->get_error_message());
            return false;
        }

        $user_data = json_decode(wp_remote_retrieve_body($user_response), true);

        return $user_data;
    }

    /** ============================================================
     * 🔹 Get TikTok User Data from Authorization Code
     * ============================================================ */
    private static function get_tiktok_user_data($code) {
        $client_key = defined('PPV_TIKTOK_CLIENT_KEY') ? PPV_TIKTOK_CLIENT_KEY : get_option('ppv_tiktok_client_key', '');
        $client_secret = defined('PPV_TIKTOK_CLIENT_SECRET') ? PPV_TIKTOK_CLIENT_SECRET : get_option('ppv_tiktok_client_secret', '');
        $redirect_uri = home_url('/login');

        if (empty($client_key) || empty($client_secret)) {
            error_log("❌ [PPV_TikTok] Client Key or Secret not configured");
            return false;
        }

        // Exchange code for access token
        $token_url = 'https://open-api.tiktok.com/oauth/access_token/';
        $token_response = wp_remote_post($token_url, [
            'body' => [
                'client_key' => $client_key,
                'client_secret' => $client_secret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirect_uri
            ]
        ]);

        if (is_wp_error($token_response)) {
            error_log("❌ [PPV_TikTok] Token exchange failed: " . $token_response->get_error_message());
            return false;
        }

        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);

        if (!isset($token_data['data']['access_token'])) {
            error_log("❌ [PPV_TikTok] No access token in response");
            return false;
        }

        $access_token = $token_data['data']['access_token'];
        $open_id = $token_data['data']['open_id'];

        // Get user info
        $user_url = 'https://open-api.tiktok.com/user/info/';
        $user_response = wp_remote_post($user_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ],
            'body' => [
                'open_id' => $open_id,
                'fields' => 'open_id,union_id,display_name'
            ]
        ]);

        if (is_wp_error($user_response)) {
            error_log("❌ [PPV_TikTok] User info fetch failed: " . $user_response->get_error_message());
            return false;
        }

        $user_data = json_decode(wp_remote_retrieve_body($user_response), true);

        if (!isset($user_data['data']['user'])) {
            error_log("❌ [PPV_TikTok] No user data in response");
            return false;
        }

        return $user_data['data']['user'];
    }
}

// Initialize
PPV_Login::hooks();
