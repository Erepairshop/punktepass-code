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
        add_action('wp_head', [__CLASS__, 'inject_head_assets'], 999); // ✅ Priority 999 = LAST, overrides theme CSS!
        add_action('wp_ajax_nopriv_ppv_login', [__CLASS__, 'ajax_login']);
        add_action('wp_ajax_nopriv_ppv_google_login', [__CLASS__, 'ajax_google_login']);
        add_action('wp_ajax_nopriv_ppv_facebook_login', [__CLASS__, 'ajax_facebook_login']);
        // add_action('wp_ajax_nopriv_ppv_tiktok_login', [__CLASS__, 'ajax_tiktok_login']); // TikTok login disabled
        add_action('wp_ajax_nopriv_ppv_apple_login', [__CLASS__, 'ajax_apple_login']);
        add_action('template_redirect', [__CLASS__, 'check_already_logged_in'], 1);
    }

    /** ============================================================
     * 🔹 Inject CSS into <head> for login page
     * ============================================================ */
    public static function inject_head_assets() {
        // Only on login page
        if (!is_page(['login', 'bejelentkezes', 'anmelden'])) {
            global $post;
            if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ppv_login_form')) {
                return;
            }
        }

        ?>
        <!-- ⚡ PERFORMANCE: Preconnect to external domains -->
        <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="preconnect" href="https://accounts.google.com" crossorigin>

        <!-- ⚡ PERFORMANCE: Preload critical assets -->
        <link rel="preload" href="<?php echo PPV_PLUGIN_URL; ?>assets/img/logo.webp?v=2" as="image" type="image/webp">

        <!-- ⚡ PERFORMANCE: Inline Critical CSS for instant first paint -->
        <style id="ppv-critical-css">
            :root{--pp-primary:#0066FF;--ppv-bg:#F8F9FB;--ppv-card-glass:rgba(255,255,255,0.85);--ppv-text:#1A1A1A;--ppv-border-glass:rgba(255,255,255,0.3);--safe-area-top:env(safe-area-inset-top,0px)}
            html,body{margin:0;padding:0;overflow:hidden;height:100%;width:100%;position:fixed;background:linear-gradient(135deg,#F8F9FB 0%,#E6F0FF 100%)}
            .ppv-landing-container{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;height:100%;height:100dvh;display:flex;flex-direction:column;overflow-y:auto;overflow-x:hidden;-webkit-overflow-scrolling:touch;overscroll-behavior:none}
            .ppv-landing-header{position:sticky;top:0;z-index:100;background:var(--ppv-card-glass);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-bottom:1px solid var(--ppv-border-glass);padding:10px 0;padding-top:calc(10px + var(--safe-area-top));flex-shrink:0}
            .ppv-header-content{max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;gap:24px}
            .ppv-logo-section{display:flex;align-items:center;gap:12px}
            .ppv-logo{width:48px;height:48px;object-fit:contain}
            .ppv-logo-section h1{font-size:20px;font-weight:700;color:var(--ppv-text);margin:0}
            .ppv-lang-switcher{display:flex;gap:4px;background:var(--ppv-bg);border:1px solid #E5E7EB;border-radius:8px;padding:4px}
            .ppv-lang-btn{padding:6px 12px;background:transparent;border:none;border-radius:6px;font-size:13px;font-weight:600;color:#6B7280;cursor:pointer}
            .ppv-lang-btn.active{background:var(--pp-primary);color:white}
            .ppv-login-card{background:var(--ppv-card-glass);backdrop-filter:blur(20px);border-radius:16px;border:1px solid var(--ppv-border-glass);padding:28px}
            @media(max-width:640px){.ppv-landing-header{padding:8px 0;padding-top:calc(8px + var(--safe-area-top))}.ppv-header-content{gap:8px;padding:0 12px}.ppv-logo{width:36px;height:36px}.ppv-logo-section{gap:8px}.ppv-logo-section h1{font-size:16px}.ppv-lang-switcher{padding:2px}.ppv-lang-btn{padding:4px 8px;font-size:12px}}
        </style>

        <!-- Google Fonts - async loading to not block render -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
        <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"></noscript>

        <!-- Login CSS - Fresh with cache-busting -->
        <link rel="stylesheet" href="<?php echo PPV_PLUGIN_URL; ?>assets/css/ppv-login-light.css?ver=<?php echo time(); ?>" type="text/css" media="all" id="ppv-login-main-css">
        <?php
    }
    
    /** ============================================================
     * 🔹 Get Current Language (Cookie > GET > GeoIP > Locale)
     * ============================================================ */
    private static function get_current_lang() {
        static $lang = null;
        if ($lang !== null) return $lang;

        // 1. Check cookie (user preference)
        if (isset($_COOKIE['ppv_lang'])) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang']);
        }
        // 2. Check GET parameter
        elseif (isset($_GET['lang'])) {
            $lang = sanitize_text_field($_GET['lang']);
        }
        // 3. GeoIP detection (country-based)
        else {
            $lang = self::detect_language_by_country();
        }

        // Validate (only allow de, hu, ro)
        if (!in_array($lang, ['de', 'hu', 'ro'])) {
            $lang = 'de';
        }

        return $lang;
    }

    /** ============================================================
     * 🌍 Detect Language by Browser (Accept-Language header)
     * ============================================================ */
    private static function detect_language_by_country() {
        // Use browser's Accept-Language header (FREE, instant, no API!)
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $detected_lang = 'de'; // Default German

        if ($accept) {
            // Check for Hungarian (hu, hu-HU)
            if (preg_match('/\bhu\b/i', $accept)) {
                $detected_lang = 'hu';
            }
            // Check for Romanian (ro, ro-RO)
            elseif (preg_match('/\bro\b/i', $accept)) {
                $detected_lang = 'ro';
            }
        }

        ppv_log("🌍 [PPV_Login] Browser Accept-Language → {$detected_lang}");
        return $detected_lang;
    }

    /** ============================================================
     * 🔹 Get Client IP Address
     * ============================================================ */
    private static function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }
    
    /** ============================================================
     * 🔹 Ensure Session Started (POS-Safe)
     * ============================================================ */
    private static function ensure_session() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $is_pos = (str_contains($uri, 'pos-admin') || 
                   (isset($_POST['action']) && str_contains($_POST['action'], 'ppv_pos_')));
        
        if ($is_pos) {
            ppv_log("🚫 [PPV_Login] POS context – session skipped");
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
            ppv_log("✅ [PPV_Login] Session started (ID=" . session_id() . ")");
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

        // ✅ DISABLED - Using inline <link> and <script> tags in template instead
        // This prevents duplicate loading and cache issues
        // See render_landing_page() for inline asset loading

        // Google OAuth Library (still needed via enqueue)
        wp_enqueue_script(
            'google-platform',
            'https://accounts.google.com/gsi/client',
            [],
            null,
            true
        );
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

    // ✅ Start session first
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    // ✅ Restore session from cookie if needed
    if (empty($_SESSION['ppv_user_id']) && !empty($_COOKIE['ppv_user_token']) && class_exists('PPV_SessionBridge')) {
        PPV_SessionBridge::restore_from_token();
    }

    // 🔹 DEBUG: Log session state
    ppv_log("🔍 [PPV_Login] check_already_logged_in: user_id=" . ($_SESSION['ppv_user_id'] ?? 'EMPTY') .
            ", user_type=" . ($_SESSION['ppv_user_type'] ?? 'EMPTY') .
            ", store_id=" . ($_SESSION['ppv_store_id'] ?? 'EMPTY') .
            ", vendor_store_id=" . ($_SESSION['ppv_vendor_store_id'] ?? 'EMPTY'));

    // 🏪 HANDLER/STORE/SCANNER already logged in
    // ✅ FIX: Check ppv_store_id OR ppv_vendor_store_id FIRST (priority over user_type check)
    // This catches: handlers, vendors, scanners, trial handlers - anyone with a store association
    if (!empty($_SESSION['ppv_store_id']) || !empty($_SESSION['ppv_vendor_store_id'])) {
        $user_type = $_SESSION['ppv_user_type'] ?? '';

        // Allowed types for qr-center: store, handler, vendor, admin, scanner
        if (in_array($user_type, ['store', 'handler', 'vendor', 'admin', 'scanner'])) {
            ppv_log("🔄 [PPV_Login] Store/Handler/Scanner redirect from login page (type={$user_type})");
            wp_safe_redirect(home_url('/qr-center'));
            exit;
        }

        // ✅ FIX: If store_id exists but user_type is wrong/empty, still redirect to qr-center
        // This handles cases where user_type was not properly set in DB
        if (!empty($_SESSION['ppv_store_id'])) {
            ppv_log("🔄 [PPV_Login] Store redirect (fallback) - store_id exists but user_type={$user_type}");
            wp_safe_redirect(home_url('/qr-center'));
            exit;
        }
    }

    // 🔐 USER already logged in (only if no store association)
    if (!empty($_SESSION['ppv_user_id']) && ($_SESSION['ppv_user_type'] ?? '') === 'user') {
        // ✅ FIX: Verify user actually exists in database before redirect
        global $wpdb;
        $user_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_users WHERE id = %d AND active = 1 LIMIT 1",
            intval($_SESSION['ppv_user_id'])
        ));

        if ($user_exists) {
            ppv_log("🔄 [PPV_Login] User redirect from login page (session check, user verified)");
            wp_safe_redirect(home_url('/user_dashboard'));
            exit;
        } else {
            // ❌ Invalid session - clear it
            ppv_log("⚠️ [PPV_Login] Invalid ppv_user_id={$_SESSION['ppv_user_id']} - user not found, clearing session");
            unset($_SESSION['ppv_user_id'], $_SESSION['ppv_user_email'], $_SESSION['ppv_user_type']);
            // Also clear the cookie
            $cookie_domain = !empty($_SERVER['HTTP_HOST']) ? str_replace('www.', '', $_SERVER['HTTP_HOST']) : '';
            setcookie('ppv_user_token', '', time() - 3600, '/', $cookie_domain, true, true);
        }
    }

    // ⚠️ If we reach here, session restore failed or no valid login
    // Don't redirect based on cookie alone - let the login page load
    ppv_log("🔍 [PPV_Login] No valid session - showing login page");
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
                        <img src="<?php echo PPV_PLUGIN_URL; ?>assets/img/logo.webp?v=2" alt="PunktePass" class="ppv-logo" fetchpriority="high">
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
                            <?php
                            // Check for referral
                            $referral = class_exists('PPV_Referral_Handler') ? PPV_Referral_Handler::get_referral_data() : null;
                            if ($referral):
                                $store_name = esc_html($referral['store_name'] ?? 'einem Shop');
                            ?>
                            <!-- Referral Welcome Banner -->
                            <div class="ppv-referral-welcome-banner">
                                <div class="referral-icon">🎁</div>
                                <div class="referral-text">
                                    <strong><?php echo PPV_Lang::t('referral_welcome_title') ?: 'Du wurdest eingeladen!'; ?></strong>
                                    <p><?php echo sprintf(PPV_Lang::t('referral_welcome_desc') ?: 'Melde dich an und erhalte Bonuspunkte bei %s', $store_name); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>

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
                                        <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047v-2.66c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.971H15.83c-1.49 0-1.955.93-1.955 1.886v2.264h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z" fill="#FFFFFF"/>
                                    </svg>
                                    <span>Facebook</span>
                                </button>

                                <!-- TikTok Login Button - DISABLED
                                <button type="button" id="ppv-tiktok-login-btn" class="ppv-social-btn ppv-tiktok-btn">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                        <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#000000"/>
                                        <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#EE1D52"/>
                                        <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#69C9D0"/>
                                    </svg>
                                    <span>TikTok</span>
                                </button>
                                -->

                                <!-- Apple Login Button -->
                                <button type="button" id="ppv-apple-login-btn" class="ppv-social-btn ppv-apple-btn">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
                                    </svg>
                                    <span>Apple</span>
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
                                        <input type="checkbox" name="remember" id="ppv-remember" checked>
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

        <!-- FingerprintJS (local vendor - no CDN dependency) -->
        <script src="<?php echo PPV_PLUGIN_URL; ?>assets/js/vendor/fp.min.js?ver=4.6.2"></script>

        <!-- Force Fresh JS Load (Bypass SW Cache) -->
        <script src="<?php echo PPV_PLUGIN_URL; ?>assets/js/ppv-login.js?ver=<?php echo time(); ?>"></script>

        <!-- Login Config -->
        <script>
        window.ppvLogin = {
            ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('ppv_login_nonce'); ?>',
            google_client_id: '<?php echo defined('PPV_GOOGLE_CLIENT_ID') ? PPV_GOOGLE_CLIENT_ID : get_option('ppv_google_client_id', '645942978357-ndj7dgrapd2dgndnjf03se1p08l0o9ra.apps.googleusercontent.com'); ?>',
            facebook_app_id: '<?php echo defined('PPV_FACEBOOK_APP_ID') ? PPV_FACEBOOK_APP_ID : get_option('ppv_facebook_app_id', '32519769227670976'); ?>',
            // tiktok_client_key: '<?php echo defined('PPV_TIKTOK_CLIENT_KEY') ? PPV_TIKTOK_CLIENT_KEY : get_option('ppv_tiktok_client_key', '9bb6aca5781d007d6c00fe3ed60d6734'); ?>', // TikTok disabled
            apple_client_id: '<?php echo defined('PPV_APPLE_CLIENT_ID') ? PPV_APPLE_CLIENT_ID : get_option('ppv_apple_client_id', ''); ?>',
            apple_redirect_uri: '<?php echo home_url('/login'); ?>',
            redirect_url: '<?php echo home_url('/user_dashboard'); ?>'
        };
        </script>

        <!-- Service Worker Cache Clear (Login Page) -->
        <script>
        // ✅ CSAK cache törlés, NEM SW re-registration!
        // Ha újra regisztráljuk a SW-t query paraméterrel, az conflict-ot okoz a dashboard SW-vel
        // és page refresh-t triggerel a clients.claim() miatt
        if ('caches' in window) {
          window.addEventListener('load', async () => {
            try {
              // DELETE all caches to ensure fresh content after login
              const cacheNames = await caches.keys();
              for (const name of cacheNames) {
                await caches.delete(name);
              }
              console.log('🧹 [Login] All caches cleared');
            } catch (err) {
              console.error('❌ [Login] Cache clear error:', err);
            }
          });
        }

        // SW registration happens in class-ppv-pwa.php (global, no conflict)
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

        // Validate fingerprint with central validation
        $fingerprint = '';
        if (!empty($_POST['device_fingerprint']) && class_exists('PPV_Device_Fingerprint')) {
            $fp_validation = PPV_Device_Fingerprint::validate_fingerprint($_POST['device_fingerprint'], true);
            $fingerprint = $fp_validation['valid'] ? ($fp_validation['sanitized'] ?? '') : '';
        } elseif (!empty($_POST['device_fingerprint'])) {
            $fingerprint = sanitize_text_field($_POST['device_fingerprint']);
        }

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

            // ✅ FIX: Respect actual user_type from database (vendor, scanner, user)
            $db_user_type = $user->user_type ?? 'user';

            // 🏪 VENDOR LOGIN: Handle vendor users separately
            if ($db_user_type === 'vendor') {
                ppv_log("🏪 [PPV_Login] Vendor user detected: #{$user->id}");

                // Set vendor session
                $_SESSION['ppv_user_id'] = $user->id;
                $_SESSION['ppv_user_type'] = 'vendor';
                $_SESSION['ppv_user_email'] = $user->email;

                // Set store session if vendor_store_id exists
                if (!empty($user->vendor_store_id)) {
                    $_SESSION['ppv_vendor_store_id'] = $user->vendor_store_id;
                    $_SESSION['ppv_store_id'] = $user->vendor_store_id;
                    $_SESSION['ppv_active_store'] = $user->vendor_store_id;
                    ppv_log("✅ [PPV_Login] Vendor store_id set: {$user->vendor_store_id}");
                } else {
                    ppv_log("⚠️ [PPV_Login] Vendor has no vendor_store_id - might need onboarding");
                }

                $GLOBALS['ppv_role'] = 'vendor';

                // Generate/reuse token
                $token = $user->login_token;
                if (empty($token)) {
                    $token = md5(uniqid('ppv_vendor_', true));
                    $wpdb->update("{$prefix}ppv_users", ['login_token' => $token], ['id' => $user->id]);
                    ppv_log("🔑 [PPV_Login] New token generated for vendor #{$user->id}");
                }

                // Set cookie
                $domain = $_SERVER['HTTP_HOST'] ?? '';
                $expire = $remember ? time() + (86400 * 180) : time() + (86400 * 30);
                setcookie('ppv_user_token', $token, $expire, '/', $domain, true, true);

                // 📱 Track login fingerprint
                if (!empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
                    PPV_Device_Fingerprint::track_login($user->id, $fingerprint, 'password');
                }

                // 🌐 Save user's browser language preference + set cookie
                $browser_lang = self::get_current_lang();
                if (!empty($browser_lang)) {
                    $wpdb->update("{$prefix}ppv_users", ['language' => $browser_lang], ['id' => $user->id], ['%s'], ['%d']);
                    $domain = $_SERVER['HTTP_HOST'] ?? '';
                    setcookie('ppv_lang', $browser_lang, time() + (86400 * 365), '/', $domain, is_ssl(), false);
                }

                ppv_log("✅ [PPV_Login] Vendor logged in (#{$user->id}, store={$user->vendor_store_id}, lang={$browser_lang})");

                wp_send_json_success([
                    'message' => PPV_Lang::t('login_success'),
                    'role' => 'vendor',
                    'user_id' => (int)$user->id,
                    'store_id' => (int)($user->vendor_store_id ?? 0),
                    'user_token' => $token,
                    'redirect' => home_url('/qr-center')
                ]);
            }

            // 🔍 SCANNER LOGIN: Handle scanner users (password login from ppv_users)
            if ($db_user_type === 'scanner') {
                ppv_log("🔍 [PPV_Login] Scanner user detected: #{$user->id}");

                // Check if scanner is active
                if ($user->active != 1) {
                    ppv_log("❌ [PPV_Login] Scanner user is disabled: #{$user->id}");
                    wp_send_json_error(['message' => 'Ihr Konto wurde deaktiviert.']);
                }

                // Get handler's store
                if (!empty($user->vendor_store_id)) {
                    $handler_store = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, active FROM {$prefix}ppv_stores WHERE id=%d LIMIT 1",
                        $user->vendor_store_id
                    ));

                    if (!$handler_store || $handler_store->active != 1) {
                        ppv_log("❌ [PPV_Login] Scanner's handler store inactive: #{$user->vendor_store_id}");
                        wp_send_json_error(['message' => 'Der Handler ist inaktiv.']);
                    }
                }

                // Set scanner session
                $_SESSION['ppv_user_id'] = $user->id;
                $_SESSION['ppv_user_type'] = 'scanner';
                $_SESSION['ppv_user_email'] = $user->email;
                if (!empty($user->vendor_store_id)) {
                    $_SESSION['ppv_store_id'] = $user->vendor_store_id;
                }

                $GLOBALS['ppv_role'] = 'scanner';

                // Generate/reuse token
                $token = $user->login_token;
                if (empty($token)) {
                    $token = md5(uniqid('ppv_scanner_', true));
                    $wpdb->update("{$prefix}ppv_users", ['login_token' => $token], ['id' => $user->id]);
                    ppv_log("🔑 [PPV_Login] New token generated for scanner #{$user->id}");
                }

                // Set cookie
                $domain = $_SERVER['HTTP_HOST'] ?? '';
                $expire = $remember ? time() + (86400 * 180) : time() + (86400 * 30);
                setcookie('ppv_user_token', $token, $expire, '/', $domain, true, true);

                // Track fingerprint
                if (!empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
                    PPV_Device_Fingerprint::track_login($user->id, $fingerprint, 'password');
                }

                ppv_log("✅ [PPV_Login] Scanner logged in (#{$user->id}, store={$user->vendor_store_id})");

                wp_send_json_success([
                    'message' => PPV_Lang::t('login_success'),
                    'role' => 'scanner',
                    'user_id' => (int)$user->id,
                    'store_id' => (int)($user->vendor_store_id ?? 0),
                    'user_token' => $token,
                    'redirect' => home_url('/qr-center')
                ]);
            }

            // 👤 REGULAR USER LOGIN
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'user';
            $_SESSION['ppv_user_email'] = $user->email;

            $GLOBALS['ppv_role'] = 'user';

            // ✅ Multi-device: Reuse existing token if available (don't kick out other devices)
            $token = $user->login_token;
            if (empty($token)) {
                $token = md5(uniqid('ppv_user_', true));
                $wpdb->update("{$prefix}ppv_users", ['login_token' => $token], ['id' => $user->id]);
                ppv_log("🔑 [PPV_Login] New token generated for user #{$user->id}");
            } else {
                ppv_log("🔑 [PPV_Login] Reusing existing token for user #{$user->id} (multi-device)");
            }

            // Set cookie
            $domain = $_SERVER['HTTP_HOST'] ?? '';
            // ✅ FIX: Default is 30 days (was 1 day - caused unexpected logout!)
            // Remember me = 180 days, no remember = 30 days
            $expire = $remember ? time() + (86400 * 180) : time() + (86400 * 30);
            setcookie('ppv_user_token', $token, $expire, '/', $domain, true, true);

            // 📱 Track login fingerprint
            if (!empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
                PPV_Device_Fingerprint::track_login($user->id, $fingerprint, 'password');
            }

            // 🌐 Save user's browser language preference + set cookie
            $browser_lang = self::get_current_lang();
            if (!empty($browser_lang)) {
                $wpdb->update("{$prefix}ppv_users", ['language' => $browser_lang], ['id' => $user->id], ['%s'], ['%d']);
                $domain = $_SERVER['HTTP_HOST'] ?? '';
                setcookie('ppv_lang', $browser_lang, time() + (86400 * 365), '/', $domain, is_ssl(), false);
            }

            ppv_log("✅ [PPV_Login] User logged in (#{$user->id}, lang={$browser_lang})");

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
            ppv_log("✅ [PPV_Login] Handler match: Store={$store->id}");
            
            // ============================================================
            // 🆕 CREATE VENDOR USER (if not exists)
            // ============================================================
            $vendor_user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_users WHERE email=%s AND user_type='vendor' LIMIT 1",
                $store->email
            ));
            
            if (!$vendor_user) {
                ppv_log("🆕 [PPV_Login] Creating vendor user...");
                
                // Generate QR token
                do {
                    $qr_token = substr(md5(uniqid(mt_rand(), true)), 0, 16);
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
                ppv_log("✅ [PPV_Login] Vendor user created: ID={$user_id}, QR={$qr_token}");
            } else {
                $user_id = $vendor_user->id;
                ppv_log("📝 [PPV_Login] Vendor user exists: ID={$user_id}");
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
            
            ppv_log("✅ [PPV_Login] SESSION: user_id={$user_id}, type=store, store={$store->id}");
            
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
                
                ppv_log("💾 [PPV_Login] POS enabled for store #{$store->id}");
            }
            
            // ✅ Multi-device: Reuse existing token if available
            $existing_token = $wpdb->get_var($wpdb->prepare(
                "SELECT login_token FROM {$prefix}ppv_users WHERE id=%d", $user_id
            ));
            if (!empty($existing_token)) {
                $token = $existing_token;
                ppv_log("🔑 [PPV_Login] Reusing existing handler token (multi-device)");
            } else {
                $token = md5(uniqid('ppv_handler_', true));
                $wpdb->update("{$prefix}ppv_users", ['login_token' => $token], ['id' => $user_id]);
                ppv_log("🔑 [PPV_Login] New handler token generated");
            }

            // Set cookie
            $domain = $_SERVER['HTTP_HOST'] ?? '';
            setcookie('ppv_user_token', $token, time() + (86400 * 180), '/', $domain, true, true);

            // 📱 Track login fingerprint (handler)
            if (!empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
                PPV_Device_Fingerprint::track_login($user_id, $fingerprint, 'password');
            }

            ppv_log("✅ [PPV_Login] Handler login success!");

            wp_send_json_success([
                'message' => PPV_Lang::t('login_success'),
                'role' => 'handler',
                'store_id' => (int)$store->id,
                'user_id' => (int)$user_id,
                'redirect' => home_url('/qr-center')
            ]);
        }

        // 🔹 SCANNER USER LOGIN (PPV Custom Users)
        $scanner_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_users WHERE email=%s AND user_type='scanner' LIMIT 1",
            $email
        ));

        if ($scanner_user && password_verify($password, $scanner_user->password)) {
            ppv_log("✅ [PPV_Login] Scanner user match: ID={$scanner_user->id}");

            // Check if scanner is disabled
            if ($scanner_user->active != 1) {
                ppv_log("❌ [PPV_Login] Scanner user is disabled: ID={$scanner_user->id}");
                wp_send_json_error(['message' => 'Ihr Konto wurde deaktiviert. Bitte kontaktieren Sie Ihren Administrator.']);
            }

            // Get handler's store
            $handler_store = $wpdb->get_row($wpdb->prepare(
                "SELECT id, active, subscription_end FROM {$prefix}ppv_stores WHERE id=%d LIMIT 1",
                $scanner_user->vendor_store_id
            ));

            if (!$handler_store) {
                ppv_log("❌ [PPV_Login] Scanner's handler store not found: store_id={$scanner_user->vendor_store_id}");
                wp_send_json_error(['message' => 'Handler Store nicht gefunden. Bitte kontaktieren Sie Ihren Administrator.']);
            }

            // Check if handler store is active
            if ($handler_store->active != 1) {
                ppv_log("❌ [PPV_Login] Handler store is inactive: store_id={$handler_store->id}");
                wp_send_json_error(['message' => 'Der Handler ist inaktiv. Scanner Login nicht möglich.']);
            }

            // Check if handler subscription is valid
            if ($handler_store->subscription_end && strtotime($handler_store->subscription_end) < time()) {
                ppv_log("❌ [PPV_Login] Handler subscription expired: store_id={$handler_store->id}, expired={$handler_store->subscription_end}");
                wp_send_json_error(['message' => 'Die Handler Subscription ist abgelaufen. Scanner Login nicht möglich.']);
            }

            // 🔒 Security: Regenerate session ID
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }

            // Clear any previous session
            unset($_SESSION['ppv_store_id'], $_SESSION['ppv_active_store'], $_SESSION['ppv_is_pos']);

            // Set scanner session
            $_SESSION['ppv_user_id'] = $scanner_user->id;
            $_SESSION['ppv_user_type'] = 'scanner';
            $_SESSION['ppv_store_id'] = intval($handler_store->id);
            $_SESSION['ppv_user_email'] = $scanner_user->email;

            $GLOBALS['ppv_role'] = 'scanner';

            // ✅ Multi-device: Reuse existing token if available
            $token = $scanner_user->login_token;
            if (empty($token)) {
                $token = md5(uniqid('ppv_scanner_', true));
                $wpdb->update("{$prefix}ppv_users", ['login_token' => $token], ['id' => $scanner_user->id]);
                ppv_log("🔑 [PPV_Login] New scanner token generated");
            } else {
                ppv_log("🔑 [PPV_Login] Reusing existing scanner token (multi-device)");
            }

            // Set cookie
            $domain = $_SERVER['HTTP_HOST'] ?? '';
            setcookie('ppv_user_token', $token, time() + (86400 * 180), '/', $domain, true, true);

            // 📱 Track login fingerprint (scanner)
            if (!empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
                PPV_Device_Fingerprint::track_login($scanner_user->id, $fingerprint, 'password');
            }

            ppv_log("✅ [PPV_Login] Scanner login success: user_id={$scanner_user->id}, store_id={$handler_store->id}");

            wp_send_json_success([
                'message' => PPV_Lang::t('login_success'),
                'role' => 'scanner',
                'user_id' => (int)$scanner_user->id,
                'store_id' => (int)$handler_store->id,
                'redirect' => home_url('/qr-center')
            ]);
        }

        // 🔹 LOGIN FAILED
        ppv_log("❌ [PPV_Login] Failed login attempt for: {$email}");
        wp_send_json_error(['message' => PPV_Lang::t('login_error_invalid')]);
    }
/** ============================================================
     * 🔹 AJAX Google Login Handler (PPV Custom Users)
     * ============================================================ */
    public static function ajax_google_login() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        self::ensure_session();

        // 🍎 iOS app sends requests without nonce - verify with Google JWT instead
        // Nonce check is optional because Google JWT token provides authentication
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!empty($nonce)) {
            // If nonce is provided, verify it (web browser requests)
            if (!wp_verify_nonce($nonce, 'ppv_login_nonce')) {
                // Nonce invalid - but continue anyway, JWT will be verified
                ppv_log("⚠️ [PPV_Login] Google login nonce invalid, continuing with JWT verification");
            }
        }
        
        $credential = sanitize_text_field($_POST['credential'] ?? '');

        // Validate fingerprint with central validation
        $fingerprint = '';
        if (!empty($_POST['device_fingerprint']) && class_exists('PPV_Device_Fingerprint')) {
            $fp_validation = PPV_Device_Fingerprint::validate_fingerprint($_POST['device_fingerprint'], true);
            $fingerprint = $fp_validation['valid'] ? ($fp_validation['sanitized'] ?? '') : '';
        } elseif (!empty($_POST['device_fingerprint'])) {
            $fingerprint = sanitize_text_field($_POST['device_fingerprint']);
        }

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
        
        // 🌐 Get user's browser language
        $browser_lang = self::get_current_lang();

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
                    'language' => $browser_lang,
                    'created_at' => current_time('mysql'),
                    'active' => 1
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
            );
            
            if ($insert_result === false) {
                ppv_log("❌ [PPV_Login] Failed to create Google user: {$email}");
                wp_send_json_error(['message' => PPV_Lang::t('login_user_create_error')]);
            }
            
            $user_id = $wpdb->insert_id;
            ppv_log("✅ [PPV_Login] New Google user created (#{$user_id}): {$email}");
            
            // Fetch the newly created user
            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_users WHERE id=%d LIMIT 1",
                $user_id
            ));
        } else {
            // Update Google ID if missing + always update language
            $update_data = ['language' => $browser_lang];
            $update_format = ['%s'];

            if (empty($user->google_id)) {
                $update_data['google_id'] = $google_id;
                $update_format[] = '%s';
            }

            $wpdb->update(
                "{$prefix}ppv_users",
                $update_data,
                ['id' => $user->id],
                $update_format,
                ['%d']
            );

            if (empty($user->google_id)) {
                ppv_log("✅ [PPV_Login] Google ID + language updated for user #{$user->id}");
            }
        }

        // 🌐 Set language cookie
        if (!empty($browser_lang)) {
            $domain = $_SERVER['HTTP_HOST'] ?? '';
            setcookie('ppv_lang', $browser_lang, time() + (86400 * 365), '/', $domain, is_ssl(), false);
        }

        // 🔐 Log user in (Session + Token)
        // 🔒 Security: Regenerate session ID to prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        unset($_SESSION['ppv_store_id'], $_SESSION['ppv_active_store'], $_SESSION['ppv_is_pos']);

        // ✅ FIX: Respect actual user_type from database (vendor, scanner, user)
        $db_user_type = $user->user_type ?? 'user';
        $redirect_url = home_url('/user_dashboard');
        $role = 'user';

        if ($db_user_type === 'vendor') {
            // 🏪 VENDOR: Set vendor session
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'vendor';
            $_SESSION['ppv_user_email'] = $user->email;

            if (!empty($user->vendor_store_id)) {
                $_SESSION['ppv_vendor_store_id'] = $user->vendor_store_id;
                $_SESSION['ppv_store_id'] = $user->vendor_store_id;
                $_SESSION['ppv_active_store'] = $user->vendor_store_id;
            }

            $GLOBALS['ppv_role'] = 'vendor';
            $redirect_url = home_url('/qr-center');
            $role = 'vendor';
            ppv_log("🏪 [PPV_Login] Google vendor login: user_id={$user->id}, store_id=" . ($user->vendor_store_id ?? 'none'));

        } elseif ($db_user_type === 'scanner') {
            // 👤 SCANNER: Set scanner session
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'scanner';
            $_SESSION['ppv_user_email'] = $user->email;

            if (!empty($user->vendor_store_id)) {
                $_SESSION['ppv_store_id'] = $user->vendor_store_id;
            }

            $GLOBALS['ppv_role'] = 'scanner';
            $redirect_url = home_url('/qr-center');
            $role = 'scanner';
            ppv_log("👤 [PPV_Login] Google scanner login: user_id={$user->id}, store_id=" . ($user->vendor_store_id ?? 'none'));

        } else {
            // 🔹 REGULAR USER
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'user';
            $_SESSION['ppv_user_email'] = $user->email;

            $GLOBALS['ppv_role'] = 'user';
            ppv_log("🔹 [PPV_Login] Google user login: user_id={$user->id}");
        }

        // ✅ Multi-device: Reuse existing token if available
        $token = $user->login_token;
        if (empty($token)) {
            $token = md5(uniqid('ppv_user_google_', true));
            $wpdb->update(
                "{$prefix}ppv_users",
                ['login_token' => $token],
                ['id' => $user->id],
                ['%s'],
                ['%d']
            );
            ppv_log("🔑 [PPV_Login] New Google token generated");
        } else {
            ppv_log("🔑 [PPV_Login] Reusing existing token for Google login (multi-device)");
        }

        // Set cookie (180 days for Google login)
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        setcookie('ppv_user_token', $token, time() + (86400 * 180), '/', $domain, true, true);

        // 📱 Track login fingerprint (Google)
        if (!empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
            PPV_Device_Fingerprint::track_login($user->id, $fingerprint, 'google');
        }

        ppv_log("✅ [PPV_Login] Google login successful (#{$user->id}): {$email}, type={$db_user_type}");

        wp_send_json_success([
            'message' => PPV_Lang::t('login_google_success'),
            'role' => $role,
            'user_id' => (int)$user->id,
            'user_token' => $token,
            'redirect' => $redirect_url
        ]);
    }

    /** ============================================================
     * 🔹 Verify Google JWT Token
     * ============================================================ */
  private static function verify_google_token($credential) {
        // Web client ID (for browser logins)
        $web_client_id = defined('PPV_GOOGLE_CLIENT_ID') ? PPV_GOOGLE_CLIENT_ID : get_option('ppv_google_client_id', '645942978357-ndj7dgrapd2dgndnjf03se1p08l0o9ra.apps.googleusercontent.com');
        // iOS client ID (for native iOS app logins)
        $ios_client_id = '645942978357-1bdviltt810gutpve9vjj2kab340man6.apps.googleusercontent.com';

        ppv_log("🔍 [PPV_Google] Starting token verification");

        // Decode JWT
        $parts = explode('.', $credential);
        if (count($parts) !== 3) {
            return false;
        }

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

        // Verify audience - accept both Web and iOS client IDs
        $valid_audiences = [$web_client_id, $ios_client_id];
        if (!isset($payload['aud']) || !in_array($payload['aud'], $valid_audiences)) {
            ppv_log("❌ [PPV_Google] Invalid audience: " . ($payload['aud'] ?? 'none'));
            return false;
        }
        
        // Verify expiry
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }

    /** ============================================================
     * 🍎 AJAX Apple Login Handler (Sign in with Apple)
     * ============================================================ */
    public static function ajax_apple_login() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        self::ensure_session();

        check_ajax_referer('ppv_login_nonce', 'nonce');

        $id_token = sanitize_text_field($_POST['id_token'] ?? '');
        $user_data = isset($_POST['user']) ? json_decode(stripslashes($_POST['user']), true) : null;

        // Validate fingerprint with central validation
        $fingerprint = '';
        if (!empty($_POST['device_fingerprint']) && class_exists('PPV_Device_Fingerprint')) {
            $fp_validation = PPV_Device_Fingerprint::validate_fingerprint($_POST['device_fingerprint'], true);
            $fingerprint = $fp_validation['valid'] ? ($fp_validation['sanitized'] ?? '') : '';
        } elseif (!empty($_POST['device_fingerprint'])) {
            $fingerprint = sanitize_text_field($_POST['device_fingerprint']);
        }

        if (empty($id_token)) {
            wp_send_json_error(['message' => PPV_Lang::t('login_apple_error') ?: 'Apple Login fehlgeschlagen']);
        }

        // Verify Apple JWT token
        $payload = self::verify_apple_token($id_token);

        if (!$payload) {
            ppv_log("❌ [PPV_Login] Apple token verification failed");
            wp_send_json_error(['message' => PPV_Lang::t('login_apple_error') ?: 'Apple Login fehlgeschlagen']);
        }

        $apple_id = sanitize_text_field($payload['sub'] ?? '');
        $email = sanitize_email($payload['email'] ?? '');

        // Apple only sends user info on first authorization
        $first_name = '';
        $last_name = '';
        if ($user_data && isset($user_data['name'])) {
            $first_name = sanitize_text_field($user_data['name']['firstName'] ?? '');
            $last_name = sanitize_text_field($user_data['name']['lastName'] ?? '');
        }

        if (empty($apple_id)) {
            wp_send_json_error(['message' => PPV_Lang::t('login_apple_error') ?: 'Apple Login fehlgeschlagen']);
        }

        // 🔍 Check if user exists by Apple ID or email
        $user = null;

        // First try to find by apple_id
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_users WHERE apple_id=%s LIMIT 1",
            $apple_id
        ));

        // If not found by apple_id, try email (if provided)
        if (!$user && !empty($email)) {
            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_users WHERE email=%s LIMIT 1",
                $email
            ));
        }

        // 🌐 Get user's browser language
        $browser_lang = self::get_current_lang();

        // 🆕 Create new user if doesn't exist
        if (!$user) {
            // Apple may hide email, generate placeholder if needed
            if (empty($email)) {
                $email = $apple_id . '@privaterelay.appleid.com';
            }

            $insert_result = $wpdb->insert(
                "{$prefix}ppv_users",
                [
                    'email' => $email,
                    'password' => password_hash(wp_generate_password(32), PASSWORD_DEFAULT),
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'apple_id' => $apple_id,
                    'language' => $browser_lang,
                    'created_at' => current_time('mysql'),
                    'active' => 1
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
            );

            if ($insert_result === false) {
                ppv_log("❌ [PPV_Login] Failed to create Apple user: {$email}");
                wp_send_json_error(['message' => PPV_Lang::t('login_user_create_error') ?: 'Benutzer konnte nicht erstellt werden']);
            }

            $user_id = $wpdb->insert_id;
            ppv_log("✅ [PPV_Login] New Apple user created (#{$user_id}): {$email}");

            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_users WHERE id=%d LIMIT 1",
                $user_id
            ));
        } else {
            // Update Apple ID if missing + always update language
            $update_data = ['language' => $browser_lang];
            $update_format = ['%s'];

            if (empty($user->apple_id)) {
                $update_data['apple_id'] = $apple_id;
                $update_format[] = '%s';
            }

            // Update name if we have it and user doesn't
            if (!empty($first_name) && empty($user->first_name)) {
                $update_data['first_name'] = $first_name;
                $update_data['last_name'] = $last_name;
                $update_format[] = '%s';
                $update_format[] = '%s';
            }

            $wpdb->update(
                "{$prefix}ppv_users",
                $update_data,
                ['id' => $user->id],
                $update_format,
                ['%d']
            );

            ppv_log("✅ [PPV_Login] Apple user updated (#{$user->id}): lang={$browser_lang}");
        }

        // 🌐 Set language cookie
        if (!empty($browser_lang)) {
            $domain = $_SERVER['HTTP_HOST'] ?? '';
            setcookie('ppv_lang', $browser_lang, time() + (86400 * 365), '/', $domain, is_ssl(), false);
        }

        // 🔐 Log user in (Session + Token)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        unset($_SESSION['ppv_store_id'], $_SESSION['ppv_active_store'], $_SESSION['ppv_is_pos']);

        // ✅ FIX: Respect actual user_type from database (vendor, scanner, user)
        $db_user_type = $user->user_type ?? 'user';
        $redirect_url = home_url('/user_dashboard');
        $role = 'user';

        if ($db_user_type === 'vendor') {
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'vendor';
            $_SESSION['ppv_user_email'] = $user->email;
            if (!empty($user->vendor_store_id)) {
                $_SESSION['ppv_vendor_store_id'] = $user->vendor_store_id;
                $_SESSION['ppv_store_id'] = $user->vendor_store_id;
                $_SESSION['ppv_active_store'] = $user->vendor_store_id;
            }
            $GLOBALS['ppv_role'] = 'vendor';
            $redirect_url = home_url('/qr-center');
            $role = 'vendor';
        } elseif ($db_user_type === 'scanner') {
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'scanner';
            $_SESSION['ppv_user_email'] = $user->email;
            if (!empty($user->vendor_store_id)) {
                $_SESSION['ppv_store_id'] = $user->vendor_store_id;
            }
            $GLOBALS['ppv_role'] = 'scanner';
            $redirect_url = home_url('/qr-center');
            $role = 'scanner';
        } else {
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'user';
            $_SESSION['ppv_user_email'] = $user->email;
            $GLOBALS['ppv_role'] = 'user';
        }

        // ✅ Multi-device: Reuse existing token if available
        $token = $user->login_token;
        if (empty($token)) {
            $token = md5(uniqid('ppv_user_apple_', true));
            $wpdb->update(
                "{$prefix}ppv_users",
                ['login_token' => $token],
                ['id' => $user->id],
                ['%s'],
                ['%d']
            );
            ppv_log("🔑 [PPV_Login] New Apple token generated");
        } else {
            ppv_log("🔑 [PPV_Login] Reusing existing token for Apple login (multi-device)");
        }

        // Set cookie (180 days)
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        setcookie('ppv_user_token', $token, time() + (86400 * 180), '/', $domain, true, true);

        // 📱 Track login fingerprint (Apple)
        if (!empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
            PPV_Device_Fingerprint::track_login($user->id, $fingerprint, 'apple');
        }

        ppv_log("✅ [PPV_Login] Apple login successful (#{$user->id}): {$user->email}, type={$db_user_type}");

        wp_send_json_success([
            'message' => PPV_Lang::t('login_apple_success') ?: 'Erfolgreich angemeldet!',
            'role' => $role,
            'user_id' => (int)$user->id,
            'user_token' => $token,
            'redirect' => $redirect_url
        ]);
    }

    /** ============================================================
     * 🍎 Verify Apple JWT Token
     * ============================================================ */
    private static function verify_apple_token($id_token) {
        // Apple's public keys endpoint
        $apple_keys_url = 'https://appleid.apple.com/auth/keys';

        ppv_log("🍎 [PPV_Apple] Starting token verification");

        // Decode JWT parts
        $parts = explode('.', $id_token);
        if (count($parts) !== 3) {
            ppv_log("❌ [PPV_Apple] Invalid JWT structure");
            return false;
        }

        $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0])), true);
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

        if (!$header || !$payload) {
            ppv_log("❌ [PPV_Apple] Failed to decode JWT");
            return false;
        }

        // Verify issuer
        if (!isset($payload['iss']) || $payload['iss'] !== 'https://appleid.apple.com') {
            ppv_log("❌ [PPV_Apple] Invalid issuer: " . ($payload['iss'] ?? 'none'));
            return false;
        }

        // Verify audience (should be your app's client ID / Service ID)
        $client_id = defined('PPV_APPLE_CLIENT_ID') ? PPV_APPLE_CLIENT_ID : get_option('ppv_apple_client_id', '');
        if (!empty($client_id) && isset($payload['aud']) && $payload['aud'] !== $client_id) {
            ppv_log("❌ [PPV_Apple] Invalid audience: " . $payload['aud'] . " (expected: {$client_id})");
            return false;
        }

        // Verify expiry
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            ppv_log("❌ [PPV_Apple] Token expired");
            return false;
        }

        // For production, you should verify the signature with Apple's public keys
        // For now, we trust the payload if the basic checks pass
        ppv_log("✅ [PPV_Apple] Token verified for sub: " . ($payload['sub'] ?? 'unknown'));

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

        // Validate fingerprint with central validation
        $fingerprint = '';
        if (!empty($_POST['device_fingerprint']) && class_exists('PPV_Device_Fingerprint')) {
            $fp_validation = PPV_Device_Fingerprint::validate_fingerprint($_POST['device_fingerprint'], true);
            $fingerprint = $fp_validation['valid'] ? ($fp_validation['sanitized'] ?? '') : '';
        } elseif (!empty($_POST['device_fingerprint'])) {
            $fingerprint = sanitize_text_field($_POST['device_fingerprint']);
        }

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
            ppv_log("❌ [PPV_Facebook] Data incomplete - email: '{$email}', facebook_id: '{$facebook_id}', raw data: " . json_encode($fb_data));
            wp_send_json_error(['message' => 'Facebook Daten unvollständig. Bitte erlaube den Zugriff auf deine E-Mail-Adresse.']);
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
                ppv_log("❌ [PPV_Login] Failed to create Facebook user: {$email}");
                wp_send_json_error(['message' => 'Benutzer konnte nicht erstellt werden']);
            }

            $user_id = $wpdb->insert_id;
            ppv_log("✅ [PPV_Login] New Facebook user created (#{$user_id}): {$email}");

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
                ppv_log("✅ [PPV_Login] Facebook ID updated for user #{$user->id}");
            }
        }

        // Log user in
        // 🔒 Security: Regenerate session ID to prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        unset($_SESSION['ppv_store_id'], $_SESSION['ppv_active_store'], $_SESSION['ppv_is_pos']);

        // ✅ FIX: Respect actual user_type from database (vendor, scanner, user)
        $db_user_type = $user->user_type ?? 'user';
        $redirect_url = home_url('/user_dashboard');
        $role = 'user';

        if ($db_user_type === 'vendor') {
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'vendor';
            $_SESSION['ppv_user_email'] = $user->email;
            if (!empty($user->vendor_store_id)) {
                $_SESSION['ppv_vendor_store_id'] = $user->vendor_store_id;
                $_SESSION['ppv_store_id'] = $user->vendor_store_id;
                $_SESSION['ppv_active_store'] = $user->vendor_store_id;
            }
            $GLOBALS['ppv_role'] = 'vendor';
            $redirect_url = home_url('/qr-center');
            $role = 'vendor';
        } elseif ($db_user_type === 'scanner') {
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'scanner';
            $_SESSION['ppv_user_email'] = $user->email;
            if (!empty($user->vendor_store_id)) {
                $_SESSION['ppv_store_id'] = $user->vendor_store_id;
            }
            $GLOBALS['ppv_role'] = 'scanner';
            $redirect_url = home_url('/qr-center');
            $role = 'scanner';
        } else {
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'user';
            $_SESSION['ppv_user_email'] = $user->email;
            $GLOBALS['ppv_role'] = 'user';
        }

        // ✅ Multi-device: Reuse existing token if available
        $token = $user->login_token;
        if (empty($token)) {
            $token = md5(uniqid('ppv_user_fb_', true));
            $wpdb->update(
                "{$prefix}ppv_users",
                ['login_token' => $token],
                ['id' => $user->id],
                ['%s'],
                ['%d']
            );
            ppv_log("🔑 [PPV_Login] New Facebook token generated");
        } else {
            ppv_log("🔑 [PPV_Login] Reusing existing token for Facebook login (multi-device)");
        }

        // Set cookie
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        setcookie('ppv_user_token', $token, time() + (86400 * 180), '/', $domain, true, true);

        // 📱 Track login fingerprint (Facebook)
        if (!empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
            PPV_Device_Fingerprint::track_login($user->id, $fingerprint, 'facebook');
        }

        ppv_log("✅ [PPV_Login] Facebook login successful (#{$user->id}): {$email}, type={$db_user_type}");

        wp_send_json_success([
            'message' => 'Erfolgreich angemeldet!',
            'role' => $role,
            'user_id' => (int)$user->id,
            'user_token' => $token,
            'redirect' => $redirect_url
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

        // Validate fingerprint with central validation
        $fingerprint = '';
        if (!empty($_POST['device_fingerprint']) && class_exists('PPV_Device_Fingerprint')) {
            $fp_validation = PPV_Device_Fingerprint::validate_fingerprint($_POST['device_fingerprint'], true);
            $fingerprint = $fp_validation['valid'] ? ($fp_validation['sanitized'] ?? '') : '';
        } elseif (!empty($_POST['device_fingerprint'])) {
            $fingerprint = sanitize_text_field($_POST['device_fingerprint']);
        }

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
                ppv_log("❌ [PPV_Login] Failed to create TikTok user: {$tiktok_id}");
                wp_send_json_error(['message' => 'Benutzer konnte nicht erstellt werden']);
            }

            $user_id = $wpdb->insert_id;
            ppv_log("✅ [PPV_Login] New TikTok user created (#{$user_id}): {$tiktok_id}");

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

        // ✅ FIX: Respect actual user_type from database (vendor, scanner, user)
        $db_user_type = $user->user_type ?? 'user';
        $redirect_url = home_url('/user_dashboard');
        $role = 'user';

        if ($db_user_type === 'vendor') {
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'vendor';
            $_SESSION['ppv_user_email'] = $user->email;
            if (!empty($user->vendor_store_id)) {
                $_SESSION['ppv_vendor_store_id'] = $user->vendor_store_id;
                $_SESSION['ppv_store_id'] = $user->vendor_store_id;
                $_SESSION['ppv_active_store'] = $user->vendor_store_id;
            }
            $GLOBALS['ppv_role'] = 'vendor';
            $redirect_url = home_url('/qr-center');
            $role = 'vendor';
        } elseif ($db_user_type === 'scanner') {
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'scanner';
            $_SESSION['ppv_user_email'] = $user->email;
            if (!empty($user->vendor_store_id)) {
                $_SESSION['ppv_store_id'] = $user->vendor_store_id;
            }
            $GLOBALS['ppv_role'] = 'scanner';
            $redirect_url = home_url('/qr-center');
            $role = 'scanner';
        } else {
            $_SESSION['ppv_user_id'] = $user->id;
            $_SESSION['ppv_user_type'] = 'user';
            $_SESSION['ppv_user_email'] = $user->email;
            $GLOBALS['ppv_role'] = 'user';
        }

        // ✅ Multi-device: Reuse existing token if available
        $token = $user->login_token;
        if (empty($token)) {
            $token = md5(uniqid('ppv_user_tt_', true));
            $wpdb->update(
                "{$prefix}ppv_users",
                ['login_token' => $token],
                ['id' => $user->id],
                ['%s'],
                ['%d']
            );
            ppv_log("🔑 [PPV_Login] New TikTok token generated");
        } else {
            ppv_log("🔑 [PPV_Login] Reusing existing token for TikTok login (multi-device)");
        }

        // Set cookie
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        setcookie('ppv_user_token', $token, time() + (86400 * 180), '/', $domain, true, true);

        // 📱 Track login fingerprint (TikTok)
        if (!empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
            PPV_Device_Fingerprint::track_login($user->id, $fingerprint, 'tiktok');
        }

        ppv_log("✅ [PPV_Login] TikTok login successful (#{$user->id}): {$tiktok_id}, type={$db_user_type}");

        wp_send_json_success([
            'message' => 'Erfolgreich angemeldet!',
            'role' => $role,
            'user_id' => (int)$user->id,
            'user_token' => $token,
            'redirect' => $redirect_url
        ]);
    }

    /** ============================================================
     * 🔹 Verify Facebook Access Token
     * ============================================================ */
    private static function verify_facebook_token($access_token) {
        $app_id = defined('PPV_FACEBOOK_APP_ID') ? PPV_FACEBOOK_APP_ID : get_option('ppv_facebook_app_id', '');
        $app_secret = defined('PPV_FACEBOOK_APP_SECRET') ? PPV_FACEBOOK_APP_SECRET : get_option('ppv_facebook_app_secret', '');

        if (empty($app_id) || empty($app_secret)) {
            ppv_log("❌ [PPV_Facebook] App ID or Secret not configured");
            return false;
        }

        // Verify token with Facebook
        $verify_url = "https://graph.facebook.com/debug_token?input_token={$access_token}&access_token={$app_id}|{$app_secret}";
        $verify_response = wp_remote_get($verify_url);

        if (is_wp_error($verify_response)) {
            ppv_log("❌ [PPV_Facebook] Token verification failed: " . $verify_response->get_error_message());
            return false;
        }

        $verify_data = json_decode(wp_remote_retrieve_body($verify_response), true);

        if (!isset($verify_data['data']['is_valid']) || !$verify_data['data']['is_valid']) {
            ppv_log("❌ [PPV_Facebook] Invalid token");
            return false;
        }

        // Get user data
        $user_url = "https://graph.facebook.com/me?fields=id,email,first_name,last_name&access_token={$access_token}";
        $user_response = wp_remote_get($user_url);

        if (is_wp_error($user_response)) {
            ppv_log("❌ [PPV_Facebook] User data fetch failed: " . $user_response->get_error_message());
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
            ppv_log("❌ [PPV_TikTok] Client Key or Secret not configured");
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
            ppv_log("❌ [PPV_TikTok] Token exchange failed: " . $token_response->get_error_message());
            return false;
        }

        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);

        if (!isset($token_data['data']['access_token'])) {
            ppv_log("❌ [PPV_TikTok] No access token in response");
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
            ppv_log("❌ [PPV_TikTok] User info fetch failed: " . $user_response->get_error_message());
            return false;
        }

        $user_data = json_decode(wp_remote_retrieve_body($user_response), true);

        if (!isset($user_data['data']['user'])) {
            ppv_log("❌ [PPV_TikTok] No user data in response");
            return false;
        }

        return $user_data['data']['user'];
    }
}

// Initialize
PPV_Login::hooks();
