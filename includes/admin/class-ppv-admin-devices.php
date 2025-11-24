<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - Admin Device Monitoring
 * Displays device usage statistics per merchant
 */
class PPV_Admin_Devices {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_submenu'], 22);
    }

    /**
     * Add submenu under PunktePass
     */
    public static function add_admin_submenu() {
        add_submenu_page(
            'punktepass-admin',
            'Eszköz Monitoring',
            '📱 Eszközök',
            'manage_options',
            'punktepass-devices',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Render device monitoring page
     */
    public static function render_page() {
        global $wpdb;

        // Get selected store filter
        $store_filter = isset($_GET['store_id']) ? intval($_GET['store_id']) : 0;

        // Get all stores
        $stores = $wpdb->get_results("SELECT id, name, company_name FROM {$wpdb->prefix}ppv_stores ORDER BY name ASC");

        ?>
        <div class="wrap">
            <h1>📱 Eszköz Monitoring</h1>
            <p>Kereskedők eszköz használati statisztikái</p>

            <!-- Store Filter -->
            <div style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 8px;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="punktepass-devices">
                    <label for="store-filter" style="font-weight: 600; margin-right: 10px;">
                        Bolt szűrés:
                    </label>
                    <select name="store_id" id="store-filter" onchange="this.form.submit()" style="min-width: 250px;">
                        <option value="0">-- Összes bolt --</option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo $store->id; ?>" <?php selected($store_filter, $store->id); ?>>
                                <?php echo esc_html($store->company_name ?: $store->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($store_filter > 0): ?>
                <?php self::render_store_devices($store_filter); ?>
            <?php else: ?>
                <?php self::render_all_stores_summary(); ?>
            <?php endif; ?>
        </div>

        <style>
            .device-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            .stat-card {
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .stat-card h3 {
                margin-top: 0;
                color: #666;
                font-size: 14px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .stat-value {
                font-size: 32px;
                font-weight: bold;
                color: #2271b1;
                margin: 10px 0;
            }
            .stat-label {
                color: #666;
                font-size: 13px;
            }
            .device-table {
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
            }
            .device-row {
                display: grid;
                grid-template-columns: 2fr 1fr 1fr 1fr;
                padding: 12px 20px;
                border-bottom: 1px solid #f0f0f0;
                align-items: center;
            }
            .device-row:hover {
                background: #f9f9f9;
            }
            .device-row.header {
                background: #f5f5f5;
                font-weight: 600;
                color: #444;
                border-bottom: 2px solid #ddd;
            }
            .device-row.header:hover {
                background: #f5f5f5;
            }
            .store-summary-card {
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                transition: all 0.2s;
            }
            .store-summary-card:hover {
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                transform: translateY(-2px);
            }
            .store-info h3 {
                margin: 0 0 5px 0;
                color: #2271b1;
            }
            .store-info small {
                color: #666;
            }
            .store-stats {
                display: flex;
                gap: 30px;
            }
            .store-stat {
                text-align: center;
            }
            .store-stat-value {
                font-size: 24px;
                font-weight: bold;
                color: #2271b1;
            }
            .store-stat-label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
            }
            .badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: bold;
            }
            .badge-success { background: #d4edda; color: #155724; }
            .badge-warning { background: #fff3cd; color: #856404; }
            .badge-info { background: #d1ecf1; color: #0c5460; }
        </style>
        <?php
    }

    /**
     * Render summary for all stores
     */
    private static function render_all_stores_summary() {
        global $wpdb;

        $stores = $wpdb->get_results("
            SELECT
                s.id,
                s.name,
                s.company_name,
                s.city,
                COUNT(DISTINCT pd.id) as device_count,
                COUNT(DISTINCT CASE WHEN pd.status = 'active' THEN pd.id END) as active_devices,
                s.trusted_device_fingerprint
            FROM {$wpdb->prefix}ppv_stores s
            LEFT JOIN {$wpdb->prefix}ppv_pos_devices pd ON s.id = pd.store_id
            GROUP BY s.id
            ORDER BY device_count DESC
        ");

        if (empty($stores)) {
            echo '<div class="notice notice-info"><p>Nincsenek boltok az adatbázisban.</p></div>';
            return;
        }

        echo '<h2>Összes Bolt - Eszköz Összesítés</h2>';

        foreach ($stores as $store) {
            $store_name = $store->company_name ?: $store->name;
            $has_trusted = !empty($store->trusted_device_fingerprint);
            ?>
            <div class="store-summary-card">
                <div class="store-info">
                    <h3>
                        <a href="<?php echo admin_url('admin.php?page=punktepass-devices&store_id=' . $store->id); ?>">
                            <?php echo esc_html($store_name); ?>
                        </a>
                    </h3>
                    <?php if ($store->city): ?>
                        <small>📍 <?php echo esc_html($store->city); ?></small>
                    <?php endif; ?>
                </div>
                <div class="store-stats">
                    <div class="store-stat">
                        <div class="store-stat-value"><?php echo intval($store->device_count); ?></div>
                        <div class="store-stat-label">POS Eszközök</div>
                    </div>
                    <div class="store-stat">
                        <div class="store-stat-value"><?php echo intval($store->active_devices); ?></div>
                        <div class="store-stat-label">Aktív</div>
                    </div>
                    <div class="store-stat">
                        <span class="badge <?php echo $has_trusted ? 'badge-success' : 'badge-warning'; ?>">
                            <?php echo $has_trusted ? '✅ Megbízható beállítva' : '⏳ Nincs megbízható'; ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render device details for a specific store
     */
    private static function render_store_devices($store_id) {
        global $wpdb;

        // Get store info
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            echo '<div class="notice notice-error"><p>Bolt nem található!</p></div>';
            return;
        }

        // Get device statistics
        $devices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_pos_devices WHERE store_id = %d ORDER BY created_at DESC",
            $store_id
        ));

        // Get scan statistics from various sources
        $scan_history = $wpdb->get_results($wpdb->prepare("
            SELECT
                DATE(created_at) as scan_date,
                COUNT(*) as scan_count,
                ip_address,
                user_agent
            FROM {$wpdb->prefix}ppv_transactions
            WHERE store_id = %d AND type = 'scan'
            GROUP BY scan_date, ip_address, user_agent
            ORDER BY scan_date DESC
            LIMIT 100
        ", $store_id));

        // Count unique devices from scan history
        $unique_ips = array_unique(array_filter(array_column($scan_history, 'ip_address')));
        $unique_devices_count = count($unique_ips);

        $store_name = $store->company_name ?: $store->name;
        $trusted_fingerprint = $store->trusted_device_fingerprint;

        ?>
        <h2><?php echo esc_html($store_name); ?> - Eszköz Részletek</h2>

        <!-- Stats Cards -->
        <div class="device-stats-grid">
            <div class="stat-card">
                <h3>📱 Regisztrált POS Eszközök</h3>
                <div class="stat-value"><?php echo count($devices); ?></div>
                <div class="stat-label">Korlátlan eszköz engedélyezve</div>
            </div>

            <div class="stat-card">
                <h3>🟢 Aktív Eszközök</h3>
                <div class="stat-value">
                    <?php echo count(array_filter($devices, fn($d) => $d->status === 'active')); ?>
                </div>
                <div class="stat-label">Jelenleg használatban</div>
            </div>

            <div class="stat-card">
                <h3>🌐 Különböző IP Címek</h3>
                <div class="stat-value"><?php echo $unique_devices_count; ?></div>
                <div class="stat-label">Szkennelt eszközök (IP alapján)</div>
            </div>

            <div class="stat-card">
                <h3>🔒 Megbízható Eszköz</h3>
                <div class="stat-value" style="font-size: 20px;">
                    <?php echo $trusted_fingerprint ? '✅ Beállítva' : '⏳ Nincs'; ?>
                </div>
                <?php if ($trusted_fingerprint): ?>
                    <div class="stat-label">
                        FP: <?php echo esc_html(substr($trusted_fingerprint, 0, 12) . '...'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- POS Devices Table -->
        <?php if (!empty($devices)): ?>
            <h3>🖥️ Regisztrált POS Eszközök</h3>
            <div class="device-table">
                <div class="device-row header">
                    <div>Eszköz Név</div>
                    <div>Státusz</div>
                    <div>Létrehozva</div>
                    <div>Utolsó Szinkron</div>
                </div>
                <?php foreach ($devices as $device): ?>
                    <div class="device-row">
                        <div>
                            <strong><?php echo esc_html($device->device_name ?: 'Eszköz #' . $device->id); ?></strong>
                            <br>
                            <small style="color: #666;">
                                Kulcs: <?php echo esc_html(substr($device->device_key, 0, 12) . '...'); ?>
                            </small>
                        </div>
                        <div>
                            <span class="badge <?php echo $device->status === 'active' ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo $device->status === 'active' ? '🟢 Aktív' : '🔴 Inaktív'; ?>
                            </span>
                        </div>
                        <div>
                            <?php echo date('Y-m-d H:i', strtotime($device->created_at)); ?>
                        </div>
                        <div>
                            <?php echo $device->last_sync ? date('Y-m-d H:i', strtotime($device->last_sync)) : '—'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="notice notice-info">
                <p>⚠️ Még nincs regisztrált POS eszköz ehhez a bolthoz.</p>
            </div>
        <?php endif; ?>

        <!-- Scan History by IP/Device -->
        <?php if (!empty($scan_history)): ?>
            <h3 style="margin-top: 30px;">📊 Szkennelési Előzmények (IP/Eszköz alapján)</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 120px;">Dátum</th>
                        <th style="width: 150px;">IP Cím</th>
                        <th>User Agent (Eszköz)</th>
                        <th style="width: 100px; text-align: center;">Szkennelések</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scan_history as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row->scan_date); ?></td>
                            <td>
                                <code><?php echo esc_html($row->ip_address ?: 'N/A'); ?></code>
                            </td>
                            <td>
                                <small style="color: #666;">
                                    <?php
                                    $ua = $row->user_agent ?: 'N/A';
                                    echo esc_html(strlen($ua) > 80 ? substr($ua, 0, 80) . '...' : $ua);
                                    ?>
                                </small>
                            </td>
                            <td style="text-align: center;">
                                <strong style="color: #2271b1;"><?php echo intval($row->scan_count); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="notice notice-info" style="margin-top: 30px;">
                <p>⚠️ Még nincs szkennelési előzmény ehhez a bolthoz.</p>
            </div>
        <?php endif; ?>
        <?php
    }
}

// Initialize
PPV_Admin_Devices::init();
