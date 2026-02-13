<?php
/**
 * PunktePass Standalone Admin - Eszköz Monitoring
 * Route: /admin/devices
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Devices {

    /**
     * Render devices monitoring page
     */
    public static function render() {
        global $wpdb;

        // Get selected store filter
        $store_filter = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;

        // Get all stores
        $stores = $wpdb->get_results("SELECT id, name, company_name FROM {$wpdb->prefix}ppv_stores ORDER BY name ASC");

        // Build where clause
        $where = '';
        if ($store_filter > 0) {
            $where = $wpdb->prepare("WHERE d.store_id = %d", $store_filter);
        }

        // Get devices with store info + last logged-in user
        $devices = $wpdb->get_results("
            SELECT
                d.*,
                s.name as store_name,
                s.company_name,
                s.city as store_city,
                login_user.email as last_user_email,
                login_user.username as last_user_name
            FROM {$wpdb->prefix}ppv_user_devices d
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON d.store_id = s.id
            LEFT JOIN (
                SELECT dl.fingerprint_hash, dl.user_id,
                       ROW_NUMBER() OVER (PARTITION BY dl.fingerprint_hash ORDER BY dl.created_at DESC) as rn
                FROM {$wpdb->prefix}ppv_device_logins dl
            ) latest_login ON latest_login.fingerprint_hash = d.fingerprint_hash AND latest_login.rn = 1
            LEFT JOIN {$wpdb->prefix}ppv_users login_user ON login_user.id = latest_login.user_id
            {$where}
            ORDER BY d.last_used_at DESC
            LIMIT 500
        ");

        // Get device stats per store
        $device_stats = $wpdb->get_results("
            SELECT
                store_id,
                COUNT(*) as device_count,
                COUNT(DISTINCT device_fingerprint) as unique_devices,
                MAX(last_used_at) as last_activity
            FROM {$wpdb->prefix}ppv_user_devices
            GROUP BY store_id
            ORDER BY device_count DESC
        ");

        self::render_html($devices, $stores, $store_filter, $device_stats);
    }

    /**
     * Render HTML
     */
    private static function render_html($devices, $stores, $store_filter, $device_stats) {
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Eszköz Monitoring - PunktePass Admin</title>
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
                }
                .filter-box select {
                    padding: 10px 15px;
                    border-radius: 6px;
                    border: 1px solid #0f3460;
                    background: #1a1a2e;
                    color: #e0e0e0;
                    font-size: 14px;
                    min-width: 250px;
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
                    font-size: 32px;
                    font-weight: 700;
                    color: #00d9ff;
                }
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
                    font-size: 12px;
                }
                th { background: #0f3460; color: #00d9ff; font-weight: 600; }
                tr:hover { background: #1f2b4d; }
                .badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 10px;
                    font-weight: 600;
                }
                .badge-success { background: #0f5132; color: #d1e7dd; }
                .badge-warning { background: #664d03; color: #fff3cd; }
                .badge-info { background: #084298; color: #cfe2ff; }
                a { color: #00d9ff; text-decoration: none; }
                .device-info { font-size: 11px; color: #888; }
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
                <h1>📱 Eszköz Monitoring</h1>
                <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza</a>
            </div>

            <div class="container">
                <!-- Filter -->
                <div class="filter-box">
                    <form method="get" action="/admin/devices">
                        <label style="margin-right: 10px; font-weight: 600;">Bolt szűrés:</label>
                        <select name="store_id" onchange="this.form.submit()">
                            <option value="0">-- Összes bolt --</option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo $store->id; ?>" <?php selected($store_filter, $store->id); ?>>
                                    <?php echo esc_html($store->company_name ?: $store->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo count($devices); ?></div>
                        <div class="label">Összes eszköz</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo count($stores); ?></div>
                        <div class="label">Összes bolt</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo count(array_unique(array_column($devices, 'device_fingerprint'))); ?></div>
                        <div class="label">Egyedi fingerprint</div>
                    </div>
                </div>

                <?php if (empty($devices)): ?>
                    <div class="empty-state">
                        <h3>Nincs eszköz adat</h3>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Bolt</th>
                                <th>Felhasználó</th>
                                <th>Eszköz</th>
                                <th>Fingerprint</th>
                                <th>Státusz</th>
                                <th>Utolsó használat</th>
                                <th>Létrehozva</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devices as $device): ?>
                                <?php
                                $store_name = $device->company_name ?: $device->store_name ?: 'Store #' . $device->store_id;
                                $is_active = strtotime($device->last_used_at) > strtotime('-7 days');
                                ?>
                                <tr>
                                    <td><strong>#<?php echo intval($device->id); ?></strong></td>
                                    <td>
                                        <strong><?php echo esc_html($store_name); ?></strong>
                                        <?php if (!empty($device->store_city)): ?>
                                            <br><small style="color: #888;"><?php echo esc_html($device->store_city); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($device->last_user_email)): ?>
                                            <strong style="color: #00d9ff;"><?php echo esc_html($device->last_user_name ?: ''); ?></strong>
                                            <br><small style="color: #888;"><?php echo esc_html($device->last_user_email); ?></small>
                                        <?php else: ?>
                                            <span style="color: #666;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($device->device_name ?: 'Ismeretlen'); ?>
                                        <div class="device-info">
                                            <?php echo esc_html($device->device_type ?: ''); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <code style="font-size: 10px; color: #888;">
                                            <?php echo esc_html(substr($device->device_fingerprint ?? '', 0, 16)); ?>...
                                        </code>
                                    </td>
                                    <td>
                                        <?php if ($is_active): ?>
                                            <span class="badge badge-success">Aktív</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Inaktív</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($device->last_used_at)); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($device->created_at)); ?></td>
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
