<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – POS Device Management v1.0
 * ✅ Több eszköz támogatása boltanként
 * ✅ REST API: list, register, update
 * Author: PunktePass (Erik Borota)
 */

class PPV_POS_Devices {

    /** Inicializálás */
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /** REST endpointok */
    public static function register_routes() {

        register_rest_route('ppv/v1', '/pos/devices/list', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_devices'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('ppv/v1', '/pos/devices/register', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'register_device'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('ppv/v1', '/pos/devices/update', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'update_device'],
            'permission_callback' => '__return_true'
        ]);
    }

    /** Eszközök listázása */
    public static function list_devices($request) {
        global $wpdb;
        $store_id = intval($request->get_param('store_id')) ?: ppv_store_id();
        if (!$store_id) return new WP_Error('no_store', 'Store ID missing', ['status' => 400]);

        $devices = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_pos_devices
            WHERE store_id = %d ORDER BY id DESC", $store_id));

        return ['success' => true, 'devices' => $devices];
    }

    /** Új eszköz regisztrálása */
    public static function register_device($request) {
        global $wpdb;
        $store_id = intval($request->get_param('store_id')) ?: ppv_store_id();
        $name = sanitize_text_field($request->get_param('device_name'));
        if (!$store_id || !$name) return new WP_Error('missing_data', 'Store ID or name missing', ['status' => 400]);

        $key = wp_generate_password(32, false);
        $wpdb->insert("{$wpdb->prefix}ppv_pos_devices", [
            'store_id' => $store_id,
            'device_name' => $name,
            'device_key' => $key,
            'status' => 'active',
            'created_at' => current_time('mysql')
        ]);

        return ['success' => true, 'device_key' => $key];
    }

    /** Eszköz státusz / szinkron frissítés */
    public static function update_device($request) {
        global $wpdb;
        $key = sanitize_text_field($request->get_param('device_key'));
        if (!$key) return new WP_Error('missing_key', 'Device key missing', ['status' => 400]);

        $wpdb->update("{$wpdb->prefix}ppv_pos_devices",
            ['last_sync' => current_time('mysql')],
            ['device_key' => $key]
        );

        return ['success' => true, 'updated' => true];
    }
}

PPV_POS_Devices::hooks();
