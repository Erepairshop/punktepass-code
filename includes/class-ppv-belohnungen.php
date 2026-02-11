<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – User Rewards Page v3.0 (Redesigned)
 *
 * NEW DESIGN:
 * - Rewards grouped by store
 * - Store-specific points shown
 * - NO remote "Einlösen" button (now QR-based at store)
 * - "BEREIT" indicator when reward available
 * - "How it works" guide section
 * - Completed redemptions history only
 *
 * Version: 3.0 NEW DESIGN
 */

class PPV_Belohnungen {

    private static function ensure_session() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    public static function hooks() {
        add_action('init', [__CLASS__, 'maybe_render_standalone'], 1);
        add_shortcode('ppv_rewards_page', [__CLASS__, 'render_rewards_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // AJAX handlers for store rating
        add_action('wp_ajax_ppv_rate_store', [__CLASS__, 'ajax_rate_store']);
        add_action('wp_ajax_nopriv_ppv_rate_store', [__CLASS__, 'ajax_rate_store']);
    }

    private static function get_lang() {
        self::ensure_session();
        $lang = sanitize_text_field($_GET['lang'] ?? '');
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? '');
        }
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = sanitize_text_field($_SESSION['ppv_lang'] ?? 'de');
        }
        return in_array($lang, ['de', 'hu', 'ro'], true) ? $lang : 'de';
    }

    private static function get_labels($lang = 'de') {
        $labels = [
            'de' => [
                'page_title' => 'Meine Belohnungen',
                'page_subtitle' => 'Deine Prämien bei deinen Lieblingsgeschäften',
                'total_points' => 'Gesamtpunkte',
                'how_it_works' => 'So funktioniert\'s',
                'how_step_1' => 'Sammle Punkte durch QR-Code Scans',
                'how_step_2' => 'Wenn du genug Punkte hast, siehst du "BEREIT"',
                'how_step_3' => 'Zeige deinen QR-Code im Geschäft',
                'how_step_4' => 'Der Händler bestätigt die Einlösung',
                'store_points' => 'Punkte',
                'reward_ready' => 'BEREIT',
                'reward_locked' => 'fehlen noch',
                'show_qr' => 'QR-Code zeigen',
                'no_stores' => 'Noch keine Punkte gesammelt',
                'no_stores_hint' => 'Scanne deinen ersten QR-Code bei einem Geschäft!',
                'history_title' => 'Eingelöste Prämien',
                'no_history' => 'Noch keine Prämien eingelöst',
                'points' => 'Punkte',
                'on_date' => 'am',
                // New labels
                'progress_title' => 'Dein Fortschritt',
                'rewards_ready' => 'Bereit',
                'rewards_almost' => 'Fast da',
                'rewards_progress' => 'In Arbeit',
                'filter_all' => 'Alle Geschäfte',
                'filter_label' => 'Geschäft wählen',
                'available_rewards' => 'Verfügbare Prämien',
                'collapse' => 'Einklappen',
                'expand' => 'Ausklappen',
                'no_rewards_yet' => 'Dieses Geschäft hat noch keine Prämien eingerichtet',
                'qr_loading' => 'QR-Code wird geladen...',
                'qr_error' => 'Fehler beim Laden des QR-Codes',
                'points_collected' => 'Du hast Punkte gesammelt! Zeige deinen QR-Code im Geschäft.',
                'points_per_scan' => 'Punkte pro Scan',
                'scans_needed' => 'Besuche fehlen',
                'scan_singular' => 'Besuch fehlt',
                'free_product' => 'Gratis',
                // Store rating labels
                'rate_store' => 'Geschäft bewerten',
                'rate_store_title' => 'Bewertung',
                'rate_store_question' => 'Wie zufrieden bist du mit diesem Geschäft?',
                'rate_store_submit' => 'Bewertung senden',
                'rate_store_thanks' => 'Danke für deine Bewertung!',
                'rate_store_already' => 'Du hast dieses Jahr bereits bewertet.',
                'rate_store_select' => 'Bitte wähle eine Bewertung.',
                'rate_store_error' => 'Fehler beim Speichern.',
            ],
            'hu' => [
                'page_title' => 'Jutalmak',
                'page_subtitle' => 'Prémiumaid a kedvenc üzleteidnél',
                'total_points' => 'Összes pont',
                'how_it_works' => 'Így működik',
                'how_step_1' => 'Gyűjts pontokat QR-kód beolvasással',
                'how_step_2' => 'Ha elég pontod van, látod a "KÉSZ" jelzést',
                'how_step_3' => 'Mutasd meg a QR-kódod az üzletben',
                'how_step_4' => 'A kereskedő megerősíti a beváltást',
                'store_points' => 'Pont',
                'reward_ready' => 'KÉSZ',
                'reward_locked' => 'hiányzik',
                'show_qr' => 'QR-kód mutatása',
                'no_stores' => 'Még nincs pontod',
                'no_stores_hint' => 'Olvasd be az első QR-kódod egy üzletben!',
                'history_title' => 'Beváltott jutalmak',
                'no_history' => 'Még nincs beváltott jutalom',
                'points' => 'pont',
                'on_date' => '',
                // New labels
                'progress_title' => 'Előrehaladásod',
                'rewards_ready' => 'Kész',
                'rewards_almost' => 'Majdnem',
                'rewards_progress' => 'Folyamatban',
                'filter_all' => 'Összes üzlet',
                'filter_label' => 'Üzlet választása',
                'available_rewards' => 'Elérhető jutalmak',
                'collapse' => 'Összecsuk',
                'expand' => 'Kinyit',
                'no_rewards_yet' => 'Ez az üzlet még nem állított be jutalmakat',
                'qr_loading' => 'QR-kód betöltése...',
                'qr_error' => 'Hiba a QR-kód betöltésekor',
                'points_collected' => 'Vannak pontjaid! Mutasd meg a QR-kódod az üzletben.',
                'points_per_scan' => 'pont/szkennelés',
                'scans_needed' => 'alkalom hiányzik',
                'scan_singular' => 'alkalom hiányzik',
                'free_product' => 'Ingyenes',
                // Store rating labels
                'rate_store' => 'Üzlet értékelése',
                'rate_store_title' => 'Értékelés',
                'rate_store_question' => 'Mennyire vagy elégedett ezzel az üzlettel?',
                'rate_store_submit' => 'Értékelés küldése',
                'rate_store_thanks' => 'Köszönjük az értékelést!',
                'rate_store_already' => 'Idén már értékeltél.',
                'rate_store_select' => 'Kérlek válassz értékelést.',
                'rate_store_error' => 'Hiba a mentés során.',
            ],
            'ro' => [
                'page_title' => 'Premiile Mele',
                'page_subtitle' => 'Premiile tale la magazinele preferate',
                'total_points' => 'Puncte totale',
                'how_it_works' => 'Cum funcționează',
                'how_step_1' => 'Colectează puncte prin scanarea codului QR',
                'how_step_2' => 'Când ai suficiente puncte, vezi "GATA"',
                'how_step_3' => 'Arată codul QR în magazin',
                'how_step_4' => 'Comerciantul confirmă răscumpărarea',
                'store_points' => 'Puncte',
                'reward_ready' => 'GATA',
                'reward_locked' => 'lipsesc',
                'show_qr' => 'Arată codul QR',
                'no_stores' => 'Încă nu ai puncte',
                'no_stores_hint' => 'Scanează primul tău cod QR într-un magazin!',
                'history_title' => 'Premii răscumpărate',
                'no_history' => 'Încă nu ai răscumpărat niciun premiu',
                'points' => 'puncte',
                'on_date' => 'pe',
                // New labels
                'progress_title' => 'Progresul tău',
                'rewards_ready' => 'Gata',
                'rewards_almost' => 'Aproape',
                'rewards_progress' => 'În progres',
                'filter_all' => 'Toate magazinele',
                'filter_label' => 'Alege magazinul',
                'available_rewards' => 'Premii disponibile',
                'collapse' => 'Restrânge',
                'expand' => 'Extinde',
                'no_rewards_yet' => 'Acest magazin nu a configurat încă premii',
                'qr_loading' => 'Se încarcă codul QR...',
                'qr_error' => 'Eroare la încărcarea codului QR',
                'points_collected' => 'Ai puncte colectate! Arată codul QR în magazin.',
                'points_per_scan' => 'puncte/scanare',
                'scans_needed' => 'vizite lipsesc',
                'scan_singular' => 'vizită lipsește',
                'free_product' => 'Gratuit',
                // Store rating labels
                'rate_store' => 'Evaluează magazinul',
                'rate_store_title' => 'Evaluare',
                'rate_store_question' => 'Cât de mulțumit ești de acest magazin?',
                'rate_store_submit' => 'Trimite evaluarea',
                'rate_store_thanks' => 'Mulțumim pentru evaluare!',
                'rate_store_already' => 'Ai evaluat deja anul acesta.',
                'rate_store_select' => 'Te rog selectează o evaluare.',
                'rate_store_error' => 'Eroare la salvare.',
            ],
        ];
        return $labels[$lang] ?? $labels['de'];
    }

    // ============================================================
    // 🚀 STANDALONE RENDERING (bypasses WordPress theme)
    // ============================================================

    public static function maybe_render_standalone() {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = rtrim($path, '/');
        if ($path !== '/belohnungen') return;

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $user_id = intval($_SESSION['ppv_user_id'] ?? 0);
        if ($user_id <= 0) {
            header('Location: /login');
            exit;
        }

        self::render_standalone_page();
        exit;
    }

    private static function render_standalone_page() {
        $plugin_url = PPV_PLUGIN_URL;
        $version    = PPV_VERSION;
        $site_url   = get_site_url();

        $lang = self::get_lang();
        $labels = self::get_labels($lang);

        // Theme
        $theme_cookie = $_COOKIE['ppv_theme'] ?? 'light';
        $is_dark = ($theme_cookie === 'dark');

        // Global lang strings
        $global_strings = [];
        if (class_exists('PPV_Lang')) {
            $global_strings = PPV_Lang::$strings ?: [];
        }

        // Config for ppv-belohnungen.js
        $rewards_config = [
            'lang'          => $lang,
            'labels'        => $labels,
            'dashboard_url' => home_url('/dashboard/'),
        ];

        // Page content (render_rewards_page returns HTML)
        $page_html = self::render_rewards_page();

        // Global header
        $global_header = '';
        if (class_exists('PPV_User_Dashboard')) {
            ob_start();
            PPV_User_Dashboard::render_global_header();
            $global_header = ob_get_clean();
        }

        // Bottom nav context
        $bottom_nav_context = '';
        if (class_exists('PPV_Bottom_Nav')) {
            ob_start();
            PPV_Bottom_Nav::inject_context();
            $bottom_nav_context = ob_get_clean();
        }

        // Body classes
        $body_classes = ['ppv-standalone', 'ppv-app-mode'];
        $body_classes[] = $is_dark ? 'ppv-dark' : 'ppv-light';
        $body_class = implode(' ', $body_classes);

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>" data-theme="<?php echo $is_dark ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="turbo-cache-control" content="no-cache">
    <title>Belohnungen - PunktePass</title>
    <link rel="manifest" href="<?php echo esc_url($site_url); ?>/manifest.json">
    <link rel="icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-theme-light.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/handler-light.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-bottom-nav.css?v=<?php echo esc_attr($version); ?>">
<?php if ($is_dark): ?>
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-theme-dark-colors.css?v=<?php echo esc_attr($version); ?>">
<?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script>
    var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    window.ppv_rewards_config = <?php echo wp_json_encode($rewards_config); ?>;
    window.ppv_lang = <?php echo wp_json_encode($global_strings); ?>;
    </script>
    <style>
    html,body{margin:0;padding:0;min-height:100vh;background:var(--pp-bg,#f5f5f7);overflow-y:auto!important;overflow-x:hidden!important;height:auto!important}
    .ppv-standalone-wrap{max-width:768px;margin:0 auto;padding:0 0 90px 0;min-height:100vh}
    .ppv-standalone-wrap{padding-top:env(safe-area-inset-top,0)}
    </style>
</head>
<body class="<?php echo esc_attr($body_class); ?>">
<?php echo $global_header; ?>
<div class="ppv-standalone-wrap">
<?php echo $page_html; ?>
</div>
<?php echo $bottom_nav_context; ?>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-debug.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-global.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-belohnungen.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-theme-loader.js?v=<?php echo esc_attr($version); ?>"></script>
<?php if (class_exists('PPV_Bottom_Nav')): ?>
<script><?php echo PPV_Bottom_Nav::inline_js(); ?></script>
<?php endif; ?>
</body>
</html>
<?php
    }

    public static function enqueue_assets() {
        // Only load on pages with ppv_rewards_page shortcode
        global $post;
        if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ppv_rewards_page')) {
            return;
        }

        $plugin_url = defined('PPV_PLUGIN_URL') ? PPV_PLUGIN_URL : plugin_dir_url(dirname(__FILE__));
        $lang = self::get_lang();

        // Theme loader
        wp_enqueue_script('ppv-theme-loader', $plugin_url . 'assets/js/ppv-theme-loader.js', [], time(), false);

        // Fonts - Google Fonts removed for performance (using system fonts)
        // wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', [], null);

        // JS
        wp_enqueue_script('ppv-belohnungen', $plugin_url . 'assets/js/ppv-belohnungen.js', [], time(), true);

        // Config
        wp_add_inline_script('ppv-belohnungen', 'window.ppv_rewards_config = ' . wp_json_encode([
            'lang' => $lang,
            'labels' => self::get_labels($lang),
            'dashboard_url' => home_url('/dashboard/'),
        ]) . ';', 'before');
    }

    public static function render_rewards_page() {
        self::ensure_session();
        global $wpdb;

        $lang = self::get_lang();
        $L = self::get_labels($lang);

        // Get user
        $user_id = 0;
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
        } elseif (!empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
        }

        if (!$user_id) {
            return '<script>window.location.href = "' . esc_js(home_url('/login/')) . '";</script>';
        }

        // Get total points
        $total_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}ppv_points WHERE user_id = %d",
            $user_id
        ));

        // Get points per store (stores where user has collected points OR redeemed rewards)
        // FIX: Removed s.active = 1 filter - show stores where user has points even if inactive
        // Users should see all their points, not just from active stores
        // Check if logo_url column exists (cached via option to avoid SHOW COLUMNS per page load)
        $has_logo = get_option('ppv_stores_has_logo_url');
        if ($has_logo === false) {
            $col = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}ppv_stores LIKE 'logo_url'");
            $has_logo = !empty($col) ? '1' : '0';
            update_option('ppv_stores_has_logo_url', $has_logo, true);
        }
        $logo_select = $has_logo === '1' ? "s.logo_url," : "NULL AS logo_url,";
        $logo_group = $has_logo === '1' ? ", s.logo_url" : "";

        $store_points = $wpdb->get_results($wpdb->prepare("
            SELECT
                s.id AS store_id,
                s.name AS store_name_short,
                s.company_name AS store_name,
                {$logo_select}
                s.active AS is_active,
                COALESCE(SUM(p.points), 0) AS points
            FROM {$wpdb->prefix}ppv_stores s
            INNER JOIN {$wpdb->prefix}ppv_points p ON s.id = p.store_id
            WHERE p.user_id = %d
            GROUP BY s.id, s.name, s.company_name, s.active{$logo_group}
            ORDER BY points DESC
        ", $user_id));

        // Also get stores where user has redeemed rewards (in case they're not in points table)
        // FIX: Use r.store_id directly from ppv_rewards_redeemed instead of joining ppv_rewards
        // This ensures stores show up even if the reward was deleted
        $redeemed_store_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT r.store_id
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            WHERE r.user_id = %d AND r.store_id IS NOT NULL
        ", $user_id));

        // Merge redeemed stores that aren't in store_points
        // FIX N+1: Batch query all missing stores at once instead of per-store queries
        $existing_store_ids = array_column($store_points, 'store_id');
        $missing_store_ids = array_diff($redeemed_store_ids, $existing_store_ids);

        if (!empty($missing_store_ids)) {
            // Batch query: get all missing stores with their points in ONE query
            $placeholders = implode(',', array_fill(0, count($missing_store_ids), '%d'));
            $query_args = array_merge($missing_store_ids, [$user_id], $missing_store_ids);

            $missing_stores = $wpdb->get_results($wpdb->prepare("
                SELECT
                    s.id AS store_id,
                    s.name AS store_name_short,
                    s.company_name AS store_name,
                    s.logo_url,
                    s.active AS is_active,
                    COALESCE(p.points_sum, 0) AS points
                FROM {$wpdb->prefix}ppv_stores s
                LEFT JOIN (
                    SELECT store_id, SUM(points) AS points_sum
                    FROM {$wpdb->prefix}ppv_points
                    WHERE user_id = %d AND store_id IN ($placeholders)
                    GROUP BY store_id
                ) p ON s.id = p.store_id
                WHERE s.id IN ($placeholders)
            ", ...$query_args));

            foreach ($missing_stores as $store_data) {
                $store_points[] = $store_data;
            }
        }

        // Check if user has any store connections (for empty state logic)
        $has_store_points = !empty($store_points);

        // Get rewards for each store + calculate progress stats
        $rewards_by_store = [];
        $progress_stats = [
            'ready' => 0,      // 100%+
            'almost' => 0,     // 70-99%
            'in_progress' => 0 // <70%
        ];

        // FIX N+1: Batch query all rewards for all active stores at once
        $active_store_ids = [];
        foreach ($store_points as $store) {
            $is_active = isset($store->is_active) ? (bool)$store->is_active : true;
            if ($is_active) {
                $active_store_ids[] = (int)$store->store_id;
            }
        }

        // Pre-fetch all rewards for active stores in ONE query
        $all_rewards_by_store = [];
        if (!empty($active_store_ids)) {
            $today = date('Y-m-d');
            $placeholders = implode(',', array_fill(0, count($active_store_ids), '%d'));
            $query_args = array_merge($active_store_ids, [$today, $today]);

            $all_rewards = $wpdb->get_results($wpdb->prepare("
                SELECT id, store_id, title, description, required_points, points_given, is_campaign, start_date, end_date, action_type, action_value
                FROM {$wpdb->prefix}ppv_rewards
                WHERE store_id IN ($placeholders) AND (active = 1 OR active IS NULL)
                AND (
                    is_campaign = 0 OR is_campaign IS NULL
                    OR (
                        is_campaign = 1
                        AND (start_date IS NULL OR start_date <= %s)
                        AND (end_date IS NULL OR end_date >= %s)
                    )
                )
                ORDER BY store_id, is_campaign DESC, required_points ASC
            ", ...$query_args));

            // Group rewards by store_id
            foreach ($all_rewards as $reward) {
                $sid = (int)$reward->store_id;
                if (!isset($all_rewards_by_store[$sid])) {
                    $all_rewards_by_store[$sid] = [];
                }
                $all_rewards_by_store[$sid][] = $reward;
            }
        }

        // Build rewards_by_store using pre-fetched data
        foreach ($store_points as $store) {
            $is_active = isset($store->is_active) ? (bool)$store->is_active : true;
            $rewards = $is_active ? ($all_rewards_by_store[(int)$store->store_id] ?? []) : [];

            // Include store even if it has no rewards - show store with points
            $store_data = [
                'store' => $store,
                'rewards' => $rewards,
                'ready_count' => 0,
                'is_active' => $is_active,
            ];

            // Calculate progress for each reward (if any)
            if (!empty($rewards)) {
                foreach ($rewards as $reward) {
                    $progress = min(100, ((int)$store->points / max(1, (int)$reward->required_points)) * 100);
                    if ($progress >= 100) {
                        $progress_stats['ready']++;
                        $store_data['ready_count']++;
                    } elseif ($progress >= 70) {
                        $progress_stats['almost']++;
                    } else {
                        $progress_stats['in_progress']++;
                    }
                }
            }

            $rewards_by_store[] = $store_data;
        }

        // Get completed redemptions (approved only)
        $history = $wpdb->get_results($wpdb->prepare("
            SELECT
                rw.title AS reward_title,
                s.company_name AS store_name,
                r.points_spent,
                r.redeemed_at
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            INNER JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
            WHERE r.user_id = %d AND r.status = 'approved'
            ORDER BY r.redeemed_at DESC
            LIMIT 10
        ", $user_id));

        // Calculate total rewards count
        $total_rewards = $progress_stats['ready'] + $progress_stats['almost'] + $progress_stats['in_progress'];

        ob_start();
        ?>
        <div class="ppv-rewards-v3" data-lang="<?php echo esc_attr($lang); ?>">

            <!-- COMPACT HEADER + POINTS + QR BUTTON -->
            <div class="ppv-rw-hero">
                <div class="ppv-rw-hero-left">
                    <h1><?php echo esc_html($L['page_title']); ?></h1>
                    <p><?php echo esc_html($L['page_subtitle']); ?></p>
                </div>
                <div class="ppv-rw-hero-right">
                    <div class="ppv-rw-hero-points">
                        <span class="ppv-rw-pts-val"><?php echo number_format($total_points); ?></span>
                        <span class="ppv-rw-pts-lbl"><?php echo esc_html($L['total_points']); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($total_points > 0 || $has_store_points): ?>
                <?php if ($total_rewards > 0): ?>
                <!-- PROGRESS SUMMARY - 3 mini cards (only show if there are rewards) -->
                <div class="ppv-rw-progress-summary">
                    <div class="ppv-rw-prog-card ppv-rw-prog-ready">
                        <span class="ppv-rw-prog-num"><?php echo $progress_stats['ready']; ?></span>
                        <span class="ppv-rw-prog-label"><?php echo esc_html($L['rewards_ready']); ?></span>
                    </div>
                    <div class="ppv-rw-prog-card ppv-rw-prog-almost">
                        <span class="ppv-rw-prog-num"><?php echo $progress_stats['almost']; ?></span>
                        <span class="ppv-rw-prog-label"><?php echo esc_html($L['rewards_almost']); ?></span>
                    </div>
                    <div class="ppv-rw-prog-card ppv-rw-prog-wip">
                        <span class="ppv-rw-prog-num"><?php echo $progress_stats['in_progress']; ?></span>
                        <span class="ppv-rw-prog-label"><?php echo esc_html($L['rewards_progress']); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- STORE FILTER -->
                <?php if (count($rewards_by_store) > 1): ?>
                    <div class="ppv-rw-filter">
                        <select id="ppv-store-filter" class="ppv-rw-filter-select">
                            <option value="all"><?php echo esc_html($L['filter_all']); ?> (<?php echo count($rewards_by_store); ?>)</option>
                            <?php foreach ($rewards_by_store as $idx => $item): ?>
                                <option value="store-<?php echo esc_attr($item['store']->store_id); ?>">
                                    <?php echo esc_html($item['store']->store_name); ?>
                                    <?php if ($item['ready_count'] > 0): ?>
                                        (<?php echo $item['ready_count']; ?> <?php echo esc_html($L['rewards_ready']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- STORES ACCORDION -->
                <div class="ppv-rw-stores" id="ppv-stores-list">
                    <?php foreach ($rewards_by_store as $idx => $item):
                        $store = $item['store'];
                        $rewards = $item['rewards'];
                        $ready_count = $item['ready_count'];
                        $is_first = ($idx === 0);
                    ?>
                        <div class="ppv-rw-store-card <?php echo $ready_count > 0 ? 'has-ready' : ''; ?>"
                             data-store-id="store-<?php echo esc_attr($store->store_id); ?>">

                            <!-- Store Header (Clickable) -->
                            <div class="ppv-rw-store-header" onclick="ppvToggleStore(this)">
                                <div class="ppv-rw-store-left">
                                    <?php if (!empty($store->logo_url)): ?>
                                        <img src="<?php echo esc_url($store->logo_url); ?>" alt="" class="ppv-rw-store-logo" width="48" height="48" loading="lazy">
                                    <?php else: ?>
                                        <div class="ppv-rw-store-logo-placeholder">
                                            <i class="ri-store-2-fill"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ppv-rw-store-info">
                                        <h3><?php echo esc_html($store->store_name ?: $store->store_name_short); ?></h3>
                                        <?php if (!empty($store->store_name_short) && !empty($store->store_name)): ?>
                                            <p style="font-size: 0.85em; color: #666; margin: 2px 0 0 0;"><?php echo esc_html($store->store_name_short); ?></p>
                                        <?php endif; ?>
                                        <span class="ppv-rw-store-points">
                                            <i class="ri-star-fill"></i> <?php echo number_format($store->points); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ppv-rw-store-right">
                                    <?php if ($ready_count > 0): ?>
                                        <span class="ppv-rw-ready-badge"><?php echo $ready_count; ?></span>
                                    <?php endif; ?>
                                    <i class="ri-arrow-down-s-line ppv-rw-chevron"></i>
                                </div>
                            </div>

                            <!-- Rewards List (Collapsible) -->
                            <div class="ppv-rw-rewards-list <?php echo $is_first ? 'is-open' : ''; ?>">
                                <?php if (!empty($rewards)): ?>
                                    <?php foreach ($rewards as $reward):
                                        $user_store_points = (int)$store->points;
                                        $required = (int)$reward->required_points;
                                        $points_per_scan = (int)($reward->points_given ?? 1);
                                        if ($points_per_scan < 1) $points_per_scan = 1;
                                        $progress = min(100, ($user_store_points / max(1, $required)) * 100);
                                        $is_ready = $user_store_points >= $required;
                                        $missing = max(0, $required - $user_store_points);
                                        $scans_needed = $is_ready ? 0 : (int)ceil($missing / $points_per_scan);
                                        $is_campaign = !empty($reward->is_campaign);
                                        $is_free_product = ($reward->action_type ?? '') === 'free_product';
                                        $end_date_str = $reward->end_date ? date('d.m', strtotime($reward->end_date)) : null;
                                    ?>
                                        <div class="ppv-rw-reward-item <?php echo $is_ready ? 'is-ready' : 'is-locked'; ?> <?php echo $is_campaign ? 'is-campaign' : ''; ?> <?php echo $is_free_product ? 'is-free-product' : ''; ?>">
                                            <?php if ($is_free_product): ?>
                                                <div class="ppv-rw-free-badge" style="position: absolute; top: 8px; right: 8px; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; text-transform: uppercase;">
                                                    <i class="ri-gift-fill"></i> <?php echo esc_html($L['free_product']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($is_campaign): ?>
                                                <div class="ppv-rw-campaign-badge">
                                                    <i class="ri-calendar-event-line"></i>
                                                    <?php if ($end_date_str): ?>
                                                        <span><?php echo esc_html($end_date_str); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="ppv-rw-reward-row">
                                                <div class="ppv-rw-reward-main">
                                                    <span class="ppv-rw-reward-title"><?php echo esc_html($reward->title); ?></span>
                                                    <span class="ppv-rw-reward-pts"><?php echo number_format($required); ?> P</span>
                                                </div>
                                                <div class="ppv-rw-reward-badge">
                                                    <?php if ($is_ready): ?>
                                                        <span class="ppv-rw-badge-ready"><i class="ri-check-line"></i></span>
                                                    <?php else: ?>
                                                        <span class="ppv-rw-badge-need">-<?php echo number_format($missing); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="ppv-rw-progress">
                                                <div class="ppv-rw-progress-bar" style="width: <?php echo intval($progress); ?>%"></div>
                                            </div>
                                            <?php if (!$is_ready): ?>
                                            <div class="ppv-rw-scan-info" style="display: flex; justify-content: space-between; font-size: 12px; color: #64748b; margin-top: 6px; padding: 0 2px;">
                                                <span><i class="ri-qr-scan-line"></i> <?php echo $points_per_scan; ?> <?php echo esc_html($L['points_per_scan']); ?></span>
                                                <span><i class="ri-walk-line"></i> <?php echo $scans_needed; ?> <?php echo esc_html($scans_needed === 1 ? $L['scan_singular'] : $L['scans_needed']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- No rewards for this store -->
                                    <div class="ppv-rw-no-rewards">
                                        <i class="ri-time-line"></i>
                                        <p><?php echo esc_html($L['no_rewards_yet']); ?></p>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($rewards_by_store)): ?>
                    <!-- User has points but no store data (fallback) -->
                    <div class="ppv-rw-info-box">
                        <i class="ri-information-line"></i>
                        <p><?php echo esc_html($L['points_collected']); ?></p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- EMPTY STATE - Only show when user has NO points at all -->
                <div class="ppv-rw-empty">
                    <i class="ri-gift-line"></i>
                    <h3><?php echo esc_html($L['no_stores']); ?></h3>
                    <p><?php echo esc_html($L['no_stores_hint']); ?></p>
                </div>
            <?php endif; ?>

            <!-- REDEMPTION HISTORY -->
            <?php if (!empty($history)): ?>
                <div class="ppv-rw-history">
                    <h3><i class="ri-history-line"></i> <?php echo esc_html($L['history_title']); ?></h3>
                    <div class="ppv-rw-history-list">
                        <?php foreach ($history as $h): ?>
                            <div class="ppv-rw-history-item">
                                <div class="ppv-rw-history-left">
                                    <span class="ppv-rw-history-title"><?php echo esc_html($h->reward_title); ?></span>
                                    <span class="ppv-rw-history-store"><?php echo esc_html($h->store_name); ?></span>
                                </div>
                                <div class="ppv-rw-history-right">
                                    <span class="ppv-rw-history-pts">-<?php echo number_format($h->points_spent); ?></span>
                                    <span class="ppv-rw-history-date"><?php echo esc_html(date('d.m', strtotime($h->redeemed_at))); ?></span>
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

    /**
     * AJAX handler for store rating
     * Users can rate once per 12 months per store
     */
    public static function ajax_rate_store() {
        global $wpdb;

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_store_rating_nonce')) {
            wp_send_json_error(['message' => 'Ungültiger Sicherheitstoken']);
            return;
        }

        // Get PPV user ID from session
        self::ensure_session();
        $user_id = intval($_SESSION['ppv_user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(['message' => 'Bitte einloggen']);
            return;
        }

        $store_id = intval($_POST['store_id'] ?? 0);
        $rating = intval($_POST['rating'] ?? 0);

        if (!$store_id || $rating < 1 || $rating > 5) {
            wp_send_json_error(['message' => 'Ungültige Bewertung']);
            return;
        }

        // Check if user already rated this store in the last 12 months
        $twelve_months_ago = date('Y-m-d H:i:s', strtotime('-12 months'));

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}pp_reviews
             WHERE store_id = %d AND user_id = %d AND created_at > %s
             LIMIT 1",
            $store_id, $user_id, $twelve_months_ago
        ));

        if ($existing) {
            $lang = self::get_lang();
            $L = self::get_labels($lang);
            wp_send_json_error(['message' => $L['rate_store_already']]);
            return;
        }

        // Delete old rating if exists (allows re-rating after 12 months)
        $wpdb->delete(
            "{$wpdb->prefix}pp_reviews",
            ['store_id' => $store_id, 'user_id' => $user_id],
            ['%d', '%d']
        );

        // Insert new rating
        $result = $wpdb->insert(
            "{$wpdb->prefix}pp_reviews",
            [
                'store_id' => $store_id,
                'user_id' => $user_id,
                'rating' => $rating,
                'comment' => '',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%s', '%s']
        );

        if ($result) {
            wp_send_json_success(['message' => 'Bewertung gespeichert']);
        } else {
            wp_send_json_error(['message' => 'Fehler beim Speichern']);
        }
    }
}

// Initialize
PPV_Belohnungen::hooks();
