<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - Admin Suspicious Devices Management
 * Displays devices with multiple accounts (potential fraud)
 */
class PPV_Admin_Suspicious_Devices {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_submenu'], 21);
        add_action('admin_post_ppv_block_device', [__CLASS__, 'handle_block_device']);
        add_action('admin_post_ppv_unblock_device', [__CLASS__, 'handle_unblock_device']);
    }

    /**
     * Add submenu under PunktePass
     */
    public static function add_admin_submenu() {
        // Get count of suspicious devices (more than 1 account)
        $suspicious_count = self::get_suspicious_device_count();
        $counter_badge = $suspicious_count > 0 ? " <span class='awaiting-mod'>{$suspicious_count}</span>" : "";

        add_submenu_page(
            'punktepass-admin',
            'Gyanús eszközök',
            'Gyanús Eszközök' . $counter_badge,
            'manage_options',
            'punktepass-suspicious-devices',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Get count of suspicious devices
     */
    public static function get_suspicious_device_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_device_fingerprints';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return 0;
        }

        return (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT fingerprint_hash)
            FROM {$table}
            GROUP BY fingerprint_hash
            HAVING COUNT(DISTINCT user_id) > 1
        ");
    }

    /**
     * Render suspicious devices page
     */
    public static function render_page() {
        global $wpdb;
        $table_fp = $wpdb->prefix . 'ppv_device_fingerprints';
        $table_users = $wpdb->prefix . 'ppv_users';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_fp}'") !== $table_fp) {
            echo '<div class="wrap"><h1>Gyanús eszközök</h1>';
            echo '<div class="notice notice-warning"><p>A device fingerprint tábla még nem létezik. Kérlek nyisd meg bármelyik admin oldalt, hogy a migráció lefusson.</p></div>';
            echo '</div>';
            return;
        }

        // Get suspicious devices (more than 1 account)
        $devices = $wpdb->get_results("
            SELECT
                fingerprint_hash,
                COUNT(DISTINCT user_id) as account_count,
                MIN(created_at) as first_seen,
                MAX(created_at) as last_seen,
                MAX(ip_address) as last_ip,
                GROUP_CONCAT(DISTINCT user_id ORDER BY created_at ASC) as user_ids
            FROM {$table_fp}
            GROUP BY fingerprint_hash
            HAVING account_count > 1
            ORDER BY account_count DESC, last_seen DESC
            LIMIT 100
        ");

        // Stats
        $total_devices = (int) $wpdb->get_var("SELECT COUNT(DISTINCT fingerprint_hash) FROM {$table_fp}");
        $suspicious_devices = count($devices);
        $max_accounts = $devices ? max(array_column($devices, 'account_count')) : 0;

        // OPTIMIZED: Batch load all users at once instead of N+1 queries per device
        $all_user_ids = [];
        foreach ($devices as $device) {
            $ids = explode(',', $device->user_ids);
            foreach ($ids as $uid) {
                $all_user_ids[intval($uid)] = true;
            }
        }
        $all_user_ids = array_keys($all_user_ids);

        $users_map = [];
        if (!empty($all_user_ids)) {
            $ids_placeholder = implode(',', array_map('intval', $all_user_ids));
            $users_data = $wpdb->get_results("
                SELECT id, email, first_name, last_name, created_at
                FROM {$table_users}
                WHERE id IN ({$ids_placeholder})
            ");
            foreach ($users_data as $u) {
                $users_map[$u->id] = $u;
            }
        }

        ?>
        <div class="wrap">
            <h1>🔍 Gyanús eszközök</h1>
            <p>Olyan eszközök listája, amelyekről több fiók lett regisztrálva. Ez csalásra utalhat.</p>

            <!-- Stats cards -->
            <div style="display: flex; gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-width: 150px;">
                    <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo $total_devices; ?></div>
                    <div style="color: #666;">Összes eszköz</div>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-width: 150px;">
                    <div style="font-size: 32px; font-weight: bold; color: <?php echo $suspicious_devices > 0 ? '#dc3545' : '#28a745'; ?>;">
                        <?php echo $suspicious_devices; ?>
                    </div>
                    <div style="color: #666;">Gyanús eszköz</div>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-width: 150px;">
                    <div style="font-size: 32px; font-weight: bold; color: #856404;"><?php echo $max_accounts; ?></div>
                    <div style="color: #666;">Max fiók/eszköz</div>
                </div>
            </div>

            <?php if (empty($devices)): ?>
                <div class="notice notice-success">
                    <p>✅ Nincs gyanús eszköz! Minden rendben.</p>
                </div>
            <?php else: ?>
                <?php
                // Show success/error messages
                if (isset($_GET['blocked']) && $_GET['blocked'] == '1') {
                    echo '<div class="notice notice-success is-dismissible"><p>🚫 Eszköz sikeresen blokkolva!</p></div>';
                }
                if (isset($_GET['unblocked']) && $_GET['unblocked'] == '1') {
                    echo '<div class="notice notice-success is-dismissible"><p>✅ Eszköz blokkolása feloldva!</p></div>';
                }
                ?>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th style="width: 60px;">⚠️</th>
                            <th>Device ID (hash)</th>
                            <th>Fiókok száma</th>
                            <th>Felhasználók</th>
                            <th>Utolsó IP</th>
                            <th>Első regisztráció</th>
                            <th>Utolsó regisztráció</th>
                            <th style="width: 120px;">Művelet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $device):
                            $user_ids = explode(',', $device->user_ids);
                            $risk_level = $device->account_count >= 3 ? 'high' : 'medium';
                            $risk_color = $risk_level === 'high' ? '#dc3545' : '#ffc107';
                            $risk_icon = $risk_level === 'high' ? '🔴' : '🟡';

                            // Check if device is blocked
                            $is_blocked = class_exists('PPV_Device_Fingerprint') && PPV_Device_Fingerprint::is_device_blocked($device->fingerprint_hash);
                            if ($is_blocked) {
                                $risk_icon = '🚫';
                                $risk_color = '#000';
                            }

                            // Get user details from pre-loaded map (OPTIMIZED - no N+1)
                            $users = [];
                            foreach ($user_ids as $uid) {
                                $uid = intval($uid);
                                if (isset($users_map[$uid])) {
                                    $users[] = $users_map[$uid];
                                }
                            }

                            // Build block/unblock URL
                            $block_url = wp_nonce_url(
                                admin_url('admin-post.php?action=ppv_block_device&hash=' . urlencode($device->fingerprint_hash)),
                                'ppv_block_device_' . $device->fingerprint_hash
                            );
                            $unblock_url = wp_nonce_url(
                                admin_url('admin-post.php?action=ppv_unblock_device&hash=' . urlencode($device->fingerprint_hash)),
                                'ppv_unblock_device_' . $device->fingerprint_hash
                            );
                        ?>
                        <tr style="<?php echo $is_blocked ? 'background: #f8d7da;' : ''; ?>">
                            <td style="text-align: center; font-size: 20px;"><?php echo $risk_icon; ?></td>
                            <td>
                                <code style="font-size: 11px; background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">
                                    <?php echo esc_html(substr($device->fingerprint_hash, 0, 16) . '...'); ?>
                                </code>
                                <?php if ($is_blocked): ?>
                                    <br><span style="color: #721c24; font-size: 11px; font-weight: bold;">BLOKKOLT</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="background: <?php echo $risk_color; ?>; color: #fff; padding: 3px 10px; border-radius: 12px; font-weight: bold;">
                                    <?php echo esc_html($device->account_count); ?> fiók
                                </span>
                            </td>
                            <td>
                                <?php foreach ($users as $u): ?>
                                    <div style="margin-bottom: 5px; padding: 5px; background: #f9f9f9; border-radius: 4px; font-size: 12px;">
                                        <strong>#<?php echo esc_html($u->id); ?></strong>
                                        <?php echo esc_html($u->email); ?>
                                        <?php if ($u->first_name || $u->last_name): ?>
                                            <br><small style="color: #666;"><?php echo esc_html(trim($u->first_name . ' ' . $u->last_name)); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <code style="font-size: 12px;"><?php echo esc_html($device->last_ip ?: '-'); ?></code>
                            </td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($device->first_seen))); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($device->last_seen))); ?></td>
                            <td>
                                <?php if ($is_blocked): ?>
                                    <a href="<?php echo esc_url($unblock_url); ?>" class="button button-small"
                                       onclick="return confirm('Biztosan feloldod a blokkolást?');">
                                        ✅ Feloldás
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo esc_url($block_url); ?>" class="button button-small"
                                       style="background: #dc3545; color: #fff; border-color: #dc3545;"
                                       onclick="return confirm('Biztosan blokkolod ezt az eszközt? Nem tud majd regisztrálni.');">
                                        🚫 Blokkolás
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div style="margin-top: 30px; padding: 20px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                <h3 style="margin-top: 0;">ℹ️ Mit jelent ez?</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    <li><strong>🟡 2 fiók/eszköz:</strong> Lehet legitim (család, közös eszköz)</li>
                    <li><strong>🔴 3+ fiók/eszköz:</strong> Valószínűleg csalás - érdemes ellenőrizni</li>
                </ul>
                <p style="margin-bottom: 0; margin-top: 15px;">
                    <strong>Device fingerprint:</strong> Egyedi azonosító a böngésző és eszköz tulajdonságai alapján (canvas, webgl, fonts, stb.)
                </p>
            </div>
        </div>

        <style>
            .wp-list-table td { vertical-align: middle; }
        </style>
        <?php
    }

    /**
     * Handle device blocking
     */
    public static function handle_block_device() {
        $hash = sanitize_text_field($_GET['hash'] ?? '');

        if (empty($hash)) {
            wp_redirect(admin_url('admin.php?page=punktepass-suspicious-devices&error=missing_hash'));
            exit;
        }

        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'ppv_block_device_' . $hash)) {
            wp_redirect(admin_url('admin.php?page=punktepass-suspicious-devices&error=invalid_nonce'));
            exit;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_redirect(admin_url('admin.php?page=punktepass-suspicious-devices&error=no_permission'));
            exit;
        }

        // Block the device
        if (class_exists('PPV_Device_Fingerprint')) {
            PPV_Device_Fingerprint::block_device($hash, 'Blocked from admin panel', get_current_user_id());
        }

        wp_redirect(admin_url('admin.php?page=punktepass-suspicious-devices&blocked=1'));
        exit;
    }

    /**
     * Handle device unblocking
     */
    public static function handle_unblock_device() {
        $hash = sanitize_text_field($_GET['hash'] ?? '');

        if (empty($hash)) {
            wp_redirect(admin_url('admin.php?page=punktepass-suspicious-devices&error=missing_hash'));
            exit;
        }

        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'ppv_unblock_device_' . $hash)) {
            wp_redirect(admin_url('admin.php?page=punktepass-suspicious-devices&error=invalid_nonce'));
            exit;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_redirect(admin_url('admin.php?page=punktepass-suspicious-devices&error=no_permission'));
            exit;
        }

        // Unblock the device
        if (class_exists('PPV_Device_Fingerprint')) {
            PPV_Device_Fingerprint::unblock_device($hash);
        }

        wp_redirect(admin_url('admin.php?page=punktepass-suspicious-devices&unblocked=1'));
        exit;
    }
}

// Initialize
PPV_Admin_Suspicious_Devices::init();
