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

        if (isset($_COOKIE['ppv_lang'])) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang']);
        } elseif (isset($_GET['lang'])) {
            $lang = sanitize_text_field($_GET['lang']);
        } else {
            $lang = substr(get_locale(), 0, 2);
        }

        return $lang ?: 'de';
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

        $user = get_userdata($uid);
        return $user ? $user->user_email : '';
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
        // 1️⃣ Skip if not logged in
        self::ensure_session();
        $uid = self::get_safe_user_id();
        if ($uid <= 0) {
            return;
        }

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
                <img src="<?php echo PPV_PLUGIN_URL; ?>assets/img/logo.webp" alt="PunktePass" class="ppv-header-logo-tiny">
                <?php if (!empty($avatar_url)): ?>
                <!-- Avatar (links to settings) -->
                <a href="<?php echo esc_url($settings_url); ?>" class="ppv-header-avatar-link" title="<?php echo esc_attr($email); ?>">
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar" class="ppv-header-avatar">
                </a>
                <?php else: ?>
                <!-- Email (fallback) -->
                <div class="ppv-user-info">
                    <span class="ppv-user-email"><?php echo esc_html($email); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Stats (USER only) -->
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
                <div class="ppv-stat-mini">
                    <i class="ri-gift-fill"></i>
                    <span id="ppv-global-rewards"><?php echo esc_html($stats['rewards']); ?></span>
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
                <select id="ppv-lang-select-global" class="ppv-lang-mini" title="Sprache / Nyelv / Limbă">
                    <option value="de" <?php selected($lang, 'de'); ?>>🇩🇪</option>
                    <option value="hu" <?php selected($lang, 'hu'); ?>>🇭🇺</option>
                    <option value="ro" <?php selected($lang, 'ro'); ?>>🇷🇴</option>
                </select>
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

            // ============================================================
            // LANGUAGE SWITCH - Fixed for Turbo compatibility
            // ============================================================
            const langSel = document.getElementById('ppv-lang-select-global');
            if (langSel && !langSel.dataset.listenerAttached) {
                langSel.dataset.listenerAttached = 'true';

                langSel.addEventListener('change', (e) => {
                    const v = e.target.value;
                    console.log('🌐 [Lang] Switching to:', v);

                    document.cookie = `ppv_lang=${v};path=/;max-age=${60*60*24*365}`;
                    localStorage.setItem('ppv_lang', v);

                    const url = new URL(window.location.href);
                    url.searchParams.set('lang', v);

                    // Use Turbo visit if available, otherwise standard redirect
                    if (window.Turbo) {
                        window.Turbo.visit(url.toString(), { action: 'replace' });
                    } else {
                        window.location.href = url.toString();
                    }
                });

                console.log('✅ [Lang] Select listener attached');
            }

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
        wp_enqueue_style(
            'remixicons',
            'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css',
            [],
            null
        );

        // 📡 ABLY: Load JS SDK from CDN if enabled
        $dependencies = ['jquery'];
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            wp_enqueue_script(
                'ably-js',
                'https://cdn.ably.com/lib/ably.min-1.js',
                [],
                '1.2',
                true
            );
            $dependencies[] = 'ably-js';
        }

        wp_enqueue_script(
            'ppv-dashboard',
            PPV_PLUGIN_URL . 'assets/js/ppv-user-dashboard.js',
            $dependencies,
            time(),
            true
        );

        $boot = self::build_boot_payload();

        wp_add_inline_script(
            'ppv-dashboard',
            'window.ppv_boot = ' . wp_json_encode($boot) . ';',
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

public static function render_dashboard() {
    echo '<script>document.body.classList.add("ppv-user-dashboard");</script>';
    
    return '<div id="ppv-dashboard-root"></div>' . do_shortcode('[ppv_bottom_nav]');
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
    
    // 2️⃣ Minden bolthoz megkeressük a következő jutalmat
    $rewards_by_store = [];
    
    foreach ($stores_with_points as $store) {
        $store_id = (int) $store->store_id;
        $store_points = (int) $store->total_points;
        
        // Lekérdezzük a bolt jutalmait
        $store_rewards = $wpdb->get_results($wpdb->prepare("
            SELECT required_points
            FROM {$prefix}ppv_rewards
            WHERE store_id = %d AND required_points > 0
            ORDER BY required_points ASC
        ", $store_id));
        
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

   public static function rest_stores_optimized(WP_REST_Request $request) {
    global $wpdb;
    ppv_log("🛰️ [REST DEBUG] rest_stores_optimized START");

    $prefix = $wpdb->prefix;

    $user_lat = floatval($request->get_param('lat'));
    $user_lng = floatval($request->get_param('lng'));
    $max_distance = floatval($request->get_param('max_distance') ?? 10);

    // ✅ Cache kulcs
    $cache_key = 'ppv_stores_list_' . md5("{$user_lat}_{$user_lng}_{$max_distance}");
    $cached = wp_cache_get($cache_key);
    if ($cached !== false) {
        return new WP_REST_Response($cached, 200);
    }

    // ✅ Alap lekérdezés – csak aktív boltok
    // ✅ FIX: Added 'country' field for currency symbol display
    $stores = $wpdb->get_results("
        SELECT id, company_name, address, city, plz, latitude, longitude,
               phone, website, logo, qr_logo, opening_hours, description,
               gallery, facebook, instagram, tiktok, country
        FROM {$prefix}ppv_stores
        WHERE active = 1
        ORDER BY company_name ASC
    ");

    if (empty($stores)) {
        return new WP_REST_Response([], 200);
    }

    $result = [];
    ppv_log("🛰️ [REST DEBUG] stores count: " . count($stores));

    foreach ($stores as $store) {
            ppv_log("🏪 [REST DEBUG] Store: {$store->company_name} (ID: {$store->id})");

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

        // ✅ Rewards quick & safe
        $rewards = [];
        $rws = $wpdb->get_results($wpdb->prepare("
    SELECT 
        id, 
        title, 
        required_points, 
        points_given,      -- ✅ új mező
        action_type, 
        action_value, 
        currency, 
        description
    FROM {$prefix}ppv_rewards
    WHERE store_id = %d 
      AND required_points > 0
    ORDER BY required_points ASC 
    LIMIT 5
", $store->id));

if ($rws) {
    foreach ($rws as $r) {
        $rewards[] = [
            'id' => (int)$r->id,
            'title' => $r->title,
            'description' => $r->description,
            'required_points' => (int)$r->required_points,
            'points_given' => (int)$r->points_given, // ✅ hozzáadva
            'action_type' => $r->action_type,
            'action_value' => $r->action_value,
            'currency' => $r->currency
        ];
    }
}


        // ✅ Campaigns - TELJES ADAT!
$campaigns = [];
$camps = $wpdb->get_results($wpdb->prepare("
    SELECT id, title, start_date, end_date, campaign_type, 
           discount_percent, extra_points, multiplier, 
           min_purchase, fixed_amount, required_points,
           free_product, free_product_value, points_given, description
    FROM {$prefix}ppv_campaigns
    WHERE store_id = %d
      AND status = 'active'
      AND start_date <= CURDATE()
      AND end_date >= CURDATE()
    ORDER BY start_date ASC LIMIT 5
", $store->id));
if ($camps) {
    foreach ($camps as $c) {
        $campaigns[] = [
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
    }
}
        $result[] = [
            'id' => (int)$store->id,
            'company_name' => $store->company_name,
            'address' => $store->address,
            'city' => $store->city,
            'plz' => $store->plz,
            'latitude' => $lat,
            'longitude' => $lng,
            'distance_km' => $distance_km ? round($distance_km, 1) : null,
            'open_now' => $is_open,
            'open_hours_today' => $today_hours,
            'phone' => $store->phone,
            'website' => $store->website,
            'logo' => $store->logo,
            'gallery' => $gallery_images,
            'country' => $store->country ?? 'DE', // ✅ FIX: Added for currency symbol
            'social' => [
                'facebook' => $store->facebook ?: null,
                'instagram' => $store->instagram ?: null,
                'tiktok' => $store->tiktok ?: null
            ],
            'rewards' => $rewards,
            'campaigns' => $campaigns
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
