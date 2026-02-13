<?php
/**
 * PunktePass - Partner Commission Tracking
 * Handles commission calculation, monthly snapshots, and payout tracking
 *
 * Table: wp_ppv_partner_commissions
 * - Monthly records per partner per premium store
 * - Status: pending → paid | cancelled
 */

if (!defined('ABSPATH')) exit;

class PPV_Partner_Commission {

    const PREMIUM_PRICE = 39.00; // EUR/month

    /**
     * Calculate and store commissions for current month (or specified month)
     * Creates records for all active premium stores that have a partner_id
     *
     * @param string|null $period  e.g. '2026-02' (defaults to current month)
     * @return int Number of new commission records created
     */
    public static function calculate_monthly($period = null) {
        global $wpdb;

        if (!$period) {
            $period = date('Y-m');
        }

        // Get all premium stores that have a partner
        $stores = $wpdb->get_results("
            SELECT s.id as store_id, s.partner_id, s.repair_company_name, s.city,
                   p.commission_rate, p.partner_code, p.company_name as partner_name
            FROM {$wpdb->prefix}ppv_stores s
            INNER JOIN {$wpdb->prefix}ppv_partners p ON p.id = s.partner_id
            WHERE s.repair_premium = 1
              AND p.status = 'active'
              AND s.partner_id IS NOT NULL
        ");

        $created = 0;
        $table = $wpdb->prefix . 'ppv_partner_commissions';

        foreach ($stores as $s) {
            // Check if record already exists for this period
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE partner_id = %d AND store_id = %d AND period_month = %s",
                $s->partner_id, $s->store_id, $period
            ));

            if ($exists) continue;

            $rate = (float) $s->commission_rate;
            $amount = self::PREMIUM_PRICE * ($rate / 100);

            $wpdb->insert($table, [
                'partner_id'      => $s->partner_id,
                'store_id'        => $s->store_id,
                'period_month'    => $period,
                'premium_price'   => self::PREMIUM_PRICE,
                'commission_rate' => $rate,
                'amount'          => $amount,
                'status'          => 'pending',
                'created_at'      => current_time('mysql'),
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Get commission summary for a partner (grouped by month)
     */
    public static function get_monthly_summary($partner_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_partner_commissions';

        return $wpdb->get_results($wpdb->prepare("
            SELECT
                period_month,
                COUNT(*) as store_count,
                SUM(amount) as total_amount,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                MAX(commission_rate) as rate,
                MAX(paid_at) as last_paid_at,
                MIN(status) as combined_status
            FROM {$table}
            WHERE partner_id = %d
            GROUP BY period_month
            ORDER BY period_month DESC
        ", $partner_id));
    }

    /**
     * Get detailed commission records for a partner in a specific month
     */
    public static function get_month_detail($partner_id, $period) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_partner_commissions';

        return $wpdb->get_results($wpdb->prepare("
            SELECT c.*, s.repair_company_name, s.city
            FROM {$table} c
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON s.id = c.store_id
            WHERE c.partner_id = %d AND c.period_month = %s
            ORDER BY c.created_at
        ", $partner_id, $period));
    }

    /**
     * Get total stats for a partner
     */
    public static function get_partner_totals($partner_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_partner_commissions';

        return $wpdb->get_row($wpdb->prepare("
            SELECT
                COALESCE(SUM(amount), 0) as total_earned,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as total_paid,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as total_pending,
                COUNT(DISTINCT period_month) as months_active,
                COUNT(DISTINCT store_id) as unique_stores
            FROM {$table}
            WHERE partner_id = %d
        ", $partner_id));
    }

    /**
     * Mark commissions as paid for a partner in a specific month
     */
    public static function mark_paid($partner_id, $period) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_partner_commissions';

        return $wpdb->update(
            $table,
            ['status' => 'paid', 'paid_at' => current_time('mysql')],
            ['partner_id' => $partner_id, 'period_month' => $period, 'status' => 'pending']
        );
    }

    /**
     * Mark commissions as cancelled for a partner in a specific month
     */
    public static function mark_cancelled($partner_id, $period) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_partner_commissions';

        return $wpdb->update(
            $table,
            ['status' => 'cancelled'],
            ['partner_id' => $partner_id, 'period_month' => $period, 'status' => 'pending']
        );
    }

    /**
     * Get all partners with pending commissions (for admin overview)
     */
    public static function get_pending_overview() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_partner_commissions';

        return $wpdb->get_results("
            SELECT
                p.id as partner_id,
                p.company_name,
                p.partner_code,
                p.email,
                COUNT(*) as pending_records,
                SUM(c.amount) as pending_total,
                MIN(c.period_month) as earliest_period,
                MAX(c.period_month) as latest_period
            FROM {$table} c
            INNER JOIN {$wpdb->prefix}ppv_partners p ON p.id = c.partner_id
            WHERE c.status = 'pending'
            GROUP BY p.id
            ORDER BY pending_total DESC
        ");
    }

    // ─── AJAX Handlers ─────────────────────────────────────

    /**
     * AJAX: Calculate commissions for current month (admin)
     */
    public static function ajax_calculate() {
        if (!PPV_Repair_Core::is_repair_admin_logged_in()) {
            wp_send_json_error(['message' => 'Nicht autorisiert']);
        }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_partner_admin')) {
            wp_send_json_error(['message' => 'Ungültiger Nonce']);
        }

        $period = sanitize_text_field($_POST['period'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            wp_send_json_error(['message' => 'Ungültiges Periodenformat']);
        }

        $created = self::calculate_monthly($period);

        wp_send_json_success([
            'created' => $created,
            'period' => $period,
            'message' => $created . ' neue Provisions-Einträge erstellt für ' . $period,
        ]);
    }

    /**
     * AJAX: Get commission data for a partner (admin)
     */
    public static function ajax_get_partner_commissions() {
        if (!PPV_Repair_Core::is_repair_admin_logged_in()) {
            wp_send_json_error(['message' => 'Nicht autorisiert']);
        }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_partner_admin')) {
            wp_send_json_error(['message' => 'Ungültiger Nonce']);
        }

        $partner_id = intval($_POST['partner_id'] ?? 0);
        if (!$partner_id) wp_send_json_error(['message' => 'Ungültige Partner-ID']);

        $summary = self::get_monthly_summary($partner_id);
        $totals = self::get_partner_totals($partner_id);

        wp_send_json_success([
            'summary' => $summary,
            'totals' => $totals,
        ]);
    }

    /**
     * AJAX: Mark a month as paid (admin)
     */
    public static function ajax_mark_paid() {
        if (!PPV_Repair_Core::is_repair_admin_logged_in()) {
            wp_send_json_error(['message' => 'Nicht autorisiert']);
        }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_partner_admin')) {
            wp_send_json_error(['message' => 'Ungültiger Nonce']);
        }

        $partner_id = intval($_POST['partner_id'] ?? 0);
        $period = sanitize_text_field($_POST['period'] ?? '');

        if (!$partner_id || !$period) {
            wp_send_json_error(['message' => 'Partner-ID und Periode erforderlich']);
        }

        $updated = self::mark_paid($partner_id, $period);
        wp_send_json_success([
            'updated' => $updated,
            'message' => $updated . ' Einträge als bezahlt markiert',
        ]);
    }
}
