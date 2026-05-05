<?php
/**
 * PPV_Advertisers — Önálló hirdető-rendszer (loyalty-tól független).
 *
 * Komponensek:
 * - DB: ppv_advertisers (cégadatok, login), ppv_ads (hirdetések), ppv_advertiser_followers (követések)
 * - Routes:
 *   /business/register   — bolt regisztráció
 *   /business/login      — bolt belépés
 *   /business/admin      — bolt admin dashboard (csak saját)
 *   /karte               — user-oldali térkép (Leaflet, geo pin-ek)
 *   /business/{slug}     — egy bolt nyilvános profilja (követés gomb, ad-ok)
 *
 * Árazás (2026-04-29-tól, egyszerűsített 1-tier modell):
 * - €10/hó (DE) / 35 RON/hó (RO) / filialé
 * - 1 ad / 1 pin / filialé, push 4/hó (1-2 filialé), 8/hó (3-6 filialé), 16/hó (7+ filialé)
 * - DE-piacon 90 nap, máshol 30 nap ingyen demo
 * - 10+ filialénál egyedi árazás (kézi tárgyalás)
 */

if (!defined('ABSPATH')) exit;

class PPV_Advertisers {

    const TIER_BASIC = 'basic';
    // 2026-04-29: egyszerűsített 1-tier modell. Ár filialénként:
    //   DE-piacon: €10/hó  ·  RO-piacon: 35 RON/hó
    //   Push-cap filialé-szám alapján skálázódik (lásd get_effective_push_limit).
    //   10+ filialénál egyedi árazás (kézi tárgyalás).
    // TIER_PLUS / TIER_PRO eltávolítva — egyetlen tier marad.
    const TIERS = [
        self::TIER_BASIC => ['price_eur' => 10, 'price_ron' => 35, 'ads' => 1, 'push_per_month' => 4, 'featured' => 0],
    ];

    public static function init() {
        add_action('init', [__CLASS__, 'register_routes']);
        add_action('rest_api_init', [__CLASS__, 'register_rest']);
        add_action('admin_post_ppv_advertiser_register', [__CLASS__, 'handle_register']);
        add_action('admin_post_nopriv_ppv_advertiser_register', [__CLASS__, 'handle_register']);
        add_action('admin_post_ppv_advertiser_login', [__CLASS__, 'handle_login']);
        add_action('admin_post_nopriv_ppv_advertiser_login', [__CLASS__, 'handle_login']);
        add_action('admin_post_ppv_advertiser_save_profile', [__CLASS__, 'handle_save_profile']);
        add_action('admin_post_nopriv_ppv_advertiser_save_profile', [__CLASS__, 'handle_save_profile']);
        add_action('admin_post_ppv_advertiser_save_ad', [__CLASS__, 'handle_save_ad']);
        add_action('admin_post_nopriv_ppv_advertiser_save_ad', [__CLASS__, 'handle_save_ad']);
        add_action('admin_post_ppv_advertiser_delete_ad', [__CLASS__, 'handle_delete_ad']);
        add_action('admin_post_nopriv_ppv_advertiser_delete_ad', [__CLASS__, 'handle_delete_ad']);
        add_action('admin_post_ppv_advertiser_send_push', [__CLASS__, 'handle_send_push']);
        add_action('admin_post_nopriv_ppv_advertiser_send_push', [__CLASS__, 'handle_send_push']);
        add_action('admin_post_nopriv_ppv_advertiser_follow', [__CLASS__, 'handle_follow']);
        add_action('admin_post_ppv_advertiser_follow', [__CLASS__, 'handle_follow']);
        add_action('admin_post_ppv_advertiser_filiale_create', [__CLASS__, 'handle_filiale_create']);
        add_action('admin_post_nopriv_ppv_advertiser_filiale_create', [__CLASS__, 'handle_filiale_create']);
        add_action('template_redirect', [__CLASS__, 'route_dispatcher']);
    }

    /** ============================================================
     * MIGRATIONS — hívva PPV_Core::run_db_migrations()-ből
     * ============================================================ */
    public static function run_migrations() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $advertisers_table = $wpdb->prefix . 'ppv_advertisers';
        $ads_table = $wpdb->prefix . 'ppv_ads';
        $db_name = defined('DB_NAME') ? DB_NAME : null;

        $sql1 = "CREATE TABLE IF NOT EXISTS {$advertisers_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            business_name VARCHAR(160) NOT NULL,
            owner_email VARCHAR(160) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            phone VARCHAR(40) NULL,
            whatsapp VARCHAR(40) NULL,
            website VARCHAR(255) NULL,
            address VARCHAR(255) NULL,
            city VARCHAR(120) NULL,
            country VARCHAR(60) NULL,
            postcode VARCHAR(20) NULL,
            lat DECIMAL(10,7) NULL,
            lng DECIMAL(10,7) NULL,
            category VARCHAR(60) NULL COMMENT 'food|cafe|service|retail|beauty|auto|health|other',
            logo_url VARCHAR(500) NULL,
            cover_url VARCHAR(500) NULL,
            gallery TEXT NULL COMMENT 'JSON array of image URLs',
            description_de TEXT NULL,
            description_hu TEXT NULL,
            description_ro TEXT NULL,
            description_en TEXT NULL,
            tier VARCHAR(20) DEFAULT 'basic',
            subscription_status VARCHAR(20) DEFAULT 'trial' COMMENT 'trial|active|expired|cancelled',
            subscription_until DATE NULL,
            push_used_this_month INT UNSIGNED DEFAULT 0,
            push_month_reset_at DATE NULL,
            featured TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_slug (slug),
            UNIQUE KEY uniq_email (owner_email),
            KEY idx_geo (lat, lng),
            KEY idx_country_city (country, city),
            KEY idx_category (category),
            KEY idx_active (is_active, subscription_status)
        ) {$charset};";
        dbDelta($sql1);

        $sql2 = "CREATE TABLE IF NOT EXISTS {$ads_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            advertiser_id BIGINT(20) UNSIGNED NOT NULL,
            title_de VARCHAR(160) NULL,
            title_hu VARCHAR(160) NULL,
            title_ro VARCHAR(160) NULL,
            title_en VARCHAR(160) NULL,
            body_de TEXT NULL,
            body_hu TEXT NULL,
            body_ro TEXT NULL,
            body_en TEXT NULL,
            image_url VARCHAR(500) NULL,
            cta_url VARCHAR(500) NULL,
            valid_from DATETIME NULL,
            valid_to DATETIME NULL,
            followers_only TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            impressions INT UNSIGNED DEFAULT 0,
            clicks INT UNSIGNED DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_advertiser (advertiser_id, is_active),
            KEY idx_valid (valid_from, valid_to)
        ) {$charset};";
        dbDelta($sql2);

        $store_followers = $wpdb->prefix . 'ppv_store_followers';
        $sql_sf = "CREATE TABLE IF NOT EXISTS {$store_followers} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            store_id BIGINT(20) UNSIGNED NOT NULL,
            push_enabled TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_store (user_id, store_id),
            KEY idx_store (store_id, push_enabled)
        ) {$charset};";
        dbDelta($sql_sf);

        $followers = $wpdb->prefix . 'ppv_advertiser_followers';
        $sql3 = "CREATE TABLE IF NOT EXISTS {$followers} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            advertiser_id BIGINT(20) UNSIGNED NOT NULL,
            push_enabled TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_adv (user_id, advertiser_id),
            KEY idx_advertiser (advertiser_id, push_enabled)
        ) {$charset};";
        dbDelta($sql3);

        // -- PHASE 1 MIGRATIONS (Filialen) --
        if ($db_name) {
            // Add parent_advertiser_id to wp_ppv_advertisers
            if (empty($wpdb->get_results($wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'parent_advertiser_id'", $db_name, $advertisers_table)))) {
                $wpdb->query("ALTER TABLE {$advertisers_table} ADD COLUMN parent_advertiser_id BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER id");
            }

            // Add max_filialen to wp_ppv_advertisers
            if (empty($wpdb->get_results($wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'max_filialen'", $db_name, $advertisers_table)))) {
                $wpdb->query("ALTER TABLE {$advertisers_table} ADD COLUMN max_filialen INT UNSIGNED DEFAULT 1");
            }

            // Add filiale_label to wp_ppv_advertisers
            if (empty($wpdb->get_results($wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'filiale_label'", $db_name, $advertisers_table)))) {
                $wpdb->query("ALTER TABLE {$advertisers_table} ADD COLUMN filiale_label VARCHAR(120) NULL");
            }

            // Add index for parent_advertiser_id
            if (empty($wpdb->get_results($wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'idx_parent_advertiser'", $db_name, $advertisers_table)))) {
                $wpdb->query("ALTER TABLE {$advertisers_table} ADD INDEX idx_parent_advertiser (parent_advertiser_id)");
            }

            // Add filiale_id to wp_ppv_ads
            if (empty($wpdb->get_results($wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'filiale_id'", $db_name, $ads_table)))) {
                $wpdb->query("ALTER TABLE {$ads_table} ADD COLUMN filiale_id BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER advertiser_id");
            }

            // Add index for filiale_id
            if (empty($wpdb->get_results($wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = 'idx_filiale'", $db_name, $ads_table)))) {
                $wpdb->query("ALTER TABLE {$ads_table} ADD INDEX idx_filiale (filiale_id)");
            }
        }
        // --- End of Filialen Migrations ---


        // Idempotent ALTER for existing installs — claim_count counter (max_claims oszlop már létezik)
        $wpdb->query("ALTER TABLE {$ads_table}
            ADD COLUMN IF NOT EXISTS claim_count INT UNSIGNED DEFAULT 0");

        ppv_log("✅ [PPV_Advertisers] Tables created/verified");
    }

    /** ============================================================
     * ROUTES
     * ============================================================ */
    public static function register_routes() {
        add_rewrite_rule('^business/register/?$', 'index.php?ppv_business=register', 'top');
        add_rewrite_rule('^business/login/?$', 'index.php?ppv_business=login', 'top');
        add_rewrite_rule('^business/logout/?$', 'index.php?ppv_business=logout', 'top');
        add_rewrite_rule('^business/admin/?$', 'index.php?ppv_business=admin', 'top');
        add_rewrite_rule('^business/admin/profile/?$', 'index.php?ppv_business=admin_profile', 'top');
        add_rewrite_rule('^business/admin/ads/?$', 'index.php?ppv_business=admin_ads', 'top');
        add_rewrite_rule('^business/admin/push/?$', 'index.php?ppv_business=admin_push', 'top');
        add_rewrite_rule('^business/admin/stats/?$', 'index.php?ppv_business=admin_stats', 'top');
        add_rewrite_rule('^business/admin/filiale-new/?$', 'index.php?ppv_business=admin_filiale_new', 'top');
        add_rewrite_rule('^business/([^/]+)/?$', 'index.php?ppv_business=public&slug=$matches[1]', 'top');
        add_rewrite_rule('^karte/?$', 'index.php?ppv_business=karte', 'top');

        add_filter('query_vars', function($vars) {
            $vars[] = 'ppv_business';
            $vars[] = 'slug';
            return $vars;
        });
    }

    public static function route_dispatcher() {
        $route = get_query_var('ppv_business');
        if (!$route) return;

        // No-cache headers for ALL business pages — prevents LiteSpeed / Cloudflare / browser caching
        // The /business/* admin area is per-user dynamic content, never cacheable
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            // LiteSpeed-specific
            header('X-LiteSpeed-Cache-Control: no-cache, no-store, private');
            header('X-LiteSpeed-Tag: no-cache');
            // Cloudflare-specific
            header('CDN-Cache-Control: no-store');
            header('Cloudflare-CDN-Cache-Control: no-store');
        }

        switch ($route) {
            case 'register':       self::render_register(); break;
            case 'login':          self::render_login(); break;
            case 'logout':         self::do_logout(); break;
            case 'admin':          self::render_admin_dashboard(); break;
            case 'admin_profile':  self::render_admin_profile(); break;
            case 'admin_ads':      self::render_admin_ads(); break;
            case 'admin_push':     self::render_admin_push(); break;
            case 'admin_stats':    self::render_admin_stats(); break;
            case 'admin_filiale_new': self::render_admin_filiale_new(); break;
            case 'public':         self::render_public_profile(get_query_var('slug')); break;
            case 'karte':          self::render_user_map(); break;
            default: return;
        }
        exit;
    }

    /** ============================================================
     * AUTH
     * ============================================================ */
    private static function start_session() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function current_advertiser_id() {
        self::start_session();
        return !empty($_SESSION['ppv_advertiser_id']) ? intval($_SESSION['ppv_advertiser_id']) : 0;
    }

    public static function current_advertiser() {
        $id = self::current_advertiser_id();
        if (!$id) return null;
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_advertisers WHERE id = %d", $id));
    }

    public static function require_login() {
        if (!self::current_advertiser_id()) {
            wp_redirect(home_url('/business/login'));
            exit;
        }
    }

    /** ============================================================
     * PLACEHOLDERS — render_* (külön template fájlokba kerülnek)
     * ============================================================ */
    public static function render_register() {
        require __DIR__ . '/templates/business-register.php';
    }
    public static function render_login() {
        require __DIR__ . '/templates/business-login.php';
    }
    public static function do_logout() {
        self::start_session();
        unset($_SESSION['ppv_advertiser_id']);
        // Also clear regular user session in case of dual-role login
        unset($_SESSION['ppv_user_id'], $_SESSION['ppv_user_type'], $_SESSION['ppv_user_email']);
        wp_redirect(home_url('/login'));
        exit;
    }
    public static function render_admin_dashboard() {
        self::require_login();
        require __DIR__ . '/templates/business-admin-dashboard.php';
    }
    public static function render_admin_profile() {
        self::require_login();
        require __DIR__ . '/templates/business-admin-profile.php';
    }
    public static function render_admin_ads() {
        self::require_login();
        require __DIR__ . '/templates/business-admin-ads.php';
    }
    public static function render_admin_push() {
        self::require_login();
        require __DIR__ . '/templates/business-admin-push.php';
    }
    public static function render_admin_stats() {
        self::require_login();
        require __DIR__ . '/templates/business-admin-stats.php';
    }
    public static function render_admin_filiale_new() {
        self::require_login();
        require __DIR__ . '/templates/business-admin-filiale-new.php';
    }
    public static function render_public_profile($slug) {
        require __DIR__ . '/templates/business-public.php';
    }
    public static function render_user_map() {
        require __DIR__ . '/templates/business-user-map.php';
    }

    /** ============================================================
     * HANDLERS — POST endpoints (admin-post.php)
     * ============================================================ */
    public static function handle_register() {
        check_admin_referer('ppv_advertiser_register');
        global $wpdb;

        $email = sanitize_email($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        if (!is_email($email) || strlen($pass) < 6) {
            wp_die(esc_html__('Hiányzó adatok.', 'punktepass'));
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_advertisers WHERE owner_email = %s", $email
        ));
        if ($exists) wp_die(esc_html__('Ez az email már regisztrálva van.', 'punktepass'));

        // Auto-generate a placeholder business name from email local-part — user fills it in profile.
        $email_local = strstr($email, '@', true) ?: $email;
        $name = ucfirst(preg_replace('/[^a-z0-9]+/', ' ', strtolower($email_local)));
        $slug = sanitize_title($name) . '-' . substr(md5($email . microtime()), 0, 6);

        // 2026-05-05: minden piacra 3 hónap (90 nap) ingyenes próba — egységes ajánlat.
        $signup_lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : '';
        if (!$signup_lang && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $signup_lang = strtolower(substr(trim(explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'])[0]), 0, 2));
        }
        $trial_days = 90;

        $wpdb->insert($wpdb->prefix . 'ppv_advertisers', [
            'slug' => $slug,
            'business_name' => $name,
            'owner_email' => $email,
            'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
            'tier' => self::TIER_BASIC,
            'subscription_status' => 'trial',
            'subscription_until' => date('Y-m-d', strtotime("+{$trial_days} days")),
            'push_month_reset_at' => date('Y-m-d', strtotime('first day of next month')),
        ]);
        $id = $wpdb->insert_id;

        self::start_session();
        $_SESSION['ppv_advertiser_id'] = $id;
        wp_redirect(home_url('/business/admin/?welcome=1'));
        exit;
    }

    public static function handle_login() {
        check_admin_referer('ppv_advertiser_login');
        global $wpdb;
        $email = sanitize_email($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, password_hash FROM {$wpdb->prefix}ppv_advertisers WHERE owner_email = %s AND is_active = 1",
            $email
        ));
        if (!$row || !password_verify($pass, $row->password_hash)) {
            wp_redirect(home_url('/business/login?err=1'));
            exit;
        }
        self::start_session();
        $_SESSION['ppv_advertiser_id'] = (int)$row->id;
        wp_redirect(home_url('/business/admin'));
        exit;
    }

    public static function handle_save_profile() {
        check_admin_referer('ppv_advertiser_save_profile');
        self::require_login();
        $adv_id = self::current_filiale_id();
        if (!$adv_id) {
            wp_die('Authentication required.');
        }

        $parent_advertiser = self::current_advertiser();
        $parent_id = $parent_advertiser->parent_advertiser_id ?: $parent_advertiser->id;

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        global $wpdb;
        $table_name = $wpdb->prefix . 'ppv_advertisers';
        $current = $wpdb->get_row($wpdb->prepare("SELECT logo_url, gallery FROM {$table_name} WHERE id = %d", $adv_id));

        // Logo upload (replaces previous if new file)
        $logo_url = $current->logo_url ?? '';
        if (!empty($_FILES['logo_file']['name'])) {
            $att_id = media_handle_upload('logo_file', 0);
            if (!is_wp_error($att_id)) {
                $logo_url = wp_get_attachment_url($att_id);
            }
        }

        // Gallery: existing kept + new appended (up to 8 total)
        $gallery = [];
        if (!empty($current->gallery)) {
            $decoded = json_decode($current->gallery, true);
            if (is_array($decoded)) $gallery = $decoded;
        }
        // Remove items user wanted to delete
        $remove = $_POST['gallery_remove'] ?? [];
        if (is_array($remove)) {
            $gallery = array_values(array_filter($gallery, fn($u) => !in_array($u, $remove, true)));
        }
        // Add new uploads
        if (!empty($_FILES['gallery_files']['name'][0])) {
            $files = $_FILES['gallery_files'];
            $count = count($files['name']);
            for ($i = 0; $i < $count && count($gallery) < 8; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $_FILES['__one'] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
                $att_id = media_handle_upload('__one', 0);
                if (!is_wp_error($att_id)) {
                    $gallery[] = wp_get_attachment_url($att_id);
                }
                unset($_FILES['__one']);
            }
        }

        // Country: from select OR manual input
        $country_select = sanitize_text_field($_POST['country_select'] ?? '');
        $country_manual = sanitize_text_field($_POST['country_manual'] ?? '');
        $country = ($country_select === '__other') ? $country_manual : $country_select;

        // Opening hours
        $hours_in = $_POST['hours'] ?? [];
        $hours = [];
        if (is_array($hours_in)) {
            foreach ($hours_in as $day => $h) {
                $day = sanitize_key($day);
                $closed = !empty($h['closed']);
                $open = preg_match('/^\d{2}:\d{2}$/', $h['open'] ?? '') ? $h['open'] : '';
                $close = preg_match('/^\d{2}:\d{2}$/', $h['close'] ?? '') ? $h['close'] : '';
                if ($closed) {
                    $hours[$day] = ['closed' => true];
                } elseif ($open && $close) {
                    $hours[$day] = ['open' => $open, 'close' => $close];
                }
            }
        }

        // Social links
        $social_in = $_POST['social'] ?? [];
        $social = [];
        if (is_array($social_in)) {
            foreach ($social_in as $k => $v) {
                $url = trim($v);
                if (!$url) continue;
                if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
                $social[sanitize_key($k)] = esc_url_raw($url);
            }
        }

        $business_name_in = sanitize_text_field($_POST['business_name'] ?? '');
        $address = sanitize_text_field($_POST['address'] ?? '');
        $city    = sanitize_text_field($_POST['city'] ?? '');
        $lat     = is_numeric($_POST['lat'] ?? null) ? floatval($_POST['lat']) : null;
        $lng     = is_numeric($_POST['lng'] ?? null) ? floatval($_POST['lng']) : null;

        $collision_clause = $wpdb->prepare(
            " AND ((T.parent_advertiser_id IS NULL AND T.id != %d) OR (T.parent_advertiser_id IS NOT NULL AND T.parent_advertiser_id != %d))",
            $parent_id, $parent_id
        );

        // 🛡️ Anti-collision: business name
        if (strlen(trim($business_name_in)) >= 2) {
            $name_norm = mb_strtolower(preg_replace('/\s+/', ' ', trim($business_name_in)));
            $name_collision = $wpdb->get_var($wpdb->prepare(
                "SELECT T.id FROM {$table_name} T
                 WHERE T.id <> %d
                   AND LOWER(REPLACE(REPLACE(TRIM(T.business_name), '  ', ' '), '   ', ' ')) = %s
                 {$collision_clause}
                 LIMIT 1",
                $adv_id, $name_norm
            ));
            if ($name_collision) {
                wp_redirect(home_url('/business/admin/profile?err=name_taken'));
                exit;
            }
        }

        // 🛡️ Anti-collision: address
        $addr_norm = mb_strtolower(preg_replace('/\s+/', ' ', trim($address . ' ' . $city)));
        if (strlen($addr_norm) >= 6) {
            $addr_collision = $wpdb->get_var($wpdb->prepare(
                "SELECT T.id FROM {$table_name} T
                 WHERE T.id <> %d
                   AND LOWER(REPLACE(REPLACE(TRIM(CONCAT_WS(' ', T.address, T.city)), '  ', ' '), '   ', ' ')) = %s
                 {$collision_clause}
                 LIMIT 1",
                $adv_id, $addr_norm
            ));
            if ($addr_collision) {
                wp_redirect(home_url('/business/admin/profile?err=address_taken'));
                exit;
            }
        }
        
        // 🛡️ Anti-collision: GPS pin (ellenőrzés MINDEN más pin-nel, saját fiókon belül is)
        if ($lat !== null && $lng !== null) {
            $gps_collision = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_name}
                 WHERE id != %d AND lat IS NOT NULL AND lng IS NOT NULL
                   AND ABS(lat - %f) < 0.00009 AND ABS(lng - %f) < 0.00009
                 LIMIT 1",
                $adv_id, $lat, $lng
            ));
            if ($gps_collision) {
                wp_redirect(home_url('/business/admin/profile?err=pin_too_close'));
                exit;
            }
        }

        $data = [
            'business_name' => sanitize_text_field($_POST['business_name'] ?? ''),
            'phone'    => sanitize_text_field($_POST['phone'] ?? ''),
            'whatsapp' => sanitize_text_field($_POST['whatsapp'] ?? ''),
            'website'  => esc_url_raw($_POST['website'] ?? ''),
            'address'  => $address,
            'city'     => $city,
            'country'  => $country,
            'postcode' => sanitize_text_field($_POST['postcode'] ?? ''),
            'lat'      => $lat,
            'lng'      => $lng,
            'category' => sanitize_text_field($_POST['category'] ?? 'other'),
            'logo_url' => $logo_url,
            'gallery'  => wp_json_encode(array_values($gallery)),
            'social'   => wp_json_encode($social),
            'opening_hours' => wp_json_encode($hours),
        ];
        $wpdb->update($table_name, $data, ['id' => $adv_id]);
        wp_redirect(home_url('/business/admin/profile?saved=1'));
        exit;
    }

    public static function handle_save_ad() {
        check_admin_referer('ppv_advertiser_save_ad');
        $adv_id = self::current_advertiser_id();
        if (!$adv_id) wp_die('Auth required.');

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        global $wpdb;
        $ad_id = intval($_POST['ad_id'] ?? 0);
        
        // -- FAZIS 5 START --
        $filiale_id = isset($_POST['filiale_id']) ? (int)$_POST['filiale_id'] : null;
        if ($filiale_id === 0) {
            $filiale_id = null;
        }
        
        // Find parent advertiser ID
        $advertiser = self::current_advertiser();
        $parent_id = $advertiser->parent_advertiser_id ?: $advertiser->id;

        // Race condition check: only for new ads
        if (!$ad_id && $filiale_id) {
            $existing_ad_for_filiale = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_ads WHERE filiale_id = %d AND is_active = 1",
                $filiale_id
            ));
            if ($existing_ad_for_filiale) {
                wp_redirect(home_url('/business/admin/ads?err=filiale_ad_exists'));
                exit;
            }
        }
        // -- FAZIS 5 END --

        $existing_image = '';
        if ($ad_id > 0) {
            $existing_image = (string)$wpdb->get_var($wpdb->prepare(
                "SELECT image_url FROM {$wpdb->prefix}ppv_ads WHERE id = %d", $ad_id
            ));
        }
        $image_url = $existing_image;
        if (!empty($_FILES['image_file']['name'])) {
            $att_id = media_handle_upload('image_file', 0);
            if (!is_wp_error($att_id)) {
                $image_url = wp_get_attachment_url($att_id);
            }
        }

        $visibility = in_array($_POST['visibility'] ?? '', ['public','followers'], true) ? $_POST['visibility'] : 'public';
        $ad_type_in = sanitize_text_field($_POST['ad_type'] ?? 'coupon');
        $ad_type = in_array($ad_type_in, ['coupon','discount_percent','discount_fixed','free_product','event','announcement'], true) ? $ad_type_in : 'coupon';
        $per_user_limit = in_array($_POST['per_user_limit'] ?? '', ['lifetime','daily','weekly','monthly','none'], true) ? $_POST['per_user_limit'] : 'lifetime';
        $max_claims_in = $_POST['max_claims'] ?? '';
        $max_claims = ($max_claims_in === '' || $max_claims_in === null) ? null : max(0, (int)$max_claims_in);

        $data = [
            'advertiser_id' => $parent_id, // Always associate with parent
            'filiale_id' => $filiale_id, // Can be null
            'ad_type'    => $ad_type,
            'visibility' => $visibility,
            'title'      => sanitize_text_field($_POST['title'] ?? ''),
            'body'       => wp_kses_post($_POST['body'] ?? ''),
            'promo_value'=> sanitize_text_field($_POST['promo_value'] ?? ''),
            'coupon_code'=> strtoupper(preg_replace('/[^A-Z0-9_-]/i', '', $_POST['coupon_code'] ?? '')),
            'badge'      => sanitize_text_field($_POST['badge'] ?? ''),
            'max_claims' => $max_claims,
            'per_user_limit' => $per_user_limit,
            'image_url'  => $image_url,
            'cta_url'    => esc_url_raw($_POST['cta_url'] ?? ''),
            'valid_from' => sanitize_text_field($_POST['valid_from'] ?? '') ?: null,
            'valid_to'   => sanitize_text_field($_POST['valid_to'] ?? '') ?: null,
            'followers_only' => $visibility === 'followers' ? 1 : 0,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        ];

        if ($ad_id > 0) {
            // Verify ownership
            $owner = $wpdb->get_var($wpdb->prepare(
                "SELECT advertiser_id FROM {$wpdb->prefix}ppv_ads WHERE id = %d", $ad_id
            ));
            if ((int)$owner !== $parent_id) wp_die('Not your ad.');
            
            // Filiale cannot be changed after creation
            unset($data['filiale_id']);

            $wpdb->update($wpdb->prefix . 'ppv_ads', $data, ['id' => $ad_id]);
        } else {
            // Tier ad limit check (this logic might need adjustment depending on how tiers are counted per-parent vs per-filiale)
            $current_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_ads WHERE advertiser_id = %d AND is_active = 1", $parent_id
            ));
            $tier = $wpdb->get_var($wpdb->prepare("SELECT tier FROM {$wpdb->prefix}ppv_advertisers WHERE id = %d", $parent_id));
            
            // This is a simplified check. A more complex one might be needed.
            // For now, we assume the total number of ads for the parent account is limited.
            $max_filialen = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_advertisers WHERE (id = %d OR parent_advertiser_id = %d) AND is_active = 1", $parent_id, $parent_id));
            $max = $max_filialen; // 1 ad per filiale/parent

            if ($current_count >= $max) {
                wp_redirect(home_url('/business/admin/ads?err=tier_limit'));
                exit;
            }
            $wpdb->insert($wpdb->prefix . 'ppv_ads', $data);
        }
        wp_redirect(home_url('/business/admin/ads?saved=1'));
        exit;
    }

    /**
     * Soft-delete an ad (is_active = 0). Auth: parent advertiser only.
     */
    public static function handle_delete_ad() {
        check_admin_referer('ppv_advertiser_delete_ad');
        $adv_id = self::current_advertiser_id();
        if (!$adv_id) wp_die('Auth required.');

        global $wpdb;
        $ad_id = (int)($_POST['ad_id'] ?? 0);
        if ($ad_id < 1) {
            wp_redirect(home_url('/business/admin/ads'));
            exit;
        }
        $owner = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT advertiser_id FROM {$wpdb->prefix}ppv_ads WHERE id = %d", $ad_id
        ));
        if ($owner !== $adv_id) wp_die('Not your ad.');
        $wpdb->update($wpdb->prefix . 'ppv_ads', ['is_active' => 0], ['id' => $ad_id]);
        wp_redirect(home_url('/business/admin/ads?deleted=1'));
        exit;
    }

    public static function handle_send_push() {
        check_admin_referer('ppv_advertiser_send_push');
        $adv_id = self::current_advertiser_id();
        if (!$adv_id) wp_die('Auth required.');

        global $wpdb;
        $adv = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_advertisers WHERE id = %d", $adv_id));
        if (!$adv) wp_die('Not found.');

        // Reset monthly counter if month changed
        if (empty($adv->push_month_reset_at) || $adv->push_month_reset_at <= date('Y-m-d')) {
            $wpdb->update($wpdb->prefix . 'ppv_advertisers',
                ['push_used_this_month' => 0,
                 'push_month_reset_at' => date('Y-m-d', strtotime('first day of next month'))],
                ['id' => $adv_id]
            );
            $adv->push_used_this_month = 0;
        }

        $tier_cap = self::TIERS[$adv->tier]['push_per_month'] ?? 4;
        if ($adv->push_used_this_month >= $tier_cap) {
            wp_redirect(home_url('/business/admin/push?err=cap'));
            exit;
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $body  = sanitize_text_field($_POST['body'] ?? '');
        if (!$title || !$body) {
            wp_redirect(home_url('/business/admin/push?err=empty'));
            exit;
        }

        // Get followers with push_enabled
        $followers = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}ppv_advertiser_followers WHERE advertiser_id = %d AND push_enabled = 1",
            $adv_id
        ));
        // Include the advertiser's own logged-in PPV user (if any) for self-test delivery
        self::start_session();
        if (!empty($_SESSION['ppv_user_id'])) {
            $followers[] = (int)$_SESSION['ppv_user_id'];
        }
        $followers = array_values(array_unique(array_filter(array_map('intval', $followers))));
        // Display sender = business_name (so users see WHO pushed) — like profile-lite Absender pattern.
        $sender_name = !empty($adv->business_name) ? $adv->business_name : 'PunktePass';
        $push_title = $sender_name;
        // If user typed a different title than the business name, prepend it as the first line of body.
        $push_body  = ($title && $title !== $sender_name) ? ($title . "\n" . $body) : $body;
        $sent = 0;
        if (!empty($followers) && class_exists('PPV_Push')) {
            foreach ($followers as $uid) {
                $result = PPV_Push::send_to_user((int)$uid, [
                    'title' => $push_title,
                    'body'  => $push_body,
                    'data'  => ['type' => 'advertiser', 'advertiser_id' => $adv_id, 'slug' => $adv->slug, 'sender' => $sender_name],
                ]);
                if (!empty($result['success'])) $sent += (int)($result['sent'] ?? 0);
            }
        }
        $wpdb->update($wpdb->prefix . 'ppv_advertisers',
            ['push_used_this_month' => $adv->push_used_this_month + 1],
            ['id' => $adv_id]
        );

        wp_redirect(home_url('/business/admin/push?sent=' . $sent));
        exit;
    }

    public static function handle_follow() {
        check_admin_referer('ppv_advertiser_follow');
        $user_id = !empty($_SESSION['ppv_user_id']) ? (int)$_SESSION['ppv_user_id'] : 0;
        if (!$user_id) wp_die('Login required.');

        global $wpdb;
        $adv_id = (int)($_POST['advertiser_id'] ?? 0);
        $action = sanitize_text_field($_POST['act'] ?? 'follow');

        $table = $wpdb->prefix . 'ppv_advertiser_followers';
        if ($action === 'unfollow') {
            $wpdb->delete($table, ['user_id' => $user_id, 'advertiser_id' => $adv_id]);
        } else {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d AND advertiser_id = %d", $user_id, $adv_id
            ));
            if (!$exists) {
                $wpdb->insert($table, ['user_id' => $user_id, 'advertiser_id' => $adv_id, 'push_enabled' => 1]);
            }
        }
        wp_redirect(wp_get_referer() ?: home_url('/karte'));
        exit;
    }

    public static function handle_filiale_create() {
        // 1. Security and Auth
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ppv_advertiser_filiale_create_nonce')) {
            wp_die('Nonce verification failed.', 'Security Check', ['response' => 403]);
        }

        $parent_advertiser = self::current_advertiser();
        if (!$parent_advertiser) {
            wp_redirect(home_url('/business/login?err=auth'));
            exit;
        }
        
        $parent_id = $parent_advertiser->parent_advertiser_id ?: $parent_advertiser->id;

        // 2. Validation
        $filiale_label = sanitize_text_field($_POST['filiale_label'] ?? '');
        $business_name = sanitize_text_field($_POST['business_name'] ?? '');
        $address = sanitize_text_field($_POST['address'] ?? '');
        $city = sanitize_text_field($_POST['city'] ?? '');

        if (empty($filiale_label) || empty($business_name) || empty($address) || empty($city)) {
            wp_redirect(home_url('/business/admin/filiale-new?err=missing_fields'));
            exit;
        }

        // 3. Database Insert
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_advertisers';

        // Inherit some fields from parent
        $parent_data = $wpdb->get_row($wpdb->prepare("SELECT owner_email, password_hash, tier, subscription_status, subscription_until, category FROM {$table} WHERE id = %d", $parent_id));

        $data = [
            'parent_advertiser_id' => $parent_id,
            'filiale_label' => $filiale_label,
            'slug' => sanitize_title($business_name . '-' . $filiale_label) . '-' . substr(wp_generate_uuid4(), 0, 8),
            'business_name' => $business_name,
            'address' => $address,
            'city' => $city,
            'postcode' => sanitize_text_field($_POST['postcode'] ?? ''),
            'country' => sanitize_text_field($_POST['country'] ?? $parent_advertiser->country),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'lat' => !empty($_POST['lat']) && is_numeric($_POST['lat']) ? floatval($_POST['lat']) : null,
            'lng' => !empty($_POST['lng']) && is_numeric($_POST['lng']) ? floatval($_POST['lng']) : null,
            // Filiale rows share the parent's password but need unique email (uniq_email constraint).
            // Synthesize: parent-prefix+filiale-suffix@local.filiale.punktepass
            'owner_email' => 'filiale+' . $parent_id . '-' . substr(md5($filiale_label . microtime()), 0, 8) . '@local.filiale.punktepass',
            'password_hash' => $parent_data->password_hash,
            'tier' => $parent_data->tier,
            'subscription_status' => $parent_data->subscription_status,
            'subscription_until' => $parent_data->subscription_until,
            'is_active' => 1,
            'category' => $parent_data->category,
        ];
        
        // Anti-collision: GPS-pin check (ellenőrzés MINDEN más pin-nel)
        if ($data['lat'] && $data['lng']) {
             $gps_collision = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_advertisers
                 WHERE lat IS NOT NULL AND lng IS NOT NULL
                   AND ABS(lat - %f) < 0.00009 AND ABS(lng - %f) < 0.00009
                 LIMIT 1",
                $data['lat'], $data['lng']
            ));
            if ($gps_collision) {
                wp_redirect(home_url('/business/admin/filiale-new?err=pin_too_close'));
                exit;
            }
        }

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            wp_redirect(home_url('/business/admin/filiale-new?err=db_error&msg=' . urlencode($wpdb->last_error)));
            exit;
        }

        // 4. Redirect
        wp_redirect(home_url('/business/admin/?filiale_created=1'));
        exit;
    }

    /** ============================================================
     * HELPERS
     * ============================================================ */
    public static function get_active_ads_for_map($lang = 'de', $bbox = null) {
        global $wpdb;
        $where = "a.is_active = 1 AND a.subscription_status IN ('trial', 'active') AND a.lat IS NOT NULL AND a.lng IS NOT NULL";
        if ($bbox && count($bbox) === 4) {
            list($minlat, $minlng, $maxlat, $maxlng) = array_map('floatval', $bbox);
            $where .= $wpdb->prepare(
                " AND a.lat BETWEEN %f AND %f AND a.lng BETWEEN %f AND %f",
                $minlat, $maxlat, $minlng, $maxlng
            );
        }
        return $wpdb->get_results(
            "SELECT a.id, a.slug, a.business_name, a.lat, a.lng, a.category, a.logo_url, a.tier, a.featured
             FROM {$wpdb->prefix}ppv_advertisers a
             WHERE {$where}
             ORDER BY a.featured DESC, a.id DESC"
        );
    }

    /** ============================================================
     * REST API
     * ============================================================ */
    public static function register_rest() {
        register_rest_route('punktepass/v1', '/map/nearby', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_map_nearby'],
            'permission_callback' => '__return_true',
            'args' => [
                'lat'    => ['type' => 'number', 'required' => false],
                'lng'    => ['type' => 'number', 'required' => false],
                'radius' => ['type' => 'number', 'default' => 50],
            ],
        ]);
        register_rest_route('punktepass/v1', '/advertiser/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_advertiser_detail'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('punktepass/v1', '/follow', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_follow'],
            'permission_callback' => '__return_true',
        ]);
        // Ad click tracker: bumps clicks counter and 302-redirects to the target URL
        register_rest_route('punktepass/v1', '/ad-click/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_ad_click'],
            'permission_callback' => '__return_true',
        ]);
        // Coupon claim — atomic decrement; returns coupon_code on success
        register_rest_route('punktepass/v1', '/coupon-claim', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_coupon_claim'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('punktepass/v1', '/filiale-delete', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_filiale_delete'],
            'permission_callback' => function() {
                return self::current_advertiser_id() > 0;
            }
        ]);

        // Flyer request endpoint (advertiser-only — moved from handler admin to business advertiser admin)
        register_rest_route('punktepass/v1', '/flyer-request', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_flyer_request'],
            'permission_callback' => function() {
                if (session_status() === PHP_SESSION_NONE) {
                    if (function_exists('ppv_maybe_start_session')) ppv_maybe_start_session();
                }
                if (self::current_advertiser_id()) {
                    return true;
                }
                // Backward-compat: allow handler session too (in case old form ever resurfaces)
                if (!empty($_SESSION['ppv_store_id']) || !empty($_SESSION['ppv_current_filiale_id']) || !empty($_SESSION['ppv_vendor_store_id'])) {
                    return true;
                }
                return current_user_can('manage_options');
            },
        ]);
    }

    public static function rest_filiale_delete($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $target_id = isset($params['target_id']) ? intval($params['target_id']) : 0;

        if ($target_id <= 0) {
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid target ID.'], 400);
        }

        $current_adv = self::current_advertiser();
        if (!$current_adv) {
            return new WP_REST_Response(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        // Determine the root parent ID of the logged-in user
        $parent_id = $current_adv->parent_advertiser_id ?: $current_adv->id;
        
        // Prevent deleting the main account
        if ($target_id === $parent_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Cannot delete the main account.'], 403);
        }

        // Fetch the filiale to be deleted to verify ownership
        $filiale_to_delete = $wpdb->get_row($wpdb->prepare("SELECT id, parent_advertiser_id FROM {$wpdb->prefix}ppv_advertisers WHERE id = %d", $target_id));

        if (!$filiale_to_delete) {
            return new WP_REST_Response(['success' => false, 'message' => 'Filiale not found.'], 404);
        }

        // Ensure the filiale to be deleted belongs to the same parent account
        if ((int)$filiale_to_delete->parent_advertiser_id !== $parent_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Permission denied. Not a child of your account.'], 403);
        }

        // Perform soft delete
        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_advertisers',
            ['is_active' => 0],
            ['id' => $target_id],
            ['%d'],
            ['%d']
        );

        if ($result === false) {
            return new WP_REST_Response(['success' => false, 'message' => 'Database error during deletion.'], 500);
        }

        return new WP_REST_Response(['success' => true, 'message' => 'Filiale marked as inactive.'], 200);
    }

    /**
     * Handle a flyer-request submission. Primary submitter is now an advertiser
     * (business admin); falls back to handler session for backward-compat.
     */
    public static function rest_flyer_request($request) {
        global $wpdb;

        $params = $request->get_json_params();
        if (!is_array($params)) $params = $request->get_params();

        $advertiser_id = self::current_advertiser_id();

        $store_id = 0;
        if (!$advertiser_id) {
            // Backward-compat: handler-side flyer request
            if (!empty($_SESSION['ppv_store_id'])) $store_id = intval($_SESSION['ppv_store_id']);
            elseif (!empty($_SESSION['ppv_current_filiale_id'])) $store_id = intval($_SESSION['ppv_current_filiale_id']);
            elseif (!empty($_SESSION['ppv_vendor_store_id'])) $store_id = intval($_SESSION['ppv_vendor_store_id']);
            if (!$store_id && !empty($params['store_id'])) $store_id = intval($params['store_id']);
        }

        $user_id = !empty($_SESSION['ppv_user_id']) ? intval($_SESSION['ppv_user_id']) : 0;

        $name          = sanitize_text_field($params['name'] ?? '');
        $business_name = sanitize_text_field($params['business_name'] ?? '');
        $address       = sanitize_text_field($params['address'] ?? '');
        $postcode      = sanitize_text_field($params['postcode'] ?? '');
        $city          = sanitize_text_field($params['city'] ?? '');
        $country       = strtoupper(sanitize_text_field($params['country'] ?? ''));
        $quantity      = intval($params['quantity'] ?? 1);
        $language      = strtolower(sanitize_text_field($params['language'] ?? 'de'));
        $message       = sanitize_textarea_field($params['message'] ?? '');

        if ($name === '' || $business_name === '' || $address === '' || $city === '' || $country === '' || $postcode === '') {
            return new WP_REST_Response(['success' => false, 'message' => 'Missing required fields'], 400);
        }
        // Free flyer = always exactly 1 piece per request
        $quantity = 1;
        if (!in_array($language, ['de','hu','ro','en'], true)) $language = 'de';
        if (!in_array($country, ['DE','HU','RO','AT','CH'], true)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid country'], 400);
        }

        $table = $wpdb->prefix . 'ppv_flyer_requests';

        // Self-heal: create table if missing (in case migration didn't run yet)
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        if (!$exists) {
            self::create_flyer_requests_table();
        }

        // Self-heal: ensure advertiser_id column exists (for installations that already have the table from migration 2.9)
        $col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'advertiser_id'");
        if (!$col_exists) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN advertiser_id BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER store_id");
            $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_advertiser (advertiser_id)");
        }

        $now = current_time('mysql');
        $insert_data = [
            'store_id'      => $store_id,
            'advertiser_id' => $advertiser_id ?: null,
            'user_id'       => $user_id,
            'name'          => $name,
            'business_name' => $business_name,
            'address'       => $address,
            'postcode'      => $postcode,
            'city'          => $city,
            'country'       => $country,
            'quantity'      => $quantity,
            'language'      => $language,
            'message'       => $message,
            'status'        => 'pending',
            'created_at'    => $now,
            'updated_at'    => $now,
        ];
        $insert_fmt = ['%d','%d','%d','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s'];
        $ok = $wpdb->insert($table, $insert_data, $insert_fmt);

        if ($ok === false) {
            ppv_log('❌ [PPV_Advertisers] flyer-request insert failed: ' . $wpdb->last_error);
            return new WP_REST_Response(['success' => false, 'message' => 'DB insert failed'], 500);
        }

        $request_id = (int)$wpdb->insert_id;

        // Resolve submitter info for email
        $submitter_label = '';
        if ($advertiser_id) {
            $adv_row = $wpdb->get_row($wpdb->prepare("SELECT id, slug, business_name FROM {$wpdb->prefix}ppv_advertisers WHERE id = %d", $advertiser_id));
            if ($adv_row) {
                $submitter_label = "Advertiser ID: {$adv_row->id}, slug: {$adv_row->slug}, business: {$adv_row->business_name}";
            } else {
                $submitter_label = "Advertiser ID: {$advertiser_id}";
            }
        } elseif ($store_id) {
            $submitter_label = "Handler / Store ID: {$store_id}";
        } else {
            $submitter_label = "Unknown submitter";
        }

        // Email notification
        $subject = 'PunktePass Flyer kérés - ' . $business_name;
        $body  = "Új flyer kérés érkezett.\n\n";
        $body .= "Request ID: {$request_id}\n";
        $body .= "Submitter: {$submitter_label}\n";
        $body .= "User ID: {$user_id}\n";
        $body .= "Név: {$name}\n";
        $body .= "Cégnév: {$business_name}\n";
        $body .= "Cím: {$address}\n";
        $body .= "Irányítószám: {$postcode}\n";
        $body .= "Város: {$city}\n";
        $body .= "Ország: {$country}\n";
        $body .= "Mennyiség: {$quantity} db\n";
        $body .= "Nyelv: {$language}\n";
        $body .= "Üzenet: " . ($message !== '' ? $message : '-') . "\n";
        $body .= "\nLétrehozva: {$now}\n";
        $body .= "Admin: https://punktepass.de/admin/flyer-requests\n";

        @wp_mail('borota25@gmail.com', $subject, $body);

        return new WP_REST_Response(['success' => true, 'message' => 'OK', 'id' => $request_id], 200);
    }

    /**
     * Create the ppv_flyer_requests table.
     */
    public static function create_flyer_requests_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_flyer_requests';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            store_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            advertiser_id BIGINT UNSIGNED NULL DEFAULT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(120) NOT NULL DEFAULT '',
            business_name VARCHAR(120) NOT NULL DEFAULT '',
            address VARCHAR(200) NOT NULL DEFAULT '',
            postcode VARCHAR(20) NOT NULL DEFAULT '',
            city VARCHAR(80) NOT NULL DEFAULT '',
            country VARCHAR(8) NOT NULL DEFAULT '',
            quantity SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            language VARCHAR(8) NOT NULL DEFAULT 'de',
            message TEXT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_store (store_id),
            KEY idx_advertiser (advertiser_id),
            KEY idx_status (status, created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        ppv_log('✅ [PPV_Advertisers] Created ppv_flyer_requests table');
    }

    public static function rest_ad_click($req) {
        global $wpdb;
        $id = (int)$req['id'];
        $to = $req->get_param('to');
        // Skip bots
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (!preg_match('/(bot|crawler|spider|preview)/i', $ua)) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}ppv_ads SET clicks = clicks + 1 WHERE id = %d",
                $id
            ));
        }
        // Validate redirect target — must be a valid http(s) URL
        if (!$to || !preg_match('#^https?://#i', $to)) {
            $to = home_url('/');
        }
        wp_redirect(esc_url_raw($to), 302);
        exit;
    }

    /**
     * Atomic coupon claim. Body: { ad_id }.
     * Returns: { ok:true, coupon_code, remaining } on success, 410 sold_out on cap.
     */
    public static function rest_coupon_claim($req) {
        global $wpdb;
        $body = $req->get_json_params() ?: [];
        $ad_id = (int)($body['ad_id'] ?? 0);
        if ($ad_id < 1) {
            return new WP_REST_Response(['ok' => false, 'message' => 'invalid_ad_id'], 400);
        }
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ppv_ads
             SET claim_count = claim_count + 1
             WHERE id = %d AND is_active = 1
               AND (max_claims IS NULL OR max_claims = 0 OR claim_count < max_claims)",
            $ad_id
        ));
        if ($affected !== 1) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, is_active FROM {$wpdb->prefix}ppv_ads WHERE id = %d", $ad_id
            ));
            if (!$row || !$row->is_active) {
                return new WP_REST_Response(['ok' => false, 'message' => 'not_found'], 404);
            }
            return new WP_REST_Response(['ok' => false, 'message' => 'sold_out'], 410);
        }
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT coupon_code, max_claims, claim_count
             FROM {$wpdb->prefix}ppv_ads WHERE id = %d", $ad_id
        ));
        $max_c = ($row->max_claims === null || $row->max_claims === '' || (int)$row->max_claims === 0) ? null : (int)$row->max_claims;
        $remaining = $max_c === null ? null : max(0, $max_c - (int)$row->claim_count);
        return new WP_REST_Response([
            'ok' => true,
            'coupon_code' => $row->coupon_code,
            'remaining' => $remaining,
        ], 200);
    }

    public static function rest_map_nearby($req) {
        global $wpdb;
        $lat = $req->get_param('lat');
        $lng = $req->get_param('lng');
        $radius = max(1, min(500, (float)$req->get_param('radius')));
        $user_id = !empty($_SESSION['ppv_user_id']) ? (int)$_SESSION['ppv_user_id'] : 0;

        // Loyalty stores — only active + non-expired subscription
        $stores = $wpdb->get_results(
            "SELECT id, name, latitude AS lat, longitude AS lng, logo, address, city, phone
             FROM {$wpdb->prefix}ppv_stores
             WHERE latitude IS NOT NULL AND longitude IS NOT NULL
               AND active = 1
               AND (subscription_expires_at IS NULL OR subscription_expires_at > NOW())
               AND (trial_ends_at IS NULL OR subscription_status != 'trial' OR trial_ends_at > NOW())
             LIMIT 1000"
        );

        // Advertisers — FÁZIS 7: minden filiale (parent+child) külön pin, ha van lat/lng.
        $advertisers = $wpdb->get_results(
            "SELECT id, business_name, filiale_label, slug, lat, lng, logo_url AS logo, address, city, phone, whatsapp, category, featured, tier
             FROM {$wpdb->prefix}ppv_advertisers
             WHERE is_active = 1 AND lat IS NOT NULL AND lng IS NOT NULL
               AND subscription_status IN ('trial', 'active')
               AND (subscription_until IS NULL OR subscription_until >= CURDATE())
             LIMIT 2000"
        );

        $features = [];
        foreach ($stores as $s) {
            $features[] = [
                'type'   => 'loyalty',
                'id'     => (int)$s->id,
                'slug'   => '',
                'name'   => $s->name,
                'lat'    => (float)$s->lat,
                'lng'    => (float)$s->lng,
                'logo'   => $s->logo ?? '',
                'address'=> trim(($s->address ?? '') . ', ' . ($s->city ?? ''), ', '),
                'phone'  => $s->phone ?? '',
                'whatsapp' => '',
                'following' => $user_id ? self::is_following_store($user_id, (int)$s->id) : false,
            ];
        }
        foreach ($advertisers as $a) {
            // FÁZIS 7: ha van filiale_label, hozzáfűzzük a névhez.
            $business_name = $a->business_name;
            if (!empty($a->filiale_label)) {
                $business_name .= ' — ' . $a->filiale_label;
            }

            $features[] = [
                'type'   => 'advertiser',
                'id'     => (int)$a->id,
                'slug'   => $a->slug,
                'name'   => $business_name,
                'lat'    => (float)$a->lat,
                'lng'    => (float)$a->lng,
                'logo'   => $a->logo,
                'address'=> trim(($a->address ?? '') . ', ' . ($a->city ?? ''), ', '),
                'phone'  => $a->phone,
                'whatsapp'=> $a->whatsapp,
                'category' => $a->category,
                'featured' => (int)$a->featured,
                'tier'   => $a->tier,
                'following' => $user_id ? self::is_following($user_id, (int)$a->id) : false,
            ];
        }
        return rest_ensure_response(['features' => $features]);
    }

    public static function rest_advertiser_detail($req) {
        global $wpdb;
        $id = (int)$req['id'];
        $lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de';
        $adv = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_advertisers WHERE id = %d AND is_active = 1", $id
        ));
        if (!$adv) return new WP_Error('not_found', 'Not found', ['status' => 404]);

        // FÁZIS 7: A hirdetéseket a kiválasztott filiáléhoz (vagy szülőhöz) szűrjük a kapott ID alapján.
        $ads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_ads
             WHERE filiale_id = %d AND is_active = 1
               AND (valid_from IS NULL OR valid_from <= NOW())
               AND (valid_to IS NULL OR valid_to >= NOW())
             ORDER BY id DESC", $id
        ));

        // Track clicks: every pin-click / advertiser-detail open = +1 click on each active ad.
        // (Per user request: impressions/CTR not used, only "how many clicked" matters.)
        // Skip bots; dedupe per session per ad with 10min cooldown.
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (!empty($ads) && !preg_match('/(bot|crawler|spider|preview)/i', $ua)) {
            if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
            $seen = $_SESSION['ppv_ad_click_seen'] ?? [];
            $bumped_ids = [];
            $now = time();
            foreach ($ads as $a) {
                $last = $seen[$a->id] ?? 0;
                if ($now - $last > 600) {
                    $bumped_ids[] = (int)$a->id;
                    $seen[$a->id] = $now;
                }
            }
            if (!empty($bumped_ids)) {
                $placeholders = implode(',', array_fill(0, count($bumped_ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ppv_ads SET clicks = clicks + 1 WHERE id IN ({$placeholders})",
                    ...$bumped_ids
                ));
            }
            $_SESSION['ppv_ad_click_seen'] = $seen;
        }

        $description = $adv->{'description_' . $lang} ?: $adv->description_de;
        $payload = [
            'id' => (int)$adv->id,
            'name' => $adv->business_name,
            'description' => $description,
            'logo' => $adv->logo_url,
            'cover' => $adv->cover_url,
            'phone' => $adv->phone,
            'whatsapp' => $adv->whatsapp,
            'website' => $adv->website,
            'address' => trim(($adv->address ?? '') . ', ' . ($adv->city ?? ''), ', '),
            'category' => $adv->category,
            'tier' => $adv->tier,
            'ads' => array_map(function($a) use ($lang) {
                // Fallback chain: per-language → German default → simple `title`/`body` (legacy/new form)
                $title = $a->{'title_' . $lang} ?: ($a->title_de ?: ($a->title ?? ''));
                $body  = $a->{'body_'  . $lang} ?: ($a->body_de  ?: ($a->body  ?? ''));
                $max_claims = isset($a->max_claims) && $a->max_claims !== null && $a->max_claims !== '' ? (int)$a->max_claims : null;
                $cur_claims = (int)($a->claim_count ?? 0);
                return [
                    'id' => (int)$a->id,
                    'title' => $title,
                    'body'  => $body,
                    'image_url' => $a->image_url,
                    'cta_url' => $a->cta_url,
                    'followers_only' => (bool)$a->followers_only,
                    'ad_type' => $a->ad_type ?? null,
                    'promo_value' => $a->promo_value ?? null,
                    'coupon_code' => $a->coupon_code ?? null,
                    'max_claims' => $max_claims,
                    'claim_count' => $cur_claims,
                    'remaining' => $max_claims === null ? null : max(0, $max_claims - $cur_claims),
                ];
            }, $ads),
        ];
        return rest_ensure_response($payload);
    }

    public static function rest_follow($req) {
        $user_id = !empty($_SESSION['ppv_user_id']) ? (int)$_SESSION['ppv_user_id'] : 0;
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'Bejelentkezés szükséges.', ['status' => 401]);
        }
        $type = $req->get_param('type');
        $target_id = (int)$req->get_param('target_id');
        $action = $req->get_param('action') ?: 'follow';

        global $wpdb;
        if ($type === 'loyalty') {
            $table = $wpdb->prefix . 'ppv_store_followers';
            $col = 'store_id';
        } else {
            $table = $wpdb->prefix . 'ppv_advertiser_followers';
            $col = 'advertiser_id';
        }
        if ($action === 'unfollow') {
            $wpdb->delete($table, ['user_id' => $user_id, $col => $target_id]);
            return ['ok' => true, 'following' => false];
        }
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d AND {$col} = %d", $user_id, $target_id
        ));
        if (!$exists) {
            $wpdb->insert($table, ['user_id' => $user_id, $col => $target_id, 'push_enabled' => 1]);
        }
        return ['ok' => true, 'following' => true];
    }

    public static function is_following_store($user_id, $store_id) {
        global $wpdb;
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_store_followers WHERE user_id = %d AND store_id = %d",
            $user_id, $store_id
        ));
    }

    public static function is_following($user_id, $advertiser_id) {
        global $wpdb;
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_advertiser_followers WHERE user_id = %d AND advertiser_id = %d",
            $user_id, $advertiser_id
        ));
    }

    /** ============================================================
     * FILIALE HELPERS
     * ============================================================ */

    /**
     * Visszaadja az aktív filiálé ID-t, vagy a szülő advertiser ID-t ha nincs beállítva.
     * @return int
     */
    public static function current_filiale_id() {
        self::start_session();
        if (!empty($_SESSION['ppv_active_filiale_id'])) {
            return intval($_SESSION['ppv_active_filiale_id']);
        }
        return self::current_advertiser_id();
    }

    /**
     * Lekér egy filiálét (ami egy advertiser-sor) ID alapján.
     * @param int $id
     * @return object|null
     */
    public static function get_filiale($id) {
        if (!$id) return null;
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_advertisers WHERE id = %d", $id));
    }

    /**
     * Megszámolja, hány aktív filiáléja van egy szülő advertisernek (a szülőt is beleértve).
     * @param int $parent_id
     * @return int
     */
    public static function count_filialen($parent_id) {
        if (!$parent_id) return 0;
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_advertisers WHERE (parent_advertiser_id = %d OR id = %d) AND is_active = 1",
            $parent_id, $parent_id
        ));
    }

    /**
     * Kiszámolja a push üzenetek limitjét a filiálék száma alapján.
     * @param int $parent_id
     * @return int
     */
    public static function get_effective_push_limit($parent_id) {
        $count = self::count_filialen($parent_id);
        if ($count < 3) return 4;
        if ($count >= 3 && $count <= 6) return 8;
        return 16;
    }

    /**
     * Kiszámolja a havidíjat a filiálék száma alapján.
     * @param int $parent_id
     * @param string $country_code 'DE', 'RO', etc.
     * @return float
     */
    public static function get_effective_price($parent_id, $country_code = 'DE') {
        $count = self::count_filialen($parent_id);
        $base_tier = self::TIERS[self::TIER_BASIC];
        $base_price = (strtoupper($country_code) === 'RO') ? $base_tier['price_ron'] : $base_tier['price_eur'];
        
        $effective_count = max(1, $count);
        
        return $base_price * $effective_count;
    }
}

PPV_Advertisers::init();
