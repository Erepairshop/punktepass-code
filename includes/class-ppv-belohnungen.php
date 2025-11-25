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
        add_shortcode('ppv_rewards_page', [__CLASS__, 'render_rewards_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
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
            ],
        ];
        return $labels[$lang] ?? $labels['de'];
    }

    public static function enqueue_assets() {
        $plugin_url = defined('PPV_PLUGIN_URL') ? PPV_PLUGIN_URL : plugin_dir_url(dirname(__FILE__));
        $lang = self::get_lang();

        // Theme loader
        wp_enqueue_script('ppv-theme-loader', $plugin_url . 'assets/js/ppv-theme-loader.js', [], time(), false);

        // Fonts & Icons
        wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', [], null);
        wp_enqueue_style('remix-icon', 'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css', [], '3.5.0');

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
        // FIX: Check if logo_url column exists (some installations may not have it)
        $has_logo_col = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}ppv_stores LIKE 'logo_url'");
        $logo_select = !empty($has_logo_col) ? "s.logo_url," : "NULL AS logo_url,";
        $logo_group = !empty($has_logo_col) ? ", s.logo_url" : "";

        $store_points = $wpdb->get_results($wpdb->prepare("
            SELECT
                s.id AS store_id,
                s.company_name AS store_name,
                {$logo_select}
                s.active AS is_active,
                COALESCE(SUM(p.points), 0) AS points
            FROM {$wpdb->prefix}ppv_stores s
            INNER JOIN {$wpdb->prefix}ppv_points p ON s.id = p.store_id
            WHERE p.user_id = %d
            GROUP BY s.id, s.company_name, s.active{$logo_group}
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
        // FIX: Also get user's points for these stores from ppv_points table
        $existing_store_ids = array_column($store_points, 'store_id');
        foreach ($redeemed_store_ids as $store_id) {
            if (!in_array($store_id, $existing_store_ids)) {
                // Get store info (show even if inactive, user already has history there)
                $store_data = $wpdb->get_row($wpdb->prepare("
                    SELECT id AS store_id, company_name AS store_name, logo_url, active AS is_active
                    FROM {$wpdb->prefix}ppv_stores
                    WHERE id = %d
                ", $store_id));
                if ($store_data) {
                    // Get user's current points at this store
                    $store_data->points = (int)$wpdb->get_var($wpdb->prepare("
                        SELECT COALESCE(SUM(points), 0)
                        FROM {$wpdb->prefix}ppv_points
                        WHERE user_id = %d AND store_id = %d
                    ", $user_id, $store_id));
                    $store_points[] = $store_data;
                }
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

        foreach ($store_points as $store) {
            // Only get rewards for active stores - inactive stores can't have available rewards
            $is_active = isset($store->is_active) ? (bool)$store->is_active : true;
            $rewards = [];

            if ($is_active) {
                $rewards = $wpdb->get_results($wpdb->prepare("
                    SELECT id, title, description, required_points
                    FROM {$wpdb->prefix}ppv_rewards
                    WHERE store_id = %d AND (active = 1 OR active IS NULL)
                    ORDER BY required_points ASC
                ", $store->store_id));
            }

            // Include store even if it has no rewards - show store with points
            $store_data = [
                'store' => $store,
                'rewards' => $rewards ?: [],
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
                                        <img src="<?php echo esc_url($store->logo_url); ?>" alt="" class="ppv-rw-store-logo">
                                    <?php else: ?>
                                        <div class="ppv-rw-store-logo-placeholder">
                                            <i class="ri-store-2-fill"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ppv-rw-store-info">
                                        <h3><?php echo esc_html($store->store_name); ?></h3>
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
                                        $progress = min(100, ($user_store_points / max(1, $required)) * 100);
                                        $is_ready = $user_store_points >= $required;
                                        $missing = max(0, $required - $user_store_points);
                                    ?>
                                        <div class="ppv-rw-reward-item <?php echo $is_ready ? 'is-ready' : 'is-locked'; ?>">
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

            <!-- HOW IT WORKS (Collapsible) -->
            <div class="ppv-rw-howto">
                <div class="ppv-rw-howto-header" onclick="ppvToggleHowto(this)">
                    <h3><i class="ri-lightbulb-line"></i> <?php echo esc_html($L['how_it_works']); ?></h3>
                    <i class="ri-arrow-down-s-line ppv-rw-chevron"></i>
                </div>
                <div class="ppv-rw-howto-content">
                    <div class="ppv-rw-howto-steps">
                        <div class="ppv-rw-step"><span class="step-num">1</span><span><?php echo esc_html($L['how_step_1']); ?></span></div>
                        <div class="ppv-rw-step"><span class="step-num">2</span><span><?php echo esc_html($L['how_step_2']); ?></span></div>
                        <div class="ppv-rw-step"><span class="step-num">3</span><span><?php echo esc_html($L['how_step_3']); ?></span></div>
                        <div class="ppv-rw-step"><span class="step-num">4</span><span><?php echo esc_html($L['how_step_4']); ?></span></div>
                    </div>
                </div>
            </div>

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
}

// Initialize
PPV_Belohnungen::hooks();
