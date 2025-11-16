<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – User Rewards & Redeem (v2.2 MODERN + BEAUTIFUL + TRANSLATED)
 * ✅ Modern cards, progress bars, gorgeous UI
 * ✅ Transparent design, glass effect
 * ✅ Light/Dark mode support
 * ✅ Full translations (de, hu, ro)
 * Version: 2.2 REDESIGN
 */

class PPV_Belohnungen {
    
    private static function ensure_session() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    public static function hooks() {
        add_shortcode('ppv_rewards_page', [__CLASS__, 'render_rewards_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    private static function get_labels($lang = 'de') {
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = 'de';
        }

        $file = dirname(__FILE__) . "/languages/lang-{$lang}-BELOHNUNGEN.php";
        if (file_exists($file)) {
            return include($file);
        }

        return [];
    }

    private static function get_label($key, $lang = 'de', $default = '') {
        $labels = self::get_labels($lang);
        return $labels[$key] ?? $default ?? $key;
    }

    public static function register_rest_routes() {
        register_rest_route('ppv/v1', '/rewards/redeem', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_redeem_reward'],
            'permission_callback' => [__CLASS__, 'check_redeem_permission'],
        ]);

        register_rest_route('ppv/v1', '/rewards/status', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_reward_status'],
            'permission_callback' => [__CLASS__, 'check_status_permission'],
        ]);
    }

    public static function check_redeem_permission($request) {
        self::ensure_session();
        if (is_user_logged_in()) {
            return true;
        }
        if (!empty($_SESSION['ppv_store_id']) || !empty($_SESSION['ppv_user_id'])) {
            return true;
        }
        return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
    }

    public static function check_status_permission($request) {
        self::ensure_session();
        $user_id = intval($request->get_param('user_id') ?? 0);
        if (!$user_id) {
            return new WP_Error('invalid_user', 'Invalid user', ['status' => 400]);
        }
        $current_user = get_current_user_id();
        if ($current_user === $user_id || is_user_logged_in()) {
            return true;
        }
        if (!empty($_SESSION['ppv_store_id'])) {
            return true;
        }
        return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
    }

    public static function rest_reward_status($request) {
        global $wpdb;
        self::ensure_session();

        $user_id = intval($request->get_param('user_id') ?? 0);
        $limit = intval($request->get_param('limit') ?? 10);
        $limit = min($limit, 100);

        if (!$user_id) {
            return new WP_REST_Response(['success' => false], 400);
        }

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT 
                r.id, rw.title, r.status, r.redeemed_at, 
                s.name AS store_name, rw.description, r.points_spent
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            INNER JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
            WHERE r.user_id = %d
            ORDER BY r.redeemed_at DESC
            LIMIT %d
        ", $user_id, $limit));

        return new WP_REST_Response([
            'success' => true,
            'items' => $rows ?: [],
            'count' => count($rows ?: [])
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate'
        ]);
    }

    public static function enqueue_assets() {
        if (wp_script_is('ppv-belohnungen', 'enqueued')) {
            return;
        }

        $plugin_url = defined('PPV_PLUGIN_URL') 
            ? PPV_PLUGIN_URL 
            : plugin_dir_url(dirname(__FILE__));

        $lang = sanitize_text_field($_GET['lang'] ?? ($_SESSION['ppv_lang'] ?? 'de'));
        if (!in_array($lang, ['de', 'hu', 'ro'])) {
            $lang = 'de';
        }

        wp_enqueue_script(
            'ppv-theme-loader',
            $plugin_url . 'assets/js/ppv-theme-loader.js',
            [],
            '2.0.0',
            false
        );

        wp_enqueue_style(
            'google-fonts-inter',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'remix-icon',
            'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css',
            [],
            '3.5.0'
        );

        $js_file = dirname(__FILE__) . '/assets/js/ppv-belohnungen.js';
        $js_version = file_exists($js_file) ? filemtime($js_file) : '2.2.0';

        wp_enqueue_script(
            'ppv-belohnungen',
            $plugin_url . 'assets/js/ppv-belohnungen.js',
            ['jquery'],
            $js_version,
            true
        );

        $data = [
            'base_url' => esc_url(rest_url('ppv/v1/')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'lang'     => $lang,
            'debug'    => defined('WP_DEBUG') && WP_DEBUG,
        ];
        wp_add_inline_script(
            'ppv-belohnungen',
            'window.ppv_belohnungen = ' . wp_json_encode($data) . ';',
            'before'
        );

        global $wpdb;
        $stores = $wpdb->get_results("
            SELECT id, name 
            FROM {$wpdb->prefix}ppv_stores 
            WHERE active = 1 
            ORDER BY name ASC
        ");
        wp_add_inline_script(
            'ppv-belohnungen',
            'window.ppv_available_stores = ' . wp_json_encode($stores ?: []) . ';',
            'before'
        );

       
    }

   

    public static function render_rewards_page() {
        self::ensure_session();
        global $wpdb;

        $lang = sanitize_text_field($_GET['lang'] ?? ($_SESSION['ppv_lang'] ?? 'de'));
        if (!in_array($lang, ['de', 'hu', 'ro'])) {
            $lang = 'de';
        }

        $user_id = 0;
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
        } elseif (!empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
        }

        if (!$user_id) {
            $msg = self::get_label('no_login', $lang, 'Kérjük, hogy jelentkezz be');
            return '<div class="ppv-notice-error">⚠️ ' . esc_html($msg) . '</div>';
        }

        $points = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points), 0) 
            FROM {$wpdb->prefix}ppv_points 
            WHERE user_id = %d
        ", $user_id));

        $rewards = $wpdb->get_results("
            SELECT r.id, r.title, r.description, r.required_points, r.store_id, s.name AS store_name
            FROM {$wpdb->prefix}ppv_rewards r
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
            WHERE s.active = 1
            ORDER BY r.required_points ASC
        ");

        $pending = $wpdb->get_results($wpdb->prepare("
            SELECT r.id, rw.title, rw.description, s.name AS store_name, r.redeemed_at, r.status, r.points_spent
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            INNER JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
            WHERE r.user_id = %d
            ORDER BY r.redeemed_at DESC
            LIMIT 20
        ", $user_id));

        ob_start();
        ?>
        <div class="ppv-belohnungen ppv-modern-rewards">

            <!-- HEADER -->
            <div class="ppv-rewards-header">
                <h2><i class="ri-gift-line"></i> <?php echo esc_html(self::get_label('belohnungen_title', $lang, 'Meine Belohnungen')); ?></h2>
                <p><?php echo esc_html(self::get_label('belohnungen_subtitle', $lang, 'Wähle eine Belohnung und löse ein')); ?></p>
            </div>

            <!-- USER POINTS CARD -->
            <div class="ppv-user-points-card">
                <div class="points-value"><?php echo intval($points); ?></div>
                <div class="points-label"><?php echo esc_html(self::get_label('my_points', $lang, 'Meine Punkte')); ?></div>
            </div>

            <!-- SEARCH & FILTER -->
            <div id="ppv-search-filter-container"></div>

            <!-- REWARDS GRID -->
            <div class="ppv-reward-grid">
                <?php if (empty($rewards)): ?>
                    <p class="ppv-empty"><i class="ri-inbox-line"></i> <?php echo esc_html(self::get_label('no_rewards', $lang, 'Keine Belohnungen vorhanden')); ?></p>
                <?php else: ?>
                    <?php foreach ($rewards as $r):
                        $can_redeem = $points >= $r->required_points;
                        $missing = $r->required_points - $points;
                        $progress = $points > 0 ? min(($points / $r->required_points) * 100, 100) : 0;
                    ?>
                        <div class="ppv-reward-card <?php echo $can_redeem ? 'available' : 'locked'; ?>" data-store="<?php echo esc_attr($r->store_id); ?>">
                            <div class="reward-header">
                                <h4><?php echo esc_html($r->title); ?></h4>
                                <small><i class="ri-store-2-line"></i> <?php echo esc_html($r->store_name ?: self::get_label('general', $lang, 'Allgemein')); ?></small>
                            </div>
                            
                            <p><?php echo esc_html($r->description ?: ''); ?></p>
                            
                            <div class="reward-meta">
                                <i class="ri-coin-line"></i>
                                <span><?php echo intval($r->required_points); ?> <?php echo esc_html(self::get_label('points', $lang, 'Punkte')); ?></span>
                            </div>

                            <!-- PROGRESS BAR -->
                            <div>
                                <div class="ppv-progress-container">
                                    <div class="ppv-progress-bar" style="width: <?php echo intval($progress); ?>%"></div>
                                </div>
                                <div class="ppv-progress-text">
                                    <?php echo intval($points); ?> / <?php echo intval($r->required_points); ?>
                                    (<?php echo intval($progress); ?>%)
                                    <?php if (!$can_redeem): ?>
                                        - <strong><?php echo intval($missing); ?> <?php echo esc_html(self::get_label('points_missing', $lang, 'Punkte fehlen')); ?></strong>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- BUTTON -->
                            <?php if ($can_redeem): ?>
                                <button class="ppv-redeem-btn"
                                        data-id="<?php echo esc_attr($r->id); ?>"
                                        data-store="<?php echo esc_attr($r->store_id); ?>"
                                        data-user="<?php echo esc_attr($user_id); ?>"
                                        data-title="<?php echo esc_attr($r->title); ?>">
                                    <i class="ri-check-double-line"></i> 
                                    <?php echo esc_html(self::get_label('redeem_button', $lang, 'Bevältsen')); ?>
                                </button>
                            <?php else: ?>
                                <button class="ppv-redeem-btn disabled" disabled>
                                    <i class="ri-lock-line"></i> 
                                    <?php echo intval($missing); ?> <?php echo esc_html(self::get_label('points_missing', $lang, 'Punkte fehlen')); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- PENDING SECTION -->
            <?php if (!empty($pending)): ?>
                <div class="ppv-pending-section">
                    <h3><i class="ri-time-line"></i> <?php echo esc_html(self::get_label('pending_section', $lang, 'Meine Anfragen')); ?></h3>
                    <div class="ppv-pending-grid">
                        <?php foreach ($pending as $p): 
                            $status_lower = strtolower($p->status);
                            $status_class = in_array($status_lower, ['pending', 'offen']) ? 'pending' : ($status_lower === 'approved' || $status_lower === 'bestätigt' ? 'approved' : 'cancelled');
                        ?>
                            <div class="ppv-pending-card status-<?php echo esc_attr($status_class); ?>">
                                <h4><?php echo esc_html($p->title); ?></h4>
                                <small><i class="ri-store-2-line"></i> <?php echo esc_html($p->store_name ?: self::get_label('general', $lang, 'Allgemein')); ?></small>
                                
                                <?php if (!empty($p->description)): ?>
                                    <p><?php echo esc_html($p->description); ?></p>
                                <?php endif; ?>

                                <div class="pending-meta">
                                    <span class="status-badge <?php echo esc_attr($status_class); ?>">
                                        <?php
                                        if ($status_lower === 'pending' || $status_lower === 'offen') {
                                            echo '<i class="ri-time-line"></i> ' . esc_html(self::get_label('status_pending', $lang, 'Awaiting'));
                                        } elseif ($status_lower === 'approved' || $status_lower === 'bestätigt') {
                                            echo '<i class="ri-check-line"></i> ' . esc_html(self::get_label('status_approved', $lang, 'Megerősítve'));
                                        } elseif ($status_lower === 'cancelled' || $status_lower === 'abgelehnt') {
                                            echo '<i class="ri-close-line"></i> ' . esc_html(self::get_label('status_cancelled', $lang, 'Elutasítva'));
                                        }
                                        ?>
                                    </span>
                                    <span class="date-badge"><?php echo esc_html(date('d.m.Y H:i', strtotime($p->redeemed_at))); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
        <?php
        $content = ob_get_clean();
        $content .= do_shortcode('[ppv_bottom_nav]');
        return $content;
    }

    public static function rest_redeem_reward($request) {
        global $wpdb;
        self::ensure_session();

        $data = $request->get_json_params();
        if (!is_array($data)) {
            return new WP_REST_Response(['success' => false], 400);
        }

        $user_id   = intval($data['user_id'] ?? 0);
        $reward_id = intval($data['reward_id'] ?? 0);

        if (!$user_id || !$reward_id) {
            return new WP_REST_Response(['success' => false], 400);
        }

        if (!isset($_SESSION['ppv_store_id'])) {
            $nonce = $request->get_header('x-wp-nonce');
            if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_REST_Response(['success' => false], 403);
            }
        }

        $reward = $wpdb->get_row($wpdb->prepare("
            SELECT id, store_id, required_points 
            FROM {$wpdb->prefix}ppv_rewards 
            WHERE id = %d
        ", $reward_id));

        if (!$reward) {
            return new WP_REST_Response(['success' => false], 404);
        }

        $wpdb->query('START TRANSACTION');

        try {
            $pending = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}ppv_rewards_redeemed
                WHERE user_id = %d AND reward_id = %d AND status IN ('pending', 'offen')
                FOR UPDATE
            ", $user_id, $reward_id));

            if ($pending > 0) {
                $wpdb->query('ROLLBACK');
                return new WP_REST_Response(['success' => false], 400);
            }

            $user_points = intval($wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}ppv_points
                WHERE user_id = %d
                FOR UPDATE
            ", $user_id)));

            if ($user_points < $reward->required_points) {
                $wpdb->query('ROLLBACK');
                return new WP_REST_Response(['success' => false], 400);
            }

            $inserted = $wpdb->insert(
                "{$wpdb->prefix}ppv_rewards_redeemed",
                [
                    'user_id'      => $user_id,
                    'store_id'     => $reward->store_id,
                    'reward_id'    => $reward_id,
                    'points_spent' => $reward->required_points,
                    'status'       => 'pending',
                    'redeemed_at'  => current_time('mysql')
                ],
                ['%d', '%d', '%d', '%d', '%s', '%s']
            );

            if (!$inserted) {
                $wpdb->query('ROLLBACK');
                return new WP_REST_Response(['success' => false], 500);
            }

            $wpdb->query('COMMIT');
            update_option('ppv_last_redeem_update', time());

            return new WP_REST_Response([
                'success' => true,
                'redeem_id' => $wpdb->insert_id
            ], 200);

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response(['success' => false], 500);
        }
    }
}

PPV_Belohnungen::hooks();