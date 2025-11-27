<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - Google Review Request System
 * Automatically sends Google review requests to loyal customers
 * when they reach a configurable point threshold.
 *
 * Configuration (per store in Marketing tab):
 * - google_review_enabled: Toggle feature on/off
 * - google_review_url: Store's Google review link
 * - google_review_threshold: Points threshold to trigger request
 * - google_review_frequency: 'once', 'monthly', 'quarterly'
 */
class PPV_Google_Review_Request {

    public static function hooks() {
        add_action('init', [__CLASS__, 'schedule_cron']);
        add_action('ppv_google_review_request', [__CLASS__, 'process_all_stores']);

        // Manual trigger for testing (admin only)
        add_action('wp_ajax_ppv_test_google_review', [__CLASS__, 'ajax_test_review']);
    }

    /**
     * Schedule daily cron event (every day at 10:00 AM)
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled('ppv_google_review_request')) {
            // Run daily at 10:00 AM server time
            $next_run = strtotime('today 10:00:00');
            if ($next_run < time()) {
                $next_run = strtotime('tomorrow 10:00:00');
            }
            wp_schedule_event($next_run, 'daily', 'ppv_google_review_request');
            ppv_log("[Google Review] Cron scheduled for: " . date('Y-m-d H:i:s', $next_run));
        }
    }

    /**
     * Process all stores with Google Review enabled
     */
    public static function process_all_stores() {
        global $wpdb;

        ppv_log("[Google Review] Starting daily processing...");

        // Get all stores with Google Review enabled and valid URL
        $stores = $wpdb->get_results("
            SELECT id, name, company_name, country,
                   google_review_url, google_review_threshold, google_review_frequency
            FROM {$wpdb->prefix}ppv_stores
            WHERE google_review_enabled = 1
              AND google_review_url IS NOT NULL
              AND google_review_url != ''
              AND subscription_status IN ('active', 'trial')
              AND parent_store_id IS NULL
        ");

        $total_sent = 0;
        $total_errors = 0;

        foreach ($stores as $store) {
            $result = self::process_store($store);
            $total_sent += $result['sent'];
            $total_errors += $result['errors'];
        }

        ppv_log("[Google Review] Complete! Total sent: {$total_sent}, Total errors: {$total_errors}");
    }

    /**
     * Process a single store - find eligible users and send requests
     */
    public static function process_store($store) {
        global $wpdb;

        $store_id = intval($store->id);
        $threshold = intval($store->google_review_threshold ?? 100);
        $frequency = $store->google_review_frequency ?? 'once';
        $review_url = $store->google_review_url;

        $sent = 0;
        $errors = 0;

        ppv_log("[Google Review] Processing store {$store_id} (threshold: {$threshold}, frequency: {$frequency})");

        // Include filialen in point calculations
        $store_ids = $wpdb->get_col($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d OR parent_store_id = %d
        ", $store_id, $store_id));

        if (empty($store_ids)) {
            $store_ids = [$store_id];
        }

        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));

        // Calculate frequency date filter
        $frequency_filter = self::get_frequency_sql($frequency);

        // Find eligible users:
        // 1. Have total points >= threshold for this store
        // 2. Have NOT been sent a request within the frequency period (or never)
        // 3. Have a valid email address
        $query = $wpdb->prepare("
            SELECT
                u.id as user_id,
                u.email,
                u.first_name,
                u.display_name,
                COALESCE(SUM(p.points), 0) as total_points,
                grr.last_request_at
            FROM {$wpdb->prefix}ppv_users u
            INNER JOIN {$wpdb->prefix}ppv_points p ON p.user_id = u.id
            LEFT JOIN {$wpdb->prefix}ppv_google_review_requests grr
                ON grr.user_id = u.id AND grr.store_id = %d
            WHERE p.store_id IN ({$placeholders})
              AND u.email IS NOT NULL
              AND u.email != ''
              AND u.email LIKE '%@%'
            GROUP BY u.id, u.email, u.first_name, u.display_name, grr.last_request_at
            HAVING total_points >= %d
               AND {$frequency_filter}
        ", array_merge([$store_id], $store_ids, [$threshold]));

        $eligible_users = $wpdb->get_results($query);

        ppv_log("[Google Review] Store {$store_id}: Found " . count($eligible_users) . " eligible users");

        foreach ($eligible_users as $user) {
            $result = self::send_review_request($store, $user, $review_url);
            if ($result) {
                $sent++;
                // Record the request
                self::record_request($store_id, $user->user_id);
            } else {
                $errors++;
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Get SQL condition for frequency check
     */
    private static function get_frequency_sql($frequency) {
        switch ($frequency) {
            case 'monthly':
                return "(grr.last_request_at IS NULL OR grr.last_request_at < DATE_SUB(NOW(), INTERVAL 30 DAY))";
            case 'quarterly':
                return "(grr.last_request_at IS NULL OR grr.last_request_at < DATE_SUB(NOW(), INTERVAL 90 DAY))";
            case 'once':
            default:
                return "grr.last_request_at IS NULL";
        }
    }

    /**
     * Send review request email to a user
     */
    public static function send_review_request($store, $user, $review_url) {
        $email = sanitize_email($user->email);

        if (empty($email) || !is_email($email)) {
            ppv_log("[Google Review] Invalid email for user {$user->user_id}");
            return false;
        }

        // Determine language from store country
        $lang = self::get_language_from_country($store->country);
        $T = self::get_translations($lang);

        // Build email
        $store_name = $store->company_name ?: $store->name ?: 'Store';
        $user_name = $user->first_name ?: $user->display_name ?: '';
        $subject = sprintf($T['email_subject'], $store_name);
        $body = self::build_email_body($store_name, $user_name, $review_url, $T);

        // Send email
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $store_name . ' <noreply@punktepass.de>'
        ];

        $sent = wp_mail($email, $subject, $body, $headers);

        if ($sent) {
            ppv_log("[Google Review] Sent to {$email} (store {$store->id}, user {$user->user_id})");
        } else {
            ppv_log("[Google Review] Failed to send to {$email} (store {$store->id}, user {$user->user_id})");
        }

        return $sent;
    }

    /**
     * Record that a review request was sent
     */
    private static function record_request($store_id, $user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'ppv_google_review_requests';

        // Check if record exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE store_id = %d AND user_id = %d",
            $store_id, $user_id
        ));

        if ($existing) {
            // Update existing record
            $wpdb->update(
                $table,
                [
                    'last_request_at' => current_time('mysql'),
                    'request_count' => new \stdClass() // Will use raw SQL
                ],
                ['store_id' => $store_id, 'user_id' => $user_id],
                ['%s'],
                ['%d', '%d']
            );
            // Increment count separately
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET request_count = request_count + 1 WHERE store_id = %d AND user_id = %d",
                $store_id, $user_id
            ));
        } else {
            // Insert new record
            $wpdb->insert($table, [
                'store_id' => $store_id,
                'user_id' => $user_id,
                'last_request_at' => current_time('mysql'),
                'request_count' => 1
            ], ['%d', '%d', '%s', '%d']);
        }
    }

    /**
     * Get language code from country
     */
    private static function get_language_from_country($country) {
        $country = strtolower(trim($country ?? ''));

        if (in_array($country, ['hungary', 'magyarorszag', 'ungarn', 'hu'])) {
            return 'hu';
        }

        if (in_array($country, ['romania', 'romania', 'rumanien', 'ro'])) {
            return 'ro';
        }

        return 'de';
    }

    /**
     * Build HTML email body
     */
    private static function build_email_body($store_name, $user_name, $review_url, $T) {
        $greeting = !empty($user_name)
            ? sprintf($T['greeting_name'], esc_html($user_name))
            : $T['greeting_generic'];

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
        <div style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; padding: 30px; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 10px;">⭐</div>
            <h1 style="margin: 0; font-size: 24px; color: #1f2937;">' . esc_html($T['email_title']) . '</h1>
        </div>

        <!-- Content -->
        <div style="padding: 30px;">
            <p style="font-size: 16px; color: #374151; margin: 0 0 20px;">
                ' . $greeting . '
            </p>

            <p style="font-size: 16px; color: #374151; margin: 0 0 20px;">
                ' . sprintf($T['thank_you_text'], '<strong>' . esc_html($store_name) . '</strong>') . '
            </p>

            <p style="font-size: 16px; color: #374151; margin: 0 0 25px;">
                ' . esc_html($T['review_request_text']) . '
            </p>

            <!-- CTA Button -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . esc_url($review_url) . '"
                   style="display: inline-block; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
                          color: #1f2937; text-decoration: none; padding: 16px 40px; border-radius: 8px;
                          font-size: 18px; font-weight: 600; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.4);">
                    ⭐ ' . esc_html($T['button_text']) . '
                </a>
            </div>

            <p style="font-size: 14px; color: #6b7280; margin: 20px 0 0; text-align: center;">
                ' . esc_html($T['takes_only_text']) . '
            </p>
        </div>

        <!-- Footer -->
        <div style="background: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;">
            <p style="margin: 0; font-size: 14px; color: #6b7280;">
                ' . esc_html($T['footer_thanks']) . '
            </p>
            <p style="margin: 8px 0 0; font-size: 13px; color: #9ca3af;">
                ' . esc_html($store_name) . '
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
                'email_subject' => 'Ihre Meinung ist uns wichtig! - %s',
                'email_title' => 'Ihre Bewertung zahlt sich aus!',
                'greeting_name' => 'Hallo %s,',
                'greeting_generic' => 'Hallo,',
                'thank_you_text' => 'vielen Dank, dass Sie treuer Kunde von %s sind!',
                'review_request_text' => 'Wir wurden uns sehr uber Ihre ehrliche Bewertung auf Google freuen. Ihre Meinung hilft anderen Kunden, uns zu finden.',
                'button_text' => 'Jetzt bewerten',
                'takes_only_text' => 'Es dauert nur 1 Minute!',
                'footer_thanks' => 'Vielen Dank fur Ihre Unterstutzung!',
            ],
            'hu' => [
                'email_subject' => 'A velemenye fontos nekunk! - %s',
                'email_title' => 'Az ertekelese szamit!',
                'greeting_name' => 'Kedves %s,',
                'greeting_generic' => 'Kedves Vasarlonk,',
                'thank_you_text' => 'koszonjuk, hogy husseges vasarloja a %s-nak!',
                'review_request_text' => 'Nagyon orulnank, ha megosztana velunk oszinte velemenyet a Google-on. Az On ertekelse segit masoknak megtalalini minket.',
                'button_text' => 'Ertekeles irasa',
                'takes_only_text' => 'Mindossze 1 percet vesz igenybe!',
                'footer_thanks' => 'Koszonjuk a tamogatasat!',
            ],
            'ro' => [
                'email_subject' => 'Parerea dvs. conteaza! - %s',
                'email_title' => 'Recenzia dvs. conteaza!',
                'greeting_name' => 'Buna ziua %s,',
                'greeting_generic' => 'Buna ziua,',
                'thank_you_text' => 'va multumim ca sunteti client fidel al %s!',
                'review_request_text' => 'Ne-ar face mare placere sa ne lasati o recenzie sincera pe Google. Parerea dvs. ii ajuta pe altii sa ne gaseasca.',
                'button_text' => 'Lasati o recenzie',
                'takes_only_text' => 'Dureaza doar 1 minut!',
                'footer_thanks' => 'Va multumim pentru sprijin!',
            ],
        ];

        return $translations[$lang] ?? $translations['de'];
    }

    /**
     * AJAX handler for testing (admin only)
     */
    public static function ajax_test_review() {
        if (!current_user_can('manage_options')) {
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

        if (empty($store->google_review_enabled)) {
            wp_send_json_error(['message' => 'Google Review not enabled for this store']);
        }

        if (empty($store->google_review_url)) {
            wp_send_json_error(['message' => 'Google Review URL not configured']);
        }

        // Optional: override email for testing
        $target_email = sanitize_email($_POST['target_email'] ?? '');

        if (!empty($target_email) && is_email($target_email)) {
            // Send test to specific email
            $test_user = (object) [
                'user_id' => 0,
                'email' => $target_email,
                'first_name' => 'Test',
                'display_name' => 'Test User'
            ];

            $result = self::send_review_request($store, $test_user, $store->google_review_url);

            if ($result) {
                wp_send_json_success([
                    'message' => 'Test review request sent to ' . $target_email,
                    'store_id' => $store_id
                ]);
            } else {
                wp_send_json_error(['message' => 'Failed to send test email']);
            }
        } else {
            // Process store normally
            $result = self::process_store($store);

            wp_send_json_success([
                'message' => "Processed store {$store_id}",
                'sent' => $result['sent'],
                'errors' => $result['errors']
            ]);
        }
    }
}

// Initialize hooks directly (required for AJAX to work properly)
PPV_Google_Review_Request::hooks();
