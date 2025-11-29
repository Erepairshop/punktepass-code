<?php
/**
 * PunktePass Standalone Admin - POS Log
 * Route: /admin/pos-log
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_POSLog {

    /**
     * Render POS log page
     */
    public static function render() {
        global $wpdb;

        // Get filter
        $store_filter = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;
        $type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';

        // Get all stores
        $stores = $wpdb->get_results("SELECT id, name, company_name FROM {$wpdb->prefix}ppv_stores ORDER BY name ASC");

        // Build where clause
        $where_parts = [];
        if ($store_filter > 0) {
            $where_parts[] = $wpdb->prepare("l.store_id = %d", $store_filter);
        }
        if ($type_filter) {
            $where_parts[] = $wpdb->prepare("l.type = %s", $type_filter);
        }

        $where = '';
        if (!empty($where_parts)) {
            $where = 'WHERE ' . implode(' AND ', $where_parts);
        }

        // Get log entries
        $logs = $wpdb->get_results("
            SELECT
                l.*,
                s.name as store_name,
                s.company_name
            FROM {$wpdb->prefix}ppv_pos_log l
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON l.store_id = s.id
            {$where}
            ORDER BY l.id DESC
            LIMIT 200
        ");

        // Get stats
        $total_points_given = $wpdb->get_var("SELECT SUM(points_change) FROM {$wpdb->prefix}ppv_pos_log WHERE points_change > 0");
        $total_points_redeemed = $wpdb->get_var("SELECT ABS(SUM(points_change)) FROM {$wpdb->prefix}ppv_pos_log WHERE points_change < 0");
        $total_rewards = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pos_log WHERE reward_title IS NOT NULL AND reward_title != ''");

        self::render_html($logs, $stores, $store_filter, $type_filter, $total_points_given, $total_points_redeemed, $total_rewards);
    }

    /**
     * Render HTML
     */
    private static function render_html($logs, $stores, $store_filter, $type_filter, $total_points_given, $total_points_redeemed, $total_rewards) {
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>POS Log - PunktePass Admin</title>
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
                .filter-box {
                    background: #16213e;
                    padding: 15px 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    display: flex;
                    gap: 20px;
                    flex-wrap: wrap;
                    align-items: center;
                }
                .filter-box select {
                    padding: 10px 15px;
                    border-radius: 6px;
                    border: 1px solid #0f3460;
                    background: #1a1a2e;
                    color: #e0e0e0;
                    font-size: 14px;
                    min-width: 200px;
                }
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 15px;
                    margin-bottom: 20px;
                }
                .stat-card {
                    background: #16213e;
                    padding: 20px;
                    border-radius: 12px;
                    text-align: center;
                }
                .stat-card .number {
                    font-size: 28px;
                    font-weight: 700;
                    color: #00d9ff;
                }
                .stat-card .number.green { color: #4ade80; }
                .stat-card .number.red { color: #f87171; }
                .stat-card .label {
                    font-size: 12px;
                    color: #888;
                    margin-top: 5px;
                }
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
                .points-plus { color: #4ade80; font-weight: 700; }
                .points-minus { color: #f87171; font-weight: 700; }
                .reward-badge {
                    display: inline-block;
                    background: #7c3aed;
                    color: #fff;
                    padding: 3px 8px;
                    border-radius: 12px;
                    font-size: 11px;
                }
                a { color: #00d9ff; text-decoration: none; }
                .empty-state {
                    text-align: center;
                    padding: 60px 20px;
                    background: #16213e;
                    border-radius: 12px;
                    color: #888;
                }
            </style>
        </head>
        <body>
            <div class="admin-header">
                <h1>📊 POS Log</h1>
                <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza</a>
            </div>

            <div class="container">
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number green">+<?php echo number_format($total_points_given ?: 0); ?></div>
                        <div class="label">Összes kiosztott pont</div>
                    </div>
                    <div class="stat-card">
                        <div class="number red">-<?php echo number_format($total_points_redeemed ?: 0); ?></div>
                        <div class="label">Összes beváltott pont</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo number_format($total_rewards ?: 0); ?></div>
                        <div class="label">Összes jutalom beváltás</div>
                    </div>
                </div>

                <!-- Filter -->
                <div class="filter-box">
                    <form method="get" action="/admin/pos-log" style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <div>
                            <label style="display: block; font-size: 12px; margin-bottom: 5px; color: #888;">Bolt:</label>
                            <select name="store_id" onchange="this.form.submit()">
                                <option value="0">-- Összes bolt --</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo $store->id; ?>" <?php selected($store_filter, $store->id); ?>>
                                        <?php echo esc_html($store->company_name ?: $store->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; margin-bottom: 5px; color: #888;">Típus:</label>
                            <select name="type" onchange="this.form.submit()">
                                <option value="">-- Mind --</option>
                                <option value="scan" <?php selected($type_filter, 'scan'); ?>>Scan (pont jóváírás)</option>
                                <option value="redeem" <?php selected($type_filter, 'redeem'); ?>>Jutalom beváltás</option>
                            </select>
                        </div>
                    </form>
                </div>

                <?php if (empty($logs)): ?>
                    <div class="empty-state">
                        <h3>Nincs log bejegyzés</h3>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Időpont</th>
                                <th>Felhasználó</th>
                                <th>Bolt</th>
                                <th>Változás</th>
                                <th>Jutalom</th>
                                <th>Megjegyzés</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $store_name = $log->company_name ?: $log->store_name ?: 'Store #' . $log->store_id;
                                $points_class = ($log->points_change ?? 0) >= 0 ? 'points-plus' : 'points-minus';
                                $points_prefix = ($log->points_change ?? 0) >= 0 ? '+' : '';
                                ?>
                                <tr>
                                    <td><strong>#<?php echo intval($log->id); ?></strong></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($log->created_at)); ?></td>
                                    <td>
                                        <a href="mailto:<?php echo esc_attr($log->email); ?>">
                                            <?php echo esc_html($log->email); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($store_name); ?></td>
                                    <td class="<?php echo $points_class; ?>">
                                        <?php echo $points_prefix . intval($log->points_change); ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($log->reward_title)): ?>
                                            <span class="reward-badge">🎁 <?php echo esc_html($log->reward_title); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo esc_html($log->message ?: '-'); ?>
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
