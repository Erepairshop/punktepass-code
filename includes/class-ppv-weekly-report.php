<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - Weekly Report Email System
 * Sends weekly scan statistics to store owners every Monday morning
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
            SELECT id, name, email, country, company_name
            FROM {$wpdb->prefix}ppv_stores
            WHERE email IS NOT NULL
              AND email != ''
              AND subscription_status IN ('active', 'trial')
              AND parent_store_id IS NULL
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
     * Build HTML email body
     */
    private static function build_email_body($store, $stats, $T) {
        $store_name = $store->company_name ?: $store->name ?: 'Store';
        $trend_icon = $stats['trend'] >= 0 ? '📈' : '📉';
        $trend_color = $stats['trend'] >= 0 ? '#10b981' : '#ef4444';
        $trend_text = ($stats['trend'] >= 0 ? '+' : '') . $stats['trend'] . '%';

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

        // Suspicious scans warning
        $suspicious_html = '';
        if ($stats['suspicious'] > 0) {
            $suspicious_html = '<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin: 15px 0; border-radius: 4px;">
                <strong>⚠️ ' . $T['suspicious_scans'] . ':</strong> ' . $stats['suspicious'] . '
            </div>';
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

            ' . $suspicious_html . '

            <!-- Scanner Performance -->
            <div style="background: #f8fafc; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 10px; font-size: 16px; color: #374151;">👤 ' . $T['top_employees'] . '</h3>
                ' . $scanner_html . '
            </div>

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
                <a href="https://punktepass.de" style="color: #6366f1; text-decoration: none;">punktepass.de</a>
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
                'summary_text' => 'Diese Woche: %s Scans, %s Punkte eingelöst',
                'footer_text' => 'Dieser Bericht wird automatisch jeden Montag versendet.',
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
                'summary_text' => 'Ezen a héten: %s scan, %s pont beváltva',
                'footer_text' => 'Ez a jelentés automatikusan kerül kiküldésre minden hétfőn.',
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
                'summary_text' => 'Săptămâna aceasta: %s scanări, %s puncte răscumpărate',
                'footer_text' => 'Acest raport este trimis automat în fiecare luni.',
            ],
        ];

        return $translations[$lang] ?? $translations['de'];
    }

    /**
     * AJAX handler for testing (admin only)
     * Optional: target_email to override destination
     */
    public static function ajax_test_report() {
        if (!current_user_can('administrator')) {
            wp_send_json_error(['message' => 'Unauthorized']);
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

        $result = self::send_report_for_store($store);

        if ($result) {
            wp_send_json_success(['message' => 'Report sent to ' . $store->email]);
        } else {
            wp_send_json_error(['message' => 'Failed to send report']);
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
