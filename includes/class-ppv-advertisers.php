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
 * Tier (Carei MVP):
 * - basic 5 EUR/hó: 1 ad slot, 1 push/hét (4/hó), térkép pin
 * - plus 9 EUR/hó:  3 ad slot, 8 push/hó, statisztika
 * - pro 19 EUR/hó:  10 ad slot, 16 push/hó (max 1/nap user-szintű global cap), featured pin
 */

if (!defined('ABSPATH')) exit;

class PPV_Advertisers {

    const TIER_BASIC = 'basic';
    const TIER_PLUS  = 'plus';
    const TIER_PRO   = 'pro';

    const TIERS = [
        self::TIER_BASIC => ['price_eur' => 5,  'ads' => 1,  'push_per_month' => 4,  'featured' => 0],
        self::TIER_PLUS  => ['price_eur' => 9,  'ads' => 3,  'push_per_month' => 8,  'featured' => 0],
        self::TIER_PRO   => ['price_eur' => 19, 'ads' => 10, 'push_per_month' => 16, 'featured' => 1],
    ];

    public static function init() {
        add_action('init', [__CLASS__, 'register_routes']);
        add_action('admin_post_ppv_advertiser_register', [__CLASS__, 'handle_register']);
        add_action('admin_post_nopriv_ppv_advertiser_register', [__CLASS__, 'handle_register']);
        add_action('admin_post_ppv_advertiser_login', [__CLASS__, 'handle_login']);
        add_action('admin_post_nopriv_ppv_advertiser_login', [__CLASS__, 'handle_login']);
        add_action('admin_post_ppv_advertiser_save_profile', [__CLASS__, 'handle_save_profile']);
        add_action('admin_post_ppv_advertiser_save_ad', [__CLASS__, 'handle_save_ad']);
        add_action('admin_post_ppv_advertiser_send_push', [__CLASS__, 'handle_send_push']);
        add_action('admin_post_nopriv_ppv_advertiser_follow', [__CLASS__, 'handle_follow']);
        add_action('admin_post_ppv_advertiser_follow', [__CLASS__, 'handle_follow']);
        add_action('template_redirect', [__CLASS__, 'route_dispatcher']);
    }

    /** ============================================================
     * MIGRATIONS — hívva PPV_Core::run_db_migrations()-ből
     * ============================================================ */
    public static function run_migrations() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $advertisers = $wpdb->prefix . 'ppv_advertisers';
        $sql1 = "CREATE TABLE IF NOT EXISTS {$advertisers} (
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

        $ads = $wpdb->prefix . 'ppv_ads';
        $sql2 = "CREATE TABLE IF NOT EXISTS {$ads} (
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

        switch ($route) {
            case 'register':       self::render_register(); break;
            case 'login':          self::render_login(); break;
            case 'logout':         self::do_logout(); break;
            case 'admin':          self::render_admin_dashboard(); break;
            case 'admin_profile':  self::render_admin_profile(); break;
            case 'admin_ads':      self::render_admin_ads(); break;
            case 'admin_push':     self::render_admin_push(); break;
            case 'admin_stats':    self::render_admin_stats(); break;
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
        wp_redirect(home_url('/business/login'));
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
        $name  = sanitize_text_field($_POST['business_name'] ?? '');
        $pass  = $_POST['password'] ?? '';
        if (!is_email($email) || strlen($name) < 2 || strlen($pass) < 6) {
            wp_die(esc_html__('Hiányzó adatok.', 'punktepass'));
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_advertisers WHERE owner_email = %s", $email
        ));
        if ($exists) wp_die(esc_html__('Ez az email már regisztrálva van.', 'punktepass'));

        $slug = sanitize_title($name) . '-' . substr(md5($email . microtime()), 0, 6);

        $wpdb->insert($wpdb->prefix . 'ppv_advertisers', [
            'slug' => $slug,
            'business_name' => $name,
            'owner_email' => $email,
            'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
            'tier' => self::TIER_BASIC,
            'subscription_status' => 'trial',
            'subscription_until' => date('Y-m-d', strtotime('+30 days')),
            'push_month_reset_at' => date('Y-m-d', strtotime('first day of next month')),
        ]);
        $id = $wpdb->insert_id;

        self::start_session();
        $_SESSION['ppv_advertiser_id'] = $id;
        wp_redirect(home_url('/business/admin/profile?welcome=1'));
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
        $adv_id = self::current_advertiser_id();
        if (!$adv_id) wp_die('Auth required.');

        global $wpdb;
        $data = [
            'business_name' => sanitize_text_field($_POST['business_name'] ?? ''),
            'phone'    => sanitize_text_field($_POST['phone'] ?? ''),
            'whatsapp' => sanitize_text_field($_POST['whatsapp'] ?? ''),
            'website'  => esc_url_raw($_POST['website'] ?? ''),
            'address'  => sanitize_text_field($_POST['address'] ?? ''),
            'city'     => sanitize_text_field($_POST['city'] ?? ''),
            'country'  => sanitize_text_field($_POST['country'] ?? ''),
            'postcode' => sanitize_text_field($_POST['postcode'] ?? ''),
            'lat'      => is_numeric($_POST['lat'] ?? null) ? floatval($_POST['lat']) : null,
            'lng'      => is_numeric($_POST['lng'] ?? null) ? floatval($_POST['lng']) : null,
            'category' => sanitize_text_field($_POST['category'] ?? 'other'),
            'logo_url' => esc_url_raw($_POST['logo_url'] ?? ''),
            'cover_url'=> esc_url_raw($_POST['cover_url'] ?? ''),
            'description_de' => wp_kses_post($_POST['description_de'] ?? ''),
            'description_hu' => wp_kses_post($_POST['description_hu'] ?? ''),
            'description_ro' => wp_kses_post($_POST['description_ro'] ?? ''),
            'description_en' => wp_kses_post($_POST['description_en'] ?? ''),
        ];
        $wpdb->update($wpdb->prefix . 'ppv_advertisers', $data, ['id' => $adv_id]);
        wp_redirect(home_url('/business/admin/profile?saved=1'));
        exit;
    }

    public static function handle_save_ad() {
        check_admin_referer('ppv_advertiser_save_ad');
        $adv_id = self::current_advertiser_id();
        if (!$adv_id) wp_die('Auth required.');

        global $wpdb;
        $ad_id = intval($_POST['ad_id'] ?? 0);
        $data = [
            'advertiser_id' => $adv_id,
            'title_de' => sanitize_text_field($_POST['title_de'] ?? ''),
            'title_hu' => sanitize_text_field($_POST['title_hu'] ?? ''),
            'title_ro' => sanitize_text_field($_POST['title_ro'] ?? ''),
            'title_en' => sanitize_text_field($_POST['title_en'] ?? ''),
            'body_de'  => wp_kses_post($_POST['body_de'] ?? ''),
            'body_hu'  => wp_kses_post($_POST['body_hu'] ?? ''),
            'body_ro'  => wp_kses_post($_POST['body_ro'] ?? ''),
            'body_en'  => wp_kses_post($_POST['body_en'] ?? ''),
            'image_url'=> esc_url_raw($_POST['image_url'] ?? ''),
            'cta_url'  => esc_url_raw($_POST['cta_url'] ?? ''),
            'valid_from' => sanitize_text_field($_POST['valid_from'] ?? '') ?: null,
            'valid_to'   => sanitize_text_field($_POST['valid_to'] ?? '') ?: null,
            'followers_only' => !empty($_POST['followers_only']) ? 1 : 0,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        ];
        if ($ad_id > 0) {
            // Verify ownership
            $owner = $wpdb->get_var($wpdb->prepare(
                "SELECT advertiser_id FROM {$wpdb->prefix}ppv_ads WHERE id = %d", $ad_id
            ));
            if ((int)$owner !== $adv_id) wp_die('Not your ad.');
            $wpdb->update($wpdb->prefix . 'ppv_ads', $data, ['id' => $ad_id]);
        } else {
            // Tier ad limit
            $current_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_ads WHERE advertiser_id = %d AND is_active = 1", $adv_id
            ));
            $tier = $wpdb->get_var($wpdb->prepare("SELECT tier FROM {$wpdb->prefix}ppv_advertisers WHERE id = %d", $adv_id));
            $max = self::TIERS[$tier]['ads'] ?? 1;
            if ($current_count >= $max) {
                wp_redirect(home_url('/business/admin/ads?err=tier_limit'));
                exit;
            }
            $wpdb->insert($wpdb->prefix . 'ppv_ads', $data);
        }
        wp_redirect(home_url('/business/admin/ads?saved=1'));
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
        $sent = 0;
        if (!empty($followers) && class_exists('PPV_Push')) {
            foreach ($followers as $uid) {
                $result = PPV_Push::send_to_user((int)$uid, [
                    'title' => $title,
                    'body'  => $body,
                    'data'  => ['type' => 'advertiser', 'advertiser_id' => $adv_id, 'slug' => $adv->slug],
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

    public static function is_following($user_id, $advertiser_id) {
        global $wpdb;
        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_advertiser_followers WHERE user_id = %d AND advertiser_id = %d",
            $user_id, $advertiser_id
        ));
    }
}

PPV_Advertisers::init();
