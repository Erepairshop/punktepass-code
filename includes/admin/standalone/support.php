<?php
/**
 * PunktePass Standalone Admin - Feedback & Support
 * Route: /admin/support
 * Multi-language support: DE, HU, EN
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Support {

    /**
     * Get translations for all supported languages
     */
    private static function get_translations() {
        return [
            'de' => [
                'page_title' => 'Feedback & Support - PunktePass Admin',
                'header_title' => 'Feedback & Support',
                'open_tickets' => 'offen',
                'back' => 'Zurück',
                'ticket_updated' => 'Ticket aktualisiert!',
                'status' => 'Status',
                'status_open' => 'Offen',
                'status_new' => 'Neu',
                'status_in_progress' => 'In Bearbeitung',
                'status_resolved' => 'Erledigt',
                'category' => 'Kategorie',
                'cat_all' => 'Alle',
                'cat_bug' => 'Bug',
                'cat_feature' => 'Idee',
                'cat_question' => 'Frage',
                'cat_rating' => 'Bewertung',
                'cat_support' => 'Support',
                'no_tickets' => 'Keine Tickets in dieser Kategorie!',
                'th_id' => 'ID',
                'th_category' => 'Kategorie',
                'th_type' => 'Typ',
                'th_sender' => 'Absender',
                'th_message' => 'Nachricht',
                'th_created' => 'Erstellt',
                'th_actions' => 'Aktionen',
                'type_handler' => 'Händler',
                'type_user' => 'Kunde',
                'btn_take' => 'Übernehmen',
                'btn_resolve' => 'Erledigen',
                'confirm_resolve' => 'Als erledigt markieren?',
            ],
            'hu' => [
                'page_title' => 'Feedback & Support - PunktePass Admin',
                'header_title' => 'Feedback & Support',
                'open_tickets' => 'nyitott',
                'back' => 'Vissza',
                'ticket_updated' => 'Jegy frissítve!',
                'status' => 'Státusz',
                'status_open' => 'Nyitott',
                'status_new' => 'Új',
                'status_in_progress' => 'Folyamatban',
                'status_resolved' => 'Megoldva',
                'category' => 'Kategória',
                'cat_all' => 'Mind',
                'cat_bug' => 'Bug',
                'cat_feature' => 'Ötlet',
                'cat_question' => 'Kérdés',
                'cat_rating' => 'Értékelés',
                'cat_support' => 'Support',
                'no_tickets' => 'Nincs jegy ebben a kategóriában!',
                'th_id' => 'ID',
                'th_category' => 'Kategória',
                'th_type' => 'Típus',
                'th_sender' => 'Küldő',
                'th_message' => 'Üzenet',
                'th_created' => 'Létrehozva',
                'th_actions' => 'Műveletek',
                'type_handler' => 'Kereskedő',
                'type_user' => 'Ügyfél',
                'btn_take' => 'Felvétel',
                'btn_resolve' => 'Megoldás',
                'confirm_resolve' => 'Megoldottként jelöli?',
            ],
            'en' => [
                'page_title' => 'Feedback & Support - PunktePass Admin',
                'header_title' => 'Feedback & Support',
                'open_tickets' => 'open',
                'back' => 'Back',
                'ticket_updated' => 'Ticket updated!',
                'status' => 'Status',
                'status_open' => 'Open',
                'status_new' => 'New',
                'status_in_progress' => 'In Progress',
                'status_resolved' => 'Resolved',
                'category' => 'Category',
                'cat_all' => 'All',
                'cat_bug' => 'Bug',
                'cat_feature' => 'Idea',
                'cat_question' => 'Question',
                'cat_rating' => 'Rating',
                'cat_support' => 'Support',
                'no_tickets' => 'No tickets in this category!',
                'th_id' => 'ID',
                'th_category' => 'Category',
                'th_type' => 'Type',
                'th_sender' => 'Sender',
                'th_message' => 'Message',
                'th_created' => 'Created',
                'th_actions' => 'Actions',
                'type_handler' => 'Merchant',
                'type_user' => 'Customer',
                'btn_take' => 'Take',
                'btn_resolve' => 'Resolve',
                'confirm_resolve' => 'Mark as resolved?',
            ]
        ];
    }

    /**
     * Get current language
     */
    private static function get_current_lang() {
        // Check URL parameter first
        if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'hu', 'en'])) {
            setcookie('ppv_admin_lang', $_GET['lang'], time() + 365*24*60*60, '/');
            return $_GET['lang'];
        }
        // Check cookie
        if (isset($_COOKIE['ppv_admin_lang']) && in_array($_COOKIE['ppv_admin_lang'], ['de', 'hu', 'en'])) {
            return $_COOKIE['ppv_admin_lang'];
        }
        // Default to German
        return 'de';
    }

    /**
     * Translation helper
     */
    private static function t($key, $lang) {
        $translations = self::get_translations();
        return $translations[$lang][$key] ?? $key;
    }

    /**
     * Render support tickets page
     */
    public static function render() {
        global $wpdb;

        // Handle status update
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
            self::handle_status_update();
        }

        // Get current language
        $lang = self::get_current_lang();

        // Get filters
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'open';
        $category_filter = isset($_GET['cat']) ? sanitize_text_field($_GET['cat']) : 'all';

        // Build WHERE conditions
        $conditions = [];

        // Status filter
        if ($status_filter === 'open') {
            $conditions[] = "t.status IN ('new', 'in_progress')";
        } elseif ($status_filter === 'resolved') {
            $conditions[] = "t.status = 'resolved'";
        } elseif ($status_filter === 'new') {
            $conditions[] = "t.status = 'new'";
        } elseif ($status_filter === 'in_progress') {
            $conditions[] = "t.status = 'in_progress'";
        }

        // Category filter
        if ($category_filter !== 'all' && in_array($category_filter, ['support', 'bug', 'feature', 'question', 'rating'])) {
            $conditions[] = $wpdb->prepare("t.category = %s", $category_filter);
        }

        $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Fetch support tickets with user info
        $tickets = $wpdb->get_results("
            SELECT
                t.*,
                s.name as store_name_db,
                s.company_name,
                s.city,
                u.email as user_email_db,
                u.first_name as user_first_name,
                u.last_name as user_last_name
            FROM {$wpdb->prefix}ppv_support_tickets t
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON t.store_id = s.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON t.user_id = u.id
            {$where_clause}
            ORDER BY
                FIELD(t.status, 'new', 'in_progress', 'resolved'),
                FIELD(t.priority, 'urgent', 'normal', 'low'),
                t.created_at DESC
        ");

        // Count by status
        $new_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE status = 'new'");
        $in_progress_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE status = 'in_progress'");
        $open_count = $new_count + $in_progress_count;
        $resolved_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE status = 'resolved'");

        // Count by category (only open tickets)
        $bug_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE category = 'bug' AND status IN ('new', 'in_progress')");
        $feature_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE category = 'feature' AND status IN ('new', 'in_progress')");
        $question_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE category = 'question' AND status IN ('new', 'in_progress')");
        $rating_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE category = 'rating' AND status IN ('new', 'in_progress')");
        $support_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE (category = 'support' OR category IS NULL) AND status IN ('new', 'in_progress')");

        $category_counts = [
            'bug' => intval($bug_count),
            'feature' => intval($feature_count),
            'question' => intval($question_count),
            'rating' => intval($rating_count),
            'support' => intval($support_count)
        ];

        self::render_html($tickets, $status_filter, $category_filter, $new_count, $in_progress_count, $open_count, $resolved_count, $category_counts, $lang);
    }

    /**
     * Handle status update
     */
    private static function handle_status_update() {
        global $wpdb;

        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['new_status'] ?? '');

        if ($ticket_id > 0 && in_array($new_status, ['new', 'in_progress', 'resolved'])) {
            $update_data = ['status' => $new_status];

            if ($new_status === 'resolved') {
                $update_data['resolved_at'] = current_time('mysql');
            }

            $wpdb->update(
                "{$wpdb->prefix}ppv_support_tickets",
                $update_data,
                ['id' => $ticket_id],
                $new_status === 'resolved' ? ['%s', '%s'] : ['%s'],
                ['%d']
            );

            ppv_log("✅ [Standalone Admin] Ticket #{$ticket_id} status updated to {$new_status}");
        }

        $current_status = isset($_GET['status']) ? $_GET['status'] : 'open';
        $current_cat = isset($_GET['cat']) ? $_GET['cat'] : 'all';
        $current_lang = isset($_GET['lang']) ? $_GET['lang'] : '';
        $lang_param = $current_lang ? "&lang={$current_lang}" : '';
        wp_redirect("/admin/support?status={$current_status}&cat={$current_cat}{$lang_param}&success=updated");
        exit;
    }

    /**
     * Render HTML
     */
    private static function render_html($tickets, $status_filter, $category_filter, $new_count, $in_progress_count, $open_count, $resolved_count, $category_counts, $lang) {
        $success = isset($_GET['success']) ? $_GET['success'] : '';
        $ticket_count = count($tickets);

        // Build base URL for language switcher
        $base_url = "/admin/support?status={$status_filter}&cat={$category_filter}";
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo $lang; ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo self::t('page_title', $lang); ?></title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #1a1a2e;
                    color: #e0e0e0;
                    min-height: 100vh;
                }
                .admin-header {
                    background: #16213e;
                    padding: 15px 20px;
                    border-bottom: 1px solid #0f3460;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 10px;
                }
                .admin-header h1 { font-size: 18px; color: #00d9ff; }
                .header-right { display: flex; align-items: center; gap: 15px; }
                .lang-switcher { display: flex; gap: 5px; }
                .lang-btn {
                    padding: 4px 8px;
                    border-radius: 4px;
                    text-decoration: none;
                    font-size: 12px;
                    font-weight: 600;
                    color: #888;
                    background: #0f3460;
                    transition: all 0.2s;
                }
                .lang-btn:hover { color: #fff; background: #1f4878; }
                .lang-btn.active { color: #000; background: #00d9ff; }
                .admin-header .back-link { color: #aaa; text-decoration: none; font-size: 14px; }
                .admin-header .back-link:hover { color: #00d9ff; }
                .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
                .success-msg {
                    background: #0f5132;
                    color: #d1e7dd;
                    padding: 12px 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }
                .filter-section {
                    margin-bottom: 15px;
                }
                .filter-section h3 {
                    font-size: 12px;
                    color: #888;
                    margin-bottom: 8px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .tabs {
                    display: flex;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .tab {
                    padding: 8px 16px;
                    background: #16213e;
                    border-radius: 8px;
                    text-decoration: none;
                    color: #aaa;
                    font-weight: 600;
                    font-size: 13px;
                    transition: all 0.2s;
                }
                .tab:hover { background: #1f2b4d; color: #fff; }
                .tab.active { background: #00d9ff; color: #000; }
                .tab.cat-bug.active { background: #ff6b6b; }
                .tab.cat-feature.active { background: #ffd93d; color: #000; }
                .tab.cat-question.active { background: #6bcb77; }
                .tab.cat-rating.active { background: #a78bfa; }
                .tab.cat-support.active { background: #00d9ff; }
                .empty-state {
                    text-align: center;
                    padding: 60px 20px;
                    background: #16213e;
                    border-radius: 12px;
                    color: #888;
                }
                .empty-state i { font-size: 48px; margin-bottom: 15px; display: block; color: #00d9ff; }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    background: #16213e;
                    border-radius: 12px;
                    overflow: hidden;
                }
                th, td {
                    padding: 12px 15px;
                    text-align: left;
                    border-bottom: 1px solid #0f3460;
                    font-size: 13px;
                }
                th { background: #0f3460; color: #00d9ff; font-weight: 600; }
                tr:hover { background: #1f2b4d; }
                tr.urgent { background: #3d1f1f; }
                tr.urgent:hover { background: #4d2929; }
                .badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 11px;
                    font-weight: 600;
                    white-space: nowrap;
                }
                .badge-success { background: #0f5132; color: #d1e7dd; }
                .badge-info { background: #084298; color: #cfe2ff; }
                .badge-warning { background: #664d03; color: #fff3cd; }
                .badge-error { background: #842029; color: #f8d7da; }
                .badge-purple { background: #5b21b6; color: #ede9fe; }
                .badge-teal { background: #0d9488; color: #ccfbf1; }
                .badge-user { background: #1e40af; color: #dbeafe; }
                .badge-handler { background: #065f46; color: #d1fae5; }
                .btn {
                    display: inline-block;
                    padding: 6px 12px;
                    border-radius: 6px;
                    font-size: 11px;
                    font-weight: 600;
                    text-decoration: none;
                    cursor: pointer;
                    border: none;
                    transition: all 0.2s;
                    margin: 2px;
                }
                .btn-primary { background: #00d9ff; color: #000; }
                .btn-primary:hover { background: #00b8d9; }
                .btn-warning { background: #ffb900; color: #000; }
                .btn-warning:hover { background: #e0a800; }
                .btn-secondary { background: #374151; color: #fff; }
                .btn-secondary:hover { background: #4b5563; }
                a { color: #00d9ff; text-decoration: none; }
                a:hover { text-decoration: underline; }
                .description { max-width: 250px; }
                .description-text {
                    display: block;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                .sender-info { line-height: 1.5; }
            </style>
        </head>
        <body>
            <div class="admin-header">
                <h1><?php echo self::t('header_title', $lang); ?> (<?php echo $open_count; ?> <?php echo self::t('open_tickets', $lang); ?>)</h1>
                <div class="header-right">
                    <div class="lang-switcher">
                        <a href="<?php echo $base_url; ?>&lang=de" class="lang-btn <?php echo $lang === 'de' ? 'active' : ''; ?>">DE</a>
                        <a href="<?php echo $base_url; ?>&lang=hu" class="lang-btn <?php echo $lang === 'hu' ? 'active' : ''; ?>">HU</a>
                        <a href="<?php echo $base_url; ?>&lang=en" class="lang-btn <?php echo $lang === 'en' ? 'active' : ''; ?>">EN</a>
                    </div>
                    <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> <?php echo self::t('back', $lang); ?></a>
                </div>
            </div>

            <div class="container">
                <?php if ($success === 'updated'): ?>
                    <div class="success-msg"><?php echo self::t('ticket_updated', $lang); ?></div>
                <?php endif; ?>

                <!-- Status Filter -->
                <div class="filter-section">
                    <h3><?php echo self::t('status', $lang); ?></h3>
                    <div class="tabs">
                        <a href="/admin/support?status=open&cat=<?php echo $category_filter; ?>&lang=<?php echo $lang; ?>" class="tab <?php echo $status_filter === 'open' ? 'active' : ''; ?>">
                            <?php echo self::t('status_open', $lang); ?> (<?php echo $open_count; ?>)
                        </a>
                        <a href="/admin/support?status=new&cat=<?php echo $category_filter; ?>&lang=<?php echo $lang; ?>" class="tab <?php echo $status_filter === 'new' ? 'active' : ''; ?>">
                            <?php echo self::t('status_new', $lang); ?> (<?php echo $new_count; ?>)
                        </a>
                        <a href="/admin/support?status=in_progress&cat=<?php echo $category_filter; ?>&lang=<?php echo $lang; ?>" class="tab <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">
                            <?php echo self::t('status_in_progress', $lang); ?> (<?php echo $in_progress_count; ?>)
                        </a>
                        <a href="/admin/support?status=resolved&cat=<?php echo $category_filter; ?>&lang=<?php echo $lang; ?>" class="tab <?php echo $status_filter === 'resolved' ? 'active' : ''; ?>">
                            <?php echo self::t('status_resolved', $lang); ?> (<?php echo $resolved_count; ?>)
                        </a>
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="filter-section">
                    <h3><?php echo self::t('category', $lang); ?></h3>
                    <div class="tabs">
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=all&lang=<?php echo $lang; ?>" class="tab <?php echo $category_filter === 'all' ? 'active' : ''; ?>">
                            <?php echo self::t('cat_all', $lang); ?>
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=bug&lang=<?php echo $lang; ?>" class="tab cat-bug <?php echo $category_filter === 'bug' ? 'active' : ''; ?>">
                            <?php echo self::t('cat_bug', $lang); ?> (<?php echo $category_counts['bug']; ?>)
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=feature&lang=<?php echo $lang; ?>" class="tab cat-feature <?php echo $category_filter === 'feature' ? 'active' : ''; ?>">
                            <?php echo self::t('cat_feature', $lang); ?> (<?php echo $category_counts['feature']; ?>)
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=question&lang=<?php echo $lang; ?>" class="tab cat-question <?php echo $category_filter === 'question' ? 'active' : ''; ?>">
                            <?php echo self::t('cat_question', $lang); ?> (<?php echo $category_counts['question']; ?>)
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=rating&lang=<?php echo $lang; ?>" class="tab cat-rating <?php echo $category_filter === 'rating' ? 'active' : ''; ?>">
                            <?php echo self::t('cat_rating', $lang); ?> (<?php echo $category_counts['rating']; ?>)
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=support&lang=<?php echo $lang; ?>" class="tab cat-support <?php echo $category_filter === 'support' ? 'active' : ''; ?>">
                            <?php echo self::t('cat_support', $lang); ?> (<?php echo $category_counts['support']; ?>)
                        </a>
                    </div>
                </div>

                <?php if ($ticket_count === 0): ?>
                    <div class="empty-state">
                        <i class="ri-checkbox-circle-line"></i>
                        <h3><?php echo self::t('no_tickets', $lang); ?></h3>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo self::t('th_id', $lang); ?></th>
                                <th><?php echo self::t('th_category', $lang); ?></th>
                                <th><?php echo self::t('th_type', $lang); ?></th>
                                <th><?php echo self::t('th_sender', $lang); ?></th>
                                <th><?php echo self::t('th_message', $lang); ?></th>
                                <th><?php echo self::t('th_created', $lang); ?></th>
                                <th><?php echo self::t('th_actions', $lang); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <?php
                                // Category badges with icons
                                $category_badges = [
                                    'bug' => ['icon' => 'ri-bug-line', 'text' => self::t('cat_bug', $lang), 'class' => 'error'],
                                    'feature' => ['icon' => 'ri-lightbulb-line', 'text' => self::t('cat_feature', $lang), 'class' => 'warning'],
                                    'question' => ['icon' => 'ri-question-line', 'text' => self::t('cat_question', $lang), 'class' => 'teal'],
                                    'rating' => ['icon' => 'ri-star-line', 'text' => self::t('cat_rating', $lang), 'class' => 'purple'],
                                    'support' => ['icon' => 'ri-customer-service-line', 'text' => self::t('cat_support', $lang), 'class' => 'info']
                                ];
                                $cat = $ticket->category ?? 'support';
                                $category_badge = $category_badges[$cat] ?? $category_badges['support'];

                                // User type badge
                                $user_type = $ticket->user_type ?? 'handler';
                                $user_type_badge = $user_type === 'handler'
                                    ? ['icon' => 'ri-store-2-line', 'text' => self::t('type_handler', $lang), 'class' => 'handler']
                                    : ['icon' => 'ri-user-line', 'text' => self::t('type_user', $lang), 'class' => 'user'];

                                // Status badges
                                $status_badges = [
                                    'new' => ['text' => self::t('status_new', $lang), 'class' => 'info'],
                                    'in_progress' => ['text' => self::t('status_in_progress', $lang), 'class' => 'warning'],
                                    'resolved' => ['text' => self::t('status_resolved', $lang), 'class' => 'success']
                                ];
                                $status_badge = $status_badges[$ticket->status] ?? $status_badges['new'];

                                $created_time = date('Y-m-d H:i', strtotime($ticket->created_at));

                                // Get sender name
                                $sender_name = '';
                                if ($user_type === 'user' && !empty($ticket->user_first_name)) {
                                    $sender_name = trim(($ticket->user_first_name ?? '') . ' ' . ($ticket->user_last_name ?? ''));
                                } elseif (!empty($ticket->company_name)) {
                                    $sender_name = $ticket->company_name;
                                } else {
                                    $sender_name = $ticket->store_name;
                                }

                                $description_short = mb_strlen($ticket->description) > 80
                                    ? mb_substr($ticket->description, 0, 80) . '...'
                                    : $ticket->description;
                                ?>
                                <tr class="<?php echo $ticket->priority === 'urgent' ? 'urgent' : ''; ?>">
                                    <td>
                                        <strong>#<?php echo intval($ticket->id); ?></strong>
                                        <br><span class="badge badge-<?php echo $status_badge['class']; ?>"><?php echo $status_badge['text']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $category_badge['class']; ?>">
                                            <i class="<?php echo $category_badge['icon']; ?>"></i> <?php echo $category_badge['text']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user_type_badge['class']; ?>">
                                            <i class="<?php echo $user_type_badge['icon']; ?>"></i> <?php echo $user_type_badge['text']; ?>
                                        </span>
                                    </td>
                                    <td class="sender-info">
                                        <strong><?php echo esc_html($sender_name); ?></strong>
                                        <br><a href="mailto:<?php echo esc_attr($ticket->handler_email); ?>" style="font-size: 11px;"><?php echo esc_html($ticket->handler_email); ?></a>
                                    </td>
                                    <td class="description">
                                        <span class="description-text" title="<?php echo esc_attr($ticket->description); ?>">
                                            <?php echo esc_html($description_short); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $created_time; ?></td>
                                    <td>
                                        <?php if ($ticket->status === 'new'): ?>
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="ticket_id" value="<?php echo intval($ticket->id); ?>">
                                                <input type="hidden" name="new_status" value="in_progress">
                                                <button type="submit" class="btn btn-warning"><?php echo self::t('btn_take', $lang); ?></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($ticket->status !== 'resolved'): ?>
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="ticket_id" value="<?php echo intval($ticket->id); ?>">
                                                <input type="hidden" name="new_status" value="resolved">
                                                <button type="submit" class="btn btn-primary" onclick="return confirm('<?php echo self::t('confirm_resolve', $lang); ?>');">✅</button>
                                            </form>
                                        <?php endif; ?>

                                        <a href="mailto:<?php echo esc_attr($ticket->handler_email); ?>?subject=Support%20Ticket%20%23<?php echo intval($ticket->id); ?>" class="btn btn-secondary">📧</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
}
