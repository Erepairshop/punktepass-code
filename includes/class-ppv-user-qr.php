<?php
if (!defined('ABSPATH')) exit;

class PPV_User_QR {

    public static function hooks() {
        add_shortcode('ppv_user_qr', [__CLASS__, 'render_qr']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
        add_action('rest_api_init', function () {
    register_rest_route('ppv/v1', '/user/qr', [
        'methods' => 'GET',
        'callback' => [__CLASS__, 'rest_get_user_qr'],
        'permission_callback' => ['PPV_Permissions', 'check_authenticated'],
    ]);

    // ✨ NEW: Timed QR generation (30 min TTL)
    register_rest_route('ppv/v1', '/user/generate-timed-qr', [
        'methods' => 'POST',
        'callback' => [__CLASS__, 'rest_generate_timed_qr'],
        'permission_callback' => ['PPV_Permissions', 'check_authenticated'],
    ]);
});

    }
    public static function rest_get_user_qr($request) {
    global $wpdb;
    $user_id = intval($request->get_param('user_id'));
    if (!$user_id) return ['error' => 'missing_user_id'];

    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT id, qr_token FROM {$wpdb->prefix}ppv_users WHERE id=%d",
        $user_id
    ));

    if (!$user) return ['error' => 'user_not_found'];
    return [
        'qr_value' => "PPUSER-{$user->id}-{$user->qr_token}",
        'qr_url'   => 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . urlencode("PPUSER-{$user->id}-{$user->qr_token}")
    ];
}

    /**
     * Generate time-limited QR code (30 min TTL)
     * Returns existing QR if still valid, generates new one if expired
     */
    public static function rest_generate_timed_qr($request) {
        $user_id = intval($request->get_param('user_id'));

        if (!$user_id) {
            return new WP_Error('missing_user_id', 'User ID hiányzik', ['status' => 400]);
        }

        // 1. Check if valid timed QR already exists
        $cache_key = "ppv_timed_qr_{$user_id}";
        $existing_qr = get_transient($cache_key);

        if ($existing_qr && !empty($existing_qr['token'])) {
            // Valid QR exists → return it
            return [
                'qr_value' => "PPUSER-{$user_id}-{$existing_qr['token']}",
                'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . urlencode("PPUSER-{$user_id}-{$existing_qr['token']}"),
                'expires_at' => $existing_qr['expires_at'],
                'expires_in' => max(0, $existing_qr['expires_at'] - time()),
                'created_at' => $existing_qr['created_at'],
                'is_new' => false
            ];
        }

        // 2. Generate new timed QR
        $token = wp_generate_password(16, false, false);
        $created_at = time();
        $expires_at = $created_at + 1800; // 30 min = 1800 sec

        // 3. Store in transient (30 min TTL)
        set_transient($cache_key, [
            'token' => $token,
            'created_at' => $created_at,
            'expires_at' => $expires_at,
            'user_id' => $user_id
        ], 1800);

        // 4. Return QR data
        return [
            'qr_value' => "PPUSER-{$user_id}-{$token}",
            'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . urlencode("PPUSER-{$user_id}-{$token}"),
            'expires_at' => $expires_at,
            'expires_in' => 1800,
            'created_at' => $created_at,
            'is_new' => true
        ];
    }

    

    public static function enqueue_styles() {
        
        wp_enqueue_script('ppv-user-qr', plugin_dir_url(__FILE__) . '../assets/js/ppv-user-qr.js', [], time(), true);

    }

    public static function render_qr() {
        if (!is_user_logged_in()) {
            return '<p style="text-align:center;">Bitte logge dich ein, um deinen QR-Code zu sehen.</p>';
        }

        $user_id = get_current_user_id();

        ob_start(); ?>
        <div class="ppv-user-qr glass-card" data-user-id="<?php echo esc_attr($user_id); ?>">
            <h2>🎟️ <?php echo esc_html(PPV_Lang::t('your_punktepass_qr')); ?></h2>

            <div class="ppv-qr-warning">
                <span class="ppv-qr-warning-icon">⚠️</span>
                <span class="ppv-qr-warning-text"><?php echo esc_html(PPV_Lang::t('qr_daily_limit_warning')); ?></span>
            </div>

            <!-- Loading State -->
            <div class="ppv-qr-loading" id="ppvQrLoading">
                <div class="ppv-spinner"></div>
                <p>QR-Code wird geladen...</p>
            </div>

            <!-- QR Display -->
            <div class="ppv-qr-display" id="ppvQrDisplay" style="display: none;">
                <img src="" alt="Dein QR-Code" class="ppv-user-qr-img" id="ppvQrImg">

                <!-- Countdown Timer -->
                <div class="ppv-qr-timer" id="ppvQrTimer">
                    <span class="ppv-timer-icon">⏱️</span>
                    <span class="ppv-timer-text">Gültig noch: <strong id="ppvTimerValue">--:--</strong></span>
                </div>

                <p><?php echo esc_html(PPV_Lang::t('show_this_code_to_collect')); ?></p>
                <input type="text" readonly value="" class="ppv-user-qr-value" id="ppvQrValue" onclick="this.select();">
            </div>

            <!-- Expired State -->
            <div class="ppv-qr-expired" id="ppvQrExpired" style="display: none;">
                <p>⏰ QR-Code abgelaufen</p>
                <button class="ppv-btn-refresh" id="ppvBtnRefresh">
                    🔄 Neuen QR-Code generieren
                </button>
            </div>

            <div class="ppv-user-qr-status" id="ppvQrStatus"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

PPV_User_QR::hooks();
