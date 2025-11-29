<?php
/**
 * PunktePass Standalone Admin - Függő Scanek
 * Route: /admin/pending-scans
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_PendingScans {

    /**
     * Render pending scans page
     */
    public static function render() {
        global $wpdb;

        // Handle approve/reject
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['approve'])) {
                self::handle_approve();
            } elseif (isset($_POST['reject'])) {
                self::handle_reject();
            }
        }

        // Get filter status
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pending';

        // Get scans
        $where = '';
        if ($status_filter !== 'all') {
            $where = $wpdb->prepare("WHERE ps.status = %s", $status_filter);
        }

        $scans = $wpdb->get_results("
            SELECT
                ps.*,
                s.name as store_name,
                s.company_name,
                s.city as store_city,
                u.email as user_email,
                u.first_name,
                u.last_name
            FROM {$wpdb->prefix}ppv_pending_scans ps
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON ps.store_id = s.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON ps.user_id = u.id
            {$where}
            ORDER BY ps.created_at DESC
            LIMIT 200
        ");

        // Count by status
        $counts = [
            'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pending_scans WHERE status = 'pending'"),
            'approved' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pending_scans WHERE status = 'approved'"),
            'rejected' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pending_scans WHERE status = 'rejected'"),
        ];
        $counts['all'] = array_sum($counts);

        self::render_html($scans, $status_filter, $counts);
    }

    /**
     * Handle approve action
     */
    private static function handle_approve() {
        global $wpdb;

        $scan_id = intval($_POST['scan_id'] ?? 0);

        if ($scan_id > 0) {
            // Get scan info
            $scan = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ppv_pending_scans WHERE id = %d",
                $scan_id
            ));

            if ($scan && $scan->status === 'pending') {
                // Add points to user
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ppv_user_points SET points = points + %d WHERE user_id = %d AND store_id = %d",
                    $scan->points,
                    $scan->user_id,
                    $scan->store_id
                ));

                // Update scan status
                $wpdb->update(
                    "{$wpdb->prefix}ppv_pending_scans",
                    [
                        'status' => 'approved',
                        'reviewed_at' => current_time('mysql')
                    ],
                    ['id' => $scan_id]
                );

                ppv_log("✅ [Standalone Admin] Pending scan #{$scan_id} approved, {$scan->points} points added");
            }
        }

        wp_redirect("/admin/pending-scans?success=approved");
        exit;
    }

    /**
     * Handle reject action
     */
    private static function handle_reject() {
        global $wpdb;

        $scan_id = intval($_POST['scan_id'] ?? 0);

        if ($scan_id > 0) {
            $wpdb->update(
                "{$wpdb->prefix}ppv_pending_scans",
                [
                    'status' => 'rejected',
                    'reviewed_at' => current_time('mysql')
                ],
                ['id' => $scan_id]
            );

            ppv_log("✅ [Standalone Admin] Pending scan #{$scan_id} rejected");
        }

        wp_redirect("/admin/pending-scans?success=rejected");
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
            <title>Függő Scanek - PunktePass Admin</title>
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
                .badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 11px;
                    font-weight: 600;
                    white-space: nowrap;
                }
                .badge-success { background: #0f5132; color: #d1e7dd; }
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
                .btn-danger { background: #842029; color: #f8d7da; }
                a { color: #00d9ff; text-decoration: none; }
                .points { color: #00d9ff; font-weight: 700; font-size: 16px; }
                .distance { color: #ff6b6b; font-weight: 600; }
            </style>
        </head>
        <body>
            <div class="admin-header">
                <h1>⏳ Függő Scanek (<?php echo $counts['pending']; ?> várakozó)</h1>
                <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza</a>
            </div>

            <div class="container">
                <?php if ($success): ?>
                    <div class="success-msg">
                        <?php echo $success === 'approved' ? '✅ Scan jóváhagyva, pontok jóváírva!' : '❌ Scan elutasítva!'; ?>
                    </div>
                <?php endif; ?>

                <div class="tabs">
                    <a href="/admin/pending-scans?status=pending" class="tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                        ⏳ Várakozó (<?php echo $counts['pending']; ?>)
                    </a>
                    <a href="/admin/pending-scans?status=approved" class="tab <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                        ✅ Jóváhagyott (<?php echo $counts['approved']; ?>)
                    </a>
                    <a href="/admin/pending-scans?status=rejected" class="tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                        ❌ Elutasított (<?php echo $counts['rejected']; ?>)
                    </a>
                    <a href="/admin/pending-scans?status=all" class="tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                        📋 Mind (<?php echo $counts['all']; ?>)
                    </a>
                </div>

                <?php if (empty($scans)): ?>
                    <div class="empty-state">
                        <i class="ri-checkbox-circle-line"></i>
                        <h3>Nincs függő scan ebben a kategóriában!</h3>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Bolt</th>
                                <th>Felhasználó</th>
                                <th>Pontok</th>
                                <th>Távolság</th>
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
                                    'pending' => ['text' => '⏳ Várakozó', 'class' => 'warning'],
                                    'approved' => ['text' => '✅ Jóváhagyott', 'class' => 'success'],
                                    'rejected' => ['text' => '❌ Elutasított', 'class' => 'error']
                                ];
                                $badge = $status_badges[$scan->status] ?? $status_badges['pending'];
                                ?>
                                <tr>
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
                                    <td><span class="points">+<?php echo intval($scan->points); ?></span></td>
                                    <td>
                                        <span class="distance"><?php echo number_format($scan->distance_meters ?? 0); ?> m</span>
                                    </td>
                                    <td><span class="badge badge-<?php echo $badge['class']; ?>"><?php echo $badge['text']; ?></span></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($scan->created_at)); ?></td>
                                    <td>
                                        <?php if ($scan->status === 'pending'): ?>
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="approve" value="1">
                                                <input type="hidden" name="scan_id" value="<?php echo intval($scan->id); ?>">
                                                <button type="submit" class="btn btn-primary" onclick="return confirm('Jóváhagyja a pontok jóváírását?');">✅ Jóváhagy</button>
                                            </form>
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="reject" value="1">
                                                <input type="hidden" name="scan_id" value="<?php echo intval($scan->id); ?>">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Elutasítja a scant?');">❌</button>
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
