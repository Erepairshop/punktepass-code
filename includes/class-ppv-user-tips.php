<?php
/**
 * PunktePass – User Tips System
 * Személyre szabott tippek megjelenítése felhasználóknak
 *
 * - Scan alapú triggerelés
 * - Időzített megjelenés
 * - Egyszer látható tippek
 * - Csak akkor jelenik meg, ha releváns (nincs kitöltve az adat)
 *
 * Version: 1.0
 * Author: PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_User_Tips {

    /**
     * Hooks inicializálása
     */
    public static function hooks() {
        // Database migration
        add_action('init', [__CLASS__, 'maybe_run_migration']);

        // REST API endpoints
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);

        // Hook into scan completion to check tips
        add_action('ppv_after_scan', [__CLASS__, 'on_user_scan'], 10, 2);
    }

    /** ============================================================
     *  🗄️ DATABASE MIGRATION
     * ============================================================ */
    public static function maybe_run_migration() {
        $version = get_option('ppv_user_tips_version', '0');

        if (version_compare($version, '1.0', '<')) {
            self::run_migration_v1();
            update_option('ppv_user_tips_version', '1.0');
        }
    }

    private static function run_migration_v1() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'ppv_user_tips';

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            tip_key VARCHAR(50) NOT NULL,
            triggered_at DATETIME DEFAULT NULL COMMENT 'When tip was triggered (scan threshold met)',
            shown_at DATETIME DEFAULT NULL COMMENT 'When tip was actually shown',
            dismissed_at DATETIME DEFAULT NULL COMMENT 'When user dismissed the tip',
            clicked_at DATETIME DEFAULT NULL COMMENT 'When user clicked/acted on tip',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY user_tip (user_id, tip_key),
            KEY user_id (user_id),
            KEY tip_key (tip_key)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        ppv_log("✅ [PPV_User_Tips] Created ppv_user_tips table");
    }

    /** ============================================================
     *  📋 TIPS CONFIGURATION
     * ============================================================ */
    public static function get_all_tips() {
        return [
            'complete_name' => [
                'trigger_scans' => 3,           // After 3 scans
                'delay_minutes' => 60,          // Wait 1 hour after trigger
                'check_field' => 'name',        // Only show if name is empty
                'priority' => 10,
                'icon' => '👤',
                'action_url' => '/einstellungen',
                'translations' => [
                    'de' => [
                        'title' => 'Vervollständige dein Profil',
                        'message' => 'Füge deinen Namen hinzu, damit Händler dich persönlich begrüßen können!',
                        'button' => 'Name hinzufügen',
                    ],
                    'hu' => [
                        'title' => 'Egészítsd ki a profilod',
                        'message' => 'Add meg a neved, hogy a kereskedők személyesen üdvözölhessenek!',
                        'button' => 'Név megadása',
                    ],
                    'ro' => [
                        'title' => 'Completează-ți profilul',
                        'message' => 'Adaugă numele tău pentru ca comercianții să te poată saluta personal!',
                        'button' => 'Adaugă nume',
                    ],
                ],
            ],

            'add_address' => [
                'trigger_scans' => 1,           // After first scan
                'delay_minutes' => 60,          // Wait 1 hour after trigger
                'check_field' => null,          // Use custom condition instead
                'check_condition' => 'no_location_data', // Check if zip+city are missing
                'priority' => 15,
                'icon' => '📍',
                'action_url' => '/einstellungen',
                'translations' => [
                    'de' => [
                        'title' => 'Finde Geschäfte in deiner Nähe',
                        'message' => 'Füge deine Adresse hinzu und sieh sofort, welche Geschäfte mit Punktesammlung in deiner Nähe sind – auch ohne GPS!',
                        'button' => 'Adresse hinzufügen',
                    ],
                    'hu' => [
                        'title' => 'Találd meg a közeli üzleteket',
                        'message' => 'Add meg a címedet és azonnal láthatod, mely üzletek vannak a közeledben pontgyűjtéssel – GPS nélkül is!',
                        'button' => 'Cím megadása',
                    ],
                    'ro' => [
                        'title' => 'Găsește magazine în apropiere',
                        'message' => 'Adaugă adresa ta și vezi imediat ce magazine cu colectare de puncte sunt în apropierea ta – chiar și fără GPS!',
                        'button' => 'Adaugă adresa',
                    ],
                ],
            ],

            'set_birthday' => [
                'trigger_scans' => 5,
                'delay_minutes' => 120,         // 2 hours after trigger
                'check_field' => 'birthday',
                'priority' => 20,
                'icon' => '🎂',
                'action_url' => '/einstellungen',
                'translations' => [
                    'de' => [
                        'title' => 'Geburtstags-Bonus aktivieren',
                        'message' => 'Trage dein Geburtsdatum ein und erhalte jedes Jahr einen speziellen Bonus von teilnehmenden Händlern!',
                        'button' => 'Geburtstag eintragen',
                    ],
                    'hu' => [
                        'title' => 'Születésnapi bónusz aktiválása',
                        'message' => 'Add meg a születésnapodat és minden évben különleges bónuszt kapsz a résztvevő kereskedőktől!',
                        'button' => 'Születésnap megadása',
                    ],
                    'ro' => [
                        'title' => 'Activează bonusul de ziua de naștere',
                        'message' => 'Introdu data nașterii și primești un bonus special în fiecare an de la comercianții participanți!',
                        'button' => 'Adaugă ziua de naștere',
                    ],
                ],
            ],

            'add_whatsapp' => [
                'trigger_scans' => 8,
                'delay_minutes' => 180,         // 3 hours after trigger
                'check_field' => 'whatsapp',
                'priority' => 30,
                'icon' => '📱',
                'action_url' => '/einstellungen',
                'translations' => [
                    'de' => [
                        'title' => 'WhatsApp verbinden',
                        'message' => 'Erhalte Benachrichtigungen über neue Belohnungen und exklusive Angebote direkt auf WhatsApp!',
                        'button' => 'WhatsApp hinzufügen',
                    ],
                    'hu' => [
                        'title' => 'WhatsApp összekapcsolása',
                        'message' => 'Kapj értesítéseket az új jutalmakról és exkluzív ajánlatokról közvetlenül WhatsApp-on!',
                        'button' => 'WhatsApp megadása',
                    ],
                    'ro' => [
                        'title' => 'Conectează WhatsApp',
                        'message' => 'Primește notificări despre recompense noi și oferte exclusive direct pe WhatsApp!',
                        'button' => 'Adaugă WhatsApp',
                    ],
                ],
            ],

            'enable_notifications' => [
                'trigger_scans' => 10,
                'delay_minutes' => 240,         // 4 hours after trigger
                'check_field' => 'push_enabled',
                'priority' => 40,
                'icon' => '🔔',
                'action_url' => '/einstellungen#notifications',
                'translations' => [
                    'de' => [
                        'title' => 'Benachrichtigungen aktivieren',
                        'message' => 'Verpasse keine Belohnungen! Aktiviere Push-Benachrichtigungen für Updates zu deinen Punkten.',
                        'button' => 'Aktivieren',
                    ],
                    'hu' => [
                        'title' => 'Értesítések bekapcsolása',
                        'message' => 'Ne maradj le a jutalmakról! Kapcsold be az értesítéseket a pontjaidról.',
                        'button' => 'Bekapcsolás',
                    ],
                    'ro' => [
                        'title' => 'Activează notificările',
                        'message' => 'Nu rata recompensele! Activează notificările pentru actualizări despre punctele tale.',
                        'button' => 'Activează',
                    ],
                ],
            ],

            'first_reward_hint' => [
                'trigger_scans' => 15,
                'delay_minutes' => 60,
                'check_field' => null,          // Always show (once)
                'check_condition' => 'no_rewards_redeemed',
                'priority' => 50,
                'icon' => '🎁',
                'action_url' => '/belohnungen',
                'translations' => [
                    'de' => [
                        'title' => 'Zeit für deine erste Belohnung!',
                        'message' => 'Du hast schon genug Punkte gesammelt. Schau dir an, welche Belohnungen auf dich warten!',
                        'button' => 'Belohnungen ansehen',
                    ],
                    'hu' => [
                        'title' => 'Ideje az első jutalmadnak!',
                        'message' => 'Már elég pontot gyűjtöttél. Nézd meg, milyen jutalmak várnak rád!',
                        'button' => 'Jutalmak megtekintése',
                    ],
                    'ro' => [
                        'title' => 'E timpul pentru prima ta recompensă!',
                        'message' => 'Ai adunat suficiente puncte. Vezi ce recompense te așteaptă!',
                        'button' => 'Vezi recompensele',
                    ],
                ],
            ],

            'rate_app' => [
                'trigger_scans' => 20,
                'delay_minutes' => 1440,        // 24 hours after trigger
                'check_field' => null,
                'priority' => 100,
                'icon' => '⭐',
                'action_url' => 'rate_app',     // Special action
                'translations' => [
                    'de' => [
                        'title' => 'Gefällt dir PunktePass?',
                        'message' => 'Wir freuen uns über deine Bewertung! Hilf anderen, PunktePass zu entdecken.',
                        'button' => 'App bewerten',
                    ],
                    'hu' => [
                        'title' => 'Tetszik a PunktePass?',
                        'message' => 'Örülnénk az értékelésednek! Segíts másoknak felfedezni a PunktePass-t.',
                        'button' => 'App értékelése',
                    ],
                    'ro' => [
                        'title' => 'Îți place PunktePass?',
                        'message' => 'Ne-ar bucura recenzia ta! Ajută-i pe alții să descopere PunktePass.',
                        'button' => 'Evaluează aplicația',
                    ],
                ],
            ],

            // Event-based tip (triggered externally, not by scan count)
            'new_store_nearby' => [
                'trigger_scans' => 0,           // Not scan-based
                'delay_minutes' => 60,          // 1 hour delay after trigger
                'check_field' => null,
                'priority' => 5,                // High priority
                'icon' => '🏪',
                'action_url' => '/shops',
                'is_event_based' => true,       // Special flag
                'translations' => [
                    'de' => [
                        'title' => 'Neuer Shop in deiner Nähe!',
                        'message' => 'Ein neues Geschäft ist jetzt bei PunktePass dabei. Entdecke es und sammle Punkte!',
                        'button' => 'Jetzt entdecken',
                    ],
                    'hu' => [
                        'title' => 'Új üzlet a közeledben!',
                        'message' => 'Egy új üzlet csatlakozott a PunktePass-hoz. Fedezd fel és gyűjts pontokat!',
                        'button' => 'Felfedezem',
                    ],
                    'ro' => [
                        'title' => 'Magazin nou în apropiere!',
                        'message' => 'Un nou magazin s-a alăturat PunktePass. Descoperă-l și colectează puncte!',
                        'button' => 'Descoperă acum',
                    ],
                ],
            ],
        ];
    }

    /**
     * Trigger "new store nearby" tip for users near a location
     * Called when a new store is created/activated
     *
     * @param int $store_id The new store ID
     * @param float $lat Store latitude
     * @param float $lng Store longitude
     * @param float $radius_km Radius in kilometers (default 20km)
     */
    public static function trigger_new_store_nearby($store_id, $lat, $lng, $radius_km = 20) {
        global $wpdb;

        if (!$lat || !$lng) {
            ppv_log("⚠️ [PPV_User_Tips] Cannot trigger new_store_nearby - no coordinates for store {$store_id}");
            return 0;
        }

        // Get store info for logging and creation date
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT name, company_name, city, created_at FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));
        $store_name = $store->name ?: $store->company_name ?: "Store #{$store_id}";
        $store_created = $store->created_at ?: current_time('mysql');

        // Find users within radius using GPS coordinates OR address coordinates
        // Using Haversine formula approximation
        $users = $wpdb->get_results($wpdb->prepare("
            SELECT id, last_lat, last_lng, address_lat, address_lng
            FROM {$wpdb->prefix}ppv_users
            WHERE created_at < %s
            AND (
                -- Users with GPS coordinates
                (last_lat IS NOT NULL AND last_lng IS NOT NULL AND (
                    6371 * acos(
                        cos(radians(%f)) * cos(radians(last_lat)) *
                        cos(radians(last_lng) - radians(%f)) +
                        sin(radians(%f)) * sin(radians(last_lat))
                    )
                ) <= %f)
                OR
                -- Users with address-based coordinates (no GPS)
                (last_lat IS NULL AND address_lat IS NOT NULL AND address_lng IS NOT NULL AND (
                    6371 * acos(
                        cos(radians(%f)) * cos(radians(address_lat)) *
                        cos(radians(address_lng) - radians(%f)) +
                        sin(radians(%f)) * sin(radians(address_lat))
                    )
                ) <= %f)
            )
        ", $store_created, $lat, $lng, $lat, $radius_km, $lat, $lng, $lat, $radius_km));

        $triggered_count = 0;

        foreach ($users as $user) {
            // Check if user already has this tip for this store
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_user_tips
                 WHERE user_id = %d AND tip_key = %s",
                $user->id, 'new_store_nearby'
            ));

            if (!$existing) {
                $wpdb->insert(
                    $wpdb->prefix . 'ppv_user_tips',
                    [
                        'user_id' => $user->id,
                        'tip_key' => 'new_store_nearby',
                        'triggered_at' => current_time('mysql'),
                    ],
                    ['%d', '%s', '%s']
                );
                $triggered_count++;
            }
        }

        ppv_log("📍 [PPV_User_Tips] New store nearby tip triggered: store={$store_name}, users={$triggered_count}, radius={$radius_km}km");

        return $triggered_count;
    }

    /** ============================================================
     *  📡 REST API ROUTES
     * ============================================================ */
    public static function register_rest_routes() {
        // Get pending tips for user
        register_rest_route('ppv/v1', '/tips/pending', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_pending_tips'],
            'permission_callback' => ['PPV_Permissions', 'check_logged_in_user']
        ]);

        // Mark tip as shown
        register_rest_route('ppv/v1', '/tips/(?P<tip_key>[a-z_]+)/shown', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_mark_shown'],
            'permission_callback' => ['PPV_Permissions', 'check_logged_in_user']
        ]);

        // Mark tip as dismissed
        register_rest_route('ppv/v1', '/tips/(?P<tip_key>[a-z_]+)/dismiss', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_dismiss_tip'],
            'permission_callback' => ['PPV_Permissions', 'check_logged_in_user']
        ]);

        // Mark tip as clicked (user took action)
        register_rest_route('ppv/v1', '/tips/(?P<tip_key>[a-z_]+)/clicked', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_mark_clicked'],
            'permission_callback' => ['PPV_Permissions', 'check_logged_in_user']
        ]);

        // Admin: Test trigger tip for a user
        register_rest_route('ppv/v1', '/admin/tips/test', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'admin_test_trigger_tip'],
            'permission_callback' => [__CLASS__, 'check_admin_permission']
        ]);
    }

    /**
     * Check admin permission for test endpoints
     */
    public static function check_admin_permission() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        return !empty($_SESSION['ppv_admin_logged_in']);
    }

    /**
     * Admin: Test trigger a tip for a user
     * POST /wp-json/ppv/v1/admin/tips/test
     * Body: { "user_id": 10, "tip_key": "complete_name", "clear_field": true }
     */
    public static function admin_test_trigger_tip(WP_REST_Request $request) {
        global $wpdb;

        $user_id = intval($request->get_param('user_id'));
        $tip_key = sanitize_text_field($request->get_param('tip_key') ?? 'complete_name');
        $clear_field = (bool) $request->get_param('clear_field');

        if (!$user_id) {
            return new WP_REST_Response(['success' => false, 'msg' => 'user_id required'], 400);
        }

        // Check if tip exists in config
        $all_tips = self::get_all_tips();
        if (!isset($all_tips[$tip_key])) {
            return new WP_REST_Response([
                'success' => false,
                'msg' => 'Invalid tip_key',
                'available' => array_keys($all_tips)
            ], 400);
        }

        // Optionally clear the check_field for testing
        $field_cleared = null;
        if ($clear_field && !empty($all_tips[$tip_key]['check_field'])) {
            $field = $all_tips[$tip_key]['check_field'];
            $field_map = [
                'name' => 'display_name',
                'birthday' => 'birthday',
                'whatsapp' => 'whatsapp',
                'push_enabled' => 'push_notifications'
            ];
            $db_field = $field_map[$field] ?? $field;

            $wpdb->update(
                $wpdb->prefix . 'ppv_users',
                [$db_field => null],
                ['id' => $user_id],
                ['%s'],
                ['%d']
            );

            // Also clear first_name/last_name if clearing display_name (to avoid fallback)
            if ($db_field === 'display_name') {
                $wpdb->update(
                    $wpdb->prefix . 'ppv_users',
                    ['first_name' => null, 'last_name' => null],
                    ['id' => $user_id],
                    ['%s', '%s'],
                    ['%d']
                );
            }

            $field_cleared = $db_field;
            ppv_log("🧹 [PPV_User_Tips] Cleared field '{$db_field}' for user {$user_id}");
        }

        // Delete existing tip for this user (for re-testing)
        $wpdb->delete(
            $wpdb->prefix . 'ppv_user_tips',
            ['user_id' => $user_id, 'tip_key' => $tip_key],
            ['%d', '%s']
        );

        // Insert new tip with triggered_at in the past (so delay is already passed)
        $delay = $all_tips[$tip_key]['delay_minutes'] ?? 60;
        $triggered_at = date('Y-m-d H:i:s', strtotime("-{$delay} minutes -1 minute"));

        $wpdb->insert(
            $wpdb->prefix . 'ppv_user_tips',
            [
                'user_id' => $user_id,
                'tip_key' => $tip_key,
                'triggered_at' => $triggered_at,
            ],
            ['%d', '%s', '%s']
        );

        ppv_log("🧪 [PPV_User_Tips] Test tip '{$tip_key}' triggered for user {$user_id}");

        return new WP_REST_Response([
            'success' => true,
            'msg' => "Tip '{$tip_key}' triggered for user {$user_id}",
            'triggered_at' => $triggered_at,
            'field_cleared' => $field_cleared
        ], 200);
    }

    /** ============================================================
     *  🔔 ON USER SCAN - Check and trigger tips
     * ============================================================ */
    public static function on_user_scan($user_id, $scan_data) {
        global $wpdb;

        if (!$user_id) return;

        // Get user's total scan count
        $scan_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points WHERE user_id = %d",
            $user_id
        ));

        $tips = self::get_all_tips();

        foreach ($tips as $tip_key => $tip) {
            // Skip event-based tips (triggered externally, not by scans)
            if (!empty($tip['is_event_based'])) {
                continue;
            }

            // Check if scan threshold is met
            if ($scan_count >= $tip['trigger_scans']) {
                // Check if tip already triggered
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ppv_user_tips WHERE user_id = %d AND tip_key = %s",
                    $user_id, $tip_key
                ));

                if (!$existing) {
                    // Trigger the tip (will be shown after delay)
                    $wpdb->insert(
                        $wpdb->prefix . 'ppv_user_tips',
                        [
                            'user_id' => $user_id,
                            'tip_key' => $tip_key,
                            'triggered_at' => current_time('mysql'),
                        ],
                        ['%d', '%s', '%s']
                    );

                    ppv_log("💡 [PPV_User_Tips] Triggered tip '{$tip_key}' for user {$user_id} (scan #{$scan_count})");
                }
            }
        }
    }

    /** ============================================================
     *  📡 REST: Get Pending Tips
     * ============================================================ */
    public static function rest_get_pending_tips(WP_REST_Request $request) {
        global $wpdb;

        try {
            $user_id = self::get_user_id();
            if (!$user_id) {
                return new WP_REST_Response(['success' => false, 'msg' => 'Not authenticated'], 401);
            }

            // Get language
            $lang = sanitize_text_field($request->get_param('lang') ?? 'de');
            if (!in_array($lang, ['de', 'hu', 'ro'])) {
                $lang = 'de';
            }

            // Get user data for field checks
            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ppv_users WHERE id = %d",
                $user_id
            ));

            if (!$user) {
                return new WP_REST_Response(['success' => false, 'msg' => 'User not found'], 404);
            }

            // Get triggered tips that haven't been dismissed
            $triggered_tips = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ppv_user_tips
                 WHERE user_id = %d
                 AND triggered_at IS NOT NULL
                 AND dismissed_at IS NULL
                 AND shown_at IS NULL",
                $user_id
            ));

            $all_tips = self::get_all_tips();
            $pending_tips = [];
            $now = current_time('timestamp');

            foreach ($triggered_tips as $triggered) {
                $tip_key = $triggered->tip_key;

                if (!isset($all_tips[$tip_key])) continue;

                $tip_config = $all_tips[$tip_key];

                // Check delay time
                $triggered_time = strtotime($triggered->triggered_at);
                $delay_seconds = $tip_config['delay_minutes'] * 60;

                if (($now - $triggered_time) < $delay_seconds) {
                    // Not enough time has passed
                    continue;
                }

                // Check if field is already filled
                if (!empty($tip_config['check_field'])) {
                    $field = $tip_config['check_field'];
                    if (!empty($user->$field)) {
                        // Field is already filled, dismiss tip automatically
                        $wpdb->update(
                            $wpdb->prefix . 'ppv_user_tips',
                            ['dismissed_at' => current_time('mysql')],
                            ['id' => $triggered->id],
                            ['%s'],
                            ['%d']
                        );
                        continue;
                    }
                }

                // Check special conditions
                if (!empty($tip_config['check_condition'])) {
                    if (!self::check_condition($tip_config['check_condition'], $user_id)) {
                        continue;
                    }
                }

                // Tip is ready to show
                $trans = $tip_config['translations'][$lang] ?? $tip_config['translations']['de'];

                $pending_tips[] = [
                    'key' => $tip_key,
                    'icon' => $tip_config['icon'],
                    'title' => $trans['title'],
                    'message' => $trans['message'],
                    'button' => $trans['button'],
                    'action_url' => $tip_config['action_url'],
                    'priority' => $tip_config['priority'],
                ];
            }

            // Sort by priority (lower = more important)
            usort($pending_tips, function($a, $b) {
                return $a['priority'] - $b['priority'];
            });

            // Return only the first tip (most important)
            $tip_to_show = !empty($pending_tips) ? $pending_tips[0] : null;

            return new WP_REST_Response([
                'success' => true,
                'tip' => $tip_to_show,
                'total_pending' => count($pending_tips)
            ], 200);

        } catch (Throwable $e) {
            ppv_log("❌ [PPV_User_Tips] Error in rest_get_pending_tips: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return new WP_REST_Response([
                'success' => false,
                'msg' => 'Server error',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    /** ============================================================
     *  📡 REST: Mark Tip as Shown
     * ============================================================ */
    public static function rest_mark_shown(WP_REST_Request $request) {
        global $wpdb;

        $user_id = self::get_user_id();
        $tip_key = sanitize_text_field($request->get_param('tip_key'));

        if (!$user_id) {
            return new WP_REST_Response(['success' => false], 401);
        }

        $wpdb->update(
            $wpdb->prefix . 'ppv_user_tips',
            ['shown_at' => current_time('mysql')],
            ['user_id' => $user_id, 'tip_key' => $tip_key],
            ['%s'],
            ['%d', '%s']
        );

        ppv_log("👁️ [PPV_User_Tips] Tip '{$tip_key}' shown to user {$user_id}");

        return new WP_REST_Response(['success' => true], 200);
    }

    /** ============================================================
     *  📡 REST: Dismiss Tip
     * ============================================================ */
    public static function rest_dismiss_tip(WP_REST_Request $request) {
        global $wpdb;

        $user_id = self::get_user_id();
        $tip_key = sanitize_text_field($request->get_param('tip_key'));

        if (!$user_id) {
            return new WP_REST_Response(['success' => false], 401);
        }

        $wpdb->update(
            $wpdb->prefix . 'ppv_user_tips',
            [
                'dismissed_at' => current_time('mysql'),
                'shown_at' => $wpdb->get_var($wpdb->prepare(
                    "SELECT shown_at FROM {$wpdb->prefix}ppv_user_tips WHERE user_id = %d AND tip_key = %s",
                    $user_id, $tip_key
                )) ?: current_time('mysql')
            ],
            ['user_id' => $user_id, 'tip_key' => $tip_key],
            ['%s', '%s'],
            ['%d', '%s']
        );

        ppv_log("❌ [PPV_User_Tips] Tip '{$tip_key}' dismissed by user {$user_id}");

        return new WP_REST_Response(['success' => true], 200);
    }

    /** ============================================================
     *  📡 REST: Mark Tip as Clicked
     * ============================================================ */
    public static function rest_mark_clicked(WP_REST_Request $request) {
        global $wpdb;

        $user_id = self::get_user_id();
        $tip_key = sanitize_text_field($request->get_param('tip_key'));

        if (!$user_id) {
            return new WP_REST_Response(['success' => false], 401);
        }

        $wpdb->update(
            $wpdb->prefix . 'ppv_user_tips',
            [
                'clicked_at' => current_time('mysql'),
                'dismissed_at' => current_time('mysql'),
                'shown_at' => $wpdb->get_var($wpdb->prepare(
                    "SELECT shown_at FROM {$wpdb->prefix}ppv_user_tips WHERE user_id = %d AND tip_key = %s",
                    $user_id, $tip_key
                )) ?: current_time('mysql')
            ],
            ['user_id' => $user_id, 'tip_key' => $tip_key],
            ['%s', '%s', '%s'],
            ['%d', '%s']
        );

        ppv_log("✅ [PPV_User_Tips] Tip '{$tip_key}' clicked by user {$user_id}");

        return new WP_REST_Response(['success' => true], 200);
    }

    /** ============================================================
     *  🔧 HELPER FUNCTIONS
     * ============================================================ */

    /**
     * Get current user ID from session
     */
    private static function get_user_id() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        return intval($_SESSION['ppv_user_id'] ?? 0);
    }

    /**
     * Check special conditions
     */
    private static function check_condition($condition, $user_id) {
        global $wpdb;

        switch ($condition) {
            case 'no_rewards_redeemed':
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_redemptions WHERE user_id = %d",
                    $user_id
                ));
                return intval($count) === 0;

            case 'no_location_data':
                // Check if user has no address data for geocoding
                $user = $wpdb->get_row($wpdb->prepare(
                    "SELECT address, zip, city FROM {$wpdb->prefix}ppv_users WHERE id = %d",
                    $user_id
                ));
                // Return true if ALL address fields are empty (tip should show)
                return empty(trim($user->address ?? '')) && empty(trim($user->zip ?? '')) && empty(trim($user->city ?? ''));

            default:
                return true;
        }
    }

    /**
     * Manually trigger tip check for user (useful for testing)
     */
    public static function check_tips_for_user($user_id) {
        global $wpdb;

        $scan_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points WHERE user_id = %d",
            $user_id
        ));

        self::on_user_scan($user_id, ['scan_count' => $scan_count]);
    }
}

// Initialize
PPV_User_Tips::hooks();
