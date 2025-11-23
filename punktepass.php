<?php
/**
 * Plugin Name: PunktePass
 * Description: Digitales Treuepunkt-System mit QR-Code, PWA und POS Integration.
 * Version: 1.0.2
 * Author: Erik Borota
 */

if (!defined('ABSPATH')) exit;

// ========================================
// 🔧 CORE CONSTANTS
// ========================================
define('PPV_VERSION', '1.0.2');
define('PPV_PLUGIN_FILE', __FILE__);
define('PPV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PPV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PPV_CSS_LOCKDOWN', true);

// ========================================
// 🐛 DEBUG MODE - SET TO false FOR PRODUCTION!
// ========================================
define('PPV_DEBUG', false);

/**
 * Global debug logger - only logs when PPV_DEBUG is true
 */
function ppv_log($msg) {
    if (defined('PPV_DEBUG') && PPV_DEBUG) {
        error_log($msg);
    }
}

// ========================================
// 📡 ABLY REAL-TIME CONFIG
// ========================================
define('PPV_ABLY_API_KEY', 'jKxtxA.r58iZQ:6SXOogAhhlFxnOsDxOAfX5KYWYcbtKbHNnNvIYmcuqQ');

// ========================================
// 🔐 SESSION INIT (Early Priority)
// ========================================
// ✅ REMOVED: Session is now handled by PPV_SessionBridge with proper cookie params
// Having duplicate session_start() with priority 1 caused race condition where
// session could start with default PHP settings instead of custom domain/lifetime
// add_action('init', function () {
//     if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
//         @session_start();
//     }
// }, 1);

// ========================================
// 🔒 SECURITY HEADERS (PWA-compatible)
// ========================================
add_action('send_headers', function() {
    if (!headers_sent()) {
        // Prevent clickjacking attacks
        header('X-Frame-Options: SAMEORIGIN');

        // Prevent MIME-sniffing
        header('X-Content-Type-Options: nosniff');

        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');

        // Referrer policy for privacy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions policy (restrict features)
        header('Permissions-Policy: geolocation=(self), camera=(self), microphone=()');

        // Note: CSP is not added here to avoid breaking OAuth integrations
        // (Google, Facebook, TikTok login require external scripts)
    }
});

// ========================================
// 🛡️ CENTRAL HELPERS
// ========================================

/**
 * Detect if current page is login page
 * Checks: Shortcode, Page Slug, URL
 */
function ppv_is_login_page() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Quick URL check (works immediately)
    if (strpos($uri, '/login') !== false || 
        strpos($uri, '/anmelden') !== false || 
        strpos($uri, '/bejelentkezes') !== false ||
        strpos($uri, '/signup') !== false || 
        strpos($uri, '/registrierung') !== false || 
        strpos($uri, '/regisztracio') !== false) {
        return true;
    }
    
    // Fallback to post check (if available)
    global $post;
    if (isset($post->post_content)) {
        if (
            has_shortcode($post->post_content, 'ppv_login_form') || 
            has_shortcode($post->post_content, 'ppv_signup')) {
            return true;
        }
    }
    
    // Page slug check
    if (is_page(['login', 'bejelentkezes', 'anmelden', 'signup', 'registrierung', 'regisztracio'])) {
        return true;
    }
    
    return false;
}
/**
 * Check if user is handler/store
 * Handler types: store, handler, vendor, admin (NOT scanner - they have limited access)
 */
function ppv_is_handler_session() {
    return !empty($_SESSION['ppv_user_type']) &&
           in_array($_SESSION['ppv_user_type'], ['store', 'handler', 'vendor', 'admin']);
}

// ========================================
// 🔒 REST AUTH
// ========================================
add_filter('rest_authentication_errors', function ($result) {
    if (!empty($result)) return $result;

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Allow PunktePass REST endpoints
    if (str_contains($uri, '/wp-json/ppv/v1/') || str_contains($uri, '/wp-json/punktepass/v1/')) {
        
        // Anonymous endpoints
        $anon_endpoints = [
            '/wp-json/ppv/v1/auth/',
            '/wp-json/ppv/v1/stores/list',
            '/wp-json/ppv/v1/bridge',
            '/wp-json/ppv/v1/pos/login',
            '/wp-json/ppv/v1/pos/scan',
            '/wp-json/ppv/v1/pos/dock',
            '/wp-json/ppv/v1/pos/redeem',
            '/wp-json/ppv/v1/pos/stats',
            '/wp-json/punktepass/v1/pos/scan',
            '/wp-json/punktepass/v1/pos/dock',
        ];
        
        foreach ($anon_endpoints as $endpoint) {
            if (str_contains($uri, $endpoint)) return true;
        }
        
        // Bearer token auth
        if (isset($_SERVER['HTTP_AUTHORIZATION']) && class_exists('PPV_Auth')) {
            if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
                $request = new WP_REST_Request('GET', '');
                $request->set_param('token', trim($m[1]));
                $user_id = PPV_Auth::get_user_from_token($request);
                if ($user_id > 0) {
                    wp_set_current_user($user_id);
                    return true;
                }
            }
        }
        
        // Query param token
        if (isset($_GET['token']) && class_exists('PPV_Auth')) {
            $r = new WP_REST_Request('GET', '');
            $r->set_param('token', sanitize_text_field($_GET['token']));
            $uid = PPV_Auth::get_user_from_token($r);
            if ($uid > 0) {
                wp_set_current_user($uid);
                return true;
            }
        }
    }

    // WP user auth
    if (is_user_logged_in()) {
        error_log("✅ [REST_AUTH] WordPress user logged in: " . get_current_user_id());
        return true;
    }

    // ✅ SESSION auth (Google/Facebook/TikTok login)
    error_log("🔍 [REST_AUTH] Starting session auth check...");
    error_log("🔍 [REST_AUTH] URI: " . ($_SERVER['REQUEST_URI'] ?? 'none'));
    error_log("🔍 [REST_AUTH] ppv_user_token cookie: " . (isset($_COOKIE['ppv_user_token']) ? 'EXISTS (len=' . strlen($_COOKIE['ppv_user_token']) . ')' : 'MISSING'));
    error_log("🔍 [REST_AUTH] Session status before: " . session_status() . " (1=disabled, 2=active, 3=none)");

    // Start session and restore from token if needed
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
        error_log("🔍 [REST_AUTH] Session started, new status: " . session_status());
    }

    error_log("🔍 [REST_AUTH] Session ID: " . (session_status() === PHP_SESSION_ACTIVE ? session_id() : 'NONE'));
    error_log("🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: " . ($_SESSION['ppv_user_id'] ?? 'EMPTY'));
    error_log("🔍 [REST_AUTH] PPV_SessionBridge class exists: " . (class_exists('PPV_SessionBridge') ? 'YES' : 'NO'));

    // Restore session from ppv_user_token cookie
    if (class_exists('PPV_SessionBridge') && empty($_SESSION['ppv_user_id'])) {
        error_log("🔄 [REST_AUTH] Calling PPV_SessionBridge::restore_from_token()...");
        PPV_SessionBridge::restore_from_token();
        error_log("🔍 [REST_AUTH] ppv_user_id in session AFTER restore: " . ($_SESSION['ppv_user_id'] ?? 'STILL EMPTY'));
    }

    // Check if session has valid user_id
    if (!empty($_SESSION['ppv_user_id'])) {
        error_log("✅ [REST_AUTH] Session user authenticated: user_id=" . $_SESSION['ppv_user_id']);
        return true;
    }

    error_log("❌ [REST_AUTH] No authentication found - returning 401 error");
    return new WP_Error('rest_forbidden', __('Du bist leider nicht berechtigt, diese Aktion durchzuführen.'), ['status' => 401]);
});

// ========================================
// 📦 MODULE LOADING
// ========================================
$core_modules = [
 'includes/ppv-security-guard.php',
    'includes/class-ppv-permissions.php',
    'includes/class-ppv-session.php',
    'includes/ppv-auto-debug.php',
    'includes/class-ppv-core.php',
    'includes/class-ppv-rest.php',
    'includes/class-ppv-pages.php',
    'includes/class-ppv-settings.php',
    'includes/class-ppv-public.php',
    'includes/class-ppv-store-public.php',
    'includes/class-ppv-qr.php',
    'includes/class-ppv-qr-generator.php',
    'includes/class-ppv-rewards.php',
    'includes/class-ppv-redeem.php',
    'includes/class-ppv-redeem-admin.php',
    'includes/class-ppv-stats.php',
    'includes/class-ppv-user-dashboard.php',
    'includes/class-ppv-my-points.php',
    'includes/class-ppv-my-points-rest.php',
    'includes/class-ppv-belohnungen.php',
    'includes/class-ppv-user-settings.php',
    'includes/class-ppv-login.php',
    'includes/class-ppv-logout.php',
    'includes/class-ppv-account-delete.php',
    'includes/class-ppv-auth.php',
    'includes/class-ppv-bridge.php',
    'includes/class-ppv-pwa-bridge.php',
    'includes/class-ppv-lang.php',
    'includes/class-ppv-lang-switcher.php',
    // 'includes/class-ppv-campaigns.php', // REMOVED: Campaigns now handled by class-ppv-qr.php
    'includes/class-ppv-poster.php',
    'includes/class-ppv-bonus-days.php',
    'includes/class-ppv-filiale.php',
    'includes/api/ppv-stores.php',
    'includes/pp-profile-lite.php',
    'includes/tools/generate-pos-keys.php',
    'includes/class-ppv-sessionbridge.php',
    'includes/class-ppv-bottom-nav.php',
    'includes/class-ppv-pos-devices.php',
    // 'includes/class-ppv-scanner.php', // DISABLED: Conflicts with class-ppv-qr.php endpoint (punktepass/v1/pos/scan)
     'includes/ppv-signup.php',
     'includes/class-ppv-analytics-api.php' ,
     'includes/class-ppv-theme-handler.php',
     'includes/class-ppv-rewards-management.php',
     'includes/class-ppv-invoices.php' ,
     'includes/class-ppv-receipts.php' ,
     'includes/class-ppv-expense-receipt.php' ,
     'includes/class-ppv-onboarding.php',
     'includes/admin/class-ppv-admin-handlers.php',
     'includes/class-ppv-legal.php',
     'includes/class-ppv-user-level.php',
     'includes/class-ppv-vip-settings.php',
     'includes/class-ppv-ably.php',

];

// Conditional POS modules
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (str_contains($uri, 'pos') || str_contains($uri, 'kasse') || str_contains($uri, 'bridge') || str_contains($uri, 'terminal')) {
    $core_modules = array_merge($core_modules, [
        'includes/class-ppv-pos-admin.php',
        'includes/class-ppv-pos-rest.php',
        'includes/api/ppv-pos.php',
        'includes/api/ppv-pos-scan.php',
        'includes/api/ppv-pos-api.php',
        'includes/api/class-ppv-pos-dock.php',
        'includes/class-ppv-admin-vendors.php',
    ]);
}

// Load modules
foreach ($core_modules as $module) {
    $path = PPV_PLUGIN_DIR . ltrim($module, '/');

    // Debug: Log PPV_Permissions loading attempt
    if (strpos($module, 'class-ppv-permissions') !== false) {
        error_log("🔍 [Module Loader] Attempting to load: " . $module);
        error_log("🔍 [Module Loader] Full path: " . $path);
        error_log("🔍 [Module Loader] File exists: " . (file_exists($path) ? 'YES' : 'NO'));
    }

    if (file_exists($path)) {
        require_once $path;

        // Debug: Confirm loading
        if (strpos($module, 'class-ppv-permissions') !== false) {
            error_log("✅ [Module Loader] Successfully loaded: " . $module);
            error_log("✅ [Module Loader] Class exists: " . (class_exists('PPV_Permissions') ? 'YES' : 'NO'));
        }
    } else {
        // Debug: File not found
        if (strpos($module, 'class-ppv-permissions') !== false) {
            error_log("❌ [Module Loader] FILE NOT FOUND: " . $path);
        }
    }
}

// ========================================
// 🎨 THEME & ASSET LOADING (OPTIMIZED)
// ========================================

/**
 * Main CSS Loader - Smart page detection
 * Priority: 100 (after plugins, before lockdown)
 */
 
 // ========================================
// 🔒 GLOBAL INIT LOCK - Prevent duplicate listeners
// ========================================
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'ppv-global-init-lock',
        PPV_PLUGIN_URL . 'assets/js/ppv-global-init-lock.js',
        [],
        PPV_VERSION,
        true  // In footer
    );
}, 1);  // Priority: 1 = LEGELŐBB! Minden más előtt


add_action('wp_enqueue_scripts', function() {
    // 🔹 LOGIN PAGE = DISABLED - Using inline assets in class-ppv-login.php instead
    // This prevents duplicate loading and cache conflicts
    if (ppv_is_login_page()) {
        // ✅ DISABLED - CSS/JS now loaded inline in template with time() cache-busting
        /*
        // CSS
        wp_enqueue_style(
            'ppv-login-light',
            PPV_PLUGIN_URL . 'assets/css/ppv-login-light.css',
            [],
            PPV_VERSION
        );

        // JS - Google OAuth
        wp_enqueue_script(
            'google-platform',
            'https://accounts.google.com/gsi/client',
            [],
            null,
            true
        );

        add_action('wp_enqueue_scripts', function() {
            wp_enqueue_script(
                'ppv-theme-sync',
                PPV_PLUGIN_URL . 'assets/js/ppv-theme-sync.js',
                ['jquery'],
                time(),
                true  // In footer
            );
        }, 99);

        // JS - Login handler
        wp_enqueue_script(
            'ppv-login-js',
            PPV_PLUGIN_URL . 'assets/js/ppv-login.js',
            ['jquery'],
            PPV_VERSION,
            true
        );
        */

        // ✅ DISABLED - window.ppvLogin now set inline in template
        /*
        // Localize
        wp_localize_script('ppv-login-js', 'ppvLogin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ppv_login_nonce'),
            'google_client_id' => defined('PPV_GOOGLE_CLIENT_ID') ? PPV_GOOGLE_CLIENT_ID : get_option('ppv_google_client_id', '453567547051-odmqrinafba8ls8ktp9snlp7d2fpl9q0.apps.googleusercontent.com'),
            'facebook_app_id' => defined('PPV_FACEBOOK_APP_ID') ? PPV_FACEBOOK_APP_ID : get_option('ppv_facebook_app_id', '4070833883179463'),
            'tiktok_client_key' => defined('PPV_TIKTOK_CLIENT_KEY') ? PPV_TIKTOK_CLIENT_KEY : get_option('ppv_tiktok_client_key', '9bb6aca5781d007d6c00fe3ed60d6734'),
            'redirect_url' => home_url('/user_dashboard')
        ]);
        */

        return; // Stop - don't load other themes
    }
    
    // 🔹 HANDLER-LIGHT.CSS - Always load globally (contains shared UI components)
    wp_enqueue_style(
        'ppv-handler-light',
        PPV_PLUGIN_URL . 'assets/css/handler-light.css',
        [],
        time()
    );

    // 🔹 HANDLER SESSION = HANDLER DARK THEME (optional override)
    if (ppv_is_handler_session()) {
        $handler_theme = $_COOKIE['ppv_handler_theme'] ?? 'light';
        if ($handler_theme === 'dark') {
            wp_enqueue_style(
                'ppv-handler-dark',
                PPV_PLUGIN_URL . 'assets/css/handler-dark.css',
                ['ppv-handler-light'],
                time()
            );
        }
    }
    
    // 🔹 NORMAL USER = USER THEME PREFERENCE
    $theme = $_COOKIE['ppv_theme'] ?? 'dark';
    $file = $theme === 'light' ? 'ppv-theme-light.css' : 'ppv-theme-dark.css';
    
    wp_enqueue_style('ppv-theme', PPV_PLUGIN_URL . 'assets/css/' . $file, [], PPV_VERSION);
}, 100);

/**
 * JS Loaders - Skip on login
 * Priority: 99-100
 */
add_action('wp_enqueue_scripts', function() {
    if (ppv_is_login_page()) return;

    wp_enqueue_script(
        'ppv-theme-loader',
        PPV_PLUGIN_URL . 'assets/js/ppv-theme-loader.js',
        [],
        PPV_VERSION,
        true
    );

    // Add WP REST nonce for theme switching
    wp_localize_script('ppv-theme-loader', 'ppvTheme', [
        'nonce' => wp_create_nonce('wp_rest'),
        'rest_url' => rest_url('ppv/v1/theme/'),
    ]);
}, 99);

// 🚀 DISABLED: Using Turbo.js instead (faster, more reliable)
// add_action('wp_enqueue_scripts', function() {
//     if (ppv_is_login_page()) return;
//
//     wp_enqueue_script(
//         'ppv-spa-loader',
//         PPV_PLUGIN_URL . 'assets/js/ppv-spa-loader.js',
//         ['jquery'],
//         PPV_VERSION,
//         true
//     );
// }, 100);

/**


/**
 * CSS Lockdown - Remove old/unused CSS
 * Priority: 99999 (last to run)
 */
add_action('wp_enqueue_scripts', function() {
    if (!PPV_CSS_LOCKDOWN) return;
    
    global $wp_styles;
    if (empty($wp_styles->queue)) return;
    
$whitelist = [
    'ppv-theme-dark',
    'ppv-theme-light',
    'ppv-handler',      // Handler theme (light/dark)
    'ppv-handler-light',
    'ppv-handler-dark',
    'ppv-login-light',
    'ppv-login',
    'ppv-toast-points',
    'ppv-vip-settings', // VIP settings
    'remix-icons',      // Icons for VIP settings
    'google-platform',
    'ppv-login-js'
];
    
    foreach ($wp_styles->queue as $handle) {
        if (strpos($handle, 'ppv-') === 0 && !in_array($handle, $whitelist)) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
    }
}, 99999);

// ========================================
// 🧩 MODULE HOOKS
// ========================================
if (class_exists('PPV_Security_Guard')) PPV_Security_Guard::hooks();
if (class_exists('PPV_Core')) PPV_Core::hooks();
if (class_exists('PPV_REST')) PPV_REST::hooks();
if (class_exists('PPV_Pages')) PPV_Pages::hooks();
if (class_exists('PPV_Settings')) PPV_Settings::hooks();
if (class_exists('PPV_Public')) PPV_Public::hooks();
if (class_exists('PPV_QR')) PPV_QR::hooks();
if (class_exists('PPV_Rewards')) PPV_Rewards::hooks();
if (class_exists('PPV_Redeem')) PPV_Redeem::hooks();
if (class_exists('PPV_Redeem_Admin')) PPV_Redeem_Admin::hooks();
if (class_exists('PPV_Stats')) PPV_Stats::hooks();
// if (class_exists('PPV_Campaigns')) PPV_Campaigns::hooks(); // REMOVED: Campaigns now handled by class-ppv-qr.php
if (class_exists('PPV_User_Dashboard')) PPV_User_Dashboard::hooks();
if (class_exists('PPV_Profile_Lite')) PPV_Profile_Lite::hooks();
if (class_exists('PPV_POS_Devices')) PPV_POS_Devices::hooks();
if (class_exists('PPV_POS_Admin')) PPV_POS_Admin::hooks();
if (class_exists('PPV_POS_REST')) PPV_POS_REST::hooks();
if (class_exists('PPV_Bottom_Nav')) PPV_Bottom_Nav::hooks();
// if (class_exists('PPV_Camera_Scanner')) PPV_Camera_Scanner::hooks(); // DISABLED: Conflicts with PPV_QR endpoint
if (class_exists('PPV_Logout')) PPV_Logout::hooks();
if (class_exists('PPV_Account_Delete')) PPV_Account_Delete::hooks();
if (class_exists('PPV_My_Points')) PPV_My_Points::hooks();  // ← ÚJ!

if (class_exists('PPV_Theme_Handler')) PPV_Theme_Handler::hooks();


// Vendor/User Signup
foreach (['pp-vendor-signup.php', 'pp-user-signup.php'] as $signup) {
    $path = PPV_PLUGIN_DIR . 'includes/' . $signup;
    if (file_exists($path)) {
        require_once $path;
        $class = str_contains($signup, 'vendor') ? 'PPV_Vendor_Signup' : 'PPV_User_Signup';
        if (class_exists($class)) $class::hooks();
    }
}

// ========================================
// 🎨 PWA META TAGS
// ========================================
add_action('wp_head', function () { ?>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#fafdff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="PunktePass">
    <link rel="apple-touch-icon" href="<?php echo PPV_PLUGIN_URL; ?>assets/img/icons/icon-192.png">
    <link rel="apple-touch-startup-image" href="<?php echo PPV_PLUGIN_URL; ?>assets/img/icons/icon-512.png">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php }, 1);



// ========================================
// 🔄 SERVICE WORKER REGISTRATION
// ========================================
add_action('wp_footer', function() {
    if (is_admin()) return;
    if (ppv_is_login_page()) return; // Skip on login
    ?>
    <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' })
          .then(reg => {
            console.log('✅ SW registered:', reg.scope);
            
            // Auto-update check
            reg.addEventListener('updatefound', () => {
              const newWorker = reg.installing;
              newWorker.addEventListener('statechange', () => {
                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                  console.log('🔄 New SW version available');
                  // Auto reload without asking
                  setTimeout(() => window.location.reload(), 1000);
                }
              });
            });
          })
          .catch(err => console.warn('⚠️ SW registration failed:', err));
      });
    }
    </script>
    <?php
}, 1);



// ========================================
// ⚡ PWA SPLASH LOADER (Turbo-compatible)
// ========================================
add_action('wp_head', function() {
    if (ppv_is_login_page()) return; // Skip on login
    ?>
    <style>
      html,body{margin:0;padding:0;background:#0b0f17;height:100%;}
      #ppv-loader{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:#0b0f17;z-index:999999;transition:opacity .4s ease;}
      #ppv-loader.fadeout{opacity:0;pointer-events:none;}
      #ppv-loader.hidden{display:none;}
      .ppv-spinner{width:60px;height:60px;border:5px solid rgba(0,191,255,0.25);border-top-color:#00bfff;border-radius:50%;animation:ppvspin 1s linear infinite;}
      @keyframes ppvspin{to{transform:rotate(360deg)}}
    </style>
    <div id="ppv-loader" data-turbo-permanent><div class="ppv-spinner"></div></div>
    <script>
      (function(){
        var loader = document.getElementById("ppv-loader");
        if (!loader) return;

        // Check if this is a Turbo navigation (loader should already be hidden)
        if (window.ppvLoaderInitialized) {
          loader.classList.add("fadeout", "hidden");
          return;
        }

        // Initial page load - show loader briefly then hide
        window.addEventListener("load", function() {
          setTimeout(function() {
            loader.classList.add("fadeout");
            setTimeout(function() { loader.classList.add("hidden"); }, 400);
          }, 300);
          window.ppvLoaderInitialized = true;
        });

        // 🚀 Turbo: Hide loader on navigation
        document.addEventListener("turbo:before-fetch-request", function() {
          // Don't show big loader for Turbo - bottom nav has its own indicator
        });
        document.addEventListener("turbo:load", function() {
          loader.classList.add("fadeout", "hidden");
        });
      })();
    </script>
    <?php
}, 2);

// ========================================
// 🍞 TOAST SYSTEM (Footer)
// ========================================
add_action('wp_footer', function () {
    if (is_admin()) return;
    ?>
    <script>
    if (!window.ppvToast) {
      window.ppvToast = function (msg, type = "info") {
        const box = document.createElement("div");
        box.className = "ppv-toast " + type;
        box.innerHTML = msg;
        document.body.appendChild(box);
        setTimeout(() => box.classList.add("show"), 10);
        setTimeout(() => box.classList.remove("show"), 2800);
        setTimeout(() => box.remove(), 3100);
      };
      
      const css = document.createElement("style");
      css.textContent = `
      .ppv-toast {
        position: fixed;bottom: 40px;left: 50%;transform: translateX(-50%) scale(0.9);
        background: #111;color: #fff;font-family: Inter,sans-serif;font-size: 15px;
        padding: 10px 18px;border-radius: 10px;box-shadow: 0 3px 10px rgba(0,0,0,0.3);
        opacity: 0;transition: all 0.3s ease;z-index: 999999;
      }
      .ppv-toast.show { opacity: 1; transform: translateX(-50%) scale(1); }
      .ppv-toast.success { background: #00c853; }
      .ppv-toast.error { background: #e53935; }
      .ppv-toast.warning { background: #ffb300; }
      `;
      document.head.appendChild(css);
    }
    </script>
    <?php
}, 999);

// ========================================
// 🏪 STORE URL REWRITE
// ========================================
add_action('init', function () {
    add_rewrite_rule('^store/([^/]*)/?$', 'index.php?pagename=store&store=$matches[1]', 'top');
}, 10);

register_activation_hook(__FILE__, function () {
    add_rewrite_rule('^store/([^/]*)/?$', 'index.php?pagename=store&store=$matches[1]', 'top');
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

// ========================================
// 📡 REST ENDPOINTS
// ========================================
add_action('rest_api_init', function () {
    register_rest_route('ppv/v1', '/user/last_scan', [
        'methods'  => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $req) {
            global $wpdb;
            $user_id = intval($req->get_param('user_id'));
            if (!$user_id) {
                return new WP_REST_Response(['success' => false, 'message' => 'Missing user_id'], 400);
            }
            
            $last = $wpdb->get_row($wpdb->prepare("
                SELECT p.*, s.company_name AS store_name
                FROM {$wpdb->prefix}ppv_points p
                LEFT JOIN {$wpdb->prefix}ppv_stores s ON p.store_id = s.id
                WHERE p.user_id = %d
                ORDER BY p.created DESC
                LIMIT 1
            ", $user_id));
            
            if (!$last) {
                return new WP_REST_Response(['success' => false, 'message' => 'No scans yet'], 200);
            }
            
            return new WP_REST_Response([
                'success' => true,
                'points'  => intval($last->points),
                'store'   => $last->store_name ?: 'PunktePass',
                'created' => $last->created,
            ], 200);
        },
    ]);
});

// ========================================
// 🚪 DEEP LOGOUT HANDLER
// ========================================
add_action('init', function() {
    if (!isset($_GET['ppv_logout']) || $_GET['ppv_logout'] != 1) return;
    
    // WP logout
    if (is_user_logged_in()) wp_logout();
    
    // Clear cookies
    if (!empty($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        $host_parts = explode('.', $host);
        $root_domain = count($host_parts) > 2 ? implode('.', array_slice($host_parts, -2)) : $host;
        
        foreach ($_COOKIE as $key => $val) {
            foreach (['', '/', '/wp-admin', '/wp-content'] as $path) {
                foreach ([$host, '.' . $host, $root_domain, '.' . $root_domain] as $domain) {
                    @setcookie($key, '', time() - 3600, $path, $domain, true, true);
                }
            }
        }
    }
    
    // Clear session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    
    // JS cleanup + redirect
    echo "<!DOCTYPE html><html lang='de'><head><meta charset='utf-8'>
    <script>
    try {
        localStorage.clear();
        sessionStorage.clear();
        document.cookie.split(';').forEach(c=>{
            document.cookie=c.trim().split('=')[0]+'=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;';
        });
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(r=>{r.forEach(sw=>sw.unregister());});
        }
        setTimeout(()=>{window.location.href='/login?nocache='+Date.now();},300);
    }catch(e){console.warn(e);}
    </script>
    </head><body style='background:#0b0d10;color:#fff;font-family:sans-serif;text-align:center;padding-top:50px'>
    <h2>Logout...</h2>
    </body></html>";
    flush();
    exit;
}, 0);

// ========================================
// 🕓 ACTION SCHEDULER
// ========================================
add_action('action_scheduler_init', function () {
    if (function_exists('as_next_scheduled_action') && function_exists('as_schedule_recurring_action')) {
        if (!as_next_scheduled_action('ppv_daily_qr_regen')) {
            as_schedule_recurring_action(time() + 3600, DAY_IN_SECONDS, 'ppv_daily_qr_regen');
        }
    }
});

// ========================================
// 💳 STRIPE DELAYED INIT
// ========================================
add_action('init', function () {
    if (did_action('action_scheduler_init')) {
        if (file_exists(PPV_PLUGIN_DIR . 'includes/class-ppv-stripe.php')) {
            require_once PPV_PLUGIN_DIR . 'includes/class-ppv-stripe.php';
            if (class_exists('PPV_Stripe')) PPV_Stripe::hooks();
        }
        if (file_exists(PPV_PLUGIN_DIR . 'includes/class-ppv-stripe-checkout.php')) {
            require_once PPV_PLUGIN_DIR . 'includes/class-ppv-stripe-checkout.php';
            if (class_exists('PPV_Stripe_Checkout')) PPV_Stripe_Checkout::hooks();
        }
    } else {
        add_action('action_scheduler_init', function () {
            if (file_exists(PPV_PLUGIN_DIR . 'includes/class-ppv-stripe.php')) {
                require_once PPV_PLUGIN_DIR . 'includes/class-ppv-stripe.php';
                if (class_exists('PPV_Stripe')) PPV_Stripe::hooks();
            }
            if (file_exists(PPV_PLUGIN_DIR . 'includes/class-ppv-stripe-checkout.php')) {
                require_once PPV_PLUGIN_DIR . 'includes/class-ppv-stripe-checkout.php';
                if (class_exists('PPV_Stripe_Checkout')) PPV_Stripe_Checkout::hooks();
            }
        });
    }
}, 99);

// ========================================
// 🪲 AUTO DEBUG
// ========================================
if (class_exists('PPV_Auto_Debug')) {
    PPV_Auto_Debug::enable();
}
