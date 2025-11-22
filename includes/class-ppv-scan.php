<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Mobile Scan API v1.0
 * Telefonos QR scan kezelés
 *
 * Features:
 * - VIP Bonus (4 típus: %, fix, streak, daily)
 * - Bonus Day kezelés
 * - Campaign kezelés
 * - Filiale support
 *
 * Author: Erik Borota / PunktePass
 */

class PPV_Scan {

    // ============================================================
    // INITIALIZATION
    // ============================================================
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private static function get_lang() {
        static $lang = null;
        if ($lang === null) {
            $lang = class_exists('PPV_Lang') ? (PPV_Lang::$strings ?? []) : [];
        }
        return $lang;
    }

    private static function t($key, $fallback = '') {
        $L = self::get_lang();
        return esc_html($L[$key] ?? $fallback);
    }

    private static function get_store_by_key($store_key) {
        if (empty($store_key)) return null;

        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, name FROM {$wpdb->prefix}ppv_stores WHERE store_key=%s LIMIT 1",
            $store_key
        ));

        return $store ?: null;
    }

    /**
     * GET STORE ID (Session-aware with FILIALE support)
     * Priority: ppv_current_filiale_id > ppv_store_id > store_key
     */
    private static function get_session_aware_store_id($store_key = '') {
        global $wpdb;

        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $filiale_id = intval($_SESSION['ppv_current_filiale_id']);
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT id, email, name FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
                $filiale_id
            ));
            if ($store) {
                return $store;
            }
        }

        // Session - base store
        if (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT id, email, name FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
                $store_id
            ));
            if ($store) {
                return $store;
            }
        }

        // Fallback: store_key (for POS devices without session)
        if (!empty($store_key)) {
            return self::get_store_by_key($store_key);
        }

        return null;
    }

    private static function validate_store($store_key) {
        $store = self::get_store_by_key($store_key);

        if (!$store) {
            return [
                'valid' => false,
                'response' => new WP_REST_Response([
                    'success' => false,
                    'message' => self::t('err_unknown_store', '❌ Ismeretlen bolt')
                ], 400)
            ];
        }

        return [
            'valid' => true,
            'store' => $store
        ];
    }

    private static function check_rate_limit($user_id, $store_id) {
        global $wpdb;

        // Get store name for error responses
        $store_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));

        // 1) Check if already scanned TODAY (daily limit: 1 scan per day per store)
        $already_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
            WHERE user_id=%d AND store_id=%d
            AND DATE(created)=CURDATE()
            AND type='qr_scan'
        ", $user_id, $store_id));

        if ($already_today > 0) {
            return [
                'limited' => true,
                'response' => new WP_REST_Response([
                    'success' => false,
                    'message' => self::t('err_already_scanned_today', '⚠️ Heute bereits gescannt'),
                    'store_name' => $store_name ?? 'PunktePass',
                    'error_type' => 'already_scanned_today'
                ], 429)
            ];
        }

        // 2) Check for duplicate scan (within 2 minutes - prevents retry spam)
        $recent = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}ppv_points
            WHERE user_id=%d AND store_id=%d
            AND created >= (NOW() - INTERVAL 2 MINUTE)
            AND type='qr_scan'
        ", $user_id, $store_id));

        if ($recent) {
            return [
                'limited' => true,
                'response' => new WP_REST_Response([
                    'success' => false,
                    'message' => self::t('err_duplicate_scan', '⚠️ Bereits gescannt. Bitte warten.'),
                    'store_name' => $store_name ?? 'PunktePass',
                    'error_type' => 'duplicate_scan'
                ], 429)
            ];
        }

        return ['limited' => false];
    }

    private static function insert_log($store_id, $user_id, $msg, $type = 'scan', $error_type = null) {
        global $wpdb;

        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address)[0]);
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $metadata_array = [
            'timestamp' => current_time('mysql'),
            'type' => $type
        ];

        if ($error_type !== null) {
            $metadata_array['error_type'] = $error_type;
        }

        $metadata = json_encode($metadata_array);

        $wpdb->insert("{$wpdb->prefix}ppv_pos_log", [
            'store_id' => $store_id,
            'user_id' => $user_id,
            'message' => sanitize_text_field($msg),
            'type' => $type,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'metadata' => $metadata,
            'created_at' => current_time('mysql')
        ]);
    }

    private static function decode_user_from_qr($qr) {
        if (empty($qr)) return false;

        // Format: PPU{user_id}{token}
        if (strpos($qr, 'PPU') === 0) {
            $body = substr($qr, 3);
            if (preg_match('/^(\d+)/', $body, $m)) {
                return intval($m[1]);
            }
        }

        // Legacy format: PPUSER-{user_id}-...
        if (strpos($qr, 'PPUSER-') === 0) {
            $parts = explode('-', $qr);
            return intval($parts[1] ?? 0);
        }

        return false;
    }

    // ============================================================
    // REST ROUTES
    // ============================================================
    public static function register_rest_routes() {
        register_rest_route('punktepass/v1', '/scan', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_process_scan'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);
    }

    // ============================================================
    // MAIN SCAN HANDLER
    // ============================================================
    public static function rest_process_scan(WP_REST_Request $r) {
        global $wpdb;

        $data = $r->get_json_params();
        $qr_code = sanitize_text_field($data['qr'] ?? '');
        $store_key = sanitize_text_field($data['store_key'] ?? '');
        $campaign_id = intval($data['campaign_id'] ?? 0);

        if (empty($qr_code) || empty($store_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_invalid_request', '❌ Érvénytelen kérés')
            ], 400);
        }

        // Validate store
        $validation = self::validate_store($store_key);
        if (!$validation['valid']) {
            return $validation['response'];
        }
        $store = $validation['store'];

        // FILIALE SUPPORT: Use session-aware store ID for points
        $session_store = self::get_session_aware_store_id($store_key);
        if ($session_store && isset($session_store->id)) {
            $store_id = intval($session_store->id);
        } else {
            $store_id = intval($store->id);
        }

        // DEBUG: Log store_id resolution
        error_log("🔍 [PPV_Scan] Store ID resolution: " . json_encode([
            'session_store_id' => $session_store->id ?? 'NULL',
            'validated_store_id' => $store->id ?? 'NULL',
            'final_store_id' => $store_id,
        ]));

        if ($store_id === 0) {
            error_log("❌ [PPV_Scan] CRITICAL: store_id is 0!");
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ Invalid store_id (0)'
            ], 400);
        }

        // Decode user from QR
        $user_id = self::decode_user_from_qr($qr_code);
        if (!$user_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_invalid_qr', '❌ Érvénytelen QR'),
                'store_name' => $store->name ?? 'PunktePass',
                'error_type' => 'invalid_qr'
            ], 400);
        }

        // Rate limit check
        $rate_check = self::check_rate_limit($user_id, $store_id);
        if ($rate_check['limited']) {
            $response_data = $rate_check['response']->get_data();
            $error_type = $response_data['error_type'] ?? null;
            self::insert_log($store_id, $user_id, $response_data['message'] ?? '⚠️ Rate limit', 'error', $error_type);
            return $rate_check['response'];
        }

        // ═══════════════════════════════════════════════════════════
        // BASE POINTS CALCULATION
        // ═══════════════════════════════════════════════════════════
        $points_add = 1;

        // Campaign points (if campaign_id provided)
        if ($campaign_id > 0) {
            $campaign_points = $wpdb->get_var($wpdb->prepare("
                SELECT points FROM {$wpdb->prefix}ppv_campaigns
                WHERE id=%d AND store_id=%d AND status='active'
            ", $campaign_id, $store_id));

            if ($campaign_points) {
                $points_add = intval($campaign_points);
            }
        }

        // ═══════════════════════════════════════════════════════════
        // BONUS DAY CALCULATION
        // ═══════════════════════════════════════════════════════════
        $bonus = $wpdb->get_row($wpdb->prepare("
            SELECT multiplier, extra_points FROM {$wpdb->prefix}ppv_bonus_days
            WHERE store_id=%d AND date=%s AND active=1
        ", $store_id, date('Y-m-d')));

        if ($bonus) {
            $points_add = (int)round(($points_add * (float)$bonus->multiplier) + (int)$bonus->extra_points);
        }

        // ═══════════════════════════════════════════════════════════
        // VIP LEVEL BONUSES (4 types)
        // ═══════════════════════════════════════════════════════════
        $vip_bonus_details = [
            'pct' => 0,      // 1. Percentage bonus
            'fix' => 0,      // 2. Fixed point bonus
            'streak' => 0,   // 3. Every Xth scan bonus
            'daily' => 0,    // 4. First daily scan bonus
        ];
        $vip_bonus_applied = 0;

        if (class_exists('PPV_User_Level')) {
            // FILIALE FIX: Get VIP settings from PARENT store
            $vip_store_id = $store_id;
            if (class_exists('PPV_Filiale')) {
                $vip_store_id = PPV_Filiale::get_parent_id($store_id);
                if ($vip_store_id !== $store_id) {
                    error_log("🏪 [PPV_Scan] VIP settings: Using PARENT store {$vip_store_id} instead of filiale {$store_id}");
                }
            }

            // Get all VIP settings
            $vip_settings = $wpdb->get_row($wpdb->prepare("
                SELECT
                    vip_enabled, vip_bronze_bonus, vip_silver_bonus, vip_gold_bonus, vip_platinum_bonus,
                    vip_fix_enabled, vip_fix_bronze, vip_fix_silver, vip_fix_gold, vip_fix_platinum,
                    vip_streak_enabled, vip_streak_count, vip_streak_type,
                    vip_streak_bronze, vip_streak_silver, vip_streak_gold, vip_streak_platinum,
                    vip_daily_enabled, vip_daily_bronze, vip_daily_silver, vip_daily_gold, vip_daily_platinum
                FROM {$wpdb->prefix}ppv_stores WHERE id = %d
            ", $vip_store_id));

            error_log("🔍 [PPV_Scan] VIP settings for store {$vip_store_id}: " . json_encode([
                'vip_enabled' => $vip_settings->vip_enabled ?? 'NULL',
                'vip_fix_enabled' => $vip_settings->vip_fix_enabled ?? 'NULL',
                'vip_fix_bronze' => $vip_settings->vip_fix_bronze ?? 'NULL',
            ]));

            if ($vip_settings) {
                $user_level = PPV_User_Level::get_vip_level_for_bonus($user_id);
                $base_points = $points_add;

                error_log("🔍 [PPV_Scan] User VIP level: user_id={$user_id}, level=" . ($user_level ?? 'NULL (Starter)'));

                // Helper to get level-specific value
                $getLevelValue = function($bronze, $silver, $gold, $platinum) use ($user_level) {
                    if ($user_level === null) return 0;
                    switch ($user_level) {
                        case 'bronze': return intval($bronze);
                        case 'silver': return intval($silver);
                        case 'gold': return intval($gold);
                        case 'platinum': return intval($platinum);
                        default: return 0;
                    }
                };

                // 1. PERCENTAGE BONUS
                if ($vip_settings->vip_enabled && $user_level !== null) {
                    $bonus_percent = $getLevelValue(
                        $vip_settings->vip_bronze_bonus ?? 3,
                        $vip_settings->vip_silver_bonus,
                        $vip_settings->vip_gold_bonus,
                        $vip_settings->vip_platinum_bonus
                    );
                    if ($bonus_percent > 0) {
                        $vip_bonus_details['pct'] = (int)round($base_points * ($bonus_percent / 100));
                    }
                }

                // 2. FIXED POINT BONUS
                if ($vip_settings->vip_fix_enabled && $user_level !== null) {
                    $fix_bonus = $getLevelValue(
                        $vip_settings->vip_fix_bronze ?? 1,
                        $vip_settings->vip_fix_silver,
                        $vip_settings->vip_fix_gold,
                        $vip_settings->vip_fix_platinum
                    );
                    if ($fix_bonus > 0) {
                        $vip_bonus_details['fix'] = $fix_bonus;
                    }
                }

                // 3. EVERY Xth SCAN BONUS (Streak)
                if ($vip_settings->vip_streak_enabled && $user_level !== null) {
                    $streak_count = intval($vip_settings->vip_streak_count);
                    if ($streak_count > 0) {
                        $user_scan_count = (int)$wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
                            WHERE user_id = %d AND store_id = %d AND type = 'qr_scan'
                        ", $user_id, $store_id));

                        $next_scan_number = $user_scan_count + 1;
                        if ($next_scan_number % $streak_count === 0) {
                            $streak_type = $vip_settings->vip_streak_type ?? 'fixed';

                            if ($streak_type === 'fixed') {
                                $vip_bonus_details['streak'] = $getLevelValue(
                                    $vip_settings->vip_streak_bronze ?? 1,
                                    $vip_settings->vip_streak_silver,
                                    $vip_settings->vip_streak_gold,
                                    $vip_settings->vip_streak_platinum
                                );
                            } elseif ($streak_type === 'double') {
                                $vip_bonus_details['streak'] = $base_points;
                            } elseif ($streak_type === 'triple') {
                                $vip_bonus_details['streak'] = $base_points * 2;
                            }

                            error_log("🔥 [PPV_Scan] Streak bonus triggered! Scan #{$next_scan_number} (every {$streak_count})");
                        }
                    }
                }

                // 4. FIRST DAILY SCAN BONUS
                if ($vip_settings->vip_daily_enabled && $user_level !== null) {
                    $today = date('Y-m-d');
                    $already_scanned_today = (int)$wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
                        WHERE user_id = %d AND store_id = %d AND type = 'qr_scan'
                        AND DATE(created) = %s
                    ", $user_id, $store_id, $today));

                    if ($already_scanned_today === 0) {
                        $vip_bonus_details['daily'] = $getLevelValue(
                            $vip_settings->vip_daily_bronze ?? 5,
                            $vip_settings->vip_daily_silver,
                            $vip_settings->vip_daily_gold,
                            $vip_settings->vip_daily_platinum
                        );
                        error_log("☀️ [PPV_Scan] First daily scan bonus applied for user {$user_id}");
                    }
                }

                // Calculate total VIP bonus
                $vip_bonus_applied = $vip_bonus_details['pct'] + $vip_bonus_details['fix'] + $vip_bonus_details['streak'] + $vip_bonus_details['daily'];

                if ($vip_bonus_applied > 0) {
                    $points_add += $vip_bonus_applied;
                    error_log("✅ [PPV_Scan] VIP bonuses applied: level={$user_level}, pct=+{$vip_bonus_details['pct']}, fix=+{$vip_bonus_details['fix']}, streak=+{$vip_bonus_details['streak']}, daily=+{$vip_bonus_details['daily']}, total_bonus={$vip_bonus_applied}, total_points={$points_add}");
                }
            }
        }

        // ═══════════════════════════════════════════════════════════
        // INSERT POINTS
        // ═══════════════════════════════════════════════════════════
        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id' => $user_id,
            'store_id' => $store_id,
            'points' => $points_add,
            'campaign_id' => $campaign_id ?: null,
            'type' => 'qr_scan',
            'created' => current_time('mysql')
        ]);

        // Update lifetime_points for VIP level calculation
        if (class_exists('PPV_User_Level')) {
            PPV_User_Level::add_lifetime_points($user_id, $points_add);
        }

        // Log
        $log_msg = $vip_bonus_applied > 0
            ? "+{$points_add} " . self::t('points', 'Punkte') . " (VIP: +{$vip_bonus_applied})"
            : "+{$points_add} " . self::t('points', 'Punkte');
        self::insert_log($store_id, $user_id, $log_msg, 'qr_scan');

        // Get store name for response
        $store_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));

        // Build response message
        $vip_suffix = $vip_bonus_applied > 0 ? " (VIP-Bonus: +{$vip_bonus_applied})" : '';
        $success_msg = "✅ +{$points_add} " . self::t('points', 'Punkte') . $vip_suffix;

        return new WP_REST_Response([
            'success' => true,
            'message' => $success_msg,
            'user_id' => $user_id,
            'store_id' => $store_id,
            'store_name' => $store_name ?? 'PunktePass',
            'points' => $points_add,
            'vip_bonus' => $vip_bonus_applied,
            'vip_bonus_details' => $vip_bonus_details,
            'time' => current_time('mysql')
        ], 200);
    }
}

PPV_Scan::hooks();
