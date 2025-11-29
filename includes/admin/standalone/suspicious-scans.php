<?php
/**
 * PunktePass Standalone Admin - Gyanús Scans
 * Route: /admin/suspicious-scans
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_SuspiciousScans {

    /**
     * Render suspicious scans page
     */
    public static function render() {
        global $wpdb;

        // Handle status update
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
            self::handle_status_update();
        }

        // Get filter status
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'new';

        // Get scans directly from DB
        $where = '';
        if ($status_filter !== 'all') {
            $where = $wpdb->prepare("WHERE ss.status = %s", $status_filter);
        }

        $scans = $wpdb->get_results("
            SELECT
                ss.*,
                s.name as store_name,
                s.company_name,
                s.city as store_city,
                s.latitude as store_latitude,
                s.longitude as store_longitude,
                u.email as user_email,
                u.first_name,
                u.last_name
            FROM {$wpdb->prefix}ppv_suspicious_scans ss
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON ss.store_id = s.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON ss.user_id = u.id
            {$where}
            ORDER BY ss.created_at DESC
            LIMIT 200
        ");

        // Count by status
        $counts = [
            'new' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_suspicious_scans WHERE status = 'new'"),
            'reviewed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_suspicious_scans WHERE status = 'reviewed'"),
            'dismissed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_suspicious_scans WHERE status = 'dismissed'"),
            'blocked' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_suspicious_scans WHERE status = 'blocked'"),
        ];
        $counts['all'] = array_sum($counts);

        self::render_html($scans, $status_filter, $counts);
    }

    /**
     * Handle status update
     */
    private static function handle_status_update() {
        global $wpdb;

        $scan_id = intval($_POST['scan_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['new_status'] ?? '');

        if ($scan_id > 0 && in_array($new_status, ['new', 'reviewed', 'dismissed', 'blocked'])) {
            $wpdb->update(
                "{$wpdb->prefix}ppv_suspicious_scans",
                [
                    'status' => $new_status,
                    'reviewed_at' => current_time('mysql')
                ],
                ['id' => $scan_id],
                ['%s', '%s'],
                ['%d']
            );
            ppv_log("✅ [Standalone Admin] Suspicious scan #{$scan_id} status updated to {$new_status}");
        }

        $current_status = isset($_GET['status']) ? $_GET['status'] : 'new';
        wp_redirect("/admin/suspicious-scans?status={$current_status}&success=updated");
        exit;
    }

    /**
     * Render HTML
     */
    private static function render_html($scans, $status_filter, $counts) {
        $success = isset($_GET['success']) ? $_GET['success'] : '';
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Gyanús Scans - PunktePass Admin</title>
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
                    font-size: 12px;
                }
                th { background: #0f3460; color: #00d9ff; font-weight: 600; }
                tr:hover { background: #1f2b4d; }
                tr.new-row { background: #3d3d1f; }
                tr.new-row:hover { background: #4d4d29; }
                .badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 10px;
                    font-weight: 600;
                    white-space: nowrap;
                }
                .badge-success { background: #0f5132; color: #d1e7dd; }
                .badge-info { background: #084298; color: #cfe2ff; }
                .badge-warning { background: #664d03; color: #fff3cd; }
                .badge-error { background: #842029; color: #f8d7da; }
                .btn {
                    display: inline-block;
                    padding: 5px 10px;
                    border-radius: 6px;
                    font-size: 10px;
                    font-weight: 600;
                    text-decoration: none;
                    cursor: pointer;
                    border: none;
                    transition: all 0.2s;
                    margin: 2px;
                }
                .btn-primary { background: #00d9ff; color: #000; }
                .btn-success { background: #0f5132; color: #d1e7dd; }
                .btn-danger { background: #842029; color: #f8d7da; }
                .btn-secondary { background: #374151; color: #fff; }
                a { color: #00d9ff; text-decoration: none; }
                .distance { color: #ff6b6b; font-weight: 700; }
                .coords { font-size: 10px; color: #888; }
            </style>
        </head>
        <body>
            <div class="admin-header">
                <h1>🔍 Gyanús Scans (<?php echo $counts['new']; ?> új)</h1>
                <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza</a>
            </div>

            <div class="container">
                <?php if ($success === 'updated'): ?>
                    <div class="success-msg">✅ Státusz frissítve!</div>
                <?php endif; ?>

                <div class="tabs">
                    <a href="/admin/suspicious-scans?status=new" class="tab <?php echo $status_filter === 'new' ? 'active' : ''; ?>">
                        🆕 Új (<?php echo $counts['new']; ?>)
                    </a>
                    <a href="/admin/suspicious-scans?status=reviewed" class="tab <?php echo $status_filter === 'reviewed' ? 'active' : ''; ?>">
                        👁️ Ellenőrizve (<?php echo $counts['reviewed']; ?>)
                    </a>
                    <a href="/admin/suspicious-scans?status=dismissed" class="tab <?php echo $status_filter === 'dismissed' ? 'active' : ''; ?>">
                        ✅ Elvetve (<?php echo $counts['dismissed']; ?>)
                    </a>
                    <a href="/admin/suspicious-scans?status=blocked" class="tab <?php echo $status_filter === 'blocked' ? 'active' : ''; ?>">
                        🚫 Tiltva (<?php echo $counts['blocked']; ?>)
                    </a>
                    <a href="/admin/suspicious-scans?status=all" class="tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                        📋 Mind (<?php echo $counts['all']; ?>)
                    </a>
                </div>

                <?php if (empty($scans)): ?>
                    <div class="empty-state">
                        <i class="ri-checkbox-circle-line"></i>
                        <h3>Nincs gyanús scan ebben a kategóriában!</h3>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Bolt</th>
                                <th>Felhasználó</th>
                                <th>Távolság</th>
                                <th>GPS koordináták</th>
                                <th>Státusz</th>
                                <th>Időpont</th>
                                <th>Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scans as $scan): ?>
                                <?php
                                $user_name = trim(($scan->first_name ?? '') . ' ' . ($scan->last_name ?? '')) ?: 'User #' . $scan->user_id;
                                $store_name = $scan->company_name ?: $scan->store_name ?: 'Store #' . $scan->store_id;

                                $status_badges = [
                                    'new' => ['text' => '🆕 Új', 'class' => 'warning'],
                                    'reviewed' => ['text' => '👁️ Ellenőrizve', 'class' => 'info'],
                                    'dismissed' => ['text' => '✅ Elvetve', 'class' => 'success'],
                                    'blocked' => ['text' => '🚫 Tiltva', 'class' => 'error']
                                ];
                                $badge = $status_badges[$scan->status] ?? $status_badges['new'];
                                ?>
                                <tr class="<?php echo $scan->status === 'new' ? 'new-row' : ''; ?>">
                                    <td><strong>#<?php echo intval($scan->id); ?></strong></td>
                                    <td>
                                        <strong><?php echo esc_html($store_name); ?></strong>
                                        <?php if (!empty($scan->store_city)): ?>
                                            <br><small style="color: #888;"><?php echo esc_html($scan->store_city); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($user_name); ?>
                                        <?php if (!empty($scan->user_email)): ?>
                                            <br><small><a href="mailto:<?php echo esc_attr($scan->user_email); ?>"><?php echo esc_html($scan->user_email); ?></a></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="distance"><?php echo number_format($scan->distance_meters ?? 0); ?> m</span>
                                    </td>
                                    <td class="coords">
                                        <strong>Scan:</strong> <?php echo esc_html($scan->scan_latitude); ?>, <?php echo esc_html($scan->scan_longitude); ?><br>
                                        <strong>Bolt:</strong> <?php echo esc_html($scan->store_latitude); ?>, <?php echo esc_html($scan->store_longitude); ?>
                                    </td>
                                    <td><span class="badge badge-<?php echo $badge['class']; ?>"><?php echo $badge['text']; ?></span></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($scan->created_at)); ?></td>
                                    <td>
                                        <?php if ($scan->status === 'new'): ?>
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="scan_id" value="<?php echo intval($scan->id); ?>">
                                                <input type="hidden" name="new_status" value="reviewed">
                                                <button type="submit" class="btn btn-secondary">👁️</button>
                                            </form>
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="scan_id" value="<?php echo intval($scan->id); ?>">
                                                <input type="hidden" name="new_status" value="dismissed">
                                                <button type="submit" class="btn btn-success">✅</button>
                                            </form>
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="scan_id" value="<?php echo intval($scan->id); ?>">
                                                <input type="hidden" name="new_status" value="blocked">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Tiltani szeretné?');">🚫</button>
                                            </form>
                                        <?php endif; ?>
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
