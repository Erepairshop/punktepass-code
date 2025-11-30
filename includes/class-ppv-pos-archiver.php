<?php
if (!defined('ABSPATH')) exit;

/**
 * Automatikus POS log archiváló rendszer
 * Havonta egyszer átmásolja a 3 hónapnál régebbi logjait archív táblákba
 * pl. wp_ppv_pos_log_2025_09
 *
 * Az utolsó 3 hónap MINDIG az alap táblában marad a CSV exporthoz!
 */
class PPV_POS_Archiver {

    // Keep logs in main table for this many months (for CSV export)
    const KEEP_MONTHS = 3;

    public static function hooks() {
        add_action('init', [__CLASS__, 'schedule_cron']);
        add_action('ppv_monthly_pos_archive', [__CLASS__, 'run_archive']);
    }

    /** 🔹 Ütemezett cron esemény havonta egyszer */
    public static function schedule_cron() {
        if (!wp_next_scheduled('ppv_monthly_pos_archive')) {
            wp_schedule_event(strtotime('first day of next month midnight'), 'monthly', 'ppv_monthly_pos_archive');
        }
    }

    /** 🔹 Archiválás logika - 3 hónapnál régebbi adatokat archivál */
    public static function run_archive() {
        global $wpdb;

        // Calculate the month to archive (3 months ago)
        $archive_date = strtotime('-' . self::KEEP_MONTHS . ' months');
        $archive_month = date('m', $archive_date);
        $archive_year  = date('Y', $archive_date);
        $archive_table = $wpdb->prefix . "ppv_pos_log_{$archive_year}_{$archive_month}";

        // Check if we have data from that month
        $has_data = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pos_log
            WHERE DATE_FORMAT(created_at, '%%Y-%%m') = %s
        ", "{$archive_year}-{$archive_month}"));

        if (!$has_data) {
            ppv_log("📦 [PPV_POS_Archiver] No data to archive for {$archive_year}-{$archive_month}");
            return;
        }

        // Check if archive table exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s", $archive_table
        ));

        if (!$exists) {
            // Create archive table based on main table structure
            $wpdb->query("CREATE TABLE $archive_table LIKE {$wpdb->prefix}ppv_pos_log");
        }

        // Copy data to archive (only the target month)
        $copied = $wpdb->query($wpdb->prepare("
            INSERT INTO $archive_table
            SELECT * FROM {$wpdb->prefix}ppv_pos_log
            WHERE DATE_FORMAT(created_at, '%%Y-%%m') = %s
        ", "{$archive_year}-{$archive_month}"));

        // Delete from main table only after successful copy
        if ($copied !== false) {
            $deleted = $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->prefix}ppv_pos_log
                WHERE DATE_FORMAT(created_at, '%%Y-%%m') = %s
            ", "{$archive_year}-{$archive_month}"));

            ppv_log("✅ [PPV_POS_Archiver] Archived {$archive_year}-{$archive_month}: {$copied} rows moved, {$deleted} rows deleted from main table");
        } else {
            ppv_log("❌ [PPV_POS_Archiver] Failed to archive {$archive_year}-{$archive_month}");
        }
    }

    /**
     * Get archive table name for a specific month
     * Returns null if no archive exists for that month
     */
    public static function get_archive_table($year, $month) {
        global $wpdb;

        $archive_table = $wpdb->prefix . "ppv_pos_log_{$year}_{$month}";
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $archive_table));

        return $exists ? $archive_table : null;
    }

    /**
     * Query logs from both main table and archive if needed
     * Used by CSV export to access historical data
     */
    public static function query_with_archive($base_query, $date_year, $date_month, $params = []) {
        global $wpdb;

        // First try main table
        $main_table = $wpdb->prefix . 'ppv_pos_log';

        // Check if data might be in archive (older than KEEP_MONTHS)
        $archive_cutoff = strtotime('-' . self::KEEP_MONTHS . ' months');
        $query_date = strtotime("{$date_year}-{$date_month}-01");

        if ($query_date < $archive_cutoff) {
            // Data might be in archive table
            $archive_table = self::get_archive_table($date_year, str_pad($date_month, 2, '0', STR_PAD_LEFT));

            if ($archive_table) {
                // Replace table name in query
                $archive_query = str_replace($main_table, $archive_table, $base_query);
                return $wpdb->get_results($wpdb->prepare($archive_query, ...$params));
            }
        }

        // Query main table
        return $wpdb->get_results($wpdb->prepare($base_query, ...$params));
    }
}

// cron időköz regisztrálása (havonta)
add_filter('cron_schedules', function($schedules) {
    $schedules['monthly'] = [
        'interval' => 30 * DAY_IN_SECONDS,
        'display'  => __('Einmal pro Monat')
    ];
    return $schedules;
});

add_action('plugins_loaded', function() {
    if (class_exists('PPV_POS_Archiver')) PPV_POS_Archiver::hooks();
});
