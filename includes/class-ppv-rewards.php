<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Einlösungen Admin Dashboard v2.0
 * Modern design - Dashboard + Napló + Bizonylatok
 *
 * Shortcode: [ppv_rewards]
 *
 * Features:
 * - Dashboard statisztikák (Heute/Woche/Monat/Wert)
 * - Beváltás napló kártyákkal
 * - Approve/Cancel funkciók
 * - Bizonylatok tab (havi generálás)
 * - Ably real-time support
 * - Filiale support
 */

class PPV_Rewards {

    public static function hooks() {
        add_shortcode('ppv_rewards', [__CLASS__, 'render_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    /** ============================================================
     *  REST ENDPOINTS
     * ============================================================ */
    public static function register_rest_routes() {
        // Dashboard stats
        register_rest_route('ppv/v1', '/einloesungen/stats', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_stats'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Einlösungen lista (pending + approved)
        register_rest_route('ppv/v1', '/einloesungen/list', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_list_einloesungen'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Update status (approve/cancel)
        register_rest_route('ppv/v1', '/einloesungen/update', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_update_status'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Recent logs
        register_rest_route('ppv/v1', '/einloesungen/log', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_logs'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Bizonylatok lista
        register_rest_route('ppv/v1', '/einloesungen/receipts', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_receipts'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Havi bizonylat generálás
        register_rest_route('ppv/v1', '/einloesungen/monthly-receipt', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_monthly_receipt'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Egyedi bizonylat generálás
        register_rest_route('ppv/v1', '/einloesungen/generate-receipt', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_single_receipt'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Dátum szerinti bizonylat generálás
        register_rest_route('ppv/v1', '/einloesungen/date-receipt', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_date_receipt'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // === LEGACY ENDPOINTS (backward compatibility) ===
        register_rest_route('ppv/v1', '/redeem/list', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_list_einloesungen'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        register_rest_route('ppv/v1', '/redeem/update', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_update_status'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        register_rest_route('ppv/v1', '/redeem/log', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_logs'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        register_rest_route('ppv/v1', '/redeem/monthly-receipt', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_monthly_receipt'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);
    }

    /** ============================================================
     *  GET STORE ID (Filiale support)
     * ============================================================ */
    private static function get_store_id() {
        global $wpdb;

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return intval($_SESSION['ppv_current_filiale_id']);
        }

        // Session - base store
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        // Vendor store fallback
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return intval($_SESSION['ppv_vendor_store_id']);
        }

        // Logged in user
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                $uid
            ));
            if ($store_id) {
                $_SESSION['ppv_store_id'] = $store_id;
                return intval($store_id);
            }
        }

        return 0;
    }

    /** ============================================================
     *  ASSETS
     * ============================================================ */
    public static function enqueue_assets() {
        global $post;
        if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ppv_rewards')) {
            return;
        }

        $plugin_url = defined('PPV_PLUGIN_URL') ? PPV_PLUGIN_URL : plugin_dir_url(dirname(__FILE__));
        $store_id = self::get_store_id();

        // Theme loader
        wp_enqueue_script('ppv-theme-loader', $plugin_url . 'assets/js/ppv-theme-loader.js', [], time(), false);

        // Fonts & Icons
        wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', [], null);
        wp_enqueue_style('remix-icon', 'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css', [], '3.5.0');

        // Ably (if enabled)
        $dependencies = [];
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            wp_enqueue_script('ably-js', 'https://cdn.ably.com/lib/ably.min-1.js', [], '1.2', true);
            $dependencies[] = 'ably-js';
        }

        // Main JS
        wp_enqueue_script('ppv-rewards', $plugin_url . 'assets/js/ppv-rewards.js', $dependencies, time(), true);

        // Config
        $config = [
            'base' => esc_url(rest_url('ppv/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'store_id' => $store_id,
            'plugin_url' => esc_url($plugin_url),
        ];

        // Ably config
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            $config['ably'] = [
                'key' => PPV_Ably::get_key(),
                'channel' => 'store-' . $store_id,
            ];
        }

        wp_add_inline_script('ppv-rewards', 'window.ppv_rewards_config = ' . wp_json_encode($config) . ';', 'before');

        // Translations
        if (class_exists('PPV_Lang')) {
            wp_add_inline_script('ppv-rewards', 'window.ppv_lang = ' . wp_json_encode(PPV_Lang::$strings) . ';', 'before');
        }
    }

    /** ============================================================
     *  RENDER PAGE
     * ============================================================ */
    public static function render_page() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        global $wpdb;
        $store_id = self::get_store_id();

        if (!$store_id) {
            return '<div class="ppv-warning"><i class="ri-error-warning-line"></i> Bitte anmelden oder Store aktivieren.</div>';
        }

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, company_name, country FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));

        if (!$store) {
            return '<div class="ppv-warning"><i class="ri-error-warning-line"></i> Store nicht gefunden.</div>';
        }

        // Currency
        $currency_map = ['DE' => 'EUR', 'HU' => 'HUF', 'RO' => 'RON'];
        $currency = $currency_map[$store->country] ?? 'EUR';

        ob_start();
        ?>
        <script>window.PPV_STORE_ID = <?php echo intval($store->id); ?>;</script>

        <div class="ppv-einloesungen-admin" data-store-id="<?php echo esc_attr($store->id); ?>" data-currency="<?php echo esc_attr($currency); ?>">

            <!-- HEADER -->
            <div class="ppv-ea-header">
                <div class="ppv-ea-header-left">
                    <h1><i class="ri-exchange-funds-line"></i> Einlösungen</h1>
                    <span class="ppv-ea-store-name"><?php echo esc_html($store->company_name); ?></span>
                </div>
                <div class="ppv-ea-header-right">
                    <button class="ppv-ea-refresh-btn" id="ppv-ea-refresh">
                        <i class="ri-refresh-line"></i>
                    </button>
                </div>
            </div>

            <!-- DASHBOARD STATS -->
            <div class="ppv-ea-stats" id="ppv-ea-stats">
                <div class="ppv-ea-stat-card">
                    <span class="ppv-ea-stat-value" id="stat-heute">-</span>
                    <span class="ppv-ea-stat-label">Heute</span>
                </div>
                <div class="ppv-ea-stat-card">
                    <span class="ppv-ea-stat-value" id="stat-woche">-</span>
                    <span class="ppv-ea-stat-label">Woche</span>
                </div>
                <div class="ppv-ea-stat-card">
                    <span class="ppv-ea-stat-value" id="stat-monat">-</span>
                    <span class="ppv-ea-stat-label">Monat</span>
                </div>
                <div class="ppv-ea-stat-card ppv-ea-stat-wert">
                    <span class="ppv-ea-stat-value" id="stat-wert">-</span>
                    <span class="ppv-ea-stat-label">Wert</span>
                </div>
            </div>

            <!-- TABS -->
            <div class="ppv-ea-tabs">
                <button class="ppv-ea-tab active" data-tab="pending">
                    <i class="ri-time-line"></i> Ausstehend
                    <span class="ppv-ea-tab-badge" id="pending-count">0</span>
                </button>
                <button class="ppv-ea-tab" data-tab="history">
                    <i class="ri-history-line"></i> Verlauf
                </button>
                <button class="ppv-ea-tab" data-tab="receipts">
                    <i class="ri-file-list-3-line"></i> Belege
                </button>
            </div>

            <!-- TAB CONTENT: PENDING -->
            <div class="ppv-ea-tab-content active" id="tab-pending">
                <div class="ppv-ea-list" id="ppv-ea-pending-list">
                    <div class="ppv-ea-loading">
                        <i class="ri-loader-4-line ri-spin"></i> Lade...
                    </div>
                </div>
            </div>

            <!-- TAB CONTENT: HISTORY -->
            <div class="ppv-ea-tab-content" id="tab-history">
                <div class="ppv-ea-filter-bar">
                    <select id="ppv-ea-filter-status" class="ppv-ea-filter-select">
                        <option value="all">Alle Status</option>
                        <option value="approved">Bestätigt</option>
                        <option value="cancelled">Abgelehnt</option>
                    </select>
                    <input type="date" id="ppv-ea-filter-date" class="ppv-ea-filter-date">
                </div>
                <div class="ppv-ea-list" id="ppv-ea-history-list">
                    <div class="ppv-ea-loading">
                        <i class="ri-loader-4-line ri-spin"></i> Lade...
                    </div>
                </div>
            </div>

            <!-- TAB CONTENT: RECEIPTS -->
            <div class="ppv-ea-tab-content" id="tab-receipts">
                <!-- Receipt Generators -->
                <div class="ppv-ea-receipt-generators">
                    <!-- Monthly Receipt Generator -->
                    <div class="ppv-ea-receipt-generator">
                        <h3><i class="ri-calendar-line"></i> Monatsbericht</h3>
                        <div class="ppv-ea-receipt-form">
                            <select id="ppv-ea-receipt-month" class="ppv-ea-select">
                                <?php
                                $months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
                                $current_month = (int)date('n');
                                for ($i = 1; $i <= 12; $i++):
                                    $selected = ($i === $current_month) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $i; ?>" <?php echo $selected; ?>><?php echo $months[$i-1]; ?></option>
                                <?php endfor; ?>
                            </select>
                            <select id="ppv-ea-receipt-year" class="ppv-ea-select">
                                <?php
                                $current_year = (int)date('Y');
                                for ($y = $current_year; $y >= $current_year - 2; $y--):
                                ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                            <button id="ppv-ea-generate-receipt" class="ppv-ea-btn-primary">
                                <i class="ri-file-add-line"></i> Erstellen
                            </button>
                        </div>
                    </div>

                    <!-- Date Range Receipt Generator -->
                    <div class="ppv-ea-receipt-generator">
                        <h3><i class="ri-calendar-check-line"></i> Zeitraumbericht</h3>
                        <div class="ppv-ea-receipt-form ppv-ea-date-range-form">
                            <div class="ppv-ea-date-range">
                                <input type="date" id="ppv-ea-receipt-date-from" class="ppv-ea-select" value="<?php echo date('Y-m-01'); ?>">
                                <span class="ppv-ea-date-separator">bis</span>
                                <input type="date" id="ppv-ea-receipt-date-to" class="ppv-ea-select" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <button id="ppv-ea-generate-date-receipt" class="ppv-ea-btn-primary">
                                <i class="ri-file-add-line"></i> Erstellen
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Receipts List -->
                <div class="ppv-ea-receipts-list" id="ppv-ea-receipts-list">
                    <div class="ppv-ea-loading">
                        <i class="ri-loader-4-line ri-spin"></i> Lade Belege...
                    </div>
                </div>
            </div>

        </div>

        <?php
        // Bottom nav
        if (class_exists('PPV_Bottom_Nav')) {
            echo PPV_Bottom_Nav::render_nav();
        } else {
            echo do_shortcode('[ppv_bottom_nav]');
        }

        return ob_get_clean();
    }

    /** ============================================================
     *  REST: Get Dashboard Stats
     * ============================================================ */
    public static function rest_get_stats($request) {
        global $wpdb;

        $store_id = self::get_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'No store ID'], 400);
        }

        $table = $wpdb->prefix . 'ppv_rewards_redeemed';

        // Today
        $heute = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table}
            WHERE store_id = %d AND status = 'approved' AND DATE(redeemed_at) = CURDATE()
        ", $store_id));

        // This week
        $woche = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table}
            WHERE store_id = %d AND status = 'approved' AND YEARWEEK(redeemed_at, 1) = YEARWEEK(CURDATE(), 1)
        ", $store_id));

        // This month
        $monat = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table}
            WHERE store_id = %d AND status = 'approved' AND YEAR(redeemed_at) = YEAR(CURDATE()) AND MONTH(redeemed_at) = MONTH(CURDATE())
        ", $store_id));

        // Total value this month - calculated from reward
        // Priority: actual_amount → action_value → free_product_value
        $rewards_table = $wpdb->prefix . 'ppv_rewards';
        $wert = (float)$wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(
                CASE
                    WHEN r.actual_amount IS NOT NULL AND r.actual_amount > 0 THEN r.actual_amount
                    WHEN rw.action_value IS NOT NULL AND rw.action_value != '' AND rw.action_value != '0' THEN CAST(rw.action_value AS DECIMAL(10,2))
                    WHEN rw.free_product_value IS NOT NULL AND rw.free_product_value > 0 THEN rw.free_product_value
                    ELSE 0
                END
            ), 0)
            FROM {$table} r
            LEFT JOIN {$rewards_table} rw ON r.reward_id = rw.id
            WHERE r.store_id = %d AND r.status = 'approved'
            AND YEAR(r.redeemed_at) = YEAR(CURDATE())
            AND MONTH(r.redeemed_at) = MONTH(CURDATE())
        ", $store_id));

        // Pending count
        $pending = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table}
            WHERE store_id = %d AND status = 'pending'
        ", $store_id));

        // Currency
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT country FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));
        $currency_map = ['DE' => 'EUR', 'HU' => 'HUF', 'RO' => 'RON'];
        $currency = $currency_map[$store->country ?? 'DE'] ?? 'EUR';

        return new WP_REST_Response([
            'success' => true,
            'stats' => [
                'heute' => $heute,
                'woche' => $woche,
                'monat' => $monat,
                'wert' => $wert,
                'currency' => $currency,
                'pending' => $pending,
            ]
        ], 200);
    }

    /** ============================================================
     *  REST: List Einlösungen
     * ============================================================ */
    public static function rest_list_einloesungen($request) {
        global $wpdb;

        $store_id = self::get_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'items' => []], 400);
        }

        $status = sanitize_text_field($request->get_param('status') ?? 'all');
        $date = sanitize_text_field($request->get_param('date') ?? '');
        $limit = intval($request->get_param('limit') ?? 50);

        $where = "r.store_id = %d";
        $params = [$store_id];

        if ($status === 'pending') {
            $where .= " AND r.status = 'pending'";
        } elseif ($status === 'approved') {
            $where .= " AND r.status = 'approved'";
        } elseif ($status === 'cancelled') {
            $where .= " AND r.status = 'cancelled'";
        } elseif ($status === 'history') {
            $where .= " AND r.status IN ('approved', 'cancelled')";
        }

        if ($date) {
            $where .= " AND DATE(r.redeemed_at) = %s";
            $params[] = $date;
        }

        $params[] = $limit;

        $items = $wpdb->get_results($wpdb->prepare("
            SELECT
                r.id,
                r.user_id,
                r.store_id,
                r.reward_id,
                r.points_spent,
                r.actual_amount,
                r.status,
                r.redeemed_at,
                r.receipt_pdf_path,
                rw.title AS reward_title,
                u.email AS user_email,
                u.first_name,
                u.last_name
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
            WHERE {$where}
            ORDER BY r.redeemed_at DESC
            LIMIT %d
        ", ...$params));

        return new WP_REST_Response([
            'success' => true,
            'items' => $items ?: [],
            'count' => count($items)
        ], 200);
    }

    /** ============================================================
     *  REST: Update Status (Approve/Cancel)
     * ============================================================ */
    public static function rest_update_status($request) {
        global $wpdb;

        $data = $request->get_json_params();
        $id = intval($data['id'] ?? 0);
        $status = sanitize_text_field($data['status'] ?? '');
        $store_id = self::get_store_id();

        if (!$id || !$status || !in_array($status, ['approved', 'cancelled'])) {
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid data'], 400);
        }

        // Check current status
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}ppv_rewards_redeemed WHERE id = %d AND store_id = %d",
            $id, $store_id
        ));

        if ($current === $status) {
            return new WP_REST_Response(['success' => true, 'message' => 'No change'], 200);
        }

        // Update status
        $wpdb->update(
            $wpdb->prefix . 'ppv_rewards_redeemed',
            ['status' => $status, 'redeemed_at' => current_time('mysql')],
            ['id' => $id, 'store_id' => $store_id],
            ['%s', '%s'],
            ['%d', '%d']
        );

        if ($wpdb->last_error) {
            return new WP_REST_Response(['success' => false, 'message' => 'DB error'], 500);
        }

        $receipt_path = null;
        $receipt_url = null;

        // If approved: deduct points + generate receipt
        if ($status === 'approved') {
            self::deduct_points($id, $store_id);

            // Generate receipt
            if (class_exists('PPV_Expense_Receipt')) {
                $receipt_path = PPV_Expense_Receipt::generate_for_redeem($id);
                if ($receipt_path) {
                    $receipt_url = PPV_Expense_Receipt::get_receipt_url($receipt_path);
                }
            }

            // Ably notification
            if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
                $redeem = $wpdb->get_row($wpdb->prepare("
                    SELECT r.user_id, r.points_spent, rw.title AS reward_title
                    FROM {$wpdb->prefix}ppv_rewards_redeemed r
                    LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
                    WHERE r.id = %d
                ", $id));

                if ($redeem && $redeem->user_id) {
                    PPV_Ably::trigger_reward_approved($redeem->user_id, [
                        'redeem_id' => $id,
                        'status' => $status,
                        'reward_name' => $redeem->reward_title,
                        'points_spent' => $redeem->points_spent,
                    ]);
                }
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => $status === 'approved' ? 'Bestätigt' : 'Abgelehnt',
            'receipt_url' => $receipt_url,
        ], 200);
    }

    /** ============================================================
     *  REST: Get Logs (History)
     * ============================================================ */
    public static function rest_get_logs($request) {
        global $wpdb;

        $store_id = self::get_store_id();
        $limit = intval($request->get_param('limit') ?? 20);

        $items = $wpdb->get_results($wpdb->prepare("
            SELECT
                r.id,
                r.points_spent,
                r.actual_amount,
                r.status,
                r.redeemed_at,
                r.receipt_pdf_path,
                rw.title AS reward_title,
                u.email AS user_email,
                u.first_name,
                u.last_name
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
            WHERE r.store_id = %d AND r.status IN ('approved', 'cancelled')
            ORDER BY r.redeemed_at DESC
            LIMIT %d
        ", $store_id, $limit));

        return new WP_REST_Response([
            'success' => true,
            'items' => $items ?: []
        ], 200);
    }

    /** ============================================================
     *  REST: Get Receipts List
     * ============================================================ */
    public static function rest_get_receipts($request) {
        global $wpdb;

        $store_id = self::get_store_id();

        // Show ALL approved redemptions, not just those with receipt_pdf_path
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT
                r.id,
                r.points_spent,
                r.actual_amount,
                r.redeemed_at,
                r.receipt_pdf_path,
                rw.title AS reward_title,
                u.email AS user_email,
                u.first_name,
                u.last_name
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
            WHERE r.store_id = %d AND r.status = 'approved'
            ORDER BY r.redeemed_at DESC
            LIMIT 50
        ", $store_id));

        // Get upload URL for receipt links
        $upload = wp_upload_dir();
        $base_url = $upload['baseurl'];

        return new WP_REST_Response([
            'success' => true,
            'items' => $items ?: [],
            'base_url' => $base_url
        ], 200);
    }

    /** ============================================================
     *  REST: Generate Monthly Receipt
     * ============================================================ */
    public static function rest_generate_monthly_receipt($request) {
        $data = $request->get_json_params();
        $store_id = self::get_store_id();
        $year = intval($data['year'] ?? date('Y'));
        $month = intval($data['month'] ?? date('m'));

        if (!$store_id || $month < 1 || $month > 12) {
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid parameters'], 400);
        }

        if (!class_exists('PPV_Expense_Receipt')) {
            $file = PPV_PLUGIN_DIR . 'includes/class-ppv-expense-receipt.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                return new WP_REST_Response(['success' => false, 'message' => 'Receipt class not found'], 500);
            }
        }

        $receipt_path = PPV_Expense_Receipt::generate_monthly_receipt($store_id, $year, $month);

        if (!$receipt_path) {
            return new WP_REST_Response(['success' => false, 'message' => 'Keine Einlösungen für diesen Zeitraum'], 400);
        }

        $upload = wp_upload_dir();
        $receipt_url = $upload['baseurl'] . '/' . $receipt_path;

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Monatsbericht erstellt',
            'receipt_url' => $receipt_url,
        ], 200);
    }

    /** ============================================================
     *  REST: Generate Single Receipt
     * ============================================================ */
    public static function rest_generate_single_receipt($request) {
        $data = $request->get_json_params();
        $redeem_id = intval($data['redeem_id'] ?? 0);

        if (!$redeem_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Keine Redeem-ID'], 400);
        }

        if (!class_exists('PPV_Expense_Receipt')) {
            $file = PPV_PLUGIN_DIR . 'includes/class-ppv-expense-receipt.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                return new WP_REST_Response(['success' => false, 'message' => 'Receipt class not found'], 500);
            }
        }

        $receipt_path = PPV_Expense_Receipt::generate_for_redeem($redeem_id);

        if (!$receipt_path) {
            return new WP_REST_Response(['success' => false, 'message' => 'Beleg konnte nicht erstellt werden'], 400);
        }

        $upload = wp_upload_dir();
        $receipt_url = $upload['baseurl'] . '/' . $receipt_path;

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Beleg erstellt',
            'receipt_url' => $receipt_url,
        ], 200);
    }

    /** ============================================================
     *  REST: Generate Receipt for date range (Zeitraumbericht)
     * ============================================================ */
    public static function rest_generate_date_receipt($request) {
        $data = $request->get_json_params();
        $store_id = self::get_store_id();
        $date_from = sanitize_text_field($data['date_from'] ?? '');
        $date_to = sanitize_text_field($data['date_to'] ?? '');

        if (!$store_id || !$date_from || !$date_to) {
            return new WP_REST_Response(['success' => false, 'message' => 'Ungültige Parameter'], 400);
        }

        // Validate date format
        if (!strtotime($date_from) || !strtotime($date_to)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Ungültiges Datumsformat'], 400);
        }

        if (!class_exists('PPV_Expense_Receipt')) {
            $file = PPV_PLUGIN_DIR . 'includes/class-ppv-expense-receipt.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                return new WP_REST_Response(['success' => false, 'message' => 'Receipt class not found'], 500);
            }
        }

        $receipt_path = PPV_Expense_Receipt::generate_date_range_receipt($store_id, $date_from, $date_to);

        if (!$receipt_path) {
            return new WP_REST_Response(['success' => false, 'message' => 'Keine Einlösungen für diesen Zeitraum'], 400);
        }

        $upload = wp_upload_dir();
        $receipt_url = $upload['baseurl'] . '/' . $receipt_path;

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Zeitraumbericht erstellt',
            'receipt_url' => $receipt_url,
        ], 200);
    }

    /** ============================================================
     *  PRIVATE: Deduct Points
     * ============================================================ */
    private static function deduct_points($redeem_id, $store_id) {
        global $wpdb;

        $redeem = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, points_spent FROM {$wpdb->prefix}ppv_rewards_redeemed WHERE id = %d",
            $redeem_id
        ), ARRAY_A);

        if (!$redeem) return false;

        $user_id = intval($redeem['user_id']);
        $points = intval($redeem['points_spent']);

        // Check if already deducted
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points WHERE reference = %s
        ", 'REDEEM-' . $redeem_id));

        if ($existing > 0) return false;

        // Deduct
        $wpdb->insert($wpdb->prefix . 'ppv_points', [
            'user_id' => $user_id,
            'store_id' => $store_id,
            'points' => -abs($points),
            'type' => 'redeem',
            'reference' => 'REDEEM-' . $redeem_id,
            'created' => current_time('mysql')
        ], ['%d', '%d', '%d', '%s', '%s', '%s']);

        return true;
    }
}

// Initialize
PPV_Rewards::hooks();
