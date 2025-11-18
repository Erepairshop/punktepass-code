<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Kamera QR Scanner (v2.1)
 * Author: Erik Borota
 * 📷 HTML5 Kamera alapú QR olvasó modul + REST Scan Handler
 */

class PPV_Camera_Scanner {

    /** 🔹 Inicializálás */
    public static function hooks() {
        add_shortcode('ppv_camera_scanner', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /** 🔹 Script betöltés */
    public static function enqueue() {
        wp_enqueue_script(
            'ppv-scanner',
            PPV_PLUGIN_URL . 'assets/js/ppv-scanner.js',
            [],
            time(),
            true
        );
    }

    /** 🔹 HTML megjelenítés */
    public static function render() {
        ob_start(); ?>
        <div id="ppv-camera-scan" style="text-align:center;margin-top:40px;">
            <h2>📷 PunktePass Kamera-Scanner</h2>
            <p style="color:#aaa;">Erlaube den Kamerazugriff, um QR-Codes einzuscannen.</p>
            <div id="ppv-scan-area"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** 🔹 REST route regisztrálása */
    public static function register_routes() {
        register_rest_route('punktepass/v1', '/pos/scan', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'handle_scan'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);
    }

    /** 🔹 REST callback: QR feldolgozás és pontmentés */
    public static function handle_scan($request) {
        global $wpdb;

        $params  = $request->get_json_params();
        $qr_code = sanitize_text_field($params['qr_code'] ?? '');

        if (empty($qr_code)) {
            return new WP_REST_Response(['msg' => '❌ Kein QR-Code erhalten.'], 400);
        }

        // 🔹 Feltételezzük, hogy QR formátum: ppv-{user_id}-{store_id}
        $parts = explode('-', $qr_code);
        $user_id  = intval($parts[1] ?? 0);
        $store_id = intval($parts[2] ?? 0);

        if (!$user_id || !$store_id) {
            return new WP_REST_Response(['msg' => '⚠️ Ungültiger QR-Code.'], 400);
        }

        // 🔹 Napi limit
        $today = date('Y-m-d');
        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
            WHERE user_id=%d AND store_id=%d AND DATE(created)=%s
        ", $user_id, $store_id, $today));

        if ($exists) {
            return new WP_REST_Response(['msg' => '⚠️ Heute bereits gescannt.'], 200);
        }

        // 🔹 Pont beszúrás
        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id'  => $user_id,
            'store_id' => $store_id,
            'points'   => 1,
            'type'     => 'camera_scan',
            'created'  => current_time('mysql')
        ]);

        return new WP_REST_Response([
            'msg'   => '🎉 Punkt erfolgreich hinzugefügt!',
            'user'  => $user_id,
            'store' => $store_id
        ], 200);
    }
}

// 🔧 Aktiválás
add_action('init', ['PPV_Camera_Scanner', 'hooks']);
