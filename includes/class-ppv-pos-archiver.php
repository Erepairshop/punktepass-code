<?php
if (!defined('ABSPATH')) exit;

/**
 * Automatikus POS log archiváló rendszer
 * Havonta egyszer átmásolja az előző hónap logjait egy új táblába
 * pl. wp_ppv_pos_log_2025_09
 */
class PPV_POS_Archiver {

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

    /** 🔹 Archiválás logika */
    public static function run_archive() {
        global $wpdb;

        $current_month = date('m');
        $current_year  = date('Y');

        // előző hónap azonosítása
        $prev_month = date('m', strtotime('first day of last month'));
        $prev_year  = date('Y', strtotime('first day of last month'));
        $archive_table = $wpdb->prefix . "ppv_pos_log_{$prev_year}_{$prev_month}";

        // ellenőrzés: létezik-e már
        $exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s", $archive_table
        ));

        if (!$exists) {
            // új archív tábla létrehozása a régi alapján
            $wpdb->query("CREATE TABLE $archive_table LIKE {$wpdb->prefix}ppv_pos_log");
        }

        // átmásolás (csak az előző hónap)
        $wpdb->query($wpdb->prepare("
            INSERT INTO $archive_table 
            SELECT * FROM {$wpdb->prefix}ppv_pos_log 
            WHERE DATE_FORMAT(created_at, '%%Y-%%m') = %s
        ", "{$prev_year}-{$prev_month}"));

        // törlés az eredetiből
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}ppv_pos_log 
            WHERE DATE_FORMAT(created_at, '%%Y-%%m') = %s
        ", "{$prev_year}-{$prev_month}"));

        // naplózás (logfileba)
        error_log("✅ PPV_POS_Archiver: archived {$prev_year}-{$prev_month} successfully.");
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
