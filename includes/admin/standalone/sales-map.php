<?php
/**
 * PunktePass Standalone Admin - Sales Map
 * Route: /admin/sales-map
 * Interactive Google Maps for sales tracking with business markers
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Sales_Map {

    /**
     * Render sales map page
     */
    public static function render() {
        global $wpdb;

        // Ensure table exists
        self::maybe_create_table();

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['add_marker'])) {
                self::handle_add_marker();
            } elseif (isset($_POST['update_marker'])) {
                self::handle_update_marker();
            } elseif (isset($_POST['delete_marker'])) {
                self::handle_delete_marker();
            }
        }

        // Get all markers
        $markers = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}ppv_sales_markers
            ORDER BY created_at DESC
        ");

        // Stats
        $stats = [
            'total' => count($markers),
            'contacted' => 0,
            'visited' => 0,
            'interested' => 0,
            'customer' => 0,
            'not_interested' => 0
        ];

        foreach ($markers as $marker) {
            if (isset($stats[$marker->status])) {
                $stats[$marker->status]++;
            }
        }

        self::render_html($markers, $stats);
    }

    /**
     * Create markers table
     */
    private static function maybe_create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_sales_markers';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            business_name varchar(255) NOT NULL,
            address varchar(500) NOT NULL,
            city varchar(100) DEFAULT '',
            lat decimal(10,8) NOT NULL,
            lng decimal(11,8) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'contacted',
            contact_name varchar(255) DEFAULT '',
            contact_email varchar(255) DEFAULT '',
            contact_phone varchar(100) DEFAULT '',
            notes text DEFAULT '',
            visited_at datetime DEFAULT NULL,
            next_followup datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY city (city),
            KEY lat_lng (lat, lng)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Handle add marker
     */
    private static function handle_add_marker() {
        global $wpdb;

        $business_name = sanitize_text_field($_POST['business_name'] ?? '');
        $address = sanitize_text_field($_POST['address'] ?? '');
        $city = sanitize_text_field($_POST['city'] ?? '');
        $lat = floatval($_POST['lat'] ?? 0);
        $lng = floatval($_POST['lng'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? 'contacted');
        $contact_name = sanitize_text_field($_POST['contact_name'] ?? '');
        $contact_email = sanitize_email($_POST['contact_email'] ?? '');
        $contact_phone = sanitize_text_field($_POST['contact_phone'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        if (empty($business_name) || $lat == 0 || $lng == 0) {
            wp_redirect("/admin/sales-map?error=missing_data");
            exit;
        }

        $wpdb->insert(
            $wpdb->prefix . 'ppv_sales_markers',
            [
                'business_name' => $business_name,
                'address' => $address,
                'city' => $city,
                'lat' => $lat,
                'lng' => $lng,
                'status' => $status,
                'contact_name' => $contact_name,
                'contact_email' => $contact_email,
                'contact_phone' => $contact_phone,
                'notes' => $notes,
                'visited_at' => $status === 'visited' ? current_time('mysql') : null
            ],
            ['%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        ppv_log("📍 [Sales Map] Új marker: {$business_name} ({$status})");
        wp_redirect("/admin/sales-map?success=added");
        exit;
    }

    /**
     * Handle update marker
     */
    private static function handle_update_marker() {
        global $wpdb;

        $id = intval($_POST['marker_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $next_followup = sanitize_text_field($_POST['next_followup'] ?? '');

        if ($id <= 0) {
            wp_redirect("/admin/sales-map?error=invalid_id");
            exit;
        }

        $update_data = [
            'status' => $status,
            'notes' => $notes,
            'next_followup' => !empty($next_followup) ? $next_followup : null
        ];

        if ($status === 'visited') {
            $update_data['visited_at'] = current_time('mysql');
        }

        $wpdb->update(
            $wpdb->prefix . 'ppv_sales_markers',
            $update_data,
            ['id' => $id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        ppv_log("📍 [Sales Map] Marker frissítve: #{$id} ({$status})");
        wp_redirect("/admin/sales-map?success=updated");
        exit;
    }

    /**
     * Handle delete marker
     */
    private static function handle_delete_marker() {
        global $wpdb;

        $id = intval($_POST['marker_id'] ?? 0);

        if ($id > 0) {
            $wpdb->delete($wpdb->prefix . 'ppv_sales_markers', ['id' => $id], ['%d']);
            ppv_log("🗑️ [Sales Map] Marker törölve: #{$id}");
        }

        wp_redirect("/admin/sales-map?success=deleted");
        exit;
    }

    /**
     * Render HTML
     */
    private static function render_html($markers, $stats) {
        $success = isset($_GET['success']) ? $_GET['success'] : '';
        $error = isset($_GET['error']) ? $_GET['error'] : '';

        // Convert markers to JSON for JavaScript
        $markers_json = [];
        foreach ($markers as $m) {
            $markers_json[] = [
                'id' => intval($m->id),
                'name' => $m->business_name,
                'address' => $m->address,
                'city' => $m->city,
                'lat' => floatval($m->lat),
                'lng' => floatval($m->lng),
                'status' => $m->status,
                'contact_name' => $m->contact_name,
                'contact_email' => $m->contact_email,
                'contact_phone' => $m->contact_phone,
                'notes' => $m->notes,
                'visited_at' => $m->visited_at,
                'next_followup' => $m->next_followup,
                'created_at' => $m->created_at
            ];
        }

        // Status colors and labels
        $status_config = [
            'contacted' => ['color' => '#3b82f6', 'icon' => '📧', 'label' => 'Kontaktiert'],
            'visited' => ['color' => '#8b5cf6', 'icon' => '👣', 'label' => 'Besucht'],
            'interested' => ['color' => '#22c55e', 'icon' => '👍', 'label' => 'Interessiert'],
            'customer' => ['color' => '#f59e0b', 'icon' => '⭐', 'label' => 'Kunde'],
            'not_interested' => ['color' => '#ef4444', 'icon' => '👎', 'label' => 'Kein Interesse'],
            'followup' => ['color' => '#06b6d4', 'icon' => '📅', 'label' => 'Follow-up']
        ];

        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Sales Map - PunktePass Admin</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #1a1a2e;
                    color: #e0e0e0;
                    height: 100vh;
                    display: flex;
                    flex-direction: column;
                }
                .admin-header {
                    background: linear-gradient(135deg, #16213e 0%, #0f3460 100%);
                    padding: 15px 25px;
                    border-bottom: 1px solid #0f3460;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 15px;
                    flex-shrink: 0;
                }
                .admin-header h1 {
                    font-size: 20px;
                    color: #00d9ff;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .header-right { display: flex; align-items: center; gap: 20px; }
                .back-link {
                    color: #888;
                    text-decoration: none;
                    font-size: 14px;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }
                .back-link:hover { color: #00d9ff; }

                .stats-bar {
                    display: flex;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .stat-badge {
                    padding: 6px 14px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }

                .main-container {
                    display: flex;
                    flex: 1;
                    overflow: hidden;
                }

                /* Sidebar */
                .sidebar {
                    width: 380px;
                    background: #16213e;
                    border-right: 1px solid #0f3460;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                }
                .sidebar-header {
                    padding: 15px 20px;
                    background: #0f3460;
                    border-bottom: 1px solid #1f2b4d;
                }
                .sidebar-header h3 {
                    font-size: 14px;
                    color: #00d9ff;
                    margin-bottom: 10px;
                }
                .search-box {
                    position: relative;
                }
                .search-box input {
                    width: 100%;
                    padding: 10px 12px 10px 36px;
                    background: #0a1628;
                    border: 1px solid #1f2b4d;
                    border-radius: 8px;
                    color: #fff;
                    font-size: 13px;
                }
                .search-box input:focus {
                    outline: none;
                    border-color: #00d9ff;
                }
                .search-box i {
                    position: absolute;
                    left: 12px;
                    top: 50%;
                    transform: translateY(-50%);
                    color: #666;
                }
                .search-results {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: #0a1628;
                    border: 1px solid #1f2b4d;
                    border-radius: 0 0 8px 8px;
                    max-height: 300px;
                    overflow-y: auto;
                    z-index: 100;
                    display: none;
                }
                .search-results.show { display: block; }
                .search-result-item {
                    padding: 12px 15px;
                    border-bottom: 1px solid #1f2b4d;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .search-result-item:hover { background: #16213e; }
                .search-result-item .name { font-weight: 600; color: #fff; }
                .search-result-item .address { font-size: 12px; color: #888; margin-top: 3px; }

                .filter-tabs {
                    display: flex;
                    gap: 6px;
                    padding: 12px 20px;
                    background: #0a1628;
                    flex-wrap: wrap;
                }
                .filter-tab {
                    padding: 6px 12px;
                    background: #16213e;
                    border: 1px solid #1f2b4d;
                    border-radius: 6px;
                    font-size: 11px;
                    color: #888;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .filter-tab:hover { border-color: #00d9ff; color: #fff; }
                .filter-tab.active { background: #00d9ff; color: #000; border-color: #00d9ff; }

                .marker-list {
                    flex: 1;
                    overflow-y: auto;
                    padding: 10px;
                }
                .marker-item {
                    background: #0a1628;
                    border: 1px solid #1f2b4d;
                    border-radius: 10px;
                    padding: 12px 15px;
                    margin-bottom: 10px;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .marker-item:hover {
                    border-color: #00d9ff;
                    transform: translateX(3px);
                }
                .marker-item.active {
                    border-color: #00d9ff;
                    background: #16213e;
                }
                .marker-item .header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 6px;
                }
                .marker-item .name {
                    font-weight: 600;
                    color: #fff;
                    font-size: 14px;
                }
                .marker-item .status-badge {
                    padding: 3px 8px;
                    border-radius: 12px;
                    font-size: 10px;
                    font-weight: 600;
                }
                .marker-item .address {
                    font-size: 12px;
                    color: #888;
                }
                .marker-item .meta {
                    display: flex;
                    gap: 10px;
                    margin-top: 8px;
                    font-size: 11px;
                    color: #666;
                }

                /* Map */
                .map-container {
                    flex: 1;
                    position: relative;
                }
                #map {
                    width: 100%;
                    height: 100%;
                }

                /* Info Panel */
                .info-panel {
                    position: absolute;
                    bottom: 20px;
                    left: 20px;
                    right: 20px;
                    background: #16213e;
                    border: 1px solid #0f3460;
                    border-radius: 12px;
                    padding: 20px;
                    display: none;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                    max-height: 300px;
                    overflow-y: auto;
                }
                .info-panel.show { display: block; }
                .info-panel .close-btn {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    background: none;
                    border: none;
                    color: #888;
                    font-size: 20px;
                    cursor: pointer;
                }
                .info-panel .close-btn:hover { color: #fff; }
                .info-panel h3 {
                    color: #00d9ff;
                    font-size: 18px;
                    margin-bottom: 10px;
                    padding-right: 30px;
                }
                .info-panel .address {
                    color: #888;
                    font-size: 13px;
                    margin-bottom: 15px;
                }
                .info-panel .info-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 12px;
                    margin-bottom: 15px;
                }
                .info-panel .info-item label {
                    display: block;
                    font-size: 10px;
                    color: #666;
                    text-transform: uppercase;
                    margin-bottom: 3px;
                }
                .info-panel .info-item .value {
                    color: #fff;
                    font-size: 13px;
                }
                .info-panel .notes {
                    background: #0a1628;
                    padding: 10px;
                    border-radius: 6px;
                    font-size: 12px;
                    color: #aaa;
                    margin-bottom: 15px;
                }
                .info-panel .actions {
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                }

                /* Add Marker Panel */
                .add-panel {
                    position: absolute;
                    top: 20px;
                    right: 20px;
                    width: 350px;
                    background: #16213e;
                    border: 1px solid #0f3460;
                    border-radius: 12px;
                    padding: 20px;
                    display: none;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                    max-height: calc(100% - 40px);
                    overflow-y: auto;
                }
                .add-panel.show { display: block; }
                .add-panel h3 {
                    color: #00d9ff;
                    font-size: 16px;
                    margin-bottom: 15px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .add-panel .close-btn {
                    position: absolute;
                    top: 15px;
                    right: 15px;
                    background: none;
                    border: none;
                    color: #888;
                    font-size: 20px;
                    cursor: pointer;
                }
                .form-group {
                    margin-bottom: 15px;
                }
                .form-group label {
                    display: block;
                    font-size: 12px;
                    color: #888;
                    margin-bottom: 5px;
                }
                .form-group input,
                .form-group select,
                .form-group textarea {
                    width: 100%;
                    padding: 10px 12px;
                    background: #0a1628;
                    border: 1px solid #1f2b4d;
                    border-radius: 6px;
                    color: #fff;
                    font-size: 13px;
                }
                .form-group input:focus,
                .form-group select:focus,
                .form-group textarea:focus {
                    outline: none;
                    border-color: #00d9ff;
                }
                .form-group textarea {
                    min-height: 80px;
                    resize: vertical;
                }
                .form-row {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 10px;
                }

                .btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 10px 16px;
                    border-radius: 8px;
                    font-size: 13px;
                    font-weight: 600;
                    cursor: pointer;
                    border: none;
                    transition: all 0.2s;
                }
                .btn-primary {
                    background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
                    color: #000;
                }
                .btn-primary:hover { transform: translateY(-1px); }
                .btn-secondary { background: #374151; color: #fff; }
                .btn-secondary:hover { background: #4b5563; }
                .btn-danger { background: #dc2626; color: #fff; }
                .btn-danger:hover { background: #b91c1c; }
                .btn-sm { padding: 6px 12px; font-size: 11px; }

                .btn-add-marker {
                    position: absolute;
                    top: 20px;
                    left: 20px;
                    z-index: 10;
                }

                /* Toast */
                .toast {
                    position: fixed;
                    bottom: 30px;
                    left: 50%;
                    transform: translateX(-50%) translateY(100px);
                    padding: 12px 24px;
                    border-radius: 8px;
                    color: #fff;
                    font-weight: 600;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                    z-index: 9999;
                    opacity: 0;
                    transition: all 0.3s ease;
                }
                .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
                .toast.success { background: #22c55e; }
                .toast.error { background: #ef4444; }

                /* Status colors */
                .status-contacted { background: rgba(59,130,246,0.2); color: #3b82f6; }
                .status-visited { background: rgba(139,92,246,0.2); color: #8b5cf6; }
                .status-interested { background: rgba(34,197,94,0.2); color: #22c55e; }
                .status-customer { background: rgba(245,158,11,0.2); color: #f59e0b; }
                .status-not_interested { background: rgba(239,68,68,0.2); color: #ef4444; }
                .status-followup { background: rgba(6,182,212,0.2); color: #06b6d4; }

                @media (max-width: 900px) {
                    .main-container { flex-direction: column; }
                    .sidebar { width: 100%; height: 250px; }
                    .map-container { height: 400px; }
                }
            </style>
        </head>
        <body>
            <div class="admin-header">
                <h1><i class="ri-map-pin-line"></i> Sales Map</h1>
                <div class="header-right">
                    <div class="stats-bar">
                        <span class="stat-badge status-contacted">📧 <?php echo $stats['contacted']; ?> Kontaktiert</span>
                        <span class="stat-badge status-visited">👣 <?php echo $stats['visited']; ?> Besucht</span>
                        <span class="stat-badge status-interested">👍 <?php echo $stats['interested']; ?> Interessiert</span>
                        <span class="stat-badge status-customer">⭐ <?php echo $stats['customer']; ?> Kunden</span>
                    </div>
                    <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza</a>
                </div>
            </div>

            <div class="main-container">
                <!-- Sidebar -->
                <div class="sidebar">
                    <div class="sidebar-header">
                        <h3><i class="ri-search-line"></i> Üzlet keresése</h3>
                        <div class="search-box">
                            <i class="ri-search-line"></i>
                            <input type="text" id="places-search" placeholder="Cím vagy üzlet neve...">
                            <div class="search-results" id="search-results"></div>
                        </div>
                    </div>

                    <div class="filter-tabs">
                        <button class="filter-tab active" data-filter="all">Mind (<?php echo $stats['total']; ?>)</button>
                        <button class="filter-tab" data-filter="contacted">📧</button>
                        <button class="filter-tab" data-filter="visited">👣</button>
                        <button class="filter-tab" data-filter="interested">👍</button>
                        <button class="filter-tab" data-filter="customer">⭐</button>
                        <button class="filter-tab" data-filter="not_interested">👎</button>
                    </div>

                    <div class="marker-list" id="marker-list">
                        <?php foreach ($markers as $m): ?>
                            <div class="marker-item" data-id="<?php echo $m->id; ?>" data-status="<?php echo $m->status; ?>" data-lat="<?php echo $m->lat; ?>" data-lng="<?php echo $m->lng; ?>">
                                <div class="header">
                                    <span class="name"><?php echo esc_html($m->business_name); ?></span>
                                    <span class="status-badge status-<?php echo $m->status; ?>">
                                        <?php echo $status_config[$m->status]['icon'] ?? '📍'; ?>
                                    </span>
                                </div>
                                <div class="address"><?php echo esc_html($m->address); ?></div>
                                <div class="meta">
                                    <?php if ($m->contact_name): ?>
                                        <span><i class="ri-user-line"></i> <?php echo esc_html($m->contact_name); ?></span>
                                    <?php endif; ?>
                                    <span><i class="ri-calendar-line"></i> <?php echo date('Y-m-d', strtotime($m->created_at)); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($markers)): ?>
                            <div style="text-align: center; padding: 40px 20px; color: #666;">
                                <i class="ri-map-pin-add-line" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                                <p>Még nincs marker</p>
                                <p style="font-size: 12px;">Keress egy üzletet a térképen!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Map -->
                <div class="map-container">
                    <button class="btn btn-primary btn-add-marker" onclick="enableAddMode()">
                        <i class="ri-add-line"></i> Új marker
                    </button>

                    <div id="map"></div>

                    <!-- Add Marker Panel -->
                    <div class="add-panel" id="add-panel">
                        <button class="close-btn" onclick="closeAddPanel()">&times;</button>
                        <h3><i class="ri-map-pin-add-line"></i> Új marker hozzáadása</h3>
                        <form method="post" id="add-marker-form">
                            <input type="hidden" name="add_marker" value="1">
                            <input type="hidden" name="lat" id="add-lat">
                            <input type="hidden" name="lng" id="add-lng">

                            <div class="form-group">
                                <label>Üzlet neve *</label>
                                <input type="text" name="business_name" id="add-name" required>
                            </div>

                            <div class="form-group">
                                <label>Cím</label>
                                <input type="text" name="address" id="add-address">
                            </div>

                            <div class="form-group">
                                <label>Város</label>
                                <input type="text" name="city" id="add-city">
                            </div>

                            <div class="form-group">
                                <label>Státusz</label>
                                <select name="status" id="add-status">
                                    <option value="contacted">📧 Kontaktiert</option>
                                    <option value="visited">👣 Besucht</option>
                                    <option value="interested">👍 Interessiert</option>
                                    <option value="customer">⭐ Kunde</option>
                                    <option value="not_interested">👎 Kein Interesse</option>
                                    <option value="followup">📅 Follow-up</option>
                                </select>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Kapcsolattartó</label>
                                    <input type="text" name="contact_name">
                                </div>
                                <div class="form-group">
                                    <label>Telefon</label>
                                    <input type="text" name="contact_phone">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="contact_email">
                            </div>

                            <div class="form-group">
                                <label>Megjegyzés</label>
                                <textarea name="notes" placeholder="Fontos infók..."></textarea>
                            </div>

                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-primary" style="flex: 1;">
                                    <i class="ri-save-line"></i> Mentés
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="closeAddPanel()">Mégsem</button>
                            </div>
                        </form>
                    </div>

                    <!-- Info Panel -->
                    <div class="info-panel" id="info-panel">
                        <button class="close-btn" onclick="closeInfoPanel()">&times;</button>
                        <h3 id="info-name"></h3>
                        <div class="address" id="info-address"></div>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Státusz</label>
                                <div class="value" id="info-status"></div>
                            </div>
                            <div class="info-item">
                                <label>Kapcsolattartó</label>
                                <div class="value" id="info-contact"></div>
                            </div>
                            <div class="info-item">
                                <label>Telefon</label>
                                <div class="value" id="info-phone"></div>
                            </div>
                            <div class="info-item">
                                <label>Email</label>
                                <div class="value" id="info-email"></div>
                            </div>
                        </div>
                        <div class="notes" id="info-notes"></div>
                        <div class="actions">
                            <form method="post" style="display: inline;" id="update-form">
                                <input type="hidden" name="update_marker" value="1">
                                <input type="hidden" name="marker_id" id="update-marker-id">
                                <select name="status" id="update-status" style="padding: 8px; background: #0a1628; border: 1px solid #1f2b4d; border-radius: 6px; color: #fff; font-size: 12px;">
                                    <option value="contacted">📧 Kontaktiert</option>
                                    <option value="visited">👣 Besucht</option>
                                    <option value="interested">👍 Interessiert</option>
                                    <option value="customer">⭐ Kunde</option>
                                    <option value="not_interested">👎 Kein Interesse</option>
                                    <option value="followup">📅 Follow-up</option>
                                </select>
                                <input type="hidden" name="notes" id="update-notes">
                                <button type="submit" class="btn btn-primary btn-sm">Státusz frissítése</button>
                            </form>
                            <a href="/admin/email-sender" class="btn btn-secondary btn-sm" id="send-email-btn" style="display: none;">
                                <i class="ri-mail-send-line"></i> Email küldése
                            </a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Biztosan törlöd?');">
                                <input type="hidden" name="delete_marker" value="1">
                                <input type="hidden" name="marker_id" id="delete-marker-id">
                                <button type="submit" class="btn btn-danger btn-sm"><i class="ri-delete-bin-line"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast -->
            <div class="toast" id="toast"></div>

            <!-- Google Maps Script -->
            <script>
            const markersData = <?php echo json_encode($markers_json); ?>;
            const statusConfig = <?php echo json_encode($status_config); ?>;

            let map;
            let markers = [];
            let infoWindow;
            let searchBox;
            let addMode = false;
            let tempMarker = null;
            let activeFilter = 'all';

            function initMap() {
                // Center on Germany
                const germany = { lat: 48.7758, lng: 9.1829 }; // Stuttgart area

                map = new google.maps.Map(document.getElementById('map'), {
                    zoom: 8,
                    center: germany,
                    styles: [
                        { elementType: "geometry", stylers: [{ color: "#1a1a2e" }] },
                        { elementType: "labels.text.stroke", stylers: [{ color: "#1a1a2e" }] },
                        { elementType: "labels.text.fill", stylers: [{ color: "#746855" }] },
                        { featureType: "administrative.locality", elementType: "labels.text.fill", stylers: [{ color: "#00d9ff" }] },
                        { featureType: "poi", elementType: "labels.text.fill", stylers: [{ color: "#93817c" }] },
                        { featureType: "poi.park", elementType: "geometry.fill", stylers: [{ color: "#1a3a2e" }] },
                        { featureType: "poi.park", elementType: "labels.text.fill", stylers: [{ color: "#6b9a76" }] },
                        { featureType: "road", elementType: "geometry", stylers: [{ color: "#2d3a4f" }] },
                        { featureType: "road", elementType: "geometry.stroke", stylers: [{ color: "#1f2b4d" }] },
                        { featureType: "road", elementType: "labels.text.fill", stylers: [{ color: "#9ca5b3" }] },
                        { featureType: "road.highway", elementType: "geometry", stylers: [{ color: "#3d4f6f" }] },
                        { featureType: "road.highway", elementType: "geometry.stroke", stylers: [{ color: "#1f2e4f" }] },
                        { featureType: "road.highway", elementType: "labels.text.fill", stylers: [{ color: "#b0d5ce" }] },
                        { featureType: "transit", elementType: "geometry", stylers: [{ color: "#2f3948" }] },
                        { featureType: "transit.station", elementType: "labels.text.fill", stylers: [{ color: "#00d9ff" }] },
                        { featureType: "water", elementType: "geometry", stylers: [{ color: "#0f3460" }] },
                        { featureType: "water", elementType: "labels.text.fill", stylers: [{ color: "#515c6d" }] }
                    ],
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: true
                });

                infoWindow = new google.maps.InfoWindow();

                // Add existing markers
                markersData.forEach(data => addMarkerToMap(data));

                // Setup Places Autocomplete
                const input = document.getElementById('places-search');
                const autocomplete = new google.maps.places.Autocomplete(input, {
                    types: ['establishment'],
                    componentRestrictions: { country: 'de' }
                });

                autocomplete.addListener('place_changed', () => {
                    const place = autocomplete.getPlace();
                    if (!place.geometry) return;

                    map.setCenter(place.geometry.location);
                    map.setZoom(17);

                    // Show add panel with place data
                    showAddPanel(
                        place.geometry.location.lat(),
                        place.geometry.location.lng(),
                        place.name || '',
                        place.formatted_address || '',
                        extractCity(place.address_components)
                    );
                });

                // Click on map to add marker
                map.addListener('click', (e) => {
                    if (addMode) {
                        showAddPanel(e.latLng.lat(), e.latLng.lng());
                    }
                });

                // Fit bounds if we have markers
                if (markersData.length > 0) {
                    const bounds = new google.maps.LatLngBounds();
                    markersData.forEach(m => bounds.extend({ lat: m.lat, lng: m.lng }));
                    map.fitBounds(bounds);
                }
            }

            function extractCity(components) {
                if (!components) return '';
                for (const c of components) {
                    if (c.types.includes('locality')) return c.long_name;
                }
                return '';
            }

            function addMarkerToMap(data) {
                const config = statusConfig[data.status] || { color: '#888', icon: '📍' };

                const marker = new google.maps.Marker({
                    position: { lat: data.lat, lng: data.lng },
                    map: map,
                    title: data.name,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 12,
                        fillColor: config.color,
                        fillOpacity: 1,
                        strokeColor: '#fff',
                        strokeWeight: 2
                    }
                });

                marker.data = data;

                marker.addListener('click', () => {
                    showInfoPanel(data);
                    highlightMarkerItem(data.id);
                });

                markers.push(marker);
            }

            function showAddPanel(lat, lng, name = '', address = '', city = '') {
                document.getElementById('add-lat').value = lat;
                document.getElementById('add-lng').value = lng;
                document.getElementById('add-name').value = name;
                document.getElementById('add-address').value = address;
                document.getElementById('add-city').value = city;
                document.getElementById('add-panel').classList.add('show');

                // Add temp marker
                if (tempMarker) tempMarker.setMap(null);
                tempMarker = new google.maps.Marker({
                    position: { lat, lng },
                    map: map,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 15,
                        fillColor: '#00d9ff',
                        fillOpacity: 0.5,
                        strokeColor: '#00d9ff',
                        strokeWeight: 3
                    }
                });
            }

            function closeAddPanel() {
                document.getElementById('add-panel').classList.remove('show');
                if (tempMarker) {
                    tempMarker.setMap(null);
                    tempMarker = null;
                }
                addMode = false;
            }

            function showInfoPanel(data) {
                document.getElementById('info-name').textContent = data.name;
                document.getElementById('info-address').textContent = data.address;
                document.getElementById('info-status').innerHTML = `<span class="status-badge status-${data.status}">${statusConfig[data.status]?.icon || '📍'} ${statusConfig[data.status]?.label || data.status}</span>`;
                document.getElementById('info-contact').textContent = data.contact_name || '-';
                document.getElementById('info-phone').textContent = data.contact_phone || '-';
                document.getElementById('info-email').textContent = data.contact_email || '-';
                document.getElementById('info-notes').textContent = data.notes || 'Nincs megjegyzés';
                document.getElementById('update-marker-id').value = data.id;
                document.getElementById('delete-marker-id').value = data.id;
                document.getElementById('update-status').value = data.status;
                document.getElementById('update-notes').value = data.notes || '';

                // Show email button if we have email
                const emailBtn = document.getElementById('send-email-btn');
                if (data.contact_email) {
                    emailBtn.style.display = 'inline-flex';
                    emailBtn.href = `/admin/email-sender?to=${encodeURIComponent(data.contact_email)}&name=${encodeURIComponent(data.contact_name || data.name)}`;
                } else {
                    emailBtn.style.display = 'none';
                }

                document.getElementById('info-panel').classList.add('show');
            }

            function closeInfoPanel() {
                document.getElementById('info-panel').classList.remove('show');
            }

            function enableAddMode() {
                addMode = true;
                showToast('Kattints a térképre egy marker hozzáadásához!', 'info');
            }

            function highlightMarkerItem(id) {
                document.querySelectorAll('.marker-item').forEach(el => el.classList.remove('active'));
                const item = document.querySelector(`.marker-item[data-id="${id}"]`);
                if (item) {
                    item.classList.add('active');
                    item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }

            // Filter tabs
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    activeFilter = tab.dataset.filter;
                    filterMarkers();
                });
            });

            function filterMarkers() {
                document.querySelectorAll('.marker-item').forEach(el => {
                    const status = el.dataset.status;
                    if (activeFilter === 'all' || status === activeFilter) {
                        el.style.display = 'block';
                    } else {
                        el.style.display = 'none';
                    }
                });

                markers.forEach(m => {
                    if (activeFilter === 'all' || m.data.status === activeFilter) {
                        m.setMap(map);
                    } else {
                        m.setMap(null);
                    }
                });
            }

            // Marker list item click
            document.querySelectorAll('.marker-item').forEach(item => {
                item.addEventListener('click', () => {
                    const lat = parseFloat(item.dataset.lat);
                    const lng = parseFloat(item.dataset.lng);
                    const id = parseInt(item.dataset.id);

                    map.setCenter({ lat, lng });
                    map.setZoom(16);

                    const data = markersData.find(m => m.id === id);
                    if (data) showInfoPanel(data);
                    highlightMarkerItem(id);
                });
            });

            function showToast(message, type = 'success') {
                const toast = document.getElementById('toast');
                toast.textContent = message;
                toast.className = 'toast ' + type + ' show';
                setTimeout(() => toast.classList.remove('show'), 3000);
            }

            // Show success/error messages
            <?php if ($success === 'added'): ?>
                setTimeout(() => showToast('Marker sikeresen hozzáadva!'), 500);
            <?php elseif ($success === 'updated'): ?>
                setTimeout(() => showToast('Marker frissítve!'), 500);
            <?php elseif ($success === 'deleted'): ?>
                setTimeout(() => showToast('Marker törölve!'), 500);
            <?php elseif ($error): ?>
                setTimeout(() => showToast('Hiba történt!', 'error'), 500);
            <?php endif; ?>
            </script>

            <!-- Load Google Maps -->
            <?php
            $google_api_key = defined('PPV_GOOGLE_MAPS_KEY') ? PPV_GOOGLE_MAPS_KEY : get_option('ppv_google_maps_api_key', '');
            if ($google_api_key):
            ?>
            <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr($google_api_key); ?>&libraries=places&callback=initMap"></script>
            <?php else: ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('map').innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #ff6b6b; text-align: center; padding: 40px;"><div><i class="ri-error-warning-line" style="font-size: 48px; display: block; margin-bottom: 15px;"></i><h3>Google Maps API kulcs hiányzik!</h3><p style="color: #888; margin-top: 10px;">Állítsd be a PPV_GOOGLE_MAPS_KEY konstanst a wp-config.php fájlban.</p></div></div>';
                });
            </script>
            <?php endif; ?>
        </body>
        </html>
        <?php
    }
}
