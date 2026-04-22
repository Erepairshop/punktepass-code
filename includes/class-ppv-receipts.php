<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Bizonylatok Verwaltung (Receipt Management)
 * Version: 2.1 FIXED - REST endpoint /redeem/monthly-receipt hozzáadva
 * ✅ Kiadási bizonylatok listázása (egyszeri + havi)
 * ✅ Szűrés dátum + felhasználó szerint
 * ✅ HTML megjelenítés böngészőben
 * ✅ /redeem/monthly-receipt POST endpoint - MŰKÖDIK!
 * ✅ DE + RO támogatás
 */

class PPV_Receipts {

    public static function hooks() {
        add_shortcode('ppv_receipts', [__CLASS__, 'render_receipts_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    /** ============================================================
     *  🔡 REST ENDPOINTS
     * ============================================================ */
    public static function register_rest_routes() {
        // Bizonylatok listázása
        register_rest_route('ppv/v1', '/receipts/list', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_list_receipts'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Szűrés
        register_rest_route('ppv/v1', '/receipts/filter', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_filter_receipts'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Download receipt
        register_rest_route('ppv/v1', '/receipts/download', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_download_receipt'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // ✅ HAVI BIZONYLAT GENERÁLÁS (POST) - A JavaScript ezt hívja! - 🔒 CSRF protected
        register_rest_route('ppv/v1', '/redeem/monthly-receipt', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_monthly_receipt'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);

        // ✅ HAVI BIZONYLAT LETÖLTÉS (PDF)
register_rest_route('ppv/v1', '/redeem/monthly-receipt-download', [
    'methods'  => 'GET',
    'callback' => [__CLASS__, 'rest_download_monthly_receipt'],
    'permission_callback' => ['PPV_Permissions', 'check_handler']
]);
    }

    /** ============================================================
     *  🎨 ASSETS
     * ============================================================ */
    public static function enqueue_assets() {
        global $post;
        if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ppv_receipts')) {
            return;
        }

        wp_enqueue_script(
            'ppv-receipts',
            PPV_PLUGIN_URL . 'assets/js/ppv-receipts.js',
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
            'ppv-receipts',
            "window.ppv_receipts_rest = " . wp_json_encode($payload) . ";
window.ppv_plugin_url = '" . esc_url(PPV_PLUGIN_URL) . "';",
            'before'
        );
    }

    /** ============================================================
     *  🔐 GET STORE ID
     * ============================================================ */
    private static function get_store_id() {
        global $wpdb;

        if (session_status() === PHP_SESSION_NONE) {
            ppv_maybe_start_session();
        }

        // 🔄 Token restoration for trial users
        if (empty($_SESSION['ppv_user_id']) && !empty($_COOKIE['ppv_user_token'])) {
            if (class_exists('PPV_SessionBridge')) {
                PPV_SessionBridge::restore_from_token();
            }
        }

        // 1️⃣ Session - ppv_store_id
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        // 2️⃣ Session - ppv_user_id (trial vendors)
        if (!empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d AND active=1 LIMIT 1",
                $user_id
            ));
            if ($store_id) {
                $_SESSION['ppv_store_id'] = $store_id;
                return intval($store_id);
            }
        }

        // 3️⃣ WordPress logged in user
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d AND active=1 LIMIT 1",
                $uid
            ));
            if ($store_id) {
                $_SESSION['ppv_store_id'] = $store_id;
                return intval($store_id);
            }
        }

        // 4️⃣ Global fallback
        if (!empty($GLOBALS['ppv_active_store'])) {
            $active = $GLOBALS['ppv_active_store'];
            return is_object($active) ? intval($active->id) : intval($active);
        }

        return 0;
    }

    /** ============================================================
     *  🎨 FRONTEND RENDER
     * ============================================================ */
    public static function render_receipts_page() {
        if (session_status() === PHP_SESSION_NONE) {
            ppv_maybe_start_session();
        }

        global $wpdb;
        $store_id = self::get_store_id();

        if (!$store_id) {
            return '<div class="ppv-warning">⚠️ Bitte anmelden oder POS aktivieren.</div>';
        }

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, company_name, country FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));

        if (!$store) {
            return '<div class="ppv-warning">⚠️ Store nicht gefunden.</div>';
        }

        $is_ro = strtoupper($store->country) === 'RO';

        ob_start();
        ?>
        <script>window.PPV_STORE_ID = <?php echo intval($store->id); ?>;</script>

        <div class="ppv-receipts-wrapper glass-section">
            <h2>📄 <?php echo $is_ro ? 'Dispoziții Cheltuieli' : 'Kiadási Bizonylatok'; ?> – <?php echo esc_html($store->company_name); ?></h2>

            <!-- SZŰRÉS -->
            <div class="ppv-receipts-filter" style="margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap;">
                <input type="text" id="ppv-receipt-search" placeholder="🔍 E-Mail keresés..." style="padding: 10px; border: 1px solid #ddd; border-radius: 6px; flex: 1; min-width: 200px;">
                
                <input type="date" id="ppv-receipt-date-from" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                <input type="date" id="ppv-receipt-date-to" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                
                <button id="ppv-receipt-filter-btn" class="ppv-btn ppv-btn-secondary" style="padding: 10px 20px;">
                    🔍 Szűrés
                </button>
            </div>

            <!-- BIZONYLATOK LISTA -->
            <div id="ppv-receipts-list" class="ppv-receipts-grid">
                <p class="ppv-loading">Bizonylatok betöltése...</p>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    /** ============================================================
     *  📋 REST – List All Receipts
     * ============================================================ */
    public static function rest_list_receipts($request) {
        global $wpdb;
        
        $store_id = intval($request->get_param('store_id') ?: 0);
        
        if (!$store_id) {
            return new WP_REST_Response([
                'success' => false,
                'items' => []
            ], 400);
        }

        $items = $wpdb->get_results($wpdb->prepare("
            SELECT 
                r.id,
                r.user_id,
                r.store_id,
                r.reward_id,
                r.points_spent,
                r.actual_amount,
                r.redeemed_at,
                r.receipt_pdf_path,
                rw.title as reward_title,
                u.email as user_email,
                u.first_name,
                u.last_name,
                s.country
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
            WHERE r.store_id = %d
            AND r.status = 'approved'
            ORDER BY r.redeemed_at DESC
            LIMIT 100
        ", $store_id));

        return new WP_REST_Response([
            'success' => true,
            'items' => $items ?: [],
            'count' => count($items)
        ], 200);
    }

    /** ============================================================
     *  🔍 REST – Filter Receipts
     * ============================================================ */
    public static function rest_filter_receipts($request) {
        global $wpdb;

        $store_id = intval($request->get_param('store_id') ?: 0);
        $search = sanitize_text_field($request->get_param('search') ?: '');
        $date_from = sanitize_text_field($request->get_param('date_from') ?: '');
        $date_to = sanitize_text_field($request->get_param('date_to') ?: '');

        if (!$store_id) {
            return new WP_REST_Response([
                'success' => false,
                'items' => []
            ], 400);
        }

        $query = "
            SELECT 
                r.id,
                r.user_id,
                r.store_id,
                r.reward_id,
                r.points_spent,
                r.actual_amount,
                r.redeemed_at,
                r.receipt_pdf_path,
                rw.title as reward_title,
                u.email as user_email,
                u.first_name,
                u.last_name,
                s.country
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
            WHERE r.store_id = %d
            AND r.status = 'approved'
            AND r.receipt_pdf_path IS NOT NULL
        ";

        $params = [$store_id];

        if ($search) {
            $query .= " AND u.email LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if ($date_from) {
            $query .= " AND DATE(r.redeemed_at) >= %s";
            $params[] = $date_from;
        }

        if ($date_to) {
            $query .= " AND DATE(r.redeemed_at) <= %s";
            $params[] = $date_to;
        }

        $query .= " ORDER BY r.redeemed_at DESC LIMIT 100";

        $items = $wpdb->get_results($wpdb->prepare($query, ...$params));

        return new WP_REST_Response([
            'success' => true,
            'items' => $items ?: [],
            'count' => count($items)
        ], 200);
    }

    /** ============================================================
     *  📥 REST – Download Receipt (PDF generálás DOMPDF-fel)
     * ============================================================ */
    public static function rest_download_receipt($request) {
        global $wpdb;

        $receipt_id = intval($request->get_param('id') ?: 0);
        $store_id   = intval($request->get_param('store_id') ?: 0);

        if (!$receipt_id || !$store_id) {
            wp_die('❌ Ungültige Parameter', 400);
        }

        $pdf_dir = '/home/u660905446/domains/punktepass.de/public_html/pdf/';
        $pdf_url = 'https://pdf.punktepass.de/';

        $receipt = $wpdb->get_row($wpdb->prepare("
            SELECT receipt_pdf_path
            FROM {$wpdb->prefix}ppv_rewards_redeemed
            WHERE id = %d AND store_id = %d
        ", $receipt_id, $store_id), ARRAY_A);

        if (!$receipt || !$receipt['receipt_pdf_path']) {
            wp_die('❌ Bizonylat nem található', 404);
        }

        $upload = wp_upload_dir();
        $html_path = $upload['basedir'] . '/' . $receipt['receipt_pdf_path'];

        if (!file_exists($html_path)) {
            wp_die('❌ Fájl nem létezik', 404);
        }

        $filename = "receipt_{$receipt_id}.pdf";
        $fullpath = $pdf_dir . $filename;

        if (!file_exists($fullpath)) {
            $html = file_get_contents($html_path);

            require_once PPV_PLUGIN_DIR . 'libs/dompdf/autoload.inc.php';
            $options = new Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            file_put_contents($fullpath, $dompdf->output());
        }

        wp_redirect($pdf_url . $filename);
        exit;
    }
    
    /**
 * 📥 REST – Download Monthly Receipt as PDF (DOMPDF)
 */
public static function rest_download_monthly_receipt($request) {
    global $wpdb;

    $store_id = intval($request->get_param('store_id') ?: 0);
    $year     = intval($request->get_param('year') ?: 0);
    $month    = intval($request->get_param('month') ?: 0);

    if (!$store_id || !$year || !$month) {
        wp_die('❌ Ungültige Parameter', 400);
    }

    ppv_log("📥 [PPV_RECEIPTS] Download monthly receipt: store={$store_id}, year={$year}, month={$month}");

    $pdf_dir = '/home/u660905446/domains/punktepass.de/public_html/pdf/';
    $pdf_url = 'https://pdf.punktepass.de/';

    // HTML path
    $upload = wp_upload_dir();
    $html_filename = sprintf("monthly-receipt-%d-%04d%02d.html", $store_id, $year, $month);
    $html_path = $upload['basedir'] . '/ppv_receipts/' . $html_filename;

    if (!file_exists($html_path)) {
        ppv_log("❌ [PPV_RECEIPTS] HTML file not found: {$html_path}");
        wp_die('❌ HTML Datei nicht gefunden', 404);
    }

    // PDF fájlnév
    $pdf_filename = sprintf("monthly-receipt-%d-%04d%02d.pdf", $store_id, $year, $month);
    $fullpath = $pdf_dir . $pdf_filename;

    // Ha nincs PDF → DOMPDF-fel generáljuk (PONTOSAN UGYANAZ MINT EGYSZERI!)
    if (!file_exists($fullpath)) {
        ppv_log("📥 [PPV_RECEIPTS] Generating PDF from HTML: {$html_filename}");

        $html = file_get_contents($html_path);

        require_once PPV_PLUGIN_DIR . 'libs/dompdf/autoload.inc.php';
        $options = new Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdf_bytes = file_put_contents($fullpath, $dompdf->output());
        ppv_log("✅ [PPV_RECEIPTS] PDF saved: {$pdf_filename} ({$pdf_bytes} bytes)");
    }

    // 302 REDIRECT a valódi PDF-re
    ppv_log("✅ [PPV_RECEIPTS] Redirecting to: {$pdf_url}{$pdf_filename}");
    wp_redirect($pdf_url . $pdf_filename);
    exit;
}

    /** ============================================================
     *  📅 REST – Generate Monthly Receipt (POST)
     *  ✅ A JavaScript ezeket hívja meg!
     * ============================================================ */
    public static function rest_generate_monthly_receipt($request) {
        global $wpdb;

        ppv_log("📅 [PPV_RECEIPTS] rest_generate_monthly_receipt() called");

        $params = $request->get_json_params();
        
        $store_id = intval($params['store_id'] ?? 0);
        $year     = intval($params['year'] ?? 0);
        $month    = intval($params['month'] ?? 0);

        ppv_log("📅 [PPV_RECEIPTS] Params: store_id={$store_id}, year={$year}, month={$month}");

        if (!$store_id || !$year || !$month) {
            ppv_log("❌ [PPV_RECEIPTS] Invalid parameters");
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ungültige Parameter (store_id, year, month erforderlich)'
            ], 400);
        }

        try {
            ppv_log("📅 [PPV_RECEIPTS] Calling PPV_Expense_Receipt::generate_monthly_receipt()");

            // ✅ PDF generálás - meghívjuk az PPV_Expense_Receipt class-t
            $html_path = PPV_Expense_Receipt::generate_monthly_receipt($store_id, $year, $month);

            if (!$html_path) {
                ppv_log("❌ [PPV_RECEIPTS] PDF generation failed");
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Fehler bei der PDF-Generierung'
                ], 500);
            }

            ppv_log("✅ [PPV_RECEIPTS] PDF generated: {$html_path}");

            // ✅ URL összeállítása
            $upload = wp_upload_dir();
            $url = $upload['baseurl'] . '/' . $html_path;

            ppv_log("✅ [PPV_RECEIPTS] URL: {$url}");

            return new WP_REST_Response([
                'success' => true,
                'receipt_url' => $url,
                'open_url' => $url,
                'message' => 'Monatsbericht erfolgreich generiert'
            ], 200);

        } catch (Exception $e) {
            ppv_log("❌ [PPV_RECEIPTS] Exception: " . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Fehler: ' . $e->getMessage()
            ], 500);
        }
    }
}

PPV_Receipts::hooks();
