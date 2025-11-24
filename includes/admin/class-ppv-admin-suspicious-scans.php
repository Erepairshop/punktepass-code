<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - Admin Suspicious Scans Management
 * Displays suspicious scan alerts with GPS distance issues
 */
class PPV_Admin_Suspicious_Scans {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_submenu'], 20);
        add_action('admin_post_ppv_update_suspicious_scan', [__CLASS__, 'handle_update_status']);
        add_action('admin_post_ppv_toggle_store_monitoring', [__CLASS__, 'handle_toggle_monitoring']);
        add_action('admin_post_ppv_set_scanner_type', [__CLASS__, 'handle_set_scanner_type']);
    }

    /**
     * Add submenu under PunktePass
     */
    public static function add_admin_submenu() {
        // Get count for badge
        $new_count = 0;
        if (class_exists('PPV_Scan_Monitoring')) {
            $new_count = PPV_Scan_Monitoring::get_new_suspicious_count();
        }
        $counter_badge = $new_count > 0 ? " <span class='awaiting-mod'>{$new_count}</span>" : "";

        add_submenu_page(
            'punktepass-admin',
            'Gyanús beolvasások',
            'Gyanús Scans' . $counter_badge,
            'manage_options',
            'punktepass-suspicious-scans',
            [__CLASS__, 'render_page']
        );

        // Store monitoring settings page
        add_submenu_page(
            'punktepass-admin',
            'Scan Monitoring',
            'Scan Monitoring',
            'manage_options',
            'punktepass-scan-monitoring',
            [__CLASS__, 'render_monitoring_page']
        );
    }

    /**
     * Render suspicious scans page
     */
    public static function render_page() {
        if (!class_exists('PPV_Scan_Monitoring')) {
            echo '<div class="wrap"><h1>Error</h1><p>Scan Monitoring class not found.</p></div>';
            return;
        }

        // Get filter status
        $status_filter = isset($_GET['scan_status']) ? sanitize_text_field($_GET['scan_status']) : 'new';

        // Get scans
        $scans = PPV_Scan_Monitoring::get_suspicious_scans(
            $status_filter !== 'all' ? $status_filter : null
        );

        // Count by status
        global $wpdb;
        $counts = [
            'new' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_suspicious_scans WHERE status = 'new'"),
            'reviewed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_suspicious_scans WHERE status = 'reviewed'"),
            'dismissed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_suspicious_scans WHERE status = 'dismissed'"),
            'blocked' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_suspicious_scans WHERE status = 'blocked'"),
        ];
        $counts['all'] = array_sum($counts);

        ?>
        <div class="wrap">
            <h1>Gyanús beolvasások</h1>
            <p>GPS távolság alapú gyanús scan-ok listája. A rendszer automatikusan észleli, ha valaki túl messzről próbál beolvasni.</p>

            <!-- Status tabs -->
            <div class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="<?php echo admin_url('admin.php?page=punktepass-suspicious-scans&scan_status=new'); ?>"
                   class="nav-tab <?php echo $status_filter === 'new' ? 'nav-tab-active' : ''; ?>">
                    Új (<?php echo $counts['new']; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=punktepass-suspicious-scans&scan_status=reviewed'); ?>"
                   class="nav-tab <?php echo $status_filter === 'reviewed' ? 'nav-tab-active' : ''; ?>">
                    Ellenorizve (<?php echo $counts['reviewed']; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=punktepass-suspicious-scans&scan_status=dismissed'); ?>"
                   class="nav-tab <?php echo $status_filter === 'dismissed' ? 'nav-tab-active' : ''; ?>">
                    Elvetve (<?php echo $counts['dismissed']; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=punktepass-suspicious-scans&scan_status=blocked'); ?>"
                   class="nav-tab <?php echo $status_filter === 'blocked' ? 'nav-tab-active' : ''; ?>">
                    Tiltva (<?php echo $counts['blocked']; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=punktepass-suspicious-scans&scan_status=all'); ?>"
                   class="nav-tab <?php echo $status_filter === 'all' ? 'nav-tab-active' : ''; ?>">
                    Mind (<?php echo $counts['all']; ?>)
                </a>
            </div>

            <?php if (empty($scans)): ?>
                <div class="notice notice-info">
                    <p>Nincs gyanús scan ebben a kategóriában!</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Bolt</th>
                            <th>Felhasználó</th>
                            <th style="width: 100px;">Távolság</th>
                            <th>GPS koordináták</th>
                            <th style="width: 80px;">Státusz</th>
                            <th style="width: 140px;">Időpont</th>
                            <th style="width: 200px;">Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scans as $scan): ?>
                            <?php
                            $user_name = trim(($scan->first_name ?? '') . ' ' . ($scan->last_name ?? '')) ?: 'User #' . $scan->user_id;
                            $store_name = $scan->company_name ?: $scan->store_name ?: 'Store #' . $scan->store_id;

                            // Status badge
                            $status_badges = [
                                'new' => ['text' => 'Új', 'class' => 'warning'],
                                'reviewed' => ['text' => 'Ellenorizve', 'class' => 'info'],
                                'dismissed' => ['text' => 'Elvetve', 'class' => 'success'],
                                'blocked' => ['text' => 'Tiltva', 'class' => 'error']
                            ];
                            $badge = $status_badges[$scan->status] ?? $status_badges['new'];
                            ?>
                            <tr style="<?php echo $scan->status === 'new' ? 'background: #fff3cd;' : ''; ?>">
                                <td><strong>#<?php echo intval($scan->id); ?></strong></td>
                                <td>
                                    <strong><?php echo esc_html($store_name); ?></strong>
                                    <?php if (!empty($scan->store_city)): ?>
                                        <br><small style="color: #666;"><?php echo esc_html($scan->store_city); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html($user_name); ?>
                                    <?php if (!empty($scan->user_email)): ?>
                                        <br><small><a href="mailto:<?php echo esc_attr($scan->user_email); ?>"><?php echo esc_html($scan->user_email); ?></a></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: #d63638;"><?php echo number_format($scan->distance_meters ?? 0); ?> m</strong>
                                </td>
                                <td>
                                    <small>
                                        <strong>Scan:</strong> <?php echo esc_html($scan->scan_latitude); ?>, <?php echo esc_html($scan->scan_longitude); ?><br>
                                        <strong>Bolt:</strong> <?php echo esc_html($scan->store_latitude); ?>, <?php echo esc_html($scan->store_longitude); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $badge['class']; ?>">
                                        <?php echo $badge['text']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($scan->created_at)); ?></td>
                                <td>
                                    <?php if ($scan->status === 'new'): ?>
                                        <!-- Mark as reviewed -->
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                            <?php wp_nonce_field('ppv_update_suspicious', 'ppv_suspicious_nonce'); ?>
                                            <input type="hidden" name="action" value="ppv_update_suspicious_scan">
                                            <input type="hidden" name="scan_id" value="<?php echo intval($scan->id); ?>">
                                            <input type="hidden" name="new_status" value="reviewed">
                                            <button type="submit" class="button button-small">Ellenorizve</button>
                                        </form>

                                        <!-- Dismiss -->
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                            <?php wp_nonce_field('ppv_update_suspicious', 'ppv_suspicious_nonce'); ?>
                                            <input type="hidden" name="action" value="ppv_update_suspicious_scan">
                                            <input type="hidden" name="scan_id" value="<?php echo intval($scan->id); ?>">
                                            <input type="hidden" name="new_status" value="dismissed">
                                            <button type="submit" class="button button-small" style="background: #d4edda; border-color: #c3e6cb;">Elvet</button>
                                        </form>

                                        <!-- Block -->
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                            <?php wp_nonce_field('ppv_update_suspicious', 'ppv_suspicious_nonce'); ?>
                                            <input type="hidden" name="action" value="ppv_update_suspicious_scan">
                                            <input type="hidden" name="scan_id" value="<?php echo intval($scan->id); ?>">
                                            <input type="hidden" name="new_status" value="blocked">
                                            <button type="submit" class="button button-small" style="background: #f8d7da; border-color: #f5c6cb;" onclick="return confirm('Tiltani szeretné ezt a felhasználót?');">Tilt</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <style>
            .badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: bold;
                white-space: nowrap;
            }
            .badge-success { background: #d4edda; color: #155724; }
            .badge-info { background: #d1ecf1; color: #0c5460; }
            .badge-warning { background: #fff3cd; color: #856404; }
            .badge-error { background: #f8d7da; color: #721c24; }
        </style>
        <?php
    }

    /**
     * Render store monitoring settings page
     */
    public static function render_monitoring_page() {
        global $wpdb;

        // Get all stores with monitoring info
        $stores = $wpdb->get_results("
            SELECT
                s.id,
                s.name,
                s.company_name,
                s.city,
                s.latitude,
                s.longitude,
                s.scan_monitoring_enabled,
                s.scanner_type,
                s.max_scan_distance,
                s.parent_store_id,
                (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_suspicious_scans ss WHERE ss.store_id = s.id AND ss.status = 'new') as suspicious_count
            FROM {$wpdb->prefix}ppv_stores s
            ORDER BY s.parent_store_id IS NOT NULL, s.id DESC
        ");

        ?>
        <div class="wrap">
            <h1>Scan Monitoring beállítások</h1>
            <p>GPS alapú scan ellenőrzés beállítása boltonként. A <strong>Mobile Scanner</strong> típusnál nincs GPS ellenőrzés.</p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Bolt</th>
                        <th>Város</th>
                        <th style="width: 120px;">GPS koordináták</th>
                        <th style="width: 100px;">Monitoring</th>
                        <th style="width: 150px;">Scanner típus</th>
                        <th style="width: 80px;">Max táv.</th>
                        <th style="width: 80px;">Gyanús</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stores as $store): ?>
                        <?php
                        $store_name = $store->company_name ?: $store->name;
                        $is_filiale = !empty($store->parent_store_id);
                        $has_gps = !empty($store->latitude) && !empty($store->longitude);
                        ?>
                        <tr>
                            <td>
                                <?php echo intval($store->id); ?>
                                <?php if ($is_filiale): ?>
                                    <br><small style="color: #666;">(Filiale)</small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($store_name); ?></strong></td>
                            <td><?php echo esc_html($store->city); ?></td>
                            <td>
                                <?php if ($has_gps): ?>
                                    <small><?php echo $store->latitude; ?>, <?php echo $store->longitude; ?></small>
                                <?php else: ?>
                                    <span style="color: #999;">Nincs GPS</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                    <?php wp_nonce_field('ppv_toggle_monitoring', 'ppv_monitoring_nonce'); ?>
                                    <input type="hidden" name="action" value="ppv_toggle_store_monitoring">
                                    <input type="hidden" name="store_id" value="<?php echo intval($store->id); ?>">
                                    <input type="hidden" name="enabled" value="<?php echo $store->scan_monitoring_enabled ? '0' : '1'; ?>">
                                    <button type="submit" class="button button-small <?php echo $store->scan_monitoring_enabled ? 'button-primary' : ''; ?>">
                                        <?php echo $store->scan_monitoring_enabled ? 'BE' : 'KI'; ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: flex; gap: 5px;">
                                    <?php wp_nonce_field('ppv_set_scanner_type', 'ppv_scanner_type_nonce'); ?>
                                    <input type="hidden" name="action" value="ppv_set_scanner_type">
                                    <input type="hidden" name="store_id" value="<?php echo intval($store->id); ?>">
                                    <select name="scanner_type" style="width: 100px;">
                                        <option value="fixed" <?php selected($store->scanner_type, 'fixed'); ?>>Fixed</option>
                                        <option value="mobile" <?php selected($store->scanner_type, 'mobile'); ?>>Mobile</option>
                                    </select>
                                    <button type="submit" class="button button-small">OK</button>
                                </form>
                            </td>
                            <td><?php echo intval($store->max_scan_distance) ?: 500; ?> m</td>
                            <td>
                                <?php if ($store->suspicious_count > 0): ?>
                                    <span style="color: #d63638; font-weight: bold;"><?php echo $store->suspicious_count; ?></span>
                                <?php else: ?>
                                    <span style="color: #46b450;">0</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Handle status update
     */
    public static function handle_update_status() {
        if (!current_user_can('manage_options')) {
            wp_die('Nincs jogosultság');
        }

        check_admin_referer('ppv_update_suspicious', 'ppv_suspicious_nonce');

        $scan_id = intval($_POST['scan_id']);
        $new_status = sanitize_text_field($_POST['new_status']);

        if (!in_array($new_status, ['new', 'reviewed', 'dismissed', 'blocked'])) {
            wp_die('Érvénytelen státusz');
        }

        if (class_exists('PPV_Scan_Monitoring')) {
            PPV_Scan_Monitoring::update_scan_status($scan_id, $new_status);
        }

        wp_redirect(admin_url('admin.php?page=punktepass-suspicious-scans&success=updated'));
        exit;
    }

    /**
     * Handle monitoring toggle
     */
    public static function handle_toggle_monitoring() {
        if (!current_user_can('manage_options')) {
            wp_die('Nincs jogosultság');
        }

        check_admin_referer('ppv_toggle_monitoring', 'ppv_monitoring_nonce');

        $store_id = intval($_POST['store_id']);
        $enabled = intval($_POST['enabled']);

        if (class_exists('PPV_Scan_Monitoring')) {
            PPV_Scan_Monitoring::toggle_monitoring($store_id, $enabled);
        }

        wp_redirect(admin_url('admin.php?page=punktepass-scan-monitoring&success=updated'));
        exit;
    }

    /**
     * Handle scanner type change
     */
    public static function handle_set_scanner_type() {
        if (!current_user_can('manage_options')) {
            wp_die('Nincs jogosultság');
        }

        check_admin_referer('ppv_set_scanner_type', 'ppv_scanner_type_nonce');

        $store_id = intval($_POST['store_id']);
        $scanner_type = sanitize_text_field($_POST['scanner_type']);

        if (class_exists('PPV_Scan_Monitoring')) {
            PPV_Scan_Monitoring::set_scanner_type($store_id, $scanner_type);
        }

        wp_redirect(admin_url('admin.php?page=punktepass-scan-monitoring&success=updated'));
        exit;
    }
}

// Initialize
PPV_Admin_Suspicious_Scans::init();
