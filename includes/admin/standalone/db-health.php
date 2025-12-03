<?php
/**
 * PunktePass Standalone Admin - Database Health Monitor
 * Route: /admin/db-health
 *
 * Monitors database size, table sizes, and provides scaling recommendations
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_DBHealth {

    // Hostinger Cloud Standard limits (200 GB disk, 3 GB RAM, 2 CPU)
    // MySQL database limits (conservative for optimal performance)
    const DB_WARNING_MB = 2000;    // Start warning at 2 GB
    const DB_CRITICAL_MB = 5000;   // Critical at 5 GB
    const DB_MAX_DISPLAY_MB = 10000; // Max display 10 GB
    const ROWS_WARNING = 5000000;  // 5M rows warning

    /**
     * Render DB health page
     */
    public static function render() {
        global $wpdb;

        // Get database name
        $db_name = DB_NAME;

        // Get total database size
        $db_size = $wpdb->get_row("
            SELECT
                SUM(data_length + index_length) as total_bytes,
                SUM(data_length) as data_bytes,
                SUM(index_length) as index_bytes
            FROM information_schema.tables
            WHERE table_schema = '{$db_name}'
        ");

        // Get PPV tables specifically
        $ppv_tables = $wpdb->get_results("
            SELECT
                table_name,
                table_rows,
                data_length,
                index_length,
                (data_length + index_length) as total_size
            FROM information_schema.tables
            WHERE table_schema = '{$db_name}'
            AND table_name LIKE '{$wpdb->prefix}ppv_%'
            ORDER BY total_size DESC
        ");

        // Get store count
        $store_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores");

        // Get log stats (accurate COUNT for pos_log - most important table)
        $log_stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total_rows,
                MIN(created_at) as oldest_log,
                MAX(created_at) as newest_log,
                COUNT(CASE WHEN type = 'error' THEN 1 END) as error_count,
                COUNT(CASE WHEN type = 'qr_scan' THEN 1 END) as scan_count
            FROM {$wpdb->prefix}ppv_pos_log
        ");

        // Get accurate row counts for key tables (info_schema table_rows is only an estimate)
        $accurate_counts = [];
        $key_tables = ['ppv_pos_log', 'ppv_einloesungen', 'ppv_transactions', 'ppv_user_devices'];
        foreach ($key_tables as $tbl) {
            $full_name = $wpdb->prefix . $tbl;
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$full_name}");
            $accurate_counts[$full_name] = $count !== null ? (int)$count : null;
        }

        // Get archived tables
        $archive_tables = $wpdb->get_results("
            SELECT table_name, table_rows, (data_length + index_length) as total_size
            FROM information_schema.tables
            WHERE table_schema = '{$db_name}'
            AND table_name LIKE '{$wpdb->prefix}ppv_pos_log_20%'
            ORDER BY table_name DESC
        ");

        // Calculate health status
        $total_mb = ($db_size->total_bytes ?? 0) / 1024 / 1024;
        $ppv_mb = array_sum(array_column($ppv_tables, 'total_size')) / 1024 / 1024;

        $health_status = 'good';
        $health_message = 'Az adatbázis egészséges';

        if ($total_mb >= self::DB_CRITICAL_MB) {
            $health_status = 'critical';
            $health_message = 'KRITIKUS: Az adatbázis majdnem megtelt! Azonnal szükséges beavatkozás.';
        } elseif ($total_mb >= self::DB_WARNING_MB) {
            $health_status = 'warning';
            $health_message = 'FIGYELEM: Az adatbázis mérete közelít a limithez.';
        }

        // Scaling recommendation - conservative estimate based on current usage
        $max_stores_estimate = 500; // Default for Cloud Standard
        if ($store_count > 0 && $ppv_mb > 0) {
            $mb_per_store = $ppv_mb / $store_count;
            // Use 1 GB as practical limit for estimation (not the warning limit)
            $practical_limit_mb = 1000;
            $available_mb = max(0, $practical_limit_mb - $total_mb);
            $max_stores_estimate = $store_count + floor($available_mb / max($mb_per_store, 0.5));
            // Cap at reasonable maximum based on hosting tier
            $max_stores_estimate = min($max_stores_estimate, 500);
        }

        self::render_html($db_size, $ppv_tables, $store_count, $log_stats, $archive_tables, $health_status, $health_message, $total_mb, $ppv_mb, $max_stores_estimate, $accurate_counts);
    }

    /**
     * Render HTML
     */
    private static function render_html($db_size, $ppv_tables, $store_count, $log_stats, $archive_tables, $health_status, $health_message, $total_mb, $ppv_mb, $max_stores_estimate, $accurate_counts) {
        $status_colors = [
            'good' => '#4ade80',
            'warning' => '#fbbf24',
            'critical' => '#f87171'
        ];
        $status_icons = [
            'good' => 'ri-checkbox-circle-fill',
            'warning' => 'ri-error-warning-fill',
            'critical' => 'ri-alarm-warning-fill'
        ];
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>DB Health - PunktePass Admin</title>
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
                .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

                .health-banner {
                    padding: 20px;
                    border-radius: 12px;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                .health-banner.good { background: rgba(74, 222, 128, 0.15); border: 1px solid #4ade80; }
                .health-banner.warning { background: rgba(251, 191, 36, 0.15); border: 1px solid #fbbf24; }
                .health-banner.critical { background: rgba(248, 113, 113, 0.15); border: 1px solid #f87171; animation: pulse 2s infinite; }
                .health-banner i { font-size: 32px; }
                .health-banner .message { font-size: 16px; font-weight: 600; }

                @keyframes pulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.7; }
                }

                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                    gap: 15px;
                    margin-bottom: 25px;
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
                .stat-card .number.yellow { color: #fbbf24; }
                .stat-card .number.red { color: #f87171; }
                .stat-card .label {
                    font-size: 12px;
                    color: #888;
                    margin-top: 5px;
                }

                .progress-bar-container {
                    background: #16213e;
                    padding: 20px;
                    border-radius: 12px;
                    margin-bottom: 25px;
                }
                .progress-bar-container h3 {
                    font-size: 14px;
                    color: #888;
                    margin-bottom: 15px;
                }
                .progress-bar {
                    background: #0f3460;
                    height: 30px;
                    border-radius: 15px;
                    overflow: hidden;
                    position: relative;
                }
                .progress-fill {
                    height: 100%;
                    border-radius: 15px;
                    transition: width 0.5s ease;
                }
                .progress-fill.good { background: linear-gradient(90deg, #4ade80, #22c55e); }
                .progress-fill.warning { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
                .progress-fill.critical { background: linear-gradient(90deg, #f87171, #ef4444); }
                .progress-text {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    font-weight: 600;
                    font-size: 13px;
                    text-shadow: 0 1px 2px rgba(0,0,0,0.5);
                }
                .progress-labels {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 8px;
                    font-size: 11px;
                    color: #666;
                }

                .section-title {
                    font-size: 16px;
                    color: #00d9ff;
                    margin: 25px 0 15px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    background: #16213e;
                    border-radius: 12px;
                    overflow: hidden;
                    margin-bottom: 20px;
                }
                th, td {
                    padding: 12px 15px;
                    text-align: left;
                    border-bottom: 1px solid #0f3460;
                    font-size: 13px;
                }
                th { background: #0f3460; color: #00d9ff; font-weight: 600; }
                tr:hover { background: #1f2b4d; }
                .size-bar {
                    display: inline-block;
                    height: 8px;
                    background: #00d9ff;
                    border-radius: 4px;
                    min-width: 5px;
                }

                .recommendation-box {
                    background: #16213e;
                    border-radius: 12px;
                    padding: 20px;
                    margin-top: 20px;
                }
                .recommendation-box h3 {
                    color: #00d9ff;
                    margin-bottom: 15px;
                    font-size: 16px;
                }
                .recommendation-item {
                    display: flex;
                    align-items: flex-start;
                    gap: 10px;
                    margin-bottom: 12px;
                    font-size: 14px;
                }
                .recommendation-item i {
                    color: #4ade80;
                    margin-top: 2px;
                }

                .refresh-btn {
                    background: #0f3460;
                    color: #00d9ff;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 14px;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                }
                .refresh-btn:hover { background: #1a4a7a; }
            </style>
        </head>
        <body>
            <div class="admin-header">
                <h1><i class="ri-database-2-line"></i> Database Health Monitor</h1>
                <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza</a>
            </div>

            <div class="container">
                <!-- Health Status Banner -->
                <div class="health-banner <?php echo $health_status; ?>">
                    <i class="<?php echo $status_icons[$health_status]; ?>" style="color: <?php echo $status_colors[$health_status]; ?>"></i>
                    <div>
                        <div class="message" style="color: <?php echo $status_colors[$health_status]; ?>"><?php echo $health_message; ?></div>
                        <div style="font-size: 13px; color: #888; margin-top: 5px;">
                            Utolsó ellenőrzés: <?php echo date('Y-m-d H:i:s'); ?>
                        </div>
                    </div>
                    <button class="refresh-btn" onclick="location.reload()" style="margin-left: auto;">
                        <i class="ri-refresh-line"></i> Frissítés
                    </button>
                </div>

                <!-- Main Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number <?php echo $total_mb >= self::DB_CRITICAL_MB ? 'red' : ($total_mb >= self::DB_WARNING_MB ? 'yellow' : 'green'); ?>">
                            <?php echo number_format($total_mb, 1); ?> MB
                        </div>
                        <div class="label">Teljes DB méret</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo number_format($ppv_mb, 1); ?> MB</div>
                        <div class="label">PunktePass táblák</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo number_format($store_count); ?></div>
                        <div class="label">Aktív boltok</div>
                    </div>
                    <div class="stat-card">
                        <div class="number <?php echo $max_stores_estimate < 50 ? 'red' : ($max_stores_estimate < 100 ? 'yellow' : 'green'); ?>">
                            ~<?php echo number_format($max_stores_estimate); ?>
                        </div>
                        <div class="label">Becsült max bolt</div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress-bar-container">
                    <h3>Adatbázis kapacitás (Hostinger Cloud Standard: ~10 GB ajánlott max)</h3>
                    <div class="progress-bar">
                        <?php
                        $percent = min(100, ($total_mb / self::DB_MAX_DISPLAY_MB) * 100);
                        $display_max = $total_mb >= 1000 ? number_format(self::DB_MAX_DISPLAY_MB / 1000, 0) . ' GB' : self::DB_MAX_DISPLAY_MB . ' MB';
                        $display_current = $total_mb >= 1000 ? number_format($total_mb / 1000, 2) . ' GB' : number_format($total_mb, 1) . ' MB';
                        ?>
                        <div class="progress-fill <?php echo $health_status; ?>" style="width: <?php echo $percent; ?>%"></div>
                        <span class="progress-text"><?php echo $display_current; ?> / <?php echo $display_max; ?></span>
                    </div>
                    <div class="progress-labels">
                        <span>0</span>
                        <span style="color: #fbbf24;">2 GB (figyelmeztetés)</span>
                        <span style="color: #f87171;">5 GB (kritikus)</span>
                        <span>10 GB</span>
                    </div>
                </div>

                <!-- Log Stats -->
                <h2 class="section-title"><i class="ri-file-list-3-line"></i> Log statisztikák</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo number_format($log_stats->total_rows ?? 0); ?></div>
                        <div class="label">Összes log rekord</div>
                    </div>
                    <div class="stat-card">
                        <div class="number green"><?php echo number_format($log_stats->scan_count ?? 0); ?></div>
                        <div class="label">Sikeres scanek</div>
                    </div>
                    <div class="stat-card">
                        <div class="number yellow"><?php echo number_format($log_stats->error_count ?? 0); ?></div>
                        <div class="label">Hibák (7 napig)</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $log_stats->oldest_log ? date('m.d', strtotime($log_stats->oldest_log)) : '-'; ?></div>
                        <div class="label">Legrégebbi log</div>
                    </div>
                </div>

                <!-- PPV Tables -->
                <h2 class="section-title"><i class="ri-table-line"></i> PunktePass táblák mérete</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Tábla</th>
                            <th>Rekordok</th>
                            <th>Adat</th>
                            <th>Index</th>
                            <th>Összesen</th>
                            <th style="width: 200px;">Méret</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $max_size = max(array_column($ppv_tables, 'total_size') ?: [1]);
                        foreach ($ppv_tables as $table):
                            $table_name = str_replace($GLOBALS['wpdb']->prefix, '', $table->table_name);
                            $size_mb = $table->total_size / 1024 / 1024;
                            $bar_width = ($table->total_size / $max_size) * 100;
                            // Use accurate count if available, otherwise use estimate
                            $row_count = isset($accurate_counts[$table->table_name])
                                ? $accurate_counts[$table->table_name]
                                : $table->table_rows;
                            $is_accurate = isset($accurate_counts[$table->table_name]);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($table_name); ?></strong></td>
                            <td>
                                <?php echo number_format($row_count); ?>
                                <?php if (!$is_accurate): ?>
                                    <span style="color: #666; font-size: 10px;" title="Becsült érték">~</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($table->data_length / 1024, 1); ?> KB</td>
                            <td><?php echo number_format($table->index_length / 1024, 1); ?> KB</td>
                            <td><strong><?php echo number_format($size_mb, 2); ?> MB</strong></td>
                            <td>
                                <div class="size-bar" style="width: <?php echo max(5, $bar_width); ?>%;"></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (!empty($archive_tables)): ?>
                <!-- Archive Tables -->
                <h2 class="section-title"><i class="ri-archive-line"></i> Archivált log táblák</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Archív tábla</th>
                            <th>Rekordok</th>
                            <th>Méret</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archive_tables as $table):
                            $table_name = str_replace($GLOBALS['wpdb']->prefix, '', $table->table_name);
                        ?>
                        <tr>
                            <td><i class="ri-folder-zip-line" style="color: #888;"></i> <?php echo esc_html($table_name); ?></td>
                            <td><?php echo number_format($table->table_rows); ?></td>
                            <td><?php echo number_format($table->total_size / 1024 / 1024, 2); ?> MB</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Recommendations -->
                <div class="recommendation-box">
                    <h3><i class="ri-lightbulb-line"></i> Ajánlások</h3>

                    <?php if ($health_status === 'good'): ?>
                    <div class="recommendation-item">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Az adatbázis mérete rendben van. A Cloud Standard csomagon ~<?php echo number_format($max_stores_estimate); ?> boltig skálázható.</span>
                    </div>
                    <?php endif; ?>

                    <?php if ($health_status === 'warning'): ?>
                    <div class="recommendation-item">
                        <i class="ri-error-warning-fill" style="color: #fbbf24;"></i>
                        <span>Az adatbázis kezd nagyobb lenni. Fontold meg a régi logok archiválását vagy Cloud Professional csomagra váltást.</span>
                    </div>
                    <?php endif; ?>

                    <?php if ($health_status === 'critical'): ?>
                    <div class="recommendation-item">
                        <i class="ri-alarm-warning-fill" style="color: #f87171;"></i>
                        <span><strong>FONTOS:</strong> Az adatbázis nagyon nagy. Szükséges a logok törlése/archiválása vagy Cloud Professional/VPS csomagra váltás.</span>
                    </div>
                    <?php endif; ?>

                    <div class="recommendation-item">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Auto-cleanup aktív: Error logok 7 nap után automatikusan törlődnek.</span>
                    </div>
                    <div class="recommendation-item">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Archiválás aktív: 3 hónapnál régebbi logok archív táblákba kerülnek.</span>
                    </div>
                    <div class="recommendation-item">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span>Log deduplikálás aktív: "Ma már beolvasva" hibák csak 1x/nap logolódnak.</span>
                    </div>

                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #0f3460;">
                        <strong style="color: #888;">Skálázási útmutató (Cloud Standard: 200 GB, 3 GB RAM, 2 CPU):</strong>
                        <table style="margin-top: 10px; font-size: 13px;">
                            <tr><td style="padding: 5px 15px 5px 0;">1-500 bolt</td><td style="color: #4ade80;">Cloud Standard (jelenlegi)</td></tr>
                            <tr><td style="padding: 5px 15px 5px 0;">500-1500 bolt</td><td style="color: #fbbf24;">Cloud Professional / VPS</td></tr>
                            <tr><td style="padding: 5px 15px 5px 0;">1500+ bolt</td><td style="color: #f87171;">Dedikált szerver / AWS</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
