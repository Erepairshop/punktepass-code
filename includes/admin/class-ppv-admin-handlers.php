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
        add_action('admin_post_ppv_mark_renewal_done', [__CLASS__, 'handle_mark_renewal_done']);
        add_action('admin_post_ppv_update_ticket_status', [__CLASS__, 'handle_update_ticket_status']);
        add_action('admin_post_ppv_update_max_filialen', [__CLASS__, 'handle_update_max_filialen']);
        add_action('admin_post_ppv_update_scanner_type', [__CLASS__, 'handle_update_scanner_type']);
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
            'Kereskedő kezelés',
            'Kereskedők',
            'manage_options',
            'punktepass-admin',
            [__CLASS__, 'render_handlers_page']
        );

        // 📧 Megújítási kérelmek
        add_submenu_page(
            'punktepass-admin',
            'Megújítási kérelmek',
            'Megújítási kérelmek',
            'manage_options',
            'punktepass-renewal-requests',
            [__CLASS__, 'render_renewal_requests_page']
        );

        // 🆘 Támogatási jegyek (számlálóval)
        global $wpdb;
        $open_tickets_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE status IN ('new', 'in_progress')");
        $counter_badge = $open_tickets_count > 0 ? " <span class='awaiting-mod'>$open_tickets_count</span>" : "";

        add_submenu_page(
            'punktepass-admin',
            'Támogatási jegyek',
            'Támogatás' . $counter_badge,
            'manage_options',
            'punktepass-support-tickets',
            [__CLASS__, 'render_support_tickets_page']
        );
    }

    // ============================================================
    // 🎨 RENDER HANDLERS PAGE
    // ============================================================
    public static function render_handlers_page() {
        global $wpdb;

        // Fetch all handlers/stores (only parent stores, not filialen)
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
                s.active,
                s.max_filialen,
                s.scanner_type,
                (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE parent_store_id = s.id) as filiale_count
            FROM {$wpdb->prefix}ppv_stores s
            WHERE s.parent_store_id IS NULL
            ORDER BY s.id DESC
        ");

        ?>
        <div class="wrap">
            <h1>🏪 Kereskedő kezelés</h1>

            <!-- Felhasználó kereskedővé alakítása -->
            <div class="card" style="max-width: 600px; margin-bottom: 20px;">
                <h2>👤 Felhasználó kereskedővé alakítása</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('ppv_convert_handler', 'ppv_convert_nonce'); ?>
                    <input type="hidden" name="action" value="ppv_convert_to_handler">

                    <table class="form-table">
                        <tr>
                            <th><label for="user_id">Felhasználó ID</label></th>
                            <td>
                                <input type="number" name="user_id" id="user_id" class="regular-text" required>
                                <p class="description">Adja meg a WordPress felhasználó ID-t</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="company_name">Cégnév</label></th>
                            <td>
                                <input type="text" name="company_name" id="company_name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="store_email">E-mail</label></th>
                            <td>
                                <input type="email" name="store_email" id="store_email" class="regular-text" required>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            ✨ Kereskedővé alakítás (30 nap próba)
                        </button>
                    </p>
                </form>
            </div>

            <!-- Kereskedők listája -->
            <h2>📋 Kereskedők listája</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cégnév</th>
                        <th>Név</th>
                        <th>E-mail</th>
                        <th>Város</th>
                        <th>Fiókok</th>
                        <th>Mobil</th>
                        <th>Próba vége</th>
                        <th>Előfiz. vége</th>
                        <th>Státusz</th>
                        <th>Létrehozva</th>
                        <th>Műveletek</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($handlers)): ?>
                        <tr>
                            <td colspan="12">Nincs kereskedő</td>
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
                                    $status_text = "✅ Aktív ({$sub_days_left} nap)";
                                } elseif ($sub_days_left > 7) {
                                    $status_class = 'info';
                                    $status_text = "📅 Aktív ({$sub_days_left} nap)";
                                } elseif ($sub_days_left > 0) {
                                    $status_class = 'warning';
                                    $status_text = "⚠️ Lejár ({$sub_days_left} nap)";
                                } else {
                                    $status_class = 'error';
                                    $status_text = '❌ Előfizetés lejárt';
                                }
                            } elseif ($trial_days_left > 7) {
                                $status_class = 'info';
                                $status_text = "📅 Próba ({$trial_days_left} nap)";
                            } elseif ($trial_days_left > 0) {
                                $status_class = 'warning';
                                $status_text = "⚠️ Próba ({$trial_days_left} nap)";
                            } else {
                                $status_class = 'error';
                                $status_text = '❌ Próba lejárt';
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
                                    $current_filialen = intval($handler->filiale_count) + 1; // +1 for parent
                                    $max_filialen = intval($handler->max_filialen) ?: 1;
                                    ?>
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: flex; align-items: center; gap: 5px;">
                                        <?php wp_nonce_field('ppv_update_max_filialen', 'ppv_filialen_nonce'); ?>
                                        <input type="hidden" name="action" value="ppv_update_max_filialen">
                                        <input type="hidden" name="handler_id" value="<?php echo intval($handler->id); ?>">
                                        <span style="color: #666; font-size: 11px;"><?php echo $current_filialen; ?>/</span>
                                        <input type="number" name="max_filialen" value="<?php echo $max_filialen; ?>" min="1" max="100" style="width: 50px; padding: 2px 4px; font-size: 12px;">
                                        <button type="submit" class="button button-small" style="padding: 0 6px; height: 24px; line-height: 22px;">💾</button>
                                    </form>
                                </td>
                                <td>
                                    <?php
                                    $is_mobile = ($handler->scanner_type === 'mobile');
                                    ?>
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                        <?php wp_nonce_field('ppv_update_scanner_type', 'ppv_scanner_type_nonce'); ?>
                                        <input type="hidden" name="action" value="ppv_update_scanner_type">
                                        <input type="hidden" name="handler_id" value="<?php echo intval($handler->id); ?>">
                                        <input type="hidden" name="scanner_type" value="<?php echo $is_mobile ? 'fixed' : 'mobile'; ?>">
                                        <button type="submit" class="button button-small <?php echo $is_mobile ? 'button-primary' : ''; ?>" style="min-width: 50px;">
                                            <?php echo $is_mobile ? '📱 BE' : '🏪 KI'; ?>
                                        </button>
                                    </form>
                                </td>
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
                                                ⏰ +30 nap
                                            </button>
                                        </form>

                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                            <?php wp_nonce_field('ppv_activate_sub', 'ppv_activate_nonce'); ?>
                                            <input type="hidden" name="action" value="ppv_activate_subscription">
                                            <input type="hidden" name="handler_id" value="<?php echo intval($handler->id); ?>">
                                            <button type="submit" class="button button-primary button-small">
                                                ✅ Aktiválás
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                            <?php wp_nonce_field('ppv_extend_subscription', 'ppv_extend_sub_nonce'); ?>
                                            <input type="hidden" name="action" value="ppv_extend_subscription">
                                            <input type="hidden" name="handler_id" value="<?php echo intval($handler->id); ?>">
                                            <input type="number" name="months" value="6" min="1" max="36" style="width: 60px;" placeholder="6">
                                            <button type="submit" class="button button-primary button-small">
                                                📅 Hosszabbítás
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
            wp_die('Nincs jogosultság');
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
            wp_die('Nincs jogosultság');
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
            wp_die('Nincs jogosultság');
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
            wp_die('Nincs jogosultság');
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

    // ============================================================
    // 📧 RENDER RENEWAL REQUESTS PAGE
    // ============================================================
    public static function render_renewal_requests_page() {
        global $wpdb;

        // Fetch handlers with renewal requests
        $requests = $wpdb->get_results("
            SELECT
                s.id,
                s.name,
                s.company_name,
                s.email,
                s.phone,
                s.renewal_phone,
                s.city,
                s.subscription_renewal_requested,
                s.subscription_status,
                s.trial_ends_at,
                s.subscription_expires_at,
                s.created_at
            FROM {$wpdb->prefix}ppv_stores s
            WHERE s.subscription_renewal_requested IS NOT NULL
            ORDER BY s.subscription_renewal_requested DESC
        ");

        $open_count = count($requests);

        ?>
        <div class="wrap">
            <h1>📧 Megújítási kérelmek (<?php echo $open_count; ?> nyitott)</h1>
            <p>Kereskedők, akik előfizetés hosszabbítást kértek.</p>

            <?php if ($open_count === 0): ?>
                <div class="notice notice-info">
                    <p>✅ Nincs nyitott megújítási kérelem!</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cégnév</th>
                            <th>E-mail</th>
                            <th>Telefon</th>
                            <th>Megújítási telefon</th>
                            <th>Város</th>
                            <th>Kérelem dátuma</th>
                            <th>Státusz</th>
                            <th>Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <?php
                            // Calculate days
                            $now = current_time('timestamp');
                            $trial_end = !empty($request->trial_ends_at) ? strtotime($request->trial_ends_at) : 0;
                            $sub_end = !empty($request->subscription_expires_at) ? strtotime($request->subscription_expires_at) : 0;

                            $trial_days_left = $trial_end > 0 ? max(0, ceil(($trial_end - $now) / 86400)) : 0;
                            $sub_days_left = $sub_end > 0 ? max(0, ceil(($sub_end - $now) / 86400)) : 0;

                            // Status badge
                            if ($request->subscription_status === 'active') {
                                if ($sub_days_left > 0) {
                                    $status_text = "✅ Aktív ({$sub_days_left} nap)";
                                    $status_class = 'success';
                                } else {
                                    $status_text = '❌ Előfizetés lejárt';
                                    $status_class = 'error';
                                }
                            } else {
                                if ($trial_days_left > 0) {
                                    $status_text = "📅 Próba ({$trial_days_left} nap)";
                                    $status_class = 'info';
                                } else {
                                    $status_text = '❌ Próba lejárt';
                                    $status_class = 'error';
                                }
                            }

                            // Requested time
                            $requested_time = date('Y-m-d H:i', strtotime($request->subscription_renewal_requested));
                            ?>
                            <tr>
                                <td><?php echo intval($request->id); ?></td>
                                <td><strong><?php echo esc_html($request->company_name ?: $request->name); ?></strong></td>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($request->email); ?>">
                                        <?php echo esc_html($request->email); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($request->phone)): ?>
                                        <a href="tel:<?php echo esc_attr($request->phone); ?>">
                                            <?php echo esc_html($request->phone); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($request->renewal_phone)): ?>
                                        <strong style="color: #00a0d2;">
                                            <a href="tel:<?php echo esc_attr($request->renewal_phone); ?>">
                                                📞 <?php echo esc_html($request->renewal_phone); ?>
                                            </a>
                                        </strong>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($request->city); ?></td>
                                <td><?php echo $requested_time; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- Készként jelölés -->
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                        <?php wp_nonce_field('ppv_mark_renewal_done', 'ppv_renewal_done_nonce'); ?>
                                        <input type="hidden" name="action" value="ppv_mark_renewal_done">
                                        <input type="hidden" name="handler_id" value="<?php echo intval($request->id); ?>">
                                        <button type="submit" class="button button-primary button-small" onclick="return confirm('Készként jelöli?');">
                                            ✅ Kész
                                        </button>
                                    </form>

                                    <!-- Gyors műveletek -->
                                    <a href="mailto:<?php echo esc_attr($request->email); ?>?subject=El%C5%91fizet%C3%A9s%20hosszabb%C3%ADt%C3%A1s" class="button button-small">
                                        📧 E-mail
                                    </a>
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
    // ✅ MARK RENEWAL AS DONE
    // ============================================================
    public static function handle_mark_renewal_done() {
        if (!current_user_can('manage_options')) {
            wp_die('Nincs jogosultság');
        }

        check_admin_referer('ppv_mark_renewal_done', 'ppv_renewal_done_nonce');

        global $wpdb;

        $handler_id = intval($_POST['handler_id']);

        // Clear renewal request fields
        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            [
                'subscription_renewal_requested' => NULL,
                'renewal_phone' => NULL
            ],
            ['id' => $handler_id],
            ['%s', '%s'],
            ['%d']
        );

        error_log("✅ [PPV_Admin] Renewal request marked as done for Handler #{$handler_id}");

        wp_redirect(admin_url('admin.php?page=punktepass-renewal-requests&success=marked_done'));
        exit;
    }

    // ============================================================
    // 🆘 RENDER SUPPORT TICKETS PAGE
    // ============================================================
    public static function render_support_tickets_page() {
        global $wpdb;

        // Get filter status (default: all open tickets)
        $status_filter = isset($_GET['ticket_status']) ? sanitize_text_field($_GET['ticket_status']) : 'open';

        // Build query based on filter
        $where_clause = '';
        if ($status_filter === 'open') {
            $where_clause = "WHERE t.status IN ('new', 'in_progress')";
        } elseif ($status_filter === 'resolved') {
            $where_clause = "WHERE t.status = 'resolved'";
        } elseif ($status_filter === 'new') {
            $where_clause = "WHERE t.status = 'new'";
        } elseif ($status_filter === 'in_progress') {
            $where_clause = "WHERE t.status = 'in_progress'";
        }

        // Fetch support tickets
        $tickets = $wpdb->get_results("
            SELECT
                t.*,
                s.name as store_name_db,
                s.company_name,
                s.city
            FROM {$wpdb->prefix}ppv_support_tickets t
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON t.store_id = s.id
            {$where_clause}
            ORDER BY
                FIELD(t.status, 'new', 'in_progress', 'resolved'),
                FIELD(t.priority, 'urgent', 'normal', 'low'),
                t.created_at DESC
        ");

        $ticket_count = count($tickets);

        // Count by status
        $new_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE status = 'new'");
        $in_progress_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE status = 'in_progress'");
        $open_count = $new_count + $in_progress_count;
        $resolved_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE status = 'resolved'");

        ?>
        <div class="wrap">
            <h1>🆘 Támogatási jegyek (<?php echo $open_count; ?> nyitott)</h1>
            <p>Kereskedők támogatási kérelmeinek kezelése.</p>

            <!-- Szűrő fülek -->
            <div class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="<?php echo admin_url('admin.php?page=punktepass-support-tickets&ticket_status=open'); ?>"
                   class="nav-tab <?php echo $status_filter === 'open' ? 'nav-tab-active' : ''; ?>">
                    🟡 Nyitott (<?php echo $open_count; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=punktepass-support-tickets&ticket_status=new'); ?>"
                   class="nav-tab <?php echo $status_filter === 'new' ? 'nav-tab-active' : ''; ?>">
                    🆕 Új (<?php echo $new_count; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=punktepass-support-tickets&ticket_status=in_progress'); ?>"
                   class="nav-tab <?php echo $status_filter === 'in_progress' ? 'nav-tab-active' : ''; ?>">
                    🔄 Folyamatban (<?php echo $in_progress_count; ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=punktepass-support-tickets&ticket_status=resolved'); ?>"
                   class="nav-tab <?php echo $status_filter === 'resolved' ? 'nav-tab-active' : ''; ?>">
                    ✅ Megoldva (<?php echo $resolved_count; ?>)
                </a>
            </div>

            <?php if ($ticket_count === 0): ?>
                <div class="notice notice-info">
                    <p>✅ Nincs jegy ebben a kategóriában!</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 80px;">Prioritás</th>
                            <th style="width: 100px;">Státusz</th>
                            <th>Cégnév</th>
                            <th>Kapcsolat</th>
                            <th>Probléma</th>
                            <th style="width: 140px;">Létrehozva</th>
                            <th style="width: 200px;">Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <?php
                            // Priority badge
                            $priority_badges = [
                                'low' => ['text' => '🟢 Alacsony', 'class' => 'success'],
                                'normal' => ['text' => '🟡 Normál', 'class' => 'warning'],
                                'urgent' => ['text' => '🔴 Sürgős', 'class' => 'error']
                            ];
                            $priority_badge = $priority_badges[$ticket->priority] ?? $priority_badges['normal'];

                            // Status badge
                            $status_badges = [
                                'new' => ['text' => '🆕 Új', 'class' => 'info'],
                                'in_progress' => ['text' => '🔄 Folyamatban', 'class' => 'warning'],
                                'resolved' => ['text' => '✅ Megoldva', 'class' => 'success']
                            ];
                            $status_badge = $status_badges[$ticket->status] ?? $status_badges['new'];

                            // Contact preference
                            $contact_icons = [
                                'email' => '📧',
                                'phone' => '📞',
                                'whatsapp' => '💬'
                            ];
                            $contact_icon = $contact_icons[$ticket->contact_preference] ?? '📧';

                            // Format time
                            $created_time = date('Y-m-d H:i', strtotime($ticket->created_at));

                            // Truncate description
                            $description_short = mb_strlen($ticket->description) > 80
                                ? mb_substr($ticket->description, 0, 80) . '...'
                                : $ticket->description;
                            ?>
                            <tr style="<?php echo $ticket->priority === 'urgent' ? 'background: #fff5f5;' : ''; ?>">
                                <td><strong>#<?php echo intval($ticket->id); ?></strong></td>
                                <td>
                                    <span class="badge badge-<?php echo $priority_badge['class']; ?>">
                                        <?php echo $priority_badge['text']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $status_badge['class']; ?>">
                                        <?php echo $status_badge['text']; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($ticket->company_name ?: $ticket->store_name); ?></strong>
                                    <?php if (!empty($ticket->city)): ?>
                                        <br><small style="color: #666;"><?php echo esc_html($ticket->city); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $contact_icon; ?>
                                    <a href="mailto:<?php echo esc_attr($ticket->handler_email); ?>" style="text-decoration: none;">
                                        <?php echo esc_html($ticket->handler_email); ?>
                                    </a>
                                    <?php if (!empty($ticket->handler_phone)): ?>
                                        <br>
                                        <a href="tel:<?php echo esc_attr($ticket->handler_phone); ?>" style="text-decoration: none;">
                                            📞 <?php echo esc_html($ticket->handler_phone); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 300px;">
                                        <div title="<?php echo esc_attr($ticket->description); ?>">
                                            <?php echo esc_html($description_short); ?>
                                        </div>
                                        <?php if (!empty($ticket->page_url)): ?>
                                            <small style="color: #666;">
                                                🌐 <a href="<?php echo esc_url($ticket->page_url); ?>" target="_blank">Oldal</a>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo $created_time; ?></td>
                                <td>
                                    <?php if ($ticket->status === 'new'): ?>
                                        <!-- Folyamatba vétel -->
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                            <?php wp_nonce_field('ppv_update_ticket_status', 'ppv_ticket_status_nonce'); ?>
                                            <input type="hidden" name="action" value="ppv_update_ticket_status">
                                            <input type="hidden" name="ticket_id" value="<?php echo intval($ticket->id); ?>">
                                            <input type="hidden" name="new_status" value="in_progress">
                                            <button type="submit" class="button button-small" style="background: #ffb900; color: #fff; border: none;">
                                                🔄 Felvétel
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($ticket->status !== 'resolved'): ?>
                                        <!-- Megoldottként jelölés -->
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
                                            <?php wp_nonce_field('ppv_update_ticket_status', 'ppv_ticket_status_nonce'); ?>
                                            <input type="hidden" name="action" value="ppv_update_ticket_status">
                                            <input type="hidden" name="ticket_id" value="<?php echo intval($ticket->id); ?>">
                                            <input type="hidden" name="new_status" value="resolved">
                                            <button type="submit" class="button button-primary button-small" onclick="return confirm('Megoldottként jelöli a jegyet?');">
                                                ✅ Megoldva
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- E-mail küldés -->
                                    <a href="mailto:<?php echo esc_attr($ticket->handler_email); ?>?subject=T%C3%A1mogat%C3%A1si%20jegy%20%23<?php echo intval($ticket->id); ?>"
                                       class="button button-small">
                                        📧 E-mail
                                    </a>
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
    // 🔄 UPDATE TICKET STATUS
    // ============================================================
    public static function handle_update_ticket_status() {
        if (!current_user_can('manage_options')) {
            wp_die('Nincs jogosultság');
        }

        check_admin_referer('ppv_update_ticket_status', 'ppv_ticket_status_nonce');

        global $wpdb;

        $ticket_id = intval($_POST['ticket_id']);
        $new_status = sanitize_text_field($_POST['new_status']);

        // Validate status
        if (!in_array($new_status, ['new', 'in_progress', 'resolved'])) {
            wp_die('Érvénytelen státusz');
        }

        // Update ticket status
        $update_data = ['status' => $new_status];

        // If resolving, set resolved_at timestamp
        if ($new_status === 'resolved') {
            $update_data['resolved_at'] = current_time('mysql');
        }

        $wpdb->update(
            "{$wpdb->prefix}ppv_support_tickets",
            $update_data,
            ['id' => $ticket_id],
            $new_status === 'resolved' ? ['%s', '%s'] : ['%s'],
            ['%d']
        );

        error_log("✅ [PPV_Support] Ticket #{$ticket_id} status updated to {$new_status}");

        wp_redirect(admin_url('admin.php?page=punktepass-support-tickets&success=status_updated'));
        exit;
    }

    // ============================================================
    // 🏪 UPDATE MAX FILIALEN
    // ============================================================
    public static function handle_update_max_filialen() {
        if (!current_user_can('manage_options')) {
            wp_die('Nincs jogosultság');
        }

        check_admin_referer('ppv_update_max_filialen', 'ppv_filialen_nonce');

        global $wpdb;

        $handler_id = intval($_POST['handler_id']);
        $max_filialen = intval($_POST['max_filialen']);

        // Ensure minimum of 1
        if ($max_filialen < 1) {
            $max_filialen = 1;
        }

        // Update max_filialen for the handler
        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            ['max_filialen' => $max_filialen],
            ['id' => $handler_id],
            ['%d'],
            ['%d']
        );

        error_log("✅ [PPV_Admin] Max Filialen updated for Handler #{$handler_id} to {$max_filialen}");

        wp_redirect(admin_url('admin.php?page=punktepass-admin&success=filialen_updated'));
        exit;
    }

    // ============================================================
    // 📱 UPDATE SCANNER TYPE (MOBILE/FIXED)
    // ============================================================
    public static function handle_update_scanner_type() {
        if (!current_user_can('manage_options')) {
            wp_die('Nincs jogosultság');
        }

        check_admin_referer('ppv_update_scanner_type', 'ppv_scanner_type_nonce');

        global $wpdb;

        $handler_id = intval($_POST['handler_id']);
        $scanner_type = sanitize_text_field($_POST['scanner_type']);

        // Validate scanner type
        if (!in_array($scanner_type, ['fixed', 'mobile'])) {
            $scanner_type = 'fixed';
        }

        // Update scanner_type for the handler and all its filialen
        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            ['scanner_type' => $scanner_type],
            ['id' => $handler_id],
            ['%s'],
            ['%d']
        );

        // Also update all filialen of this handler
        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            ['scanner_type' => $scanner_type],
            ['parent_store_id' => $handler_id],
            ['%s'],
            ['%d']
        );

        $type_label = ($scanner_type === 'mobile') ? 'Mobile' : 'Fixed';
        error_log("📱 [PPV_Admin] Scanner type updated for Handler #{$handler_id} to {$type_label}");

        wp_redirect(admin_url('admin.php?page=punktepass-admin&success=scanner_type_updated'));
        exit;
    }
}

// Initialize
PPV_Admin_Handlers::init();
