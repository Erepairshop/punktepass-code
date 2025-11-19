<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - Handler Admin Management
 * Admin felület handler/bolt kezeléshez
 */
class PPV_Admin_Handlers {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_post_ppv_convert_to_handler', [__CLASS__, 'handle_convert_to_handler']);
        add_action('admin_post_ppv_extend_trial', [__CLASS__, 'handle_extend_trial']);
        add_action('admin_post_ppv_activate_subscription', [__CLASS__, 'handle_activate_subscription']);
        add_action('admin_post_ppv_extend_subscription', [__CLASS__, 'handle_extend_subscription']);
    }

    // ============================================================
    // 📋 ADMIN MENU
    // ============================================================
    public static function add_admin_menu() {
        add_menu_page(
            'PunktePass Admin',           // Page title
            'PunktePass',                  // Menu title
            'manage_options',              // Capability
            'punktepass-admin',            // Menu slug
            [__CLASS__, 'render_handlers_page'], // Callback
            'dashicons-store',             // Icon
            30                             // Position
        );

        add_submenu_page(
            'punktepass-admin',
            'Handler Verwaltung',
            'Handler',
            'manage_options',
            'punktepass-admin',
            [__CLASS__, 'render_handlers_page']
        );
    }

    // ============================================================
    // 🎨 RENDER HANDLERS PAGE
    // ============================================================
    public static function render_handlers_page() {
        global $wpdb;

        // Fetch all handlers/stores
        $handlers = $wpdb->get_results("
            SELECT
                s.id,
                s.name,
                s.company_name,
                s.email,
                s.phone,
                s.city,
                s.trial_ends_at,
                s.subscription_status,
                s.subscription_expires_at,
                s.created_at,
                s.active
            FROM {$wpdb->prefix}ppv_stores s
            ORDER BY s.id DESC
        ");

        ?>
        <div class="wrap">
            <h1>🏪 Handler Verwaltung</h1>

            <!-- Convert User to Handler -->
            <div class="card" style="max-width: 600px; margin-bottom: 20px;">
                <h2>👤 User zu Handler konvertieren</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('ppv_convert_handler', 'ppv_convert_nonce'); ?>
                    <input type="hidden" name="action" value="ppv_convert_to_handler">

                    <table class="form-table">
                        <tr>
                            <th><label for="user_id">User ID</label></th>
                            <td>
                                <input type="number" name="user_id" id="user_id" class="regular-text" required>
                                <p class="description">Geben Sie die WordPress User ID ein</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="company_name">Firma</label></th>
                            <td>
                                <input type="text" name="company_name" id="company_name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="store_email">E-Mail</label></th>
                            <td>
                                <input type="email" name="store_email" id="store_email" class="regular-text" required>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            ✨ Zu Handler konvertieren (30 Tage Trial)
                        </button>
                    </p>
                </form>
            </div>

            <!-- Handlers List -->
            <h2>📋 Handler Liste</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Firma</th>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Stadt</th>
                        <th>Trial Ende</th>
                        <th>Abo Ende</th>
                        <th>Status</th>
                        <th>Erstellt</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($handlers)): ?>
                        <tr>
                            <td colspan="10">Keine Handler gefunden</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($handlers as $handler): ?>
                            <?php
                            $trial_end = !empty($handler->trial_ends_at) ? strtotime($handler->trial_ends_at) : 0;
                            $sub_end = !empty($handler->subscription_expires_at) ? strtotime($handler->subscription_expires_at) : 0;
                            $now = current_time('timestamp');
                            $trial_days_left = $trial_end > 0 ? max(0, ceil(($trial_end - $now) / 86400)) : 0;
                            $sub_days_left = $sub_end > 0 ? max(0, ceil(($sub_end - $now) / 86400)) : 0;

                            // Status badge
                            $status_class = '';
                            $status_text = '';

                            if ($handler->subscription_status === 'active') {
                                if ($sub_days_left > 30) {
                                    $status_class = 'success';
                                    $status_text = "✅ Aktiv ({$sub_days_left} Tage)";
                                } elseif ($sub_days_left > 7) {
                                    $status_class = 'info';
                                    $status_text = "📅 Aktiv ({$sub_days_left} Tage)";
                                } elseif ($sub_days_left > 0) {
                                    $status_class = 'warning';
                                    $status_text = "⚠️ Läuft ab ({$sub_days_left} Tage)";
                                } else {
                                    $status_class = 'error';
                                    $status_text = '❌ Abo abgelaufen';
                                }
                            } elseif ($trial_days_left > 7) {
                                $status_class = 'info';
                                $status_text = "📅 Trial ({$trial_days_left} Tage)";
                            } elseif ($trial_days_left > 0) {
                                $status_class = 'warning';
                                $status_text = "⚠️ Trial ({$trial_days_left} Tage)";
                            } else {
                                $status_class = 'error';
                                $status_text = '❌ Trial abgelaufen';
                            }
                            ?>
                            <tr>
                                <td><?php echo intval($handler->id); ?></td>
                                <td><strong><?php echo esc_html($handler->company_name); ?></strong></td>
                                <td><?php echo esc_html($handler->name); ?></td>
                                <td><?php echo esc_html($handler->email); ?></td>
                                <td><?php echo esc_html($handler->city); ?></td>
                                <td>
                                    <?php
                                    if ($trial_end > 0) {
                                        echo date('Y-m-d', $trial_end);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($sub_end > 0) {
                                        echo '<strong>' . date('Y-m-d', $sub_end) . '</strong>';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($handler->created_at)); ?></td>
                                <td>
                                    <?php if ($handler->subscription_status === 'trial'): ?>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                            <?php wp_nonce_field('ppv_extend_trial', 'ppv_extend_nonce'); ?>
                                            <input type="hidden" name="action" value="ppv_extend_trial">
                                            <input type="hidden" name="handler_id" value="<?php echo intval($handler->id); ?>">
                                            <button type="submit" class="button button-small">
                                                ⏰ +30 Tage
                                            </button>
                                        </form>

                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                            <?php wp_nonce_field('ppv_activate_sub', 'ppv_activate_nonce'); ?>
                                            <input type="hidden" name="action" value="ppv_activate_subscription">
                                            <input type="hidden" name="handler_id" value="<?php echo intval($handler->id); ?>">
                                            <button type="submit" class="button button-primary button-small">
                                                ✅ Aktivieren
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                            <?php wp_nonce_field('ppv_extend_subscription', 'ppv_extend_sub_nonce'); ?>
                                            <input type="hidden" name="action" value="ppv_extend_subscription">
                                            <input type="hidden" name="handler_id" value="<?php echo intval($handler->id); ?>">
                                            <input type="number" name="months" value="6" min="1" max="36" style="width: 60px;" placeholder="6">
                                            <button type="submit" class="button button-primary button-small">
                                                📅 Verlängern
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <style>
            .badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: bold;
            }
            .badge-success {
                background: #d4edda;
                color: #155724;
            }
            .badge-info {
                background: #d1ecf1;
                color: #0c5460;
            }
            .badge-warning {
                background: #fff3cd;
                color: #856404;
            }
            .badge-error {
                background: #f8d7da;
                color: #721c24;
            }
        </style>
        <?php
    }

    // ============================================================
    // 🔄 CONVERT USER TO HANDLER
    // ============================================================
    public static function handle_convert_to_handler() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }

        check_admin_referer('ppv_convert_handler', 'ppv_convert_nonce');

        global $wpdb;

        $user_id = intval($_POST['user_id']);
        $company_name = sanitize_text_field($_POST['company_name']);
        $store_email = sanitize_email($_POST['store_email']);

        // Check if user exists
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            wp_redirect(admin_url('admin.php?page=punktepass-admin&error=user_not_found'));
            exit;
        }

        // Check if already a handler
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
            $user_id
        ));

        if ($existing) {
            wp_redirect(admin_url('admin.php?page=punktepass-admin&error=already_handler'));
            exit;
        }

        // Generate store key
        $store_key = bin2hex(random_bytes(16));

        // Calculate trial end (30 days)
        $trial_ends_at = date('Y-m-d H:i:s', strtotime('+30 days'));

        // Create store
        $result = $wpdb->insert(
            "{$wpdb->prefix}ppv_stores",
            [
                'user_id' => $user_id,
                'name' => $user->display_name,
                'company_name' => $company_name,
                'email' => $store_email,
                'store_key' => $store_key,
                'trial_ends_at' => $trial_ends_at,
                'subscription_status' => 'trial',
                'active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if ($result) {
            error_log("✅ [PPV_Admin] User #{$user_id} zu Handler konvertiert. Store ID: {$wpdb->insert_id}");
            wp_redirect(admin_url('admin.php?page=punktepass-admin&success=converted'));
        } else {
            error_log("❌ [PPV_Admin] Konvertierung fehlgeschlagen: " . $wpdb->last_error);
            wp_redirect(admin_url('admin.php?page=punktepass-admin&error=conversion_failed'));
        }
        exit;
    }

    // ============================================================
    // ⏰ EXTEND TRIAL
    // ============================================================
    public static function handle_extend_trial() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }

        check_admin_referer('ppv_extend_trial', 'ppv_extend_nonce');

        global $wpdb;

        $handler_id = intval($_POST['handler_id']);

        // Get current trial end date
        $current_trial = $wpdb->get_var($wpdb->prepare(
            "SELECT trial_ends_at FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $handler_id
        ));

        // Calculate new trial end (+30 days from current end, or from now if expired)
        $current_end = strtotime($current_trial);
        $now = current_time('timestamp');

        if ($current_end > $now) {
            // Extend from current end
            $new_trial_end = date('Y-m-d H:i:s', strtotime('+30 days', $current_end));
        } else {
            // Extend from now
            $new_trial_end = date('Y-m-d H:i:s', strtotime('+30 days', $now));
        }

        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            ['trial_ends_at' => $new_trial_end],
            ['id' => $handler_id],
            ['%s'],
            ['%d']
        );

        error_log("⏰ [PPV_Admin] Trial verlängert für Handler #{$handler_id} bis {$new_trial_end}");

        wp_redirect(admin_url('admin.php?page=punktepass-admin&success=trial_extended'));
        exit;
    }

    // ============================================================
    // ✅ ACTIVATE SUBSCRIPTION
    // ============================================================
    public static function handle_activate_subscription() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }

        check_admin_referer('ppv_activate_sub', 'ppv_activate_nonce');

        global $wpdb;

        $handler_id = intval($_POST['handler_id']);

        // Set subscription status to active and set 6 months expiry
        $subscription_expires = date('Y-m-d H:i:s', strtotime('+6 months'));

        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            [
                'subscription_status' => 'active',
                'subscription_expires_at' => $subscription_expires
            ],
            ['id' => $handler_id],
            ['%s', '%s'],
            ['%d']
        );

        error_log("✅ [PPV_Admin] Subscription aktiviert für Handler #{$handler_id} bis {$subscription_expires}");

        wp_redirect(admin_url('admin.php?page=punktepass-admin&success=subscription_activated'));
        exit;
    }

    // ============================================================
    // 📅 EXTEND SUBSCRIPTION
    // ============================================================
    public static function handle_extend_subscription() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }

        check_admin_referer('ppv_extend_subscription', 'ppv_extend_sub_nonce');

        global $wpdb;

        $handler_id = intval($_POST['handler_id']);
        $months = isset($_POST['months']) ? intval($_POST['months']) : 6;

        // Get current subscription_expires_at
        $current_expires = $wpdb->get_var($wpdb->prepare(
            "SELECT subscription_expires_at FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $handler_id
        ));

        // Calculate new expiry date
        $now = current_time('timestamp');

        if (!empty($current_expires)) {
            $current_end = strtotime($current_expires);
            // If current expiry is in the future, extend from there
            if ($current_end > $now) {
                $new_expires = date('Y-m-d H:i:s', strtotime("+{$months} months", $current_end));
            } else {
                // If expired, extend from now
                $new_expires = date('Y-m-d H:i:s', strtotime("+{$months} months", $now));
            }
        } else {
            // No expiry set yet, extend from now
            $new_expires = date('Y-m-d H:i:s', strtotime("+{$months} months", $now));
        }

        // Update subscription_expires_at
        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            ['subscription_expires_at' => $new_expires],
            ['id' => $handler_id],
            ['%s'],
            ['%d']
        );

        error_log("✅ [PPV_Admin] Subscription verlängert für Handler #{$handler_id} um {$months} Monate bis {$new_expires}");

        wp_redirect(admin_url('admin.php?page=punktepass-admin&success=subscription_extended'));
        exit;
    }
}

// Initialize
PPV_Admin_Handlers::init();
