<?php
if (!defined('ABSPATH')) exit;

class PPV_POS_Admin {

    /** ============================================================
     * INIT
     * ============================================================ */
    public static function hooks() {
        add_shortcode('ppv_pos_admin', [__CLASS__, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** ============================================================
     /** ============================================================
 *  ASSETS (CSS + JS)
 *  ============================================================ */
public static function enqueue_assets() {
    // csak akkor töltsük be, ha shortcode az oldalon van
    if (!is_singular()) return;
    global $post;
    if (has_shortcode($post->post_content, 'ppv_pos_admin')) {

        // 🔹 CSS betöltés
        wp_enqueue_style(
            'ppv-pos-admin',
            PPV_PLUGIN_URL . 'assets/css/ppv-pos-admin.css',
            [],
            time()
        );

        // 🔹 JS betöltés
        wp_enqueue_script(
            'ppv-pos-admin',
            PPV_PLUGIN_URL . 'assets/js/ppv-pos-admin.js',
            ['jquery'],
            time(),
            true
        );

        // 🔹 Régi lokalizáció (logout, log funkciókhoz)
        $__data = is_array([
            'resturl' => trailingslashit(esc_url(rest_url('ppv/v1/'))),
            'nonce'   => wp_create_nonce('wp_rest'),
        ] ?? null) ? [
            'resturl' => trailingslashit(esc_url(rest_url('ppv/v1/'))),
            'nonce'   => wp_create_nonce('wp_rest'),
        ] : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('ppv-pos-admin', "window.PPV_POS_ADMIN = {$__json};", 'before');

        // 🔹 Új lokalizáció a statisztikához és dashboardhoz
        $__data = is_array([
            'api_base' => esc_url(rest_url('ppv/v1')),
            'store_id' => get_current_user_id() ? intval(get_user_meta(get_current_user_id(), 'store_id', true)) : 0,
        ] ?? null) ? [
            'api_base' => esc_url(rest_url('ppv/v1')),
            'store_id' => get_current_user_id() ? intval(get_user_meta(get_current_user_id(), 'store_id', true)) : 0,
        ] : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('ppv-pos-admin', "window.PPV_POS = {$__json};", 'before');
    }
}
/** ============================================================
 *  HOOK AKTIVÁLÁS + POS SESSION AUTO-FIX
 *  ============================================================ */
public static function hooks_loader() {

    add_action('plugins_loaded', function () {

        PPV_POS_Admin::hooks();
        error_log("✅ PPV_POS_Admin (Frontend) aktiv – Shortcode [ppv_pos_admin]");

        // ============================================================
        // 🧠 POS Session Auto-Fix (ensure global state)
        // ============================================================
        add_action('wp', function () {
            if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
                @session_start();
                error_log("✅ [PPV_POS_Admin] Session started safely (moved to wp hook).");
            } else {
                error_log("⚠️ [PPV_POS_Admin] Session skipped – headers sent or already active.");
            }
// 🔄 Token → Session Sync (if cookie exists)
    if (!isset($_SESSION['ppv_is_pos']) && !empty($_COOKIE['ppv_pos_token'])) {
        $_SESSION['ppv_is_pos'] = true;
        $_SESSION['ppv_active_store'] = intval($_COOKIE['ppv_store_id'] ?? 9);
        error_log("🧩 [PPV_POS_Admin] Session restored from cookie token.");
    }
            $sid = intval($_SESSION['ppv_active_store'] ?? 0);
            $is_pos_raw = $_SESSION['ppv_is_pos'] ?? false;

            // 🔍 engedjük az összes lehetséges értéket
            $is_pos = in_array($is_pos_raw, [true, 1, '1', 'true', 'yes'], true);

            if ($is_pos && $sid > 0) {
                $GLOBALS['ppv_is_pos'] = true;
                $GLOBALS['ppv_active_store'] = $sid;
                error_log("✅ [PPV_POS_SESSION_FIX] POS aktiv | Store={$sid}");
            } else {
                error_log("⚠️ [PPV_POS_SESSION_FIX] Keine gültige POS-Session gefunden | sid={$sid}, raw=" . json_encode($is_pos_raw));
            }
        }, 1);
    });
}


    /** ============================================================
     * SHORTCODE MEGJELENÍTÉS ([ppv_pos_admin])
     * ============================================================ */
public static function render_shortcode() {
    
    


        ob_start();
        ?>
        <div id="ppv-pos-wrapper" class="ppv-pos-container">
            <div id="ppv-pos-login" class="ppv-pos-card">
                <h2>💳 POS Login</h2>
                <p>Bitte geben Sie den PIN ein, um sich anzumelden.</p>
                <input type="password" id="ppv-pos-pin" placeholder="PIN eingeben">
                <button id="ppv-pos-login-btn" class="ppv-btn-primary">Anmelden</button>
                <div id="ppv-pos-login-msg" class="ppv-msg"></div>
                
                
            </div>

            <div id="ppv-pos-dashboard" style="display:none;">
    <?php echo self::render_pos_dashboard(); ?>
</div>

        </div>
        <?php
        
    if (!isset($_COOKIE['ppv_pos_token'])) {
    ?>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('ppv-pos-dashboard').style.display = 'none';
        document.getElementById('ppv-pos-login').style.display = 'block';
      });
    </script>
    <?php
}

        return ob_get_clean();
    }
    
   public static function render_pos_dashboard() {
    ob_start(); ?>
    
    <div id="ppv-pos-dashboard" class="ppv-pos-dashboard">
        <h2>📊 POS Dashboard</h2>
        <p>Willkommen, <span id="ppv-store-name">Lade...</span></p>
        <div class="ppv-store-switcher">
  <label for="ppv-store-selector">🛍️ Geschäft wählen:</label>
  <select id="ppv-store-selector" class="ppv-select"></select>
</div>


        <div class="ppv-pos-cards">
            <div class="ppv-pos-card">
                <span>📈 Heutige Scans:</span>
                <strong id="today-scans">0</strong>
            </div>

            <div class="ppv-pos-card">
                <span>💰 Heutige Punkte:</span>
                <strong id="today-points">0</strong>
            </div>

            <div class="ppv-pos-card">
                <span>🎁 Heutige Rewards:</span>
                <strong id="today-rewards">0</strong>
            </div>

            <div class="ppv-pos-card">
                <span>📣 Aktive Kampagnen:</span>
                <strong id="active-campaigns">0</strong>
            </div>

            <div class="ppv-pos-card">
                <span>💶 Heutiger Umsatz (€):</span>
                <strong id="today-sales">0.00</strong>
            </div>

            <div class="ppv-pos-card">
                <span>🕒 Letzter Scan:</span>
                <strong id="last-scan">—</strong>
            </div>
        </div>

        <!-- 📊 Diagrambereich -->
        <div class="ppv-pos-chart-wrapper" style="margin-top:25px;">
            <canvas id="posChart" style="width:100%;height:220px;"></canvas>
        </div>

        <div class="ppv-pos-buttons" style="margin-top:25px;display:flex;gap:10px;justify-content:center;">
            <button id="ppv-pos-refresh" class="ppv-btn-refresh">🔄 Aktualisieren</button>
            <button id="ppv-pos-logout-btn" class="ppv-btn-logout">🚪 Logout</button>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

}


/** ============================================================
 * REST API VÉGPONTOK
 * ============================================================ */
add_action('rest_api_init', function() {
    error_log("🧩 [PPV_POS_Admin] register_rest_route aktiválva");


    register_rest_route('ppv/v1', '/pos/login', [
        'methods'  => 'POST',
        'callback' => function($request) {
            global $wpdb;
            $pin = sanitize_text_field($request['pin'] ?? '');

            if (!$pin) {
                return new WP_REST_Response(['success' => false, 'message' => 'PIN fehlt.'], 400);
            }

            // 🔹 Händler anhand PIN suchen
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT id, company_name, pos_enabled, active, subscription_status 
                FROM {$wpdb->prefix}ppv_stores 
                WHERE pos_pin = %s LIMIT 1
            ", $pin));

       

            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            $_SESSION['ppv_is_pos'] = true;
            $_SESSION['ppv_active_store'] = intval($store->id);
            $_SESSION['ppv_store_name'] = $store->company_name ?? '';
            session_write_close();

            $token = wp_generate_uuid4();
            set_transient("ppv_pos_session_{$token}", $store->id, HOUR_IN_SECONDS * 6);
            setcookie('ppv_pos_token', $token, time() + 6 * HOUR_IN_SECONDS, '/');

            $GLOBALS['ppv_is_pos'] = true;
            $GLOBALS['ppv_active_store'] = $store->id;

            error_log("✅ [POS_Login] Erfolgreich | Store={$store->company_name} (ID={$store->id})");

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'store_id' => intval($store->id),
                    'store_name' => $store->company_name,
                    'session_token' => $token,
                    'message' => 'POS-Login erfolgreich.'
                ]
            ], 200);
        },
        'permission_callback' => '__return_true'
    ]);
});
