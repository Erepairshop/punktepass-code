<?php
// place this in your plugin main file or include it and fire via admin-only page
if (!defined('ABSPATH')) exit;

function ppv_generate_pos_api_keys_once() {
    global $wpdb;
    $table = $wpdb->prefix . 'ppv_stores';

    // 1) add column if not exists
    $col_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW COLUMNS FROM {$table} LIKE %s", 'pos_api_key'
    ));

    if (!$col_exists) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN pos_api_key VARCHAR(128) DEFAULT NULL");
    }

    // 2) fetch stores and generate keys
    $stores = $wpdb->get_results("SELECT id, pos_api_key FROM {$table}");

    foreach ($stores as $s) {
        if (empty($s->pos_api_key)) {
            // strong random seed
            try {
                $random = bin2hex(random_bytes(32)); // 64 hex chars
            } catch (Exception $e) {
                // fallback
                $random = wp_generate_password(64, true, true);
            }
            // create HMAC using WP salt for extra randomness tied to install
            $raw_key = $random . '|' . $s->id . '|' . time();
            $key = hash_hmac('sha256', $raw_key, wp_salt());

            $wpdb->update($table, ['pos_api_key' => $key], ['id' => $s->id]);
        }
    }

    // 3) optionally add unique index (MySQL may fail if NULLs exist; ensure non-null or use index on length)
    // create unique index only if there are no duplicates
    $has_index = $wpdb->get_var($wpdb->prepare(
        "SHOW INDEX FROM {$table} WHERE Key_name = %s", 'uniq_pos_api_key'
    ));
    if (!$has_index) {
        // index on full column
        $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY uniq_pos_api_key (pos_api_key(64))");
    }

    return true;
}

// Option A: run on plugin activation
register_activation_hook(__FILE__, 'ppv_generate_pos_api_keys_once');

// Option B: or call manually from admin (uncomment to use):
// add_action('admin_init', function() { if (current_user_can('activate_plugins')) ppv_generate_pos_api_keys_once(); });
