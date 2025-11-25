<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - Admin Pending Scans Management
 * Displays pending scan requests (10km+ distance) for admin approval
 */
class PPV_Admin_Pending_Scans {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_submenu'], 21);
        add_action('admin_post_ppv_approve_pending_scan', [__CLASS__, 'handle_approve']);
        add_action('admin_post_ppv_reject_pending_scan', [__CLASS__, 'handle_reject']);
    }

    /**
     * Add submenu under PunktePass
     */
    public static function add_admin_submenu() {
        // Get count for badge
        $pending_count = 0;
        if (class_exists('PPV_Scan_Monitoring')) {
            $pending_count = PPV_Scan_Monitoring::get_pending_scans_count();
        }
        $counter_badge = $pending_count > 0 ? " <span class='awaiting-mod'>{$pending_count}</span>" : "";

        add_submenu_page(
            'punktepass-admin',
            'Függő Scanek',
            'Függő Scanek' . $counter_badge,
            'manage_options',
            'punktepass-pending-scans',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Render pending scans page
     */
    public static function render_page() {
        if (!class_exists('PPV_Scan_Monitoring')) {
            echo '<div class="wrap"><h1>Error</h1><p>Scan Monitoring class not found.</p></div>';
            return;
        }

        // Get filter status
        $status_filter = isset($_GET['scan_status']) ? sanitize_text_field($_GET['scan_status']) : 'pending';

        // Get scans
        $scans = PPV_Scan_Monitoring::get_pending_scans($status_filter);

        // Count by status
        global $wpdb;
        $counts = [
            'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pending_scans WHERE status = 'pending'"),
            'approved' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pending_scans WHERE status = 'approved'"),
            'rejected' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pending_scans WHERE status = 'rejected'"),
        ];
        $counts['all'] = array_sum($counts);

        ?>
        <div class="wrap">
            <h1>📋 Függő Scanek (10km+ távolság)</h1>
            <p>Ezek a scanek <strong>10km-nél távolabbról</strong> érkeztek. A pontok csak admin jóváhagyás után kerülnek jóváírásra.</p>

            <?php if (isset($_GET['success'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo $_GET['success'] === 'approved' ? '✅ Scan jóváhagyva, pontok hozzáadva!' : '❌ Scan elutasítva.'; ?></p>
                </div>
            <?php endif; ?>

            <!-- Status tabs -->
            <div class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="<?php echo admin_url('admin.php?page=punktepass-pending-scans&scan_status=pending'); ?>"
                   class="nav-tab <?php echo $status_filter === 'pending' ? 'nav-tab-active' : ''; ?>">
                    ⏳ Függő (<?php echo $counts['pending']; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=punktepass-pending-scans&scan_status=approved'); ?>"
                   class="nav-tab <?php echo $status_filter === 'approved' ? 'nav-tab-active' : ''; ?>">
                    ✅ Jóváhagyott (<?php echo $counts['approved']; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=punktepass-pending-scans&scan_status=rejected'); ?>"
                   class="nav-tab <?php echo $status_filter === 'rejected' ? 'nav-tab-active' : ''; ?>">
                    ❌ Elutasított (<?php echo $counts['rejected']; ?>)
                </a>
            </div>

            <?php if (empty($scans)): ?>
                <div class="notice notice-info">
                    <p>Nincs függő scan ebben a kategóriában!</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Bolt</th>
                            <th>Felhasználó</th>
                            <th style="width: 80px;">Pontok</th>
                            <th style="width: 100px;">Távolság</th>
                            <th style="width: 140px;">Időpont</th>
                            <th style="width: 80px;">Státusz</th>
                            <th style="width: 250px;">Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scans as $scan): ?>
                            <?php
                            $user_name = trim(($scan->first_name ?? '') . ' ' . ($scan->last_name ?? '')) ?: 'User #' . $scan->user_id;
                            $store_name = $scan->company_name ?: $scan->store_name ?: 'Store #' . $scan->store_id;
                            $distance_km = round(($scan->distance_meters ?? 0) / 1000, 1);

                            // Status badge
                            $status_badges = [
                                'pending' => ['text' => 'Függő', 'class' => 'warning', 'icon' => '⏳'],
                                'approved' => ['text' => 'Jóváhagyva', 'class' => 'success', 'icon' => '✅'],
                                'rejected' => ['text' => 'Elutasítva', 'class' => 'error', 'icon' => '❌']
                            ];
                            $badge = $status_badges[$scan->status] ?? $status_badges['pending'];
                            ?>
                            <tr style="<?php echo $scan->status === 'pending' ? 'background: #fff3cd;' : ''; ?>">
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
                                <td style="text-align: center;">
                                    <strong style="color: #2271b1; font-size: 16px;">+<?php echo intval($scan->points); ?></strong>
                                </td>
                                <td>
                                    <strong style="color: #d63638;"><?php echo $distance_km; ?> km</strong>
                                    <br><small>(<?php echo number_format($scan->distance_meters ?? 0); ?> m)</small>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($scan->created_at)); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $badge['class']; ?>">
                                        <?php echo $badge['icon'] . ' ' . $badge['text']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($scan->status === 'pending'): ?>
                                        <!-- Approve -->
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                            <?php wp_nonce_field('ppv_pending_scan_action', 'ppv_pending_nonce'); ?>
                                            <input type="hidden" name="action" value="ppv_approve_pending_scan">
                                            <input type="hidden" name="pending_id" value="<?php echo intval($scan->id); ?>">
                                            <button type="submit" class="button button-primary button-small" style="background: #46b450; border-color: #3c9e3f;">
                                                ✅ Jóváhagy
                                            </button>
                                        </form>

                                        <!-- Reject -->
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                            <?php wp_nonce_field('ppv_pending_scan_action', 'ppv_pending_nonce'); ?>
                                            <input type="hidden" name="action" value="ppv_reject_pending_scan">
                                            <input type="hidden" name="pending_id" value="<?php echo intval($scan->id); ?>">
                                            <button type="submit" class="button button-small" style="background: #f8d7da; border-color: #f5c6cb;" onclick="return confirm('Biztosan elutasítja? A pontok NEM kerülnek jóváírásra.');">
                                                ❌ Elutasít
                                            </button>
                                        </form>
                                    <?php elseif ($scan->status === 'approved'): ?>
                                        <small style="color: #46b450;">
                                            <?php if ($scan->reviewed_at): ?>
                                                Jóváhagyva: <?php echo date('m.d H:i', strtotime($scan->reviewed_at)); ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php elseif ($scan->status === 'rejected'): ?>
                                        <small style="color: #d63638;">
                                            <?php if ($scan->reviewed_at): ?>
                                                Elutasítva: <?php echo date('m.d H:i', strtotime($scan->reviewed_at)); ?>
                                            <?php endif; ?>
                                        </small>
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
     * Handle approve pending scan
     */
    public static function handle_approve() {
        if (!current_user_can('manage_options')) {
            wp_die('Nincs jogosultság');
        }

        check_admin_referer('ppv_pending_scan_action', 'ppv_pending_nonce');

        $pending_id = intval($_POST['pending_id']);

        if (class_exists('PPV_Scan_Monitoring')) {
            $result = PPV_Scan_Monitoring::approve_pending_scan($pending_id);
        }

        wp_redirect(admin_url('admin.php?page=punktepass-pending-scans&success=approved'));
        exit;
    }

    /**
     * Handle reject pending scan
     */
    public static function handle_reject() {
        if (!current_user_can('manage_options')) {
            wp_die('Nincs jogosultság');
        }

        check_admin_referer('ppv_pending_scan_action', 'ppv_pending_nonce');

        $pending_id = intval($_POST['pending_id']);

        if (class_exists('PPV_Scan_Monitoring')) {
            $result = PPV_Scan_Monitoring::reject_pending_scan($pending_id);
        }

        wp_redirect(admin_url('admin.php?page=punktepass-pending-scans&success=rejected'));
        exit;
    }
}

// Initialize
PPV_Admin_Pending_Scans::init();
