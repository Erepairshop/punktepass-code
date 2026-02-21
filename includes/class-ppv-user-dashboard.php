<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – User Dashboard (v4.5 GLOBAL HEADER)
 * ✅ Original working version
 * ✅ + company_name, gallery, social media fields
 * ✅ Distance-based sorting
 * ✅ PWA optimized
 * ✅ GLOBAL HEADER - appears on all pages for logged-in users
 */

class PPV_User_Dashboard {

    const CACHE_KEY_PREFIX = 'ppv_dashboard_';
    const CACHE_TTL = 3600;

    public static function hooks() {
        add_action('init', [__CLASS__, 'maybe_render_standalone'], 1);
        add_shortcode('ppv_user_dashboard', [__CLASS__, 'render_dashboard']);
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_body_open', [__CLASS__, 'render_global_header'], 5); // ← GLOBAL HEADER
    }

    private static function ensure_session() {
        static $session_started = false;
        if ($session_started) return;

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $session_started = true;
    }

    private static function get_user_lang() {
        static $lang = null;
        if ($lang !== null) return $lang;

        // 1. Cookie
        if (isset($_COOKIE['ppv_lang'])) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang']);
        }
        // 2. GET parameter
        elseif (isset($_GET['lang'])) {
            $lang = sanitize_text_field($_GET['lang']);
        }
        // 3. User's saved language from database
        elseif (!empty($_SESSION['ppv_user_id'])) {
            global $wpdb;
            $prefix = $wpdb->prefix;
            $user_lang = $wpdb->get_var($wpdb->prepare(
                "SELECT language FROM {$prefix}ppv_users WHERE id=%d LIMIT 1",
                intval($_SESSION['ppv_user_id'])
            ));
            if (!empty($user_lang) && in_array($user_lang, ['de', 'hu', 'ro', 'en'])) {
                $lang = $user_lang;
            }
        }
        // 4. WordPress locale fallback
        if (empty($lang)) {
            $lang = substr(get_locale(), 0, 2);
        }

        return in_array($lang, ['de', 'hu', 'ro', 'en']) ? $lang : 'ro'; // Default Romanian
    }

    private static function get_safe_user_id() {
        global $wpdb;
        self::ensure_session();

        // Priority 1: Session ppv_user_id (already the correct ppv_users.id)
        if (!empty($_SESSION['ppv_user_id'])) {
            return intval($_SESSION['ppv_user_id']);
        }

        // Priority 2: WordPress user - but we need to find the corresponding ppv_users.id!
        $wp_uid = get_current_user_id();
        if ($wp_uid > 0) {
            // ✅ FIX: Lookup ppv_users.id by WordPress user's email
            // WordPress user ID is NOT the same as ppv_users.id!
            $wp_user = get_userdata($wp_uid);
            if ($wp_user && $wp_user->user_email) {
                $ppv_user_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email = %s LIMIT 1",
                    $wp_user->user_email
                ));

                if ($ppv_user_id) {
                    // Cache in session for future requests
                    $_SESSION['ppv_user_id'] = $ppv_user_id;
                    ppv_log("✅ [PPV_Dashboard] get_safe_user_id: Mapped WP user #{$wp_uid} ({$wp_user->user_email}) to ppv_users.id={$ppv_user_id}");
                    return intval($ppv_user_id);
                } else {
                    ppv_log("⚠️ [PPV_Dashboard] get_safe_user_id: WP user #{$wp_uid} ({$wp_user->user_email}) NOT FOUND in ppv_users table");
                }
            }
            // Fallback: return WordPress user ID (but this may not have points!)
            ppv_log("⚠️ [PPV_Dashboard] get_safe_user_id: Falling back to WP user ID #{$wp_uid} (may not have points)");
            return $wp_uid;
        }

        if (!empty($_SESSION['ppv_store_user'])) {
            return 0;
        }

        return 0;
    }

    private static function get_user_stats($uid) {
        global $wpdb;

        if ($uid <= 0) {
            return ['points' => 0, 'rewards' => 0];
        }

        $cache_key = self::CACHE_KEY_PREFIX . 'stats_' . $uid;
        $cached = wp_cache_get($cache_key);
        if ($cached) {
            return $cached;
        }

        $prefix = $wpdb->prefix;

        // ✅ FIX: Only count rewards from stores where user has at least 1 point
        $result = $wpdb->get_row($wpdb->prepare("
            SELECT
                COALESCE(SUM(p.points), 0) as points,
                (
                    SELECT COUNT(DISTINCT r.id)
                    FROM {$prefix}ppv_rewards r
                    INNER JOIN {$prefix}ppv_stores s ON r.store_id = s.id
                    INNER JOIN (
                        SELECT store_id
                        FROM {$prefix}ppv_points
                        WHERE user_id = %d
                        GROUP BY store_id
                        HAVING SUM(points) >= 1
                    ) AS user_stores ON r.store_id = user_stores.store_id
                    WHERE s.active = 1
                ) as rewards
            FROM {$prefix}ppv_points p
            WHERE p.user_id=%d
        ", $uid, $uid));

        $stats = [
            'points' => (int)($result->points ?? 0),
            'rewards' => (int)($result->rewards ?? 0)
        ];

        wp_cache_set($cache_key, $stats, '', self::CACHE_TTL);
        return $stats;
    }

    private static function get_user_qr_token($uid) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        if ($uid <= 0) return null;

        $token = $wpdb->get_var($wpdb->prepare("
            SELECT token FROM {$prefix}ppv_tokens
            WHERE entity_type='user' AND entity_id=%d
            AND expires_at > NOW()
            ORDER BY created_at DESC LIMIT 1
        ", $uid));

        if (empty($token)) {
            $token = wp_generate_password(16, false);
            $wpdb->insert("{$prefix}ppv_tokens", [
                'entity_type' => 'user',
                'entity_id' => $uid,
                'user_id' => $uid,  // ✅ FIX: Set both user_id AND entity_id
                'token' => $token,
                'created_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
            ]);

            // 🔄 INVALIDATE QR CACHE when new token is created
            // This ensures the QR image is regenerated with the new token
            $qr_dir = WP_CONTENT_DIR . '/uploads/ppv_qr/';
            $qr_file = $qr_dir . "user_{$uid}.png";
            if (file_exists($qr_file)) {
                @unlink($qr_file);
                ppv_log("🔄 [PPV_Dashboard] QR cache invalidated for user {$uid} - new token created");
            }
        }

        return $token;
    }

    private static function get_user_email($uid) {
        self::ensure_session();

        if (!empty($_SESSION['ppv_user_email'])) {
            return sanitize_email($_SESSION['ppv_user_email']);
        }

        // Try WordPress user first
        $user = get_userdata($uid);
        if ($user && $user->user_email) {
            return $user->user_email;
        }

        // Fallback: lookup in ppv_users table (repair login stores ppv_users.id, not WP user ID)
        if ($uid > 0) {
            global $wpdb;
            $email = $wpdb->get_var($wpdb->prepare(
                "SELECT email FROM {$wpdb->prefix}ppv_users WHERE id = %d LIMIT 1",
                $uid
            ));
            if ($email) {
                $_SESSION['ppv_user_email'] = $email;
                return sanitize_email($email);
            }
        }

        return '';
    }

    private static function generate_qr_code($uid, $token) {
        if ($uid <= 0 || empty($token)) return '';

        $qr_dir = WP_CONTENT_DIR . '/uploads/ppv_qr/';
        if (!file_exists($qr_dir)) {
            wp_mkdir_p($qr_dir);
        }

        $qr_file = $qr_dir . "user_{$uid}.png";

        if (file_exists($qr_file)) {
            $age = time() - filemtime($qr_file);
            if ($age < 86400) {
                return content_url("uploads/ppv_qr/user_{$uid}.png") . '?v=' . md5_file($qr_file);
            }
        }

        $data = "PPU{$uid}{$token}";
        $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=" . urlencode($data);

        $response = wp_remote_get($qr_api, ['timeout' => 5]);
        if (is_wp_error($response)) {
            ppv_log("🚫 [PPV_Dashboard] QR generation failed: " . $response->get_error_message());
            return '';
        }

        $img = wp_remote_retrieve_body($response);
        if (!empty($img)) {
            file_put_contents($qr_file, $img);
            return content_url("uploads/ppv_qr/user_{$uid}.png") . '?v=' . md5($img);
        }

        return '';
    }

    private static function cleanup_tokens($uid) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        if ($uid <= 0) return;

        $wpdb->query($wpdb->prepare("
            DELETE FROM {$prefix}ppv_tokens
            WHERE entity_type='user' AND entity_id=%d
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $uid));

        $wpdb->query($wpdb->prepare("
            DELETE FROM {$prefix}ppv_tokens
            WHERE entity_type='user' AND entity_id=%d
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM {$prefix}ppv_tokens
                    WHERE entity_type='user' AND entity_id=%d
                    ORDER BY created_at DESC
                    LIMIT 3
                ) t
            )
        ", $uid, $uid));
    }

private static function is_store_open($opening_hours) {
    if (empty($opening_hours)) {
        return false;
    }

    $now = current_time('timestamp');
    
    // ✅ FIX: Map English day names to 2-char codes
    $day_map = [
        'monday' => 'mo',
        'tuesday' => 'di',
        'wednesday' => 'mi',
        'thursday' => 'do',
        'friday' => 'fr',
        'saturday' => 'sa',
        'sunday' => 'so'
    ];
    
    $day_name = strtolower(date('l', $now));
    $day = $day_map[$day_name] ?? 'mo';
    
    $current_time = date('H:i', $now);

    $hours = json_decode($opening_hours, true);
    if (!is_array($hours)) {
        return false;
    }

    ppv_log("🕒 [Open Check] Day: {$day}, Time: {$current_time}, Data: " . json_encode($hours));

    // ✅ NEW FORMAT: Check if it's closed
    if (!isset($hours[$day])) {
        return false;
    }

    $day_hours = $hours[$day];
    
    // ✅ NEW FORMAT: Check closed flag
    if (!is_array($day_hours)) {
        return false;
    }
    
    if (!empty($day_hours['closed'])) {
        ppv_log("🕒 [Open Check] CLOSED flag set for {$day}");
        return false;
    }

    // ✅ NEW FORMAT: Extract von & bis
    $von = $day_hours['von'] ?? '';
    $bis = $day_hours['bis'] ?? '';

    if (empty($von) || empty($bis)) {
        ppv_log("🕒 [Open Check] Empty hours for {$day}: von={$von}, bis={$bis}");
        return false;
    }

    $is_open = ($current_time >= $von && $current_time <= $bis);
    ppv_log("🕒 [Open Check] {$day}: {$von}-{$bis}, Current: {$current_time}, Result: " . ($is_open ? 'NYITVA' : 'ZÁRVA'));
    
    return $is_open;
}

private static function get_today_hours($opening_hours) {
    if (empty($opening_hours)) {
        return '';
    }

    $now = current_time('timestamp');
    
    // ✅ FIX: Map English day names to 2-char codes
    $day_map = [
        'monday' => 'mo',
        'tuesday' => 'di',
        'wednesday' => 'mi',
        'thursday' => 'do',
        'friday' => 'fr',
        'saturday' => 'sa',
        'sunday' => 'so'
    ];
    
    $day_name = strtolower(date('l', $now));
    $day = $day_map[$day_name] ?? 'mo';

    $hours = json_decode($opening_hours, true);
    if (!is_array($hours) || !isset($hours[$day])) {
        return '';
    }

    $day_hours = $hours[$day];
    
    // ✅ NEW FORMAT: Return formatted string
    if (is_array($day_hours)) {
        $von = $day_hours['von'] ?? '';
        $bis = $day_hours['bis'] ?? '';
        $closed = $day_hours['closed'] ?? 0;
        
        if ($closed) {
    return '🔴 ' . (class_exists('PPV_Lang') ? PPV_Lang::t('dashboard_store_closed') : 'Zárva');
        }
        
        return $von && $bis ? "{$von} - {$bis}" : '';
    }

    return '';
}

/** ============================================================
     * 🌐 GLOBAL COMPACT HEADER - COMPLETE WITH CSS + LOGO FIX
     * ============================================================ */
    public static function render_global_header() {
        // 1️⃣ Check login status
        self::ensure_session();
        $uid = self::get_safe_user_id();

        // 2️⃣ Skip on login/signup pages
        if (function_exists('ppv_is_login_page') && ppv_is_login_page()) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $exclude_patterns = ['/login', '/signup', '/anmelden', '/registrierung', '/bejelentkezes', '/regisztracio'];
        foreach ($exclude_patterns as $pattern) {
            if (strpos($uri, $pattern) !== false) {
                return;
            }
        }

        // 3️⃣ If NOT logged in → Show session expired header on ALL protected pages
        if ($uid <= 0) {
            // Public pages that don't need login header
            $public_pages = ['/', '/impressum', '/datenschutz', '/agb', '/kontakt', '/about', '/home', '/partner'];
            $is_public = false;
            $clean_uri = rtrim(parse_url($uri, PHP_URL_PATH), '/');
            if (empty($clean_uri)) $clean_uri = '/';

            foreach ($public_pages as $page) {
                if ($clean_uri === $page) {
                    $is_public = true;
                    break;
                }
            }

            // Show session expired header on all non-public pages
            if (!$is_public) {
                $login_url = home_url('/login');
                ?>
                <div class="ppv-global-header ppv-session-expired">
                    <div class="ppv-header-inner">
                        <div class="ppv-header-left">
                            <span class="ppv-session-msg">
                                <i class="ri-error-warning-line"></i>
                                <?php echo PPV_Lang::t('session_expired'); ?>
                            </span>
                        </div>
                        <div class="ppv-header-right">
                            <a href="<?php echo esc_url(home_url('?ppv_logout=1')); ?>" class="ppv-logout-btn">
                                <i class="ri-logout-box-line"></i>
                                <?php echo PPV_Lang::t('logout'); ?>
                            </a>
                            <a href="<?php echo esc_url($login_url); ?>" class="ppv-login-btn">
                                <i class="ri-login-box-line"></i>
                                <?php echo PPV_Lang::t('login_button'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <style>
                .ppv-session-expired {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    z-index: 99999;
                    background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
                    border-bottom: 1px solid #F59E0B;
                    padding: 12px 16px;
                    padding-top: calc(12px + env(safe-area-inset-top, 0px));
                }
                .ppv-session-expired .ppv-header-inner {
                    max-width: 600px;
                    margin: 0 auto;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 16px;
                }
                .ppv-session-msg {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    color: #92400E;
                }
                .ppv-session-msg i {
                    font-size: 18px;
                }
                .ppv-header-right {
                    display: flex;
                    gap: 8px;
                }
                .ppv-logout-btn {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    padding: 10px 16px;
                    background: rgba(0, 0, 0, 0.1);
                    color: #92400E;
                    border-radius: 10px;
                    font-size: 14px;
                    font-weight: 600;
                    text-decoration: none;
                    transition: all 0.3s ease;
                }
                .ppv-logout-btn:hover {
                    background: #DC2626;
                    color: white;
                }
                .ppv-logout-btn i {
                    font-size: 16px;
                }
                .ppv-login-btn {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    padding: 10px 20px;
                    background: linear-gradient(135deg, #0066FF 0%, #0052CC 100%);
                    color: white;
                    border-radius: 10px;
                    font-size: 14px;
                    font-weight: 600;
                    text-decoration: none;
                    box-shadow: 0 4px 12px rgba(0, 102, 255, 0.3);
                    transition: all 0.3s ease;
                }
                .ppv-login-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 16px rgba(0, 102, 255, 0.4);
                }
                .ppv-login-btn i {
                    font-size: 16px;
                }
                body {
                    padding-top: calc(56px + env(safe-area-inset-top, 0px)) !important;
                }
                </style>
                <?php
            }
            return;
        }

        // 3️⃣ Skip on admin pages
        if (is_admin()) {
            return;
        }

        // 4️⃣ Detect user type (FIXED - ALWAYS CHECK DB IF 'user')
        global $wpdb;

        $user_type = 'user'; // default
        $detection_source = 'default';

        // 🔍 DEBUG: Start detection
        ppv_log("🔍 [PPV_Header] === USER TYPE DETECTION START === User ID: {$uid}");

        // First try session
        if (!empty($_SESSION['ppv_user_type'])) {
            $user_type = $_SESSION['ppv_user_type'];
            $detection_source = 'session';
            ppv_log("✅ [PPV_Header] User type from SESSION: '{$user_type}'");
            
            // ✅ FIX: If session says 'user', double-check the DB!
            // (Session might be stale or incorrect)
            if (strtolower($user_type) === 'user') {
                ppv_log("⚠️ [PPV_Header] Session says 'user', double-checking DB...");
                
                $db_user_type = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_type FROM {$wpdb->prefix}ppv_users WHERE id=%d LIMIT 1",
                    $uid
                ));
                
                if ($db_user_type && strtolower($db_user_type) !== 'user') {
                    ppv_log("✅ [PPV_Header] DB override! Changed from 'user' to '{$db_user_type}'");
                    $user_type = $db_user_type;
                    $detection_source = 'ppv_users (override)';
                    $_SESSION['ppv_user_type'] = $db_user_type; // Update session!
                } else {
                    ppv_log("ℹ️ [PPV_Header] DB confirms: user type is 'user'");
                }
            }
        } 
        // Then check ppv_users table
        else {
            ppv_log("⚠️ [PPV_Header] Session empty, checking ppv_users table...");
            
            $db_user_type = $wpdb->get_var($wpdb->prepare(
                "SELECT user_type FROM {$wpdb->prefix}ppv_users WHERE id=%d LIMIT 1",
                $uid
            ));
            
            if ($db_user_type) {
                $user_type = $db_user_type;
                $detection_source = 'ppv_users';
                $_SESSION['ppv_user_type'] = $db_user_type; // Cache in session
                ppv_log("✅ [PPV_Header] User type from ppv_users: '{$db_user_type}'");
            } 
            // Fallback: Check ppv_stores table (if user is store owner)
            else {
                ppv_log("⚠️ [PPV_Header] Not found in ppv_users, checking ppv_stores...");
                
                $store_check = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                    $uid
                ));
                
                if ($store_check) {
                    $user_type = 'handler';
                    $detection_source = 'ppv_stores';
                    $_SESSION['ppv_user_type'] = 'handler';
                    ppv_log("✅ [PPV_Header] User is store owner → Set as 'handler'");
                } else {
                    ppv_log("❌ [PPV_Header] User #{$uid} NOT FOUND in ppv_users OR ppv_stores!");
                }
            }
        }

        // Clean and check user type
        $user_type_clean = strtolower(trim($user_type));
        $handler_types = ['store', 'handler', 'vendor', 'admin', 'scanner'];
        $is_handler = in_array($user_type_clean, $handler_types);

        // 🔍 DEBUG: Final result
        ppv_log("🔍 [PPV_Header] === DETECTION RESULT ===");
        ppv_log("   User ID: {$uid}");
        ppv_log("   Raw type: '{$user_type}'");
        ppv_log("   Clean type: '{$user_type_clean}'");
        ppv_log("   Source: {$detection_source}");
        ppv_log("   Is Handler: " . ($is_handler ? 'YES ✅' : 'NO ❌'));
        ppv_log("   Valid handler types: " . implode(', ', $handler_types));
        
        if ($is_handler) {
            ppv_log("✅ [PPV_Header] WILL RENDER: User Dashboard + Scanner buttons");
        } else {
            ppv_log("ℹ️ [PPV_Header] WILL RENDER: Points + Rewards stats");
        }
        ppv_log("🔍 [PPV_Header] === DETECTION END ===");

        // 5️⃣ Get user data
        $email = self::get_user_email($uid);
        $stats = self::get_user_stats($uid);
        $lang = self::get_user_lang();

        // 5.1️⃣ Get avatar (if exists)
        $avatar_url = get_user_meta($uid, 'ppv_avatar', true);
        $settings_url = home_url('/einstellungen');

        // 5.2️⃣ Get user level badge (if class exists)
        $level_badge = '';
        if (class_exists('PPV_User_Level')) {
            $level_badge = PPV_User_Level::render_compact_badge($uid, $lang);
        }

        // 6️⃣ Translations
        $translations = [
            'de' => [
                'welcome' => 'PunktePass',
                'points' => 'Punkte',
                'rewards' => 'Prämien',
                'user_dashboard' => 'Benutzer',
                'qr_center' => 'Scanner',
                'logout' => 'Abmelden'
            ],
            'hu' => [
                'welcome' => 'PunktePass',
                'points' => 'Pontok',
                'rewards' => 'Jutalmak',
                'user_dashboard' => 'Felhasználó',
                'qr_center' => 'Scanner',
                'logout' => 'Kijelentkezés'
            ],
            'ro' => [
                'welcome' => 'PunktePass',
                'points' => 'Puncte',
                'rewards' => 'Recompense',
                'user_dashboard' => 'Utilizator',
                'qr_center' => 'Scanner',
                'logout' => 'Deconectare'
            ]
        ];
        $T = $translations[$lang] ?? $translations['de'];

        // 7️⃣ URLs (FIXED - Use PPV_Logout URL!)
        $user_dashboard_url = home_url('/user_dashboard');
        $qr_center_url = home_url('/qr-center');
        $logout_url = site_url('/?ppv_logout=1'); // ← PPV_Logout URL!

        // 8️⃣ Render compact header
        ?>
        <div id="ppv-global-header" class="ppv-compact-header <?php echo $is_handler ? 'ppv-handler-mode' : ''; ?>">
            
            <!-- Logo + Avatar/Email -->
            <div class="ppv-header-left">
                <img src="<?php echo PPV_PLUGIN_URL; ?>assets/img/logo.webp" alt="PunktePass" class="ppv-header-logo-tiny" width="32" height="32">
                <?php if (!empty($avatar_url)): ?>
                <!-- Avatar (links to settings) -->
                <a href="<?php echo esc_url($settings_url); ?>" class="ppv-header-avatar-link" title="<?php echo esc_attr($email); ?>">
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar" class="ppv-header-avatar" width="36" height="36">
                </a>
                <?php else: ?>
                <!-- Email (fallback) -->
                <div class="ppv-user-info">
                    <span class="ppv-user-email"><?php echo esc_html($email); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Stats (USER only) - Points only, rewards removed -->
            <?php if (!$is_handler): ?>
            <div class="ppv-header-stats">
                <?php if (!empty($level_badge)): ?>
                <!-- User Level Badge -->
                <?php echo $level_badge; ?>
                <?php endif; ?>
                <div class="ppv-stat-mini">
                    <i class="ri-star-fill"></i>
                    <span id="ppv-global-points"><?php echo esc_html($stats['points']); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Navigation Buttons -->
            <div class="ppv-header-nav">
                
                <?php if ($is_handler): ?>
                <!-- User Dashboard (HANDLER only) -->
               <a href="<?php echo esc_url($user_dashboard_url); ?>" 
   class="ppv-nav-btn ppv-btn-user"
   title="<?php echo esc_attr($T['user_dashboard']); ?>"
   onclick="window.location.href=this.href; return false;">
                    <i class="ri-user-line"></i>
                    <span class="ppv-nav-text"><?php echo esc_html($T['user_dashboard']); ?></span>
                </a>
                
                <!-- QR Center (HANDLER only) -->
                <a href="<?php echo esc_url($qr_center_url); ?>" 
   class="ppv-nav-btn ppv-btn-scanner"
   title="<?php echo esc_attr($T['qr_center']); ?>"
   onclick="window.location.href=this.href; return false;">
                    <i class="ri-qr-scan-line"></i>
                    <span class="ppv-nav-text"><?php echo esc_html($T['qr_center']); ?></span>
                </a>
                <?php endif; ?>
                
                <!-- Logout (EVERYONE - PPV_Logout integration) -->
                <a href="<?php echo esc_url($logout_url); ?>" 
                   class="ppv-nav-btn ppv-btn-logout"
                   id="ppv-logout-btn"
                   title="<?php echo esc_attr($T['logout']); ?>">
                    <i class="ri-logout-box-line"></i>
                    <span class="ppv-nav-text"><?php echo esc_html($T['logout']); ?></span>
                </a>
            </div>
            
            <!-- Settings (Language + Theme) -->
            <div class="ppv-header-settings">
                <?php echo PPV_Lang_Switcher::render(); ?>
           <button id="ppv-theme-toggle-global" class="ppv-theme-btn-mini" type="button">
    <i id="ppv-theme-icon" class="ri-moon-line"></i>
</button>
            </div>
        </div>

        

        <!-- ============================================================
             🎯 JAVASCRIPT
             ============================================================ -->
        <script>
        // 🚀 Turbo-compatible initialization function
        function initGlobalHeaderJS() {
            // Prevent double initialization
            if (document.getElementById('ppv-global-header')?.dataset.jsInit === 'true') {
                console.log('⏭️ [Header] Already initialized, skipping');
                return;
            }
            const header = document.getElementById('ppv-global-header');
            if (header) header.dataset.jsInit = 'true';

            console.log('🚀 [Header] Initializing global header JS (Turbo-compatible)');

            // ============================================================
            // LOGOUT WITH CACHE CLEARING
            // ============================================================
            const logoutBtn = document.getElementById('ppv-logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    console.log('🚪 [PPV] Logout initiated - clearing cache...');
                    
                    // 1️⃣ Clear localStorage
                    try {
                        localStorage.clear();
                        console.log('✅ [PPV] localStorage cleared');
                    } catch (ex) {
                        console.error('❌ [PPV] localStorage clear failed:', ex);
                    }
                    
                    // 2️⃣ Clear sessionStorage
                    try {
                        sessionStorage.clear();
                        console.log('✅ [PPV] sessionStorage cleared');
                    } catch (ex) {
                        console.error('❌ [PPV] sessionStorage clear failed:', ex);
                    }
                    
                    // 3️⃣ Clear cookies
                    try {
                        document.cookie.split(";").forEach(c => {
                            const eq = c.indexOf("=");
                            const name = eq > -1 ? c.substr(0, eq).trim() : c.trim();
                            document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
                            document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=" + window.location.hostname;
                            document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=." + window.location.hostname;
                        });
                        console.log('✅ [PPV] Cookies cleared');
                    } catch (ex) {
                        console.error('❌ [PPV] Cookie clear failed:', ex);
                    }
                    
                    // 4️⃣ Clear service worker cache
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.getRegistrations().then(regs => {
                            regs.forEach(reg => {
                                reg.unregister();
                                console.log('✅ [PPV] Service worker unregistered');
                            });
                        }).catch(ex => {
                            console.error('❌ [PPV] Service worker clear failed:', ex);
                        });
                    }
                    
                    if ('caches' in window) {
                        caches.keys().then(names => {
                            names.forEach(name => {
                                caches.delete(name);
                                console.log('✅ [PPV] Cache deleted:', name);
                            });
                        }).catch(ex => {
                            console.error('❌ [PPV] Cache clear failed:', ex);
                        });
                    }
                    
                    // 5️⃣ Redirect to PPV_Logout URL
                    console.log('🚪 [PPV] Redirecting to:', this.getAttribute('href'));
                    setTimeout(() => {
                        window.location.href = this.getAttribute('href');
                    }, 150);
                });
            }
            
            // ============================================================
            // THEME TOGGLE - Handled by ppv-theme-loader.js (v2.3+)
            // ============================================================
            // REMOVED: Duplicate listener was conflicting with theme-loader.js
            // The theme-loader.js now handles all theme switching logic

            // Language switch is now handled by PPV_Lang_Switcher component

            <?php if (!$is_handler): ?>
            // ============================================================
            // POINTS SYNC (USER ONLY) - Ably on dashboard, polling fallback elsewhere
            // ============================================================
            // Skip header polling if dashboard page (dashboard JS handles Ably sync)
            if (document.getElementById('ppv-dashboard-root')) {
                console.log('📡 [Header] Dashboard detected - Ably handles sync');
            } else if (!window.PPV_HEADER_POLLING_ID) {
                // Fallback polling for non-dashboard pages (30s interval)
                console.log('🔄 [Header] Starting polling fallback (30s)');
                window.PPV_HEADER_POLLING_ID = setInterval(async () => {
                    try {
                        const res = await fetch('<?php echo esc_url(rest_url('ppv/v1/user/points-poll')); ?>', {
                            method: 'GET',
                            headers: {'Content-Type': 'application/json'}
                        });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (data.success) {
                            const pointsEl = document.getElementById('ppv-global-points');
                            const rewardsEl = document.getElementById('ppv-global-rewards');
                            if (pointsEl) pointsEl.textContent = data.points;
                            if (rewardsEl) rewardsEl.textContent = data.rewards;
                        }
                    } catch (e) {
                        // Silent fail
                    }
                }, 30000); // 30s polling on non-dashboard pages
            }
            <?php endif; ?>
        }

        // 🚀 Run on DOMContentLoaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initGlobalHeaderJS);
        } else {
            initGlobalHeaderJS();
        }

        // 🚀 Also run on Turbo navigation (re-init after page change)
        document.addEventListener('turbo:load', initGlobalHeaderJS);

        // 🧹 Reset init flag before Turbo renders new page
        document.addEventListener('turbo:before-render', function() {
            const header = document.getElementById('ppv-global-header');
            if (header) header.dataset.jsInit = 'false';
        });
        </script>
        <?php
    }
    public static function enqueue_assets() {
        // RemixIcons loaded globally in punktepass.php

        // 🎨 QR Code Generator library (local generation, offline support)
        wp_enqueue_script('qrcode-generator', PPV_PLUGIN_URL . 'assets/js/vendor/qrcode-generator.min.js', [], '1.4.4', true);

        // 📡 ABLY: Load JS SDK + shared manager if enabled
        $dependencies = ['jquery', 'qrcode-generator'];
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            PPV_Ably::enqueue_scripts();
            $dependencies[] = 'ppv-ably-manager';
        }

        wp_enqueue_script(
            'ppv-dashboard',
            PPV_PLUGIN_URL . 'assets/js/ppv-user-dashboard.js',
            $dependencies,
            PPV_Core::asset_version(PPV_PLUGIN_DIR . 'assets/js/ppv-user-dashboard.js'),
            true
        );

        // 💡 User Tips - personalized tips/hints
        wp_enqueue_script(
            'ppv-user-tips',
            PPV_PLUGIN_URL . 'assets/js/ppv-user-tips.js',
            ['ppv-dashboard'],
            PPV_Core::asset_version(PPV_PLUGIN_DIR . 'assets/js/ppv-user-tips.js'),
            true
        );

        // Config for User Tips
        wp_localize_script('ppv-user-tips', 'ppvConfig', [
            'restBase' => esc_url_raw(rest_url('ppv/v1/')),
            'nonce' => wp_create_nonce('wp_rest')
        ]);

        $boot = self::build_boot_payload();

        wp_add_inline_script(
            'ppv-dashboard',
            'window.ppv_boot = ' . wp_json_encode($boot) . ';',
            'before'
        );

        // Add store rating config (nonce + ajax_url)
        wp_add_inline_script(
            'ppv-dashboard',
            'window.ppv_dashboard = ' . wp_json_encode([
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ppv_store_rating_nonce'),
                'lang' => self::get_user_lang()
            ]) . ';',
            'before'
        );

        if (class_exists('PPV_Lang') && !empty(PPV_Lang::$strings)) {
            wp_add_inline_script(
                'ppv-dashboard',
                'window.ppv_lang = ' . wp_json_encode(PPV_Lang::$strings) . ';',
                'before'
            );
        }
    }

    private static function build_boot_payload() {
        $uid = self::get_safe_user_id();

        $email = self::get_user_email($uid);
        $lang = self::get_user_lang();
        
        if ($uid <= 0) {
            return [
                'uid' => $uid,
                'email' => $email,
                'lang' => $lang,
                'qr_url' => '',
                'points' => 0,
                'rewards' => 0,
                'api' => esc_url_raw(rest_url('ppv/v1/')),
                'assets' => [
                    'logo' => PPV_PLUGIN_URL . 'assets/img/logo.webp',
                    'store_default' => PPV_PLUGIN_URL . 'assets/img/store-default-logo.webp'
                ]
            ];
        }

        $stats = self::get_user_stats($uid);
        $token = self::get_user_qr_token($uid);
        $qr_url = $token ? self::generate_qr_code($uid, $token) : '';

        self::cleanup_tokens($uid);

        $boot = [
            'uid' => $uid,
            'email' => $email,
            'lang' => $lang,
            'qr_url' => $qr_url,
            'points' => $stats['points'],
            'rewards' => $stats['rewards'],
            'api' => esc_url_raw(rest_url('ppv/v1/')),
            'assets' => [
                'logo' => PPV_PLUGIN_URL . 'assets/img/logo.webp',
                'store_default' => PPV_PLUGIN_URL . 'assets/img/store-default-logo.webp'
            ]
        ];

        // 📡 ABLY: Add config for real-time updates
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            $boot['ably'] = [
                'key' => PPV_Ably::get_key(),
            ];
        }

        return $boot;
    }

    // ========================================
    // 🚀 STANDALONE RENDERING (bypasses WordPress theme)
    // ========================================

    public static function maybe_render_standalone() {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = rtrim($path, '/');
        if ($path !== '/user_dashboard') return;

        ppv_disable_wp_optimization();

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        // Customer auth: check ppv_user_id in session
        $user_id = intval($_SESSION['ppv_user_id'] ?? 0);
        if ($user_id <= 0) {
            header('Location: /login');
            exit;
        }

        self::render_standalone_page();
        exit;
    }

    private static function render_standalone_page() {
        $plugin_url = PPV_PLUGIN_URL;
        $version    = PPV_Core::asset_version();
        $site_url   = get_site_url();

        // ─── Language ───
        if (class_exists('PPV_Lang')) {
            $lang    = PPV_Lang::$active ?: 'de';
            $strings = PPV_Lang::$strings ?: [];
        } else {
            $lang    = self::get_user_lang();
            $strings = [];
        }

        // ─── Theme ───
        $theme_cookie = $_COOKIE['ppv_theme'] ?? 'light';
        $is_dark = ($theme_cookie === 'dark');

        // ─── Boot payload (user data, QR, points, rewards, Ably) ───
        $boot = self::build_boot_payload();

        // ─── Dashboard config ───
        $dashboard_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ppv_store_rating_nonce'),
            'lang'     => $lang,
        ];

        // ─── Tips config ───
        $tips_config = [
            'restBase' => esc_url_raw(rest_url('ppv/v1/')),
            'nonce'    => wp_create_nonce('wp_rest'),
        ];

        // ─── Ably ───
        $ably_enabled = class_exists('PPV_Ably') && PPV_Ably::is_enabled();

        // ─── Page content (render_dashboard echoes + returns) ───
        ob_start();
        $page_html = self::render_dashboard();
        $echoed = ob_get_clean();
        // render_dashboard echoes a script + returns HTML
        $page_html = $echoed . $page_html;

        // ─── Global header ───
        ob_start();
        self::render_global_header();
        $global_header = ob_get_clean();

        // ─── Bottom nav (context + HTML, rendered OUTSIDE .ppv-standalone-wrap) ───
        $bottom_nav_context = '';
        if (class_exists('PPV_Bottom_Nav')) {
            ob_start();
            PPV_Bottom_Nav::inject_context();
            echo PPV_Bottom_Nav::render_nav();
            $bottom_nav_context = ob_get_clean();
        }

        // ─── Body classes ───
        $body_classes = ['ppv-standalone', 'ppv-app-mode', 'ppv-user-dashboard'];
        $body_classes[] = $is_dark ? 'ppv-dark' : 'ppv-light';
        $body_class = implode(' ', $body_classes);

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>" data-theme="<?php echo $is_dark ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <?php ppv_standalone_cleanup_head(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Dashboard - PunktePass</title>
    <link rel="manifest" href="<?php echo esc_url($site_url); ?>/manifest.json">
    <link rel="icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-core.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-layout.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-components.css?v=<?php echo esc_attr($version); ?>">
    <!-- ppv-theme-light.css + handler-light.css DISABLED – replaced by modular CSS -->
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-bottom-nav.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-dashboard.css?v=<?php echo esc_attr($version); ?>">
<?php if ($is_dark): ?>
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-theme-dark-colors.css?v=<?php echo esc_attr($version); ?>">
<?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script>
    var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    window.ppv_boot = <?php echo wp_json_encode($boot); ?>;
    window.ppv_dashboard = <?php echo wp_json_encode($dashboard_data); ?>;
    window.ppvConfig = <?php echo wp_json_encode($tips_config); ?>;
    window.ppv_lang = <?php echo wp_json_encode($strings); ?>;
    </script>
    <style>
    html,body{margin:0;padding:0;min-height:100vh;background:var(--pp-bg,#f5f5f7);overflow-y:auto;overflow-x:hidden}
    .ppv-standalone-wrap{max-width:768px;margin:0 auto;padding:16px 0 100px 0;min-height:100vh;padding-top:env(safe-area-inset-top,0)}
    </style>
</head>
<body class="<?php echo esc_attr($body_class); ?>">
<?php echo $global_header; ?>
<div class="ppv-standalone-wrap">
<?php echo $page_html; ?>
</div>
<?php echo $bottom_nav_context; ?>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-debug.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-global.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/vendor/qrcode-generator.min.js"></script>
<?php if ($ably_enabled): ?>
<script src="https://cdn.ably.com/lib/ably.min-1.js"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-ably-manager.js?v=<?php echo esc_attr($version); ?>"></script>
<?php endif; ?>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-user-dashboard.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-user-tips.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-theme-loader.js?v=<?php echo esc_attr($version); ?>"></script>
<?php if (class_exists('PPV_Bottom_Nav')): ?>
<script><?php echo PPV_Bottom_Nav::inline_js(); ?></script>
<?php endif; ?>
</body>
</html>
<?php
    }

public static function render_dashboard() {
    echo '<script>document.body.classList.add("ppv-user-dashboard");</script>';

    // Bottom nav is rendered separately outside .ppv-standalone-wrap
    // to avoid transform-based containing blocks breaking position:fixed
    return '<div id="ppv-dashboard-root"></div>';
}

    public static function register_routes() {
    // ✅ DETAILED POINTS (for My Points page)
    register_rest_route('ppv/v1', '/user/points-detailed', [
        'methods' => 'GET',
        'callback' => [__CLASS__, 'rest_get_detailed_points'],
        'permission_callback' => ['PPV_Permissions', 'check_authenticated'],
    ]);

    // Simple poll (for header)
    register_rest_route('ppv/v1', '/user/points-poll', [
        'methods' => 'GET',
        'callback' => [__CLASS__, 'rest_poll_points'],
        'permission_callback' => ['PPV_Permissions', 'check_authenticated'],
    ]);

    // Stores (public endpoint - anyone can see store list)
    register_rest_route('ppv/v1', '/stores/list-optimized', [
        'methods' => 'GET',
        'callback' => [__CLASS__, 'rest_stores_optimized'],
        'permission_callback' => ['PPV_Permissions', 'allow_anonymous'],
    ]);

    // User address-based location (GPS fallback)
    register_rest_route('ppv/v1', '/user/address-location', [
        'methods' => 'GET',
        'callback' => [__CLASS__, 'rest_get_address_location'],
        'permission_callback' => ['PPV_Permissions', 'check_authenticated'],
    ]);

    ppv_log("✅ [PPV_Dashboard] REST routes registered (with points-detailed)");
}
    
    public static function rest_poll_points(WP_REST_Request $request) {
        global $wpdb;

        // ✅ FIX: Use helper methods for session + user ID (same as rest_get_detailed_points)
        self::ensure_session();

        // ✅ FORCE SESSION RESTORE (Google/Facebook/TikTok login)
        if (class_exists('PPV_SessionBridge') && empty($_SESSION['ppv_user_id'])) {
            PPV_SessionBridge::restore_from_token();
        }

        $user_id = self::get_safe_user_id();

        if ($user_id <= 0) {
            ppv_log("❌ [PPV_Dashboard] rest_poll_points: No user found (WP=" . get_current_user_id() . ", SESSION=" . ($_SESSION['ppv_user_id'] ?? 'none') . ")");
            return new WP_REST_Response(['success' => false, 'points' => 0], 401);
        }

        $stats = self::get_user_stats($user_id);

        // Get the most recent store name from last point transaction
        $last_store_name = $wpdb->get_var($wpdb->prepare("
            SELECT s.name
            FROM {$wpdb->prefix}ppv_points p
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON p.store_id = s.id
            WHERE p.user_id = %d
            ORDER BY p.created DESC
            LIMIT 1
        ", $user_id));

        // Check for recent errors (last 15 seconds) in pos_log
        // BUT only if there's NO successful scan AFTER the error
        $recent_error = $wpdb->get_row($wpdb->prepare("
            SELECT l.message, l.type, s.name as store_name, l.created_at, l.store_id, l.metadata
            FROM {$wpdb->prefix}ppv_pos_log l
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON l.store_id = s.id
            WHERE l.user_id = %d
            AND l.type = 'error'
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL 15 SECOND)
            ORDER BY l.created_at DESC
            LIMIT 1
        ", $user_id));

        $response = [
            'success' => true,
            'points' => $stats['points'],
            'rewards' => $stats['rewards'],
            'store' => $last_store_name ?: 'PunktePass'
        ];

        // Add error info if found AND no successful scan happened after the error
        if ($recent_error && !empty($recent_error->message)) {
            // Check if there's a successful point transaction AFTER this error
            $success_after_error = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
                WHERE user_id = %d
                AND store_id = %d
                AND created > %s
                AND type = 'qr_scan'
            ", $user_id, $recent_error->store_id, $recent_error->created_at));

            // Only show error if NO successful scan happened after it
            if ($success_after_error == 0) {
                // Extract error_type from metadata for client-side translation
                $metadata = json_decode($recent_error->metadata ?? '{}', true);
                $error_type = $metadata['error_type'] ?? 'rate_limit';

                $response['error_message'] = $recent_error->message; // Fallback for old errors
                $response['error_type'] = $error_type; // Send error_type for client-side translation
                $response['error_store'] = $recent_error->store_name ?: 'PunktePass';
                $response['error_timestamp'] = $recent_error->created_at; // Add timestamp for tracking
                ppv_log("⚠️ [PPV_Dashboard] rest_poll_points: User=$user_id has recent error: " . $recent_error->message . " (type: $error_type)");
            } else {
                ppv_log("✅ [PPV_Dashboard] rest_poll_points: Error found but successful scan happened after, ignoring error");
            }
        }

        ppv_log("✅ [PPV_Dashboard] rest_poll_points: User=$user_id, Points=" . $stats['points'] . ", Store=" . ($last_store_name ?: 'none'));

        return new WP_REST_Response($response, 200);
    }
    
   public static function rest_get_detailed_points(WP_REST_Request $request) {
    global $wpdb;
    $prefix = $wpdb->prefix;

    self::ensure_session();

    // ✅ FORCE SESSION RESTORE (Google/Facebook/TikTok login)
    if (class_exists('PPV_SessionBridge') && empty($_SESSION['ppv_user_id'])) {
        PPV_SessionBridge::restore_from_token();
        ppv_log("🔄 [PPV_Dashboard] Forced session restore from token");
    }

    $user_id = self::get_safe_user_id();

    if ($user_id <= 0) {
        ppv_log("❌ [PPV_Dashboard] No user found (WP_user=" . get_current_user_id() . ", SESSION=" . ($_SESSION['ppv_user_id'] ?? 'none') . ")");
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Not authenticated',
            'data' => [
                'total' => 0,
                'avg' => 0,
                'top_day' => null,
                'top_store' => null,
                'top3' => [],
                'entries' => [],
                'rewards_by_store' => []
            ]
        ], 401);
    }
    
    // TOTAL POINTS
    $total_points = $wpdb->get_var($wpdb->prepare("
        SELECT COALESCE(SUM(points), 0)
        FROM {$prefix}ppv_points
        WHERE user_id = %d
    ", $user_id));
    
    // AVERAGE
    $avg_points = $wpdb->get_var($wpdb->prepare("
        SELECT COALESCE(AVG(points), 0)
        FROM {$prefix}ppv_points
        WHERE user_id = %d
    ", $user_id));
    
    // BEST DAY
    $best_day = $wpdb->get_row($wpdb->prepare("
        SELECT DATE(created) as day, SUM(points) as total
        FROM {$prefix}ppv_points
        WHERE user_id = %d
        GROUP BY DATE(created)
        ORDER BY total DESC
        LIMIT 1
    ", $user_id));
    
    // TOP STORE
    $top_store = $wpdb->get_row($wpdb->prepare("
        SELECT s.company_name as store_name, SUM(p.points) as total
        FROM {$prefix}ppv_points p
        LEFT JOIN {$prefix}ppv_stores s ON p.store_id = s.id
        WHERE p.user_id = %d
        GROUP BY p.store_id
        ORDER BY total DESC
        LIMIT 1
    ", $user_id));
    
    // TOP 3 STORES
    $top3_stores = $wpdb->get_results($wpdb->prepare("
        SELECT s.company_name as store_name, SUM(p.points) as total
        FROM {$prefix}ppv_points p
        LEFT JOIN {$prefix}ppv_stores s ON p.store_id = s.id
        WHERE p.user_id = %d
        GROUP BY p.store_id
        ORDER BY total DESC
        LIMIT 3
    ", $user_id));
    
    // RECENT ENTRIES
    $recent_entries = $wpdb->get_results($wpdb->prepare("
        SELECT p.points, p.created as created, s.company_name as store_name
        FROM {$prefix}ppv_points p
        LEFT JOIN {$prefix}ppv_stores s ON p.store_id = s.id
        WHERE p.user_id = %d
        ORDER BY p.created DESC
        LIMIT 20
    ", $user_id));
    
    // ✅ ÚJ! BOLT-SPECIFIKUS REWARD TRACKING
    // 1️⃣ Megkeressük melyik boltokban gyűjtött pontot
    $stores_with_points = $wpdb->get_results($wpdb->prepare("
        SELECT
            s.id as store_id,
            s.company_name as store_name,
            SUM(p.points) as total_points
        FROM {$prefix}ppv_points p
        LEFT JOIN {$prefix}ppv_stores s ON p.store_id = s.id
        WHERE p.user_id = %d
        GROUP BY p.store_id
    ", $user_id));

    // ✅ OPTIMIZED: Batch load ALL rewards in 1 query instead of N queries
    $all_rewards_map = [];
    if (!empty($stores_with_points)) {
        $store_ids = array_map(fn($s) => (int)$s->store_id, $stores_with_points);
        $store_ids_str = implode(',', array_filter($store_ids));
        $today = date('Y-m-d');

        if (!empty($store_ids_str)) {
            // Same query as belohnungen.php - include active check and campaign dates
            $all_rewards_raw = $wpdb->get_results($wpdb->prepare("
                SELECT store_id, required_points
                FROM {$prefix}ppv_rewards
                WHERE store_id IN ({$store_ids_str})
                AND required_points > 0
                AND (active = 1 OR active IS NULL)
                AND (
                    is_campaign = 0 OR is_campaign IS NULL
                    OR (
                        is_campaign = 1
                        AND (start_date IS NULL OR start_date <= %s)
                        AND (end_date IS NULL OR end_date >= %s)
                    )
                )
                ORDER BY store_id, required_points ASC
            ", $today, $today));

            // Group by store_id
            foreach ($all_rewards_raw as $r) {
                $sid = (int)$r->store_id;
                if (!isset($all_rewards_map[$sid])) $all_rewards_map[$sid] = [];
                $all_rewards_map[$sid][] = $r;
            }
        }
    }

    // 2️⃣ Minden bolthoz megkeressük a következő jutalmat
    $rewards_by_store = [];

    foreach ($stores_with_points as $store) {
        $store_id = (int) $store->store_id;
        $store_points = (int) $store->total_points;

        // ✅ OPTIMIZED: Use pre-loaded rewards instead of query per store
        $store_rewards = $all_rewards_map[$store_id] ?? [];
        
        if (empty($store_rewards)) {
            continue; // Nincs jutalom ebben a boltban
        }
        
        // Megkeressük a következő jutalmat
        $next_goal = null;
        $remaining = null;
        $progress_percent = 0;
        $achieved = false;
        
        foreach ($store_rewards as $reward) {
            $req = (int) $reward->required_points;
            if ($req > $store_points) {
                $next_goal = $req;
                $remaining = $req - $store_points;
                $progress_percent = round(($store_points / $req) * 100, 1);
                break;
            }
        }
        
        // Ha nem találtunk (elérte az összeset)
        if ($next_goal === null && !empty($store_rewards)) {
            $last_reward = (int) end($store_rewards)->required_points;
            if ($store_points >= $last_reward) {
                $achieved = true;
                $next_goal = $last_reward;
                $remaining = 0;
                $progress_percent = 100;
            }
        }
        
        $rewards_by_store[] = [
            'store_id' => $store_id,
            'store_name' => $store->store_name ?: 'Unknown',
            'current_points' => $store_points,
            'next_goal' => $next_goal,
            'remaining' => $remaining,
            'progress_percent' => $progress_percent,
            'achieved' => $achieved
        ];
    }
    
    return new WP_REST_Response([
        'success' => true,
        'data' => [
            'total' => (int) $total_points,
            'avg' => round((float) $avg_points, 1),
            'top_day' => $best_day ? [
                'day' => $best_day->day,
                'total' => (int) $best_day->total
            ] : null,
            'top_store' => $top_store ? [
                'store_name' => $top_store->store_name ?: 'Unknown',
                'total' => (int) $top_store->total
            ] : null,
            'top3' => array_map(function($s) {
                return [
                    'store_name' => $s->store_name ?: 'Unknown',
                    'total' => (int) $s->total
                ];
            }, $top3_stores),
            'entries' => array_map(function($e) {
                return [
                    'points' => (int) $e->points,
                    'created' => $e->created,
                    'store_name' => $e->store_name ?: 'Unknown'
                ];
            }, $recent_entries),
            'rewards_by_store' => $rewards_by_store // ✅ ÚJ!
        ]
    ], 200);
}

    /**
     * Get user's location based on their saved address (GPS fallback)
     * Uses zip + city to geocode approximate coordinates via OpenStreetMap Nominatim
     */
    public static function rest_get_address_location(WP_REST_Request $request) {
        global $wpdb;

        self::ensure_session();

        // Restore session from token if needed
        if (class_exists('PPV_SessionBridge') && empty($_SESSION['ppv_user_id'])) {
            PPV_SessionBridge::restore_from_token();
        }

        $user_id = self::get_safe_user_id();

        ppv_log("📍 [Address Location] user_id={$user_id}");

        if ($user_id <= 0) {
            ppv_log("❌ [Address Location] Not authenticated");
            return new WP_REST_Response([
                'success' => false,
                'msg' => 'Not authenticated'
            ], 401);
        }

        // Get user's address data
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT address, zip, city, country FROM {$wpdb->prefix}ppv_users WHERE id = %d",
            $user_id
        ));

        // Check if user has any address data
        $address = trim($user->address ?? '');
        $zip = trim($user->zip ?? '');
        $city = trim($user->city ?? '');
        $country = trim($user->country ?? 'DE');

        ppv_log("📍 [Address Location] address='{$address}', zip='{$zip}', city='{$city}', country='{$country}'");

        // No address data at all
        if (!$user || (empty($address) && empty($zip) && empty($city))) {
            ppv_log("⚠️ [Address Location] No address data found");
            return new WP_REST_Response([
                'success' => false,
                'msg' => 'no_address',
                'has_address' => false
            ], 200);
        }

        // Country code mapping
        $country_codes = [
            'DE' => 'de',
            'HU' => 'hu',
            'RO' => 'ro',
            'AT' => 'at',
            'CH' => 'ch'
        ];
        $country_code = $country_codes[$country] ?? 'de';

        // Build search query - use full address for better precision
        $parts = array_filter([$address, $zip, $city]);
        $search_query = implode(', ', $parts);

        // Check cache first (geocoding results rarely change)
        $cache_key = 'ppv_geo_' . md5("{$search_query}_{$country_code}");
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return new WP_REST_Response([
                'success' => true,
                'lat' => $cached['lat'],
                'lng' => $cached['lng'],
                'source' => 'address',
                'cached' => true
            ], 200);
        }

        // Call OpenStreetMap Nominatim for geocoding
        $nominatim_url = 'https://nominatim.openstreetmap.org/search';
        $response = wp_remote_get(
            add_query_arg([
                'q' => $search_query,
                'countrycodes' => $country_code,
                'format' => 'json',
                'limit' => 1,
                'addressdetails' => 0
            ], $nominatim_url),
            [
                'timeout' => 5,
                'headers' => [
                    'User-Agent' => 'PunktePass/1.0 (contact@punktepass.de)'
                ]
            ]
        );

        if (is_wp_error($response)) {
            ppv_log("❌ [Address Location] Nominatim error: " . $response->get_error_message());
            return new WP_REST_Response([
                'success' => false,
                'msg' => 'geocoding_error'
            ], 200);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !isset($data[0]['lat']) || !isset($data[0]['lon'])) {
            ppv_log("⚠️ [Address Location] No results for: {$search_query}, {$country_code}");
            return new WP_REST_Response([
                'success' => false,
                'msg' => 'no_results',
                'has_address' => true
            ], 200);
        }

        $lat = floatval($data[0]['lat']);
        $lng = floatval($data[0]['lon']);

        // Cache result for 7 days (city coordinates don't change)
        set_transient($cache_key, ['lat' => $lat, 'lng' => $lng], 7 * DAY_IN_SECONDS);

        ppv_log("✅ [Address Location] Geocoded {$search_query} -> {$lat}, {$lng}");

        return new WP_REST_Response([
            'success' => true,
            'lat' => $lat,
            'lng' => $lng,
            'source' => 'address',
            'cached' => false
        ], 200);
    }

   public static function rest_stores_optimized(WP_REST_Request $request) {
    global $wpdb;
    ppv_log("🛰️ [REST DEBUG] rest_stores_optimized START");

    $prefix = $wpdb->prefix;

    $user_lat = floatval($request->get_param('lat'));
    $user_lng = floatval($request->get_param('lng'));
    $max_distance = floatval($request->get_param('max_distance') ?? 10);

    // ✅ Cache kulcs - now includes VIP version for cache invalidation
    // Version 2: Updated store name filter (name OR company_name)
    $vip_version = wp_cache_get('ppv_vip_version') ?: '2';
    $cache_key = 'ppv_stores_list_v2_' . md5("{$user_lat}_{$user_lng}_{$max_distance}_{$vip_version}");
    $cached = wp_cache_get($cache_key);
    if ($cached !== false) {
        return new WP_REST_Response($cached, 200);
    }

    // ✅ Alap lekérdezés – csak aktív boltok
    // ✅ FIX: Added 'country' field for currency symbol display
    // ✅ FIX: Added VIP fields for shop card display
    $stores = $wpdb->get_results("
        SELECT s.id, s.name, s.company_name, s.address, s.city, s.plz, s.latitude, s.longitude,
               s.phone, s.public_email, s.website, s.logo, s.qr_logo, s.opening_hours, s.description,
               s.gallery, s.facebook, s.instagram, s.tiktok, s.country, s.slogan,
               s.vacation_enabled, s.vacation_from, s.vacation_to, s.vacation_message,
               s.vip_enabled,
               s.vip_fix_enabled, s.vip_fix_bronze, s.vip_fix_silver, s.vip_fix_gold, s.vip_fix_platinum,
               s.vip_streak_enabled, s.vip_streak_count, s.vip_streak_type,
               s.vip_streak_bronze, s.vip_streak_silver, s.vip_streak_gold, s.vip_streak_platinum
        FROM {$prefix}ppv_stores s
        WHERE s.active = 1
          AND (
              (s.name IS NOT NULL AND s.name != '')
              OR (s.company_name IS NOT NULL AND s.company_name != '')
          )
          AND EXISTS (SELECT 1 FROM {$prefix}ppv_rewards r WHERE r.store_id = s.id)
        ORDER BY s.name ASC
    ");

    if (empty($stores)) {
        return new WP_REST_Response([], 200);
    }

    ppv_log("🛰️ [REST DEBUG] stores count: " . count($stores));

    // ✅ SQL OPTIMIZATION: Batch fetch all rewards and campaigns in 2 queries instead of N*2
    $store_ids = array_map(function($s) { return (int)$s->id; }, $stores);
    $store_ids_str = implode(',', $store_ids);

    // ✅ Batch query for ALL rewards
    $all_rewards = [];
    if (!empty($store_ids_str)) {
        $rewards_query = "
            SELECT store_id, id, title, required_points, points_given,
                   action_type, action_value, currency, description, end_date
            FROM {$prefix}ppv_rewards
            WHERE store_id IN ({$store_ids_str})
              AND required_points > 0
            ORDER BY store_id, required_points ASC
        ";
        $rewards_raw = $wpdb->get_results($rewards_query);

        // Group by store_id (limit 5 per store)
        $rewards_count = [];
        foreach ($rewards_raw as $r) {
            $sid = (int)$r->store_id;
            if (!isset($rewards_count[$sid])) $rewards_count[$sid] = 0;
            if ($rewards_count[$sid] >= 5) continue;

            if (!isset($all_rewards[$sid])) $all_rewards[$sid] = [];
            $all_rewards[$sid][] = [
                'id' => (int)$r->id,
                'title' => $r->title,
                'description' => $r->description,
                'required_points' => (int)$r->required_points,
                'points_given' => (int)$r->points_given,
                'action_type' => $r->action_type,
                'action_value' => $r->action_value,
                'currency' => $r->currency,
                'end_date' => $r->end_date
            ];
            $rewards_count[$sid]++;
        }
    }

    // ✅ Batch query for ALL campaigns
    $all_campaigns = [];
    if (!empty($store_ids_str)) {
        $campaigns_query = "
            SELECT store_id, id, title, start_date, end_date, campaign_type,
                   discount_percent, extra_points, multiplier,
                   min_purchase, fixed_amount, required_points,
                   free_product, free_product_value, points_given, description
            FROM {$prefix}ppv_campaigns
            WHERE store_id IN ({$store_ids_str})
              AND status = 'active'
              AND start_date <= CURDATE()
              AND end_date >= CURDATE()
            ORDER BY store_id, start_date ASC
        ";
        $campaigns_raw = $wpdb->get_results($campaigns_query);

        // Group by store_id (limit 5 per store)
        $campaigns_count = [];
        foreach ($campaigns_raw as $c) {
            $sid = (int)$c->store_id;
            if (!isset($campaigns_count[$sid])) $campaigns_count[$sid] = 0;
            if ($campaigns_count[$sid] >= 5) continue;

            if (!isset($all_campaigns[$sid])) $all_campaigns[$sid] = [];
            $all_campaigns[$sid][] = [
                'id' => (int)$c->id,
                'title' => $c->title,
                'start_date' => $c->start_date,
                'end_date' => $c->end_date,
                'campaign_type' => $c->campaign_type,
                'discount_percent' => (float)$c->discount_percent,
                'extra_points' => (int)$c->extra_points,
                'multiplier' => (int)$c->multiplier,
                'min_purchase' => (float)$c->min_purchase,
                'fixed_amount' => (float)$c->fixed_amount,
                'required_points' => (int)$c->required_points,
                'free_product' => $c->free_product,
                'free_product_value' => (float)$c->free_product_value,
                'points_given' => (int)$c->points_given,
                'description' => $c->description
            ];
            $campaigns_count[$sid]++;
        }
    }

    ppv_log("✅ [REST DEBUG] Batch loaded " . count($all_rewards) . " store rewards, " . count($all_campaigns) . " store campaigns");

    $result = [];
    foreach ($stores as $store) {
        $lat = floatval($store->latitude);
        $lng = floatval($store->longitude);

        // ✅ Distance calc safe
        $distance_km = null;
        if ($user_lat && $user_lng && $lat && $lng) {
            $distance_km = self::calculate_distance($user_lat, $user_lng, $lat, $lng);
            if ($distance_km > $max_distance) continue;
        }

        // ✅ Open + hours safe
        $is_open = self::is_store_open($store->opening_hours);
        $today_hours = self::get_today_hours($store->opening_hours);

        // ✅ Gallery safe
        $gallery_images = [];
        if (!empty($store->gallery)) {
            $decoded = json_decode($store->gallery, true);
            if (is_array($decoded)) $gallery_images = array_slice($decoded, 0, 6);
        }

        // ✅ Get rewards from batch-loaded data
        $rewards = $all_rewards[(int)$store->id] ?? [];

        // ✅ Get campaigns from batch-loaded data
        $campaigns = $all_campaigns[(int)$store->id] ?? [];
        // ✅ Build VIP object (if vip_fix or vip_streak is enabled)
        // Note: handlers control VIP via vip_fix_enabled / vip_streak_enabled toggles
        $vip = null;
        $has_vip = (
            !empty($store->vip_fix_enabled) ||
            !empty($store->vip_streak_enabled)
        );

        if ($has_vip) {
            $vip = [
                'fix' => !empty($store->vip_fix_enabled) ? [
                    'enabled' => true,
                    'bronze' => (int)($store->vip_fix_bronze ?? 1),
                    'silver' => (int)($store->vip_fix_silver ?? 2),
                    'gold' => (int)($store->vip_fix_gold ?? 3),
                    'platinum' => (int)($store->vip_fix_platinum ?? 5),
                ] : null,
                'streak' => !empty($store->vip_streak_enabled) ? [
                    'enabled' => true,
                    'count' => (int)($store->vip_streak_count ?? 10),
                    'type' => $store->vip_streak_type ?? 'fixed',
                    'bronze' => (int)($store->vip_streak_bronze ?? 1),
                    'silver' => (int)($store->vip_streak_silver ?? 2),
                    'gold' => (int)($store->vip_streak_gold ?? 3),
                    'platinum' => (int)($store->vip_streak_platinum ?? 5),
                ] : null,
            ];
        }

        $result[] = [
            'id' => (int)$store->id,
            'name' => $store->name,  // Store name (üzlet neve)
            'company_name' => $store->company_name,  // Company name (cégnév)
            'slogan' => $store->slogan ?? null,  // Store slogan
            'address' => $store->address,
            'city' => $store->city,
            'plz' => $store->plz,
            'latitude' => $lat,
            'longitude' => $lng,
            'distance_km' => $distance_km ? round($distance_km, 1) : null,
            'open_now' => $is_open,
            'open_hours_today' => $today_hours,
            'phone' => $store->phone,
            'public_email' => $store->public_email,
            'website' => esc_url($store->website),
            // ✅ FIX: Validate logo URL - return null if empty/invalid
            'logo' => (!empty($store->logo) && $store->logo !== 'null') ? esc_url($store->logo) : null,
            'gallery' => array_map('esc_url', $gallery_images),
            'country' => $store->country ?? 'DE', // ✅ FIX: Added for currency symbol
            'social' => [
                // ✅ FIX: Escape social media URLs
                'facebook' => !empty($store->facebook) ? esc_url($store->facebook) : null,
                'instagram' => !empty($store->instagram) ? esc_url($store->instagram) : null,
                'tiktok' => !empty($store->tiktok) ? esc_url($store->tiktok) : null
            ],
            'rewards' => $rewards,
            'campaigns' => $campaigns,
            'vip' => $vip,  // ✅ NEW: VIP bonus info
            'vacation_from' => $store->vacation_from ?? null,
            'vacation_to' => $store->vacation_to ?? null,
            'vacation_message' => $store->vacation_message ?? null,
            'is_on_vacation' => self::is_store_on_vacation($store->vacation_enabled, $store->vacation_from, $store->vacation_to)
        ];
    }

    // ✅ Distance sort
    if ($user_lat && $user_lng) {
        usort($result, fn($a, $b) => ($a['distance_km'] ?? 99999) <=> ($b['distance_km'] ?? 99999));
    }

    // ✅ Cache mentés 1 órára
    wp_cache_set($cache_key, $result, '', 3600);

    return new WP_REST_Response($result, 200);
}

    /**
     * Check if store is currently on vacation based on enabled flag and date range
     */
    private static function is_store_on_vacation($vacation_enabled, $vacation_from, $vacation_to) {
        if (empty($vacation_enabled)) {
            return false;
        }
        if (empty($vacation_from) || empty($vacation_to)) {
            return false;
        }

        $today = date('Y-m-d');
        return ($today >= $vacation_from && $today <= $vacation_to);
    }

    private static function calculate_distance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth_radius * $c;
    }
}

PPV_User_Dashboard::hooks();
