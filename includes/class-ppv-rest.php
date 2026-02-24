<?php
if (!defined('ABSPATH')) exit;

class PPV_Scan {

    public static function hooks() {
        ppv_log('🧩 [PPV_REST] register_rest_route() started');

        add_shortcode('ppv_scan_page', [__CLASS__, 'render_scan_page']);
        // add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']); // Disabled - CSS not used
        add_action('wp_ajax_ppv_auto_add_point', [__CLASS__, 'ajax_auto_add_point']);
        // 🔒 Removed wp_ajax_nopriv - authentication required for point operations
    }

    /** 🔹 CSS + JS betöltése */
    public static function enqueue_assets() {
        wp_enqueue_style('ppv-scan', PPV_PLUGIN_URL . 'assets/css/ppv-scan.css', [], time());
        wp_enqueue_script('ppv-scan', PPV_PLUGIN_URL . 'assets/js/ppv-scan.js', ['jquery'], time(), true);

        $data = [
            'ajaxurl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ppv_scan_nonce'),
            'redirect' => site_url('/user-dashboard/')
        ];
        wp_add_inline_script('ppv-scan', 'window.ppvScan = ' . wp_json_encode($data) . ';', 'before');
    }

    /** 🔹 SCAN oldal megjelenítés */
    public static function render_scan_page() {
        ob_start();
        $store_id = isset($_GET['store']) ? intval($_GET['store']) : 0;
        $campaign_id = isset($_GET['campaign']) ? intval($_GET['campaign']) : 0;
        ?>
        <div class="ppv-scan-wrapper">
            <h2>📸 Punkte scannen</h2>

            <?php if (!is_user_logged_in()): ?>
                <p class="ppv-warning">⚠️ Bitte melde dich an, um Punkte zu sammeln.</p>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="ppv-btn">Jetzt anmelden</a>
            <?php elseif ($store_id): ?>
                <div id="ppv-scan-status"
                     data-store="<?php echo esc_attr($store_id); ?>"
                     data-campaign="<?php echo esc_attr($campaign_id); ?>">
                    <div class="ppv-loader"></div>
                    <p>Punkte werden automatisch hinzugefügt...</p>
                </div>
            <?php else: ?>
                <p class="ppv-warning">❌ Kein Store angegeben. Bitte scanne einen gültigen QR-Code.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /** 🔹 AJAX: pont automatikus jóváírás */
    public static function ajax_auto_add_point() {
        check_ajax_referer('ppv_scan_nonce', 'nonce');
        global $wpdb;

        if (!is_user_logged_in()) {
            wp_send_json_error(['msg' => 'Nicht eingeloggt.']);
        }

        $user_id     = get_current_user_id();
        $store_id    = intval($_POST['store_id'] ?? 0);
        $campaign_id = intval($_POST['campaign_id'] ?? 0);

        if (!$store_id) {
            wp_send_json_error(['msg' => 'Ungültige Store-ID.']);
        }

        // 🔹 Bolt ellenőrzése
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT id, company_name AS name FROM {$wpdb->prefix}ppv_stores WHERE id=%d
        ", $store_id));

        if (!$store) {
            wp_send_json_error(['msg' => 'Store nicht gefunden.']);
        }

        // 🔹 Napi duplikáció ellenőrzése
        $today = date('Y-m-d');
        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
            WHERE user_id=%d AND store_id=%d AND DATE(created)=%s
        ", $user_id, $store_id, $today));

        if ($exists > 0) {
            // 🔹 Csak frissítjük a timestampet, hogy a LiveScan észrevegye
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}ppv_points
                SET created = NOW()
                WHERE user_id=%d AND store_id=%d AND DATE(created)=%s
            ", $user_id, $store_id, $today));

            wp_send_json_success([
                'msg'    => 'Du hast heute bereits einen Punkt gesammelt.',
                'repeat' => true,
                'store'  => $store->name
            ]);
        }

        // 🔹 Kampány pont lekérdezése (ha van)
        $points_to_add = 1;
        if ($campaign_id) {
            $points_to_add = intval($wpdb->get_var($wpdb->prepare("
                SELECT points FROM {$wpdb->prefix}ppv_campaigns
                WHERE id=%d AND store_id=%d
            ", $campaign_id, $store_id))) ?: 1;
        }

        // 🔒 SECURITY: Max point limit per scan
        $max_points_per_scan = 20;
        if ($points_to_add > $max_points_per_scan) {
            $points_to_add = $max_points_per_scan;
        }

        // 🔹 Új pont beszúrása
        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id'     => $user_id,
            'store_id'    => $store_id,
            'points'      => $points_to_add,
            'campaign_id' => $campaign_id,
            'created'     => current_time('mysql')
        ]);
        do_action('ppv_points_changed', $user_id);

        wp_send_json_success([
            'msg'    => "{$points_to_add} Punkt(e) erfolgreich gesammelt!",
            'repeat' => false,
            'store'  => $store->name
        ]);
    }
}

PPV_Scan::hooks();
