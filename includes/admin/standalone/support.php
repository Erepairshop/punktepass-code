<?php
/**
 * PunktePass Standalone Admin - Támogatási jegyek
 * Route: /admin/support
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Support {

    /**
     * Render support tickets page
     */
    public static function render() {
        global $wpdb;

        // Handle status update
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
            self::handle_status_update();
        }

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

        self::render_html($tickets, $status_filter, $category_filter, $new_count, $in_progress_count, $open_count, $resolved_count, $category_counts);
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
        wp_redirect("/admin/support?status={$current_status}&success=updated");
        exit;
    }

    /**
     * Render HTML
     */
    private static function render_html($tickets, $status_filter, $category_filter, $new_count, $in_progress_count, $open_count, $resolved_count, $category_counts) {
        $success = isset($_GET['success']) ? $_GET['success'] : '';
        $ticket_count = count($tickets);
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Feedback & Support - PunktePass Admin</title>
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
                }
                .admin-header h1 { font-size: 18px; color: #00d9ff; }
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
                <h1>💬 Feedback & Support (<?php echo $open_count; ?> nyitott)</h1>
                <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza</a>
            </div>

            <div class="container">
                <?php if ($success === 'updated'): ?>
                    <div class="success-msg">Jegy frissitve!</div>
                <?php endif; ?>

                <!-- Status Filter -->
                <div class="filter-section">
                    <h3>Staatusz</h3>
                    <div class="tabs">
                        <a href="/admin/support?status=open&cat=<?php echo $category_filter; ?>" class="tab <?php echo $status_filter === 'open' ? 'active' : ''; ?>">
                            Nyitott (<?php echo $open_count; ?>)
                        </a>
                        <a href="/admin/support?status=new&cat=<?php echo $category_filter; ?>" class="tab <?php echo $status_filter === 'new' ? 'active' : ''; ?>">
                            Uj (<?php echo $new_count; ?>)
                        </a>
                        <a href="/admin/support?status=in_progress&cat=<?php echo $category_filter; ?>" class="tab <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">
                            Folyamatban (<?php echo $in_progress_count; ?>)
                        </a>
                        <a href="/admin/support?status=resolved&cat=<?php echo $category_filter; ?>" class="tab <?php echo $status_filter === 'resolved' ? 'active' : ''; ?>">
                            Megoldva (<?php echo $resolved_count; ?>)
                        </a>
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="filter-section">
                    <h3>Kategoria</h3>
                    <div class="tabs">
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=all" class="tab <?php echo $category_filter === 'all' ? 'active' : ''; ?>">
                            Mind
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=bug" class="tab cat-bug <?php echo $category_filter === 'bug' ? 'active' : ''; ?>">
                            Bug (<?php echo $category_counts['bug']; ?>)
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=feature" class="tab cat-feature <?php echo $category_filter === 'feature' ? 'active' : ''; ?>">
                            Otlet (<?php echo $category_counts['feature']; ?>)
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=question" class="tab cat-question <?php echo $category_filter === 'question' ? 'active' : ''; ?>">
                            Kerdes (<?php echo $category_counts['question']; ?>)
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=rating" class="tab cat-rating <?php echo $category_filter === 'rating' ? 'active' : ''; ?>">
                            Ertekeles (<?php echo $category_counts['rating']; ?>)
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=support" class="tab cat-support <?php echo $category_filter === 'support' ? 'active' : ''; ?>">
                            Support (<?php echo $category_counts['support']; ?>)
                        </a>
                    </div>
                </div>

                <?php if ($ticket_count === 0): ?>
                    <div class="empty-state">
                        <i class="ri-checkbox-circle-line"></i>
                        <h3>Nincs jegy ebben a kategoriaban!</h3>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kategoria</th>
                                <th>Tipus</th>
                                <th>Kuldő</th>
                                <th>Uzenet</th>
                                <th>Letrehozva</th>
                                <th>Muveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <?php
                                // Category badges with icons
                                $category_badges = [
                                    'bug' => ['icon' => 'ri-bug-line', 'text' => 'Bug', 'class' => 'error'],
                                    'feature' => ['icon' => 'ri-lightbulb-line', 'text' => 'Otlet', 'class' => 'warning'],
                                    'question' => ['icon' => 'ri-question-line', 'text' => 'Kerdes', 'class' => 'teal'],
                                    'rating' => ['icon' => 'ri-star-line', 'text' => 'Ertekeles', 'class' => 'purple'],
                                    'support' => ['icon' => 'ri-customer-service-line', 'text' => 'Support', 'class' => 'info']
                                ];
                                $cat = $ticket->category ?? 'support';
                                $category_badge = $category_badges[$cat] ?? $category_badges['support'];

                                // User type badge
                                $user_type = $ticket->user_type ?? 'handler';
                                $user_type_badge = $user_type === 'handler'
                                    ? ['icon' => 'ri-store-2-line', 'text' => 'Handler', 'class' => 'handler']
                                    : ['icon' => 'ri-user-line', 'text' => 'User', 'class' => 'user'];

                                // Status badges
                                $status_badges = [
                                    'new' => ['text' => 'Uj', 'class' => 'info'],
                                    'in_progress' => ['text' => 'Folyamatban', 'class' => 'warning'],
                                    'resolved' => ['text' => 'Megoldva', 'class' => 'success']
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
                                                <button type="submit" class="btn btn-warning">Felvetel</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($ticket->status !== 'resolved'): ?>
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="ticket_id" value="<?php echo intval($ticket->id); ?>">
                                                <input type="hidden" name="new_status" value="resolved">
                                                <button type="submit" class="btn btn-primary" onclick="return confirm('Megoldottként jelöli?');">✅</button>
                                            </form>
                                        <?php endif; ?>

                                        <a href="mailto:<?php echo esc_attr($ticket->handler_email); ?>?subject=Támogatási%20jegy%20%23<?php echo intval($ticket->id); ?>" class="btn btn-secondary">📧</a>
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
