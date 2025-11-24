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

        $qr_value = "PPUSER-{$user->id}-{$user->qr_token}";
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . urlencode($qr_value);

        ob_start(); ?>
        <div class="ppv-user-qr glass-card">
            <h2>🎟️ <?php echo esc_html(PPV_Lang::t('your_punktepass_qr')); ?></h2>

            <div class="ppv-qr-warning">
                <span class="ppv-qr-warning-icon">⚠️</span>
                <span class="ppv-qr-warning-text"><?php echo esc_html(PPV_Lang::t('qr_daily_limit_warning')); ?></span>
            </div>

            <img src="<?php echo esc_url($qr_url); ?>" alt="Dein QR-Code" class="ppv-user-qr-img">
            <p><?php echo esc_html(PPV_Lang::t('show_this_code_to_collect')); ?></p>
            <input type="text" readonly value="<?php echo esc_attr($qr_value); ?>" class="ppv-user-qr-value" onclick="this.select();">
        </div>
        <?php
        return ob_get_clean();
    }
}

PPV_User_QR::hooks();
