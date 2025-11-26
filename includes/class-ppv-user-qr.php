<?php
if (!defined('ABSPATH')) exit;

class PPV_User_QR {

    public static function hooks() {
        add_shortcode('ppv_user_qr', [__CLASS__, 'render_qr']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
        add_action('rest_api_init', function () {
            // Régi statikus QR endpoint
            register_rest_route('ppv/v1', '/user/qr', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'rest_get_user_qr'],
                'permission_callback' => '__return_true',
            ]);

            // ÚJ: Timed QR endpoint (30 perces)
            register_rest_route('ppv/v1', '/user/generate-timed-qr', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'rest_generate_timed_qr'],
                'permission_callback' => '__return_true',
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
     * 🎫 TIMED QR - 30 perces időkorlátozott QR generálás
     */
    public static function rest_generate_timed_qr($request) {
        global $wpdb;

        $params = $request->get_json_params();
        $user_id = intval($params['user_id'] ?? 0);

        if (!$user_id) {
            return new WP_REST_Response(['code' => 'missing_user_id', 'message' => 'User ID hiányzik'], 400);
        }

        // User lekérése
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, qr_token, timed_qr_token, timed_qr_expires FROM {$wpdb->prefix}ppv_users WHERE id = %d",
            $user_id
        ));

        if (!$user) {
            return new WP_REST_Response(['code' => 'user_not_found', 'message' => 'Felhasználó nem található'], 404);
        }

        $now = time();
        $expires_at = $user->timed_qr_expires ? strtotime($user->timed_qr_expires) : 0;

        // Ha van érvényes timed token, visszaadjuk
        if (!empty($user->timed_qr_token) && $expires_at > $now) {
            $qr_value = "PPQR-{$user->id}-{$user->timed_qr_token}";
            $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . urlencode($qr_value);

            return new WP_REST_Response([
                'qr_value'   => $qr_value,
                'qr_url'     => $qr_url,
                'expires_at' => $expires_at,
                'expires_in' => $expires_at - $now,
                'is_new'     => false
            ], 200);
        }

        // Új timed token generálása (30 perc)
        $new_token = bin2hex(random_bytes(16));
        $new_expires = date('Y-m-d H:i:s', $now + 1800); // 30 perc

        $wpdb->update(
            "{$wpdb->prefix}ppv_users",
            [
                'timed_qr_token'   => $new_token,
                'timed_qr_expires' => $new_expires
            ],
            ['id' => $user_id],
            ['%s', '%s'],
            ['%d']
        );

        $qr_value = "PPQR-{$user->id}-{$new_token}";
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . urlencode($qr_value);

        return new WP_REST_Response([
            'qr_value'   => $qr_value,
            'qr_url'     => $qr_url,
            'expires_at' => $now + 1800,
            'expires_in' => 1800,
            'is_new'     => true
        ], 200);
    }

    

    public static function enqueue_styles() {
        
        wp_enqueue_script('ppv-user-qr', plugin_dir_url(__FILE__) . '../assets/js/ppv-user-qr.js', [], time(), true);

    }

    public static function render_qr() {
        if (!is_user_logged_in()) {
            return '<p style="text-align:center;">Bitte logge dich ein, um deinen QR-Code zu sehen.</p>';
        }

        global $wpdb;
        $user_id = get_current_user_id();

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, qr_token FROM {$wpdb->prefix}ppv_users WHERE id=%d",
            $user_id
        ));

        if (!$user || empty($user->qr_token)) {
            return '<p style="text-align:center;">⚠️ Kein QR-Code gefunden.</p>';
        }

        ob_start(); ?>
        <div class="ppv-user-qr glass-card" data-user-id="<?php echo esc_attr($user_id); ?>">
            <h2>🎟️ <?php echo esc_html(PPV_Lang::t('your_punktepass_qr')); ?></h2>

            <div class="ppv-qr-warning">
                <span class="ppv-qr-warning-icon">⚠️</span>
                <span class="ppv-qr-warning-text"><?php echo esc_html(PPV_Lang::t('qr_daily_limit_warning')); ?></span>
            </div>

            <!-- Loading State -->
            <div id="ppvQrLoading" class="ppv-qr-loading">
                <i class="ri-loader-4-line ri-spin ppv-qr-spinner"></i>
                <p>QR-Code wird geladen...</p>
            </div>

            <!-- QR Display (hidden initially, shown by JS) -->
            <div id="ppvQrDisplay" class="ppv-qr-display" style="display:none;">
                <img id="ppvQrImg" src="" alt="Dein QR-Code" class="ppv-user-qr-img">

                <!-- Timer -->
                <div id="ppvQrTimer" class="ppv-qr-timer">
                    <i class="ri-time-line"></i>
                    <span id="ppvTimerValue">30:00</span>
                </div>

                <p><?php echo esc_html(PPV_Lang::t('show_this_code_to_collect')); ?></p>
                <input type="text" id="ppvQrValue" readonly value="" class="ppv-user-qr-value" title="Klicken zum Kopieren">
            </div>

            <!-- Expired State -->
            <div id="ppvQrExpired" class="ppv-qr-expired" style="display:none;">
                <i class="ri-time-line ppv-qr-expired-icon"></i>
                <p>QR-Code abgelaufen</p>
            </div>

            <!-- Status Message -->
            <div id="ppvQrStatus" class="ppv-user-qr-status"></div>

            <!-- Refresh Button -->
            <div class="ppv-qr-actions">
                <button id="ppvBtnRefresh" class="ppv-btn ppv-btn-secondary ppv-btn-sm">
                    <i class="ri-refresh-line"></i> Aktualisieren
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

PPV_User_QR::hooks();
