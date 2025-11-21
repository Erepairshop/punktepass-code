<?php
if (!defined('ABSPATH')) exit;

class PPV_Bonus_Days {

    public static function hooks() {
        add_shortcode('ppv_bonus_days', [__CLASS__, 'render_bonus_page']);
        add_action('wp_ajax_ppv_save_bonus_day', [__CLASS__, 'ajax_save_bonus_day']);
        add_action('wp_ajax_ppv_delete_bonus_day', [__CLASS__, 'ajax_delete_bonus_day']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** ============================================================
     *  🔐 GET STORE ID (with FILIALE support)
     * ============================================================ */
    private static function get_store_id() {
        global $wpdb;

        // 🔐 Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return intval($_SESSION['ppv_current_filiale_id']);
        }

        // Session - base store
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        // Fallback: vendor store
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return intval($_SESSION['ppv_vendor_store_id']);
        }

        // Fallback: WordPress user (rare case)
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                $uid
            ));
            if ($store_id) {
                return intval($store_id);
            }
        }

        return 0;
    }

    public static function enqueue_assets() {
        wp_enqueue_style('ppv-bonus-days', PPV_PLUGIN_URL . 'assets/css/ppv-bonus-days.css', [], time());
        wp_enqueue_script('ppv-bonus-days', PPV_PLUGIN_URL . 'assets/js/ppv-bonus-days.js', ['jquery'], time(), true);
        $__data = is_array([
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ppv_bonus_nonce')
        ] ?? null) ? [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ppv_bonus_nonce')
        ] : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('ppv-bonus-days', "window.ppv_bonus_ajax = {$__json};", 'before');
    }

    public static function render_bonus_page() {
        if (!is_user_logged_in()) {
            return '<p>Bitte melden Sie sich an.</p>';
        }

        ob_start();
        ?>
        <div class="ppv-bonus-wrapper">
            <h2>🎁 Bonus-Tage einstellen</h2>
            <form id="ppv-bonus-form">
                <label>📅 Datum:</label>
                <input type="date" name="date" required>

                <label>✖ Multiplikator (z.B. 2 = doppelte Punkte):</label>
                <input type="number" step="0.1" name="multiplier" value="1.0">

                <label>➕ Extra Punkte:</label>
                <input type="number" name="extra_points" value="0">

                <label>✅ Aktiv:</label>
                <select name="active">
                    <option value="1">Ja</option>
                    <option value="0">Nein</option>
                </select>

                <button type="submit" class="ppv-btn-save">Speichern</button>
            </form>

            <h3>📋 Aktive Bonus-Tage</h3>
            <div id="ppv-bonus-list"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** 🔹 AJAX – Bonus mentés */
    public static function ajax_save_bonus_day() {
        check_ajax_referer('ppv_bonus_nonce', 'nonce');
        global $wpdb;

        // 🏪 FILIALE SUPPORT: Get store ID from session
        $store_id = self::get_store_id();
        if (!$store_id) {
            wp_send_json_error(['message' => 'Kein Store gefunden.']);
        }

        $date = sanitize_text_field($_POST['date'] ?? '');
        $multiplier = floatval($_POST['multiplier'] ?? 1);
        $extra_points = intval($_POST['extra_points'] ?? 0);
        $active = intval($_POST['active'] ?? 1);

        if (!$date) {
            wp_send_json_error(['message' => 'Fehlende Daten']);
        }

        $wpdb->insert('wp_ppv_bonus_days', [
            'store_id' => $store_id,
            'date' => $date,
            'multiplier' => $multiplier,
            'extra_points' => $extra_points,
            'active' => $active,
            'created_at' => current_time('mysql')
        ]);

        wp_send_json_success(['message' => 'Bonus-Tag gespeichert']);
    }

    /** 🔹 AJAX – Bonus törlés */
    public static function ajax_delete_bonus_day() {
        check_ajax_referer('ppv_bonus_nonce', 'nonce');
        global $wpdb;

        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => 'Fehlende ID']);
        }

        // 🏪 FILIALE SUPPORT: Get store ID from session
        $store_id = self::get_store_id();
        if (!$store_id) {
            wp_send_json_error(['message' => 'Kein Store gefunden.']);
        }

        // 🔒 SECURITY: Verify bonus day belongs to handler's store/filiale
        $bonus = $wpdb->get_row($wpdb->prepare(
            "SELECT id, store_id FROM {$wpdb->prefix}ppv_bonus_days WHERE id=%d LIMIT 1",
            $id
        ));
        if (!$bonus || $bonus->store_id != $store_id) {
            wp_send_json_error(['message' => 'Keine Berechtigung für diesen Bonus-Tag.']);
        }

        $wpdb->delete($wpdb->prefix . 'ppv_bonus_days', ['id' => $id]);
        wp_send_json_success(['message' => 'Bonus-Tag gelöscht']);
    }
}

add_action('plugins_loaded', function() {
    if (class_exists('PPV_Bonus_Days')) PPV_Bonus_Days::hooks();
});
