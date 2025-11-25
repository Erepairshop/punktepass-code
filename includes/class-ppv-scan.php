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

            $vip_bonus_details = ['fix' => 0, 'streak' => 0, 'daily' => 0];

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

    // --- Beszúrás ---
    $wpdb->insert("{$wpdb->prefix}ppv_points", [
        'user_id'    => $user_id,
        'store_id'   => $store_id,
        'points'     => $points_to_add,
        'campaign_id'=> $campaign_id,
        'type'       => 'qr_scan',
        'created'    => current_time('mysql')
    ]);

    // Update lifetime_points for VIP level calculation
    if (class_exists('PPV_User_Level') && $points_to_add > 0) {
        PPV_User_Level::add_lifetime_points($user_id, $points_to_add);
    }

    // --- Visszajelzés ---
    $msg = "{$points_to_add} Punkt(e) erfolgreich gesammelt!";
    if ($vip_bonus_applied > 0) {
        $msg .= " (VIP-Bonus: +{$vip_bonus_applied})";
    }

    wp_send_json_success([
        'msg'   => $msg,
        'store' => $store->company_name ?? 'Unbekannt',
        'user_id' => $user_id,
        'store_id'=> $store_id,
        'points' => $points_to_add,
        'vip_bonus' => $vip_bonus_applied
    ]);
}

}

PPV_Scan::hooks();
