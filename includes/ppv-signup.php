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
        // Standalone: intercept signup page before WP theme renders
        add_action('template_redirect', [__CLASS__, 'intercept_signup_page']);

        // Keep shortcode as fallback
        add_shortcode('ppv_signup', [__CLASS__, 'render_signup_page']);

        // AJAX hooks - for logged OUT users (nopriv)
        add_action('wp_ajax_nopriv_ppv_signup', [__CLASS__, 'ajax_signup']);
        add_action('wp_ajax_nopriv_ppv_google_signup', [__CLASS__, 'ajax_google_signup']);

        // ALSO for logged IN users (safety measure)
        add_action('wp_ajax_ppv_signup', [__CLASS__, 'ajax_signup']);
        add_action('wp_ajax_ppv_google_signup', [__CLASS__, 'ajax_google_signup']);

        ppv_log("✅ [PPV_Signup] Hooks registered successfully");
    }

    /** ============================================================
     * 🔹 Initialize
     * ============================================================ */
    public static function init() {
        if (!session_id() && !headers_sent()) {
            ppv_maybe_start_session();
        }

        self::hooks();
        ppv_log("✅ [PPV_Signup] Initialized");
    }

    /** ============================================================
     * 🔹 Get Current Language
     * ============================================================ */
    private static function get_current_lang() {
        static $lang = null;
        if ($lang !== null) return $lang;

        if (isset($_COOKIE['ppv_lang'])) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang']);
        } elseif (isset($_GET['lang']) || isset($_GET['ppv_lang'])) {
            $lang = sanitize_text_field($_GET['lang'] ?? $_GET['ppv_lang']);
        } else {
            // Browser Accept-Language detection
            $lang = self::detect_browser_lang();
        }

        if (!in_array($lang, ['de', 'hu', 'ro', 'en'])) {
            $lang = 'ro'; // Default Romanian
        }

        return $lang;
    }

    /**
     * Detect browser language from Accept-Language header
     */
    private static function detect_browser_lang() {
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (!$accept) return 'ro';

        $supported = ['de', 'hu', 'ro', 'en'];
        $langs = [];

        foreach (explode(',', $accept) as $part) {
            $part = trim($part);
            if (preg_match('/^([a-z]{2})(?:-[A-Za-z]{2})?(?:\s*;\s*q=([0-9.]+))?$/', $part, $m)) {
                $code = strtolower($m[1]);
                $q = isset($m[2]) ? floatval($m[2]) : 1.0;
                if (in_array($code, $supported)) {
                    $langs[$code] = max($langs[$code] ?? 0, $q);
                }
            }
        }

        if (!empty($langs)) {
            arsort($langs);
            return array_key_first($langs);
        }

        return 'ro';
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
            ppv_maybe_start_session();
        }
    }

    /** ============================================================
     * Intercept Signup Page → Standalone HTML
     * ============================================================ */
    public static function intercept_signup_page() {
        if (!is_page(['signup', 'registrierung', 'regisztracio'])) {
            return;
        }
        self::render_standalone_signup();
        exit;
    }

    /** ============================================================
     * Render Standalone Signup (full HTML, no WP theme)
     * ============================================================ */
    private static function render_standalone_signup() {
        $plugin_url = PPV_PLUGIN_URL;
        $plugin_dir = PPV_PLUGIN_DIR;
        $version    = PPV_VERSION;
        $lang       = self::get_current_lang();

        // Page content
        $page_html = self::render_signup_page([]);

        // CSS version
        $css_ver = class_exists('PPV_Core') ? PPV_Core::asset_version($plugin_dir . 'assets/css/ppv-signup.css') : $version;
        $js_ver  = class_exists('PPV_Core') ? PPV_Core::asset_version($plugin_dir . 'assets/js/ppv-signup.js') : $version;

        $google_client_id = defined('PPV_GOOGLE_CLIENT_ID')
            ? PPV_GOOGLE_CLIENT_ID
            : get_option('ppv_google_client_id', '645942978357-ndj7dgrapd2dgndnjf03se1p08l0o9ra.apps.googleusercontent.com');

        header('Content-Type: text/html; charset=utf-8');
        ?><!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo esc_html(PPV_Lang::t('signup_title')); ?> - PunktePass</title>
    <link rel="icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png">
    <link rel="preconnect" href="https://accounts.google.com" crossorigin>
    <link rel="preload" href="<?php echo esc_url($plugin_url); ?>assets/img/logo.webp?v=2" as="image" type="image/webp">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-signup.css?ver=<?php echo esc_attr($css_ver); ?>">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
</head>
<body>
<?php echo $page_html; ?>
<script>
window.ppvSignup = {
    ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('ppv_signup_nonce'); ?>',
    google_client_id: '<?php echo esc_js($google_client_id); ?>',
    redirect_url: '<?php echo home_url('/user_dashboard'); ?>',
    vendor_redirect_url: '<?php echo home_url('/haendler'); ?>',
    debug: true
};
window.ppvSignupTranslations = <?php echo wp_json_encode([
    'error_fill_all' => PPV_Lang::t('signup_error_empty'),
    'error_invalid_email' => PPV_Lang::t('signup_error_invalid_email'),
    'error_password_mismatch' => PPV_Lang::t('signup_error_password_mismatch'),
    'error_password_short' => PPV_Lang::t('signup_error_password_short'),
    'error_password_requirements' => PPV_Lang::t('signup_error_password_weak'),
    'error_terms' => PPV_Lang::t('signup_error_terms'),
    'error_connection' => PPV_Lang::t('network_error'),
    'error_google_unavailable' => PPV_Lang::t('signup_google_error'),
    'error_google_failed' => PPV_Lang::t('signup_google_error'),
    'password_strength_weak' => PPV_Lang::t('password_strength_weak'),
    'password_strength_medium' => PPV_Lang::t('password_strength_medium'),
    'password_strength_good' => PPV_Lang::t('password_strength_good'),
    'password_strength_strong' => PPV_Lang::t('password_strength_strong'),
    'registering' => PPV_Lang::t('signup_registering'),
    'signup_google_btn' => PPV_Lang::t('signup_google_btn'),
]); ?>;
</script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-signup.js?ver=<?php echo esc_attr($js_ver); ?>"></script>
<script src="https://accounts.google.com/gsi/client" async defer></script>
</body>
</html>
<?php
    }

    /** ============================================================
     * 🔹 Render Signup Page (Modern Design v2)
     * ============================================================ */
    public static function render_signup_page($atts) {
        self::ensure_session();

        // Auto-redirect if already logged in
        if (!empty($_SESSION['ppv_user_id'])) {
            $user_type = $_SESSION['ppv_user_type'] ?? 'user';
            if ($user_type === 'user') {
                wp_redirect(home_url('/user_dashboard'));
                exit;
            } elseif (in_array($user_type, ['vendor', 'scanner'])) {
                wp_redirect(home_url('/qr-center'));
                exit;
            }
        }

        $lang = self::get_current_lang();

        ob_start();
        ?>

        <div class="su-page">
            <!-- Header -->
            <header class="su-header">
                <div class="su-header-inner">
                    <a href="/" class="su-brand">
                        <img src="<?php echo PPV_PLUGIN_URL; ?>assets/img/logo.webp?v=2" alt="PunktePass">
                        <span>PunktePass</span>
                    </a>
                    <?php if (class_exists('PPV_Lang_Switcher')) echo PPV_Lang_Switcher::render(); ?>
                </div>
            </header>

            <!-- Main -->
            <main class="su-main">
                <div class="su-card">
                    <div class="su-card-body">

                        <!-- Welcome -->
                        <div class="su-welcome">
                            <h2><?php echo PPV_Lang::t('signup_title'); ?></h2>
                            <p><?php echo PPV_Lang::t('signup_subtitle'); ?></p>
                        </div>

                        <!-- User Type Selector (Segmented Control) -->
                        <div class="su-type-selector">
                            <span class="su-type-label"><?php echo PPV_Lang::t('signup_i_am'); ?></span>
                            <div class="su-type-tabs">
                                <button type="button" class="su-type-tab active" data-type="user">
                                    <i class="ri-user-line"></i>
                                    <?php echo PPV_Lang::t('signup_type_customer'); ?>
                                </button>
                                <button type="button" class="su-type-tab" data-type="vendor">
                                    <i class="ri-store-2-line"></i>
                                    <?php echo PPV_Lang::t('signup_type_vendor'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Vendor sub-choice modal: Loyalty vs Werbung -->
                        <div id="ppv-vendor-choice" class="vc-overlay" style="display:none;">
                          <div class="vc-modal">
                            <button type="button" class="vc-close" id="vc-close" aria-label="Close"><i class="ri-close-line"></i></button>
                            <h3 class="vc-title"><?php echo esc_html(PPV_Lang::t('signup_choose_type')); ?></h3>
                            <p class="vc-sub"><?php echo esc_html(PPV_Lang::t('signup_choose_sub')); ?></p>

                            <button type="button" class="vc-card vc-loyalty" id="vc-pick-loyalty">
                              <div class="vc-card-icon"><i class="ri-gift-2-fill"></i></div>
                              <div class="vc-card-body">
                                <div class="vc-card-title"><?php echo esc_html(PPV_Lang::t('signup_loyalty_title')); ?></div>
                                <div class="vc-card-desc"><?php echo esc_html(PPV_Lang::t('signup_loyalty_desc')); ?></div>
                              </div>
                              <i class="ri-arrow-right-s-line vc-card-arrow"></i>
                            </button>

                            <button type="button" class="vc-card vc-ad" id="vc-pick-ad">
                              <div class="vc-card-icon ad"><i class="ri-megaphone-fill"></i></div>
                              <div class="vc-card-body">
                                <div class="vc-card-title"><?php echo esc_html(PPV_Lang::t('signup_ad_title')); ?></div>
                                <div class="vc-card-desc"><?php echo esc_html(PPV_Lang::t('signup_ad_desc')); ?></div>
                              </div>
                              <i class="ri-arrow-right-s-line vc-card-arrow"></i>
                            </button>
                          </div>
                        </div>
                        <style>
                          .vc-overlay { position:fixed; inset:0; background:rgba(15,23,42,.6); backdrop-filter:blur(4px); z-index:99998; display:flex; align-items:center; justify-content:center; padding:16px; }
                          .vc-modal { width:100%; max-width:440px; background:#fff; border-radius:18px; padding:24px 22px 18px; box-shadow:0 20px 60px rgba(0,0,0,.3); position:relative; max-height:90vh; overflow:auto; }
                          .vc-close { position:absolute; top:10px; right:10px; width:36px; height:36px; border-radius:50%; background:#f3f4f6; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:20px; color:#6b7280; }
                          .vc-title { margin:0 0 4px; font-size:19px; font-weight:700; color:#111827; }
                          .vc-sub { margin:0 0 18px; color:#6b7280; font-size:13px; }
                          .vc-card { display:flex; align-items:center; gap:14px; width:100%; text-align:left; padding:16px; margin-bottom:12px; border:2px solid #e5e7eb; border-radius:14px; background:#fff; cursor:pointer; transition:all .18s ease; }
                          .vc-card:hover { border-color:#6366f1; transform:translateY(-1px); box-shadow:0 6px 18px rgba(99,102,241,.15); }
                          .vc-card-icon { width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; display:flex; align-items:center; justify-content:center; font-size:24px; flex-shrink:0; }
                          .vc-card-icon.ad { background:linear-gradient(135deg,#f59e0b,#d97706); }
                          .vc-card-body { flex:1; min-width:0; }
                          .vc-card-title { font-weight:700; color:#111827; font-size:15px; margin-bottom:2px; }
                          .vc-card-desc { color:#6b7280; font-size:12.5px; line-height:1.4; }
                          .vc-card-arrow { color:#9ca3af; font-size:22px; flex-shrink:0; }
                          .vc-card.vc-ad:hover { border-color:#f59e0b; box-shadow:0 6px 18px rgba(245,158,11,.18); }
                        </style>

                        <!-- Google Signup -->
                        <button type="button" id="ppv-google-signup-btn" class="su-google-btn">
                            <svg width="18" height="18" viewBox="0 0 48 48">
                                <path d="M47.532 24.5528C47.532 22.9214 47.3997 21.2811 47.1175 19.6761H24.48V28.9181H37.4434C36.9055 31.8988 35.177 34.5356 32.6461 36.2111V42.2078H40.3801C44.9217 38.0278 47.532 31.8547 47.532 24.5528Z" fill="#4285F4"/>
                                <path d="M24.48 48.0016C30.9529 48.0016 36.4116 45.8764 40.3888 42.2078L32.6549 36.2111C30.5031 37.675 27.7252 38.5039 24.4888 38.5039C18.2275 38.5039 12.9187 34.2798 11.0139 28.6006H3.03296V34.7825C7.10718 42.8868 15.4056 48.0016 24.48 48.0016Z" fill="#34A853"/>
                                <path d="M11.0051 28.6006C9.99973 25.6199 9.99973 22.3922 11.0051 19.4115V13.2296H3.03298C-0.371021 20.0112 -0.371021 28.0009 3.03298 34.7825L11.0051 28.6006Z" fill="#FBBC04"/>
                                <path d="M24.48 9.49932C27.9016 9.44641 31.2086 10.7339 33.6866 13.0973L40.5387 6.24523C36.2 2.17101 30.4414 -0.068932 24.48 0.00161733C15.4055 0.00161733 7.10718 5.11644 3.03296 13.2296L11.005 19.4115C12.901 13.7235 18.2187 9.49932 24.48 9.49932Z" fill="#EA4335"/>
                            </svg>
                            <span><?php echo PPV_Lang::t('signup_google_btn'); ?></span>
                        </button>

                        <!-- Divider -->
                        <div class="su-divider"><?php echo PPV_Lang::t('signup_or_email'); ?></div>

                        <!-- Signup Form -->
                        <form id="ppv-signup-form" class="su-form" autocomplete="off">
                            <input type="hidden" name="user_type" id="ppv-user-type" value="user">

                            <!-- Business name (advertiser only) -->
                            <div class="su-input-group" id="ppv-business-name-group" style="display:none;">
                                <label for="ppv-business-name"><i class="ri-store-2-line"></i> <?php echo esc_html(PPV_Lang::t('signup_business_name')); ?></label>
                                <div class="su-input-wrap">
                                    <input type="text" id="ppv-business-name" name="business_name" class="su-input" placeholder="<?php echo esc_attr(PPV_Lang::t('signup_business_name_placeholder')); ?>" maxlength="80">
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="su-input-group">
                                <label for="ppv-email"><i class="ri-mail-line"></i> <?php echo PPV_Lang::t('signup_email_label'); ?></label>
                                <div class="su-input-wrap">
                                    <input type="email" id="ppv-email" name="email" class="su-input" placeholder="<?php echo PPV_Lang::t('signup_email_placeholder'); ?>" autocomplete="email" required>
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="su-input-group">
                                <label for="ppv-password"><i class="ri-lock-line"></i> <?php echo PPV_Lang::t('signup_password_label'); ?></label>
                                <div class="su-input-wrap su-input-wrap--password">
                                    <input type="password" id="ppv-password" name="password" class="su-input" placeholder="<?php echo PPV_Lang::t('signup_password_placeholder'); ?>" autocomplete="new-password" required>
                                    <button type="button" id="ppv-generate-password" class="su-pw-generate" title="<?php echo esc_attr(PPV_Lang::t('signup_generate_password')); ?>">
                                        <i class="ri-key-line" style="font-size: 16px;"></i>
                                    </button>
                                    <button type="button" class="su-pw-toggle ppv-password-toggle" aria-label="<?php echo PPV_Lang::t('signup_show_password'); ?>">
                                        <i class="ri-eye-line ppv-eye-open" style="font-size: 18px;"></i>
                                        <i class="ri-eye-off-line ppv-eye-closed" style="font-size: 18px; display:none;"></i>
                                    </button>
                                </div>

                                <!-- Password Strength -->
                                <div id="ppv-password-strength" class="su-pw-strength" style="display:none;">
                                    <div class="su-strength-bar">
                                        <div class="ppv-strength-fill su-strength-fill"></div>
                                    </div>
                                    <p class="ppv-strength-text su-strength-text"></p>
                                </div>

                                <!-- Password Requirements -->
                                <div class="su-pw-reqs">
                                    <div class="su-pw-reqs-title"><?php echo PPV_Lang::t('signup_password_requirements'); ?></div>
                                    <ul class="su-pw-reqs-list">
                                        <li id="req-length"><i class="ri-checkbox-blank-circle-line"></i> <?php echo PPV_Lang::t('signup_req_length'); ?></li>
                                        <li id="req-uppercase"><i class="ri-checkbox-blank-circle-line"></i> <?php echo PPV_Lang::t('signup_req_uppercase'); ?></li>
                                        <li id="req-number"><i class="ri-checkbox-blank-circle-line"></i> <?php echo PPV_Lang::t('signup_req_number'); ?></li>
                                        <li id="req-special"><i class="ri-checkbox-blank-circle-line"></i> <?php echo PPV_Lang::t('signup_req_special'); ?></li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="su-input-group">
                                <label for="ppv-password-confirm"><i class="ri-checkbox-circle-line"></i> <?php echo PPV_Lang::t('signup_password_confirm_label'); ?></label>
                                <div class="su-input-wrap">
                                    <input type="password" id="ppv-password-confirm" name="password_confirm" class="su-input" placeholder="<?php echo PPV_Lang::t('signup_password_confirm_placeholder'); ?>" autocomplete="new-password" required>
                                </div>
                            </div>

                            <!-- Terms & Privacy -->
                            <div class="su-terms">
                                <label class="su-check">
                                    <input type="checkbox" name="terms" id="ppv-terms" required>
                                    <span><?php echo PPV_Lang::t('signup_terms_agree'); ?> <a href="/agb" target="_blank"><?php echo PPV_Lang::t('signup_terms_link'); ?></a></span>
                                </label>
                                <label class="su-check">
                                    <input type="checkbox" name="privacy" id="ppv-privacy" required>
                                    <span><?php echo PPV_Lang::t('signup_privacy_agree'); ?> <a href="/datenschutz" target="_blank"><?php echo PPV_Lang::t('signup_privacy_link'); ?></a></span>
                                </label>
                            </div>

                            <!-- Submit -->
                            <button type="submit" class="su-submit" id="ppv-submit-btn">
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

                        <!-- Alert -->
                        <div id="ppv-signup-alert" class="su-alert" style="display:none;"></div>

                        <!-- Login Link -->
                        <div class="su-login-link">
                            <?php echo PPV_Lang::t('signup_have_account'); ?> <a href="/login"><?php echo PPV_Lang::t('signup_login_now'); ?></a>
                        </div>

                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer class="su-footer">
                <p><?php echo PPV_Lang::t('landing_footer_copyright'); ?></p>
                <div class="su-footer-links">
                    <a href="/datenschutz"><?php echo PPV_Lang::t('landing_footer_privacy'); ?></a>
                    <span>&middot;</span>
                    <a href="/agb"><?php echo PPV_Lang::t('landing_footer_terms'); ?></a>
                    <span>&middot;</span>
                    <a href="/impressum"><?php echo PPV_Lang::t('landing_footer_imprint'); ?></a>
                </div>
            </footer>
        </div>

        <script>
        // User Type Segmented Control
        (function() {
            function initTypeToggle() {
                const tabs = document.querySelectorAll('.su-type-tab');
                const hiddenInput = document.getElementById('ppv-user-type');

                if (!tabs.length || !hiddenInput) {
                    setTimeout(initTypeToggle, 100);
                    return;
                }

                const overlay = document.getElementById('ppv-vendor-choice');
                const pickLoyalty = document.getElementById('vc-pick-loyalty');
                const pickAd = document.getElementById('vc-pick-ad');
                const closeBtn = document.getElementById('vc-close');

                function openVendorChoice() {
                    if (overlay) overlay.style.display = 'flex';
                }
                function closeVendorChoice() {
                    if (overlay) overlay.style.display = 'none';
                    // Revert tab to user if no choice made
                    tabs.forEach(function(t) { t.classList.toggle('active', t.dataset.type === 'user'); });
                    hiddenInput.value = 'user';
                }

                tabs.forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        tabs.forEach(function(t) { t.classList.remove('active'); });
                        tab.classList.add('active');
                        hiddenInput.value = tab.dataset.type;
                        if (tab.dataset.type === 'vendor') openVendorChoice();
                    });
                });

                if (closeBtn) closeBtn.addEventListener('click', closeVendorChoice);
                if (overlay) overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) closeVendorChoice();
                });
                const bizGroup = document.getElementById('ppv-business-name-group');
                function setAdvertiserMode(on) {
                  if (bizGroup) bizGroup.style.display = on ? '' : 'none';
                  const inp = document.getElementById('ppv-business-name');
                  if (inp) inp.required = !!on;
                }
                if (pickLoyalty) pickLoyalty.addEventListener('click', function() {
                    hiddenInput.value = 'vendor';
                    setAdvertiserMode(false);
                    if (overlay) overlay.style.display = 'none';
                });
                if (pickAd) pickAd.addEventListener('click', function() {
                    hiddenInput.value = 'advertiser';
                    setAdvertiserMode(true);
                    if (overlay) overlay.style.display = 'none';
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initTypeToggle);
            } else {
                initTypeToggle();
            }
        })();
        </script>

        <?php
        return ob_get_clean();
    }

    /** ============================================================
     * 🔹 AJAX Signup Handler (WITH HÄNDLER SUPPORT)
     * ============================================================ */
    public static function ajax_signup() {
        ppv_log("========================================");
        ppv_log("🔹 [PPV_Signup] AJAX signup called");
        ppv_log("========================================");

        global $wpdb;
        $prefix = $wpdb->prefix;

        if (!$wpdb) {
            ppv_log("❌ [PPV_Signup] WPDB not available");
            wp_send_json_error(['message' => 'Adatbázis hiba']);
            return;
        }

        self::ensure_session();

        // NONCE CHECK
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ppv_signup_nonce')) {
            ppv_log("❌ [PPV_Signup] Nonce verification failed");
            wp_send_json_error(['message' => 'Biztonsági ellenőrzés sikertelen!']);
            return;
        }

        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $terms = isset($_POST['terms']) && $_POST['terms'] === 'true';
        $privacy = isset($_POST['privacy']) && $_POST['privacy'] === 'true';
        $user_type = sanitize_text_field($_POST['user_type'] ?? 'user'); // NEW!

        // Advertiser branch — separate registration in ppv_advertisers table
        if ($user_type === 'advertiser') {
            $business_name = sanitize_text_field($_POST['business_name'] ?? '');
            if (!is_email($email) || strlen($password) < 6 || strlen($business_name) < 2) {
                wp_send_json_error(['message' => 'Hiányzó adatok (cégnév, email, jelszó min 6).']);
                return;
            }
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$prefix}ppv_advertisers WHERE owner_email = %s", $email
            ));
            if ($exists) {
                wp_send_json_error(['message' => 'Ez az email már regisztrálva van advertiser-ként.']);
                return;
            }
            $slug = sanitize_title($business_name) . '-' . substr(md5($email . microtime()), 0, 6);
            $wpdb->insert($prefix . 'ppv_advertisers', [
                'slug' => $slug,
                'business_name' => $business_name,
                'owner_email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'tier' => 'basic',
                'subscription_status' => 'trial',
                'subscription_until' => date('Y-m-d', strtotime('+30 days')),
                'push_month_reset_at' => date('Y-m-d', strtotime('first day of next month')),
            ]);
            $adv_id = $wpdb->insert_id;
            if (!$adv_id) {
                wp_send_json_error(['message' => 'Adatbázis hiba: ' . $wpdb->last_error]);
                return;
            }
            $_SESSION['ppv_advertiser_id'] = $adv_id;
            wp_send_json_success([
                'message' => 'Sikeres advertiser regisztráció! 30 nap ingyen trial.',
                'redirect' => home_url('/business/admin/profile?welcome=1'),
                'user_type' => 'advertiser',
            ]);
            return;
        }

        ppv_log("📧 Email: {$email}");
        ppv_log("👤 User Type: {$user_type}");

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
            ppv_log("❌ [PPV_Signup] Email exists: {$email}");
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
            ppv_log("❌ [PPV_Signup] Insert failed: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Regisztráció sikertelen: ' . $wpdb->last_error]);
            return;
        }

        $user_id = $wpdb->insert_id;
        ppv_log("✅ [PPV_Signup] User created: #{$user_id} ({$user_type})");

        // HÄNDLER: Create store
        $store_id = null;
        if ($user_type === 'vendor') {
            ppv_log("🏪 [PPV_Signup] Creating store for vendor #{$user_id}...");

            $pos_token = md5(uniqid('pos_', true));
            $store_key = bin2hex(random_bytes(32));
            $qr_secret = bin2hex(random_bytes(16));
            $pos_api_key = bin2hex(random_bytes(32));
            $trial_ends_at = date('Y-m-d H:i:s', strtotime('+30 days'));

            // Use email username as default store name (part before @)
            $default_name = explode('@', $email)[0];

            $store_data = [
                'user_id' => $user_id,
                'email' => $email,
                'name' => $default_name,
                'pos_token' => $pos_token,
                'store_key' => $store_key,
                'qr_secret' => $qr_secret,
                'pos_api_key' => $pos_api_key,
                'pos_enabled' => 1,
                'trial_ends_at' => $trial_ends_at,
                'subscription_status' => 'trial',
                'active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];

            // Log store data for debugging
            ppv_log("📝 [PPV_Signup] Store data: " . json_encode($store_data, JSON_UNESCAPED_UNICODE));

            $store_result = $wpdb->insert(
                "{$prefix}ppv_stores",
                $store_data,
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s']
            );

            // Debug: Log the last query
            ppv_log("🔍 [PPV_Signup] Last query: " . $wpdb->last_query);

            if ($store_result !== false) {
                $store_id = $wpdb->insert_id;

                // Link store to user
                $update_result = $wpdb->update(
                    "{$prefix}ppv_users",
                    ['vendor_store_id' => $store_id],
                    ['id' => $user_id],
                    ['%d'],
                    ['%d']
                );

                if ($update_result === false) {
                    ppv_log("⚠️ [PPV_Signup] Failed to link store to user: " . $wpdb->last_error);
                }

                ppv_log("✅ [PPV_Signup] Store created: #{$store_id} | Trial ends: {$trial_ends_at}");
            } else {
                ppv_log("❌ [PPV_Signup] Store INSERT failed!");
                ppv_log("❌ [PPV_Signup] DB Error: " . $wpdb->last_error);
                ppv_log("❌ [PPV_Signup] Last query: " . $wpdb->last_query);

                // Return error to user for debugging
                // Note: In production, you might want to show a generic error
                wp_send_json_error([
                    'message' => 'Store creation failed: ' . $wpdb->last_error,
                    'debug' => 'User created but store failed. Check server logs.'
                ]);
                return;
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

        ppv_log("========================================");
        ppv_log("✅ [PPV_Signup] SUCCESS - User #{$user_id}: {$email} ({$user_type})");
        if ($user_type === 'vendor') {
            ppv_log("   Store: #{$store_id} | Trial: {$trial_ends_at}");
        }
        ppv_log("========================================");

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
        ppv_log("🔹 [PPV_Signup] Google signup called");

        global $wpdb;
        $prefix = $wpdb->prefix;

        self::ensure_session();

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ppv_signup_nonce')) {
            ppv_log("❌ [PPV_Signup] Google signup - nonce failed");
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

        // Advertiser branch — Google signup creates ppv_advertisers entry directly
        if ($user_type === 'advertiser') {
            $business_name = sanitize_text_field($_POST['business_name'] ?? '');
            if (strlen($business_name) < 2) {
                wp_send_json_error(['message' => 'Add meg a cégnevet a Google regisztráció előtt.']);
                return;
            }
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$prefix}ppv_advertisers WHERE owner_email = %s", $email
            ));
            if ($exists) {
                $_SESSION['ppv_advertiser_id'] = (int)$exists;
                wp_send_json_success([
                    'message' => 'Belépés Google-lel.',
                    'redirect' => home_url('/business/admin/profile'),
                ]);
                return;
            }
            $slug = sanitize_title($business_name) . '-' . substr(md5($email . microtime()), 0, 6);
            $rand_pass = wp_generate_password(32);
            $wpdb->insert($prefix . 'ppv_advertisers', [
                'slug' => $slug,
                'business_name' => $business_name,
                'owner_email' => $email,
                'password_hash' => password_hash($rand_pass, PASSWORD_DEFAULT),
                'tier' => 'basic',
                'subscription_status' => 'trial',
                'subscription_until' => date('Y-m-d', strtotime('+30 days')),
                'push_month_reset_at' => date('Y-m-d', strtotime('first day of next month')),
            ]);
            $adv_id = $wpdb->insert_id;
            if (!$adv_id) {
                wp_send_json_error(['message' => 'Adatbázis hiba: ' . $wpdb->last_error]);
                return;
            }
            $_SESSION['ppv_advertiser_id'] = $adv_id;
            wp_send_json_success([
                'message' => 'Sikeres Google regisztráció! 30 nap ingyen.',
                'redirect' => home_url('/business/admin/profile?welcome=1'),
            ]);
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
            $display_name = trim($first_name . ' ' . $last_name);

            $insert_data = [
                'email' => $email,
                'password' => password_hash(wp_generate_password(32), PASSWORD_DEFAULT),
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $display_name,
                'google_id' => $google_id,
                'avatar' => $picture,
                'qr_token' => $qr_token,
                'user_type' => $user_type,
                'active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];

            $insert_format = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'];

            $insert_result = $wpdb->insert("{$prefix}ppv_users", $insert_data, $insert_format);

            if ($insert_result === false) {
                ppv_log("❌ [PPV_Signup] Google user insert failed: " . $wpdb->last_error);
                wp_send_json_error(['message' => PPV_Lang::t('signup_error_create')]);
                return;
            }

            $user_id = $wpdb->insert_id;
            ppv_log("✅ [PPV_Signup] Google user created: #{$user_id} ({$user_type})");

            // HÄNDLER: Create store
            $store_id = null;
            if ($user_type === 'vendor') {
                ppv_log("🏪 [PPV_Signup] Creating store for Google vendor #{$user_id}...");

                $pos_token = md5(uniqid('pos_', true));
                $store_key = bin2hex(random_bytes(32));
                $qr_secret = bin2hex(random_bytes(16));
                $pos_api_key = bin2hex(random_bytes(32));
                $trial_ends_at = date('Y-m-d H:i:s', strtotime('+30 days'));

                // Use full name or email username as default store name
                $default_name = trim($first_name . ' ' . $last_name) ?: explode('@', $email)[0];

                $store_data = [
                    'user_id' => $user_id,
                    'email' => $email,
                    'name' => $default_name,
                    'pos_token' => $pos_token,
                    'store_key' => $store_key,
                    'qr_secret' => $qr_secret,
                    'pos_api_key' => $pos_api_key,
                    'pos_enabled' => 1,
                    'trial_ends_at' => $trial_ends_at,
                    'subscription_status' => 'trial',
                    'active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ];

                // Log store data for debugging
                ppv_log("📝 [PPV_Signup] Google store data: " . json_encode($store_data, JSON_UNESCAPED_UNICODE));

                $store_result = $wpdb->insert(
                    "{$prefix}ppv_stores",
                    $store_data,
                    ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s']
                );

                // Debug: Log the last query
                ppv_log("🔍 [PPV_Signup] Google store last query: " . $wpdb->last_query);

                if ($store_result !== false) {
                    $store_id = $wpdb->insert_id;

                    $update_result = $wpdb->update(
                        "{$prefix}ppv_users",
                        ['vendor_store_id' => $store_id],
                        ['id' => $user_id],
                        ['%d'],
                        ['%d']
                    );

                    if ($update_result === false) {
                        ppv_log("⚠️ [PPV_Signup] Failed to link store to Google user: " . $wpdb->last_error);
                    }

                    ppv_log("✅ [PPV_Signup] Store created for Google user: #{$store_id}");
                } else {
                    ppv_log("❌ [PPV_Signup] Google store INSERT failed!");
                    ppv_log("❌ [PPV_Signup] DB Error: " . $wpdb->last_error);
                    ppv_log("❌ [PPV_Signup] Last query: " . $wpdb->last_query);

                    wp_send_json_error([
                        'message' => 'Store creation failed: ' . $wpdb->last_error,
                        'debug' => 'User created but store failed. Check server logs.'
                    ]);
                    return;
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

        ppv_log("✅ [PPV_Signup] Google signup success: #{$user->id} ({$user->user_type})");

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
            : get_option('ppv_google_client_id', '645942978357-ndj7dgrapd2dgndnjf03se1p08l0o9ra.apps.googleusercontent.com');

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
            ppv_maybe_start_session();
        }

        if (empty($_SESSION['ppv_user_id'])) {
            wp_send_json_error(['message' => 'Nicht eingeloggt']);
            return;
        }

        $user_id = intval($_SESSION['ppv_user_id']);

        // 🏪 FILIALE SUPPORT: Always use BASE store for renewal requests
        // Renewals are for the handler subscription, not individual filialen
        $store_id = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);

        // ✅ If no store_id in session, lookup via user_id
        if ($store_id === 0) {
            ppv_log("🔍 [PPV_Renewal] No store_id in session, looking up via user_id={$user_id}");

            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));

            if (!$store_id) {
                ppv_log("❌ [PPV_Renewal] No store found for user_id={$user_id}");
                wp_send_json_error(['message' => 'Store nicht gefunden']);
                return;
            }

            $store_id = intval($store_id);
            ppv_log("✅ [PPV_Renewal] Found store_id={$store_id} via user_id");
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
            ppv_log("❌ [PPV_Renewal] Failed to send email to {$to} for store #{$store_id}");
        } else {
            ppv_log("✅ [PPV_Renewal] Email sent to {$to} for store #{$store_id}");
        }

        ppv_log("✅ [PPV_Renewal] Request submitted - Store #{$store_id} | Phone: {$phone}");

        wp_send_json_success(['message' => 'Anfrage erfolgreich gesendet']);
    }

    /* ============================================================
     * 🆘 AJAX Submit Support Ticket
     * ============================================================ */
    public static function ajax_submit_support_ticket() {
        global $wpdb;

        // Check if user is logged in
        if (session_status() === PHP_SESSION_NONE) {
            ppv_maybe_start_session();
        }

        if (empty($_SESSION['ppv_user_id'])) {
            wp_send_json_error(['message' => 'Nicht eingeloggt']);
            return;
        }

        $user_id = intval($_SESSION['ppv_user_id']);

        // 🏪 FILIALE SUPPORT: Always use BASE store for support tickets
        // Support tickets are for the handler, not individual filialen
        $store_id = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);

        // ✅ If no store_id in session, lookup via user_id
        if ($store_id === 0) {
            ppv_log("🔍 [PPV_Support] No store_id in session, looking up via user_id={$user_id}");

            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));

            if (!$store_id) {
                ppv_log("❌ [PPV_Support] No store found for user_id={$user_id}");
                wp_send_json_error(['message' => 'Store nicht gefunden']);
                return;
            }

            $store_id = intval($store_id);
            ppv_log("✅ [PPV_Support] Found store_id={$store_id} via user_id");
        }

        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $priority = sanitize_text_field($_POST['priority'] ?? 'normal');
        $contact_preference = sanitize_text_field($_POST['contact_preference'] ?? 'email');
        $page_url = esc_url_raw($_POST['page_url'] ?? '');

        // User-provided contact info from support form
        $user_email = sanitize_email($_POST['email'] ?? '');
        $user_phone = sanitize_text_field($_POST['phone'] ?? '');

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
            ppv_log("❌ [PPV_Support] Failed to insert ticket: " . $wpdb->last_error);
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

        // User-provided contact info (from support form)
        if (!empty($user_email) || !empty($user_phone)) {
            $message .= "📱 Kontaktdaten aus Formular:\n";
            if (!empty($user_email)) {
                $message .= "   • E-Mail: {$user_email}\n";
            }
            if (!empty($user_phone)) {
                $message .= "   • Telefon: {$user_phone}\n";
            }
            $message .= "\n";
        }

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

        // Use user-provided email for Reply-To if available
        $reply_to_email = !empty($user_email) ? $user_email : $store->email;

        $headers = [
            'From: PunktePass Support <noreply@punktepass.de>',
            'Reply-To: ' . $reply_to_email,
            'Content-Type: text/plain; charset=UTF-8'
        ];

        $mail_sent = wp_mail($to, $subject, $message, $headers);

        if (!$mail_sent) {
            ppv_log("❌ [PPV_Support] Failed to send email to {$to} for ticket #{$ticket_id}");
        } else {
            ppv_log("✅ [PPV_Support] Email sent to {$to} for ticket #{$ticket_id}");
        }

        ppv_log("✅ [PPV_Support] Ticket #{$ticket_id} created - Store #{$store_id} | Priority: {$priority}");

        wp_send_json_success(['message' => '✅ Ticket erfolgreich gesendet! Wir melden uns schnellstmöglich.']);
    }

    /* ============================================================
     * 💬 AJAX Submit Feedback (Universal - Users & Handlers)
     * Categories: bug, feature, question, rating
     * Features: Language tracking, monthly rating limit, auto-confirmation email
     * ============================================================ */
    public static function ajax_submit_feedback() {
        global $wpdb;

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_feedback_nonce')) {
            wp_send_json_error(['message' => 'Ungültige Sicherheitstoken']);
            return;
        }

        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            ppv_maybe_start_session();
        }

        // Get user info
        $user_id = intval($_SESSION['ppv_user_id'] ?? 0);
        $user_type = sanitize_text_field($_POST['user_type'] ?? 'user');
        $store_id = 0;

        // For handlers, get store_id
        if ($user_type === 'handler') {
            $store_id = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);
            if ($store_id === 0 && $user_id > 0) {
                $store_id = intval($wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                    $user_id
                )));
            }
        }

        // Get form data
        $category = sanitize_text_field($_POST['category'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $rating = intval($_POST['rating'] ?? 0);
        $email = sanitize_email($_POST['email'] ?? '');
        $page_url = esc_url_raw($_POST['page_url'] ?? '');
        $device_info = sanitize_text_field($_POST['device_info'] ?? '');
        $source = sanitize_text_field($_POST['source'] ?? 'punktepass');

        // Get user language from cookie
        $language = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'ro';
        if (!in_array($language, ['de', 'hu', 'ro', 'en', 'en'])) $language = 'ro';

        // Validate category
        $valid_categories = ['bug', 'feature', 'question', 'rating'];
        if (!in_array($category, $valid_categories)) {
            wp_send_json_error(['message' => 'Ungültige Kategorie']);
            return;
        }

        // Validate rating for rating category
        if ($category === 'rating' && ($rating < 1 || $rating > 5)) {
            wp_send_json_error(['message' => 'Ungültige Bewertung']);
            return;
        }

        // Message required except for rating
        if (empty($message) && $category !== 'rating') {
            wp_send_json_error(['message' => 'Bitte beschreiben Sie Ihr Anliegen']);
            return;
        }

        // 🚫 MONTHLY RATING LIMIT: Check if user already submitted a rating this month
        if ($category === 'rating') {
            $month_start = date('Y-m-01 00:00:00');
            $month_end = date('Y-m-t 23:59:59');

            // Build query based on user type
            if ($user_type === 'handler' && $store_id > 0) {
                $existing_rating = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_support_tickets
                     WHERE category = 'rating'
                     AND user_type = 'handler'
                     AND store_id = %d
                     AND created_at BETWEEN %s AND %s
                     LIMIT 1",
                    $store_id, $month_start, $month_end
                ));
            } elseif ($user_id > 0) {
                $existing_rating = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_support_tickets
                     WHERE category = 'rating'
                     AND user_type = 'user'
                     AND user_id = %d
                     AND created_at BETWEEN %s AND %s
                     LIMIT 1",
                    $user_id, $month_start, $month_end
                ));
            } else {
                $existing_rating = null;
            }

            if ($existing_rating) {
                // Multi-language error messages
                $error_messages = [
                    'de' => 'Sie haben diesen Monat bereits eine Bewertung abgegeben. Sie können im nächsten Monat wieder bewerten.',
                    'hu' => 'Ebben a hónapban már küldött értékelést. A következő hónapban újra értékelhet.',
                    'en' => 'You have already submitted a rating this month. You can rate again next month.'
                ];
                wp_send_json_error(['message' => $error_messages[$language]]);
                return;
            }
        }

        // Get user/store info for email
        $user_email = '';
        $user_name = '';

        if ($user_type === 'handler' && $store_id > 0) {
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT email, company_name, name FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
                $store_id
            ));
            if ($store) {
                $user_email = $email ?: $store->email;
                $user_name = $store->company_name ?: $store->name;
            }
        } elseif ($user_id > 0) {
            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT email, first_name, last_name FROM {$wpdb->prefix}ppv_users WHERE id = %d LIMIT 1",
                $user_id
            ));
            if ($user) {
                $user_email = $user->email;
                $user_name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            }
        }

        // Category metadata
        $cat_meta = [
            'bug' => ['emoji' => '🐛', 'text' => 'Fehler', 'priority' => 'normal'],
            'feature' => ['emoji' => '💡', 'text' => 'Idee/Wunsch', 'priority' => 'low'],
            'question' => ['emoji' => '❓', 'text' => 'Frage', 'priority' => 'normal'],
            'rating' => ['emoji' => '⭐', 'text' => 'Bewertung', 'priority' => 'low']
        ];
        $meta = $cat_meta[$category];

        // Build description with context
        $full_description = "{$meta['emoji']} {$meta['text']}\n\n";
        if ($category === 'rating') {
            $full_description .= "Bewertung: " . str_repeat('⭐', $rating) . " ({$rating}/5)\n\n";
        }
        $full_description .= $message;
        $full_description .= "\n\n---\n";
        $full_description .= "📱 Gerät: {$device_info}\n";
        $full_description .= "🌐 Seite: {$page_url}\n";
        $full_description .= "🌍 Sprache: " . strtoupper($language) . "\n";
        $full_description .= "👤 Typ: " . ($user_type === 'handler' ? 'Händler' : 'Kunde') . "\n";
        $full_description .= "📍 Quelle: " . ($source === 'repair_formular' ? 'Repair Formular' : 'PunktePass App');

        // Insert into support_tickets table (with new columns)
        $insert_result = $wpdb->insert(
            "{$wpdb->prefix}ppv_support_tickets",
            [
                'category' => $category,
                'store_id' => $store_id ?: 0,
                'user_id' => $user_id ?: 0,
                'user_type' => $user_type,
                'language' => $language,
                'rating' => $category === 'rating' ? $rating : null,
                'handler_email' => $user_email ?: 'anonymous@punktepass.de',
                'handler_phone' => '',
                'store_name' => $user_name ?: ($user_type === 'handler' ? 'Händler' : 'Kunde'),
                'subject' => "[{$meta['text']}] " . ($user_name ?: 'Anonym'),
                'description' => $full_description,
                'priority' => $meta['priority'],
                'status' => 'new',
                'contact_preference' => 'email',
                'page_url' => $page_url,
                'device_info' => $device_info,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$insert_result) {
            ppv_log("❌ [PPV_Feedback] Failed to insert: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Fehler beim Speichern']);
            return;
        }

        $ticket_id = $wpdb->insert_id;

        // Send email to admin
        $to = 'info@punktepass.de';
        $source_label = ($source === 'repair_formular') ? '[Formular]' : '[App]';
        $subject = "{$meta['emoji']} {$source_label} Feedback #{$ticket_id} - {$meta['text']}";

        $email_body = "Neues Feedback eingegangen:\n\n";
        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $email_body .= "🎫 Ticket ID: #{$ticket_id}\n";
        $email_body .= "📁 Kategorie: {$meta['emoji']} {$meta['text']}\n";
        $email_body .= "👤 Benutzertyp: " . ($user_type === 'handler' ? 'Händler' : 'Kunde') . "\n";
        $email_body .= "🌍 Sprache: " . strtoupper($language) . "\n";
        if ($store_id > 0) {
            $email_body .= "🏪 Store ID: #{$store_id}\n";
        }
        if ($user_id > 0) {
            $email_body .= "🆔 User ID: #{$user_id}\n";
        }
        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        if ($category === 'rating') {
            $email_body .= "⭐ Bewertung: " . str_repeat('⭐', $rating) . " ({$rating}/5)\n\n";
        }

        $email_body .= "📝 Nachricht:\n";
        $email_body .= $message . "\n\n";

        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $email_body .= "📱 Gerät: {$device_info}\n";
        $email_body .= "🌐 Seite: {$page_url}\n";
        $email_body .= "📧 E-Mail: " . ($user_email ?: 'Keine angegeben') . "\n";
        $email_body .= "🕐 Zeit: " . current_time('Y-m-d H:i:s') . "\n";
        $email_body .= "📍 Quelle: " . ($source === 'repair_formular' ? '🔧 Repair Formular' : '📱 PunktePass App') . "\n";

        $headers = [
            'From: PunktePass Feedback <noreply@punktepass.de>',
            'Content-Type: text/plain; charset=UTF-8'
        ];
        if ($user_email) {
            $headers[] = 'Reply-To: ' . $user_email;
        }

        wp_mail($to, $subject, $email_body, $headers);

        // 📧 SEND AUTO-CONFIRMATION EMAIL TO USER (except for ratings)
        if ($category !== 'rating' && !empty($user_email)) {
            self::send_feedback_confirmation_email($user_email, $user_name, $ticket_id, $category, $language);
        }

        ppv_log("✅ [PPV_Feedback] #{$ticket_id} created - {$category} from {$user_type} (lang: {$language})");

        // Multi-language success messages
        $success_messages = [
            'de' => 'Vielen Dank für Ihr Feedback!',
            'hu' => 'Köszönjük visszajelzését!',
            'en' => 'Thank you for your feedback!'
        ];
        wp_send_json_success(['message' => $success_messages[$language]]);
    }

    /**
     * 📧 Send feedback confirmation email to user
     */
    private static function send_feedback_confirmation_email($email, $name, $ticket_id, $category, $lang = 'de') {
        // Multi-language email content
        $translations = [
            'de' => [
                'subject' => "Feedback erhalten - Ticket #{$ticket_id}",
                'greeting' => $name ? "Hallo {$name}," : "Hallo,",
                'thanks' => "vielen Dank für Ihre Nachricht an das PunktePass-Team!",
                'received' => "Wir haben Ihr Anliegen erhalten und werden uns schnellstmöglich bei Ihnen melden.",
                'ticket_info' => "Ihre Ticket-Nummer",
                'footer_1' => "Mit freundlichen Grüßen",
                'footer_2' => "Ihr PunktePass-Team",
                'auto_msg' => "Dies ist eine automatisch generierte E-Mail. Bitte antworten Sie nicht direkt auf diese Nachricht."
            ],
            'hu' => [
                'subject' => "Visszajelzés megérkezett - Jegy #{$ticket_id}",
                'greeting' => $name ? "Kedves {$name}!" : "Kedves Felhasználó!",
                'thanks' => "Köszönjük, hogy írt a PunktePass csapatának!",
                'received' => "Megkaptuk üzenetét és hamarosan válaszolunk.",
                'ticket_info' => "Az Ön jegyszáma",
                'footer_1' => "Üdvözlettel",
                'footer_2' => "A PunktePass csapat",
                'auto_msg' => "Ez egy automatikusan generált e-mail. Kérjük, ne válaszoljon közvetlenül erre az üzenetre."
            ],
            'en' => [
                'subject' => "Feedback Received - Ticket #{$ticket_id}",
                'greeting' => $name ? "Hello {$name}," : "Hello,",
                'thanks' => "Thank you for contacting the PunktePass team!",
                'received' => "We have received your message and will get back to you as soon as possible.",
                'ticket_info' => "Your ticket number",
                'footer_1' => "Best regards",
                'footer_2' => "Your PunktePass Team",
                'auto_msg' => "This is an automatically generated email. Please do not reply directly to this message."
            ]
        ];

        $T = $translations[$lang] ?? $translations['de'];

        $cat_names = [
            'bug' => ['de' => 'Fehlermeldung', 'hu' => 'Hibabejelentés', 'en' => 'Bug Report'],
            'feature' => ['de' => 'Verbesserungsvorschlag', 'hu' => 'Fejlesztési javaslat', 'en' => 'Feature Request'],
            'question' => ['de' => 'Frage', 'hu' => 'Kérdés', 'en' => 'Question']
        ];
        $cat_name = $cat_names[$category][$lang] ?? $cat_names[$category]['de'] ?? $category;

        $body = "
{$T['greeting']}

{$T['thanks']}

{$T['received']}

{$T['ticket_info']}: #{$ticket_id}
Kategorie / Category: {$cat_name}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

{$T['footer_1']},
{$T['footer_2']}

www.punktepass.de

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$T['auto_msg']}
";

        $headers = [
            'From: PunktePass <info@punktepass.de>',
            'Content-Type: text/plain; charset=UTF-8'
        ];

        wp_mail($email, $T['subject'], $body, $headers);
        ppv_log("📧 [PPV_Feedback] Confirmation email sent to {$email} for ticket #{$ticket_id}");
    }

    /* ============================================================
     * 👥 AJAX Create Scanner User
     * ============================================================ */
    public static function ajax_create_scanner_user() {
        global $wpdb;

        // Check if handler is logged in
        if (session_status() === PHP_SESSION_NONE) {
            ppv_maybe_start_session();
        }

        if (empty($_SESSION['ppv_user_id'])) {
            wp_send_json_error(['message' => 'Nicht eingeloggt']);
            return;
        }

        $handler_user_id = intval($_SESSION['ppv_user_id']);

        // 🏪 FILIALE SUPPORT: Accept filiale_id from frontend
        $filiale_id = intval($_POST['filiale_id'] ?? 0);

        // If no filiale_id provided, default to handler's base store
        if ($filiale_id === 0) {
            $filiale_id = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);
        }

        // Get store_id if still missing
        if ($filiale_id === 0) {
            $filiale_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $handler_user_id
            ));

            if (!$filiale_id) {
                wp_send_json_error(['message' => 'Store nicht gefunden']);
                return;
            }

            $filiale_id = intval($filiale_id);
        }

        // Verify that the filiale belongs to this handler (security check)
        $parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $filiale_id
        ));

        $base_handler_id = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);
        if ($parent_id != $base_handler_id && $filiale_id != $base_handler_id) {
            wp_send_json_error(['message' => 'Ungültige Filiale']);
            return;
        }

        $login = sanitize_text_field($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        // Login field is required
        if (empty($login) || empty($password)) {
            wp_send_json_error(['message' => 'Benutzername/E-Mail und Passwort sind erforderlich']);
            return;
        }

        // Detect if login is email or username
        $is_email = is_email($login);
        $email = null;
        $username = null;

        if ($is_email) {
            $email = sanitize_email($login);
            // Check if email already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email = %s LIMIT 1",
                $email
            ));
            if ($existing) {
                wp_send_json_error(['message' => 'Diese E-Mail ist bereits registriert']);
                return;
            }
        } else {
            $username = $login;
            // Check if username already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE username = %s LIMIT 1",
                $username
            ));
            if ($existing) {
                wp_send_json_error(['message' => 'Dieser Benutzername ist bereits vergeben']);
                return;
            }
        }

        // Generate unique QR token
        do {
            $qr_token = substr(md5(uniqid(mt_rand(), true)), 0, 16);
            $check = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE qr_token = %s LIMIT 1",
                $qr_token
            ));
        } while ($check);

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert scanner user into PPV users table (with selected filiale)
        $insert_data = [
            'email' => !empty($email) ? $email : null,
            'username' => $username,
            'password' => $hashed_password,
            'first_name' => '',
            'last_name' => '',
            'user_type' => 'scanner',
            'vendor_store_id' => $filiale_id,
            'qr_token' => $qr_token,
            'active' => 1,
            'created_at' => current_time('mysql')
        ];

        $insert_result = $wpdb->insert(
            "{$wpdb->prefix}ppv_users",
            $insert_data,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s']
        );

        if ($insert_result === false) {
            ppv_log("❌ [PPV_Scanner] Failed to create scanner user: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Fehler beim Erstellen des Benutzers']);
            return;
        }

        $user_id = $wpdb->insert_id;

        $log_msg = "✅ [PPV_Scanner] Scanner user created: ID={$user_id}, Store={$filiale_id}, QR={$qr_token}";
        if ($username) {
            $log_msg .= ", Username={$username}";
        }
        if ($email) {
            $log_msg .= ", Email={$email}";
        }
        ppv_log($log_msg);

        wp_send_json_success([
            'message' => '✅ Scanner Benutzer erfolgreich erstellt!',
            'user_id' => $user_id,
            'login' => $login
        ]);
    }

    /* ============================================================
     * 🔄 AJAX Reset Scanner Password
     * ============================================================ */
    public static function ajax_reset_scanner_password() {
        global $wpdb;

        // Check if handler is logged in
        if (session_status() === PHP_SESSION_NONE) {
            ppv_maybe_start_session();
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

        // 🏪 FILIALE SUPPORT: Always use BASE store for scanner management
        $handler_store_id = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);

        if ($handler_store_id === 0) {
            $handler_store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $handler_user_id
            ));
        }

        // Get scanner user from PPV users
        $scanner_user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, vendor_store_id FROM {$wpdb->prefix}ppv_users
             WHERE id = %d AND user_type = 'scanner' LIMIT 1",
            $scanner_user_id
        ));

        if (!$scanner_user) {
            wp_send_json_error(['message' => 'Scanner Benutzer nicht gefunden']);
            return;
        }

        if ($scanner_user->vendor_store_id != $handler_store_id) {
            wp_send_json_error(['message' => 'Keine Berechtigung für diesen Benutzer']);
            return;
        }

        // Reset password in PPV users table
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $update_result = $wpdb->update(
            "{$wpdb->prefix}ppv_users",
            ['password' => $hashed_password],
            ['id' => $scanner_user_id],
            ['%s'],
            ['%d']
        );

        if ($update_result === false) {
            ppv_log("❌ [PPV_Scanner] Password reset failed: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Fehler beim Zurücksetzen des Passworts']);
            return;
        }

        ppv_log("✅ [PPV_Scanner] Password reset for scanner: ID={$scanner_user_id}, Email={$scanner_user->email}");

        wp_send_json_success([
            'message' => '✅ Passwort erfolgreich zurückgesetzt!',
            'email' => $scanner_user->email
        ]);
    }

    /* ============================================================
     * 🚫 AJAX Toggle Scanner Status
     * ============================================================ */
    public static function ajax_toggle_scanner_status() {
        global $wpdb;

        // Check if handler is logged in
        if (session_status() === PHP_SESSION_NONE) {
            ppv_maybe_start_session();
        }

        if (empty($_SESSION['ppv_user_id'])) {
            wp_send_json_error(['message' => 'Nicht eingeloggt']);
            return;
        }

        $scanner_user_id = intval($_POST['user_id'] ?? 0);
        $action = sanitize_text_field($_POST['toggle_action'] ?? '');

        if (!$scanner_user_id || !in_array($action, ['enable', 'disable'])) {
            wp_send_json_error(['message' => 'Ungültige Parameter']);
            return;
        }

        // Verify scanner belongs to this handler
        $handler_user_id = intval($_SESSION['ppv_user_id']);

        // 🏪 FILIALE SUPPORT: Always use BASE store for scanner management
        $handler_store_id = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);

        if ($handler_store_id === 0) {
            $handler_store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $handler_user_id
            ));
        }

        // Get scanner user from PPV users
        $scanner_user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, vendor_store_id FROM {$wpdb->prefix}ppv_users
             WHERE id = %d AND user_type = 'scanner' LIMIT 1",
            $scanner_user_id
        ));

        if (!$scanner_user) {
            wp_send_json_error(['message' => 'Scanner Benutzer nicht gefunden']);
            return;
        }

        // Check if scanner belongs to handler's store OR any of their filialen
        $scanner_store_id = intval($scanner_user->vendor_store_id);
        $has_permission = false;

        if ($scanner_store_id == $handler_store_id) {
            $has_permission = true;
        } else {
            // Check if scanner's store is a filiale of handler's store
            $is_filiale = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores
                 WHERE id = %d AND parent_store_id = %d",
                $scanner_store_id, $handler_store_id
            ));
            if ($is_filiale > 0) {
                $has_permission = true;
            }
        }

        if (!$has_permission) {
            wp_send_json_error(['message' => 'Keine Berechtigung für diesen Benutzer']);
            return;
        }

        // Update only active status (user_type stays 'scanner' so they appear in list)
        // Disabled scanners can still login but without scanner privileges
        $new_status = $action === 'enable' ? 1 : 0;

        $update_result = $wpdb->update(
            "{$wpdb->prefix}ppv_users",
            ['active' => $new_status],
            ['id' => $scanner_user_id],
            ['%d'],
            ['%d']
        );

        if ($update_result === false) {
            ppv_log("❌ [PPV_Scanner] Status toggle failed: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Fehler beim Ändern des Status']);
            return;
        }

        $status_text = $action === 'disable' ? 'deaktiviert' : 'aktiviert';

        ppv_log("✅ [PPV_Scanner] Scanner {$status_text}: ID={$scanner_user_id}, Email={$scanner_user->email}");

        wp_send_json_success([
            'message' => $action === 'disable'
                ? "✅ Scanner deaktiviert! Benutzer kann sich weiterhin als normaler Nutzer einloggen."
                : "✅ Scanner erfolgreich aktiviert!",
            'new_status' => $action === 'enable' ? 'active' : 'disabled'
        ]);
    }

    /* ============================================================
     * 🏪 AJAX Update Scanner Filiale
     * ============================================================ */
    public static function ajax_update_scanner_filiale() {
        global $wpdb;

        // Check if handler is logged in
        if (session_status() === PHP_SESSION_NONE) {
            ppv_maybe_start_session();
        }

        if (empty($_SESSION['ppv_user_id'])) {
            wp_send_json_error(['message' => 'Nicht eingeloggt']);
            return;
        }

        $handler_user_id = intval($_SESSION['ppv_user_id']);
        $scanner_user_id = intval($_POST['user_id'] ?? 0);
        $new_filiale_id = intval($_POST['new_filiale_id'] ?? $_POST['filiale_id'] ?? 0);

        if (!$scanner_user_id || !$new_filiale_id) {
            wp_send_json_error(['message' => 'Ungültige Parameter']);
            return;
        }

        // Get handler's base store
        $base_handler_id = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);
        if ($base_handler_id === 0) {
            $base_handler_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $handler_user_id
            ));
        }

        // Verify that the new filiale belongs to this handler (security check)
        $parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $new_filiale_id
        ));

        if ($parent_id != $base_handler_id && $new_filiale_id != $base_handler_id) {
            wp_send_json_error(['message' => 'Ungültige Filiale']);
            return;
        }

        // Verify scanner belongs to this handler
        $scanner_user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, vendor_store_id FROM {$wpdb->prefix}ppv_users
             WHERE id = %d AND user_type = 'scanner' LIMIT 1",
            $scanner_user_id
        ));

        if (!$scanner_user) {
            wp_send_json_error(['message' => 'Scanner Benutzer nicht gefunden']);
            return;
        }

        // Verify current filiale belongs to handler (additional security)
        $current_parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $scanner_user->vendor_store_id
        ));

        if ($current_parent_id != $base_handler_id && $scanner_user->vendor_store_id != $base_handler_id) {
            wp_send_json_error(['message' => 'Keine Berechtigung für diesen Benutzer']);
            return;
        }

        // Update filiale assignment
        $update_result = $wpdb->update(
            "{$wpdb->prefix}ppv_users",
            ['vendor_store_id' => $new_filiale_id],
            ['id' => $scanner_user_id],
            ['%d'],
            ['%d']
        );

        if ($update_result === false) {
            ppv_log("❌ [PPV_Scanner] Filiale update failed: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Fehler beim Ändern der Filiale']);
            return;
        }

        // Get new filiale name for response
        $new_filiale = $wpdb->get_row($wpdb->prepare(
            "SELECT name, city FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $new_filiale_id
        ));

        $filiale_display = $new_filiale->name ?? 'N/A';
        if (!empty($new_filiale->city)) {
            $filiale_display .= ' - ' . $new_filiale->city;
        }

        ppv_log("✅ [PPV_Scanner] Filiale updated: Scanner ID={$scanner_user_id}, Email={$scanner_user->email}, New Filiale={$filiale_display}");

        wp_send_json_success([
            'message' => "✅ Filiale erfolgreich geändert!",
            'new_filiale' => $filiale_display
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

// Register AJAX handlers for universal feedback
add_action('wp_ajax_ppv_submit_feedback', ['PPV_Signup', 'ajax_submit_feedback']);
add_action('wp_ajax_nopriv_ppv_submit_feedback', ['PPV_Signup', 'ajax_submit_feedback']);

// Register AJAX handlers for scanner user management
add_action('wp_ajax_ppv_create_scanner_user', ['PPV_Signup', 'ajax_create_scanner_user']);
add_action('wp_ajax_nopriv_ppv_create_scanner_user', ['PPV_Signup', 'ajax_create_scanner_user']);
add_action('wp_ajax_ppv_reset_scanner_password', ['PPV_Signup', 'ajax_reset_scanner_password']);
add_action('wp_ajax_nopriv_ppv_reset_scanner_password', ['PPV_Signup', 'ajax_reset_scanner_password']);
add_action('wp_ajax_ppv_toggle_scanner_status', ['PPV_Signup', 'ajax_toggle_scanner_status']);
add_action('wp_ajax_nopriv_ppv_toggle_scanner_status', ['PPV_Signup', 'ajax_toggle_scanner_status']);
add_action('wp_ajax_ppv_update_scanner_filiale', ['PPV_Signup', 'ajax_update_scanner_filiale']);
add_action('wp_ajax_nopriv_ppv_update_scanner_filiale', ['PPV_Signup', 'ajax_update_scanner_filiale']);

PPV_Signup::hooks();

