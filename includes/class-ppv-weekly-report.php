<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - Weekly Report Email System
 * Sends weekly scan statistics to store owners every Monday at 8:00 AM
 * Language is determined by store's country setting
 */
class PPV_Weekly_Report {

    public static function hooks() {
        add_action('init', [__CLASS__, 'schedule_cron']);
        add_action('ppv_weekly_report', [__CLASS__, 'send_all_reports']);

        // Manual trigger for testing (admin only)
        add_action('wp_ajax_ppv_test_weekly_report', [__CLASS__, 'ajax_test_report']);
    }

    /**
     * Schedule weekly cron event (every Monday at 8:00 AM)
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled('ppv_weekly_report')) {
            // Next Monday at 8:00 AM server time
            $next_monday = strtotime('next monday 08:00:00');
            wp_schedule_event($next_monday, 'weekly', 'ppv_weekly_report');
            ppv_log("📧 [Weekly Report] Cron scheduled for: " . date('Y-m-d H:i:s', $next_monday));
        }
    }

    /**
     * Send reports to all active stores
     */
    public static function send_all_reports() {
        global $wpdb;

        ppv_log("📧 [Weekly Report] Starting weekly report generation...");

        // Get all active stores with email
        $stores = $wpdb->get_results("
            SELECT id, name, email, country, company_name, city
            FROM {$wpdb->prefix}ppv_stores
            WHERE email IS NOT NULL
              AND email != ''
              AND subscription_status IN ('active', 'trial')
              AND parent_store_id IS NULL
              AND (repair_enabled IS NULL OR repair_enabled = 0)
        ");

        $sent_count = 0;
        $error_count = 0;

        foreach ($stores as $store) {
            $result = self::send_report_for_store($store);
            if ($result) {
                $sent_count++;
            } else {
                $error_count++;
            }
        }

        ppv_log("📧 [Weekly Report] Complete! Sent: {$sent_count}, Errors: {$error_count}");
    }

    /**
     * Send report for a single store
     */
    public static function send_report_for_store($store) {
        global $wpdb;

        $store_id = intval($store->id);
        $email = sanitize_email($store->email);

        if (empty($email) || !is_email($email)) {
            ppv_log("⚠️ [Weekly Report] Invalid email for store {$store_id}");
            return false;
        }

        // Determine language from country
        $lang = self::get_language_from_country($store->country);
        $T = self::get_translations($lang);

        // Get statistics for last 7 days
        $stats = self::get_store_stats($store_id);

        // Build email content
        $store_name = $store->company_name ?: $store->name ?: 'Store #' . $store_id;
        $subject = sprintf($T['email_subject'], $store_name);
        $body = self::build_email_body($store, $stats, $T);

        // Send email
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: PunktePass <noreply@punktepass.de>'
        ];

        $sent = wp_mail($email, $subject, $body, $headers);

        if ($sent) {
            ppv_log("✅ [Weekly Report] Sent to {$email} (store {$store_id}, lang: {$lang})");
        } else {
            ppv_log("❌ [Weekly Report] Failed to send to {$email} (store {$store_id})");
        }

        return $sent;
    }

    /**
     * Get language code from country
     */
    private static function get_language_from_country($country) {
        $country = strtolower(trim($country ?? ''));

        // Hungarian
        if (in_array($country, ['hungary', 'magyarország', 'ungarn', 'hu'])) {
            return 'hu';
        }

        // Romanian
        if (in_array($country, ['romania', 'románia', 'rumänien', 'ro'])) {
            return 'ro';
        }

        // Default: German
        return 'de';
    }

    /**
     * Get store statistics for the last 7 days
     */
    private static function get_store_stats($store_id) {
        global $wpdb;

        $today = current_time('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days', strtotime($today)));
        $prev_week_start = date('Y-m-d', strtotime('-14 days', strtotime($today)));
        $prev_week_end = date('Y-m-d', strtotime('-8 days', strtotime($today)));

        $table_points = $wpdb->prefix . 'ppv_points';
        $table_log = $wpdb->prefix . 'ppv_pos_log';
        $table_redeemed = $wpdb->prefix . 'ppv_rewards_redeemed';

        // Include filialen in stats
        $store_ids = $wpdb->get_col($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d OR parent_store_id = %d
        ", $store_id, $store_id));

        if (empty($store_ids)) {
            $store_ids = [$store_id];
        }

        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));

        // This week scans
        $week_scans = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE store_id IN ($placeholders) AND DATE(created) >= %s",
            array_merge($store_ids, [$week_ago])
        ));

        // Previous week scans (for comparison)
        $prev_week_scans = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE store_id IN ($placeholders) AND DATE(created) BETWEEN %s AND %s",
            array_merge($store_ids, [$prev_week_start, $prev_week_end])
        ));

        // Unique users this week
        $unique_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_points WHERE store_id IN ($placeholders) AND DATE(created) >= %s",
            array_merge($store_ids, [$week_ago])
        ));

        // Redemptions this week
        $redemptions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_redeemed WHERE store_id IN ($placeholders) AND DATE(redeemed_at) >= %s",
            array_merge($store_ids, [$week_ago])
        ));

        // Points spent on redemptions
        $points_spent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points_spent), 0) FROM $table_redeemed
             WHERE store_id IN ($placeholders) AND DATE(redeemed_at) >= %s AND status IN ('approved', 'bestätigt')",
            array_merge($store_ids, [$week_ago])
        ));

        // Scanner stats (top 3)
        $scanner_stats = $wpdb->get_results($wpdb->prepare("
            SELECT
                JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.scanner_name')) as scanner_name,
                COUNT(*) as scan_count
            FROM {$table_log}
            WHERE store_id IN ({$placeholders})
              AND type = 'qr_scan'
              AND DATE(created_at) >= %s
              AND JSON_EXTRACT(metadata, '$.scanner_id') IS NOT NULL
            GROUP BY JSON_EXTRACT(metadata, '$.scanner_id')
            ORDER BY scan_count DESC
            LIMIT 3
        ", array_merge($store_ids, [$week_ago])));

        // Suspicious scans this week
        $suspicious = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_suspicious_scans
             WHERE store_id IN ($placeholders) AND DATE(created_at) >= %s",
            array_merge($store_ids, [$week_ago])
        ));

        // NEW: First-time customers this week (users whose first scan at this store was this week)
        $new_customers = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.user_id) FROM $table_points p
             WHERE p.store_id IN ($placeholders)
               AND DATE(p.created) >= %s
               AND NOT EXISTS (
                   SELECT 1 FROM $table_points p2
                   WHERE p2.user_id = p.user_id
                     AND p2.store_id IN ($placeholders)
                     AND DATE(p2.created) < %s
               )",
            array_merge($store_ids, [$week_ago], $store_ids, [$week_ago])
        ));

        // Returning customers (visited before AND this week)
        $returning_customers = $unique_users - $new_customers;
        $returning_percent = $unique_users > 0 ? round(($returning_customers / $unique_users) * 100, 0) : 0;

        // Most active day this week
        $busiest_day_data = $wpdb->get_row($wpdb->prepare(
            "SELECT DAYNAME(created) as day_name, DAYOFWEEK(created) as day_num, COUNT(*) as scan_count
             FROM $table_points
             WHERE store_id IN ($placeholders) AND DATE(created) >= %s
             GROUP BY DAYOFWEEK(created), DAYNAME(created)
             ORDER BY scan_count DESC
             LIMIT 1",
            array_merge($store_ids, [$week_ago])
        ));
        $busiest_day = $busiest_day_data->day_name ?? null;
        $busiest_day_count = $busiest_day_data->scan_count ?? 0;

        // Calculate trend
        $trend = 0;
        if ($prev_week_scans > 0) {
            $trend = round((($week_scans - $prev_week_scans) / $prev_week_scans) * 100, 1);
        }

        return [
            'week_scans' => $week_scans,
            'prev_week_scans' => $prev_week_scans,
            'trend' => $trend,
            'unique_users' => $unique_users,
            'new_customers' => $new_customers,
            'returning_customers' => $returning_customers,
            'returning_percent' => $returning_percent,
            'busiest_day' => $busiest_day,
            'busiest_day_count' => $busiest_day_count,
            'redemptions' => $redemptions,
            'points_spent' => $points_spent,
            'scanner_stats' => $scanner_stats,
            'suspicious' => $suspicious,
            'period' => [
                'start' => $week_ago,
                'end' => $today
            ]
        ];
    }

    /**
     * Get statistics for a single store (no filialen)
     */
    private static function get_single_store_stats($store_id) {
        global $wpdb;

        $today = current_time('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days', strtotime($today)));

        $table_points = $wpdb->prefix . 'ppv_points';
        $table_redeemed = $wpdb->prefix . 'ppv_rewards_redeemed';

        // This week scans for this store only
        $week_scans = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE store_id = %d AND DATE(created) >= %s",
            $store_id, $week_ago
        ));

        // Unique users this week
        $unique_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_points WHERE store_id = %d AND DATE(created) >= %s",
            $store_id, $week_ago
        ));

        // Redemptions this week
        $redemptions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_redeemed WHERE store_id = %d AND DATE(redeemed_at) >= %s",
            $store_id, $week_ago
        ));

        return [
            'week_scans' => $week_scans,
            'unique_users' => $unique_users,
            'redemptions' => $redemptions
        ];
    }

    /**
     * Get filialen for a store
     */
    private static function get_filialen($store_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT id, name, company_name, city
            FROM {$wpdb->prefix}ppv_stores
            WHERE parent_store_id = %d
            ORDER BY name ASC
        ", $store_id));
    }

    /**
     * Get store features status for tips
     */
    private static function get_store_features($store_id) {
        global $wpdb;

        $store = $wpdb->get_row($wpdb->prepare("
            SELECT vip_enabled, vip_fix_enabled, vip_streak_enabled, vip_daily_enabled,
                   google_review_enabled, google_review_url,
                   whatsapp_marketing_enabled, whatsapp_enabled,
                   comeback_enabled, birthday_bonus_enabled
            FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d
        ", $store_id));

        // Count active rewards
        $rewards_count = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_rewards
            WHERE store_id = %d AND active = 1
        ", $store_id));

        // Count scanners/employees
        $scanner_count = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_users
            WHERE vendor_store_id = %d AND user_type = 'scanner' AND active = 1
        ", $store_id));

        // VIP is enabled if ANY of the VIP features are enabled
        $vip_enabled = !empty($store->vip_enabled) ||
                       !empty($store->vip_fix_enabled) ||
                       !empty($store->vip_streak_enabled) ||
                       !empty($store->vip_daily_enabled);

        return [
            'vip_enabled' => $vip_enabled,
            'google_review_enabled' => !empty($store->google_review_enabled) && !empty($store->google_review_url),
            'whatsapp_marketing' => !empty($store->whatsapp_marketing_enabled),
            'whatsapp_enabled' => !empty($store->whatsapp_enabled),
            'comeback_enabled' => !empty($store->comeback_enabled),
            'birthday_enabled' => !empty($store->birthday_bonus_enabled),
            'rewards_count' => $rewards_count,
            'scanner_count' => $scanner_count,
        ];
    }

    /**
     * Generate personalized tips based on store data and features
     */
    private static function generate_tips($stats, $features, $T) {
        $tips = [];

        // Tip 1: VIP not enabled
        if (!$features['vip_enabled']) {
            $tips[] = [
                'icon' => '⭐',
                'text' => $T['tip_vip'],
                'priority' => 1
            ];
        }

        // Tip 2: Low returning customer rate
        if ($stats['returning_percent'] < 30 && $stats['unique_users'] >= 5) {
            $tips[] = [
                'icon' => '🔄',
                'text' => $T['tip_loyalty'],
                'priority' => 2
            ];
        }

        // Tip 3: Google Review not set up
        if (!$features['google_review_enabled']) {
            $tips[] = [
                'icon' => '⭐',
                'text' => $T['tip_google_review'],
                'priority' => 3
            ];
        }

        // Tip 4: No rewards set up
        if ($features['rewards_count'] == 0) {
            $tips[] = [
                'icon' => '🎁',
                'text' => $T['tip_rewards'],
                'priority' => 1
            ];
        }

        // Tip 5: Few rewards (1-2)
        if ($features['rewards_count'] > 0 && $features['rewards_count'] < 3) {
            $tips[] = [
                'icon' => '🎁',
                'text' => $T['tip_more_rewards'],
                'priority' => 4
            ];
        }

        // Tip 6: WhatsApp not enabled
        if (!$features['whatsapp_enabled']) {
            $tips[] = [
                'icon' => '💬',
                'text' => $T['tip_whatsapp'],
                'priority' => 5
            ];
        }

        // Tip 7: Comeback campaign not enabled
        if (!$features['comeback_enabled']) {
            $tips[] = [
                'icon' => '👋',
                'text' => $T['tip_comeback'],
                'priority' => 3
            ];
        }

        // Tip 8: Birthday bonus not enabled
        if (!$features['birthday_enabled']) {
            $tips[] = [
                'icon' => '🎂',
                'text' => $T['tip_birthday'],
                'priority' => 4
            ];
        }

        // Tip 9: High returning rate - congratulate!
        if ($stats['returning_percent'] >= 50 && $stats['unique_users'] >= 5) {
            $tips[] = [
                'icon' => '🏆',
                'text' => sprintf($T['tip_great_loyalty'], $stats['returning_percent']),
                'priority' => 0
            ];
        }

        // Tip 8: Growth trend - congratulate!
        if ($stats['trend'] >= 20) {
            $tips[] = [
                'icon' => '📈',
                'text' => sprintf($T['tip_great_growth'], '+' . $stats['trend'] . '%'),
                'priority' => 0
            ];
        }

        // Sort by priority and limit to 2 tips
        usort($tips, fn($a, $b) => $a['priority'] - $b['priority']);
        return array_slice($tips, 0, 2);
    }

    /**
     * Build HTML email body
     */
    private static function build_email_body($store, $stats, $T) {
        $store_name = $store->company_name ?: $store->name ?: 'Store';
        $trend_icon = $stats['trend'] >= 0 ? '📈' : '📉';
        $trend_color = $stats['trend'] >= 0 ? '#10b981' : '#ef4444';
        $trend_text = ($stats['trend'] >= 0 ? '+' : '') . $stats['trend'] . '%';

        // Determine domain based on country
        $country = strtolower(trim($store->country ?? ''));
        $domain = 'punktepass.de'; // default
        if (in_array($country, ['romania', 'románia', 'rumänien', 'ro'])) {
            $domain = 'punktepass.ro';
        }

        // Scanner list HTML
        $scanner_html = '';
        if (!empty($stats['scanner_stats'])) {
            $scanner_html = '<table style="width:100%; border-collapse: collapse; margin-top: 10px;">';
            $medals = ['🥇', '🥈', '🥉'];
            foreach ($stats['scanner_stats'] as $i => $scanner) {
                $medal = $medals[$i] ?? '';
                $name = esc_html($scanner->scanner_name ?: 'Scanner');
                $count = intval($scanner->scan_count);
                $scanner_html .= "<tr style='border-bottom: 1px solid #eee;'>
                    <td style='padding: 8px;'>{$medal} {$name}</td>
                    <td style='padding: 8px; text-align: right; font-weight: bold;'>{$count}</td>
                </tr>";
            }
            $scanner_html .= '</table>';
        } else {
            $scanner_html = '<p style="color: #6b7280; font-style: italic;">' . $T['no_scanner_data'] . '</p>';
        }

        // Suspicious scans - always show (even if 0)
        $suspicious_url = site_url('/statistik?tab=suspicious');
        if ($stats['suspicious'] > 0) {
            $suspicious_html = '<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin: 15px 0; border-radius: 4px;">
                <strong>⚠️ ' . $T['suspicious_scans'] . ':</strong> ' . $stats['suspicious'] . '
                <a href="' . esc_url($suspicious_url) . '" style="margin-left: 10px; color: #d97706; text-decoration: underline;">' . $T['view_details'] . ' →</a>
            </div>';
        } else {
            $suspicious_html = '<div style="background: #f0fdf4; border-left: 4px solid #10b981; padding: 12px; margin: 15px 0; border-radius: 4px;">
                <strong>✅ ' . $T['suspicious_scans'] . ':</strong> 0
            </div>';
        }

        // Filiale breakdown (if store has filialen)
        $filiale_html = '';
        $filialen = self::get_filialen($store->id);
        if (!empty($filialen)) {
            $filiale_html = '<div style="background: #f8fafc; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px; font-size: 16px; color: #374151;">🏪 ' . $T['filiale_breakdown'] . '</h3>
                <table style="width:100%; border-collapse: collapse;">';

            // Main store stats first
            $main_stats = self::get_single_store_stats($store->id);
            $main_name = esc_html($store->name ?: $store->company_name ?: $T['main_store']);
            // Add city if available
            if (!empty($store->city)) {
                $main_name .= ' – ' . esc_html($store->city);
            }
            $filiale_html .= '<tr style="border-bottom: 2px solid #e5e7eb; background: #f0f9ff;">
                <td style="padding: 10px; font-weight: 600;">📍 ' . $main_name . '</td>
                <td style="padding: 10px; text-align: center;"><strong>' . $main_stats['week_scans'] . '</strong><br><span style="font-size: 11px; color: #6b7280;">' . $T['total_scans'] . '</span></td>
                <td style="padding: 10px; text-align: center;"><strong>' . $main_stats['unique_users'] . '</strong><br><span style="font-size: 11px; color: #6b7280;">' . $T['unique_customers'] . '</span></td>
                <td style="padding: 10px; text-align: center;"><strong>' . $main_stats['redemptions'] . '</strong><br><span style="font-size: 11px; color: #6b7280;">' . $T['redemptions'] . '</span></td>
            </tr>';

            // Each filiale
            foreach ($filialen as $filiale) {
                $f_stats = self::get_single_store_stats($filiale->id);
                $f_name = esc_html($filiale->name ?: $filiale->company_name ?: 'Filiale #' . $filiale->id);
                // Add city if available
                if (!empty($filiale->city)) {
                    $f_name .= ' – ' . esc_html($filiale->city);
                }
                $filiale_html .= '<tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 10px;">🏠 ' . $f_name . '</td>
                    <td style="padding: 10px; text-align: center;"><strong>' . $f_stats['week_scans'] . '</strong></td>
                    <td style="padding: 10px; text-align: center;"><strong>' . $f_stats['unique_users'] . '</strong></td>
                    <td style="padding: 10px; text-align: center;"><strong>' . $f_stats['redemptions'] . '</strong></td>
                </tr>';
            }

            $filiale_html .= '</table></div>';
        }

        // Personalized tips section
        $tips_html = '';
        $features = self::get_store_features($store->id);
        $tips = self::generate_tips($stats, $features, $T);
        if (!empty($tips)) {
            $tips_html = '<div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px; font-size: 16px; color: #92400e;">💡 ' . $T['tips_title'] . '</h3>';
            foreach ($tips as $tip) {
                $tips_html .= '<div style="background: white; border-radius: 8px; padding: 12px; margin-bottom: 10px; display: flex; align-items: flex-start; gap: 10px;">
                    <span style="font-size: 20px;">' . $tip['icon'] . '</span>
                    <span style="color: #78350f; font-size: 14px;">' . $tip['text'] . '</span>
                </div>';
            }
            $tips_html .= '</div>';
        }

        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #f3f4f6; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">

        <!-- Header -->
        <div style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; padding: 30px; text-align: center;">
            <h1 style="margin: 0; font-size: 24px;">📊 ' . $T['weekly_report'] . '</h1>
            <p style="margin: 10px 0 0; opacity: 0.9;">' . esc_html($store_name) . '</p>
            <p style="margin: 5px 0 0; font-size: 13px; opacity: 0.8;">' . $stats['period']['start'] . ' - ' . $stats['period']['end'] . '</p>
        </div>

        <!-- Main Stats -->
        <div style="padding: 25px;">
            <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">

                <!-- Total Scans -->
                <div style="flex: 1; min-width: 120px; background: #f8fafc; border-radius: 10px; padding: 15px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #6366f1;">' . number_format($stats['week_scans']) . '</div>
                    <div style="font-size: 13px; color: #6b7280;">' . $T['total_scans'] . '</div>
                    <div style="font-size: 12px; color: ' . $trend_color . '; margin-top: 4px;">' . $trend_icon . ' ' . $trend_text . '</div>
                </div>

                <!-- Unique Users -->
                <div style="flex: 1; min-width: 120px; background: #f8fafc; border-radius: 10px; padding: 15px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #10b981;">' . number_format($stats['unique_users']) . '</div>
                    <div style="font-size: 13px; color: #6b7280;">' . $T['unique_customers'] . '</div>
                </div>

                <!-- Redemptions -->
                <div style="flex: 1; min-width: 120px; background: #f8fafc; border-radius: 10px; padding: 15px; text-align: center;">
                    <div style="font-size: 28px; font-weight: 700; color: #f59e0b;">' . number_format($stats['redemptions']) . '</div>
                    <div style="font-size: 13px; color: #6b7280;">' . $T['redemptions'] . '</div>
                </div>

            </div>

            <!-- Customer Insights -->
            <div style="background: #f8fafc; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px; font-size: 16px; color: #374151;">📊 ' . $T['customer_insights'] . '</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <!-- New Customers -->
                    <div style="flex: 1; min-width: 100px; background: white; border-radius: 8px; padding: 12px; text-align: center; border: 1px solid #e5e7eb;">
                        <div style="font-size: 22px; font-weight: 700; color: #8b5cf6;">🆕 ' . number_format($stats['new_customers']) . '</div>
                        <div style="font-size: 12px; color: #6b7280;">' . $T['new_customers'] . '</div>
                    </div>
                    <!-- Returning Customers -->
                    <div style="flex: 1; min-width: 100px; background: white; border-radius: 8px; padding: 12px; text-align: center; border: 1px solid #e5e7eb;">
                        <div style="font-size: 22px; font-weight: 700; color: #ec4899;">🔄 ' . $stats['returning_percent'] . '%</div>
                        <div style="font-size: 12px; color: #6b7280;">' . $T['returning_customers'] . '</div>
                    </div>
                    <!-- Busiest Day -->
                    <div style="flex: 1; min-width: 100px; background: white; border-radius: 8px; padding: 12px; text-align: center; border: 1px solid #e5e7eb;">
                        <div style="font-size: 18px; font-weight: 700; color: #0ea5e9;">📅 ' . ($stats['busiest_day'] ? ($T['days'][$stats['busiest_day']] ?? $stats['busiest_day']) : '-') . '</div>
                        <div style="font-size: 12px; color: #6b7280;">' . $T['busiest_day'] . '</div>
                        <div style="font-size: 11px; color: #9ca3af;">' . number_format($stats['busiest_day_count']) . ' ' . $T['scans_text'] . '</div>
                    </div>
                </div>
            </div>

            ' . $filiale_html . '

            ' . $suspicious_html . '

            <!-- Scanner Performance -->
            <div style="background: #f8fafc; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 10px; font-size: 16px; color: #374151;">👤 ' . $T['top_employees'] . '</h3>
                ' . $scanner_html . '
            </div>

            ' . $tips_html . '

            <!-- Summary -->
            <div style="background: #f0fdf4; border-radius: 10px; padding: 15px; text-align: center;">
                <p style="margin: 0; color: #166534;">
                    ' . sprintf($T['summary_text'], number_format($stats['week_scans']), number_format($stats['points_spent'])) . '
                </p>
            </div>

        </div>

        <!-- Footer -->
        <div style="background: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;">
            <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                ' . $T['footer_text'] . '
            </p>
            <p style="margin: 8px 0 0; font-size: 11px; color: #9ca3af;">
                <a href="https://' . $domain . '" style="color: #6366f1; text-decoration: none;">' . $domain . '</a>
            </p>
        </div>

    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Get translations for email
     */
    private static function get_translations($lang) {
        $translations = [
            'de' => [
                'email_subject' => 'Wöchentlicher Bericht - %s',
                'weekly_report' => 'Wöchentlicher Bericht',
                'total_scans' => 'Scans gesamt',
                'unique_customers' => 'Kunden',
                'redemptions' => 'Einlösungen',
                'top_employees' => 'Top Mitarbeiter',
                'no_scanner_data' => 'Keine Mitarbeiter-Daten vorhanden',
                'suspicious_scans' => 'Verdächtige Scans',
                'view_details' => 'Anzeigen',
                'summary_text' => 'Diese Woche: %s Scans, %s Punkte eingelöst',
                'footer_text' => 'Dieser Bericht wird automatisch jeden Montag versendet.',
                'filiale_breakdown' => 'Statistiken nach Filiale',
                'main_store' => 'Hauptgeschäft',
                'customer_insights' => 'Kundenanalyse',
                'new_customers' => 'Neue Kunden',
                'returning_customers' => 'Stammkunden',
                'busiest_day' => 'Aktivster Tag',
                'scans_text' => 'Scans',
                'days' => ['Sunday' => 'Sonntag', 'Monday' => 'Montag', 'Tuesday' => 'Dienstag', 'Wednesday' => 'Mittwoch', 'Thursday' => 'Donnerstag', 'Friday' => 'Freitag', 'Saturday' => 'Samstag'],
                'tips_title' => 'Tipps für Sie',
                'tip_vip' => 'Aktivieren Sie VIP-Stufen! Belohnen Sie Ihre treuesten Kunden mit Bonuspunkten.',
                'tip_loyalty' => 'Ihre Stammkundenrate ist niedrig. Erstellen Sie attraktivere Prämien, um Kunden zurückzubringen!',
                'tip_google_review' => 'Richten Sie automatische Google-Bewertungsanfragen ein, um Ihre Online-Präsenz zu stärken.',
                'tip_rewards' => 'Erstellen Sie Prämien! Kunden sind motivierter, wenn sie wissen, wofür sie Punkte sammeln.',
                'tip_more_rewards' => 'Fügen Sie mehr Prämien hinzu! Vielfalt hält Kunden engagiert.',
                'tip_whatsapp' => 'Aktivieren Sie WhatsApp-Benachrichtigungen für bessere Kundenkommunikation.',
                'tip_comeback' => 'Aktivieren Sie die Comeback-Kampagne! Belohnen Sie Kunden, die lange nicht da waren.',
                'tip_birthday' => 'Aktivieren Sie den Geburtstags-Bonus! Überraschen Sie Kunden an ihrem Ehrentag.',
                'tip_great_loyalty' => 'Fantastisch! %s%% Ihrer Kunden kehren zurück. Weiter so!',
                'tip_great_growth' => 'Beeindruckend! %s Wachstum diese Woche. Ihr Geschäft boomt!',
            ],
            'hu' => [
                'email_subject' => 'Heti jelentés - %s',
                'weekly_report' => 'Heti jelentés',
                'total_scans' => 'Összes scan',
                'unique_customers' => 'Vásárlók',
                'redemptions' => 'Beváltások',
                'top_employees' => 'Top alkalmazottak',
                'no_scanner_data' => 'Nincs alkalmazott adat',
                'suspicious_scans' => 'Gyanús scanek',
                'view_details' => 'Megtekintés',
                'summary_text' => 'Ezen a héten: %s scan, %s pont beváltva',
                'footer_text' => 'Ez a jelentés automatikusan kerül kiküldésre minden hétfőn.',
                'filiale_breakdown' => 'Statisztikák filiálék szerint',
                'main_store' => 'Főüzlet',
                'customer_insights' => 'Vásárlói elemzés',
                'new_customers' => 'Új vásárlók',
                'returning_customers' => 'Visszatérő',
                'busiest_day' => 'Legaktívabb nap',
                'scans_text' => 'scan',
                'days' => ['Sunday' => 'Vasárnap', 'Monday' => 'Hétfő', 'Tuesday' => 'Kedd', 'Wednesday' => 'Szerda', 'Thursday' => 'Csütörtök', 'Friday' => 'Péntek', 'Saturday' => 'Szombat'],
                'tips_title' => 'Tippek Önnek',
                'tip_vip' => 'Aktiváld a VIP szinteket! Jutalmazd meg a leghűségesebb vásárlóidat bónusz pontokkal.',
                'tip_loyalty' => 'Alacsony a visszatérő vásárlók aránya. Készíts vonzóbb jutalmakat!',
                'tip_google_review' => 'Állítsd be az automatikus Google értékelés kérést az online jelenlét erősítéséhez.',
                'tip_rewards' => 'Hozz létre jutalmakat! A vásárlók motiváltabbak, ha tudják, miért gyűjtenek pontokat.',
                'tip_more_rewards' => 'Adj hozzá több jutalmat! A változatosság fenntartja a vásárlók érdeklődését.',
                'tip_whatsapp' => 'Aktiváld a WhatsApp értesítéseket a jobb ügyfélkommunikációért.',
                'tip_comeback' => 'Aktiváld a Comeback kampányt! Jutalmazd a régóta távol lévő vásárlókat.',
                'tip_birthday' => 'Aktiváld a születésnapi bónuszt! Lepd meg a vásárlókat a nagy napjukon.',
                'tip_great_loyalty' => 'Fantasztikus! Vásárlóid %s%%-a visszatér. Így tovább!',
                'tip_great_growth' => 'Lenyűgöző! %s növekedés ezen a héten. Az üzleted virágzik!',
            ],
            'ro' => [
                'email_subject' => 'Raport săptămânal - %s',
                'weekly_report' => 'Raport săptămânal',
                'total_scans' => 'Scanări totale',
                'unique_customers' => 'Clienți',
                'redemptions' => 'Răscumpărări',
                'top_employees' => 'Top angajați',
                'no_scanner_data' => 'Nu există date despre angajați',
                'suspicious_scans' => 'Scanări suspecte',
                'view_details' => 'Vizualizare',
                'summary_text' => 'Săptămâna aceasta: %s scanări, %s puncte răscumpărate',
                'footer_text' => 'Acest raport este trimis automat în fiecare luni.',
                'filiale_breakdown' => 'Statistici pe filiale',
                'main_store' => 'Magazin principal',
                'customer_insights' => 'Analiza clienților',
                'new_customers' => 'Clienți noi',
                'returning_customers' => 'Clienți fideli',
                'busiest_day' => 'Cea mai activă zi',
                'scans_text' => 'scanări',
                'days' => ['Sunday' => 'Duminică', 'Monday' => 'Luni', 'Tuesday' => 'Marți', 'Wednesday' => 'Miercuri', 'Thursday' => 'Joi', 'Friday' => 'Vineri', 'Saturday' => 'Sâmbătă'],
                'tips_title' => 'Sfaturi pentru dumneavoastră',
                'tip_vip' => 'Activați nivelurile VIP! Recompensați clienții fideli cu puncte bonus.',
                'tip_loyalty' => 'Rata clienților fideli este scăzută. Creați recompense mai atractive!',
                'tip_google_review' => 'Configurați solicitări automate de recenzii Google pentru a vă întări prezența online.',
                'tip_rewards' => 'Creați recompense! Clienții sunt mai motivați când știu pentru ce colectează puncte.',
                'tip_more_rewards' => 'Adăugați mai multe recompense! Varietatea menține clienții implicați.',
                'tip_whatsapp' => 'Activați notificările WhatsApp pentru o comunicare mai bună cu clienții.',
                'tip_comeback' => 'Activați campania Comeback! Recompensați clienții care nu au mai venit de mult.',
                'tip_birthday' => 'Activați bonusul de ziua de naștere! Surprindeți clienții în ziua lor specială.',
                'tip_great_loyalty' => 'Fantastic! %s%% din clienții dvs. revin. Continuați așa!',
                'tip_great_growth' => 'Impresionant! %s creștere săptămâna aceasta. Afacerea dvs. prosperă!',
            ],
        ];

        return $translations[$lang] ?? $translations['de'];
    }

    /**
     * AJAX handler for testing (admin only)
     * Optional: target_email to override destination
     */
    public static function ajax_test_report() {
        ppv_log("📧 [Weekly Report TEST] AJAX called, user ID: " . get_current_user_id() . ", can admin: " . (current_user_can('manage_options') ? 'yes' : 'no'));

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => 'Unauthorized',
                'user_id' => get_current_user_id(),
                'is_admin' => current_user_can('manage_options')
            ]);
        }

        $store_id = intval($_POST['store_id'] ?? 0);
        if (!$store_id) {
            wp_send_json_error(['message' => 'Missing store_id']);
        }

        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            wp_send_json_error(['message' => 'Store not found']);
        }

        // Optional: override email for testing
        $target_email = sanitize_email($_POST['target_email'] ?? '');
        if (!empty($target_email) && is_email($target_email)) {
            $original_email = $store->email;
            $store->email = $target_email;
            ppv_log("📧 [Weekly Report TEST] Overriding email from {$original_email} to {$target_email}");
        }

        // Get stats for debugging
        $stats = self::get_store_stats($store_id);

        $result = self::send_report_for_store($store);

        if ($result) {
            wp_send_json_success([
                'message' => 'Report sent to ' . $store->email,
                'store_id' => $store_id,
                'email' => $store->email,
                'stats' => $stats
            ]);
        } else {
            // Get more error info
            global $phpmailer;
            $error_info = '';
            if (isset($phpmailer) && isset($phpmailer->ErrorInfo)) {
                $error_info = $phpmailer->ErrorInfo;
            }
            wp_send_json_error([
                'message' => 'Failed to send report',
                'store_id' => $store_id,
                'email' => $store->email,
                'mail_error' => $error_info,
                'stats' => $stats
            ]);
        }
    }
}

// Register weekly cron schedule
add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['weekly'])) {
        $schedules['weekly'] = [
            'interval' => 7 * DAY_IN_SECONDS,
            'display'  => __('Einmal pro Woche')
        ];
    }
    return $schedules;
});

// Initialize
add_action('plugins_loaded', function() {
    if (class_exists('PPV_Weekly_Report')) {
        PPV_Weekly_Report::hooks();
    }
});
