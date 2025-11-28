<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - Google Review Request System
 * Automatically sends Google review requests to loyal customers
 * when they reach a configurable lifetime point threshold.
 *
 * Configuration (per store in Marketing tab):
 * - google_review_enabled: Toggle feature on/off
 * - google_review_url: Store's Google review link
 * - google_review_threshold: Lifetime points threshold to trigger request
 * - google_review_bonus_points: Points awarded on next scan after review request
 *
 * Notification priority:
 * 1. WhatsApp (if user has whatsapp_consent=1 and phone_number)
 * 2. Email (if user has marketing_emails=1 and valid email)
 *
 * Language: Uses user's saved language preference (from browser)
 */
class PPV_Google_Review_Request {

    public static function hooks() {
        add_action('init', [__CLASS__, 'schedule_cron']);
        add_action('ppv_google_review_request', [__CLASS__, 'process_all_stores']);
    }

    /**
     * Schedule daily cron event (every day at 10:00 AM)
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled('ppv_google_review_request')) {
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
                   google_review_url, google_review_threshold,
                   google_review_bonus_points, whatsapp_enabled
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
        $bonus_points = intval($store->google_review_bonus_points ?? 5);
        $review_url = $store->google_review_url;

        $sent = 0;
        $errors = 0;

        ppv_log("[Google Review] Processing store {$store_id} (threshold: {$threshold}, bonus: {$bonus_points})");

        // Include filialen in point calculations
        $store_ids = $wpdb->get_col($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d OR parent_store_id = %d
        ", $store_id, $store_id));

        if (empty($store_ids)) {
            $store_ids = [$store_id];
        }

        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));

        // Find eligible users:
        // 1. Have LIFETIME points >= threshold for this store
        // 2. Have NEVER been sent a request (once per user)
        // 3. Have either WhatsApp consent OR marketing_emails enabled
        $query = $wpdb->prepare("
            SELECT
                u.id as user_id,
                u.email,
                u.first_name,
                u.display_name,
                u.phone_number,
                u.whatsapp_consent,
                u.marketing_emails,
                u.language,
                COALESCE(SUM(p.points), 0) as total_points,
                grr.last_request_at
            FROM {$wpdb->prefix}ppv_users u
            INNER JOIN {$wpdb->prefix}ppv_points p ON p.user_id = u.id
            LEFT JOIN {$wpdb->prefix}ppv_google_review_requests grr
                ON grr.user_id = u.id AND grr.store_id = %d
            WHERE p.store_id IN ({$placeholders})
              AND (
                  (u.whatsapp_consent = 1 AND u.phone_number IS NOT NULL AND u.phone_number != '')
                  OR
                  (u.marketing_emails = 1 AND u.email IS NOT NULL AND u.email != '' AND u.email LIKE '%@%')
              )
            GROUP BY u.id, u.email, u.first_name, u.display_name, u.phone_number, u.whatsapp_consent, u.marketing_emails, u.language, grr.last_request_at
            HAVING total_points >= %d
               AND grr.last_request_at IS NULL
        ", array_merge([$store_id], $store_ids, [$threshold]));

        $eligible_users = $wpdb->get_results($query);

        ppv_log("[Google Review] Store {$store_id}: Found " . count($eligible_users) . " eligible users");

        foreach ($eligible_users as $user) {
            $result = self::send_review_request($store, $user, $review_url, $bonus_points);
            if ($result) {
                $sent++;
                // Record the request and set bonus_pending
                self::record_request($store_id, $user->user_id);
            } else {
                $errors++;
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Send review request to a user - WhatsApp first, fallback to Email
     */
    public static function send_review_request($store, $user, $review_url, $bonus_points) {
        // Determine notification channel: WhatsApp priority, then Email
        $use_whatsapp = !empty($user->whatsapp_consent)
                       && !empty($user->phone_number)
                       && !empty($store->whatsapp_enabled);

        if ($use_whatsapp) {
            return self::send_whatsapp_request($store, $user, $review_url, $bonus_points);
        } else {
            return self::send_email_request($store, $user, $review_url, $bonus_points);
        }
    }

    /**
     * Send WhatsApp review request
     */
    private static function send_whatsapp_request($store, $user, $review_url, $bonus_points) {
        $phone = $user->phone_number;
        $store_name = $store->company_name ?: $store->name ?: 'Store';
        $user_name = $user->first_name ?: $user->display_name ?: '';

        // Use user's language preference
        $lang = self::get_user_language($user);

        ppv_log("[Google Review] Sending WhatsApp to: {$phone} for store {$store->id} (lang: {$lang})");

        // Build WhatsApp message text
        $T = self::get_translations($lang);
        $message = sprintf(
            $T['whatsapp_message'],
            $user_name ? $user_name . ', ' : '',
            $store_name,
            $bonus_points,
            $review_url
        );

        // Use PPV_WhatsApp class to send text message
        if (class_exists('PPV_WhatsApp')) {
            $result = PPV_WhatsApp::send_text($store->id, $phone, $message);

            if (!is_wp_error($result)) {
                ppv_log("[Google Review] WhatsApp SUCCESS to {$phone}");
                return true;
            } else {
                ppv_log("[Google Review] WhatsApp FAILED to {$phone}: " . $result->get_error_message());
                // Fallback to email if WhatsApp fails
                return self::send_email_request($store, $user, $review_url, $bonus_points);
            }
        }

        // Fallback to email if WhatsApp class not available
        return self::send_email_request($store, $user, $review_url, $bonus_points);
    }

    /**
     * Send Email review request
     */
    private static function send_email_request($store, $user, $review_url, $bonus_points) {
        $email = sanitize_email($user->email ?? '');

        if (empty($email) || !is_email($email)) {
            ppv_log("[Google Review] Invalid email for user {$user->user_id}");
            return false;
        }

        // Check if user has marketing_emails enabled
        if (empty($user->marketing_emails)) {
            ppv_log("[Google Review] User {$user->user_id} has marketing_emails disabled");
            return false;
        }

        // Use user's language preference
        $lang = self::get_user_language($user);
        $T = self::get_translations($lang);

        // Build email
        $store_name = $store->company_name ?: $store->name ?: 'Store';
        $user_name = $user->first_name ?: $user->display_name ?: '';
        $subject = sprintf($T['email_subject'], $store_name);
        $body = self::build_email_body($store_name, $user_name, $review_url, $bonus_points, $T);

        // Send email
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $store_name . ' <noreply@punktepass.de>'
        ];

        ppv_log("[Google Review] Sending email to: {$email} (lang: {$lang})");

        $sent = wp_mail($email, $subject, $body, $headers);

        if ($sent) {
            ppv_log("[Google Review] Email SUCCESS to {$email}");
        } else {
            global $phpmailer;
            $error_info = 'Unknown error';
            if (isset($phpmailer) && isset($phpmailer->ErrorInfo) && !empty($phpmailer->ErrorInfo)) {
                $error_info = $phpmailer->ErrorInfo;
            }
            ppv_log("[Google Review] Email FAILED to {$email}: {$error_info}");
        }

        return $sent;
    }

    /**
     * Record that a review request was sent and set bonus_pending
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
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET
                    last_request_at = %s,
                    request_count = request_count + 1,
                    bonus_pending = 1,
                    bonus_awarded_at = NULL
                 WHERE store_id = %d AND user_id = %d",
                current_time('mysql'), $store_id, $user_id
            ));
        } else {
            // Insert new record
            $wpdb->insert($table, [
                'store_id' => $store_id,
                'user_id' => $user_id,
                'last_request_at' => current_time('mysql'),
                'request_count' => 1,
                'bonus_pending' => 1
            ], ['%d', '%d', '%s', '%d', '%d']);
        }
    }

    /**
     * Check and award bonus points during QR scan
     * Called from QR scan handler when user scans
     *
     * @param int $store_id The store (or parent store) ID
     * @param int $user_id The user ID
     * @return array|false Bonus info if awarded, false otherwise
     */
    public static function check_and_award_bonus($store_id, $user_id) {
        global $wpdb;

        // Get parent store ID if this is a filiale
        $parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        $store_id_to_check = $parent_id ?: $store_id;

        // Check if user has pending bonus
        $pending = $wpdb->get_row($wpdb->prepare("
            SELECT grr.*, s.google_review_bonus_points
            FROM {$wpdb->prefix}ppv_google_review_requests grr
            INNER JOIN {$wpdb->prefix}ppv_stores s ON s.id = grr.store_id
            WHERE grr.store_id = %d
              AND grr.user_id = %d
              AND grr.bonus_pending = 1
        ", $store_id_to_check, $user_id));

        if (!$pending) {
            return false;
        }

        $bonus_points = intval($pending->google_review_bonus_points ?? 5);

        if ($bonus_points <= 0) {
            return false;
        }

        // Award the bonus points
        $wpdb->insert($wpdb->prefix . 'ppv_points', [
            'store_id' => $store_id,
            'user_id' => $user_id,
            'points' => $bonus_points,
            'type' => 'google_review_bonus',
            'description' => 'Google Review Bonus',
            'created_at' => current_time('mysql')
        ], ['%d', '%d', '%d', '%s', '%s', '%s']);

        // Mark bonus as awarded
        $wpdb->update(
            $wpdb->prefix . 'ppv_google_review_requests',
            [
                'bonus_pending' => 0,
                'bonus_awarded_at' => current_time('mysql')
            ],
            [
                'store_id' => $store_id_to_check,
                'user_id' => $user_id
            ],
            ['%d', '%s'],
            ['%d', '%d']
        );

        ppv_log("[Google Review] Bonus {$bonus_points} points awarded to user {$user_id} at store {$store_id}");

        return [
            'points' => $bonus_points,
            'type' => 'google_review_bonus'
        ];
    }

    /**
     * Get user's language preference
     */
    private static function get_user_language($user) {
        // Use saved language preference, fallback to 'de'
        $lang = $user->language ?? 'de';

        // Validate language
        if (!in_array($lang, ['de', 'hu', 'ro'])) {
            $lang = 'de';
        }

        return $lang;
    }

    /**
     * Build HTML email body with bonus info
     */
    private static function build_email_body($store_name, $user_name, $review_url, $bonus_points, $T) {
        $greeting = !empty($user_name)
            ? sprintf($T['greeting_name'], esc_html($user_name))
            : $T['greeting_generic'];

        $bonus_text = sprintf($T['bonus_text'], $bonus_points);

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

            <p style="font-size: 16px; color: #374151; margin: 0 0 15px;">
                ' . esc_html($T['review_request_text']) . '
            </p>

            <!-- Bonus highlight -->
            <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
                <div style="font-size: 32px; margin-bottom: 8px;">🎁</div>
                <p style="margin: 0; font-size: 18px; font-weight: 600;">' . esc_html($bonus_text) . '</p>
            </div>

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
     * Get translations for messages
     */
    private static function get_translations($lang) {
        $translations = [
            'de' => [
                'email_subject' => 'Ihre Meinung ist uns wichtig! - %s',
                'email_title' => 'Ihre Bewertung zahlt sich aus!',
                'greeting_name' => 'Hallo %s,',
                'greeting_generic' => 'Hallo,',
                'thank_you_text' => 'vielen Dank, dass Sie treuer Kunde von %s sind!',
                'review_request_text' => 'Wir würden uns sehr über Ihre ehrliche Bewertung auf Google freuen.',
                'bonus_text' => 'Bei Ihrem nächsten Besuch erhalten Sie %d Bonuspunkte!',
                'button_text' => 'Jetzt bewerten',
                'takes_only_text' => 'Es dauert nur 1 Minute!',
                'footer_thanks' => 'Vielen Dank für Ihre Unterstützung!',
                'whatsapp_message' => '%svielen Dank für Ihre Treue bei %s! Wir würden uns über eine Google-Bewertung freuen. Bei Ihrem nächsten Besuch erhalten Sie %d Bonuspunkte! %s',
            ],
            'hu' => [
                'email_subject' => 'A véleménye fontos nekünk! - %s',
                'email_title' => 'Az értékelése számít!',
                'greeting_name' => 'Kedves %s,',
                'greeting_generic' => 'Kedves Vásárlónk,',
                'thank_you_text' => 'köszönjük, hogy hűséges vásárlója a %s-nak!',
                'review_request_text' => 'Nagyon örülnénk, ha megosztaná velünk őszinte véleményét a Google-on.',
                'bonus_text' => 'Következő látogatásánál %d bónuszpontot kap!',
                'button_text' => 'Értékelés írása',
                'takes_only_text' => 'Mindössze 1 percet vesz igénybe!',
                'footer_thanks' => 'Köszönjük a támogatását!',
                'whatsapp_message' => '%sköszönjük hűségét a %s-nál! Örülnénk egy Google értékelésnek. Következő látogatásakor %d bónuszpontot kap! %s',
            ],
            'ro' => [
                'email_subject' => 'Părerea dvs. contează! - %s',
                'email_title' => 'Recenzia dvs. contează!',
                'greeting_name' => 'Bună ziua %s,',
                'greeting_generic' => 'Bună ziua,',
                'thank_you_text' => 'vă mulțumim că sunteți client fidel al %s!',
                'review_request_text' => 'Ne-ar face mare plăcere să ne lăsați o recenzie sinceră pe Google.',
                'bonus_text' => 'La următoarea vizită veți primi %d puncte bonus!',
                'button_text' => 'Lăsați o recenzie',
                'takes_only_text' => 'Durează doar 1 minut!',
                'footer_thanks' => 'Vă mulțumim pentru sprijin!',
                'whatsapp_message' => '%svă mulțumim pentru fidelitate la %s! V-am fi recunoscători pentru o recenzie pe Google. La următoarea vizită primiți %d puncte bonus! %s',
            ],
        ];

        return $translations[$lang] ?? $translations['de'];
    }
}

// Initialize hooks
PPV_Google_Review_Request::hooks();
