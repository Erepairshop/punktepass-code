<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('PPV_Core')) {

class PPV_Core {

    /** ============================================================
     * 🔹 Telepítés és eseményregisztráció
     * ============================================================ */
    public static function hooks() {
        register_activation_hook(PPV_PLUGIN_FILE, [__CLASS__, 'activate']);
        register_deactivation_hook(PPV_PLUGIN_FILE, [__CLASS__, 'deactivate']);
        add_action('wp_enqueue_scripts', ['PPV_Core', 'enqueue_global_theme'], 5);
        add_action('wp', ['PPV_Core', 'init_session_bridge'], 1);
        add_action('admin_init', [__CLASS__, 'run_db_migrations'], 5);
    }

    /** ============================================================
     * 🔹 Database Migrations
     * Runs once per version to add new columns
     * ============================================================ */
    public static function run_db_migrations() {
        global $wpdb;

        $migration_version = get_option('ppv_db_migration_version', '0');

        // Migration 1.1: Add VIP bonus columns to stores
        if (version_compare($migration_version, '1.1', '<')) {
            $table = $wpdb->prefix . 'ppv_stores';

            // Check if columns exist
            $vip_col = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'vip_enabled'");

            if (empty($vip_col)) {
                $wpdb->query("ALTER TABLE {$table}
                    ADD COLUMN vip_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable VIP bonus points for this store',
                    ADD COLUMN vip_silver_bonus INT DEFAULT 5 COMMENT 'Extra points % for Silver users',
                    ADD COLUMN vip_gold_bonus INT DEFAULT 10 COMMENT 'Extra points % for Gold users',
                    ADD COLUMN vip_platinum_bonus INT DEFAULT 20 COMMENT 'Extra points % for Platinum users'
                ");
                ppv_log("✅ [PPV_Core] VIP bonus columns added to ppv_stores table");
            }

            update_option('ppv_db_migration_version', '1.1');
        }

        // Migration 1.2: Add lifetime_points column to users + Bronze VIP bonus columns
        if (version_compare($migration_version, '1.2', '<')) {
            // 1. Add lifetime_points to ppv_users
            $users_table = $wpdb->prefix . 'ppv_users';
            $lifetime_col = $wpdb->get_results("SHOW COLUMNS FROM {$users_table} LIKE 'lifetime_points'");

            if (empty($lifetime_col)) {
                $wpdb->query("ALTER TABLE {$users_table}
                    ADD COLUMN lifetime_points INT UNSIGNED DEFAULT 0 COMMENT 'Total points ever collected (never decreases)'
                ");
                ppv_log("✅ [PPV_Core] lifetime_points column added to ppv_users table");

                // Calculate lifetime_points for existing users (only positive points = earned, not redeemed)
                $wpdb->query("
                    UPDATE {$users_table} u
                    SET u.lifetime_points = (
                        SELECT COALESCE(SUM(p.points), 0)
                        FROM {$wpdb->prefix}ppv_points p
                        WHERE p.user_id = u.id AND p.points > 0
                    )
                ");
                ppv_log("✅ [PPV_Core] Calculated lifetime_points for existing users");
            }

            // 2. Add Bronze VIP bonus columns to stores (new level structure)
            $stores_table = $wpdb->prefix . 'ppv_stores';
            $bronze_col = $wpdb->get_results("SHOW COLUMNS FROM {$stores_table} LIKE 'vip_fix_bronze'");

            if (empty($bronze_col)) {
                $wpdb->query("ALTER TABLE {$stores_table}
                    ADD COLUMN vip_bronze_bonus INT DEFAULT 3 COMMENT 'Extra points % for Bronze users' AFTER vip_enabled,
                    ADD COLUMN vip_fix_bronze INT DEFAULT 1 COMMENT 'Fixed extra points for Bronze users' AFTER vip_fix_enabled,
                    ADD COLUMN vip_streak_bronze INT DEFAULT 1 COMMENT 'Streak bonus for Bronze users' AFTER vip_streak_type,
                    ADD COLUMN vip_daily_bronze INT DEFAULT 5 COMMENT 'Daily first scan bonus for Bronze users' AFTER vip_daily_enabled
                ");
                ppv_log("✅ [PPV_Core] Bronze VIP bonus columns added to ppv_stores table");
            }

            update_option('ppv_db_migration_version', '1.2');
        }

        // Migration 1.3: Add device_fingerprints table for fraud prevention
        if (version_compare($migration_version, '1.3', '<')) {
            $table_fingerprints = $wpdb->prefix . 'ppv_device_fingerprints';

            $sql = "CREATE TABLE IF NOT EXISTS {$table_fingerprints} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                fingerprint_hash VARCHAR(64) NOT NULL COMMENT 'SHA256 hash of device fingerprint',
                user_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Associated user account',
                ip_address VARCHAR(45) NULL COMMENT 'IP address at registration',
                user_agent TEXT NULL COMMENT 'User agent string',
                fingerprint_data JSON NULL COMMENT 'Raw fingerprint components for analysis',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_fingerprint (fingerprint_hash),
                KEY idx_user (user_id),
                KEY idx_created (created_at)
            ) {$wpdb->get_charset_collate()};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            ppv_log("✅ [PPV_Core] ppv_device_fingerprints table created");
            update_option('ppv_db_migration_version', '1.3');
        }

        // Migration 1.4: Add redemption_prompts table for real-time reward redemption flow
        if (version_compare($migration_version, '1.4', '<')) {
            $table_redemption_prompts = $wpdb->prefix . 'ppv_redemption_prompts';

            $sql = "CREATE TABLE IF NOT EXISTS {$table_redemption_prompts} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'User who can redeem',
                store_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Store where scan happened',
                token VARCHAR(64) NOT NULL COMMENT 'Unique security token for this prompt',
                selected_reward_id BIGINT(20) UNSIGNED NULL COMMENT 'Which reward user selected',
                available_rewards JSON NULL COMMENT 'List of available rewards at prompt time',
                status ENUM('pending', 'user_accepted', 'user_declined', 'handler_approved', 'handler_rejected', 'expired', 'completed') DEFAULT 'pending',
                user_response_at DATETIME NULL COMMENT 'When user responded',
                handler_response_at DATETIME NULL COMMENT 'When handler responded',
                rejection_reason VARCHAR(255) NULL COMMENT 'Why handler rejected',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL COMMENT 'Prompt expires after 60 seconds',
                PRIMARY KEY (id),
                UNIQUE KEY token (token),
                KEY idx_user_store (user_id, store_id),
                KEY idx_status (status),
                KEY idx_expires (expires_at)
            ) {$wpdb->get_charset_collate()};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            ppv_log("✅ [PPV_Core] ppv_redemption_prompts table created for real-time redemption flow");
            update_option('ppv_db_migration_version', '1.4');
        }

        // Migration 1.5: Add active column to rewards table if missing, then set all to active=1
        if (version_compare($migration_version, '1.5', '<')) {
            $rewards_table = $wpdb->prefix . 'ppv_rewards';

            // Check if active column exists
            $active_col = $wpdb->get_results("SHOW COLUMNS FROM {$rewards_table} LIKE 'active'");

            if (empty($active_col)) {
                // Add the active column with default value 1
                $wpdb->query("ALTER TABLE {$rewards_table} ADD COLUMN active TINYINT(1) DEFAULT 1 COMMENT 'Is reward active'");
                ppv_log("✅ [PPV_Core] Added 'active' column to ppv_rewards table");
            }

            // Set all NULL or 0 values to 1 (all existing rewards should be active)
            $updated = $wpdb->query("
                UPDATE {$rewards_table}
                SET active = 1
                WHERE active IS NULL OR active = 0
            ");

            if ($updated > 0) {
                ppv_log("✅ [PPV_Core] Fixed {$updated} rewards → set to active=1");
            }

            update_option('ppv_db_migration_version', '1.5');
        }

        // Migration 1.6: Add apple_id column to ppv_users table for Sign in with Apple
        if (version_compare($migration_version, '1.6', '<')) {
            $users_table = $wpdb->prefix . 'ppv_users';

            // Check if apple_id column exists
            $apple_col = $wpdb->get_results("SHOW COLUMNS FROM {$users_table} LIKE 'apple_id'");

            if (empty($apple_col)) {
                $wpdb->query("ALTER TABLE {$users_table} ADD COLUMN apple_id VARCHAR(255) NULL COMMENT 'Apple Sign In user ID' AFTER google_id");
                $wpdb->query("ALTER TABLE {$users_table} ADD INDEX idx_apple_id (apple_id)");
                ppv_log("✅ [PPV_Core] Added 'apple_id' column to ppv_users table");
            }

            update_option('ppv_db_migration_version', '1.6');
        }

        // Migration 1.7: Add campaign fields to ppv_rewards (unified rewards + campaigns)
        if (version_compare($migration_version, '1.7', '<')) {
            $rewards_table = $wpdb->prefix . 'ppv_rewards';

            // Check if start_date column exists
            $start_date_col = $wpdb->get_results("SHOW COLUMNS FROM {$rewards_table} LIKE 'start_date'");

            if (empty($start_date_col)) {
                $wpdb->query("ALTER TABLE {$rewards_table}
                    ADD COLUMN start_date DATE NULL COMMENT 'Campaign start date (NULL = always active)' AFTER active,
                    ADD COLUMN end_date DATE NULL COMMENT 'Campaign end date (NULL = no expiry)' AFTER start_date,
                    ADD COLUMN is_campaign TINYINT(1) DEFAULT 0 COMMENT '1 = time-limited campaign, 0 = regular reward' AFTER end_date
                ");
                ppv_log("✅ [PPV_Core] Added campaign fields (start_date, end_date, is_campaign) to ppv_rewards table");
            }

            update_option('ppv_db_migration_version', '1.7');
        }

        // Migration 1.8: Add language column to ppv_users for user language preference
        if (version_compare($migration_version, '1.8', '<')) {
            $users_table = $wpdb->prefix . 'ppv_users';

            // Check if language column exists
            $lang_col = $wpdb->get_results("SHOW COLUMNS FROM {$users_table} LIKE 'language'");

            if (empty($lang_col)) {
                $wpdb->query("ALTER TABLE {$users_table} ADD COLUMN language VARCHAR(5) DEFAULT 'de' COMMENT 'User language preference (de/hu/ro)' AFTER email");
                ppv_log("✅ [PPV_Core] Added 'language' column to ppv_users table");
            }

            update_option('ppv_db_migration_version', '1.8');
        }

        // Migration 1.9: Add scanner_id column to redemption_prompts for targeted modal display
        if (version_compare($migration_version, '1.9', '<')) {
            $prompts_table = $wpdb->prefix . 'ppv_redemption_prompts';

            // Check if scanner_id column exists
            $scanner_col = $wpdb->get_results("SHOW COLUMNS FROM {$prompts_table} LIKE 'scanner_id'");

            if (empty($scanner_col)) {
                $wpdb->query("ALTER TABLE {$prompts_table} ADD COLUMN scanner_id BIGINT(20) UNSIGNED NULL COMMENT 'Scanner device that scanned the user' AFTER store_id");
                ppv_log("✅ [PPV_Core] Added 'scanner_id' column to ppv_redemption_prompts table");
            }

            update_option('ppv_db_migration_version', '1.9');
        }
    }

    public static function init_session_bridge() {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }

        // ✅ POS / User sync
        $store = function_exists('ppv_current_store') ? ppv_current_store() : null;
        if ($store) {
            $GLOBALS['ppv_active_store'] = $store;
        }

        // ✅ User ID sync
        if (is_user_logged_in()) {
            $_SESSION['ppv_user_id'] = get_current_user_id();
        }
    }

    /** ============================================================
     * 🔹 Aktiváláskor adatbázis létrehozása
     * ============================================================ */
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 🏪 STORES tábla
        $table_stores = $wpdb->prefix . 'ppv_stores';
        $sql1 = "CREATE TABLE IF NOT EXISTS $table_stores (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            store_key VARCHAR(64) NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            address VARCHAR(255),
            plz VARCHAR(20),
            city VARCHAR(100),
            phone VARCHAR(50),
            email VARCHAR(100),
            website VARCHAR(255),
            logo VARCHAR(255),
            cover VARCHAR(255),
            facebook VARCHAR(255),
            instagram VARCHAR(255),
            tiktok VARCHAR(255),
            whatsapp VARCHAR(50),
            opening_hours LONGTEXT,
            gallery LONGTEXT,
            qr_secret VARCHAR(64),
            design_color VARCHAR(10),
            design_logo VARCHAR(255),
            trial_ends_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY store_key (store_key)
        ) $charset_collate;";

        // ⭐ POINTS tábla
        $table_points = $wpdb->prefix . 'ppv_points';
        $sql2 = "CREATE TABLE IF NOT EXISTS $table_points (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            store_id BIGINT(20) UNSIGNED NOT NULL,
            campaign_id BIGINT(20) UNSIGNED DEFAULT 0,
            points INT NOT NULL DEFAULT 1,
            created DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_store (user_id, store_id, created)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);

        // 🔄 Régi adatok migrálása
        $old_table = $wpdb->prefix . 'pp_stores';
        if ($wpdb->get_var("SHOW TABLES LIKE '$old_table'")) {
            $wpdb->query("INSERT IGNORE INTO $table_stores SELECT * FROM $old_table");
        }
    }

    /** ============================================================
     * 🔹 Deaktiválás
     * ============================================================ */
    public static function deactivate() {
        // semmit nem törlünk deaktiváláskor
    }
    
    

    /** ============================================================
     * 🔹 Globális Theme betöltés (Dark / Light)
     * ⚠️ SKIP LOGIN PAGE! (Fixed)
     * ============================================================ */
    public static function enqueue_global_theme() {
    // ⛔ LOGIN OLDAL SKIP
    if (function_exists('ppv_is_login_page') && ppv_is_login_page()) {
        return;
    }
    
    // ⛔ SIGNUP OLDAL SKIP
    if (function_exists('ppv_is_signup_page') && ppv_is_signup_page()) {
        return;
    }
    
    // ⛔ HANDLER SESSION SKIP
    if (function_exists('ppv_is_handler_session') && ppv_is_handler_session()) {
        return;
    }
        
        // 🧹 Minden korábbi PPV CSS eltávolítása (kivéve whitelistet)
        $whitelist = ['ppv-theme-light', 'ppv-login-light', 'ppv-handler-light', 'ppv-handler-dark'];
        foreach (wp_styles()->queue as $handle) {
            if (strpos($handle, 'ppv-') === 0 && !in_array($handle, $whitelist)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }

        // 🔹 ALWAYS USE LIGHT CSS (contains all dark mode styles via body.ppv-dark selectors)
        // Theme switching is handled via body class (ppv-light/ppv-dark) by theme-loader.js
        wp_register_style(
            'ppv-theme-light',
            PPV_PLUGIN_URL . 'assets/css/ppv-theme-light.css',
            [],
            PPV_VERSION
        );
        wp_enqueue_style('ppv-theme-light');

        // 🔹 Theme váltó JS (globálisan minden oldalra)
        wp_enqueue_script(
            'ppv-theme',
            PPV_PLUGIN_URL . 'assets/js/ppv-theme-handler.js',
            [],
            PPV_VERSION,
            true
        );
    }

    /** ============================================================
     * 🔹 QR Feedback sablon megjelenítés
     * ============================================================ */
    public static function render_qr_feedback($success, $store = null, $message = "") {
        $template = PPV_PLUGIN_DIR . 'templates/qr-feedback.php';
        if (file_exists($template)) {
            include $template;
            exit;
        } else {
            wp_die($success ? "✅ Punkt erfolgreich gesammelt" : "❌ " . esc_html($message));
        }
    }
}

} // class vége

/**
 * ✅ QR SCAN feldolgozás (Tages-QR + Kampagne)
 */
add_action('init', function() {
    if (
        (!isset($_GET['pp_scan']) || $_GET['pp_scan'] != 1) &&
        (!isset($_GET['pp_campaign']) || $_GET['pp_campaign'] != 1)
    ) return;

    global $wpdb;

    $store_key   = sanitize_text_field($_GET['store_key'] ?? '');
    $token       = sanitize_text_field($_GET['token'] ?? '');
    $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;

    if (empty($store_key)) {
        PPV_Core::render_qr_feedback(false, null, "Ungültiger QR-Code");
    }

    // 🔍 Store keresése
    $store = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE store_key=%s",
        $store_key
    ));
    if (!$store) PPV_Core::render_qr_feedback(false, null, "Store nicht gefunden");

    // ✅ Token ellenőrzés
    if (empty($token)) {
        PPV_Core::render_qr_feedback(false, null, "Ungültiger QR-Code (Token fehlt)");
    }

    $store_id_from_token = PPV_QR::verify_secure_qr_token($token);
    if (!$store_id_from_token || intval($store_id_from_token) !== intval($store->id)) {
        PPV_Core::render_qr_feedback(false, null, "QR-Code ungültig oder abgelaufen");
    }

    // 👤 Felhasználó azonosítása
    $user_id = $_SESSION['ppv_user_id'] ?? get_current_user_id() ?? 0;
    if (!$user_id || $user_id <= 0) {
        PPV_Core::render_qr_feedback(false, null, "Bitte zuerst einloggen oder aktiver POS-Scan verwenden");
    }

    // 🎯 Kampány ellenőrzés
    $points_to_add = 1;
    if ($campaign_id > 0) {
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_campaigns WHERE id=%d AND store_id=%d",
            $campaign_id, $store->id
        ));
        if (!$campaign) PPV_Core::render_qr_feedback(false, $store, "Kampagne nicht gefunden");

        $today = date('Y-m-d');
        if (!empty($campaign->end_date) && $campaign->end_date < $today) {
            PPV_Core::render_qr_feedback(false, $store, "Diese Kampagne ist abgelaufen");
        }

        $points_to_add = max(1, intval($campaign->threshold));
    }

    // 📅 Napi limit (1 pont/nap/store)
    $today = date('Y-m-d');
    $already = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points 
         WHERE user_id=%d AND store_id=%d AND campaign_id=%d AND DATE(created)=%s",
        $user_id, $store->id, $campaign_id, $today
    ));
    if ($already > 0) {
        PPV_Core::render_qr_feedback(false, $store, "Du hast heute schon Punkte gesammelt ✅");
    }

    // 💾 Pont beszúrás
    $wpdb->insert("{$wpdb->prefix}ppv_points", [
        'user_id'     => $user_id,
        'store_id'    => $store->id,
        'campaign_id' => $campaign_id,
        'points'      => $points_to_add,
        'created'     => current_time('mysql')
    ]);

    $msg = $campaign_id > 0
        ? "✅ Du hast {$points_to_add} Punkte aus der Kampagne gesammelt!"
        : "✅ Du hast 1 Punkt gesammelt!";

    PPV_Core::render_qr_feedback(true, $store, $msg);
});