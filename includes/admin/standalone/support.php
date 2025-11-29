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

        // Get filter status
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'open';

        // Build query based on filter
        $where_clause = '';
        if ($status_filter === 'open') {
            $where_clause = "WHERE t.status IN ('new', 'in_progress')";
        } elseif ($status_filter === 'resolved') {
            $where_clause = "WHERE t.status = 'resolved'";
        } elseif ($status_filter === 'new') {
            $where_clause = "WHERE t.status = 'new'";
        } elseif ($status_filter === 'in_progress') {
            $where_clause = "WHERE t.status = 'in_progress'";
        }

        // Fetch support tickets
        $tickets = $wpdb->get_results("
            SELECT
                t.*,
                s.name as store_name_db,
                s.company_name,
                s.city
            FROM {$wpdb->prefix}ppv_support_tickets t
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON t.store_id = s.id
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

        self::render_html($tickets, $status_filter, $new_count, $in_progress_count, $open_count, $resolved_count);
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
    private static function render_html($tickets, $status_filter, $new_count, $in_progress_count, $open_count, $resolved_count) {
        $success = isset($_GET['success']) ? $_GET['success'] : '';
        $ticket_count = count($tickets);
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Támogatási jegyek - PunktePass Admin</title>
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
                .tabs {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 20px;
                    flex-wrap: wrap;
                }
                .tab {
                    padding: 10px 20px;
                    background: #16213e;
                    border-radius: 8px;
                    text-decoration: none;
                    color: #aaa;
                    font-weight: 600;
                    font-size: 14px;
                    transition: all 0.2s;
                }
                .tab:hover { background: #1f2b4d; color: #fff; }
                .tab.active { background: #00d9ff; color: #000; }
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
            </style>
        </head>
        <body>
            <div class="admin-header">
                <h1>🆘 Támogatási jegyek (<?php echo $open_count; ?> nyitott)</h1>
                <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza</a>
            </div>

            <div class="container">
                <?php if ($success === 'updated'): ?>
                    <div class="success-msg">✅ Jegy státusza frissítve!</div>
                <?php endif; ?>

                <div class="tabs">
                    <a href="/admin/support?status=open" class="tab <?php echo $status_filter === 'open' ? 'active' : ''; ?>">
                        🟡 Nyitott (<?php echo $open_count; ?>)
                    </a>
                    <a href="/admin/support?status=new" class="tab <?php echo $status_filter === 'new' ? 'active' : ''; ?>">
                        🆕 Új (<?php echo $new_count; ?>)
                    </a>
                    <a href="/admin/support?status=in_progress" class="tab <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">
                        🔄 Folyamatban (<?php echo $in_progress_count; ?>)
                    </a>
                    <a href="/admin/support?status=resolved" class="tab <?php echo $status_filter === 'resolved' ? 'active' : ''; ?>">
                        ✅ Megoldva (<?php echo $resolved_count; ?>)
                    </a>
                </div>

                <?php if ($ticket_count === 0): ?>
                    <div class="empty-state">
                        <i class="ri-checkbox-circle-line"></i>
                        <h3>Nincs jegy ebben a kategóriában!</h3>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Prioritás</th>
                                <th>Státusz</th>
                                <th>Cégnév</th>
                                <th>Kapcsolat</th>
                                <th>Probléma</th>
                                <th>Létrehozva</th>
                                <th>Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <?php
                                $priority_badges = [
                                    'low' => ['text' => '🟢 Alacsony', 'class' => 'success'],
                                    'normal' => ['text' => '🟡 Normál', 'class' => 'warning'],
                                    'urgent' => ['text' => '🔴 Sürgős', 'class' => 'error']
                                ];
                                $priority_badge = $priority_badges[$ticket->priority] ?? $priority_badges['normal'];

                                $status_badges = [
                                    'new' => ['text' => '🆕 Új', 'class' => 'info'],
                                    'in_progress' => ['text' => '🔄 Folyamatban', 'class' => 'warning'],
                                    'resolved' => ['text' => '✅ Megoldva', 'class' => 'success']
                                ];
                                $status_badge = $status_badges[$ticket->status] ?? $status_badges['new'];

                                $created_time = date('Y-m-d H:i', strtotime($ticket->created_at));
                                $description_short = mb_strlen($ticket->description) > 60
                                    ? mb_substr($ticket->description, 0, 60) . '...'
                                    : $ticket->description;
                                ?>
                                <tr class="<?php echo $ticket->priority === 'urgent' ? 'urgent' : ''; ?>">
                                    <td><strong>#<?php echo intval($ticket->id); ?></strong></td>
                                    <td><span class="badge badge-<?php echo $priority_badge['class']; ?>"><?php echo $priority_badge['text']; ?></span></td>
                                    <td><span class="badge badge-<?php echo $status_badge['class']; ?>"><?php echo $status_badge['text']; ?></span></td>
                                    <td>
                                        <strong><?php echo esc_html($ticket->company_name ?: $ticket->store_name); ?></strong>
                                        <?php if (!empty($ticket->city)): ?>
                                            <br><small style="color: #888;"><?php echo esc_html($ticket->city); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo esc_attr($ticket->handler_email); ?>"><?php echo esc_html($ticket->handler_email); ?></a>
                                        <?php if (!empty($ticket->handler_phone)): ?>
                                            <br><a href="tel:<?php echo esc_attr($ticket->handler_phone); ?>">📞 <?php echo esc_html($ticket->handler_phone); ?></a>
                                        <?php endif; ?>
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
                                                <button type="submit" class="btn btn-warning">🔄 Felvétel</button>
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
