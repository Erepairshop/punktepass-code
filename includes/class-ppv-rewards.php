<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Einlösungen Verwaltung (Redeem Overview Only)
 * Version: 5.3 – FRISSÍTETT: Auto Kiadási Bizonylat Generálás + PPV_Lang
 * ✅ CSAK Einlösungen (approve/cancel/logs)
 * ✅ Invoice endpoints
 * ✅ Approve után → AUTO Bizonylat generálás
 * ✅ Receipt PDF path és URL visszaadás
 * ✅ Teljes PPV_Lang fordítás támogatás
 */

class PPV_Rewards {

    public static function hooks() {
        add_shortcode('ppv_rewards', [__CLASS__, 'render_rewards_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    /** ============================================================
     *  🔡 REST ENDPOINTS
     * ============================================================ */
    public static function register_rest_routes() {
        // Einlösungen lista
        register_rest_route('ppv/v1', '/redeem/list', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_list_redeems'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Einlösung jóváhagyás/elutasítás
        register_rest_route('ppv/v1', '/redeem/update', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_update_redeem'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Recent logs
        register_rest_route('ppv/v1', '/redeem/log', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_recent_logs'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Havi bizonylat generálás
        register_rest_route('ppv/v1', '/redeem/monthly-receipt', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_monthly_receipt'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);
    }

    /** ============================================================
     *  🎨 ASSETS
     * ============================================================ */
    public static function enqueue_assets() {
        // ✅ CSAK akkor töltse be, ha az oldal tartalmazza a shortcode-ot!
        global $post;
        if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ppv_rewards')) {
            return; // Skip loading
        }

        wp_enqueue_script(
            'ppv-rewards',
            PPV_PLUGIN_URL . 'assets/js/ppv-rewards.js',
            ['jquery'],
            time(),
            true
        );

        $payload = [
            'base'     => esc_url(rest_url('ppv/v1/')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'store_id' => self::get_store_id(),
            'plugin_url' => esc_url(PPV_PLUGIN_URL)
        ];

        wp_add_inline_script(
            'ppv-rewards',
            "window.ppv_rewards_rest = " . wp_json_encode($payload) . ";
window.ppv_receipts_rest = " . wp_json_encode($payload) . ";
window.ppv_plugin_url = '" . esc_url(PPV_PLUGIN_URL) . "';",
            'before'
        );

        // 🌍 FORDÍTÁSOK - PPV_Lang betöltése
        if (class_exists('PPV_Lang')) {
            wp_add_inline_script(
                'ppv-rewards',
                "window.ppv_lang = " . wp_json_encode(PPV_Lang::$strings) . ";",
                'before'
            );
        }
    }

    /** ============================================================
     *  🔐 GET STORE ID
     * ============================================================ */
    private static function get_store_id() {
        global $wpdb;

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return intval($_SESSION['ppv_current_filiale_id']);
        }

        // 1️⃣ Session - base store
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        // 1.5️⃣ Vendor store fallback
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return intval($_SESSION['ppv_vendor_store_id']);
        }

        // 2️⃣ Logged in user
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

        // 3️⃣ Global fallback
        if (!empty($GLOBALS['ppv_active_store'])) {
            $active = $GLOBALS['ppv_active_store'];
            return is_object($active) ? intval($active->id) : intval($active);
        }

        return 0;
    }

    /** ============================================================
     *  🎨 FRONTEND RENDER - JAVÍTOTT HTML STRUKTÚRA
     * ============================================================ */
    public static function render_rewards_page() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        global $wpdb;
        $store_id = self::get_store_id();

        if (!$store_id) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('rewards_login_required') : 'Bitte anmelden oder POS aktivieren.';
            return '<div class="ppv-warning">⚠️ ' . esc_html($msg) . '</div>';
        }

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, company_name FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));

        if (!$store) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('store_not_found') : 'Store nicht gefunden.';
            return '<div class="ppv-warning">⚠️ ' . esc_html($msg) . '</div>';
        }

        ob_start();
        ?>
        <script>window.PPV_STORE_ID = <?php echo intval($store->id); ?>;</script>

        <div class="ppv-rewards-wrapper glass-section">
            <h2 style="font-size: 18px; margin-bottom: 16px;"><i class="ri-checkbox-circle-line"></i> <?php echo esc_html(PPV_Lang::t('redeem_management_title') ?: 'Einlösungen Verwaltung – '); ?><?php echo esc_html($store->company_name); ?></h2>

            <!-- TAB MENU -->
            <div class="ppv-rewards-tabs" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #ddd;">
                <button class="ppv-tab-btn ppv-tab-active" data-tab="redeem" style="padding: 12px 20px; background: none; border: none; font-size: 14px; font-weight: 600; cursor: pointer; border-bottom: 3px solid #0066cc; color: #0066cc;">
                    <i class="ri-checkbox-circle-line"></i> <?php echo esc_html(PPV_Lang::t('redeem_tab_redeems') ?: 'Einlösungen'); ?>
                </button>
                <button class="ppv-tab-btn" data-tab="receipts" style="padding: 12px 20px; background: none; border: none; font-size: 14px; font-weight: 600; cursor: pointer; border-bottom: 3px solid transparent; color: #666;">
                    <i class="ri-file-text-line"></i> <?php echo esc_html(PPV_Lang::t('redeem_tab_receipts') ?: 'Bizonylatok'); ?>
                </button>
            </div>

            <!-- TAB CONTENT: REDEEM -->
            <div id="ppv-tab-redeem" class="ppv-tab-content" style="display: block;">
                <!-- EINLÖSUNGEN LISTE -->
                <div class="ppv-redeem-section">
                    <h3><i class="ri-list-check-2"></i> <?php echo esc_html(PPV_Lang::t('redeem_list_title') ?: 'Einlösungen'); ?></h3>
                    <div id="ppv-redeem-list" class="ppv-redeem-grid">
                        <p class="ppv-loading"><i class="ri-loader-4-line ri-spin"></i> <?php echo esc_html(PPV_Lang::t('redeem_loading') ?: 'Lade...'); ?></p>
                    </div>
                </div>

                <!-- RECENT LOGS -->
                <div id="ppv-redeem-log" class="ppv-log-box" style="margin-top:30px;">
                    <h3><i class="ri-history-line"></i> <?php echo esc_html(PPV_Lang::t('redeem_recent_logs') ?: 'Letzte 10 Einlösungen'); ?></h3>
                    <div id="ppv-log-list">
                        <p><i class="ri-loader-4-line ri-spin"></i> <?php echo esc_html(PPV_Lang::t('redeem_loading') ?: 'Lade...'); ?></p>
                    </div>
                </div>
            </div>

            <!-- TAB CONTENT: RECEIPTS -->
            <div id="ppv-tab-receipts" class="ppv-tab-content" style="display: none;">
                <div id="ppv-receipts-container">
                    <p class="ppv-loading"><i class="ri-loader-4-line ri-spin"></i> <?php echo esc_html(PPV_Lang::t('redeem_receipts_loading') ?: 'Bizonylatok betöltése...'); ?></p>
                </div>
            </div>
        </div>

        <?php
        
        if (class_exists('PPV_Bottom_Nav')) {
            echo PPV_Bottom_Nav::render_nav();
        } else {
            echo do_shortcode('[ppv_bottom_nav]');
        }
 
        return ob_get_clean();
    }

    /** ============================================================
     *  📋 REST – List Redeems (✅ WITH RECEIPT DATA!)
     * ============================================================ */
    public static function rest_list_redeems($request) {
        global $wpdb;

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 🏪 FILIALE SUPPORT: ALWAYS use session-aware store ID, ignore request parameter
        // The request parameter is cached by JavaScript and not reliable for FILIALE switching
        $store_id = self::get_store_id();

        // 🔍 DEBUG INFO for troubleshooting FILIALE issues
        $debug_info = [
            'request_store_id' => intval($request->get_param('store_id') ?: 0),
            'final_store_id' => $store_id,
            'session_ppv_current_filiale_id' => $_SESSION['ppv_current_filiale_id'] ?? 'NOT_SET',
            'session_ppv_store_id' => $_SESSION['ppv_store_id'] ?? 'NOT_SET',
            'session_ppv_vendor_store_id' => $_SESSION['ppv_vendor_store_id'] ?? 'NOT_SET',
        ];
        error_log("🔍 [rest_list_redeems] " . json_encode($debug_info));

        if (!$store_id) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('error_no_store') : 'Nincs Store ID';
            return new WP_REST_Response([
                'success' => false,
                'items' => [],
                'message' => '❌ ' . $msg,
                'debug' => $debug_info
            ], 400);
        }
        
        // ✅ PENDING + APPROVED ITEMS + RECEIPT INFO
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT 
                r.id,
                r.user_id,
                r.store_id,
                r.reward_id,
                r.points_spent,
                r.actual_amount,
                r.invoice_id,
                r.status,
                r.redeemed_at,
                r.receipt_pdf_path,
                rw.title as reward_title,
                u.email as user_email,
                inv.pdf_path as invoice_pdf_path,
                inv.invoice_number
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
            LEFT JOIN {$wpdb->prefix}ppv_invoices inv ON r.invoice_id = inv.id
            WHERE r.store_id = %d
            AND r.status IN ('pending', 'approved')
            ORDER BY r.redeemed_at DESC
            LIMIT 50
        ", $store_id));
        
        return new WP_REST_Response([
            'success' => true,
            'items' => $items ?: [],
            'count' => count($items),
            'debug' => $debug_info
        ], 200);
    }

    /** ============================================================
     *  ✅ REST – Update Redeem Status + AUTO RECEIPT GENERATION
     * ============================================================ */
    public static function rest_update_redeem($request) {
        global $wpdb;

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $data = $request->get_json_params();
        $id = intval($data['id'] ?? 0);
        $status = sanitize_text_field($data['status'] ?? '');

        // 🏪 FILIALE SUPPORT: ALWAYS use session-aware store ID, ignore request parameter
        $store_id = self::get_store_id();

        error_log("📝 [PPV_REWARDS] Update redeem #{$id} to status: {$status}, store_id: {$store_id}");

        if (!$id || !$status) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('error_invalid_data') : 'Ungültige Daten';
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ ' . $msg
            ], 400);
        }

        $table = $wpdb->prefix . 'ppv_rewards_redeemed';

        // Jelenlegi státusz ellenőrzése
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table WHERE id = %d",
            $id
        ));

        if ($current === $status) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('redeem_no_change') : 'Keine Statusänderung';
            return new WP_REST_Response([
                'success' => true,
                'message' => '🔌 ' . $msg
            ], 200);
        }

        // Státusz frissítése
        $wpdb->update(
            $table,
            [
                'status'      => $status,
                'redeemed_at' => current_time('mysql')
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        if ($wpdb->last_error) {
            error_log('❌ [PPV_REWARDS] Update Error: ' . $wpdb->last_error);
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('error_update_failed') : 'Update fehlgeschlagen';
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ ' . $msg
            ], 500);
        }

        error_log("✅ [PPV_REWARDS] Updated redeem #{$id} to {$status}");

        // ✅ RECEIPT GENERÁLÁS - APPROVED ESETÉN
        $receipt_path = null;
        $receipt_url = null;

        if ($status === 'approved') {
            // 1️⃣ Pontlevonás
            self::deduct_points_for_redeem($id, $store_id);

            // 2️⃣ BIZONYLAT GENERÁLÁS
            error_log("📄 [PPV_REWARDS] Bizonylat generálása: redeem #{$id}");

            // Betöltjük az Expense Receipt class-t
            $expense_receipt_file = PPV_PLUGIN_DIR . 'includes/class-ppv-expense-receipt.php';

            if (!file_exists($expense_receipt_file)) {
                error_log("❌ [PPV_REWARDS] Expense receipt class nem található: {$expense_receipt_file}");
            } else {
                require_once $expense_receipt_file;

                // Ellenőrizzük, hogy a class betöltődött-e
                if (!class_exists('PPV_Expense_Receipt')) {
                    error_log("❌ [PPV_REWARDS] PPV_Expense_Receipt class nem létezik a fájl betöltése után");
                } else {
                    // Generáljuk a bizonylatot
                    $receipt_path = PPV_Expense_Receipt::generate_for_redeem($id);

                    if ($receipt_path) {
                        $receipt_url = PPV_Expense_Receipt::get_receipt_url($receipt_path);
                        error_log("✅ [PPV_REWARDS] Bizonylat sikeres: {$receipt_path}");
                    } else {
                        error_log("❌ [PPV_REWARDS] Bizonylat generálás sikertelen: #{$id}");
                    }
                }
            }
        }

        $msg_approved = class_exists('PPV_Lang') ? PPV_Lang::t('redeem_approved') : 'Anfrage bestätigt';
        $msg_cancelled = class_exists('PPV_Lang') ? PPV_Lang::t('redeem_rejected') : 'Anfrage abgelehnt';

        return new WP_REST_Response([
            'success' => true,
            'message' => $status === 'approved'
                ? '✅ ' . $msg_approved
                : '❌ ' . $msg_cancelled,
            'receipt_pdf_path' => $receipt_path,
            'receipt_url' => $receipt_url
        ], 200);
    }

    /** ============================================================
     *  📜 REST – Recent Logs
     * ============================================================ */
    public static function rest_recent_logs($request) {
        global $wpdb;

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 🏪 FILIALE SUPPORT: ALWAYS use session-aware store ID, ignore request parameter
        $store_id = self::get_store_id();

        error_log("📝 [PPV_REWARDS] rest_recent_logs for store_id: {$store_id}");

        $table = $wpdb->prefix . 'ppv_rewards_redeemed';
        
        $sql = $store_id > 0 
            ? $wpdb->prepare("
                SELECT 
                    r.id,
                    u.email AS user_email,
                    r.points_spent,
                    r.status,
                    r.redeemed_at,
                    r.receipt_pdf_path
                FROM $table r
                LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
                WHERE r.store_id = %d
                AND r.status IN ('approved', 'cancelled')
                ORDER BY r.redeemed_at DESC
                LIMIT 10
            ", $store_id)
            : "
                SELECT 
                    r.id,
                    u.email AS user_email,
                    r.points_spent,
                    r.status,
                    r.redeemed_at,
                    r.receipt_pdf_path
                FROM $table r
                LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
                WHERE r.status IN ('approved', 'cancelled')
                ORDER BY r.redeemed_at DESC
                LIMIT 10
            ";

        $rows = $wpdb->get_results($sql);

        error_log("✅ [PPV_REWARDS] Found " . count($rows) . " log items");

        return new WP_REST_Response([
            'success' => true,
            'items' => $rows ?: []
        ], 200);
    }

    /** ============================================================
     *  📊 REST – Havi Bizonylat Generálás
     * ============================================================ */
    public static function rest_generate_monthly_receipt($request) {
        global $wpdb;

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $data = $request->get_json_params();

        // 🏪 FILIALE SUPPORT: ALWAYS use session-aware store ID, ignore request parameter
        $store_id = self::get_store_id();

        $year = intval($data['year'] ?? date('Y'));
        $month = intval($data['month'] ?? date('m'));

        if (!$store_id || $month < 1 || $month > 12) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('error_invalid_params') : 'Ungültige Parameter';
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ ' . $msg
            ], 400);
        }

        error_log("📊 [PPV_REWARDS] Havi bizonylat: store_id={$store_id}, {$year}-{$month}");

        $expense_receipt_file = PPV_PLUGIN_DIR . 'includes/class-ppv-expense-receipt.php';

        if (!file_exists($expense_receipt_file)) {
            error_log("❌ [PPV_REWARDS] Expense receipt class nem található");
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('error_system') : 'Systemfehler';
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ ' . $msg
            ], 500);
        }

        require_once $expense_receipt_file;

        if (!class_exists('PPV_Expense_Receipt')) {
            error_log("❌ [PPV_REWARDS] PPV_Expense_Receipt class nem létezik");
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('error_system') : 'Systemfehler';
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ ' . $msg
            ], 500);
        }

        $receipt_path = PPV_Expense_Receipt::generate_monthly_receipt($store_id, $year, $month);

        if ($receipt_path) {
            // ✅ JSON VÁLASZ + OPEN URL
            $open_url = rest_url('ppv/v1/receipts/monthly-open?path=' . urlencode($receipt_path));
            $receipt_url = PPV_Expense_Receipt::get_receipt_url($receipt_path);
            
            error_log("✅ [PPV_REWARDS] Havi bizonylat generálva: {$receipt_path}");

            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('redeem_monthly_receipt_created') : 'Monatliche Dispoziție erstellt';
            
            return new WP_REST_Response([
                'success' => true,
                'message' => '✅ ' . $msg,
                'receipt_path' => $receipt_path,
                'receipt_url' => $receipt_url,
                'open_url' => $open_url
            ], 200);
        }

        $msg = class_exists('PPV_Lang') ? PPV_Lang::t('redeem_no_redeems_period') : 'Keine Einlösungen für diesen Zeitraum gefunden';
        return new WP_REST_Response([
            'success' => false,
            'message' => '❌ ' . $msg
        ], 400);
    }

    /** ============================================================
     *  💰 PRIVATE – Deduct Points
     * ============================================================ */
    private static function deduct_points_for_redeem($redeem_id, $store_id) {
        global $wpdb;

        $redeem = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_rewards_redeemed WHERE id = %d",
            $redeem_id
        ), ARRAY_A);

        if (!$redeem) {
            error_log("❌ [PPV_REWARDS] Redeem not found: {$redeem_id}");
            return false;
        }

        $user_id = intval($redeem['user_id']);
        $points  = intval($redeem['points_spent']);

        // Ellenőrizzük, van-e már pontlevonás
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
            WHERE reference = %s
        ", 'REDEEM-' . $redeem_id));

        if ($existing > 0) {
            error_log("⚠️ [PPV_REWARDS] Points already deducted for redeem: {$redeem_id}");
            return false;
        }

        // Pontlevonás
        $wpdb->insert(
            $wpdb->prefix . 'ppv_points',
            [
                'user_id'   => $user_id,
                'store_id'  => $store_id,
                'points'    => -abs($points),
                'type'      => 'redeem',
                'reference' => 'REDEEM-' . $redeem_id,
                'created'   => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s']
        );

        if ($wpdb->last_error) {
            error_log('❌ [PPV_REWARDS] Punkteabzug Fehler: ' . $wpdb->last_error);
            return false;
        }

        error_log("✅ [PPV_REWARDS] Points deducted: -{$points} for user {$user_id}");
        return true;
    }
}

PPV_Rewards::hooks();