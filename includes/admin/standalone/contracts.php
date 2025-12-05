<?php
/**
 * PunktePass Standalone Admin - Szerződések (Contracts)
 * Route: /admin/contracts
 * Shows contracts created via /invoice page with sales user tracking
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Contracts {

    /**
     * Render contracts page
     */
    public static function render() {
        global $wpdb;

        $table = $wpdb->prefix . 'ppv_contracts';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

        if (!$table_exists) {
            self::render_no_table();
            return;
        }

        // Get filter parameters
        $sales_filter = isset($_GET['sales_user']) ? intval($_GET['sales_user']) : 0;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        // Build query
        $where_clauses = [];
        $params = [];

        if ($sales_filter > 0) {
            $where_clauses[] = "c.sales_user_id = %d";
            $params[] = $sales_filter;
        }

        if (!empty($status_filter)) {
            $where_clauses[] = "c.status = %s";
            $params[] = $status_filter;
        }

        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        // Fetch contracts
        $query = "
            SELECT c.*
            FROM $table c
            $where_sql
            ORDER BY c.created_at DESC
            LIMIT 200
        ";

        if (!empty($params)) {
            $contracts = $wpdb->get_results($wpdb->prepare($query, ...$params));
        } else {
            $contracts = $wpdb->get_results($query);
        }

        // Get statistics per sales user
        $sales_stats = $wpdb->get_results("
            SELECT
                sales_user_id,
                sales_user_email,
                sales_user_type,
                COUNT(*) as contract_count,
                MAX(created_at) as last_contract
            FROM $table
            GROUP BY sales_user_id, sales_user_email, sales_user_type
            ORDER BY contract_count DESC
        ");

        // Get total counts
        $total_contracts = intval($wpdb->get_var("SELECT COUNT(*) FROM $table"));
        $signed_contracts = intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'signed'"));
        $pending_contracts = intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'"));

        self::render_html($contracts, $sales_stats, $total_contracts, $signed_contracts, $pending_contracts, $sales_filter, $status_filter);
    }

    /**
     * Render page when table doesn't exist
     */
    private static function render_no_table() {
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Szerződések - PunktePass Admin</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #1a1a2e;
                    color: #e0e0e0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .message-box {
                    background: #16213e;
                    padding: 40px;
                    border-radius: 12px;
                    text-align: center;
                    max-width: 500px;
                }
                .message-box i { font-size: 48px; color: #f59e0b; margin-bottom: 15px; }
                .message-box h2 { color: #00d9ff; margin-bottom: 15px; }
                .message-box p { color: #888; margin-bottom: 20px; }
                .message-box a { color: #00d9ff; }
            </style>
        </head>
        <body>
            <div class="message-box">
                <i class="ri-file-warning-line"></i>
                <h2>Szerződések tábla nem található</h2>
                <p>A szerződések tábla még nem lett létrehozva.
                Kérjük, látogassa meg a <a href="<?php echo home_url('/invoice'); ?>">/invoice</a> oldalt,
                hogy automatikusan létrejöjjön a tábla.</p>
                <a href="/admin/dashboard">Vissza a Dashboardra</a>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Render the contracts page HTML
     */
    private static function render_html($contracts, $sales_stats, $total, $signed, $pending, $sales_filter, $status_filter) {
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Szerződések - PunktePass Admin</title>
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

                /* Stats Cards */
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
                .stat-card .stat-value {
                    font-size: 2.5rem;
                    font-weight: 700;
                    color: #00d9ff;
                }
                .stat-card .stat-label {
                    color: #888;
                    font-size: 0.85rem;
                    margin-top: 5px;
                }
                .stat-card.success .stat-value { color: #10b981; }
                .stat-card.warning .stat-value { color: #f59e0b; }
                .stat-card.purple .stat-value { color: #a855f7; }

                /* Sales Stats Section */
                .sales-section {
                    background: #16213e;
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 25px;
                }
                .sales-section h3 {
                    color: #00d9ff;
                    margin-bottom: 15px;
                    font-size: 16px;
                }

                /* Tables */
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

                /* Badges */
                .badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 11px;
                    font-weight: 600;
                }
                .badge-handler { background: #084298; color: #cfe2ff; }
                .badge-user { background: #0f5132; color: #d1e7dd; }
                .badge-admin { background: #664d03; color: #fff3cd; }
                .badge-signed { background: #0f5132; color: #d1e7dd; }
                .badge-pending { background: #664d03; color: #fff3cd; }

                /* Filter Form */
                .filter-section {
                    background: #16213e;
                    border-radius: 12px;
                    padding: 15px 20px;
                    margin-bottom: 20px;
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                    align-items: center;
                }
                .filter-section select {
                    padding: 8px 12px;
                    background: #0f3460;
                    border: 1px solid #1f4068;
                    border-radius: 6px;
                    color: #e0e0e0;
                    font-size: 13px;
                }
                .filter-section button {
                    padding: 8px 16px;
                    background: #00d9ff;
                    color: #000;
                    border: none;
                    border-radius: 6px;
                    font-weight: 600;
                    cursor: pointer;
                }
                .filter-section button:hover { background: #00b8d9; }
                .filter-section a.clear {
                    color: #888;
                    text-decoration: none;
                    font-size: 13px;
                    margin-left: 10px;
                }
                .filter-section a.clear:hover { color: #00d9ff; }

                /* Contracts List */
                .contracts-section {
                    background: #16213e;
                    border-radius: 12px;
                    overflow: hidden;
                }
                .contracts-section h3 {
                    color: #00d9ff;
                    padding: 15px 20px;
                    margin: 0;
                    background: #0f3460;
                    font-size: 16px;
                }
                .company-name { font-weight: 600; color: #fff; }
                .contact-info { font-size: 12px; color: #888; }
                .contact-info a { color: #00d9ff; }
                .date-info { font-size: 12px; color: #888; }
                .sales-email { font-weight: 600; color: #00d9ff; }

                /* Empty State */
                .empty-state {
                    text-align: center;
                    padding: 60px 20px;
                    color: #888;
                }
                .empty-state i { font-size: 48px; margin-bottom: 15px; display: block; color: #00d9ff; }

                /* Links */
                a { color: #00d9ff; text-decoration: none; }
                a:hover { text-decoration: underline; }

                .filter-btn {
                    color: #00d9ff;
                    font-size: 12px;
                }
            </style>
        </head>
        <body>
            <header class="admin-header">
                <h1><i class="ri-file-text-line"></i> Szerződések</h1>
                <a href="/admin/dashboard" class="back-link"><i class="ri-arrow-left-line"></i> Vissza a Dashboardra</a>
            </header>

            <div class="container">
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $total; ?></div>
                        <div class="stat-label">Összes szerződés</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?php echo $signed; ?></div>
                        <div class="stat-label">Aláírt</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value"><?php echo $pending; ?></div>
                        <div class="stat-label">Függőben</div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-value"><?php echo count($sales_stats); ?></div>
                        <div class="stat-label">Sales munkatárs</div>
                    </div>
                </div>

                <!-- Sales Statistics -->
                <?php if (!empty($sales_stats)): ?>
                <div class="sales-section">
                    <h3><i class="ri-user-star-line"></i> Szerződések munkatársanként</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Munkatárs</th>
                                <th>Típus</th>
                                <th>Szerződések száma</th>
                                <th>Utolsó szerződés</th>
                                <th>Művelet</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_stats as $stat): ?>
                            <tr>
                                <td class="sales-email"><?php echo esc_html($stat->sales_user_email ?: 'Ismeretlen'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo esc_attr($stat->sales_user_type ?: 'user'); ?>">
                                        <?php echo esc_html($stat->sales_user_type ?: 'user'); ?>
                                    </span>
                                </td>
                                <td><strong style="color: #00d9ff; font-size: 18px;"><?php echo intval($stat->contract_count); ?></strong></td>
                                <td class="date-info"><?php echo date('Y.m.d H:i', strtotime($stat->last_contract)); ?></td>
                                <td>
                                    <a href="?sales_user=<?php echo intval($stat->sales_user_id); ?>" class="filter-btn">
                                        <i class="ri-filter-line"></i> Szűrés
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <form class="filter-section" method="get">
                    <select name="sales_user">
                        <option value="">Összes munkatárs</option>
                        <?php foreach ($sales_stats as $stat): ?>
                        <option value="<?php echo intval($stat->sales_user_id); ?>"
                                <?php echo $sales_filter == $stat->sales_user_id ? 'selected' : ''; ?>>
                            <?php echo esc_html($stat->sales_user_email); ?> (<?php echo intval($stat->contract_count); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <option value="">Összes státusz</option>
                        <option value="signed" <?php echo $status_filter === 'signed' ? 'selected' : ''; ?>>Aláírt</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Függőben</option>
                    </select>
                    <button type="submit"><i class="ri-filter-line"></i> Szűrés</button>
                    <?php if ($sales_filter || $status_filter): ?>
                    <a href="/admin/contracts" class="clear">Szűrők törlése</a>
                    <?php endif; ?>
                </form>

                <!-- Contracts List -->
                <div class="contracts-section">
                    <h3><i class="ri-list-check"></i> Összes szerződés (<?php echo count($contracts); ?>)</h3>

                    <?php if (empty($contracts)): ?>
                    <div class="empty-state">
                        <i class="ri-file-text-line"></i>
                        <p>Nincs találat</p>
                    </div>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kereskedő</th>
                                <th>Kapcsolat</th>
                                <th>Város</th>
                                <th>IMEI</th>
                                <th>Sales munkatárs</th>
                                <th>Státusz</th>
                                <th>Dátum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contracts as $contract): ?>
                            <tr>
                                <td>#<?php echo intval($contract->id); ?></td>
                                <td class="company-name"><?php echo esc_html($contract->haendler_name); ?></td>
                                <td class="contact-info">
                                    <?php echo esc_html($contract->ansprechpartner); ?><br>
                                    <a href="mailto:<?php echo esc_attr($contract->haendler_email); ?>">
                                        <?php echo esc_html($contract->haendler_email); ?>
                                    </a><br>
                                    <?php echo esc_html($contract->haendler_telefon); ?>
                                </td>
                                <td><?php echo esc_html($contract->haendler_plz . ' ' . $contract->haendler_ort); ?></td>
                                <td><?php echo esc_html($contract->imei ?: '-'); ?></td>
                                <td>
                                    <span class="sales-email"><?php echo esc_html($contract->sales_user_email ?: 'Ismeretlen'); ?></span>
                                    <?php if ($contract->sales_user_type): ?>
                                    <br>
                                    <span class="badge badge-<?php echo esc_attr($contract->sales_user_type); ?>">
                                        <?php echo esc_html($contract->sales_user_type); ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo esc_attr($contract->status); ?>">
                                        <?php echo $contract->status === 'signed' ? 'Aláírt' : ($contract->status === 'pending' ? 'Függőben' : ucfirst($contract->status)); ?>
                                    </span>
                                </td>
                                <td class="date-info">
                                    <?php echo date('Y.m.d', strtotime($contract->created_at)); ?><br>
                                    <?php echo date('H:i', strtotime($contract->created_at)); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
