<?php
if (!defined('ABSPATH')) exit;

class PPV_Scan {

    public static function hooks() {
        add_shortcode('ppv_scan_page', [__CLASS__, 'render_scan_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_ppv_auto_add_point', [__CLASS__, 'ajax_auto_add_point']);
        // 🔒 Removed wp_ajax_nopriv - authentication required for point operations
    }

    /** 🔹 CSS + JS betöltése */
    public static function enqueue_assets() {
        wp_enqueue_style('ppv-scan', PPV_PLUGIN_URL . 'assets/css/ppv-scan.css', [], time());
        wp_enqueue_script('ppv-scan', PPV_PLUGIN_URL . 'assets/js/ppv-scan.js', ['jquery'], time(), true);

        $__data = is_array([
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ppv_scan_nonce'),
            'redirect' => site_url('/user-dashboard/')
        ] ?? null) ? [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ppv_scan_nonce'),
            'redirect' => site_url('/user-dashboard/')
        ] : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('ppv-scan', "window.ppvScan = {$__json};", 'before');
    }

    /** 🔹 SCAN oldal megjelenítése */
    public static function render_scan_page() {
        ob_start();
        $store_id = isset($_GET['store']) ? intval($_GET['store']) : 0;
        $campaign_id = isset($_GET['campaign']) ? intval($_GET['campaign']) : 0;

        // 🔍 Merchant setup checks (for store owners viewing scan page)
        $merchant_warnings = [];
        if ($store_id) {
            global $wpdb;

            // Get store data
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT id, latitude, longitude FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
                $store_id
            ));

            if ($store) {
                // 🏪 FILIALE FIX: Get rewards from PARENT store if this is a filiale
                $reward_store_id = $store_id;
                if (class_exists('PPV_Filiale')) {
                    $reward_store_id = PPV_Filiale::get_parent_id($store_id);
                }

                // Check 1: Prämie (reward) configured?
                $has_reward = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_rewards WHERE store_id = %d AND points_given > 0",
                    $reward_store_id
                ));

                if (!$has_reward) {
                    $merchant_warnings[] = [
                        'icon' => '🎁',
                        'de' => 'Bitte richten Sie eine Prämie ein (Punkte pro Scan).',
                        'hu' => 'Kérjük, állítson be egy prémiumot (pontok szkenneléskor).',
                        'ro' => 'Vă rugăm să configurați o recompensă (puncte per scanare).'
                    ];
                }

                // Check 2: Latitude/Longitude configured?
                if (empty($store->latitude) || empty($store->longitude) ||
                    floatval($store->latitude) == 0 || floatval($store->longitude) == 0) {
                    $merchant_warnings[] = [
                        'icon' => '📍',
                        'de' => 'Bitte setzen Sie Ihren Standort im Profil: Klicken Sie auf "Auf Google suchen" und speichern Sie.',
                        'hu' => 'Kérjük, állítsa be a pozícióját a profilban: Kattintson a "Google keresés" gombra, majd mentse.',
                        'ro' => 'Vă rugăm să setați locația în profil: Faceți clic pe "Căutare Google" și salvați.'
                    ];
                }
            }
        }

        // Get current language
        $lang = 'de';
        if (class_exists('PPV_Lang')) {
            $lang = PPV_Lang::current();
        }
        ?>
        <div class="ppv-scan-wrapper">
            <h2>📸 Punkte scannen</h2>

            <?php if (!empty($merchant_warnings)): ?>
                <div class="ppv-merchant-setup-warnings" style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 12px 0; color: #856404;">
                        <?php
                        if ($lang === 'hu') echo '⚠️ Beállítás szükséges';
                        elseif ($lang === 'ro') echo '⚠️ Configurare necesară';
                        else echo '⚠️ Einrichtung erforderlich';
                        ?>
                    </h4>
                    <ul style="margin: 0; padding-left: 20px; color: #856404;">
                        <?php foreach ($merchant_warnings as $warning): ?>
                            <li style="margin-bottom: 8px;">
                                <?php echo esc_html($warning['icon'] . ' ' . ($warning[$lang] ?? $warning['de'])); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin: 12px 0 0 0; font-size: 13px; color: #856404;">
                        <?php
                        if ($lang === 'hu') echo '👉 Menjen a Profil oldalra a beállításokhoz.';
                        elseif ($lang === 'ro') echo '👉 Accesați pagina Profil pentru configurare.';
                        else echo '👉 Gehen Sie zur Profilseite für die Einrichtung.';
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!is_user_logged_in()): ?>
                <p class="ppv-warning">⚠️ Bitte melde dich an, um Punkte zu sammeln.</p>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="ppv-btn">Jetzt anmelden</a>
            <?php elseif ($store_id): ?>
                <div id="ppv-scan-status"
                     data-store="<?php echo esc_attr($store_id); ?>"
                     data-campaign="<?php echo esc_attr($campaign_id); ?>">
                    <div class="ppv-loader"></div>
                    <p>Punkte werden automatisch hinzugefügt...</p>
                </div>
            <?php else: ?>
                <p class="ppv-warning">❌ Kein Store angegeben. Bitte scanne einen gültigen QR-Code.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /** 🔹 AJAX: pont automatikus jóváírás */
public static function ajax_auto_add_point() {
    check_ajax_referer('ppv_scan_nonce', 'nonce');
    global $wpdb;

    // --- USER AZONOSÍTÁS ---
    $user_id = intval($_SESSION['ppv_user_id'] ?? 0);
    if (!$user_id) {
        $user_id = intval(get_current_user_id());
    }

    if (!$user_id) {
        wp_send_json_error(['msg' => '❌ Kein Benutzer gefunden (Session leer).']);
    }

    // --- STORE AZONOSÍTÁS ---
    // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
    $store_id = 0;
    if (!empty($_SESSION['ppv_current_filiale_id'])) {
        $store_id = intval($_SESSION['ppv_current_filiale_id']);
    } elseif (!empty($_SESSION['ppv_store_id'])) {
        $store_id = intval($_SESSION['ppv_store_id']);
    } elseif (!empty($_POST['store_id'])) {
        $store_id = intval($_POST['store_id']);
    } elseif (!empty($GLOBALS['ppv_active_store'])) {
        $store_id = intval($GLOBALS['ppv_active_store']);
    }

    if (!$store_id) {
        wp_send_json_error(['msg' => '❌ Kein Store angegeben oder aktiv.']);
    }

    // --- Kampány ---
    $campaign_id = intval($_POST['campaign_id'] ?? 0);

    // 🔒 NEW: Device/IP tracking for fraud detection
    $device_fingerprint = isset($_POST['device_fingerprint']) ? sanitize_text_field($_POST['device_fingerprint']) : null;
    $scan_lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $scan_lng = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $scanner_id = isset($_POST['scanner_id']) ? intval($_POST['scanner_id']) : null;
    $ip_address = self::get_client_ip();

    // --- Ellenőrzés: létezik-e a bolt ---
    $store = $wpdb->get_row($wpdb->prepare(
        "SELECT id, company_name FROM {$wpdb->prefix}ppv_stores WHERE id=%d",
        $store_id
    ));
    if (!$store) {
        wp_send_json_error(['msg' => '❌ Store nicht gefunden.']);
    }

    // --- Napi pont limit ---
    $today = date('Y-m-d');
    $exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
        WHERE user_id=%d AND store_id=%d AND DATE(created)=%s
    ", $user_id, $store_id, $today));

    if ($exists > 0) {
        wp_send_json_error(['msg' => '⚠️ Du hast heute bereits einen Punkt gesammelt.']);
    }

    // --- Pontok kiszámítása (Campaign OR Reward points_given) ---
    $points_to_add = 0;

    // 1. Check for campaign points FIRST (if campaign_id provided)
    if ($campaign_id) {
        $campaign_points = $wpdb->get_var($wpdb->prepare("
            SELECT points FROM {$wpdb->prefix}ppv_campaigns
            WHERE id=%d AND store_id=%d AND status='active'
        ", $campaign_id, $store_id));

        if ($campaign_points) {
            $points_to_add = intval($campaign_points);
            ppv_log("🎯 [PPV_Scan] Campaign points applied: campaign_id={$campaign_id}, points={$points_to_add}");
        }
    }

    // 2. If no campaign, use Prämien (reward) points_given as base
    if ($points_to_add === 0) {
        // 🏪 FILIALE FIX: Get rewards from PARENT store if this is a filiale
        $reward_store_id = $store_id;
        if (class_exists('PPV_Filiale')) {
            $reward_store_id = PPV_Filiale::get_parent_id($store_id);
            if ($reward_store_id !== $store_id) {
                ppv_log("🏪 [PPV_Scan] Reward lookup: Using PARENT store {$reward_store_id} instead of filiale {$store_id}");
            }
        }

        $reward_points = $wpdb->get_var($wpdb->prepare("
            SELECT points_given FROM {$wpdb->prefix}ppv_rewards
            WHERE store_id=%d AND points_given > 0
            ORDER BY id ASC LIMIT 1
        ", $reward_store_id));

        if ($reward_points && intval($reward_points) > 0) {
            $points_to_add = intval($reward_points);
            ppv_log("🎁 [PPV_Scan] Reward base points applied: reward_store_id={$reward_store_id}, points_given={$points_to_add}");
        }
    }

    // 3. If neither exists, notify merchant to configure
    if ($points_to_add === 0) {
        ppv_log("⚠️ [PPV_Scan] No points configured: store_id={$store_id}, campaign_id={$campaign_id}");
        wp_send_json_error(['msg' => '⚠️ Keine Punkte konfiguriert. Bitte Prämie oder Kampagne einrichten.']);
    }

    // 🔒 SECURITY: Max point limit per scan (prevents point inflation)
    $max_points_per_scan = 100;
    if ($points_to_add > $max_points_per_scan) {
        ppv_log("⚠️ [PPV_Scan] Points capped: requested={$points_to_add}, max={$max_points_per_scan}");
        $points_to_add = $max_points_per_scan;
    }

    // 🔍 DEBUG: Log scan source
    ppv_log("🔍 [PPV_Scan] AJAX scan: user_id={$user_id}, store_id={$store_id}, points={$points_to_add}");

    /** 🌟 VIP Level Bonuses */
    $vip_bonus_applied = 0;

    if (class_exists('PPV_User_Level')) {
        // 🏪 FILIALE FIX: Get VIP settings from PARENT store if this is a filiale
        $vip_store_id = $store_id;
        if (class_exists('PPV_Filiale')) {
            $vip_store_id = PPV_Filiale::get_parent_id($store_id);
            if ($vip_store_id !== $store_id) {
                ppv_log("🏪 [PPV_Scan] VIP settings: Using PARENT store {$vip_store_id} instead of filiale {$store_id}");
            }
        }

        // Get VIP settings for this store (or parent store)
        $vip_settings = $wpdb->get_row($wpdb->prepare("
            SELECT
                vip_fix_enabled, vip_fix_bronze, vip_fix_silver, vip_fix_gold, vip_fix_platinum,
                vip_streak_enabled, vip_streak_count, vip_streak_type,
                vip_streak_bronze, vip_streak_silver, vip_streak_gold, vip_streak_platinum,
                vip_daily_enabled, vip_daily_bronze, vip_daily_silver, vip_daily_gold, vip_daily_platinum
            FROM {$wpdb->prefix}ppv_stores WHERE id = %d
        ", $vip_store_id));

        ppv_log("🔍 [PPV_Scan] VIP settings for store {$vip_store_id}: " . json_encode([
            'vip_fix_enabled' => $vip_settings->vip_fix_enabled ?? 'NULL',
            'vip_fix_bronze' => $vip_settings->vip_fix_bronze ?? 'NULL',
        ]));

        if ($vip_settings) {
            $user_level = PPV_User_Level::get_vip_level_for_bonus($user_id);
            $base_points = $points_to_add;

            ppv_log("🔍 [PPV_Scan] User VIP level: user_id={$user_id}, level=" . ($user_level ?? 'NULL (Starter)'));

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

            // 🔒 FIX: Initialize ALL keys including 'pct' to prevent undefined array key error
            $vip_bonus_details = ['pct' => 0, 'fix' => 0, 'streak' => 0, 'daily' => 0];

            // 🔒 FIX: Save TRUE base points BEFORE any bonuses for double_points calculations
            $true_base_points = $points_to_add;

            // 1. FIXED POINT BONUS
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

            // 3. STREAK BONUS (every Xth scan)
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

                        ppv_log("🔥 [PPV_Scan] Streak bonus triggered! Scan #{$next_scan_number} (every {$streak_count})");
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
                    ppv_log("☀️ [PPV_Scan] First daily scan bonus applied for user {$user_id}");
                }
            }

            // Calculate total VIP bonus
            $vip_bonus_applied = $vip_bonus_details['pct'] + $vip_bonus_details['fix'] + $vip_bonus_details['streak'] + $vip_bonus_details['daily'];

            if ($vip_bonus_applied > 0) {
                $points_to_add += $vip_bonus_applied;
                ppv_log("✅ [PPV_Scan] VIP bonuses applied: level={$user_level}, pct=+{$vip_bonus_details['pct']}, fix=+{$vip_bonus_details['fix']}, streak=+{$vip_bonus_details['streak']}, daily=+{$vip_bonus_details['daily']}, total_bonus={$vip_bonus_applied}, total_points={$points_to_add}");
            }
        }
    }

    /** 🎂 Birthday Bonus */
    $birthday_bonus_applied = 0;
    $birthday_bonus_message = '';

    // Get birthday bonus settings from store (or parent store for filiales)
    $birthday_store_id = $store_id;
    if (class_exists('PPV_Filiale')) {
        $birthday_store_id = PPV_Filiale::get_parent_id($store_id);
    }

    $birthday_settings = $wpdb->get_row($wpdb->prepare("
        SELECT birthday_bonus_enabled, birthday_bonus_type, birthday_bonus_value, birthday_bonus_message
        FROM {$wpdb->prefix}ppv_stores WHERE id = %d
    ", $birthday_store_id));

    if ($birthday_settings && $birthday_settings->birthday_bonus_enabled) {
        // Get user's birthday and last birthday bonus date from ppv_users table
        $user_bday_data = $wpdb->get_row($wpdb->prepare("
            SELECT birthday, last_birthday_bonus_at FROM {$wpdb->prefix}ppv_users WHERE id = %d
        ", $user_id));

        if ($user_bday_data && $user_bday_data->birthday) {
            // Check if today is user's birthday (compare month and day)
            $today_md = date('m-d');
            $birthday_md = date('m-d', strtotime($user_bday_data->birthday));

            if ($today_md === $birthday_md) {
                // Anti-abuse check: minimum 320 days between birthday bonuses
                $can_receive_bonus = true;
                if ($user_bday_data->last_birthday_bonus_at) {
                    $last_bonus_date = strtotime($user_bday_data->last_birthday_bonus_at);
                    $days_since_last_bonus = floor((time() - $last_bonus_date) / (60 * 60 * 24));
                    if ($days_since_last_bonus < 320) {
                        $can_receive_bonus = false;
                        ppv_log("🎂 [PPV_Scan] Birthday bonus BLOCKED for user {$user_id}: only {$days_since_last_bonus} days since last bonus (min 320)");
                    }
                }

                if ($can_receive_bonus) {
                    ppv_log("🎂 [PPV_Scan] Today is user {$user_id}'s birthday!");

                    $bonus_type = $birthday_settings->birthday_bonus_type ?? 'double_points';
                    // 🔒 FIX: Use true_base_points (before VIP bonuses) to prevent bonus compounding
                    $base_points_for_birthday = isset($true_base_points) ? $true_base_points : $points_to_add;

                    switch ($bonus_type) {
                        case 'double_points':
                            $birthday_bonus_applied = $base_points_for_birthday; // Double = add same amount again
                            break;
                        case 'fixed_points':
                            $birthday_bonus_applied = intval($birthday_settings->birthday_bonus_value ?? 0);
                            break;
                        case 'free_product':
                            // Free product is handled separately (not points)
                            // TODO: Implement free product voucher creation
                            ppv_log("🎁 [PPV_Scan] Birthday free product bonus - not yet implemented");
                            break;
                    }

                    if ($birthday_bonus_applied > 0) {
                        // 🔒 FIX: Use atomic UPDATE with WHERE to prevent race condition
                        $rows_updated = $wpdb->query($wpdb->prepare("
                            UPDATE {$wpdb->prefix}ppv_users
                            SET last_birthday_bonus_at = %s
                            WHERE id = %d
                            AND (last_birthday_bonus_at IS NULL OR last_birthday_bonus_at < DATE_SUB(CURDATE(), INTERVAL 320 DAY))
                        ", date('Y-m-d'), $user_id));

                        if ($rows_updated > 0) {
                            // Successfully claimed - add bonus
                            $points_to_add += $birthday_bonus_applied;
                            $birthday_bonus_message = $birthday_settings->birthday_bonus_message ?? '';
                            ppv_log("🎂 [PPV_Scan] Birthday bonus applied: type={$bonus_type}, bonus=+{$birthday_bonus_applied}, total_points={$points_to_add}");
                        } else {
                            // Race condition prevented - another request already claimed it
                            $birthday_bonus_applied = 0;
                            ppv_log("🔒 [PPV_Scan] Birthday bonus race condition prevented for user {$user_id}");
                        }
                    }
                }
            }
        }
    }

    /** ⭐ Google Review Bonus (auto-awarded on next scan after review request) */
    $google_review_bonus_applied = 0;

    if (class_exists('PPV_Google_Review_Request')) {
        $review_bonus = PPV_Google_Review_Request::check_and_award_bonus($store_id, $user_id);
        if ($review_bonus && !empty($review_bonus['points'])) {
            $google_review_bonus_applied = intval($review_bonus['points']);
            ppv_log("⭐ [PPV_Scan] Google Review bonus applied: user_id={$user_id}, bonus=+{$google_review_bonus_applied}");
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 🔒 INSERT POINTS (WITH TRANSACTION FOR ATOMICITY)
    // ═══════════════════════════════════════════════════════════
    $wpdb->query('START TRANSACTION');

    try {
        // 🔒 DUPLICATE CHECK: Prevent race condition double-inserts
        $recent_insert = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}ppv_points
            WHERE user_id = %d AND store_id = %d AND type = 'qr_scan'
            AND created > DATE_SUB(NOW(), INTERVAL 5 SECOND)
            LIMIT 1
        ", $user_id, $store_id));

        if ($recent_insert) {
            $wpdb->query('ROLLBACK');
            ppv_log("⚠️ [PPV_Scan] Duplicate scan blocked: user={$user_id}, store={$store_id}, existing_id={$recent_insert}");
            wp_send_json_error(['msg' => '⚠️ Scan bereits verarbeitet', 'error_type' => 'duplicate_scan']);
        }

        // --- Beszúrás with new tracking fields ---
        $insert_result = $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id'    => $user_id,
            'store_id'   => $store_id,
            'points'     => $points_to_add,
            'campaign_id'=> $campaign_id ?: null,
            'type'       => 'qr_scan',
            // 🔒 NEW: Device/GPS tracking fields
            'device_fingerprint' => $device_fingerprint,
            'ip_address' => $ip_address,
            'latitude' => $scan_lat,
            'longitude' => $scan_lng,
            'scanner_id' => $scanner_id,
            'created'    => current_time('mysql')
        ]);

        if ($insert_result === false) {
            $wpdb->query('ROLLBACK');
            ppv_log("❌ [PPV_Scan] Failed to insert points: " . $wpdb->last_error);
            wp_send_json_error(['msg' => '❌ Datenbankfehler', 'error_type' => 'db_error']);
        }

        $points_insert_id = $wpdb->insert_id;

        // Update lifetime_points for VIP level calculation
        $total_points_for_lifetime = $points_to_add + $google_review_bonus_applied;
        if (class_exists('PPV_User_Level') && $total_points_for_lifetime > 0) {
            PPV_User_Level::add_lifetime_points($user_id, $total_points_for_lifetime);
        }

        $wpdb->query('COMMIT');
        ppv_log("✅ [PPV_Scan] Points inserted successfully: id={$points_insert_id}, user={$user_id}, store={$store_id}, points={$points_to_add}");

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        ppv_log("❌ [PPV_Scan] Transaction failed: " . $e->getMessage());
        wp_send_json_error(['msg' => '❌ Transaktionsfehler', 'error_type' => 'transaction_error']);
    }

    // --- Visszajelzés ---
    $msg = "{$points_to_add} Punkt(e) erfolgreich gesammelt!";
    if ($vip_bonus_applied > 0) {
        $msg .= " (VIP-Bonus: +{$vip_bonus_applied})";
    }
    if ($birthday_bonus_applied > 0) {
        $msg .= " 🎂 Geburtstags-Bonus: +{$birthday_bonus_applied}!";
    }
    if ($google_review_bonus_applied > 0) {
        $msg .= " ⭐ Google-Bewertungs-Bonus: +{$google_review_bonus_applied}!";
    }

    wp_send_json_success([
        'msg'   => $msg,
        'store' => $store->company_name ?? 'Unbekannt',
        'user_id' => $user_id,
        'store_id'=> $store_id,
        'points' => $points_to_add,
        'vip_bonus' => $vip_bonus_applied,
        'birthday_bonus' => $birthday_bonus_applied,
        'birthday_message' => $birthday_bonus_message,
        'google_review_bonus' => $google_review_bonus_applied
    ]);
}

    // ============================================================
    // 🔒 HELPER: Get client IP address
    // ============================================================
    private static function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',   // Proxy/Load balancer
            'HTTP_X_REAL_IP',         // Nginx proxy
            'REMOTE_ADDR'             // Direct connection
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated list (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

}

PPV_Scan::hooks();
