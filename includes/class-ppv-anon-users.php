<?php
/**
 * Anonymous user creation + identity continuity.
 *
 * Solves: vendor scans QR -> follows shop / receives push without registering.
 * Implementation:
 *   - On first hit, generate anon_id, create row in wp_ppv_anon_users.
 *   - Persist via 1-year cookie ppv_anon_id (HttpOnly, SameSite=Lax).
 *   - Bridge into $_SESSION['ppv_user_id'] as a NEGATIVE int (-anon_id) so
 *     existing code paths see it as a logged-in user, but downstream tables
 *     using user_id columns remain compatible because the magnitude space is
 *     disjoint from real wp_users IDs (which are always positive).
 *   - Anon -> named upgrade later: if user later supplies email + magic-link
 *     verifies, we copy followers/push tokens onto the new wp_users row and
 *     drop the anon row. Done by self::upgrade_to_named().
 */

if (!defined('ABSPATH')) exit;

class PPV_Anon_Users {

    const COOKIE_NAME = 'ppv_anon_id';
    const COOKIE_LIFETIME = 31536000; // 1 year
    const TABLE = 'ppv_anon_users';

    public static function hooks() {
        add_action('init', [__CLASS__, 'maybe_resume_anon'], 5);
    }

    /** Schema bootstrap — called from main plugin install hook. */
    public static function install_table() {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(64) NOT NULL,
            language VARCHAR(8) DEFAULT 'de',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            user_agent VARCHAR(255) DEFAULT NULL,
            upgraded_to_user_id BIGINT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid_idx (uuid),
            KEY upgraded_idx (upgraded_to_user_id)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /** Read cookie at request start; if valid, populate session. */
    public static function maybe_resume_anon() {
        if (!empty($_SESSION['ppv_user_id'])) return; // real or anon user already in session
        if (empty($_COOKIE[self::COOKIE_NAME])) return;

        global $wpdb;
        $uuid = sanitize_text_field($_COOKIE[self::COOKIE_NAME]);
        if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) return;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, language, upgraded_to_user_id
             FROM {$wpdb->prefix}" . self::TABLE . "
             WHERE uuid = %s LIMIT 1",
            $uuid
        ));
        if (!$row) return;

        // If anon already upgraded to named user, set the named id instead.
        if (!empty($row->upgraded_to_user_id)) {
            if (function_exists('ppv_maybe_start_session')) ppv_maybe_start_session();
            $_SESSION['ppv_user_id'] = (int) $row->upgraded_to_user_id;
        } else {
            if (function_exists('ppv_maybe_start_session')) ppv_maybe_start_session();
            $_SESSION['ppv_user_id'] = -((int) $row->id); // negative => anon marker
            $_SESSION['ppv_is_anon'] = true;
        }

        $wpdb->update(
            $wpdb->prefix . self::TABLE,
            ['last_seen' => current_time('mysql')],
            ['id' => $row->id]
        );
    }

    /**
     * Returns the current actor id (real user_id positive, anon_id negative,
     * or 0 if anonymous and we have not created a row yet).
     */
    public static function current_id() {
        return !empty($_SESSION['ppv_user_id']) ? (int) $_SESSION['ppv_user_id'] : 0;
    }

    /** Convert -123 -> 123 for storage in user_id columns when caller wants it. */
    public static function abs_id() {
        $id = self::current_id();
        return $id < 0 ? -$id : $id;
    }

    /**
     * Returns existing actor id, OR creates an anon row + cookie + session and
     * returns the negative id. Idempotent.
     */
    public static function get_or_create() {
        $id = self::current_id();
        if ($id !== 0) return $id;

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $uuid  = bin2hex(random_bytes(16));
        $lang  = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de';
        $ua    = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 255) : null;

        $wpdb->insert($table, [
            'uuid'       => $uuid,
            'language'   => $lang,
            'created_at' => current_time('mysql'),
            'last_seen'  => current_time('mysql'),
            'user_agent' => $ua,
        ]);
        $anon_id = (int) $wpdb->insert_id;
        if ($anon_id <= 0) return 0;

        // Set persistent cookie
        if (!headers_sent()) {
            $params = function_exists('ppv_get_session_cookie_params')
                ? ppv_get_session_cookie_params()
                : ['path' => '/', 'domain' => '', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'];
            setcookie(self::COOKIE_NAME, $uuid, [
                'expires'  => time() + self::COOKIE_LIFETIME,
                'path'     => $params['path'] ?? '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => $params['secure'] ?? is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[self::COOKIE_NAME] = $uuid;
        }

        if (function_exists('ppv_maybe_start_session')) ppv_maybe_start_session();
        $_SESSION['ppv_user_id'] = -$anon_id;
        $_SESSION['ppv_is_anon'] = true;

        return -$anon_id;
    }

    /**
     * Promote the current anon to a real wp_users row. Called from the
     * email-magic-link verification flow. Returns true if successful.
     *
     * Hooks into existing followers / push token tables: rows referencing the
     * anon_id (stored as e.g. -123 in user_id columns) are NOT rewritten —
     * instead, callers should consult upgraded_to_user_id when looking up by
     * old anon id. (This avoids mass UPDATEs on possibly large tables.)
     *
     * In practice we pre-create the named wp_users row first, then attach the
     * negative id mapping here.
     */
    public static function upgrade_to_named($named_user_id) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $current = self::current_id();
        if ($current >= 0) return false; // not anon
        $anon_row_id = -$current;

        $wpdb->update(
            $table,
            ['upgraded_to_user_id' => (int) $named_user_id],
            ['id' => $anon_row_id]
        );

        // Remap follower/push rows from negative anon_id -> positive named id.
        // These tables currently only see positive user_ids in production; we
        // are the ones writing negative ids during the anon phase, so rewriting
        // them is safe.
        $tables_to_remap = [
            $wpdb->prefix . 'ppv_advertiser_followers',
            $wpdb->prefix . 'ppv_push_subscriptions',
        ];
        foreach ($tables_to_remap as $t) {
            // Skip silently if table missing
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
            if (!$exists) continue;
            $wpdb->query($wpdb->prepare(
                "UPDATE {$t} SET user_id = %d WHERE user_id = %d",
                (int) $named_user_id, $current
            ));
        }

        $_SESSION['ppv_user_id'] = (int) $named_user_id;
        unset($_SESSION['ppv_is_anon']);
        return true;
    }
}
PPV_Anon_Users::hooks();
